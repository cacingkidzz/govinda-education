<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
require_login();
csrf_require();
require_role(['admin', 'petugas']);

/**
 * Ambil input mentah dari form.
 *
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
        'parent_name'  => post_string('parent_name'),
        'parent_phone' => post_string('parent_phone'),
        'school'       => post_string('school'),
        'school_class' => post_string('school_class'),
        'join_date'    => post_string('join_date'),
        'is_active'    => $status === '0' ? 0 : 1,
    ];
};

/**
 * Validasi sisi server. Mengembalikan daftar error per field.
 *
 * @param array<string, mixed> $data
 * @return array<string, string>
 */
$validate = static function (array $data): array {
    $errors = [];
    $today = date('Y-m-d');

    $name = trim((string) $data['name']);
    if ($name === '') {
        $errors['name'] = 'Nama lengkap siswa wajib diisi.';
    } elseif (mb_strlen($name) < 2) {
        $errors['name'] = 'Nama siswa minimal 2 karakter.';
    } elseif (mb_strlen($name) > 120) {
        $errors['name'] = 'Nama siswa maksimal 120 karakter.';
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
        $errors['phone'] = 'Nomor HP siswa tidak valid. Gunakan format 08xxxxxxxxxx.';
    }

    if (mb_strlen((string) $data['parent_name']) > 120) {
        $errors['parent_name'] = 'Nama orang tua/wali maksimal 120 karakter.';
    }

    if ((string) $data['parent_phone'] !== '' && !valid_phone((string) $data['parent_phone'])) {
        $errors['parent_phone'] = 'Nomor HP orang tua/wali tidak valid. Gunakan format 08xxxxxxxxxx.';
    }

    if (mb_strlen((string) $data['school']) > 150) {
        $errors['school'] = 'Nama sekolah maksimal 150 karakter.';
    }

    if (mb_strlen((string) $data['school_class']) > 50) {
        $errors['school_class'] = 'Kelas sekolah maksimal 50 karakter.';
    }

    if ((string) $data['join_date'] === '') {
        $errors['join_date'] = 'Tanggal mulai bergabung wajib diisi.';
    } elseif (!valid_date((string) $data['join_date'])) {
        $errors['join_date'] = 'Tanggal mulai bergabung tidak valid.';
    } elseif ((string) $data['join_date'] > $today) {
        $errors['join_date'] = 'Tanggal mulai bergabung tidak boleh melebihi hari ini.';
    }

    return $errors;
};

/**
 * Simpan foto baru bila ada.
 *
 * @return array{path:?string,error:?string}
 */
$handlePhoto = static function (): array {
    $file = $_FILES['photo'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }
    $result = Upload::image($file, 'siswa', 'siswa');
    return [
        'path'  => $result['ok'] ? $result['path'] : null,
        'error' => $result['ok'] ? null : $result['error'],
    ];
};

$action = post_string('action');

if ($action === 'toggle') {
    $id = post_int('id');
    $student = $id > 0 ? Student::find($id) : null;
    if ($student === null) {
        abort_404('Data siswa tidak ditemukan.');
    }

    $activate = (int) $student['is_active'] !== 1;
    Student::setActive($id, $activate);
    log_activity(
        $activate ? 'siswa_aktif' : 'siswa_nonaktif',
        ($activate ? 'Mengaktifkan' : 'Menonaktifkan') . ' siswa "' . $student['name'] . '" (' . $student['code'] . ').',
        'student',
        $id
    );
    flash('success', 'Siswa "' . $student['name'] . '" berhasil ' . ($activate ? 'diaktifkan' : 'dinonaktifkan') . '.');

    if (post_string('redirect') === 'detail') {
        redirect('admin/siswa/detail.php?id=' . $id);
    }
    redirect('admin/siswa/index.php');
}

if ($action === 'create') {
    $raw = $collect();
    $errors = $validate($raw);
    $photo = $handlePhoto();
    if ($photo['error'] !== null) {
        $errors['photo'] = $photo['error'];
    }

    if ($errors !== []) {
        if ($photo['path'] !== null) {
            Upload::remove($photo['path']);
        }
        form_fail($errors, $raw, 'admin/siswa/tambah.php');
    }

    $data = $raw;
    $data['phone'] = $raw['phone'] !== '' ? normalize_phone((string) $raw['phone']) : '';
    $data['parent_phone'] = $raw['parent_phone'] !== '' ? normalize_phone((string) $raw['parent_phone']) : '';
    $data['photo'] = $photo['path'];

    try {
        $created = Student::create($data);
    } catch (Throwable $exception) {
        if ($photo['path'] !== null) {
            Upload::remove($photo['path']);
        }
        throw $exception;
    }

    log_activity(
        'siswa_tambah',
        'Menambahkan siswa "' . $data['name'] . '" (' . $created['code'] . ').',
        'student',
        $created['id']
    );
    flash('success', 'Siswa "' . $data['name'] . '" berhasil ditambahkan dengan kode ' . $created['code'] . '.');
    redirect('admin/siswa/detail.php?id=' . $created['id']);
}

