<?php

use classes\system\ControllerBase;
use classes\system\SysClass;
use classes\system\Session;
use classes\system\AuthService;
use classes\system\AuthSessionService;
use classes\system\Constants;
use classes\system\EntityPublicUrlService;
use classes\system\LegalConsentService;
use classes\system\Logger;
use classes\helpers\ClassMail;
use classes\system\Hook;
use custom\legal\LegalDocumentService;

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
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . ($this->lang['sys.home'] ?? 'Home');
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION;
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->parameters_layout["layout_content"] = $this->html;
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Публичная документация в локальном allbriz-контуре отключена.
     */
    public function docs($params = NULL) {
        unset($params);
        SysClass::handleRedirect(404);
    }

    /**
     * Legacy-route документации тоже закрыт.
     */
    public function get_doc($params = NULL) {
        unset($params);
        SysClass::handleRedirect(404);
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
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . ($this->lang['sys.about_project'] ?? 'About project');
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
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . ($this->lang['sys.contacts'] ?? 'Contacts');
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION;
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->parameters_layout["layout_content"] = $this->html;
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Публичный вывод страницы или категории по semantic URL.
     */
    public function public_entity($params = null) {
        $params = is_array($params) ? array_values($params) : [];
        $entityType = (string) ($params[0] ?? '');
        $entityId = (int) ($params[1] ?? 0);
        $languageCode = (string) ($params[2] ?? ($_GET['sl'] ?? ''));

        $basePayload = EntityPublicUrlService::getEntityViewPayload($entityType, $entityId, $languageCode);
        if ($basePayload === null) {
            SysClass::handleRedirect(404);
            return;
        }

        $this->applyInterfaceLanguage((string) ($basePayload['language_code'] ?? ee_get_current_lang_code()), false);

        $this->access = [Constants::ALL_AUTH];
        $this->loadModel('m_public_catalog');

        $entityType = strtolower(trim($entityType));
        $payload = $entityType === 'category'
            ? $this->models['m_public_catalog']->getCategoryPayload($entityId, $languageCode)
            : $this->models['m_public_catalog']->getPagePayload($entityId, $languageCode);
        if ($payload === null) {
            SysClass::handleRedirect(404);
            return;
        }

        $this->getStandardViews();
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->getPathController() . '/css/public_catalog.css"/>';
        $this->view->set('top_panel', $this->view->read('v_top_panel', false));
        $this->view->set('publicCatalog', $payload);
        $this->html = $this->view->read(
            ($payload['view_type'] ?? '') === 'category'
                ? 'v_public_category'
                : 'v_public_page'
        );

        $entityTitle = trim((string) ($payload['title'] ?? ''));
        $metaDescription = trim((string) ($payload['meta_description'] ?? ''));
        $plainText = trim((string) ($payload['plain_text'] ?? ''));

        $this->parameters_layout["title"] = $entityTitle !== '' ? ($entityTitle . ' - ' . ENV_SITE_NAME) : ENV_SITE_NAME;
        $this->parameters_layout["description"] = $metaDescription !== '' ? $metaDescription : ENV_SITE_DESCRIPTION;
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($plainText !== '' ? $plainText : $entityTitle);
        $this->parameters_layout["canonical_href"] = (string) ($payload['canonical_url'] ?? ENV_URL_SITE);
        $this->parameters_layout["alternate_hreflang"] = (array) ($payload['alternate_links'] ?? []);
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
        $this->users->getAdminProfile(); // Если профиля администратора нет, он будет создан из bootstrap-конфига проекта
        /* view */
        $this->getStandardViews();
        if ($this->view->get('new_user') || !$this->logged_in) {
            $this->html = $this->view->read('v_login_form');
        } else {
            /* Уже авторизован */
            SysClass::handleRedirect(200, $this->getDefaultAuthorizedLandingUrl((int) ($this->users->data['user_role'] ?? 0)));
        }
        /* layouts */
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/js/plugins/validator.min.js" type="text/javascript"></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/login-register.js" type="text/javascript"></script>';
        $this->parameters_layout["add_script"] .= '<script>$(document).ready(function () { if (typeof openLoginModal === "function") { openLoginModal(); } });</script>';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->getPathController() . '/css/login-register.css"/>';
        $this->parameters_layout["title"] = ENV_SITE_NAME;
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - ' . (string) ($this->lang['sys.login_registration_form'] ?? 'Login / registration form');
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
        $authorizedUserId = (int) ($this->users->lastAuthResult['user_id'] ?? 0);
        $json['status'] = $status;
        if ($status === 'success') {
            $json['error'] = '';
            $json['message'] = $this->lang['sys.welcome'] . '!';
            $json['redirect'] = $this->getAuthorizedLandingUrlForUserId($authorizedUserId);
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
        $consentFlags = LegalConsentService::getSubmittedFlags($postData);
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
            $missingConsentKeys = LegalConsentService::getMissingRequiredKeys($postData);
            if ($missingConsentKeys !== []) {
                $messages = [];
                if (in_array('privacy_policy_accepted', $missingConsentKeys, true)) {
                    $messages[] = $this->lang['sys.privacy_policy_acceptance_required'] ?? 'Необходимо принять Политику в отношении обработки персональных данных.';
                }
                if (in_array('personal_data_consent_accepted', $missingConsentKeys, true)) {
                    $messages[] = $this->lang['sys.personal_data_consent_required'] ?? 'Необходимо дать согласие на обработку персональных данных.';
                }
                $json['error'] .= implode(' ', $messages);
            }
        }
        if (!$json['error']) {
            $result = (new AuthService())->registerLocalUser($email, $pass, $consentFlags);
            $json['status'] = (string) ($result['status'] ?? '');
            switch ($json['status']) {
                case 'email_taken':
                    $json['error'] = $this->lang['sys.the_mail_is_already_busy'];
                    break;
                case 'invalid_email':
                    $json['error'] = $this->lang['sys.invalid_mail_format'];
                    break;
                case 'consent_required':
                    $json['error'] = $this->lang['sys.required_consents_missing'] ?? 'Для регистрации необходимо принять обязательные документы.';
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
            $status = (string) ($json['status'] ?? '');
            $isValidationRejection = $status === '';
            if (in_array($status, ['invalid_email', 'email_taken', 'consent_required'], true) || $isValidationRejection) {
                Logger::info('auth', 'Регистрация отклонена валидацией', [
                    'status' => $status,
                    'email' => $email,
                ], [
                    'initiator' => 'register',
                    'details' => $json['error'],
                    'include_trace' => false,
                ]);
            } elseif ($status === 'registered_activation_mail_failed') {
                Logger::warning('auth', 'Регистрация создана, но письмо активации не отправлено', [
                    'status' => $status,
                    'email' => $email,
                ], [
                    'initiator' => 'register',
                    'details' => $json['error'],
                    'include_trace' => false,
                ]);
            } else {
                SysClass::preFile('index_error', 'register', 'Ошибка регистрации', ['error' => $json['error'], 'email' => $email]);
            }
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
            if (empty($this->logged_in) && !LegalConsentService::hasProviderConsent($provider)) {
                SysClass::handleRedirect(302, '/auth_consent/provider/' . rawurlencode($provider));
                return;
            }
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

        $providerConsent = empty($this->logged_in) ? LegalConsentService::getProviderConsent($provider) : [];
        $result = $authService->handleProviderCallback($provider, $_GET, $providerConsent);
        $status = (string) ($result['status'] ?? '');
        if (in_array($status, ['success', 'registered_pending_activation'], true)) {
            LegalConsentService::clearProviderConsent($provider);
        }
        if ($status === 'success') {
            SysClass::handleRedirect(302, '/admin');
            return;
        }
        if ($status === 'consent_required') {
            SysClass::handleRedirect(302, '/auth_consent/provider/' . rawurlencode($provider));
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

    public function auth_consent($params = []): void {
        $provider = strtolower(trim((string) ($params[1] ?? '')));
        if (strtolower(trim((string) ($params[0] ?? ''))) !== 'provider' || $provider === '') {
            SysClass::handleRedirect(200, '/show_login_form');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $postData = SysClass::ee_cleanArray($_POST);
            $missingConsentKeys = LegalConsentService::getMissingRequiredKeys($postData);
            if ($missingConsentKeys === []) {
                LegalConsentService::storeProviderConsent($provider, $postData);
                SysClass::handleRedirect(302, '/auth/' . rawurlencode($provider) . '/start');
                return;
            }

            $this->renderLegalConsentsForm(
                $this->lang['sys.provider_consent_title'] ?? 'Подтверждение обязательных документов',
                $this->lang['sys.provider_consent_intro'] ?? 'Перед продолжением через внешний провайдер подтвердите обязательные документы платформы.',
                ENV_URL_SITE . '/auth_consent/provider/' . rawurlencode($provider),
                $this->lang['sys.continue_with_google'],
                $this->buildConsentErrorMessage($missingConsentKeys),
                [
                    'provider' => $provider,
                    'privacy_policy_accepted' => !empty($postData['privacy_policy_accepted']) ? 1 : 0,
                    'personal_data_consent_accepted' => !empty($postData['personal_data_consent_accepted']) ? 1 : 0,
                ]
            );
            return;
        }

        $this->renderLegalConsentsForm(
            $this->lang['sys.provider_consent_title'] ?? 'Подтверждение обязательных документов',
            $this->lang['sys.provider_consent_intro'] ?? 'Перед продолжением через внешний провайдер подтвердите обязательные документы платформы.',
            ENV_URL_SITE . '/auth_consent/provider/' . rawurlencode($provider),
            $provider === 'google' ? ($this->lang['sys.continue_with_google'] ?? 'Продолжить через Google') : ($this->lang['sys.continue'] ?? 'Продолжить'),
            '',
            [
                'provider' => $provider,
            ]
        );
    }

    public function required_consents($params = []): void {
        unset($params);
        if (empty($this->logged_in)) {
            SysClass::handleRedirect(302, '/show_login_form');
            return;
        }

        $returnPath = LegalConsentService::sanitizeReturnPath($_REQUEST['return'] ?? '/admin', '/admin');
        if (LegalConsentService::hasRequiredConsents($this->users->data)) {
            SysClass::handleRedirect(302, $returnPath);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $postData = SysClass::ee_cleanArray($_POST);
            $returnPath = LegalConsentService::sanitizeReturnPath($postData['return'] ?? $returnPath, '/admin');
            $missingConsentKeys = LegalConsentService::getMissingRequiredKeys($postData);
            if ($missingConsentKeys === []) {
                LegalConsentService::updateUserConsents((int) $this->logged_in, $postData, 'required_consents_gate');
                SysClass::handleRedirect(302, $returnPath);
                return;
            }
            $this->renderLegalConsentsForm(
                $this->lang['sys.required_consents_title'] ?? 'Обязательные документы',
                $this->lang['sys.required_consents_intro'] ?? 'Для продолжения работы примите обязательные документы платформы.',
                ENV_URL_SITE . '/required_consents',
                $this->lang['sys.save_consents'] ?? 'Сохранить согласия',
                $this->buildConsentErrorMessage($missingConsentKeys),
                [
                    'return' => $returnPath,
                    'privacy_policy_accepted' => !empty($postData['privacy_policy_accepted']) ? 1 : 0,
                    'personal_data_consent_accepted' => !empty($postData['personal_data_consent_accepted']) ? 1 : 0,
                ]
            );
            return;
        }

        $this->renderLegalConsentsForm(
            $this->lang['sys.required_consents_title'] ?? 'Обязательные документы',
            $this->lang['sys.required_consents_intro'] ?? 'Для продолжения работы примите обязательные документы платформы.',
            ENV_URL_SITE . '/required_consents',
            $this->lang['sys.save_consents'] ?? 'Сохранить согласия',
            '',
            [
                'return' => $returnPath,
                'privacy_policy_accepted' => !empty($this->users->data['privacy_policy_accepted']) ? 1 : 0,
                'personal_data_consent_accepted' => !empty($this->users->data['personal_data_consent_accepted']) ? 1 : 0,
            ]
        );
    }

    public function privacy_policy($params = null): void {
        if ($params) {
            SysClass::handleRedirect();
        }
        $this->renderLegalDocumentPage('privacy_policy');
    }

    public function consent_personal_data($params = null): void {
        if ($params) {
            SysClass::handleRedirect();
        }
        $this->renderLegalDocumentPage('consent_personal_data');
    }

    /**
     * Преключение языка AJAX
     * И обновление всех параметров пользователя, если есть авторизация
     */
    public function set_options($params = []) {
        $allExistingLanguages = ee_get_interface_lang_codes();
        $requestedLangCode = ee_normalize_lang_code((string) ($params[0] ?? ''));
        if (count($params) > 1 || count($params) == 0 || !SysClass::isAjaxRequestFromSameSite() || $requestedLangCode === '' || !in_array($requestedLangCode, $allExistingLanguages, true)) {
            die(json_encode(array('error' => 'it`s a lie')));
        }
        $postData = SysClass::ee_cleanArray($_POST);
        if ($this->logged_in) {
            $this->access = [classes\system\Constants::ALL_AUTH];
            if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
                die(json_encode(array('error' => 'access denieded')));
            }                          
            Session::set('lang', $requestedLangCode);
            Session::set('lang_manual', 1);
            $postData['localize'] = $requestedLangCode;
            $this->loadModel('m_index');
            $user_data = $this->users->data;
            foreach ($postData as $key => $value) {
                if (array_key_exists($key, $user_data['options'])) {
                    $user_data['options'][$key] = $value;
                }
            }
            $this->users->setUserOptions($this->logged_in, $user_data['options']);
        } else {
            Session::set('lang', $requestedLangCode);
            Session::set('lang_manual', 1);
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

    private function renderLegalConsentsForm(string $title, string $intro, string $actionUrl, string $submitLabel, string $error = '', array $options = []): void {
        $this->getStandardViews();
        $this->view->set('legal_form_title', $title);
        $this->view->set('legal_form_intro', $intro);
        $this->view->set('legal_form_action', $actionUrl);
        $this->view->set('legal_form_submit', $submitLabel);
        $this->view->set('legal_form_error', $error);
        $this->view->set('legal_form_provider', (string) ($options['provider'] ?? ''));
        $this->view->set('legal_form_return', (string) ($options['return'] ?? ''));
        $this->view->set('legal_form_privacy_policy_accepted', !empty($options['privacy_policy_accepted']) ? 1 : 0);
        $this->view->set('legal_form_personal_data_consent_accepted', !empty($options['personal_data_consent_accepted']) ? 1 : 0);
        $this->html = $this->view->read('v_legal_consents_form');
        $this->parameters_layout["layout"] = 'login_form';
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["title"] = $title;
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION;
        $this->parameters_layout["canonical_href"] = $actionUrl;
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->getPathController() . '/css/login-register.css"/>';
        $this->showLayout($this->parameters_layout);
    }

    private function renderLegalDocumentPage(string $slug): void {
        $service = new LegalDocumentService();
        $meta = $service->getDocumentMeta($slug);
        $documentHtml = $service->renderDocumentHtml($slug);
        if ($meta === [] || $documentHtml === '') {
            SysClass::handleRedirect(404);
            return;
        }

        $this->getStandardViews();
        $this->view->set('legal_document_title', $meta['title']);
        $this->view->set('legal_document_version', $meta['version']);
        $this->view->set('legal_document_html', $documentHtml);
        $this->html = $this->view->read('v_legal_document');
        $this->parameters_layout["layout"] = 'index';
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["title"] = $meta['title'];
        $this->parameters_layout["description"] = $meta['title'] . ' - ' . ENV_SITE_NAME;
        $this->parameters_layout["canonical_href"] = $meta['canonical'];
        $this->showLayout($this->parameters_layout);
    }

    private function buildConsentErrorMessage(array $missingConsentKeys): string {
        $messages = [];
        if (in_array('privacy_policy_accepted', $missingConsentKeys, true)) {
            $messages[] = $this->lang['sys.privacy_policy_acceptance_required'] ?? 'Необходимо принять Политику в отношении обработки персональных данных.';
        }
        if (in_array('personal_data_consent_accepted', $missingConsentKeys, true)) {
            $messages[] = $this->lang['sys.personal_data_consent_required'] ?? 'Необходимо дать согласие на обработку персональных данных.';
        }
        return implode(' ', $messages);
    }
    
}
