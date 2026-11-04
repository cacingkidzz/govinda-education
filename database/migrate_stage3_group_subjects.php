<?php
declare(strict_types=1);

/**
 * Migrasi Stage 3: relasi grup–mata pelajaran–guru (group_subjects)
 * dan penyesuaian jadwal agar melekat pada relasi tersebut.
 *
 * Jalankan dari root proyek:
 *   php database/migrate_stage3_group_subjects.php
 *
 * Migrasi bersifat idempoten: aman dijalankan lebih dari sekali.
 * Data lama tidak dihapus. Kolom class_groups.subject_id/teacher_id
 * dipertahankan sebagai kolom legacy (nullable) sampai verifikasi selesai.
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

$indexColumns = static function (string $table, string $index) use ($pdo, $dbName): array {
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND INDEX_NAME = :index
         ORDER BY SEQ_IN_INDEX ASC'
    );
    $stmt->execute([':db' => $dbName, ':table' => $table, ':index' => $index]);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
};

$fkExists = static function (string $table, string $fk) use ($pdo, $dbName): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = :db AND TABLE_NAME = :table
           AND CONSTRAINT_NAME = :fk AND CONSTRAINT_TYPE = "FOREIGN KEY"'
    );
    $stmt->execute([':db' => $dbName, ':table' => $table, ':fk' => $fk]);
    return (int) $stmt->fetchColumn() > 0;
};

$step = static function (string $message): void {
    echo '[MIGRASI] ' . $message . PHP_EOL;
};

echo '=== Migrasi Stage 3: group_subjects ===' . PHP_EOL;

// ---------------------------------------------------------------------
// 1. Tabel group_subjects
// ---------------------------------------------------------------------
if (!$tableExists('group_subjects')) {
    $pdo->exec(
        "CREATE TABLE `group_subjects` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `group_id` INT UNSIGNED NOT NULL,
            `subject_id` INT UNSIGNED NOT NULL,
            `teacher_id` INT UNSIGNED NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `description` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_group_subjects_group_subject` (`group_id`,`subject_id`),
            KEY `idx_group_subjects_subject` (`subject_id`,`is_active`),
            KEY `idx_group_subjects_teacher` (`teacher_id`,`is_active`),
            KEY `idx_group_subjects_group_active` (`group_id`,`is_active`),
            CONSTRAINT `fk_group_subjects_group`
                FOREIGN KEY (`group_id`) REFERENCES `class_groups` (`id`)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `fk_group_subjects_subject`
                FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `fk_group_subjects_teacher`
                FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $step('Tabel group_subjects dibuat.');
} else {
    $step('Tabel group_subjects sudah ada, dilewati.');
}

// ---------------------------------------------------------------------
// 2. Pindahkan relasi lama class_groups.subject_id/teacher_id
// ---------------------------------------------------------------------
if ($columnExists('class_groups', 'subject_id') && $columnExists('class_groups', 'teacher_id')) {
    $stmt = $pdo->exec(
        "INSERT INTO `group_subjects`
             (`group_id`, `subject_id`, `teacher_id`, `is_active`, `description`, `created_at`, `updated_at`)
         SELECT g.`id`, g.`subject_id`, g.`teacher_id`, g.`is_active`, NULL, g.`created_at`, g.`updated_at`
           FROM `class_groups` g
           JOIN `subjects` s ON s.`id` = g.`subject_id`
           JOIN `teachers` t ON t.`id` = g.`teacher_id`
          WHERE g.`subject_id` IS NOT NULL
            AND g.`teacher_id` IS NOT NULL
         ON DUPLICATE KEY UPDATE
             `teacher_id` = VALUES(`teacher_id`),
             `updated_at` = VALUES(`updated_at`)"
    );
    $step('Relasi mata pelajaran & guru lama dipindahkan ke group_subjects (' . (int) $stmt . ' baris).');
}

// ---------------------------------------------------------------------
// 3. Kolom group_schedules.group_subject_id
// ---------------------------------------------------------------------
if (!$columnExists('group_schedules', 'group_subject_id')) {
    $pdo->exec(
        'ALTER TABLE `group_schedules`
         ADD COLUMN `group_subject_id` INT UNSIGNED NULL AFTER `id`'
    );
    $step('Kolom group_schedules.group_subject_id ditambahkan (nullable sementara).');
}

// ---------------------------------------------------------------------
// 4. Hubungkan jadwal lama ke relasi grup–mapel yang sesuai
// ---------------------------------------------------------------------
if ($columnExists('group_schedules', 'group_id')) {
    // 4a. Group lama memiliki satu subject_id → cocokkan berdasarkan subject.
    if ($columnExists('class_groups', 'subject_id')) {
        $stmt = $pdo->exec(
            "UPDATE `group_schedules` gs
               JOIN `class_groups` g ON g.`id` = gs.`group_id`
               JOIN `group_subjects` gsub
                    ON gsub.`group_id` = gs.`group_id`
                   AND gsub.`subject_id` = g.`subject_id`
                SET gs.`group_subject_id` = gsub.`id`
              WHERE gs.`group_subject_id` IS NULL"
        );
        $step('Jadwal lama dihubungkan ke group_subjects berdasarkan mata pelajaran (' . (int) $stmt . ' baris).');
    }

    // 4b. Fallback: group dengan tepat satu relasi → gunakan relasi tersebut.
    $stmt = $pdo->exec(
        "UPDATE `group_schedules` gs
           JOIN (
                SELECT `group_id`, MIN(`id`) AS only_id
                  FROM `group_subjects`
                 GROUP BY `group_id`
                HAVING COUNT(*) = 1
           ) single ON single.`group_id` = gs.`group_id`
            SET gs.`group_subject_id` = single.`only_id`
          WHERE gs.`group_subject_id` IS NULL"
    );
    $step('Fallback relasi tunggal diterapkan (' . (int) $stmt . ' baris).');
}

$orphans = (int) $pdo->query(
    'SELECT COUNT(*) FROM `group_schedules` WHERE `group_subject_id` IS NULL'
)->fetchColumn();
if ($orphans > 0) {
    fwrite(STDERR, 'GAGAL: ' . $orphans . ' jadwal belum memiliki group_subject_id. '
        . 'Lengkapi relasi grup–mata pelajaran sebelum melanjutkan.' . PHP_EOL);
    exit(1);
}
$step('Verifikasi: seluruh jadwal memiliki group_subject_id.');

// ---------------------------------------------------------------------
// 5. group_subject_id wajib terisi
// ---------------------------------------------------------------------
$stmt = $pdo->query(
    "SELECT IS_NULLABLE FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'group_schedules'
        AND COLUMN_NAME = 'group_subject_id'"
);
if ((string) $stmt->fetchColumn() === 'YES') {
    $pdo->exec(
        'ALTER TABLE `group_schedules`
         MODIFY COLUMN `group_subject_id` INT UNSIGNED NOT NULL'
    );
    $step('Kolom group_subject_id dijadikan NOT NULL.');
}

// ---------------------------------------------------------------------
// 6. Unique key baru: (group_subject_id, hari, jam mulai)
// ---------------------------------------------------------------------
$slotIndex = $indexExists('group_schedules', 'uq_group_schedule_slot')
    ? $indexColumns('group_schedules', 'uq_group_schedule_slot')
    : [];
$expectedSlot = ['group_subject_id', 'day_of_week', 'start_time'];
if ($slotIndex !== $expectedSlot) {
    if ($slotIndex !== []) {
        $pdo->exec('ALTER TABLE `group_schedules` DROP INDEX `uq_group_schedule_slot`');
        $step('Unique key lama (group_id, hari, jam mulai) dihapus.');
    }
    $pdo->exec(
        'ALTER TABLE `group_schedules`
         ADD UNIQUE KEY `uq_group_schedule_slot` (`group_subject_id`,`day_of_week`,`start_time`)'
    );
    $step('Unique key baru (group_subject_id, hari, jam mulai) dibuat.');
} else {
    $step('Unique key group_subject_id sudah sesuai, dilewati.');
}

// ---------------------------------------------------------------------
// 7. Hapus kolom lama group_schedules.group_id setelah verifikasi
// ---------------------------------------------------------------------
if ($fkExists('group_schedules', 'fk_group_schedules_group')) {
    $pdo->exec('ALTER TABLE `group_schedules` DROP FOREIGN KEY `fk_group_schedules_group`');
    $step('Foreign key lama fk_group_schedules_group dihapus.');
}
if ($columnExists('group_schedules', 'group_id')) {
    $pdo->exec('ALTER TABLE `group_schedules` DROP COLUMN `group_id`');
    $step('Kolom lama group_schedules.group_id dihapus.');
}
if (!$indexExists('group_schedules', 'idx_group_schedules_group_subject_active')) {
    $pdo->exec(
        'ALTER TABLE `group_schedules`
         ADD KEY `idx_group_schedules_group_subject_active` (`group_subject_id`,`is_active`)'
    );
    $step('Index jadwal per relasi grup–mapel dibuat.');
}

// ---------------------------------------------------------------------
// 8. Kolom legacy class_groups dibuat nullable (tidak dipakai lagi)
// ---------------------------------------------------------------------
foreach (['subject_id', 'teacher_id'] as $legacyColumn) {
    if (!$columnExists('class_groups', $legacyColumn)) {
        continue;
    }
    $stmt = $pdo->prepare(
        'SELECT IS_NULLABLE FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = "class_groups"
            AND COLUMN_NAME = :column'
    );
    $stmt->execute([':column' => $legacyColumn]);
    if ((string) $stmt->fetchColumn() === 'NO') {
        $pdo->exec(
            'ALTER TABLE `class_groups`
             MODIFY COLUMN `' . $legacyColumn . '` INT UNSIGNED NULL'
        );
        $step('Kolom legacy class_groups.' . $legacyColumn . ' dijadikan nullable.');
    }
}

// ---------------------------------------------------------------------
// 9. Verifikasi akhir
// ---------------------------------------------------------------------
$summary = [
    'group_subjects'   => (int) $pdo->query('SELECT COUNT(*) FROM `group_subjects`')->fetchColumn(),
    'group_schedules'  => (int) $pdo->query('SELECT COUNT(*) FROM `group_schedules`')->fetchColumn(),
    'schedules_linked' => (int) $pdo->query(
        'SELECT COUNT(*) FROM `group_schedules` gs
           JOIN `group_subjects` gsub ON gsub.`id` = gs.`group_subject_id`'
    )->fetchColumn(),
];

if ($summary['group_schedules'] !== $summary['schedules_linked']) {
    fwrite(STDERR, 'GAGAL: jumlah jadwal dan relasi tidak konsisten.' . PHP_EOL);
    exit(1);
}

echo '=== Migrasi selesai ===' . PHP_EOL;
echo 'group_subjects   : ' . $summary['group_subjects'] . PHP_EOL;
echo 'group_schedules  : ' . $summary['group_schedules'] . PHP_EOL;
echo 'jadwal terhubung : ' . $summary['schedules_linked'] . PHP_EOL;
