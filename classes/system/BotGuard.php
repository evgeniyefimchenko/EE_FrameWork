<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

/**
 * Request guard against basic abusive traffic patterns.
 */
class BotGuard {

    private static bool $useRedis = false;
    private static ?\Redis $redisClient = null;
    private static array $badBots = [];
    private static bool $isInitialized = false;
    private static int $lastBlacklistCleanupTs = 0;

    private const BAD_BOTS_FILE_NAME = 'bad_bots.json';
    private const BAD_BOTS_RESTORE_MARKER = '.bad_bots_restore.marker';
    private const BAD_BOTS_WARNING_MARKER = '.bad_bots_warning.marker';
    private const BAD_BOTS_RESTORE_TTL = 1800;
    private const BAD_BOTS_WARNING_TTL = 21600;
    private const BLACKLIST_CLEANUP_INTERVAL = 60;
    private const BAD_BOTS_HTTP_TIMEOUT = 10;

    private const REDIS_BAD_BOTS_SET_KEY = 'ee_bad_bots_set';
    private const REDIS_BAD_BOTS_MARKER = 'ee_bad_bots_loaded_marker';

    private const DEFAULT_BAD_BOTS_SOURCE_URLS = [
        'https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/_generator_lists/bad-user-agents.list',
        'https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/main/_generator_lists/bad-user-agents.list',
    ];

    private const FALLBACK_BAD_BOTS = [
        'sqlmap',
        'nikto',
        'acunetix',
        'nessus',
        'masscan',
        'zgrab',
        'wpscan',
        'dirbuster',
    ];

    private const CHECK_MISSING_IP_OR_UA = 'missing_ip_or_ua';
    private const CHECK_BAD_BOT = 'bad_bot';
    private const CHECK_SQL_INJECTION = 'sql_injection';
    private const CHECK_XSS = 'xss';
    private const CHECK_BLACKLISTED_IP = 'blacklisted_ip';
    private const CHECK_RATE_LIMIT = 'rate_limit';
    private const CHECK_HONEYPOT = 'honeypot';

    private const HONEYPOT_FIELDS = [
        'website',
        'homepage',
        'url',
        'middle_name',
        'fax_number',
        'hp_field',
    ];

    private const ENABLED_CHECKS = [
        self::CHECK_MISSING_IP_OR_UA => true,
        self::CHECK_BAD_BOT => true,
        self::CHECK_SQL_INJECTION => true,
        self::CHECK_XSS => true,
        self::CHECK_BLACKLISTED_IP => true,
        self::CHECK_RATE_LIMIT => true,
        self::CHECK_HONEYPOT => true,
    ];

    private static string $redisKeyIpBlacklist = 'ee_ip_blacklist_set';
    private static string $redisKeyBlacklistMarker = 'ee_blacklist_loaded_marker';

    private static function logBotGuard(string $level, string $channel, string $message, array $context = [], ?string $initiator = null, bool $includeTrace = true): void {
        Logger::log($level, $channel, $message, $context, [
            'initiator' => $initiator ?: __METHOD__,
            'details' => $message,
            'include_trace' => $includeTrace,
        ]);
    }

    /**
     * Initializes guard state once per process.
     */
    private static function initialize(): void {
        if (self::$isInitialized) {
            return;
        }

        self::connectRedis();
        self::ensureTmpPathExists();

        $localFile = self::getBadBotsFilePath();
        $botsFromFile = self::loadBadBotsFromFile($localFile);

        if (empty($botsFromFile) && self::canAttemptBadBotsRestore()) {
            self::markBadBotsRestoreAttempt();
            $updateResult = self::updateBadBotList();
            if (($updateResult['status'] ?? '') === 'success') {
                $botsFromFile = self::loadBadBotsFromFile($localFile);
            }
        }

        if (empty($botsFromFile)) {
            self::logMissingBadBotsWarning($localFile);
            $botsFromFile = self::FALLBACK_BAD_BOTS;
        }

        self::$badBots = self::normalizeBotList($botsFromFile);
        if (empty(self::$badBots)) {
            self::$badBots = self::normalizeBotList(self::FALLBACK_BAD_BOTS);
        }

        if (self::$useRedis) {
            self::loadBadBotsIntoRedis(self::$badBots);
        }

        self::$isInitialized = true;
    }

    /**
     * Connects to Redis if enabled.
     */
    private static function connectRedis(): void {
        self::$useRedis = false;
        self::$redisClient = null;

        if (!ENV_GUARD_REDIS || !class_exists('\Redis')) {
            return;
        }

        try {
            $redis = new \Redis();
            if ($redis->connect(ENV_REDIS_ADDRESS, ENV_REDIS_PORT)) {
                self::$redisClient = $redis;
                self::$useRedis = true;
            }
        } catch (\Throwable $e) {
            self::logBotGuard(Logger::LEVEL_WARNING, 'botguard_redis', 'BotGuard Redis connection failed: ' . $e->getMessage(), [], __FUNCTION__, false);
            self::$useRedis = false;
            self::$redisClient = null;
        }
    }

    /**
     * Returns active Redis client or null.
     */
    private static function getRedisClient(): ?\Redis {
        if (!self::$useRedis || !(self::$redisClient instanceof \Redis)) {
            return null;
        }
        return self::$redisClient;
    }

    /**
     * Loads bot signatures into Redis if marker expired.
     *
     * @param array<int, string> $bots
     */
    private static function loadBadBotsIntoRedis(array $bots): void {
        $redis = self::getRedisClient();
        if (!$redis) {
            return;
        }

        try {
            if (!$redis->exists(self::REDIS_BAD_BOTS_MARKER)) {
                $redis->del(self::REDIS_BAD_BOTS_SET_KEY);
                foreach ($bots as $botName) {
                    $botName = trim((string) $botName);
                    if ($botName !== '') {
                        $redis->sAdd(self::REDIS_BAD_BOTS_SET_KEY, $botName);
                    }
                }
                $redis->setEx(self::REDIS_BAD_BOTS_MARKER, 3600, (string) time());
            }
        } catch (\Throwable $e) {
            self::logBotGuard(Logger::LEVEL_WARNING, 'botguard_redis', 'BotGuard Redis bad-bots sync failed: ' . $e->getMessage(), [], __FUNCTION__, false);
        }
    }

