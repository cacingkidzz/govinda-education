# AGENTS.md — Aturan Proyek Govinda Education

Dokumen ini adalah sumber aturan teknis dan bisnis yang wajib dipatuhi oleh setiap pengembang/AI agent yang bekerja pada proyek ini.

## 1. Identitas Proyek

- **Nama aplikasi:** Govinda Education
- **Jenis:** Aplikasi manajemen bimbingan belajar
- **Lokasi proyek:** `C:\xampp\htdocs\govinda-education`
- **Database:** `govinda_education` (MySQL/MariaDB, InnoDB, utf8mb4)
- **Target runtime:** PHP 8.x di XAMPP dan hosting cPanel
- **Zona waktu aplikasi:** `Asia/Jakarta`
- **Mata uang:** Rupiah (IDR), format `Rp1.500.000`

## 2. Tumpukan Teknologi

- PHP Native (tanpa framework PHP).
- MySQL/MariaDB via **PDO** dan **prepared statements**.
- AdminLTE 4, Bootstrap 5.3, Bootstrap Icons.
- jQuery, DataTables, SweetAlert2, Select2, Chart.js.
- HTML5 + CSS3.
- Aset pihak ketiga disimpan lokal di `assets/` agar aplikasi tetap berjalan tanpa CDN.

## 3. Struktur Modul

```
admin/
  dashboard.php
  siswa/  guru/  mata-pelajaran/  group/  jadwal/
  siswa/pendaftaran.php  (pengelolaan tautan pendaftaran mandiri)
  realisasi-pertemuan/  paket/  tarif/  tagihan/  pembayaran/  pengeluaran/
  laporan/  profil/  pengguna/  log-aktivitas/
config/    database.php, app.php, config.local.example.php
includes/  bootstrap.php, auth.php, csrf.php, functions.php,
           header.php, navbar.php, sidebar.php, footer.php
classes/   Database, User, Student, Teacher, Subject, Group,
           GroupSubject, Schedule, Membership,
           StudentPackage, Meeting, Pricing, BillingPeriod,
           Invoice, Payment, Expense, Report, ActivityLog, Profile,
           StudentSelfService
proses/    endpoint POST untuk setiap modul
assets/    adminlte/, vendor/, css/, js/, img/
uploads/   siswa/, guru/, pembayaran/, profil/
database/  govinda_education.sql, govinda_education_cpanel.sql,
           build_cpanel_sql.php,
           migrate_stage3_group_subjects.php, migration_stage3_group_subjects.sql,
           migrate_stage4_packages.php, migration_stage4_packages.sql,
           migrate_self_registration.php, migrate_expenses.php
tests/     .htaccess, stage3_test.php, stage3_http_test.php,
           stage4_test.php, stage4_http_test.php, profile_http_test.php,
           self_registration_test.php, self_registration_http_test.php,
           expense_test.php, expense_http_test.php,
           user_test.php, user_http_test.php
pendaftaran.php  (halaman publik pendaftaran & pembaruan data siswa)
DEPLOY-CPANEL.md  (panduan deploy hosting)
```

Endpoint tulis setiap modul berada di `admin/<modul>/proses.php` dan hanya menerima POST + CSRF.

Setiap halaman admin wajib memuat `includes/bootstrap.php`, memanggil `require_login()` (dan `require_role()` bila perlu), lalu memakai `header.php` dan `footer.php` agar layout konsisten (header, sidebar, navbar, breadcrumb, judul, konten, footer).

## 4. Aturan Bisnis Wajib

### 4.1 Siswa
- Siswa yang sudah memiliki riwayat tagihan/pembayaran **tidak boleh dihapus permanen**. Gunakan status aktif/nonaktif.
- Satu siswa hanya boleh memiliki **satu keanggotaan group aktif** pada waktu yang sama.
- Perpindahan group menutup keanggotaan lama (`end_date`) dan membuat keanggotaan baru dalam satu transaksi database.
- Riwayat perpindahan disimpan di `group_members` dan `student_group_history`.
- Perpindahan tidak boleh mengubah invoice yang sudah diterbitkan. Tarif group baru berlaku pada paket berikutnya.
- Jika keanggotaan lama masih memiliki paket berjalan (`aktif`), perpindahan wajib dikonfirmasi dan paket lama ditutup dengan alasan audit. Sisa pertemuan **tidak** dibawa ke group baru; paket baru dimulai dari nol. Utang/tagihan lama tetap tersimpan dan tidak menghalangi perpindahan.
- Kapasitas group tujuan wajib divalidasi sebelum perpindahan.

