<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
require_login();
csrf_require();
require_admin();

$action = post_string('action');

$backToList = static function (int $groupId, ?int $relationId = null): never {
    if ($relationId !== null && $relationId > 0) {
        redirect('admin/group/mapel-edit.php?id=' . $relationId);
    }
    redirect('admin/group/mapel.php' . ($groupId > 0 ? '?group_id=' . $groupId : ''));
};

if ($action === 'create') {
    $groupId = post_int('group_id');
    $data = [
        'group_id'    => $groupId,
        'subject_id'  => post_int('subject_id'),
        'teacher_id'  => post_int('teacher_id'),
        'description' => post_string('description'),
        'is_active'   => post_string('is_active') === '0' ? 0 : 1,
    ];

    if ($groupId <= 0) {
        form_fail(['group_id' => 'Group wajib dipilih.'], $data, 'admin/group/mapel-tambah.php');
    }
    if ($data['subject_id'] <= 0) {
        form_fail(['subject_id' => 'Mata pelajaran wajib dipilih.'], $data, 'admin/group/mapel-tambah.php?group_id=' . $groupId);
    }
    if ($data['teacher_id'] <= 0) {
        form_fail(['teacher_id' => 'Guru wajib dipilih.'], $data, 'admin/group/mapel-tambah.php?group_id=' . $groupId);
    }

    try {
        $newId = GroupSubject::create($data);
    } catch (DomainException $exception) {
        form_fail(['_form' => $exception->getMessage()], $data, 'admin/group/mapel-tambah.php?group_id=' . $groupId);
    } catch (Throwable $exception) {
        error_log('[GROUP SUBJECT CREATE] ' . $exception->getMessage());
        form_fail(['_form' => 'Relasi mata pelajaran gagal disimpan. Silakan coba lagi.'], $data,
            'admin/group/mapel-tambah.php?group_id=' . $groupId);
    }

    $relation = GroupSubject::find($newId);
    log_activity(
        'group_mapel_tambah',
        'Menambahkan mata pelajaran "' . ($relation['subject_name'] ?? '-') . '" dengan guru "'
        . ($relation['teacher_name'] ?? '-') . '" pada group "' . ($relation['group_name'] ?? '-') . '".',
        'group',
        $groupId
    );
    flash('success', 'Mata pelajaran berhasil ditambahkan ke group.');
    redirect('admin/group/detail.php?id=' . $groupId . '&tab=mapel');
}

if ($action === 'update') {
    $id = post_int('id');
    $existing = $id > 0 ? GroupSubject::find($id) : null;
    if ($existing === null) {
        abort_404('Relasi mata pelajaran tidak ditemukan.');
    }
    $groupId = (int) $existing['group_id'];

    $data = [
        'subject_id'  => post_int('subject_id'),
        'teacher_id'  => post_int('teacher_id'),
        'description' => post_string('description'),
        'is_active'   => post_string('is_active') === '0' ? 0 : 1,
    ];

    if ($data['subject_id'] <= 0) {
        form_fail(['subject_id' => 'Mata pelajaran wajib dipilih.'], $data, 'admin/group/mapel-edit.php?id=' . $id);
    }
    if ($data['teacher_id'] <= 0) {
        form_fail(['teacher_id' => 'Guru wajib dipilih.'], $data, 'admin/group/mapel-edit.php?id=' . $id);
    }

    try {
        GroupSubject::update($id, $data);
    } catch (DomainException $exception) {
        form_fail(['_form' => $exception->getMessage()], $data, 'admin/group/mapel-edit.php?id=' . $id);
    } catch (Throwable $exception) {
        error_log('[GROUP SUBJECT UPDATE] ' . $exception->getMessage());
        form_fail(['_form' => 'Relasi mata pelajaran gagal diperbarui. Silakan coba lagi.'], $data,
            'admin/group/mapel-edit.php?id=' . $id);
    }

    $changes = [];
    if ((int) $existing['subject_id'] !== (int) $data['subject_id']) {
        $changes[] = 'mata pelajaran';
    }
    if ((int) $existing['teacher_id'] !== (int) $data['teacher_id']) {
        $changes[] = 'guru';
    }
    if ((int) $existing['is_active'] !== (int) $data['is_active']) {
        $changes[] = 'status';
    }

    log_activity(
        'group_mapel_edit',
        'Mengubah relasi mata pelajaran "' . $existing['subject_name'] . '" pada group "'
        . $existing['group_name'] . '"' . ($changes !== [] ? ': ' . implode(', ', $changes) : '') . '.',
        'group',
        $groupId
    );
    flash('success', 'Relasi mata pelajaran berhasil diperbarui.');
    redirect('admin/group/detail.php?id=' . $groupId . '&tab=mapel');
}

if ($action === 'toggle') {
    $id = post_int('id');
    $relation = $id > 0 ? GroupSubject::find($id) : null;
    if ($relation === null) {
        abort_404('Relasi mata pelajaran tidak ditemukan.');
    }
    $activate = (int) $relation['is_active'] !== 1;

    try {
        GroupSubject::setActive($id, $activate);
    } catch (DomainException $exception) {
        flash('danger', $exception->getMessage());
        redirect('admin/group/detail.php?id=' . (int) $relation['group_id'] . '&tab=mapel');
    } catch (Throwable $exception) {
        error_log('[GROUP SUBJECT TOGGLE] ' . $exception->getMessage());
        flash('danger', 'Status relasi mata pelajaran gagal diubah.');
        redirect('admin/group/detail.php?id=' . (int) $relation['group_id'] . '&tab=mapel');
    }

    log_activity(
        $activate ? 'group_mapel_aktif' : 'group_mapel_nonaktif',
        ($activate ? 'Mengaktifkan' : 'Menonaktifkan') . ' mata pelajaran "' . $relation['subject_name']
        . '" pada group "' . $relation['group_name'] . '".',
        'group',
        (int) $relation['group_id']
    );
    flash('success', 'Relasi mata pelajaran berhasil ' . ($activate ? 'diaktifkan' : 'dinonaktifkan') . '.');
    redirect('admin/group/detail.php?id=' . (int) $relation['group_id'] . '&tab=mapel');
}

if ($action === 'delete') {
    $id = post_int('id');
    $relation = $id > 0 ? GroupSubject::find($id) : null;
    if ($relation === null) {
        abort_404('Relasi mata pelajaran tidak ditemukan.');
    }

    try {
        GroupSubject::delete($id);
    } catch (DomainException $exception) {
        flash('danger', $exception->getMessage());
        redirect('admin/group/detail.php?id=' . (int) $relation['group_id'] . '&tab=mapel');
    } catch (Throwable $exception) {
        error_log('[GROUP SUBJECT DELETE] ' . $exception->getMessage());
        flash('danger', 'Relasi mata pelajaran gagal dihapus.');
        redirect('admin/group/detail.php?id=' . (int) $relation['group_id'] . '&tab=mapel');
    }

    log_activity(
        'group_mapel_hapus',
        'Menghapus relasi mata pelajaran "' . $relation['subject_name'] . '" dari group "'
        . $relation['group_name'] . '".',
        'group',
        (int) $relation['group_id']
    );
    flash('success', 'Relasi mata pelajaran berhasil dihapus.');
    redirect('admin/group/detail.php?id=' . (int) $relation['group_id'] . '&tab=mapel');
}

abort_404('Aksi yang diminta tidak dikenali.');
