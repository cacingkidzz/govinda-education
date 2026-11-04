<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_admin();

$id = get_int('id');
$user = $id > 0 ? User::findById($id) : null;
if ($user === null) {
    abort_404('Data pengguna tidak ditemukan.');
}

$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];

$formMode = 'edit';

$pageTitle = 'Edit Pengguna';
$pageSubtitle = 'Perbarui identitas, peran, dan status akun pengguna.';
$breadcrumbs = [
    ['label' => 'Manajemen Pengguna', 'url' => url('admin/pengguna/index.php')],
    ['label' => 'Edit Pengguna'],
];
$activeMenu = 'pengguna';

require_once ROOT_PATH . '/includes/header.php';
require __DIR__ . '/_form.php';
require_once ROOT_PATH . '/includes/footer.php';
