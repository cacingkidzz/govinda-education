<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
require_login();
require_role(['admin', 'petugas']);
csrf_require();

$action = post_string('action');

if ($action !== 'pay') {
    abort_404('Aksi yang diminta tidak dikenali.');
}

$invoiceIds = [];
$rawIds = $_POST['invoice_ids'] ?? [];
if (is_array($rawIds)) {
    foreach ($rawIds as $rawId) {
        $id = (int) $rawId;
        if ($id > 0) {
            $invoiceIds[$id] = $id;
        }
    }
}
if ($invoiceIds === []) {
    // Kompatibilitas tautan lama yang hanya mengirim satu invoice_id.
    $singleId = post_int('invoice_id');
    if ($singleId > 0) {
        $invoiceIds[$singleId] = $singleId;
    }
}
$invoiceIds = array_values($invoiceIds);
sort($invoiceIds, SORT_NUMERIC);

$firstInvoice = $invoiceIds !== [] ? Invoice::find((int) $invoiceIds[0]) : null;
$studentId = $firstInvoice !== null ? (int) $firstInvoice['student_id'] : 0;

$old = [
    'invoice_ids'  => $invoiceIds,
    'payment_date' => post_string('payment_date'),
    'method'       => post_string('method'),
    'reference'    => post_string('reference'),
    'notes'        => post_string('notes'),
];
$backUrl = $studentId > 0
    ? 'admin/pembayaran/tambah.php?student_id=' . $studentId
    : 'admin/pembayaran/tambah.php';

if ($invoiceIds === []) {
    form_fail(
        ['form' => 'Pilih minimal satu tagihan yang akan dilunasi.', 'payment_date' => 'Pilih minimal satu tagihan.'],
        $old,
        $backUrl
    );
}

$result = Payment::payMany($invoiceIds, [
    'amount'       => post_string('amount'),
    'method'       => $old['method'],
    'payment_date' => $old['payment_date'],
    'reference'    => $old['reference'],
    'notes'        => $old['notes'],
]);

if (!$result['ok']) {
    form_fail(
        ['form' => $result['error'], 'payment_date' => $result['error']],
        $old,
        $backUrl
    );
}

$invoiceCount = (int) $result['invoice_count'];
flash(
    'success',
    'Pembayaran lunas ' . $result['payment_number'] . ' berhasil disimpan untuk '
    . $invoiceCount . ' tagihan. Rincian alokasi dan nota siap dicetak.'
);
redirect('admin/pembayaran/nota.php?id=' . (int) $result['payment_id']);
