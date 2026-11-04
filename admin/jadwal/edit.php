<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_admin();

$id = get_int('id');
$schedule = $id > 0 ? Schedule::find($id) : null;
if ($schedule === null) {
    abort_404('Data jadwal yang Anda cari tidak ditemukan.');
}

$relations = GroupSubject::activeOptions();
$relationIds = array_map(static fn (array $relation): int => (int) $relation['id'], $relations);
if (!in_array((int) $schedule['group_subject_id'], $relationIds, true)) {
    // Relasi lama yang nonaktif tetap ditampilkan agar jadwal bisa diperbaiki/dinonaktifkan.
    array_unshift($relations, [
        'id'           => (int) $schedule['group_subject_id'],
        'group_id'     => (int) $schedule['group_id'],
        'group_code'   => (string) $schedule['group_code'],
        'group_name'   => (string) $schedule['group_name'] . ' (nonaktif)',
        'subject_name' => (string) $schedule['subject_name'],
        'teacher_name' => (string) $schedule['teacher_name'],
    ]);
}

$state = pull_form_state();
$formMode = 'edit';
$errors = $state['errors'];
$old = $state['old'];
$returnTo = get_string('return') === 'group' ? 'group' : '';
$returnId = get_int('return_id') ?: (int) $schedule['group_id'];

$pageTitle = 'Edit Jadwal Belajar';
$pageSubtitle = 'Perbarui jadwal dengan pemeriksaan bentrok guru, group, dan ruangan. Jadwal fleksibel dan boleh diubah kapan saja.';
$breadcrumbs = [
    ['label' => 'Jadwal Belajar', 'url' => url('admin/jadwal/index.php')],
    ['label' => (string) $schedule['group_name'], 'url' => url('admin/jadwal/detail.php?id=' . $id)],
    ['label' => 'Edit'],
];
$activeMenu = 'jadwal';
$pageStyles = ['vendor/select2/select2.min.css', 'vendor/select2/select2-bootstrap-5-theme.min.css'];
$pageScripts = ['vendor/select2/select2.min.js'];

require_once ROOT_PATH . '/includes/header.php';
?>

<?php require __DIR__ . '/_form.php'; ?>

<?php require_once ROOT_PATH . '/includes/footer.php';
