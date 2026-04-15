<?php

namespace classes\system;

use classes\plugins\SafeMySQL;
use classes\helpers\ClassNotifications;

/**
 * Базовый абстрактный класс для всех контроллеров проекта
 */
abstract class ControllerBase {

    /**
     * Начальная страница для каждого контроллера обязательна
     * необходимо обрабатывать параметры для исключения дублей главной страницы
     */
    abstract function index($param = []);

    protected $lang = []; // Языковые переменные

    /**
     * Массив содержащий ID ролей пользователей имеющих доступ к странице
     * инициализируется внутри каждой функции контроллера
     * 100 - все зарегистрированные пользователи
     * Если не указать доступ в контроллере то будет запрещено загружать любую можель
     */
    protected $access = [];

    /**
     * Содержит id пользователя если он авторизован
     */
    protected $logged_in;

    /**
     * Массив с подключенными моделями
     */
    protected $models = [];

    /**
     * Класс PHP хуков     
     */
    protected $hooks = [];

    /**
     * Экземпляр класса для работы с представлениями
     */
    protected $view;

    /**
     * Экземпляр класса для работы с пользователями
     */
    protected $users;

    /**
     * Собирает весь HTML код для вывода на страницу
     */
    protected $html;

    /**
     * Содержит начальные папраметры макета(layout) определённого в контроллере
     */
    protected $parameters_layout = ['description' => '', 'keywords' => '', 'add_script' => '',
        'add_style' => '', 'canonical_href' => ENV_URL_SITE, 'layout' => 'index', 'imagePage' => '/favicon.png',
        'json_ld' => '', 'alternate_hreflang' => []];
    
