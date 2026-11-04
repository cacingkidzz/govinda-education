<?php
declare(strict_types=1);

/**
 * Kelompok belajar.
 *
 * Aturan kapasitas (dijaga juga oleh CHECK constraint di database):
 * - private : tepat 1 siswa, kapasitas = 1
 * - group   : kapasitas 2–7 siswa
 *
 * Mata pelajaran dan guru TIDAK lagi melekat langsung pada group; relasi
 * disimpan di tabel group_subjects (lihat GroupSubject) sehingga satu group
 * boleh memiliki banyak mata pelajaran dengan guru berbeda.
 */
final class Group
{
    public const TYPE_PRIVATE = 'private';
    public const TYPE_GROUP = 'group';

    public const MIN_GROUP_CAPACITY = 2;
    public const MAX_GROUP_CAPACITY = 7;

    public static function countActive(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM class_groups WHERE is_active = 1')->fetchColumn();
    }

    public static function countByType(string $type): int
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM class_groups WHERE is_active = 1 AND group_type = :type'
        );
        $stmt->execute([':type' => $type]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Daftar group beserta hitungan anggota aktif, jadwal aktif, dan
     * ringkasan mata pelajaran/guru dari group_subjects.
     *
     * Filter teacher_id/subject_id memakai EXISTS agar group yang memiliki
     * relasi tersebut tetap muncul walau punya mata pelajaran lain.
     *
     * @param array{group_type?:string,teacher_id?:string,subject_id?:string,status?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function all(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (in_array($filters['group_type'] ?? '', [self::TYPE_PRIVATE, self::TYPE_GROUP], true)) {
            $where[] = 'g.group_type = :group_type';
            $params[':group_type'] = (string) $filters['group_type'];
        }
        if (($filters['teacher_id'] ?? '') !== '') {
            $where[] = 'EXISTS(SELECT 1 FROM group_subjects x
                                WHERE x.group_id = g.id AND x.teacher_id = :teacher_id)';
            $params[':teacher_id'] = (int) $filters['teacher_id'];
        }
        if (($filters['subject_id'] ?? '') !== '') {
            $where[] = 'EXISTS(SELECT 1 FROM group_subjects y
                                WHERE y.group_id = g.id AND y.subject_id = :subject_id)';
            $params[':subject_id'] = (int) $filters['subject_id'];
        }
        if (($filters['status'] ?? '') === '1' || ($filters['status'] ?? '') === '0') {
            $where[] = 'g.is_active = :status';
            $params[':status'] = (int) $filters['status'];
        }

        $sql = self::selectColumns() . ' FROM class_groups g'
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY g.name ASC, g.id ASC';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(self::selectColumns() . ' FROM class_groups g WHERE g.id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Group aktif untuk pilihan pada form (anggota, jadwal, perpindahan).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function activeOptions(): array
    {
        return db()->query(
            'SELECT g.id, g.code, g.name, g.group_type, g.capacity,
                    (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ", ")
                       FROM group_subjects gsub
                       JOIN subjects s ON s.id = gsub.subject_id
                      WHERE gsub.group_id = g.id AND gsub.is_active = 1) AS subject_names,
                    (SELECT GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ", ")
                       FROM group_subjects gsub
                       JOIN teachers t ON t.id = gsub.teacher_id
                      WHERE gsub.group_id = g.id AND gsub.is_active = 1) AS teacher_names,
                    (SELECT COUNT(*) FROM group_members gm
                      WHERE gm.group_id = g.id AND gm.end_date IS NULL) AS member_count
             FROM class_groups g
             WHERE g.is_active = 1
             ORDER BY g.name ASC'
        )->fetchAll();
    }

    /**
     * Anggota group. $onlyActive = true → keanggotaan yang belum berakhir.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function members(int $groupId, bool $onlyActive = true): array
    {
        $sql = 'SELECT gm.id AS membership_id, gm.start_date, gm.end_date, gm.reason,
                       s.id AS student_id, s.code, s.name, s.school_class, s.photo, s.gender,
                       s.is_active AS student_active,
                       u.name AS created_by_name
                FROM group_members gm
                JOIN students s ON s.id = gm.student_id
                LEFT JOIN users u ON u.id = gm.created_by
                WHERE gm.group_id = :group_id'
            . ($onlyActive ? ' AND gm.end_date IS NULL' : '')
            . ' ORDER BY (gm.end_date IS NULL) DESC, s.name ASC, gm.id ASC';

        $stmt = db()->prepare($sql);
        $stmt->execute([':group_id' => $groupId]);
        return $stmt->fetchAll();
    }

    public static function activeMemberCount(int $groupId): int
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM group_members WHERE group_id = :group_id AND end_date IS NULL'
        );
        $stmt->execute([':group_id' => $groupId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Kapasitas efektif group (tidak pernah melebihi batas jenis group).
     */
    public static function capacityLimit(array $group): int
    {
        if ((string) $group['group_type'] === self::TYPE_PRIVATE) {
            return 1;
        }
        return max(self::MIN_GROUP_CAPACITY, min(self::MAX_GROUP_CAPACITY, (int) $group['capacity']));
    }

    /**
     * Group dianggap memenuhi komposisi bila jumlah anggota sesuai jenis group.
     */
    public static function meetsComposition(array $group, ?int $memberCount = null): bool
    {
        $count = $memberCount ?? self::activeMemberCount((int) $group['id']);
        if ((string) $group['group_type'] === self::TYPE_PRIVATE) {
            return $count === 1;
        }
        return $count >= self::MIN_GROUP_CAPACITY && $count <= self::capacityLimit($group);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id:int,code:string}
     */
    public static function create(array $data): array
    {
        $type = ($data['group_type'] ?? '') === self::TYPE_PRIVATE ? self::TYPE_PRIVATE : self::TYPE_GROUP;
        $capacity = self::normalizeCapacity($type, (int) ($data['capacity'] ?? 0));

        $sql = 'INSERT INTO class_groups
                    (code, name, group_type, capacity, is_active, description)
                VALUES
                    (:code, :name, :group_type, :capacity, :is_active, :description)';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $code = self::nextCode();
            try {
                $stmt = db()->prepare($sql);
                $stmt->execute([
                    ':code'        => $code,
                    ':name'        => (string) ($data['name'] ?? ''),
                    ':group_type'  => $type,
                    ':capacity'    => $capacity,
                    ':is_active'   => !empty($data['is_active']) ? 1 : 0,
                    ':description' => ($data['description'] ?? '') !== '' ? (string) $data['description'] : null,
                ]);
                return ['id' => (int) db()->lastInsertId(), 'code' => $code];
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000' && $attempt < 5) {
                    continue;
                }
                throw $exception;
            }
        }

        throw new RuntimeException('Kode group tidak dapat dibuat.');
    }

    /**
     * Perbarui group. Kapasitas tidak boleh lebih kecil dari anggota aktif.
     *
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM class_groups WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $existing = $stmt->fetch();
            if ($existing === false) {
                throw new DomainException('Group tidak ditemukan.');
            }

            $type = ($data['group_type'] ?? '') === self::TYPE_PRIVATE ? self::TYPE_PRIVATE : self::TYPE_GROUP;
            $capacity = self::normalizeCapacity($type, (int) ($data['capacity'] ?? 0));

            $countStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM group_members WHERE group_id = :id AND end_date IS NULL'
            );
            $countStmt->execute([':id' => $id]);
            $memberCount = (int) $countStmt->fetchColumn();

            if ($capacity < $memberCount) {
                throw new DomainException(
                    'Kapasitas tidak boleh lebih kecil dari jumlah anggota aktif (' . $memberCount . ' siswa).'
                );
            }
            if ($type === self::TYPE_PRIVATE && $memberCount > 1) {
                throw new DomainException('Group dengan lebih dari 1 anggota tidak dapat diubah menjadi private.');
            }

            $stmt = $pdo->prepare(
                'UPDATE class_groups SET
                    name = :name, group_type = :group_type,
                    capacity = :capacity, is_active = :is_active,
                    description = :description
                 WHERE id = :id'
            );
            $stmt->execute([
                ':name'        => (string) ($data['name'] ?? ''),
                ':group_type'  => $type,
                ':capacity'    => $capacity,
                ':is_active'   => !empty($data['is_active']) ? 1 : 0,
                ':description' => ($data['description'] ?? '') !== '' ? (string) $data['description'] : null,
                ':id'          => $id,
            ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function setActive(int $id, bool $active): void
    {
        $stmt = db()->prepare('UPDATE class_groups SET is_active = :active WHERE id = :id');
        $stmt->execute([':active' => $active ? 1 : 0, ':id' => $id]);
    }

    public static function nextCode(): string
    {
        $prefix = 'GRP-' . date('Y') . '-';
        $stmt = db()->prepare('SELECT code FROM class_groups WHERE code LIKE :prefix ORDER BY code DESC LIMIT 1');
        $stmt->execute([':prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();

        $sequence = 1;
        if (is_string($last) && preg_match('/-(\d+)$/', $last, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }
        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Group dengan riwayat akademik/keuangan tidak boleh dihapus permanen.
     * Relasi mata pelajaran dan jadwal juga dihitung sebagai riwayat.
     */
    public static function hasHistory(int $id): bool
    {
        $stmt = db()->prepare(
            'SELECT EXISTS(SELECT 1 FROM group_members WHERE group_id = :a)
                 OR EXISTS(SELECT 1 FROM invoices WHERE group_id = :b)
                 OR EXISTS(SELECT 1 FROM group_subjects WHERE group_id = :c)'
        );
        $stmt->execute([':a' => $id, ':b' => $id, ':c' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public static function normalizeCapacity(string $type, int $capacity): int
    {
        if ($type === self::TYPE_PRIVATE) {
            return 1;
        }
        return max(self::MIN_GROUP_CAPACITY, min(self::MAX_GROUP_CAPACITY, $capacity));
    }

    /**
     * Kolom SELECT standar untuk daftar/detail group.
     */
    private static function selectColumns(): string
    {
        return 'SELECT g.*,
                       (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ", ")
                          FROM group_subjects gsub
                          JOIN subjects s ON s.id = gsub.subject_id
                         WHERE gsub.group_id = g.id AND gsub.is_active = 1) AS subject_names,
                       (SELECT GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ", ")
                          FROM group_subjects gsub
                          JOIN teachers t ON t.id = gsub.teacher_id
                         WHERE gsub.group_id = g.id AND gsub.is_active = 1) AS teacher_names,
                       (SELECT COUNT(*) FROM group_subjects gsub
                         WHERE gsub.group_id = g.id) AS relation_count,
                       (SELECT COUNT(*) FROM group_subjects gsub
                         WHERE gsub.group_id = g.id AND gsub.is_active = 1) AS active_relation_count,
                       (SELECT COUNT(*) FROM group_members gm
                         WHERE gm.group_id = g.id AND gm.end_date IS NULL) AS member_count,
                       (SELECT COUNT(*) FROM group_schedules gs
                          JOIN group_subjects gsub ON gsub.id = gs.group_subject_id
                         WHERE gsub.group_id = g.id AND gs.is_active = 1) AS schedule_count';
    }
}
