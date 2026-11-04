<?php
declare(strict_types=1);

/**
 * Master data siswa.
 */
final class Student
{
    public static function countActive(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM students WHERE is_active = 1')->fetchColumn();
    }

    public static function countInactive(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM students WHERE is_active = 0')->fetchColumn();
    }

    public static function countAll(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM students')->fetchColumn();
    }

    /**
     * Daftar siswa lengkap dengan group aktif.
     *
     * @param array{status?:string,school_class?:string,group_id?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function all(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (($filters['status'] ?? '') === '1' || ($filters['status'] ?? '') === '0') {
            $where[] = 's.is_active = :status';
            $params[':status'] = (int) $filters['status'];
        }
        if (($filters['school_class'] ?? '') !== '') {
            $where[] = 's.school_class = :school_class';
            $params[':school_class'] = (string) $filters['school_class'];
        }
        if (($filters['group_id'] ?? '') === 'none') {
            $where[] = 'gm.id IS NULL';
        } elseif (($filters['group_id'] ?? '') !== '') {
            $where[] = 'gm.group_id = :group_id';
            $params[':group_id'] = (int) $filters['group_id'];
        }

        $sql = 'SELECT s.*,
                       gm.id AS membership_id,
                       gm.start_date AS membership_start,
                       g.id AS group_id,
                       g.code AS group_code,
                       g.name AS group_name,
                       g.group_type,
                       (SELECT GROUP_CONCAT(sub.name ORDER BY sub.name SEPARATOR ", ")
                          FROM group_subjects gsub
                          JOIN subjects sub ON sub.id = gsub.subject_id
                         WHERE gsub.group_id = g.id AND gsub.is_active = 1) AS subject_names,
                       (SELECT GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ", ")
                          FROM group_subjects gsub
                          JOIN teachers t ON t.id = gsub.teacher_id
                         WHERE gsub.group_id = g.id AND gsub.is_active = 1) AS teacher_names
                FROM students s
                LEFT JOIN group_members gm ON gm.student_id = s.id AND gm.end_date IS NULL
                LEFT JOIN class_groups g ON g.id = gm.group_id'
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY s.name ASC, s.id ASC';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM students WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Group aktif siswa (satu per siswa dijaga unique index).
     */
    public static function activeMembership(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT gm.id AS membership_id, gm.start_date, gm.reason,
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
                    (SELECT COUNT(*) FROM group_members x
                      WHERE x.group_id = g.id AND x.end_date IS NULL) AS member_count
             FROM group_members gm
             JOIN class_groups g ON g.id = gm.group_id
             WHERE gm.student_id = :id AND gm.end_date IS NULL
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Riwayat keanggotaan group (termasuk yang sudah selesai).
     */
    public static function groupHistory(int $id): array
    {
        $stmt = db()->prepare(
            'SELECT gm.id AS membership_id, gm.start_date, gm.end_date, gm.reason,
                    g.id AS group_id, g.code AS group_code, g.name AS group_name, g.group_type,
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
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    /**
     * Riwayat tagihan siswa beserta alokasi pembayaran berhasil.
     */
    public static function invoices(int $id, int $limit = 20): array
    {
        $stmt = db()->prepare(
            'SELECT i.invoice_number, i.period_label, i.total_amount, i.status,
                    i.issue_date, i.due_date, i.invoice_type, i.package_number,
                    i.meeting_target, i.group_name,
                    COALESCE(pa.amount, 0) AS paid_amount,
                    pay.payment_number
             FROM invoices i
             LEFT JOIN payment_allocations pa ON pa.invoice_id = i.id
             LEFT JOIN payments pay ON pay.id = pa.payment_id AND pay.status = "berhasil"
             WHERE i.student_id = :id
             ORDER BY i.issue_date DESC, i.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Riwayat pembayaran siswa beserta nomor tagihan yang dilunasi.
     */
    public static function payments(int $id, int $limit = 20): array
    {
        $stmt = db()->prepare(
            "SELECT p.id, p.payment_number, p.amount, p.payment_date, p.method,
                    (SELECT GROUP_CONCAT(i.invoice_number ORDER BY i.id SEPARATOR ', ')
                       FROM payment_allocations pa
                       JOIN invoices i ON i.id = pa.invoice_id
                      WHERE pa.payment_id = p.id) AS invoice_number,
                    (SELECT COUNT(*) FROM payment_allocations pa
                      WHERE pa.payment_id = p.id) AS invoice_count
             FROM payments p
             WHERE p.student_id = :id AND p.status = 'berhasil'
             ORDER BY p.payment_date DESC, p.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Simpan siswa baru. Kode dibuat otomatis dan diulang bila bentrok.
     *
     * @param array<string, mixed> $data
     * @return array{id:int,code:string}
     */
    public static function create(array $data): array
    {
        $sql = 'INSERT INTO students
                    (code, name, gender, birth_place, birth_date, address, phone,
                     parent_name, parent_phone, school, school_class, join_date, photo, is_active)
                VALUES
                    (:code, :name, :gender, :birth_place, :birth_date, :address, :phone,
                     :parent_name, :parent_phone, :school, :school_class, :join_date, :photo, :is_active)';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $code = self::nextCode();
            try {
                $stmt = db()->prepare($sql);
                $stmt->execute(self::params($data, $code));
                return ['id' => (int) db()->lastInsertId(), 'code' => $code];
            } catch (PDOException $exception) {
                // 23000 = pelanggaran constraint; ulangi dengan kode baru.
                if ($exception->getCode() === '23000' && $attempt < 5) {
                    continue;
                }
                throw $exception;
            }
        }

        throw new RuntimeException('Kode siswa tidak dapat dibuat.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $existing = self::find($id);
        if ($existing === null) {
            throw new RuntimeException('Siswa tidak ditemukan.');
        }

        $sql = 'UPDATE students SET
                    name = :name, gender = :gender, birth_place = :birth_place, birth_date = :birth_date,
                    address = :address, phone = :phone, parent_name = :parent_name,
                    parent_phone = :parent_phone, school = :school, school_class = :school_class,
                    join_date = :join_date, photo = :photo, is_active = :is_active
                WHERE id = :id';

        $params = self::params($data, (string) $existing['code']);
        unset($params[':code']);
        $params[':id'] = $id;

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
    }

    public static function setActive(int $id, bool $active): void
    {
        $stmt = db()->prepare('UPDATE students SET is_active = :active WHERE id = :id');
        $stmt->execute([':active' => $active ? 1 : 0, ':id' => $id]);
    }

    public static function nextCode(): string
    {
        $prefix = 'SIS-' . date('Y') . '-';
        $stmt = db()->prepare('SELECT code FROM students WHERE code LIKE :prefix ORDER BY code DESC LIMIT 1');
        $stmt->execute([':prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();
        $sequence = 1;
        if (is_string($last) && preg_match('/-(\d+)$/', $last, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }
        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, string>
     */
    public static function schoolClasses(): array
    {
        return db()->query(
            "SELECT DISTINCT school_class FROM students
             WHERE school_class IS NOT NULL AND school_class <> ''
             ORDER BY school_class ASC"
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Group aktif untuk filter daftar siswa.
     */
    public static function groupOptions(): array
    {
        return db()->query(
            'SELECT g.id, g.name, g.code
             FROM class_groups g
             WHERE g.is_active = 1
             ORDER BY g.name ASC'
        )->fetchAll();
    }

    public static function hasTransactions(int $id): bool
    {
        $stmt = db()->prepare(
            'SELECT EXISTS(SELECT 1 FROM invoices WHERE student_id = :invoice_student)
                 OR EXISTS(SELECT 1 FROM payments WHERE student_id = :payment_student)'
        );
        $stmt->execute([':invoice_student' => $id, ':payment_student' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * True jika siswa punya riwayat akademik/keuangan sehingga tidak boleh dihapus.
     */
    public static function hasHistory(int $id): bool
    {
        $stmt = db()->prepare(
            'SELECT EXISTS(SELECT 1 FROM group_members WHERE student_id = :a)
                 OR EXISTS(SELECT 1 FROM invoices WHERE student_id = :b)
                 OR EXISTS(SELECT 1 FROM payments WHERE student_id = :c)'
        );
        $stmt->execute([':a' => $id, ':b' => $id, ':c' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function params(array $data, string $code): array
    {
        $nullable = static fn ($value): ?string => ($value === null || $value === '') ? null : (string) $value;

        return [
            ':code'         => $code,
            ':name'         => (string) ($data['name'] ?? ''),
            ':gender'       => (string) ($data['gender'] ?? 'L'),
            ':birth_place'  => $nullable($data['birth_place'] ?? null),
            ':birth_date'   => $nullable($data['birth_date'] ?? null),
            ':address'      => $nullable($data['address'] ?? null),
            ':phone'        => $nullable($data['phone'] ?? null),
            ':parent_name'  => $nullable($data['parent_name'] ?? null),
            ':parent_phone' => $nullable($data['parent_phone'] ?? null),
            ':school'       => $nullable($data['school'] ?? null),
            ':school_class' => $nullable($data['school_class'] ?? null),
            ':join_date'    => (string) ($data['join_date'] ?? date('Y-m-d')),
            ':photo'        => $nullable($data['photo'] ?? null),
            ':is_active'    => !empty($data['is_active']) ? 1 : 0,
        ];
    }
}
