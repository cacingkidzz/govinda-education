<?php
declare(strict_types=1);

/** @var string $formMode */
/** @var array<string, mixed> $subject */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

$formMode = $formMode ?? 'create';
$subject = $subject ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$isEdit = $formMode === 'edit';
$v = static fn (string $key, string $default = ''): string => old_value($old, $subject, $key, $default);
$currentActive = $v('is_active', '1');
$usage = $isEdit ? Subject::usageCount((int) ($subject['id'] ?? 0)) : 0;
?>
<form method="post" action="<?= e(url('admin/mata-pelajaran/proses.php')) ?>" data-submit-once novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) ($subject['id'] ?? 0) ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Informasi Mata Pelajaran</h2>
                        <div class="card-subtitle">Nama mapel digunakan pada group dan jadwal belajar.</div>
                    </div>
                    <span class="badge bg-teal-soft text-teal">
                        <i class="bi bi-journal-bookmark me-1"></i>Mapel
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="code_display" class="form-label">Kode mata pelajaran</label>
                            <input type="text" class="form-control font-monospace" id="code_display"
                                   value="<?= e($isEdit ? (string) ($subject['code'] ?? '') : 'Otomatis') ?>" readonly>
                            <div class="form-text">
                                <?= $isEdit
                                    ? 'Kode mapel bersifat tetap dan tidak dapat diubah.'
                                    : 'Dibuat otomatis dan unik saat data disimpan.' ?>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label for="name" class="form-label">Nama mata pelajaran <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'name') ?>" id="name"
                                   name="name" value="<?= e($v('name')) ?>" maxlength="100" required
                                   placeholder="Contoh: Matematika">
                            <?= field_error($errors, 'name') ?>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Keterangan</label>
                            <textarea class="form-control<?= field_invalid($errors, 'description') ?>"
                                      id="description" name="description" rows="4" maxlength="500"
                                      placeholder="Deskripsi singkat mata pelajaran (opsional)"><?= e($v('description')) ?></textarea>
                            <?= field_error($errors, 'description') ?>
                            <div class="form-text">Maksimal 500 karakter.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Status</h2>
                        <div class="card-subtitle">Mapel nonaktif tidak dapat dipilih untuk group baru.</div>
                    </div>
                </div>
                <div class="card-body">
                    <label for="is_active" class="form-label">Status keaktifan</label>
                    <select class="form-select<?= field_invalid($errors, 'is_active') ?>" id="is_active" name="is_active">
                        <option value="1" <?= $currentActive === '1' ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= $currentActive === '0' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                    <?= field_error($errors, 'is_active') ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title"><?= $isEdit ? 'Penggunaan' : 'Catatan' ?></h2>
                        <div class="card-subtitle">
                            <?= $isEdit ? 'Mapel ini sudah dipakai pada group belajar.' : 'Mapel baru dapat langsung dipakai group.' ?>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($isEdit): ?>
                        <div class="usage-box">
                            <div class="usage-value"><?= e((string) $usage) ?></div>
                            <div class="usage-label">group menggunakan mapel ini</div>
                        </div>
                        <p class="form-text mt-2 mb-0">
                            <?= $usage > 0
                                ? 'Karena sudah digunakan, mapel hanya dapat dinonaktifkan, tidak dihapus permanen.'
                                : 'Mapel belum digunakan group. Tetap gunakan nonaktifkan agar data historis aman.' ?>
                        </p>
                    <?php else: ?>
                        <p class="form-text mb-0">
                            Kode mapel dibuat otomatis. Pastikan nama mapel unik dan mudah dikenali.
                        </p>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Mata Pelajaran' ?>
                        </button>
                        <a href="<?= e($isEdit
                            ? url('admin/mata-pelajaran/detail.php?id=' . (int) ($subject['id'] ?? 0))
                            : url('admin/mata-pelajaran/index.php')) ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
