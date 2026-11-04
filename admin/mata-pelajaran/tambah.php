<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();

$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];

$subject = ['is_active' => 1];
$formMode = 'create';

$pageTitle = 'Tambah Mata Pelajaran';
$pageSubtitle = 'Tambahkan mata pelajaran yang akan digunakan pada group belajar.';
$breadcrumbs = [
    ['label' => 'Mata Pelajaran', 'url' => url('admin/mata-pelajaran/index.php')],
    ['label' => 'Tambah'],
];
$activeMenu = 'mata-pelajaran';

require_once ROOT_PATH . '/includes/header.php';
require __DIR__ . '/_form.php';
require_once ROOT_PATH . '/includes/footer.php';
