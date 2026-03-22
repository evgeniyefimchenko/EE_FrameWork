# Модели и работа с данными

В EE_FrameWork модель — это слой данных и инвариантов, а не место для HTML, redirect или UI-решений.

## Как получить модель

Есть два основных способа.

### Из контроллера через loadModel()

```php
$this->loadModel('m_pages');
$page = $this->models['m_pages']->getPageData($pageId, $languageCode);
```

Это стандартный путь для controller/trait кода.

### Через SysClass::getModelObject()

```php
$modelPages = SysClass::getModelObject('admin', 'm_pages');
$page = $modelPages->getPageData($pageId, 'RU');
```

Этот путь удобен:

- в service-классах;
- в hooks;
- в lifecycle-коде;
- в import/cron сценариях.

## Ожидаемый интерфейс модели

Внутри проекта нет одного абстрактного интерфейса на все модели, но практический стандарт такой:

- методы чтения возвращают массивы, scalar-значения или `null`;
- методы мутации возвращают `OperationResult`;
- модель сама делает SQL-фильтрацию и нормализацию входа;
- контроллер не должен гадать по типу результата.

## Стандарт мутаций: OperationResult

Если метод модели меняет состояние, возвращайте:

```php
OperationResult::success(...)
OperationResult::validation(...)
OperationResult::failure(...)
```

Пример:

```php
return OperationResult::success($pageId, '', 'updated');
return OperationResult::validation('Не указан title', $pageData);
return OperationResult::failure('Ошибка сохранения страницы', 'page_save_failed', $pageData);
```

Контроллер потом работает только через единый путь:

```php
$result = $this->notifyOperationResult(
    $this->models['m_pages']->updatePageData($postData, $languageCode),
    ['success_message' => 'Страница сохранена']
);
```

Практический стандарт границы такой:

- модель не возвращает `false`, `['error' => ...]` или `ErrorLogger` наружу как основной контракт;
- controller, trait, importer и cron-код получают единый результат;
- чтение может оставаться на массивах и scalar-значениях, но мутация должна быть стандартизована.

### Что означает успех

```php
return OperationResult::success($pageId, '', 'updated');
return OperationResult::success(['page_id' => $pageId], '', 'created');
```

### Ошибка валидации

```php
return OperationResult::validation('Не указан title', $pageData);
```

### Операционная ошибка

```php
return OperationResult::failure(
    'Ошибка сохранения страницы',
    'page_save_failed',
    $pageData
);
```

Если код ещё находится в переходном сценарии, допускается адаптер `OperationResult::fromLegacy(...)`, но для новых методов это уже не считается нормой.

## Работа с БД

Основной рабочий слой — `SafeMySQL`.

Типовые операции:

```php
$row = SafeMySQL::gi()->getRow('SELECT * FROM ?n WHERE page_id = ?i', Constants::PAGES_TABLE, $pageId);
$rows = SafeMySQL::gi()->getAll('SELECT * FROM ?n WHERE category_id = ?i', Constants::PAGES_TABLE, $categoryId);
$value = SafeMySQL::gi()->getOne('SELECT COUNT(*) FROM ?n', Constants::PAGES_TABLE);
$ok = SafeMySQL::gi()->query('UPDATE ?n SET ?u WHERE page_id = ?i', Constants::PAGES_TABLE, $payload, $pageId);
```

Практические правила:

- фильтруйте массивы через `SafeMySQL::gi()->filterArray(...)`;
- не давайте контроллеру формировать SQL;
- пользуйтесь `Constants::*_TABLE`, а не строками-именами таблиц по коду;
- держите в модели нормализацию, а не в каждом контроллере отдельно.

## Где валидировать входные данные

Слои валидации такие:

### Controller

Отвечает за:

- общий доступ к action;
- базовую форму запроса;
- выбор языка;
- наличие обязательных route-параметров.

### Model

Отвечает за:

- бизнес-валидацию;
- SQL-safe нормализацию;
- проверку инвариантов;
- дубликаты;
- связи между сущностями.

## Что не стоит делать в модели

- не отправляйте redirect из модели;
- не вызывайте layout/view;
- не генерируйте HTML;
- не полагайтесь на bell-уведомления как на единственный канал ошибки.

## Когда использовать транзакции

Используйте транзакцию, если операция:

- пишет в несколько таблиц;
- меняет связи и сущность одновременно;
- может оставить систему в частично обновлённом состоянии.

Типичный пример:

- удаление сущности + чистка связанных property values;
- import;
- auth lifecycle;
- крупная lifecycle-синхронизация.

## Где смотреть хорошие примеры

Смотрите:

- сохранение сущностей в `ModelPages`, `ModelCategories`;
- сохранение свойств в `ModelProperties`;
- import-путь в `WordpressImporter`;
- единый контракт результата в `OperationResult`;
- [Контентная модель и сущности](/docs/content-model).
