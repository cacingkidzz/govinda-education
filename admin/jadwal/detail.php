<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$id = get_int('id');
$schedule = $id > 0 ? Schedule::find($id) : null;
if ($schedule === null) {
    abort_404('Data jadwal yang Anda cari tidak ditemukan.');
}

$groupSchedules = Schedule::forGroup((int) $schedule['group_id'], false);
$isAdmin = is_admin();
$isActive = (int) $schedule['is_active'] === 1;

$pageTitle = 'Detail Jadwal';
$pageSubtitle = 'Informasi jadwal, group, guru, dan ruangan.';
$breadcrumbs = [
    ['label' => 'Jadwal Belajar', 'url' => url('admin/jadwal/index.php')],
    ['label' => hari_id((int) $schedule['day_of_week']) . ', '
        . time_label((string) $schedule['start_time']) . '–' . time_label((string) $schedule['end_time'])],
];
$activeMenu = 'jadwal';

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= e($schedule['group_name']) ?></h2>
                    <div class="card-subtitle font-monospace"><?= e($schedule['group_code']) ?></div>
                </div>
                <?= active_badge($isActive) ?>
            </div>
            <div class="card-body">
                <div class="schedule-hero mb-3">
                    <div class="schedule-hero-day"><?= e(hari_id((int) $schedule['day_of_week'])) ?></div>
                    <div class="schedule-hero-time">
                        <?= e(time_label((string) $schedule['start_time'])) ?>
                        <span>–</span>
                        <?= e(time_label((string) $schedule['end_time'])) ?>
                    </div>
                    <div class="schedule-hero-room">
                        <i class="bi bi-geo-alt me-1"></i><?= e($schedule['room'] ?: 'Ruangan belum ditentukan') ?>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <dl class="detail-list">
                            <div class="detail-row">
                                <dt>Group</dt>
                                <dd>
                                    <a href="<?= e(url('admin/group/detail.php?id=' . (int) $schedule['group_id'] . '&tab=jadwal')) ?>">
                                        <?= e($schedule['group_name']) ?>
                                    </a>
                                </dd>
                            </div>
                            <div class="detail-row">
                                <dt>Jenis group</dt>
                                <dd><?= group_type_badge((string) $schedule['group_type']) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Guru</dt>
                                <dd>
                                    <a href="<?= e(url('admin/guru/detail.php?id=' . (int) $schedule['teacher_id'])) ?>">
                                        <?= e($schedule['teacher_name']) ?>
                                    </a>
                                </dd>
                            </div>
                            <div class="detail-row">
                                <dt>Mata pelajaran</dt>
                                <dd><?= e($schedule['subject_name']) ?></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="detail-list">
                            <div class="detail-row">
                                <dt>Hari</dt>
                                <dd><?= e(hari_id((int) $schedule['day_of_week'])) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Jam</dt>
                                <dd>
                                    <?= e(time_label((string) $schedule['start_time'])) ?>–<?= e(time_label((string) $schedule['end_time'])) ?>
                                </dd>
                            </div>
                            <div class="detail-row">
                                <dt>Ruangan</dt>
                                <dd><?= e($schedule['room'] ?: '-') ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Status</dt>
                                <dd><?= active_badge($isActive) ?></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="col-12">
                        <dl class="detail-list">
                            <div class="detail-row">
                                <dt>Keterangan</dt>
                                <dd><?= nl2br(e($schedule['notes'] ?: '-')) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Dibuat</dt>
                                <dd><?= e(tanggal_waktu_id($schedule['created_at'] ?? null)) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Diperbarui</dt>
                                <dd><?= e(tanggal_waktu_id($schedule['updated_at'] ?? null)) ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-2">
                <a href="<?= e(url('admin/jadwal/index.php')) ?>" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Kembali
                </a>
                <?php if ($isAdmin): ?>
                    <a href="<?= e(url('admin/jadwal/edit.php?id=' . $id . '&return=index')) ?>" class="btn btn-warning btn-sm">
                        <i class="bi bi-pencil-square me-1"></i>Edit Jadwal
                    </a>
                    <form method="post" action="<?= e(url('admin/jadwal/proses.php')) ?>"
                          data-confirm="<?= $isActive
                              ? 'Jadwal akan dinonaktifkan dan tidak dihitung sebagai jadwal aktif.'
                              : 'Jadwal akan diaktifkan kembali setelah pemeriksaan bentrok.' ?>"
                          data-confirm-title="Ubah status jadwal"
                          data-confirm-button="<?= $isActive ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                          data-confirm-icon="warning" data-submit-once>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="return" value="index">
                        <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-danger' : 'btn-success' ?>">
                            <i class="bi <?= $isActive ? 'bi-pause-circle' : 'bi-play-circle' ?> me-1"></i>
                            <?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </button>
                    </form>
                    <?php if (!$isActive): ?>
                        <form method="post" action="<?= e(url('admin/jadwal/proses.php')) ?>"
                              data-confirm="Jadwal nonaktif ini akan dihapus permanen. Tindakan ini tidak dapat dibatalkan."
                              data-confirm-title="Hapus jadwal"
                              data-confirm-button="Ya, hapus"
                              data-confirm-icon="warning" data-submit-once>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <button type="submit" class="btn btn-dark btn-sm">
                                <i class="bi bi-trash me-1"></i>Hapus Permanen
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Jadwal Group Ini</h2>
                    <div class="card-subtitle">Seluruh jadwal, termasuk yang nonaktif.</div>
                </div>
                <span class="badge bg-teal-soft text-teal"><?= e((string) count($groupSchedules)) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if ($groupSchedules === []): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
                        <div class="empty-state-title">Belum ada jadwal lain</div>
                    </div>
                <?php else: ?>
                    <ul class="schedule-list">
                        <?php foreach ($groupSchedules as $row): ?>
                            <li class="schedule-list-item <?= (int) $row['id'] === $id ? 'is-current' : '' ?>">
                                <a href="<?= e(url('admin/jadwal/detail.php?id=' . (int) $row['id'])) ?>">
                                    <span class="schedule-day"><?= e(hari_id((int) $row['day_of_week'])) ?></span>
                                    <span class="schedule-time">
                                        <?= e(time_label((string) $row['start_time'])) ?>–<?= e(time_label((string) $row['end_time'])) ?>
                                    </span>
                                    <span class="schedule-room"><?= e($row['room'] ?: 'Tanpa ruangan') ?></span>
                                    <?= active_badge((int) $row['is_active'] === 1) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
