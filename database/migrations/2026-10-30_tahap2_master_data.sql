-- =====================================================================
-- Govinda Education — Migrasi Tahap 2 (Master Data)
-- Tanggal : 2026-10-30
-- Tujuan  :
--   1. teachers  : menambah birth_place & birth_date (form guru).
--   2. subjects  : menambah code unik (kode mata pelajaran otomatis).
-- Tidak menghapus tabel/kolom dan tidak menghapus data yang ada.
--
-- Jalankan SEKALI pada database yang sudah berisi data Tahap 1:
--   C:\xampp\mysql\bin\mysql.exe -u root govinda_education < file_ini
-- Untuk instalasi baru, cukup import database/govinda_education.sql
-- (file tersebut sudah memuat struktur terbaru).
-- =====================================================================

USE `govinda_education`;

-- ---------------------------------------------------------------------
-- 1. TEACHERS — tempat & tanggal lahir
-- ---------------------------------------------------------------------
ALTER TABLE `teachers`
  ADD COLUMN `birth_place` VARCHAR(100) DEFAULT NULL AFTER `gender`,
  ADD COLUMN `birth_date` DATE DEFAULT NULL AFTER `birth_place`;

-- ---------------------------------------------------------------------
-- 2. SUBJECTS — kode unik
--    Ditambahkan sebagai NULL dahulu, diisi untuk baris lama, lalu
--    dijadikan NOT NULL + UNIQUE agar data lama tidak hilang.
-- ---------------------------------------------------------------------
ALTER TABLE `subjects`
  ADD COLUMN `code` VARCHAR(30) DEFAULT NULL AFTER `id`;

UPDATE `subjects`
SET `code` = CONCAT('MPL-', LPAD(`id`, 3, '0'))
WHERE `code` IS NULL OR `code` = '';

ALTER TABLE `subjects`
  MODIFY COLUMN `code` VARCHAR(30) NOT NULL,
  ADD UNIQUE KEY `uq_subjects_code` (`code`);
