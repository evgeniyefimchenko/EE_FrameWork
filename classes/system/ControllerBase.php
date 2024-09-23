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

    protected $lang = []; // Языковые переменные
    
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
     * Класс PHP хуков     
     */
    protected $hooks = [];

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
    protected $parameters_layout = ['description' => '', 'keywords' => '', 'add_script' => '', 'add_style' => '', 'canonical_href' => ENV_URL_SITE, 'layout' => 'index', 'imagePage' => '/favicon.png'];

    /**
     * Конструктор класса принимает экземпляр класса представления из /classes/system/Router.php
     * Проверяет сессию пользователя и записывает id в logged_in
     * @param mixed $view Экземпляр класса представления
     */
    function __construct($view = null) {
        SysClass::checkInstall(); // Подумать над кешированием проверки TODO
        $this->view = $view instanceof View ? $view : new View();
        $session = ENV_AUTH_USER === 2 ? Cookies::get('user_session') : Session::get('user_session');
        if ($session) {
            $this->logged_in = $this->logged_in ?? $this->getUsersSessionData($session);
        } else {
            Session::destroy();
            Cookies::clear('user_session');
        }        
        $this->users = new Users($this->logged_in);
        $user_data = $this->users->data;
        // Прогрузка пользовательских данных в представления и языковой массив        
        $this->set_user_data($user_data);
        // Объединение серверных параметров
        $input_data = file_get_contents('php://input');
        $php_input = SysClass::ee_isValidJson($input_data) ? json_decode($input_data, true) : [$input_data];
        define('__REQUEST', array_merge($php_input, $_REQUEST, $_GET, $_POST, $_SERVER));
    }
    
    /**
     * Загрузит в представление данные пользователя
     * И языковой массив
     * @param $user_data - Данные пользователя для загрузки
     */
    private function set_user_data($user_data) {
        global $global_lang;
        $get_lang_code = '';
        if (!isset($user_data['new_user']) || $user_data['new_user'] != 1) {
            foreach ($user_data as $name => $val) {
                $this->view->set($name, $val);
            }
            $s_lang = Session::get('lang');
            if (strlen($user_data['options']['localize']) > 1 && empty($s_lang)) { // Проверка на наличие локали в настройках пользователя
                $get_lang_code = 'user_data options localize';
                $lang_code = $user_data['options']['localize'];
            } else { // Записываем локаль в опции пользователя
                $get_lang_code = 'user_data Session';
                $lang_code = $s_lang;
                $user_data['options']['localize'] = $lang_code;
                $this->users->set_user_options($this->logged_in, $user_data['options']);
            }
        } else {
            $lang_code = Session::get('lang');
            $get_lang_code = 'Session';
            if (!$lang_code) {
                $lang_code = ENV_DEF_LANG;
                $get_lang_code = 'ENV_DEF_LANG';
                Session::set('lang', $lang_code);
            }
        }
        $lang_path = ENV_SITE_PATH . ENV_PATH_LANG . '/' . $lang_code . '.php';
        $lang = file_exists($lang_path) ? include($lang_path) : false;
        if (!is_array($lang)) {
            SysClass::preFile('lang_errors', 'base get_user_data ' . var_export($get_lang_code, true), var_export($lang, true), 'Языковой файл не найден(подключаем ENV_PROTO_LANGUAGE'
                    . ' ' . ENV_PROTO_LANGUAGE . '): ' . $lang_path);
            $lang_path = ENV_SITE_PATH . ENV_PATH_LANG . '/' . ENV_PROTO_LANGUAGE . '.php';
            $lang_code = ENV_PROTO_LANGUAGE;
            if (file_exists($lang_path)) {
                $lang = include($lang_path);
            }
        }
        Session::set('lang', $lang_code);
        $this->view->set('lang', $lang);
        $global_lang = $lang;
        // Фильтрация массива языковых элементов, содержащих 'sys' в ключе
        $sysLang = array_filter($lang, function ($key) {
            return strpos($key, 'sys.') === 0;
        }, ARRAY_FILTER_USE_KEY);
        $this->users->lang = $sysLang;
        $this->lang = $lang;
    }

    /**
     * Загружает модель для контроллера
     * @param string $model Имя файла модели без расширения, например 'm_index'
     * @param array $arg Массив аргументов для передачи в конструктор модели
     * @param string $path Опциональный абсолютный путь к модели
     * @param bool $reload Определяет, нужно ли перезагружать модель, если она уже была загружена ранее
     * @throws Exception Если модель или класс модели не найдены
     */
    protected function loadModel(string $model, array $arg = [], string $path = '', bool $reload = false): void {
        if (count($this->access) == 0) {
            SysClass::pre('Не указаны права доступа на ' . $model, true);
        }
        // Определение пути к модели
        $parts = explode('_', substr($model, 2));
        $className = 'Model' . implode('', array_map('ucfirst', $parts));
        $modelPath = $path ? $path : dirname(ENV_CONTROLLER_PATH) . '/models/' . $className . '.php';
        if (!file_exists($modelPath)) {
            SysClass::pre('Модель не найдена: ' . $modelPath);
        }
        include_once($modelPath);        
        if (!class_exists($className)) {
            SysClass::pre('Класс модели не найден: ' . $className);
        }
        if (!isset($this->models[$model]) || $reload) {
            $this->models[$model] = new $className($arg);
        }
    }

    /**
     * Обрабатывает и отображает макет, перемещая JavaScript скрипты после указанного комментария, за исключением скриптов между специальными маркерами
     * Эта функция загружает указанный файл макета, обрабатывает его содержимое и перемещает JavaScript скрипты
     * Все скрипты, кроме тех, что находятся между <!-- start of non-relocatable JS scripts --> и <!-- end of non-relocatable JS scripts -->,
     * будут перемещены после маркера <!-- ported scripts -->, удаляя при этом дубликаты скриптов
     * Функция также поддерживает сжатие HTML если установлена соответствующая настройка
     * @param array $param Параметры для настройки макета, включая имя макета и другие значения
     */
    protected function showLayout($param) {
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
        // Сохраняем не перемещаемые скрипты и заменяем их маркером
        $nonRelocatableScriptMarker = 'NON_RELOCATABLE_SCRIPTS';
        preg_match("/<!-- start of non-relocatable JS scripts -->(.*?)<!-- end of non-relocatable JS scripts -->/si", $this->html, $nonRelocatableScripts);
        if (!empty($nonRelocatableScripts[1])) {
            $this->html = str_replace($nonRelocatableScripts[0], $nonRelocatableScriptMarker, $this->html);
        }
        // Находим, удаляем дубликаты и убираем все остальные скрипты
        preg_match_all("/<script.*?<\/script>/si", $this->html, $scriptMatches);
        $scriptsFound = array_unique($scriptMatches[0]);
        $this->html = preg_replace("/<script.*?<\/script>/si", '', $this->html);
        // Вставляем скрипты обратно в HTML после <!-- ported scripts -->
        $scriptsString = implode("\n", $scriptsFound);
        $this->html = str_replace('<!-- ported scripts -->', "<!-- ported scripts -->\n" . $scriptsString, $this->html);
        if (!empty($nonRelocatableScripts[1])) {
            $this->html = str_replace($nonRelocatableScriptMarker, $nonRelocatableScripts[0], $this->html);
        }
        if (ENV_COMPRESS_HTML) {
            echo Sysclass::minifyHtml($this->html);
        } else {
            echo $this->html;
        }
    }

    /**
     * Вернёт URL путь к папке контроллера
     */
    public function getPathController() {
        $stack_dir = dirname(ENV_CONTROLLER_PATH);       
        return ENV_URL_SITE . substr($stack_dir, strpos($stack_dir, ENV_DIRSEP . ENV_APP_DIRECTORY));
    }

    /**
     * Ищет текущую сессию пользователя в базе, обновляет крайнее время на сайте 
     * @session - сессия с сервера
     * @return id пользователя или false
     */
    private function getUsersSessionData($session) {
        $sql = 'SELECT user_id, last_ip FROM ?n WHERE session = ?s';
        $user_data = SafeMySQL::gi()->getRow($sql, ENV_DB_PREF . 'users', $session);
        if (ENV_ONE_IP_ONE_USER && $user_data['last_ip'] !== SysClass::client_ip()) {
            return false;
        } else {
            if (isset($user_data['user_id'])) {
                $sql = 'UPDATE ?n SET last_activ = NOW() WHERE user_id = ?i';
                SafeMySQL::gi()->query($sql, ENV_DB_PREF . 'users', $user_data['user_id']);
                return $user_data['user_id'];
            }
            return false;
        }
    }

}
