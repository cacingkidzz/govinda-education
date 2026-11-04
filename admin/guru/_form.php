<?php
declare(strict_types=1);

/** @var string $formMode */
/** @var array<string, mixed> $teacher */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

$formMode = $formMode ?? 'create';
$teacher = $teacher ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$isEdit = $formMode === 'edit';
$v = static fn (string $key, string $default = ''): string => old_value($old, $teacher, $key, $default);

if (!isset($selectedSubjects) || !is_array($selectedSubjects)) {
    $selectedSubjects = $isEdit ? Teacher::subjectIds((int) ($teacher['id'] ?? 0)) : [];
}
$selectedSubjects = array_map('intval', $selectedSubjects);
if (array_key_exists('subject_ids', $old) && is_array($old['subject_ids'])) {
    $selectedSubjects = array_map('intval', $old['subject_ids']);
}

$subjectOptions = Subject::activeList();
if ($isEdit) {
    // Tetap tampilkan mapel yang sudah terpasang meskipun kini nonaktif.
    foreach (Teacher::subjects((int) ($teacher['id'] ?? 0)) as $assigned) {
        $already = false;
        foreach ($subjectOptions as $option) {
            if ((int) $option['id'] === (int) $assigned['id']) {
                $already = true;
                break;
            }
        }
        if (!$already) {
            $subjectOptions[] = $assigned;
        }
    }
    usort($subjectOptions, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));
}

