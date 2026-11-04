<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_admin();

$state = pull_form_state();
$formMode = 'create';
$rule = [];
$errors = $state['errors'];
$old = $state['old'];

$pageTitle = 'Tambah Tarif Paket';
$pageSubtitle = 'Tentukan nominal satu paket 8 pertemuan berdasarkan jenis kelompok dan jumlah anggota.';
$breadcrumbs = [
    ['label' => 'Tarif Paket', 'url' => url('admin/tarif/index.php')],
    ['label' => 'Tambah Tarif'],
];
$activeMenu = 'tarif';

require_once ROOT_PATH . '/includes/header.php';
?>

<?php require __DIR__ . '/_form.php'; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
