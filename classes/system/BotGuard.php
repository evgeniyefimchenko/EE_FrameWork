<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

class BotGuard {

    // Список плохих ботов (по умолчанию)
    private static $badBots = [
        'BLEXBot', 'Semrush', 'DataForSeoBot', 'AhrefsBot', 'Barkrowler', 'MJ12bot', 'Serendeputy',
        'netEstate NE Crawler', 'SeopultContentAnalyzer', 'CCBot', 'MegaIndex', 'Serpstatbot',
        'ZoominfoBot', 'Linkfluence', 'NetcraftSurveyAgent', 'weborama', 'SeekportBot', 'SEOkicks',
        'AwarioBot', 'Keys.so', 'GetIntent Crawler', 'Bytedance', 'ClaudeBot', 'Nmap', 'BuiltWith',
        'Riddler', 'Screaming Frog SEO Spider', 'Go-http-client', 'PR-CY.RU', 'wp_is_mobile',
        'ALittle Client', 'Apache-HttpClient', 'Linux Mozilla', 'paloaltonetworks', 'BackupLand',
        'Scrapy', 'Hello, world', 'Nuclei', 'WellKnownBot', 'KOCMOHABT', 'AcademicBotRTU', 'Statdom',
        'Turnitin', 'Amazonbot', 'Aboundex', '80legs', '360Spider', 'Cogentbot', 'Alexibot', 'asterias',
        'attach', 'BackDoorBot', 'BackWeb', 'Bandit', 'BatchFTP', 'Bigfoot', 'Black.Hole', 'BlackWidow',
        'BlowFish', 'BotALot', 'Buddy', 'BuiltBotTough', 'Bullseye', 'BunnySlippers', 'Cegbfeieh',
        'CheeseBot', 'CherryPicker', 'ChinaClaw', 'Collector', 'Copier', 'CopyRightCheck', 'cosmos',
        'Crescent', 'Custo', 'AIBOT', 'DISCo', 'DIIbot', 'DittoSpyder', 'Download Demon', 'Download Devil',
        'Download Wonder', 'dragonfly', 'Drip', 'eCatch', 'EasyDL', 'ebingbong', 'EirGrabber', 'EmailCollector',
        'EmailSiphon', 'EmailWolf', 'EroCrawler', 'Exabot', 'Express WebPictures', 'Extractor', 'EyeNetIE',
        'Foobot', 'flunky', 'FrontPage', 'Go-Ahead-Got-It', 'gotit', 'GrabNet', 'Grafula', 'Harvest',
        'hloader', 'HMView', 'HTTrack', 'humanlinks', 'IlseBot', 'Image Stripper', 'Image Sucker', 'Indy Library',
        'InfoNavibot', 'InfoTekies', 'Intelliseek', 'InterGET', 'Internet Ninja', 'Iria', 'Jakarta', 'JennyBot',
        'JetCar', 'JOC', 'JustView', 'Jyxobot', 'Kenjin.Spider', 'Keyword.Density', 'larbin', 'LexiBot',
        'libWeb/clsHTTP', 'likse', 'LinkextractorPro', 'LinkScan/8.1a.Unix', 'LNSpiderguy', 'LinkWalker',
        'lwp-trivial', 'LWP::Simple', 'Magnet', 'Mag-Net', 'MarkWatch', 'Mass Downloader', 'Mata.Hari',
        'Microsoft.URL', 'Microsoft URL Control', 'MIDown tool', 'MIIxpc', 'Mirror', 'Missigua Locator',
        'Mister PiX', 'moget', 'Mozilla/3.Mozilla/2.01', 'Mozilla.*NEWT', 'NAMEPROTECT', 'Navroad', 'NearSite',
        'NetAnts', 'Netcraft', 'NetMechanic', 'NetSpider', 'Net Vampire', 'NetZIP', 'NextGenSearchBot',
        'NICErsPRO', 'niki-bot', 'NimbleCrawler', 'Ninja', 'NPbot', 'Octopus', 'Offline Explorer',
        'Offline Navigator', 'Openfind', 'OutfoxBot', 'PageGrabber', 'Papa Foto', 'pavuk', 'pcBrowser',
        'PHP version tracker', 'Pockey', 'ProPowerBot/2.14', 'ProWebWalker', 'psbot', 'Pump', 'QueryN.Metasearch',
        'RealDownload', 'Reaper', 'Recorder', 'ReGet', 'RepoMonkey', 'Siphon', 'SiteSnagger', 'SlySearch',
        'SmartDownload', 'Snake', 'Snapbot', 'Snoopy', 'sogou', 'SpaceBison', 'SpankBot', 'spanner', 'Sqworm',
        'Stripper', 'Sucker', 'SuperBot', 'SuperHTTP', 'Surfbot', 'suzuran', 'Szukacz/1.4', 'tAkeOut',
        'Teleport', 'Telesoft', 'TurnitinBot/1.5', 'The.Intraformant', 'TheNomad', 'TightTwatBot', 'Titan',
        'True_bot', 'turingos', 'TurnitinBot', 'URLy.Warning', 'Vacuum', 'VoidEYE', 'Web Image Collector',
        'Web Sucker', 'WebAuto', 'WebBandit', 'Webclipping.com', 'WebCopier', 'WebEnhancer', 'WebFetch',
        'WebGo IS', 'Web.Image.Collector', 'WebLeacher', 'WebmasterWorldForumBot', 'WebReaper', 'WebSauger',
        'Website eXtractor', 'Website Quester', 'Webster', 'WebStripper', 'WebWhacker', 'WebZIP', 'Whacker',
        'Widow', 'WISENutbot', 'WWWOFFLE', 'WWW-Collector-E', 'Xaldon', 'Zeus', 'ZmEu', 'Zyborg',
        'archive.org_bot', 'bingbot', 'Wget', 'Acunetix', 'FHscan', 'Baiduspider', 'Slurp', 'DotBot'
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
            new \classes\system\ErrorLogger(
                    'Отсутствует IP-адрес или User-Agent',
                    __FUNCTION__,
                    'botguard',
                    ['REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'N/A', 'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A']
            );
            http_response_code(400);
            exit('Bad Request');
        }
        // Проверка на плохих ботов
        if (self::ENABLED_CHECKS[self::CHECK_BAD_BOT]) {
            foreach (self::$badBots as $bot) {
                if (stripos($_SERVER['HTTP_USER_AGENT'], $bot) !== false) {
                    new \classes\system\ErrorLogger(
                            'Обнаружен плохой бот',
                            __FUNCTION__,
                            'botguard',
                            ['HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT']]
                    );
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
     * @param string $input Входная строка для проверки
     * @return bool Возвращает true, если обнаружены признаки XSS, иначе false
     */
    private static function containsXss(string $input): bool {
        $xssPatterns = [
            '/<script.*?>.*?<\/script>/i', '/javascript:/i', '/on\w+=\s*["\'].*?["\']/i',
            '/<\w+.*?>.*?<\/\w+>/i', '/<\w+.*?>/i', '/<\/\w+.*?>/i', '/eval\s*\(/i',
            '/document\.cookie/i', '/alert\s*\(/i', '/confirm\s*\(/i', '/prompt\s*\(/i'
        ];
        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                new \classes\system\ErrorLogger(
                        'Обнаружена потенциальная XSS-атака',
                        __FUNCTION__,
                        'botguard',
                        ['pattern' => $pattern, 'input' => $input]
                );
                return true;
            }
        }
        return false;
    }

    /**
     * Проверяет, находится ли IP-адрес в чёрном списке
     * Удаляет записи, у которых истёк срок блокировки
     * @param string $ip IP-адрес для проверки
     * @return bool Возвращает true, если IP в чёрном списке, иначе false
     */
    private static function isIpBlacklisted(string $ip): bool {
        try {
            $db = SafeMySQL::gi();
            // Удаляем истёкшие записи
            $sqlDelete = "DELETE FROM ?n WHERE block_until < NOW()";
            $db->query($sqlDelete, Constants::IP_BLACKLIST_TABLE);
            // Получаем актуальный список заблокированных IP
            $sqlSelect = "SELECT ip_range FROM ?n WHERE block_until >= NOW()";
            $blacklistedIps = $db->getCol($sqlSelect, Constants::IP_BLACKLIST_TABLE);
            if (!empty($blacklistedIps)) {
                foreach ($blacklistedIps as $blacklistedIp) {
                    if (self::ipInRange($ip, $blacklistedIp)) {
                        new \classes\system\ErrorLogger(
                                'IP-адрес в чёрном списке',
                                __FUNCTION__,
                                'botguard',
                                ['ip' => $ip, 'blacklistedIp' => $blacklistedIp]
                        );
                        \classes\system\Session::clearKeysByPattern('botguard_%');
                        return true;
                    }
                }
            }
            return false;
        } catch (\Exception $e) {
            new \classes\system\ErrorLogger(
                    'Ошибка проверки чёрного списка IP: ' . $e->getMessage(),
                    __FUNCTION__,
                    'botguard',
                    ['ip' => $ip, 'trace' => $e->getTraceAsString()]
            );
            return false;
        }
    }

    /**
     * Проверяет, принадлежит ли IP-адрес указанному диапазону
     * @param string $ip IP-адрес для проверки
     * @param string $range Диапазон (например, "192.168.1.1", "192.168.1.0/24", "192.168.1.1-192.168.1.255")
     * @return bool Возвращает true, если IP принадлежит диапазону, иначе false
     */
    private static function ipInRange(string $ip, string $range): bool {
        if (strpos($range, '-') !== false) {
            list($startIp, $endIp) = explode('-', $range, 2);
            $startIp = ip2long(trim($startIp));
            $endIp = ip2long(trim($endIp));
            $ip = ip2long($ip);
            return $ip >= $startIp && $ip <= $endIp;
        }
        if (strpos($range, '/') !== false) {
            list($subnet, $mask) = explode('/', $range, 2);
            $subnet = ip2long($subnet);
            $ip = ip2long($ip);
            $wildcard = (1 << (32 - $mask)) - 1;
            $netmask = ~$wildcard;
            return ($ip & $netmask) === ($subnet & $netmask);
        }
        return $ip === $range;
    }

    /**
     * Проверяет, содержит ли строка потенциальные SQL-инъекции
     * @param string $input Входная строка для проверки
     * @return bool Возвращает true, если обнаружены признаки SQL-инъекции, иначе false
     */
    private static function containsSqlInjection(string $input): bool {
        $sqlPatterns = [
            '/\bUNION\b.*\bSELECT\b/i', '/\bINSERT\b.*\bINTO\b/i', '/\bDELETE\b.*\bFROM\b/i',
            '/\bUPDATE\b.*\bSET\b/i', '/\bDROP\b/i', '/\bTRUNCATE\b/i', '/\bCREATE\b/i',
            '/\bALTER\b/i', '/\bEXEC\b/i', '/\bXP_CMDSHELL\b/i', '/\b--\b/', '/\bOR\b.*\b1=1\b/i',
            '/\bAND\b.*\b1=1\b/i', '/\bWAITFOR\b.*\bDELAY\b/i', '/\bSLEEP\b.*\(/i', '/\bBENCHMARK\b.*\(/i'
        ];
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                new \classes\system\ErrorLogger(
                        'Обнаружена потенциальная SQL-инъекция',
                        __FUNCTION__,
                        'botguard',
                        ['pattern' => $pattern, 'input' => $input]
                );
                return true;
            }
        }
        return false;
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

