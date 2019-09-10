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

    /**
     * Главная страница проекта
     */
    public function index($param = []) {
        if ($this->logged) {
            /* user logined */
        } else {
            /* model */
            $this->load_model('m_index', array($this->logged));
            /* search user data */
            $user_data = $this->models['m_index']->data;
            foreach ($user_data as $name => $val) {
                $this->view->set($name, $val);
            }
            /* view */
            $this->html = $this->view->read('v_index');
            /* layouts */
            $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/main.js" type="text/javascript" /></script>';
            $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->get_path_controller() . '/css/main.css" />';
            $this->parameters_layout["title"] = ENV_SITE_NAME;
            $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - Главная страница';
            $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
            $this->parameters_layout["layout_content"] = $this->html;
            $this->show_layout($this->parameters_layout);
        }
    }

    public function show_login_form($param = null) {
        if ($param) {
            SysClass::return_to_main();
        }
        /* view */
        $this->html = $this->view->read('v_login_form');
        /* layouts */
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/js/plugins/validator.min.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/login-register.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script>$(document).ready(function () {openLoginModal();});</script>';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->get_path_controller() . '/css/login-register.css"/>';
        $this->parameters_layout["title"] = ENV_SITE_NAME;
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - Форма авторизации/Регистрации';
        $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->parameters_layout["layout_content"] = $this->html;
        $this->show_layout($this->parameters_layout);
    }

    /**
     * AJAX Регистрация пользователя после заполнения формы
     */
    public function register() {
        $post_data = filter_input_array(INPUT_POST, $_POST);
        $email = trim($post_data['email']);
        $pass = trim($post_data['password']);
        $conf_pass = trim($post_data['password_confirmation']);
        if (!SysClass::validEmail($email)) {
            $json['error'] .= 'Неверный формат почты';
        }
        if ($pass !== $conf_pass) {
            $json['error'] .= 'Пароли не совпали';
        }
        $this->load_model('m_index');

        if (!$json['error'] && $this->models['m_index']->get_email_exist($email)) {
            $json['error'] .= 'Почта уже занята.';
        }

        if (!$json['error'] && !$this->models['m_index']->registration_users($email, $pass)) {
            $json['error'] .= 'Ошибка регистрации в БД';
        }

        $json['error'] = $json['error'] ? $json['error'] : '';

        if (ENV_LOG && $json['error'] != '') {
            SysClass::SetLog('Ошибка регистрации' . $json['error'] . ' Почта: ' . $email . ' Пароль: ' . $pass . ' Дубль: ' . $conf_pass, 'error', 8);
        }

        if ($json['error'] === '') {
            if (ENV_CONFIRM_EMAIL) {
                $json['error'] = $this->models['m_index']->send_register_code($email) ? '' : 'Ошибка отправления письма!';
            } else {
                $json['error'] = $this->models['m_index']->confirm_user($param[1], '', true); /* Автологин */
            }
        }
        echo json_encode($json);
    }

    /**
     * Активация пользователя по ссылке из письма
     * @param type $param
     * $param[1] - почта $param[0] - Код
     */
    public function activation($param) {
        $this->load_model('m_index');
        if ($this->models['m_index']->get_email_exist($param[1])) {
            $active = $this->models['m_index']->get_user_stat($this->models['m_index']->get_user_id_by_email($param[1]));
            if ($active == 1) {
                if (password_verify($param[1], base64_decode($param[0]))) {
                    if ($this->models['m_index']->dell_activation_code($param[1])) {
                        $this->models['m_index']->confirm_user($param[1], '', true); /* Автологин */
                        $this->parameters_layout["layout_content"] = 'Вы успешно активировали свой аккаунт. И будете перенаправлены.<meta http-equiv="refresh" content="5;URL=' . ENV_URL_SITE . '">';
                    } else {
                        $this->parameters_layout["layout_content"] = 'Произошла ошибка активации, код был просрочен. Данные очищены, пройдите регистрацию вновь. <meta http-equiv="refresh" content="5;URL=' . ENV_URL_SITE . '">';
                        if (ENV_LOG) {
                            SysClass::SetLog('Ошибка удаления активационного кода ' . $param[0] . ' для' . $param[1], 'error');
                        }
                    }
                } else {
                    SysClass::return_to_main();
                }
            } elseif ($active == 2) {
                $this->parameters_layout["layout_content"] = 'Ваш аккаунт уже активен. <meta http-equiv="refresh" content="5;URL=' . ENV_URL_SITE . '">';
            } elseif ($active == 3) {
                $this->parameters_layout["layout_content"] = 'Сожалеем но Вас заблокировали, обратитесь к администратору сайта. <meta http-equiv="refresh" content="5;URL=' . ENV_URL_SITE . '">';
            }
        } else {
            $this->parameters_layout["layout_content"] = 'Извините, но Вы у нас не регистрировались с такой почтой. <meta http-equiv="refresh" content="5;URL=' . ENV_URL_SITE . '">';
        }
        $this->show_layout($this->parameters_layout);
    }

    /**
     * AJAX авторизация пользователя
     */
    public function login() {
        $json['error'] = '';
        $post_data = filter_input_array(INPUT_POST, $_POST);
        $email = trim($post_data['email']);
        $pass = trim($post_data['password']);
        if (SysClass::validEmail($email) === false) {
            $json['error'] = 'Неверный формат почты';
            echo json_encode($json, JSON_UNESCAPED_UNICODE);
            die();
        }
        $this->load_model('m_index');
        $json['error'] = $this->models['m_index']->confirm_user($email, $pass);
        echo json_encode($json, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Выход пользователя
     * Сброс сессии
     */
    public function exit_login() {
        Session::destroy();
        SysClass::return_to_main(301, '/');
    }

    /**
     * Восстановление пароля 
     */
    public function recovery() {
        $user_email = '';
        $this->load_model('m_index');
        if ($this->models['m_index']->get_email_exist($user_email)) {
            $this->models['m_index']->send_recovery_password($user_email);
        }
        $this->parameters_layout["layout_content"] = 'Извините, функционал на стадии разработки.<meta http-equiv="refresh" content="5;URL=' . ENV_URL_SITE . '">';
        $this->show_layout($this->parameters_layout);
    }

}
