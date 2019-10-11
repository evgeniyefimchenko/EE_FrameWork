<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

require_once('messages_trait.php');
require_once('notifications_trait.php');

/*
 * Админ-панель
 */

Class Controller_index Extends Controller_Base {
    
	/* Подключение traits */
	use messages_trait,
    notifications_trait;

    /**
     * Главная страница админ-панели
     */
    public function index($param = []) {		
        /* $this->access Массив с перечнем ролей пользователей которым разрешён доступ к странице
		* 1-админ 2-модератор 3-пользователь
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
        } elseif($user_data['user_role'] == 2) {                                // Доступ для модератора
            $this->view->set('body_view', 'Доступ для модератора, пока без представления');
        } elseif($user_data['user_role'] == 3) {                                // Доступ для менеджера
            $this->view->set('body_view', 'Доступ для менеджера, пока без представления');
        } else {																// Обычный пользователь
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
        if (!SysClass::get_access_user($this->logged_in, $this->access) || count($arg)>0) {
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

}
