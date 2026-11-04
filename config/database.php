<?php
declare(strict_types=1);

/**
 * Konfigurasi koneksi MySQL/MariaDB.
 *
 * Kredensial dipilih otomatis berdasarkan environment:
 * - LOKAL (XAMPP/laptop): host localhost / 127.0.0.1 atau dijalankan via CLI.
 * - PRODUCTION (cPanel): domain apa pun selain localhost.
 *
 * PENTING: file ini TIDAK menyimpan kredensial hosting.
 * Kredensial produksi wajib diisi di config/config.local.php
 * (salin dari config/config.local.example.php). File tersebut diblokir
 * dari akses web oleh config/.htaccess dan root .htaccess.
 *
 * Urutan prioritas:
 * 1. config/config.local.php (bila ada) — menimpa semua nilai di bawah.
 * 2. Environment variable GOVINDA_DB_*.
 * 3. Blok LOKAL/PRODUCTION di file ini (blok produksi sengaja kosong).
 *
 * Paksa environment bila perlu:
 *   GOVINDA_ENV=production php database/migrate_expenses.php
 *   GOVINDA_ENV=local php tests/stage4_test.php
 */

require_once __DIR__ . '/app.php';
require_once ROOT_PATH . '/classes/Database.php';

$productionNeedsConfig = false;

if (!defined('DB_HOST') || !defined('DB_PORT') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    // Deteksi environment. SERVER_NAME berasal dari konfigurasi server,
    // bukan header Host yang bisa dipalsukan pengunjung.
    $serverName = strtolower(trim((string) ($_SERVER['SERVER_NAME'] ?? '')));
    $httpHost   = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    $httpHost   = (string) preg_replace('/:\d+$/', '', $httpHost);
    $detectedHost = $serverName !== '' ? $serverName : $httpHost;

    $localHosts = ['localhost', '127.0.0.1', '::1', '[::1]'];
    $isLocal = in_array($detectedHost, $localHosts, true) || PHP_SAPI === 'cli';

    $forcedEnv = strtolower(trim((string) (getenv('GOVINDA_ENV') ?: '')));
    if ($forcedEnv === 'production') {
        $isLocal = false;
    } elseif ($forcedEnv === 'local') {
        $isLocal = true;
    }

    if ($isLocal) {
        // --- KONFIGURASI LOKAL (XAMPP/LAPTOP) ---
        $dbHost = 'localhost';
        $dbPort = 3306;
        $dbName = 'govinda_education';
        $dbUser = 'root';
        $dbPass = '';
    } else {
        // --- KONFIGURASI PRODUCTION (HOSTING CPANEL) ---
        // Sengaja dikosongkan: jangan menaruh kredensial hosting di file ini.
        // Isi DB_* lewat config/config.local.php atau environment GOVINDA_DB_*.
        $dbHost = 'localhost';
        $dbPort = 3306;
        $dbName = '';
        $dbUser = '';
        $dbPass = '';
        $productionNeedsConfig = true;
    }

    // Definisikan per konstanta agar config.local.php boleh mengisi sebagian saja.
    // Environment variable (bila diisi) menimpa nilai blok di atas.
    if (!defined('DB_HOST')) {
        define('DB_HOST', getenv('GOVINDA_DB_HOST') ?: $dbHost);
    }
    if (!defined('DB_PORT')) {
        define('DB_PORT', (int) (getenv('GOVINDA_DB_PORT') ?: $dbPort));
    }
    if (!defined('DB_NAME')) {
        define('DB_NAME', getenv('GOVINDA_DB_NAME') ?: $dbName);
    }
    if (!defined('DB_USER')) {
        define('DB_USER', getenv('GOVINDA_DB_USER') ?: $dbUser);
    }
    if (!defined('DB_PASS')) {
        define('DB_PASS', getenv('GOVINDA_DB_PASS') !== false ? (string) getenv('GOVINDA_DB_PASS') : $dbPass);
    }
}

// Cegah tebakan kredensial: di produksi tanpa config.local.php, berhenti
// dengan pesan yang jelas (detail teknis tetap hanya masuk ke log).
if ($productionNeedsConfig && (DB_NAME === '' || DB_USER === '')) {
    throw new RuntimeException(
        'Kredensial database hosting belum diisi. Salin config/config.local.example.php '
        . 'menjadi config/config.local.php lalu isi DB_NAME, DB_USER, dan DB_PASS.'
    );
}

return Database::pdo();
