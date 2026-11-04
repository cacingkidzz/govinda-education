<?php
declare(strict_types=1);

/**
 * Pengguna aplikasi (admin / petugas).
 */
final class User
{
    /** Hash dummy agar waktu verifikasi tetap konsisten saat username tidak ditemukan. */
    private const DUMMY_HASH = '$2y$10$DD17hhzVCKTRDOQhKTfS0u.xKtpGo..vZYM1eg140rmL09HBKiha6';

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT id, name, username, email, role, is_active, last_login_at, created_at
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $stmt = db()->prepare(
            'SELECT id, name, username, email, password_hash, role, is_active, last_login_at, created_at
             FROM users WHERE username = :username LIMIT 1'
        );
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Verifikasi kredensial. Mengembalikan data user (tanpa password) bila valid & aktif.
     */
    public static function attempt(string $username, string $password): ?array
    {
        $user = self::findByUsername($username);
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        $valid = password_verify($password, $hash);

        if (!$user || !$valid || (int) $user['is_active'] !== 1) {
            return null;
        }

        unset($user['password_hash']);
        return $user;
    }

    public static function touchLogin(int $id): void
    {
        $stmt = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function activeCount(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();
    }
}
