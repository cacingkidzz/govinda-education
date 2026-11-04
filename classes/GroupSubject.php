<?php
declare(strict_types=1);

/**
 * Relasi grup – mata pelajaran – guru.
 *
 * Satu grup boleh memiliki banyak mata pelajaran; satu mata pelajaran
 * hanya boleh terdaftar sekali dalam grup yang sama (dijaga unique index
 * uq_group_subjects_group_subject). Setiap mata pelajaran dalam grup
 * diampu oleh tepat satu guru, dan guru boleh berbeda antarmapel.
 */
final class GroupSubject
{
    /**
     * Daftar relasi dengan filter opsional.
     *
     * @param array{group_id?:string,subject_id?:string,teacher_id?:string,status?:string,q?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function all(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (($filters['group_id'] ?? '') !== '') {
            $where[] = 'gsub.group_id = :group_id';
            $params[':group_id'] = (int) $filters['group_id'];
        }
        if (($filters['subject_id'] ?? '') !== '') {
            $where[] = 'gsub.subject_id = :subject_id';
            $params[':subject_id'] = (int) $filters['subject_id'];
        }
        if (($filters['teacher_id'] ?? '') !== '') {
            $where[] = 'gsub.teacher_id = :teacher_id';
            $params[':teacher_id'] = (int) $filters['teacher_id'];
        }
        if (($filters['status'] ?? '') === '1' || ($filters['status'] ?? '') === '0') {
            $where[] = 'gsub.is_active = :status';
            $params[':status'] = (int) $filters['status'];
        }
        if (trim((string) ($filters['q'] ?? '')) !== '') {
            $where[] = '(g.name LIKE :q1 OR s.name LIKE :q2 OR t.name LIKE :q3)';
            $keyword = '%' . trim((string) $filters['q']) . '%';
            $params[':q1'] = $keyword;
            $params[':q2'] = $keyword;
            $params[':q3'] = $keyword;
        }

        $stmt = db()->prepare(
            'SELECT gsub.*, g.code AS group_code, g.name AS group_name, g.group_type,
                    g.capacity, g.is_active AS group_active,
                    s.code AS subject_code, s.name AS subject_name, s.is_active AS subject_active,
                    t.code AS teacher_code, t.name AS teacher_name, t.is_active AS teacher_active,
                    (SELECT COUNT(*) FROM group_schedules gs
                      WHERE gs.group_subject_id = gsub.id AND gs.is_active = 1) AS schedule_count
             FROM group_subjects gsub
             JOIN class_groups g ON g.id = gsub.group_id
             JOIN subjects s ON s.id = gsub.subject_id
             JOIN teachers t ON t.id = gsub.teacher_id'
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY g.name ASC, s.name ASC, gsub.id ASC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Relasi milik satu grup.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forGroup(int $groupId, bool $includeInactive = true): array
    {
        $sql = 'SELECT gsub.*, g.code AS group_code, g.name AS group_name, g.group_type,
                       g.capacity, g.is_active AS group_active,
                       s.code AS subject_code, s.name AS subject_name, s.is_active AS subject_active,
                       t.code AS teacher_code, t.name AS teacher_name, t.is_active AS teacher_active,
                       (SELECT COUNT(*) FROM group_schedules gs
                         WHERE gs.group_subject_id = gsub.id AND gs.is_active = 1) AS schedule_count
                FROM group_subjects gsub
                JOIN class_groups g ON g.id = gsub.group_id
                JOIN subjects s ON s.id = gsub.subject_id
                JOIN teachers t ON t.id = gsub.teacher_id
                WHERE gsub.group_id = :group_id'
            . ($includeInactive ? '' : ' AND gsub.is_active = 1')
            . ' ORDER BY gsub.is_active DESC, s.name ASC, gsub.id ASC';

        $stmt = db()->prepare($sql);
        $stmt->execute([':group_id' => $groupId]);
        return $stmt->fetchAll();
    }

    /**
     * Relasi aktif milik grup, hanya bila grup, mapel, dan guru aktif.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function activeForGroup(int $groupId): array
    {
        $stmt = db()->prepare(
            'SELECT gsub.*, s.name AS subject_name, s.code AS subject_code,
                    t.name AS teacher_name, t.code AS teacher_code,
                    (SELECT COUNT(*) FROM group_schedules gs
                      WHERE gs.group_subject_id = gsub.id AND gs.is_active = 1) AS schedule_count
             FROM group_subjects gsub
             JOIN class_groups g ON g.id = gsub.group_id
             JOIN subjects s ON s.id = gsub.subject_id
             JOIN teachers t ON t.id = gsub.teacher_id
             WHERE gsub.group_id = :group_id
               AND gsub.is_active = 1
               AND g.is_active = 1
               AND s.is_active = 1
               AND t.is_active = 1
             ORDER BY s.name ASC'
        );
        $stmt->execute([':group_id' => $groupId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT gsub.*, g.code AS group_code, g.name AS group_name, g.group_type,
                    g.capacity, g.is_active AS group_active,
                    s.code AS subject_code, s.name AS subject_name, s.is_active AS subject_active,
                    t.code AS teacher_code, t.name AS teacher_name, t.is_active AS teacher_active
             FROM group_subjects gsub
             JOIN class_groups g ON g.id = gsub.group_id
             JOIN subjects s ON s.id = gsub.subject_id
             JOIN teachers t ON t.id = gsub.teacher_id
             WHERE gsub.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Pilihan relasi untuk form jadwal (hanya relasi yang benar-benar aktif).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function activeOptions(?int $groupId = null): array
    {
        $sql = 'SELECT gsub.id, gsub.group_id, gsub.subject_id, gsub.teacher_id,
                       g.code AS group_code, g.name AS group_name,
                       s.name AS subject_name, t.name AS teacher_name
                FROM group_subjects gsub
                JOIN class_groups g ON g.id = gsub.group_id
                JOIN subjects s ON s.id = gsub.subject_id
                JOIN teachers t ON t.id = gsub.teacher_id
                WHERE gsub.is_active = 1
                  AND g.is_active = 1
                  AND s.is_active = 1
                  AND t.is_active = 1';
        $params = [];
        if ($groupId !== null && $groupId > 0) {
            $sql .= ' AND gsub.group_id = :group_id';
            $params[':group_id'] = $groupId;
        }
        $sql .= ' ORDER BY g.name ASC, s.name ASC';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $groupId = (int) ($data['group_id'] ?? 0);
        $subjectId = (int) ($data['subject_id'] ?? 0);
        $teacherId = (int) ($data['teacher_id'] ?? 0);

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $group = self::lockGroup($groupId);
            if ($group === null) {
                throw new DomainException('Group tidak ditemukan.');
            }
            if ((int) $group['is_active'] !== 1) {
                throw new DomainException('Group sedang nonaktif sehingga tidak dapat ditambah mata pelajaran.');
            }

            self::assertSubjectActive($subjectId);
            self::assertTeacherActive($teacherId);
            self::assertUniquePair($groupId, $subjectId);

            $stmt = $pdo->prepare(
                'INSERT INTO group_subjects (group_id, subject_id, teacher_id, is_active, description)
                 VALUES (:group_id, :subject_id, :teacher_id, :is_active, :description)'
            );
            $stmt->execute([
                ':group_id'    => $groupId,
                ':subject_id'  => $subjectId,
                ':teacher_id'  => $teacherId,
                ':is_active'   => !empty($data['is_active']) ? 1 : 0,
                ':description' => trim((string) ($data['description'] ?? '')) !== ''
                    ? mb_substr(trim((string) $data['description']), 0, 255)
                    : null,
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            return $id;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() === '23000') {
                throw new DomainException('Mata pelajaran tersebut sudah terdaftar pada group ini.');
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM group_subjects WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $existing = $stmt->fetch();
            if ($existing === false) {
                throw new DomainException('Relasi mata pelajaran tidak ditemukan.');
            }

            $groupId = (int) $existing['group_id'];
            self::lockGroup($groupId);

            $subjectId = (int) ($data['subject_id'] ?? 0);
            $teacherId = (int) ($data['teacher_id'] ?? 0);
            $isActive = !empty($data['is_active']) ? 1 : 0;

            self::assertSubjectActive($subjectId, (int) $existing['subject_id']);
            self::assertTeacherActive($teacherId, (int) $existing['teacher_id']);
            self::assertUniquePair($groupId, $subjectId, $id);

            // Guru berubah → periksa ulang seluruh jadwal aktif relasi ini.
            if ($teacherId !== (int) $existing['teacher_id'] && $isActive === 1) {
                $messages = Schedule::relationTeacherConflicts($id, $teacherId);
                if ($messages !== []) {
                    throw new DomainException(implode("\n", $messages));
                }
            }

            // Relasi diaktifkan kembali → jadwalnya kembali dihitung, periksa bentrok.
            if ($isActive === 1 && (int) $existing['is_active'] !== 1) {
                $messages = Schedule::relationConflictMessages($id);
                if ($messages !== []) {
                    throw new DomainException(implode("\n", $messages));
                }
            }

            $update = $pdo->prepare(
                'UPDATE group_subjects SET
                    subject_id = :subject_id, teacher_id = :teacher_id,
                    is_active = :is_active, description = :description
                 WHERE id = :id'
            );
            $update->execute([
                ':subject_id'  => $subjectId,
                ':teacher_id'  => $teacherId,
                ':is_active'   => $isActive,
                ':description' => trim((string) ($data['description'] ?? '')) !== ''
                    ? mb_substr(trim((string) $data['description']), 0, 255)
                    : null,
                ':id'          => $id,
            ]);

            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() === '23000') {
                throw new DomainException('Mata pelajaran tersebut sudah terdaftar pada group ini.');
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function setActive(int $id, bool $active): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM group_subjects WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $existing = $stmt->fetch();
            if ($existing === false) {
                throw new DomainException('Relasi mata pelajaran tidak ditemukan.');
            }

            if ($active) {
                $group = self::lockGroup((int) $existing['group_id']);
                if ($group === null || (int) $group['is_active'] !== 1) {
                    throw new DomainException('Group sedang nonaktif; relasi tidak dapat diaktifkan.');
                }
                self::assertSubjectActive((int) $existing['subject_id'], (int) $existing['subject_id']);
                self::assertTeacherActive((int) $existing['teacher_id'], (int) $existing['teacher_id']);

                $messages = Schedule::relationConflictMessages($id);
                if ($messages !== []) {
                    throw new DomainException(implode("\n", $messages));
                }
            }

            $update = $pdo->prepare('UPDATE group_subjects SET is_active = :active WHERE id = :id');
            $update->execute([':active' => $active ? 1 : 0, ':id' => $id]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Hapus permanen hanya bila belum pernah memiliki jadwal.
     * Relasi yang sudah dipakai harus dinonaktifkan, bukan dihapus.
     */
    public static function delete(int $id): void
    {
        $relation = self::find($id);
        if ($relation === null) {
            throw new DomainException('Relasi mata pelajaran tidak ditemukan.');
        }

        $stmt = db()->prepare('SELECT COUNT(*) FROM group_schedules WHERE group_subject_id = :id');
        $stmt->execute([':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new DomainException(
                'Mata pelajaran ini sudah memiliki riwayat jadwal sehingga tidak dapat dihapus permanen. '
                . 'Nonaktifkan relasinya bila tidak dipakai lagi.'
            );
        }

        $delete = db()->prepare('DELETE FROM group_subjects WHERE id = :id');
        $delete->execute([':id' => $id]);
    }

