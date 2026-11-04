<?php
declare(strict_types=1);

/**
 * Laporan Tahap 4: pembayaran, tagihan, paket belajar, realisasi
 * pertemuan, dan rekap keuangan.
 *
 * Rekap keuangan SELALU membedakan:
 * - nilai tagihan yang diterbitkan (belum termasuk yang dibatalkan);
 * - uang yang benar-benar diterima (pembayaran berhasil);
 * sehingga total tagihan tidak pernah disamakan dengan kas masuk.
 */
final class Report
{
    /**
     * @param array<string, mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>,count:int,amount:float}
     */
    public static function payments(array $filters = []): array
    {
        $rows = Payment::all($filters);
        $summary = Payment::summary($filters);
        return ['rows' => $rows, 'count' => $summary['count'], 'amount' => $summary['amount']];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>,summary:array<string,int|float>}
     */
    public static function invoices(array $filters = []): array
    {
        $filters['invoice_type'] = $filters['invoice_type'] ?? Invoice::TYPE_PACKAGE;
        return [
            'rows'    => Invoice::all($filters),
            'summary' => Invoice::summary($filters),
        ];
    }

    /**
     * @param array{status?:string,group_id?:string,student_id?:string,date_from?:string,date_to?:string,q?:string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function packages(array $filters = []): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (($filters['status'] ?? '') !== ''
            && in_array($filters['status'], StudentPackage::statusOptions(), true)) {
            $where[] = 'p.status = :status';
            $params[':status'] = (string) $filters['status'];
        }
        if (($filters['group_id'] ?? '') !== '') {
            $where[] = 'p.group_id = :group_id';
            $params[':group_id'] = (int) $filters['group_id'];
        }
        if (($filters['student_id'] ?? '') !== '') {
            $where[] = 'p.student_id = :student_id';
            $params[':student_id'] = (int) $filters['student_id'];
        }
        if (($filters['date_from'] ?? '') !== '' && valid_date((string) $filters['date_from'])) {
            $where[] = 'p.start_date >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? '') !== '' && valid_date((string) $filters['date_to'])) {
            $where[] = 'p.start_date <= :date_to';
            $params[':date_to'] = (string) $filters['date_to'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(s.name LIKE :q1 OR s.code LIKE :q2 OR g.name LIKE :q3)';
            $keyword = '%' . $filters['q'] . '%';
            $params[':q1'] = $keyword;
            $params[':q2'] = $keyword;
            $params[':q3'] = $keyword;
        }

        $stmt = db()->prepare(
            'SELECT p.*, s.code AS student_code, s.name AS student_name,
                    g.code AS group_code, g.name AS group_name,
                    i.invoice_number, i.status AS invoice_status, i.total_amount AS invoice_total
             FROM student_packages p
             JOIN students s ON s.id = p.student_id
             JOIN class_groups g ON g.id = p.group_id
             LEFT JOIN invoices i
                    ON i.id = p.invoice_id AND i.status <> "dibatalkan"
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.id DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public static function meetings(array $filters = []): array
    {
        return Meeting::recorded($filters);
    }

    /**
     * Rekap keuangan: diterbitkan vs diterima.
     *
     * @param array<string, mixed> $filters
     * @return array{issued_count:int,issued_amount:float,paid_count:int,paid_amount:float,unpaid_count:int,unpaid_amount:float,cancelled_count:int,cancelled_amount:float,received_count:int,received_amount:float}
     */
    public static function finance(array $filters = []): array
    {
        $filters['invoice_type'] = Invoice::TYPE_PACKAGE;
        return Invoice::summary($filters);
    }

    /**
     * Rekap pengeluaran operasional pada rentang filter.
     *
     * @param array<string, mixed> $filters
     * @return array{count:int,amount:float}
     */
    public static function expenses(array $filters = []): array
    {
        return Expense::summary($filters);
    }

    /**
     * Pengeluaran operasional per bulan (hanya status tercatat).
     *
     * @return array{labels:array<int,string>,amounts:array<int,float>}
     */
    public static function monthlyExpenses(int $months = 6): array
    {
        return Expense::monthlyTotals($months);
    }

    /**
     * Perbandingan tagihan diterbitkan vs pembayaran diterima per bulan.
     *
     * @return array{labels:array<int,string>,issued:array<int,float>,received:array<int,float>}
     */
    public static function monthlyIssuedVsReceived(int $months = 6): array
    {
        $months = max(1, min(12, $months));
        $start = (new DateTimeImmutable('first day of this month'))->modify('-' . ($months - 1) . ' months');
        $end = new DateTimeImmutable('last day of this month');

        $issuedStmt = db()->prepare(
            "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS ym, COALESCE(SUM(total_amount), 0) AS total
             FROM invoices
             WHERE invoice_type = 'paket' AND status <> 'dibatalkan'
               AND issue_date BETWEEN :start_date AND :end_date
             GROUP BY ym"
        );
        $issuedStmt->execute([
            ':start_date' => $start->format('Y-m-d'),
            ':end_date'   => $end->format('Y-m-d'),
        ]);
        $issuedMap = [];
        foreach ($issuedStmt->fetchAll() as $row) {
            $issuedMap[(string) $row['ym']] = (float) $row['total'];
        }

        $receivedStmt = db()->prepare(
            "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, COALESCE(SUM(amount), 0) AS total
             FROM payments
             WHERE status = 'berhasil'
               AND payment_date BETWEEN :start_date AND :end_date
             GROUP BY ym"
        );
        $receivedStmt->execute([
            ':start_date' => $start->format('Y-m-d'),
            ':end_date'   => $end->format('Y-m-d'),
        ]);
        $receivedMap = [];
        foreach ($receivedStmt->fetchAll() as $row) {
            $receivedMap[(string) $row['ym']] = (float) $row['total'];
        }

        $labels = [];
        $issued = [];
        $received = [];
        $cursor = $start;
        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');
            $labels[] = bulan_singkat_id((int) $cursor->format('n')) . ' ' . $cursor->format('Y');
            $issued[] = $issuedMap[$key] ?? 0.0;
            $received[] = $receivedMap[$key] ?? 0.0;
            $cursor = $cursor->modify('+1 month');
        }

        return ['labels' => $labels, 'issued' => $issued, 'received' => $received];
    }
}
