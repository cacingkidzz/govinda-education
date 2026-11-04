<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$id = get_int('id');
$invoice = $id > 0 ? Invoice::find($id) : null;
if ($invoice === null) {
    abort_404('Tagihan tidak ditemukan.');
}
$payment = $invoice['payment_id'] !== null ? Payment::receipt((int) $invoice['payment_id']) : null;

$status = (string) $invoice['status'];
$statusClass = match ($status) {
    'lunas'      => 'is-paid',
    'dibatalkan' => 'is-cancelled',
    default      => 'is-unpaid',
};
$orgName = Profile::name();
$orgTagline = Profile::tagline();
$orgAddress = Profile::address();
$orgPhone = Profile::phone();
$orgEmail = Profile::email();
$orgWebsite = Profile::websiteLabel();
$logo = Profile::logoUrl();
$isPaid = $status === 'lunas';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice <?= e($invoice['invoice_number']) ?> — <?= e($orgName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/print.css')) ?>">
</head>
<body class="print-body">

<div class="print-toolbar">
    <?php if ($isPaid && $payment !== null): ?>
        <a class="btn btn-light" href="<?= e(url('admin/pembayaran/nota.php?id=' . (int) $payment['id'])) ?>">
            Buka Nota
        </a>
    <?php endif; ?>
    <a class="btn btn-light" href="<?= e(url('admin/tagihan/detail.php?id=' . (int) $invoice['id'])) ?>">Kembali</a>
    <button type="button" class="btn btn-primary" onclick="window.print()">Cetak / Simpan PDF</button>
</div>

<div class="print-sheet">
    <div class="print-head">
        <div class="print-brand">
            <img src="<?= e($logo) ?>" alt="Logo <?= e($orgName) ?>"
                 class="<?= e(Profile::logoOnLightClass()) ?>">
            <div>
                <p class="print-brand-name"><?= e($orgName) ?></p>
                <?php if ($orgTagline !== ''): ?>
                    <p class="print-brand-tagline"><?= e($orgTagline) ?></p>
                <?php endif; ?>
                <div class="print-brand-meta">
                    <?php if ($orgAddress !== ''): ?><?= nl2br(e($orgAddress)) ?><br><?php endif; ?>

                    <?php if ($orgPhone !== ''): ?>Telp. <?= e($orgPhone) ?><?php endif; ?>
                    <?php if ($orgPhone !== '' && ($orgEmail !== '' || $orgWebsite !== '')): ?> &middot; <?php endif; ?>
                    <?php if ($orgEmail !== ''): ?><?= e($orgEmail) ?><?php endif; ?>
                    <?php if ($orgEmail !== '' && $orgWebsite !== ''): ?> &middot; <?php endif; ?>
                    <?php if ($orgWebsite !== ''): ?><?= e($orgWebsite) ?><?php endif; ?>
                    <?php if ($orgAddress === '' && $orgPhone === '' && $orgEmail === '' && $orgWebsite === ''): ?>
                        Bimbingan belajar untuk jenjang sekolah.
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="print-doc-title">
            <h2>Invoice</h2>
            <div class="print-doc-number"><?= e($invoice['invoice_number']) ?></div>
            <div class="print-doc-status <?= e($statusClass) ?>"><?= e(invoice_status_label($status)) ?></div>
        </div>
    </div>

    <div class="print-info-grid">
        <div class="print-info-block">
            <h3>Ditagihkan kepada</h3>
            <p class="print-strong"><?= e($invoice['student_name']) ?></p>
            <p><?= e($invoice['student_code']) ?></p>
        </div>
        <div class="print-info-block" style="text-align:right;">
            <h3>Detail Tagihan</h3>
            <p>Terbit: <strong><?= e(tanggal_id((string) $invoice['issue_date'])) ?></strong></p>
            <p>Jatuh tempo: <strong><?= e(tanggal_id((string) $invoice['due_date'])) ?></strong></p>
            <p>Paket: <strong>Paket <?= e((string) (int) $invoice['package_number']) ?></strong></p>
        </div>
    </div>

    <table class="print-table">
        <thead>
        <tr>
            <th>Deskripsi</th>
            <th class="text-end">Nominal</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>
                <span class="print-item-title">
                    Paket Belajar <?= e((string) (int) $invoice['package_number']) ?>
                    — <?= e((string) (int) $invoice['meeting_target']) ?> pertemuan
                </span>
                <span class="print-item-sub">
                    <?= e($invoice['group_name']) ?> &middot;
                    <?= e(package_type_label((string) $invoice['group_type'])) ?> &middot;
                    <?= e((string) (int) $invoice['member_count']) ?> siswa
                </span>
            </td>
            <td class="text-end"><?= e(rupiah((float) $invoice['unit_amount'])) ?></td>
        </tr>
        <?php if ((float) $invoice['discount_amount'] > 0): ?>
            <tr>
                <td><span class="print-item-title">Diskon</span></td>
                <td class="text-end">- <?= e(rupiah((float) $invoice['discount_amount'])) ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="print-totals">
        <div class="print-totals-row">
            <span>Subtotal</span>
            <span><?= e(rupiah((float) $invoice['unit_amount'])) ?></span>
        </div>
        <?php if ((float) $invoice['discount_amount'] > 0): ?>
            <div class="print-totals-row is-discount">
                <span>Diskon</span>
                <span>- <?= e(rupiah((float) $invoice['discount_amount'])) ?></span>
            </div>
        <?php endif; ?>
        <div class="print-totals-row is-total">
            <span>Total Tagihan</span>
            <span><?= e(rupiah((float) $invoice['total_amount'])) ?></span>
        </div>
    </div>

    <?php if ($isPaid && $payment !== null): ?>
        <div class="print-note">
            <strong>Sudah lunas</strong> pada <?= e(tanggal_id((string) $payment['payment_date'], true)) ?>
            melalui <?= e(payment_method_label((string) $payment['method'])) ?>.
            Nota pembayaran: <strong><?= e($payment['payment_number']) ?></strong>.
            Pembayaran dilakukan penuh sesuai total tagihan.
        </div>
    <?php elseif ($status === 'belum_bayar'): ?>
        <div class="print-note">
            Pembayaran wajib <strong>lunas penuh</strong> sebesar
            <strong><?= e(rupiah((float) $invoice['total_amount'])) ?></strong>.
            Tidak ada cicilan atau pembayaran sebagian. Tagihan ini menjadi bukti resmi paket belajar.
        </div>
    <?php else: ?>
        <div class="print-note">
            Tagihan ini telah <strong>dibatalkan</strong>
            <?php if ($invoice['cancel_reason'] !== null): ?>
                dengan alasan: <?= e((string) $invoice['cancel_reason']) ?>
            <?php endif; ?>.
            Dokumen tetap disimpan sebagai riwayat dan tidak dapat dibayar.
        </div>
    <?php endif; ?>

    <div class="print-sign">
        <div class="print-sign-box">
            <div class="print-sign-space"></div>
            <div class="print-sign-name"><?= e($invoice['created_by_name'] ?: 'Admin') ?></div>
            <div>Petugas Penerbit</div>
        </div>
        <div class="print-sign-box">
            <div class="print-sign-space"></div>
            <div class="print-sign-name"><?= e($orgName) ?></div>
            <div>Bagian Keuangan</div>
        </div>
    </div>

    <div class="print-footer">
        <span>Dicetak <?= e(tanggal_waktu_id(date('Y-m-d H:i:s'))) ?></span>
        <span class="print-verified">Dokumen resmi <?= e($orgName) ?></span>
    </div>
</div>

</body>
</html>
