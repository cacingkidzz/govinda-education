<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
require_login();
require_role(['admin', 'petugas']);
csrf_require();

$action = post_string('action');
$id = post_int('id');

$backToDetail = static function (int $packageId): never {
    redirect('admin/paket/detail.php?id=' . $packageId);
};

if ($action === 'create') {
    $membershipId = post_int('group_member_id');
    $startDate = post_string('start_date');
    $discountRaw = post_string('discount');
    $notes = post_string('notes');

    $old = [
        'group_member_id' => (string) $membershipId,
        'start_date'      => $startDate,
        'discount'        => $discountRaw,
        'notes'           => $notes,
    ];

    $errors = [];
    $membership = $membershipId > 0 ? StudentPackage::findForMembership($membershipId) : null;
    if ($membership === null) {
        $errors['group_member_id'] = 'Siswa dan kelompok wajib dipilih.';
    }
    if (!valid_date($startDate)) {
        $errors['start_date'] = 'Tanggal mulai paket wajib diisi dengan benar.';
    } elseif ($membership !== null && $startDate < (string) $membership['membership_start']) {
        $errors['start_date'] = 'Tanggal mulai tidak boleh sebelum tanggal masuk keanggotaan ('
            . tanggal_id((string) $membership['membership_start']) . ').';
    }

    $discount = 0.0;
    if (trim($discountRaw) !== '') {
        $parsed = parse_currency($discountRaw);
        if ($parsed === null || $parsed < 0) {
            $errors['discount'] = 'Diskon tidak valid.';
        } else {
            $discount = $parsed;
        }
    }
    if (mb_strlen($notes) > 500) {
        $errors['notes'] = 'Catatan maksimal 500 karakter.';
    }

    if ($errors !== []) {
        form_fail($errors, $old, 'admin/paket/tambah.php' . ($membershipId > 0 ? '?membership_id=' . $membershipId : ''));
    }

    $result = StudentPackage::createForMembership($membershipId, $startDate, [
        'discount' => $discount,
        'notes'    => $notes,
    ]);

    if (!$result['ok']) {
        form_fail(['form' => $result['error'], 'group_member_id' => $result['error']], $old, 'admin/paket/tambah.php');
    }

    if ($result['warning'] !== '') {
        flash('warning', $result['warning']);
    }
    flash(
        'success',
        'Paket belajar berhasil dibuat'
        . ($result['invoice_id'] > 0 ? ' beserta tagihannya.' : '; tagihan belum diterbitkan karena tarif belum tersedia.')
    );
    redirect('admin/paket/detail.php?id=' . (int) $result['id']);
}

if ($action === 'create_group') {
    $groupId = post_int('group_id');
    $startDate = post_string('start_date');
    $discountRaw = post_string('discount');
    $notes = post_string('notes');

    $membershipIds = [];
    $rawIds = $_POST['membership_ids'] ?? [];
    if (is_array($rawIds)) {
        foreach ($rawIds as $raw) {
            $value = (int) $raw;
            if ($value > 0) {
                $membershipIds[$value] = $value;
            }
        }
    }
    $membershipIds = array_values($membershipIds);

    $old = [
        'group_id'       => (string) $groupId,
        'start_date'     => $startDate,
        'discount'       => $discountRaw,
        'notes'          => $notes,
        'membership_ids' => $membershipIds,
    ];
    $backUrl = 'admin/paket/tambah.php?mode=group' . ($groupId > 0 ? '&group_id=' . $groupId : '');

    $errors = [];
    $group = $groupId > 0 ? Group::find($groupId) : null;
    if ($group === null || (int) $group['is_active'] !== 1) {
        $errors['group_id'] = 'Kelompok aktif wajib dipilih.';
    }
    if (!valid_date($startDate)) {
        $errors['start_date'] = 'Tanggal mulai paket wajib diisi dengan benar.';
    }
    if ($membershipIds === []) {
        $errors['membership_ids'] = 'Pilih minimal satu siswa yang akan dibuatkan paket.';
    }

    $discount = 0.0;
    if (trim($discountRaw) !== '') {
        $parsed = parse_currency($discountRaw);
        if ($parsed === null || $parsed < 0) {
            $errors['discount'] = 'Diskon tidak valid.';
        } else {
            $discount = $parsed;
        }
    }
    if (mb_strlen($notes) > 500) {
        $errors['notes'] = 'Catatan maksimal 500 karakter.';
    }

    if ($errors !== []) {
        form_fail($errors, $old, $backUrl);
    }

    $result = StudentPackage::createForGroup($groupId, $startDate, $membershipIds, [
        'discount' => $discount,
        'notes'    => $notes,
    ]);

    if (!$result['ok']) {
        form_fail(['form' => $result['error']], $old, $backUrl);
    }

    $message = $result['created'] . ' paket belajar berhasil dibuat untuk group '
        . (string) $group['name'] . '.';
    if ($result['invoices'] > 0) {
        $message .= ' ' . $result['invoices'] . ' tagihan langsung diterbitkan.';
    } else {
        $message .= ' Tagihan belum diterbitkan karena tarif belum tersedia.';
    }
    flash('success', $message);

    if ($result['skipped'] !== []) {
        flash(
            'warning',
            'Dilewati: ' . implode('; ', $result['skipped'])
            . '. Paket mereka tidak dibuat agar tidak terjadi paket ganda.'
        );
    }
    if ($result['warnings'] !== []) {
        flash(
            'warning',
            count($result['warnings']) . ' siswa belum memiliki tagihan karena tarif belum diatur: '
            . implode('; ', array_slice($result['warnings'], 0, 3))
            . (count($result['warnings']) > 3 ? ' dan lainnya.' : '')
        );
    }

    redirect('admin/paket/index.php?group_id=' . $groupId);
}

