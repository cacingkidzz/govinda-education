<?php
declare(strict_types=1);

/**
 * Migrasi Pendaftaran Mandiri Siswa.
 *
 * Jalankan dari root proyek:
 *   php database/migrate_self_registration.php
 *
 * Sifat migrasi:
 * - Idempoten: kolom, index, dan setting dicek lebih dulu.
 * - Membuat backup tabel students + app_settings sebelum mengubah apa pun.
 * - Tidak mengubah data siswa yang sudah ada selain menambah kolom kosong.
 */

require_once __DIR__ . '/../config/database.php';

$pdo = Database::pdo();
$dbName = DB_NAME;

$columnExists = static function (string $table, string $column) use ($pdo, $dbName): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND COLUMN_NAME = :column'
    );
    $stmt->execute([':db' => $dbName, ':table' => $table, ':column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};

$indexExists = static function (string $table, string $index) use ($pdo, $dbName): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND INDEX_NAME = :index'
    );
    $stmt->execute([':db' => $dbName, ':table' => $table, ':index' => $index]);
    return (int) $stmt->fetchColumn() > 0;
};

$settingExists = static function (string $key) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM app_settings WHERE setting_key = :key');
    $stmt->execute([':key' => $key]);
    return (int) $stmt->fetchColumn() > 0;
};

$step = static function (string $message): void {
    echo '[MIGRASI] ' . $message . PHP_EOL;
};

echo '=== Migrasi Pendaftaran Mandiri Siswa ===' . PHP_EOL;

// ---------------------------------------------------------------------
// 0. BACKUP tabel yang berubah
// ---------------------------------------------------------------------
$backupDir = ROOT_PATH . '/storage/backups';
if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, 'GAGAL: folder backup tidak dapat dibuat: ' . $backupDir . PHP_EOL);
    exit(1);
}

$backupFile = $backupDir . '/self_registration_' . date('Ymd_His') . '.sql';
$handle = @fopen($backupFile, 'wb');
if ($handle === false) {
    fwrite(STDERR, 'GAGAL: file backup tidak dapat ditulis: ' . $backupFile . PHP_EOL);
    exit(1);
}

fwrite($handle, "-- Backup otomatis Govinda Education sebelum migrasi Pendaftaran Mandiri\n");
fwrite($handle, "-- Dibuat: " . date('Y-m-d H:i:s') . "\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

foreach (['students', 'app_settings'] as $table) {
    $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch();
    if (!is_array($create)) {
        continue;
    }
    fwrite($handle, 'DROP TABLE IF EXISTS `' . $table . "`;\n");
    fwrite($handle, (string) ($create['Create Table'] ?? $create[1] ?? '') . ";\n");

    $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll();
    if ($rows === []) {
        fwrite($handle, "\n");
        continue;
    }
    $columns = array_keys($rows[0]);
    $columnSql = '`' . implode('`,`', $columns) . '`';
    foreach (array_chunk($rows, 100) as $chunk) {
        $values = [];
        foreach ($chunk as $row) {
            $parts = [];
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                $parts[] = $value === null ? 'NULL' : $pdo->quote((string) $value);
            }
            $values[] = '(' . implode(',', $parts) . ')';
        }
        fwrite($handle, 'INSERT INTO `' . $table . '` (' . $columnSql . ") VALUES\n"
            . implode(",\n", $values) . ";\n");
    }
    fwrite($handle, "\n");
}
fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($handle);
$step('Backup dibuat: ' . $backupFile . ' (' . number_format((int) filesize($backupFile), 0, ',', '.') . ' byte)');

// ---------------------------------------------------------------------
// 1. Kolom token & penanda pembaruan mandiri pada students
// ---------------------------------------------------------------------
$columns = [
    'public_token'            => 'VARCHAR(64) NULL DEFAULT NULL AFTER `is_active`',
    'public_token_created_at' => 'DATETIME NULL DEFAULT NULL AFTER `public_token`',
    'self_updated_at'         => 'DATETIME NULL DEFAULT NULL AFTER `public_token_created_at`',
    'profile_completed_at'    => 'DATETIME NULL DEFAULT NULL AFTER `self_updated_at`',
];

foreach ($columns as $column => $definition) {
    if ($columnExists('students', $column)) {
        $step('Kolom students.' . $column . ' sudah ada, dilewati.');
        continue;
    }
    $pdo->exec('ALTER TABLE `students` ADD COLUMN `' . $column . '` ' . $definition);
    $step('Kolom students.' . $column . ' ditambahkan.');
}

if ($indexExists('students', 'uq_students_public_token')) {
    $step('Index uq_students_public_token sudah ada, dilewati.');
} else {
    $pdo->exec('ALTER TABLE `students` ADD UNIQUE KEY `uq_students_public_token` (`public_token`)');
    $step('Index unik token siswa ditambahkan.');
}

// ---------------------------------------------------------------------
// 2. Setting pendaftaran umum
// ---------------------------------------------------------------------
$settings = [
    'registration_enabled'    => '0',
    'registration_token'      => '',
    'registration_rotated_at' => '',
];

foreach ($settings as $key => $value) {
    if ($settingExists($key)) {
        $step('Setting ' . $key . ' sudah ada, dilewati.');
        continue;
    }
    $stmt = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (:key, :value)');
    $stmt->execute([':key' => $key, ':value' => $value]);
    $step('Setting ' . $key . ' ditambahkan.');
}

echo '[MIGRASI] Selesai. Pendaftaran umum nonaktif sampai admin membuat tautannya.' . PHP_EOL;
