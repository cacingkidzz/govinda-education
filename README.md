# Govinda Education

Aplikasi manajemen bimbingan belajar berbasis **PHP Native + MySQL/MariaDB**, menggunakan **AdminLTE 4**, Bootstrap 5, PDO, jQuery, Chart.js, dan aset lokal (tanpa ketergantungan CDN).

Status pengembangan: **Tahap 1 (Fondasi), Tahap 2 (Master Data), Tahap 3 (Akademik), dan Tahap 4 (Keuangan & Operasional) selesai**, ditambah **modul Profil Bimbel**, **Pendaftaran Mandiri Siswa**, dan **Pengeluaran Operasional**. Tahap berikutnya dikerjakan setelah instruksi lanjutan.

---

## 1. Fitur Tahap 1

- Struktur proyek modular (config, includes, classes, admin, proses, assets, uploads, database).
- Database lengkap (master data, akademik, keuangan) dengan foreign key, index, generated column, dan constraint.
- Koneksi PDO prepared statements + konfigurasi terpusat.
- Login/logout aman: password hashing, session regeneration, CSRF, rate limit percobaan login.
- Role **Admin** dan **Petugas** dengan pemeriksaan akses di server.
- Layout AdminLTE 4 responsif: sidebar, navbar, breadcrumb, footer.
- Dashboard dengan data **nyata dari database**:
  - siswa aktif, guru aktif, group aktif;
  - paket aktif dan paket selesai;
  - pertemuan terlaksana bulan berjalan;
  - uang diterima, **total utang**, jumlah tagihan belum dibayar, dan jumlah siswa berutang;
  - daftar siswa berutang beserta tautan pelunasan;
  - grafik pembayaran 12 bulan;
  - grafik status tagihan/paket;
  - pembayaran terbaru, tagihan belum lunas, dan paket mendekati selesai.
- Log aktivitas (login, logout, login gagal) + halaman audit khusus admin.
- Proteksi folder internal dan eksekusi skrip di folder upload.
- Format tanggal Indonesia dan Rupiah.

> Dashboard akan menampilkan angka 0 dan empty state sampai data master serta transaksi diisi pada tahap berikutnya. Tidak ada data dummy.

---

## 1a. Fitur Tahap 2 — Master Data

**Data Siswa** (`admin/siswa/`)
- Daftar siswa dengan DataTables (pencarian, pagination, urutan jumlah data), filter status, kelas sekolah, dan group aktif.
- Kolom: No., Foto, Kode, Nama, JK, Kelas, Orang Tua, No. HP Orang Tua, Group Aktif, Status, Aksi.
- Tambah/edit siswa dengan validasi server: nama wajib, tanggal valid, nomor HP dinormalisasi (`08xxxxxxxxxx`), email/telepon orang tua opsional.
- Kode siswa otomatis `SIS-YYYY-NNNN`, unik, dengan retry saat bentrok, dan tidak diubah saat edit.
- Upload foto opsional (JPG/JPEG/PNG/WEBP, maks. 2 MB) dengan pratinjau dan foto default.
- Detail siswa: identitas, orang tua, sekolah, group aktif, riwayat group, ringkasan keuangan (total tagihan, total dibayar, total utang, jumlah tagihan belum dibayar), riwayat tagihan per paket, dan riwayat pembayaran multi-tagihan.
- Nonaktifkan/aktifkan siswa tanpa menghapus data; semua perubahan status dicatat di log aktivitas.

**Data Guru** (`admin/guru/`)
- CRUD penuh untuk admin; petugas hanya dapat melihat (server menolak 403 pada halaman/endpoint tulis).
- Kode guru otomatis `GRU-YYYY-NNNN`. Validasi email dan nomor HP.
- Multi-select mata pelajaran yang diampu (Select2, tabel `teacher_subjects`), ikut tampil di daftar dan detail guru.
- Detail guru: profil, kontak, mapel, group diajar (melalui relasi `group_subjects`), dan jadwal mengajar.
- Foto guru dengan aturan upload yang sama seperti siswa.

**Mata Pelajaran** (`admin/mata-pelajaran/`)
- CRUD penuh untuk admin; petugas hanya dapat melihat.
- Kode otomatis `MPL-NNN`, nama wajib dan unik, keterangan opsional, status aktif/nonaktif.
- Detail menampilkan guru pengampu dan group yang memakai mapel (empty state bila belum ada).
- Proteksi: mapel yang sudah dipakai group tidak dihapus permanen, hanya dinonaktifkan.

**Keamanan upload foto**
- Validasi MIME di server (`finfo` + `getimagesize`), bukan hanya ekstensi.
- Nama file acak, folder `uploads/siswa` dan `uploads/guru` diblokir dari eksekusi skrip.
- Foto lama baru dihapus setelah data baru tersimpan di database.

---

## 1b. Fitur Tahap 3 — Akademik

**Group Belajar** (`admin/group/`)
- Daftar group dengan DataTables + filter jenis, guru, mata pelajaran, dan status. Kolom mata pelajaran/guru menampilkan gabungan seluruh relasi aktif.
- Tambah/edit group (admin): kode otomatis `GRP-YYYY-NNNN` (unik, retry saat bentrok), jenis `private`/`group`, kapasitas, keterangan, dan status. Mata pelajaran dan guru **tidak lagi melekat pada group**.
- Aturan kapasitas dijaga di server dan database: private tepat 1 siswa (input kapasitas dinormalisasi menjadi 1), group 2–7 siswa. Kapasitas tidak boleh lebih kecil dari jumlah anggota aktif.
- Group yang memiliki anggota aktif >1 tidak dapat diubah menjadi private.
- Group tidak dihapus permanen; gunakan aktif/nonaktif. Group nonaktif tidak dapat menerima anggota, relasi mapel baru, jadwal baru, atau menjadi tujuan perpindahan.
- Detail group bertab: **Informasi, Anggota, Mata Pelajaran & Guru, Jadwal, Pertemuan, Riwayat Perubahan**. Tab mapel menampilkan jumlah pertemuan terlaksana per mata pelajaran; tab pertemuan memuat jurnal kegiatan belajar.
- Ringkasan dashboard: group private aktif, group reguler aktif, siswa sudah tergabung, dan siswa belum tergabung.

**Mata Pelajaran dan Guru** (`admin/group/mapel*.php`, tabel `group_subjects`)
- Satu group boleh memiliki **banyak mata pelajaran**; setiap mata pelajaran diampu oleh **tepat satu guru**, dan guru boleh berbeda antarmapel.
- Mata pelajaran yang sama tidak boleh didaftarkan dua kali dalam satu group (unique `(group_id, subject_id)`).
- Hanya group, mata pelajaran, dan guru aktif yang dapat dihubungkan. Relasi dapat dinonaktifkan tanpa menghapus riwayat.
- Perubahan guru pada relasi yang sudah memiliki jadwal aktif diperiksa ulang terhadap bentrok guru; pengaktifan kembali relasi memeriksa ulang seluruh jadwalnya.
- Relasi yang sudah memiliki riwayat jadwal tidak dapat dihapus permanen; gunakan nonaktif. Hapus permanen hanya untuk relasi tanpa jadwal.

