<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$membershipId = get_int('membership_id');
$membership = $membershipId > 0 ? Membership::find($membershipId) : null;
if ($membership === null) {
    abort_404('Data keanggotaan tidak ditemukan.');
}
if ($membership['end_date'] !== null) {
    flash('warning', 'Keanggotaan siswa "' . $membership['student_name'] . '" sudah berstatus selesai.');
    redirect('admin/group/detail.php?id=' . (int) $membership['group_id'] . '&tab=anggota');
}

$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];
$v = static fn (string $key, string $default = ''): string => old_value($old, [], $key, $default);
$returnTo = get_string('return') === 'student' ? 'student' : 'group';

$pageTitle = 'Akhiri Keanggotaan';
$pageSubtitle = 'Tutup keanggotaan siswa tanpa menghapus riwayat group.';
$breadcrumbs = [
    ['label' => 'Group Belajar', 'url' => url('admin/group/index.php')],
    ['label' => (string) $membership['group_name'], 'url' => url('admin/group/detail.php?id=' . (int) $membership['group_id'] . '&tab=anggota')],
    ['label' => 'Akhiri Keanggotaan'],
];
$activeMenu = 'anggota-group';

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Form Akhiri Keanggotaan</h2>
                    <div class="card-subtitle">Record keanggotaan tetap tersimpan dan ditandai selesai.</div>
                </div>
                <?= membership_status_badge(null) ?>
            </div>
            <div class="card-body">
                <?php if (!empty($errors['form'])): ?>
                    <div class="alert alert-danger py-2 px-3 small">
                        <i class="bi bi-x-circle-fill me-1"></i><?= e($errors['form']) ?>
                    </div>
                <?php endif; ?>
                <div class="alert alert-info py-2 px-3 small">
                    <i class="bi bi-info-circle me-1"></i>Mengakhiri keanggotaan tidak menghapus data group,
                    tidak mengubah invoice yang sudah diterbitkan, dan tidak menghapus riwayat siswa.
                </div>

                <form method="post" action="<?= e(url('admin/group/anggota-proses.php')) ?>"
                      data-confirm="Keanggotaan siswa pada group ini akan ditutup dan tidak dapat diaktifkan kembali. Buat keanggotaan baru bila siswa kembali bergabung."
                      data-confirm-title="Akhiri keanggotaan"
                      data-confirm-button="Ya, akhiri"
                      data-confirm-icon="warning" data-submit-once novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="end">
                    <input type="hidden" name="membership_id" value="<?= $membershipId ?>">
                    <input type="hidden" name="return" value="<?= e($returnTo) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">Tanggal keluar <span class="text-danger">*</span></label>
                            <input type="date" class="form-control<?= field_invalid($errors, 'end_date') ?>"
                                   id="end_date" name="end_date"
                                   value="<?= e($v('end_date', date('Y-m-d'))) ?>"
                                   min="<?= e((string) $membership['start_date']) ?>" required>
                            <?= field_error($errors, 'end_date') ?>
                            <div class="form-text">Tidak boleh sebelum tanggal masuk
                                (<?= e(tanggal_id((string) $membership['start_date'])) ?>).</div>
                        </div>
                        <div class="col-12">
                            <label for="note" class="form-label">Keterangan</label>
                            <textarea class="form-control<?= field_invalid($errors, 'note') ?>" id="note" name="note"
                                      rows="3" maxlength="255"
                                      placeholder="Contoh: siswa berhenti, lulus, atau alasan lain"><?= e($v('note')) ?></textarea>
                            <?= field_error($errors, 'note') ?>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-person-dash me-1"></i>Akhiri Keanggotaan
                        </button>
                        <a href="<?= e(url('admin/group/detail.php?id=' . (int) $membership['group_id'] . '&tab=anggota')) ?>"
                           class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Data Keanggotaan</h2>
                    <div class="card-subtitle">Periksa sebelum mengakhiri.</div>
                </div>
            </div>
            <div class="card-body">
                <div class="info-tile mb-3">
                    <?= photo_tag($membership['photo'] ?? null, (string) $membership['student_name'], 'avatar-photo', 46) ?>
                    <div>
                        <div class="info-tile-title"><?= e($membership['student_name']) ?></div>
                        <div class="info-tile-sub font-monospace"><?= e($membership['student_code']) ?></div>
                        <div class="info-tile-sub"><?= e($membership['school_class'] ?: 'Kelas belum diisi') ?></div>
                    </div>
                </div>
                <dl class="detail-list">
                    <div class="detail-row">
                        <dt>Group</dt>
                        <dd><?= e($membership['group_name']) ?> (<?= e($membership['group_code']) ?>)</dd>
                    </div>
                    <div class="detail-row">
                        <dt>Jenis</dt>
                        <dd><?= group_type_badge((string) $membership['group_type']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Guru</dt>
                        <dd><?= e($membership['teacher_names'] ?: '-') ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Mapel</dt>
                        <dd><?= e($membership['subject_names'] ?: '-') ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Tanggal masuk</dt>
                        <dd><?= e(tanggal_id((string) $membership['start_date'])) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Catatan</dt>
                        <dd><?= e($membership['reason'] ?: '-') ?></dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
