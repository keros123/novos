<?php
declare(strict_types=1);

function supabase_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config.php';
    }
    return $config;
}

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function supabase_request(string $method, string $path, ?array $body = null, array $extraHeaders = []): array
{
    $config = supabase_config();
    $url = rtrim($config['supabase_url'], '/') . $path;

    $headers = array_merge([
        'apikey: ' . $config['supabase_service_key'],
        'Authorization: Bearer ' . $config['supabase_service_key'],
        'Content-Type: application/json',
        'Prefer: return=representation',
    ], $extraHeaders);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'status' => 502, 'error' => $error ?: 'Error de conexión con Supabase'];
    }

    $decoded = json_decode($response, true);
    if ($httpCode >= 400) {
        $message = is_array($decoded)
            ? ($decoded['message'] ?? $decoded['error'] ?? $decoded['msg'] ?? $response)
            : $response;
        return ['ok' => false, 'status' => $httpCode, 'error' => $message];
    }

    return ['ok' => true, 'status' => $httpCode, 'data' => $decoded];
}

function supabase_upload(string $objectPath, string $binary, string $mime): array
{
    $config = supabase_config();
    $bucket = $config['storage_bucket'];
    $url = rtrim($config['supabase_url'], '/') . '/storage/v1/object/' . rawurlencode($bucket) . '/' . $objectPath;

    $headers = [
        'apikey: ' . $config['supabase_service_key'],
        'Authorization: Bearer ' . $config['supabase_service_key'],
        'Content-Type: ' . $mime,
        'x-upsert: true',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $binary,
        CURLOPT_TIMEOUT => 60,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        $decoded = json_decode((string) $response, true);
        $message = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? $response) : ($error ?: $response);
        return ['ok' => false, 'status' => $httpCode ?: 502, 'error' => $message ?: 'No se pudo subir la imagen'];
    }

    $publicUrl = rtrim($config['supabase_url'], '/') . '/storage/v1/object/public/' . $bucket . '/' . $objectPath;
    return ['ok' => true, 'url' => $publicUrl, 'path' => $objectPath];
}

function supabase_remove_object(string $objectPath): void
{
    $config = supabase_config();
    $bucket = $config['storage_bucket'];
    supabase_request('DELETE', '/storage/v1/object/' . rawurlencode($bucket), [
        'prefixes' => [$objectPath],
    ]);
}

function public_url_to_path(?string $url): ?string
{
    if (!$url) {
        return null;
    }
    $bucket = supabase_config()['storage_bucket'];
    $needle = '/storage/v1/object/public/' . $bucket . '/';
    $pos = strpos($url, $needle);
    if ($pos === false) {
        return null;
    }
    return substr($url, $pos + strlen($needle));
}
