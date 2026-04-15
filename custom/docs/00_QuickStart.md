# Быстрый старт: 5 минут до первого запроса

Этот раздел нужен, чтобы быстро поднять EE_FrameWork локально и понять, где находится минимальная рабочая точка расширения.

## Минимальные требования

- PHP `8.0+`
- MySQL/MariaDB для штатного runtime
- расширения PHP:
  - `pdo` или драйвер вашей СУБД
  - `mbstring`
  - `json`
  - `session`
  - `fileinfo`
  - `openssl`
  - `curl` для внешних HTTP-интеграций
- Redis опционален, если включаете Redis-cache

## Права на директории

Веб-процесс должен уметь писать в:

- `cache/`
- `logs/`
- `uploads/`
- временные каталоги импорта, если вы их используете

Практическое правило:

- единый владелец или единая группа для CLI и web-процесса;
- для `logs/` и `cache/` желательно настроить наследование группы, чтобы фоновые задачи и веб не конфликтовали по правам.

## Роль configuration.php и bootstrap.php

- `inc/configuration.php` — только конфигурация проекта и значения по умолчанию.
- `inc/bootstrap.php` — runtime-слой: вычисляет производные значения, определяет константы, подключает `startup.php`, включает core bootstrap и поднимает `custom/`.

Практическое правило:

- меняйте настройки в `inc/configuration.php`;
- не добавляйте в него функции, shutdown-обработчики, probe-логику и side effects;
- всё вычислительное и bootstrap-related должно жить в `inc/bootstrap.php`.

## Что происходит в index.php

Последовательность входа в систему такая:

1. Проверяется версия PHP.
2. Подключается `inc/bootstrap.php`.
3. Вызывается `ee_bootstrap_runtime()`.
4. Поднимается `BotGuard`.
5. Создаётся `Router`.
6. `Router` определяет контроллер и делегирует запрос.

Практически это означает:

- любой HTTP-запрос входит через `index.php`;
- до контроллера уже готовы конфиг, автозагрузка, core hooks и `custom/` bootstrap;
- именно `Router` определяет, какой контроллер и action будут вызваны.

## Первый запуск встроенным сервером

Для быстрого smoke-теста можно начать так:

```bash
php -S 127.0.0.1:8080 -t .
```

После этого проверьте:

- `http://127.0.0.1:8080/index.php`
- `php inc/cli.php help`

Важно:

- для полноценной работы красивых URL нужен веб-сервер с rewrite-правилами;
- встроенный сервер удобен для первого запуска и проверки bootstrap, но не заменяет normal Apache/Nginx setup.

## Минимальная настройка перед первым осмысленным запуском

Перед тем как проверять маршруты, убедитесь, что:

- конфигурация БД в `inc/configuration.php` указывает на доступную базу;
- `inc/configuration.php` содержит только настройки, а не project-specific логику;
- веб-процесс действительно может писать в `logs/`, `cache/` и `uploads/`;
- в окружении не осталось старых route/html cache после переноса проекта;
- системный CLI поднимается без fatal через `php inc/cli.php help`.

Если БД ещё пустая, фреймворк поднимает install-flow через системный контур `Users`. Для разработчика это значит: первый запуск нужно проверять не только по “открылась ли главная”, но и по тому, что install/bootstrap не споткнулся на правах, БД и конфигурации.

## Первый контроллер

Самый быстрый путь — новый модуль в `app/`.

Пример структуры:

```text
app/hello/
├─ index.php
├─ views/
│  └─ v_index.php
├─ js/
└─ css/
```

Пример `app/hello/index.php`:

```php
<?php

use classes\system\ControllerBase;

class ControllerHello extends ControllerBase {
    public function index($params = []): void {
        $this->view->set('message', 'Hello from EE_FrameWork');
        $this->html = $this->view->read('v_index');
        $this->parameters_layout['layout_content'] = $this->html;
        $this->showLayout($this->parameters_layout);
    }
}
```

Пример `app/hello/views/v_index.php`:

```php
<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<h1><?= htmlspecialchars((string) ($message ?? ''), ENT_QUOTES) ?></h1>
```

Открывайте:

```text
/hello
```

## Обязательные правила именования

- файл контроллера по умолчанию: `index.php`;
- класс: `Controller<ModuleName>`;
- базовый action: `index($params = [])`;
- шаблон: `views/v_<action>.php` или любой другой файл, который вы вызываете через `View::read()`.

## Что оставить в app, а что выносить в custom

Оставляйте в `app/`:

- маршруты;
- контроллеры;
- views;
- модульные js/css;
- модели, специфичные для модуля.

Выносите в `custom/`:

- проектные хуки;
- сервисы и helper-классы, которые не должны теряться при обновлении ядра;
- custom bootstrap.

## Первая проверка, что всё собрано правильно

Минимальный чеклист:

1. `index.php` открывается без fatal.
2. новый маршрут из `app/hello/index.php` отдаёт ответ.
3. `logs/` создаётся и writable.
4. `cache/` writable.
5. `php inc/cli.php help` выполняется без ошибок.

Если не сработало:

- идите в [Отладка](/docs/debug);
- проверьте `error.php`, `logs/` и серверные логи PHP.
- проверьте, что системный CLI тоже поднимается через `php inc/cli.php help`.
