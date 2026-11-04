<?php
declare(strict_types=1);

/**
 * Migrasi Modul Pengeluaran.
 *
 * Jalankan dari root proyek:
 *   php database/migrate_expenses.php
 *
 * Sifat migrasi:
 * - Idempoten: tabel dicek lebih dulu, aman dijalankan berulang.
 * - Membuat backup database (struktur + data tabel terkait) sebelum mengubah.
 * - Tidak mengubah data keuangan yang sudah ada.
 */

require_once __DIR__ . '/../config/database.php';

$pdo = Database::pdo();
$dbName = DB_NAME;

$tableExists = static function (string $table) use ($pdo, $dbName): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table'
    );
    $stmt->execute([':db' => $dbName, ':table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
};

$step = static function (string $message): void {
    echo '[MIGRASI] ' . $message . PHP_EOL;
};

echo '=== Migrasi Modul Pengeluaran ===' . PHP_EOL;

// ---------------------------------------------------------------------
// 0. BACKUP tabel yang berubah
// ---------------------------------------------------------------------
$backupDir = ROOT_PATH . '/storage/backups';
if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, 'GAGAL: folder backup tidak dapat dibuat: ' . $backupDir . PHP_EOL);
    exit(1);
}

$backupFile = $backupDir . '/expenses_' . date('Ymd_His') . '.sql';
$handle = @fopen($backupFile, 'wb');
if ($handle === false) {
    fwrite(STDERR, 'GAGAL: file backup tidak dapat ditulis: ' . $backupFile . PHP_EOL);
    exit(1);
}

fwrite($handle, "-- Backup otomatis Govinda Education sebelum migrasi Pengeluaran\n");
fwrite($handle, "-- Dibuat: " . date('Y-m-d H:i:s') . "\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

// Tabel yang mungkin terdampak. Tabel expenses belum ada pada instalasi lama.
$backupTables = ['expenses', 'users'];
foreach ($backupTables as $table) {
    if (!$tableExists($table)) {
        fwrite($handle, "-- Tabel {$table} belum ada, tidak ada yang dibackup.\n\n");
        continue;
    }
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
// 1. Tabel expenses
// ---------------------------------------------------------------------
if ($tableExists('expenses')) {
    $step('Tabel expenses sudah ada, dilewati.');
} else {
    $pdo->exec(
        "CREATE TABLE `expenses` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `expense_number` VARCHAR(40) NOT NULL,
          `expense_date` DATE NOT NULL,
          `category` ENUM('honor_guru','sewa','listrik_internet','atk','transport','konsumsi','perawatan','lainnya')
              NOT NULL DEFAULT 'lainnya',
          `amount` DECIMAL(12,2) NOT NULL,
          `method` ENUM('tunai','transfer','qris','lainnya') NOT NULL DEFAULT 'tunai',
          `payee` VARCHAR(120) NOT NULL,
          `reference_number` VARCHAR(100) NULL DEFAULT NULL,
          `notes` TEXT NULL DEFAULT NULL,
          `status` ENUM('tercatat','dibatalkan') NOT NULL DEFAULT 'tercatat',
          `recorded_by` INT UNSIGNED NULL DEFAULT NULL,
          `recorded_by_name` VARCHAR(120) NOT NULL,
          `cancelled_at` DATETIME NULL DEFAULT NULL,
          `cancelled_by` INT UNSIGNED NULL DEFAULT NULL,
          `cancel_reason` VARCHAR(255) NULL DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_expenses_number` (`expense_number`),
          KEY `idx_expenses_date` (`expense_date`),
          KEY `idx_expenses_category` (`category`),
          KEY `idx_expenses_status` (`status`),
          KEY `idx_expenses_recorded_by` (`recorded_by`),
          CONSTRAINT `fk_expenses_recorded_by` FOREIGN KEY (`recorded_by`)
              REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
          CONSTRAINT `fk_expenses_cancelled_by` FOREIGN KEY (`cancelled_by`)
              REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
          CONSTRAINT `chk_expenses_amount` CHECK (`amount` > 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $step('Tabel expenses dibuat.');
}

echo '[MIGRASI] Selesai. Modul pengeluaran siap digunakan.' . PHP_EOL;
