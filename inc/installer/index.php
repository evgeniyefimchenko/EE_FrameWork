<?php

require_once __DIR__ . '/InstallerService.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['ee_install_csrf'])) {
    $_SESSION['ee_install_csrf'] = bin2hex(random_bytes(24));
}

$installer = new EEInstallerService(dirname(__DIR__, 2));
$action = strtolower(trim((string) ($_GET['action'] ?? '')));

if ($action === 'status') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($installer->getStatus(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

if ($action === 'run' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $input = $_POST;
    unset($input['force']);

    try {
        $submittedToken = (string) ($input['_csrf'] ?? '');
        unset($input['_csrf']);
        if (!hash_equals((string) ($_SESSION['ee_install_csrf'] ?? ''), $submittedToken)) {
            throw new EEInstallException('Сессия установщика устарела. Обновите страницу и повторите запуск.');
        }
        $result = $installer->run($input);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    } catch (Throwable $e) {
        $installer->writeFailureState($e);
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    exit;
}

$status = $installer->getStatus();
$locked = !empty($status['locked']);
$requestHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost')));
$requestHost = preg_replace('~[/?#].*$~', '', $requestHost) ?? $requestHost;
$scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
if (in_array($forwardedProto, ['http', 'https'], true)) {
    $scheme = $forwardedProto;
}

$h = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$tip = static function (string $text) use ($h): string {
    $escaped = $h($text);
    return '<span class="help-tip" tabindex="0" title="' . $escaped . '" data-tooltip="' . $escaped . '">?</span>';
};
$csrfToken = (string) ($_SESSION['ee_install_csrf'] ?? '');

?><!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Установка EE_FrameWork</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f6f7f9;
            --panel: #ffffff;
            --text: #18212f;
            --muted: #64748b;
            --line: #d9e0e8;
            --line-strong: #b8c2cc;
            --accent: #2457c5;
            --accent-dark: #1d459d;
            --surface: #fbfcfe;
            --danger: #b42318;
            --ok: #157347;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font: 14px/1.5 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .page {
            width: min(1120px, calc(100% - 32px));
            margin: 28px auto 48px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: flex-start;
            padding-bottom: 18px;
            margin-bottom: 18px;
            border-bottom: 1px solid var(--line);
        }
        h1 {
            font-size: 28px;
            line-height: 1.15;
            margin: 0 0 8px;
        }
        h2 {
            font-size: 18px;
            margin: 0 0 14px;
        }
        p { margin: 0 0 12px; }
        .muted { color: var(--muted); }
        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 22px;
            margin-bottom: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 16px;
        }
        .full { grid-column: 1 / -1; }
        label {
            display: block;
            font-weight: 650;
            margin-bottom: 5px;
        }
        .label-row {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .help-tip {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 17px;
            height: 17px;
            border: 1px solid #9aa8b6;
            border-radius: 50%;
            color: #3c4a5c;
            background: #f7f9fb;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            cursor: help;
            user-select: none;
        }
        .help-tip::before,
        .help-tip::after {
            position: absolute;
            left: 50%;
            z-index: 20;
            opacity: 0;
            pointer-events: none;
            transform: translate(-50%, 4px);
            transition: opacity .12s ease, transform .12s ease;
        }
        .help-tip::before {
            content: attr(data-tooltip);
            bottom: calc(100% + 8px);
            width: max-content;
            max-width: min(320px, calc(100vw - 48px));
            padding: 7px 9px;
            border-radius: 5px;
            background: #1f2937;
            color: #fff;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .22);
            font-size: 12px;
            font-weight: 500;
            line-height: 1.35;
            text-align: left;
            white-space: normal;
        }
        .help-tip::after {
            content: "";
            bottom: calc(100% + 3px);
            border: 5px solid transparent;
            border-top-color: #1f2937;
        }
        .help-tip:hover::before,
        .help-tip:hover::after,
        .help-tip:focus::before,
        .help-tip:focus::after {
            opacity: 1;
            transform: translate(-50%, 0);
        }
        input, select {
            width: 100%;
            height: 40px;
            border: 1px solid #c8d2dc;
            border-radius: 6px;
            padding: 8px 10px;
            background: #fff;
            color: var(--text);
            font: inherit;
        }
        input:focus, select:focus {
            outline: 2px solid rgba(36, 87, 197, .18);
            border-color: var(--accent);
        }
        input[type="checkbox"] {
            width: auto;
            height: auto;
            margin-right: 8px;
        }
        .hint {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: 13px;
        }
        .actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 18px;
        }
        button {
            border: 0;
            border-radius: 6px;
            background: var(--accent);
            color: #fff;
            height: 42px;
            padding: 0 18px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        button:hover { background: var(--accent-dark); }
        button:disabled { opacity: .6; cursor: wait; }
        details {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 12px 14px;
            background: var(--surface);
        }
        summary {
            cursor: pointer;
            font-weight: 700;
        }
        .progress {
            height: 14px;
            border-radius: 999px;
            background: #dce4eb;
            overflow: hidden;
            margin: 10px 0 8px;
        }
        .progress span {
            display: block;
            height: 100%;
            width: 0;
            background: var(--accent);
            transition: width .2s ease;
        }
        .status-line {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            color: var(--muted);
            font-size: 14px;
        }
        .steps {
            margin: 12px 0 0;
            padding-left: 18px;
            color: var(--muted);
            max-height: 180px;
            overflow: auto;
        }
        .notice {
            border-left: 4px solid var(--accent);
            padding: 10px 12px;
            background: #eefaf8;
            border-radius: 4px;
        }
        .error {
            border-left-color: var(--danger);
            background: #fff1f0;
            color: var(--danger);
        }
        .ok {
            border-left-color: var(--ok);
            background: #effaf3;
            color: var(--ok);
        }
        code {
            background: #eef2f6;
            border-radius: 4px;
            padding: 2px 5px;
        }
        .switch-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 2px;
        }
        .switch {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            min-height: 52px;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface);
            font-weight: 600;
        }
        .switch input {
            flex: 0 0 auto;
            margin-top: 3px;
        }
        .switch span {
            display: block;
        }
        .switch small {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-weight: 400;
        }
        .section-heading {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 16px;
            margin: 0 0 14px;
        }
        .section-heading h2 {
            margin: 0;
        }
        .section-heading span {
            color: var(--muted);
            font-size: 13px;
        }
        @media (max-width: 760px) {
            .grid { grid-template-columns: 1fr; }
            .switch-grid { grid-template-columns: 1fr; }
            .header { display: block; }
        }
    </style>
