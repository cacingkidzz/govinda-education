<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$id = get_int('id');
$payment = $id > 0 ? Payment::receipt($id) : null;
if ($payment === null) {
    abort_404('Pembayaran tidak ditemukan.');
}

$invoices = is_array($payment['invoices'] ?? null) ? $payment['invoices'] : [];
$invoiceNumbers = [];
$allocatedTotal = 0.0;
foreach ($invoices as $invoiceRow) {
    $invoiceNumbers[] = (string) $invoiceRow['invoice_number'];
    $allocatedTotal += (float) $invoiceRow['allocated_amount'];
}

$pageTitle = 'Pembayaran ' . $payment['payment_number'];
$pageSubtitle = 'Bukti pelunasan penuh yang tidak dapat diubah setelah tersimpan.';
$breadcrumbs = [
    ['label' => 'Pembayaran', 'url' => url('admin/pembayaran/index.php')],
    ['label' => $payment['payment_number']],
];
$activeMenu = 'pembayaran';

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= e($payment['payment_number']) ?></h2>
                    <div class="card-subtitle">
                        <?= e(tanggal_id((string) $payment['payment_date'], true)) ?>
                        &middot; <?= e(payment_method_label((string) $payment['method'])) ?>
                    </div>
                </div>
                <span class="badge text-bg-success">LUNAS</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <dl class="detail-list mb-0">
                            <div class="detail-row">
                                <dt>Siswa</dt>
                                <dd>
                                    <span class="cell-title"><?= e($payment['student_name']) ?></span>
                                    <span class="cell-sub font-monospace"><?= e($payment['student_code']) ?></span>
                                </dd>
                            </div>
                            <div class="detail-row">
                                <dt>Jumlah tagihan</dt>
                                <dd><?= e((string) count($invoices)) ?> tagihan</dd>
                            </div>
                            <div class="detail-row">
                                <dt>Petugas</dt>
                                <dd><?= e(($payment['received_by_name'] ?: $payment['officer_name']) ?: '-') ?></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="detail-list mb-0">
                            <div class="detail-row">
                                <dt>Jumlah dibayar</dt>
                                <dd class="amount fw-semibold fs-5"><?= e(rupiah((float) $payment['amount'])) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Total dialokasikan</dt>
                                <dd class="amount"><?= e(rupiah($allocatedTotal)) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Referensi</dt>
                                <dd><?= e((string) ($payment['reference_number'] ?? '')) ?: '—' ?></dd>
                            </div>
                        </dl>
                    </div>
                    <?php if ($payment['notes'] !== null && trim((string) $payment['notes']) !== ''): ?>
                        <div class="col-12">
                            <div class="alert alert-light border mb-0 small">
                                <strong>Catatan:</strong> <?= e((string) $payment['notes']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Rincian Alokasi</h2>
                    <div class="card-subtitle">Setiap tagihan dibayar penuh dan dialokasikan terpisah.</div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($invoices === []): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-receipt"></i></div>
                        <div class="empty-state-title">Tidak ada alokasi tagihan</div>
                        <p class="empty-state-text">Pembayaran ini tidak memiliki rincian tagihan yang tersimpan.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Kelompok / Paket</th>
                                <th class="text-end">Total Tagihan</th>
                                <th class="text-end">Alokasi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($invoices as $invoiceRow): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(url('admin/tagihan/detail.php?id=' . (int) $invoiceRow['id'])) ?>"
                                           class="invoice-number text-decoration-none">
                                            <?= e($invoiceRow['invoice_number']) ?>
                                        </a>
                                        <span class="cell-sub"><?= e(tanggal_short_id((string) $invoiceRow['issue_date'])) ?></span>
                                    </td>
                                    <td>
                                        <span class="cell-title"><?= e($invoiceRow['group_name']) ?></span>
                                        <span class="cell-sub">
                                            <?= e(package_type_label((string) $invoiceRow['group_type'])) ?>
                                            &middot; Paket <?= e((string) (int) $invoiceRow['package_number']) ?>
                                            &middot; <?= e((string) (int) $invoiceRow['meeting_target']) ?> pertemuan
                                        </span>
                                    </td>
                                    <td class="text-end amount"><?= e(rupiah((float) $invoiceRow['total_amount'])) ?></td>
                                    <td class="text-end amount fw-semibold"><?= e(rupiah((float) $invoiceRow['allocated_amount'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total dibayar</th>
                                <th class="text-end amount"><?= e(rupiah((float) $payment['amount'])) ?></th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer d-flex flex-wrap gap-2">
                <a href="<?= e(url('admin/pembayaran/nota.php?id=' . (int) $payment['id'])) ?>"
                   class="btn btn-primary" target="_blank" rel="noopener">
                    <i class="bi bi-printer me-1"></i>Cetak Nota
                </a>
                <a href="<?= e(url('admin/pembayaran/index.php')) ?>" class="btn btn-secondary ms-auto">
                    <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Paket Terkait</h2>
                    <div class="card-subtitle">Status paket tidak berubah karena pembayaran.</div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($invoices === []): ?>
                    <div class="text-secondary small">Tidak ada paket terkait.</div>
                <?php else: ?>
                    <?php foreach ($invoices as $invoiceRow): ?>
                        <div class="border rounded-3 p-2 mb-2">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="cell-title">Paket <?= e((string) (int) $invoiceRow['package_number']) ?></div>
                                    <div class="cell-sub">
                                        <?= e((string) (int) ($invoiceRow['completed_meetings'] ?? 0)) ?> /
                                        <?= e((string) (int) ($invoiceRow['target_meetings'] ?? 0)) ?> pertemuan
                                    </div>
                                </div>
                                <?= package_status_badge((string) ($invoiceRow['package_status'] ?? '')) ?>
                            </div>
                            <?php if ($invoiceRow['student_package_id'] !== null): ?>
                                <a href="<?= e(url('admin/paket/detail.php?id=' . (int) $invoiceRow['student_package_id'])) ?>"
                                   class="btn btn-light btn-sm mt-2">
                                    <i class="bi bi-box-seam me-1"></i>Detail Paket
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="form-text">
                    Paket berikutnya sudah aktif sejak pertemuan ke-8 paket sebelumnya. Pembayaran hanya
                    melunasi tagihan dan tidak mengubah hitungan pertemuan.
                </div>
            </div>
        </div>

        <div class="master-note">
            <i class="bi bi-info-circle"></i>
            <span>Nota memuat seluruh tagihan yang dilunasi oleh pembayaran ini. Data siswa, kelompok,
                tarif, dan petugas disalin sebagai snapshot.</span>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
