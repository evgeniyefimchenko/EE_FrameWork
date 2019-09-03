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
	* Массив поисковых роботов
	*/
	const ARRAY_ROBORS = array();
	
    /**
     * Логирование в БД(если включено в ENV_LOG)
     * $changes - Какое изменение
     * $flag - тип сообщения info success error
     * $who - Кто вызвал(по умолчанию система id = 8)
     */

    public static function SetLog($changes = 'not change', $flag = 'info', $who = 8) {
        $sql = 'INSERT INTO ' . ENV_DB_PREF . '`logs` SET who=?i, changes=?s, flag=?s';
        $res_q = SafeMySQL::gi()->query($sql, $who, $changes, $flag);
    }

    /**
     * Получаем реальный ip пользователя
     * @return string
     */
    public static function client_ip() {
        $client = $_SERVER['HTTP_CLIENT_IP'];
        $forward = $_SERVER['HTTP_X_FORWARDED_FOR'];
        $remote = $_SERVER['REMOTE_ADDR'];

        if (filter_var($client, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE && FILTER_FLAG_NO_RES_RANGE))
            $ip = $client;
        elseif (filter_var($forward, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE && FILTER_FLAG_NO_RES_RANGE))
            $ip = $forward;
        else
            $ip = $remote;

        return (string) $ip;
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

    /**
     * Определяет авторизованность и уровень доступа пользователя
     * по переданным параметрам
     * @param int $id - id пользователя из таблицы users
     * @param array int $access -  Массив с id пользователей из таблицы user_roles для проверки
     * 100 - все зарегистрированные пользователи
     * @return boolean
     */
    public static function get_access_user($id = 0, $access = array()) {
        if (!$id || !filter_var($id, FILTER_VALIDATE_INT)) {
            return FALSE;
        }
        $user_date = new Users(array($id));
        $add_access = array(100);
        if (!in_array($user_date->get_user_role($id), $access) && !array_intersect($add_access, $access)) {
            return FALSE;
        }
        return TRUE;
    }
	
	/**
	* Проверка почты на валидность
	* @param str $email - переданная почта
	* @return boolean
	*/
    public function validEmail($email) {
        $pattern = '/.+@.+\..+/i'; // Всё остальное от лукавого!
		return preg_match($pattern, $email);
    }	

    /**
     * Подбор ключевых слов, исключены слова из массива $adjectivearray
     * @contents - текст
     * @symbol - количество символов в слове
     * @words - количество возвращаемых слов
     * @count - количество совпадений слова в тексте
     */
    public function keywords($contents, $symbol = 3, $words = 5, $count = 3) {
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
     * @param  $number Integer Число на основе которого нужно сформировать окончание
     * @param  $endingsArray  Array Массив слов или окончаний для чисел (1, 4, 5),
     *         например array('яблоко', 'яблока', 'яблок')
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
     * Вернёт название страны по международному коду
     */
    public static function code2country($code) {
        switch ($code) {
            case "RU" : return "Россия";
            case "UA" : return "Украина";
            case "BY" : return "Беларусь";
            case "KZ" : return "Казахстан";
            case "UZ" : return "Узбекистан";
            case "AM" : return "Армения";
            case "AZ" : return "Азербаджан";
            case "BE" : return "Бельгия";
            case "TR" : return "Турция";
            case "TM" : return "Туркмения";
            case "TJ" : return "Таджикистан";
            case "KG" : return "Киргизия";
            case "AD" : return "Андорра";
            case "AF" : return "Афганистан";
            case "AG" : return "Антигуа ";
            case "AI" : return "Ангилья";
            case "AL" : return "Албания";
            case "AO" : return "Ангола";
            case "AQ" : return "Антарктида";
            case "AR" : return "Аргентина";
            case "AS" : return "Американское Самоа";
            case "AU" : return "Австралия";
            case "AW" : return "Аруба";
            case "BA" : return "Босния";
            case "BB" : return "Барбадос";
            case "BD" : return "Бангладеш";
            case "BG" : return "Болгария";
            case "BF" : return "Буркина-Фасо";
            case "BH" : return "Бахрейн";
            case "BI" : return "Бурунди";
            case "BJ" : return "Бенин";
            case "BN" : return "Бруней-Даруссалам";
            case "BO" : return "Боливия";
            case "BR" : return "Бразилия";
            case "BS" : return "Багамы";
            case "BT" : return "Бутан";
            case "BW" : return "Ботсвана";
            case "BZ" : return "Белиз";
            case "CA" : return "Канада";
            case "CD" : return "Конго";
            case "CF" : return "Центрально-Африканская Республика";
            case "CG" : return "Конго";
            case "CI" : return "Кот дИвуар";
            case "CL" : return "Чили";
            case "CM" : return "Камерун";
            case "CN" : return "Китай";
            case "CO" : return "Колумбия";
            case "CR" : return "Коста-Рика";
            case "CU" : return "Куба";
            case "CV" : return "Кабо-Верде";
            case "CY" : return "Кипр";
            case "CZ" : return "Чешская Республика";
            case "DK" : return "Дания";
            case "DZ" : return "Алжир";
            case "DJ" : return "Джибути";
            case "DM" : return "Доминика";
            case "DO" : return "Доминиканская Республика";
            case "EG" : return "Египет";
            case "SV" : return "Эль-Сальвадор";
            case "EQ" : return "Экваториальная Гвинея";
            case "ER" : return "Эритрея";
            case "EE" : return "Эстония";
            case "ET" : return "Эфиопия";
            case "FO" : return "Фарерские острова";
            case "FJ" : return "Фиджи";
            case "FI" : return "Финляндия";
            case "FR" : return "Франция";
            case "GA" : return "Габон";
            case "GM" : return "Гамбия";
            case "GE" : return "Грузия";
            case "DE" : return "Германия";
            case "GH" : return "Гана";
            case "GQ" : return "Экваториальная Гвинея";
            case "GR" : return "Греция";
            case "GD" : return "Гренада";
            case "GT" : return "Гватемала";
            case "GN" : return "Гвинея";
            case "GW" : return "Гвинея-Бисау";
            case "GY" : return "Гайана";
            case "HT" : return "Гаити";
            case "HN" : return "Гондурас";
            case "HK" : return "Гонконг";
            case "HR" : return "Хорватия";
            case "HU" : return "Венгрия";
            case "IS" : return "Исландия";
            case "IN" : return "Индия";
            case "ID" : return "Индонезия";
            case "IR" : return "Иран";
            case "IQ" : return "Ирак";
            case "IE" : return "Ирландия";
            case "IL" : return "Израиль";
            case "IT" : return "Италия";
            case "JM" : return "Ямайка";
            case "JP" : return "Япония";
            case "JO" : return "Иордания";
            case "KE" : return "Кения";
            case "KH" : return "Камбоджа";
            case "KI" : return "Кирибати";
            case "KM" : return "Коморы";
            case "KW" : return "Кувейт";
            case "KY" : return "Острова Кайман";
            case "LV" : return "Латвия";
            case "LB" : return "Ливан";
            case "LS" : return "Лесото";
            case "LR" : return "Либерия";
            case "LY" : return "Ливия";
            case "LI" : return "Лихтенштейн";
            case "LT" : return "Литва";
            case "LU" : return "Люксембург";
            case "MO" : return "Макао";
            case "MK" : return "Республика Македония";
            case "MG" : return "Мадагаскар";
            case "MW" : return "Малави";
            case "MY" : return "Малайзия";
            case "MV" : return "Мальдивы";
            case "ML" : return "Мали";
            case "MT" : return "Мальта";
            case "MH" : return "Маршалловы острова";
            case "MR" : return "Мавритания";
            case "MU" : return "Маврикий";
            case "MX" : return "Мексика";
            case "FM" : return "Микронезия";
            case "MD" : return "Молдова";
            case "MC" : return "Монако";
            case "MN" : return "Монголия";
            case "ME" : return "Черногория";
            case "MA" : return "Марокко";
            case "MZ" : return "Мозамбик";
            case "MM" : return "Мьянма";
            case "NA" : return "Намибия";
            case "NR" : return "Науру";
            case "NP" : return "Непал";
            case "NL" : return "Нидерланды";
            case "NZ" : return "Новая Зеландия";
            case "NI" : return "Никарагуа";
            case "NE" : return "Нигер";
            case "NG" : return "Нигерия";
            case "NO" : return "Норвегия";
            case "OM" : return "Оман";
            case "PK" : return "Пакистан";
            case "PW" : return "Палау";
            case "PA" : return "Панама";
            case "PG" : return "Папуа-Новая Гвинея";
            case "PY" : return "Парагвай";
            case "PE" : return "Перу";
            case "PH" : return "Филиппины";
            case "PL" : return "Польша";
            case "PT" : return "Португалия";
            case "PR" : return "Пуэрто-Рико";
            case "QA" : return "Катар";
            case "RO" : return "Румыния";
            case "RW" : return "Руанда";
            case "LC" : return "Сент-Люсия";
            case "WS" : return "Самоа";
            case "SM" : return "Сан-Марино";
            case "ST" : return "Сан-Томе и Принсипи";
            case "SA" : return "Саудовская Аравия";
            case "UK" : return "Шотландия";
            case "SN" : return "Сенегал";
            case "RS" : return "Сербия";
            case "SL" : return "Сьерра-Леоне";
            case "SG" : return "Сингапур";
            case "SK" : return "Словакия";
            case "SI" : return "Словения";
            case "SB" : return "Соломоновы острова";
            case "SO" : return "Сомали";
            case "ZA" : return "Южная Африка";
            case "KR" : return "Южная Корея";
            case "ES" : return "Испания";
            case "LK" : return "Шри-Ланка";
            case "SD" : return "Судан";
            case "SR" : return "Суринам";
            case "SZ" : return "Свазиленд";
            case "SE" : return "Швеция";
            case "CH" : return "Швейцария";
            case "SY" : return "Сирия";
            case "TW" : return "Тайвань";
            case "TZ" : return "Танзания";
            case "TD" : return "Чад";
            case "TH" : return "Таиланд";
            case "TL" : return "Тимор-Лесте";
            case "TG" : return "Того";
            case "TO" : return "Тонга";
            case "TT" : return "Тринидад и Тобаго";
            case "TN" : return "Тунис";
            case "TV" : return "Тувалу";
            case "UG" : return "Уганда";
            case "AE" : return "Объединенные Арабские Эмираты";
            case "GB" : return "Соединенное Королевство";
            case "US" : return "Соединенные Штаты";
            case "UY" : return "Уругвай";
            case "VU" : return "Вануату";
            case "VA" : return "Ватикан";
            case "VE" : return "Венесуэла";
            case "EH" : return "Западная Сахара";
            case "YE" : return "Йемен";
            case "ZM" : return "Замбия";
            case "ZW" : return "Зимбабве";
        }
    }

	/**
	* Рекурсивный поиск файла в папке
	* и подпапках
	* @param str $dir - где искать
	* @param str $tosearch - что искать
	* @return boolean || path file
	*/
	public static function search_file($dir, $tosearch) {
		$files = array_diff(scandir($dir), Array(".", ".."));
		foreach ($files as $d) {
			$path = $dir . "/" . $d;
			if (!is_dir($path)) { // Это не папка
				if (strtolower($d) == strtolower($tosearch)) {
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
	* Проверка возможности соединения с БД
	* @param str $host - хост базы данных
	* @param str $user - пользователь MySql
	* @param str $pass - пароль пользователя базы данных
	* @param str $db_name - имя базы данных
	* @return boolean
	*/
	public static function connect_db_exists($host = ENV_DB_HOST, $user = ENV_DB_USER, $pass = ENV_DB_PASS, $db_name = ENV_DB_NAME){
		if ($host && $user && $pass && $db_name) {
			try {
				SafeMySQL::gi()->query('show tables like ?s', ENV_DB_PREF.'users');
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
	* Рекурсивный поиск изображений в подпапках
	* Для использования необходимо удалить на выходе асолютный путь до каталога
	* Пример:
	* $dir = ENV_SITE_PATH . "/uploads/images/my_img";
	* foreach(str_replace(ENV_SITE_PATH, '', SysClass::search_images_file($dir)) as $path_image) {echo '<img src="'.$path_image.'" />';}
	* @param str $dir - начальная категория поиска
	* @param array $allowed_types - разрешенные разширения файлов
	* @return array - массив с относительными путями к файлам изображений
	*/
	public static function search_images_file($dir, $allowed_types = array("jpg", "jpeg", "png", "gif")) {
		$res_array = []; 
		$files = array_diff(scandir($dir), Array(".", ".."));
		foreach ($files as $file) {			
			$path = $dir . "/" . $file;	
			if (!is_dir($path)) {
				$ext = pathinfo($file, PATHINFO_EXTENSION);
				if(in_array($ext, $allowed_types)) {					
					$res_array[] = $dir . "/" . $file;
				}
			} else {
				$res_array = array_merge($res_array, SysClass::search_images_file($path, $allowed_types));							
			}
		}
		return $res_array;
	}

	/**
	* Вернёт по представлению его description
	* для вывода на страницу под поисковиков
	* настраивается опционально
	*/
	public static  function get_description_page($html_text) {
		preg_match_all('#<div itemprop="description">(.+?)</div>#is', $html_text, $arr);
		return strip_tags(implode('', $arr[1]));
	}
	
	/**
	* Вернёт по представлению его title
	* для вывода на страницу под поисковиков
	* настраивается опционально
	*/
	public static  function get_title_page($html_text) {
		preg_match_all('#<h1 itemprop="headline">(.+?)</h1>#is', $html_text, $arr);
		return strip_tags(implode('', $arr[1]));
	}


}
