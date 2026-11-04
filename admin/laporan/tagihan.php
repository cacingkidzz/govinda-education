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
$status = in_array($status, ['belum_bayar', 'lunas', 'dibatalkan'], true) ? $status : '';
$q = get_string('q');
$groupId = get_string('group_id');
$groupId = ctype_digit($groupId) ? $groupId : '';

$filters = [
    'date_from'    => $dateFrom,
    'date_to'      => $dateTo,
    'status'       => $status,
    'q'            => $q,
    'group_id'     => $groupId,
    'invoice_type' => Invoice::TYPE_PACKAGE,
];

$rows = Invoice::all($filters);
$summary = Invoice::summary($filters);
$groups = Group::all();
$hasFilter = !$range['isDefault'] || $status !== '' || $q !== '' || $groupId !== '';

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
            (string) $row['invoice_number'],
            (string) $row['issue_date'],
            (string) $row['due_date'],
            (string) $row['student_code'],
            (string) $row['student_name'],
            (string) $row['group_name'],
            package_type_label((string) $row['group_type']),
            'Paket ' . (int) $row['package_number'],
            rupiah((float) $row['unit_amount'], false),
            rupiah((float) $row['discount_amount'], false),
            rupiah((float) $row['total_amount'], false),
            invoice_status_label((string) $row['status']),
            (string) ($row['payment_number'] ?? ''),
        ];
    }
    report_send_csv(
        'laporan-tagihan-' . $dateFrom . '_' . $dateTo . '.csv',
        ['Nomor Invoice', 'Terbit', 'Jatuh Tempo', 'Kode Siswa', 'Nama Siswa', 'Kelompok',
            'Jenis', 'Paket', 'Tarif Dasar', 'Diskon', 'Total', 'Status', 'Nomor Nota'],
        $csvRows
    );
}

$pageTitle = 'Laporan Tagihan';
$pageSubtitle = 'Nilai tagihan paket yang diterbitkan, dibedakan dari uang yang sudah diterima.';
$breadcrumbs = [['label' => 'Laporan', 'url' => url('admin/laporan/keuangan.php')], ['label' => 'Tagihan']];
$activeMenu = 'laporan-tagihan';
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
                    <div class="stat-label">Tagihan Diterbitkan</div>
                    <div class="stat-value stat-money"><?= e(rupiah($summary['issued_amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-receipt"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-list-ol"></i>
                <?= e(number_format($summary['issued_count'], 0, ',', '.')) ?> tagihan
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-green">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Lunas</div>
                    <div class="stat-value stat-money"><?= e(rupiah($summary['paid_amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-list-ol"></i>
                <?= e(number_format($summary['paid_count'], 0, ',', '.')) ?> tagihan
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-gold">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Belum Dibayar</div>
                    <div class="stat-value stat-money"><?= e(rupiah($summary['unpaid_amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-list-ol"></i>
                <?= e(number_format($summary['unpaid_count'], 0, ',', '.')) ?> tagihan
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-navy">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Uang Diterima</div>
                    <div class="stat-value stat-money"><?= e(rupiah($summary['received_amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-check2-all"></i>
                <?= e(number_format($summary['received_count'], 0, ',', '.')) ?> pembayaran
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info d-flex gap-2 align-items-start">
    <i class="bi bi-info-circle-fill mt-1"></i>
    <div class="small">
        <strong>Diterbitkan</strong> adalah nilai tagihan yang sah (tanpa yang dibatalkan).
        <strong>Diterima</strong> adalah uang kas dari pembayaran berhasil. Keduanya tidak boleh disamakan
        karena bisa berbeda tanggal dan sebagian tagihan belum dibayar.
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/laporan/tagihan.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-6">
                <label for="date_from" class="form-label">Terbit dari</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-xl-2 col-md-6">
                <label for="date_to" class="form-label">Sampai</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua status</option>
                    <option value="belum_bayar" <?= $status === 'belum_bayar' ? 'selected' : '' ?>>Belum Bayar</option>
                    <option value="lunas" <?= $status === 'lunas' ? 'selected' : '' ?>>Lunas</option>
                    <option value="dibatalkan" <?= $status === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
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
                       placeholder="Nama, kode, invoice">
            </div>
            <div class="col-xl-2 col-md-8 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/laporan/tagihan.php')) ?>" class="btn btn-light" title="Reset">
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
            <h2 class="card-title">Rincian Tagihan</h2>
            <div class="card-subtitle">
                <?= e(number_format(count($rows), 0, ',', '.')) ?> tagihan &middot;
                diterbitkan <?= e(rupiah($summary['issued_amount'])) ?>
            </div>
        </div>
        <?= report_toolbar(url_query('admin/laporan/tagihan.php', $csvParams)) ?>
    </div>
    <div class="card-body p-0">
        <?php if ($rows === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-receipt"></i></div>
                <div class="empty-state-title">Tidak ada tagihan pada rentang ini</div>
                <p class="empty-state-text">Ubah rentang tanggal atau filter status.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width:40px;">No.</th>
                        <th>Nomor Invoice</th>
                        <th>Terbit</th>
                        <th>Siswa</th>
                        <th>Kelompok</th>
                        <th class="no-sort">Paket</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th>Nota</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td>
                                <a href="<?= e(url('admin/tagihan/detail.php?id=' . (int) $row['id'])) ?>"
                                   class="invoice-number text-decoration-none">
                                    <?= e($row['invoice_number']) ?>
                                </a>
                            </td>
                            <td class="text-nowrap"><?= e(tanggal_short_id((string) $row['issue_date'])) ?></td>
                            <td>
                                <span class="cell-title"><?= e($row['student_name']) ?></span>
                                <span class="cell-sub font-monospace"><?= e($row['student_code']) ?></span>
                            </td>
                            <td><?= e($row['group_name']) ?></td>
                            <td class="text-nowrap">Paket <?= e((string) (int) $row['package_number']) ?></td>
                            <td class="text-end amount"><?= e(rupiah((float) $row['total_amount'])) ?></td>
                            <td><?= invoice_status_badge((string) $row['status']) ?></td>
                            <td class="small"><?= e((string) ($row['payment_number'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
