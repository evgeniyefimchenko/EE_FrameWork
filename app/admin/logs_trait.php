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
    public function logs($param = array()) {
        $this->access = array(1);
        if (!SysClass::get_access_user($this->logged_in, $this->access) || array_filter($param)) {
            SysClass::return_to_main();
            exit();
        }
        /* model */
        $this->load_model('m_logs', array($this->logged_in));
        /* get user data - все переменные пользователя доступны в представлениях */
        $user_data = $this->models['m_logs']->data;
        $this->get_user_data($user_data);
        $log_items = $this->models['m_logs']->get_general_logs();
        $get_API_logs = $this->models['m_logs']->get_API_logs();
        $text_logs = [];
        krsort($text_logs);
        foreach ($log_items as $key => $value) {
            $log_items[$key]['who'] = $this->models['m_logs']->get_text_role($value['who']);
        }
        
        $files = ['test', 'test2'];
        
        /* view */
        $this->get_standart_view();
        $this->view->set('text_logs', $text_logs);
        $this->view->set('get_API_logs', $get_API_logs);
        $this->view->set('log_items', $log_items);
        $this->view->set('files', $files);
        $this->view->set('body_view', $this->view->read('v_logs'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<!-- Footable -->
		<script src="/assets/node_modules/moment/moment.js"></script>
		<script src="/assets/node_modules/footable/js/footable.min.js"></script>';
        $this->parameters_layout["add_style"] .= '<!-- Footable CSS -->
        <link href="/assets/node_modules/footable/css/footable.bootstrap.min.css" rel="stylesheet">';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/logs.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Systems/Panel';
        $this->show_layout($this->parameters_layout);
    }

    public function logs_actions($param = []) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access) || array_filter($param)) {
            SysClass::return_to_main();
            exit();
        }
        if (count($_POST) < 1) {
            SysClass::return_to_main(200, '/admin/logs');
        }
        switch ($_POST['action_param']) {
            case 'kill_em_all' : $this->kill_em_all();
                break;
            case 'copy_all' : $res = $this->copy_all();
                break;
            case 'kill_copy_all' : $this->kill_copy_all();
                break;
        }
        SysClass::return_to_main(200, '/admin/logs');
    }

	/**
	* Очистить все таблицы без удаления проекта.
	* Таблицы нужно дополнять на своё усмотрение.
	* Оставит единственного пользователя admin с паролем admin
	*/
    private function kill_em_all() {		
        $this->load_model('m_logs', array($this->logged_in));
		$this->models['m_logs']->kill_db();
        $this->models['m_logs']->registration_new_user(array('name' => 'admin', 'email' => 'test@test.com', 'active' => '2', 'user_role' => '1', 'subscribed' => '1', 'comment' => 'Смените пароль администратора', 'pwd' => 'admin'));
    }

    private function copy_all() {
        return false; // Баги какие-то но в целом работает
        $dbhost = ENV_DB_HOST;   // Адрес сервера MySQL, обычно localhost
        $dbuser = ENV_DB_USER;   // имя пользователя базы данных
        $dbpass = ENV_DB_PASS;   // пароль пользователя базы данных
        $dbname = ENV_DB_NAME;   // название базы данных
        $return_value = '';
        $dir = ENV_SITE_PATH . '/' . ENV_BACKUP_CAT . '/';
        $kill_hour = 190; // Через сколько начинать удалять старые копии
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        if (!is_writable($dir)) {
            die('Директория ' . $dir . ' не доступна для записи.');
        }
        $dbbackup = $dir . 'db_copy-' . date("d.m.Y-H:i:s") . '.sql.gz';
        system("mysqldump -h $dbhost -u $dbuser --password='$dbpass' $dbname | gzip > $dbbackup");
        if (file_exists($dbbackup)) {
            $res .= 'Архив создан ' . $dbbackup . PHP_EOL;
        } else {
            $res .= 'Ошибка создания арихива БД ' . $dbbackup . PHP_EOL;
        }

        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if (filemtime($dir . $file) < strtotime('-' . $kill_hour . ' hours') && $file != '.' && $file != '..' && $file != 'db_upload.php') {
                    $res .= 'dell file=' . $dir . $file . PHP_EOL;
                    $count++;
                    if (unlink($dir . $file)) {
                        $res .= 'Удалён файл ' . $dir . $file . PHP_EOL;
                    } else {
                        $res .= 'Не удаётся удалить файл ' . $dir . $file . PHP_EOL;
                    }
                }
            }
            closedir($dh);
        }
        if (!$count) {
            $res .= 'Удалять пока нечего.' . PHP_EOL . '--------------------------------------------------------';
        }
        file_put_contents($dir . 'logs_db.txt', date('d.m.Y H:i:s') . ' : ' . $res . PHP_EOL, FILE_APPEND | LOCK_EX);

        SysClass::copydirect(ENV_SITE_PATH, $dir . 'files' . ENV_DIRSEP . date('d-m-Y-H:i:s'), true, [ENV_SITE_PATH . ENV_BACKUP_CAT]);
        SysClass::create_zip_archive(ENV_SITE_PATH, $dir . 'files' . ENV_DIRSEP . date('d-m-Y-H:i:s') . '.zip', $dir . 'files' . ENV_DIRSEP . date('d-m-Y-H:i:s'));
    }

    private function kill_copy_all() {
        die('kill_copy_all');
    }
    
}
