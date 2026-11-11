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
$packageStatusColors = [];
$packageStatusColorMap = [
    StudentPackage::STATUS_AKTIF      => '#0f766e',
    StudentPackage::STATUS_SELESAI    => '#2563eb',
    StudentPackage::STATUS_DIBATALKAN => '#94a3b8',
];
foreach (StudentPackage::statusOptions() as $status) {
    $count = (int) ($packageStats[$status] ?? 0);
    if ($count > 0) {
        $packageStatusLabels[] = package_status_label($status);
        $packageStatusValues[] = $count;
        $packageStatusColors[] = $packageStatusColorMap[$status] ?? '#94a3b8';
    }
}
$packageStatusTotal = array_sum($packageStatusValues);
$revenueChartTotal = array_sum($revenue['totals']);

// Statistik pendukung grafik pembayaran (hanya dari data nyata).
$revenueMonthCount = max(1, count($revenue['totals']));
$revenueAverage = $revenueChartTotal / $revenueMonthCount;
$revenueBestIndex = 0;
foreach ($revenue['totals'] as $index => $total) {
    if ((float) $total > (float) $revenue['totals'][$revenueBestIndex]) {
        $revenueBestIndex = (int) $index;
    }
}
$revenueBestAmount = (float) ($revenue['totals'][$revenueBestIndex] ?? 0);
$revenueBestLabel = (string) ($revenue['labels'][$revenueBestIndex] ?? '');

// Tren realisasi pertemuan enam bulan terakhir.
$meetingTrend = Meeting::monthlyStats(6);
$meetingTrendHasData = $meetingTrend['total_done'] > 0 || $meetingTrend['total_scheduled'] > 0;
$meetingTrendAverage = $meetingTrend['total_done'] / max(1, count($meetingTrend['done']));

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
                <div class="chart-box">
                    <canvas id="revenueChart" role="img"
                            aria-label="Grafik batang pembayaran diterima per bulan"></canvas>
                    <?php if ($revenueChartTotal <= 0): ?>
                        <div class="chart-empty">
                            <i class="bi bi-bar-chart-line"></i>
                            <span>
                                <strong>Belum ada pembayaran</strong>
                                Grafik terisi otomatis setelah pelunasan pertama dicatat
                                pada menu Pembayaran.
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($revenueChartTotal > 0): ?>
                    <div class="chart-stat-row">
                        <span>
                            Rata-rata per bulan
                            <strong><?= e(rupiah($revenueAverage)) ?></strong>
                        </span>
                        <span>
                            Bulan tertinggi
                            <strong><?= e($revenueBestLabel) ?> &middot; <?= e(rupiah($revenueBestAmount)) ?></strong>
                        </span>
                    </div>
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
                    <div class="chart-box chart-box-sm">
                        <canvas id="packageChart" role="img"
                                aria-label="Diagram donat komposisi status paket"></canvas>
                        <div class="doughnut-center">
                            <span>Total</span>
                            <strong><?= e(number_format($packageStatusTotal, 0, ',', '.')) ?></strong>
                            <small>paket</small>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-2 mt-3">
                        <?php foreach (StudentPackage::statusOptions() as $status): ?>
                            <?php
                            $statusCount = (int) ($packageStats[$status] ?? 0);
                            $statusPercent = $packageStatusTotal > 0
                                ? (int) round($statusCount / $packageStatusTotal * 100)
                                : 0;
                            ?>
                            <div class="status-row">
                                <span class="status-row-label">
                                    <span class="legend-dot"
                                          style="background-color: <?= e($packageStatusColorMap[$status] ?? '#94a3b8') ?>;"></span>
                                    <?= e(package_status_label($status)) ?>
                                </span>
                                <span class="status-row-value">
                                    <?= e(number_format($statusCount, 0, ',', '.')) ?>
                                    <small><?= e($statusPercent) ?>%</small>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Realisasi Pertemuan</h2>
                    <div class="card-subtitle">
                        Pertemuan terlaksana dan terjadwal per bulan &middot; enam bulan terakhir.
                    </div>
                </div>
                <span class="legend-list">
                    <span><span class="legend-dot" style="background-color: #0f766e;"></span> Terlaksana</span>
                    <span><span class="legend-dot" style="background-color: #94a3b8;"></span> Terjadwal</span>
                </span>
            </div>
            <div class="card-body">
                <div class="chart-box chart-box-xs">
                    <canvas id="meetingChart" role="img"
                            aria-label="Grafik garis realisasi pertemuan per bulan"></canvas>
                    <?php if (!$meetingTrendHasData): ?>
                        <div class="chart-empty">
                            <i class="bi bi-calendar-check"></i>
                            <span>
                                <strong>Belum ada pertemuan tercatat</strong>
                                Grafik terisi otomatis setelah jurnal pertemuan diisi
                                pada detail kelompok.
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($meetingTrendHasData): ?>
                    <div class="chart-stat-row">
                        <span>
                            Terlaksana 6 bulan
                            <strong><?= e(number_format($meetingTrend['total_done'], 0, ',', '.')) ?> pertemuan</strong>
                        </span>
                        <span>
                            Rata-rata per bulan
                            <strong><?= e(rtrim(rtrim(number_format($meetingTrendAverage, 1, ',', '.'), '0'), ',')) ?> pertemuan</strong>
                        </span>
                        <span>
                            Terjadwal
                            <strong><?= e(number_format($meetingTrend['total_scheduled'], 0, ',', '.')) ?> pertemuan</strong>
                        </span>
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

