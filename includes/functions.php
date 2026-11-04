<?php
declare(strict_types=1);

/**
 * Helper umum aplikasi: koneksi, escape, format Indonesia, flash, error page.
 */

/**
 * Koneksi PDO tunggal per request.
 */
function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }
    require_once ROOT_PATH . '/config/database.php';
    $connection = Database::pdo();
    return $connection;
}

/**
 * Escape output untuk mencegah XSS.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * URL internal aplikasi (mendukung subfolder).
 */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    if ($path === '') {
        return BASE_URL !== '' ? BASE_URL . '/' : '/';
    }
    return BASE_URL . '/' . $path;
}

/**
 * URL absolut untuk tautan yang dibagikan ke siswa.
 * Memakai host permintaan saat ini agar tetap benar di localhost maupun hosting.
 */
function absolute_url(string $path = ''): string
{
    $relative = url($path);
    if (preg_match('~^https?://~i', $relative) === 1) {
        return $relative;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    $validHost = is_string($host) && preg_match(
        '/^(?:[A-Za-z0-9.-]+|\[[0-9A-Fa-f:.]+\])(?::\d{1,5})?$/',
        $host
    ) === 1;

    if (!$validHost) {
        $name = $_SERVER['SERVER_NAME'] ?? '';
        $host = is_string($name) ? trim($name) : '';
        $port = (string) ($_SERVER['SERVER_PORT'] ?? '');
        if ($host !== '' && $port !== '' && !in_array($port, ['80', '443'], true)) {
            $host .= ':' . $port;
        }
    }

    if ($host === '') {
        return $relative;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    return ($https ? 'https' : 'http') . '://' . $host . $relative;
}

/**
 * URL aset dengan cache busting berbasis waktu modifikasi file.
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $full = ROOT_PATH . '/assets/' . $path;
    $version = is_file($full) ? (string) filemtime($full) : APP_VERSION;
    return url('assets/' . $path) . '?v=' . rawurlencode($version);
}

function redirect(string $path, int $status = 302): never
{
    $location = trim($path);

    if ($location === '') {
        $location = url();
    } elseif (!preg_match('~^https?://~i', $location)) {
        // Cegah BASE_URL terpasang dua kali ketika pemanggil sudah memakai url().
        $base = trim(BASE_URL, '/');
        $trimmed = ltrim($location, '/');
        if ($base !== '' && ($trimmed === $base || str_starts_with($trimmed, $base . '/'))) {
            $location = '/' . $trimmed;
        } else {
            $location = url($trimmed);
        }
    }

    if (!headers_sent()) {
        header('Location: ' . $location, true, $status);
    }
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function require_method(string $method): void
{
    if (strtoupper($method) !== ($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
        if (!headers_sent()) {
            header('Allow: ' . strtoupper($method));
        }
        render_error_page(405, 'Metode Tidak Diizinkan', 'Permintaan tidak dapat diproses dengan metode tersebut.');
    }
}

function post_string(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function get_string(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function post_int(string $key, int $default = 0): int
{
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    return $value === false || $value === null ? $default : (int) $value;
}

function get_int(string $key, int $default = 0): int
{
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    return $value === false || $value === null ? $default : (int) $value;
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

function client_user_agent(): string
{
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return is_string($agent) ? mb_substr($agent, 0, 255) : '';
}

// ---------------------------------------------------------------------
// Format Indonesia
// ---------------------------------------------------------------------

function rupiah(float|int|string|null $amount, bool $withPrefix = true): string
{
    $value = (float) ($amount ?? 0);
    $negative = $value < 0;
    $formatted = number_format(abs($value), 0, ',', '.');
    return ($negative ? '-' : '') . ($withPrefix ? 'Rp' : '') . $formatted;
}

function bulan_id(int $month): string
{
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];
    return $months[$month] ?? '-';
}

function bulan_singkat_id(int $month): string
{
    $months = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];
    return $months[$month] ?? '-';
}

function hari_id(int $day): string
{
    $days = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    return $days[$day] ?? '-';
}

function tanggal_id(?string $date, bool $withDay = false): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '-';
    }
    try {
        $dt = new DateTimeImmutable($date);
    } catch (Throwable) {
        return '-';
    }
    $result = $dt->format('j') . ' ' . bulan_id((int) $dt->format('n')) . ' ' . $dt->format('Y');
    if ($withDay) {
        $result = hari_id((int) $dt->format('N')) . ', ' . $result;
    }
    return $result;
}

function tanggal_short_id(?string $date): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '-';
    }
    try {
        $dt = new DateTimeImmutable($date);
    } catch (Throwable) {
        return '-';
    }
    return $dt->format('j') . ' ' . bulan_singkat_id((int) $dt->format('n')) . ' ' . $dt->format('Y');
}

function tanggal_waktu_id(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '-';
    }
    try {
        $dt = new DateTimeImmutable($datetime);
    } catch (Throwable) {
        return '-';
    }
    return $dt->format('d/m/Y H:i');
}

function periode_label(int $month, int $year): string
{
    return bulan_id($month) . ' ' . $year;
}

// ---------------------------------------------------------------------
// Label & badge
// ---------------------------------------------------------------------

function invoice_status_label(string $status): string
{
    return match ($status) {
        'belum_bayar' => 'Belum Bayar',
        'lunas'       => 'Lunas',
        'dibatalkan'  => 'Dibatalkan',
        default       => 'Tidak Diketahui',
    };
}

function invoice_status_badge(string $status): string
{
    $class = match ($status) {
        'belum_bayar' => 'text-bg-warning',
        'lunas'       => 'text-bg-success',
        'dibatalkan'  => 'text-bg-secondary',
        default       => 'text-bg-light',
    };
    return '<span class="badge ' . $class . '">' . e(invoice_status_label($status)) . '</span>';
}

/**
 * Status paket belajar (8 pertemuan).
 */
function package_status_label(string $status): string
{
    return match ($status) {
        'aktif'      => 'Aktif',
        'selesai'    => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
        default      => 'Tidak Diketahui',
    };
}

function package_status_badge(string $status): string
{
    $class = match ($status) {
        'aktif'      => 'text-bg-success',
        'selesai'    => 'text-bg-primary',
        'dibatalkan' => 'text-bg-danger',
        default      => 'text-bg-light',
    };
    $icon = match ($status) {
        'aktif'      => 'bi-play-circle',
        'selesai'    => 'bi-check2-circle',
        'dibatalkan' => 'bi-x-circle',
        default      => 'bi-dot',
    };
    return '<span class="badge ' . $class . '"><i class="bi ' . $icon . ' me-1"></i>'
        . e(package_status_label($status)) . '</span>';
}

/**
 * Progres paket 0/8 — 8/8 dalam bentuk progress bar.
 */
function package_progress(int $completed, int $target, bool $showLabel = true): string
{
    $target = max(1, $target);
    $completed = max(0, min($completed, $target));
    $percent = (int) round($completed / $target * 100);
    $class = $percent >= 100 ? 'bg-success' : ($percent >= 75 ? 'bg-warning' : 'bg-primary');
    $html = '<div class="package-progress">'
        . '<div class="progress" role="progressbar" aria-valuenow="' . $completed . '"'
        . ' aria-valuemin="0" aria-valuemax="' . $target . '">'
        . '<div class="progress-bar ' . $class . '" style="width:' . $percent . '%"></div>'
        . '</div>';
    if ($showLabel) {
        $html .= '<span class="package-progress-label">' . $completed . '/' . $target . '</span>';
    }
    return $html . '</div>';
}

/**
 * Status realisasi pertemuan.
 */
function meeting_status_label(string $status): string
{
    return match ($status) {
        'terjadwal'         => 'Terjadwal',
        'terlaksana'        => 'Terlaksana',
        'dibatalkan'        => 'Dibatalkan',
        'dijadwalkan_ulang' => 'Dijadwalkan Ulang',
        default             => 'Tidak Diketahui',
    };
}

function meeting_status_badge(string $status): string
{
    $class = match ($status) {
        'terjadwal'         => 'text-bg-secondary',
        'terlaksana'        => 'text-bg-success',
        'dibatalkan'        => 'text-bg-danger',
        'dijadwalkan_ulang' => 'text-bg-warning',
        default             => 'text-bg-light',
    };
    $icon = match ($status) {
        'terjadwal'         => 'bi-calendar-event',
        'terlaksana'        => 'bi-check2-circle',
        'dibatalkan'        => 'bi-x-circle',
        'dijadwalkan_ulang' => 'bi-arrow-repeat',
        default             => 'bi-dot',
    };
    return '<span class="badge ' . $class . '"><i class="bi ' . $icon . ' me-1"></i>'
        . e(meeting_status_label($status)) . '</span>';
}

/**
 * Jenis kelompok pada konteks tarif: Private / Reguler.
 */
function package_type_label(string $groupType): string
{
    return match ($groupType) {
        'private' => 'Private',
        'group'   => 'Reguler',
        default   => '-',
    };
}

function invoice_type_label(string $type): string
{
    return $type === 'paket' ? 'Paket Belajar' : ($type === 'bulanan' ? 'Bulanan (arsip)' : '-');
}

/**
 * Ubah input nominal menjadi float.
 *
 * Mendukung format Indonesia maupun format teknis:
 * - "1500000", "1.500.000", "Rp1.500.000"      → 1500000
 * - "1.500.000,00", "1500000,00"               → 1500000
 * - "250000.00", "1,500,000.00"                → 250000 / 1500000
 *
 * Titik/koma dianggap pemisah desimal hanya bila diikuti 1–2 digit dan
 * hanya muncul sekali. Selain itu diperlakukan sebagai pemisah ribuan.
 */
function parse_currency(string $value): ?float
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $negative = str_starts_with($value, '-');
    $clean = preg_replace('/[^0-9.,]/', '', $value) ?? '';
    if (preg_match('/[0-9]/', $clean) !== 1) {
        return null;
    }

    $lastDot = strrpos($clean, '.');
    $lastComma = strrpos($clean, ',');

    $decimalPos = -1;
    if ($lastDot !== false && $lastComma !== false) {
        $separator = $lastDot > $lastComma ? '.' : ',';
        if (substr_count($clean, $separator) === 1) {
            $decimalPos = max($lastDot, $lastComma);
        }
    } elseif ($lastDot !== false) {
        $digitsAfter = strlen($clean) - $lastDot - 1;
        if (substr_count($clean, '.') === 1 && $digitsAfter >= 1 && $digitsAfter <= 2) {
            $decimalPos = $lastDot;
        }
    } elseif ($lastComma !== false) {
        $digitsAfter = strlen($clean) - $lastComma - 1;
        if (substr_count($clean, ',') === 1 && $digitsAfter >= 1 && $digitsAfter <= 2) {
            $decimalPos = $lastComma;
        }
    }

    if ($decimalPos >= 0) {
        $integerDigits = preg_replace('/[^0-9]/', '', substr($clean, 0, $decimalPos)) ?? '';
        $decimalDigits = preg_replace('/[^0-9]/', '', substr($clean, $decimalPos + 1)) ?? '';
    } else {
        $integerDigits = preg_replace('/[^0-9]/', '', $clean) ?? '';
        $decimalDigits = '';
    }

    if ($integerDigits === '' && $decimalDigits === '') {
        return null;
    }
    if ($integerDigits === '') {
        $integerDigits = '0';
    }

    $amount = (float) ($decimalDigits !== '' ? $integerDigits . '.' . $decimalDigits : $integerDigits);
    return $negative ? -$amount : $amount;
}

function payment_method_label(string $method): string
{
    return match ($method) {
        'tunai'    => 'Tunai',
        'transfer' => 'Transfer',
        'qris'     => 'QRIS',
        'lainnya'  => 'Lainnya',
        default    => '-',
    };
}

function payment_method_badge(string $method): string
{
    $class = match ($method) {
        'tunai'    => 'text-bg-success',
        'transfer' => 'text-bg-primary',
        'qris'     => 'text-bg-info',
        'lainnya'  => 'text-bg-secondary',
        default    => 'text-bg-light',
    };
    $icon = match ($method) {
        'tunai'    => 'bi-cash-coin',
        'transfer' => 'bi-bank',
        'qris'     => 'bi-qr-code',
        'lainnya'  => 'bi-wallet2',
        default    => 'bi-dot',
    };
    return '<span class="badge ' . $class . ' badge-method"><i class="bi ' . $icon . ' me-1"></i>'
        . e(payment_method_label($method)) . '</span>';
}

function expense_category_label(string $category): string
{
    return match ($category) {
        'honor_guru'       => 'Honor Guru',
        'sewa'             => 'Sewa Tempat',
        'listrik_internet' => 'Listrik & Internet',
        'atk'              => 'ATK & Perlengkapan',
        'transport'        => 'Transport',
        'konsumsi'         => 'Konsumsi',
        'perawatan'        => 'Perawatan & Kebersihan',
        'lainnya'          => 'Lainnya',
        default            => '-',
    };
}

function expense_category_badge(string $category): string
{
    $class = match ($category) {
        'honor_guru'       => 'text-bg-primary',
        'sewa'             => 'text-bg-info',
        'listrik_internet' => 'text-bg-warning',
        'atk'              => 'text-bg-secondary',
        'transport'        => 'text-bg-dark',
        'konsumsi'         => 'text-bg-success',
        'perawatan'        => 'text-bg-light',
        default            => 'text-bg-secondary',
    };
    $icon = match ($category) {
        'honor_guru'       => 'bi-person-badge',
        'sewa'             => 'bi-building',
        'listrik_internet' => 'bi-lightning-charge',
        'atk'              => 'bi-paperclip',
        'transport'        => 'bi-truck',
        'konsumsi'         => 'bi-cup-hot',
        'perawatan'        => 'bi-tools',
        default            => 'bi-three-dots',
    };
    return '<span class="badge ' . $class . '"><i class="bi ' . $icon . ' me-1"></i>'
        . e(expense_category_label($category)) . '</span>';
}

function expense_status_label(string $status): string
{
    return match ($status) {
        'tercatat'   => 'Tercatat',
        'dibatalkan' => 'Dibatalkan',
        default      => '-',
    };
}

function expense_status_badge(string $status): string
{
    $class = match ($status) {
        'tercatat'   => 'text-bg-success',
        'dibatalkan' => 'text-bg-danger',
        default      => 'text-bg-light',
    };
    $icon = match ($status) {
        'tercatat'   => 'bi-check2-circle',
        'dibatalkan' => 'bi-x-circle',
        default      => 'bi-dot',
    };
    return '<span class="badge ' . $class . '"><i class="bi ' . $icon . ' me-1"></i>'
        . e(expense_status_label($status)) . '</span>';
}

function period_status_label(string $status): string
{
    return match ($status) {
        'draft'  => 'Draft',
        'aktif'  => 'Aktif',
        'ditutup'=> 'Ditutup',
        default  => '-',
    };
}

function period_status_badge(string $status): string
{
    $class = match ($status) {
        'draft'   => 'text-bg-secondary',
        'aktif'   => 'text-bg-success',
        'ditutup' => 'text-bg-dark',
        default   => 'text-bg-light',
    };
    return '<span class="badge ' . $class . '">' . e(period_status_label($status)) . '</span>';
}

function gender_label(string $gender): string
{
    return $gender === 'L' ? 'Laki-laki' : ($gender === 'P' ? 'Perempuan' : '-');
}

function role_label(string $role): string
{
    return $role === 'admin' ? 'Administrator' : ($role === 'petugas' ? 'Petugas' : '-');
}

function group_type_label(string $type): string
{
    return match ($type) {
        'private' => 'Private',
        'group'   => 'Group',
        default   => '-',
    };
}

function group_type_badge(string $type): string
{
    $class = $type === 'private' ? 'text-bg-info' : 'text-bg-primary';
    $icon = $type === 'private' ? 'bi-person-fill' : 'bi-people-fill';
    return '<span class="badge ' . $class . '"><i class="bi ' . $icon . ' me-1"></i>'
        . e(group_type_label($type)) . '</span>';
}

/**
 * Status keanggotaan diturunkan dari end_date (NULL = masih aktif).
 */
function membership_status_badge(?string $endDate): string
{
    return $endDate === null
        ? '<span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Aktif</span>'
        : '<span class="badge text-bg-secondary"><i class="bi bi-flag me-1"></i>Selesai</span>';
}

/**
 * Indikator jumlah anggota aktif terhadap kapasitas group.
 */
function capacity_badge(int $memberCount, int $capacity, string $type): string
{
    $limit = $type === 'private' ? 1 : max(2, min(7, $capacity));
    $class = 'capacity-badge';
    if ($memberCount >= $limit) {
        $class .= ' is-full';
    } elseif ($memberCount === 0) {
        $class .= ' is-empty';
    }
    return '<span class="' . e($class) . '">' . e((string) $memberCount) . ' / ' . e((string) $limit)
        . ' <i class="bi bi-person-fill"></i></span>';
}

function time_label(?string $time): string
{
    return $time !== null && $time !== '' ? substr($time, 0, 5) : '-';
}

/**
 * Label, warna, dan ikon untuk kode aktivitas pada log.
 *
 * @return array{label:string,class:string,icon:string}
 */
function activity_meta(string $activity): array
{
    $map = [
        'login'              => ['Login', 'text-bg-success', 'bi-box-arrow-in-right'],
        'logout'             => ['Logout', 'text-bg-secondary', 'bi-box-arrow-right'],
        'login_gagal'        => ['Login Gagal', 'text-bg-danger', 'bi-shield-exclamation'],
        'siswa_tambah'       => ['Tambah Siswa', 'text-bg-success', 'bi-person-plus'],
        'siswa_edit'         => ['Edit Siswa', 'text-bg-info', 'bi-pencil-square'],
        'siswa_nonaktif'     => ['Nonaktifkan Siswa', 'text-bg-warning', 'bi-person-dash'],
        'siswa_aktif'        => ['Aktifkan Siswa', 'text-bg-success', 'bi-person-check'],
        'pendaftaran_link'   => ['Tautan Pendaftaran', 'text-bg-info', 'bi-link-45deg'],
        'pendaftaran_mandiri'=> ['Pendaftaran Mandiri', 'text-bg-success', 'bi-person-check'],
        'pendaftaran_pengaturan' => ['Pengaturan Pendaftaran', 'text-bg-primary', 'bi-gear'],
        'guru_tambah'        => ['Tambah Guru', 'text-bg-success', 'bi-person-plus'],
        'guru_edit'          => ['Edit Guru', 'text-bg-info', 'bi-pencil-square'],
        'guru_nonaktif'      => ['Nonaktifkan Guru', 'text-bg-warning', 'bi-person-dash'],
        'guru_aktif'         => ['Aktifkan Guru', 'text-bg-success', 'bi-person-check'],
        'subject_tambah'     => ['Tambah Mapel', 'text-bg-success', 'bi-journal-plus'],
        'subject_edit'       => ['Edit Mapel', 'text-bg-info', 'bi-pencil-square'],
        'subject_nonaktif'   => ['Nonaktifkan Mapel', 'text-bg-warning', 'bi-journal-minus'],
        'subject_aktif'      => ['Aktifkan Mapel', 'text-bg-success', 'bi-journal-check'],
        'group_tambah'       => ['Tambah Group', 'text-bg-success', 'bi-plus-circle'],
        'group_edit'         => ['Edit Group', 'text-bg-info', 'bi-pencil-square'],
        'group_nonaktif'     => ['Nonaktifkan Group', 'text-bg-warning', 'bi-pause-circle'],
        'group_aktif'        => ['Aktifkan Group', 'text-bg-success', 'bi-play-circle'],
        'group_anggota_tambah' => ['Tambah Anggota', 'text-bg-success', 'bi-person-plus'],
        'group_anggota_selesai' => ['Akhiri Keanggotaan', 'text-bg-warning', 'bi-person-dash'],
        'group_member_pindah'=> ['Pindah Group', 'text-bg-warning', 'bi-arrow-left-right'],
        'jadwal_tambah'      => ['Tambah Jadwal', 'text-bg-success', 'bi-calendar-plus'],
        'jadwal_edit'        => ['Edit Jadwal', 'text-bg-info', 'bi-calendar-check'],
        'jadwal_nonaktif'    => ['Nonaktifkan Jadwal', 'text-bg-warning', 'bi-calendar-x'],
        'jadwal_aktif'       => ['Aktifkan Jadwal', 'text-bg-success', 'bi-calendar2-check'],
        'jadwal_hapus'       => ['Hapus Jadwal', 'text-bg-danger', 'bi-trash'],
        'tarif_tambah'       => ['Tambah Tarif', 'text-bg-success', 'bi-cash-coin'],
        'tarif_edit'         => ['Edit Tarif', 'text-bg-info', 'bi-pencil-square'],
        'periode_tambah'     => ['Tambah Periode', 'text-bg-success', 'bi-calendar-plus'],
        'periode_edit'       => ['Edit Periode', 'text-bg-info', 'bi-calendar-check'],
        'periode_tutup'      => ['Tutup Periode', 'text-bg-dark', 'bi-calendar-x'],
        'invoice_generate'   => ['Generate Invoice', 'text-bg-primary', 'bi-receipt'],
        'invoice_batal'      => ['Batal Invoice', 'text-bg-danger', 'bi-x-circle'],
        'pembayaran'         => ['Pembayaran', 'text-bg-success', 'bi-cash-stack'],
        'pertemuan_terjadwal'=> ['Jadwal Pertemuan', 'text-bg-secondary', 'bi-calendar-event'],
        'pertemuan_terlaksana' => ['Pertemuan Terlaksana', 'text-bg-success', 'bi-check2-circle'],
        'pertemuan_edit'     => ['Ubah Realisasi', 'text-bg-info', 'bi-pencil-square'],
        'pertemuan_batal'    => ['Pertemuan Dibatalkan', 'text-bg-danger', 'bi-x-circle'],
        'pertemuan_pindah'   => ['Jadwal Dipindahkan', 'text-bg-warning', 'bi-arrow-repeat'],
        'pertemuan_batal_hitung' => ['Batal Hitung Pertemuan', 'text-bg-warning', 'bi-arrow-counterclockwise'],
        'paket_tambah'       => ['Tambah Paket', 'text-bg-success', 'bi-box-seam'],
        'paket_berikutnya'   => ['Paket Berikutnya', 'text-bg-primary', 'bi-boxes'],
        'paket_selesai'      => ['Paket Selesai', 'text-bg-success', 'bi-check2-all'],
        'paket_batal'        => ['Paket Ditutup', 'text-bg-danger', 'bi-x-octagon'],
        'paket_penyesuaian'  => ['Penyesuaian Paket', 'text-bg-warning', 'bi-sliders'],
        'tagihan_terbit'     => ['Terbitkan Tagihan', 'text-bg-primary', 'bi-receipt'],
        'pengeluaran_tambah' => ['Tambah Pengeluaran', 'text-bg-success', 'bi-cash-coin'],
        'pengeluaran_edit'   => ['Edit Pengeluaran', 'text-bg-info', 'bi-pencil-square'],
        'pengeluaran_batal'  => ['Batal Pengeluaran', 'text-bg-danger', 'bi-x-circle'],
        'profil_edit'        => ['Edit Profil Bimbel', 'text-bg-info', 'bi-building'],
        'profil_logo'        => ['Ubah Logo Bimbel', 'text-bg-info', 'bi-image'],
        'profil_logo_reset'  => ['Reset Logo Bimbel', 'text-bg-warning', 'bi-arrow-counterclockwise'],
        'user_tambah'        => ['Tambah Pengguna', 'text-bg-success', 'bi-person-plus'],
        'user_edit'          => ['Edit Pengguna', 'text-bg-info', 'bi-pencil-square'],
    ];

    if (isset($map[$activity])) {
        return ['label' => $map[$activity][0], 'class' => $map[$activity][1], 'icon' => $map[$activity][2]];
    }

    return [
        'label' => ucwords(str_replace('_', ' ', $activity)),
        'class' => 'text-bg-light',
        'icon'  => 'bi-activity',
    ];
}

function active_badge(bool|int $active, string $activeLabel = 'Aktif', string $inactiveLabel = 'Nonaktif'): string
{
    return $active
        ? '<span class="badge text-bg-success">' . e($activeLabel) . '</span>'
        : '<span class="badge text-bg-secondary">' . e($inactiveLabel) . '</span>';
}

/**
 * Badge kelengkapan data pribadi siswa (pendaftaran mandiri).
 *
 * @param array<string, mixed> $student
 */
function profile_completion_badge(array $student, bool $withPercent = true): string
{
    $completeness = StudentSelfService::completeness($student);
    if ($completeness['complete']) {
        return '<span class="badge text-bg-success" title="Data pribadi sudah lengkap">'
            . '<i class="bi bi-check2-circle me-1"></i>Data lengkap</span>';
    }

    if ($completeness['percent'] <= 0) {
        return '<span class="badge text-bg-light text-secondary" title="Belum ada data pribadi yang diisi">'
            . '<i class="bi bi-dash-circle me-1"></i>Belum diisi</span>';
    }

    $percent = (string) $completeness['percent'];
    return '<span class="badge text-bg-warning" title="Masih ada data yang belum lengkap">'
        . '<i class="bi bi-exclamation-circle me-1"></i>'
        . ($withPercent ? $percent . '%' : 'Belum lengkap') . '</span>';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts, static fn ($p) => $p !== ''));
    if ($parts === []) {
        return 'GE';
    }
    if (count($parts) === 1) {
        return mb_strtoupper(mb_substr($parts[0], 0, 2));
    }
    $first = mb_substr($parts[0], 0, 1);
    $second = mb_substr($parts[1], 0, 1);
    return mb_strtoupper($first . $second);
}

