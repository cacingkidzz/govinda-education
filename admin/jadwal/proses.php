<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
require_login();
csrf_require();
require_admin();

$action = post_string('action');

/**
 * Validasi input jadwal. Mengembalikan error per field.
 *
 * @param array<string, mixed> $data
 * @param array<string, mixed>|null $existing
 * @return array<string, string>
 */
$validate = static function (array $data, ?array $existing = null): array {
    $errors = [];

    $relationId = (int) ($data['group_subject_id'] ?? 0);
    $relation = $relationId > 0 ? GroupSubject::find($relationId) : null;
    if ($relation === null) {
        $errors['group_subject_id'] = 'Mata pelajaran dalam group wajib dipilih.';
    } elseif ((int) $relation['is_active'] !== 1
        && (int) ($existing['group_subject_id'] ?? 0) !== $relationId) {
        $errors['group_subject_id'] = 'Relasi mata pelajaran tersebut sedang nonaktif.';
    } elseif ((int) $relation['group_active'] !== 1
        && (int) ($existing['group_subject_id'] ?? 0) !== $relationId) {
        $errors['group_subject_id'] = 'Group dari mata pelajaran tersebut sedang nonaktif.';
    }

    $day = (int) $data['day_of_week'];
    if ($day < 1 || $day > 7) {
        $errors['day_of_week'] = 'Hari wajib dipilih.';
    }

    $start = Schedule::normalizeTime((string) $data['start_time']);
    $end = Schedule::normalizeTime((string) $data['end_time']);
    if ($start === null) {
        $errors['start_time'] = 'Jam mulai tidak valid. Gunakan format HH:MM.';
    }
    if ($end === null) {
        $errors['end_time'] = 'Jam selesai tidak valid. Gunakan format HH:MM.';
    }
    if ($start !== null && $end !== null && $end <= $start) {
        $errors['end_time'] = 'Jam selesai harus lebih besar dari jam mulai.';
    }

    if (mb_strlen((string) $data['room']) > 100) {
        $errors['room'] = 'Ruangan maksimal 100 karakter.';
    }
    if (mb_strlen((string) $data['notes']) > 255) {
        $errors['notes'] = 'Keterangan maksimal 255 karakter.';
    }

    if ($errors === [] && $relation !== null) {
        $conflictData = [
            'group_subject_id' => (int) $relation['id'],
            'group_id'         => (int) $relation['group_id'],
            'group_name'       => (string) $relation['group_name'],
            'subject_name'     => (string) $relation['subject_name'],
            'teacher_id'       => (int) $relation['teacher_id'],
            'teacher_name'     => (string) $relation['teacher_name'],
            'day_of_week'      => $day,
            'start_time'       => (string) $start,
            'end_time'         => (string) $end,
            'room'             => (string) $data['room'],
        ];
        $messages = Schedule::conflictMessages(
            $conflictData,
            $existing !== null ? (int) $existing['id'] : 0
        );
        if ($messages !== []) {
            $errors['schedule'] = implode("\n", $messages);
        }
    }

    return $errors;
};

/**
 * Bangun ulang URL form agar parameter kembali tetap terjaga.
 */
$formUrl = static function (string $base, int $id = 0): string {
    $params = [];
    if ($id > 0) {
        $params['id'] = $id;
    }
    if (post_string('return') === 'group') {
        $params['return'] = 'group';
        $params['return_id'] = post_int('return_id');
    }
    if ($id === 0) {
        if (post_int('group_id') > 0) {
            $params['group_id'] = post_int('group_id');
        }
        if (post_int('group_subject_id') > 0) {
            $params['group_subject_id'] = post_int('group_subject_id');
        }
    }
    return url_query($base, $params);
};

/**
 * URL setelah aksi berhasil.
 */
$successUrl = static function (int $newId = 0): string {
    $return = post_string('return');
    $returnId = post_int('return_id');
    if ($return === 'group' && $returnId > 0) {
        return url('admin/group/detail.php?id=' . $returnId . '&tab=jadwal');
    }
    if ($return === 'index') {
        return url('admin/jadwal/index.php');
    }
    return $newId > 0
        ? url('admin/jadwal/detail.php?id=' . $newId)
        : url('admin/jadwal/index.php');
};

if ($action === 'create') {
    $data = [
        'group_subject_id' => post_int('group_subject_id'),
        'day_of_week'      => post_int('day_of_week'),
        'start_time'       => post_string('start_time'),
        'end_time'         => post_string('end_time'),
        'room'             => post_string('room'),
        'notes'            => post_string('notes'),
        'is_active'        => post_string('is_active') === '0' ? 0 : 1,
    ];

    $errors = $validate($data);
    if ($errors !== []) {
        form_fail($errors, $data, $formUrl('admin/jadwal/tambah.php'));
    }

    $data['start_time'] = (string) Schedule::normalizeTime((string) $data['start_time']);
    $data['end_time'] = (string) Schedule::normalizeTime((string) $data['end_time']);

    try {
        $createdId = Schedule::create($data);
    } catch (DomainException $exception) {
        form_fail(['schedule' => $exception->getMessage()], $data, $formUrl('admin/jadwal/tambah.php'));
    } catch (Throwable $exception) {
        error_log('[SCHEDULE CREATE] ' . $exception->getMessage());
        form_fail(['schedule' => 'Jadwal gagal disimpan. Silakan coba lagi.'], $data,
            $formUrl('admin/jadwal/tambah.php'));
    }

    $relation = GroupSubject::find((int) $data['group_subject_id']);
    log_activity(
        'jadwal_tambah',
        'Menambahkan jadwal ' . ($relation['subject_name'] ?? '-') . ' group "'
        . ($relation['group_name'] ?? '-') . '" pada '
        . hari_id((int) $data['day_of_week']) . ' '
        . Schedule::timeLabel((string) $data['start_time']) . '–'
        . Schedule::timeLabel((string) $data['end_time'])
        . ((string) $data['room'] !== '' ? ' di ' . $data['room'] : '') . '.',
        'group',
        (int) ($relation['group_id'] ?? 0)
    );
    flash('success', 'Jadwal berhasil ditambahkan.');
    redirect($successUrl($createdId));
}