if ($action === 'update') {
    $id = post_int('id');
    $existing = $id > 0 ? Student::find($id) : null;
    if ($existing === null) {
        abort_404('Data siswa tidak ditemukan.');
    }

    $raw = $collect();
    $errors = $validate($raw);
    $photo = $handlePhoto();
    if ($photo['error'] !== null) {
        $errors['photo'] = $photo['error'];
    }

    if ($errors !== []) {
        if ($photo['path'] !== null) {
            Upload::remove($photo['path']);
        }
        form_fail($errors, $raw, 'admin/siswa/edit.php?id=' . $id);
    }

    $data = $raw;
    $data['phone'] = $raw['phone'] !== '' ? normalize_phone((string) $raw['phone']) : '';
    $data['parent_phone'] = $raw['parent_phone'] !== '' ? normalize_phone((string) $raw['parent_phone']) : '';
    $data['photo'] = $photo['path'] ?? $existing['photo'];

    try {
        Student::update($id, $data);
    } catch (Throwable $exception) {
        if ($photo['path'] !== null) {
            Upload::remove($photo['path']);
        }
        throw $exception;
    }

    // Foto lama baru dihapus setelah database berhasil diperbarui.
    if ($photo['path'] !== null && !empty($existing['photo'])) {
        Upload::remove((string) $existing['photo']);
    }

    log_activity(
        'siswa_edit',
        'Mengubah data siswa "' . $data['name'] . '" (' . $existing['code'] . ').',
        'student',
        $id
    );
    flash('success', 'Data siswa "' . $data['name'] . '" berhasil diperbarui.');
    redirect('admin/siswa/detail.php?id=' . $id);
}

if ($action === 'self_link') {
    $id = post_int('id');
    $student = $id > 0 ? Student::find($id) : null;
    if ($student === null) {
        abort_404('Data siswa tidak ditemukan.');
    }

    StudentSelfService::studentToken($id);
    log_activity(
        'pendaftaran_link',
        'Membuat tautan pendaftaran mandiri untuk siswa "' . $student['name'] . '" (' . $student['code'] . ').',
        'student',
        $id
    );
    flash('success', 'Tautan pendaftaran mandiri siswa "' . $student['name'] . '" siap dibagikan.');

    if (post_string('redirect') === 'detail') {
        redirect('admin/siswa/detail.php?id=' . $id);
    }
    redirect('admin/siswa/pendaftaran.php');
}

if ($action === 'self_link_rotate') {
    $id = post_int('id');
    $student = $id > 0 ? Student::find($id) : null;
    if ($student === null) {
        abort_404('Data siswa tidak ditemukan.');
    }

    StudentSelfService::rotateStudentToken($id);
    log_activity(
        'pendaftaran_link',
        'Mengganti tautan pendaftaran mandiri siswa "' . $student['name'] . '" (' . $student['code'] . ').',
        'student',
        $id
    );
    flash('success', 'Tautan lama siswa "' . $student['name'] . '" tidak berlaku lagi. Bagikan tautan baru.');

    if (post_string('redirect') === 'detail') {
        redirect('admin/siswa/detail.php?id=' . $id);
    }
    redirect('admin/siswa/pendaftaran.php');
}

if ($action === 'self_link_all') {
    $count = StudentSelfService::generateMissingStudentTokens();
    log_activity(
        'pendaftaran_link',
        'Membuat tautan pendaftaran mandiri untuk ' . $count . ' siswa aktif yang belum memiliki tautan.'
    );
    flash(
        $count > 0 ? 'success' : 'info',
        $count > 0
            ? 'Tautan pendaftaran mandiri berhasil dibuat untuk ' . $count . ' siswa.'
            : 'Semua siswa aktif sudah memiliki tautan pendaftaran mandiri.'
    );
    redirect('admin/siswa/pendaftaran.php');
}

if ($action === 'registration_generate') {
    require_role(['admin']);
    $token = StudentSelfService::ensureGeneralToken();
    log_activity(
        'pendaftaran_pengaturan',
        'Membuat tautan pendaftaran siswa baru' . ($token !== '' ? '.' : ' (gagal).')
    );
    flash('success', 'Tautan pendaftaran siswa baru berhasil dibuat.');
    redirect('admin/siswa/pendaftaran.php');
}

if ($action === 'registration_toggle') {
    require_role(['admin']);
    $enabled = post_string('enabled') === '1';
    StudentSelfService::setGeneralEnabled($enabled);
    log_activity(
        'pendaftaran_pengaturan',
        ($enabled ? 'Membuka' : 'Menutup') . ' pendaftaran mandiri siswa baru.'
    );
    flash(
        'success',
        $enabled
            ? 'Pendaftaran siswa baru dibuka. Bagikan tautan kepada calon siswa.'
            : 'Pendaftaran siswa baru ditutup. Tautan umum tidak dapat dipakai lagi.'
    );
    redirect('admin/siswa/pendaftaran.php');
}

if ($action === 'registration_rotate') {
    require_role(['admin']);
    StudentSelfService::rotateGeneralToken();
    log_activity('pendaftaran_pengaturan', 'Mengganti tautan pendaftaran siswa baru.');
    flash('success', 'Tautan pendaftaran siswa baru berhasil diganti. Tautan lama tidak berlaku lagi.');
    redirect('admin/siswa/pendaftaran.php');
}

abort_404('Aksi yang diminta tidak dikenali.');
