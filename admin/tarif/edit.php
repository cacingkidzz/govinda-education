<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_admin();

$id = get_int('id');
$rule = $id > 0 ? Pricing::find($id) : null;
if ($rule === null) {
    abort_404('Data tarif tidak ditemukan.');
}

$state = pull_form_state();
$formMode = 'edit';
$errors = $state['errors'];
$old = $state['old'];

$pageTitle = 'Edit Tarif Paket';
$pageSubtitle = 'Perbarui nominal atau periode berlaku tarif untuk tagihan berikutnya.';
$breadcrumbs = [
    ['label' => 'Tarif Paket', 'url' => url('admin/tarif/index.php')],
    ['label' => 'Edit Tarif'],
];
$activeMenu = 'tarif';

require_once ROOT_PATH . '/includes/header.php';
?>

<?php require __DIR__ . '/_form.php'; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
