<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$id = get_int('id');
$package = $id > 0 ? StudentPackage::find($id) : null;
if ($package === null) {
    abort_404('Paket belajar tidak ditemukan.');
}

$meetings = StudentPackage::meetings($id);
$invoices = Invoice::forPackage($id);
$activeInvoice = Invoice::activeForPackage($id);
$history = StudentPackage::forMembership((int) $package['group_member_id']);

$target = max(1, (int) $package['target_meetings']);
$completed = (int) $package['completed_meetings'];
$remaining = max(0, $target - $completed);
$percent = StudentPackage::progressPercent($completed, $target);
$isCancelled = (string) $package['status'] === StudentPackage::STATUS_DIBATALKAN;
$isAdmin = is_admin();

$state = pull_form_state();
$errors = $state['errors'];

$pageTitle = 'Detail Paket ' . (int) $package['package_number'];
$pageSubtitle = 'Riwayat pertemuan, tagihan, dan pembayaran paket belajar siswa.';
$breadcrumbs = [
    ['label' => 'Paket Belajar', 'url' => url('admin/paket/index.php')],
    ['label' => 'Paket ' . (int) $package['package_number']],
];
$activeMenu = 'paket';
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
            <i class="bi bi-exclamation-triangle-fill me-1"></i>Aksi belum dapat diproses
        </div>
        <?php foreach ($errors as $message): ?>
            <div class="small"><?= e($message) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Paket <?= e((string) (int) $package['package_number']) ?></h2>
                    <div class="card-subtitle">
                        <?= e($package['student_name']) ?> &middot; <?= e($package['student_code']) ?>
                    </div>
                </div>
                <?= package_status_badge((string) $package['status']) ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <dl class="detail-list mb-0">
                            <div class="detail-row">
                                <dt>Kelompok</dt>
                                <dd>
                                    <a href="<?= e(url('admin/group/detail.php?id=' . (int) $package['group_id'])) ?>"
                                       class="link-dark text-decoration-none">
                                        <?= e($package['group_name']) ?>
                                    </a>
                                    <div class="cell-sub"><?= e($package['group_code']) ?></div>
                                </dd>
                            </div>
                            <div class="detail-row">
                                <dt>Jenis kelompok</dt>
                                <dd>
                                    <?= e(package_type_label((string) $package['group_type'])) ?>
                                    &middot; <?= e((string) (int) $package['member_count']) ?> siswa
                                </dd>
                            </div>
                            <div class="detail-row">
                                <dt>Tanggal mulai</dt>
                                <dd><?= e(tanggal_id((string) $package['start_date'], true)) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt><?= $isCancelled ? 'Ditutup' : 'Perkiraan selesai' ?></dt>
                                <dd>
                                    <?php if ($package['end_date'] !== null): ?>
                                        <?= e(tanggal_id((string) $package['end_date'], true)) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Berjalan</span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="detail-list mb-0">
                            <div class="detail-row">
                                <dt>Tarif dasar</dt>
                                <dd class="amount"><?= e(rupiah((float) $package['base_amount'])) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Diskon</dt>
                                <dd class="amount">
                                    <?= (float) $package['discount_amount'] > 0
                                        ? '- ' . e(rupiah((float) $package['discount_amount']))
                                        : '—' ?>
                                </dd>
                            </div>
                            <div class="detail-row">
                                <dt>Total paket</dt>
                                <dd class="amount fw-semibold"><?= e(rupiah((float) $package['total_amount'])) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Dibuat oleh</dt>
                                <dd><?= e($package['created_by_name'] ?: '-') ?></dd>
                            </div>
                        </dl>
                    </div>

                    <div class="col-12">
                        <div class="package-progress card-panel">
                            <div class="d-flex justify-content-between align-items-end mb-2">
                                <div>
                                    <div class="stat-label">Progres pertemuan</div>
                                    <div class="fw-semibold fs-5">
                                        <?= e((string) $completed) ?> / <?= e((string) $target) ?> pertemuan
                                    </div>
                                </div>
                                <div class="text-end small text-muted">
                                        <span class="badge text-bg-light border">
                                            <?= e((string) $percent) ?>%
                                        </span>
                                    <div>Sisa <?= e((string) $remaining) ?> pertemuan</div>
                                </div>
                            </div>
                            <div class="progress" style="height:12px;" role="progressbar"
                                 aria-valuenow="<?= $completed ?>" aria-valuemin="0"
                                 aria-valuemax="<?= $target ?>">
                                <div class="progress-bar <?= $percent >= 100 ? 'bg-success' : ($percent >= 75 ? 'bg-warning' : 'bg-primary') ?>"
                                     style="width:<?= $percent ?>%"></div>
                            </div>
                            <div class="form-text mt-2">
                                Dihitung dari pertemuan berstatus Terlaksana pada seluruh mata pelajaran
                                kelompok ini. Kehadiran siswa tidak memengaruhi hitungan.
                            </div>
                        </div>
                    </div>

                    <?php if ($package['notes'] !== null && trim((string) $package['notes']) !== ''): ?>
                        <div class="col-12">
                            <div class="alert alert-light border mb-0 small">
                                <strong>Catatan:</strong> <?= e((string) $package['notes']) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($isCancelled): ?>
                        <div class="col-12">
                            <div class="alert alert-danger mb-0 small">
                                <strong>Paket ditutup.</strong>
                                <?= e((string) ($package['cancel_reason'] ?? '-')) ?>
                                <?php if ($package['cancelled_at'] !== null): ?>
                                    &middot; <?= e(tanggal_waktu_id((string) $package['cancelled_at'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Tagihan Aktif</h2>
                    <div class="card-subtitle">Satu paket hanya memiliki satu tagihan aktif.</div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($activeInvoice !== null): ?>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <a href="<?= e(url('admin/tagihan/detail.php?id=' . (int) $activeInvoice['id'])) ?>"
                               class="invoice-number text-decoration-none">
                                <?= e($activeInvoice['invoice_number']) ?>
                            </a>
                            <div class="cell-sub">Jatuh tempo
                                <?= e(tanggal_short_id((string) $activeInvoice['due_date'])) ?></div>
                        </div>
                        <?= invoice_status_badge((string) $activeInvoice['status']) ?>
                    </div>
                    <div class="amount fs-5 fw-semibold mb-3">
                        <?= e(rupiah((float) $activeInvoice['total_amount'])) ?>
                    </div>
                    <div class="d-grid gap-2">
                        <?php if ((string) $activeInvoice['status'] === 'belum_bayar'): ?>
                            <a href="<?= e(url('admin/pembayaran/tambah.php?invoice_id=' . (int) $activeInvoice['id'])) ?>"
                               class="btn btn-success">
                                <i class="bi bi-cash-stack me-1"></i>Catat Pelunasan
                            </a>
                        <?php elseif ((string) $activeInvoice['status'] === 'lunas'): ?>
                            <a href="<?= e(url('admin/tagihan/detail.php?id=' . (int) $activeInvoice['id'])) ?>"
                               class="btn btn-light">
                                <i class="bi bi-receipt me-1"></i>Lihat Tagihan &amp; Nota
                            </a>
                        <?php endif; ?>
                        <a href="<?= e(url('admin/tagihan/cetak.php?id=' . (int) $activeInvoice['id'])) ?>"
                           class="btn btn-outline-secondary" target="_blank" rel="noopener">
                            <i class="bi bi-printer me-1"></i>Cetak Invoice
                        </a>
                    </div>
                <?php elseif ($isCancelled): ?>
                    <div class="text-muted small">
                        Paket sudah ditutup sehingga tidak memiliki tagihan aktif.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Paket ini belum memiliki tagihan aktif. Terbitkan setelah tarif sesuai jumlah anggota
                        kelompok tersedia.
                    </div>
                    <form method="post" action="<?= e(url('admin/paket/proses.php')) ?>"
                          data-confirm="Tagihan paket ini akan diterbitkan berdasarkan tarif yang berlaku saat ini."
                          data-confirm-title="Terbitkan tagihan"
                          data-confirm-button="Ya, terbitkan"
                          data-confirm-icon="question" data-submit-once>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="issue">
                        <input type="hidden" name="id" value="<?= (int) $package['id'] ?>">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-receipt me-1"></i>Terbitkan Tagihan
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isAdmin && !$isCancelled): ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Tindakan Admin</h2>
                        <div class="card-subtitle">Selalu memerlukan alasan dan tercatat di log.</div>
                    </div>
                </div>
                <div class="card-body">
                    <details class="admin-action mb-2">
                        <summary class="small fw-semibold text-secondary">
                            <i class="bi bi-sliders me-1"></i>Hitung ulang progres dari riwayat
                        </summary>
                        <form method="post" action="<?= e(url('admin/paket/proses.php')) ?>"
                              class="mt-2" data-submit-once>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="recount">
                            <input type="hidden" name="id" value="<?= (int) $package['id'] ?>">
                            <label for="recount_reason" class="form-label small">Alasan perbaikan data <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm mb-2" id="recount_reason"
                                   name="reason" maxlength="255" required
                                   placeholder="Contoh: koreksi setelah pembatalan realisasi">
                            <button type="submit" class="btn btn-warning btn-sm">
                                <i class="bi bi-arrow-repeat me-1"></i>Hitung Ulang
                            </button>
                            <div class="form-text">
                                Hitungan diambil ulang dari tabel riwayat pertemuan. Tidak boleh mengubah
                                riwayat secara manual.
                            </div>
                        </form>
                    </details>

                    <details class="admin-action">
                        <summary class="small fw-semibold text-danger">
                            <i class="bi bi-x-octagon me-1"></i>Tutup paket
                        </summary>
                        <form method="post" action="<?= e(url('admin/paket/proses.php')) ?>"
                              class="mt-2"
                              data-confirm="Paket akan ditutup dan tagihannya yang belum dibayar dibatalkan. Tindakan ini tidak menghapus riwayat."
                              data-confirm-title="Tutup paket"
                              data-confirm-button="Ya, tutup paket"
                              data-confirm-icon="warning" data-submit-once>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="id" value="<?= (int) $package['id'] ?>">
                            <label for="cancel_reason" class="form-label small">Alasan penutupan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm mb-2" id="cancel_reason"
                                   name="reason" maxlength="255" required
                                   placeholder="Contoh: siswa berhenti belajar">
                            <label for="cancel_date" class="form-label small">Tanggal penutupan</label>
                            <input type="date" class="form-control form-control-sm mb-2" id="cancel_date"
                                   name="end_date" value="<?= e(date('Y-m-d')) ?>"
                                   min="<?= e((string) $package['start_date']) ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="bi bi-x-octagon me-1"></i>Tutup Paket
                            </button>
                            <div class="form-text">
                                Paket yang sudah memiliki pembayaran berhasil tidak dapat ditutup.
                            </div>
                        </form>
                    </details>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <div>
            <h2 class="card-title">Riwayat Pertemuan</h2>
            <div class="card-subtitle">
                <?= e(number_format(count($meetings), 0, ',', '.')) ?> pertemuan terhitung pada paket ini.
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($meetings === []): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-calendar-check"></i></div>
                <div class="empty-state-title">Belum ada pertemuan terhitung</div>
                <p class="empty-state-text">
                    Catat realisasi pertemuan pada menu Realisasi Pertemuan. Hanya status Terlaksana yang
                    menambah hitungan paket.
                </p>
                <a href="<?= e(url('admin/realisasi-pertemuan/index.php')) ?>" class="btn btn-primary">
                    <i class="bi bi-check2-square me-1"></i>Buka Realisasi Pertemuan
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive master-table-wrap">
                <table class="table table-modern table-hover align-middle js-datatable w-100">
                    <thead>
                    <tr>
                        <th class="no-sort" style="width:40px;">No.</th>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru</th>
                        <th>Status</th>
                        <th>Catatan</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($meetings as $index => $meeting): ?>
                        <tr>
                            <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                            <td class="text-nowrap">
                                <span class="cell-title"><?= e(tanggal_short_id((string) $meeting['meeting_date'])) ?></span>
                                <span class="cell-sub">
                                    <?= e(hari_id((int) date('N', strtotime((string) $meeting['meeting_date'])))) ?>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <?= e(time_label((string) $meeting['start_time'])) ?>–<?= e(time_label((string) $meeting['end_time'])) ?>
                            </td>
                            <td><?= e($meeting['subject_name']) ?></td>
                            <td><?= e($meeting['teacher_name']) ?></td>
                            <td>
                                <?= meeting_status_badge((string) $meeting['status']) ?>
                                <?php if ($meeting['rescheduled_from_id'] !== null): ?>
                                    <span class="badge text-bg-warning">Pengganti</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="cell-sub d-block" style="max-width:260px;"><?= e($meeting['notes'] ?: '-') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Riwayat Tagihan Paket Ini</h2>
                    <div class="card-subtitle">Snapshot nominal tidak berubah walau tarif diperbarui.</div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($invoices === []): ?>
                    <div class="p-4 text-center text-muted small">
                        Belum ada tagihan yang pernah diterbitkan untuk paket ini.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Nomor</th>
                                <th>Terbit</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                                <th>Pembayaran</th>
                                <th class="no-sort text-end">Aksi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(url('admin/tagihan/detail.php?id=' . (int) $invoice['id'])) ?>"
                                           class="invoice-number text-decoration-none">
                                            <?= e($invoice['invoice_number']) ?>
                                        </a>
                                    </td>
                                    <td class="text-nowrap"><?= e(tanggal_short_id((string) $invoice['issue_date'])) ?></td>
                                    <td class="text-end amount"><?= e(rupiah((float) $invoice['total_amount'])) ?></td>
                                    <td><?= invoice_status_badge((string) $invoice['status']) ?></td>
                                    <td>
                                        <?php if ($invoice['payment_number'] !== null): ?>
                                            <span class="cell-title font-monospace"><?= e($invoice['payment_number']) ?></span>
                                            <span class="cell-sub">
                                                <?= e(tanggal_short_id((string) $invoice['payment_date'])) ?>
                                                &middot; <?= e(payment_method_label((string) $invoice['method'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">Belum dibayar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="table-actions justify-content-end">
                                            <a href="<?= e(url('admin/tagihan/cetak.php?id=' . (int) $invoice['id'])) ?>"
                                               class="btn btn-light btn-sm" title="Cetak invoice"
                                               target="_blank" rel="noopener">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <?php if ($invoice['payment_number'] !== null): ?>
                                                <a href="<?= e(url('admin/pembayaran/nota.php?id=' . (int) $invoice['payment_id'])) ?>"
                                                   class="btn btn-info btn-sm" title="Nota pembayaran"
                                                   target="_blank" rel="noopener">
                                                    <i class="bi bi-receipt-cutoff"></i>
                                                </a>
                                            <?php elseif ((string) $invoice['status'] === 'belum_bayar'): ?>
                                                <a href="<?= e(url('admin/pembayaran/tambah.php?invoice_id=' . (int) $invoice['id'])) ?>"
                                                   class="btn btn-success btn-sm" title="Catat pelunasan">
                                                    <i class="bi bi-cash-stack"></i>
                                                </a>
                                            <?php endif; ?>
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
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Riwayat Paket Siswa</h2>
                    <div class="card-subtitle">Semua paket pada keanggotaan ini.</div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Paket</th>
                            <th>Mulai</th>
                            <th class="text-center">Progres</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($history as $item): ?>
                            <tr class="<?= (int) $item['id'] === $id ? 'table-active' : '' ?>">
                                <td>
                                    <?php if ((int) $item['id'] === $id): ?>
                                        <span class="fw-semibold">Paket <?= e((string) (int) $item['package_number']) ?></span>
                                    <?php else: ?>
                                        <a href="<?= e(url('admin/paket/detail.php?id=' . (int) $item['id'])) ?>"
                                           class="link-dark text-decoration-none">
                                            Paket <?= e((string) (int) $item['package_number']) ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap small"><?= e(tanggal_short_id((string) $item['start_date'])) ?></td>
                                <td class="text-center text-nowrap small">
                                    <?= e((string) (int) $item['completed_meetings']) ?>/<?= e((string) (int) $item['target_meetings']) ?>
                                </td>
                                <td><?= package_status_badge((string) $item['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="master-note">
    <i class="bi bi-info-circle"></i>
    <span>Progres paket tidak dapat diubah manual. Perbaikan hanya melalui hitung ulang dari riwayat
        pertemuan yang tercatat, dengan alasan dan jejak pada log aktivitas.</span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
