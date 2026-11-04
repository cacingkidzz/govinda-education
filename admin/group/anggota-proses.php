<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
require_login();
csrf_require();
require_role(['admin', 'petugas']);

$action = post_string('action');

if ($action === 'add') {
    $groupId = post_int('group_id');
    $studentId = post_int('student_id');
    $startDate = post_string('start_date');
    $note = post_string('note');

    $group = $groupId > 0 ? Group::find($groupId) : null;
    $student = $studentId > 0 ? Student::find($studentId) : null;
    if ($group === null || $student === null) {
        abort_404('Data group atau siswa tidak ditemukan.');
    }

    $errors = [];
    if ($studentId <= 0) {
        $errors['student_id'] = 'Siswa wajib dipilih.';
    }
    if ($startDate === '') {
        $errors['start_date'] = 'Tanggal masuk wajib diisi.';
    } elseif (!valid_date($startDate)) {
        $errors['start_date'] = 'Tanggal masuk tidak valid.';
    }
    if (mb_strlen($note) > 255) {
        $errors['note'] = 'Keterangan maksimal 255 karakter.';
    }

    if ($errors !== []) {
        form_fail(
            $errors,
            ['student_id' => $studentId, 'start_date' => $startDate, 'note' => $note],
            'admin/group/anggota-tambah.php?group_id=' . $groupId
        );
    }

    $result = Membership::add($groupId, $studentId, $startDate, $note);
    if (!$result['ok']) {
        form_fail(
            ['form' => $result['error'], 'student_id' => $result['error']],
            ['student_id' => $studentId, 'start_date' => $startDate, 'note' => $note],
            'admin/group/anggota-tambah.php?group_id=' . $groupId
        );
    }

    log_activity(
        'group_anggota_tambah',
        'Menambahkan siswa "' . $student['name'] . '" (' . $student['code'] . ') ke group "'
        . $group['name'] . '" (' . $group['code'] . ') mulai ' . tanggal_id($startDate) . '.',
        'group',
        $groupId
    );
    flash('success', 'Siswa "' . $student['name'] . '" berhasil ditambahkan ke group "' . $group['name'] . '".');
    redirect('admin/group/detail.php?id=' . $groupId . '&tab=anggota');
}

if ($action === 'end') {
    $membershipId = post_int('membership_id');
    $membership = $membershipId > 0 ? Membership::find($membershipId) : null;
    if ($membership === null) {
        abort_404('Data keanggotaan tidak ditemukan.');
    }

    $endDate = post_string('end_date');
    $note = post_string('note');
    $returnTo = post_string('return') === 'student' ? 'student' : 'group';

    $errors = [];
    if ($endDate === '') {
        $errors['end_date'] = 'Tanggal keluar wajib diisi.';
    } elseif (!valid_date($endDate)) {
        $errors['end_date'] = 'Tanggal keluar tidak valid.';
    } elseif ($endDate < (string) $membership['start_date']) {
        $errors['end_date'] = 'Tanggal keluar tidak boleh sebelum tanggal masuk ('
            . tanggal_id((string) $membership['start_date']) . ').';
    }
    if (mb_strlen($note) > 255) {
        $errors['note'] = 'Keterangan maksimal 255 karakter.';
    }

    $backUrl = 'admin/group/anggota-akhir.php?membership_id=' . $membershipId
        . '&return=' . $returnTo;

    if ($errors !== []) {
        form_fail($errors, ['end_date' => $endDate, 'note' => $note], $backUrl);
    }

    $result = Membership::end($membershipId, $endDate, $note);
    if (!$result['ok']) {
        form_fail(
            ['form' => $result['error'], 'end_date' => $result['error']],
            ['end_date' => $endDate, 'note' => $note],
            $backUrl
        );
    }

    log_activity(
        'group_anggota_selesai',
        'Mengakhiri keanggotaan siswa "' . $membership['student_name'] . '" (' . $membership['student_code']
        . ') pada group "' . $membership['group_name'] . '" (' . $membership['group_code'] . ') per '
        . tanggal_id($endDate) . '.',
        'group',
        (int) $membership['group_id']
    );
    flash(
        'success',
        'Keanggotaan siswa "' . $membership['student_name'] . '" pada group "'
        . $membership['group_name'] . '" berhasil diakhiri.'
    );

    if ($returnTo === 'student') {
        redirect('admin/siswa/detail.php?id=' . (int) $membership['student_id']);
    }
    redirect('admin/group/detail.php?id=' . (int) $membership['group_id'] . '&tab=anggota');
}

if ($action === 'transfer') {
    $studentId = post_int('student_id');
    $toGroupId = post_int('to_group_id');
    $transferDate = post_string('transfer_date');
    $reason = post_string('reason');

    $student = $studentId > 0 ? Student::find($studentId) : null;
    if ($student === null) {
        abort_404('Data siswa tidak ditemukan.');
    }

    $errors = [];
    if ($toGroupId <= 0) {
        $errors['to_group_id'] = 'Group tujuan wajib dipilih.';
    }
    if ($transferDate === '') {
        $errors['transfer_date'] = 'Tanggal perpindahan wajib diisi.';
    } elseif (!valid_date($transferDate)) {
        $errors['transfer_date'] = 'Tanggal perpindahan tidak valid.';
    }
    if (trim($reason) === '') {
        $errors['reason'] = 'Alasan perpindahan wajib diisi.';
    } elseif (mb_strlen(trim($reason)) < 3) {
        $errors['reason'] = 'Alasan perpindahan minimal 3 karakter.';
    } elseif (mb_strlen(trim($reason)) > 200) {
        $errors['reason'] = 'Alasan perpindahan maksimal 200 karakter.';
    }

    $backUrl = 'admin/group/pindah.php?student_id=' . $studentId;
    $old = [
        'to_group_id'   => $toGroupId,
        'transfer_date' => $transferDate,
        'reason'        => $reason,
    ];

    if ($errors !== []) {
        form_fail($errors, $old, $backUrl);
    }

    $result = Membership::transfer(
        $studentId,
        $toGroupId,
        $transferDate,
        $reason,
        post_string('confirm_package_close') === '1'
    );
    if (!$result['ok']) {
        if ($result['needs_confirmation']) {
            form_fail(
                [
                    'form'              => $result['error'],
                    'confirm_package'   => 'Centang konfirmasi penutupan paket lama untuk melanjutkan.',
                ],
                $old,
                $backUrl
            );
        }
        form_fail(
            ['form' => $result['error'], 'to_group_id' => $result['error']],
            $old,
            $backUrl
        );
    }

    log_activity(
        'group_member_pindah',
        'Memindahkan siswa "' . $student['name'] . '" (' . $student['code'] . ') dari group "'
        . $result['from_name'] . '" ke group "' . $result['to_name'] . '" per '
        . tanggal_id($transferDate) . '. Alasan: ' . trim($reason),
        'student',
        $studentId
    );
    flash(
        'success',
        'Siswa "' . $student['name'] . '" berhasil dipindahkan dari group "'
        . $result['from_name'] . '" ke group "' . $result['to_name'] . '".'
        . ($result['package_number'] > 0
            ? ' Paket ' . $result['package_number'] . ' pada group lama ditutup; sisa pertemuan tidak dipindahkan.'
            : '')
        . ($result['new_package_id'] > 0 ? ' Paket baru untuk group tujuan sudah dibuat.' : '')
    );
    if ($result['package_warning'] !== '') {
        flash('warning', $result['package_warning']);
    }
    redirect('admin/siswa/detail.php?id=' . $studentId . '&tab=riwayat');
}

abort_404('Aksi yang diminta tidak dikenali.');
