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

}
