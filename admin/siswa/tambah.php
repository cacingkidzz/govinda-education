<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];

$student = [
    'gender'    => 'L',
    'join_date' => date('Y-m-d'),
    'is_active' => 1,
];
$formMode = 'create';

$pageTitle = 'Tambah Siswa';
$pageSubtitle = 'Lengkapi identitas siswa, kontak orang tua, dan data sekolah.';
$breadcrumbs = [
    ['label' => 'Data Siswa', 'url' => url('admin/siswa/index.php')],
    ['label' => 'Tambah Siswa'],
];
$activeMenu = 'siswa';

require_once ROOT_PATH . '/includes/header.php';
require __DIR__ . '/_form.php';
require_once ROOT_PATH . '/includes/footer.php';
