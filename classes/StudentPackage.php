<?php
declare(strict_types=1);

/**
 * Paket belajar 8 pertemuan aktual per siswa.
 *
 * Aturan utama:
 * - satu siswa hanya boleh memiliki satu paket aktif per keanggotaan
 *   (dijaga generated column active_package_key + unique index);
 * - pertemuan dihitung dari realisasi berstatus "terlaksana" pada kelompok
 *   tempat siswa menjadi anggota pada tanggal pelaksanaan;
 * - paket selesai tepat pada pertemuan ke-8, lalu sistem membuat paket
 *   berikutnya + tagihannya (idempoten, tidak pernah ganda);
 * - paket berikutnya langsung aktif tanpa menunggu pembayaran: utang
 *   tagihan lama tidak pernah menghalangi kelanjutan belajar;
 * - sisa pertemuan paket lama tidak dipindahkan saat siswa pindah kelompok.
 */
final class StudentPackage
{
    public const TARGET = 8;

    public const STATUS_AKTIF = 'aktif';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_DIBATALKAN = 'dibatalkan';

    /**
     * @return array<int, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_AKTIF,
            self::STATUS_SELESAI,
            self::STATUS_DIBATALKAN,
        ];
    }

    /**
     * @param array{student_id?:string,group_id?:string,status?:string,q?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function all(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (($filters['student_id'] ?? '') !== '') {
            $where[] = 'p.student_id = :student_id';
            $params[':student_id'] = (int) $filters['student_id'];
        }
        if (($filters['group_id'] ?? '') !== '') {
            $where[] = 'p.group_id = :group_id';
            $params[':group_id'] = (int) $filters['group_id'];
        }
        if (in_array($filters['status'] ?? '', self::statusOptions(), true)) {
            $where[] = 'p.status = :status';
            $params[':status'] = (string) $filters['status'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(s.name LIKE :q1 OR s.code LIKE :q2 OR g.name LIKE :q3)';
            $keyword = '%' . $filters['q'] . '%';
            $params[':q1'] = $keyword;
            $params[':q2'] = $keyword;
            $params[':q3'] = $keyword;
        }

        $stmt = db()->prepare(
            'SELECT p.*, s.code AS student_code, s.name AS student_name, s.photo,
                    g.code AS group_code, g.name AS group_name,
                    gm.start_date AS membership_start, gm.end_date AS membership_end,
                    i.id AS active_invoice_id, i.invoice_number AS active_invoice_number,
                    i.status AS active_invoice_status, i.total_amount AS active_invoice_total
             FROM student_packages p
             JOIN students s ON s.id = p.student_id
             JOIN class_groups g ON g.id = p.group_id
             JOIN group_members gm ON gm.id = p.group_member_id
             LEFT JOIN invoices i
                    ON i.student_package_id = p.id AND i.status <> "dibatalkan"'
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY p.id DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT p.*, s.code AS student_code, s.name AS student_name, s.photo,
                    s.school_class, g.code AS group_code, g.name AS group_name,
                    g.group_type AS current_group_type, g.capacity,
                    gm.start_date AS membership_start, gm.end_date AS membership_end,
                    u.name AS created_by_name
             FROM student_packages p
             JOIN students s ON s.id = p.student_id
             JOIN class_groups g ON g.id = p.group_id
             JOIN group_members gm ON gm.id = p.group_member_id
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Keanggotaan aktif yang belum memiliki paket berjalan.
     * Dipakai saat membuat paket pertama secara manual.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function membershipsWithoutPackage(): array
    {
        $stmt = db()->query(
            "SELECT gm.id AS membership_id, gm.start_date AS membership_start,
                    s.id AS student_id, s.code AS student_code, s.name AS student_name, s.photo,
                    s.school_class,
                    g.id AS group_id, g.code AS group_code, g.name AS group_name,
                    g.group_type, g.capacity,
                    (SELECT COUNT(*) FROM group_members gm2
                      WHERE gm2.group_id = g.id AND gm2.end_date IS NULL) AS member_count,
                    (SELECT COUNT(*) FROM student_packages p
                      WHERE p.group_member_id = gm.id
                        AND p.status = 'aktif') AS pending_count,
                    (SELECT COALESCE(MAX(p2.package_number), 0) FROM student_packages p2
                      WHERE p2.group_member_id = gm.id) AS last_package_number
             FROM group_members gm
             JOIN students s ON s.id = gm.student_id
             JOIN class_groups g ON g.id = gm.group_id
             WHERE gm.end_date IS NULL
               AND s.is_active = 1
             ORDER BY g.name ASC, s.name ASC"
        );
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            if ((int) $row['pending_count'] === 0) {
                $result[] = $row;
            }
        }
        return $result;
    }

    /**
     * Ringkasan seluruh anggota aktif satu kelompok beserta kesiapan paket.
     * Dipakai form pembuatan paket massal per group.
     *
     * @return array{group:array<string,mixed>,members:array<int,array<string,mixed>>}|null
     */
    public static function groupMembershipOverview(int $groupId): ?array
    {
        $groupStmt = db()->prepare(
            'SELECT g.id, g.code, g.name, g.group_type, g.capacity, g.is_active,
                    (SELECT COUNT(*) FROM group_members gm
                      WHERE gm.group_id = g.id AND gm.end_date IS NULL) AS member_count
             FROM class_groups g
             WHERE g.id = :id LIMIT 1'
        );
        $groupStmt->execute([':id' => $groupId]);
        $group = $groupStmt->fetch();
        if ($group === false) {
            return null;
        }

        $memberStmt = db()->prepare(
            "SELECT gm.id AS membership_id, gm.start_date AS membership_start,
                    s.id AS student_id, s.code AS student_code, s.name AS student_name,
                    s.school_class, s.photo, s.is_active AS student_active,
                    g.id AS group_id, g.code AS group_code, g.name AS group_name,
                    g.group_type, g.capacity,
                    (SELECT COUNT(*) FROM student_packages p
                      WHERE p.group_member_id = gm.id
                        AND p.status = 'aktif') AS active_count,
                    (SELECT COALESCE(MAX(p2.package_number), 0) FROM student_packages p2
                      WHERE p2.group_member_id = gm.id) AS last_package_number
             FROM group_members gm
             JOIN students s ON s.id = gm.student_id
             JOIN class_groups g ON g.id = gm.group_id
             WHERE gm.group_id = :group_id
               AND gm.end_date IS NULL
             ORDER BY s.name ASC, gm.id ASC"
        );
        $memberStmt->execute([':group_id' => $groupId]);
        $members = $memberStmt->fetchAll();

        foreach ($members as &$member) {
            $hasActive = (int) $member['active_count'] > 0;
            $isActiveStudent = (int) $member['student_active'] === 1;
            $member['has_active_package'] = $hasActive;
            $member['is_ready'] = $isActiveStudent && !$hasActive;
            $member['skip_reason'] = !$isActiveStudent
                ? 'Siswa nonaktif'
                : ($hasActive ? 'Sudah memiliki paket aktif' : '');
        }
        unset($member);

        return ['group' => $group, 'members' => $members];
    }

