<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
require_role(['admin', 'petugas']);

$mode = get_string('mode') === 'group' ? 'group' : 'single';
$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];
$v = static fn (string $key, string $default = ''): string => old_value($old, [], $key, $default);

$groups = Group::all();

if ($mode === 'group') {
    $groupOptions = Group::activeOptions();
    $selectedGroupId = get_int('group_id');
    if ($selectedGroupId <= 0 && isset($old['group_id'])) {
        $selectedGroupId = (int) $old['group_id'];
    }
    $overview = $selectedGroupId > 0 ? StudentPackage::groupMembershipOverview($selectedGroupId) : null;
    if ($overview !== null && (int) $overview['group']['is_active'] !== 1) {
        $overview = null;
    }

    $readyMembers = [];
    $selectedIds = [];
    if ($overview !== null) {
        foreach ($overview['members'] as $member) {
            if ($member['is_ready']) {
                $readyMembers[] = $member;
            }
        }
        if (array_key_exists('membership_ids', $old)) {
            $selectedIds = array_map('intval', (array) $old['membership_ids']);
            $readyIds = array_map(
                static fn (array $member): int => (int) $member['membership_id'],
                $readyMembers
            );
            $selectedIds = array_values(array_intersect($selectedIds, $readyIds));
        } else {
            $selectedIds = array_map(
                static fn (array $member): int => (int) $member['membership_id'],
                $readyMembers
            );
        }
    }

    $defaultStart = $v('start_date', date('Y-m-d'));
    $baseAmount = null;
    if ($overview !== null) {
        $baseAmount = Pricing::findApplicable(
            (string) $overview['group']['group_type'],
            max(1, (int) $overview['group']['member_count']),
            valid_date($defaultStart) ? $defaultStart : date('Y-m-d')
        );
    }
} else {
    $memberships = StudentPackage::membershipsWithoutPackage();

    $grouped = [];
    foreach ($memberships as $membership) {
        $grouped[(string) $membership['group_name']][] = $membership;
    }
    ksort($grouped);

    $preselect = get_int('membership_id');
    if (!array_key_exists('group_member_id', $old) && $preselect > 0) {
        $old['group_member_id'] = (string) $preselect;
    }
}

$pageTitle = 'Buat Paket Belajar';
$pageSubtitle = 'Pilih pembuatan per siswa atau sekaligus untuk seluruh anggota satu kelompok.';
$breadcrumbs = [
    ['label' => 'Paket Belajar', 'url' => url('admin/paket/index.php')],
    ['label' => 'Buat Paket'],
];
$activeMenu = 'paket';
$pageStyles = ['vendor/select2/select2.min.css', 'vendor/select2/select2-bootstrap-5-theme.min.css'];
$pageScripts = ['vendor/select2/select2.min.js'];

require_once ROOT_PATH . '/includes/header.php';
?>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $mode === 'single' ? 'active' : '' ?>"
           href="<?= e(url('admin/paket/tambah.php')) ?>">
            <i class="bi bi-person-plus me-1"></i>Per Siswa
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $mode === 'group' ? 'active' : '' ?>"
           href="<?= e(url('admin/paket/tambah.php?mode=group')) ?>">
            <i class="bi bi-people me-1"></i>Per Group (Massal)
        </a>
    </li>
</ul>

