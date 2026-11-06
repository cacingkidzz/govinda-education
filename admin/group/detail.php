<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$id = get_int('id');
$group = $id > 0 ? Group::find($id) : null;
if ($group === null) {
    abort_404('Data group yang Anda cari tidak ditemukan.');
}

$members = Group::members($id, false);
$activeMembers = array_values(array_filter(
    $members,
    static fn (array $row): bool => $row['end_date'] === null
));
$schedules = Schedule::forGroup($id, false);
$relations = GroupSubject::forGroup($id, true);
$activeRelations = GroupSubject::activeForGroup($id);
$activities = ActivityLog::forRelated('group', $id, 40);
$memberCount = count($activeMembers);
$capacity = Group::capacityLimit($group);
$isAdmin = is_admin();
$isActive = (int) $group['is_active'] === 1;
$meetsComposition = Group::meetsComposition($group, $memberCount);
$canReceive = $isActive && $memberCount < $capacity;

$tab = get_string('tab', 'info');
$allowedTabs = ['info', 'anggota', 'mapel', 'jadwal', 'pertemuan', 'riwayat'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'info';
}

// Jurnal pertemuan group: gabungan seluruh mata pelajaran, tanpa batas bulanan.
$meetingRows = Meeting::recorded(['group_id' => $id]);
$meetingDoneCount = 0;
$meetingScheduledCount = 0;
$doneByRelation = [];
foreach ($meetingRows as $meetingRow) {
    if ((string) $meetingRow['status'] === Meeting::STATUS_DONE) {
        $meetingDoneCount++;
        $relationKey = (int) $meetingRow['id'] > 0 ? (int) ($meetingRow['group_subject_id'] ?? 0) : 0;
        if ($relationKey > 0) {
            $doneByRelation[$relationKey] = ($doneByRelation[$relationKey] ?? 0) + 1;
        }
    } elseif ((string) $meetingRow['status'] === Meeting::STATUS_SCHEDULED) {
        $meetingScheduledCount++;
    }
}

// Pilihan mapel untuk form catatan pertemuan beserta jam jadwal aktifnya.
$journalRelations = [];
foreach ($activeRelations as $relation) {
    $slots = Schedule::forGroupSubject((int) $relation['id'], true);
    $firstSlot = $slots[0] ?? null;
    $journalRelations[] = [
        'id'         => (int) $relation['id'],
        'label'      => (string) $relation['subject_name'] . ' — ' . (string) $relation['teacher_name'],
        'start_time' => $firstSlot !== null ? (string) $firstSlot['start_time'] : '',
        'end_time'   => $firstSlot !== null ? (string) $firstSlot['end_time'] : '',
        'day'        => $firstSlot !== null ? (int) $firstSlot['day_of_week'] : 0,
    ];
}

$journalState = ['errors' => [], 'old' => []];
if ($tab === 'pertemuan') {
    $journalState = pull_form_state();
}
$journalErrors = $journalState['errors'];
$journalOld = $journalState['old'];
$journalValue = static function (string $key, string $default = '') use ($journalOld): string {
    return array_key_exists($key, $journalOld) ? (string) $journalOld[$key] : $default;
};
$journalDefaultRelation = $journalRelations !== [] ? (string) $journalRelations[0]['id'] : '';
$journalRelationValue = $journalValue('group_subject_id', $journalDefaultRelation);
$journalDateValue = $journalValue('meeting_date', date('Y-m-d'));
$journalStartValue = $journalValue('start_time');
$journalEndValue = $journalValue('end_time');
if ($journalOld === [] && $journalRelations !== []) {
    $journalStartValue = substr($journalRelations[0]['start_time'], 0, 5);
    $journalEndValue = substr($journalRelations[0]['end_time'], 0, 5);
}
$journalStatusValue = $journalValue('status', Meeting::STATUS_DONE);

$relationIssues = [];
foreach ($relations as $relation) {
    if ((int) $relation['is_active'] !== 1) {
        continue;
    }
    if ((int) $relation['subject_active'] !== 1) {
        $relationIssues[] = 'Mata pelajaran "' . $relation['subject_name'] . '" sedang nonaktif.';
    }
    if ((int) $relation['teacher_active'] !== 1) {
        $relationIssues[] = 'Guru "' . $relation['teacher_name'] . '" pada mata pelajaran "'
            . $relation['subject_name'] . '" sedang nonaktif.';
    }
}
$relationIssues = array_values(array_unique($relationIssues));

