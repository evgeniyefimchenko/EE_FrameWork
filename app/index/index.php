<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Класс контроллера главной страницы сайта
 */
Class Controller_index  Extends Controller_Base{

	/**
	* Главная страница проекта
	*/
    public function index() {
            if ($this->logged) {
				/*user logined*/
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
				$this->parameters_layout["add_script"] = '<script src="' . $this->get_path_controller() . '/js/main.js" type="text/javascript" /></script>';            
				$this->parameters_layout["add_style"] = '<link href="' . $this->get_path_controller() . '/css/main.css" rel="stylesheet" />';
				$this->parameters_layout["title"] = 'EE_FrameWork';
				$this->parameters_layout["description"] = 'EE_FRAMEFORK - Лёгкий PHP MVC фреймворк';
				$this->parameters_layout["keywords"] = Sysclass::keywords($this->html);				
				$this->parameters_layout["layout_content"] = $this->html;
				$this->show_layout($this->parameters_layout);   
			}
    }
}
