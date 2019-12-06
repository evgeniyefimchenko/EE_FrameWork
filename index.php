<?php

  /**
	* EE_FrameWork
	* Простой фреймворк построенный на базе схемы разделения данных приложения MVC
	* С русскими комментариями кода
	* Написан на языке PHP 5.6+ и использует следующие библиотеки
	* Bootstrap 4 https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css
	* jQuery latest http://code.jquery.com/jquery-latest.min.js
	* FontAwesom latest https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css 
	* В EeFramework используются следующие плагины
	* php_libmail_2_11 http://webi.ru/webi_files/php_libmail.html - работа с почтой
	* SafeMySQL http://phpfaq.ru/safemysql (дописан под шаблон проектирования Singleton) - работа с БД MySql
	* @author Evgeniy Efimchenko efimchenko.ru
  */
if (version_compare(phpversion(), '5.5.0', '<') == true) {
    die('PHP - Нужна версия >= 5.6.0');
}

include ('inc/startup.php');

$router = new Router();
$router->setPath(ENV_SITE_PATH . ENV_APP_DIRECTORY); // Путь к контроллерам
$router->delegate();         // Роутинг на контроллер
