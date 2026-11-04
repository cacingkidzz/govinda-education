<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();

$id = get_int('id');
$teacher = $id > 0 ? Teacher::find($id) : null;
if ($teacher === null) {
    abort_404('Data guru yang Anda cari tidak ditemukan.');
}

$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];
$formMode = 'edit';

$pageTitle = 'Edit Guru';
$pageSubtitle = 'Perbarui profil ' . $teacher['name'] . ' tanpa mengubah riwayat mengajar.';
$breadcrumbs = [
    ['label' => 'Data Guru', 'url' => url('admin/guru/index.php')],
    ['label' => (string) $teacher['name'], 'url' => url('admin/guru/detail.php?id=' . (int) $teacher['id'])],
    ['label' => 'Edit'],
];
$activeMenu = 'guru';
$pageStyles = ['vendor/select2/select2.min.css', 'vendor/select2/select2-bootstrap-5-theme.min.css'];
$pageScripts = ['vendor/select2/select2.min.js'];

require_once ROOT_PATH . '/includes/header.php';
require __DIR__ . '/_form.php';
require_once ROOT_PATH . '/includes/footer.php';
