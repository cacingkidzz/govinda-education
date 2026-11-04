<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$id = get_int('id');
$student = $id > 0 ? Student::find($id) : null;
if ($student === null) {
    abort_404('Data siswa yang Anda cari tidak ditemukan.');
}

$membership = Student::activeMembership($id);
$history = Student::groupHistory($id);
$invoices = Student::invoices($id);
$payments = Student::payments($id);
$hasHistory = Student::hasHistory($id);
$isActive = (int) $student['is_active'] === 1;
$debt = Invoice::debtSummary([
    'student_id'   => $id,
    'invoice_type' => Invoice::TYPE_PACKAGE,
]);
$canManagePayment = in_array((string) (current_user()['role'] ?? ''), ['admin', 'petugas'], true);
$completeness = StudentSelfService::completeness($student);
$publicToken = trim((string) ($student['public_token'] ?? ''));
$hasPublicToken = StudentSelfService::isToken($publicToken);
$publicLink = $hasPublicToken ? StudentSelfService::studentLink($student) : '';

$pageTitle = 'Detail Siswa';
$pageSubtitle = 'Profil lengkap, group aktif, dan riwayat akademik siswa.';
$breadcrumbs = [
    ['label' => 'Data Siswa', 'url' => url('admin/siswa/index.php')],
    ['label' => (string) $student['name']],
];
$activeMenu = 'siswa';

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body text-center profile-card">
                <?= photo_tag($student['photo'] ?? null, (string) $student['name'], 'profile-photo', 124) ?>
                <h2 class="profile-name"><?= e($student['name']) ?></h2>
                <div class="profile-code font-monospace"><?= e($student['code']) ?></div>
                <div class="mt-2">
                    <?= active_badge($isActive) ?>
                    <span class="badge text-bg-light">
                        <i class="bi bi-gender-ambiguous me-1"></i><?= e(gender_label((string) $student['gender'])) ?>
                    </span>
                </div>
                <div class="profile-meta">
                    <span><i class="bi bi-calendar-plus me-1"></i>Bergabung <?= e(tanggal_id($student['join_date'] ?? null)) ?></span>
                    <?php if (!empty($student['school_class'])): ?>
                        <span><i class="bi bi-mortarboard me-1"></i>Kelas <?= e($student['school_class']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="d-grid gap-2 mt-3">
                    <a href="<?= e(url('admin/siswa/edit.php?id=' . $id)) ?>" class="btn btn-warning">
                        <i class="bi bi-pencil-square me-1"></i>Edit Data Siswa
                    </a>
                    <form method="post" action="<?= e(url('admin/siswa/proses.php')) ?>"
                          data-confirm="<?= $isActive
                              ? 'Siswa akan dinonaktifkan dan tidak dapat dimasukkan ke group baru.'
                              : 'Siswa akan diaktifkan kembali dan dapat dimasukkan ke group.' ?>"
                          data-confirm-title="Ubah status siswa"
                          data-confirm-button="<?= $isActive ? 'Ya, nonaktifkan' : 'Ya, aktifkan' ?>"
                          data-confirm-icon="warning" data-submit-once>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="redirect" value="detail">
                        <button type="submit" class="btn <?= $isActive ? 'btn-danger' : 'btn-success' ?> w-100">
                            <i class="bi <?= $isActive ? 'bi-person-dash' : 'bi-person-check' ?> me-1"></i>
                            <?= $isActive ? 'Nonaktifkan Siswa' : 'Aktifkan Siswa' ?>
                        </button>
                    </form>
                    <a href="<?= e(url('admin/siswa/index.php')) ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar
                    </a>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Pendaftaran Mandiri</h2>
                    <div class="card-subtitle">Tautan siswa untuk melengkapi datanya sendiri.</div>
                </div>
                <?= profile_completion_badge($student) ?>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small text-secondary">Kelengkapan data pribadi</span>
                    <span class="small fw-semibold"><?= e((string) $completeness['percent']) ?>%</span>
                </div>
                <div class="progress mb-3" role="progressbar" aria-label="Kelengkapan data pribadi"
                     aria-valuenow="<?= (int) $completeness['percent'] ?>" aria-valuemin="0" aria-valuemax="100"
                     style="height: 8px;">
                    <div class="progress-bar <?= $completeness['complete'] ? 'bg-success' : 'bg-warning' ?>"
                         style="width: <?= (int) $completeness['percent'] ?>%;"></div>
                </div>

                <?php if (!$completeness['complete']): ?>
                    <div class="small text-secondary mb-3">
                        <strong class="text-dark">Belum diisi:</strong>
                        <?= e(implode(', ', $completeness['missing'])) ?>
                    </div>
                <?php endif; ?>

                <dl class="detail-list mb-3">
                    <div class="detail-row">
                        <dt>Diperbarui mandiri</dt>
                        <dd>
                            <?= !empty($student['self_updated_at'])
                                ? e(tanggal_waktu_id((string) $student['self_updated_at']))
                                : 'Belum pernah' ?>
                        </dd>
                    </div>
                    <div class="detail-row">
                        <dt>Data lengkap sejak</dt>
                        <dd>
                            <?= !empty($student['profile_completed_at'])
                                ? e(tanggal_waktu_id((string) $student['profile_completed_at']))
                                : '-' ?>
                        </dd>
                    </div>
                </dl>

                <?php if ($hasPublicToken): ?>
                    <label for="student-link" class="form-label">Tautan pribadi</label>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control font-monospace" id="student-link"
                               value="<?= e($publicLink) ?>" readonly>
                        <button type="button" class="btn btn-outline-primary" data-copy-target="#student-link"
                                title="Salin tautan">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= e($publicLink) ?>" class="btn btn-light btn-sm" target="_blank"
                           rel="noopener noreferrer">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Buka Formulir
                        </a>
                        <form method="post" action="<?= e(url('admin/siswa/proses.php')) ?>" data-submit-once
                              data-confirm="Tautan lama akan langsung tidak berlaku. Bagikan tautan baru setelah ini."
                              data-confirm-title="Ganti tautan siswa"
                              data-confirm-button="Ya, ganti tautan" data-confirm-icon="warning">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="self_link_rotate">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="redirect" value="detail">
                            <button type="submit" class="btn btn-warning btn-sm">
                                <i class="bi bi-arrow-repeat me-1"></i>Ganti Tautan
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info small mb-2" role="status">
                        <i class="bi bi-info-circle me-1"></i>
                        Tautan pribadi belum dibuat. Buat tautan agar siswa dapat mengisi datanya sendiri.
                    </div>
                    <form method="post" action="<?= e(url('admin/siswa/proses.php')) ?>" data-submit-once>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="self_link">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="redirect" value="detail">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-link-45deg me-1"></i>Buat Tautan Siswa
                        </button>
                    </form>
                <?php endif; ?>

                <div class="small text-secondary mt-3">
                    <i class="bi bi-shield-lock me-1"></i>Tautan bersifat rahasia. Kode siswa, group, status,
                    dan tanggal bergabung tidak dapat diubah siswa.
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Orang Tua / Wali</h2>
                    <div class="card-subtitle">Kontak administrasi siswa.</div>
                </div>
            </div>
            <div class="card-body">
                <dl class="detail-list">
                    <div class="detail-row">
                        <dt>Nama</dt>
                        <dd><?= e($student['parent_name'] ?: '-') ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Nomor HP</dt>
                        <dd>
                            <?php if (!empty($student['parent_phone'])): ?>
                                <a href="tel:<?= e(normalize_phone((string) $student['parent_phone'])) ?>">
                                    <?= e(phone_display((string) $student['parent_phone'])) ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Data Sekolah</h2>
                    <div class="card-subtitle">Sekolah asal dan kelas siswa.</div>
                </div>
            </div>
            <div class="card-body">
                <dl class="detail-list">
                    <div class="detail-row">
                        <dt>Sekolah</dt>
                        <dd><?= e($student['school'] ?: '-') ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt>Kelas</dt>
                        <dd><?= e($student['school_class'] ?: '-') ?></dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Identitas Siswa</h2>
                    <div class="card-subtitle">Data tercatat pada sistem.</div>
                </div>
                <span class="badge <?= $isActive ? 'text-bg-success' : 'text-bg-secondary' ?>">
                    <i class="bi <?= $isActive ? 'bi-check-circle' : 'bi-pause-circle' ?> me-1"></i>
                    <?= $isActive ? 'Aktif' : 'Nonaktif' ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <dl class="detail-list">
                            <div class="detail-row">
                                <dt>Kode siswa</dt>
                                <dd class="font-monospace"><?= e($student['code']) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Nama lengkap</dt>
                                <dd><?= e($student['name']) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Jenis kelamin</dt>
                                <dd><?= e(gender_label((string) $student['gender'])) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Tempat, tanggal lahir</dt>
                                <dd>
                                    <?= e($student['birth_place'] ?: '-') ?>
                                    <?php if (!empty($student['birth_date'])): ?>
                                        , <?= e(tanggal_id((string) $student['birth_date'])) ?>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="detail-list">
                            <div class="detail-row">
                                <dt>Nomor HP</dt>
                                <dd>
                                    <?php if (!empty($student['phone'])): ?>
                                        <a href="tel:<?= e(normalize_phone((string) $student['phone'])) ?>">
                                            <?= e(phone_display((string) $student['phone'])) ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <div class="detail-row">
                                <dt>Tanggal bergabung</dt>
                                <dd><?= e(tanggal_id((string) $student['join_date'])) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Terakhir diperbarui</dt>
                                <dd><?= e(tanggal_waktu_id($student['updated_at'] ?? null)) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>Status</dt>
                                <dd><?= active_badge($isActive) ?></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="col-12">
                        <dl class="detail-list">
                            <div class="detail-row">
                                <dt>Alamat</dt>
                                <dd><?= nl2br(e($student['address'] ?: '-')) ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Group Aktif</h2>
                    <div class="card-subtitle">Satu siswa hanya memiliki satu group aktif.</div>
                </div>
                <?php if ($membership !== null): ?>
                    <span class="badge text-bg-primary">
                        <i class="bi bi-people-fill me-1"></i><?= e($membership['group_name']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($membership === null): ?>
                <div class="empty-state py-4">
                    <div class="empty-state-icon"><i class="bi bi-people"></i></div>
                    <div class="empty-state-title">Belum memiliki group aktif</div>
                    <p class="empty-state-text">Tempatkan siswa pada group agar jadwal dan penagihan periode berikutnya dapat dihitung.</p>
                    <?php if ($isActive): ?>
                        <a href="<?= e(url('admin/group/anggota-tambah.php?student_id=' . $id)) ?>" class="btn btn-primary">
                            <i class="bi bi-person-plus me-1"></i>Tambah ke Group
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <dl class="detail-list">
                                <div class="detail-row">
                                    <dt>Group</dt>
                                    <dd><?= e($membership['group_name']) ?> <span class="text-muted">(<?= e($membership['group_code']) ?>)</span></dd>
                                </div>
                                <div class="detail-row">
                                    <dt>Jenis group</dt>
                                    <dd><?= group_type_badge((string) $membership['group_type']) ?></dd>
                                </div>
                                <div class="detail-row">
                                    <dt>Mata pelajaran</dt>
                                    <dd><?= e($membership['subject_names'] ?: '-') ?></dd>
                                </div>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="detail-list">
                                <div class="detail-row">
                                    <dt>Guru utama</dt>
                                    <dd><?= e($membership['teacher_names'] ?: '-') ?></dd>
                                </div>
                                <div class="detail-row">
                                    <dt>Anggota aktif</dt>
                                    <dd><?= capacity_badge((int) $membership['member_count'], (int) $membership['capacity'], (string) $membership['group_type']) ?></dd>
                                </div>
                                <div class="detail-row">
                                    <dt>Mulai</dt>
                                    <dd><?= e(tanggal_id((string) $membership['start_date'])) ?></dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex flex-wrap gap-2">
                    <a href="<?= e(url('admin/group/detail.php?id=' . (int) $membership['group_id'] . '&tab=anggota')) ?>"
                       class="btn btn-light btn-sm">
                        <i class="bi bi-diagram-3 me-1"></i>Detail Group
                    </a>
                    <?php if ($isActive): ?>
                        <a href="<?= e(url('admin/group/pindah.php?student_id=' . $id)) ?>" class="btn btn-warning btn-sm">
                            <i class="bi bi-arrow-left-right me-1"></i>Pindah Group
                        </a>
                        <a href="<?= e(url('admin/group/anggota-akhir.php?membership_id=' . (int) $membership['membership_id'] . '&return=student')) ?>"
                           class="btn btn-danger btn-sm">
                            <i class="bi bi-person-dash me-1"></i>Akhiri Keanggotaan
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Ringkasan Keuangan</h2>
                    <div class="card-subtitle">Total Utang = Total Tagihan - Total Pembayaran yang Dialokasikan.</div>
                </div>
                <?php if ($canManagePayment && $debt['unpaid_count'] > 0): ?>
                    <a href="<?= e(url('admin/pembayaran/tambah.php?student_id=' . $id)) ?>"
                       class="btn btn-success btn-sm">
                        <i class="bi bi-cash-stack me-1"></i>Bayar Tagihan
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="text-secondary small">Total Tagihan</div>
                            <div class="fs-5 fw-semibold amount"><?= e(rupiah($debt['billed_amount'])) ?></div>
                            <div class="cell-sub"><?= e((string) $debt['billed_count']) ?> tagihan paket</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="text-secondary small">Total Dibayar</div>
                            <div class="fs-5 fw-semibold amount text-success"><?= e(rupiah($debt['received_amount'])) ?></div>
                            <div class="cell-sub"><?= e((string) $debt['paid_count']) ?> tagihan lunas</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100 border-danger-subtle">
                            <div class="text-secondary small">Total Utang</div>
                            <div class="fs-5 fw-semibold amount text-danger"><?= e(rupiah($debt['debt_amount'])) ?></div>
                            <div class="cell-sub">Sisa yang belum dibayar</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="text-secondary small">Tagihan Belum Dibayar</div>
                            <div class="fs-5 fw-semibold"><?= e((string) $debt['unpaid_count']) ?> paket</div>
                            <div class="cell-sub"><?= e(rupiah($debt['unpaid_amount'])) ?></div>
                        </div>
                    </div>
                </div>
                <?php if ($debt['debt_amount'] > 0): ?>
                    <div class="alert alert-warning small mt-3 mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Siswa memiliki utang berjalan. Utang <strong>tidak menghalangi</strong> paket belajar
                        berikutnya; pembayaran tetap dapat dicatat kapan saja dan akan dialokasikan per tagihan.
                    </div>
                <?php else: ?>
                    <div class="alert alert-success small mt-3 mb-0">
                        <i class="bi bi-check2-circle me-1"></i>
                        Tidak ada utang paket belajar yang tersisa untuk siswa ini.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Riwayat Group</h2>
                    <div class="card-subtitle">Seluruh keanggotaan group, termasuk yang sudah selesai.</div>
                </div>
                <span class="badge bg-teal-soft text-teal"><?= e((string) count($history)) ?> riwayat</span>
            </div>
            <div class="card-body p-0">
                <?php if ($history === []): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-clock-history"></i></div>
                        <div class="empty-state-title">Belum ada riwayat group</div>
                        <p class="empty-state-text">Riwayat akan tercatat setelah siswa ditempatkan pada group.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 40px;">No.</th>
                                <th>Group</th>
                                <th>Jenis</th>
                                <th>Guru</th>
                                <th>Mata Pelajaran</th>
                                <th>Mulai</th>
                                <th>Keluar</th>
                                <th>Alasan / Catatan</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($history as $index => $row): ?>
                                <tr>
                                    <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                                    <td>
                                        <a href="<?= e(url('admin/group/detail.php?id=' . (int) $row['group_id'] . '&tab=riwayat')) ?>"
                                           class="cell-title link-dark text-decoration-none">
                                            <?= e($row['group_name']) ?>
                                        </a>
                                        <span class="cell-sub font-monospace"><?= e($row['group_code']) ?></span>
                                    </td>
                                    <td><?= group_type_badge((string) $row['group_type']) ?></td>
                                    <td><?= e($row['teacher_names'] ?: '-') ?></td>
                                    <td><?= e($row['subject_names'] ?: '-') ?></td>
                                    <td><?= e(tanggal_id((string) $row['start_date'])) ?></td>
                                    <td><?= e($row['end_date'] ? tanggal_id((string) $row['end_date']) : '-') ?></td>
                                    <td><span class="cell-sub d-block" style="max-width: 240px;"><?= e($row['reason'] ?: '-') ?></span></td>
                                    <td><?= membership_status_badge($row['end_date'] !== null ? (string) $row['end_date'] : null) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Riwayat Tagihan</h2>
                    <div class="card-subtitle">Tagihan yang pernah diterbitkan untuk siswa ini.</div>
                </div>
                <span class="badge bg-teal-soft text-teal"><?= e((string) count($invoices)) ?> tagihan</span>
            </div>
            <div class="card-body p-0">
                <?php if ($invoices === []): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-receipt"></i></div>
                        <div class="empty-state-title">Belum ada tagihan</div>
                        <p class="empty-state-text">Tagihan paket akan muncul otomatis saat paket belajar siswa dibuat.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Nomor</th>
                                <th>Paket</th>
                                <th>Periode</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Dibayar</th>
                                <th>Jatuh Tempo</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td class="invoice-number"><?= e($invoice['invoice_number']) ?></td>
                                    <td class="text-nowrap">
                                        <?php if ((string) $invoice['invoice_type'] === Invoice::TYPE_PACKAGE): ?>
                                            Paket <?= e((string) (int) $invoice['package_number']) ?>
                                            <span class="cell-sub"><?= e((string) (int) $invoice['meeting_target']) ?> pertemuan</span>
                                        <?php else: ?>
                                            <?= e(invoice_type_label((string) $invoice['invoice_type'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($invoice['period_label']) ?></td>
                                    <td class="text-end amount"><?= e(rupiah($invoice['total_amount'])) ?></td>
                                    <td class="text-end amount">
                                        <?= e(rupiah((float) $invoice['paid_amount'])) ?>
                                        <?php if (!empty($invoice['payment_number'])): ?>
                                            <span class="cell-sub font-monospace"><?= e($invoice['payment_number']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e(tanggal_id($invoice['due_date'] ?? null)) ?></td>
                                    <td><?= invoice_status_badge((string) $invoice['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Riwayat Pembayaran</h2>
                    <div class="card-subtitle">Pembayaran lunas yang tercatat untuk siswa ini.</div>
                </div>
                <span class="badge bg-teal-soft text-teal"><?= e((string) count($payments)) ?> pembayaran</span>
            </div>
            <div class="card-body p-0">
                <?php if ($payments === []): ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-cash-stack"></i></div>
                        <div class="empty-state-title">Belum ada pembayaran</div>
                        <p class="empty-state-text">Pembayaran muncul setelah pelunasan tagihan dicatat.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Nomor Nota</th>
                                <th>Tanggal</th>
                                <th>Tagihan</th>
                                <th>Metode</th>
                                <th class="text-end">Jumlah</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td class="invoice-number">
                                        <?php if ($canManagePayment): ?>
                                            <a href="<?= e(url('admin/pembayaran/detail.php?id=' . (int) $payment['id'])) ?>"
                                               class="text-decoration-none"><?= e($payment['payment_number']) ?></a>
                                        <?php else: ?>
                                            <?= e($payment['payment_number']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e(tanggal_id((string) $payment['payment_date'])) ?></td>
                                    <td>
                                        <span class="small"><?= e((string) ($payment['invoice_number'] ?? '')) ?: '-' ?></span>
                                        <?php if ((int) ($payment['invoice_count'] ?? 0) > 1): ?>
                                            <span class="badge text-bg-light"><?= e((string) (int) $payment['invoice_count']) ?> tagihan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e(payment_method_label((string) $payment['method'])) ?></td>
                                    <td class="text-end amount"><?= e(rupiah($payment['amount'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="master-note">
    <i class="bi bi-shield-lock"></i>
    <span>
        <?= $hasHistory
            ? 'Siswa ini memiliki riwayat akademik/keuangan sehingga hanya dapat dinonaktifkan, tidak dihapus permanen.'
            : 'Siswa tanpa riwayat tetap tidak dihapus permanen; gunakan status nonaktif untuk menonaktifkan akses akademik.' ?>
    </span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
