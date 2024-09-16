<?php

namespace classes\system;

use classes\system\SysClass;

/**
 * Роутинг проекта
 */
class Router {

    /**
     * Путь к контроллерам, задаётся в головном файле INDEX
     */
    private $path;

    /**
     * Устанавливает путь к контроллерам, задаётся в головном файле INDEX
     */
    public function setPath($path) {
        $path = trim($path, '/\\');
        $path .= ENV_DIRSEP;
        $path = ENV_DIRSEP . $path;
        if (is_dir($path) == false) {
            throw new Exception('Указана неверная папка для контроллеров: `' . $path . '`');
        }
        $this->path = $path;
    }

    /*
     * Отфильтровывает  и разбивает на параметры маршрут сайта
     * @file - полный путь к файлу контроллера для подключения
     * @controller - имя класса контроллера
     * @action - функция в классе контроллера
     * @args - массив параметров для функции класса контроллера
     * @return - работает с указателями на переменные
     */

    private function getController(mixed &$file, mixed &$controller, mixed &$action, mixed &$args): void {
        if (ENV_TEST && isset($_GET['route'])) {
            echo 'route= ' . $_GET['route'] . '<br/>';
        }
        $get_route = (empty($_GET['route'])) ? '' : filter_var($_GET['route'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (isset($_GET['route']) && $_GET['route'] != $get_route) { // Проверка валидности запроса
            if (ENV_TEST) {
                die('Ошибка проверки запроса = ' . $get_route);
            }
            Sysclass::handleRedirect(404);
        }
        $add_path = 0;
        if (empty($get_route)) {
            $route = 'index';
        } else {
            $route = $this->normalizeRoute($get_route);
        }
        $parts = explode('/', $route);
        /* Поиск папки и контроллера */
        foreach ($parts as $part) {
            $fullpath = $this->path . $part;
            if (is_dir($fullpath)) {
                $this->path .= $part . ENV_DIRSEP;
                array_shift($parts);
                $add_path++;
                continue;
            }
            if (is_file($fullpath . '.php')) {
                $controller = $part;
                array_shift($parts);
                break;
            } elseif (is_file($this->path . 'index' . ENV_DIRSEP . $part . '.php')) {
                $controller = $part;
                $this->path .= 'index' . ENV_DIRSEP;
                array_shift($parts);
                break;
            }
        }
        if (empty($controller)) {
            $this->path .= $add_path === 0 ? 'index' . ENV_DIRSEP : '';
            $controller = 'index';
        }
        $file = $this->path . $controller . '.php';
        if (is_readable($file) == false) {
            if (ENV_TEST) {
                die('not readable ' . $file);
            }
            Sysclass::handleRedirect(404);
            exit;
        }
        $args = $parts;
        include_once($file);
        $action = array_shift($args);
        $temp_class = 'Controller' . ucfirst($controller);
        if (ENV_TEST) {
            echo '<pre>';
            var_dump(['file' => $file, 'controller' => $temp_class, 'action' => $action, 'args' => $args]);
            echo '</pre>';
        }
        if (empty($action) || !method_exists($temp_class, $action)) {
            $action = 'index';
        }
    }

    /*
     * Подключение класса контроллера для страницы
     * @file - полный путь к файлу контроллера для подключения
     * @controller - имя класса контроллера для идентификации
     * @class - полное имя класса контроллера для подключения
     * @action - функция в классе контроллера
     * @args - массив параметров для функции класса контроллера
     */

    public function delegate() {        
        $this->getController($file, $controller, $action, $args);
        define('ENV_CONTROLLER_PATH', $file);
        define('ENV_CONTROLLER_NAME', $controller);
        define('ENV_CONTROLLER_ACTION', $action);
        define('ENV_CONTROLLER_ARGS', $args);
        $class = 'Controller' . ucfirst($controller);
        $view = new View();
        $controller = new $class($view);
        if (is_callable(array($controller, $action)) == false) {
            if (ENV_TEST) {
                die('</br></br>is_callable class= ' . $class . ' action= ' . $action);
            }
            Sysclass::handleRedirect(404);
            exit;
        }
        $controller->$action($args);
    }

    /**
     * Удаляет все index и лишние слэши
     * вернёт текущий путь или выполнит редирект 307 на валидный
     */
    private function normalizeRoute($param) {
        $dell_index = preg_replace('/index/', '', $param);
        $dell_duble_slash = preg_replace('/(?<!:)[\/]{2,}/', '', $dell_index);
        $dell_end_slash = preg_replace('/\/{1,}$/', '', $dell_duble_slash);
        if ($dell_end_slash == $param) {
            return $dell_end_slash;
        } else {
            Sysclass::handleRedirect(307, ENV_URL_SITE . ENV_DIRSEP . $dell_end_slash);
        }
    }

}
