<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();

$state = pull_form_state();
$errors = $state['errors'];
$old = $state['old'];

$teacher = [
    'gender'    => 'L',
    'is_active' => 1,
];
$selectedSubjects = [];
$formMode = 'create';

$pageTitle = 'Tambah Guru';
$pageSubtitle = 'Lengkapi profil guru, kontak, dan mata pelajaran yang diampu.';
$breadcrumbs = [
    ['label' => 'Data Guru', 'url' => url('admin/guru/index.php')],
    ['label' => 'Tambah Guru'],
];
$activeMenu = 'guru';
$pageStyles = ['vendor/select2/select2.min.css', 'vendor/select2/select2-bootstrap-5-theme.min.css'];
$pageScripts = ['vendor/select2/select2.min.js'];

require_once ROOT_PATH . '/includes/header.php';
require __DIR__ . '/_form.php';
require_once ROOT_PATH . '/includes/footer.php';
