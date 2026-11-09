<?php
declare(strict_types=1);

/**
 * Pembayaran paket belajar — WAJIB LUNAS per tagihan.
 *
 * Tidak ada cicilan dan tidak ada pembayaran sebagian:
 * - setiap tagihan yang dipilih harus dibayar sama persis dengan totalnya;
 * - satu pembayaran boleh melunasi beberapa tagihan sekaligus;
 * - rincian per tagihan disimpan di payment_allocations;
 * - satu tagihan hanya boleh memiliki satu alokasi pembayaran berhasil
 *   (UNIQUE invoice_id) dan pemrosesan memakai SELECT ... FOR UPDATE;
 * - pembayaran TIDAK mengaktifkan paket dan tidak mengubah hitungan
 *   pertemuan; paket berikutnya sudah aktif sejak pertemuan ke-8;
 * - nomor nota unik (NOTA-YYYYMM-####) dan menjadi bukti historis.
 */
final class Payment
{
    public const METHOD_CASH = 'tunai';
    public const METHOD_TRANSFER = 'transfer';
    public const METHOD_QRIS = 'qris';
    public const METHOD_OTHER = 'lainnya';

    public const STATUS_SUCCESS = 'berhasil';
    public const STATUS_CANCELLED = 'dibatalkan';

    /**
     * @return array<int, string>
     */
    public static function methodOptions(): array
    {
        return [self::METHOD_CASH, self::METHOD_TRANSFER, self::METHOD_QRIS, self::METHOD_OTHER];
    }

    /**
     * Pelunasan satu tagihan (pembungkus payMany).
     *
     * @param array{amount:float|string,method:string,payment_date:string,reference?:string,notes?:string} $data
     * @return array{ok:bool,error:string,payment_id:int,payment_number:string,invoice_count:int}
     */
    public static function pay(int $invoiceId, array $data): array
    {
        return self::payMany([$invoiceId], $data);
    }

    /**
     * Simpan pelunasan penuh untuk satu atau beberapa tagihan.
     *
     * Semua tagihan harus milik siswa yang sama dan berstatus belum_bayar.
     * Jumlah pembayaran harus sama persis dengan jumlah total seluruh
     * tagihan yang dipilih; setiap tagihan mendapat alokasi penuh.
     *
     * @param array<int, int> $invoiceIds
     * @param array{amount:float|string,method:string,payment_date:string,reference?:string,notes?:string} $data
     * @return array{ok:bool,error:string,payment_id:int,payment_number:string,invoice_count:int}
     */
    public static function payMany(array $invoiceIds, array $data): array
    {
        $method = (string) ($data['method'] ?? '');
        $paymentDate = trim((string) ($data['payment_date'] ?? ''));
        $reference = mb_substr(trim((string) ($data['reference'] ?? '')), 0, 100);
        $notes = mb_substr(trim((string) ($data['notes'] ?? '')), 0, 500);
        $amountRaw = $data['amount'] ?? '';

        $ids = [];
        foreach ($invoiceIds as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);
        sort($ids, SORT_NUMERIC);

        if ($ids === []) {
            return self::fail('Pilih minimal satu tagihan yang akan dilunasi.');
        }
        if (count($ids) > 50) {
            return self::fail('Maksimal 50 tagihan dalam satu pembayaran.');
        }
        if (!in_array($method, self::methodOptions(), true)) {
            return self::fail('Metode pembayaran tidak valid.');
        }
        if (!valid_date($paymentDate)) {
            return self::fail('Tanggal pembayaran wajib diisi dengan benar.');
        }
        if ($paymentDate > date('Y-m-d')) {
            return self::fail('Tanggal pembayaran tidak boleh melebihi hari ini.');
        }

        $amount = is_string($amountRaw) ? parse_currency($amountRaw) : round((float) $amountRaw, 2);
        if ($amount === null || $amount <= 0) {
            return self::fail('Jumlah pembayaran wajib diisi dan lebih besar dari nol.');
        }

        $user = Auth::user();
        if ($user === null) {
            return self::fail('Sesi pengguna berakhir. Silakan login kembali.');
        }
        $userId = (int) $user['id'];

        $pdo = db();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }

