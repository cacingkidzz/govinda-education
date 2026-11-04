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
-- =====================================================================
--  GOVINDA EDUCATION
--  Skema database lengkap — MySQL 5.7+ / MariaDB 10.4+
--  Charset : utf8mb4 | Collation : utf8mb4_unicode_ci | Engine : InnoDB
--
--  ATURAN BISNIS YANG DIJAGA OLEH SKEMA:
--  * Satu siswa hanya memiliki satu keanggotaan group aktif (UNIQUE generated).
--  * Satu siswa hanya memiliki satu invoice aktif per periode (UNIQUE generated).
--  * Satu siswa hanya memiliki satu paket aktif per keanggotaan (UNIQUE generated).
--  * Satu paket hanya memiliki satu invoice aktif (UNIQUE generated).
--  * Satu pertemuan hanya dihitung sekali per siswa (UNIQUE student_id+class_meeting_id).
--  * Satu invoice hanya dapat memiliki satu pembayaran berhasil (UNIQUE invoice_id).
--  * Invoice adalah snapshot; data group/guru/tarif disalin saat penerbitan.
--  * Tidak ada status cicilan. Status invoice: belum_bayar, lunas, dibatalkan.
--  * Data transaksi tidak dihapus permanen.
--
--  CATATAN TAHAP 4:
--  * Tagihan baru berbasis paket 8 pertemuan (invoices.invoice_type='paket').
--  * Tabel billing_periods dan invoice bulanan lama dipertahankan sebagai arsip.
--  * Tarif (pricing_rules.amount) adalah nominal per paket 8 pertemuan.
--
--  Impor melalui phpMyAdmin atau:
--    mysql -u root < database/govinda_education.sql
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. USERS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','petugas') NOT NULL DEFAULT 'petugas',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_active` (`role`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. TEACHERS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `teachers`;
CREATE TABLE `teachers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(30) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `gender` ENUM('L','P') NOT NULL,
  `birth_place` VARCHAR(100) DEFAULT NULL,
  `birth_date` DATE DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `education` VARCHAR(120) DEFAULT NULL,
  `photo` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teachers_code` (`code`),
  KEY `idx_teachers_active_name` (`is_active`,`name`),
  KEY `idx_teachers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. STUDENTS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(30) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `gender` ENUM('L','P') NOT NULL,
  `birth_place` VARCHAR(100) DEFAULT NULL,
  `birth_date` DATE DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `parent_name` VARCHAR(120) DEFAULT NULL,
  `parent_phone` VARCHAR(30) DEFAULT NULL,
  `school` VARCHAR(150) DEFAULT NULL,
  `school_class` VARCHAR(50) DEFAULT NULL,
  `join_date` DATE NOT NULL,
  `photo` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `public_token` VARCHAR(64) DEFAULT NULL,
  `public_token_created_at` DATETIME DEFAULT NULL,
  `self_updated_at` DATETIME DEFAULT NULL,
  `profile_completed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_students_code` (`code`),
  UNIQUE KEY `uq_students_public_token` (`public_token`),
  KEY `idx_students_active_name` (`is_active`,`name`),
  KEY `idx_students_name` (`name`),
  KEY `idx_students_join_date` (`join_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. SUBJECTS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(30) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subjects_code` (`code`),
  UNIQUE KEY `uq_subjects_name` (`name`),
  KEY `idx_subjects_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4b. TEACHER SUBJECTS (relasi guru - mata pelajaran)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `teacher_subjects`;
