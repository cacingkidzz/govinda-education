<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
require_login();
csrf_require();
require_admin();

/**
 * @return array<string, mixed>
 */
$collect = static function (): array {
    $status = post_string('is_active');
    return [
        'name'             => post_string('name'),
        'username'         => post_string('username'),
        'email'            => post_string('email'),
        'role'             => post_string('role'),
        'is_active'        => $status === '0' ? 0 : 1,
        'password'         => is_string($_POST['password'] ?? null) ? (string) $_POST['password'] : '',
        'password_confirm' => is_string($_POST['password_confirm'] ?? null) ? (string) $_POST['password_confirm'] : '',
    ];
};

$action = post_string('action');
$me = Auth::user();

if ($action === 'create') {
    $raw = $collect();
    $errors = User::validate($raw, 0, true);

    if ($errors !== []) {
        form_fail($errors, $raw, 'admin/pengguna/tambah.php');
    }

    $id = User::create($raw, (string) $raw['password']);

    log_activity(
        'user_tambah',
        'Menambahkan pengguna "' . $raw['name'] . '" (@' . User::normalizeUsername((string) $raw['username'])
            . ') dengan peran ' . role_label((string) $raw['role']) . '.',
        'user',
        $id
    );
    flash('success', 'Pengguna "' . $raw['name'] . '" berhasil ditambahkan dan dapat langsung login.');
    redirect('admin/pengguna/index.php');
}

if ($action === 'update') {
    $id = post_int('id');
    $existing = $id > 0 ? User::findById($id) : null;
    if ($existing === null) {
        abort_404('Data pengguna tidak ditemukan.');
    }

    $raw = $collect();
    $errors = User::validate($raw, $id, false);

    $roleChanged = (string) $raw['role'] !== (string) $existing['role'];
    $statusChanged = (int) $raw['is_active'] !== (int) $existing['is_active'];
    $wasActiveAdmin = (string) $existing['role'] === User::ROLE_ADMIN && (int) $existing['is_active'] === 1;
    $stillActiveAdmin = (string) $raw['role'] === User::ROLE_ADMIN && (int) $raw['is_active'] === 1;

    if ($me !== null && $id === (int) $me['id'] && !$stillActiveAdmin) {
        $errors['form'] = 'Anda tidak dapat menurunkan peran atau menonaktifkan akun sendiri.';
    } elseif ($wasActiveAdmin && !$stillActiveAdmin && User::activeAdminCount($id) === 0) {
        $errors['form'] = 'Minimal harus ada satu administrator aktif.';
    }

    if ($errors !== []) {
        form_fail($errors, $raw, 'admin/pengguna/edit.php?id=' . $id);
    }

    User::update($id, $raw);

    $changes = [];
    if ((string) $raw['name'] !== (string) $existing['name']) {
        $changes[] = 'nama';
    }
    if (User::normalizeUsername((string) $raw['username']) !== (string) $existing['username']) {
        $changes[] = 'username';
    }
    if ((string) $raw['email'] !== (string) ($existing['email'] ?? '')) {
        $changes[] = 'email';
    }
    if ($roleChanged) {
        $changes[] = 'peran menjadi ' . role_label((string) $raw['role']);
    }
    if ($statusChanged) {
        $changes[] = (int) $raw['is_active'] === 1 ? 'status aktif' : 'status nonaktif';
    }

    log_activity(
        'user_edit',
        'Mengubah pengguna "' . $raw['name'] . '" (@' . User::normalizeUsername((string) $raw['username']) . ')'
            . ($changes !== [] ? ': ' . implode(', ', $changes) : '.'),
        'user',
        $id
    );
    flash('success', 'Data pengguna "' . $raw['name'] . '" berhasil diperbarui.');
    redirect('admin/pengguna/index.php');
}

if ($action === 'toggle') {
    $id = post_int('id');
    $user = $id > 0 ? User::findById($id) : null;
    if ($user === null) {
        abort_404('Data pengguna tidak ditemukan.');
    }

    if ($me !== null && $id === (int) $me['id']) {
        flash('danger', 'Anda tidak dapat menonaktifkan akun sendiri.');
        redirect('admin/pengguna/index.php');
    }

    $activate = (int) $user['is_active'] !== 1;
    if (!$activate && (string) $user['role'] === User::ROLE_ADMIN && User::activeAdminCount($id) === 0) {
        flash('danger', 'Minimal harus ada satu administrator aktif.');
        redirect('admin/pengguna/index.php');
    }

    User::setActive($id, $activate);

    log_activity(
        $activate ? 'user_aktif' : 'user_nonaktif',
        ($activate ? 'Mengaktifkan' : 'Menonaktifkan') . ' pengguna "' . $user['name']
            . '" (@' . $user['username'] . ').',
        'user',
        $id
    );
    flash('success', 'Pengguna "' . $user['name'] . '" berhasil ' . ($activate ? 'diaktifkan' : 'dinonaktifkan') . '.');
    redirect('admin/pengguna/index.php');
}

if ($action === 'password') {
    $id = post_int('id');
    $user = $id > 0 ? User::findById($id) : null;
    if ($user === null) {
        abort_404('Data pengguna tidak ditemukan.');
    }

    $password = is_string($_POST['password'] ?? null) ? (string) $_POST['password'] : '';
    $confirm = is_string($_POST['password_confirm'] ?? null) ? (string) $_POST['password_confirm'] : '';

    $errors = [];
    foreach (User::passwordErrors($password) as $message) {
        $errors['password'] = $message;
        break;
    }
    if (!isset($errors['password']) && $password !== $confirm) {
        $errors['password_confirm'] = 'Konfirmasi password tidak sama.';
    }

    if ($errors !== []) {
        form_fail($errors, [], 'admin/pengguna/password.php?id=' . $id);
    }

    User::setPassword($id, $password);

    // Password tidak pernah ditulis ke log aktivitas.
    log_activity(
        'user_password',
        'Mengatur ulang password pengguna "' . $user['name'] . '" (@' . $user['username'] . ').',
        'user',
        $id
    );
    flash('success', 'Password pengguna "' . $user['name'] . '" berhasil diperbarui.');
    redirect('admin/pengguna/index.php');
}

abort_404('Aksi yang diminta tidak dikenali.');
