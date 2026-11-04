<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$currentRole = (string) (current_user()['role'] ?? '');
$isAdmin = $currentRole === 'admin';

$generalEnabled = StudentSelfService::generalEnabled();
$generalToken = StudentSelfService::generalToken();
$generalLink = StudentSelfService::generalLink();
$generalRotatedAt = StudentSelfService::generalRotatedAt();

$students = Student::all();
$activeCount = 0;
$tokenCount = 0;
$completeCount = 0;
foreach ($students as $row) {
    if ((int) $row['is_active'] === 1) {
        $activeCount++;
    }
    if (StudentSelfService::isToken(trim((string) ($row['public_token'] ?? '')))) {
        $tokenCount++;
    }
    if (StudentSelfService::completeness($row)['complete']) {
        $completeCount++;
    }
}
$studentCount = count($students);

$pageTitle = 'Pendaftaran Mandiri';
$pageSubtitle = 'Bagikan tautan agar siswa mengisi dan melengkapi datanya sendiri.';
$breadcrumbs = [
    ['label' => 'Data Siswa', 'url' => url('admin/siswa/index.php')],
    ['label' => 'Pendaftaran Mandiri'],
];
$activeMenu = 'pendaftaran';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-lg-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-secondary small mb-1">Siswa terdaftar</div>
                <div class="h3 mb-0"><?= e(number_format($studentCount, 0, ',', '.')) ?></div>
                <div class="small text-secondary mt-1"><?= e(number_format($activeCount, 0, ',', '.')) ?> aktif</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-secondary small mb-1">Sudah punya tautan</div>
                <div class="h3 mb-0"><?= e(number_format($tokenCount, 0, ',', '.')) ?></div>
                <div class="small text-secondary mt-1">tautan pribadi siswa</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-secondary small mb-1">Data pribadi lengkap</div>
                <div class="h3 mb-0"><?= e(number_format($completeCount, 0, ',', '.')) ?></div>
                <div class="small text-secondary mt-1">dari <?= e(number_format($studentCount, 0, ',', '.')) ?> siswa</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-secondary small mb-1">Pendaftaran siswa baru</div>
                <div class="mb-1">
                    <?= $generalEnabled
                        ? '<span class="badge text-bg-success"><i class="bi bi-unlock me-1"></i>Dibuka</span>'
                        : '<span class="badge text-bg-secondary"><i class="bi bi-lock me-1"></i>Ditutup</span>' ?>
                </div>
                <div class="small text-secondary mt-1">
                    <?= $generalToken !== '' ? 'Tautan umum tersedia' : 'Tautan umum belum dibuat' ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <div>
            <h2 class="card-title">Pendaftaran Siswa Baru</h2>
            <div class="card-subtitle">
                Satu tautan umum untuk calon siswa baru. Dapat dibuka atau ditutup kapan saja.
            </div>
        </div>
        <?= $generalEnabled
            ? '<span class="badge text-bg-success"><i class="bi bi-broadcast me-1"></i>Aktif</span>'
            : '<span class="badge text-bg-secondary"><i class="bi bi-pause-circle me-1"></i>Nonaktif</span>' ?>
    </div>
    <div class="card-body">
        <?php if ($generalToken !== ''): ?>
            <label for="general-link" class="form-label">Tautan pendaftaran umum</label>
            <div class="input-group mb-2">
                <input type="text" class="form-control font-monospace" id="general-link"
                       value="<?= e($generalLink) ?>" readonly>
                <button type="button" class="btn btn-outline-primary" data-copy-target="#general-link"
                        title="Salin tautan">
                    <i class="bi bi-clipboard"></i><span class="d-none d-sm-inline ms-1">Salin</span>
                </button>
                <a href="<?= e($generalLink) ?>" class="btn btn-light" target="_blank" rel="noopener noreferrer"
                   title="Buka halaman pendaftaran">
                    <i class="bi bi-box-arrow-up-right"></i>
                </a>
            </div>
            <div class="form-text mb-3">
                <?php if ($generalEnabled): ?>
                    Tautan ini dapat dipakai calon siswa baru. Setiap pendaftaran langsung tersimpan.
                <?php else: ?>
                    Pendaftaran sedang ditutup. Tautan tidak dapat dipakai sampai diaktifkan kembali.
                <?php endif; ?>
                <?php if ($generalRotatedAt !== ''): ?>
                    &middot; Terakhir diganti <?= e(tanggal_waktu_id($generalRotatedAt)) ?>.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info d-flex gap-2 align-items-start mb-3" role="status">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    Tautan pendaftaran umum belum dibuat. Buat tautan terlebih dahulu, lalu aktifkan
                    pendaftaran agar calon siswa dapat mendaftar sendiri.
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($generalToken === ''): ?>
                    <form method="post" action="<?= e(url('admin/siswa/proses.php')) ?>" data-submit-once>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="registration_generate">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-link-45deg me-1"></i>Buat Tautan Pendaftaran
                        </button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(url('admin/siswa/proses.php')) ?>" data-submit-once
                          <?= $generalEnabled ? '' : 'data-confirm="Tautan umum akan aktif kembali dan dapat dipakai calon siswa baru."' ?>
                          data-confirm-title="Buka pendaftaran siswa baru"
                          data-confirm-button="Ya, buka pendaftaran" data-confirm-icon="question">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="registration_toggle">
                        <input type="hidden" name="enabled" value="<?= $generalEnabled ? '0' : '1' ?>">
                        <button type="submit" class="btn <?= $generalEnabled ? 'btn-warning' : 'btn-success' ?>">
                            <i class="bi <?= $generalEnabled ? 'bi-lock' : 'bi-unlock' ?> me-1"></i>
                            <?= $generalEnabled ? 'Tutup Pendaftaran' : 'Buka Pendaftaran' ?>
                        </button>
                    </form>
                    <form method="post" action="<?= e(url('admin/siswa/proses.php')) ?>" data-submit-once
                          data-confirm="Tautan umum lama akan langsung tidak berlaku. Calon siswa harus memakai tautan baru."
                          data-confirm-title="Ganti tautan pendaftaran"
                          data-confirm-button="Ya, ganti tautan" data-confirm-icon="warning">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="registration_rotate">
                        <button type="submit" class="btn btn-light">
                            <i class="bi bi-arrow-repeat me-1"></i>Ganti Tautan
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="text-secondary small">
                <i class="bi bi-info-circle me-1"></i>Hanya admin yang dapat membuka, menutup, atau mengganti tautan umum.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Tautan Pribadi Siswa</h2>
            <div class="card-subtitle">
                Bagikan satu tautan per siswa. Tautan bersifat rahasia dan hanya dapat dipakai siswa tersebut.
            </div>
        </div>
        <form method="post" action="<?= e(url('admin/siswa/proses.php')) ?>" data-submit-once
              data-confirm="Tautan akan dibuat untuk semua siswa aktif yang belum memilikinya."
              data-confirm-title="Buat tautan untuk semua siswa"
              data-confirm-button="Ya, buat tautan" data-confirm-icon="question">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="self_link_all">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-link-45deg me-1"></i>Buat Semua Tautan
            </button>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if ($students === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-people"></i></div>
                <div class="empty-state-title">Belum ada data siswa</div>
                <p class="empty-state-text">Tambahkan siswa terlebih dahulu, lalu buat tautan pendaftaran mandiri.</p>
                <a href="<?= e(url('admin/siswa/tambah.php')) ?>" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i>Tambah Siswa
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th style="width: 40px;">No.</th>
                        <th>Kode</th>
                        <th>Nama Siswa</th>
                        <th style="width: 110px;">Status</th>
                        <th style="width: 150px;">Kelengkapan Data</th>
                        <th style="width: 130px;">Tautan</th>
                        <th style="width: 170px;">Diperbarui Mandiri</th>
                        <th class="no-sort" style="width: 150px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $index => $row): ?>
                        <?php
                        $rowToken = trim((string) ($row['public_token'] ?? ''));
                        $rowLink = StudentSelfService::studentLink($row);
                        $rowComplete = StudentSelfService::completeness($row);
                        $detailUrl = url('admin/siswa/detail.php?id=' . (int) $row['id']);
                        ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td><span class="cell-title font-monospace"><?= e($row['code']) ?></span></td>
                            <td>
                                <a href="<?= e($detailUrl) ?>" class="cell-title link-dark text-decoration-none">
                                    <?= e($row['name']) ?>
                                </a>
                                <span class="cell-sub"><?= e($row['school_class'] ?: 'Kelas belum diisi') ?></span>
                            </td>
                            <td><?= active_badge((int) $row['is_active'] === 1) ?></td>
                            <td>
                                <?= profile_completion_badge($row) ?>
                                <?php if (!$rowComplete['complete'] && $rowComplete['missing'] !== []): ?>
                                    <span class="cell-sub" title="<?= e(implode(', ', $rowComplete['missing'])) ?>">
                                        <?= e((string) count($rowComplete['missing'])) ?> data belum diisi
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (StudentSelfService::isToken($rowToken)): ?>
                                    <span class="badge text-bg-success"><i class="bi bi-check2 me-1"></i>Siap</span>
                                <?php else: ?>
                                    <span class="badge text-bg-light text-secondary">Belum dibuat</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['self_updated_at'])): ?>
                                    <span class="cell-title"><?= e(tanggal_waktu_id((string) $row['self_updated_at'])) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">Belum pernah</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <?php if (StudentSelfService::isToken($rowToken)): ?>
                                        <button type="button" class="btn btn-primary btn-sm" title="Salin tautan"
                                                data-copy="<?= e($rowLink) ?>">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                        <a href="<?= e($rowLink) ?>" class="btn btn-info btn-sm" target="_blank"
                                           rel="noopener noreferrer" title="Buka formulir siswa">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                        <form method="post" action="<?= e(url('admin/siswa/proses.php')) ?>"
                                              class="d-inline" data-submit-once
                                              data-confirm="Tautan lama akan langsung tidak berlaku. Bagikan tautan baru setelah ini."
                                              data-confirm-title="Ganti tautan siswa"
                                              data-confirm-button="Ya, ganti tautan" data-confirm-icon="warning">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="self_link_rotate">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn btn-warning btn-sm" title="Ganti tautan">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(url('admin/siswa/proses.php')) ?>"
                                              class="d-inline" data-submit-once>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="self_link">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="bi bi-link-45deg me-1"></i>Buat
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="<?= e($detailUrl) ?>" class="btn btn-light btn-sm" title="Detail siswa">
                                        <i class="bi bi-eye"></i>
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

<div class="master-note">
    <i class="bi bi-shield-lock"></i>
    <span>Tautan pribadi bersifat rahasia. Jangan membagikan tautan satu siswa kepada siswa lain.
        Ganti tautan bila ada kemungkinan tautan lama tersebar.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
