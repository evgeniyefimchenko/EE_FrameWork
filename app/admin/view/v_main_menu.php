<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
/**
* Главное меню админ-панели
* Настраивается опционально
*/
?>
<ul class="nav">
    <li id="main-item-menu" class="">
        <a class="nav-link" href="/admin">
            <i class="nc-icon nc-button-power"></i>
            <p>Главная</p>
        </a>
    </li>
    <?php if (in_array($user_role, array(1,2))){?>
    <li id="users-item-menu" class="">
        <a class="nav-link" href="/admin/users">
            <i class="nc-icon nc-circle-09"></i>
            <p>Пользователи</p>
        </a>
    </li>
    <?php }?>
    <?php if (in_array($user_role, array(1,2,3))){?>
    <li style="display:none;" id="customers-item-menu" class="">
        <a class="nav-link" href="/admin/customers">
            <i class="nc-icon nc-satisfied"></i>
            <p>Клиенты</p>
        </a>
    </li>
    <?php }?>
    <?php if (in_array($user_role, array(1,2))){?>
    <li style="display:none;" id="managers-item-menu" class="">
        <a class="nav-link" href="/admin/managers">
            <i class="nc-icon nc-single-02"></i>
            <p>Менеджеры</p>
        </a>
    </li>
    <?php }?>
    <?php if (in_array($user_role, array(1,2))){?>
    <li style="display:none;" id="partners-item-menu" class="">
        <a class="nav-link" href="/admin/partners">
            <i class="nc-icon nc-tap-01"></i>
            <p>Партнёры(точки)</p>
        </a>
    </li>
    <?php }?>
    <?php if (in_array($user_role, array(1,2))){?>
    <li style="display:none;" id="licenses-item-menu" class="">
        <a class="nav-link" href="/admin/licenses">
            <i class="nc-icon nc-bullet-list-67"></i>
            <p>Лицензии</p>
        </a>
    </li>
    <?php }?>
    <?php if (in_array($user_role, array(1,2))){?>
    <li style="display:none;" id="suppliers-item-menu" class="">
        <a class="nav-link" href="/admin/suppliers">
            <i class="nc-icon nc-bag"></i>
            <p>Поставщики</p>
        </a>
    </li>
    <?php }?>
    <?php if (in_array($user_role, array(1,2))){?>
    <li style="display:none;" id="prices-item-menu" class="">
        <a class="nav-link" href="/admin/prices">
            <i class="nc-icon nc-paper-2"></i>
            <p>Прайсы</p>
        </a>
    </li>
    <?php }?>    
    <li class="nav-item active active-pro" style="display:none;">
        <a class="nav-link active" href="/admin/upgrade">
            <i class="nc-icon nc-credit-card"></i>
            <p>Докупить опций</p>
        </a>
    </li>
</ul>