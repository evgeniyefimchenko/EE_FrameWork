<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Функции работы с сообщениями
 */

trait messages_trait {
    /**
     * Страница сообщений пользователя
     * все сообщения подгружаются в системном классе users
     * при get_user_data
     */
    public function messages($arg = array()) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(401);
            exit();
        }
        /* model */
        $this->load_model('m_messages', array($this->logged_in));
        /* get data */
        $user_data = $this->models['m_messages']->data;
        foreach ($user_data as $name => $val) {
            $this->view->set($name, $val);
        }
        $get_user_id = is_numeric($arg[0]) ? $arg[0] : $this->logged_in;

        if ($this->logged_in != $get_user_id && $user_data['user_role'] <= 3) {
            $class_messages = new Class_messages();
            $this->view->set('count_message', $class_messages->get_count_messages($get_user_id), TRUE);
            $this->view->set('messages', $class_messages->get_messages_user($get_user_id), TRUE);
            $this->view->set('moderation', TRUE);
            unset($class_messages);
        } else {
            /* notifications - Удалить оповещение о непрочитанных сообщениях */
            $notification = new Class_notifications();
            $notification->kill_notification_by_text($this->logged_in, 'непрочитанное сообщение');
        }
        /* view */
        $this->show_standart_view();
        $this->view->set('body_view', $this->view->read('v_messages'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/messages.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Административная панель/Сообщения';
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Отметить все сообщения пользователя прочитанными
     */
    public function read_all_message() {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(401);
            exit();
        }
        $this->load_model('m_messages');        
        $this->models['m_messages']->read_all($this->logged_in);        
    }
    
    /**
     * Удалить все сообщения пользователя
     */
    public function kill_all_message() {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(401);
            exit();
        }
        $this->load_model('m_messages');        
        $this->models['m_messages']->kill_all_message($this->logged_in);        
    }
    
    /**
     * Пометить сообщение прочитанным
     * @param array $param - ID сообщения
     */
    public function set_readed($param) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(401);
            exit();
        }
        $message_id = filter_var($param[0], FILTER_VALIDATE_INT);
        $this->load_model('m_messages');
        $this->models['m_messages']->set_message_as_readed($message_id, $this->logged_in);
    }
    
	/**
	* Удалит все оповещения пользователя
	* связанные с непрочитанными сообщениями
	*/
    public function set_readed_all() {
        $notification = new Class_notifications();
        $notification->kill_notification_by_text($this->logged_in, 'непрочитанное сообщение');
        $this->set_readed();
    }
    
    /**
     * Удалить сообщение
     * @param array $param - ID сообщения
     */
    public function dell_message($param) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(401);
            exit();
        }
        $message_id = filter_var($param[0], FILTER_VALIDATE_INT);
        $this->load_model('m_messages');
        $this->models['m_messages']->kill_message($message_id, $this->logged_in);
    }    
}