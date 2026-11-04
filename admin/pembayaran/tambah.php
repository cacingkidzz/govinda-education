<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$invoiceId = get_int('invoice_id');
$studentId = get_int('student_id');

$presetInvoice = null;
if ($invoiceId > 0) {
    $presetInvoice = Invoice::find($invoiceId);
    if ($presetInvoice === null) {
        abort_404('Tagihan tidak ditemukan.');
    }
    if ((string) $presetInvoice['status'] !== 'belum_bayar') {
        flash(
            'warning',
            'Tagihan ' . $presetInvoice['invoice_number'] . ' berstatus '
            . invoice_status_label((string) $presetInvoice['status']) . ' sehingga tidak dapat dibayar.'
        );
        redirect('admin/tagihan/detail.php?id=' . $invoiceId);
    }
    $studentId = (int) $presetInvoice['student_id'];
}

$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];
$v = static fn (string $key, string $default = ''): string => old_value($old, [], $key, $default);

$paymentDate = $v('payment_date', date('Y-m-d'));
$methodValue = $v('method', Payment::METHOD_CASH);
$referenceValue = $v('reference');
$notesValue = $v('notes');

$selectedIds = [];
if (isset($old['invoice_ids']) && is_array($old['invoice_ids'])) {
    foreach ($old['invoice_ids'] as $rawSelected) {
        $selected = (int) $rawSelected;
        if ($selected > 0) {
            $selectedIds[$selected] = $selected;
        }
    }
} elseif ($invoiceId > 0) {
    $selectedIds[$invoiceId] = $invoiceId;
}

$debtors = array_values(array_filter(
    Invoice::debtByStudent(['invoice_type' => Invoice::TYPE_PACKAGE]),
    static fn (array $row): bool => (int) $row['unpaid_count'] > 0
));

$studentInvoices = [];
$student = null;
$debt = null;
if ($studentId > 0) {
    $studentInvoices = Invoice::all([
        'status'       => 'belum_bayar',
        'invoice_type' => Invoice::TYPE_PACKAGE,
        'student_id'   => $studentId,
    ]);
    if ($studentInvoices !== []) {
        $firstInvoice = $studentInvoices[0];
        $student = [
            'name' => (string) $firstInvoice['student_name'],
            'code' => (string) $firstInvoice['student_code'],
        ];
    }
    $debt = Invoice::debtSummary([
        'student_id'   => $studentId,
        'invoice_type' => Invoice::TYPE_PACKAGE,
    ]);
    $availableIds = [];
    foreach ($studentInvoices as $row) {
        $availableIds[(int) $row['id']] = true;
    }
    $selectedIds = array_filter(
        $selectedIds,
        static fn (int $id): bool => isset($availableIds[$id])
    );
}

$allUnpaid = $studentId > 0
    ? []
    : Invoice::all(['status' => 'belum_bayar', 'invoice_type' => Invoice::TYPE_PACKAGE]);

$pageTitle = $studentId > 0 ? 'Catat Pelunasan' : 'Pilih Tagihan';
$pageSubtitle = $studentId > 0
    ? 'Satu pembayaran dapat melunasi beberapa tagihan sekaligus. Setiap tagihan tetap wajib lunas penuh.'
    : 'Pilih siswa untuk melihat tagihan yang belum dibayar, atau bayar satu tagihan langsung dari daftar.';
$breadcrumbs = [
    ['label' => 'Pembayaran', 'url' => url('admin/pembayaran/index.php')],
    ['label' => $studentId > 0 ? 'Catat Pelunasan' : 'Pilih Tagihan'],
];
$activeMenu = 'pembayaran';
$pageStyles = ['vendor/datatables/datatables.min.css'];
$pageScripts = [
    'vendor/datatables/datatables.min.js',
    'vendor/datatables/datatables.bootstrap5.min.js',
];