### 4.2 Group
- Jenis `private`: tepat **1** siswa, kapasitas 1.
- Jenis `group`: **2–7** siswa.
- Satu group boleh memiliki **banyak mata pelajaran**, disimpan di tabel `group_subjects`.
- Setiap relasi group–mata pelajaran diampu oleh **tepat satu guru**; guru boleh berbeda antar-mata pelajaran.
- Mata pelajaran yang sama tidak boleh terdaftar dua kali dalam satu group; jaga unique `(group_id, subject_id)`.
- Group, mata pelajaran, dan guru yang nonaktif tidak boleh dihubungkan dengan relasi baru.
- Relasi `group_subjects` yang sudah memiliki riwayat jadwal tidak boleh dihapus permanen; gunakan status nonaktif.
- Satu group dapat memiliki banyak jadwal; jadwal melekat pada `group_subjects` (`group_schedules.group_subject_id`), bukan langsung pada group.
- Jumlah anggota aktif tidak boleh melebihi kapasitas.
- Group dengan riwayat transaksi tidak boleh dihapus permanen.
- Jadwal guru, group, dan ruangan yang sama tidak boleh bentrok pada hari dan jam yang bertumpukan. Jadwal/relasi/group nonaktif tidak dihitung.
- **Jadwal bersifat fleksibel**: tidak ada batas pertemuan per bulan/minggu. Jadwal boleh ditambah, diubah, atau dipindah kapan saja mengikuti situasi group. Target **8 pertemuan** adalah target paket belajar siswa, bukan batas jadwal.
- Setiap pertemuan per group wajib memiliki **catatan/jurnal** yang diisi admin/petugas atas nama guru pengampu, disimpan pada `class_meetings` (`group_subject_id`, tanggal, jam, status, `notes`).
- Jurnal pertemuan ditampilkan pada tab **Pertemuan** di detail group tanpa filter bulanan; hanya status `terlaksana` yang dihitung ke paket.
- Perubahan guru pada relasi atau pengaktifan kembali relasi wajib memeriksa ulang seluruh jadwal aktifnya.

### 4.3 Tarif Paket
- Tarif dihitung **per siswa per paket 8 pertemuan**, berdasarkan jenis group dan jumlah siswa aktif. Tarif bukan lagi tarif bulanan.
- Nominal ditentukan admin; jangan mengisi nominal otomatis. Kolom `pricing_rules.package_size` default 8.
- Tarif mengikuti tanggal efektif. Perubahan tarif tidak mengubah invoice yang sudah terbit.
- Riwayat tarif tidak dihapus. Tarif baru berlaku sesuai tanggal efektif, bukan otomatis untuk paket berjalan.
- Pencarian tarif memakai jenis group + jumlah siswa + tanggal mulai paket. Bila tarif belum tersedia, paket tetap dibuat dan tagihannya menyusul setelah tarif diisi.

### 4.4 Invoice
- Status invoice **hanya**: `belum_bayar`, `lunas`, `dibatalkan`.
- Jenis invoice: `paket` untuk transaksi baru. Jenis `bulanan` hanya dipertahankan sebagai riwayat lama; **tidak ada lagi penerbitan tagihan bulanan otomatis**.
- Invoice adalah **snapshot**: nama siswa, group, jenis group, mapel, guru, jumlah anggota, nomor paket, target pertemuan, tarif dasar, diskon, total, dan label periode disalin saat penerbitan.
- Satu siswa hanya boleh memiliki satu invoice aktif per paket/keanggotaan. Invoice batal tetap tersimpan sebagai riwayat.
- Invoice lunas tidak boleh diubah nominalnya. Invoice batal tidak boleh dibayar.
- Prorata **belum diaktifkan**; gunakan tarif paket penuh sampai ada konfigurasi dan persetujuan admin.
- Nomor invoice unik dan dibuat otomatis. Invoice paket diterbitkan otomatis saat paket dibuat bila tarif tersedia; pembayaran **tidak** menerbitkan atau mengaktifkan paket.
- Satu siswa boleh memiliki beberapa tagihan belum dibayar sekaligus (utang menumpuk). Tunggakan tidak boleh memblokir pembuatan paket, pencatatan pertemuan, atau perpindahan.