</head>
<body>
<main class="page">
    <div class="header">
        <div>
            <h1>Установка EE_FrameWork</h1>
            <p class="muted">Мастер создаёт конфиг, проверяет БД, разворачивает таблицы, пишет lock-файл и подсказывает cron.</p>
        </div>
        <div class="muted">PHP <?= $h(PHP_VERSION) ?></div>
    </div>

    <?php if ($locked) { ?>
        <section class="panel">
            <h2>Установщик закрыт</h2>
            <div class="notice ok">
                <?= $h($status['message'] ?? 'Проект уже установлен.') ?>
            </div>
            <p class="muted" style="margin-top: 12px;">
                Повторный запуск через web-интерфейс заблокирован. Для осознанной переустановки используйте CLI с флагами
                <code>--force</code> и <code>--overwrite-config</code>.
            </p>
            <p class="muted">
                Lock: <code><?= $h($status['paths']['lock'] ?? '') ?></code>
            </p>
        </section>
    <?php } else { ?>
        <section class="panel">
            <div class="section-heading">
                <h2>Параметры сайта</h2>
                <span>Записываются в ENV_* конфигурацию проекта</span>
            </div>
            <form id="install-form" method="post">
                <input type="hidden" name="_csrf" value="<?= $h($csrfToken) ?>">
                <div class="grid">
                    <div>
                        <label class="label-row" for="site_host">Домен <?= $tip('Записывается в ENV_CANONICAL_HOST. Используется для канонического URL сайта, ссылок, редиректов и cookie-домена.') ?></label>
                        <input id="site_host" name="site_host" value="<?= $h($requestHost) ?>" required>
                        <span class="hint">Без протокола, например example.com.</span>
                    </div>
                    <div>
                        <label class="label-row" for="canonical_scheme">Протокол <?= $tip('Записывается в ENV_CANONICAL_SCHEME. Определяет базовый http/https URL, канонические ссылки и secure-режим auth cookie.') ?></label>
                        <select id="canonical_scheme" name="canonical_scheme">
                            <option value="https"<?= $scheme === 'https' ? ' selected' : '' ?>>https</option>
                            <option value="http"<?= $scheme === 'http' ? ' selected' : '' ?>>http</option>
                        </select>
                    </div>
                    <div>
                        <label class="label-row" for="site_name">Название сайта <?= $tip('Записывается в ENV_SITE_NAME. Показывается в layout, meta title, админке и системных уведомлениях.') ?></label>
                        <input id="site_name" name="site_name" value="<?= $h($requestHost) ?>" required>
                    </div>
                    <div>
                        <label class="label-row" for="site_description">Описание <?= $tip('Записывается в ENV_SITE_DESCRIPTION. Используется как краткое описание проекта в meta-тегах и базовых шаблонах.') ?></label>
                        <input id="site_description" name="site_description" value="<?= $h($requestHost) ?>">
                    </div>
                    <div>
                        <label class="label-row" for="site_author">Владелец и автор <?= $tip('Записывается в ENV_SITE_AUTHOR и юридические настройки по умолчанию. Используется в meta author, документах и служебной информации сайта.') ?></label>
                        <input id="site_author" name="site_author" required>
                    </div>
                    <div>
                        <label class="label-row" for="site_email">Почта сайта <?= $tip('Записывается в ENV_SITE_EMAIL. Используется как публичный контакт и адрес отправителя там, где проекту нужен общий email сайта.') ?></label>
                        <input id="site_email" name="site_email" type="email" required>
                    </div>
                    <div>
                        <label class="label-row" for="admin_email">Почта администратора <?= $tip('Записывается в ENV_ADMIN_EMAIL. Используется для bootstrap-администратора, системных уведомлений и технических сообщений.') ?></label>
                        <input id="admin_email" name="admin_email" type="email" required>
                    </div>
                    <div>
                        <label class="label-row" for="support_email">Почта поддержки/модератора <?= $tip('Записывается в ENV_SUPPORT_EMAIL. Используется для bootstrap-модератора и пользовательских обращений; если пусто, берётся почта администратора.') ?></label>
                        <input id="support_email" name="support_email" type="email">
                    </div>
                    <div>
                        <label class="label-row" for="proto_language">ENV_PROTO_LANGUAGE <?= $tip('Базовый язык прототипа и fallback для системных данных. Влияет на исходные языковые значения, если контент ещё не локализован.') ?></label>
                        <select id="proto_language" name="proto_language">
                            <option value="EN" selected>EN</option>
                            <option value="RU">RU</option>
                        </select>
                        <span class="hint">Базовый язык прототипа и fallback для интерфейсных данных.</span>
                    </div>
                    <div>
                        <label class="label-row" for="content_langs">ENV_CONTENT_LANGS <?= $tip('Список языков контента проекта. Используется контентной моделью, мультиязычными полями, админкой и публичными страницами.') ?></label>
                        <input id="content_langs" name="content_langs" value="RU" required>
                        <span class="hint">Список языков контента через запятую: RU,EN.</span>
                    </div>
                    <div class="full">
                        <label class="label-row">Подключение ассетов публичного проекта <?= $tip('Эти флаги управляют только публичным проектом после установки. Сам установщик не использует CDN и остаётся автономным.') ?></label>
                        <div class="switch-grid">
                            <input type="hidden" name="bootstrap533_cdn" value="0">
                            <label class="switch">
                                <input type="checkbox" name="bootstrap533_cdn" value="1" checked>
                                <span>ENV_BOOTSTRAP533_CDN <?= $tip('Разрешает шаблонам проекта подключать Bootstrap 5.3.3 через CDN, если layout это поддерживает.') ?><small>Разрешить Bootstrap 5.3.3 через CDN для публичного проекта.</small></span>
                            </label>
                            <input type="hidden" name="font_awesome_cdn" value="0">
                            <label class="switch">
                                <input type="checkbox" name="font_awesome_cdn" value="1" checked>
                                <span>ENV_FONT_AWESOME_CDN <?= $tip('Разрешает шаблонам проекта подключать Font Awesome через CDN для иконок интерфейса.') ?><small>Разрешить Font Awesome через CDN для публичного проекта.</small></span>
                            </label>
                        </div>
                        <span class="hint">Сам установщик не подключает внешние библиотеки и работает на собственном CSS/JS.</span>
                    </div>
                </div>

                <section style="margin-top: 18px;">
                    <h2>База данных</h2>
                    <div class="grid">
                        <div>
                            <label class="label-row" for="db_host">Хост БД <?= $tip('Записывается в ENV_DB_HOST. Обычно localhost, если MySQL/MariaDB работает на этом же сервере.') ?></label>
                            <input id="db_host" name="db_host" value="localhost" required>
                        </div>
                        <div>
                            <label class="label-row" for="db_name">Имя БД <?= $tip('Записывается в ENV_DB_NAME. В этой базе установщик создаст таблицы платформы и стартовые данные.') ?></label>
                            <input id="db_name" name="db_name" required>
                        </div>
                        <div>
                            <label class="label-row" for="db_user">Пользователь БД <?= $tip('Записывается в ENV_DB_USER. От имени этого пользователя приложение будет читать и менять данные после установки.') ?></label>
                            <input id="db_user" name="db_user" required>
                        </div>
                        <div>
                            <label class="label-row" for="db_pass">Пароль БД <?= $tip('Записывается в ENV_DB_PASS. Нужен приложению для подключения к базе; не выводится публично и не должен попадать в Git.') ?></label>
                            <input id="db_pass" name="db_pass" type="password" autocomplete="new-password">
                        </div>
                        <div>
                            <label class="label-row" for="db_prefix">Префикс таблиц <?= $tip('Записывается в ENV_DB_PREF. Добавляется к именам таблиц, чтобы несколько установок могли жить в одной БД без конфликтов.') ?></label>
                            <input id="db_prefix" name="db_prefix" value="ee_" required>
                        </div>
                    </div>
                </section>

                <section style="margin-top: 18px;">
                    <details>
                        <summary class="label-row">Создать БД/пользователя через административный доступ <?= $tip('Опциональный режим: установщик временно использует admin-доступ MySQL/MariaDB, чтобы создать базу, пользователя и выдать права.') ?></summary>
                        <div class="grid" style="margin-top: 14px;">
                            <div class="full">
                                <label class="label-row"><input type="checkbox" name="create_database" value="1"> Создать БД, если её нет <?= $tip('Если база с указанным именем отсутствует, установщик создаст её перед развёртыванием таблиц.') ?></label>
                                <label class="label-row"><input type="checkbox" name="create_user" value="1"> Создать пользователя и выдать права <?= $tip('Если пользователь отсутствует, установщик создаст его и выдаст права на выбранную базу.') ?></label>
                            </div>
                            <div>
                                <label class="label-row" for="db_admin_user">DB admin user <?= $tip('Административный пользователь MySQL/MariaDB. Используется только во время установки и не записывается в configuration.php.') ?></label>
                                <input id="db_admin_user" name="db_admin_user" autocomplete="off">
                            </div>
                            <div>
                                <label class="label-row" for="db_admin_pass">DB admin password <?= $tip('Пароль административного пользователя БД. Используется только для создания БД/пользователя и не сохраняется в конфиг проекта.') ?></label>
                                <input id="db_admin_pass" name="db_admin_pass" type="password" autocomplete="new-password">
                            </div>
                            <div>
                                <label class="label-row" for="db_user_host">Host для DB user <?= $tip('Host-часть MySQL-пользователя, например localhost или %. Определяет, откуда разрешено подключаться приложению.') ?></label>
                                <input id="db_user_host" name="db_user_host" value="localhost">
                            </div>
                        </div>
                    </details>
                </section>

                <section style="margin-top: 18px;">
                    <details>
                        <summary class="label-row">Пароли bootstrap-пользователей <?= $tip('Стартовые учётные записи создаются при установке, чтобы сразу войти в админку и продолжить настройку проекта.') ?></summary>
                        <div class="grid" style="margin-top: 14px;">
                            <div>
                                <label class="label-row" for="admin_password">Пароль администратора <?= $tip('Используется для стартового администратора. Если оставить пустым, установщик сгенерирует пароль и покажет его один раз.') ?></label>
                                <input id="admin_password" name="admin_password" type="password" autocomplete="new-password">
                                <span class="hint">Если оставить пустым, установщик сгенерирует пароль и покажет его один раз.</span>
                            </div>
                            <div>
                                <label class="label-row" for="moderator_password">Пароль модератора <?= $tip('Используется для стартового модератора. Если оставить пустым, пароль будет сгенерирован и показан один раз после установки.') ?></label>
                                <input id="moderator_password" name="moderator_password" type="password" autocomplete="new-password">
                            </div>
                        </div>
                    </details>
                </section>

                <?php if (!empty($status['config_exists'])) { ?>
                    <section style="margin-top: 18px;">
                        <label class="label-row"><input type="checkbox" name="overwrite_config" value="1"> Перезаписать существующий inc/configuration.php с backup <?= $tip('Нужно только при повторной CLI/web-подготовке окружения. Перед заменой установщик создаёт backup текущего configuration.php.') ?></label>
                    </section>
                <?php } ?>

                <div class="actions">
                    <button type="submit" id="install-submit">Запустить установку</button>
                    <span class="muted">После завершения мастер будет заблокирован.</span>
                </div>
            </form>
        </section>
    <?php } ?>

    <section class="panel">
        <h2>Прогресс</h2>
        <div class="progress"><span id="progress-bar"></span></div>
        <div class="status-line">
            <span id="progress-message"><?= $h($status['state']['message'] ?? 'Ожидание запуска') ?></span>
            <span id="progress-percent"><?= (int) ($status['state']['percent'] ?? 0) ?>%</span>
        </div>
        <ol class="steps" id="progress-steps"></ol>
        <div id="install-result" style="margin-top: 14px;"></div>
    </section>

    <section class="panel">
        <h2>CLI</h2>
        <p class="muted">Та же установка доступна из консоли:</p>
        <p><code>php inc/cli.php install:run --site-host=example.com --site-author="Имя" --site-email=mail@example.com --admin-email=mail@example.com --db-name=example --db-user=example --db-pass=secret</code></p>
        <p><code>php inc/cli.php install:status</code></p>
    </section>
