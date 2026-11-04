<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$status = get_string('status');
$status = in_array($status, ['1', '0'], true) ? $status : '';
$filters = ['status' => $status];

$subjects = Subject::all($filters);
$hasFilter = $status !== '';
$canManage = is_admin();

$totalAll = Subject::countAll();
$totalActive = Subject::countActive();
$totalInactive = Subject::countInactive();

$pageTitle = 'Mata Pelajaran';
$pageSubtitle = 'Kelola daftar mata pelajaran yang digunakan pada group belajar.';
$breadcrumbs = [['label' => 'Mata Pelajaran']];
$activeMenu = 'mata-pelajaran';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/mata-pelajaran/index.php')) ?>" class="row g-2 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label for="status" class="form-label">Status mata pelajaran</label>
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
                    <a href="<?= e(url('admin/mata-pelajaran/index.php')) ?>" class="btn btn-light"
                       title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Daftar Mata Pelajaran</h2>
            <div class="card-subtitle">
                <?= e(number_format($totalAll, 0, ',', '.')) ?> mapel terdaftar
                &middot; <?= e(number_format($totalActive, 0, ',', '.')) ?> aktif
                &middot; <?= e(number_format($totalInactive, 0, ',', '.')) ?> nonaktif
                <?php if ($hasFilter): ?>
                    &middot; <?= e(number_format(count($subjects), 0, ',', '.')) ?> sesuai filter
                <?php endif; ?>
            </div>
        </div>
        <?php if ($canManage): ?>
            <a href="<?= e(url('admin/mata-pelajaran/tambah.php')) ?>" class="btn btn-primary">
                <i class="bi bi-journal-plus me-1"></i>Tambah Mata Pelajaran
            </a>
        <?php endif; ?>
    </div>

    <div class="card-body p-0">
        <?php if ($subjects === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-journal-bookmark"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada mata pelajaran yang sesuai filter' : 'Belum ada data mata pelajaran' ?>
                </div>
                <p class="empty-state-text">
                    <?= $hasFilter
                        ? 'Coba ubah atau reset filter untuk melihat data lainnya.'
                        : ($canManage
                            ? 'Tambahkan mata pelajaran pertama sebelum membuat group belajar.'
                            : 'Data mata pelajaran belum ditambahkan oleh administrator.') ?>
                </p>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/mata-pelajaran/index.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                    </a>
                <?php elseif ($canManage): ?>
                    <a href="<?= e(url('admin/mata-pelajaran/tambah.php')) ?>" class="btn btn-primary">
                        <i class="bi bi-journal-plus me-1"></i>Tambah Mata Pelajaran
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width: 54px;">No.</th>
                        <th style="width: 130px;">Kode</th>
                        <th>Nama Mata Pelajaran</th>
                        <th>Keterangan</th>
                        <th class="no-sort" style="width: 100px;">Dipakai</th>
                        <th class="no-sort" style="width: 110px;">Status</th>
                        <th class="no-sort" style="width: <?= $canManage ? '132px' : '64px' ?>;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($subjects as $index => $subject): ?>
                        <?php $detailUrl = url('admin/mata-pelajaran/detail.php?id=' . (int) $subject['id']); ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td><span class="cell-title font-monospace"><?= e($subject['code']) ?></span></td>
                            <td>
                                <a href="<?= e($detailUrl) ?>" class="cell-title link-dark text-decoration-none">
                                    <?= e($subject['name']) ?>
                                </a>
                            </td>
                            <td>
                                <?php if (!empty($subject['description'])): ?>
                                    <span class="text-secondary small">
                                        <?= e(mb_strimwidth((string) $subject['description'], 0, 90, '…')) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $used = (int) $subject['group_count']; ?>
                                <span class="badge <?= $used > 0 ? 'text-bg-light border' : 'text-bg-light' ?>">
                                    <i class="bi bi-people me-1"></i><?= e((string) $used) ?> group
                                </span>
                            </td>
                            <td><?= active_badge((int) $subject['is_active'] === 1) ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e($detailUrl) ?>" class="btn btn-info btn-sm"
                                       title="Detail" aria-label="Detail <?= e($subject['name']) ?>">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($canManage): ?>
                                        <a href="<?= e(url('admin/mata-pelajaran/edit.php?id=' . (int) $subject['id'])) ?>"
                                           class="btn btn-warning btn-sm" title="Edit"
                                           aria-label="Edit <?= e($subject['name']) ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form method="post" action="<?= e(url('admin/mata-pelajaran/proses.php')) ?>"
                                              class="d-inline"
                                              data-confirm="<?= (int) $subject['is_active'] === 1
                                                  ? 'Mata pelajaran akan dinonaktifkan dan tidak dapat dipilih untuk group baru.'
                                                  : 'Mata pelajaran akan diaktifkan kembali dan dapat dipilih untuk group.' ?>"
                                              data-confirm-title="Ubah status mata pelajaran"
                                              data-confirm-button="<?= (int) $subject['is_active'] === 1 ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                                              data-confirm-icon="warning"
                                              data-submit-once>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $subject['id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-sm <?= (int) $subject['is_active'] === 1 ? 'btn-danger' : 'btn-success' ?>"
                                                    title="<?= (int) $subject['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>"
                                                    aria-label="<?= (int) $subject['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> <?= e($subject['name']) ?>">
                                                <i class="bi <?= (int) $subject['is_active'] === 1 ? 'bi-journal-minus' : 'bi-journal-check' ?>"></i>
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
        Mata pelajaran yang sudah dipakai group tidak dihapus permanen.
        <?php if (!$canManage): ?>
            Halaman ini bersifat baca-saja untuk petugas; perubahan data dilakukan administrator.
        <?php endif; ?>
    </span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
