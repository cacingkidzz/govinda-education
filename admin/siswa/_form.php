<?php
declare(strict_types=1);

/** @var string $formMode */
/** @var array<string, mixed> $student */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

$formMode = $formMode ?? 'create';
$student = $student ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$isEdit = $formMode === 'edit';
$v = static fn (string $key, string $default = ''): string => old_value($old, $student, $key, $default);
$currentActive = $v('is_active', '1');
?>
<form method="post" action="<?= e(url('admin/siswa/proses.php')) ?>" enctype="multipart/form-data"
      data-submit-once novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) ($student['id'] ?? 0) ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Identitas Siswa</h2>
                        <div class="card-subtitle">Data utama yang digunakan pada seluruh modul akademik.</div>
                    </div>
                    <span class="badge bg-teal-soft text-teal">
                        <i class="bi bi-person-vcard me-1"></i>Profil
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="name" class="form-label">Nama lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'name') ?>" id="name"
                                   name="name" value="<?= e($v('name')) ?>" maxlength="120" required
                                   autocomplete="name" placeholder="Contoh: Ayu Pramesti">
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
                            <label for="code_display" class="form-label">Kode siswa</label>
                            <input type="text" class="form-control font-monospace" id="code_display"
                                   value="<?= e($isEdit ? (string) ($student['code'] ?? '') : 'Otomatis') ?>" readonly>
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
                            <label for="join_date" class="form-label">Tanggal mulai bergabung <span class="text-danger">*</span></label>
                            <input type="date" class="form-control<?= field_invalid($errors, 'join_date') ?>"
                                   id="join_date" name="join_date" value="<?= e($v('join_date', date('Y-m-d'))) ?>"
                                   max="<?= e(date('Y-m-d')) ?>" required>
                            <?= field_error($errors, 'join_date') ?>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Nomor HP siswa</label>
                            <input type="tel" class="form-control<?= field_invalid($errors, 'phone') ?>" id="phone"
                                   name="phone" value="<?= e($v('phone')) ?>" maxlength="30"
                                   placeholder="08xxxxxxxxxx" autocomplete="tel">
                            <?= field_error($errors, 'phone') ?>
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label">Alamat</label>
                            <textarea class="form-control<?= field_invalid($errors, 'address') ?>" id="address"
                                      name="address" rows="3" maxlength="500"
                                      placeholder="Alamat domisili siswa"><?= e($v('address')) ?></textarea>
                            <?= field_error($errors, 'address') ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Orang Tua / Wali</h2>
                        <div class="card-subtitle">Kontak yang dihubungkan dengan administrasi siswa.</div>
                    </div>
                    <span class="badge bg-teal-soft text-teal">
                        <i class="bi bi-telephone me-1"></i>Kontak
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="parent_name" class="form-label">Nama orang tua / wali</label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'parent_name') ?>"
                                   id="parent_name" name="parent_name" value="<?= e($v('parent_name')) ?>"
                                   maxlength="120" placeholder="Nama orang tua atau wali">
                            <?= field_error($errors, 'parent_name') ?>
                        </div>
                        <div class="col-md-6">
                            <label for="parent_phone" class="form-label">Nomor HP orang tua / wali</label>
                            <input type="tel" class="form-control<?= field_invalid($errors, 'parent_phone') ?>"
                                   id="parent_phone" name="parent_phone" value="<?= e($v('parent_phone')) ?>"
                                   maxlength="30" placeholder="08xxxxxxxxxx">
                            <?= field_error($errors, 'parent_phone') ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Data Sekolah</h2>
                        <div class="card-subtitle">Informasi sekolah asal dan kelas siswa.</div>
                    </div>
                    <span class="badge bg-teal-soft text-teal">
                        <i class="bi bi-building me-1"></i>Sekolah
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="school" class="form-label">Sekolah asal</label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'school') ?>" id="school"
                                   name="school" value="<?= e($v('school')) ?>" maxlength="150"
                                   placeholder="Contoh: SMP Negeri 2 Bandung">
                            <?= field_error($errors, 'school') ?>
                        </div>
                        <div class="col-md-4">
                            <label for="school_class" class="form-label">Kelas sekolah</label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'school_class') ?>"
                                   id="school_class" name="school_class" value="<?= e($v('school_class')) ?>"
                                   maxlength="50" placeholder="Contoh: VIII A" list="class-options">
                            <datalist id="class-options">
                                <?php foreach (Student::schoolClasses() as $classOption): ?>
                                    <option value="<?= e($classOption) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <?= field_error($errors, 'school_class') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Foto Siswa</h2>
                        <div class="card-subtitle">JPG, PNG, atau WEBP maksimal <?= e(Upload::maxSizeLabel()) ?>.</div>
                    </div>
                </div>
                <div class="card-body text-center">
                    <img src="<?= e(photo_url($student['photo'] ?? null)) ?>" alt="Pratinjau foto siswa"
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
                        <h2 class="card-title">Status Siswa</h2>
                        <div class="card-subtitle">Siswa nonaktif tidak dapat dimasukkan ke group baru.</div>
                    </div>
                </div>
                <div class="card-body">
                    <label for="is_active" class="form-label">Status keaktifan</label>
                    <select class="form-select<?= field_invalid($errors, 'is_active') ?>" id="is_active" name="is_active">
                        <option value="1" <?= $currentActive === '1' ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= $currentActive === '0' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                    <?= field_error($errors, 'is_active') ?>
                    <div class="form-text mt-2">
                        Perubahan status juga dapat dilakukan dari daftar siswa tanpa membuka formulir ini.
                    </div>
                </div>
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Siswa' ?>
                        </button>
                        <a href="<?= e($isEdit
                            ? url('admin/siswa/detail.php?id=' . (int) ($student['id'] ?? 0))
                            : url('admin/siswa/index.php')) ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
