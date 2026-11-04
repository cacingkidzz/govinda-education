<?php
declare(strict_types=1);

/**
 * Bootstrap umum: konfigurasi, autoloader, helper, CSRF, autentikasi.
 * Semua halaman aplikasi wajib memuat file ini lebih dahulu.
 */

require_once __DIR__ . '/../config/app.php';

spl_autoload_register(static function (string $class): void {
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) {
        return;
    }
    $file = ROOT_PATH . '/classes/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