if ($action === 'update') {
    $id = post_int('id');
    $existing = $id > 0 ? Schedule::find($id) : null;
    if ($existing === null) {
        abort_404('Data jadwal tidak ditemukan.');
    }

    $data = [
        'group_subject_id' => post_int('group_subject_id'),
        'day_of_week'      => post_int('day_of_week'),
        'start_time'       => post_string('start_time'),
        'end_time'         => post_string('end_time'),
        'room'             => post_string('room'),
        'notes'            => post_string('notes'),
        'is_active'        => post_string('is_active') === '0' ? 0 : 1,
    ];

    $errors = $validate($data, $existing);
    if ($errors !== []) {
        form_fail($errors, $data, $formUrl('admin/jadwal/edit.php', $id));
    }

    $data['start_time'] = (string) Schedule::normalizeTime((string) $data['start_time']);
    $data['end_time'] = (string) Schedule::normalizeTime((string) $data['end_time']);

    try {
        Schedule::update($id, $data);
    } catch (DomainException $exception) {
        form_fail(['schedule' => $exception->getMessage()], $data, $formUrl('admin/jadwal/edit.php', $id));
    } catch (Throwable $exception) {
        error_log('[SCHEDULE UPDATE] ' . $exception->getMessage());
        form_fail(['schedule' => 'Perubahan jadwal gagal disimpan. Silakan coba lagi.'], $data,
            $formUrl('admin/jadwal/edit.php', $id));
    }

    $relation = GroupSubject::find((int) $data['group_subject_id']);
    log_activity(
        'jadwal_edit',
        'Mengubah jadwal ' . ($relation['subject_name'] ?? '-') . ' group "'
        . ($relation['group_name'] ?? '-') . '" menjadi '
        . hari_id((int) $data['day_of_week']) . ' '
        . Schedule::timeLabel((string) $data['start_time']) . '–'
        . Schedule::timeLabel((string) $data['end_time']) . '.',
        'group',
        (int) ($relation['group_id'] ?? 0)
    );
    flash('success', 'Jadwal berhasil diperbarui.');
    redirect($successUrl($id));
}

if ($action === 'toggle') {
    $id = post_int('id');
    $schedule = $id > 0 ? Schedule::find($id) : null;
    if ($schedule === null) {
        abort_404('Data jadwal tidak ditemukan.');
    }

    $activate = (int) $schedule['is_active'] !== 1;

    try {
        Schedule::setActive($id, $activate);
    } catch (DomainException $exception) {
        flash('danger', $exception->getMessage());
        redirect($successUrl($id));
    } catch (Throwable $exception) {
        error_log('[SCHEDULE TOGGLE] ' . $exception->getMessage());
        flash('danger', 'Status jadwal gagal diubah.');
        redirect($successUrl($id));
    }

    log_activity(
        $activate ? 'jadwal_aktif' : 'jadwal_nonaktif',
        ($activate ? 'Mengaktifkan' : 'Menonaktifkan') . ' jadwal ' . $schedule['subject_name']
        . ' group "' . $schedule['group_name'] . '" pada ' . hari_id((int) $schedule['day_of_week']) . ' '
        . Schedule::timeLabel((string) $schedule['start_time']) . '–'
        . Schedule::timeLabel((string) $schedule['end_time']) . '.',
        'group',
        (int) $schedule['group_id']
    );
    flash('success', 'Jadwal berhasil ' . ($activate ? 'diaktifkan' : 'dinonaktifkan') . '.');
    redirect($successUrl($id));
}

if ($action === 'delete') {
    $id = post_int('id');
    $schedule = $id > 0 ? Schedule::find($id) : null;
    if ($schedule === null) {
        abort_404('Data jadwal tidak ditemukan.');
    }

    try {
        Schedule::delete($id);
    } catch (DomainException $exception) {
        flash('danger', $exception->getMessage());
        redirect('admin/jadwal/index.php');
    } catch (Throwable $exception) {
        error_log('[SCHEDULE DELETE] ' . $exception->getMessage());
        flash('danger', 'Jadwal gagal dihapus.');
        redirect('admin/jadwal/index.php');
    }

    log_activity(
        'jadwal_hapus',
        'Menghapus jadwal nonaktif ' . $schedule['subject_name'] . ' group "'
        . $schedule['group_name'] . '" pada '
        . hari_id((int) $schedule['day_of_week']) . ' '
        . Schedule::timeLabel((string) $schedule['start_time']) . '–'
        . Schedule::timeLabel((string) $schedule['end_time']) . '.',
        'group',
        (int) $schedule['group_id']
    );
    flash('success', 'Jadwal nonaktif berhasil dihapus permanen.');
    redirect('admin/jadwal/index.php');
}

abort_404('Aksi yang diminta tidak dikenali.');
