<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} ?>

<div class="container">
	<div class="row mt-4">
		<div class="col-6 text-center">Вас приветствует EE_FRAMEFORK v<?=ENV_VERSION_CORE?></div>
		<div class="col-6">			
			<?php if ($get_db && !$logged_in) { ?>
				<button autofocus="autofocus" class="btn btn-success" type="button" onclick="document.location.href = '/show_login_form'">Авторизация</button>
			<?php } else { ?>
				<?='get_db='.$get_db?>  Теперь наполните свой сайт!  <?='logged_in='.$logged_in?>
			<?php } ?>
		</div>
	</div>
</div>
