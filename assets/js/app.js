const API = 'api/productos.php';
const UPLOAD = 'api/upload.php';

const categorias = {
  oficina: 'Oficina',
  hogar: 'Hogar',
  tecnologia: 'Tecnología',
  papeleria: 'Papelería',
  otro: 'Otro',
};

const els = {
  tabla: document.getElementById('tablaCuerpo'),
  vacio: document.getElementById('vacio'),
  alerta: document.getElementById('alerta'),
  buscar: document.getElementById('buscar'),
  contador: document.getElementById('contador'),
  form: document.getElementById('formProducto'),
  id: document.getElementById('productoId'),
  previewImg: document.getElementById('previewImg'),
  previewEmpty: document.getElementById('previewEmpty'),
  imagen: document.getElementById('imagen'),
  titulo: document.getElementById('modalTitulo'),
  eliminarNombre: document.getElementById('eliminarNombre'),
  statTotal: document.getElementById('statTotal'),
  statActivos: document.getElementById('statActivos'),
  statStock: document.getElementById('statStock'),
};

const modalProducto = new bootstrap.Modal(document.getElementById('modalProducto'));
const modalEliminar = new bootstrap.Modal(document.getElementById('modalEliminar'));

let registros = [];
let idEliminar = null;
let imagenActual = null;

function mostrarAlerta(tipo, mensaje) {
  els.alerta.className = `alert alert-${tipo}`;
  els.alerta.textContent = mensaje;
  els.alerta.classList.remove('d-none');
  setTimeout(() => els.alerta.classList.add('d-none'), 4500);
}

async function api(url, options = {}) {
  const res = await fetch(url, options);
  const data = await res.json().catch(() => ({ ok: false, error: 'Respuesta no válida' }));
  if (!res.ok || data.ok === false) {
    throw new Error(data.error || 'Error de servidor');
  }
  return data;
}

function formatoPrecio(n) {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n) || 0);
}

function formatoFecha(iso) {
  if (!iso) return '—';
  const [y, m, d] = String(iso).slice(0, 10).split('-');
  return `${d}/${m}/${y}`;
}

function setPreview(url) {
  if (url) {
    els.previewImg.src = url;
    els.previewImg.hidden = false;
    els.previewEmpty.hidden = true;
  } else {
    els.previewImg.removeAttribute('src');
    els.previewImg.hidden = true;
    els.previewEmpty.hidden = false;
  }
}

function filtrar() {
  const q = els.buscar.value.trim().toLowerCase();
  const lista = !q
    ? registros
    : registros.filter((r) =>
        [r.nombre, r.categoria, r.email_contacto, r.descripcion]
          .filter(Boolean)
          .some((v) => String(v).toLowerCase().includes(q))
      );
  pintar(lista);
}

function pintar(lista) {
  els.contador.textContent = `${lista.length} elemento${lista.length === 1 ? '' : 's'}`;
  els.statTotal.textContent = String(registros.length);
  els.statActivos.textContent = String(registros.filter((r) => r.activo).length);
  els.statStock.textContent = String(registros.reduce((a, r) => a + Number(r.stock || 0), 0));

  els.tabla.innerHTML = '';
  if (!lista.length) {
    els.vacio.classList.remove('d-none');
    return;
  }
  els.vacio.classList.add('d-none');

  for (const item of lista) {
    const tr = document.createElement('tr');
    const img = item.imagen_url
      ? `<img class="thumb" src="${item.imagen_url}" alt="">`
      : `<div class="thumb-empty"><i class="bi bi-image"></i></div>`;
    tr.innerHTML = `
      <td>${img}</td>
      <td>
        <div class="fw-semibold">${escapeHtml(item.nombre)}</div>
        <div class="small text-muted">${escapeHtml(item.email_contacto || item.descripcion || '')}</div>
      </td>
      <td><span class="cat-pill">${categorias[item.categoria] || item.categoria}</span></td>
      <td>${formatoPrecio(item.precio)}</td>
      <td>${item.stock}</td>
      <td>${formatoFecha(item.fecha_ingreso)}</td>
      <td><span class="badge ${item.activo ? 'badge-on' : 'badge-off'}">${item.activo ? 'Activo' : 'Inactivo'}</span></td>
      <td class="text-end text-nowrap">
        <button type="button" class="icon-action" data-edit="${item.id}" title="Editar"><i class="bi bi-pencil"></i></button>
        <button type="button" class="icon-action danger" data-del="${item.id}" title="Eliminar"><i class="bi bi-trash"></i></button>
      </td>
    `;
    els.tabla.appendChild(tr);
  }
}

