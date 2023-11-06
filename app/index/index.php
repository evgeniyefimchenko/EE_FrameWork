<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Класс контроллера главной страницы сайта
 */
Class Controller_index Extends Controller_Base {

    private $lang = []; // Языковые переменные в рамках этого класса

    /**
     * Загрузит в представление данные пользователя, модель m_index
     * И языковой массив данных в представления, сессию и свойство класса
     */
    private function get_user_data() {
        $this->access = array(100);
        if ($this->logged_in) {
            $this->load_model('m_index', [$this->logged_in]);
            /* get user data */
            $user_data = $this->models['m_index']->data;
            foreach ($user_data as $name => $val) {
                $this->view->set($name, $val);
            }
            if (mb_strlen($user_data['options']['localize']) > 1 && empty(Session::get('lang'))) { // Проверка на наличие локали в настройках пользователя
                $lang_code = $user_data['options']['localize'];
            } else { // Записываем локаль в опции пользователя
                $lang_code = Session::get('lang');
                $user_data['options']['localize'] = $lang_code;
                $this->models['m_index']->set_user_options($this->logged_in, $user_data['options']);
            }
            include_once(ENV_SITE_PATH . ENV_PATH_LANG . '/' . $lang_code . '.php');
            Session::set('lang', $lang_code);
            $this->view->set('lang', $lang);
            $this->lang = $lang;
        } else {
            $this->load_model('m_index');
            $lang_code = Session::get('lang');
            if (!$lang_code) {
                $lang_code = ENV_DEF_LANG;
                Session::set('lang', $lang_code);
            }
            include(ENV_SITE_PATH . ENV_PATH_LANG . '/' . $lang_code . '.php');
            $this->lang = $lang;
            $this->view->set('lang', $lang);
            $this->models['m_index']->set_lang($lang);
        }
    }

    /**
     * Загрузка стандартных представлений
     */
    private function get_standart_view() {
        $this->view->set('logged_in', $this->logged_in);
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/index.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->get_path_controller() . '/css/index.css"/>';
    }

    /**
     * Главная страница проекта
     */
    public function index($params = NULL) {
        if ($params) {
            SysClass::return_to_main();
        }
        $this->get_user_data();
        /* view */
        $this->get_standart_view();
        $this->view->set('top_panel', $this->view->read('v_top_panel'));
        $this->html = $this->view->read('v_index');
        /* layouts */
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - General page';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION;
        $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->parameters_layout["layout_content"] = $this->html;
        $this->show_layout($this->parameters_layout);
    }

    /**
     * О нас
     */
    public function about($params = NULL) {
        if ($params) {
            SysClass::return_to_main();
        }
        $this->get_user_data();
        /* view */
        $this->get_standart_view();
        $this->html = $this->view->read('v_about');
        /* layouts */
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - About Us';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION;
        $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->parameters_layout["layout_content"] = $this->html;
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Контакты
     */
    public function contact($params = NULL) {
        if ($params) {
            SysClass::return_to_main();
        }
        $this->get_user_data();
        /* view */
        $this->get_standart_view();
        $this->html = $this->view->read('v_contact');
        /* layouts */
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - Contact';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION;
        $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->parameters_layout["layout_content"] = $this->html;
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Покажет форму Авторизации/Регистрации
     * Если передать GET параметр return то после действия с формой
     * произойдет возврат на указанную страницу
     * site.ru/show_login_form?return='help' вернёт на site.ru/help
     */
    public function show_login_form($params = null) {
        if ($params) {
            SysClass::return_to_main();
        }

        $this->get_user_data();
        $this->models['m_index']->get_admin_profile(); // Если профиля админа не существует то он будет создан test@test.com admin

        /* view */
        if ($this->view->get('new_user') || !$this->logged_in) {
            $this->html = $this->view->read('v_login_form');
        } else {
            /* Уже авторизован */
            SysClass::return_to_main(200, '/admin');
        }
        /* layouts */
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/js/plugins/validator.min.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/login-register.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script>$(document).ready(function () {openLoginModal();});</script>';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->get_path_controller() . '/css/login-register.css"/>';
        $this->parameters_layout["title"] = ENV_SITE_NAME;
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - Login/Registration Form';
        $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->parameters_layout["layout"] = 'login_form';
        $this->parameters_layout["layout_content"] = $this->html;
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Авторизация пользователя AJAX
     */
    public function login($params = null) {
        if ($params || !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest' || empty(ENV_SITE)) {
            die(json_encode(array('error' => 'it`s a lie')));
        }
        if (!SysClass::connect_db_exists()) {
            $json['error'] = $this->lang['sys.no_connection_to_db'];
            die(json_encode($json, JSON_UNESCAPED_UNICODE));
        }
        $this->get_user_data();
        $json['error'] = '';
        $post_data = SysClass::ee_cleanArray($_POST);
        $email = trim($post_data['email']);
        $pass = trim($post_data['password']);
        if (!SysClass::validEmail($email)) {
            $json['error'] = $this->lang['sys.invalid_mail_format'];
            die(json_encode($json, JSON_UNESCAPED_UNICODE));
        }

        $json['error'] = $this->models['m_index']->confirm_user($email, $pass);
        die(json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Выход пользователя
     */
    public function exit_login($params = null) {
        Session::un_set('user_session');
        SysClass::return_to_main(301, '/');
    }

    /**
     * Регистрация пользователя после заполнения формы AJAX
     */
    public function register($params = null) {
        if ($params || !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest' || empty(ENV_SITE)) {
            die(json_encode(array('error' => 'it`s a lie')));
        }
        $this->get_user_data();
        if (!SysClass::connect_db_exists()) {
            $json['error'] = $this->lang['sys.no_connection_to_db'];
            echo json_encode($json, JSON_UNESCAPED_UNICODE);
            die();
        }
        $post_data = SysClass::ee_cleanArray($_POST);
        $email = trim($post_data['email']);
        $pass = trim($post_data['password']);
        $conf_pass = trim($post_data['password_confirmation']);
        if (!SysClass::validEmail($email)) {
            $json['error'] .= $this->lang['sys.invalid_mail_format'];
        }
        if ($pass !== $conf_pass) {
            $json['error'] .= $this->lang['sys.password_mismatch'];
        }

        if (!$json['error'] && $this->models['m_index']->get_email_exist($email)) {
            $json['error'] .= $this->lang['sys.the_mail_is_already_busy'];
        }

        if (!$json['error'] && !$this->models['m_index']->registration_users($email, $pass)) {
            $json['error'] .= $this->lang['sys.db_registration_error'];
        }

        $json['error'] = $json['error'] ? $json['error'] : '';

        if (ENV_LOG && $json['error'] != '') {
            SysClass::SetLog('Ошибка регистрации ' . $json['error'] . ' Почта: ' . $email . ' Пароль: ' . $pass . ' Дубль: ' . $conf_pass, 'error', 8);
        }

        if ($json['error'] === '') {
            if (ENV_CONFIRM_EMAIL == 1) {
                $json['error'] = $this->models['m_index']->send_register_code($email) ? '' : $this->lang['sys.email_sending_error'];
            } else {
                $this->models['m_index']->confirm_user($email, '', true); /* Автологин */
            }
        }
        echo json_encode($json);
    }

    /**
     * Активация пользователя по ссылке из письма
     * @params array $params - $params[1] - почта $params[0] - Код
     */
    public function activation($params) {
        $this->get_user_data();
        if ($this->models['m_index']->get_email_exist($params[1])) {
            $active = $this->models['m_index']->get_user_stat($this->models['m_index']->get_user_id_by_email($params[1]));
            if ($active == 1) {
                if (password_verify($params[1], base64_decode($params[0]))) {
                    if ($this->models['m_index']->dell_activation_code($params[1], $params[0])) {
                        $this->models['m_index']->confirm_user($params[1], '', true); /* Автологин */
                        $this->parameters_layout["layout_content"] = $this->lang['sys.successfully_activated'] . ' <meta http-equiv="refresh" content="7;URL=' . ENV_URL_SITE . '">';
                    } else {
                        $this->parameters_layout["layout_content"] = $this->lang['sys.activation_error'] . ' <meta http-equiv="refresh" content="7;URL=' . ENV_URL_SITE . '">';
                        if (ENV_LOG) {
                            SysClass::SetLog('Ошибка удаления активационного кода ' . $params[0] . ' для' . $params[1], 'error');
                        }
                    }
                } else {
                    SysClass::return_to_main();
                }
            } elseif ($active == 2) {
                $this->parameters_layout["layout_content"] = $this->lang['sys.account_is_already_active'] . ' <meta http-equiv="refresh" content="7;URL=' . ENV_URL_SITE . '">';
            } elseif ($active == 3) {
                $this->parameters_layout["layout_content"] = $this->lang['sys.you_were_blocked'] . ' <meta http-equiv="refresh" content="7;URL=' . ENV_URL_SITE . '">';
            }
        } else {
            $this->parameters_layout["layout_content"] = $this->lang['sys.email_not_registered'] . ' <meta http-equiv="refresh" content="7;URL=' . ENV_URL_SITE . '">';
        }
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Восстановление пароля AJAX
     */
    public function recovery_password() {
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest' || empty(ENV_SITE)) {
            die(json_encode(array('error' => 'it`s a lie')));
        }
        $this->get_user_data();

        die('Ouuuu this fuck');
    }

    /**
     * Преключение языка AJAX
     * И обновление всех параметров пользователя, если есть авторизация
     */
    public function set_options($params = []) {
        if (count($params) > 1 || count($params) == 0 || !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest' || empty(ENV_SITE)) {
            die(json_encode(array('error' => 'it`s a lie')));
        }
        $post_data = SysClass::ee_cleanArray($_POST);
        if ($this->logged_in) {
            $this->access = array(100);
            if (!SysClass::get_access_user($this->logged_in, $this->access)) {
                die(json_encode(array('error' => 'access denieded')));
            }
            if ($params[0] == 'en' || $params[0] == 'ru') {                
                Session::set('lang', $params[0]);
                $post_data['localize'] = $params[0];
            }
            $this->load_model('m_index', [$this->logged_in]);
            $user_data = $this->models['m_index']->data;
            foreach ($post_data as $key => $value) {
                if (array_key_exists($key, $user_data['options'])) {
                    $user_data['options'][$key] = $value;
                }
            }
            $this->models['m_index']->set_user_options($this->logged_in, $user_data['options']);
        } else {
            Session::set('lang', $params[0]);
        }
        die(json_encode(array('error' => 'no')));
    }

    /**
     * Функция возврата языковых переменных AJAX
     */
    public function language($params = NULL) {
        if ($params || !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest' || empty(ENV_SITE)) {
            die('it`s a lie');
        }
        $post_data = SysClass::ee_cleanArray($_POST);
        $this->get_user_data();
        if (isset($post_data['loadAll']) && $post_data['loadAll'] == 'true') {
            die(json_encode($this->lang));
        } else {
            die(isset($this->lang[$post_data['text']]) ? $this->lang[$post_data['text']] : 'var ' . $post_data['text'] . ' not found!');
        }
    }

}
