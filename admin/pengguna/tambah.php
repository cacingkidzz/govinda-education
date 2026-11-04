<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_admin();

$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];

$formMode = 'create';
$user = [];

$pageTitle = 'Tambah Pengguna';
$pageSubtitle = 'Buat akun admin atau petugas baru untuk masuk ke aplikasi.';
$breadcrumbs = [
    ['label' => 'Manajemen Pengguna', 'url' => url('admin/pengguna/index.php')],
    ['label' => 'Tambah Pengguna'],
];
$activeMenu = 'pengguna';

require_once ROOT_PATH . '/includes/header.php';
require __DIR__ . '/_form.php';
require_once ROOT_PATH . '/includes/footer.php';
