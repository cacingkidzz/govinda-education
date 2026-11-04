<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$id = get_int('id');
$teacher = $id > 0 ? Teacher::find($id) : null;
if ($teacher === null) {
    abort_404('Data guru yang Anda cari tidak ditemukan.');
}

$subjects = Teacher::subjects($id);
$groups = Teacher::groups($id);
$schedules = Teacher::schedules($id);
$isActive = (int) $teacher['is_active'] === 1;
$canManage = is_admin();

$pageTitle = 'Detail Guru';
$pageSubtitle = 'Profil guru, mata pelajaran yang diampu, dan riwayat mengajar.';
$breadcrumbs = [
    ['label' => 'Data Guru', 'url' => url('admin/guru/index.php')],
    ['label' => (string) $teacher['name']],
];
$activeMenu = 'guru';

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body text-center profile-card">
                <?= photo_tag($teacher['photo'] ?? null, (string) $teacher['name'], 'profile-photo', 124) ?>
                <h2 class="profile-name"><?= e($teacher['name']) ?></h2>
                <div class="profile-code font-monospace"><?= e($teacher['code']) ?></div>
                <div class="mt-2">
                    <?= active_badge($isActive) ?>
                    <span class="badge text-bg-light">
                        <i class="bi bi-gender-ambiguous me-1"></i><?= e(gender_label((string) $teacher['gender'])) ?>
                    </span>
                </div>
                <div class="profile-meta">
                    <?php if (!empty($teacher['education'])): ?>
                        <span><i class="bi bi-mortarboard me-1"></i><?= e($teacher['education']) ?></span>
                    <?php endif; ?>
                    <span><i class="bi bi-journal-bookmark me-1"></i><?= e((string) count($subjects)) ?> mata pelajaran</span>
                    <span><i class="bi bi-people me-1"></i><?= e((string) count($groups)) ?> group diajar</span>
                </div>

                <?php if ($canManage): ?>
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?= e(url('admin/guru/edit.php?id=' . $id)) ?>" class="btn btn-warning">
                            <i class="bi bi-pencil-square me-1"></i>Edit Data Guru
                        </a>
                        <form method="post" action="<?= e(url('admin/guru/proses.php')) ?>"
                              data-confirm="<?= $isActive
                                  ? 'Guru akan dinonaktifkan dan tidak dapat dipilih untuk group baru.'
                                  : 'Guru akan diaktifkan kembali dan dapat dipilih untuk group.' ?>"
                              data-confirm-title="Ubah status guru"
                              data-confirm-button="<?= $isActive ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                              data-confirm-icon="warning" data-submit-once>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="redirect" value="detail">
                            <button type="submit" class="btn <?= $isActive ? 'btn-danger' : 'btn-success' ?> w-100">
                                <i class="bi <?= $isActive ? 'bi-person-dash' : 'bi-person-check' ?> me-1"></i>
                                <?= $isActive ? 'Nonaktifkan Guru' : 'Aktifkan Guru' ?>
                            </button>
                        </form>
                        <a href="<?= e(url('admin/guru/index.php')) ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar
                        </a>
                    </div>
                <?php else: ?>
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?= e(url('admin/guru/index.php')) ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Kontak</h2>
                    <div class="card-subtitle">Informasi komunikasi dengan guru.</div>
                </div>
            </div>
            <div class="card-body">
                <dl class="detail-list">
                    <div class="detail-row">
                        <dt>Nomor HP</dt>
                        <dd>
                            <?php if (!empty($teacher['phone'])): ?>
                                <a href="tel:<?= e(normalize_phone((string) $teacher['phone'])) ?>">
                                    <?= e(phone_display((string) $teacher['phone'])) ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="detail-row">
                        <dt>Email</dt>
                        <dd>
                            <?php if (!empty($teacher['email'])): ?>
                                <a href="mailto:<?= e($teacher['email']) ?>"><?= e($teacher['email']) ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="detail-row">
                        <dt>Alamat</dt>
                        <dd><?= nl2br(e($teacher['address'] ?: '-')) ?></dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Identitas Guru</h2>
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
                                <dt>Kode guru</dt>
                                <dd class="font-monospace"><?= e($teacher['code']) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Nama lengkap</dt>
                                <dd><?= e($teacher['name']) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Jenis kelamin</dt>
                                <dd><?= e(gender_label((string) $teacher['gender'])) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Tempat, tanggal lahir</dt>
                                <dd>
                                    <?= e($teacher['birth_place'] ?: '-') ?>
                                    <?php if (!empty($teacher['birth_date'])): ?>
                                        , <?= e(tanggal_id((string) $teacher['birth_date'])) ?>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="detail-list">
                            <div class="detail-row">
                                <dt>Pendidikan terakhir</dt>
                                <dd><?= e($teacher['education'] ?: '-') ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Terakhir diperbarui</dt>
                                <dd><?= e(tanggal_waktu_id($teacher['updated_at'] ?? null)) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Status</dt>
                                <dd><?= active_badge($isActive) ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Mata Pelajaran Diampu</h2>
                    <div class="card-subtitle">Mapel yang dapat diajarkan oleh guru ini.</div>
                </div>
                <span class="badge bg-teal-soft text-teal"><?= e((string) count($subjects)) ?> mapel</span>
            </div>
            <div class="card-body">
                <?php if ($subjects === []): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-journal-x"></i></div>
                        <div class="empty-state-title">Belum ada mata pelajaran</div>
                        <p class="empty-state-text">
                            <?= $canManage
                                ? 'Atur mata pelajaran yang diampu melalui tombol edit data guru.'
                                : 'Administrator belum mengatur mata pelajaran untuk guru ini.' ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="chip-list">
                        <?php foreach ($subjects as $subject): ?>
                            <a href="<?= e(url('admin/mata-pelajaran/detail.php?id=' . (int) $subject['id'])) ?>"
                               class="chip <?= (int) $subject['is_active'] === 1 ? '' : 'chip-muted' ?>">
                                <i class="bi bi-journal-bookmark me-1"></i><?= e($subject['name']) ?>
                                <?php if ((int) $subject['is_active'] !== 1): ?>
                                    <span class="chip-note">nonaktif</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Group Diajar</h2>
                    <div class="card-subtitle">Group yang menggunakan guru ini sebagai guru utama.</div>
                </div>
                <span class="badge bg-teal-soft text-teal"><?= e((string) count($groups)) ?> group</span>
            </div>
            <div class="card-body p-0">
                <?php if ($groups === []): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-people"></i></div>
                        <div class="empty-state-title">Belum mengajar group</div>
                        <p class="empty-state-text">Modul group belum tersedia pada tahap ini. Data akan tampil otomatis setelah group dibuat.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Group</th>
                                <th>Jenis</th>
                                <th>Mata Pelajaran</th>
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
                                    <td><?= e($group['subject_name'] ?: '-') ?></td>
                                    <td><?= e((string) $group['member_count']) ?> / <?= e((string) $group['capacity']) ?></td>
                                    <td><?= active_badge((int) $group['is_active'] === 1) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Jadwal Mengajar</h2>
                    <div class="card-subtitle">Jadwal aktif yang terhubung dengan group guru.</div>
                </div>
                <span class="badge bg-teal-soft text-teal"><?= e((string) count($schedules)) ?> jadwal</span>
            </div>
            <div class="card-body p-0">
                <?php if ($schedules === []): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-calendar-week"></i></div>
                        <div class="empty-state-title">Belum ada jadwal</div>
                        <p class="empty-state-text">Modul jadwal belum tersedia pada tahap ini. Data akan tampil otomatis setelah jadwal dibuat.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Hari</th>
                                <th>Jam</th>
                                <th>Group</th>
                                <th>Ruangan</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($schedules as $schedule): ?>
                                <tr>
                                    <td><?= e(hari_id((int) $schedule['day_of_week'])) ?></td>
                                    <td><?= e(substr((string) $schedule['start_time'], 0, 5)) ?>–<?= e(substr((string) $schedule['end_time'], 0, 5)) ?></td>
                                    <td>
                                        <span class="cell-title"><?= e($schedule['group_name']) ?></span>
                                        <span class="cell-sub"><?= e($schedule['subject_name'] ?: '-') ?></span>
                                    </td>
                                    <td><?= e($schedule['room'] ?: '-') ?></td>
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
    <span>Guru dengan riwayat group atau jadwal hanya dapat dinonaktifkan, tidak dihapus permanen.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
