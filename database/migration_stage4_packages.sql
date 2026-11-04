-- =====================================================================
-- Govinda Education — Migrasi Stage 4
-- Realisasi pertemuan, paket belajar 8 pertemuan, tagihan paket,
-- pembayaran penuh, dan nota pembayaran.
--
-- PENTING:
--   * Jalankan database/migrate_stage4_packages.php (idempoten, membuat
--     backup otomatis) — file SQL ini adalah dokumentasi/perbandingan.
--   * Tabel billing_periods dan invoice bulanan lama TIDAK dihapus.
--   * Invoice lama tetap tersimpan sebagai arsip (invoice_type='bulanan').
--   * Revisi paket berjalan tanpa pelunasan: runner
--     migrate_stage4_packages.php memakai status aktif/selesai/dibatalkan,
--     paket berikutnya langsung aktif, dan tabel payment_allocations.
--     Bagian ENUM lama di bawah hanya jejak migrasi awal; runner adalah
--     sumber resmi dan aman dijalankan berulang.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. REALISASI PERTEMUAN
--    Satu baris = satu kegiatan belajar pada satu tanggal.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `class_meetings` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. PAKET BELAJAR SISWA (8 pertemuan aktual)
--    Satu siswa hanya boleh memiliki satu paket aktif per keanggotaan.
--    Keunikan paket aktif dijaga generated column + unique index.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_packages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `group_member_id` INT UNSIGNED NOT NULL,
  `group_id` INT UNSIGNED NOT NULL,
  `package_number` TINYINT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `target_meetings` TINYINT UNSIGNED NOT NULL DEFAULT 8,
  `completed_meetings` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('aktif','selesai','menunggu_pembayaran','menunggu_paket_berikutnya','dibatalkan')
      NOT NULL DEFAULT 'aktif',
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
    IF(`status` IN ('aktif','menunggu_pembayaran'), CONCAT(`student_id`, ':', `group_member_id`), NULL)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. RIWAYAT PERTEMUAN YANG MASUK KE PAKET
--    UNIQUE (student_id, class_meeting_id) = tidak ada penghitungan ganda
--    walaupun realisasi diubah/diaktifkan ulang.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_package_meetings` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. TARIF: nominal kini untuk satu paket 8 pertemuan
-- ---------------------------------------------------------------------
ALTER TABLE `pricing_rules`
  ADD COLUMN `package_size` TINYINT UNSIGNED NOT NULL DEFAULT 8 AFTER `student_count`;

-- ---------------------------------------------------------------------
-- 5. INVOICES: dukung tagihan paket tanpa periode bulanan
-- ---------------------------------------------------------------------
ALTER TABLE `invoices`
  MODIFY COLUMN `billing_period_id` INT UNSIGNED DEFAULT NULL,
  MODIFY COLUMN `subject_name` VARCHAR(100) DEFAULT NULL,
  MODIFY COLUMN `teacher_name` VARCHAR(120) DEFAULT NULL,
  ADD COLUMN `student_package_id` INT UNSIGNED DEFAULT NULL AFTER `group_member_id`,
  ADD COLUMN `package_number` TINYINT UNSIGNED DEFAULT NULL AFTER `billing_period_id`,
  ADD COLUMN `meeting_target` TINYINT UNSIGNED DEFAULT NULL DEFAULT 8 AFTER `package_number`,
  ADD COLUMN `invoice_type` ENUM('bulanan','paket') NOT NULL DEFAULT 'bulanan' AFTER `period_label`,
  ADD COLUMN `active_package_invoice_key` VARCHAR(64) GENERATED ALWAYS AS (
    IF(`status` = 'dibatalkan' OR `student_package_id` IS NULL, NULL, `student_package_id`)
  ) STORED,
  ADD UNIQUE KEY `uq_invoices_active_package` (`active_package_invoice_key`),
  ADD KEY `idx_invoices_type_status` (`invoice_type`,`status`),
  ADD KEY `idx_invoices_package` (`student_package_id`),
  ADD CONSTRAINT `fk_invoices_student_package` FOREIGN KEY (`student_package_id`)
      REFERENCES `student_packages` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- FK balik: paket menunjuk invoice yang menagihnya (ditambahkan setelah
-- tabel invoices memiliki kolom student_package_id).
ALTER TABLE `student_packages`
  ADD CONSTRAINT `fk_student_packages_invoice` FOREIGN KEY (`invoice_id`)
      REFERENCES `invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ---------------------------------------------------------------------
-- 6. PAYMENTS: nomor referensi, status, dan waktu terima
-- ---------------------------------------------------------------------
ALTER TABLE `payments`
  ADD COLUMN `reference_number` VARCHAR(100) DEFAULT NULL AFTER `method`,
  ADD COLUMN `status` ENUM('berhasil','dibatalkan') NOT NULL DEFAULT 'berhasil' AFTER `received_by`,
  ADD COLUMN `received_by_name` VARCHAR(100) DEFAULT NULL AFTER `received_by`,
  ADD COLUMN `paid_at` DATETIME DEFAULT NULL AFTER `payment_date`,
  ADD KEY `idx_payments_status_date` (`status`,`payment_date`);
