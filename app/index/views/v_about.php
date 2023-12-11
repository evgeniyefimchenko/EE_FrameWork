<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} ?>
<div style="width: 100%; text-align: center;">
	<h1>Проект является Open source лёгким PHP фреймворком</h1>
	<h2>Автор проекта <a href="https://efimchenko.ru" target="_blank">efimchenko.ru</a></h2>
	<h3>Код проекта находится тут <a href="https://github.com/evgeniyefimchenko/EE_FrameWork" target="_blank">https://github.com/evgeniyefimchenko/EE_FrameWork</a></h3>
	<h4><a href="<?=ENV_URL_SITE?>">На главную</a></h4>
</div>