<?php
if (!ENV_SITE) {
	http_response_code(404); die;
}
/*
if (isset($user_id)) {
	echo '<a class="btn btn-primary purple-block" id="login_button" href="/admin" aria-label="' . $lang['sys.dashboard'] . '">' . $lang['sys.dashboard'] . '</a>';
} else {
	echo '<a class="btn btn-primary purple-block" id="login_button" href="/show_login_form" aria-label="' . $lang['sys.authorization'] . '">' . $lang['sys.authorization'] . '</a>';
}
*/
?>