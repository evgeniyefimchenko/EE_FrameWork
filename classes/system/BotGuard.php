<?php

namespace classes\system;

use classes\system\SysClass;

class BotGuard {

    // Список плохих ботов (по умолчанию)
    private static $badBots = [
        'BLEXBot',
        'Semrush',
        'DataForSeoBot',
        'AhrefsBot',
        'Barkrowler',
        'MJ12bot',
        'Serendeputy',
        'netEstate NE Crawler',
        'SeopultContentAnalyzer',
        'CCBot',
        'MegaIndex',
        'Serpstatbot',
        'ZoominfoBot',
        'Linkfluence',
        'NetcraftSurveyAgent',
        'weborama',
        'SeekportBot',
        'SEOkicks',
        'AwarioBot',
        'Keys.so',
        'GetIntent Crawler',
        'Bytedance',
        'ClaudeBot',
        'Nmap',
        'BuiltWith',
        'Riddler',
        'Screaming Frog SEO Spider',
        'Go-http-client',
        'PR-CY.RU',
        'wp_is_mobile',
        'ALittle Client',
        'Apache-HttpClient',
        'Linux Mozilla',
        'paloaltonetworks',
        'BackupLand',
        'Scrapy',
        'Hello, world',
        'Nuclei',
        'WellKnownBot',
        'KOCMOHABT',
        'AcademicBotRTU',
        'Statdom',
        'Turnitin',
        'Amazonbot',
        'Aboundex',
        '80legs',
        '360Spider',
        'Cogentbot',
        'Alexibot',
        'asterias',
        'attach',
        'BackDoorBot',
        'BackWeb',
        'Bandit',
        'BatchFTP',
        'Bigfoot',
        'Black.Hole',
        'BlackWidow',
        'BlowFish',
        'BotALot',
        'Buddy',
        'BuiltBotTough',
        'Bullseye',
        'BunnySlippers',
        'Cegbfeieh',
        'CheeseBot',
        'CherryPicker',
        'ChinaClaw',
        'Collector',
        'Copier',
        'CopyRightCheck',
        'cosmos',
        'Crescent',
        'Custo',
        'AIBOT',
        'DISCo',
        'DIIbot',
        'DittoSpyder',
        'Download Demon',
        'Download Devil',
        'Download Wonder',
        'dragonfly',
        'Drip',
        'eCatch',
        'EasyDL',
        'ebingbong',
        'EirGrabber',
        'EmailCollector',
        'EmailSiphon',
        'EmailWolf',
        'EroCrawler',
        'Exabot',
        'Express WebPictures',
        'Extractor',
        'EyeNetIE',
        'Foobot',
        'flunky',
        'FrontPage',
        'Go-Ahead-Got-It',
        'gotit',
        'GrabNet',
        'Grafula',
        'Harvest',
        'hloader',
        'HMView',
        'HTTrack',
        'humanlinks',
        'IlseBot',
        'Image Stripper',
        'Image Sucker',
        'Indy Library',
        'InfoNavibot',
        'InfoTekies',
        'Intelliseek',
        'InterGET',
        'Internet Ninja',
        'Iria',
        'Jakarta',
        'JennyBot',
        'JetCar',
        'JOC',
        'JustView',
        'Jyxobot',
        'Kenjin.Spider',
        'Keyword.Density',
        'larbin',
        'LexiBot',
        'libWeb/clsHTTP',
        'likse',
        'LinkextractorPro',
        'LinkScan/8.1a.Unix',
        'LNSpiderguy',
        'LinkWalker',
        'lwp-trivial',
        'LWP::Simple',
        'Magnet',
        'Mag-Net',
        'MarkWatch',
        'Mass Downloader',
        'Mata.Hari',
        'Microsoft.URL',
        'Microsoft URL Control',
        'MIDown tool',
        'MIIxpc',
        'Mirror',
        'Missigua Locator',
        'Mister PiX',
        'moget',
        'Mozilla/3.Mozilla/2.01',
        'Mozilla.*NEWT',
        'NAMEPROTECT',
        'Navroad',
        'NearSite',
        'NetAnts',
        'Netcraft',
        'NetMechanic',
        'NetSpider',
        'Net Vampire',
        'NetZIP',
        'NextGenSearchBot',
        'NICErsPRO',
        'niki-bot',
        'NimbleCrawler',
        'Ninja',
        'NPbot',
        'Octopus',
        'Offline Explorer',
        'Offline Navigator',
        'Openfind',
        'OutfoxBot',
        'PageGrabber',
        'Papa Foto',
        'pavuk',
        'pcBrowser',
        'PHP version tracker',
        'Pockey',
        'ProPowerBot/2.14',
        'ProWebWalker',
        'psbot',
        'Pump',
        'QueryN.Metasearch',
        'RealDownload',
        'Reaper',
        'Recorder',
        'ReGet',
        'RepoMonkey',
        'Siphon',
        'SiteSnagger',
        'SlySearch',
        'SmartDownload',
        'Snake',
        'Snapbot',
        'Snoopy',
        'sogou',
        'SpaceBison',
        'SpankBot',
        'spanner',
        'Sqworm',
        'Stripper',
        'Sucker',
        'SuperBot',
        'SuperHTTP',
        'Surfbot',
        'suzuran',
        'Szukacz/1.4',
        'tAkeOut',
        'Teleport',
        'Telesoft',
        'TurnitinBot/1.5',
        'The.Intraformant',
        'TheNomad',
        'TightTwatBot',
        'Titan',
        'True_bot',
        'turingos',
        'TurnitinBot',
        'URLy.Warning',
        'Vacuum',
        'VoidEYE',
        'Web Image Collector',
        'Web Sucker',
        'WebAuto',
        'WebBandit',
        'Webclipping.com',
        'WebCopier',
        'WebEnhancer',
        'WebFetch',
        'WebGo IS',
        'Web.Image.Collector',
        'WebLeacher',
        'WebmasterWorldForumBot',
        'WebReaper',
        'WebSauger',
        'Website eXtractor',
        'Website Quester',
        'Webster',
        'WebStripper',
        'WebWhacker',
        'WebZIP',
        'Whacker',
        'Widow',
        'WISENutbot',
        'WWWOFFLE',
        'WWW-Collector-E',
        'Xaldon',
        'Zeus',
        'ZmEu',
        'Zyborg',
        'archive.org_bot',
        'bingbot',
        'Wget',
        'Acunetix',
        'FHscan',
        'Baiduspider',
        'Slurp',
        'DotBot'
    ];

