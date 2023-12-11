<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} ?>
<div style="width: 100%; text-align: center;">
	<h1>Контакты</h1>
	<h2>Автор проекта <a href="https://efimchenko.ru" target="_blank">efimchenko.ru</a> все контакты в подвале сайта</h2>
	<h3><a href="<?=ENV_URL_SITE?>">На главную</a></h3>
</div>