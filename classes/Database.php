<?php
declare(strict_types=1);

/**
 * Koneksi database terpusat (PDO).
 * Semua query aplikasi WAJIB memakai prepared statements.
 */
final class Database
{
    private static ?PDO $pdo = null;

    private function __construct()
    {
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        try {
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ]);
            self::$pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $exception) {
            @file_put_contents(
                LOG_PATH . '/app.log',
                sprintf(
                    "[%s] DB CONNECTION ERROR: %s\n",
                    date('Y-m-d H:i:s'),
                    $exception->getMessage()
                ),
                FILE_APPEND | LOCK_EX
            );
            throw new RuntimeException('Koneksi database gagal. Periksa konfigurasi database.');
        }

        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
