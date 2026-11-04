<?php
declare(strict_types=1);

/**
 * Halaman publik pendaftaran & pembaruan data siswa.
 *
 * Mode:
 * - pendaftaran.php?token=...  → siswa terdaftar melengkapi datanya.
 * - pendaftaran.php?daftar=... → pendaftaran siswa baru (dapat ditutup admin).
 */

require_once __DIR__ . '/includes/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

/**
 * Ambil token hex dari nilai mentah (token atau tautan lengkap).
 */
$cleanToken = static function (string $value): string {
    $value = trim($value);
    if (preg_match('~[?&](?:token|daftar)=([A-Fa-f0-9]{48})~', $value, $matches) === 1) {
        return strtolower($matches[1]);
    }
    return preg_match('/^[A-Fa-f0-9]{48}$/', $value) === 1 ? strtolower($value) : '';
};

$token = $cleanToken(get_string('token'));
$daftar = $cleanToken(get_string('daftar'));
$rawToken = get_string('token');
$rawDaftar = get_string('daftar');

$view = 'landing';
$student = null;
$isNew = false;
$errors = [];
$old = [];
$invalidMessage = '';
$success = null;
$q = get_string('q');

// ---------------------------------------------------------------------
// Tempel tautan / token di halaman awal
// ---------------------------------------------------------------------
if (!is_post() && $q !== '') {
    if (preg_match('~[?&]daftar=([A-Fa-f0-9]{48})~', $q, $matches) === 1) {
        redirect('pendaftaran.php?daftar=' . strtolower($matches[1]));
    }
    if (preg_match('~[?&]token=([A-Fa-f0-9]{48})~', $q, $matches) === 1) {
        redirect('pendaftaran.php?token=' . strtolower($matches[1]));
    }
    $pasteToken = $cleanToken($q);
    if ($pasteToken !== '') {
        if (StudentSelfService::generalTokenValid($pasteToken)) {
            redirect('pendaftaran.php?daftar=' . $pasteToken);
        }
        redirect('pendaftaran.php?token=' . $pasteToken);
    }
    $errors['q'] = 'Tautan atau kode tidak dikenali. Salin ulang tautan dari admin.';
}