if ($action === 'issue') {
    $package = $id > 0 ? StudentPackage::find($id) : null;
    if ($package === null) {
        abort_404('Paket belajar tidak ditemukan.');
    }

    $result = StudentPackage::issueInvoice($id);
    if (!$result['ok']) {
        flash('danger', $result['error']);
        $backToDetail($id);
    }

    flash('success', 'Tagihan paket berhasil diterbitkan.');
    redirect('admin/tagihan/detail.php?id=' . (int) $result['invoice_id']);
}

if ($action === 'cancel') {
    require_admin();

    $package = $id > 0 ? StudentPackage::find($id) : null;
    if ($package === null) {
        abort_404('Paket belajar tidak ditemukan.');
    }

    $reason = post_string('reason');
    $endDate = post_string('end_date');
    $old = ['reason' => $reason, 'end_date' => $endDate];

    $errors = [];
    if (mb_strlen(trim($reason)) < 3) {
        $errors['reason'] = 'Alasan penutupan minimal 3 karakter.';
    }
    if ($endDate !== '' && !valid_date($endDate)) {
        $errors['end_date'] = 'Tanggal penutupan tidak valid.';
    } elseif ($endDate !== '' && $endDate < (string) $package['start_date']) {
        $errors['end_date'] = 'Tanggal penutupan tidak boleh sebelum tanggal mulai paket.';
    }
    if (StudentPackage::hasSuccessfulPayment($id)) {
        $errors['form'] = 'Paket ini sudah memiliki pembayaran berhasil sehingga tidak dapat ditutup. '
            . 'Gunakan penyesuaian administratif, bukan penghapusan.';
    }

    if ($errors !== []) {
        form_fail($errors, $old, 'admin/paket/detail.php?id=' . $id);
    }

    $result = StudentPackage::cancel($id, $reason, $endDate !== '' ? $endDate : null);
    if (!$result['ok']) {
        form_fail(['form' => $result['error'], 'reason' => $result['error']], $old, 'admin/paket/detail.php?id=' . $id);
    }

    flash('success', 'Paket berhasil ditutup. Riwayat pertemuan dan tagihan tetap tersimpan.');
    $backToDetail($id);
}

if ($action === 'recount') {
    require_admin();

    $package = $id > 0 ? StudentPackage::find($id) : null;
    if ($package === null) {
        abort_404('Paket belajar tidak ditemukan.');
    }
    if ((string) $package['status'] === StudentPackage::STATUS_DIBATALKAN) {
        flash('danger', 'Paket yang sudah ditutup tidak dapat dihitung ulang.');
        $backToDetail($id);
    }

    $reason = trim(post_string('reason'));
    $old = ['reason' => $reason];
    if (mb_strlen($reason) < 3) {
        form_fail(
            ['reason' => 'Alasan perbaikan data minimal 3 karakter.'],
            $old,
            'admin/paket/detail.php?id=' . $id
        );
    }
    if (mb_strlen($reason) > 255) {
        form_fail(
            ['reason' => 'Alasan perbaikan data maksimal 255 karakter.'],
            $old,
            'admin/paket/detail.php?id=' . $id
        );
    }

    $before = (int) $package['completed_meetings'];
    $after = StudentPackage::recount($id);

    log_activity(
        'paket_penyesuaian',
        'Hitung ulang Paket ' . (int) $package['package_number'] . ' untuk ' . $package['student_name']
        . ': ' . $before . ' → ' . $after . ' pertemuan. Alasan: ' . $reason,
        'student_package',
        $id
    );

    if ($after === $before) {
        flash('info', 'Hitung ulang selesai. Tidak ada perubahan hitungan (' . $after . ' pertemuan).');
    } else {
        flash('success', 'Hitungan paket diperbarui dari ' . $before . ' menjadi ' . $after . ' pertemuan.');
    }
    $backToDetail($id);
}

abort_404('Aksi yang diminta tidak dikenali.');
