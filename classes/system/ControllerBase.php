<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

/**
 * Базовый абстрактный класс для всех контроллеров проекта
 */
abstract class ControllerBase {

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
     * Экземпляр класса для работы с пользователями
     */
    protected $users;

    /**
     * Собирает весь HTML код для вывода на страницу
     */
    protected $html;

    /**
     * Содержит начальные папраметры макета(layout) определённого в контроллере
     */
    protected $parameters_layout = ['add_script' => '', 'add_style' => '', 'canonical_href' => ENV_URL_SITE, 'layout' => 'index', 'image_twiter' => 'favicon.png', 'image_social' => 'favicon.png'];

    /**
     * Конструктор класса принимает экземпляр класса представления из /classes/system/Router.php.
     * Проверяет сессию пользователя и записывает id в logged_in.
     * @param mixed $view Экземпляр класса представления.
     */
    function __construct($view = null) {
        $this->view = $view instanceof View ? $view : new View();
        $sessionKey = ENV_AUTH_USER === 2 ? 'user_session' : 'user_session';
        $session = ENV_AUTH_USER === 2 ? Cookies::get($sessionKey) : Session::get($sessionKey);
        if ($session && SysClass::check_install()) {
            $this->logged_in = $this->logged_in ?? $this->get_users_session_data($session);
        } else {
            Session::destroy();
            Cookies::clear('user_session');
        }        
        $this->users = new Users($this->logged_in);
        $php_input = json_decode(file_get_contents('php://input'), true) ?? [];
        $_REQUEST = array_merge($php_input, $_REQUEST, $_GET, $_POST, $_SERVER);
    }

    /**
     * Загружает модель для контроллера.
     * @param string $model Имя файла модели без расширения, например 'm_index'.
     * @param array $arg Массив аргументов для передачи в конструктор модели.
     * @param string $path Опциональный абсолютный путь к модели.
     * @param bool $reload Определяет, нужно ли перезагружать модель, если она уже была загружена ранее.
     * @throws Exception Если модель или класс модели не найдены.
     */
    protected function load_model(string $model, array $arg = [], string $path = '', bool $reload = false): void {
        if (count($this->access) == 0) {
            SysClass::pre('Не указаны права доступа на ' . $model);
        }
        // Определение пути к модели
        $parts = explode('_', substr($model, 2));
        $transformedParts = array_map(function($part) {
            return ucfirst($part);
        }, $parts);
        $className = implode('', $transformedParts);
        $className = 'Model' . $className;
        $modelPath = $path ? $path : dirname(ENV_CONTROLLER_PATH) . '/models/' . $className . '.php';
        if (!file_exists($modelPath)) {
            SysClass::pre('Модель не найдена: ' . $modelPath);
        }
        include_once($modelPath);        
        if (!class_exists($className)) {
            SysClass::pre('Класс модели не найден: ' . $className);
        }
        if (!isset($this->models[$model]) || $reload) {
            $this->models[$model] = new $className(...$arg);
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
        $stack_dir = dirname(ENV_CONTROLLER_PATH);       
        return ENV_URL_SITE . substr($stack_dir, strpos($stack_dir, ENV_DIRSEP . ENV_APP_DIRECTORY));
    }

    /**
     * Ищет текущую сессию пользователя в базе, обновляет крайнее время на сайте 
     * @session - сессия с сервера
     * @return id пользователя или false
     */
    private function get_users_session_data($session) {
        if (ENV_AUTH_USER === 0) {
            $user_id = Session::get('user_id');
            $sql = 'SELECT `id`, `last_ip` FROM ?n WHERE `id` = ?i';
            $user_data = SafeMySQL::gi()->getRow($sql, ENV_DB_PREF . 'users', $user_id);
        }
        if (ENV_AUTH_USER === 1) {
            $sql = 'SELECT `id`, `last_ip` FROM ?n WHERE `session` = ?s';
            $user_data = SafeMySQL::gi()->getRow($sql, ENV_DB_PREF . 'users', $session);
        }
        if (ENV_AUTH_USER === 2) {
            $user_id = Cookies::get('user_id');
            $sql = 'SELECT `id`, `last_ip` FROM ?n WHERE `id` = ?i';
            $user_data = SafeMySQL::gi()->getRow($sql, ENV_DB_PREF . 'users', $user_id);
        }
        if (ENV_ONE_IP_ONE_USER && $user_data['last_ip'] !== SysClass::client_ip()) {
            return false;
        } else {
            $sql = 'UPDATE ?n SET `last_activ` = NOW() WHERE `id` = ?i';
            SafeMySQL::gi()->query($sql, ENV_DB_PREF . 'users', $user_data['id']);
            return $user_data['id'];
        }
    }

}