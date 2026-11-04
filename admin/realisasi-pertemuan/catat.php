<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$scheduleId = get_int('schedule_id');
$meetingId = get_int('id');
$date = get_string('tanggal');
$date = valid_date($date) ? $date : date('Y-m-d');
$groupSubjectId = get_int('group_subject_id');

$meeting = $meetingId > 0 ? Meeting::find($meetingId) : null;
if ($meetingId > 0 && $meeting === null) {
    abort_404('Realisasi pertemuan tidak ditemukan.');
}

$schedule = null;
if ($meeting !== null) {
    $scheduleId = $meeting['schedule_id'] !== null ? (int) $meeting['schedule_id'] : 0;
    $groupSubjectId = (int) $meeting['group_subject_id'];
    $date = (string) $meeting['meeting_date'];
} elseif ($scheduleId > 0) {
    $schedule = Schedule::find($scheduleId);
    if ($schedule === null) {
        abort_404('Jadwal tidak ditemukan.');
    }
    $groupSubjectId = (int) $schedule['group_subject_id'];
    $existing = db()->prepare(
        'SELECT id FROM class_meetings WHERE schedule_id = :schedule_id AND meeting_date = :meeting_date LIMIT 1'
    );
    $existing->execute([':schedule_id' => $scheduleId, ':meeting_date' => $date]);
    $existingId = (int) $existing->fetchColumn();
    if ($existingId > 0) {
        redirect('admin/realisasi-pertemuan/catat.php?id=' . $existingId);
    }
}

if ($groupSubjectId <= 0) {
    abort_404('Jadwal pertemuan tidak valid.');
}

$relation = GroupSubject::find($groupSubjectId);
if ($relation === null) {
    abort_404('Relasi kelompok–mata pelajaran tidak ditemukan.');
}

$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];

$defaultStatus = $meeting !== null ? (string) $meeting['status'] : Meeting::STATUS_DONE;
$startDefault = $meeting !== null ? (string) $meeting['start_time'] : (string) ($schedule['start_time'] ?? '');
$endDefault = $meeting !== null ? (string) $meeting['end_time'] : (string) ($schedule['end_time'] ?? '');
$notesDefault = $meeting !== null ? (string) ($meeting['notes'] ?? '') : '';
$v = static fn (string $key, string $default = ''): string => old_value($old, [], $key, $default);

$statusValue = $v('status', $defaultStatus);
$dateValue = $v('meeting_date', $date);
$startValue = time_label($v('start_time', $startDefault));
$endValue = time_label($v('end_time', $endDefault));
$wasDone = $meeting !== null && (string) $meeting['status'] === Meeting::STATUS_DONE;

$preview = Meeting::previewStudents((int) $relation['group_id'], $dateValue, $meetingId);
$groupId = (int) $relation['group_id'];

$returnFrom = get_string('return_from');
$backParams = [];
if ($returnFrom !== '' && substr_count($returnFrom, '|') === 1) {
    [$backFrom, $backTo] = explode('|', $returnFrom, 2);
    $backParams = ['date_from' => $backFrom, 'date_to' => $backTo];
}

$pageTitle = $meeting !== null ? 'Ubah Realisasi Pertemuan' : 'Catat Realisasi Pertemuan';
$pageSubtitle = 'Tentukan status pelaksanaan. Hanya status Terlaksana yang menambah hitungan paket siswa.';
$breadcrumbs = [
    ['label' => 'Realisasi Pertemuan', 'url' => url_query('admin/realisasi-pertemuan/index.php', $backParams)],
    ['label' => $meeting !== null ? 'Ubah Realisasi' : 'Catat Realisasi'],
];
$activeMenu = 'realisasi';

