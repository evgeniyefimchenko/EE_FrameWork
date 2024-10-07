<?php

if (version_compare(phpversion(), '8.0', '<') == true) {
    die('PHP - Нужна версия 8.0 или выше!');
}

$debug = 1;

if ($debug) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);  
}

if ($debug && isset($_GET['phpinfo'])) {
    phpinfo();
    die;
}

function ee_startUp() {    
    require_once('inc/configuration.php');     
    require_once ('inc/startup.php');    
    AutoloadManager::init(); // Необходимо для hooks.php
    require_once ('inc/hooks.php');
}

if (function_exists('opcache_get_status')) {
    $opcache_status = opcache_get_status(); // Основные классы и автолоадер загружены в память из файла /inc/preloader.php
    if (empty($opcache_status['preload_statistics'])) {
        // Предзагрузка не используется
        ee_startUp();        
    }
} else {    
    // OPcache недоступен
    ee_startUp();
}

// ВАЖНО: Всегда регистрируем автозагрузчик
AutoloadManager::init();

\classes\system\SysClass::guard();
$router = new classes\system\Router();
$router->setPath(ENV_SITE_PATH . ENV_APP_DIRECTORY);
$router->delegate();
