<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
require_login();
require_role(['admin', 'petugas']);
csrf_require();

$action = post_string('action');
$id = post_int('id');

if ($action === 'cancel') {
    require_admin();

    $invoice = $id > 0 ? Invoice::find($id) : null;
    if ($invoice === null) {
        abort_404('Tagihan tidak ditemukan.');
    }

    $reason = post_string('reason');
    if (mb_strlen(trim($reason)) < 3) {
        form_fail(
            ['reason' => 'Alasan pembatalan minimal 3 karakter.'],
            ['reason' => $reason],
            'admin/tagihan/detail.php?id=' . $id
        );
    }

    try {
        Invoice::cancel($id, $reason);
    } catch (DomainException $exception) {
        form_fail(
            ['form' => $exception->getMessage(), 'reason' => $exception->getMessage()],
            ['reason' => $reason],
            'admin/tagihan/detail.php?id=' . $id
        );
    } catch (Throwable $exception) {
        error_log('[INVOICE CANCEL] ' . $exception->getMessage());
        form_fail(
            ['form' => 'Tagihan gagal dibatalkan. Silakan coba lagi.'],
            ['reason' => $reason],
            'admin/tagihan/detail.php?id=' . $id
        );
    }

    flash('success', 'Tagihan ' . $invoice['invoice_number'] . ' dibatalkan. Riwayat tetap tersimpan.');
    redirect('admin/tagihan/detail.php?id=' . $id);
}

abort_404('Aksi yang diminta tidak dikenali.');
