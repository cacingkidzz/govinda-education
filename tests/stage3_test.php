<?php
declare(strict_types=1);

/**
 * Uji mandiri aturan bisnis Tahap 3 (group, relasi mapel-guru, jadwal, keanggotaan).
 *
 * Jalankan dari root proyek:
 *   php tests/stage3_test.php
 *
 * Skrip ini membuat data uji berawalan T3TEST- lalu menghapusnya kembali.
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

function expectDomain(string $name, callable $callback, string $needle = ''): void
{
    try {
        $callback();
        check($name, false, 'tidak melempar DomainException');
    } catch (DomainException $exception) {
        $message = $exception->getMessage();
        check($name, $needle === '' || stripos($message, $needle) !== false, $message);
    } catch (Throwable $exception) {
        check($name, false, get_class($exception) . ': ' . $exception->getMessage());
    }
}

function expectMembershipFail(string $name, callable $callback, string $needle = ''): void
{
    $result = $callback();
    $ok = is_array($result) && ($result['ok'] ?? false) === false;
    check($name, $ok, is_array($result) ? (string) ($result['error'] ?? '') : 'hasil tidak valid');
    if ($ok && $needle !== '') {
        check($name . ' (pesan)', stripos((string) $result['error'], $needle) !== false, (string) $result['error']);
    }
}

/**
 * Jalankan callback dan kembalikan pesan DomainException ('' bila tidak ada).
 */
function domainMessage(callable $callback): string
{
    try {
        $callback();
    } catch (DomainException $exception) {
        return $exception->getMessage();
    } catch (Throwable $exception) {
        return get_class($exception) . ': ' . $exception->getMessage();
    }
    return '';
}

function bruteWeekdayCount(int $isoDay, int $year, int $month): int
{
    $days = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
    $count = 0;
    for ($d = 1; $d <= $days; $d++) {
        $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $d));
        if ((int) $date->format('N') === $isoDay) {
            $count++;
        }
    }
    return $count;
}

/**
 * Cari hari ISO dengan jumlah kemunculan tertentu pada bulan berjalan.
 *
 * @return array<int, int>
 */
function weekdaysWithCount(int $year, int $month, int $count): array
{
    $result = [];
    for ($day = 1; $day <= 7; $day++) {
        if (bruteWeekdayCount($day, $year, $month) === $count) {
            $result[] = $day;
        }
    }
    return $result;
}

$pdo = db();
$stamp = date('His');
$prefix = 'T3TEST-' . $stamp;
$created = [
    'groups'   => [],
    'teachers' => [],
    'subjects' => [],
    'students' => [],
];

$insertTeacher = static function (string $name, int $active = 1) use ($pdo, $prefix, &$created): int {
    $stmt = $pdo->prepare(
        'INSERT INTO teachers (code, name, gender, is_active) VALUES (:code, :name, :gender, :active)'
    );
    $stmt->execute([
        ':code'   => $prefix . '-G' . (count($created['teachers']) + 1),
        ':name'   => $name,
        ':gender' => 'L',
        ':active' => $active,
    ]);
    $id = (int) $pdo->lastInsertId();
    $created['teachers'][] = $id;
    return $id;
};

$insertSubject = static function (string $name, int $active = 1) use ($pdo, $prefix, &$created): int {
    $stmt = $pdo->prepare(
        'INSERT INTO subjects (code, name, is_active) VALUES (:code, :name, :active)'
    );
    $stmt->execute([
        ':code'   => $prefix . '-M' . (count($created['subjects']) + 1),
        ':name'   => $name,
        ':active' => $active,
    ]);
    $id = (int) $pdo->lastInsertId();
    $created['subjects'][] = $id;
    return $id;
};

$insertStudent = static function (string $name) use ($pdo, $prefix, &$created): int {
    $stmt = $pdo->prepare(
        'INSERT INTO students (code, name, gender, join_date, is_active)
         VALUES (:code, :name, :gender, :join_date, 1)'
    );
    $stmt->execute([
        ':code'      => $prefix . '-S' . (count($created['students']) + 1),
        ':name'      => $name,
        ':gender'    => 'P',
        ':join_date' => date('Y-m-d'),
    ]);
    $id = (int) $pdo->lastInsertId();
    $created['students'][] = $id;
    return $id;
};

