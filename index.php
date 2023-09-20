<?php

/**
 * EE_FrameWork
 * Простой фреймворк построенный на базе схемы разделения данных приложения MVC
 * Написан на языке PHP 5.6+ и использует следующие библиотеки
 * Bootstrap 5 https://stackpath.bootstrapcdn.com
 * jQuery latest http://code.jquery.com/jquery-latest.min.js
 * FontAwesom latest https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css 
 * В EeFramework используются следующие плагины
 * php_libmail http://webi.ru/webi_files/php_libmail.html - работа с почтой
 * SafeMySQL http://phpfaq.ru/safemysql (дописан под шаблон проектирования Singleton) - работа с БД MySql
 * @author Evgeniy Efimchenko efimchenko.ru
 */
if (version_compare(phpversion(), '8.0', '<') == true) {
    // die('PHP - Нужна версия 8.0 и выше ');
}
/*
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
 */

include ('inc/startup.php');

$router = new Router();
$router->setPath(ENV_SITE_PATH . ENV_APP_DIRECTORY);
$router->delegate();
