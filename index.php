<?php
declare(strict_types=1);

/**
 * Halaman depan publik (landing page) Govinda Education.
 *
 * Satu halaman, tanpa framework front-end dan tanpa aset CDN.
 * Semua identitas bimbel diambil dari modul Profil Bimbel agar konsisten
 * dengan sidebar, invoice, dan nota. Statistik hanya memakai data nyata
 * dari database; bila data belum ada, halaman memakai fakta program.
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (auth_check()) {
    redirect('admin/dashboard.php');
}

$brand = Profile::name();
$profileTagline = Profile::tagline();
$authPhone = Profile::phone();
$authEmail = Profile::email();
$authAddress = Profile::address();
$authWebsite = Profile::websiteUrl();
$authWebsiteLabel = Profile::websiteLabel();

$subjects = [];
$teacherCount = 0;
$groupCount = 0;

try {
    $subjects = Subject::activeList();
    $teacherCount = Teacher::countActive();
    $groupCount = Group::countActive();
} catch (Throwable $exception) {
    // Landing page tetap tampil walau database belum siap.
    @file_put_contents(
        LOG_PATH . '/app.log',
        sprintf(
            "[%s] Landing stats: %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ),
        FILE_APPEND | LOCK_EX
    );
}

$registrationLink = '';
try {
    if (StudentSelfService::generalEnabled()) {
        $registrationLink = StudentSelfService::generalLink();
    }
} catch (Throwable $exception) {
    @file_put_contents(
        LOG_PATH . '/app.log',
        sprintf(
            "[%s] Landing registration: %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ),
        FILE_APPEND | LOCK_EX
    );
}

$waNumber = normalize_phone($authPhone);
$waValid = preg_match('/^0[1-9][0-9]{7,12}$/', $waNumber) === 1;
$waLink = $waValid ? 'https://wa.me/62' . substr($waNumber, 1) : '';
$phoneLink = $waNumber !== '' ? 'tel:' . $waNumber : '';
$emailLink = $authEmail !== '' ? 'mailto:' . $authEmail : '';
$hasContact = $waLink !== '' || $phoneLink !== '' || $emailLink !== '' || $authAddress !== '' || $authWebsite !== '';

// Tombol daftar selalu tampil. Bila pendaftaran umum sedang dibuka, tombol
// langsung menuju formulir siswa baru; bila ditutup, menuju halaman
// pendaftaran yang menjelaskan status dan langkah berikutnya.
$registerLink = $registrationLink !== '' ? $registrationLink : url('pendaftaran.php');

$currentHost = strtolower((string) preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$isExternal = static function (string $link) use ($currentHost): bool {
    if (preg_match('~^https?://~i', $link) !== 1) {
        return false;
    }
    $host = strtolower((string) parse_url($link, PHP_URL_HOST));
    return $host !== '' && ($currentHost === '' || $host !== $currentHost);
};

// Tombol utama di hero: kontak lebih dulu, lalu pendaftaran.
if ($waLink !== '') {
    $primaryLink = $waLink;
    $primaryLabel = 'Chat WhatsApp';
    $primaryIcon = 'bi-whatsapp';
    $primaryClass = 'lp-btn--wa';
} elseif ($emailLink !== '') {
    $primaryLink = $emailLink;
    $primaryLabel = 'Kirim Email';
    $primaryIcon = 'bi-envelope-fill';
    $primaryClass = 'lp-btn--teal';
} else {
    $primaryLink = $registerLink;
    $primaryLabel = 'Daftar Sekarang';
    $primaryIcon = 'bi-pencil-square';
    $primaryClass = 'lp-btn--gold';
}

// Tombol utama di bagian kontak tidak pernah jatuh ke anchor program.
if ($waLink !== '') {
    $contactPrimaryLink = $waLink;
    $contactPrimaryLabel = 'Chat WhatsApp';
    $contactPrimaryIcon = 'bi-whatsapp';
    $contactPrimaryClass = 'lp-btn--wa';
} elseif ($emailLink !== '') {
    $contactPrimaryLink = $emailLink;
    $contactPrimaryLabel = 'Kirim Email';
    $contactPrimaryIcon = 'bi-envelope-fill';
    $contactPrimaryClass = 'lp-btn--teal';
} elseif ($phoneLink !== '') {
    $contactPrimaryLink = $phoneLink;
    $contactPrimaryLabel = 'Telepon Admin';
    $contactPrimaryIcon = 'bi-telephone-fill';
    $contactPrimaryClass = 'lp-btn--teal';
} else {
    $contactPrimaryLink = $registerLink;
    $contactPrimaryLabel = 'Daftar Sekarang';
    $contactPrimaryIcon = 'bi-pencil-square';
    $contactPrimaryClass = 'lp-btn--gold';
}

$navCtaLink = $registerLink;
$navCtaLabel = 'Daftar';

$subjectCount = count($subjects);
$subjectNames = array_map(static fn (array $subject): string => (string) $subject['name'], $subjects);
$heroSubjects = array_slice($subjectNames, 0, 3);
if ($heroSubjects === []) {
    $heroSubjects = ['Semua mapel', 'Sesuai kebutuhan'];
}
$subjectChips = array_slice($subjectNames, 0, 8);
$remainingSubjects = max(0, $subjectCount - count($subjectChips));

$stats = [
    [
        'value' => '2–7',
        'label' => 'siswa per kelas',
        'icon'  => 'bi-people-fill',
        'tone'  => 'teal',
    ],
    [
        'value' => '8',
        'label' => 'pertemuan per paket',
        'icon'  => 'bi-calendar2-check-fill',
        'tone'  => 'gold',
    ],
    $subjectCount > 0
        ? [
            'value' => (string) $subjectCount,
            'label' => 'mata pelajaran aktif',
            'icon'  => 'bi-journal-bookmark-fill',
            'tone'  => 'blue',
        ]
        : [
            'value' => '1:1',
            'label' => 'sesi privat tersedia',
            'icon'  => 'bi-person-check-fill',
            'tone'  => 'blue',
        ],
    $teacherCount > 0
        ? [
            'value' => (string) $teacherCount,
            'label' => 'guru pengampu',
            'icon'  => 'bi-mortarboard-fill',
            'tone'  => 'rose',
        ]
        : ($groupCount > 0
            ? [
                'value' => (string) $groupCount,
                'label' => 'kelas aktif',
                'icon'  => 'bi-collection-fill',
                'tone'  => 'rose',
            ]
            : [
                'value' => 'Fleksibel',
                'label' => 'jadwal belajar',
                'icon'  => 'bi-clock-history',
                'tone'  => 'rose',
            ]),
];

$features = [
    [
        'icon'  => 'bi-people-fill',
        'tone'  => '',
        'title' => 'Kelas kecil, perhatian lebih',
        'text'  => 'Maksimal 2–7 siswa per grup. Guru jadi lebih gampang notice siapa yang masih bingung dan siapa yang siap lanjut.',
    ],
    [
        'icon'  => 'bi-person-heart',
        'tone'  => 'tone-gold',
        'title' => 'Guru yang sabar',
        'text'  => 'Dibimbing guru pengampu tiap mapel. Nggak buru-buru, yang penting kamu beneran paham, bukan cuma hafal.',
    ],
    [
        'icon'  => 'bi-bullseye',
        'tone'  => 'tone-blue',
        'title' => 'Target jelas: 8 pertemuan',
        'text'  => 'Tiap paket punya tujuan yang terukur. Kamu tahu harus ke mana, dan progres belajar jadi kelihatan.',
    ],
    [
        'icon'  => 'bi-calendar2-week',
        'tone'  => 'tone-rose',
        'title' => 'Jadwal fleksibel',
        'text'  => 'Jadwal bisa disesuaikan sama situasi kelas. Nggak kaku, tapi tetap teratur dan tercatat.',
    ],
    [
        'icon'  => 'bi-graph-up-arrow',
        'tone'  => 'tone-violet',
        'title' => 'Progres tercatat rapi',
        'text'  => 'Kehadiran, pertemuan, dan catatan belajar tersimpan rapi. Gampang dicek kapan aja kamu butuh.',
    ],
    [
        'icon'  => 'bi-shield-check',
        'tone'  => 'tone-gold',
        'title' => 'Administrasi transparan',
        'text'  => 'Tagihan dan pembayaran tercatat jelas. Nggak ada hitungan yang bikin bingung di belakang.',
    ],
];

$faqItems = [
    [
        'q' => 'Aku belum pernah ikut bimbel. Bisa mulai dari nol?',
        'a' => 'Bisa banget. Materi disesuaikan sama level kamu sekarang, jadi nggak perlu takut ketinggalan. Yang penting kamu mau mulai dan berani tanya.',
    ],
    [
        'q' => 'Satu kelas isinya berapa orang?',
        'a' => 'Kalau privat, satu siswa satu guru. Kalau grup kecil, 2–7 siswa. Jumlahnya dijaga biar suasana belajar tetap nyaman dan kamu tetap dapat perhatian.',
    ],
    [
        'q' => 'Bisa pilih mata pelajaran sendiri?',
        'a' => 'Bisa. Kamu bisa cerita mapel apa yang mau didalami, nanti admin bantu cocokkan dengan kelas dan guru yang tersedia.',
    ],
    [
        'q' => 'Jadwalnya bisa disesuaikan?',
        'a' => 'Bisa. Jadwal dibuat fleksibel mengikuti situasi kelas. Kalau ada perubahan, admin bantu atur ulang biar belajar tetap jalan.',
    ],
    [
        'q' => 'Kalau tagihan belum dibayar, belajar tetap jalan?',
        'a' => 'Tetap jalan. Fokus utama di sini adalah belajar. Tagihan tetap tercatat rapi dan admin bakal bantu jelasin supaya semuanya jelas.',
    ],
    [
        'q' => 'Gimana cara daftarnya?',
        'a' => $registrationLink !== ''
            ? 'Gampang. Klik tombol Daftar di halaman ini, isi data kamu, lalu admin akan menghubungi untuk ngobrol soal kelas dan jadwal.'
            : 'Klik tombol Daftar di halaman ini. Kalau pendaftaran online sedang ditutup, halaman itu akan menunjukkan langkah berikutnya, atau kamu bisa langsung hubungi admin lewat kontak yang tersedia.',
    ],
    [
        'q' => 'Orang tua bisa pantau progres?',
        'a' => 'Bisa. Kehadiran dan realisasi pertemuan tercatat di sistem, jadi admin bisa bantu tunjukkan rekap progres belajar kamu kapan pun dibutuhkan.',
    ],
];

$landingTagline = rtrim($profileTagline !== '' ? $profileTagline : 'Belajar itu menyenangkan', ". \t\n\r\0\x0B");
$pageTitle = $brand . ' — ' . $landingTagline . '.';
$metaDescription = 'Bimbingan belajar dengan kelas kecil 2–7 siswa, guru yang sabar, dan target 8 pertemuan per paket. ' . $landingTagline . '.';

$logoUrl = Profile::logoUrl();
$logoRelative = ltrim((string) preg_replace('~^' . preg_quote(BASE_URL, '~') . '~', '', $logoUrl), '/');
$logoAbsolute = $logoRelative !== '' ? absolute_url($logoRelative) : absolute_url('assets/img/logo-mark.svg');
$brandMarkClass = Profile::hasCustomLogo() ? 'lp-brand-mark' : 'lp-brand-mark lp-brand-mark--plain';

$schema = [
    '@context'    => 'https://schema.org',
    '@type'       => 'EducationalOrganization',
    'name'        => $brand,
    'description' => $metaDescription,
    'url'         => absolute_url(''),
    'logo'        => $logoAbsolute,
];
if ($authAddress !== '') {
    $schema['address'] = [
        '@type'           => 'PostalAddress',
        'streetAddress'   => $authAddress,
        'addressCountry'  => 'ID',
    ];
}
if ($authPhone !== '') {
    $schema['telephone'] = $authPhone;
}
if ($authEmail !== '') {
    $schema['email'] = $authEmail;
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b2532">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <link rel="canonical" href="<?= e(absolute_url('')) ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:url" content="<?= e(absolute_url('')) ?>">
    <meta property="og:image" content="<?= e($logoAbsolute) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/landing.css')) ?>">
    <script>
        document.documentElement.classList.add('lp-js');
        window.setTimeout(function () {
            document.documentElement.classList.add('lp-reveal-all');
        }, 2200);
    </script>
    <script type="application/ld+json"><?= json_encode(
        $schema,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?></script>
</head>
<body class="lp-body">
<a class="lp-skip" href="#main">Langsung ke konten</a>

<header class="lp-nav" id="lp-nav">
    <div class="lp-container lp-nav-inner">
        <a class="lp-brand" href="#beranda">
            <span class="<?= e($brandMarkClass) ?>">
                <img class="lp-brand-logo <?= e(Profile::logoOnLightClass()) ?>"
                     src="<?= e($logoUrl) ?>"
                     alt="">
            </span>
            <span class="lp-brand-text">
                <span class="lp-brand-name"><?= e($brand) ?></span>
                <?php if ($profileTagline !== ''): ?>
                    <span class="lp-brand-sub"><?= e($profileTagline) ?></span>
                <?php endif; ?>
            </span>
        </a>

        <nav class="lp-nav-links" id="lp-nav-links" aria-label="Navigasi utama">
            <a class="lp-nav-link" href="#beranda">Beranda</a>
            <a class="lp-nav-link" href="#program">Program</a>
            <a class="lp-nav-link" href="#keunggulan">Keunggulan</a>
            <a class="lp-nav-link" href="#cara-belajar">Cara Belajar</a>
            <a class="lp-nav-link" href="#faq">FAQ</a>
            <?php if ($hasContact): ?>
                <a class="lp-nav-link" href="#kontak">Kontak</a>
            <?php endif; ?>
            <a class="lp-btn lp-btn--gold lp-nav-cta"
               href="<?= e($navCtaLink) ?>"
               <?= $isExternal($navCtaLink) ? 'target="_blank" rel="noopener"' : '' ?>>
                <i class="bi bi-pencil-square"></i>
                <?= e($navCtaLabel) ?>
            </a>
        </nav>

        <button class="lp-nav-toggle" type="button" aria-label="Buka menu"
                aria-expanded="false" aria-controls="lp-nav-links">
            <i class="bi bi-list"></i>
        </button>
    </div>
</header>

<main id="main">
    <!-- =============================================================
         Hero
         ============================================================= -->
    <section class="lp-hero" id="beranda">
        <div class="lp-container lp-hero-inner">
            <div class="lp-hero-copy">
                <span class="lp-badge">
                    <span class="lp-badge-dot"></span>
                    <?= e($profileTagline !== '' ? $profileTagline : 'Bimbingan belajar') ?> · kelas kecil 2–7 siswa
                </span>

                <h1>Belajar itu<br><span class="lp-highlight">menyenangkan.</span></h1>

                <p class="lp-hero-lead">
                    Belajar nggak harus tegang. Di sini kamu dapat kelas kecil, guru yang sabar,
                    dan target yang jelas — biar tiap pertemuan beneran nambah paham dan tetap seru.
                </p>

                <div class="lp-hero-actions">
                    <a class="lp-btn <?= e($primaryClass) ?> lp-btn--lg"
                       href="<?= e($primaryLink) ?>"
                       <?= $isExternal($primaryLink) ? 'target="_blank" rel="noopener"' : '' ?>>
                        <i class="bi <?= e($primaryIcon) ?>"></i>
                        <?= e($primaryLabel) ?>
                    </a>
                    <a class="lp-btn lp-btn--ghost lp-btn--lg" href="#cara-belajar">
                        Cara belajar
                        <i class="bi bi-arrow-down-right"></i>
                    </a>
                    <?php if ($primaryLink !== $registerLink): ?>
                        <a class="lp-btn lp-btn--gold lp-btn--lg"
                           href="<?= e($registerLink) ?>">
                            <i class="bi bi-pencil-square"></i>
                            Daftar Sekarang
                        </a>
                    <?php endif; ?>
                </div>

                <ul class="lp-hero-points">
                    <li><i class="bi bi-check2-circle"></i> Privat atau grup kecil</li>
                    <li><i class="bi bi-check2-circle"></i> Guru pengampu tiap mapel</li>
                    <li><i class="bi bi-check2-circle"></i> Target 8 pertemuan</li>
                </ul>
            </div>

            <div class="lp-hero-visual" aria-hidden="true">
                <span class="lp-orbit lp-orbit--1"></span>
                <span class="lp-orbit lp-orbit--2"></span>

                <div class="lp-study-card">
                    <div class="lp-study-top">
                        <span class="lp-study-title"><span></span> Paket belajar</span>
                        <span class="lp-study-pill">1 paket</span>
                    </div>
                    <div class="lp-study-number">
                        <strong>8</strong>
                        <small>pertemuan<br>per paket</small>
                    </div>
                    <div class="lp-meeting-dots">
                        <?php for ($dot = 1; $dot <= 8; $dot++): ?>
                            <span></span>
                        <?php endfor; ?>
                    </div>
                    <div class="lp-study-subjects">
                        <?php foreach ($heroSubjects as $heroSubject): ?>
                            <span class="lp-mini-chip"><?= e($heroSubject) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="lp-study-foot">
                        <i class="bi bi-people-fill"></i>
                        Kelas kecil 2–7 siswa
                    </div>
                </div>

                <div class="lp-float lp-float--top">
                    <i class="bi bi-person-check-fill"></i>
                    <div>
                        <strong>Privat 1-on-1</strong>
                        <span>fokus kamu</span>
                    </div>
                </div>
                <div class="lp-float lp-float--bottom">
                    <i class="bi bi-graph-up-arrow"></i>
                    <div>
                        <strong>Progres</strong>
                        <span>tercatat rapi</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================================
         Statistik
         ============================================================= -->
    <div class="lp-stats-wrap">
        <div class="lp-container">
            <div class="lp-stats">
                <?php foreach ($stats as $stat): ?>
                    <div class="lp-stat">
                        <span class="lp-stat-icon tone-<?= e($stat['tone']) ?>">
                            <i class="bi <?= e($stat['icon']) ?>"></i>
                        </span>
                        <div>
                            <div class="lp-stat-value"><?= e($stat['value']) ?></div>
                            <div class="lp-stat-label"><?= e($stat['label']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- =============================================================
         Keunggulan
         ============================================================= -->
    <section class="lp-section" id="keunggulan">
        <div class="lp-container">
            <div class="lp-section-head" data-reveal>
                <span class="lp-eyebrow"><i class="bi bi-stars"></i> Kenapa di sini?</span>
                <h2>Belajar yang bikin kamu <span class="lp-accent">nyaman</span>, bukan tambah pusing.</h2>
                <p>
                    Kami rancang kelasnya biar kamu berani tanya, paham pelan-pelan,
                    dan tetap punya waktu buat hal lain.
                </p>
            </div>

            <div class="lp-features">
                <?php foreach ($features as $index => $feature): ?>
                    <article class="lp-feature" data-reveal style="--lp-delay: <?= e((string) (($index % 3) * 0.07)) ?>s">
                        <span class="lp-feature-icon <?= e($feature['tone']) ?>">
                            <i class="bi <?= e($feature['icon']) ?>"></i>
                        </span>
                        <h3><?= e($feature['title']) ?></h3>
                        <p><?= e($feature['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- =============================================================
         Program
         ============================================================= -->
    <section class="lp-section lp-section--alt" id="program">
        <div class="lp-container">
            <div class="lp-section-head" data-reveal>
                <span class="lp-eyebrow"><i class="bi bi-grid-1x2-fill"></i> Pilih cara belajarmu</span>
                <h2>Mau fokus sendiri atau <span class="lp-accent">belajar bareng</span>?</h2>
                <p>
                    Dua-duanya bisa. Pilih yang paling pas sama gaya belajar dan target kamu.
                </p>
            </div>

            <div class="lp-programs">
                <article class="lp-program" data-reveal>
                    <span class="lp-program-icon"><i class="bi bi-person-fill"></i></span>
                    <h3>Privat 1-on-1</h3>
                    <p>
                        Buat kamu yang pengin fokus penuh. Materi dibahas satu lawan satu,
                        jadi bisa lebih dalam dan lebih leluasa tanya.
                    </p>
                    <ul>
                        <li><i class="bi bi-check2"></i> 1 siswa, 1 guru</li>
                        <li><i class="bi bi-check2"></i> Materi menyesuaikan level</li>
                        <li><i class="bi bi-check2"></i> Lebih leluasa tanya</li>
                    </ul>
                    <div class="lp-program-note">Cocok buat persiapan ujian atau mapel yang bikin stuck.</div>
                </article>

                <article class="lp-program lp-program--featured" data-reveal style="--lp-delay: .08s">
                    <span class="lp-program-badge">Paling seru</span>
                    <span class="lp-program-icon"><i class="bi bi-people-fill"></i></span>
                    <h3>Grup Kecil</h3>
                    <p>
                        Belajar bareng 2–7 siswa. Bisa diskusi, saling bantu,
                        dan tetap dapat perhatian dari guru.
                    </p>
                    <ul>
                        <li><i class="bi bi-check2"></i> 2–7 siswa per grup</li>
                        <li><i class="bi bi-check2"></i> Bisa banyak mapel</li>
                        <li><i class="bi bi-check2"></i> Suasana santai dan terarah</li>
                    </ul>
                    <div class="lp-program-note">Pas buat kamu yang lebih semangat kalau belajar bareng.</div>
                </article>

                <article class="lp-program" data-reveal style="--lp-delay: .16s">
                    <span class="lp-program-icon"><i class="bi bi-journal-bookmark-fill"></i></span>
                    <h3>Pilih Mapel</h3>
                    <p>
                        Satu grup bisa punya beberapa mata pelajaran.
                        Tiap mapel diampu guru yang fokus di bidangnya.
                    </p>
                    <ul>
                        <li><i class="bi bi-check2"></i> Tiap mapel ada gurunya</li>
                        <li><i class="bi bi-check2"></i> Jadwal menyesuaikan</li>
                        <li><i class="bi bi-check2"></i> Mulai dari level kamu</li>
                    </ul>
                    <div class="lp-program-note">Tinggal bilang mapel apa yang mau kamu dalami.</div>
                </article>
            </div>

            <div class="lp-price-note" data-reveal>
                <i class="bi bi-info-circle-fill"></i>
                <span>
                    <strong>Soal biaya, transparan.</strong>
                    Tarif paket dihitung per siswa dan menyesuaikan jenis kelas serta jumlah anggota.
                    Admin bakal jelasin rinciannya sebelum kamu mulai — nggak ada biaya kejutan.
                </span>
            </div>

            <div class="lp-subjects-panel" data-reveal>
                <span class="lp-subjects-label">
                    <i class="bi bi-journal-bookmark-fill"></i>
                    Mapel yang tersedia
                </span>
                <?php if ($subjectChips !== []): ?>
                    <div class="lp-subject-chips">
                        <?php foreach ($subjectChips as $subjectName): ?>
                            <span class="lp-subject-chip"><?= e($subjectName) ?></span>
                        <?php endforeach; ?>
                        <?php if ($remainingSubjects > 0): ?>
                            <span class="lp-subject-chip lp-subject-chip--more">
                                +<?= e((string) $remainingSubjects) ?> mapel lain
                            </span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="lp-subjects-empty">
                        Daftar mapel lagi disiapkan. Kamu tetap bisa tanya admin soal mapel
                        yang mau dipelajari, nanti dibantu dicarikan kelasnya.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- =============================================================
         Cara belajar
         ============================================================= -->
    <section class="lp-section" id="cara-belajar">
        <div class="lp-container">
            <div class="lp-section-head" data-reveal>
                <span class="lp-eyebrow"><i class="bi bi-signpost-split-fill"></i> Cara belajarnya</span>
                <h2>Mulai dari obrolan santai, <span class="lp-accent">lanjut sampai jago</span>.</h2>
                <p>
                    Nggak ada proses ribet. Kamu cerita, kami bantu cocokkan,
                    lalu belajar jalan sesuai ritme kamu.
                </p>
            </div>

            <div class="lp-steps">
                <div class="lp-step" data-reveal>
                    <div class="lp-step-num">01</div>
                    <h3>Ceritain kebutuhanmu</h3>
                    <p>Bilang aja mapel, kelas, dan target kamu. Nggak perlu formal, santai aja.</p>
                </div>
                <div class="lp-step" data-reveal style="--lp-delay: .07s">
                    <div class="lp-step-num">02</div>
                    <h3>Cocokkan kelas &amp; jadwal</h3>
                    <p>Admin bantu carikan grup kecil atau sesi privat yang paling pas buat kamu.</p>
                </div>
                <div class="lp-step" data-reveal style="--lp-delay: .14s">
                    <div class="lp-step-num">03</div>
                    <h3>Belajar bareng</h3>
                    <p>Masuk kelas, pahami materi pelan-pelan, dan tanya apa aja yang masih bikin bingung.</p>
                </div>
                <div class="lp-step" data-reveal style="--lp-delay: .21s">
                    <div class="lp-step-num">04</div>
                    <h3>Pantau progres &amp; lanjut</h3>
                    <p>Tiap paket 8 pertemuan. Habis itu, kamu lanjut ke target berikutnya.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================================
         Paket 8 pertemuan
         ============================================================= -->
    <section class="lp-section lp-section--tight" id="paket">
        <div class="lp-container">
            <div class="lp-package" data-reveal>
                <div class="lp-package-copy">
                    <span class="lp-eyebrow lp-eyebrow--dark"><i class="bi bi-calendar2-check-fill"></i> Paket belajar</span>
                    <h2>Satu paket = 8 pertemuan nyata.</h2>
                    <p>
                        Bukan hitungan bulan yang bikin bingung. Kamu belajar 8 kali pertemuan
                        beneran, dihitung dari semua mapel dalam grup. Simpel dan adil.
                    </p>
                    <ul class="lp-package-list">
                        <li><i class="bi bi-check2-circle"></i> Dihitung dari seluruh mata pelajaran dalam grup.</li>
                        <li><i class="bi bi-check2-circle"></i> Hanya pertemuan yang benar-benar terlaksana yang dihitung.</li>
                        <li><i class="bi bi-check2-circle"></i> Jadwal fleksibel, nggak dibatasi per minggu.</li>
                        <li><i class="bi bi-check2-circle"></i> Habis 8 pertemuan, langsung lanjut ke paket berikutnya.</li>
                    </ul>
                </div>
                <div class="lp-package-visual" aria-hidden="true">
                    <div class="lp-package-card">
                        <div class="lp-package-card-top">
                            <span>Paket belajar</span>
                            <strong>Aktif</strong>
                        </div>
                        <div class="lp-package-big">
                            <strong>8</strong>
                            <small>pertemuan<br>nyata</small>
                        </div>
                        <div class="lp-package-grid">
                            <?php for ($meeting = 1; $meeting <= 8; $meeting++): ?>
                                <span><?= e((string) $meeting) ?></span>
                            <?php endfor; ?>
                        </div>
                        <div class="lp-package-card-foot">
                            <i class="bi bi-arrow-repeat"></i>
                            Progres kamu, tercatat rapi
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- =============================================================
         FAQ
         ============================================================= -->
    <section class="lp-section lp-section--alt" id="faq">
        <div class="lp-container lp-faq-layout">
            <div class="lp-faq-intro" data-reveal>
                <span class="lp-eyebrow"><i class="bi bi-chat-dots-fill"></i> Masih penasaran?</span>
                <h2>Pertanyaan yang sering <span class="lp-accent">ditanyain</span>.</h2>
                <p>
                    Kalau jawabannya belum ada di sini, tinggal tanya admin.
                    Nggak ada pertanyaan yang terlalu kecil buat memulai.
                </p>
                <?php if ($contactPrimaryLink !== ''): ?>
                    <a class="lp-btn lp-btn--dark"
                       href="<?= e($contactPrimaryLink) ?>"
                       <?= $isExternal($contactPrimaryLink) ? 'target="_blank" rel="noopener"' : '' ?>>
                        <i class="bi bi-chat-dots-fill"></i>
                        Tanya admin
                    </a>
                <?php else: ?>
                    <a class="lp-btn lp-btn--dark" href="#program">
                        <i class="bi bi-grid-1x2-fill"></i>
                        Lihat program
                    </a>
                <?php endif; ?>
            </div>

            <div class="lp-faq-list">
                <?php foreach ($faqItems as $index => $faq): ?>
                    <details class="lp-faq-item" <?= $index === 0 ? 'open' : '' ?>>
                        <summary>
                            <?= e($faq['q']) ?>
                            <span class="lp-faq-icon"><i class="bi bi-chevron-down"></i></span>
                        </summary>
                        <div class="lp-faq-answer"><?= e($faq['a']) ?></div>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- =============================================================
         Kontak
         ============================================================= -->
    <?php if ($hasContact): ?>
        <section class="lp-section" id="kontak">
            <div class="lp-container">
                <div class="lp-cta" data-reveal>
                    <div class="lp-cta-copy">
                        <span class="lp-eyebrow lp-eyebrow--dark"><i class="bi bi-send-fill"></i> Kontak</span>
                        <h2>Siap mulai? Kita bantu dari nol.</h2>
                        <p>
                            Nggak perlu takut nanya. Ceritain aja kebutuhan belajar kamu,
                            nanti admin bantu carikan kelas yang paling pas.
                        </p>
                    </div>
                    <div class="lp-cta-actions">
                        <?php if ($contactPrimaryLink !== ''): ?>
                            <a class="lp-btn <?= e($contactPrimaryClass) ?> lp-btn--lg"
                               href="<?= e($contactPrimaryLink) ?>"
                               <?= $isExternal($contactPrimaryLink) ? 'target="_blank" rel="noopener"' : '' ?>>
                                <i class="bi <?= e($contactPrimaryIcon) ?>"></i>
                                <?= e($contactPrimaryLabel) ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($contactPrimaryLink !== $registerLink): ?>
                            <a class="lp-btn lp-btn--gold lp-btn--lg"
                               href="<?= e($registerLink) ?>">
                                <i class="bi bi-pencil-square"></i>
                                Daftar Sekarang
                            </a>
                        <?php endif; ?>
                        <a class="lp-btn lp-btn--ghost lp-btn--lg" href="#program">
                            Lihat program
                            <i class="bi bi-arrow-up-right"></i>
                        </a>
                    </div>
                </div>

                <div class="lp-contact-grid">
                    <?php if ($waLink !== ''): ?>
                        <a class="lp-contact-item" href="<?= e($waLink) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-whatsapp"></i>
                            <span>
                                <small>WhatsApp</small>
                                <strong><?= e(phone_display($authPhone)) ?></strong>
                            </span>
                        </a>
                    <?php elseif ($phoneLink !== ''): ?>
                        <a class="lp-contact-item" href="<?= e($phoneLink) ?>">
                            <i class="bi bi-telephone-fill"></i>
                            <span>
                                <small>Telepon</small>
                                <strong><?= e(phone_display($authPhone)) ?></strong>
                            </span>
                        </a>
                    <?php endif; ?>

                    <?php if ($emailLink !== ''): ?>
                        <a class="lp-contact-item" href="<?= e($emailLink) ?>">
                            <i class="bi bi-envelope-fill"></i>
                            <span>
                                <small>Email</small>
                                <strong><?= e($authEmail) ?></strong>
                            </span>
                        </a>
                    <?php endif; ?>

                    <?php if ($authWebsite !== ''): ?>
                        <a class="lp-contact-item" href="<?= e($authWebsite) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-globe2"></i>
                            <span>
                                <small>Website</small>
                                <strong><?= e($authWebsiteLabel !== '' ? $authWebsiteLabel : $authWebsite) ?></strong>
                            </span>
                        </a>
                    <?php endif; ?>

                    <?php if ($authAddress !== ''): ?>
                        <div class="lp-contact-item">
                            <i class="bi bi-geo-alt-fill"></i>
                            <span>
                                <small>Alamat</small>
                                <strong><?= e($authAddress) ?></strong>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>

<footer class="lp-footer">
    <div class="lp-container">
        <div class="lp-footer-main">
            <div>
                <div class="lp-footer-brand">
                    <img class="<?= e(Profile::logoOnDarkClass()) ?>"
                         src="<?= e($logoUrl) ?>"
                         alt=""
                         loading="lazy">
                    <div class="lp-brand-text">
                        <span class="lp-brand-name"><?= e($brand) ?></span>
                        <?php if ($profileTagline !== ''): ?>
                            <span class="lp-brand-sub"><?= e($profileTagline) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="lp-footer-desc">
                    Bimbingan belajar dengan kelas kecil, guru yang sabar, dan target yang jelas.
                    Belajar jadi terasa menyenangkan, hasilnya tetap serius.
                </p>
            </div>

            <div>
                <h3 class="lp-footer-heading">Jelajahi</h3>
                <ul class="lp-footer-links">
                    <li><a href="#beranda">Beranda</a></li>
                    <li><a href="#program">Program</a></li>
                    <li><a href="#keunggulan">Keunggulan</a></li>
                    <li><a href="#cara-belajar">Cara belajar</a></li>
                    <li><a href="#faq">FAQ</a></li>
                    <?php if ($hasContact): ?>
                        <li><a href="#kontak">Kontak</a></li>
                    <?php endif; ?>
                    <li><a href="<?= e($registerLink) ?>">Daftar siswa baru</a></li>
                </ul>
            </div>

            <div>
                <h3 class="lp-footer-heading"><?= $hasContact ? 'Kontak bimbel' : 'Mau mulai?' ?></h3>
                <?php if ($hasContact): ?>
                    <ul class="lp-footer-contact">
                        <?php if ($waLink !== '' || $phoneLink !== ''): ?>
                            <li>
                                <i class="bi bi-telephone-fill"></i>
                                <span><?= e(phone_display($authPhone)) ?></span>
                            </li>
                        <?php endif; ?>
                        <?php if ($emailLink !== ''): ?>
                            <li>
                                <i class="bi bi-envelope-fill"></i>
                                <a href="<?= e($emailLink) ?>"><?= e($authEmail) ?></a>
                            </li>
                        <?php endif; ?>
                        <?php if ($authAddress !== ''): ?>
                            <li>
                                <i class="bi bi-geo-alt-fill"></i>
                                <span><?= e($authAddress) ?></span>
                            </li>
                        <?php endif; ?>
                        <?php if ($authWebsite !== ''): ?>
                            <li>
                                <i class="bi bi-globe2"></i>
                                <a href="<?= e($authWebsite) ?>" target="_blank" rel="noopener">
                                    <?= e($authWebsiteLabel !== '' ? $authWebsiteLabel : $authWebsite) ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                <?php else: ?>
                    <p class="lp-footer-desc">
                        Pilih program yang paling pas, lalu ngobrol sama admin
                        soal jadwal dan kelas.
                    </p>
                    <a class="lp-btn lp-btn--gold lp-footer-cta" href="<?= e($registerLink) ?>">
                        <i class="bi bi-pencil-square"></i>
                        Daftar sekarang
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="lp-footer-bottom">
            <span>&copy; <?= e((string) date('Y')) ?> <?= e($brand) ?>. Semua hak dilindungi.</span>
            <span>
                <a href="<?= e(url('login.php')) ?>">Area admin</a>
            </span>
        </div>
    </div>
</footer>

<?php if ($waLink !== ''): ?>
    <a class="lp-wa" href="<?= e($waLink) ?>" target="_blank" rel="noopener"
       aria-label="Chat admin via WhatsApp" title="Chat WhatsApp">
        <i class="bi bi-whatsapp"></i>
        <span>Chat admin</span>
    </a>
<?php endif; ?>

<script src="<?= e(asset('js/landing.js')) ?>" defer></script>
</body>
</html>
