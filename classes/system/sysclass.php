<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Системный класc для использования во всём проекте
 * Все методы статические
 * @author Evgeniy Efimchenko efimchenko.ru  
 */
Class SysClass {

    function __construct() {
        throw new Exception('Static only.');
    }

    /**
     * Массив слов исключений
     */
    const ARRAY_EXCEPTIONS = array("ые", "ое", "ие", "ий", "ая", "ый", "ой", "ми", "ых", "ее", "ую", "их", "ым",
        "как", "для", "что", "что-то", "или", "это", "этих", "вот",
        "всех", "вас", "они", "она", "он", "оно", "еще", "когда",
        "где", "эта", "лишь", "уже", "вам", "нас", "нет", "чему", "пру", "ему", "нам", "кем", "без",
        "если", "надо", "все", "так", "его", "чем", "этот", "сам", "самим", "самого", "самих",
        "при", "даже", "мне", "есть", "только", "очень",
        "оба", "тут", "той", "ней", "меня", "мною",
        "сейчас", "точно", "обычно", "не", "под");

    /**
     * Массив поисковых роботов(приблизительные данные)
     */
    const ARRAY_ROBORS = [
        'YandexBot', 'YandexAccessibilityBot', 'YandexMobileBot', 'YandexDirectDyn', 'YandexScreenshotBot',
        'YandexImages', 'YandexVideo', 'YandexVideoParser', 'YandexMedia', 'YandexBlogs', 'YandexFavicons',
        'YandexWebmaster', 'YandexPagechecker', 'YandexImageResizer', 'YandexAdNet', 'YandexDirect',
        'YaDirectFetcher', 'YandexCalendar', 'YandexSitelinks', 'YandexMetrika', 'YandexNews',
        'YandexNewslinks', 'YandexCatalog', 'YandexAntivirus', 'YandexMarket', 'YandexVertis',
        'YandexForDomain', 'YandexSpravBot', 'YandexSearchShop', 'YandexMedianaBot', 'YandexOntoDB',
        'YandexOntoDBAPI', 'YandexTurbo', 'YandexVerticals', 'yandexSomething', 'Copyscape.com', 'domaintools.com',
        'Googlebot', 'Googlebot-Image', 'Mediapartners-Google', 'AdsBot-Google', 'APIs-Google',
        'AdsBot-Google-Mobile', 'AdsBot-Google-Mobile', 'Googlebot-News', 'Googlebot-Video', 'AdsBot-Google-Mobile-Apps',
        'Mail.RU_Bot', 'bingbot', 'Accoona', 'ia_archiver', 'Ask Jeeves', 'OmniExplorer_Bot', 'W3C_Validator', 'SemrushBot',
        'WebAlta', 'YahooFeedSeeker', 'Yahoo!', 'Ezooms', 'Tourlentabot', 'MJ12bot', 'AhrefsBot',
        'SearchBot', 'SiteStatus', 'Nigma.ru', 'Baiduspider', 'Statsbot', 'SISTRIX', 'AcoonBot', 'findlinks',
        'proximic', 'OpenindexSpider', 'statdom.ru', 'Exabot', 'Spider', 'SeznamBot', 'oBot', 'C-T bot',
        'Updownerbot', 'Snoopy', 'heritrix', 'Yeti', 'DomainVader', 'DCPbot', 'PaperLiBot', 'StackRambler',
        'msnbot-media', 'msnbot-news', 'openstat.ru', 'rambler', 'googlebot', 'aport', 'yahoo', 'msnbot', 'turtle', 'mail.ru', 'omsktele',
        'yetibot', 'picsearch', 'sape.bot', 'sape_context', 'gigabot', 'snapbot', 'alexa.com', 'DotBot', 'Cliqzbot', 'CCBot', 'BLEXBot',
        'megadownload.net', 'askpeter.info', 'igde.ru', 'ask.com', 'qwartabot', 'yanga.co.uk',
        'scoutjet', 'similarpages', 'oozbot', 'shrinktheweb.com', 'aboutusbot', 'followsite.com', 'facebookexternalhit',
        'dataparksearch', 'google-sitemaps', 'appEngine-google', 'feedfetcher-google',
        'liveinternet.ru', 'xml-sitemaps.com', 'agama', 'metadatalabs.com', 'h1.hrn.ru',
        'googlealert.com', 'seo-rus.com', 'yaDirectBot', 'yandeG', 'yandex', 'archive.org_bot', 'Wotbox',
        'Nigma.ru', 'bing.com', 'dotnetdotcom', 'OdklBot', 'vkShare', 'LiveInternet', 'GrapeshotCrawler', 'Twitterbot', 'BegunAdvertising'
    ];

    /**
     * PHP var_export() с коротким синтаксисом массива (квадратные скобки) с отступом в 2 пробела.
     *
     * Единственная проблема заключается в том, что если строковое значение имеет `=> \ n [`, оно будет преобразовано в `=> [`
     */
    public static function varexport($expression, $return = false) {
        $export = var_export($expression, true);
        $patterns = [
            "/array \(/" => '[',
            "/^([ ]*)\)(,?)$/m" => '$1]$2',
            "/=>[ ]?\n[ ]+\[/" => '=> [',
            "/([ ]*)(\'[^\']+\') => ([\[\'])/" => '$1$2 => $3',
            "/\n/" => ''
        ];
        $export = preg_replace(array_keys($patterns), array_values($patterns), $export);
        if ($return) {
            return $export;
        } else {
            echo $export;
        }
    }

    /**
     * Проверка URL на валидность
     * @param str $url - переданный URL
     * @return str or NULL - валидный url или NULL
     */
    public static function parse_url_if_valid($url) {
        // Массив с компонентами URL, сгенерированный функцией parse_url()
        $arUrl = parse_url($url);
        // Возвращаемое значение. По умолчанию будет считать наш URL некорректным.
        $ret = NULL;

        // Если не был указан протокол, или
        // указанный протокол некорректен для url
        if (!array_key_exists("scheme", $arUrl) || !in_array($arUrl["scheme"], array("http", "https"/* , "ftp" */)))
        // Задаем протокол по умолчанию - http
            $arUrl["scheme"] = "http";

        // Если функция parse_url смогла определить host
        if (array_key_exists("host", $arUrl) && !empty($arUrl["host"]))
        // Собираем конечное значение url
            $ret = sprintf("%s://%s%s", $arUrl["scheme"], $arUrl["host"], $arUrl["path"]);

        // Если значение хоста не определено
        // (обычно так бывает, если не указан протокол),
        // Проверяем $arUrl["path"] на соответствие шаблона URL.
        else if (preg_match("/^\w+\.[\w\.]+(\/.*)?$/", $arUrl["path"]))
        // Собираем URL
            $ret = sprintf("%s://%s", $arUrl["scheme"], $arUrl["path"]);

        // Если url валидный и передана строка параметров запроса
        if ($ret && !empty($arUrl["query"]))
            $ret .= sprintf("?%s", $arUrl["query"]);

        return $ret;
    }

    /**
     * Генерация уникального uuid
     */
    public static function generate_uuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Логирование в БД(если включено в ENV_LOG)
     * @param str $changes - Какое изменение
     * @param str $flag - тип сообщения info success error
     * @param int $who - Кто вызвал(по умолчанию система id = 8)
     */
    public static function SetLog($changes = 'not change', $flag = 'info', $who = 8) {
        $who = $who === NULL ? 8 : $who;
    }

    /**
     * Вернёт записи лога по переданным параметрам
     * @param количество записей $count
     * @return array
     */
    public static function GetLog($count = 5) {
        $sql = 'SELECT * FROM ' . ENV_DB_PREF . '`logs` ORDER BY `id` LIMIT ?i';
        return SafeMySQL::gi()->getAll($sql, $count);
    }

    /**
     * Получаем реальный ip пользователя
     * @return string
     */
    public static function client_ip() {
        $client = isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : false;
        $forward = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : false;
        $remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false;

        if (filter_var($client, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE && FILTER_FLAG_NO_RES_RANGE))
            $ip = $client;
        elseif (filter_var($forward, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE && FILTER_FLAG_NO_RES_RANGE))
            $ip = $forward;
        else
            $ip = $remote;

        return (string) $ip;
    }

    /**
     * Получаем IP хоста
     * @param str $url
     */
    public static function get_host_ip($url) {
        $ip = FALSE;
        if (strpos($url, 'http') !== FALSE) {
            $url_array = parse_url($url); // разбиваем URL на части
            $host = $url_array['host'];
        } else {
            $host = $url;
        }
        $ip = gethostbyname($host); // получаем IP по доменному имени
        if ($ip == $host) { // получили ли мы IP
            $ip = FALSE;
        }
        return $ip;
    }

    /**
     * Обрезает строку 
     * @param str $string - строка
     * @param int $len - количество символов
     * @return string обрезанная строка
     */
    public static function truncate_string($string, $len = 140) {
        $string = mb_substr(strip_tags($string), 0, $len);
        $string = rtrim($string, "!,.-");
        $string = mb_substr($string, 0, strrpos($string, ' '));
        return $string . "…";
    }

    /** Прячем часть строки за символами
     * @param str $person_name - строка в которой хотим заменить часть букв на звездочки
     * @param int $first - количество символов, открытых в начале слова
     * @param int $last - количество символов, открытых в конце слова
     * @param char $symbol - указываем символ, которым будем скрывать буквы
     */
    public static function hide_person_name($str, $first = 4, $last = 4, $symbol = '*') {
        $name_array = [$str];
        $hidden_name = '';
        foreach ($name_array as $name) {
            $part_length = mb_strlen($name);
            if (($first + $last) >= $part_length) {
                $i = 1;
                while ($i <= $part_length) {
                    $hidden_name .= $symbol;
                    $i++;
                }
                $hidden_name .= ' ';
            } else {
                $first_letters = mb_substr($name, 0, $first, "UTF-8");
                $last_letters = mb_substr($name, -1 * $last, $last, "UTF-8");
                $i = 1;
                while ($i < ($part_length - $last)) {
                    $first_letters .= $symbol;
                    $i++;
                }
                $hidden_name .= $first_letters . $last_letters . ' ';
            }
        }
        $hidden_name = rtrim($hidden_name);
        return $hidden_name;
    }

    /**
     * Определяет авторизованность и уровень доступа пользователя
     * по переданным параметрам
     * @param int $id - id пользователя из таблицы users
     * @param array int $access -  Массив с id пользователей из таблицы user_roles для проверки
     * 100 - все зарегистрированные пользователи
     * @return boolean
     */
    public static function get_access_user($id = 0, $access = array()) {
        if (!$id || !filter_var($id, FILTER_VALIDATE_INT)) { // Всех не авторизованных отправляем авторизоваться
            $trace = debug_backtrace();
            $caller = $trace[1];
            self::return_to_main(200, '/show_login_form?return=' . $caller['function']);
        }
        $user_date = new Users(array($id));
        $add_access = array(100);
        if (!in_array($user_date->get_user_role($id), $access) && !array_intersect($add_access, $access)) { // Проверка доступа
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Проверка почты на валидность
     * @param str $email - переданная почта
     * @return boolean
     */
    public static function validEmail($emails) {
        $pattern = '/.+@.+\..+/i'; // Всё остальное от лукавого!
        // Если передана строка (один адрес электронной почты), преобразуем её в массив
        if (is_string($emails)) {
            $emails = [$emails];
        }

        // Если передан массив, проходимся по каждому адресу и проверяем его
        if (is_array($emails)) {
            foreach ($emails as $email) {
                if (!preg_match($pattern, $email)) {
                    return false; // Возвращаем false при первом обнаружении невалидного адреса
                }
            }
            return true; // Все адреса валидны
        }

        return false; // Если передан не массив и не строка, возвращаем false
    }

    /**
     * Подбор ключевых слов, исключены слова из массива $adjectivearray
     * @contents - текст
     * @symbol - количество символов в слове
     * @words - количество возвращаемых слов
     * @count - количество совпадений слова в тексте
     */
    public static function keywords($contents, $symbol = 3, $words = 5, $count = 3) {
        $contents = mb_eregi_replace("[^а-яА-ЯёЁ ]", '', $contents);
        $contents = filter_var($contents, FILTER_SANITIZE_STRING, array(FILTER_FLAG_STRIP_HIGH, FILTER_FLAG_STRIP_LOW));
        $contents = @preg_replace(array("'<[/!]*?[^<>]*?>'si", "'([rn])[s]+'si", "'&[a-z0-9]{1,6};'si", "'( +)'si"), array("", "1 ", " ", " "), strip_tags($contents));
        $rearray = array("~", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "+",
            "`", '"', "№", ";", ":", "?", "-", "=", "|", "\"", "", "/",
            "[", "]", "{", "}", "'", ",", ".", "<", ">", "rn", "n", "t", "«", "»");

        $contents = @str_replace($rearray, " ", $contents);
        $keywordcache = @explode(" ", $contents);
        $rearray = array();
        foreach ($keywordcache as $word) {
            if (mb_strlen($word, "utf-8") >= $symbol && !is_numeric($word)) {
                if (!in_array(strtolower($word), self::ARRAY_EXCEPTIONS)) {
                    $rearray[$word] = (array_key_exists($word, $rearray)) ? ($rearray[$word] + 1) : 1;
                }
            }
        }
        @arsort($rearray);
        $keywordcache = @array_slice($rearray, 0, $words);
        $keywords = "";
        foreach ($keywordcache as $word => $c) {
            if ($c >= $count) {
                $keywords .= ", " . $word;
            }
        }
        return substr($keywords, 2);
    }

    /*
     * Преобразует весь HTML код в одну линию, удаляя все комментарии
     * Условные комментарии не удаляются
     * @buffer - HTML код для преобразования
     * @return строка для вывода
     */

    public static function one_line($buffer) {
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
     * Функция возвращает окончание для множественного числа слова на основании числа и массива окончаний
     * @param int $number Число на основе которого нужно сформировать окончание
     * @param Array $endingsArray Массив слов или окончаний для чисел (1, 4, 5),
     * например array('яблоко', 'яблока', 'яблок')
     * @return String
     */
    public static function getNumEnding($number = 0, $endingArray = array()) {
        $number = $number % 100;
        if ($number >= 11 && $number <= 19) {
            $ending = $endingArray[2];
        } else {
            $i = $number % 10;
            switch ($i) {
                case (1): $ending = $endingArray[0];
                    break;
                case (2):
                case (3):
                case (4): $ending = $endingArray[1];
                    break;
                default: $ending = $endingArray[2];
            }
        }
        return $ending;
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
     * Транслитерация имён файлов
     * @param str $s
     * @return str
     */
    public static function transliterate_file_name($s) {
        $s = (string) $s;
        $s = strip_tags($s);
        $s = str_replace(array("\n", "\r"), " ", $s); // убираем перевод каретки
        $s = preg_replace("/\s+/", ' ', $s); // удаляем повторяющие пробелы
        $s = trim($s);
        $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); // переводим строку в нижний регистр (иногда нужно задать локаль)
        $s = strtr($s, array('а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => ''));
        $s = str_replace($search, "-", $s); // заменяем пробелы, кавычки и точки знаком минус
        return $s;
    }

    /**
     * Транслитерация ошибочного ввода на
     * английской раскладке
     * @param str $s
     * @return str
     */
    public static function transliterate_error_input($s) {
        $s = str_replace(array("\n", "\r"), " ", $s); // убираем перевод каретки
        $s = preg_replace("/\s+/", ' ', $s); // удаляем повторяющие пробелы
        $s = trim($s); // убираем пробелы в начале и конце строки
        $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); // переводим строку в нижний регистр (иногда нужно задать локаль)
        $s = strtr($s, array_flip(array(
            "а" => "f", "А" => "F",
            "б" => ",", "Б" => "<",
            "в" => "d", "В" => "D",
            "г" => "u", "Г" => "D",
            "д" => "l", "Д" => "L",
            "е" => "t", "Е" => "T",
            "ё" => "`", "Ё" => "~",
            "ж" => ";", "Ж" => ":",
            "з" => "p", "З" => "P",
            "и" => "b", "И" => "B",
            "й" => "q", "Й" => "Q",
            "к" => "r", "К" => "R",
            "л" => "k", "Л" => "K",
            "м" => "v", "М" => "V",
            "н" => "y", "Н" => "Y",
            "о" => "j", "О" => "J",
            "п" => "g", "П" => "G",
            "р" => "h", "Р" => "H",
            "с" => "c", "С" => "C",
            "т" => "n", "Т" => "N",
            "у" => "e", "У" => "E",
            "ф" => "a", "Ф" => "A",
            "х" => "[", "Х" => "{",
            "ц" => "w", "Ц" => "W",
            "ч" => "x", "Ч" => "X",
            "ш" => "i", "Ш" => "I",
            "щ" => "o", "Щ" => "O",
            "ъ" => "]", "Ъ" => "}",
            "ы" => "s", "Ы" => "S",
            "ь" => "m", "Ь" => "M",
            "э" => "'", "Э" => "\"",
            "ю" => ".", "Ю" => ">",
            "я" => "z", "Я" => "Z",
            "," => "?", "." => "/"
        )));
        return $s; // возвращаем результат
    }

    /**
     * Переадресует на страницу с переданным кодом
     * По умолчанию код 404 и редирект на главную страницу сайта
     * @param int $code код ответа сервера
     * @param str $url куда редирект
     */
    public static function return_to_main($code = 404, $url = ENV_URL_SITE) {
        if ($code === 404) {
            $code_redirect = '404 Not Found';
        } elseif ($code === 301) {
            $code_redirect = '301 Moved Permanently';
        } elseif ($code === 307) {
            $code_redirect = '307 Temporary Redirect';
        } else {
            $code_redirect = '404 Not Found';
        }
        if (ENV_TEST) {
            $stack = debug_backtrace();
            echo ' Возврат из ' . $stack[0]['file'] . ' line ' . $stack[0]['line'] . ' to ' . $url . ' code=' . $code_redirect . '<br/>';
            die();
        }
        /* Если это не редирект то вывести шаблон ошибки */
        if ($code >= 400) {
            Session::set('code', $code_redirect);
            headers_sent() ? NULL : header("HTTP/1.1 " . $code_redirect);
            include_once(ENV_SITE_PATH . "error.php");
            Session::set('code', NULL);
            die();
        }
        headers_sent() ? NULL : header("HTTP/1.1 " . $code_redirect);
        headers_sent() ? NULL : header("Location: " . $url);
        die();
    }

    /**
     * Определение браузера пользователя	
     */
    public static function client_browser() {
        $agent = $_SERVER['HTTP_USER_AGENT'];
        preg_match("/(MSIE|Opera|Firefox|Chrome|Version|Opera Mini|Netscape|Konqueror|SeaMonkey|Camino|Minefield|Iceweasel|K-Meleon|Maxthon)(?:\/| )([0-9.]+)/", $agent, $browser_info);
        list(, $browser, $version) = $browser_info;
        if (preg_match("/Opera ([0-9.]+)/i", $agent, $opera))
            return 'Opera ' . $opera[1];
        if ($browser == 'MSIE') {
            preg_match("/(Maxthon|Avant Browser|MyIE2)/i", $agent, $ie);
            if ($ie)
                return $ie[1] . ' based on IE ' . $version;
            return 'IE ' . $version;
        }
        if ($browser == 'Firefox') {
            preg_match("/(Flock|Navigator|Epiphany)\/([0-9.]+)/", $agent, $ff);
            if ($ff)
                return $ff[1] . ' ' . $ff[2];
        }
        if ($browser == 'Opera' && $version == '9.80')
            return 'Opera ' . substr($agent, -5);
        if ($browser == 'Version')
            return 'Safari ' . $version;
        if (!$browser && strpos($agent, 'Gecko'))
            return 'Browser based on Gecko';
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
     * Проверка возможности соединения с БД
     * @param str $host - хост базы данных
     * @param str $user - пользователь MySql
     * @param str $pass - пароль пользователя базы данных
     * @param str $db_name - имя базы данных
     * @return boolean
     */
    public static function connect_db_exists($host = ENV_DB_HOST, $user = ENV_DB_USER, $pass = ENV_DB_PASS, $db_name = ENV_DB_NAME) {
        if ($host && $user && $pass && $db_name) {
            try {
                $db = new SafeMySQL(array($host, $user, $pass, $db_name));
                $db->query('show tables like ?s', ENV_DB_PREF . 'users');
                return true;
            } catch (Exception $ex) {
                if (ENV_TEST) {
                    echo $ex->getMessage();
                    return false;
                }
            }
        }
        return false;
    }

    /**
     * Рекурсивный поиск файла в папке
     * и подпапках
     * @param str $dir - где искать
     * @param str $tosearch - что искать
     * @param bool $this_dir - искать директорию
     * @return boolean || path file
     */
    public static function search_file($dir, $tosearch = false, $this_dir = false) {
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
    public static function search_images_file($dir, $allowed_types = ["jpg", "jpeg", "png", "gif"], $name = false) {
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
                $temp_array = SysClass::search_images_file($path, $allowed_types, $name);
                $res_array = $temp_array ? array_merge($res_array, $temp_array) : false;
            }
        }
        if (!$res_array || count($res_array) <= 0) {
            return false;
        }
        return $res_array;
    }

    /**
     * Вернёт по представлению его description
     * для вывода на страницу под поисковиков
     * настраивается опционально
     */
    public static function get_description_page($html_text) {
        preg_match_all('#<div itemprop="description">(.+?)</div>#is', $html_text, $arr);
        return strip_tags(implode('', $arr[1]));
    }

    /**
     * Вернёт по представлению его title
     * для вывода на страницу под поисковиков
     * настраивается опционально
     */
    public static function get_title_page($html_text) {
        preg_match_all('#<h1 itemprop="headline">(.+?)</h1>#is', $html_text, $arr);
        return strip_tags(implode('', $arr[1]));
    }

    /**
     * Функция логирования на экран
     * @param mix $val - Значение для вывода
     * @param bool $flag - Флаг моментального вывода, если указать false то вывод произойдёт только при указании GET параметра show_pre
     * @return var_dump die
     */
    public static function pre($val, $flag = true) {
        if (isset($_GET['show_pre']) || $flag) {
            $trace = debug_backtrace();
            $caller = $trace[1];
            echo $caller['file'] . ' ' . $caller['line'] . ' ' . $caller['function'] . PHP_EOL . '<br/><pre style="width: max-content; background: blue; border-radius: 5px; color: white; font-size: 16px; padding: 2%;">';
            echo htmlentities(var_export($val, true), ENT_QUOTES);
            echo '</pre>';
            die;
        }
    }

    /**
     * Функция логирования в файл
     * Принимает до 5-ти значений
     */
    public static function pre_file($val, $temp = '', $temp1 = '', $temp2 = '', $temp3 = '') {
        $error = '';
        if ('error' == strtolower($val)) {
            $error = 'error_';
        }
        if (!file_exists(ENV_SITE_PATH . 'logs')) {
            mkdir(ENV_SITE_PATH . 'logs', 0777, true);
        }
        $trace = debug_backtrace();
        $caller = $trace[1];
        $temp = $temp ? var_export($temp, true) . PHP_EOL : '';
        $temp1 = $temp1 ? var_export($temp1, true) . PHP_EOL : '';
        $temp2 = $temp2 ? var_export($temp2, true) . PHP_EOL : '';
        $temp3 = $temp3 ? var_export($temp3, true) . PHP_EOL : '';
        file_put_contents(ENV_SITE_PATH . 'logs' . ENV_DIRSEP . $error . date("Y-m-d") . '.txt', PHP_EOL . date("Y-m-d H:i:s") . ' из ' . $caller['file'] . ' Line: ' . $caller['line'] . ' Func: ' . $caller['function'] . PHP_EOL . var_export($val, true) . PHP_EOL . $temp . $temp1 . $temp2 . $temp3 . PHP_EOL, FILE_APPEND | LOCK_EX);
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
     * Создает резервную копию базы данных в файле SQL, используя mysqldump или SafeMySQL.
     *
     * @param string $host Хост базы данных.
     * @param string $user Имя пользователя базы данных.
     * @param string $password Пароль пользователя базы данных.
     * @param string $database Имя базы данных.
     * @param string $backup_dir Директория для создания резервной копии.
     * @param string $archive_password Пароль для защиты архива с резервной копией.
     * @return string Имя файла резервной копии.
     */
    function backup_database($host, $user, $password, $database, $backup_dir, $archive_password) {
        // Создаем имя файла резервной копии
        $backup_file = "backup_" . date("Ymd") . ".sql";

        // Создаем новый объект класса SafeMySQL
        $db = new SafeMySQL(array(
            'host' => $host,
            'user' => $user,
            'pass' => $password,
            'db' => $database
        ));

        // Проверяем, установлен ли mysqldump
        $has_mysqldump = (bool) shell_exec('command -v mysqldump');

        if ($has_mysqldump) {
            // Выполняем mysqldump для создания дампа базы данных
            $command = "mysqldump -h {$host} -u {$user} -p{$password} {$database} > {$backup_dir}/{$backup_file}";
            exec($command);

            // Архивируем файл с паролем
            $archive_file = "{$backup_dir}/{$backup_file}.zip";
            $zip = new ZipArchive();
            $zip->open($archive_file, ZipArchive::CREATE);
            $zip->setPassword($archive_password);
            $zip->addFile("{$backup_dir}/{$backup_file}", $backup_file);
            $zip->close();

            // Удаляем исходный файл дампа
            unlink("{$backup_dir}/{$backup_file}");
        } else {
            // Создаем дамп базы данных с помощью SafeMySQL
            $dump = $db->dump();

            // Записываем дамп в файл
            file_put_contents("{$backup_dir}/{$backup_file}", $dump);

            // Архивируем файл с паролем
            $archive_file = "{$backup_dir}/{$backup_file}.zip";
            $zip = new ZipArchive();
            $zip->open($archive_file, ZipArchive::CREATE);
            $zip->setPassword($archive_password);
            $zip->addFile("{$backup_dir}/{$backup_file}", $backup_file);
            $zip->close();

            // Удаляем исходный файл дампа
            unlink("{$backup_dir}/{$backup_file}");
        }

        // Возвращаем имя файла резервной копии
        return $archive_file;
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
                        $href = self::search_images_file($dir, ["jpg", "jpeg", "png", "gif"], pathinfo($matches[1], PATHINFO_BASENAME));
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
     *
     * @param string $data Данные для шифрования
     * @param string $key Ключ шифрования
     *
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
        // Декодируем данные из Base64
        $data = base64_decode($data);
        // Получаем хеш ключа с использованием SHA-512
        $key = hash('sha512', $key, true);
        // Расшифровываем данные с использованием AES-256-CBC
        $decrypted = openssl_decrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted;
    }

    /**
     * Функция очистит многомерный массив от пустых значений
     * @param array $array
     * @return array
     */
    public static function ee_remove_empty_values(array $array) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::ee_remove_empty_values($value);
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
    public static function ee_convertArrayValuesToNumbers($array) {
        foreach ($array as $key => $value) {
            if (is_numeric($value)) {
                if (strpos($value, '.') !== false) {
                    $array[$key] = (float) $value;
                } else {
                    $array[$key] = (int) $value;
                }
            }
        }
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
     * Получает поля указанной таблицы из базы данных.
     * Если поля уже были получены ранее и сохранены в константе, возвращает их оттуда.
     * В противном случае получает поля из базы данных, обновляет файл constants.php и возвращает поля.
     * @param string $tableName Имя таблицы, поля которой нужно получить.
     * @return array Массив имен полей таблицы.
     * @throws ReflectionException Если класс Constants не найден.
     */
    public static function ee_get_fields_table($tableName) {
        $reflection = new ReflectionClass('Constants');
        $fieldsKey = array_search($tableName, $reflection->getConstants());
        $fieldsKey .= '_FIELDS';
        $fields = $reflection->getConstant($fieldsKey);
        if (!empty($fields) && count($fields)) {
            return $fields;  // Если массив уже заполнен, возвращаем его
        }
        // Если массив не заполнен, получаем поля таблицы из базы данных
        $fields = SafeMySQL::gi()->getAll("DESCRIBE ?n", $tableName);
        $fieldNames = array_column($fields, 'Field');
        // Обновляем файл constants.php
        $constantsFile = ENV_SITE_PATH . 'classes/system/constants.php';
        $fileContent = file_get_contents($constantsFile);
        $newContent = preg_replace(
                "/({$fieldsKey} = \[\])/",
                "{$fieldsKey} = " . var_export($fieldNames, true),
                $fileContent
        );
        file_put_contents($constantsFile, $newContent);
        return $fieldNames;
    }

    /**
     * Добавляет указанный префикс к именам полей в строке запроса.
     * @param string $where Строка условия запроса, в которой нужно добавить префикс к именам полей.
     * @param array $fields Массив имен полей, к которым нужно добавить префикс.
     * @param string $prefix Префикс, который нужно добавить к именам полей.
     * @return string Строка условия запроса с префиксированными именами полей.
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
            // Проверяем, является ли найденное слово именем поля
            if (in_array($field, $fields)) {
                // Если да, добавляем префикс
                return $prefix . $field;
            }
            // Если нет, возвращаем слово без изменений
            return $field;
        };
        // Применяем callback-функцию к каждому совпадению регулярного выражения
        $prefixedWhere = preg_replace_callback('/\b([a-zA-Z_]+)\b/', $callback, $where);
        return $prefixedWhere;
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

}
