<?php
declare(strict_types=1);

/** @var string $formMode */
/** @var array<string, mixed> $schedule */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var array<int, array<string, mixed>> $relations */

$formMode = $formMode ?? 'create';
$schedule = $schedule ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$relations = $relations ?? [];
$isEdit = $formMode === 'edit';
$v = static fn (string $key, string $default = ''): string => old_value($old, $schedule, $key, $default);
$dayValue = $v('day_of_week', '1');
$startValue = $v('start_time') !== '' ? time_label($v('start_time')) : '';
$endValue = $v('end_time') !== '' ? time_label($v('end_time')) : '';
$currentActive = $v('is_active', '1');
$days = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

$groupedRelations = [];
foreach ($relations as $relation) {
    $groupedRelations[(string) $relation['group_name']][] = $relation;
}
ksort($groupedRelations);
?>

<form method="post" action="<?= e(url('admin/jadwal/proses.php')) ?>" data-submit-once novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) ($schedule['id'] ?? 0) ?>">
    <?php endif; ?>
    <input type="hidden" name="return" value="<?= e((string) ($returnTo ?? '')) ?>">
    <input type="hidden" name="return_id" value="<?= (int) ($returnId ?? 0) ?>">

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Informasi Jadwal</h2>
                        <div class="card-subtitle">Jadwal melekat pada mata pelajaran dalam group; satu mata pelajaran boleh beberapa hari.</div>
                    </div>
                    <span class="badge bg-teal-soft text-teal">
                        <i class="bi bi-calendar-week me-1"></i>Jadwal
                    </span>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors['schedule'])): ?>
                        <div class="alert alert-danger">
                            <div class="fw-semibold mb-1">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>Jadwal tidak dapat disimpan
                            </div>
                            <div class="small"><?= nl2br(e($errors['schedule'])) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="group_subject_id" class="form-label">Group &amp; mata pelajaran <span class="text-danger">*</span></label>
                            <select class="form-select js-select2<?= field_invalid($errors, 'group_subject_id') ?>"
                                    id="group_subject_id" name="group_subject_id" required data-placeholder="Pilih mata pelajaran dalam group">
                                <option value="">Pilih mata pelajaran dalam group</option>
                                <?php foreach ($groupedRelations as $groupName => $items): ?>
                                    <optgroup label="<?= e($groupName) ?>">
                                        <?php foreach ($items as $relation): ?>
                                            <option value="<?= (int) $relation['id'] ?>"
                                                <?= $v('group_subject_id') === (string) $relation['id'] ? 'selected' : '' ?>>
                                                <?= e($relation['subject_name']) ?> — <?= e($relation['teacher_name']) ?>
                                                (<?= e($relation['group_code']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'group_subject_id') ?>
                            <div class="form-text">Guru mengikuti guru yang ditetapkan pada relasi mata pelajaran.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="day_of_week" class="form-label">Hari <span class="text-danger">*</span></label>
                            <select class="form-select<?= field_invalid($errors, 'day_of_week') ?>"
                                    id="day_of_week" name="day_of_week" required>
                                <?php foreach ($days as $number => $label): ?>
                                    <option value="<?= $number ?>" <?= $dayValue === (string) $number ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'day_of_week') ?>
                        </div>

                        <div class="col-md-4">
                            <label for="start_time" class="form-label">Jam mulai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control<?= field_invalid($errors, 'start_time') ?>"
                                   id="start_time" name="start_time" value="<?= e($startValue) ?>" required>
                            <?= field_error($errors, 'start_time') ?>
                        </div>
                        <div class="col-md-4">
                            <label for="end_time" class="form-label">Jam selesai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control<?= field_invalid($errors, 'end_time') ?>"
                                   id="end_time" name="end_time" value="<?= e($endValue) ?>" required>
                            <?= field_error($errors, 'end_time') ?>
                            <div class="form-text">Harus lebih besar dari jam mulai.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="is_active" class="form-label">Status</label>
                            <select class="form-select<?= field_invalid($errors, 'is_active') ?>" id="is_active" name="is_active">
                                <option value="1" <?= $currentActive === '1' ? 'selected' : '' ?>>Aktif</option>
                                <option value="0" <?= $currentActive === '0' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                            <?= field_error($errors, 'is_active') ?>
                        </div>

                        <div class="col-md-6">
                            <label for="room" class="form-label">Ruangan / lokasi</label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'room') ?>"
                                   id="room" name="room" value="<?= e($v('room')) ?>" maxlength="100"
                                   placeholder="Contoh: Ruang 1">
                            <?= field_error($errors, 'room') ?>
                            <div class="form-text">Opsional. Ruangan yang sama tidak boleh dipakai bersamaan.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="notes" class="form-label">Keterangan</label>
                            <input type="text" class="form-control<?= field_invalid($errors, 'notes') ?>"
                                   id="notes" name="notes" value="<?= e($v('notes')) ?>" maxlength="255"
                                   placeholder="Catatan jadwal (opsional)">
                            <?= field_error($errors, 'notes') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Aturan Jadwal</h2>
                        <div class="card-subtitle">Diperiksa di server saat menyimpan.</div>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="rule-list">
                        <li>
                            <span class="rule-icon rule-clock"><i class="bi bi-clock"></i></span>
                            <span>Jam selesai harus lebih besar dari jam mulai.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-check"><i class="bi bi-person-video3"></i></span>
                            <span>Guru yang sama tidak boleh mengajar dua group pada waktu bertumpukan.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-check"><i class="bi bi-people"></i></span>
                            <span>Satu group tidak boleh memiliki dua mata pelajaran bertumpukan.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-check"><i class="bi bi-door-open"></i></span>
                            <span>Ruangan yang sama tidak boleh dipakai dua jadwal pada waktu bertumpukan.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-lock"><i class="bi bi-calendar-check"></i></span>
                            <span>Jadwal bersifat fleksibel: boleh diubah kapan saja mengikuti situasi group.
                                Target <strong>8 pertemuan</strong> adalah target paket belajar siswa, bukan batas
                                jadwal mingguan.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-lock"><i class="bi bi-slash-circle"></i></span>
                            <span>Jadwal nonaktif tidak dihitung, dan jadwal yang sedang diedit dikecualikan.</span>
                        </li>
                    </ul>
                </div>
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Jadwal' ?>
                        </button>
                        <a href="<?= e($isEdit
                            ? url('admin/jadwal/detail.php?id=' . (int) ($schedule['id'] ?? 0))
                            : url('admin/jadwal/index.php')) ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    'use strict';
    const start = document.getElementById('start_time');
    const end = document.getElementById('end_time');
    if (!start || !end) {
        return;
    }
    const sync = () => {
        if (start.value) {
            end.min = start.value;
        }
        if (start.value && end.value && end.value <= start.value) {
            end.setCustomValidity('Jam selesai harus lebih besar dari jam mulai.');
        } else {
            end.setCustomValidity('');
        }
    };
    start.addEventListener('change', sync);
    end.addEventListener('input', sync);
    sync();
})();
</script>
