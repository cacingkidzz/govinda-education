<?php
declare(strict_types=1);

/** @var string $formMode */
/** @var array<string, mixed> $group */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

$formMode = $formMode ?? 'create';
$group = $group ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$isEdit = $formMode === 'edit';
$v = static fn (string $key, string $default = ''): string => old_value($old, $group, $key, $default);
$currentType = $v('group_type', 'group');
$currentCapacity = $v('capacity', '4');
$currentActive = $v('is_active', '1');
$memberCount = (int) ($group['member_count'] ?? 0);
?>

<form method="post" action="<?= e(url('admin/group/proses.php')) ?>" data-submit-once novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) ($group['id'] ?? 0) ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Identitas Group</h2>
                        <div class="card-subtitle">Data utama yang dipakai pada jadwal dan keanggotaan siswa.</div>
                    </div>
                    <span class="badge bg-teal-soft text-teal">
                        <i class="bi bi-diagram-3 me-1"></i>Akademik
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="name" class="form-label">Nama group <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'name') ?>" id="name"
                                   name="name" value="<?= e($v('name')) ?>" maxlength="120" required
                                   placeholder="Contoh: Matematika VIII A">
                            <?= field_error($errors, 'name') ?>
                        </div>
                        <div class="col-md-4">
                            <label for="code_display" class="form-label">Kode group</label>
                            <input type="text" class="form-control font-monospace" id="code_display"
                                   value="<?= e($isEdit ? (string) ($group['code'] ?? '') : 'Otomatis') ?>" readonly>
                            <div class="form-text">
                                <?= $isEdit
                                    ? 'Kode tidak diubah karena menjadi identitas group.'
                                    : 'Dibuat otomatis dan unik saat group disimpan.' ?>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="group_type" class="form-label">Jenis group <span class="text-danger">*</span></label>
                            <select class="form-select<?= field_invalid($errors, 'group_type') ?>" id="group_type"
                                    name="group_type" required data-group-type>
                                <option value="group" <?= $currentType === 'group' ? 'selected' : '' ?>>Group</option>
                                <option value="private" <?= $currentType === 'private' ? 'selected' : '' ?>>Private</option>
                            </select>
                            <?= field_error($errors, 'group_type') ?>
                        </div>
                        <div class="col-md-4">
                            <label for="capacity" class="form-label">Kapasitas maksimal <span class="text-danger">*</span></label>
                            <select class="form-select<?= field_invalid($errors, 'capacity') ?>" id="capacity"
                                    name="capacity" data-group-capacity required>
                                <option value="1" data-single-capacity
                                    <?= (int) $currentCapacity === 1 ? 'selected' : '' ?>
                                    <?= $currentType === 'private' ? '' : 'hidden' ?>>1 siswa</option>
                                <?php for ($cap = 2; $cap <= 7; $cap++): ?>
                                    <option value="<?= $cap ?>" <?= (int) $currentCapacity === $cap ? 'selected' : '' ?>>
                                        <?= $cap ?> siswa
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <?= field_error($errors, 'capacity') ?>
                            <div class="form-text" data-capacity-hint>
                                <?= $currentType === 'private'
                                    ? 'Group private selalu berkapasitas tepat 1 siswa.'
                                    : 'Group belajar dapat menampung 2–7 siswa.' ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="is_active" class="form-label">Status group</label>
                            <select class="form-select<?= field_invalid($errors, 'is_active') ?>" id="is_active" name="is_active">
                                <option value="1" <?= $currentActive === '1' ? 'selected' : '' ?>>Aktif</option>
                                <option value="0" <?= $currentActive === '0' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                            <?= field_error($errors, 'is_active') ?>
                        </div>

                        <div class="col-md-6">
                            <label for="description" class="form-label">Keterangan</label>
                            <textarea class="form-control<?= field_invalid($errors, 'description') ?>" id="description"
                                      name="description" rows="3" maxlength="1000"
                                      placeholder="Catatan tambahan tentang group (opsional)"><?= e($v('description')) ?></textarea>
                            <?= field_error($errors, 'description') ?>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-info mb-0 h-100 d-flex flex-column justify-content-center">
                                <div class="fw-semibold mb-1">
                                    <i class="bi bi-journal-bookmark me-1"></i>Mata pelajaran &amp; guru
                                </div>
                                <div class="small">
                                    Satu group dapat memiliki banyak mata pelajaran dengan guru berbeda.
                                    Relasi tersebut dikelola di halaman
                                    <strong>Mata Pelajaran dan Guru</strong> setelah group disimpan.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Aturan Kapasitas</h2>
                        <div class="card-subtitle">Divalidasi ulang di server dan database.</div>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="rule-list">
                        <li>
                            <span class="rule-icon rule-private"><i class="bi bi-person-fill"></i></span>
                            <span><strong>Private</strong> tepat 1 siswa, kapasitas dikunci pada 1.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-group"><i class="bi bi-people-fill"></i></span>
                            <span><strong>Group</strong> minimal 2 dan maksimal 7 siswa.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-lock"><i class="bi bi-shield-lock"></i></span>
                            <span>Kapasitas tidak boleh lebih kecil dari jumlah anggota aktif.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-clock"><i class="bi bi-clock-history"></i></span>
                            <span>Group dapat dibuat tanpa anggota, tetapi belum siap dipakai untuk pembelajaran.</span>
                        </li>
                    </ul>

                    <?php if ($isEdit && $memberCount > 0): ?>
                        <div class="alert alert-info py-2 px-3 mt-3 mb-0 small">
                            <i class="bi bi-people me-1"></i>
                            Saat ini group memiliki <strong><?= e((string) $memberCount) ?> anggota aktif</strong>.
                            Kapasitas minimal harus <?= e((string) $memberCount) ?>.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Group' ?>
                        </button>
                        <a href="<?= e($isEdit
                            ? url('admin/group/detail.php?id=' . (int) ($group['id'] ?? 0))
                            : url('admin/group/index.php')) ?>" class="btn btn-secondary">
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
    const capacity = document.getElementById('capacity');
    const hint = document.querySelector('[data-capacity-hint]');
    if (!type || !capacity) {
        return;
    }
    const singleOption = capacity.querySelector('[data-single-capacity]');

    const sync = () => {
        const isPrivate = type.value === 'private';
        if (isPrivate) {
            if (capacity.value !== '1') {
                capacity.dataset.previous = capacity.value || '4';
            }
            if (singleOption) {
                singleOption.hidden = false;
            }
            capacity.value = '1';
            capacity.disabled = true;
        } else {
            if (singleOption) {
                singleOption.hidden = true;
            }
            capacity.disabled = false;
            if (capacity.value === '1') {
                const previous = capacity.dataset.previous;
                capacity.value = previous && previous !== '1' ? previous : '4';
            }
        }
        if (hint) {
            hint.textContent = isPrivate
                ? 'Group private selalu berkapasitas tepat 1 siswa.'
                : 'Group belajar dapat menampung 2–7 siswa.';
        }
    };

    type.addEventListener('change', sync);
    sync();
})();
</script>
