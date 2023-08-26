<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
?>
<!-- Панель пользователя -->
<div class="content">
    <div class="container-fluid">
        Панель пользователя
    </div>
</div>