### 4.5 Pembayaran — WAJIB LUNAS
- **Tidak ada cicilan, pembayaran sebagian, atau pelunasan bertahap** di database, kode, form, laporan, maupun dashboard.
- Satu pembayaran boleh melunasi **beberapa tagihan** milik siswa yang sama. Jumlah pembayaran harus **sama persis** dengan total seluruh tagihan yang dipilih.
- Setiap tagihan tetap dibayar **penuh**; kurang atau lebih dari total gabungan ditolak.
- Invoice lunas atau batal → pembayaran ditolak.
- Satu invoice hanya boleh memiliki **satu pembayaran berhasil**. Dijamin oleh `UNIQUE(invoice_id)` pada tabel `payment_allocations` dan `SELECT ... FOR UPDATE` di dalam transaksi.
- `payments.invoice_id` boleh `NULL` untuk pembayaran multi-tagihan; rincian per tagihan disimpan di `payment_allocations` (satu baris per invoice, nominal penuh). Jumlah alokasi wajib sama dengan nominal pembayaran.
- Simpan pembayaran, seluruh alokasi, dan perubahan status invoice dalam satu transaksi database; rollback bila ada kegagalan.
- Petugas yang memproses pembayaran wajib dicatat (ID + nama snapshot `received_by_name`).
- Kwitansi (nota) hanya dibuat setelah pembayaran berhasil dan bersifat **snapshot permanen** (nomor, identitas, siswa, group, paket, 8 pertemuan, tarif, diskon, total, metode, status LUNAS, petugas, ucapan terima kasih). Nota multi-tagihan wajib merinci **setiap** invoice yang dilunasi beserta total pembayaran.
- **Pembayaran tidak mengaktifkan paket, tidak mengubah `start_date`, dan tidak mengubah hitungan pertemuan.** Paket berikutnya sudah aktif sejak pertemuan ke-8 paket sebelumnya; paket `selesai` tidak boleh dihidupkan kembali oleh pembayaran.
- Utang dihitung `Total Tagihan - Total Pembayaran yang Dialokasikan`. Pembayaran sebagian tagihan diperbolehkan selama tagihan yang dipilih lunas penuh; utang tidak menghalangi pembelajaran.
- Pembayaran berhasil tidak boleh dihapus atau diubah nominalnya; koreksi hanya lewat pembatalan ber-audit.
- Validasi JavaScript hanya pelengkap; validasi ulang wajib di PHP.

### 4.6 Realisasi Pertemuan
- Satu baris `class_meetings` = satu kegiatan belajar pada satu tanggal dan jam, melekat pada `group_subjects`.
- Status pertemuan **hanya**: `terjadwal`, `terlaksana`, `dibatalkan`, `dijadwalkan_ulang`.
- **Hanya `terlaksana` yang dihitung** ke paket siswa. Kehadiran/ketidakhadiran siswa tidak memengaruhi hitungan selama kegiatan berlangsung.
- Pertemuan `dibatalkan` dan `dijadwalkan_ulang` tidak dihitung. Jadwal yang dipindahkan membuat pertemuan pengganti (`rescheduled_from_id`).
- Satu siswa hanya boleh dihitung sekali untuk satu pertemuan; dicegah unique dan pengecekan riwayat `student_package_meetings`.
- Hitungan mengikuti masa keanggotaan (`start_date`/`end_date`): siswa yang belum/tidak lagi aktif pada tanggal pertemuan tidak dihitung.
- Realisasi ganda dicegah `UNIQUE (schedule_id, meeting_date)` dan `UNIQUE (group_subject_id, meeting_date, start_time)`.
- Riwayat pertemuan tidak dihapus; perubahan status dicatat di log aktivitas.

