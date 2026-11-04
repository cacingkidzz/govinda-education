<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$id = get_int('id');
$expense = $id > 0 ? Expense::find($id) : null;
if ($expense === null) {
    abort_404('Pengeluaran tidak ditemukan.');
}

$state = pull_form_state();
$errors = $state['errors'];
$isAdmin = is_admin();
$isRecorded = (string) $expense['status'] === Expense::STATUS_RECORDED;
$isCancelled = (string) $expense['status'] === Expense::STATUS_CANCELLED;

$pageTitle = 'Pengeluaran ' . $expense['expense_number'];
$pageSubtitle = 'Rincian biaya operasional dan jejak pencatatnya.';
$breadcrumbs = [
    ['label' => 'Pengeluaran', 'url' => url('admin/pengeluaran/index.php')],
    ['label' => $expense['expense_number']],
];
$activeMenu = 'pengeluaran';

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
                    <h2 class="card-title"><?= e($expense['expense_number']) ?></h2>
                    <div class="card-subtitle">
                        Dicatat <?= e(tanggal_id((string) $expense['expense_date'], true)) ?>
                        &middot; <?= e(expense_category_label((string) $expense['category'])) ?>
                    </div>
                </div>
                <?= expense_status_badge((string) $expense['status']) ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <dl class="detail-list mb-0">
                            <div class="detail-row">
                                <dt>Tanggal</dt>
                                <dd><?= e(tanggal_id((string) $expense['expense_date'], true)) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Kategori</dt>
                                <dd><?= expense_category_badge((string) $expense['category']) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Penerima / Vendor</dt>
                                <dd><?= e($expense['payee']) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Metode</dt>
                                <dd><?= payment_method_badge((string) $expense['method']) ?></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="detail-list mb-0">
                            <div class="detail-row">
                                <dt>Nominal</dt>
                                <dd class="amount fw-semibold fs-5"><?= e(rupiah((float) $expense['amount'])) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Nomor referensi</dt>
                                <dd><?= $expense['reference_number'] !== null && $expense['reference_number'] !== ''
                                    ? e((string) $expense['reference_number'])
                                    : '<span class="text-muted">—</span>' ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Dicatat oleh</dt>
                                <dd><?= e(($expense['officer_name'] ?: $expense['recorded_by_name']) ?: '-') ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Terakhir diperbarui</dt>
                                <dd><?= e(tanggal_waktu_id((string) $expense['updated_at'])) ?></dd>
                            </div>
                        </dl>
                    </div>
                    <?php if ($expense['notes'] !== null && trim((string) $expense['notes']) !== ''): ?>
                        <div class="col-12">
                            <div class="alert alert-light border mb-0 small">
                                <strong>Catatan:</strong> <?= e((string) $expense['notes']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($isCancelled): ?>
                        <div class="col-12">
                            <div class="alert alert-secondary mb-0 small">
                                <strong>Pengeluaran dibatalkan.</strong>
                                <?= e((string) ($expense['cancel_reason'] ?? '-')) ?>
                                <?php if ($expense['cancelled_at'] !== null): ?>
                                    &middot; <?= e(tanggal_waktu_id((string) $expense['cancelled_at'])) ?>
                                <?php endif; ?>
                                <?php if (!empty($expense['cancelled_by_name'])): ?>
                                    &middot; oleh <?= e((string) $expense['cancelled_by_name']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer d-flex flex-wrap gap-2">
                <?php if ($isRecorded): ?>
                    <a href="<?= e(url('admin/pengeluaran/edit.php?id=' . (int) $expense['id'])) ?>"
                       class="btn btn-primary">
                        <i class="bi bi-pencil-square me-1"></i>Edit Pengeluaran
                    </a>
                <?php endif; ?>
                <a href="<?= e(url('admin/pengeluaran/index.php')) ?>" class="btn btn-secondary ms-auto">
                    <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Ringkasan</h2>
                    <div class="card-subtitle">Pengeluaran yang mengurangi kas.</div>
                </div>
            </div>
            <div class="card-body">
                <dl class="detail-list mb-0">
                    <div class="detail-row">
                        <dt>Nomor</dt>
                        <dd class="font-monospace"><?= e($expense['expense_number']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Status</dt>
                        <dd><?= expense_status_badge((string) $expense['status']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Nominal</dt>
                        <dd class="amount fw-semibold"><?= e(rupiah((float) $expense['amount'])) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Dibuat</dt>
                        <dd><?= e(tanggal_waktu_id((string) $expense['created_at'])) ?></dd>
                    </div>
                </dl>
            </div>
            <div class="card-footer bg-white">
                <a href="<?= e(url('admin/laporan/keuangan.php')) ?>" class="btn btn-light w-100">
                    <i class="bi bi-graph-up-arrow me-1"></i>Lihat Rekap Keuangan
                </a>
            </div>
        </div>

        <?php if ($isAdmin && $isRecorded): ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Batalkan Pengeluaran</h2>
                        <div class="card-subtitle">Data tetap tersimpan sebagai riwayat.</div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('admin/pengeluaran/proses.php')) ?>"
                          data-confirm="Pengeluaran akan dibatalkan dan tidak dihitung ke kas maupun laporan. Riwayat tetap tersimpan."
                          data-confirm-title="Batalkan pengeluaran"
                          data-confirm-button="Ya, batalkan"
                          data-confirm-icon="warning" data-submit-once>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="id" value="<?= (int) $expense['id'] ?>">
                        <label for="reason" class="form-label small">Alasan pembatalan <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm mb-2" id="reason" name="reason"
                                  rows="2" maxlength="255" required
                                  placeholder="Contoh: salah input nominal"></textarea>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-x-circle me-1"></i>Batalkan Pengeluaran
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
