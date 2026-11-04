<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();

$profile = Profile::all();
$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];
$hasCustomLogo = Profile::hasCustomLogo();
$lastUpdated = Profile::lastUpdated();

$pageTitle = 'Edit Profil Bimbel';
$pageSubtitle = 'Perbarui identitas, kontak, dan logo lembaga.';
$breadcrumbs = [
    ['label' => 'Profil Bimbel', 'url' => url('admin/profil/index.php')],
    ['label' => 'Edit'],
];
$activeMenu = 'profil';

$v = static fn (string $key, string $default = ''): string => old_value($old, $profile, $key, $default);
$removeLogo = old_value($old, [], 'remove_logo', '0') === '1';

require_once ROOT_PATH . '/includes/header.php';
?>

<form method="post" action="<?= e(url('admin/profil/proses.php')) ?>" enctype="multipart/form-data"
      data-submit-once novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update">

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Identitas Bimbel</h2>
                        <div class="card-subtitle">Nama dan tagline tampil di sidebar, login, serta kop dokumen.</div>
                    </div>
                    <span class="badge bg-teal-soft text-teal">
                        <i class="bi bi-building me-1"></i>Identitas
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="app_name" class="form-label">Nama bimbel <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'app_name') ?>"
                                   id="app_name" name="app_name" value="<?= e($v('app_name', APP_NAME)) ?>"
                                   maxlength="120" required autocomplete="organization"
                                   placeholder="Contoh: Govinda Education">
                            <?= field_error($errors, 'app_name') ?>
                        </div>
                        <div class="col-md-5">
                            <label for="app_tagline" class="form-label">Tagline</label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'app_tagline') ?>"
                                   id="app_tagline" name="app_tagline" value="<?= e($v('app_tagline')) ?>"
                                   maxlength="150" placeholder="Contoh: Bimbingan Belajar">
                            <?= field_error($errors, 'app_tagline') ?>
                            <div class="form-text">Ditampilkan sebagai subjudul identitas.</div>
                        </div>

                        <div class="col-12">
                            <label for="app_description" class="form-label">Deskripsi singkat</label>
                            <textarea class="form-control<?= field_invalid($errors, 'app_description') ?>"
                                      id="app_description" name="app_description" rows="3" maxlength="500"
                                      placeholder="Ringkasan singkat tentang bimbel"><?= e($v('app_description')) ?></textarea>
                            <?= field_error($errors, 'app_description') ?>
                            <div class="form-text">
                                Dipakai pada hero halaman login dan ringkasan profil. Maksimal 500 karakter.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Kontak &amp; Alamat</h2>
                        <div class="card-subtitle">Tampil pada invoice, nota, dan halaman profil.</div>
                    </div>
                    <span class="badge bg-teal-soft text-teal">
                        <i class="bi bi-geo-alt me-1"></i>Kontak
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="app_address" class="form-label">Alamat</label>
                            <textarea class="form-control<?= field_invalid($errors, 'app_address') ?>"
                                      id="app_address" name="app_address" rows="3" maxlength="500"
                                      placeholder="Alamat lengkap bimbel"><?= e($v('app_address')) ?></textarea>
                            <?= field_error($errors, 'app_address') ?>
                        </div>
                        <div class="col-md-4">
                            <label for="app_phone" class="form-label">Telepon</label>
                            <input type="tel" class="form-control<?= field_invalid($errors, 'app_phone') ?>"
                                   id="app_phone" name="app_phone" value="<?= e($v('app_phone')) ?>"
                                   maxlength="30" placeholder="08xxxxxxxxxx" autocomplete="tel">
                            <?= field_error($errors, 'app_phone') ?>
                        </div>
                        <div class="col-md-4">
                            <label for="app_email" class="form-label">Email</label>
                            <input type="email" class="form-control<?= field_invalid($errors, 'app_email') ?>"
                                   id="app_email" name="app_email" value="<?= e($v('app_email')) ?>"
                                   maxlength="150" placeholder="admin@bimbel.sch.id" autocomplete="email">
                            <?= field_error($errors, 'app_email') ?>
                        </div>
                        <div class="col-md-4">
                            <label for="app_website" class="form-label">Situs web</label>
                            <input type="url" class="form-control<?= field_invalid($errors, 'app_website') ?>"
                                   id="app_website" name="app_website" value="<?= e($v('app_website')) ?>"
                                   maxlength="200" placeholder="https://bimbel.sch.id" autocomplete="url">
                            <?= field_error($errors, 'app_website') ?>
                            <div class="form-text">Boleh tanpa https://, sistem menambahkan otomatis.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Logo Bimbel</h2>
                        <div class="card-subtitle">JPG, PNG, atau WEBP maksimal <?= e(Upload::maxSizeLabel()) ?>.</div>
                    </div>
                    <?php if ($hasCustomLogo): ?>
                        <span class="badge text-bg-success">Kustom</span>
                    <?php else: ?>
                        <span class="badge text-bg-secondary">Bawaan</span>
                    <?php endif; ?>
                </div>
                <div class="card-body text-center">
                    <img src="<?= e(Profile::logoUrl()) ?>" alt="Pratinjau logo bimbel"
                         class="profile-logo-preview mb-3 <?= e(Profile::logoOnLightClass()) ?>"
                         id="logo-preview" data-photo-preview>
                    <input type="file" class="form-control<?= field_invalid($errors, 'logo') ?>" id="logo"
                           name="logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                           data-photo-input data-photo-preview="#logo-preview">
                    <?= field_error($errors, 'logo') ?>
                    <div class="form-text text-start mt-2">
                        Biarkan kosong jika tidak ingin mengganti logo. File lama dihapus hanya setelah
                        penyimpanan berhasil.
                    </div>

                    <?php if ($hasCustomLogo): ?>
                        <div class="form-check text-start mt-3">
                            <input class="form-check-input<?= field_invalid($errors, 'remove_logo') ?>"
                                   type="checkbox" value="1" id="remove_logo" name="remove_logo"
                                   <?= $removeLogo ? 'checked' : '' ?>>
                            <label class="form-check-label" for="remove_logo">
                                Hapus logo kustom dan pakai logo bawaan
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Simpan Perubahan</h2>
                        <div class="card-subtitle">Berlaku langsung setelah disimpan.</div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($lastUpdated !== null): ?>
                        <div class="small text-secondary mb-3">
                            <i class="bi bi-clock-history me-1"></i>
                            Terakhir diperbarui <?= e(tanggal_waktu_id($lastUpdated)) ?> WIB
                        </div>
                    <?php endif; ?>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i>Simpan Profil
                        </button>
                        <a href="<?= e(url('admin/profil/index.php')) ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Batal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
