<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$user = Auth::user();
$now = new DateTimeImmutable('now');
$monthStart = $now->format('Y-m-01');
$monthEnd = $now->format('Y-m-t');

$studentActive = Student::countActive();
$groupActive = Group::countActive();
$teacherActive = Teacher::countActive();

$meetingStats = Meeting::statsBetween($monthStart, $monthEnd);
$packageStats = StudentPackage::stats();
$invoiceSummary = Invoice::summary(['invoice_type' => Invoice::TYPE_PACKAGE]);
$paymentsThisMonth = Payment::receivedBetween($monthStart, $monthEnd);
$revenue = Payment::monthlyRevenue(6);
$recentPayments = Payment::recent(6);
$recentInvoices = Invoice::recentPackage(6);
$nearingPackages = StudentPackage::nearingCompletion(5);
$debt = Invoice::debtSummary(['invoice_type' => Invoice::TYPE_PACKAGE]);
$debtors = array_values(array_filter(
    Invoice::debtByStudent(['invoice_type' => Invoice::TYPE_PACKAGE]),
    static fn (array $row): bool => (float) $row['debt_amount'] > 0
));
$debtorPreview = array_slice($debtors, 0, 6);

$packageStatusLabels = [];
$packageStatusValues = [];
foreach (StudentPackage::statusOptions() as $status) {
    $count = (int) ($packageStats[$status] ?? 0);
    if ($count > 0) {
        $packageStatusLabels[] = package_status_label($status);
        $packageStatusValues[] = $count;
    }
}
$packageStatusTotal = array_sum($packageStatusValues);
$revenueChartTotal = array_sum($revenue['totals']);

