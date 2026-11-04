<?php
declare(strict_types=1);

/**
 * Pengguna aplikasi (admin / petugas).
 *
 * Aturan:
 * - Username unik, huruf kecil, tanpa spasi.
 * - Email opsional tetapi unik bila diisi.
 * - Pengguna tidak dihapus permanen; cukup dinonaktifkan.
 * - Minimal satu administrator aktif harus selalu tersedia.
 */
final class User
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_PETUGAS = 'petugas';
    public const ROLES = [self::ROLE_ADMIN, self::ROLE_PETUGAS];

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
     * Daftar pengguna untuk halaman manajemen.
     *
     * @param array{role?: string, status?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function all(array $filters = []): array
    {
        $sql = 'SELECT id, name, username, email, role, is_active, last_login_at, created_at
                FROM users WHERE 1 = 1';
        $params = [];

        $role = (string) ($filters['role'] ?? '');
        if ($role !== '' && in_array($role, self::ROLES, true)) {
            $sql .= ' AND role = :role';
            $params[':role'] = $role;
        }

        $status = (string) ($filters['status'] ?? '');
        if ($status === '0' || $status === '1') {
            $sql .= ' AND is_active = :status';
            $params[':status'] = (int) $status;
        }

        $sql .= " ORDER BY is_active DESC, FIELD(role, 'admin', 'petugas'), name ASC";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function countAll(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public static function countActive(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();
    }

    public static function countInactive(): int
    {
        return (int) db()->query('SELECT COUNT(*) FROM users WHERE is_active = 0')->fetchColumn();
    }

    /**
     * Jumlah administrator aktif, dapat mengecualikan satu akun
     * (untuk memastikan perubahan tidak menyisakan nol admin).
     */
    public static function activeAdminCount(int $excludeId = 0): int
    {
        $sql = "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1";
        $params = [];
        if ($excludeId > 0) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $excludeId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function usernameExists(string $username, int $ignoreId = 0): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = :username';
        $params = [':username' => $username];
        if ($ignoreId > 0) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignoreId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function emailExists(string $email, int $ignoreId = 0): bool
    {
        if ($email === '') {
            return false;
        }
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $params = [':email' => $email];
        if ($ignoreId > 0) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignoreId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Username disimpan huruf kecil tanpa spasi di tepi. */
    public static function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    /**
     * Validasi data pengguna. Dipakai endpoint dan pengujian.
     *
     * @param array<string, mixed> $data
     * @return array<string, string> pesan error per field (kosong = valid)
     */
    public static function validate(array $data, int $ignoreId = 0, bool $withPassword = false): array
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Nama lengkap wajib diisi.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'Nama minimal 2 karakter.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Nama maksimal 100 karakter.';
        }

        $username = self::normalizeUsername((string) ($data['username'] ?? ''));
        if ($username === '') {
            $errors['username'] = 'Username wajib diisi.';
        } elseif (mb_strlen($username) < 3) {
            $errors['username'] = 'Username minimal 3 karakter.';
        } elseif (mb_strlen($username) > 30) {
            $errors['username'] = 'Username maksimal 30 karakter.';
        } elseif (preg_match('/^[a-z][a-z0-9._-]*$/', $username) !== 1) {
            $errors['username'] = 'Username hanya boleh huruf kecil, angka, titik, garis bawah, dan tanda hubung, serta diawali huruf.';
        } elseif (self::usernameExists($username, $ignoreId)) {
            $errors['username'] = 'Username tersebut sudah dipakai pengguna lain.';
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '') {
            if (mb_strlen($email) > 120) {
                $errors['email'] = 'Email maksimal 120 karakter.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Format email tidak valid.';
            } elseif (self::emailExists($email, $ignoreId)) {
                $errors['email'] = 'Email tersebut sudah dipakai pengguna lain.';
            }
        }

        $role = (string) ($data['role'] ?? '');
        if (!in_array($role, self::ROLES, true)) {
            $errors['role'] = 'Peran pengguna wajib dipilih.';
        }

        if ($withPassword) {
            $password = (string) ($data['password'] ?? '');
            $confirm = (string) ($data['password_confirm'] ?? '');

            foreach (self::passwordErrors($password) as $message) {
                $errors['password'] = $message;
                break;
            }

            if (!isset($errors['password']) && $password !== $confirm) {
                $errors['password_confirm'] = 'Konfirmasi password tidak sama.';
            }
        }

        return $errors;
    }

    /**
     * Kebijakan password: 8-72 karakter, mengandung huruf dan angka.
     *
     * @return array<int, string>
     */
    public static function passwordErrors(string $password): array
    {
        if ($password === '') {
            return ['Password wajib diisi.'];
        }
        if (mb_strlen($password) < 8) {
            return ['Password minimal 8 karakter.'];
        }
        if (strlen($password) > 72) {
            return ['Password maksimal 72 karakter.'];
        }
        if (preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/[0-9]/', $password) !== 1) {
            return ['Password harus mengandung huruf dan angka.'];
        }
        return [];
    }

    /**
     * @param array<string, mixed> $data sudah lolos User::validate()
     */
    public static function create(array $data, string $password): int
    {
        $stmt = db()->prepare(
            'INSERT INTO users (name, username, email, password_hash, role, is_active)
             VALUES (:name, :username, :email, :password_hash, :role, :is_active)'
        );
        $stmt->execute([
            ':name'          => trim((string) $data['name']),
            ':username'      => self::normalizeUsername((string) $data['username']),
            ':email'         => trim((string) ($data['email'] ?? '')) !== '' ? trim((string) $data['email']) : null,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':role'          => (string) $data['role'],
            ':is_active'     => (int) ($data['is_active'] ?? 1) === 1 ? 1 : 0,
        ]);

        return (int) db()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data sudah lolos User::validate()
     */
    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE users
             SET name = :name, username = :username, email = :email,
                 role = :role, is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute([
            ':name'      => trim((string) $data['name']),
            ':username'  => self::normalizeUsername((string) $data['username']),
            ':email'     => trim((string) ($data['email'] ?? '')) !== '' ? trim((string) $data['email']) : null,
            ':role'      => (string) $data['role'],
            ':is_active' => (int) ($data['is_active'] ?? 1) === 1 ? 1 : 0,
            ':id'        => $id,
        ]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $stmt = db()->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
        $stmt->execute([':is_active' => $active ? 1 : 0, ':id' => $id]);
    }

    public static function setPassword(int $id, string $password): void
    {
        $stmt = db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute([
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':id'            => $id,
        ]);
    }

    /**
     * Verifikasi kredensial. Mengembalikan data user (tanpa password) bila valid & aktif.
     */
    public static function attempt(string $username, string $password): ?array
    {
        $user = self::findByUsername(self::normalizeUsername($username));
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
