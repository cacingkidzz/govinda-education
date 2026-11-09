<?php
declare(strict_types=1);

/**
 * Rekap pembayaran per group.
 *
 * Menampilkan uang yang benar-benar diterima dari setiap siswa dalam satu
 * group, lalu menjumlahkannya menjadi total per group. Nominal diambil dari
 * alokasi pembayaran ke tagihan (payment_allocations) agar pembayaran yang
 * melunasi beberapa tagihan sekaligus tidak terhitung ganda.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_common.php';
require_login();
require_role(['admin', 'petugas']);

$range = report_date_range();
$dateFrom = $range['from'];
$dateTo = $range['to'];

$method = get_string('method');
$method = in_array($method, Payment::methodOptions(), true) ? $method : '';
$groupId = get_string('group_id');
$groupId = ctype_digit($groupId) ? $groupId : '';
$includeEmpty = get_string('include_empty') === '1';

$filters = [
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'method'    => $method,
    'group_id'  => $groupId,
];

$report = Payment::groupReport($filters);
$groups = Group::all();
$groupsById = [];
foreach ($groups as $group) {
    $groupsById[(int) $group['id']] = $group;
}

// Group tanpa pembayaran tetap ditampilkan bila diminta.
if ($includeEmpty) {
    $existing = [];
    foreach ($report as $item) {
        $existing[(int) $item['group_id']] = true;
    }
    foreach ($groups as $group) {
        $id = (int) $group['id'];
        if (isset($existing[$id])) {
            continue;
        }
        if ($groupId !== '' && $id !== (int) $groupId) {
            continue;
        }
        $report[] = [
            'group_id'           => $id,
            'group_name'         => (string) $group['name'],
            'students'           => [],
            'student_count'      => 0,
            'payment_count'      => 0,
            'invoice_count'      => 0,
            'total_amount'       => 0.0,
            'first_payment_date' => null,
            'last_payment_date'  => null,
        ];
    }
    usort(
        $report,
        static fn (array $a, array $b): int => strcmp((string) $a['group_name'], (string) $b['group_name'])
    );
}

$totalReceived = 0.0;
$totalStudents = 0;
$totalPayments = 0;
$totalInvoices = 0;
$groupsPaid = 0;
foreach ($report as $item) {
    $totalReceived += (float) $item['total_amount'];
    $totalStudents += (int) $item['student_count'];
    $totalPayments += (int) $item['payment_count'];
    $totalInvoices += (int) $item['invoice_count'];
    if ((int) $item['student_count'] > 0) {
        $groupsPaid++;
    }
}
$averagePerStudent = $totalStudents > 0 ? $totalReceived / $totalStudents : 0.0;

$hasFilter = !$range['isDefault'] || $method !== '' || $groupId !== '' || $includeEmpty;

$csvParams = array_merge([
    'date_from'     => $dateFrom,
    'date_to'       => $dateTo,
    'method'        => $method,
    'group_id'      => $groupId,
    'include_empty' => $includeEmpty ? '1' : '',
], ['export' => 'csv']);

if (get_string('export') === 'csv') {
    $csvRows = [];
    foreach ($report as $item) {
        $meta = $groupsById[(int) $item['group_id']] ?? null;
        $groupLabel = (string) $item['group_name'];
        $groupCode = $meta !== null ? (string) $meta['code'] : '';

        if ($item['students'] === []) {
            $csvRows[] = [$groupLabel, $groupCode, '', '', '', '', '', '', '0'];
            continue;
        }

        foreach ($item['students'] as $student) {
            $packages = $student['package_numbers'] === []
                ? '-'
                : implode(', ', array_map(
                    static fn (int $number): string => 'Paket ' . $number,
                    $student['package_numbers']
                ));
            $csvRows[] = [
                $groupLabel,
                $groupCode,
                (string) $student['student_code'],
                (string) $student['student_name'],
                (string) $student['school_class'],
                $packages,
                (string) (int) $student['payment_count'],
                (string) $student['last_payment_date'],
                rupiah((float) $student['total_amount'], false),
            ];
        }
        $csvRows[] = [
            'TOTAL ' . $groupLabel,
            $groupCode,
            '',
            (string) (int) $item['student_count'] . ' siswa membayar',
            '',
            '',
            (string) (int) $item['payment_count'],
            '',
            rupiah((float) $item['total_amount'], false),
        ];
    }
    $csvRows[] = [];
    $csvRows[] = [
        'TOTAL SELURUH GROUP',
        '',
        '',
        (string) $totalStudents . ' siswa membayar',
        '',
        '',
        (string) $totalPayments,
        '',
        rupiah($totalReceived, false),
    ];

    report_send_csv(
        'rekap-pembayaran-per-group-' . $dateFrom . '_' . $dateTo . '.csv',
        ['Kelompok', 'Kode Kelompok', 'Kode Siswa', 'Nama Siswa', 'Kelas',
            'Paket Lunas', 'Transaksi', 'Terakhir Bayar', 'Total Diterima'],
        $csvRows
    );
}

$pageTitle = 'Rekap Pembayaran per Group';
$pageSubtitle = 'Uang yang benar-benar diterima dari seluruh siswa dalam setiap group.';
$breadcrumbs = [
    ['label' => 'Laporan', 'url' => url('admin/laporan/keuangan.php')],
    ['label' => 'Pembayaran per Group'],
];
$activeMenu = 'laporan-pembayaran-group';

require_once ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-green">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total Diterima</div>
                    <div class="stat-value stat-money"><?= e(rupiah($totalReceived)) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-calendar-range"></i>
                <?= e(tanggal_short_id($dateFrom)) ?> – <?= e(tanggal_short_id($dateTo)) ?>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-teal">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Group Menerima</div>
                    <div class="stat-value"><?= e(number_format($groupsPaid, 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-diagram-3"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-list-check"></i>
                <?= e(number_format(count($report), 0, ',', '.')) ?> group ditampilkan
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-gold">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Siswa Membayar</div>
                    <div class="stat-value"><?= e(number_format($totalStudents, 0, ',', '.')) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-receipt"></i>
                <?= e(number_format($totalPayments, 0, ',', '.')) ?> transaksi &middot;
                <?= e(number_format($totalInvoices, 0, ',', '.')) ?> tagihan lunas
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card stat-navy">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Rata-rata per Siswa</div>
                    <div class="stat-value stat-money"><?= e(rupiah($averagePerStudent)) ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-calculator"></i></div>
            </div>
            <div class="stat-meta"><i class="bi bi-info-circle"></i>Dari siswa yang sudah membayar</div>
        </div>
    </div>
</div>

<div class="card mb-3 no-print">
    <div class="card-body">
        <form method="get" action="<?= e(url('admin/laporan/pembayaran-group.php')) ?>" class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-4">
                <label for="date_from" class="form-label">Dari tanggal</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="date_to" class="form-label">Sampai tanggal</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-xl-3 col-md-4">
                <label for="group_id" class="form-label">Kelompok</label>
                <select class="form-select" id="group_id" name="group_id">
                    <option value="">Semua kelompok</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>" <?= $groupId === (string) $group['id'] ? 'selected' : '' ?>>
                            <?= e($group['name']) ?> (<?= e($group['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label for="method" class="form-label">Metode</label>
                <select class="form-select" id="method" name="method">
                    <option value="">Semua metode</option>
                    <?php foreach (Payment::methodOptions() as $option): ?>
                        <option value="<?= e($option) ?>" <?= $method === $option ? 'selected' : '' ?>>
                            <?= e(payment_method_label($option)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-3 col-md-8">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="include_empty"
                           name="include_empty" <?= $includeEmpty ? 'checked' : '' ?>>
                    <label class="form-check-label" for="include_empty">
                        Tampilkan group yang belum ada pembayaran
                    </label>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-funnel me-1"></i>Terapkan
                    </button>
                    <?php if ($hasFilter): ?>
                        <a href="<?= e(url('admin/laporan/pembayaran-group.php')) ?>" class="btn btn-light"
                           title="Reset filter">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="text-secondary small">
        <?= e(number_format(count($report), 0, ',', '.')) ?> group &middot;
        <?= e(number_format($totalStudents, 0, ',', '.')) ?> siswa membayar &middot;
        total <span class="fw-semibold text-dark"><?= e(rupiah($totalReceived)) ?></span>
    </div>
    <?= report_toolbar(url_query('admin/laporan/pembayaran-group.php', $csvParams)) ?>
</div>

<?php if ($report === []): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-cash-stack"></i></div>
            <div class="empty-state-title">Belum ada pembayaran pada rentang ini</div>
            <p class="empty-state-text">
                Ubah rentang tanggal, atau centang “Tampilkan group yang belum ada pembayaran”
                untuk melihat semua group.
            </p>
            <a href="<?= e(url('admin/laporan/pembayaran-group.php')) ?>" class="btn btn-light">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filter
            </a>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($report as $item): ?>
        <?php
        $meta = $groupsById[(int) $item['group_id']] ?? null;
        $groupName = $meta !== null ? (string) $meta['name'] : (string) $item['group_name'];
        $groupCode = $meta !== null ? (string) $meta['code'] : '-';
        $groupType = $meta !== null ? (string) $meta['group_type'] : '';
        $memberCount = $meta !== null ? (int) $meta['member_count'] : 0;
        $subjectNames = $meta !== null ? (string) ($meta['subject_names'] ?? '') : '';
        $students = $item['students'];
        $paidStudents = count($students);
        $detailUrl = url_query('admin/laporan/pembayaran.php', [
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'group_id'  => (int) $item['group_id'],
            'method'    => $method !== '' ? $method : null,
        ]);
        ?>
        <div class="card mb-3">
            <div class="card-header flex-wrap gap-2">
                <div>
                    <h2 class="card-title">
                        <?= e($groupName) ?>
                        <span class="badge bg-teal-soft text-teal ms-1"><?= e($groupCode) ?></span>
                        <?php if ($groupType !== ''): ?>
                            <span class="badge text-bg-light ms-1"><?= e(package_type_label($groupType)) ?></span>
                        <?php endif; ?>
                    </h2>
                    <div class="card-subtitle">
                        <?= e((string) $memberCount) ?> siswa aktif
                        <?php if ($subjectNames !== ''): ?>
                            &middot; <?= e($subjectNames) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge text-bg-success">
                        <i class="bi bi-people me-1"></i><?= e((string) $paidStudents) ?> siswa membayar
                    </span>
                    <span class="badge text-bg-light">
                        <?= e((string) (int) $item['payment_count']) ?> transaksi
                    </span>
                    <span class="badge text-bg-light">
                        <?= e((string) (int) $item['invoice_count']) ?> tagihan lunas
                    </span>
                    <span class="badge bg-teal-soft text-teal">
                        <?= e(rupiah((float) $item['total_amount'])) ?>
                    </span>
                    <a href="<?= e($detailUrl) ?>" class="btn btn-light btn-sm no-print"
                       title="Lihat rincian pembayaran group ini">
                        <i class="bi bi-list-check me-1"></i>Rincian
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($students === []): ?>
                    <div class="p-4 text-center text-muted small">
                        <i class="bi bi-inbox me-1"></i>
                        Belum ada pembayaran dari siswa di group ini pada rentang tanggal tersebut.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width:48px;">No.</th>
                                <th>Siswa</th>
                                <th>Kelas</th>
                                <th>Paket Lunas</th>
                                <th class="text-center">Transaksi</th>
                                <th>Terakhir Bayar</th>
                                <th class="text-end">Total Diterima</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($students as $index => $student): ?>
                                <tr>
                                    <td class="text-secondary"><?= e((string) ($index + 1)) ?></td>
                                    <td>
                                        <span class="cell-title"><?= e($student['student_name']) ?></span>
                                        <span class="cell-sub font-monospace"><?= e($student['student_code']) ?></span>
                                    </td>
                                    <td><?= e($student['school_class'] !== '' ? $student['school_class'] : '-') ?></td>
                                    <td class="text-nowrap">
                                        <?php if ($student['package_numbers'] === []): ?>
                                            <span class="text-muted">—</span>
                                        <?php else: ?>
                                            <?php foreach ($student['package_numbers'] as $packageNumber): ?>
                                                <span class="badge text-bg-light">Paket <?= e((string) $packageNumber) ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= e((string) (int) $student['payment_count']) ?></td>
                                    <td class="text-nowrap">
                                        <?= e($student['last_payment_date'] !== ''
                                            ? tanggal_short_id($student['last_payment_date'])
                                            : '-') ?>
                                    </td>
                                    <td class="text-end amount"><?= e(rupiah((float) $student['total_amount'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="4" class="text-end">Total <?= e($groupName) ?></td>
                                <td class="text-center"><?= e((string) (int) $item['payment_count']) ?></td>
                                <td>
                                    <?= e($item['last_payment_date'] !== null
                                        ? 's.d. ' . tanggal_short_id((string) $item['last_payment_date'])
                                        : '') ?>
                                </td>
                                <td class="text-end amount"><?= e(rupiah((float) $item['total_amount'])) ?></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white small text-muted d-flex flex-wrap justify-content-between gap-2">
                <span>
                    <?php if ($paidStudents > 0 && $memberCount > 0): ?>
                        <?= e((string) $paidStudents) ?> dari <?= e((string) $memberCount) ?> siswa aktif
                        sudah membayar pada periode ini.
                    <?php elseif ($paidStudents > 0): ?>
                        <?= e((string) $paidStudents) ?> siswa membayar pada periode ini.
                    <?php else: ?>
                        Belum ada siswa yang membayar pada periode ini.
                    <?php endif; ?>
                </span>
                <span>
                    Rata-rata per siswa:
                    <strong><?= e(rupiah($paidStudents > 0
                        ? (float) $item['total_amount'] / $paidStudents
                        : 0)) ?></strong>
                </span>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="master-note">
    <i class="bi bi-info-circle"></i>
    <span>
        Nominal dihitung dari <strong>alokasi pembayaran ke tiap tagihan</strong>, sehingga satu nota
        yang melunasi beberapa tagihan sekaligus terbagi tepat ke group asal tagihannya dan tidak
        terhitung ganda. Hanya pembayaran berstatus <strong>berhasil</strong> yang dihitung.
    </span>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
