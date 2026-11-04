<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$dateFrom = get_string('date_from');
$dateFrom = valid_date($dateFrom) ? $dateFrom : '';
$dateTo = get_string('date_to');
$dateTo = valid_date($dateTo) ? $dateTo : '';
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
$payments = Payment::all($filters);
$summary = Payment::summary($filters);
$groups = Group::all();
$hasFilter = $dateFrom !== '' || $dateTo !== '' || $method !== '' || $q !== '' || $groupId !== '';

$monthSummary = Payment::receivedBetween($monthStart, $monthEnd);
$unpaid = Invoice::summary(['invoice_type' => Invoice::TYPE_PACKAGE]);

$pageTitle = 'Pembayaran';
$pageSubtitle = 'Pelunasan penuh paket belajar. Tidak ada cicilan atau pembayaran sebagian.';
$breadcrumbs = [['label' => 'Pembayaran']];
$activeMenu = 'pembayaran';
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
                    <div class="stat-label">Diterima Bulan Ini</div>
                    <div class="stat-value stat-money"><?= e(rupiah($monthSummary['amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-receipt"></i><?= e(number_format($monthSummary['count'], 0, ',', '.')) ?> pembayaran lunas
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card stat-teal">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total Diterima (Filter)</div>
                    <div class="stat-value stat-money"><?= e(rupiah($summary['amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-list-check"></i><?= e(number_format($summary['count'], 0, ',', '.')) ?> transaksi
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card stat-gold">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Tagihan Belum Dibayar</div>
                    <div class="stat-value stat-money"><?= e(rupiah($unpaid['unpaid_amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-exclamation-circle"></i><?= e(number_format($unpaid['unpaid_count'], 0, ',', '.')) ?> tagihan
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/pembayaran/index.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-3 col-md-6">
                <label for="q" class="form-label">Cari nota / siswa</label>
                <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>"
                       placeholder="Nomor nota, invoice, nama siswa">
            </div>
            <div class="col-xl-2 col-md-6">
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
                <label for="date_from" class="form-label">Dari tanggal</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="date_to" class="form-label">Sampai tanggal</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-xl-1 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary" title="Terapkan filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/pembayaran/index.php')) ?>" class="btn btn-light" title="Reset filter">
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
            <h2 class="card-title">Daftar Pembayaran</h2>
            <div class="card-subtitle">
                <?= e(number_format(count($payments), 0, ',', '.')) ?> pembayaran berhasil
                <?= $hasFilter ? ' &middot; sesuai filter' : '' ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e(url('admin/laporan/pembayaran.php')) ?>" class="btn btn-light">
                <i class="bi bi-file-earmark-text me-1"></i>Laporan
            </a>
            <a href="<?= e(url('admin/tagihan/index.php')) ?>" class="btn btn-primary">
                <i class="bi bi-receipt me-1"></i>Tagihan Belum Bayar
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($payments === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada pembayaran yang sesuai filter' : 'Belum ada pembayaran' ?>
                </div>
                <p class="empty-state-text">
                    <?= $hasFilter
                        ? 'Coba ubah atau reset filter untuk melihat pembayaran lainnya.'
                        : 'Pembayaran dicatat dari tagihan paket yang belum dibayar dan harus lunas penuh.' ?>
                </p>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/pembayaran/index.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                    </a>
                <?php else: ?>
                    <a href="<?= e(url('admin/tagihan/index.php')) ?>" class="btn btn-primary">
                        <i class="bi bi-receipt me-1"></i>Lihat Tagihan
                    </a>
                <?php endif; ?>
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
                        <th>Tagihan</th>
                        <th>Paket</th>
                        <th>Metode</th>
                        <th class="text-end">Jumlah</th>
                        <th>Petugas</th>
                        <th class="no-sort" style="width:90px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $index => $row): ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td>
                                <a href="<?= e(url('admin/pembayaran/detail.php?id=' . (int) $row['id'])) ?>"
                                   class="invoice-number text-decoration-none">
                                    <?= e($row['payment_number']) ?>
                                </a>
                            </td>
                            <td class="text-nowrap"><?= e(tanggal_short_id((string) $row['payment_date'])) ?></td>
                            <td>
                                <span class="cell-title"><?= e($row['student_name']) ?></span>
                                <span class="cell-sub font-monospace"><?= e($row['student_code']) ?></span>
                            </td>
                            <td>
                                <?php if ((int) ($row['invoice_count'] ?? 0) === 1 && $row['invoice_id'] !== null): ?>
                                    <a href="<?= e(url('admin/tagihan/detail.php?id=' . (int) $row['invoice_id'])) ?>"
                                       class="text-decoration-none small">
                                        <?= e($row['invoice_number']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="small"><?= e($row['invoice_number']) ?></span>
                                    <span class="badge text-bg-light ms-1">
                                        <?= e((string) (int) ($row['invoice_count'] ?? 0)) ?> tagihan
                                    </span>
                                <?php endif; ?>
                                <span class="cell-sub"><?= e($row['group_name']) ?></span>
                            </td>
                            <td class="text-nowrap">
                                Paket <?= e((string) (int) $row['package_number']) ?>
                                <?php if ((int) ($row['invoice_count'] ?? 0) > 1): ?>
                                    <span class="badge text-bg-light">+<?= e((string) ((int) $row['invoice_count'] - 1)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(payment_method_label((string) $row['method'])) ?></td>
                            <td class="text-end amount fw-semibold"><?= e(rupiah((float) $row['amount'])) ?></td>
                            <td><?= e($row['officer_name'] ?: '-') ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e(url('admin/pembayaran/nota.php?id=' . (int) $row['id'])) ?>"
                                       class="btn btn-warning btn-sm" title="Cetak nota"
                                       target="_blank" rel="noopener">
                                        <i class="bi bi-receipt-cutoff"></i>
                                    </a>
                                </div>
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
    <span>Kwitansi/nota hanya dibuat setelah pembayaran berhasil disimpan. Data siswa, kelompok, tarif,
        dan petugas disalin sebagai snapshot sehingga dokumen historis tidak berubah.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