$createGroup = static function (string $name, string $type, int $capacity, int $active = 1) use (&$created): int {
    $result = Group::create([
        'name'       => $name,
        'group_type' => $type,
        'capacity'   => $capacity,
        'is_active'  => $active,
    ]);
    $created['groups'][] = $result['id'];
    return $result['id'];
};

$link = static function (int $groupId, int $subjectId, int $teacherId): int {
    return GroupSubject::create([
        'group_id'   => $groupId,
        'subject_id' => $subjectId,
        'teacher_id' => $teacherId,
        'is_active'  => 1,
    ]);
};

$schedule = static function (
    int $relationId,
    int $day,
    string $start,
    string $end,
    int $year = 0,
    int $month = 0,
    string $room = '',
    int $active = 1
): int {
    return Schedule::create([
        'group_subject_id' => $relationId,
        'day_of_week'      => $day,
        'start_time'       => $start,
        'end_time'         => $end,
        'room'             => $room,
        'notes'            => '',
        'is_active'        => $active,
    ]);
};

$cleanup = static function () use ($pdo, &$created): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    if ($created['students'] !== []) {
        $ids = implode(',', array_map('intval', $created['students']));
        // Tahap 4: keanggotaan sekarang dapat memiliki paket, tagihan, dan
        // pembayaran. Hapus turunannya lebih dulu agar tidak ada baris yatim.
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
        $pdo->exec('DELETE FROM student_package_meetings WHERE class_meeting_id IN
                    (SELECT id FROM class_meetings WHERE group_id IN (' . $ids . '))');
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
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
};

try {
    echo "== Persiapan data uji ==\n";
    $teacher1 = $insertTeacher($prefix . ' Guru Satu');
    $teacher2 = $insertTeacher($prefix . ' Guru Dua');
    $teacherInactive = $insertTeacher($prefix . ' Guru Nonaktif', 0);
    $subject1 = $insertSubject($prefix . ' Mapel Satu');
    $subject2 = $insertSubject($prefix . ' Mapel Dua');
    $subject3 = $insertSubject($prefix . ' Mapel Tiga');
    $subjectInactive = $insertSubject($prefix . ' Mapel Nonaktif', 0);
    echo "  data guru/mapel dibuat\n";

    // -----------------------------------------------------------------
    echo "\n== A. Group private ==\n";
    // -----------------------------------------------------------------
    $privateId = $createGroup($prefix . ' Private', 'private', 5);
    $private = Group::find($privateId);
    check('Private selalu berkapasitas 1', (int) $private['capacity'] === 1, 'capacity=' . $private['capacity']);
    $privateRelation = $link($privateId, $subject1, $teacher1);
    check('Relasi mapel-guru private dibuat', $privateRelation > 0);

    $studentPrivate1 = $insertStudent($prefix . ' Siswa Private 1');
    $studentPrivate2 = $insertStudent($prefix . ' Siswa Private 2');
    $r = Membership::add($privateId, $studentPrivate1, date('Y-m-d'));
    check('Private menerima siswa pertama', ($r['ok'] ?? false) === true, (string) ($r['error'] ?? ''));
    expectMembershipFail(
        'Private menolak siswa kedua',
        static fn (): array => Membership::add($privateId, $studentPrivate2, date('Y-m-d')),
        'private'
    );

    // -----------------------------------------------------------------
    echo "\n== B. Group reguler & kapasitas ==\n";
    // -----------------------------------------------------------------
    $regularId = $createGroup($prefix . ' Reguler', 'group', 7);
    $regularRelation1 = $link($regularId, $subject1, $teacher1);
    check('Relasi mapel pertama pada group reguler', $regularRelation1 > 0);

    $regularStudents = [];
    for ($i = 1; $i <= 7; $i++) {
        $studentId = $insertStudent($prefix . ' Siswa Reguler ' . $i);
        $regularStudents[] = $studentId;
        $res = Membership::add($regularId, $studentId, date('Y-m-d'));
        check('Reguler menerima anggota ke-' . $i, ($res['ok'] ?? false) === true, (string) ($res['error'] ?? ''));
    }
    $eighth = $insertStudent($prefix . ' Siswa Reguler 8');
    expectMembershipFail(
        'Reguler menolak anggota ke-8',
        static fn (): array => Membership::add($regularId, $eighth, date('Y-m-d')),
        '7'
    );

    $smallId = $createGroup($prefix . ' Reguler Kecil', 'group', 2);
    $link($smallId, $subject1, $teacher1);
    $small1 = $insertStudent($prefix . ' Siswa Kecil 1');
    $small2 = $insertStudent($prefix . ' Siswa Kecil 2');
    $small3 = $insertStudent($prefix . ' Siswa Kecil 3');
    Membership::add($smallId, $small1, date('Y-m-d'));
    $res = Membership::add($smallId, $small2, date('Y-m-d'));
    check('Group kapasitas 2 menerima 2 anggota', ($res['ok'] ?? false) === true, (string) ($res['error'] ?? ''));
    expectMembershipFail(
        'Group kapasitas 2 menolak anggota ke-3',
        static fn (): array => Membership::add($smallId, $small3, date('Y-m-d')),
        'penuh'
    );

    // -----------------------------------------------------------------
    echo "\n== C. Banyak mapel dengan guru berbeda ==\n";
    // -----------------------------------------------------------------
    $multiId = $createGroup($prefix . ' Multimapel', 'group', 6);
    $multiRelation1 = $link($multiId, $subject1, $teacher1);
    $multiRelation2 = $link($multiId, $subject2, $teacher2);
    check('Mapel kedua dengan guru berbeda diterima', $multiRelation2 > 0);

    expectDomain(
        'Mapel duplikat dalam satu group ditolak',
        static fn (): int => $link($multiId, $subject1, $teacher2),
        'sudah terdaftar'
    );
    expectDomain(
        'Mapel nonaktif tidak dapat dihubungkan',
        static fn (): int => $link($multiId, $subjectInactive, $teacher1),
        'nonaktif'
    );
    expectDomain(
        'Guru nonaktif tidak dapat dihubungkan',
        static fn (): int => $link($multiId, $subject3, $teacherInactive),
        'nonaktif'
    );
    $multiRelations = GroupSubject::forGroup($multiId, false);
    check('Group memiliki dua relasi aktif', count($multiRelations) === 2);
    $names = array_column($multiRelations, 'teacher_name');
    check('Dua guru berbeda tercatat', count(array_unique($names)) === 2, implode(', ', $names));

    // -----------------------------------------------------------------
    echo "\n== D. Jadwal banyak hari & bentrok ==\n";
    // -----------------------------------------------------------------
    // Jadwal tidak lagi dibatasi bulan; tahun/bulan pada helper hanya
    // dipertahankan agar pemanggilan lama tetap kompatibel.
    $now = ['year' => 2026, 'month' => 1];
    $scheduleGroupId = $createGroup($prefix . ' Jadwal', 'group', 5);
    $schRelation = $link($scheduleGroupId, $subject1, $teacher1);
    $sMon = $schedule($schRelation, 1, '08:00', '09:00', $now['year'], $now['month'], 'Ruang Uji A');
    $sWed = $schedule($schRelation, 3, '08:00', '09:00', $now['year'], $now['month'], 'Ruang Uji B');
    check('Satu mapel boleh punya jadwal Senin', $sMon > 0);
    check('Satu mapel boleh punya jadwal Rabu', $sWed > 0);

    expectDomain(
        'Slot hari + jam mulai duplikat ditolak',
        static fn (): int => $schedule($schRelation, 1, '08:00', '09:00', $now['year'], $now['month']),
        'jam mulai yang sama'
    );

    // Guru bentrok: guru1 mengajar group lain pada Senin 08:30–09:30.
    $teacherConflictGroup = $createGroup($prefix . ' Bentrok Guru', 'group', 5);
    $teacherConflictRelation = $link($teacherConflictGroup, $subject2, $teacher1);
    expectDomain(
        'Bentrok guru terdeteksi',
        static fn (): int => $schedule(
            $teacherConflictRelation, 1, '08:30', '09:30', $now['year'], $now['month'], 'Ruang Uji C'
        ),
        'Guru'
    );

    // Group bentrok: group jadwal, mapel kedua guru2, Senin 08:30–09:30.
    $groupConflictRelation = $link($scheduleGroupId, $subject2, $teacher2);
    expectDomain(
        'Bentrok group terdeteksi',
        static fn (): int => $schedule(
            $groupConflictRelation, 1, '08:30', '09:30', $now['year'], $now['month'], 'Ruang Uji D'
        ),
        'Group'
    );

    // Ruangan bentrok: group lain, guru2, ruangan sama.
    $roomConflictGroup = $createGroup($prefix . ' Bentrok Ruang', 'group', 5);
    $roomConflictRelation = $link($roomConflictGroup, $subject2, $teacher2);
    expectDomain(
        'Bentrok ruangan terdeteksi',
        static fn (): int => $schedule(
            $roomConflictRelation, 1, '08:30', '09:30', $now['year'], $now['month'], 'Ruang Uji A'
        ),
        'Ruangan'
    );

    // Jadwal nonaktif diabaikan.
    $inactiveScheduleGroup = $createGroup($prefix . ' Abaikan Nonaktif', 'group', 5);
    $inactiveRelation = $link($inactiveScheduleGroup, $subject1, $teacher2);
    $inactiveSchedule = $schedule(
        $inactiveRelation, 1, '08:30', '09:30', $now['year'], $now['month'], 'Ruang Uji E'
    );
    Schedule::setActive($inactiveSchedule, false);
    $afterInactive = $schedule($roomConflictRelation, 1, '08:30', '09:30', $now['year'], $now['month'], 'Ruang Uji E');
    check('Jadwal nonaktif diabaikan saat cek bentrok', $afterInactive > 0);
    Schedule::delete($inactiveSchedule);

    // Jadwal baru yang langsung disimpan nonaktif tidak perlu lolos cek bentrok,
    // tetapi slot (relasi, hari, jam mulai) tetap harus unik.
    $draftGroup = $createGroup($prefix . ' Draf Nonaktif', 'group', 5);
    $draftRelation = $link($draftGroup, $subject1, $teacher1);
    $draft = $schedule(
        $draftRelation, 1, '08:30', '09:30', $now['year'], $now['month'], 'Ruang Uji A', 0
    );
    check('Jadwal nonaktif baru tidak diblokir cek bentrok', $draft > 0);

    // Relasi nonaktif diabaikan.
    $inactiveRelGroup = $createGroup($prefix . ' Abaikan Relasi', 'group', 5);
    $inactiveRelRelation = $link($inactiveRelGroup, $subject1, $teacher1);
    $schedule($inactiveRelRelation, 2, '10:00', '11:00', $now['year'], $now['month'], 'Ruang Uji F');
    GroupSubject::setActive($inactiveRelRelation, false);
    $freshConflictGroup = $createGroup($prefix . ' Setelah Relasi Nonaktif', 'group', 5);
    $freshConflictRelation = $link($freshConflictGroup, $subject2, $teacher2);
    $ok = $schedule($freshConflictRelation, 2, '10:00', '11:00', $now['year'], $now['month'], 'Ruang Uji F');
    check('Relasi nonaktif diabaikan saat cek bentrok', $ok > 0);

    // Edit mengecualikan diri sendiri.
    Schedule::update($ok, [
        'group_subject_id' => $freshConflictRelation,
        'day_of_week'      => 2,
        'start_time'       => '10:00',
        'end_time'         => '11:30',
        'room'             => 'Ruang Uji F',
        'notes'            => '',
        'is_active'        => 1,
    ]);
    check('Edit jadwal tidak dianggap bentrok dengan dirinya sendiri', true);

    // Ubah guru pada relasi yang punya jadwal → bentrok diperiksa ulang.
    expectDomain(
        'Perubahan guru pada relasi dicek ulang terhadap jadwal aktif',
        static function () use ($inactiveRelRelation, $subject1, $teacher2): void {
            GroupSubject::update($inactiveRelRelation, [
                'subject_id'  => $subject1,
                'teacher_id'  => $teacher2,
                'is_active'   => 1,
                'description' => '',
            ]);
        },
        'Guru'
    );

    // -----------------------------------------------------------------
    echo "\n== E. Jadwal fleksibel tanpa batas bulanan ==\n";
    // -----------------------------------------------------------------
    check(
        'Aturan batas pertemuan bulanan sudah dihapus dari kelas jadwal',
        !method_exists(Schedule::class, 'monthlyGroupCount')
        && !method_exists(Schedule::class, 'assertGroupMonthlyLimit')
        && !method_exists(Schedule::class, 'monthlySummaryForGroup')
        && !method_exists(Schedule::class, 'groupOverflowMonths')
    );

    // E1. Banyak jadwal dalam satu minggu tetap diterima (total > 8 per bulan).
    $flexGroup = $createGroup($prefix . ' Grup Fleksibel', 'group', 7);
    $flexTeacher = $insertTeacher($prefix . ' Guru Fleksibel');
    $flexRelation = $link($flexGroup, $subject1, $flexTeacher);
    $flexScheduleIds = [];
    for ($day = 1; $day <= 6; $day++) {
        $flexScheduleIds[] = $schedule($flexRelation, $day, '07:00', '08:00', 2026, 1);
    }
    check('Enam jadwal mingguan pada satu mapel diterima', count($flexScheduleIds) === 6);
    check(
        'Jumlah jadwal aktif tidak lagi dibatasi hitungan bulan',
        count(Schedule::forGroupSubject($flexRelation, true)) === 6
    );

    // E2. Mata pelajaran lain pada group yang sama bebas menambah jadwal.
    $flexTeacher2 = $insertTeacher($prefix . ' Guru Fleksibel Dua');
    $flexRelation2 = $link($flexGroup, $subject2, $flexTeacher2);
    $schedule($flexRelation2, 1, '09:00', '10:00', 2026, 1);
    $schedule($flexRelation2, 3, '09:00', '10:00', 2026, 1);
    check('Mapel lain bebas menambah jadwal tanpa batas grup', count(Schedule::forGroup($flexGroup, true)) === 8);

    // E3. Mengaktifkan jadwal nonaktif tidak ditolak karena hitungan bulanan.
    $flexInactive = $schedule($flexRelation2, 5, '09:00', '10:00', 2026, 1, '', 0);
    Schedule::setActive($flexInactive, true);
    check(
        'Mengaktifkan jadwal nonaktif tidak diblokir batas bulanan',
        (int) Schedule::find($flexInactive)['is_active'] === 1
    );

    // E4. Jadwal boleh dipindah hari kapan saja mengikuti situasi group.
    Schedule::update($flexInactive, [
        'group_subject_id' => $flexRelation2,
        'day_of_week'      => 6,
        'start_time'       => '09:30',
        'end_time'         => '10:30',
        'room'             => '',
        'notes'            => '',
        'is_active'        => 1,
    ]);
    check(
        'Jadwal dapat dipindah hari tanpa validasi bulan',
        (int) Schedule::find($flexInactive)['day_of_week'] === 6
    );

    // E5. Jadwal nonaktif yang belum punya riwayat pertemuan boleh dihapus.
    Schedule::setActive($flexInactive, false);
    Schedule::delete($flexInactive);
    check('Jadwal nonaktif tanpa riwayat dapat dihapus', Schedule::find($flexInactive) === null);

    // -----------------------------------------------------------------
    echo "\n== F. Jurnal pertemuan per group ==\n";
    // -----------------------------------------------------------------
    $journalGroup = $createGroup($prefix . ' Jurnal', 'group', 5);
    $journalRelation = $link($journalGroup, $subject1, $teacher1);
    $journalNotes = 'Materi aljabar dan latihan soal.';

    $journalRecord = Meeting::record([
        'schedule_id'      => 0,
        'group_subject_id' => $journalRelation,
        'meeting_date'     => '2026-03-02',
        'start_time'       => '08:00',
        'end_time'         => '09:00',
        'status'           => Meeting::STATUS_DONE,
        'notes'            => $journalNotes,
    ]);
    check(
        'Pertemuan group tercatat',
        ($journalRecord['ok'] ?? false) === true,
        (string) ($journalRecord['error'] ?? '')
    );

    $journalRows = Meeting::recorded(['group_id' => $journalGroup]);
    check('Jurnal group memuat satu pertemuan', count($journalRows) === 1);
    check('Catatan pertemuan tersimpan apa adanya', ($journalRows[0]['notes'] ?? '') === $journalNotes);
    check('Jurnal menyediakan nama pencatat', array_key_exists('recorded_by_name', $journalRows[0]));
    check(
        'Pertemuan melekat pada relasi mapel group',
        (int) $journalRows[0]['group_subject_id'] === $journalRelation
    );

    $duplicate = Meeting::record([
        'schedule_id'      => 0,
        'group_subject_id' => $journalRelation,
        'meeting_date'     => '2026-03-02',
        'start_time'       => '08:00',
        'end_time'         => '09:00',
        'status'           => Meeting::STATUS_DONE,
        'notes'            => 'Duplikat.',
    ]);
    check('Pertemuan ganda pada relasi, tanggal, dan jam sama ditolak', ($duplicate['ok'] ?? false) === false);

    $journalScheduled = Meeting::record([
        'schedule_id'      => 0,
        'group_subject_id' => $journalRelation,
        'meeting_date'     => '2026-03-09',
        'start_time'       => '08:00',
        'end_time'         => '09:00',
        'status'           => Meeting::STATUS_SCHEDULED,
        'notes'            => 'Rencana pertemuan berikutnya.',
    ]);
    check(
        'Pertemuan terjadwal dapat dicatat tanpa hitungan paket',
        ($journalScheduled['ok'] ?? false) === true && (int) $journalScheduled['counted'] === 0,
        (string) ($journalScheduled['error'] ?? '')
    );

    $journalCancel = Meeting::record([
        'meeting_id'       => (int) $journalRecord['meeting_id'],
        'schedule_id'      => 0,
        'group_subject_id' => $journalRelation,
        'meeting_date'     => '2026-03-02',
        'start_time'       => '08:00',
        'end_time'         => '09:00',
        'status'           => Meeting::STATUS_CANCELLED,
        'notes'            => 'Dibatalkan karena guru berhalangan.',
        'reason'           => 'Guru berhalangan hadir.',
    ]);
    check(
        'Pertemuan terlaksana dapat dibatalkan dengan catatan',
        ($journalCancel['ok'] ?? false) === true,
        (string) ($journalCancel['error'] ?? '')
    );
    check(
        'Status pembatalan tersimpan pada jurnal',
        (string) Meeting::find((int) $journalRecord['meeting_id'])['status'] === Meeting::STATUS_CANCELLED
    );

    $journalRowsAfter = Meeting::recorded(['group_id' => $journalGroup]);
    check('Jurnal group memuat seluruh status pertemuan', count($journalRowsAfter) === 2);
    // -----------------------------------------------------------------
    echo "\n== G. Keanggotaan, perpindahan, riwayat ==\n";
    // -----------------------------------------------------------------
    $fromId = $createGroup($prefix . ' Asal', 'group', 5);
    $toId = $createGroup($prefix . ' Tujuan', 'group', 5);
    $link($fromId, $subject1, $teacher1);
    $link($toId, $subject2, $teacher2);
    $transferStudent = $insertStudent($prefix . ' Siswa Pindah');
    $add = Membership::add($fromId, $transferStudent, '2026-01-05');
    check('Siswa masuk group asal', ($add['ok'] ?? false) === true, (string) ($add['error'] ?? ''));

    $transfer = Membership::transfer($transferStudent, $toId, '2026-02-10', 'Penyesuaian jadwal');
    check('Perpindahan berhasil', ($transfer['ok'] ?? false) === true, (string) ($transfer['error'] ?? ''));

    $history = Membership::historyForStudent($transferStudent);
    $activeHistory = array_values(array_filter(
        $history,
        static fn (array $row): bool => $row['end_date'] === null
    ));
    check('Keanggotaan aktif hanya satu setelah pindah', count($activeHistory) === 1);
    check('Keanggotaan aktif berada di group tujuan', (int) $activeHistory[0]['group_id'] === $toId);
    check('Tanggal mulai keanggotaan baru = tanggal pindah', (string) $activeHistory[0]['start_date'] === '2026-02-10');

    $closed = array_values(array_filter(
        Membership::historyForStudent($transferStudent),
        static fn (array $row): bool => $row['end_date'] !== null
    ));
    check(
        'Keanggotaan lama ditutup, bukan dihapus',
        count($closed) === 1 && (string) $closed[0]['end_date'] === '2026-02-10'
        && (int) $closed[0]['group_id'] === $fromId
    );

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM student_group_history WHERE student_id = :id');
    $stmt->execute([':id' => $transferStudent]);
    check('Riwayat perpindahan tercatat satu baris', (int) $stmt->fetchColumn() === 1);

    // Rollback: pindah ke group penuh tidak boleh mengubah apa pun.
    $fullId = $createGroup($prefix . ' Penuh', 'group', 2);
    $link($fullId, $subject1, $teacher1);
    $full1 = $insertStudent($prefix . ' Siswa Penuh 1');
    $full2 = $insertStudent($prefix . ' Siswa Penuh 2');
    Membership::add($fullId, $full1, date('Y-m-d'));
    Membership::add($fullId, $full2, date('Y-m-d'));

    $beforeActive = Membership::activeForStudent($transferStudent);
    $historyBefore = (int) $pdo->query(
        'SELECT COUNT(*) FROM student_group_history WHERE student_id = ' . $transferStudent
    )->fetchColumn();
    expectMembershipFail(
        'Pindah ke group penuh ditolak',
        static fn (): array => Membership::transfer($transferStudent, $fullId, '2026-03-01', 'Coba pindah'),
        'penuh'
    );
    $afterActive = Membership::activeForStudent($transferStudent);
    $historyAfter = (int) $pdo->query(
        'SELECT COUNT(*) FROM student_group_history WHERE student_id = ' . $transferStudent
    )->fetchColumn();
    check(
        'Rollback perpindahan: keanggotaan aktif tidak berubah',
        $afterActive !== null && (int) $afterActive['group_id'] === (int) $beforeActive['group_id']
    );
    check('Rollback perpindahan: tidak ada riwayat baru', $historyBefore === $historyAfter);

    $inactiveGroup = $createGroup($prefix . ' Nonaktif Tujuan', 'group', 5);
    $link($inactiveGroup, $subject1, $teacher1);
    Group::setActive($inactiveGroup, false);
    expectMembershipFail(
        'Pindah ke group nonaktif ditolak',
        static fn (): array => Membership::transfer($transferStudent, $inactiveGroup, '2026-03-01', 'Coba pindah'),
        'nonaktif'
    );

    $noRelationGroup = $createGroup($prefix . ' Tanpa Mapel', 'group', 5);
    expectMembershipFail(
        'Pindah ke group tanpa relasi mapel ditolak',
        static fn (): array => Membership::transfer($transferStudent, $noRelationGroup, '2026-03-01', 'Coba pindah'),
        'mata pelajaran'
    );

    expectMembershipFail(
        'Tanggal pindah sebelum tanggal masuk ditolak',
        static fn (): array => Membership::transfer($transferStudent, $fromId, '2025-01-01', 'Tanggal salah'),
        'sebelum'
    );

    // -----------------------------------------------------------------
    echo "\n== H. Validasi jadwal ==\n";
    // -----------------------------------------------------------------
    check('Jam tidak valid ditolak', Schedule::normalizeTime('25:00') === null);
    check('Jam valid dinormalisasi', Schedule::normalizeTime('08:15') === '08:15:00');
    $s = Schedule::find($sMon);
    check('Jadwal tersimpan dengan relasi mapel-guru', (int) $s['group_subject_id'] === $schRelation);
    check('Jadwal menyertakan nama mapel dan guru', $s['subject_name'] !== '' && $s['teacher_name'] !== '');

    // -----------------------------------------------------------------
    echo "\n== I. Migrasi data lama ==\n";
    // -----------------------------------------------------------------
    $legacy = $pdo->query(
        "SELECT g.id AS group_id, g.code, g.name,
                (SELECT COUNT(*) FROM group_subjects gs WHERE gs.group_id = g.id) AS rel_count,
                (SELECT COUNT(*) FROM group_schedules gsch
                   JOIN group_subjects gs ON gs.id = gsch.group_subject_id
                  WHERE gs.group_id = g.id) AS schedule_count
         FROM class_groups g
         WHERE g.code = 'GRP-MIG-001' LIMIT 1"
    )->fetch();
    if ($legacy === false) {
        echo "  SKIP  Data legacy GRP-MIG-001 tidak ada (sudah dibersihkan)\n";
    } else {
        check('Group legacy tetap ada', $legacy['name'] === 'Grup Migrasi');
        check('Relasi mapel legacy dipindahkan', (int) $legacy['rel_count'] >= 1);
        check('Jadwal legacy tetap terhubung', (int) $legacy['schedule_count'] === 2);
    }
} catch (Throwable $exception) {
    echo "\nFATAL: " . get_class($exception) . ': ' . $exception->getMessage() . "\n";
    echo $exception->getTraceAsString() . "\n";
    $fail++;
    $failures[] = 'FATAL: ' . $exception->getMessage();
} finally {
    $cleanup();
}

echo "\n============================================\n";
echo "LULUS: {$pass}   GAGAL: {$fail}\n";
if ($failures !== []) {
    echo "Rincian kegagalan:\n";
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
}
exit($fail === 0 ? 0 : 1);
