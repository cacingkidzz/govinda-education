<?php
declare(strict_types=1);

/**
 * Uji HTTP modul Profil Bimbel.
 *
 * Cakupan:
 * - halaman profil dan edit hanya untuk admin;
 * - identitas profil tampil di sidebar/dashboard/login;
 * - logo kustom atau logo bawaan tampil dengan latar kontras;
 * - validasi server menolak input tidak valid tanpa mengubah data;
 * - simpan no-op mempertahankan seluruh nilai profil;
 * - proteksi role, CSRF, dan metode POST.
 *
 * Uji ini tidak mengunggah/menghapus logo dan tidak mengganti identitas
 * secara permanen. Data profil hanya ditulis ulang dengan nilai yang sama.
 *
 * Prasyarat: server pengembangan berjalan di http://127.0.0.1:8080
 *   php -S 127.0.0.1:8080
 * Jalankan:
 *   php tests/profile_http_test.php
 */

const BASE = 'http://127.0.0.1:8080';
const COOKIE_ADMIN = __DIR__ . '/.cookie-profile-admin.txt';
const COOKIE_PETUGAS = __DIR__ . '/.cookie-profile-petugas.txt';

$pass = 0;
$fail = 0;
$failures = [];

function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $failures;
    if ($ok) {
        $pass++;
        echo "  PASS  {$name}\n";
        return;
    }
    $fail++;
    $failures[] = $name . ($detail !== '' ? ' — ' . $detail : '');
    echo "  FAIL  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

/**
 * @return array{status:int,body:string,location:string,type:string}
 */
function http(string $method, string $path, array $fields = [], ?string $cookieJar = null): array
{
    $ch = curl_init(BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if ($cookieJar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    curl_close($ch);

    $location = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m) === 1) {
        $location = trim($m[1]);
    }
    return ['status' => $status, 'body' => $body, 'location' => $location, 'type' => $contentType];
}

function csrf_from(string $html): string
{
    return preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) === 1 ? $m[1] : '';
}

function login(string $username, string $password, string $jar): bool
{
    @unlink($jar);
    $page = http('GET', '/login.php', [], $jar);
    $token = csrf_from($page['body']);
    if ($token === '') {
        return false;
    }
    $result = http('POST', '/login.php', [
        '_csrf'    => $token,
        'username' => $username,
        'password' => $password,
    ], $jar);
    return $result['status'] === 302 && strpos($result['location'], 'admin/dashboard.php') !== false;
}

/**
 * Token pada halaman yang dapat diakses role tertentu.
 */
function page_token(string $path, string $jar): string
{
    $page = http('GET', $path, [], $jar);
    return csrf_from($page['body']);
}

function logo_present(string $body): bool
{
    return stripos($body, 'logo-mark') !== false || stripos($body, 'uploads/profil/') !== false;
}

require_once __DIR__ . '/../includes/bootstrap.php';

echo "== A. Validasi kelas Profile (tanpa menulis data) ==\n";

$invalid = Profile::validate([
    'app_name'    => '',
    'app_tagline' => str_repeat('x', 151),
    'app_email'   => 'bukan-email',
    'app_phone'   => '123',
    'app_website' => 'ftp://contoh.test',
]);
check('Nama kosong ditolak', isset($invalid['app_name']));
check('Tagline kepanjangan ditolak', isset($invalid['app_tagline']));
check('Email tidak valid ditolak', isset($invalid['app_email']));
check('Telepon tidak valid ditolak', isset($invalid['app_phone']));
check('Situs non-http ditolak', isset($invalid['app_website']));

check('Situs tanpa skema dinormalisasi', Profile::normalizeWebsite('bimbel.example.test') === 'https://bimbel.example.test');
check('Situs http diterima', Profile::normalizeWebsite('http://bimbel.example.test') === 'http://bimbel.example.test');
check('Situs javascript ditolak', Profile::normalizeWebsite('javascript:alert(1)') === '');
check('Path logo valid diterima', Profile::normalizeLogoPath('profil/logo-1.png') === 'profil/logo-1.png');
check('Path logo traversal ditolak', Profile::normalizeLogoPath('../logo.png') === '');

