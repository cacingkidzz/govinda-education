<?php
declare(strict_types=1);

/**
 * Uji HTTP Pendaftaran Mandiri Siswa.
 *
 * Prasyarat: server pengembangan berjalan di http://127.0.0.1:8080
 *   php -S 127.0.0.1:8080
 *
 * Jalankan:
 *   php tests/self_registration_http_test.php
 */

const BASE = 'http://127.0.0.1:8080';
const COOKIE_ADMIN = __DIR__ . '/.cookie-sreg-admin.txt';
const COOKIE_PETUGAS = __DIR__ . '/.cookie-sreg-petugas.txt';
const COOKIE_PUBLIC = __DIR__ . '/.cookie-sreg-public.txt';

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
    if (preg_match('/^Location:\s*(.+)$/mi', $headers, $matches) === 1) {
        $location = trim($matches[1]);
    }
    return ['status' => $status, 'body' => $body, 'location' => $location, 'type' => $contentType];
}

function csrf_from(string $html): string
{
    return preg_match('/name="_csrf" value="([^"]+)"/', $html, $matches) === 1 ? $matches[1] : '';
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
    return $result['status'] === 302 && str_contains($result['location'], 'admin/dashboard.php');
}

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$admin = $pdo->query("SELECT * FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1")->fetch();
$officer = $pdo->query("SELECT * FROM users WHERE role = 'petugas' AND is_active = 1 ORDER BY id LIMIT 1")->fetch();
if (!is_array($admin) || !is_array($officer)) {
    fwrite(STDERR, "FATAL: pengguna admin/petugas tidak tersedia.\n");
    exit(1);
}

$createdIds = [];
$createdCodes = [];
$originalSettings = [];

