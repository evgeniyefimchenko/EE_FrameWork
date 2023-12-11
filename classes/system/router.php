<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

namespace classes\system;

/**
 * Роутинг проекта
 * при отсутствии контроллера или действия в указанном пути
 * подключаются index, что делает невозможным вызов файла напрямую
 * из директории
 * @author Evgeniy Efimchenko efimchenko.ru 
 */
Class Router {

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
        $path = '/' . $path;
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

    private function getController(&$file, &$controller, &$action, &$args) { 
        if (ENV_TEST) {
            echo 'route= ' . $_GET['route'] . '<br/>';
        }
        $get_route = (empty($_GET['route'])) ? '' : filter_var($_GET['route'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (isset($_GET['route']) && $_GET['route'] != $get_route) { // Проверка валидности запроса
            if (ENV_TEST) {
                die('Ошибка проверки запроса = ' . $get_route);
            }
            Sysclass::return_to_main(404);
        }

        $add_path = 0;
        if (empty($get_route)) {
            $route = 'index';
        } else {
            $route = $this->remove_double_path($get_route);
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
            $this->path .= $add_path === 0 ? 'index/' : '';
            $controller = 'index';
        }

        $file = $this->path . $controller . '.php';

        if (is_readable($file) == false) {
            if (ENV_TEST) {
                die('not readable ' . $file);
            }
            Sysclass::return_to_main(404);
            exit;
        }

        $args = $parts;
        include_once ($file);
        $action = array_shift($args);

        $temp_class = 'Controller_' . $controller;
        if (ENV_TEST) {
            echo '<pre>';            
            var_dump(['file' => $file, 'controller' => $temp_class, 'action' => $action, 'args' => $args]);
            echo '</pre>';
        }        
        if (empty($action) || !method_exists(new $temp_class, $action)) {
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
        $class = 'Controller_' . $controller;
        $view = new View();        
		$controller = new $class($view);
        if (is_callable(array($controller, $action)) == false) {
            if (ENV_TEST) {
                die('</br></br>is_callable class= ' . $class . ' action= ' . $action);
            }
            Sysclass::return_to_main(404);
            exit;
        }		
        $controller->$action($args);
    }

    /**
     * Удаляет все index и лишние слэши
     * вернёт текущий путь или выполнит редирект 307 на валидный
     */
    private function remove_double_path($param) { 
        $dell_index = preg_replace('/index/', '', $param);
        $dell_duble_slash = preg_replace('/(?<!:)[\/]{2,}/', '', $dell_index);
        $dell_end_slash = preg_replace('/\/{1,}$/', '', $dell_duble_slash); 
        if ($dell_end_slash == $param) {		
            return $dell_end_slash;
        } else {
            Sysclass::return_to_main(307, ENV_URL_SITE . ENV_DIRSEP . $dell_end_slash);
        }
    }

}
