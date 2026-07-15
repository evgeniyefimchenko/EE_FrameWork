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

### `requireAccess(array $access = [], array $options = []): bool`

Единый guard controller/action-уровня:

- проверяет доступ текущего пользователя;
- умеет корректно отвечать для обычного HTTP и AJAX;
- логирует отказ доступа;
- поддерживает redirect и user-facing message policy через `options`.

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

### Практические core hook key для auth-routing

- `auth.landing_url`
- `auth.front_landing_url`
- `auth.route_guard`

Практический смысл:

- `auth.landing_url` — базовый landing URL внутри внутренних auth-flow;
- `auth.front_landing_url` — landing URL для frontend login/activation/recovery/provider flows;
- `auth.route_guard` — `Hook::until(...)` guard для contour isolation между `/admin`, `/manager` и `/user`.

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

## ModelUserEdit

### `getUserRolePropertySetIds(int $roleId): array`

Возвращает ID наборов свойств, назначенных роли пользователя.

### `getUserPropertySetIds(int $userId): array`

Возвращает ID наборов свойств, которые должны быть доступны пользователю через его роль.

### `updateUserRolePropertySets(int $roleId, mixed $setIds): OperationResult`

Перезаписывает привязки роли пользователя к наборам свойств в `ee_user_role_to_property_set`.

### `ensureUserPropertyInfrastructure(): bool`

Проверяет и при необходимости подготавливает инфраструктуру пользовательских свойств:

- `entity_type=user` в таблицах свойств;
- таблицу связи ролей и наборов свойств.

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

## EntityPublicUrlService

### `EntityPublicUrlService::buildEntityUrl(string $entityType, int $entityId, ?string $languageCode = null, bool $absolute = true, ?bool $includeLanguageQuery = null): string`

Собирает public URL сущности по semantic contract.

### `EntityPublicUrlService::resolvePath(string $routePath, ?string $preferredLanguageCode = null): ?array`

Резолвит semantic path в `entity_type/entity_id/language_code`.

### `EntityPublicUrlService::buildHreflangLinks(string $entityType, int $entityId, array $availableLanguageCodes = []): array`

Возвращает canonical alternate links для layout/meta.

## ModelPublicCatalog

### `ModelPublicCatalog::getPagePayload(int $pageId, string $languageCode = ENV_DEF_LANG): ?array`

Собирает публичный payload страницы:

- breadcrumbs
- description
- gallery
- contacts
- details
- map
- domain-specific blocks
- related pages

### `ModelPublicCatalog::getCategoryPayload(int $categoryId, string $languageCode = ENV_DEF_LANG): ?array`

Собирает публичный payload категории:

- overview text
- left/right rich text blocks
- gallery
- map
- child categories
- direct pages

## EntityTranslationService

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

### `SysClass::installAiProfilesSchema(): void`

Создаёт или обновляет таблицу `ee_ai_profiles` для ИИ-профилей.

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

## ApiKeyService

### `ApiKeyService::ensureInfrastructure(bool $force = false): void`

Создаёт таблицу API-ключей пользователей.

### `ApiKeyService::generateForUser(int $userId, string $label = 'Default API key'): array`

Выдаёт новый raw API key, отзывает прежние active-ключи пользователя и сохраняет hash.

### `ApiKeyService::resolveActiveKey(string $rawKey): ?array`

Проверяет raw-ключ и возвращает metadata ключа вместе с пользователем.

### `ApiKeyService::touchKeyUsage(int $apiKeyId, ?string $ip = null): void`

Обновляет `last_used_at` и `last_used_ip`.

### `ApiKeyService::extractRequestApiKey(): string`

Читает ключ из `Authorization: Bearer ...`, `Authorization: ApiKey ...` или `X-API-Key`.

## ModelAiSettings

### `ensureInfrastructure(): void`

Гарантирует наличие таблицы `ee_ai_profiles`.

### `getProviderOptions(): array`