**Jadwal Belajar** (`admin/jadwal/`)
- Jadwal melekat pada `group_subjects` (bukan langsung pada group), sehingga satu mata pelajaran dapat memiliki banyak jadwal dalam seminggu.
- CRUD jadwal (admin), validasi hari 1–7, jam selesai harus lebih besar dari jam mulai, dan normalisasi `HH:MM` → `HH:MM:SS`.
- Pencegahan bentrok aktif untuk **guru**, **group**, dan **ruangan** pada hari yang sama dengan jam bertumpukan (nama ruangan dibandingkan case-insensitive). Jadwal yang sedang diedit dikecualikan; jadwal/relasi/group nonaktif tidak dihitung.
- Pesan bentrok menyebut nama guru/group/mapel/ruangan, hari, dan jam.
- Slot `(group_subject_id, hari, jam mulai)` unik termasuk untuk jadwal nonaktif.
- **Jadwal bersifat fleksibel**: tidak ada batas pertemuan per bulan/minggu. Jadwal boleh diubah, ditambah, atau dipindah kapan saja mengikuti situasi group. Target **8 pertemuan** adalah target paket belajar siswa, bukan batas jadwal.
- Validasi jadwal berjalan di server di dalam transaksi dengan `SELECT ... FOR UPDATE` pada baris group agar pemeriksaan bentrok tetap serial.
- Tampilan tabel dengan filter group/mapel/guru/hari/status serta **tampilan mingguan** (grid Senin–Minggu, responsive scroll).
- Jadwal aktif tidak dapat dihapus; nonaktifkan terlebih dahulu. Hapus permanen hanya tersedia untuk jadwal nonaktif.

**Jurnal Pertemuan Group** (`admin/group/pertemuan-proses.php`, tab Pertemuan)
- Setiap pertemuan per group dicatat sebagai satu baris `class_meetings` pada relasi mata pelajaran–guru, lengkap dengan tanggal, jam, status, dan **catatan/jurnal yang wajib diisi**.
- Catatan diisi admin/petugas atas nama guru pengampu (aplikasi belum memiliki login guru); nama pencatat ditampilkan pada jurnal.
- Status yang dapat dicatat dari tab Pertemuan: `terlaksana`, `terjadwal`, dan `dibatalkan`. Pemindahan jadwal (`dijadwalkan_ulang`) tetap dikelola dari menu Realisasi Pertemuan.
- Hanya status `terlaksana` yang dihitung ke paket siswa. Catatan tidak mengubah aturan paket 8 pertemuan maupun status tagihan.
- Jurnal menampilkan seluruh riwayat pertemuan group (tanpa filter bulan) beserta jumlah siswa yang terhitung dan tautan kelola pertemuan.

**Keanggotaan & Perpindahan** (`admin/group/anggota*.php`, `pindah.php`)
- Halaman **Anggota Group**: daftar keanggotaan aktif, filter group, pencarian, dan metrik siswa tergabung/belum tergabung.
- Tambah anggota: hanya siswa aktif yang belum memiliki group aktif; validasi kapasitas dan status group diulang di dalam transaksi (`SELECT ... FOR UPDATE`).
- Satu siswa hanya memiliki satu keanggotaan aktif (dijaga `UNIQUE(active_student_id)`).
- Akhiri keanggotaan: mengisi `end_date` + catatan, record tetap tersimpan sebagai riwayat, tidak menghapus siswa/group.
- Pindah group: group asal terisi otomatis, validasi group tujuan aktif + kapasitas + minimal satu relasi mapel-guru aktif, lalu menutup keanggotaan lama, membuat keanggotaan baru, dan mencatat `student_group_history` dalam **satu transaksi** (rollback bila gagal).
- Invoice/tagihan lama tidak disentuh; tarif group baru berlaku pada paket berikutnya. Utang dan tagihan lama tetap tersimpan serta tidak menghalangi perpindahan.
- Halaman **Riwayat Perpindahan**: filter tanggal, group asal, group tujuan, dan pencarian siswa; DataTables; riwayat tidak dapat dihapus.

**Hak akses**
- Admin: seluruh operasi group, relasi mapel-guru, jadwal, keanggotaan, dan perpindahan.
- Petugas: dapat menambah anggota, mengakhiri keanggotaan, dan memindahkan siswa; tidak dapat membuat/mengubah group, relasi mapel, atau jadwal (server menolak 403).

**Migrasi dari Tahap 3 versi satu-mapel**
- Jalankan `php database/migrate_stage3_group_subjects.php` (idempoten) atau impor `database/migration_stage3_group_subjects.sql`.
- Skrip memindahkan `class_groups.subject_id/teacher_id` lama menjadi baris `group_subjects`, mengubah `group_schedules.group_id` menjadi `group_schedule_subject_id`, dan mempertahankan seluruh jadwal lama.
- Kolom lama `class_groups.subject_id`/`teacher_id` dibiarkan sebagai kolom legacy nullable dan tidak lagi dipakai aplikasi.


---

## 1c. Fitur Tahap 4 — Keuangan & Operasional

**Realisasi Pertemuan** (`admin/realisasi-pertemuan/`)
- Pencatatan pertemuan nyata per relasi mata pelajaran–guru, tanggal, dan jam; status `terjadwal`, `terlaksana`, `dibatalkan`, atau `dijadwalkan_ulang`.
- Hanya status **terlaksana** yang dihitung ke paket siswa. Kehadiran siswa tidak memengaruhi hitungan selama kelas berjalan.
- Pemindahan jadwal mencatat pertemuan asal sebagai `dijadwalkan_ulang` dan membuat pertemuan pengganti.
- Pencegahan realisasi ganda lewat unique `(schedule_id, meeting_date)` dan `(group_subject_id, meeting_date, start_time)` serta riwayat `student_package_meetings`.
- Riwayat perubahan status dicatat di log aktivitas; tidak ada penghapusan permanen.

**Paket Belajar 8 Pertemuan** (`admin/paket/`, `classes/StudentPackage.php`)
- Paket dihitung **per siswa**, menjumlahkan pertemuan dari **seluruh mata pelajaran** dalam group; tidak ada batas per mata pelajaran.
- Status paket hanya `aktif`, `selesai`, dan `dibatalkan`. Satu paket aktif per keanggotaan dijaga generated column + unique index; nomor paket berurutan.
- Paket pertama dibuat manual oleh admin dan langsung memperoleh invoice bila tarif tersedia.
- Pembuatan **massal per group** tersedia di **Paket Belajar → Per Group**: pilih kelompok, tanggal mulai, diskon per siswa, lalu centang anggota. Paket dan tagihan tetap dimiliki **per siswa**; siswa yang sudah punya paket aktif dilewati dan proses dibatalkan seluruhnya bila salah satu gagal.
- Pertemuan ke-8 menutup paket (`selesai`) dan **langsung membuat paket berikutnya berstatus `aktif`** + invoice bila tarif tersedia. Pembelajaran tidak pernah diblokir oleh tagihan lama yang belum lunas.
- Pertemuan berikutnya langsung dihitung ke paket baru yang aktif, meskipun tagihan paket sebelumnya masih menunggak.
- Bila tarif belum tersedia, paket berikutnya tetap dibuat aktif tanpa invoice; tagihan menyusul saat tarif diisi.
- Pembayaran **tidak** mengubah status paket, `start_date`, atau hitungan pertemuan.
- Pembatalan pertemuan ke-8 mengembalikan paket ke `aktif`; paket lanjutan hanya dibatalkan bila belum memiliki pembayaran berhasil dan belum memiliki pertemuan.
- Hitung ulang/pembatalan paket hanya oleh admin dan wajib beralasan (audit). Riwayat paket tidak dihapus.

**Tarif Paket** (`admin/tarif/`)
- Nominal ditentukan admin per **jenis group + jumlah siswa aktif + paket 8 pertemuan** (`pricing_rules.package_size`).
- Tarif mengikuti tanggal efektif; perubahan tidak mengubah invoice yang sudah terbit.
- Modul tulis khusus admin; petugas hanya dapat melihat.

