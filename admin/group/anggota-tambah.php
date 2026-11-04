<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$groupId = get_int('group_id');
$studentId = get_int('student_id');
$group = $groupId > 0 ? Group::find($groupId) : null;
$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];

$pageStyles = ['vendor/select2/select2.min.css', 'vendor/select2/select2-bootstrap-5-theme.min.css'];
$pageScripts = ['vendor/select2/select2.min.js'];
$activeMenu = 'anggota-group';

if ($group === null) {
    $groups = Group::activeOptions();
    $pageTitle = 'Tambah Anggota Group';
    $pageSubtitle = 'Pilih group tujuan sebelum menambahkan siswa.';
    $breadcrumbs = [
        ['label' => 'Group Belajar', 'url' => url('admin/group/index.php')],
        ['label' => 'Anggota Group', 'url' => url('admin/group/anggota.php')],
        ['label' => 'Pilih Group'],
    ];

    require_once ROOT_PATH . '/includes/header.php';
    ?>
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Pilih Group Tujuan</h2>
                <div class="card-subtitle">Hanya group aktif yang masih memiliki kapasitas.</div>
            </div>
            <span class="badge bg-teal-soft text-teal"><?= e((string) count($groups)) ?> group aktif</span>
        </div>
        <div class="card-body p-0">
            <?php if ($groups === []): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-diagram-3"></i></div>
                    <div class="empty-state-title">Belum ada group aktif</div>
                    <p class="empty-state-text">Buat group terlebih dahulu sebelum menambahkan anggota.</p>
                    <?php if (is_admin()): ?>
                        <a href="<?= e(url('admin/group/tambah.php')) ?>" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i>Tambah Group
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Group</th>
                            <th>Jenis</th>
                            <th>Guru / Mapel</th>
                            <th class="text-center">Anggota</th>
                            <th class="text-center">Sisa Kapasitas</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($groups as $option): ?>
                            <?php
                            $limit = Group::capacityLimit($option);
                            $count = (int) $option['member_count'];
                            $remaining = max(0, $limit - $count);
                            $full = $remaining <= 0;
                            $pickUrl = url_query('admin/group/anggota-tambah.php', [
                                'group_id' => (int) $option['id'],
                                'student_id' => $studentId > 0 ? $studentId : null,
                            ]);
                            ?>
                            <tr>
                                <td>
                                    <span class="cell-title"><?= e($option['name']) ?></span>
                                    <span class="cell-sub font-monospace"><?= e($option['code']) ?></span>
                                </td>
                                <td><?= group_type_badge((string) $option['group_type']) ?></td>
                                <td>
                                    <span class="cell-title"><?= e($option['teacher_names'] ?: '-') ?></span>
                                    <span class="cell-sub"><?= e($option['subject_names'] ?: '-') ?></span>
                                </td>
                                <td class="text-center"><?= capacity_badge($count, (int) $option['capacity'], (string) $option['group_type']) ?></td>
                                <td class="text-center">
                                    <?php if ($full): ?>
                                        <span class="badge text-bg-danger">Penuh</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-success"><?= e((string) $remaining) ?> siswa</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($full): ?>
                                        <button type="button" class="btn btn-light btn-sm" disabled>Kapasitas penuh</button>
                                    <?php else: ?>
                                        <a href="<?= e($pickUrl) ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-arrow-right me-1"></i>Pilih
                                        </a>
                                    <?php endif; ?>
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
        <span>Siswa yang sudah memiliki group aktif tidak muncul pada pilihan. Gunakan fitur pindah group
            untuk memindahkan siswa tanpa menghilangkan riwayatnya.</span>
    </div>
    <?php
    require_once ROOT_PATH . '/includes/footer.php';
    exit;
}

$members = Group::activeMemberCount($groupId);
$capacity = Group::capacityLimit($group);
$remaining = max(0, $capacity - $members);
$isFull = $remaining <= 0;
$groupActive = (int) $group['is_active'] === 1;
$availableStudents = Membership::availableStudents();
$selectedStudent = $studentId > 0 ? (string) $studentId : old_value($old, [], 'student_id', '');
$v = static fn (string $key, string $default = ''): string => old_value($old, [], $key, $default);