<?php
$revenueScaleMax = $revenueChartTotal > 0 ? 'null' : '1000000';
$meetingScaleMax = $meetingTrendHasData ? 'null' : '8';
$pageInlineScript = '
(function () {
    "use strict";

    if (typeof Chart === "undefined") {
        return;
    }

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily || "system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = "#8a9aa5";
    // Gambar langsung tanpa menunggu frame animasi agar grafik selalu tampil
    // (termasuk saat tab baru dimuat di belakang, dicetak, atau di-screenshot).
    Chart.defaults.animation = false;

    const rupiahFull = (value) => "Rp" + Number(value).toLocaleString("id-ID");
    const rupiahShort = (value) => {
        if (value >= 1000000) return "Rp" + (value / 1000000).toLocaleString("id-ID") + " jt";
        if (value >= 1000) return "Rp" + (value / 1000).toLocaleString("id-ID") + " rb";
        return "Rp" + Number(value).toLocaleString("id-ID");
    };
    const tooltipStyle = {
        backgroundColor: "#0b2532",
        titleColor: "#ffffff",
        bodyColor: "#dbe7e5",
        padding: 10,
        cornerRadius: 8,
        displayColors: false
    };
    const barGradient = (chart) => {
        if (!chart.chartArea) {
            return "rgba(15, 118, 110, 0.82)";
        }
        const gradient = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
        gradient.addColorStop(0, "#17a396");
        gradient.addColorStop(1, "#0f766e");
        return gradient;
    };

    const revenueEl = document.getElementById("revenueChart");
    if (revenueEl) {
        new Chart(revenueEl, {
            type: "bar",
            data: {
                labels: ' . js_json($revenue['labels']) . ',
                datasets: [{
                    label: "Uang Diterima",
                    data: ' . js_json($revenue['totals']) . ',
                    backgroundColor: (context) => barGradient(context.chart),
                    hoverBackgroundColor: "#f2c14e",
                    borderRadius: 7,
                    borderSkipped: false,
                    maxBarThickness: 38,
                    barPercentage: 0.66,
                    categoryPercentage: 0.72
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: Object.assign({}, tooltipStyle, {
                        callbacks: {
                            label: (context) => {
                                const count = ' . js_json($revenue['counts']) . '[context.dataIndex] || 0;
                                return rupiahFull(context.parsed.y) + " \u00b7 " + count + " pelunasan";
                            }
                        }
                    })
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: ' . $revenueScaleMax . ',
                        grid: { color: "#eef3f2", drawTicks: false },
                        border: { display: false },
                        ticks: { callback: rupiahShort, maxTicksLimit: 5, padding: 8 }
                    },
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { padding: 6 }
                    }
                }
            }
        });
    }

    const packageEl = document.getElementById("packageChart");
    if (packageEl) {
        new Chart(packageEl, {
            type: "doughnut",
            data: {
                labels: ' . js_json($packageStatusLabels) . ',
                datasets: [{
                    data: ' . js_json($packageStatusValues) . ',
                    backgroundColor: ' . js_json($packageStatusColors) . ',
                    borderColor: "#ffffff",
                    borderWidth: 3,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "70%",
                plugins: {
                    legend: { display: false },
                    tooltip: Object.assign({}, tooltipStyle, {
                        callbacks: {
                            label: (context) => {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0) || 1;
                                const percent = Math.round((context.parsed / total) * 100);
                                return context.label + ": " + context.parsed + " paket (" + percent + "%)";
                            }
                        }
                    })
                }
            }
        });
    }

    const meetingEl = document.getElementById("meetingChart");
    if (meetingEl) {
        const areaGradient = (chart) => {
            if (!chart.chartArea) {
                return "rgba(15, 118, 110, 0.15)";
            }
            const gradient = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
            gradient.addColorStop(0, "rgba(20, 122, 112, 0.22)");
            gradient.addColorStop(1, "rgba(20, 122, 112, 0.01)");
            return gradient;
        };

        new Chart(meetingEl, {
            type: "line",
            data: {
                labels: ' . js_json($meetingTrend['labels']) . ',
                datasets: [
                    {
                        label: "Terlaksana",
                        data: ' . js_json($meetingTrend['done']) . ',
                        borderColor: "#0f766e",
                        backgroundColor: (context) => areaGradient(context.chart),
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 3.5,
                        pointBackgroundColor: "#ffffff",
                        pointBorderColor: "#0f766e",
                        pointBorderWidth: 2,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: "#0f766e",
                        pointHoverBorderColor: "#ffffff"
                    },
                    {
                        label: "Terjadwal",
                        data: ' . js_json($meetingTrend['scheduled']) . ',
                        borderColor: "#94a3b8",
                        borderDash: [5, 4],
                        borderWidth: 2,
                        fill: false,
                        tension: 0.35,
                        pointRadius: 2.5,
                        pointBackgroundColor: "#ffffff",
                        pointBorderColor: "#94a3b8",
                        pointBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: Object.assign({}, tooltipStyle, {
                        callbacks: {
                            label: (context) => context.dataset.label + ": " + context.parsed.y + " pertemuan"
                        }
                    })
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: ' . $meetingScaleMax . ',
                        grid: { color: "#eef3f2", drawTicks: false },
                        border: { display: false },
                        ticks: { precision: 0, maxTicksLimit: 5, padding: 8 }
                    },
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { padding: 6 }
                    }
                }
            }
        });
    }
})();';
?>


<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