require_once ROOT_PATH . '/includes/header.php';
?>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>Pembayaran belum dapat disimpan
        </div>
        <?php foreach ($errors as $message): ?>
            <div class="small"><?= e($message) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($studentId <= 0): ?>
    <div class="alert alert-info d-flex gap-2 align-items-start">
        <i class="bi bi-info-circle-fill mt-1"></i>
        <div class="small">
            Hanya tagihan berstatus <strong>Belum Bayar</strong> yang dapat dilunasi. Utang siswa tidak
            menghalangi paket belajar berikutnya; pembayaran tetap dapat dicatat kapan saja.
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" action="<?= e(url('admin/pembayaran/tambah.php')) ?>" class="row g-2 align-items-end">
                <div class="col-lg-8">
                    <label for="student_id" class="form-label">Pilih siswa dengan tagihan belum dibayar</label>
                    <select class="form-select" id="student_id" name="student_id" data-auto-submit>
                        <option value="">— Pilih siswa —</option>
                        <?php foreach ($debtors as $debtor): ?>
                            <option value="<?= (int) $debtor['student_id'] ?>"
                                <?= $studentId === (int) $debtor['student_id'] ? 'selected' : '' ?>>
                                <?= e($debtor['student_name']) ?> (<?= e($debtor['student_code']) ?>)
                                — <?= e((string) (int) $debtor['unpaid_count']) ?> tagihan,
                                <?= e(rupiah((float) $debtor['debt_amount'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i>Tampilkan Tagihan
                    </button>
                    <a href="<?= e(url('admin/tagihan/index.php')) ?>" class="btn btn-light">
                        <i class="bi bi-receipt me-1"></i>Semua Tagihan
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Tagihan Belum Dibayar</h2>
                <div class="card-subtitle">
                    <?= e(number_format(count($allUnpaid), 0, ',', '.')) ?> tagihan menunggu pelunasan.
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if ($allUnpaid === []): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-check2-all"></i></div>
                    <div class="empty-state-title">Tidak ada tagihan yang menunggu pembayaran</div>
                    <p class="empty-state-text">
                        Semua tagihan paket sudah lunas atau dibatalkan. Tagihan baru terbit otomatis
                        setiap paket 8 pertemuan berikutnya dibuat.
                    </p>
                    <a href="<?= e(url('admin/tagihan/index.php')) ?>" class="btn btn-light">
                        <i class="bi bi-receipt me-1"></i>Lihat Semua Tagihan
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive master-table-wrap">
                    <table class="table table-modern table-hover align-middle js-datatable w-100">
                        <thead>
                        <tr>
                            <th class="no-sort" style="width:40px;">No.</th>
                            <th>Nomor Invoice</th>
                            <th>Siswa</th>
                            <th>Kelompok</th>
                            <th class="no-sort">Paket</th>
                            <th>Jatuh Tempo</th>
                            <th class="text-end">Total</th>
                            <th class="no-sort" style="width:180px;">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($allUnpaid as $index => $row): ?>
                            <tr>
                                <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                                <td>
                                    <a href="<?= e(url('admin/tagihan/detail.php?id=' . (int) $row['id'])) ?>"
                                       class="invoice-number text-decoration-none">
                                        <?= e($row['invoice_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="cell-title"><?= e($row['student_name']) ?></span>
                                    <span class="cell-sub font-monospace"><?= e($row['student_code']) ?></span>
                                </td>
                                <td><?= e($row['group_name']) ?></td>
                                <td class="text-nowrap">Paket <?= e((string) (int) $row['package_number']) ?></td>
                                <td class="text-nowrap">
                                    <?= e(tanggal_short_id((string) $row['due_date'])) ?>
                                    <?php if ((string) $row['due_date'] < date('Y-m-d')): ?>
                                        <span class="badge text-bg-danger d-block mt-1">Lewat tempo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end amount fw-semibold"><?= e(rupiah((float) $row['total_amount'])) ?></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="<?= e(url('admin/pembayaran/tambah.php?invoice_id=' . (int) $row['id'])) ?>"
                                           class="btn btn-success btn-sm">
                                            <i class="bi bi-cash-stack me-1"></i>Bayar
                                        </a>
                                        <a href="<?= e(url('admin/pembayaran/tambah.php?student_id=' . (int) $row['student_id'])) ?>"
                                           class="btn btn-light btn-sm">
                                            <i class="bi bi-list-check me-1"></i>Beberapa
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php elseif ($studentInvoices === []): ?>
    <?php
    $studentName = '';
    $studentCode = '';
    if ($presetInvoice !== null) {
        $studentName = (string) $presetInvoice['student_name'];
        $studentCode = (string) $presetInvoice['student_code'];
    }
    ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-check2-all"></i></div>
                <div class="empty-state-title">Tidak ada tagihan belum dibayar untuk siswa ini</div>
                <p class="empty-state-text">
                    <?php if ($studentName !== ''): ?>
                        <?= e($studentName) ?> (<?= e($studentCode) ?>) tidak memiliki tagihan yang menunggu pembayaran.
                    <?php else: ?>
                        Siswa ini tidak memiliki tagihan paket yang belum dibayar.
                    <?php endif; ?>
                </p>
                <a href="<?= e(url('admin/pembayaran/tambah.php')) ?>" class="btn btn-light">
                    <i class="bi bi-arrow-left me-1"></i>Pilih Siswa Lain
                </a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <form method="post" action="<?= e(url('admin/pembayaran/proses.php')) ?>"
                  data-submit-once novalidate id="paymentForm"
                  data-confirm="Simpan pelunasan penuh untuk tagihan yang dipilih?"
                  data-confirm-title="Konfirmasi pelunasan"
                  data-confirm-button="Ya, simpan pelunasan"
                  data-confirm-icon="question">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="pay">

                <div class="card mb-3">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Pilih Tagihan yang Dilunasi</h2>
                            <div class="card-subtitle">
                                Centang satu atau beberapa tagihan. Setiap tagihan dibayar penuh.
                            </div>
                        </div>
                        <span class="badge text-bg-success">WAJIB LUNAS</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-modern align-middle mb-0">
                                <thead>
                                <tr>
                                    <th class="no-sort" style="width:44px;">
                                        <input type="checkbox" class="form-check-input" id="checkAll"
                                               aria-label="Pilih semua tagihan">
                                    </th>
                                    <th>Invoice</th>
                                    <th>Paket</th>
                                    <th>Jatuh tempo</th>
                                    <th class="text-end">Total</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($studentInvoices as $row): ?>
                                    <?php $rowId = (int) $row['id']; ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input js-invoice-check"
                                                   name="invoice_ids[]" value="<?= $rowId ?>"
                                                   data-amount="<?= e(number_format((float) $row['total_amount'], 2, '.', '')) ?>"
                                                   aria-label="Pilih invoice <?= e($row['invoice_number']) ?>"
                                                <?= isset($selectedIds[$rowId]) ? 'checked' : '' ?>>
                                        </td>
                                        <td>
                                            <a href="<?= e(url('admin/tagihan/detail.php?id=' . $rowId)) ?>"
                                               class="invoice-number text-decoration-none">
                                                <?= e($row['invoice_number']) ?>
                                            </a>
                                            <span class="cell-sub">
                                                <?= e($row['group_name']) ?>
                                                &middot; <?= e(package_type_label((string) $row['group_type'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            Paket <?= e((string) (int) $row['package_number']) ?>
                                            <span class="cell-sub"><?= e((string) (int) $row['meeting_target']) ?> pertemuan</span>
                                        </td>
                                        <td class="text-nowrap">
                                            <?= e(tanggal_short_id((string) $row['due_date'])) ?>
                                            <?php if ((string) $row['due_date'] < date('Y-m-d')): ?>
                                                <span class="badge text-bg-danger d-block mt-1">Lewat tempo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end amount fw-semibold"><?= e(rupiah((float) $row['total_amount'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                <tr>
                                    <th colspan="4" class="text-end">Total tagihan dipilih</th>
                                    <th class="text-end amount" id="selectedTotal">Rp0</th>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Form Pelunasan</h2>
                            <div class="card-subtitle">Jumlah terkunci sesuai total tagihan yang dipilih.</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="payment_date" class="form-label">Tanggal pembayaran <span class="text-danger">*</span></label>
                                <input type="date" class="form-control<?= field_invalid($errors, 'payment_date') ?>"
                                       id="payment_date" name="payment_date" value="<?= e($paymentDate) ?>"
                                       max="<?= e(date('Y-m-d')) ?>" required>
                                <?= field_error($errors, 'payment_date') ?>
                            </div>
                            <div class="col-md-6">
                                <label for="method" class="form-label">Metode pembayaran <span class="text-danger">*</span></label>
                                <select class="form-select<?= field_invalid($errors, 'method') ?>" id="method" name="method" required>
                                    <?php foreach (Payment::methodOptions() as $option): ?>
                                        <option value="<?= e($option) ?>" <?= $methodValue === $option ? 'selected' : '' ?>>
                                            <?= e(payment_method_label($option)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($errors, 'method') ?>
                            </div>

                            <div class="col-md-6">
                                <label for="amount_display" class="form-label">Jumlah dibayar <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control amount-locked"
                                           id="amount_display" value="0"
                                           readonly aria-readonly="true">
                                    <input type="hidden" name="amount" id="amount" value="0">
                                </div>
                                <div class="form-text">
                                    Harus sama persis dengan total tagihan yang dipilih. Kurang atau lebih akan ditolak server.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="reference" class="form-label">Nomor referensi</label>
                                <input type="text" class="form-control" id="reference" name="reference"
                                       maxlength="100" value="<?= e($referenceValue) ?>"
                                       placeholder="Opsional, mis. nomor transfer">
                                <div class="form-text">Boleh dikosongkan untuk pembayaran tunai.</div>
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Catatan</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"
                                          maxlength="500" placeholder="Catatan pembayaran (opsional)"><?= e($notesValue) ?></textarea>
                            </div>

                            <div class="col-12">
                                <div class="alert alert-warning small mb-0">
                                    <i class="bi bi-shield-check me-1"></i>
                                    Sistem memeriksa ulang jumlah, status tagihan, dan pembayaran ganda di server
                                    dalam satu transaksi database. Setiap tagihan memperoleh alokasi penuh.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                        <button type="submit" class="btn btn-success" id="submitPayment">
                            <i class="bi bi-check2-circle me-1"></i>Simpan Pelunasan
                        </button>
                        <a href="<?= e(url('admin/pembayaran/tambah.php')) ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Batal
                        </a>
                        <span class="small text-secondary ms-auto" id="paymentHint" role="status"></span>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Ringkasan Siswa</h2>
                        <div class="card-subtitle"><?= e((string) $student['code']) ?></div>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="detail-list mb-0">
                        <div class="detail-row">
                            <dt>Siswa</dt>
                            <dd><span class="cell-title"><?= e((string) $student['name']) ?></span></dd>
                        </div>
                        <div class="detail-row">
                            <dt>Total tagihan</dt>
                            <dd class="amount"><?= e(rupiah((float) $debt['billed_amount'])) ?></dd>
                        </div>
                        <div class="detail-row">
                            <dt>Total dibayar</dt>
                            <dd class="amount text-success"><?= e(rupiah((float) $debt['received_amount'])) ?></dd>
                        </div>
                        <div class="detail-row">
                            <dt>Total utang</dt>
                            <dd class="amount fw-semibold fs-5 text-danger"><?= e(rupiah((float) $debt['debt_amount'])) ?></dd>
                        </div>
                        <div class="detail-row">
                            <dt>Tagihan belum dibayar</dt>
                            <dd><?= e((string) (int) $debt['unpaid_count']) ?> tagihan</dd>
                        </div>
                    </dl>
                    <div class="form-text mt-2">
                        Utang tidak menghalangi paket belajar berikutnya. Pembayaran dapat dilakukan
                        untuk sebagian tagihan atau sekaligus, asalkan setiap tagihan lunas penuh.
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Aturan Pembayaran</h2>
                        <div class="card-subtitle">Tidak ada cicilan.</div>
                    </div>
                </div>
                <div class="card-body small text-secondary">
                    <ul class="mb-0 ps-3">
                        <li>Satu pembayaran boleh melunasi beberapa tagihan milik siswa yang sama.</li>
                        <li>Jumlah dibayar harus sama persis dengan total tagihan yang dipilih.</li>
                        <li>Setiap tagihan mendapat alokasi penuh dan berstatus lunas.</li>
                        <li>Tagihan lunas atau dibatalkan tidak dapat dibayar lagi.</li>
                        <li>Nota diterbitkan setelah pembayaran berhasil dan memuat rincian semua tagihan.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        'use strict';
        const checks = Array.from(document.querySelectorAll('.js-invoice-check'));
        const checkAll = document.getElementById('checkAll');
        const display = document.getElementById('amount_display');
        const hidden = document.getElementById('amount');
        const totalCell = document.getElementById('selectedTotal');
        const form = document.getElementById('paymentForm');
        const hint = document.getElementById('paymentHint');

        const formatRupiah = (value) => 'Rp' + value.toLocaleString('id-ID');

        const sync = () => {
            let total = 0;
            let count = 0;
            checks.forEach((check) => {
                if (check.checked) {
                    total += parseFloat(check.dataset.amount || '0');
                    count++;
                }
            });
            display.value = total.toLocaleString('id-ID');
            hidden.value = total.toFixed(2);
            totalCell.textContent = formatRupiah(total);
            if (checkAll) {
                checkAll.checked = checks.length > 0 && count === checks.length;
                checkAll.indeterminate = count > 0 && count < checks.length;
            }
            if (hint) {
                hint.textContent = count === 0
                    ? 'Centang minimal satu tagihan di atas, lalu klik Simpan Pelunasan.'
                    : count + ' tagihan dipilih. Jumlah di bawah terkunci sesuai total tagihan.';
                hint.classList.toggle('text-danger', count === 0);
            }
            if (form) {
                form.setAttribute(
                    'data-confirm',
                    'Simpan pelunasan penuh ' + formatRupiah(total) + ' untuk ' + count + ' tagihan yang dipilih?'
                );
            }
        };

        checks.forEach((check) => check.addEventListener('change', sync));

        if (checkAll) {
            checkAll.addEventListener('change', () => {
                checks.forEach((check) => {
                    check.checked = checkAll.checked;
                });
                sync();
            });
        }

        if (form) {
            form.addEventListener('submit', (event) => {
                const selected = checks.filter((check) => check.checked);
                if (selected.length === 0) {
                    // Hentikan submit sebelum handler global menonaktifkan tombol.
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    window.alert('Pilih minimal satu tagihan yang akan dilunasi.');
                    return;
                }
                // Pastikan nominal selalu sinkron dengan pilihan terakhir.
                hidden.value = selected
                    .reduce((sum, check) => sum + parseFloat(check.dataset.amount || '0'), 0)
                    .toFixed(2);
            });
        }

        sync();
    })();
    </script>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