// ---------------------------------------------------------------------
// Validasi & normalisasi input
// ---------------------------------------------------------------------

/**
 * Validasi tanggal format Y-m-d yang benar-benar ada di kalender.
 */
function valid_date(string $value): bool
{
    if ($value === '') {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
}

/**
 * Normalisasi nomor HP Indonesia menjadi format 08xxxxxxxxxx.
 */
function normalize_phone(string $phone): string
{
    $digits = preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';
    if (str_starts_with($digits, '+62')) {
        $digits = '0' . substr($digits, 3);
    } elseif (str_starts_with($digits, '62')) {
        $digits = '0' . substr($digits, 2);
    }
    return $digits;
}

/**
 * Nomor HP Indonesia: 08 + 8–12 digit, tanpa spasi/tanda baca.
 */
function valid_phone(string $phone): bool
{
    return preg_match('/^0[1-9][0-9]{7,12}$/', normalize_phone($phone)) === 1;
}

/**
 * Tampilan nomor HP yang mudah dibaca: 0812-3456-7890.
 */
function phone_display(?string $phone): string
{
    $digits = preg_replace('/[^0-9]/', '', (string) $phone) ?? '';
    if ($digits === '') {
        return '-';
    }
    if (str_starts_with($digits, '62')) {
        $digits = '0' . substr($digits, 2);
    }
    if (strlen($digits) < 8 || strlen($digits) > 15) {
        return (string) $phone;
    }

    $chunks = str_split($digits, 4);
    $lastIndex = count($chunks) - 1;
    if ($lastIndex > 0 && strlen($chunks[$lastIndex]) < 3) {
        $needed = 3 - strlen($chunks[$lastIndex]);
        $chunks[$lastIndex] = substr($chunks[$lastIndex - 1], -$needed) . $chunks[$lastIndex];
        $chunks[$lastIndex - 1] = substr($chunks[$lastIndex - 1], 0, -$needed);
    }
    return implode('-', array_filter($chunks, static fn (string $c): bool => $c !== ''));
}

/**
 * URL foto pengguna. Mengembalikan avatar default bila file tidak ada.
 */
function photo_url(?string $photo, string $fallback = 'assets/img/avatar-default.svg'): string
{
    $photo = (string) $photo;
    if (preg_match('#^(siswa|guru)/[A-Za-z0-9._-]+$#', $photo) === 1) {
        $full = UPLOAD_PATH . '/' . $photo;
        if (is_file($full)) {
            return url('uploads/' . $photo) . '?v=' . rawurlencode((string) filemtime($full));
        }
    }
    return url($fallback);
}

/**
 * Markup foto/avatar dengan atribut lebar-tinggi untuk mencegah layout shift.
 */
function photo_tag(?string $photo, string $name, string $cssClass = 'avatar-photo', int $size = 40): string
{
    return '<img src="' . e(photo_url($photo)) . '" alt="Foto ' . e($name) . '" class="' . e($cssClass)
        . '" width="' . $size . '" height="' . $size . '" loading="lazy">';
}

// ---------------------------------------------------------------------
// State form (validasi gagal → redirect kembali dengan old input)
// ---------------------------------------------------------------------

/**
 * @param array<string, string> $errors
 * @param array<string, mixed>  $old
 */
function form_fail(array $errors, array $old, string $path): never
{
    $_SESSION['_form_state'] = ['errors' => $errors, 'old' => $old];
    redirect($path);
}

/**
 * @return array{errors: array<string, string>, old: array<string, mixed>}
 */
function pull_form_state(): array
{
    $state = $_SESSION['_form_state'] ?? null;
    unset($_SESSION['_form_state']);
    if (!is_array($state)) {
        return ['errors' => [], 'old' => []];
    }
    return [
        'errors' => is_array($state['errors'] ?? null) ? $state['errors'] : [],
        'old'    => is_array($state['old'] ?? null) ? $state['old'] : [],
    ];
}

/**
 * Nilai field: old input (bila validasi gagal) atau data dari database.
 *
 * @param array<string, mixed> $old
 * @param array<string, mixed> $row
 */
function old_value(array $old, array $row, string $key, string $default = ''): string
{
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if (array_key_exists($key, $row) && $row[$key] !== null) {
        return (string) $row[$key];
    }
    return $default;
}

/**
 * @param array<string, string> $errors
 */
function field_invalid(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}

/**
 * @param array<string, string> $errors
 */
function field_error(array $errors, string $key): string
{
    if (!isset($errors[$key])) {
        return '';
    }
    return '<div class="invalid-feedback d-block">' . e($errors[$key]) . '</div>';
}

// ---------------------------------------------------------------------
// Flash message
// ---------------------------------------------------------------------

function flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $allowed = ['success', 'danger', 'warning', 'info'];
    if (!in_array($type, $allowed, true)) {
        $type = 'info';
    }
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($flashes) ? $flashes : [];
}