$pageTitle = $group['name'];
$pageSubtitle = 'Detail group, anggota aktif, mata pelajaran, jadwal, jurnal pertemuan, dan riwayat perubahannya.';
$breadcrumbs = [
    ['label' => 'Group Belajar', 'url' => url('admin/group/index.php')],
    ['label' => (string) $group['name']],
];
$activeMenu = 'group';
$pageStyles = ['vendor/select2/select2.min.css', 'vendor/select2/select2-bootstrap-5-theme.min.css'];
$pageScripts = ['vendor/select2/select2.min.js'];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-start gap-3">
                    <div class="group-emblem">
                        <i class="bi <?= (string) $group['group_type'] === 'private' ? 'bi-person-fill' : 'bi-people-fill' ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h2 class="profile-name mb-0"><?= e($group['name']) ?></h2>
                            <?= group_type_badge((string) $group['group_type']) ?>
                            <?= active_badge($isActive) ?>
                        </div>
                        <div class="profile-code font-monospace"><?= e($group['code']) ?></div>
                        <div class="profile-meta justify-content-start mt-2">
                            <span><i class="bi bi-journal-bookmark me-1"></i>
                                <?= $group['subject_names'] !== null
                                    ? e((string) $group['subject_names'])
                                    : 'Belum ada mata pelajaran' ?>
                            </span>
                            <span><i class="bi bi-person-video3 me-1"></i>
                                <?= $group['teacher_names'] !== null
                                    ? e((string) $group['teacher_names'])
                                    : 'Belum ada guru' ?>
                            </span>
                            <span><i class="bi bi-people me-1"></i><?= e((string) $memberCount) ?> / <?= e((string) $capacity) ?> siswa</span>
                            <span><i class="bi bi-calendar-week me-1"></i><?= e((string) count($schedules)) ?> jadwal</span>
                        </div>
                    </div>
                </div>

                <?php if (!$isActive): ?>
                    <div class="alert alert-warning mt-3 mb-0 py-2 px-3 small">
                        <i class="bi bi-pause-circle me-1"></i>Group sedang nonaktif. Group tidak dapat menerima anggota,
                        jadwal baru, atau menjadi tujuan perpindahan sampai diaktifkan kembali.
                    </div>
                <?php elseif (!$meetsComposition): ?>
                    <div class="alert alert-info mt-3 mb-0 py-2 px-3 small">
                        <i class="bi bi-info-circle me-1"></i>
                        <?= (string) $group['group_type'] === 'private'
                            ? 'Group private memerlukan tepat 1 siswa aktif.'
                            : 'Group belajar memerlukan minimal 2 siswa aktif agar memenuhi komposisi.' ?>
                        Saat ini <?= e((string) $memberCount) ?> siswa aktif.
                    </div>
                <?php endif; ?>

                <?php if ($relationIssues !== []): ?>
                    <div class="alert alert-danger mt-3 mb-0 py-2 px-3 small">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <?= e(implode(' ', $relationIssues)) ?>
                        Perbarui relasi mata pelajaran dan guru pada tab <strong>Mata Pelajaran &amp; Guru</strong>.
                    </div>
                <?php endif; ?>

                <?php if ($relations === []): ?>
                    <div class="alert alert-info mt-3 mb-0 py-2 px-3 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Group ini belum memiliki mata pelajaran. Tambahkan minimal satu mata pelajaran dan guru
                        agar jadwal dapat dibuat.
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-2">
                <?php if ($isAdmin): ?>
                    <a href="<?= e(url('admin/group/edit.php?id=' . $id)) ?>" class="btn btn-warning btn-sm">
                        <i class="bi bi-pencil-square me-1"></i>Edit Group
                    </a>
                    <a href="<?= e(url('admin/group/mapel.php?group_id=' . $id)) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-journal-plus me-1"></i>Mata Pelajaran &amp; Guru
                    </a>
                <?php endif; ?>
                <?php if ($canReceive): ?>
                    <a href="<?= e(url('admin/group/anggota-tambah.php?group_id=' . $id . '&tab=anggota')) ?>"
                       class="btn btn-primary btn-sm">
                        <i class="bi bi-person-plus me-1"></i>Tambah Anggota
                    </a>
                <?php endif; ?>
                <?php if ($isActive && $memberCount > 0): ?>
                    <a href="<?= e(url('admin/paket/tambah.php?mode=group&group_id=' . $id)) ?>"
                       class="btn btn-outline-success btn-sm">
                        <i class="bi bi-people me-1"></i>Buat Paket Massal
                    </a>
                <?php endif; ?>
                <?php if ($isAdmin && $isActive && $activeRelations !== []): ?>
                    <a href="<?= e(url('admin/jadwal/tambah.php?group_id=' . $id . '&return=group&return_id=' . $id)) ?>"
                       class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-calendar-plus me-1"></i>Tambah Jadwal
                    </a>
                <?php endif; ?>
                <a href="<?= e(url('admin/group/riwayat.php?from_group_id=' . $id)) ?>" class="btn btn-light btn-sm">
                    <i class="bi bi-arrow-left-right me-1"></i>Riwayat Perpindahan
                </a>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Ringkasan</h2>
                    <div class="card-subtitle">Status kesiapan group.</div>
                </div>
            </div>
            <div class="card-body">
                <dl class="detail-list">
                    <div class="detail-row">
                        <dt>Anggota aktif</dt>
                        <dd><?= capacity_badge($memberCount, (int) $group['capacity'], (string) $group['group_type']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Komposisi</dt>
                        <dd>
                            <?= $meetsComposition
                                ? '<span class="badge text-bg-success">Memenuhi aturan</span>'
                                : '<span class="badge text-bg-warning">Belum memenuhi</span>' ?>
                        </dd>
                    </div>
                    <div class="detail-row">
                        <dt>Mata pelajaran aktif</dt>
                        <dd><?= e((string) count($relations)) ?> relasi</dd>
                    </div>
                    <div class="detail-row">
                        <dt>Jadwal aktif</dt>
                        <dd>
                            <?= e((string) count(array_filter(
                                $schedules,
                                static fn (array $row): bool => (int) $row['is_active'] === 1
                            ))) ?>
                        </dd>
                    </div>
                    <div class="detail-row">
                        <dt>Dibuat</dt>
                        <dd><?= e(tanggal_waktu_id($group['created_at'] ?? null)) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Diperbarui</dt>
                        <dd><?= e(tanggal_waktu_id($group['updated_at'] ?? null)) ?></dd>
                    </div>
                </dl>
            </div>
            <?php if ($isAdmin): ?>
                <div class="card-footer bg-white">
                    <form method="post" action="<?= e(url('admin/group/proses.php')) ?>"
                          data-confirm="<?= $isActive
                              ? 'Group akan dinonaktifkan. Anggota dan jadwal tetap tersimpan, tetapi group tidak dapat menerima anggota baru.'
                              : 'Group akan diaktifkan kembali dan dapat menerima anggota serta jadwal baru.' ?>"
                          data-confirm-title="Ubah status group"
                          data-confirm-button="<?= $isActive ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                          data-confirm-icon="warning" data-submit-once>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button type="submit" class="btn btn-sm w-100 <?= $isActive ? 'btn-danger' : 'btn-success' ?>">
                            <i class="bi <?= $isActive ? 'bi-pause-circle' : 'bi-play-circle' ?> me-1"></i>
                            <?= $isActive ? 'Nonaktifkan Group' : 'Aktifkan Group' ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header p-0 border-0">
        <ul class="nav nav-tabs group-tabs px-3 pt-2" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $tab === 'info' ? 'active' : '' ?>" id="tab-info"
                        data-bs-toggle="tab" data-bs-target="#pane-info" type="button" role="tab">
                    <i class="bi bi-info-circle me-1"></i>Informasi
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $tab === 'anggota' ? 'active' : '' ?>" id="tab-anggota"
                        data-bs-toggle="tab" data-bs-target="#pane-anggota" type="button" role="tab">
                    <i class="bi bi-people me-1"></i>Anggota
                    <span class="badge text-bg-light ms-1"><?= e((string) $memberCount) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $tab === 'mapel' ? 'active' : '' ?>" id="tab-mapel"
                        data-bs-toggle="tab" data-bs-target="#pane-mapel" type="button" role="tab">
                    <i class="bi bi-journal-bookmark me-1"></i>Mata Pelajaran &amp; Guru
                    <span class="badge text-bg-light ms-1"><?= e((string) count($relations)) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $tab === 'jadwal' ? 'active' : '' ?>" id="tab-jadwal"
                        data-bs-toggle="tab" data-bs-target="#pane-jadwal" type="button" role="tab">
                    <i class="bi bi-calendar-week me-1"></i>Jadwal
                    <span class="badge text-bg-light ms-1"><?= e((string) count($schedules)) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $tab === 'pertemuan' ? 'active' : '' ?>" id="tab-pertemuan"
                        data-bs-toggle="tab" data-bs-target="#pane-pertemuan" type="button" role="tab">
                    <i class="bi bi-journal-text me-1"></i>Pertemuan
                    <span class="badge text-bg-light ms-1"><?= e((string) count($meetingRows)) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $tab === 'riwayat' ? 'active' : '' ?>" id="tab-riwayat"
                        data-bs-toggle="tab" data-bs-target="#pane-riwayat" type="button" role="tab">
                    <i class="bi bi-clock-history me-1"></i>Riwayat Perubahan
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">

            <div class="tab-pane fade <?= $tab === 'info' ? 'show active' : '' ?>" id="pane-info" role="tabpanel">
                <div class="row g-3">
                    <div class="col-md-6">
                        <dl class="detail-list">
                            <div class="detail-row">
                                <dt>Kode group</dt>
                                <dd class="font-monospace"><?= e($group['code']) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Nama group</dt>
                                <dd><?= e($group['name']) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Jenis group</dt>
                                <dd><?= group_type_label((string) $group['group_type']) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Status</dt>
                                <dd><?= active_badge($isActive) ?></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="detail-list">
                            <div class="detail-row">
                                <dt>Kapasitas</dt>
                                <dd><?= e((string) $capacity) ?> siswa</dd>
                            </div>
                            <div class="detail-row">
                                <dt>Anggota aktif</dt>
                                <dd><?= e((string) $memberCount) ?> siswa</dd>
                            </div>
                            <div class="detail-row">
                                <dt>Mata pelajaran</dt>
                                <dd><?= e((string) count($relations)) ?> relasi aktif</dd>
                            </div>
                            <div class="detail-row">
                                <dt>Total jadwal</dt>
                                <dd><?= e((string) count($schedules)) ?> jadwal</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="col-12">
                        <dl class="detail-list">
                            <div class="detail-row">
                                <dt>Keterangan</dt>
                                <dd><?= nl2br(e($group['description'] ?: '-')) ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade <?= $tab === 'anggota' ? 'show active' : '' ?>" id="pane-anggota" role="tabpanel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div class="text-secondary small">
                        <?= e((string) $memberCount) ?> anggota aktif dari kapasitas <?= e((string) $capacity) ?>.
                        Keanggotaan selesai tetap tersimpan sebagai riwayat.
                    </div>
                    <?php if ($canReceive): ?>
                        <a href="<?= e(url('admin/group/anggota-tambah.php?group_id=' . $id . '&tab=anggota')) ?>"
                           class="btn btn-primary btn-sm">
                            <i class="bi bi-person-plus me-1"></i>Tambah Anggota
                        </a>
                    <?php endif; ?>
                    <?php if ($isActive && $memberCount > 0): ?>
                        <a href="<?= e(url('admin/paket/tambah.php?mode=group&group_id=' . $id)) ?>"
                           class="btn btn-outline-success btn-sm">
                            <i class="bi bi-people me-1"></i>Buat Paket Massal
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($members === []): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-people"></i></div>
                        <div class="empty-state-title">Belum ada anggota</div>
                        <p class="empty-state-text">Tambahkan siswa aktif yang belum memiliki group.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 40px;">No.</th>
                                <th style="width: 48px;">Foto</th>
                                <th>Kode</th>
                                <th>Nama Siswa</th>
                                <th>Kelas</th>
                                <th>Mulai</th>
                                <th>Keluar</th>
                                <th>Status</th>
                                <th class="text-end" style="width: 160px;">Aksi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($members as $index => $member): ?>
                                <?php
                                $isMemberActive = $member['end_date'] === null;
                                $studentUrl = url('admin/siswa/detail.php?id=' . (int) $member['student_id']);
                                ?>
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
                                        <?php if (!empty($member['reason'])): ?>
                                            <span class="cell-sub text-truncate d-block" style="max-width: 260px;">
                                                <?= e($member['reason']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($member['school_class'] ?: '-') ?></td>
                                    <td><?= e(tanggal_id((string) $member['start_date'])) ?></td>
                                    <td><?= e($member['end_date'] ? tanggal_id((string) $member['end_date']) : '-') ?></td>
                                    <td><?= membership_status_badge($member['end_date'] !== null ? (string) $member['end_date'] : null) ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="<?= e($studentUrl) ?>" class="btn btn-info btn-sm" title="Detail siswa">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($isMemberActive): ?>
                                                <a href="<?= e(url('admin/group/pindah.php?student_id=' . (int) $member['student_id'])) ?>"
                                                   class="btn btn-warning btn-sm" title="Pindah group">
                                                    <i class="bi bi-arrow-left-right"></i>
                                                </a>
                                                <a href="<?= e(url('admin/group/anggota-akhir.php?membership_id=' . (int) $member['membership_id'])) ?>"
                                                   class="btn btn-danger btn-sm" title="Akhiri keanggotaan">
                                                    <i class="bi bi-person-dash"></i>
                                                </a>
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

            <div class="tab-pane fade <?= $tab === 'mapel' ? 'show active' : '' ?>" id="pane-mapel" role="tabpanel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div class="text-secondary small">
                        Satu group dapat memiliki banyak mata pelajaran; setiap mata pelajaran diampu satu guru.
                        Jumlah pertemuan bukan batas bulanan — target <strong>8 pertemuan</strong> berlaku per paket
                        belajar siswa dan dihitung dari gabungan seluruh mata pelajaran.
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= e(url('admin/group/detail.php?id=' . $id . '&tab=pertemuan')) ?>"
                           class="btn btn-light btn-sm" title="Lihat jurnal pertemuan group">
                            <i class="bi bi-journal-text me-1"></i>Jurnal Pertemuan
                        </a>
                        <?php if ($isAdmin && $isActive): ?>
                            <a href="<?= e(url('admin/group/mapel-tambah.php?group_id=' . $id)) ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-journal-plus me-1"></i>Tambah Mata Pelajaran
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($relations === []): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-journal-x"></i></div>
                        <div class="empty-state-title">Belum ada mata pelajaran</div>
                        <p class="empty-state-text">Tambahkan mata pelajaran beserta guru yang mengampunya.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 40px;">No.</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th class="text-center">Jadwal</th>
                                <th class="text-center">Terlaksana</th>
                                <th>Status</th>
                                <th class="text-end" style="width: 170px;">Aksi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($relations as $index => $relation): ?>
                                <?php
                                $relationCounted = (int) ($relation['is_active'] ?? 0) === 1;
                                $subjectOk = (int) $relation['subject_active'] === 1;
                                $teacherOk = (int) $relation['teacher_active'] === 1;
                                ?>
                                <tr>
                                    <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                                    <td>
                                        <a href="<?= e(url('admin/mata-pelajaran/detail.php?id=' . (int) $relation['subject_id'])) ?>"
                                           class="cell-title link-dark text-decoration-none">
                                            <?= e($relation['subject_name']) ?>
                                        </a>
                                        <span class="cell-sub font-monospace"><?= e($relation['subject_code']) ?></span>
                                        <?php if (!$subjectOk): ?>
                                            <span class="badge text-bg-secondary ms-1">Mapel nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= e(url('admin/guru/detail.php?id=' . (int) $relation['teacher_id'])) ?>"
                                           class="cell-title link-dark text-decoration-none">
                                            <?= e($relation['teacher_name']) ?>
                                        </a>
                                        <span class="cell-sub font-monospace"><?= e($relation['teacher_code']) ?></span>
                                        <?php if (!$teacherOk): ?>
                                            <span class="badge text-bg-secondary ms-1">Guru nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= e((string) ($relation['schedule_count'] ?? 0)) ?></td>
                                    <td class="text-center">
                                        <?php if ($relationCounted): ?>
                                            <span class="fw-semibold"><?= e((string) ($doneByRelation[(int) $relation['id']] ?? 0)) ?></span>
                                            <span class="cell-sub">pertemuan</span>
                                        <?php else: ?>
                                            <span class="text-secondary" title="Relasi nonaktif: pertemuan tidak dihitung">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= active_badge((int) $relation['is_active'] === 1) ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <?php if ($isAdmin): ?>
                                                <a href="<?= e(url('admin/group/mapel-edit.php?id=' . (int) $relation['id'])) ?>"
                                                   class="btn btn-warning btn-sm" title="Edit relasi">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <form method="post" action="<?= e(url('admin/group/mapel-proses.php')) ?>"
                                                      data-confirm="<?= (int) $relation['is_active'] === 1
                                                          ? 'Relasi mata pelajaran akan dinonaktifkan. Jadwal relasi ini tidak lagi dihitung sampai diaktifkan kembali.'
                                                          : 'Relasi akan diaktifkan kembali setelah pemeriksaan bentrok jadwal.' ?>"
                                                      data-confirm-title="Ubah status relasi"
                                                      data-confirm-button="<?= (int) $relation['is_active'] === 1 ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                                                      data-confirm-icon="warning" data-submit-once>
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?= (int) $relation['id'] ?>">
                                                    <button type="submit"
                                                            class="btn btn-sm <?= (int) $relation['is_active'] === 1 ? 'btn-danger' : 'btn-success' ?>"
                                                            title="<?= (int) $relation['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                                        <i class="bi <?= (int) $relation['is_active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="<?= e(url('admin/jadwal/index.php?group_subject_id=' . (int) $relation['id'])) ?>"
                                               class="btn btn-info btn-sm" title="Lihat jadwal">
                                                <i class="bi bi-calendar-week"></i>
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

            <div class="tab-pane fade <?= $tab === 'jadwal' ? 'show active' : '' ?>" id="pane-jadwal" role="tabpanel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div class="text-secondary small">
                        Jadwal melekat pada mata pelajaran. Jadwal bersifat fleksibel dan boleh diubah kapan saja
                        mengikuti situasi group; jadwal nonaktif tidak dihitung pada pemeriksaan bentrok.
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <a href="<?= e(url('admin/group/detail.php?id=' . $id . '&tab=pertemuan')) ?>"
                           class="btn btn-light btn-sm" title="Lihat jurnal pertemuan group">
                            <i class="bi bi-journal-text me-1"></i>Jurnal Pertemuan
                        </a>
                        <?php if ($isAdmin && $isActive && $activeRelations !== []): ?>
                            <a href="<?= e(url('admin/jadwal/tambah.php?group_id=' . $id . '&return=group&return_id=' . $id)) ?>"
                               class="btn btn-primary btn-sm">
                                <i class="bi bi-calendar-plus me-1"></i>Tambah Jadwal
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($schedules === []): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
                        <div class="empty-state-title">Belum ada jadwal</div>
                        <p class="empty-state-text">Tambahkan jadwal belajar untuk setiap mata pelajaran pada group ini.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 40px;">No.</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th>Hari</th>
                                <th>Jam</th>
                                <th>Ruangan</th>
                                <th>Status</th>
                                <th class="text-end" style="width: 150px;">Aksi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($schedules as $index => $schedule): ?>
                                <tr>
                                    <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                                    <td><span class="cell-title"><?= e($schedule['subject_name']) ?></span></td>
                                    <td><?= e($schedule['teacher_name']) ?></td>
                                    <td><span class="cell-title"><?= e(hari_id((int) $schedule['day_of_week'])) ?></span></td>
                                    <td class="text-nowrap">
                                        <?= e(time_label((string) $schedule['start_time'])) ?>–<?= e(time_label((string) $schedule['end_time'])) ?>
                                    </td>
                                    <td><?= e($schedule['room'] ?: '-') ?></td>
                                    <td><?= active_badge((int) $schedule['is_active'] === 1) ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="<?= e(url('admin/jadwal/detail.php?id=' . (int) $schedule['id'])) ?>"
                                               class="btn btn-info btn-sm" title="Detail jadwal">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($isAdmin): ?>
                                                <a href="<?= e(url('admin/jadwal/edit.php?id=' . (int) $schedule['id'])) ?>"
                                                   class="btn btn-warning btn-sm" title="Edit jadwal">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <form method="post" action="<?= e(url('admin/jadwal/proses.php')) ?>"
                                                      data-confirm="<?= (int) $schedule['is_active'] === 1
                                                          ? 'Jadwal akan dinonaktifkan dan tidak dihitung sebagai jadwal aktif.'
                                                          : 'Jadwal akan diaktifkan kembali setelah pemeriksaan bentrok jadwal.' ?>"
                                                      data-confirm-title="Ubah status jadwal"
                                                      data-confirm-button="<?= (int) $schedule['is_active'] === 1 ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                                                      data-confirm-icon="warning" data-submit-once>
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?= (int) $schedule['id'] ?>">
                                                    <input type="hidden" name="return" value="group">
                                                    <input type="hidden" name="return_id" value="<?= $id ?>">
                                                    <button type="submit"
                                                            class="btn btn-sm <?= (int) $schedule['is_active'] === 1 ? 'btn-danger' : 'btn-success' ?>">
                                                        <i class="bi <?= (int) $schedule['is_active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
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

            <div class="tab-pane fade <?= $tab === 'pertemuan' ? 'show active' : '' ?>" id="pane-pertemuan" role="tabpanel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="text-secondary small">
                        Jurnal pertemuan group, diisi admin/petugas atas nama guru pengampu. Target
                        <strong>8 pertemuan</strong> adalah target paket siswa; jadwal tetap fleksibel dan
                        boleh berubah kapan saja. Hanya pertemuan <strong>terlaksana</strong> yang dihitung ke paket.
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="badge text-bg-light"><?= e((string) count($meetingRows)) ?> tercatat</span>
                        <span class="badge text-bg-success"><?= e((string) $meetingDoneCount) ?> terlaksana</span>
                        <span class="badge text-bg-info"><?= e((string) $meetingScheduledCount) ?> terjadwal</span>
                        <a href="<?= e(url('admin/group/absensi.php?group_id=' . $id)) ?>"
                           class="btn btn-light btn-sm" title="Cetak lembar absensi siswa" target="_blank" rel="noopener">
                            <i class="bi bi-printer me-1"></i>Cetak Absensi
                        </a>
                    </div>
                </div>

                <?php if ($journalErrors !== []): ?>
                    <div class="alert alert-danger d-flex gap-2 align-items-start">
                        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                        <div class="small">
                            <?php foreach ($journalErrors as $journalError): ?>
                                <div><?= e($journalError) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$isActive): ?>
                    <div class="alert alert-warning py-2 px-3 small">
                        <i class="bi bi-pause-circle me-1"></i>Group nonaktif. Aktifkan group untuk mencatat pertemuan baru.
                    </div>
                <?php elseif ($journalRelations === []): ?>
                    <div class="alert alert-info py-2 px-3 small">
                        <i class="bi bi-info-circle me-1"></i>Belum ada mata pelajaran aktif. Tambahkan mata pelajaran
                        dan guru terlebih dahulu agar pertemuan dapat dicatat.
                    </div>
                <?php else: ?>
                    <div class="card border mb-3">
                        <div class="card-header bg-white">
                            <h3 class="card-title mb-0"><i class="bi bi-journal-plus me-1"></i>Catat Pertemuan</h3>
                            <div class="card-subtitle">Catatan wajib diisi sebagai jurnal kegiatan belajar.</div>
                        </div>
                        <form method="post" action="<?= e(url('admin/group/pertemuan-proses.php')) ?>"
                              data-submit-once novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="group_id" value="<?= $id ?>">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="pertemuan-mapel" class="form-label">Mata pelajaran &amp; guru</label>
                                        <select class="form-select<?= field_invalid($journalErrors, 'group_subject_id') ?>"
                                                id="pertemuan-mapel" name="group_subject_id" required>
                                            <?php foreach ($journalRelations as $journalRelation): ?>
                                                <option value="<?= $journalRelation['id'] ?>"
                                                        data-start="<?= e(substr($journalRelation['start_time'], 0, 5)) ?>"
                                                        data-end="<?= e(substr($journalRelation['end_time'], 0, 5)) ?>"
                                                    <?= $journalRelationValue === (string) $journalRelation['id'] ? 'selected' : '' ?>>
                                                    <?= e($journalRelation['label']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?= field_error($journalErrors, 'group_subject_id') ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="pertemuan-tanggal" class="form-label">Tanggal pertemuan</label>
                                        <input type="date" class="form-control<?= field_invalid($journalErrors, 'meeting_date') ?>"
                                               id="pertemuan-tanggal" name="meeting_date"
                                               value="<?= e($journalDateValue) ?>" required>
                                        <?= field_error($journalErrors, 'meeting_date') ?>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="pertemuan-mulai" class="form-label">Jam mulai</label>
                                        <input type="time" class="form-control<?= field_invalid($journalErrors, 'start_time') ?>"
                                               id="pertemuan-mulai" name="start_time"
                                               value="<?= e($journalStartValue) ?>" required>
                                        <?= field_error($journalErrors, 'start_time') ?>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="pertemuan-selesai" class="form-label">Jam selesai</label>
                                        <input type="time" class="form-control<?= field_invalid($journalErrors, 'end_time') ?>"
                                               id="pertemuan-selesai" name="end_time"
                                               value="<?= e($journalEndValue) ?>" required>
                                        <?= field_error($journalErrors, 'end_time') ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="pertemuan-status" class="form-label">Status pertemuan</label>
                                        <select class="form-select<?= field_invalid($journalErrors, 'status') ?>"
                                                id="pertemuan-status" name="status" required>
                                            <?php foreach ([Meeting::STATUS_DONE, Meeting::STATUS_SCHEDULED, Meeting::STATUS_CANCELLED] as $journalStatus): ?>
                                                <option value="<?= e($journalStatus) ?>"
                                                    <?= $journalStatusValue === $journalStatus ? 'selected' : '' ?>>
                                                    <?= e(meeting_status_label($journalStatus)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?= field_error($journalErrors, 'status') ?>
                                        <div class="form-text">Status lain (dijadwalkan ulang) dikelola dari menu Realisasi Pertemuan.</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="pertemuan-catatan" class="form-label">Catatan pertemuan / jurnal guru</label>
                                        <textarea class="form-control<?= field_invalid($journalErrors, 'notes') ?>"
                                                  id="pertemuan-catatan" name="notes" rows="3" maxlength="255"
                                                  placeholder="Materi yang dibahas, kondisi kelas, PR, atau kendala..." required><?= e($journalValue('notes')) ?></textarea>
                                        <?= field_error($journalErrors, 'notes') ?>
                                        <div class="form-text">Maksimal 255 karakter dan wajib diisi.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-white d-flex justify-content-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check2-circle me-1"></i>Simpan Catatan Pertemuan
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($meetingRows === []): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-journal-text"></i></div>
                        <div class="empty-state-title">Belum ada catatan pertemuan</div>
                        <p class="empty-state-text">Catat setiap kegiatan belajar agar perkembangan group terdokumentasi.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 40px;">No.</th>
                                <th>Tanggal</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th>Jam</th>
                                <th>Status</th>
                                <th>Catatan</th>
                                <th class="text-center">Siswa</th>
                                <th>Dicatat oleh</th>
                                <th class="text-end" style="width: 90px;">Aksi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($meetingRows as $meetingIndex => $meetingRow): ?>
                                <tr>
                                    <td class="text-secondary"><?= e((string) ($meetingIndex + 1)) ?></td>
                                    <td class="text-nowrap"><?= e(tanggal_id((string) $meetingRow['meeting_date'])) ?></td>
                                    <td><span class="cell-title"><?= e($meetingRow['subject_name']) ?></span></td>
                                    <td><?= e($meetingRow['teacher_name']) ?></td>
                                    <td class="text-nowrap">
                                        <?= e(time_label((string) $meetingRow['start_time'])) ?>–<?= e(time_label((string) $meetingRow['end_time'])) ?>
                                    </td>
                                    <td><?= meeting_status_badge((string) $meetingRow['status']) ?></td>
                                    <td>
                                        <?php if (trim((string) $meetingRow['notes']) !== ''): ?>
                                            <span class="cell-sub d-block" style="max-width: 280px; white-space: normal;">
                                                <?= e($meetingRow['notes']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-secondary">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= e((string) ($meetingRow['affected_count'] ?? 0)) ?></td>
                                    <td><?= e($meetingRow['recorded_by_name'] ?: '-') ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="<?= e(url('admin/realisasi-pertemuan/catat.php?id=' . (int) $meetingRow['id'])) ?>"
                                               class="btn btn-warning btn-sm" title="Kelola pertemuan">
                                                <i class="bi bi-pencil-square"></i>
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

            <div class="tab-pane fade <?= $tab === 'riwayat' ? 'show active' : '' ?>" id="pane-riwayat" role="tabpanel">
                <h3 class="section-mini-title">Riwayat Keanggotaan</h3>
                <?php if ($members === []): ?>
                    <p class="text-secondary small">Belum ada riwayat keanggotaan pada group ini.</p>
                <?php else: ?>
                    <div class="table-responsive mb-4">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Mulai</th>
                                <th>Keluar</th>
                                <th>Alasan / Catatan</th>
                                <th>Diproses oleh</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td>
                                        <span class="cell-title"><?= e($member['name']) ?></span>
                                        <span class="cell-sub font-monospace"><?= e($member['code']) ?></span>
                                    </td>
                                    <td><?= e(tanggal_id((string) $member['start_date'])) ?></td>
                                    <td><?= e($member['end_date'] ? tanggal_id((string) $member['end_date']) : '-') ?></td>
                                    <td><?= e($member['reason'] ?: '-') ?></td>
                                    <td><?= e($member['created_by_name'] ?: '-') ?></td>
                                    <td><?= membership_status_badge($member['end_date'] !== null ? (string) $member['end_date'] : null) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <h3 class="section-mini-title">Log Perubahan Group</h3>
                <?php if ($activities === []): ?>
                    <p class="text-secondary small mb-0">Belum ada aktivitas yang tercatat untuk group ini.</p>
                <?php else: ?>
                    <ul class="timeline-list">
                        <?php foreach ($activities as $activity): ?>
                            <?php $meta = activity_meta((string) $activity['activity']); ?>
                            <li class="timeline-item">
                                <span class="timeline-icon"><i class="bi <?= e($meta['icon']) ?>"></i></span>
                                <div>
                                    <div class="timeline-title">
                                        <?= e($meta['label']) ?>
                                        <span class="badge <?= e($meta['class']) ?> ms-1"><?= e((string) $activity['username']) ?></span>
                                    </div>
                                    <div class="timeline-text"><?= e($activity['description'] ?: '-') ?></div>
                                    <div class="timeline-time"><?= e(tanggal_waktu_id($activity['created_at'] ?? null)) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    const mapel = document.getElementById('pertemuan-mapel');
    const start = document.getElementById('pertemuan-mulai');
    const end = document.getElementById('pertemuan-selesai');
    if (!mapel || !start || !end) {
        return;
    }
    mapel.addEventListener('change', function () {
        const option = mapel.options[mapel.selectedIndex];
        if (!option) {
            return;
        }
        if (option.dataset.start) {
            start.value = option.dataset.start;
        }
        if (option.dataset.end) {
            end.value = option.dataset.end;
        }
    });
})();
</script>

<?php require_once ROOT_PATH . '/includes/footer.php';
