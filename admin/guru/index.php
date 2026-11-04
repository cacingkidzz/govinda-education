<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$status = get_string('status');
$status = in_array($status, ['1', '0'], true) ? $status : '';
$filters = ['status' => $status];

$teachers = Teacher::all($filters);
$hasFilter = $status !== '';
$canManage = is_admin();

$totalAll = Teacher::countAll();
$totalActive = Teacher::countActive();
$totalInactive = Teacher::countInactive();

$pageTitle = 'Data Guru';
$pageSubtitle = 'Kelola profil guru, kontak, pendidikan, dan mata pelajaran yang diampu.';
$breadcrumbs = [['label' => 'Data Guru']];
$activeMenu = 'guru';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/guru/index.php')) ?>" class="row g-2 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label for="status" class="form-label">Status guru</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua status</option>
                    <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Aktif</option>
                    <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/guru/index.php')) ?>" class="btn btn-light"
                       title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Daftar Guru</h2>
            <div class="card-subtitle">
                <?= e(number_format($totalAll, 0, ',', '.')) ?> guru terdaftar
                &middot; <?= e(number_format($totalActive, 0, ',', '.')) ?> aktif
                &middot; <?= e(number_format($totalInactive, 0, ',', '.')) ?> nonaktif
                <?php if ($hasFilter): ?>
                    &middot; <?= e(number_format(count($teachers), 0, ',', '.')) ?> sesuai filter
                <?php endif; ?>
            </div>
        </div>
        <?php if ($canManage): ?>
            <a href="<?= e(url('admin/guru/tambah.php')) ?>" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i>Tambah Guru
            </a>
        <?php endif; ?>
    </div>

    <div class="card-body p-0">
        <?php if ($teachers === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-person-video3"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada guru yang sesuai filter' : 'Belum ada data guru' ?>
                </div>
                <p class="empty-state-text">
                    <?= $hasFilter
                        ? 'Coba ubah atau reset filter untuk melihat data lainnya.'
                        : ($canManage
                            ? 'Tambahkan guru pertama untuk mulai mengelola data pengajar.'
                            : 'Data guru belum ditambahkan oleh administrator.') ?>
                </p>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/guru/index.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                    </a>
                <?php elseif ($canManage): ?>
                    <a href="<?= e(url('admin/guru/tambah.php')) ?>" class="btn btn-primary">
                        <i class="bi bi-person-plus me-1"></i>Tambah Guru
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width: 54px;">No.</th>
                        <th class="no-sort" style="width: 64px;">Foto</th>
                        <th>Kode</th>
                        <th>Nama Guru</th>
                        <th style="width: 90px;">JK</th>
                        <th>No. HP</th>
                        <th>Email</th>
                        <th>Pendidikan</th>
                        <th>Mata Pelajaran</th>
                        <th class="no-sort" style="width: 110px;">Status</th>
                        <th class="no-sort" style="width: <?= $canManage ? '132px' : '64px' ?>;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($teachers as $index => $teacher): ?>
                        <?php $detailUrl = url('admin/guru/detail.php?id=' . (int) $teacher['id']); ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td>
                                <a href="<?= e($detailUrl) ?>" class="photo-link">
                                    <?= photo_tag($teacher['photo'] ?? null, (string) $teacher['name']) ?>
                                </a>
                            </td>
                            <td><span class="cell-title font-monospace"><?= e($teacher['code']) ?></span></td>
                            <td>
                                <a href="<?= e($detailUrl) ?>" class="cell-title link-dark text-decoration-none">
                                    <?= e($teacher['name']) ?>
                                </a>
                            </td>
                            <td><?= e($teacher['gender'] === 'L' ? 'Laki-laki' : 'Perempuan') ?></td>
                            <td><span class="text-nowrap"><?= e(phone_display($teacher['phone'] ?? null)) ?></span></td>
                            <td>
                                <?php if (!empty($teacher['email'])): ?>
                                    <a href="mailto:<?= e($teacher['email']) ?>" class="text-truncate d-inline-block" style="max-width: 190px;">
                                        <?= e($teacher['email']) ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= e($teacher['education'] ?: '-') ?></td>
                            <td>
                                <?php if (!empty($teacher['subject_names'])): ?>
                                    <span class="cell-title"><?= e($teacher['subject_names']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small"><i class="bi bi-dash-circle me-1"></i>Belum diatur</span>
                                <?php endif; ?>
                            </td>
                            <td><?= active_badge((int) $teacher['is_active'] === 1) ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e($detailUrl) ?>" class="btn btn-info btn-sm"
                                       title="Detail" aria-label="Detail <?= e($teacher['name']) ?>">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($canManage): ?>
                                        <a href="<?= e(url('admin/guru/edit.php?id=' . (int) $teacher['id'])) ?>"
                                           class="btn btn-warning btn-sm" title="Edit"
                                           aria-label="Edit <?= e($teacher['name']) ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form method="post" action="<?= e(url('admin/guru/proses.php')) ?>"
                                              class="d-inline"
                                              data-confirm="<?= (int) $teacher['is_active'] === 1
                                                  ? 'Guru akan dinonaktifkan dan tidak dapat dipilih untuk group baru.'
                                                  : 'Guru akan diaktifkan kembali dan dapat dipilih untuk group.' ?>"
                                              data-confirm-title="Ubah status guru"
                                              data-confirm-button="<?= (int) $teacher['is_active'] === 1 ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                                              data-confirm-icon="warning"
                                              data-submit-once>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $teacher['id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-sm <?= (int) $teacher['is_active'] === 1 ? 'btn-danger' : 'btn-success' ?>"
                                                    title="<?= (int) $teacher['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>"
                                                    aria-label="<?= (int) $teacher['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> <?= e($teacher['name']) ?>">
                                                <i class="bi <?= (int) $teacher['is_active'] === 1 ? 'bi-person-dash' : 'bi-person-check' ?>"></i>
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
    <i class="bi bi-info-circle"></i>
    <span>
        Guru dengan riwayat group atau jadwal tidak dihapus permanen.
        <?php if (!$canManage): ?>
            Halaman ini bersifat baca-saja untuk petugas; perubahan data guru dilakukan administrator.
        <?php endif; ?>
    </span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
