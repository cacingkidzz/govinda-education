<?php
declare(strict_types=1);

/**
 * Membuat versi SQL yang siap diimpor lewat phpMyAdmin cPanel.
 *
 * Perbedaan dengan database/govinda_education.sql:
 * - tanpa `CREATE DATABASE` dan `USE`, karena di cPanel database sudah
 *   dibuat lebih dulu dengan nama berprefix akun (mis. cpuser_govinda)
 *   dan user biasanya tidak memiliki hak CREATE DATABASE.
 *
 * Jalankan dari root proyek setiap kali skema berubah:
 *   php database/build_cpanel_sql.php
 *
 * Hasil: database/govinda_education_cpanel.sql
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Hanya dapat dijalankan dari CLI.');
}

$source = __DIR__ . '/govinda_education.sql';
$target = __DIR__ . '/govinda_education_cpanel.sql';

if (!is_file($source)) {
    fwrite(STDERR, 'GAGAL: file sumber tidak ditemukan: ' . $source . PHP_EOL);
    exit(1);
}

$sql = (string) file_get_contents($source);

// Buang pembuatan & pemilihan database.
$sql = (string) preg_replace('/^CREATE DATABASE\b[^;]*;\s*/mi', '', $sql);
$sql = (string) preg_replace('/^USE\s+`[^`]+`;\s*/mi', '', $sql);

$remaining = preg_match('/^\s*(CREATE DATABASE|USE\s+`)/mi', $sql);
if ($remaining === 1) {
    fwrite(STDERR, 'GAGAL: masih ada pernyataan CREATE DATABASE/USE yang belum dibuang.' . PHP_EOL);
    exit(1);
}

$header = <<<'SQL'
-- =====================================================================
--  GOVINDA EDUCATION — SKEMA UNTUK CPANEL / PHPMYADMIN
--
--  File ini DIHASILKAN OTOMATIS oleh database/build_cpanel_sql.php.
--  Jangan diedit manual; ubah database/govinda_education.sql lalu
--  jalankan: php database/build_cpanel_sql.php
--
--  CARA IMPOR:
--   1. Buat database di cPanel (MySQL(R) Databases), mis. cpuser_govinda.
--   2. Buka phpMyAdmin, pilih database tersebut.
--   3. Tab Import -> pilih file ini -> Go.
--  File ini tidak membuat atau memilih database.
-- =====================================================================

SQL;

$written = file_put_contents($target, $header . $sql);
if ($written === false) {
    fwrite(STDERR, 'GAGAL: tidak dapat menulis ' . $target . PHP_EOL);
    exit(1);
}

echo '[OK] File cPanel dibuat: ' . $target . PHP_EOL;
echo '[OK] Ukuran: ' . number_format((int) filesize($target), 0, ',', '.') . ' byte' . PHP_EOL;
