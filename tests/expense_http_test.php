<?php
declare(strict_types=1);

/**
 * Uji HTTP modul Pengeluaran.
 *
 * Cakupan:
 * - halaman daftar, form tambah/edit, dan detail;
 * - pencatatan oleh petugas, pembaruan, validasi, dan pesan error;
 * - pembatalan hanya oleh admin (petugas 403);
 * - proteksi POST + CSRF dan metode;
 * - integrasi pengeluaran ke Rekap Keuangan dan ekspor CSV.
 *
 * Prasyarat: server pengembangan berjalan di http://127.0.0.1:8080
 *   php -S 127.0.0.1:8080
 * Jalankan:
 *   php tests/expense_http_test.php
 */

const BASE = 'http://127.0.0.1:8080';
const COOKIE_ADMIN = __DIR__ . '/.cookie-exp-admin.txt';
const COOKIE_PETUGAS = __DIR__ . '/.cookie-exp-petugas.txt';

$pass = 0;
$fail = 0;
$failures = [];

function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $failures;
    if ($ok) {
        $pass++;
        echo "  PASS  {$name}\n";
        return;
    }
    $fail++;
    $failures[] = $name . ($detail !== '' ? ' — ' . $detail : '');
    echo "  FAIL  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

/**
 * @return array{status:int,body:string,location:string,type:string}
 */
function http(string $method, string $path, array $fields = [], ?string $cookieJar = null): array
{
    $ch = curl_init(BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if ($cookieJar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    curl_close($ch);

    $location = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m) === 1) {
        $location = trim($m[1]);
    }
    return ['status' => $status, 'body' => $body, 'location' => $location, 'type' => $contentType];
}

function csrf_from(string $html): string
{
    if (preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) === 1) {
        return $m[1];
    }
    return '';
}

function login(string $username, string $password, string $jar): bool
{
    @unlink($jar);
    $page = http('GET', '/login.php', [], $jar);
    $token = csrf_from($page['body']);
    if ($token === '') {
        return false;
    }
    $result = http('POST', '/login.php', [
        '_csrf'    => $token,
        'username' => $username,
        'password' => $password,
    ], $jar);
    return $result['status'] === 302 && strpos($result['location'], 'admin/dashboard.php') !== false;
}

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$admin = $pdo->query("SELECT * FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1")->fetch();
$officer = $pdo->query("SELECT * FROM users WHERE role = 'petugas' AND is_active = 1 ORDER BY id LIMIT 1")->fetch();
if (!is_array($admin) || !is_array($officer)) {
    fwrite(STDERR, "FATAL: pengguna admin/petugas tidak tersedia.\n");
    exit(1);
}

$stamp = date('His');
$prefix = 'EXPHTTP-' . $stamp;
$today = date('Y-m-d');

$cleanup = static function () use ($pdo, $prefix): void {
    $ids = $pdo->query(
        'SELECT id FROM expenses WHERE payee LIKE ' . $pdo->quote($prefix . '%')
    )->fetchAll(PDO::FETCH_COLUMN);
    if ($ids !== []) {
        $pdo->exec("DELETE FROM activity_logs WHERE related_type = 'expense' AND related_id IN ("
            . implode(',', array_map('intval', $ids)) . ')');
        $pdo->exec('DELETE FROM expenses WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')');
    }
};

$cleanup();

