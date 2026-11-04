<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
require_login();
require_admin();
csrf_require();

$action = post_string('action');

/**
 * @param array<string, mixed> $data
 * @param int $excludeId
 * @return array<string, string>
 */
$validate = static function (array $data, int $excludeId = 0): array {
    $errors = [];

    $groupType = (string) ($data['group_type'] ?? '');
    if (!in_array($groupType, [Group::TYPE_PRIVATE, Group::TYPE_GROUP], true)) {
        $errors['group_type'] = 'Jenis kelompok wajib dipilih.';
    }

    $studentCount = (int) ($data['student_count'] ?? 0);
    if ($groupType === Group::TYPE_PRIVATE && $studentCount !== 1) {
        $errors['student_count'] = 'Kelompok Private harus berisi 1 siswa.';
    }
    if ($groupType === Group::TYPE_GROUP && ($studentCount < 2 || $studentCount > 7)) {
        $errors['student_count'] = 'Kelompok Reguler harus berisi 2 sampai 7 siswa.';
    }

    $amount = parse_currency((string) ($data['amount'] ?? ''));
    if ($amount === null || $amount <= 0) {
        $errors['amount'] = 'Nominal tarif wajib diisi dan lebih besar dari nol.';
    } elseif ($amount > 9999999999.99) {
        $errors['amount'] = 'Nominal tarif melebihi batas yang didukung.';
    }

    $start = (string) ($data['effective_start'] ?? '');
    $end = trim((string) ($data['effective_end'] ?? ''));
    if (!valid_date($start)) {
        $errors['effective_start'] = 'Tanggal berlaku mulai tidak valid.';
    }
    if ($end !== '' && !valid_date($end)) {
        $errors['effective_end'] = 'Tanggal berlaku sampai tidak valid.';
    }
    if ($end !== '' && valid_date($start) && valid_date($end) && $end < $start) {
        $errors['effective_end'] = 'Tanggal berlaku sampai tidak boleh sebelum tanggal mulai.';
    }

    if (mb_strlen(trim((string) ($data['notes'] ?? ''))) > 255) {
        $errors['notes'] = 'Keterangan maksimal 255 karakter.';
    }

    if ($errors === [] && !isset($errors['group_type'])) {
        if (Pricing::hasOverlap($groupType, $studentCount, $start, $end !== '' ? $end : null, $excludeId)) {
            $errors['form'] = 'Sudah ada tarif untuk jenis kelompok dan jumlah siswa tersebut pada periode yang bertumpukan.';
        }
    }

    return $errors;
};

if ($action === 'create' || $action === 'update') {
    $isUpdate = $action === 'update';
    $id = $isUpdate ? post_int('id') : 0;
    $existing = $isUpdate ? Pricing::find($id) : null;
    if ($isUpdate && $existing === null) {
        abort_404('Data tarif tidak ditemukan.');
    }

    $data = [
        'group_type'      => post_string('group_type'),
        'student_count'   => post_int('student_count'),
        'amount'          => post_string('amount'),
        'effective_start' => post_string('effective_start'),
        'effective_end'   => post_string('effective_end'),
        'is_active'       => post_string('is_active') === '0' ? 0 : 1,
        'notes'           => post_string('notes'),
    ];

    $errors = $validate($data, $id);
    if ($errors !== []) {
        form_fail($errors, $data, $isUpdate
            ? 'admin/tarif/edit.php?id=' . $id
            : 'admin/tarif/tambah.php');
    }

    $payload = [
        'group_type'      => $data['group_type'],
        'student_count'   => $data['student_count'],
        'amount'          => (float) parse_currency($data['amount']),
        'effective_start' => $data['effective_start'],
        'effective_end'   => $data['effective_end'] !== '' ? $data['effective_end'] : null,
        'is_active'       => $data['is_active'],
        'notes'           => $data['notes'],
    ];

    try {
        if ($isUpdate) {
            Pricing::update($id, $payload);
        } else {
            $id = Pricing::create($payload);
        }
    } catch (Throwable $exception) {
        error_log('[PRICING SAVE] ' . $exception->getMessage());
        form_fail(
            ['form' => 'Tarif gagal disimpan. Silakan coba lagi.'],
            $data,
            $isUpdate ? 'admin/tarif/edit.php?id=' . $id : 'admin/tarif/tambah.php'
        );
    }

    log_activity(
        $isUpdate ? 'tarif_edit' : 'tarif_tambah',
        ($isUpdate ? 'Mengubah' : 'Menambahkan') . ' tarif '
        . package_type_label($payload['group_type']) . ' ' . $payload['student_count'] . ' siswa: '
        . rupiah($payload['amount']) . ' per paket ' . Pricing::PACKAGE_SIZE . ' pertemuan, berlaku '
        . tanggal_id($payload['effective_start']) . '.',
        'pricing_rule',
        $id
    );
    flash('success', 'Tarif berhasil ' . ($isUpdate ? 'diperbarui' : 'ditambahkan') . '.');
    redirect('admin/tarif/index.php');
}

if ($action === 'toggle') {
    $id = post_int('id');
    $rule = $id > 0 ? Pricing::find($id) : null;
    if ($rule === null) {
        abort_404('Data tarif tidak ditemukan.');
    }
    $activate = (int) $rule['is_active'] !== 1;
    Pricing::setActive($id, $activate);

    log_activity(
        'tarif_edit',
        ($activate ? 'Mengaktifkan' : 'Menonaktifkan') . ' tarif ' . package_type_label((string) $rule['group_type'])
        . ' ' . (int) $rule['student_count'] . ' siswa.',
        'pricing_rule',
        $id
    );
    flash('success', 'Tarif berhasil ' . ($activate ? 'diaktifkan' : 'dinonaktifkan') . '.');
    redirect('admin/tarif/index.php');
}

abort_404('Aksi yang diminta tidak dikenali.');