### 4.7 Paket Belajar 8 Pertemuan
- Paket = **8 pertemuan nyata per siswa**, dijumlahkan dari **seluruh mata pelajaran** dalam group. Tidak ada batas per mata pelajaran.
- Status paket **hanya**: `aktif`, `selesai`, `dibatalkan`.
- Satu siswa hanya boleh memiliki **satu paket aktif** per keanggotaan; dijaga generated column + unique index.
- Nomor paket berurutan per keanggotaan (1, 2, 3, …).
- Paket pertama dibuat **manual oleh admin**; invoice langsung terbit bila tarif tersedia dan paket tetap `aktif` agar pertemuan dihitung.
- Paket dapat dibuat **massal per group** dalam satu aksi. Paket dan tagihan tetap **milik masing-masing siswa**; group hanya menjadi cara memilih anggota. Siswa yang sudah memiliki paket aktif/nonaktif dilewati, tanggal mulai mengikuti tanggal masuk masing-masing siswa bila lebih lambat, dan seluruh proses dibatalkan bila satu siswa gagal agar tidak ada data setengah jadi.
- Saat pertemuan ke-8 tercatat, paket menjadi `selesai` dan paket berikutnya **langsung dibuat berstatus `aktif`** + invoice bila tarif tersedia. Pembelajaran **tidak pernah** diblokir oleh tagihan lama yang belum lunas.
- Pertemuan berikutnya langsung dihitung ke paket baru yang aktif, meskipun tagihan paket sebelumnya belum dibayar.
- Bila tarif belum tersedia, paket berikutnya tetap dibuat `aktif` tanpa invoice; tagihan menyusul saat tarif diisi.
- Membatalkan status pertemuan ke-8 mengembalikan paket ke `aktif`; paket berikutnya hanya dibatalkan bila belum memiliki pembayaran berhasil dan belum memiliki pertemuan.
- Utang tidak mengubah status paket dan tidak menghentikan hitungan pertemuan.
- Hitungan paket hanya boleh diubah melalui hitung ulang/pembatalan ber-audit dengan alasan, khusus admin. Riwayat paket tidak dihapus.
- Generator paket wajib idempoten: pemanggilan berulang tidak boleh membuat paket ganda.

### 4.8 Laporan & Dashboard
- Laporan wajib tersedia untuk pembayaran, tagihan, paket, realisasi pertemuan, **utang**, dan rekap keuangan.
- Laporan utang wajib menampilkan total tagihan, total dibayar, **total utang**, jumlah tagihan belum dibayar, jumlah siswa berutang, rekap per siswa, dan rincian tagihan per paket; filter siswa/group/periode serta cetak/CSV.
- Rekap keuangan wajib **membedakan uang diterbitkan (issued) dan uang diterima (received)**; jangan mencampur keduanya.
- Filter tanggal/group/siswa/status wajib berfungsi; cetak dan ekspor CSV (UTF-8 BOM, pemisah `;`) tanpa dependensi baru.
- Dashboard minimal menampilkan total utang, jumlah siswa berutang, jumlah tagihan belum dibayar, total uang diterima, dan daftar siswa berutang.
- Dashboard hanya menampilkan data nyata dari database; tidak boleh ada data dummy.
- Dokumen cetak: invoice A4, nota pembayaran A5 (termasuk nota multi-tagihan), memakai halaman cetak mandiri dan `assets/css/print.css`.

### 4.9 Profil Bimbel
- Identitas organisasi dikelola di modul **Profil Bimbel** (`admin/profil/`), **khusus admin**.
- Data disimpan di tabel `app_settings` (key/value) dengan kunci `app_name`, `app_tagline`, `app_description`, `app_address`, `app_phone`, `app_email`, `app_website`, dan `app_logo`. Tidak perlu tabel baru.
- Nama bimbel wajib diisi. Email harus valid. Telepon hanya boleh berisi angka, `+`, spasi, tanda hubung, dan tanda kurung. Situs web dinormalisasi ke `http`/`https`; skema lain (termasuk `javascript:`) ditolak.
- Logo kustom diunggah ke `uploads/profil/` (JPG/PNG/WebP, maksimal 2 MB, nama acak). Bila tidak ada logo kustom, gunakan `assets/img/logo-mark.svg`.
- Path logo wajib divalidasi agar tidak keluar dari folder upload; reset logo hanya menghapus file logo kustom yang tercatat, bukan file lain.
- Identitas dan logo wajib konsisten di sidebar, navbar, halaman login, dashboard, invoice A4, nota A5, dan halaman profil.
- Logo dengan warna dominan terang wajib diberi latar gelap pada permukaan terang, dan sebaliknya, agar tetap terbaca. Logo bawaan tidak memerlukan latar tambahan.
- Perubahan profil, unggah logo, dan reset logo dicatat di log aktivitas (`profil_edit`, `profil_logo`, `profil_logo_reset`).
- Jangan menimpa data profil atau logo yang sudah dipakai tanpa konfirmasi pengguna.