</main>

<script>
const form = document.getElementById('install-form');
const button = document.getElementById('install-submit');
const bar = document.getElementById('progress-bar');
const percent = document.getElementById('progress-percent');
const message = document.getElementById('progress-message');
const steps = document.getElementById('progress-steps');
const result = document.getElementById('install-result');
let pollTimer = null;

function setState(state) {
    const pct = Number(state.percent || 0);
    bar.style.width = pct + '%';
    percent.textContent = pct + '%';
    message.textContent = state.message || '';
    steps.innerHTML = '';
    (state.history || []).slice(-12).forEach(item => {
        const li = document.createElement('li');
        li.textContent = `[${item.percent}%] ${item.message}`;
        steps.appendChild(li);
    });
}

async function pollStatus() {
    const response = await fetch('/install/?action=status', {headers: {'Accept': 'application/json'}});
    const payload = await response.json();
    setState(payload.state || {});
    if (!payload.state || payload.state.running !== true) {
        clearInterval(pollTimer);
        pollTimer = null;
        if (button) button.disabled = false;
    }
}

if (form) {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        result.innerHTML = '';
        button.disabled = true;
        pollTimer = setInterval(pollStatus, 700);
        try {
            const response = await fetch('/install/?action=run', {
                method: 'POST',
                body: new FormData(form),
                headers: {'Accept': 'application/json'}
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Установка не завершена.');
            }
            let html = `<div class="notice ok"><strong>${payload.message}</strong><br>Сайт: ${payload.site_url}</div>`;
            const creds = payload.generated_credentials || {};
            if (creds.admin_password_generated || creds.moderator_password_generated) {
                html += '<div class="notice" style="margin-top: 12px;"><strong>Сгенерированные пароли показываются один раз.</strong>';
                if (creds.admin_password_generated) html += `<br>admin: <code>${creds.admin_password}</code>`;
                if (creds.moderator_password_generated) html += `<br>moderator: <code>${creds.moderator_password}</code>`;
                html += '</div>';
            }
            html += `<p class="muted" style="margin-top: 12px;">Cron: <code>${payload.cron.recommended_line}</code></p>`;
            result.innerHTML = html;
        } catch (error) {
            result.innerHTML = `<div class="notice error">${error.message}</div>`;
        } finally {
            await pollStatus();
            if (button) button.disabled = false;
        }
    });
}

pollStatus().catch(() => {});
</script>
</body>
</html>
