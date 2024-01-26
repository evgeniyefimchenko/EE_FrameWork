<?php

if (version_compare(phpversion(), '8.0', '<') == true) {
    die('PHP - Нужна версия 8.0 и выше ');
}

if ($debug = 1) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
 
include ('inc/configuration.php');
include ('inc/startup.php');
AutoloadManager::addNamespace('classes\system', ENV_SITE_PATH . 'classes' . ENV_DIRSEP . 'system' . ENV_DIRSEP);
AutoloadManager::addNamespace('classes\plugins', ENV_SITE_PATH . 'classes' . ENV_DIRSEP . 'plugins' . ENV_DIRSEP);
AutoloadManager::addNamespace('classes\helpers', ENV_SITE_PATH . 'classes' . ENV_DIRSEP . 'helpers' . ENV_DIRSEP);
AutoloadManager::addNamespace('app\admin', ENV_SITE_PATH . ENV_APP_DIRECTORY . ENV_DIRSEP . 'admin' . ENV_DIRSEP);
AutoloadManager::init();

$router = new classes\system\Router();
$router->setPath(ENV_SITE_PATH . ENV_APP_DIRECTORY);
$router->delegate();
