<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$dateFrom = get_string('date_from');
$dateFrom = valid_date($dateFrom) ? $dateFrom : '';
$dateTo = get_string('date_to');
$dateTo = valid_date($dateTo) ? $dateTo : '';
if ($dateFrom !== '' && $dateTo === '') {
    $dateTo = $dateFrom;
} elseif ($dateTo !== '' && $dateFrom === '') {
    $dateFrom = $dateTo;
} elseif ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$groupId = get_string('group_id');
$groupId = ctype_digit($groupId) ? $groupId : '';
$subjectId = get_string('subject_id');
$subjectId = ctype_digit($subjectId) ? $subjectId : '';
$onlyWithMeetings = get_string('only_with_meetings', '1') !== '0';

$summary = Meeting::groupSummary([
    'group_id'           => $groupId,
    'subject_id'         => $subjectId,
    'date_from'          => $dateFrom,
    'date_to'            => $dateTo,
    'only_with_meetings' => $onlyWithMeetings,
]);

$groups = Group::all();
$subjects = Subject::all();
$manualRelations = GroupSubject::activeOptions();
$manualReturnFrom = $dateFrom !== '' && $dateTo !== '' ? $dateFrom . '|' . $dateTo : '';
$manualReturnTo = 'rekap';

$totalGroups = count($summary);
$totalDone = 0;
$totalRecorded = 0;
$totalScheduled = 0;
foreach ($summary as $item) {
    $totalDone += (int) $item['done'];
    $totalRecorded += (int) $item['total'];
    $totalScheduled += (int) ($item['counts'][Meeting::STATUS_SCHEDULED] ?? 0);
}

$hasFilter = $groupId !== '' || $subjectId !== '' || $dateFrom !== '' || $dateTo !== '' || !$onlyWithMeetings;
$rangeLabel = $dateFrom !== ''
    ? tanggal_short_id($dateFrom) . ' – ' . tanggal_short_id($dateTo)
    : 'seluruh periode';

$statusChip = static function (array $date) use ($dateFrom, $dateTo): string {
    $status = (string) $date['status'];
    $class = match ($status) {
        Meeting::STATUS_DONE        => 'text-bg-success',
        Meeting::STATUS_CANCELLED   => 'text-bg-danger',
        Meeting::STATUS_RESCHEDULED => 'text-bg-warning',
        default                     => 'text-bg-secondary',
    };
    $icon = match ($status) {
        Meeting::STATUS_DONE        => 'bi-check2-circle',
        Meeting::STATUS_CANCELLED   => 'bi-x-circle',
        Meeting::STATUS_RESCHEDULED => 'bi-arrow-repeat',
        default                     => 'bi-calendar-event',
    };
    $url = url_query('admin/realisasi-pertemuan/catat.php', [
        'id'          => (int) $date['meeting_id'],
        'return_from' => $dateFrom !== '' && $dateTo !== '' ? $dateFrom . '|' . $dateTo : null,
    ]);
    $title = meeting_status_label($status) . ' · '
        . time_label((string) $date['start_time']) . '–' . time_label((string) $date['end_time']);

    return '<a href="' . e($url) . '" class="badge ' . $class . ' text-decoration-none" title="' . e($title) . '">'
        . '<i class="bi ' . $icon . ' me-1"></i>' . e(tanggal_short_id((string) $date['meeting_date']))
        . ' <span class="fw-normal">' . e(substr((string) $date['start_time'], 0, 5)) . '</span></a>';
};

