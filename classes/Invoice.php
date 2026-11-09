<?php
declare(strict_types=1);

/**
 * Tagihan (invoice) — data snapshot.
 *
 * Tahap 4: tagihan paket belajar 8 pertemuan.
 * - Satu paket hanya boleh memiliki satu tagihan aktif (UNIQUE generated column).
 * - Snapshot siswa/kelompok/tarif disimpan sebagai kolom sehingga perubahan
 *   data master maupun tarif tidak mengubah tagihan lama.
 * - Nomor invoice unik: INV-YYYYMM-####.
 * - Tagihan lunas tidak boleh diubah; tagihan batal menyimpan alasan.
 * - Tagihan bulanan lama (invoice_type='bulanan') tetap tersimpan sebagai arsip.
 */
final class Invoice
{
    public const TYPE_MONTHLY = 'bulanan';
    public const TYPE_PACKAGE = 'paket';

    /** Jatuh tempo default (hari) untuk tagihan paket. */
    public const DEFAULT_DUE_DAYS = 7;

    // -----------------------------------------------------------------
    // Pembuatan tagihan paket
    // -----------------------------------------------------------------

    /**
     * Buat tagihan untuk satu paket belajar.
     *
     * Aman dipanggil berulang: bila paket sudah memiliki tagihan aktif,
     * id tagihan tersebut yang dikembalikan (tidak membuat duplikat).
     *
     * @return int id invoice
     */
    public static function createForPackage(
        int $packageId,
        float $discount = 0.0,
        ?string $issueDate = null,
        ?string $dueDate = null
    ): int {
        $pdo = db();

        $lock = $pdo->prepare(
            'SELECT p.*, s.code AS student_code, s.name AS student_name,
                    g.name AS group_name, g.code AS group_code
             FROM student_packages p
             JOIN students s ON s.id = p.student_id
             JOIN class_groups g ON g.id = p.group_id
             WHERE p.id = :id LIMIT 1 FOR UPDATE'
        );
        $lock->execute([':id' => $packageId]);
        $package = $lock->fetch();
        if ($package === false) {
            throw new DomainException('Paket belajar tidak ditemukan.');
        }
        if ((string) $package['status'] === StudentPackage::STATUS_DIBATALKAN) {
            throw new DomainException('Paket yang dibatalkan tidak dapat ditagihkan.');
        }

        $existing = self::activeForPackage($packageId);
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $issue = $issueDate !== null && valid_date($issueDate) ? $issueDate : date('Y-m-d');
        $due = $dueDate !== null && valid_date($dueDate)
            ? $dueDate
            : (new DateTimeImmutable($issue))->modify('+' . self::DEFAULT_DUE_DAYS . ' days')->format('Y-m-d');

        $base = round((float) $package['base_amount'], 2);
        $discount = round(max(0.0, $discount), 2);
        if ($discount > $base) {
            throw new DomainException('Diskon tidak boleh melebihi tarif dasar.');
        }
        $total = round($base - $discount, 2);

        $packageNumber = (int) $package['package_number'];
        $target = (int) $package['target_meetings'];
        $periodLabel = 'Paket ' . $packageNumber . ' — ' . $target . ' pertemuan';

        $insert = $pdo->prepare(
            'INSERT INTO invoices
                (invoice_number, student_id, group_id, group_member_id,
                 student_package_id, billing_period_id, package_number, meeting_target,
                 pricing_rule_id, student_code, student_name, group_name, group_type,
                 subject_name, teacher_name, period_label, invoice_type, member_count,
                 unit_amount, discount_amount, total_amount, issue_date, due_date,
                 status, notes, created_by)
             VALUES
                (:invoice_number, :student_id, :group_id, :group_member_id,
                 :student_package_id, NULL, :package_number, :meeting_target,
                 :pricing_rule_id, :student_code, :student_name, :group_name, :group_type,
                 NULL, NULL, :period_label, :invoice_type, :member_count,
                 :unit_amount, :discount_amount, :total_amount, :issue_date, :due_date,
                 :status, :notes, :created_by)'
        );

        $attempt = 0;
        while (true) {
            $attempt++;
            $number = self::nextNumber($issue);
            try {
                $insert->execute([
                    ':invoice_number'     => $number,
                    ':student_id'         => (int) $package['student_id'],
                    ':group_id'           => (int) $package['group_id'],
                    ':group_member_id'    => (int) $package['group_member_id'],
                    ':student_package_id' => $packageId,
                    ':package_number'     => $packageNumber,
                    ':meeting_target'     => $target,
                    ':pricing_rule_id'    => $package['pricing_rule_id'] !== null
                        ? (int) $package['pricing_rule_id'] : null,
                    ':student_code'       => (string) $package['student_code'],
                    ':student_name'       => (string) $package['student_name'],
                    ':group_name'         => (string) $package['group_name'],
                    ':group_type'         => (string) $package['group_type'],
                    ':period_label'       => $periodLabel,
                    ':invoice_type'       => self::TYPE_PACKAGE,
                    ':member_count'       => max(1, (int) $package['member_count']),
                    ':unit_amount'        => $base,
                    ':discount_amount'    => $discount,
                    ':total_amount'       => $total,
                    ':issue_date'         => $issue,
                    ':due_date'           => $due,
                    ':status'             => 'belum_bayar',
                    ':notes'              => 'Tagihan paket belajar ' . $target . ' pertemuan.',
                    ':created_by'         => self::actorId(),
                ]);
                break;
            } catch (PDOException $exception) {
                $isDuplicate = (string) $exception->getCode() === '23000';
                if (!$isDuplicate || $attempt >= 5) {
                    throw $exception;
                }
            }
        }

        $invoiceId = (int) $pdo->lastInsertId();

        $update = $pdo->prepare('UPDATE student_packages SET invoice_id = :invoice_id WHERE id = :id');
        $update->execute([':invoice_id' => $invoiceId, ':id' => $packageId]);

        ActivityLog::record(
            'tagihan_terbit',
            'Tagihan ' . $number . ' untuk Paket ' . $packageNumber . ' diterbitkan (' . rupiah($total) . ').',
            'invoice',
            $invoiceId
        );

        return $invoiceId;
    }

