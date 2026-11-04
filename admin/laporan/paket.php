<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_common.php';
require_login();
require_role(['admin', 'petugas']);

$range = report_date_range();
$dateFrom = $range['from'];
$dateTo = $range['to'];

$status = get_string('status');
$status = in_array($status, StudentPackage::statusOptions(), true) ? $status : '';
$q = get_string('q');
$groupId = get_string('group_id');
$groupId = ctype_digit($groupId) ? $groupId : '';

$filters = [
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'status'    => $status,
    'q'         => $q,
    'group_id'  => $groupId,
];

$rows = Report::packages($filters);
$groups = Group::all();
$hasFilter = !$range['isDefault'] || $status !== '' || $q !== '' || $groupId !== '';

$statusCount = array_fill_keys(StudentPackage::statusOptions(), 0);
$totalCompleted = 0;
$totalTarget = 0;
foreach ($rows as $row) {
    $statusCount[(string) $row['status']] = ($statusCount[(string) $row['status']] ?? 0) + 1;
    $totalCompleted += (int) $row['completed_meetings'];
    $totalTarget += (int) $row['target_meetings'];
}

$csvParams = array_merge([
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'status'    => $status,
    'q'         => $q,
    'group_id'  => $groupId,
], ['export' => 'csv']);

if (get_string('export') === 'csv') {
    $csvRows = [];
    foreach ($rows as $row) {
        $csvRows[] = [
            (string) $row['student_code'],
            (string) $row['student_name'],
            (string) $row['group_name'],
            package_type_label((string) $row['group_type']),
            'Paket ' . (int) $row['package_number'],
            (string) $row['start_date'],
            (string) ($row['end_date'] ?? ''),
            (int) $row['completed_meetings'] . '/' . (int) $row['target_meetings'],
            rupiah((float) $row['base_amount'], false),
            rupiah((float) $row['discount_amount'], false),
            rupiah((float) $row['total_amount'], false),
            package_status_label((string) $row['status']),
            (string) ($row['invoice_number'] ?? ''),
            (string) ($row['invoice_status'] ?? ''),
        ];
    }
    report_send_csv(
        'laporan-paket-' . $dateFrom . '_' . $dateTo . '.csv',
        ['Kode Siswa', 'Nama Siswa', 'Kelompok', 'Jenis', 'Paket', 'Mulai', 'Selesai',
            'Progres', 'Tarif Dasar', 'Diskon', 'Total', 'Status Paket', 'Invoice', 'Status Invoice'],
        $csvRows
    );
}

$pageTitle = 'Laporan Paket Belajar';
$pageSubtitle = 'Rekap paket 8 pertemuan beserta progres dan status tagihannya.';
$breadcrumbs = [['label' => 'Laporan', 'url' => url('admin/laporan/keuangan.php')], ['label' => 'Paket Belajar']];
$activeMenu = 'laporan-paket';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-teal">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total Paket</div>
                    <div class="stat-value"><?= e(number_format(count($rows), 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-calendar-range"></i>
                <?= e(tanggal_short_id($dateFrom)) ?> – <?= e(tanggal_short_id($dateTo)) ?>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-green">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Paket Aktif</div>
                    <div class="stat-value"><?= e(number_format($statusCount[StudentPackage::STATUS_AKTIF], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-play-circle"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-hourglass-split"></i>
                <?= e(number_format($statusCount[StudentPackage::STATUS_SELESAI], 0, ',', '.')) ?> selesai
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-gold">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Paket Selesai</div>
                    <div class="stat-value"><?= e(number_format($statusCount[StudentPackage::STATUS_SELESAI], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-check2-all"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-flag"></i>Mencapai target pertemuan</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-navy">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total Pertemuan</div>
                    <div class="stat-value"><?= e(number_format($totalCompleted, 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-target"></i>
                dari target <?= e(number_format($totalTarget, 0, ',', '.')) ?> pertemuan
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/laporan/paket.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-6">
                <label for="date_from" class="form-label">Mulai dari</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-xl-2 col-md-6">
                <label for="date_to" class="form-label">Sampai</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="status" class="form-label">Status paket</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua status</option>
                    <?php foreach (StudentPackage::statusOptions() as $option): ?>
                        <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>>
                            <?= e(package_status_label($option)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
                <label for="q" class="form-label">Cari siswa</label>
                <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>"
                       placeholder="Nama, kode, kelompok">
            </div>
            <div class="col-xl-2 col-md-8 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/laporan/paket.php')) ?>" class="btn btn-light" title="Reset">
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
            <h2 class="card-title">Rincian Paket Belajar</h2>
            <div class="card-subtitle">
                <?= e(number_format(count($rows), 0, ',', '.')) ?> paket pada rentang tanggal mulai.
            </div>
        </div>
        <?= report_toolbar(url_query('admin/laporan/paket.php', $csvParams)) ?>
    </div>
    <div class="card-body p-0">
        <?php if ($rows === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-box-seam"></i></div>
                <div class="empty-state-title">Tidak ada paket pada rentang ini</div>
                <p class="empty-state-text">Ubah rentang tanggal, status, atau kelompok.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width:40px;">No.</th>
                        <th>Siswa</th>
                        <th>Kelompok</th>
                        <th class="no-sort">Paket</th>
                        <th>Mulai</th>
                        <th style="min-width:130px;">Progres</th>
                        <th class="text-end">Total Tarif</th>
                        <th>Status</th>
                        <th>Invoice</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td>
                                <a href="<?= e(url('admin/paket/detail.php?id=' . (int) $row['id'])) ?>"
                                   class="cell-title link-dark text-decoration-none">
                                    <?= e($row['student_name']) ?>
                                </a>
                                <span class="cell-sub font-monospace"><?= e($row['student_code']) ?></span>
                            </td>
                            <td>
                                <span class="cell-title"><?= e($row['group_name']) ?></span>
                                <span class="cell-sub"><?= e(package_type_label((string) $row['group_type'])) ?>
                                    &middot; <?= e((string) (int) $row['member_count']) ?> siswa</span>
                            </td>
                            <td class="text-nowrap">Paket <?= e((string) (int) $row['package_number']) ?></td>
                            <td class="text-nowrap"><?= e(tanggal_short_id((string) $row['start_date'])) ?></td>
                            <td><?= package_progress((int) $row['completed_meetings'], (int) $row['target_meetings']) ?></td>
                            <td class="text-end amount"><?= e(rupiah((float) $row['total_amount'])) ?></td>
                            <td><?= package_status_badge((string) $row['status']) ?></td>
                            <td>
                                <?php if ($row['invoice_number'] !== null): ?>
                                    <span class="invoice-number small"><?= e($row['invoice_number']) ?></span>
                                    <span class="cell-sub"><?= e(invoice_status_label((string) $row['invoice_status'])) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
