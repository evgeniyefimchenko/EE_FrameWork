<?php

/**
 * Евгений Ефимченко, efimchenko.com
 * Обработчик страницы ошибок, перенаправляет пользователей на главную страницу сайта с сообщением.
 * /error.php
 */
use classes\system\Session;

if (!defined('ENV_SITE') || !ENV_SITE) {
    http_response_code(404);
    die;
}

$statusLine = (string) (Session::get('code') ?: '');
$statusCode = 0;
$statusCodeFromSession = false;
if (preg_match('/^(\d{3})\b/', $statusLine, $matches)) {
    $statusCode = (int) $matches[1];
    $statusCodeFromSession = true;
}

if ($statusCode < 400 || $statusCode > 599) {
    $statusCode = http_response_code();
    if ($statusCode < 400 || $statusCode > 599) {
        $statusCode = 404;
    }
}

$statusText = match ($statusCode) {
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not Found',
    500 => 'Internal Server Error',
    503 => 'Service Unavailable',
    default => 'Error',
};
$statusLine = $statusCode . ' ' . $statusText;
if (!$statusCodeFromSession && !headers_sent()) {
    http_response_code($statusCode);
}

$pageTitle = match ($statusCode) {
    404 => 'Страница не найдена',
    503 => 'Сервис временно недоступен',
    500 => 'Внутренняя ошибка сервера',
    default => 'Ошибка запроса',
};
$pageMessage = match ($statusCode) {
    404 => 'Запрошенная страница не найдена или была перемещена.',
    503 => 'Сервис временно недоступен. Попробуйте обновить страницу позже.',
    500 => 'На сервере произошла ошибка. Подробности сохранены в журнале.',
    default => 'Не удалось обработать запрос.',
};
$showAutoRefresh = in_array($statusCode, [404], true);
?>
<!DOCTYPE html>
<html lang="<?= defined('ENV_DEF_LANG') ? strtolower((string) ENV_DEF_LANG) : 'ru' ?>">
    <head>
        <title><?= htmlspecialchars($statusLine, ENT_QUOTES, 'UTF-8') ?></title>
        <meta charset="utf-8">
        <?php if ($showAutoRefresh): ?>
        <meta http-equiv="refresh" content="5;<?= htmlspecialchars((string) ENV_URL_SITE, ENT_QUOTES, 'UTF-8') ?>">
        <?php endif; ?>
        <meta name="viewport" content="width=device-width, initial-scale=0.5">
        <link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
        <style type="text/css">
            html, body {
                width:100%;
                height:100%;
                overflow:hidden;
                margin:0px;
                padding:0px;
                font-family:'Open Sans',sans-serif;
                font-size:16px
            }
            body {
                background:#fff;
                color:#111;
            }
            .content {
                width:min(720px, calc(100% - 32px));
                text-align:center;
                position:absolute;
                top:50%;
                left:50%;
                transform:translate(-50%, -50%);
            }
            .status-code {
                font-size:72px;
                font-weight:700;
                line-height:1;
                margin:0 0 20px;
            }
            .status-title {
                font-size:28px;
                font-weight:700;
                margin:0 0 12px;
            }
            .status-message {
                font-size:18px;
                line-height:1.5;
                margin:0 0 24px;
            }
            .content a {
                display:inline-block;
                text-decoration:none;
                border:1px solid #111;
                border-radius:4px;
                padding:10px 16px;
            }
            .content a:hover {
                opacity:0.7
            }
            .content a, .content a:hover {
                color:#000;
            }
            @media only screen and (max-width: 460px), screen and (max-height: 700px) {
                .content {
                    position:absolute;
                }
                .status-code {
                    font-size:48px;
                }
            }
        </style>
    </head>
    <body>
        <div class="content">
            <div class="status-code"><?= (int) $statusCode ?></div>
            <h1 class="status-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="status-message"><?= htmlspecialchars($pageMessage, ENT_QUOTES, 'UTF-8') ?></p>
            <a href="/">На главную страницу</a>
        </div>
    </body>
</html>
