<?php
declare(strict_types=1);

/**
 * Profil bimbel: identitas, kontak, deskripsi, dan logo.
 *
 * Nilai disimpan pada tabel app_settings (key/value) agar tidak menambah
 * tabel baru. Logo kustom disimpan di uploads/profil/ dan path relatifnya
 * dicatat pada setting app_logo.
 */
final class Profile
{
    /** Setting yang dikelola melalui modul profil. */
    public const KEYS = [
        'app_name',
        'app_tagline',
        'app_description',
        'app_address',
        'app_phone',
        'app_email',
        'app_website',
        'app_logo',
    ];

    /** @var array<string, string>|null */
    private static ?array $cache = null;

    /** @var bool|null Cache apakah logo kustom tergolong terang. */
    private static ?bool $logoIsLightCache = null;

    /**
     * Nilai default bila setting belum tersedia di database.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'app_name'        => APP_NAME,
            'app_tagline'     => APP_TAGLINE,
            'app_description' => 'Kelola siswa, guru, kelompok belajar, jadwal, tagihan, '
                . 'dan pembayaran dalam satu aplikasi yang terintegrasi.',
            'app_address'     => '',
            'app_phone'       => '',
            'app_email'       => '',
            'app_website'     => '',
            'app_logo'        => '',
        ];
    }

    /**
     * Seluruh nilai profil yang sudah digabung dengan default.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $data = self::defaults();
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll();
            foreach ($rows as $row) {
                $key = (string) ($row['setting_key'] ?? '');
                if ($key !== '' && array_key_exists($key, $data)) {
                    $data[$key] = (string) ($row['setting_value'] ?? '');
                }
            }
        } catch (Throwable $exception) {
            self::logError($exception);
        }

        self::$cache = $data;
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $data = self::all();
        return $data[$key] ?? $default;
    }

    public static function name(): string
    {
        $name = trim(self::get('app_name'));
        return $name !== '' ? $name : APP_NAME;
    }

    public static function tagline(): string
    {
        return trim(self::get('app_tagline'));
    }

    public static function description(): string
    {
        return trim(self::get('app_description'));
    }

    public static function address(): string
    {
        return trim(self::get('app_address'));
    }

    public static function phone(): string
    {
        return trim(self::get('app_phone'));
    }

    public static function email(): string
    {
        return trim(self::get('app_email'));
    }

    public static function website(): string
    {
        return trim(self::get('app_website'));
    }

    /**
     * Path relatif logo kustom yang benar-benar ada di folder upload.
     */
    public static function logoPath(): string
    {
        $path = self::normalizeLogoPath(self::get('app_logo'));
        if ($path === '') {
            return '';
        }
        return is_file(UPLOAD_PATH . '/' . $path) ? $path : '';
    }

    public static function hasCustomLogo(): bool
    {
        return self::logoPath() !== '';
    }

    /**
     * URL logo yang dipakai layout, login, dan dokumen cetak.
     * Bila belum ada logo kustom, gunakan logo bawaan aplikasi.
     */
    public static function logoUrl(): string
    {
        $path = self::logoPath();
        if ($path === '') {
            return self::defaultLogoUrl();
        }

        $full = UPLOAD_PATH . '/' . $path;
        return url('uploads/' . $path) . '?v=' . rawurlencode((string) filemtime($full));
    }

    public static function defaultLogoUrl(): string
    {
        return url('assets/img/logo-mark.svg');
    }

