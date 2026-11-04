<?php
declare(strict_types=1);

/**
 * Pendaftaran & pembaruan data siswa secara mandiri.
 *
 * - Setiap siswa memiliki token acak unik untuk tautan pribadinya.
 * - Pendaftaran siswa baru memakai token umum terpisah di app_settings
 *   dan dapat dinonaktifkan/diganti oleh admin.
 * - Siswa hanya boleh mengubah data pribadi. Kode siswa, group, status
 *   aktif, dan tanggal bergabung selalu dikunci oleh server.
 */
final class StudentSelfService
{
    /** Panjang token hex (24 byte acak = 48 karakter). */
    public const TOKEN_LENGTH = 48;

    /** Setting token pendaftaran umum. */
    private const SETTING_ENABLED = 'registration_enabled';
    private const SETTING_TOKEN = 'registration_token';
    private const SETTING_ROTATED = 'registration_rotated_at';

    /**
     * Field data pribadi yang wajib lengkap.
     *
     * @var array<string, string>
     */
    public const REQUIRED_FIELDS = [
        'name'         => 'Nama lengkap',
        'gender'       => 'Jenis kelamin',
        'birth_place'  => 'Tempat lahir',
        'birth_date'   => 'Tanggal lahir',
        'address'      => 'Alamat',
        'phone'        => 'Nomor HP siswa',
        'parent_name'  => 'Nama orang tua/wali',
        'parent_phone' => 'Nomor HP orang tua/wali',
        'school'       => 'Sekolah',
        'school_class' => 'Kelas sekolah',
    ];

    // -----------------------------------------------------------------
    // Token siswa
    // -----------------------------------------------------------------

    public static function newToken(): string
    {
        return bin2hex(random_bytes((int) (self::TOKEN_LENGTH / 2)));
    }

