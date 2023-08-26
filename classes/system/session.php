<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
/*
 * Класс работы с сессиями
 * При инициализации устанавливает время жизни сессии
 * из конфигурационного файла
 * @author Evgeniy Efimchenko efimchenko.ru 
 */

class Session {

    private static function init() {
        if (session_status() == PHP_SESSION_NONE) {
            headers_sent() ? NULL : ini_set('session.gc_maxlifetime', ENV_TIME_SESSION);
            headers_sent() ? NULL : session_start();
        }
    }

    public static function set($key, $value) {
        self::init();
        if (is_array($value)) {
            $cleared_value = filter_input_array(FILTER_SANITIZE_STRING, $value);
        } else {
            $cleared_value = filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
        $cleared_key = filter_var($key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $cleared_value = $value;
        $_SESSION[$cleared_key] = $cleared_value;
        return self::get($cleared_key) ? true : false;
    }

    /**
     * Вернёт значение сессии по ключу
     * Если ключ пустой то вернёт все значения сессии в одну строку
     */
    public static function get($key = '') {
        self::init();
        if (isset($_SESSION[$key])) {
            return $_SESSION[$key];
        } elseif ($key === '') {
            return implode(', ', array_map(function ($v, $k) {
                        return sprintf("%s = '%s'", $k, $v);
                    }, $_SESSION, array_keys($_SESSION)));
        }
    }

    public static function un_set($key) {
        self::init();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public static function destroy() {
        self::init();
        unset($_SESSION);
        headers_sent() ? NULL : session_destroy();
    }

}
