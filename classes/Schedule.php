<?php
declare(strict_types=1);

/**
 * Jadwal belajar yang melekat pada relasi grup–mata pelajaran.
 *
 * Aturan:
 * - satu relasi boleh punya banyak jadwal (beberapa hari dalam seminggu);
 * - tidak boleh ada jadwal aktif yang bertumpukan untuk grup, guru,
 *   atau ruangan yang sama;
 * - jadwal bersifat fleksibel dan boleh diubah kapan saja mengikuti
 *   situasi group; tidak ada batas pertemuan per bulan;
 * - target 8 pertemuan adalah target paket belajar siswa, bukan batas
 *   jadwal mingguan/bulanan;
 * - jadwal nonaktif (atau relasi nonaktif) tidak dihitung pada bentrok.
 */
final class Schedule
{
    private const SLOT_TAKEN_MESSAGE = 'Sudah ada jadwal dengan mata pelajaran, hari, dan jam mulai yang sama '
        . '(termasuk jadwal nonaktif). Ubah jam mulai atau aktifkan kembali jadwal lama.';

    /**
     * @param array{group_id?:string,group_subject_id?:string,subject_id?:string,teacher_id?:string,day?:string,status?:string} $filters
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
        if (($filters['group_subject_id'] ?? '') !== '') {
            $where[] = 'gs.group_subject_id = :group_subject_id';
            $params[':group_subject_id'] = (int) $filters['group_subject_id'];
        }
        if (($filters['subject_id'] ?? '') !== '') {
            $where[] = 'gsub.subject_id = :subject_id';
            $params[':subject_id'] = (int) $filters['subject_id'];
        }
        if (($filters['teacher_id'] ?? '') !== '') {
            $where[] = 'gsub.teacher_id = :teacher_id';
            $params[':teacher_id'] = (int) $filters['teacher_id'];
        }
        if (($filters['day'] ?? '') !== '') {
            $where[] = 'gs.day_of_week = :day';
            $params[':day'] = (int) $filters['day'];
        }
        if (($filters['status'] ?? '') === '1' || ($filters['status'] ?? '') === '0') {
            $where[] = 'gs.is_active = :status';
            $params[':status'] = (int) $filters['status'];
        }

        $stmt = db()->prepare(
            self::baseSelect()
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY gs.day_of_week ASC, gs.start_time ASC, g.name ASC, s.name ASC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(self::baseSelect() . ' WHERE gs.id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forGroup(int $groupId, bool $onlyActive = true): array
    {
        $sql = self::baseSelect() . ' WHERE gsub.group_id = :group_id'
            . ($onlyActive ? ' AND gs.is_active = 1' : '')
            . ' ORDER BY s.name ASC, gs.day_of_week ASC, gs.start_time ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute([':group_id' => $groupId]);
        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forGroupSubject(int $groupSubjectId, bool $onlyActive = true): array
    {
        $sql = self::baseSelect() . ' WHERE gs.group_subject_id = :group_subject_id'
            . ($onlyActive ? ' AND gs.is_active = 1' : '')
            . ' ORDER BY gs.day_of_week ASC, gs.start_time ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute([':group_subject_id' => $groupSubjectId]);
        return $stmt->fetchAll();
    }

    /**
     * Ringkasan jadwal aktif per group untuk kolom daftar group.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function activeByGroup(): array
    {
        $rows = db()->query(
            'SELECT gsub.group_id, gs.day_of_week, gs.start_time, gs.end_time, gs.room,
                    s.name AS subject_name, t.name AS teacher_name
             FROM group_schedules gs
             JOIN group_subjects gsub ON gsub.id = gs.group_subject_id
             JOIN subjects s ON s.id = gsub.subject_id
             JOIN teachers t ON t.id = gsub.teacher_id
             WHERE gs.is_active = 1 AND gsub.is_active = 1
             ORDER BY gs.day_of_week ASC, gs.start_time ASC'
        )->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['group_id']][] = $row;
        }
        return $map;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $data = self::withContext($data);
        $groupId = (int) ($data['group_id'] ?? 0);

        $pdo = db();
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        try {
            // Kunci grup agar pemeriksaan bentrok berjalan serial per grup.
            self::lockGroup($groupId);
            self::assertSlotFree($data, 0);
            if (!empty($data['is_active'])) {
                $messages = self::conflictMessages($data, 0);
                if ($messages !== []) {
                    throw new DomainException(implode("\n", $messages));
                }
            }

            $stmt = $pdo->prepare(
                'INSERT INTO group_schedules
                    (group_subject_id, day_of_week, start_time, end_time, room, notes, is_active)
                 VALUES
                    (:group_subject_id, :day, :start_time, :end_time, :room, :notes, :is_active)'
            );
            $stmt->execute(self::params($data));
            $id = (int) $pdo->lastInsertId();

            if ($ownTransaction) {
                $pdo->commit();
            }
            return $id;
        } catch (Throwable $exception) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                throw new DomainException(self::SLOT_TAKEN_MESSAGE);
            }
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $data = self::withContext($data);
        $groupId = (int) ($data['group_id'] ?? 0);

        $pdo = db();
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        try {
            self::lockGroup($groupId);
            self::assertSlotFree($data, $id);
            if (!empty($data['is_active'])) {
                $messages = self::conflictMessages($data, $id);
                if ($messages !== []) {
                    throw new DomainException(implode("\n", $messages));
                }
            }

            $stmt = $pdo->prepare(
                'UPDATE group_schedules SET
                    group_subject_id = :group_subject_id, day_of_week = :day,
                    start_time = :start_time, end_time = :end_time,
                    room = :room, notes = :notes, is_active = :is_active
                 WHERE id = :id'
            );
            $stmt->execute(self::params($data, $id));

            if ($ownTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                throw new DomainException(self::SLOT_TAKEN_MESSAGE);
            }
            throw $exception;
        }
    }

    public static function setActive(int $id, bool $active): void
    {
        $pdo = db();
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $schedule = self::find($id);
            if ($schedule === null) {
                throw new DomainException('Jadwal tidak ditemukan.');
            }
            self::lockGroup((int) $schedule['group_id']);

            if ($active) {
                $messages = self::conflictMessages([
                    'group_subject_id' => (int) $schedule['group_subject_id'],
                    'group_id'         => (int) $schedule['group_id'],
                    'group_name'       => (string) $schedule['group_name'],
                    'subject_name'     => (string) $schedule['subject_name'],
                    'teacher_id'       => (int) $schedule['teacher_id'],
                    'teacher_name'     => (string) $schedule['teacher_name'],
                    'day_of_week'      => (int) $schedule['day_of_week'],
                    'start_time'       => (string) $schedule['start_time'],
                    'end_time'         => (string) $schedule['end_time'],
                    'room'             => (string) ($schedule['room'] ?? ''),
                ], $id);
                if ($messages !== []) {
                    throw new DomainException(implode("\n", $messages));
                }
            }

            $stmt = $pdo->prepare('UPDATE group_schedules SET is_active = :active WHERE id = :id');
            $stmt->execute([':active' => $active ? 1 : 0, ':id' => $id]);

            if ($ownTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Hapus permanen hanya untuk jadwal yang sudah nonaktif dan tidak
     * memiliki riwayat pertemuan (modul pertemuan menyusul di Stage 4).
     */
    public static function delete(int $id): void
    {
        $schedule = self::find($id);
        if ($schedule === null) {
            throw new DomainException('Jadwal tidak ditemukan.');
        }
        if ((int) $schedule['is_active'] === 1) {
            throw new DomainException('Jadwal aktif tidak dapat dihapus. Nonaktifkan terlebih dahulu.');
        }

        $stmt = db()->prepare('DELETE FROM group_schedules WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * Daftar pesan bentrok (grup, guru, ruangan).
     *
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    public static function conflictMessages(array $data, int $excludeId = 0): array
    {
        $messages = [];

        $day = (int) ($data['day_of_week'] ?? 0);
        $start = (string) ($data['start_time'] ?? '');
        $end = (string) ($data['end_time'] ?? '');
        $groupName = (string) ($data['group_name'] ?? 'group ini');
        $subjectName = (string) ($data['subject_name'] ?? '');

        $groupConflict = self::queryConflict(
            'gsub.group_id = :group_id',
            [':group_id' => (int) ($data['group_id'] ?? 0)],
            $day,
            $start,
            $end,
            $excludeId
        );
        if ($groupConflict !== null) {
            $messages[] = 'Group ' . $groupName . ' sudah memiliki jadwal '
                . ($groupConflict['subject_name'] !== '' ? $groupConflict['subject_name'] . ' ' : '')
                . 'pada ' . hari_id($day) . ' '
                . self::timeLabel((string) $groupConflict['start_time'])
                . '–' . self::timeLabel((string) $groupConflict['end_time']) . '.';
        }

        if (!empty($data['teacher_id'])) {
            $teacherConflict = self::queryConflict(
                'gsub.teacher_id = :teacher_id',
                [':teacher_id' => (int) $data['teacher_id']],
                $day,
                $start,
                $end,
                $excludeId
            );
            if ($teacherConflict !== null) {
                $messages[] = 'Guru ' . $teacherConflict['teacher_name'] . ' sudah mengajar '
                    . $teacherConflict['group_name'] . ' (' . $teacherConflict['subject_name'] . ') pada '
                    . hari_id($day) . ' '
                    . self::timeLabel((string) $teacherConflict['start_time'])
                    . '–' . self::timeLabel((string) $teacherConflict['end_time']) . '.';
            }
        }

        $room = trim((string) ($data['room'] ?? ''));
        if ($room !== '') {
            $roomConflict = self::queryConflict(
                "LOWER(TRIM(gs.room)) = LOWER(TRIM(:room)) AND gs.room IS NOT NULL AND gs.room <> ''",
                [':room' => $room],
                $day,
                $start,
                $end,
                $excludeId
            );
            if ($roomConflict !== null) {
                $messages[] = 'Ruangan ' . $roomConflict['room'] . ' sudah dipakai '
                    . $roomConflict['group_name'] . ' (' . $roomConflict['subject_name'] . ') pada '
                    . hari_id($day) . ' '
                    . self::timeLabel((string) $roomConflict['start_time'])
                    . '–' . self::timeLabel((string) $roomConflict['end_time']) . '.';
            }
        }

        return $messages;
    }

    /**
     * Seluruh jadwal aktif pada satu relasi diperiksa ulang (mis. saat
     * relasi diaktifkan kembali).
     *
     * @return array<int, string>
     */
    public static function relationConflictMessages(int $groupSubjectId): array
    {
        $relation = GroupSubject::find($groupSubjectId);
        if ($relation === null) {
            return [];
        }
        $messages = [];
        foreach (self::forGroupSubject($groupSubjectId, true) as $schedule) {
            foreach (self::conflictMessages(self::dataFromRow($schedule, $relation), (int) $schedule['id']) as $message) {
                $messages[$message] = $message;
            }
        }
        return array_values($messages);
    }

    /**
     * Pemeriksaan bentrok guru saja, dipakai saat guru pada relasi diubah.
     *
     * @return array<int, string>
     */
    public static function relationTeacherConflicts(int $groupSubjectId, int $teacherId): array
    {
        $messages = [];
        foreach (self::forGroupSubject($groupSubjectId, true) as $schedule) {
            $conflict = self::queryConflict(
                'gsub.teacher_id = :teacher_id',
                [':teacher_id' => $teacherId],
                (int) $schedule['day_of_week'],
                (string) $schedule['start_time'],
                (string) $schedule['end_time'],
                (int) $schedule['id']
            );
            if ($conflict !== null) {
                $teacherName = (string) ($conflict['teacher_name'] ?? '');
                $messages[] = 'Guru ' . $teacherName . ' sudah mengajar ' . $conflict['group_name']
                    . ' (' . $conflict['subject_name'] . ') pada '
                    . hari_id((int) $schedule['day_of_week']) . ' '
                    . self::timeLabel((string) $conflict['start_time'])
                    . '–' . self::timeLabel((string) $conflict['end_time']) . '.';
            }
        }
        return array_values(array_unique($messages));
    }

    /**
     * Normalisasi input jam menjadi HH:MM:SS, atau null bila tidak valid.
     */
    public static function normalizeTime(string $time): ?string
    {
        $time = trim($time);
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $time, $matches) !== 1) {
            return null;
        }
        return $matches[1] . ':' . $matches[2] . ':00';
    }