// ---------------------------------------------------------------------
// Proses kiriman formulir
// ---------------------------------------------------------------------
if (is_post()) {
    $mode = post_string('mode');
    $old = $_POST;

    if ($mode === 'student') {
        $token = $cleanToken(post_string('token'));
        $student = $token !== '' ? StudentSelfService::findByToken($token) : null;
        if ($student === null) {
            $view = 'invalid';
            $invalidMessage = 'Tautan pribadi tidak valid atau sudah diganti. Minta tautan baru kepada admin bimbel.';
        } elseif ((int) $student['is_active'] !== 1) {
            $view = 'invalid';
            $invalidMessage = 'Tautan ini sudah tidak aktif. Hubungi admin bimbel untuk informasi lebih lanjut.';
        } else {
            $view = 'form';
        }
    } elseif ($mode === 'new') {
        $daftar = $cleanToken(post_string('daftar'));
        if (!StudentSelfService::generalEnabled() || !StudentSelfService::generalTokenValid($daftar)) {
            $view = 'invalid';
            $invalidMessage = 'Pendaftaran siswa baru sedang ditutup atau tautan tidak valid. Hubungi admin bimbel.';
        } else {
            $view = 'form';
            $isNew = true;
        }
    } else {
        $view = 'invalid';
        $invalidMessage = 'Permintaan tidak dikenali. Buka kembali tautan yang diberikan admin.';
    }

    if ($view === 'form') {
        $now = time();
        $attempts = $_SESSION['_self_reg_attempts'] ?? [];
        $attempts = is_array($attempts) ? $attempts : [];
        $attempts = array_values(array_filter(
            $attempts,
            static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - 600
        ));

        if (count($attempts) >= 20) {
            $errors['_form'] = 'Terlalu banyak percobaan dalam waktu singkat. Tunggu beberapa menit lalu coba lagi.';
        } else {
            $attempts[] = $now;
            $_SESSION['_self_reg_attempts'] = $attempts;

            if (!csrf_check()) {
                $errors['_form'] = 'Sesi formulir telah berakhir. Muat ulang halaman lalu coba lagi.';
            }

            if (post_string('website') !== '') {
                $errors['_form'] = 'Data tidak dapat diproses. Muat ulang halaman lalu coba lagi.';
            }

            $data = StudentSelfService::collect($_POST);
            $errors = array_merge($errors, StudentSelfService::validate($data));

            $photoPath = null;
            $photoOld = null;
            $file = $_FILES['photo'] ?? null;
            if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $upload = Upload::image($file, 'siswa', 'siswa');
                if ($upload['ok']) {
                    $photoPath = $upload['path'];
                } else {
                    $errors['photo'] = $upload['error'];
                }
            }

            if ($errors === []) {
                try {
                    if ($student !== null) {
                        $saved = StudentSelfService::updateStudent((int) $student['id'], $data, $photoPath);
                        if ($saved['old_photo'] !== null) {
                            Upload::remove($saved['old_photo']);
                        }

                        $nextToken = (string) ($student['public_token'] ?? $token);
                        ActivityLog::recordGuest(
                            (string) $student['code'],
                            'pendaftaran_mandiri',
                            'Siswa memperbarui data pribadi melalui tautan mandiri.'
                        );
                        $_SESSION['_self_reg_success'] = [
                            'type'  => 'update',
                            'name'  => $data['name'],
                            'code'  => (string) $student['code'],
                            'link'  => absolute_url('pendaftaran.php?token=' . $nextToken),
                        ];
                    } else {
                        $created = StudentSelfService::createStudent($data, $photoPath);
                        ActivityLog::recordGuest(
                            $created['code'],
                            'pendaftaran_mandiri',
                            'Siswa baru mendaftar melalui halaman pendaftaran mandiri.'
                        );
                        $_SESSION['_self_reg_success'] = [
                            'type' => 'new',
                            'name' => $data['name'],
                            'code' => $created['code'],
                            'link' => absolute_url('pendaftaran.php?token=' . $created['token']),
                        ];
                    }

                    redirect('pendaftaran.php?selesai=1');
                } catch (Throwable $exception) {
                    if ($photoPath !== null) {
                        Upload::remove($photoPath);
                    }
                    @file_put_contents(
                        LOG_PATH . '/app.log',
                        '[' . date('Y-m-d H:i:s') . '] PENDAFTARAN MANDIRI ERROR: '
                        . $exception->getMessage() . PHP_EOL,
                        FILE_APPEND | LOCK_EX
                    );
                    $errors['_form'] = 'Data belum dapat disimpan. Silakan coba beberapa saat lagi.';
                }
            } elseif ($photoPath !== null) {
                Upload::remove($photoPath);
            }
        }
    }
} else {
    // -----------------------------------------------------------------
    // Tampilan GET
    // -----------------------------------------------------------------
    if (isset($_GET['selesai'])) {
        $saved = $_SESSION['_self_reg_success'] ?? null;
        unset($_SESSION['_self_reg_success']);
        if (is_array($saved) && isset($saved['name'], $saved['code'], $saved['link'])) {
            $success = $saved;
            $view = 'success';
        } else {
            redirect('pendaftaran.php');
        }
    } elseif ($rawToken !== '') {
        if ($token === '') {
            $view = 'invalid';
            $invalidMessage = 'Tautan pribadi tidak valid. Periksa kembali tautan yang diberikan admin bimbel.';
        } else {
            $student = StudentSelfService::findByToken($token);
            if ($student === null) {
                $view = 'invalid';
                $invalidMessage = 'Tautan pribadi tidak ditemukan atau sudah diganti. Minta tautan baru kepada admin bimbel.';
            } elseif ((int) $student['is_active'] !== 1) {
                $view = 'invalid';
                $invalidMessage = 'Tautan ini sudah tidak aktif. Hubungi admin bimbel untuk informasi lebih lanjut.';
            } else {
                $view = 'form';
            }
        }
    } elseif ($rawDaftar !== '') {
        if (!StudentSelfService::generalEnabled()) {
            $view = 'invalid';
            $invalidMessage = 'Pendaftaran siswa baru sedang ditutup. Silakan hubungi admin bimbel.';
        } elseif ($token === '' && $daftar === '') {
            $view = 'invalid';
            $invalidMessage = 'Tautan pendaftaran tidak valid. Minta tautan terbaru kepada admin bimbel.';
        } elseif (!StudentSelfService::generalTokenValid($daftar)) {
            $view = 'invalid';
            $invalidMessage = 'Tautan pendaftaran tidak valid atau sudah diganti. Minta tautan terbaru kepada admin bimbel.';
        } else {
            $view = 'form';
            $isNew = true;
        }
    }
}

