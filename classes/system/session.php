<?php
namespace classes\system;

/**
 * Класс работы с сессиями.
 * При инициализации устанавливает время жизни сессии
 * из конфигурационного файла.
 * 
 * @author Evgeniy Efimchenko efimchenko.com
 */
class Session {

    /**
     * Инициализирует сессию, если она еще не была начата
     * Устанавливает максимальное время жизни сессии и запускает сессию, если заголовки еще не были отправлены
     * Эта функция является статической и вызывается без создания экземпляра класса
     */
    private static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                ini_set('session.gc_maxlifetime', (string) (defined('ENV_TIME_AUTH_SESSION') ? ENV_TIME_AUTH_SESSION : 1440));
                session_start();
            }
        }
    }

    /**
     * Устанавливает значение в сессии после его санитизации
     * Эта функция предназначена для безопасного сохранения данных в сессионных переменных
     * Она сначала инициализирует сессию (если это еще не было сделано), затем санитизирует ключ и значение,
     * и сохраняет их в сессии. Для массивов используется рекурсивная функция санитизации, для строк - `filter_var`
     * @param string $key Ключ сессионной переменной, который будет санитизирован и использован для сохранения значения
     * @param mixed $value Значение для сохранения в сессии. Может быть строкой, массивом или другим типом данных
     * @return bool Возвращает true, если значение было успешно установлено в сессию, и false, если нет
     */
    public static function set(string $key, mixed $value): bool {
        self::init();
        if (is_array($value)) {
            $cleared_value = self::sanitize_recursive($value);
        } else {
            $cleared_value = filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
        $cleared_key = filter_var($key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $_SESSION[$cleared_key] = $cleared_value;
        return isset($_SESSION[$cleared_key]);
    }

    /**
     * Рекурсивная санитизация значений массива
     * Если значение является массивом, функция рекурсивно проходит по каждому элементу и применяет фильтрацию
     * В противном случае используется фильтрация с помощью `filter_var`
     * @param mixed $value Значение для санитизации. Может быть строкой или массивом
     * @return mixed Возвращает санитизированное значение или массив
     */
    private static function sanitize_recursive(mixed $value): mixed {
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = self::sanitize_recursive($val);
            }
            return $value;
        } else {
            return filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
    }

    /**
     * Получает значение из сессии по указанному ключу
     * Если ключ не указан, возвращает все значения сессии в виде строки
     * @param string|null $key Ключ для значения сессии
     * @return mixed Возвращает значение сессии по ключу или все значения сессии в виде строки
     */
    public static function get(?string $key = null): mixed {
        self::init();
        if ($key !== null && isset($_SESSION[$key])) {
            return $_SESSION[$key];
        } elseif ($key === null) {
            return implode(', ', array_map(function ($v, $k) {
                return sprintf("%s = '%s'", $k, $v);
            }, $_SESSION, array_keys($_SESSION)));
        }
        return null;
    }

    /**
     * Удаляет значение из сессии по указанному ключу
     * @param string $key Ключ для удаления из сессии
     * @return bool Возвращает true, если значение удалено, иначе false
     */
    public static function un_set(string $key): bool {
        self::init();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
            return true;
        }
        return false;
    }

    /**
     * Уничтожает текущую сессию
     * Очищает все данные и завершает сессию
     */
    public static function destroy(): void {
        self::init();
        session_unset();
        session_destroy();
    }
}