    /**
     * Добавляет IP-адрес или диапазон в чёрный список, если он ещё не заблокирован
     * @param string $ipRange IP-адрес или диапазон (например, "192.168.1.1" или "10.0.0.0/24")
     * @param int $blockDuration Время блокировки в секундах (по умолчанию 24 часа)
     * @param string|null $reason Причина блокировки (опционально)
     * @return bool Возвращает true в случае успеха или если IP уже заблокирован, false при ошибке
     */
    public static function addIpToBlacklist(string $ipRange, int $blockDuration = 86400, ?string $reason = null): bool {
        try {
            $db = SafeMySQL::gi();

            // Проверяем, существует ли активная запись для этого IP
            $sqlCheck = "SELECT COUNT(*) FROM ?n WHERE ip_range = ?s AND block_until > NOW()";
            $exists = $db->getOne($sqlCheck, Constants::IP_BLACKLIST_TABLE, $ipRange);

            if ($exists) {
                return true; // IP уже заблокирован, считаем это успешным результатом
            }
            // Добавляем новую запись
            $blockUntil = date('Y-m-d H:i:s', time() + $blockDuration);
            $sqlInsert = "INSERT INTO ?n (ip_range, block_until, reason) VALUES (?s, ?s, ?s)";
            $db->query($sqlInsert, Constants::IP_BLACKLIST_TABLE, $ipRange, $blockUntil, $reason);
            new \classes\system\ErrorLogger(
                    'IP успешно добавлен в чёрный список',
                    __FUNCTION__,
                    'botguard_info',
                    ['ip_range' => $ipRange, 'block_until' => $blockUntil, 'reason' => $reason]
            );
            return true;
        } catch (\Exception $e) {
            new \classes\system\ErrorLogger(
                    'Ошибка добавления IP в чёрный список: ' . $e->getMessage(),
                    __FUNCTION__,
                    'botguard',
                    ['ip_range' => $ipRange, 'trace' => $e->getTraceAsString()]
            );
            return false;
        }
    }
}
