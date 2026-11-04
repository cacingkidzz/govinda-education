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
    $status = post_string('is_active');
    return [
        'name'         => post_string('name'),
        'gender'       => post_string('gender'),
        'birth_place'  => post_string('birth_place'),
        'birth_date'   => post_string('birth_date'),
        'address'      => post_string('address'),
        'phone'        => post_string('phone'),
        'email'        => post_string('email'),
        'education'    => post_string('education'),
        'is_active'    => $status === '0' ? 0 : 1,
    ];
};

/**
 * @param array<string, mixed> $data
 * @return array{errors: array<string, string>, subject_ids: array<int, int>, old: array<string, mixed>}
 */
$validate = static function (array $data): array {
    $errors = [];
    $today = date('Y-m-d');

    $name = trim((string) $data['name']);
    if ($name === '') {
        $errors['name'] = 'Nama lengkap guru wajib diisi.';
    } elseif (mb_strlen($name) < 2) {
        $errors['name'] = 'Nama guru minimal 2 karakter.';
    } elseif (mb_strlen($name) > 120) {
        $errors['name'] = 'Nama guru maksimal 120 karakter.';
    }

    if (!in_array((string) $data['gender'], ['L', 'P'], true)) {
        $errors['gender'] = 'Jenis kelamin wajib dipilih.';
    }

    if (mb_strlen((string) $data['birth_place']) > 100) {
        $errors['birth_place'] = 'Tempat lahir maksimal 100 karakter.';
    }

    if ((string) $data['birth_date'] !== '') {
        if (!valid_date((string) $data['birth_date'])) {
            $errors['birth_date'] = 'Tanggal lahir tidak valid.';
        } elseif ((string) $data['birth_date'] > $today) {
            $errors['birth_date'] = 'Tanggal lahir tidak boleh melebihi hari ini.';
        }
    }

    if (mb_strlen((string) $data['address']) > 500) {
        $errors['address'] = 'Alamat maksimal 500 karakter.';
    }

    if ((string) $data['phone'] !== '' && !valid_phone((string) $data['phone'])) {
        $errors['phone'] = 'Nomor HP tidak valid. Gunakan format 08xxxxxxxxxx.';
    }

    $email = trim((string) $data['email']);
    if ($email !== '') {
        if (mb_strlen($email) > 120) {
            $errors['email'] = 'Email maksimal 120 karakter.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid.';
        }
    }

    if (mb_strlen((string) $data['education']) > 120) {
        $errors['education'] = 'Pendidikan terakhir maksimal 120 karakter.';
    }

    // Mata pelajaran (opsional, array id).
    $subjectIds = [];
    $rawSubjects = $_POST['subject_ids'] ?? [];
    if (is_array($rawSubjects)) {
        foreach ($rawSubjects as $value) {
            if (!is_string($value) && !is_int($value)) {
                continue;
            }
            $subjectId = (int) $value;
            if ($subjectId <= 0) {
                continue;
            }
            if (Subject::find($subjectId) === null) {
                $errors['subject_ids'] = 'Salah satu mata pelajaran yang dipilih tidak ditemukan.';
                continue;
            }
            $subjectIds[] = $subjectId;
        }
    }
    $subjectIds = array_values(array_unique($subjectIds));

    $old = $data;
    $old['subject_ids'] = $subjectIds;

    return ['errors' => $errors, 'subject_ids' => $subjectIds, 'old' => $old];
};

/**
 * @return array{path:?string,error:?string}
 */
$handlePhoto = static function (): array {
    $file = $_FILES['photo'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }
    $result = Upload::image($file, 'guru', 'guru');
    return [
        'path'  => $result['ok'] ? $result['path'] : null,
        'error' => $result['ok'] ? null : $result['error'],
    ];
};

$action = post_string('action');

if ($action === 'toggle') {
    $id = post_int('id');
    $teacher = $id > 0 ? Teacher::find($id) : null;
    if ($teacher === null) {
        abort_404('Data guru tidak ditemukan.');
    }

    $activate = (int) $teacher['is_active'] !== 1;
    Teacher::setActive($id, $activate);
    log_activity(
        $activate ? 'guru_aktif' : 'guru_nonaktif',
        ($activate ? 'Mengaktifkan' : 'Menonaktifkan') . ' guru "' . $teacher['name'] . '" (' . $teacher['code'] . ').',
        'teacher',
        $id
    );
    flash('success', 'Guru "' . $teacher['name'] . '" berhasil ' . ($activate ? 'diaktifkan' : 'dinonaktifkan') . '.');

    if (post_string('redirect') === 'detail') {
        redirect('admin/guru/detail.php?id=' . $id);
    }
    redirect('admin/guru/index.php');
}

if ($action === 'create') {
    $raw = $collect();
    $checked = $validate($raw);
    $errors = $checked['errors'];
    $photo = $handlePhoto();
    if ($photo['error'] !== null) {
        $errors['photo'] = $photo['error'];
    }

    if ($errors !== []) {
        if ($photo['path'] !== null) {
            Upload::remove($photo['path']);
        }
        form_fail($errors, $checked['old'], 'admin/guru/tambah.php');
    }

    $data = $raw;
    $data['phone'] = $raw['phone'] !== '' ? normalize_phone((string) $raw['phone']) : '';
    $data['photo'] = $photo['path'];

    try {
        $created = Teacher::create($data, $checked['subject_ids']);
    } catch (Throwable $exception) {
        if ($photo['path'] !== null) {
            Upload::remove($photo['path']);
        }
        throw $exception;
    }

    log_activity(
        'guru_tambah',
        'Menambahkan guru "' . $data['name'] . '" (' . $created['code'] . ').',
        'teacher',
        $created['id']
    );
    flash('success', 'Guru "' . $data['name'] . '" berhasil ditambahkan dengan kode ' . $created['code'] . '.');
    redirect('admin/guru/detail.php?id=' . $created['id']);
}

if ($action === 'update') {
    $id = post_int('id');
    $existing = $id > 0 ? Teacher::find($id) : null;
    if ($existing === null) {
        abort_404('Data guru tidak ditemukan.');
    }

    $raw = $collect();
    $checked = $validate($raw);
    $errors = $checked['errors'];
    $photo = $handlePhoto();
    if ($photo['error'] !== null) {
        $errors['photo'] = $photo['error'];
    }

    if ($errors !== []) {
        if ($photo['path'] !== null) {
            Upload::remove($photo['path']);
        }
        form_fail($errors, $checked['old'], 'admin/guru/edit.php?id=' . $id);
    }

    $data = $raw;
    $data['phone'] = $raw['phone'] !== '' ? normalize_phone((string) $raw['phone']) : '';
    $data['photo'] = $photo['path'] ?? $existing['photo'];

    try {
        Teacher::update($id, $data, $checked['subject_ids']);
    } catch (Throwable $exception) {
        if ($photo['path'] !== null) {
            Upload::remove($photo['path']);
        }
        throw $exception;
    }

    if ($photo['path'] !== null && !empty($existing['photo'])) {
        Upload::remove((string) $existing['photo']);
    }

    log_activity(
        'guru_edit',
        'Mengubah data guru "' . $data['name'] . '" (' . $existing['code'] . ').',
        'teacher',
        $id
    );
    flash('success', 'Data guru "' . $data['name'] . '" berhasil diperbarui.');
    redirect('admin/guru/detail.php?id=' . $id);
}

abort_404('Aksi yang diminta tidak dikenali.');
