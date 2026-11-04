<?php
declare(strict_types=1);

/** @var string $formMode */
/** @var array<string, mixed> $relation */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var array<int, array<string, mixed>> $groups */
/** @var array<int, array<string, mixed>> $subjects */
/** @var array<int, array<string, mixed>> $teachers */

$formMode = $formMode ?? 'create';
$relation = $relation ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$groups = $groups ?? [];
$subjects = $subjects ?? [];
$teachers = $teachers ?? [];
$isEdit = $formMode === 'edit';
$v = static fn (string $key, string $default = ''): string => old_value($old, $relation, $key, $default);
$groupId = (int) ($relation['group_id'] ?? $v('group_id', '0'));
$currentActive = $v('is_active', '1');
?>

<form method="post" action="<?= e(url('admin/group/mapel-proses.php')) ?>" data-submit-once novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) ($relation['id'] ?? 0) ?>">
        <input type="hidden" name="group_id" value="<?= $groupId ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Relasi Mata Pelajaran &amp; Guru</h2>
                        <div class="card-subtitle">Satu mata pelajaran diampu oleh satu guru dalam group ini.</div>
                    </div>
                    <span class="badge bg-teal-soft text-teal">
                        <i class="bi bi-journal-bookmark me-1"></i>Akademik
                    </span>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors['_form'])): ?>
                        <div class="alert alert-danger d-flex gap-2 align-items-start">
                            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                            <div><?= nl2br(e($errors['_form'])) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="group_id" class="form-label">Group <span class="text-danger">*</span></label>
                            <?php if ($isEdit): ?>
                                <input type="text" class="form-control" id="group_id"
                                       value="<?= e((string) ($relation['group_name'] ?? '') . ' (' . (string) ($relation['group_code'] ?? '') . ')') ?>" readonly>
                                <div class="form-text">Group tidak dapat diubah. Hapus relasi ini bila ingin memindahkannya.</div>
                            <?php else: ?>
                                <select class="form-select js-select2<?= field_invalid($errors, 'group_id') ?>" id="group_id"
                                        name="group_id" required data-placeholder="Pilih group">
                                    <option value="">Pilih group</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?= (int) $group['id'] ?>"
                                            <?= $groupId === (int) $group['id'] ? 'selected' : '' ?>>
                                            <?= e($group['name']) ?> (<?= e($group['code']) ?>) &middot;
                                            <?= e(group_type_label((string) $group['group_type'])) ?> &middot;
                                            <?= e((string) $group['member_count']) ?>/<?= e((string) $group['capacity']) ?> siswa
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($errors, 'group_id') ?>
                                <div class="form-text">Hanya group aktif yang ditampilkan.</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="subject_id" class="form-label">Mata pelajaran <span class="text-danger">*</span></label>
                            <select class="form-select js-select2<?= field_invalid($errors, 'subject_id') ?>" id="subject_id"
                                    name="subject_id" required data-placeholder="Pilih mata pelajaran">
                                <option value="">Pilih mata pelajaran</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= (int) $subject['id'] ?>"
                                        <?= $v('subject_id') === (string) $subject['id'] ? 'selected' : '' ?>>
                                        <?= e($subject['name']) ?> (<?= e($subject['code']) ?>)
                                        <?= (int) $subject['is_active'] === 1 ? '' : ' — nonaktif' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'subject_id') ?>
                            <div class="form-text">Mata pelajaran yang sama tidak boleh didaftarkan dua kali dalam satu group.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="teacher_id" class="form-label">Guru pengampu <span class="text-danger">*</span></label>
                            <select class="form-select js-select2<?= field_invalid($errors, 'teacher_id') ?>" id="teacher_id"
                                    name="teacher_id" required data-placeholder="Pilih guru">
                                <option value="">Pilih guru</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?= (int) $teacher['id'] ?>"
                                        <?= $v('teacher_id') === (string) $teacher['id'] ? 'selected' : '' ?>>
                                        <?= e($teacher['name']) ?> (<?= e($teacher['code']) ?>)
                                        <?= (int) $teacher['is_active'] === 1 ? '' : ' — nonaktif' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'teacher_id') ?>
                            <div class="form-text">Guru boleh berbeda untuk setiap mata pelajaran dalam group.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="is_active" class="form-label">Status relasi</label>
                            <select class="form-select<?= field_invalid($errors, 'is_active') ?>" id="is_active" name="is_active">
                                <option value="1" <?= $currentActive === '1' ? 'selected' : '' ?>>Aktif</option>
                                <option value="0" <?= $currentActive === '0' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                            <?= field_error($errors, 'is_active') ?>
                            <div class="form-text">Relasi nonaktif tidak dihitung dalam pemeriksaan bentrok jadwal.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="description" class="form-label">Keterangan</label>
                            <textarea class="form-control<?= field_invalid($errors, 'description') ?>" id="description"
                                      name="description" rows="2" maxlength="255"
                                      placeholder="Catatan tambahan (opsional)"><?= e($v('description')) ?></textarea>
                            <?= field_error($errors, 'description') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Aturan Relasi</h2>
                        <div class="card-subtitle">Divalidasi ulang di server.</div>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="rule-list">
                        <li>
                            <span class="rule-icon rule-group"><i class="bi bi-journals"></i></span>
                            <span>Satu group boleh memiliki <strong>banyak mata pelajaran</strong>.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-private"><i class="bi bi-person-video3"></i></span>
                            <span>Setiap mata pelajaran diampu <strong>tepat satu guru</strong>.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-lock"><i class="bi bi-shield-lock"></i></span>
                            <span>Mata pelajaran yang sudah memiliki jadwal tidak dapat dihapus permanen; nonaktifkan relasinya.</span>
                        </li>
                        <li>
                            <span class="rule-icon rule-clock"><i class="bi bi-clock-history"></i></span>
                            <span>Perubahan guru diperiksa terhadap seluruh jadwal aktif relasi ini.</span>
                        </li>
                    </ul>
                </div>
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Relasi' ?>
                        </button>
                        <a href="<?= e($groupId > 0
                            ? url('admin/group/detail.php?id=' . $groupId . '&tab=mapel')
                            : url('admin/group/mapel.php')) ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
