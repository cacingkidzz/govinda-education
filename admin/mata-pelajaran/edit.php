<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();

$id = get_int('id');
$subject = $id > 0 ? Subject::find($id) : null;
if ($subject === null) {
    abort_404('Mata pelajaran yang Anda cari tidak ditemukan.');
}

$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];
$formMode = 'edit';

$pageTitle = 'Edit Mata Pelajaran';
$pageSubtitle = 'Perbarui informasi ' . $subject['name'] . ' tanpa mengubah riwayat group.';
$breadcrumbs = [
    ['label' => 'Mata Pelajaran', 'url' => url('admin/mata-pelajaran/index.php')],
    ['label' => (string) $subject['name'], 'url' => url('admin/mata-pelajaran/detail.php?id=' . (int) $subject['id'])],
    ['label' => 'Edit'],
];
$activeMenu = 'mata-pelajaran';

require_once ROOT_PATH . '/includes/header.php';
require __DIR__ . '/_form.php';
require_once ROOT_PATH . '/includes/footer.php';
