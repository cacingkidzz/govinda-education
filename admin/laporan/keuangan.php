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

$filters = [
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'group_id'  => $groupId,
];

$summary = Report::finance($filters);
$monthly = Report::monthlyIssuedVsReceived(6);
$expenseSummary = Report::expenses($filters);
$monthlyExpense = Report::monthlyExpenses(6);
$groups = Group::all();
$hasFilter = !$range['isDefault'] || $groupId !== '';

$outstanding = max(0.0, $summary['issued_amount'] - $summary['received_amount']);
$netCash = $summary['received_amount'] - $expenseSummary['amount'];
$collectionRate = $summary['issued_amount'] > 0
    ? min(100.0, $summary['received_amount'] / $summary['issued_amount'] * 100)
    : 0.0;

$csvParams = array_merge([
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'group_id'  => $groupId,
], ['export' => 'csv']);

if (get_string('export') === 'csv') {
    $csvRows = [
        ['Ringkasan', 'Jumlah', 'Nominal'],
        ['Tagihan diterbitkan', $summary['issued_count'], rupiah($summary['issued_amount'], false)],
        ['Tagihan lunas', $summary['paid_count'], rupiah($summary['paid_amount'], false)],
        ['Tagihan belum dibayar', $summary['unpaid_count'], rupiah($summary['unpaid_amount'], false)],
        ['Tagihan dibatalkan', $summary['cancelled_count'], rupiah($summary['cancelled_amount'], false)],
        ['Pembayaran diterima', $summary['received_count'], rupiah($summary['received_amount'], false)],
        ['Pengeluaran operasional', $expenseSummary['count'], rupiah($expenseSummary['amount'], false)],
        ['Saldo kas (diterima - pengeluaran)', '', rupiah($netCash, false)],
        [],
        ['Bulan', 'Tagihan Diterbitkan', 'Pembayaran Diterima', 'Pengeluaran', 'Saldo Kas'],
    ];
    foreach ($monthly['labels'] as $index => $label) {
        $csvRows[] = [
            $label,
            rupiah($monthly['issued'][$index], false),
            rupiah($monthly['received'][$index], false),
            rupiah((float) ($monthlyExpense['amounts'][$index] ?? 0), false),
            rupiah(
                (float) $monthly['received'][$index] - (float) ($monthlyExpense['amounts'][$index] ?? 0),
                false
            ),
        ];
    }
    report_send_csv(
        'laporan-keuangan-' . $dateFrom . '_' . $dateTo . '.csv',
        ['Keterangan', 'Jumlah', 'Nominal'],
        $csvRows
    );
}

