<?php
declare(strict_types=1);

/** @var string $formMode */
/** @var array<string, mixed> $rule */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

$formMode = $formMode ?? 'create';
$rule = $rule ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$isEdit = $formMode === 'edit';
$v = static fn (string $key, string $default = ''): string => old_value($old, $rule, $key, $default);
$typeValue = $v('group_type', 'private');
$countValue = $v('student_count', $typeValue === 'private' ? '1' : '2');
$amountValue = $v('amount') !== '' ? rupiah((float) $v('amount'), false) : '';
$startValue = $v('effective_start', date('Y-m-d'));
$endValue = $v('effective_end');
$activeValue = $v('is_active', '1');
?>

<form method="post" action="<?= e(url('admin/tarif/proses.php')) ?>" data-submit-once novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) ($rule['id'] ?? 0) ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Informasi Tarif</h2>
                        <div class="card-subtitle">Nominal untuk satu paket <?= e((string) Pricing::PACKAGE_SIZE) ?> pertemuan per siswa.</div>
                    </div>
                    <span class="badge bg-teal-soft text-teal"><i class="bi bi-cash-coin me-1"></i>Tarif</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors['form'])): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($errors['form']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="group_type" class="form-label">Jenis kelompok <span class="text-danger">*</span></label>
                            <select class="form-select<?= field_invalid($errors, 'group_type') ?>" id="group_type" name="group_type" required>
                                <option value="private" <?= $typeValue === 'private' ? 'selected' : '' ?>>Private — 1 siswa</option>
                                <option value="group" <?= $typeValue === 'group' ? 'selected' : '' ?>>Reguler — 2 sampai 7 siswa</option>
                            </select>
                            <?= field_error($errors, 'group_type') ?>
                        </div>
                        <div class="col-md-6">
                            <label for="student_count" class="form-label">Jumlah anggota aktif <span class="text-danger">*</span></label>
                            <select class="form-select<?= field_invalid($errors, 'student_count') ?>" id="student_count" name="student_count" required>
                                <option value="1" <?= $countValue === '1' ? 'selected' : '' ?>>1 siswa</option>
                                <?php for ($count = 2; $count <= 7; $count++): ?>
                                    <option value="<?= $count ?>" <?= $countValue === (string) $count ? 'selected' : '' ?>>
                                        <?= $count ?> siswa
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <?= field_error($errors, 'student_count') ?>
                            <div class="form-text">Tarif dipilih saat paket dimulai berdasarkan jumlah anggota aktif kelompok.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="amount" class="form-label">Nominal per paket (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" inputmode="numeric" class="form-control<?= field_invalid($errors, 'amount') ?>"
                                       id="amount" name="amount" value="<?= e($amountValue) ?>" required
                                       placeholder="Contoh: 400.000" autocomplete="off">
                            </div>
                            <?= field_error($errors, 'amount') ?>
                            <div class="form-text">Nominal penuh untuk 8 pertemuan, bukan per bulan.</div>
                        </div>
                        <div class="col-md-3">
                            <label for="effective_start" class="form-label">Berlaku mulai <span class="text-danger">*</span></label>
                            <input type="date" class="form-control<?= field_invalid($errors, 'effective_start') ?>"
                                   id="effective_start" name="effective_start" value="<?= e($startValue) ?>" required>
                            <?= field_error($errors, 'effective_start') ?>
                        </div>
                        <div class="col-md-3">
                            <label for="effective_end" class="form-label">Berlaku sampai</label>
                            <input type="date" class="form-control<?= field_invalid($errors, 'effective_end') ?>"
                                   id="effective_end" name="effective_end" value="<?= e($endValue) ?>">
                            <?= field_error($errors, 'effective_end') ?>
                            <div class="form-text">Kosongkan bila tanpa batas.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="is_active" class="form-label">Status</label>
                            <select class="form-select" id="is_active" name="is_active">
                                <option value="1" <?= $activeValue === '1' ? 'selected' : '' ?>>Aktif</option>
                                <option value="0" <?= $activeValue === '0' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label for="notes" class="form-label">Keterangan</label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'notes') ?>"
                                   id="notes" name="notes" maxlength="255" value="<?= e($v('notes')) ?>"
                                   placeholder="Catatan internal (opsional)">
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
                        <h2 class="card-title">Ketentuan Tarif</h2>
                        <div class="card-subtitle">Berlaku untuk tagihan baru saja.</div>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="rule-list">
                        <li>
                            <span class="rule-icon rule-check"><i class="bi bi-people"></i></span>
                            <span>Private berlaku untuk kelompok berisi tepat 1 siswa.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-check"><i class="bi bi-people-fill"></i></span>
                            <span>Reguler berlaku untuk kelompok 2–7 siswa sesuai jumlah anggota aktif.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-lock"><i class="bi bi-calendar-range"></i></span>
                            <span>Periode tarif dengan jenis dan jumlah siswa yang sama tidak boleh bertumpukan.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-lock"><i class="bi bi-receipt"></i></span>
                            <span>Tagihan dan nota lama tetap memakai nominal snapshot saat diterbitkan.</span>
                        </li>
                    </ul>
                </div>
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Tarif' ?>
                        </button>
                        <a href="<?= e(url('admin/tarif/index.php')) ?>" class="btn btn-secondary">
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
    const type = document.getElementById('group_type');
    const count = document.getElementById('student_count');
    const amount = document.getElementById('amount');
    const start = document.getElementById('effective_start');
    const end = document.getElementById('effective_end');

    const syncType = () => {
        const isPrivate = type.value === 'private';
        Array.from(count.options).forEach((option) => {
            option.hidden = isPrivate ? option.value !== '1' : option.value === '1';
        });
        if (isPrivate) {
            count.value = '1';
        } else if (count.value === '1') {
            count.value = '2';
        }
    };
    type.addEventListener('change', syncType);
    syncType();

    if (amount) {
        amount.addEventListener('input', () => {
            const digits = amount.value.replace(/[^0-9]/g, '');
            amount.value = digits ? Number(digits).toLocaleString('id-ID') : '';
        });
    }
    if (start && end) {
        const syncEnd = () => {
            end.min = start.value;
            if (start.value && end.value && end.value < start.value) {
                end.value = '';
            }
        };
        start.addEventListener('change', syncEnd);
        syncEnd();
    }
})();
</script>
