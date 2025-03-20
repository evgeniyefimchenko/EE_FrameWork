<?php if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301)); ?>
<style>
.list-group-submenu {
    margin-left: 20px;
}

.list-group-submenu .list-group-submenu {
    margin-left: 20px;
}

.list-group-item i.float-end {
    transition: transform 0.3s ease;
}

.list-group-submenu li a:hover {
    background-color: #f8f9fa;
}
</style>

<div id="doc-menu-wrapper" class="list-group">
    <a href="#" class="list-group-item list-group-item-action" data-doc="introduction">
        <i class="fas fa-book-open"></i> Введение
    </a>
    <a href="#" class="list-group-item list-group-item-action" data-doc="installation">
        <i class="fas fa-tools"></i> Установка
    </a>
    <a href="#" class="list-group-item list-group-item-action">
        <i class="fas fa-cogs"></i> Конфигурация<i class="fas fa-chevron-down float-end"></i>
    </a>
    <ul class="list-group-submenu">
        <li>
            <a href="#" class="list-group-item list-group-item-action" data-doc="configuration">
                <i class="fas fa-sliders-h"></i> Основные настройки
            </a>
        </li>
        <li>
            <a href="#" class="list-group-item list-group-item-action" data-doc="configuration-advanced">
                <i class="fas fa-wrench"></i> Продвинутые настройки
            </a>
        </li>
    </ul>
    <a href="#" class="list-group-item list-group-item-action" data-doc="content">
        <i class="fas fa-file-alt"></i> Контент
    </a>
    <a href="#" class="list-group-item list-group-item-action" data-doc="hooks">
        <i class="fas fa-code"></i> Хуки
    </a>
    <a href="#" class="list-group-item list-group-item-action">
        <i class="fas fa-layer-group"></i> Классы<i class="fas fa-chevron-down float-end"></i>
    </a>
    <ul class="list-group-submenu">
        <li>
            <a href="#" class="list-group-item list-group-item-action" data-doc="safepostgresql">
                <i class="fas fa-database"></i> Класс для работы с PostgreSQL
            </a>
        </li>
        <li>
            <a href="#" class="list-group-item list-group-item-action">
                <i class="fas fa-cogs"></i> Системные классы<i class="fas fa-chevron-down float-end"></i>
            </a>
            <ul class="list-group-submenu">
                <li>
                    <a href="#" class="list-group-item list-group-item-action" data-doc="sysclass">
                        SysClass
                    </a>
                </li>
                <li>
                    <a href="#" class="list-group-item list-group-item-action" data-doc="controllerbase">
                        ControllerBase
                    </a>
                </li>
            </ul>            
        </li>
    </ul>    
</div>
