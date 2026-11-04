<?php
declare(strict_types=1);

/**
 * Upload gambar yang aman (siswa, guru, dan logo profil).
 *
 * - Hanya JPG/JPEG, PNG, dan WEBP.
 * - Maksimal 2 MB (MAX_UPLOAD_SIZE).
 * - MIME diverifikasi lewat finfo + getimagesize, bukan ekstensi.
 * - Nama file selalu acak, nama asli tidak pernah dipakai.
 * - File disimpan pada uploads/{folder}/ yang tidak mengeksekusi PHP.
 */
final class Upload
{
    /** Folder yang diizinkan untuk gambar aplikasi. */
    private const ALLOWED_FOLDERS = ['siswa', 'guru', 'profil'];

    /** MIME yang diterima dan ekstensi kanonisnya. */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Simpan satu file gambar.
     *
     * @param array<string, mixed> $file Entri dari $_FILES
     * @return array{ok:bool,path:string,error:string}
     */
    public static function image(array $file, string $folder, string $prefix = 'foto'): array
    {
        if (!in_array($folder, self::ALLOWED_FOLDERS, true)) {
            return self::failure('Folder upload tidak dikenali.');
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return self::failure('Tidak ada file yang dipilih.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            return self::failure(self::uploadErrorMessage($error));
        }

        $tmp = $file['tmp_name'] ?? '';
        if (!is_string($tmp) || $tmp === '' || !is_uploaded_file($tmp)) {
            return self::failure('File upload tidak valid.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            return self::failure('File yang diunggah kosong.');
        }
        if ($size > MAX_UPLOAD_SIZE) {
            return self::failure('Ukuran foto melebihi batas ' . self::maxSizeLabel() . '.');
        }

        $mime = self::detectMime($tmp);
        if ($mime === null || !isset(self::MIME_EXTENSIONS[$mime])) {
            return self::failure('Format foto harus JPG, JPEG, PNG, atau WEBP.');
        }

        $imageInfo = @getimagesize($tmp);
        if ($imageInfo === false || (int) ($imageInfo[0] ?? 0) < 1 || (int) ($imageInfo[1] ?? 0) < 1) {
            return self::failure('File tersebut bukan gambar yang valid.');
        }

        $directory = UPLOAD_PATH . '/' . $folder;
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return self::failure('Folder penyimpanan foto tidak dapat dibuat.');
        }

        $safePrefix = preg_replace('/[^a-z0-9]+/', '-', strtolower($prefix)) ?? 'foto';
        $safePrefix = trim($safePrefix, '-') !== '' ? trim($safePrefix, '-') : 'foto';
        $filename = $safePrefix . '-' . date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . self::MIME_EXTENSIONS[$mime];
        $target = $directory . '/' . $filename;

        if (!@move_uploaded_file($tmp, $target)) {
            return self::failure('Foto gagal disimpan. Silakan coba lagi.');
        }
        @chmod($target, 0644);

        return ['ok' => true, 'path' => $folder . '/' . $filename, 'error' => ''];
    }

    /**
     * Hapus foto lama. Hanya menerima path relatif yang dibuat aplikasi.
     */
    public static function remove(?string $relativePath): void
    {
        $relativePath = (string) $relativePath;
        if (!preg_match('#^(siswa|guru|profil)/[A-Za-z0-9._-]+$#', $relativePath)) {
            return;
        }

        $full = UPLOAD_PATH . '/' . $relativePath;
        $real = realpath($full);
        $uploadRoot = realpath(UPLOAD_PATH);
        if ($real === false || $uploadRoot === false || !str_starts_with(str_replace('\\', '/', $real), str_replace('\\', '/', $uploadRoot) . '/')) {
            return;
        }
        if (is_file($real)) {
            @unlink($real);
        }
    }

    public static function maxSizeLabel(): string
    {
        return (int) round(MAX_UPLOAD_SIZE / 1048576) . ' MB';
    }

    private static function detectMime(string $path): ?string
    {
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);
            return is_string($mime) && $mime !== '' ? strtolower($mime) : null;
        }

        // Fallback bila ekstensi fileinfo tidak tersedia.
        $info = @getimagesize($path);
        $mime = is_array($info) ? ($info['mime'] ?? null) : null;
        return is_string($mime) && $mime !== '' ? strtolower($mime) : null;
    }

    private static function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran foto melebihi batas ' . self::maxSizeLabel() . '.',
            UPLOAD_ERR_PARTIAL    => 'Foto hanya terunggah sebagian. Silakan ulangi.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara server tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file foto.',
            UPLOAD_ERR_EXTENSION  => 'Upload dihentikan oleh ekstensi server.',
            default               => 'Foto gagal diunggah. Silakan coba lagi.',
        };
    }

    /**
     * @return array{ok:bool,path:string,error:string}
     */
    private static function failure(string $message): array
    {
        return ['ok' => false, 'path' => '', 'error' => $message];
    }
}
