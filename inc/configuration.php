<?php

/**
 * Евгений Ефимченко, efimchenko.com
 * Определяет основные настройки конфигурации для EE_FrameWork, включая параметры базы данных и сайта
 * /inc/configuration.php
 */
 
global $protoLang;
$protoLang = 'EN';
 
function loadConfig(): array {
	global $protoLang;
    $config = [
        'ENV_VERSION_CORE' => '4.7.3',
        // Настройка базы данных
        'ENV_DB_HOST' => 'localhost',
        'ENV_DB_USER' => 'whrgijws_family',
        'ENV_DB_PASS' => '655351024',
        'ENV_DB_NAME' => 'whrgijws_skku',
        'ENV_DB_PREF' => 'ee_',
        // Технические настройки сайта
        'ENV_SITE_NAME' => 'skku.shop',
        'ENV_SITE_DESCRIPTION' => 'skku.shop',
        'ENV_SITE_AUTHOR' => 'efimchenko.com',
        'ENV_GET_KEYWORDS' => 0,
        'ENV_DATE_SITE_CREATE' => '13.02.2023',
        'ENV_DIRSEP' => DIRECTORY_SEPARATOR,
        'ENV_DOMEN_PROTOCOL' => !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? "https://" : "http://",
        'ENV_DOMEN_NAME' => $_SERVER['SERVER_NAME'],
        'ENV_SITE_PATH' => realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
        'ENV_LOG' => 1,
        'ENV_COMPRESS_HTML' => 0,
        'ENV_CREATE_WEBP' => true, // Преобразовывать все загруженные картинки в webp формат
        'ENV_WEBP_QUALITY' => 80, // Качество при конвертации
        'ENV_CACHE' => 0,
        'ENV_CACHE_LIFETIME' => 3600,
        'ENV_SECRET_KEY' => '(b[RX{28Z_9j;+k',
        'ENV_SITE' => 1,
        'ENV_TEST' => 0,
        'ENV_INSERT_TEST_DATA' => 1, // Вставить тестовые данные при создании таблиц
        'ENV_FATAL_ERROR_LOGGING' => 1,
        'ENV_CONFIRM_EMAIL' => 1,
        'ENV_TIME_AUTH_SESSION' => 86400 * 15,
        'ENV_TIME_ACTIVATION' => 86400 * 15,
        'ENV_SITE_INDEX' => 'noindex, nofollow',
        'ENV_FONT_AWESOME_CDN' => true,
        'ENV_BOOTSTRAP533_CDN' => true,
        'ENV_JQUERY_CDN' => true,
		'ENV_GUARD_REDIS' => 0, // Использовать REDIS для класса BotGuard
		'ENV_GUARD_RATE_LIMIT_COUNT' => 20, // количество запросов в период
		'ENV_GUARD_RATE_LIMIT_WINDOW' => 30, // период в секундах
		'ENV_GUARD_STRIKE_LIMIT' => 5, // Количество "страйков", после которых IP блокируется
		'ENV_GUARD_STRIKE_TTL' => 30, // Время в секундах, в течение которого считаются страйки
        'ENV_REDIS_ADDRESS' => '127.0.0.1',
        'ENV_REDIS_PORT' => 6379,
        'ENV_MAX_FILE_SIZE' => 10 * 1024 * 1024,
        'ENV_ROUTING_CACHE' => 0, // Время жизни не ограничено!!!
        // Персональные настройки сайта
        'ENV_APP_DIRECTORY' => 'app',
        'ENV_PATH_LANG' => 'inc' . DIRECTORY_SEPARATOR . 'langs' . DIRECTORY_SEPARATOR,
        'ENV_PROTO_LANGUAGE' => $protoLang,
        'ENV_SITE_EMAIL' => 'evgeniy@efimchenko.com',
        'ENV_ADMIN_EMAIL' => 'evgeniy@efimchenko.com',
        'ENV_SUPPORT_EMAIL' => 'evgeniy@efimchenko.com',
        'ENV_SMTP' => 0,
        'ENV_ONE_IP_ONE_USER' => 0,
        'ENV_AUTH_USER' => 2,
        'ENV_SMTP_PORT' => 465,
        'ENV_SMTP_SERVER' => '',
        'ENV_SMTP_LOGIN' => '',
        'ENV_SMTP_PASSWORD' => ''
    ];

    // Вычисляемые значения
    $config['ENV_URL_SITE'] = $config['ENV_DOMEN_PROTOCOL'] . $config['ENV_DOMEN_NAME'];
    $config['ENV_LOGS_PATH'] = $config['ENV_SITE_PATH'] . 'logs' . $config['ENV_DIRSEP'];
    $config['ENV_TMP_PATH'] = $config['ENV_SITE_PATH'] . 'uploads' . $config['ENV_DIRSEP'] . 'tmp' . $config['ENV_DIRSEP'];
    $config['ENV_CACHE_PATH'] = $config['ENV_SITE_PATH'] . 'cache' . $config['ENV_DIRSEP'];
    $config['ENV_EMAIL_TEMPLATE'] = $config['ENV_SITE_PATH'] . 'assets' . $config['ENV_DIRSEP'] . 'emails_templates';
    $config['ENV_DEF_LANG'] = strtoupper(substr(GetClientPreferedLanguage(), 0, 2)) ?: $config['ENV_PROTO_LANGUAGE'];

    if ($config['ENV_DEF_LANG'] == 'RU') {
        date_default_timezone_set('Europe/Moscow');
    }
    $isConnect = checkRedisConnection($config['ENV_REDIS_ADDRESS'], $config['ENV_REDIS_PORT'], $config);
    if (!$isConnect) {
        array_walk(
            $config,
            fn(&$value, $key) => in_array($key, ['ENV_CACHE_REDIS', 'ENV_GUARD_REDIS', 'ENV_ROUTING_CACHE']) ? $value = 0 : null
        );
    }
    return $config;
}

