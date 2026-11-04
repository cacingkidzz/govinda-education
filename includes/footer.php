<?php
declare(strict_types=1);

/** @var array<int, string> $pageScripts */
/** @var string $pageInlineScript */
$pageScripts = $pageScripts ?? [];
$pageInlineScript = $pageInlineScript ?? '';
?>
            </div>
        </div>
    </main>

    <footer class="app-footer">
        <div class="float-end d-none d-sm-inline">
            <span class="footer-version">v<?= e(APP_VERSION) ?></span>
        </div>
        <strong><?= e(Profile::name()) ?></strong>
        <span class="text-secondary">
            — <?= e(Profile::tagline() !== '' ? Profile::tagline() : 'Sistem Manajemen Bimbingan Belajar') ?>
        </span>
        <span class="d-block d-sm-inline text-secondary ms-sm-2">
            &copy; <?= e(date('Y')) ?> <?= e(Profile::name()) ?>
        </span>
    </footer>
</div>

<script src="<?= e(asset('vendor/jquery/jquery.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/overlayscrollbars/overlayscrollbars.browser.es6.min.js')) ?>"></script>
<script src="<?= e(asset('adminlte/js/adminlte.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.all.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($pageScripts as $script): ?>
    <script src="<?= e(asset($script)) ?>"></script>
<?php endforeach; ?>
<?php if ($pageInlineScript !== ''): ?>
<script>
<?= $pageInlineScript ?>
</script>
<?php endif; ?>
</body>
</html>