$pageTitle = 'Rekap Keuangan';
$pageSubtitle = 'Perbandingan nilai tagihan yang diterbitkan dengan uang yang benar-benar diterima.';
$breadcrumbs = [['label' => 'Laporan', 'url' => url('admin/laporan/keuangan.php')], ['label' => 'Rekap Keuangan']];
$activeMenu = 'laporan-keuangan';
$pageScripts = ['vendor/chartjs/chart.umd.min.js'];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-xl-4 col-md-6">
        <div class="stat-card stat-teal">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Tagihan Diterbitkan</div>
                    <div class="stat-value stat-money"><?= e(rupiah($summary['issued_amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-receipt"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-list-ol"></i>
                <?= e(number_format($summary['issued_count'], 0, ',', '.')) ?> tagihan sah
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card stat-green">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Uang Diterima</div>
                    <div class="stat-value stat-money"><?= e(rupiah($summary['received_amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-check2-circle"></i>
                <?= e(number_format($summary['received_count'], 0, ',', '.')) ?> pembayaran lunas
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card stat-gold">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Pengeluaran Operasional</div>
                    <div class="stat-value stat-money"><?= e(rupiah($expenseSummary['amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-arrow-up-right-circle"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-journal-text"></i>
                <?= e(number_format($expenseSummary['count'], 0, ',', '.')) ?> transaksi tercatat
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card stat-navy">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Saldo Kas</div>
                    <div class="stat-value stat-money <?= $netCash < 0 ? 'text-warning' : '' ?>">
                        <?= e(rupiah($netCash)) ?>
                    </div>
                </div>
                <div class="stat-icon"><i class="bi bi-safe2"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-info-circle"></i>Uang diterima dikurangi pengeluaran</div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card stat-gold">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Belum Tertagih</div>
                    <div class="stat-value stat-money"><?= e(rupiah($summary['unpaid_amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-exclamation-circle"></i>
                <?= e(number_format($summary['unpaid_count'], 0, ',', '.')) ?> tagihan belum bayar
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card stat-navy">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Rasio Penerimaan</div>
                    <div class="stat-value"><?= e(number_format($collectionRate, 1, ',', '.')) ?>%</div>
                </div>
                <div class="stat-icon"><i class="bi bi-pie-chart"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-info-circle"></i>Diterima dibanding diterbitkan</div>
        </div>
    </div>
</div>

<div class="alert alert-info d-flex gap-2 align-items-start">
    <i class="bi bi-info-circle-fill mt-1"></i>
    <div class="small">
        <strong>Tagihan diterbitkan</strong> menunjukkan nilai tagihan yang sah pada rentang tanggal terbit.
        <strong>Uang diterima</strong> menunjukkan kas yang masuk pada rentang tanggal pembayaran.
        <strong>Pengeluaran operasional</strong> menunjukkan biaya bimbel yang tercatat pada rentang tanggal pengeluaran.
        <strong>Saldo kas</strong> dihitung dari uang diterima dikurangi pengeluaran tercatat.
        Selisih belum tentu tunggakan karena tanggal terbit dan tanggal bayar bisa berbeda periode.
        Tagihan dan pengeluaran yang dibatalkan tidak dihitung.
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/laporan/keuangan.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-3 col-md-6">
                <label for="date_from" class="form-label">Dari tanggal</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-xl-3 col-md-6">
                <label for="date_to" class="form-label">Sampai tanggal</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-xl-4 col-md-6">
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
            <div class="col-xl-2 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/laporan/keuangan.php')) ?>" class="btn btn-light" title="Reset">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Diterbitkan vs Diterima</h2>
                    <div class="card-subtitle">Enam bulan terakhir.</div>
                </div>
            </div>
            <div class="card-body">
                <canvas id="financeChart" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Rincian Nilai</h2>
                    <div class="card-subtitle">
                        <?= e(tanggal_short_id($dateFrom)) ?> â€“ <?= e(tanggal_short_id($dateTo)) ?>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0">
                        <tbody>
                        <tr>
                            <td>Tagihan diterbitkan</td>
                            <td class="text-end amount"><?= e(rupiah($summary['issued_amount'])) ?></td>
                        </tr>
                        <tr>
                            <td>Tagihan lunas</td>
                            <td class="text-end amount text-success"><?= e(rupiah($summary['paid_amount'])) ?></td>
                        </tr>
                        <tr>
                            <td>Tagihan belum dibayar</td>
                            <td class="text-end amount text-warning"><?= e(rupiah($summary['unpaid_amount'])) ?></td>
                        </tr>
                        <tr>
                            <td>Tagihan dibatalkan</td>
                            <td class="text-end amount text-muted"><?= e(rupiah($summary['cancelled_amount'])) ?></td>
                        </tr>
                        <tr class="fw-semibold">
                            <td>Uang diterima</td>
                            <td class="text-end amount"><?= e(rupiah($summary['received_amount'])) ?></td>
                        </tr>
                        <tr>
                            <td>Pengeluaran operasional</td>
                            <td class="text-end amount text-warning"><?= e(rupiah($expenseSummary['amount'])) ?></td>
                        </tr>
                        <tr class="fw-semibold">
                            <td>Saldo kas</td>
                            <td class="text-end amount <?= $netCash < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= e(rupiah($netCash)) ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Selisih diterbitkan âˆ’ diterima</td>
                            <td class="text-end amount"><?= e(rupiah($outstanding)) ?></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">
                <?= report_toolbar(url_query('admin/laporan/keuangan.php', $csvParams)) ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Rekap Bulanan</h2>
            <div class="card-subtitle">Tagihan terbit, pembayaran masuk, pengeluaran, dan saldo kas per bulan.</div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead>
                <tr>
                    <th>Bulan</th>
                    <th class="text-end">Tagihan Diterbitkan</th>
                    <th class="text-end">Pembayaran Diterima</th>
                    <th class="text-end">Pengeluaran</th>
                    <th class="text-end">Saldo Kas</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($monthly['labels'] as $index => $label): ?>
                    <?php
                    $monthExpense = (float) ($monthlyExpense['amounts'][$index] ?? 0);
                    $monthNet = (float) $monthly['received'][$index] - $monthExpense;
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= e($label) ?></td>
                        <td class="text-end amount"><?= e(rupiah((float) $monthly['issued'][$index])) ?></td>
                        <td class="text-end amount"><?= e(rupiah((float) $monthly['received'][$index])) ?></td>
                        <td class="text-end amount text-warning"><?= e(rupiah($monthExpense)) ?></td>
                        <td class="text-end amount <?= $monthNet < 0 ? 'text-danger' : 'text-success' ?>">
                            <?= e(rupiah($monthNet)) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$pageInlineScript = '
(function () {
    "use strict";
    const canvas = document.getElementById("financeChart");
    if (!canvas || typeof Chart === "undefined") {
        return;
    }
    new Chart(canvas, {
        type: "bar",
        data: {
            labels: ' . js_json($monthly['labels']) . ',
            datasets: [
                {
                    label: "Tagihan Diterbitkan",
                    data: ' . js_json($monthly['issued']) . ',
                    backgroundColor: "rgba(15, 118, 110, 0.75)",
                    borderRadius: 6,
                    maxBarThickness: 26
                },
                {
                    label: "Pembayaran Diterima",
                    data: ' . js_json($monthly['received']) . ',
                    backgroundColor: "rgba(245, 158, 11, 0.85)",
                    borderRadius: 6,
                    maxBarThickness: 26
                },
                {
                    label: "Pengeluaran",
                    data: ' . js_json($monthlyExpense['amounts']) . ',
                    backgroundColor: "rgba(220, 53, 69, 0.75)",
                    borderRadius: 6,
                    maxBarThickness: 26
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: "bottom", labels: { usePointStyle: true, boxWidth: 8 } },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return context.dataset.label + ": Rp" + Number(context.parsed.y).toLocaleString("id-ID");
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: "rgba(227, 233, 231, 0.85)" },
                    ticks: {
                        callback: function (value) {
                            if (value >= 1000000) return "Rp" + (value / 1000000).toLocaleString("id-ID") + " jt";
                            if (value >= 1000) return "Rp" + (value / 1000).toLocaleString("id-ID") + " rb";
                            return "Rp" + value;
                        }
                    }
                },
                x: { grid: { display: false } }
            }
        }
    });
})();';
?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
