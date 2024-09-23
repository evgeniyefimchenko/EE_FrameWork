<?php

namespace classes\system;

/**
 * Класс Cookies для работы с cookie: сохранение, чтение, обновление и удаление данных cookie
 * Поддерживается установка префикса и срока действия
 * @package classes\system
 */
class Cookies {

    private static string $_prefix = 'ee_';         // Префикс для всех cookie
    private static string $_securekey = 'efimchenko.com';  // Ключ шифрования cookie
    private static int $_expire = 2592000;             // Время жизни cookie по умолчанию 30 суток (в секундах)

    /**
     * Устанавливает cookie с шифрованием
     * @param string $name Имя cookie
     * @param mixed $value Значение cookie (может быть строкой, массивом и т.д.)
     * @param int $expire Время жизни cookie (в секундах)
     */
    public static function set(string $name, mixed $value, int $expire = 0): void {
        $cookie_name = self::getName($name);
        $cookie_expire = time() + ($expire ?: self::$_expire);
        $cookie_value = self::pack($value, $cookie_expire);
        $cookie_value = self::authcode($cookie_value, 'ENCODE');
        if ($cookie_name && $cookie_value && $cookie_expire) {
            setcookie($cookie_name, $cookie_value, $cookie_expire);
        }
        $_COOKIE[$cookie_name] = $cookie_value;
    }

    /**
     * Получает значение cookie и расшифровывает его.
     * @param string $name Имя cookie
     * @return mixed Возвращает значение cookie или null, если cookie не найдено.
     */
    public static function get(string $name): mixed {
        $cookie_name = self::getName($name);
        if (isset($_COOKIE[$cookie_name])) {
            $cookie_value = self::authcode($_COOKIE[$cookie_name], 'DECODE');
            $cookie_value = self::unpack($cookie_value);
            return $cookie_value[0] ?? null;
        }
        return null;
    }

    /**
     * Обновляет значение существующего cookie
     * @param string $name Имя cookie
     * @param mixed $value Новое значение для обновления
     * @return bool Возвращает true, если обновление прошло успешно, иначе false
     */
    public static function update(string $name, mixed $value): bool {
        $cookie_name = self::getName($name);
        if (isset($_COOKIE[$cookie_name])) {
            $old_cookie_value = self::authcode($_COOKIE[$cookie_name], 'DECODE');
            $old_cookie_value = self::unpack($old_cookie_value);
            if (isset($old_cookie_value[1]) && $old_cookie_value[1] > 0) {
                $cookie_expire = $old_cookie_value[1];
                $cookie_value = self::pack($value, $cookie_expire);
                $cookie_value = self::authcode($cookie_value, 'ENCODE');
                if ($cookie_name && $cookie_value && $cookie_expire) {
                    setcookie($cookie_name, $cookie_value, $cookie_expire);
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Очищает (удаляет) cookie
     * @param string $name Имя cookie для удаления
     */
    public static function clear(string $name): void {
        $cookie_name = self::getName($name);
        setcookie($cookie_name, '', time() - self::$_expire);
        if (isset($_COOKIE[$cookie_name])) unset($_COOKIE[$cookie_name]);
    }

    /**
     * Устанавливает префикс для имен cookie.
     * @param string $prefix Префикс для имен cookie
     */
    public static function setPrefix(string $prefix): void {
        if ($prefix !== '') {
            self::$_prefix = $prefix;
        }
    }

    /**
     * Устанавливает срок действия cookie.
     * @param int $expire Время жизни cookie (в секундах)
     */
    public static function setExpire(int $expire): void {
        if ($expire > 0) {
            self::$_expire = $expire;
        }
    }

    /**
     * Возвращает полное имя cookie с префиксом
     * @param string $name Имя cookie
     * @return string Полное имя cookie
     */
    private static function getName(string $name): string {
        return self::$_prefix . '_' . $name;
    }

    /**
     * Упаковывает значение и срок действия в JSON
     * @param mixed $data Данные для сохранения
     * @param int $expire Время жизни cookie
     * @return string JSON строка с данными и временем жизни
     */
    private static function pack(mixed $data, int $expire): string {
        return json_encode(['value' => $data, 'expire' => $expire]);
    }

    /**
     * Распаковывает значение из JSON и проверяет срок действия
     * @param string $data JSON строка
     * @return array Распакованные данные [значение, срок действия]
     */
    private static function unpack(string $data): array {
        $cookie_data = json_decode($data, true);
        if (isset($cookie_data['value']) && isset($cookie_data['expire'])) {
            if (time() < $cookie_data['expire']) {
                return [$cookie_data['value'], $cookie_data['expire']];
            }
        }
        return ['', 0];
    }

    /**
     * Шифрует или расшифровывает строку с использованием ключа
     * @param string $string Строка для шифрования/дешифрования
     * @param string $operation ENCODE или DECODE
     * @return string Результат шифрования/дешифрования
     */
    private static function authcode(string $string, string $operation = 'DECODE'): string {
        $ckey_length = 7;   // Случайная длина ключа, значение 0-32;
        $key = self::$_securekey;
        $key = md5($key);
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';
        $cryptkey = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);
        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', 0) . substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($string);
        $result = '';
        $box = range(0, 255);
        $rndkey = array();
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }
        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }
        if ($operation == 'DECODE') {
            if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc . str_replace('=', '', base64_encode($result));
        }
    }
}
