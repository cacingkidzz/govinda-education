<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_admin();

$id = get_int('id');
$user = $id > 0 ? User::findById($id) : null;
if ($user === null) {
    abort_404('Data pengguna tidak ditemukan.');
}

$state = pull_form_state();
$errors = $state['errors'];

$pageTitle = 'Atur Ulang Password';
$pageSubtitle = 'Tetapkan password baru untuk akun pengguna.';
$breadcrumbs = [
    ['label' => 'Manajemen Pengguna', 'url' => url('admin/pengguna/index.php')],
    ['label' => 'Atur Ulang Password'],
];
$activeMenu = 'pengguna';

require_once ROOT_PATH . '/includes/header.php';
?>

<form method="post" action="<?= e(url('admin/pengguna/proses.php')) ?>" data-submit-once novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Password Baru</h2>
                        <div class="card-subtitle">Untuk akun @<?= e($user['username']) ?>.</div>
                    </div>
                    <span class="badge bg-teal-soft text-teal">
                        <i class="bi bi-key me-1"></i>Keamanan
                    </span>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors['form'])): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($errors['form']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password baru <span class="text-danger">*</span></label>
                            <input type="password" class="form-control<?= field_invalid($errors, 'password') ?>"
                                   id="password" name="password" minlength="8" maxlength="72" required
                                   autocomplete="new-password">
                            <?= field_error($errors, 'password') ?>
                            <div class="form-text">Minimal 8 karakter, harus mengandung huruf dan angka.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirm" class="form-label">Ulangi password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control<?= field_invalid($errors, 'password_confirm') ?>"
                                   id="password_confirm" name="password_confirm" minlength="8" maxlength="72" required
                                   autocomplete="new-password">
                            <?= field_error($errors, 'password_confirm') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Akun</h2>
                        <div class="card-subtitle">Pastikan akun yang dipilih benar.</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="avatar-initials <?= $user['role'] === 'admin' ? 'role-admin' : 'role-petugas' ?>">
                            <?= e(initials((string) $user['name'])) ?>
                        </span>
                        <div>
                            <div class="fw-semibold"><?= e($user['name']) ?></div>
                            <div class="text-secondary small">@<?= e($user['username']) ?></div>
                        </div>
                    </div>
                    <dl class="row small mb-0">
                        <dt class="col-5 text-secondary fw-normal">Peran</dt>
                        <dd class="col-7 mb-1"><?= e(role_label((string) $user['role'])) ?></dd>
                        <dt class="col-5 text-secondary fw-normal">Status</dt>
                        <dd class="col-7 mb-1"><?= active_badge((int) $user['is_active'] === 1) ?></dd>
                        <dt class="col-5 text-secondary fw-normal">Login terakhir</dt>
                        <dd class="col-7 mb-0"><?= e(tanggal_waktu_id($user['last_login_at'] ?? null)) ?></dd>
                    </dl>
                </div>
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i>Simpan Password
                        </button>
                        <a href="<?= e(url('admin/pengguna/index.php')) ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
