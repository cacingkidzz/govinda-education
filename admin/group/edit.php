<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_admin();

$id = get_int('id');
$group = $id > 0 ? Group::find($id) : null;
if ($group === null) {
    abort_404('Data group yang Anda cari tidak ditemukan.');
}

$state = pull_form_state();
$formMode = 'edit';
$errors = $state['errors'];
$old = $state['old'];

$pageTitle = 'Edit Group Belajar';
$pageSubtitle = 'Perbarui data group tanpa mengubah riwayat keanggotaan maupun invoice lama.';
$breadcrumbs = [
    ['label' => 'Group Belajar', 'url' => url('admin/group/index.php')],
    ['label' => (string) $group['name'], 'url' => url('admin/group/detail.php?id=' . $id)],
    ['label' => 'Edit'],
];
$activeMenu = 'group';

require_once ROOT_PATH . '/includes/header.php';
?>

<?php require __DIR__ . '/_form.php'; ?>

<div class="master-note">
    <i class="bi bi-shield-lock"></i>
    <span>Perubahan mata pelajaran atau guru dilakukan pada halaman Mata Pelajaran dan Guru.
        Perubahan tersebut tidak mengubah snapshot invoice yang sudah diterbitkan, dan riwayat
        keanggotaan siswa tetap tersimpan.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php';
