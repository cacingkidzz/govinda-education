<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_common.php';
require_login();
require_role(['admin', 'petugas']);

$range = report_date_range();
$dateFrom = $range['from'];
$dateTo = $range['to'];

$groupId = get_string('group_id');
$groupId = ctype_digit($groupId) ? $groupId : '';
$teacherId = get_string('teacher_id');
$teacherId = ctype_digit($teacherId) ? $teacherId : '';
$subjectId = get_string('subject_id');
$subjectId = ctype_digit($subjectId) ? $subjectId : '';
$status = get_string('status');
$status = in_array($status, Meeting::statusOptions(), true) ? $status : '';

$filters = [
    'date_from'  => $dateFrom,
    'date_to'    => $dateTo,
    'group_id'   => $groupId,
    'teacher_id' => $teacherId,
    'subject_id' => $subjectId,
    'status'     => $status,
];

$rows = Meeting::recorded($filters);
$groups = Group::all();
$teachers = Teacher::all();
$subjects = Subject::all();
$hasFilter = !$range['isDefault'] || $groupId !== '' || $teacherId !== '' || $subjectId !== '' || $status !== '';

$countByStatus = array_fill_keys(Meeting::statusOptions(), 0);
$affectedTotal = 0;
foreach ($rows as $row) {
    $countByStatus[(string) $row['status']] = ($countByStatus[(string) $row['status']] ?? 0) + 1;
    $affectedTotal += (int) $row['affected_count'];
}

$csvParams = array_merge([
    'date_from'  => $dateFrom,
    'date_to'    => $dateTo,
    'group_id'   => $groupId,
    'teacher_id' => $teacherId,
    'subject_id' => $subjectId,
    'status'     => $status,
], ['export' => 'csv']);

if (get_string('export') === 'csv') {
    $csvRows = [];
    foreach ($rows as $row) {
        $csvRows[] = [
            (string) $row['meeting_date'],
            substr((string) $row['start_time'], 0, 5) . '-' . substr((string) $row['end_time'], 0, 5),
            (string) $row['group_code'],
            (string) $row['group_name'],
            (string) $row['subject_name'],
            (string) $row['teacher_name'],
            meeting_status_label((string) $row['status']),
            (int) $row['affected_count'],
            (string) ($row['notes'] ?? ''),
        ];
    }
    report_send_csv(
        'laporan-realisasi-' . $dateFrom . '_' . $dateTo . '.csv',
        ['Tanggal', 'Jam', 'Kode Kelompok', 'Kelompok', 'Mata Pelajaran', 'Guru',
            'Status', 'Paket Terhitung', 'Catatan'],
        $csvRows
    );
}

$pageTitle = 'Laporan Realisasi Pertemuan';
$pageSubtitle = 'Catatan pelaksanaan kegiatan belajar dan dampaknya pada paket siswa.';
$breadcrumbs = [['label' => 'Laporan', 'url' => url('admin/laporan/keuangan.php')], ['label' => 'Realisasi']];
$activeMenu = 'laporan-realisasi';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-green">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Terlaksana</div>
                    <div class="stat-value"><?= e(number_format($countByStatus[Meeting::STATUS_DONE], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-box-seam"></i>
                <?= e(number_format($affectedTotal, 0, ',', '.')) ?> paket terhitung
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-navy">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Terjadwal</div>
                    <div class="stat-value"><?= e(number_format($countByStatus[Meeting::STATUS_SCHEDULED], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-hourglass"></i>Belum dicatat hasilnya</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-gold">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Dijadwalkan Ulang</div>
                    <div class="stat-value"><?= e(number_format($countByStatus[Meeting::STATUS_RESCHEDULED], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-arrow-repeat"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-shield-check"></i>Tidak dihitung ke paket</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-rose">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Dibatalkan</div>
                    <div class="stat-value"><?= e(number_format($countByStatus[Meeting::STATUS_CANCELLED], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-shield-check"></i>Tidak dihitung ke paket</div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/laporan/realisasi.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-4">
                <label for="date_from" class="form-label">Dari tanggal</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="date_to" class="form-label">Sampai</label>
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
            <div class="col-xl-2 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/laporan/realisasi.php')) ?>" class="btn btn-light" title="Reset">
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
            <h2 class="card-title">Rincian Realisasi</h2>
            <div class="card-subtitle">
                <?= e(number_format(count($rows), 0, ',', '.')) ?> catatan pertemuan pada rentang ini.
            </div>
        </div>
        <?= report_toolbar(url_query('admin/laporan/realisasi.php', $csvParams)) ?>
    </div>
    <div class="card-body p-0">
        <?php if ($rows === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
                <div class="empty-state-title">Belum ada realisasi pada rentang ini</div>
                <p class="empty-state-text">
                    Catat pelaksanaan pada menu Realisasi Pertemuan. Jadwal yang belum dicatat tidak
                    muncul di laporan ini.
                </p>
                <a href="<?= e(url('admin/realisasi-pertemuan/index.php')) ?>" class="btn btn-primary">
                    <i class="bi bi-check2-square me-1"></i>Realisasi Pertemuan
                </a>
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
                        <th>Status</th>
                        <th class="text-center">Paket</th>
                        <th>Catatan</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $index => $row): ?>
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
                                <span class="cell-sub"><?= e($row['group_code']) ?></span>
                            </td>
                            <td><?= e($row['subject_name']) ?></td>
                            <td><?= e($row['teacher_name']) ?></td>
                            <td><?= meeting_status_badge((string) $row['status']) ?></td>
                            <td class="text-center">
                                <?= (int) $row['affected_count'] > 0
                                    ? '<span class="badge text-bg-success">' . (int) $row['affected_count'] . '</span>'
                                    : '<span class="text-muted">—</span>' ?>
                            </td>
                            <td><span class="cell-sub d-block" style="max-width:220px;"><?= e($row['notes'] ?: '-') ?></span></td>
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
    <span>Hanya pertemuan berstatus Terlaksana yang dihitung ke paket siswa. Kehadiran siswa tidak
        memengaruhi hitungan, sedangkan jadwal yang dibatalkan atau dipindahkan tidak dihitung.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