$profile = Profile::all();
check('Nama profil tidak kosong', Profile::name() !== '');
check('Logo URL tersedia', Profile::logoUrl() !== '');

echo "\n== B. Login dan halaman profil ==\n";

$admin = db()->query("SELECT * FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1")->fetch();
$officer = db()->query("SELECT * FROM users WHERE role = 'petugas' AND is_active = 1 ORDER BY id LIMIT 1")->fetch();
if (!is_array($admin) || !is_array($officer)) {
    fwrite(STDERR, "FATAL: pengguna admin/petugas tidak tersedia.\n");
    exit(1);
}

check('Login admin berhasil', login('admin', 'Admin@2026', COOKIE_ADMIN));
check('Login petugas berhasil', login('petugas', 'Petugas@2026', COOKIE_PETUGAS));

$publicLogin = http('GET', '/login.php');
check('Halaman login publik dapat dibuka', $publicLogin['status'] === 200, 'status ' . $publicLogin['status']);
check('Login menampilkan nama profil', strpos($publicLogin['body'], Profile::name()) !== false);
check('Login menampilkan logo profil', logo_present($publicLogin['body']));
if (Profile::tagline() !== '') {
    check('Login menampilkan tagline profil', strpos($publicLogin['body'], Profile::tagline()) !== false);
}

$index = http('GET', '/admin/profil/index.php', [], COOKIE_ADMIN);
check('Halaman profil dapat dibuka admin', $index['status'] === 200, 'status ' . $index['status']);
check('Halaman profil memuat judul', stripos($index['body'], 'Profil Bimbel') !== false);
check('Halaman profil memuat nama', strpos($index['body'], Profile::name()) !== false);
check('Halaman profil memuat logo', logo_present($index['body']));
if (Profile::address() !== '') {
    check('Halaman profil memuat alamat', strpos($index['body'], Profile::address()) !== false);
}
if (Profile::hasCustomLogo()) {
    check('Logo kustom memakai kelas kontras', strpos($index['body'], 'logo-tile-') !== false);
}

$edit = http('GET', '/admin/profil/edit.php', [], COOKIE_ADMIN);
check('Halaman edit profil dapat dibuka', $edit['status'] === 200, 'status ' . $edit['status']);
check('Form edit memuat field identitas', strpos($edit['body'], 'name="app_name"') !== false
    && strpos($edit['body'], 'name="app_address"') !== false
    && strpos($edit['body'], 'name="logo"') !== false);
check('Form edit memuat nilai saat ini', strpos($edit['body'], e(Profile::name())) !== false);

$dashboard = http('GET', '/admin/dashboard.php', [], COOKIE_ADMIN);
check('Dashboard memakai nama profil', strpos($dashboard['body'], Profile::name()) !== false);
check('Dashboard memakai logo profil', logo_present($dashboard['body']));

echo "\n== C. Hak akses, CSRF, dan metode ==\n";

$officerIndex = http('GET', '/admin/profil/index.php', [], COOKIE_PETUGAS);
check('Petugas ditolak membuka profil', $officerIndex['status'] === 403, 'status ' . $officerIndex['status']);

$officerEdit = http('GET', '/admin/profil/edit.php', [], COOKIE_PETUGAS);
check('Petugas ditolak membuka edit profil', $officerEdit['status'] === 403, 'status ' . $officerEdit['status']);

$officerToken = page_token('/admin/dashboard.php', COOKIE_PETUGAS);
$officerPost = http('POST', '/admin/profil/proses.php', [
    '_csrf'    => $officerToken,
    'action'   => 'update',
    'app_name' => 'Tidak Boleh Tersimpan',
], COOKIE_PETUGAS);
check('Petugas ditolak menyimpan profil', $officerPost['status'] === 403, 'status ' . $officerPost['status']);

