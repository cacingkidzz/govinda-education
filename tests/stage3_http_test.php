<?php
declare(strict_types=1);

/**
 * Uji HTTP Tahap 3: role, CSRF, dan alur form (group, relasi mapel, jadwal).
 *
 * Prasyarat: server pengembangan berjalan di http://127.0.0.1:8080
 *   php -S 127.0.0.1:8080
 * Jalankan:
 *   php tests/stage3_http_test.php
 */

const BASE = 'http://127.0.0.1:8080';
const COOKIE_ADMIN = __DIR__ . '/.cookie-admin.txt';
const COOKIE_PETUGAS = __DIR__ . '/.cookie-petugas.txt';

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
 * @return array{status:int,body:string,location:string}
 */
function http(string $method, string $path, array $fields = [], ?string $cookieJar = null, bool $follow = false): array
{
    $ch = curl_init(BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 20,
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
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    curl_close($ch);

    $location = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m) === 1) {
        $location = trim($m[1]);
    }
    return ['status' => $status, 'body' => $body, 'location' => $location];
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

function dbp(): PDO
{
    return db();
}

require_once __DIR__ . '/../includes/bootstrap.php';

// Bersihkan sisa uji HTTP sebelumnya.
$pdo = dbp();
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec("DELETE FROM student_package_meetings WHERE student_package_id IN
            (SELECT id FROM student_packages WHERE group_id IN
              (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%'))");
$pdo->exec("DELETE FROM payment_allocations WHERE invoice_id IN
            (SELECT id FROM invoices WHERE group_id IN
              (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%'))");
$pdo->exec("DELETE FROM payments WHERE invoice_id IN
            (SELECT id FROM invoices WHERE group_id IN
              (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%'))");
$pdo->exec("DELETE FROM invoices WHERE group_id IN
            (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%')");
$pdo->exec("DELETE FROM student_packages WHERE group_id IN
            (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%')");
$pdo->exec("DELETE FROM student_package_meetings WHERE class_meeting_id IN
            (SELECT id FROM class_meetings WHERE group_id IN
              (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%'))");
$pdo->exec("DELETE FROM class_meetings WHERE group_id IN
            (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%')");
$pdo->exec("DELETE FROM group_schedules WHERE group_subject_id IN
            (SELECT id FROM group_subjects WHERE group_id IN
              (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%'))");
$pdo->exec("DELETE FROM group_subjects WHERE group_id IN
            (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%')");
$pdo->exec("DELETE FROM class_groups WHERE name LIKE 'T3HTTP%'");
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$createdSubjectIds = [];
$createdTeacherIds = [];
$subjectId = (int) $pdo->query('SELECT id FROM subjects WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
if ($subjectId === 0) {
    $pdo->prepare('INSERT INTO subjects (code, name, is_active) VALUES (:code, :name, 1)')
        ->execute([':code' => 'T3HTTP-MAPEL', ':name' => 'T3HTTP Mapel']);
    $subjectId = (int) $pdo->lastInsertId();
    $createdSubjectIds[] = $subjectId;
}
$subjectId2 = (int) $pdo->query(
    'SELECT id FROM subjects WHERE is_active = 1 AND id <> ' . $subjectId . ' ORDER BY id LIMIT 1'
)->fetchColumn();
if ($subjectId2 === 0) {
    $pdo->prepare('INSERT INTO subjects (code, name, is_active) VALUES (:code, :name, 1)')
        ->execute([':code' => 'T3HTTP-MAPEL-2', ':name' => 'T3HTTP Mapel Dua']);
    $subjectId2 = (int) $pdo->lastInsertId();
    $createdSubjectIds[] = $subjectId2;
}
$teacherId = (int) $pdo->query('SELECT id FROM teachers WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
if ($teacherId === 0) {
    $pdo->prepare('INSERT INTO teachers (code, name, gender, is_active) VALUES (:code, :name, :gender, 1)')
        ->execute([':code' => 'T3HTTP-GURU', ':name' => 'T3HTTP Guru', ':gender' => 'L']);
    $teacherId = (int) $pdo->lastInsertId();
    $createdTeacherIds[] = $teacherId;
}
$teacherId2 = (int) $pdo->query(
    'SELECT id FROM teachers WHERE is_active = 1 AND id <> ' . $teacherId . ' ORDER BY id LIMIT 1'
)->fetchColumn();
if ($teacherId2 === 0) {
    $pdo->prepare('INSERT INTO teachers (code, name, gender, is_active) VALUES (:code, :name, :gender, 1)')
        ->execute([':code' => 'T3HTTP-GURU-2', ':name' => 'T3HTTP Guru Dua', ':gender' => 'P']);
    $teacherId2 = (int) $pdo->lastInsertId();
    $createdTeacherIds[] = $teacherId2;
}

try {
    echo "== A. Autentikasi & otorisasi ==\n";
    $anon = http('GET', '/admin/group/index.php');
    check('Halaman group menolak pengunjung tanpa login', $anon['status'] === 302
        && strpos($anon['location'], 'login.php') !== false, 'status=' . $anon['status']);

    check('Login admin berhasil', login('admin', 'Admin@2026', COOKIE_ADMIN));
    check('Login petugas berhasil', login('petugas', 'Petugas@2026', COOKIE_PETUGAS));

    $petugasPage = http('GET', '/admin/group/index.php', [], COOKIE_PETUGAS);
    check('Petugas boleh melihat daftar group', $petugasPage['status'] === 200);

    $formPage = http('GET', '/admin/group/tambah.php', [], COOKIE_PETUGAS);
    check('Petugas ditolak saat membuka form tambah group', $formPage['status'] === 403, 'status=' . $formPage['status']);

    $token = csrf_from(http('GET', '/admin/group/index.php', [], COOKIE_PETUGAS)['body']);
    $petugasPost = http('POST', '/admin/group/proses.php', [
        '_csrf'      => $token,
        'action'     => 'create',
        'name'       => 'T3HTTP Petugas',
        'group_type' => 'group',
        'capacity'   => '4',
        'description'=> '',
    ], COOKIE_PETUGAS);
    check('Petugas tidak boleh menyimpan group', $petugasPost['status'] === 403, 'status=' . $petugasPost['status']);

    $badCsrf = http('POST', '/admin/group/proses.php', [
        '_csrf'      => 'token-palsu',
        'action'     => 'create',
        'name'       => 'T3HTTP CSRF',
        'group_type' => 'group',
        'capacity'   => '4',
        'description'=> '',
    ], COOKIE_ADMIN);
    check('POST group dengan CSRF salah ditolak 419', $badCsrf['status'] === 419, 'status=' . $badCsrf['status']);

    $badCsrfMapel = http('POST', '/admin/group/mapel-proses.php', [
        '_csrf'      => 'token-palsu',
        'action'     => 'create',
        'group_id'   => '1',
        'subject_id' => '1',
        'teacher_id' => '1',
    ], COOKIE_ADMIN);
    check('POST relasi mapel dengan CSRF salah ditolak 419', $badCsrfMapel['status'] === 419, 'status=' . $badCsrfMapel['status']);

    $getProses = http('GET', '/admin/jadwal/proses.php', [], COOKIE_ADMIN);
    check('Endpoint proses hanya menerima POST', $getProses['status'] === 405, 'status=' . $getProses['status']);

    echo "\n== B. Validasi form group ==\n";
    $form = http('GET', '/admin/group/tambah.php', [], COOKIE_ADMIN);
    $token = csrf_from($form['body']);
    $invalid = http('POST', '/admin/group/proses.php', [
        '_csrf'      => $token,
        'action'     => 'create',
        'name'       => 'T3HTTP Kapasitas Salah',
        'group_type' => 'group',
        'capacity'   => '8',
        'description'=> '',
    ], COOKIE_ADMIN);
    check('Kapasitas 8 mengembalikan form (302)', $invalid['status'] === 302, 'status=' . $invalid['status']);
    $reload = http('GET', '/admin/group/tambah.php', [], COOKIE_ADMIN);
    check(
        'Pesan validasi kapasitas tampil di server',
        strpos($reload['body'], 'Kapasitas group belajar harus antara 2 sampai 7 siswa') !== false
    );
    check(
        'Nama group tidak tersimpan saat validasi gagal',
        (int) dbp()->query("SELECT COUNT(*) FROM class_groups WHERE name = 'T3HTTP Kapasitas Salah'")->fetchColumn() === 0
    );

    echo "\n== C. Alur sukses group -> relasi -> jadwal ==\n";
    $form = http('GET', '/admin/group/tambah.php', [], COOKIE_ADMIN);
    $token = csrf_from($form['body']);
    $create = http('POST', '/admin/group/proses.php', [
        '_csrf'      => $token,
        'action'     => 'create',
        'name'       => 'T3HTTP Group Sukses',
        'group_type' => 'group',
        'capacity'   => '5',
        'description'=> 'Group uji HTTP',
    ], COOKIE_ADMIN);
    check('Group berhasil dibuat lewat form', $create['status'] === 302);
    $groupId = (int) dbp()->query("SELECT id FROM class_groups WHERE name = 'T3HTTP Group Sukses' LIMIT 1")->fetchColumn();
    check('Group tersimpan di database', $groupId > 0);
    check('Kode group otomatis dibuat', $groupId > 0
        && str_starts_with((string) dbp()->query('SELECT code FROM class_groups WHERE id = ' . $groupId)->fetchColumn(), 'GRP-'));

    $subjectId = (int) dbp()->query('SELECT id FROM subjects WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
    $teacherId = (int) dbp()->query('SELECT id FROM teachers WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
    $linkPage = http('GET', '/admin/group/mapel-tambah.php?group_id=' . $groupId, [], COOKIE_ADMIN);
    $token = csrf_from($linkPage['body']);
    $link = http('POST', '/admin/group/mapel-proses.php', [
        '_csrf'      => $token,
        'action'     => 'create',
        'group_id'   => (string) $groupId,
        'subject_id' => (string) $subjectId,
        'teacher_id' => (string) $teacherId,
        'is_active'  => '1',
        'description'=> '',
    ], COOKIE_ADMIN);
    check('Relasi mata pelajaran-guru berhasil dibuat', $link['status'] === 302);
    $relationId = (int) dbp()->query(
        'SELECT id FROM group_subjects WHERE group_id = ' . $groupId . ' LIMIT 1'
    )->fetchColumn();
    check('Relasi tersimpan di database', $relationId > 0);

    $dupPage = http('GET', '/admin/group/mapel-tambah.php?group_id=' . $groupId, [], COOKIE_ADMIN);
    $token = csrf_from($dupPage['body']);
    $dup = http('POST', '/admin/group/mapel-proses.php', [
        '_csrf'      => $token,
        'action'     => 'create',
        'group_id'   => (string) $groupId,
        'subject_id' => (string) $subjectId,
        'teacher_id' => (string) $teacherId,
        'is_active'  => '1',
        'description'=> '',
    ], COOKIE_ADMIN);
    check('Relasi duplikat mengembalikan form', $dup['status'] === 302);
    $dupReload = http('GET', '/admin/group/mapel-tambah.php?group_id=' . $groupId, [], COOKIE_ADMIN);
    check('Pesan relasi duplikat tampil', strpos($dupReload['body'], 'sudah terdaftar pada group ini') !== false);
    check(
        'Relasi duplikat tidak menambah baris',
        (int) dbp()->query('SELECT COUNT(*) FROM group_subjects WHERE group_id = ' . $groupId)->fetchColumn() === 1
    );

    $schedulePage = http('GET', '/admin/jadwal/tambah.php?group_id=' . $groupId, [], COOKIE_ADMIN);
    check('Form jadwal memuat relasi group', $schedulePage['status'] === 200
        && strpos($schedulePage['body'], 'name="group_subject_id"') !== false);
    $token = csrf_from($schedulePage['body']);
    $schedule = http('POST', '/admin/jadwal/proses.php', [
        '_csrf'            => $token,
        'action'           => 'create',
        'group_subject_id' => (string) $relationId,
        'day_of_week'      => '5',
        'start_time'       => '09:00',
        'end_time'         => '10:30',
        'room'             => 'Ruang HTTP',
        'notes'            => '',
        'is_active'        => '1',
        'bulan'            => '2026-01',
        'return'           => 'group',
        'return_id'        => (string) $groupId,
    ], COOKIE_ADMIN);
    check('Jadwal berhasil dibuat lewat form', $schedule['status'] === 302);
    $scheduleRow = dbp()->query(
        'SELECT * FROM group_schedules WHERE group_subject_id = ' . $relationId . ' LIMIT 1'
    )->fetch();
    check('Jadwal tersimpan pada relasi mapel', $scheduleRow !== false
        && (int) $scheduleRow['group_subject_id'] === $relationId);
    check('Jam jadwal dinormalisasi', $scheduleRow !== false
        && $scheduleRow['start_time'] === '09:00:00' && $scheduleRow['end_time'] === '10:30:00');

    // -------------------------------------------------------------
    // Jadwal fleksibel: tidak ada lagi batas 8 pertemuan per bulan.
    // -------------------------------------------------------------
    $linkPage2 = http('GET', '/admin/group/mapel-tambah.php?group_id=' . $groupId, [], COOKIE_ADMIN);
    $token = csrf_from($linkPage2['body']);
    $link2 = http('POST', '/admin/group/mapel-proses.php', [
        '_csrf'      => $token,
        'action'     => 'create',
        'group_id'   => (string) $groupId,
        'subject_id' => (string) $subjectId2,
        'teacher_id' => (string) $teacherId2,
        'is_active'  => '1',
        'description'=> '',
    ], COOKIE_ADMIN);
    check('Relasi mapel kedua pada group yang sama dibuat', $link2['status'] === 302);
    $relationId2 = (int) dbp()->query(
        'SELECT id FROM group_subjects WHERE group_id = ' . $groupId
        . ' AND subject_id = ' . $subjectId2 . ' LIMIT 1'
    )->fetchColumn();
    check('Relasi mapel kedua tersimpan', $relationId2 > 0 && $relationId2 !== $relationId);

    // Tambah jadwal Senin pada mapel kedua: tetap diterima walau total
    // jadwal mingguan group bisa melebihi 8 pertemuan dalam sebulan.
    $secondPage = http('GET', '/admin/jadwal/tambah.php?group_id=' . $groupId, [], COOKIE_ADMIN);
    check(
        'Form jadwal tidak lagi meminta bulan validasi',
        $secondPage['status'] === 200
        && strpos($secondPage['body'], 'name="bulan"') === false
        && strpos($secondPage['body'], 'Bulan validasi') === false
    );
    $token = csrf_from($secondPage['body']);
    $second = http('POST', '/admin/jadwal/proses.php', [
        '_csrf'            => $token,
        'action'           => 'create',
        'group_subject_id' => (string) $relationId2,
        'day_of_week'      => '1',
        'start_time'       => '07:00',
        'end_time'         => '08:00',
        'room'             => 'Ruang HTTP 2',
        'notes'            => '',
        'is_active'        => '1',
        'return'           => 'group',
        'return_id'        => (string) $groupId,
    ], COOKIE_ADMIN);
    check('Jadwal kedua pada group yang sama diterima tanpa batas bulanan', $second['status'] === 302);
    check(
        'Redirect simpan jadwal menuju detail group tanpa duplikasi base URL',
        $second['location'] === '/admin/group/detail.php?id=' . $groupId . '&tab=jadwal',
        $second['location']
    );
    check(
        'Jadwal kedua tersimpan pada mapel berbeda',
        (int) dbp()->query(
            'SELECT COUNT(*) FROM group_schedules WHERE group_subject_id = ' . $relationId2
        )->fetchColumn() === 1
    );

    $detail = http('GET', '/admin/group/detail.php?id=' . $groupId . '&tab=mapel', [], COOKIE_ADMIN);
    check('Detail group tab mapel tampil', $detail['status'] === 200);
    check(
        'Detail tidak lagi menampilkan rekap/batas bulanan',
        strpos($detail['body'], 'Rekap pertemuan grup') === false
        && strpos($detail['body'], 'Batas maksimal tercapai') === false
        && strpos($detail['body'], 'name="bulan"') === false
    );
    check('Tab mapel menampilkan kolom terlaksana', strpos($detail['body'], '>Terlaksana<') !== false);

    // -------------------------------------------------------------
    // Jurnal pertemuan per group (diisi admin/petugas atas nama guru).
    // -------------------------------------------------------------
    $pertemuanPage = http('GET', '/admin/group/detail.php?id=' . $groupId . '&tab=pertemuan', [], COOKIE_ADMIN);
    check(
        'Tab pertemuan menampilkan form jurnal',
        $pertemuanPage['status'] === 200
        && strpos($pertemuanPage['body'], 'id="pane-pertemuan"') !== false
        && strpos($pertemuanPage['body'], 'Simpan Catatan Pertemuan') !== false
    );

    $token = csrf_from($pertemuanPage['body']);
    $journalNotes = 'Jurnal HTTP: materi pecahan dan latihan soal.';
    $journal = http('POST', '/admin/group/pertemuan-proses.php', [
        '_csrf'            => $token,
        'group_id'         => (string) $groupId,
        'group_subject_id' => (string) $relationId,
        'meeting_date'     => '2026-04-06',
        'start_time'       => '09:00',
        'end_time'         => '10:30',
        'status'           => 'terlaksana',
        'notes'            => $journalNotes,
    ], COOKIE_ADMIN);
    check(
        'Catatan pertemuan tersimpan lewat endpoint group',
        $journal['status'] === 302 && strpos($journal['location'], 'tab=pertemuan') !== false,
        $journal['location']
    );
    $meetingRow = dbp()->query(
        'SELECT * FROM class_meetings WHERE group_id = ' . $groupId
        . " AND meeting_date = '2026-04-06' LIMIT 1"
    )->fetch();
    check(
        'Pertemuan tersimpan dengan catatan, status, dan relasi mapel',
        $meetingRow !== false
        && $meetingRow['notes'] === $journalNotes
        && $meetingRow['status'] === 'terlaksana'
        && (int) $meetingRow['group_subject_id'] === $relationId
    );
    check(
        'Petugas pencatat tercatat pada pertemuan',
        $meetingRow !== false && $meetingRow['created_by'] !== null
    );

    $pertemuanReload = http('GET', '/admin/group/detail.php?id=' . $groupId . '&tab=pertemuan', [], COOKIE_ADMIN);
    check(
        'Catatan pertemuan tampil pada jurnal group',
        strpos($pertemuanReload['body'], 'Jurnal HTTP: materi pecahan dan latihan soal.') !== false
        && preg_match('/(Fatal error|Warning:|Notice:|Deprecated:)/', $pertemuanReload['body']) !== 1
    );

    $token = csrf_from($pertemuanReload['body']);
    $emptyJournal = http('POST', '/admin/group/pertemuan-proses.php', [
        '_csrf'            => $token,
        'group_id'         => (string) $groupId,
        'group_subject_id' => (string) $relationId,
        'meeting_date'     => '2026-04-13',
        'start_time'       => '09:00',
        'end_time'         => '10:30',
        'status'           => 'terlaksana',
        'notes'            => '',
    ], COOKIE_ADMIN);
    check('Catatan pertemuan kosong ditolak', $emptyJournal['status'] === 302);
    $emptyReload = http('GET', '/admin/group/detail.php?id=' . $groupId . '&tab=pertemuan', [], COOKIE_ADMIN);
    check(
        'Pesan catatan wajib diisi tampil',
        strpos($emptyReload['body'], 'Catatan pertemuan wajib diisi minimal 3 karakter.') !== false
    );
    check(
        'Pertemuan tanpa catatan tidak tersimpan',
        (int) dbp()->query(
            'SELECT COUNT(*) FROM class_meetings WHERE group_id = ' . $groupId
            . " AND meeting_date = '2026-04-13'"
        )->fetchColumn() === 0
    );

    $petugasPage = http('GET', '/admin/group/detail.php?id=' . $groupId . '&tab=pertemuan', [], COOKIE_PETUGAS);
    $token = csrf_from($petugasPage['body']);
    $petugasJournal = http('POST', '/admin/group/pertemuan-proses.php', [
        '_csrf'            => $token,
        'group_id'         => (string) $groupId,
        'group_subject_id' => (string) $relationId2,
        'meeting_date'     => '2026-04-20',
        'start_time'       => '07:00',
        'end_time'         => '08:00',
        'status'           => 'terjadwal',
        'notes'            => 'Dijadwalkan oleh petugas.',
    ], COOKIE_PETUGAS);
    check(
        'Petugas dapat mencatat pertemuan group',
        $petugasJournal['status'] === 302
        && (int) dbp()->query(
            'SELECT COUNT(*) FROM class_meetings WHERE group_id = ' . $groupId
            . " AND meeting_date = '2026-04-20'"
        )->fetchColumn() === 1
    );
    foreach (['info', 'anggota', 'mapel', 'jadwal', 'pertemuan', 'riwayat'] as $tab) {
        $page = http('GET', '/admin/group/detail.php?id=' . $groupId . '&tab=' . $tab, [], COOKIE_ADMIN);
        $hasPane = strpos($page['body'], 'show active" id="pane-' . $tab . '"') !== false;
        $hasError = (bool) preg_match('/(Fatal error|Warning:|Notice:|Deprecated:)/', $page['body']);
        check('Tab detail "' . $tab . '" tampil tanpa error', $page['status'] === 200 && $hasPane && !$hasError);
    }
} catch (Throwable $exception) {
    $fail++;
    $failures[] = 'FATAL: ' . $exception->getMessage();
    echo "\nFATAL: " . $exception->getMessage() . "\n";
} finally {
    $pdo = Database::pdo();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    // Tahap 4: bersihkan paket/tagihan/pembayaran yang mungkin terbentuk
    // dari alur keanggotaan sebelum group dan siswa dihapus.
    $pdo->exec("DELETE FROM student_package_meetings WHERE student_package_id IN
                (SELECT id FROM student_packages WHERE group_id IN
                  (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%'))");
    $pdo->exec("DELETE FROM payment_allocations WHERE invoice_id IN
                (SELECT id FROM invoices WHERE group_id IN
                  (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%'))");
    $pdo->exec("DELETE FROM payments WHERE invoice_id IN
                (SELECT id FROM invoices WHERE group_id IN
                  (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%'))");
    $pdo->exec("DELETE FROM invoices WHERE group_id IN
                (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%')");
    $pdo->exec("DELETE FROM student_packages WHERE group_id IN
                (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%')");
    $pdo->exec("DELETE FROM student_package_meetings WHERE class_meeting_id IN
                (SELECT id FROM class_meetings WHERE group_id IN
                  (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%'))");
    $pdo->exec("DELETE FROM class_meetings WHERE group_id IN
                (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%')");
    $pdo->exec("DELETE FROM group_schedules WHERE group_subject_id IN
                (SELECT id FROM group_subjects WHERE group_id IN
                  (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%'))");
    $pdo->exec("DELETE FROM group_subjects WHERE group_id IN
                (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%')");
    $pdo->exec("DELETE FROM group_members WHERE group_id IN
                (SELECT id FROM class_groups WHERE name LIKE 'T3HTTP%')");
    $pdo->exec("DELETE FROM class_groups WHERE name LIKE 'T3HTTP%'");
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    foreach ($createdSubjectIds as $createdSubjectId) {
        $pdo->exec('DELETE FROM teacher_subjects WHERE subject_id = ' . (int) $createdSubjectId);
        $pdo->exec('DELETE FROM subjects WHERE id = ' . (int) $createdSubjectId);
    }
    foreach ($createdTeacherIds as $createdTeacherId) {
        $pdo->exec('DELETE FROM teacher_subjects WHERE teacher_id = ' . (int) $createdTeacherId);
        $pdo->exec('DELETE FROM teachers WHERE id = ' . (int) $createdTeacherId);
    }
    @unlink(COOKIE_ADMIN);
    @unlink(COOKIE_PETUGAS);
}

echo "\n============================================\n";
echo "LULUS: {$pass}   GAGAL: {$fail}\n";
if ($failures !== []) {
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
}
exit($fail === 0 ? 0 : 1);
