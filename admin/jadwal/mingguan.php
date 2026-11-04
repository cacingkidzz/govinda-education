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

$filters = [
    'group_id'         => $groupId,
    'group_subject_id' => $groupSubjectId,
    'teacher_id'       => $teacherId,
    'status'           => '1',
];

$schedules = Schedule::all($filters);
$groups = Group::all();
$teachers = Teacher::all();
$hasFilter = $groupId !== '' || $groupSubjectId !== '' || $teacherId !== '';

$byDay = [];
for ($day = 1; $day <= 7; $day++) {
    $byDay[$day] = [];
}
foreach ($schedules as $schedule) {
    $byDay[(int) $schedule['day_of_week']][] = $schedule;
}

$pageTitle = 'Jadwal Mingguan';
$pageSubtitle = 'Tampilan mingguan jadwal aktif per hari untuk seluruh group.';
$breadcrumbs = [
    ['label' => 'Jadwal Belajar', 'url' => url('admin/jadwal/index.php')],
    ['label' => 'Tampilan Mingguan'],
];
$activeMenu = 'jadwal';

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/jadwal/mingguan.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-4 col-md-5">
                <label for="group_id" class="form-label">Group</label>
                <select class="form-select" id="group_id" name="group_id">
                    <option value="">Semua group</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>" <?= $groupId === (string) $group['id'] ? 'selected' : '' ?>>
                            <?= e($group['name']) ?> (<?= e($group['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-4 col-md-5">
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
            <div class="col-xl-4 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/jadwal/mingguan.php')) ?>" class="btn btn-light"
                       title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Kalender Mingguan</h2>
            <div class="card-subtitle">
                <?= e((string) count($schedules)) ?> jadwal aktif<?= $hasFilter ? ' sesuai filter' : '' ?>
                &middot; Senin–Minggu
            </div>
        </div>
        <a href="<?= e(url_query('admin/jadwal/index.php', [
            'group_id' => $groupId,
            'group_subject_id' => $groupSubjectId,
            'teacher_id' => $teacherId,
        ])) ?>" class="btn btn-light btn-sm">
            <i class="bi bi-table me-1"></i>Tampilan Tabel
        </a>
    </div>
    <div class="card-body">
        <?php if ($schedules === []): ?>
            <div class="empty-state py-4">
                <div class="empty-state-icon"><i class="bi bi-calendar-week"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada jadwal aktif yang sesuai filter' : 'Belum ada jadwal aktif' ?>
                </div>
                <p class="empty-state-text">Tambahkan jadwal pada menu Jadwal Belajar untuk melihat kalender mingguan.</p>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/jadwal/mingguan.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="week-grid-wrap">
                <table class="week-grid">
                    <thead>
                    <tr>
                        <?php for ($day = 1; $day <= 7; $day++): ?>
                            <th class="<?= $day >= 6 ? 'is-weekend' : '' ?>">
                                <?= e(hari_id($day)) ?>
                                <span class="week-grid-count"><?= e((string) count($byDay[$day])) ?></span>
                            </th>
                        <?php endfor; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <?php for ($day = 1; $day <= 7; $day++): ?>
                            <td class="<?= $day >= 6 ? 'is-weekend' : '' ?>">
                                <?php if ($byDay[$day] === []): ?>
                                    <div class="week-empty"><i class="bi bi-dash"></i></div>
                                <?php else: ?>
                                    <?php foreach ($byDay[$day] as $schedule): ?>
                                        <a href="<?= e(url('admin/jadwal/detail.php?id=' . (int) $schedule['id'])) ?>"
                                           class="week-card">
                                            <span class="week-card-time">
                                                <?= e(time_label((string) $schedule['start_time'])) ?>–<?= e(time_label((string) $schedule['end_time'])) ?>
                                            </span>
                                            <span class="week-card-title"><?= e($schedule['group_name']) ?></span>
                                            <span class="week-card-meta">
                                                <i class="bi bi-journal-bookmark me-1"></i><?= e($schedule['subject_name']) ?>
                                            </span>
                                            <span class="week-card-meta">
                                                <i class="bi bi-person-video3 me-1"></i><?= e($schedule['teacher_name']) ?>
                                            </span>
                                            <span class="week-card-meta">
                                                <i class="bi bi-geo-alt me-1"></i><?= e($schedule['room'] ?: 'Tanpa ruangan') ?>
                                            </span>
                                            <span class="week-card-type"><?= e(group_type_label((string) $schedule['group_type'])) ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="master-note">
    <i class="bi bi-info-circle"></i>
    <span>Kalender mingguan hanya menampilkan jadwal aktif. Jadwal nonaktif tetap tersimpan dan dapat
        diaktifkan kembali melalui daftar jadwal.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
