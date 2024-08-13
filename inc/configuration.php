<?php

/**
 * Конфигурационный файл настроек сайта
 * База данных, технические и индивидуальные настройки проекта.
 */

$cacheKey = 'config_cache';
$cacheTTL = 3600; // Время жизни кеша в секундах
$C = null;

// Попытка использования APCu
if (function_exists('apcu_exists')) {
    if (apcu_exists($cacheKey)) {
        $C = apcu_fetch($cacheKey);
    } else {
        $C = loadConfig();
        apcu_store($cacheKey, $C, $cacheTTL);
    }
} 

// Если APCu не доступен, попробуем Redis
elseif (class_exists('Redis')) {
    $redis = new Redis();
    if ($redis->connect('127.0.0.1', 6379)) {
        if ($redis->exists($cacheKey)) {
            $C = unserialize($redis->get($cacheKey));
        } else {
            $C = loadConfig();
            $redis->set($cacheKey, serialize($C), $cacheTTL);
        }
    }
}

// Если нет ни APCu, ни Redis, используем файл
if ($C === null) {
    $cacheFile = __DIR__ . '/config_cache.php';    
    if (file_exists($cacheFile) && time() - filemtime($cacheFile) < $cacheTTL) {        
        $C = unserialize(file_get_contents($cacheFile));        
    } else {
        $C = loadConfig();
        file_put_contents($cacheFile, serialize($C));
    }
}

function loadConfig() {
    $config = [];
    $config['ENV_VERSION_CORE'] = '4.0.0';
    /**
     * Настройка базы данных
     * Если указать начальные настройки при первом запуске формы авторизации то первичные таблицы в БД будут созданы автоматически
     */
    $config['ENV_DB_HOST'] = '';
    $config['ENV_DB_USER'] = '';
    $config['ENV_DB_PASS'] = '';
    $config['ENV_DB_NAME'] = '';
    $config['ENV_DB_PREF'] = NULL;

    /* Технические настройки сайта */
    $config['ENV_SITE_NAME'] = 'skku.shop'; // Название сайта
    $config['ENV_SITE_DESCRIPTION'] = 'skku.shop'; // Описание сайта
    $config['ENV_SITE_AUTHOR'] = 'efimchenko.ru'; // Автор сайта
    $config['ENV_DATE_SITE_CREATE'] = '13.02.2023'; // Дата создания сайта
    $config['ENV_DIRSEP'] = DIRECTORY_SEPARATOR;  // Разделитель операционной системы
    $config['ENV_DOMEN_PROTOCOL'] = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? "https://" : "http://"; // Протокол сайта
    $config['ENV_DOMEN_NAME'] = $_SERVER['SERVER_NAME']; // Домена сайта
    $config['ENV_URL_SITE'] = $config['ENV_DOMEN_PROTOCOL'] . $config['ENV_DOMEN_NAME']; // Домен с протоколом сайта
    $config['ENV_SITE_PATH'] = realpath(dirname(__FILE__) . $config['ENV_DIRSEP'] . '..' . $config['ENV_DIRSEP']) . $config['ENV_DIRSEP']; // Каталог сайта на сервере
    $config['ENV_LOG'] = 1;        // Логирование изменений в таблицу БД change_log
    $config['ENV_LOGS_PATH'] = $config['ENV_SITE_PATH'] . 'logs' . $config['ENV_DIRSEP'];
    $config['ENV_COMPRESS_HTML'] = 0;     // Сжимать HTML код 0-нет 1-Да
    $config['ENV_CACHE'] = 1;     // Использовать кеш 0-нет 1-Да
    $config['ENV_SECRET_KEY'] = '<{"u*{y$Pv.x@p{'; // Ключ защиты сайта(по умолчанию не используется)
    $config['ENV_SITE'] = 1;          // Логическая константа
    $config['ENV_TEST'] = 0;          // Режим работы сайта 1 - тест 0 - рабочий(если включен то выводит обрабатываемую информацию по скриптам, где это предусмотрено)
    $config['ENV_FATAL_ERROR_LOGGING'] = 1; // Запишет фатальные ошибки в корень сайта fatal_errors.txt register_shutdown_function
    $config['ENV_CONFIRM_EMAIL'] = 1;         // Требуется ли проверка почты зарегистрированным пользователям 0 - нет 1 - да
    $config['ENV_TIME_SESSION'] = 86400;    // Срок жизни сессии (86400 - сутки)
    $config['ENV_TIME_ACTIVATION'] = 86400 * 15;    // Срок жизни ссылки для активации аккаунта 15 суток
    $config['ENV_SITE_INDEX'] = 'noindex, nofollow';   // Индексация роботами noindex, nofollow - отключить; ALL - индексировать
    $config['ENV_EMAIL_TEMPLATE'] = $config['ENV_SITE_PATH'] . 'assets' . $config['ENV_DIRSEP'] . 'emails_templates';   // Папка для шаблонов писем
    $config['ENV_FONT_AWESOME_CDN'] = true;
    $config['ENV_BOOTSTRAP533_CDN'] = true;
	
    /* Персональные настройки сайта */
    $config['ENV_APP_DIRECTORY'] = 'app';    // Директория приложения
    $config['ENV_PATH_LANG'] = 'inc' . DIRECTORY_SEPARATOR . 'langs';    // Директория языковых файлов
    $config['ENV_PROTO_LANGUAGE'] = 'EN';
    $get_lang_code = strtoupper(substr(GetClientPreferedLanguage(), 0, 2));
    $config['ENV_DEF_LANG'] = $get_lang_code ? $get_lang_code : $config['ENV_PROTO_LANGUAGE'];    // Локализация по умолчанию, выбирает наиболее предпочитаемый язык пользователя или RU
    if ($config['ENV_DEF_LANG'] == 'RU') {
        date_default_timezone_set('Europe/Moscow');
    }
    
    $config['ENV_SITE_EMAIL'] = 'evgeniy@efimchenko.ru';   // Почта сайта ОБЯЗАТЕЛЬНОЕ ЗАПОЛНЕНИЕ
    $config['ENV_ADMIN_EMAIL'] = 'evgeniy@efimchenko.ru';  // Почта администратора сайта ОБЯЗАТЕЛЬНОЕ ЗАПОЛНЕНИЕ
    $config['ENV_SUPPORT_EMAIL'] = 'evgeniy@efimchenko.ru';  // Почта службы поддержки сайта
    $config['ENV_SMTP'] = 0;       // Метод отправки писем 0 - обычный 1 - SMTP(требуется настройка)

    $config['ENV_ONE_IP_ONE_USER'] = 0; // Только одна авторизация с одного IP адреса, не имеет смысла если ENV_AUTH_USER = 1
    $config['ENV_AUTH_USER'] = 0; // Метод хранения авторизации пользователя 0 - только в сессиях, 1 - сессии хранятся в БД(авторизация на двух устройствах невозможна), 2 - COOKIES

    /* Настройки почты для SMTP по умолчанию */
    $config['ENV_SMTP_PORT'] = 465;
    $config['ENV_SMTP_SERVER'] = '';
    $config['ENV_SMTP_LOGIN'] = '';
    $config['ENV_SMTP_PASSWORD'] = '';
    return $config;
}

