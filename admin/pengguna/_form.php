<?php
declare(strict_types=1);

/** @var string $formMode */
/** @var array<string, mixed> $user */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

$formMode = $formMode ?? 'create';
$user = $user ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$isEdit = $formMode === 'edit';
$v = static fn (string $key, string $default = ''): string => old_value($old, $user, $key, $default);
$roleValue = $v('role', 'petugas');
$statusValue = array_key_exists('is_active', $old)
    ? (string) ((int) $old['is_active'] === 1 ? '1' : '0')
    : (string) ((int) ($user['is_active'] ?? 1) === 1 ? '1' : '0');
$isSelf = $isEdit && (int) ($user['id'] ?? 0) === (int) (current_user()['id'] ?? 0);
?>

<form method="post" action="<?= e(url('admin/pengguna/proses.php')) ?>" data-submit-once novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) ($user['id'] ?? 0) ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Identitas Pengguna</h2>
                        <div class="card-subtitle">Data akun yang dipakai untuk masuk ke aplikasi.</div>
                    </div>
                    <span class="badge bg-teal-soft text-teal">
                        <i class="bi bi-person-badge me-1"></i>Akun
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
                            <label for="name" class="form-label">Nama lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'name') ?>"
                                   id="name" name="name" maxlength="100" value="<?= e($v('name')) ?>" required
                                   autocomplete="name" placeholder="Contoh: Administrator">
                            <?= field_error($errors, 'name') ?>
                        </div>
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">@</span>
                                <input type="text" class="form-control<?= field_invalid($errors, 'username') ?>"
                                       id="username" name="username" maxlength="30" value="<?= e($v('username')) ?>"
                                       required autocomplete="username" autocapitalize="none" spellcheck="false"
                                       placeholder="contoh: petugas2">
                            </div>
                            <?= field_error($errors, 'username') ?>
                            <div class="form-text">Huruf kecil, angka, titik, garis bawah, atau tanda hubung. Minimal 3 karakter.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control<?= field_invalid($errors, 'email') ?>"
                                   id="email" name="email" maxlength="120" value="<?= e($v('email')) ?>"
                                   autocomplete="email" placeholder="opsional@contoh.com">
                            <?= field_error($errors, 'email') ?>
                            <div class="form-text">Opsional, tetapi harus unik bila diisi.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label">Peran <span class="text-danger">*</span></label>
                            <select class="form-select<?= field_invalid($errors, 'role') ?>" id="role" name="role" required>
                                <option value="petugas" <?= $roleValue === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                                <option value="admin" <?= $roleValue === 'admin' ? 'selected' : '' ?>>Administrator</option>
                            </select>
                            <?= field_error($errors, 'role') ?>
                            <div class="form-text">Administrator dapat mengelola pengguna, profil bimbel, dan membatalkan pengeluaran.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="is_active" class="form-label">Status akun <span class="text-danger">*</span></label>
                            <select class="form-select<?= field_invalid($errors, 'is_active') ?>"
                                    id="is_active" name="is_active" required <?= $isSelf ? 'disabled' : '' ?>>
                                <option value="1" <?= $statusValue === '1' ? 'selected' : '' ?>>Aktif</option>
                                <option value="0" <?= $statusValue === '0' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                            <?php if ($isSelf): ?>
                                <input type="hidden" name="is_active" value="1">
                                <div class="form-text">Akun sendiri selalu aktif.</div>
                            <?php else: ?>
                                <div class="form-text">Akun nonaktif tidak dapat login, tetapi datanya tetap tersimpan.</div>
                            <?php endif; ?>
                            <?= field_error($errors, 'is_active') ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$isEdit): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Password Awal</h2>
                            <div class="card-subtitle">Dipakai pengguna untuk login pertama kali.</div>
                        </div>
                        <span class="badge bg-teal-soft text-teal">
                            <i class="bi bi-key me-1"></i>Keamanan
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
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
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Ketentuan Akun</h2>
                        <div class="card-subtitle">Agar akses tetap aman dan terkontrol.</div>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="rule-list">
                        <li>
                            <span class="rule-icon rule-check"><i class="bi bi-person-check"></i></span>
                            <span>Username harus unik dan dipakai untuk login.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-check"><i class="bi bi-key"></i></span>
                            <span>Password disimpan sebagai hash; tidak dapat dilihat kembali.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-lock"><i class="bi bi-shield-lock"></i></span>
                            <span>Akun tidak dihapus permanen, hanya dinonaktifkan.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-lock"><i class="bi bi-person-lock"></i></span>
                            <span>Akun sendiri tidak dapat dinonaktifkan atau diturunkan perannya.</span>
                        </li>
                    </ul>
                    <?php if ($isEdit): ?>
                        <div class="alert alert-info small mb-0">
                            <i class="bi bi-info-circle me-1"></i>Password tidak diubah di halaman ini.
                            Gunakan tombol <i class="bi bi-key"></i> pada daftar pengguna untuk mengatur ulang password.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Pengguna' ?>
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
