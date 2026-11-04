<?php
declare(strict_types=1);

/**
 * Proteksi CSRF berbasis session.
 * Token wajib disertakan pada seluruh request POST yang mengubah data.
 */

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf']) || strlen($_SESSION['_csrf']) < 32) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(?string $token = null): bool
{
    $token ??= $_POST['_csrf'] ?? '';
    if (!is_string($token) || $token === '' || !isset($_SESSION['_csrf'])) {
        return false;
    }
    return hash_equals((string) $_SESSION['_csrf'], $token);
}

function csrf_rotate(): void
{
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}

/**
 * Verifikasi CSRF untuk request POST. Jika gagal: 419.
 */
function csrf_require(): void
{
    if (!is_post() || !csrf_check()) {
        render_error_page(
            419,
            'Sesi Formulir Kedaluwarsa',
            'Halaman telah lama dibuka atau token keamanan tidak valid. Muat ulang halaman lalu coba lagi.'
        );
    }
}
