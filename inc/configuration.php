<?php
/**
 * Конфигурационный файл настроек сайта
 * База данных, технические и индивидуальные настройки проекта.
 * @author Evgeniy Efimchenko efimchenko.ru
 */
$C = array();
$C['ENV_VERSION_CORE'] = '1.0.2';
/**
* Настройка базы данных
* Если указать начальные настройки при первом запуске то первичные таблицы в БД будут созданы автоматически
*/
$C['ENV_DB_HOST'] = '';
$C['ENV_DB_USER'] = '';
$C['ENV_DB_PASS'] = '';
$C['ENV_DB_NAME'] = '';
$C['ENV_DB_PREF'] = NULL;

/* Технические настройки сайта */
$C['ENV_SITE_NAME'] = 'EE_FrameWork v.' . $C['ENV_VERSION_CORE']; // Название сайта
$C['ENV_SITE_DESCRIPTION'] = 'EE_FRAMEFORK ' . $C['ENV_VERSION_CORE'] . ' - Лёгкий PHP MVC фреймворк'; // Описание сайта
$C['ENV_SITE_AUTHOR'] = 'Евгений Владимирович Ефимченко'; // Автор сайта
$C['ENV_DATE_SITE_CREATE'] = '01.09.2018'; // Дата создания сайта
$C['ENV_DIRSEP'] = DIRECTORY_SEPARATOR;  // Разделитель операционной системы
$C['ENV_DOMEN_PROTOCOL'] = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? "https://" : "http://"; // Протокол сайта
$C['ENV_DOMEN_NAME'] = $_SERVER['SERVER_NAME']; // Домена сайта
$C['ENV_URL_SITE'] = $C['ENV_DOMEN_PROTOCOL'] . $C['ENV_DOMEN_NAME']; // Домен с протоколом сайта
$C['ENV_SITE_PATH'] = realpath(dirname(__FILE__) . $C['ENV_DIRSEP'] . '..' . $C['ENV_DIRSEP']) . $C['ENV_DIRSEP']; // Каталог сайта на сервере
$C['ENV_LOG'] = 1;        // Логирование изменений в таблицу БД change_log
$C['ENV_COMPRESS_HTML'] = 0;     // Сжимать HTML код 0-нет 1-Да
$C['ENV_SECRET_KEY'] = '<{"u*{y$Pv.x@p{'; // Ключ защиты сайта(по умолчанию не используется)
$C['ENV_SITE'] = 1;					     // Логическая константа
$C['ENV_TEST'] = 0;      				// Режим работы сайта 1 - тест 0 - рабочий(если включен то выводит обрабатываемую информацию по скриптам, где это предусмотрено)
$C['ENV_CONFIRM_EMAIL'] = 1;      			// Требуется ли проверка почты зарегистрированным пользователям 0 - нет 1 - да
$C['ENV_TIME_SESSION'] = 86400;    // Срок жизни сессии (86400 - сутки)
$C['ENV_TIME_ACTIVATION'] = 86400 * 15;    // Срок жизни ссылки для активации аккаунта 15 суток
$C['ENV_SITE_INDEX'] = 'noindex, nofollow';   // Индексация роботами noindex, nofollow - отключить; ALL - индексировать
$C['ENV_GEO_RU'] = 0;					     // Устанавливать ли GEO_RU при развёртывании БД, по умолчанию отключено

/* Персональные настройки сайта */
$C['ENV_APP_DIRECTORY'] = 'app';    // Директория контроллеров
$C['ENV_SITE_EMAIL'] = 'mail@site.ru';   // Почта сайта ОБЯЗАТЕЛЬНОЕ ЗАПОЛНЕНИЕ
$C['ENV_ADMIN_EMAIL'] = 'mail_admin@site.ru';  // Почта администратора сайта ОБЯЗАТЕЛЬНОЕ ЗАПОЛНЕНИЕ
$C['ENV_SUPPORT_EMAIL'] = '';  // Почта службы поддержки сайта
$C['ENV_SMTP'] = 0;       // Метод отправки писем 0 - обычный 1 - SMTP(требуется настройка)

/* Настройки почты для SMTP по умолчанию */
$C['ENV_SMTP_PORT'] = 25;
$C['ENV_SMTP_SERVER'] = 0;
$C['ENV_SMTP_LOGIN'] = 0;
$C['ENV_SMTP_PASSWORD'] = 0;
