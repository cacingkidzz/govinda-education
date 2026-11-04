# Deploy Govinda Education ke cPanel

Panduan langkah demi langkah menaikkan aplikasi ke hosting cPanel (shared hosting).
Ikuti berurutan. Semua perintah dijalankan dari folder proyek di server.

---

## 1. Persyaratan hosting

| Kebutuhan | Nilai |
|---|---|
| PHP | **8.1 atau lebih baru** (disarankan 8.2) |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `json`, `zlib` |
| Database | MySQL 5.7+ / MariaDB 10.4+ |
| Apache | `mod_rewrite` dan `mod_headers` (standar cPanel/LiteSpeed) |
| SSL | Aktif (AutoSSL) agar cookie session memakai flag `Secure` |
| Ruang | ± 60 MB (aset lokal + upload) |

Aplikasi **tidak butuh Composer, Node.js, atau CDN**. Semua aset sudah lokal di `assets/`.

> Jika hosting memakai Nginx murni (bukan Apache/LiteSpeed), `.htaccess` diabaikan.
> Pada kondisi itu arahkan document root ke folder proyek dan pastikan folder
> `config/`, `includes/`, `classes/`, `database/`, `storage/`, dan `tests/`
> tidak dapat diakses dari web.

---

## 2. Sebelum upload (di komputer lokal)

1. Pastikan aplikasi berjalan normal di XAMPP.
2. Buat arsip ZIP **isi proyek** (bukan folder induknya):
   - `admin/`, `assets/`, `classes/`, `config/`, `includes/`, `proses/`, `uploads/`
   - `index.php`, `login.php`, `logout.php`, `pendaftaran.php`, `.htaccess`, `.user.ini`
   - `storage/` (kosongkan isi `logs/` dan `backups/` agar tidak membawa data lokal)
3. **Jangan** sertakan `config/config.local.php` milik komputer lokal (kalau ada).
4. File `database/govinda_education_cpanel.sql` sudah disiapkan untuk phpMyAdmin.
   Jika skema berubah, buat ulang dengan:
   ```powershell
   php database\build_cpanel_sql.php
   ```

---

## 3. Upload ke cPanel

Pilih salah satu skenario:

### A. Subfolder (paling mudah, disarankan)

1. cPanel → **File Manager** → masuk `public_html`.
2. Buat folder, mis. `govinda-education`.
3. Upload ZIP ke folder itu, lalu **Extract**.
4. Pastikan `index.php` berada di `public_html/govinda-education/index.php`.
5. URL aplikasi: `https://domainanda.com/govinda-education/`

Base URL terdeteksi otomatis dari `DOCUMENT_ROOT`. Tidak perlu diubah.

### B. Root domain / subdomain / addon domain

1. Arahkan document root domain ke folder proyek (Addon Domains / Subdomains).
2. Upload **isi** proyek langsung ke folder document root tersebut.
3. URL aplikasi: `https://domainanda.com/`

> Jangan menimpa `public_html` yang sudah berisi situs lain. Gunakan subfolder,
> subdomain, atau addon domain.

Setelah extract, hapus file ZIP dari server.

---

## 4. Buat database & user MySQL

1. cPanel → **MySQL® Databases**.
2. **Create New Database**: mis. `govinda` → menjadi `cpuser_govinda`.
3. **Add New User**: mis. `govinda` + password kuat → menjadi `cpuser_govinda`.
4. **Add User To Database** → pilih database → centang **ALL PRIVILEGES** → Make Changes.
5. Catat:
   - Database: `cpuser_govinda`
   - User: `cpuser_govinda`
   - Password: (yang baru dibuat)
   - Host: `localhost`

> Nama database dan user **selalu** mendapat prefix akun cPanel. Jangan pakai
> nama polos `govinda_education`.

---

## 5. Impor skema database

Gunakan file **`database/govinda_education_cpanel.sql`** (bukan file utama),
karena file tersebut tidak menjalankan `CREATE DATABASE`/`USE` yang akan
ditolak pada user cPanel.

1. cPanel → **phpMyAdmin**.
2. Pilih database `cpuser_govinda` di sidebar kiri.
3. Tab **Import** → **Choose File** → `govinda_education_cpanel.sql`.
4. Format: SQL, character set: **utf-8** → **Go**.
5. Pastikan muncul "Import has been successfully finished" dan tabel terbentuk
   (21 tabel, termasuk `expenses`, `invoices`, `payments`, `app_settings`).

