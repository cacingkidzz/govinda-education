<?php
declare(strict_types=1);

/**
 * Rekap pembayaran & potensi dana per group.
 *
 * Dua sudut pandang ditampilkan berdampingan:
 * - Tagihan: seluruh tagihan paket non-batal yang diterbitkan pada rentang
 *   tanggal, termasuk milik siswa yang belum membayar. Dari sini terlihat
 *   total dana yang akan masuk ke setiap group.
 * - Pembayaran: uang yang benar-benar diterima pada rentang tanggal,
 *   dihitung dari alokasi pembayaran ke tagihan agar tidak terhitung ganda.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_common.php';
require_login();
require_role(['admin', 'petugas']);

$range = report_date_range();
$dateFrom = $range['from'];
$dateTo = $range['to'];

$method = get_string('method');
$method = in_array($method, Payment::methodOptions(), true) ? $method : '';
$groupId = get_string('group_id');
$groupId = ctype_digit($groupId) ? $groupId : '';
$includeEmpty = get_string('include_empty') === '1';

$paymentFilters = [
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'method'    => $method,
    'group_id'  => $groupId,
];

$payments = Payment::groupReport($paymentFilters);
$billing = Invoice::groupBilling([
    'date_from'    => $dateFrom,
    'date_to'      => $dateTo,
    'group_id'     => $groupId,
    'invoice_type' => Invoice::TYPE_PACKAGE,
]);

$groups = Group::all();
$groupsById = [];
foreach ($groups as $group) {
    $groupsById[(int) $group['id']] = $group;
}

// ---------------------------------------------------------------------
// Gabungkan tagihan (semua siswa, dibayar atau belum) dengan pembayaran.
// ---------------------------------------------------------------------
$merged = [];
$initGroup = static function (int $id, string $name) use (&$merged): void {
    if (isset($merged[$id])) {
        return;
    }
    $merged[$id] = [
        'group_id'          => $id,
        'group_name'        => $name,
        'students'          => [],
        'student_count'     => 0,
        'paid_student_count' => 0,
        'invoice_count'     => 0,
        'unpaid_count'      => 0,
        'billed_amount'     => 0.0,
        'received_amount'   => 0.0,
        'debt_amount'       => 0.0,
        'oldest_due_date'   => null,
        'payment_count'     => 0,
        'period_amount'     => 0.0,
        'last_payment_date' => null,
    ];
};

foreach ($billing as $item) {
    $id = (int) $item['group_id'];
    $initGroup($id, (string) $item['group_name']);
    $merged[$id]['invoice_count'] += (int) $item['invoice_count'];
    $merged[$id]['unpaid_count'] += (int) $item['unpaid_count'];
    $merged[$id]['billed_amount'] += (float) $item['billed_amount'];
    $merged[$id]['received_amount'] += (float) $item['received_amount'];
    $merged[$id]['debt_amount'] += (float) $item['debt_amount'];
    if ($item['oldest_due_date'] !== null) {
        $current = $merged[$id]['oldest_due_date'];
        if ($current === null || (string) $item['oldest_due_date'] < $current) {
            $merged[$id]['oldest_due_date'] = (string) $item['oldest_due_date'];
        }
    }

    foreach ($item['students'] as $student) {
        $sid = (int) $student['student_id'];
        $merged[$id]['students'][$sid] = array_merge($student, [
            'payment_count'     => 0,
            'period_amount'     => 0.0,
            'last_payment_date' => null,
        ]);
    }
}

foreach ($payments as $item) {
    $id = (int) $item['group_id'];
    $initGroup($id, (string) $item['group_name']);
    $merged[$id]['payment_count'] += (int) $item['payment_count'];
    $merged[$id]['period_amount'] += (float) $item['total_amount'];
    if ($item['last_payment_date'] !== null) {
        $date = (string) $item['last_payment_date'];
        $current = $merged[$id]['last_payment_date'];
        if ($current === null || $date > $current) {
            $merged[$id]['last_payment_date'] = $date;
        }
    }

    foreach ($item['students'] as $student) {
        $sid = (int) $student['student_id'];
        if (!isset($merged[$id]['students'][$sid])) {
            $merged[$id]['students'][$sid] = [
                'student_id'      => $sid,
                'student_code'    => (string) $student['student_code'],
                'student_name'    => (string) $student['student_name'],
                'school_class'    => (string) $student['school_class'],
                'invoice_count'   => 0,
                'unpaid_count'    => 0,
                'billed_amount'   => 0.0,
                'received_amount' => 0.0,
                'debt_amount'     => 0.0,
                'oldest_due_date' => null,
            ];
        }
        $merged[$id]['students'][$sid]['payment_count'] = (int) $student['payment_count'];
        $merged[$id]['students'][$sid]['period_amount'] = (float) $student['total_amount'];
        $merged[$id]['students'][$sid]['last_payment_date'] = (string) $student['last_payment_date'];
    }
}

// Group tanpa tagihan & tanpa pembayaran tetap ditampilkan bila diminta.
if ($includeEmpty) {
    foreach ($groups as $group) {
        $id = (int) $group['id'];
        if ($groupId !== '' && $id !== (int) $groupId) {
            continue;
        }
        $initGroup($id, (string) $group['name']);
    }
}

foreach ($merged as &$item) {
    $item['students'] = array_values($item['students']);
    usort(
        $item['students'],
        static fn (array $a, array $b): int => strcmp((string) $a['student_name'], (string) $b['student_name'])
    );
    $item['student_count'] = count($item['students']);
    $item['paid_student_count'] = count(array_filter(
        $item['students'],
        static fn (array $student): bool => (float) $student['period_amount'] > 0
    ));
}
unset($item);

$report = array_values($merged);
usort(
    $report,
    static fn (array $a, array $b): int => strcmp((string) $a['group_name'], (string) $b['group_name'])
);

$totalBilled = 0.0;
$totalBilledReceived = 0.0;
$totalOutstanding = 0.0;
$totalInvoiceCount = 0;
$totalUnpaidCount = 0;
$totalPeriodAmount = 0.0;
$totalPeriodPayments = 0;
$totalPaidStudents = 0;
$groupsWithDebt = 0;
foreach ($report as $item) {
    $totalBilled += (float) $item['billed_amount'];
    $totalBilledReceived += (float) $item['received_amount'];
    $totalOutstanding += (float) $item['debt_amount'];
    $totalInvoiceCount += (int) $item['invoice_count'];
    $totalUnpaidCount += (int) $item['unpaid_count'];
    $totalPeriodAmount += (float) $item['period_amount'];
    $totalPeriodPayments += (int) $item['payment_count'];
    $totalPaidStudents += (int) $item['paid_student_count'];
    if ((float) $item['debt_amount'] > 0) {
        $groupsWithDebt++;
    }
}
$averagePerStudent = $totalPaidStudents > 0 ? $totalPeriodAmount / $totalPaidStudents : 0.0;

$hasFilter = !$range['isDefault'] || $method !== '' || $groupId !== '' || $includeEmpty;

$csvParams = array_merge([
    'date_from'     => $dateFrom,
    'date_to'       => $dateTo,
    'method'        => $method,
    'group_id'      => $groupId,
    'include_empty' => $includeEmpty ? '1' : '',
], ['export' => 'csv']);

if (get_string('export') === 'csv') {
    $csvRows = [];
    foreach ($report as $item) {
        $meta = $groupsById[(int) $item['group_id']] ?? null;
        $groupCode = $meta !== null ? (string) $meta['code'] : '';

        if ($item['students'] === []) {
            $csvRows[] = [
                (string) $item['group_name'], $groupCode, '', '', '',
                '0', '0', '0', '0', '0', '', '',
            ];
            continue;
        }

        foreach ($item['students'] as $student) {
            $csvRows[] = [
                (string) $item['group_name'],
                $groupCode,
                (string) $student['student_code'],
                (string) $student['student_name'],
                (string) $student['school_class'],
                (string) (int) $student['invoice_count'],
                rupiah((float) $student['billed_amount'], false),
                rupiah((float) $student['received_amount'], false),
                rupiah((float) $student['debt_amount'], false),
                rupiah((float) $student['period_amount'], false),
                (string) (int) $student['payment_count'],
                $student['last_payment_date'] !== null ? (string) $student['last_payment_date'] : '',
            ];
        }
        $csvRows[] = [
            'TOTAL ' . $item['group_name'],
            $groupCode,
            '',
            (string) (int) $item['student_count'] . ' siswa',
            '',
            (string) (int) $item['invoice_count'],
            rupiah((float) $item['billed_amount'], false),
            rupiah((float) $item['received_amount'], false),
            rupiah((float) $item['debt_amount'], false),
            rupiah((float) $item['period_amount'], false),
            (string) (int) $item['payment_count'],
            '',
        ];
    }
    $csvRows[] = [];
    $csvRows[] = [
        'TOTAL SELURUH GROUP',
        '',
        '',
        '',
        '',
        (string) $totalInvoiceCount,
        rupiah($totalBilled, false),
        rupiah($totalBilledReceived, false),
        rupiah($totalOutstanding, false),
        rupiah($totalPeriodAmount, false),
        (string) $totalPeriodPayments,
        '',
    ];

    report_send_csv(
        'rekap-pembayaran-per-group-' . $dateFrom . '_' . $dateTo . '.csv',
        ['Kelompok', 'Kode Kelompok', 'Kode Siswa', 'Nama Siswa', 'Kelas',
            'Jumlah Tagihan', 'Total Tagihan', 'Sudah Masuk', 'Sisa Tagihan',
            'Diterima Periode', 'Transaksi', 'Terakhir Bayar'],
        $csvRows
    );
}

$pageTitle = 'Rekap Pembayaran per Group';
$pageSubtitle = 'Total tagihan dan uang yang diterima dari seluruh siswa dalam setiap group.';
$breadcrumbs = [
    ['label' => 'Laporan', 'url' => url('admin/laporan/keuangan.php')],
    ['label' => 'Pembayaran per Group'],
];
$activeMenu = 'laporan-pembayaran-group';

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-blue">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total Tagihan</div>
                    <div class="stat-value stat-money"><?= e(rupiah($totalBilled)) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-receipt"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-list-ol"></i>
                <?= e(number_format($totalInvoiceCount, 0, ',', '.')) ?> tagihan paket diterbitkan
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-green">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Dana Sudah Masuk</div>
                    <div class="stat-value stat-money"><?= e(rupiah($totalBilledReceived)) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-check2-circle"></i>Untuk tagihan pada rentang ini</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-rose">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Dana Belum Masuk</div>
                    <div class="stat-value stat-money"><?= e(rupiah($totalOutstanding)) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-exclamation-circle"></i>
                <?= e(number_format($totalUnpaidCount, 0, ',', '.')) ?> tagihan belum dibayar
                &middot; <?= e(number_format($groupsWithDebt, 0, ',', '.')) ?> group
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-teal">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Diterima pada Periode</div>
                    <div class="stat-value stat-money"><?= e(rupiah($totalPeriodAmount)) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-calendar-range"></i>
                <?= e(number_format($totalPeriodPayments, 0, ',', '.')) ?> transaksi &middot;
                <?= e(number_format($totalPaidStudents, 0, ',', '.')) ?> siswa
            </div>
        </div>
    </div>
</div>

<div class="card mb-3 no-print">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/laporan/pembayaran-group.php')) ?>" class="row g-2 align-items-end">
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
            <div class="col-xl-3 col-md-8">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="include_empty"
                           name="include_empty" <?= $includeEmpty ? 'checked' : '' ?>>
                    <label class="form-check-label" for="include_empty">
                        Tampilkan group yang belum ada tagihan/pembayaran
                    </label>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-funnel me-1"></i>Terapkan
                    </button>
                    <?php if ($hasFilter): ?>
                        <a href="<?= e(url('admin/laporan/pembayaran-group.php')) ?>" class="btn btn-light"
                           title="Reset filter">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="text-secondary small">
        <?= e(number_format(count($report), 0, ',', '.')) ?> group &middot;
        <?= e(number_format($totalPaidStudents, 0, ',', '.')) ?> siswa membayar &middot;
        rata-rata per siswa <?= e(rupiah($averagePerStudent)) ?>
    </div>
    <?= report_toolbar(url_query('admin/laporan/pembayaran-group.php', $csvParams)) ?>
</div>

<?php if ($report === []): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-cash-stack"></i></div>
            <div class="empty-state-title">Belum ada tagihan atau pembayaran pada rentang ini</div>
            <p class="empty-state-text">
                Ubah rentang tanggal, atau centang “Tampilkan group yang belum ada tagihan/pembayaran”
                untuk melihat semua group.
            </p>
            <a href="<?= e(url('admin/laporan/pembayaran-group.php')) ?>" class="btn btn-light">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
            </a>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($report as $item): ?>
        <?php
        $meta = $groupsById[(int) $item['group_id']] ?? null;
        $groupName = $meta !== null ? (string) $meta['name'] : (string) $item['group_name'];
        $groupCode = $meta !== null ? (string) $meta['code'] : '-';
        $groupType = $meta !== null ? (string) $meta['group_type'] : '';
        $memberCount = $meta !== null ? (int) $meta['member_count'] : 0;
        $subjectNames = $meta !== null ? (string) ($meta['subject_names'] ?? '') : '';
        $students = $item['students'];
        $debtAmount = (float) $item['debt_amount'];
        $detailUrl = url_query('admin/laporan/pembayaran.php', [
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'group_id'  => (int) $item['group_id'],
            'method'    => $method !== '' ? $method : null,
        ]);
        ?>
        <div class="card mb-3">
            <div class="card-header flex-wrap gap-2">
                <div>
                    <h2 class="card-title">
                        <?= e($groupName) ?>
                        <span class="badge bg-teal-soft text-teal ms-1"><?= e($groupCode) ?></span>
                        <?php if ($groupType !== ''): ?>
                            <span class="badge text-bg-light ms-1"><?= e(package_type_label($groupType)) ?></span>
                        <?php endif; ?>
                    </h2>
                    <div class="card-subtitle">
                        <?= e((string) $memberCount) ?> siswa aktif
                        <?php if ($subjectNames !== ''): ?>
                            &middot; <?= e($subjectNames) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge text-bg-light" title="Total dana yang seharusnya masuk dari group ini">
                        Tagihan <?= e(rupiah((float) $item['billed_amount'])) ?>
                    </span>
                    <?php if ($debtAmount > 0): ?>
                        <span class="badge text-bg-danger"
                              title="Tagihan yang belum dibayar siswa">
                            Belum masuk <?= e(rupiah($debtAmount)) ?>
                        </span>
                    <?php elseif ((float) $item['billed_amount'] > 0): ?>
                        <span class="badge text-bg-success">
                            <i class="bi bi-check2-all me-1"></i>Lunas semua
                        </span>
                    <?php endif; ?>
                    <span class="badge text-bg-success" title="Uang yang diterima pada rentang tanggal">
                        <i class="bi bi-wallet2 me-1"></i><?= e(rupiah((float) $item['period_amount'])) ?>
                    </span>
                    <a href="<?= e($detailUrl) ?>" class="btn btn-light btn-sm no-print"
                       title="Lihat rincian pembayaran group ini">
                        <i class="bi bi-list-check me-1"></i>Rincian
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($students === []): ?>
                    <div class="p-4 text-center text-muted small">
                        <i class="bi bi-inbox me-1"></i>
                        Belum ada tagihan maupun pembayaran dari siswa di group ini pada rentang tanggal tersebut.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width:48px;">No.</th>
                                <th>Siswa</th>
                                <th>Kelas</th>
                                <th class="text-end" title="Total tagihan paket yang diterbitkan pada rentang ini">
                                    Tagihan
                                </th>
                                <th class="text-end" title="Pembayaran yang sudah dialokasikan ke tagihan tersebut">
                                    Sudah Masuk
                                </th>
                                <th class="text-end" title="Dana yang belum masuk dari tagihan tersebut">
                                    Sisa
                                </th>
                                <th class="text-end" title="Pembayaran yang diterima pada rentang tanggal">
                                    Diterima (Periode)
                                </th>
                                <th class="text-center">Trx</th>
                                <th>Terakhir Bayar</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($students as $index => $student): ?>
                                <tr>
                                    <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                                    <td>
                                        <span class="cell-title"><?= e($student['student_name']) ?></span>
                                        <span class="cell-sub font-monospace"><?= e($student['student_code']) ?></span>
                                    </td>
                                    <td><?= e($student['school_class'] !== '' ? $student['school_class'] : '-') ?></td>
                                    <td class="text-end text-nowrap">
                                        <?= e(rupiah((float) $student['billed_amount'])) ?>
                                        <?php if ((int) $student['unpaid_count'] > 0): ?>
                                            <div class="cell-sub text-danger">
                                                <?= e((string) (int) $student['unpaid_count']) ?> belum dibayar
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap text-success">
                                        <?= e(rupiah((float) $student['received_amount'])) ?>
                                    </td>
                                    <td class="text-end text-nowrap <?= (float) $student['debt_amount'] > 0 ? 'text-danger fw-semibold' : '' ?>">
                                        <?= e(rupiah((float) $student['debt_amount'])) ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?= e(rupiah((float) $student['period_amount'])) ?>
                                    </td>
                                    <td class="text-center"><?= e((string) (int) $student['payment_count']) ?></td>
                                    <td class="text-nowrap">
                                        <?= e($student['last_payment_date'] !== null && $student['last_payment_date'] !== ''
                                            ? tanggal_short_id((string) $student['last_payment_date'])
                                            : '-') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="3" class="text-end">Total <?= e($groupName) ?></td>
                                <td class="text-end text-nowrap"><?= e(rupiah((float) $item['billed_amount'])) ?></td>
                                <td class="text-end text-nowrap text-success"><?= e(rupiah((float) $item['received_amount'])) ?></td>
                                <td class="text-end text-nowrap <?= $debtAmount > 0 ? 'text-danger' : '' ?>">
                                    <?= e(rupiah($debtAmount)) ?>
                                </td>
                                <td class="text-end text-nowrap"><?= e(rupiah((float) $item['period_amount'])) ?></td>
                                <td class="text-center"><?= e((string) (int) $item['payment_count']) ?></td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white small text-muted d-flex flex-wrap justify-content-between gap-2">
                <span>
                    <?php if ($debtAmount > 0): ?>
                        <i class="bi bi-hourglass-split me-1"></i>
                        Potensi dana masuk <strong class="text-dark"><?= e(rupiah($debtAmount)) ?></strong>
                        dari <?= e((string) (int) $item['unpaid_count']) ?> tagihan belum dibayar.
                        <?php if ($item['oldest_due_date'] !== null): ?>
                            Jatuh tempo paling awal <?= e(tanggal_short_id((string) $item['oldest_due_date'])) ?>.
                        <?php endif; ?>
                    <?php else: ?>
                        <i class="bi bi-check2-all me-1"></i>Tidak ada tagihan tertunggak pada group ini.
                    <?php endif; ?>
                </span>
                <span>
                    <?= e((string) (int) $item['paid_student_count']) ?> dari
                    <?= e((string) (int) $item['student_count']) ?> siswa membayar pada periode ini.
                </span>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="master-note">
    <i class="bi bi-info-circle"></i>
    <span>
        <strong>Total Tagihan</strong> menghitung semua tagihan paket non-batal yang terbit pada rentang tanggal,
        termasuk siswa yang belum membayar — dari selisihnya terlihat dana yang akan masuk.
        <strong>Diterima (Periode)</strong> menghitung uang yang benar-benar masuk pada rentang tanggal,
        diambil dari alokasi pembayaran ke tiap tagihan agar nota multi-tagihan tidak terhitung ganda.
    </span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
