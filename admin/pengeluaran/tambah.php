<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];

$formMode = 'create';
$expense = [];

$pageTitle = 'Catat Pengeluaran';
$pageSubtitle = 'Tambahkan biaya operasional bimbel yang benar-benar dikeluarkan.';
$breadcrumbs = [
    ['label' => 'Pengeluaran', 'url' => url('admin/pengeluaran/index.php')],
    ['label' => 'Catat Pengeluaran'],
];
$activeMenu = 'pengeluaran';

require_once ROOT_PATH . '/includes/header.php';
require __DIR__ . '/_form.php';
require_once ROOT_PATH . '/includes/footer.php';
