<?php
// Configuración central. El archivo carga variables desde .env si existe
// y usa valores por defecto en caso contrario.

// Carga un archivo .env simple (KEY=VALUE) si está presente
function _load_dotenv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($k,$v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        // remove surrounding quotes
        if ((strlen($v) >= 2) && (($v[0] === '"' && substr($v,-1) === '"') || ($v[0] === "'" && substr($v,-1) === "'"))) {
            $v = substr($v,1,-1);
        }
        if (getenv($k) === false) {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
    }
}

// Intentar cargar .env en la raíz del proyecto
// (anteriormente se apuntaba al directorio padre; usar la raíz del repo)
_load_dotenv(__DIR__ . '/.env');

// Helpers para leer variables con fallback
function env($key, $default = null) {
    $v = getenv($key);
    if ($v === false) return $default;
    return $v;
}

return [
    'db' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'name' => env('DB_NAME', 'stockdirecta'),
        'user' => env('DB_USER', 'dbuser'),
        'pass' => env('DB_PASS', 'dbpass'),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ],
    'base_url' => env('BASE_URL', 'http://localhost'),
    'mail' => [
        'from_email' => env('MAIL_FROM_EMAIL', 'no-reply@stockdirecta.com'),
        'from_name' => env('MAIL_FROM_NAME', 'StockDirecta')
    ],
    // SMTP options (opcional) - si usas PHPMailer, rellena estos valores en .env
    'smtp' => [
        'host' => env('SMTP_HOST', ''),
        'port' => intval(env('SMTP_PORT', 587)),
        'username' => env('SMTP_USERNAME', ''),
        'password' => env('SMTP_PASSWORD', ''),
        'secure' => env('SMTP_SECURE', 'tls'), // 'tls' or 'ssl' or ''
        'auth' => filter_var(env('SMTP_AUTH', 'true'), FILTER_VALIDATE_BOOLEAN)
    ],
    'site' => [
        'name' => env('SITE_NAME', 'StockDirecta'),
        'logo_url' => env('SITE_LOGO_URL', '')
    ]
];
