<?php
declare(strict_types=1);

/**
 * Govinda Education — Konfigurasi aplikasi.
 * File ini dimuat paling awal oleh includes/bootstrap.php.
 */

if (defined('GOVINDA_APP_INIT')) {
    return;
}
define('GOVINDA_APP_INIT', true);

// ---------------------------------------------------------------------
// Identitas & versi
// ---------------------------------------------------------------------
define('APP_NAME', 'Govinda Education');
define('APP_TAGLINE', 'Bimbingan Belajar');
define('APP_VERSION', '1.0.0');
define('APP_STAGE', 'Tahap 4 - Keuangan (Paket & Pembayaran)');

// ---------------------------------------------------------------------
// Path & environment
// ---------------------------------------------------------------------
define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)));
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('LOG_PATH', STORAGE_PATH . '/logs');

// ---------------------------------------------------------------------
// Konfigurasi lokal / hosting (opsional, tidak ikut dibagikan)
// Salin config/config.local.example.php menjadi config/config.local.php
// lalu isi kredensial cPanel. File ini dapat mendefinisikan DB_* dan
// APP_BASE_URL_OVERRIDE tanpa mengubah file aplikasi.
// ---------------------------------------------------------------------
$localConfigFile = __DIR__ . '/config.local.php';
if (is_file($localConfigFile)) {
    // Buffer agar BOM/spasi tak sengaja pada file konfigurasi tidak
    // terkirim ke browser sebelum header/redirect.
    ob_start();
    require_once $localConfigFile;
    ob_end_clean();
}

$debugEnv = getenv('GOVINDA_DEBUG');
define('APP_DEBUG', $debugEnv === '1' || $debugEnv === 'true');
define('APP_ENV', APP_DEBUG ? 'development' : 'production');

// Base URL otomatis: mendukung root domain maupun subfolder (XAMPP/cPanel).
$rootReal = str_replace('\\', '/', (string) realpath(ROOT_PATH));
$docRoot  = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$basePath = '';

if ($docRoot !== '' && str_starts_with($rootReal, $docRoot)) {
    $basePath = substr($rootReal, strlen($docRoot));
}
$baseOverride = getenv('GOVINDA_BASE_URL');
if ((!is_string($baseOverride) || $baseOverride === '') && defined('APP_BASE_URL_OVERRIDE')) {
    $baseOverride = (string) APP_BASE_URL_OVERRIDE;
}
if (is_string($baseOverride) && $baseOverride !== '') {
    $basePath = $baseOverride;
}
$basePath = '/' . trim(str_replace('\\', '/', $basePath), '/');
if ($basePath === '/') {
    $basePath = '';
}
define('BASE_URL', rtrim($basePath, '/'));

define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2 MB
define('SESSION_NAME', 'govinda_session');
define('SESSION_IDLE_TIMEOUT', 3 * 60 * 60); // 3 jam

// ---------------------------------------------------------------------
// Error & logging
// ---------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . '/php-error.log');
ini_set('default_charset', 'UTF-8');

if (!is_dir(LOG_PATH)) {
    @mkdir(LOG_PATH, 0775, true);
}

date_default_timezone_set('Asia/Jakarta');
setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'Indonesian');

// ---------------------------------------------------------------------
// Session yang aman
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', (string) SESSION_IDLE_TIMEOUT);

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL !== '' ? BASE_URL . '/' : '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------------------------------------------------------------------
// Header keamanan (hanya untuk request web)
// ---------------------------------------------------------------------
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('X-Permitted-Cross-Domain-Policies: none');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ---------------------------------------------------------------------
// Penanganan exception & fatal error yang aman
// ---------------------------------------------------------------------
set_exception_handler(static function (Throwable $exception): void {
    $log = sprintf(
        "[%s] %s: %s in %s:%d\n%s\n",
        date('Y-m-d H:i:s'),
        $exception::class,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );
    @file_put_contents(LOG_PATH . '/app.log', $log, FILE_APPEND | LOCK_EX);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $log);
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    $debug = APP_DEBUG;
    $message = $debug
        ? htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        : 'Terjadi kendala pada sistem. Silakan coba beberapa saat lagi.';

    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Kesalahan Sistem — ' . APP_NAME . '</title>'
        . '<style>body{font-family:system-ui,Segoe UI,sans-serif;background:#f1f5f9;color:#0f172a;margin:0;display:grid;place-items:center;min-height:100vh;padding:24px}'
        . '.box{max-width:560px;background:#fff;border-radius:16px;padding:36px;box-shadow:0 18px 50px rgba(15,23,42,.12);border-top:4px solid #0f766e}'
        . 'h1{font-size:1.35rem;margin:0 0 12px}p{color:#475569;line-height:1.6;margin:0 0 18px}'
        . 'a{display:inline-block;background:#0f766e;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600}'
        . 'code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:.85em}</style></head><body><div class="box">'
        . '<h1>Kesalahan Sistem</h1><p>' . $message . '</p>';
    if ($debug) {
        echo '<p><code>' . htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8') . ':'
            . $exception->getLine() . '</code></p>';
    }
    echo '<a href="' . htmlspecialchars(BASE_URL . '/login.php', ENT_QUOTES, 'UTF-8') . '">Kembali ke Login</a>'
        . '</div></body></html>';
    exit;
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    $log = sprintf(
        "[%s] FATAL: %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $error['message'],
        $error['file'],
        $error['line']
    );
    @file_put_contents(LOG_PATH . '/app.log', $log, FILE_APPEND | LOCK_EX);
});
