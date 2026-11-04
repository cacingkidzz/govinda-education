<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
require_login();
require_role(['admin', 'petugas']);
csrf_require();

$action = post_string('action');
$id = post_int('id');

$input = [
    'expense_date'     => post_string('expense_date'),
    'category'         => post_string('category'),
    'amount'           => post_string('amount'),
    'method'           => post_string('method'),
    'payee'            => post_string('payee'),
    'reference_number' => post_string('reference_number'),
    'notes'            => post_string('notes'),
];

if ($action === 'create') {
    $result = Expense::create($input);
    if (!$result['ok']) {
        $errors = $result['errors'] !== [] ? $result['errors'] : ['form' => $result['error']];
        form_fail($errors, $input, 'admin/pengeluaran/tambah.php');
    }

    flash('success', 'Pengeluaran ' . $result['number'] . ' berhasil dicatat.');
    redirect('admin/pengeluaran/detail.php?id=' . $result['id']);
}

if ($action === 'update') {
    $expense = $id > 0 ? Expense::find($id) : null;
    if ($expense === null) {
        abort_404('Pengeluaran tidak ditemukan.');
    }

    $result = Expense::update($id, $input);
    if (!$result['ok']) {
        $errors = $result['errors'] !== [] ? $result['errors'] : ['form' => $result['error']];
        form_fail($errors, $input, 'admin/pengeluaran/edit.php?id=' . $id);
    }

    flash('success', 'Pengeluaran ' . $result['number'] . ' berhasil diperbarui.');
    redirect('admin/pengeluaran/detail.php?id=' . $id);
}

if ($action === 'cancel') {
    require_admin();

    $expense = $id > 0 ? Expense::find($id) : null;
    if ($expense === null) {
        abort_404('Pengeluaran tidak ditemukan.');
    }

    $reason = post_string('reason');
    if (mb_strlen(trim($reason)) < 3) {
        form_fail(
            ['reason' => 'Alasan pembatalan minimal 3 karakter.'],
            ['reason' => $reason],
            'admin/pengeluaran/detail.php?id=' . $id
        );
    }

    try {
        Expense::cancel($id, $reason);
    } catch (DomainException $exception) {
        form_fail(
            ['form' => $exception->getMessage(), 'reason' => $exception->getMessage()],
            ['reason' => $reason],
            'admin/pengeluaran/detail.php?id=' . $id
        );
    } catch (Throwable $exception) {
        error_log('[EXPENSE CANCEL] ' . $exception->getMessage());
        form_fail(
            ['form' => 'Pengeluaran gagal dibatalkan. Silakan coba lagi.'],
            ['reason' => $reason],
            'admin/pengeluaran/detail.php?id=' . $id
        );
    }

    flash('success', 'Pengeluaran ' . $expense['expense_number'] . ' dibatalkan. Riwayat tetap tersimpan.');
    redirect('admin/pengeluaran/detail.php?id=' . $id);
}

abort_404('Aksi yang diminta tidak dikenali.');
