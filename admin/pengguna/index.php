<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_admin();

$role = get_string('role');
$role = in_array($role, User::ROLES, true) ? $role : '';
$status = get_string('status');
$status = in_array($status, ['1', '0'], true) ? $status : '';

$filters = ['role' => $role, 'status' => $status];
$users = User::all($filters);
$hasFilter = $role !== '' || $status !== '';

$totalAll = User::countAll();
$totalActive = User::countActive();
$totalInactive = User::countInactive();

$currentUserId = (int) (current_user()['id'] ?? 0);

$pageTitle = 'Manajemen Pengguna';
$pageSubtitle = 'Kelola akun admin dan petugas yang dapat masuk ke aplikasi.';
$breadcrumbs = [['label' => 'Manajemen Pengguna']];
$activeMenu = 'pengguna';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/pengguna/index.php')) ?>" class="row g-2 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label for="role" class="form-label">Peran</label>
                <select class="form-select" id="role" name="role">
                    <option value="">Semua peran</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    <option value="petugas" <?= $role === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                </select>
            </div>
            <div class="col-lg-4 col-md-6">
                <label for="status" class="form-label">Status akun</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua status</option>
                    <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Aktif</option>
                    <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-lg-4 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/pengguna/index.php')) ?>" class="btn btn-light"
                       title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Daftar Pengguna</h2>
            <div class="card-subtitle">
                <?= e(number_format($totalAll, 0, ',', '.')) ?> pengguna terdaftar
                &middot; <?= e(number_format($totalActive, 0, ',', '.')) ?> aktif
                &middot; <?= e(number_format($totalInactive, 0, ',', '.')) ?> nonaktif
                <?php if ($hasFilter): ?>
                    &middot; <?= e(number_format(count($users), 0, ',', '.')) ?> sesuai filter
                <?php endif; ?>
            </div>
        </div>
        <a href="<?= e(url('admin/pengguna/tambah.php')) ?>" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>Tambah Pengguna
        </a>
    </div>

    <div class="card-body p-0">
        <?php if ($users === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-person-badge"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada pengguna yang sesuai filter' : 'Belum ada pengguna' ?>
                </div>
                <p class="empty-state-text">
                    <?= $hasFilter
                        ? 'Coba ubah atau reset filter untuk melihat data lainnya.'
                        : 'Tambahkan pengguna pertama agar bisa masuk ke aplikasi.' ?>
                </p>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/pengguna/index.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                    </a>
                <?php else: ?>
                    <a href="<?= e(url('admin/pengguna/tambah.php')) ?>" class="btn btn-primary">
                        <i class="bi bi-person-plus me-1"></i>Tambah Pengguna
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width: 54px;">No.</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th style="width: 120px;">Peran</th>
                        <th>Login Terakhir</th>
                        <th class="no-sort" style="width: 110px;">Status</th>
                        <th class="no-sort" style="width: 150px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $index => $user): ?>
                        <?php $isSelf = (int) $user['id'] === $currentUserId; ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td>
                                <span class="cell-title"><?= e($user['name']) ?></span>
                                <?php if ($isSelf): ?>
                                    <span class="badge text-bg-light border ms-1">Anda</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="font-monospace">@<?= e($user['username']) ?></span></td>
                            <td>
                                <?php if (!empty($user['email'])): ?>
                                    <a href="mailto:<?= e($user['email']) ?>" class="text-truncate d-inline-block" style="max-width: 190px;">
                                        <?= e($user['email']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $user['role'] === 'admin' ? 'text-bg-warning' : 'text-bg-info' ?>">
                                    <?= e(role_label((string) $user['role'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($user['last_login_at'])): ?>
                                    <span class="text-nowrap"><?= e(tanggal_waktu_id($user['last_login_at'])) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small"><i class="bi bi-dash-circle me-1"></i>Belum pernah</span>
                                <?php endif; ?>
                            </td>
                            <td><?= active_badge((int) $user['is_active'] === 1) ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e(url('admin/pengguna/edit.php?id=' . (int) $user['id'])) ?>"
                                       class="btn btn-warning btn-sm" title="Edit"
                                       aria-label="Edit <?= e($user['name']) ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <a href="<?= e(url('admin/pengguna/password.php?id=' . (int) $user['id'])) ?>"
                                       class="btn btn-secondary btn-sm" title="Atur ulang password"
                                       aria-label="Atur ulang password <?= e($user['name']) ?>">
                                        <i class="bi bi-key"></i>
                                    </a>
                                    <?php if ($isSelf): ?>
                                        <button type="button" class="btn btn-light btn-sm" disabled
                                                title="Akun sendiri tidak dapat dinonaktifkan">
                                            <i class="bi bi-person-lock"></i>
                                        </button>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(url('admin/pengguna/proses.php')) ?>"
                                              class="d-inline"
                                              data-confirm="<?= (int) $user['is_active'] === 1
                                                  ? 'Pengguna akan dinonaktifkan dan tidak dapat login sampai diaktifkan kembali.'
                                                  : 'Pengguna akan diaktifkan kembali dan dapat login.' ?>"
                                              data-confirm-title="Ubah status pengguna"
                                              data-confirm-button="<?= (int) $user['is_active'] === 1 ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                                              data-confirm-icon="warning"
                                              data-submit-once>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-sm <?= (int) $user['is_active'] === 1 ? 'btn-danger' : 'btn-success' ?>"
                                                    title="<?= (int) $user['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>"
                                                    aria-label="<?= (int) $user['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> <?= e($user['name']) ?>">
                                                <i class="bi <?= (int) $user['is_active'] === 1 ? 'bi-person-dash' : 'bi-person-check' ?>"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="master-note">
    <i class="bi bi-shield-lock"></i>
    <span>
        Akun pengguna tidak dihapus permanen, hanya dinonaktifkan agar riwayat aktivitas tetap utuh.
        Minimal satu administrator aktif harus selalu tersedia, dan akun sendiri tidak dapat dinonaktifkan.
    </span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
