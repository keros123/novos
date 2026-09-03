# Flujo de datos del CRUD

La aplicación no habla con Supabase desde el navegador. El JavaScript llama a PHP; PHP autentica con la `service_role` y consulta PostgREST o Storage.

```
Navegador (index.html + assets/js/app.js)
        │  fetch JSON / FormData
        ▼
PHP (api/productos.php · api/upload.php)
        │  cURL + service_role
        ▼
Supabase (Postgres: tabla productos · Storage: bucket productos)
```

Las claves viven en `.env` (`SUPABASE_URL`, `SUPABASE_SERVICE_ROLE_KEY`, `STORAGE_BUCKET`), cargadas por `config.php` y usadas en `lib/supabase.php`.

---

## Visión general por operación

| Función | UI | HTTP local | Supabase |
|---|---|---|---|
| Listar | `cargar()` | `GET api/productos.php` | `GET /rest/v1/productos` |
| Leer uno | (API disponible; la UI usa la lista en memoria) | `GET api/productos.php?id=` | `GET /rest/v1/productos?id=eq.{id}` |
| Crear | envío del formulario sin `id` | `POST api/productos.php` | `POST /rest/v1/productos` |
| Actualizar | envío del formulario con `id` | `PUT api/productos.php?id=` | `PATCH /rest/v1/productos?id=eq.{id}` |
| Eliminar | confirmación en modal | `DELETE api/productos.php?id=` | Storage `DELETE` + `DELETE /rest/v1/productos` |
| Subir imagen | archivo en el formulario | `POST api/upload.php` | `POST /storage/v1/object/productos/{path}` |
| Filtrar | `filtrar()` | ninguno | ninguno (memoria) |

---

## 1. Listar productos — `cargar()`

**Origen:** carga inicial de `app.js` y botón Actualizar (`btnRecargar`).

```
cargar()
  → GET api/productos.php
      → GET {supabase}/rest/v1/productos?select=*&order=created_at.desc
  ← { ok: true, data: [ ...filas ] }
  → registros = data
  → filtrar() → pintar(lista)
```

**Pasos**

1. `api()` hace `fetch` a `api/productos.php` sin cuerpo.
2. PHP entra en `case 'GET'` sin `?id`.
3. `supabase_request()` añade `apikey` y `Authorization: Bearer` (service role) y pide todas las columnas, ordenadas por `created_at` descendente.
4. PostgREST devuelve un arreglo JSON. PHP lo envuelve en `{ ok, data }`.
5. El cliente guarda el arreglo en `registros` (estado en memoria).
6. `filtrar()` aplica el texto de búsqueda si hay; `pintar()` actualiza tabla, contador y tarjetas (total, activos, unidades).

**Datos que viajan de vuelta:** `id`, `nombre`, `descripcion`, `categoria`, `precio`, `stock`, `fecha_ingreso`, `activo`, `email_contacto`, `imagen_url`, `created_at`, `updated_at`.

Las miniaturas de la tabla usan `imagen_url` (URL pública de Storage). El navegador las pide directo a Supabase, no a PHP.

**Error:** se vacía `registros`, se pinta lista vacía y se muestra alerta.

---

## 2. Leer un producto — `GET` con `id`

La interfaz de edición **no** llama a este endpoint: busca el registro en `registros` y abre el modal. El endpoint existe para consulta puntual.

```
GET api/productos.php?id={uuid}
  → GET /rest/v1/productos?id=eq.{uuid}&select=*
  ← { ok: true, data: { ...fila } }
     o 404 si no hay fila
```

---

## 3. Abrir formulario nuevo — `abrirNuevo()`

No hay red. Solo estado de UI.

1. Se limpia el formulario y `productoId`.
2. `imagenActual = null`.
3. Valores por defecto: activo, fecha de hoy, precio y stock en `0`.
4. Vista previa vacía.
5. Se abre el modal «Nuevo producto».

---

## 4. Abrir formulario de edición — `abrirEditar(item)`

Tampoco hay red. El `item` sale de `registros` al pulsar el lápiz (`data-edit`).

1. Se rellenan los campos del DOM con el objeto en memoria.
2. `imagenActual` queda con la `imagen_url` existente (o `null`).
3. La vista previa muestra esa URL pública.
4. Título del modal: «Editar producto».

---

## 5. Subir imagen — `subirImagenSiHay()` + `api/upload.php`

Se ejecuta **antes** de crear o actualizar, solo si el usuario eligió un archivo.

```
<input type="file" id="imagen">
  → FormData { imagen: File }
  → POST api/upload.php
      validar MIME (jpg/png/webp/gif) y tamaño ≤ 4 MB
      path = items/{año}/{mes}/{hex}.{ext}
      → POST /storage/v1/object/productos/{path}  (cuerpo binario, x-upsert)
  ← { ok: true, url, path }
```

**Pasos**

1. Si no hay archivo, se reutiliza `imagenActual` (URL previa o `null`).
2. PHP lee `$_FILES['imagen']`, comprueba el tipo real con `finfo` (no solo la extensión) y el tamaño.
3. Genera un nombre aleatorio bajo `items/YYYY/MM/`.
4. `supabase_upload()` envía el binario al bucket `productos`.
5. La URL pública se construye así:  
   `{supabase_url}/storage/v1/object/public/productos/{path}`.
6. Esa URL se mete después en el JSON del producto como `imagen_url`. El archivo no se guarda en Postgres.

**Vista previa local:** al cambiar el input, `URL.createObjectURL(file)` muestra el archivo en el cliente **sin** subirlo todavía.

---

## 6. Crear producto — `POST`

