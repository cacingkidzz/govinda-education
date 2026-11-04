<?php
declare(strict_types=1);

/**
 * Uji HTTP modul Manajemen Pengguna.
 *
 * Cakupan:
 * - akses halaman hanya untuk admin (petugas 403);
 * - halaman daftar, form tambah, edit, dan atur ulang password;
 * - proteksi POST + CSRF;
 * - pembuatan akun lalu login dengan akun tersebut;
 * - penolakan username ganda dan password lemah;
 * - akun sendiri tidak dapat diturunkan perannya;
 * - atur ulang password dan penonaktifan akun;
 * - log aktivitas tercatat.
 *
 * Prasyarat: server pengembangan berjalan di http://127.0.0.1:8080
 *   php -S 127.0.0.1:8080
 * Jalankan:
 *   php tests/user_http_test.php
 */

const BASE = 'http://127.0.0.1:8080';
const COOKIE_ADMIN = __DIR__ . '/.cookie-user-admin.txt';
const COOKIE_PETUGAS = __DIR__ . '/.cookie-user-petugas.txt';
const COOKIE_BARU = __DIR__ . '/.cookie-user-baru.txt';

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
    if (preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) === 1) {
        return $m[1];
    }
    return '';
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

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$admin = $pdo->query("SELECT * FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1")->fetch();
$officer = $pdo->query("SELECT * FROM users WHERE role = 'petugas' AND is_active = 1 ORDER BY id LIMIT 1")->fetch();
if (!is_array($admin) || !is_array($officer)) {
    fwrite(STDERR, "FATAL: pengguna admin/petugas tidak tersedia.\n");
    exit(1);
}

$stamp = date('His');
$prefix = 'userhttp' . $stamp;
$username = $prefix . 'baru';
$email = $prefix . '@contoh.test';
$password = 'Rahasia2026';
$newPassword = 'PasswordBaru2026';

$cleanup = static function () use ($pdo, $prefix): void {
    $ids = $pdo->query(
        'SELECT id FROM users WHERE username LIKE ' . $pdo->quote($prefix . '%')
    )->fetchAll(PDO::FETCH_COLUMN);
    if ($ids !== []) {
        $list = implode(',', array_map('intval', $ids));
        $pdo->exec("DELETE FROM activity_logs WHERE related_type = 'user' AND related_id IN ({$list})");
        $pdo->exec("DELETE FROM users WHERE id IN ({$list})");
    }
    foreach ([COOKIE_ADMIN, COOKIE_PETUGAS, COOKIE_BARU] as $jar) {
        @unlink($jar);
    }
};

$cleanup();

