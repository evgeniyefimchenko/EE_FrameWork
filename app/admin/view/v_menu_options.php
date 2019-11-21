<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
?> 
<!-- Опционное меню  -->
<div class="fixed-plugin">
    <div class="dropdown show-dropdown">
        <a href="javascript:void(0)" data-toggle="dropdown">
            <i class="fa fa-cog"> </i>
        </a>

        <ul class="dropdown-menu">
            <li class="header-title">Приятной работы</li>
            <li class="adjustments-line">
                <a href="javascript:void(0)" class="switch-trigger">
                    <p>Фон для меню</p>
                    <label class="switch">
                        <input type="checkbox" data-toggle="switch" <?= $options['show_image_in_sidebar'] == 'yes' ? 'checked' : '' ?> data-on-color="primary" data-off-color="primary"><span class="toggle"></span>
                    </label>
                    <div class="clearfix"></div>
                </a>
            </li>
            <li class="adjustments-line">
                <a href="javascript:void(0)" class="switch-trigger background-color">
                    <p>Фильтр</p>
                    <div class="pull-right">
                        <span class="badge filter badge-black" data-color="black"></span>
                        <span class="badge filter badge-azure" data-color="azure"></span>
                        <span class="badge filter badge-green" data-color="green"></span>
                        <span class="badge filter badge-orange" data-color="orange"></span>
                        <span class="badge filter badge-red" data-color="red"></span>
                        <span class="badge filter badge-purple" data-color="purple"></span>
                    </div>
                    <div class="clearfix"></div>
                </a>
            </li>
            <li class="header-title">Изображения</li>

            <li class="check-image-background">
                <a class="img-holder switch-trigger" href="javascript:void(0)">
                    <img src="<?= ENV_URL_SITE ?>/uploads/images/backgrounds/sidebar-1.jpg" alt="">
                </a>
            </li>
            <li class="check-image-background">
                <a class="img-holder switch-trigger" href="javascript:void(0)">
                    <img src="<?= ENV_URL_SITE ?>/uploads/images/backgrounds/sidebar-2.jpg" alt="">
                </a>
            </li>
            <li class="check-image-background">
                <a class="img-holder switch-trigger" href="javascript:void(0)">
                    <img src="<?= ENV_URL_SITE ?>/uploads/images/backgrounds/sidebar-3.jpg" alt="">
                </a>
            </li>
            <li class="check-image-background">
                <a class="img-holder switch-trigger" href="javascript:void(0)">
                    <img src="<?= ENV_URL_SITE ?>/uploads/images/backgrounds/sidebar-4.jpg" alt="">
                </a>
            </li>
            <li class="check-image-background">
                <a class="img-holder switch-trigger" href="javascript:void(0)">
                    <img src="<?= ENV_URL_SITE ?>/uploads/images/backgrounds/sidebar-5.jpg" alt="">
                </a>
            </li>

            <li class="header-title" id="sharrreTitle">Спасибо что Вы с нами!</li>
        </ul>
    </div>
</div>