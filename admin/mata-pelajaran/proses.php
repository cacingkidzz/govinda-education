<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
require_login();
csrf_require();
require_admin();

/**
 * @return array<string, mixed>
 */
$collect = static function (): array {
    return [
        'name'        => post_string('name'),
        'description' => post_string('description'),
        'is_active'   => post_string('is_active') === '0' ? 0 : 1,
    ];
};

/**
 * @param array<string, mixed> $data
 * @return array<string, string>
 */
$validate = static function (array $data, ?int $exceptId = null): array {
    $errors = [];
    $name = trim((string) $data['name']);

    if ($name === '') {
        $errors['name'] = 'Nama mata pelajaran wajib diisi.';
    } elseif (mb_strlen($name) < 2) {
        $errors['name'] = 'Nama mata pelajaran minimal 2 karakter.';
    } elseif (mb_strlen($name) > 100) {
        $errors['name'] = 'Nama mata pelajaran maksimal 100 karakter.';
    } elseif (Subject::nameExists($name, $exceptId)) {
        $errors['name'] = 'Nama mata pelajaran sudah digunakan.';
    }

    if (mb_strlen((string) $data['description']) > 500) {
        $errors['description'] = 'Keterangan maksimal 500 karakter.';
    }

    return $errors;
};

$action = post_string('action');

if ($action === 'toggle') {
    $id = post_int('id');
    $subject = $id > 0 ? Subject::find($id) : null;
    if ($subject === null) {
        abort_404('Mata pelajaran tidak ditemukan.');
    }

    $activate = (int) $subject['is_active'] !== 1;
    Subject::setActive($id, $activate);
    log_activity(
        $activate ? 'subject_aktif' : 'subject_nonaktif',
        ($activate ? 'Mengaktifkan' : 'Menonaktifkan') . ' mata pelajaran "' . $subject['name'] . '" (' . $subject['code'] . ').',
        'subject',
        $id
    );
    flash('success', 'Mata pelajaran "' . $subject['name'] . '" berhasil ' . ($activate ? 'diaktifkan' : 'dinonaktifkan') . '.');

    if (post_string('redirect') === 'detail') {
        redirect('admin/mata-pelajaran/detail.php?id=' . $id);
    }
    redirect('admin/mata-pelajaran/index.php');
}

if ($action === 'create') {
    $raw = $collect();
    $errors = $validate($raw);
    if ($errors !== []) {
        form_fail($errors, $raw, 'admin/mata-pelajaran/tambah.php');
    }

    $created = Subject::create($raw);
    log_activity(
        'subject_tambah',
        'Menambahkan mata pelajaran "' . $raw['name'] . '" (' . $created['code'] . ').',
        'subject',
        $created['id']
    );
    flash('success', 'Mata pelajaran "' . $raw['name'] . '" berhasil ditambahkan dengan kode ' . $created['code'] . '.');
    redirect('admin/mata-pelajaran/detail.php?id=' . $created['id']);
}

if ($action === 'update') {
    $id = post_int('id');
    $existing = $id > 0 ? Subject::find($id) : null;
    if ($existing === null) {
        abort_404('Mata pelajaran tidak ditemukan.');
    }

    $raw = $collect();
    $errors = $validate($raw, $id);
    if ($errors !== []) {
        form_fail($errors, $raw, 'admin/mata-pelajaran/edit.php?id=' . $id);
    }

    Subject::update($id, $raw);
    log_activity(
        'subject_edit',
        'Mengubah mata pelajaran "' . $raw['name'] . '" (' . $existing['code'] . ').',
        'subject',
        $id
    );
    flash('success', 'Mata pelajaran "' . $raw['name'] . '" berhasil diperbarui.');
    redirect('admin/mata-pelajaran/detail.php?id=' . $id);
}

abort_404('Aksi yang diminta tidak dikenali.');