        try {
            $placeholders = [];
            $params = [];
            foreach ($ids as $index => $id) {
                $key = ':id' . $index;
                $placeholders[] = $key;
                $params[$key] = $id;
            }

            // Kunci semua tagihan dalam urutan id menaik agar konsisten.
            $lock = $pdo->prepare(
                'SELECT * FROM invoices WHERE id IN (' . implode(', ', $placeholders) . ')
                 ORDER BY id ASC FOR UPDATE'
            );
            $lock->execute($params);
            $invoiceRows = $lock->fetchAll();
            if (count($invoiceRows) !== count($ids)) {
                throw new DomainException('Sebagian tagihan tidak ditemukan. Muat ulang halaman lalu coba lagi.');
            }

            $studentId = null;
            $total = 0.0;
            $invoiceNumbers = [];
            foreach ($invoiceRows as $invoice) {
                $invoiceId = (int) $invoice['id'];
                if ((string) $invoice['status'] === 'lunas') {
                    throw new DomainException(
                        'Tagihan ' . $invoice['invoice_number'] . ' sudah lunas. Pembayaran ganda tidak diperbolehkan.'
                    );
                }
                if ((string) $invoice['status'] === 'dibatalkan') {
                    throw new DomainException(
                        'Tagihan ' . $invoice['invoice_number'] . ' dibatalkan sehingga tidak dapat dibayar.'
                    );
                }
                $currentStudent = (int) $invoice['student_id'];
                if ($studentId === null) {
                    $studentId = $currentStudent;
                } elseif ($studentId !== $currentStudent) {
                    throw new DomainException(
                        'Semua tagihan dalam satu pembayaran harus milik siswa yang sama.'
                    );
                }
                $total += round((float) $invoice['total_amount'], 2);
                $invoiceNumbers[] = (string) $invoice['invoice_number'];
            }
            $total = round($total, 2);

            if ((int) round($amount * 100) !== (int) round($total * 100)) {
                throw new DomainException(
                    'Jumlah pembayaran harus sama persis dengan total tagihan yang dipilih ('
                    . rupiah($total) . '). Pembayaran sebagian atau berlebih tidak diperbolehkan.'
                );
            }

            $duplicateStmt = $pdo->prepare(
                'SELECT i.invoice_number, pay.payment_number
                 FROM payment_allocations pa
                 JOIN invoices i ON i.id = pa.invoice_id
                 JOIN payments pay ON pay.id = pa.payment_id
                 WHERE pa.invoice_id IN (' . implode(', ', $placeholders) . ')
                 LIMIT 1 FOR UPDATE'
            );
            $duplicateStmt->execute($params);
            $duplicate = $duplicateStmt->fetch();
            if ($duplicate !== false) {
                throw new DomainException(
                    'Tagihan ' . $duplicate['invoice_number'] . ' sudah memiliki pembayaran ('
                    . $duplicate['payment_number'] . '). Pembayaran ganda ditolak.'
                );
            }

            $paymentNumber = self::nextNumber($paymentDate);
            $insert = $pdo->prepare(
                'INSERT INTO payments
                    (payment_number, invoice_id, student_id, amount, payment_date, paid_at,
                     method, reference_number, notes, received_by, received_by_name, status)
                 VALUES
                    (:payment_number, NULL, :student_id, :amount, :payment_date, NOW(),
                     :method, :reference_number, :notes, :received_by, :received_by_name, :status)'
            );

            $attempt = 0;
            while (true) {
                $attempt++;
                try {
                    $insert->execute([
                        ':payment_number'   => $paymentNumber,
                        ':student_id'       => (int) $studentId,
                        ':amount'           => $total,
                        ':payment_date'     => $paymentDate,
                        ':method'           => $method,
                        ':reference_number' => $reference !== '' ? $reference : null,
                        ':notes'            => $notes !== '' ? $notes : null,
                        ':received_by'      => $userId,
                        ':received_by_name' => mb_substr((string) $user['name'], 0, 100),
                        ':status'           => self::STATUS_SUCCESS,
                    ]);
                    break;
                } catch (PDOException $exception) {
                    if ((string) $exception->getCode() !== '23000' || $attempt >= 5) {
                        throw $exception;
                    }
                    $paymentNumber = self::nextNumber($paymentDate);
                }
            }
            $paymentId = (int) $pdo->lastInsertId();

            $allocate = $pdo->prepare(
                'INSERT INTO payment_allocations (payment_id, invoice_id, amount)
                 VALUES (:payment_id, :invoice_id, :amount)'
            );
            $markPaid = $pdo->prepare("UPDATE invoices SET status = 'lunas' WHERE id = :id");

            foreach ($invoiceRows as $invoice) {
                $invoiceId = (int) $invoice['id'];
                $invoiceTotal = round((float) $invoice['total_amount'], 2);
                $allocate->execute([
                    ':payment_id' => $paymentId,
                    ':invoice_id' => $invoiceId,
                    ':amount'     => $invoiceTotal,
                ]);
                $markPaid->execute([':id' => $invoiceId]);
            }

            ActivityLog::record(
                'pembayaran',
                'Pelunasan ' . $paymentNumber . ' untuk ' . count($invoiceRows) . ' tagihan ('
                . implode(', ', $invoiceNumbers) . ') sebesar ' . rupiah($total)
                . ' via ' . payment_method_label($method) . '.',
                'payment',
                $paymentId
            );

            if ($own) {
                $pdo->commit();
            }

            return [
                'ok'             => true,
                'error'          => '',
                'payment_id'     => $paymentId,
                'payment_number' => $paymentNumber,
                'invoice_count'  => count($invoiceRows),
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
            self::logError('pay-many', $exception);
            return self::fail('Pembayaran gagal disimpan. Tidak ada data yang diubah.');
        }
    }

