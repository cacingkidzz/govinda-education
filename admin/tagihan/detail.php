<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$id = get_int('id');
$invoice = $id > 0 ? Invoice::find($id) : null;
if ($invoice === null) {
    abort_404('Tagihan tidak ditemukan.');
}

$payment = $invoice['payment_id'] !== null ? Payment::receipt((int) $invoice['payment_id']) : null;
$history = $invoice['student_package_id'] !== null
    ? Invoice::forPackage((int) $invoice['student_package_id'])
    : [];
$state = pull_form_state();
$errors = $state['errors'];
$isAdmin = is_admin();
$isUnpaid = (string) $invoice['status'] === 'belum_bayar';
$isPaid = (string) $invoice['status'] === 'lunas';
$isCancelled = (string) $invoice['status'] === 'dibatalkan';

$pageTitle = 'Tagihan ' . $invoice['invoice_number'];
$pageSubtitle = 'Snapshot tagihan paket belajar beserta status pembayarannya.';
$breadcrumbs = [
    ['label' => 'Tagihan', 'url' => url('admin/tagihan/index.php')],
    ['label' => $invoice['invoice_number']],
];
$activeMenu = 'tagihan';

require_once ROOT_PATH . '/includes/header.php';
?>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>Aksi belum dapat diproses
        </div>
        <?php foreach ($errors as $message): ?>
            <div class="small"><?= e($message) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= e($invoice['invoice_number']) ?></h2>
                    <div class="card-subtitle">
                        Diterbitkan <?= e(tanggal_id((string) $invoice['issue_date'], true)) ?>
                        &middot; jatuh tempo <?= e(tanggal_id((string) $invoice['due_date'])) ?>
                    </div>
                </div>
                <?= invoice_status_badge((string) $invoice['status']) ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <dl class="detail-list mb-0">
                            <div class="detail-row">
                                <dt>Siswa</dt>
                                <dd>
                                    <span class="cell-title"><?= e($invoice['student_name']) ?></span>
                                    <span class="cell-sub font-monospace"><?= e($invoice['student_code']) ?></span>
                                </dd>
                            </div>
                            <div class="detail-row">
                                <dt>Kelompok</dt>
                                <dd>
                                    <span class="cell-title"><?= e($invoice['group_name']) ?></span>
                                    <span class="cell-sub"><?= e(package_type_label((string) $invoice['group_type'])) ?>
                                        &middot; <?= e((string) (int) $invoice['member_count']) ?> siswa</span>
                                </dd>
                            </div>
                            <div class="detail-row">
                                <dt>Paket</dt>
                                <dd>
                                    Paket <?= e((string) (int) $invoice['package_number']) ?>
                                    <span class="cell-sub"><?= e((string) (int) $invoice['meeting_target']) ?> pertemuan</span>
                                </dd>
                            </div>
                            <div class="detail-row">
                                <dt>Periode tagihan</dt>
                                <dd><?= e($invoice['period_label']) ?></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="detail-list mb-0">
                            <div class="detail-row">
                                <dt>Tarif dasar</dt>
                                <dd class="amount"><?= e(rupiah((float) $invoice['unit_amount'])) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Diskon</dt>
                                <dd class="amount">
                                    <?= (float) $invoice['discount_amount'] > 0
                                        ? '- ' . e(rupiah((float) $invoice['discount_amount']))
                                        : '—' ?>
                                </dd>
                            </div>
                            <div class="detail-row">
                                <dt>Total tagihan</dt>
                                <dd class="amount fw-semibold fs-5"><?= e(rupiah((float) $invoice['total_amount'])) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Diterbitkan oleh</dt>
                                <dd><?= e($invoice['created_by_name'] ?: '-') ?></dd>
                            </div>
                        </dl>
                    </div>
                    <?php if ($invoice['notes'] !== null && trim((string) $invoice['notes']) !== ''): ?>
                        <div class="col-12">
                            <div class="alert alert-light border mb-0 small">
                                <strong>Catatan:</strong> <?= e((string) $invoice['notes']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($isCancelled): ?>
                        <div class="col-12">
                            <div class="alert alert-secondary mb-0 small">
                                <strong>Tagihan dibatalkan.</strong>
                                <?= e((string) ($invoice['cancel_reason'] ?? '-')) ?>
                                <?php if ($invoice['cancelled_at'] !== null): ?>
                                    &middot; <?= e(tanggal_waktu_id((string) $invoice['cancelled_at'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer d-flex flex-wrap gap-2">
                <a href="<?= e(url('admin/tagihan/cetak.php?id=' . (int) $invoice['id'])) ?>"
                   class="btn btn-primary" target="_blank" rel="noopener">
                    <i class="bi bi-printer me-1"></i>Cetak Invoice
                </a>
                <?php if ($isUnpaid): ?>
                    <a href="<?= e(url('admin/pembayaran/tambah.php?invoice_id=' . (int) $invoice['id'])) ?>"
                       class="btn btn-success">
                        <i class="bi bi-cash-stack me-1"></i>Catat Pelunasan
                    </a>
                <?php endif; ?>
                <?php if ($isPaid && $payment !== null): ?>
                    <a href="<?= e(url('admin/pembayaran/nota.php?id=' . (int) $payment['id'])) ?>"
                       class="btn btn-warning" target="_blank" rel="noopener">
                        <i class="bi bi-receipt-cutoff me-1"></i>Cetak Nota
                    </a>
                <?php endif; ?>
                <?php if ($invoice['student_package_id'] !== null): ?>
                    <a href="<?= e(url('admin/paket/detail.php?id=' . (int) $invoice['student_package_id'])) ?>"
                       class="btn btn-light">
                        <i class="bi bi-box-seam me-1"></i>Lihat Paket
                    </a>
                <?php endif; ?>
                <a href="<?= e(url('admin/tagihan/index.php')) ?>" class="btn btn-secondary ms-auto">
                    <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <?php if ($isPaid && $payment !== null): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Pembayaran Lunas</h2>
                        <div class="card-subtitle">Tidak boleh ada pembayaran kedua.</div>
                    </div>
                    <span class="badge text-bg-success">LUNAS</span>
                </div>
                <div class="card-body">
                    <dl class="detail-list mb-0">
                        <div class="detail-row">
                            <dt>Nomor nota</dt>
                            <dd class="font-monospace"><?= e($payment['payment_number']) ?></dd>
                        </div>
                        <div class="detail-row">
                            <dt>Tanggal</dt>
                            <dd><?= e(tanggal_id((string) $payment['payment_date'], true)) ?></dd>
                        </div>
                        <div class="detail-row">
                            <dt>Metode</dt>
                            <dd><?= e(payment_method_label((string) $payment['method'])) ?></dd>
                        </div>
                        <div class="detail-row">
                            <dt>Jumlah dialokasikan</dt>
                            <dd class="amount fw-semibold">
                                <?= e(rupiah((float) ($invoice['paid_amount'] ?? $payment['amount']))) ?>
                            </dd>
                        </div>
                        <?php if (count($payment['invoices'] ?? []) > 1): ?>
                            <div class="detail-row">
                                <dt>Total nota</dt>
                                <dd class="amount"><?= e(rupiah((float) $payment['amount'])) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Cakupan nota</dt>
                                <dd><?= e((string) count($payment['invoices'])) ?> tagihan dalam satu pembayaran</dd>
                            </div>
                        <?php endif; ?>
                        <div class="detail-row">
                            <dt>Petugas</dt>
                            <dd><?= e(($payment['received_by_name'] ?: $payment['officer_name']) ?: '-') ?></dd>
                        </div>
                        <?php if (!empty($payment['reference_number'])): ?>
                            <div class="detail-row">
                                <dt>Referensi</dt>
                                <dd><?= e((string) $payment['reference_number']) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
                <div class="card-footer bg-white d-flex flex-wrap gap-2">
                    <a href="<?= e(url('admin/pembayaran/nota.php?id=' . (int) $payment['id'])) ?>"
                       class="btn btn-warning flex-grow-1" target="_blank" rel="noopener">
                        <i class="bi bi-receipt-cutoff me-1"></i>Buka Nota Pembayaran
                    </a>
                    <?php if (count($payment['invoices'] ?? []) > 1): ?>
                        <a href="<?= e(url('admin/pembayaran/detail.php?id=' . (int) $payment['id'])) ?>"
                           class="btn btn-light flex-grow-1">
                            <i class="bi bi-list-check me-1"></i>Detail Pembayaran
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($isUnpaid): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Belum Dibayar</h2>
                        <div class="card-subtitle">Pelunasan penuh, tanpa cicilan.</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Jumlah bayar harus <strong>sama persis</strong> dengan
                        <?= e(rupiah((float) $invoice['total_amount'])) ?>. Kurang atau lebih akan ditolak server.
                    </div>
                    <a href="<?= e(url('admin/pembayaran/tambah.php?invoice_id=' . (int) $invoice['id'])) ?>"
                       class="btn btn-success w-100">
                        <i class="bi bi-cash-stack me-1"></i>Catat Pelunasan
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isAdmin && !$isPaid && !$isCancelled): ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Batalkan Tagihan</h2>
                        <div class="card-subtitle">Tagihan batal tetap tersimpan sebagai riwayat.</div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('admin/tagihan/proses.php')) ?>"
                          data-confirm="Tagihan akan dibatalkan dan tidak dapat dibayar lagi. Riwayat tetap tersimpan."
                          data-confirm-title="Batalkan tagihan"
                          data-confirm-button="Ya, batalkan"
                          data-confirm-icon="warning" data-submit-once>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="id" value="<?= (int) $invoice['id'] ?>">
                        <label for="reason" class="form-label small">Alasan pembatalan <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm mb-2" id="reason" name="reason"
                                  rows="2" maxlength="255" required
                                  placeholder="Contoh: paket dibuat ganda"></textarea>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-x-circle me-1"></i>Batalkan Tagihan
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Riwayat Tagihan Paket</h2>
            <div class="card-subtitle">Tagihan lama tetap tersimpan meski paket sudah selesai.</div>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($history === []): ?>
            <div class="p-4 text-center text-muted small">Tidak ada riwayat tagihan lain untuk paket ini.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Terbit</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th>Pembayaran</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $row): ?>
                        <tr class="<?= (int) $row['id'] === $id ? 'table-active' : '' ?>">
                            <td>
                                <?php if ((int) $row['id'] === $id): ?>
                                    <span class="invoice-number"><?= e($row['invoice_number']) ?></span>
                                <?php else: ?>
                                    <a href="<?= e(url('admin/tagihan/detail.php?id=' . (int) $row['id'])) ?>"
                                       class="invoice-number text-decoration-none">
                                        <?= e($row['invoice_number']) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= e(tanggal_short_id((string) $row['issue_date'])) ?></td>
                            <td class="text-end amount"><?= e(rupiah((float) $row['total_amount'])) ?></td>
                            <td><?= invoice_status_badge((string) $row['status']) ?></td>
                            <td class="small">
                                <?= $row['payment_number'] !== null
                                    ? e((string) $row['payment_number'])
                                    : '<span class="text-muted">Belum dibayar</span>' ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= e(url('admin/tagihan/cetak.php?id=' . (int) $row['id'])) ?>"
                                   class="btn btn-light btn-sm" title="Cetak invoice"
                                   target="_blank" rel="noopener">
                                    <i class="bi bi-printer"></i>
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

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