    /**
     * Конструктор класса принимает экземпляр класса представления из /classes/system/Router.php
     * Проверяет сессию пользователя и записывает id в logged_in
     * @param mixed $view Экземпляр класса представления
     */
    function __construct($view = \null) {
        SysClass::checkInstall();
        $this->view = $view instanceof View ? $view : new View();
        try {
            $this->logged_in = AuthSessionService::resolveCurrentUserId();
        } catch (\RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'Authentication infrastructure is not installed')) {
                $this->logged_in = false;
                AuthSessionService::clearTransportState();
            } else {
                throw $exception;
            }
        }
        if ($this->logged_in) {
            $this->logged_in = $this->logged_in ?? false;
        } else {
            AuthSessionService::clearTransportState();
        }
        $this->users = new Users($this->logged_in);
        $userData = $this->users->data;
        $this->guardCustomAuthRoute($userData);
        $this->guardRequiredLegalConsents($userData);
        // Прогрузка пользовательских данных в представления и языковой массив        
        $this->setUserData($userData);
    }

    private function guardCustomAuthRoute(array $userData): void {
        if (empty($this->logged_in)) {
            return;
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $requestPath = (string) (parse_url($requestUri, PHP_URL_PATH) ?? '/');
        $requestPath = $requestPath !== '' ? $requestPath : '/';
        if ($requestPath[0] !== '/') {
            $requestPath = '/' . ltrim($requestPath, '/');
        }

        $segments = array_values(array_filter(explode('/', trim($requestPath, '/')), static fn(string $value): bool => $value !== ''));
        $requestArea = strtolower((string) ($segments[0] ?? ''));
        $context = [
            'controller' => get_class($this),
            'user_id' => (int) $this->logged_in,
            'user_role' => (int) ($userData['user_role'] ?? 0),
            'request_uri' => $requestUri,
            'request_path' => $requestPath,
            'request_area' => $requestArea,
            'is_ajax' => SysClass::isAjaxRequestFromSameSite(),
        ];

        $decision = Hook::until('auth.route_guard', null, $context);
        if ($decision === null || $decision === false) {
            return;
        }

        if (is_string($decision)) {
            $decision = ['redirect' => $decision];
        }
        if (!is_array($decision)) {
            return;
        }

        $redirect = trim((string) ($decision['redirect'] ?? ''));
        if ($redirect === '') {
            return;
        }

        $redirectPath = (string) (parse_url($redirect, PHP_URL_PATH) ?? $redirect);
        if ($redirectPath !== '' && $redirectPath[0] !== '/') {
            $redirectPath = '/' . ltrim($redirectPath, '/');
        }
        if ($redirectPath === $requestPath) {
            return;
        }

        if (!empty($context['is_ajax'])) {
            http_response_code((int) ($decision['ajax_http_code'] ?? 403));
            echo json_encode([
                'error' => 'access_denied',
                'status' => (string) ($decision['status'] ?? 'contour_redirect'),
                'redirect' => $redirect,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }

        SysClass::handleRedirect((int) ($decision['http_code'] ?? 302), $redirect);
        exit();
    }

    private function guardRequiredLegalConsents(array $userData): void {
        if (get_class($this) !== 'ControllerAdmin' || empty($this->logged_in)) {
            return;
        }
        $impersonationState = AuthSessionService::getImpersonationState();
        if (!empty($impersonationState['active'])) {
            return;
        }
        if (LegalConsentService::hasRequiredConsents($userData)) {
            return;
        }

        $currentPath = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        if ($currentPath === 'required_consents' || $currentPath === 'logout' || $currentPath === 'exit_login') {
            return;
        }

        $redirect = '/required_consents?return=' . rawurlencode($currentPath !== '' ? $currentPath : 'admin');
        if (SysClass::isAjaxRequestFromSameSite()) {
            http_response_code(428);
            echo json_encode([
                'error' => 'legal_consents_required',
                'status' => 'legal_consents_required',
                'redirect' => $redirect,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }

        SysClass::handleRedirect(200, $redirect);
        exit();
    }

    /**
     * Загрузит в представление данные пользователя
     * И языковой массив
     * @param $userData - Данные пользователя для загрузки
     */
    private function setUserData($userData) {
        $getLangCode = ''; // Для логирования
        $availableLangs = ee_get_interface_lang_codes();
        $normalizeLangCode = static function ($value) use ($availableLangs): string {
            $candidate = ee_normalize_lang_code((string) $value);
            if ($candidate === '' || !in_array($candidate, $availableLangs, true)) {
                return '';
            }
            return $candidate;
        };
        $requestedUiLangCode = $normalizeLangCode((string) ($_GET['ui_lang'] ?? ''));
        $sessionLangCode = $normalizeLangCode(Session::get('lang'));
        $sessionLangManual = (int) (Session::get('lang_manual') ?? 0) === 1;
        $detectedLangCode = ee_detect_interface_lang_code();

        if ($requestedUiLangCode !== '') {
            $sessionLangCode = $requestedUiLangCode;
            $sessionLangManual = true;
            Session::set('lang', $requestedUiLangCode);
            Session::set('lang_manual', 1);
            if (!empty($this->logged_in) && !empty($userData['options']) && is_array($userData['options'])) {
                $storedLangCode = $normalizeLangCode($userData['options']['localize'] ?? '');
                if ($storedLangCode !== $requestedUiLangCode) {
                    $userData['options']['localize'] = $requestedUiLangCode;
                    $this->users->setUserOptions((int) $this->logged_in, $userData['options']);
                }
            }
        }

        if (empty($userData['new_user'])) { // Разегистрированный пользователь
            $notifications = !empty($this->logged_in) ? ClassNotifications::getNotificationsUser((int) $this->logged_in, 20) : [];
            $userData['notifications'] = $notifications;
            $this->view->set('notifications', $notifications);
            $this->view->set('messages', is_array($userData['messages'] ?? null) ? $userData['messages'] : []);
            $this->view->set('count_unread_messages', (int) ($userData['count_unread_messages'] ?? 0));
            $this->view->set('count_messages', (int) ($userData['count_messages'] ?? 0));
            $this->view->set('userData', $userData);
            $storedLangCode = $normalizeLangCode($userData['options']['localize'] ?? '');
            if ($sessionLangManual && $sessionLangCode !== '') {
                $getLangCode = 'user_data Session override';
                $langCode = $sessionLangCode;
                if (($storedLangCode === '' || $storedLangCode !== $langCode) && !empty($this->logged_in)) {
                    $userData['options']['localize'] = $langCode;
                    $this->users->setUserOptions($this->logged_in, $userData['options']);
                }
            } elseif ($storedLangCode !== '') { // Проверка на наличие локали в настройках пользователя
                $getLangCode = 'user_data options localize';
                $langCode = $storedLangCode;
            } else { // Записываем локаль в опции пользователя
                $getLangCode = 'user_data detected fallback';
                $langCode = $detectedLangCode;
                $userData['options']['localize'] = $langCode;
                $this->users->setUserOptions($this->logged_in, $userData['options']);
            }
        } else { // Новый пользователь
            $this->view->set('notifications', []);
            $this->view->set('messages', []);
            $this->view->set('count_unread_messages', 0);
            $this->view->set('count_messages', 0);
            $langCode = ($sessionLangManual && $sessionLangCode !== '') ? $sessionLangCode : $detectedLangCode;
            $getLangCode = 'New user ' . $langCode . ($sessionLangManual ? ' manual' : ' auto');
            Session::set('lang', $langCode);
            Session::set('lang_manual', $sessionLangManual ? 1 : 0);
        }
        $this->lang = Lang::init($langCode);
        $langCode = Lang::getCurrentLangCode() ?: $langCode;
        Session::set('lang', $langCode);
        SysClass::checkLangVars($langCode, $this->lang);
        $this->view->set('lang', $this->lang);
        $this->users->lang = $this->lang;
    }

    protected function getAdminUiLanguageCode(): string {
        return ee_get_default_interface_lang_code(
            (string) (Session::get('lang') ?: ($this->users->data['options']['localize'] ?? ENV_DEF_LANG))
        );
    }

    protected function applyInterfaceLanguage(string $langCode, bool $persist = true): string {
        $langCode = ee_get_default_interface_lang_code($langCode);

        Session::set('lang', $langCode);
        Session::set('lang_manual', $persist ? 1 : 0);
        if ($persist && !empty($this->logged_in) && !empty($this->users->data['options']) && is_array($this->users->data['options'])) {
            $this->users->data['options']['localize'] = $langCode;
            $this->users->setUserOptions((int) $this->logged_in, $this->users->data['options']);
        }

        $this->lang = Lang::init($langCode);
        $langCode = Lang::getCurrentLangCode() ?: $langCode;
        Session::set('lang', $langCode);
        SysClass::checkLangVars($langCode, $this->lang);
        $this->view->set('lang', $this->lang, true);
        $this->users->lang = $this->lang;

        return $langCode;
    }

    protected function syncAdminUiLanguageFromRequest(string $queryKey = 'ui_lang'): string {
        $requestedLangCode = strtoupper(trim((string) ($_GET[$queryKey] ?? '')));
        if ($requestedLangCode === '') {
            return $this->getAdminUiLanguageCode();
        }

        return $this->applyInterfaceLanguage($requestedLangCode);
    }

    protected function getDefaultAuthorizedLandingUrl(int $userRole): string {
        $defaultUrl = match ((int) $userRole) {
            Constants::ADMIN,
            Constants::MODERATOR,
            Constants::SYSTEM => '/admin',
            Constants::MANAGER => '/manager',
            Constants::USER => '/user',
            default => '/',
        };
        return (string) Hook::filter('auth.landing_url', $defaultUrl, $userRole, 'core');
    }

    protected function getAuthorizedLandingUrlForUserId(int $userId): string {
        if ($userId <= 0) {
            return '/';
        }

        $userRole = (int) (SafeMySQL::gi()->getOne(
            'SELECT user_role FROM ?n WHERE user_id = ?i LIMIT 1',
            Constants::USERS_TABLE,
            $userId
        ) ?? 0);

        return $this->getDefaultAuthorizedLandingUrl($userRole);
    }

    protected function normalizeOperationResult(mixed $result, array $options = []): OperationResult {
        $defaultErrorMessage = trim((string) ($options['default_error_message'] ?? ($this->lang['sys.error'] ?? 'Ошибка выполнения операции')));
        $successMessage = trim((string) ($options['success_message'] ?? ''));

        return OperationResult::fromLegacy($result, [
            'false_message' => $defaultErrorMessage,
            'success_message' => $successMessage,
            'failure_code' => (string) ($options['failure_code'] ?? 'operation_failed'),
        ]);
    }

    protected function notifyOperationResult(mixed $result, array $options = []): OperationResult {
        $operationResult = $this->normalizeOperationResult($result, $options);
        $defaultErrorMessage = trim((string) ($options['default_error_message'] ?? ($this->lang['sys.error'] ?? 'Ошибка выполнения операции')));

        if ($operationResult->isSuccess()) {
            if (!($options['skip_success_notification'] ?? false)) {
                $successMessage = trim((string) ($options['success_message'] ?? $operationResult->getMessage('')));
                if ($successMessage !== '' && !empty($this->logged_in)) {
                    ClassNotifications::addNotificationUser($this->logged_in, [
                        'text' => $successMessage,
                        'status' => $options['success_status'] ?? 'success',
                    ]);
                }
            }
            return $operationResult;
        }

        if (!($options['skip_error_notification'] ?? false) && !empty($this->logged_in)) {
            $errorMessage = $operationResult->getMessage($defaultErrorMessage);
            if ($errorMessage !== '') {
                ClassNotifications::addNotificationUser($this->logged_in, [
                    'text' => $errorMessage,
                    'status' => $options['error_status'] ?? 'danger',
                ]);
            }
        }

        return $operationResult;
    }

    protected function canAccess(array $access = []): bool {
        return SysClass::getAccessUser($this->logged_in, $access);
    }

    /**
     * Единый guard для controller/trait методов.
     */
    protected function requireAccess(array $access = [], array $options = []): bool {
        $this->access = $access;
        $decision = SysClass::getAccessDecision($this->logged_in, $access);
        if (!empty($decision['allowed'])) {
            return true;
        }

        $status = (string) ($decision['status'] ?? 'forbidden');
        $currentPath = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $returnPath = trim((string) ($options['return'] ?? $currentPath), '/');
        $isAjax = array_key_exists('ajax', $options)
            ? (bool) $options['ajax']
            : SysClass::isAjaxRequestFromSameSite();

        Logger::warning('access_denied', 'Попытка доступа отклонена', [
            'user_id' => (int) ($this->logged_in ?? 0),
            'status' => $status,
            'access' => $access,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        ], [
            'initiator' => $options['initiator'] ?? __METHOD__,
            'details' => 'Access denied',
            'include_trace' => false,
        ]);

        if (in_array($status, ['deleted', 'blocked', 'inactive'], true)) {
            AuthSessionService::clearTransportState();
        }

        if ($isAjax) {
            http_response_code((int) ($options['ajax_http_code'] ?? 403));
            echo json_encode([
                'error' => 'access_denied',
                'status' => $status,
                'message' => $options['ajax_message'] ?? ($this->lang['sys.no_access'] ?? 'Access denied'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return false;
        }

        $redirect = trim((string) ($options['redirect'] ?? ''));
        if ($redirect === '') {
            if ($status === 'not_authenticated') {
                $redirect = '/show_login_form' . ($returnPath !== '' ? '?return=' . rawurlencode($returnPath) : '');
            } else {
                $redirect = (string) ($options['forbidden_redirect'] ?? '/');
            }
        }

        SysClass::handleRedirect(200, $redirect);
        return false;
    }

    protected function withCsrfUrl(string $url): string {
        return CsrfService::appendToUrl($url);
    }

    protected function csrfTokenForUrl(string $url): string {
        return CsrfService::tokenForUrl($url);
    }

    protected function requireCsrfRequest(array $options = []): bool {
        if (CsrfService::isValidForCurrentRequest()) {
            return true;
        }

        Logger::warning('csrf_blocked', 'CSRF validation failed for state-changing request', [
            'user_id' => (int) ($this->logged_in ?? 0),
            'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            'referer' => (string) ($_SERVER['HTTP_REFERER'] ?? ''),
        ], [
            'initiator' => $options['initiator'] ?? __METHOD__,
            'details' => 'CSRF token missing or invalid',
            'include_trace' => false,
        ]);

        if (!empty($this->logged_in)) {
            ClassNotifications::upsertSourceNotification((int) $this->logged_in, 'security', 1, [
                'text' => (string) ($options['message'] ?? ($this->lang['sys.security_action_expired'] ?? 'Проверка безопасности не пройдена. Повторите действие снова.')),
                'status' => 'warning',
                'url' => '/admin/health',
            ]);
        }

        $redirect = trim((string) ($options['redirect'] ?? ''));
        if ($redirect === '') {
            $redirect = (string) ($_SERVER['HTTP_REFERER'] ?? '/admin');
        }

        SysClass::handleRedirect(200, $redirect);
        return false;
    }

    /**
     * Загружает модель для контроллера
     * @param string $model Имя файла модели без расширения, например 'm_index'
     * @param array $arg Массив аргументов для передачи в конструктор модели
     * @param string $path Опциональный абсолютный путь к модели
     * @param bool $reload Определяет, нужно ли перезагружать модель, если она уже была загружена ранее
     * @throws Exception Если модель или класс модели не найдены
     */
    protected function loadModel(string $model, array $arg = [], string $path = '', bool $reload = false): void {
        if (count($this->access) == 0) {
            SysClass::pre('Не указаны права доступа на ' . $model, true);
        }
        // Определение пути к модели
        $parts = explode('_', substr($model, 2));
        $className = 'Model' . implode('', array_map('ucfirst', $parts));
        $modelPath = $path ? $path : dirname(ENV_CONTROLLER_PATH) . '/models/' . $className . '.php';
        if (!file_exists($modelPath)) {
            SysClass::pre('Модель не найдена: ' . $modelPath);
        }
        include_once($modelPath);
        if (!class_exists($className)) {
            SysClass::pre('Класс модели не найден: ' . $className);
        }
        if (!isset($this->models[$model]) || $reload) {
            $this->models[$model] = new $className($arg);
        }
    }

    /**
     * Обрабатывает и отображает макет, перемещая JavaScript скрипты после указанного комментария, за исключением скриптов между специальными маркерами
     * Эта функция загружает указанный файл макета, обрабатывает его содержимое и перемещает JavaScript скрипты
     * Все скрипты, кроме тех, что находятся между <!-- start of non-relocatable JS scripts --> и <!-- end of non-relocatable JS scripts -->,
     * будут перемещены после маркера <!-- ported scripts -->, удаляя при этом дубликаты скриптов
     * Функция также поддерживает сжатие HTML если установлена соответствующая настройка
     * @param array $param Параметры для настройки макета, включая имя макета и другие значения
     */
    protected function showLayout($param) {
        extract($param);
        $file = ENV_SITE_PATH . 'layouts/' . $layout . '.php';
        ob_start();
        if (file_exists($file)) {
            include_once $file;
        } else {
            $layout = $layout ? $layout : 'UNKNOWN';
            SysClass::pre('layout ' . $layout . ' не найден!');
        }
        $this->html = ob_get_contents();
        ob_end_clean();
        // Сохраняем не перемещаемые скрипты и заменяем их маркером
        $nonRelocatableScriptMarker = 'NON_RELOCATABLE_SCRIPTS';
        preg_match("/<!-- start of non-relocatable JS scripts -->(.*?)<!-- end of non-relocatable JS scripts -->/si", $this->html, $nonRelocatableScripts);
        if (!empty($nonRelocatableScripts[1])) {
            $this->html = str_replace($nonRelocatableScripts[0], $nonRelocatableScriptMarker, $this->html);
        }
        // Находим, удаляем дубликаты и убираем все остальные скрипты
        preg_match_all("/<script.*?<\/script>/si", $this->html, $scriptMatches);
        $scriptsFound = array_unique($scriptMatches[0]);
        $this->html = preg_replace("/<script.*?<\/script>/si", '', $this->html);
        // Вставляем скрипты обратно в HTML после <!-- ported scripts -->
        $scriptsString = implode("\n", $scriptsFound);
        $this->html = str_replace('<!-- ported scripts -->', "<!-- ported scripts -->\n" . $scriptsString, $this->html);
        if (!empty($nonRelocatableScripts[1])) {
            $this->html = str_replace($nonRelocatableScriptMarker, $nonRelocatableScripts[0], $this->html);
        }
        if (ENV_COMPRESS_HTML) {
            echo Sysclass::minifyHtml($this->html);
        } else {
            echo $this->html;
        }
    }

    /**
     * Вернёт URL путь к папке контроллера
     * @param bool $killApp Убрать /app из результата
     */
    public function getPathController($killApp = false): string {
        $stackDir = dirname(ENV_CONTROLLER_PATH);
        $result = ENV_URL_SITE . substr($stackDir, strpos($stackDir, ENV_DIRSEP . ENV_APP_DIRECTORY));
        if ($killApp) {
            return str_replace('/app', '', $result);
        }
        return $result;
    }

}
