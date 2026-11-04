<?php
declare(strict_types=1);

/**
 * Endpoint catatan pertemuan per group.
 *
 * Mencatat jurnal pertemuan (tanggal, jam, status, dan catatan guru) pada
 * relasi mata pelajaran milik group. Hitungan paket, audit, dan transaksi
 * ditangani Meeting::record() agar konsisten dengan menu Realisasi Pertemuan.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
require_login();
require_role(['admin', 'petugas']);
csrf_require();

$groupId = post_int('group_id');
$groupSubjectId = post_int('group_subject_id');
$meetingDate = post_string('meeting_date');
$startTime = post_string('start_time');
$endTime = post_string('end_time');
$status = post_string('status');
$notes = post_string('notes');

$group = $groupId > 0 ? Group::find($groupId) : null;
if ($group === null) {
    abort_404('Data group tidak ditemukan.');
}

$formUrl = url_query('admin/group/detail.php', [
    'id'  => $groupId,
    'tab' => 'pertemuan',
]);

$old = [
    'group_id'         => $groupId,
    'group_subject_id' => $groupSubjectId,
    'meeting_date'     => $meetingDate,
    'start_time'       => $startTime,
    'end_time'         => $endTime,
    'status'           => $status,
    'notes'            => $notes,
];

$errors = [];

$relation = $groupSubjectId > 0 ? GroupSubject::find($groupSubjectId) : null;
if ($relation === null || (int) $relation['group_id'] !== $groupId) {
    $errors['group_subject_id'] = 'Mata pelajaran group wajib dipilih dengan benar.';
} elseif ((int) $relation['is_active'] !== 1) {
    $errors['group_subject_id'] = 'Relasi mata pelajaran tersebut sedang nonaktif.';
} elseif ((int) $relation['group_active'] !== 1) {
    $errors['group_subject_id'] = 'Group sedang nonaktif; pertemuan baru tidak dapat dicatat.';
}

if ((int) $group['is_active'] !== 1) {
    $errors['group_subject_id'] = $errors['group_subject_id']
        ?? 'Group sedang nonaktif; pertemuan baru tidak dapat dicatat.';
}

if (!valid_date($meetingDate)) {
    $errors['meeting_date'] = 'Tanggal pertemuan wajib diisi dengan format yang benar.';
}

if (Meeting::normalizeTime($startTime) === '') {
    $errors['start_time'] = 'Jam mulai tidak valid. Gunakan format HH:MM.';
}
if (Meeting::normalizeTime($endTime) === '') {
    $errors['end_time'] = 'Jam selesai tidak valid. Gunakan format HH:MM.';
}
if (!isset($errors['start_time']) && !isset($errors['end_time'])
    && Meeting::normalizeTime($endTime) <= Meeting::normalizeTime($startTime)) {
    $errors['end_time'] = 'Jam selesai harus lebih besar dari jam mulai.';
}

$allowedStatuses = [Meeting::STATUS_DONE, Meeting::STATUS_SCHEDULED, Meeting::STATUS_CANCELLED];
if (!in_array($status, $allowedStatuses, true)) {
    $errors['status'] = 'Status pertemuan tidak valid.';
}

if (mb_strlen($notes) < 3) {
    $errors['notes'] = 'Catatan pertemuan wajib diisi minimal 3 karakter.';
} elseif (mb_strlen($notes) > 255) {
    $errors['notes'] = 'Catatan pertemuan maksimal 255 karakter.';
}

if ($errors !== []) {
    form_fail($errors, $old, $formUrl);
}

$result = Meeting::record([
    'schedule_id'      => 0,
    'group_subject_id' => $groupSubjectId,
    'meeting_date'     => $meetingDate,
    'start_time'       => $startTime,
    'end_time'         => $endTime,
    'status'           => $status,
    'notes'            => $notes,
]);

if (!$result['ok']) {
    form_fail(['form' => $result['error'], 'notes' => $result['error']], $old, $formUrl);
}

flash('success', $result['message'] !== ''
    ? $result['message']
    : 'Catatan pertemuan berhasil disimpan.');

redirect($formUrl);
