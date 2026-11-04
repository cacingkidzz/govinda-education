<?php
declare(strict_types=1);

/**
 * Uji aturan bisnis Pendaftaran Mandiri Siswa.
 *
 * Cakupan:
 * - token pribadi siswa (buat, putar, cari, tautan absolut);
 * - validasi data pribadi dan kelengkapan profil;
 * - pembaruan mandiri tidak mengubah kode/group/status/tanggal bergabung;
 * - pendaftaran siswa baru + token otomatis;
 * - token umum pendaftaran (buka/tutup/ganti) tanpa mengubah data riil.
 *
 * Jalankan:
 *   php tests/self_registration_test.php
 */

const TEST_PREFIX = 'SREG-R-';
const TEST_NAME = 'SREG Uji Mandiri';

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

function checkSame(string $name, mixed $expected, mixed $actual): void
{
    check($name, $expected === $actual, 'diharapkan ' . var_export($expected, true) . ', didapat ' . var_export($actual, true));
}

require_once __DIR__ . '/../includes/bootstrap.php';

$_SERVER['HTTP_HOST'] = '127.0.0.1:8080';
$_SERVER['SERVER_PORT'] = '8080';

$pdo = db();
$createdIds = [];
$createdCodes = [];

$cleanup = static function () use ($pdo, &$createdIds, &$createdCodes): void {
    $pdo->exec("DELETE FROM students WHERE code LIKE '" . TEST_PREFIX . "%'");
    if ($createdIds !== []) {
        $ids = implode(',', array_map('intval', $createdIds));
        $pdo->exec('DELETE FROM students WHERE id IN (' . $ids . ')');
    }
    if ($createdCodes !== []) {
        $quoted = array_map(static fn (string $code): string => $pdo->quote($code), $createdCodes);
        $pdo->exec("DELETE FROM activity_logs WHERE username IN (" . implode(',', $quoted) . ")");
    }
};

$cleanup();

$validData = [
    'name'         => 'Siswa Uji Mandiri',
    'gender'       => 'P',
    'birth_place'  => 'Bandung',
    'birth_date'   => '2010-04-12',
    'address'      => 'Jl. Uji Mandiri No. 10',
    'phone'        => '081234567890',
    'parent_name'  => 'Orang Tua Uji',
    'parent_phone' => '081298765432',
    'school'       => 'SMP Negeri Uji',
    'school_class' => '8A',
];

echo "== Persiapan ==\n";
$stmt = $pdo->prepare(
    'INSERT INTO students (code, name, gender, join_date, is_active)
     VALUES (:code, :name, :gender, :join, :active)'
);
$stmt->execute([
    ':code'   => TEST_PREFIX . 'A',
    ':name'   => TEST_NAME . ' A',
    ':gender' => 'P',
    ':join'   => '2026-01-05',
    ':active' => 1,
]);
$studentA = (int) $pdo->lastInsertId();
$createdIds[] = $studentA;

$stmt->execute([
    ':code'   => TEST_PREFIX . 'B',
    ':name'   => TEST_NAME . ' B',
    ':gender' => 'L',
    ':join'   => '2026-02-06',
    ':active' => 1,
]);
$studentB = (int) $pdo->lastInsertId();
$createdIds[] = $studentB;

$stmt->execute([
    ':code'   => TEST_PREFIX . 'C',
    ':name'   => TEST_NAME . ' C',
    ':gender' => 'L',
    ':join'   => '2026-03-07',
    ':active' => 0,
]);
$studentC = (int) $pdo->lastInsertId();
$createdIds[] = $studentC;