### 4.10 Pendaftaran Mandiri Siswa
- Halaman publik `pendaftaran.php` melayani dua mode: `?token=<token-siswa>` untuk pembaruan data siswa terdaftar, dan `?daftar=<token-umum>` untuk pendaftaran siswa baru.
- Setiap siswa memiliki `students.public_token` acak 48 karakter yang unik. Tautan pribadi bersifat rahasia; token dapat dibuat otomatis dan diganti admin kapan saja.
- Token pendaftaran umum disimpan di `app_settings` (`registration_enabled`, `registration_token`, `registration_rotated_at`). Hanya admin yang boleh membuka, menutup, atau mengganti tautan umum. Pendaftaran umum default **nonaktif**.
- Siswa hanya boleh mengubah data pribadi: nama, jenis kelamin, tempat/tanggal lahir, alamat, HP siswa, nama/HP orang tua, sekolah, kelas, dan foto. **Kode siswa, group, status aktif, dan tanggal bergabung dikunci server** dan tidak boleh diambil dari input.
- Data yang dikirim siswa **langsung tersimpan** (tanpa antrean verifikasi). Perubahan wajib dicatat di log aktivitas (`pendaftaran_mandiri`) dan waktu pembaruan disimpan di `students.self_updated_at`. `students.profile_completed_at` diisi saat 10 field wajib lengkap.
- Kelengkapan data dihitung dari field wajib: nama, jenis kelamin, tempat lahir, tanggal lahir, alamat, HP siswa, nama orang tua, HP orang tua, sekolah, dan kelas. Foto opsional.
- Siswa nonaktif tidak boleh memakai tautan pembaruan. Tautan umum yang ditutup atau diganti harus langsung tidak berlaku.
- Halaman publik wajib memakai CSRF, honeypot anti-bot, pembatasan percobaan per sesi, validasi ulang di server, upload gambar sesuai aturan, dan seluruh output di-escape.
- Pembuatan/penggantian tautan siswa, perubahan pengaturan pendaftaran, dan pembaruan data oleh siswa dicatat di log aktivitas (`pendaftaran_link`, `pendaftaran_pengaturan`, `pendaftaran_mandiri`).

### 4.11 Pengeluaran Operasional
- Pengeluaran mencatat biaya operasional bimbel: honor guru, sewa, listrik & internet, ATK, transport, konsumsi, perawatan, dan lainnya.
- Nomor pengeluaran unik dan otomatis dengan format `OUT-YYYYMM-####`; dibuat di dalam transaksi dan tidak boleh diubah saat edit.
- Status pengeluaran hanya `tercatat` dan `dibatalkan`. **Hanya `tercatat`** yang mengurangi kas, masuk ringkasan, dan masuk laporan.
- Data pengeluaran **tidak boleh dihapus permanen**. Koreksi dilakukan dengan pembatalan ber-audit yang wajib menyertakan alasan, waktu, dan admin pembatal.
- Admin dan petugas boleh mencatat serta mengubah pengeluaran. **Pembatalan hanya admin**; petugas yang mencoba membatalkan ditolak 403.
- Nominal wajib lebih besar dari nol, disimpan `DECIMAL(12,2)`, dan tidak boleh melebihi batas kolom.
- Nama petugas pencatat disimpan sebagai snapshot `recorded_by_name`; perubahan nama pengguna tidak mengubah riwayat.
- Rekap Keuangan wajib menampilkan pengeluaran operasional dan saldo kas (`uang diterima - pengeluaran tercatat`) secara terpisah dari tagihan diterbitkan.
- Perubahan pengeluaran, pembatalan, dan pencatatan dicatat di log aktivitas (`pengeluaran_tambah`, `pengeluaran_edit`, `pengeluaran_batal`).