/**
 * Проверяет доступность Redis по указанным параметрам
 * @param string $address Адрес Redis сервера
 * @param int $port Порт Redis сервера
 * @param array $config Конфигурационный массив
 * @return bool Возвращает true, если Redis доступен, и false в противном случае
 */
function checkRedisConnection(string $address, int $port, array $config): bool {
    $cacheFile = $config['ENV_CACHE_PATH'] . 'redis_connection_check.cache';
	if (!is_dir($cacheDir)) {
		mkdir($cacheDir, 0755, true);
	}    
	if (file_exists($cacheFile)) {
        return (bool) file_get_contents($cacheFile);
    }
    try {
        if (!class_exists('\Redis')) {
            file_put_contents($cacheFile, '0', LOCK_EX);
            return false;
        }
        $redis = new \Redis();
        $redis->connect($address, $port);
        $isAvailable = ($redis->ping() == '+PONG');
        file_put_contents($cacheFile, $isAvailable ? '1' : '0', LOCK_EX);
        return $isAvailable;
    } catch (\RedisException $e) {
        $logMessage = sprintf(
                "{START}\nВремя события: %s\nИнициатор: %s\nРезультат: Ошибка подключения\nДетали: %s\n{END}\n",
                date("Y-m-d H:i:s"),
                'checkRedisConnection',
                'Ошибка подключения к Redis: ' . $e->getMessage()
        );
        file_put_contents($config['ENV_LOGS_PATH'] . 'errors' . DIRECTORY_SEPARATOR . date("Y-m-d") . '.txt', $logMessage, FILE_APPEND | LOCK_EX);
        file_put_contents($cacheFile, '0', LOCK_EX);
        return false;
    }
}

/**
 * Определяет предпочтительный язык клиента из заголовка HTTP_ACCEPT_LANGUAGE
 * @param bool $getSortedList Если true, возвращает отсортированный список языков с их весами
 * @param string|false $acceptedLanguages Строка заголовка 'Accept-Language'. Если false, используется $_SERVER['HTTP_ACCEPT_LANGUAGE']
 * @return string|array Возвращает код предпочтительного языка (например, 'en') или массив языков с их весами, если установлен $getSortedList
 */
function GetClientPreferedLanguage(bool $getSortedList = false, string|false $acceptedLanguages = false): string|array {
    global $protoLang;
    if ($acceptedLanguages === false) {
        $acceptedLanguages = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    }
    // Проверяем пустое значение и устанавливаем язык по умолчанию
    if (trim($acceptedLanguages) === '') {
        return $getSortedList ? [] : $protoLang;
    }
    preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})*)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $acceptedLanguages, $lang_parse);
    $langs = $lang_parse[1] ?? [];
    $ranks = $lang_parse[4] ?? [];
    if (empty($langs)) {
        return $getSortedList ? [] : $protoLang;
    }
    $lang2pref = array_combine($langs, array_map(fn($rank) => (float)($rank ?? 1), $ranks));
    uksort($lang2pref, fn($a, $b) => $lang2pref[$b] <=> $lang2pref[$a] ?: strlen($b) <=> strlen($a));
    return $getSortedList ? $lang2pref : (key($lang2pref) ?? strtolower($protoLang));
}

foreach (loadConfig() as $name => $val) {
    define($name, $val);
}

// Объединение серверных параметров
$input_data = file_get_contents('php://input');
define('__REQUEST', ['input_data' => $input_data, '_REQUEST' => $_REQUEST, '_GET' => $_GET, '_POST' => $_POST, '_SERVER' => $_SERVER]);

/**
 * Отловим фатальные ошибки
 */
register_shutdown_function(function () {
    if (ENV_FATAL_ERROR_LOGGING) {
        $error = error_get_last();
        if ($error && (in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR]))) {
            $formattedError = sprintf(
                    "Date: %s\nMessage: %s in %s on line %s\n\n",
                    date('d-m-Y H:i:s'),
                    $error['message'],
                    $error['file'],
                    $error['line']
            );
            file_put_contents(ENV_LOGS_PATH . 'fatal_errors.txt', $formattedError, FILE_APPEND);
        }
    }
});

/**
 * Перезаписать файл robots при отключенной индексации
 */
if (ENV_SITE_INDEX !== 'ALL') {
    file_put_contents(ENV_SITE_PATH . 'robots.txt', 'User-agent:* \n Disallow: /', LOCK_EX);
}