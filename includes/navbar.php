<?php
declare(strict_types=1);

$currentUser = $user ?? Auth::user();
if ($currentUser === null) {
    return;
}
$roleClass = $currentUser['role'] === 'admin' ? 'role-admin' : 'role-petugas';
?>
<nav class="app-header navbar navbar-expand bg-white">
    <div class="container-fluid">
        <ul class="navbar-nav" role="list">
            <li class="nav-item">
                <a class="nav-link nav-toggle-sidebar" data-lte-toggle="sidebar" href="#" role="button"
                   aria-label="Buka atau tutup sidebar">
                    <i class="bi bi-list fs-5"></i>
                </a>
            </li>
            <?php if (Profile::tagline() !== ''): ?>
                <li class="nav-item d-none d-md-flex align-items-center">
                    <span class="navbar-context">
                        <span class="navbar-context-dot"></span>
                        <?= e(Profile::tagline()) ?>
                    </span>
                </li>
            <?php endif; ?>
        </ul>

        <ul class="navbar-nav ms-auto align-items-center gap-1" role="list">
            <li class="nav-item d-none d-lg-block">
                <span class="navbar-date">
                    <i class="bi bi-calendar3 me-2"></i><?= e(tanggal_id(date('Y-m-d'), true)) ?>
                </span>
            </li>

            <li class="nav-item d-none d-sm-block">
                <a class="nav-link nav-icon-btn" href="#" data-lte-toggle="fullscreen"
                   aria-label="Tampilkan layar penuh" title="Layar penuh">
                    <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                    <i data-lte-icon="minimize" class="bi bi-fullscreen-exit d-none"></i>
                </a>
            </li>

            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link user-trigger dropdown-toggle" data-bs-toggle="dropdown"
                   aria-expanded="false">
                    <span class="avatar-initials <?= e($roleClass) ?>"><?= e(initials((string) $currentUser['name'])) ?></span>
                    <span class="user-meta d-none d-md-inline-flex">
                        <span class="user-name"><?= e($currentUser['name']) ?></span>
                        <span class="user-role"><?= e(role_label((string) $currentUser['role'])) ?></span>
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-end user-dropdown shadow">
                    <div class="user-dropdown-head">
                        <span class="avatar-initials avatar-lg <?= e($roleClass) ?>"><?= e(initials((string) $currentUser['name'])) ?></span>
                        <div>
                            <div class="fw-semibold"><?= e($currentUser['name']) ?></div>
                            <div class="text-secondary small">@<?= e($currentUser['username']) ?></div>
                            <span class="badge <?= $currentUser['role'] === 'admin' ? 'text-bg-warning' : 'text-bg-info' ?> mt-1">
                                <?= e(role_label((string) $currentUser['role'])) ?>
                            </span>
                        </div>
                    </div>
                    <div class="user-dropdown-meta">
                        <i class="bi bi-clock-history me-2"></i>
                        Login terakhir: <?= e(tanggal_waktu_id($currentUser['last_login_at'] ?? null)) ?>
                    </div>
                    <div class="dropdown-divider"></div>
                    <form method="post" action="<?= e(url('logout.php')) ?>" class="px-2 pb-1">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-box-arrow-right me-1"></i>Keluar
                        </button>
                    </form>
                </div>
            </li>
        </ul>
    </div>
</nav>
