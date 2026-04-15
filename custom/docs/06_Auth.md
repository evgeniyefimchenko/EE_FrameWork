# Аутентификация и права доступа

В EE_FrameWork аутентификация построена вокруг `Users` и нового auth-слоя, который разделяет:

- профиль пользователя;
- credential state;
- auth sessions;
- challenges;
- внешние identities.

## Главные классы

- `Users` — базовый доступ к данным пользователя и роли;
- `AuthService` — orchestration auth lifecycle;
- `AuthSessionService` — работа с сессиями;
- `AuthChallengeService` — reset/setup/activation flow;
- `AuthIdentityService` — внешние провайдеры;
- `GoogleAuthProvider` — первый provider-адаптер.

## Роли и константы

Для проверки доступа в admin-контроллерах используются константы из `Constants`.

Типовые:

- `Constants::ADMIN`
- `Constants::MODERATOR`
- `Constants::MANAGER`
- `Constants::USER`
- `Constants::SYSTEM`
- `Constants::ALL_AUTH`

## Стандарт проверки доступа в контроллере

```php
if (!$this->requireAccess([Constants::ADMIN, Constants::MODERATOR], [
    'return' => 'admin',
    'initiator' => __METHOD__,
])) {
    return;
}
```

Практическое правило:

- проверка доступа выполняется в начале action;
- модель не должна сама решать, можно ли пользователю открыть страницу админки;
- если нужен доступ на уровне данных, это уже отдельная валидация в модели;
- прямой вызов `SysClass::getAccessUser()` допустим только как low-level API, а не как основной стандарт controller-кода.

## Auth hooks для landing и contour routing

В ядре есть три hook key для маршрутизации авторизованных пользователей:

- `auth.landing_url`
- `auth.front_landing_url`
- `auth.route_guard`

Практически это означает:

- ядро знает только default policy и точки расширения;
- project-specific поведение регистрируется в `custom/hooks.php`;
- сам проект решает, кто должен попадать в `/admin`, `/manager` и `/user`.

### `auth.landing_url`

Используется для определения базового landing URL после авторизации во внутренних сценариях.

Типичный пример:

```php
ee_add_custom_hook('auth.landing_url', [\custom\ProjectAuthPolicy::class, 'filterLandingUrl'], 10);
```

### `auth.front_landing_url`

Используется публичными auth-flow:

- login на фронте;
- activation;
- password recovery;
- password setup;
- external provider callback;
- frontend account menu.

Это позволяет держать единый маршрутный контракт без копипасты редиректов по всему проекту.

### `auth.route_guard`

Это `Hook::until(...)`-hook, который получает контекст текущего запроса и может вернуть решение о принудительном redirect.

Типичный use case:

- менеджер не должен заходить в `/admin`;
- обычный пользователь не должен попадать в `/manager`;
- администратор может оставаться в `/admin`.

Пример:

```php
ee_add_custom_hook('auth.route_guard', [\custom\ProjectAuthPolicy::class, 'guardRoute'], 10);
```

Контекст guard-а обычно включает:

- `controller`
- `user_id`
- `user_role`
- `request_uri`
- `request_path`
- `request_area`
- `is_ajax`

А результат может вернуть:

- `redirect`
- `status`
- `http_code`
- `ajax_http_code`

Это позволяет реализовать contour isolation на уровне проекта, не вшивая project policy в ядро.

## Где лежит текущий пользователь

Контроллер получает пользователя через базовый runtime-контур.

Обычно в контроллере доступны:

- `$this->users`
- `$this->logged_in`

А у пользователя дальше есть:

- `user_id`
- `user_role`
- `options`
- счётчики сообщений и т.д.

## Что важно про auth transport

Конфиг транспорта авторизации теперь явный:

- `cookie`
- `php_session`

Оба транспорта должны вести себя одинаково по server-side semantics, а различаться только способом переноса токена.

## Что важно про soft delete и restore

Удаление пользователя — это soft delete, а не физическое удаление строки.

При soft delete система должна:

- ставить `deleted = 1`;
- отзывать auth sessions;
- запрещать логин и recovery;
- сохранять audit trail.

Restore:

- возвращает пользователя;
- не возвращает старые сессии;
- может требовать повторную установку пароля.

## Must set password

После миграции пользователей с других систем может использоваться режим:

- пользователь вводит любой пароль;
- система не логинит его напрямую;
- создаёт challenge на установку собственного пароля;
- после подтверждения и установки пароля аккаунт входит в normal flow.

Security-критичный источник истины для этого режима должен жить не только в JSON `options`, а в auth-слое.

## Ошибки доступа

Что делать в контроллере:

- для недоступной admin-страницы — redirect/403 по текущей политике;
- для битого маршрута — `error.php`;
- для ошибки auth lifecycle — логирование + понятное уведомление.

Что не делать:

- не скрывать ошибку доступа под пустую страницу;
- не молча продолжать сценарий после провала auth-операции;
- не держать важные auth-флаги только в `options`.

## Обязательные legal-consents

Для публичной регистрации и обязательного legal-gate в платформе теперь используются два отдельных согласия:

- `privacy_policy_accepted` — принятие Политики в отношении обработки персональных данных;
- `personal_data_consent_accepted` — согласие на обработку персональных данных.

Факт принятия хранится в `ee_users` вместе с metadata:

- дата и время принятия;
- IP-адрес;
- user-agent;
- версия документа.

Публичные документы доступны по маршрутам:

- `/privacy_policy`
- `/consent_personal_data`

Если пользователь уже существует в системе, но ещё не принял обязательные документы, admin-контур принудительно переводит его на `/required_consents` до завершения этого шага.

Для внешней авторизации используется отдельный pre-auth сценарий:

- `/auth_consent/provider/google`

Это не даёт создать нового пользователя через social login без обязательных согласий.