### 4.12 Pengguna & Akses
- Modul **Manajemen Pengguna** (`admin/pengguna/`) hanya untuk **admin**; petugas mendapat 403 pada halaman maupun endpoint.
- Role hanya `admin` dan `petugas`. Administrator dapat mengelola pengguna, profil bimbel, dan membatalkan pengeluaran.
- Username wajib unik, huruf kecil, 3–30 karakter, diawali huruf, dan hanya boleh berisi huruf, angka, titik, garis bawah, serta tanda hubung.
- Email opsional, harus valid dan unik bila diisi.
- Password minimal 8 karakter (maksimal 72), wajib mengandung huruf dan angka, disimpan dengan `password_hash()`. Password tidak pernah ditampilkan kembali atau ditulis ke log.
- Akun **tidak dihapus permanen**; status diubah aktif/nonaktif dan riwayat aktivitas tetap utuh.
- Minimal **satu administrator aktif** harus selalu tersedia. Penonaktifan atau penurunan peran admin terakhir ditolak.
- Pengguna tidak boleh menonaktifkan atau menurunkan peran **akunnya sendiri**.
- Pengguna nonaktif tidak dapat login; sesi yang sedang berjalan berakhir pada pemeriksaan berikutnya.
- Pembuatan, perubahan, atur ulang password, dan perubahan status dicatat di log aktivitas (`user_tambah`, `user_edit`, `user_password`, `user_aktif`, `user_nonaktif`).

## 5. Aturan Keamanan

- Semua query memakai PDO prepared statements. Dilarang menyisipkan input ke SQL.
- Password memakai `password_hash()` dan `password_verify()`.
- Session regeneration setelah login; cookie `HttpOnly`, `SameSite=Lax`, dan `Secure` saat HTTPS.
- CSRF token wajib pada semua form POST dan diverifikasi di server.
- Escape seluruh output dengan `e()` (`htmlspecialchars`).
- Validasi dan normalisasi input di server.
- Upload: whitelist ekstensi/MIME, batas ukuran, nama file acak, simpan di luar eksekusi PHP.
- Modul **Profil Bimbel** hanya untuk admin; petugas tidak boleh membuka halaman maupun endpoint-nya (403).
- Cek role di setiap halaman/endpoint. Folder `proses/` hanya menerima POST + CSRF.
- Modul Manajemen Pengguna hanya untuk admin (halaman **dan** endpoint). Jangan menyediakan hapus permanen akun; gunakan status aktif/nonaktif, jaga minimal satu administrator aktif, dan larang pengguna menonaktifkan/menurunkan peran akun sendiri.
- Halaman pendaftaran mandiri wajib memvalidasi token secara ketat (format hex 48 karakter), menolak siswa nonaktif, dan tidak pernah mempercayai ID siswa dari input. Ambil siswa dari token.
- Jangan menampilkan token siswa lain, jangan menaruh token di log, dan jangan mengizinkan siswa mengubah kode/group/status/tanggal bergabung.
- Jangan tampilkan detail error/database ke pengguna. Log ke `storage/logs/`.
- Jangan pernah menghapus permanen data transaksi keuangan.
- Pembatalan pengeluaran wajib memakai `require_admin()` di halaman **dan** endpoint `admin/pengeluaran/proses.php`; petugas tidak boleh melihat form pembatalan maupun berhasil memprosesnya.
- Pembatalan pengeluaran wajib menyertakan alasan minimal 3 karakter, dicatat bersama waktu dan admin pembatal, serta tidak boleh menimpa alasan pembatalan sebelumnya.
- Jangan menaruh password atau kredensial di repository/halaman publik.
- Kredensial hosting hanya disimpan di `config/config.local.php` (dimuat otomatis, diblokir `.htaccess`). Jangan mengisi kredensial produksi di `config/database.php`.
- Folder `tests/` wajib ditolak dari akses web (root `.htaccess` + `tests/.htaccess`) karena skrip pengujian membuat dan menghapus data.
- `database/govinda_education_cpanel.sql` dihasilkan dari `database/govinda_education.sql`; jangan mengimpor SQL utama ke database cPanel yang sudah ada karena berisi `CREATE DATABASE`/`USE` dan `DROP TABLE`.

## 6. Standar Database