    public static function isToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{' . self::TOKEN_LENGTH . '}$/', $token) === 1;
    }

    /**
     * Token pribadi siswa; dibuat otomatis bila belum ada.
     */
    public static function studentToken(int $studentId): string
    {
        $student = Student::find($studentId);
        if ($student === null) {
            throw new RuntimeException('Siswa tidak ditemukan.');
        }

        $token = trim((string) ($student['public_token'] ?? ''));
        if (self::isToken($token)) {
            return $token;
        }

        return self::rotateStudentToken($studentId);
    }

    /**
     * Buat token baru dan batalkan tautan lama.
     */
    public static function rotateStudentToken(int $studentId): string
    {
        if (Student::find($studentId) === null) {
            throw new RuntimeException('Siswa tidak ditemukan.');
        }

        $stmt = db()->prepare(
            'UPDATE students
                SET public_token = :token, public_token_created_at = NOW()
              WHERE id = :id'
        );

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $token = self::newToken();
            try {
                $stmt->execute([':token' => $token, ':id' => $studentId]);
                return $token;
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000' && $attempt < 5) {
                    continue;
                }
                throw $exception;
            }
        }

        throw new RuntimeException('Token siswa tidak dapat dibuat.');
    }

    public static function findByToken(string $token): ?array
    {
        if (!self::isToken($token)) {
            return null;
        }
        $stmt = db()->prepare('SELECT * FROM students WHERE public_token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Buat token untuk seluruh siswa aktif yang belum punya tautan.
     *
     * @return int Jumlah siswa yang mendapat token baru.
     */
    public static function generateMissingStudentTokens(): int
    {
        $rows = db()->query(
            "SELECT id FROM students
              WHERE is_active = 1
                AND (public_token IS NULL OR public_token = '')
              ORDER BY id ASC"
        )->fetchAll(PDO::FETCH_COLUMN);

        $count = 0;
        foreach ($rows as $id) {
            self::rotateStudentToken((int) $id);
            $count++;
        }
        return $count;
    }

    /**
     * Tautan pribadi siswa. String kosong bila token belum dibuat.
     *
     * @param array<string, mixed> $student
     */
    public static function studentLink(array $student): string
    {
        $token = trim((string) ($student['public_token'] ?? ''));
        if (!self::isToken($token)) {
            return '';
        }
        return absolute_url('pendaftaran.php?token=' . $token);
    }

    // -----------------------------------------------------------------
    // Pendaftaran umum (siswa baru)
    // -----------------------------------------------------------------

    private static function setting(string $key, string $default = ''): string
    {
        $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    }

    private static function putSetting(string $key, string $value): void
    {
        $stmt = db()->prepare(
            'INSERT INTO app_settings (setting_key, setting_value)
             VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([':key' => $key, ':value' => $value]);
    }

    public static function generalToken(): string
    {
        $token = trim(self::setting(self::SETTING_TOKEN));
        return self::isToken($token) ? $token : '';
    }

    public static function generalEnabled(): bool
    {
        return self::setting(self::SETTING_ENABLED, '0') === '1' && self::generalToken() !== '';
    }

    public static function generalLink(): string
    {
        $token = self::generalToken();
        return $token === '' ? '' : absolute_url('pendaftaran.php?daftar=' . $token);
    }

    /**
     * Pastikan token umum tersedia. Dipakai saat admin mengaktifkan pendaftaran.
     */
    public static function ensureGeneralToken(): string
    {
        $token = self::generalToken();
        if ($token !== '') {
            return $token;
        }
        return self::rotateGeneralToken();
    }

    public static function rotateGeneralToken(): string
    {
        $token = self::newToken();
        self::putSetting(self::SETTING_TOKEN, $token);
        self::putSetting(self::SETTING_ROTATED, date('Y-m-d H:i:s'));
        return $token;
    }

    public static function setGeneralEnabled(bool $enabled): void
    {
        if ($enabled) {
            self::ensureGeneralToken();
        }
        self::putSetting(self::SETTING_ENABLED, $enabled ? '1' : '0');
    }

    public static function generalRotatedAt(): string
    {
        return trim(self::setting(self::SETTING_ROTATED));
    }

    /**
     * Cocokkan token pendaftaran umum yang dikirim dari halaman publik.
     */
    public static function generalTokenValid(string $token): bool
    {
        $active = self::generalToken();
        return $active !== '' && self::isToken($token) && hash_equals($active, $token);
    }

    // -----------------------------------------------------------------
    // Validasi & kelengkapan data
    // -----------------------------------------------------------------

    /**
     * Ambil field yang boleh diubah siswa dari input mentah.
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function collect(array $input): array
    {
        $value = static function (string $key) use ($input): string {
            $raw = $input[$key] ?? '';
            return is_string($raw) ? trim($raw) : '';
        };

        return [
            'name'         => mb_substr($value('name'), 0, 120),
            'gender'       => strtoupper($value('gender')),
            'birth_place'  => mb_substr($value('birth_place'), 0, 100),
            'birth_date'   => $value('birth_date'),
            'address'      => mb_substr($value('address'), 0, 500),
            'phone'        => mb_substr($value('phone'), 0, 30),
            'parent_name'  => mb_substr($value('parent_name'), 0, 120),
            'parent_phone' => mb_substr($value('parent_phone'), 0, 30),
            'school'       => mb_substr($value('school'), 0, 150),
            'school_class' => mb_substr($value('school_class'), 0, 50),
        ];
    }

    /**
     * @param array<string, string> $data
     * @return array<string, string>
     */
    public static function validate(array $data): array
    {
        $errors = [];
        $today = date('Y-m-d');

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $errors['name'] = 'Nama lengkap wajib diisi.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'Nama lengkap minimal 2 karakter.';
        }

        if (!in_array($data['gender'] ?? '', ['L', 'P'], true)) {
            $errors['gender'] = 'Jenis kelamin wajib dipilih.';
        }

        if (trim($data['birth_place'] ?? '') === '') {
            $errors['birth_place'] = 'Tempat lahir wajib diisi.';
        }

        $birthDate = trim($data['birth_date'] ?? '');
        if ($birthDate === '') {
            $errors['birth_date'] = 'Tanggal lahir wajib diisi.';
        } elseif (!valid_date($birthDate)) {
            $errors['birth_date'] = 'Tanggal lahir tidak valid.';
        } elseif ($birthDate > $today) {
            $errors['birth_date'] = 'Tanggal lahir tidak boleh melebihi hari ini.';
        }

        if (trim($data['address'] ?? '') === '') {
            $errors['address'] = 'Alamat wajib diisi.';
        }

        $phone = trim($data['phone'] ?? '');
        if ($phone === '') {
            $errors['phone'] = 'Nomor HP siswa wajib diisi.';
        } elseif (!valid_phone($phone)) {
            $errors['phone'] = 'Nomor HP siswa tidak valid. Gunakan format 08xxxxxxxxxx.';
        }

        if (trim($data['parent_name'] ?? '') === '') {
            $errors['parent_name'] = 'Nama orang tua/wali wajib diisi.';
        }

        $parentPhone = trim($data['parent_phone'] ?? '');
        if ($parentPhone === '') {
            $errors['parent_phone'] = 'Nomor HP orang tua/wali wajib diisi.';
        } elseif (!valid_phone($parentPhone)) {
            $errors['parent_phone'] = 'Nomor HP orang tua/wali tidak valid. Gunakan format 08xxxxxxxxxx.';
        }

        if (trim($data['school'] ?? '') === '') {
            $errors['school'] = 'Nama sekolah wajib diisi.';
        }

        if (trim($data['school_class'] ?? '') === '') {
            $errors['school_class'] = 'Kelas sekolah wajib diisi.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed>|null $student
     * @return array{percent:int,missing:array<int, string>,complete:bool,checked:int}
     */
    public static function completeness(?array $student): array
    {
        $missing = [];
        foreach (self::REQUIRED_FIELDS as $field => $label) {
            $value = trim((string) ($student[$field] ?? ''));
            if ($value === '') {
                $missing[] = $label;
            }
        }

        $total = count(self::REQUIRED_FIELDS);
        $done = $total - count($missing);

        return [
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
            'missing' => $missing,
            'complete' => $missing === [],
            'checked' => $done,
        ];
    }

    // -----------------------------------------------------------------
    // Penyimpanan
    // -----------------------------------------------------------------

    /**
     * Perbarui data pribadi siswa. Kolom terkunci tidak disentuh.
     *
     * @param array<string, string> $data Sudah lolos validate()
     * @return array{old_photo:?string}
     */
    public static function updateStudent(int $studentId, array $data, ?string $newPhoto): array
    {
        $existing = Student::find($studentId);
        if ($existing === null) {
            throw new RuntimeException('Siswa tidak ditemukan.');
        }

        $photo = $newPhoto !== null && $newPhoto !== '' ? $newPhoto : (string) ($existing['photo'] ?? '');
        $complete = self::completeness(array_merge($existing, $data))['complete'];

        $stmt = db()->prepare(
            'UPDATE students SET
                name = :name,
                gender = :gender,
                birth_place = :birth_place,
                birth_date = :birth_date,
                address = :address,
                phone = :phone,
                parent_name = :parent_name,
                parent_phone = :parent_phone,
                school = :school,
                school_class = :school_class,
                photo = :photo,
                self_updated_at = NOW(),
                profile_completed_at = ' . ($complete ? 'NOW()' : 'profile_completed_at') . '
              WHERE id = :id'
        );

        $stmt->execute([
            ':name'         => $data['name'],
            ':gender'       => $data['gender'],
            ':birth_place'  => $data['birth_place'],
            ':birth_date'   => $data['birth_date'],
            ':address'      => $data['address'],
            ':phone'        => normalize_phone($data['phone']),
            ':parent_name'  => $data['parent_name'],
            ':parent_phone' => normalize_phone($data['parent_phone']),
            ':school'       => $data['school'],
            ':school_class' => $data['school_class'],
            ':photo'        => $photo !== '' ? $photo : null,
            ':id'           => $studentId,
        ]);

        return [
            'old_photo' => ($newPhoto !== null && $newPhoto !== '' && !empty($existing['photo']))
                ? (string) $existing['photo']
                : null,
        ];
    }

    /**
     * Daftarkan siswa baru dari halaman publik.
     *
     * @param array<string, string> $data Sudah lolos validate()
     * @return array{id:int,code:string,token:string}
     */
    public static function createStudent(array $data, ?string $photo): array
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $created = Student::create([
                'name'         => $data['name'],
                'gender'       => $data['gender'],
                'birth_place'  => $data['birth_place'],
                'birth_date'   => $data['birth_date'],
                'address'      => $data['address'],
                'phone'        => normalize_phone($data['phone']),
                'parent_name'  => $data['parent_name'],
                'parent_phone' => normalize_phone($data['parent_phone']),
                'school'       => $data['school'],
                'school_class' => $data['school_class'],
                'join_date'    => date('Y-m-d'),
                'photo'        => $photo,
                'is_active'    => 1,
            ]);

            $token = self::newToken();
            $stmt = $pdo->prepare(
                'UPDATE students
                    SET public_token = :token,
                        public_token_created_at = NOW(),
                        self_updated_at = NOW(),
                        profile_completed_at = NOW()
                  WHERE id = :id'
            );
            $stmt->execute([':token' => $token, ':id' => $created['id']]);

            $pdo->commit();
            return ['id' => $created['id'], 'code' => $created['code'], 'token' => $token];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
