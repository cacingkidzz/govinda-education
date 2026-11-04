<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$groupType = get_string('group_type');
$groupType = in_array($groupType, [Group::TYPE_PRIVATE, Group::TYPE_GROUP], true) ? $groupType : '';
$status = get_string('status');
$status = in_array($status, ['1', '0'], true) ? $status : '';

$filters = ['group_type' => $groupType, 'status' => $status];
$rules = Pricing::all($filters);
$hasFilter = $groupType !== '' || $status !== '';
$isAdmin = is_admin();

$pageTitle = 'Tarif Paket Belajar';
$pageSubtitle = 'Atur nominal satu paket 8 pertemuan berdasarkan jenis kelompok dan jumlah anggota aktif.';
$breadcrumbs = [['label' => 'Tarif Paket']];
$activeMenu = 'tarif';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="alert alert-info d-flex gap-2 align-items-start">
    <i class="bi bi-info-circle-fill mt-1"></i>
    <div class="small">
        Tarif dihitung <strong>per siswa untuk satu paket <?= e((string) Pricing::PACKAGE_SIZE) ?> pertemuan</strong>,
        bukan per bulan dan tidak dikalikan jumlah mata pelajaran. Nominal diisi manual oleh admin.
        Perubahan tarif tidak mengubah tagihan maupun nota yang sudah terbit.
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/tarif/index.php')) ?>" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="group_type" class="form-label">Jenis kelompok</label>
                <select class="form-select" id="group_type" name="group_type">
                    <option value="">Semua jenis</option>
                    <option value="private" <?= $groupType === 'private' ? 'selected' : '' ?>>Private (1 siswa)</option>
                    <option value="group" <?= $groupType === 'group' ? 'selected' : '' ?>>Reguler (2–7 siswa)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua status</option>
                    <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Aktif</option>
                    <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/tarif/index.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Daftar Tarif</h2>
            <div class="card-subtitle">
                <?= e(number_format(count($rules), 0, ',', '.')) ?> aturan tarif
                <?= $hasFilter ? ' &middot; sesuai filter' : '' ?>
            </div>
        </div>
        <?php if ($isAdmin): ?>
            <a href="<?= e(url('admin/tarif/tambah.php')) ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Tambah Tarif
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if ($rules === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-cash-coin"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada tarif yang sesuai filter' : 'Belum ada tarif paket' ?>
                </div>
                <p class="empty-state-text">
                    <?= $hasFilter
                        ? 'Coba ubah atau reset filter untuk melihat tarif lainnya.'
                        : 'Tambahkan tarif Private dan Reguler agar tagihan paket dapat diterbitkan otomatis.' ?>
                </p>
                <?php if ($isAdmin && !$hasFilter): ?>
                    <a href="<?= e(url('admin/tarif/tambah.php')) ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Tambah Tarif
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width:40px;">No.</th>
                        <th>Jenis Kelompok</th>
                        <th>Jumlah Siswa</th>
                        <th>Nominal / Paket</th>
                        <th>Berlaku</th>
                        <th>Keterangan</th>
                        <th class="no-sort">Status</th>
                        <?php if ($isAdmin): ?>
                            <th class="no-sort" style="width:130px;">Aksi</th>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rules as $index => $rule): ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td>
                                <span class="cell-title"><?= e(package_type_label((string) $rule['group_type'])) ?></span>
                                <span class="cell-sub"><?= e(group_type_label((string) $rule['group_type'])) ?></span>
                            </td>
                            <td><?= e((string) (int) $rule['student_count']) ?> siswa</td>
                            <td class="amount fw-semibold"><?= e(rupiah((float) $rule['amount'])) ?></td>
                            <td class="text-nowrap">
                                <span class="cell-title"><?= e(tanggal_short_id((string) $rule['effective_start'])) ?></span>
                                <span class="cell-sub">
                                    s.d. <?= $rule['effective_end'] !== null
                                        ? e(tanggal_short_id((string) $rule['effective_end']))
                                        : 'tanpa batas' ?>
                                </span>
                            </td>
                            <td><span class="cell-sub d-block" style="max-width:220px;"><?= e($rule['notes'] ?: '-') ?></span></td>
                            <td><?= active_badge((int) $rule['is_active'] === 1) ?></td>
                            <?php if ($isAdmin): ?>
                                <td>
                                    <div class="table-actions">
                                        <a href="<?= e(url('admin/tarif/edit.php?id=' . (int) $rule['id'])) ?>"
                                           class="btn btn-warning btn-sm" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form method="post" action="<?= e(url('admin/tarif/proses.php')) ?>"
                                              data-confirm="<?= (int) $rule['is_active'] === 1
                                                  ? 'Tarif ini akan dinonaktifkan dan tidak dipakai untuk tagihan baru.'
                                                  : 'Tarif ini akan diaktifkan kembali untuk tagihan baru.' ?>"
                                              data-confirm-title="Ubah status tarif"
                                              data-confirm-button="<?= (int) $rule['is_active'] === 1 ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                                              data-confirm-icon="warning" data-submit-once>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $rule['id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-sm <?= (int) $rule['is_active'] === 1 ? 'btn-danger' : 'btn-success' ?>"
                                                    title="<?= (int) $rule['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                                <i class="bi <?= (int) $rule['is_active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
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
    <span>Tarif dipilih berdasarkan tanggal efektif paket. Jika belum ada tarif yang berlaku, paket tetap dibuat
        tetapi tagihan belum diterbitkan; admin dapat menerbitkannya setelah tarif diatur.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
