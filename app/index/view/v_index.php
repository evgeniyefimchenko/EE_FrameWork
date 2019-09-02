<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} ?>

<div class="container">
	<div class="row">
		Основная страница сайта
	</div>
</div>