- InnoDB, `utf8mb4_unicode_ci`.
- Primary key setiap tabel; foreign key dengan perilaku eksplisit.
- Nominal uang: `DECIMAL(12,2)` — bukan FLOAT/DOUBLE.
- `UNIQUE` untuk nomor invoice, nomor transaksi, dan `payments.invoice_id`.
- Index pada kolom pencarian dan foreign key.
- Snapshot invoice dan snapshot nota disimpan sebagai kolom (bukan hasil join saat cetak).
- Satu keanggotaan aktif per siswa dijaga dengan generated column + unique index.
- Satu invoice aktif per siswa/paket dijaga dengan generated column + unique index (`active_package_invoice_key`); invoice bulanan lama memakai `active_invoice_key`.
- Satu paket aktif per keanggotaan dijaga dengan generated column + unique index (`active_package_key`) yang hanya berlaku untuk `status='aktif'`.
- `student_packages.status` hanya berisi `aktif`, `selesai`, `dibatalkan`.
- `students.public_token` wajib unik (`uq_students_public_token`) dan boleh `NULL` untuk siswa yang belum diberi tautan. Token tidak boleh dipakai ulang setelah diganti.
- Pengaturan pendaftaran umum disimpan di `app_settings`, bukan tabel baru. Jangan mencampurnya dengan kunci profil bimbel.
- `payments.invoice_id` nullable untuk pembayaran multi-tagihan; rincian per tagihan disimpan di `payment_allocations` dengan `UNIQUE(payment_id, invoice_id)` dan `UNIQUE(invoice_id)` (satu alokasi berhasil per tagihan).
- `class_meetings`, `student_packages`, `student_package_meetings`, dan `payment_allocations` wajib memiliki FK eksplisit, index pencarian, dan unique pencegah hitung ganda.
- Tabel `expenses` memakai `UNIQUE(expense_number)` (`OUT-YYYYMM-####`), `amount DECIMAL(12,2)` dengan `CHECK (amount > 0)`, enum `status` (`tercatat`, `dibatalkan`), FK `recorded_by`/`cancelled_by` ke `users` (`ON DELETE SET NULL`), index `expense_date`/`category`/`status`, dan snapshot `recorded_by_name`. Tidak boleh ada kolom cicilan atau nominal parsial.
- Perubahan skema harus disertai update `database/govinda_education.sql` (dan migration bila diperlukan), lalu diverifikasi paritas dengan database aktual.

## 7. Standar Tampilan

- Layout AdminLTE 4 responsif; sidebar, navbar, breadcrumb, judul, konten, footer.
- Warna identitas: teal gelap `#0f766e`, aksen emas `#f59e0b`, navy `#0b2532`.
- Format tanggal Indonesia (`d/m/Y` atau `d F Y`) dan Rupiah (`Rp1.500.000`).
- Gunakan komponen konsisten: tombol tambah/edit/detail/nonaktifkan, DataTables, filter, pagination, SweetAlert2, konfirmasi tindakan penting.
- Tidak boleh ada tombol/menu yang tidak berfungsi. Menu modul hanya ditampilkan setelah modulnya benar-benar tersedia.
- Empty state harus informatif, bukan data dummy.
- Identitas dan logo pada seluruh layout serta dokumen cetak diambil dari **Profil Bimbel**, bukan hardcode.

## 8. Alur Kerja Pengembangan

1. Periksa struktur dan file yang ada sebelum mengubah.
2. Jangan menimpa file penting tanpa membaca isinya.
3. Jangan mengubah nama tabel/kolom tanpa alasan dan penjelasan.
4. Selesaikan satu tahap, uji, laporkan, lalu tunggu instruksi.
5. Perbaiki error sebelum lanjut.
6. Jika aturan bisnis nominal/transaksi belum jelas, tanyakan — jangan berasumsi.
7. Jangan mengubah aturan pembayaran wajib lunas dalam kondisi apa pun.

