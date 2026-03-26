<?php

namespace classes\system;

/**
 * Единый сервис логирования CMS.
 * Сохраняет обратную совместимость с legacy-форматом блоков, но добавляет
 * production-ready контекст запроса, уровень, канал и управляемую ротацию.
 */
class Logger {

    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_NOTICE = 'NOTICE';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';
    public const LEVEL_CRITICAL = 'CRITICAL';
    public const LEVEL_ALERT = 'ALERT';
    public const LEVEL_EMERGENCY = 'EMERGENCY';
    public const LEVEL_AUDIT = 'AUDIT';

    private static bool $bootstrapped = false;
    private static ?string $requestId = null;
    private static array $cleanedDirectories = [];

    /**
     * Инициализация runtime-контекста логирования.
     */
    public static function bootstrap(): void {
        if (self::$bootstrapped) {
            return;
        }

        self::$requestId = self::resolveRequestId();
        $headerName = trim((string) (defined('ENV_LOG_REQUEST_ID_HEADER') ? ENV_LOG_REQUEST_ID_HEADER : 'X-Request-Id'));
        if ($headerName !== '' && PHP_SAPI !== 'cli' && !headers_sent()) {
            header($headerName . ': ' . self::$requestId);
        }

        self::$bootstrapped = true;
    }

    /**
     * Legacy wrapper для существующих вызовов SysClass::preFile().
     */
    public static function legacy(string $subFolder, string $initiator, mixed $result, mixed $details = ''): bool {
        $message = is_scalar($result) || $result === null
            ? (string) $result
            : self::safeJsonEncode($result);

        $context = [];
        if (is_array($details)) {
            $context = $details;
        } elseif ($details instanceof \Throwable) {
            $context = ['throwable' => self::throwableToArray($details)];
        } elseif ($details !== '' && $details !== null) {
            $context = ['details' => $details];
        }

        return self::log(
            self::inferLegacyLevel($subFolder),
            $subFolder,
            $message,
            $context,
            [
                'initiator' => $initiator,
                'details' => $details,
                'include_trace' => true,
                'legacy_result' => $result,
            ]
        );
    }

    public static function debug(string $channel, string $message, array $context = [], array $options = []): bool {
        return self::log(self::LEVEL_DEBUG, $channel, $message, $context, $options);
    }

    public static function info(string $channel, string $message, array $context = [], array $options = []): bool {
        return self::log(self::LEVEL_INFO, $channel, $message, $context, $options);
    }

    public static function notice(string $channel, string $message, array $context = [], array $options = []): bool {
        return self::log(self::LEVEL_NOTICE, $channel, $message, $context, $options);
    }

    public static function warning(string $channel, string $message, array $context = [], array $options = []): bool {
        return self::log(self::LEVEL_WARNING, $channel, $message, $context, $options);
    }

    public static function error(string $channel, string $message, array $context = [], array $options = []): bool {
        return self::log(self::LEVEL_ERROR, $channel, $message, $context, $options);
    }

    public static function critical(string $channel, string $message, array $context = [], array $options = []): bool {
        return self::log(self::LEVEL_CRITICAL, $channel, $message, $context, $options);
    }

    public static function audit(string $channel, string $message, array $context = [], array $options = []): bool {
        return self::log(self::LEVEL_AUDIT, $channel, $message, $context, $options);
    }

    /**
     * Основной метод structured logging.
     */
    public static function log(string $level, string $channel, string $message, array $context = [], array $options = []): bool {
        if (!defined('ENV_LOG') || !ENV_LOG) {
            return false;
        }

        self::bootstrap();

        $level = self::normalizeLevel($level);
        $channel = self::normalizeChannel($channel);
        $initiator = trim((string) ($options['initiator'] ?? self::detectInitiator()));
        $details = $options['details'] ?? $context;
        $includeTrace = array_key_exists('include_trace', $options)
            ? (bool) $options['include_trace']
            : in_array($level, [self::LEVEL_WARNING, self::LEVEL_ERROR, self::LEVEL_CRITICAL, self::LEVEL_ALERT, self::LEVEL_EMERGENCY], true);

        $payload = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'channel' => $channel,
            'initiator' => $initiator !== '' ? $initiator : 'unknown',
            'message' => $message,
            'details' => self::stringifyValue($details),
            'context' => self::normalizeContext($context),
            'meta' => self::buildMeta($options),
            'stack_trace' => $includeTrace ? self::buildTrace($options['trace'] ?? null) : [],
        ];

