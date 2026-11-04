<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_admin();

$id = get_int('id');
$relation = $id > 0 ? GroupSubject::find($id) : null;
if ($relation === null) {
    abort_404('Relasi mata pelajaran tidak ditemukan.');
}

$subjects = Subject::all(['status' => '1']);
$teachers = Teacher::all(['status' => '1']);

// Mapel/guru lama yang kini nonaktif tetap ditampilkan agar relasi bisa diperbaiki.
if ((int) $relation['subject_active'] !== 1) {
    array_unshift($subjects, [
        'id'        => (int) $relation['subject_id'],
        'code'      => (string) $relation['subject_code'],
        'name'      => (string) $relation['subject_name'] . ' (nonaktif)',
        'is_active' => 0,
    ]);
}
if ((int) $relation['teacher_active'] !== 1) {
    array_unshift($teachers, [
        'id'        => (int) $relation['teacher_id'],
        'code'      => (string) $relation['teacher_code'],
        'name'      => (string) $relation['teacher_name'] . ' (nonaktif)',
        'is_active' => 0,
    ]);
}

$state = pull_form_state();
$formMode = 'edit';
$errors = $state['errors'];
$old = $state['old'];
$groups = [];

$pageTitle = 'Edit Mata Pelajaran Group';
$pageSubtitle = 'Perubahan guru diperiksa ulang terhadap jadwal aktif relasi ini.';
$breadcrumbs = [
    ['label' => 'Group Belajar', 'url' => url('admin/group/index.php')],
    ['label' => (string) $relation['group_name'], 'url' => url('admin/group/detail.php?id=' . (int) $relation['group_id'])],
    ['label' => 'Mata Pelajaran & Guru', 'url' => url('admin/group/mapel.php?group_id=' . (int) $relation['group_id'])],
    ['label' => 'Edit'],
];
$activeMenu = 'group-mapel';
$pageStyles = ['vendor/select2/select2.min.css', 'vendor/select2/select2-bootstrap-5-theme.min.css'];
$pageScripts = ['vendor/select2/select2.min.js'];

require_once ROOT_PATH . '/includes/header.php';
?>

<?php require __DIR__ . '/_mapel_form.php'; ?>

<?php require_once ROOT_PATH . '/includes/footer.php';