**Tagihan** (`admin/tagihan/`)
- Daftar/filter/detail tagihan paket, cetak invoice **A4** dengan identitas organisasi dan status.
- Invoice adalah snapshot lengkap (siswa, group, jenis, mapel/guru, jumlah anggota, nomor paket, target pertemuan, tarif, diskon, total).
- Status hanya `belum_bayar`, `lunas`, `dibatalkan`; invoice batal tetap tersimpan sebagai riwayat dan dapat diterbitkan ulang.
- Tidak ada penagihan bulanan otomatis untuk transaksi baru.

**Pembayaran & Nota** (`admin/pembayaran/`)
- Pembayaran **wajib lunas dan sama persis** dengan total seluruh tagihan yang dipilih; kurang/lebih ditolak dan tidak ada cicilan.
- Satu pembayaran boleh melunasi **beberapa tagihan** milik siswa yang sama. Rincian per tagihan disimpan di `payment_allocations`; satu invoice hanya punya satu alokasi berhasil (`UNIQUE(invoice_id)`) dan diproses dengan `SELECT ... FOR UPDATE` dalam satu transaksi.
- `payments.invoice_id` nullable untuk pembayaran multi-tagihan; jumlah alokasi wajib sama dengan nominal pembayaran.
- Metode tunai/transfer/QRIS/lainnya, nomor referensi opsional, dan petugas penerima dicatat sebagai snapshot.
- Nota/kwitansi **A5** hanya tersedia setelah pembayaran berhasil, bersifat permanen, dan merinci **setiap** invoice yang dilunasi beserta total pembayaran.
- Pembayaran tidak mengaktifkan paket atau mengubah hitungan pertemuan. Utang dihitung `Total Tagihan - Total Pembayaran yang Dialokasikan`.

**Laporan** (`admin/laporan/`)
- Laporan pembayaran, tagihan, paket, realisasi pertemuan, **utang**, dan rekap keuangan.
- Laporan utang menampilkan total tagihan, total dibayar, total utang, jumlah tagihan belum dibayar, jumlah siswa berutang, rekap per siswa, dan rincian tagihan per paket.
- Rekap memisahkan **uang diterbitkan** dan **uang diterima**.
- Filter tanggal/group/siswa/status, cetak, dan ekspor CSV (UTF-8 BOM, pemisah `;`) tanpa dependensi baru.

**Dashboard**
- Seluruh angka berasal dari database: paket aktif/selesai, pertemuan bulan ini, uang diterima, **total utang**, jumlah tagihan belum dibayar, jumlah siswa berutang, daftar siswa berutang, grafik pembayaran, dan daftar paket mendekati selesai. Tidak ada data dummy.

---

## 1d. Profil Bimbel

Modul **Profil Bimbel** (`admin/profil/`, khusus admin) mengelola identitas organisasi tanpa mengubah kode:

- **Data yang dikelola:** nama bimbel, tagline, deskripsi singkat, alamat, telepon, email, situs web, dan logo.
- **Penyimpanan:** tabel `app_settings` yang sudah ada (key/value), dengan kunci `app_name`, `app_tagline`, `app_description`, `app_address`, `app_phone`, `app_email`, `app_website`, dan `app_logo`. Tidak ada tabel baru.
- **Logo:** unggah gambar (JPG/PNG/WebP, maksimal 2 MB) ke `uploads/profil/` dengan nama acak. Logo bawaan `assets/img/logo-mark.svg` dipakai bila belum ada logo kustom. Logo dapat direset kembali ke bawaan.
- **Validasi server:** nama wajib diisi; email harus valid; telepon hanya angka/`+`/spasi/`-`; situs web dinormalisasi ke `http`/`https` dan skema berbahaya ditolak; path logo dicek agar tidak keluar dari folder upload.
- **Konsistensi tampilan:** identitas dan logo otomatis tampil di sidebar, navbar, halaman login, dashboard, invoice A4, nota A5, dan halaman profil.
- **Latar logo adaptif:** logo kustom yang didominasi warna terang memperoleh latar gelap pada permukaan terang (dan sebaliknya) agar tetap terbaca; logo bawaan tidak memerlukan latar tambahan.
- **Audit:** perubahan profil, unggah logo, dan reset logo dicatat di log aktivitas (`profil_edit`, `profil_logo`, `profil_logo_reset`).

---

## 1e. Pendaftaran Mandiri Siswa

Halaman publik **`pendaftaran.php`** memungkinkan siswa mengisi data sendiri tanpa admin mengetik ulang:

- **Dua jenis tautan:**
  - **Tautan pribadi siswa** (`pendaftaran.php?token=...`) untuk melengkapi/memperbarui data siswa yang sudah terdaftar. Token acak 48 karakter, unik per siswa, dan dapat diganti kapan saja.
  - **Tautan pendaftaran umum** (`pendaftaran.php?daftar=...`) untuk siswa baru. Hanya satu tautan aktif, disimpan di `app_settings` (`registration_enabled`, `registration_token`, `registration_rotated_at`), dan dapat dibuka/ditutup/diganti oleh admin.
- **Data langsung tersimpan** setelah siswa mengirim formulir. Tidak ada antrean verifikasi; admin hanya memeriksa hasilnya di daftar/detail siswa.
- **Kolom yang dapat diubah siswa:** nama, jenis kelamin, tempat/tanggal lahir, alamat, HP siswa, nama/HP orang tua, sekolah, kelas, dan foto. **Kode siswa, group, status aktif, dan tanggal bergabung dikunci server.**
- **Kelengkapan data** dihitung dari 10 field wajib dan ditampilkan sebagai badge di daftar siswa, detail siswa, dan halaman pendaftaran admin. Foto bersifat opsional.
- **Keamanan:** token tidak dapat ditebak, CSRF wajib, honeypot anti-bot, pembatasan percobaan per sesi, upload JPG/PNG/WebP maksimal 2 MB dengan nama acak, dan seluruh output di-escape.
- **Audit:** pembuatan/penggantian tautan, perubahan pengaturan pendaftaran, dan pembaruan data oleh siswa dicatat di log aktivitas (`pendaftaran_link`, `pendaftaran_pengaturan`, `pendaftaran_mandiri`).
- **Pengelolaan admin:** menu **Master Data → Pendaftaran Mandiri** (`admin/siswa/pendaftaran.php`) untuk menyalin tautan, membuat tautan semua siswa, serta membuka/menutup pendaftaran baru. Kartu tautan juga tersedia di detail siswa.

---

## 1f. Pengeluaran Operasional

Modul **Pengeluaran** (`admin/pengeluaran/`) mencatat biaya operasional bimbel agar kas dan laporan keuangan lengkap:

- **Kategori:** honor guru, sewa tempat, listrik & internet, ATK & perlengkapan, transport, konsumsi, perawatan & kebersihan, dan lainnya.
- **Data yang dicatat:** tanggal, kategori, nominal, metode pembayaran (tunai/transfer/QRIS/lainnya), penerima/vendor, nomor referensi, dan catatan.
- **Nomor otomatis** `OUT-YYYYMM-####` yang unik dan berurutan per bulan.
- **Snapshot petugas:** nama admin/petugas yang mencatat disimpan pada `recorded_by_name`.
- **Tidak ada penghapusan permanen:** data yang salah dibatalkan oleh **admin** dengan alasan wajib; status berubah menjadi `dibatalkan` dan tetap tersimpan sebagai riwayat.
- **Hanya status `tercatat`** yang dihitung ke kas, ringkasan, dan laporan.
- **Hak akses:** admin dan petugas dapat mencatat/mengubah pengeluaran; pembatalan hanya admin (petugas mendapat 403).
- **Integrasi laporan:** **Rekap Keuangan** menampilkan kartu Pengeluaran Operasional dan Saldo Kas, rincian nilai, kolom pengeluaran & saldo kas pada rekap bulanan, dataset grafik, serta baris pada ekspor CSV.
- **Keamanan:** seluruh endpoint tulis hanya menerima POST + CSRF, output di-escape, dan query memakai PDO prepared statements.

