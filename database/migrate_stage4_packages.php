<?php
declare(strict_types=1);

/**
 * Migrasi Stage 4: realisasi pertemuan, paket belajar 8 pertemuan,
 * tagihan paket, pembayaran penuh, dan nota.
 *
 * Jalankan dari root proyek:
 *   php database/migrate_stage4_packages.php
 *
 * Sifat migrasi:
 * - Idempoten: aman dijalankan lebih dari sekali (kolom/index dicek dulu).
 * - Membuat backup seluruh tabel ke storage/backups/ sebelum mengubah apa pun.
 * - Tidak menghapus billing_periods maupun invoice bulanan lama.
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

$columnNullable = static function (string $table, string $column) use ($pdo, $dbName): ?bool {
    $stmt = $pdo->prepare(
        'SELECT IS_NULLABLE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND COLUMN_NAME = :column'
    );
    $stmt->execute([':db' => $dbName, ':table' => $table, ':column' => $column]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : ((string) $value === 'YES');
};

$indexExists = static function (string $table, string $index) use ($pdo, $dbName): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND INDEX_NAME = :index'
    );
    $stmt->execute([':db' => $dbName, ':table' => $table, ':index' => $index]);
    return (int) $stmt->fetchColumn() > 0;
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

// ---------------------------------------------------------------------
// 0. BACKUP
// ---------------------------------------------------------------------
$backupDir = ROOT_PATH . '/storage/backups';
if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, 'GAGAL: folder backup tidak dapat dibuat: ' . $backupDir . PHP_EOL);
    exit(1);
}

$backupFile = $backupDir . '/stage4_' . date('Ymd_His') . '.sql';
$handle = @fopen($backupFile, 'wb');
if ($handle === false) {
    fwrite(STDERR, 'GAGAL: file backup tidak dapat ditulis: ' . $backupFile . PHP_EOL);
    exit(1);
}

fwrite($handle, "-- Backup otomatis Govinda Education sebelum migrasi Stage 4\n");
fwrite($handle, "-- Dibuat: " . date('Y-m-d H:i:s') . "\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

$tables = array_map(
    'strval',
    $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)
);
foreach ($tables as $table) {
    $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch();
    if (!is_array($create)) {
        continue;
    }
    fwrite($handle, 'DROP TABLE IF EXISTS `' . $table . "`;\n");
    fwrite($handle, (string) ($create['Create Table'] ?? $create[1] ?? '') . ";\n");
    $rows = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`')->fetchAll();
    if ($rows === []) {
        fwrite($handle, "\n");
        continue;
    }
    $columns = array_keys($rows[0]);
    $columnSql = '`' . implode('`,`', array_map(static fn (string $c): string => str_replace('`', '``', $c), $columns)) . '`';
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

echo '=== Migrasi Stage 4: paket belajar & keuangan ===' . PHP_EOL;

// DDL MySQL/MariaDB menyebabkan implicit commit, sehingga transaksi tidak
// dapat membungkus seluruh migrasi. Keamanan dijamin oleh pengecekan
// idempoten per objek + backup penuh di atas.
try {
    // -----------------------------------------------------------------
    // 1. class_meetings
    // -----------------------------------------------------------------
    if (!$tableExists('class_meetings')) {
        $pdo->exec(
            "CREATE TABLE `class_meetings` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `group_id` INT UNSIGNED NOT NULL,
                `group_subject_id` INT UNSIGNED NOT NULL,
                `teacher_id` INT UNSIGNED NOT NULL,
                `schedule_id` INT UNSIGNED DEFAULT NULL,
                `meeting_date` DATE NOT NULL,
                `start_time` TIME NOT NULL,
                `end_time` TIME NOT NULL,
                `status` ENUM('terjadwal','terlaksana','dibatalkan','dijadwalkan_ulang')
                    NOT NULL DEFAULT 'terjadwal',
                `notes` VARCHAR(255) DEFAULT NULL,
                `rescheduled_from_id` INT UNSIGNED DEFAULT NULL,
                `created_by` INT UNSIGNED DEFAULT NULL,
                `updated_by` INT UNSIGNED DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_class_meetings_schedule_date` (`schedule_id`,`meeting_date`),
                UNIQUE KEY `uq_class_meetings_slot` (`group_subject_id`,`meeting_date`,`start_time`),
                KEY `idx_class_meetings_date_status` (`meeting_date`,`status`),
                KEY `idx_class_meetings_group_date` (`group_id`,`meeting_date`),
                KEY `idx_class_meetings_subject_date` (`group_subject_id`,`meeting_date`),
                KEY `idx_class_meetings_teacher_date` (`teacher_id`,`meeting_date`),
                CONSTRAINT `fk_class_meetings_group` FOREIGN KEY (`group_id`)
                    REFERENCES `class_groups` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_class_meetings_group_subject` FOREIGN KEY (`group_subject_id`)
                    REFERENCES `group_subjects` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_class_meetings_teacher` FOREIGN KEY (`teacher_id`)
                    REFERENCES `teachers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_class_meetings_schedule` FOREIGN KEY (`schedule_id`)
                    REFERENCES `group_schedules` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_class_meetings_rescheduled` FOREIGN KEY (`rescheduled_from_id`)
                    REFERENCES `class_meetings` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_class_meetings_created_by` FOREIGN KEY (`created_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_class_meetings_updated_by` FOREIGN KEY (`updated_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `chk_class_meetings_time` CHECK (`end_time` > `start_time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $step('Tabel class_meetings dibuat.');
    } else {
        $step('Tabel class_meetings sudah ada, dilewati.');
    }

    // -----------------------------------------------------------------
    // 2. student_packages
    // -----------------------------------------------------------------
    if (!$tableExists('student_packages')) {
        $pdo->exec(
            "CREATE TABLE `student_packages` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `student_id` INT UNSIGNED NOT NULL,
                `group_member_id` INT UNSIGNED NOT NULL,
                `group_id` INT UNSIGNED NOT NULL,
                `package_number` TINYINT UNSIGNED NOT NULL,
                `start_date` DATE NOT NULL,
                `end_date` DATE DEFAULT NULL,
                `target_meetings` TINYINT UNSIGNED NOT NULL DEFAULT 8,
                `completed_meetings` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `status` ENUM('aktif','selesai','dibatalkan') NOT NULL DEFAULT 'aktif',
                `invoice_id` INT UNSIGNED DEFAULT NULL,
                `pricing_rule_id` INT UNSIGNED DEFAULT NULL,
                `group_type` ENUM('private','group') NOT NULL,
                `member_count` TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `base_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `cancel_reason` VARCHAR(255) DEFAULT NULL,
                `cancelled_at` DATETIME DEFAULT NULL,
                `cancelled_by` INT UNSIGNED DEFAULT NULL,
                `notes` TEXT DEFAULT NULL,
                `created_by` INT UNSIGNED DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `active_package_key` VARCHAR(64) GENERATED ALWAYS AS (
                    IF(`status` = 'aktif',
                       CONCAT(`student_id`, ':', `group_member_id`), NULL)
                ) STORED,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_student_packages_number` (`student_id`,`group_member_id`,`package_number`),
                UNIQUE KEY `uq_student_packages_active` (`active_package_key`),
                KEY `idx_student_packages_student_status` (`student_id`,`status`),
                KEY `idx_student_packages_group_status` (`group_id`,`status`),
                KEY `idx_student_packages_member` (`group_member_id`,`status`),
                KEY `idx_student_packages_invoice` (`invoice_id`),
                KEY `idx_student_packages_pricing` (`pricing_rule_id`),
                CONSTRAINT `fk_student_packages_student` FOREIGN KEY (`student_id`)
                    REFERENCES `students` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_student_packages_member` FOREIGN KEY (`group_member_id`)
                    REFERENCES `group_members` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_student_packages_group` FOREIGN KEY (`group_id`)
                    REFERENCES `class_groups` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_student_packages_pricing` FOREIGN KEY (`pricing_rule_id`)
                    REFERENCES `pricing_rules` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_student_packages_created_by` FOREIGN KEY (`created_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_student_packages_cancelled_by` FOREIGN KEY (`cancelled_by`)
                    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `chk_student_packages_target` CHECK (`target_meetings` BETWEEN 1 AND 100),
                CONSTRAINT `chk_student_packages_progress` CHECK (`completed_meetings` <= `target_meetings`),
                CONSTRAINT `chk_student_packages_amount` CHECK (
                    `base_amount` >= 0 AND `discount_amount` >= 0 AND `total_amount` >= 0
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $step('Tabel student_packages dibuat.');
    } else {
        $step('Tabel student_packages sudah ada, dilewati.');
    }

    // -----------------------------------------------------------------
    // 3. student_package_meetings
    // -----------------------------------------------------------------
    if (!$tableExists('student_package_meetings')) {
        $pdo->exec(
            "CREATE TABLE `student_package_meetings` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `student_package_id` INT UNSIGNED NOT NULL,
                `class_meeting_id` INT UNSIGNED NOT NULL,
                `student_id` INT UNSIGNED NOT NULL,
                `meeting_date` DATE NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_package_meetings_member` (`student_package_id`,`class_meeting_id`),
                UNIQUE KEY `uq_package_meetings_student` (`student_id`,`class_meeting_id`),
                KEY `idx_package_meetings_date` (`meeting_date`),
                CONSTRAINT `fk_package_meetings_package` FOREIGN KEY (`student_package_id`)
                    REFERENCES `student_packages` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_package_meetings_meeting` FOREIGN KEY (`class_meeting_id`)
                    REFERENCES `class_meetings` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_package_meetings_student` FOREIGN KEY (`student_id`)
                    REFERENCES `students` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $step('Tabel student_package_meetings dibuat.');
    } else {
        $step('Tabel student_package_meetings sudah ada, dilewati.');
    }

    // -----------------------------------------------------------------
    // 4. pricing_rules.package_size
    // -----------------------------------------------------------------
    if (!$columnExists('pricing_rules', 'package_size')) {
        $pdo->exec(
            'ALTER TABLE `pricing_rules`
             ADD COLUMN `package_size` TINYINT UNSIGNED NOT NULL DEFAULT 8 AFTER `student_count`'
        );
        $step('Kolom pricing_rules.package_size ditambahkan (default 8).');
    } else {
        $step('Kolom pricing_rules.package_size sudah ada, dilewati.');
    }

    // -----------------------------------------------------------------
    // 5. invoices: dukungan tagihan paket
    // -----------------------------------------------------------------
    if ($columnNullable('invoices', 'billing_period_id') !== true) {
        $pdo->exec('ALTER TABLE `invoices` MODIFY COLUMN `billing_period_id` INT UNSIGNED DEFAULT NULL');
        $step('invoices.billing_period_id dijadikan nullable (tagihan paket tanpa periode).');
    }
    if ($columnNullable('invoices', 'subject_name') !== true) {
        $pdo->exec('ALTER TABLE `invoices` MODIFY COLUMN `subject_name` VARCHAR(100) DEFAULT NULL');
        $step('invoices.subject_name dijadikan nullable.');
    }
    if ($columnNullable('invoices', 'teacher_name') !== true) {
        $pdo->exec('ALTER TABLE `invoices` MODIFY COLUMN `teacher_name` VARCHAR(120) DEFAULT NULL');
        $step('invoices.teacher_name dijadikan nullable.');
    }
    if (!$columnExists('invoices', 'student_package_id')) {
        $pdo->exec('ALTER TABLE `invoices` ADD COLUMN `student_package_id` INT UNSIGNED DEFAULT NULL AFTER `group_member_id`');
        $step('Kolom invoices.student_package_id ditambahkan.');
    }
    if (!$columnExists('invoices', 'package_number')) {
        $pdo->exec('ALTER TABLE `invoices` ADD COLUMN `package_number` TINYINT UNSIGNED DEFAULT NULL AFTER `billing_period_id`');
        $step('Kolom invoices.package_number ditambahkan.');
    }
    if (!$columnExists('invoices', 'meeting_target')) {
        $pdo->exec('ALTER TABLE `invoices` ADD COLUMN `meeting_target` TINYINT UNSIGNED DEFAULT NULL DEFAULT 8 AFTER `package_number`');
        $step('Kolom invoices.meeting_target ditambahkan.');
    }
    if (!$columnExists('invoices', 'invoice_type')) {
        $pdo->exec(
            "ALTER TABLE `invoices`
             ADD COLUMN `invoice_type` ENUM('bulanan','paket') NOT NULL DEFAULT 'bulanan' AFTER `period_label`"
        );
        $step('Kolom invoices.invoice_type ditambahkan.');
    }
    if (!$columnExists('invoices', 'active_package_invoice_key')) {
        $pdo->exec(
            "ALTER TABLE `invoices`
             ADD COLUMN `active_package_invoice_key` VARCHAR(64) GENERATED ALWAYS AS (
                IF(`status` = 'dibatalkan' OR `student_package_id` IS NULL, NULL, `student_package_id`)
             ) STORED"
        );
        $step('Generated column active_package_invoice_key ditambahkan.');
    }
    if (!$indexExists('invoices', 'uq_invoices_active_package')) {
        $pdo->exec('ALTER TABLE `invoices` ADD UNIQUE KEY `uq_invoices_active_package` (`active_package_invoice_key`)');
        $step('Unique key satu tagihan aktif per paket dibuat.');
    }
    if (!$indexExists('invoices', 'idx_invoices_type_status')) {
        $pdo->exec('ALTER TABLE `invoices` ADD KEY `idx_invoices_type_status` (`invoice_type`,`status`)');
        $step('Index invoices.invoice_type/status dibuat.');
    }
    if (!$indexExists('invoices', 'idx_invoices_package')) {
        $pdo->exec('ALTER TABLE `invoices` ADD KEY `idx_invoices_package` (`student_package_id`)');
        $step('Index invoices.student_package_id dibuat.');
    }
    if (!$fkExists('invoices', 'fk_invoices_student_package')) {
        $pdo->exec(
            'ALTER TABLE `invoices`
             ADD CONSTRAINT `fk_invoices_student_package` FOREIGN KEY (`student_package_id`)
             REFERENCES `student_packages` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE'
        );
        $step('Foreign key invoices → student_packages dibuat.');
    }
    if (!$fkExists('student_packages', 'fk_student_packages_invoice')) {
        $pdo->exec(
            'ALTER TABLE `student_packages`
             ADD CONSTRAINT `fk_student_packages_invoice` FOREIGN KEY (`invoice_id`)
             REFERENCES `invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'
        );
        $step('Foreign key student_packages → invoices dibuat.');
    }

    // -----------------------------------------------------------------
    // 6. payments: referensi, status, waktu
    // -----------------------------------------------------------------
    if (!$columnExists('payments', 'reference_number')) {
        $pdo->exec('ALTER TABLE `payments` ADD COLUMN `reference_number` VARCHAR(100) DEFAULT NULL AFTER `method`');
        $step('Kolom payments.reference_number ditambahkan.');
    }
    if (!$columnExists('payments', 'status')) {
        $pdo->exec(
            "ALTER TABLE `payments`
             ADD COLUMN `status` ENUM('berhasil','dibatalkan') NOT NULL DEFAULT 'berhasil' AFTER `received_by`"
        );
        $step('Kolom payments.status ditambahkan.');
    }
    if (!$columnExists('payments', 'received_by_name')) {
        $pdo->exec(
            'ALTER TABLE `payments` ADD COLUMN `received_by_name` VARCHAR(100) DEFAULT NULL AFTER `received_by`'
        );
        $pdo->exec(
            'UPDATE `payments` pay
             JOIN `users` u ON u.id = pay.received_by
             SET pay.received_by_name = u.name
             WHERE pay.received_by_name IS NULL'
        );
        $step('Kolom payments.received_by_name (snapshot petugas untuk nota) ditambahkan.');
    }
    if (!$columnExists('payments', 'paid_at')) {
        $pdo->exec('ALTER TABLE `payments` ADD COLUMN `paid_at` DATETIME DEFAULT NULL AFTER `payment_date`');
        $pdo->exec('UPDATE `payments` SET `paid_at` = `created_at` WHERE `paid_at` IS NULL');
        $step('Kolom payments.paid_at ditambahkan dan data lama diisi.');
    }
    if (!$indexExists('payments', 'idx_payments_status_date')) {
        $pdo->exec('ALTER TABLE `payments` ADD KEY `idx_payments_status_date` (`status`,`payment_date`)');
        $step('Index payments.status/payment_date dibuat.');
    }

    // -----------------------------------------------------------------
    // 6b. payment_allocations: satu pembayaran boleh melunasi beberapa
    //     tagihan, satu tagihan hanya boleh dialokasikan sekali.
    // -----------------------------------------------------------------
    if (!$tableExists('payment_allocations')) {
        $pdo->exec(
            "CREATE TABLE `payment_allocations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `payment_id` INT UNSIGNED NOT NULL,
                `invoice_id` INT UNSIGNED NOT NULL,
                `amount` DECIMAL(12,2) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_payment_allocations_payment_invoice` (`payment_id`,`invoice_id`),
                UNIQUE KEY `uq_payment_allocations_invoice` (`invoice_id`),
                CONSTRAINT `fk_payment_allocations_payment` FOREIGN KEY (`payment_id`)
                    REFERENCES `payments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_payment_allocations_invoice` FOREIGN KEY (`invoice_id`)
                    REFERENCES `invoices` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `chk_payment_allocations_amount` CHECK (`amount` > 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $step('Tabel payment_allocations dibuat.');
    } else {
        $step('Tabel payment_allocations sudah ada, dilewati pembuatan.');
    }

    // payments.invoice_id boleh NULL karena satu pembayaran dapat melunasi
    // beberapa invoice sekaligus (relasi resmi ada di payment_allocations).
    if ($columnNullable('payments', 'invoice_id') !== true) {
        $pdo->exec('ALTER TABLE `payments` MODIFY COLUMN `invoice_id` INT UNSIGNED DEFAULT NULL');
        $step('payments.invoice_id dijadikan nullable (pembayaran multi-tagihan).');
    }
    if ($indexExists('payments', 'uq_payments_invoice')) {
        if ($fkExists('payments', 'fk_payments_invoice')) {
            $pdo->exec('ALTER TABLE `payments` DROP FOREIGN KEY `fk_payments_invoice`');
        }
        $pdo->exec('ALTER TABLE `payments` DROP INDEX `uq_payments_invoice`');
        $step('Unique lama payments.invoice_id dihapus.');
    }
    if (!$indexExists('payments', 'idx_payments_invoice')) {
        $pdo->exec('ALTER TABLE `payments` ADD KEY `idx_payments_invoice` (`invoice_id`)');
    }
    if (!$fkExists('payments', 'fk_payments_invoice')) {
        $pdo->exec(
            'ALTER TABLE `payments`
             ADD CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`)
             REFERENCES `invoices` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE'
        );
        $step('Foreign key payments.invoice_id dipasang kembali (nullable).');
    }

    // Salin pembayaran lama ke tabel alokasi agar laporan dan nota tetap utuh.
    $pdo->exec(
        "INSERT INTO `payment_allocations` (`payment_id`, `invoice_id`, `amount`)
         SELECT pay.id, pay.invoice_id, i.total_amount
         FROM `payments` pay
         JOIN `invoices` i ON i.id = pay.invoice_id
         WHERE pay.invoice_id IS NOT NULL
           AND pay.status = 'berhasil'
           AND NOT EXISTS (
               SELECT 1 FROM `payment_allocations` pa WHERE pa.payment_id = pay.id
           )"
    );
    $step('Pembayaran lama tanpa alokasi disalin ke payment_allocations.');

    if ($indexExists('payment_allocations', 'uq_payment_allocations_payment')
        || $indexExists('payment_allocations', 'idx_payment_allocations_invoice')) {
        if ($fkExists('payment_allocations', 'fk_payment_allocations_payment')) {
            $pdo->exec('ALTER TABLE `payment_allocations` DROP FOREIGN KEY `fk_payment_allocations_payment`');
        }
        if ($fkExists('payment_allocations', 'fk_payment_allocations_invoice')) {
            $pdo->exec('ALTER TABLE `payment_allocations` DROP FOREIGN KEY `fk_payment_allocations_invoice`');
        }
        if ($indexExists('payment_allocations', 'uq_payment_allocations_payment')) {
            $pdo->exec('ALTER TABLE `payment_allocations` DROP INDEX `uq_payment_allocations_payment`');
            $step('Unique lama satu alokasi per pembayaran dihapus.');
        }
        if ($indexExists('payment_allocations', 'idx_payment_allocations_invoice')) {
            $pdo->exec('ALTER TABLE `payment_allocations` DROP INDEX `idx_payment_allocations_invoice`');
            $step('Index lama payment_allocations.invoice_id dihapus (diganti unique).');
        }
    }
    if (!$indexExists('payment_allocations', 'uq_payment_allocations_payment_invoice')) {
        $pdo->exec(
            'ALTER TABLE `payment_allocations`
             ADD UNIQUE KEY `uq_payment_allocations_payment_invoice` (`payment_id`,`invoice_id`)'
        );
        $step('Unique (payment_id, invoice_id) dibuat.');
    }
    if (!$indexExists('payment_allocations', 'uq_payment_allocations_invoice')) {
        $pdo->exec(
            'ALTER TABLE `payment_allocations`
             ADD UNIQUE KEY `uq_payment_allocations_invoice` (`invoice_id`)'
        );
        $step('Unique satu alokasi sukses per tagihan dibuat.');
    }
    if (!$fkExists('payment_allocations', 'fk_payment_allocations_payment')) {
        $pdo->exec(
            'ALTER TABLE `payment_allocations`
             ADD CONSTRAINT `fk_payment_allocations_payment` FOREIGN KEY (`payment_id`)
             REFERENCES `payments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE'
        );
    }
    if (!$fkExists('payment_allocations', 'fk_payment_allocations_invoice')) {
        $pdo->exec(
            'ALTER TABLE `payment_allocations`
             ADD CONSTRAINT `fk_payment_allocations_invoice` FOREIGN KEY (`invoice_id`)
             REFERENCES `invoices` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE'
        );
    }

    // -----------------------------------------------------------------
    // 6c. student_packages: hanya tiga status (aktif/selesai/dibatalkan).
    //     Paket berikutnya langsung aktif tanpa menunggu pembayaran.
    // -----------------------------------------------------------------
    $legacyStatuses = (int) $pdo->query(
        "SELECT COUNT(*) FROM `student_packages`
         WHERE `status` IN ('menunggu_pembayaran','menunggu_paket_berikutnya')"
    )->fetchColumn();
    if ($legacyStatuses > 0) {
        if ($indexExists('student_packages', 'uq_student_packages_active')) {
            $pdo->exec('ALTER TABLE `student_packages` DROP INDEX `uq_student_packages_active`');
        }
        $pdo->exec(
            "UPDATE `student_packages` SET `status` = 'aktif'
             WHERE `status` IN ('menunggu_pembayaran','menunggu_paket_berikutnya')"
        );
        $step($legacyStatuses . ' paket lama berstatus menunggu diubah menjadi aktif.');
    }

    if ($columnExists('student_packages', 'active_package_key')) {
        if ($indexExists('student_packages', 'uq_student_packages_active')) {
            $pdo->exec('ALTER TABLE `student_packages` DROP INDEX `uq_student_packages_active`');
        }
        $pdo->exec('ALTER TABLE `student_packages` DROP COLUMN `active_package_key`');
        $step('Generated column active_package_key lama dihapus.');
    }

    $pdo->exec(
        "ALTER TABLE `student_packages`
         MODIFY COLUMN `status` ENUM('aktif','selesai','dibatalkan') NOT NULL DEFAULT 'aktif'"
    );
    $step('Enum status paket menjadi aktif/selesai/dibatalkan.');

    if (!$columnExists('student_packages', 'active_package_key')) {
        $pdo->exec(
            "ALTER TABLE `student_packages`
             ADD COLUMN `active_package_key` VARCHAR(64) GENERATED ALWAYS AS (
                IF(`status` = 'aktif', CONCAT(`student_id`, ':', `group_member_id`), NULL)
             ) STORED"
        );
        $step('Generated column active_package_key baru dibuat.');
    }
    if (!$indexExists('student_packages', 'uq_student_packages_active')) {
        $pdo->exec(
            'ALTER TABLE `student_packages`
             ADD UNIQUE KEY `uq_student_packages_active` (`active_package_key`)'
        );
        $step('Unique satu paket aktif per keanggotaan dibuat.');
    }

    $step('Seluruh perubahan selesai.');
} catch (Throwable $exception) {
    fwrite(STDERR, 'GAGAL migrasi: ' . $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, 'Backup tersimpan di: ' . $backupFile . PHP_EOL);
    exit(1);
}

// ---------------------------------------------------------------------
// 7. Verifikasi
// ---------------------------------------------------------------------
$summary = [
    'class_meetings'          => (int) $pdo->query('SELECT COUNT(*) FROM `class_meetings`')->fetchColumn(),
    'student_packages'        => (int) $pdo->query('SELECT COUNT(*) FROM `student_packages`')->fetchColumn(),
    'student_package_meetings'=> (int) $pdo->query('SELECT COUNT(*) FROM `student_package_meetings`')->fetchColumn(),
    'invoice_arsip_bulanan'   => (int) $pdo->query("SELECT COUNT(*) FROM `invoices` WHERE `invoice_type` = 'bulanan'")->fetchColumn(),
    'payment_lama'            => (int) $pdo->query('SELECT COUNT(*) FROM `payments`')->fetchColumn(),
    'billing_periods'         => (int) $pdo->query('SELECT COUNT(*) FROM `billing_periods`')->fetchColumn(),
];

echo '=== Migrasi Stage 4 selesai ===' . PHP_EOL;
foreach ($summary as $key => $value) {
    echo str_pad($key, 26, ' ') . ': ' . $value . PHP_EOL;
}
echo 'Backup: ' . $backupFile . PHP_EOL;
