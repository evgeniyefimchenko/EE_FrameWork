<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
?>
<!-- MessageUser -->
<?php if ($count_message): ?>	
    <li class="dropdown nav-item">
        <a href="#" class="dropdown-toggle nav-link" data-toggle="dropdown">
            <i class="nc-icon nc-time-alarm"></i>
            <span class="notification"><?= $count_message ?></span>
            <span class="d-lg-none">Сообщения</span>
        </a>
        <ul class="dropdown-menu">
            <?php foreach ($messages as $message){?>
				<a class="dropdown-item" href="<?= ENV_URL_SITE . '/admin/messages' ?>"><?= SysClass::truncate_string($message['message_text'], 70) ?></a>
            <?php }?>
            <div class="divider"></div><a id="set_readed_all" data-return="admin" class="dropdown-item" href="#">Отметить всё как прочитано</a>
        </ul>
    </li>
<?php endif ?>
<!-- endMessageUser -->