<?php
declare(strict_types=1);

/**
 * Master data mata pelajaran.
 */
final class Subject
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function activeList(): array
    {
        return db()->query(
            'SELECT id, code, name, description FROM subjects WHERE is_active = 1 ORDER BY name ASC'
        )->fetchAll();
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
            $where = ' WHERE s.is_active = :status';
            $params[':status'] = (int) $filters['status'];
        }

        $stmt = db()->prepare(
            'SELECT s.*,
                    (SELECT COUNT(DISTINCT gsub.group_id) FROM group_subjects gsub
                      WHERE gsub.subject_id = s.id) AS group_count,
                    (SELECT COUNT(DISTINCT ts.teacher_id) FROM teacher_subjects ts
                      WHERE ts.subject_id = s.id) AS teacher_count
             FROM subjects s'
            . $where
            . ' ORDER BY s.name ASC, s.id ASC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM subjects WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function countAll(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM subjects')->fetchColumn();
    }

    public static function countActive(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM subjects WHERE is_active = 1')->fetchColumn();
    }

    public static function countInactive(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM subjects WHERE is_active = 0')->fetchColumn();
    }

    public static function nameExists(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT EXISTS(SELECT 1 FROM subjects WHERE name = :name';
        $params = [':name' => $name];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $exceptId;
        }
        $sql .= ')';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id:int,code:string}
     */
    public static function create(array $data): array
    {
        $sql = 'INSERT INTO subjects (code, name, description, is_active)
                VALUES (:code, :name, :description, :is_active)';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $code = self::nextCode();
            try {
                $stmt = db()->prepare($sql);
                $stmt->execute([
                    ':code'        => $code,
                    ':name'        => (string) ($data['name'] ?? ''),
                    ':description' => ($data['description'] ?? '') !== '' ? (string) $data['description'] : null,
                    ':is_active'   => !empty($data['is_active']) ? 1 : 0,
                ]);
                return ['id' => (int) db()->lastInsertId(), 'code' => $code];
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000' && $attempt < 5) {
                    continue;
                }
                throw $exception;
            }
        }

        throw new RuntimeException('Kode mata pelajaran tidak dapat dibuat.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE subjects SET name = :name, description = :description, is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute([
            ':name'        => (string) ($data['name'] ?? ''),
            ':description' => ($data['description'] ?? '') !== '' ? (string) $data['description'] : null,
            ':is_active'   => !empty($data['is_active']) ? 1 : 0,
            ':id'          => $id,
        ]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $stmt = db()->prepare('UPDATE subjects SET is_active = :active WHERE id = :id');
        $stmt->execute([':active' => $active ? 1 : 0, ':id' => $id]);
    }

    public static function nextCode(): string
    {
        $stmt = db()->query("SELECT code FROM subjects WHERE code LIKE 'MPL-%' ORDER BY code DESC LIMIT 1");
        $last = $stmt->fetchColumn();
        $sequence = 1;
        if (is_string($last) && preg_match('/-(\d+)$/', $last, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }
        return 'MPL-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Group yang memakai mata pelajaran ini.
     */
    public static function groups(int $id): array
    {
        $stmt = db()->prepare(
            'SELECT g.id, g.code, g.name, g.group_type, g.is_active,
                    t.name AS teacher_name, gsub.is_active AS relation_active,
                    (SELECT COUNT(*) FROM group_members gm
                      WHERE gm.group_id = g.id AND gm.end_date IS NULL) AS member_count
             FROM group_subjects gsub
             JOIN class_groups g ON g.id = gsub.group_id
             JOIN teachers t ON t.id = gsub.teacher_id
             WHERE gsub.subject_id = :id
             ORDER BY g.name ASC'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    public static function usageCount(int $id): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM group_subjects WHERE subject_id = :id');
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Nama guru yang mengampu mapel ini.
     */
    public static function teachers(int $id): array
    {
        $stmt = db()->prepare(
            'SELECT t.id, t.name, t.is_active
             FROM teacher_subjects ts
             JOIN teachers t ON t.id = ts.teacher_id
             WHERE ts.subject_id = :id
             ORDER BY t.name ASC'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }
}
