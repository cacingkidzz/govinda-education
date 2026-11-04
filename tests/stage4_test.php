<?php
declare(strict_types=1);

/**
 * Uji mandiri aturan bisnis Tahap 4 — revisi paket berjalan tanpa pelunasan.
 *
 * Fokus pengujian:
 * - paket 1 selesai saat tagihan 1 belum dibayar → paket 2 tetap dibuat & aktif;
 * - pembelajaran paket 2 berjalan meski tagihan lama menunggak;
 * - paket 2 selesai & ditagih saat tagihan 1 masih belum dibayar;
 * - utang menumpuk dan terlihat pada ringkasan/laporan;
 * - pembayaran hanya tagihan tertentu, pembayaran beberapa tagihan sekaligus;
 * - nominal salah ditolak; riwayat tidak hilang dan tidak terhitung ganda.
 *
 * Jalankan dari root proyek:
 *   php tests/stage4_test.php
 *
 * Skrip membuat data uji berawalan T4TEST- lalu menghapusnya kembali.
 * Data produksi tidak disentuh. Kode keluar 0 = semua lulus, 1 = ada kegagalan.
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

/**
 * Pastikan sebuah operasi GAGAL (hasil array dengan ok=false) dan pesannya memuat kata kunci.
 */
function expectFail(string $name, callable $callback, string $needle = ''): void
{
    $result = $callback();
    $ok = is_array($result) && ($result['ok'] ?? true) === false;
    check($name, $ok, is_array($result) ? (string) ($result['error'] ?? 'hasil tidak valid') : 'hasil tidak valid');
    if ($ok && $needle !== '') {
        check($name . ' (pesan)', stripos((string) ($result['error'] ?? ''), $needle) !== false, (string) ($result['error'] ?? ''));
    }
}

$pdo = db();

// ---------------------------------------------------------------------
// Sesi admin (dipakai Payment::payMany dan ActivityLog)
// ---------------------------------------------------------------------
$admin = $pdo->query("SELECT * FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1")->fetch();
if (!is_array($admin)) {
    fwrite(STDERR, "FATAL: tidak ada pengguna admin aktif. Jalankan seed database terlebih dahulu.\n");
    exit(1);
}
$_SESSION['auth_user_id'] = (int) $admin['id'];
$_SESSION['_last_activity'] = time();

$stamp = date('His');
$prefix = 'T4TEST-' . $stamp;

$created = [
    'groups'    => [],
    'teachers'  => [],
    'subjects'  => [],
    'students'  => [],
    'pricing'   => [],
    'members'   => [],
    'packages'  => [],
    'meetings'  => [],
];

$lastMonth = (new DateTimeImmutable('first day of last month'))->format('Y-m-d');
$day = static fn (int $n): string => (new DateTimeImmutable($lastMonth))->modify('+' . ($n - 1) . ' days')->format('Y-m-d');
$today = date('Y-m-d');

$insertTeacher = static function (string $name) use ($pdo, $prefix, &$created): int {
    $stmt = $pdo->prepare('INSERT INTO teachers (code, name, gender, is_active) VALUES (:code, :name, :gender, 1)');
    $stmt->execute([
        ':code'   => $prefix . '-G' . (count($created['teachers']) + 1),
        ':name'   => $name,
        ':gender' => 'L',
    ]);
    $id = (int) $pdo->lastInsertId();
    $created['teachers'][] = $id;
    return $id;
};

$insertSubject = static function (string $name) use ($pdo, $prefix, &$created): int {
    $stmt = $pdo->prepare('INSERT INTO subjects (code, name, is_active) VALUES (:code, :name, 1)');
    $stmt->execute([
        ':code' => $prefix . '-M' . (count($created['subjects']) + 1),
        ':name' => $name,
    ]);
    $id = (int) $pdo->lastInsertId();
    $created['subjects'][] = $id;
    return $id;
};

$insertStudent = static function (string $name) use ($pdo, $prefix, &$created, $lastMonth): int {
    $stmt = $pdo->prepare(
        'INSERT INTO students (code, name, gender, join_date, is_active)
         VALUES (:code, :name, :gender, :join_date, 1)'
    );
    $stmt->execute([
        ':code'      => $prefix . '-S' . (count($created['students']) + 1),
        ':name'      => $name,
        ':gender'    => 'P',
        ':join_date' => $lastMonth,
    ]);
    $id = (int) $pdo->lastInsertId();
    $created['students'][] = $id;
    return $id;
};

$createGroup = static function (string $name, string $type, int $capacity) use (&$created): int {
    $result = Group::create([
        'name'       => $name,
        'group_type' => $type,
        'capacity'   => $capacity,
        'is_active'  => 1,
    ]);
    $created['groups'][] = (int) $result['id'];
    return (int) $result['id'];
};

$link = static function (int $groupId, int $subjectId, int $teacherId): int {
    return GroupSubject::create([
        'group_id'   => $groupId,
        'subject_id' => $subjectId,
        'teacher_id' => $teacherId,
        'is_active'  => 1,
    ]);
};

$addMember = static function (int $groupId, int $studentId, string $startDate) use (&$created): int {
    $result = Membership::add($groupId, $studentId, $startDate);
    if (($result['ok'] ?? false) !== true) {
        throw new RuntimeException('Gagal menambah anggota: ' . (string) ($result['error'] ?? ''));
    }
    $created['members'][] = (int) $result['membership_id'];
    return (int) $result['membership_id'];
};

$createPricing = static function (string $type, int $count, float $amount, string $start, ?string $end) use ($pdo, &$created, $admin, $prefix): int {
    $stmt = $pdo->prepare(
        'INSERT INTO pricing_rules
            (group_type, student_count, package_size, amount, effective_start, effective_end, is_active, notes, created_by)
         VALUES (:type, :count, 8, :amount, :start, :end, 1, :notes, :by)'
    );
    $stmt->execute([
        ':type'   => $type,
        ':count'  => $count,
        ':amount' => $amount,
        ':start'  => $start,
        ':end'    => $end,
        ':notes'  => $prefix . ' tarif uji',
        ':by'     => (int) $admin['id'],
    ]);
    $id = (int) $pdo->lastInsertId();
    $created['pricing'][] = $id;
    return $id;
};