Jika muncul error `#1044 Access denied ... CREATE DATABASE`, berarti file yang
diimpor salah. Gunakan `govinda_education_cpanel.sql`.

---

## 6. Konfigurasi aplikasi (kredensial hosting)

`config/database.php` memilih environment **otomatis** berdasarkan hostname:

- `localhost` / `127.0.0.1` / CLI → blok **LOKAL** (XAMPP, user `root`, tanpa password).
- domain hosting → blok **PRODUCTION**.

Blok PRODUCTION sengaja **tidak berisi kredensial**. Kredensial hosting hanya
disimpan di `config/config.local.php` yang diblokir dari akses web.

1. cPanel → File Manager → buka `config/`.
2. Salin `config.local.example.php` → namai **`config.local.php`**.
3. Isi sesuai database cPanel:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_PORT', 3306);
   define('DB_NAME', 'cpuser_govinda');
   define('DB_USER', 'cpuser_govinda');
   define('DB_PASS', 'password-database-cpanel-anda');
   ```
4. Simpan sebagai **UTF-8 tanpa BOM**.

Catatan:

- Tanpa `config.local.php`, aplikasi berhenti dengan pesan
  **"Kredensial database hosting belum diisi"**. Ini disengaja agar kredensial
  tidak pernah ditulis di file aplikasi.
- `config.local.php` **menimpa** semua nilai di `config/database.php`, termasuk
  saat CLI, sehingga migrasi lewat Terminal otomatis memakai database hosting.
- File ini diblokir dari akses web, tetapi tetap jangan dibagikan dan jangan
  di-commit ke repositori.
- Jika deteksi base URL salah (aset 404 atau redirect aneh), tambahkan di
  `config.local.php`:
  ```php
  define('APP_BASE_URL_OVERRIDE', '/govinda-education'); // untuk subfolder
  // define('APP_BASE_URL_OVERRIDE', '');               // untuk root domain
  ```

---

## 7. Permission folder

Lewat File Manager (klik kanan → **Change Permissions**) atau Terminal:

| Path | Permission |
|---|---|
| Semua folder | `755` |
| Semua file `.php`, `.css`, `.js` | `644` |
| `.htaccess`, `.user.ini` | `644` |
| `storage/` dan `storage/logs/` | `755` (atau `775` bila perlu) |
| `uploads/` dan subfolder `siswa/ guru/ pembayaran/ profil/` | `755` (atau `775`) |

Aplikasi berjalan sebagai user cPanel, jadi `755` biasanya sudah cukup.
Jika log/upload gagal, naikkan folder terkait ke `775` — **jangan** `777`.

---

## 8. PHP version, ekstensi, dan batas upload

1. cPanel → **MultiPHP Manager** (atau **Select PHP Version**) → pilih **PHP 8.2**
   (minimal 8.1) untuk domain/subfolder aplikasi.
2. cPanel → **Select PHP Version → Extensions** → aktifkan:
   `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `json`, `zlib`.
3. Batas upload (foto maksimal 2 MB):
   - cPanel → **MultiPHP INI Editor** → pilih domain → set:
     ```ini
     upload_max_filesize = 2M
     post_max_size = 8M
     memory_limit = 128M
     max_execution_time = 60
     ```
   - Alternatif: file **`.user.ini`** sudah disertakan di folder proyek dengan isi
     `upload_max_filesize=2M` dan `post_max_size=8M` (berlaku pada PHP-FPM/LiteSpeed;
     tunggu ±5 menit). Sesuaikan lewat MultiPHP INI Editor bila hosting membatasi.
4. Pastikan `display_errors = Off` dan `log_errors = On` (default produksi aplikasi
   sudah memaksa `display_errors=0`).

> Jangan memakai `php_value`/`php_flag` di `.htaccess` pada hosting PHP-FPM/LiteSpeed;
> dapat menyebabkan **500**. Gunakan MultiPHP INI Editor atau `.user.ini`.

---

## 9. HTTPS / SSL