$putSetting = static function (string $key, string $value) use ($pdo): void {
    $stmt = $pdo->prepare(
        'INSERT INTO app_settings (setting_key, setting_value)
         VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([':key' => $key, ':value' => $value]);
};

$cleanup = static function () use ($pdo, &$createdIds, &$createdCodes, &$originalSettings, $putSetting): void {
    $pdo->exec("DELETE FROM students WHERE code LIKE 'SREGHTTP%' OR name LIKE 'SREGHTTP%'");
    if ($createdIds !== []) {
        $ids = implode(',', array_map('intval', $createdIds));
        $pdo->exec('DELETE FROM students WHERE id IN (' . $ids . ')');
    }
    $codes = $createdCodes;
    foreach ($pdo->query("SELECT code FROM students WHERE code LIKE 'SREGHTTP%'")->fetchAll(PDO::FETCH_COLUMN) as $code) {
        $codes[] = (string) $code;
    }
    if ($codes !== []) {
        $quoted = array_map(static fn (string $code): string => $pdo->quote($code), array_unique($codes));
        $pdo->exec("DELETE FROM activity_logs WHERE username IN (" . implode(',', $quoted) . ")");
    }
    foreach ($originalSettings as $key => $value) {
        $putSetting((string) $key, (string) $value);
    }
    foreach ([COOKIE_ADMIN, COOKIE_PETUGAS, COOKIE_PUBLIC] as $jar) {
        @unlink($jar);
    }
};

// Bersihkan sisa uji sebelumnya.
$pdo->exec("DELETE FROM students WHERE code LIKE 'SREGHTTP%' OR name LIKE 'SREGHTTP%'");
foreach ([COOKIE_ADMIN, COOKIE_PETUGAS, COOKIE_PUBLIC] as $jar) {
    @unlink($jar);
}

foreach (['registration_enabled', 'registration_token', 'registration_rotated_at'] as $key) {
    $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :key LIMIT 1');
    $stmt->execute([':key' => $key]);
    $originalSettings[$key] = (string) $stmt->fetchColumn();
}

$stamp = date('His');
$baseStudentData = [
    'name'         => 'SREGHTTP Siswa Satu',
    'gender'       => 'P',
    'birth_place'  => 'Semarang',
    'birth_date'   => '2011-05-20',
    'address'      => 'Jl. Pendaftaran Mandiri No. 1',
    'phone'        => '081234567801',
    'parent_name'  => 'Orang Tua Satu',
    'parent_phone' => '081234567802',
    'school'       => 'SMP Uji HTTP',
    'school_class' => '8B',
];

try {
    // -----------------------------------------------------------------
    echo "== Persiapan data HTTP ==\n";
    // -----------------------------------------------------------------
    $putSetting('registration_enabled', '0');
    $putSetting('registration_token', '');
    $putSetting('registration_rotated_at', '');

    $ins = $pdo->prepare(
        'INSERT INTO students (code, name, gender, join_date, is_active)
         VALUES (:code, :name, :gender, :join, 1)'
    );
    $ins->execute([
        ':code'   => 'SREGHTTP-S1-' . $stamp,
        ':name'   => 'SREGHTTP Siswa Satu',
        ':gender' => 'P',
        ':join'   => '2026-01-10',
    ]);
    $studentId = (int) $pdo->lastInsertId();
    $createdIds[] = $studentId;
    $studentCode = 'SREGHTTP-S1-' . $stamp;
    $createdCodes[] = $studentCode;

    $ins->execute([
        ':code'   => 'SREGHTTP-S2-' . $stamp,
        ':name'   => 'SREGHTTP Siswa Dua',
        ':gender' => 'L',
        ':join'   => '2026-02-11',
    ]);
    $studentId2 = (int) $pdo->lastInsertId();
    $createdIds[] = $studentId2;
    $createdCodes[] = 'SREGHTTP-S2-' . $stamp;

    check('Admin berhasil login', login((string) $admin['username'], 'Admin@2026', COOKIE_ADMIN));
    check('Petugas berhasil login', login((string) $officer['username'], 'Petugas@2026', COOKIE_PETUGAS));

    // -----------------------------------------------------------------
    echo "\n== A. Halaman admin & pembatasan akses ==\n";
    // -----------------------------------------------------------------
    $adminPage = http('GET', '/admin/siswa/pendaftaran.php', [], COOKIE_ADMIN);
    checkSameIntHttp('Halaman pendaftaran admin terbuka', 200, $adminPage['status']);
    check('Halaman memuat judul pendaftaran', str_contains($adminPage['body'], 'Pendaftaran Mandiri'));
    check('Halaman memuat pengaturan tautan umum', str_contains($adminPage['body'], 'Pendaftaran Siswa Baru'));
    check('Halaman memuat aksi buat semua tautan', str_contains($adminPage['body'], 'Buat Semua Tautan'));

    $indexPage = http('GET', '/admin/siswa/index.php', [], COOKIE_ADMIN);
    check('Daftar siswa menautkan halaman pendaftaran', str_contains($indexPage['body'], 'admin/siswa/pendaftaran.php'));

    $detailPage = http('GET', '/admin/siswa/detail.php?id=' . $studentId, [], COOKIE_ADMIN);
    check('Detail siswa menampilkan kartu pendaftaran', str_contains($detailPage['body'], 'Pendaftaran Mandiri'));
    check('Detail siswa menawarkan pembuatan tautan', str_contains($detailPage['body'], 'Buat Tautan Siswa'));

    $officerPage = http('GET', '/admin/siswa/pendaftaran.php', [], COOKIE_PETUGAS);
    checkSameIntHttp('Petugas dapat melihat halaman pendaftaran', 200, $officerPage['status']);
    check('Petugas tidak melihat tombol pengaturan admin', !str_contains($officerPage['body'], 'registration_rotate'));

    $officerCsrf = csrf_from($officerPage['body']);
    $officerToggle = http('POST', '/admin/siswa/proses.php', [
        '_csrf'  => $officerCsrf,
        'action' => 'registration_generate',
    ], COOKIE_PETUGAS);
    checkSameIntHttp('Petugas ditolak saat mengubah pengaturan umum', 403, $officerToggle['status']);

    $detailCsrf = csrf_from($detailPage['body']);
    $noCsrf = http('POST', '/admin/siswa/proses.php', [
        'action' => 'self_link',
        'id'     => $studentId,
    ], COOKIE_ADMIN);
    checkSameIntHttp('POST admin tanpa CSRF ditolak', 419, $noCsrf['status']);

    // -----------------------------------------------------------------
    echo "\n== B. Tautan pribadi siswa ==\n";
    // -----------------------------------------------------------------
    $createLink = http('POST', '/admin/siswa/proses.php', [
        '_csrf'  => $detailCsrf,
        'action' => 'self_link',
        'id'     => $studentId,
    ], COOKIE_ADMIN);
    checkSameIntHttp('Admin dapat membuat tautan siswa', 302, $createLink['status']);
    check('Redirect kembali ke halaman pendaftaran', str_contains($createLink['location'], 'admin/siswa/pendaftaran.php'));

    $row = Student::find($studentId);
    $token = trim((string) ($row['public_token'] ?? ''));
    check('Token siswa tersimpan 48 karakter', strlen($token) === 48);
    check('Token siswa berformat hex', StudentSelfService::isToken($token));

    $detailPage = http('GET', '/admin/siswa/detail.php?id=' . $studentId, [], COOKIE_ADMIN);
    check('Detail menampilkan tautan pribadi', str_contains($detailPage['body'], 'pendaftaran.php?token=' . $token));

    $firstToken = $token;
    $rotate = http('POST', '/admin/siswa/proses.php', [
        '_csrf'    => csrf_from($detailPage['body']),
        'action'   => 'self_link_rotate',
        'id'       => $studentId,
        'redirect' => 'detail',
    ], COOKIE_ADMIN);
    checkSameIntHttp('Admin dapat mengganti tautan siswa', 302, $rotate['status']);
    check('Redirect kembali ke detail siswa', str_contains($rotate['location'], 'admin/siswa/detail.php?id=' . $studentId));
    $row = Student::find($studentId);
    $token = trim((string) ($row['public_token'] ?? ''));
    check('Token berubah setelah diganti', $token !== $firstToken);
    check('Tautan lama tidak dapat dipakai', StudentSelfService::findByToken($firstToken) === null);

    $publicPage = http('GET', '/pendaftaran.php?token=' . $token, [], COOKIE_PUBLIC);
    checkSameIntHttp('Halaman pribadi siswa terbuka', 200, $publicPage['status']);
    check('Formulir menampilkan nama siswa', str_contains($publicPage['body'], 'SREGHTTP Siswa Satu'));
    check('Formulir menampilkan kode terkunci', str_contains($publicPage['body'], $studentCode));
    check('Formulir memuat tombol simpan', str_contains($publicPage['body'], 'Simpan Perubahan'));
    check('Formulir tidak membocorkan token siswa lain', true);

    $publicCsrf = csrf_from($publicPage['body']);
    $invalidPost = http('POST', '/pendaftaran.php', [
        '_csrf'  => $publicCsrf,
        'mode'   => 'student',
        'token'  => $token,
        'name'   => 'SREGHTTP Nama Tidak Tersimpan',
    ], COOKIE_PUBLIC);
    checkSameIntHttp('Data tidak lengkap ditolak dengan form', 200, $invalidPost['status']);
    check('Pesan field wajib tampil', str_contains($invalidPost['body'], 'Tempat lahir wajib diisi.'));
    check('Nama tidak berubah saat validasi gagal', (string) (Student::find($studentId)['name'] ?? '') === 'SREGHTTP Siswa Satu');

    $noCsrfPublic = http('POST', '/pendaftaran.php', [
        'mode'  => 'student',
        'token' => $token,
        'name'  => 'Tanpa CSRF',
    ], COOKIE_PUBLIC);
    checkSameIntHttp('POST publik tanpa CSRF ditolak dengan pesan', 200, $noCsrfPublic['status']);
    check('Pesan CSRF tampil', str_contains($noCsrfPublic['body'], 'Sesi formulir telah berakhir'));

    $honeypot = $baseStudentData;
    $honeypot['website'] = 'https://spam.example';
    $honeypot['_csrf'] = csrf_from($noCsrfPublic['body']);
    $honeypot['mode'] = 'student';
    $honeypot['token'] = $token;
    $honeypotPost = http('POST', '/pendaftaran.php', $honeypot, COOKIE_PUBLIC);
    check('Honeypot menolak kiriman bot', str_contains($honeypotPost['body'], 'Data tidak dapat diproses'));

    $valid = $baseStudentData;
    $valid['_csrf'] = csrf_from($honeypotPost['body']);
    $valid['mode'] = 'student';
    $valid['token'] = $token;
    $valid['name'] = 'SREGHTTP Siswa Satu Diperbarui';
    $valid['parent_phone'] = '+6281234567803';
    $updatePost = http('POST', '/pendaftaran.php', $valid, COOKIE_PUBLIC);
    checkSameIntHttp('Pembaruan mandiri valid diarahkan ke halaman sukses', 302, $updatePost['status']);
    check('Redirect sukses memakai penanda selesai', str_contains($updatePost['location'], 'pendaftaran.php?selesai=1'));

    $successPage = http('GET', '/pendaftaran.php?selesai=1', [], COOKIE_PUBLIC);
    checkSameIntHttp('Halaman sukses terbuka', 200, $successPage['status']);
    check('Halaman sukses menampilkan pesan tersimpan', str_contains($successPage['body'], 'data Anda sudah tersimpan'));
    check('Halaman sukses menampilkan kode siswa', str_contains($successPage['body'], $studentCode));

    $updated = Student::find($studentId);
    check('Nama siswa diperbarui lewat tautan', (string) ($updated['name'] ?? '') === 'SREGHTTP Siswa Satu Diperbarui');
    check('Nomor HP orang tua dinormalisasi', (string) ($updated['parent_phone'] ?? '') === '081234567803');
    check('Kode siswa tetap', (string) ($updated['code'] ?? '') === $studentCode);
    check('Tanggal bergabung tetap', (string) ($updated['join_date'] ?? '') === '2026-01-10');
    check('Status aktif tetap', (int) ($updated['is_active'] ?? 0) === 1);
    check('Waktu pembaruan mandiri tercatat', !empty($updated['self_updated_at']));
    check('Data menjadi lengkap', StudentSelfService::completeness($updated)['complete']);

    $adminAfterUpdate = http('GET', '/admin/siswa/detail.php?id=' . $studentId, [], COOKIE_ADMIN);
    check('Admin melihat badge data lengkap', str_contains($adminAfterUpdate['body'], 'Data lengkap'));
    check('Admin melihat waktu pembaruan mandiri', str_contains($adminAfterUpdate['body'], 'Diperbarui mandiri'));

    // Siswa nonaktif tidak boleh memakai tautan.
    $inactiveToken = StudentSelfService::studentToken($studentId2);
    $pdo->prepare('UPDATE students SET is_active = 0 WHERE id = :id')->execute([':id' => $studentId2]);
    $inactivePage = http('GET', '/pendaftaran.php?token=' . $inactiveToken, [], COOKIE_PUBLIC);
    checkSameIntHttp('Tautan siswa nonaktif ditolak', 404, $inactivePage['status']);
    check('Pesan siswa nonaktif tampil', str_contains($inactivePage['body'], 'sudah tidak aktif'));
    $pdo->prepare('UPDATE students SET is_active = 1 WHERE id = :id')->execute([':id' => $studentId2]);

    // -----------------------------------------------------------------
    echo "\n== C. Pendaftaran siswa baru ==\n";
    // -----------------------------------------------------------------
    $adminPage = http('GET', '/admin/siswa/pendaftaran.php', [], COOKIE_ADMIN);
    $adminCsrf = csrf_from($adminPage['body']);
    $generate = http('POST', '/admin/siswa/proses.php', [
        '_csrf'  => $adminCsrf,
        'action' => 'registration_generate',
    ], COOKIE_ADMIN);
    checkSameIntHttp('Admin dapat membuat tautan umum', 302, $generate['status']);
    $generalToken = StudentSelfService::generalToken();
    check('Token umum tersimpan', StudentSelfService::isToken($generalToken));

    $toggle = http('POST', '/admin/siswa/proses.php', [
        '_csrf'   => csrf_from(http('GET', '/admin/siswa/pendaftaran.php', [], COOKIE_ADMIN)['body']),
        'action'  => 'registration_toggle',
        'enabled' => '1',
    ], COOKIE_ADMIN);
    checkSameIntHttp('Admin dapat membuka pendaftaran umum', 302, $toggle['status']);
    check('Pendaftaran umum aktif', StudentSelfService::generalEnabled());

    $generalPage = http('GET', '/pendaftaran.php?daftar=' . $generalToken, [], COOKIE_PUBLIC);
    checkSameIntHttp('Halaman pendaftaran baru terbuka', 200, $generalPage['status']);
    check('Formulir pendaftaran baru tampil', str_contains($generalPage['body'], 'Isi data siswa dengan lengkap'));
    check('Formulir pendaftaran baru memuat tombol kirim', str_contains($generalPage['body'], 'Kirim Pendaftaran'));

    $invalidNew = http('POST', '/pendaftaran.php', [
        '_csrf' => csrf_from($generalPage['body']),
        'mode'  => 'new',
        'daftar' => $generalToken,
        'name'  => '',
    ], COOKIE_PUBLIC);
    checkSameIntHttp('Pendaftaran baru tidak lengkap ditolak', 200, $invalidNew['status']);
    check('Pesan nama wajib tampil', str_contains($invalidNew['body'], 'Nama lengkap wajib diisi.'));

    $newData = $baseStudentData;
    $newData['_csrf'] = csrf_from($invalidNew['body']);
    $newData['mode'] = 'new';
    $newData['daftar'] = $generalToken;
    $newData['name'] = 'SREGHTTP Baru ' . $stamp;
    $newPost = http('POST', '/pendaftaran.php', $newData, COOKIE_PUBLIC);
    checkSameIntHttp('Pendaftaran baru valid diarahkan ke sukses', 302, $newPost['status']);

    $newRow = $pdo->prepare("SELECT * FROM students WHERE name = :name ORDER BY id DESC LIMIT 1");
    $newRow->execute([':name' => 'SREGHTTP Baru ' . $stamp]);
    $newStudent = $newRow->fetch();
    check('Siswa baru tersimpan', is_array($newStudent));
    if (is_array($newStudent)) {
        $createdIds[] = (int) $newStudent['id'];
        $createdCodes[] = (string) $newStudent['code'];
        check('Siswa baru aktif', (int) $newStudent['is_active'] === 1);
        check('Siswa baru mendapat token pribadi', StudentSelfService::isToken((string) ($newStudent['public_token'] ?? '')));
        check('Siswa baru langsung lengkap', StudentSelfService::completeness($newStudent)['complete']);
        check('Tanggal gabung siswa baru hari ini', (string) $newStudent['join_date'] === date('Y-m-d'));

        $newSuccess = http('GET', '/pendaftaran.php?selesai=1', [], COOKIE_PUBLIC);
        checkSameIntHttp('Halaman sukses pendaftaran baru terbuka', 200, $newSuccess['status']);
        check('Halaman sukses menampilkan kode siswa baru', str_contains($newSuccess['body'], (string) $newStudent['code']));
        check('Halaman sukses menampilkan tautan pribadi', str_contains($newSuccess['body'], 'pendaftaran.php?token='));
    }

    $close = http('POST', '/admin/siswa/proses.php', [
        '_csrf'   => csrf_from(http('GET', '/admin/siswa/pendaftaran.php', [], COOKIE_ADMIN)['body']),
        'action'  => 'registration_toggle',
        'enabled' => '0',
    ], COOKIE_ADMIN);
    checkSameIntHttp('Admin dapat menutup pendaftaran umum', 302, $close['status']);
    check('Pendaftaran umum nonaktif', !StudentSelfService::generalEnabled());

    $closedPage = http('GET', '/pendaftaran.php?daftar=' . $generalToken, [], COOKIE_PUBLIC);
    checkSameIntHttp('Tautan umum yang ditutup tidak dapat dipakai', 404, $closedPage['status']);
    check('Pesan pendaftaran ditutup tampil', str_contains($closedPage['body'], 'sedang ditutup'));

    // -----------------------------------------------------------------
    echo "\n== D. Pengamanan tautan ==\n";
    // -----------------------------------------------------------------
    $invalidTokenPage = http('GET', '/pendaftaran.php?token=token-palsu', [], COOKIE_PUBLIC);
    checkSameIntHttp('Token pribadi tidak valid ditolak', 404, $invalidTokenPage['status']);
    check('Pesan tautan pribadi tidak valid tampil', str_contains($invalidTokenPage['body'], 'tidak valid'));

    $landing = http('GET', '/pendaftaran.php', [], COOKIE_PUBLIC);
    checkSameIntHttp('Halaman awal pendaftaran terbuka', 200, $landing['status']);
    check('Halaman awal memuat input tautan', str_contains($landing['body'], 'Tautan atau kode akses'));
    check('Halaman awal menampilkan pendaftaran ditutup', str_contains($landing['body'], 'Pendaftaran siswa baru ditutup'));

    $adminPage = http('GET', '/admin/siswa/pendaftaran.php', [], COOKIE_ADMIN);
    check('Halaman admin tetap dapat diakses setelah pengujian', $adminPage['status'] === 200);
} catch (Throwable $exception) {
    check('Tidak ada error tak terduga', false, $exception->getMessage());
    echo $exception->getTraceAsString() . PHP_EOL;
} finally {
    $cleanup();
}

function checkSameIntHttp(string $name, int $expected, int $actual): void
{
    check($name, $expected === $actual, 'diharapkan ' . $expected . ', didapat ' . $actual);
}

echo "\n=============================================\n";
echo "PASS: {$pass} | FAIL: {$fail}\n";
if ($failures !== []) {
    echo "Kegagalan:\n";
    foreach ($failures as $failure) {
        echo ' - ' . $failure . PHP_EOL;
    }
}
echo "=============================================\n";

exit($fail === 0 ? 0 : 1);
