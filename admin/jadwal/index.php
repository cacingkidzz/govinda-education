<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$groupId = get_string('group_id');
$groupId = ctype_digit($groupId) ? $groupId : '';
$groupSubjectId = get_string('group_subject_id');
$groupSubjectId = ctype_digit($groupSubjectId) ? $groupSubjectId : '';
$teacherId = get_string('teacher_id');
$teacherId = ctype_digit($teacherId) ? $teacherId : '';
$day = get_string('day');
$day = ctype_digit($day) && (int) $day >= 1 && (int) $day <= 7 ? $day : '';
$status = get_string('status');
$status = in_array($status, ['1', '0'], true) ? $status : '';

$filters = [
    'group_id'         => $groupId,
    'group_subject_id' => $groupSubjectId,
    'teacher_id'       => $teacherId,
    'day'              => $day,
    'status'           => $status,
];

$schedules = Schedule::all($filters);
$groups = Group::all();
$teachers = Teacher::all();
$relations = GroupSubject::all();
$hasFilter = $groupId !== '' || $groupSubjectId !== '' || $teacherId !== '' || $day !== '' || $status !== '';
$isAdmin = is_admin();

$totalActive = 0;
foreach ($schedules as $row) {
    if ((int) $row['is_active'] === 1) {
        $totalActive++;
    }
}

