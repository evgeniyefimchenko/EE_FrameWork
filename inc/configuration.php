<?php
/**
 * Конфигурационный файл настроек сайта
 * База данных, технические и индивидуальные настройки проекта.
 */
$C = [];
$C['ENV_VERSION_CORE'] = '3.0.0';
/**
* Настройка базы данных
* Если указать начальные настройки при первом запуске формы авторизации то первичные таблицы в БД будут созданы автоматически
*/
$C['ENV_DB_HOST'] = '';
$C['ENV_DB_USER'] = '';
$C['ENV_DB_PASS'] = '';
$C['ENV_DB_NAME'] = '';
$C['ENV_DB_PREF'] = NULL;

/* Технические настройки сайта */
$C['ENV_SITE_NAME'] = ''; // Название сайта
$C['ENV_SITE_DESCRIPTION'] = ''; // Описание сайта
$C['ENV_SITE_AUTHOR'] = 'efimchenko.com'; // Автор сайта
$C['ENV_DATE_SITE_CREATE'] = ''; // Дата создания сайта
$C['ENV_DIRSEP'] = DIRECTORY_SEPARATOR;  // Разделитель операционной системы
$C['ENV_DOMEN_PROTOCOL'] = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? "https://" : "http://"; // Протокол сайта
$C['ENV_DOMEN_NAME'] = $_SERVER['SERVER_NAME']; // Домена сайта
$C['ENV_URL_SITE'] = $C['ENV_DOMEN_PROTOCOL'] . $C['ENV_DOMEN_NAME']; // Домен с протоколом сайта
$C['ENV_SITE_PATH'] = realpath(dirname(__FILE__) . $C['ENV_DIRSEP'] . '..' . $C['ENV_DIRSEP']) . $C['ENV_DIRSEP']; // Каталог сайта на сервере
$C['ENV_LOG'] = 1;        // Логирование изменений в таблицу БД change_log
$C['ENV_LOGS_PATH'] = $C['ENV_SITE_PATH'] . 'logs' . $C['ENV_DIRSEP'];
$C['ENV_COMPRESS_HTML'] = 0;     // Сжимать HTML код 0-нет 1-Да
$C['ENV_CACHE'] = 1;     // Использовать кеш 0-нет 1-Да
$C['ENV_SECRET_KEY'] = '<{"u*{y$Pv.x@p{'; // Ключ защиты сайта(по умолчанию не используется)
$C['ENV_SITE'] = 1;					     // Логическая константа
$C['ENV_TEST'] = 0;      				// Режим работы сайта 1 - тест 0 - рабочий(если включен то выводит обрабатываемую информацию по скриптам, где это предусмотрено)
$C['ENV_FATAL_ERROR_LOGGING'] = 1; // Запишет фатальные ошибки в корень сайта fatal_errors.txt register_shutdown_function
$C['ENV_CONFIRM_EMAIL'] = 1;      			// Требуется ли проверка почты зарегистрированным пользователям 0 - нет 1 - да
$C['ENV_TIME_SESSION'] = 86400;    // Срок жизни сессии (86400 - сутки)
$C['ENV_TIME_ACTIVATION'] = 86400 * 15;    // Срок жизни ссылки для активации аккаунта 15 суток
$C['ENV_SITE_INDEX'] = 'noindex, nofollow';   // Индексация роботами noindex, nofollow - отключить; ALL - индексировать
$C['ENV_EMAIL_TEMPLATE'] = $C['ENV_SITE_PATH'] . 'assets' . $C['ENV_DIRSEP'] . 'emails_templates';   // Папка для шаблонов писем

/* Персональные настройки сайта */
$C['ENV_APP_DIRECTORY'] = 'app';    // Директория приложения
$C['ENV_PATH_LANG'] = 'inc' . DIRECTORY_SEPARATOR . 'langs';    // Директория языковых файлов
$get_lang_code = substr(Get_Client_Prefered_Language(), 0, 2);
$C['ENV_DEF_LANG'] = $get_lang_code ? strtoupper($get_lang_code) : 'RU';    // Локализация по умолчанию,выбирает наиболее предпочитаемый язык пользователя или en
$C['ENV_SITE_EMAIL'] = '';   // Почта сайта ОБЯЗАТЕЛЬНОЕ ЗАПОЛНЕНИЕ
$C['ENV_ADMIN_EMAIL'] = '';  // Почта администратора сайта ОБЯЗАТЕЛЬНОЕ ЗАПОЛНЕНИЕ
$C['ENV_SUPPORT_EMAIL'] = '';  // Почта службы поддержки сайта
$C['ENV_SMTP'] = 0;       // Метод отправки писем 0 - обычный 1 - SMTP(требуется настройка)

$C['ENV_ONE_IP_ONE_USER'] = 0; // Только одна авторизация с одного IP адреса, не имеет смысла если ENV_AUTH_USER = 1
$C['ENV_AUTH_USER'] = 0; // Метод хранения авторизации пользователя 0 - только в сессиях, 1 - сессии хранятся в БД(авторизация на двух устройствах невозможна), 2 - COOKIES

/* Настройки почты для SMTP по умолчанию */
$C['ENV_SMTP_PORT'] = 465;
$C['ENV_SMTP_SERVER'] = '';
$C['ENV_SMTP_LOGIN'] = '';
$C['ENV_SMTP_PASSWORD'] = '';

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
            file_put_contents(ENV_LOGS_PATH . 'fatal_errors.txt', date('d-m-Y h:i:s') . PHP_EOL . var_export($error, true), FILE_APPEND);
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
 * Получает предпочтительный язык клиента из заголовка HTTP_ACCEPT_LANGUAGE.
 * @param bool $getSortedList Если true, возвращает отсортированный список языков с их весами.
 * @param string|false $acceptedLanguages Строка заголовка 'Accept-Language'. Если false, используется $_SERVER['HTTP_ACCEPT_LANGUAGE'].
 * @return string|array Возвращает код предпочтительного языка или массив языков с их весами, если установлен $getSortedList.
 */
function Get_Client_Prefered_Language($getSortedList = false, $acceptedLanguages = false) {
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


