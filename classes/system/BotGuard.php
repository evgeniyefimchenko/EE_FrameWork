<?php

namespace classes\system;

use classes\plugins\SafeMySQL;
use classes\system\SysClass;

class BotGuard {

    /**
     * @var bool $useRedis Использовать ли Redis для кеширования
     */
    private static $useRedis = false;
    private static ?\Redis $redisClient = null;
    // Список плохих ботов
    private static $badBots = [];

    // Приватные константы для типов проверок
    private const CHECK_MISSING_IP_OR_UA = 'missing_ip_or_ua';
    private const CHECK_BAD_BOT = 'bad_bot';
    private const CHECK_SQL_INJECTION = 'sql_injection';
    private const CHECK_XSS = 'xss';
    private const CHECK_BLACKLISTED_IP = 'blacklisted_ip';

    /** @var bool Флаг, чтобы не инициализировать класс несколько раз */
    private static $isInitialized = false;

    // Новые типы проверок
    private const CHECK_RATE_LIMIT = 'rate_limit';
    private const CHECK_HONEYPOT = 'honeypot';
    // Массив для управления проверками
    private const ENABLED_CHECKS = [
        self::CHECK_MISSING_IP_OR_UA => true,
        self::CHECK_BAD_BOT => true,
        self::CHECK_SQL_INJECTION => true,
        self::CHECK_XSS => true,
        self::CHECK_BLACKLISTED_IP => true,
        self::CHECK_RATE_LIMIT => true,
        self::CHECK_HONEYPOT => true,
    ];

    /** @var string Ключ в Redis для хранения набора заблокированных IP (Set) */
    private static $redisKeyIpBlacklist = 'ee_ip_blacklist_set';

    /** @var string Ключ-маркер, указывающий, что черный список загружен в Redis */
    private static $redisKeyBlacklistMarker = 'ee_blacklist_loaded_marker';

    /**
     * Инициализирует класс: определяет доступность Redis и загружает списки ботов
     * Выполняется один раз за запрос
     */
    private static function initialize() {
        if (self::$isInitialized) {
            return;
        }
        // 1. Определяем, доступен ли Redis, и сохраняем результат в статическое свойство
        if (ENV_GUARD_REDIS) {
            try {
                $redis = new \Redis();
                self::$redisClient = $redis->connect(ENV_REDIS_ADDRESS, ENV_REDIS_PORT);
                if (self::$redisClient) {
                    self::$useRedis = true;
                }
            } catch (\Exception $e) {
                new ErrorLogger('Сбой подключения Redis в BotGuard: ' . $e->getMessage(), __FUNCTION__, 'botguard_redis');
                self::$useRedis = false;
            }
        }
        // 2. Логика загрузки списков ботов (с учетом доступности Redis)
        $localFile = ENV_TMP_PATH . 'bad_bots.json';
        $botsFromFile = [];
        if (file_exists($localFile)) {
            $decodedJson = json_decode(file_get_contents($localFile), true);
            if (is_array($decodedJson)) {
                $botsFromFile = $decodedJson;
            }
        } else {
            new \classes\system\ErrorLogger(
                    'Локальный файл со списком ботов не найден. Защита может быть неполной.',
                    __FUNCTION__,
                    'botguard_info', // Информационное сообщение, не ошибка
                    ['path' => $localFile]
            );
        }
        if (self::$useRedis) {
            // Если Redis используется и маркер отсутствует, загружаем в него список
            if (!self::$redisClient->exists('ee_bad_bots_loaded_marker')) {
                self::$redisClient->del('ee_bad_bots_set');
                if (!empty($botsFromFile)) {
                    self::$redisClient->sAddArray('ee_bad_bots_set', $botsFromFile);
                }
                self::$redisClient->set('ee_bad_bots_loaded_marker', time(), 3600);
            }
        } else {
            // Если Redis не используется, загружаем список в локальное свойство
            self::$badBots = array_unique(array_merge(self::$badBots, $botsFromFile));
        }

        self::$isInitialized = true;
    }