Возвращает список поддерживаемых провайдеров для select box.

### `getProfiles(): array`

Возвращает компактный список профилей для `/admin/ai_profiles`.

### `getProfileById(int $profileId): ?array`

Возвращает профиль для карточки редактирования без раскрытия полного API-ключа.

### `getDefaultProfile(): array`

Возвращает структуру нового профиля по умолчанию.

### `getProviderSettingsContext(string $provider, array $profile = []): array`

Готовит provider-specific данные для AJAX-панели настроек.

### `saveProfile(array $input, int $profileId = 0): int`

Создаёт или обновляет профиль, валидирует `profile_code`, `api_base_url`, `provider_settings` и шифрует новый API-ключ.

### `searchProviderModels(string $provider, int $profileId = 0, string $query = '', int $limit = 50): array`

Возвращает нормализованный список моделей для поля поиска. Использует каталог провайдера, кеш `cache/ai/` и fallback-списки.

### `testConnection(int $profileId): array`

Проверяет сохранённый профиль через models endpoint провайдера и сохраняет `last_test_*` поля.

### `getProfileStats(): array`

Возвращает базовую статистику для `/admin/ai_statistics`: всего профилей, включённые, выключенные и с сохранённым ключом.

## ContentApiService

`ContentApiService` используется административным `/api/v1` и должен вызываться только после проверки admin API key.

### `ContentApiService::getEntity(string $entityType, int $entityId, string $languageCode = ''): OperationResult`

Читает категорию или страницу вместе с `properties`.

### `ContentApiService::createEntity(string $entityType, array $payload): OperationResult`

Создаёт категорию или страницу и затем сохраняет переданные `properties`.

### `ContentApiService::updateEntity(string $entityType, int $entityId, array $payload): OperationResult`

Обновляет core-поля сущности и значения её свойств.

### `ContentApiService::getEntitySchema(string $entityType, array $context = []): OperationResult`

Возвращает schema/template для API-создания:

- для `page` нужен `category_id`;
- для `category` нужен `type_id`;
- в ответе приходят `entity_fields`, `entity_defaults` и `properties`.

Схема свойств возвращает field `uid`, `type`, default/value и метаданные ввода. Для `date-range` значение имеет структурный формат:

```json
{
  "from": "01.06",
  "to": "15.06"
}
```

В repeatable-свойствах этот объект повторяется массивом по индексам элементов. `date-range` участвует в поисковом индексе, но не является generic materialized filter type: фильтрация по пересечению дат должна описываться отдельной прикладной логикой.

Для `repeatable-group` field schema содержит дочерний массив `fields`, а значение хранит строки группы:

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
    }
  ]
}
```

Внутри repeatable/composite-свойства `repeatable-group.value` становится двумерным массивом: внешний уровень — индекс основного элемента, внутренний — строки группы. Этот тип searchable, но не является `ee_filters` field type; nested-фильтрация по датам/ценам описывается отдельно от generic filters.

Security-ожидания для `/api/v1`:

- активный admin API key;
- throttling read/write запросов;
- structured audit log для read/create/update операций;
- JSON-ошибка без raw exception dump.

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
- `ENV_AUTH_MAX_ACTIVE_SESSIONS_PER_USER`
- `ENV_CUSTOM_PATH`
- `ENV_DEF_LANG`
- `ENV_LEGAL_OPERATOR_STATUS`
- `ENV_LEGAL_OPERATOR_NAME`
- `ENV_LEGAL_OPERATOR_ADDRESS`
- `ENV_LEGAL_OPERATOR_INN`
- `ENV_LEGAL_OPERATOR_OGRN`
- `ENV_LEGAL_PRIVACY_POLICY_VERSION`
- `ENV_LEGAL_PERSONAL_DATA_CONSENT_VERSION`
- `ENV_LEGAL_PERSONAL_DATA_DISTRIBUTION_CONSENT_VERSION`
- `ENV_SECRET_KEY`

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
