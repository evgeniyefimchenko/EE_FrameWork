<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Базовый абстрактный класс для всех контроллеров проекта
 *
 * @author Evgeniy Efimchenko efimchenko.ru 
 */
Abstract Class Controller_Base {

    /**
     * Начальная страница для каждого контроллера обязательна
	 * необходимо обрабатывать параметры для исключения дублей главной страницы
     */
    abstract function index($param = []);

    /**
     * Массив содержащий ID ролей пользователей имеющих доступ к странице
     * инициализируется внутри каждой функции контроллера
     */
    protected $access = array();

    /**
     * Содержит id пользователя если он авторизован
     */
    protected $logged_in;

    /**
     * Массив с подключенными моделями
     */
    protected $models = array();

    /**
     * Экземпляр класса для работы с представлениями
     */
    protected $view;

    /**
     * Собирает весь HTML код для вывода на страницу
     */
    protected $html;

    /**
     * Содержит начальные папраметры макета(layout) определённого в контроллере
     */
    protected $parameters_layout = array('canonical_href' => ENV_URL_SITE, 'layout' => 'index', 'image_twiter' => 'favicon.png', 'image_social' => 'favicon.png');

    /**
     * Конструктор класса принимает экземпляр класса представления из /classes/system/route.php
     * Проверяет сессию пользователя и записывает id в logged_in
     * @view - экземпляр класса представления
     */
    function __construct($view = '') {
        $session = Session::get('user_session');		
        if ($session) {
			if (!SysClass::connect_db_exists() || SafeMySQL::gi()->query('show tables like ?s', ENV_DB_PREF.'users')->{"num_rows"} === 0) {
				Session::destroy();
			} else {
				$this->logged_in = $this->get_users_session_data($session);
			}
        }
        $this->view = $view;
    }

    /**
     * Загружает модель для контроллера по абсолютному пути
     * @param str $model - имя файла модели без расширения m_index
     * @param array $arg - Массив возможных аргументов для модели
     * @param str $path - если указан путь, то загрузка модели произойдёт только по нему
     */

    protected function load_model($model, $arg = array(), $path = '') {
        $stack = debug_backtrace();
        $stack = dirname($stack[0]['file']);
        $file = $stack . ENV_DIRSEP . 'model' . ENV_DIRSEP . $model . '.php';
        $class = 'Model_' . substr($model, 2);
        if ($path) {
            $file = ENV_SITE_PATH . $path . ENV_DIRSEP . $model . '.php';
        }        
        if (file_exists($file)) {
            include_once($file);
            $this->models[$model] = new $class($arg);
        } else {
            trigger_error('Модель не найдена ' . $file);
            exit();
        }
    }

    /**
     * Выводит макет в сборе с представлением и компрессией кода при ENV_COMPRESS_HTML
     * @param - дополнительные или переопределённые параметры макета в именнованом массиве (parameters_layout)
     * @layout - название макета, берётся из параметров или index по умолчанию
     */

    protected function show_layout($param) {
        extract($param);		
        $file = ENV_SITE_PATH . 'layouts/' . $layout . '.php';
        ob_start();
        if (file_exists($file)) {
			include_once $file;
		} else {
			die('layout '.$layout.' не найден!');
		}
        $this->html = ob_get_contents();
        ob_end_clean();
        if (ENV_COMPRESS_HTML) {
            echo Sysclass::one_line($this->html);
        } else {
            echo $this->html;
        }
    }

    /**
     * Вернёт URL путь к папке контроллера
     */
    public function get_path_controller() {
        $stack = debug_backtrace();
        $stack_dir = dirname($stack[0]['file']);
        return ENV_URL_SITE . substr($stack_dir, strpos($stack_dir, ENV_DIRSEP . ENV_APP_DIRECTORY));
    }

    /**
     * Ищет текущую сессию пользователя в базе, обновляет крайнее время на сайте 
     * @session - сессия с сервера
     * @return id пользователя или false
     */
    private function get_users_session_data($session) {
		$table = ENV_DB_PREF . 'users';
        $sql = 'SELECT `id`, `last_ip` FROM ?n WHERE `session` = ?s';
        $user = SafeMySQL::gi()->getRow($sql, $table, $session);
        if ($user['last_ip'] !== SysClass::client_ip()) {
            return false;
        } else {
            $sql = 'UPDATE ?n SET `last_activ` = NOW() WHERE `id` = ?i';
            SafeMySQL::gi()->query($sql, $table, $user['id']);
            return $user['id'];
        }
    }

}