$pageTitle = 'Dashboard';
$pageSubtitle = 'Ringkasan operasional, paket belajar, dan keuangan ' . Profile::name() . '.';
$breadcrumbs = [['label' => 'Dashboard']];
$activeMenu = 'dashboard';
$pageScripts = ['vendor/chartjs/chart.umd.min.js'];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="dash-hero mb-4">
    <div class="row align-items-center g-3">
        <div class="col-lg-9">
            <div class="dash-hero-content">
                <div class="dash-hero-eyebrow">
                    <i class="bi bi-stars"></i> Dashboard Operasional
                </div>
                <h2>Selamat datang, <?= e($user['name']) ?>.</h2>
                <p>
                    Ringkasan data per <?= e(tanggal_id($now->format('Y-m-d'), true)) ?>.
                    Semua angka diambil langsung dari database, tanpa data contoh.
                </p>
                <div class="dash-hero-meta">
                    <span class="dash-pill dash-pill-gold">
                        <i class="bi bi-box-seam"></i>
                        <?= e(number_format($packageStats['aktif'], 0, ',', '.')) ?> paket aktif
                    </span>
                    <span class="dash-pill">
                        <i class="bi bi-calendar-check"></i>
                        <?= e(number_format($meetingStats[Meeting::STATUS_DONE], 0, ',', '.')) ?> pertemuan bulan ini
                    </span>
                    <span class="dash-pill">
                        <i class="bi bi-person-badge"></i>
                        <?= e(role_label((string) $user['role'])) ?>
                    </span>
                    <span class="dash-pill">
                        <i class="bi bi-clock"></i>
                        <?= e($now->format('H:i')) ?> WIB
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 d-none d-lg-flex justify-content-end">
            <div class="dash-hero-emblem">
                <img src="<?= e(Profile::logoUrl()) ?>" alt="<?= e(Profile::name()) ?>"
                     class="<?= e(Profile::logoOnDarkClass()) ?>">
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-teal">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Siswa Aktif</div>
                    <div class="stat-value"><?= e(number_format($studentActive, 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-diagram-3"></i>
                <?= e(number_format($groupActive, 0, ',', '.')) ?> kelompok aktif
                &middot; <?= e(number_format($teacherActive, 0, ',', '.')) ?> guru
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-green">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Pertemuan Terlaksana</div>
                    <div class="stat-value"><?= e(number_format($meetingStats[Meeting::STATUS_DONE], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-calendar-month"></i>
                <?= e(bulan_id((int) $now->format('n'))) ?> <?= e($now->format('Y')) ?>
                &middot; <?= e(number_format($meetingStats[Meeting::STATUS_SCHEDULED], 0, ',', '.')) ?> belum dicatat
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-gold">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Paket Aktif</div>
                    <div class="stat-value"><?= e(number_format((int) ($packageStats[StudentPackage::STATUS_AKTIF] ?? 0), 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-check2-all"></i>
                <?= e(number_format((int) ($packageStats[StudentPackage::STATUS_SELESAI] ?? 0), 0, ',', '.')) ?> paket selesai 8/8
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-navy">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Diterima Bulan Ini</div>
                    <div class="stat-value stat-money"><?= e(rupiah($paymentsThisMonth['amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-receipt"></i>
                <?= e(number_format($paymentsThisMonth['count'], 0, ',', '.')) ?> pelunasan penuh
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-blue">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Tagihan Diterbitkan</div>
                    <div class="stat-value stat-money"><?= e(rupiah($invoiceSummary['issued_amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-receipt"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-list-ol"></i>
                <?= e(number_format($invoiceSummary['issued_count'], 0, ',', '.')) ?> tagihan paket
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-rose">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total Utang</div>
                    <div class="stat-value stat-money"><?= e(rupiah($debt['debt_amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-exclamation-circle"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-clock-history"></i>
                <?= e(number_format($debt['unpaid_count'], 0, ',', '.')) ?> tagihan belum dibayar
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-green">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total Uang Diterima</div>
                    <div class="stat-value stat-money"><?= e(rupiah($debt['received_amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-shield-check"></i>
                <?= e(number_format($invoiceSummary['received_count'], 0, ',', '.')) ?> pembayaran berhasil
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-navy">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Siswa Berutang</div>
                    <div class="stat-value"><?= e(number_format($debt['debtor_count'], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-person-exclamation"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-info-circle"></i>
                Utang tidak menghalangi paket berikutnya
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Pembayaran Diterima</h2>
                    <div class="card-subtitle">
                        Enam bulan terakhir &middot; total <?= e(rupiah($revenueChartTotal)) ?>
                    </div>
                </div>
                <a href="<?= e(url('admin/laporan/keuangan.php')) ?>" class="btn btn-light btn-sm">
                    <i class="bi bi-graph-up me-1"></i>Rekap Keuangan
                </a>
            </div>
            <div class="card-body">
                <?php if ($revenueChartTotal <= 0): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-bar-chart"></i></div>
                        <div class="empty-state-title">Belum ada pembayaran</div>
                        <p class="empty-state-text">
                            Grafik muncul setelah pembayaran pertama dicatat pada menu Pembayaran.
                        </p>
                    </div>
                <?php else: ?>
                    <canvas id="revenueChart" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Status Paket</h2>
                    <div class="card-subtitle">
                        <?= e(number_format($packageStatusTotal, 0, ',', '.')) ?> paket tercatat
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($packageStatusTotal <= 0): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-box-seam"></i></div>
                        <div class="empty-state-title">Belum ada paket belajar</div>
                        <p class="empty-state-text">Buat paket pertama untuk siswa yang sudah berkelompok.</p>
                        <a href="<?= e(url('admin/paket/tambah.php')) ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle me-1"></i>Buat Paket
                        </a>
                    </div>
                <?php else: ?>
                    <canvas id="packageChart" height="190"></canvas>
                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                            <?php foreach (StudentPackage::statusOptions() as $status): ?>
                                <tr>
                                    <td class="small"><?= package_status_badge($status) ?></td>
                                    <td class="text-end small fw-semibold">
                                        <?= e(number_format((int) ($packageStats[$status] ?? 0), 0, ',', '.')) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Paket Mendekati Selesai</h2>
                    <div class="card-subtitle">6–7 dari 8 pertemuan; siapkan paket berikutnya.</div>
                </div>
                <a href="<?= e(url('admin/paket/index.php?status=aktif')) ?>" class="btn btn-light btn-sm">Lihat semua</a>
            </div>
            <div class="card-body p-0">
                <?php if ($nearingPackages === []): ?>
                    <div class="p-4 text-center text-muted small">
                        Belum ada paket aktif yang mendekati 8 pertemuan.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Kelompok</th>
                                <th style="min-width:120px;">Progres</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($nearingPackages as $package): ?>
                                <tr>
                                    <td>
                                        <span class="cell-title"><?= e($package['student_name']) ?></span>
                                        <span class="cell-sub">Paket <?= e((string) (int) $package['package_number']) ?></span>
                                    </td>
                                    <td class="small"><?= e($package['group_name']) ?></td>
                                    <td><?= package_progress((int) $package['completed_meetings'], (int) $package['target_meetings']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= e(url('admin/paket/detail.php?id=' . (int) $package['id'])) ?>"
                                           class="btn btn-info btn-sm">
                                            <i class="bi bi-eye"></i>
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
    </div>

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Siswa Berutang</h2>
                    <div class="card-subtitle">Total tagihan dikurangi pembayaran teralokasi.</div>
                </div>
                <a href="<?= e(url('admin/laporan/utang.php')) ?>" class="btn btn-light btn-sm">
                    <i class="bi bi-file-earmark-text me-1"></i>Laporan Utang
                </a>
            </div>
            <div class="card-body p-0">
                <?php if ($debtorPreview === []): ?>
                    <div class="p-4 text-center text-muted small">
                        Tidak ada siswa dengan utang paket belajar.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Tagihan</th>
                                <th class="text-end">Utang</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($debtorPreview as $debtor): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(url('admin/siswa/detail.php?id=' . (int) $debtor['student_id'])) ?>"
                                           class="cell-title link-dark text-decoration-none">
                                            <?= e($debtor['student_name']) ?>
                                        </a>
                                        <span class="cell-sub font-monospace"><?= e($debtor['student_code']) ?></span>
                                    </td>
                                    <td class="small">
                                        <?= e((string) (int) $debtor['unpaid_count']) ?> belum dibayar
                                        <span class="cell-sub">
                                            dari <?= e((string) (int) $debtor['invoice_count']) ?> tagihan
                                        </span>
                                    </td>
                                    <td class="text-end amount text-danger fw-semibold">
                                        <?= e(rupiah((float) $debtor['debt_amount'])) ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= e(url('admin/pembayaran/tambah.php?student_id=' . (int) $debtor['student_id'])) ?>"
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
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Tagihan Terbaru</h2>
                    <div class="card-subtitle">Snapshot paket belajar.</div>
                </div>
                <a href="<?= e(url('admin/tagihan/index.php')) ?>" class="btn btn-light btn-sm">Lihat semua</a>
            </div>
            <div class="card-body p-0">
                <?php if ($recentInvoices === []): ?>
                    <div class="p-4 text-center text-muted small">Belum ada tagihan paket diterbitkan.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Siswa</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentInvoices as $invoice): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(url('admin/tagihan/detail.php?id=' . (int) $invoice['id'])) ?>"
                                           class="invoice-number text-decoration-none small">
                                            <?= e($invoice['invoice_number']) ?>
                                        </a>
                                        <span class="cell-sub">Paket <?= e((string) (int) $invoice['package_number']) ?></span>
                                    </td>
                                    <td class="small">
                                        <?= e($invoice['student_name']) ?>
                                        <span class="cell-sub"><?= e($invoice['group_name']) ?></span>
                                    </td>
                                    <td class="text-end amount"><?= e(rupiah((float) $invoice['total_amount'])) ?></td>
                                    <td><?= invoice_status_badge((string) $invoice['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Pembayaran Terbaru</h2>
                    <div class="card-subtitle">Pelunasan penuh, tanpa cicilan.</div>
                </div>
                <a href="<?= e(url('admin/pembayaran/index.php')) ?>" class="btn btn-light btn-sm">Lihat semua</a>
            </div>
            <div class="card-body p-0">
                <?php if ($recentPayments === []): ?>
                    <div class="p-4 text-center text-muted small">Belum ada pembayaran dicatat.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Nota</th>
                                <th>Siswa</th>
                                <th class="text-end">Jumlah</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentPayments as $payment): ?>
                                <tr>
                                    <td>
                                        <span class="invoice-number small"><?= e($payment['payment_number']) ?></span>
                                        <span class="cell-sub"><?= e(tanggal_short_id((string) $payment['payment_date'])) ?></span>
                                    </td>
                                    <td class="small">
                                        <?= e($payment['student_name']) ?>
                                        <span class="cell-sub"><?= e(payment_method_label((string) $payment['method'])) ?></span>
                                    </td>
                                    <td class="text-end amount"><?= e(rupiah((float) $payment['amount'])) ?></td>
                                    <td class="text-end">
                                        <a href="<?= e(url('admin/pembayaran/nota.php?id=' . (int) $payment['id'])) ?>"
                                           class="btn btn-warning btn-sm" title="Cetak nota"
                                           target="_blank" rel="noopener">
                                            <i class="bi bi-receipt-cutoff"></i>
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
    </div>
</div>

<?php if ($revenueChartTotal > 0 || $packageStatusTotal > 0): ?>
    <?php
    $pageInlineScript = '
(function () {
    "use strict";
    const revenueEl = document.getElementById("revenueChart");
    if (revenueEl && typeof Chart !== "undefined") {
        new Chart(revenueEl, {
            type: "bar",
            data: {
                labels: ' . js_json($revenue['labels']) . ',
                datasets: [{
                    label: "Pembayaran Diterima",
                    data: ' . js_json($revenue['totals']) . ',
                    backgroundColor: "rgba(15, 118, 110, 0.8)",
                    hoverBackgroundColor: "rgba(15, 118, 110, 1)",
                    borderRadius: 6,
                    maxBarThickness: 30
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return "Rp" + Number(context.parsed.y).toLocaleString("id-ID");
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
    }

    const packageEl = document.getElementById("packageChart");
    if (packageEl && typeof Chart !== "undefined") {
        new Chart(packageEl, {
            type: "doughnut",
            data: {
                labels: ' . js_json($packageStatusLabels) . ',
                datasets: [{
                    data: ' . js_json($packageStatusValues) . ',
                    backgroundColor: ["#0f766e", "#2563eb", "#f59e0b", "#94a3b8", "#be123c"],
                    borderWidth: 2,
                    borderColor: "#fff"
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "62%",
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.label + ": " + context.parsed + " paket";
                            }
                        }
                    }
                }
            }
        });
    }
})();';
    ?>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
