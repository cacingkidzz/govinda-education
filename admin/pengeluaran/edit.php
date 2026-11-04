<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$id = get_int('id');
$expense = $id > 0 ? Expense::find($id) : null;
if ($expense === null) {
    abort_404('Pengeluaran tidak ditemukan.');
}
if ((string) $expense['status'] !== Expense::STATUS_RECORDED) {
    flash('warning', 'Pengeluaran ' . $expense['expense_number'] . ' sudah dibatalkan sehingga tidak dapat diubah.');
    redirect('admin/pengeluaran/detail.php?id=' . $id);
}

$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];

$formMode = 'edit';

$pageTitle = 'Edit Pengeluaran';
$pageSubtitle = 'Perbarui data pengeluaran ' . $expense['expense_number'] . '.';
$breadcrumbs = [
    ['label' => 'Pengeluaran', 'url' => url('admin/pengeluaran/index.php')],
    ['label' => $expense['expense_number'], 'url' => url('admin/pengeluaran/detail.php?id=' . $id)],
    ['label' => 'Edit'],
];
$activeMenu = 'pengeluaran';

require_once ROOT_PATH . '/includes/header.php';
require __DIR__ . '/_form.php';
require_once ROOT_PATH . '/includes/footer.php';
