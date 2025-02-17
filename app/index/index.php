<?php

use classes\system\ControllerBase;
use classes\system\SysClass;
use classes\system\Session;
use classes\system\Cookies;
use classes\helpers\ClassMail;

/**
 * Класс контроллера главной страницы сайта
 */
class ControllerIndex Extends ControllerBase {

    /**
     * Загрузка стандартных представлений
     */
    private function getStandardViews() {
        $this->view->set('logged_in', $this->logged_in);
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/index.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->getPathController() . '/css/index.css"/>';
    }

    /**
     * Главная страница проекта
     */
    public function index($params = NULL) {
        if ($params) {
            SysClass::pre($params); // TODO
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
        if ($params) {
            SysClass::handleRedirect();
        }
        /* view */
        $this->getStandardViews();
        $this->view->set('menu_docs', $this->view->read('v_menu_docs'));
        $this->html = $this->view->read('v_docs');
        /* layouts */
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/docs.js" ></script>';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->getPathController() . '/css/docs.css"/>';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - Documentation';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION;
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->parameters_layout["layout_content"] = $this->html;
        $this->showLayout($this->parameters_layout);        
    }
    
    /**
     * AJAX получение страниц документации
     * @param type $params
     */
    public function get_doc($params = NULL) {
        if (isset($_POST) && isset($_POST['docName'])) {
            $file_path = ENV_SITE_PATH . 'uploads/docs/' . $_POST['docName']. '.html';
            if (file_exists($file_path) && is_readable($file_path)) {
                echo file_get_contents($file_path);
            } else {
                echo 'Ошибка чтения файла ' . $file_path;
            }
        }
        die;
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
        if ($params || !SysClass::isAjaxRequestFromSameSite() || empty(ENV_SITE)) {
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
        $json['error'] = $this->users->confirmUser($email, $pass);
        die(json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Выход пользователя
     */
    public function exit_login($params = null) {
        if (ENV_AUTH_USER == 0) {
            Session::un_set('user_session');
        }
        if (ENV_AUTH_USER === 2) {
            Cookies::clear('user_session');
        }
        SysClass::handleRedirect(301, '/');
    }

    /**
     * Регистрация пользователя после заполнения формы AJAX
     */
    public function register($params = null) {
        if ($params || !SysClass::isAjaxRequestFromSameSite() || empty(ENV_SITE)) {
            die(json_encode(array('error' => 'it`s a lie')));
        }
        $json = [];
        $json['error'] = '';
        if (!SysClass::checkDatabaseConnection()) {
            $json['error'] = $this->lang['sys.no_connection_to_db'];
            echo json_encode($json, JSON_UNESCAPED_UNICODE);
            die();
        }
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

        if (!$json['error'] && $this->users->get_email_exist($email)) {
            $json['error'] .= $this->lang['sys.the_mail_is_already_busy'];
        }

        if (!$json['error'] && !$this->users->registrationUsers($email, $pass)) {
            $json['error'] .= $this->lang['sys.db_registration_error'];
        }

        $json['error'] = $json['error'] ? $json['error'] : '';

        if ($json['error'] != '') {
            SysClass::preFile('index_error', 'register', 'Ошибка регистрации', ['error' => $json['error'], 'email' => $email]);
        }

        if ($json['error'] === '') {
            if (ENV_CONFIRM_EMAIL == 1) {
                $json['error'] = $this->users->send_register_code($email) ? '' : $this->lang['sys.email_sending_error'];
            } else {
                $this->users->confirmUser($email, '', true); /* Автологин */
            }
        }
        echo json_encode($json);
    }

    /**
     * Активация пользователя по ссылке из письма
     * @params array $params - $params[1] - почта $params[0] - Код
     */
    public function activation($params) {
        if ($this->users->get_email_exist($params[1])) {
            $active = $this->users->get_user_stat($this->users->get_user_id_by_email($params[1]));
            if ($active == 1) {
                if (password_verify($params[1], base64_decode($params[0]))) {
                    if ($this->users->dell_activation_code($params[1], $params[0])) {
                        ClassMail::send_mail($email, 'Спасибо за активацию', ['EMAIL' => $email], 'account_activated');
                        $this->users->confirmUser($params[1], '', true); /* Автологин */
                        $this->parameters_layout["layout_content"] = $this->lang['sys.successfully_activated'] . ' <meta http-equiv="refresh" content="7;URL=' . ENV_URL_SITE . '">';
                    } else {
                        $this->parameters_layout["layout_content"] = $this->lang['sys.activation_error'] . ' <meta http-equiv="refresh" content="7;URL=' . ENV_URL_SITE . '">';
                        SysClass::preFile('index_error', 'activation', 'Ошибка удаления активационного кода', ['code' => $params[0], 'email' => $params[1]]);
                    }
                } else {
                    SysClass::handleRedirect();
                }
            } elseif ($active == 2) {
                $this->parameters_layout["layout_content"] = $this->lang['sys.account_is_already_active'] . ' <meta http-equiv="refresh" content="7;URL=' . ENV_URL_SITE . '">';
            } elseif ($active == 3) {
                $this->parameters_layout["layout_content"] = $this->lang['sys.you_were_blocked'] . ' <meta http-equiv="refresh" content="7;URL=' . ENV_URL_SITE . '">';
            }
        } else {
            $this->parameters_layout["layout_content"] = $this->lang['sys.email_not_registered'] . ' <meta http-equiv="refresh" content="7;URL=' . ENV_URL_SITE . '">';
        }
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Восстановление пароля AJAX
     */
    public function recovery_password() {
        if (!SysClass::isAjaxRequestFromSameSite() || empty(ENV_SITE)) {
            die(json_encode(array('error' => 'it`s a lie')));
        }
        die('in development');
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
    
}
