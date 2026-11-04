<?php
declare(strict_types=1);

/**
 * Uji mandiri modul Pengeluaran.
 *
 * Cakupan:
 * - nomor otomatis OUT-YYYYMM-#### berurutan;
 * - validasi tanggal, kategori, nominal, metode, dan penerima;
 * - snapshot petugas yang mencatat;
 * - pembaruan data dan penolakan terhadap data yang sudah dibatalkan;
 * - pembatalan ber-audit beserta alasan;
 * - ringkasan, rekap kategori, filter, dan rekap bulanan hanya menghitung status tercatat.
 *
 * Jalankan dari root proyek:
 *   php tests/expense_test.php
 *
 * Skrip membuat data uji berawalan EXPTEST- lalu menghapusnya kembali.
 * Kode keluar 0 = semua lulus, 1 = ada kegagalan.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$pass = 0;
$fail = 0;
$failures = [];

function check(string $name, bool $condition, string $detail = ''): void
{
    global $pass, $fail, $failures;
    if ($condition) {
        $pass++;
        echo "  PASS  {$name}\n";
        return;
    }
    $fail++;
    $failures[] = $name . ($detail !== '' ? ' — ' . $detail : '');
    echo "  FAIL  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

function checkSame(string $name, mixed $expected, mixed $actual): void
{
    $same = is_float($expected) || is_float($actual)
        ? abs((float) $expected - (float) $actual) < 0.005
        : $expected === $actual;
    check($name, $same, 'diharapkan ' . var_export($expected, true) . ', didapat ' . var_export($actual, true));
}

$pdo = db();
$admin = $pdo->query("SELECT * FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1")->fetch();
if (!is_array($admin)) {
    fwrite(STDERR, "FATAL: tidak ada pengguna admin aktif.\n");
    exit(1);
}

$_SESSION['auth_user_id'] = (int) $admin['id'];
$_SESSION['_last_activity'] = time();
$_SESSION['_last_regenerate'] = time();

$prefix = 'EXPTEST-' . date('His');
$today = date('Y-m-d');

$cleanup = static function () use ($pdo, $prefix): void {
    $ids = $pdo->query(
        "SELECT id FROM expenses WHERE payee LIKE " . $pdo->quote($prefix . '%')
    )->fetchAll(PDO::FETCH_COLUMN);
    if ($ids !== []) {
        $pdo->exec('DELETE FROM activity_logs WHERE related_type = \'expense\' AND related_id IN ('
            . implode(',', array_map('intval', $ids)) . ')');
        $pdo->exec('DELETE FROM expenses WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')');
    }
};

$cleanup();

try {
    // =================================================================
    echo "\n== A. Validasi input ==\n";
    // =================================================================
    $invalid = Expense::create([
        'expense_date' => '',
        'category'     => 'tidak_ada',
        'amount'       => 'abc',
        'method'       => 'kartu',
        'payee'        => '',
    ]);
    check('Input tidak valid ditolak', $invalid['ok'] === false);
    check('Tanggal wajib dilaporkan', isset($invalid['errors']['expense_date']));
    check('Kategori tidak valid dilaporkan', isset($invalid['errors']['category']));
    check('Nominal tidak valid dilaporkan', isset($invalid['errors']['amount']));
    check('Metode tidak valid dilaporkan', isset($invalid['errors']['method']));
    check('Penerima wajib dilaporkan', isset($invalid['errors']['payee']));

    $zero = Expense::create([
        'expense_date' => $today,
        'category'     => 'lainnya',
        'amount'       => '0',
        'method'       => 'tunai',
        'payee'        => $prefix . '-ZERO',
    ]);
    check('Nominal nol ditolak', $zero['ok'] === false && isset($zero['errors']['amount']));

    checkSame('Tidak ada baris tersimpan dari input tidak valid', 0, (int) $pdo->query(
        "SELECT COUNT(*) FROM expenses WHERE payee LIKE " . $pdo->quote($prefix . '%')
    )->fetchColumn());

    // =================================================================
    echo "\n== B. Pencatatan & nomor otomatis ==\n";
    // =================================================================
    $first = Expense::create([
        'expense_date'     => $today,
        'category'         => 'honor_guru',
        'amount'           => '250.000',
        'method'           => 'transfer',
        'payee'            => $prefix . '-GURU',
        'reference_number' => 'REF-001',
        'notes'            => 'Honor guru matematika',
    ]);
    check('Pengeluaran pertama tersimpan', ($first['ok'] ?? false) === true, (string) ($first['error'] ?? ''));
    check('Nomor memakai format OUT-YYYYMM-####', preg_match('/^OUT-\d{6}-\d{4}$/', (string) $first['number']) === 1, (string) $first['number']);
    checkSame('Nomor memakai bulan tanggal pengeluaran', date('Ym'), substr((string) $first['number'], 4, 6));

    $row = Expense::find((int) $first['id']);
    check('Data dapat dibaca kembali', is_array($row));
    checkSame('Nominal tersimpan sebagai uang', 250000.0, (float) ($row['amount'] ?? 0));
    checkSame('Kategori tersimpan', 'honor_guru', (string) ($row['category'] ?? ''));
    checkSame('Metode tersimpan', 'transfer', (string) ($row['method'] ?? ''));
    checkSame('Snapshot petugas tersimpan', (string) $admin['name'], (string) ($row['recorded_by_name'] ?? ''));
    checkSame('Status awal tercatat', 'tercatat', (string) ($row['status'] ?? ''));
    checkSame('Referensi tersimpan', 'REF-001', (string) ($row['reference_number'] ?? ''));

    $second = Expense::create([
        'expense_date' => $today,
        'category'     => 'atk',
        'amount'       => 75000,
        'method'       => 'tunai',
        'payee'        => $prefix . '-ATK',
    ]);
    check('Pengeluaran kedua tersimpan', ($second['ok'] ?? false) === true, (string) ($second['error'] ?? ''));
    $firstSeq = (int) substr((string) $first['number'], -4);
    $secondSeq = (int) substr((string) $second['number'], -4);
    checkSame('Nomor berurutan', $firstSeq + 1, $secondSeq);

    // =================================================================
    echo "\n== C. Pembaruan data ==\n";
    // =================================================================
    $update = Expense::update((int) $first['id'], [
        'expense_date' => $today,
        'category'     => 'transport',
        'amount'       => '300.000,50',
        'method'       => 'qris',
        'payee'        => $prefix . '-GURU-REV',
        'reference_number' => 'REF-002',
        'notes'        => 'Revisi biaya transport',
    ]);
    check('Pembaruan diterima', ($update['ok'] ?? false) === true, (string) ($update['error'] ?? ''));

    $row = Expense::find((int) $first['id']);
    checkSame('Kategori berubah', 'transport', (string) $row['category']);
    checkSame('Nominal berubah termasuk desimal', 300000.5, (float) $row['amount']);
    checkSame('Metode berubah', 'qris', (string) $row['method']);
    checkSame('Penerima berubah', $prefix . '-GURU-REV', (string) $row['payee']);
    checkSame('Nomor tidak berubah saat edit', (string) $first['number'], (string) $row['expense_number']);

    // =================================================================
    echo "\n== D. Pembatalan ber-audit ==\n";
    // =================================================================
    $shortReason = false;
    try {
        Expense::cancel((int) $first['id'], 'x');
    } catch (DomainException $exception) {
        $shortReason = true;
    }
    check('Alasan pembatalan terlalu pendek ditolak', $shortReason);

    Expense::cancel((int) $first['id'], 'Salah input nominal');
    $row = Expense::find((int) $first['id']);
    checkSame('Status menjadi dibatalkan', 'dibatalkan', (string) $row['status']);
    checkSame('Alasan pembatalan tersimpan', 'Salah input nominal', (string) $row['cancel_reason']);
    check('Waktu pembatalan tercatat', $row['cancelled_at'] !== null);
    checkSame('Petugas pembatal tercatat', (int) $admin['id'], (int) $row['cancelled_by']);

    Expense::cancel((int) $first['id'], 'Pembatalan ulang');
    $row = Expense::find((int) $first['id']);
    checkSame('Pembatalan ulang idempoten', 'Salah input nominal', (string) $row['cancel_reason']);

    $updateCancelled = Expense::update((int) $first['id'], [
        'expense_date' => $today,
        'category'     => 'lainnya',
        'amount'       => '1000',
        'method'       => 'tunai',
        'payee'        => $prefix . '-REV2',
    ]);
    check('Data yang dibatalkan tidak dapat diubah', ($updateCancelled['ok'] ?? true) === false);

    $cancelMissing = false;
    try {
        Expense::cancel(999999999, 'Tidak ada');
    } catch (DomainException $exception) {
        $cancelMissing = true;
    }
    check('Pembatalan data tidak ditemukan ditolak', $cancelMissing);

    $logCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM activity_logs
          WHERE related_type = 'expense' AND related_id = " . (int) $first['id'] . "
            AND activity IN ('pengeluaran_tambah','pengeluaran_edit','pengeluaran_batal')"
    )->fetchColumn();
    checkSame('Jejak aktivitas tambah/edit/batal tercatat', 3, $logCount);

    // =================================================================
    echo "\n== E. Ringkasan & filter ==\n";
    // =================================================================
    $filters = ['q' => $prefix];
    $summary = Expense::summary($filters);
    checkSame('Ringkasan hanya menghitung status tercatat', 1, $summary['count']);
    checkSame('Nominal ringkasan hanya yang tercatat', 75000.0, $summary['amount']);

    $allRecorded = Expense::all($filters);
    checkSame('Daftar default hanya menampilkan tercatat', 1, count($allRecorded));

    $allBoth = Expense::all($filters + ['include_cancelled' => true]);
    checkSame('Daftar dapat menampilkan data dibatalkan', 2, count($allBoth));

    $cancelledOnly = Expense::all($filters + ['status' => 'dibatalkan']);
    checkSame('Filter status dibatalkan berfungsi', 1, count($cancelledOnly));

    $categoryFilter = Expense::all(['q' => $prefix, 'include_cancelled' => true, 'category' => 'atk']);
    checkSame('Filter kategori berfungsi', 1, count($categoryFilter));

    $methodFilter = Expense::all(['q' => $prefix, 'include_cancelled' => true, 'method' => 'tunai']);
    checkSame('Filter metode berfungsi', 1, count($methodFilter));

    $outOfRange = Expense::all([
        'q'        => $prefix,
        'date_from' => date('Y-m-d', strtotime('-10 days')),
        'date_to'   => date('Y-m-d', strtotime('-5 days')),
    ]);
    checkSame('Filter tanggal di luar rentang kosong', 0, count($outOfRange));

    $byCategory = Expense::byCategory($filters);
    checkSame('Rekap kategori berisi satu kategori tercatat', 1, count($byCategory));
    checkSame('Kategori rekap sesuai', 'atk', (string) ($byCategory[0]['category'] ?? ''));
    checkSame('Nominal rekap kategori sesuai', 75000.0, (float) ($byCategory[0]['amount'] ?? 0));

    $monthly = Expense::monthlyTotals(6);
    checkSame('Rekap bulanan punya 6 label', 6, count($monthly['labels']));
    checkSame('Rekap bulanan punya 6 nilai', 6, count($monthly['amounts']));
    $lastAmount = (float) $monthly['amounts'][5];
    check('Rekap bulanan memuat pengeluaran tercatat bulan ini', $lastAmount >= 75000.0);

    // =================================================================
    echo "\n== F. Format bantu tampilan ==\n";
    // =================================================================
    checkSame('Label kategori honor guru', 'Honor Guru', expense_category_label('honor_guru'));
    checkSame('Label kategori listrik & internet', 'Listrik & Internet', expense_category_label('listrik_internet'));
    checkSame('Label status tercatat', 'Tercatat', expense_status_label('tercatat'));
    checkSame('Label status dibatalkan', 'Dibatalkan', expense_status_label('dibatalkan'));
    check('Badge kategori menghasilkan HTML aman', str_contains(expense_category_badge('atk'), 'ATK &amp; Perlengkapan'));
    check('Badge status dibatalkan memakai warna bahaya', str_contains(expense_status_badge('dibatalkan'), 'text-bg-danger'));
} catch (Throwable $exception) {
    echo "\nFATAL: " . get_class($exception) . ': ' . $exception->getMessage() . "\n";
    echo $exception->getTraceAsString() . "\n";
    $fail++;
} finally {
    $cleanup();
}

echo "\n============================================\n";
echo 'LULUS: ' . $pass . '   GAGAL: ' . $fail . "\n";
if ($failures !== []) {
    echo "\nRincian kegagalan:\n";
    foreach ($failures as $failure) {
        echo ' - ' . $failure . "\n";
    }
}
exit($fail === 0 ? 0 : 1);
