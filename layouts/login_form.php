<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
        <meta http-equiv="content-language" content="ru-RU">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
        <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no" name="viewport" />
        <meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE" />
        <meta name="robots" content="<?= ENV_SITE_INDEX ?>" /><!-- Индексация роботами -->
        <meta name="distribution" content="Global" /> 
        <meta name="rating" content="General" />
        <meta name="subject" content="<?= ENV_SITE_NAME ?>" /> <!-- Указывает тему страницы. По умолчанию берётся название сайта -->
        <meta name="page-type" content="Текст" /> <!-- Указывает тип страницы. Например, "Текст" или "Графика" -->
        <meta name="page-topic" content="<?= ENV_SITE_NAME ?>, <?= ENV_URL_SITE ?>" />
        <meta name="site-created" content="<?= ENV_DATE_SITE_CREATE ?>" /> <!-- Дата создания сайта-->
        <meta name="document-state" content="Dynamic">
        <meta name="page-type" content="Текст" />
        <meta name="generator" content="efimchenko.ru" /> <!-- Какой софт сгенерировал страницу-->
        <meta name="author" content = "<?= ENV_SITE_AUTHOR ?>" /> <!-- Автор сайта-->
        <meta name="reply-to" content = "<?= ENV_SITE_EMAIL ?>" /> <!-- Почта автора сайта-->
        <meta name="copyright" content="<?= ENV_SITE_AUTHOR ?>" /> 
        <meta name="address" content="<?= ENV_URL_SITE ?>" /> <!-- Указывает адрес автора или организации собственника страницы. -->
        <meta name="publisher-name" content="efimchenko.ru" /> <!-- Кто разместил сайт-->
        <meta name="publisher-type" content="Private" /> <!-- Тип владельца сайта "Private", "Company" -->
        <meta name="home-url" content="<?= ENV_URL_SITE ?>" />
        <meta name="keywords" content='<?= $keywords ?>'/>
        <meta name="description" content="<?= $description ?>"/>
        <!-- Мета теги соц. сетей -->
        <meta property="og:type" content="website" />
        <meta property="og:title" content="<?= $title ?>" />
        <meta property="og:description" content="<?= $description ?>"/>
        <meta property="og:image" content="<?= $image_social ?>"> <!-- URL-адрес изображения JPG, PNG и GIF min-size = 120х90 -->
        <meta property="og:url" content="<?= ENV_URL_SITE ?>">
        <meta name="twitter:card" content="summary">
        <meta name="twitter:site" content=""> <!-- аккаунт в Twitter -->
        <meta name="twitter:title" content="<?= $title ?>">
        <meta name="twitter:description" content="<?= $description ?>">
        <meta name="twitter:image" content="<?= $image_twiter ?>"> <!-- URL-адрес изображения JPG, PNG и GIF min-size = 120×120 -->
        <!-- Стили -->
        <link rel="apple-touch-icon" sizes="76x76" href="favicon.png">
        <link rel="icon" type="image/png" href="favicon.ico">
        <!-- Стандартные стили -->
        <!-- Bootstrap Min CSS -->
        <link rel="stylesheet" href="<?= ENV_URL_SITE ?>/assets/bootstrap/css/bootstrap.min.css" type="text/css">
        <!-- Font Awesome Min CSS -->
        <link rel="stylesheet" href="<?= ENV_URL_SITE ?>/assets/fontawesome/css/all.css" type="text/css"/>
        <link rel="canonical" href="<?= $canonical_href ?>" />
        <!-- Добавленные стили из контроллера -->
        <?= $add_style ?>
        <title><?= $title ?></title>
    </head>
    <body>
        <!-- Preloader -->
        <div id="preloader" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.9); z-index: 9999; display: flex; justify-content: center; align-items: center;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        <!-- Основной контент страниц -->
        <?= $layout_content ?>
        <!-- JS scripts -->
        <script src="<?= ENV_URL_SITE ?>/assets/js/plugins/jquery.min.js" type="text/javascript"></script>
        <script src="<?= ENV_URL_SITE ?>/assets/bootstrap/js/bootstrap.bundle.min.js" type="text/javascript"></script>
        <!-- core -->
        <script src="<?= ENV_URL_SITE ?>/classes/system/js/core.js" type="text/javascript"></script>	
        <!-- Добавленные скрипты из контроллера -->
        <?= $add_script ?>
        <!-- ported scripts -->		
    </body>
</html>