---

## 2. Kebutuhan Server

- PHP **8.1+** (diuji pada PHP 8.2.4).
- MySQL 5.7+ / MariaDB 10.4+ (diuji pada MariaDB 10.4.28).
- Ekstensi PHP: `pdo`, `pdo_mysql`, `mbstring`, `fileinfo`, `gd` (untuk upload foto), `zip` (opsional ekspor).
- Apache dengan `mod_rewrite` dan `mod_headers` (XAMPP/cPanel).
- Browser modern.

---

## 3. Instalasi di XAMPP (Windows)

1. **Salin proyek** ke `C:\xampp\htdocs\govinda-education`.
2. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
3. **Impor database**:
   - Buka `http://localhost/phpmyadmin`.
   - Pilih tab **Import**, pilih file `database/govinda_education.sql`, lalu **Go**.
   - File SQL membuat database `govinda_education` beserta seluruh tabel dan akun awal.
   - Alternatif CLI:
     ```powershell
     C:\xampp\mysql\bin\mysql.exe -u root < database\govinda_education.sql
     ```
   - **Sudah punya database Tahap 1?** Jangan impor ulang (data akan tertimpa). Jalankan migrasi:
     ```powershell
     C:\xampp\mysql\bin\mysql.exe -u root < database\migrations\2026-10-30_tahap2_master_data.sql
     ```
   - **Sudah punya database Tahap 3 versi satu-mapel?** Jalankan migrasi relasi banyak mapel (idempoten, aman diulang):
     ```powershell
     php database\migrate_stage3_group_subjects.php
     ```
     Salinan SQL untuk audit/backup tersedia di `database/migration_stage3_group_subjects.sql`.
   - **Sudah punya database Tahap 3?** Jalankan migrasi Tahap 4 (idempoten, membuat backup otomatis di `storage/backups/`):
     ```powershell
     php database\migrate_stage4_packages.php
     ```
     Migrasi menambahkan tabel realisasi/paket, kolom invoice paket, kolom pembayaran/nota, generated unique key, dan index. Dokumentasi SQL ada di `database/migration_stage4_packages.sql`.
   - **Ingin mengaktifkan pendaftaran mandiri siswa?** Jalankan migrasi token (idempoten, membuat backup otomatis):
     ```powershell
     php database\migrate_self_registration.php
     ```
     Migrasi menambahkan kolom token/pembaruan mandiri pada `students` dan setting `registration_*` pada `app_settings`. Pendaftaran umum tetap **nonaktif** sampai admin membuat dan membukanya di menu **Pendaftaran Mandiri**.
    - **Ingin mencatat pengeluaran operasional?** Jalankan migrasi modul pengeluaran (idempoten, membuat backup otomatis):
      ```powershell
      php database\migrate_expenses.php
      ```
      Migrasi membuat tabel `expenses` untuk biaya operasional bimbel. Tidak mengubah data keuangan yang sudah ada.
4. **Sesuaikan koneksi** bila perlu di `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'govinda_education');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
5. Buka `http://localhost/govinda-education/`.
6. Login dengan akun awal:

   | Role | Username | Password |
   |------|----------|----------|
   | Admin | `admin` | `Admin@2026` |
   | Petugas | `petugas` | `Petugas@2026` |

7. **Segera ganti password default** sebelum aplikasi dipakai. Modul manajemen pengguna akan tersedia pada tahap pengembangan berikutnya; sampai saat itu, ganti hash password langsung di database menggunakan `password_hash()`.

---

## 4. Instalasi di cPanel

Panduan lengkap (persyaratan, upload, database, permission, SSL, verifikasi,
update, dan troubleshooting) tersedia di **[DEPLOY-CPANEL.md](DEPLOY-CPANEL.md)**.

Ringkasan:

1. Upload isi proyek ke `public_html` atau subfolder (mis. `public_html/govinda-education`).
2. Buat database dan user MySQL melalui **MySQL® Databases**, beri hak akses penuh.
3. Impor **`database/govinda_education_cpanel.sql`** melalui phpMyAdmin
   (file ini tidak membuat/memilih database, sehingga cocok untuk nama berprefix `cpuser_`).
   Jika skema berubah, buat ulang dengan `php database\build_cpanel_sql.php`.
4. Salin `config/config.local.example.php` menjadi **`config/config.local.php`**, lalu isi
   `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS` milik hosting. Kredensial produksi
   **tidak** disimpan di `config/database.php`; tanpa file ini aplikasi berhenti dengan
   pesan "Kredensial database hosting belum diisi".
5. Pastikan `storage/logs` dan `uploads/{siswa,guru,pembayaran,profil}` dapat ditulis
   (`755`, atau `775` bila perlu — jangan `777`).
6. Pilih PHP **8.1+** (disarankan 8.2) dan aktifkan ekstensi
   `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`.
7. Aktifkan **HTTPS/SSL**, lalu buka komentar blok redirect HTTPS di `.htaccess`.
8. Buka domain/subfolder, login, dan **segera ganti password default**.
9. Hapus folder `tests/` dan `database/` dari server setelah import (keduanya sudah
   diblokir `.htaccess`, tetapi menghapusnya lebih aman).

**Catatan cPanel:** jangan mengimpor `govinda_education.sql` (file utama) lewat
phpMyAdmin karena berisi `CREATE DATABASE`/`USE` yang akan ditolak user cPanel.
Gunakan file `govinda_education_cpanel.sql`.

---

## 5. Struktur Proyek