require_once ROOT_PATH . '/includes/header.php';
?>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Realisasi belum dapat disimpan</div>
        <?php foreach ($errors as $message): ?>
            <div class="small"><?= e($message) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" action="<?= e(url('admin/realisasi-pertemuan/proses.php')) ?>"
              data-submit-once novalidate id="meetingForm">
            <?= csrf_field() ?>
            <input type="hidden" name="schedule_id" value="<?= $scheduleId ?>">
            <input type="hidden" name="meeting_id" value="<?= $meetingId ?>">
            <input type="hidden" name="group_subject_id" value="<?= $groupSubjectId ?>">
            <input type="hidden" name="return_from" value="<?= e($returnFrom) ?>">

            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Informasi Pertemuan</h2>
                        <div class="card-subtitle">Diambil dari jadwal Stage 3; guru mengikuti relasi mata pelajaran.</div>
                    </div>
                    <?= meeting_status_badge($statusValue) ?>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Kelompok</label>
                            <input type="text" class="form-control" readonly
                                   value="<?= e($relation['group_name']) ?> (<?= e($relation['group_code']) ?>)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jenis kelompok</label>
                            <input type="text" class="form-control" readonly
                                   value="<?= e(package_type_label((string) $relation['group_type'])) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mata pelajaran</label>
                            <input type="text" class="form-control" readonly value="<?= e($relation['subject_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Guru</label>
                            <input type="text" class="form-control" readonly value="<?= e($relation['teacher_name']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="meeting_date" class="form-label">Tanggal pelaksanaan <span class="text-danger">*</span></label>
                            <input type="date" class="form-control<?= field_invalid($errors, 'meeting_date') ?>"
                                   id="meeting_date" name="meeting_date" value="<?= e($dateValue) ?>" required>
                            <?= field_error($errors, 'meeting_date') ?>
                        </div>
                        <div class="col-md-3">
                            <label for="start_time" class="form-label">Jam mulai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control<?= field_invalid($errors, 'start_time') ?>"
                                   id="start_time" name="start_time" value="<?= e($startValue) ?>" required>
                            <?= field_error($errors, 'start_time') ?>
                        </div>
                        <div class="col-md-3">
                            <label for="end_time" class="form-label">Jam selesai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control<?= field_invalid($errors, 'end_time') ?>"
                                   id="end_time" name="end_time" value="<?= e($endValue) ?>" required>
                            <?= field_error($errors, 'end_time') ?>
                        </div>

                        <div class="col-md-6">
                            <label for="status" class="form-label">Status pertemuan <span class="text-danger">*</span></label>
                            <select class="form-select<?= field_invalid($errors, 'status') ?>" id="status" name="status" required>
                                <?php foreach (Meeting::statusOptions() as $option): ?>
                                    <option value="<?= e($option) ?>" <?= $statusValue === $option ? 'selected' : '' ?>>
                                        <?= e(meeting_status_label($option)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'status') ?>
                            <div class="form-text">Hanya Terlaksana yang dihitung ke paket belajar.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="notes" class="form-label">Catatan</label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'notes') ?>"
                                   id="notes" name="notes" maxlength="255" value="<?= e($v('notes', $notesDefault)) ?>"
                                   placeholder="Catatan pelaksanaan (opsional)">
                            <?= field_error($errors, 'notes') ?>
                        </div>

                        <div class="col-12" id="reasonWrap">
                            <label for="reason" class="form-label">
                                Alasan perubahan / pembatalan
                                <?php if ($wasDone): ?><span class="text-danger">*</span><?php endif; ?>
                            </label>
                            <textarea class="form-control<?= field_invalid($errors, 'reason') ?>" id="reason" name="reason"
                                      rows="2" maxlength="255" placeholder="Wajib diisi bila pertemuan sudah terlaksana dan diubah atau dibatalkan."><?= e($v('reason')) ?></textarea>
                            <?= field_error($errors, 'reason') ?>
                            <?php if ($wasDone): ?>
                                <div class="alert alert-warning py-2 px-3 small mt-2 mb-0">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                    Pertemuan ini sudah terlaksana. Perubahan tanggal, jam, atau status wajib
                                    disertai alasan dan akan tercatat pada log aktivitas.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 d-none" id="rescheduleWrap">
                            <div class="border rounded-3 p-3 bg-light-subtle">
                                <div class="fw-semibold mb-2">
                                    <i class="bi bi-arrow-repeat me-1 text-warning"></i>Pertemuan pengganti
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label for="replacement_date" class="form-label">Tanggal pengganti <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control<?= field_invalid($errors, 'replacement_date') ?>"
                                               id="replacement_date" name="replacement_date"
                                               value="<?= e($v('replacement_date')) ?>">
                                        <?= field_error($errors, 'replacement_date') ?>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="replacement_start" class="form-label">Jam mulai pengganti</label>
                                        <input type="time" class="form-control" id="replacement_start" name="replacement_start"
                                               value="<?= e(time_label($v('replacement_start'))) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="replacement_end" class="form-label">Jam selesai pengganti</label>
                                        <input type="time" class="form-control" id="replacement_end" name="replacement_end"
                                               value="<?= e(time_label($v('replacement_end'))) ?>">
                                    </div>
                                </div>
                                <div class="form-text mt-1">
                                    Pertemuan asal ditandai dijadwalkan ulang (tidak dihitung) dan pertemuan pengganti
                                    dicatat terpisah. Hitungan paket hanya bertambah saat pengganti berstatus Terlaksana.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-success"
                            data-confirm="<?= $wasDone ? 'Pertemuan ini sudah terlaksana. Perubahan akan dicatat pada log aktivitas.' : 'Simpan realisasi pertemuan ini?' ?>"
                            data-confirm-title="Simpan realisasi"
                            data-confirm-button="Ya, simpan" data-confirm-icon="question">
                        <i class="bi bi-check2-circle me-1"></i>Simpan Realisasi
                    </button>
                    <a href="<?= e(url_query('admin/realisasi-pertemuan/index.php', $backParams)) ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Kembali
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Siswa Terdampak</h2>
                    <div class="card-subtitle">
                        Keanggotaan aktif per <?= e(tanggal_short_id($dateValue)) ?>.
                    </div>
                </div>
                <span class="badge bg-teal-soft text-teal">
                    <?= e((string) count($preview)) ?> siswa
                </span>
            </div>
            <div class="card-body p-0">
                <?php if ($preview === []): ?>
                    <div class="p-4 text-center text-muted small">
                        Belum ada siswa aktif pada kelompok ini di tanggal tersebut.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Siswa</th>
                                <th class="text-center">Paket</th>
                                <th class="text-center">Sebelum</th>
                                <th class="text-center">Sesudah</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($preview as $student): ?>
                                <tr>
                                    <td>
                                        <div class="cell-title"><?= e($student['student_name']) ?></div>
                                        <div class="cell-sub font-monospace"><?= e($student['student_code']) ?></div>
                                    </td>
                                    <td class="text-center">
                                        <?= $student['package_id'] !== null ? e('Paket ' . (int) $student['package_number']) : '—' ?>
                                    </td>
                                    <td class="text-center"><?= e((string) $student['before']) ?></td>
                                    <td class="text-center">
                                        <span class="fw-semibold <?= $student['will_count'] ? 'text-success' : 'text-muted' ?>">
                                            <?= e((string) $student['after']) ?>
                                        </span>
                                        <?php if ($student['will_complete']): ?>
                                            <span class="badge text-bg-warning d-block mt-1">Selesai 8/8</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $student['package_id'] !== null
                                            ? package_status_badge((string) $student['package_status'])
                                            : '<span class="badge text-bg-secondary">Tanpa paket</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white small text-muted">
                Pratinjau memakai status Terlaksana. Kehadiran siswa tidak memengaruhi hitungan paket.
            </div>
        </div>

        <?php if ($meeting !== null): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Riwayat Pencatatan</h2>
                </div>
                <div class="card-body">
                    <dl class="detail-list">
                        <div class="detail-row">
                            <dt>Status tersimpan</dt>
                            <dd><?= meeting_status_badge((string) $meeting['status']) ?></dd>
                        </div>
                        <div class="detail-row">
                            <dt>Dicatat oleh</dt>
                            <dd><?= e($meeting['recorded_by_name'] ?: '-') ?></dd>
                        </div>
                        <div class="detail-row">
                            <dt>Terakhir diubah</dt>
                            <dd><?= e(tanggal_waktu_id((string) $meeting['updated_at'])) ?></dd>
                        </div>
                        <div class="detail-row">
                            <dt>Paket terhitung</dt>
                            <dd><?= e((string) (int) $meeting['affected_count']) ?> paket siswa</dd>
                        </div>
                        <?php if ($meeting['original_date'] !== null): ?>
                            <div class="detail-row">
                                <dt>Pengganti dari</dt>
                                <dd><?= e(tanggal_id((string) $meeting['original_date'])) ?>
                                    (<?= e(time_label((string) $meeting['original_start'])) ?>–<?= e(time_label((string) $meeting['original_end'])) ?>)</dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    'use strict';
    const status = document.getElementById('status');
    const wrap = document.getElementById('rescheduleWrap');
    const date = document.getElementById('meeting_date');
    const start = document.getElementById('start_time');
    const end = document.getElementById('end_time');
    const replacementDate = document.getElementById('replacement_date');

    const syncReschedule = () => {
        const isReschedule = status.value === 'dijadwalkan_ulang';
        wrap.classList.toggle('d-none', !isReschedule);
        if (replacementDate) {
            replacementDate.required = isReschedule;
            if (date.value) {
                replacementDate.min = date.value;
            }
        }
    };
    status.addEventListener('change', syncReschedule);
    syncReschedule();

    if (start && end) {
        const syncTime = () => {
            end.min = start.value || '';
            if (start.value && end.value && end.value <= start.value) {
                end.setCustomValidity('Jam selesai harus lebih besar dari jam mulai.');
            } else {
                end.setCustomValidity('');
            }
        };
        start.addEventListener('change', syncTime);
        end.addEventListener('input', syncTime);
        syncTime();
    }
})();
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
