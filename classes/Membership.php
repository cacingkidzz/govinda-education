<?php
declare(strict_types=1);

/**
 * Keanggotaan siswa pada group belajar.
 *
 * Sumber kebenaran periode keanggotaan adalah tabel group_members
 * (end_date NULL = aktif). Tabel student_group_history hanya mencatat
 * peristiwa perpindahan (group asal → group tujuan), sehingga tidak ada
 * riwayat ganda.
 *
 * Semua mutasi memakai transaksi dan row locking:
 *   urutan lock: students → class_groups (id menaik) → group_members
 */
final class Membership
{
    /**
     * Keanggotaan aktif siswa (maksimal satu, dijaga unique index).
     */
    public static function activeForStudent(int $studentId): ?array
    {
        $stmt = db()->prepare(
            'SELECT gm.id AS membership_id, gm.start_date, gm.reason,
                    g.id AS group_id, g.code AS group_code, g.name AS group_name,
                    g.group_type, g.capacity, g.is_active AS group_active,
                    (SELECT GROUP_CONCAT(sub.name ORDER BY sub.name SEPARATOR ", ")
                       FROM group_subjects gsub
                       JOIN subjects sub ON sub.id = gsub.subject_id
                      WHERE gsub.group_id = g.id AND gsub.is_active = 1) AS subject_names,
                    (SELECT GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ", ")
                       FROM group_subjects gsub
                       JOIN teachers t ON t.id = gsub.teacher_id
                      WHERE gsub.group_id = g.id AND gsub.is_active = 1) AS teacher_names,
                    (SELECT COUNT(*) FROM group_members x
                      WHERE x.group_id = g.id AND x.end_date IS NULL) AS member_count
             FROM group_members gm
             JOIN class_groups g ON g.id = gm.group_id
             WHERE gm.student_id = :id AND gm.end_date IS NULL
             LIMIT 1'
        );
        $stmt->execute([':id' => $studentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Satu record keanggotaan beserta data siswa dan group.
     */
    public static function find(int $membershipId): ?array
    {
        $stmt = db()->prepare(
            'SELECT gm.*, s.code AS student_code, s.name AS student_name, s.photo, s.school_class,
                    g.code AS group_code, g.name AS group_name, g.group_type, g.capacity,
                    (SELECT GROUP_CONCAT(sub.name ORDER BY sub.name SEPARATOR ", ")
                       FROM group_subjects gsub
                       JOIN subjects sub ON sub.id = gsub.subject_id
                      WHERE gsub.group_id = g.id AND gsub.is_active = 1) AS subject_names,
                    (SELECT GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ", ")
                       FROM group_subjects gsub
                       JOIN teachers t ON t.id = gsub.teacher_id
                      WHERE gsub.group_id = g.id AND gsub.is_active = 1) AS teacher_names
             FROM group_members gm
             JOIN students s ON s.id = gm.student_id
             JOIN class_groups g ON g.id = gm.group_id
             WHERE gm.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $membershipId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Seluruh riwayat keanggotaan siswa (terbaru lebih dahulu).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function historyForStudent(int $studentId): array
    {
        $stmt = db()->prepare(
            'SELECT gm.id AS membership_id, gm.start_date, gm.end_date, gm.reason,
                    g.id AS group_id, g.code AS group_code, g.name AS group_name,
                    g.group_type, g.capacity,
                    (SELECT GROUP_CONCAT(sub.name ORDER BY sub.name SEPARATOR ", ")
                       FROM group_subjects gsub
                       JOIN subjects sub ON sub.id = gsub.subject_id
                      WHERE gsub.group_id = g.id AND gsub.is_active = 1) AS subject_names,
                    (SELECT GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ", ")
                       FROM group_subjects gsub
                       JOIN teachers t ON t.id = gsub.teacher_id
                      WHERE gsub.group_id = g.id AND gsub.is_active = 1) AS teacher_names,
                    u.name AS created_by_name
             FROM group_members gm
             JOIN class_groups g ON g.id = gm.group_id
             LEFT JOIN users u ON u.id = gm.created_by
             WHERE gm.student_id = :id
             ORDER BY gm.start_date DESC, gm.id DESC'
        );
        $stmt->execute([':id' => $studentId]);
        return $stmt->fetchAll();
    }

    /**
     * Siswa aktif yang belum memiliki keanggotaan group aktif.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function availableStudents(): array
    {
        return db()->query(
            'SELECT s.id, s.code, s.name, s.school_class
             FROM students s
             WHERE s.is_active = 1
               AND NOT EXISTS (
                   SELECT 1 FROM group_members gm
                   WHERE gm.student_id = s.id AND gm.end_date IS NULL
               )
             ORDER BY s.name ASC'
        )->fetchAll();
    }

    /**
     * Seluruh keanggotaan aktif (halaman Anggota Group).
     *
     * @param array{group_id?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function allActive(array $filters = []): array
    {
        $where = ['gm.end_date IS NULL'];
        $params = [];

        if (($filters['group_id'] ?? '') !== '') {
            $where[] = 'gm.group_id = :group_id';
            $params[':group_id'] = (int) $filters['group_id'];
        }

        $stmt = db()->prepare(
            'SELECT gm.id AS membership_id, gm.start_date, gm.reason,
                    s.id AS student_id, s.code, s.name, s.school_class, s.photo, s.gender,
                    g.id AS group_id, g.code AS group_code, g.name AS group_name,
                    g.group_type, g.capacity,
                    (SELECT GROUP_CONCAT(sub.name ORDER BY sub.name SEPARATOR ", ")
                       FROM group_subjects gsub
                       JOIN subjects sub ON sub.id = gsub.subject_id
                      WHERE gsub.group_id = g.id AND gsub.is_active = 1) AS subject_names,
                    (SELECT GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ", ")
                       FROM group_subjects gsub
                       JOIN teachers t ON t.id = gsub.teacher_id
                      WHERE gsub.group_id = g.id AND gsub.is_active = 1) AS teacher_names,
                    u.name AS created_by_name
             FROM group_members gm
             JOIN students s ON s.id = gm.student_id
             JOIN class_groups g ON g.id = gm.group_id
             LEFT JOIN users u ON u.id = gm.created_by
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY g.name ASC, s.name ASC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Riwayat perpindahan group.
     *
     * @param array{date_from?:string,date_to?:string,from_group_id?:string,to_group_id?:string,q?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function transfers(array $filters = []): array
    {
        $where = ['h.from_group_id IS NOT NULL', 'h.to_group_id IS NOT NULL'];
        $params = [];

        if (($filters['date_from'] ?? '') !== '') {
            $where[] = 'h.start_date >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '') {
            $where[] = 'h.start_date <= :date_to';
            $params[':date_to'] = (string) $filters['date_to'];
        }
        if (($filters['from_group_id'] ?? '') !== '') {
            $where[] = 'h.from_group_id = :from_group_id';
            $params[':from_group_id'] = (int) $filters['from_group_id'];
        }
        if (($filters['to_group_id'] ?? '') !== '') {
            $where[] = 'h.to_group_id = :to_group_id';
            $params[':to_group_id'] = (int) $filters['to_group_id'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(s.name LIKE :q1 OR s.code LIKE :q2)';
            $keyword = '%' . $filters['q'] . '%';
            $params[':q1'] = $keyword;
            $params[':q2'] = $keyword;
        }

        $stmt = db()->prepare(
            'SELECT h.id, h.start_date AS transfer_date, h.reason, h.created_at,
                    s.id AS student_id, s.code AS student_code, s.name AS student_name,
                    fg.id AS from_group_id, fg.code AS from_code, fg.name AS from_name,
                    tg.id AS to_group_id, tg.code AS to_code, tg.name AS to_name,
                    u.name AS changed_by_name, u.username AS changed_by_username
             FROM student_group_history h
             JOIN students s ON s.id = h.student_id
             LEFT JOIN class_groups fg ON fg.id = h.from_group_id
             LEFT JOIN class_groups tg ON tg.id = h.to_group_id
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY h.start_date DESC, h.id DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function countStudentsInGroup(): int
    {
        return (int) db()->query(
            'SELECT COUNT(DISTINCT student_id) FROM group_members WHERE end_date IS NULL'
        )->fetchColumn();
    }

    public static function countStudentsWithoutGroup(): int
    {
        return (int) db()->query(
            'SELECT COUNT(*) FROM students s
             WHERE s.is_active = 1
               AND NOT EXISTS (
                   SELECT 1 FROM group_members gm
                   WHERE gm.student_id = s.id AND gm.end_date IS NULL
               )'
        )->fetchColumn();
    }

    /**
     * Tambahkan siswa ke group.
     *
     * @return array{ok:bool,error:string,membership_id:int}
     */
    public static function add(int $groupId, int $studentId, string $startDate, string $note = ''): array
    {
        if ($groupId <= 0 || $studentId <= 0) {
            return self::fail('Group dan siswa wajib dipilih.');
        }
        if (!valid_date($startDate)) {
            return self::fail('Tanggal masuk tidak valid.');
        }
        $note = mb_substr(trim($note), 0, 255);
        if (mb_strlen($note) > 255) {
            return self::fail('Keterangan maksimal 255 karakter.');
        }

        $pdo = db();
        try {
            $pdo->beginTransaction();

            $student = self::lockStudent($studentId);
            if ($student === null) {
                throw new DomainException('Siswa tidak ditemukan.');
            }
            if ((int) $student['is_active'] !== 1) {
                throw new DomainException('Siswa nonaktif tidak dapat ditambahkan ke group.');
            }

            $groups = self::lockGroups([$groupId]);
            $group = $groups[$groupId] ?? null;
            if ($group === null) {
                throw new DomainException('Group tidak ditemukan.');
            }
            if ((int) $group['is_active'] !== 1) {
                throw new DomainException('Group sedang nonaktif sehingga tidak dapat menerima anggota.');
            }

            $check = $pdo->prepare(
                'SELECT gm.id, g.name AS group_name
                 FROM group_members gm
                 JOIN class_groups g ON g.id = gm.group_id
                 WHERE gm.student_id = :student_id AND gm.end_date IS NULL
                 LIMIT 1 FOR UPDATE'
            );
            $check->execute([':student_id' => $studentId]);
            $active = $check->fetch();
            if ($active !== false) {
                throw new DomainException(
                    'Siswa sudah memiliki group aktif (' . $active['group_name'] . '). '
                    . 'Gunakan fitur pindah group untuk memindahkan siswa.'
                );
            }

            $countStmt = $pdo->prepare(
                'SELECT id FROM group_members WHERE group_id = :group_id AND end_date IS NULL FOR UPDATE'
            );
            $countStmt->execute([':group_id' => $groupId]);
            $memberCount = count($countStmt->fetchAll());

            $limit = Group::capacityLimit($group);
            if ($memberCount >= $limit) {
                if ((string) $group['group_type'] === Group::TYPE_PRIVATE) {
                    throw new DomainException('Group private hanya boleh memiliki 1 siswa aktif.');
                }
                if ($memberCount >= Group::MAX_GROUP_CAPACITY) {
                    throw new DomainException('Group belajar maksimal 7 siswa aktif.');
                }
                throw new DomainException('Kapasitas group sudah penuh (' . $limit . ' siswa).');
            }

            $insert = $pdo->prepare(
                'INSERT INTO group_members (group_id, student_id, start_date, end_date, reason, created_by)
                 VALUES (:group_id, :student_id, :start_date, NULL, :reason, :created_by)'
            );
            $insert->execute([
                ':group_id'   => $groupId,
                ':student_id' => $studentId,
                ':start_date' => $startDate,
                ':reason'     => $note !== '' ? $note : null,
                ':created_by' => self::actorId(),
            ]);
            $membershipId = (int) $pdo->lastInsertId();

            $pdo->commit();
            return ['ok' => true, 'error' => '', 'membership_id' => $membershipId];
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return self::fail($exception->getMessage());
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('add', $exception);
            if ($exception->getCode() === '23000') {
                return self::fail('Siswa sudah terdaftar pada group aktif. Muat ulang halaman lalu coba lagi.');
            }
            return self::fail('Keanggotaan gagal disimpan. Silakan coba lagi.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('add', $exception);
            return self::fail('Keanggotaan gagal disimpan. Silakan coba lagi.');
        }
    }

    /**
     * Akhiri keanggotaan (tidak menghapus record).
     *
     * @return array{ok:bool,error:string,membership_id:int,group_id:int,student_id:int}
     */
    public static function end(int $membershipId, string $endDate, string $note = ''): array
    {
        if ($membershipId <= 0) {
            return self::fail('Keanggotaan tidak ditemukan.');
        }
        if (!valid_date($endDate)) {
            return self::fail('Tanggal keluar tidak valid.');
        }
        $note = mb_substr(trim($note), 0, 255);

        $pdo = db();
        try {
            $pdo->beginTransaction();

            $peek = $pdo->prepare('SELECT * FROM group_members WHERE id = :id LIMIT 1');
            $peek->execute([':id' => $membershipId]);
            $peekRow = $peek->fetch();
            if ($peekRow === false) {
                throw new DomainException('Keanggotaan tidak ditemukan.');
            }

            self::lockStudent((int) $peekRow['student_id']);
            self::lockGroups([(int) $peekRow['group_id']]);

            $stmt = $pdo->prepare('SELECT * FROM group_members WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute([':id' => $membershipId]);
            $membership = $stmt->fetch();
            if ($membership === false) {
                throw new DomainException('Keanggotaan tidak ditemukan.');
            }
            if ($membership['end_date'] !== null) {
                throw new DomainException('Keanggotaan ini sudah berstatus selesai.');
            }
            if ($endDate < (string) $membership['start_date']) {
                throw new DomainException(
                    'Tanggal keluar tidak boleh sebelum tanggal masuk ('
                    . tanggal_id((string) $membership['start_date']) . ').'
                );
            }

            $update = $pdo->prepare(
                'UPDATE group_members SET end_date = :end_date, reason = COALESCE(:reason, reason) WHERE id = :id'
            );
            $update->execute([
                ':end_date' => $endDate,
                ':reason'   => $note !== '' ? $note : null,
                ':id'       => $membershipId,
            ]);

            $pdo->commit();
            return [
                'ok'            => true,
                'error'         => '',
                'membership_id' => $membershipId,
                'group_id'      => (int) $membership['group_id'],
                'student_id'    => (int) $membership['student_id'],
            ];
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return self::fail($exception->getMessage());
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('end', $exception);
            return self::fail('Keanggotaan gagal diakhiri. Silakan coba lagi.');
        }
    }

    /**
     * Pindahkan siswa ke group lain dalam satu transaksi.
     *
     * Tahap 4: bila siswa masih memiliki paket belajar yang belum tuntas
     * (atau tagihannya belum lunas), pemanggil wajib mengirim
     * $confirmPackageClose = true setelah petugas menyetujui peringatan.
     * Paket lama ditutup dengan alasan + audit log, sisa pertemuan TIDAK
     * dipindahkan, dan paket baru dibuat untuk keanggotaan baru.
     *
     * @return array{ok:bool,error:string,needs_confirmation:bool,package_number:int,package_progress:string,membership_id:int,group_id:int,student_id:int,from_name:string,to_name:string,new_package_id:int,package_warning:string}
     */
    public static function transfer(
        int $studentId,
        int $toGroupId,
        string $transferDate,
        string $reason,
        bool $confirmPackageClose = false
    ): array {
        if ($studentId <= 0 || $toGroupId <= 0) {
            return self::fail('Siswa dan group tujuan wajib dipilih.');
        }
        if (!valid_date($transferDate)) {
            return self::fail('Tanggal perpindahan tidak valid.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            return self::fail('Alasan perpindahan wajib diisi.');
        }
        if (mb_strlen($reason) < 3) {
            return self::fail('Alasan perpindahan minimal 3 karakter.');
        }
        if (mb_strlen($reason) > 200) {
            return self::fail('Alasan perpindahan maksimal 200 karakter.');
        }

        $pdo = db();
        try {
            $pdo->beginTransaction();

            $student = self::lockStudent($studentId);
            if ($student === null) {
                throw new DomainException('Siswa tidak ditemukan.');
            }
            if ((int) $student['is_active'] !== 1) {
                throw new DomainException('Siswa nonaktif tidak dapat dipindahkan ke group baru.');
            }

            $oldStmt = $pdo->prepare(
                'SELECT * FROM group_members
                 WHERE student_id = :student_id AND end_date IS NULL
                 LIMIT 1'
            );
            $oldStmt->execute([':student_id' => $studentId]);
            $old = $oldStmt->fetch();
            if ($old === false) {
                throw new DomainException('Siswa tidak memiliki keanggotaan group aktif untuk dipindahkan.');
            }

            $fromGroupId = (int) $old['group_id'];
            if ($fromGroupId === $toGroupId) {
                throw new DomainException('Group tujuan harus berbeda dari group asal.');
            }

            // Urutan lock konsisten: students → class_groups (id menaik) → group_members.
            $groups = self::lockGroups([$fromGroupId, $toGroupId]);

            $oldLock = $pdo->prepare(
                'SELECT * FROM group_members
                 WHERE student_id = :student_id AND end_date IS NULL
                 LIMIT 1 FOR UPDATE'
            );
            $oldLock->execute([':student_id' => $studentId]);
            $old = $oldLock->fetch();
            if ($old === false || (int) $old['group_id'] !== $fromGroupId) {
                throw new DomainException(
                    'Keanggotaan siswa berubah saat proses berjalan. Muat ulang halaman lalu coba lagi.'
                );
            }

            $from = $groups[$fromGroupId] ?? null;
            $to = $groups[$toGroupId] ?? null;

            if ($from === null) {
                throw new DomainException('Group asal tidak ditemukan.');
            }
            if ($to === null) {
                throw new DomainException('Group tujuan tidak ditemukan.');
            }
            if ((int) $to['is_active'] !== 1) {
                throw new DomainException('Group tujuan sedang nonaktif sehingga tidak dapat menerima anggota.');
            }
            if (GroupSubject::activeForGroup($toGroupId) === []) {
                throw new DomainException(
                    'Group tujuan belum memiliki mata pelajaran dan guru aktif. '
                    . 'Lengkapi relasi mata pelajaran terlebih dahulu.'
                );
            }
            if ($transferDate < (string) $old['start_date']) {
                throw new DomainException(
                    'Tanggal perpindahan tidak boleh sebelum tanggal masuk group asal ('
                    . tanggal_id((string) $old['start_date']) . ').'
                );
            }

            $countStmt = $pdo->prepare(
                'SELECT id FROM group_members WHERE group_id = :group_id AND end_date IS NULL FOR UPDATE'
            );
            $countStmt->execute([':group_id' => $toGroupId]);
            $memberCount = count($countStmt->fetchAll());

            $limit = Group::capacityLimit($to);
            if ($memberCount >= $limit) {
                if ((string) $to['group_type'] === Group::TYPE_PRIVATE) {
                    throw new DomainException('Group tujuan private hanya boleh memiliki 1 siswa aktif.');
                }
                if ($memberCount >= Group::MAX_GROUP_CAPACITY) {
                    throw new DomainException('Group tujuan sudah mencapai batas maksimal 7 siswa aktif.');
                }
                throw new DomainException('Kapasitas group tujuan sudah penuh (' . $limit . ' siswa).');
            }

            // Tahap 4: paket belajar yang masih berjalan harus dikonfirmasi
            // sebelum ditutup. Sisa pertemuan tidak dipindahkan ke group baru.
            $pendingStmt = $pdo->prepare(
                "SELECT * FROM student_packages
                 WHERE group_member_id = :member_id
                   AND status = 'aktif'
                 ORDER BY package_number DESC
                 LIMIT 1 FOR UPDATE"
            );
            $pendingStmt->execute([':member_id' => (int) $old['id']]);
            $pendingPackage = $pendingStmt->fetch() ?: null;

            if ($pendingPackage !== null && !$confirmPackageClose) {
                $pdo->rollBack();
                return [
                    'ok'                 => false,
                    'error'              => 'Siswa masih memiliki Paket ' . (int) $pendingPackage['package_number']
                        . ' (' . (int) $pendingPackage['completed_meetings'] . '/'
                        . (int) $pendingPackage['target_meetings'] . ' pertemuan, status '
                        . package_status_label((string) $pendingPackage['status'])
                        . '). Konfirmasi diperlukan: paket lama akan ditutup, sisa pertemuan tidak '
                        . 'dipindahkan, dan paket baru dibuat untuk group tujuan.',
                    'needs_confirmation' => true,
                    'package_number'     => (int) $pendingPackage['package_number'],
                    'package_progress'   => (int) $pendingPackage['completed_meetings'] . '/'
                        . (int) $pendingPackage['target_meetings'],
                    'membership_id'      => 0,
                    'group_id'           => 0,
                    'student_id'         => $studentId,
                    'from_name'          => (string) ($from['name'] ?? ''),
                    'to_name'            => (string) $to['name'],
                    'new_package_id'     => 0,
                    'package_warning'    => '',
                ];
            }

            // 1. Tutup keanggotaan lama (record tetap tersimpan sebagai riwayat).
            $closeStmt = $pdo->prepare(
                'UPDATE group_members SET end_date = :end_date, reason = :reason WHERE id = :id'
            );
            $closeStmt->execute([
                ':end_date' => $transferDate,
                ':reason'   => mb_substr('Pindah ke ' . $to['name'] . ': ' . $reason, 0, 255),
                ':id'       => (int) $old['id'],
            ]);

            // 2. Buat keanggotaan baru pada group tujuan.
            $insert = $pdo->prepare(
                'INSERT INTO group_members (group_id, student_id, start_date, end_date, reason, created_by)
                 VALUES (:group_id, :student_id, :start_date, NULL, :reason, :created_by)'
            );
            $insert->execute([
                ':group_id'   => $toGroupId,
                ':student_id' => $studentId,
                ':start_date' => $transferDate,
                ':reason'     => $reason,
                ':created_by' => self::actorId(),
            ]);
            $newMembershipId = (int) $pdo->lastInsertId();

            // 3. Catat peristiwa perpindahan (bukan duplikasi periode keanggotaan).
            $history = $pdo->prepare(
                'INSERT INTO student_group_history
                    (student_id, from_group_id, to_group_id, start_date, end_date, reason, changed_by)
                 VALUES
                    (:student_id, :from_group_id, :to_group_id, :start_date, NULL, :reason, :changed_by)'
            );
            $history->execute([
                ':student_id'    => $studentId,
                ':from_group_id' => $fromGroupId,
                ':to_group_id'   => $toGroupId,
                ':start_date'    => $transferDate,
                ':reason'        => mb_substr($reason, 0, 255),
                ':changed_by'    => self::actorId(),
            ]);

            // 4. Tahap 4: tutup paket lama (bila ada) dan buat paket baru
            //    untuk keanggotaan baru. Sisa pertemuan tidak dipindahkan.
            $newPackageId = 0;
            $packageWarning = '';
            if ($pendingPackage !== null) {
                $packageResult = StudentPackage::closeForTransfer(
                    (int) $old['id'],
                    $newMembershipId,
                    $transferDate,
                    $reason
                );
                if (!$packageResult['ok']) {
                    throw new DomainException('Perpindahan dibatalkan: ' . $packageResult['error']);
                }
                $newPackageId = (int) $packageResult['new_package_id'];
            } else {
                $packageResult = StudentPackage::createForMembership($newMembershipId, $transferDate, [
                    'notes' => 'Paket baru setelah pindah kelompok. Sisa paket lama tidak dipindahkan.',
                ]);
                if (!$packageResult['ok']) {
                    throw new DomainException('Perpindahan dibatalkan: ' . $packageResult['error']);
                }
                $newPackageId = (int) $packageResult['id'];
                $packageWarning = (string) $packageResult['warning'];
            }

            $pdo->commit();

            return [
                'ok'                 => true,
                'error'              => '',
                'needs_confirmation' => false,
                'package_number'     => $pendingPackage !== null ? (int) $pendingPackage['package_number'] : 0,
                'package_progress'   => $pendingPackage !== null
                    ? (int) $pendingPackage['completed_meetings'] . '/' . (int) $pendingPackage['target_meetings']
                    : '',
                'membership_id'      => $newMembershipId,
                'group_id'           => $toGroupId,
                'student_id'         => $studentId,
                'from_name'          => (string) $from['name'],
                'to_name'            => (string) $to['name'],
                'new_package_id'     => $newPackageId,
                'package_warning'    => $packageWarning,
            ];
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return self::fail($exception->getMessage());
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('transfer', $exception);
            if ($exception->getCode() === '23000') {
                return self::fail('Siswa sudah memiliki keanggotaan aktif. Muat ulang halaman lalu coba lagi.');
            }
            return self::fail('Perpindahan gagal diproses. Tidak ada data yang diubah.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('transfer', $exception);
            return self::fail('Perpindahan gagal diproses. Tidak ada data yang diubah.');
        }
    }

    private static function lockStudent(int $studentId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM students WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute([':id' => $studentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Lock beberapa group sekaligus dengan urutan id menaik untuk menghindari deadlock.
     *
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    private static function lockGroups(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        sort($ids);

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            'SELECT * FROM class_groups WHERE id IN (' . $placeholders . ') ORDER BY id ASC FOR UPDATE'
        );
        $stmt->execute($ids);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[(int) $row['id']] = $row;
        }
        return $rows;
    }

    private static function actorId(): ?int
    {
        $user = Auth::user();
        return $user !== null ? (int) $user['id'] : null;
    }

    /**
     * @return array{ok:false,error:string,needs_confirmation:false,package_number:0,package_progress:'',membership_id:0,group_id:0,student_id:0,from_name:'',to_name:'',new_package_id:0,package_warning:''}
     */
    private static function fail(string $message): array
    {
        return [
            'ok'                 => false,
            'error'              => $message,
            'needs_confirmation' => false,
            'package_number'     => 0,
            'package_progress'   => '',
            'membership_id'      => 0,
            'group_id'           => 0,
            'student_id'         => 0,
            'from_name'          => '',
            'to_name'            => '',
            'new_package_id'     => 0,
            'package_warning'    => '',
        ];
    }

    private static function logError(string $action, Throwable $exception): void
    {
        @file_put_contents(
            LOG_PATH . '/app.log',
            '[' . date('Y-m-d H:i:s') . '] MEMBERSHIP ' . strtoupper($action) . ': '
            . $exception->getMessage() . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
