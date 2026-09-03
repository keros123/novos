<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'Falta vendor/autoload.php. Ejecuta composer install.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $autoload;

if (is_file(__DIR__ . '/.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}

$env = static function (string $key, ?string $default = null): ?string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string) $value;
};

$config = [
    'supabase_url' => $env('SUPABASE_URL') ?? '',
    'supabase_service_key' => $env('SUPABASE_SERVICE_ROLE_KEY') ?? '',
    'storage_bucket' => $env('STORAGE_BUCKET') ?? 'productos',
];

$url = (string) $config['supabase_url'];
$key = (string) $config['supabase_service_key'];
$looksPlaceholder = str_contains($url, 'TU_PROYECTO')
    || str_contains($url, 'tu-proyecto')
    || str_contains($key, 'TU_SERVICE_ROLE_KEY')
    || str_contains($key, 'tu-service-role');

if ($url === '' || $key === '' || $looksPlaceholder) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'Configura SUPABASE_URL y SUPABASE_SERVICE_ROLE_KEY en .env.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

return $config;
