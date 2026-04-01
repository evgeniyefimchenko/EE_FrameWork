<!DOCTYPE html>
<?php
$documentLangCode = ee_get_current_lang_code();
$documentLang = ee_get_lang_html_attr($documentLangCode);
$documentLocale = ee_get_lang_locale($documentLangCode);
$langBundleUrl = ee_get_lang_bundle_url($documentLangCode);
$currentCanonical = trim((string) ($canonical_href ?? ENV_URL_SITE));
$currentCanonical = $currentCanonical !== '' ? $currentCanonical : ENV_URL_SITE;
$alternateHreflang = is_array($alternate_hreflang ?? null) ? $alternate_hreflang : [];
?>
<html lang="<?= htmlspecialchars($documentLang, ENT_QUOTES, 'UTF-8') ?>">
    <head>
        <title><?= $title ?></title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
        <meta http-equiv="content-language" content="<?= htmlspecialchars($documentLocale, ENT_QUOTES, 'UTF-8') ?>">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
        <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no" name="viewport" />
        <meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE" />
        <meta name="robots" content="noindex, nofollow" /><!-- Индексация роботами TODO to ALL -->
        <meta name="distribution" content="Global" /> 
        <meta name="rating" content="General" />
        <meta name="subject" content="<?= ENV_SITE_NAME ?>" /> <!-- Указывает тему страницы. По умолчанию берётся название сайта -->
        <meta name="page-type" content="Текст" /> <!-- Указывает тип страницы. Например, "Текст" или "Графика" -->
        <meta name="page-topic" content="<?= ENV_SITE_NAME ?>, <?= ENV_URL_SITE ?>" />
        <meta name="site-created" content="01.09.2018" /> <!-- TODO Дата создания сайта-->
        <meta name="document-state" content="Dynamic">
        <meta name="page-type" content="Текст" />
        <meta name="generator" content="efimchenko.com" /> <!-- Какой софт сгенерировал страницу-->
        <meta name="author" content = "<?= ENV_SITE_AUTHOR ?>" /> <!-- Автор сайта-->
        <meta name="reply-to" content = "<?= ENV_SITE_EMAIL ?>" /> <!-- Почта автора сайта-->
        <meta name="copyright" content="<?= ENV_SITE_AUTHOR ?>" /> 
        <meta name="address" content="<?= ENV_URL_SITE ?>" /> <!-- Указывает адрес автора или организации собственника страницы. -->
        <meta name="publisher-name" content="<?= ENV_SITE_NAME ?>" /> <!-- Кто разместил сайт-->
        <meta name="publisher-type" content="Private" /> <!-- Тип владельца сайта "Private", "Company" -->
        <meta name="home-url" content="<?= ENV_URL_SITE ?>" />
        <meta name="keywords" content='<?= $keywords ?>'/>
        <meta name="description" content="<?= $description ?>"/>
        <meta name="image" content="<?= $imagePage ?>">
        <!-- Мета теги соц. сетей -->
        <meta property="og:type" content="website" />
        <meta property="og:title" content="<?= $title ?>" />
        <meta property="og:description" content="<?= $description ?>" />
        <meta property="og:image" content="<?= $imagePage ?>" />
        <meta property="og:image:width" content="1200" /> <!-- Ширина для Open Graph -->
        <meta property="og:image:height" content="630" />  <!-- Высота для Open Graph -->
        <meta property="og:url" content="<?= htmlspecialchars($currentCanonical, ENT_QUOTES, 'UTF-8') ?>">
        <meta name="twitter:site" content=""> <!-- аккаунт в Twitter -->
        <meta name="twitter:title" content="<?= $title ?>" />
        <meta name="twitter:description" content="<?= $description ?>" />
        <meta name="twitter:image" content="<?= $imagePage ?>" />
        <meta name="twitter:card" content="<?= $imagePage ?>" /> <!-- Для Twitter большого изображения -->
        <meta name="twitter:image:width" content="1200" /><!-- Ширина для Twitter -->
        <meta name="twitter:image:height" content="675" />  <!-- Высота для Twitter -->
        <!-- Стили -->
        <link rel="apple-touch-icon" sizes="76x76" href="<?= ENV_URL_SITE ?>/favicon.png" />
        <link rel="icon" type="image/png" href="<?= ENV_URL_SITE ?>/favicon.ico" />
        <!-- Стандартные стили-->
        <!-- Bootstrap Min CSS -->        
        <?php if (!ENV_BOOTSTRAP533_CDN) { ?>
            <link rel="stylesheet" href="<?= ENV_URL_SITE ?>/assets/bootstrap/css/bootstrap.min.css" type="text/css" />
        <?php } else { ?>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" type="text/css" />
        <?php } ?>
        <!-- Font Awesome Min CSS -->
        <?php if (!ENV_FONT_AWESOME_CDN) { ?>
            <link rel="stylesheet" href="<?= ENV_URL_SITE ?>/assets/fontawesome/css/all.css" type="text/css" />
        <?php } else { ?>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
        <?php } ?>
        <!-- General Styles -->
        <link rel="stylesheet" href="<?= ENV_URL_SITE . '/' . ENV_APP_DIRECTORY ?>/admin/css/index.css" type="text/css" />				
        <!-- canonical -->
        <link rel="canonical" href="<?= htmlspecialchars($currentCanonical, ENT_QUOTES, 'UTF-8') ?>" />
        <?php foreach ($alternateHreflang as $alternateLink): ?>
            <?php if (!is_array($alternateLink) || empty($alternateLink['hreflang']) || empty($alternateLink['href'])) { continue; } ?>
            <link rel="alternate" hreflang="<?= htmlspecialchars((string) $alternateLink['hreflang'], ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars((string) $alternateLink['href'], ENT_QUOTES, 'UTF-8') ?>" />
        <?php endforeach; ?>
        <!-- Добавленные стили из контроллера-->                
        <?= $add_style ?>
        <!-- END -->
    </head>
    <body class="sb-nav-fixed">
        <!-- Preloader -->
        <div id="preloader" class="preloader">
            <div class="pyramid-loader">
              <div class="wrapper">
                <span class="side side1"></span>
                <span class="side side2"></span>
                <span class="side side3"></span>
                <span class="side side4"></span>
                <span class="shadow"></span>
              </div>  
            </div>
        </div>
        <!-- Основной контент страниц-->
        <?= $layout_content ?>
        <!-- start of non-relocatable JS scripts -->
        <?php if (!ENV_JQUERY_CDN) { ?>
            <script src="<?= ENV_URL_SITE ?>/assets/js/plugins/jquery.min.js" type="text/javascript"></script>
        <?php } else { ?>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <?php } ?>
        <?php if (!ENV_BOOTSTRAP533_CDN) { ?>
            <script src="<?= ENV_URL_SITE ?>/assets/bootstrap/js/bootstrap.bundle.min.js" type="text/javascript"></script>
        <?php } else { ?>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" type="text/javascript"></script>
        <?php } ?>
        <script src="<?= ENV_URL_SITE ?>/assets/js/plugins/notify.js" type="text/javascript"></script>
        <!-- core -->
        <script src="<?= htmlspecialchars($langBundleUrl, ENT_QUOTES, 'UTF-8') ?>" type="text/javascript"></script>
        <script src="<?= ENV_URL_SITE ?>/assets/js/core.js" type="text/javascript"></script>
        <script src="<?= ENV_URL_SITE ?>/app/admin/js/dashboard.js" type="text/javascript"></script>

        <!-- end of non-relocatable JS scripts -->
        <!-- Добавленные скрипты из контроллера -->
        <?= $add_script ?>
        <!-- ported scripts -->
    </body>
</html>
