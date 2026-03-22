# API Reference

Этот раздел — справочный снимок developer-facing API EE_FrameWork. Он не заменяет чтение исходников, но помогает быстро вспомнить ключевые сигнатуры.

## ControllerBase

### `getPathController(bool $killApp = false): string`

Возвращает URL-путь к текущему модулю контроллера.

### `showLayout(array $parameters): void`

Отрисовывает layout и финальный HTML.

### `loadModel(string $model, array $arg = [], string $path = '', bool $reload = false): void`

Подключает модель текущего модуля.

### `normalizeOperationResult(mixed $result, array $options = []): OperationResult`

Нормализует legacy- и standard-результаты в единый контракт.

### `notifyOperationResult(mixed $result, array $options = []): OperationResult`

Нормализует результат и показывает admin notification.

## Router

### `setPath(string $path): void`

Задаёт корневую папку контроллеров.

### `delegate(): void`

Разбирает маршрут и вызывает нужный контроллер/action.

### `clearRouteCache(): void`

Очищает route cache.

### `isRouteCacheEnabled(): bool`

Возвращает статус route cache.

### `getRouteCacheBackend(): string`

Возвращает backend `file|redis`.

## View

### `set(string $name, mixed $value, bool $overwrite = false): bool`

Передаёт переменную в шаблон.

### `get(string $name): mixed`

Возвращает значение переменной шаблона.

### `getVars(): array`

Возвращает весь набор variables.

### `remove(string $name): void`

Удаляет переменную.

### `read(string $templateName, bool $cache = true, string $addPath = '', bool $fullPath = false): string`

Рендерит шаблон.

## Hook

### `Hook::add(string $key, $callback, int $priority = 10, ?string $source = null, ?string $extensionId = null): bool`

Регистрирует callback.

### `Hook::run(string $key, ...$args): void`

Запускает все callback-ы события.

### `Hook::filter(string $key, $value, ...$args)`

Пропускает значение через цепочку callback-ов.

### `Hook::until(string $key, $default = null, ...$args)`

Возвращает первый ненулевой результат.

### `Hook::remove(string $key): bool`

Удаляет весь hook key.

### `Hook::removeCallback(string $key, $callback): bool`

Удаляет конкретный callback.

### `Hook::removeBySource(string $source): int`

Удаляет callback-ы по metadata source.

### `Hook::hasCallback(string $key, $callback): bool`

Проверяет наличие callback-а.

### `Hook::getAllHooks(): array`

Возвращает все hooks с metadata.

## Users

### `getUserData($id = 0, $create_table = false)`

Возвращает подготовленные данные пользователя.

### `setUserData(int $userId = 0, array $fields = []): int`

Обновляет данные пользователя.

### `getUsersData($order = 'user_id ASC', $where = null, $start = 0, $limit = 100, bool $deleted = false)`

Список пользователей.

### `getUserOptions($userId)`

Возвращает UI/options пользователя.

### `setUserOptions($userId, $options = '')`

Сохраняет пользовательские options.

## AuthService

Класс отвечает за единый auth-hub:

- local login;
- logout;
- challenge-based recovery/setup;
- identity linking;
- soft-delete lifecycle.

Практически это основной orchestration-класс auth-системы.

## CacheManager

### `resolveBackend(): string`

Возвращает `file|redis`.

### `isCached(string $param): string|false`

Проверяет наличие HTML cache entry.

### `getCache(string $cacheKey): string`

Читает содержимое кэша.

### `setCache(string $content, string $param): void`

Пишет HTML cache entry.

### `clearCache(string $param): void`

Удаляет одну cache entry.

### `clearAllCache(): void`

Очищает HTML, block и route cache проекта.

## Logger

### `Logger::bootstrap(): void`

Инициализирует logger runtime и `request_id`.

### `Logger::debug|info|notice|warning|error|critical|audit(...)`

Основные structured logging методы.

### `Logger::legacy(...)`

Wrapper для старых вызовов `preFile`.

### `Logger::getRequestId(): string`

Возвращает `request_id` текущего запроса.

## OperationResult

### `OperationResult::success(...)`

Успешный результат мутации.

### `OperationResult::failure(...)`

Операционная ошибка.

### `OperationResult::validation(...)`

Ошибка валидации.

### `OperationResult::fromLegacy(...)`

Адаптер старых форматов к единому контракту.

### `isSuccess(): bool`

Проверка успешности.

### `isFailure(): bool`

Проверка ошибки.

### `getId(?array $keys = null): int`

Извлекает идентификатор сущности из результата.

## SysClass: часто используемые методы

### `SysClass::getModelObject(...)`

Быстрый доступ к модели вне контроллера.

### `SysClass::getAccessUser(...)`

Проверка ролей и доступа.

### `SysClass::handleRedirect($code = 404, $url = ENV_URL_SITE): void`

Redirect и штатный error flow.

### `SysClass::ee_cleanArray(...)`, `ee_cleanString(...)`

Базовая очистка входных данных.

### `SysClass::createDirectoriesForFile(...)`

Создание нужных директорий перед записью файла.

## Константы, которые нужно помнить

- `ENV_SITE_PATH`
- `ENV_APP_DIRECTORY`
- `ENV_CONTROLLER_PATH`
- `ENV_CONTROLLER_NAME`
- `ENV_CONTROLLER_ACTION`
- `ENV_CONTROLLER_ARGS`
- `ENV_CONTROLLER_FOLDER`
- `ENV_CACHE`
- `ENV_CACHE_PATH`
- `ENV_CACHE_BACKEND`
- `ENV_ROUTING_CACHE_ENABLED`
- `ENV_AUTH_TRANSPORT`
- `ENV_CUSTOM_PATH`
- `ENV_DEF_LANG`

## Что читать после API Reference

Если вы ищете не сигнатуру, а способ применения, возвращайтесь в тематические разделы:

- [Models](/docs/models)
- [Views](/docs/views)
- [Hooks](/docs/hooks)
- [Auth](/docs/auth)
- [Cache](/docs/cache)
- [Debug](/docs/debug)
