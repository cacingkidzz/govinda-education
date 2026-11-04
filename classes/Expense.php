<?php
declare(strict_types=1);

/**
 * Pengeluaran operasional bimbel.
 *
 * - Nomor unik OUT-YYYYMM-#### dibuat otomatis di dalam transaksi.
 * - Data tidak pernah dihapus permanen; koreksi lewat pembatalan ber-audit.
 * - Hanya pengeluaran berstatus 'tercatat' yang dihitung ke kas & laporan.
 * - Petugas yang mencatat disimpan sebagai snapshot (recorded_by_name).
 */
final class Expense
{
    public const STATUS_RECORDED = 'tercatat';
    public const STATUS_CANCELLED = 'dibatalkan';

    public const METHOD_CASH = 'tunai';
    public const METHOD_TRANSFER = 'transfer';
    public const METHOD_QRIS = 'qris';
    public const METHOD_OTHER = 'lainnya';

    /** Batas nominal yang didukung kolom DECIMAL(12,2). */
    public const MAX_AMOUNT = 9999999999.99;

    /**
     * @return array<int, string>
     */
    public static function categoryOptions(): array
    {
        return [
            'honor_guru',
            'sewa',
            'listrik_internet',
            'atk',
            'transport',
            'konsumsi',
            'perawatan',
            'lainnya',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function methodOptions(): array
    {
        return [self::METHOD_CASH, self::METHOD_TRANSFER, self::METHOD_QRIS, self::METHOD_OTHER];
    }

    /**
     * @return array<int, string>
     */
    public static function statusOptions(): array
    {
        return [self::STATUS_RECORDED, self::STATUS_CANCELLED];
    }

    // -----------------------------------------------------------------
    // Tulis data
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $input
     * @return array{ok:bool,error:string,errors:array<string,string>,id:int,number:string}
     */
    public static function create(array $input): array
    {
        $validated = self::validate($input);
        if ($validated['errors'] !== []) {
            return self::fail('Periksa kembali data pengeluaran.', $validated['errors']);
        }
        $data = $validated['data'];

        $pdo = db();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }

        try {
            $number = self::nextNumber((string) $data['expense_date']);
            $actor = self::actor();

            $stmt = $pdo->prepare(
                'INSERT INTO expenses
                    (expense_number, expense_date, category, amount, method, payee,
                     reference_number, notes, status, recorded_by, recorded_by_name, created_at, updated_at)
                 VALUES
                    (:number, :date, :category, :amount, :method, :payee,
                     :reference, :notes, :status, :recorded_by, :recorded_by_name, NOW(), NOW())'
            );
            $stmt->execute([
                ':number'           => $number,
                ':date'             => (string) $data['expense_date'],
                ':category'         => (string) $data['category'],
                ':amount'           => (string) $data['amount'],
                ':method'           => (string) $data['method'],
                ':payee'            => (string) $data['payee'],
                ':reference'        => $data['reference_number'],
                ':notes'            => $data['notes'],
                ':status'           => self::STATUS_RECORDED,
                ':recorded_by'      => $actor['id'],
                ':recorded_by_name' => $actor['name'],
            ]);
            $id = (int) $pdo->lastInsertId();

            ActivityLog::record(
                'pengeluaran_tambah',
                'Pengeluaran ' . $number . ' dicatat (' . expense_category_label((string) $data['category'])
                    . ', ' . rupiah((float) $data['amount']) . ').',
                'expense',
                $id
            );

            if ($own) {
                $pdo->commit();
            }

            return [
                'ok'     => true,
                'error'  => '',
                'errors' => [],
                'id'     => $id,
                'number' => $number,
            ];
        } catch (Throwable $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('create', $exception);
            return self::fail('Pengeluaran gagal disimpan. Tidak ada data yang diubah.');
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok:bool,error:string,errors:array<string,string>,id:int,number:string}
     */
    public static function update(int $id, array $input): array
    {
        $validated = self::validate($input);
        if ($validated['errors'] !== []) {
            return self::fail('Periksa kembali data pengeluaran.', $validated['errors']);
        }
        $data = $validated['data'];

        $pdo = db();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }

        try {
            $lock = $pdo->prepare('SELECT * FROM expenses WHERE id = :id LIMIT 1 FOR UPDATE');
            $lock->execute([':id' => $id]);
            $current = $lock->fetch();
            if ($current === false) {
                if ($own && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return self::fail('Pengeluaran tidak ditemukan.');
            }
            if ((string) $current['status'] !== self::STATUS_RECORDED) {
                if ($own && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return self::fail('Pengeluaran yang sudah dibatalkan tidak dapat diubah.');
            }

            $stmt = $pdo->prepare(
                'UPDATE expenses
                    SET expense_date = :date, category = :category, amount = :amount,
                        method = :method, payee = :payee, reference_number = :reference,
                        notes = :notes, updated_at = NOW()
                  WHERE id = :id AND status = :status'
            );
            $stmt->execute([
                ':date'      => (string) $data['expense_date'],
                ':category'  => (string) $data['category'],
                ':amount'    => (string) $data['amount'],
                ':method'    => (string) $data['method'],
                ':payee'     => (string) $data['payee'],
                ':reference' => $data['reference_number'],
                ':notes'     => $data['notes'],
                ':id'        => $id,
                ':status'    => self::STATUS_RECORDED,
            ]);

            ActivityLog::record(
                'pengeluaran_edit',
                'Pengeluaran ' . $current['expense_number'] . ' diubah menjadi '
                    . expense_category_label((string) $data['category']) . ', '
                    . rupiah((float) $data['amount']) . '.',
                'expense',
                $id
            );

            if ($own) {
                $pdo->commit();
            }

            return [
                'ok'     => true,
                'error'  => '',
                'errors' => [],
                'id'     => $id,
                'number' => (string) $current['expense_number'],
            ];
        } catch (Throwable $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('update', $exception);
            return self::fail('Perubahan pengeluaran gagal disimpan. Tidak ada data yang diubah.');
        }
    }

    /**
     * Batalkan pengeluaran dengan alasan audit.
     *
     * @throws DomainException
     */
    public static function cancel(int $id, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new DomainException('Alasan pembatalan pengeluaran minimal 3 karakter.');
        }

        $pdo = db();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }

        try {
            $lock = $pdo->prepare('SELECT * FROM expenses WHERE id = :id LIMIT 1 FOR UPDATE');
            $lock->execute([':id' => $id]);
            $row = $lock->fetch();
            if ($row === false) {
                throw new DomainException('Pengeluaran tidak ditemukan.');
            }
            if ((string) $row['status'] === self::STATUS_CANCELLED) {
                if ($own && $pdo->inTransaction()) {
                    $pdo->commit();
                }
                return;
            }

            $actor = self::actor();
            $stmt = $pdo->prepare(
                "UPDATE expenses
                    SET status = 'dibatalkan', cancel_reason = :reason,
                        cancelled_at = NOW(), cancelled_by = :cancelled_by, updated_at = NOW()
                  WHERE id = :id AND status = 'tercatat'"
            );
            $stmt->execute([
                ':reason'       => mb_substr($reason, 0, 255),
                ':cancelled_by' => $actor['id'],
                ':id'           => $id,
            ]);

            ActivityLog::record(
                'pengeluaran_batal',
                'Pengeluaran ' . $row['expense_number'] . ' dibatalkan. Alasan: ' . $reason,
                'expense',
                $id
            );

            if ($own) {
                $pdo->commit();
            }
        } catch (DomainException $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('cancel', $exception);
            throw new DomainException('Pengeluaran gagal dibatalkan. Silakan coba lagi.');
        }
    }

    // -----------------------------------------------------------------
    // Baca data
    // -----------------------------------------------------------------

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT e.*,
                    COALESCE(u.name, e.recorded_by_name) AS officer_name,
                    cu.name AS cancelled_by_name
               FROM expenses e
               LEFT JOIN users u ON u.id = e.recorded_by
               LEFT JOIN users cu ON cu.id = e.cancelled_by
              WHERE e.id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array{date_from?:string,date_to?:string,category?:string,method?:string,status?:string,q?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function all(array $filters = []): array
    {
        [$where, $params] = self::buildWhere($filters);
        $stmt = db()->prepare(
            'SELECT e.*,
                    COALESCE(u.name, e.recorded_by_name) AS officer_name
               FROM expenses e
               LEFT JOIN users u ON u.id = e.recorded_by
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY e.expense_date DESC, e.id DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @param array{date_from?:string,date_to?:string,category?:string,method?:string,status?:string,q?:string} $filters
     * @return array{count:int,amount:float}
     */
    public static function summary(array $filters = []): array
    {
        [$where, $params] = self::buildWhere($filters);
        $stmt = db()->prepare(
            'SELECT COUNT(*) AS total_count, COALESCE(SUM(e.amount), 0) AS total_amount
               FROM expenses e
              WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'count'  => (int) ($row['total_count'] ?? 0),
            'amount' => (float) ($row['total_amount'] ?? 0),
        ];
    }

    /**
     * Rekap pengeluaran per kategori untuk filter aktif.
     *
     * @param array{date_from?:string,date_to?:string,category?:string,method?:string,status?:string,q?:string} $filters
     * @return array<int, array{category:string,count:int,amount:float}>
     */
    public static function byCategory(array $filters = []): array
    {
        [$where, $params] = self::buildWhere($filters);
        $stmt = db()->prepare(
            'SELECT e.category, COUNT(*) AS total_count, COALESCE(SUM(e.amount), 0) AS total_amount
               FROM expenses e
              WHERE ' . implode(' AND ', $where) . '
              GROUP BY e.category
              ORDER BY total_amount DESC, e.category ASC'
        );
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'category' => (string) $row['category'],
                'count'    => (int) $row['total_count'],
                'amount'   => (float) $row['total_amount'],
            ];
        }
        return $rows;
    }

    /**
     * Total pengeluaran per bulan (hanya status tercatat), selaras dengan
     * label bulan pada laporan keuangan.
     *
     * @return array{labels:array<int,string>,amounts:array<int,float>}
     */
    public static function monthlyTotals(int $months = 6): array
    {
        $months = max(1, min(12, $months));
        $start = (new DateTimeImmutable('first day of this month'))->modify('-' . ($months - 1) . ' months');
        $end = new DateTimeImmutable('last day of this month');

        $stmt = db()->prepare(
            "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, COALESCE(SUM(amount), 0) AS total
               FROM expenses
              WHERE status = 'tercatat'
                AND expense_date BETWEEN :start_date AND :end_date
              GROUP BY ym"
        );
        $stmt->execute([
            ':start_date' => $start->format('Y-m-d'),
            ':end_date'   => $end->format('Y-m-d'),
        ]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(string) $row['ym']] = (float) $row['total'];
        }

        $labels = [];
        $amounts = [];
        $cursor = $start;
        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');
            $labels[] = bulan_singkat_id((int) $cursor->format('n')) . ' ' . $cursor->format('Y');
            $amounts[] = $map[$key] ?? 0.0;
            $cursor = $cursor->modify('+1 month');
        }

        return ['labels' => $labels, 'amounts' => $amounts];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function recent(int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = db()->prepare(
            "SELECT e.*, COALESCE(u.name, e.recorded_by_name) AS officer_name
               FROM expenses e
               LEFT JOIN users u ON u.id = e.recorded_by
              WHERE e.status = 'tercatat'
              ORDER BY e.expense_date DESC, e.id DESC
              LIMIT " . $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // -----------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $input
     * @return array{errors:array<string,string>,data:array<string,mixed>}
     */
    private static function validate(array $input): array
    {
        $errors = [];

        $date = trim((string) ($input['expense_date'] ?? ''));
        if (!valid_date($date)) {
            $errors['expense_date'] = 'Tanggal pengeluaran wajib diisi dengan benar.';
        }

        $category = (string) ($input['category'] ?? '');
        if (!in_array($category, self::categoryOptions(), true)) {
            $errors['category'] = 'Kategori pengeluaran wajib dipilih.';
        }

        $amount = parse_currency((string) ($input['amount'] ?? ''));
        if ($amount === null || $amount <= 0) {
            $errors['amount'] = 'Nominal pengeluaran wajib diisi dan lebih besar dari nol.';
        } elseif ($amount > self::MAX_AMOUNT) {
            $errors['amount'] = 'Nominal pengeluaran melebihi batas yang didukung.';
        }

        $method = (string) ($input['method'] ?? '');
        if (!in_array($method, self::methodOptions(), true)) {
            $errors['method'] = 'Metode pembayaran wajib dipilih.';
        }

        $payee = mb_substr(trim((string) ($input['payee'] ?? '')), 0, 120);
        if ($payee === '') {
            $errors['payee'] = 'Penerima / vendor wajib diisi.';
        }

        $reference = mb_substr(trim((string) ($input['reference_number'] ?? '')), 0, 100);
        $notes = mb_substr(trim((string) ($input['notes'] ?? '')), 0, 500);

        return [
            'errors' => $errors,
            'data'   => [
                'expense_date'     => $date,
                'category'         => $category,
                'amount'           => $amount !== null ? round($amount, 2) : 0.0,
                'method'           => $method,
                'payee'            => $payee,
                'reference_number' => $reference !== '' ? $reference : null,
                'notes'            => $notes !== '' ? $notes : null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0:array<int,string>,1:array<string,mixed>}
     */
    private static function buildWhere(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, self::statusOptions(), true)) {
            $where[] = 'e.status = :status';
            $params[':status'] = $status;
        } elseif (empty($filters['include_cancelled'])) {
            // Default laporan: hanya pengeluaran sah yang mengurangi kas.
            $where[] = "e.status = 'tercatat'";
        }

        if (($filters['date_from'] ?? '') !== '' && valid_date((string) $filters['date_from'])) {
            $where[] = 'e.expense_date >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '' && valid_date((string) $filters['date_to'])) {
            $where[] = 'e.expense_date <= :date_to';
            $params[':date_to'] = (string) $filters['date_to'];
        }
        if (in_array($filters['category'] ?? '', self::categoryOptions(), true)) {
            $where[] = 'e.category = :category';
            $params[':category'] = (string) $filters['category'];
        }
        if (in_array($filters['method'] ?? '', self::methodOptions(), true)) {
            $where[] = 'e.method = :method';
            $params[':method'] = (string) $filters['method'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(e.expense_number LIKE :q1
                         OR e.payee LIKE :q2
                         OR e.reference_number LIKE :q3
                         OR e.notes LIKE :q4)';
            $keyword = '%' . (string) $filters['q'] . '%';
            $params[':q1'] = $keyword;
            $params[':q2'] = $keyword;
            $params[':q3'] = $keyword;
            $params[':q4'] = $keyword;
        }

        return [$where, $params];
    }

    private static function nextNumber(string $date): string
    {
        $prefix = 'OUT-' . (new DateTimeImmutable($date))->format('Ym') . '-';
        $stmt = db()->prepare(
            'SELECT expense_number FROM expenses
              WHERE expense_number LIKE :prefix
              ORDER BY expense_number DESC LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([':prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();
        $sequence = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }
        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{id:?int,name:string}
     */
    private static function actor(): array
    {
        $actor = current_user() ?? [];
        $id = isset($actor['id']) ? (int) $actor['id'] : null;
        $name = trim((string) ($actor['name'] ?? $actor['username'] ?? ''));
        return [
            'id'   => $id !== null && $id > 0 ? $id : null,
            'name' => $name !== '' ? mb_substr($name, 0, 120) : 'Sistem',
        ];
    }

    /**
     * @param array<string,string> $errors
     * @return array{ok:bool,error:string,errors:array<string,string>,id:int,number:string}
     */
    private static function fail(string $error, array $errors = []): array
    {
        return ['ok' => false, 'error' => $error, 'errors' => $errors, 'id' => 0, 'number' => ''];
    }

    private static function logError(string $context, Throwable $exception): void
    {
        @file_put_contents(
            LOG_PATH . '/app.log',
            '[' . date('Y-m-d H:i:s') . '] EXPENSE ' . $context . ': ' . $exception->getMessage() . PHP_EOL,
            FILE_APPEND
        );
    }
}