// ---------------------------------------------------------------------
// Data tampilan
// ---------------------------------------------------------------------
$membership = $student !== null ? Student::activeMembership((int) $student['id']) : null;
$generalEnabled = StudentSelfService::generalEnabled();
$generalLink = StudentSelfService::generalLink();

$pageTitle = match ($view) {
    'success' => 'Data Berhasil Disimpan',
    'invalid' => 'Tautan Tidak Valid',
    default   => $isNew ? 'Pendaftaran Siswa Baru' : 'Lengkapi Data Siswa',
};

if ($view === 'invalid') {
    http_response_code(404);
}

$displayName = $student !== null ? (string) $student['name'] : '';
?>
<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b2532">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title><?= e($pageTitle) ?> — <?= e(Profile::name()) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/pendaftaran.css')) ?>">
</head>
<body class="reg-body">
<div class="reg-shell">
    <header class="reg-topbar">
        <a href="<?= e(url('pendaftaran.php')) ?>" class="reg-brand">
            <img src="<?= e(Profile::logoUrl()) ?>" alt="Logo <?= e(Profile::name()) ?>"
                 class="<?= e(Profile::logoOnDarkClass()) ?>">
            <span>
                <strong><?= e(Profile::name()) ?></strong>
                <?php if (Profile::tagline() !== ''): ?>
                    <small><?= e(Profile::tagline()) ?></small>
                <?php endif; ?>
            </span>
        </a>
        <?php if (Profile::phone() !== '' || Profile::email() !== ''): ?>
            <div class="reg-help">
                <i class="bi bi-headset"></i>
                <span>Butuh bantuan?</span>
                <?php if (Profile::phone() !== ''): ?>
                    <a href="tel:<?= e(normalize_phone(Profile::phone())) ?>"><?= e(Profile::phone()) ?></a>
                <?php endif; ?>
                <?php if (Profile::email() !== ''): ?>
                    <a href="mailto:<?= e(Profile::email()) ?>"><?= e(Profile::email()) ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>

    <main class="reg-main">
        <aside class="reg-intro">
            <div class="reg-intro-badge"><i class="bi bi-shield-check"></i> Formulir resmi</div>
            <h1>
                <?php if ($view === 'success'): ?>
                    Terima kasih, <span>data sudah tersimpan.</span>
                <?php elseif ($view === 'invalid'): ?>
                    Tautan ini <span>tidak dapat digunakan.</span>
                <?php elseif ($view === 'form' && $isNew): ?>
                    Daftar sebagai <span>siswa baru.</span>
                <?php elseif ($view === 'form'): ?>
                    Lengkapi <span>data belajarmu.</span>
                <?php else: ?>
                    Satu formulir untuk <span>data siswa.</span>
                <?php endif; ?>
            </h1>
            <p>
                <?= e(Profile::description() !== ''
                    ? Profile::description()
                    : 'Data yang Anda isi membantu admin menyiapkan jadwal, group belajar, dan administrasi dengan tepat.') ?>
            </p>

            <ul class="reg-points">
                <li>
                    <span><i class="bi bi-pencil-square"></i></span>
                    <div>
                        <strong>Data langsung tersimpan</strong>
                        <p>Perubahan yang Anda kirim langsung tercatat di sistem, tanpa menunggu verifikasi.</p>
                    </div>
                </li>
                <li>
                    <span><i class="bi bi-lock"></i></span>
                    <div>
                        <strong>Kode &amp; group tetap aman</strong>
                        <p>Kode siswa, group belajar, status aktif, dan tanggal bergabung hanya dapat diubah admin.</p>
                    </div>
                </li>
                <li>
                    <span><i class="bi bi-person-check"></i></span>
                    <div>
                        <strong>Khusus pemilik tautan</strong>
                        <p>Jangan bagikan tautan pribadi Anda. Setiap perubahan tercatat di log aktivitas.</p>
                    </div>
                </li>
            </ul>

            <div class="reg-intro-footer">
                <i class="bi bi-geo-alt"></i>
                <span><?= e(Profile::address() !== '' ? Profile::address() : 'Alamat bimbel dapat dilihat pada halaman utama.') ?></span>
            </div>
        </aside>

        <section class="reg-panel">
            <?php if ($view === 'landing'): ?>
                <div class="reg-card">
                    <div class="reg-card-head">
                        <span class="reg-eyebrow"><i class="bi bi-box-arrow-in-right"></i> Portal Siswa</span>
                        <h2>Masuk ke formulir Anda</h2>
                        <p>Tempel tautan yang diberikan admin, atau gunakan tombol pendaftaran bila Anda siswa baru.</p>
                    </div>

                    <?php if (isset($errors['q'])): ?>
                        <div class="reg-alert reg-alert-danger" role="alert">
                            <i class="bi bi-exclamation-octagon-fill"></i>
                            <span><?= e($errors['q']) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="get" action="<?= e(url('pendaftaran.php')) ?>" class="reg-stack">
                        <div>
                            <label for="q" class="form-label">Tautan atau kode akses</label>
                            <input type="text" class="form-control" id="q" name="q"
                                   placeholder="Tempel tautan dari admin di sini…" autocomplete="off" required>
                            <div class="form-text">Contoh: <?= e(absolute_url('pendaftaran.php?token=…')) ?></div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-arrow-right-circle me-1"></i>Buka Formulir
                        </button>
                    </form>

                    <div class="reg-divider"><span>atau</span></div>

                    <?php if ($generalEnabled && $generalLink !== ''): ?>
                        <a href="<?= e($generalLink) ?>" class="btn btn-outline-primary w-100">
                            <i class="bi bi-person-plus me-1"></i>Daftar sebagai Siswa Baru
                        </a>
                        <p class="reg-note">Pendaftaran siswa baru sedang dibuka oleh admin bimbel.</p>
                    <?php else: ?>
                        <div class="reg-closed">
                            <i class="bi bi-door-closed"></i>
                            <div>
                                <strong>Pendaftaran siswa baru ditutup</strong>
                                <span>Hubungi admin bimbel untuk mendapatkan tautan pendaftaran.</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="reg-security">
                        <i class="bi bi-shield-lock-fill"></i>
                        <span>Halaman ini hanya untuk siswa <?= e(Profile::name()) ?>. Jangan bagikan tautan pribadi Anda.</span>
                    </div>
                </div>

            <?php elseif ($view === 'invalid'): ?>
                <div class="reg-card reg-card-center">
                    <div class="reg-state-icon reg-state-danger"><i class="bi bi-link-45deg"></i></div>
                    <span class="reg-eyebrow">Tautan tidak aktif</span>
                    <h2>Tautan tidak dapat digunakan</h2>
                    <p class="reg-lead"><?= e($invalidMessage) ?></p>
                    <div class="reg-contact-box">
                        <strong>Hubungi admin bimbel</strong>
                        <?php if (Profile::phone() !== ''): ?>
                            <span><i class="bi bi-telephone me-1"></i><?= e(Profile::phone()) ?></span>
                        <?php endif; ?>
                        <?php if (Profile::email() !== ''): ?>
                            <span><i class="bi bi-envelope me-1"></i><?= e(Profile::email()) ?></span>
                        <?php endif; ?>
                        <?php if (Profile::phone() === '' && Profile::email() === ''): ?>
                            <span>Silakan hubungi admin bimbel secara langsung.</span>
                        <?php endif; ?>
                    </div>
                    <a href="<?= e(url('pendaftaran.php')) ?>" class="btn btn-primary w-100">
                        <i class="bi bi-arrow-left me-1"></i>Kembali ke Halaman Awal
                    </a>
                </div>

            <?php elseif ($view === 'success' && is_array($success)): ?>
                <div class="reg-card reg-card-center">
                    <div class="reg-state-icon reg-state-success"><i class="bi bi-check2-circle"></i></div>
                    <span class="reg-eyebrow">Berhasil</span>
                    <h2><?= e((string) $success['name']) ?>, data Anda sudah tersimpan</h2>
                    <p class="reg-lead">
                        <?= $success['type'] === 'new'
                            ? 'Pendaftaran diterima. Admin bimbel akan menghubungi Anda untuk langkah berikutnya.'
                            : 'Perubahan data pribadi sudah langsung tercatat di sistem bimbel.' ?>
                    </p>

                    <div class="reg-summary">
                        <div>
                            <span>Kode siswa</span>
                            <strong class="font-monospace"><?= e((string) $success['code']) ?></strong>
                        </div>
                        <div>
                            <span>Tautan pribadi</span>
                            <div class="reg-copy-row">
                                <input type="text" class="form-control" id="personal-link"
                                       value="<?= e((string) $success['link']) ?>" readonly>
                                <button type="button" class="btn btn-outline-primary"
                                        data-copy-target="#personal-link" title="Salin tautan">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <p class="reg-note">
                        Simpan tautan pribadi di atas. Tautan yang sama dapat dipakai lagi bila ada data yang perlu diperbarui.
                    </p>

                    <div class="reg-actions">
                        <a href="<?= e((string) $success['link']) ?>" class="btn btn-primary">
                            <i class="bi bi-pencil-square me-1"></i>Buka Tautan Pribadi
                        </a>
                        <a href="<?= e(url('pendaftaran.php')) ?>" class="btn btn-light">Selesai</a>
                    </div>
                </div>

            <?php else: ?>
                <?php $currentGender = old_value($old, $student ?? [], 'gender', 'L'); ?>
                <form method="post" action="<?= e(url('pendaftaran.php')) ?>" enctype="multipart/form-data"
                      class="reg-form" novalidate data-submit-once>
                    <?= csrf_field() ?>
                    <input type="hidden" name="mode" value="<?= $isNew ? 'new' : 'student' ?>">
                    <?php if ($isNew): ?>
                        <input type="hidden" name="daftar" value="<?= e($daftar) ?>">
                    <?php else: ?>
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                    <?php endif; ?>
                    <div class="reg-honeypot" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="reg-card">
                        <div class="reg-card-head">
                            <span class="reg-eyebrow">
                                <i class="bi <?= $isNew ? 'bi-person-plus' : 'bi-person-badge' ?>"></i>
                                <?= $isNew ? 'Pendaftaran siswa baru' : 'Pembaruan data siswa' ?>
                            </span>
                            <h2><?= $isNew ? 'Isi data siswa dengan lengkap' : 'Halo, ' . e($displayName) ?></h2>
                            <p>
                                <?= $isNew
                                    ? 'Semua kolom bertanda * wajib diisi. Data akan langsung tersimpan setelah dikirim.'
                                    : 'Periksa dan lengkapi data berikut. Perubahan langsung tersimpan setelah dikirim.' ?>
                            </p>
                        </div>

                        <?php if ($student !== null): ?>
                            <div class="reg-locked">
                                <div>
                                    <span>Kode siswa</span>
                                    <strong class="font-monospace"><?= e((string) $student['code']) ?></strong>
                                </div>
                                <div>
                                    <span>Tanggal bergabung</span>
                                    <strong><?= e(tanggal_id((string) $student['join_date'])) ?></strong>
                                </div>
                                <div>
                                    <span>Group aktif</span>
                                    <strong><?= e($membership !== null ? (string) $membership['group_name'] : 'Belum ada group') ?></strong>
                                </div>
                                <small><i class="bi bi-lock-fill me-1"></i>Data ini hanya dapat diubah oleh admin.</small>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($errors['_form'])): ?>
                            <div class="reg-alert reg-alert-danger" role="alert">
                                <i class="bi bi-exclamation-octagon-fill"></i>
                                <span><?= e($errors['_form']) ?></span>
                            </div>
                        <?php endif; ?>

                        <fieldset class="reg-fieldset">
                            <legend><i class="bi bi-person-vcard"></i>Identitas siswa</legend>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="name" class="form-label">Nama lengkap <span class="req">*</span></label>
                                    <input type="text" class="form-control<?= field_invalid($errors, 'name') ?>"
                                           id="name" name="name" maxlength="120" required autocomplete="name"
                                           value="<?= e(old_value($old, $student ?? [], 'name')) ?>">
                                    <?= field_error($errors, 'name') ?>
                                </div>
                                <div class="col-md-4">
                                    <label for="gender" class="form-label">Jenis kelamin <span class="req">*</span></label>
                                    <select class="form-select<?= field_invalid($errors, 'gender') ?>" id="gender" name="gender" required>
                                        <option value="">Pilih…</option>
                                        <option value="L" <?= $currentGender === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                        <option value="P" <?= $currentGender === 'P' ? 'selected' : '' ?>>Perempuan</option>
                                    </select>
                                    <?= field_error($errors, 'gender') ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="birth_place" class="form-label">Tempat lahir <span class="req">*</span></label>
                                    <input type="text" class="form-control<?= field_invalid($errors, 'birth_place') ?>"
                                           id="birth_place" name="birth_place" maxlength="100" required
                                           value="<?= e(old_value($old, $student ?? [], 'birth_place')) ?>">
                                    <?= field_error($errors, 'birth_place') ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="birth_date" class="form-label">Tanggal lahir <span class="req">*</span></label>
                                    <input type="date" class="form-control<?= field_invalid($errors, 'birth_date') ?>"
                                           id="birth_date" name="birth_date" max="<?= e(date('Y-m-d')) ?>" required
                                           value="<?= e(old_value($old, $student ?? [], 'birth_date')) ?>">
                                    <?= field_error($errors, 'birth_date') ?>
                                </div>
                                <div class="col-12">
                                    <label for="address" class="form-label">Alamat lengkap <span class="req">*</span></label>
                                    <textarea class="form-control<?= field_invalid($errors, 'address') ?>"
                                              id="address" name="address" rows="3" maxlength="500" required
                                              placeholder="Jalan, nomor rumah, RT/RW, kelurahan, kecamatan, kota/kabupaten"><?= e(old_value($old, $student ?? [], 'address')) ?></textarea>
                                    <?= field_error($errors, 'address') ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Nomor HP siswa <span class="req">*</span></label>
                                    <input type="tel" class="form-control<?= field_invalid($errors, 'phone') ?>"
                                           id="phone" name="phone" maxlength="30" required autocomplete="tel"
                                           placeholder="08xxxxxxxxxx"
                                           value="<?= e(old_value($old, $student ?? [], 'phone')) ?>">
                                    <?= field_error($errors, 'phone') ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="photo" class="form-label">Foto siswa <span class="text-muted">(opsional)</span></label>
                                    <input type="file" class="form-control<?= field_invalid($errors, 'photo') ?>"
                                           id="photo" name="photo" accept="image/jpeg,image/png,image/webp"
                                           data-photo-input data-photo-preview="#photo-preview">
                                    <div class="form-text">JPG, PNG, atau WebP. Maksimal 2 MB.</div>
                                    <?= field_error($errors, 'photo') ?>
                                </div>
                                <div class="col-12">
                                    <div class="reg-photo-preview">
                                        <img src="<?= e(photo_url($student['photo'] ?? null)) ?>" alt="Pratinjau foto siswa"
                                             class="reg-avatar" id="photo-preview" width="72" height="72">
                                        <?php if (!empty($student['photo'])): ?>
                                            <span>Foto saat ini. Unggah foto baru untuk menggantinya.</span>
                                        <?php else: ?>
                                            <span>Pratinjau foto akan muncul di sini.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="reg-fieldset">
                            <legend><i class="bi bi-people"></i>Orang tua / wali</legend>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="parent_name" class="form-label">Nama orang tua/wali <span class="req">*</span></label>
                                    <input type="text" class="form-control<?= field_invalid($errors, 'parent_name') ?>"
                                           id="parent_name" name="parent_name" maxlength="120" required
                                           value="<?= e(old_value($old, $student ?? [], 'parent_name')) ?>">
                                    <?= field_error($errors, 'parent_name') ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="parent_phone" class="form-label">Nomor HP orang tua/wali <span class="req">*</span></label>
                                    <input type="tel" class="form-control<?= field_invalid($errors, 'parent_phone') ?>"
                                           id="parent_phone" name="parent_phone" maxlength="30" required
                                           placeholder="08xxxxxxxxxx"
                                           value="<?= e(old_value($old, $student ?? [], 'parent_phone')) ?>">
                                    <?= field_error($errors, 'parent_phone') ?>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="reg-fieldset">
                            <legend><i class="bi bi-mortarboard"></i>Sekolah</legend>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="school" class="form-label">Nama sekolah <span class="req">*</span></label>
                                    <input type="text" class="form-control<?= field_invalid($errors, 'school') ?>"
                                           id="school" name="school" maxlength="150" required
                                           value="<?= e(old_value($old, $student ?? [], 'school')) ?>">
                                    <?= field_error($errors, 'school') ?>
                                </div>
                                <div class="col-md-4">
                                    <label for="school_class" class="form-label">Kelas <span class="req">*</span></label>
                                    <input type="text" class="form-control<?= field_invalid($errors, 'school_class') ?>"
                                           id="school_class" name="school_class" maxlength="50" required
                                           placeholder="Contoh: 8A"
                                           value="<?= e(old_value($old, $student ?? [], 'school_class')) ?>">
                                    <?= field_error($errors, 'school_class') ?>
                                </div>
                            </div>
                        </fieldset>

                        <div class="reg-submit">
                            <div class="reg-submit-note">
                                <i class="bi bi-info-circle"></i>
                                <span>Dengan mengirim formulir, Anda menyatakan data yang diisi benar dan dapat dipertanggungjawabkan.</span>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-send-check me-1"></i>
                                <?= $isNew ? 'Kirim Pendaftaran' : 'Simpan Perubahan' ?>
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>

    <footer class="reg-footer">
        <span>&copy; <?= e(date('Y')) ?> <?= e(Profile::name()) ?>. Data siswa hanya digunakan untuk keperluan bimbingan belajar.</span>
        <a href="<?= e(url('login.php')) ?>">Akses admin</a>
    </footer>
</div>

<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
