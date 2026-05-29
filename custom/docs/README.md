# EE_FrameWork: карта документации

EE_FrameWork — это PHP-фреймворк с явным front controller, MVC-ядром, встроенным административным контуром и расширением через `custom/` без правок ядра.

Эта документация написана для разработчика, который:

- уверенно знает синтаксис PHP;
- умеет читать чужой код;
- не знает внутреннюю архитектуру EE_FrameWork;
- хочет быстро перейти от первого запроса к уверенной разработке на проекте.

## Как читать документацию

Если вы открыли проект впервые, идите по порядку:

1. [Быстрый старт](/docs/quick-start)
2. [Установщик проекта](/docs/installer)
3. [Архитектура](/docs/architecture)
4. [Маршрутизация](/docs/routing)
5. [Модели](/docs/models)
6. [Контентная модель](/docs/content-model)
7. [Views и Layouts](/docs/views)
8. [Hooks и custom-слой](/docs/hooks)
9. [Импорт структуры](/docs/imports)
10. [Auth и доступ](/docs/auth)
11. [Кэширование](/docs/cache)
12. [Cron-агенты и scheduler](/docs/cron-agents)
13. [Резервное копирование](/docs/backup)
14. [Отладка](/docs/debug)
15. [Security и production hardening](/docs/security)
16. [API Reference](/docs/api-reference)
17. [Content API v1](/docs/catalog-api)

## Что важно понять про EE_FrameWork сразу

- `index.php` — единая точка входа HTTP.
- production web server должен исполнять PHP только через front controller.
- `inc/configuration.php` создаётся установщиком и хранит только настройки конкретного сайта.
- `inc/configuration.sample.php` — шаблон конфигурации для репозитория.
- `inc/bootstrap.php` собирает runtime ядра, вычисляет производные константы и подключает `custom/`.
- `Router` определяет контроллер, action и аргументы из URL.
- контроллеры живут в `app/<module>/index.php` или `app/<module>/<controller>.php`.
- `View` и layout-слой отвечают за вывод, а не за бизнес-логику.
- проектный код расширения должен идти в `custom/`, а не в `inc/hooks.php` и не в `inc/startup.php`.
- auth-routing и contour-policy должны настраиваться через hooks в `custom/hooks.php`, а не project-specific правками ядра.
- ошибки маршрутизации и недоступные документы должны уходить в `error.php` в корне проекта.
- `ENV_DEBUG=false`, закрытые внутренние директории и allowlist sanitization публичного rich text обязательны для production.

## Базовая карта репозитория

```text
/index.php                 HTTP entrypoint
/inc/                      bootstrap, configuration sample, installer, core hooks
/classes/system/           ядро: Router, View, Users, CacheManager, Logger, Hook
/classes/helpers/          helper- и service-классы
/app/                      контроллеры, views, js/css, models по модулям
/app/docs/                 docs-модуль как обычный маршрут фреймворка
/custom/                   upgrade-safe слой проекта
/custom/docs/              исходники пользовательской документации
/uploads/                  пользовательские файлы и данные
/layouts/                  layout-шаблоны
/error.php                 штатная обработка ошибок 4xx/5xx
```

## Как устроен docs-модуль

В проекте документация разделена на два рабочих слоя:

- `custom/docs/*.md` и `custom/docs/manifest.json` — источник истины для контента;
- `app/docs/...` — обычный модуль фреймворка, который читает этот контент и отдаёт публичные URL вида `/docs/quick-start`.

Это разделение сделано специально:

- исходники документации не смешиваются с ядром;
- обновление платформы не должно затереть проектные тексты;
- docs-модуль остаётся обычным маршрутом фреймворка.

Если вы обновляете документацию, редактируйте `custom/docs/`, а не layout и не core hooks.

## Принципы разработки в EE_FrameWork

- Ядро и проектный слой разделяются жёстко.
- Контроллер управляет сценарием запроса, но не тащит в себя SQL.
- Модель отвечает за данные и возвращает единый результат операции.
- Мутации из модели наружу должны возвращать `OperationResult`.
- Хуки расширяют поведение, но не заменяют архитектуру.
- Кэш — это слой ускорения, а не источник истины.
- Логи, CLI-диагностика и явные health-check сценарии используются для диагностики, а не для повседневного UX.

## Когда идти в API Reference

Идите в [API Reference](/docs/api-reference), когда:

- вы уже поняли общий поток запроса;
- вам нужен конкретный метод `View`, `Hook`, `Users`, `CacheManager` или `Logger`;
- вы хотите быстро вспомнить сигнатуру без чтения исходников.