$pageTitle = 'Jadwal Belajar';
$pageSubtitle = 'Kelola jadwal mingguan group dengan pemeriksaan bentrok guru, group, dan ruangan.';
$breadcrumbs = [['label' => 'Jadwal Belajar']];
$activeMenu = 'jadwal';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/jadwal/index.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-3 col-md-4">
                <label for="group_id" class="form-label">Group</label>
                <select class="form-select" id="group_id" name="group_id">
                    <option value="">Semua group</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>" <?= $groupId === (string) $group['id'] ? 'selected' : '' ?>>
                            <?= e($group['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-3 col-md-4">
                <label for="group_subject_id" class="form-label">Mata pelajaran</label>
                <select class="form-select" id="group_subject_id" name="group_subject_id">
                    <option value="">Semua mata pelajaran</option>
                    <?php foreach ($relations as $relation): ?>
                        <option value="<?= (int) $relation['id'] ?>"
                            <?= $groupSubjectId === (string) $relation['id'] ? 'selected' : '' ?>>
                            <?= e($relation['group_name']) ?> — <?= e($relation['subject_name']) ?>
                            (<?= e($relation['teacher_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="teacher_id" class="form-label">Guru</label>
                <select class="form-select" id="teacher_id" name="teacher_id">
                    <option value="">Semua guru</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?= (int) $teacher['id'] ?>" <?= $teacherId === (string) $teacher['id'] ? 'selected' : '' ?>>
                            <?= e($teacher['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="day" class="form-label">Hari</label>
                <select class="form-select" id="day" name="day">
                    <option value="">Semua hari</option>
                    <?php for ($d = 1; $d <= 7; $d++): ?>
                        <option value="<?= $d ?>" <?= $day === (string) $d ? 'selected' : '' ?>>
                            <?= e(hari_id($d)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua status</option>
                    <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Aktif</option>
                    <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/jadwal/index.php')) ?>" class="btn btn-light"
                       title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Daftar Jadwal</h2>
            <div class="card-subtitle">
                <?= e(number_format(count($schedules), 0, ',', '.')) ?> jadwal
                &middot; <?= e(number_format($totalActive, 0, ',', '.')) ?> aktif
                <?= $hasFilter ? ' &middot; sesuai filter' : '' ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e(url_query('admin/jadwal/mingguan.php', [
                'group_id' => $groupId,
                'group_subject_id' => $groupSubjectId,
                'teacher_id' => $teacherId,
            ])) ?>" class="btn btn-light">
                <i class="bi bi-grid-3x3-gap me-1"></i>Tampilan Mingguan
            </a>
            <?php if ($isAdmin): ?>
                <a href="<?= e(url('admin/jadwal/tambah.php')) ?>" class="btn btn-primary">
                    <i class="bi bi-calendar-plus me-1"></i>Tambah Jadwal
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($schedules === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada jadwal yang sesuai filter' : 'Belum ada jadwal belajar' ?>
                </div>
                <p class="empty-state-text">
                    <?= $hasFilter
                        ? 'Coba ubah atau reset filter untuk melihat jadwal lainnya.'
                        : 'Tambahkan jadwal pertama untuk group yang sudah dibuat.' ?>
                </p>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/jadwal/index.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                    </a>
                <?php elseif ($isAdmin): ?>
                    <a href="<?= e(url('admin/jadwal/tambah.php')) ?>" class="btn btn-primary">
                        <i class="bi bi-calendar-plus me-1"></i>Tambah Jadwal
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
                        <th>Jenis</th>
                        <th>Guru / Mapel</th>
                        <th>Hari</th>
                        <th>Jam</th>
                        <th>Ruangan</th>
                        <th>Keterangan</th>
                        <th class="no-sort">Status</th>
                        <th class="no-sort" style="width: 150px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($schedules as $index => $schedule): ?>
                        <?php $detailUrl = url('admin/jadwal/detail.php?id=' . (int) $schedule['id']); ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td>
                                <a href="<?= e(url('admin/group/detail.php?id=' . (int) $schedule['group_id'] . '&tab=jadwal')) ?>"
                                   class="cell-title link-dark text-decoration-none">
                                    <?= e($schedule['group_name']) ?>
                                </a>
                                <span class="cell-sub font-monospace"><?= e($schedule['group_code']) ?></span>
                            </td>
                            <td><?= group_type_badge((string) $schedule['group_type']) ?></td>
                            <td>
                                <span class="cell-title"><?= e($schedule['teacher_name']) ?></span>
                                <span class="cell-sub"><?= e($schedule['subject_name']) ?></span>
                            </td>
                            <td><span class="cell-title"><?= e(hari_id((int) $schedule['day_of_week'])) ?></span></td>
                            <td class="text-nowrap">
                                <?= e(time_label((string) $schedule['start_time'])) ?>–<?= e(time_label((string) $schedule['end_time'])) ?>
                            </td>
                            <td><?= e($schedule['room'] ?: '-') ?></td>
                            <td><span class="cell-sub d-block" style="max-width: 220px;"><?= e($schedule['notes'] ?: '-') ?></span></td>
                            <td><?= active_badge((int) $schedule['is_active'] === 1) ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e($detailUrl) ?>" class="btn btn-info btn-sm" title="Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($isAdmin): ?>
                                        <a href="<?= e(url('admin/jadwal/edit.php?id=' . (int) $schedule['id'] . '&return=index')) ?>"
                                           class="btn btn-warning btn-sm" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form method="post" action="<?= e(url('admin/jadwal/proses.php')) ?>"
                                              data-confirm="<?= (int) $schedule['is_active'] === 1
                                                  ? 'Jadwal akan dinonaktifkan dan tidak dihitung sebagai jadwal aktif.'
                                                  : 'Jadwal akan diaktifkan kembali setelah pemeriksaan bentrok.' ?>"
                                              data-confirm-title="Ubah status jadwal"
                                              data-confirm-button="<?= (int) $schedule['is_active'] === 1 ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                                              data-confirm-icon="warning" data-submit-once>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $schedule['id'] ?>">
                                            <input type="hidden" name="return" value="index">
                                            <button type="submit"
                                                    class="btn btn-sm <?= (int) $schedule['is_active'] === 1 ? 'btn-danger' : 'btn-success' ?>"
                                                    title="<?= (int) $schedule['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                                <i class="bi <?= (int) $schedule['is_active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                            </button>
                                        </form>
                                        <?php if ((int) $schedule['is_active'] !== 1): ?>
                                            <form method="post" action="<?= e(url('admin/jadwal/proses.php')) ?>"
                                                  data-confirm="Jadwal nonaktif ini akan dihapus permanen. Tindakan ini tidak dapat dibatalkan."
                                                  data-confirm-title="Hapus jadwal"
                                                  data-confirm-button="Ya, hapus"
                                                  data-confirm-icon="warning" data-submit-once>
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $schedule['id'] ?>">
                                                <button type="submit" class="btn btn-dark btn-sm" title="Hapus permanen">
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
    <i class="bi bi-info-circle"></i>
    <span>Jadwal aktif tidak boleh bertumpukan untuk guru, group, atau ruangan yang sama.
        Nonaktifkan jadwal bila sudah tidak dipakai; hapus permanen hanya tersedia untuk jadwal nonaktif.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
