<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$q = get_string('q');
$status = get_string('status');
$status = in_array($status, StudentPackage::statusOptions(), true) ? $status : '';
$groupId = get_string('group_id');
$groupId = ctype_digit($groupId) ? $groupId : '';

$filters = ['q' => $q, 'status' => $status, 'group_id' => $groupId];
$packages = StudentPackage::all($filters);
$groups = Group::all();
$stats = StudentPackage::stats();
$hasFilter = $q !== '' || $status !== '' || $groupId !== '';
$isAdmin = is_admin();

$pageTitle = 'Paket Belajar';
$pageSubtitle = 'Satu paket berisi 8 pertemuan aktual dari seluruh mata pelajaran dalam kelompok.';
$breadcrumbs = [['label' => 'Paket Belajar']];
$activeMenu = 'paket';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-teal">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Paket Aktif</div>
                    <div class="stat-value"><?= e(number_format($stats['aktif'], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-play-circle"></i>Pertemuan sedang berjalan</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-gold">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Paket Selesai</div>
                    <div class="stat-value"><?= e(number_format($stats['selesai'], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-check2-all"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-flag"></i>Mencapai 8/8 pertemuan</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-green">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total Paket</div>
                    <div class="stat-value"><?= e(number_format($stats['total'], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-collection"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-clock-history"></i>Seluruh riwayat paket</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-navy">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Paket Dibatalkan</div>
                    <div class="stat-value"><?= e(number_format($stats['dibatalkan'], 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-journal-text"></i>Tersimpan sebagai riwayat</div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/paket/index.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-4 col-md-6">
                <label for="q" class="form-label">Cari siswa / kelompok</label>
                <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>"
                       placeholder="Nama siswa, kode, atau kelompok">
            </div>
            <div class="col-xl-3 col-md-6">
                <label for="group_id" class="form-label">Kelompok</label>
                <select class="form-select" id="group_id" name="group_id">
                    <option value="">Semua kelompok</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>" <?= $groupId === (string) $group['id'] ? 'selected' : '' ?>>
                            <?= e($group['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-3 col-md-6">
                <label for="status" class="form-label">Status paket</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua status</option>
                    <?php foreach (StudentPackage::statusOptions() as $option): ?>
                        <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>>
                            <?= e(package_status_label($option)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/paket/index.php')) ?>" class="btn btn-light" title="Reset filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Daftar Paket Belajar</h2>
            <div class="card-subtitle">
                <?= e(number_format(count($packages), 0, ',', '.')) ?> paket
                <?= $hasFilter ? ' &middot; sesuai filter' : '' ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e(url('admin/laporan/paket.php')) ?>" class="btn btn-light">
                <i class="bi bi-file-earmark-text me-1"></i>Laporan
            </a>
            <a href="<?= e(url('admin/paket/tambah.php?mode=group')) ?>" class="btn btn-outline-primary">
                <i class="bi bi-people me-1"></i>Per Group
            </a>
            <a href="<?= e(url('admin/paket/tambah.php')) ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Buat Paket
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($packages === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-box-seam"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada paket yang sesuai filter' : 'Belum ada paket belajar' ?>
                </div>
                <p class="empty-state-text">
                    <?= $hasFilter
                        ? 'Coba ubah atau reset filter untuk melihat paket lainnya.'
                        : 'Buat paket pertama untuk siswa yang sudah memiliki keanggotaan kelompok aktif.' ?>
                </p>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/paket/index.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                    </a>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <a href="<?= e(url('admin/paket/tambah.php?mode=group')) ?>" class="btn btn-outline-primary">
                            <i class="bi bi-people me-1"></i>Buat per Group
                        </a>
                        <a href="<?= e(url('admin/paket/tambah.php')) ?>" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i>Buat Paket
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width:40px;">No.</th>
                        <th>Siswa</th>
                        <th>Kelompok</th>
                        <th class="no-sort">Paket</th>
                        <th>Mulai</th>
                        <th style="min-width:140px;">Progres</th>
                        <th class="text-center">Sisa</th>
                        <th>Tarif Paket</th>
                        <th class="no-sort">Status</th>
                        <th class="no-sort">Tagihan</th>
                        <th class="no-sort" style="width:120px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($packages as $index => $package): ?>
                        <?php
                        $target = (int) $package['target_meetings'];
                        $completed = (int) $package['completed_meetings'];
                        $remaining = max(0, $target - $completed);
                        $detailUrl = url('admin/paket/detail.php?id=' . (int) $package['id']);
                        ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td>
                                <a href="<?= e($detailUrl) ?>" class="cell-title link-dark text-decoration-none">
                                    <?= e($package['student_name']) ?>
                                </a>
                                <span class="cell-sub font-monospace"><?= e($package['student_code']) ?></span>
                            </td>
                            <td>
                                <span class="cell-title"><?= e($package['group_name']) ?></span>
                                <span class="cell-sub"><?= e(package_type_label((string) $package['group_type'])) ?>
                                    &middot; <?= e((string) (int) $package['member_count']) ?> siswa</span>
                            </td>
                            <td class="text-nowrap">Paket <?= e((string) (int) $package['package_number']) ?></td>
                            <td class="text-nowrap"><?= e(tanggal_short_id((string) $package['start_date'])) ?></td>
                            <td><?= package_progress($completed, $target) ?></td>
                            <td class="text-center"><?= e((string) $remaining) ?></td>
                            <td class="text-nowrap"><?= e(rupiah((float) $package['total_amount'])) ?></td>
                            <td><?= package_status_badge((string) $package['status']) ?></td>
                            <td>
                                <?php if ($package['active_invoice_id'] !== null): ?>
                                    <a href="<?= e(url('admin/tagihan/detail.php?id=' . (int) $package['active_invoice_id'])) ?>"
                                       class="invoice-number text-decoration-none">
                                        <?= e($package['active_invoice_number']) ?>
                                    </a>
                                    <div class="mt-1"><?= invoice_status_badge((string) $package['active_invoice_status']) ?></div>
                                <?php elseif ((string) $package['status'] !== StudentPackage::STATUS_DIBATALKAN): ?>
                                    <span class="badge text-bg-secondary">Belum ada tagihan</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e($detailUrl) ?>" class="btn btn-info btn-sm" title="Detail paket">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($package['active_invoice_id'] === null
                                        && (string) $package['status'] !== StudentPackage::STATUS_DIBATALKAN): ?>
                                        <form method="post" action="<?= e(url('admin/paket/proses.php')) ?>"
                                              data-confirm="Tagihan paket ini akan diterbitkan berdasarkan tarif yang berlaku saat ini."
                                              data-confirm-title="Terbitkan tagihan"
                                              data-confirm-button="Ya, terbitkan"
                                              data-confirm-icon="question" data-submit-once>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="issue">
                                            <input type="hidden" name="id" value="<?= (int) $package['id'] ?>">
                                            <button type="submit" class="btn btn-success btn-sm" title="Terbitkan tagihan">
                                                <i class="bi bi-receipt"></i>
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
    <span>Paket berikutnya dibuat otomatis dan langsung aktif saat paket mencapai 8 pertemuan, tanpa menunggu
        pelunasan tagihan. Sisa pertemuan paket lama tidak dipindahkan bila siswa berpindah kelompok.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