    public static function timeLabel(?string $time): string
    {
        return $time !== null && $time !== '' ? substr($time, 0, 5) : '-';
    }

    private static function baseSelect(): string
    {
        return 'SELECT gs.*, gsub.group_id, gsub.subject_id, gsub.teacher_id,
                       gsub.is_active AS relation_active,
                       g.code AS group_code, g.name AS group_name, g.group_type,
                       g.capacity, g.is_active AS group_active,
                       s.code AS subject_code, s.name AS subject_name, s.is_active AS subject_active,
                       t.code AS teacher_code, t.name AS teacher_name, t.is_active AS teacher_active,
                       (SELECT COUNT(*) FROM group_members gm
                         WHERE gm.group_id = g.id AND gm.end_date IS NULL) AS member_count
                FROM group_schedules gs
                JOIN group_subjects gsub ON gsub.id = gs.group_subject_id
                JOIN class_groups g ON g.id = gsub.group_id
                JOIN subjects s ON s.id = gsub.subject_id
                JOIN teachers t ON t.id = gsub.teacher_id';
    }

    /**
     * Unique key (group_subject_id, hari, jam mulai) juga berlaku untuk
     * jadwal nonaktif.
     *
     * @param array<string, mixed> $data
     */
    private static function assertSlotFree(array $data, int $excludeId): void
    {
        $sql = 'SELECT id FROM group_schedules
                WHERE group_subject_id = :group_subject_id
                  AND day_of_week = :day
                  AND start_time = :start_time';
        $params = [
            ':group_subject_id' => (int) ($data['group_subject_id'] ?? 0),
            ':day'              => (int) ($data['day_of_week'] ?? 0),
            ':start_time'       => (string) ($data['start_time'] ?? ''),
        ];
        if ($excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch() !== false) {
            throw new DomainException(self::SLOT_TAKEN_MESSAGE);
        }
    }