    /**
     * Returns bad bots local JSON file path.
     */
    private static function getBadBotsFilePath(): string {
        return rtrim((string) ENV_TMP_PATH, '/\\') . DIRECTORY_SEPARATOR . self::BAD_BOTS_FILE_NAME;
    }

    /**
     * Ensures tmp path exists.
     */
    private static function ensureTmpPathExists(): bool {
        $tmpPath = rtrim((string) ENV_TMP_PATH, '/\\') . DIRECTORY_SEPARATOR;
        if ($tmpPath === DIRECTORY_SEPARATOR) {
            return false;
        }
        if (is_dir($tmpPath)) {
            return true;
        }
        return @mkdir($tmpPath, 0755, true) || is_dir($tmpPath);
    }

    /**
     * Loads bad bots from JSON file.
     *
     * @return array<int, string>
     */
    private static function loadBadBotsFromFile(string $filePath): array {
        if ($filePath === '' || !is_file($filePath) || !is_readable($filePath)) {
            return [];
        }

        $json = @file_get_contents($filePath);
        if (!is_string($json) || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return self::normalizeBotList($decoded);
    }

    /**
     * Normalizes list of bot signatures.
     *
     * @param array<int|string, mixed> $bots
     * @return array<int, string>
     */
    private static function normalizeBotList(array $bots): array {
        $normalized = [];
        foreach ($bots as $item) {
            $botName = strtolower(trim((string) $item));
            if ($botName === '') {
                continue;
            }
            if (strlen($botName) < 2 || strlen($botName) > 255) {
                continue;
            }
            if ($botName[0] === '#') {
                continue;
            }
            if (!preg_match('/[a-z0-9]/i', $botName)) {
                continue;
            }
            $normalized[] = $botName;
        }
        return array_values(array_unique($normalized));
    }

    /**
     * Decides whether restore attempt is allowed by marker TTL.
     */
    private static function canAttemptBadBotsRestore(): bool {
        $markerPath = rtrim((string) ENV_TMP_PATH, '/\\') . DIRECTORY_SEPARATOR . self::BAD_BOTS_RESTORE_MARKER;
        if (!is_file($markerPath)) {
            return true;
        }

        $mtime = @filemtime($markerPath);
        if ($mtime === false) {
            return true;
        }

        return (time() - $mtime) >= self::BAD_BOTS_RESTORE_TTL;
    }

    /**
     * Stores restore-attempt marker.
     */
    private static function markBadBotsRestoreAttempt(): void {
        $markerPath = rtrim((string) ENV_TMP_PATH, '/\\') . DIRECTORY_SEPARATOR . self::BAD_BOTS_RESTORE_MARKER;
        @file_put_contents($markerPath, (string) time(), LOCK_EX);
    }

    /**
     * Logs warning about missing local bad bots file with throttling.
     */
    private static function logMissingBadBotsWarning(string $filePath): void {
        $markerPath = rtrim((string) ENV_TMP_PATH, '/\\') . DIRECTORY_SEPARATOR . self::BAD_BOTS_WARNING_MARKER;
        $shouldLog = true;

        if (is_file($markerPath)) {
            $mtime = @filemtime($markerPath);
            if ($mtime !== false && (time() - $mtime) < self::BAD_BOTS_WARNING_TTL) {
                $shouldLog = false;
            }
        }

        if ($shouldLog) {
            self::logBotGuard(Logger::LEVEL_WARNING, 'botguard_info', 'Local bad_bots.json is missing or invalid. Built-in fallback list is used.', ['path' => $filePath], __FUNCTION__, false);
            @file_put_contents($markerPath, (string) time(), LOCK_EX);
        }
    }

    /**
     * Resolves client IP for the guard, falling back to loopback/private
     * addresses when public IP detection is not available.
     */
    private static function resolveClientIp(): string {
        $ip = SysClass::getClientIp();
        if ($ip !== '' && $ip !== 'unknown') {
            return $ip;
        }

        $fallbackSources = [
            (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
            (string) ($_SERVER['HTTP_CLIENT_IP'] ?? ''),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ];

        foreach ($fallbackSources as $source) {
            if ($source === '') {
                continue;
            }

            foreach (array_map('trim', explode(',', $source)) as $candidate) {
                if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        return '';
    }

    /**
     * Main guard entry point.
     */
    public static function guard(): void {
        self::initialize();

        $ip = self::resolveClientIp();
        if ($ip === '') {
            http_response_code(400);
            exit('Bad Request');
        }

        if (self::ENABLED_CHECKS[self::CHECK_BLACKLISTED_IP] && self::isIpBlacklisted($ip)) {
            self::logBotGuard(Logger::LEVEL_WARNING, 'botguard_blocked', 'Request denied: blacklisted IP', ['ip' => $ip], __FUNCTION__, false);
            http_response_code(403);
            exit('Access Denied');
        }

        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $httpCode = 403;
        $rejectionReason = null;
        $rejectionDetails = [
            'ip' => $ip,
            'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
        ];
        $immediateBan = false;
        $countStrike = true;

        $inputs = self::collectRequestInputs();
        if (self::inspectRequestVariables($inputs)) {
            $rejectionReason = 'Potential SQLi/XSS payload detected';
            $rejectionDetails['signal'] = 'request_variables';
            $immediateBan = true;
        } elseif (self::ENABLED_CHECKS[self::CHECK_MISSING_IP_OR_UA] && trim($userAgent) === '') {
            $rejectionReason = 'Missing User-Agent';
            $httpCode = 400;
            $countStrike = false;
        } else {
            $honeypotFields = self::getTriggeredHoneypotFields();
            if (self::ENABLED_CHECKS[self::CHECK_HONEYPOT] && !empty($honeypotFields)) {
                $rejectionReason = 'Honeypot field triggered';
                $rejectionDetails['honeypot_fields'] = $honeypotFields;
                $immediateBan = true;
            } elseif (self::ENABLED_CHECKS[self::CHECK_BAD_BOT] && self::isBadBot($userAgent)) {
                $rejectionReason = 'Bad bot signature detected';
                $rejectionDetails['user_agent'] = self::truncateForLog($userAgent);
                $immediateBan = true;
            } elseif (
                self::ENABLED_CHECKS[self::CHECK_RATE_LIMIT]
                && !self::shouldSkipRateLimitForCurrentRequest()
                && self::isRateLimited($ip)
            ) {
                $rejectionReason = 'Rate limit exceeded';
                $httpCode = 429;
            }
        }

        if ($rejectionReason === null) {
            return;
        }

        self::logBotGuard(Logger::LEVEL_WARNING, 'botguard', $rejectionReason, self::summarizeValueForLog($rejectionDetails), __FUNCTION__, false);

        if ($immediateBan) {
            self::addIpToBlacklist($ip, 3600, $rejectionReason);
        } elseif ($countStrike) {
            $strikeCount = self::incrementOffenseCounter($ip);
            if ($strikeCount >= ENV_GUARD_STRIKE_LIMIT) {
                self::addIpToBlacklist($ip, 3600, "Exceeded strike limit ({$strikeCount} offenses)");
            }
        }

        http_response_code($httpCode);
        exit($httpCode === 429 ? 'Too Many Requests' : 'Access Denied');
    }

    /**
     * Checks if current request is authenticated admin area request.
     */
    private static function shouldSkipRateLimitForCurrentRequest(): bool {
        if (!self::isAdminAreaRequest()) {
            return false;
        }

        try {
            $userId = SysClass::getCurrentUserId();
            return is_numeric($userId) && (int) $userId > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Checks if request path belongs to /admin.
     */
    private static function isAdminAreaRequest(): bool {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($uri === '') {
            return false;
        }

        $path = (string) parse_url($uri, PHP_URL_PATH);
        if ($path === '') {
            return false;
        }

        $path = '/' . ltrim($path, '/');
        return $path === '/admin' || str_starts_with($path, '/admin/');
    }

    /**
     * Collects all input sources used for suspicious payload inspection.
     *
     * @return array<string, mixed>
     */
    private static function collectRequestInputs(): array {
        $inputs = [
            'GET' => is_array($_GET) ? $_GET : [],
            'POST' => is_array($_POST) ? $_POST : [],
            'COOKIE' => is_array($_COOKIE) ? $_COOKIE : [],
        ];

        if (defined('__REQUEST') && is_array(__REQUEST)) {
            if (isset(__REQUEST['_REQUEST']) && is_array(__REQUEST['_REQUEST'])) {
                $inputs['REQUEST'] = __REQUEST['_REQUEST'];
            }
            if (isset(__REQUEST['_GET']) && is_array(__REQUEST['_GET'])) {
                $inputs['GET'] = __REQUEST['_GET'];
            }
            if (isset(__REQUEST['_POST']) && is_array(__REQUEST['_POST'])) {
                $inputs['POST'] = __REQUEST['_POST'];
            }
        }

        $rawBody = self::shouldInspectRawBody() ? self::extractRawBodyFromRequest() : '';
        if ($rawBody !== '') {
            $inputs['RAW_BODY'] = $rawBody;

            $jsonBody = json_decode($rawBody, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonBody)) {
                $inputs['JSON_BODY'] = $jsonBody;
            } elseif (str_contains($rawBody, '=')) {
                $formBody = [];
                parse_str($rawBody, $formBody);
                if (!empty($formBody)) {
                    $inputs['FORM_BODY'] = $formBody;
                }
            }
        }

        return self::sanitizeTrustedRequestInputs($inputs);
    }

    /**
     * Raw request body inspection is skipped for multipart uploads because
     * binary payloads and MIME boundaries produce many false positives.
     */
    private static function shouldInspectRawBody(): bool {
        $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '')));
        if ($contentType === '') {
            return true;
        }

        return !str_starts_with($contentType, 'multipart/form-data');
    }

    /**
     * Исключает из сигнатурного анализа служебные JSON-поля,
     * которые формируются самой админкой и могут содержать любые пользовательские данные.
     *
     * @param array<string, mixed> $inputs
     * @return array<string, mixed>
     */
    private static function sanitizeTrustedRequestInputs(array $inputs): array {
        if (!self::isPropertyDefinitionsImportConfirmRequest()) {
            return $inputs;
        }

        foreach (['POST', 'REQUEST', 'FORM_BODY', 'JSON_BODY'] as $source) {
            if (!isset($inputs[$source]) || !is_array($inputs[$source])) {
                continue;
            }

            if (array_key_exists('property_definition_editor_state', $inputs[$source])) {
                $inputs[$source]['property_definition_editor_state'] = '[admin_property_definitions_editor_state]';
            }
        }

        if (isset($inputs['RAW_BODY'])) {
            $inputs['RAW_BODY'] = '[admin_property_definitions_submit]';
        }

        return $inputs;
    }

    /**
     * Проверяет, что отправляется подтверждение импорта определений свойств из админки.
     */
    private static function isPropertyDefinitionsImportConfirmRequest(): bool {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'POST') {
            return false;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $path = rtrim('/' . ltrim($path, '/'), '/');
        if ($path !== '/admin/import_property_definitions') {
            return false;
        }

        $postData = is_array($_POST) ? $_POST : [];
        if (empty($postData) && defined('__REQUEST') && is_array(__REQUEST) && isset(__REQUEST['_POST']) && is_array(__REQUEST['_POST'])) {
            $postData = __REQUEST['_POST'];
        }

        $action = strtolower(trim((string) ($postData['property_definitions_action'] ?? '')));
        if ($action !== 'confirm_import') {
            return false;
        }

        return array_key_exists('property_definition_editor_state', $postData);
    }

    /**
     * Gets raw request body from global constant or php input stream.
     */
    private static function extractRawBodyFromRequest(): string {
        if (defined('__REQUEST') && is_array(__REQUEST) && isset(__REQUEST['input_data'])) {
            return (string) __REQUEST['input_data'];
        }

        $rawBody = @file_get_contents('php://input');
        return is_string($rawBody) ? $rawBody : '';
    }

    /**
     * Inspects request values recursively for suspicious SQLi/XSS markers.
     *
     * @param array<string, mixed> $inputs
     */
    private static function inspectRequestVariables(array $inputs = []): bool {
        foreach ($inputs as $source => $value) {
            if (self::containsSuspiciousData($source, true)) {
                return true;
            }
            if (self::containsSuspiciousData($value, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursive suspicious-data checker.
     */
    private static function containsSuspiciousData(mixed $value, bool $checkKeys = false): bool {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if ($checkKeys && is_string($key) && self::containsSuspiciousData($key, false)) {
                    return true;
                }
                if (self::containsSuspiciousData($item, true)) {
                    return true;
                }
            }
            return false;
        }

        if (is_object($value) || $value === null) {
            return false;
        }

        if (!is_scalar($value)) {
            return false;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return false;
        }

        if (self::ENABLED_CHECKS[self::CHECK_SQL_INJECTION] && self::containsSqlInjection($text)) {
            return true;
        }
        if (self::ENABLED_CHECKS[self::CHECK_XSS] && self::containsXss($text)) {
            return true;
        }

        return false;
    }

    /**
     * Returns honeypot fields filled by client.
     *
     * @return array<int, string>
     */
    private static function getTriggeredHoneypotFields(): array {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'POST') {
            return [];
        }

        $triggered = [];
        foreach (self::HONEYPOT_FIELDS as $field) {
            if (!array_key_exists($field, $_POST)) {
                continue;
            }
            $value = trim((string) $_POST[$field]);
            if ($value !== '') {
                $triggered[] = $field;
            }
        }

        return $triggered;
    }

    /**
     * Checks if current user-agent matches bad bot signatures.
     */
    private static function isBadBot(string $userAgent): bool {
        $ua = strtolower(trim($userAgent));
        if ($ua === '') {
            return false;
        }

        $botList = self::$badBots;
        $redis = self::getRedisClient();
        if ($redis) {
            try {
                if ($redis->exists(self::REDIS_BAD_BOTS_SET_KEY)) {
                    $redisList = $redis->sMembers(self::REDIS_BAD_BOTS_SET_KEY);
                    if (is_array($redisList) && !empty($redisList)) {
                        $botList = $redisList;
                    }
                }
            } catch (\Throwable $e) {
                // keep local fallback list
            }
        }

        foreach ($botList as $bot) {
            $signature = strtolower(trim((string) $bot));
            if ($signature !== '' && str_contains($ua, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * SQLi signature detector.
     */
    private static function containsSqlInjection(string $input): bool {
        $sqlPatterns = [
            '/\bunion(?:\s+all)?\s+select\b/i',
            '/\b(?:or|and)\b\s+[\(\s]*\d+\s*=\s*\d+/i',
            '/\b(?:sleep|benchmark)\s*\(/i',
            '/\bwaitfor\b\s+\bdelay\b/i',
            '/\b(?:drop|truncate|alter)\s+table\b/i',
            '/\binformation_schema\b/i',
            '/\bload_file\s*\(/i',
            '/(?:--|#|\/\*)/i',
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * XSS signature detector.
     */
    private static function containsXss(string $input): bool {
        $xssPatterns = [
            '/<\s*script\b/i',
            '/javascript\s*:/i',
            '/\bon\w+\s*=/i',
            '/<\s*iframe\b/i',
            '/<\s*svg\b/i',
            '/\b(?:document\.cookie|window\.location)\b/i',
            '/\b(?:eval|alert|prompt|confirm)\s*\(/i',
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks request rate-limit backend.
     */
    private static function isRateLimited(string $ip): bool {
        if (self::$useRedis && self::getRedisClient()) {
            return self::_rateLimitRedis($ip);
        }
        return self::_rateLimitDb($ip);
    }

    /**
     * Redis rate limiter.
     */
    private static function _rateLimitRedis(string $ip): bool {
        try {
            $redis = self::getRedisClient();
            if (!$redis) {
                return false;
            }

            $window = (int) ENV_GUARD_RATE_LIMIT_WINDOW;
            $limit = (int) ENV_GUARD_RATE_LIMIT_COUNT;
            if ($window <= 0 || $limit <= 0) {
                return false;
            }

            $key = 'ee_rate_limit:' . sha1($ip);
            $count = (int) $redis->incr($key);
            if ($count === 1) {
                $redis->expire($key, $window);
            }

            return $count > $limit;
        } catch (\Throwable $e) {
            self::logBotGuard(Logger::LEVEL_WARNING, 'botguard_redis', 'Redis rate-limit check failed: ' . $e->getMessage(), [], __FUNCTION__, false);
            return false;
        }
    }

    /**
     * DB rate limiter.
     */
    private static function _rateLimitDb(string $ip): bool {
        try {
            $window = (int) ENV_GUARD_RATE_LIMIT_WINDOW;
            $limit = (int) ENV_GUARD_RATE_LIMIT_COUNT;
            if ($window <= 0 || $limit <= 0) {
                return false;
            }

            $db = SafeMySQL::gi();
            $sql = "INSERT INTO ?n (ip, first_request_at) VALUES(?s, NOW())
                    ON DUPLICATE KEY UPDATE
                    request_count = IF(first_request_at < NOW() - INTERVAL ?i SECOND, 1, request_count + 1),
                    first_request_at = IF(first_request_at < NOW() - INTERVAL ?i SECOND, NOW(), first_request_at)";

            $db->query($sql, Constants::IP_REQUEST_LOGS_TABLE, $ip, $window, $window);

            $count = (int) $db->getOne(
                            "SELECT request_count FROM ?n WHERE ip = ?s",
                            Constants::IP_REQUEST_LOGS_TABLE,
                            $ip
            );

            return $count > $limit;
        } catch (\Throwable $e) {
            self::logBotGuard(Logger::LEVEL_ERROR, 'botguard', 'DB rate-limit check failed: ' . $e->getMessage(), ['ip' => $ip], __FUNCTION__);
            return false;
        }
    }

    /**
     * Checks blacklist via Redis/DB.
     */
    private static function isIpBlacklisted(string $ip): bool {
        if (self::$useRedis && self::getRedisClient()) {
            return self::_isBlacklistedRedis($ip);
        }
        return self::_isBlacklistedDb($ip);
    }

    /**
     * Redis blacklist check.
     */
    private static function _isBlacklistedRedis(string $ip): bool {
        try {
            $redis = self::getRedisClient();
            if (!$redis) {
                return self::_isBlacklistedDb($ip);
            }

            if (!$redis->exists(self::$redisKeyBlacklistMarker)) {
                self::_syncBlacklistToRedis($redis);
            }

            if ($redis->sIsMember(self::$redisKeyIpBlacklist, $ip)) {
                return true;
            }

            $blacklist = $redis->sMembers(self::$redisKeyIpBlacklist);
            if (!is_array($blacklist) || empty($blacklist)) {
                return false;
            }

            foreach ($blacklist as $range) {
                $range = trim((string) $range);
                if ($range === '' || $range === $ip) {
                    continue;
                }
                if ((str_contains($range, '-') || str_contains($range, '/')) && self::ipInRange($ip, $range)) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            self::logBotGuard(Logger::LEVEL_WARNING, 'botguard_redis', 'Redis blacklist check failed: ' . $e->getMessage(), [], __FUNCTION__, false);
            return self::_isBlacklistedDb($ip);
        }
    }

    /**
     * Refreshes Redis blacklist snapshot from DB.
     */
    private static function _syncBlacklistToRedis(\Redis $redis): void {
        try {
            $db = SafeMySQL::gi();
            self::cleanupExpiredBlacklistEntries($db);
            $blacklistedIps = $db->getCol(
                    "SELECT ip_range FROM ?n WHERE block_until > NOW()",
                    Constants::IP_BLACKLIST_TABLE
            );

            $redis->del(self::$redisKeyIpBlacklist);
            if (is_array($blacklistedIps)) {
                foreach ($blacklistedIps as $range) {
                    $range = trim((string) $range);
                    if ($range !== '') {
                        $redis->sAdd(self::$redisKeyIpBlacklist, $range);
                    }
                }
            }

            $redis->setEx(self::$redisKeyBlacklistMarker, 300, (string) time());
        } catch (\Throwable $e) {
            self::logBotGuard(Logger::LEVEL_WARNING, 'botguard_redis', 'Redis blacklist sync failed: ' . $e->getMessage(), [], __FUNCTION__, false);
        }
    }

    /**
     * DB blacklist check.
     */
    private static function _isBlacklistedDb(string $ip): bool {
        try {
            $db = SafeMySQL::gi();
            self::cleanupExpiredBlacklistEntries($db);

            $exactMatch = (int) $db->getOne(
                            "SELECT COUNT(*) FROM ?n WHERE ip_range = ?s AND block_until > NOW()",
                            Constants::IP_BLACKLIST_TABLE,
                            $ip
            );
            if ($exactMatch > 0) {
                return true;
            }

            $ranges = $db->getCol(
                    "SELECT ip_range FROM ?n
                     WHERE block_until > NOW()
                     AND (ip_range LIKE '%-%' OR ip_range LIKE '%/%')",
                    Constants::IP_BLACKLIST_TABLE
            );

            if (!is_array($ranges) || empty($ranges)) {
                return false;
            }

            foreach ($ranges as $range) {
                if (self::ipInRange($ip, trim((string) $range))) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            self::logBotGuard(Logger::LEVEL_ERROR, 'botguard', 'DB blacklist check failed: ' . $e->getMessage(), ['ip' => $ip], __FUNCTION__);
            return false;
        }
    }

    /**
     * Cleans expired blacklist rows with throttling.
     */
    private static function cleanupExpiredBlacklistEntries(SafeMySQL $db): void {
        $now = time();
        if (($now - self::$lastBlacklistCleanupTs) < self::BLACKLIST_CLEANUP_INTERVAL) {
            return;
        }

        $db->query("DELETE FROM ?n WHERE block_until < NOW()", Constants::IP_BLACKLIST_TABLE);
        self::$lastBlacklistCleanupTs = $now;
    }

    /**
     * Checks whether IP belongs to range.
     * Supports exact IP, start-end range and CIDR (IPv4/IPv6).
     */
    private static function ipInRange(string $ip, string $range): bool {
        $ip = trim($ip);
        $range = trim($range);
        if ($ip === '' || $range === '') {
            return false;
        }

        if (filter_var($range, FILTER_VALIDATE_IP)) {
            $cmp = self::compareIpsBinary($ip, $range);
            return $cmp !== null && $cmp === 0;
        }

        if (str_contains($range, '-')) {
            [$startIp, $endIp] = array_map('trim', explode('-', $range, 2));
            $boundsCmp = self::compareIpsBinary($startIp, $endIp);
            if ($boundsCmp === null || $boundsCmp > 0) {
                return false;
            }
            $cmpStart = self::compareIpsBinary($ip, $startIp);
            $cmpEnd = self::compareIpsBinary($ip, $endIp);
            return $cmpStart !== null && $cmpEnd !== null && $cmpStart >= 0 && $cmpEnd <= 0;
        }

        if (str_contains($range, '/')) {
            return self::ipInCidr($ip, $range);
        }

        return false;
    }

    /**
     * Validates single IP, range or CIDR.
     */
    private static function isValidIpRange(string $ipRange): bool {
        $ipRange = trim($ipRange);
        if ($ipRange === '') {
            return false;
        }

        if (filter_var($ipRange, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (str_contains($ipRange, '-')) {
            [$startIp, $endIp] = array_map('trim', explode('-', $ipRange, 2));
            if (!filter_var($startIp, FILTER_VALIDATE_IP) || !filter_var($endIp, FILTER_VALIDATE_IP)) {
                return false;
            }
            $cmp = self::compareIpsBinary($startIp, $endIp);
            return $cmp !== null && $cmp <= 0;
        }

        if (str_contains($ipRange, '/')) {
            [$subnet, $mask] = array_map('trim', explode('/', $ipRange, 2));
            if (!filter_var($subnet, FILTER_VALIDATE_IP) || !is_numeric($mask)) {
                return false;
            }
            $maxBits = str_contains($subnet, ':') ? 128 : 32;
            $maskInt = (int) $mask;
            return $maskInt >= 0 && $maskInt <= $maxBits;
        }

        return false;
    }

    /**
     * Compares two IPs in binary representation.
     * Returns null when IP families differ or values are invalid.
     */
    private static function compareIpsBinary(string $left, string $right): ?int {
        $leftBin = self::ipToBinary($left);
        $rightBin = self::ipToBinary($right);

        if ($leftBin === null || $rightBin === null || strlen($leftBin) !== strlen($rightBin)) {
            return null;
        }

        return strcmp($leftBin, $rightBin);
    }

    /**
     * Checks whether IP belongs to CIDR block.
     */
    private static function ipInCidr(string $ip, string $cidr): bool {
        [$subnet, $mask] = array_map('trim', explode('/', $cidr, 2));
        $ipBin = self::ipToBinary($ip);
        $subnetBin = self::ipToBinary($subnet);

        if ($ipBin === null || $subnetBin === null || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maxBits = strlen($ipBin) * 8;
        if (!is_numeric($mask)) {
            return false;
        }

        $maskInt = (int) $mask;
        if ($maskInt < 0 || $maskInt > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($maskInt, 8);
        $restBits = $maskInt % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($restBits === 0) {
            return true;
        }

        $maskByte = (0xFF << (8 - $restBits)) & 0xFF;
        $ipByte = ord($ipBin[$fullBytes]);
        $subnetByte = ord($subnetBin[$fullBytes]);

        return ($ipByte & $maskByte) === ($subnetByte & $maskByte);
    }

    /**
     * Converts IP to packed binary string.
     */
    private static function ipToBinary(string $ip): ?string {
        $packed = @inet_pton($ip);
        return $packed === false ? null : $packed;
    }

    /**
     * Downloads and parses bad bots list from API or plain-text source URL.
     *
     * @return array<int, string>
     */
    public static function getBadBotsFromAPI(string $apiUrl): array {
        $content = self::fetchBadBotsFromUrl($apiUrl);
        if ($content === null) {
            return [];
        }

        return self::parseBadBotsContent($content);
    }

    /**
     * Fetches remote text content with timeout and status-code guard.
     */
    private static function fetchBadBotsFromUrl(string $url): ?string {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $options = [
            'http' => [
                'method' => 'GET',
                'timeout' => self::BAD_BOTS_HTTP_TIMEOUT,
                'ignore_errors' => true,
                'header' => "User-Agent: BotGuard-Updater/2.0\r\nAccept: application/json,text/plain,*/*\r\n",
            ],
        ];

        $context = stream_context_create($options);
        $content = @file_get_contents($url, false, $context);
        if (is_string($content) && trim($content) !== '') {
            $statusCode = 0;
            $headers = function_exists('http_get_last_response_headers')
                ? http_get_last_response_headers()
                : [];

            if (is_array($headers) && isset($headers[0])) {
                if (preg_match('/\s(\d{3})\s/', (string) $headers[0], $matches) === 1) {
                    $statusCode = (int) ($matches[1] ?? 0);
                }
            }

            if ($statusCode < 400 || $statusCode === 0) {
                return $content;
            }
        }

        $fallbackContent = self::fetchBadBotsViaShell($url);
        if ($fallbackContent !== null) {
            return $fallbackContent;
        }

        return null;
    }

    /**
     * Windows fallback when PHP cannot fetch https URLs directly.
     */
    private static function fetchBadBotsViaShell(string $url): ?string {
        if (!function_exists('shell_exec') || PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        $safeUrl = str_replace("'", "''", $url);
        $script = "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8; "
            . "\$ProgressPreference='SilentlyContinue'; "
            . "try { (Invoke-WebRequest -Uri '" . $safeUrl . "' -TimeoutSec 15).Content } "
            . "catch { '' }";

        $command = 'powershell -NoProfile -NonInteractive -Command ' . escapeshellarg($script);
        $output = @shell_exec($command);

        if (!is_string($output) || trim($output) === '') {
            return null;
        }

        return $output;
    }

    /**
     * Parses bot signatures from JSON or plain text.
     *
     * @return array<int, string>
     */
    private static function parseBadBotsContent(string $content): array {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $bots = self::extractBadBotsFromJson($decoded);
            if (!empty($bots)) {
                return $bots;
            }
        }

        return self::parseBadBotsPlainText($trimmed);
    }

    /**
     * Extracts bot signatures from mixed JSON structures.
     *
     * @return array<int, string>
     */
    private static function extractBadBotsFromJson(mixed $data): array {
        if (!is_array($data)) {
            return [];
        }

        $found = [];

        if (array_is_list($data)) {
            foreach ($data as $item) {
                if (is_string($item)) {
                    $found[] = $item;
                    continue;
                }
                if (is_array($item)) {
                    foreach (['user_agent', 'ua', 'name', 'bot'] as $key) {
                        if (isset($item[$key]) && is_string($item[$key])) {
                            $found[] = $item[$key];
                        }
                    }
                    $found = array_merge($found, self::extractBadBotsFromJson($item));
                }
            }
            return self::normalizeBotList($found);
        }

        foreach (['bad_bots', 'bots', 'user_agents', 'data', 'results', 'items'] as $containerKey) {
            if (isset($data[$containerKey])) {
                $found = array_merge($found, self::extractBadBotsFromJson($data[$containerKey]));
            }
        }

        if (!empty($found)) {
            return self::normalizeBotList($found);
        }

        $isScalarMap = true;
        foreach ($data as $value) {
            if (!is_scalar($value) && $value !== null) {
                $isScalarMap = false;
                break;
            }
        }

        if ($isScalarMap) {
            foreach ($data as $key => $value) {
                if (is_string($key)) {
                    if (is_bool($value) && $value) {
                        $found[] = $key;
                        continue;
                    }
                    if (is_numeric($value) && (int) $value === 1) {
                        $found[] = $key;
                        continue;
                    }
                }
                if (is_string($value)) {
                    $found[] = $value;
                }
            }
            return self::normalizeBotList($found);
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $found = array_merge($found, self::extractBadBotsFromJson($value));
            }
        }

        return self::normalizeBotList($found);
    }

    /**
     * Parses line-based bad bot list.
     *
     * @return array<int, string>
     */
    private static function parseBadBotsPlainText(string $content): array {
        $bots = [];
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, '//')) {
                continue;
            }

            if (str_starts_with(strtolower($line), 'user-agent:')) {
                $line = trim(substr($line, strlen('user-agent:')));
            }

            $line = preg_replace('/\s+#.*$/', '', $line) ?? $line;
            $line = trim($line);
            if ($line !== '') {
                $bots[] = $line;
            }
        }

        return self::normalizeBotList($bots);
    }

    /**
     * Returns list of configured remote sources for bot signatures.
     *
     * @return array<int, string>
     */
    private static function getBadBotSourceUrls(): array {
        $sources = [];

        foreach (['ENV_GUARD_BAD_BOTS_API_URL', 'ENV_GUARD_BAD_BOTS_API_URLS'] as $constName) {
            if (!defined($constName)) {
                continue;
            }

            $value = constant($constName);
            $candidates = [];

            if (is_array($value)) {
                $candidates = $value;
            } else {
                $raw = trim((string) $value);
                if ($raw !== '') {
                    $candidates = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                }
            }

            foreach ($candidates as $candidate) {
                $url = trim((string) $candidate);
                if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                    $sources[] = $url;
                }
            }
        }

        foreach (self::DEFAULT_BAD_BOTS_SOURCE_URLS as $source) {
            if (filter_var($source, FILTER_VALIDATE_URL)) {
                $sources[] = $source;
            }
        }

        return array_values(array_unique($sources));
    }

    /**
     * Sets runtime bad-bots list.
     *
     * @param array<int, string> $bots
     */
    public static function setBadBots(array $bots): void {
        self::initialize();
        self::$badBots = self::normalizeBotList($bots);

        $redis = self::getRedisClient();
        if (!$redis) {
            return;
        }

        try {
            $redis->del(self::REDIS_BAD_BOTS_SET_KEY);
            foreach (self::$badBots as $bot) {
                $redis->sAdd(self::REDIS_BAD_BOTS_SET_KEY, $bot);
            }
            $redis->setEx(self::REDIS_BAD_BOTS_MARKER, 3600, (string) time());
        } catch (\Throwable $e) {
            self::logBotGuard(Logger::LEVEL_WARNING, 'botguard_redis', 'BotGuard setBadBots Redis update failed: ' . $e->getMessage(), [], __FUNCTION__, false);
        }
    }

    /**
     * Adds IP/range into blacklist for provided duration.
     */
    public static function addIpToBlacklist(string $ipRange, int $blockDuration = 86400, ?string $reason = null): bool {
        $ipRange = trim($ipRange);
        if (!self::isValidIpRange($ipRange)) {
            self::logBotGuard(Logger::LEVEL_WARNING, 'botguard', 'Invalid IP range passed to blacklist', ['ip_range' => self::truncateForLog($ipRange)], __FUNCTION__, false);
            return false;
        }

        $blockDuration = max(1, $blockDuration);

        try {
            $db = SafeMySQL::gi();
            $exists = (int) $db->getOne(
                            "SELECT COUNT(*) FROM ?n WHERE ip_range = ?s AND block_until > NOW()",
                            Constants::IP_BLACKLIST_TABLE,
                            $ipRange
            );
            if ($exists > 0) {
                return true;
            }

            $sqlInsert = "INSERT INTO ?n (ip_range, block_until, reason)
                          VALUES (?s, NOW() + INTERVAL ?i SECOND, ?s)";
            $resDb = $db->query($sqlInsert, Constants::IP_BLACKLIST_TABLE, $ipRange, $blockDuration, $reason);
            if ($resDb === false) {
                return false;
            }

            $redis = self::getRedisClient();
            if ($redis) {
                try {
                    $redis->sAdd(self::$redisKeyIpBlacklist, $ipRange);
                } catch (\Throwable $e) {
                    self::logBotGuard(Logger::LEVEL_WARNING, 'botguard_redis', 'Redis blacklist add failed: ' . $e->getMessage(), [], __FUNCTION__, false);
                }
            }

            return true;
        } catch (\Throwable $e) {
            self::logBotGuard(Logger::LEVEL_ERROR, 'botguard', 'Failed to add IP to blacklist: ' . $e->getMessage(), ['ip_range' => $ipRange], __FUNCTION__);
            return false;
        }
    }

    /**
     * Updates local bad_bots.json from remote source(s).
     *
     * @return array{status:string,count?:int,source?:string,message?:string}
     */
    public static function updateBadBotList(): array {
        if (!self::ensureTmpPathExists()) {
            return ['status' => 'error', 'message' => 'Failed to create temp directory for bad_bots.json'];
        }

        $sources = self::getBadBotSourceUrls();
        if (empty($sources)) {
            return ['status' => 'error', 'message' => 'No bad-bots source URL configured'];
        }

        $destinationFile = self::getBadBotsFilePath();
        $lastMessage = 'Bad-bots update failed for all sources';

        foreach ($sources as $sourceUrl) {
            $badBotsArray = self::getBadBotsFromAPI($sourceUrl);
            if (empty($badBotsArray)) {
                $lastMessage = 'Empty bad-bots list from source: ' . $sourceUrl;
                continue;
            }

            if (!self::writeBadBotsToFile($destinationFile, $badBotsArray)) {
                $lastMessage = 'Failed to save bad_bots.json to disk';
                continue;
            }

            @touch(rtrim((string) ENV_TMP_PATH, '/\\') . DIRECTORY_SEPARATOR . self::BAD_BOTS_RESTORE_MARKER);
            self::$badBots = $badBotsArray;

            $redis = self::getRedisClient();
            if ($redis) {
                try {
                    $redis->del(self::REDIS_BAD_BOTS_SET_KEY);
                    foreach ($badBotsArray as $botName) {
                        $redis->sAdd(self::REDIS_BAD_BOTS_SET_KEY, $botName);
                    }
                    $redis->setEx(self::REDIS_BAD_BOTS_MARKER, 3600, (string) time());
                } catch (\Throwable $e) {
                    self::logBotGuard(Logger::LEVEL_WARNING, 'botguard_redis', 'BotGuard Redis update after bad-bots refresh failed: ' . $e->getMessage(), [], __FUNCTION__, false);
                }
            }

            return [
                'status' => 'success',
                'count' => count($badBotsArray),
                'source' => $sourceUrl,
            ];
        }

        self::logBotGuard(Logger::LEVEL_ERROR, 'botguard_update', $lastMessage, ['sources' => $sources], __FUNCTION__);

        return ['status' => 'error', 'message' => $lastMessage];
    }

    /**
     * Persists bad bot signatures to local JSON file.
     *
     * @param array<int, string> $badBots
     */
    private static function writeBadBotsToFile(string $destinationFile, array $badBots): bool {
        $json = json_encode(self::normalizeBotList($badBots), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return false;
        }

        $saved = @file_put_contents($destinationFile, $json, LOCK_EX);
        return $saved !== false;
    }

    /**
     * Increments offense counter for IP.
     */
    private static function incrementOffenseCounter(string $ip): int {
        if (self::$useRedis && self::getRedisClient()) {
            return self::_incrementOffenseRedis($ip);
        }
        return self::_incrementOffenseDb($ip);
    }

    /**
     * Redis offense counter.
     */
    private static function _incrementOffenseRedis(string $ip): int {
        try {
            $redis = self::getRedisClient();
            if (!$redis) {
                return self::_incrementOffenseDb($ip);
            }

            $ttl = (int) ENV_GUARD_STRIKE_TTL;
            if ($ttl <= 0) {
                $ttl = 30;
            }

            $key = 'ee_offense_count:' . sha1($ip);
            $count = (int) $redis->incr($key);
            if ($count === 1) {
                $redis->expire($key, $ttl);
            }

            return $count;
        } catch (\Throwable $e) {
            return 1;
        }
    }

    /**
     * DB offense counter.
     */
    private static function _incrementOffenseDb(string $ip): int {
        try {
            $ttl = (int) ENV_GUARD_STRIKE_TTL;
            if ($ttl <= 0) {
                $ttl = 30;
            }

            $db = SafeMySQL::gi();
            $sql = "INSERT INTO ?n (ip, last_offense_at) VALUES(?s, NOW())
                    ON DUPLICATE KEY UPDATE
                    strike_count = IF(last_offense_at < NOW() - INTERVAL ?i SECOND, 1, strike_count + 1),
                    last_offense_at = NOW()";

            $db->query($sql, Constants::IP_OFFENSES_TABLE, $ip, $ttl);

            return (int) $db->getOne(
                            "SELECT strike_count FROM ?n WHERE ip = ?s",
                            Constants::IP_OFFENSES_TABLE,
                            $ip
            );
        } catch (\Throwable $e) {
            return 1;
        }
    }

    /**
     * Sanitizes context recursively before logging.
     */
    private static function summarizeValueForLog(mixed $value, int $depth = 0): mixed {
        if ($depth > 3) {
            return '[depth_limit]';
        }

        if (is_array($value)) {
            $result = [];
            $i = 0;
            foreach ($value as $key => $item) {
                if ($i >= 20) {
                    $result['...'] = '[truncated]';
                    break;
                }

                $stringKey = (string) $key;
                if (self::isSensitiveKey($stringKey)) {
                    $result[$stringKey] = '[redacted]';
                } else {
                    $result[$stringKey] = self::summarizeValueForLog($item, $depth + 1);
                }
                $i++;
            }
            return $result;
        }

        if (is_object($value)) {
            return '[object ' . get_class($value) . ']';
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return self::truncateForLog((string) $value);
    }

    /**
     * Checks whether key likely contains sensitive data.
     */
    private static function isSensitiveKey(string $key): bool {
        $key = strtolower($key);
        $sensitiveTokens = [
            'password',
            'pwd',
            'pass',
            'token',
            'secret',
            'session',
            'cookie',
            'authorization',
            'auth',
        ];

        foreach ($sensitiveTokens as $token) {
            if (str_contains($key, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Truncates strings for safer logging.
     */
    private static function truncateForLog(string $value, int $maxLen = 200): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value) <= $maxLen) {
                return $value;
            }
            return mb_substr($value, 0, $maxLen) . '...';
        }

        if (strlen($value) <= $maxLen) {
            return $value;
        }

        return substr($value, 0, $maxLen) . '...';
    }
}
