<?php
declare(strict_types=1);

/**
 * Uji HTTP Tahap 4 — revisi paket berjalan tanpa pelunasan & utang menumpuk.
 *
 * Cakupan:
 * - dashboard menampilkan total utang, siswa berutang, dan uang diterima;
 * - detail siswa menampilkan tagihan, pembayaran, dan utang;
 * - form pembayaran multi-tagihan memakai invoice_ids[] dan nominal terkunci;
 * - nominal salah ditolak server;
 * - pelunasan satu tagihan dan pelunasan beberapa tagihan sekaligus;
 * - nota A5 merinci setiap tagihan yang dilunasi;
 * - laporan utang beserta ekspor CSV;
 * - peran, CSRF, dan proteksi metode.
 *
 * Prasyarat: server pengembangan berjalan di http://127.0.0.1:8080
 *   php -S 127.0.0.1:8080
 * Jalankan:
 *   php tests/stage4_http_test.php
 */

const BASE = 'http://127.0.0.1:8080';
const COOKIE_ADMIN = __DIR__ . '/.cookie-t4-admin.txt';
const COOKIE_PETUGAS = __DIR__ . '/.cookie-t4-petugas.txt';

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

function checkSameInt(string $name, int $expected, int $actual): void
{
    check($name, $expected === $actual, 'diharapkan ' . $expected . ', didapat ' . $actual);
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

// Bersihkan sisa uji HTTP sebelumnya.
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec("DELETE FROM student_package_meetings WHERE student_id IN (SELECT id FROM students WHERE code LIKE 'T4HTTP%')");
$pdo->exec("DELETE FROM payment_allocations WHERE invoice_id IN (SELECT id FROM invoices WHERE student_id IN (SELECT id FROM students WHERE code LIKE 'T4HTTP%'))");
$pdo->exec("DELETE FROM payments WHERE student_id IN (SELECT id FROM students WHERE code LIKE 'T4HTTP%')");
$pdo->exec("DELETE FROM invoices WHERE student_id IN (SELECT id FROM students WHERE code LIKE 'T4HTTP%')");
$pdo->exec("DELETE FROM student_packages WHERE student_id IN (SELECT id FROM students WHERE code LIKE 'T4HTTP%')");
$pdo->exec("DELETE FROM group_members WHERE student_id IN (SELECT id FROM students WHERE code LIKE 'T4HTTP%')");
$pdo->exec("DELETE FROM students WHERE code LIKE 'T4HTTP%'");
$pdo->exec("DELETE FROM class_meetings WHERE group_id IN (SELECT id FROM class_groups WHERE name LIKE 'T4HTTP%')");
$pdo->exec("DELETE FROM group_schedules WHERE group_subject_id IN (SELECT id FROM group_subjects WHERE group_id IN (SELECT id FROM class_groups WHERE name LIKE 'T4HTTP%'))");
$pdo->exec("DELETE FROM group_subjects WHERE group_id IN (SELECT id FROM class_groups WHERE name LIKE 'T4HTTP%')");
$pdo->exec("DELETE FROM group_members WHERE group_id IN (SELECT id FROM class_groups WHERE name LIKE 'T4HTTP%')");
$pdo->exec("DELETE FROM class_groups WHERE name LIKE 'T4HTTP%'");
$pdo->exec("DELETE FROM pricing_rules WHERE notes LIKE 'T4HTTP%'");
$pdo->exec("DELETE FROM teacher_subjects WHERE teacher_id IN (SELECT id FROM teachers WHERE code LIKE 'T4HTTP%')");
$pdo->exec("DELETE FROM teachers WHERE code LIKE 'T4HTTP%'");
$pdo->exec("DELETE FROM subjects WHERE code LIKE 'T4HTTP%'");
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$_SESSION['auth_user_id'] = (int) $admin['id'];
$_SESSION['_last_activity'] = time();
$_SESSION['_last_regenerate'] = time();

$created = [
    'students' => [],
    'groups'   => [],
    'teachers' => [],
    'subjects' => [],
    'pricing'  => [],
];

$stamp = date('His');
$lastMonth = (new DateTimeImmutable('first day of last month'))->format('Y-m-d');
$day = static fn (int $n): string => (new DateTimeImmutable($lastMonth))->modify('+' . ($n - 1) . ' days')->format('Y-m-d');
$today = date('Y-m-d');
$tariff = 850000;

try {
    // -----------------------------------------------------------------
    echo "== Persiapan data HTTP ==\n";
    // -----------------------------------------------------------------
    $ins = $pdo->prepare('INSERT INTO teachers (code, name, gender, is_active) VALUES (:code, :name, :gender, 1)');
    $ins->execute([':code' => 'T4HTTP-G' . $stamp, ':name' => 'T4HTTP Guru', ':gender' => 'L']);
    $teacherId = (int) $pdo->lastInsertId();
    $created['teachers'][] = $teacherId;

    $ins = $pdo->prepare('INSERT INTO subjects (code, name, is_active) VALUES (:code, :name, 1)');
    $ins->execute([':code' => 'T4HTTP-M' . $stamp, ':name' => 'T4HTTP Matematika']);
    $subjectId = (int) $pdo->lastInsertId();
    $created['subjects'][] = $subjectId;

    $tariffStart = (new DateTimeImmutable('first day of last month'))->modify('-1 month')->format('Y-m-d');
    $ins = $pdo->prepare(
        'INSERT INTO pricing_rules
            (group_type, student_count, package_size, amount, effective_start, effective_end, is_active, notes, created_by)
         VALUES (:type, :count, 8, :amount, :start, NULL, 1, :notes, :by)'
    );
    $ins->execute([
        ':type'   => 'private',
        ':count'  => 1,
        ':amount' => $tariff,
        ':start'  => $tariffStart,
        ':notes'  => 'T4HTTP tarif',
        ':by'     => (int) $admin['id'],
    ]);
    $created['pricing'][] = (int) $pdo->lastInsertId();

    $group = Group::create([
        'name'       => 'T4HTTP Private ' . $stamp,
        'group_type' => 'private',
        'capacity'   => 1,
        'is_active'  => 1,
    ]);
    $groupId = (int) $group['id'];
    $created['groups'][] = $groupId;
    $relationId = GroupSubject::create([
        'group_id'   => $groupId,
        'subject_id' => $subjectId,
        'teacher_id' => $teacherId,
        'is_active'  => 1,
    ]);

    $ins = $pdo->prepare(
        'INSERT INTO students (code, name, gender, join_date, is_active)
         VALUES (:code, :name, :gender, :join_date, 1)'
    );
    $ins->execute([
        ':code'      => 'T4HTTP-S' . $stamp,
        ':name'      => 'T4HTTP Siswa Uji',
        ':gender'    => 'P',
        ':join_date' => $lastMonth,
    ]);
    $studentId = (int) $pdo->lastInsertId();
    $created['students'][] = $studentId;

    $member = Membership::add($groupId, $studentId, $day(1));
    if (($member['ok'] ?? false) !== true) {
        throw new RuntimeException('Gagal menambah anggota uji: ' . (string) ($member['error'] ?? ''));
    }
    $membershipId = (int) $member['membership_id'];

    // Keanggotaan kedua tanpa paket: dipakai menguji form tambah paket.
    $group2 = Group::create([
        'name'       => 'T4HTTP Private Dua ' . $stamp,
        'group_type' => 'private',
        'capacity'   => 1,
        'is_active'  => 1,
    ]);
    $groupId2 = (int) $group2['id'];
    $created['groups'][] = $groupId2;
    GroupSubject::create([
        'group_id'   => $groupId2,
        'subject_id' => $subjectId,
        'teacher_id' => $teacherId,
        'is_active'  => 1,
    ]);
    $ins = $pdo->prepare(
        'INSERT INTO students (code, name, gender, join_date, is_active)
         VALUES (:code, :name, :gender, :join_date, 1)'
    );
    $ins->execute([
        ':code'      => 'T4HTTP-S2' . $stamp,
        ':name'      => 'T4HTTP Siswa Dua',
        ':gender'    => 'L',
        ':join_date' => $lastMonth,
    ]);
    $studentId2 = (int) $pdo->lastInsertId();
    $created['students'][] = $studentId2;
    $member2 = Membership::add($groupId2, $studentId2, $day(1));
    if (($member2['ok'] ?? false) !== true) {
        throw new RuntimeException('Gagal menambah anggota kedua: ' . (string) ($member2['error'] ?? ''));
    }

    $package1 = StudentPackage::createForMembership($membershipId, $day(1));
    if (($package1['ok'] ?? false) !== true) {
        throw new RuntimeException('Gagal membuat paket uji: ' . (string) ($package1['error'] ?? ''));
    }
    $packageId = (int) $package1['id'];
    $invoiceId = (int) $package1['invoice_id'];

    // 16 pertemuan: paket 1 dan 2 selesai, paket 3 aktif — semua tagihan belum dibayar.
    for ($i = 1; $i <= 16; $i++) {
        $meeting = Meeting::record([
            'schedule_id'      => 0,
            'group_subject_id' => $relationId,
            'meeting_date'     => $day($i),
            'start_time'       => '15:00',
            'end_time'         => '16:30',
            'status'           => 'terlaksana',
            'notes'            => '',
            'reason'           => '',
        ]);
        if (($meeting['ok'] ?? false) !== true) {
            throw new RuntimeException('Gagal mencatat pertemuan uji ke-' . $i . ': ' . (string) ($meeting['error'] ?? ''));
        }
    }

    $package2Id = (int) $pdo->query(
        'SELECT id FROM student_packages WHERE group_member_id = ' . $membershipId . ' AND package_number = 2 LIMIT 1'
    )->fetchColumn();
    $package3Id = (int) $pdo->query(
        'SELECT id FROM student_packages WHERE group_member_id = ' . $membershipId . ' AND package_number = 3 LIMIT 1'
    )->fetchColumn();
    if ($package2Id <= 0 || $package3Id <= 0) {
        throw new RuntimeException('Paket berikutnya tidak terbentuk otomatis setelah 16 pertemuan.');
    }
    $invoice2Id = (int) $pdo->query('SELECT invoice_id FROM student_packages WHERE id = ' . $package2Id)->fetchColumn();
    $invoice3Id = (int) $pdo->query('SELECT invoice_id FROM student_packages WHERE id = ' . $package3Id)->fetchColumn();
    if ($invoice2Id <= 0 || $invoice3Id <= 0) {
        throw new RuntimeException('Tagihan paket berikutnya tidak terbit otomatis.');
    }

    $invoiceNumber1 = (string) Invoice::find($invoiceId)['invoice_number'];
    $invoiceNumber2 = (string) Invoice::find($invoice2Id)['invoice_number'];
    $invoiceNumber3 = (string) Invoice::find($invoice3Id)['invoice_number'];
    $totalDebt = $tariff * 3;
    echo "  data uji siap (paket #{$packageId}/#{$package2Id}/#{$package3Id}, tagihan {$invoiceNumber1}, {$invoiceNumber2}, {$invoiceNumber3})\n";

    // -----------------------------------------------------------------
    echo "\n== A. Login, peran, dan dashboard utang ==\n";
    // -----------------------------------------------------------------
    check('Login admin berhasil', login('admin', 'Admin@2026', COOKIE_ADMIN));
    check('Login petugas berhasil', login('petugas', 'Petugas@2026', COOKIE_PETUGAS));

    $page = http('GET', '/admin/dashboard.php', [], COOKIE_ADMIN);
    check('Dashboard admin dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Dashboard memuat kartu Total Utang', stripos($page['body'], 'Total Utang') !== false);
    check('Dashboard memuat kartu Siswa Berutang', stripos($page['body'], 'Siswa Berutang') !== false);
    check('Dashboard memuat kartu Uang Diterima', stripos($page['body'], 'Uang Diterima') !== false);
    check('Dashboard memuat total utang uji', str_contains($page['body'], 'Rp2.550.000'), 'tidak menemukan Rp2.550.000');
    check('Dashboard memuat siswa berutang uji', stripos($page['body'], 'T4HTTP Siswa Uji') !== false);
    check('Dashboard menjelaskan utang tidak menghalangi paket', stripos($page['body'], 'tidak menghalangi') !== false);
    check('Dashboard memuat tautan laporan utang', stripos($page['body'], 'laporan/utang.php') !== false);

    $page = http('GET', '/admin/tarif/edit.php?id=' . $created['pricing'][0], [], COOKIE_PETUGAS);
    check('Petugas ditolak dari form tarif (khusus admin)', in_array($page['status'], [302, 403], true), 'status ' . $page['status']);

    $page = http('GET', '/admin/pembayaran/tambah.php?student_id=' . $studentId, [], COOKIE_PETUGAS);
    check('Petugas dapat membuka form pembayaran multi-tagihan', $page['status'] === 200, 'status ' . $page['status']);

    // -----------------------------------------------------------------
    echo "\n== B. Halaman paket & realisasi ==\n";
    // -----------------------------------------------------------------
    $page = http('GET', '/admin/paket/index.php', [], COOKIE_ADMIN);
    check('Daftar paket dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Daftar paket memuat data uji', stripos($page['body'], 'T4HTTP') !== false);
    check('Daftar paket menjelaskan paket berikutnya langsung aktif', stripos($page['body'], 'langsung aktif') !== false);

    $page = http('GET', '/admin/paket/tambah.php', [], COOKIE_ADMIN);
    check('Form tambah paket dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Form tambah paket memuat pilihan keanggotaan', stripos($page['body'], 'name="group_member_id"') !== false);
    check('Form tambah paket menampilkan siswa tanpa paket', stripos($page['body'], 'T4HTTP Siswa Dua') !== false);

    $page = http('GET', '/admin/paket/detail.php?id=' . $packageId, [], COOKIE_ADMIN);
    check('Detail paket 1 dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Detail paket menampilkan progres pertemuan', stripos($page['body'], 'Progres pertemuan') !== false);
    check('Detail paket 1 menampilkan hitungan 8/8', preg_match('/8\s*\/\s*8/', $page['body']) === 1);
    check('Detail paket 1 berstatus Selesai', stripos($page['body'], 'Selesai') !== false);

    $page = http('GET', '/admin/paket/detail.php?id=' . $package3Id, [], COOKIE_ADMIN);
    check('Detail paket 3 dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Detail paket 3 berstatus Aktif', stripos($page['body'], 'Aktif') !== false);

    $page = http('GET', '/admin/tarif/index.php', [], COOKIE_ADMIN);
    check('Daftar tarif dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Daftar tarif menyebut paket 8 pertemuan', stripos($page['body'], '8 pertemuan') !== false);

    $page = http('GET', '/admin/realisasi-pertemuan/index.php?bulan=' . date('n', strtotime($lastMonth)) . '&tahun=' . date('Y', strtotime($lastMonth)), [], COOKIE_ADMIN);
    check('Realisasi pertemuan dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Realisasi memuat status Terlaksana', stripos($page['body'], 'Terlaksana') !== false);

    // -----------------------------------------------------------------
    echo "\n== C. Tagihan & cetak invoice ==\n";
    // -----------------------------------------------------------------
    $page = http('GET', '/admin/tagihan/index.php', [], COOKIE_ADMIN);
    check('Daftar tagihan dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Daftar tagihan memuat tagihan paket 1', strpos($page['body'], $invoiceNumber1) !== false);
    check('Daftar tagihan memuat tagihan paket 2', strpos($page['body'], $invoiceNumber2) !== false);
    check('Daftar tagihan memuat tagihan paket 3', strpos($page['body'], $invoiceNumber3) !== false);

    $page = http('GET', '/admin/tagihan/detail.php?id=' . $invoiceId, [], COOKIE_ADMIN);
    check('Detail tagihan dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Detail tagihan paket 1 masih Belum Bayar', stripos($page['body'], 'Belum Bayar') !== false);

    $page = http('GET', '/admin/tagihan/cetak.php?id=' . $invoiceId, [], COOKIE_ADMIN);
    check('Cetak invoice belum bayar dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Cetak invoice memuat nomor invoice', strpos($page['body'], $invoiceNumber1) !== false);
    check('Cetak invoice memuat identitas aplikasi', stripos($page['body'], Profile::name()) !== false);
    check(
        'Cetak invoice memuat logo',
        stripos($page['body'], 'logo-mark') !== false || stripos($page['body'], 'uploads/profil/') !== false
    );
    check('Cetak invoice memuat paket 8 pertemuan', preg_match('/Paket\s*1/', $page['body']) === 1);
    check('Cetak invoice memakai lembar cetak', stripos($page['body'], 'print-sheet') !== false);
    check('Cetak invoice memuat tombol cetak/PDF', stripos($page['body'], 'window.print()') !== false);
    check('Invoice belum bayar tidak dicap lunas', stripos($page['body'], 'Belum Bayar') !== false);

    // -----------------------------------------------------------------
    echo "\n== D. Utang terlihat di detail siswa & laporan ==\n";
    // -----------------------------------------------------------------
    $page = http('GET', '/admin/siswa/detail.php?id=' . $studentId, [], COOKIE_ADMIN);
    check('Detail siswa dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Detail siswa memuat Ringkasan Keuangan', stripos($page['body'], 'Ringkasan Keuangan') !== false);
    check('Detail siswa memuat Total Utang', stripos($page['body'], 'Total Utang') !== false);
    check('Detail siswa memuat total utang Rp2.550.000', str_contains($page['body'], 'Rp2.550.000'));
    check('Detail siswa memuat jumlah tagihan belum dibayar', stripos($page['body'], '3 paket') !== false);
    check('Detail siswa memuat tagihan paket 1', str_contains($page['body'], $invoiceNumber1));
    check('Detail siswa memuat tagihan paket 2', str_contains($page['body'], $invoiceNumber2));
    check('Detail siswa memuat tagihan paket 3', str_contains($page['body'], $invoiceNumber3));
    check('Detail siswa belum memiliki pembayaran', stripos($page['body'], 'Belum ada pembayaran') !== false);
    check('Detail siswa memuat tombol bayar tagihan', stripos($page['body'], 'pembayaran/tambah.php?student_id=' . $studentId) !== false);

    $page = http('GET', '/admin/laporan/utang.php', [], COOKIE_ADMIN);
    check('Laporan utang dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Laporan utang memuat judul', stripos($page['body'], 'Laporan Utang') !== false);
    check('Laporan utang memuat total utang', str_contains($page['body'], 'Rp2.550.000'));
    check('Laporan utang memuat siswa berutang', stripos($page['body'], 'T4HTTP Siswa Uji') !== false);
    check('Laporan utang memuat kode siswa', stripos($page['body'], 'T4HTTP-S' . $stamp) !== false);
    check('Laporan utang memuat rincian tagihan', str_contains($page['body'], $invoiceNumber2));
    check('Laporan utang memuat filter tanggal', stripos($page['body'], 'name="date_from"') !== false);
    check('Laporan utang memuat filter kelompok', stripos($page['body'], 'name="group_id"') !== false);
    check('Laporan utang memuat filter siswa', stripos($page['body'], 'name="student_id"') !== false);

    $page = http('GET', '/admin/laporan/utang.php?student_id=' . $studentId . '&group_id=' . $groupId, [], COOKIE_ADMIN);
    check('Laporan utang dapat difilter siswa & kelompok', $page['status'] === 200, 'status ' . $page['status']);
    check('Hasil filter utang memuat siswa uji', stripos($page['body'], 'T4HTTP Siswa Uji') !== false);

    $page = http('GET', '/admin/laporan/utang.php?export=csv', [], COOKIE_ADMIN);
    check('Ekspor CSV utang berhasil', $page['status'] === 200, 'status ' . $page['status']);
    check('Ekspor CSV utang memakai content-type CSV', stripos($page['type'], 'text/csv') !== false, $page['type']);
    check('Ekspor CSV utang memakai BOM UTF-8', str_starts_with($page['body'], "\xEF\xBB\xBF"));
    check('Ekspor CSV utang memakai pemisah titik koma', str_contains($page['body'], ';'));
    check('Ekspor CSV utang memuat kode siswa', str_contains($page['body'], 'T4HTTP-S' . $stamp));
    check('Ekspor CSV utang memuat tagihan paket 1', str_contains($page['body'], $invoiceNumber1));
    check('Ekspor CSV utang memuat tagihan paket 3', str_contains($page['body'], $invoiceNumber3));

    // -----------------------------------------------------------------
    echo "\n== E. Form pembayaran multi-tagihan & nominal salah ==\n";
    // -----------------------------------------------------------------
    $formUrl = '/admin/pembayaran/tambah.php?student_id=' . $studentId;
    $page = http('GET', $formUrl, [], COOKIE_ADMIN);
    check('Form pelunasan siswa dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Form memakai checkbox invoice_ids[]', stripos($page['body'], 'name="invoice_ids[]"') !== false);
    check('Form memuat tagihan paket 1', str_contains($page['body'], $invoiceNumber1));
    check('Form memuat tagihan paket 2', str_contains($page['body'], $invoiceNumber2));
    check('Form memuat tagihan paket 3', str_contains($page['body'], $invoiceNumber3));
    check('Form menandai aturan WAJIB LUNAS', stripos($page['body'], 'WAJIB LUNAS') !== false);
    check('Form menampilkan total utang', str_contains($page['body'], 'Rp2.550.000'));
    check('Form mengunci nominal pembayaran', stripos($page['body'], 'amount-locked') !== false);
    check('Form menampilkan petunjuk pemilihan tagihan', str_contains($page['body'], 'id="paymentHint"'));
    check('Tombol simpan tidak dinonaktifkan diam-diam', !str_contains($page['body'], 'submit.disabled = count === 0'));
    $token = csrf_from($page['body']);
    check('Form pelunasan memuat token CSRF', $token !== '');

    $paymentsBefore = (int) $pdo->query('SELECT COUNT(*) FROM payments WHERE student_id = ' . $studentId)->fetchColumn();
    $wrong = http('POST', '/admin/pembayaran/proses.php', [
        '_csrf'        => $token,
        'action'       => 'pay',
        'invoice_ids'  => [$invoiceId],
        'payment_date' => $today,
        'method'       => 'tunai',
        'reference'    => 'T4HTTP-SALAH',
        'notes'        => 'Nominal salah',
        'amount'       => '800000',
    ], COOKIE_ADMIN);
    check('Nominal kurang dari total ditolak (redirect)', $wrong['status'] === 302, 'status ' . $wrong['status']);
    checkSameInt(
        'Tidak ada pembayaran tersimpan setelah nominal salah',
        $paymentsBefore,
        (int) $pdo->query('SELECT COUNT(*) FROM payments WHERE student_id = ' . $studentId)->fetchColumn()
    );
    check('Tagihan 1 tetap belum dibayar setelah nominal salah', (string) Invoice::find($invoiceId)['status'] === 'belum_bayar');

    $state = http('GET', $wrong['location'] !== '' ? parse_url($wrong['location'], PHP_URL_PATH) . '?' . (string) parse_url($wrong['location'], PHP_URL_QUERY) : $formUrl, [], COOKIE_ADMIN);
    check('Halaman form menampilkan pesan gagal', stripos($state['body'], 'belum dapat disimpan') !== false);
    check('Pesan gagal menyebut nominal harus sama persis', stripos($state['body'], 'sama persis') !== false);

    // -----------------------------------------------------------------
    echo "\n== F. Pelunasan satu tagihan (paket 1) ==\n";
    // -----------------------------------------------------------------
    $page = http('GET', $formUrl, [], COOKIE_ADMIN);
    $token = csrf_from($page['body']);
    $payOne = http('POST', '/admin/pembayaran/proses.php', [
        '_csrf'        => $token,
        'action'       => 'pay',
        'invoice_ids'  => [$invoiceId],
        'payment_date' => $today,
        'method'       => 'transfer',
        'reference'    => 'T4HTTP-TRX1',
        'notes'        => 'Pelunasan paket 1',
        // Meniru nilai yang dikirim JavaScript form (dua angka desimal).
        'amount'       => number_format((float) $tariff, 2, '.', ''),
    ], COOKIE_ADMIN);
    check('Pelunasan satu tagihan diterima (redirect ke nota)', $payOne['status'] === 302 && strpos($payOne['location'], 'nota.php') !== false, 'status ' . $payOne['status'] . ' loc ' . $payOne['location']);
    $payment1Id = preg_match('/id=(\d+)/', $payOne['location'], $m) === 1 ? (int) $m[1] : 0;
    check('Nota pembayaran pertama terbentuk', $payment1Id > 0);

    $payment1Row = $payment1Id > 0 ? $pdo->query('SELECT * FROM payments WHERE id = ' . $payment1Id)->fetch() : null;
    check('Pembayaran pertama tersimpan', is_array($payment1Row));
    check('Nominal pembayaran pertama sesuai tagihan', is_array($payment1Row) && abs((float) $payment1Row['amount'] - (float) $tariff) < 0.005);
    check('Pembayaran pertama menyimpan invoice_id NULL', is_array($payment1Row) && $payment1Row['invoice_id'] === null);
    checkSameInt(
        'Pembayaran pertama memiliki satu alokasi',
        1,
        (int) $pdo->query('SELECT COUNT(*) FROM payment_allocations WHERE payment_id = ' . $payment1Id)->fetchColumn()
    );
    check('Tagihan paket 1 menjadi Lunas', (string) Invoice::find($invoiceId)['status'] === 'lunas');
    check('Tagihan paket 2 tetap Belum Bayar', (string) Invoice::find($invoice2Id)['status'] === 'belum_bayar');
    check('Tagihan paket 3 tetap Belum Bayar', (string) Invoice::find($invoice3Id)['status'] === 'belum_bayar');

    $page = http('GET', '/admin/siswa/detail.php?id=' . $studentId, [], COOKIE_ADMIN);
    check('Setelah bayar satu tagihan, utang turun menjadi Rp1.700.000', str_contains($page['body'], 'Rp1.700.000'));
    check('Riwayat pembayaran siswa memuat nota pertama', strpos($page['body'], (string) $payment1Row['payment_number']) !== false);

    $page = http('GET', '/admin/pembayaran/nota.php?id=' . $payment1Id, [], COOKIE_ADMIN);
    check('Nota pembayaran pertama dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Nota memuat nomor nota', strpos($page['body'], (string) $payment1Row['payment_number']) !== false);
    check('Nota memuat nomor tagihan paket 1', str_contains($page['body'], $invoiceNumber1));
    check('Nota memuat nama siswa', stripos($page['body'], 'T4HTTP Siswa Uji') !== false);
    check('Nota memuat 8 pertemuan', stripos($page['body'], '8 pertemuan') !== false);
    check('Nota memuat status Lunas', stripos($page['body'], 'Lunas') !== false);
    check('Nota memuat petugas penerima', stripos($page['body'], (string) $admin['name']) !== false);
    check('Nota memakai ukuran A5', stripos($page['body'], 'print-sheet--a5') !== false);
    check(
        'Nota menyertakan logo organisasi',
        stripos($page['body'], 'logo-mark') !== false || stripos($page['body'], 'uploads/profil/') !== false
    );

    $page = http('GET', '/admin/pembayaran/detail.php?id=' . $payment1Id, [], COOKIE_ADMIN);
    check('Detail pembayaran pertama dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Detail pembayaran memuat rincian alokasi', stripos($page['body'], 'Rincian Alokasi') !== false);
    check('Detail pembayaran memuat tagihan paket 1', str_contains($page['body'], $invoiceNumber1));

    // -----------------------------------------------------------------
    echo "\n== G. Pelunasan dua tagihan sekaligus ==\n";
    // -----------------------------------------------------------------
    $page = http('GET', $formUrl, [], COOKIE_ADMIN);
    $token = csrf_from($page['body']);
    check('Form hanya menampilkan tagihan yang belum dibayar', !str_contains($page['body'], $invoiceNumber1));
    check('Form menampilkan tagihan paket 2 & 3', str_contains($page['body'], $invoiceNumber2) && str_contains($page['body'], $invoiceNumber3));

    $payMany = http('POST', '/admin/pembayaran/proses.php', [
        '_csrf'        => $token,
        'action'       => 'pay',
        'invoice_ids'  => [$invoice3Id, $invoice2Id],
        'payment_date' => $today,
        'method'       => 'qris',
        'reference'    => 'T4HTTP-TRX2',
        'notes'        => 'Pelunasan paket 2 dan 3',
        'amount'       => number_format((float) ($tariff * 2), 2, '.', ''),
    ], COOKIE_ADMIN);
    check('Pelunasan dua tagihan diterima (redirect ke nota)', $payMany['status'] === 302 && strpos($payMany['location'], 'nota.php') !== false, 'status ' . $payMany['status'] . ' loc ' . $payMany['location']);
    $payment2Id = preg_match('/id=(\d+)/', $payMany['location'], $m) === 1 ? (int) $m[1] : 0;
    check('Nota pembayaran kedua terbentuk', $payment2Id > 0);

    $payment2Row = $payment2Id > 0 ? $pdo->query('SELECT * FROM payments WHERE id = ' . $payment2Id)->fetch() : null;
    check('Pembayaran kedua tersimpan', is_array($payment2Row));
    check('Nominal pembayaran kedua = dua tagihan', is_array($payment2Row) && abs((float) $payment2Row['amount'] - (float) ($tariff * 2)) < 0.005);
    checkSameInt(
        'Pembayaran kedua memiliki dua alokasi',
        2,
        (int) $pdo->query('SELECT COUNT(*) FROM payment_allocations WHERE payment_id = ' . $payment2Id)->fetchColumn()
    );
    check(
        'Total alokasi = total pembayaran kedua',
        abs((float) $pdo->query(
            'SELECT COALESCE(SUM(amount), 0) FROM payment_allocations WHERE payment_id = ' . $payment2Id
        )->fetchColumn() - (float) ($tariff * 2)) < 0.005
    );
    checkSameInt(
        'Setiap tagihan mendapat alokasi penuh',
        2,
        (int) $pdo->query(
            'SELECT COUNT(*) FROM payment_allocations WHERE payment_id = ' . $payment2Id
            . ' AND amount = ' . (int) $tariff
        )->fetchColumn()
    );
    check('Tagihan paket 2 menjadi Lunas', (string) Invoice::find($invoice2Id)['status'] === 'lunas');
    check('Tagihan paket 3 menjadi Lunas', (string) Invoice::find($invoice3Id)['status'] === 'lunas');

    $page = http('GET', '/admin/pembayaran/nota.php?id=' . $payment2Id, [], COOKIE_ADMIN);
    check('Nota multi-tagihan dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Nota multi-tagihan memuat tagihan paket 2', str_contains($page['body'], $invoiceNumber2));
    check('Nota multi-tagihan memuat tagihan paket 3', str_contains($page['body'], $invoiceNumber3));
    check('Nota multi-tagihan memuat total Rp1.700.000', str_contains($page['body'], 'Rp1.700.000'));
    check('Nota multi-tagihan memuat jumlah tagihan', stripos($page['body'], '2 tagihan') !== false);
    check('Nota multi-tagihan menyatakan Lunas', stripos($page['body'], 'Lunas') !== false);

    $page = http('GET', '/admin/pembayaran/detail.php?id=' . $payment2Id, [], COOKIE_ADMIN);
    check('Detail pembayaran multi-tagihan dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Detail pembayaran multi memuat paket 2', str_contains($page['body'], $invoiceNumber2));
    check('Detail pembayaran multi memuat paket 3', str_contains($page['body'], $invoiceNumber3));
    check('Detail pembayaran menjelaskan status paket tidak berubah', stripos($page['body'], 'tidak berubah karena pembayaran') !== false);

    $page = http('GET', '/admin/siswa/detail.php?id=' . $studentId, [], COOKIE_ADMIN);
    check('Setelah semua lunas tidak ada utang tersisa', stripos($page['body'], 'Tidak ada utang paket belajar yang tersisa') !== false);
    check('Riwayat pembayaran memuat nota kedua', strpos($page['body'], (string) $payment2Row['payment_number']) !== false);

    $page = http('GET', '/admin/laporan/utang.php?student_id=' . $studentId, [], COOKIE_ADMIN);
    check('Laporan utang kosong setelah semua lunas', stripos($page['body'], 'Tidak ada utang pada filter ini') !== false);

    // -----------------------------------------------------------------
    echo "\n== H. Keamanan: CSRF, metode, dan login ==\n";
    // -----------------------------------------------------------------
    $paymentsFinal = (int) $pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();
    $page = http('POST', '/admin/pembayaran/proses.php', [
        'action'      => 'pay',
        'invoice_ids' => [$invoiceId],
        'amount'      => (string) $tariff,
    ], COOKIE_ADMIN);
    check('POST pembayaran tanpa token CSRF ditolak (419)', $page['status'] === 419, 'status ' . $page['status']);
    checkSameInt('Tidak ada pembayaran baru dari POST tanpa CSRF', $paymentsFinal, (int) $pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());

    $page = http('GET', '/admin/pembayaran/proses.php', [], COOKIE_ADMIN);
    check('GET pada endpoint pembayaran ditolak', in_array($page['status'], [405, 419], true), 'status ' . $page['status']);

    $beforePackages = (int) $pdo->query('SELECT COUNT(*) FROM student_packages')->fetchColumn();
    $page = http('POST', '/admin/paket/proses.php', [
        'action'          => 'create',
        'group_member_id' => (string) $membershipId,
        'start_date'      => $today,
    ], COOKIE_ADMIN);
    check('POST paket tanpa token CSRF ditolak (419)', $page['status'] === 419, 'status ' . $page['status']);
    checkSameInt('Tidak ada paket baru dari POST tanpa CSRF', $beforePackages, (int) $pdo->query('SELECT COUNT(*) FROM student_packages')->fetchColumn());

    $page = http('GET', '/admin/paket/proses.php', [], COOKIE_ADMIN);
    check('GET pada endpoint paket ditolak', in_array($page['status'], [405, 419], true), 'status ' . $page['status']);

    $page = http('GET', '/admin/paket/index.php');
    check('Halaman admin tanpa login dialihkan ke login', $page['status'] === 302, 'status ' . $page['status']);
    check('Pengalihan menuju halaman login', strpos($page['location'], 'login.php') !== false, $page['location']);

    $page = http('GET', '/admin/pembayaran/nota.php?id=' . $payment2Id);
    check('Halaman nota tanpa login dialihkan', $page['status'] === 302, 'status ' . $page['status']);

    $page = http('GET', '/admin/laporan/utang.php');
    check('Laporan utang tanpa login dialihkan', $page['status'] === 302, 'status ' . $page['status']);

    // -----------------------------------------------------------------
    echo "\n== I. Paket massal per group via HTTP ==\n";
    // -----------------------------------------------------------------
    $ins = $pdo->prepare(
        'INSERT INTO pricing_rules
            (group_type, student_count, package_size, amount, effective_start, effective_end, is_active, notes, created_by)
         VALUES (:type, :count, 8, :amount, :start, NULL, 1, :notes, :by)'
    );
    $ins->execute([
        ':type'   => 'group',
        ':count'  => 2,
        ':amount' => 900000,
        ':start'  => $tariffStart,
        ':notes'  => 'T4HTTP tarif group',
        ':by'     => (int) $admin['id'],
    ]);
    $created['pricing'][] = (int) $pdo->lastInsertId();

    $bulkGroup = Group::create([
        'name'       => 'T4HTTP Group Massal ' . $stamp,
        'group_type' => 'group',
        'capacity'   => 7,
        'is_active'  => 1,
    ]);
    $bulkGroupId = (int) $bulkGroup['id'];
    $created['groups'][] = $bulkGroupId;
    GroupSubject::create([
        'group_id'   => $bulkGroupId,
        'subject_id' => $subjectId,
        'teacher_id' => $teacherId,
        'is_active'  => 1,
    ]);

    $bulkMemberships = [];
    foreach ([['T4HTTP-B1', 'T4HTTP Massal Satu'], ['T4HTTP-B2', 'T4HTTP Massal Dua']] as $index => [$code, $name]) {
        $ins = $pdo->prepare(
            'INSERT INTO students (code, name, gender, join_date, is_active)
             VALUES (:code, :name, :gender, :join_date, 1)'
        );
        $ins->execute([
            ':code'      => $code . $stamp,
            ':name'      => $name,
            ':gender'    => $index === 0 ? 'P' : 'L',
            ':join_date' => $lastMonth,
        ]);
        $bulkStudentId = (int) $pdo->lastInsertId();
        $created['students'][] = $bulkStudentId;
        $bulkMember = Membership::add($bulkGroupId, $bulkStudentId, $day(1));
        if (($bulkMember['ok'] ?? false) !== true) {
            throw new RuntimeException('Gagal menambah anggota massal: ' . (string) ($bulkMember['error'] ?? ''));
        }
        $bulkMemberships[] = (int) $bulkMember['membership_id'];
    }

    $page = http('GET', '/admin/paket/tambah.php?mode=group&group_id=' . $bulkGroupId, [], COOKIE_ADMIN);
    check('Halaman paket massal per group dapat dibuka', $page['status'] === 200, 'status ' . $page['status']);
    check('Halaman massal memuat judul mode', stripos($page['body'], 'Paket Massal per Group') !== false);
    check('Halaman massal memuat kedua anggota', stripos($page['body'], 'T4HTTP Massal Satu') !== false
        && stripos($page['body'], 'T4HTTP Massal Dua') !== false);
    check('Halaman massal memakai checkbox membership_ids[]', stripos($page['body'], 'name="membership_ids[]"') !== false);
    check('Form massal mengirim action create_group', stripos($page['body'], 'value="create_group"') !== false);
    $bulkToken = csrf_from($page['body']);
    check('Form massal memuat token CSRF', $bulkToken !== '');

    $badBulk = http('POST', '/admin/paket/proses.php', [
        '_csrf'      => $bulkToken,
        'action'     => 'create_group',
        'group_id'   => (string) $bulkGroupId,
        'start_date' => $day(1),
        'discount'   => '0',
        'notes'      => 'T4HTTP massal',
    ], COOKIE_ADMIN);
    check('Massal tanpa memilih siswa ditolak (redirect)', $badBulk['status'] === 302, 'status ' . $badBulk['status']);
    checkSameInt(
        'Tidak ada paket dibuat saat siswa belum dipilih',
        0,
        (int) $pdo->query('SELECT COUNT(*) FROM student_packages WHERE group_id = ' . $bulkGroupId)->fetchColumn()
    );
    $badPath = (string) parse_url($badBulk['location'], PHP_URL_PATH);
    $badQuery = (string) parse_url($badBulk['location'], PHP_URL_QUERY);
    $badState = http('GET', $badPath . ($badQuery !== '' ? '?' . $badQuery : ''), [], COOKIE_ADMIN);
    check('Pesan validasi massal ditampilkan', stripos($badState['body'], 'Pilih minimal satu siswa') !== false);

    $bulkToken = csrf_from($badState['body']);
    $bulkPost = http('POST', '/admin/paket/proses.php', [
        '_csrf'          => $bulkToken,
        'action'         => 'create_group',
        'group_id'       => (string) $bulkGroupId,
        'start_date'     => $day(1),
        'discount'       => '0',
        'notes'          => 'T4HTTP massal',
        'membership_ids' => array_map('strval', $bulkMemberships),
    ], COOKIE_ADMIN);
    check(
        'POST massal berhasil dialihkan ke daftar paket group',
        $bulkPost['status'] === 302 && strpos($bulkPost['location'], 'admin/paket/index.php?group_id=' . $bulkGroupId) !== false,
        'status ' . $bulkPost['status'] . ' loc ' . $bulkPost['location']
    );
    checkSameInt(
        'Dua paket dibuat lewat HTTP',
        2,
        (int) $pdo->query('SELECT COUNT(*) FROM student_packages WHERE group_id = ' . $bulkGroupId)->fetchColumn()
    );
    checkSameInt(
        'Dua tagihan diterbitkan lewat HTTP',
        2,
        (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE group_id = " . $bulkGroupId . " AND invoice_type = 'paket'")->fetchColumn()
    );

    $page = http('GET', '/admin/paket/index.php?group_id=' . $bulkGroupId, [], COOKIE_ADMIN);
    check('Daftar paket hasil massal dapat difilter per group', $page['status'] === 200, 'status ' . $page['status']);
    check('Daftar paket menampilkan siswa massal', stripos($page['body'], 'T4HTTP Massal Satu') !== false);
    check('Daftar paket menampilkan pesan sukses massal', stripos($page['body'], 'paket belajar berhasil dibuat untuk group') !== false);
} catch (Throwable $exception) {
    echo "\nFATAL: " . get_class($exception) . ': ' . $exception->getMessage() . "\n";
    $fail++;
    $failures[] = 'FATAL ' . $exception->getMessage();
} finally {
    // Bersihkan data uji.
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec("DELETE FROM student_package_meetings WHERE student_id IN (SELECT id FROM students WHERE code LIKE 'T4HTTP%')");
    $pdo->exec("DELETE FROM payment_allocations WHERE invoice_id IN (SELECT id FROM invoices WHERE student_id IN (SELECT id FROM students WHERE code LIKE 'T4HTTP%'))");
    $pdo->exec("DELETE FROM payments WHERE student_id IN (SELECT id FROM students WHERE code LIKE 'T4HTTP%')");
    $pdo->exec("DELETE FROM invoices WHERE student_id IN (SELECT id FROM students WHERE code LIKE 'T4HTTP%')");
    $pdo->exec("DELETE FROM student_packages WHERE student_id IN (SELECT id FROM students WHERE code LIKE 'T4HTTP%')");
    $pdo->exec("DELETE FROM group_members WHERE student_id IN (SELECT id FROM students WHERE code LIKE 'T4HTTP%')");
    $pdo->exec("DELETE FROM students WHERE code LIKE 'T4HTTP%'");
    $pdo->exec("DELETE FROM class_meetings WHERE group_id IN (SELECT id FROM class_groups WHERE name LIKE 'T4HTTP%')");
    $pdo->exec("DELETE FROM group_schedules WHERE group_subject_id IN (SELECT id FROM group_subjects WHERE group_id IN (SELECT id FROM class_groups WHERE name LIKE 'T4HTTP%'))");
    $pdo->exec("DELETE FROM group_subjects WHERE group_id IN (SELECT id FROM class_groups WHERE name LIKE 'T4HTTP%')");
    $pdo->exec("DELETE FROM group_members WHERE group_id IN (SELECT id FROM class_groups WHERE name LIKE 'T4HTTP%')");
    $pdo->exec("DELETE FROM class_groups WHERE name LIKE 'T4HTTP%'");
    $pdo->exec("DELETE FROM pricing_rules WHERE notes LIKE 'T4HTTP%'");
    $pdo->exec("DELETE FROM teacher_subjects WHERE teacher_id IN (SELECT id FROM teachers WHERE code LIKE 'T4HTTP-%')");
    $pdo->exec("DELETE FROM teachers WHERE code LIKE 'T4HTTP-%'");
    $pdo->exec("DELETE FROM subjects WHERE code LIKE 'T4HTTP-%'");
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    @unlink(COOKIE_ADMIN);
    @unlink(COOKIE_PETUGAS);
}

echo "\n============================================\n";
echo "LULUS: {$pass}   GAGAL: {$fail}\n";
if ($fail > 0) {
    echo "\nRincian kegagalan:\n";
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
    exit(1);
}
exit(0);