CREATE TABLE `teacher_subjects` (
  `teacher_id` INT UNSIGNED NOT NULL,
  `subject_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`teacher_id`,`subject_id`),
  KEY `idx_teacher_subjects_subject` (`subject_id`),
  CONSTRAINT `fk_teacher_subjects_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_teacher_subjects_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. CLASS GROUPS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `class_groups`;
CREATE TABLE `class_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(30) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `group_type` ENUM('private','group') NOT NULL,
  `subject_id` INT UNSIGNED DEFAULT NULL,
  `teacher_id` INT UNSIGNED DEFAULT NULL,
  `capacity` TINYINT UNSIGNED NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_groups_code` (`code`),
  KEY `idx_class_groups_active` (`is_active`),
  KEY `idx_class_groups_subject` (`subject_id`),
  KEY `idx_class_groups_teacher` (`teacher_id`),
  CONSTRAINT `fk_class_groups_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_class_groups_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_class_groups_capacity` CHECK (
    (`group_type` = 'private' AND `capacity` = 1) OR
    (`group_type` = 'group' AND `capacity` BETWEEN 2 AND 7)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5a. GROUP SUBJECTS (relasi grup–mata pelajaran–guru)
--     Satu grup boleh memiliki banyak mata pelajaran.
--     Satu mata pelajaran hanya sekali dalam satu grup.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `group_subjects`;
CREATE TABLE `group_subjects` (
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
  CONSTRAINT `fk_group_subjects_group` FOREIGN KEY (`group_id`) REFERENCES `class_groups` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_group_subjects_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_group_subjects_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. GROUP SCHEDULES
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `group_schedules`;
CREATE TABLE `group_schedules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_subject_id` INT UNSIGNED NOT NULL,
  `day_of_week` TINYINT UNSIGNED NOT NULL COMMENT '1=Senin ... 7=Minggu',
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `room` VARCHAR(100) DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_group_schedule_slot` (`group_subject_id`,`day_of_week`,`start_time`),
  KEY `idx_group_schedules_day_time` (`day_of_week`,`start_time`,`end_time`),
  KEY `idx_group_schedules_group` (`is_active`),
  KEY `idx_group_schedules_group_subject_active` (`group_subject_id`,`is_active`),
  CONSTRAINT `fk_group_schedules_group_subject` FOREIGN KEY (`group_subject_id`) REFERENCES `group_subjects` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_group_schedules_day` CHECK (`day_of_week` BETWEEN 1 AND 7),
  CONSTRAINT `chk_group_schedules_time` CHECK (`start_time` < `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. GROUP MEMBERS
--    end_date NULL = keanggotaan aktif.
--    active_student_id + UNIQUE menjamin satu keanggotaan aktif per siswa.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `group_members`;
CREATE TABLE `group_members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `active_student_id` INT UNSIGNED GENERATED ALWAYS AS (IF(`end_date` IS NULL, `student_id`, NULL)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_group_members_active_student` (`active_student_id`),
  KEY `idx_group_members_group` (`group_id`,`end_date`),
  KEY `idx_group_members_student` (`student_id`,`start_date`),
  KEY `idx_group_members_created_by` (`created_by`),
  CONSTRAINT `fk_group_members_group` FOREIGN KEY (`group_id`) REFERENCES `class_groups` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_group_members_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_group_members_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_group_members_period` CHECK (`end_date` IS NULL OR `end_date` >= `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. STUDENT GROUP HISTORY
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `student_group_history`;
CREATE TABLE `student_group_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `from_group_id` INT UNSIGNED DEFAULT NULL,
  `to_group_id` INT UNSIGNED DEFAULT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `changed_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_history_student` (`student_id`,`start_date`),
  KEY `idx_history_from_group` (`from_group_id`),
  KEY `idx_history_to_group` (`to_group_id`),
  KEY `idx_history_changed_by` (`changed_by`),
  CONSTRAINT `fk_history_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_history_from_group` FOREIGN KEY (`from_group_id`) REFERENCES `class_groups` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_history_to_group` FOREIGN KEY (`to_group_id`) REFERENCES `class_groups` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_history_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_history_period` CHECK (`end_date` IS NULL OR `end_date` >= `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. PRICING RULES
--     Tarif per siswa untuk satu paket 8 pertemuan, efektif berdasarkan tanggal.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `pricing_rules`;
CREATE TABLE `pricing_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_type` ENUM('private','group') NOT NULL,
  `student_count` TINYINT UNSIGNED NOT NULL,
  `package_size` TINYINT UNSIGNED NOT NULL DEFAULT 8,
  `amount` DECIMAL(12,2) NOT NULL,
  `effective_start` DATE NOT NULL,
  `effective_end` DATE DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pricing_rule_start` (`group_type`,`student_count`,`effective_start`),
  KEY `idx_pricing_lookup` (`group_type`,`student_count`,`effective_start`,`effective_end`),
  KEY `idx_pricing_active` (`is_active`),
  CONSTRAINT `fk_pricing_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_pricing_student_count` CHECK (`student_count` BETWEEN 1 AND 7),
  CONSTRAINT `chk_pricing_amount` CHECK (`amount` >= 0),
  CONSTRAINT `chk_pricing_period` CHECK (`effective_end` IS NULL OR `effective_end` >= `effective_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. BILLING PERIODS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `billing_periods`;
CREATE TABLE `billing_periods` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `month` TINYINT UNSIGNED NOT NULL,
  `year` SMALLINT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `issue_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `status` ENUM('draft','aktif','ditutup') NOT NULL DEFAULT 'draft',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_billing_period_month_year` (`month`,`year`),
  KEY `idx_billing_period_status` (`status`),
  KEY `idx_billing_period_dates` (`start_date`,`end_date`),
  CONSTRAINT `fk_billing_period_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_billing_period_month` CHECK (`month` BETWEEN 1 AND 12),
  CONSTRAINT `chk_billing_period_year` CHECK (`year` BETWEEN 2000 AND 2100),
  CONSTRAINT `chk_billing_period_dates` CHECK (`end_date` >= `start_date`),
  CONSTRAINT `chk_billing_period_due` CHECK (`due_date` >= `issue_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. INVOICES  (snapshot saat diterbitkan)
--     invoice_type='bulanan' = arsip tagihan bulanan lama.
--     invoice_type='paket'   = tagihan paket 8 pertemuan (Tahap 4).
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(40) NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `group_id` INT UNSIGNED NOT NULL,
  `group_member_id` INT UNSIGNED DEFAULT NULL,
  `student_package_id` INT UNSIGNED DEFAULT NULL,
  `billing_period_id` INT UNSIGNED DEFAULT NULL,
  `package_number` TINYINT UNSIGNED DEFAULT NULL,
  `meeting_target` TINYINT UNSIGNED DEFAULT NULL DEFAULT 8,
  `pricing_rule_id` INT UNSIGNED DEFAULT NULL,
  `student_code` VARCHAR(30) NOT NULL,
  `student_name` VARCHAR(120) NOT NULL,
  `group_name` VARCHAR(120) NOT NULL,
  `group_type` ENUM('private','group') NOT NULL,
  `subject_name` VARCHAR(100) DEFAULT NULL,
  `teacher_name` VARCHAR(120) DEFAULT NULL,
  `period_label` VARCHAR(100) NOT NULL,
  `invoice_type` ENUM('bulanan','paket') NOT NULL DEFAULT 'bulanan',
  `member_count` TINYINT UNSIGNED NOT NULL,
  `unit_amount` DECIMAL(12,2) NOT NULL,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL,
  `issue_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `status` ENUM('belum_bayar','lunas','dibatalkan') NOT NULL DEFAULT 'belum_bayar',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `cancelled_at` DATETIME DEFAULT NULL,
  `cancelled_by` INT UNSIGNED DEFAULT NULL,
  `cancel_reason` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `active_invoice_key` VARCHAR(64) GENERATED ALWAYS AS (
    IF(`status` = 'dibatalkan', NULL, CONCAT(`student_id`, ':', `billing_period_id`))
  ) STORED,
  `active_package_invoice_key` VARCHAR(64) GENERATED ALWAYS AS (
    IF(`status` = 'dibatalkan' OR `student_package_id` IS NULL, NULL, `student_package_id`)
  ) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoices_number` (`invoice_number`),
  UNIQUE KEY `uq_invoices_active_period` (`active_invoice_key`),
  UNIQUE KEY `uq_invoices_active_package` (`active_package_invoice_key`),
  KEY `idx_invoices_student_status` (`student_id`,`status`),
  KEY `idx_invoices_period_status` (`billing_period_id`,`status`),
  KEY `idx_invoices_group` (`group_id`),
  KEY `idx_invoices_due_status` (`due_date`,`status`),
  KEY `idx_invoices_type_status` (`invoice_type`,`status`),
  KEY `idx_invoices_package` (`student_package_id`),
  KEY `idx_invoices_created_by` (`created_by`),
  CONSTRAINT `fk_invoices_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_invoices_group` FOREIGN KEY (`group_id`) REFERENCES `class_groups` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_invoices_member` FOREIGN KEY (`group_member_id`) REFERENCES `group_members` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_invoices_student_package` FOREIGN KEY (`student_package_id`) REFERENCES `student_packages` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_invoices_period` FOREIGN KEY (`billing_period_id`) REFERENCES `billing_periods` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_invoices_pricing` FOREIGN KEY (`pricing_rule_id`) REFERENCES `pricing_rules` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_invoices_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_invoices_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_invoices_amounts` CHECK (`unit_amount` >= 0 AND `discount_amount` >= 0 AND `total_amount` >= 0),
  CONSTRAINT `chk_invoices_dates` CHECK (`due_date` >= `issue_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. PAYMENTS  (hanya pelunasan penuh)
--     invoice_id NULL = pembayaran melunasi beberapa tagihan sekaligus;
--     rincian resmi per tagihan ada di payment_allocations.
--     received_by_name = snapshot petugas agar nota tidak berubah.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_number` VARCHAR(40) NOT NULL,
  `invoice_id` INT UNSIGNED DEFAULT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `method` ENUM('tunai','transfer','qris','lainnya') NOT NULL,
  `reference_number` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `proof_file` VARCHAR(255) DEFAULT NULL,
  `received_by` INT UNSIGNED NOT NULL,
  `received_by_name` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('berhasil','dibatalkan') NOT NULL DEFAULT 'berhasil',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payments_number` (`payment_number`),
  KEY `idx_payments_date` (`payment_date`),
  KEY `idx_payments_method` (`method`,`payment_date`),
  KEY `idx_payments_student` (`student_id`),
  KEY `idx_payments_received_by` (`received_by`,`payment_date`),
  KEY `idx_payments_status_date` (`status`,`payment_date`),
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_payments_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. PAYMENT ALLOCATIONS
--     Satu pembayaran boleh melunasi beberapa tagihan (satu baris per
--     tagihan). UNIQUE(invoice_id) menjamin satu tagihan hanya lunas sekali.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `payment_allocations`;
CREATE TABLE `payment_allocations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` INT UNSIGNED NOT NULL,
  `invoice_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_allocations_payment_invoice` (`payment_id`,`invoice_id`),
  UNIQUE KEY `uq_payment_allocations_invoice` (`invoice_id`),
  CONSTRAINT `fk_payment_allocations_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_payment_allocations_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_payment_allocations_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. ACTIVITY LOGS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `username` VARCHAR(50) DEFAULT NULL,
  `role` VARCHAR(20) DEFAULT NULL,
  `activity` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `related_type` VARCHAR(50) DEFAULT NULL,
  `related_id` BIGINT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_logs_user` (`user_id`,`created_at`),
  KEY `idx_activity_logs_activity` (`activity`,`created_at`),
  KEY `idx_activity_logs_created` (`created_at`),
  KEY `idx_activity_logs_related` (`related_type`,`related_id`),
  CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 15. APP SETTINGS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(80) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_app_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 16. CLASS MEETINGS  (realisasi pertemuan)
--     Satu baris = satu kegiatan belajar pada satu tanggal.
--     Turunan jadwal otomatis diberi schedule_id + UNIQUE(schedule_id, tanggal)
--     agar jadwal yang sama tidak dapat direalisasikan dua kali.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `class_meetings`;
CREATE TABLE `class_meetings` (
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
-- 17. STUDENT PACKAGES  (paket belajar 8 pertemuan aktual)
--     Satu siswa hanya boleh memiliki satu paket aktif per keanggotaan.
--     Paket berikutnya langsung aktif; tagihan berjalan terpisah dan
--     utang tidak pernah menghalangi kelanjutan belajar.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `student_packages`;
CREATE TABLE `student_packages` (
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
    IF(`status` = 'aktif', CONCAT(`student_id`, ':', `group_member_id`), NULL)
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
  CONSTRAINT `fk_student_packages_invoice` FOREIGN KEY (`invoice_id`)
      REFERENCES `invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
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
-- 18. STUDENT PACKAGE MEETINGS  (riwayat pertemuan yang masuk ke paket)
--     UNIQUE (student_id, class_meeting_id) = tidak ada penghitungan ganda.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `student_package_meetings`;
CREATE TABLE `student_package_meetings` (
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
-- 19. EXPENSES  (biaya operasional bimbel)
--     Nomor unik OUT-YYYYMM-####. Data tidak boleh dihapus permanen;
--     koreksi dilakukan dengan status 'dibatalkan' + alasan audit.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- DATA AWAL (BUKAN DATA BISNIS DUMMY)
-- =====================================================================

-- Password awal:
--   admin   : Admin@2026
--   petugas : Petugas@2026
-- WAJIB diganti setelah login pertama.
INSERT INTO `users` (`name`, `username`, `email`, `password_hash`, `role`, `is_active`) VALUES
('Administrator', 'admin', NULL, '$2y$10$pZimFOjLZRWGvDuLmZnMSuOcfZt82i3ry2Ivi60/GOXrsNQlSAKvS', 'admin', 1),
('Petugas Operasional', 'petugas', NULL, '$2y$10$DD17hhzVCKTRDOQhKTfS0u.xKtpGo..vZYM1eg140rmL09HBKiha6', 'petugas', 1);

INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('app_name', 'Govinda Education'),
('app_tagline', 'Bimbingan Belajar'),
('app_description', 'Kelola siswa, guru, kelompok belajar, jadwal, tagihan, dan pembayaran dalam satu aplikasi yang terintegrasi.'),
('app_address', ''),
('app_phone', ''),
('app_email', ''),
('app_website', ''),
('app_logo', ''),
('registration_enabled', '0'),
('registration_token', ''),
('registration_rotated_at', ''),
('invoice_prefix', 'INV'),
('receipt_prefix', 'KW'),
('currency', 'Rp');
