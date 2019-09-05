<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} ?>

<div class="container">
	<div class="row">
		Вас приветствует EE_FRAMEFORK v<?=ENV_VERSION_CORE?>
	</div>
</div>
