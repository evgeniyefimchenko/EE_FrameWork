<div style="width: 100%; text-align: center;">
	<?=$top_panel?>
	<h1>Начало!</h1>
	<?php if (isset($user_id)) { ?>
		<a href="/admin">Админ-панель</a><br/>
	<?php } else { ?>
		<a href="/show_login_form">Авторизация</a><br/>
	<?php } ?>
	<a href="/about">О проекте</a><br/>
	<a href="/contact">Контакты</a><br/>
</div>