$getProses = http('GET', '/admin/profil/proses.php', [], COOKIE_ADMIN);
check('Endpoint proses menolak GET', $getProses['status'] === 405, 'status ' . $getProses['status']);

$noCsrf = http('POST', '/admin/profil/proses.php', ['action' => 'update'], COOKIE_ADMIN);
check('POST tanpa CSRF ditolak', $noCsrf['status'] === 419, 'status ' . $noCsrf['status']);

$adminToken = page_token('/admin/profil/edit.php', COOKIE_ADMIN);
$unknown = http('POST', '/admin/profil/proses.php', [
    '_csrf'  => $adminToken,
    'action' => 'aksi-tidak-dikenal',
], COOKIE_ADMIN);
check('Aksi tidak dikenal ditolak 404', $unknown['status'] === 404, 'status ' . $unknown['status']);

echo "\n== D. Validasi server tidak mengubah data ==\n";

$before = Profile::all();
$editAgain = http('GET', '/admin/profil/edit.php', [], COOKIE_ADMIN);
$token = csrf_from($editAgain['body']);
check('Token form edit tersedia', $token !== '');

$invalidPost = http('POST', '/admin/profil/proses.php', [
    '_csrf'          => $token,
    'action'         => 'update',
    'app_name'       => 'Nama Tidak Boleh Tersimpan',
    'app_tagline'    => $before['app_tagline'],
    'app_description'=> $before['app_description'],
    'app_address'    => $before['app_address'],
    'app_phone'      => $before['app_phone'],
    'app_email'      => 'bukan-email',
    'app_website'    => $before['app_website'],
], COOKIE_ADMIN);
check('Input tidak valid kembali ke form', $invalidPost['status'] === 302
    && strpos($invalidPost['location'], 'admin/profil/edit.php') !== false, 'status ' . $invalidPost['status']);

Profile::flushCache();
$afterInvalid = Profile::all();
check('Nama lama tidak berubah saat validasi gagal', $afterInvalid['app_name'] === $before['app_name']);
check('Logo lama tidak berubah saat validasi gagal', $afterInvalid['app_logo'] === $before['app_logo']);

$errorPage = http('GET', '/admin/profil/edit.php', [], COOKIE_ADMIN);
check('Pesan error email tampil', stripos($errorPage['body'], 'Alamat email tidak valid') !== false);

echo "\n== E. Simpan no-op mempertahankan data ==\n";

$editNoop = http('GET', '/admin/profil/edit.php', [], COOKIE_ADMIN);
$token = csrf_from($editNoop['body']);
$noop = http('POST', '/admin/profil/proses.php', [
    '_csrf'           => $token,
    'action'          => 'update',
    'app_name'        => $before['app_name'],
    'app_tagline'     => $before['app_tagline'],
    'app_description' => $before['app_description'],
    'app_address'     => $before['app_address'],
    'app_phone'       => $before['app_phone'],
    'app_email'       => $before['app_email'],
    'app_website'     => $before['app_website'],
], COOKIE_ADMIN);
check('Simpan no-op berhasil', $noop['status'] === 302
    && strpos($noop['location'], 'admin/profil/index.php') !== false, 'status ' . $noop['status']);

Profile::flushCache();
$after = Profile::all();
$same = true;
foreach (['app_name', 'app_tagline', 'app_description', 'app_address', 'app_phone', 'app_email', 'app_website', 'app_logo'] as $key) {
    if (($before[$key] ?? '') !== ($after[$key] ?? '')) {
        $same = false;
        echo '        berubah: ' . $key . PHP_EOL;
    }
}
check('Seluruh nilai profil tetap setelah simpan no-op', $same);

echo "\n=============================================\n";
echo "HASIL PROFIL: {$pass} PASS / {$fail} FAIL\n";
if ($failures !== []) {
    echo "Kegagalan:\n";
    foreach ($failures as $failure) {
        echo "  - {$failure}\n";
    }
    exit(1);
}
exit(0);
