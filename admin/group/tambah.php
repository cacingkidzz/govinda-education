<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_admin();

$state = pull_form_state();

$formMode = 'create';
$group = [];
$errors = $state['errors'];
$old = $state['old'];

$pageTitle = 'Tambah Group Belajar';
$pageSubtitle = 'Buat group private atau group belajar. Mata pelajaran dan guru ditambahkan setelah group tersimpan.';
$breadcrumbs = [
    ['label' => 'Group Belajar', 'url' => url('admin/group/index.php')],
    ['label' => 'Tambah Group'],
];
$activeMenu = 'group';

require_once ROOT_PATH . '/includes/header.php';
?>

<?php require __DIR__ . '/_form.php'; ?>

<?php require_once ROOT_PATH . '/includes/footer.php';
