<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_admin();

$groupId = get_int('group_id');
$groups = Group::activeOptions();
if ($groupId > 0) {
    $found = false;
    foreach ($groups as $group) {
        if ((int) $group['id'] === $groupId) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        abort_404('Group aktif yang Anda pilih tidak ditemukan.');
    }
}

$state = pull_form_state();
$formMode = 'create';
$relation = ['group_id' => $groupId];
$errors = $state['errors'];
$old = $state['old'];
$subjects = Subject::all(['status' => '1']);
$teachers = Teacher::all(['status' => '1']);

$pageTitle = 'Tambah Mata Pelajaran Group';
$pageSubtitle = 'Hubungkan satu mata pelajaran dengan guru pengampunya pada group.';
$breadcrumbs = [
    ['label' => 'Group Belajar', 'url' => url('admin/group/index.php')],
    ['label' => 'Mata Pelajaran & Guru', 'url' => url('admin/group/mapel.php')],
    ['label' => 'Tambah Relasi'],
];
$activeMenu = 'group-mapel';
$pageStyles = ['vendor/select2/select2.min.css', 'vendor/select2/select2-bootstrap-5-theme.min.css'];
$pageScripts = ['vendor/select2/select2.min.js'];

require_once ROOT_PATH . '/includes/header.php';
?>

<?php if ($groups === []): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <strong>Belum ada group aktif.</strong>
            Buat group terlebih dahulu sebelum menambahkan mata pelajaran.
            <a href="<?= e(url('admin/group/tambah.php')) ?>" class="alert-link">Tambah group</a>.
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_mapel_form.php'; ?>

<?php require_once ROOT_PATH . '/includes/footer.php';