    /**
     * Nomor invoice berikutnya untuk bulan berjalan dari tanggal terbit.
     */
    private static function nextNumber(string $date): string
    {
        $prefix = 'INV-' . (new DateTimeImmutable($date))->format('Ym') . '-';

        $stmt = db()->prepare(
            'SELECT invoice_number FROM invoices
             WHERE invoice_number LIKE :prefix
             ORDER BY invoice_number DESC
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([':prefix' => $prefix . '%']);
        $last = $stmt->fetchColumn();
        $sequence = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }
        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    // -----------------------------------------------------------------
    // Pembatalan
    // -----------------------------------------------------------------

    /**
     * Batalkan tagihan yang belum dibayar. Wajib menyertakan alasan.
     */
    public static function cancel(int $invoiceId, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new DomainException('Alasan pembatalan tagihan minimal 3 karakter.');
        }

        $pdo = db();
        $lock = $pdo->prepare('SELECT * FROM invoices WHERE id = :id LIMIT 1 FOR UPDATE');
        $lock->execute([':id' => $invoiceId]);
        $invoice = $lock->fetch();
        if ($invoice === false) {
            throw new DomainException('Tagihan tidak ditemukan.');
        }
        if ((string) $invoice['status'] === 'lunas') {
            throw new DomainException('Tagihan yang sudah lunas tidak dapat dibatalkan.');
        }
        if ((string) $invoice['status'] === 'dibatalkan') {
            return;
        }

        $update = $pdo->prepare(
            "UPDATE invoices SET status = 'dibatalkan', cancel_reason = :reason,
                    cancelled_at = NOW(), cancelled_by = :by
             WHERE id = :id"
        );
        $update->execute([
            ':reason' => mb_substr($reason, 0, 255),
            ':by'     => self::actorId(),
            ':id'     => $invoiceId,
        ]);

        if ($invoice['student_package_id'] !== null) {
            $clear = $pdo->prepare(
                'UPDATE student_packages SET invoice_id = NULL
                 WHERE id = :package_id AND invoice_id = :invoice_id'
            );
            $clear->execute([
                ':package_id' => (int) $invoice['student_package_id'],
                ':invoice_id' => $invoiceId,
            ]);
        }

        ActivityLog::record(
            'invoice_batal',
            'Tagihan ' . $invoice['invoice_number'] . ' dibatalkan: ' . $reason,
            'invoice',
            $invoiceId
        );
    }