```
govinda-education/
├── admin/
│   ├── dashboard.php
│   ├── log-aktivitas/index.php
│   ├── siswa/ guru/ mata-pelajaran/   # modul Tahap 2
│   ├── siswa/pendaftaran.php          # pengelolaan tautan pendaftaran mandiri
│   ├── group/ jadwal/                 # modul Tahap 3 (akademik)
│   ├── realisasi-pertemuan/ paket/    # modul Tahap 4 (operasional)
│   ├── tarif/ tagihan/ pembayaran/    # modul Tahap 4 (keuangan)
│   ├── pengeluaran/                   # biaya operasional bimbel
│   ├── laporan/                       # laporan + utang + cetak + CSV
│   ├── profil/                        # identitas & logo bimbel (admin)
│   └── pengguna/                      # tahap berikutnya
├── assets/
│   ├── adminlte/          # CSS & JS AdminLTE 4
│   ├── vendor/            # Bootstrap, Icons, jQuery, Chart.js, dll.
│   ├── css/app.css        # tema Govinda Education
│   ├── css/print.css      # tata letak invoice A4 & nota A5
│   ├── css/login.css
│   ├── css/pendaftaran.css # halaman pendaftaran mandiri siswa
│   ├── js/app.js
│   └── img/               # logo bawaan, favicon, avatar default
├── classes/               # Database, User, Student, Teacher, Subject, Upload,
│                          # Group, GroupSubject, Schedule,
│                          # Membership, StudentPackage, Meeting, Pricing,
│                          # BillingPeriod, Invoice, Payment, Expense, Report,
│                          # ActivityLog, Profile, StudentSelfService
├── config/
│   ├── app.php            # konstanta, session, security, error handler
│   ├── database.php       # koneksi PDO + default lokal
│   └── config.local.example.php  # contoh kredensial hosting (salin ke config.local.php)
├── database/
│   ├── govinda_education.sql
│   ├── govinda_education_cpanel.sql        # versi siap impor phpMyAdmin cPanel
│   ├── build_cpanel_sql.php                # pembuat SQL cPanel dari SQL utama
│   ├── migrate_stage3_group_subjects.php   # runner migrasi idempoten
│   ├── migration_stage3_group_subjects.sql # dokumentasi SQL migrasi
│   ├── migrate_stage4_packages.php         # runner migrasi Tahap 4
│   ├── migration_stage4_packages.sql       # dokumentasi SQL Tahap 4
│   ├── migrate_self_registration.php       # runner migrasi pendaftaran mandiri
│   ├── migrate_expenses.php                # runner migrasi pengeluaran
│   └── migrations/        # migrasi SQL per tahap
├── tests/
│   ├── .htaccess              # menolak akses web ke folder pengujian
│   ├── stage3_test.php        # uji aturan bisnis Tahap 3 (CLI)
│   ├── stage3_http_test.php   # uji role, CSRF, dan alur form Tahap 3
│   ├── stage4_test.php        # uji paket, invoice, pembayaran, nota
│   ├── stage4_http_test.php   # uji halaman, cetak, CSV, role, CSRF
│   ├── profile_http_test.php  # uji validasi, akses, dan integrasi profil
│   ├── self_registration_test.php      # uji token, validasi, kelengkapan data
│   ├── self_registration_http_test.php # uji halaman publik & pengelolaan tautan
│   ├── expense_test.php        # uji aturan bisnis pengeluaran
│   └── expense_http_test.php   # uji halaman, hak akses, dan integrasi laporan
├── includes/
│   ├── bootstrap.php      # autoloader + helper + auth
│   ├── auth.php           # Auth class + require_login/role
│   ├── csrf.php
│   ├── functions.php
│   ├── header.php navbar.php sidebar.php footer.php
├── proses/                # endpoint POST (tahap berikutnya)
├── uploads/
│   ├── siswa/ guru/ pembayaran/ profil/
│   └── .htaccess          # blokir eksekusi skrip
├── storage/logs/          # log error aplikasi (tidak dapat diakses web)
├── .htaccess
├── AGENTS.md
├── index.php
├── login.php
├── pendaftaran.php        # halaman publik pendaftaran & pembaruan data siswa
├── logout.php
└── README.md
```

Folder modul yang belum diimplementasikan sengaja dibiarkan kosong. Menu sidebar hanya menampilkan modul yang benar-benar sudah berfungsi — tidak ada menu pajangan.

---

## 6. Aturan Bisnis Inti (Ringkasan)

- Status invoice **hanya** `belum_bayar`, `lunas`, `dibatalkan`. Tidak ada cicilan.
- Status paket **hanya** `aktif`, `selesai`, `dibatalkan`.
- Pembayaran **wajib sama persis** dengan total seluruh tagihan yang dipilih; kurang/lebih ditolak.
- Satu pembayaran boleh melunasi beberapa tagihan; rincian disimpan di `payment_allocations` dan satu invoice hanya punya satu alokasi berhasil (`UNIQUE(invoice_id)`).
- `payments.invoice_id` nullable untuk pembayaran multi-tagihan; total alokasi harus sama dengan nominal pembayaran.
- Pembayaran **tidak** mengaktifkan paket, tidak mengubah `start_date`, dan tidak mengubah hitungan pertemuan.
- Paket berikutnya dibuat dan **langsung aktif** saat pertemuan ke-8, tanpa menunggu pelunasan tagihan lama.
- Utang = total tagihan non-batal - total pembayaran teralokasi; utang tidak menghalangi pembelajaran.
- Satu siswa hanya boleh memiliki satu keanggotaan group aktif.
- Satu siswa hanya boleh memiliki satu paket aktif per keanggotaan; paket = 8 pertemuan nyata dari seluruh mata pelajaran.
- Tagihan berbasis paket, bukan bulanan. Invoice adalah snapshot; perubahan group/tarif/guru tidak mengubah invoice lama.
- Tarif per siswa per paket 8 pertemuan, berdasarkan jenis group + jumlah siswa, ditentukan admin.
- Group private = 1 siswa; group belajar = 2–7 siswa.
- Hanya pertemuan `terlaksana` yang dihitung; kehadiran tidak memengaruhi hitungan.
- Data transaksi tidak dihapus permanen; gunakan status aktif/nonaktif, pembatalan, atau audit.
- Identitas bimbel (nama, tagline, alamat, kontak, situs, logo) dikelola lewat **Profil Bimbel** dan otomatis dipakai di seluruh halaman serta dokumen cetak.
- Siswa dapat memperbarui data pribadinya lewat tautan token pribadi; kode siswa, group, status aktif, dan tanggal bergabung tetap dikunci server. Pendaftaran siswa baru memakai tautan umum yang dapat ditutup admin.
- Pengeluaran operasional dicatat per transaksi dengan nomor `OUT-YYYYMM-####`; data tidak dihapus permanen dan pembatalan hanya oleh admin dengan alasan. Hanya status `tercatat` yang mengurangi kas dan masuk laporan.

Detail lengkap ada di `AGENTS.md`.

---

## 7. Keamanan yang Diterapkan

- PDO prepared statements (emulasi dimatikan).
- `password_hash()` / `password_verify()`.
- CSRF token pada form POST.
- Session `HttpOnly`, `SameSite=Lax`, `Secure` saat HTTPS; regenerasi ID saat login.
- Idle timeout 3 jam dan rotasi session berkala.
- Pemeriksaan role dan status aktif pengguna dari database setiap request.
- Escape output dengan `e()` (`htmlspecialchars`).
- Error ditampilkan generik; detail masuk `storage/logs`.
- Folder config/includes/classes/database/storage diblokir dari web.
- Tautan pendaftaran memakai token acak 48 karakter; token pribadi dan token umum terpisah, dapat diganti, dan pendaftaran umum dapat ditutup admin.
- Halaman pendaftaran memakai CSRF, honeypot, pembatasan percobaan per sesi, validasi ulang di server, serta upload gambar dengan whitelist MIME/ekstensi dan nama acak.
- Folder upload tidak dapat mengeksekusi PHP.
- `.htaccess` menolak file `.sql`, `.log`, `.md`, `.env`, dsb.
- Upload foto/logo: whitelist MIME via `finfo` + `getimagesize`, maksimal 2 MB, nama file acak, folder upload tidak dapat mengeksekusi PHP.

---

## 8. Pengujian Tahap 1

1. Buka `http://localhost/govinda-education/` → harus diarahkan ke halaman login.
2. Login dengan password salah 5 kali → muncul peringatan dan pembatasan sementara.
3. Login `admin` / `Admin@2026` → masuk dashboard, muncul toast selamat datang.
4. Login `petugas` / `Petugas@2026` → dashboard tampil, menu **Log Aktivitas** tidak terlihat.
5. Saat login sebagai petugas, akses `admin/log-aktivitas/index.php` → halaman **403 Akses Ditolak**.
6. Login sebagai admin → buka **Log Aktivitas**, cek catatan login/logout, filter, dan pagination.
7. Klik menu pengguna → **Keluar** → kembali ke login dengan pesan logout.
8. Setelah logout, tekan tombol Back browser → dashboard tidak boleh terbuka (diarahkan ke login).
9. Matikan MySQL lalu buka aplikasi → tampil halaman error generik, bukan detail database.
10. Periksa `storage/logs/app.log` dan `storage/logs/php-error.log` bila ada kendala.
11. Uji tampilan di desktop, tablet, dan smartphone (sidebar collapse, tabel scroll).
12. Cek sintaks seluruh PHP:
    ```powershell
    Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
    ```

