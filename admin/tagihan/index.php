<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$status = get_string('status');
$status = in_array($status, ['belum_bayar', 'lunas', 'dibatalkan'], true) ? $status : '';
$q = get_string('q');
$dateFrom = get_string('date_from');
$dateFrom = valid_date($dateFrom) ? $dateFrom : '';
$dateTo = get_string('date_to');
$dateTo = valid_date($dateTo) ? $dateTo : '';
$groupId = get_string('group_id');
$groupId = ctype_digit($groupId) ? $groupId : '';

$filters = [
    'status'       => $status,
    'q'            => $q,
    'date_from'    => $dateFrom,
    'date_to'      => $dateTo,
    'group_id'     => $groupId,
    'invoice_type' => Invoice::TYPE_PACKAGE,
];

$invoices = Invoice::all($filters);
$summary = Invoice::summary($filters);
$groups = Group::all();
$hasFilter = $status !== '' || $q !== '' || $dateFrom !== '' || $dateTo !== '' || $groupId !== '';

$pageTitle = 'Tagihan Paket Belajar';
$pageSubtitle = 'Satu tagihan per paket 8 pertemuan. Nominal dan snapshot tidak berubah setelah terbit.';
$breadcrumbs = [['label' => 'Tagihan']];
$activeMenu = 'tagihan';
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
                    <div class="stat-value"><?= e(number_format($summary['issued_count'], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-receipt"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-cash-coin"></i><?= e(rupiah($summary['issued_amount'])) ?>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-gold">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Belum Dibayar</div>
                    <div class="stat-value"><?= e(number_format($summary['unpaid_count'], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-exclamation-circle"></i><?= e(rupiah($summary['unpaid_amount'])) ?>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-green">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Uang Diterima</div>
                    <div class="stat-value"><?= e(number_format($summary['received_count'], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-check2-circle"></i><?= e(rupiah($summary['received_amount'])) ?>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-rose">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Dibatalkan</div>
                    <div class="stat-value"><?= e(number_format($summary['cancelled_count'], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
            </div>
            <div class="stat-meta">
                <i class="bi bi-archive"></i>Tetap tersimpan sebagai riwayat
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/tagihan/index.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-3 col-md-6">
                <label for="q" class="form-label">Cari tagihan / siswa</label>
                <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>"
                       placeholder="Nomor invoice, nama, kode siswa">
            </div>
            <div class="col-xl-2 col-md-6">
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
                <label for="date_from" class="form-label">Terbit dari</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="date_to" class="form-label">Sampai</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-xl-1 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary" title="Terapkan filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/tagihan/index.php')) ?>" class="btn btn-light" title="Reset filter">
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
            <h2 class="card-title">Daftar Tagihan Paket</h2>
            <div class="card-subtitle">
                <?= e(number_format(count($invoices), 0, ',', '.')) ?> tagihan
                <?= $hasFilter ? ' &middot; sesuai filter' : '' ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e(url('admin/laporan/tagihan.php')) ?>" class="btn btn-light">
                <i class="bi bi-file-earmark-text me-1"></i>Laporan
            </a>
            <a href="<?= e(url('admin/paket/index.php')) ?>" class="btn btn-primary">
                <i class="bi bi-box-seam me-1"></i>Paket Belajar
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($invoices === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-receipt"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada tagihan yang sesuai filter' : 'Belum ada tagihan paket' ?>
                </div>
                <p class="empty-state-text">
                    <?= $hasFilter
                        ? 'Coba ubah atau reset filter untuk melihat tagihan lainnya.'
                        : 'Tagihan terbit otomatis saat paket belajar dibuat dan tarif sudah tersedia.' ?>
                </p>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/tagihan/index.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                    </a>
                <?php else: ?>
                    <a href="<?= e(url('admin/paket/tambah.php')) ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Buat Paket
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width:40px;">No.</th>
                        <th>Nomor Invoice</th>
                        <th>Siswa</th>
                        <th>Kelompok</th>
                        <th class="no-sort">Paket</th>
                        <th>Terbit</th>
                        <th>Jatuh Tempo</th>
                        <th class="text-end">Total</th>
                        <th class="no-sort">Status</th>
                        <th class="no-sort" style="width:150px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invoices as $index => $invoice): ?>
                        <?php
                        $isOverdue = (string) $invoice['status'] === 'belum_bayar'
                            && (string) $invoice['due_date'] < date('Y-m-d');
                        $detailUrl = url('admin/tagihan/detail.php?id=' . (int) $invoice['id']);
                        $payUrl = url('admin/pembayaran/tambah.php?invoice_id=' . (int) $invoice['id']);
                        ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td>
                                <a href="<?= e($detailUrl) ?>" class="invoice-number text-decoration-none">
                                    <?= e($invoice['invoice_number']) ?>
                                </a>
                                <span class="cell-sub"><?= e(invoice_type_label((string) $invoice['invoice_type'])) ?></span>
                            </td>
                            <td>
                                <span class="cell-title"><?= e($invoice['student_name']) ?></span>
                                <span class="cell-sub font-monospace"><?= e($invoice['student_code']) ?></span>
                            </td>
                            <td>
                                <span class="cell-title"><?= e($invoice['group_name']) ?></span>
                                <span class="cell-sub"><?= e(package_type_label((string) $invoice['group_type'])) ?>
                                    &middot; <?= e((string) (int) $invoice['member_count']) ?> siswa</span>
                            </td>
                            <td class="text-nowrap">
                                Paket <?= e((string) (int) $invoice['package_number']) ?>
                                <span class="cell-sub"><?= e((string) (int) $invoice['meeting_target']) ?> pertemuan</span>
                            </td>
                            <td class="text-nowrap"><?= e(tanggal_short_id((string) $invoice['issue_date'])) ?></td>
                            <td class="text-nowrap">
                                <?= e(tanggal_short_id((string) $invoice['due_date'])) ?>
                                <?php if ($isOverdue): ?>
                                    <span class="badge text-bg-danger d-block mt-1">Lewat tempo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end amount fw-semibold"><?= e(rupiah((float) $invoice['total_amount'])) ?></td>
                            <td><?= invoice_status_badge((string) $invoice['status']) ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e($detailUrl) ?>" class="btn btn-info btn-sm" title="Detail tagihan">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= e(url('admin/tagihan/cetak.php?id=' . (int) $invoice['id'])) ?>"
                                       class="btn btn-light btn-sm" title="Cetak invoice"
                                       target="_blank" rel="noopener">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <?php if ((string) $invoice['status'] === 'belum_bayar'): ?>
                                        <a href="<?= e($payUrl) ?>" class="btn btn-success btn-sm"
                                           title="Catat pelunasan">
                                            <i class="bi bi-cash-stack"></i>
                                        </a>
                                    <?php elseif ($invoice['payment_number'] !== null): ?>
                                        <a href="<?= e(url('admin/pembayaran/nota.php?id=' . (int) $invoice['payment_id'])) ?>"
                                           class="btn btn-warning btn-sm" title="Nota pembayaran"
                                           target="_blank" rel="noopener">
                                            <i class="bi bi-receipt-cutoff"></i>
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
    <span>Pembayaran wajib lunas: jumlahnya harus sama persis dengan total tagihan. Tidak ada cicilan
        atau pembayaran sebagian, dan satu tagihan hanya menerima satu pembayaran berhasil.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
