<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/*
 * Админпанель
 */
Class Controller_index Extends Controller_Base {

    /**
     * Главная страница админ панели
     */
    public function index() {
        /* model */
        $this->load_model('m_index', array($this->logged));
        /* search user data */
		$user_data = $this->models['m_index']->data;
		foreach ($user_data as $name => $val) {
			$this->view->set($name, $val);
		}		
        /* view */
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/admin/js/dashboard.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Административная панель/Главная';
		$this->parameters_layout["description"] = 'EE_FRAMEFORK - Лёгкий PHP MVC фреймворк';
		$this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
		$this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->show_layout($this->parameters_layout);
    }
}