---

## 8a. Pengujian Tahap 2 (Master Data)

Login sebagai admin, lalu:

**Mata Pelajaran**
1. Buka **Master Data → Mata Pelajaran** → **Tambah Mata Pelajaran**.
2. Simpan nama `Matematika` → kode otomatis `MPL-001`, data muncul di daftar dan detail.
3. Tambah mapel kedua → kode berlanjut `MPL-002`.
4. Coba simpan nama yang sama → muncul pesan "Nama mata pelajaran sudah digunakan".
5. Nonaktifkan lalu aktifkan kembali → badge status berubah dan tercatat di log aktivitas.

**Guru**
6. Buka **Data Guru** → **Tambah Guru**; isi nama, jenis kelamin, kontak, email, pendidikan, dan pilih mapel pada Select2.
7. Simpan → kode otomatis `GRU-2026-0001`, mapel tampil di daftar guru dan chip pada detail.
8. Edit guru → unggah foto (maks. 2 MB). Foto lama hanya terhapus setelah penyimpanan berhasil.
9. Unggah file `.php` yang di-rename menjadi `.png` → ditolak dengan pesan format foto.
10. Kosongkan field foto lalu simpan → foto lama tetap dipakai.
11. Nonaktifkan/aktifkan guru → status berubah dan tercatat di log.
12. Login sebagai **petugas** → menu tambah/edit/nonaktif guru tidak tampil; akses langsung `admin/guru/tambah.php` → **403**.

**Siswa**
13. Buka **Data Siswa** → **Tambah Siswa**; simpan tanpa nama → validasi "Nama lengkap siswa wajib diisi" dan isian lama tetap ada.
14. Isi data valid → kode `SIS-2026-0001`, redirect ke halaman detail.
15. Cek detail: identitas, orang tua, sekolah, group aktif, riwayat group, serta empty state tagihan/pembayaran.
16. Edit siswa dengan foto baru → pratinjau tampil, foto lama terhapus setelah simpan.
17. Filter status/kelas/group dan pencarian DataTables → hasil menyesuaikan.
18. Nonaktifkan siswa → hilang dari filter aktif, muncul di filter nonaktif; aktifkan kembali berhasil.
19. Sebagai petugas: tambah/edit/nonaktif siswa diizinkan (tercatat atas nama petugas).
20. Tanpa CSRF atau akses `proses.php` via GET → **419** / **405**; tanpa login → redirect ke login.

---

## 8b. Pengujian Tahap 3 (Akademik)

Login sebagai admin, lalu:

**Persiapan**
1. Pastikan sudah ada minimal 1 guru aktif, 1 mata pelajaran aktif, dan beberapa siswa aktif.
2. Buka **Akademik → Group Belajar** → **Tambah Group**.

**Group**
3. Simpan group private → kode `GRP-2026-0001`, jenis Private, kapasitas otomatis 1.
4. Simpan group reguler kapasitas 2 → tersimpan. Coba kapasitas 8 → ditolak dengan pesan kapasitas 2–7.
5. Buka detail group → cek tab Informasi, Anggota, Mata Pelajaran & Guru, Jadwal, Riwayat Perubahan.
6. Tambah 3 anggota pada group kapasitas 3, lalu edit kapasitas menjadi 2 → ditolak karena lebih kecil dari anggota aktif.
7. Ubah group beranggota >1 menjadi Private → ditolak.
8. Nonaktifkan group → anggota, relasi mapel, dan jadwal tidak dapat ditambah; aktifkan kembali.

**Mata Pelajaran & Guru**
9. Buka tab **Mata Pelajaran & Guru** → **Tambah Mata Pelajaran**; pilih Matematika + Guru A → tersimpan.
10. Tambah Biologi + Guru B pada group yang sama → tersimpan; dua guru berbeda tampil.
11. Tambah Matematika lagi pada group yang sama → ditolak "sudah terdaftar pada group ini".
12. Nonaktifkan relasi → jadwal relasi tidak dihitung; aktifkan kembali → berhasil bila tidak bentrok.
13. Hapus relasi yang belum punya jadwal → berhasil. Relasi yang sudah punya jadwal → tombol hapus tidak tersedia/ditolak; gunakan nonaktif.
14. Ubah guru pada relasi yang memiliki jadwal yang bentrok dengan guru baru → ditolak dengan nama guru/hari/jam.

**Jadwal**
15. Tambah jadwal Senin 08:00–09:00 untuk Matematika, lalu Rabu 08:00–09:00 untuk mapel yang sama → keduanya diterima (banyak hari).
16. Tambah jadwal guru yang sama pada 08:30–09:30 di group lain → ditolak (bentrok guru).
17. Tambah jadwal group lain dengan guru berbeda pada jam bertumpukan dan ruangan sama (beda huruf besar/kecil) → ditolak (bentrok ruangan).
18. Tambah jadwal kedua pada group yang sama dengan jam bertumpukan → ditolak (bentrok group).
19. Isi jam selesai lebih kecil dari jam mulai → ditolak.
20. Edit jadwal yang sama tanpa mengubah jam → berhasil (diri sendiri dikecualikan).
21. Simpan jadwal → diarahkan ke detail group tanpa error link (base URL tidak terpasang dua kali).
22. Tambah beberapa jadwal pada satu mapel maupun mapel lain dalam group yang sama → semuanya diterima; tidak ada batas 8 pertemuan per bulan.
23. Buka tab **Mata Pelajaran & Guru** → kolom **Terlaksana** menampilkan jumlah pertemuan dari jurnal; tidak ada rekap/filter bulanan.
24. Buka tab **Pertemuan** → isi mapel, tanggal, jam, status, dan catatan jurnal → catatan wajib terisi dan langsung tampil di tabel jurnal.
25. Coba simpan pertemuan tanpa catatan → ditolak dengan pesan "Catatan pertemuan wajib diisi minimal 3 karakter."
26. Coba hapus jadwal aktif → ditolak; nonaktifkan lalu hapus → berhasil. Jadwal nonaktif tidak dihitung pada cek bentrok.
27. Buka **Tampilan Mingguan** → grid 7 hari tampil, hanya jadwal aktif, dan menampilkan mata pelajaran.

**Keanggotaan & Perpindahan**
28. Tambah anggota ke group private yang sudah berisi 1 siswa → ditolak.
29. Isi group reguler sampai 7 siswa → diterima; tambah siswa ke-8 → ditolak "maksimal 7".
30. Tambah siswa yang sudah punya group aktif ke group lain → ditolak dengan arahan memakai fitur pindah.
31. Pindah siswa ke group lain → group asal menutup keanggotaan (`end_date`), group tujuan bertambah, dan halaman **Riwayat Perpindahan** mencatat 1 baris.
32. Coba pindah ke group penuh → ditolak dan tidak ada data yang berubah (rollback).
33. Pindah ke group nonaktif atau group tanpa relasi mapel aktif → ditolak.
34. Akhiri keanggotaan → record tetap ada dengan status Selesai; siswa bisa ditempatkan ke group baru.
35. Filter riwayat berdasarkan tanggal, group asal, group tujuan, dan nama siswa.

