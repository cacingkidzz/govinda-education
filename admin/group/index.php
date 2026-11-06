<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$type = get_string('group_type');
$type = in_array($type, ['private', 'group'], true) ? $type : '';
$status = get_string('status');
$status = in_array($status, ['1', '0'], true) ? $status : '';
$teacherId = get_string('teacher_id');
$teacherId = ctype_digit($teacherId) ? $teacherId : '';
$subjectId = get_string('subject_id');
$subjectId = ctype_digit($subjectId) ? $subjectId : '';

$filters = [
    'group_type' => $type,
    'status'     => $status,
    'teacher_id' => $teacherId,
    'subject_id' => $subjectId,
];

$groups = Group::all($filters);
$schedules = Schedule::activeByGroup();
$teachers = Teacher::all();
$subjects = Subject::all();
$hasFilter = $type !== '' || $status !== '' || $teacherId !== '' || $subjectId !== '';

$totalActive = Group::countActive();
$totalPrivate = Group::countByType('private');
$totalRegular = Group::countByType('group');
$isAdmin = is_admin();
$joined = Membership::countStudentsInGroup();

$pageTitle = 'Group Belajar';
$pageSubtitle = 'Kelola group private dan group belajar beserta anggota serta jadwalnya.';
$breadcrumbs = [['label' => 'Group Belajar']];
$activeMenu = 'group';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/group/index.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-4">
                <label for="group_type" class="form-label">Jenis group</label>
                <select class="form-select" id="group_type" name="group_type">
                    <option value="">Semua jenis</option>
                    <option value="private" <?= $type === 'private' ? 'selected' : '' ?>>Private</option>
                    <option value="group" <?= $type === 'group' ? 'selected' : '' ?>>Group</option>
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
                    <a href="<?= e(url('admin/group/index.php')) ?>" class="btn btn-light"
                       title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="mini-stat">
            <span class="mini-stat-icon mini-stat-teal"><i class="bi bi-diagram-3-fill"></i></span>
            <div>
                <div class="mini-stat-value"><?= e(number_format($totalActive, 0, ',', '.')) ?></div>
                <div class="mini-stat-label">Group aktif</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mini-stat">
            <span class="mini-stat-icon mini-stat-navy"><i class="bi bi-person-fill"></i></span>
            <div>
                <div class="mini-stat-value"><?= e(number_format($totalPrivate, 0, ',', '.')) ?></div>
                <div class="mini-stat-label">Group private</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mini-stat">
            <span class="mini-stat-icon mini-stat-gold"><i class="bi bi-people-fill"></i></span>
            <div>
                <div class="mini-stat-value"><?= e(number_format($totalRegular, 0, ',', '.')) ?></div>
                <div class="mini-stat-label">Group reguler &middot; <?= e(number_format($joined, 0, ',', '.')) ?> siswa tergabung</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Daftar Group</h2>
            <div class="card-subtitle">
                <?= e(number_format(count($groups), 0, ',', '.')) ?> group<?= $hasFilter ? ' sesuai filter' : '' ?>
                &middot; jumlah anggota dihitung dari keanggotaan aktif
            </div>
        </div>
        <?php if ($isAdmin): ?>
            <a href="<?= e(url('admin/group/tambah.php')) ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Tambah Group
            </a>
        <?php endif; ?>
    </div>

    <div class="card-body p-0">
        <?php if ($groups === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-diagram-3"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada group yang sesuai filter' : 'Belum ada group belajar' ?>
                </div>
                <p class="empty-state-text">
                    <?= $hasFilter
                        ? 'Coba ubah atau reset filter untuk melihat group lainnya.'
                        : 'Buat group pertama, lalu tambahkan anggota dan jadwal belajarnya.' ?>
                </p>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/group/index.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                    </a>
                <?php elseif ($isAdmin): ?>
                    <a href="<?= e(url('admin/group/tambah.php')) ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Tambah Group
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width: 40px;">No.</th>
                        <th>Kode</th>
                        <th>Nama Group</th>
                        <th>Jenis</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru Pengajar</th>
                        <th class="text-center">Anggota</th>
                        <th class="text-center">Kapasitas</th>
                        <th>Jadwal</th>
                        <th class="no-sort">Status</th>
                        <th class="no-sort" style="width: 124px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($groups as $index => $group): ?>
                        <?php
                        $detailUrl = url('admin/group/detail.php?id=' . (int) $group['id']);
                        $memberCount = (int) $group['member_count'];
                        $capacity = (int) $group['capacity'];
                        $groupSchedules = $schedules[(int) $group['id']] ?? [];
                        ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td><span class="cell-title font-monospace"><?= e($group['code']) ?></span></td>
                            <td>
                                <a href="<?= e($detailUrl) ?>" class="cell-title link-dark text-decoration-none">
                                    <?= e($group['name']) ?>
                                </a>
                                <?php if (!empty($group['description'])): ?>
                                    <span class="cell-sub text-truncate d-block" style="max-width: 230px;">
                                        <?= e($group['description']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= group_type_badge((string) $group['group_type']) ?></td>
                            <td>
                                <?php if (!empty($group['subject_names'])): ?>
                                    <span class="cell-title"><?= e((string) $group['subject_names']) ?></span>
                                    <span class="cell-sub"><?= e((string) ($group['active_relation_count'] ?? 0)) ?> mata pelajaran aktif</span>
                                <?php else: ?>
                                    <span class="cell-sub text-muted"><i class="bi bi-dash-circle me-1"></i>Belum ada mata pelajaran</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($group['teacher_names'])): ?>
                                    <span class="cell-title"><?= e((string) $group['teacher_names']) ?></span>
                                <?php else: ?>
                                    <span class="cell-sub text-muted"><i class="bi bi-dash-circle me-1"></i>Belum ada guru</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= capacity_badge($memberCount, $capacity, (string) $group['group_type']) ?></td>
                            <td class="text-center">
                                <span class="fw-semibold"><?= e((string) $capacity) ?></span>
                                <span class="text-muted small"> siswa</span>
                            </td>
                            <td>
                                <?php if ($groupSchedules === []): ?>
                                    <span class="text-muted small"><i class="bi bi-calendar-x me-1"></i>Belum ada</span>
                                <?php else: ?>
                                    <?php foreach (array_slice($groupSchedules, 0, 2) as $schedule): ?>
                                        <span class="schedule-chip">
                                            <?= e(hari_id((int) $schedule['day_of_week'])) ?>
                                            <?= e(time_label((string) $schedule['start_time'])) ?>–<?= e(time_label((string) $schedule['end_time'])) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($groupSchedules) > 2): ?>
                                        <span class="cell-sub">+<?= e((string) (count($groupSchedules) - 2)) ?> jadwal lain</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><?= active_badge((int) $group['is_active'] === 1) ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e($detailUrl) ?>" class="btn btn-info btn-sm"
                                       title="Detail" aria-label="Detail <?= e($group['name']) ?>">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= e(url('admin/group/absensi.php?group_id=' . (int) $group['id'])) ?>"
                                       class="btn btn-light btn-sm" title="Cetak absensi"
                                       aria-label="Cetak absensi <?= e($group['name']) ?>"
                                       target="_blank" rel="noopener">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <?php if ($isAdmin): ?>
                                        <a href="<?= e(url('admin/group/edit.php?id=' . (int) $group['id'])) ?>"
                                           class="btn btn-warning btn-sm" title="Edit"
                                           aria-label="Edit <?= e($group['name']) ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form method="post" action="<?= e(url('admin/group/proses.php')) ?>"
                                              data-confirm="<?= (int) $group['is_active'] === 1
                                                  ? 'Group akan dinonaktifkan. Group nonaktif tidak dapat menerima anggota atau jadwal baru.'
                                                  : 'Group akan diaktifkan kembali dan dapat menerima anggota.' ?>"
                                              data-confirm-title="Ubah status group"
                                              data-confirm-button="<?= (int) $group['is_active'] === 1 ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                                              data-confirm-icon="warning" data-submit-once>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $group['id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-sm <?= (int) $group['is_active'] === 1 ? 'btn-danger' : 'btn-success' ?>"
                                                    title="<?= (int) $group['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                                <i class="bi <?= (int) $group['is_active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                            </button>
                                        </form>
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
    <span>Group tidak dihapus permanen agar riwayat keanggotaan dan transaksi tetap utuh.
        Gunakan tombol nonaktifkan bila group sudah tidak dipakai.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
