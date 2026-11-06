<?php
declare(strict_types=1);

/**
 * Lembar absensi siswa per group — siap cetak A4 landscape.
 *
 * Halaman ini mencetak formulir kosong: 12 kolom pertemuan dengan tanggal
 * kosong, baris siswa, kolom jumlah hadir, dan kolom paraf siswa. Tanggal
 * dan tanda kehadiran diisi manual saat kegiatan berlangsung.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$groupId = get_int('group_id');
if ($groupId <= 0) {
    $groupId = get_int('id');
}
$group = $groupId > 0 ? Group::find($groupId) : null;
if ($group === null) {
    abort_404('Group tidak ditemukan.');
}

$members = Group::members($groupId, true);
$relations = GroupSubject::activeForGroup($groupId);

$subjectNames = [];
$teacherNames = [];
foreach ($relations as $relation) {
    $subject = trim((string) ($relation['subject_name'] ?? ''));
    $teacher = trim((string) ($relation['teacher_name'] ?? ''));
    if ($subject !== '') {
        $subjectNames[$subject] = true;
    }
    if ($teacher !== '') {
        $teacherNames[$teacher] = true;
    }
}
$subjectLabel = $subjectNames === [] ? '-' : implode(', ', array_keys($subjectNames));
$teacherLabel = $teacherNames === [] ? '-' : implode(', ', array_keys($teacherNames));

$meetingColumns = 12;
$minimumRows = 10;
$blankRows = max(0, $minimumRows - count($members));

$orgName = Profile::name();
$orgTagline = Profile::tagline();
$orgAddress = Profile::address();
$orgPhone = Profile::phone();
$orgEmail = Profile::email();
$orgWebsite = Profile::websiteLabel();
$logo = Profile::logoUrl();
$logoClass = Profile::logoOnLightClass();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Absensi <?= e($group['name']) ?> — <?= e($orgName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/print-absensi.css')) ?>">
</head>
<body>

<div class="absensi-toolbar">
    <a class="btn btn-light" href="<?= e(url('admin/group/detail.php?id=' . $groupId . '&tab=pertemuan')) ?>">
        Kembali
    </a>
    <button type="button" class="btn btn-primary" onclick="window.print()">
        Cetak / Simpan PDF (A4 Landscape)
    </button>
</div>

<div class="absensi-sheet">
    <div class="absensi-head">
        <div class="absensi-brand">
            <img src="<?= e($logo) ?>" alt="Logo <?= e($orgName) ?>" class="absensi-logo <?= e($logoClass) ?>">
            <div>
                <p class="absensi-brand-name"><?= e($orgName) ?></p>
                <?php if ($orgTagline !== ''): ?>
                    <p class="absensi-brand-tagline"><?= e($orgTagline) ?></p>
                <?php endif; ?>
                <div class="absensi-brand-meta">
                    <?php if ($orgAddress !== ''): ?><?= nl2br(e($orgAddress)) ?><br><?php endif; ?>
                    <?php if ($orgPhone !== ''): ?>Telp. <?= e($orgPhone) ?><?php endif; ?>
                    <?php if ($orgPhone !== '' && ($orgEmail !== '' || $orgWebsite !== '')): ?> &middot; <?php endif; ?>
                    <?php if ($orgEmail !== ''): ?><?= e($orgEmail) ?><?php endif; ?>
                    <?php if ($orgEmail !== '' && $orgWebsite !== ''): ?> &middot; <?php endif; ?>
                    <?php if ($orgWebsite !== ''): ?><?= e($orgWebsite) ?><?php endif; ?>
                    <?php if ($orgAddress === '' && $orgPhone === '' && $orgEmail === '' && $orgWebsite === ''): ?>
                        Lembar absensi kegiatan belajar.
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="absensi-title">
            <h1>Daftar Hadir Siswa</h1>
            <div class="absensi-subtitle">Absensi <?= e((string) $meetingColumns) ?> Pertemuan</div>
        </div>
    </div>

    <div class="absensi-info">
        <div class="info-item">
            <span class="info-label">Kelompok</span>
            <span class="info-value"><?= e($group['name']) ?> (<?= e($group['code']) ?>)</span>
        </div>
        <div class="info-item">
            <span class="info-label">Jenis</span>
            <span class="info-value"><?= e(package_type_label((string) $group['group_type'])) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Jumlah Siswa</span>
            <span class="info-value"><?= e((string) count($members)) ?> siswa</span>
        </div>
        <div class="info-item">
            <span class="info-label">Mata Pelajaran</span>
            <span class="info-value"><?= e($subjectLabel) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Guru</span>
            <span class="info-value"><?= e($teacherLabel) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Periode</span>
            <span class="info-value">&hellip;&hellip;&hellip;&hellip;&hellip;&hellip; s.d. &hellip;&hellip;&hellip;&hellip;&hellip;&hellip;</span>
        </div>
    </div>

    <table class="absen-table">
        <thead>
        <tr>
            <th rowspan="2" class="is-no col-no">No</th>
            <th rowspan="2" class="is-name col-name">Nama Siswa</th>
            <th rowspan="2" class="is-class col-class">Kelas</th>
            <?php for ($column = 1; $column <= $meetingColumns; $column++): ?>
                <th scope="col">Pertemuan <?= e((string) $column) ?></th>
            <?php endfor; ?>
            <th rowspan="2" class="is-total col-total">Jumlah<br>Hadir</th>
            <th rowspan="2" class="is-sign col-sign">Paraf<br>Siswa</th>
        </tr>
        <tr>
            <?php for ($column = 1; $column <= $meetingColumns; $column++): ?>
                <th class="date-head">Tanggal<span class="date-line"></span></th>
            <?php endfor; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($members as $index => $member): ?>
            <tr>
                <td class="cell-no"><?= e((string) ($index + 1)) ?></td>
                <td class="cell-name"><?= e((string) $member['name']) ?></td>
                <td class="cell-class"><?= e((string) ($member['school_class'] ?: '-')) ?></td>
                <?php for ($column = 1; $column <= $meetingColumns; $column++): ?>
                    <td class="cell-mark"></td>
                <?php endfor; ?>
                <td class="cell-total"></td>
                <td class="cell-sign"></td>
            </tr>
        <?php endforeach; ?>

        <?php for ($row = 0; $row < $blankRows; $row++): ?>
            <tr>
                <td class="cell-no"><?= e((string) (count($members) + $row + 1)) ?></td>
                <td class="cell-name">&nbsp;</td>
                <td class="cell-class">&nbsp;</td>
                <?php for ($column = 1; $column <= $meetingColumns; $column++): ?>
                    <td class="cell-mark"></td>
                <?php endfor; ?>
                <td class="cell-total"></td>
                <td class="cell-sign"></td>
            </tr>
        <?php endfor; ?>
        </tbody>
    </table>

    <div class="absensi-legend">
        <span><strong>Keterangan:</strong></span>
        <span><strong>H</strong> = Hadir</span>
        <span><strong>S</strong> = Sakit</span>
        <span><strong>I</strong> = Izin</span>
        <span><strong>A</strong> = Alfa</span>
        <span>Tanggal pertemuan, tanda kehadiran, dan paraf diisi manual oleh guru dan siswa.</span>
    </div>

    <div class="absensi-sign">
        <div class="absensi-sign-box">
            <div>&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;, &hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;</div>
            <div class="sign-space"></div>
            <div class="sign-name"><?= e($teacherLabel) ?></div>
            <div class="sign-role">Guru Pengampu</div>
        </div>
        <div class="absensi-sign-box is-right">
            <div>Mengetahui,</div>
            <div class="sign-space"></div>
            <div class="sign-name"><?= e($orgName) ?></div>
            <div class="sign-role">Pimpinan / Admin</div>
        </div>
    </div>

    <div class="absensi-footer">
        <span>Dicetak <?= e(tanggal_waktu_id(date('Y-m-d H:i:s'))) ?></span>
        <span>Dokumen <?= e($orgName) ?> &middot; <?= e($group['code']) ?></span>
    </div>
</div>

</body>
</html>
