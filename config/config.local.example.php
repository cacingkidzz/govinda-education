<?php

/**
 * Contoh konfigurasi lokal / hosting cPanel.
 *
 * CARA PAKAI:
 *   1. Salin file ini menjadi config/config.local.php
 *      (di cPanel: File Manager -> config/ -> Copy, atau buat file baru).
 *   2. Isi DB_* sesuai database yang dibuat di cPanel
 *      (MySQL® Databases: nama database & user berawalan akun cPanel,
 *      mis. cpuser_govinda).
 *   3. Simpan sebagai UTF-8 tanpa BOM.
 *   4. Jangan unggah/bagikan config/config.local.php. File ini diblokir
 *      dari akses web oleh config/.htaccess dan root .htaccess.
 *
 * File ini WAJIB ada di hosting. config/database.php memilih environment
 * secara otomatis berdasarkan hostname, tetapi kredensial produksi tidak
 * disimpan di sana. Tanpa file ini aplikasi berhenti dengan pesan
 * "Kredensial database hosting belum diisi".
 *
 * Nilai di file ini menimpa config/database.php, termasuk saat dijalankan
 * lewat CLI (migrasi/tes di server).
 */

// ---------------------------------------------------------------------
// Database MySQL cPanel
// ---------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'cpuser_govinda');
define('DB_USER', 'cpuser_govinda');
define('DB_PASS', 'GANTI_DENGAN_PASSWORD_CPANEL');

// ---------------------------------------------------------------------
// Opsional: paksa base URL bila deteksi otomatis salah.
// Contoh: proyek di https://domain.com/govinda-education
//   define('APP_BASE_URL_OVERRIDE', '/govinda-education');
// Contoh: proyek di root domain (https://domain.com)
//   define('APP_BASE_URL_OVERRIDE', '');
// Biarkan baris di bawah tetap komentar bila deteksi otomatis sudah benar.
// ---------------------------------------------------------------------
// define('APP_BASE_URL_OVERRIDE', '/govinda-education');