### Tahapan
- **Tahap 1 — Fondasi:** struktur, AGENTS.md, SQL, PDO, config, layout, login/logout, auth/role, dashboard, README. ✅
- **Tahap 2 — Master Data:** siswa, guru, mata pelajaran, upload foto. ✅
- **Tahap 3 — Akademik:** group, relasi mapel-guru (`group_subjects`), jadwal fleksibel, jurnal pertemuan group, keanggotaan, riwayat, validasi. ✅
- **Tahap 4 — Keuangan & Operasional:** realisasi pertemuan, paket 8 pertemuan, tarif paket, generate invoice paket, cetak invoice, pembayaran wajib lunas, nota/kwitansi A5, laporan (pembayaran, tagihan, paket, realisasi, rekap keuangan), ekspor CSV, dashboard nyata. ✅
- **Tahap 5 — Pembayaran:** ✅ pelunasan penuh, transaksi + row lock, kwitansi diselesaikan bersama Tahap 4 sesuai instruksi.
- **Tahap 6 — Laporan:** ✅ laporan dan ekspor diselesaikan bersama Tahap 4 sesuai instruksi.
- **Profil Bimbel:** ✅ CRUD identitas (nama, tagline, deskripsi, alamat, kontak, situs) + unggah/reset logo, diterapkan ke layout, login, dashboard, invoice, dan nota.
- **Pendaftaran Mandiri:** ✅ halaman publik siswa baru + tautan token per siswa, pengelolaan tautan admin, kelengkapan data, audit, dan pengujian.
- **Pengeluaran Operasional:** ✅ pencatatan biaya bimbel, nomor `OUT-YYYYMM-####`, pembatalan ber-audit khusus admin, integrasi Rekap Keuangan + CSV, dan pengujian.
- **Manajemen Pengguna:** ✅ daftar akun, tambah/edit, atur ulang password, aktif/nonaktif, proteksi admin terakhir & akun sendiri, audit, dan pengujian.
- **Tahap 7 — Pengujian:** pengujian menyeluruh, penyempurnaan, UAT, dan kesiapan produksi. ⏳

## 9. Perintah Penting

```bash
# Cek sintaks seluruh file PHP (Windows PowerShell)
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }

# Import database (XAMPP)
C:\xampp\mysql\bin\mysql.exe -u root < database\govinda_education.sql

# Buat ulang SQL siap impor untuk phpMyAdmin cPanel (tanpa CREATE DATABASE/USE)
php database\build_cpanel_sql.php

# Jalankan server pengembangan
php -S localhost:8080

# Migrasi relasi banyak mapel (dari Tahap 3 versi satu-mapel, idempoten)
php database\migrate_stage3_group_subjects.php

# Migrasi Tahap 4: realisasi, paket, invoice paket, pembayaran, nota (idempoten)
php database\migrate_stage4_packages.php

# Migrasi pendaftaran mandiri: token siswa, penanda pembaruan, setting pendaftaran (idempoten)
php database\migrate_self_registration.php

# Migrasi pengeluaran: tabel expenses untuk biaya operasional (idempoten)
php database\migrate_expenses.php

# Uji aturan bisnis Tahap 3 (membuat + membersihkan data uji sendiri)
php tests\stage3_test.php

# Uji HTTP Tahap 3 (jalankan server lebih dulu di 127.0.0.1:8080)
php tests\stage3_http_test.php

# Uji aturan bisnis Tahap 4 (paket, pertemuan, invoice, pembayaran, nota, laporan)
php tests\stage4_test.php

# Uji HTTP Tahap 4 (halaman, cetak, CSV, role, CSRF)
php tests\stage4_http_test.php

# Uji HTTP Profil Bimbel (validasi, akses admin, integrasi tampilan)
php tests\profile_http_test.php

# Uji aturan bisnis pendaftaran mandiri (token, validasi, kelengkapan, pembaruan)
php tests\self_registration_test.php

# Uji HTTP pendaftaran mandiri (halaman publik, tautan admin, role, CSRF)
php tests\self_registration_http_test.php

# Uji aturan bisnis pengeluaran (nomor, validasi, pembatalan, ringkasan, rekap)
php tests\expense_test.php

# Uji HTTP pengeluaran (halaman, hak akses, CSRF, integrasi laporan & CSV)
php tests\expense_http_test.php

# Uji aturan bisnis manajemen pengguna (validasi, hash, keunikan, status, proteksi admin)
php tests\user_test.php

# Uji HTTP manajemen pengguna (halaman, role, CSRF, pembuatan akun, reset password)
php tests\user_http_test.php
```

## 10. Kredensial Awal

Dibuat oleh seed SQL, **wajib diganti** setelah instalasi:

| Role | Username | Password awal |
|------|----------|---------------|
| Admin | `admin` | `Admin@2026` |
| Petugas | `petugas` | `Petugas@2026` |

Jangan menambahkan data bisnis dummy ke seed produksi.