    private static function nextNumber(string $date): string
    {
        $prefix = 'NOTA-' . (new DateTimeImmutable($date))->format('Ym') . '-';
        $stmt = db()->prepare(
            'SELECT payment_number FROM payments
             WHERE payment_number LIKE :prefix
             ORDER BY payment_number DESC LIMIT 1 FOR UPDATE'
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
     * Data lengkap pembayaran untuk halaman detail & nota.
     *
     * Mengambil snapshot dari invoice agar nota historis tidak berubah.
     * Satu pembayaran dapat memuat beberapa invoice pada kunci 'invoices'.
     */
    public static function receipt(int $paymentId): ?array
    {
        if ($paymentId <= 0) {
            return null;
        }

        $stmt = db()->prepare(
            'SELECT pay.*, u.name AS officer_name, u.username AS officer_username,
                    s.name AS student_name, s.code AS student_code
             FROM payments pay
             LEFT JOIN users u ON u.id = pay.received_by
             LEFT JOIN students s ON s.id = pay.student_id
             WHERE pay.id = :id AND pay.status = "berhasil" LIMIT 1'
        );
        $stmt->execute([':id' => $paymentId]);
        $payment = $stmt->fetch();
        if ($payment === false) {
            return null;
        }

        $invoiceStmt = db()->prepare(
            'SELECT i.*, pa.id AS allocation_id, pa.amount AS allocated_amount,
                    sp.start_date AS package_start, sp.end_date AS package_end,
                    sp.completed_meetings, sp.target_meetings,
                    sp.status AS package_status
             FROM payment_allocations pa
             JOIN invoices i ON i.id = pa.invoice_id
             LEFT JOIN student_packages sp ON sp.id = i.student_package_id
             WHERE pa.payment_id = :id
             ORDER BY pa.id ASC'
        );
        $invoiceStmt->execute([':id' => $paymentId]);
        $invoices = $invoiceStmt->fetchAll();
        $payment['invoices'] = $invoices;

        // Bidang ringkas dari invoice pertama untuk kompatibilitas tampilan.
        $first = $invoices[0] ?? null;
        if (is_array($first)) {
            $payment['invoice_id'] = (int) $first['id'];
            $payment['invoice_number'] = (string) $first['invoice_number'];
            $payment['group_name'] = (string) $first['group_name'];
            $payment['group_type'] = (string) $first['group_type'];
            $payment['package_number'] = $first['package_number'];
            $payment['meeting_target'] = $first['meeting_target'];
            $payment['member_count'] = $first['member_count'];
            $payment['unit_amount'] = $first['unit_amount'];
            $payment['discount_amount'] = $first['discount_amount'];
            $payment['total_amount'] = $first['total_amount'];
            $payment['issue_date'] = $first['issue_date'];
            $payment['due_date'] = $first['due_date'];
            $payment['invoice_type'] = $first['invoice_type'];
            $payment['period_label'] = $first['period_label'];
            $payment['invoice_status'] = $first['status'];
            $payment['student_package_id'] = $first['student_package_id'];
            $payment['package_start'] = $first['package_start'];
            $payment['package_end'] = $first['package_end'];
            $payment['completed_meetings'] = $first['completed_meetings'];
            $payment['target_meetings'] = $first['target_meetings'];
            $payment['package_status'] = $first['package_status'];
            $payment['student_name'] = (string) $first['student_name'];
            $payment['student_code'] = (string) $first['student_code'];
        } else {
            $payment['invoice_number'] = '';
            $payment['total_amount'] = $payment['amount'];
        }

        return $payment;
    }

    public static function find(int $id): ?array
    {
        return self::receipt($id);
    }

    /**
     * @param array{date_from?:string,date_to?:string,group_id?:string,student_id?:string,method?:string,q?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function all(array $filters = []): array
    {
        $where = ["pay.status = 'berhasil'"];
        $params = [];

        if (($filters['date_from'] ?? '') !== '' && valid_date((string) $filters['date_from'])) {
            $where[] = 'pay.payment_date >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '' && valid_date((string) $filters['date_to'])) {
            $where[] = 'pay.payment_date <= :date_to';
            $params[':date_to'] = (string) $filters['date_to'];
        }
        if (($filters['group_id'] ?? '') !== '') {
            $where[] = 'EXISTS (
                SELECT 1 FROM payment_allocations pag
                JOIN invoices ig ON ig.id = pag.invoice_id
                WHERE pag.payment_id = pay.id AND ig.group_id = :group_id
            )';
            $params[':group_id'] = (int) $filters['group_id'];
        }
        if (($filters['student_id'] ?? '') !== '') {
            $where[] = 'pay.student_id = :student_id';
            $params[':student_id'] = (int) $filters['student_id'];
        }
        if (in_array($filters['method'] ?? '', self::methodOptions(), true)) {
            $where[] = 'pay.method = :method';
            $params[':method'] = (string) $filters['method'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(pay.payment_number LIKE :q1
                         OR EXISTS (
                             SELECT 1 FROM payment_allocations paq
                             JOIN invoices iq ON iq.id = paq.invoice_id
                             WHERE paq.payment_id = pay.id
                               AND (iq.invoice_number LIKE :q2
                                    OR iq.student_name LIKE :q3
                                    OR iq.student_code LIKE :q4)
                         ))';
            $keyword = '%' . $filters['q'] . '%';
            $params[':q1'] = $keyword;
            $params[':q2'] = $keyword;
            $params[':q3'] = $keyword;
            $params[':q4'] = $keyword;
        }

        $stmt = db()->prepare(
            'SELECT pay.*,
                    s.name AS student_name, s.code AS student_code,
                    COALESCE(pay.received_by_name, u.name) AS officer_name,
                    (SELECT GROUP_CONCAT(i.invoice_number ORDER BY i.id SEPARATOR ", ")
                       FROM payment_allocations pa
                       JOIN invoices i ON i.id = pa.invoice_id
                      WHERE pa.payment_id = pay.id) AS invoice_number,
                    (SELECT COUNT(*) FROM payment_allocations pa
                      WHERE pa.payment_id = pay.id) AS invoice_count,
                    (SELECT i2.id FROM payment_allocations pa2
                       JOIN invoices i2 ON i2.id = pa2.invoice_id
                      WHERE pa2.payment_id = pay.id ORDER BY pa2.id ASC LIMIT 1) AS invoice_id,
                    (SELECT GROUP_CONCAT(DISTINCT i3.group_name ORDER BY i3.group_name SEPARATOR ", ")
                       FROM payment_allocations pa3
                       JOIN invoices i3 ON i3.id = pa3.invoice_id
                      WHERE pa3.payment_id = pay.id) AS group_name,
                    (SELECT i4.package_number FROM payment_allocations pa4
                       JOIN invoices i4 ON i4.id = pa4.invoice_id
                      WHERE pa4.payment_id = pay.id ORDER BY pa4.id ASC LIMIT 1) AS package_number,
                    (SELECT i5.period_label FROM payment_allocations pa5
                       JOIN invoices i5 ON i5.id = pa5.invoice_id
                      WHERE pa5.payment_id = pay.id ORDER BY pa5.id ASC LIMIT 1) AS period_label
             FROM payments pay
             LEFT JOIN users u ON u.id = pay.received_by
             LEFT JOIN students s ON s.id = pay.student_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY pay.payment_date DESC, pay.id DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @param array{date_from?:string,date_to?:string,group_id?:string,student_id?:string,method?:string} $filters
     * @return array{count:int,amount:float}
     */
    public static function summary(array $filters = []): array
    {
        $where = ["pay.status = 'berhasil'"];
        $params = [];

        if (($filters['date_from'] ?? '') !== '' && valid_date((string) $filters['date_from'])) {
            $where[] = 'pay.payment_date >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '' && valid_date((string) $filters['date_to'])) {
            $where[] = 'pay.payment_date <= :date_to';
            $params[':date_to'] = (string) $filters['date_to'];
        }
        if (($filters['group_id'] ?? '') !== '') {
            $where[] = 'EXISTS (
                SELECT 1 FROM payment_allocations pag
                JOIN invoices ig ON ig.id = pag.invoice_id
                WHERE pag.payment_id = pay.id AND ig.group_id = :group_id
            )';
            $params[':group_id'] = (int) $filters['group_id'];
        }
        if (($filters['student_id'] ?? '') !== '') {
            $where[] = 'pay.student_id = :student_id';
            $params[':student_id'] = (int) $filters['student_id'];
        }
        if (in_array($filters['method'] ?? '', self::methodOptions(), true)) {
            $where[] = 'pay.method = :method';
            $params[':method'] = (string) $filters['method'];
        }

        $stmt = db()->prepare(
            'SELECT COUNT(*) AS total_count, COALESCE(SUM(pay.amount), 0) AS total_amount
             FROM payments pay
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
     * Rekap uang diterima per group beserta rincian per siswa.
     *
     * Nominal dihitung dari payment_allocations, bukan payments.amount,
     * agar pembayaran yang melunasi beberapa tagihan sekaligus terbagi
     * tepat ke group asal masing-masing tagihan (snapshot
     * invoices.group_id). Memakai payments.amount akan menghitung ganda
     * saat satu nota melunasi tagihan dari group yang berbeda.
     *
     * @param array{date_from?:string,date_to?:string,group_id?:string,student_id?:string,method?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function groupReport(array $filters = []): array
    {
        $where = ["p.status = 'berhasil'"];
        $params = [];

        if (($filters['date_from'] ?? '') !== '' && valid_date((string) $filters['date_from'])) {
            $where[] = 'p.payment_date >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '' && valid_date((string) $filters['date_to'])) {
            $where[] = 'p.payment_date <= :date_to';
            $params[':date_to'] = (string) $filters['date_to'];
        }
        if (($filters['group_id'] ?? '') !== '') {
            $where[] = 'i.group_id = :group_id';
            $params[':group_id'] = (int) $filters['group_id'];
        }
        if (($filters['student_id'] ?? '') !== '') {
            $where[] = 'i.student_id = :student_id';
            $params[':student_id'] = (int) $filters['student_id'];
        }
        if (in_array($filters['method'] ?? '', self::methodOptions(), true)) {
            $where[] = 'p.method = :method';
            $params[':method'] = (string) $filters['method'];
        }

        $stmt = db()->prepare(
            'SELECT i.group_id,
                    MAX(COALESCE(g.name, i.group_name)) AS group_name,
                    i.student_id,
                    MAX(COALESCE(s.code, i.student_code)) AS student_code,
                    MAX(COALESCE(s.name, i.student_name)) AS student_name,
                    MAX(COALESCE(s.school_class, "")) AS school_class,
                    COUNT(DISTINCT p.id) AS payment_count,
                    COUNT(DISTINCT i.id) AS invoice_count,
                    COALESCE(SUM(pa.amount), 0) AS total_amount,
                    MAX(p.payment_date) AS last_payment_date,
                    GROUP_CONCAT(DISTINCT i.package_number
                                 ORDER BY i.package_number SEPARATOR ",") AS package_numbers
             FROM payment_allocations pa
             JOIN payments p ON p.id = pa.payment_id
             JOIN invoices i ON i.id = pa.invoice_id
             LEFT JOIN students s ON s.id = i.student_id
             LEFT JOIN class_groups g ON g.id = i.group_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY i.group_id, i.student_id
             ORDER BY group_name ASC, student_name ASC'
        );
        $stmt->execute($params);

        $report = [];
        foreach ($stmt->fetchAll() as $row) {
            $groupId = (int) $row['group_id'];
            if (!isset($report[$groupId])) {
                $report[$groupId] = [
                    'group_id'           => $groupId,
                    'group_name'         => (string) $row['group_name'],
                    'students'           => [],
                    'student_count'      => 0,
                    'payment_count'      => 0,
                    'invoice_count'      => 0,
                    'total_amount'       => 0.0,
                    'first_payment_date' => null,
                    'last_payment_date'  => null,
                ];
            }

            $packageNumbers = [];
            foreach (explode(',', (string) ($row['package_numbers'] ?? '')) as $number) {
                $number = trim($number);
                if ($number !== '') {
                    $packageNumbers[] = (int) $number;
                }
            }
            sort($packageNumbers);

            $student = [
                'student_id'       => (int) $row['student_id'],
                'student_code'     => (string) $row['student_code'],
                'student_name'     => (string) $row['student_name'],
                'school_class'     => (string) $row['school_class'],
                'payment_count'    => (int) $row['payment_count'],
                'invoice_count'    => (int) $row['invoice_count'],
                'total_amount'     => (float) $row['total_amount'],
                'last_payment_date'=> (string) $row['last_payment_date'],
                'package_numbers'  => $packageNumbers,
            ];
            $report[$groupId]['students'][] = $student;
            $report[$groupId]['student_count']++;
            $report[$groupId]['payment_count'] += $student['payment_count'];
            $report[$groupId]['invoice_count'] += $student['invoice_count'];
            $report[$groupId]['total_amount'] += $student['total_amount'];

            $date = $student['last_payment_date'];
            if ($date !== '') {
                $first = $report[$groupId]['first_payment_date'];
                if ($first === null || $date < $first) {
                    $report[$groupId]['first_payment_date'] = $date;
                }
                if ($report[$groupId]['last_payment_date'] === null || $date > $report[$groupId]['last_payment_date']) {
                    $report[$groupId]['last_payment_date'] = $date;
                }
            }
        }

        return array_values($report);
    }

    // -----------------------------------------------------------------
    // Ringkasan dashboard
    // -----------------------------------------------------------------

    /**
     * @return array{count:int, amount:float}
     */
    public static function receivedBetween(string $startDate, string $endDate): array
    {
        $stmt = db()->prepare(
            "SELECT COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_amount
             FROM payments
             WHERE status = 'berhasil' AND payment_date BETWEEN :start_date AND :end_date"
        );
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $row = $stmt->fetch() ?: [];

        return [
            'count'  => (int) ($row['total_count'] ?? 0),
            'amount' => (float) ($row['total_amount'] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function recent(int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = db()->prepare(
            "SELECT p.id, p.payment_number, p.amount, p.payment_date, p.method,
                    s.name AS student_name, s.code AS student_code,
                    COALESCE(p.received_by_name, u.name) AS officer_name,
                    (SELECT GROUP_CONCAT(i.invoice_number ORDER BY i.id SEPARATOR ', ')
                       FROM payment_allocations pa
                       JOIN invoices i ON i.id = pa.invoice_id
                      WHERE pa.payment_id = p.id) AS invoice_number,
                    (SELECT COUNT(*) FROM payment_allocations pa
                      WHERE pa.payment_id = p.id) AS invoice_count
             FROM payments p
             LEFT JOIN users u ON u.id = p.received_by
             LEFT JOIN students s ON s.id = p.student_id
             WHERE p.status = 'berhasil'
             ORDER BY p.payment_date DESC, p.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Total pembayaran per bulan untuk grafik pendapatan.
     *
     * @return array{labels: array<int,string>, totals: array<int,float>, counts: array<int,int>, grand_total: float, grand_count: int}
     */
    public static function monthlyRevenue(int $months = 12): array
    {
        $months = max(1, min(24, $months));
        $start = (new DateTimeImmutable('first day of this month'))->modify('-' . ($months - 1) . ' months');
        $end = new DateTimeImmutable('last day of this month');

        $stmt = db()->prepare(
            "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym,
                    COUNT(*) AS total_count,
                    COALESCE(SUM(amount), 0) AS total_amount
             FROM payments
             WHERE status = 'berhasil'
               AND payment_date BETWEEN :start_date AND :end_date
             GROUP BY ym
             ORDER BY ym ASC"
        );
        $stmt->execute([
            ':start_date' => $start->format('Y-m-d'),
            ':end_date'   => $end->format('Y-m-d'),
        ]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(string) $row['ym']] = [
                'amount' => (float) $row['total_amount'],
                'count'  => (int) $row['total_count'],
            ];
        }

        $labels = [];
        $totals = [];
        $counts = [];
        $grandTotal = 0.0;
        $grandCount = 0;

        $cursor = $start;
        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');
            $labels[] = bulan_singkat_id((int) $cursor->format('n')) . ' ' . $cursor->format('Y');
            $totals[] = $map[$key]['amount'] ?? 0.0;
            $counts[] = $map[$key]['count'] ?? 0;
            $grandTotal += $map[$key]['amount'] ?? 0.0;
            $grandCount += $map[$key]['count'] ?? 0;
            $cursor = $cursor->modify('+1 month');
        }

        return [
            'labels'      => $labels,
            'totals'      => $totals,
            'counts'      => $counts,
            'grand_total' => $grandTotal,
            'grand_count' => $grandCount,
        ];
    }

    /**
     * @return array{ok:false,error:string,payment_id:0,payment_number:'',invoice_count:0}
     */
    private static function fail(string $message): array
    {
        return [
            'ok'            => false,
            'error'         => $message,
            'payment_id'    => 0,
            'payment_number' => '',
            'invoice_count' => 0,
        ];
    }

    private static function logError(string $action, Throwable $exception): void
    {
        @file_put_contents(
            LOG_PATH . '/app.log',
            '[' . date('Y-m-d H:i:s') . '] PAYMENT ' . strtoupper($action) . ': '
            . $exception->getMessage() . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
