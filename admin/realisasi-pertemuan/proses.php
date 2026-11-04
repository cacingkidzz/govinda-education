<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
require_login();
require_role(['admin', 'petugas']);
csrf_require();

$scheduleId = post_int('schedule_id');
$meetingId = post_int('meeting_id');
$groupSubjectId = post_int('group_subject_id');
$meetingDate = post_string('meeting_date');
$startTime = post_string('start_time');
$endTime = post_string('end_time');
$status = post_string('status');
$notes = post_string('notes');
$reason = post_string('reason');
$replacementDate = post_string('replacement_date');
$replacementStart = post_string('replacement_start');
$replacementEnd = post_string('replacement_end');
$returnFrom = post_string('return_from');

$backUrl = 'admin/realisasi-pertemuan/index.php';
$backParams = [];
if ($returnFrom !== '' && substr_count($returnFrom, '|') === 1) {
    [$from, $to] = explode('|', $returnFrom, 2);
    if (valid_date($from) && valid_date($to)) {
        $backParams = ['date_from' => $from, 'date_to' => $to];
    }
}

$formUrl = 'admin/realisasi-pertemuan/catat.php?' . http_build_query(array_filter([
    'schedule_id'      => $scheduleId > 0 ? $scheduleId : null,
    'id'               => $meetingId > 0 ? $meetingId : null,
    'group_subject_id' => $groupSubjectId > 0 ? $groupSubjectId : null,
    'tanggal'          => valid_date($meetingDate) ? $meetingDate : null,
    'return_from'      => $returnFrom !== '' ? $returnFrom : null,
]));

$old = [
    'meeting_date'      => $meetingDate,
    'start_time'        => $startTime,
    'end_time'          => $endTime,
    'status'            => $status,
    'notes'             => $notes,
    'reason'            => $reason,
    'replacement_date'  => $replacementDate,
    'replacement_start' => $replacementStart,
    'replacement_end'   => $replacementEnd,
];

$errors = [];
if ($groupSubjectId <= 0) {
    $errors['status'] = 'Jadwal pertemuan tidak valid.';
}
if (!valid_date($meetingDate)) {
    $errors['meeting_date'] = 'Tanggal pelaksanaan wajib diisi dengan benar.';
}
if ($startTime === '' || $endTime === '') {
    $errors['start_time'] = 'Jam mulai dan jam selesai wajib diisi.';
} elseif (Meeting::normalizeTime($startTime) === '' || Meeting::normalizeTime($endTime) === '') {
    $errors['start_time'] = 'Format jam tidak valid.';
} elseif (Meeting::normalizeTime($endTime) <= Meeting::normalizeTime($startTime)) {
    $errors['end_time'] = 'Jam selesai harus lebih besar dari jam mulai.';
}
if (!in_array($status, Meeting::statusOptions(), true)) {
    $errors['status'] = 'Status pertemuan tidak valid.';
}
if (mb_strlen($notes) > 255) {
    $errors['notes'] = 'Catatan maksimal 255 karakter.';
}
if ($status === Meeting::STATUS_RESCHEDULED) {
    if (!valid_date($replacementDate)) {
        $errors['replacement_date'] = 'Tanggal pengganti wajib diisi untuk status dijadwalkan ulang.';
    } elseif ($replacementDate === $meetingDate) {
        $errors['replacement_date'] = 'Tanggal pengganti harus berbeda dari tanggal jadwal asal.';
    }
    if ($replacementStart !== '' && Meeting::normalizeTime($replacementStart) === '') {
        $errors['replacement_date'] = 'Jam mulai pengganti tidak valid.';
    }
    if ($replacementEnd !== '' && Meeting::normalizeTime($replacementEnd) === '') {
        $errors['replacement_date'] = 'Jam selesai pengganti tidak valid.';
    }
}

if ($errors !== []) {
    form_fail($errors, $old, $formUrl);
}

$result = Meeting::record([
    'schedule_id'       => $scheduleId,
    'meeting_id'        => $meetingId,
    'group_subject_id'  => $groupSubjectId,
    'meeting_date'      => $meetingDate,
    'start_time'        => $startTime,
    'end_time'          => $endTime,
    'status'            => $status,
    'notes'             => $notes,
    'reason'            => $reason,
    'replacement_date'  => $replacementDate,
    'replacement_start' => $replacementStart,
    'replacement_end'   => $replacementEnd,
]);

if (!$result['ok']) {
    form_fail(['form' => $result['error'], 'status' => $result['error']], $old, $formUrl);
}

flash('success', $result['message']);
redirect(url_query($backUrl, $backParams));