    // -----------------------------------------------------------------
    // Pencarian data
    // -----------------------------------------------------------------

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT i.*, sp.status AS package_status, sp.completed_meetings, sp.target_meetings,
                    sp.start_date AS package_start, u.name AS created_by_name,
                    pay.id AS payment_id, pay.payment_number, pay.status AS payment_status,
                    pay.payment_date, pay.method, pa.amount AS paid_amount
             FROM invoices i
             LEFT JOIN student_packages sp ON sp.id = i.student_package_id
             LEFT JOIN users u ON u.id = i.created_by
             LEFT JOIN payment_allocations pa ON pa.invoice_id = i.id
             LEFT JOIN payments pay ON pay.id = pa.payment_id AND pay.status = "berhasil"
             WHERE i.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findForPackage(int $packageId): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM invoices
             WHERE student_package_id = :package_id
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':package_id' => $packageId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Tagihan aktif (bukan dibatalkan) milik satu paket.
     */
    public static function activeForPackage(int $packageId): ?array
    {
        $stmt = db()->prepare(
            "SELECT * FROM invoices
             WHERE student_package_id = :package_id AND status <> 'dibatalkan'
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':package_id' => $packageId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Seluruh riwayat tagihan satu paket.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forPackage(int $packageId): array
    {
        $stmt = db()->prepare(
            'SELECT i.*, pay.payment_number, pay.payment_date, pay.method,
                    pay.amount AS paid_amount, u.name AS cancelled_by_name
             FROM invoices i
             LEFT JOIN payment_allocations pa ON pa.invoice_id = i.id
             LEFT JOIN payments pay ON pay.id = pa.payment_id AND pay.status = "berhasil"
             LEFT JOIN users u ON u.id = i.cancelled_by
             WHERE i.student_package_id = :package_id
             ORDER BY i.id DESC'
        );
        $stmt->execute([':package_id' => $packageId]);
        return $stmt->fetchAll();
    }

    /**
     * Daftar tagihan dengan filter.
     *
     * @param array{status?:string,group_id?:string,student_id?:string,date_from?:string,date_to?:string,invoice_type?:string,q?:string,package_status?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function all(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (in_array($filters['status'] ?? '', ['belum_bayar', 'lunas', 'dibatalkan'], true)) {
            $where[] = 'i.status = :status';
            $params[':status'] = (string) $filters['status'];
        }
        if (($filters['group_id'] ?? '') !== '') {
            $where[] = 'i.group_id = :group_id';
            $params[':group_id'] = (int) $filters['group_id'];
        }
        if (($filters['student_id'] ?? '') !== '') {
            $where[] = 'i.student_id = :student_id';
            $params[':student_id'] = (int) $filters['student_id'];
        }
        if (($filters['date_from'] ?? '') !== '' && valid_date((string) $filters['date_from'])) {
            $where[] = 'i.issue_date >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '' && valid_date((string) $filters['date_to'])) {
            $where[] = 'i.issue_date <= :date_to';
            $params[':date_to'] = (string) $filters['date_to'];
        }
        if (($filters['invoice_type'] ?? '') === self::TYPE_PACKAGE || ($filters['invoice_type'] ?? '') === self::TYPE_MONTHLY) {
            $where[] = 'i.invoice_type = :invoice_type';
            $params[':invoice_type'] = (string) $filters['invoice_type'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(i.invoice_number LIKE :q1 OR i.student_name LIKE :q2 OR i.student_code LIKE :q3 OR i.group_name LIKE :q4)';
            $keyword = '%' . $filters['q'] . '%';
            $params[':q1'] = $keyword;
            $params[':q2'] = $keyword;
            $params[':q3'] = $keyword;
            $params[':q4'] = $keyword;
        }

        $stmt = db()->prepare(
            'SELECT i.*, sp.status AS package_status, sp.completed_meetings, sp.target_meetings,
                    pay.id AS payment_id, pay.payment_number, pay.payment_date, pay.method
             FROM invoices i
             LEFT JOIN student_packages sp ON sp.id = i.student_package_id
             LEFT JOIN payment_allocations pa ON pa.invoice_id = i.id
             LEFT JOIN payments pay ON pay.id = pa.payment_id AND pay.status = "berhasil"'
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY i.issue_date DESC, i.id DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Tagihan terbaru untuk dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function recentPackage(int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = db()->prepare(
            "SELECT i.*, pay.payment_number
             FROM invoices i
             LEFT JOIN payment_allocations pa ON pa.invoice_id = i.id
             LEFT JOIN payments pay ON pay.id = pa.payment_id AND pay.status = 'berhasil'
             WHERE i.invoice_type = 'paket'
             ORDER BY i.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // -----------------------------------------------------------------
    // Ringkasan (dashboard & laporan)
    // -----------------------------------------------------------------

    /**
     * Ringkasan tagihan dengan filter tanggal/kelompok/siswa.
     *
     * Membedakan tagihan diterbitkan (belum dibatalkan) dengan uang yang
     * benar-benar diterima (pembayaran berhasil).
     *
     * @param array{date_from?:string,date_to?:string,group_id?:string,student_id?:string,invoice_type?:string} $filters
     * @return array{issued_count:int,issued_amount:float,paid_count:int,paid_amount:float,unpaid_count:int,unpaid_amount:float,cancelled_count:int,cancelled_amount:float,received_count:int,received_amount:float}
     */
    public static function summary(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (($filters['date_from'] ?? '') !== '' && valid_date((string) $filters['date_from'])) {
            $where[] = 'i.issue_date >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '' && valid_date((string) $filters['date_to'])) {
            $where[] = 'i.issue_date <= :date_to';
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
        if (in_array($filters['invoice_type'] ?? '', [self::TYPE_PACKAGE, self::TYPE_MONTHLY], true)) {
            $where[] = 'i.invoice_type = :invoice_type';
            $params[':invoice_type'] = (string) $filters['invoice_type'];
        }

        $stmt = db()->prepare(
            "SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN i.status <> 'dibatalkan' THEN 1 ELSE 0 END), 0) AS issued_count,
                COALESCE(SUM(CASE WHEN i.status <> 'dibatalkan' THEN i.total_amount ELSE 0 END), 0) AS issued_amount,
                COALESCE(SUM(CASE WHEN i.status = 'lunas' THEN 1 ELSE 0 END), 0) AS paid_count,
                COALESCE(SUM(CASE WHEN i.status = 'lunas' THEN i.total_amount ELSE 0 END), 0) AS paid_amount,
                COALESCE(SUM(CASE WHEN i.status = 'belum_bayar' THEN 1 ELSE 0 END), 0) AS unpaid_count,
                COALESCE(SUM(CASE WHEN i.status = 'belum_bayar' THEN i.total_amount ELSE 0 END), 0) AS unpaid_amount,
                COALESCE(SUM(CASE WHEN i.status = 'dibatalkan' THEN 1 ELSE 0 END), 0) AS cancelled_count,
                COALESCE(SUM(CASE WHEN i.status = 'dibatalkan' THEN i.total_amount ELSE 0 END), 0) AS cancelled_amount
             FROM invoices i"
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
        );
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        $paymentWhere = ["pay.status = 'berhasil'"];
        $paymentParams = [];
        foreach ($params as $key => $value) {
            if (in_array($key, [':group_id', ':student_id', ':invoice_type'], true)) {
                $paymentWhere[] = 'i.' . ltrim($key, ':') . ' = ' . $key;
                $paymentParams[$key] = $value;
            }
        }
        if (($filters['date_from'] ?? '') !== '' && valid_date((string) $filters['date_from'])) {
            $paymentWhere[] = 'pay.payment_date >= :pay_from';
            $paymentParams[':pay_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '' && valid_date((string) $filters['date_to'])) {
            $paymentWhere[] = 'pay.payment_date <= :pay_to';
            $paymentParams[':pay_to'] = (string) $filters['date_to'];
        }

        $paymentStmt = db()->prepare(
            "SELECT COUNT(DISTINCT pay.id) AS received_count, COALESCE(SUM(pa.amount), 0) AS received_amount
             FROM payment_allocations pa
             JOIN payments pay ON pay.id = pa.payment_id
             JOIN invoices i ON i.id = pa.invoice_id
             WHERE " . implode(' AND ', $paymentWhere)
        );
        $paymentStmt->execute($paymentParams);
        $paymentRow = $paymentStmt->fetch() ?: [];

        return [
            'issued_count'     => (int) ($row['issued_count'] ?? 0),
            'issued_amount'    => (float) ($row['issued_amount'] ?? 0),
            'paid_count'       => (int) ($row['paid_count'] ?? 0),
            'paid_amount'      => (float) ($row['paid_amount'] ?? 0),
            'unpaid_count'     => (int) ($row['unpaid_count'] ?? 0),
            'unpaid_amount'    => (float) ($row['unpaid_amount'] ?? 0),
            'cancelled_count'  => (int) ($row['cancelled_count'] ?? 0),
            'cancelled_amount' => (float) ($row['cancelled_amount'] ?? 0),
            'received_count'   => (int) ($paymentRow['received_count'] ?? 0),
            'received_amount'  => (float) ($paymentRow['received_amount'] ?? 0),
        ];
    }

    // -----------------------------------------------------------------
    // Utang (tagihan terbit vs pembayaran teralokasi)
    // -----------------------------------------------------------------

    /**
     * Ringkasan utang: Total Utang = Total Tagihan - Total Pembayaran
     * yang dialokasikan. Hanya tagihan non-dibatalkan yang dihitung.
     *
     * @param array{date_from?:string,date_to?:string,group_id?:string,student_id?:string,invoice_type?:string} $filters
     * @return array{billed_count:int,billed_amount:float,received_amount:float,debt_amount:float,debtor_count:int,unpaid_count:int,unpaid_amount:float,paid_count:int,cancelled_count:int,cancelled_amount:float}
     */
    public static function debtSummary(array $filters = []): array
    {
        $where = ["i.status <> 'dibatalkan'"];
        $params = [];

        if (($filters['date_from'] ?? '') !== '' && valid_date((string) $filters['date_from'])) {
            $where[] = 'i.issue_date >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '' && valid_date((string) $filters['date_to'])) {
            $where[] = 'i.issue_date <= :date_to';
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
        if (in_array($filters['invoice_type'] ?? '', [self::TYPE_PACKAGE, self::TYPE_MONTHLY], true)) {
            $where[] = 'i.invoice_type = :invoice_type';
            $params[':invoice_type'] = (string) $filters['invoice_type'];
        }

        $sql = 'SELECT
                    COUNT(*) AS billed_count,
                    COALESCE(SUM(i.total_amount), 0) AS billed_amount,
                    COALESCE(SUM(CASE WHEN i.status = "belum_bayar" THEN 1 ELSE 0 END), 0) AS unpaid_count,
                    COALESCE(SUM(CASE WHEN i.status = "belum_bayar" THEN i.total_amount ELSE 0 END), 0) AS unpaid_amount,
                    COALESCE(SUM(CASE WHEN i.status = "lunas" THEN 1 ELSE 0 END), 0) AS paid_count,
                    COUNT(DISTINCT CASE WHEN i.status = "belum_bayar" THEN i.student_id END) AS debtor_count,
                    COALESCE(SUM(paid.paid_amount), 0) AS received_amount,
                    COALESCE(SUM(CASE WHEN i.status = "dibatalkan" THEN 1 ELSE 0 END), 0) AS cancelled_count,
                    COALESCE(SUM(CASE WHEN i.status = "dibatalkan" THEN i.total_amount ELSE 0 END), 0) AS cancelled_amount
                FROM invoices i
                LEFT JOIN (
                    SELECT pa.invoice_id, SUM(pa.amount) AS paid_amount
                    FROM payment_allocations pa
                    JOIN payments pay ON pay.id = pa.payment_id AND pay.status = "berhasil"
                    GROUP BY pa.invoice_id
                ) paid ON paid.invoice_id = i.id
                WHERE ' . implode(' AND ', $where);

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        $billed = (float) ($row['billed_amount'] ?? 0);
        $received = (float) ($row['received_amount'] ?? 0);

        return [
            'billed_count'     => (int) ($row['billed_count'] ?? 0),
            'billed_amount'    => $billed,
            'received_amount'  => $received,
            'debt_amount'      => max(0.0, round($billed - $received, 2)),
            'debtor_count'     => (int) ($row['debtor_count'] ?? 0),
            'unpaid_count'     => (int) ($row['unpaid_count'] ?? 0),
            'unpaid_amount'    => (float) ($row['unpaid_amount'] ?? 0),
            'paid_count'       => (int) ($row['paid_count'] ?? 0),
            'cancelled_count'  => (int) ($row['cancelled_count'] ?? 0),
            'cancelled_amount' => (float) ($row['cancelled_amount'] ?? 0),
        ];
    }

    /**
     * Utang per siswa untuk laporan dan dashboard.
     *
     * @param array{date_from?:string,date_to?:string,group_id?:string,student_id?:string,invoice_type?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function debtByStudent(array $filters = []): array
    {
        $where = ["i.status <> 'dibatalkan'"];
        $params = [];

        if (($filters['date_from'] ?? '') !== '' && valid_date((string) $filters['date_from'])) {
            $where[] = 'i.issue_date >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '' && valid_date((string) $filters['date_to'])) {
            $where[] = 'i.issue_date <= :date_to';
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
        if (in_array($filters['invoice_type'] ?? '', [self::TYPE_PACKAGE, self::TYPE_MONTHLY], true)) {
            $where[] = 'i.invoice_type = :invoice_type';
            $params[':invoice_type'] = (string) $filters['invoice_type'];
        }

        $stmt = db()->prepare(
            'SELECT i.student_id, i.student_code, i.student_name,
                    COUNT(*) AS invoice_count,
                    COALESCE(SUM(CASE WHEN i.status = "belum_bayar" THEN 1 ELSE 0 END), 0) AS unpaid_count,
                    COALESCE(SUM(i.total_amount), 0) AS billed_amount,
                    COALESCE(SUM(paid.paid_amount), 0) AS received_amount
             FROM invoices i
             LEFT JOIN (
                 SELECT pa.invoice_id, SUM(pa.amount) AS paid_amount
                 FROM payment_allocations pa
                 JOIN payments pay ON pay.id = pa.payment_id AND pay.status = "berhasil"
                 GROUP BY pa.invoice_id
             ) paid ON paid.invoice_id = i.id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY i.student_id, i.student_code, i.student_name
             ORDER BY (COALESCE(SUM(i.total_amount), 0) - COALESCE(SUM(paid.paid_amount), 0)) DESC,
                      i.student_name ASC'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['debt_amount'] = max(0.0, round((float) $row['billed_amount'] - (float) $row['received_amount'], 2));
        }
        unset($row);
        return $rows;
    }


    /**
     * Rekap tagihan per group dan per siswa untuk melihat potensi dana masuk.
     *
     * Berbeda dari rekap pembayaran, di sini semua tagihan non-batal ikut
     * dihitung walau siswanya belum membayar. Nominal "sudah masuk" adalah
     * alokasi pembayaran berhasil pada tagihan tersebut, sehingga:
     *   sisa = total tagihan - sudah masuk.
     *
     * @param array{date_from?:string,date_to?:string,group_id?:string,student_id?:string,invoice_type?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function groupBilling(array $filters = []): array
    {
        $where = ["i.status <> 'dibatalkan'"];
        $params = [];

        if (($filters['date_from'] ?? '') !== '' && valid_date((string) $filters['date_from'])) {
            $where[] = 'i.issue_date >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '' && valid_date((string) $filters['date_to'])) {
            $where[] = 'i.issue_date <= :date_to';
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
        if (in_array($filters['invoice_type'] ?? '', [self::TYPE_PACKAGE, self::TYPE_MONTHLY], true)) {
            $where[] = 'i.invoice_type = :invoice_type';
            $params[':invoice_type'] = (string) $filters['invoice_type'];
        }

        $stmt = db()->prepare(
            'SELECT i.group_id,
                    MAX(COALESCE(g.name, i.group_name)) AS group_name,
                    i.student_id,
                    MAX(COALESCE(s.code, i.student_code)) AS student_code,
                    MAX(COALESCE(s.name, i.student_name)) AS student_name,
                    MAX(COALESCE(s.school_class, "")) AS school_class,
                    COUNT(*) AS invoice_count,
                    COALESCE(SUM(CASE WHEN i.status = "belum_bayar" THEN 1 ELSE 0 END), 0) AS unpaid_count,
                    COALESCE(SUM(i.total_amount), 0) AS billed_amount,
                    COALESCE(SUM(paid.paid_amount), 0) AS received_amount,
                    MIN(CASE WHEN i.status = "belum_bayar" THEN i.due_date END) AS oldest_due_date
             FROM invoices i
             LEFT JOIN students s ON s.id = i.student_id
             LEFT JOIN class_groups g ON g.id = i.group_id
             LEFT JOIN (
                 SELECT pa.invoice_id, SUM(pa.amount) AS paid_amount
                 FROM payment_allocations pa
                 JOIN payments pay ON pay.id = pa.payment_id AND pay.status = "berhasil"
                 GROUP BY pa.invoice_id
             ) paid ON paid.invoice_id = i.id
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
                    'group_id'        => $groupId,
                    'group_name'      => (string) $row['group_name'],
                    'students'        => [],
                    'student_count'   => 0,
                    'invoice_count'   => 0,
                    'unpaid_count'    => 0,
                    'billed_amount'   => 0.0,
                    'received_amount' => 0.0,
                    'debt_amount'     => 0.0,
                    'oldest_due_date' => null,
                ];
            }

            $billed = (float) $row['billed_amount'];
            $received = (float) $row['received_amount'];
            $debt = max(0.0, round($billed - $received, 2));
            $dueDate = $row['oldest_due_date'] !== null ? (string) $row['oldest_due_date'] : null;

            $report[$groupId]['students'][] = [
                'student_id'      => (int) $row['student_id'],
                'student_code'    => (string) $row['student_code'],
                'student_name'    => (string) $row['student_name'],
                'school_class'    => (string) $row['school_class'],
                'invoice_count'   => (int) $row['invoice_count'],
                'unpaid_count'    => (int) $row['unpaid_count'],
                'billed_amount'   => $billed,
                'received_amount' => $received,
                'debt_amount'     => $debt,
                'oldest_due_date' => $dueDate,
            ];

            $report[$groupId]['student_count']++;
            $report[$groupId]['invoice_count'] += (int) $row['invoice_count'];
            $report[$groupId]['unpaid_count'] += (int) $row['unpaid_count'];
            $report[$groupId]['billed_amount'] += $billed;
            $report[$groupId]['received_amount'] += $received;
            $report[$groupId]['debt_amount'] += $debt;
            if ($dueDate !== null) {
                $current = $report[$groupId]['oldest_due_date'];
                if ($current === null || $dueDate < $current) {
                    $report[$groupId]['oldest_due_date'] = $dueDate;
                }
            }
        }

        return array_values($report);
    }


    /**
     * Ringkasan invoice dalam satu periode.
     *
     * @return array<string, int|float>
     */
    public static function periodSummary(int $periodId): array
    {
        $stmt = db()->prepare(
            "SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN status <> 'dibatalkan' THEN 1 ELSE 0 END), 0) AS issued_count,
                COALESCE(SUM(CASE WHEN status <> 'dibatalkan' THEN total_amount ELSE 0 END), 0) AS issued_amount,
                COALESCE(SUM(CASE WHEN status = 'lunas' THEN 1 ELSE 0 END), 0) AS paid_count,
                COALESCE(SUM(CASE WHEN status = 'lunas' THEN total_amount ELSE 0 END), 0) AS paid_amount,
                COALESCE(SUM(CASE WHEN status = 'belum_bayar' THEN 1 ELSE 0 END), 0) AS unpaid_count,
                COALESCE(SUM(CASE WHEN status = 'belum_bayar' THEN total_amount ELSE 0 END), 0) AS unpaid_amount,
                COALESCE(SUM(CASE WHEN status = 'dibatalkan' THEN 1 ELSE 0 END), 0) AS cancelled_count,
                COALESCE(SUM(CASE WHEN status = 'dibatalkan' THEN total_amount ELSE 0 END), 0) AS cancelled_amount
             FROM invoices
             WHERE billing_period_id = :period_id"
        );
        $stmt->execute([':period_id' => $periodId]);
        $row = $stmt->fetch() ?: [];

        return [
            'total_count'     => (int) ($row['total_count'] ?? 0),
            'issued_count'    => (int) ($row['issued_count'] ?? 0),
            'issued_amount'   => (float) ($row['issued_amount'] ?? 0),
            'paid_count'      => (int) ($row['paid_count'] ?? 0),
            'paid_amount'     => (float) ($row['paid_amount'] ?? 0),
            'unpaid_count'    => (int) ($row['unpaid_count'] ?? 0),
            'unpaid_amount'   => (float) ($row['unpaid_amount'] ?? 0),
            'cancelled_count' => (int) ($row['cancelled_count'] ?? 0),
            'cancelled_amount' => (float) ($row['cancelled_amount'] ?? 0),
        ];
    }

    /**
     * Jumlah & nominal invoice per status (seluruh data).
     *
     * @return array<string, array{count:int, amount:float}>
     */
    public static function statusBreakdown(): array
    {
        $result = [
            'belum_bayar' => ['count' => 0, 'amount' => 0.0],
            'lunas'       => ['count' => 0, 'amount' => 0.0],
            'dibatalkan'  => ['count' => 0, 'amount' => 0.0],
        ];

        $rows = db()->query(
            'SELECT status, COUNT(*) AS total, COALESCE(SUM(total_amount), 0) AS amount
             FROM invoices GROUP BY status'
        )->fetchAll();

        foreach ($rows as $row) {
            $status = (string) $row['status'];
            if (isset($result[$status])) {
                $result[$status] = [
                    'count'  => (int) $row['total'],
                    'amount' => (float) $row['amount'],
                ];
            }
        }

        return $result;
    }

    /**
     * @return array{count:int, amount:float, overdue_count:int, overdue_amount:float}
     */
    public static function outstandingSummary(): array
    {
        $row = db()->query(
            "SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(total_amount), 0) AS total_amount,
                COALESCE(SUM(CASE WHEN due_date < CURDATE() THEN 1 ELSE 0 END), 0) AS overdue_count,
                COALESCE(SUM(CASE WHEN due_date < CURDATE() THEN total_amount ELSE 0 END), 0) AS overdue_amount
             FROM invoices
             WHERE status = 'belum_bayar'"
        )->fetch() ?: [];

        return [
            'count'          => (int) ($row['total_count'] ?? 0),
            'amount'         => (float) ($row['total_amount'] ?? 0),
            'overdue_count'  => (int) ($row['overdue_count'] ?? 0),
            'overdue_amount' => (float) ($row['overdue_amount'] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function recentUnpaid(int $limit = 6): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = db()->prepare(
            "SELECT id, invoice_number, student_name, group_name, period_label,
                    total_amount, due_date, status, invoice_type,
                    DATEDIFF(CURDATE(), due_date) AS overdue_days
             FROM invoices
             WHERE status = 'belum_bayar'
             ORDER BY issue_date ASC, id ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private static function actorId(): ?int
    {
        $user = Auth::user();
        return $user !== null ? (int) $user['id'] : null;
    }
}
