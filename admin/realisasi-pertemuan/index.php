<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$singleDate = get_string('tanggal');
$singleDate = valid_date($singleDate) ? $singleDate : '';
$dateFrom = get_string('date_from');
$dateFrom = valid_date($dateFrom) ? $dateFrom : '';
$dateTo = get_string('date_to');
$dateTo = valid_date($dateTo) ? $dateTo : '';

if ($singleDate !== '') {
    $dateFrom = $singleDate;
    $dateTo = $singleDate;
} elseif ($dateFrom === '' && $dateTo === '') {
    $dateFrom = $monthStart;
    $dateTo = $monthEnd;
} elseif ($dateFrom === '') {
    $dateFrom = $dateTo;
} elseif ($dateTo === '') {
    $dateTo = $dateFrom;
}

$groupId = get_string('group_id');
$groupId = ctype_digit($groupId) ? $groupId : '';
$teacherId = get_string('teacher_id');
$teacherId = ctype_digit($teacherId) ? $teacherId : '';
$subjectId = get_string('subject_id');
$subjectId = ctype_digit($subjectId) ? $subjectId : '';
$status = get_string('status');
$status = in_array($status, Meeting::statusOptions(), true) ? $status : '';
$onlyUnrecorded = get_string('unrecorded') === '1';

$filters = [
    'group_id'   => $groupId,
    'teacher_id' => $teacherId,
    'subject_id' => $subjectId,
    'status'     => $status,
];

$occurrences = Meeting::occurrences($dateFrom, $dateTo, $filters);
if ($onlyUnrecorded) {
    $occurrences = array_values(array_filter(
        $occurrences,
        static fn (array $row): bool => !$row['has_realization'] || $row['status'] === Meeting::STATUS_SCHEDULED
    ));
}

$groups = Group::all();
$teachers = Teacher::all();
$subjects = Subject::all();
$hasFilter = $singleDate !== '' || $groupId !== '' || $teacherId !== '' || $subjectId !== ''
    || $status !== '' || $onlyUnrecorded
    || $dateFrom !== $monthStart || $dateTo !== $monthEnd;

$doneCount = 0;
$scheduledCount = 0;
foreach ($occurrences as $row) {
    if ($row['status'] === Meeting::STATUS_DONE) {
        $doneCount++;
    } elseif ($row['status'] === Meeting::STATUS_SCHEDULED) {
        $scheduledCount++;
    }
}

