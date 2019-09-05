<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} ?>

<div class="content">
<div class="row">
<div class="col-12">
Приветствую идущий в знание!
</div>
</div>
</div>