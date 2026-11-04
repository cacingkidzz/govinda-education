<?php
declare(strict_types=1);

/** @var string $formMode */
/** @var array<string, mixed> $expense */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

$formMode = $formMode ?? 'create';
$expense = $expense ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$isEdit = $formMode === 'edit';
$v = static fn (string $key, string $default = ''): string => old_value($old, $expense, $key, $default);
$dateValue = $v('expense_date', date('Y-m-d'));
$categoryValue = $v('category', 'lainnya');
$methodValue = $v('method', Expense::METHOD_CASH);
$amountRaw = $v('amount');
$amountValue = $amountRaw !== '' ? rupiah((float) parse_currency($amountRaw), false) : '';
?>

<form method="post" action="<?= e(url('admin/pengeluaran/proses.php')) ?>" data-submit-once novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) ($expense['id'] ?? 0) ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Informasi Pengeluaran</h2>
                        <div class="card-subtitle">Biaya operasional bimbel yang benar-benar dikeluarkan.</div>
                    </div>
                    <span class="badge bg-teal-soft text-teal">
                        <i class="bi bi-wallet2 me-1"></i>Pengeluaran
                    </span>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors['form'])): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($errors['form']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="expense_date" class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" class="form-control<?= field_invalid($errors, 'expense_date') ?>"
                                   id="expense_date" name="expense_date" value="<?= e($dateValue) ?>" required>
                            <?= field_error($errors, 'expense_date') ?>
                        </div>
                        <div class="col-md-4">
                            <label for="category" class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select<?= field_invalid($errors, 'category') ?>"
                                    id="category" name="category" required>
                                <?php foreach (Expense::categoryOptions() as $option): ?>
                                    <option value="<?= e($option) ?>" <?= $categoryValue === $option ? 'selected' : '' ?>>
                                        <?= e(expense_category_label($option)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'category') ?>
                        </div>
                        <div class="col-md-4">
                            <label for="method" class="form-label">Metode <span class="text-danger">*</span></label>
                            <select class="form-select<?= field_invalid($errors, 'method') ?>"
                                    id="method" name="method" required>
                                <?php foreach (Expense::methodOptions() as $option): ?>
                                    <option value="<?= e($option) ?>" <?= $methodValue === $option ? 'selected' : '' ?>>
                                        <?= e(payment_method_label($option)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'method') ?>
                        </div>

                        <div class="col-md-6">
                            <label for="amount" class="form-label">Nominal (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" inputmode="numeric" autocomplete="off"
                                       class="form-control<?= field_invalid($errors, 'amount') ?>"
                                       id="amount" name="amount" value="<?= e($amountValue) ?>" required
                                       placeholder="Contoh: 250.000">
                            </div>
                            <?= field_error($errors, 'amount') ?>
                            <div class="form-text">Nominal penuh yang dibayarkan, bukan per siswa.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="payee" class="form-label">Penerima / Vendor <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'payee') ?>"
                                   id="payee" name="payee" maxlength="120" value="<?= e($v('payee')) ?>" required
                                   placeholder="Contoh: Guru Matematika / PLN / Toko ATK">
                            <?= field_error($errors, 'payee') ?>
                        </div>

                        <div class="col-md-6">
                            <label for="reference_number" class="form-label">Nomor referensi</label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'reference_number') ?>"
                                   id="reference_number" name="reference_number" maxlength="100"
                                   value="<?= e($v('reference_number')) ?>"
                                   placeholder="Nomor kuitansi / transaksi (opsional)">
                            <?= field_error($errors, 'reference_number') ?>
                        </div>
                        <div class="col-md-6">
                            <label for="notes" class="form-label">Catatan</label>
                            <textarea class="form-control<?= field_invalid($errors, 'notes') ?>"
                                      id="notes" name="notes" rows="2" maxlength="500"
                                      placeholder="Keterangan tambahan (opsional)"><?= e($v('notes')) ?></textarea>
                            <?= field_error($errors, 'notes') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Ketentuan Pencatatan</h2>
                        <div class="card-subtitle">Agar kas dan laporan tetap akurat.</div>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="rule-list">
                        <li>
                            <span class="rule-icon rule-check"><i class="bi bi-hash"></i></span>
                            <span>Nomor pengeluaran dibuat otomatis dengan format <code>OUT-YYYYMM-####</code>.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-check"><i class="bi bi-cash-coin"></i></span>
                            <span>Nominal wajib lebih besar dari nol dan disimpan sebagai nilai uang.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-lock"><i class="bi bi-lock"></i></span>
                            <span>Data pengeluaran tidak dihapus permanen. Koreksi dilakukan dengan pembatalan ber-audit.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-check"><i class="bi bi-person-check"></i></span>
                            <span>Nama petugas yang mencatat disimpan sebagai snapshot.</span>
                        </li>
                    </ul>
                </div>
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Pengeluaran' ?>
                        </button>
                        <a href="<?= e($isEdit
                            ? url('admin/pengeluaran/detail.php?id=' . (int) ($expense['id'] ?? 0))
                            : url('admin/pengeluaran/index.php')) ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    'use strict';
    const amount = document.getElementById('amount');
    if (amount) {
        amount.addEventListener('input', () => {
            const digits = amount.value.replace(/[^0-9]/g, '');
            amount.value = digits ? Number(digits).toLocaleString('id-ID') : '';
        });
    }
})();
</script>
