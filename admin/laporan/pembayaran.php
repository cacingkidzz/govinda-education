<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_common.php';
require_login();
require_role(['admin', 'petugas']);

$range = report_date_range();
$dateFrom = $range['from'];
$dateTo = $range['to'];

$method = get_string('method');
$method = in_array($method, Payment::methodOptions(), true) ? $method : '';
$q = get_string('q');
$groupId = get_string('group_id');
$groupId = ctype_digit($groupId) ? $groupId : '';

$filters = [
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'method'    => $method,
    'q'         => $q,
    'group_id'  => $groupId,
];

$rows = Payment::all($filters);
$summary = Payment::summary($filters);
$groups = Group::all();
$hasFilter = !$range['isDefault'] || $method !== '' || $q !== '' || $groupId !== '';

$csvParams = array_merge([
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'method'    => $method,
    'q'         => $q,
    'group_id'  => $groupId,
], ['export' => 'csv']);

if (get_string('export') === 'csv') {
    $csvRows = [];
    foreach ($rows as $row) {
        $csvRows[] = [
            (string) $row['payment_number'],
            (string) $row['payment_date'],
            (string) $row['invoice_number'],
            (string) $row['student_code'],
            (string) $row['student_name'],
            (string) $row['group_name'],
            'Paket ' . (int) $row['package_number'],
            payment_method_label((string) $row['method']),
            rupiah((float) $row['amount'], false),
            (string) ($row['officer_name'] ?? ''),
            (string) ($row['reference_number'] ?? ''),
        ];
    }
    report_send_csv(
        'laporan-pembayaran-' . $dateFrom . '_' . $dateTo . '.csv',
        ['Nomor Nota', 'Tanggal', 'Invoice', 'Kode Siswa', 'Nama Siswa', 'Kelompok',
            'Paket', 'Metode', 'Jumlah', 'Petugas', 'Referensi'],
        $csvRows
    );
}

$totalAmount = 0.0;
$methodTotals = [];
foreach ($rows as $row) {
    $totalAmount += (float) $row['amount'];
    $key = (string) $row['method'];
    $methodTotals[$key] = ($methodTotals[$key] ?? 0.0) + (float) $row['amount'];
}

$pageTitle = 'Laporan Pembayaran';
$pageSubtitle = 'Uang yang benar-benar diterima dari pelunasan tagihan paket belajar.';
$breadcrumbs = [['label' => 'Laporan', 'url' => url('admin/laporan/keuangan.php')], ['label' => 'Pembayaran']];
$activeMenu = 'laporan-pembayaran';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-xl-4 col-md-6">
        <div class="stat-card stat-green">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total Diterima</div>
                    <div class="stat-value stat-money"><?= e(rupiah($summary['amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-calendar-range"></i>
                <?= e(tanggal_short_id($dateFrom)) ?> – <?= e(tanggal_short_id($dateTo)) ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card stat-teal">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Jumlah Transaksi</div>
                    <div class="stat-value"><?= e(number_format($summary['count'], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-receipt"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-check2-circle"></i>Semua pelunasan penuh</div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card stat-gold">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Rata-rata per Transaksi</div>
                    <div class="stat-value stat-money">
                        <?= e(rupiah($summary['count'] > 0 ? $summary['amount'] / $summary['count'] : 0)) ?>
                    </div>
                </div>
                <div class="stat-icon"><i class="bi bi-graph-up"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-calculator"></i>Berdasarkan filter aktif</div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/laporan/pembayaran.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-6">
                <label for="date_from" class="form-label">Dari tanggal</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-xl-2 col-md-6">
                <label for="date_to" class="form-label">Sampai tanggal</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="method" class="form-label">Metode</label>
                <select class="form-select" id="method" name="method">
                    <option value="">Semua metode</option>
                    <?php foreach (Payment::methodOptions() as $option): ?>
                        <option value="<?= e($option) ?>" <?= $method === $option ? 'selected' : '' ?>>
                            <?= e(payment_method_label($option)) ?>
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
                       placeholder="Nama, kode, nota">
            </div>
            <div class="col-xl-2 col-md-8 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/laporan/pembayaran.php')) ?>" class="btn btn-light" title="Reset">
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
            <h2 class="card-title">Rincian Pembayaran</h2>
            <div class="card-subtitle">
                <?= e(number_format(count($rows), 0, ',', '.')) ?> pembayaran &middot;
                total <?= e(rupiah($totalAmount)) ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e(url_query('admin/laporan/pembayaran-group.php', [
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'group_id'  => $groupId,
                'method'    => $method,
            ])) ?>" class="btn btn-light no-print" title="Rekap uang diterima per group">
                <i class="bi bi-people-fill me-1"></i>Rekap per Group
            </a>
            <?= report_toolbar(url_query('admin/laporan/pembayaran.php', $csvParams)) ?>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($rows === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="empty-state-title">Tidak ada pembayaran pada rentang ini</div>
                <p class="empty-state-text">Ubah rentang tanggal atau pastikan pelunasan sudah dicatat.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width:40px;">No.</th>
                        <th>Nomor Nota</th>
                        <th>Tanggal</th>
                        <th>Siswa</th>
                        <th>Kelompok</th>
                        <th class="no-sort">Paket</th>
                        <th>Metode</th>
                        <th class="text-end">Jumlah</th>
                        <th>Petugas</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td class="font-monospace small"><?= e($row['payment_number']) ?></td>
                            <td class="text-nowrap"><?= e(tanggal_short_id((string) $row['payment_date'])) ?></td>
                            <td>
                                <span class="cell-title"><?= e($row['student_name']) ?></span>
                                <span class="cell-sub font-monospace"><?= e($row['student_code']) ?></span>
                            </td>
                            <td><?= e($row['group_name']) ?></td>
                            <td class="text-nowrap">Paket <?= e((string) (int) $row['package_number']) ?></td>
                            <td><?= e(payment_method_label((string) $row['method'])) ?></td>
                            <td class="text-end amount"><?= e(rupiah((float) $row['amount'])) ?></td>
                            <td><?= e($row['officer_name'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr class="fw-semibold">
                        <td colspan="7" class="text-end">Total</td>
                        <td class="text-end amount"><?= e(rupiah($totalAmount)) ?></td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