    /**
     * Kunci baris grup agar pemeriksaan bentrok berjalan serial per grup.
     */
    private static function lockGroup(int $groupId): void
    {
        if ($groupId <= 0) {
            return;
        }
        $stmt = db()->prepare('SELECT id FROM class_groups WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $groupId]);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private static function queryConflict(
        string $condition,
        array $params,
        int $day,
        string $start,
        string $end,
        int $excludeId
    ): ?array {
        $sql = 'SELECT gs.start_time, gs.end_time, gs.room,
                       g.name AS group_name, s.name AS subject_name,
                       gsub.teacher_id, t.name AS teacher_name
                FROM group_schedules gs
                JOIN group_subjects gsub ON gsub.id = gs.group_subject_id
                JOIN class_groups g ON g.id = gsub.group_id
                JOIN subjects s ON s.id = gsub.subject_id
                JOIN teachers t ON t.id = gsub.teacher_id
                WHERE gs.is_active = 1
                  AND gsub.is_active = 1
                  AND g.is_active = 1
                  AND gs.day_of_week = :day
                  AND gs.start_time < :end_time
                  AND gs.end_time > :start_time
                  AND ' . $condition;
        if ($excludeId > 0) {
            $sql .= ' AND gs.id <> :exclude_id';
        }
        $sql .= ' ORDER BY gs.start_time ASC LIMIT 1';

        $stmt = db()->prepare($sql);
        $bind = $params + [
            ':day'        => $day,
            ':start_time' => $start,
            ':end_time'   => $end,
        ];
        if ($excludeId > 0) {
            $bind[':exclude_id'] = $excludeId;
        }
        $stmt->execute($bind);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Lengkapi data jadwal dengan konteks relasi (group, mapel, guru) agar
     * pemeriksaan bentrok tidak bergantung pada pemanggil.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function withContext(array $data): array
    {
        $relationId = (int) ($data['group_subject_id'] ?? 0);
        if ($relationId <= 0) {
            return $data;
        }
        $relation = GroupSubject::find($relationId);
        if ($relation === null) {
            return $data;
        }
        $data['group_id'] = $data['group_id'] ?? (int) $relation['group_id'];
        $data['group_name'] = $data['group_name'] ?? (string) $relation['group_name'];
        $data['subject_name'] = $data['subject_name'] ?? (string) $relation['subject_name'];
        $data['teacher_id'] = $data['teacher_id'] ?? (int) $relation['teacher_id'];
        $data['teacher_name'] = $data['teacher_name'] ?? (string) $relation['teacher_name'];
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function params(array $data, int $id = 0): array
    {
        $params = [
            ':group_subject_id' => (int) ($data['group_subject_id'] ?? 0),
            ':day'              => (int) ($data['day_of_week'] ?? 0),
            ':start_time'       => (string) ($data['start_time'] ?? ''),
            ':end_time'         => (string) ($data['end_time'] ?? ''),
            ':room'             => trim((string) ($data['room'] ?? '')) !== '' ? trim((string) $data['room']) : null,
            ':notes'            => trim((string) ($data['notes'] ?? '')) !== '' ? trim((string) $data['notes']) : null,
            ':is_active'        => !empty($data['is_active']) ? 1 : 0,
        ];
        if ($id > 0) {
            $params[':id'] = $id;
        }
        return $params;
    }

    /**
     * @param array<string, mixed> $schedule
     * @param array<string, mixed> $relation
     * @return array<string, mixed>
     */
    private static function dataFromRow(array $schedule, array $relation): array
    {
        return [
            'group_subject_id' => (int) $schedule['group_subject_id'],
            'group_id'         => (int) $relation['group_id'],
            'group_name'       => (string) $relation['group_name'],
            'subject_name'     => (string) $relation['subject_name'],
            'teacher_id'       => (int) $relation['teacher_id'],
            'teacher_name'     => (string) $relation['teacher_name'],
            'day_of_week'      => (int) $schedule['day_of_week'],
            'start_time'       => (string) $schedule['start_time'],
            'end_time'         => (string) $schedule['end_time'],
            'room'             => (string) ($schedule['room'] ?? ''),
        ];
    }
}
