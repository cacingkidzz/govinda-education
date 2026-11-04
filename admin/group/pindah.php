<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$studentId = get_int('student_id');
$student = $studentId > 0 ? Student::find($studentId) : null;
if ($student === null) {
    abort_404('Data siswa tidak ditemukan.');
}

$membership = Membership::activeForStudent($studentId);
if ($membership === null) {
    flash('warning', 'Siswa "' . $student['name'] . '" belum memiliki group aktif sehingga belum dapat dipindahkan.');
    redirect('admin/siswa/detail.php?id=' . $studentId);
}

$destinations = array_values(array_filter(
    Group::activeOptions(),
    static fn (array $group): bool => (int) $group['id'] !== (int) $membership['group_id']
));

$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];
$v = static fn (string $key, string $default = ''): string => old_value($old, [], $key, $default);

$pendingPackage = StudentPackage::pendingForMembership((int) $membership['membership_id']);
$pendingMeetings = 0;
if ($pendingPackage !== null) {
    $pendingMeetings = (int) $pendingPackage['completed_meetings'];
}

$pageTitle = 'Pindah Group';
$pageSubtitle = 'Pindahkan siswa ke group baru tanpa menghilangkan riwayat keanggotaan lama.';
$breadcrumbs = [
    ['label' => 'Data Siswa', 'url' => url('admin/siswa/index.php')],
    ['label' => (string) $student['name'], 'url' => url('admin/siswa/detail.php?id=' . $studentId)],
    ['label' => 'Pindah Group'],
];
$activeMenu = 'anggota-group';
$pageStyles = ['vendor/select2/select2.min.css', 'vendor/select2/select2-bootstrap-5-theme.min.css'];
$pageScripts = ['vendor/select2/select2.min.js'];

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Form Perpindahan</h2>
                    <div class="card-subtitle">Group asal terisi otomatis dari keanggotaan aktif siswa.</div>
                </div>
                <span class="badge text-bg-warning"><i class="bi bi-arrow-left-right me-1"></i>Pindah</span>
            </div>
            <div class="card-body">
                <?php if (!empty($errors['form'])): ?>
                    <div class="alert alert-danger py-2 px-3 small">
                        <i class="bi bi-x-circle-fill me-1"></i><?= e($errors['form']) ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= e(url('admin/group/anggota-proses.php')) ?>"
                      data-confirm="Keanggotaan group lama akan ditutup dan keanggotaan group baru dibuat. Riwayat lama tetap tersimpan."
                      data-confirm-title="Konfirmasi pindah group"
                      data-confirm-button="Ya, pindahkan"
                      data-confirm-icon="question" data-submit-once novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="transfer">
                    <input type="hidden" name="student_id" value="<?= $studentId ?>">

                    <div class="row g-3">
                        <?php if ($pendingPackage !== null): ?>
                            <div class="col-12">
                                <div class="alert alert-warning">
                                    <div class="fw-semibold mb-1">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                        Paket belajar belum tuntas
                                    </div>
                                    <div class="small">
                                        Siswa masih memiliki <strong>Paket
                                        <?= e((string) $pendingPackage['package_number']) ?></strong> pada group asal
                                        dengan progres <strong><?= e((string) $pendingMeetings) ?>/<?= e((string) $pendingPackage['target_meetings']) ?></strong>
                                        pertemuan (<?= package_status_badge((string) $pendingPackage['status']) ?>).
                                        Jika dipindahkan, paket lama ditutup dengan alasan perpindahan,
                                        sisa pertemuan <strong>tidak dipindahkan</strong>, dan paket baru dibuat
                                        untuk group tujuan.
                                    </div>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input<?= field_invalid($errors, 'confirm_package') ?>"
                                           type="checkbox" value="1" id="confirm_package_close"
                                           name="confirm_package_close" required
                                        <?= $v('confirm_package_close') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="confirm_package_close">
                                        Saya memahami paket lama akan ditutup dan menyetujui penyesuaian ini.
                                    </label>
                                    <?= field_error($errors, 'confirm_package') ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label for="from_group" class="form-label">Group asal</label>
                            <input type="text" class="form-control" id="from_group"
                                   value="<?= e($membership['group_name']) ?> (<?= e($membership['group_code']) ?>)"
                                   readonly>
                            <div class="form-text">Otomatis dari keanggotaan aktif.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="to_group_id" class="form-label">Group tujuan <span class="text-danger">*</span></label>
                            <select class="form-select js-select2<?= field_invalid($errors, 'to_group_id') ?>"
                                    id="to_group_id" name="to_group_id" required data-placeholder="Pilih group tujuan">
                                <option value="">Pilih group tujuan</option>
                                <?php foreach ($destinations as $destination): ?>
                                    <?php
                                    $limit = Group::capacityLimit($destination);
                                    $count = (int) $destination['member_count'];
                                    $isFull = $count >= $limit;
                                    ?>
                                    <option value="<?= (int) $destination['id'] ?>"
                                        <?= $v('to_group_id') === (string) $destination['id'] ? 'selected' : '' ?>
                                        <?= $isFull ? 'disabled' : '' ?>>
                                        <?= e($destination['name']) ?> (<?= e($destination['code']) ?>)
                                        — <?= e(group_type_label((string) $destination['group_type'])) ?>
                                        · <?= $count ?>/<?= $limit ?> siswa<?= $isFull ? ' · PENUH' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'to_group_id') ?>
                            <div class="form-text">Hanya group aktif yang berbeda dari group asal yang ditampilkan.</div>
                        </div>
                        <div class="col-md-5">
                            <label for="transfer_date" class="form-label">Tanggal perpindahan <span class="text-danger">*</span></label>
                            <input type="date" class="form-control<?= field_invalid($errors, 'transfer_date') ?>"
                                   id="transfer_date" name="transfer_date"
                                   value="<?= e($v('transfer_date', date('Y-m-d'))) ?>"
                                   min="<?= e((string) $membership['start_date']) ?>" required>
                            <?= field_error($errors, 'transfer_date') ?>
                            <div class="form-text">Tidak boleh sebelum tanggal masuk group asal
                                (<?= e(tanggal_id((string) $membership['start_date'])) ?>).</div>
                        </div>
                        <div class="col-12">
                            <label for="reason" class="form-label">Alasan perpindahan <span class="text-danger">*</span></label>
                            <textarea class="form-control<?= field_invalid($errors, 'reason') ?>" id="reason" name="reason"
                                      rows="3" maxlength="200" required
                                      placeholder="Contoh: penyesuaian jadwal atau level belajar"><?= e($v('reason')) ?></textarea>
                            <?= field_error($errors, 'reason') ?>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="submit" class="btn btn-primary"
                            <?= $destinations === [] ? 'disabled' : '' ?>>
                            <i class="bi bi-arrow-left-right me-1"></i>Proses Perpindahan
                        </button>
                        <a href="<?= e(url('admin/siswa/detail.php?id=' . $studentId)) ?>" class="btn btn-secondary">Batal</a>
                    </div>
                </form>

                <?php if ($destinations === []): ?>
                    <div class="alert alert-warning mt-3 mb-0 py-2 px-3 small">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Belum ada group tujuan lain yang aktif. Buat group baru terlebih dahulu.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Siswa</h2>
                    <div class="card-subtitle">Data pemilik keanggotaan.</div>
                </div>
            </div>
            <div class="card-body">
                <div class="info-tile mb-3">
                    <?= photo_tag($student['photo'] ?? null, (string) $student['name'], 'avatar-photo', 46) ?>
                    <div>
                        <div class="info-tile-title"><?= e($student['name']) ?></div>
                        <div class="info-tile-sub font-monospace"><?= e($student['code']) ?></div>
                        <div class="info-tile-sub"><?= e($student['school_class'] ?: 'Kelas belum diisi') ?></div>
                    </div>
                </div>
                <dl class="detail-list">
                    <div class="detail-row">
                        <dt>Group asal</dt>
                        <dd><?= e($membership['group_name']) ?>
                            <span class="text-muted">(<?= e($membership['group_code']) ?>)</span></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Jenis asal</dt>
                        <dd><?= group_type_badge((string) $membership['group_type']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Guru asal</dt>
                        <dd><?= e($membership['teacher_names'] ?: '-') ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Mapel asal</dt>
                        <dd><?= e($membership['subject_names'] ?: '-') ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Mulai</dt>
                        <dd><?= e(tanggal_id((string) $membership['start_date'])) ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Aturan Perpindahan</h2>
                    <div class="card-subtitle">Dijalankan dalam satu transaksi database.</div>
                </div>
            </div>
            <div class="card-body">
                <ul class="rule-list">
                    <li>
                        <span class="rule-icon rule-check"><i class="bi bi-1-circle"></i></span>
                        <span>Keanggotaan group lama ditutup, record tetap tersimpan sebagai riwayat.</span>
                    </li>
                    <li>
                        <span class="rule-icon rule-check"><i class="bi bi-2-circle"></i></span>
                        <span>Keanggotaan baru dibuat pada group tujuan dengan tanggal perpindahan.</span>
                    </li>
                    <li>
                        <span class="rule-icon rule-check"><i class="bi bi-3-circle"></i></span>
                        <span>Peristiwa perpindahan dicatat pada riwayat siswa beserta petugasnya.</span>
                    </li>
                    <li>
                        <span class="rule-icon rule-lock"><i class="bi bi-receipt"></i></span>
                        <span>Invoice lama tidak diubah. Tarif group baru berlaku pada periode tagihan berikutnya.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
