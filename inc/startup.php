<?php

$console = '';
/**
 * Файл загрущик констант проекта и автолоадер классов php
 * автолоадер не чувствителен к регистру символов имён файлов
 * @author Evgeniy Efimchenko efimchenko.ru
 */
/**
 * Включить необходимую конфигурационную информацию
 */
include_once 'configuration.php';

/**
 * Определить константы для конфигурационной информации
 */
foreach ($C as $name => $val) {
    define($name, $val);
}

/**
* Отловим фатальные ошибки
*/
register_shutdown_function(function() {
    if (ENV_FATAL_ERROR_LOGGING) {
		$error = error_get_last();
		if ($error && (in_array($error['type'], [E_ERROR,  E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR]))) {
			file_put_contents(ENV_SITE_PATH . 'fatal_errors.txt', date('d-m-Y h:i:s') . PHP_EOL . var_export($error, true), FILE_APPEND);
		}
	}
});

/**
 * Перезаписать файл robots
 * при отключенной индексации
 */
if (ENV_SITE_INDEX !== 'ALL') {
    $filename = ENV_SITE_PATH . 'robots.txt';
    $text = 'User-agent:* \n Disallow: /';
    file_put_contents($filename, $text, LOCK_EX);
} else {
    // Для снятия лишней нагрузки файл robots.txt, при включении индексации, редактируется вручную (User-agent:*)
}

/**
 * Автолоадер
 */
spl_autoload_register(function ($name) {
    global $console;
	$name = mb_strtolower($name);
	$filename = $name . '.php';
    if (strpos($name, 'class') !== false) { // Класс        
        $res = search_file('classes', $filename);		
    } elseif (strpos($name, 'trait') !== false) { // Трейт
        $callingFile = debug_backtrace()[1]['file'];
        $callingDir = dirname($callingFile);
        $res = search_file($callingDir, $filename);
    } else { // Системный или плагин класс
		$res = search_file('classes/system', $filename);
		if (!$res) $res = search_file('classes/plugins', $filename);
    }
    if ($res) {
        if (ENV_TEST) {
            echo $filename . ' <b>include class or trait:</b> <i>' . $res . '</i><br/>';
        }
    } else {
        if (!headers_sent()) {
            header("HTTP/1.0 200 OK");
        }
        echo 'Класс или трейт не найден: ' . $name . ' пути поиска:<br/>' . $console . '~';
    }
});

/**
 * Рекурсивный поиск файла класса
 */
function search_file($dir, $tosearch) {
    global $console;
    $files = array_diff(scandir($dir), [".", ".."]);
    foreach ($files as $d) {
        $path = $dir . "/" . $d;
        if (!is_dir($path)) { // Это не папка
            if (mb_strtolower($d) == $tosearch) { // Файл найден
                include_once($path);
                $info = pathinfo($path);
                if (class_exists($info['filename']) || trait_exists($info['filename'])) { // Класс или трейт найден
                    return $path;
                }
            }
        } else { // Это папка продолжаем рекурсию
            $console .= $path . '<br/>';
            $res = search_file($path, $tosearch);
            if ($res) {
                return $res;
            }
        }
    }
    return false;
}
