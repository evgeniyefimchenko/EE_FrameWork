# Hooks и расширяемость

EE_FrameWork поддерживает расширение через hooks. Это основной способ добавить проектное поведение без правок ядра.

## Где регистрировать свои hooks

Правильное место:

```text
/custom/hooks.php
```

Неправильные места:

- `inc/hooks.php` — это core-only слой;
- `inc/startup.php` — это core autoload, а не проектная точка расширения.

## Быстрый пример

```php
<?php

ee_add_custom_hook('afterUpdatePageData', [\custom\MyHooks::class, 'afterUpdatePageData'], 20);
```

## Базовый API Hook

- `Hook::add($key, $callback, $priority = 10, $source = null, $extensionId = null)`
- `Hook::run($key, ...$args)`
- `Hook::filter($key, $value, ...$args)`
- `Hook::until($key, $default = null, ...$args)`
- `Hook::remove($key)`
- `Hook::removeCallback($key, $callback)`
- `Hook::removeBySource($source)`
- `Hook::hasCallback($key, $callback)`
- `Hook::getAllHooks()`

## Какой метод когда использовать

### Hook::run()

Используйте, когда просто нужно уведомить подписчиков о событии.

Пример:

- после сохранения страницы;
- после обновления индекса поиска;
- после rebuild lifecycle.

### Hook::filter()

Используйте, когда нужно пропустить значение через цепочку обработчиков.

Пример:

- изменить catalog документации;
- модифицировать payload до сохранения;
- адаптировать результат без переписывания ядра.

### Hook::until()

Используйте, когда один из обработчиков может принять решение и остановить цепочку.

Пример:

- разрешить/запретить действие;
- вернуть кастомный путь обработки;
- short-circuit интеграцию.

## Приоритеты

Приоритет меньше — выполняется раньше.

Практический стиль:

- `10` — обычный обработчик;
- `5` — обработчик, который должен вмешаться раньше;
- `50+` — пост-обработка или логирование.

## Metadata hooks

У hooks есть metadata:

- `source`
- `extension_id`
- `priority`

Это важно для:

- точечного снятия callbacks;
- диагностики, кто именно подписался на событие;
- безопасного отключения проектного слоя.

## Custom-слой и автозагрузка

Собственный код не нужно регистрировать в `startup.php`.

Достаточно:

- положить класс в `custom/src/...`;
- использовать namespace `custom\...`;
- зарегистрировать hook в `custom/hooks.php`.

Bootstrap сам поднимет namespace `custom\`.

## Что должно жить в custom

`custom/` — это не место для маршрутов и не замена `app/`.

Сюда выносятся:

- project-specific hooks;
- custom bootstrap;
- сервисные классы проекта;
- интеграционная логика, которую нельзя потерять при обновлении ядра.

Практическая структура:

```text
custom/
├─ hooks.php
├─ bootstrap.php
└─ src/
   └─ ...
```

Что важно:

- маршруты, контроллеры, `js/css/views/models` по-прежнему создаются в `app/...`;
- `inc/hooks.php` и `inc/startup.php` не должны использоваться как точка проектной кастомизации;
- `custom/` — это upgrade-safe слой проекта, а не “временная папка под всё подряд”.

## Практические сценарии

### Логирование события

```php
ee_add_custom_hook('afterUpdatePageData', static function ($pageId, $payload) {
    \classes\system\Logger::audit('pages', 'Page updated from custom hook', [
        'page_id' => $pageId,
        'payload' => $payload,
    ]);
}, 30);
```

### Модификация данных каталога документов

```php
ee_add_custom_hook('C_docsCatalog', [\custom\docs\ProjectDocsPlugin::class, 'filterCatalog'], 10);
```

Практический смысл этого примера такой:

- `app/docs/...` отвечает только за публичный docs-модуль;
- `custom/src/docs/ProjectDocsPlugin.php` поставляет каталог и HTML документа;
- источник контента лежит в `custom/docs/manifest.json` и `custom/docs/*.md`.

### Пост-обработка после сохранения

```php
ee_add_custom_hook('afterUpdatePropertySetComposition', [\custom\PropertyHooks::class, 'afterSetComposition'], 20);
```

## Правила хорошего hook-кода

- hook должен быть коротким и понятным;
- тяжёлую логику выносите в сервисный класс;
- не используйте hooks как замену нормального контроллера или модели;
- если hook может упасть, логируйте это явно;
- не меняйте структуру аргументов хаотично от обработчика к обработчику.
