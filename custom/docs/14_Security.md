# Security и production hardening

Этот раздел фиксирует базовую security-модель EE_FrameWork для production-развёртывания. Он относится к ядру и не должен зависеть от домена, бренда или предметной области конкретного проекта.

## Production-модель

Обязательные правила:

- единственная публичная PHP-точка входа для HTTP-запросов - `index.php`;
- `error.php` используется только как внутренний error endpoint для штатного 4xx/5xx flow;
- PHP-файлы во внутренних директориях не должны исполняться напрямую через HTTP;
- `ENV_DEBUG=false` в production;
- публичный rich text проходит HTML allowlist sanitizer перед выводом;
- admin API key используется только для доверенных интеграций, работает с throttling и должен оставлять audit trail для операций чтения и записи.

Если нужно добавить новый публичный endpoint, сначала проверьте, можно ли сделать его обычным маршрутом через Router. Отдельный PHP-файл в webroot почти всегда является ошибкой архитектуры.

## Front controller и закрытые директории

Веб-сервер должен отдавать статические файлы только из разрешённых публичных зон и передавать динамические запросы в `index.php`.

Внутренние директории должны быть закрыты от прямого HTTP-доступа:

- `inc/`
- `classes/`
- `layouts/`
- `config/`
- `custom/`
- `app/cron/`
- служебные `data/`
- `exports/`
- `testplan/`
- `logs/`
- `cache/`

Практическое правило: deny-правила должны матчить имя директории от корня URL, а не произвольное вхождение строки в любом публичном path. Иначе можно случайно сломать легальные маршруты вроде `/docs/...`.

Пример проверки после изменения конфигурации:

```bash
nginx -t
curl -I https://example.test/inc/configuration.php
curl -I https://example.test/classes/system/Router.php
curl -I https://example.test/app/cron/run.php
```

Ожидаемый результат для внутренних путей - `403` или `404`, но не `200` и не исполнение PHP.

## Security headers

Production-конфигурация веб-сервера должна задавать базовые headers:

```text
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'self'
```

CSP может быть расширена под конкретный проект, но расширение должно быть явным: добавляйте только реально используемые источники скриптов, стилей, изображений и шрифтов.

## Debug policy

Production-режим:

- `ENV_DEBUG=false`;
- `display_errors=0`;
- нет публичного `phpinfo()`;
- нет постоянных query-флагов, которые раскрывают runtime state;
- ошибки пользователя идут через `error.php`, а детали остаются в логах;
- AJAX/API не должны получать raw exception dump.

Для диагностики используйте:

- серверные PHP logs;
- `logs/` проекта;
- admin logs viewer;
- `php inc/cli.php ...`;
- health-check и diagnostics-команды.

## Rich text и публичный HTML

Нормализация и sanitization - разные операции.

Нормализация может привести импортированный plain text или mixed HTML к абзацам, переносам и относительным ссылкам. Она не считается достаточной защитой для публичного вывода.

Перед рендером публичного rich text должен применяться DOM allowlist sanitizer. Он должен:

- оставлять только разрешённые теги и атрибуты;
- удалять `<script>`;
- удалять event-атрибуты вроде `onclick`;
- удалять URL со схемами `javascript:`, `data:`, `vbscript:`;
- удалять активные или небезопасные элементы: `svg`, `iframe`, `object`, `embed`, `form`, `video`, `audio`;
- безопасно обрабатывать `href`, `src`, `poster` и похожие URL-атрибуты.

Новые rich-text поверхности на фронте должны использовать тот же sanitizer. Нельзя выводить HTML из БД только потому, что он был импортирован, отредактирован администратором или прошёл нормализацию.

## Admin API v1

`app/api/v1.php` - административный JSON API для интеграций.

Базовый контракт безопасности:

- ключ передаётся как `Authorization: Bearer {api_key}` или `X-API-Key: {api_key}`;
- ключи хранятся как hash, raw-ключ показывается только при выдаче;
- доступ разрешён только активному пользователю с ролью администратора;
- read/write запросы проходят общий throttling;
- операции чтения, создания и обновления должны писать структурированный audit log с `request_id`, user id, endpoint, методом, entity type и entity id;
- расширения API не должны обходить `requireApiAdmin()`.

Если проекту нужен внешний публичный API, его нельзя строить на admin API key. Для этого нужен отдельный auth/permission contract.

## Release checklist

Перед публикацией релиза:

1. `ENV_DEBUG=false` в production-конфигурации.
2. Нет `phpinfo()`, временных debug-файлов и ad-hoc diagnostic endpoints в webroot.
3. В webroot нет export ZIP, SQL dump, тест-планов, operational notes и временных файлов.
4. Веб-сервер пропускает PHP только через front controller.
5. Внутренние директории закрыты от HTTP.
6. Security headers выставлены.
7. Admin API keys выданы только доверенным интеграциям.
8. Rich text на публичных страницах проходит allowlist sanitizer.
9. Route/html cache очищены после деплоя.
10. `php inc/cli.php ops:health-check` и smoke-проверки HTTP проходят без critical errors.

## Smoke-проверки

Минимальная проверка hardened-развёртывания:

```bash
php inc/cli.php help
php inc/cli.php ops:health-check
curl -I https://example.test/
curl -I https://example.test/inc/configuration.php
curl -I https://example.test/classes/system/Router.php
curl -I https://example.test/custom/docs/README.md
curl -I https://example.test/app/cron/run.php
```

Внутренние пути не должны возвращать содержимое файлов. Публичная главная и публичные маршруты должны продолжать работать через `index.php`.
