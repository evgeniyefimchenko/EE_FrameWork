<?php

namespace classes\system;

/*
 * Класс работы с сессиями
 * При инициализации устанавливает время жизни сессии
 * из конфигурационного файла
 * @author Evgeniy Efimchenko efimchenko.ru 
 */

class Session {

    /**
     * Инициализирует сессию, если она еще не была начата.
     * Устанавливает максимальное время жизни сессии и запускает сессию, если заголовки еще не были отправлены.
     * Эта функция является статической и вызывается без создания экземпляра класса.
     */
    private static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                ini_set('session.gc_maxlifetime', ENV_TIME_SESSION);
                session_start();
            }
        }
    }

    /**
     * Устанавливает значение в сессии после его санитизации.
     * Эта функция предназначена для безопасного сохранения данных в сессионных переменных.
     * Она сначала инициализирует сессию (если это еще не было сделано), затем санитизирует ключ и значение,
     * и сохраняет их в сессии. Для массивов используется `filter_input_array`, для строк - `filter_var`.
     * Функция возвращает true, если значение было успешно установлено и существует в сессии.
     * @param string $key Ключ сессионной переменной, который будет санитизирован и использован для сохранения значения.
     * @param mixed $value Значение для сохранения в сессии. Может быть как строкой, так и массивом.
     * @return bool Возвращает true, если значение было успешно установлено в сессию, и false, если нет.
     */
    public static function set($key, $value) {
        self::init();
        if (is_array($value)) {
            $cleared_value = filter_input_array(FILTER_SANITIZE_STRING, $value);
        } else {
            $cleared_value = filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
        $cleared_key = filter_var($key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $_SESSION[$cleared_key] = $cleared_value;
        return isset($_SESSION[$cleared_key]);
    }

    /**
     * Получает значение из сессии по указанному ключу.
     * Если ключ не указан, возвращает все значения сессии в виде строки.
     * @param string $key Ключ для значения сессии.
     * @return mixed Возвращает значение сессии по ключу или все значения сессии в виде строки.
     */
    public static function get($key = '') {
        self::init();
        if ($key !== '' && isset($_SESSION[$key])) {
            return $_SESSION[$key];
        } elseif ($key === '') {
            return implode(', ', array_map(function ($v, $k) {
                        return sprintf("%s = '%s'", $k, $v);
                    }, $_SESSION, array_keys($_SESSION)));
        }
        return null;
    }

    /**
     * Удаляет значение из сессии по указанному ключу.
     * @param string $key Ключ для удаления из сессии.
     * @return bool Возвращает true, если значение удалено, иначе false.
     */
    public static function un_set($key) {
        self::init();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
            return true;
        }
        return false;
    }

    /**
     * Уничтожает текущую сессию.
     */
    public static function destroy() {
        self::init();
        session_unset();
        session_destroy();
    }

}