    /**
     * Buat paket untuk seluruh siswa terpilih dalam satu group sekaligus.
     *
     * Paket dan tagihan tetap dimiliki per siswa; method ini hanya
     * menjalankan pembuatan massal dalam satu transaksi. Siswa yang sudah
     * memiliki paket aktif atau nonaktif dilewati. Bila satu siswa gagal,
     * seluruh proses dibatalkan agar tidak ada data setengah jadi.
     *
     * @param array<int,int|string> $membershipIds
     * @param array{discount?:float,notes?:string} $options
     * @return array{ok:bool,error:string,created:int,invoices:int,warnings:array<int,string>,skipped:array<int,string>,package_ids:array<int,int>,invoice_ids:array<int,int>}
     */
    public static function createForGroup(int $groupId, string $startDate, array $membershipIds, array $options = []): array
    {
        if ($groupId <= 0 || !valid_date($startDate)) {
            return self::failBulk('Kelompok dan tanggal mulai paket wajib valid.');
        }

        $overview = self::groupMembershipOverview($groupId);
        if ($overview === null || (int) $overview['group']['is_active'] !== 1) {
            return self::failBulk('Kelompok tidak ditemukan atau sudah nonaktif.');
        }

        $requested = [];
        foreach ($membershipIds as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $requested[$id] = $id;
            }
        }
        if ($requested === []) {
            return self::failBulk('Pilih minimal satu siswa yang akan dibuatkan paket.');
        }

        $byId = [];
        foreach ($overview['members'] as $member) {
            $byId[(int) $member['membership_id']] = $member;
        }
        foreach ($requested as $id) {
            if (!isset($byId[$id])) {
                return self::failBulk('Ada siswa yang bukan anggota aktif kelompok ini.');
            }
        }

        $eligible = [];
        $skipped = [];
        foreach ($requested as $id) {
            $member = $byId[$id];
            if (!$member['is_ready']) {
                $skipped[] = (string) $member['student_name'] . ' (' . (string) $member['skip_reason'] . ')';
                continue;
            }
            $eligible[] = $member;
        }
        if ($eligible === []) {
            return self::failBulk(
                'Tidak ada siswa yang siap dibuatkan paket. Semua siswa terpilih sudah memiliki paket aktif atau nonaktif.'
            );
        }

        $discount = round(max(0.0, (float) ($options['discount'] ?? 0)), 2);
        $notes = mb_substr(trim((string) ($options['notes'] ?? '')), 0, 500);

