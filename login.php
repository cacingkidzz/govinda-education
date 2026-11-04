<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_guest();

$error = '';
$info = '';
$username = '';
$lockedUntil = (int) ($_SESSION['_login_locked_until'] ?? 0);

if ($lockedUntil > 0 && $lockedUntil <= time()) {
    unset($_SESSION['_login_locked_until']);
    $_SESSION['_login_attempts'] = [];
    $lockedUntil = 0;
}

if (isset($_GET['logout'])) {
    $info = 'Anda telah keluar dari aplikasi. Sampai jumpa kembali.';
} elseif (isset($_GET['timeout'])) {
    $info = 'Sesi berakhir karena tidak ada aktivitas. Silakan login kembali.';
} elseif (isset($_GET['inactive'])) {
    $info = 'Sesi Anda berakhir karena akun dinonaktifkan. Hubungi administrator.';
}

if (is_post()) {
    $username = mb_substr(post_string('username'), 0, 50);
    $password = $_POST['password'] ?? '';

    if (!csrf_check()) {
        $error = 'Sesi formulir telah berakhir. Muat ulang halaman lalu coba lagi.';
    } elseif ($lockedUntil > time()) {
        $minutes = max(1, (int) ceil(($lockedUntil - time()) / 60));
        $error = 'Terlalu banyak percobaan login. Coba lagi dalam ' . $minutes . ' menit.';
    } elseif ($username === '' || !is_string($password) || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
        $error = 'Username atau password tidak sesuai, atau akun Anda tidak aktif.';
    } elseif (strlen($password) > 200) {
        $error = 'Username atau password tidak sesuai, atau akun Anda tidak aktif.';
    } else {
        $user = User::attempt($username, $password);

        if ($user !== null) {
            Auth::login($user);
            ActivityLog::record('login', 'Login berhasil melalui halaman login.');
            flash('success', 'Selamat datang, ' . $user['name'] . '.');
            $intended = Auth::pullIntended();
            redirect($intended ?? 'admin/dashboard.php');
        }

        usleep(350000);

        $attempts = $_SESSION['_login_attempts'] ?? [];
        $attempts = is_array($attempts) ? $attempts : [];
        $cutoff = time() - 600;
        $attempts = array_values(array_filter(
            $attempts,
            static fn ($timestamp) => is_int($timestamp) && $timestamp > $cutoff
        ));
        $attempts[] = time();
        $_SESSION['_login_attempts'] = $attempts;

        if (count($attempts) >= 5) {
            $_SESSION['_login_locked_until'] = time() + 300;
            $lockedUntil = (int) $_SESSION['_login_locked_until'];
            $error = 'Terlalu banyak percobaan login. Akun dikunci sementara selama 5 menit.';
        } else {
            $remaining = 5 - count($attempts);
            $error = 'Username atau password tidak sesuai, atau akun Anda tidak aktif. '
                . 'Sisa percobaan: ' . max(0, $remaining) . '.';
        }

        ActivityLog::recordGuest(
            $username,
            'login_gagal',
            'Percobaan login gagal dari halaman login.'
        );
    }
}

