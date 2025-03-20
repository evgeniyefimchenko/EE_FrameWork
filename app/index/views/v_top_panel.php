<?php if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301)); ?>
<div class="text-center">
<?php
if (isset($user_id)) {
	echo '<a class="btn btn-primary purple-block" id="login_button" href="/admin" aria-label="' . $lang['sys.dashboard'] . '">' . $lang['sys.dashboard'] . '</a>';
} else {
	echo '<a class="btn btn-primary purple-block" id="login_button" href="/show_login_form" aria-label="' . $lang['sys.authorization'] . '">' . $lang['sys.authorization'] . '</a>';
}
?>
</div>