/**
 * Получает предпочтительный язык клиента из заголовка HTTP_ACCEPT_LANGUAGE.
 * @param bool $getSortedList Если true, возвращает отсортированный список языков с их весами.
 * @param string|false $acceptedLanguages Строка заголовка 'Accept-Language'. Если false, используется $_SERVER['HTTP_ACCEPT_LANGUAGE'].
 * @return string|array Возвращает код предпочтительного языка или массив языков с их весами, если установлен $getSortedList.
 */
function GetClientPreferedLanguage($getSortedList = false, $acceptedLanguages = false) {
    session_start();
    if (isset($_SESSION['lang']) && !$getSortedList) {
        return $_SESSION['lang'];
    }
    if ($acceptedLanguages === false) {
        $acceptedLanguages = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
    }
    preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})*)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $acceptedLanguages, $lang_parse);
    $langs = $lang_parse[1];
    $ranks = $lang_parse[4];
    $lang2pref = [];
    foreach ($langs as $i => $lang) {
        $lang2pref[$lang] = (float) ($ranks[$i] ?? 1);
    }
    uksort($lang2pref, function ($a, $b) use ($lang2pref) {
        return $lang2pref[$b] <=> $lang2pref[$a] ?: strlen($b) <=> strlen($a);
    });
    return $getSortedList ? $lang2pref : key($lang2pref);
}

foreach ($C as $name => $val) {
    define($name, $val);
}

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