        $logPath = self::buildChannelLogPath($channel, $payload['timestamp']);
        $logBlock = self::buildStructuredBlock($payload);
        $written = @file_put_contents($logPath, $logBlock, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            error_log('[EE Logger][' . $channel . '] ' . $message . ' ' . self::safeJsonEncode($payload['context']));
            return false;
        }

        @chmod($logPath, 0664);
        self::maintainLogFile($logPath);
        return true;
    }

    /**
     * Логирование фатальной shutdown-ошибки.
     */
    public static function logFatalShutdownError(array $error): void {
        $message = (string) ($error['message'] ?? 'Fatal shutdown error');
        $file = (string) ($error['file'] ?? '');
        $line = (int) ($error['line'] ?? 0);
        $type = (int) ($error['type'] ?? 0);

        self::critical(
            'php_fatal',
            $message,
            [
                'file' => $file,
                'line' => $line,
                'type' => $type,
            ],
            [
                'initiator' => 'register_shutdown_function',
                'details' => $message . ($file !== '' ? ' in ' . $file . ' on line ' . $line : ''),
                'include_trace' => false,
            ]
        );

        $formattedError = sprintf(
            "Date: %s\nMessage: %s in %s on line %s\n\n",
            date('d-m-Y H:i:s'),
            $message,
            $file,
            $line
        );
        $fatalLogPath = ENV_LOGS_PATH . 'fatal_errors.txt';
        if (function_exists('ee_runtime_append_managed_log')) {
            ee_runtime_append_managed_log($fatalLogPath, $formattedError, FILE_APPEND | LOCK_EX);
            return;
        }

        @file_put_contents($fatalLogPath, $formattedError, FILE_APPEND | LOCK_EX);
        @chmod($fatalLogPath, 0664);
    }

    /**
     * Вернёт request id текущего запроса.
     */
    public static function getRequestId(): string {
        self::bootstrap();
        return self::$requestId ?? self::resolveRequestId();
    }

    private static function normalizeLevel(string $level): string {
        $level = strtoupper(trim($level));
        $allowed = [
            self::LEVEL_DEBUG,
            self::LEVEL_INFO,
            self::LEVEL_NOTICE,
            self::LEVEL_WARNING,
            self::LEVEL_ERROR,
            self::LEVEL_CRITICAL,
            self::LEVEL_ALERT,
            self::LEVEL_EMERGENCY,
            self::LEVEL_AUDIT,
        ];
        return in_array($level, $allowed, true) ? $level : self::LEVEL_INFO;
    }

    private static function normalizeChannel(string $channel): string {
        $channel = trim($channel);
        if ($channel === '') {
            return 'system';
        }
        $channel = preg_replace('~[^a-zA-Z0-9_\\-]+~', '_', $channel) ?? 'system';
        $channel = trim($channel, '_-');
        return $channel !== '' ? $channel : 'system';
    }

    private static function detectInitiator(): string {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        foreach ($trace as $item) {
            $function = (string) ($item['function'] ?? '');
            $class = (string) ($item['class'] ?? '');
            if ($class === self::class || $function === 'log' || $function === 'legacy') {
                continue;
            }
            return $class !== '' ? $class . '::' . $function : $function;
        }
        return 'unknown';
    }

    private static function normalizeContext(array $context): array {
        foreach ($context as $key => $value) {
            if ($value instanceof \Throwable) {
                $context[$key] = self::throwableToArray($value);
                continue;
            }
            if (is_object($value) && !($value instanceof \JsonSerializable)) {
                $context[$key] = method_exists($value, '__toString') ? (string) $value : get_class($value);
            }
        }
        return $context;
    }

    private static function buildMeta(array $options = []): array {
        $userId = null;
        if (class_exists(AuthSessionService::class)) {
            try {
                $userId = AuthSessionService::resolveCurrentUserId();
            } catch (\Throwable) {
                $userId = null;
            }
        }

        $meta = [
            'request_id' => self::$requestId ?? self::resolveRequestId(),
            'user_id' => $userId ?: null,
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? (PHP_SAPI === 'cli' ? 'CLI' : '')),
            'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            'host' => (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''),
            'ip' => self::resolveRemoteAddress(),
            'sapi' => PHP_SAPI,
            'pid' => function_exists('getmypid') ? (int) getmypid() : 0,
            'controller' => defined('ENV_CONTROLLER_FOLDER') ? (string) ENV_CONTROLLER_FOLDER : '',
            'action' => defined('ENV_CONTROLLER_ACTION') ? (string) ENV_CONTROLLER_ACTION : '',
        ];

        if (PHP_SAPI === 'cli') {
            $meta['cli_command'] = implode(' ', array_map('strval', (array) ($_SERVER['argv'] ?? [])));
        }

        if (!empty($options['meta']) && is_array($options['meta'])) {
            $meta = array_merge($meta, self::normalizeContext($options['meta']));
        }

        return $meta;
    }

    private static function buildTrace(mixed $trace = null): array {
        if ($trace instanceof \Throwable) {
            return self::normalizeTraceArray($trace->getTrace());
        }
        if (is_array($trace)) {
            return self::normalizeTraceArray($trace);
        }
        return self::normalizeTraceArray(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
    }

    private static function normalizeTraceArray(array $trace): array {
        $formatted = [];
        foreach ($trace as $item) {
            $formatted[] = [
                'function' => $item['function'] ?? 'N/A',
                'line' => $item['line'] ?? 'N/A',
                'file' => $item['file'] ?? 'N/A',
                'class' => $item['class'] ?? 'N/A',
                'type' => $item['type'] ?? 'N/A',
            ];
        }
        return $formatted;
    }

    private static function buildStructuredBlock(array $payload): string {
        $meta = $payload['meta'];
        $block = "{START}";
        $block .= PHP_EOL . 'Время события: ' . $payload['timestamp'];
        $block .= PHP_EOL . 'Уровень: ' . $payload['level'];
        $block .= PHP_EOL . 'Канал: ' . $payload['channel'];
        $block .= PHP_EOL . 'Инициатор: ' . var_export($payload['initiator'], true);
        $block .= PHP_EOL . 'Request ID: ' . ($meta['request_id'] ?? '');
        $block .= PHP_EOL . 'User ID: ' . (($meta['user_id'] ?? '') !== null ? (string) $meta['user_id'] : '');
        $block .= PHP_EOL . 'Метод: ' . ($meta['method'] ?? '');
        $block .= PHP_EOL . 'URI: ' . ($meta['uri'] ?? '');
        $block .= PHP_EOL . 'Host: ' . ($meta['host'] ?? '');
        $block .= PHP_EOL . 'IP: ' . ($meta['ip'] ?? '');
        $block .= PHP_EOL . 'Результат: ' . $payload['message'];
        $block .= PHP_EOL . 'Детали: ' . $payload['details'];
        $block .= PHP_EOL . 'Контекст: ' . self::safeJsonEncode($payload['context']);
        $block .= PHP_EOL . 'Мета: ' . self::safeJsonEncode($meta);
        $block .= PHP_EOL . 'Полный стек вызовов: ' . self::safeJsonEncode($payload['stack_trace']);
        $block .= PHP_EOL . '{END}' . PHP_EOL;
        return $block;
    }

    private static function buildChannelLogPath(string $channel, string $timestamp): string {
        $logsPath = rtrim((string) ENV_LOGS_PATH, '/\\') . ENV_DIRSEP . $channel;
        if (!is_dir($logsPath)) {
            @mkdir($logsPath, 02775, true);
        }
        @chmod($logsPath, 02775);
        return $logsPath . ENV_DIRSEP . substr($timestamp, 0, 10) . '.txt';
    }

    private static function maintainLogFile(string $filePath): void {
        self::rotateIfTooBig($filePath);
        $directory = dirname($filePath);
        if (isset(self::$cleanedDirectories[$directory])) {
            return;
        }
        self::$cleanedDirectories[$directory] = true;
        self::cleanupOldFiles($directory);
    }

    private static function rotateIfTooBig(string $filePath): void {
        if (!is_file($filePath)) {
            return;
        }
        $maxSize = (int) (defined('ENV_LOG_ROTATE_FILE_SIZE') ? ENV_LOG_ROTATE_FILE_SIZE : (10 * 1024 * 1024));
        $size = (int) (@filesize($filePath) ?: 0);
        if ($maxSize <= 0 || $size < $maxSize) {
            return;
        }

        self::cleanupOldestBackup($filePath);
        self::shiftBackups($filePath);
        $backupPath = $filePath . '.1';
        if (@rename($filePath, $backupPath)) {
            @touch($filePath);
            @chmod($filePath, 0664);
        }
    }

    private static function cleanupOldFiles(string $directory): void {
        if (!is_dir($directory)) {
            return;
        }
        $items = @scandir($directory);
        if ($items === false) {
            return;
        }
        $maxAgeDays = max(1, (int) (defined('ENV_LOG_RETENTION_DAYS') ? ENV_LOG_RETENTION_DAYS : 30));
        $cutoffTime = time() - ($maxAgeDays * 86400);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_file($path) && preg_match('/\.(txt|log)(\.\d+)?$/', $item)) {
                $mtime = @filemtime($path);
                if ($mtime && $mtime < $cutoffTime) {
                    @unlink($path);
                }
            }
        }
    }

    private static function shiftBackups(string $filePath): void {
        $maxBackupCount = max(1, (int) (defined('ENV_LOG_MAX_BACKUPS') ? ENV_LOG_MAX_BACKUPS : 5));
        for ($i = $maxBackupCount - 1; $i >= 1; $i--) {
            $current = $filePath . '.' . $i;
            $next = $filePath . '.' . ($i + 1);
            if (is_file($current)) {
                if (is_file($next)) {
                    @unlink($next);
                }
                @rename($current, $next);
            }
        }
    }

    private static function cleanupOldestBackup(string $filePath): void {
        $maxBackupCount = max(1, (int) (defined('ENV_LOG_MAX_BACKUPS') ? ENV_LOG_MAX_BACKUPS : 5));
        $oldest = $filePath . '.' . $maxBackupCount;
        if (is_file($oldest)) {
            @unlink($oldest);
        }
    }

    private static function inferLegacyLevel(string $channel): string {
        $channel = strtolower($channel);
        if (str_contains($channel, 'error')) {
            return self::LEVEL_ERROR;
        }
        if (str_contains($channel, 'warning')) {
            return self::LEVEL_WARNING;
        }
        if (str_contains($channel, 'audit') || str_contains($channel, 'info')) {
            return self::LEVEL_AUDIT;
        }
        return self::LEVEL_INFO;
    }

    private static function resolveRequestId(): string {
        $headerValue = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
        if ($headerValue !== '') {
            return substr($headerValue, 0, 64);
        }

        try {
            return bin2hex(random_bytes(8));
        } catch (\Throwable) {
            return substr(sha1(uniqid((string) mt_rand(), true)), 0, 16);
        }
    }

    private static function resolveRemoteAddress(): string {
        $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remoteAddr !== '') {
            return $remoteAddr;
        }
        $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded === '') {
            return '';
        }
        $parts = array_map('trim', explode(',', $forwarded));
        return (string) ($parts[0] ?? '');
    }

    private static function stringifyValue(mixed $value): string {
        if ($value instanceof \Throwable) {
            return self::safeJsonEncode(self::throwableToArray($value));
        }
        if (is_array($value) || is_object($value)) {
            return self::safeJsonEncode($value);
        }
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return (string) $value;
    }

    private static function safeJsonEncode(mixed $value): string {
        try {
            $json = json_encode(
                $value,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
            if (is_string($json)) {
                return $json;
            }
        } catch (\Throwable) {
        }
        return var_export($value, true);
    }

    private static function throwableToArray(\Throwable $throwable): array {
        return [
            'class' => get_class($throwable),
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'code' => $throwable->getCode(),
            'trace' => $throwable->getTrace(),
        ];
    }
}
