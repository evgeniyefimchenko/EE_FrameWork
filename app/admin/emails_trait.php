<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Функции работы с письмами
 */
trait emails_trait {
	
	/**
	* Редактирование шаблона писем
	*/
    public function edit_emails_templates($param = []) {
        $this->access = array(1);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        /* model */
        $this->load_model('m_settings', array($this->logged_in));
        /* get user data - все переменные пользователя доступны в представлениях */
        $user_data = $this->models['m_settings']->data;
        $this->get_user_data($user_data);
        /* view */
        $this->get_standart_view();        
        $path = ENV_EMAIL_TEMPLATE . ENV_DIRSEP . $param[0] . ENV_DIRSEP . 'body.tpl';
        $content_email = file_get_contents($path);
        $this->view->set('path_templ', $path);        
        $this->view->set('name_template', $param[0]);        
        $this->view->set('content_email', $content_email);        
        $this->view->set('body_view', $this->view->read('v_admin_edit_emails_tpl'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */       
        $this->parameters_layout["add_script"] .= '<script type="text/javascript" src="' . ENV_URL_SITE . '/assets/editor/tinymce/js/tinymce/tinymce.min.js"></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/edit_emails_templates.js" type="text/javascript" /></script>';
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = 'Emails Templates';
        $this->show_layout($this->parameters_layout);        
    }
    
    /**
     * Сохраняет шаблоны писем
     * @param array $param
     */
    public function ajax_func_save($param = []) {
        $this->access = array(1);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $path = SysClass::search_file(ENV_EMAIL_TEMPLATE, $_POST['template'], true);
        if ($path) {
            $boby_file = $path . ENV_DIRSEP . 'body.tpl';
            $head_file = $path . ENV_DIRSEP . 'header.tpl';
            $footer_file = $path . ENV_DIRSEP . 'footer.tpl';
            $full_file = $path . ENV_DIRSEP . 'mail.html';
            $body_content = SysClass::convert_img_to_base64($_POST['text_content'], $path);
            if (file_put_contents($boby_file, $body_content)) {
                $head_content = file_get_contents($head_file);                
                $footer_content = file_get_contents($footer_file);
                $all_content = $head_content . $body_content . $footer_content;
                if (!file_put_contents($full_file, SysClass::one_line($all_content))) {
                    echo json_encode(['error' => 'not save check permissions', 'path' => $full_file]);
                    die;
                }
                echo json_encode(['error' => 'no', 'body' => $boby_file]);
            } else {
                echo json_encode(['error' => 'not save check permissions', 'path' => $boby_file]);
            }            
        } else {
            echo json_encode(['error' => 'no content source']);
        }        
        die;
    }	
}