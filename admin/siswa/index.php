<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$status = get_string('status');
$status = in_array($status, ['1', '0'], true) ? $status : '';
$schoolClass = mb_substr(get_string('school_class'), 0, 50);
$groupId = get_string('group_id');
if ($groupId !== 'none' && !ctype_digit($groupId)) {
    $groupId = '';
}

$filters = [
    'status'       => $status,
    'school_class' => $schoolClass,
    'group_id'     => $groupId,
];

$students = Student::all($filters);
$classes = Student::schoolClasses();
$groups = Student::groupOptions();
$hasFilter = $status !== '' || $schoolClass !== '' || $groupId !== '';

$totalAll = Student::countAll();
$totalActive = Student::countActive();
$totalInactive = Student::countInactive();

$pageTitle = 'Data Siswa';
$pageSubtitle = 'Kelola identitas, data orang tua, dan status keaktifan siswa.';
$breadcrumbs = [['label' => 'Data Siswa']];
$activeMenu = 'siswa';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/siswa/index.php')) ?>" class="row g-2 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label for="status" class="form-label">Status siswa</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua status</option>
                    <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Aktif</option>
                    <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label for="school_class" class="form-label">Kelas sekolah</label>
                <select class="form-select" id="school_class" name="school_class">
                    <option value="">Semua kelas</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= e($class) ?>" <?= $schoolClass === (string) $class ? 'selected' : '' ?>>
                            <?= e($class) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label for="group_id" class="form-label">Group aktif</label>
                <select class="form-select" id="group_id" name="group_id">
                    <option value="">Semua group</option>
                    <option value="none" <?= $groupId === 'none' ? 'selected' : '' ?>>Belum punya group</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>" <?= $groupId === (string) $group['id'] ? 'selected' : '' ?>>
                            <?= e($group['name']) ?> (<?= e($group['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/siswa/index.php')) ?>" class="btn btn-light"
                       title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Daftar Siswa</h2>
            <div class="card-subtitle">
                <?= e(number_format($totalAll, 0, ',', '.')) ?> siswa terdaftar
                &middot; <?= e(number_format($totalActive, 0, ',', '.')) ?> aktif
                &middot; <?= e(number_format($totalInactive, 0, ',', '.')) ?> nonaktif
                <?php if ($hasFilter): ?>
                    &middot; <?= e(number_format(count($students), 0, ',', '.')) ?> sesuai filter
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e(url('admin/siswa/pendaftaran.php')) ?>" class="btn btn-light">
                <i class="bi bi-link-45deg me-1"></i>Pendaftaran Mandiri
            </a>
            <a href="<?= e(url('admin/siswa/tambah.php')) ?>" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i>Tambah Siswa
            </a>
        </div>
    </div>

    <div class="card-body p-0">
        <?php if ($students === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-people"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada siswa yang sesuai filter' : 'Belum ada data siswa' ?>
                </div>
                <p class="empty-state-text">
                    <?= $hasFilter
                        ? 'Coba ubah atau reset filter untuk melihat data lainnya.'
                        : 'Tambahkan siswa pertama untuk mulai mengelola data bimbingan belajar.' ?>
                </p>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/siswa/index.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                    </a>
                <?php else: ?>
                    <a href="<?= e(url('admin/siswa/tambah.php')) ?>" class="btn btn-primary">
                        <i class="bi bi-person-plus me-1"></i>Tambah Siswa
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width: 40px;">No.</th>
                        <th class="no-sort" style="width: 48px;">Foto</th>
                        <th>Kode</th>
                        <th>Nama Siswa</th>
                        <th style="width: 74px;">JK</th>
                        <th>Kelas</th>
                        <th>Orang Tua</th>
                        <th>No. HP Orang Tua</th>
                        <th>Group Aktif</th>
                        <th class="no-sort" style="width: 98px;">Status</th>
                        <th class="no-sort" style="width: 124px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $index => $student): ?>
                        <?php $detailUrl = url('admin/siswa/detail.php?id=' . (int) $student['id']); ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td>
                                <a href="<?= e($detailUrl) ?>" class="photo-link">
                                    <?= photo_tag($student['photo'] ?? null, (string) $student['name']) ?>
                                </a>
                            </td>
                            <td><span class="cell-title font-monospace"><?= e($student['code']) ?></span></td>
                            <td>
                                <a href="<?= e($detailUrl) ?>" class="cell-title link-dark text-decoration-none">
                                    <?= e($student['name']) ?>
                                </a>
                                <?php if (!empty($student['birth_place'])): ?>
                                    <span class="cell-sub"><?= e($student['birth_place']) ?></span>
                                <?php endif; ?>
                                <div class="mt-1"><?= profile_completion_badge($student) ?></div>
                            </td>
                            <td><?= e($student['gender'] === 'L' ? 'Laki-laki' : 'Perempuan') ?></td>
                            <td><?= e($student['school_class'] ?: '-') ?></td>
                            <td><?= e($student['parent_name'] ?: '-') ?></td>
                            <td><span class="text-nowrap"><?= e(phone_display($student['parent_phone'] ?? null)) ?></span></td>
                            <td>
                                <?php if (!empty($student['group_name'])): ?>
                                    <span class="cell-title"><?= e($student['group_name']) ?></span>
                                    <span class="cell-sub">
                                        <?= e($student['group_type'] === 'private' ? 'Private' : 'Group') ?>
                                        <?php if (!empty($student['subject_names'])): ?>
                                            &middot; <?= e($student['subject_names']) ?>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small"><i class="bi bi-dash-circle me-1"></i>Belum ada group</span>
                                <?php endif; ?>
                            </td>
                            <td><?= active_badge((int) $student['is_active'] === 1) ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e($detailUrl) ?>" class="btn btn-info btn-sm"
                                       title="Detail" aria-label="Detail <?= e($student['name']) ?>">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= e(url('admin/siswa/edit.php?id=' . (int) $student['id'])) ?>"
                                       class="btn btn-warning btn-sm" title="Edit"
                                       aria-label="Edit <?= e($student['name']) ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <form method="post" action="<?= e(url('admin/siswa/proses.php')) ?>"
                                          class="d-inline"
                                          data-confirm="<?= (int) $student['is_active'] === 1
                                              ? 'Siswa akan dinonaktifkan dan tidak dapat dimasukkan ke group baru.'
                                              : 'Siswa akan diaktifkan kembali dan dapat dimasukkan ke group.' ?>"
                                          data-confirm-title="Ubah status siswa"
                                          data-confirm-button="<?= (int) $student['is_active'] === 1 ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                                          data-confirm-icon="warning"
                                          data-submit-once>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $student['id'] ?>">
                                        <button type="submit"
                                                class="btn btn-sm <?= (int) $student['is_active'] === 1 ? 'btn-danger' : 'btn-success' ?>"
                                                title="<?= (int) $student['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>"
                                                aria-label="<?= (int) $student['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> <?= e($student['name']) ?>">
                                            <i class="bi <?= (int) $student['is_active'] === 1 ? 'bi-person-dash' : 'bi-person-check' ?>"></i>
                                        </button>
                                    </form>
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
    <span>Data siswa dengan riwayat group, tagihan, atau pembayaran tidak dihapus permanen.
        Gunakan tombol nonaktifkan agar riwayat tetap utuh.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
