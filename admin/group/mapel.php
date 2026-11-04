<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$groupId = get_string('group_id');
$groupId = ctype_digit($groupId) ? $groupId : '';
$subjectId = get_string('subject_id');
$subjectId = ctype_digit($subjectId) ? $subjectId : '';
$teacherId = get_string('teacher_id');
$teacherId = ctype_digit($teacherId) ? $teacherId : '';
$status = get_string('status');
$status = in_array($status, ['1', '0'], true) ? $status : '';
$keyword = trim(get_string('q'));

$filters = [
    'group_id'   => $groupId,
    'subject_id' => $subjectId,
    'teacher_id' => $teacherId,
    'status'     => $status,
    'q'          => $keyword,
];

$relations = GroupSubject::all($filters);
$groups = Group::all();
$subjects = Subject::all();
$teachers = Teacher::all();
$isAdmin = is_admin();
$hasFilter = $groupId !== '' || $subjectId !== '' || $teacherId !== '' || $status !== '' || $keyword !== '';

$filteredGroup = null;
if ($groupId !== '') {
    $filteredGroup = Group::find((int) $groupId);
}

$pageTitle = 'Mata Pelajaran dan Guru';
$pageSubtitle = 'Kelola relasi mata pelajaran dan guru pengampu untuk setiap group.';
$breadcrumbs = [['label' => 'Group Belajar', 'url' => url('admin/group/index.php')], ['label' => 'Mata Pelajaran & Guru']];
$activeMenu = 'group-mapel';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<?php if ($filteredGroup !== null): ?>
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="cell-sub">Group terpilih</div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <a href="<?= e(url('admin/group/detail.php?id=' . (int) $filteredGroup['id'])) ?>"
                       class="cell-title link-dark text-decoration-none">
                        <?= e($filteredGroup['name']) ?>
                    </a>
                    <span class="badge text-bg-light font-monospace"><?= e($filteredGroup['code']) ?></span>
                    <?= group_type_badge((string) $filteredGroup['group_type']) ?>
                    <?= active_badge((int) $filteredGroup['is_active'] === 1) ?>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= e(url('admin/group/detail.php?id=' . (int) $filteredGroup['id'] . '&tab=mapel')) ?>"
                   class="btn btn-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Kembali ke Detail
                </a>
                <?php if ($isAdmin && (int) $filteredGroup['is_active'] === 1): ?>
                    <a href="<?= e(url('admin/group/mapel-tambah.php?group_id=' . (int) $filteredGroup['id'])) ?>"
                       class="btn btn-primary btn-sm">
                        <i class="bi bi-journal-plus me-1"></i>Tambah Mata Pelajaran
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/group/mapel.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-3 col-md-4">
                <label for="group_id" class="form-label">Group</label>
                <select class="form-select" id="group_id" name="group_id">
                    <option value="">Semua group</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>" <?= $groupId === (string) $group['id'] ? 'selected' : '' ?>>
                            <?= e($group['name']) ?><?= (int) $group['is_active'] === 1 ? '' : ' (nonaktif)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-3 col-md-4">
                <label for="subject_id" class="form-label">Mata pelajaran</label>
                <select class="form-select" id="subject_id" name="subject_id">
                    <option value="">Semua mata pelajaran</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= (int) $subject['id'] ?>" <?= $subjectId === (string) $subject['id'] ? 'selected' : '' ?>>
                            <?= e($subject['name']) ?><?= (int) $subject['is_active'] === 1 ? '' : ' (nonaktif)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-3 col-md-4">
                <label for="teacher_id" class="form-label">Guru</label>
                <select class="form-select" id="teacher_id" name="teacher_id">
                    <option value="">Semua guru</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?= (int) $teacher['id'] ?>" <?= $teacherId === (string) $teacher['id'] ? 'selected' : '' ?>>
                            <?= e($teacher['name']) ?><?= (int) $teacher['is_active'] === 1 ? '' : ' (nonaktif)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-1 col-md-4">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua</option>
                    <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Aktif</option>
                    <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-8 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/group/mapel.php')) ?>" class="btn btn-light" title="Reset filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Relasi Mata Pelajaran &amp; Guru</h2>
            <div class="card-subtitle">
                <?= e(number_format(count($relations), 0, ',', '.')) ?> relasi<?= $hasFilter ? ' sesuai filter' : '' ?>.
                Satu mata pelajaran hanya boleh sekali dalam satu group.
            </div>
        </div>
        <?php if ($isAdmin): ?>
            <a href="<?= e(url('admin/group/mapel-tambah.php' . ($groupId !== '' ? '?group_id=' . $groupId : ''))) ?>"
               class="btn btn-primary">
                <i class="bi bi-journal-plus me-1"></i>Tambah Relasi
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if ($relations === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-journal-x"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada relasi yang sesuai filter' : 'Belum ada relasi mata pelajaran' ?>
                </div>
                <p class="empty-state-text">
                    <?= $hasFilter
                        ? 'Coba ubah atau reset filter untuk melihat relasi lainnya.'
                        : 'Tambahkan mata pelajaran beserta guru pengampu untuk setiap group.' ?>
                </p>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/group/mapel.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                    </a>
                <?php elseif ($isAdmin): ?>
                    <a href="<?= e(url('admin/group/mapel-tambah.php')) ?>" class="btn btn-primary">
                        <i class="bi bi-journal-plus me-1"></i>Tambah Relasi
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width: 40px;">No.</th>
                        <th>Group</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru Pengampu</th>
                        <th class="text-center">Jadwal</th>
                        <th>Status</th>
                        <th class="no-sort" style="width: 150px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($relations as $index => $relation): ?>
                        <?php
                        $groupUrl = url('admin/group/detail.php?id=' . (int) $relation['group_id']);
                        $subjectOk = (int) $relation['subject_active'] === 1;
                        $teacherOk = (int) $relation['teacher_active'] === 1;
                        ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td>
                                <a href="<?= e($groupUrl) ?>" class="cell-title link-dark text-decoration-none">
                                    <?= e($relation['group_name']) ?>
                                </a>
                                <span class="cell-sub font-monospace"><?= e($relation['group_code']) ?></span>
                            </td>
                            <td>
                                <span class="cell-title"><?= e($relation['subject_name']) ?></span>
                                <span class="cell-sub font-monospace"><?= e($relation['subject_code']) ?></span>
                                <?php if (!$subjectOk): ?>
                                    <span class="badge text-bg-secondary ms-1">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= e(url('admin/guru/detail.php?id=' . (int) $relation['teacher_id'])) ?>"
                                   class="cell-title link-dark text-decoration-none">
                                    <?= e($relation['teacher_name']) ?>
                                </a>
                                <span class="cell-sub font-monospace"><?= e($relation['teacher_code']) ?></span>
                                <?php if (!$teacherOk): ?>
                                    <span class="badge text-bg-secondary ms-1">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="<?= e(url('admin/jadwal/index.php?group_subject_id=' . (int) $relation['id'])) ?>"
                                   class="badge text-bg-light text-decoration-none">
                                    <?= e((string) ($relation['schedule_count'] ?? 0)) ?> jadwal
                                </a>
                            </td>
                            <td><?= active_badge((int) $relation['is_active'] === 1) ?></td>
                            <td>
                                <div class="table-actions">
                                    <?php if ($isAdmin): ?>
                                        <a href="<?= e(url('admin/group/mapel-edit.php?id=' . (int) $relation['id'])) ?>"
                                           class="btn btn-warning btn-sm" title="Edit relasi">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form method="post" action="<?= e(url('admin/group/mapel-proses.php')) ?>"
                                              data-confirm="<?= (int) $relation['is_active'] === 1
                                                  ? 'Relasi mata pelajaran akan dinonaktifkan. Jadwal relasi ini tidak dihitung sampai diaktifkan kembali.'
                                                  : 'Relasi akan diaktifkan kembali setelah pemeriksaan bentrok jadwal.' ?>"
                                              data-confirm-title="Ubah status relasi"
                                              data-confirm-button="<?= (int) $relation['is_active'] === 1 ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                                              data-confirm-icon="warning" data-submit-once>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $relation['id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-sm <?= (int) $relation['is_active'] === 1 ? 'btn-danger' : 'btn-success' ?>"
                                                    title="<?= (int) $relation['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                                <i class="bi <?= (int) $relation['is_active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                            </button>
                                        </form>
                                        <?php if ((int) ($relation['schedule_count'] ?? 0) === 0): ?>
                                            <form method="post" action="<?= e(url('admin/group/mapel-proses.php')) ?>"
                                                  data-confirm="Relasi mata pelajaran ini belum memiliki jadwal dan akan dihapus permanen."
                                                  data-confirm-title="Hapus relasi mata pelajaran"
                                                  data-confirm-button="Ya, hapus" data-confirm-icon="warning" data-submit-once>
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $relation['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus relasi">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="master-note">
    <i class="bi bi-shield-check"></i>
    <span>Relasi yang sudah memiliki riwayat jadwal tidak dihapus permanen.
        Gunakan status nonaktif agar riwayat tetap utuh.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php';
