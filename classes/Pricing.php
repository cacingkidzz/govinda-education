<?php
declare(strict_types=1);

/**
 * Tarif paket belajar 8 pertemuan.
 *
 * Nominal ditentukan admin (tidak pernah di-hardcode), berdasarkan jenis
 * kelompok (private/group) dan jumlah anggota aktif saat paket dimulai.
 * Tarif berlaku berdasarkan tanggal efektif; perubahan tarif tidak boleh
 * mengubah tagihan/nota yang sudah terbit.
 */
final class Pricing
{
    public const PACKAGE_SIZE = 8;

    /**
     * Cari tarif yang berlaku pada tanggal tertentu.
     */
    public static function findApplicable(string $groupType, int $studentCount, string $date): ?array
    {
        $stmt = db()->prepare(
            "SELECT *
             FROM pricing_rules
             WHERE group_type = :group_type
               AND student_count = :student_count
               AND is_active = 1
               AND effective_start <= :date_from
               AND (effective_end IS NULL OR effective_end >= :date_to)
             ORDER BY effective_start DESC
             LIMIT 1"
        );
        $stmt->execute([
            ':group_type'    => $groupType,
            ':student_count' => $studentCount,
            ':date_from'     => $date,
            ':date_to'       => $date,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array{group_type?:string,status?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function all(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (in_array($filters['group_type'] ?? '', [Group::TYPE_PRIVATE, Group::TYPE_GROUP], true)) {
            $where[] = 'p.group_type = :group_type';
            $params[':group_type'] = (string) $filters['group_type'];
        }
        if (($filters['status'] ?? '') === '1' || ($filters['status'] ?? '') === '0') {
            $where[] = 'p.is_active = :status';
            $params[':status'] = (int) $filters['status'];
        }

        $stmt = db()->prepare(
            'SELECT p.*, u.name AS created_by_name
             FROM pricing_rules p
             LEFT JOIN users u ON u.id = p.created_by'
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY p.group_type ASC, p.student_count ASC, p.effective_start DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM pricing_rules WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Cek apakah ada aturan tarif lain yang periodenya beririsan.
     */
    public static function hasOverlap(
        string $groupType,
        int $studentCount,
        string $effectiveStart,
        ?string $effectiveEnd,
        int $excludeId = 0
    ): bool {
        $sql = "SELECT EXISTS(
                    SELECT 1 FROM pricing_rules
                    WHERE group_type = :group_type
                      AND student_count = :student_count
                      AND (:start_date <= COALESCE(effective_end, '9999-12-31'))
                      AND (effective_start <= COALESCE(:end_date, '9999-12-31'))
                      AND id <> :exclude_id
                )";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':group_type'    => $groupType,
            ':student_count' => $studentCount,
            ':start_date'    => $effectiveStart,
            ':end_date'      => $effectiveEnd,
            ':exclude_id'    => $excludeId,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param array{group_type:string,student_count:int,amount:float,effective_start:string,effective_end:?string,is_active:int,notes:string} $data
     */
    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO pricing_rules
                (group_type, student_count, package_size, amount, effective_start, effective_end, is_active, notes, created_by)
             VALUES
                (:group_type, :student_count, :package_size, :amount, :effective_start, :effective_end, :is_active, :notes, :created_by)'
        );
        $stmt->execute([
            ':group_type'      => $data['group_type'],
            ':student_count'   => $data['student_count'],
            ':package_size'    => self::PACKAGE_SIZE,
            ':amount'          => $data['amount'],
            ':effective_start' => $data['effective_start'],
            ':effective_end'   => $data['effective_end'],
            ':is_active'       => $data['is_active'],
            ':notes'           => $data['notes'] !== '' ? $data['notes'] : null,
            ':created_by'      => self::actorId(),
        ]);
        return (int) db()->lastInsertId();
    }

    /**
     * @param array{group_type:string,student_count:int,amount:float,effective_start:string,effective_end:?string,is_active:int,notes:string} $data
     */
    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE pricing_rules SET
                group_type = :group_type, student_count = :student_count,
                amount = :amount, effective_start = :effective_start,
                effective_end = :effective_end, is_active = :is_active,
                notes = :notes
             WHERE id = :id'
        );
        $stmt->execute([
            ':group_type'      => $data['group_type'],
            ':student_count'   => $data['student_count'],
            ':amount'          => $data['amount'],
            ':effective_start' => $data['effective_start'],
            ':effective_end'   => $data['effective_end'],
            ':is_active'       => $data['is_active'],
            ':notes'           => $data['notes'] !== '' ? $data['notes'] : null,
            ':id'              => $id,
        ]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $stmt = db()->prepare('UPDATE pricing_rules SET is_active = :active WHERE id = :id');
        $stmt->execute([':active' => $active ? 1 : 0, ':id' => $id]);
    }

    private static function actorId(): ?int
    {
        $user = Auth::user();
        return $user !== null ? (int) $user['id'] : null;
    }
}