    public static function hasActiveSchedules(int $id): bool
    {
        $stmt = db()->prepare(
            'SELECT EXISTS(SELECT 1 FROM group_schedules WHERE group_subject_id = :id AND is_active = 1)'
        );
        $stmt->execute([':id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function lockGroup(int $groupId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM class_groups WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $groupId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function assertSubjectActive(int $subjectId, int $allowInactiveId = 0): void
    {
        $subject = $subjectId > 0 ? Subject::find($subjectId) : null;
        if ($subject === null) {
            throw new DomainException('Mata pelajaran wajib dipilih.');
        }
        if ((int) $subject['is_active'] !== 1 && $subjectId !== $allowInactiveId) {
            throw new DomainException('Mata pelajaran "' . $subject['name'] . '" sedang nonaktif.');
        }
    }

    private static function assertTeacherActive(int $teacherId, int $allowInactiveId = 0): void
    {
        $teacher = $teacherId > 0 ? Teacher::find($teacherId) : null;
        if ($teacher === null) {
            throw new DomainException('Guru wajib dipilih.');
        }
        if ((int) $teacher['is_active'] !== 1 && $teacherId !== $allowInactiveId) {
            throw new DomainException('Guru "' . $teacher['name'] . '" sedang nonaktif.');
        }
    }

    private static function assertUniquePair(int $groupId, int $subjectId, int $exceptId = 0): void
    {
        $sql = 'SELECT gsub.id, s.name AS subject_name
                FROM group_subjects gsub
                JOIN subjects s ON s.id = gsub.subject_id
                WHERE gsub.group_id = :group_id AND gsub.subject_id = :subject_id';
        $params = [':group_id' => $groupId, ':subject_id' => $subjectId];
        if ($exceptId > 0) {
            $sql .= ' AND gsub.id <> :except_id';
            $params[':except_id'] = $exceptId;
        }
        $sql .= ' LIMIT 1';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if ($row !== false) {
            throw new DomainException(
                'Mata pelajaran "' . $row['subject_name'] . '" sudah terdaftar pada group ini.'
            );
        }
    }
}
