<?php if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301)); ?>
<footer class="py-4 bg-light mt-auto">
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between small">
            <div class="text-muted">Copyright &copy; <?= ENV_SITE_NAME ?> <?= date('Y') ?></div>
            <div>
                <a href="#">Политика</a>
                &middot;
                <a href="#">Условия использования</a>
            </div>
        </div>
    </div>
</footer>