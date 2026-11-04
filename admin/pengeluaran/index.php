<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$dateFrom = get_string('date_from');
$dateFrom = valid_date($dateFrom) ? $dateFrom : '';
$dateTo = get_string('date_to');
$dateTo = valid_date($dateTo) ? $dateTo : '';
$category = get_string('category');
$category = in_array($category, Expense::categoryOptions(), true) ? $category : '';
$method = get_string('method');
$method = in_array($method, Expense::methodOptions(), true) ? $method : '';
$status = get_string('status');
$status = in_array($status, Expense::statusOptions(), true) ? $status : '';
$q = get_string('q');

$filters = [
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'category'  => $category,
    'method'    => $method,
    'status'    => $status,
    'q'         => $q,
];

$listFilters = $filters + ['include_cancelled' => true];
$expenses = Expense::all($listFilters);
$summary = Expense::summary($filters);
$byCategory = Expense::byCategory($filters);
$monthSummary = Expense::summary([
    'date_from' => date('Y-m-01'),
    'date_to'   => date('Y-m-t'),
]);
$hasFilter = $dateFrom !== '' || $dateTo !== '' || $category !== ''
    || $method !== '' || $status !== '' || $q !== '';
$topCategory = $byCategory[0] ?? null;

$pageTitle = 'Pengeluaran';
$pageSubtitle = 'Catat biaya operasional bimbel. Data tidak dihapus permanen; koreksi lewat pembatalan ber-audit.';
$breadcrumbs = [['label' => 'Pengeluaran']];
$activeMenu = 'pengeluaran';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-gold">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Pengeluaran Bulan Ini</div>
                    <div class="stat-value stat-money"><?= e(rupiah($monthSummary['amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-calendar-month"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-list-check"></i><?= e(number_format($monthSummary['count'], 0, ',', '.')) ?> transaksi tercatat
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-navy">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total Sesuai Filter</div>
                    <div class="stat-value stat-money"><?= e(rupiah($summary['amount'])) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-receipt"></i><?= e(number_format($summary['count'], 0, ',', '.')) ?> pengeluaran
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-teal">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Kategori Terbesar</div>
                    <div class="stat-value stat-money">
                        <?= e($topCategory !== null ? rupiah($topCategory['amount']) : rupiah(0)) ?>
                    </div>
                </div>
                <div class="stat-icon"><i class="bi bi-pie-chart"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-tag"></i>
                <?= e($topCategory !== null ? expense_category_label($topCategory['category']) : 'Belum ada data') ?>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-green">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Transaksi Ditampilkan</div>
                    <div class="stat-value"><?= e(number_format(count($expenses), 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-journal-text"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-funnel"></i><?= $hasFilter ? 'Sesuai filter aktif' : 'Seluruh riwayat' ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/pengeluaran/index.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-3 col-md-6">
                <label for="q" class="form-label">Cari pengeluaran</label>
                <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>"
                       placeholder="Nomor, penerima, referensi, catatan">
            </div>
            <div class="col-xl-2 col-md-6">
                <label for="category" class="form-label">Kategori</label>
                <select class="form-select" id="category" name="category">
                    <option value="">Semua kategori</option>
                    <?php foreach (Expense::categoryOptions() as $option): ?>
                        <option value="<?= e($option) ?>" <?= $category === $option ? 'selected' : '' ?>>
                            <?= e(expense_category_label($option)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="method" class="form-label">Metode</label>
                <select class="form-select" id="method" name="method">
                    <option value="">Semua metode</option>
                    <?php foreach (Expense::methodOptions() as $option): ?>
                        <option value="<?= e($option) ?>" <?= $method === $option ? 'selected' : '' ?>>
                            <?= e(payment_method_label($option)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua status</option>
                    <?php foreach (Expense::statusOptions() as $option): ?>
                        <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>>
                            <?= e(expense_status_label($option)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-1 col-md-4">
                <label for="date_from" class="form-label">Dari</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-xl-1 col-md-4">
                <label for="date_to" class="form-label">Sampai</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-xl-1 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary" title="Terapkan filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/pengeluaran/index.php')) ?>" class="btn btn-light" title="Reset filter">
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
            <h2 class="card-title">Daftar Pengeluaran</h2>
            <div class="card-subtitle">
                <?= e(number_format(count($expenses), 0, ',', '.')) ?> transaksi
                <?= $hasFilter ? ' &middot; sesuai filter' : '' ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e(url('admin/laporan/keuangan.php')) ?>" class="btn btn-light">
                <i class="bi bi-graph-up-arrow me-1"></i>Rekap Keuangan
            </a>
            <a href="<?= e(url('admin/pengeluaran/tambah.php')) ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Catat Pengeluaran
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($expenses === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-wallet2"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada pengeluaran yang sesuai filter' : 'Belum ada pengeluaran' ?>
                </div>
                <p class="empty-state-text">
                    <?= $hasFilter
                        ? 'Coba ubah atau reset filter untuk melihat pengeluaran lainnya.'
                        : 'Catat biaya operasional seperti honor guru, sewa tempat, listrik, dan ATK agar kas bimbel terpantau.' ?>
                </p>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/pengeluaran/index.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                    </a>
                <?php else: ?>
                    <a href="<?= e(url('admin/pengeluaran/tambah.php')) ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Catat Pengeluaran
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width:40px;">No.</th>
                        <th>Nomor</th>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Penerima</th>
                        <th>Metode</th>
                        <th class="text-end">Nominal</th>
                        <th>Petugas</th>
                        <th>Status</th>
                        <th class="no-sort" style="width:90px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($expenses as $index => $row): ?>
                        <?php $isRecorded = (string) $row['status'] === Expense::STATUS_RECORDED; ?>
                        <tr class="<?= $isRecorded ? '' : 'table-light text-muted' ?>">
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td>
                                <a href="<?= e(url('admin/pengeluaran/detail.php?id=' . (int) $row['id'])) ?>"
                                   class="invoice-number text-decoration-none">
                                    <?= e($row['expense_number']) ?>
                                </a>
                            </td>
                            <td class="text-nowrap"><?= e(tanggal_short_id((string) $row['expense_date'])) ?></td>
                            <td><?= expense_category_badge((string) $row['category']) ?></td>
                            <td>
                                <span class="cell-title"><?= e($row['payee']) ?></span>
                                <?php if (!empty($row['reference_number'])): ?>
                                    <span class="cell-sub font-monospace"><?= e((string) $row['reference_number']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= payment_method_badge((string) $row['method']) ?></td>
                            <td class="text-end amount fw-semibold"><?= e(rupiah((float) $row['amount'])) ?></td>
                            <td><?= e($row['officer_name'] ?: '-') ?></td>
                            <td><?= expense_status_badge((string) $row['status']) ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e(url('admin/pengeluaran/detail.php?id=' . (int) $row['id'])) ?>"
                                       class="btn btn-light btn-sm" title="Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($isRecorded): ?>
                                        <a href="<?= e(url('admin/pengeluaran/edit.php?id=' . (int) $row['id'])) ?>"
                                           class="btn btn-primary btn-sm" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                    <?php endif; ?>
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
    <span>Pengeluaran berstatus <strong>dibatalkan</strong> tetap tersimpan sebagai riwayat dan tidak dihitung
        ke kas maupun laporan. Pembatalan hanya dapat dilakukan admin dengan alasan.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
