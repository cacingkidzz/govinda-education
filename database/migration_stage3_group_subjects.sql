-- =====================================================================
-- Govinda Education — Migrasi Stage 3
-- Relasi grup–mata pelajaran–guru (group_subjects)
-- =====================================================================
-- Jalankan dari root proyek:
--   php database/migrate_stage3_group_subjects.php
--
-- File ini adalah dokumentasi SQL yang dijalankan oleh runner di atas.
-- Runner memeriksa information_schema terlebih dahulu sehingga aman
-- dijalankan berulang (idempoten) dan tidak menghapus data lama.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Tabel penghubung grup–mata pelajaran–guru.
--    Satu grup boleh memiliki banyak mata pelajaran.
--    Satu mata pelajaran hanya sekali dalam satu grup (unique).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `group_subjects` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Pindahkan relasi lama dari class_groups (bila kolomnya ada).
--    Kombinasi (group_id, subject_id) unik; data lama tidak hilang.
-- ---------------------------------------------------------------------
INSERT INTO `group_subjects`
  (`group_id`, `subject_id`, `teacher_id`, `is_active`, `created_at`, `updated_at`)
SELECT g.`id`, g.`subject_id`, g.`teacher_id`, g.`is_active`, g.`created_at`, g.`updated_at`
  FROM `class_groups` g
  JOIN `subjects` s ON s.`id` = g.`subject_id`
  JOIN `teachers` t ON t.`id` = g.`teacher_id`
 WHERE g.`subject_id` IS NOT NULL
   AND g.`teacher_id` IS NOT NULL
ON DUPLICATE KEY UPDATE
  `teacher_id` = VALUES(`teacher_id`),
  `updated_at` = VALUES(`updated_at`);

-- ---------------------------------------------------------------------
-- 3. Jadwal melekat pada relasi grup–mata pelajaran.
-- ---------------------------------------------------------------------
ALTER TABLE `group_schedules`
  ADD COLUMN `group_subject_id` INT UNSIGNED NULL AFTER `id`;

UPDATE `group_schedules` gs
  JOIN `class_groups` g ON g.`id` = gs.`group_id`
  JOIN `group_subjects` gsub
       ON gsub.`group_id` = gs.`group_id`
      AND gsub.`subject_id` = g.`subject_id`
   SET gs.`group_subject_id` = gsub.`id`
 WHERE gs.`group_subject_id` IS NULL;

-- Verifikasi: tidak boleh ada jadwal tanpa relasi.
SELECT COUNT(*) AS jadwal_tanpa_relasi
  FROM `group_schedules`
 WHERE `group_subject_id` IS NULL;

ALTER TABLE `group_schedules`
  MODIFY COLUMN `group_subject_id` INT UNSIGNED NOT NULL;

-- ---------------------------------------------------------------------
-- 4. Ganti unique slot: dari per group menjadi per group_subject.
-- ---------------------------------------------------------------------
ALTER TABLE `group_schedules`
  DROP INDEX `uq_group_schedule_slot`;

ALTER TABLE `group_schedules`
  ADD UNIQUE KEY `uq_group_schedule_slot`
      (`group_subject_id`,`day_of_week`,`start_time`);

-- ---------------------------------------------------------------------
-- 5. Hapus kolom lama setelah migrasi terverifikasi.
-- ---------------------------------------------------------------------
ALTER TABLE `group_schedules`
  DROP FOREIGN KEY `fk_group_schedules_group`;

ALTER TABLE `group_schedules`
  DROP COLUMN `group_id`;

ALTER TABLE `group_schedules`
  ADD KEY `idx_group_schedules_group_subject_active`
      (`group_subject_id`,`is_active`);

-- ---------------------------------------------------------------------
-- 6. Kolom legacy class_groups dibuat nullable.
--    Tidak dipakai aplikasi lagi; boleh dihapus pada migrasi terpisah
--    setelah seluruh modul Stage 4 selesai diverifikasi.
-- ---------------------------------------------------------------------
ALTER TABLE `class_groups`
  MODIFY COLUMN `subject_id` INT UNSIGNED NULL;

ALTER TABLE `class_groups`
  MODIFY COLUMN `teacher_id` INT UNSIGNED NULL;
