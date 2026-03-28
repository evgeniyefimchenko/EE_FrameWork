# API Reference

Этот раздел — справочный снимок developer-facing API EE_FrameWork. Он не заменяет чтение исходников, но помогает быстро вспомнить ключевые сигнатуры.

## Bootstrap

### `ee_bootstrap_prepare_core(): array`

Загружает `inc/configuration.php`, вычисляет runtime-константы, подключает `inc/startup.php` и применяет раннюю инициализацию ядра.

### `ee_bootstrap_runtime(): void`

Поднимает autoload, logger, core hooks и project-level слой `custom/`.

### `ee_bootstrap_preload(): void`

Готовит preload/runtime-контур для OPcache preload.

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

## CronAgentService

### `CronAgentService::getSummary(): array`

Возвращает сводку по агентам, лимитам scheduler-а и команде минутного запуска.

### `CronAgentService::getAgents(int $limit = 200): array`

Возвращает список настроенных cron-агентов.

### `CronAgentService::saveAgent(array $agentData): OperationResult`

Создаёт или обновляет cron-агента.

### `CronAgentService::runDueAgents(string $triggerSource = 'scheduler'): OperationResult`

Выполняет один минутный проход scheduler-а.

### `CronAgentService::runAgentNow(int|string $idOrCode, string $triggerSource = 'manual'): OperationResult`

Запускает конкретный агент вручную.

### `CronAgentService::recoverStaleAgents(): OperationResult`

Снимает stale locks и переводит зависшие run-ы в failed.

## CronAgentRegistry

### `CronAgentRegistry::getHandlers(): array`

Возвращает список встроенных handler-ов cron-агентов.

### `CronAgentRegistry::runHandler(string $handler, array $payload = [], array $context = []): array`

Выполняет встроенный handler cron-агента.

## EntityTranslationService

## EntityPublicUrlService

### `EntityPublicUrlService::buildEntityUrl(string $entityType, int $entityId, ?string $languageCode = null, bool $absolute = true, ?bool $includeLanguageQuery = null): string`

Собирает public URL сущности по semantic contract.

### `EntityPublicUrlService::resolvePath(string $routePath, ?string $preferredLanguageCode = null): ?array`

Резолвит semantic path в `entity_type/entity_id/language_code`.

### `EntityPublicUrlService::buildHreflangLinks(string $entityType, int $entityId, array $availableLanguageCodes = []): array`

Возвращает canonical alternate links для layout/meta.

## ModelPublicCatalog

### `ModelPublicCatalog::getPagePayload(int $pageId, string $languageCode = ENV_DEF_LANG): ?array`

Собирает публичный payload карточки объекта:

- breadcrumbs
- description
- gallery
- contacts
- details
- map
- room/pricing block
- related objects

### `ModelPublicCatalog::getCategoryPayload(int $categoryId, string $languageCode = ENV_DEF_LANG): ?array`

Собирает публичный payload страницы курорта:

- overview text
- left/right rich text blocks
- gallery
- map
- child resorts
- direct object cards

### `EntityTranslationService::ensureInfrastructure(bool $force = false): void`

Создаёт и обновляет таблицу `ee_entity_translations`.

### `EntityTranslationService::ensureEntity(string $entityType, int $entityId): array`

Гарантирует, что сущность участвует в translation-группе.

### `EntityTranslationService::linkEntityToSource(string $entityType, int $entityId, int $sourceEntityId): array`

Привязывает новую языковую версию к translation-группе исходной сущности.

### `EntityTranslationService::getTranslationState(string $entityType, int $entityId, array $availableLanguageCodes = []): array`

Возвращает текущее состояние переводов сущности, включая существующие и отсутствующие языковые версии.

### `EntityTranslationService::getTranslatedEntityId(string $entityType, int $sourceEntityId, string $targetLanguageCode): ?int`

Возвращает ID перевода в нужной локали, если он уже существует.

### `EntityTranslationService::duplicatePropertyValuesFromSource(string $entityType, int $sourceEntityId, int $targetEntityId, string $sourceLanguageCode, string $targetLanguageCode): int`

Копирует property values из исходной языковой версии в новую сущность-перевод.

### `EntityTranslationService::removeEntityTranslation(string $entityType, int $entityId): void`

Удаляет translation-связь сущности и ребалансирует primary-версию группы.

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

## LegalConsentService

### `LegalConsentService::ensureInfrastructure(...)`

Добивает колонками `ee_users` под обязательные согласия для существующих проектов.

### `LegalConsentService::getSubmittedFlags(array $input): array`

Нормализует два обязательных чекбокса:

- `privacy_policy_accepted`
- `personal_data_consent_accepted`

### `LegalConsentService::hasRequiredConsents(array $userData): bool`

Проверяет, что пользователь принял оба обязательных документа.

### `LegalConsentService::updateUserConsents(int $userId, array $input, string $source = 'web'): bool`

Обновляет согласия пользователя и сохраняет metadata принятия.

### `EntityPublicUrlService::buildEntityUrl(string $entityType, int $entityId, ?string $languageCode = null, bool $absolute = true, ?bool $includeLanguageQuery = null): string`

Строит публичный URL категории или страницы по semantic contract на базе `slug`.

### `EntityPublicUrlService::resolvePath(string $routePath, ?string $preferredLanguageCode = null): ?array`

Резолвит semantic public URL в сущность `page/category` и её языковой контекст.

### `EntityPublicUrlService::buildHreflangLinks(string $entityType, int $entityId, array $availableLanguageCodes = []): array`

Возвращает `canonical/hreflang`-совместимый набор ссылок для переводов сущности.

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
- `ENV_ROUTING_CACHE_BACKEND`
- `ENV_AUTH_TRANSPORT`
- `ENV_CUSTOM_PATH`
- `ENV_DEF_LANG`
- `ENV_LEGAL_OPERATOR_STATUS`
- `ENV_LEGAL_OPERATOR_NAME`
- `ENV_LEGAL_OPERATOR_ADDRESS`
- `ENV_LEGAL_OPERATOR_INN`
- `ENV_LEGAL_OPERATOR_OGRN`
- `ENV_LEGAL_PRIVACY_POLICY_VERSION`
- `ENV_LEGAL_PERSONAL_DATA_CONSENT_VERSION`

`ENV_ROUTING_CACHE` считается legacy-алиасом. Для новых проектов используйте только `ENV_ROUTING_CACHE_ENABLED`.

## CLI команды, которые важно помнить

- `php app/cron/run.php`
- `php inc/cli.php cron:run-agents`
- `php inc/cli.php cron:run-agent <id|code>`
- `php inc/cli.php cron:import <job_id>`
- `php inc/cli.php ops:health-check`

## Что читать после API Reference

Если вы ищете не сигнатуру, а способ применения, возвращайтесь в тематические разделы:

- [Models](/docs/models)
- [Views](/docs/views)
- [Hooks](/docs/hooks)
- [Auth](/docs/auth)
- [Cache](/docs/cache)
- [Debug](/docs/debug)