**Hak akses & keamanan**
36. Login sebagai **petugas** → dapat menambah anggota/memindahkan siswa, tetapi form tambah group, relasi mapel, dan jadwal tidak tersedia; akses langsung `admin/group/tambah.php`, `admin/group/mapel-tambah.php`, atau POST `admin/jadwal/proses.php` → **403**.
37. Kirim POST tanpa `_csrf` → **419**. Akses endpoint `proses.php` via GET → **405**.
38. Cek dashboard: kartu group private, group reguler, siswa tergabung, dan belum tergabung sesuai isi database.
39. Uji tampilan desktop/tablet/smartphone: tabel scroll horizontal, tab group dapat digeser, grid mingguan dapat di-scroll.

**Uji otomatis**
40. Jalankan uji aturan bisnis (membuat dan membersihkan data uji sendiri):
    ```powershell
    php tests\stage3_test.php
    ```
41. Dengan server `php -S 127.0.0.1:8080` berjalan, jalankan uji HTTP (role, CSRF, alur form):
    ```powershell
    php tests\stage3_http_test.php
    ```

---

## 8c. Pengujian Tahap 4 (Keuangan & Operasional)

Login sebagai admin, lalu:

**Persiapan**
1. Pastikan ada guru aktif, mata pelajaran aktif, dan siswa aktif.
2. Buat group private (1 siswa) dan group reguler (2–3 siswa), tambahkan relasi mata pelajaran–guru dan jadwal.
3. Isi **Tarif Paket** untuk jenis private/1 siswa dan group/2–3 siswa.
4. Tambahkan siswa sebagai anggota group.

**Paket dan realisasi**
5. Buka **Paket Belajar → Tambah Paket**, pilih keanggotaan, simpan → paket nomor 1 berstatus `aktif` dan invoice paket langsung terbit bila tarif tersedia.
   - Untuk banyak siswa sekaligus, buka **Paket Belajar → Per Group**, pilih kelompok, tanggal mulai, dan siswa yang siap → semua paket dan tagihan dibuat dalam satu aksi. Siswa yang sudah memiliki paket aktif otomatis dilewati.
6. Catat pertemuan **terlaksana** di **Realisasi Pertemuan** → progres paket bertambah 1 per pertemuan.
7. Catat pertemuan berstatus **dibatalkan** dan **dijadwalkan_ulang** → tidak menambah hitungan paket.
8. Coba simpan realisasi ganda pada tanggal/jam yang sama → ditolak.
9. Catat pertemuan ke-8 → paket 1 menjadi `selesai`, paket 2 otomatis dibuat **langsung `aktif`**, dan invoice paket 2 terbit meskipun tagihan paket 1 belum dibayar.
10. Catat pertemuan ke-9 → dihitung ke paket 2 yang aktif; tagihan paket 1 tetap belum dibayar.
11. Selesaikan paket 2 (pertemuan ke-16) → paket 3 otomatis aktif dan invoice paket 3 terbit; utang tiga paket menumpuk.
12. Buka **Detail Paket** → cek progres 8/8, riwayat pertemuan, dan audit perubahan.

**Tagihan, pembayaran, dan utang**
13. Buka **Tagihan**, filter status, lalu **Cetak Invoice A4** → nomor invoice, identitas, snapshot siswa/group/mapel/guru, paket 8 pertemuan, tarif, diskon, total, dan status tampil.
14. Buka **Detail Siswa** → ringkasan keuangan menampilkan total tagihan, total dibayar, **total utang**, jumlah tagihan belum dibayar, rincian per paket, dan riwayat pembayaran.
15. Buka **Pembayaran → Tambah**, pilih siswa → centang **satu atau beberapa** tagihan; nominal otomatis terkunci pada total pilihan.
16. Ubah nominal menjadi kurang/lebih dari total pilihan → ditolak dan tidak ada pembayaran tersimpan.
17. Simpan nominal **sama persis** → pembayaran berhasil; setiap tagihan yang dipilih menjadi `lunas` dan memperoleh alokasi penuh. Paket **tidak** berubah status/`start_date`/hitungan.
18. Coba bayar ulang invoice yang sudah lunas → ditolak; invoice batal juga tidak dapat dibayar.
19. Bayar **dua tagihan sekaligus** dalam satu pembayaran → satu nota terbentuk, `payment_allocations` berisi dua baris, dan total alokasi = nominal pembayaran.
20. Buka **Nota A5** → nomor nota, **setiap** invoice yang dilunasi, siswa, group, paket, 8 pertemuan, tarif, diskon, total, metode, status **LUNAS**, petugas, dan ucapan terima kasih tampil.
21. Ubah nama siswa/petugas lalu buka nota lama → isi nota tetap snapshot. Ubah tarif paket lalu cek invoice lama → nominal tidak berubah.
22. Buka **Dashboard** dan **Laporan Utang** → total utang turun sesuai pembayaran, daftar siswa berutang diperbarui, dan siswa yang lunas hilang dari daftar utang.

**Perpindahan dan pembatalan**
23. Pindahkan siswa yang masih memiliki paket `aktif` → muncul konfirmasi; setelah dikonfirmasi paket lama ditutup beralasan dan paket baru dimulai dari 0 (sisa pertemuan tidak dibawa). Utang lama tetap tersimpan.
24. Sebagai admin, batalkan pertemuan ke-8 → paket kembali `aktif`; paket lanjutan hanya dibatalkan bila belum memiliki pembayaran berhasil dan belum memiliki pertemuan.
25. Coba hitung ulang/batalkan paket tanpa alasan → ditolak.

**Laporan, dashboard, dan hak akses**
26. Buka **Laporan → Pembayaran/Tagihan/Paket/Realisasi/Utang** → filter tanggal, group, siswa, status berfungsi; tombol cetak dan **Ekspor CSV** menghasilkan file bertanda BOM dan pemisah `;`.
27. Buka **Rekap Keuangan** → angka **Diterbitkan** dan **Diterima** terpisah dan sesuai data.
28. Cek **Dashboard** → kartu paket, pertemuan, uang diterima, **total utang**, tagihan belum dibayar, siswa berutang, grafik, dan daftar paket mendekati selesai sesuai database.
29. Login sebagai **petugas** → dapat mencatat pembayaran dan melihat laporan; akses tulis tarif/paket (hitung ulang/batal) → **403**.
30. Kirim POST tanpa `_csrf` → **419**; akses endpoint `proses.php` via GET → **405**; tanpa login → redirect ke login.
31. Uji tampilan desktop/tablet/smartphone: tabel, kartu progres paket, formulir, dan dokumen cetak.

**Uji otomatis**
32. Jalankan uji aturan bisnis:
    ```powershell
    php tests\stage4_test.php
    ```
33. Dengan server `php -S 127.0.0.1:8080` berjalan, jalankan uji HTTP:
    ```powershell
    php tests\stage4_http_test.php
    ```

---

## 8d. Pengujian Profil Bimbel

Login sebagai **admin**, lalu:

