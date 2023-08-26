<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/*
 * Админ-панель
 */

Class Controller_index Extends Controller_Base {    

	private $lang = []; // Языковые переменные в рамках этого класса

	/* Подключение traits */
	use messages_trait,
		notifications_trait,
		logs_trait,
		emails_trait;

    /**
     * Главная страница админ-панели
     */
    public function index($param = []) {		
        /* $this->access Массив с перечнем ролей пользователей которым разрешён доступ к странице
         * 1-админ 2-модератор 3-продавец 4-пользователь
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
		$this->get_user_data($user_data);
        /* view */
        $this->get_standart_view();
        /* Отобразить контент согласно уровня доступа */
        if ($user_data['user_role'] == 1) { // Доступ для администратора
            $this->view->set('body_view', $this->view->read('v_dashboard_admin'));			
			$this->parameters_layout["add_script"] .= '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.bundle.js" integrity="sha512-zO8oeHCxetPn1Hd9PdDleg5Tw1bAaP0YmNvPY8CwcRyUk7d7/+nyElmFrB6f7vg4f7Fv4sui1mcep8RIEShczg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>';
			$this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/dashboard_admin.js" type="text/javascript" /></script>';
        }		
        $this->html = $this->view->read('v_dashboard');        
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - DASHBOARD';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - DASHBOARD';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->show_layout($this->parameters_layout);		
    }

	/**
	* Коммерческое предложение
	*/
	public function upgrade($params = []) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(200, '/show_login_form?return=admin');
        }
        /* model */
        $this->load_model('m_index', array($this->logged_in));
        /* get user data - все переменные пользователя доступны в представлениях */
        $user_data = $this->models['m_index']->data;
		$this->get_user_data($user_data);
        /* view */
        $this->get_standart_view();
		
		$this->view->set('body_view', $this->view->read('v_upgrade'));        
        $this->html = $this->view->read('v_dashboard');
		
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - UPGRADE';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - UPGRADE';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin/upgrade';
        $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->show_layout($this->parameters_layout);				
	}

    /**
     * Загрузит в представление данные пользователя
	 * И языковой массив
	 * @param $user_data - Данные пользователя для загрузки
     */
    private function get_user_data($user_data) {
        foreach ($user_data as $name => $val) {            
			$this->view->set($name, $val);
        }
		if ($user_data['options']['localize']) { // Проверка на наличие локали в настройках пользователя
			$lang_code = $user_data['options']['localize'];
		} else { // Записываем локаль в опции пользователя
			$lang_code = Session::get('lang');
			$user_data['options']['localize'] = $lang_code;
			$this->models['m_index']->set_user_options($this->logged_in, $user_data['options']);
		}
		include(ENV_SITE_PATH . ENV_PATH_LANG . '/' . $lang_code . '.php');
		Session::set('lang', $lang_code);
		$this->view->set('lang', $lang);
		$this->lang = $lang;
		foreach($this->models as $key => $value) {
			if (is_callable($this->models[$key]->set_lang($lang))) {
				$this->models[$key]->set_lang($lang);
			}
		}
    }	
	
    /**
     * Загрузка стандартных представлений для каждой страницы
     */
    private function get_standart_view() {
        $this->view->set('top_bar', $this->view->read('v_top_bar'));
        $this->view->set('main_menu', $this->view->read('v_main_menu'));
    }

    /**
     * Обработка AJAX запросов админ-панели
     * @param array $arg - дополнительные параметры запрещены
     * @param POST $post_data - POST параметры update или get
     */
    public function ajax_admin($param = array()) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access) || count($param) > 0) {
            echo '{"error": "access denieded"}';
            exit();
        }
        /* model */
        $this->load_model('m_index', array($this->logged_in));
        /* get data */
        $user_data = $this->models['m_index']->data;
		$this->get_user_data($user_data);
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
    public function delete_user($param) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        /* model */
        $this->load_model('m_logs', array($this->logged_in));
        if (in_array('id', $param)) { // Пользователь передан получаем данные из базы
            $id = filter_var($param[array_search('id', $param) + 1], FILTER_VALIDATE_INT);
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
        /* get current user data */
        $user_data = $this->models['m_index']->data;
		$this->get_user_data($user_data);		
        if (in_array('id', $arg)) {                                                       // Пользователь передан получаем данные из базы
            $id = filter_var($arg[array_search('id', $arg) + 1], FILTER_VALIDATE_INT);
            $get_user_context = $this->models['m_index']->get_user_data($id);
            /* Нельзя посмотреть чужую карточку равной себе роли или выше */
            if ($this->models['m_index']->data['user_role'] >= $get_user_context['user_role'] && $this->logged_in != $id) {
                SysClass::return_to_main(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
            }
        } else {                                                                            // Не передан ключевой параметр id
            SysClass::return_to_main(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }

        $this->load_model('m_user_edit');
        /*Если не админ и модер и карточка не своя возвращаем*/
        if ($this->models['m_index']->data['user_role'] > 2 && $this->logged_in != $id) {
            SysClass::return_to_main(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        /*get data*/
        $free_active_status = [1 => 'Not confirmed', 2 => 'Active', 3 => 'Blocked'];
        unset($free_active_status[$get_user_context['active']]);
        $get_free_roles = $this->models['m_user_edit']->get_free_roles($get_user_context['user_role']); // Получим свободные роли
        $this->view->set('free_active_status', $free_active_status);
        $this->view->set('get_free_roles', $get_free_roles);
        $this->view->set('user_id', $this->logged_in);        

        /* view */
		$this->view->set('get_user_context', $get_user_context);
        $this->get_standart_view();
        $this->view->set('body_view', $this->view->read('v_edit_user'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="/assets/js/plugins/jQueryInputFormattingNumber/dist/jquery.masknumber.min.js" type="text/javascript" /></script>';        
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/edit_user.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'USER EDIT';
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
		$this->load_model('m_settings');
        if ($this->models['m_index']->data['user_role'] > 2 && $this->logged_in != $id) { // Роль меньше модератора или id не текущего пользователя выходим
            echo json_encode(array('error' => 'error no access'));
            exit();
        }
        /* set data user */                                
		
		$_POST['phone'] = isset($_POST['phone']) ? preg_replace('/[^0-9+]/', '', $_POST['phone']) : null;
        if ($this->models['m_index']->data['phone'] && $this->models['m_index']->data['user_role'] > 2) {
            unset($_POST['phone']);
        }
        
        if ($_POST['new'] == '1') {
            if ($this->models['m_index']->registration_new_user($_POST)) {
                $new_id = $this->models['m_index']->get_user_id(trim($_POST['email']));
                echo json_encode(array('error' => 'no', 'id' => $new_id));
                exit();
            } else {
                echo json_encode(array('error' => 'error ajax_user_edit isert user'));
                exit();
            }
        }

        if ($this->models['m_index']->set_user_data($id, $_POST)) {
            $user_role = $this->models['m_index']->get_user_role($id);
            if (isset($_POST['user_role']) && $_POST['user_role'] != $user_role) { // Сменилась роль пользователя, оповещаем админа и пишем лог
                $mail = new Class_mail();
                $mail->send_mail($this->models['m_index']->get_user_email(1), 'changed status(' . $user_role . ' to ' . $_POST['user_role'] . ') to user', 'User ' . $this->logged_in . ' changed status to user ' . $id);
                SysClass::SetLog('Изменили роль пользователю ID=' . $id . ' с ' . $this->models['m_index']->data['user_role'] . ' на ' . $_POST['user_role'], 'info', $this->logged_in);
            }
            echo json_encode(array('error' => 'no', 'id' => $id));
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
		$this->get_user_data($user_data);
        $users_array = $this->models['m_index']->get_users_data();
        /* view */
        $this->get_standart_view();
        $this->view->set('users', is_array($users_array) ? $users_array : array());
        $this->view->set('body_view', $this->view->read('v_users'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="/assets/js/plugins/DataTables/DataTables-1.10.20/js/jquery.dataTables.min.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script src="/assets/js/plugins/DataTables/DataTables-1.10.20/js/dataTables.bootstrap4.min.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script src="/assets/js/plugins/DataTables/SearchPanes-1.0.1/js/dataTables.searchPanes.min.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script src="/assets/js/plugins/DataTables/SearchPanes-1.0.1/js/searchPanes.bootstrap4.min.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script src="/assets/js/plugins/DataTables/Select-1.3.1/js/dataTables.select.min.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="/assets/js/plugins/DataTables/DataTables-1.10.20/css/dataTables.bootstrap4.min.css"/>';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="/assets/js/plugins/DataTables/SearchPanes-1.0.1/css/searchPanes.bootstrap4.min.css"/>';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="/assets/js/plugins/DataTables/Select-1.3.1/css/select.bootstrap4.min.css"/>';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/users.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'USERS';
        $this->show_layout($this->parameters_layout);
    }
    
    /**
     * Отправит сообщение администратору AJAX
     * @param array $param
     */
    public function send_message_admin($param = []) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access) || count($param)>0) {
            echo json_encode(array('error' => 'access denided'));
            exit();
        }
        $class_messages = new Class_messages();
        $this->load_model('m_index', array($this->logged_in));
        $class_messages->set_message_user(1, $this->logged_in, $_REQUEST['message']);
        echo json_encode(array('error' => 'no'));
        exit();
    }
}
