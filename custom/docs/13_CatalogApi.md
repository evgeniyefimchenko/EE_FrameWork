# Content API v1

Этот раздел описывает generic admin API для чтения, создания и обновления контентных сущностей EE_FrameWork: `categories` и `pages`.

API не привязан к предметной области проекта. Конкретные свойства, наборы и схемы определяются текущей БД и импортированной property model.

## Назначение

`app/api/v1.php` нужен для доверенных административных интеграций:

- получить сущность вместе со значениями свойств;
- получить schema/template для будущего `POST`;
- создать категорию или страницу;
- обновить core-поля и свойства существующей сущности.

Это не публичный пользовательский API. Для frontend, мобильного клиента или сторонних пользователей нужен отдельный контракт доступа.

## Security model

Каждый запрос к `pages` и `categories` проходит через admin API key.

Ключ можно передать так:

```text
Authorization: Bearer {api_key}
X-API-Key: {api_key}
```

Обязательные правила:

- API key хранится в БД как hash;
- raw-ключ показывается только в момент выдачи;
- пользователь ключа должен быть активен;
- роль пользователя должна быть admin;
- read/write запросы проходят общий throttling;
- операции чтения, создания и обновления должны писать structured audit log;
- расширения API не должны обходить `requireApiAdmin()`.

## Endpoints

```text
GET    /api/v1/pages/id/{id}
GET    /api/v1/pages/schema?category_id={category_id}
POST   /api/v1/pages
PUT    /api/v1/pages/id/{id}
PATCH  /api/v1/pages/id/{id}

GET    /api/v1/categories/id/{id}
GET    /api/v1/categories/schema?type_id={type_id}
POST   /api/v1/categories
PUT    /api/v1/categories/id/{id}
PATCH  /api/v1/categories/id/{id}
```

`language_code` можно передавать в query или payload. Если язык не передан, используется default content language.

## Schema endpoint

Перед созданием сущности безопаснее запросить schema.

Для страницы:

```text
GET /api/v1/pages/schema?category_id=1
```

Для категории:

```text
GET /api/v1/categories/schema?type_id=1
```

Ответ содержит:

- `entity_type`
- `language_code`
- `context`
- `entity_fields`
- `entity_defaults`
- `properties`

Почему schema важна:

- в ней уже есть актуальные `property_id`, `set_id`, `uid`;
- choice-поля возвращают допустимые варианты;
- repeatable и composite fields не нужно собирать наугад;
- integration client не зависит от имён свойств там, где лучше использовать ID.

## Core-поля

Для `page` обязательны:

- `category_id`
- `title`

Частые optional-поля:

- `parent_page_id`
- `status`
- `slug`
- `route_path`
- `short_description`
- `description`

Для `category` обязательны:

- `type_id`
- `title`

Частые optional-поля:

- `parent_id`
- `status`
- `slug`
- `route_path`
- `short_description`
- `description`

## Payload

Core-поля можно передавать на верхнем уровне или внутри `page`/`category`.

Свойства передаются в массиве `properties`.

Свойство можно адресовать:

- по `property_id`;
- или по `name`, если интеграция осознанно зависит от имени.

Минимальный пример `POST /api/v1/pages`:

```json
{
  "page": {
    "category_id": 1,
    "title": "Example page",
    "status": "active",
    "short_description": "Short public summary",
    "description": "<p>Public description.</p>"
  },
  "properties": [
    {
      "property_id": 10,
      "set_id": 3,
      "fields": [
        {
          "uid": "default_0",
          "type": "text",
          "value": "Example value"
        },
        {
          "uid": "default_1",
          "type": "date-range",
          "value": {
            "from": "01.06",
            "to": "15.06"
          }
        }
      ]
    }
  ]
}
```

Пример `POST /api/v1/categories`:

```json
{
  "category": {
    "type_id": 1,
    "title": "Example category",
    "status": "active"
  },
  "properties": []
}
```

## Repeatable и composite fields

Для повторяемых свойств берите schema и меняйте только `value` у нужных `uid`.

Если поле хранит несколько значений, порядок элементов массива должен быть согласован между всеми fields одного composite-свойства. Один индекс массива соответствует одному повторяемому элементу.

Для `date-range` значение одного поля передаётся как объект `{ "from": "...", "to": "..." }`. В repeatable-свойстве это массив таких объектов:

```json
{
  "uid": "period",
  "type": "date-range",
  "value": [
    { "from": "01.06", "to": "15.06" },
    { "from": "16.06", "to": "30.06" }
  ]
}
```

`date-range` индексируется поиском как структурное значение `from/to`. Generic API не превращает его в публичный фильтр по датам: если интеграции нужен поиск по пересечению интервалов, это отдельный прикладной контракт поверх property layer.

Для `repeatable-group` значение передаётся массивом строк. Каждая строка содержит объект `values`, где ключи совпадают с `uid` дочерних fields из schema:

```json
{
  "uid": "prices",
  "type": "repeatable-group",
  "value": [
    {
      "values": {
        "period": { "from": "01.06", "to": "15.06" },
        "price": "2500"
      }
    },
    {
      "values": {
        "period": { "from": "16.06", "to": "30.06" },
        "price": "3000"
      }
    }
  ]
}
```

Если группа находится внутри repeatable/composite-свойства, внешний массив соответствует индексу основного элемента, а внутренний массив — строкам группы:

```json
{
  "uid": "prices",
  "type": "repeatable-group",
  "value": [
    [
      {
        "values": {
          "period": { "from": "01.06", "to": "15.06" },
          "price": "2500"
        }
      }
    ]
  ]
}
```

API-клиент не должен пытаться превратить `repeatable-group` в generic materialized filter. Для вложенных дат и цен нужен отдельный domain/nested-filter endpoint или прикладной контракт поверх Content API.

## Что не делать

- не придумывать свои `uid`;
- не отправлять label вместо option value;
- не собирать choice-списки вручную, если schema уже доступна;
- не полагаться на set name для сохранения;
- не использовать admin API key как публичную авторизацию;
- не выводить HTML, полученный через API, без public rich text sanitizer.

## Audit и troubleshooting

Для production-интеграций полезно логировать на стороне клиента:

- endpoint;
- HTTP method;
- request id из ответа или логов;
- entity type;
- entity id;
- user/integration label API key.

На стороне EE_FrameWork операции API должны оставлять structured audit log. Ошибки API возвращаются JSON-ответом и не должны раскрывать raw exception dump.
