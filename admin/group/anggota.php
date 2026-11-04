<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$groupId = get_string('group_id');
$groupId = ctype_digit($groupId) ? $groupId : '';
$filters = ['group_id' => $groupId];

$members = Membership::allActive($filters);
$groups = Group::all(['status' => '1']);
$hasFilter = $groupId !== '';

$joined = Membership::countStudentsInGroup();
$unjoined = Membership::countStudentsWithoutGroup();

$pageTitle = 'Anggota Group';
$pageSubtitle = 'Daftar keanggotaan aktif seluruh siswa beserta group dan pengajarnya.';
$breadcrumbs = [
    ['label' => 'Group Belajar', 'url' => url('admin/group/index.php')],
    ['label' => 'Anggota Group'],
];
$activeMenu = 'anggota-group';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="mini-stat">
            <span class="mini-stat-icon mini-stat-teal"><i class="bi bi-person-check-fill"></i></span>
            <div>
                <div class="mini-stat-value"><?= e(number_format($joined, 0, ',', '.')) ?></div>
                <div class="mini-stat-label">Siswa sudah tergabung</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mini-stat">
            <span class="mini-stat-icon mini-stat-gold"><i class="bi bi-person-dash-fill"></i></span>
            <div>
                <div class="mini-stat-value"><?= e(number_format($unjoined, 0, ',', '.')) ?></div>
                <div class="mini-stat-label">Siswa belum tergabung</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mini-stat">
            <span class="mini-stat-icon mini-stat-navy"><i class="bi bi-people-fill"></i></span>
            <div>
                <div class="mini-stat-value"><?= e(number_format(count($members), 0, ',', '.')) ?></div>
                <div class="mini-stat-label">Keanggotaan aktif<?= $hasFilter ? ' (filter)' : '' ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/group/anggota.php')) ?>" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label for="group_id" class="form-label">Filter group</label>
                <select class="form-select" id="group_id" name="group_id">
                    <option value="">Semua group aktif</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>" <?= $groupId === (string) $group['id'] ? 'selected' : '' ?>>
                            <?= e($group['name']) ?> (<?= e($group['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i>Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/group/anggota.php')) ?>" class="btn btn-light">
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
            <h2 class="card-title">Keanggotaan Aktif</h2>
            <div class="card-subtitle">Satu siswa hanya boleh memiliki satu keanggotaan aktif.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= e(url('admin/group/riwayat.php')) ?>" class="btn btn-light btn-sm">
                <i class="bi bi-clock-history me-1"></i>Riwayat
            </a>
            <a href="<?= e(url('admin/group/anggota-tambah.php')) ?>" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i>Tambah Anggota
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($members === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-people"></i></div>
                <div class="empty-state-title">
                    <?= $hasFilter ? 'Tidak ada anggota pada group ini' : 'Belum ada keanggotaan aktif' ?>
                </div>
                <p class="empty-state-text">
                    <?= $hasFilter
                        ? 'Pilih group lain atau reset filter.'
                        : 'Tambahkan siswa aktif yang belum memiliki group untuk memulai.' ?>
                </p>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/group/anggota.php')) ?>" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
                    </a>
                <?php else: ?>
                    <a href="<?= e(url('admin/group/anggota-tambah.php')) ?>" class="btn btn-primary">
                        <i class="bi bi-person-plus me-1"></i>Tambah Anggota
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
                        <th>Kelas</th>
                        <th>Group</th>
                        <th>Mapel / Guru</th>
                        <th>Mulai</th>
                        <th class="no-sort">Status</th>
                        <th class="no-sort" style="width: 124px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($members as $index => $member): ?>
                        <?php $studentUrl = url('admin/siswa/detail.php?id=' . (int) $member['student_id']); ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td>
                                <a href="<?= e($studentUrl) ?>" class="photo-link">
                                    <?= photo_tag($member['photo'] ?? null, (string) $member['name']) ?>
                                </a>
                            </td>
                            <td><span class="cell-title font-monospace"><?= e($member['code']) ?></span></td>
                            <td>
                                <a href="<?= e($studentUrl) ?>" class="cell-title link-dark text-decoration-none">
                                    <?= e($member['name']) ?>
                                </a>
                            </td>
                            <td><?= e($member['school_class'] ?: '-') ?></td>
                            <td>
                                <a href="<?= e(url('admin/group/detail.php?id=' . (int) $member['group_id'] . '&tab=anggota')) ?>"
                                   class="cell-title link-dark text-decoration-none">
                                    <?= e($member['group_name']) ?>
                                </a>
                                <span class="cell-sub">
                                    <?= e($member['group_code']) ?> &middot;
                                    <?= e(group_type_label((string) $member['group_type'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="cell-title"><?= e($member['subject_names'] ?: '-') ?></span>
                                <span class="cell-sub"><?= e($member['teacher_names'] ?: '-') ?></span>
                            </td>
                            <td><?= e(tanggal_id((string) $member['start_date'])) ?></td>
                            <td><?= membership_status_badge(null) ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e($studentUrl) ?>" class="btn btn-info btn-sm" title="Detail siswa">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= e(url('admin/group/pindah.php?student_id=' . (int) $member['student_id'])) ?>"
                                       class="btn btn-warning btn-sm" title="Pindah group">
                                        <i class="bi bi-arrow-left-right"></i>
                                    </a>
                                    <a href="<?= e(url('admin/group/anggota-akhir.php?membership_id=' . (int) $member['membership_id'])) ?>"
                                       class="btn btn-danger btn-sm" title="Akhiri keanggotaan">
                                        <i class="bi bi-person-dash"></i>
                                    </a>
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

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
