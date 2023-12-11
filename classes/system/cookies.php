<?php

namespace classes\system;

/** Класс cookie Сохранение, чтение, обновление и удаление данных cookie. Префикс можно установить. Обязательный тайм-аут.
 * Данные могут быть строками, массивами, объектами и т. Д.
 * public set Set cookie
 * public get Get update Read cookie
 * public Exp ire Prefix set public set clear cookie
 * Public set clear
 * Prefix Срок действия
 * частное шифрование / дешифрование кода авторизации
 * данные пакета частного пакета
 * данные распаковки закрытого типа
 * частное имя getName получить имя файла cookie, добавить обработку префикса
 */
class Cookies {

    static $_prefix = 'ee';
    static $_securekey = 'efimchenko.ru';   // encrypt key
    static $_expire = 3600;

    /** Инициализация
     * @param String $prefix cookie prefix     
     * @param int $expire Срок действия
     * @param str $securekey  cookie secure key
     */
    public function __construct($prefix = '', $expire = 0, $securekey = '') {
        if (is_string($prefix) && $prefix != '') {
            self::$_prefix = $prefix;
        }
        if (is_numeric($expire) && $expire > 0) {
            self::$_expire = $expire;
        }
        if (is_string($securekey) && $securekey != '') {
            self::$_securekey = $securekey;
        }
    }

    /** Установить cookie
     * @param str $name cookie name
     * @param Значение cookie со смешанным значением $ может быть строкой, массивом, объектом и т. д.
     * @param int $expire Срок действия
     */
    public static function set($name, $value, $expire = 0) {
        $cookie_name = self::getName($name);
        $cookie_expire = time() + ($expire ? $expire : self::$_expire);
        $cookie_value = self::pack($value, $cookie_expire);
        $cookie_value = self::authcode($cookie_value, 'ENCODE');
        if ($cookie_name && $cookie_value && $cookie_expire) {
            setcookie($cookie_name, $cookie_value, $cookie_expire);
        }
        $_COOKIE[$cookie_name] = $cookie_value;
    }

    /** Читать cookie
     * @param str $name cookie name
     * @return mixed
     */
    public static function get($name) {
        $cookie_name = self::getName($name);
        if (isset($_COOKIE[$cookie_name])) {
            $cookie_value = self::authcode($_COOKIE[$cookie_name], 'DECODE');
            $cookie_value = self::unpack($cookie_value);
            return isset($cookie_value[0]) ? $cookie_value[0] : null;
        } else {
            return null;
        }
    }

    /** Обновите cookie, обновите только содержимое, если нужно обновить срок действия метод setExpire
     * @param str $name cookie name
     * @param mixed $value cookie value
     * @return boolean
     */
    public static function update($name, $value) {
        $cookie_name = self::getName($name);
        if (isset($_COOKIE[$cookie_name])) {
            $old_cookie_value = self::authcode($_COOKIE[$cookie_name], 'DECODE');
            $old_cookie_value = self::unpack($old_cookie_value);
            if (isset($old_cookie_value[1]) && $old_cookie_value[1] > 0) { // Получить предыдущее время истечения
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

    /** Очистить cookie
     * @param str $name cookie name
     */
    public static function clear($name) {
        $cookie_name = self::getName($name);
        setcookie($cookie_name);
    }

    /** Установить префикс
     * @param str $prefix cookie prefix
     */
    public static function setPrefix($prefix) {
        if (is_string($prefix) && $prefix != '') {
            self::$_prefix = $prefix;
        }
    }

    /** Установить срок действия
     * @param int $expire cookie expire
     */
    public static function setExpire($expire) {
        if (is_numeric($expire) && $expire > 0) {
            self::$_expire = $expire;
        }
    }

    /** Получить имя файла cookie
     * @param  str $name
     * @return str
     */
    private static function getName($name) {
        return self::$_prefix ? self::$_prefix . '_' . $name : $name;
    }

    /** pack
     * @param var $data
     * @param int $expire Срок действия Используется для оценки
     * @return
     */
    private static function pack($data, $expire) {
        if ($data === '') {
            return '';
        }
        $cookie_data = array();
        $cookie_data['value'] = $data;
        $cookie_data['expire'] = $expire;
        return json_encode($cookie_data);
    }

    /** unpack
     * @param var данные $ data
     * @return array (данные, срок действия)
     */
    private static function unpack($data) {
        if ($data === '') {
            return array('', 0);
        }
        $cookie_data = json_decode($data, true);
        if (isset($cookie_data['value']) && isset($cookie_data['expire'])) {
            if (time() < $cookie_data['expire']) { // не истек
                return array($cookie_data['value'], $cookie_data['expire']);
            }
        }

        return array('', 0);
    }

    /** Зашифровать / расшифровать данные
     * @paramСтрока $ str Оригинальный или зашифрованный текст
     * @param str $operation ENCODE or DECODE
     * @return str Возвращает чистый текст или зашифрованный текст в соответствии с настройками
     */
    private static function authcode($string, $operation = 'DECODE') {
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
