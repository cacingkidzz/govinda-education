<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_common.php';
require_login();
require_role(['admin', 'petugas']);

$dateFromRaw = get_string('date_from');
$dateFrom = valid_date($dateFromRaw) ? $dateFromRaw : '';
$dateToRaw = get_string('date_to');
$dateTo = valid_date($dateToRaw) ? $dateToRaw : '';
$groupId = get_string('group_id');
$groupId = ctype_digit($groupId) ? $groupId : '';
$studentId = get_string('student_id');
$studentId = ctype_digit($studentId) ? $studentId : '';

$filters = [
    'date_from'    => $dateFrom,
    'date_to'      => $dateTo,
    'group_id'     => $groupId,
    'student_id'   => $studentId,
    'invoice_type' => Invoice::TYPE_PACKAGE,
];
$hasFilter = $dateFrom !== '' || $dateTo !== '' || $groupId !== '' || $studentId !== '';

$summary = Invoice::debtSummary($filters);
$studentRows = array_values(array_filter(
    Invoice::debtByStudent($filters),
    static fn (array $row): bool => (float) $row['debt_amount'] > 0
));
$unpaidInvoices = Invoice::all(array_merge($filters, ['status' => 'belum_bayar']));
$groups = Group::all();
$students = Student::all();

$csvParams = array_merge($filters, ['export' => 'csv']);
if (get_string('export') === 'csv') {
    $csvRows = [];
    foreach ($unpaidInvoices as $row) {
        $csvRows[] = [
            (string) $row['student_code'],
            (string) $row['student_name'],
            (string) $row['group_name'],
            'Paket ' . (int) $row['package_number'],
            (string) $row['invoice_number'],
            (string) $row['issue_date'],
            (string) $row['due_date'],
            rupiah((float) $row['total_amount'], false),
            rupiah(0, false),
            rupiah((float) $row['total_amount'], false),
            invoice_status_label((string) $row['status']),
        ];
    }
    report_send_csv(
        'laporan-utang-' . date('Ymd') . '.csv',
        ['Kode Siswa', 'Nama Siswa', 'Kelompok', 'Paket', 'Invoice', 'Tanggal Terbit',
            'Jatuh Tempo', 'Total Tagihan', 'Dibayar', 'Utang', 'Status'],
        $csvRows
    );
}

