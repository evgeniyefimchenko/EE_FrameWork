<?php

/**
 * Евгений Ефимченко, efimchenko.com
 * Главная точка входа для приложения EE_FrameWork, инициализирует маршрутизацию и выводит отладочную информацию.
 * /index.php
 */
define('USE_APDEX_INDEX', false);

if (USE_APDEX_INDEX) {
    $start_time = microtime(true);
}

if (version_compare(phpversion(), '8.0', '<') == true) {
    die('PHP - Нужна версия 8.0 или выше!');
}

$debug = true;

if ($debug) { // Повреждает некоторые AJAX запросы
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

if ($debug && isset($_GET['phpinfo'])) {
    phpinfo();
    die;
}

require_once('inc/bootstrap.php');
ee_bootstrap_runtime();

\classes\system\BotGuard::guard();
$router = new \classes\system\Router();
$router->setPath(ENV_SITE_PATH . ENV_APP_DIRECTORY);
$router->delegate();

if (USE_APDEX_INDEX) {
    $end_time = microtime(true);
    $response_time = $end_time - $start_time;
    $url = $_SERVER['REQUEST_URI'];
    \classes\system\SysClass::preFile('apdex', 'USE_APDEX_INDEX', $url, $response_time);
}

if ($debug && !empty($_GET['show_debug'])) {
    echo '<div style="width: 100%; position: fixed; bottom: 0; text-align: center; z-index: 100000;"><div>';
    echo "Текущее использование памяти: " . (memory_get_usage(true) / 1024 / 1024) . " MB<br/>";
    echo "Пиковое использование памяти: " . (memory_get_peak_usage(true) / 1024 / 1024) . " MB<br/>";
    echo 'ENV_CONTROLLER_PATH: ' . ENV_CONTROLLER_PATH . '<br/>';
    echo 'ENV_CONTROLLER_NAME: ' . ENV_CONTROLLER_NAME . '<br/>';
    echo 'ENV_CONTROLLER_ACTION: ' . ENV_CONTROLLER_ACTION . '<br/>';
    echo 'ENV_CONTROLLER_ARGS: ' . var_export(ENV_CONTROLLER_ARGS, true) . '<br/>';
    echo 'ENV_CONTROLLER_FOLDER: ' . ENV_CONTROLLER_FOLDER . '<br/>';
    echo '</div></div>';
}
