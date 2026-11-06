<?php
declare(strict_types=1);

/**
 * Realisasi pertemuan belajar.
 *
 * Satu baris class_meetings = satu kegiatan belajar pada satu tanggal.
 * Hanya status "terlaksana" yang dihitung ke paket belajar siswa.
 *
 * - Kehadiran siswa TIDAK memengaruhi hitungan paket. Selama kegiatan
 *   berlangsung, pertemuan dihitung untuk seluruh siswa yang menjadi
 *   anggota kelompok pada tanggal pelaksanaan.
 * - Jadwal yang dipindahkan dicatat sebagai status "dijadwalkan_ulang"
 *   pada tanggal asal dan dibuatkan pertemuan pengganti (rescheduled_from_id).
 * - Realisasi ganda dicegah UNIQUE (schedule_id, meeting_date) dan
 *   UNIQUE (group_subject_id, meeting_date, start_time) plus pengecekan
 *   riwayat student_package_meetings (satu siswa satu pertemuan).
 * - Riwayat tidak pernah dihapus; perubahan status selalu tercatat di log.
 */
final class Meeting
{
    public const STATUS_SCHEDULED = 'terjadwal';
    public const STATUS_DONE = 'terlaksana';
    public const STATUS_CANCELLED = 'dibatalkan';
    public const STATUS_RESCHEDULED = 'dijadwalkan_ulang';

    public const MAX_RANGE_DAYS = 92;

    /**
     * @return array<int, string>
     */
    public static function statusOptions(): array
    {
        return [self::STATUS_SCHEDULED, self::STATUS_DONE, self::STATUS_CANCELLED, self::STATUS_RESCHEDULED];
    }

