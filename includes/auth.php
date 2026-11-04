<?php
declare(strict_types=1);

/**
 * Autentikasi berbasis session + pemeriksaan role.
 * Role dan status aktif selalu dibaca ulang dari database.
 */
final class Auth
{
    private static bool $loaded = false;
    private static ?array $user = null;
    private static ?string $event = null;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        $userId = $_SESSION['auth_user_id'] ?? null;
        if (!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) {
            return null;
        }

        $lastActivity = (int) ($_SESSION['_last_activity'] ?? 0);
        if ($lastActivity > 0 && (time() - $lastActivity) > SESSION_IDLE_TIMEOUT) {
            self::destroySession();
            self::$event = 'timeout';
            return null;
        }

        $user = User::findById((int) $userId);
        if ($user === null || (int) $user['is_active'] !== 1) {
            self::destroySession();
            self::$event = 'inactive';
            return null;
        }

        $_SESSION['_last_activity'] = time();
        $_SESSION['auth_user'] = [
            'id'       => (int) $user['id'],
            'name'     => (string) $user['name'],
            'username' => (string) $user['username'],
            'role'     => (string) $user['role'],
        ];

        $lastRegenerate = (int) ($_SESSION['_last_regenerate'] ?? 0);
        if ($lastRegenerate === 0 || (time() - $lastRegenerate) > 1800) {
            // Jangan memanggil session_regenerate_id() bila header sudah terkirim:
            // PHP akan memunculkan warning dan ID sesi tidak berubah.
            if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                session_regenerate_id(true);
            }
            $_SESSION['_last_regenerate'] = time();
        }

        self::$user = $user;
        return self::$user;
    }

    public static function login(array $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['auth_user_id'] = (int) $user['id'];
        $_SESSION['auth_user'] = [
            'id'       => (int) $user['id'],
            'name'     => (string) $user['name'],
            'username' => (string) $user['username'],
            'role'     => (string) $user['role'],
        ];
        $_SESSION['_last_activity'] = time();
        $_SESSION['_last_regenerate'] = time();
        $_SESSION['_login_attempts'] = [];
        unset($_SESSION['_login_locked_until']);

        csrf_rotate();
        User::touchLogin((int) $user['id']);

        self::$loaded = true;
        self::$user = $user;
    }

    public static function logout(bool $writeLog = true): void
    {
        if ($writeLog && self::check()) {
            ActivityLog::record('logout', 'Pengguna keluar dari aplikasi.');
        }
        self::destroySession();
    }

    private static function destroySession(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$loaded = true;
        self::$user = null;
    }

    public static function event(): ?string
    {
        return self::$event;
    }

    public static function intended(string $url): void
    {
        if ($url !== '' && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $_SESSION['_intended_url'] = $url;
        }
    }

    public static function pullIntended(): ?string
    {
        $url = $_SESSION['_intended_url'] ?? null;
        unset($_SESSION['_intended_url']);
        if (!is_string($url) || $url === '' || !str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }
        if (!str_starts_with($url, BASE_URL . '/admin/')) {
            return null;
        }
        return BASE_URL !== '' ? substr($url, strlen(BASE_URL)) : $url;
    }
}

// ---------------------------------------------------------------------
// Fungsi pembantu akses
// ---------------------------------------------------------------------

function current_user(): ?array
{
    return Auth::user();
}

function auth_check(): bool
{
    return Auth::check();
}

function is_admin(): bool
{
    $user = Auth::user();
    return $user !== null && $user['role'] === 'admin';
}

function require_login(): void
{
    if (Auth::check()) {
        return;
    }

    $event = Auth::event();
    if ($event === 'timeout') {
        redirect('login.php?timeout=1');
    }
    if ($event === 'inactive') {
        redirect('login.php?inactive=1');
    }

    $requested = (string) ($_SERVER['REQUEST_URI'] ?? '');
    Auth::intended($requested);
    redirect('login.php');
}

function require_guest(): void
{
    if (Auth::check()) {
        redirect('admin/dashboard.php');
    }
}

/**
 * @param string|array<int, string> $roles
 */
function require_role(string|array $roles): void
{
    require_login();
    $allowed = is_array($roles) ? $roles : [$roles];
    $user = Auth::user();
    if ($user === null || !in_array($user['role'], $allowed, true)) {
        abort_403();
    }
}

function require_admin(): void
{
    require_role('admin');
}