try {
    check('Admin berhasil login', login('admin', 'Admin@2026', COOKIE_ADMIN));
    check('Petugas berhasil login', login('petugas', 'Petugas@2026', COOKIE_PETUGAS));

    // -----------------------------------------------------------------
    echo "\n== A. Halaman daftar & form ==\n";
    // -----------------------------------------------------------------
    $page = http('GET', '/admin/pengeluaran/index.php', [], COOKIE_ADMIN);
    check('Halaman daftar dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Menu Pengeluaran tampil di sidebar', str_contains($page['body'], 'admin/pengeluaran/index.php'));
    check('Judul daftar pengeluaran tampil', str_contains($page['body'], 'Daftar Pengeluaran'));
    check('Tombol catat pengeluaran tampil', str_contains($page['body'], 'Catat Pengeluaran'));

    $form = http('GET', '/admin/pengeluaran/tambah.php', [], COOKIE_PETUGAS);
    check('Form tambah dapat dibuka petugas', $form['status'] === 200, 'status ' . $form['status']);
    check('Form memuat tanggal', str_contains($form['body'], 'name="expense_date"'));
    check('Form memuat kategori', str_contains($form['body'], 'name="category"'));
    check('Form memuat nominal', str_contains($form['body'], 'name="amount"'));
    check('Form memuat metode', str_contains($form['body'], 'name="method"'));
    check('Form memuat penerima', str_contains($form['body'], 'name="payee"'));
    $tokenPetugas = csrf_from($form['body']);
    check('Form memuat token CSRF', $tokenPetugas !== '');

    // -----------------------------------------------------------------
    echo "\n== B. Petugas mencatat pengeluaran ==\n";
    // -----------------------------------------------------------------
    $create = http('POST', '/admin/pengeluaran/proses.php', [
        '_csrf'            => $tokenPetugas,
        'action'           => 'create',
        'expense_date'     => $today,
        'category'         => 'honor_guru',
        'amount'           => '275.000',
        'method'           => 'transfer',
        'payee'            => $prefix . '-GURU',
        'reference_number' => 'TRX-' . $stamp,
        'notes'            => 'Uji HTTP pencatatan pengeluaran',
    ], COOKIE_PETUGAS);
    check('Pencatatan diterima dan dialihkan ke detail', $create['status'] === 302
        && strpos($create['location'], 'admin/pengeluaran/detail.php?id=') !== false,
        'status ' . $create['status'] . ' loc ' . $create['location']);

    $expenseId = preg_match('/id=(\d+)/', $create['location'], $m) === 1 ? (int) $m[1] : 0;
    check('ID pengeluaran terbentuk', $expenseId > 0);

    $row = $expenseId > 0 ? Expense::find($expenseId) : null;
    check('Data pengeluaran tersimpan', is_array($row));
    check('Nomor berformat OUT-YYYYMM-####', is_array($row)
        && preg_match('/^OUT-\d{6}-\d{4}$/', (string) $row['expense_number']) === 1,
        is_array($row) ? (string) $row['expense_number'] : '');
    check('Nominal tersimpan benar', is_array($row) && abs((float) $row['amount'] - 275000.0) < 0.005);
    check('Petugas pencatat tersimpan', is_array($row) && (int) $row['recorded_by'] === (int) $officer['id']);
    check('Snapshot nama petugas tersimpan', is_array($row) && (string) $row['recorded_by_name'] === (string) $officer['name']);
    $number = is_array($row) ? (string) $row['expense_number'] : '';

    $detail = http('GET', '/admin/pengeluaran/detail.php?id=' . $expenseId, [], COOKIE_ADMIN);
    check('Halaman detail dapat dibuka', $detail['status'] === 200, 'status ' . $detail['status']);
    check('Detail menampilkan nomor', str_contains($detail['body'], $number));
    check('Detail menampilkan penerima', str_contains($detail['body'], $prefix . '-GURU'));
    check('Detail menampilkan nominal rupiah', str_contains($detail['body'], 'Rp275.000'));
    check('Detail menampilkan status tercatat', str_contains($detail['body'], 'Tercatat'));
    check('Admin melihat tombol edit', str_contains($detail['body'], 'Edit Pengeluaran'));
    check('Admin melihat form pembatalan', str_contains($detail['body'], 'Batalkan Pengeluaran'));
    $tokenAdmin = csrf_from($detail['body']);

    $detailPetugas = http('GET', '/admin/pengeluaran/detail.php?id=' . $expenseId, [], COOKIE_PETUGAS);
    check('Petugas dapat melihat detail', $detailPetugas['status'] === 200);
    check('Petugas tidak melihat form pembatalan', !str_contains($detailPetugas['body'], 'Batalkan Pengeluaran'));
    $tokenPetugasDetail = csrf_from($detailPetugas['body']);

    // -----------------------------------------------------------------
    echo "\n== C. Pembaruan & validasi ==\n";
    // -----------------------------------------------------------------
    $edit = http('GET', '/admin/pengeluaran/edit.php?id=' . $expenseId, [], COOKIE_PETUGAS);
    check('Form edit dapat dibuka', $edit['status'] === 200, 'status ' . $edit['status']);
    check('Form edit memuat nilai lama', str_contains($edit['body'], $prefix . '-GURU'));
    check('Form edit memuat nominal terformat', str_contains($edit['body'], '275.000'));

    $update = http('POST', '/admin/pengeluaran/proses.php', [
        '_csrf'            => $tokenPetugas,
        'action'           => 'update',
        'id'               => $expenseId,
        'expense_date'     => $today,
        'category'         => 'sewa',
        'amount'           => '300.000',
        'method'           => 'qris',
        'payee'            => $prefix . '-SEWA',
        'reference_number' => 'TRX-' . $stamp . '-REV',
        'notes'            => 'Revisi kategori dan nominal',
    ], COOKIE_PETUGAS);
    check('Pembaruan diterima', $update['status'] === 302
        && strpos($update['location'], 'admin/pengeluaran/detail.php?id=') !== false,
        'status ' . $update['status'] . ' loc ' . $update['location']);

    $row = Expense::find($expenseId);
    check('Kategori berubah', is_array($row) && (string) $row['category'] === 'sewa');
    check('Nominal berubah', is_array($row) && abs((float) $row['amount'] - 300000.0) < 0.005);
    check('Penerima berubah', is_array($row) && (string) $row['payee'] === $prefix . '-SEWA');

    $invalid = http('POST', '/admin/pengeluaran/proses.php', [
        '_csrf'        => $tokenPetugas,
        'action'       => 'create',
        'expense_date' => '',
        'category'     => 'tidak_ada',
        'amount'       => 'abc',
        'method'       => 'kartu',
        'payee'        => '',
    ], COOKIE_PETUGAS);
    check('Input tidak valid dialihkan kembali ke form', $invalid['status'] === 302
        && strpos($invalid['location'], 'admin/pengeluaran/tambah.php') !== false,
        'status ' . $invalid['status'] . ' loc ' . $invalid['location']);

    $formAgain = http('GET', '/admin/pengeluaran/tambah.php', [], COOKIE_PETUGAS);
    check('Pesan tanggal wajib tampil', str_contains($formAgain['body'], 'Tanggal pengeluaran wajib diisi'));
    check('Pesan penerima wajib tampil', str_contains($formAgain['body'], 'Penerima / vendor wajib diisi'));

    // -----------------------------------------------------------------
    echo "\n== D. Hak akses & proteksi ==\n";
    // -----------------------------------------------------------------
    $petugasCancel = http('POST', '/admin/pengeluaran/proses.php', [
        '_csrf'  => $tokenPetugasDetail,
        'action' => 'cancel',
        'id'     => $expenseId,
        'reason' => 'Petugas mencoba membatalkan',
    ], COOKIE_PETUGAS);
    check('Petugas ditolak saat membatalkan (403)', $petugasCancel['status'] === 403,
        'status ' . $petugasCancel['status']);

    $getProses = http('GET', '/admin/pengeluaran/proses.php', [], COOKIE_ADMIN);
    check('GET pada endpoint pengeluaran ditolak (405)', $getProses['status'] === 405,
        'status ' . $getProses['status']);

    $noCsrf = http('POST', '/admin/pengeluaran/proses.php', [
        'action'       => 'create',
        'expense_date' => $today,
        'category'     => 'lainnya',
        'amount'       => '1000',
        'method'       => 'tunai',
        'payee'        => $prefix . '-NOCSRF',
    ], COOKIE_ADMIN);
    check('POST tanpa CSRF ditolak (419)', $noCsrf['status'] === 419, 'status ' . $noCsrf['status']);

    $anon = http('GET', '/admin/pengeluaran/index.php');
    check('Halaman pengeluaran tanpa login dialihkan', $anon['status'] === 302
        && strpos($anon['location'], 'login.php') !== false,
        'status ' . $anon['status'] . ' loc ' . $anon['location']);

    // -----------------------------------------------------------------
    echo "\n== E. Admin membatalkan pengeluaran ==\n";
    // -----------------------------------------------------------------
    $cancel = http('POST', '/admin/pengeluaran/proses.php', [
        '_csrf'  => $tokenAdmin,
        'action' => 'cancel',
        'id'     => $expenseId,
        'reason' => 'Salah input nominal',
    ], COOKIE_ADMIN);
    check('Admin berhasil membatalkan', $cancel['status'] === 302
        && strpos($cancel['location'], 'admin/pengeluaran/detail.php?id=') !== false,
        'status ' . $cancel['status'] . ' loc ' . $cancel['location']);

    $row = Expense::find($expenseId);
    check('Status menjadi dibatalkan', is_array($row) && (string) $row['status'] === 'dibatalkan');
    check('Alasan pembatalan tersimpan', is_array($row) && (string) $row['cancel_reason'] === 'Salah input nominal');
    check('Admin pembatal tercatat', is_array($row) && (int) $row['cancelled_by'] === (int) $admin['id']);

    $detailCancelled = http('GET', '/admin/pengeluaran/detail.php?id=' . $expenseId, [], COOKIE_ADMIN);
    check('Detail menampilkan status dibatalkan', str_contains($detailCancelled['body'], 'Pengeluaran dibatalkan'));
    check('Detail menampilkan alasan pembatalan', str_contains($detailCancelled['body'], 'Salah input nominal'));
    check('Tombol edit hilang setelah dibatalkan', !str_contains($detailCancelled['body'], 'Edit Pengeluaran'));

    $editCancelled = http('GET', '/admin/pengeluaran/edit.php?id=' . $expenseId, [], COOKIE_ADMIN);
    check('Edit data dibatalkan dialihkan kembali', $editCancelled['status'] === 302
        && strpos($editCancelled['location'], 'admin/pengeluaran/detail.php?id=') !== false,
        'status ' . $editCancelled['status'] . ' loc ' . $editCancelled['location']);

    // -----------------------------------------------------------------
    echo "\n== F. Filter & integrasi laporan ==\n";
    // -----------------------------------------------------------------
    $filtered = http('GET', '/admin/pengeluaran/index.php?status=dibatalkan&q=' . urlencode($prefix), [], COOKIE_ADMIN);
    check('Filter status dibatalkan menampilkan data', $filtered['status'] === 200
        && str_contains($filtered['body'], $number),
        'status ' . $filtered['status']);

    $report = http('GET', '/admin/laporan/keuangan.php', [], COOKIE_ADMIN);
    check('Rekap keuangan dapat dibuka', $report['status'] === 200, 'status ' . $report['status']);
    check('Rekap keuangan menampilkan pengeluaran operasional', str_contains($report['body'], 'Pengeluaran Operasional'));
    check('Rekap keuangan menampilkan saldo kas', str_contains($report['body'], 'Saldo Kas'));
    check('Rekap keuangan menampilkan kolom pengeluaran bulanan', str_contains($report['body'], 'Pengeluaran</th>'));

    $csv = http('GET', '/admin/laporan/keuangan.php?export=csv', [], COOKIE_ADMIN);
    check('Ekspor CSV rekap keuangan berhasil', $csv['status'] === 200, 'status ' . $csv['status']);
    check('CSV memuat baris pengeluaran operasional', str_contains($csv['body'], 'Pengeluaran operasional'));
    check('CSV memuat baris saldo kas', str_contains($csv['body'], 'Saldo kas'));
    check('CSV memakai pemisah titik koma', str_contains($csv['body'], ';'));
} catch (Throwable $exception) {
    echo "\nFATAL: " . get_class($exception) . ': ' . $exception->getMessage() . "\n";
    echo $exception->getTraceAsString() . "\n";
    $fail++;
} finally {
    $cleanup();
    @unlink(COOKIE_ADMIN);
    @unlink(COOKIE_PETUGAS);
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