    /**
     * Daftar pertemuan pada rentang tanggal: gabungan jadwal aktif
     * (occurrence) dengan realisasi yang sudah tercatat.
     *
     * @param array{group_id?:string,group_subject_id?:string,subject_id?:string,teacher_id?:string,status?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function occurrences(string $from, string $to, array $filters = []): array
    {
        if (!valid_date($from) || !valid_date($to)) {
            return [];
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $days = (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days;
        if ($days > self::MAX_RANGE_DAYS) {
            $to = (new DateTimeImmutable($from))->modify('+' . self::MAX_RANGE_DAYS . ' days')->format('Y-m-d');
        }

        $schedules = self::activeSchedules($filters);
        $recorded = self::recordedBetween($from, $to, $filters);

        $recordedByKey = [];
        foreach ($recorded as $row) {
            if ($row['schedule_id'] !== null) {
                $recordedByKey[(int) $row['schedule_id'] . '|' . (string) $row['meeting_date']] = $row;
            }
        }

        $rows = [];
        $usedKeys = [];
        $cursor = new DateTimeImmutable($from);
        $last = new DateTimeImmutable($to);

        while ($cursor <= $last) {
            $date = $cursor->format('Y-m-d');
            $weekday = (int) $cursor->format('N');
            foreach ($schedules as $schedule) {
                if ((int) $schedule['day_of_week'] !== $weekday) {
                    continue;
                }
                $key = (int) $schedule['schedule_id'] . '|' . $date;
                $usedKeys[$key] = true;
                $realization = $recordedByKey[$key] ?? null;

                $rows[] = self::buildOccurrence($schedule, $date, $realization);
            }
            $cursor = $cursor->modify('+1 day');
        }

        // Realisasi ad-hoc / pengganti / jadwal yang sudah tidak aktif.
        foreach ($recorded as $row) {
            if ($row['schedule_id'] !== null) {
                $key = (int) $row['schedule_id'] . '|' . (string) $row['meeting_date'];
                if (isset($usedKeys[$key])) {
                    continue;
                }
            }
            $rows[] = self::buildOccurrence([
                'schedule_id'      => $row['schedule_id'] !== null ? (int) $row['schedule_id'] : 0,
                'group_subject_id' => (int) $row['group_subject_id'],
                'group_id'         => (int) $row['group_id'],
                'teacher_id'       => (int) $row['teacher_id'],
                'day_of_week'      => (int) (new DateTimeImmutable((string) $row['meeting_date']))->format('N'),
                'start_time'       => (string) $row['start_time'],
                'end_time'         => (string) $row['end_time'],
                'room'             => (string) ($row['room'] ?? ''),
                'group_code'       => (string) ($row['group_code'] ?? ''),
                'group_name'       => (string) ($row['group_name'] ?? ''),
                'group_type'       => (string) ($row['group_type'] ?? 'group'),
                'subject_name'     => (string) ($row['subject_name'] ?? ''),
                'teacher_name'     => (string) ($row['teacher_name'] ?? ''),
                'member_count'     => (int) ($row['member_count'] ?? 0),
            ], (string) $row['meeting_date'], $row);
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, self::statusOptions(), true)) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => (string) $row['status'] === $status
            ));
        }

        usort($rows, static function (array $a, array $b): int {
            return [$a['meeting_date'], $a['start_time'], $a['group_name']]
                <=> [$b['meeting_date'], $b['start_time'], $b['group_name']];
        });

        return $rows;
    }

    /**
     * @param array<string, mixed> $schedule
     * @param array<string, mixed>|null $realization
     * @return array<string, mixed>
     */
    private static function buildOccurrence(array $schedule, string $date, ?array $realization): array
    {
        $status = $realization !== null ? (string) $realization['status'] : self::STATUS_SCHEDULED;

        return [
            'meeting_id'        => $realization !== null ? (int) $realization['id'] : null,
            'schedule_id'       => (int) $schedule['schedule_id'],
            'group_subject_id'  => (int) $schedule['group_subject_id'],
            'group_id'          => (int) $schedule['group_id'],
            'teacher_id'        => (int) $schedule['teacher_id'],
            'meeting_date'      => $date,
            'start_time'        => (string) $schedule['start_time'],
            'end_time'          => (string) $schedule['end_time'],
            'room'              => (string) ($schedule['room'] ?? ''),
            'group_code'        => (string) ($schedule['group_code'] ?? ''),
            'group_name'        => (string) ($schedule['group_name'] ?? ''),
            'group_type'        => (string) ($schedule['group_type'] ?? 'group'),
            'subject_name'      => (string) ($schedule['subject_name'] ?? ''),
            'teacher_name'      => (string) ($schedule['teacher_name'] ?? ''),
            'member_count'      => (int) ($schedule['member_count'] ?? 0),
            'status'            => $status,
            'notes'             => $realization !== null ? (string) ($realization['notes'] ?? '') : '',
            'rescheduled_from_id' => $realization !== null && $realization['rescheduled_from_id'] !== null
                ? (int) $realization['rescheduled_from_id'] : null,
            'recorded_by_name'  => $realization !== null ? (string) ($realization['recorded_by_name'] ?? '') : '',
            'affected_count'    => $realization !== null ? (int) ($realization['affected_count'] ?? 0) : 0,
            'has_realization'   => $realization !== null,
            'is_replacement'    => $realization !== null && $realization['rescheduled_from_id'] !== null,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private static function activeSchedules(array $filters): array
    {
        $where = ['gs.is_active = 1', 'gsub.is_active = 1', 'g.is_active = 1'];
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

        $stmt = db()->prepare(
            'SELECT gs.id AS schedule_id, gs.group_subject_id, gs.day_of_week,
                    gs.start_time, gs.end_time, gs.room,
                    gsub.group_id, gsub.subject_id, gsub.teacher_id,
                    g.code AS group_code, g.name AS group_name, g.group_type,
                    s.name AS subject_name, t.name AS teacher_name,
                    (SELECT COUNT(*) FROM group_members gm
                      WHERE gm.group_id = g.id AND gm.end_date IS NULL) AS member_count
             FROM group_schedules gs
             JOIN group_subjects gsub ON gsub.id = gs.group_subject_id
             JOIN class_groups g ON g.id = gsub.group_id
             JOIN subjects s ON s.id = gsub.subject_id
             JOIN teachers t ON t.id = gsub.teacher_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY gs.day_of_week ASC, gs.start_time ASC, g.name ASC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private static function recordedBetween(string $from, string $to, array $filters): array
    {
        $where = ['cm.meeting_date BETWEEN :from_date AND :to_date'];
        $params = [':from_date' => $from, ':to_date' => $to];

        if (($filters['group_id'] ?? '') !== '') {
            $where[] = 'cm.group_id = :group_id';
            $params[':group_id'] = (int) $filters['group_id'];
        }
        if (($filters['group_subject_id'] ?? '') !== '') {
            $where[] = 'cm.group_subject_id = :group_subject_id';
            $params[':group_subject_id'] = (int) $filters['group_subject_id'];
        }
        if (($filters['subject_id'] ?? '') !== '') {
            $where[] = 'gsub.subject_id = :subject_id';
            $params[':subject_id'] = (int) $filters['subject_id'];
        }
        if (($filters['teacher_id'] ?? '') !== '') {
            $where[] = 'cm.teacher_id = :teacher_id';
            $params[':teacher_id'] = (int) $filters['teacher_id'];
        }

        $stmt = db()->prepare(
            'SELECT cm.*, g.code AS group_code, g.name AS group_name, g.group_type,
                    s.name AS subject_name, t.name AS teacher_name, gs.room,
                    u.name AS recorded_by_name,
                    (SELECT COUNT(*) FROM student_package_meetings spm
                      WHERE spm.class_meeting_id = cm.id) AS affected_count,
                    (SELECT COUNT(*) FROM group_members gm
                      WHERE gm.group_id = cm.group_id AND gm.end_date IS NULL) AS member_count
             FROM class_meetings cm
             JOIN class_groups g ON g.id = cm.group_id
             JOIN group_subjects gsub ON gsub.id = cm.group_subject_id
             JOIN subjects s ON s.id = gsub.subject_id
             JOIN teachers t ON t.id = cm.teacher_id
             LEFT JOIN group_schedules gs ON gs.id = cm.schedule_id
             LEFT JOIN users u ON u.id = cm.updated_by
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY cm.meeting_date ASC, cm.start_time ASC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT cm.*, g.code AS group_code, g.name AS group_name, g.group_type, g.capacity,
                    gsub.subject_id, s.name AS subject_name, t.name AS teacher_name,
                    u.name AS recorded_by_name, gs.room,
                    src.meeting_date AS original_date, src.start_time AS original_start,
                    src.end_time AS original_end, src.status AS original_status,
                    (SELECT COUNT(*) FROM student_package_meetings spm
                      WHERE spm.class_meeting_id = cm.id) AS affected_count
             FROM class_meetings cm
             JOIN class_groups g ON g.id = cm.group_id
             JOIN group_subjects gsub ON gsub.id = cm.group_subject_id
             JOIN subjects s ON s.id = gsub.subject_id
             JOIN teachers t ON t.id = cm.teacher_id
             LEFT JOIN group_schedules gs ON gs.id = cm.schedule_id
             LEFT JOIN users u ON u.id = cm.updated_by
             LEFT JOIN class_meetings src ON src.id = cm.rescheduled_from_id
             WHERE cm.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Siswa yang terkena dampak bila pertemuan dicatat pada tanggal tertentu.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function previewStudents(int $groupId, string $meetingDate, int $meetingId = 0): array
    {
        if ($groupId <= 0 || !valid_date($meetingDate)) {
            return [];
        }

        $stmt = db()->prepare(
            "SELECT gm.id AS membership_id, gm.start_date AS membership_start,
                    s.id AS student_id, s.code AS student_code, s.name AS student_name, s.photo,
                    p.id AS package_id, p.package_number, p.completed_meetings,
                    p.target_meetings, p.status AS package_status,
                    (SELECT COUNT(*) FROM student_package_meetings spm
                      WHERE spm.class_meeting_id = :meeting_id
                        AND spm.student_id = s.id) AS already_counted
             FROM group_members gm
             JOIN students s ON s.id = gm.student_id
             LEFT JOIN student_packages p ON p.id = (
                 SELECT p2.id FROM student_packages p2
                 WHERE p2.group_member_id = gm.id
                   AND p2.status = 'aktif'
                 ORDER BY p2.package_number DESC LIMIT 1
             )
             WHERE gm.group_id = :group_id
               AND gm.start_date <= :meeting_date_start
               AND (gm.end_date IS NULL OR gm.end_date >= :meeting_date_end)
             ORDER BY s.name ASC"
        );
        $stmt->execute([
            ':group_id'          => $groupId,
            ':meeting_date_start' => $meetingDate,
            ':meeting_date_end'   => $meetingDate,
            ':meeting_id'        => $meetingId,
        ]);

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $completed = (int) ($row['completed_meetings'] ?? 0);
            $target = max(1, (int) ($row['target_meetings'] ?? StudentPackage::TARGET));
            $alreadyCounted = (int) $row['already_counted'] > 0;
            $status = (string) ($row['package_status'] ?? '');

            $row['before'] = $alreadyCounted ? max(0, $completed - 1) : $completed;
            $row['will_count'] = $status === StudentPackage::STATUS_AKTIF && !$alreadyCounted;
            $row['after'] = $alreadyCounted
                ? $completed
                : ($row['will_count'] ? min($target, $completed + 1) : $completed);
            $row['target'] = $target;
            $row['will_complete'] = $row['after'] >= $target && $completed < $target;
        }
        unset($row);

        return $rows;
    }

    /**
     * Catat atau ubah realisasi pertemuan.
     *
     * @param array<string, mixed> $data
     * @return array{ok:bool,error:string,meeting_id:int,counted:int,skipped:int,message:string}
     */
    public static function record(array $data): array
    {
        $scheduleId = (int) ($data['schedule_id'] ?? 0);
        $meetingId = (int) ($data['meeting_id'] ?? 0);
        $groupSubjectId = (int) ($data['group_subject_id'] ?? 0);
        $date = trim((string) ($data['meeting_date'] ?? ''));
        $start = self::normalizeTime((string) ($data['start_time'] ?? ''));
        $end = self::normalizeTime((string) ($data['end_time'] ?? ''));
        $status = (string) ($data['status'] ?? '');
        $notes = mb_substr(trim((string) ($data['notes'] ?? '')), 0, 255);
        $reason = mb_substr(trim((string) ($data['reason'] ?? '')), 0, 255);

        if (!in_array($status, self::statusOptions(), true)) {
            return self::fail('Status pertemuan tidak valid.');
        }
        if (!valid_date($date)) {
            return self::fail('Tanggal pelaksanaan wajib diisi dengan format yang benar.');
        }
        if ($start === '' || $end === '') {
            return self::fail('Jam mulai dan jam selesai wajib diisi.');
        }
        if ($end <= $start) {
            return self::fail('Jam selesai harus lebih besar dari jam mulai.');
        }
        if ($groupSubjectId <= 0) {
            return self::fail('Mata pelajaran kelompok tidak valid.');
        }

        $relation = GroupSubject::find($groupSubjectId);
        if ($relation === null) {
            return self::fail('Relasi kelompok–mata pelajaran tidak ditemukan.');
        }
        $groupId = (int) $relation['group_id'];
        $teacherId = (int) $relation['teacher_id'];

        $pdo = db();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }

        try {
            $lockGroup = $pdo->prepare('SELECT id FROM class_groups WHERE id = :id FOR UPDATE');
            $lockGroup->execute([':id' => $groupId]);

            $existing = null;
            if ($meetingId > 0) {
                $find = $pdo->prepare('SELECT * FROM class_meetings WHERE id = :id LIMIT 1 FOR UPDATE');
                $find->execute([':id' => $meetingId]);
                $existing = $find->fetch() ?: null;
                if ($existing === null) {
                    throw new DomainException('Realisasi pertemuan tidak ditemukan.');
                }
            } elseif ($scheduleId > 0) {
                $find = $pdo->prepare(
                    'SELECT * FROM class_meetings
                     WHERE schedule_id = :schedule_id AND meeting_date = :meeting_date
                     LIMIT 1 FOR UPDATE'
                );
                $find->execute([':schedule_id' => $scheduleId, ':meeting_date' => $date]);
                $existing = $find->fetch() ?: null;
            }

            $previousStatus = $existing !== null ? (string) $existing['status'] : null;
            $previousDate = $existing !== null ? (string) $existing['meeting_date'] : null;
            $previousStart = $existing !== null ? (string) $existing['start_time'] : null;
            $previousEnd = $existing !== null ? (string) $existing['end_time'] : null;

            if ($previousStatus === self::STATUS_DONE) {
                $changed = $status !== self::STATUS_DONE
                    || $date !== $previousDate
                    || $start !== $previousStart
                    || $end !== $previousEnd;
                if ($changed && mb_strlen($reason) < 3) {
                    throw new DomainException(
                        'Pertemuan ini sudah terlaksana. Perubahan atau pembatalan wajib disertai alasan minimal 3 karakter.'
                    );
                }
            }

            if ($status === self::STATUS_RESCHEDULED) {
                $replacementDate = trim((string) ($data['replacement_date'] ?? ''));
                if (!valid_date($replacementDate)) {
                    throw new DomainException('Tanggal pengganti wajib diisi untuk status dijadwalkan ulang.');
                }
                if ($replacementDate === $date) {
                    throw new DomainException('Tanggal pengganti harus berbeda dari tanggal jadwal asal.');
                }
            }

            $userId = self::actorId();
            if ($existing !== null) {
                $update = $pdo->prepare(
                    'UPDATE class_meetings SET
                        group_id = :group_id, group_subject_id = :group_subject_id,
                        teacher_id = :teacher_id, meeting_date = :meeting_date,
                        start_time = :start_time, end_time = :end_time,
                        status = :status, notes = :notes, updated_by = :updated_by
                     WHERE id = :id'
                );
                $update->execute([
                    ':group_id'         => $groupId,
                    ':group_subject_id' => $groupSubjectId,
                    ':teacher_id'       => $teacherId,
                    ':meeting_date'     => $date,
                    ':start_time'       => $start,
                    ':end_time'         => $end,
                    ':status'           => $status,
                    ':notes'            => $notes !== '' ? $notes : null,
                    ':updated_by'       => $userId,
                    ':id'               => $meetingId,
                ]);
                $meetingId = (int) $existing['id'];

                if ($previousDate !== null && $previousDate !== $date) {
                    $sync = $pdo->prepare(
                        'UPDATE student_package_meetings SET meeting_date = :date
                         WHERE class_meeting_id = :id'
                    );
                    $sync->execute([':date' => $date, ':id' => $meetingId]);
                }
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO class_meetings
                        (group_id, group_subject_id, teacher_id, schedule_id,
                         meeting_date, start_time, end_time, status, notes,
                         created_by, updated_by)
                     VALUES
                        (:group_id, :group_subject_id, :teacher_id, :schedule_id,
                         :meeting_date, :start_time, :end_time, :status, :notes,
                         :created_by, :updated_by)'
                );
                try {
                    $insert->execute([
                        ':group_id'         => $groupId,
                        ':group_subject_id' => $groupSubjectId,
                        ':teacher_id'       => $teacherId,
                        ':schedule_id'      => $scheduleId > 0 ? $scheduleId : null,
                        ':meeting_date'     => $date,
                        ':start_time'       => $start,
                        ':end_time'         => $end,
                        ':status'           => $status,
                        ':notes'            => $notes !== '' ? $notes : null,
                        ':created_by'       => $userId,
                        ':updated_by'       => $userId,
                    ]);
                } catch (PDOException $exception) {
                    if ((string) $exception->getCode() === '23000') {
                        throw new DomainException($scheduleId > 0
                            ? 'Realisasi untuk jadwal dan tanggal ini sudah tercatat. Muat ulang halaman daftar.'
                            : 'Sudah ada pertemuan pada mata pelajaran, tanggal, dan jam mulai yang sama. '
                                . 'Ubah jam mulai agar pertemuan lain di hari yang sama dapat disimpan.');
                    }
                    throw $exception;
                }
                $meetingId = (int) $pdo->lastInsertId();
            }

            $counted = 0;
            $skipped = 0;

            if ($status === self::STATUS_DONE && $previousStatus !== self::STATUS_DONE) {
                $result = StudentPackage::addMeeting($meetingId, $groupId, $date);
                if (!$result['ok']) {
                    throw new DomainException($result['error']);
                }
                $counted = (int) $result['counted'];
                $skipped = (int) $result['skipped'];
            } elseif ($status !== self::STATUS_DONE && $previousStatus === self::STATUS_DONE) {
                $result = StudentPackage::removeMeeting($meetingId);
                if (!$result['ok']) {
                    throw new DomainException($result['error']);
                }
            }

            $replacementId = 0;
            if ($status === self::STATUS_RESCHEDULED) {
                $replacementId = self::saveReplacement($meetingId, [
                    'group_id'         => $groupId,
                    'group_subject_id' => $groupSubjectId,
                    'teacher_id'       => $teacherId,
                    'replacement_date' => trim((string) ($data['replacement_date'] ?? '')),
                    'replacement_start' => self::normalizeTime((string) ($data['replacement_start'] ?? '')),
                    'replacement_end'  => self::normalizeTime((string) ($data['replacement_end'] ?? '')),
                    'notes'            => $notes,
                    'user_id'          => $userId,
                ]);
            }

            $activity = match ($status) {
                self::STATUS_DONE => $previousStatus === self::STATUS_DONE ? 'pertemuan_edit' : 'pertemuan_terlaksana',
                self::STATUS_CANCELLED => 'pertemuan_batal',
                self::STATUS_RESCHEDULED => 'pertemuan_pindah',
                default => 'pertemuan_terjadwal',
            };

            $description = 'Realisasi ' . (string) $relation['group_name'] . ' — '
                . (string) $relation['subject_name'] . ' pada ' . tanggal_id($date) . ' pukul '
                . substr($start, 0, 5) . '–' . substr($end, 0, 5) . ' berstatus '
                . meeting_status_label($status) . '.';
            if ($counted > 0) {
                $description .= ' Dihitung ke ' . $counted . ' paket siswa.';
            }
            if ($reason !== '') {
                $description .= ' Alasan: ' . $reason;
            }
            if ($replacementId > 0) {
                $description .= ' Pertemuan pengganti dicatat.';
            }

            ActivityLog::record($activity, $description, 'class_meeting', $meetingId);

            if ($own) {
                $pdo->commit();
            }

            $message = 'Realisasi pertemuan disimpan.';
            if ($counted > 0) {
                $message .= ' ' . $counted . ' paket siswa bertambah.';
            }
            if ($skipped > 0) {
                $message .= ' ' . $skipped . ' siswa belum memiliki paket aktif sehingga tidak dihitung.';
            }

            return [
                'ok'         => true,
                'error'      => '',
                'meeting_id' => $meetingId,
                'counted'    => $counted,
                'skipped'    => $skipped,
                'message'    => $message,
            ];
        } catch (DomainException $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return self::fail($exception->getMessage());
        } catch (Throwable $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('record', $exception);
            return self::fail('Realisasi pertemuan gagal disimpan. Silakan coba lagi.');
        }
    }

    /**
     * Simpan pertemuan pengganti untuk jadwal yang dipindahkan.
     *
     * @param array<string, mixed> $data
     */
    private static function saveReplacement(int $originalId, array $data): int
    {
        $pdo = db();
        $date = (string) $data['replacement_date'];
        $start = (string) $data['replacement_start'];
        $end = (string) $data['replacement_end'];

        $original = $pdo->prepare('SELECT * FROM class_meetings WHERE id = :id LIMIT 1 FOR UPDATE');
        $original->execute([':id' => $originalId]);
        $row = $original->fetch();
        if ($row === false) {
            throw new DomainException('Jadwal asal tidak ditemukan.');
        }
        if ($start === '') {
            $start = (string) $row['start_time'];
        }
        if ($end === '') {
            $end = (string) $row['end_time'];
        }
        if ($end <= $start) {
            throw new DomainException('Jam pertemuan pengganti tidak valid.');
        }

        $find = $pdo->prepare(
            "SELECT * FROM class_meetings
             WHERE rescheduled_from_id = :original_id AND status <> 'dibatalkan'
             ORDER BY id DESC LIMIT 1 FOR UPDATE"
        );
        $find->execute([':original_id' => $originalId]);
        $existing = $find->fetch();

        $notes = trim((string) ($data['notes'] ?? ''));
        $notesText = 'Pertemuan pengganti dari ' . tanggal_id((string) $row['meeting_date'])
            . ($notes !== '' ? ' — ' . $notes : '');

        if ($existing !== false) {
            if ((string) $existing['status'] === self::STATUS_DONE) {
                throw new DomainException(
                    'Pertemuan pengganti untuk jadwal ini sudah terlaksana dan tidak dapat diubah lagi.'
                );
            }
            $update = $pdo->prepare(
                'UPDATE class_meetings SET
                    meeting_date = :meeting_date, start_time = :start_time, end_time = :end_time,
                    notes = :notes, updated_by = :updated_by
                 WHERE id = :id'
            );
            $update->execute([
                ':meeting_date' => $date,
                ':start_time'   => $start,
                ':end_time'     => $end,
                ':notes'        => mb_substr($notesText, 0, 255),
                ':updated_by'   => (int) $data['user_id'],
                ':id'           => (int) $existing['id'],
            ]);
            return (int) $existing['id'];
        }

        $insert = $pdo->prepare(
            'INSERT INTO class_meetings
                (group_id, group_subject_id, teacher_id, schedule_id,
                 meeting_date, start_time, end_time, status, notes,
                 rescheduled_from_id, created_by, updated_by)
             VALUES
                (:group_id, :group_subject_id, :teacher_id, NULL,
                 :meeting_date, :start_time, :end_time, :status, :notes,
                 :rescheduled_from_id, :created_by, :updated_by)'
        );
        try {
            $insert->execute([
                ':group_id'            => (int) $data['group_id'],
                ':group_subject_id'    => (int) $data['group_subject_id'],
                ':teacher_id'          => (int) $data['teacher_id'],
                ':meeting_date'        => $date,
                ':start_time'          => $start,
                ':end_time'            => $end,
                ':status'              => self::STATUS_SCHEDULED,
                ':notes'               => mb_substr($notesText, 0, 255),
                ':rescheduled_from_id' => $originalId,
                ':created_by'          => (int) $data['user_id'],
                ':updated_by'          => (int) $data['user_id'],
            ]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new DomainException(
                    'Sudah ada pertemuan lain pada mata pelajaran, tanggal, dan jam yang sama. Pilih waktu lain.'
                );
            }
            throw $exception;
        }

        return (int) $pdo->lastInsertId();
    }

    /**
     * Daftar realisasi yang sudah tercatat (untuk laporan).
     *
     * @param array{date_from?:string,date_to?:string,group_id?:string,teacher_id?:string,subject_id?:string,status?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function recorded(array $filters = []): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (($filters['date_from'] ?? '') !== '' && valid_date((string) $filters['date_from'])) {
            $where[] = 'cm.meeting_date >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '' && valid_date((string) $filters['date_to'])) {
            $where[] = 'cm.meeting_date <= :date_to';
            $params[':date_to'] = (string) $filters['date_to'];
        }
        if (($filters['group_id'] ?? '') !== '') {
            $where[] = 'cm.group_id = :group_id';
            $params[':group_id'] = (int) $filters['group_id'];
        }
        if (($filters['teacher_id'] ?? '') !== '') {
            $where[] = 'cm.teacher_id = :teacher_id';
            $params[':teacher_id'] = (int) $filters['teacher_id'];
        }
        if (($filters['subject_id'] ?? '') !== '') {
            $where[] = 'gsub.subject_id = :subject_id';
            $params[':subject_id'] = (int) $filters['subject_id'];
        }
        if (in_array($filters['status'] ?? '', self::statusOptions(), true)) {
            $where[] = 'cm.status = :status';
            $params[':status'] = (string) $filters['status'];
        }

        $stmt = db()->prepare(
            'SELECT cm.id, cm.group_subject_id, cm.meeting_date, cm.start_time, cm.end_time, cm.status, cm.notes,
                    cm.rescheduled_from_id,
                    g.code AS group_code, g.name AS group_name, g.group_type,
                    s.name AS subject_name, t.name AS teacher_name,
                    u.name AS recorded_by_name,
                    (SELECT COUNT(*) FROM student_package_meetings spm
                      WHERE spm.class_meeting_id = cm.id) AS affected_count
             FROM class_meetings cm
             JOIN class_groups g ON g.id = cm.group_id
             JOIN group_subjects gsub ON gsub.id = cm.group_subject_id
             JOIN subjects s ON s.id = gsub.subject_id
             JOIN teachers t ON t.id = cm.teacher_id
             LEFT JOIN users u ON u.id = COALESCE(cm.updated_by, cm.created_by)
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY cm.meeting_date DESC, cm.start_time DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Rekap pertemuan per kelompok: jumlah per status dan tanggal-tanggalnya.
     *
     * Dipakai halaman "Rekap per Kelompok" agar mudah melihat berapa kali
     * sebuah kelompok sudah belajar dan pada tanggal berapa saja. Rekap
     * memakai data class_meetings apa adanya; tidak mengubah struktur tabel.
     *
     * @param array{group_id?:string,group_subject_id?:string,subject_id?:string,date_from?:string,date_to?:string,only_with_meetings?:bool} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function groupSummary(array $filters = []): array
    {
        $onlyWithMeetings = !empty($filters['only_with_meetings']);
        $wantedGroup = (int) ($filters['group_id'] ?? 0);
        $wantedSubject = (string) ($filters['subject_id'] ?? '');

        $summary = [];
        foreach (Group::all($wantedSubject !== '' ? ['subject_id' => $wantedSubject] : []) as $group) {
            $groupId = (int) $group['id'];
            if ($wantedGroup > 0 && $groupId !== $wantedGroup) {
                continue;
            }
            $summary[$groupId] = [
                'group'      => $group,
                'counts'     => array_fill_keys(self::statusOptions(), 0),
                'total'      => 0,
                'done'       => 0,
                'first_done' => null,
                'last_done'  => null,
                'subjects'   => [],
            ];
        }
        if ($summary === []) {
            return [];
        }

        $where = ['1 = 1'];
        $params = [];
        if (($filters['date_from'] ?? '') !== '' && valid_date((string) $filters['date_from'])) {
            $where[] = 'cm.meeting_date >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '' && valid_date((string) $filters['date_to'])) {
            $where[] = 'cm.meeting_date <= :date_to';
            $params[':date_to'] = (string) $filters['date_to'];
        }
        if (($filters['group_subject_id'] ?? '') !== '') {
            $where[] = 'cm.group_subject_id = :group_subject_id';
            $params[':group_subject_id'] = (int) $filters['group_subject_id'];
        }
        if ($wantedSubject !== '') {
            $where[] = 'gsub.subject_id = :subject_id';
            $params[':subject_id'] = (int) $wantedSubject;
        }

        $stmt = db()->prepare(
            'SELECT cm.id, cm.group_id, cm.group_subject_id, cm.meeting_date,
                    cm.start_time, cm.end_time, cm.status, cm.notes,
                    s.name AS subject_name, t.name AS teacher_name
             FROM class_meetings cm
             JOIN group_subjects gsub ON gsub.id = cm.group_subject_id
             JOIN subjects s ON s.id = gsub.subject_id
             JOIN teachers t ON t.id = cm.teacher_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY cm.meeting_date ASC, cm.start_time ASC'
        );
        $stmt->execute($params);

        foreach ($stmt->fetchAll() as $row) {
            $groupId = (int) $row['group_id'];
            if (!isset($summary[$groupId])) {
                continue;
            }

            $status = (string) $row['status'];
            $summary[$groupId]['total']++;
            $summary[$groupId]['counts'][$status] = (int) ($summary[$groupId]['counts'][$status] ?? 0) + 1;

            if ($status === self::STATUS_DONE) {
                $summary[$groupId]['done']++;
                $date = (string) $row['meeting_date'];
                if ($summary[$groupId]['first_done'] === null || $date < $summary[$groupId]['first_done']) {
                    $summary[$groupId]['first_done'] = $date;
                }
                if ($summary[$groupId]['last_done'] === null || $date > $summary[$groupId]['last_done']) {
                    $summary[$groupId]['last_done'] = $date;
                }
            }

            $relationId = (int) $row['group_subject_id'];
            if (!isset($summary[$groupId]['subjects'][$relationId])) {
                $summary[$groupId]['subjects'][$relationId] = [
                    'group_subject_id' => $relationId,
                    'subject_name'     => (string) $row['subject_name'],
                    'teacher_name'     => (string) $row['teacher_name'],
                    'counts'           => array_fill_keys(self::statusOptions(), 0),
                    'total'            => 0,
                    'done'             => 0,
                    'dates'            => [],
                ];
            }

            $subject = &$summary[$groupId]['subjects'][$relationId];
            $subject['total']++;
            $subject['counts'][$status] = (int) ($subject['counts'][$status] ?? 0) + 1;
            if ($status === self::STATUS_DONE) {
                $subject['done']++;
            }
            $subject['dates'][] = [
                'meeting_id'   => (int) $row['id'],
                'meeting_date' => (string) $row['meeting_date'],
                'start_time'   => (string) $row['start_time'],
                'end_time'     => (string) $row['end_time'],
                'status'       => $status,
                'notes'        => (string) ($row['notes'] ?? ''),
            ];
            unset($subject);
        }

        foreach ($summary as &$item) {
            usort(
                $item['subjects'],
                static fn (array $a, array $b): int => strcmp((string) $a['subject_name'], (string) $b['subject_name'])
            );
        }
        unset($item);

        if ($onlyWithMeetings) {
            $summary = array_filter(
                $summary,
                static fn (array $item): bool => (int) $item['total'] > 0
            );
        }

        return array_values($summary);
    }

    /**
     * @return array<string, int>
     */
    public static function statsBetween(string $from, string $to): array
    {
        $stmt = db()->prepare(
            'SELECT status, COUNT(*) AS total FROM class_meetings
             WHERE meeting_date BETWEEN :from_date AND :to_date
             GROUP BY status'
        );
        $stmt->execute([':from_date' => $from, ':to_date' => $to]);
        $result = array_fill_keys(self::statusOptions(), 0);
        foreach ($stmt->fetchAll() as $row) {
            $result[(string) $row['status']] = (int) $row['total'];
        }
        $result['total'] = array_sum($result);
        return $result;
    }

    public const STATUS_LABELS = [
        self::STATUS_SCHEDULED   => 'Terjadwal',
        self::STATUS_DONE        => 'Terlaksana',
        self::STATUS_CANCELLED   => 'Dibatalkan',
        self::STATUS_RESCHEDULED => 'Dijadwalkan Ulang',
    ];

    /**
     * Normalisasi "HH:MM" / "HH:MM:SS" menjadi "HH:MM:SS".
     */
    public static function normalizeTime(string $time): string
    {
        $time = trim($time);
        if (preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])(?::([0-5][0-9]))?$/', $time, $m) === 1) {
            return $m[1] . ':' . $m[2] . ':' . ($m[3] ?? '00');
        }
        return '';
    }

    private static function actorId(): ?int
    {
        $user = Auth::user();
        return $user !== null ? (int) $user['id'] : null;
    }

    /**
     * @return array{ok:false,error:string,meeting_id:int,counted:int,skipped:int,message:string}
     */
    private static function fail(string $message): array
    {
        return [
            'ok'         => false,
            'error'      => $message,
            'meeting_id' => 0,
            'counted'    => 0,
            'skipped'    => 0,
            'message'    => '',
        ];
    }

    private static function logError(string $action, Throwable $exception): void
    {
        @file_put_contents(
            LOG_PATH . '/app.log',
            '[' . date('Y-m-d H:i:s') . '] MEETING ' . strtoupper($action) . ': '
            . $exception->getMessage() . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
