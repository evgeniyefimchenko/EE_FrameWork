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
                        <label for="site_host">Домен</label>
                        <input id="site_host" name="site_host" value="<?= $h($requestHost) ?>" required>
                        <span class="hint">Без протокола, например example.com.</span>
                    </div>
                    <div>
                        <label for="canonical_scheme">Протокол</label>
                        <select id="canonical_scheme" name="canonical_scheme">
                            <option value="https"<?= $scheme === 'https' ? ' selected' : '' ?>>https</option>
                            <option value="http"<?= $scheme === 'http' ? ' selected' : '' ?>>http</option>
                        </select>
                    </div>
                    <div>
                        <label for="site_name">Название сайта</label>
                        <input id="site_name" name="site_name" value="<?= $h($requestHost) ?>" required>
                    </div>
                    <div>
                        <label for="site_description">Описание</label>
                        <input id="site_description" name="site_description" value="<?= $h($requestHost) ?>">
                    </div>
                    <div>
                        <label for="site_author">Владелец и автор</label>
                        <input id="site_author" name="site_author" required>
                    </div>
                    <div>
                        <label for="site_email">Почта сайта</label>
                        <input id="site_email" name="site_email" type="email" required>
                    </div>
                    <div>
                        <label for="admin_email">Почта администратора</label>
                        <input id="admin_email" name="admin_email" type="email" required>
                    </div>
                    <div>
                        <label for="support_email">Почта поддержки/модератора</label>
                        <input id="support_email" name="support_email" type="email">
                    </div>
                    <div>
                        <label for="proto_language">ENV_PROTO_LANGUAGE</label>
                        <select id="proto_language" name="proto_language">
                            <option value="EN" selected>EN</option>
                            <option value="RU">RU</option>
                        </select>
                        <span class="hint">Базовый язык прототипа и fallback для интерфейсных данных.</span>
                    </div>
                    <div>
                        <label for="content_langs">ENV_CONTENT_LANGS</label>
                        <input id="content_langs" name="content_langs" value="RU" required>
                        <span class="hint">Список языков контента через запятую: RU,EN.</span>
                    </div>
                    <div class="full">
                        <label>Подключение ассетов публичного проекта</label>
                        <div class="switch-grid">
                            <input type="hidden" name="bootstrap533_cdn" value="0">
                            <label class="switch">
                                <input type="checkbox" name="bootstrap533_cdn" value="1" checked>
                                <span>ENV_BOOTSTRAP533_CDN<small>Разрешить Bootstrap 5.3.3 через CDN для публичного проекта.</small></span>
                            </label>
                            <input type="hidden" name="font_awesome_cdn" value="0">
                            <label class="switch">
                                <input type="checkbox" name="font_awesome_cdn" value="1" checked>
                                <span>ENV_FONT_AWESOME_CDN<small>Разрешить Font Awesome через CDN для публичного проекта.</small></span>
                            </label>
                        </div>
                        <span class="hint">Сам установщик не подключает внешние библиотеки и работает на собственном CSS/JS.</span>
                    </div>
                </div>

                <section style="margin-top: 18px;">
                    <h2>База данных</h2>
                    <div class="grid">
                        <div>
                            <label for="db_host">Хост БД</label>
                            <input id="db_host" name="db_host" value="localhost" required>
                        </div>
                        <div>
                            <label for="db_name">Имя БД</label>
                            <input id="db_name" name="db_name" required>
                        </div>
                        <div>
                            <label for="db_user">Пользователь БД</label>
                            <input id="db_user" name="db_user" required>
                        </div>
                        <div>
                            <label for="db_pass">Пароль БД</label>
                            <input id="db_pass" name="db_pass" type="password" autocomplete="new-password">
                        </div>
                        <div>
                            <label for="db_prefix">Префикс таблиц</label>
                            <input id="db_prefix" name="db_prefix" value="ee_" required>
                        </div>
                    </div>
                </section>

                <section style="margin-top: 18px;">
                    <details>
                        <summary>Создать БД/пользователя через административный доступ</summary>
                        <div class="grid" style="margin-top: 14px;">
                            <div class="full">
                                <label><input type="checkbox" name="create_database" value="1"> Создать БД, если её нет</label>
                                <label><input type="checkbox" name="create_user" value="1"> Создать пользователя и выдать права</label>
                            </div>
                            <div>
                                <label for="db_admin_user">DB admin user</label>
                                <input id="db_admin_user" name="db_admin_user" autocomplete="off">
                            </div>
                            <div>
                                <label for="db_admin_pass">DB admin password</label>
                                <input id="db_admin_pass" name="db_admin_pass" type="password" autocomplete="new-password">
                            </div>
                            <div>
                                <label for="db_user_host">Host для DB user</label>
                                <input id="db_user_host" name="db_user_host" value="localhost">
                            </div>
                        </div>
                    </details>
                </section>

                <section style="margin-top: 18px;">
                    <details>
                        <summary>Пароли bootstrap-пользователей</summary>
                        <div class="grid" style="margin-top: 14px;">
                            <div>
                                <label for="admin_password">Пароль администратора</label>
                                <input id="admin_password" name="admin_password" type="password" autocomplete="new-password">
                                <span class="hint">Если оставить пустым, установщик сгенерирует пароль и покажет его один раз.</span>
                            </div>
                            <div>
                                <label for="moderator_password">Пароль модератора</label>
                                <input id="moderator_password" name="moderator_password" type="password" autocomplete="new-password">
                            </div>
                        </div>
                    </details>
                </section>

                <?php if (!empty($status['config_exists'])) { ?>
                    <section style="margin-top: 18px;">
                        <label><input type="checkbox" name="overwrite_config" value="1"> Перезаписать существующий inc/configuration.php с backup</label>
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
