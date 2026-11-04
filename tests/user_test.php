<?php
declare(strict_types=1);

/**
 * Uji mandiri modul Manajemen Pengguna.
 *
 * Cakupan:
 * - validasi nama, username, email, peran, dan password;
 * - pembuatan akun + hash password + login (attempt);
 * - keunikan username dan email;
 * - pembaruan data dan pengecekan username milik sendiri;
 * - penonaktifan akun menolak login;
 * - atur ulang password;
 * - proteksi minimal satu administrator aktif.
 *
 * Jalankan dari root proyek:
 *   php tests/user_test.php
 *
 * Skrip membuat pengguna uji berawalan USERTEST- lalu menghapusnya kembali.
 * Kode keluar 0 = semua lulus, 1 = ada kegagalan.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$pass = 0;
$fail = 0;
$failures = [];

function check(string $name, bool $condition, string $detail = ''): void
{
    global $pass, $fail, $failures;
    if ($condition) {
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
    check(
        $name,
        $expected === $actual,
        'diharapkan ' . var_export($expected, true) . ', didapat ' . var_export($actual, true)
    );
}

$pdo = db();
$admin = $pdo->query("SELECT * FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1")->fetch();
if (!is_array($admin)) {
    fwrite(STDERR, "FATAL: tidak ada pengguna admin aktif.\n");
    exit(1);
}

$prefix = 'usertest' . date('His');
$stamp = date('His');

$cleanup = static function () use ($pdo, $prefix): void {
    $ids = $pdo->query(
        'SELECT id FROM users WHERE username LIKE ' . $pdo->quote($prefix . '%')
    )->fetchAll(PDO::FETCH_COLUMN);
    if ($ids !== []) {
        $list = implode(',', array_map('intval', $ids));
        $pdo->exec("DELETE FROM activity_logs WHERE related_type = 'user' AND related_id IN ({$list})");
        $pdo->exec("DELETE FROM users WHERE id IN ({$list})");
    }
};

$cleanup();

try {
    // =================================================================
    echo "\n== A. Validasi input ==\n";
    // =================================================================
    $invalid = User::validate([
        'name'     => '',
        'username' => '',
        'email'    => 'bukan-email',
        'role'     => 'owner',
    ], 0, true);
    check('Nama wajib dilaporkan', isset($invalid['name']));
    check('Username wajib dilaporkan', isset($invalid['username']));
    check('Email tidak valid dilaporkan', isset($invalid['email']));
    check('Peran tidak valid dilaporkan', isset($invalid['role']));
    check('Password wajib dilaporkan', isset($invalid['password']));

    $badUsername = User::validate([
        'name'     => 'Uji Username',
        'username' => '1 spasi salah',
        'email'    => '',
        'role'     => 'petugas',
    ], 0, true);
    check('Username dengan spasi ditolak', isset($badUsername['username']));

    $shortUsername = User::validate([
        'name'     => 'Uji Username',
        'username' => 'ab',
        'email'    => '',
        'role'     => 'petugas',
    ], 0, true);
    check('Username kurang dari 3 karakter ditolak', isset($shortUsername['username']));

    check('Password 7 karakter ditolak', User::passwordErrors('Abc1234') !== []);
    check('Password tanpa angka ditolak', User::passwordErrors('abcdefgh') !== []);
    check('Password tanpa huruf ditolak', User::passwordErrors('12345678') !== []);
    check('Password valid diterima', User::passwordErrors('Rahasia2026') === []);

    $mismatch = User::validate([
        'name'             => 'Uji Konfirmasi',
        'username'         => $prefix . 'konfirm',
        'email'            => '',
        'role'             => 'petugas',
        'password'         => 'Rahasia2026',
        'password_confirm' => 'Rahasia2027',
    ], 0, true);
    check('Konfirmasi password berbeda ditolak', isset($mismatch['password_confirm']));

    checkSame('Tidak ada pengguna uji tersimpan dari input tidak valid', 0, (int) $pdo->query(
        'SELECT COUNT(*) FROM users WHERE username LIKE ' . $pdo->quote($prefix . '%')
    )->fetchColumn());

    // =================================================================
    echo "\n== B. Pembuatan akun & login ==\n";
    // =================================================================
    $data = [
        'name'             => 'Petugas Uji ' . $stamp,
        'username'         => $prefix . 'satu',
        'email'            => $prefix . 'satu@contoh.test',
        'role'             => 'petugas',
        'is_active'        => 1,
        'password'         => 'Rahasia2026',
        'password_confirm' => 'Rahasia2026',
    ];
    check('Data pengguna baru valid', User::validate($data, 0, true) === []);

    $newId = User::create($data, 'Rahasia2026');
    check('Pengguna baru tersimpan', $newId > 0);

    $row = User::findById($newId);
    check('Username dinormalisasi huruf kecil', ($row['username'] ?? '') === $prefix . 'satu');
    check('Peran tersimpan sebagai petugas', ($row['role'] ?? '') === 'petugas');
    checkSame('Akun baru berstatus aktif', 1, (int) ($row['is_active'] ?? 0));

    $hash = (string) $pdo->query('SELECT password_hash FROM users WHERE id = ' . $newId)->fetchColumn();
    check('Password tidak disimpan sebagai teks biasa', $hash !== 'Rahasia2026' && str_starts_with($hash, '$2y$'));
    check('Password dapat diverifikasi', password_verify('Rahasia2026', $hash));

    $loginOk = User::attempt($prefix . 'satu', 'Rahasia2026');
    check('Login dengan password benar berhasil', is_array($loginOk));
    check('Data login tidak membawa hash password', !isset($loginOk['password_hash']));
    check('Login dengan password salah ditolak', User::attempt($prefix . 'satu', 'Salah2026') === null);
    check('Login dengan username huruf besar tetap dikenali', is_array(User::attempt(strtoupper($prefix . 'satu'), 'Rahasia2026')));

    $duplicate = User::validate($data, 0, true);
    check('Username ganda ditolak', isset($duplicate['username']));
    check('Username ganda terdeteksi oleh usernameExists', User::usernameExists($prefix . 'satu'));

    $duplicateEmail = User::validate([
        'name'     => 'Email Ganda',
        'username' => $prefix . 'dua',
        'email'    => $prefix . 'satu@contoh.test',
        'role'     => 'petugas',
    ], 0, true);
    check('Email ganda ditolak', isset($duplicateEmail['email']));
    check('Email kosong tidak dianggap ganda', !User::emailExists(''));

    // =================================================================
    echo "\n== C. Pembaruan data ==\n";
    // =================================================================
    $updated = [
        'name'      => 'Petugas Uji Diperbarui ' . $stamp,
        'username'  => $prefix . 'satu',
        'email'     => $prefix . 'baru@contoh.test',
        'role'      => 'petugas',
        'is_active' => 1,
    ];
    check('Username sendiri tidak dianggap ganda saat edit', User::validate($updated, $newId, false) === []);

    User::update($newId, $updated);
    $row = User::findById($newId);
    check('Nama pengguna diperbarui', ($row['name'] ?? '') === $updated['name']);
    check('Email pengguna diperbarui', ($row['email'] ?? '') === $updated['email']);
    check('Email lama tidak lagi terpakai', !User::emailExists($prefix . 'satu@contoh.test', $newId));

    $takeUsername = User::validate([
        'name'     => 'Peran Diubah',
        'username' => $prefix . 'satu',
        'email'    => '',
        'role'     => 'petugas',
    ], 0, false);
    check('Username milik pengguna lain tetap ditolak', isset($takeUsername['username']));

    // =================================================================
    echo "\n== D. Status aktif & password ==\n";
    // =================================================================
    User::setActive($newId, false);
    check('Akun nonaktif tidak dapat login', User::attempt($prefix . 'satu', 'Rahasia2026') === null);
    checkSame('Akun nonaktif tercatat nonaktif', 0, (int) (User::findById($newId)['is_active'] ?? 1));

    User::setActive($newId, true);
    check('Akun aktif kembali dapat login', is_array(User::attempt($prefix . 'satu', 'Rahasia2026')));

    User::setPassword($newId, 'PasswordBaru2026');
    check('Password lama tidak berlaku setelah diubah', User::attempt($prefix . 'satu', 'Rahasia2026') === null);
    check('Password baru dapat dipakai login', is_array(User::attempt($prefix . 'satu', 'PasswordBaru2026')));

    // =================================================================
    echo "\n== E. Proteksi administrator ==\n";
    // =================================================================
    $activeAdmins = User::activeAdminCount();
    check('Jumlah admin aktif terbaca', $activeAdmins >= 1, (string) $activeAdmins);
    checkSame(
        'Mengecualikan satu admin mengurangi hitungan',
        $activeAdmins - 1,
        User::activeAdminCount((int) $admin['id'])
    );

    $adminRow = User::findById((int) $admin['id']);
    check('Admin utama tetap ada', is_array($adminRow) && $adminRow['role'] === 'admin');
    check('Tidak ada metode hapus permanen', !method_exists(User::class, 'delete'));

    // =================================================================
    echo "\n== F. Ringkasan ==\n";
    // =================================================================
    $total = User::countAll();
    $active = User::countActive();
    $inactive = User::countInactive();
    checkSame('Total = aktif + nonaktif', $total, $active + $inactive);
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
echo "Semua pengujian pengguna lulus.\n";
exit(0);