try {
    check('Admin berhasil login', login('admin', 'Admin@2026', COOKIE_ADMIN));
    check('Petugas berhasil login', login('petugas', 'Petugas@2026', COOKIE_PETUGAS));

    // -----------------------------------------------------------------
    echo "\n== A. Hak akses & halaman ==\n";
    // -----------------------------------------------------------------
    $petugasPage = http('GET', '/admin/pengguna/index.php', [], COOKIE_PETUGAS);
    check('Petugas ditolak membuka daftar pengguna', $petugasPage['status'] === 403, 'status ' . $petugasPage['status']);

    $petugasForm = http('GET', '/admin/pengguna/tambah.php', [], COOKIE_PETUGAS);
    check('Petugas ditolak membuka form tambah pengguna', $petugasForm['status'] === 403, 'status ' . $petugasForm['status']);

    $page = http('GET', '/admin/pengguna/index.php', [], COOKIE_ADMIN);
    check('Admin dapat membuka daftar pengguna', $page['status'] === 200, 'status ' . $page['status']);
    check('Menu Manajemen Pengguna tampil di sidebar', str_contains($page['body'], 'admin/pengguna/index.php'));
    check('Judul daftar pengguna tampil', str_contains($page['body'], 'Daftar Pengguna'));
    check('Tombol tambah pengguna tampil', str_contains($page['body'], 'Tambah Pengguna'));
    check('Akun sendiri ditandai', str_contains($page['body'], '>Anda<'));
    check('Akun sendiri tidak dapat dinonaktifkan', str_contains($page['body'], 'Akun sendiri tidak dapat dinonaktifkan'));

    $form = http('GET', '/admin/pengguna/tambah.php', [], COOKIE_ADMIN);
    check('Form tambah pengguna dapat dibuka', $form['status'] === 200, 'status ' . $form['status']);
    check('Field username tersedia', str_contains($form['body'], 'name="username"'));
    check('Field password tersedia', str_contains($form['body'], 'name="password"'));
    $token = csrf_from($form['body']);
    check('Token CSRF tersedia pada form', $token !== '');

    $petugasDashboard = http('GET', '/admin/dashboard.php', [], COOKIE_PETUGAS);
    $petugasToken = csrf_from($petugasDashboard['body']);
    check('Token CSRF petugas tersedia', $petugasToken !== '');

    $petugasPost = http('POST', '/admin/pengguna/proses.php', [
        '_csrf'    => $petugasToken,
        'action'   => 'create',
        'name'     => 'Petugas Nakal',
        'username' => $prefix . 'nakal',
        'email'    => '',
        'role'     => 'admin',
        'is_active' => '1',
        'password' => 'Rahasia2026',
        'password_confirm' => 'Rahasia2026',
    ], COOKIE_PETUGAS);
    check('Petugas ditolak memproses pembuatan pengguna', $petugasPost['status'] === 403, 'status ' . $petugasPost['status']);

    // -----------------------------------------------------------------
    echo "\n== B. Proteksi CSRF ==\n";
    // -----------------------------------------------------------------
    $noCsrf = http('POST', '/admin/pengguna/proses.php', [
        'action'   => 'create',
        'name'     => 'Tanpa CSRF',
        'username' => $prefix . 'csrf',
        'email'    => '',
        'role'     => 'petugas',
        'is_active' => '1',
        'password' => 'Rahasia2026',
        'password_confirm' => 'Rahasia2026',
    ], COOKIE_ADMIN);
    check('POST tanpa CSRF ditolak 419', $noCsrf['status'] === 419, 'status ' . $noCsrf['status']);

    // -----------------------------------------------------------------
    echo "\n== C. Pembuatan akun ==\n";
    // -----------------------------------------------------------------
    $create = http('POST', '/admin/pengguna/proses.php', [
        '_csrf'    => $token,
        'action'   => 'create',
        'name'     => 'Petugas HTTP ' . $stamp,
        'username' => $username,
        'email'    => $email,
        'role'     => 'petugas',
        'is_active' => '1',
        'password' => $password,
        'password_confirm' => $password,
    ], COOKIE_ADMIN);
    check('Pembuatan pengguna dialihkan ke daftar', $create['status'] === 302
        && str_contains($create['location'], 'admin/pengguna/index.php'), 'status ' . $create['status'] . ' ' . $create['location']);

    $row = $pdo->query('SELECT * FROM users WHERE username = ' . $pdo->quote($username))->fetch();
    check('Pengguna baru tersimpan di database', is_array($row));
    check('Peran tersimpan sebagai petugas', ($row['role'] ?? '') === 'petugas');
    check('Status awal aktif', (int) ($row['is_active'] ?? 0) === 1);
    check('Password tersimpan sebagai hash', str_starts_with((string) ($row['password_hash'] ?? ''), '$2y$'));

    check('Pengguna baru dapat login', login($username, $password, COOKIE_BARU));
    $newUserPage = http('GET', '/admin/pengguna/index.php', [], COOKIE_BARU);
    check('Petugas baru tidak dapat membuka manajemen pengguna', $newUserPage['status'] === 403, 'status ' . $newUserPage['status']);

    // -----------------------------------------------------------------
    echo "\n== D. Validasi server ==\n";
    // -----------------------------------------------------------------
    $before = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $duplicate = http('POST', '/admin/pengguna/proses.php', [
        '_csrf'    => $token,
        'action'   => 'create',
        'name'     => 'Duplikat',
        'username' => $username,
        'email'    => '',
        'role'     => 'petugas',
        'is_active' => '1',
        'password' => $password,
        'password_confirm' => $password,
    ], COOKIE_ADMIN);
    check('Username ganda dikembalikan ke form', $duplicate['status'] === 302
        && str_contains($duplicate['location'], 'admin/pengguna/tambah.php'), 'lokasi ' . $duplicate['location']);
    check('Username ganda tidak menambah data', (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === $before);

    $weak = http('POST', '/admin/pengguna/proses.php', [
        '_csrf'    => $token,
        'action'   => 'create',
        'name'     => 'Password Lemah',
        'username' => $prefix . 'lemah',
        'email'    => '',
        'role'     => 'petugas',
        'is_active' => '1',
        'password' => 'abc',
        'password_confirm' => 'abc',
    ], COOKIE_ADMIN);
    check('Password lemah dikembalikan ke form', $weak['status'] === 302
        && str_contains($weak['location'], 'admin/pengguna/tambah.php'), 'lokasi ' . $weak['location']);
    check('Password lemah tidak membuat akun', (int) $pdo->query(
        'SELECT COUNT(*) FROM users WHERE username = ' . $pdo->quote($prefix . 'lemah')
    )->fetchColumn() === 0);

    $editPage = http('GET', '/admin/pengguna/edit.php?id=' . (int) $row['id'], [], COOKIE_ADMIN);
    check('Form edit pengguna dapat dibuka', $editPage['status'] === 200, 'status ' . $editPage['status']);

    // -----------------------------------------------------------------
    echo "\n== E. Perubahan data & proteksi akun sendiri ==\n";
    // -----------------------------------------------------------------
    $update = http('POST', '/admin/pengguna/proses.php', [
        '_csrf'    => $token,
        'action'   => 'update',
        'id'       => (string) (int) $row['id'],
        'name'     => 'Petugas HTTP Diperbarui ' . $stamp,
        'username' => $username,
        'email'    => $email,
        'role'     => 'petugas',
        'is_active' => '1',
    ], COOKIE_ADMIN);
    check('Perubahan data pengguna berhasil', $update['status'] === 302
        && str_contains($update['location'], 'admin/pengguna/index.php'), 'status ' . $update['status']);
    check('Nama pengguna diperbarui di database', (string) $pdo->query(
        'SELECT name FROM users WHERE id = ' . (int) $row['id']
    )->fetchColumn() === 'Petugas HTTP Diperbarui ' . $stamp);

    $selfDemote = http('POST', '/admin/pengguna/proses.php', [
        '_csrf'    => $token,
        'action'   => 'update',
        'id'       => (string) (int) $admin['id'],
        'name'     => (string) $admin['name'],
        'username' => (string) $admin['username'],
        'email'    => (string) ($admin['email'] ?? ''),
        'role'     => 'petugas',
        'is_active' => '1',
    ], COOKIE_ADMIN);
    check('Admin tidak dapat menurunkan peran sendiri', $selfDemote['status'] === 302
        && str_contains($selfDemote['location'], 'admin/pengguna/edit.php'), 'lokasi ' . $selfDemote['location']);
    check('Peran admin utama tidak berubah', (string) $pdo->query(
        'SELECT role FROM users WHERE id = ' . (int) $admin['id']
    )->fetchColumn() === 'admin');

    $selfToggle = http('POST', '/admin/pengguna/proses.php', [
        '_csrf'  => $token,
        'action' => 'toggle',
        'id'     => (string) (int) $admin['id'],
    ], COOKIE_ADMIN);
    check('Admin tidak dapat menonaktifkan akun sendiri', $selfToggle['status'] === 302
        && str_contains($selfToggle['location'], 'admin/pengguna/index.php'), 'status ' . $selfToggle['status']);
    check('Akun admin utama tetap aktif', (int) $pdo->query(
        'SELECT is_active FROM users WHERE id = ' . (int) $admin['id']
    )->fetchColumn() === 1);

    // -----------------------------------------------------------------
    echo "\n== F. Atur ulang password & status ==\n";
    // -----------------------------------------------------------------
    $passwordPage = http('GET', '/admin/pengguna/password.php?id=' . (int) $row['id'], [], COOKIE_ADMIN);
    check('Form atur ulang password dapat dibuka', $passwordPage['status'] === 200, 'status ' . $passwordPage['status']);
    $passwordToken = csrf_from($passwordPage['body']);
    check('Token CSRF tersedia pada form password', $passwordToken !== '');

    $reset = http('POST', '/admin/pengguna/proses.php', [
        '_csrf'    => $passwordToken,
        'action'   => 'password',
        'id'       => (string) (int) $row['id'],
        'password' => $newPassword,
        'password_confirm' => $newPassword,
    ], COOKIE_ADMIN);
    check('Atur ulang password berhasil', $reset['status'] === 302
        && str_contains($reset['location'], 'admin/pengguna/index.php'), 'status ' . $reset['status']);
    check('Login memakai password baru berhasil', login($username, $newPassword, COOKIE_BARU));
    check('Password lama tidak berlaku lagi', !login($username, $password, COOKIE_BARU));

    $toggle = http('POST', '/admin/pengguna/proses.php', [
        '_csrf'  => $token,
        'action' => 'toggle',
        'id'     => (string) (int) $row['id'],
    ], COOKIE_ADMIN);
    check('Penonaktifan pengguna berhasil', $toggle['status'] === 302
        && str_contains($toggle['location'], 'admin/pengguna/index.php'), 'status ' . $toggle['status']);
    check('Pengguna nonaktif tercatat di database', (int) $pdo->query(
        'SELECT is_active FROM users WHERE id = ' . (int) $row['id']
    )->fetchColumn() === 0);
    check('Pengguna nonaktif tidak dapat login', !login($username, $newPassword, COOKIE_BARU));

    // -----------------------------------------------------------------
    echo "\n== G. Log aktivitas ==\n";
    // -----------------------------------------------------------------
    $logs = $pdo->query(
        "SELECT activity FROM activity_logs WHERE related_type = 'user' AND related_id = " . (int) $row['id']
    )->fetchAll(PDO::FETCH_COLUMN);
    check('Log penambahan pengguna tercatat', in_array('user_tambah', $logs, true));
    check('Log perubahan pengguna tercatat', in_array('user_edit', $logs, true));
    check('Log atur ulang password tercatat', in_array('user_password', $logs, true));
    check('Log penonaktifan pengguna tercatat', in_array('user_nonaktif', $logs, true));
    check('Password tidak pernah ditulis ke log', !str_contains(
        implode(' ', (array) $pdo->query(
            "SELECT description FROM activity_logs WHERE related_type = 'user' AND related_id = " . (int) $row['id']
        )->fetchAll(PDO::FETCH_COLUMN)),
        $newPassword
    ));
} finally {
    $cleanup();
}

echo "\n============================================\n";
echo 'LULUS: ' . $pass . '   GAGAL: ' . $fail . "\n";
if ($fail > 0) {
    echo "\nKegagalan:\n";
    foreach ($failures as $failure) {
        echo '  - ' . $failure . "\n";
    }
    exit(1);
}
echo "Semua pengujian HTTP pengguna lulus.\n";
exit(0);
