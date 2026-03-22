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
        'json_ld' => ''];
    
    /**
     * Конструктор класса принимает экземпляр класса представления из /classes/system/Router.php
     * Проверяет сессию пользователя и записывает id в logged_in
     * @param mixed $view Экземпляр класса представления
     */
    function __construct($view = \null) {
        SysClass::checkInstall();
        $this->view = $view instanceof View ? $view : new View();
        $this->logged_in = AuthSessionService::resolveCurrentUserId();
        if ($this->logged_in) {
            $this->logged_in = $this->logged_in ?? false;
        } else {
            AuthSessionService::clearTransportState();
        }
        $this->users = new Users($this->logged_in);
        $userData = $this->users->data;
        // Прогрузка пользовательских данных в представления и языковой массив        
        $this->setUserData($userData);
    }

    /**
     * Загрузит в представление данные пользователя
     * И языковой массив
     * @param $userData - Данные пользователя для загрузки
     */
    private function setUserData($userData) {
        $getLangCode = ''; // Для логирования
        $availableLangs = array_map('strtoupper', Lang::getLangFilesWithoutExtension());
        $normalizeLangCode = static function ($value) use ($availableLangs): string {
            $value = strtoupper(trim((string)$value));
            return in_array($value, $availableLangs, true) ? $value : '';
        };
        $sessionLangCode = $normalizeLangCode(Session::get('lang'));
        if (empty($userData['new_user'])) { // Разегистрированный пользователь
            $notifications = !empty($this->logged_in) ? ClassNotifications::getNotificationsUser((int) $this->logged_in, 20) : [];
            $userData['notifications'] = $notifications;
            $this->view->set('notifications', $notifications);
            $this->view->set('messages', is_array($userData['messages'] ?? null) ? $userData['messages'] : []);
            $this->view->set('count_unread_messages', (int) ($userData['count_unread_messages'] ?? 0));
            $this->view->set('count_messages', (int) ($userData['count_messages'] ?? 0));
            $this->view->set('userData', $userData);
            $storedLangCode = $normalizeLangCode($userData['options']['localize'] ?? '');
            if ($sessionLangCode !== '') {
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
                $getLangCode = 'user_data ENV fallback';
                $langCode = $normalizeLangCode(ENV_DEF_LANG);
                if ($langCode === '') {
                    $langCode = strtoupper((string)ENV_PROTO_LANGUAGE);
                }
                $userData['options']['localize'] = $langCode;
                $this->users->setUserOptions($this->logged_in, $userData['options']);
            }
        } else { // Новый пользователь
            $this->view->set('notifications', []);
            $this->view->set('messages', []);
            $this->view->set('count_unread_messages', 0);
            $this->view->set('count_messages', 0);
            $langCode = $sessionLangCode !== '' ? $sessionLangCode : $normalizeLangCode(ENV_DEF_LANG);
            if ($langCode === '') {
                $langCode = strtoupper((string)ENV_PROTO_LANGUAGE);
            }
            $getLangCode = 'New user ' . $langCode;
            Session::set('lang', $langCode);
        }
        $this->lang = Lang::init($langCode);
        Session::set('lang', $langCode);
        SysClass::checkLangVars($langCode, $this->lang);
        $this->view->set('lang', $this->lang);
        $this->users->lang = $this->lang;
    }

    protected function getAdminUiLanguageCode(): string {
        $langCode = strtoupper(trim((string) (Session::get('lang') ?: ($this->users->data['options']['localize'] ?? ENV_DEF_LANG))));
        if ($langCode === '') {
            $langCode = strtoupper((string) ENV_DEF_LANG);
        }
        if ($langCode === '') {
            $langCode = strtoupper((string) ENV_PROTO_LANGUAGE);
        }
        return $langCode;
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
