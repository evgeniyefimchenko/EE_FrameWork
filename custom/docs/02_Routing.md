# Маршрутизация

Router в EE_FrameWork разбирает URL и превращает его в четыре значения:

- `ENV_CONTROLLER_PATH`
- `ENV_CONTROLLER_NAME`
- `ENV_CONTROLLER_ACTION`
- `ENV_CONTROLLER_ARGS`

## Базовое правило

Маршрут интерпретируется относительно папки `app/`.

Примеры:

```text
/                         -> app/index/index.php -> index()
/admin                    -> app/admin/index.php -> index()
/admin/pages              -> app/admin/index.php -> pages()
/hello                    -> app/hello/index.php -> index()
/hello/edit/id/15         -> app/hello/index.php -> edit(['id', '15'])
```

## Как Router выбирает контроллер

Алгоритм в упрощённом виде:

1. Берёт `$_GET['route']`.
2. Нормализует путь.
3. Проверяет, является ли первый сегмент папкой внутри `app/`.
4. Если да, ищет контроллер внутри этой папки.
5. Если нет, пытается трактовать сегмент как файл-контроллер.
6. Если action не существует, но контроллер имеет `index()`, Router передаёт action как первый аргумент в `index($params)`.

Именно поэтому путь вида `/docs/quick-start` может обрабатываться одним `ControllerDocs::index()` с аргументом `quick-start`.

В текущей архитектуре docs-модуль — это обычный маршрут фреймворка:

- контроллер живёт в `app/docs/index.php`;
- контент живёт в `custom/docs/`;
- URL `/docs/<slug>` должен доходить до Router так же, как и любой другой маршрут проекта.

## Приоритеты

Практический приоритет такой:

1. существующая папка-модуль;
2. существующий файл контроллера;
3. fallback в `index`-контроллер с интерпретацией сегмента как action;
4. `error.php` через `SysClass::handleRedirect(404)`.

## Статические и динамические маршруты

Специального декларативного роутера с отдельной таблицей маршрутов здесь нет.

Маршрут формируется из структуры файлов.

Плюсы:

- низкий порог входа;
- быстрое создание модуля;
- легко понять, откуда берётся путь.

Минусы:

- нужно дисциплинированно держать структуру `app/`;
- сложные alias-маршруты лучше решать контроллером или hooks;
- очень нетривиальные SEO-схемы требуют отдельного проектного слоя.

## Public URL Contract

Для `categories` и `pages` теперь действует отдельный semantic URL layer поверх core-полей `slug` и optional-полей `route_path`.

Контракт такой:

- категория: `/<category-slug>/<child-category-slug>`
- страница: `/<category-slug>/<child-category-slug>/<page-slug>`

Правила:

- `slug` хранится в самих таблицах `ee_categories` и `ee_pages`, а не в свойствах;
- `route_path` тоже хранится в core-таблицах `ee_categories` и `ee_pages`, а не в свойствах;
- если у сущности заполнен `route_path`, public resolver использует именно его;
- если `route_path` пуст, маршрут собирается из обычной slug-иерархии;
- Router сначала проверяет реальные модули и контроллеры в `app/`, и только потом пробует semantic entity route;
- public route резолвится по текущему языковому контексту;
- для явного выбора не-default языка поддерживается suffix-параметр `?sl=EN`.

Пример:

```text
/gaspra
/gaspra/gostinica-nika
/noviyafon/catalog/flat
/gaspra/gostinica-nika?sl=EN
```

Это позволяет держать два режима:

- штатный EE semantic routing по `slug`;
- опциональное сохранение donor-path при полном переезде с другого движка.

`docs` в этот контракт не входят и продолжают жить отдельным модулем `/docs/...`.

После резолва semantic entity route Router передаёт запрос в обычный public controller:

- `app/index/index.php -> ControllerIndex::public_entity()`

Дальше уже public model layer собирает domain payload:

- `ModelPublicCatalog::getCategoryPayload()`
- `ModelPublicCatalog::getPagePayload()`

И только потом включаются dedicated views:

- `app/index/views/v_public_category.php`
- `app/index/views/v_public_page.php`

## Важная оговорка про веб-сервер

Если на сервере есть защитные `deny`-регексы для внутренних директорий, они должны быть привязаны к имени директории, а не к любому префиксу пути.

Хороший вариант:

```text
^/(logs|classes|layouts|inc|config)(/|$)
```

Опасный вариант:

```text
/(logs|classes|layouts|inc|config)
```

Во втором случае сервер может начать блокировать легальные публичные URL вроде `/docs/settings-page`, даже если это не внутренний каталог проекта.

## Route cache

Router умеет кэшировать разрешение маршрута.

Что важно знать:

- route cache теперь можно реально выключать;
- backend может быть `file` или `redis`;
- route cache отделён по namespace/version;
- ключ route cache учитывает языковой контекст semantic public URL;
- для очистки есть отдельные admin actions.

Используйте route cache, когда:

- структура маршрутов стабильна;
- проект работает под нагрузкой;
- у вас нет частой hot-смены модулей без очистки кэша.

## Инвалидация route cache

Чистите route cache после:

- добавления нового контроллера;
- переименования action;
- изменения структуры `app/`;
- деплоя, который меняет маршрутизацию.

## Отладка маршрута

Для быстрого анализа:

- проверьте, какой URL реально пришёл в веб-сервер;
- очистите route cache;
- откройте `logs/router_error/...`, если маршрут падает в error flow;
- при необходимости временно смотрите runtime-константы `ENV_CONTROLLER_*` через локальную диагностику, а не через публичный контракт документации.

## Что делать, если маршрут не работает

Чеклист:

1. Убедитесь, что файл контроллера реально существует.
2. Проверьте имя класса: `Controller<ModuleName>`.
3. Проверьте, что action callable.
4. Очистите route cache.
5. Посмотрите `logs/router_error/...`.

Если Router не смог найти контроллер или action, штатное поведение должно идти через `error.php` в корне проекта.