    /**
     * Apakah logo kustom didominasi warna terang?
     * Dipakai untuk memilih latar kontras pada halaman terang/gelap.
     */
    public static function logoIsLight(): bool
    {
        if (self::$logoIsLightCache !== null) {
            return self::$logoIsLightCache;
        }

        $path = self::logoPath();
        if ($path === '') {
            return self::$logoIsLightCache = false;
        }

        $full = UPLOAD_PATH . '/' . $path;
        $info = @getimagesize($full);
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($full),
            'image/png'  => @imagecreatefrompng($full),
            'image/webp' => @imagecreatefromwebp($full),
            default      => false,
        };
        if (!$image) {
            return self::$logoIsLightCache = false;
        }

        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $step = max(1, (int) floor(max($width, $height) / 64));
        $sum = 0.0;
        $samples = 0;

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $color = imagecolorat($image, $x, $y);
                if ($mime === 'image/png' || $mime === 'image/webp') {
                    $alpha = ($color >> 24) & 0x7F;
                    if ($alpha >= 120) {
                        continue;
                    }
                }
                $red = ($color >> 16) & 0xFF;
                $green = ($color >> 8) & 0xFF;
                $blue = $color & 0xFF;
                $sum += (0.2126 * $red + 0.7152 * $green + 0.0722 * $blue) / 255;
                $samples++;
            }
        }
        imagedestroy($image);

        return self::$logoIsLightCache = $samples > 0 && ($sum / $samples) >= 0.65;
    }

    /**
     * Kelas latar untuk logo pada permukaan gelap (sidebar, hero, panel login).
     */
    public static function logoOnDarkClass(): string
    {
        return self::hasCustomLogo() && !self::logoIsLight() ? 'logo-tile-light' : '';
    }

    /**
     * Kelas latar untuk logo pada permukaan terang (profil, kertas cetak).
     */
    public static function logoOnLightClass(): string
    {
        return self::hasCustomLogo() && self::logoIsLight() ? 'logo-tile-dark' : '';
    }

    /**
     * URL situs yang aman (hanya http/https) atau string kosong.
     */
    public static function websiteUrl(): string
    {
        return self::normalizeWebsite(self::website());
    }

    /**
     * Label situs tanpa skema, untuk ditampilkan pada halaman profil.
     */
    public static function websiteLabel(): string
    {
        $website = self::website();
        if ($website === '') {
            return '';
        }
        return (string) preg_replace('#^https?://#i', '', rtrim($website, '/'));
    }

    /**
     * Simpan seluruh setting profil. Nilai yang tidak dikenal diabaikan.
     *
     * @param array<string, mixed> $values
     */
    public static function save(array $values): void
    {
        $data = self::all();
        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $values)) {
                $data[$key] = trim((string) $values[$key]);
            }
        }
        $data['app_logo'] = self::normalizeLogoPath($data['app_logo']);

        $pdo = db();
        $stmt = $pdo->prepare(
            'INSERT INTO app_settings (setting_key, setting_value)
             VALUES (:setting_key, :setting_value)
             ON DUPLICATE KEY UPDATE setting_value = :setting_value_update'
        );

        $startedTransaction = false;
        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $startedTransaction = true;
            }

            foreach (self::KEYS as $key) {
                $stmt->execute([
                    ':setting_key'          => $key,
                    ':setting_value'        => $data[$key],
                    ':setting_value_update' => $data[$key],
                ]);
            }

            if ($startedTransaction) {
                $pdo->commit();
            }
            self::$cache = $data;
            self::$logoIsLightCache = null;
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function flushCache(): void
    {
        self::$cache = null;
        self::$logoIsLightCache = null;
    }

    /**
     * Validasi input formulir profil.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public static function validate(array $data): array
    {
        $errors = [];

        $name = trim((string) ($data['app_name'] ?? ''));
        if ($name === '') {
            $errors['app_name'] = 'Nama bimbel wajib diisi.';
        } elseif (mb_strlen($name) < 2) {
            $errors['app_name'] = 'Nama bimbel minimal 2 karakter.';
        } elseif (mb_strlen($name) > 120) {
            $errors['app_name'] = 'Nama bimbel maksimal 120 karakter.';
        }

        if (mb_strlen(trim((string) ($data['app_tagline'] ?? ''))) > 150) {
            $errors['app_tagline'] = 'Tagline maksimal 150 karakter.';
        }

        if (mb_strlen(trim((string) ($data['app_description'] ?? ''))) > 500) {
            $errors['app_description'] = 'Deskripsi singkat maksimal 500 karakter.';
        }

        if (mb_strlen(trim((string) ($data['app_address'] ?? ''))) > 500) {
            $errors['app_address'] = 'Alamat maksimal 500 karakter.';
        }

        $phone = trim((string) ($data['app_phone'] ?? ''));
        if ($phone !== '' && !valid_phone($phone)) {
            $errors['app_phone'] = 'Nomor telepon tidak valid. Gunakan format 08xxxxxxxxxx.';
        }

        $email = trim((string) ($data['app_email'] ?? ''));
        if ($email !== '') {
            if (mb_strlen($email) > 150) {
                $errors['app_email'] = 'Email maksimal 150 karakter.';
            } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors['app_email'] = 'Alamat email tidak valid.';
            }
        }

        $website = trim((string) ($data['app_website'] ?? ''));
        if ($website !== '') {
            if (mb_strlen($website) > 200) {
                $errors['app_website'] = 'Alamat situs maksimal 200 karakter.';
            } elseif (self::normalizeWebsite($website) === '') {
                $errors['app_website'] = 'Alamat situs tidak valid. Gunakan http:// atau https://.';
            }
        }

        return $errors;
    }

    public static function normalizeLogoPath(?string $path): string
    {
        $path = trim((string) $path);
        return preg_match('#^profil/[A-Za-z0-9._-]+$#', $path) === 1 ? $path : '';
    }

    public static function normalizeWebsite(string $website): string
    {
        $website = trim($website);
        if ($website === '') {
            return '';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $website) !== 1) {
            $website = 'https://' . $website;
        }

        $scheme = strtolower((string) parse_url($website, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        return filter_var($website, FILTER_VALIDATE_URL) !== false ? $website : '';
    }

    /**
     * Waktu perubahan terakhir salah satu setting profil.
     */
    public static function lastUpdated(): ?string
    {
        try {
            $placeholders = implode(',', array_fill(0, count(self::KEYS), '?'));
            $stmt = db()->prepare(
                'SELECT MAX(updated_at) FROM app_settings WHERE setting_key IN (' . $placeholders . ')'
            );
            $stmt->execute(self::KEYS);
            $value = $stmt->fetchColumn();
            return is_string($value) && $value !== '' ? $value : null;
        } catch (Throwable $exception) {
            self::logError($exception);
            return null;
        }
    }

    private static function logError(Throwable $exception): void
    {
        @file_put_contents(
            LOG_PATH . '/app.log',
            '[' . date('Y-m-d H:i:s') . '] PROFILE ERROR: ' . $exception->getMessage() . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
