<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();

$isValidDate = static function (string $value): bool {
    if ($value === '') {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
};

$q = mb_substr(get_string('q'), 0, 100);
$activity = mb_substr(get_string('activity'), 0, 100);
$userId = get_int('user_id');
$dateFrom = get_string('date_from');
$dateTo = get_string('date_to');
$dateFrom = $isValidDate($dateFrom) ? $dateFrom : '';
$dateTo = $isValidDate($dateTo) ? $dateTo : '';

if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$filters = [
    'q'         => $q,
    'activity'  => $activity,
    'user_id'   => $userId,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
];

$page = max(1, get_int('page', 1));
$result = ActivityLog::paginate($filters, $page, 25);
$page = max(1, min($page, $result['pages']));
$logs = $result['items'];
$total = $result['total'];
$pages = $result['pages'];
$perPage = 25;
$startNumber = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$endNumber = min($total, $page * $perPage);

$users = db()->query('SELECT id, name, username FROM users ORDER BY name ASC')->fetchAll();
$activityOptions = ActivityLog::activityOptions();

$queryFilters = array_filter($filters, static fn ($value) => $value !== '' && $value !== 0);
$hasFilter = $queryFilters !== [];

$pageTitle = 'Log Aktivitas';
$pageSubtitle = 'Jejak audit aktivitas penting pengguna aplikasi.';
$breadcrumbs = [['label' => 'Log Aktivitas']];
$activeMenu = 'log-aktivitas';

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/log-aktivitas/index.php')) ?>" class="row g-2 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label for="q" class="form-label">Pencarian</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>"
                           placeholder="Deskripsi, username, aktivitas…" maxlength="100">
                </div>
            </div>
            <div class="col-lg-2 col-md-6">
                <label for="activity" class="form-label">Aktivitas</label>
                <select class="form-select" id="activity" name="activity">
                    <option value="">Semua aktivitas</option>
                    <?php foreach ($activityOptions as $option): ?>
                        <option value="<?= e($option) ?>" <?= $activity === $option ? 'selected' : '' ?>>
                            <?= e(activity_meta($option)['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label for="user_id" class="form-label">Pengguna</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">Semua pengguna</option>
                    <?php foreach ($users as $optionUser): ?>
                        <option value="<?= (int) $optionUser['id'] ?>" <?= $userId === (int) $optionUser['id'] ? 'selected' : '' ?>>
                            <?= e($optionUser['name']) ?> (@<?= e($optionUser['username']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label for="date_from" class="form-label">Dari tanggal</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-lg-2 col-md-6">
                <label for="date_to" class="form-label">Sampai tanggal</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-lg-1 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1" title="Terapkan filter">
                    <i class="bi bi-funnel"></i>
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/log-aktivitas/index.php')) ?>" class="btn btn-light"
                       title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Riwayat Aktivitas</h2>
            <div class="card-subtitle">
                Menampilkan <?= e(number_format($startNumber, 0, ',', '.')) ?>–<?= e(number_format($endNumber, 0, ',', '.')) ?>
                dari <?= e(number_format($total, 0, ',', '.')) ?> log.
            </div>
        </div>
        <span class="badge bg-teal-soft text-teal">
            <i class="bi bi-shield-check me-1"></i>Audit trail
        </span>
    </div>

    <div class="card-body p-0">
        <?php if ($logs === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-journal-text"></i></div>
                <div class="empty-state-title">Tidak ada log yang sesuai</div>
                <p class="empty-state-text">
                    <?= $hasFilter ? 'Coba ubah atau reset filter pencarian.' : 'Aktivitas pengguna akan tercatat di sini.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-modern">
                    <thead>
                    <tr>
                        <th style="width: 170px;">Waktu</th>
                        <th>Pengguna</th>
                        <th>Aktivitas</th>
                        <th>Keterangan</th>
                        <th style="width: 130px;">IP</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php $meta = activity_meta((string) $log['activity']); ?>
                        <tr>
                            <td>
                                <span class="cell-title"><?= e(tanggal_waktu_id((string) $log['created_at'])) ?></span>
                                <span class="cell-sub">WIB</span>
                            </td>
                            <td>
                                <?php if (!empty($log['user_name'])): ?>
                                    <span class="cell-title"><?= e($log['user_name']) ?></span>
                                    <span class="cell-sub">
                                        @<?= e($log['username'] ?? '-') ?>
                                        <?php if (!empty($log['role'])): ?>
                                            &middot; <?= e(role_label((string) $log['role'])) ?>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="cell-title"><?= e($log['username'] ?? 'Tidak dikenal') ?></span>
                                    <span class="cell-sub">Tidak terautentikasi</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= e($meta['class']) ?>">
                                    <i class="bi <?= e($meta['icon']) ?> me-1"></i><?= e($meta['label']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="log-description"><?= e($log['description'] ?: '-') ?></span>
                                <?php if (!empty($log['related_type']) && !empty($log['related_id'])): ?>
                                    <span class="cell-sub">
                                        <?= e($log['related_type']) ?> #<?= e((string) $log['related_id']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="cell-sub font-monospace"><?= e($log['ip_address'] ?: '-') ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($pages > 1): ?>
        <div class="card-footer bg-white border-top-0 pt-0">
            <nav aria-label="Navigasi halaman log">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php
                    $prevParams = array_merge($queryFilters, ['page' => max(1, $page - 1)]);
                    $nextParams = array_merge($queryFilters, ['page' => min($pages, $page + 1)]);
                    ?>
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(url_query('admin/log-aktivitas/index.php', $prevParams)) ?>"
                           aria-label="Sebelumnya">&laquo;</a>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link"
                               href="<?= e(url_query('admin/log-aktivitas/index.php', array_merge($queryFilters, ['page' => $i]))) ?>">
                                <?= e((string) $i) ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(url_query('admin/log-aktivitas/index.php', $nextParams)) ?>"
                           aria-label="Berikutnya">&raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<div class="financial-note">
    <i class="bi bi-lock-fill"></i>
    <span>Log aktivitas bersifat audit trail dan tidak dapat diubah atau dihapus melalui aplikasi.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