$pageTitle = 'Masuk';
$lockedSeconds = $lockedUntil > time() ? $lockedUntil - time() : 0;
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b2532">
    <meta name="robots" content="noindex, nofollow">
    <title>Masuk — <?= e(Profile::name()) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/login.css')) ?>">
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-brand-panel" aria-label="Tentang <?= e(Profile::name()) ?>">
            <div class="login-brand">
                <img src="<?= e(Profile::logoUrl()) ?>" alt="Logo <?= e(Profile::name()) ?>"
                     class="<?= e(Profile::logoOnDarkClass()) ?>">
                <div>
                    <span class="login-brand-name"><?= e(Profile::name()) ?></span>
                    <?php if (Profile::tagline() !== ''): ?>
                        <span class="login-brand-sub"><?= e(Profile::tagline()) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="login-hero">
                <div class="login-hero-eyebrow">Sistem Manajemen Bimbel</div>
                <h1>Satu sistem untuk <span>operasional bimbel</span> yang lebih rapi.</h1>
                <p>
                    <?= e(Profile::description() !== ''
                        ? Profile::description()
                        : 'Kelola siswa, guru, kelompok belajar, jadwal, tagihan, dan pembayaran dalam satu aplikasi yang terintegrasi.') ?>
                </p>

                <div class="login-features">
                    <div class="login-feature">
                        <span class="login-feature-icon"><i class="bi bi-people-fill"></i></span>
                        <span>Data siswa, guru, dan kelompok belajar tersimpan terpusat.</span>
                    </div>
                    <div class="login-feature">
                        <span class="login-feature-icon"><i class="bi bi-receipt-cutoff"></i></span>
                        <span>Tagihan paket 8 pertemuan dengan riwayat tarif yang jelas dan transparan.</span>
                    </div>
                    <div class="login-feature">
                        <span class="login-feature-icon"><i class="bi bi-shield-check"></i></span>
                        <span>Pembayaran wajib lunas — tanpa cicilan, lebih mudah diaudit.</span>
                    </div>
                </div>
            </div>

            <div class="login-brand-footer">
                <span>&copy; <?= e(date('Y')) ?> <?= e(Profile::name()) ?></span>
                <span>Akses internal</span>
            </div>
        </section>

        <section class="login-form-panel">
            <div class="login-form-wrap">
                <div class="login-mobile-brand">
                    <img src="<?= e(Profile::logoUrl()) ?>" alt="Logo <?= e(Profile::name()) ?>"
                         class="<?= e(Profile::logoOnLightClass()) ?>">
                    <div>
                        <strong><?= e(Profile::name()) ?></strong>
                        <?php if (Profile::tagline() !== ''): ?>
                            <span><?= e(Profile::tagline()) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="login-kicker"><i class="bi bi-lock-fill"></i> Portal Internal</div>
                <h2>Selamat datang kembali</h2>
                <p class="login-subtitle">Masuk dengan akun yang telah diberikan administrator.</p>

                <?php if ($info !== ''): ?>
                    <div class="login-alert alert-info" role="status">
                        <i class="bi bi-info-circle-fill"></i>
                        <span><?= e($info) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="login-alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($lockedSeconds > 0): ?>
                    <div class="login-alert alert-warning" role="alert">
                        <i class="bi bi-hourglass-split"></i>
                        <span>
                            Login dikunci sementara. Coba lagi dalam
                            <?= e((string) max(1, (int) ceil($lockedSeconds / 60))) ?> menit.
                        </span>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('login.php')) ?>" novalidate autocomplete="on">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?= e($username) ?>" placeholder="Masukkan username"
                                   autocomplete="username" maxlength="50" required autofocus
                                   <?= $lockedSeconds > 0 ? 'disabled' : '' ?>>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Masukkan password" autocomplete="current-password"
                                   maxlength="200" required
                                   <?= $lockedSeconds > 0 ? 'disabled' : '' ?>>
                            <button class="btn password-toggle" type="button" data-password-toggle
                                    data-target="password" aria-label="Tampilkan password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary login-submit"
                            <?= $lockedSeconds > 0 ? 'disabled' : '' ?>>
                        <i class="bi bi-box-arrow-in-right me-2"></i>Masuk ke Aplikasi
                    </button>
                </form>

                <div class="login-divider">Keamanan Akun</div>
                <div class="login-security-note">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span>
                        Jangan bagikan password. Setiap aktivitas login dan perubahan data
                        tercatat di log sistem.
                    </span>
                </div>

                <div class="login-copyright">
                    <?= e(Profile::name()) ?> v<?= e(APP_VERSION) ?> &middot; Akses terbatas untuk staf
                </div>
            </div>
        </section>
    </main>

    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
