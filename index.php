<?php

if (version_compare(phpversion(), '8.0', '<') == true) {
    die('PHP - Нужна версия 8.0 и выше ');
}

if ($debug = 1) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
 
require_once('inc/configuration.php');
require_once ('inc/startup.php');

AutoloadManager::init();

$router = new classes\system\Router();
$router->setPath(ENV_SITE_PATH . ENV_APP_DIRECTORY);
$router->delegate();