<?php if ($mode === 'group'): ?>
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" action="<?= e(url('admin/paket/tambah.php')) ?>" class="row g-2 align-items-end">
                <input type="hidden" name="mode" value="group">
                <div class="col-lg-8">
                    <label for="group_id" class="form-label">Pilih kelompok</label>
                    <select class="form-select" id="group_id" name="group_id" onchange="this.form.submit()">
                        <option value="">Pilih kelompok yang akan dibuatkan paket</option>
                        <?php foreach ($groupOptions as $option): ?>
                            <option value="<?= (int) $option['id'] ?>"
                                <?= $selectedGroupId === (int) $option['id'] ? 'selected' : '' ?>>
                                <?= e($option['name']) ?> — <?= e(package_type_label((string) $option['group_type'])) ?>
                                · <?= (int) $option['member_count'] ?> siswa
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-arrow-right-circle me-1"></i>Tampilkan Anggota
                    </button>
                    <a href="<?= e(url('admin/paket/tambah.php')) ?>" class="btn btn-light" title="Mode per siswa">
                        <i class="bi bi-person"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedGroupId <= 0): ?>
        <?php if ($groupOptions === []): ?>
            <div class="alert alert-warning d-flex gap-2 align-items-start">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                <div>
                    <strong>Belum ada kelompok aktif.</strong>
                    Aktifkan kelompok dan tambahkan anggota terlebih dahulu melalui
                    <a href="<?= e(url('admin/group/index.php')) ?>" class="alert-link">Data Group</a>.
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Pilih Kelompok</h2>
                        <div class="card-subtitle">Paket tetap dimiliki per siswa, dibuat sekaligus untuk satu kelompok.</div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Kelompok</th>
                                <th>Jenis</th>
                                <th class="text-center">Anggota</th>
                                <th>Mata Pelajaran</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($groupOptions as $option): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($option['name']) ?></div>
                                        <div class="text-muted small"><?= e($option['code']) ?></div>
                                    </td>
                                    <td><?= e(package_type_label((string) $option['group_type'])) ?></td>
                                    <td class="text-center"><?= (int) $option['member_count'] ?></td>
                                    <td class="small text-muted"><?= e((string) ($option['subject_names'] ?? '—')) ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-primary"
                                           href="<?= e(url('admin/paket/tambah.php?mode=group&group_id=' . (int) $option['id'])) ?>">
                                            <i class="bi bi-box-seam me-1"></i>Pilih
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php elseif ($overview === null): ?>
        <div class="alert alert-danger d-flex gap-2 align-items-start">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>
                <strong><?= e($errors['group_id'] ?? 'Kelompok tidak ditemukan atau sudah nonaktif.') ?></strong>
                <a href="<?= e(url('admin/paket/tambah.php?mode=group')) ?>" class="alert-link">Pilih kelompok lain</a>.
            </div>
        </div>
    <?php else: ?>
        <?php
        $groupInfo = $overview['group'];
        $members = $overview['members'];
        $readyCount = count($readyMembers);
        $baseValue = $baseAmount !== null ? (float) $baseAmount['amount'] : 0.0;
        ?>
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap gap-3 justify-content-between align-items-center">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h2 class="h5 mb-0"><?= e($groupInfo['name']) ?></h2>
                        <span class="badge bg-teal-soft text-teal"><?= e(package_type_label((string) $groupInfo['group_type'])) ?></span>
                        <span class="badge bg-light text-dark"><?= e($groupInfo['code']) ?></span>
                    </div>
                    <div class="text-muted small">
                        <?= (int) $groupInfo['member_count'] ?> anggota aktif
                        · <?= $readyCount ?> siap dibuatkan paket
                    </div>
                </div>
                <a href="<?= e(url('admin/group/detail.php?id=' . (int) $groupInfo['id'] . '&tab=anggota')) ?>"
                   class="btn btn-light btn-sm">
                    <i class="bi bi-people me-1"></i>Kelola Anggota
                </a>
            </div>
        </div>

        <?php if ($readyCount === 0): ?>
            <div class="alert alert-info d-flex gap-2 align-items-start">
                <i class="bi bi-info-circle-fill mt-1"></i>
                <div>
                    <strong>Semua anggota sudah memiliki paket aktif atau nonaktif.</strong>
                    Paket berikutnya dibuat otomatis saat paket berjalan mencapai 8 pertemuan.
                    Tambahkan anggota baru melalui <a href="<?= e(url('admin/group/anggota.php')) ?>" class="alert-link">Anggota Group</a>.
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-lg-8">
                <form method="post" action="<?= e(url('admin/paket/proses.php')) ?>" id="bulk-form"
                      data-submit-once novalidate
                      data-base="<?= e((string) $baseValue) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_group">
                    <input type="hidden" name="group_id" value="<?= (int) $groupInfo['id'] ?>">

                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h2 class="card-title">Paket Massal per Group</h2>
                                <div class="card-subtitle">Satu paket 8 pertemuan untuk setiap siswa terpilih.</div>
                            </div>
                            <span class="badge bg-teal-soft text-teal"><i class="bi bi-people me-1"></i>Massal</span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($errors['form'])): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($errors['form']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($errors['group_id'])): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($errors['group_id']) ?>
                                </div>
                            <?php endif; ?>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="start_date" class="form-label">Tanggal mulai paket <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control<?= field_invalid($errors, 'start_date') ?>"
                                           id="start_date" name="start_date"
                                           value="<?= e($v('start_date', date('Y-m-d'))) ?>" required>
                                    <?= field_error($errors, 'start_date') ?>
                                    <div class="form-text">
                                        Siswa yang masuk setelah tanggal ini memakai tanggal masuknya sendiri.
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="discount" class="form-label">Diskon per siswa (Rp)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" inputmode="numeric"
                                               class="form-control<?= field_invalid($errors, 'discount') ?>"
                                               id="discount" name="discount" value="<?= e($v('discount', '0')) ?>"
                                               placeholder="0" autocomplete="off">
                                    </div>
                                    <?= field_error($errors, 'discount') ?>
                                    <div class="form-text">Opsional, berlaku untuk setiap tagihan siswa.</div>
                                </div>
                                <div class="col-12">
                                    <label for="notes" class="form-label">Catatan</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="500"
                                              placeholder="Catatan pembuatan paket (opsional)"><?= e($v('notes')) ?></textarea>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <div class="fw-semibold">Pilih siswa</div>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="check-all"
                                           <?= $readyCount > 0 ? 'checked' : 'disabled' ?>>
                                    <label class="form-check-label small" for="check-all">Pilih semua yang siap</label>
                                </div>
                            </div>

                            <?php if (!empty($errors['membership_ids'])): ?>
                                <div class="alert alert-danger py-2">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($errors['membership_ids']) ?>
                                </div>
                            <?php endif; ?>

                            <div class="table-responsive border rounded">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th style="width:44px"></th>
                                        <th>Siswa</th>
                                        <th>Masuk</th>
                                        <th>Status</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($members as $member): ?>
                                        <?php
                                        $memberId = (int) $member['membership_id'];
                                        $isReady = (bool) $member['is_ready'];
                                        $isChecked = $isReady && in_array($memberId, $selectedIds, true);
                                        ?>
                                        <tr class="<?= $isReady ? '' : 'text-muted' ?>">
                                            <td>
                                                <input class="form-check-input js-member" type="checkbox"
                                                       name="membership_ids[]" value="<?= $memberId ?>"
                                                       id="member-<?= $memberId ?>"
                                                       <?= $isChecked ? 'checked' : '' ?>
                                                       <?= $isReady ? '' : 'disabled' ?>>
                                            </td>
                                            <td>
                                                <label class="mb-0 <?= $isReady ? '' : 'text-muted' ?>" for="member-<?= $memberId ?>">
                                                    <span class="fw-semibold"><?= e($member['student_name']) ?></span>
                                                    <span class="text-muted small">· <?= e($member['student_code']) ?></span>
                                                    <?php if ($member['school_class'] !== null && $member['school_class'] !== ''): ?>
                                                        <span class="text-muted small">· <?= e($member['school_class']) ?></span>
                                                    <?php endif; ?>
                                                </label>
                                                <div class="small text-muted">
                                                    Paket ke-<?= (int) $member['last_package_number'] + 1 ?>
                                                </div>
                                            </td>
                                            <td class="small"><?= e(tanggal_short_id((string) $member['membership_start'])) ?></td>
                                            <td>
                                                <?php if ($isReady): ?>
                                                    <span class="badge bg-success-subtle text-success-emphasis">Siap</span>
                                                <?php elseif ($member['has_active_package']): ?>
                                                    <span class="badge bg-warning-subtle text-warning-emphasis">Punya paket aktif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Siswa nonaktif</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-success" <?= $readyCount === 0 ? 'disabled' : '' ?>>
                                <i class="bi bi-check2-all me-1"></i>Buat Paket untuk Siswa Terpilih
                            </button>
                            <a href="<?= e(url('admin/paket/index.php?group_id=' . (int) $groupInfo['id'])) ?>"
                               class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Kembali
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Ringkasan Massal</h2>
                            <div class="card-subtitle">Estimasi sebelum disimpan.</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-7 fw-normal text-muted">Anggota aktif</dt>
                            <dd class="col-5 text-end fw-semibold"><?= (int) $groupInfo['member_count'] ?></dd>
                            <dt class="col-7 fw-normal text-muted">Siap dibuatkan</dt>
                            <dd class="col-5 text-end fw-semibold"><?= $readyCount ?></dd>
                            <dt class="col-7 fw-normal text-muted">Terpilih</dt>
                            <dd class="col-5 text-end fw-semibold" id="selected-count"><?= count($selectedIds) ?></dd>
                            <dt class="col-7 fw-normal text-muted">Tarif per siswa</dt>
                            <dd class="col-5 text-end fw-semibold">
                                <?= $baseAmount !== null ? e(rupiah($baseValue)) : '<span class="text-warning">Belum ada</span>' ?>
                            </dd>
                            <dt class="col-7 fw-normal text-muted">Estimasi total</dt>
                            <dd class="col-5 text-end fw-bold" id="bulk-total">
                                <?= $baseAmount !== null ? e(rupiah($baseValue * count($selectedIds))) : '—' ?>
                            </dd>
                        </dl>
                        <?php if ($baseAmount === null): ?>
                            <div class="alert alert-warning small mt-3 mb-0">
                                Tarif untuk jenis group ini belum tersedia. Paket tetap dibuat,
                                tetapi tagihan menyusul setelah tarif diatur.
                            </div>
                        <?php else: ?>
                            <div class="form-text mt-2">Estimasi belum memperhitungkan diskon.</div>
                        <?php endif; ?>
                    </div>
                    <?php if (is_admin()): ?>
                        <div class="card-footer bg-white">
                            <a href="<?= e(url('admin/tarif/index.php')) ?>" class="btn btn-light btn-sm w-100">
                                <i class="bi bi-cash-coin me-1"></i>Kelola Tarif
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Ketentuan Massal</h2>
                            <div class="card-subtitle">Dijaga oleh server dan database.</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <ul class="rule-list">
                            <li>
                                <span class="rule-icon rule-check"><i class="bi bi-people"></i></span>
                                <span>Paket dan tagihan tetap dimiliki <strong>per siswa</strong>, bukan satu tagihan untuk group.</span>
                            </li>
                            <li>
                                <span class="rule-icon rule-lock"><i class="bi bi-shield-check"></i></span>
                                <span>Siswa yang sudah memiliki paket aktif dilewati otomatis agar tidak terjadi paket ganda.</span>
                            </li>
                            <li>
                                <span class="rule-icon rule-lock"><i class="bi bi-arrow-repeat"></i></span>
                                <span>Bila satu siswa gagal, seluruh pembuatan massal dibatalkan agar data tidak setengah jadi.</span>
                            </li>
                            <li>
                                <span class="rule-icon rule-lock"><i class="bi bi-8-circle"></i></span>
                                <span>Setiap paket tetap berisi target 8 pertemuan aktual dari seluruh mata pelajaran.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <?php if ($memberships === []): ?>
        <div class="alert alert-warning d-flex gap-2 align-items-start">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>
                <strong>Belum ada keanggotaan yang dapat dibuatkan paket.</strong>
                Semua siswa aktif mungkin sudah memiliki paket berjalan, atau belum ditempatkan ke kelompok.
                Kelola anggota pada <a href="<?= e(url('admin/group/anggota.php')) ?>" class="alert-link">Anggota Group</a>.
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <div class="col-lg-8">
                <form method="post" action="<?= e(url('admin/paket/proses.php')) ?>" data-submit-once novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">

                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h2 class="card-title">Informasi Paket</h2>
                                <div class="card-subtitle">Tarif mengikuti jumlah anggota aktif pada tanggal mulai.</div>
                            </div>
                            <span class="badge bg-teal-soft text-teal"><i class="bi bi-box-seam me-1"></i>Paket</span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($errors['form'])): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($errors['form']) ?>
                                </div>
                            <?php endif; ?>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="group_member_id" class="form-label">Siswa &amp; kelompok <span class="text-danger">*</span></label>
                                    <select class="form-select js-select2<?= field_invalid($errors, 'group_member_id') ?>"
                                            id="group_member_id" name="group_member_id" required
                                            data-placeholder="Pilih siswa pada kelompok">
                                        <option value="">Pilih siswa pada kelompok</option>
                                        <?php foreach ($grouped as $groupName => $items): ?>
                                            <optgroup label="<?= e($groupName) ?>">
                                                <?php foreach ($items as $membership): ?>
                                                    <option value="<?= (int) $membership['membership_id'] ?>"
                                                        <?= $v('group_member_id') === (string) $membership['membership_id'] ? 'selected' : '' ?>>
                                                        <?= e($membership['student_name']) ?> — <?= e($membership['student_code']) ?>
                                                        · <?= e(package_type_label((string) $membership['group_type'])) ?>
                                                        · <?= (int) $membership['member_count'] ?> siswa
                                                        · masuk <?= e(tanggal_short_id((string) $membership['membership_start'])) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                    <?= field_error($errors, 'group_member_id') ?>
                                    <div class="form-text">
                                        Hanya keanggotaan aktif tanpa paket berjalan. Paket berikutnya dibuat otomatis
                                        saat paket sebelumnya mencapai 8 pertemuan.
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="start_date" class="form-label">Tanggal mulai paket <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control<?= field_invalid($errors, 'start_date') ?>"
                                           id="start_date" name="start_date" value="<?= e($v('start_date', date('Y-m-d'))) ?>" required>
                                    <?= field_error($errors, 'start_date') ?>
                                    <div class="form-text">Tidak boleh sebelum tanggal masuk keanggotaan.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="discount" class="form-label">Diskon (Rp)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" inputmode="numeric" class="form-control<?= field_invalid($errors, 'discount') ?>"
                                               id="discount" name="discount" value="<?= e($v('discount', '0')) ?>"
                                               placeholder="0" autocomplete="off">
                                    </div>
                                    <?= field_error($errors, 'discount') ?>
                                    <div class="form-text">Opsional. Disimpan sebagai snapshot pada tagihan.</div>
                                </div>
                                <div class="col-12">
                                    <label for="notes" class="form-label">Catatan</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="500"
                                              placeholder="Catatan pembuatan paket (opsional)"><?= e($v('notes')) ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check2-circle me-1"></i>Simpan &amp; Terbitkan Tagihan
                            </button>
                            <a href="<?= e(url('admin/paket/index.php')) ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Kembali
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Tarif Berlaku</h2>
                            <div class="card-subtitle">Nominal per paket 8 pertemuan.</div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead><tr><th>Jenis</th><th>Siswa</th><th class="text-end">Tarif</th></tr></thead>
                                <tbody>
                                <?php $hasRule = false; ?>
                                <?php foreach ($groups as $group): ?>
                                    <?php
                                    $count = 0;
                                    foreach ($memberships as $membership) {
                                        if ((int) $membership['group_id'] === (int) $group['id']) {
                                            $count = (int) $membership['member_count'];
                                            break;
                                        }
                                    }
                                    if ($count === 0) {
                                        continue;
                                    }
                                    $rule = Pricing::findApplicable((string) $group['group_type'], $count, date('Y-m-d'));
                                    if ($rule === null) {
                                        continue;
                                    }
                                    $hasRule = true;
                                    ?>
                                    <tr>
                                        <td><?= e(package_type_label((string) $group['group_type'])) ?></td>
                                        <td><?= e((string) $count) ?></td>
                                        <td class="text-end"><?= e(rupiah((float) $rule['amount'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$hasRule): ?>
                                    <tr><td colspan="3" class="text-muted small p-3">
                                        Belum ada tarif berlaku. Paket tetap dapat dibuat, tetapi tagihan baru diterbitkan
                                        setelah tarif diatur.
                                    </td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if (is_admin()): ?>
                        <div class="card-footer bg-white">
                            <a href="<?= e(url('admin/tarif/index.php')) ?>" class="btn btn-light btn-sm w-100">
                                <i class="bi bi-cash-coin me-1"></i>Kelola Tarif
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Ketentuan Paket</h2>
                            <div class="card-subtitle">Dijaga oleh server dan database.</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <ul class="rule-list">
                            <li>
                                <span class="rule-icon rule-check"><i class="bi bi-8-circle"></i></span>
                                <span>Satu paket = 8 pertemuan aktual dari seluruh mata pelajaran kelompok.</span>
                            </li>
                            <li>
                                <span class="rule-icon rule-lock"><i class="bi bi-person-check"></i></span>
                                <span>Satu siswa hanya boleh memiliki satu paket aktif per keanggotaan.</span>
                            </li>
                            <li>
                                <span class="rule-icon rule-lock"><i class="bi bi-receipt"></i></span>
                                <span>Tagihan diterbitkan sekali per paket dan tidak berubah bila tarif berubah.</span>
                            </li>
                            <li>
                                <span class="rule-icon rule-lock"><i class="bi bi-cash-stack"></i></span>
                                <span>Paket berikutnya otomatis aktif saat paket ini mencapai 8 pertemuan; pembayaran tagihan tidak menghalangi paket.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
(function () {
    'use strict';

    const discount = document.getElementById('discount');
    if (discount) {
        discount.addEventListener('input', () => {
            const digits = discount.value.replace(/[^0-9]/g, '');
            discount.value = digits ? Number(digits).toLocaleString('id-ID') : '';
            recalc();
        });
    }

    const form = document.getElementById('bulk-form');
    if (!form) {
        return;
    }

    const base = Number(form.dataset.base || 0);
    const boxes = Array.from(document.querySelectorAll('.js-member:not(:disabled)'));
    const checkAll = document.getElementById('check-all');
    const selectedCount = document.getElementById('selected-count');
    const totalEl = document.getElementById('bulk-total');

    const money = (value) => 'Rp' + Math.round(value).toLocaleString('id-ID');

    function recalc() {
        const selected = boxes.filter((box) => box.checked).length;
        if (selectedCount) {
            selectedCount.textContent = String(selected);
        }
        if (totalEl) {
            if (base <= 0) {
                totalEl.textContent = '—';
            } else {
                const raw = discount ? discount.value.replace(/[^0-9]/g, '') : '0';
                const cut = Math.min(base, Number(raw || 0));
                totalEl.textContent = money(Math.max(0, base - cut) * selected);
            }
        }
        if (checkAll) {
            checkAll.checked = boxes.length > 0 && selected === boxes.length;
            checkAll.indeterminate = selected > 0 && selected < boxes.length;
        }
    }

    boxes.forEach((box) => box.addEventListener('change', recalc));

    if (checkAll) {
        checkAll.addEventListener('change', () => {
            boxes.forEach((box) => { box.checked = checkAll.checked; });
            recalc();
        });
    }

    recalc();
})();
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
