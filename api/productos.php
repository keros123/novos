<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/supabase.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';

$categorias = ['oficina', 'hogar', 'tecnologia', 'papeleria', 'otro'];

function sanitize_producto(array $input, bool $partial = false): array
{
    global $categorias;

    $fields = [
        'nombre' => fn ($v) => trim((string) $v),
        'descripcion' => fn ($v) => trim((string) $v),
        'categoria' => fn ($v) => (string) $v,
        'precio' => fn ($v) => round((float) $v, 2),
        'stock' => fn ($v) => (int) $v,
        'fecha_ingreso' => fn ($v) => (string) $v,
        'activo' => fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN),
        'email_contacto' => fn ($v) => trim((string) $v),
        'imagen_url' => fn ($v) => $v === null || $v === '' ? null : trim((string) $v),
    ];

    $out = [];
    foreach ($fields as $key => $cast) {
        if (!array_key_exists($key, $input)) {
            if ($partial) {
                continue;
            }
            if (in_array($key, ['descripcion', 'email_contacto', 'imagen_url'], true)) {
                $out[$key] = $key === 'imagen_url' ? null : '';
                continue;
            }
        }
        if (array_key_exists($key, $input)) {
            $out[$key] = $cast($input[$key]);
        }
    }

    if (isset($out['nombre']) && $out['nombre'] === '') {
        json_response(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);
    }
    if (isset($out['categoria']) && !in_array($out['categoria'], $categorias, true)) {
        json_response(['ok' => false, 'error' => 'Categoría no válida.'], 422);
    }
    if (isset($out['precio']) && $out['precio'] < 0) {
        json_response(['ok' => false, 'error' => 'El precio no puede ser negativo.'], 422);
    }
    if (isset($out['stock']) && $out['stock'] < 0) {
        json_response(['ok' => false, 'error' => 'El stock no puede ser negativo.'], 422);
    }
    if (isset($out['fecha_ingreso']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $out['fecha_ingreso'])) {
        json_response(['ok' => false, 'error' => 'La fecha no es válida.'], 422);
    }
    if (!empty($out['email_contacto']) && !filter_var($out['email_contacto'], FILTER_VALIDATE_EMAIL)) {
        json_response(['ok' => false, 'error' => 'El correo no es válido.'], 422);
    }

    return $out;
}

switch ($method) {
    case 'GET':
        if ($id !== '') {
            $res = supabase_request('GET', '/rest/v1/productos?id=eq.' . rawurlencode($id) . '&select=*');
            if (!$res['ok']) {
                json_response(['ok' => false, 'error' => $res['error']], $res['status']);
            }
            $row = $res['data'][0] ?? null;
            if (!$row) {
                json_response(['ok' => false, 'error' => 'Registro no encontrado.'], 404);
            }
            json_response(['ok' => true, 'data' => $row]);
        }

        $res = supabase_request('GET', '/rest/v1/productos?select=*&order=created_at.desc');
        if (!$res['ok']) {
            json_response(['ok' => false, 'error' => $res['error']], $res['status']);
        }
        json_response(['ok' => true, 'data' => $res['data'] ?? []]);

    case 'POST':
        $payload = sanitize_producto(request_json());
        $res = supabase_request('POST', '/rest/v1/productos', $payload);
        if (!$res['ok']) {
            json_response(['ok' => false, 'error' => $res['error']], $res['status']);
        }
        json_response(['ok' => true, 'data' => $res['data'][0] ?? $res['data']], 201);

    case 'PUT':
        if ($id === '') {
            json_response(['ok' => false, 'error' => 'Falta el id.'], 400);
        }
        $payload = sanitize_producto(request_json(), true);
        $payload['updated_at'] = gmdate('c');
        $res = supabase_request('PATCH', '/rest/v1/productos?id=eq.' . rawurlencode($id), $payload);
        if (!$res['ok']) {
            json_response(['ok' => false, 'error' => $res['error']], $res['status']);
        }
        json_response(['ok' => true, 'data' => $res['data'][0] ?? $res['data']]);

    case 'DELETE':
        if ($id === '') {
            json_response(['ok' => false, 'error' => 'Falta el id.'], 400);
        }
        $current = supabase_request('GET', '/rest/v1/productos?id=eq.' . rawurlencode($id) . '&select=imagen_url');
        if ($current['ok'] && !empty($current['data'][0]['imagen_url'])) {
            $path = public_url_to_path($current['data'][0]['imagen_url']);
            if ($path) {
                supabase_remove_object($path);
            }
        }
        $res = supabase_request('DELETE', '/rest/v1/productos?id=eq.' . rawurlencode($id));
        if (!$res['ok']) {
            json_response(['ok' => false, 'error' => $res['error']], $res['status']);
        }
        json_response(['ok' => true]);

    default:
        json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}