1. cPanel → **SSL/TLS Status** → jalankan **AutoSSL** bila sertifikat belum ada.
2. Setelah HTTPS aktif, buka `.htaccess` di folder proyek dan **buka komentar**
   blok berikut:
   ```apache
   <IfModule mod_rewrite.c>
       RewriteCond %{HTTPS} !=on
       RewriteCond %{HTTP:X-Forwarded-Proto} !https
       RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
   </IfModule>
   ```
3. Kunjungi `https://domainanda.com/govinda-education/` dan pastikan tidak ada
   redirect loop. Jika loop, komentari kembali blok tersebut (proxy hosting
   mungkin sudah menangani HTTPS).

Cookie session otomatis memakai flag `Secure` saat request HTTPS terdeteksi.

---

## 10. Login pertama & pengamanan akun

1. Buka URL aplikasi → halaman login.
2. Login `admin` / `Admin@2026`.
3. Buka **Sistem → Manajemen Pengguna**, lalu gunakan **Atur Ulang Password**:
   - ganti password `admin`,
   - ganti password `petugas`,
   - buat akun petugas baru bila perlu.
4. Login ulang dengan password baru untuk memastikan berhasil.
5. Buka **Profil Bimbel** → isi nama, alamat, kontak, dan unggah logo.
6. Pastikan tidak ada akun default yang masih memakai password awal.

> Akun tidak dapat dihapus permanen, hanya dinonaktifkan. Minimal satu
> administrator aktif harus selalu tersedia, dan akun sendiri tidak dapat
> dinonaktifkan atau diturunkan perannya.

---

## 11. Verifikasi setelah deploy

Lakukan berurutan; semua harus berhasil:

- [ ] Halaman login tampil rapi (CSS/JS termuat, bukan halaman polos).
- [ ] Login admin berhasil, dashboard menampilkan data nyata.
- [ ] **Manajemen Pengguna** terbuka (admin), password default sudah diganti, dan petugas mendapat **403** saat membukanya.
- [ ] Menu **Pengeluaran** terbuka; catat 1 pengeluaran lalu batalkan (admin).
- [ ] **Profil Bimbel** menyimpan identitas dan logo.
- [ ] Tambah siswa/guru + unggah foto (uji batas 2 MB).
- [ ] Buat group, relasi mapel-guru, dan jadwal.
- [ ] Buat paket + tagihan, cetak invoice A4.
- [ ] Catat pembayaran lunas, cetak nota A5.
- [ ] **Laporan → Rekap Keuangan** tampil dan **Ekspor CSV** terunduh.
- [ ] Login **petugas**: tidak melihat form pembatalan pengeluaran/profil.
- [ ] Halaman publik `pendaftaran.php` tampil; pendaftaran umum tetap nonaktif
      sampai admin membukanya.
- [ ] Logout berfungsi dan kembali ke login.
- [ ] Tidak ada error di `storage/logs/php-error.log` dan `storage/logs/app.log`.

---

## 12. Keamanan berkas di server produksi

`.htaccess` sudah memblokir folder internal dan folder pengujian, tetapi
menghapus berkas yang tidak diperlukan lebih aman:

| Boleh dihapus setelah import | Jangan dihapus |
|---|---|
| `tests/` (seluruh folder) | `config/config.local.php` |
| `database/` (setelah SQL diimpor) | `uploads/` (berisi foto & logo) |
| `storage/backups/*.sql` lama | `storage/logs/` (folder, biarkan) |
| `AGENTS.md`, `README.md`, `DEPLOY-CPANEL.md` | `admin/`, `classes/`, `includes/`, `proses/`, `assets/` |
| file ZIP installer | `index.php`, `login.php`, `logout.php`, `pendaftaran.php`, `.htaccess` |

Catatan:

- `tests/*.php` membuat dan menghapus data. Folder ini **wajib** tidak dapat
  diakses dari web (sudah diblokir `.htaccess` + `tests/.htaccess`).
- Folder `database/` berisi file `.sql` dan skrip migrasi; sudah diblokir,
  tetapi sebaiknya dihapus dari server setelah import.
- `storage/backups/` berisi salinan data; jangan biarkan menumpuk.
- Jangan pernah menaruh file backup database di `public_html`.

---

## 13. Update aplikasi (versi berikutnya)

Saat ada perubahan kode/skema:

