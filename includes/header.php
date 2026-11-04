<?php
declare(strict_types=1);

/** @var string $pageTitle */
/** @var string|null $pageSubtitle */
/** @var array<int, array{label:string,url?:string}> $breadcrumbs */
/** @var string $activeMenu */
/** @var array<int, string> $pageStyles */

$pageTitle = $pageTitle ?? Profile::name();
$pageSubtitle = $pageSubtitle ?? null;
$activeMenu = $activeMenu ?? '';
$breadcrumbs = $breadcrumbs ?? [['label' => 'Dashboard']];
$pageStyles = $pageStyles ?? [];
$user = Auth::user();
$flashes = pull_flashes();
?>
<!doctype html>
<html lang="id" data-lte-color-mode="off" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#0f766e">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> — <?= e(Profile::name()) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/favicon.svg')) ?>">

    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/overlayscrollbars/overlayscrollbars.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('adminlte/css/adminlte.min.css')) ?>">
    <?php foreach ($pageStyles as $style): ?>
        <link rel="stylesheet" href="<?= e(asset($style)) ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">

    <?php require __DIR__ . '/navbar.php'; ?>
    <?php require __DIR__ . '/sidebar.php'; ?>

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-sm-7">
                        <h1 class="page-heading mb-0"><?= e($pageTitle) ?></h1>
                        <?php if (!empty($pageSubtitle)): ?>
                            <p class="page-subheading mb-0"><?= e($pageSubtitle) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-5">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb justify-content-sm-end mb-0">
                                <li class="breadcrumb-item">
                                    <a href="<?= e(url('admin/dashboard.php')) ?>">
                                        <i class="bi bi-house-door me-1"></i>Beranda
                                    </a>
                                </li>
                                <?php $lastIndex = count($breadcrumbs) - 1; ?>
                                <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                    <?php if ($index === $lastIndex): ?>
                                        <li class="breadcrumb-item active" aria-current="page"><?= e($crumb['label']) ?></li>
                                    <?php else: ?>
                                        <li class="breadcrumb-item">
                                            <a href="<?= e($crumb['url'] ?? '#') ?>"><?= e($crumb['label']) ?></a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">

                <?php foreach ($flashes as $flash): ?>
                    <?php
                    $type = in_array($flash['type'], ['success', 'danger', 'warning', 'info'], true)
                        ? $flash['type']
                        : 'info';
                    $icon = match ($type) {
                        'success' => 'bi-check-circle-fill',
                        'danger'  => 'bi-x-circle-fill',
                        'warning' => 'bi-exclamation-triangle-fill',
                        default   => 'bi-info-circle-fill',
                    };
                    ?>
                    <div class="alert alert-<?= e($type) ?> alert-dismissible fade show app-alert" role="alert">
                        <i class="bi <?= e($icon) ?> me-2"></i><?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                    </div>
                <?php endforeach; ?>