$pageTitle = 'Laporan Utang';
$pageSubtitle = 'Total Utang = Total Tagihan - Total Pembayaran yang Dialokasikan.';
$breadcrumbs = [['label' => 'Laporan', 'url' => url('admin/laporan/keuangan.php')], ['label' => 'Utang']];
$activeMenu = 'laporan-utang';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-blue">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total Tagihan</div>
                    <div class="stat-value stat-money"><?= e(rupiah($summary['billed_amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-receipt"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-list-ol"></i>
                <?= e(number_format($summary['billed_count'], 0, ',', '.')) ?> tagihan non-batal
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-green">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total Dibayar</div>
                    <div class="stat-value stat-money"><?= e(rupiah($summary['received_amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-check2-all"></i>
                <?= e(number_format($summary['paid_count'], 0, ',', '.')) ?> tagihan lunas
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-rose">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total Utang</div>
                    <div class="stat-value stat-money"><?= e(rupiah($summary['debt_amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-exclamation-circle"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-hourglass-split"></i>
                <?= e(number_format($summary['unpaid_count'], 0, ',', '.')) ?> tagihan belum dibayar
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-navy">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Siswa Berutang</div>
                    <div class="stat-value"><?= e(number_format($summary['debtor_count'], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-person-exclamation"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-info-circle"></i>Utang tidak menghalangi paket</div>
        </div>
    </div>
</div>

<div class="card mb-3 no-print">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/laporan/utang.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-6">
                <label for="date_from" class="form-label">Tanggal terbit dari</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-xl-2 col-md-6">
                <label for="date_to" class="form-label">Sampai</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-xl-3 col-md-4">
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
            <div class="col-xl-3 col-md-4">
                <label for="student_id" class="form-label">Siswa</label>
                <select class="form-select" id="student_id" name="student_id">
                    <option value="">Semua siswa</option>
                    <?php foreach ($students as $studentRow): ?>
                        <option value="<?= (int) $studentRow['id'] ?>" <?= $studentId === (string) $studentRow['id'] ? 'selected' : '' ?>>
                            <?= e($studentRow['name']) ?> (<?= e($studentRow['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/laporan/utang.php')) ?>" class="btn btn-light" title="Reset">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <div>
            <h2 class="card-title">Rekap Utang per Siswa</h2>
            <div class="card-subtitle">
                <?= e(number_format(count($studentRows), 0, ',', '.')) ?> siswa memiliki sisa utang.
            </div>
        </div>
        <?= report_toolbar(url_query('admin/laporan/utang.php', $csvParams)) ?>
    </div>
    <div class="card-body p-0">
        <?php if ($studentRows === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-check2-all"></i></div>
                <div class="empty-state-title">Tidak ada utang pada filter ini</div>
                <p class="empty-state-text">Semua tagihan paket sudah lunas atau dibatalkan.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width:40px;">No.</th>
                        <th>Siswa</th>
                        <th class="text-end">Tagihan</th>
                        <th class="text-end">Belum Dibayar</th>
                        <th class="text-end">Total Tagihan</th>
                        <th class="text-end">Total Dibayar</th>
                        <th class="text-end">Total Utang</th>
                        <th class="no-sort text-end">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($studentRows as $index => $row): ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td>
                                <a href="<?= e(url('admin/siswa/detail.php?id=' . (int) $row['student_id'])) ?>"
                                   class="cell-title link-dark text-decoration-none">
                                    <?= e($row['student_name']) ?>
                                </a>
                                <span class="cell-sub font-monospace"><?= e($row['student_code']) ?></span>
                            </td>
                            <td class="text-end"><?= e((string) (int) $row['invoice_count']) ?></td>
                            <td class="text-end"><?= e((string) (int) $row['unpaid_count']) ?></td>
                            <td class="text-end amount"><?= e(rupiah((float) $row['billed_amount'])) ?></td>
                            <td class="text-end amount text-success"><?= e(rupiah((float) $row['received_amount'])) ?></td>
                            <td class="text-end amount text-danger fw-semibold"><?= e(rupiah((float) $row['debt_amount'])) ?></td>
                            <td class="text-end">
                                <a href="<?= e(url('admin/pembayaran/tambah.php?student_id=' . (int) $row['student_id'])) ?>"
                                   class="btn btn-success btn-sm" title="Catat pelunasan">
                                    <i class="bi bi-cash-stack"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="2" class="text-end">Total</th>
                        <th class="text-end"><?= e(number_format($summary['billed_count'], 0, ',', '.')) ?></th>
                        <th class="text-end"><?= e(number_format($summary['unpaid_count'], 0, ',', '.')) ?></th>
                        <th class="text-end amount"><?= e(rupiah($summary['billed_amount'])) ?></th>
                        <th class="text-end amount"><?= e(rupiah($summary['received_amount'])) ?></th>
                        <th class="text-end amount"><?= e(rupiah($summary['debt_amount'])) ?></th>
                        <th></th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Rincian Utang per Paket</h2>
            <div class="card-subtitle">Tagihan paket yang belum dibayar pada filter aktif.</div>
        </div>
        <span class="badge text-bg-light"><?= e(number_format(count($unpaidInvoices), 0, ',', '.')) ?> tagihan</span>
    </div>
    <div class="card-body p-0">
        <?php if ($unpaidInvoices === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-receipt"></i></div>
                <div class="empty-state-title">Tidak ada tagihan belum dibayar</div>
                <p class="empty-state-text">Semua tagihan paket pada filter ini sudah lunas atau dibatalkan.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width:40px;">No.</th>
                        <th>Invoice</th>
                        <th>Siswa</th>
                        <th>Kelompok</th>
                        <th class="no-sort">Paket</th>
                        <th>Terbit</th>
                        <th>Jatuh Tempo</th>
                        <th class="text-end">Total Tagihan</th>
                        <th class="no-sort text-end">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($unpaidInvoices as $index => $row): ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td>
                                <a href="<?= e(url('admin/tagihan/detail.php?id=' . (int) $row['id'])) ?>"
                                   class="invoice-number text-decoration-none">
                                    <?= e($row['invoice_number']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="cell-title"><?= e($row['student_name']) ?></span>
                                <span class="cell-sub font-monospace"><?= e($row['student_code']) ?></span>
                            </td>
                            <td><?= e($row['group_name']) ?></td>
                            <td class="text-nowrap">Paket <?= e((string) (int) $row['package_number']) ?></td>
                            <td class="text-nowrap"><?= e(tanggal_short_id((string) $row['issue_date'])) ?></td>
                            <td class="text-nowrap">
                                <?= e(tanggal_short_id((string) $row['due_date'])) ?>
                                <?php if ((string) $row['due_date'] < date('Y-m-d')): ?>
                                    <span class="badge text-bg-danger d-block mt-1">Lewat tempo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end amount fw-semibold"><?= e(rupiah((float) $row['total_amount'])) ?></td>
                            <td class="text-end">
                                <a href="<?= e(url('admin/pembayaran/tambah.php?invoice_id=' . (int) $row['id'])) ?>"
                                   class="btn btn-success btn-sm" title="Catat pelunasan">
                                    <i class="bi bi-cash-stack"></i>
                                </a>
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
    <span>Total Utang dihitung dari tagihan non-dibatalkan dikurangi pembayaran berhasil yang
        dialokasikan ke tagihan tersebut. Utang tidak menghalangi pembuatan paket berikutnya.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