1. Buka **Sistem → Profil Bimbel** → cek nama, tagline, deskripsi, alamat, telepon, email, situs, dan logo saat ini.
2. Klik **Edit Profil**, ubah alamat/telepon, lalu simpan → data berubah dan tampil di sidebar serta halaman profil.
3. Unggah logo baru (PNG/JPG/WebP, maks. 2 MB) → pratinjau tampil, logo lama diganti, dan muncul di login, dashboard, invoice, serta nota.
4. Unggah file `.php` yang di-rename menjadi `.png` atau file melebihi 2 MB → ditolak dengan pesan validasi.
5. Isi email `bukan-email`, telepon berisi huruf, atau situs dengan skema selain `http/https` → form kembali dengan pesan error dan **tidak ada data yang berubah**.
6. Kosongkan nama bimbel → ditolak karena wajib diisi.
7. Klik **Reset Logo** dan konfirmasi → logo kembali ke bawaan `assets/img/logo-mark.svg`; aktivitas tercatat di **Log Aktivitas**.
8. Login sebagai **petugas** → menu **Profil Bimbel** tidak tampil; akses langsung `admin/profil/index.php`, `admin/profil/edit.php`, atau POST `admin/profil/proses.php` → **403**.
9. POST tanpa `_csrf` → **419**; akses `proses.php` via GET → **405**; tanpa login → redirect ke login.
10. Buka invoice dan nota lama → identitas mengikuti profil terbaru karena dokumen memakai identitas organisasi saat dicetak (snapshot transaksi tetap utuh).

**Uji otomatis** (server `php -S 127.0.0.1:8080` harus berjalan):
```powershell
php tests\profile_http_test.php
```
Uji ini tidak mengubah data profil secara permanen: penyimpanan hanya dilakukan dengan nilai yang sama, dan validasi gagal tidak menulis apa pun.

---

## 8e. Pengujian Pendaftaran Mandiri

Login sebagai **admin**, lalu:

1. Buka **Master Data → Pendaftaran Mandiri**.
2. Klik **Buat Tautan Pendaftaran** pada kartu pendaftaran siswa baru, lalu **Buka Pendaftaran**.
3. Salin tautan umum dan buka di jendela privat → formulir pendaftaran siswa baru tampil. Kirim data lengkap → siswa baru muncul di daftar siswa, aktif, dengan token pribadi.
4. Buka detail siswa → kartu **Pendaftaran Mandiri** menampilkan token dan badge kelengkapan data. Klik **Buat Tautan Siswa** bila belum ada.
5. Salin tautan pribadi dan buka → formulir menampilkan kode siswa terkunci; ubah alamat/HP lalu simpan → data langsung berubah dan badge menjadi **Data lengkap**.
6. Coba kosongkan field wajib, isi HP tidak valid, atau unggah file lebih dari 2 MB → form kembali dengan pesan error dan data lama tidak berubah.
7. Tutup pendaftaran umum → tautan umum tidak dapat dipakai lagi (404). Ganti tautan siswa → tautan lama tidak berlaku.
8. Login sebagai **petugas** → halaman pendaftaran dapat dilihat, tetapi tombol pengaturan umum tidak tampil; POST pengaturan umum → **403**. POST tanpa `_csrf` → **419**.
9. Buka tautan siswa yang sudah dinonaktifkan → halaman menolak akses.

**Uji otomatis** (server `php -S 127.0.0.1:8080` harus berjalan):
```powershell
php tests\self_registration_test.php
php tests\self_registration_http_test.php
```
Uji HTTP memakai siswa berprefix `SREGHTTP` dan membersihkan datanya sendiri. Pengaturan pendaftaran umum dikembalikan ke nilai semula setelah pengujian.

---

## 8f. Pengujian Pengeluaran Operasional

Login sebagai **admin**, lalu:

1. Buka **Keuangan → Pengeluaran** → **Catat Pengeluaran**.
2. Simpan honor guru Rp250.000 → nomor otomatis `OUT-YYYYMM-0001`, status **Tercatat**, dan nama pencatat tampil di detail.
3. Tambah pengeluaran kategori lain → nomor berlanjut dan total kartu ringkasan bertambah.
4. Edit nominal/kategori/penerima → data berubah, nomor tetap.
5. Kosongkan tanggal/penerima atau isi nominal `abc` → form kembali dengan pesan validasi dan isian lama tetap ada.
6. Login sebagai **petugas** → dapat mencatat dan mengedit, tetapi tombol **Batalkan Pengeluaran** tidak tampil; POST pembatalan langsung → **403**.
7. Sebagai **admin**, batalkan pengeluaran dengan alasan → status **Dibatalkan**, alasan tampil, dan data hilang dari hitungan kas/laporan tetapi tetap ada di daftar dengan filter status.
8. Buka **Laporan → Rekap Keuangan** → kartu **Pengeluaran Operasional** dan **Saldo Kas**, rincian nilai, kolom rekap bulanan, grafik, serta **Ekspor CSV** memuat baris pengeluaran dan saldo kas.
9. POST tanpa `_csrf` → **419**; akses `proses.php` via GET → **405**; tanpa login → redirect ke login.

**Uji otomatis** (server `php -S 127.0.0.1:8080` harus berjalan):
```powershell
php tests\expense_test.php
php tests\expense_http_test.php
```
Kedua uji membuat data berprefix `EXPTEST-`/`EXPHTTP-` dan membersihkannya sendiri. Data keuangan nyata tidak disentuh.

---

## 9. Debug

Secara default aplikasi berjalan dalam mode produksi (error tidak ditampilkan). Untuk pengembangan lokal:

```powershell
$env:GOVINDA_DEBUG = "1"
php -S localhost:8080
```

Atau set environment variable `GOVINDA_DEBUG=1` di Apache/cPanel. **Jangan aktifkan di produksi.**

Override konfigurasi database opsional:

```text
GOVINDA_DB_HOST, GOVINDA_DB_PORT, GOVINDA_DB_NAME,
GOVINDA_DB_USER, GOVINDA_DB_PASS, GOVINDA_BASE_URL
```

---

## 10. Roadmap

- **Tahap 2:** ✅ CRUD siswa, guru, mata pelajaran + upload foto.
- **Tahap 3:** ✅ group belajar multi-mapel, relasi mapel-guru, jadwal fleksibel (bentrok guru/group/ruangan), jurnal pertemuan group, keanggotaan, riwayat perpindahan.
- **Tahap 4:** ✅ realisasi pertemuan, paket 8 pertemuan (paket berikutnya langsung aktif), tarif paket, invoice paket + cetak A4, pembayaran wajib lunas multi-tagihan + alokasi, nota A5, laporan utang + CSV, dashboard nyata.
- **Profil Bimbel:** ✅ CRUD identitas (nama, tagline, deskripsi, alamat, kontak, situs) + unggah/reset logo, diterapkan ke layout, login, dashboard, invoice, dan nota.
- **Pendaftaran Mandiri:** ✅ halaman publik untuk siswa baru dan tautan token per siswa, pengelolaan tautan di admin, kelengkapan data, audit, dan pengujian.
- **Pengeluaran Operasional:** ✅ pencatatan biaya bimbel, nomor otomatis, pembatalan ber-audit, hak akses admin/petugas, integrasi Rekap Keuangan + CSV, dan pengujian.
- **Tahap 5:** ✅ pembayaran wajib lunas, transaksi + row lock, kwitansi (diselesaikan bersama Tahap 4).
- **Tahap 6:** ✅ laporan pembayaran/tagihan/paket/realisasi/keuangan + cetak & ekspor CSV (diselesaikan bersama Tahap 4).
- **Tahap 7:** ⏳ pengujian menyeluruh, manajemen pengguna, penyempurnaan, UAT, kesiapan produksi.

---

## 11. Lisensi Aset

- AdminLTE 4 — MIT License.
- Bootstrap 5 — MIT License.
- Bootstrap Icons — MIT License.
- jQuery — MIT License.
- Chart.js — MIT License.
- SweetAlert2 — MIT License.
- Select2 — MIT License.
- DataTables — MIT License.

Logo Govinda Education dibuat khusus untuk proyek ini.
