<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

// use classes\system\Constants;

/**
 * Системный класc для использования во всём проекте
 * Все методы статические
 */
class SysClass {

    /**
     * @var bool|null Кэшированный результат проверки подключения и наличия таблицы
     */
    private static $cacheDB = null;
    
    /**
     * Кеширует подключенные модели для экономии памяти
     * @var array|null
     */
    private static $cacheModel = null;
    
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
     * Получает реальный IP-адрес пользователя, учитывая прокси-серверы и заголовки
     * Проверяет различные HTTP-заголовки, такие как HTTP_CLIENT_IP, HTTP_X_FORWARDED_FOR,
     * и возвращает наиболее достоверный IP-адрес. В случае некорректных данных возвращает "unknown".
     * @return string Реальный IP-адрес пользователя или "unknown", если IP определить не удалось
     */
    public static function getClientIp(): string {
        // Возможные источники IP-адреса
        $ipSources = [
            'HTTP_CLIENT_IP' => $_SERVER['HTTP_CLIENT_IP'] ?? null,        // IP от клиента через прокси
            'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null, // IP через заголовок X-Forwarded-For
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? null              // Прямой IP клиента
        ];

        // Фильтры для проверки IP
        $ipFilterOptions = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE; // Исключаем частные и зарезервированные диапазоны

        // Проверяем каждый источник IP
        foreach ($ipSources as $header => $value) {
            if (empty($value)) {
                continue; // Пропускаем пустые значения
            }

            // Если в X-Forwarded-For несколько IP, берём первый (реальный клиентский IP)
            if ($header === 'HTTP_X_FORWARDED_FOR') {
                $ipList = explode(',', $value);
                $value = trim($ipList[0]); // Первый IP в цепочке
            }

            // Проверяем валидность IP
            if (filter_var($value, FILTER_VALIDATE_IP, $ipFilterOptions)) {
                return (string) $value; // Возвращаем первый валидный IP
            }
        }

        // Если ни один IP не прошёл проверку
        new \classes\system\ErrorLogger(
            'Не удалось определить реальный IP-адрес пользователя',
            __FUNCTION__,
            'system',
            [
                'ip_sources' => $ipSources,
                'server_data' => array_intersect_key($_SERVER, array_flip(['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR']))
            ]
        );
        return 'unknown'; // Возвращаем "unknown" как значение по умолчанию
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
     * Проверяет доступ пользователя к определенному ресурсу на основе его роли
     * @param int $userId Идентификатор пользователя. Если не указан или равен 0, считается, что пользователь не авторизован
     * @param array $access Массив ролей, имеющих доступ. Если роль пользователя не входит в этот массив, доступ будет отклонен
     * @return bool Возвращает TRUE, если у пользователя есть доступ, иначе FALSE
     */
    public static function getAccessUser(mixed $userId = 0, array $access = []): bool {
        $userData = new Users([$userId]);
        if (in_array(Constants::ALL, $access)) {
            return true;
        }
        if ($userData->data['new_user']) {
            return false;
        }
        if (in_array(Constants::ALL_AUTH, $access)) {
            return true;
        }
        $role = strtoupper($userData->data['user_role_name']);
        if (!is_string($role)) {
            self::pre("Invalid role name: " . var_export($role, true));
        }
        if (!defined("classes\system\Constants::$role")) {
            self::pre("Constant Constants::$role is not defined");
        }
        if (in_array(constant("classes\system\Constants::" . $role), $access)) {
            return true;
        }
        return false;
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
     * @return string Строка ключевых слов, разделенных запятыми или пустая строка в зависимости от ENV_GET_KEYWORDS
     */
    public static function getKeywordsFromText(string $contents, int $symbol = 3, int $words = 5, int $count = 3): string {
        if (!defined('ENV_GET_KEYWORDS') || !ENV_GET_KEYWORDS)
            return '';
        $contents = mb_eregi_replace("[^а-яА-ЯёЁ ]", '', $contents);
        $contents = strip_tags($contents);
        $contents = preg_replace(
                ["'<[/!]*?[^<>]*?>'si", "'([\r\n])[s]+'si", "'&[a-z0-9]{1,6};'si", "'( +)'si"],
                ["", " ", " ", " "],
                $contents
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
    public static function getCurrentUserId(): int|bool {
        $session = ENV_AUTH_USER === 2 ? Cookies::get('user_session') : Session::get('user_session');
        $sql = 'SELECT user_id FROM ?n WHERE `session` = ?s';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $session);
    }

    /**
     * Вернёт роль пользователя по его ID
     * @param int $user_id id пользователя
     * @return int ID роли пользователя
     */
    public static function getUserRoleById(int $user_id): int|bool {
        $sql = 'SELECT user_role FROM ?n WHERE user_id = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $user_id);
    }

    /**
     * Транслитерирует и очищает имя файла, поддерживает символы из любых языков
     * Имя файла транслитерируется с использованием `Transliterator` или, если оно недоступно, массивом $transliterationTable
     * @param string $fileName Имя файла для обработки
     * @return string Транслитерированное и очищенное имя файла
     */
    public static function transliterateFileName(string $fileName): string {
        // Удаляем теги и пробелы
        $fileName = strip_tags($fileName);
        $fileName = str_replace(array("\n", "\r"), ' ', $fileName);
        $fileName = preg_replace("/\s+/", ' ', $fileName);
        $fileName = trim($fileName);
        // Разделяем имя файла и его расширение
        $fileExtension = '';
        if (strpos($fileName, '.') !== false) {
            $fileParts = explode('.', $fileName);
            $fileExtension = array_pop($fileParts); // Получаем расширение
            $fileName = implode('.', $fileParts); // Оставшаяся часть имени
        }
        // Приводим к нижнему регистру
        $fileName = function_exists('mb_strtolower') ? mb_strtolower($fileName) : strtolower($fileName);
        // Транслитерация с использованием класса Transliterator, если доступен
        if (class_exists('Transliterator')) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove');
            $fileName = $transliterator->transliterate($fileName);
        } else {
            SysClass::preFile('errors', 'transliterateFileName', 'Класс Transliterator недоступен', __LINE__);
            // Если Transliterator недоступен, используем ручную транслитерацию кириллицы и других символов
            $transliterationTable = [
                'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
                'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
                'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
                'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y',
                'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => '', 'ї' => 'yi', 'є' => 'ye',
                ' ' => '-', '_' => '-', '+' => '-', '=' => '-',
                '(' => '', ')' => '', '{' => '', '}' => '', '[' => '', ']' => '',
                '<' => '', '>' => '', '"' => '', "'" => '', '«' => '', '»' => '',
                '\\' => '', '/' => '', '|' => '', '?' => '', '!' => '', '№' => '',
                ':' => '', ';' => '', '.' => '', ',' => '', '#' => '', '@' => '',
                '&' => '', '*' => '', '^' => '', '%' => '', '$' => '', '~' => '',
                '`' => '', '©' => '', '®' => '', '™' => '', '€' => '', '£' => '', '¥' => ''];
            $fileName = strtr($fileName, $transliterationTable);
        }
        // Заменяем некорректные символы на безопасные (разрешены только буквы, цифры и дефисы)
        $fileName = preg_replace("/[^a-z0-9\-]+/u", '', $fileName);
        // Убираем повторяющиеся дефисы
        $fileName = preg_replace("/-+/", '-', $fileName);
        $fileName = trim($fileName, '-');
        // Если имя пустое (например, если были только некорректные символы), заменим его на "file"
        if (empty($fileName)) {
            $fileName = 'file_' . time();
        }
        // Добавляем обратно расширение файла (если оно есть)
        if ($fileExtension) {
            $fileName .= '.' . strtolower($fileExtension);
        }
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
    public static function handleRedirect($code = 404, $url = ENV_URL_SITE): void {
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
    public static function detectClientBrowser(): string {
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
    public static function code2country(string $code, string $lang = 'RU'): bool|string {
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
                "ZH" => "Китай",
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
                "ZH" => "China",                
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
    public static function checkInstall(): bool {
        $cacheFilePath = ENV_CACHE_PATH . 'checkInstall.txt';
        if (file_exists($cacheFilePath)) {
            return true;
        }
        if (!ENV_DB_USER || !ENV_DB_PASS) {
            self::pre('Выполните необходимые настройки в файле configuration.php для базы данных!');
        }
        if (!ENV_SITE_EMAIL || !ENV_ADMIN_EMAIL || !SysClass::validEmail([ENV_SITE_EMAIL, ENV_ADMIN_EMAIL])) {
            self::pre('Выполните необходимые настройки в файле configuration.php для электронной почты!');
        }
        if (!ENV_DATE_SITE_CREATE) {
            self::pre('Выполните необходимые настройки в файле configuration.php для даты создания сайта!');
        }
        if (!self::checkDatabaseConnection()) {
            self::pre('Нет соединения с БД. Выполните необходимые настройки в файле configuration.php.');
        }
        if (SafeMySQL::gi()->query('SHOW TABLES LIKE ?s', Constants::USERS_TABLE)->num_rows === 0) {
            new Users(true);
        }
        if (ENV_CACHE_PATH && !self::createDirectoriesForFile(ENV_CACHE_PATH . '.cache')) {
            self::preFile('errors', 'checkInstall', 'Не удалось создать директорию кэша', ENV_CACHE_PATH);
        }
        if (!self::createDirectoriesForFile($cacheFilePath) || file_put_contents($cacheFilePath, 'Install check passed') === false) {
            self::preFile('errors', 'checkInstall', 'Не удалось создать файл кэша', $cacheFilePath);
        }
        return true;
    }

    /**
     * Проверяет языковые переменные и создает JS-файл с этими переменными, если файл не существует
     * @param string $langCode Код языка, например 'ru', 'en'.
     * @param array $lang Массив языковых переменных, который будет экспортирован в JS
     * Если файл с языковыми переменными уже существует или в массиве $lang присутствует ключ 'error', функция ничего не делает
     * Иначе создает файл с именем "{$langCode}.js" в директории временных файлов и записывает в него переменную 
     * JavaScript `window.LANG_VARS` с языковыми данными.
     * В случае ошибок записи или пустого файла, логирует их с помощью класса `ErrorLogger`
     * Логика работы:
     * - Если файл уже существует или обнаружена ошибка в массиве $lang, завершить выполнение
     * - Иначе создать содержимое JS-файла и записать его.
     * - Проверить результат записи: если произошла ошибка или размер файла 0 байт, записать ошибку в лог
     * @return void
     */
    public static function checkLangVars(string $langCode, array $lang): void {
        $langJsPath = ENV_TMP_PATH . $langCode . '.js';
        if (file_exists($langJsPath) || !empty($lang['error'])) {
            return;
        }
        $global = [
            'ENV_SITE_NAME' => ENV_SITE_NAME,            
            'ENV_DOMEN_NAME' => ENV_DOMEN_NAME,            
            'ENV_URL_SITE' => ENV_URL_SITE,
            'ENV_DEF_LANG' => ENV_DEF_LANG,
            'ENV_VERSION_CORE' => ENV_VERSION_CORE,
            'ENV_COMPRESS_HTML' => ENV_COMPRESS_HTML
        ];
        $jsContent = "window.LANG_VARS = " . json_encode(array_merge($lang, $global), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . ";";
        $result = file_put_contents($langJsPath, $jsContent);
        if ($result === false) {
            new ErrorLogger("Не удалось записать файл: $langJsPath", __FUNCTION__, 'lang');
        } elseif ($result === 0) {
            new ErrorLogger("Файл записан, но его размер равен 0 байт: $langJsPath", __FUNCTION__, 'lang');
        }
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
        // Проверка, загружено ли расширение MySQLi
        if (!extension_loaded('mysqli')) {
            self::pre('Ошибка! Расширение MySQLi не загружено. Пожалуйста, установите и активируйте расширение MySQLi');
        }
        if (!$host || !$user || !$pass || !$db_name) {
            return false;
        }
        try {
            $db = new SafeMySQL([$host, $user, $pass, $db_name]);
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
            echo (isset($caller['file']) ? $caller['file'] : '') . ' Line: ' . (isset($caller['line']) ? $caller['line'] : '') . ' Func: ' . $caller['function'] . PHP_EOL
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
     * Функция логирования в файл с расширенной информацией
     * @param string $subFolder Подпапка для лога
     * @param string $initiator Инициатор записи в лог
     * @param mixed $result Результат для логирования
     * @param mixed $details Дополнительные детали
     */
    public static function preFile(string $subFolder, string $initiator, mixed $result, mixed $details = ''): void {
        if (ENV_LOG) {
            $logsPath = ENV_LOGS_PATH . $subFolder;
            if (!file_exists($logsPath)) {
                mkdir($logsPath, 0755, true);
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
     * Рекурсивно создаёт каталоги по указанному пути, если они не существуют
     * Функция не создаёт файл, а только структуру директорий до него
     * В случае успеха возвращает true, в случае ошибки — false и записывает лог
     * @param string $filePath Путь к файлу, для которого нужно создать директории
     * @param int $permissions Права на создаваемые директории (по умолчанию 0775)
     * @return bool Возвращает true в случае успешного создания директорий, иначе false
     */
    public static function createDirectoriesForFile(string $filePath, int $permissions = 0775): bool {
        $directory = dirname($filePath);
        if (file_exists($directory)) {
            return true;
        }
        if (!mkdir($directory, $permissions, true)) {
            self::preFile('errors', 'createDirectoriesForFile', 'Ошибка создания директории', $filePath);
            return false;
        }
        return true;
    }

    /**
     * Удаляет содержимое папки (файлы и подпапки)
     * @param string $path Путь к папке
     * @param bool $delete_self Удалять ли саму папку после очистки
     * @return void
     */
    public static function ee_removeDir(string $path, bool $delete_self = false): void {
        $path = rtrim($path, '/');
        $glob = glob("$path/{,.}[!.,!..]*", GLOB_BRACE);
        if (is_array($glob)) {
            foreach ($glob as $file) {
                if (is_dir($file)) {
                    self::ee_removeDir($file, true);
                } else {
                    @unlink($file);
                }
            }
        }
        if ($delete_self) {
            @rmdir($path);
        }
    }

    /**
     * Проверяет, является ли строка правильным JSON
     * @param mixed $input Данные для проверки
     * @return bool Возвращает true, если входные данные являются правильным JSON
     */
    public static function ee_isValidJson(mixed $input): bool {
        if (!is_string($input)) {
            return false;
        }
        json_decode($input);
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
     * Создает резервную копию файлов в указанной директории в архиве ZIP
     * @param string $dir Директория для создания резервной копии
     * @param array $exclude_dirs Список директорий, которые нужно исключить из копии
     * @param string|null $password Пароль для шифрования архива (по умолчанию null)
     * @return string Имя файла резервной копии
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
                    $base64[] = self::convertImageBase64($matches[1]);
                } else if (strpos($matches[1], 'data:image/') === false) { // файлы к какой-то дирректории
                    if ($dir) {
                        $href = self::searchImagesFile($dir, ["jpg", "jpeg", "png", "gif"], pathinfo($matches[1], PATHINFO_BASENAME));
                        $href = $href ? $href[0] : false;
                        if ($href) {
                            $base64[] = self::convertImageBase64($href);
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

    private static function convertImageBase64($href) {
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
     * @param array $array Массив значений для преобразования
     * @return array Массив с преобразованными значениями
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
     * Очищает строковую переменную от специальных символов и обрезает пробелы с начала и конца строки
     * @param string $inputString Входная строка для очистки
     * @return string Очищенная строка
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
    public static function ee_getFieldsTable(string $tableName) {
        try {
            $reflection = new \ReflectionClass('classes\system\Constants');
        } catch (\ReflectionException $e) {
            $message = "Класс Constants не найден: " . $e->getMessage();
            self::preFile('sysclass', 'ee_getFieldsTable', 'throw new \ReflectionException', $message);
            throw new \ReflectionException($message);
        }
        $constantTableName = str_replace(ENV_DB_PREF, '', $tableName) . '_table';
        $fieldsKey = strtoupper($constantTableName) . '_FIELDS';
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
        $newContent = str_replace($fieldsKey . ' = []', $fieldsKey . ' = [' . implode(',', array_map(function ($value) {
                            return "'" . addslashes($value) . "'";
                        }, $fieldNames)) . ']', $fileContent);
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
     * Выводит статистику запросов SafeMySQL в файл
     * Функция собирает статистику запросов, выполняемых через SafeMySQL, и сохраняет информацию в лог-файле
     * Статистика включает сам запрос, время выполнения, общее время и трассировку вызовов
     * @param string $logFile Имя файла, в который будет записана статистика. По умолчанию 'mysql_log'
     * @return void
     */
    public static function ee_printSafeMySQLStats(string $logFile = 'mysql_log'): void {
        $stats = array_reverse(classes\plugins\SafeMySQL::gi()->getStats());
        $echo = '';
        foreach ($stats as $item) {
            $echo .= "QUERY: " . $item['query'] . PHP_EOL;
            $echo .= "Timer: " . $item['timer'] . PHP_EOL;
            $echo .= "Total time: " . $item['total_time'] . PHP_EOL;
            $echo .= "Backtrace: " . var_export($item['backtrace'], true) . PHP_EOL;
        }
        self::preFile($logFile, 'ee_printSafeMySQLStats', end($stats)['total_time'], $echo);
    }

    /**
     * Проверяет, является ли запрос AJAX-запросом, с сайта проекта,
     * проверяя наличие заголовка `HTTP_X_REQUESTED_WITH` и его значение,
     * сравнивая хост из заголовка `HTTP_REFERER` с текущим хостом
     * @return bool
     */
    public static function isAjaxRequestFromSameSite(): bool {        
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        $referer = !empty($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER']) : null;
        $currentHost = $_SERVER['HTTP_HOST'];
        $isSameSite = $referer && $referer['host'] == $currentHost;
        return $isAjax && $isSameSite && !empty(ENV_SITE);
    }

    /**
     * Получает объект модели на основе переданных области и имени модели
     * @param string $area Название области, где находится модель
     * @param string $modelName Имя модели в формате "Модель_Название"
     * @param array $params Параметры для модели
     * @return object|false Возвращает объект модели, если он существует, или false, если модель не найдена
     */
    public static function getModelObject(string $area, string $modelName, array $params = []): object|false {
        $parts = explode('_', substr($modelName, 2));
        $className = 'Model' . implode('', array_map('ucfirst', $parts));
        if (is_array(self::$cacheModel) && !empty(self::$cacheModel[$className])) {
            return self::$cacheModel[$className];
        }                
        $filePath = ENV_SITE_PATH . ENV_APP_DIRECTORY . ENV_DIRSEP . $area . ENV_DIRSEP . 'models' . ENV_DIRSEP . $className . '.php';
        if (!file_exists($filePath)) {
            return false;
        }
        include_once($filePath);
        if (class_exists($className)) {
            $classObject = new $className($params);
            self::$cacheModel[$className] = $classObject;
            return $classObject;
        }
        return false;
    }

    /**
     * Сохраняет глобальную опцию
     * @param string $key Ключ опции
     * @param mixed $value Значение опции
     * @return bool
     */
    public static function setOption(string $key, $value): bool {
        // Проверяем, существует ли опция
        $existing = SafeMySQL::gi()->getOne("SELECT option_id FROM ?n WHERE option_key = ?s", Constants::GLOBAL_OPTIONS, $key);
        if ($existing) {
            SafeMySQL::gi()->query("UPDATE ?n SET option_value = ?s, updated_at = NOW() WHERE option_id = ?i", Constants::GLOBAL_OPTIONS, $value, $existing);
        } else {
            SafeMySQL::gi()->query("INSERT INTO ?n (option_key, option_value) VALUES (?s, ?s)", Constants::GLOBAL_OPTIONS, $key, $value);
        }
        return true;
    }

    /**
     * Получает глобальную опцию
     * @param string $key Ключ опции
     * @return mixed
     */
    public static function getOption(string $key) {
        return SafeMySQL::gi()->getOne("SELECT option_value FROM ?n WHERE option_key = ?s", Constants::GLOBAL_OPTIONS, $key);
    }

    /**
     * Удаляет глобальную опцию
     * @param string $key Ключ опции
     * @return bool
     */
    public static function deleteOption(string $key): bool {
        SafeMySQL::gi()->query("DELETE FROM ?n WHERE option_key = ?s", Constants::GLOBAL_OPTIONS, $key);
        return true;
    }
}