function escapeHtml(str) {
  return String(str)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;');
}

function hoy() {
  return new Date().toISOString().slice(0, 10);
}

function abrirNuevo() {
  els.form.reset();
  els.id.value = '';
  imagenActual = null;
  document.getElementById('activo').checked = true;
  document.getElementById('fecha_ingreso').value = hoy();
  document.getElementById('precio').value = '0';
  document.getElementById('stock').value = '0';
  setPreview(null);
  els.titulo.textContent = 'Nuevo producto';
  modalProducto.show();
}

function abrirEditar(item) {
  els.form.reset();
  els.id.value = item.id;
  imagenActual = item.imagen_url || null;
  document.getElementById('nombre').value = item.nombre || '';
  document.getElementById('descripcion').value = item.descripcion || '';
  document.getElementById('categoria').value = item.categoria || 'oficina';
  document.getElementById('precio').value = item.precio ?? 0;
  document.getElementById('stock').value = item.stock ?? 0;
  document.getElementById('fecha_ingreso').value = String(item.fecha_ingreso || hoy()).slice(0, 10);
  document.getElementById('email_contacto').value = item.email_contacto || '';
  document.getElementById('activo').checked = Boolean(item.activo);
  setPreview(imagenActual);
  els.titulo.textContent = 'Editar producto';
  modalProducto.show();
}

async function cargar() {
  try {
    const { data } = await api(API);
    registros = Array.isArray(data) ? data : [];
    filtrar();
  } catch (err) {
    registros = [];
    pintar([]);
    mostrarAlerta('danger', err.message);
  }
}

async function subirImagenSiHay() {
  const file = els.imagen.files[0];
  if (!file) return imagenActual;
  const fd = new FormData();
  fd.append('imagen', file);
  const { url } = await api(UPLOAD, { method: 'POST', body: fd });
  return url;
}

els.imagen.addEventListener('change', () => {
  const file = els.imagen.files[0];
  if (!file) return;
  const url = URL.createObjectURL(file);
  setPreview(url);
});

document.getElementById('btnNuevo').addEventListener('click', abrirNuevo);
document.getElementById('btnNuevoVacio').addEventListener('click', abrirNuevo);
document.getElementById('btnRecargar').addEventListener('click', cargar);
els.buscar.addEventListener('input', filtrar);

els.tabla.addEventListener('click', (e) => {
  const edit = e.target.closest('[data-edit]');
  const del = e.target.closest('[data-del]');
  if (edit) {
    const item = registros.find((r) => r.id === edit.dataset.edit);
    if (item) abrirEditar(item);
  }
  if (del) {
    const item = registros.find((r) => r.id === del.dataset.del);
    if (!item) return;
    idEliminar = item.id;
    els.eliminarNombre.textContent = item.nombre;
    modalEliminar.show();
  }
});

document.getElementById('btnConfirmarEliminar').addEventListener('click', async () => {
  try {
    await api(`${API}?id=${encodeURIComponent(idEliminar)}`, { method: 'DELETE' });
    modalEliminar.hide();
    mostrarAlerta('success', 'Registro eliminado.');
    await cargar();
  } catch (err) {
    mostrarAlerta('danger', err.message);
  }
});

els.form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('btnGuardar');
  btn.disabled = true;
  try {
    const imagen_url = await subirImagenSiHay();
    const payload = {
      nombre: document.getElementById('nombre').value.trim(),
      descripcion: document.getElementById('descripcion').value.trim(),
      categoria: document.getElementById('categoria').value,
      precio: Number(document.getElementById('precio').value || 0),
      stock: Number(document.getElementById('stock').value || 0),
      fecha_ingreso: document.getElementById('fecha_ingreso').value,
      activo: document.getElementById('activo').checked,
      email_contacto: document.getElementById('email_contacto').value.trim(),
      imagen_url,
    };
    const id = els.id.value;
    if (id) {
      await api(`${API}?id=${encodeURIComponent(id)}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      mostrarAlerta('success', 'Producto actualizado.');
    } else {
      await api(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      mostrarAlerta('success', 'Producto creado.');
    }
    modalProducto.hide();
    await cargar();
  } catch (err) {
    mostrarAlerta('danger', err.message);
  } finally {
    btn.disabled = false;
  }
});

cargar();
