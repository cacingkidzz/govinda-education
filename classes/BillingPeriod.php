<?php
declare(strict_types=1);

/**
 * Periode tagihan bulanan.
 */
final class BillingPeriod
{
    public static function findForMonth(int $month, int $year): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM billing_periods WHERE month = :month AND year = :year LIMIT 1'
        );
        $stmt->execute([':month' => $month, ':year' => $year]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM billing_periods WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function latest(): ?array
    {
        $row = db()->query(
            'SELECT * FROM billing_periods ORDER BY year DESC, month DESC LIMIT 1'
        )->fetch();
        return $row ?: null;
    }
}
