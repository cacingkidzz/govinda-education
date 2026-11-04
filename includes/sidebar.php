<?php
declare(strict_types=1);

$sidebarUser = $currentUser ?? Auth::user();
$activeMenu = $activeMenu ?? '';
?>
<aside class="app-sidebar sidebar-govinda shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="<?= e(url('admin/dashboard.php')) ?>" class="brand-link">
            <img src="<?= e(Profile::logoUrl()) ?>" alt="Logo <?= e(Profile::name()) ?>"
                 class="brand-image brand-logo <?= e(Profile::logoOnDarkClass()) ?>">
            <span class="brand-text">
                <span class="brand-name"><?= e(Profile::name()) ?></span>
                <?php if (Profile::tagline() !== ''): ?>
                    <span class="brand-sub"><?= e(Profile::tagline()) ?></span>
                <?php endif; ?>
            </span>
        </a>
    </div>

    <div class="sidebar-wrapper">
        <nav class="mt-3" aria-label="Navigasi utama">
            <div class="sidebar-section-label">Menu Utama</div>
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" data-accordion="false">
                <li class="nav-item">
                    <a href="<?= e(url('admin/dashboard.php')) ?>"
                       class="nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-grid-1x2-fill"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
            </ul>

            <div class="sidebar-section-label mt-3">Master Data</div>
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" data-accordion="false">
                <li class="nav-item">
                    <a href="<?= e(url('admin/siswa/index.php')) ?>"
                       class="nav-link <?= $activeMenu === 'siswa' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-people-fill"></i>
                        <p>Siswa</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/siswa/pendaftaran.php')) ?>"
                       class="nav-link <?= $activeMenu === 'pendaftaran' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-link-45deg"></i>
                        <p>Pendaftaran Mandiri</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/guru/index.php')) ?>"
                       class="nav-link <?= $activeMenu === 'guru' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-person-video3"></i>
                        <p>Guru</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/mata-pelajaran/index.php')) ?>"
                       class="nav-link <?= $activeMenu === 'mata-pelajaran' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-journal-bookmark-fill"></i>
                        <p>Mata Pelajaran</p>
                    </a>
                </li>
            </ul>

            <div class="sidebar-section-label mt-3">Akademik</div>
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" data-accordion="false">
                <li class="nav-item">
                    <a href="<?= e(url('admin/group/index.php')) ?>"
                       class="nav-link <?= $activeMenu === 'group' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-diagram-3-fill"></i>
                        <p>Group Belajar</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/group/mapel.php')) ?>"
                       class="nav-link <?= $activeMenu === 'group-mapel' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-journals"></i>
                        <p>Mata Pelajaran &amp; Guru</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/jadwal/index.php')) ?>"
                       class="nav-link <?= $activeMenu === 'jadwal' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-calendar-week-fill"></i>
                        <p>Jadwal Belajar</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/realisasi-pertemuan/index.php')) ?>"
                       class="nav-link <?= $activeMenu === 'realisasi' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-check2-square"></i>
                        <p>Realisasi Pertemuan</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/group/anggota.php')) ?>"
                       class="nav-link <?= $activeMenu === 'anggota-group' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-people-fill"></i>
                        <p>Anggota Group</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/group/riwayat.php')) ?>"
                       class="nav-link <?= $activeMenu === 'riwayat-group' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-arrow-left-right"></i>
                        <p>Riwayat Perpindahan</p>
                    </a>
                </li>
            </ul>

            <div class="sidebar-section-label mt-3">Keuangan</div>
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" data-accordion="false">
                <li class="nav-item">
                    <a href="<?= e(url('admin/tarif/index.php')) ?>"
                       class="nav-link <?= $activeMenu === 'tarif' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-cash-coin"></i>
                        <p>Tarif Paket</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/paket/index.php')) ?>"
                       class="nav-link <?= $activeMenu === 'paket' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-box-seam"></i>
                        <p>Paket Belajar</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/tagihan/index.php')) ?>"
                       class="nav-link <?= $activeMenu === 'tagihan' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-receipt"></i>
                        <p>Tagihan</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/pembayaran/index.php')) ?>"
                       class="nav-link <?= $activeMenu === 'pembayaran' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-cash-stack"></i>
                        <p>Pembayaran</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/pengeluaran/index.php')) ?>"
                       class="nav-link <?= $activeMenu === 'pengeluaran' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-arrow-up-right-circle"></i>
                        <p>Pengeluaran</p>
                    </a>
                </li>
            </ul>

            <div class="sidebar-section-label mt-3">Laporan</div>
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" data-accordion="false">
                <li class="nav-item">
                    <a href="<?= e(url('admin/laporan/keuangan.php')) ?>"
                       class="nav-link <?= $activeMenu === 'laporan-keuangan' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-graph-up-arrow"></i>
                        <p>Rekap Keuangan</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/laporan/pembayaran.php')) ?>"
                       class="nav-link <?= $activeMenu === 'laporan-pembayaran' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-wallet2"></i>
                        <p>Laporan Pembayaran</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/laporan/tagihan.php')) ?>"
                       class="nav-link <?= $activeMenu === 'laporan-tagihan' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-receipt-cutoff"></i>
                        <p>Laporan Tagihan</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/laporan/paket.php')) ?>"
                       class="nav-link <?= $activeMenu === 'laporan-paket' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-boxes"></i>
                        <p>Laporan Paket</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/laporan/realisasi.php')) ?>"
                       class="nav-link <?= $activeMenu === 'laporan-realisasi' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-calendar-check"></i>
                        <p>Laporan Realisasi</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= e(url('admin/laporan/utang.php')) ?>"
                       class="nav-link <?= $activeMenu === 'laporan-utang' ? 'active' : '' ?>">
                        <i class="nav-icon bi bi-exclamation-circle"></i>
                        <p>Laporan Utang</p>
                    </a>
                </li>
            </ul>

            <?php if ($sidebarUser !== null && $sidebarUser['role'] === 'admin'): ?>
                <div class="sidebar-section-label mt-3">Sistem</div>
                <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" data-accordion="false">
                    <li class="nav-item">
                        <a href="<?= e(url('admin/profil/index.php')) ?>"
                           class="nav-link <?= $activeMenu === 'profil' ? 'active' : '' ?>">
                            <i class="nav-icon bi bi-building-gear"></i>
                            <p>Profil Bimbel</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= e(url('admin/log-aktivitas/index.php')) ?>"
                           class="nav-link <?= $activeMenu === 'log-aktivitas' ? 'active' : '' ?>">
                            <i class="nav-icon bi bi-shield-check"></i>
                            <p>Log Aktivitas</p>
                        </a>
                    </li>
                </ul>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer-card">
            <div class="d-flex align-items-center gap-2">
                <span class="sidebar-footer-icon"><i class="bi bi-mortarboard-fill"></i></span>
                <div>
                    <div class="sidebar-footer-title"><?= e(Profile::name()) ?></div>
                    <div class="sidebar-footer-text">Versi <?= e(APP_VERSION) ?></div>
                </div>
            </div>
            <div class="sidebar-footer-copy">&copy; <?= e(date('Y')) ?> <?= e(Profile::name()) ?></div>
        </div>
    </div>
</aside>
