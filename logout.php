<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    render_error_page(
        405,
        'Metode Tidak Diizinkan',
        'Logout harus dilakukan melalui tombol keluar pada aplikasi.'
    );
}

csrf_require();

Auth::logout(true);

redirect('login.php?logout=1');
