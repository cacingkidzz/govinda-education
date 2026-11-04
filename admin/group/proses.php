<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
require_login();
csrf_require();
require_admin();

$action = post_string('action');

/**
 * Validasi input group di sisi server.
 *
 * @param array<string, mixed> $data
 * @param array<string, mixed>|null $existing
 * @return array<string, string>
 */
$validate = static function (array $data, ?array $existing = null): array {
    $errors = [];

    $name = trim((string) $data['name']);
    if ($name === '') {
        $errors['name'] = 'Nama group wajib diisi.';
    } elseif (mb_strlen($name) < 3) {
        $errors['name'] = 'Nama group minimal 3 karakter.';
    } elseif (mb_strlen($name) > 120) {
        $errors['name'] = 'Nama group maksimal 120 karakter.';
    }

    if (!in_array((string) $data['group_type'], ['private', 'group'], true)) {
        $errors['group_type'] = 'Jenis group wajib dipilih.';
    }

    if ((string) $data['group_type'] === 'private') {
        // Kapasitas private selalu dinormalisasi menjadi 1 oleh Group::normalizeCapacity().
        // Nilai dari form diabaikan agar tetap patuh saat JavaScript dinonaktifkan.
    } else {
        $capacity = (int) $data['capacity'];
        if ($capacity < Group::MIN_GROUP_CAPACITY || $capacity > Group::MAX_GROUP_CAPACITY) {
            $errors['capacity'] = 'Kapasitas group belajar harus antara 2 sampai 7 siswa.';
        }
    }

    if (mb_strlen((string) $data['description']) > 1000) {
        $errors['description'] = 'Keterangan maksimal 1000 karakter.';
    }

    return $errors;
};

if ($action === 'create') {
    $data = [
        'name'        => post_string('name'),
        'group_type'  => post_string('group_type'),
        'capacity'    => post_int('capacity'),
        'description' => post_string('description'),
        'is_active'   => post_string('is_active') === '0' ? 0 : 1,
    ];

    $errors = $validate($data);
    if ($errors !== []) {
        form_fail($errors, $data, 'admin/group/tambah.php');
    }

    try {
        $created = Group::create($data);
    } catch (Throwable $exception) {
        error_log('[GROUP CREATE] ' . $exception->getMessage());
        form_fail(
            ['name' => 'Group gagal disimpan. Silakan coba lagi.'],
            $data,
            'admin/group/tambah.php'
        );
    }

    log_activity(
        'group_tambah',
        'Menambahkan group "' . $data['name'] . '" (' . $created['code'] . ') berjenis '
        . group_type_label((string) $data['group_type']) . '.',
        'group',
        $created['id']
    );
    flash('success', 'Group "' . $data['name'] . '" berhasil ditambahkan dengan kode ' . $created['code'] . '.');
    redirect('admin/group/detail.php?id=' . $created['id']);
}

if ($action === 'update') {
    $id = post_int('id');
    $existing = $id > 0 ? Group::find($id) : null;
    if ($existing === null) {
        abort_404('Data group tidak ditemukan.');
    }

    $data = [
        'name'        => post_string('name'),
        'group_type'  => post_string('group_type'),
        'capacity'    => post_int('capacity'),
        'description' => post_string('description'),
        'is_active'   => post_string('is_active') === '0' ? 0 : 1,
    ];

    $errors = $validate($data, $existing);

    $memberCount = Group::activeMemberCount($id);
    if ((string) $data['group_type'] === 'private' && $memberCount > 1) {
        $errors['group_type'] = 'Group dengan lebih dari 1 anggota aktif tidak dapat diubah menjadi private.';
    }
    $effectiveCapacity = (string) $data['group_type'] === 'private'
        ? 1
        : max(Group::MIN_GROUP_CAPACITY, min(Group::MAX_GROUP_CAPACITY, (int) $data['capacity']));
    if ($memberCount > $effectiveCapacity) {
        $errors['capacity'] = 'Kapasitas tidak boleh lebih kecil dari jumlah anggota aktif ('
            . $memberCount . ' siswa).';
    }

    if ($errors !== []) {
        form_fail($errors, $data, 'admin/group/edit.php?id=' . $id);
    }

    try {
        Group::update($id, $data);
    } catch (DomainException $exception) {
        form_fail(['capacity' => $exception->getMessage()], $data, 'admin/group/edit.php?id=' . $id);
    } catch (Throwable $exception) {
        error_log('[GROUP UPDATE] ' . $exception->getMessage());
        form_fail(
            ['name' => 'Perubahan group gagal disimpan. Silakan coba lagi.'],
            $data,
            'admin/group/edit.php?id=' . $id
        );
    }

    $changes = [];
    if ((string) $existing['name'] !== (string) $data['name']) {
        $changes[] = 'nama';
    }
    if ((string) $existing['group_type'] !== (string) $data['group_type']) {
        $changes[] = 'jenis';
    }
    if ((int) $existing['capacity'] !== $effectiveCapacity) {
        $changes[] = 'kapasitas';
    }
    if ((int) $existing['is_active'] !== (int) $data['is_active']) {
        $changes[] = 'status';
    }

    log_activity(
        'group_edit',
        'Mengubah group "' . $existing['name'] . '" (' . $existing['code'] . ')'
        . ($changes !== [] ? ': ' . implode(', ', $changes) . '.' : '.'),
        'group',
        $id
    );
    flash('success', 'Group "' . $data['name'] . '" berhasil diperbarui.');
    redirect('admin/group/detail.php?id=' . $id);
}

if ($action === 'toggle') {
    $id = post_int('id');
    $group = $id > 0 ? Group::find($id) : null;
    if ($group === null) {
        abort_404('Data group tidak ditemukan.');
    }

    $activate = (int) $group['is_active'] !== 1;
    Group::setActive($id, $activate);

    log_activity(
        $activate ? 'group_aktif' : 'group_nonaktif',
        ($activate ? 'Mengaktifkan' : 'Menonaktifkan') . ' group "' . $group['name'] . '" ('
        . $group['code'] . ').',
        'group',
        $id
    );
    flash(
        'success',
        'Group "' . $group['name'] . '" berhasil ' . ($activate ? 'diaktifkan' : 'dinonaktifkan') . '.'
    );

    $redirect = post_string('redirect');
    if ($redirect === 'index') {
        redirect('admin/group/index.php');
    }
    redirect('admin/group/detail.php?id=' . $id);
}

abort_404('Aksi yang diminta tidak dikenali.');