$pageTitle = 'Rekap Pertemuan per Group';
$pageSubtitle = 'Lihat berapa kali setiap group sudah belajar dan pada tanggal berapa saja.';
$breadcrumbs = [
    ['label' => 'Realisasi Pertemuan', 'url' => url('admin/realisasi-pertemuan/index.php')],
    ['label' => 'Rekap per Group'],
];
$activeMenu = 'realisasi';
$pageStyles = [
    'vendor/select2/select2.min.css',
    'vendor/select2/select2-bootstrap-5-theme.min.css',
];
$pageScripts = ['vendor/select2/select2.min.js'];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/realisasi-pertemuan/rekap.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-4">
                <label for="date_from" class="form-label">Dari tanggal</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="date_to" class="form-label">Sampai tanggal</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-xl-3 col-md-4">
                <label for="group_id" class="form-label">Kelompok</label>
                <select class="form-select" id="group_id" name="group_id">
                    <option value="">Semua kelompok</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>" <?= $groupId === (string) $group['id'] ? 'selected' : '' ?>>
                            <?= e($group['name']) ?> (<?= e($group['code']) ?>)
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
            <div class="col-xl-3 col-md-4">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" value="1" id="only_with_meetings"
                           name="only_with_meetings" <?= $onlyWithMeetings ? 'checked' : '' ?>>
                    <label class="form-check-label" for="only_with_meetings">Hanya group yang punya pertemuan</label>
                </div>
            </div>
            <div class="col-xl-4 col-md-8 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <a href="<?= e(url('admin/realisasi-pertemuan/rekap.php')) ?>" class="btn btn-light">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </a>
                <a href="<?= e(url_query('admin/realisasi-pertemuan/index.php', [
                    'date_from' => $dateFrom !== '' ? $dateFrom : null,
                    'date_to'   => $dateTo !== '' ? $dateTo : null,
                    'group_id'  => $groupId !== '' ? $groupId : null,
                ])) ?>" class="btn btn-light">
                    <i class="bi bi-list-check me-1"></i>Daftar Realisasi
                </a>
                <button type="button" class="btn btn-success" data-bs-toggle="modal"
                        data-bs-target="#manualMeetingModal">
                    <i class="bi bi-journal-plus me-1"></i>Pertemuan Manual
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-teal">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Group Ditampilkan</div>
                    <div class="stat-value"><?= e(number_format($totalGroups, 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-diagram-3"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-calendar-range"></i><?= e($rangeLabel) ?></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-green">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Pertemuan Terlaksana</div>
                    <div class="stat-value"><?= e(number_format($totalDone, 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-check2-circle"></i>Dihitung ke paket siswa</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-gold">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total Pertemuan Tercatat</div>
                    <div class="stat-value"><?= e(number_format($totalRecorded, 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-journal-text"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-info-circle"></i>Termasuk batal &amp; pindah</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-navy">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Belum Dicatat</div>
                    <div class="stat-value"><?= e(number_format($totalScheduled, 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-calendar-event"></i>Berstatus terjadwal</div>
        </div>
    </div>
</div>

<?php if ($summary === []): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-journal-x"></i></div>
            <div class="empty-state-title">
                <?= $hasFilter ? 'Tidak ada group yang sesuai filter' : 'Belum ada pertemuan tercatat' ?>
            </div>
            <p class="empty-state-text">
                <?= $hasFilter
                    ? 'Coba ubah filter atau tampilkan group yang belum memiliki pertemuan.'
                    : 'Catat pertemuan dari daftar realisasi atau gunakan tombol Pertemuan Manual.' ?>
            </p>
            <?php if ($hasFilter): ?>
                <a href="<?= e(url('admin/realisasi-pertemuan/rekap.php')) ?>" class="btn btn-light">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($summary as $item): ?>
        <?php
        $group = $item['group'];
        $doneCount = (int) $item['done'];
        $recordedCount = (int) $item['total'];
        $cancelledCount = (int) ($item['counts'][Meeting::STATUS_CANCELLED] ?? 0);
        $rescheduledCount = (int) ($item['counts'][Meeting::STATUS_RESCHEDULED] ?? 0);
        $listUrl = url_query('admin/realisasi-pertemuan/index.php', [
            'group_id'  => (int) $group['id'],
            'date_from' => $dateFrom !== '' ? $dateFrom : null,
            'date_to'   => $dateTo !== '' ? $dateTo : null,
        ]);
        ?>
        <div class="card mb-3">
            <div class="card-header flex-wrap gap-2">
                <div>
                    <h2 class="card-title">
                        <?= e($group['name']) ?>
                        <span class="badge bg-teal-soft text-teal ms-1"><?= e($group['code']) ?></span>
                        <?php if ((int) $group['is_active'] !== 1): ?>
                            <span class="badge text-bg-secondary ms-1">Nonaktif</span>
                        <?php endif; ?>
                    </h2>
                    <div class="card-subtitle">
                        <?= e(package_type_label((string) $group['group_type'])) ?>
                        &middot; <?= e((string) (int) $group['member_count']) ?> siswa aktif
                        &middot; <?= e($group['subject_names'] ?: 'belum ada mapel') ?>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge text-bg-success">
                        <i class="bi bi-check2-circle me-1"></i><?= e((string) $doneCount) ?> terlaksana
                    </span>
                    <span class="badge text-bg-light"><?= e((string) $recordedCount) ?> tercatat</span>
                    <?php if ($cancelledCount > 0): ?>
                        <span class="badge text-bg-danger"><?= e((string) $cancelledCount) ?> batal</span>
                    <?php endif; ?>
                    <?php if ($rescheduledCount > 0): ?>
                        <span class="badge text-bg-warning"><?= e((string) $rescheduledCount) ?> pindah</span>
                    <?php endif; ?>
                    <a href="<?= e($listUrl) ?>" class="btn btn-light btn-sm" title="Lihat pada daftar realisasi">
                        <i class="bi bi-list-check me-1"></i>Daftar
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($item['subjects'] === []): ?>
                    <div class="p-4 text-center text-muted small">
                        Belum ada pertemuan pada rentang ini.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th class="text-center">Terlaksana</th>
                                <th class="text-center">Batal</th>
                                <th class="text-center">Pindah</th>
                                <th class="text-center">Terjadwal</th>
                                <th>Tanggal Pertemuan</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($item['subjects'] as $subject): ?>
                                <tr>
                                    <td><span class="cell-title"><?= e($subject['subject_name']) ?></span></td>
                                    <td><?= e($subject['teacher_name']) ?></td>
                                    <td class="text-center">
                                        <span class="fw-semibold text-success"><?= e((string) (int) $subject['done']) ?></span>
                                        <span class="cell-sub">/ <?= e((string) (int) $subject['total']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?= (int) ($subject['counts'][Meeting::STATUS_CANCELLED] ?? 0) > 0
                                            ? e((string) (int) $subject['counts'][Meeting::STATUS_CANCELLED])
                                            : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td class="text-center">
                                        <?= (int) ($subject['counts'][Meeting::STATUS_RESCHEDULED] ?? 0) > 0
                                            ? e((string) (int) $subject['counts'][Meeting::STATUS_RESCHEDULED])
                                            : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td class="text-center">
                                        <?= (int) ($subject['counts'][Meeting::STATUS_SCHEDULED] ?? 0) > 0
                                            ? e((string) (int) $subject['counts'][Meeting::STATUS_SCHEDULED])
                                            : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($subject['dates'] as $date): ?>
                                                <?= $statusChip($date) ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white small text-muted d-flex flex-wrap justify-content-between gap-2">
                <span>
                    <?php if ($item['first_done'] !== null): ?>
                        Pertemuan terlaksana pertama <?= e(tanggal_id((string) $item['first_done'])) ?>,
                        terakhir <?= e(tanggal_id((string) $item['last_done'])) ?>.
                    <?php else: ?>
                        Belum ada pertemuan terlaksana pada rentang ini.
                    <?php endif; ?>
                </span>
                <span>Klik tanggal untuk membuka atau mengubah realisasi.</span>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="master-note">
    <i class="bi bi-info-circle"></i>
    <span>Jumlah <strong>terlaksana</strong> dihitung dari seluruh mata pelajaran dalam group dan menjadi
        dasar hitungan paket siswa. Pertemuan batal atau dijadwalkan ulang tidak dihitung.
        Beberapa pertemuan pada hari yang sama tetap dihitung terpisah selama jamnya berbeda.</span>
</div>

<?php require __DIR__ . '/_manual_modal.php'; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
