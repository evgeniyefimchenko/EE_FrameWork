<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} ?>
<?= $top_bar ?>
<div id="layoutSidenav">
		<!-- General menu -->
		<?= $main_menu ?>                  
		<!-- Content -->
	<div id="layoutSidenav_content">
		<?= $body_view ?>
		<!-- End Content -->
		<?=$page_footer?>
	</div><!-- Закрывает крайний div из $body_view -->
</div>