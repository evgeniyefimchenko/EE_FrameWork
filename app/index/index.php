<?php

use classes\system\ControllerBase;
use classes\system\SysClass;
use classes\system\Session;
use classes\system\AuthService;
use classes\system\AuthSessionService;
use classes\helpers\ClassMail;
use classes\system\Hook;

/**
 * Класс контроллера главной страницы сайта
 */
class ControllerIndex Extends ControllerBase {

    /**
     * Загрузка стандартных представлений
     */
    private function getStandardViews() {
        Hook::run('C_beforeGetStandardViews', $this->view);
        $this->view->set('logged_in', $this->logged_in);
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/index.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->getPathController() . '/css/index.css"/>';
        Hook::run('C_afterGetStandardViews', $this->view);
    }

    /**
     * Главная страница проекта
     */
    public function index($params = NULL) {
        if ($params) {
            SysClass::handleRedirect();
        }
        /* view */
        $this->getStandardViews();
        $this->view->set('top_panel', $this->view->read('v_top_panel', false));
        $this->html = $this->view->read('v_index');
        /* layouts */
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - General page';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION;
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->parameters_layout["layout_content"] = $this->html;
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Документация
     * @param NULL $params
     */
    public function docs($params = NULL) {
        $controllerFile = ENV_SITE_PATH . 'app' . ENV_DIRSEP . 'docs' . ENV_DIRSEP . 'index.php';
        if (is_readable($controllerFile)) {
            require_once $controllerFile;
            $controller = new \ControllerDocs();
            $controller->index(is_array($params) ? $params : []);
            return;
        }
        SysClass::handleRedirect(404);
    }
    
    /**
     * AJAX получение страниц документации
     * @param type $params
     */
    public function get_doc($params = NULL) {
        unset($params);
        SysClass::handleRedirect(301, '/docs');
    }
    
    /**
     * О нас
     */
    public function about($params = NULL) {
        if ($params) {
            SysClass::handleRedirect();
        }
        /* view */
        $this->getStandardViews();
        $this->html = $this->view->read('v_about');
        /* layouts */
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - About Us';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION;
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->parameters_layout["layout_content"] = $this->html;
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Контакты
     */
    public function contact($params = NULL) {
        if ($params) {
            SysClass::handleRedirect();
        }
        /* view */
        $this->getStandardViews();
        $this->html = $this->view->read('v_contact');
        /* layouts */
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - Contact';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION;
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->parameters_layout["layout_content"] = $this->html;
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Покажет форму Авторизации/Регистрации
     * Если передать GET параметр return то после действия с формой
     * произойдет возврат на указанную страницу
     * site.ru/show_login_form?return='help' вернёт на site.ru/help
     */
    public function show_login_form($params = null) {
        if ($params) {
            SysClass::handleRedirect();
        }
        $this->users->getAdminProfile(); // Если профиля админа не существует то он будет создан test@test.com admin
        /* view */
        $this->getStandardViews();
        if ($this->view->get('new_user') || !$this->logged_in) {
            $this->html = $this->view->read('v_login_form');
        } else {
            /* Уже авторизован */
            SysClass::handleRedirect(200, '/admin');
        }
        /* layouts */
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/js/plugins/validator.min.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/login-register.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script>$(document).ready(function () {openLoginModal();});</script>';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->getPathController() . '/css/login-register.css"/>';
        $this->parameters_layout["title"] = ENV_SITE_NAME;
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - Login/Registration Form';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->parameters_layout["layout"] = 'login_form';
        $this->parameters_layout["layout_content"] = $this->html;
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Авторизация пользователя AJAX
     */
    public function login($params = null) {
        if ($params || !SysClass::isAjaxRequestFromSameSite()) {
            die(json_encode(array('error' => 'it`s a lie')));
        }
        if (!SysClass::checkDatabaseConnection()) {
            $json['error'] = $this->lang['sys.no_connection_to_db'];
            die(json_encode($json, JSON_UNESCAPED_UNICODE));
        }
        $json['error'] = '';
        $postData = SysClass::ee_cleanArray($_POST);
        $email = trim($postData['email']);
        $pass = trim($postData['password']);
        if (!SysClass::validEmail($email)) {
            $json['error'] = $this->lang['sys.invalid_mail_format'];
            die(json_encode($json, JSON_UNESCAPED_UNICODE));
        }
        $json['status'] = '';
        $json['message'] = '';
        $json['redirect'] = '';
        $json['error'] = $this->users->confirmUser($email, $pass);
        $status = (string) ($this->users->lastAuthResult['status'] ?? '');
        $json['status'] = $status;
        if ($status === 'success') {
            $json['error'] = '';
            $json['message'] = $this->lang['sys.welcome'] . '!';
            $json['redirect'] = '/admin';
        } elseif ($status === 'password_setup_required') {
            $json['error'] = '';
            $json['message'] = $this->lang['sys.password_setup_link_sent'];
        }
        die(json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    public function logout($params = null) {
        $this->exit_login($params);
    }

    /**
     * Выход пользователя
     */
    public function exit_login($params = null) {
        AuthSessionService::revokeCurrentSession();
        SysClass::handleRedirect(301, '/');
    }

    /**
     * Регистрация пользователя после заполнения формы AJAX
     */
    public function register($params = null) {
        if ($params || !SysClass::isAjaxRequestFromSameSite()) {
            die(json_encode(array('error' => 'it`s a lie')));
        }
        $json = [];
        $json['error'] = '';
        $json['message'] = '';
        $json['status'] = '';
        $json['redirect'] = '';
        $postData = SysClass::ee_cleanArray($_POST);
        $email = trim($postData['email']);
        $pass = trim($postData['password']);
        $conf_pass = trim($postData['password_confirmation']);
        if (!SysClass::validEmail($email)) {
            $json['error'] .= $this->lang['sys.invalid_mail_format'];
        }
        if ($pass !== $conf_pass) {
            $json['error'] .= $this->lang['sys.password_mismatch'];
        }
        if (!$json['error'] && mb_strlen($pass) < 5) {
            $json['error'] .= $this->lang['sys.password_too_short'];
        }
        if (!$json['error']) {
            $result = (new AuthService())->registerLocalUser($email, $pass);
            $json['status'] = (string) ($result['status'] ?? '');
            switch ($json['status']) {
                case 'email_taken':
                    $json['error'] = $this->lang['sys.the_mail_is_already_busy'];
                    break;
                case 'invalid_email':
                    $json['error'] = $this->lang['sys.invalid_mail_format'];
                    break;
                case 'registered_pending_activation':
                    $json['message'] = $this->lang['sys.verify_email'];
                    break;
                case 'registered_activation_mail_failed':
                    $json['error'] = $this->lang['sys.email_sending_error'];
                    break;
                case 'registered_active':
                    $json['message'] = $this->lang['sys.welcome'] . '!';
                    $json['redirect'] = '/admin';
                    break;
                default:
                    $json['error'] = $this->lang['sys.db_registration_error'];
                    break;
            }
        }
        $json['error'] = $json['error'] ? $json['error'] : '';
        if ($json['error'] != '') {
            SysClass::preFile('index_error', 'register', 'Ошибка регистрации', ['error' => $json['error'], 'email' => $email]);
        }
        echo json_encode($json, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Активация пользователя по ссылке из письма
     * Проверяет код активации, активирует пользователя и выполняет автологин при успехе
     * @param array $params Массив параметров, где $params[0] — код активации
     */
    public function activation($params = []): void {
        $token = trim((string) ($params[0] ?? ''));
        if ($token === '') {
            $this->renderAuthMessagePage($this->lang['sys.authorization'], $this->lang['sys.activation_error'], ENV_URL_SITE);
            return;
        }

        $result = (new AuthService())->activateByToken($token);
        if (($result['status'] ?? '') === 'activation_completed') {
            $userId = (int) ($result['user_id'] ?? 0);
            if ($userId > 0) {
                AuthSessionService::establishSession($userId);
                $email = $this->users->getUserEmail($userId);
                if ($email) {
                    ClassMail::sendMail($email, '', 'account_activated');
                }
            }
            $this->renderAuthMessagePage($this->lang['sys.authorization'], $this->lang['sys.successfully_activated'], ENV_URL_SITE . '/admin');
            return;
        }

        $message = ($result['status'] ?? '') === 'activation_not_modified'
            ? $this->lang['sys.account_is_already_active']
            : $this->lang['sys.activation_error'];
        $this->renderAuthMessagePage($this->lang['sys.authorization'], $message, ENV_URL_SITE . '/show_login_form');
    }

    /**
     * Восстановление пароля AJAX
     */
    public function recovery_password($params = []) {
        $authService = new AuthService();
        if (($params[0] ?? '') === 'confirm') {
            $token = trim((string) ($params[1] ?? ''));
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $postData = SysClass::ee_cleanArray($_POST);
                $password = trim((string) ($postData['password'] ?? ''));
                $passwordConfirmation = trim((string) ($postData['password_confirmation'] ?? ''));
                if ($password !== $passwordConfirmation) {
                    $this->renderAuthPasswordForm(
                        $this->lang['sys.restore_password_process'],
                        $this->lang['sys.set_new_password'],
                        ENV_URL_SITE . '/recovery_password/confirm/' . $token,
                        $token,
                        $this->lang['sys.restore_password'],
                        $this->lang['sys.password_mismatch']
                    );
                    return;
                }
                if (mb_strlen($password) < 5) {
                    $this->renderAuthPasswordForm(
                        $this->lang['sys.restore_password_process'],
                        $this->lang['sys.set_new_password'],
                        ENV_URL_SITE . '/recovery_password/confirm/' . $token,
                        $token,
                        $this->lang['sys.restore_password'],
                        $this->lang['sys.password_too_short']
                    );
                    return;
                }
                $result = $authService->confirmPasswordRecovery($token, $password, true);
                if (($result['status'] ?? '') === 'password_recovery_completed') {
                    $this->renderAuthMessagePage($this->lang['sys.restore_password_process'], $this->lang['sys.password_changed_successfully'], ENV_URL_SITE . '/admin');
                    return;
                }
                $this->renderAuthPasswordForm(
                    $this->lang['sys.restore_password_process'],
                    $this->lang['sys.set_new_password'],
                    ENV_URL_SITE . '/recovery_password/confirm/' . $token,
                    $token,
                    $this->lang['sys.restore_password'],
                    $this->lang['sys.challenge_invalid']
                );
                return;
            }

            $this->renderAuthPasswordForm(
                $this->lang['sys.restore_password_process'],
                $this->lang['sys.set_new_password'],
                ENV_URL_SITE . '/recovery_password/confirm/' . $token,
                $token,
                $this->lang['sys.restore_password']
            );
            return;
        }

        if (!SysClass::isAjaxRequestFromSameSite()) {
            SysClass::handleRedirect(200, '/show_login_form');
            return;
        }
        $json = ['error' => '', 'message' => '', 'status' => ''];
        $postData = SysClass::ee_cleanArray($_POST);
        $email = trim((string) ($postData['email'] ?? ''));
        if (!SysClass::validEmail($email)) {
            $json['error'] = $this->lang['sys.invalid_mail_format'];
            die(json_encode($json, JSON_UNESCAPED_UNICODE));
        }
        $result = $authService->requestPasswordRecovery($email);
        $json['status'] = (string) ($result['status'] ?? '');
        if ($json['status'] === 'recovery_mail_failed') {
            $json['error'] = $this->lang['sys.email_sending_error'];
        } else {
            $json['message'] = $this->lang['sys.recovery_link_sent'];
        }
        die(json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    public function password_setup($params = []) {
        $authService = new AuthService();
        if (($params[0] ?? '') === 'request') {
            if (!SysClass::isAjaxRequestFromSameSite()) {
                die(json_encode(array('error' => 'it`s a lie')));
            }
            $postData = SysClass::ee_cleanArray($_POST);
            $email = trim((string) ($postData['email'] ?? ''));
            $result = $authService->requestPasswordSetup($email, 'manual_request');
            $json = [
                'error' => ($result['status'] ?? '') === 'password_setup_mail_failed' ? $this->lang['sys.password_setup_mail_failed'] : '',
                'message' => ($result['status'] ?? '') === 'password_setup_mail_failed' ? '' : $this->lang['sys.password_setup_link_sent'],
                'status' => (string) ($result['status'] ?? ''),
            ];
            die(json_encode($json, JSON_UNESCAPED_UNICODE));
        }

        if (($params[0] ?? '') !== 'confirm') {
            SysClass::handleRedirect(200, '/show_login_form');
            return;
        }

        $token = trim((string) ($params[1] ?? ''));
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $postData = SysClass::ee_cleanArray($_POST);
            $password = trim((string) ($postData['password'] ?? ''));
            $passwordConfirmation = trim((string) ($postData['password_confirmation'] ?? ''));
            if ($password !== $passwordConfirmation) {
                $this->renderAuthPasswordForm(
                    $this->lang['sys.password_setup'],
                    $this->lang['sys.password_setup_intro'],
                    ENV_URL_SITE . '/password_setup/confirm/' . $token,
                    $token,
                    $this->lang['sys.save_password'],
                    $this->lang['sys.password_mismatch']
                );
                return;
            }
            if (mb_strlen($password) < 5) {
                $this->renderAuthPasswordForm(
                    $this->lang['sys.password_setup'],
                    $this->lang['sys.password_setup_intro'],
                    ENV_URL_SITE . '/password_setup/confirm/' . $token,
                    $token,
                    $this->lang['sys.save_password'],
                    $this->lang['sys.password_too_short']
                );
                return;
            }
            $result = $authService->confirmPasswordSetup($token, $password, true);
            if (($result['status'] ?? '') === 'password_setup_completed') {
                $this->renderAuthMessagePage($this->lang['sys.password_setup'], $this->lang['sys.password_setup_completed'], ENV_URL_SITE . '/admin');
                return;
            }
            $this->renderAuthPasswordForm(
                $this->lang['sys.password_setup'],
                $this->lang['sys.password_setup_intro'],
                ENV_URL_SITE . '/password_setup/confirm/' . $token,
                $token,
                $this->lang['sys.save_password'],
                $this->lang['sys.challenge_invalid']
            );
            return;
        }

        $this->renderAuthPasswordForm(
            $this->lang['sys.password_setup'],
            $this->lang['sys.password_setup_intro'],
            ENV_URL_SITE . '/password_setup/confirm/' . $token,
            $token,
            $this->lang['sys.save_password']
        );
    }

    public function auth($params = []) {
        $authService = new AuthService();
        $first = strtolower(trim((string) ($params[0] ?? '')));
        if ($first === 'link') {
            $token = trim((string) ($params[1] ?? ''));
            $result = $authService->confirmAccountLink($token, true);
            $message = ($result['status'] ?? '') === 'account_link_completed'
                ? $this->lang['sys.account_link_completed']
                : $this->lang['sys.challenge_invalid'];
            $redirect = ($result['status'] ?? '') === 'account_link_completed'
                ? ENV_URL_SITE . '/admin'
                : ENV_URL_SITE . '/show_login_form';
            $this->renderAuthMessagePage($this->lang['sys.authorization'], $message, $redirect);
            return;
        }

        $provider = $first;
        $providerAction = strtolower(trim((string) ($params[1] ?? '')));
        if ($provider === '' || $providerAction === '') {
            SysClass::handleRedirect(200, '/show_login_form');
            return;
        }

        if ($providerAction === 'start') {
            $result = $authService->startProviderAuth($provider);
            if (($result['status'] ?? '') === 'redirect' && !empty($result['redirect_url'])) {
                header('Location: ' . $result['redirect_url'], true, 302);
                exit();
            }
            $this->renderAuthMessagePage($this->lang['sys.authorization'], $this->lang['sys.provider_not_configured'], ENV_URL_SITE . '/show_login_form');
            return;
        }

        if ($providerAction !== 'callback') {
            SysClass::handleRedirect(200, '/show_login_form');
            return;
        }

        $result = $authService->handleProviderCallback($provider, $_GET);
        $status = (string) ($result['status'] ?? '');
        if ($status === 'success') {
            SysClass::handleRedirect(302, '/admin');
            return;
        }

        $message = match ($status) {
            'account_link_email_sent' => $this->lang['sys.account_link_email_sent'],
            'registered_pending_activation' => $this->lang['sys.verify_email'],
            'deleted' => $this->lang['sys.account_deleted'],
            'blocked' => $this->lang['sys.account_is_blocked'],
            'provider_profile_incomplete' => $this->lang['sys.provider_profile_incomplete'],
            'provider_state_invalid' => $this->lang['sys.provider_state_invalid'],
            'provider_not_configured' => $this->lang['sys.provider_not_configured'],
            'account_link_mail_failed' => $this->lang['sys.email_sending_error'],
            default => $this->lang['sys.provider_auth_failed'],
        };
        $this->renderAuthMessagePage($this->lang['sys.authorization'], $message, ENV_URL_SITE . '/show_login_form');
    }

    /**
     * Преключение языка AJAX
     * И обновление всех параметров пользователя, если есть авторизация
     */
    public function set_options($params = []) {
        $allExistingLanguages = classes\system\Lang::getLangFilesWithoutExtension();
        if (count($params) > 1 || count($params) == 0 || !SysClass::isAjaxRequestFromSameSite() || !in_array(strtoupper($params[0]), $allExistingLanguages)) {
            die(json_encode(array('error' => 'it`s a lie')));
        }
        $postData = SysClass::ee_cleanArray($_POST);
        if ($this->logged_in) {
            $this->access = [classes\system\Constants::ALL_AUTH];
            if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
                die(json_encode(array('error' => 'access denieded')));
            }                          
            Session::set('lang', $params[0]);
            $postData['localize'] = $params[0];
            $this->loadModel('m_index');
            $user_data = $this->users->data;
            foreach ($postData as $key => $value) {
                if (array_key_exists($key, $user_data['options'])) {
                    $user_data['options'][$key] = $value;
                }
            }
            $this->users->setUserOptions($this->logged_in, $user_data['options']);
        } else {
            Session::set('lang', $params[0]);
        }
        die(json_encode(array('error' => 'no')));
    }

    private function renderAuthMessagePage(string $title, string $message, string $redirectUrl = '', int $refreshSeconds = 7): void {
        $this->getStandardViews();
        $redirectMeta = $redirectUrl !== '' ? ' <meta http-equiv="refresh" content="' . (int) $refreshSeconds . ';URL=' . htmlspecialchars($redirectUrl, ENT_QUOTES) . '">' : '';
        $this->html = '<div class="container py-5"><div class="row justify-content-center"><div class="col-lg-7">'
                . '<div class="alert alert-info shadow-sm">' . $message . '</div></div></div></div>' . $redirectMeta;
        $this->parameters_layout["layout"] = 'login_form';
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["title"] = $title;
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION;
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->getPathController() . '/css/login-register.css"/>';
        $this->showLayout($this->parameters_layout);
    }

    private function renderAuthPasswordForm(string $title, string $intro, string $actionUrl, string $token, string $submitLabel, string $error = ''): void {
        $this->getStandardViews();
        $this->view->set('auth_form_title', $title);
        $this->view->set('auth_form_intro', $intro);
        $this->view->set('auth_form_action', $actionUrl);
        $this->view->set('auth_form_token', $token);
        $this->view->set('auth_form_submit', $submitLabel);
        $this->view->set('auth_form_error', $error);
        $this->html = $this->view->read('v_auth_password_form');
        $this->parameters_layout["layout"] = 'login_form';
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["title"] = $title;
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION;
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->getPathController() . '/css/login-register.css"/>';
        $this->showLayout($this->parameters_layout);
    }
    
}
