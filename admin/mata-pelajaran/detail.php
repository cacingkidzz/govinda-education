<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$id = get_int('id');
$subject = $id > 0 ? Subject::find($id) : null;
if ($subject === null) {
    abort_404('Mata pelajaran yang Anda cari tidak ditemukan.');
}

$groups = Subject::groups($id);
$teachers = Subject::teachers($id);
$isActive = (int) $subject['is_active'] === 1;
$canManage = is_admin();

$pageTitle = 'Detail Mata Pelajaran';
$pageSubtitle = 'Informasi mapel, guru pengampu, dan group yang menggunakannya.';
$breadcrumbs = [
    ['label' => 'Mata Pelajaran', 'url' => url('admin/mata-pelajaran/index.php')],
    ['label' => (string) $subject['name']],
];
$activeMenu = 'mata-pelajaran';

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body profile-card">
                <div class="subject-emblem"><i class="bi bi-journal-bookmark-fill"></i></div>
                <h2 class="profile-name"><?= e($subject['name']) ?></h2>
                <div class="profile-code font-monospace"><?= e($subject['code']) ?></div>
                <div class="mt-2"><?= active_badge($isActive) ?></div>
                <div class="profile-meta">
                    <span><i class="bi bi-people me-1"></i><?= e((string) count($groups)) ?> group</span>
                    <span><i class="bi bi-person-video3 me-1"></i><?= e((string) count($teachers)) ?> guru</span>
                </div>

                <?php if ($canManage): ?>
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?= e(url('admin/mata-pelajaran/edit.php?id=' . $id)) ?>" class="btn btn-warning">
                            <i class="bi bi-pencil-square me-1"></i>Edit Mata Pelajaran
                        </a>
                        <form method="post" action="<?= e(url('admin/mata-pelajaran/proses.php')) ?>"
                              data-confirm="<?= $isActive
                                  ? 'Mata pelajaran akan dinonaktifkan dan tidak dapat dipilih untuk group baru.'
                                  : 'Mata pelajaran akan diaktifkan kembali dan dapat dipilih untuk group.' ?>"
                              data-confirm-title="Ubah status mata pelajaran"
                              data-confirm-button="<?= $isActive ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                              data-confirm-icon="warning" data-submit-once>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="redirect" value="detail">
                            <button type="submit" class="btn <?= $isActive ? 'btn-danger' : 'btn-success' ?> w-100">
                                <i class="bi <?= $isActive ? 'bi-journal-minus' : 'bi-journal-check' ?> me-1"></i>
                                <?= $isActive ? 'Nonaktifkan Mapel' : 'Aktifkan Mapel' ?>
                            </button>
                        </form>
                        <a href="<?= e(url('admin/mata-pelajaran/index.php')) ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar
                        </a>
                    </div>
                <?php else: ?>
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?= e(url('admin/mata-pelajaran/index.php')) ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Keterangan</h2>
                    <div class="card-subtitle">Deskripsi mata pelajaran.</div>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($subject['description'])): ?>
                    <p class="mb-0 text-secondary"><?= nl2br(e($subject['description'])) ?></p>
                <?php else: ?>
                    <p class="mb-0 text-muted small">Belum ada keterangan untuk mata pelajaran ini.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Informasi Mata Pelajaran</h2>
                    <div class="card-subtitle">Data tercatat pada sistem.</div>
                </div>
                <span class="badge <?= $isActive ? 'text-bg-success' : 'text-bg-secondary' ?>">
                    <i class="bi <?= $isActive ? 'bi-check-circle' : 'bi-pause-circle' ?> me-1"></i>
                    <?= $isActive ? 'Aktif' : 'Nonaktif' ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <dl class="detail-list">
                            <div class="detail-row">
                                <dt>Kode</dt>
                                <dd class="font-monospace"><?= e($subject['code']) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Nama</dt>
                                <dd><?= e($subject['name']) ?></dd>
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
                                <dt>Digunakan group</dt>
                                <dd><?= e((string) count($groups)) ?> group</dd>
                            </div>
                            <div class="detail-row">
                                <dt>Guru pengampu</dt>
                                <dd><?= e((string) count($teachers)) ?> guru</dd>
                            </div>
                            <div class="detail-row">
                                <dt>Terakhir diperbarui</dt>
                                <dd><?= e(tanggal_waktu_id($subject['updated_at'] ?? null)) ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Guru Pengampu</h2>
                    <div class="card-subtitle">Guru yang terhubung dengan mata pelajaran ini.</div>
                </div>
                <span class="badge bg-teal-soft text-teal"><?= e((string) count($teachers)) ?> guru</span>
            </div>
            <div class="card-body">
                <?php if ($teachers === []): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-person-video3"></i></div>
                        <div class="empty-state-title">Belum ada guru pengampu</div>
                        <p class="empty-state-text">
                            <?= $canManage
                                ? 'Hubungkan guru dengan mapel ini melalui form edit guru.'
                                : 'Administrator belum menghubungkan guru dengan mapel ini.' ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="chip-list">
                        <?php foreach ($teachers as $teacher): ?>
                            <a href="<?= e(url('admin/guru/detail.php?id=' . (int) $teacher['id'])) ?>"
                               class="chip <?= (int) $teacher['is_active'] === 1 ? '' : 'chip-muted' ?>">
                                <i class="bi bi-person-badge me-1"></i><?= e($teacher['name']) ?>
                                <?php if ((int) $teacher['is_active'] !== 1): ?>
                                    <span class="chip-note">nonaktif</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Group Menggunakan Mapel</h2>
                    <div class="card-subtitle">Group belajar dengan mata pelajaran ini.</div>
                </div>
                <span class="badge bg-teal-soft text-teal"><?= e((string) count($groups)) ?> group</span>
            </div>
            <div class="card-body p-0">
                <?php if ($groups === []): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-people"></i></div>
                        <div class="empty-state-title">Belum digunakan group</div>
                        <p class="empty-state-text">Modul group belum tersedia pada tahap ini. Data akan tampil otomatis setelah group dibuat.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Group</th>
                                <th>Jenis</th>
                                <th>Guru</th>
                                <th>Anggota</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($groups as $group): ?>
                                <tr>
                                    <td>
                                        <span class="cell-title"><?= e($group['name']) ?></span>
                                        <span class="cell-sub font-monospace"><?= e($group['code']) ?></span>
                                    </td>
                                    <td><?= e($group['group_type'] === 'private' ? 'Private' : 'Group') ?></td>
                                    <td><?= e($group['teacher_name'] ?: '-') ?></td>
                                    <td><?= e((string) $group['member_count']) ?></td>
                                    <td><?= active_badge((int) $group['is_active'] === 1) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="master-note">
    <i class="bi bi-shield-lock"></i>
    <span>Mata pelajaran yang sudah digunakan group hanya dapat dinonaktifkan, tidak dihapus permanen.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