    /**
     * Проверяет все входящие переменные (GET, POST, COOKIE) на атаки.
     * @return bool True, если атака обнаружена.
     */
    private static function inspectRequestVariables(array $inputs = []): bool {
        $ip = SysClass::getClientIp();
        foreach ($inputs as $type => $superglobal) {
            foreach ($superglobal as $key => $value) {
                $value = is_string($value) ? $value : ''; // Проверяем только строки
                if (self::ENABLED_CHECKS[self::CHECK_SQL_INJECTION] && (self::containsSqlInjection($key) || self::containsSqlInjection($value))) {
                    return true;
                }
                if (self::ENABLED_CHECKS[self::CHECK_XSS] && (self::containsXss($key) || self::containsXss($value))) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Проверяет, не превысил ли IP-адрес лимит запросов.
     * Автоматически выбирает хранилище: Redis или БД.
     * @param string $ip IP-адрес для проверки
     * @return bool True, если лимит превышен
     */
    private static function isRateLimited(string $ip): bool {
        if (self::$useRedis) {
            return self::_rateLimitRedis($ip);
        } else {
            return self::_rateLimitDb($ip);
        }
    }

    /**
     * Реализация Rate Limit на Redis.
     * @param string $ip
     * @return bool
     */
    private static function _rateLimitRedis(string $ip): bool {
        try {
            $redis = (new \classes\system\CacheManager())->getRedisClient();
            if (!$redis) {
                return false;
            }
            $key = 'rate_limit:' . $ip;
            $count = $redis->incr($key);
            if ($count === 1) {
                $redis->expire($key, ENV_GUARD_RATE_LIMIT_WINDOW);
            }
            if ($count > ENV_GUARD_RATE_LIMIT_COUNT) {
                new \classes\system\ErrorLogger('Превышен лимит запросов (Redis)', __FUNCTION__, 'botguard', ['ip' => $ip, 'count' => $count]);
                self::addIpToBlacklist($ip, 3600, 'Rate limit exceeded');
                return true;
            }
            return false;
        } catch (\Exception $e) {
            new \classes\system\ErrorLogger('Ошибка Redis при Rate Limiting: ' . $e->getMessage(), __FUNCTION__, 'botguard_redis');
            return false;
        }
    }

    /**
     * Реализация Rate Limit на Базе Данных (резервный механизм).
     * @param string $ip
     * @return bool
     */
    private static function _rateLimitDb(string $ip): bool {
        try {
            $db = SafeMySQL::gi();
            $sql = "INSERT INTO ?n (ip, first_request_at) VALUES(?s, NOW())
                    ON DUPLICATE KEY UPDATE request_count = IF(first_request_at < NOW() - INTERVAL ?i SECOND, 1, request_count + 1),
                    first_request_at = IF(first_request_at < NOW() - INTERVAL ?i SECOND, NOW(), first_request_at)";
            $db->query($sql, Constants::IP_REQUEST_LOGS_TABLE, $ip, ENV_GUARD_RATE_LIMIT_WINDOW, ENV_GUARD_RATE_LIMIT_WINDOW);
            $count = $db->getOne("SELECT request_count FROM ?n WHERE ip = ?s", Constants::IP_REQUEST_LOGS_TABLE, $ip);
            if ($count > ENV_GUARD_RATE_LIMIT_COUNT) {
                new \classes\system\ErrorLogger('Превышен лимит запросов (DB)', __FUNCTION__, 'botguard', ['ip' => $ip, 'count' => $count]);
                self::addIpToBlacklist($ip, 3600, 'Rate limit exceeded');
                return true;
            }
            return false;
        } catch (\Exception $e) {
            new \classes\system\ErrorLogger('Ошибка DB при Rate Limiting: ' . $e->getMessage(), __FUNCTION__, 'botguard', ['ip' => $ip, 'trace' => $e->getTraceAsString()]);
            return false;
        }
    }

    /**
     * Главный метод-файрвол, выполняющий многоуровневую проверку каждого запроса
     * Блокирует известные угрозы и накапливает "нарушения" (страйки) для автоматической
     * блокировки подозрительных IP-адресов
     * Этот метод является основной точкой входа для системы безопасности и должен
     * вызываться в самом начале выполнения скрипта
     * ВАЖНО: В случае обнаружения угрозы, метод немедленно прерывает выполнение
     * скрипта через exit() с соответствующим HTTP-кодом
     * Логика проверок выстроена в следующем порядке для максимальной эффективности:
     * 1. Проверка чёрного списка IP: Сначала выполняется быстрая проверка, не заблокирован
     * ли IP-адрес уже. Если да, запрос немедленно отклоняетс
     * 2. Проверки, генерирующие "страйки": Если IP не в чёрном списке,
     * выполняется серия проверок (отсутствие User-Agent, Honeypot, плохой бот,
     * Rate Limit, SQLi/XSS).
     * 3. Накопление страйков: Если любая из проверок на шаге 2 провалена, счётчик
     * нарушений для данного IP увеличивается
     * 4. Автоматический бан: Если счётчик нарушений достигает лимита
     * (ENV_GUARD_STRIKE_LIMIT), IP-адрес автоматически добавляется в чёрный список
     * @see self::isIpBlacklisted()
     * @see self::isRateLimited()
     * @see self::incrementOffenseCounter()
     * @return void Метод ничего не возвращает.
     */
    public static function guard() {
        self::initialize();
        $ip = SysClass::getClientIp();
        if (empty($ip)) {
            http_response_code(400);
            exit('Bad Request');
        }
        // --- ШАГ 1: Приоритетная проверка чёрного списка ---
        if (self::ENABLED_CHECKS[self::CHECK_BLACKLISTED_IP] && self::isIpBlacklisted($ip)) {
            new ErrorLogger('Отклонен запрос от уже заблокированного IP', __FUNCTION__, 'botguard_blocked', ['ip' => $ip]);
            http_response_code(403);
            exit('Access Denied');
        }
        // --- ШАГ 2: Проверки, генерирующие "страйки" или мгновенный бан ---
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $rejectionReason = null;
        $rejectionDetails = [];
        $httpCode = 403;
        $immediateBan = false;

        $resVarInspect = self::inspectRequestVariables($inputs = ['GET' => $_GET, 'POST' => $_POST, 'COOKIE' => $_COOKIE]);
        if ($resVarInspect) {
            $rejectionReason = 'Обнаружена потенциальная атака (SQLi/XSS)';
            $rejectionDetails = ['GET' => $_GET, 'POST' => $_POST, 'COOKIE' => $_COOKIE];
            $immediateBan = true;
        } elseif (self::ENABLED_CHECKS[self::CHECK_MISSING_IP_OR_UA] && empty($userAgent)) {
            $rejectionReason = 'Отсутствует User-Agent';
            $httpCode = 400;
            $immediateBan = true;
        } elseif (self::ENABLED_CHECKS[self::CHECK_HONEYPOT] && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['comment'])) {
            $rejectionReason = 'Сработала ловушка-приманка (Honeypot)';
            $rejectionDetails['Details'] = var_export(__REQUEST, true);
            $immediateBan = true;
        } elseif (self::ENABLED_CHECKS[self::CHECK_BAD_BOT] && self::isBadBot($userAgent)) {
            $rejectionReason = 'Обнаружен плохой бот';
            $rejectionDetails['HTTP_USER_AGENT'] = $userAgent;
            $rejectionDetails['Details'] = var_export(__REQUEST, true);
            $immediateBan = true;
        } elseif (self::ENABLED_CHECKS[self::CHECK_RATE_LIMIT] && self::isRateLimited($ip)) {
            $rejectionReason = 'Превышен лимит запросов (Rate Limit)';
            $httpCode = 429;
        }

        // --- ШАГ 3: Принятие решения и накопление страйков ---
        if ($rejectionReason !== null) {
            $rejectionDetails['ip'] = $ip;
            // Логируем общую причину блокировки
            new ErrorLogger($rejectionReason, __FUNCTION__, 'botguard', $rejectionDetails);
            if ($immediateBan) {
                self::addIpToBlacklist($ip, 3600, $rejectionReason);
                new ErrorLogger("IP заблокирован за попытку атаки", __FUNCTION__, 'botguard_ban', ['ip' => $ip]);
            } else {
                $strikeCount = self::incrementOffenseCounter($ip);
                new ErrorLogger("Текущее количество страйков для IP", __FUNCTION__, 'botguard_debug', ['ip' => $ip, 'strikes' => $strikeCount]);
                if ($strikeCount >= ENV_GUARD_STRIKE_LIMIT) {
                    self::addIpToBlacklist($ip, 3600, "Exceeded strike limit ({$strikeCount} offenses)");
                    new ErrorLogger("IP заблокирован после {$strikeCount} нарушений", __FUNCTION__, 'botguard_ban', ['ip' => $ip]);
                }
            }
            http_response_code($httpCode);
            exit($httpCode === 429 ? 'Too Many Requests' : 'Access Denied');
        }
    }

    /**
     * Вспомогательный метод для проверки User-Agent (вынесен из guard).
     * @param string $userAgent
     * @return bool
     */
    private static function isBadBot(string $userAgent): bool {
        $botList = [];
        if (self::$useRedis) {
            try {
                if (self::$redisClient && self::$redisClient->exists('ee_bad_bots_set')) {
                    $botList = self::$redisClient->sMembers('ee_bad_bots_set');
                }
            } catch (\Exception $e) { /* fallback */
            }
        }

        if (empty($botList)) {
            $botList = self::$badBots;
        }

        foreach ($botList as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                return true;
            }
        }
        return false;
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
     * Главный метод проверки IP в чёрном списке.
     * Автоматически выбирает хранилище: Redis или БД.
     * @param string $ip IP-адрес для проверки
     * @return bool
     */
    private static function isIpBlacklisted(string $ip): bool {
        if (self::$useRedis) {
            $res = self::_isBlacklistedRedis($ip);
            SysClass::preFile('debugs', 'isIpBlacklisted', [self::$useRedis, $res], '_isBlacklistedRedis'); // TODO test            
            return $res;
        } else {
            $res = self::_isBlacklistedDb($ip);
            SysClass::preFile('debugs', 'isIpBlacklisted', [self::$useRedis, $res], '_isBlacklistedDb'); // TODO test
            return $res;
        }
    }

    /**
     * Проверяет IP по чёрному списку, кешированному в Redis.
     * @param string $ip
     * @return bool
     */
    private static function _isBlacklistedRedis(string $ip): bool {
        try {
            $redis = (new \classes\system\CacheManager())->getRedisClient();
            if (!$redis) {
                return false;
            }
            // Проверяем наличие маркера. Если его нет - данные в Redis устарели.
            if (!$redis->exists(self::$redisKeyBlacklistMarker)) {
                self::_syncBlacklistToRedis($redis);
            }
            // Получаем весь список из Redis Set и проверяем IP
            $blacklist = $redis->sMembers(self::$redisKeyIpBlacklist);
            foreach ($blacklist as $range) {
                if (self::ipInRange($ip, $range)) {
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            new \classes\system\ErrorLogger('Ошибка Redis при проверке черного списка IP: ' . $e->getMessage(), __FUNCTION__, 'botguard_redis');
            return false; // При сбое Redis лучше пропустить, чем заблокировать
        }
    }

    /**
     * Синхронизирует актуальный чёрный список из БД в Redis.
     * @param \Redis $redis Экземпляр клиента Redis
     */
    private static function _syncBlacklistToRedis(\Redis $redis): void {
        $db = SafeMySQL::gi();
        // Удаляем истёкшие записи в самой БД
        $db->query("DELETE FROM ?n WHERE block_until < NOW()", Constants::IP_BLACKLIST_TABLE);
        // Получаем актуальный список
        $blacklistedIps = $db->getCol("SELECT ip_range FROM ?n", Constants::IP_BLACKLIST_TABLE);
        // Перезаписываем данные в Redis
        $redis->del(self::$redisKeyIpBlacklist); // Удаляем старый набор
        if (!empty($blacklistedIps)) {
            $redis->sAddArray(self::$redisKeyIpBlacklist, $blacklistedIps);
        }
        // Ставим маркер на 5 минут. Это значит, что список будет обновляться из БД не чаще, чем раз в 5 минут.
        $redis->set(self::$redisKeyBlacklistMarker, time(), 300);
    }

    /**
     * Проверяет IP по чёрному списку в Базе Данных
     * @param string $ip
     * @return bool
     */
    private static function _isBlacklistedDb(string $ip): bool {
        try {
            $db = SafeMySQL::gi();
            $db->query("DELETE FROM ?n WHERE block_until < NOW()", Constants::IP_BLACKLIST_TABLE);
            $blacklistedIps = $db->getCol("SELECT ip_range FROM ?n", Constants::IP_BLACKLIST_TABLE);
            if (!empty($blacklistedIps)) {
                $trimmedIp = trim($ip);
                foreach ($blacklistedIps as $blacklistedIp) {
                    if (self::ipInRange($trimmedIp, trim($blacklistedIp))) {
                        new \classes\system\ErrorLogger(
                                'IP-адрес найден в чёрном списке (DB)',
                                __FUNCTION__,
                                'botguard_info',
                                ['ip' => $ip, 'matched_range' => $blacklistedIp]
                        );
                        return true;
                    }
                }
            }
            return false;
        } catch (\Exception $e) {
            new \classes\system\ErrorLogger(
                    'Ошибка DB при проверке черного списка IP: ' . $e->getMessage(),
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
        // 1. Преобразуем IP в число ОДИН РАЗ в начале и проверяем корректность
        $ipAsLong = ip2long($ip);
        if ($ipAsLong === false) {
            return false; // Некорректный IP-адрес
        }
        // 2. Проверка диапазона "начало-конец"
        if (strpos($range, '-') !== false) {
            list($startIp, $endIp) = explode('-', $range, 2);
            $startIp = ip2long(trim($startIp));
            $endIp = ip2long(trim($endIp));
            // Проверяем, что диапазон корректен
            if ($startIp === false || $endIp === false) {
                return false;
            }
            return $ipAsLong >= $startIp && $ipAsLong <= $endIp;
        }
        // 3. Проверка CIDR-диапазона
        if (strpos($range, '/') !== false) {
            list($subnet, $mask) = explode('/', $range, 2);
            $subnet = ip2long(trim($subnet));
            $mask = (int) $mask;
            // Проверяем, что подсеть корректна
            if ($subnet === false || $mask < 0 || $mask > 32) {
                return false;
            }
            $wildcard = (1 << (32 - $mask)) - 1;
            $netmask = ~$wildcard;
            return ($ipAsLong & $netmask) === ($subnet & $netmask);
        }
        // 4. Проверка на точное совпадение
        // TODO test
        new \classes\system\ErrorLogger(
                'Результат сравнения',
                __FUNCTION__,
                'botguard_info',
                ['ip_range' => $range, 'ip' => $ip, 'reason' => $ip === $range]
        );
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
     * Добавляет IP-адрес или диапазон в чёрный список
     * @param string $ipRange IP-адрес или диапазон
     * @param int $blockDuration Время блокировки в секундах
     * @param string|null $reason Причина блокировки
     * @return bool
     */
    public static function addIpToBlacklist(string $ipRange, int $blockDuration = 86400, ?string $reason = null): bool {
        try {
            $db = SafeMySQL::gi();
            // Проверяем, существует ли активная запись для этого IP
            $sqlCheck = "SELECT COUNT(*) FROM ?n WHERE ip_range = ?s AND block_until > NOW()";
            $exists = $db->getOne($sqlCheck, Constants::IP_BLACKLIST_TABLE, $ipRange);
            if ($exists) {
                return true; // IP уже заблокирован
            }
            // Используем NOW() и INTERVAL из MySQL вместо date() из PHP
            $sqlInsert = "INSERT INTO ?n (ip_range, block_until, reason) VALUES (?s, NOW() + INTERVAL ?i SECOND, ?s)";
            $resDb = $db->query($sqlInsert, Constants::IP_BLACKLIST_TABLE, $ipRange, $blockDuration, $reason);
            // Для логгирования нам всё ещё нужно получить отформатированный запрос
            $parsedQueryForLog = $db->parse($sqlInsert, Constants::IP_BLACKLIST_TABLE, $ipRange, $blockDuration, $reason);
            SysClass::preFile('addIpToBlacklist', 'addIpToBlacklist', $parsedQueryForLog, $resDb);

            if (self::$useRedis && self::$redisClient) {
                self::$redisClient->sAdd(self::$redisKeyIpBlacklist, $ipRange);
            }
            new \classes\system\ErrorLogger(
                    'IP успешно добавлен в чёрный список',
                    __FUNCTION__,
                    'botguard_info',
                    ['ip_range' => $ipRange, 'block_duration_sec' => $blockDuration, 'reason' => $reason]
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

    /**
     * Скачивает и обновляет локальный JSON-файл со списком плохих ботов
     * Этот метод предназначен для вызова по расписанию (cron) раз в сутки
     * @return array Массив с результатом операции
     */
    public static function updateBadBotList(): array {
        $sourceUrl = 'https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/_generator_lists/bad-user-agents.list';
        $destinationFile = ENV_TMP_PATH . 'bad_bots.json';
        $options = ['http' => ['method' => 'GET', 'header' => 'User-Agent: BotGuard-Updater/1.0']];
        $context = stream_context_create($options);
        $content = @file_get_contents($sourceUrl, false, $context);
        if ($content === false) {
            $message = 'Не удалось скачать список ботов с GitHub.';
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'botguard_update');
            return ['status' => 'error', 'message' => $message];
        }
        $lines = explode("\n", $content);
        $badBotsArray = [];
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (!empty($trimmedLine) && $trimmedLine[0] !== '#') {
                $badBotsArray[] = $trimmedLine;
            }
        }
        if (empty($badBotsArray)) {
            $message = 'Не удалось обработать список, получен пустой массив.';
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'botguard_update');
            return ['status' => 'error', 'message' => $message];
        }
        file_put_contents($destinationFile, json_encode(array_values($badBotsArray), JSON_PRETTY_PRINT));
        return ['status' => 'success', 'count' => count($badBotsArray)];
    }

    /**
     * Увеличивает счётчик нарушений для IP и возвращает текущее количество.
     * @param string $ip
     * @return int
     */
    private static function incrementOffenseCounter(string $ip): int {
        if (self::$useRedis) {
            return self::_incrementOffenseRedis($ip);
        } else {
            return self::_incrementOffenseDb($ip);
        }
    }

    /**
     * Увеличивает счётчик нарушений в Redis.
     * @param string $ip
     * @return int
     */
    private static function _incrementOffenseRedis(string $ip): int {
        try {
            $key = 'offense_count:' . $ip;
            $count = self::$redisClient->incr($key);
            // При первом нарушении устанавливаем время жизни счетчика
            if ($count === 1) {
                self::$redisClient->expire($key, ENV_GUARD_STRIKE_TTL);
            }
            return $count;
        } catch (\Exception $e) {
            return 1; // В случае сбоя не эскалируем
        }
    }

    /**
     * Увеличивает счётчик нарушений в БД.
     * @param string $ip
     * @return int
     */
    private static function _incrementOffenseDb(string $ip): int {
        try {
            $db = SafeMySQL::gi();
            // Сбрасываем счетчик, если последняя попытка была давно
            $sql = "INSERT INTO ?n (ip, last_offense_at) VALUES(?s, NOW())
                    ON DUPLICATE KEY UPDATE 
                    strike_count = IF(last_offense_at < NOW() - INTERVAL ?i SECOND, 1, strike_count + 1),
                    last_offense_at = NOW()";
            $db->query($sql, Constants::IP_OFFENSES_TABLE, $ip, ENV_GUARD_STRIKE_TTL);

            return (int) $db->getOne("SELECT strike_count FROM ?n WHERE ip = ?s", Constants::IP_OFFENSES_TABLE, $ip);
        } catch (\Exception $e) {
            return 1; // В случае сбоя не эскалируем
        }
    }
}