// ---------------------------------------------------------------------
// Pengaturan aplikasi
// ---------------------------------------------------------------------

function app_setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll();
            foreach ($rows as $row) {
                $cache[(string) $row['setting_key']] = (string) $row['setting_value'];
            }
        } catch (Throwable $exception) {
            @file_put_contents(
                LOG_PATH . '/app.log',
                '[' . date('Y-m-d H:i:s') . '] APP SETTINGS ERROR: ' . $exception->getMessage() . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        }
    }
    return $cache[$key] ?? $default;
}

// ---------------------------------------------------------------------
// Log aktivitas
// ---------------------------------------------------------------------

function log_activity(
    string $activity,
    string $description = '',
    ?string $relatedType = null,
    ?int $relatedId = null
): void {
    ActivityLog::record($activity, $description, $relatedType, $relatedId);
}

// ---------------------------------------------------------------------
// Halaman error
// ---------------------------------------------------------------------

function render_error_page(int $status, string $title, string $message, string $backUrl = ''): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
    }

    $back = $backUrl !== '' ? $backUrl : (Auth::check() ? url('admin/dashboard.php') : url('login.php'));
    $backLabel = $backUrl !== '' ? 'Kembali' : (Auth::check() ? 'Ke Dashboard' : 'Ke Halaman Login');

    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . ' — ' . e(APP_NAME) . '</title>'
        . '<link rel="icon" type="image/svg+xml" href="' . e(url('assets/img/favicon.svg')) . '">'
        . '<style>'
        . 'body{font-family:system-ui,"Segoe UI",sans-serif;background:linear-gradient(145deg,#eef6f4,#f8fafc);color:#0f172a;margin:0;display:grid;place-items:center;min-height:100vh;padding:24px}'
        . '.box{max-width:560px;width:100%;background:#fff;border-radius:18px;padding:40px;box-shadow:0 22px 60px rgba(15,23,42,.14);border-top:4px solid #0f766e;text-align:center}'
        . '.mark{width:64px;height:64px;border-radius:18px;background:linear-gradient(135deg,#0f766e,#0b2532);display:grid;place-items:center;color:#f2c14e;font-size:28px;font-weight:800;margin:0 auto 20px}'
        . 'h1{font-size:1.45rem;margin:0 0 10px}p{color:#475569;line-height:1.65;margin:0 0 24px}'
        . 'a{display:inline-block;background:#0f766e;color:#fff;text-decoration:none;padding:11px 20px;border-radius:10px;font-weight:600}'
        . 'a:hover{background:#0b5f59}'
        . '</style></head><body><div class="box">'
        . '<div class="mark">GE</div>'
        . '<h1>' . e($title) . '</h1><p>' . e($message) . '</p>'
        . '<a href="' . e($back) . '">' . e($backLabel) . '</a>'
        . '</div></body></html>';
    exit;
}

function abort_403(string $message = 'Anda tidak memiliki hak akses untuk membuka halaman ini.'): never
{
    render_error_page(403, 'Akses Ditolak', $message);
}

function abort_404(string $message = 'Halaman yang Anda cari tidak ditemukan.'): never
{
    render_error_page(404, 'Halaman Tidak Ditemukan', $message);
}

// ---------------------------------------------------------------------
// Query string & pagination
// ---------------------------------------------------------------------

function url_query(string $path, array $params = []): string
{
    $base = url($path);
    $params = array_filter($params, static fn ($value) => $value !== null && $value !== '');
    if ($params === []) {
        return $base;
    }
    return $base . '?' . http_build_query($params);
}

function js_json(mixed $data): string
{
    return (string) json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
    return $bytes . ' B';
}