$pageTitle = 'Tambah Anggota';
$pageSubtitle = 'Tambahkan siswa aktif yang belum memiliki keanggotaan group.';
$breadcrumbs = [
    ['label' => 'Group Belajar', 'url' => url('admin/group/index.php')],
    ['label' => (string) $group['name'], 'url' => url('admin/group/detail.php?id=' . $groupId . '&tab=anggota')],
    ['label' => 'Tambah Anggota'],
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Form Anggota Baru</h2>
                    <div class="card-subtitle">Validasi kapasitas dilakukan kembali di dalam transaksi database.</div>
                </div>
                <span class="badge bg-teal-soft text-teal font-monospace"><?= e($group['code']) ?></span>
            </div>
            <div class="card-body">
                <?php if (!empty($errors['form'])): ?>
                    <div class="alert alert-danger py-2 px-3 small">
                        <i class="bi bi-x-circle-fill me-1"></i><?= e($errors['form']) ?>
                    </div>
                <?php endif; ?>
                <?php if (!$groupActive): ?>
                    <div class="alert alert-danger py-2 px-3 small">
                        <i class="bi bi-x-circle-fill me-1"></i>Group sedang nonaktif sehingga tidak dapat menerima anggota.
                    </div>
                <?php elseif ($isFull): ?>
                    <div class="alert alert-warning py-2 px-3 small">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Kapasitas group sudah penuh
                        (<?= e((string) $members) ?>/<?= e((string) $capacity) ?> siswa).
                    </div>
                <?php endif; ?>

                <?php if ($availableStudents === []): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-person-check"></i></div>
                        <div class="empty-state-title">Tidak ada siswa yang tersedia</div>
                        <p class="empty-state-text">Semua siswa aktif sudah memiliki group. Nonaktifkan keanggotaan
                            atau pindahkan siswa bila ingin mengubah penempatan.</p>
                        <a href="<?= e(url('admin/siswa/index.php')) ?>" class="btn btn-light">
                            <i class="bi bi-people me-1"></i>Lihat Data Siswa
                        </a>
                    </div>
                <?php else: ?>
                    <form method="post" action="<?= e(url('admin/group/anggota-proses.php')) ?>"
                          data-submit-once novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="group_id" value="<?= $groupId ?>">

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="student_id" class="form-label">Siswa <span class="text-danger">*</span></label>
                                <select class="form-select js-select2<?= field_invalid($errors, 'student_id') ?>"
                                        id="student_id" name="student_id" required data-placeholder="Cari siswa">
                                    <option value="">Pilih siswa</option>
                                    <?php foreach ($availableStudents as $student): ?>
                                        <option value="<?= (int) $student['id'] ?>"
                                            <?= $selectedStudent === (string) $student['id'] ? 'selected' : '' ?>>
                                            <?= e($student['name']) ?> — <?= e($student['code']) ?>
                                            <?= $student['school_class'] ? ' (' . e($student['school_class']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($errors, 'student_id') ?>
                                <div class="form-text">Hanya siswa aktif tanpa group aktif yang ditampilkan.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Tanggal masuk <span class="text-danger">*</span></label>
                                <input type="date" class="form-control<?= field_invalid($errors, 'start_date') ?>"
                                       id="start_date" name="start_date"
                                       value="<?= e($v('start_date', date('Y-m-d'))) ?>" required>
                                <?= field_error($errors, 'start_date') ?>
                            </div>
                            <div class="col-12">
                                <label for="note" class="form-label">Keterangan</label>
                                <textarea class="form-control<?= field_invalid($errors, 'note') ?>" id="note" name="note"
                                          rows="3" maxlength="255"
                                          placeholder="Catatan penempatan group (opsional)"><?= e($v('note')) ?></textarea>
                                <?= field_error($errors, 'note') ?>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <button type="submit" class="btn btn-primary"
                                <?= (!$groupActive || $isFull) ? 'disabled' : '' ?>>
                                <i class="bi bi-person-plus me-1"></i>Simpan Anggota
                            </button>
                            <a href="<?= e(url('admin/group/detail.php?id=' . $groupId . '&tab=anggota')) ?>"
                               class="btn btn-secondary">Batal</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Group Tujuan</h2>
                    <div class="card-subtitle">Ringkasan kapasitas.</div>
                </div>
            </div>
            <div class="card-body">
                <dl class="detail-list">
                    <div class="detail-row">
                        <dt>Group</dt>
                        <dd><?= e($group['name']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Jenis</dt>
                        <dd><?= group_type_badge((string) $group['group_type']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Guru</dt>
                        <dd><?= e($group['teacher_names'] ?: '-') ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Mapel</dt>
                        <dd><?= e($group['subject_names'] ?: '-') ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Anggota</dt>
                        <dd><?= capacity_badge($members, (int) $group['capacity'], (string) $group['group_type']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Sisa</dt>
                        <dd><?= e((string) $remaining) ?> siswa</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="master-note mt-0">
            <i class="bi bi-shield-lock"></i>
            <span>Penambahan anggota tidak membuat invoice atau pembayaran. Penagihan mengikuti periode
                tagihan yang berlaku dan belum termasuk dalam tahap ini.</span>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