$currentActive = $v('is_active', '1');
?>
<form method="post" action="<?= e(url('admin/guru/proses.php')) ?>" enctype="multipart/form-data"
      data-submit-once novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) ($teacher['id'] ?? 0) ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Identitas Guru</h2>
                        <div class="card-subtitle">Data utama pengajar yang digunakan pada modul akademik.</div>
                    </div>
                    <span class="badge bg-teal-soft text-teal">
                        <i class="bi bi-person-video3 me-1"></i>Profil
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="name" class="form-label">Nama lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'name') ?>" id="name"
                                   name="name" value="<?= e($v('name')) ?>" maxlength="120" required
                                   autocomplete="name" placeholder="Contoh: Budi Santoso">
                            <?= field_error($errors, 'name') ?>
                        </div>
                        <div class="col-md-4">
                            <label for="gender" class="form-label">Jenis kelamin <span class="text-danger">*</span></label>
                            <select class="form-select<?= field_invalid($errors, 'gender') ?>" id="gender" name="gender" required>
                                <option value="L" <?= $v('gender', 'L') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= $v('gender', 'L') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                            <?= field_error($errors, 'gender') ?>
                        </div>

                        <div class="col-md-4">
                            <label for="code_display" class="form-label">Kode guru</label>
                            <input type="text" class="form-control font-monospace" id="code_display"
                                   value="<?= e($isEdit ? (string) ($teacher['code'] ?? '') : 'Otomatis') ?>" readonly>
                            <div class="form-text">
                                <?= $isEdit
                                    ? 'Kode tidak diubah karena menjadi identitas transaksi.'
                                    : 'Dibuat otomatis dan unik saat data disimpan.' ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="birth_place" class="form-label">Tempat lahir</label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'birth_place') ?>"
                                   id="birth_place" name="birth_place" value="<?= e($v('birth_place')) ?>"
                                   maxlength="100" placeholder="Contoh: Bandung">
                            <?= field_error($errors, 'birth_place') ?>
                        </div>
                        <div class="col-md-4">
                            <label for="birth_date" class="form-label">Tanggal lahir</label>
                            <input type="date" class="form-control<?= field_invalid($errors, 'birth_date') ?>"
                                   id="birth_date" name="birth_date" value="<?= e($v('birth_date')) ?>"
                                   max="<?= e(date('Y-m-d')) ?>">
                            <?= field_error($errors, 'birth_date') ?>
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label">Nomor HP</label>
                            <input type="tel" class="form-control<?= field_invalid($errors, 'phone') ?>" id="phone"
                                   name="phone" value="<?= e($v('phone')) ?>" maxlength="30"
                                   placeholder="08xxxxxxxxxx" autocomplete="tel">
                            <?= field_error($errors, 'phone') ?>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control<?= field_invalid($errors, 'email') ?>" id="email"
                                   name="email" value="<?= e($v('email')) ?>" maxlength="120"
                                   placeholder="nama@contoh.com" autocomplete="email">
                            <?= field_error($errors, 'email') ?>
                        </div>

                        <div class="col-md-6">
                            <label for="education" class="form-label">Pendidikan terakhir</label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'education') ?>"
                                   id="education" name="education" value="<?= e($v('education')) ?>"
                                   maxlength="120" placeholder="Contoh: S1 Pendidikan Matematika">
                            <?= field_error($errors, 'education') ?>
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label">Alamat</label>
                            <textarea class="form-control<?= field_invalid($errors, 'address') ?>" id="address"
                                      name="address" rows="3" maxlength="500"
                                      placeholder="Alamat domisili guru"><?= e($v('address')) ?></textarea>
                            <?= field_error($errors, 'address') ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Mata Pelajaran Diampu</h2>
                        <div class="card-subtitle">Pilih satu atau lebih mata pelajaran yang diajarkan.</div>
                    </div>
                    <span class="badge bg-teal-soft text-teal">
                        <i class="bi bi-journal-bookmark me-1"></i>Mapel
                    </span>
                </div>
                <div class="card-body">
                    <?php if ($subjectOptions === []): ?>
                        <div class="empty-state py-4">
                            <div class="empty-state-icon"><i class="bi bi-journal-x"></i></div>
                            <div class="empty-state-title">Belum ada mata pelajaran aktif</div>
                            <p class="empty-state-text">
                                Tambahkan mata pelajaran terlebih dahulu melalui menu
                                <a href="<?= e(url('admin/mata-pelajaran/index.php')) ?>">Mata Pelajaran</a>.
                            </p>
                        </div>
                    <?php else: ?>
                        <label for="subject_ids" class="form-label">Mata pelajaran</label>
                        <select class="form-select js-select2<?= field_invalid($errors, 'subject_ids') ?>"
                                id="subject_ids" name="subject_ids[]" multiple
                                data-placeholder="Pilih mata pelajaran">
                            <?php foreach ($subjectOptions as $option): ?>
                                <option value="<?= (int) $option['id'] ?>"
                                    <?= in_array((int) $option['id'], $selectedSubjects, true) ? 'selected' : '' ?>>
                                    <?= e($option['name']) ?>
                                    <?php if ((int) ($option['is_active'] ?? 1) !== 1): ?>
                                        (nonaktif)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, 'subject_ids') ?>
                        <div class="form-text mt-2">Opsional. Daftar mapel juga dapat diubah kapan saja.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Foto Guru</h2>
                        <div class="card-subtitle">JPG, PNG, atau WEBP maksimal <?= e(Upload::maxSizeLabel()) ?>.</div>
                    </div>
                </div>
                <div class="card-body text-center">
                    <img src="<?= e(photo_url($teacher['photo'] ?? null)) ?>" alt="Pratinjau foto guru"
                         class="photo-preview mb-3" id="photo-preview" data-photo-preview>
                    <input type="file" class="form-control<?= field_invalid($errors, 'photo') ?>" id="photo"
                           name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                           data-photo-input data-photo-preview="#photo-preview">
                    <?= field_error($errors, 'photo') ?>
                    <div class="form-text text-start mt-2">
                        <?= $isEdit
                            ? 'Biarkan kosong jika tidak ingin mengganti foto. Foto lama dihapus hanya setelah penyimpanan berhasil.'
                            : 'Foto tidak wajib. Avatar default digunakan bila tidak ada foto.' ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Status Guru</h2>
                        <div class="card-subtitle">Guru nonaktif tidak dapat dipilih untuk group baru.</div>
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
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Guru' ?>
                        </button>
                        <a href="<?= e($isEdit
                            ? url('admin/guru/detail.php?id=' . (int) ($teacher['id'] ?? 0))
                            : url('admin/guru/index.php')) ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
