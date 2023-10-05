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
     * 100 - все зарегистрированные пользователи
     * Если не указать доступ в контроллере то будет запрещено загружать любую можель
     */
    protected $access = [];

    /**
     * Содержит id пользователя если он авторизован
     */
    protected $logged_in;

    /**
     * Массив с подключенными моделями
     */
    protected $models = [];

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
    protected $parameters_layout = ['add_script' => '', 'add_style' => '', 'canonical_href' => ENV_URL_SITE, 'layout' => 'index', 'image_twiter' => 'favicon.png', 'image_social' => 'favicon.png'];

    /**
     * Конструктор класса принимает экземпляр класса представления из /classes/system/route.php
     * Проверяет сессию пользователя и записывает id в logged_in
     * @view - экземпляр класса представления
     */
    function __construct($view = '') {
        if (ENV_AUTH_USER === 2) {
            $session = Cookies::get('user_session');
        } else {
            $session = Session::get('user_session');
        }
        if ($session) {
            if (!SysClass::connect_db_exists()) {
                Session::destroy();
            } else {
                if (!$this->logged_in) $this->logged_in = $this->get_users_session_data($session);
            }
        }
        $php_input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($php_input)) {
            $php_input = [];
        }
        if (!is_array($_REQUEST)) {
            $_REQUEST = [];
        }
        if (!is_array($_GET)) {
            $_GET = [];
        }
        if (!is_array($_POST)) {
            $_POST = [];
        }
        if (!is_array($_SERVER)) {
            $_SERVER = [];
        }
        $_REQUEST = array_merge($php_input, $_REQUEST, $_GET, $_POST, $_SERVER);
        $this->view = $view;
    }

    /**
     * Загружает модель для контроллера по абсолютному пути
     * @param str $model - имя файла модели без расширения m_index
     * @param array $arg - Массив возможных аргументов для модели
     * @param str $path - если указан путь, то загрузка модели произойдёт только по нему
     * @param bool $reload - перезагружать модель если она уже вызвана
     */
    protected function load_model($model, $arg = [], $path = '', $reload = false) {
        if (count($this->access) == 0) {
            SysClass::pre_file('error', 'Не указаны права доступа. ' . $model);
            SysClass::pre('Не указаны права доступа. ' . $model);
        }
        $stack = debug_backtrace();
        $stack = dirname($stack[0]['file']);
        $file = $stack . ENV_DIRSEP . 'model' . ENV_DIRSEP . $model . '.php';
        $class = 'Model_' . substr($model, 2);
        if ($path) {
            $file = ENV_SITE_PATH . $path . ENV_DIRSEP . $model . '.php';
        }
        if (file_exists($file)) {
            include_once($file);
            if (class_exists($class)) {
                if (!isset($this->models[$model]) || $reload) {
                    $this->models[$model] = new $class($arg);
                }
            } else {
                trigger_error('Класс не найден или уже загружен ' . $file);                
                exit();
            }
        } else {
			SysClass::pre('Модель не найдена ' . $model);			
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
			$layout = $layout ? $layout : 'UNKNOWN';
			SysClass::pre('layout ' . $layout . ' не найден!');
		}
		$this->html = ob_get_contents();
		ob_end_clean();

		// Выделяем участок от начала страницы до <!-- JS scripts -->
		preg_match("/^(.*?)(<!-- JS scripts -->)/si", $this->html, $matches);
		if (isset($matches[1])) {
			$beforeJsScripts = $matches[1];

			// Находим все скрипты в этом участке
			preg_match_all("/<script.*?<\/script>/si", $beforeJsScripts, $scriptMatches);

			if (isset($scriptMatches[0]) && $scriptMatches[0]) {
				// Удаляем найденные скрипты из этого участка
				foreach ($scriptMatches[0] as $script) {
					$this->html = str_replace($script, '', $this->html);
				}

				// Вставляем скрипты сразу после <!-- ported scripts -->
				$scripts = implode("\n", $scriptMatches[0]);
				$this->html = str_replace('<!-- ported scripts -->', "<!-- ported scripts -->\n" . $scripts, $this->html);
			}
		}

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
        if (ENV_AUTH_USER === 1) {
            $sql = 'SELECT `id`, `last_ip` FROM ?n WHERE `session` = ?s';
            $user = SafeMySQL::gi()->getRow($sql, ENV_DB_PREF . 'users', $session);
        }
        if (ENV_AUTH_USER === 0) {
            $user_id = Session::get('user_id');
            $sql = 'SELECT `id`, `last_ip` FROM ?n WHERE `id` = ?i';
            $user = SafeMySQL::gi()->getRow($sql, ENV_DB_PREF . 'users', $user_id);
        }
        if (ENV_AUTH_USER === 2) {
            $user_id = Cookies::get('user_id');
            $sql = 'SELECT `id`, `last_ip` FROM ?n WHERE `id` = ?i';
            $user = SafeMySQL::gi()->getRow($sql, ENV_DB_PREF . 'users', $user_id);
        }
        if (ENV_ONE_IP_ONE_USER && $user['last_ip'] !== SysClass::client_ip()) {
            return false;
        } else {
            $sql = 'UPDATE ?n SET `last_activ` = NOW() WHERE `id` = ?i';
            SafeMySQL::gi()->query($sql, ENV_DB_PREF . 'users', $user['id']);
            return $user['id'];
        }
    }

}
