<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_admin();

$relations = GroupSubject::activeOptions();
$state = pull_form_state();

$formMode = 'create';
$schedule = [];
$errors = $state['errors'];
$old = $state['old'];

// Preselect group/mata pelajaran dari tautan detail group.
$preselectGroup = get_int('group_id');
$preselectRelation = get_int('group_subject_id');
if (!array_key_exists('group_subject_id', $old)) {
    if ($preselectRelation > 0) {
        $old['group_subject_id'] = (string) $preselectRelation;
    } elseif ($preselectGroup > 0) {
        foreach ($relations as $relation) {
            if ((int) $relation['group_id'] === $preselectGroup) {
                $old['group_subject_id'] = (string) $relation['id'];
                break;
            }
        }
    }
}
$returnTo = get_string('return') === 'group' ? 'group' : '';
$returnId = $preselectGroup;

$pageTitle = 'Tambah Jadwal Belajar';
$pageSubtitle = 'Tambahkan jadwal belajar untuk mata pelajaran dalam group dan hindari bentrok guru, group, serta ruangan.';
$breadcrumbs = [
    ['label' => 'Jadwal Belajar', 'url' => url('admin/jadwal/index.php')],
    ['label' => 'Tambah Jadwal'],
];
$activeMenu = 'jadwal';
$pageStyles = ['vendor/select2/select2.min.css', 'vendor/select2/select2-bootstrap-5-theme.min.css'];
$pageScripts = ['vendor/select2/select2.min.js'];

require_once ROOT_PATH . '/includes/header.php';
?>

<?php if ($relations === []): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <strong>Belum ada mata pelajaran aktif pada group.</strong>
            Jadwal melekat pada relasi group–mata pelajaran. Tambahkan mata pelajaran dan guru terlebih dahulu.
            <?php if (is_admin()): ?>
                <a href="<?= e(url('admin/group/mapel.php')) ?>" class="alert-link">Kelola mata pelajaran</a>.
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <?php require __DIR__ . '/_form.php'; ?>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php';
