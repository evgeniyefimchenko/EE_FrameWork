<?php

namespace classes\system;

use classes\plugins\SafeMySQL;
use classes\system\Constants;

/**
 * Системный класc для использования во всём проекте
 * Все методы статические
 */
class SysClass {

    /**
     * @var bool|null Кэшированный результат проверки подключения и наличия таблицы
     */
    private static $cacheDB = null;

    // Массив исключений - слова, которые не будут включены в ключевые слова
    private const ARRAY_EXCEPTIONS = [
        "и", "в", "не", "на", "я", "с", "что", "а", "по", "он", "она", "оно", "из", "у", "к", "ко", "за", "от", "до", "без", "для", "о", "об", "под", "про", "над", "через", "при"
    ];

    function __construct() {
        throw new Exception('Static only.');
    }

    /**
     * Генерация уникального UUIDv4
     */
    public static function ee_generate_uuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
    }

    /**
     * Получает реальный IP-адрес пользователя, учитывая возможность наличия прокси-серверов
     * @return string Реальный IP-адрес пользователя
     */
    public static function client_ip() {
        $client = $_SERVER['HTTP_CLIENT_IP'] ?? false;
        $forward = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? false;
        $remote = $_SERVER['REMOTE_ADDR'] ?? false;
        if ($client && filter_var($client, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $ip = $client;
        } elseif ($forward && filter_var($forward, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $ip = $forward;
        } else {
            $ip = $remote;
        }
        return (string) $ip;
    }

    /**
     * Получает IP-адрес по URL или имени хоста
     * Эта функция принимает URL или имя хоста, извлекает хост из URL (если это необходимо),
     * и возвращает IP-адрес. Если имя хоста не может быть разрешено, функция возвращает false
     * @param string $url URL или имя хоста
     * @return string|false IP-адрес или false в случае неудачи
     */
    public static function getIpFromUrlOrHost(string $url): string|false {
        $host = (filter_var($url, FILTER_VALIDATE_URL)) ? parse_url($url, PHP_URL_HOST) : $url;
        $ip = gethostbyname($host);
        return ($ip !== $host) ? $ip : false;
    }

    /**
     * Обрезает строку до указанного количества символов, сохраняя целостность слов
     * @param string $string Строка, которую нужно обрезать
     * @param int $len Количество символов
     * @return string Обрезанная строка с многоточием в конце
     */
    public static function truncateString($string, $len = 140) {
        $string = strip_tags($string);
        if (mb_strlen($string) <= $len) {
            return $string;
        }
        $truncated = mb_substr($string, 0, $len);
        $truncated = rtrim($truncated, "!,.-");
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }
        return $truncated . "…";
    }

    /**
     * Прячет часть строки за символами, оставляя указанные количество символов в начале и в конце строки видимыми.
     * @param string $str Строка, в которой хотим заменить часть букв на символы.
     * @param int $first Количество символов, открытых в начале строки.
     * @param int $last Количество символов, открытых в конце строки.
     * @param string $symbol Символ, которым будем скрывать буквы.
     * @return string Строка с замененными символами.
     */
    public static function maskString($str, $first = 4, $last = 4, $symbol = '*') {
        $part_length = mb_strlen($str);
        if (($first + $last) >= $part_length) {
            return str_repeat($symbol, $part_length);
        }
        $first_letters = mb_substr($str, 0, $first, "UTF-8");
        $last_letters = mb_substr($str, -$last, $last, "UTF-8");
        $hidden_part_length = $part_length - $first - $last;
        $hidden_part = str_repeat($symbol, $hidden_part_length);
        return $first_letters . $hidden_part . $last_letters;
    }

    /**
     * Проверяет доступ пользователя к определенному ресурсу на основе его идентификатора и роли.
     * Если пользователь не авторизован или не имеет необходимой роли доступа, он будет перенаправлен на форму входа.
     * Параметры вызывающей функции и её имя передаются в форму входа через URL, с использованием слешей для разделения.
     * @param int $id Идентификатор пользователя. Если не указан или равен 0, считается, что пользователь не авторизован.
     * @param array $access Массив ролей, имеющих доступ. Если роль пользователя не входит в этот массив, доступ будет отклонен.
     * @return bool Возвращает TRUE, если у пользователя есть доступ, иначе FALSE.
     */
    public static function getAccessUser(mixed $id = 0, array $access = []): bool {
        if (!$id || !filter_var($id, FILTER_VALIDATE_INT)) {
            $queryParams = 'return=' . $_SERVER['REQUEST_URI'];
            self::handleRedirect(200, '/show_login_form?' . $queryParams);
            return false;
        }
        $user_data = new Users(array($id));
        $add_access = array(100);
        if (!in_array($user_data->get_user_role($id), $access) && !array_intersect($add_access, $access)) {
            return false;
        }
        return true;
    }

    /**
     * Проверка адресов электронной почты на валидность.
     * Поддерживает как латинские, так и международные символы.
     * @param string|string[] $emails Строка с одним адресом электронной почты или массив адресов.
     * @return bool Возвращает true, если все адреса валидны, иначе false.
     */
    public static function validEmail(mixed $emails): bool {
        $pattern = '/.+@.+\..+/i'; // Базовый шаблон для проверки электронной почты
        if (is_string($emails)) {
            $emails = [$emails];
        }
        if (is_array($emails)) {
            foreach ($emails as $email) {
                if (!is_string($email) || !preg_match($pattern, $email)) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Извлекает ключевые слова из предоставленного содержимого
     * Эта функция обрабатывает входной текст, фильтрует ненужные символы
     * и возвращает строку с наиболее частыми ключевыми словами
     * @param string $contents Входной текст для извлечения ключевых слов
     * @param int $symbol Минимальная длина ключевого слова
     * @param int $words Максимальное количество ключевых слов для возврата
     * @param int $count Минимальное количество повторений ключевого слова для включения в результат
     * @return string Строка ключевых слов, разделенных запятыми
     */
    public static function getKeywordsFromText(string $contents, int $symbol = 3, int $words = 5, int $count = 3): string {
        $contents = mb_eregi_replace("[^а-яА-ЯёЁ ]", '', $contents);
        $contents = filter_var($contents, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
        $contents = preg_replace(
                ["'<[/!]*?[^<>]*?>'si", "'([\r\n])[s]+'si", "'&[a-z0-9]{1,6};'si", "'( +)'si"],
                ["", " ", " ", " "],
                strip_tags($contents)
        );
        $replaceArray = [
            "~", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "+", "`", '"', "№", ";", ":", "?", "-", "=", "|", "\"", "",
            "/", "[", "]", "{", "}", "'", ",", ".", "<", ">", "\r\n", "\n", "\t", "«", "»"
        ];
        $contents = str_replace($replaceArray, " ", $contents);
        $keywordCache = explode(" ", $contents);
        $rearray = [];
        foreach ($keywordCache as $word) {
            $word = mb_strtolower($word, 'utf-8');
            if (mb_strlen($word, "utf-8") >= $symbol && !is_numeric($word)) {
                if (!in_array($word, self::ARRAY_EXCEPTIONS)) {
                    $rearray[$word] = array_key_exists($word, $rearray) ? ($rearray[$word] + 1) : 1;
                }
            }
        }
        arsort($rearray);
        $keywordCache = array_slice($rearray, 0, $words, true);
        $keywords = "";
        foreach ($keywordCache as $word => $c) {
            if ($c >= $count) {
                $keywords .= ", " . $word;
            }
        }
        return substr($keywords, 2);
    }

    /**
     * Преобразует весь HTML код в одну линию, удаляя все комментарии
     * Условные комментарии не удаляются
     * @param string $buffer HTML код для преобразования
     * @return string Преобразованный HTML код в одну линию
     */
    public static function minifyHtml(string $buffer): string {
        $buffer = preg_replace('/(?:(?<=\>)|(?<=\/\>))\s+(?=\<\/?)/', '', $buffer);
        if (strpos($buffer, '<pre') === FALSE) {
            $buffer = preg_replace('/\s+/', ' ', $buffer);
        }
        $buffer = preg_replace('/[\t\r]\s+/', ' ', $buffer);
        $buffer = preg_replace('/<!--[^\[](.|\s)*?-->/', '', $buffer);
        $buffer = preg_replace('/\/\*.*?\*\//', '', $buffer);
        return $buffer;
    }

    /**
     * Возвращает правильное окончание для множественного числа слова на основании числа и массива окончаний
     * @param int $number Число, на основе которого нужно выбрать окончание
     * @param array $endingArray Массив окончаний для чисел (1, 4, 5), например, array('яблоко', 'яблока', 'яблок')
     * @return string Правильное окончание для слова
     * @throws InvalidArgumentException Если массив окончаний не содержит ровно три элемента
     */
    public static function selectWordEnding(int $number, array $endingArray): string {
        if (count($endingArray) !== 3) {
            throw new InvalidArgumentException('Массив окончаний должен содержать ровно три элемента.');
        }
        $number = $number % 100;
        if ($number >= 11 && $number <= 19) {
            return $endingArray[2];
        }
        $i = $number % 10;
        switch ($i) {
            case 1:
                return $endingArray[0];
            case 2:
            case 3:
            case 4:
                return $endingArray[1];
            default:
                return $endingArray[2];
        }
    }

    /**
     * Вернёт ID текущего пользователя
     * @return int ID авторизованного пользователя
     */
    public static function get_current_user_id() {
        $table = ENV_DB_PREF . 'users';
        $sql = 'SELECT `id` FROM ?n WHERE `session` = ?s';
        return SafeMySQL::gi()->getOne($sql, $table, Session::get('user_session'));
    }

    /**
     * Вернёт роль пользователя по ID
     * @return int ID роли пользователя
     */
    public static function get_user_role_by_id($id) {
        $table = ENV_DB_PREF . 'users';
        $sql = 'SELECT `user_role` FROM ?n WHERE `id` = ?i';
        return SafeMySQL::gi()->getOne($sql, $table, $id);
    }

    /**
     * Транслитерирует и очищает имя файла
     * @param string $fileName Имя файла для транслитерации
     * @return string Транслитерированное и очищенное имя файла
     */
    public static function transliterateFileName($fileName) {
        $fileName = (string) $fileName;
        $fileName = strip_tags($fileName);
        $fileName = str_replace(array("\n", "\r"), " ", $fileName);
        $fileName = preg_replace("/\s+/", ' ', $fileName);
        $fileName = trim($fileName);
        $fileName = function_exists('mb_strtolower') ? mb_strtolower($fileName) : strtolower($fileName);
        // Транслитерация кириллических символов
        $transliterationTable = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
            'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
            'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => ''
        );
        $fileName = strtr($fileName, $transliterationTable);
        // Поддержка дополнительных символов и алфавитов
        $additionalTransliterationTable = array(
                // Добавьте сюда другие символы для транслитерации, если необходимо
        );
        $fileName = strtr($fileName, $additionalTransliterationTable);
        $fileName = preg_replace("/[^a-z0-9]+/", '-', $fileName);
        $fileName = trim($fileName, '-');
        return $fileName;
    }

    /**
     * Транслитерация ошибочного ввода на английской раскладке
     * @param string $s Входная строка для транслитерации
     * @return string Транслитерированная строка
     */
    public static function transliterateErrorInput(string $s): string {
        $s = preg_replace("/\s+/", ' ', trim($s)); // Убираем лишние пробелы и переводы строк
        $s = mb_strtolower($s); // Переводим строку в нижний регистр
        $transliterationMap = [
            "а" => "f", "б" => ",", "в" => "d", "г" => "u", "д" => "l",
            "е" => "t", "ё" => "`", "ж" => ";", "з" => "p", "и" => "b",
            "й" => "q", "к" => "r", "л" => "k", "м" => "v", "н" => "y",
            "о" => "j", "п" => "g", "р" => "h", "с" => "c", "т" => "n",
            "у" => "e", "ф" => "a", "х" => "[", "ц" => "w", "ч" => "x",
            "ш" => "i", "щ" => "o", "ъ" => "]", "ы" => "s", "ь" => "m",
            "э" => "'", "ю" => ".", "я" => "z", "," => "?", "." => "/",
            // Добавляем капитализированные версии
            ...array_map(fn($v, $k) => [$k => strtoupper($v)], array_keys($transliterationMap), $transliterationMap)
        ];
        $s = strtr($s, $transliterationMap);
        return $s;
    }

    /**
     * Переадресует на указанную страницу с заданным HTTP кодом ответа
     * По умолчанию используется код 404 и редирект на главную страницу
     * Если заголовки уже были отправлены, использует JavaScript для редиректа
     * @param int $code Код HTTP ответа
     * @param string $url URL для перенаправления
     */
    public static function handleRedirect($code = 404, $url = ENV_URL_SITE) {
        $code_redirect = match ($code) {
            200 => '200 OK',
            301 => '301 Moved Permanently',
            307 => '307 Temporary Redirect',
            400 => '400 Bad Request',
            401 => '401 Unauthorized',
            403 => '403 Forbidden',
            500 => '500 Internal Server Error',
            default => '404 Not Found'
        };
        if (ENV_TEST) {
            $stack = debug_backtrace();
            self::pre('Возврат из ' . $stack[0]['file'] . ' line ' . $stack[0]['line'] . ' to ' . $url . ' code=' . $code_redirect . '<br/>');
        }
        if (headers_sent()) {
            echo "<script type='text/javascript'>window.location.href = '" . $url . "';</script>";
            exit;
        }
        header("HTTP/1.1 " . $code_redirect);
        if ($code >= 400) {
            Session::set('code', $code_redirect);
            include_once(ENV_SITE_PATH . "error.php");
            Session::set('code', NULL);
            exit;
        }
        header("Location: " . $url);
        exit;
    }

    /**
     * Определяет браузер пользователя на основе строки User-Agent
     * @return string Информация о браузере пользователя
     */
    public static function detectClientBrowser() {
        $agent = $_SERVER['HTTP_USER_AGENT'];
        $browser_info = [];
        preg_match("/(Edge|Edg|Opera|OPR|Firefox|Chrome|Version|Opera Mini|Netscape|Konqueror|SeaMonkey|Camino|Minefield|Iceweasel|K-Meleon|Maxthon|Vivaldi|Brave)(?:\/| )([0-9.]+)/", $agent, $browser_info);
        if (count($browser_info) < 3) {
            return 'Unknown browser';
        }
        list(, $browser, $version) = $browser_info;
        // Специальные проверки для некоторых браузеров
        if (preg_match("/Opera ([0-9.]+)/i", $agent, $opera)) {
            return 'Opera ' . $opera[1];
        }
        if ($browser === 'MSIE' || $browser === 'Trident') {
            if (preg_match("/(Maxthon|Avant Browser|MyIE2)/i", $agent, $ie)) {
                return $ie[1] . ' based on IE ' . $version;
            }
            return 'IE ' . $version;
        }
        if ($browser === 'Firefox') {
            if (preg_match("/(Flock|Navigator|Epiphany)\/([0-9.]+)/", $agent, $ff)) {
                return $ff[1] . ' ' . $ff[2];
            }
        }
        if (($browser === 'Opera' || $browser === 'OPR') && $version === '9.80') {
            return 'Opera ' . substr($agent, -5);
        }
        if ($browser === 'Version') {
            return 'Safari ' . $version;
        }
        if ($browser === 'Edge' || $browser === 'Edg') {
            return 'Edge ' . $version;
        }
        if ($browser === 'Vivaldi') {
            return 'Vivaldi ' . $version;
        }
        if ($browser === 'Brave') {
            return 'Brave ' . $version;
        }
        if (!$browser && strpos($agent, 'Gecko') !== false) {
            return 'Browser based on Gecko';
        }
        return $browser . ' ' . $version;
    }

    /**
     * Вернёт название страны по международному коду ISO 3166-2
     */
    public static function code2country($code, $lang = 'RU') {
        if ($lang == 'RU') {
            $countries = [
                "RU" => "Россия",
                "UA" => "Украина",
                "BY" => "Беларусь",
                "KZ" => "Казахстан",
                "UZ" => "Узбекистан",
                "AM" => "Армения",
                "AZ" => "Азербайджан",
                "BE" => "Бельгия",
                "TR" => "Турция",
                "TM" => "Туркмения",
                "TJ" => "Таджикистан",
                "KG" => "Киргизия",
                "AD" => "Андорра",
                "AF" => "Афганистан",
                "AG" => "Антигуа",
                "AI" => "Ангилья",
                "AL" => "Албания",
                "AO" => "Ангола",
                "AQ" => "Антарктида",
                "AR" => "Аргентина",
                "AS" => "Американское Самоа",
                "AU" => "Австралия",
                "AW" => "Аруба",
                "BA" => "Босния",
                "BB" => "Барбадос",
                "BD" => "Бангладеш",
                "BG" => "Болгария",
                "BF" => "Буркина-Фасо",
                "BH" => "Бахрейн",
                "BI" => "Бурунди",
                "BJ" => "Бенин",
                "BN" => "Бруней-Даруссалам",
                "BO" => "Боливия",
                "BR" => "Бразилия",
                "BS" => "Багамы",
                "BT" => "Бутан",
                "BW" => "Ботсвана",
                "BZ" => "Белиз",
                "CA" => "Канада",
                "CD" => "Конго",
                "CF" => "Центрально-Африканская Республика",
                "CG" => "Конго",
                "CI" => "Кот дИвуар",
                "CL" => "Чили",
                "CM" => "Камерун",
                "CN" => "Китай",
                "CO" => "Колумбия",
                "CR" => "Коста-Рика",
                "CU" => "Куба",
                "CV" => "Кабо-Верде",
                "CY" => "Кипр",
                "CZ" => "Чешская Республика",
                "DK" => "Дания",
                "DZ" => "Алжир",
                "DJ" => "Джибути",
                "DM" => "Доминика",
                "DO" => "Доминиканская Республика",
                "EG" => "Египет",
                "SV" => "Эль-Сальвадор",
                "EQ" => "Экваториальная Гвинея",
                "ER" => "Эритрея",
                "EE" => "Эстония",
                "ET" => "Эфиопия",
                "FO" => "Фарерские острова",
                "FJ" => "Фиджи",
                "FI" => "Финляндия",
                "FR" => "Франция",
                "GA" => "Габон",
                "GM" => "Гамбия",
                "GE" => "Грузия",
                "DE" => "Германия",
                "GH" => "Гана",
                "GQ" => "Экваториальная Гвинея",
                "GR" => "Греция",
                "GD" => "Гренада",
                "GT" => "Гватемала",
                "GN" => "Гвинея",
                "GW" => "Гвинея-Бисау",
                "GY" => "Гайана",
                "HT" => "Гаити",
                "HN" => "Гондурас",
                "HK" => "Гонконг",
                "HR" => "Хорватия",
                "HU" => "Венгрия",
                "IS" => "Исландия",
                "IN" => "Индия",
                "ID" => "Индонезия",
                "IR" => "Иран",
                "IQ" => "Ирак",
                "IE" => "Ирландия",
                "IL" => "Израиль",
                "IT" => "Италия",
                "JM" => "Ямайка",
                "JP" => "Япония",
                "JO" => "Иордания",
                "KE" => "Кения",
                "KH" => "Камбоджа",
                "KI" => "Кирибати",
                "KM" => "Коморы",
                "KW" => "Кувейт",
                "KY" => "Острова Кайман",
                "LV" => "Латвия",
                "LB" => "Ливан",
                "LS" => "Лесото",
                "LR" => "Либерия",
                "LY" => "Ливия",
                "LI" => "Лихтенштейн",
                "LT" => "Литва",
                "LU" => "Люксембург",
                "MO" => "Макао",
                "MK" => "Республика Македония",
                "MG" => "Мадагаскар",
                "MW" => "Малави",
                "MY" => "Малайзия",
                "MV" => "Мальдивы",
                "ML" => "Мали",
                "MT" => "Мальта",
                "MH" => "Маршалловы острова",
                "MR" => "Мавритания",
                "MU" => "Маврикий",
                "MX" => "Мексика",
                "FM" => "Микронезия",
                "MD" => "Молдова",
                "MC" => "Монако",
                "MN" => "Монголия",
                "ME" => "Черногория",
                "MA" => "Марокко",
                "MZ" => "Мозамбик",
                "MM" => "Мьянма",
                "NA" => "Намибия",
                "NR" => "Науру",
                "NP" => "Непал",
                "NL" => "Нидерланды",
                "NZ" => "Новая Зеландия",
                "NI" => "Никарагуа",
                "NE" => "Нигер",
                "NG" => "Нигерия",
                "NO" => "Норвегия",
                "OM" => "Оман",
                "PK" => "Пакистан",
                "PW" => "Палау",
                "PA" => "Панама",
                "PG" => "Папуа-Новая Гвинея",
                "PY" => "Парагвай",
                "PE" => "Перу",
                "PH" => "Филиппины",
                "PL" => "Польша",
                "PT" => "Португалия",
                "PR" => "Пуэрто-Рико",
                "QA" => "Катар",
                "RO" => "Румыния",
                "RW" => "Руанда",
                "LC" => "Сент-Люсия",
                "WS" => "Самоа",
                "SM" => "Сан-Марино",
                "ST" => "Сан-Томе и Принсипи",
                "SA" => "Саудовская Аравия",
                "UK" => "Шотландия",
                "SN" => "Сенегал",
                "RS" => "Сербия",
                "SL" => "Сьерра-Леоне",
                "SG" => "Сингапур",
                "SK" => "Словакия",
                "SI" => "Словения",
                "SB" => "Соломоновы острова",
                "SO" => "Сомали",
                "ZA" => "Южная Африка",
                "KR" => "Южная Корея",
                "ES" => "Испания",
                "LK" => "Шри-Ланка",
                "SD" => "Судан",
                "SR" => "Суринам",
                "SZ" => "Свазиленд",
                "SE" => "Швеция",
                "CH" => "Швейцария",
                "SY" => "Сирия",
                "TW" => "Тайвань",
                "TZ" => "Танзания",
                "TD" => "Чад",
                "TH" => "Таиланд",
                "TL" => "Тимор-Лесте",
                "TG" => "Того",
                "TO" => "Тонга",
                "TT" => "Тринидад и Тобаго",
                "TN" => "Тунис",
                "TV" => "Тувалу",
                "UG" => "Уганда",
                "AE" => "Объединенные Арабские Эмираты",
                "GB" => "Соединенное Королевство",
                "US" => "Соединенные Штаты",
                "UY" => "Уругвай",
                "VU" => "Вануату",
                "VA" => "Ватикан",
                "VE" => "Венесуэла",
                "EH" => "Западная Сахара",
                "YE" => "Йемен",
                "ZM" => "Замбия",
                "ZW" => "Зимбабве",
                "AX" => "Аландские острова",
                "AT" => "Австрия",
                "BM" => "Бермуды",
                "BQ" => "Бонайре, Синт-Эстатиус и Саба",
                "BV" => "Остров Буве",
                "IO" => "Британская территория в Индийском океане",
                "CX" => "Остров Рождества",
                "CC" => "Кокосовые острова Килинг",
                "CK" => "Острова Кука",
                "CW" => "Кюрасао",
                "EC" => "Эквадор",
                "FK" => "Фолклендские острова",
                "GF" => "Французская Гвиана",
                "PF" => "Французская Полинезия",
                "TF" => "Французские Южные и Антарктические территории",
                "GI" => "Гибралтар",
                "GL" => "Гренландия",
                "GP" => "Гваделупа",
                "GU" => "Гуам",
                "GG" => "Гернси",
                "HM" => "Острова Херд и Макдональд",
                "IM" => "Остров Мэн",
                "JE" => "Джерси",
                "KP" => "Корейская Народно-Демократическая Республика",
                "LA" => "Лаосская Народно-Демократическая Республика",
                "MQ" => "Мартиника",
                "YT" => "Майотта",
                "MS" => "Монсеррат",
                "NC" => "Новая Каледония",
                "NU" => "Ниуэ",
                "NF" => "Норфолк",
                "MP" => "Северные Марианские острова",
                "PS" => "Государство Палестина",
                "PN" => "Питкерн",
                "RE" => "Реюньон",
                "BL" => "Сен-Бартельми",
                "SH" => "Вознесение острова Святой Елены и Тристан-да-Кунья",
                "KN" => "Сент-Китс и Невис",
                "MF" => "Сен-Мартен, французская часть",
                "PM" => "Сен-Пьер и Микелон",
                "VC" => "Святой Винсент и Гренадины",
                "SC" => "Сейшелы",
                "SX" => "Голландская часть Синт-Мартена",
                "GS" => "Южная Георгия и Южные Сандвичевы острова",
                "SS" => "Южный Судан",
                "SJ" => "Шпицберген и Ян-Майен",
                "TK" => "Токелау",
                "TC" => "Острова Теркс и Кайкос",
                "UM" => "Малые отдаленные острова США",
                "VN" => "Вьетнам",
                "VG" => "Британские Виргинские острова",
                "VI" => "Виргинские острова США",
                "WF" => "Уоллис и Футуна",
            ];
        } else {
            $countries = array(
                "CV" => "Cabo Verde",
                "NG" => "Nigeria",
                "KH" => "Cambodia",
                "NU" => "Niue",
                "CM" => "Cameroon",
                "NF" => "Norfolk Island",
                "CA" => "Canada",
                "KY" => "Cayman Islands",
                "NO" => "Norway",
                "CF" => "Central African Republic",
                "OM" => "Oman",
                "TD" => "Chad",
                "PK" => "Pakistan",
                "CL" => "Chile",
                "PW" => "Palau",
                "CN" => "China",
                "PS" => "Palestine, State of",
                "CX" => "Christmas Island",
                "PA" => "Panama",
                "PG" => "Papua New Guinea",
                "CO" => "Colombia",
                "PY" => "Paraguay",
                "KM" => "Comoros",
                "PE" => "Peru",
                "CG" => "Congo",
                "PH" => "Philippines",
                "CD" => "Congo, Democratic Republic of the",
                "PN" => "Pitcairn",
                "CK" => "Cook Islands",
                "PL" => "Poland",
                "CR" => "Costa Rica",
                "PT" => "Portugal",
                "CI" => "Côte d'Ivoire",
                "PR" => "Puerto Rico",
                "HR" => "Croatia",
                "QA" => "Qatar",
                "CU" => "Cuba",
                "RE" => "Réunion",
                "CW" => "Curaçao",
                "RO" => "Romania",
                "CY" => "Cyprus",
                "RU" => "Russian Federation",
                "CZ" => "Czechia",
                "RW" => "Rwanda",
                "DK" => "Denmark",
                "BL" => "Saint Barthélemy",
                "DJ" => "Djibouti",
                "SH" => "Saint Helena, Ascension and Tristanda Cunha",
                "DM" => "Dominica",
                "KN" => "Saint Kitts and Nevis",
                "DO" => "Dominican Republic",
                "LC" => "Saint Lucia",
                "MF" => "Saint Martin (French part)",
                "PM" => "Saint Pierre and Miquelon",
                "VC" => "Saint Vincent and the Grenadines",
                "GQ" => "Equatorial Guinea",
                "WS" => "Samoa",
                "ER" => "Eritrea",
                "SM" => "San Marino",
                "EE" => "Estonia",
                "ST" => "Sao Tome and Principe",
                "SZ" => "Eswatini",
                "SA" => "Saudi Arabia",
                "ET" => "Ethiopia",
                "SN" => "Senegal",
                "FK" => "Falkland Islands (Malvinas)",
                "RS" => "Serbia",
                "FO" => "Faroe Islands",
                "SC" => "Seychelles",
                "FJ" => "Fiji",
                "SL" => "Sierra Leone",
                "FI" => "Finland",
                "SG" => "Singapore",
                "FR" => "France",
                "SX" => "Sint Maarten (Dutch part)",
                "GF" => "French Guiana",
                "SK" => "Slovakia",
                "PF" => "French Polynesia",
                "SI" => "Slovenia",
                "TF" => "French Southern Territories",
                "SB" => "Solomon Islands",
                "GA" => "Gabon",
                "SO" => "Somalia",
                "GM" => "Gambia",
                "ZA" => "South Africa",
                "GE" => "Georgia",
                "GS" => "South Georgia and the South Sandwich Islands",
                "DE" => "Germany",
                "SS" => "South Sudan",
                "GH" => "Ghana",
                "ES" => "Spain",
                "GI" => "Gibraltar",
                "LK" => "Sri Lanka",
                "GR" => "Greece",
                "SD" => "Sudan",
                "GL" => "Greenland",
                "SR" => "Suriname",
                "GD" => "Grenada",
                "SJ" => "Svalbard and Jan Mayen",
                "GP" => "Guadeloupe",
                "SE" => "Sweden",
                "GU" => "Guam",
                "CH" => "Switzerland",
                "GT" => "Guatemala",
                "SY" => "Syrian Arab Republic",
                "GG" => "Guernsey",
                "TW" => "Taiwan, Province of China",
                "GN" => "Guinea",
                "TJ" => "Tajikistan",
                "GW" => "Guinea-Bissau",
                "TZ" => "Tanzania, United Republic of",
                "GY" => "Guyana",
                "TH" => "Thailand",
                "HT" => "Haiti",
                "TL" => "Timor-Leste",
                "VA" => "Holy See",
                "TG" => "Togo",
                "TK" => "Tokelau",
                "HN" => "Honduras",
                "TO" => "Tonga",
                "HK" => "Hong Kong SAR",
                "TT" => "Trinidad and Tobago",
                "HU" => "Hungary",
                "TN" => "Tunisia",
                "IS" => "Iceland",
                "TR" => "Turkey",
                "IN" => "India",
                "TM" => "Turkmenistan",
                "ID" => "Indonesia",
                "TC" => "Turks and Caicos Islands",
                "IR" => "Iran (Islamic Republic of)",
                "TV" => "Tuvalu",
                "IQ" => "Iraq",
                "UG" => "Uganda",
                "IE" => "Ireland",
                "UA" => "Ukraine",
                "IM" => "Isle of Man",
                "AE" => "United Arab Emirates",
                "IL" => "Israel",
                "GB" => "United Kingdom of Great Britain and Northern Ireland",
                "IT" => "Italy",
                "US" => "United States of America",
                "JM" => "Jamaica",
                "UM" => "United States Minor Outlying Islands",
                "JP" => "Japan",
                "UY" => "Uruguay",
                "JE" => "Jersey",
                "UZ" => "Uzbekistan",
                "JO" => "Jordan",
                "VU" => "Vanuatu",
                "KZ" => "Kazakhstan",
                "VE" => "Venezuela (Bolivarian Republic of)",
                "KE" => "Kenya",
                "VN" => "Vietnam",
                "KI" => "Kiribati",
                "VG" => "Virgin Islands (British)",
                "KP" => "Korea (Democratic People's Republic of)",
                "VI" => "Virgin Islands (U.S.)",
                "KR" => "Korea, Republic of",
                "WF" => "Wallis and Futuna",
                "KW" => "Kuwait",
                "EH" => "Western Sahara",
                "KG" => "Kyrgyzstan",
                "YE" => "Yemen",
                "LA" => "Lao People's Democratic Republic",
                "ZM" => "Zambia",
                "LV" => "Latvia",
                "ZW" => "Zimbabwe",
                "LB" => "Lebanon"
            );
        }
        if (isset($countries[$code])) {
            return $countries[$code];
        } else {
            return false;
        }
    }

    /**
     * Проверяет наличие основных конфигурационных параметров и состояние установки проекта
     * Метод проверяет следующие условия:
     * - Правильность настроек базы данных в файле configuration.php
     * - Наличие и валидность обязательных адресов электронной почты
     * - Наличие даты создания сайта в файле configuration.php
     * - Возможность соединения с базой данных
     * - Наличие основной таблицы пользователей в базе данных
     * В случае обнаружения проблем метод выводит соответствующие предупреждения
     * @return bool Возвращает true, если все проверки пройдены успешно
     * @throws Exception Возможное исключение, если соединение с базой данных не установлено или настройки проекта не произведены
     */
    public static function checkInstall() {
        if (!ENV_DB_USER || !ENV_DB_PASS) { // Нет настроек для БД
            self::pre('Выполните необходимые настройки в файле configuration.php для базы данных!');
        }
        if (!ENV_SITE_EMAIL || !ENV_ADMIN_EMAIL || !SysClass::validEmail([ENV_SITE_EMAIL, ENV_ADMIN_EMAIL])) { // Нет обязательных адресов почты или они не валидны
            self::pre('Выполните необходимые настройки в файле configuration.php для электронной почты!');
        }
        if (!ENV_DATE_SITE_CREATE) {
            self::pre('Выполните необходимые настройки в файле configuration.php для даты создания сайта!');
        }
        if (!self::checkDatabaseConnection()) {
            self::pre('Нет соединения с БД Выполните необходимые настройки в файле configuration.php');
        }
        if (SafeMySQL::gi()->query('show tables like ?s', Constants::USERS_TABLE)->{"num_rows"} === 0) {
            new Users(true);
        }
        return true;
    }

    /**
     * Проверяет подключение к базе данных и наличие таблицы с указанным префиксом.
     * @param string $host Хост базы данных. По умолчанию значение константы ENV_DB_HOST.
     * @param string $user Имя пользователя базы данных. По умолчанию значение константы ENV_DB_USER.
     * @param string $pass Пароль пользователя базы данных. По умолчанию значение константы ENV_DB_PASS.
     * @param string $db_name Имя базы данных. По умолчанию значение константы ENV_DB_NAME.
     * @return bool Возвращает true, если подключение успешно и таблица существует, false в противном случае.
     */
    public static function checkDatabaseConnection($host = ENV_DB_HOST, $user = ENV_DB_USER, $pass = ENV_DB_PASS, $db_name = ENV_DB_NAME) {
        if (self::$cacheDB !== null) {
            return self::$cacheDB;
        }
        if (!$host || !$user || !$pass || !$db_name) {
            return false;
        }
        try {
            $db = new SafeMySQL(array($host, $user, $pass, $db_name));
            $result = $db->getOne('SHOW TABLES LIKE ?s', ENV_DB_PREF . 'users');
            unset($db);
            self::$cacheDB = $result !== null;
            return self::$cacheDB;
        } catch (Exception $ex) {
            self::pre($ex->getMessage());
        }
    }

    /**
     * Рекурсивный поиск файла в папке
     * и подпапках
     * @param str $dir - где искать
     * @param str $tosearch - что искать
     * @param bool $this_dir - искать директорию
     * @return boolean || path file
     */
    public static function searchFile($dir, $tosearch = false, $this_dir = false) {
        $files = array_diff(scandir($dir), Array(".", ".."));
        foreach ($files as $d) {
            $path = $dir . "/" . $d;
            if (!$this_dir && !is_dir($path)) { // Это не папка
                if ($tosearch) {
                    if (strtolower($d) == strtolower($tosearch)) {
                        return $path;
                    }
                } else {
                    return $path;
                }
            } else if ($this_dir && is_dir($path)) {
                if ($tosearch) {
                    if (strtolower($d) == strtolower($tosearch)) {
                        return $path;
                    }
                } else {
                    return $path;
                }
            } else { // Это папка продолжаем рекурсию
                $res = search_file($dir . "/" . $d, $tosearch);
                if ($res) {
                    return $res;
                }
            }
        }
        return false;
    }

    /**
     * Рекурсивный поиск изображений в подпапках
     * Для использования необходимо удалить на выходе абсолютный путь до каталога
     * Пример:
     * $dir = ENV_SITE_PATH . "/uploads/images/my_img";
     * foreach(str_replace(ENV_SITE_PATH, '', SysClass::search_images_file($dir)) as $path_image) {echo '<img src="'.$path_image.'" />';}
     * @param str $dir - начальная категория поиска
     * @param array $allowed_types - разрешенные раcширения файлов
     * @param str $name - имя файла с расширением если указанно
     * @return array - массив с относительными путями к файлам изображений или false
     */
    public static function searchImagesFile($dir, $allowed_types = ["jpg", "jpeg", "png", "gif"], $name = false) {
        $res_array = [];
        $files = array_diff(scandir($dir), Array(".", ".."));
        foreach ($files as $file) {
            $path = $dir . "/" . $file;
            if (!is_dir($path)) {
                $ext = pathinfo($file);
                if (in_array($ext['extension'], $allowed_types)) {
                    if ($name && $name == $ext["basename"]) {
                        $res_array[] = $dir . "/" . $file;
                    } else if (!$name) {
                        $res_array[] = $dir . "/" . $file;
                    }
                }
            } else {
                $temp_array = SysClass::searchImagesFile($path, $allowed_types, $name);
                $res_array = $temp_array ? array_merge($res_array, $temp_array) : false;
            }
        }
        if (!$res_array || count($res_array) <= 0) {
            return false;
        }
        return $res_array;
    }

    /**
     * Выводит отформатированный вывод переменной с информацией о месте вызова
     * @param mixed $val Значение переменной для вывода
     * @param bool $die Определяет, следует ли завершить выполнение скрипта после вывода. По умолчанию true
     * @param bool $full_stack Определяет, следует ли выводить полный стек вызовов. По умолчанию false
     * @param bool $flag Определяет, следует ли игнорировать параметр GET 'show_pre'. По умолчанию true
     * @return void
     */
    public static function pre($val, $die = true, $full_stack = false, $flag = true) {
        if (isset($_GET['show_pre']) || $flag) {
            $add_trace = '';
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            if ($full_stack) {
                $formattedTrace = [];
                foreach ($trace as $item) {
                    $formattedTrace[] = [
                        'function' => $item['function'] ?? 'N/A',
                        'line' => $item['line'] ?? 'N/A',
                        'file' => $item['file'] ?? 'N/A',
                        'class' => $item['class'] ?? 'N/A',
                        'type' => $item['type'] ?? 'N/A',
                        'object' => $item['object'] ?? 'N/A',
                    ];
                }
                $add_trace = '<hr/><b>Полный стек вызовов:</b> ' . print_r($formattedTrace, true);
            }
            $caller = $trace[1];
            echo (isset($caller['file']) ? $caller['file'] : '') . ' ' . (isset($caller['line']) ? $caller['line'] : '') . ' ' . $caller['function'] . PHP_EOL
            . '<br/><pre style="width: max-content; background: blue; border-radius: 5px; color: white; font-size: 16px; padding: 2%;">';
            echo htmlentities(var_export($val, true), ENT_QUOTES);
            echo $add_trace;
            echo '</pre>';
            if ($die) {
                die;
            }
        }
    }

    /**
     * Функция логирования в файл с расширенной информацией.
     * @param string $subFolder Подпапка для лога.
     * @param string $initiator Инициатор записи в лог.
     * @param mixed $result Результат для логирования.
     * @param mixed $details Дополнительные детали.
     */
    public static function preFile(string $subFolder, string $initiator, mixed $result, mixed $details = ''): void {
        if (ENV_LOG) {
            $logsPath = ENV_LOGS_PATH . $subFolder;
            if (!file_exists($logsPath)) {
                mkdir($logsPath, 0777, true);
            }
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $formattedTrace = [];
            foreach ($trace as $item) {
                $formattedTrace[] = [
                    'function' => $item['function'] ?? 'N/A',
                    'line' => $item['line'] ?? 'N/A',
                    'file' => $item['file'] ?? 'N/A',
                    'class' => $item['class'] ?? 'N/A',
                    'type' => $item['type'] ?? 'N/A',
                    'object' => $item['object'] ?? 'N/A',
                ];
            }
            $path = $logsPath . ENV_DIRSEP . date("Y-m-d") . '.txt';
            $result = is_array($result) ? json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $result;
            $details = is_array($details) ? json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $details;
            $logMessage = "{START}";
            $logMessage .= PHP_EOL . "Время события: " . date("Y-m-d H:i:s");
            $logMessage .= PHP_EOL . "Инициатор: " . var_export($initiator, true);
            $logMessage .= PHP_EOL . "Результат: " . $result;
            $logMessage .= PHP_EOL . "Детали: " . $details;
            $json = json_encode($formattedTrace, JSON_UNESCAPED_SLASHES);
            $logMessage .= PHP_EOL . "Полный стек вызовов: " . $json;
            $logMessage .= PHP_EOL . "{END}" . PHP_EOL;
            file_put_contents($path, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Проверяет, является ли строка правильным JSON.
     * @param string $string Строка для проверки.
     * @return bool Возвращает true, если строка является правильным JSON.
     */
    public static function ee_isValidJson(mixed $string): bool {
        $string = is_string($string) ? json_decode($string) : false;
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Проверяет, соответствует ли строка формату UUID
     * UUID должен быть в формате 8-4-4-4-12 шестнадцатеричных символов, разделённых дефисами
     * Функция использует регулярное выражение для проверки соответствия строки стандартному формату UUID
     * @param string $uuid Строка, которую необходимо проверить на соответствие формату UUID
     * @return bool Возвращает true, если строка является валидным UUID, и false в противном случае
     */
    function ee_isValidUuid($uuid) {
        $regex = '/^\{?[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\}?$/';
        return preg_match($regex, $uuid) === 1;
    }

    /**
     * Копирование папки $source в $dest
     * Во всех переменны используется полный путь к категориям
     * @param $source - Категория источник
     * @param $dest - Категория назначения. Если отсутствуе то будет создана рекурсивно
     * @param $override - Перезаписвать имеющиеся файлы
     * @param $exclude_cat - исключаем категории
     */
    public static function copydirect($source, $dest, $override = false, $exclude_cat = []) {
        if (!is_dir($dest)) {
            mkdir($dest, 0750, true);
        }
        $res = '';
        if (!in_array($source, $exclude_cat) && $handle = opendir($source)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != '.' && $file != '..') {
                    $path = $source . '/' . $file;
                    if (is_file($path)) {
                        if (!is_file($dest . '/' . $file || $override)) {
                            if (!@copy($path, $dest . '/' . $file)) {
                                $res .= "(' . $path . ') Ошибка!!! ";
                            }
                        }
                    } elseif (is_dir($path)) {
                        if (!is_dir($dest . '/' . $file)) {
                            mkdir($dest . '/' . $file, 0750, true);
                        }
                        self::copydirect($path, $dest . '/' . $file, $override, $exclude_cat);
                    }
                }
            }
            closedir($handle);
        }
        if ($res != '') {
            file_put_contents($dir . 'logs_copy.txt', date('d.m.Y H:i:s') . ' : ' . $res . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Создает резервную копию файлов в указанной директории в архиве ZIP.
     *
     * @param string $dir Директория для создания резервной копии.
     * @param array $exclude_dirs Список директорий, которые нужно исключить из копии.
     * @param string|null $password Пароль для шифрования архива (по умолчанию null).
     * @return string Имя файла резервной копии.
     */
    public static function ee_backup_files($dir, $exclude_dirs = array(), $password = null) {
        // Создаем имя файла резервной копии
        $backup_file = "backup_" . date("Ymd") . ".zip";

        // Создаем новый объект класса ZipArchive
        $zip = new ZipArchive();

        // Открываем архив для записи и задаем пароль, если он задан
        if ($zip->open($backup_file, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE) !== TRUE) {
            die("Ошибка: Не удалось создать архив");
        }
        if ($password) {
            $zip->setPassword($password);
        }

        // Добавляем все файлы в директории к архиву
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != "." && $file != ".." && !in_array($file, $exclude_dirs)) {
                $full_path = $dir . "/" . $file;
                if (is_file($full_path)) {
                    $zip->addFile($full_path);
                } elseif (is_dir($full_path)) {
                    backup_files_recursive($full_path, $zip, '', $exclude_dirs);
                }
            }
        }

        // Закрываем архив
        $zip->close();

        // Возвращаем имя файла резервной копии
        return $backup_file;
    }

    /**
     * Создает резервную копию базы данных в файле SQL, используя mysqldump или SafeMySQL
     * @param string $host Хост базы данных
     * @param string $user Имя пользователя базы данных
     * @param string $password Пароль пользователя базы данных
     * @param string $database Имя базы данных
     * @param string $backupDir Директория для создания резервной копии
     * @param string $archivePassword Пароль для защиты архива с резервной копией
     * @return string Имя файла резервной копии
     * @throws RuntimeException При возникновении ошибок в процессе резервного копирования
     */
    function backupDatabase(
            string $host,
            string $user,
            string $password,
            string $database,
            string $backupDir,
            string $archivePassword
    ): string {
        $backupFile = "backup_" . date("Ymd") . ".sql";
        $backupFilePath = "{$backupDir}/{$backupFile}";
        $db = new SafeMySQL([
            'host' => $host,
            'user' => $user,
            'pass' => $password,
            'db' => $database
        ]);
        $hasMysqldump = (bool) shell_exec('command -v mysqldump');
        if ($hasMysqldump) {
            $command = "mysqldump -h {$host} -u {$user} -p{$password} {$database} > {$backupFilePath}";
            exec($command, $output, $returnVar);
            if ($returnVar !== 0) {
                throw new RuntimeException("Ошибка при выполнении mysqldump: " . implode("\n", $output));
            }
        } else {
            $dump = $db->dump();
            if (file_put_contents($backupFilePath, $dump) === false) {
                throw new RuntimeException("Не удалось записать дамп базы данных в файл.");
            }
        }

        $archiveFile = "{$backupFilePath}.zip";
        $zip = new ZipArchive();
        if ($zip->open($archiveFile, ZipArchive::CREATE) !== true) {
            throw new RuntimeException("Не удалось создать архивный файл.");
        }
        $zip->setPassword($archivePassword);
        if (!$zip->addFile($backupFilePath, $backupFile)) {
            throw new RuntimeException("Не удалось добавить файл в архив.");
        }
        $zip->close();
        unlink($backupFilePath);
        return $archiveFile;
    }

    /**
     * Конвертирует все ссылки контента src в base64 формат
     * @param str $content
     * @param str $dir директория поиска изображений на сервере с относительными путями
     * @return str
     */
    public static function convert_img_to_base64($content, $dir = false) {
        $lastPos = 0;
        $base64 = $old_href = [];
        $needle = 'src=';
        while (($lastPos = strpos($content, $needle, $lastPos)) !== false) {
            preg_match('/"(\\S+)"/', $content, $matches, false, $lastPos);
            if (isset($matches[1])) {
                $href = false;
                $old_href[] = $matches[1];
                if (strpos($matches[1], 'blob:https://') !== false || strpos($matches[1], 'blob:https://') !== false) { // BLOB ссылки
                    self::pre(file_get_contents($matches[1]));
                } else if (strpos($matches[1], 'https://') !== false || strpos($matches[1], 'http://') !== false) { // Ссылка на картинку
                    $base64[] = self::convert_image_base64($matches[1]);
                } else if (strpos($matches[1], 'data:image/') === false) { // файлы к какой-то дирректории
                    if ($dir) {
                        $href = self::searchImagesFile($dir, ["jpg", "jpeg", "png", "gif"], pathinfo($matches[1], PATHINFO_BASENAME));
                        $href = $href ? $href[0] : false;
                        if ($href) {
                            $base64[] = self::convert_image_base64($href);
                        }
                    }
                    if (!$href) {
                        $base64[] = 'none';
                    }
                } else {
                    $base64[] = $matches[1];
                }
            }
            $lastPos = $lastPos + strlen($needle);
        }
        return str_replace($old_href, $base64, $content);
    }

    private static function convert_image_base64($href) {
        $type = pathinfo($href, PATHINFO_EXTENSION);
        $data = file_get_contents($href);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }

    /**
     * Кодирует обратимым шифрованием данные
     * @param variant $data - любые данные
     * @param str $pass - пароль любой длинны
     * @return str
     */
    public static function ee_encode($data, $pass) {
        $add_hash = abs(crc32($pass));
        $func = function ($a) use (&$func) {
            $res = array_sum(str_split($a));
            if (strlen((string) $res) == 1) {
                return $res;
            }
            return $func($res);
        };
        $alko_index = $func($add_hash);
        $alko_index = $alko_index ? $alko_index : 11;
        $encode = base64_encode($add_hash . serialize($data));
        if ($encode) {
            $uniqueNumbers = strlen($encode);
            $temp_array = [];
            $hash_arr = array_map(function () use (&$temp_array, $uniqueNumbers) {
                do {
                    $rand = rand(0, $uniqueNumbers - 1);
                } while (in_array($rand, $temp_array));
                $temp_array[] = $rand;
                return $rand;
            },
                    array_fill(0, $uniqueNumbers, null));
        } else {
            return false;
        }
        $res_temp = $res = [];
        foreach ($hash_arr as $item) {
            $res_temp[$item] = ord($encode[$item]) + $alko_index;
        }
        ksort($res_temp);
        foreach ($res_temp as $k => $v) {
            $res[][$k] = $v;
        }
        return base64_encode(serialize($res));
    }

    /**
     * Декодирует данные
     * @param str $data
     * @param str $pass
     * @return variant | bool     
     */
    public static function ee_decode($data, $pass) {
        $add_hash = abs(crc32($pass));
        $func = function ($a) use (&$func) {
            $res = array_sum(str_split($a));
            if (strlen((string) $res) == 1) {
                return $res;
            }
            return $func($res);
        };
        $alko_index = $func($add_hash);
        $alko_index = $alko_index ? $alko_index : 11;
        $arr_data = unserialize(base64_decode($data));
        $res = [];
        if (is_array($arr_data)) {
            foreach ($arr_data as $item) {
                $key = array_key_first($item);
                $res[$key] = chr($item[$key] - $alko_index);
            }
            ksort($res);
            return unserialize(str_replace($add_hash, '', base64_decode(implode($res))));
        }
        throw new Exception('Ошибка декодирования!');
    }

    /**
     * Более простые функции шифрования
     * @param type $string
     * @param type $key
     * @return type
     */
    public static function ee_reversibleEncrypt($string, $key) {
        $result = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $char = $string[$i];
            $keyChar = $key[$i % strlen($key)];
            $char = chr(ord($char) + ord($keyChar));
            $result .= $char;
        }
        return base64_encode($result);
    }

    public static function ee_reversibleDecrypt($string, $key) {
        $result = '';
        $string = base64_decode($string);
        for ($i = 0; $i < strlen($string); $i++) {
            $char = $string[$i];
            $keyChar = $key[$i % strlen($key)];
            $char = chr(ord($char) - ord($keyChar));
            $result .= $char;
        }
        return $result;
    }

    // Более сложные функции обратного шифрования

    /**
     * Функция шифрования с использованием AES и SHA-512
     * @param string $data Данные для шифрования
     * @param string $key Ключ шифрования
     * @return string Зашифрованные данные в base64
     */
    public static function ee_encrypt($data, $key) {
        $iv_size = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($iv_size);
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha512', $encrypted, $key, true);
        return base64_encode($iv . $hmac . $encrypted);
    }

    public static function ee_decrypt($data, $key, $iv) {
        $data = base64_decode($data);
        $key = hash('sha512', $key, true);
        $decrypted = openssl_decrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted;
    }

    /**
     * Функция очистит многомерный массив от пустых значений
     * @param array $array
     * @return array
     */
    public static function ee_removeEmptyValuesToArray(array $array) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::ee_removeEmptyValuesToArray($value);
                if (empty($array[$key])) {
                    unset($array[$key]);
                }
            } else {
                if ($value === "" || $value === null) {
                    unset($array[$key]);
                }
            }
        }
        return $array;
    }

    /**
     * Преобразует значения массива в числа, если это возможно, или оставляет их как есть
     * @param array $array Массив значений для преобразования.
     * @return array Массив с преобразованными значениями.
     */
    public static function ee_convertArrayValuesToNumbers(array $array): array {
        array_walk($array, function (&$value) {
            if (is_array($value)) {
                // Рекурсивное преобразование для вложенных массивов
                $value = self::ee_convertArrayValuesToNumbers($value);
            } elseif (is_numeric($value)) {
                // Преобразуем в float, затем проверяем, можно ли преобразовать в int
                $value = (float) $value;
                if (floor($value) == $value) {
                    $value = (int) $value;
                }
            }
        });
        return $array;
    }

    /**
     * Очищает строковую переменную от специальных символов и обрезает пробелы с начала и конца строки.
     * @param string $inputString Входная строка для очистки.
     * @return string Очищенная строка.
     */
    public static function ee_cleanString($inputString) {
        if (!is_string($inputString)) {
            return false;  // Возвращает false, если входное значение не является строкой
        }
        $inputString = htmlspecialchars($inputString, ENT_QUOTES, 'UTF-8');  // Преобразование специальных символов в HTML-сущности
        $inputString = strip_tags($inputString);  // Удаление HTML и PHP-тегов из строки
        $inputString = trim($inputString);  // Удаление пробелов с начала и конца строки
        return $inputString;
    }

    /**
     * Очищает входной массив или строку от специальных символов и обрезает пробелы с начала и конца строки.
     * Если элемент массива является строкой, он будет очищен от специальных символов и обрезан.
     * Если элемент массива является другим массивом, функция будет рекурсивно вызвана для этого массива.
     * @param array|string $input Входной массив или строка для очистки.
     * @return array|string|false Очищенный массив, строка или false, если входное значение не является строкой или массивом.
     */
    public static function ee_cleanArray($input = []) {
        if (is_string($input)) {
            // Если входное значение является строкой, очищаем его и возвращаем
            return self::ee_cleanString($input);
        } elseif (is_array($input)) {
            // Если входное значение является массивом, обрабатываем каждый элемент массива
            foreach ($input as $key => $value) {
                if (is_array($value)) {
                    $input[$key] = self::ee_cleanArray($value);  // Рекурсивный вызов для вложенных массивов
                } elseif (is_string($value)) {
                    $input[$key] = self::ee_cleanString($value);  // Очистка строковых значений
                }
            }
            return $input;
        } else {
            return false;  // Возвращает false для необработанных типов данных
        }
    }

    /**
     * Получает поля указанной таблицы из базы данных
     * Если поля уже были получены ранее и сохранены в константе, возвращает их
     * В противном случае получает поля из базы данных, обновляет файл constants.php и возвращает поля
     * @param string $tableName Имя таблицы, поля которой нужно получить
     * @return array Массив имен полей таблицы
     * @throws ReflectionException Если класс Constants не найден
     * @throws RuntimeException Если не удалось обновить файл constants.php
     */
    public static function ee_getFieldsTable($tableName) {
        try {
            $reflection = new \ReflectionClass('classes\system\Constants');
        } catch (\ReflectionException $e) {
            $message = "Класс Constants не найден: " . $e->getMessage();
            self::preFile('sysclass', 'ee_getFieldsTable', 'throw new \ReflectionException', $message);
            throw new \ReflectionException($message);
        }
        $fieldsKey = strtoupper($tableName) . '_FIELDS';
        $fields = $reflection->getConstant($fieldsKey);
        if (!empty($fields) && is_array($fields)) {
            return $fields;
        }
        $fields = SafeMySQL::gi()->getAll("DESCRIBE ?n", $tableName);
        $fieldNames = array_column($fields, 'Field');
        $constantsFile = ENV_SITE_PATH . 'classes/system/Constants.php';
        if (!is_writable($constantsFile)) {
            $message = "Файл constants.php недоступен для записи.";
            self::preFile('sysclass', 'ee_getFieldsTable', 'throw new \ReflectionException', $message);
            throw new \RuntimeException($message);
        }
        $fileContent = file_get_contents($constantsFile);
        if ($fileContent === false) {
            $message = "Не удалось прочитать файл constants.php.";
            self::preFile('sysclass', 'ee_getFieldsTable', 'throw new \ReflectionException', $message);
            throw new \RuntimeException($message);
        }
        $newContent = preg_replace(
                "/const {$fieldsKey} = \[\];/",
                "const {$fieldsKey} = " . var_export($fieldNames, true) . ";",
                $fileContent
        );
        if (file_put_contents($constantsFile, $newContent) === false) {
            $message = "Не удалось обновить файл constants.php.";
            self::preFile('sysclass', 'ee_getFieldsTable', 'throw new \ReflectionException', $message);
            throw new \RuntimeException($message);
        }
        return $fieldNames;
    }

    /**
     * Добавляет указанный префикс к именам полей в строке запроса
     * @param string $where Строка условия запроса, в которой нужно добавить префикс к именам полей
     * @param array $fields Массив имен полей, к которым нужно добавить префикс
     * @param string $prefix Префикс, который нужно добавить к именам полей
     * @return string Строка условия запроса с префиксированными именами полей
     * Пример использования:
     * $where = "title LIKE '%example%' AND category_id = 1";
     * $fields = ['title', 'category_id'];
     * $prefix = 'e.';
     * $prefixedWhere = SysClass::addPrefixToFields($where, $fields, $prefix);
     * // $prefixedWhere будет содержать строку "e.title LIKE '%example%' AND e.category_id = 1"
     */
    public static function ee_addPrefixToFields($where, $fields, $prefix = '') {
        $callback = function ($matches) use ($fields, $prefix) {
            $field = $matches[1];
            if (in_array($field, $fields)) {
                return $prefix . $field;
            }
            return $field;
        };
        $prefixedWhere = preg_replace_callback('/\b([a-zA-Z_]+)\b/', $callback, $where);
        return $prefixedWhere;
    }

    /**
     * Рекурсивно обходит массив и удаляет пробелы с начала и конца каждой строки в массиве
     * @param array $array Массив, который нужно обработать
     * @return void Функция не возвращает значения, она изменяет переданный массив напрямую
     */
    public static function ee_trimArrayValues(&$array) {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ee_trimArrayValues($value);
            } elseif (is_string($value)) {
                $value = trim($value);
            }
        }
        return $array;
    }

    /**
     * Выводит трассировку стека вызовов функций
     * Для отладки проекта
     */
    public static function ee_printStackTrace() {
        $trace = debug_backtrace();
        array_shift($trace);
        echo 'Трассировка:<br/>';
        foreach ($trace as $item) {
            echo "Класс: " . ($item['class'] ?? 'N/A') . "<br/>";
            echo "Функция: " . $item['function'] . "<br/>";
            echo "Линия: " . ($item['line'] ?? 'N/A') . "<br/>";
            echo "Файл: " . ($item['file'] ?? 'N/A') . "<br/>";
            echo "<hr/>";
        }
    }

    /**
     * Проверяет, является ли запрос AJAX-запросом, пришедшим с того же сайта,
     * проверяя наличие заголовка `HTTP_X_REQUESTED_WITH` и его значение,
     * сравнивая хост из заголовка `HTTP_REFERER` с текущим хостом
     * @return bool
     */
    public static function isAjaxRequestFromSameSite(): bool {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        $referer = !empty($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER']) : null;
        $currentHost = $_SERVER['HTTP_HOST'];
        $isSameSite = $referer && $referer['host'] == $currentHost;
        return $isAjax && $isSameSite;
    }

    /**
     * Файервол проекта :-)
     */
    public static function guard() {
        if (!isset($_SERVER['HTTP_USER_AGENT']) || empty($_SERVER['HTTP_USER_AGENT'])) {
            http_response_code(400);
            exit('Bad Request');
        }
    }
    
    /**
     * Получает объект модели на основе переданных области и имени модели
     * @param string $area Название области, где находится модель
     * @param string $modelName Имя модели в формате "Модель_Название"
     * @return object|false Возвращает объект модели, если он существует, или false, если модель не найдена
     */    
    public static function getModelObject(string $area, string $modelName): object|false {
        $parts = explode('_', substr($modelName, 2));
        $className = 'Model' . implode('', array_map('ucfirst', $parts));
        $filePath = ENV_SITE_PATH . ENV_APP_DIRECTORY . ENV_DIRSEP . $area . ENV_DIRSEP . 'models' . ENV_DIRSEP . $className . '.php';
        if (!file_exists($filePath)) {
            return false;
        }
        include_once($filePath);
        if (class_exists($className)) {
            return new $className();
        }
        return false;
    }
}
