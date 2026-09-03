<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/supabase.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'error' => 'No se recibió ninguna imagen.'], 400);
}

$file = $_FILES['imagen'];
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

if (!isset($allowed[$mime])) {
    json_response(['ok' => false, 'error' => 'Formato no permitido. Usa JPG, PNG, WEBP o GIF.'], 422);
}

if ($file['size'] > 4 * 1024 * 1024) {
    json_response(['ok' => false, 'error' => 'La imagen no puede superar 4 MB.'], 422);
}

$ext = $allowed[$mime];
$objectPath = 'items/' . date('Y/m') . '/' . bin2hex(random_bytes(8)) . '.' . $ext;
$binary = file_get_contents($file['tmp_name']);

$res = supabase_upload($objectPath, $binary, $mime);
if (!$res['ok']) {
    json_response(['ok' => false, 'error' => $res['error']], $res['status']);
}

json_response(['ok' => true, 'url' => $res['url'], 'path' => $res['path']]);
