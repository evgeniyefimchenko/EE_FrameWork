<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

require_once('messages_trait.php');
require_once('notifications_trait.php');
require_once('logs_trait.php');

/*
 * Админ-панель
 */

Class Controller_index Extends Controller_Base {
    /* Подключение traits */

use messages_trait,
    notifications_trait,
    logs_trait;

    /**
     * Главная страница админ-панели
     */
    public function index($param = []) {
        /* $this->access Массив с перечнем ролей пользователей которым разрешён доступ к странице
         * 1-админ 2-модератор 3-менеджер 4-пользователь
         * 100 - все зарегистрированные пользователи
         */
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(200, '/show_login_form?return=admin');
        }
        /* model */
        $this->load_model('m_index', array($this->logged_in));
        /* get user data - все переменные пользователя доступны в представлениях */
        $user_data = $this->models['m_index']->data;
        foreach ($user_data as $name => $val) {
            $this->view->set($name, $val);
        }
        /* view */
        $this->get_standart_view();
        /* Отобразить контент согласно уровня доступа */
        if ($user_data['user_role'] == 1) {                                     // Доступ для администратора
            $this->view->set('body_view', $this->view->read('v_chart'));
        } elseif ($user_data['user_role'] == 2) {                                // Доступ для модератора
            $this->view->set('body_view', 'Доступ для модератора, пока без представления');
        } elseif ($user_data['user_role'] == 3) {                                // Доступ для менеджера
            $this->view->set('body_view', 'Доступ для менеджера, пока без представления');
        } else {                // Обычный пользователь
            $this->view->set('body_view', 'Доступ для пользователя, пока без представления');
        }
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/main_page.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = ENV_SITE_NAME;
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - Административная панель';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Загрузка стандартных представлений для каждой страницы
     */
    private function get_standart_view() {
        $this->view->set('message_user', $this->view->read('v_message_panel'));
        $this->view->set('menu_options', $this->view->read('v_menu_options'));
        $this->view->set('main_menu', $this->view->read('v_main_menu'));
    }

    /**
     * Обработка AJAX запросов админ-панели
     * @param array $arg - дополнительные параметры запрещены
     * @param POST $post_data - POST параметры update или get
     */
    public function ajax_admin($arg = array()) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access) || count($arg) > 0) {
            echo '{"error": "access denieded"}';
            exit();
        }
        /* model */
        $this->load_model('m_index', array($this->logged_in));
        /* get data */
        $user_data = $this->models['m_index']->data;
        /* Read POST data */
        $post_data = filter_input_array(INPUT_POST, $_POST);
        switch (true) {
            case isset($post_data['update']):
                foreach ($post_data as $key => $value) {
                    if (array_key_exists($key, $user_data['options'])) {
                        $user_data['options'][$key] = $value;
                    }
                }
                echo $this->models['m_index']->set_user_options($this->logged_in, $user_data['options']);
                exit();
            case isset($post_data['get']):
                echo json_encode($user_data['options']);
                exit();
            default:
                echo '{"error": "no data"}';
        }
    }

    /**
     * AJAX Удаление пользователя(перемещение в таблицу удалённых)
     */
    public function delete_user($arg) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        /* model */
        $this->load_model('m_logs', array($this->logged_in));
        if (in_array('id', $arg)) {                                                       // Пользователь передан получаем данные из базы
            $id = filter_var($arg[array_search('id', $arg) + 1], FILTER_VALIDATE_INT);
            $get_user_context = $this->models['m_logs']->get_user_data($id);
        } else {
            echo json_encode(array('error' => 'error delete_user not user id'));
            exit();
        }
        if ($get_user_context) {
            $this->models['m_logs']->dell_user_data($id, true);
        } else {
            echo json_encode(array('error' => 'error delete_user not user in db'));
            exit();
        }
        echo json_encode(array('error' => 'no'));
        exit();
    }

    /**
     * Карточка пользователя сайта
     * для изменения данных и внесения новых пользователей вручную
     * @param type $arg
     */
    public function user_edit($arg) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        /* model */
        $this->load_model('m_index', array($this->logged_in));
        if (in_array('id', $arg)) {                                                       // Пользователь передан получаем данные из базы
            $id = filter_var($arg[array_search('id', $arg) + 1], FILTER_VALIDATE_INT);
            $get_user_context = $this->models['m_index']->get_user_data($id);
            /* Нельзя посмотреть чужую карточку равной себе роли или выше */
            if ($this->models['m_index']->data['user_role'] >= $get_user_context['user_role'] && $this->logged_in != $id) {
                SysClass::return_to_main(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
            }
        } else {                                                                            // Не передан ключевой параметр id
            SysClass::return_to_main(301, $_SERVER['HTTP_REFERER']);
        }

        $this->load_model('m_user_edit');

        /* get data */
        $user_data = $this->models['m_index']->data;
        foreach ($user_data as $name => $val) {
            $this->view->set($name, $val);
        }
        $free_active_status = [1 => 'Не подтверждён', 2 => 'Активен', 3 => 'Блокирован'];
        unset($free_active_status[$get_user_context['active']]);
        $get_free_roles = $this->models['m_user_edit']->get_free_roles($get_user_context['user_role']); // Получим свободные роли
        $this->view->set('free_active_status', $free_active_status);
        $this->view->set('get_free_roles', $get_free_roles);
        $this->view->set('user_id', $this->logged_in);
        $this->view->set('get_user_context', $get_user_context);
        /* view */
        $this->get_standart_view();
        $this->view->set('body_view', $this->view->read('v_edit_user'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/edit_user.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Административная панель/Редактирование профиля';
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Аякс изменение рег. данных пользователя
     * Редактирование возможно модераторами
     * или самим пользователем
     * @param $arg - ID пользователя для изменения
     * @return json сообщение об ошибке или no
     */
    public function ajax_user_edit($arg) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $id = filter_var($arg[array_search('id', $arg) + 1], FILTER_VALIDATE_INT);
        /* model */
        $this->load_model('m_index', array($this->logged_in));
        if ($this->models['m_index']->data['user_role'] > 2 && $this->logged_in != $id) { // Роль меньше модератора или id не текущего пользователя выходим
            echo json_encode(array('error' => 'error no access'));
            exit();
        }
        /* set data user */
        $post_data = filter_input_array(INPUT_POST, $_POST);
        if ($post_data['new'] == '1') {
            if ($this->models['m_index']->registration_new_user($post_data)) {
                $new_id = $this->models['m_index']->get_user_id(trim($post_data['email']));
                echo json_encode(array('error' => 'no', 'id' => $new_id));
                exit();
            } else {
                echo json_encode(array('error' => 'error ajax_user_edit isert user'));
                exit();
            }
        }

        if ($this->models['m_index']->set_user_data($id, $post_data)) {
            echo json_encode(array('error' => 'no'));
            exit();
        } else {
            echo json_encode(array('error' => 'error ajax_user_edit'));
            exit();
        }
    }

    /**
     * Выводит список пользователей
     * Доступ у администраторов, модераторов
     * @param arg - массив аргументов для поиска
     */
    public function users($arg = array()) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        /* model */
        $this->load_model('m_index', array($this->logged_in));
        /* get data */
        $user_data = $this->models['m_index']->data;
        foreach ($user_data as $name => $val) {
            $this->view->set($name, $val);
        }
        $users_array = $this->models['m_index']->get_users_data();
        /* view */
        $this->get_standart_view();
        $this->view->set('users', is_array($users_array) ? $users_array : array());
        $this->view->set('body_view', $this->view->read('v_users'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/plugins/DataTables/datatables.min.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->get_path_controller() . '/js/plugins/DataTables/datatables.min.css"/>';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/users.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Административная панель/Пользователи';
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Обратная связь с автором
     */
    public function upgrade() {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        /* model */
        $this->load_model('m_index', array($this->logged_in));
        /* get data */
        $user_data = $this->models['m_index']->data;
        foreach ($user_data as $name => $val) {
            $this->view->set($name, $val);
        }
        $users_array = $this->models['m_index']->get_users_data();
        /* view */
        $this->get_standart_view();
        $this->view->set('body_view', $this->view->read('v_upgrade'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/upgrade.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Административная панель/Связь с автором';
        $this->show_layout($this->parameters_layout);
    }

}