1. **Backup dulu**: cPanel → **Backup** atau phpMyAdmin → **Export** database.
2. Upload file yang berubah (jangan menimpa `config/config.local.php`, `uploads/`).
3. Jalankan migrasi yang diperlukan lewat **Terminal** cPanel:
   ```bash
   cd ~/public_html/govinda-education
   php database/migrate_expenses.php
   ```
   Migrasi bersifat idempoten dan membuat backup otomatis di `storage/backups/`.
   Karena `config/config.local.php` juga dimuat pada CLI, migrasi otomatis
   memakai database hosting tanpa perlu environment tambahan.
4. Jika Terminal tidak tersedia, buat **Cron Job** sekali jalan:
   ```bash
   cd /home/USER/public_html/govinda-education && /usr/local/bin/php database/migrate_expenses.php
   ```
   Setelah selesai, **hapus** cron tersebut.
5. **Jangan** mengimpor ulang `govinda_education_cpanel.sql` ke database yang
   sudah berisi data: file itu menjalankan `DROP TABLE` dan akan menghapus data.
6. Uji kembali checklist pada bagian 11.

---

## 14. Backup & pemulihan

- **Backup hosting**: cPanel → Backup Wizard / Backup → unduh Home Directory
  dan MySQL Databases secara berkala.
- **Backup aplikasi**: setiap migrasi menyimpan salinan ke `storage/backups/`.
- **Sebelum update**: unduh database lewat phpMyAdmin (Export → SQL) dan simpan
  di komputer, bukan di `public_html`.
- **Pemulihan**: buat database baru → import file backup → sesuaikan
  `config/config.local.php` bila nama database berubah.

---

## 15. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| **500 Internal Server Error** di semua halaman | Lihat `storage/logs/php-error.log`. Biasanya PHP < 8.1, direktif `.htaccess` tidak didukung, atau permission salah. Coba rename `.htaccess` sementara untuk isolasi. |
| **403 Forbidden** semua halaman | Permission folder bukan `755`/file `644`, atau ownership salah. Perbaiki lewat File Manager. |
| **"Kredensial database hosting belum diisi"** | `config/config.local.php` belum dibuat. Salin `config/config.local.example.php` lalu isi `DB_NAME`, `DB_USER`, `DB_PASS` (bagian 6). |
| **"Koneksi database gagal"** | Kredensial di `config/config.local.php` salah, user belum ditambahkan ke database, atau host bukan `localhost`. Detail asli ada di `storage/logs/app.log`. |
| **Halaman login tanpa CSS/JS** | Base URL salah. Set `APP_BASE_URL_OVERRIDE` (`/govinda-education` untuk subfolder, `''` untuk root). |
| **Redirect loop** | Blok HTTPS di `.htaccess` aktif padahal proxy sudah HTTPS. Komentari kembali blok tersebut. |
| **Login berhasil lalu kembali ke login** | Cookie/session terblokir. Pastikan HTTPS aktif dan URL konsisten (jangan campur `www` dan non-`www`). |
| **Upload foto gagal** | `upload_max_filesize` < 2M, folder `uploads/` tidak writable, atau ekstensi `fileinfo` mati. |
| **Import SQL gagal `#1044`** | Memakai `govinda_education.sql` (mengandung `CREATE DATABASE`). Gunakan `govinda_education_cpanel.sql`. |
| **Import SQL gagal `#1046 No database selected`** | Di phpMyAdmin belum memilih database di sidebar kiri sebelum Import. |
| **Data hilang setelah import ulang** | File SQL menjalankan `DROP TABLE`. Jangan impor ulang ke database berisi data; gunakan migrasi. |
| **Zona waktu/format tanggal salah** | Aplikasi memaksa `Asia/Jakarta` dan MySQL `+07:00`. Pastikan tidak ada override timezone di `.user.ini`. |
| **CSV terbuka berantakan di Excel** | CSV memakai UTF-8 BOM + pemisah `;` sesuai standar Indonesia. Gunakan Excel versi terbaru atau import data. |

Jika butuh menyalakan pesan error sementara (hanya saat debugging):

```apache
# .htaccess — HAPUS setelah selesai
SetEnv GOVINDA_DEBUG 1
```

Jangan biarkan `GOVINDA_DEBUG` aktif di produksi.
