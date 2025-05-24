<?php

// 1. Проверка запуска из командной строки
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    error_log('CRON Bootstrap Error: Access denied. Script must be run from CLI.');
    die('Error: Access denied This script can only be run from the command line');
}

// 2. Установка путей
define('CRON_SCRIPT_DIR', __DIR__);
define('PROJECT_ROOT_DIR', dirname(CRON_SCRIPT_DIR));

// 3. Настройка ошибок для CLI
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
// ini_set('error_log', PROJECT_ROOT_DIR . '/logs/cron_php_errors.log');

echo "CRON Bootstrap: Starting environment setup..." . PHP_EOL;

// 4. Определение ENV_SITE_PATH (если нужно)
if (!defined('ENV_SITE_PATH')) {
    define('ENV_SITE_PATH', PROJECT_ROOT_DIR . DIRECTORY_SEPARATOR);
    echo "CRON Bootstrap: ENV_SITE_PATH defined as " . ENV_SITE_PATH . PHP_EOL;
} else {
    echo "CRON Bootstrap: ENV_SITE_PATH already defined." . PHP_EOL;
}

// 5. Загрузка Основной Конфигурации (ЗДЕСЬ УСТАНАВЛИВАЕТСЯ TIMEZONE, ЕСЛИ ЯЗЫК RU)
$configPath = PROJECT_ROOT_DIR . '/inc/configuration.php';
if (!file_exists($configPath)) {
     $errorMsg = 'CRON Bootstrap Error: Configuration file not found at ' . $configPath;
     error_log($errorMsg);
     die($errorMsg . PHP_EOL);
}
require_once $configPath;
echo "CRON Bootstrap: Configuration loaded (timezone might be set here based on ENV_DEF_LANG)." . PHP_EOL;

// 6. Загрузка startup.php (Определение AutoloadManager и др.)
$startupPath = PROJECT_ROOT_DIR . '/inc/startup.php';
if (!file_exists($startupPath)) {
     $errorMsg = 'CRON Bootstrap Error: Startup file not found at ' . $startupPath;
     error_log($errorMsg);
     die($errorMsg . PHP_EOL);
}
require_once $startupPath;
echo "CRON Bootstrap: Startup file loaded." . PHP_EOL;

// 7. Инициализация Автозагрузчика
if (!class_exists('AutoloadManager')) { // Проверяем глобальный класс
     $errorMsg = 'CRON Bootstrap Error: AutoloadManager class not found after including startup.php';
     error_log($errorMsg);
     die($errorMsg . PHP_EOL);
}
\AutoloadManager::init(); // Вызываем глобальный класс
echo "CRON Bootstrap: AutoloadManager initialized." . PHP_EOL;

// 8. Загрузка Хуков (опционально для CRON)
$hooksPath = PROJECT_ROOT_DIR . '/inc/hooks.php';
if (file_exists($hooksPath)) {
    require_once $hooksPath;
    echo "CRON Bootstrap: Hooks file loaded." . PHP_EOL;
} else {
    echo "CRON Bootstrap: Hooks file not found (optional)." . PHP_EOL;
}

// 9. Инициализация Соединения с БД
try {
    if (!class_exists('classes\plugins\SafeMySQL')) { // Проверяем класс с неймспейсом
        throw new \Exception('SafeMySQL class not found by autoloader');
    }
    \classes\plugins\SafeMySQL::gi(); // Вызываем класс с неймспейсом
    echo "CRON Bootstrap: Database connection initialized." . PHP_EOL;
} catch (\Throwable $e) {
    $errorMsg = 'CRON Bootstrap DB Error: ' . $e->getMessage();
    error_log($errorMsg);
    try { new \classes\system\ErrorLogger($errorMsg, 'CRON Bootstrap', 'db_error'); } catch (\Throwable $t) {}
    die($errorMsg . PHP_EOL);
}

// 10. Временная зона - БОЛЬШЕ НЕ УСТАНАВЛИВАЕМ ЗДЕСЬ
// Используется та, что была установлена в configuration.php или дефолтная серверная.
$currentTimezone = date_default_timezone_get();
echo "CRON Bootstrap: Effective timezone for script execution is " . $currentTimezone . "." . PHP_EOL;

echo "CRON Bootstrap: Environment setup complete." . PHP_EOL;