        $pdo = db();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }

        $packageIds = [];
        $invoiceIds = [];
        $warnings = [];
        try {
            foreach ($eligible as $member) {
                // Siswa yang masuk setelah tanggal mulai group memakai
                // tanggal masuknya sendiri agar paket tidak mendahului
                // keanggotaan.
                $effectiveStart = $startDate < (string) $member['membership_start']
                    ? (string) $member['membership_start']
                    : $startDate;

                $result = self::createForMembership((int) $member['membership_id'], $effectiveStart, [
                    'discount' => $discount,
                    'notes'    => $notes,
                ]);
                if (!$result['ok']) {
                    throw new DomainException(
                        (string) $member['student_name'] . ': ' . $result['error']
                    );
                }
                $packageIds[] = (int) $result['id'];
                if ((int) $result['invoice_id'] > 0) {
                    $invoiceIds[] = (int) $result['invoice_id'];
                }
                if ($result['warning'] !== '') {
                    $warnings[] = (string) $member['student_name'] . ': ' . $result['warning'];
                }
            }

            ActivityLog::record(
                'paket_massal',
                count($packageIds) . ' paket dibuat sekaligus untuk group '
                . (string) $overview['group']['name'] . ' sejak ' . tanggal_id($startDate) . '.',
                'class_group',
                $groupId
            );

            if ($own) {
                $pdo->commit();
            }

            return [
                'ok'          => true,
                'error'       => '',
                'created'     => count($packageIds),
                'invoices'    => count($invoiceIds),
                'warnings'    => $warnings,
                'skipped'     => $skipped,
                'package_ids' => $packageIds,
                'invoice_ids' => $invoiceIds,
            ];
        } catch (DomainException $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return self::failBulk($exception->getMessage());
        } catch (Throwable $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('bulk', $exception);
            return self::failBulk('Paket massal gagal dibuat. Silakan coba lagi.');
        }
    }

    /**
     * Ringkas satu keanggotaan (untuk pesan/validasi).
     *
     * @return array<string, mixed>|null
     */
    public static function findForMembership(int $membershipId): ?array
    {
        $stmt = db()->prepare(
            'SELECT gm.id AS membership_id, gm.start_date AS membership_start,
                    s.name AS student_name, g.name AS group_name
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
     * Riwayat paket satu keanggotaan.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forMembership(int $membershipId): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM student_packages WHERE group_member_id = :id
             ORDER BY package_number ASC, id ASC'
        );
        $stmt->execute([':id' => $membershipId]);
        return $stmt->fetchAll();
    }

    public static function activeForMembership(int $membershipId): ?array
    {
        $stmt = db()->prepare(
            "SELECT * FROM student_packages
             WHERE group_member_id = :id AND status = 'aktif'
             LIMIT 1"
        );
        $stmt->execute([':id' => $membershipId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Paket yang masih berjalan pada satu keanggotaan.
     * Dipakai untuk peringatan perpindahan kelompok.
     */
    public static function pendingForMembership(int $membershipId): ?array
    {
        $stmt = db()->prepare(
            "SELECT * FROM student_packages
             WHERE group_member_id = :id
               AND status = 'aktif'
             ORDER BY package_number DESC LIMIT 1"
        );
        $stmt->execute([':id' => $membershipId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Paket belum selesai milik seorang siswa (untuk peringatan pindah group).
     */
    public static function unfinishedForStudent(int $studentId): ?array
    {
        $stmt = db()->prepare(
            "SELECT p.*, g.name AS group_name
             FROM student_packages p
             JOIN class_groups g ON g.id = p.group_id
             WHERE p.student_id = :student_id
               AND p.status = 'aktif'
               AND p.completed_meetings < p.target_meetings
             ORDER BY p.id DESC LIMIT 1"
        );
        $stmt->execute([':student_id' => $studentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Riwayat pertemuan yang masuk ke paket.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function meetings(int $packageId): array
    {
        $stmt = db()->prepare(
            'SELECT spm.id AS link_id, spm.meeting_date,
                    cm.id AS meeting_id, cm.start_time, cm.end_time, cm.status,
                    cm.notes, cm.rescheduled_from_id,
                    sub.name AS subject_name, t.name AS teacher_name
             FROM student_package_meetings spm
             JOIN class_meetings cm ON cm.id = spm.class_meeting_id
             JOIN group_subjects gsub ON gsub.id = cm.group_subject_id
             JOIN subjects sub ON sub.id = gsub.subject_id
             JOIN teachers t ON t.id = cm.teacher_id
             WHERE spm.student_package_id = :id
             ORDER BY spm.meeting_date ASC, cm.start_time ASC, cm.id ASC'
        );
        $stmt->execute([':id' => $packageId]);
        return $stmt->fetchAll();
    }

    /**
     * Buat paket pertama untuk sebuah keanggotaan (aksi admin).
     *
     * @param array{discount?:float,notes?:string} $options
     * @return array{ok:bool,error:string,id:int,invoice_id:int,warning:string}
     */
    public static function createForMembership(int $membershipId, string $startDate, array $options = []): array
    {
        if ($membershipId <= 0 || !valid_date($startDate)) {
            return self::fail('Keanggotaan dan tanggal mulai paket wajib valid.');
        }

        $discount = round(max(0.0, (float) ($options['discount'] ?? 0)), 2);
        $notes = mb_substr(trim((string) ($options['notes'] ?? '')), 0, 500);

        $pdo = db();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }

        try {
            $memberStmt = $pdo->prepare(
                'SELECT gm.*, g.name AS group_name, g.group_type
                 FROM group_members gm
                 JOIN class_groups g ON g.id = gm.group_id
                 WHERE gm.id = :id LIMIT 1 FOR UPDATE'
            );
            $memberStmt->execute([':id' => $membershipId]);
            $member = $memberStmt->fetch();
            if ($member === false) {
                throw new DomainException('Keanggotaan tidak ditemukan.');
            }
            if ($member['end_date'] !== null) {
                throw new DomainException('Keanggotaan ini sudah berakhir; paket baru tidak dapat dibuat.');
            }
            if ($startDate < (string) $member['start_date']) {
                throw new DomainException(
                    'Tanggal mulai paket tidak boleh sebelum tanggal masuk keanggotaan ('
                    . tanggal_id((string) $member['start_date']) . ').'
                );
            }

            $activeStmt = $pdo->prepare(
                "SELECT id, package_number FROM student_packages
                 WHERE group_member_id = :id AND status = 'aktif'
                 LIMIT 1 FOR UPDATE"
            );
            $activeStmt->execute([':id' => $membershipId]);
            $active = $activeStmt->fetch();
            if ($active !== false) {
                throw new DomainException(
                    'Siswa sudah memiliki paket aktif (Paket ' . (int) $active['package_number'] . ').'
                );
            }

            $numberStmt = $pdo->prepare(
                'SELECT COALESCE(MAX(package_number), 0) FROM student_packages WHERE group_member_id = :id'
            );
            $numberStmt->execute([':id' => $membershipId]);
            $number = (int) $numberStmt->fetchColumn() + 1;

            $memberCount = self::memberCountAt((int) $member['group_id'], $startDate);
            $pricing = Pricing::findApplicable((string) $member['group_type'], $memberCount, $startDate);
            $base = $pricing !== null ? (float) $pricing['amount'] : 0.0;
            if ($discount > $base) {
                throw new DomainException('Diskon tidak boleh melebihi tarif dasar.');
            }

            $insert = $pdo->prepare(
                'INSERT INTO student_packages
                    (student_id, group_member_id, group_id, package_number, start_date, end_date,
                     target_meetings, completed_meetings, status, invoice_id, pricing_rule_id,
                     group_type, member_count, base_amount, discount_amount, total_amount,
                     notes, created_by)
                 VALUES
                    (:student_id, :group_member_id, :group_id, :package_number, :start_date, NULL,
                     :target, 0, :status, NULL, :pricing_rule_id,
                     :group_type, :member_count, :base, :discount, :total,
                     :notes, :created_by)'
            );
            $insert->execute([
                ':student_id'      => (int) $member['student_id'],
                ':group_member_id' => $membershipId,
                ':group_id'        => (int) $member['group_id'],
                ':package_number'  => $number,
                ':start_date'      => $startDate,
                ':target'          => self::TARGET,
                ':status'          => self::STATUS_AKTIF,
                ':pricing_rule_id' => $pricing !== null ? (int) $pricing['id'] : null,
                ':group_type'      => (string) $member['group_type'],
                ':member_count'    => $memberCount,
                ':base'            => $base,
                ':discount'        => $discount,
                ':total'           => max(0.0, $base - $discount),
                ':notes'           => $notes !== '' ? $notes : null,
                ':created_by'      => self::actorId(),
            ]);
            $packageId = (int) $pdo->lastInsertId();

            $invoiceId = 0;
            $warning = '';
            if ($pricing === null) {
                $warning = 'Tarif untuk ' . group_type_label((string) $member['group_type'])
                    . ' dengan ' . $memberCount . ' siswa belum diatur. Tagihan belum diterbitkan; '
                    . 'atur tarif lalu gunakan tombol Terbitkan Tagihan.';
            } else {
                $invoiceId = Invoice::createForPackage($packageId, $discount, $startDate);
            }

            self::log(
                'paket_tambah',
                'Paket ' . $number . ' untuk ' . $member['group_name'] . ' dibuat.',
                $packageId
            );

            if ($own) {
                $pdo->commit();
            }

            return ['ok' => true, 'error' => '', 'id' => $packageId, 'invoice_id' => $invoiceId, 'warning' => $warning];
        } catch (DomainException $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return self::fail($exception->getMessage());
        } catch (Throwable $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('create', $exception);
            return self::fail('Paket gagal dibuat. Silakan coba lagi.');
        }
    }

    /**
     * Setelah paket selesai (8/8), buat paket berikutnya + tagihan.
     * Idempoten: pemanggilan ulang tidak membuat paket ganda.
     *
     * @return array{ok:bool,error:string,id:int,invoice_id:int}
     */
    public static function ensureNextPackage(int $packageId, string $date): array
    {
        $pdo = db();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }

        try {
            $lock = $pdo->prepare('SELECT * FROM student_packages WHERE id = :id LIMIT 1 FOR UPDATE');
            $lock->execute([':id' => $packageId]);
            $package = $lock->fetch();
            if ($package === false) {
                throw new DomainException('Paket tidak ditemukan.');
            }
            if ((string) $package['status'] !== self::STATUS_SELESAI) {
                if ($own) {
                    $pdo->commit();
                }
                return ['ok' => true, 'error' => '', 'id' => 0, 'invoice_id' => 0];
            }

            $nextNumber = (int) $package['package_number'] + 1;
            $existingStmt = $pdo->prepare(
                'SELECT * FROM student_packages
                 WHERE group_member_id = :member_id AND package_number = :number LIMIT 1 FOR UPDATE'
            );
            $existingStmt->execute([
                ':member_id' => (int) $package['group_member_id'],
                ':number'    => $nextNumber,
            ]);
            $existing = $existingStmt->fetch();

            $memberCount = self::memberCountAt((int) $package['group_id'], $date);
            $pricing = Pricing::findApplicable((string) $package['group_type'], $memberCount, $date);
            $base = $pricing !== null ? (float) $pricing['amount'] : 0.0;
            // Paket berikutnya SELALU aktif. Ketiadaan tarif hanya berarti
            // tagihan belum dapat diterbitkan, bukan penghalang belajar.
            $status = self::STATUS_AKTIF;

            if ($existing !== false) {
                if ((string) $existing['status'] !== self::STATUS_DIBATALKAN) {
                    if ($own) {
                        $pdo->commit();
                    }
                    return [
                        'ok'         => true,
                        'error'      => '',
                        'id'         => (int) $existing['id'],
                        'invoice_id' => (int) $existing['invoice_id'],
                    ];
                }

                // Paket sebelumnya dibatalkan karena pembatalan pertemuan →
                // aktifkan kembali dengan nomor yang sama.
                $reactivate = $pdo->prepare(
                    'UPDATE student_packages SET
                        status = :status, start_date = :start_date, end_date = NULL,
                        completed_meetings = 0, member_count = :member_count,
                        pricing_rule_id = :pricing_rule_id, base_amount = :base,
                        discount_amount = 0, total_amount = :total,
                        cancel_reason = NULL, cancelled_at = NULL, cancelled_by = NULL,
                        invoice_id = NULL
                     WHERE id = :id'
                );
                $reactivate->execute([
                    ':status'          => $status,
                    ':start_date'      => $date,
                    ':member_count'    => $memberCount,
                    ':pricing_rule_id' => $pricing !== null ? (int) $pricing['id'] : null,
                    ':base'            => $base,
                    ':total'           => $base,
                    ':id'              => (int) $existing['id'],
                ]);
                $nextId = (int) $existing['id'];
            } else {
                $numberStmt = $pdo->prepare(
                    'SELECT COALESCE(MAX(package_number), 0) FROM student_packages WHERE group_member_id = :id'
                );
                $numberStmt->execute([':id' => (int) $package['group_member_id']]);
                $number = max($nextNumber, (int) $numberStmt->fetchColumn() + 1);

                $insert = $pdo->prepare(
                    'INSERT INTO student_packages
                        (student_id, group_member_id, group_id, package_number, start_date, end_date,
                         target_meetings, completed_meetings, status, invoice_id, pricing_rule_id,
                         group_type, member_count, base_amount, discount_amount, total_amount, created_by)
                     VALUES
                        (:student_id, :group_member_id, :group_id, :package_number, :start_date, NULL,
                         :target, 0, :status, NULL, :pricing_rule_id,
                         :group_type, :member_count, :base, 0, :total, :created_by)'
                );
                $insert->execute([
                    ':student_id'      => (int) $package['student_id'],
                    ':group_member_id' => (int) $package['group_member_id'],
                    ':group_id'        => (int) $package['group_id'],
                    ':package_number'  => $number,
                    ':start_date'      => $date,
                    ':target'          => self::TARGET,
                    ':status'          => $status,
                    ':pricing_rule_id' => $pricing !== null ? (int) $pricing['id'] : null,
                    ':group_type'      => (string) $package['group_type'],
                    ':member_count'    => $memberCount,
                    ':base'            => $base,
                    ':total'           => $base,
                    ':created_by'      => self::actorId(),
                ]);
                $nextId = (int) $pdo->lastInsertId();
            }

            $invoiceId = 0;
            if ($pricing !== null) {
                $invoiceId = Invoice::createForPackage($nextId, 0.0, $date);
            }

            self::log(
                'paket_berikutnya',
                'Paket ' . $nextNumber . ' otomatis dibuat setelah paket ' . (int) $package['package_number']
                . ' mencapai ' . self::TARGET . ' pertemuan.',
                $nextId
            );

            if ($own) {
                $pdo->commit();
            }
            return ['ok' => true, 'error' => '', 'id' => $nextId, 'invoice_id' => $invoiceId];
        } catch (DomainException $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $exception->getMessage(), 'id' => 0, 'invoice_id' => 0];
        } catch (Throwable $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('next', $exception);
            return ['ok' => false, 'error' => 'Paket berikutnya gagal dibuat.', 'id' => 0, 'invoice_id' => 0];
        }
    }

    /**
     * Terbitkan tagihan untuk paket yang belum memiliki invoice aktif.
     *
     * @return array{ok:bool,error:string,invoice_id:int}
     */
    public static function issueInvoice(int $packageId, float $discount = 0.0, ?string $issueDate = null): array
    {
        $pdo = db();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }

        try {
            $lock = $pdo->prepare('SELECT * FROM student_packages WHERE id = :id LIMIT 1 FOR UPDATE');
            $lock->execute([':id' => $packageId]);
            $package = $lock->fetch();
            if ($package === false) {
                throw new DomainException('Paket tidak ditemukan.');
            }
            if ((string) $package['status'] === self::STATUS_DIBATALKAN) {
                throw new DomainException('Paket yang dibatalkan tidak dapat ditagihkan.');
            }

            $activeStmt = $pdo->prepare(
                'SELECT id FROM invoices
                 WHERE student_package_id = :id AND status <> "dibatalkan" LIMIT 1'
            );
            $activeStmt->execute([':id' => $packageId]);
            $existing = (int) $activeStmt->fetchColumn();
            if ($existing > 0) {
                if ($own) {
                    $pdo->commit();
                }
                return ['ok' => true, 'error' => '', 'invoice_id' => $existing];
            }

            $date = $issueDate !== null && valid_date($issueDate) ? $issueDate : (string) $package['start_date'];
            $memberCount = self::memberCountAt((int) $package['group_id'], $date);
            $pricing = Pricing::findApplicable((string) $package['group_type'], $memberCount, $date);
            if ($pricing === null) {
                throw new DomainException(
                    'Tarif untuk ' . group_type_label((string) $package['group_type'])
                    . ' dengan ' . $memberCount . ' siswa belum diatur pada tanggal '
                    . tanggal_id($date) . '. Atur tarif terlebih dahulu.'
                );
            }

            $base = (float) $pricing['amount'];
            $discount = round(max(0.0, $discount), 2);
            if ($discount > $base) {
                throw new DomainException('Diskon tidak boleh melebihi tarif dasar.');
            }

            $update = $pdo->prepare(
                'UPDATE student_packages SET
                    pricing_rule_id = :pricing_rule_id, member_count = :member_count,
                    base_amount = :base, discount_amount = :discount, total_amount = :total,
                    invoice_id = NULL
                 WHERE id = :id'
            );
            $update->execute([
                ':pricing_rule_id' => (int) $pricing['id'],
                ':member_count'    => $memberCount,
                ':base'            => $base,
                ':discount'        => $discount,
                ':total'           => max(0.0, $base - $discount),
                ':id'              => $packageId,
            ]);

            $invoiceId = Invoice::createForPackage($packageId, $discount, $date);

            self::log(
                'tagihan_terbit',
                'Tagihan paket ' . (int) $package['package_number'] . ' diterbitkan.',
                $packageId
            );

            if ($own) {
                $pdo->commit();
            }
            return ['ok' => true, 'error' => '', 'invoice_id' => $invoiceId];
        } catch (DomainException $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $exception->getMessage(), 'invoice_id' => 0];
        } catch (Throwable $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('invoice', $exception);
            return ['ok' => false, 'error' => 'Tagihan gagal diterbitkan.', 'invoice_id' => 0];
        }
    }

    /**
     * Catat satu realisasi pertemuan ke paket aktif seluruh siswa yang
     * menjadi anggota kelompok pada tanggal tersebut.
     *
     * @return array{ok:bool,error:string,counted:int,skipped:int,package_ids:array<int,int>}
     */
    public static function addMeeting(int $meetingId, int $groupId, string $meetingDate): array
    {
        $pdo = db();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }

        try {
            $memberStmt = $pdo->prepare(
                'SELECT id, student_id FROM group_members
                 WHERE group_id = :group_id
                   AND start_date <= :meeting_date_start
                   AND (end_date IS NULL OR end_date >= :meeting_date_end)
                 ORDER BY id ASC
                 FOR UPDATE'
            );
            $memberStmt->execute([
                ':group_id'            => $groupId,
                ':meeting_date_start'  => $meetingDate,
                ':meeting_date_end'    => $meetingDate,
            ]);
            $members = $memberStmt->fetchAll();

            $counted = 0;
            $skipped = 0;
            $packageIds = [];

            foreach ($members as $member) {
                $packageStmt = $pdo->prepare(
                    "SELECT * FROM student_packages
                     WHERE group_member_id = :member_id
                       AND status = 'aktif'
                       AND start_date <= :meeting_date
                     ORDER BY package_number DESC LIMIT 1 FOR UPDATE"
                );
                $packageStmt->execute([
                    ':member_id'     => (int) $member['id'],
                    ':meeting_date'  => $meetingDate,
                ]);
                $package = $packageStmt->fetch();
                if ($package === false) {
                    $skipped++;
                    continue;
                }

                $existsStmt = $pdo->prepare(
                    'SELECT id FROM student_package_meetings
                     WHERE class_meeting_id = :meeting_id AND student_id = :student_id LIMIT 1'
                );
                $existsStmt->execute([
                    ':meeting_id'  => $meetingId,
                    ':student_id'  => (int) $member['student_id'],
                ]);
                if ($existsStmt->fetchColumn() !== false) {
                    $skipped++;
                    continue;
                }

                $link = $pdo->prepare(
                    'INSERT INTO student_package_meetings
                        (student_package_id, class_meeting_id, student_id, meeting_date)
                     VALUES (:package_id, :meeting_id, :student_id, :meeting_date)'
                );
                $link->execute([
                    ':package_id'   => (int) $package['id'],
                    ':meeting_id'   => $meetingId,
                    ':student_id'   => (int) $member['student_id'],
                    ':meeting_date' => $meetingDate,
                ]);

                $newCount = (int) $package['completed_meetings'] + 1;
                $finished = $newCount >= (int) $package['target_meetings'];
                $update = $pdo->prepare(
                    'UPDATE student_packages SET
                        completed_meetings = :count,
                        status = :status,
                        end_date = :end_date
                     WHERE id = :id'
                );
                $update->execute([
                    ':count'    => min($newCount, (int) $package['target_meetings']),
                    ':status'   => $finished ? self::STATUS_SELESAI : self::STATUS_AKTIF,
                    ':end_date' => $finished ? $meetingDate : null,
                    ':id'       => (int) $package['id'],
                ]);

                $packageIds[] = (int) $package['id'];
                $counted++;

                if ($finished) {
                    self::log(
                        'paket_selesai',
                        'Paket ' . (int) $package['package_number'] . ' selesai pada pertemuan ke-'
                        . (int) $package['target_meetings'] . '.',
                        (int) $package['id']
                    );
                    self::ensureNextPackage((int) $package['id'], $meetingDate);
                }
            }

            if ($own) {
                $pdo->commit();
            }
            return [
                'ok'          => true,
                'error'       => '',
                'counted'     => $counted,
                'skipped'     => $skipped,
                'package_ids' => $packageIds,
            ];
        } catch (Throwable $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('add-meeting', $exception);
            return [
                'ok'          => false,
                'error'       => $exception instanceof DomainException
                    ? $exception->getMessage()
                    : 'Pertemuan gagal dihitung ke paket.',
                'counted'     => 0,
                'skipped'     => 0,
                'package_ids' => [],
            ];
        }
    }

    /**
     * Batalkan penghitungan satu realisasi pertemuan.
     *
     * @return array{ok:bool,error:string,removed:int}
     */
    public static function removeMeeting(int $meetingId): array
    {
        $pdo = db();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }

        try {
            $linkStmt = $pdo->prepare(
                'SELECT * FROM student_package_meetings
                 WHERE class_meeting_id = :meeting_id ORDER BY id ASC FOR UPDATE'
            );
            $linkStmt->execute([':meeting_id' => $meetingId]);
            $links = $linkStmt->fetchAll();

            // Validasi dulu sebelum mengubah apa pun.
            foreach ($links as $link) {
                $packageStmt = $pdo->prepare('SELECT * FROM student_packages WHERE id = :id LIMIT 1 FOR UPDATE');
                $packageStmt->execute([':id' => (int) $link['student_package_id']]);
                $package = $packageStmt->fetch();
                if ($package === false) {
                    continue;
                }
                if ((string) $package['status'] === self::STATUS_SELESAI) {
                    $nextStmt = $pdo->prepare(
                        'SELECT * FROM student_packages
                         WHERE group_member_id = :member_id AND package_number = :number LIMIT 1 FOR UPDATE'
                    );
                    $nextStmt->execute([
                        ':member_id' => (int) $package['group_member_id'],
                        ':number'    => (int) $package['package_number'] + 1,
                    ]);
                    $next = $nextStmt->fetch();
                    if ($next !== false && (string) $next['status'] !== self::STATUS_DIBATALKAN) {
                        if ((int) $next['completed_meetings'] > 0) {
                            throw new DomainException(
                                'Pertemuan tidak dapat dibatalkan karena paket berikutnya sudah berjalan.'
                            );
                        }
                        if (self::hasSuccessfulPayment((int) $next['id'])) {
                            throw new DomainException(
                                'Pertemuan tidak dapat dibatalkan karena tagihan paket berikutnya sudah dibayar.'
                            );
                        }
                    }
                }
            }

            $removed = 0;
            foreach ($links as $link) {
                $packageStmt = $pdo->prepare('SELECT * FROM student_packages WHERE id = :id LIMIT 1 FOR UPDATE');
                $packageStmt->execute([':id' => (int) $link['student_package_id']]);
                $package = $packageStmt->fetch();
                if ($package === false) {
                    continue;
                }

                $delete = $pdo->prepare('DELETE FROM student_package_meetings WHERE id = :id');
                $delete->execute([':id' => (int) $link['id']]);
                $removed++;

                $newCount = max(0, (int) $package['completed_meetings'] - 1);

                if ((string) $package['status'] === self::STATUS_SELESAI) {
                    $nextStmt = $pdo->prepare(
                        'SELECT * FROM student_packages
                         WHERE group_member_id = :member_id AND package_number = :number LIMIT 1 FOR UPDATE'
                    );
                    $nextStmt->execute([
                        ':member_id' => (int) $package['group_member_id'],
                        ':number'    => (int) $package['package_number'] + 1,
                    ]);
                    $next = $nextStmt->fetch();
                    if ($next !== false && (string) $next['status'] !== self::STATUS_DIBATALKAN) {
                        self::cancelInvoiceForPackage(
                            (int) $next['id'],
                            'Dibatalkan karena pertemuan ke-' . (int) $package['target_meetings'] . ' dibatalkan.'
                        );
                        $cancelNext = $pdo->prepare(
                            'UPDATE student_packages SET
                                status = :status, cancel_reason = :reason,
                                cancelled_at = NOW(), cancelled_by = :by, invoice_id = NULL
                             WHERE id = :id'
                        );
                        $cancelNext->execute([
                            ':status' => self::STATUS_DIBATALKAN,
                            ':reason' => 'Pertemuan ke-8 dibatalkan; paket berikutnya dibatalkan.',
                            ':by'     => self::actorId(),
                            ':id'     => (int) $next['id'],
                        ]);
                    }

                    $update = $pdo->prepare(
                        'UPDATE student_packages SET
                            completed_meetings = :count, status = :status, end_date = NULL
                         WHERE id = :id'
                    );
                    $update->execute([
                        ':count'  => $newCount,
                        ':status' => self::STATUS_AKTIF,
                        ':id'     => (int) $package['id'],
                    ]);
                } else {
                    $update = $pdo->prepare(
                        'UPDATE student_packages SET completed_meetings = :count WHERE id = :id'
                    );
                    $update->execute([':count' => $newCount, ':id' => (int) $package['id']]);
                }

                self::log(
                    'pertemuan_batal_hitung',
                    'Penghitungan pertemuan dibatalkan pada paket ' . (int) $package['package_number'] . '.',
                    (int) $package['id']
                );
            }

            if ($own) {
                $pdo->commit();
            }
            return ['ok' => true, 'error' => '', 'removed' => $removed];
        } catch (DomainException $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $exception->getMessage(), 'removed' => 0];
        } catch (Throwable $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('remove-meeting', $exception);
            return ['ok' => false, 'error' => 'Penghitungan pertemuan gagal dibatalkan.', 'removed' => 0];
        }
    }

    /**
     * Tutup satu paket secara khusus dengan alasan + audit log.
     *
     * @return array{ok:bool,error:string}
     */
    public static function cancel(int $packageId, string $reason, ?string $endDate = null): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            return ['ok' => false, 'error' => 'Alasan penutupan paket minimal 3 karakter.'];
        }
        if (mb_strlen($reason) > 255) {
            return ['ok' => false, 'error' => 'Alasan penutupan paket maksimal 255 karakter.'];
        }

        $pdo = db();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }

        try {
            $lock = $pdo->prepare('SELECT * FROM student_packages WHERE id = :id LIMIT 1 FOR UPDATE');
            $lock->execute([':id' => $packageId]);
            $package = $lock->fetch();
            if ($package === false) {
                throw new DomainException('Paket tidak ditemukan.');
            }
            if ((string) $package['status'] === self::STATUS_DIBATALKAN) {
                throw new DomainException('Paket ini sudah dibatalkan.');
            }

            self::cancelInvoiceForPackage($packageId, 'Paket ditutup: ' . $reason);

            $date = $endDate !== null && valid_date($endDate) ? $endDate : date('Y-m-d');
            if ($date < (string) $package['start_date']) {
                throw new DomainException('Tanggal penutupan tidak boleh sebelum tanggal mulai paket.');
            }

            $update = $pdo->prepare(
                'UPDATE student_packages SET
                    status = :status, end_date = :end_date, cancel_reason = :reason,
                    cancelled_at = NOW(), cancelled_by = :by, invoice_id = NULL
                 WHERE id = :id'
            );
            $update->execute([
                ':status'   => self::STATUS_DIBATALKAN,
                ':end_date' => $date,
                ':reason'   => $reason,
                ':by'       => self::actorId(),
                ':id'       => $packageId,
            ]);

            self::log(
                'paket_batal',
                'Paket ' . (int) $package['package_number'] . ' ditutup: ' . $reason,
                $packageId
            );

            if ($own) {
                $pdo->commit();
            }
            return ['ok' => true, 'error' => ''];
        } catch (DomainException $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $exception->getMessage()];
        } catch (Throwable $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('cancel', $exception);
            return ['ok' => false, 'error' => 'Paket gagal ditutup.'];
        }
    }

    /**
     * Dipanggil oleh Membership::transfer di dalam transaksi yang sama.
     * Menutup paket lama (dengan alasan) lalu membuat paket baru untuk
     * keanggotaan baru. Sisa pertemuan tidak dipindahkan.
     *
     * @return array{ok:bool,error:string,new_package_id:int,new_invoice_id:int}
     */
    public static function closeForTransfer(
        int $oldMembershipId,
        int $newMembershipId,
        string $date,
        string $reason
    ): array {
        $pdo = db();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }

        try {
            $oldStmt = $pdo->prepare(
                "SELECT * FROM student_packages
                 WHERE group_member_id = :member_id
                   AND status = 'aktif'
                 ORDER BY package_number ASC FOR UPDATE"
            );
            $oldStmt->execute([':member_id' => $oldMembershipId]);
            $packages = $oldStmt->fetchAll();

            foreach ($packages as $package) {
                self::cancelInvoiceForPackage(
                    (int) $package['id'],
                    'Pindah kelompok: ' . $reason
                );
                $close = $pdo->prepare(
                    'UPDATE student_packages SET
                        status = :status, end_date = :end_date,
                        cancel_reason = :reason, cancelled_at = NOW(),
                        cancelled_by = :by, invoice_id = NULL
                     WHERE id = :id'
                );
                $close->execute([
                    ':status'   => self::STATUS_DIBATALKAN,
                    ':end_date' => $date,
                    ':reason'   => mb_substr('Pindah kelompok: ' . $reason, 0, 255),
                    ':by'       => self::actorId(),
                    ':id'       => (int) $package['id'],
                ]);
                self::log(
                    'paket_batal',
                    'Paket ' . (int) $package['package_number'] . ' ditutup karena pindah kelompok: ' . $reason,
                    (int) $package['id']
                );
            }

            $created = self::createForMembership($newMembershipId, $date, [
                'notes' => 'Paket baru setelah pindah kelompok. Sisa paket lama tidak dipindahkan.',
            ]);
            if (!$created['ok']) {
                throw new DomainException($created['error']);
            }

            if ($own) {
                $pdo->commit();
            }
            return [
                'ok'             => true,
                'error'          => '',
                'new_package_id' => $created['id'],
                'new_invoice_id' => $created['invoice_id'],
            ];
        } catch (DomainException $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $exception->getMessage(), 'new_package_id' => 0, 'new_invoice_id' => 0];
        } catch (Throwable $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('transfer', $exception);
            return [
                'ok'             => false,
                'error'          => 'Paket baru gagal dibuat saat perpindahan.',
                'new_package_id' => 0,
                'new_invoice_id' => 0,
            ];
        }
    }

    public static function hasSuccessfulPayment(int $packageId): bool
    {
        $stmt = db()->prepare(
            "SELECT EXISTS(
                SELECT 1 FROM payment_allocations pa
                JOIN payments pay ON pay.id = pa.payment_id
                JOIN invoices i ON i.id = pa.invoice_id
                WHERE i.student_package_id = :package_id AND pay.status = 'berhasil'
            )"
        );
        $stmt->execute([':package_id' => $packageId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Hitung ulang completed_meetings dari tabel riwayat (perbaikan data).
     */
    public static function recount(int $packageId): int
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM student_package_meetings WHERE student_package_id = :id'
        );
        $stmt->execute([':id' => $packageId]);
        $count = (int) $stmt->fetchColumn();
        $update = db()->prepare('UPDATE student_packages SET completed_meetings = :count WHERE id = :id');
        $update->execute([':count' => $count, ':id' => $packageId]);
        return $count;
    }

    /**
     * @return array<string, int>
     */
    public static function stats(): array
    {
        $rows = db()->query(
            'SELECT status, COUNT(*) AS total FROM student_packages GROUP BY status'
        )->fetchAll();
        $result = array_fill_keys(self::statusOptions(), 0);
        foreach ($rows as $row) {
            $result[(string) $row['status']] = (int) $row['total'];
        }
        $result['total'] = array_sum($result);
        return $result;
    }

    /**
     * Paket yang hampir selesai (6–7 dari 8).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function nearingCompletion(int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = db()->prepare(
            "SELECT p.*, s.name AS student_name, g.name AS group_name
             FROM student_packages p
             JOIN students s ON s.id = p.student_id
             JOIN class_groups g ON g.id = p.group_id
             WHERE p.status = 'aktif'
               AND p.completed_meetings >= p.target_meetings - 2
               AND p.completed_meetings < p.target_meetings
             ORDER BY p.completed_meetings DESC, p.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Paket aktif yang memiliki tagihan belum dibayar (informasi utang).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function withUnpaidInvoice(int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = db()->prepare(
            "SELECT p.*, s.name AS student_name, g.name AS group_name,
                    i.id AS invoice_id, i.invoice_number, i.total_amount AS invoice_total,
                    i.due_date
             FROM student_packages p
             JOIN students s ON s.id = p.student_id
             JOIN class_groups g ON g.id = p.group_id
             JOIN invoices i
                    ON i.student_package_id = p.id
                   AND i.status = 'belum_bayar'
             ORDER BY i.due_date ASC, p.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function progressPercent(int $completed, int $target): int
    {
        if ($target <= 0) {
            return 0;
        }
        return max(0, min(100, (int) round($completed / $target * 100)));
    }

    private static function memberCountAt(int $groupId, string $date): int
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM group_members
             WHERE group_id = :group_id
               AND start_date <= :date_start
               AND (end_date IS NULL OR end_date >= :date_end)'
        );
        $stmt->execute([':group_id' => $groupId, ':date_start' => $date, ':date_end' => $date]);
        return max(1, (int) $stmt->fetchColumn());
    }

    private static function cancelInvoiceForPackage(int $packageId, string $reason): void
    {
        $stmt = db()->prepare(
            "SELECT id FROM invoices
             WHERE student_package_id = :package_id AND status = 'belum_bayar'
             ORDER BY id DESC LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([':package_id' => $packageId]);
        $invoiceId = (int) $stmt->fetchColumn();
        if ($invoiceId <= 0) {
            return;
        }
        Invoice::cancel($invoiceId, mb_substr($reason, 0, 255));
    }

    private static function actorId(): ?int
    {
        $user = Auth::user();
        return $user !== null ? (int) $user['id'] : null;
    }

    private static function log(string $activity, string $description, int $packageId): void
    {
        ActivityLog::record($activity, $description, 'student_package', $packageId);
    }

    /**
     * @return array{ok:false,error:string,id:0,invoice_id:0,warning:string}
     */
    private static function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message, 'id' => 0, 'invoice_id' => 0, 'warning' => ''];
    }

    /**
     * @return array{ok:false,error:string,created:0,invoices:0,warnings:array<int,string>,skipped:array<int,string>,package_ids:array<int,int>,invoice_ids:array<int,int>}
     */
    private static function failBulk(string $message): array
    {
        return [
            'ok'          => false,
            'error'       => $message,
            'created'     => 0,
            'invoices'    => 0,
            'warnings'    => [],
            'skipped'     => [],
            'package_ids' => [],
            'invoice_ids' => [],
        ];
    }

    private static function logError(string $action, Throwable $exception): void
    {
        @file_put_contents(
            LOG_PATH . '/app.log',
            '[' . date('Y-m-d H:i:s') . '] PACKAGE ' . strtoupper($action) . ': '
            . $exception->getMessage() . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