**Origen:** `submit` del formulario cuando `productoId` está vacío.

```
formulario DOM
  → subirImagenSiHay()  (opcional)
  → JSON payload
  → POST api/productos.php
      sanitize_producto()
      → POST /rest/v1/productos   Prefer: return=representation
  ← { ok: true, data: fila }  HTTP 201
  → modal se cierra → cargar()
```

**Payload**

| Campo | Origen en UI | Transformación en PHP |
|---|---|---|
| `nombre` | texto | trim; obligatorio |
| `descripcion` | textarea | trim |
| `categoria` | select | debe estar en `oficina`, `hogar`, `tecnologia`, `papeleria`, `otro` |
| `precio` | number | float ≥ 0, 2 decimales |
| `stock` | number | entero ≥ 0 |
| `fecha_ingreso` | date | `YYYY-MM-DD` |
| `activo` | checkbox | boolean |
| `email_contacto` | email | trim; si hay valor, debe ser correo válido |
| `imagen_url` | resultado del upload o `null` | string o `null` |

Postgres asigna `id` (uuid), `created_at` y `updated_at`. PHP no envía el `id`.

Tras el 201, la UI no inserta la fila a mano: vuelve a **listar** para alinear tabla y estadísticas.

---

## 7. Actualizar producto — `PUT`

**Origen:** `submit` cuando `productoId` tiene uuid.

```
formulario DOM + id
  → subirImagenSiHay()
      si hay archivo nuevo → nueva URL
      si no → se conserva imagenActual
  → PUT api/productos.php?id={uuid}
      sanitize_producto(..., partial=true)
      payload.updated_at = ahora (UTC)
      → PATCH /rest/v1/productos?id=eq.{uuid}
  ← { ok: true, data: fila }
  → cargar()
```

PHP traduce el `PUT` del cliente a `PATCH` de PostgREST (actualización parcial por `id`).

Si el usuario no elige imagen nueva, `imagen_url` sigue siendo la URL anterior. Una imagen nueva **no** borra el objeto viejo en Storage (solo se sustituye la URL en la fila).

---

## 8. Eliminar producto — `DELETE`

**Origen:** papelera → modal → `btnConfirmarEliminar`.

```
click data-del
  → idEliminar = item.id  (solo UI)
confirmar
  → DELETE api/productos.php?id={uuid}
      GET imagen_url de esa fila
      extraer path con public_url_to_path()
      → DELETE /storage/v1/object/productos  { prefixes: [path] }
      → DELETE /rest/v1/productos?id=eq.{uuid}
  ← { ok: true }
  → cargar()
```

**Pasos**

1. El nombre se muestra en el modal; el `id` queda en `idEliminar`.
2. PHP lee `imagen_url` para no dejar el archivo huérfano.
3. Si la URL es del bucket configurado, se borra el objeto en Storage.
4. Luego se borra la fila en `productos`.
5. La lista se recarga.

El orden es Storage primero y tabla después: si falla el `DELETE` de Postgres, el archivo ya pudo haberse eliminado.

---

## 9. Filtrar en cliente — `filtrar()`

No hay petición. Recorre `registros` y compara el texto de `#buscar` (minúsculas) contra `nombre`, `categoria`, `email_contacto` y `descripcion`. Llama a `pintar()` con el subconjunto. Las estadísticas (`statTotal`, `statActivos`, `statStock`) se calculan siempre sobre `registros` completo, no sobre el filtro.

---

## 10. Pintar tabla — `pintar(lista)`

Función de presentación. Recibe la lista ya filtrada y escribe el DOM:

- contador de elementos visibles
- tres tarjetas de resumen
- filas con miniatura, datos formateados (precio MXN, fecha `dd/mm/aaaa`, badge activo/inactivo)
- botones `data-edit` / `data-del`

`escapeHtml()` escapa textos antes de insertarlos con `innerHTML`.

---

## 11. Cliente HTTP — `api(url, options)`

Capa común a listar, crear, actualizar, borrar y subir:

1. `fetch`
2. `res.json()`
3. Si `!res.ok` o `data.ok === false`, lanza `Error` con `data.error`
4. Si no, devuelve el objeto JSON

Todas las respuestas de PHP siguen `{ ok: true, data? | url? }` o `{ ok: false, error }`.

---

## 12. Cliente Supabase — `lib/supabase.php`

| Función | Rol |
|---|---|
| `supabase_config()` | Lee URL, service key y nombre del bucket |
| `json_response()` | JSON + código HTTP y `exit` |
| `request_json()` | Decodifica el body de POST/PUT |
| `supabase_request()` | REST de PostgREST y Storage (JSON) |
| `supabase_upload()` | Subida binaria a Storage |
| `supabase_remove_object()` | Borrado por `prefixes` |
| `public_url_to_path()` | De URL pública a ruta dentro del bucket |

El header `Prefer: return=representation` hace que POST/PATCH devuelvan la fila escrita.

---

## Validación: dónde ocurre

```
Navegador     HTML required / type=email / type=number (primera barrera)
     ↓
PHP           sanitize_producto() y comprobaciones de upload (barrera real)
     ↓
Postgres      CHECK de categoria, precio ≥ 0, stock ≥ 0
```

Si el JS se omite, PHP y la tabla siguen rechazando datos inválidos.

---

## Qué no viaja al navegador

- `supabase_service_key` (solo servidor)
- el binario de la imagen después de subirse (solo la URL pública)
- el `id` en el alta (lo genera la base)

El navegador sí recibe URLs públicas de imágenes y puede mostrarlas sin pasar por PHP.
