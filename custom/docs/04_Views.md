# Views и Layouts

В EE_FrameWork вывод строится в два этапа:

1. контроллер собирает содержимое через `View`;
2. layout оборачивает это содержимое в общую страницу.

## Базовый API View

Самый частый набор методов:

- `set($name, $value)` — передать переменную в шаблон;
- `get($name)` — получить переменную;
- `read($templateName, $cache = true, $addPath = '', $fullPath = false)` — отрендерить шаблон;
- `remove($name)` — удалить переменную;
- `getVars()` — получить весь набор переданных значений.

Пример:

```php
$this->view->set('page', $pageData);
$this->view->set('title', 'Редактирование страницы');
$body = $this->view->read('v_edit_page');
```

## Как контроллер передаёт данные в layout

Обычно сценарий такой:

```php
$this->html = $this->view->read('v_index');
$this->parameters_layout['layout_content'] = $this->html;
$this->parameters_layout['title'] = 'Страница';
$this->showLayout($this->parameters_layout);
```

`showLayout()` живёт в `ControllerBase` и отвечает за финальную сборку страницы.

## Где лежат шаблоны

Обычно:

```text
app/<module>/views/
```

Примеры:

```text
app/admin/views/v_dashboard.php
app/docs/views/v_index.php
app/index/views/v_login_form.php
app/index/views/v_public_category.php
app/index/views/v_public_page.php
```

## Правила именования

Практический стиль:

- префикс `v_` для view-файлов;
- layout-шаблоны отдельно в `/layouts`;
- не смешивать layout и частичный view в одном файле.

## Экранирование

Базовое правило безопасности:

- всё, что пришло от пользователя или из БД, экранируйте при выводе;
- не полагайтесь на то, что данные «уже чистые».

Стандарт:

```php
<?= htmlspecialchars((string) ($title ?? ''), ENT_QUOTES) ?>
```

Не выводите сырые строки так:

```php
<?= $title ?>
```

Исключение:

- только явно подготовленный HTML-контент, который вы осознанно рендерите как HTML.

## Подключение ассетов

Обычно контроллер добавляет CSS/JS через `parameters_layout`:

```php
$this->parameters_layout['add_style'] .= '<link rel="stylesheet" href="' . $this->getPathController() . '/css/index.css">';
$this->parameters_layout['add_script'] .= '<script src="' . $this->getPathController() . '/js/index.js"></script>';
```

Практические правила:

- модульные ассеты держите рядом с модулем;
- общие ассеты — в `assets/`;
- избегайте жёсткого копирования одних и тех же `<script>` в разные контроллеры.

## Кэширование views

`View::read()` умеет работать с cache manager.

Но есть важные ограничения:

- админская часть не кэшируется;
- HTML cache должен учитывать контекст запроса;
- кэш нельзя использовать как замену корректной инвалидации.

Если шаблон содержит динамический фрагмент, его можно рендерить как dynamic block, а не кешировать целиком.

## Что считается хорошим стилем view-слоя

- view получает уже подготовленные данные;
- view не ходит сам в БД;
- view не содержит бизнес-решений;
- layout не решает, какие данные читать;
- контроллер не собирает HTML строками, если можно использовать шаблон.

## Public Catalog Views

Для публичных semantic entity URL больше не используется generic-view с raw полями сущности.

Рабочая схема теперь такая:

1. Router резолвит semantic path.
2. `ControllerIndex::public_entity()` вызывает public model.
3. `ModelPublicCatalog` подготавливает человекочитаемый payload:
   - breadcrumbs
   - gallery
   - contacts
   - map
   - room/pricing block
   - related objects
4. View только рендерит этот payload.

Это важный ориентир для будущего фронта: публичный шаблон должен получать уже нормализованные domain-данные, а не разбирать `property_values` самостоятельно.
