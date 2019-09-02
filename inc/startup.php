<?php

/**
 * Файл загрущик констант проекта и автолоадер классов php
 * автолоадер не чувствителен к регистру символов имён файлов
 * @author Evgeniy Efimchenko efimchenko.ru
 */
include_once 'configuration.php';

foreach ($C as $name => $val) {
    define($name, $val);
}

/**
* Перезаписать файл robots
* при отключенной индексации
*/
if (ENV_SITE_INDEX !== 'ALL') {
	$filename = ENV_SITE_PATH . 'robots.txt';
	$text = 'User-agent:* \n Disallow: /';	
	file_put_contents($filename, $text, LOCK_EX);
} else {
	// Для снятия лишней нагрузки файл robots.txt, при включении индексации, редактируется в ручную (User-agent:*)
}

spl_autoload_register(function($class_name) {
    $filename = $class_name . '.php';
    $res = search_file('classes', $filename);
    if ($res) {
        if (ENV_TEST) {
            echo 'include:' . $res . ' | ';
        }		
        include_once ($res);
    } else {
        echo 'Класс не найден: ' . $class_name . '<br/>пути поиска: ' . $console . '~';
        return FALSE;
    }
});

function search_file($dir, $tosearch) {
    global $console;	
    $files = array_diff(scandir($dir), Array(".", ".."));
    foreach ($files as $d) {
        $path = $dir . "/" . $d;
        if (!is_dir($path)) { // Это не папка
            if (strtolower($d) == strtolower($tosearch)) {
                return $path;
            }
        } else { // Это папка продолжаем рекурсию
		    $console .= $path . '<br/>';
            $res = search_file($dir . "/" . $d, $tosearch);
            if ($res) {
                return $res;
            }
        }
    }
    return false;
}
