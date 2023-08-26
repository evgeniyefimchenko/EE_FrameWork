<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Класс представлений
 * устанавливает переменные и удаляет шаблону
 * Загружает представление в контроллер
 * @author Evgeniy Efimchenko efimchenko.ru 
 */
Class View {

    private $vars = array();

    /**
     * Установит переменную представления
     * @param str $varname - имя переменной
     * @param var $value - значение переменной
     * @param boolean $overwrite
     * @return boolean
     */
    public function set($varname, $value, $overwrite = false) {
        if (isset($this->vars[$varname]) == true && $overwrite == false) {
            trigger_error('Переменная `' . $varname . '`. Уже установлена, перезапись не разрешена.', E_USER_NOTICE);
            return false;
        }
        $this->vars[$varname] = $value;
        return true;
    }

    /**
     * Считывает переменную представления
     */
    public function get($varname) {
        if (isset($this->vars[$varname]) == true) {
            return $this->vars[$varname];
        } else {
            return FALSE;
        }
    }

    /**
     * Удаляет переменные представления
     * @param str $varname - имя переменной
     */
    function remove($varname) {
        unset($this->vars[$varname]);
    }

    /**
     * Загружает представление в контроллер
     * @param str $name - Имя представления
     * @param str $add_path - Доп. путь к представлению
     * @param boolean $full_path - флаг полного пути к представлению
     * @return boolean
     */
    public function read($name, $add_path = '', $full_path = FALSE) {
        if ($full_path) {
            $path = $name;
        } else {
            $stack = debug_backtrace();
            $stack = dirname($stack[0]['file']);
            $path = $stack . ENV_DIRSEP . 'view' . ENV_DIRSEP . $add_path . $name . '.php';
        }
        if (!file_exists($path)) {
            return 'Шаблон `' . $name . '` не существует. Полный путь: ' . $path;
        }

        foreach ($this->vars as $key => $value) {
            $$key = $value;
        }

        ob_start();
        include_once ($path);
        return ob_get_clean();
    }

    /**
     * Существует ли переданное представление
     * в папке представлений контроллера
     * @return полный путь или FALSE
     */
    public function view_exists($view) {
        $view_file = $view . '.php';
        $stack = debug_backtrace();
        $stack = dirname($stack[0]['file']);
        $path = $stack . ENV_DIRSEP . 'view';
        return Sysclass::search_file($path, $view_file);
    }

}
