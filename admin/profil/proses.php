<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
require_login();
csrf_require();
require_admin();

$action = post_string('action');

/**
 * Ambil input identitas dari formulir.
 *
 * @return array<string, string>
 */
$collect = static function (): array {
    return [
        'app_name'        => post_string('app_name'),
        'app_tagline'     => post_string('app_tagline'),
        'app_description' => post_string('app_description'),
        'app_address'     => post_string('app_address'),
        'app_phone'       => post_string('app_phone'),
        'app_email'       => post_string('app_email'),
        'app_website'     => post_string('app_website'),
    ];
};

if ($action === 'reset_logo') {
    $currentLogo = Profile::normalizeLogoPath(Profile::get('app_logo'));

    try {
        Profile::save(['app_logo' => '']);
    } catch (Throwable $exception) {
        @file_put_contents(
            LOG_PATH . '/app.log',
            '[' . date('Y-m-d H:i:s') . '] PROFILE LOGO RESET ERROR: ' . $exception->getMessage() . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
        flash('danger', 'Logo gagal direset. Silakan coba lagi.');
        redirect('admin/profil/index.php');
    }

    Upload::remove($currentLogo);
    log_activity(
        'profil_logo_reset',
        'Mengembalikan logo bimbel ke logo bawaan aplikasi.',
        'profile',
        1
    );
    flash('success', 'Logo kustom telah dihapus. Aplikasi kembali memakai logo bawaan.');
    redirect('admin/profil/index.php');
}

if ($action !== 'update') {
    abort_404('Aksi profil tidak dikenali.');
}

$raw = $collect();
$errors = Profile::validate($raw);

$removeLogo = post_string('remove_logo') === '1';
$existingLogo = Profile::normalizeLogoPath(Profile::get('app_logo'));
if ($existingLogo !== '' && !is_file(UPLOAD_PATH . '/' . $existingLogo)) {
    $existingLogo = '';
}

$uploadedLogo = null;
$logoFile = $_FILES['logo'] ?? null;
if (is_array($logoFile) && (int) ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $result = Upload::image($logoFile, 'profil', 'logo');
    if ($result['ok']) {
        $uploadedLogo = $result['path'];
    } else {
        $errors['logo'] = $result['error'];
    }
}

if ($errors !== []) {
    if ($uploadedLogo !== null) {
        Upload::remove($uploadedLogo);
    }
    $old = $raw;
    $old['remove_logo'] = $removeLogo ? '1' : '0';
    form_fail($errors, $old, 'admin/profil/edit.php');
}

$finalLogo = $existingLogo;
if ($uploadedLogo !== null) {
    $finalLogo = $uploadedLogo;
} elseif ($removeLogo) {
    $finalLogo = '';
}

$data = [
    'app_name'        => $raw['app_name'],
    'app_tagline'     => $raw['app_tagline'],
    'app_description' => $raw['app_description'],
    'app_address'     => $raw['app_address'],
    'app_phone'       => $raw['app_phone'] !== '' ? normalize_phone($raw['app_phone']) : '',
    'app_email'       => $raw['app_email'] !== '' ? mb_strtolower($raw['app_email']) : '',
    'app_website'     => Profile::normalizeWebsite($raw['app_website']),
    'app_logo'        => $finalLogo,
];

try {
    Profile::save($data);
} catch (Throwable $exception) {
    if ($uploadedLogo !== null) {
        Upload::remove($uploadedLogo);
    }
    @file_put_contents(
        LOG_PATH . '/app.log',
        '[' . date('Y-m-d H:i:s') . '] PROFILE UPDATE ERROR: ' . $exception->getMessage() . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
    flash('danger', 'Profil bimbel gagal disimpan. Silakan coba lagi.');
    redirect('admin/profil/edit.php');
}

// File logo lama baru dihapus setelah setting berhasil tersimpan.
if ($existingLogo !== '' && $existingLogo !== $finalLogo) {
    Upload::remove($existingLogo);
}

log_activity(
    'profil_edit',
    'Memperbarui profil bimbel "' . $data['app_name'] . '".',
    'profile',
    1
);

if ($uploadedLogo !== null) {
    log_activity(
        'profil_logo',
        'Mengganti logo bimbel "' . $data['app_name'] . '".',
        'profile',
        1
    );
} elseif ($removeLogo && $existingLogo !== '') {
    log_activity(
        'profil_logo_reset',
        'Menghapus logo kustom bimbel "' . $data['app_name'] . '".',
        'profile',
        1
    );
}

flash('success', 'Profil bimbel berhasil diperbarui. Perubahan langsung tampil di seluruh aplikasi.');
redirect('admin/profil/index.php');
