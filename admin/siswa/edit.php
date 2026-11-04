<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$id = get_int('id');
$student = $id > 0 ? Student::find($id) : null;
if ($student === null) {
    abort_404('Data siswa yang Anda cari tidak ditemukan atau telah dihapus.');
}

$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];
$formMode = 'edit';

$pageTitle = 'Edit Siswa';
$pageSubtitle = 'Perbarui profil ' . $student['name'] . ' tanpa mengubah riwayat group dan tagihan.';
$breadcrumbs = [
    ['label' => 'Data Siswa', 'url' => url('admin/siswa/index.php')],
    ['label' => (string) $student['name'], 'url' => url('admin/siswa/detail.php?id=' . (int) $student['id'])],
    ['label' => 'Edit'],
];
$activeMenu = 'siswa';

require_once ROOT_PATH . '/includes/header.php';
require __DIR__ . '/_form.php';
require_once ROOT_PATH . '/includes/footer.php';