/**
 * Catat realisasi pertemuan dan kembalikan hasilnya.
 *
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
$recordMeeting = static function (
    int $relationId,
    string $date,
    string $status = 'terlaksana',
    array $extra = []
) use (&$created): array {
    $result = Meeting::record(array_merge([
        'schedule_id'      => 0,
        'group_subject_id' => $relationId,
        'meeting_date'     => $date,
        'start_time'       => '15:00',
        'end_time'         => '16:30',
        'status'           => $status,
        'notes'            => '',
        'reason'           => '',
    ], $extra));
    if (($result['ok'] ?? false) === true && (int) ($result['meeting_id'] ?? 0) > 0) {
        $created['meetings'][] = (int) $result['meeting_id'];
    }
    return $result;
};

$cleanup = static function () use ($pdo, &$created): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    if ($created['students'] !== []) {
        $ids = implode(',', array_map('intval', $created['students']));
        $pdo->exec('DELETE FROM student_package_meetings WHERE student_id IN (' . $ids . ')');
        $pdo->exec('DELETE FROM payment_allocations WHERE invoice_id IN
                    (SELECT id FROM invoices WHERE student_id IN (' . $ids . '))');
        $pdo->exec('DELETE FROM payments WHERE student_id IN (' . $ids . ')');
        $pdo->exec('DELETE FROM invoices WHERE student_id IN (' . $ids . ')');
        $pdo->exec('DELETE FROM student_packages WHERE student_id IN (' . $ids . ')');
        $pdo->exec('DELETE FROM student_group_history WHERE student_id IN (' . $ids . ')');
        $pdo->exec('DELETE FROM group_members WHERE student_id IN (' . $ids . ')');
        $pdo->exec('DELETE FROM students WHERE id IN (' . $ids . ')');
    }
    if ($created['groups'] !== []) {
        $ids = implode(',', array_map('intval', $created['groups']));
        $pdo->exec('DELETE FROM class_meetings WHERE group_id IN (' . $ids . ')');
        $pdo->exec('DELETE FROM group_schedules WHERE group_subject_id IN
                    (SELECT id FROM group_subjects WHERE group_id IN (' . $ids . '))');
        $pdo->exec('DELETE FROM group_subjects WHERE group_id IN (' . $ids . ')');
        $pdo->exec('DELETE FROM group_members WHERE group_id IN (' . $ids . ')');
        $pdo->exec('DELETE FROM class_groups WHERE id IN (' . $ids . ')');
    }
    if ($created['subjects'] !== []) {
        $ids = implode(',', array_map('intval', $created['subjects']));
        $pdo->exec('DELETE FROM teacher_subjects WHERE subject_id IN (' . $ids . ')');
        $pdo->exec('DELETE FROM subjects WHERE id IN (' . $ids . ')');
    }
    if ($created['teachers'] !== []) {
        $ids = implode(',', array_map('intval', $created['teachers']));
        $pdo->exec('DELETE FROM teacher_subjects WHERE teacher_id IN (' . $ids . ')');
        $pdo->exec('DELETE FROM teachers WHERE id IN (' . $ids . ')');
    }
    if ($created['pricing'] !== []) {
        $ids = implode(',', array_map('intval', $created['pricing']));
        $pdo->exec('DELETE FROM pricing_rules WHERE id IN (' . $ids . ')');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
};

try {
    // =================================================================
    echo "== Persiapan data uji ==\n";
    // =================================================================
    $teacher1 = $insertTeacher($prefix . ' Guru Satu');
    $teacher2 = $insertTeacher($prefix . ' Guru Dua');
    $subject1 = $insertSubject($prefix . ' Matematika');
    $subject2 = $insertSubject($prefix . ' Bahasa Inggris');

    // Tarif efektif sejak dua bulan lalu agar mencakup seluruh tanggal uji.
    $tariffStart = (new DateTimeImmutable('first day of last month'))->modify('-1 month')->format('Y-m-d');
    $tariffEnd = (new DateTimeImmutable('today'))->modify('+8 months')->format('Y-m-d');
    $createPricing('private', 1, 800000.0, $tariffStart, $tariffEnd);
    $createPricing('group', 2, 900000.0, $tariffStart, $tariffEnd);
    echo "  guru, mapel, dan tarif uji dibuat\n";

    // =================================================================
    echo "\n== A. Paket 1 dibuat dengan tagihan belum dibayar ==\n";
    // =================================================================
    $privateGroup = $createGroup($prefix . ' Private', 'private', 1);
    $privateRel1 = $link($privateGroup, $subject1, $teacher1);
    $privateRel2 = $link($privateGroup, $subject2, $teacher2);

    $s1 = $insertStudent($prefix . ' Siswa Utama');
    $m1 = $addMember($privateGroup, $s1, $day(1));

    $created1 = StudentPackage::createForMembership($m1, $day(1));
    check('Paket pertama dibuat', ($created1['ok'] ?? false) === true, (string) ($created1['error'] ?? ''));
    $pkg1 = (int) $created1['id'];
    $inv1 = (int) $created1['invoice_id'];
    $row1 = StudentPackage::find($pkg1);

    checkSame('Paket pertama bernomor 1', 1, (int) $row1['package_number']);
    checkSame('Target paket 8 pertemuan', 8, (int) $row1['target_meetings']);
    checkSame('Status paket pertama aktif', 'aktif', (string) $row1['status']);
    checkSame('Hitungan awal 0 pertemuan', 0, (int) $row1['completed_meetings']);
    check('Paket pertama langsung memiliki tagihan', $inv1 > 0);
    checkSame('Nominal paket private sesuai tarif', 800000.0, (float) $row1['total_amount']);

    $invoice1 = Invoice::find($inv1);
    checkSame('Jenis tagihan adalah paket', 'paket', (string) $invoice1['invoice_type']);
    checkSame('Snapshot nomor paket pada tagihan', 1, (int) $invoice1['package_number']);
    checkSame('Snapshot target pertemuan pada tagihan', 8, (int) $invoice1['meeting_target']);
    checkSame('Snapshot nama siswa', $prefix . ' Siswa Utama', (string) $invoice1['student_name']);
    checkSame('Label periode paket', 'Paket 1 — 8 pertemuan', (string) $invoice1['period_label']);
    checkSame('Tagihan awal belum dibayar', 'belum_bayar', (string) $invoice1['status']);
    checkSame('Total tagihan = tarif paket', 800000.0, (float) $invoice1['total_amount']);

    checkSame(
        'Menerbitkan tagihan ulang mengembalikan tagihan yang sama (idempoten)',
        $inv1,
        Invoice::createForPackage($pkg1, 0.0, $day(1))
    );

    // =================================================================
    echo "\n== B. Paket 1 selesai 8 pertemuan saat tagihan 1 belum dibayar ==\n";
    // =================================================================
    for ($i = 1; $i <= 7; $i++) {
        $r = $recordMeeting($i % 2 === 0 ? $privateRel2 : $privateRel1, $day($i));
        checkSame('Pertemuan ke-' . $i . ' dihitung', 1, (int) ($r['counted'] ?? 0));
    }
    checkSame('Paket 1 baru 7 pertemuan', 7, (int) StudentPackage::find($pkg1)['completed_meetings']);
    checkSame('Tagihan 1 masih belum dibayar sebelum pertemuan ke-8', 'belum_bayar', (string) Invoice::find($inv1)['status']);

    $r = $recordMeeting($privateRel1, $day(8));
    checkSame('Pertemuan ke-8 dihitung', 1, (int) ($r['counted'] ?? 0));

    $row1 = StudentPackage::find($pkg1);
    checkSame('Paket 1 selesai tepat 8 pertemuan', 'selesai', (string) $row1['status']);
    checkSame('Hitungan paket 1 = 8', 8, (int) $row1['completed_meetings']);
    checkSame('Tanggal selesai paket 1 tercatat', $day(8), (string) $row1['end_date']);
    checkSame('Tagihan 1 TETAP belum dibayar', 'belum_bayar', (string) Invoice::find($inv1)['status']);

    $pkg2Row = $pdo->query(
        'SELECT * FROM student_packages WHERE group_member_id = ' . $m1 . ' AND package_number = 2 LIMIT 1'
    )->fetch();
    check('Paket 2 otomatis dibuat meski tagihan 1 belum dibayar', is_array($pkg2Row));
    $pkg2 = is_array($pkg2Row) ? (int) $pkg2Row['id'] : 0;
    checkSame('Nomor paket berikutnya = 2', 2, (int) ($pkg2Row['package_number'] ?? 0));
    checkSame('Paket 2 LANGSUNG aktif tanpa menunggu pembayaran', 'aktif', (string) ($pkg2Row['status'] ?? ''));
    checkSame('Paket 2 dimulai dari 0 pertemuan', 0, (int) ($pkg2Row['completed_meetings'] ?? -1));
    checkSame('Paket 2 mulai pada tanggal pertemuan ke-8', $day(8), (string) ($pkg2Row['start_date'] ?? ''));

    $inv2 = (int) ($pkg2Row['invoice_id'] ?? 0);
    check('Tagihan paket 2 otomatis diterbitkan', $inv2 > 0);
    $invoice2 = $inv2 > 0 ? Invoice::find($inv2) : null;
    checkSame('Tagihan paket 2 belum dibayar', 'belum_bayar', (string) ($invoice2['status'] ?? ''));
    checkSame('Total tagihan paket 2 = tarif', 800000.0, (float) ($invoice2['total_amount'] ?? 0));

    $activeNow = StudentPackage::activeForMembership($m1);
    checkSame('Paket aktif keanggotaan sekarang adalah paket 2', $pkg2, (int) ($activeNow['id'] ?? 0));

    $again = StudentPackage::ensureNextPackage($pkg1, $day(8));
    checkSame('Pemanggilan ulang tidak membuat paket baru (idempoten)', $pkg2, (int) ($again['id'] ?? 0));
    checkSame(
        'Jumlah paket untuk keanggotaan tetap 2',
        2,
        (int) $pdo->query('SELECT COUNT(*) FROM student_packages WHERE group_member_id = ' . $m1)->fetchColumn()
    );

    // =================================================================
    echo "\n== C. Paket 2 berjalan & selesai saat tagihan 1 masih menunggak ==\n";
    // =================================================================
    $r = $recordMeeting($privateRel1, $day(9));
    checkSame('Pertemuan pertama paket 2 dihitung', 1, (int) ($r['counted'] ?? 0));
    checkSame('Hitungan paket 2 menjadi 1', 1, (int) StudentPackage::find($pkg2)['completed_meetings']);
    checkSame('Tagihan 1 masih belum dibayar', 'belum_bayar', (string) Invoice::find($inv1)['status']);
    checkSame(
        'Pertemuan baru tidak menambah hitungan paket 1',
        8,
        (int) StudentPackage::find($pkg1)['completed_meetings']
    );

    for ($i = 10; $i <= 16; $i++) {
        $recordMeeting($i % 2 === 0 ? $privateRel2 : $privateRel1, $day($i));
    }
    $row2 = StudentPackage::find($pkg2);
    checkSame('Paket 2 selesai setelah 8 pertemuan', 'selesai', (string) $row2['status']);
    checkSame('Hitungan paket 2 = 8', 8, (int) $row2['completed_meetings']);
    checkSame('Tagihan 1 tetap belum dibayar saat paket 2 selesai', 'belum_bayar', (string) Invoice::find($inv1)['status']);
    checkSame('Tagihan 2 tetap belum dibayar', 'belum_bayar', (string) Invoice::find($inv2)['status']);

    $pkg3Row = $pdo->query(
        'SELECT * FROM student_packages WHERE group_member_id = ' . $m1 . ' AND package_number = 3 LIMIT 1'
    )->fetch();
    check('Paket 3 otomatis dibuat saat paket 2 selesai', is_array($pkg3Row));
    $pkg3 = is_array($pkg3Row) ? (int) $pkg3Row['id'] : 0;
    checkSame('Paket 3 langsung aktif', 'aktif', (string) ($pkg3Row['status'] ?? ''));
    $inv3 = (int) ($pkg3Row['invoice_id'] ?? 0);
    check('Tagihan paket 3 otomatis diterbitkan', $inv3 > 0);
    checkSame('Tagihan paket 3 belum dibayar', 'belum_bayar', (string) (Invoice::find($inv3)['status'] ?? ''));

    $r = $recordMeeting($privateRel1, $day(17));
    checkSame('Paket 3 tetap dapat berjalan meski 2 tagihan menunggak', 1, (int) ($r['counted'] ?? 0));
    checkSame('Hitungan paket 3 menjadi 1', 1, (int) StudentPackage::find($pkg3)['completed_meetings']);
    checkSame(
        'Jumlah paket dan tagihan bertambah tanpa duplikasi',
        [3, 3],
        [
            (int) $pdo->query('SELECT COUNT(*) FROM student_packages WHERE student_id = ' . $s1)->fetchColumn(),
            (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE student_id = " . $s1 . " AND status <> 'dibatalkan'")->fetchColumn(),
        ]
    );

    // =================================================================
    echo "\n== D. Utang menumpuk & terbaca pada ringkasan ==\n";
    // =================================================================
    $debt = Invoice::debtSummary(['invoice_type' => Invoice::TYPE_PACKAGE, 'student_id' => $s1]);
    checkSame('Total tagihan 3 paket = Rp2.400.000', 2400000.0, $debt['billed_amount']);
    checkSame('Belum ada pembayaran diterima', 0.0, $debt['received_amount']);
    checkSame('Total utang = total tagihan', 2400000.0, $debt['debt_amount']);
    checkSame('Jumlah tagihan belum dibayar = 3', 3, $debt['unpaid_count']);
    checkSame('Jumlah siswa berutang = 1', 1, $debt['debtor_count']);
    checkSame('Jumlah tagihan lunas = 0', 0, $debt['paid_count']);

    $debtRows = Invoice::debtByStudent(['invoice_type' => Invoice::TYPE_PACKAGE, 'student_id' => $s1]);
    checkSame('Laporan utang per siswa memuat satu baris', 1, count($debtRows));
    $debtRow = $debtRows[0] ?? [];
    checkSame('Baris utang memuat nama siswa', $prefix . ' Siswa Utama', (string) ($debtRow['student_name'] ?? ''));
    checkSame('Baris utang memuat total utang', 2400000.0, (float) ($debtRow['debt_amount'] ?? 0));
    checkSame('Baris utang memuat jumlah belum dibayar', 3, (int) ($debtRow['unpaid_count'] ?? 0));

    $filteredDebt = Invoice::debtSummary([
        'invoice_type' => Invoice::TYPE_PACKAGE,
        'student_id'   => $s1,
        'date_from'    => $day(1),
        'date_to'      => $day(16),
    ]);
    checkSame('Filter periode menghitung tagihan dalam rentang', 3, $filteredDebt['unpaid_count']);

    $studentInvoices = Student::invoices($s1);
    checkSame('Riwayat tagihan siswa memuat 3 tagihan', 3, count($studentInvoices));
    $allUnpaid = true;
    foreach ($studentInvoices as $studentInvoice) {
        $allUnpaid = $allUnpaid && (string) $studentInvoice['status'] === 'belum_bayar'
            && (float) $studentInvoice['paid_amount'] === 0.0;
    }
    check('Semua tagihan siswa belum dibayar dan belum dialokasikan', $allUnpaid);

    // =================================================================
    echo "\n== E. Validasi pembayaran wajib lunas ==\n";
    // =================================================================
    expectFail(
        'Pembayaran kurang dari total ditolak',
        static fn (): array => Payment::pay($inv1, [
            'amount'       => 799999.99,
            'method'       => 'tunai',
            'payment_date' => $today,
        ]),
        'sama persis'
    );
    expectFail(
        'Pembayaran lebih dari total ditolak',
        static fn (): array => Payment::pay($inv1, [
            'amount'       => 800000.01,
            'method'       => 'tunai',
            'payment_date' => $today,
        ]),
        'sama persis'
    );
    expectFail(
        'Pembayaran beberapa tagihan dengan total salah ditolak',
        static fn (): array => Payment::payMany([$inv1, $inv2], [
            'amount'       => 1500000,
            'method'       => 'tunai',
            'payment_date' => $today,
        ]),
        'sama persis'
    );
    expectFail(
        'Metode pembayaran tidak valid ditolak',
        static fn (): array => Payment::pay($inv1, [
            'amount'       => 800000,
            'method'       => 'bitcoin',
            'payment_date' => $today,
        ]),
        'Metode'
    );
    expectFail(
        'Pembayaran tanpa tagihan dipilih ditolak',
        static fn (): array => Payment::payMany([], [
            'amount'       => 0,
            'method'       => 'tunai',
            'payment_date' => $today,
        ]),
        'minimal satu'
    );
    checkSame('Tidak ada tagihan berubah setelah validasi gagal', 'belum_bayar', (string) Invoice::find($inv1)['status']);
    checkSame(
        'Tidak ada pembayaran tersimpan setelah validasi gagal',
        0,
        (int) $pdo->query('SELECT COUNT(*) FROM payments WHERE student_id = ' . $s1)->fetchColumn()
    );

    // =================================================================
    echo "\n== F. Bayar hanya tagihan paket 1 ==\n";
    // =================================================================
    $pkg2BeforePay = StudentPackage::find($pkg2);
    $pkg3BeforePay = StudentPackage::find($pkg3);

    $pay1 = Payment::payMany([$inv1], [
        'amount'       => 800000,
        'method'       => 'transfer',
        'payment_date' => $today,
        'reference'    => 'TRX-T4TEST-1',
        'notes'        => 'Pelunasan paket 1 saja',
    ]);
    check('Pelunasan satu tagihan diterima', ($pay1['ok'] ?? false) === true, (string) ($pay1['error'] ?? ''));
    $payment1Id = (int) ($pay1['payment_id'] ?? 0);
    $payment1Number = (string) ($pay1['payment_number'] ?? '');
    checkSame('Satu tagihan dilunasi dalam pembayaran ini', 1, (int) ($pay1['invoice_count'] ?? 0));
    check('Nomor nota otomatis dibuat', str_starts_with($payment1Number, 'NOTA-'), $payment1Number);

    checkSame('Tagihan 1 menjadi lunas', 'lunas', (string) Invoice::find($inv1)['status']);
    checkSame('Tagihan 2 tetap belum dibayar', 'belum_bayar', (string) Invoice::find($inv2)['status']);
    checkSame('Tagihan 3 tetap belum dibayar', 'belum_bayar', (string) Invoice::find($inv3)['status']);

    $pkg2AfterPay = StudentPackage::find($pkg2);
    $pkg3AfterPay = StudentPackage::find($pkg3);
    checkSame('Pembayaran tidak mengubah status paket 2', (string) $pkg2BeforePay['status'], (string) $pkg2AfterPay['status']);
    checkSame('Pembayaran tidak mengubah hitungan paket 2', (int) $pkg2BeforePay['completed_meetings'], (int) $pkg2AfterPay['completed_meetings']);
    checkSame('Pembayaran tidak mengubah tanggal mulai paket 2', (string) $pkg2BeforePay['start_date'], (string) $pkg2AfterPay['start_date']);
    checkSame('Pembayaran tidak mengubah status paket 3', (string) $pkg3BeforePay['status'], (string) $pkg3AfterPay['status']);
    checkSame('Pembayaran tidak mengubah hitungan paket 3', (int) $pkg3BeforePay['completed_meetings'], (int) $pkg3AfterPay['completed_meetings']);

    $debtAfter1 = Invoice::debtSummary(['invoice_type' => Invoice::TYPE_PACKAGE, 'student_id' => $s1]);
    checkSame('Total dibayar menjadi Rp800.000', 800000.0, $debtAfter1['received_amount']);
    checkSame('Total utang turun menjadi Rp1.600.000', 1600000.0, $debtAfter1['debt_amount']);
    checkSame('Tagihan belum dibayar tersisa 2', 2, $debtAfter1['unpaid_count']);

    $alloc1 = $pdo->query(
        'SELECT COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount
         FROM payment_allocations WHERE payment_id = ' . $payment1Id
    )->fetch();
    checkSame('Pembayaran pertama memiliki 1 alokasi', 1, (int) ($alloc1['total'] ?? 0));
    checkSame('Nominal alokasi = total tagihan 1', 800000.0, (float) ($alloc1['amount'] ?? 0));
    checkSame(
        'Pembayaran menyimpan invoice_id NULL (alokasi terpisah)',
        null,
        $pdo->query('SELECT invoice_id FROM payments WHERE id = ' . $payment1Id)->fetchColumn()
    );

    expectFail(
        'Pembayaran ganda pada tagihan yang sudah lunas ditolak',
        static fn (): array => Payment::pay($inv1, [
            'amount'       => 800000,
            'method'       => 'tunai',
            'payment_date' => $today,
        ]),
        'lunas'
    );

    // =================================================================
    echo "\n== G. Bayar dua tagihan sekaligus ==\n";
    // =================================================================
    $paymentsBefore = (int) $pdo->query('SELECT COUNT(*) FROM payments WHERE student_id = ' . $s1)->fetchColumn();
    $allocationsBefore = (int) $pdo->query(
        'SELECT COUNT(*) FROM payment_allocations pa
         JOIN invoices i ON i.id = pa.invoice_id WHERE i.student_id = ' . $s1
    )->fetchColumn();

    // Urutan sengaja dibalik: sistem harus mengunci berurutan dan tetap konsisten.
    $pay2 = Payment::payMany([$inv3, $inv2], [
        'amount'       => 1600000,
        'method'       => 'qris',
        'payment_date' => $today,
        'reference'    => 'TRX-T4TEST-2',
        'notes'        => 'Pelunasan paket 2 dan 3 sekaligus',
    ]);
    check('Pembayaran dua tagihan diterima', ($pay2['ok'] ?? false) === true, (string) ($pay2['error'] ?? ''));
    $payment2Id = (int) ($pay2['payment_id'] ?? 0);
    checkSame('Dua tagihan dilunasi dalam satu pembayaran', 2, (int) ($pay2['invoice_count'] ?? 0));

    checkSame('Tagihan 2 menjadi lunas', 'lunas', (string) Invoice::find($inv2)['status']);
    checkSame('Tagihan 3 menjadi lunas', 'lunas', (string) Invoice::find($inv3)['status']);

    $payment2Row = $pdo->query('SELECT * FROM payments WHERE id = ' . $payment2Id)->fetch();
    checkSame('Total pembayaran = jumlah dua tagihan', 1600000.0, (float) ($payment2Row['amount'] ?? 0));
    checkSame('Pembayaran multi-tagihan menyimpan invoice_id NULL', null, $payment2Row['invoice_id'] ?? null);

    $alloc2 = $pdo->query(
        'SELECT COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount
         FROM payment_allocations WHERE payment_id = ' . $payment2Id
    )->fetch();
    checkSame('Pembayaran kedua memiliki 2 alokasi', 2, (int) ($alloc2['total'] ?? 0));
    checkSame('Total alokasi = total pembayaran', 1600000.0, (float) ($alloc2['amount'] ?? 0));

    $perInvoice = $pdo->query(
        'SELECT invoice_id, amount FROM payment_allocations
         WHERE payment_id = ' . $payment2Id . ' ORDER BY invoice_id'
    )->fetchAll();
    $perInvoiceAmounts = array_map(static fn (array $row): float => (float) $row['amount'], $perInvoice);
    checkSame('Setiap tagihan mendapat alokasi penuh Rp800.000', [800000.0, 800000.0], $perInvoiceAmounts);

    checkSame(
        'Satu pembayaran baru tercatat (bukan dua nota)',
        $paymentsBefore + 1,
        (int) $pdo->query('SELECT COUNT(*) FROM payments WHERE student_id = ' . $s1)->fetchColumn()
    );
    checkSame(
        'Dua alokasi baru tercatat',
        $allocationsBefore + 2,
        (int) $pdo->query(
            'SELECT COUNT(*) FROM payment_allocations pa
             JOIN invoices i ON i.id = pa.invoice_id WHERE i.student_id = ' . $s1
        )->fetchColumn()
    );

    $debtFinal = Invoice::debtSummary(['invoice_type' => Invoice::TYPE_PACKAGE, 'student_id' => $s1]);
    checkSame('Total tagihan tetap Rp2.400.000', 2400000.0, $debtFinal['billed_amount']);
    checkSame('Total dibayar menjadi Rp2.400.000', 2400000.0, $debtFinal['received_amount']);
    checkSame('Total utang menjadi Rp0', 0.0, $debtFinal['debt_amount']);
    checkSame('Tidak ada tagihan belum dibayar', 0, $debtFinal['unpaid_count']);

    $paymentList = Payment::all(['student_id' => $s1]);
    $multiRow = null;
    foreach ($paymentList as $paymentItem) {
        if ((int) $paymentItem['id'] === $payment2Id) {
            $multiRow = $paymentItem;
        }
    }
    check('Daftar pembayaran memuat pembayaran multi-tagihan', is_array($multiRow));
    checkSame('Daftar pembayaran menampilkan 2 tagihan', 2, (int) ($multiRow['invoice_count'] ?? 0));
    check(
        'Daftar pembayaran memuat kedua nomor invoice',
        str_contains((string) ($multiRow['invoice_number'] ?? ''), (string) $invoice2['invoice_number'])
        && str_contains((string) ($multiRow['invoice_number'] ?? ''), (string) Invoice::find($inv3)['invoice_number']),
        (string) ($multiRow['invoice_number'] ?? '')
    );

    // =================================================================
    echo "\n== H. Nota multi-tagihan & snapshot ==\n";
    // =================================================================
    checkSame('Nota tidak tersedia sebelum pembayaran berhasil', null, Payment::receipt(0));

    $receipt1 = Payment::receipt($payment1Id);
    check('Nota pembayaran tunggal tersedia', is_array($receipt1));
    checkSame('Nota tunggal memuat 1 tagihan', 1, count($receipt1['invoices'] ?? []));
    checkSame('Nota tunggal memuat total dibayar', 800000.0, (float) ($receipt1['amount'] ?? 0));
    checkSame('Nota tunggal memuat nomor tagihan', (string) $invoice1['invoice_number'], (string) ($receipt1['invoice_number'] ?? ''));

    $receipt2 = Payment::receipt($payment2Id);
    check('Nota multi-tagihan tersedia', is_array($receipt2));
    checkSame('Nota memuat 2 tagihan', 2, count($receipt2['invoices'] ?? []));
    $receiptAllocated = 0.0;
    $receiptNumbers = [];
    foreach ($receipt2['invoices'] as $receiptInvoice) {
        $receiptAllocated += (float) $receiptInvoice['allocated_amount'];
        $receiptNumbers[] = (string) $receiptInvoice['invoice_number'];
    }
    checkSame('Jumlah alokasi pada nota = total pembayaran', 1600000.0, $receiptAllocated);
    checkSame('Total pembayaran pada nota = Rp1.600.000', 1600000.0, (float) ($receipt2['amount'] ?? 0));
    check('Nota memuat kedua nomor tagihan', count(array_unique($receiptNumbers)) === 2);
    checkSame('Nota memuat nama siswa snapshot', $prefix . ' Siswa Utama', (string) ($receipt2['student_name'] ?? ''));
    checkSame('Nota memuat petugas snapshot', (string) $admin['name'], (string) ($receipt2['received_by_name'] ?? ''));

    // Snapshot tidak boleh berubah walau data induk diubah.
    $pdo->prepare('UPDATE students SET name = :name WHERE id = :id')
        ->execute([':name' => $prefix . ' Nama Diubah', ':id' => $s1]);
    $pdo->prepare('UPDATE users SET name = :name WHERE id = :id')
        ->execute([':name' => 'Petugas Diubah', ':id' => (int) $admin['id']]);
    $receiptAfter = Payment::receipt($payment2Id);
    checkSame(
        'Nama siswa pada nota tetap snapshot',
        $prefix . ' Siswa Utama',
        (string) ($receiptAfter['student_name'] ?? '')
    );
    checkSame(
        'Nama petugas pada nota tetap snapshot',
        (string) $admin['name'],
        (string) ($receiptAfter['received_by_name'] ?? '')
    );
    checkSame(
        'Nominal tagihan tetap snapshot setelah data induk berubah',
        800000.0,
        (float) Invoice::find($inv1)['total_amount']
    );
    $pdo->prepare('UPDATE students SET name = :name WHERE id = :id')
        ->execute([':name' => $prefix . ' Siswa Utama', ':id' => $s1]);
    $pdo->prepare('UPDATE users SET name = :name WHERE id = :id')
        ->execute([':name' => (string) $admin['name'], ':id' => (int) $admin['id']]);

    // =================================================================
    echo "\n== I. Riwayat tetap utuh & tidak terhitung ganda ==\n";
    // =================================================================
    $finalPackages = $pdo->query(
        'SELECT id, package_number, status, completed_meetings
         FROM student_packages WHERE student_id = ' . $s1 . ' ORDER BY package_number'
    )->fetchAll();
    checkSame('Riwayat paket tetap 3 baris', 3, count($finalPackages));
    checkSame('Paket 1 tetap selesai 8 pertemuan', ['selesai', 8], [
        (string) $finalPackages[0]['status'],
        (int) $finalPackages[0]['completed_meetings'],
    ]);
    checkSame('Paket 2 tetap selesai 8 pertemuan', ['selesai', 8], [
        (string) $finalPackages[1]['status'],
        (int) $finalPackages[1]['completed_meetings'],
    ]);
    checkSame('Paket 3 tetap aktif 1 pertemuan', ['aktif', 1], [
        (string) $finalPackages[2]['status'],
        (int) $finalPackages[2]['completed_meetings'],
    ]);

    checkSame(
        'Riwayat tagihan tetap 3 dan semuanya lunas',
        [3, 3],
        [
            (int) $pdo->query('SELECT COUNT(*) FROM invoices WHERE student_id = ' . $s1)->fetchColumn(),
            (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE student_id = " . $s1 . " AND status = 'lunas'")->fetchColumn(),
        ]
    );
    checkSame(
        'Riwayat pembayaran tetap 2 nota',
        2,
        (int) $pdo->query('SELECT COUNT(*) FROM payments WHERE student_id = ' . $s1)->fetchColumn()
    );
    $allocTotal = (float) $pdo->query(
        'SELECT COALESCE(SUM(pa.amount), 0) FROM payment_allocations pa
         JOIN invoices i ON i.id = pa.invoice_id WHERE i.student_id = ' . $s1
    )->fetchColumn();
    checkSame('Total alokasi historis = Rp2.400.000', 2400000.0, $allocTotal);

    $dupCount = (int) $pdo->query(
        'SELECT COUNT(*) FROM (
            SELECT class_meeting_id FROM student_package_meetings
            WHERE student_id = ' . $s1 . ' GROUP BY class_meeting_id HAVING COUNT(*) > 1
         ) x'
    )->fetchColumn();
    checkSame('Tidak ada pertemuan terhitung ganda', 0, $dupCount);

    // Database menolak alokasi kedua untuk tagihan yang sama.
    $duplicateAllocationBlocked = false;
    try {
        $pdo->prepare(
            'INSERT INTO payment_allocations (payment_id, invoice_id, amount)
             VALUES (:payment_id, :invoice_id, :amount)'
        )->execute([
            ':payment_id' => $payment2Id,
            ':invoice_id' => $inv1,
            ':amount'     => 800000,
        ]);
    } catch (PDOException $exception) {
        $duplicateAllocationBlocked = (string) $exception->getCode() === '23000';
    }
    check('Database menolak alokasi kedua untuk satu tagihan (UNIQUE)', $duplicateAllocationBlocked);
    checkSame(
        'Hanya satu alokasi berhasil per tagihan',
        1,
        (int) $pdo->query('SELECT COUNT(*) FROM payment_allocations WHERE invoice_id = ' . $inv1)->fetchColumn()
    );

    // =================================================================
    echo "\n== J. Status paket & laporan keuangan ==\n";
    // =================================================================
    checkSame(
        'Status paket hanya aktif, selesai, dibatalkan',
        ['aktif', 'selesai', 'dibatalkan'],
        StudentPackage::statusOptions()
    );
    $invalidStatus = (int) $pdo->query(
        "SELECT COUNT(*) FROM student_packages WHERE status NOT IN ('aktif','selesai','dibatalkan')"
    )->fetchColumn();
    checkSame('Tidak ada paket dengan status lama', 0, $invalidStatus);

    $finance = Report::finance();
    $issuedAmount = (float) $pdo->query(
        "SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE invoice_type = 'paket' AND status <> 'dibatalkan'"
    )->fetchColumn();
    $receivedViaAllocation = (float) $pdo->query(
        "SELECT COALESCE(SUM(pa.amount), 0)
         FROM payment_allocations pa
         JOIN payments pay ON pay.id = pa.payment_id AND pay.status = 'berhasil'
         JOIN invoices i ON i.id = pa.invoice_id
         WHERE i.invoice_type = 'paket' AND i.status <> 'dibatalkan'"
    )->fetchColumn();
    checkSame('Rekap tagihan diterbitkan sesuai database', $issuedAmount, (float) $finance['issued_amount']);
    checkSame('Rekap uang diterima memakai alokasi', $receivedViaAllocation, (float) $finance['received_amount']);

    // Pengecekan kesetaraan dibatasi pada data uji agar tidak terpengaruh
    // pembayaran nyata yang sudah ada di database produksi.
    $issuedForTest = (float) $pdo->query(
        "SELECT COALESCE(SUM(total_amount), 0) FROM invoices
          WHERE student_id = " . (int) $s1 . " AND invoice_type = 'paket' AND status <> 'dibatalkan'"
    )->fetchColumn();
    $receivedForTest = (float) $pdo->query(
        "SELECT COALESCE(SUM(pa.amount), 0)
           FROM payment_allocations pa
           JOIN payments pay ON pay.id = pa.payment_id AND pay.status = 'berhasil'
           JOIN invoices i ON i.id = pa.invoice_id
          WHERE i.student_id = " . (int) $s1 . " AND i.invoice_type = 'paket' AND i.status <> 'dibatalkan'"
    )->fetchColumn();
    checkSame('Tagihan data uji Tahap 4 berjumlah 2.400.000', 2400000.0, $issuedForTest);
    checkSame(
        'Setelah semua lunas, diterima = diterbitkan pada data uji',
        $issuedForTest,
        $receivedForTest
    );

    $reportInvoices = Report::invoices(['invoice_type' => 'paket', 'student_id' => $s1]);
    checkSame('Laporan tagihan siswa memuat 3 baris', 3, count($reportInvoices['rows']));
    checkSame('Ringkasan laporan memisahkan diterbitkan & diterima', true, isset(
        $reportInvoices['summary']['issued_amount'],
        $reportInvoices['summary']['received_amount']
    ));

    // =================================================================
    echo "\n== K. Integritas paket aktif ==\n";
    // =================================================================
    checkSame(
        'Satu paket aktif per keanggotaan (generated unique)',
        1,
        (int) $pdo->query(
            'SELECT COUNT(*) FROM student_packages WHERE active_package_key IS NOT NULL
             AND group_member_id = ' . $m1
        )->fetchColumn()
    );
    $activeBlocked = false;
    try {
        $pdo->prepare(
            "INSERT INTO student_packages
                (student_id, group_member_id, group_id, package_number, start_date, target_meetings,
                 completed_meetings, status, group_type, member_count)
             VALUES (:student_id, :member_id, :group_id, 99, :start_date, 8, 0, 'aktif', 'private', 1)"
        )->execute([
            ':student_id' => $s1,
            ':member_id'  => $m1,
            ':group_id'   => $privateGroup,
            ':start_date' => $today,
        ]);
    } catch (PDOException $exception) {
        $activeBlocked = (string) $exception->getCode() === '23000';
    }
    check('Database menolak paket aktif kedua untuk keanggotaan yang sama', $activeBlocked);

    // =================================================================
    echo "\n== L. Pembuatan paket massal per group ==\n";
    // =================================================================
    $bulkGroup = $createGroup($prefix . ' Group Massal', 'group', 7);
    $link($bulkGroup, $subject1, $teacher1);
    $sBulk1 = $insertStudent($prefix . ' Massal Satu');
    $sBulk2 = $insertStudent($prefix . ' Massal Dua');
    $mBulk1 = $addMember($bulkGroup, $sBulk1, $day(1));
    $mBulk2 = $addMember($bulkGroup, $sBulk2, $day(1));

    $overview = StudentPackage::groupMembershipOverview($bulkGroup);
    checkSame('Ringkasan group memuat 2 anggota aktif', 2, count($overview['members'] ?? []));
    checkSame(
        'Kedua anggota dinyatakan siap dibuatkan paket',
        2,
        count(array_filter(
            $overview['members'] ?? [],
            static fn (array $member): bool => (bool) $member['is_ready']
        ))
    );

    $bulk = StudentPackage::createForGroup($bulkGroup, $day(1), [$mBulk1, $mBulk2]);
    check('Pembuatan paket massal berhasil', ($bulk['ok'] ?? false) === true, (string) ($bulk['error'] ?? ''));
    checkSame('Dua paket dibuat dalam satu aksi', 2, (int) ($bulk['created'] ?? 0));
    checkSame('Dua tagihan langsung diterbitkan', 2, (int) ($bulk['invoices'] ?? 0));
    checkSame('Tidak ada siswa terlewat', 0, count($bulk['skipped'] ?? []));

    $bulkRows = $pdo->query(
        'SELECT * FROM student_packages WHERE group_id = ' . $bulkGroup . ' ORDER BY student_id ASC'
    )->fetchAll();
    checkSame('Database berisi tepat 2 paket untuk group massal', 2, count($bulkRows));
    checkSame(
        'Seluruh paket massal berstatus aktif',
        2,
        count(array_filter($bulkRows, static fn (array $row): bool => (string) $row['status'] === 'aktif'))
    );
    checkSame(
        'Setiap siswa memulai dari paket nomor 1',
        2,
        count(array_filter($bulkRows, static fn (array $row): bool => (int) $row['package_number'] === 1))
    );
    checkSame('Tarif group 2 siswa dipakai per siswa', 900000.0, (float) $bulkRows[0]['total_amount']);

    $bulkInvoiceCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM invoices WHERE group_id = " . $bulkGroup . " AND invoice_type = 'paket'"
    )->fetchColumn();
    checkSame('Tagihan tetap diterbitkan per siswa, bukan per group', 2, $bulkInvoiceCount);

    $bulkInvoice = $pdo->query(
        'SELECT * FROM invoices WHERE group_id = ' . $bulkGroup . ' ORDER BY id ASC LIMIT 1'
    )->fetch();
    checkSame('Snapshot nama siswa pada tagihan massal', $prefix . ' Massal Satu', (string) $bulkInvoice['student_name']);
    checkSame('Snapshot paket 1 — 8 pertemuan', 'Paket 1 — 8 pertemuan', (string) $bulkInvoice['period_label']);

    $bulkAgain = StudentPackage::createForGroup($bulkGroup, $day(1), [$mBulk1, $mBulk2]);
    check('Pemanggilan ulang tidak membuat paket ganda', ($bulkAgain['ok'] ?? true) === false);
    checkSame(
        'Jumlah paket tetap 2 setelah pemanggilan ulang',
        2,
        (int) $pdo->query('SELECT COUNT(*) FROM student_packages WHERE group_id = ' . $bulkGroup)->fetchColumn()
    );

    // =================================================================
    echo "\n== M. Massal melewati siswa yang sudah punya paket aktif ==\n";
    // =================================================================
    $partialGroup = $createGroup($prefix . ' Group Sebagian', 'group', 7);
    $link($partialGroup, $subject1, $teacher1);
    $sPartial1 = $insertStudent($prefix . ' Sebagian Satu');
    $sPartial2 = $insertStudent($prefix . ' Sebagian Dua');
    $mPartial1 = $addMember($partialGroup, $sPartial1, $day(1));
    $mPartial2 = $addMember($partialGroup, $sPartial2, $day(1));

    $singleFirst = StudentPackage::createForMembership($mPartial1, $day(1));
    check('Paket satu siswa dibuat lebih dulu', ($singleFirst['ok'] ?? false) === true);

    $partial = StudentPackage::createForGroup($partialGroup, $day(1), [$mPartial1, $mPartial2]);
    check('Massal tetap berjalan untuk siswa yang belum punya paket', ($partial['ok'] ?? false) === true);
    checkSame('Hanya satu paket baru dibuat', 1, (int) ($partial['created'] ?? 0));
    checkSame('Satu siswa dilewati', 1, count($partial['skipped'] ?? []));
    check(
        'Alasan pelewatan menyebut paket aktif',
        str_contains((string) ($partial['skipped'][0] ?? ''), 'paket aktif'),
        (string) ($partial['skipped'][0] ?? '')
    );

    // =================================================================
    echo "\n== N. Massal menghormati tanggal masuk & rollback diskon salah ==\n";
    // =================================================================
    $lateGroup = $createGroup($prefix . ' Group Tanggal', 'group', 7);
    $link($lateGroup, $subject1, $teacher1);
    $sLate1 = $insertStudent($prefix . ' Tanggal Satu');
    $sLate2 = $insertStudent($prefix . ' Tanggal Dua');
    $mLate1 = $addMember($lateGroup, $sLate1, $day(1));
    $mLate2 = $addMember($lateGroup, $sLate2, $day(5));

    $badBulk = StudentPackage::createForGroup($lateGroup, $day(1), [$mLate1, $mLate2], [
        'discount' => 9999999.0,
    ]);
    check('Diskon melebihi tarif menolak seluruh proses massal', ($badBulk['ok'] ?? true) === false);
    checkSame(
        'Tidak ada paket setengah jadi saat massal gagal',
        0,
        (int) $pdo->query('SELECT COUNT(*) FROM student_packages WHERE group_id = ' . $lateGroup)->fetchColumn()
    );

    $lateBulk = StudentPackage::createForGroup($lateGroup, $day(1), [$mLate1, $mLate2]);
    check('Massal dengan tanggal masuk berbeda berhasil', ($lateBulk['ok'] ?? false) === true);
    checkSame('Dua paket dibuat', 2, (int) ($lateBulk['created'] ?? 0));

    $lateRows = $pdo->query(
        'SELECT * FROM student_packages WHERE group_id = ' . $lateGroup . ' ORDER BY student_id ASC'
    )->fetchAll();
    checkSame('Siswa yang masuk lebih awal mulai pada tanggal group', $day(1), (string) $lateRows[0]['start_date']);
    checkSame('Siswa yang masuk belakangan mulai pada tanggal masuknya', $day(5), (string) $lateRows[1]['start_date']);

    // =================================================================
    echo "\n== H. Format nominal pembayaran ==\n";
    // =================================================================
    // Nilai dari form pembayaran dikirim JavaScript sebagai "250000.00".
    // Titik desimal tidak boleh dianggap pemisah ribuan.
    checkSame('Nominal desimal titik terbaca benar', 250000.0, parse_currency('250000.00'));
    checkSame('Nominal desimal koma terbaca benar', 250000.0, parse_currency('250000,00'));
    checkSame('Nominal ribuan titik tetap benar', 1500000.0, parse_currency('1.500.000'));
    checkSame('Nominal ribuan + desimal terbaca benar', 1500000.5, parse_currency('1.500.000,50'));
    checkSame('Nominal format Inggris terbaca benar', 1500000.0, parse_currency('1,500,000.00'));
    checkSame('Nominal polos tetap benar', 850000.0, parse_currency('850000'));
    checkSame('Nominal berawalan Rp terbaca benar', 250000.0, parse_currency('Rp250.000'));
    checkSame('Nominal kosong ditolak', null, parse_currency(''));
    checkSame('Nominal tanpa angka ditolak', null, parse_currency('abc'));
} catch (Throwable $exception) {
    echo "\nFATAL: " . get_class($exception) . ': ' . $exception->getMessage() . "\n";
    echo $exception->getFile() . ':' . $exception->getLine() . "\n";
    $fail++;
    $failures[] = 'FATAL ' . $exception->getMessage();
} finally {
    $cleanup();
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