    // Приватные константы для типов проверок
    private const CHECK_MISSING_IP_OR_UA = 'missing_ip_or_ua';
    private const CHECK_BAD_BOT = 'bad_bot';
    private const CHECK_SQL_INJECTION = 'sql_injection';
    private const CHECK_XSS = 'xss';
    private const CHECK_BLACKLISTED_IP = 'blacklisted_ip';
    // Массив для управления проверками
    private const ENABLED_CHECKS = [
        self::CHECK_MISSING_IP_OR_UA => true,
        self::CHECK_BAD_BOT => true,
        self::CHECK_SQL_INJECTION => true,
        self::CHECK_XSS => true,
        self::CHECK_BLACKLISTED_IP => true,
    ];

    /**
     * Основной метод для проверки и блокировки ботов
     */
    public static function guard() {
        // Проверка на отсутствие REMOTE_ADDR и HTTP_USER_AGENT
        if (self::ENABLED_CHECKS[self::CHECK_MISSING_IP_OR_UA] &&
                (empty($_SERVER['REMOTE_ADDR']) || empty($_SERVER['HTTP_USER_AGENT']))) {
            SysClass::preFile('blocked_request', 'CHECK_MISSING_IP_OR_UA', ['REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'], 'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT']], __LINE__);
            http_response_code(400);
            exit('Bad Request');
        }
        // Проверка на плохих ботов
        if (self::ENABLED_CHECKS[self::CHECK_BAD_BOT]) {
            foreach (self::$badBots as $bot) {
                if (stripos($_SERVER['HTTP_USER_AGENT'], $bot) !== false) {
                    SysClass::preFile('blocked_request', 'CHECK_BAD_BOT', $_SERVER['HTTP_USER_AGENT'], __LINE__);
                    http_response_code(403);
                    exit('Access Denied');
                }
            }
        }
        // Проверка на SQL-инъекции
        if (self::ENABLED_CHECKS[self::CHECK_SQL_INJECTION] &&
                self::containsSqlInjection($_SERVER['QUERY_STRING'] ?? '')) {
            http_response_code(403);
            exit('Access Denied');
        }
        // Проверка на XSS
        if (self::ENABLED_CHECKS[self::CHECK_XSS] &&
                self::containsXss($_SERVER['QUERY_STRING'] ?? '')) {
            http_response_code(403);
            exit('Access Denied');
        }
        // Проверка IP-адреса в чёрном списке
        if (self::ENABLED_CHECKS[self::CHECK_BLACKLISTED_IP] &&
                self::isIpBlacklisted($_SERVER['REMOTE_ADDR'])) {
            http_response_code(403);
            exit('Access Denied');
        }
    }

    /**
     * Проверяет, содержит ли строка потенциальные XSS-атаки
     * Функция анализирует входную строку на наличие подозрительных шаблонов,
     * таких как теги `<script>`, `javascript:`, обработчики событий (onload, onclick и т.д.),
     * а также другие опасные конструкции
     * @param string $input Входная строка для проверки
     * @return bool Возвращает `true`, если обнаружены признаки XSS, иначе `false`
     */
    private static function containsXss(string $input): bool {
        // Список подозрительных шаблонов для XSS
        $xssPatterns = [
            '/<script.*?>.*?<\/script>/i', // Теги <script>
            '/javascript:/i', // JavaScript-схемы
            '/on\w+=\s*["\'].*?["\']/i', // Обработчики событий (onload, onclick и т.д.)
            '/<\w+.*?>.*?<\/\w+>/i', // Любые HTML-теги
            '/<\w+.*?>/i', // Открывающие HTML-теги
            '/<\/\w+.*?>/i', // Закрывающие HTML-теги
            '/eval\s*\(/i', // Функция eval
            '/document\.cookie/i', // Доступ к cookies
            '/alert\s*\(/i', // Функция alert
            '/confirm\s*\(/i', // Функция confirm
            '/prompt\s*\(/i', // Функция prompt
        ];
        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                SysClass::preFile('blocked_request', 'containsXss', ['pattern' => $pattern, 'input' => $input], __LINE__);
                return true; // Обнаружен XSS
            }
        }
        return false; // XSS не обнаружен
    }

    /**
     * Проверяет, находится ли IP-адрес в чёрном списке
     * Функция проверяет, содержится ли переданный IP-адрес в списке запрещённых адресов
     * Чёрный список может быть загружен из файла, базы данных или задан вручную
     * @param string $ip IP-адрес для проверки
     * @return bool Возвращает `true`, если IP-адрес находится в чёрном списке, иначе `false`
     */
    private static function isIpBlacklisted(string $ip): bool {
        // Чёрный список IP-адресов и диапазонов
        $blacklistedIps = [
            '192.168.1.1', // Одиночный IP
            '10.0.0.0/24', // Диапазон (10.0.0.1 - 10.0.0.255)
            '203.0.113.195', // Одиночный IP
            '198.51.100.42', // Одиночный IP
            '192.168.2.1-192.168.2.100', // Диапазон (192.168.2.1 - 192.168.2.100)
        ];
        foreach ($blacklistedIps as $blacklistedIp) {
            if (self::ipInRange($ip, $blacklistedIp)) {
                SysClass::preFile('blocked_request', 'isIpBlacklisted', ['ip' => $ip, 'blacklistedIp' => $blacklistedIp], __LINE__);
                return true; // IP находится в чёрном списке
            }
        }
        return false; // IP не находится в чёрном списке
    }

    /**
     * Проверяет, принадлежит ли IP-адрес указанному диапазону
     * @param string $ip IP-адрес для проверки
     * @param string $range Диапазон (например, "192.168.1.1", "192.168.1.0/24", "192.168.1.1-192.168.1.255")
     * @return bool Возвращает `true`, если IP принадлежит диапазону, иначе `false`
     */
    private static function ipInRange(string $ip, string $range): bool {
        // Если диапазон указан в формате "начальный IP - конечный IP"
        if (strpos($range, '-') !== false) {
            list($startIp, $endIp) = explode('-', $range, 2);
            $startIp = ip2long(trim($startIp));
            $endIp = ip2long(trim($endIp));
            $ip = ip2long($ip);
            return $ip >= $startIp && $ip <= $endIp;
        }
        // Если диапазон указан в формате "IP/маска"
        if (strpos($range, '/') !== false) {
            list($subnet, $mask) = explode('/', $range, 2);
            $subnet = ip2long($subnet);
            $ip = ip2long($ip);
            $wildcard = (1 << (32 - $mask)) - 1;
            $netmask = ~$wildcard;
            return ($ip & $netmask) === ($subnet & $netmask);
        }
        // Если указан одиночный IP
        return $ip === $range;
    }

    /**
     * Проверяет, содержит ли строка потенциальные SQL-инъекции
     * Функция анализирует входную строку на наличие подозрительных SQL-шаблонов,
     * таких как `UNION SELECT`, `DROP TABLE`, `OR 1=1`, комментарии (`--`) и другие
     * @param string $input Входная строка для проверки
     * @return bool Возвращает `true`, если обнаружены признаки SQL-инъекции, иначе `false`
     */
    private static function containsSqlInjection(string $input): bool {
        // Список подозрительных шаблонов для SQL-инъекций
        $sqlPatterns = [
            '/\bUNION\b.*\bSELECT\b/i', // UNION SELECT
            '/\bINSERT\b.*\bINTO\b/i', // INSERT INTO
            '/\bDELETE\b.*\bFROM\b/i', // DELETE FROM
            '/\bUPDATE\b.*\bSET\b/i', // UPDATE SET
            '/\bDROP\b/i', // DROP
            '/\bTRUNCATE\b/i', // TRUNCATE
            '/\bCREATE\b/i', // CREATE
            '/\bALTER\b/i', // ALTER
            '/\bEXEC\b/i', // EXEC
            '/\bXP_CMDSHELL\b/i', // XP_CMDSHELL
            '/\b--\b/', // Комментарии SQL
            '/\bOR\b.*\b1=1\b/i', // OR 1=1
            '/\bAND\b.*\b1=1\b/i', // AND 1=1
            '/\bWAITFOR\b.*\bDELAY\b/i', // WAITFOR DELAY
            '/\bSLEEP\b.*\(/i', // SLEEP
            '/\bBENCHMARK\b.*\(/i', // BENCHMARK
        ];
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                SysClass::preFile('blocked_request', 'containsSqlInjection', ['pattern' => $pattern, 'input' => $input], __LINE__);
                return true; // Обнаружена SQL-инъекция
            }
        }
        return false; // SQL-инъекция не обнаружена
    }

    /**
     * Получает список плохих ботов через API
     * @param string $apiUrl URL API для получения списка ботов
     * @return array
     */
    public static function getBadBotsFromAPI(string $apiUrl): array {
        $response = file_get_contents($apiUrl);
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }
        return [];
    }

    /**
     * Устанавливает список плохих ботов вручную
     * @param array $bots Список ботов
     */
    public static function setBadBots(array $bots) {
        self::$badBots = $bots;
    }

}