try {
    // -----------------------------------------------------------------
    echo "\n== A. Skema & token siswa ==\n";
    // -----------------------------------------------------------------
    $columns = $pdo->query('SHOW COLUMNS FROM students')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['public_token', 'public_token_created_at', 'self_updated_at', 'profile_completed_at'] as $column) {
        check('Kolom students.' . $column . ' tersedia', in_array($column, $columns, true));
    }

    $settings = $pdo->query(
        "SELECT setting_key FROM app_settings WHERE setting_key LIKE 'registration_%'"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach (['registration_enabled', 'registration_token', 'registration_rotated_at'] as $key) {
        check('Setting ' . $key . ' tersedia', in_array($key, $settings, true));
    }

    $token = StudentSelfService::studentToken($studentA);
    check('Token siswa memiliki panjang 48', strlen($token) === 48);
    check('Token siswa berformat hex', StudentSelfService::isToken($token));
    check('Token acak bukan nilai tetap', $token !== str_repeat('0', 48));
    check('Token tidak valid ditolak', !StudentSelfService::isToken('token-palsu'));
    check('Token dapat dicari kembali', (int) (StudentSelfService::findByToken($token)['id'] ?? 0) === $studentA);
    check('Token siswa tetap sama saat diminta ulang', StudentSelfService::studentToken($studentA) === $token);

    $studentArow = Student::find($studentA);
    check('Tautan siswa absolut dan memuat token', str_contains(
        StudentSelfService::studentLink($studentArow),
        'http://127.0.0.1:8080/pendaftaran.php?token=' . $token
    ));

    $oldToken = $token;
    $newToken = StudentSelfService::rotateStudentToken($studentA);
    check('Token baru berbeda setelah diputar', $newToken !== $oldToken);
    check('Token lama tidak berlaku', StudentSelfService::findByToken($oldToken) === null);
    check('Token baru berlaku', (int) (StudentSelfService::findByToken($newToken)['id'] ?? 0) === $studentA);

    // -----------------------------------------------------------------
    echo "\n== B. Validasi & kelengkapan ==\n";
    // -----------------------------------------------------------------
    $empty = StudentSelfService::completeness([]);
    checkSame('Data kosong 0%', 0, $empty['percent']);
    check('Data kosong tidak lengkap', !$empty['complete']);

    $partial = StudentSelfService::completeness(['name' => 'A', 'gender' => 'L']);
    checkSame('Dua dari sepuluh field = 20%', 20, $partial['percent']);
    checkSame('Dua field terisi', 2, $partial['checked']);

    $full = StudentSelfService::completeness($validData);
    checkSame('Data lengkap 100%', 100, $full['percent']);
    check('Data lengkap tanpa kekurangan', $full['complete']);

    check('Data valid lolos validasi', StudentSelfService::validate($validData) === []);

    $missing = StudentSelfService::validate([]);
    foreach (['name', 'gender', 'birth_place', 'birth_date', 'address', 'phone', 'parent_name', 'parent_phone', 'school', 'school_class'] as $field) {
        check('Field wajib ' . $field . ' dilaporkan', isset($missing[$field]));
    }

    $badPhone = $validData;
    $badPhone['phone'] = '12345';
    check('Nomor HP siswa tidak valid ditolak', isset(StudentSelfService::validate($badPhone)['phone']));

    $badParentPhone = $validData;
    $badParentPhone['parent_phone'] = 'abc';
    check('Nomor HP orang tua tidak valid ditolak', isset(StudentSelfService::validate($badParentPhone)['parent_phone']));

    $future = $validData;
    $future['birth_date'] = date('Y-m-d', strtotime('+1 day'));
    check('Tanggal lahir masa depan ditolak', isset(StudentSelfService::validate($future)['birth_date']));

    $longName = $validData;
    $longName['name'] = str_repeat('A', 121);
    $collected = StudentSelfService::collect($longName);
    checkSame('Nama dipotong maksimal 120 karakter', 120, mb_strlen($collected['name']));

    // -----------------------------------------------------------------
    echo "\n== C. Pembaruan mandiri ==\n";
    // -----------------------------------------------------------------
    Student::setActive($studentA, false);
    $before = Student::find($studentA);
    $data = $validData;
    $data['name'] = 'Nama Diperbarui Mandiri';
    $result = StudentSelfService::updateStudent($studentA, $data, null);
    check('Tidak ada foto lama yang perlu dihapus', $result['old_photo'] === null);

    $after = Student::find($studentA);
    checkSame('Nama diperbarui', 'Nama Diperbarui Mandiri', (string) $after['name']);
    checkSame('Nomor HP dinormalisasi', '081234567890', (string) $after['phone']);
    checkSame('Kode siswa tidak berubah', (string) $before['code'], (string) $after['code']);
    checkSame('Tanggal bergabung tidak berubah', (string) $before['join_date'], (string) $after['join_date']);
    checkSame('Status aktif tidak berubah', 0, (int) $after['is_active']);
    check('Waktu pembaruan mandiri terisi', !empty($after['self_updated_at']));
    check('Waktu kelengkapan profil terisi', !empty($after['profile_completed_at']));
    check('Kelengkapan menjadi 100%', StudentSelfService::completeness($after)['complete']);
    Student::setActive($studentA, true);

    // -----------------------------------------------------------------
    echo "\n== D. Pendaftaran siswa baru ==\n";
    // -----------------------------------------------------------------
    $newData = $validData;
    $newData['name'] = TEST_NAME . ' Baru';
    $created = StudentSelfService::createStudent($newData, null);
    $createdIds[] = $created['id'];
    $createdCodes[] = $created['code'];

    $newRow = Student::find($created['id']);
    check('Siswa baru berhasil dibuat', $newRow !== null);
    check('Kode siswa baru berformat SIS-', str_starts_with($created['code'], 'SIS-'));
    checkSame('Siswa baru aktif', 1, (int) ($newRow['is_active'] ?? 0));
    checkSame('Tanggal gabung hari ini', date('Y-m-d'), (string) ($newRow['join_date'] ?? ''));
    check('Siswa baru memiliki token', StudentSelfService::isToken($created['token']));
    check('Token siswa baru dapat dicari', (int) (StudentSelfService::findByToken($created['token'])['id'] ?? 0) === $created['id']);
    check('Data siswa baru lengkap', StudentSelfService::completeness($newRow)['complete']);

    // -----------------------------------------------------------------
    echo "\n== E. Token pendaftaran umum (dalam transaksi) ==\n";
    // -----------------------------------------------------------------
    $pdo->beginTransaction();
    try {
        StudentSelfService::setGeneralEnabled(false);
        check('Pendaftaran umum dapat ditutup', !StudentSelfService::generalEnabled());

        $general = StudentSelfService::ensureGeneralToken();
        check('Token umum memiliki format benar', StudentSelfService::isToken($general));
        check('Token umum yang sama dipakai ulang', StudentSelfService::ensureGeneralToken() === $general);

        StudentSelfService::setGeneralEnabled(true);
        check('Pendaftaran umum dapat dibuka', StudentSelfService::generalEnabled());
        check('Token umum valid diterima', StudentSelfService::generalTokenValid($general));
        check('Token umum salah ditolak', !StudentSelfService::generalTokenValid(str_repeat('a', 48)));
        check('Tautan umum absolut', str_contains(
            StudentSelfService::generalLink(),
            'http://127.0.0.1:8080/pendaftaran.php?daftar=' . $general
        ));

        $rotated = StudentSelfService::rotateGeneralToken();
        check('Token umum baru berbeda', $rotated !== $general);
        check('Token umum lama ditolak', !StudentSelfService::generalTokenValid($general));
        check('Waktu ganti token tercatat', StudentSelfService::generalRotatedAt() !== '');

        $pdo->rollBack();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    check('Perubahan token umum dibatalkan setelah uji', true);
} catch (Throwable $exception) {
    check('Tidak ada error tak terduga', false, $exception->getMessage());
    echo $exception->getTraceAsString() . PHP_EOL;
} finally {
    $cleanup();
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
