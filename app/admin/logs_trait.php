<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Функции работы с логами
 */

trait logs_trait {
  
	/**
	* Вывод страницы с логами
	*/
    public function logs($param = array()){
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access) || array_filter($param) || !ENV_LOG) {
            SysClass::return_to_main();
            exit();
        }
        /* model */
        $this->load_model('m_logs', array($this->logged_in));
        /* get user data - все переменные пользователя доступны в представлениях */
        $user_data = $this->models['m_logs']->data;
		$this->get_user_data($user_data);
		$log_items = $this->models['m_logs']->get_logs();
		foreach($log_items as $key=>$value) {
			$log_items[$key]['who'] = $this->models['m_logs']->get_text_role($value['who']);
		}			
        /* view */
		$this->get_standart_view();
        $this->view->set('log_items', $log_items);
        $this->view->set('body_view', $this->view->read('v_logs'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/plugins/DataTables/datatables.min.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->get_path_controller() . '/js/plugins/DataTables/datatables.min.css"/>';		
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/logs.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Административная панель/Журнал действий';
        $this->show_layout($this->parameters_layout);		
    }

}