$pageTitle = 'Realisasi Pertemuan';
$pageSubtitle = 'Catat apakah kegiatan belajar benar-benar terlaksana. Hanya status Terlaksana yang dihitung ke paket.';
$breadcrumbs = [['label' => 'Realisasi Pertemuan']];
$activeMenu = 'realisasi';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/realisasi-pertemuan/index.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-4">
                <label for="tanggal" class="form-label">Tanggal</label>
                <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= e($singleDate) ?>">
                <div class="form-text">Kosongkan untuk memakai rentang.</div>
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="date_from" class="form-label">Dari tanggal</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="date_to" class="form-label">Sampai tanggal</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="group_id" class="form-label">Kelompok</label>
                <select class="form-select" id="group_id" name="group_id">
                    <option value="">Semua kelompok</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>" <?= $groupId === (string) $group['id'] ? 'selected' : '' ?>>
                            <?= e($group['name']) ?>
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
                <label for="subject_id" class="form-label">Mata pelajaran</label>
                <select class="form-select" id="subject_id" name="subject_id">
                    <option value="">Semua mapel</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= (int) $subject['id'] ?>" <?= $subjectId === (string) $subject['id'] ? 'selected' : '' ?>>
                            <?= e($subject['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua status</option>
                    <?php foreach (Meeting::statusOptions() as $option): ?>
                        <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>>
                            <?= e(meeting_status_label($option)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" value="1" id="unrecorded" name="unrecorded"
                        <?= $onlyUnrecorded ? 'checked' : '' ?>>
                    <label class="form-check-label" for="unrecorded">Belum dicatat</label>
                </div>
            </div>
            <div class="col-xl-4 col-md-8 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <a href="<?= e(url('admin/realisasi-pertemuan/index.php')) ?>" class="btn btn-light">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Bulan Ini
                </a>
                <a href="<?= e(url_query('admin/laporan/realisasi.php', [
                    'date_from' => $dateFrom,
                    'date_to'   => $dateTo,
                    'group_id'  => $groupId,
                    'teacher_id'=> $teacherId,
                    'subject_id'=> $subjectId,
                    'status'    => $status,
                ])) ?>" class="btn btn-light">
                    <i class="bi bi-file-earmark-text me-1"></i>Laporan
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Daftar Jadwal &amp; Realisasi</h2>
            <div class="card-subtitle">
                <?= e(tanggal_short_id($dateFrom)) ?> – <?= e(tanggal_short_id($dateTo)) ?>
                &middot; <?= e(number_format(count($occurrences), 0, ',', '.')) ?> jadwal
                &middot; <?= e(number_format($doneCount, 0, ',', '.')) ?> terlaksana
                &middot; <?= e(number_format($scheduledCount, 0, ',', '.')) ?> belum dicatat
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($occurrences === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada jadwal yang sesuai filter' : 'Belum ada jadwal pada rentang ini' ?>
                </div>
                <p class="empty-state-text">
                    <?= $hasFilter
                        ? 'Coba ubah atau reset filter untuk melihat jadwal lainnya.'
                        : 'Pastikan jadwal belajar aktif sudah dibuat pada menu Jadwal Belajar.' ?>
                </p>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/realisasi-pertemuan/index.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width:40px;">No.</th>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Kelompok</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru</th>
                        <th class="text-center">Siswa</th>
                        <th class="no-sort">Status</th>
                        <th class="text-center">Terhitung</th>
                        <th class="no-sort" style="width:120px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($occurrences as $index => $row): ?>
                        <?php
                        $catatUrl = url_query('admin/realisasi-pertemuan/catat.php', [
                            'schedule_id' => $row['schedule_id'] > 0 ? (int) $row['schedule_id'] : null,
                            'id'          => $row['meeting_id'] !== null ? (int) $row['meeting_id'] : null,
                            'tanggal'     => (string) $row['meeting_date'],
                            'group_subject_id' => (int) $row['group_subject_id'],
                            'return_from' => $dateFrom . '|' . $dateTo,
                        ]);
                        ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td class="text-nowrap">
                                <span class="cell-title"><?= e(tanggal_short_id((string) $row['meeting_date'])) ?></span>
                                <span class="cell-sub"><?= e(hari_id((int) date('N', strtotime((string) $row['meeting_date'])))) ?></span>
                            </td>
                            <td class="text-nowrap">
                                <?= e(time_label((string) $row['start_time'])) ?>–<?= e(time_label((string) $row['end_time'])) ?>
                            </td>
                            <td>
                                <span class="cell-title"><?= e($row['group_name']) ?></span>
                                <span class="cell-sub"><?= e($row['group_code']) ?> &middot;
                                    <?= e(package_type_label((string) $row['group_type'])) ?></span>
                                <?php if ($row['is_replacement']): ?>
                                    <span class="badge text-bg-warning mt-1">Pengganti</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($row['subject_name']) ?></td>
                            <td><?= e($row['teacher_name']) ?></td>
                            <td class="text-center"><?= e((string) (int) $row['member_count']) ?></td>
                            <td><?= meeting_status_badge((string) $row['status']) ?></td>
                            <td class="text-center">
                                <?php if ((int) $row['affected_count'] > 0): ?>
                                    <span class="badge text-bg-success"><?= e((string) (int) $row['affected_count']) ?> paket</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e($catatUrl) ?>"
                                       class="btn btn-sm <?= $row['status'] === Meeting::STATUS_DONE ? 'btn-info' : 'btn-primary' ?>"
                                       title="<?= $row['has_realization'] ? 'Ubah realisasi' : 'Catat realisasi' ?>">
                                        <i class="bi <?= $row['has_realization'] ? 'bi-pencil-square' : 'bi-check2-square' ?>"></i>
                                    </a>
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
    <span>Pertemuan dihitung untuk seluruh siswa yang menjadi anggota kelompok pada tanggal pelaksanaan,
        tanpa melihat kehadiran. Kelas yang dibatalkan atau dipindahkan tidak dihitung, dan satu realisasi
        tidak pernah dihitung dua kali.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
