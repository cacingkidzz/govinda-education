<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$dateFrom = get_string('date_from');
$dateTo = get_string('date_to');
$fromGroupId = get_string('from_group_id');
$toGroupId = get_string('to_group_id');
$keyword = mb_substr(get_string('q'), 0, 80);

$dateFrom = valid_date($dateFrom) ? $dateFrom : '';
$dateTo = valid_date($dateTo) ? $dateTo : '';
$fromGroupId = ctype_digit($fromGroupId) ? $fromGroupId : '';
$toGroupId = ctype_digit($toGroupId) ? $toGroupId : '';

if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$filters = [
    'date_from'     => $dateFrom,
    'date_to'       => $dateTo,
    'from_group_id' => $fromGroupId,
    'to_group_id'   => $toGroupId,
    'q'             => $keyword,
];

$transfers = Membership::transfers($filters);
$groups = Group::all();
$hasFilter = $dateFrom !== '' || $dateTo !== '' || $fromGroupId !== '' || $toGroupId !== '' || $keyword !== '';

$pageTitle = 'Riwayat Perpindahan Group';
$pageSubtitle = 'Seluruh perpindahan siswa antar-group yang tercatat di sistem.';
$breadcrumbs = [
    ['label' => 'Group Belajar', 'url' => url('admin/group/index.php')],
    ['label' => 'Riwayat Perpindahan'],
];
$activeMenu = 'riwayat-group';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/group/riwayat.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-4">
                <label for="date_from" class="form-label">Dari tanggal</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="date_to" class="form-label">Sampai tanggal</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="from_group_id" class="form-label">Group asal</label>
                <select class="form-select" id="from_group_id" name="from_group_id">
                    <option value="">Semua group</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>" <?= $fromGroupId === (string) $group['id'] ? 'selected' : '' ?>>
                            <?= e($group['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="to_group_id" class="form-label">Group tujuan</label>
                <select class="form-select" id="to_group_id" name="to_group_id">
                    <option value="">Semua group</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>" <?= $toGroupId === (string) $group['id'] ? 'selected' : '' ?>>
                            <?= e($group['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="q" class="form-label">Cari siswa</label>
                <input type="search" class="form-control" id="q" name="q" value="<?= e($keyword) ?>"
                       placeholder="Nama atau kode siswa">
            </div>
            <div class="col-xl-2 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/group/riwayat.php')) ?>" class="btn btn-light"
                       title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Perpindahan Siswa</h2>
            <div class="card-subtitle">
                <?= e(number_format(count($transfers), 0, ',', '.')) ?> perpindahan<?= $hasFilter ? ' sesuai filter' : '' ?>
                &middot; riwayat tidak dapat dihapus
            </div>
        </div>
        <span class="badge bg-teal-soft text-teal">
            <i class="bi bi-shield-lock me-1"></i>Audit trail
        </span>
    </div>
    <div class="card-body p-0">
        <?php if ($transfers === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-arrow-left-right"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada perpindahan yang sesuai filter' : 'Belum ada perpindahan siswa' ?>
                </div>
                <p class="empty-state-text">
                    <?= $hasFilter
                        ? 'Coba ubah atau reset filter untuk melihat riwayat lainnya.'
                        : 'Riwayat akan tercatat otomatis saat siswa dipindahkan ke group lain.' ?>
                </p>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/group/riwayat.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width: 40px;">No.</th>
                        <th>Tanggal</th>
                        <th>Kode Siswa</th>
                        <th>Nama Siswa</th>
                        <th>Group Asal</th>
                        <th>Group Tujuan</th>
                        <th>Alasan</th>
                        <th>Petugas</th>
                        <th class="no-sort" style="width: 80px;">Detail</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transfers as $index => $transfer): ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td class="text-nowrap"><?= e(tanggal_id((string) $transfer['transfer_date'])) ?></td>
                            <td><span class="cell-title font-monospace"><?= e($transfer['student_code']) ?></span></td>
                            <td>
                                <span class="cell-title"><?= e($transfer['student_name']) ?></span>
                            </td>
                            <td>
                                <span class="cell-title"><?= e($transfer['from_name'] ?: '-') ?></span>
                                <span class="cell-sub font-monospace"><?= e($transfer['from_code'] ?: '-') ?></span>
                            </td>
                            <td>
                                <span class="cell-title"><?= e($transfer['to_name'] ?: '-') ?></span>
                                <span class="cell-sub font-monospace"><?= e($transfer['to_code'] ?: '-') ?></span>
                            </td>
                            <td><span class="cell-sub d-block" style="max-width: 280px;"><?= e($transfer['reason'] ?: '-') ?></span></td>
                            <td>
                                <span class="cell-title"><?= e($transfer['changed_by_name'] ?: '-') ?></span>
                                <span class="cell-sub"><?= e($transfer['changed_by_username'] ?: '') ?></span>
                            </td>
                            <td>
                                <a href="<?= e(url('admin/siswa/detail.php?id=' . (int) $transfer['student_id'] . '&tab=riwayat')) ?>"
                                   class="btn btn-info btn-sm" title="Detail siswa" aria-label="Detail siswa">
                                    <i class="bi bi-eye"></i>
                                </a>
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
    <span>Perpindahan menutup keanggotaan group lama dan membuat keanggotaan baru dalam satu transaksi.
        Invoice yang sudah diterbitkan tidak diubah; tarif group baru berlaku pada periode tagihan berikutnya.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
