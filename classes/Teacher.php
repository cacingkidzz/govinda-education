<?php
declare(strict_types=1);

/**
 * Master data guru.
 */
final class Teacher
{
    public static function countActive(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM teachers WHERE is_active = 1')->fetchColumn();
    }

    public static function countInactive(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM teachers WHERE is_active = 0')->fetchColumn();
    }

    public static function countAll(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM teachers')->fetchColumn();
    }

    /**
     * @param array{status?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function all(array $filters = []): array
    {
        $where = '';
        $params = [];
        if (($filters['status'] ?? '') === '1' || ($filters['status'] ?? '') === '0') {
            $where = ' WHERE t.is_active = :status';
            $params[':status'] = (int) $filters['status'];
        }

        $stmt = db()->prepare(
            'SELECT t.*,
                    (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ", ")
                       FROM teacher_subjects ts
                       JOIN subjects s ON s.id = ts.subject_id
                      WHERE ts.teacher_id = t.id) AS subject_names
             FROM teachers t'
            . $where
            . ' ORDER BY t.name ASC, t.id ASC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM teachers WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function subjects(int $id): array
    {
        $stmt = db()->prepare(
            'SELECT s.* FROM teacher_subjects ts
             JOIN subjects s ON s.id = ts.subject_id
             WHERE ts.teacher_id = :id
             ORDER BY s.name ASC'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    /**
     * @return array<int, int>
     */
    public static function subjectIds(int $id): array
    {
        $stmt = db()->prepare('SELECT subject_id FROM teacher_subjects WHERE teacher_id = :id');
        $stmt->execute([':id' => $id]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Group yang diajar guru.
     */
    public static function groups(int $id): array
    {
        $stmt = db()->prepare(
            'SELECT g.id, g.code, g.name, g.group_type, g.is_active, g.capacity,
                    sub.name AS subject_name, gsub.is_active AS relation_active,
                    (SELECT COUNT(*) FROM group_members gm
                      WHERE gm.group_id = g.id AND gm.end_date IS NULL) AS member_count
             FROM group_subjects gsub
             JOIN class_groups g ON g.id = gsub.group_id
             JOIN subjects sub ON sub.id = gsub.subject_id
             WHERE gsub.teacher_id = :id
             ORDER BY g.name ASC, sub.name ASC'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    /**
     * Jadwal mengajar guru (berasal dari relasi grup–mata pelajaran).
     */
    public static function schedules(int $id): array
    {
        $stmt = db()->prepare(
            'SELECT gs.day_of_week, gs.start_time, gs.end_time, gs.room,
                    g.name AS group_name, sub.name AS subject_name
             FROM group_schedules gs
             JOIN group_subjects gsub ON gsub.id = gs.group_subject_id
             JOIN class_groups g ON g.id = gsub.group_id
             JOIN subjects sub ON sub.id = gsub.subject_id
             WHERE gsub.teacher_id = :id AND gs.is_active = 1 AND gsub.is_active = 1
             ORDER BY gs.day_of_week ASC, gs.start_time ASC'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, int>      $subjectIds
     * @return array{id:int,code:string}
     */
    public static function create(array $data, array $subjectIds = []): array
    {
        $sql = 'INSERT INTO teachers
                    (code, name, gender, birth_place, birth_date, address, phone, email,
                     education, photo, is_active)
                VALUES
                    (:code, :name, :gender, :birth_place, :birth_date, :address, :phone, :email,
                     :education, :photo, :is_active)';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $code = self::nextCode();
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare($sql);
                $stmt->execute(self::params($data, $code));
                $id = (int) $pdo->lastInsertId();
                self::syncSubjects($id, $subjectIds);
                $pdo->commit();
                return ['id' => $id, 'code' => $code];
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($exception->getCode() === '23000' && $attempt < 5) {
                    continue;
                }
                throw $exception;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        }

        throw new RuntimeException('Kode guru tidak dapat dibuat.');
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, int>      $subjectIds
     */
    public static function update(int $id, array $data, array $subjectIds = []): void
    {
        $existing = self::find($id);
        if ($existing === null) {
            throw new RuntimeException('Guru tidak ditemukan.');
        }

        $sql = 'UPDATE teachers SET
                    name = :name, gender = :gender, birth_place = :birth_place, birth_date = :birth_date,
                    address = :address, phone = :phone, email = :email, education = :education,
                    photo = :photo, is_active = :is_active
                WHERE id = :id';

        $pdo = db();
        try {
            $pdo->beginTransaction();
            $params = self::params($data, (string) $existing['code']);
            unset($params[':code']);
            $params[':id'] = $id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            self::syncSubjects($id, $subjectIds);
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
        $stmt = db()->prepare('UPDATE teachers SET is_active = :active WHERE id = :id');
        $stmt->execute([':active' => $active ? 1 : 0, ':id' => $id]);
    }

    public static function nextCode(): string
    {
        $prefix = 'GRU-' . date('Y') . '-';
        $stmt = db()->prepare('SELECT code FROM teachers WHERE code LIKE :prefix ORDER BY code DESC LIMIT 1');
        $stmt->execute([':prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();
        $sequence = 1;
        if (is_string($last) && preg_match('/-(\d+)$/', $last, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }
        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public static function hasGroups(int $id): bool
    {
        $stmt = db()->prepare('SELECT EXISTS(SELECT 1 FROM group_subjects WHERE teacher_id = :id)');
        $stmt->execute([':id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public static function hasSchedules(int $id): bool
    {
        $stmt = db()->prepare(
            'SELECT EXISTS(SELECT 1 FROM group_schedules gs
                           JOIN group_subjects gsub ON gsub.id = gs.group_subject_id
                           WHERE gsub.teacher_id = :id)'
        );
        $stmt->execute([':id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param array<int, int> $subjectIds
     */
    private static function syncSubjects(int $teacherId, array $subjectIds): void
    {
        $delete = db()->prepare('DELETE FROM teacher_subjects WHERE teacher_id = :id');
        $delete->execute([':id' => $teacherId]);

        $subjectIds = array_values(array_unique(array_filter(array_map('intval', $subjectIds), static fn (int $id): bool => $id > 0)));
        if ($subjectIds === []) {
            return;
        }

        $insert = db()->prepare(
            'INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (:teacher_id, :subject_id)'
        );
        foreach ($subjectIds as $subjectId) {
            $insert->execute([':teacher_id' => $teacherId, ':subject_id' => $subjectId]);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function params(array $data, string $code): array
    {
        $nullable = static fn ($value): ?string => ($value === null || $value === '') ? null : (string) $value;

        return [
            ':code'        => $code,
            ':name'        => (string) ($data['name'] ?? ''),
            ':gender'      => (string) ($data['gender'] ?? 'L'),
            ':birth_place' => $nullable($data['birth_place'] ?? null),
            ':birth_date'  => $nullable($data['birth_date'] ?? null),
            ':address'     => $nullable($data['address'] ?? null),
            ':phone'       => $nullable($data['phone'] ?? null),
            ':email'       => $nullable($data['email'] ?? null),
            ':education'   => $nullable($data['education'] ?? null),
            ':photo'       => $nullable($data['photo'] ?? null),
            ':is_active'   => !empty($data['is_active']) ? 1 : 0,
        ];
    }
}
