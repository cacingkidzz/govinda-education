<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();

$profile = Profile::all();
$logoPath = Profile::logoPath();
$hasCustomLogo = $logoPath !== '';
$lastUpdated = Profile::lastUpdated();

$pageTitle = 'Profil Bimbel';
$pageSubtitle = 'Identitas lembaga yang tampil pada aplikasi dan dokumen resmi.';
$breadcrumbs = [['label' => 'Profil Bimbel']];
$activeMenu = 'profil';

$address = Profile::address();
$phone = Profile::phone();
$email = Profile::email();
$website = Profile::websiteUrl();
$websiteLabel = Profile::websiteLabel();
$description = Profile::description();

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="dash-hero mb-4">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <div class="dash-hero-content">
                <div class="dash-hero-eyebrow">
                    <i class="bi bi-buildings"></i> Identitas Lembaga
                </div>
                <h2><?= e(Profile::name()) ?></h2>
                <p>
                    <?= e($description !== ''
                        ? $description
                        : 'Lengkapi identitas bimbel agar tampil konsisten di dashboard, halaman login, invoice, dan nota pembayaran.') ?>
                </p>
                <div class="dash-hero-meta">
                    <span class="dash-pill <?= $hasCustomLogo ? 'dash-pill-gold' : '' ?>">
                        <i class="bi <?= $hasCustomLogo ? 'bi-image-fill' : 'bi-image' ?>"></i>
                        <?= $hasCustomLogo ? 'Logo kustom' : 'Logo bawaan' ?>
                    </span>
                    <span class="dash-pill">
                        <i class="bi bi-telephone"></i>
                        <?= e($phone !== '' ? phone_display($phone) : 'Telepon belum diisi') ?>
                    </span>
                    <span class="dash-pill">
                        <i class="bi bi-geo-alt"></i>
                        <?= e($address !== '' ? mb_strimwidth($address, 0, 48, '…') : 'Alamat belum diisi') ?>
                    </span>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="<?= e(url('admin/profil/edit.php')) ?>" class="btn btn-light btn-sm">
                        <i class="bi bi-pencil-square me-1"></i>Edit Profil
                    </a>
                    <a href="<?= e(url('admin/dashboard.php')) ?>" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-grid-1x2 me-1"></i>Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
        <div class="col-lg-4 d-none d-lg-flex justify-content-end">
            <div class="dash-hero-emblem">
                <img src="<?= e(Profile::logoUrl()) ?>" alt="Logo <?= e(Profile::name()) ?>"
                     class="<?= e(Profile::logoOnDarkClass()) ?>">
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Identitas &amp; Kontak</h2>
                    <div class="card-subtitle">Data ini dipakai sebagai kop aplikasi dan dokumen cetak.</div>
                </div>
                <span class="badge bg-teal-soft text-teal">
                    <i class="bi bi-person-vcard me-1"></i>Profil
                </span>
            </div>
            <div class="card-body">
                <dl class="detail-list">
                    <div class="detail-row">
                        <dt>Nama bimbel</dt>
                        <dd class="fw-semibold"><?= e(Profile::name()) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Tagline</dt>
                        <dd>
                            <?php if (Profile::tagline() !== ''): ?>
                                <?= e(Profile::tagline()) ?>
                            <?php else: ?>
                                <span class="text-secondary">Belum diisi</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="detail-row">
                        <dt>Deskripsi</dt>
                        <dd>
                            <?php if ($description !== ''): ?>
                                <?= nl2br(e($description)) ?>
                            <?php else: ?>
                                <span class="text-secondary">Belum diisi</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="detail-row">
                        <dt>Alamat</dt>
                        <dd>
                            <?php if ($address !== ''): ?>
                                <?= nl2br(e($address)) ?>
                            <?php else: ?>
                                <span class="text-secondary">Belum diisi</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="detail-row">
                        <dt>Telepon</dt>
                        <dd>
                            <?php if ($phone !== ''): ?>
                                <a href="tel:<?= e(normalize_phone($phone)) ?>" class="text-decoration-none">
                                    <?= e(phone_display($phone)) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-secondary">Belum diisi</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="detail-row">
                        <dt>Email</dt>
                        <dd>
                            <?php if ($email !== ''): ?>
                                <a href="mailto:<?= e($email) ?>" class="text-decoration-none"><?= e($email) ?></a>
                            <?php else: ?>
                                <span class="text-secondary">Belum diisi</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="detail-row">
                        <dt>Situs web</dt>
                        <dd>
                            <?php if ($website !== ''): ?>
                                <a href="<?= e($website) ?>" target="_blank" rel="noopener noreferrer"
                                   class="text-decoration-none">
                                    <?= e($websiteLabel) ?>
                                    <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-secondary">Belum diisi</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>
            </div>
            <div class="card-footer bg-white border-top-0 pt-0">
                <a href="<?= e(url('admin/profil/edit.php')) ?>" class="btn btn-success">
                    <i class="bi bi-pencil-square me-1"></i>Edit Identitas
                </a>
            </div>
        </div>

        <div class="master-note">
            <i class="bi bi-info-circle"></i>
            <span>
                Perubahan identitas dan logo langsung dipakai pada sidebar, navbar, halaman login,
                dashboard, invoice, dan nota pembayaran. Dokumen yang sudah tercetak tidak berubah.
            </span>
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
                    <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Kustom</span>
                <?php else: ?>
                    <span class="badge text-bg-secondary"><i class="bi bi-image me-1"></i>Bawaan</span>
                <?php endif; ?>
            </div>
            <div class="card-body text-center">
                <img src="<?= e(Profile::logoUrl()) ?>" alt="Logo <?= e(Profile::name()) ?>"
                     class="profile-logo-preview mb-3 <?= e(Profile::logoOnLightClass()) ?>">
                <div class="small text-secondary mb-3">
                    <?php if ($hasCustomLogo): ?>
                        <span class="d-block font-monospace text-truncate" title="<?= e($logoPath) ?>">
                            uploads/<?= e($logoPath) ?>
                        </span>
                    <?php else: ?>
                        Menggunakan logo bawaan aplikasi.
                    <?php endif; ?>
                    <?php if ($lastUpdated !== null): ?>
                        <span class="d-block mt-1">Diperbarui <?= e(tanggal_waktu_id($lastUpdated)) ?> WIB</span>
                    <?php endif; ?>
                </div>

                <div class="d-grid gap-2">
                    <a href="<?= e(url('admin/profil/edit.php')) ?>" class="btn btn-outline-success">
                        <i class="bi bi-upload me-1"></i>Ganti Logo
                    </a>
                    <?php if ($hasCustomLogo): ?>
                        <form method="post" action="<?= e(url('admin/profil/proses.php')) ?>"
                              data-confirm="Logo kustom akan dihapus dan aplikasi kembali memakai logo bawaan. Lanjutkan?"
                              data-confirm-title="Reset logo bimbel"
                              data-confirm-button="Ya, reset logo"
                              data-confirm-icon="warning"
                              data-confirm-color="#f59e0b">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reset_logo">
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset ke Logo Bawaan
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Panduan Logo</h2>
                    <div class="card-subtitle">Agar logo tampil tajam dan tidak terpotong.</div>
                </div>
            </div>
            <div class="card-body">
                <ul class="list-unstyled small mb-0 d-grid gap-2">
                    <li><i class="bi bi-check2-circle text-success me-2"></i>Gunakan gambar persegi (disarankan 512 × 512 px).</li>
                    <li><i class="bi bi-check2-circle text-success me-2"></i>Latar transparan (PNG/WEBP) lebih fleksibel.</li>
                    <li><i class="bi bi-check2-circle text-success me-2"></i>Ukuran file maksimal <?= e(Upload::maxSizeLabel()) ?>.</li>
                    <li><i class="bi bi-shield-check text-success me-2"></i>Nama file dibuat acak dan disimpan di luar eksekusi PHP.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
