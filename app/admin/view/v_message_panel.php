<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
?>
<!-- MessageUser -->
<?php if ($count_unread_messages): ?>	
    <li class="dropdown nav-item">
        <a href="#" class="dropdown-toggle nav-link" data-toggle="dropdown">
            <i class="nc-icon nc-time-alarm"></i>
            <span class="notification"><?= $count_unread_messages ?></span>
            <span class="d-lg-none">Messages</span>
        </a>
        <ul class="dropdown-menu">
            <?php foreach ($unread_messages as $message) { ?>
                <a class="dropdown-item" href="<?= ENV_URL_SITE . '/admin/messages' ?>"><?= SysClass::truncate_string($message['message_text'], 70) ?></a>
            <?php } ?>
            <div class="divider"></div><a id="set_readed_all" data-return="<?= $_SERVER['REQUEST_URI'] ?>" class="dropdown-item" href="javascript:void(0);">Mark All Read</a>
        </ul>
    </li>
<?php endif ?>
<!-- endMessageUser -->