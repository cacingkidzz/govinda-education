<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$id = get_int('id');
$payment = $id > 0 ? Payment::receipt($id) : null;
if ($payment === null) {
    abort_404('Pembayaran tidak ditemukan atau belum berhasil.');
}

$invoices = is_array($payment['invoices'] ?? null) ? $payment['invoices'] : [];
$invoiceNumbers = [];
$groupNames = [];
$allocatedTotal = 0.0;
foreach ($invoices as $invoiceRow) {
    $invoiceNumbers[] = (string) $invoiceRow['invoice_number'];
    $groupNames[(string) $invoiceRow['group_name']] = true;
    $allocatedTotal += (float) $invoiceRow['allocated_amount'];
}
$groupLabel = $groupNames === [] ? '-' : implode(', ', array_keys($groupNames));

$orgName = Profile::name();
$orgTagline = Profile::tagline();
$orgAddress = Profile::address();
$orgPhone = Profile::phone();
$orgEmail = Profile::email();
$orgWebsite = Profile::websiteLabel();
$logo = Profile::logoUrl();
$officer = ($payment['received_by_name'] ?: $payment['officer_name']) ?: '-';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nota <?= e($payment['payment_number']) ?> — <?= e($orgName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/print.css')) ?>">
</head>
<body class="print-body">

<div class="print-toolbar">
    <a class="btn btn-light" href="<?= e(url('admin/pembayaran/detail.php?id=' . (int) $payment['id'])) ?>">Kembali</a>
    <button type="button" class="btn btn-primary" onclick="window.print()">Cetak / Simpan PDF (A5)</button>
</div>

<div class="print-sheet print-sheet--a5">
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
                </div>
            </div>
        </div>
        <div class="print-doc-title">
            <h2>Nota</h2>
            <div class="print-doc-number"><?= e($payment['payment_number']) ?></div>
            <div class="print-doc-status is-paid">Lunas</div>
        </div>
    </div>

    <div class="print-info-grid">
        <div class="print-info-block">
            <h3>Diterima dari</h3>
            <p class="print-strong"><?= e($payment['student_name']) ?></p>
            <p><?= e($payment['student_code']) ?></p>
            <p><?= e($groupLabel) ?></p>
        </div>
        <div class="print-info-block" style="text-align:right;">
            <h3>Waktu Pembayaran</h3>
            <p><?= e(tanggal_id((string) $payment['payment_date'], true)) ?></p>
            <p><?= e($payment['paid_at'] !== null ? substr((string) $payment['paid_at'], 11, 5) : '-') ?> WIB</p>
            <p>Metode: <strong><?= e(payment_method_label((string) $payment['method'])) ?></strong></p>
        </div>
    </div>

    <table class="print-table">
        <thead>
        <tr>
            <th>Rincian Tagihan</th>
            <th class="text-end">Nominal</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($invoices === []): ?>
            <tr>
                <td>
                    <span class="print-item-title">Pembayaran paket belajar</span>
                    <span class="print-item-sub">Tanpa rincian tagihan</span>
                </td>
                <td class="text-end"><?= e(rupiah((float) $payment['amount'])) ?></td>
            </tr>
        <?php else: ?>
            <?php foreach ($invoices as $invoiceRow): ?>
                <tr>
                    <td>
                        <span class="print-item-title">
                            Paket Belajar <?= e((string) (int) $invoiceRow['package_number']) ?>
                            — <?= e((string) (int) $invoiceRow['meeting_target']) ?> pertemuan
                        </span>
                        <span class="print-item-sub">
                            <?= e($invoiceRow['group_name']) ?> &middot;
                            <?= e(package_type_label((string) $invoiceRow['group_type'])) ?> &middot;
                            <?= e((string) (int) $invoiceRow['member_count']) ?> siswa
                        </span>
                        <span class="print-item-sub">Invoice <?= e($invoiceRow['invoice_number']) ?></span>
                        <?php if ((float) $invoiceRow['discount_amount'] > 0): ?>
                            <span class="print-item-sub">
                                Tarif <?= e(rupiah((float) $invoiceRow['unit_amount'])) ?> &middot;
                                diskon <?= e(rupiah((float) $invoiceRow['discount_amount'])) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= e(rupiah((float) $invoiceRow['allocated_amount'])) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="print-totals">
        <div class="print-totals-row">
            <span>Jumlah tagihan</span>
            <span><?= e((string) count($invoices)) ?> tagihan</span>
        </div>
        <div class="print-totals-row">
            <span>Total dialokasikan</span>
            <span><?= e(rupiah($invoices === [] ? (float) $payment['amount'] : $allocatedTotal)) ?></span>
        </div>
        <div class="print-totals-row is-total">
            <span>Dibayar Lunas</span>
            <span><?= e(rupiah((float) $payment['amount'])) ?></span>
        </div>
    </div>

    <div class="print-note">
        Pembayaran diterima <strong>lunas penuh</strong> untuk seluruh tagihan yang tercantum dan tidak ada
        sisa tagihan pada invoice tersebut. Nota ini adalah bukti sah pembayaran paket belajar.
        <?php if (!empty($payment['reference_number'])): ?>
            Referensi: <?= e((string) $payment['reference_number']) ?>.
        <?php endif; ?>
        Terima kasih atas kepercayaan Anda kepada <?= e($orgName) ?>.
    </div>

    <div class="print-sign">
        <div class="print-sign-box">
            <div class="print-sign-space"></div>
            <div class="print-sign-name"><?= e($officer) ?></div>
            <div>Petugas Penerima</div>
        </div>
        <div class="print-sign-box">
            <div class="print-sign-space"></div>
            <div class="print-sign-name"><?= e($orgName) ?></div>
            <div>Bagian Keuangan</div>
        </div>
    </div>

    <div class="print-footer">
        <span>Nota <?= e($payment['payment_number']) ?> &middot; Invoice <?= e(implode(', ', $invoiceNumbers)) ?></span>
        <span class="print-verified">Lunas</span>
    </div>
</div>

</body>
</html>
