<?php

if (!function_exists('ee_split_host_and_port')) {
    function ee_split_host_and_port(string $value): array {
        $value = trim(strtolower($value));
        if ($value === '') {
            return ['host' => '', 'port' => null];
        }

        $probe = str_contains($value, '://') ? $value : '//' . ltrim($value, '/');
        $host = (string) (parse_url($probe, PHP_URL_HOST) ?? '');
        $port = parse_url($probe, PHP_URL_PORT);

        if ($host === '') {
            $value = preg_replace('~[/?#].*$~', '', $value) ?? '';
            if ($value === '') {
                return ['host' => '', 'port' => null];
            }
            if (preg_match('/^\[(.+)\](?::(\d+))?$/', $value, $matches)) {
                return ['host' => strtolower($matches[1]), 'port' => $matches[2] ?? null];
            }
            if (substr_count($value, ':') === 1 && preg_match('/^([^:]+):(\d+)$/', $value, $matches)) {
                return ['host' => strtolower($matches[1]), 'port' => $matches[2]];
            }
            return ['host' => rtrim($value, '.'), 'port' => null];
        }

        return [
            'host' => rtrim(strtolower($host), '.'),
            'port' => $port !== null ? (string) $port : null,
        ];
    }
}

if (!function_exists('ee_format_host')) {
    function ee_format_host(string $host, int|string|null $port = null): string {
        $host = trim(strtolower($host));
        if ($host === '') {
            return '';
        }
        $hostForUrl = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[' . $host . ']' : $host;
        if ($port === null || $port === '') {
            return $hostForUrl;
        }
        return $hostForUrl . ':' . (string) $port;
    }
}

if (!function_exists('ee_normalize_host')) {
    function ee_normalize_host(string $value, bool $preservePort = true): string {
        $parts = ee_split_host_and_port($value);
        if ($parts['host'] === '') {
            return '';
        }
        return ee_format_host($parts['host'], $preservePort ? ($parts['port'] ?? null) : null);
    }
}

if (!function_exists('ee_get_request_host')) {
    function ee_get_request_host(): string {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
        $normalized = ee_normalize_host($host, true);
        return $normalized !== '' ? $normalized : 'localhost';
    }
}

if (!function_exists('ee_get_request_host_name')) {
    function ee_get_request_host_name(): string {
        $parts = ee_split_host_and_port(ee_get_request_host());
        return $parts['host'] !== '' ? $parts['host'] : 'localhost';
    }
}

if (!function_exists('ee_is_local_host')) {
    function ee_is_local_host(string $host): bool {
        $parts = ee_split_host_and_port($host);
        $hostName = $parts['host'] ?? '';
        if ($hostName === '') {
            return false;
        }
        if (in_array($hostName, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }
        return str_ends_with($hostName, '.localhost');
    }
}

if (!function_exists('ee_get_request_scheme')) {
    function ee_get_request_scheme(): string {
        $https = strtolower(trim((string) ($_SERVER['HTTPS'] ?? '')));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return 'https';
        }

        $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwardedProto !== '') {
            $forwardedProto = explode(',', $forwardedProto)[0] ?? '';
            $forwardedProto = trim($forwardedProto);
            if (in_array($forwardedProto, ['http', 'https'], true)) {
                return $forwardedProto;
            }
        }

        $requestScheme = strtolower(trim((string) ($_SERVER['REQUEST_SCHEME'] ?? '')));
        if (in_array($requestScheme, ['http', 'https'], true)) {
            return $requestScheme;
        }

        return ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') ? 'https' : 'http';
    }
}

if (!function_exists('ee_build_base_url')) {
    function ee_build_base_url(string $scheme, string $host): string {
        $scheme = strtolower($scheme) === 'https' ? 'https' : 'http';
        $normalizedHost = ee_normalize_host($host, true);
        if ($normalizedHost === '') {
            $normalizedHost = 'localhost';
        }
        return $scheme . '://' . $normalizedHost;
    }
}

if (!function_exists('ee_get_canonical_host')) {
    function ee_get_canonical_host(array $config): string {
        $configuredHost = ee_normalize_host((string) ($config['ENV_CANONICAL_HOST'] ?? ''), false);
        if ($configuredHost !== '') {
            return $configuredHost;
        }

        $fallbackHost = ee_normalize_host((string) ($config['ENV_SITE_NAME'] ?? ''), false);
        if ($fallbackHost !== '' && !ee_is_local_host($fallbackHost)) {
            return $fallbackHost;
        }

        return ee_normalize_host(ee_get_request_host(), false);
    }
}

if (!function_exists('ee_get_effective_site_host')) {
    function ee_get_effective_site_host(array $config): string {
        if (PHP_SAPI === 'cli') {
            $canonicalHost = ee_get_canonical_host($config);
            if ($canonicalHost !== '') {
                return $canonicalHost;
            }
        }
        $requestHost = ee_get_request_host();
        if (ee_is_local_host($requestHost)) {
            $canonicalHost = ee_get_canonical_host($config);
            return $canonicalHost !== '' ? $canonicalHost : $requestHost;
        }
        $canonicalHost = ee_get_canonical_host($config);
        return $canonicalHost !== '' ? $canonicalHost : $requestHost;
    }
}

if (!function_exists('ee_get_effective_site_scheme')) {
    function ee_get_effective_site_scheme(array $config): string {
        if (PHP_SAPI === 'cli') {
            $configuredScheme = strtolower(trim((string) ($config['ENV_CANONICAL_SCHEME'] ?? 'https')));
            return $configuredScheme === 'http' ? 'http' : 'https';
        }
        $requestHost = ee_get_request_host();
        if (ee_is_local_host($requestHost)) {
            $configuredScheme = strtolower(trim((string) ($config['ENV_CANONICAL_SCHEME'] ?? 'https')));
            return $configuredScheme === 'http' ? 'http' : ee_get_request_scheme();
        }
        $configuredScheme = strtolower(trim((string) ($config['ENV_CANONICAL_SCHEME'] ?? 'https')));
        return $configuredScheme === 'http' ? 'http' : 'https';
    }
}

if (!function_exists('ee_should_redirect_to_canonical')) {
    function ee_should_redirect_to_canonical(array $config): bool {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return false;
        }

        if (empty($config['ENV_CANONICAL_REDIRECT'])) {
            return false;
        }

        $requestHost = ee_get_request_host();
        if (ee_is_local_host($requestHost)) {
            return false;
        }

        $canonicalHost = ee_get_canonical_host($config);
        if ($canonicalHost === '') {
            return false;
        }

        $requestHostName = ee_normalize_host($requestHost, false);
        $effectiveScheme = ee_get_effective_site_scheme($config);
        $requestScheme = ee_get_request_scheme();

        return $requestHostName !== $canonicalHost || $requestScheme !== $effectiveScheme;
    }
}

if (!function_exists('ee_apply_canonical_redirect')) {
    function ee_apply_canonical_redirect(array $config): void {
        if (!ee_should_redirect_to_canonical($config)) {
            return;
        }

        $baseUrl = ee_build_base_url(
            ee_get_effective_site_scheme($config),
            ee_get_effective_site_host($config)
        );
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        if ($requestUri === '' || $requestUri[0] !== '/') {
            $requestUri = '/' . ltrim($requestUri, '/');
        }

        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $statusCode = in_array($requestMethod, ['GET', 'HEAD'], true) ? 301 : 308;
        header('Location: ' . rtrim($baseUrl, '/') . $requestUri, true, $statusCode);
        exit;
    }
}

if (!function_exists('ee_get_cache_namespace')) {
    function ee_get_cache_namespace(array $config = []): string {
        $rawNamespace = (string) ($config['ENV_CANONICAL_HOST'] ?? $config['ENV_SITE_NAME'] ?? 'ee-site');
        $normalized = preg_replace('~[^a-z0-9]+~i', '-', strtolower($rawNamespace)) ?? 'ee-site';
        $normalized = trim($normalized, '-');
        return $normalized !== '' ? $normalized : 'ee-site';
    }
}

if (!function_exists('ee_parse_utc_datetime')) {
    function ee_parse_utc_datetime(?string $value): ?DateTimeImmutable {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $utc = new DateTimeZone('UTC');
        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $utc);
        if ($dateTime instanceof DateTimeImmutable) {
            return $dateTime;
        }

        try {
            return new DateTimeImmutable($value, $utc);
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('ee_resolve_datetime_for_runtime')) {
    function ee_resolve_datetime_for_runtime(?string $value): ?DateTimeImmutable {
        $utcDateTime = ee_parse_utc_datetime($value);
        if (!$utcDateTime) {
            return null;
        }

        $localTimezone = new DateTimeZone(date_default_timezone_get());
        if ($localTimezone->getName() === 'UTC') {
            return $utcDateTime;
        }

        $rawValue = trim((string) $value);
        if ($rawValue === '') {
            return $utcDateTime;
        }

        $localDateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $rawValue, $localTimezone);
        if (!$localDateTime instanceof DateTimeImmutable) {
            try {
                $localDateTime = new DateTimeImmutable($rawValue, $localTimezone);
            } catch (Throwable) {
                $localDateTime = null;
            }
        }

        if (!$localDateTime instanceof DateTimeImmutable) {
            return $utcDateTime;
        }

        $nowTs = time();
        $utcDelta = $nowTs - $utcDateTime->getTimestamp();
        $localDelta = $nowTs - $localDateTime->getTimestamp();

        if ($utcDelta < -300 && $localDelta >= -300) {
            return $localDateTime;
        }

        return $utcDateTime;
    }
}

if (!function_exists('ee_format_utc_datetime')) {
    function ee_format_utc_datetime(?string $value, string $format = 'd.m.Y H:i'): string {
        $dateTime = ee_resolve_datetime_for_runtime($value);
        if (!$dateTime) {
            return trim((string) $value);
        }

        $localTimezone = new DateTimeZone(date_default_timezone_get());
        return $dateTime->setTimezone($localTimezone)->format($format);
    }
}

if (!function_exists('ee_minutes_since_utc_datetime')) {
    function ee_minutes_since_utc_datetime(?string $value): int {
        $dateTime = ee_resolve_datetime_for_runtime($value);
        if (!$dateTime) {
            return -1;
        }

        return max(0, (int) floor((time() - $dateTime->getTimestamp()) / 60));
    }
}

if (!function_exists('ee_get_cache_version')) {
    function ee_get_cache_version(array $config = []): string {
        $fingerprint = [
            (string) ($config['ENV_VERSION_CORE'] ?? '0'),
            (string) @filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'configuration.php'),
            (string) @filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'startup.php'),
            (string) @filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'hooks.php'),
            (string) @filemtime(__FILE__),
        ];
        return substr(sha1(implode('|', $fingerprint)), 0, 12);
    }
}

if (!function_exists('ee_runtime_write_file')) {
    /**
     * Best-effort запись runtime-файлов без шумных warning в bootstrap-контуре.
     */
    function ee_runtime_write_file(string $path, string $contents, int $flags = 0, int $chmod = 0664, bool $replaceUnwritableFile = false): bool {
        $dir = dirname($path);
        if ($dir === '' || $dir === '.' || $dir === DIRECTORY_SEPARATOR) {
            return false;
        }

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        if ($replaceUnwritableFile && file_exists($path) && !is_writable($path) && is_writable($dir)) {
            @unlink($path);
        }

        $written = @file_put_contents($path, $contents, $flags);
        if ($written === false && $replaceUnwritableFile && file_exists($path) && !is_writable($path) && is_writable($dir)) {
            @unlink($path);
            $written = @file_put_contents($path, $contents, $flags);
        }

        if ($written === false) {
            return false;
        }

        @chmod($path, $chmod);
        return true;
    }
}

if (!function_exists('ee_runtime_log_limits')) {
    function ee_runtime_log_limits(?array $config = null): array {
        return [
            'max_size' => max(0, (int) (($config['ENV_LOG_ROTATE_FILE_SIZE'] ?? null) ?? (defined('ENV_LOG_ROTATE_FILE_SIZE') ? ENV_LOG_ROTATE_FILE_SIZE : (10 * 1024 * 1024)))),
            'max_backups' => max(1, (int) (($config['ENV_LOG_MAX_BACKUPS'] ?? null) ?? (defined('ENV_LOG_MAX_BACKUPS') ? ENV_LOG_MAX_BACKUPS : 5))),
            'retention_days' => max(1, (int) (($config['ENV_LOG_RETENTION_DAYS'] ?? null) ?? (defined('ENV_LOG_RETENTION_DAYS') ? ENV_LOG_RETENTION_DAYS : 30))),
        ];
    }
}

if (!function_exists('ee_runtime_cleanup_oldest_backup')) {
    function ee_runtime_cleanup_oldest_backup(string $filePath, int $maxBackupCount): void {
        $oldest = $filePath . '.' . $maxBackupCount;
        if (is_file($oldest)) {
            @unlink($oldest);
        }
    }
}

if (!function_exists('ee_runtime_shift_backups')) {
    function ee_runtime_shift_backups(string $filePath, int $maxBackupCount): void {
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
}

if (!function_exists('ee_runtime_rotate_log_file')) {
    function ee_runtime_rotate_log_file(string $filePath, int $maxSize, int $maxBackupCount): void {
        if ($maxSize <= 0 || !is_file($filePath)) {
            return;
        }

        $size = (int) (@filesize($filePath) ?: 0);
        if ($size < $maxSize) {
            return;
        }

        ee_runtime_cleanup_oldest_backup($filePath, $maxBackupCount);
        ee_runtime_shift_backups($filePath, $maxBackupCount);
        $backupPath = $filePath . '.1';
        if (@rename($filePath, $backupPath)) {
            @touch($filePath);
            @chmod($filePath, 0664);
        }
    }
}

if (!function_exists('ee_runtime_cleanup_old_logs')) {
    function ee_runtime_cleanup_old_logs(string $directory, int $retentionDays): void {
        if (!is_dir($directory)) {
            return;
        }

        $items = @scandir($directory);
        if ($items === false) {
            return;
        }

        $cutoffTime = time() - ($retentionDays * 86400);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (!is_file($path)) {
                continue;
            }

            if (preg_match('/\.(txt|log)(\.\d+)?$/', $item) !== 1) {
                continue;
            }

            $mtime = @filemtime($path);
            if ($mtime && $mtime < $cutoffTime) {
                @unlink($path);
            }
        }
    }
}

if (!function_exists('ee_runtime_maintain_log_file')) {
    function ee_runtime_maintain_log_file(string $filePath, ?array $config = null): void {
        $limits = ee_runtime_log_limits($config);
        ee_runtime_rotate_log_file($filePath, $limits['max_size'], $limits['max_backups']);
        ee_runtime_cleanup_old_logs(dirname($filePath), $limits['retention_days']);
    }
}

if (!function_exists('ee_runtime_append_managed_log')) {
    function ee_runtime_append_managed_log(
        string $path,
        string $contents,
        int $flags = FILE_APPEND | LOCK_EX,
        int $chmod = 0664,
        bool $replaceUnwritableFile = false,
        ?array $config = null
    ): bool {
        $written = ee_runtime_write_file($path, $contents, $flags, $chmod, $replaceUnwritableFile);
        if ($written) {
            ee_runtime_maintain_log_file($path, $config);
        }
        return $written;
    }
}

if (!function_exists('ee_parse_memory_size_to_bytes')) {
    function ee_parse_memory_size_to_bytes(int|string|null $value): int {
        if (is_int($value)) {
            return max(0, $value);
        }

        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        if ($value === '-1') {
            return -1;
        }

        if (!preg_match('~^([0-9]+(?:\.[0-9]+)?)\s*([kmgt]?b?)?$~i', $value, $matches)) {
            return 0;
        }

        $number = (float) ($matches[1] ?? 0);
        $unit = strtolower((string) ($matches[2] ?? ''));

        return match ($unit) {
            'g', 'gb' => (int) round($number * 1024 * 1024 * 1024),
            'm', 'mb' => (int) round($number * 1024 * 1024),
            'k', 'kb' => (int) round($number * 1024),
            default => (int) round($number),
        };
    }
}

if (!function_exists('ee_apply_runtime_memory_limit')) {
    function ee_apply_runtime_memory_limit(array $config): void {
        $limit = PHP_SAPI === 'cli'
            ? (string) ($config['ENV_MEMORY_LIMIT_CLI'] ?? '')
            : (string) ($config['ENV_MEMORY_LIMIT_WEB'] ?? '');

        $limit = trim($limit);
        if ($limit === '') {
            return;
        }

        @ini_set('memory_limit', $limit);
    }
}

if (!function_exists('ee_get_runtime_memory_limit_bytes')) {
    function ee_get_runtime_memory_limit_bytes(): int {
        return ee_parse_memory_size_to_bytes((string) @ini_get('memory_limit'));
    }
}

if (!function_exists('ee_get_runtime_memory_usage_bytes')) {
    function ee_get_runtime_memory_usage_bytes(bool $realUsage = true): int {
        return (int) memory_get_usage($realUsage);
    }
}

if (!function_exists('ee_get_runtime_soft_memory_limit_bytes')) {
    function ee_get_runtime_soft_memory_limit_bytes(string $scope = 'generic', ?array $config = null): int {
        $config ??= [];

        $configuredMb = match ($scope) {
            'media_worker' => (int) (($config['ENV_MEDIA_MIRROR_MEMORY_SOFT_LIMIT_MB'] ?? null) ?? (defined('ENV_MEDIA_MIRROR_MEMORY_SOFT_LIMIT_MB') ? ENV_MEDIA_MIRROR_MEMORY_SOFT_LIMIT_MB : 0)),
            'cron_agent' => (int) (($config['ENV_CRON_AGENT_MEMORY_SOFT_LIMIT_MB'] ?? null) ?? (defined('ENV_CRON_AGENT_MEMORY_SOFT_LIMIT_MB') ? ENV_CRON_AGENT_MEMORY_SOFT_LIMIT_MB : 0)),
            'cron_scheduler' => (int) (($config['ENV_CRON_MEMORY_SOFT_LIMIT_MB'] ?? null) ?? (defined('ENV_CRON_MEMORY_SOFT_LIMIT_MB') ? ENV_CRON_MEMORY_SOFT_LIMIT_MB : 0)),
            default => (int) (($config['ENV_MEMORY_SOFT_LIMIT_MB'] ?? null) ?? (defined('ENV_MEMORY_SOFT_LIMIT_MB') ? ENV_MEMORY_SOFT_LIMIT_MB : 0)),
        };

        $softLimitBytes = $configuredMb > 0 ? ($configuredMb * 1024 * 1024) : 0;
        $hardLimitBytes = ee_get_runtime_memory_limit_bytes();

        if ($hardLimitBytes > 0) {
            $hardDerivedSoftLimit = max(0, $hardLimitBytes - (16 * 1024 * 1024));
            if ($softLimitBytes <= 0 || $softLimitBytes > $hardDerivedSoftLimit) {
                $softLimitBytes = $hardDerivedSoftLimit;
            }
        }

        return max(0, $softLimitBytes);
    }
}

if (!function_exists('ee_runtime_memory_guard_exceeded')) {
    function ee_runtime_memory_guard_exceeded(string $scope = 'generic', int $reserveBytes = 0, ?array $config = null): bool {
        $softLimit = ee_get_runtime_soft_memory_limit_bytes($scope, $config);
        if ($softLimit <= 0) {
            return false;
        }

        return ee_get_runtime_memory_usage_bytes(true) >= max(0, $softLimit - $reserveBytes);
    }
}

if (!function_exists('ee_get_proto_language_seed')) {
    function ee_get_proto_language_seed(): string {
        static $protoLang = null;

        if (defined('ENV_PROTO_LANGUAGE')) {
            return (string) ENV_PROTO_LANGUAGE;
        }

        if ($protoLang !== null) {
            return $protoLang;
        }

        $config = require __DIR__ . '/configuration.php';
        $protoLang = is_array($config) && !empty($config['ENV_PROTO_LANGUAGE'])
            ? (string) $config['ENV_PROTO_LANGUAGE']
            : 'EN';

        return $protoLang;
    }
}

if (!function_exists('ee_normalize_lang_code')) {
    function ee_normalize_lang_code(string $langCode): string {
        $normalized = strtoupper(trim(pathinfo($langCode, PATHINFO_FILENAME)));
        $normalized = preg_replace('/[^A-Z0-9_-]/', '', $normalized) ?? '';
        return $normalized;
    }
}

if (!function_exists('ee_collect_lang_codes')) {
    function ee_collect_lang_codes(iterable $codes): array {
        $normalized = [];
        foreach ($codes as $code) {
            $code = ee_normalize_lang_code((string) $code);
            if ($code === '' || in_array($code, $normalized, true)) {
                continue;
            }
            $normalized[] = $code;
        }

        return $normalized;
    }
}

if (!function_exists('ee_load_config_value')) {
    function ee_load_config_value(string $key, mixed $default = null): mixed {
        static $rawConfig = null;
        if ($rawConfig === null) {
            $rawConfig = require __DIR__ . '/configuration.php';
            if (!is_array($rawConfig)) {
                $rawConfig = [];
            }
        }

        return array_key_exists($key, $rawConfig) ? $rawConfig[$key] : $default;
    }
}

if (!function_exists('ee_get_interface_lang_codes')) {
    function ee_get_interface_lang_codes(): array {
        static $interfaceLangCodes = null;
        if ($interfaceLangCodes !== null) {
            return $interfaceLangCodes;
        }

        $sitePath = defined('ENV_SITE_PATH')
            ? (string) ENV_SITE_PATH
            : (string) ee_load_config_value('ENV_SITE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
        $langPath = defined('ENV_PATH_LANG')
            ? (string) ENV_PATH_LANG
            : (string) ee_load_config_value('ENV_PATH_LANG', 'inc' . DIRECTORY_SEPARATOR . 'langs' . DIRECTORY_SEPARATOR);

        $langDir = rtrim($sitePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($langPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $files = is_dir($langDir) ? (glob($langDir . '*.php') ?: []) : [];
        $interfaceLangCodes = ee_collect_lang_codes(array_map(
            static fn(string $filePath): string => basename($filePath, '.php'),
            $files
        ));

        if ($interfaceLangCodes === []) {
            $interfaceLangCodes[] = 'EN';
        }

        return $interfaceLangCodes;
    }
}

if (!function_exists('ee_get_content_lang_codes')) {
    function ee_get_content_lang_codes(): array {
        static $contentLangCodes = null;
        if ($contentLangCodes !== null) {
            return $contentLangCodes;
        }

        $rawContentLangs = defined('ENV_CONTENT_LANGS')
            ? ENV_CONTENT_LANGS
            : ee_load_config_value('ENV_CONTENT_LANGS', null);

        if (is_string($rawContentLangs)) {
            $rawContentLangs = preg_split('/[\s,;|]+/', $rawContentLangs, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $contentLangCodes = ee_collect_lang_codes(is_iterable($rawContentLangs) ? $rawContentLangs : []);

        if ($contentLangCodes === []) {
            $protoLanguage = ee_normalize_lang_code(ee_get_proto_language_seed());
            if ($protoLanguage !== '') {
                $contentLangCodes[] = $protoLanguage;
            }
        }

        if ($contentLangCodes === []) {
            $contentLangCodes = ee_get_interface_lang_codes();
        }

        if ($contentLangCodes === []) {
            $contentLangCodes = ['EN'];
        }

        return $contentLangCodes;
    }
}

if (!function_exists('ee_resolve_lang_code_from_allowed')) {
    function ee_resolve_lang_code_from_allowed(array $candidates, array $allowedCodes, string $fallback = ''): string {
        $allowedCodes = ee_collect_lang_codes($allowedCodes);
        if ($allowedCodes === []) {
            $fallback = ee_normalize_lang_code($fallback);
            return $fallback !== '' ? $fallback : 'EN';
        }

        foreach ($candidates as $candidate) {
            $candidate = ee_normalize_lang_code((string) $candidate);
            if ($candidate !== '' && in_array($candidate, $allowedCodes, true)) {
                return $candidate;
            }
        }

        $fallback = ee_normalize_lang_code($fallback);
        if ($fallback !== '' && in_array($fallback, $allowedCodes, true)) {
            return $fallback;
        }

        return (string) ($allowedCodes[0] ?? 'EN');
    }
}

if (!function_exists('ee_get_default_interface_lang_code')) {
    function ee_get_default_interface_lang_code(?string $preferredCode = null): string {
        return ee_resolve_lang_code_from_allowed(
            [
                $preferredCode,
                defined('ENV_DEF_LANG') ? (string) ENV_DEF_LANG : '',
                'EN',
            ],
            ee_get_interface_lang_codes(),
            'EN'
        );
    }
}

if (!function_exists('ee_detect_interface_lang_code')) {
    function ee_detect_interface_lang_code(?string $acceptedLanguages = null): string {
        $acceptedLanguages = $acceptedLanguages ?? (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        $preferred = strtoupper(substr((string) GetClientPreferedLanguage(false, $acceptedLanguages), 0, 2));
        return ee_resolve_lang_code_from_allowed(
            [$preferred, 'EN'],
            ee_get_interface_lang_codes(),
            'EN'
        );
    }
}

if (!function_exists('ee_get_default_content_lang_code')) {
    function ee_get_default_content_lang_code(?string $preferredCode = null): string {
        return ee_resolve_lang_code_from_allowed(
            [
                $preferredCode,
                defined('ENV_PROTO_LANGUAGE') ? (string) ENV_PROTO_LANGUAGE : '',
                defined('ENV_DEF_LANG') ? (string) ENV_DEF_LANG : '',
                'EN',
            ],
            ee_get_content_lang_codes(),
            defined('ENV_PROTO_LANGUAGE') ? (string) ENV_PROTO_LANGUAGE : 'EN'
        );
    }
}

if (!function_exists('ee_get_current_lang_code')) {
    function ee_get_current_lang_code(?string $preferredCode = null): string {
        return ee_resolve_lang_code_from_allowed(
            [
                $preferredCode,
                class_exists('\classes\system\Lang') ? (string) \classes\system\Lang::getCurrentLangCode() : '',
                class_exists('\classes\system\Session') ? (string) \classes\system\Session::get('lang') : '',
                ee_detect_interface_lang_code(),
                'EN',
            ],
            ee_get_interface_lang_codes(),
            'EN'
        );
    }
}

if (!function_exists('ee_get_lang_locale')) {
    function ee_get_lang_locale(?string $langCode = null): string {
        $langCode = ee_get_current_lang_code($langCode);
        $localeMap = [
            'RU' => 'ru-RU',
            'EN' => 'en-US',
            'DE' => 'de-DE',
            'ES' => 'es-ES',
            'FR' => 'fr-FR',
            'IT' => 'it-IT',
            'PT' => 'pt-PT',
            'TR' => 'tr-TR',
        ];

        if (isset($localeMap[$langCode])) {
            return $localeMap[$langCode];
        }

        $primary = strtolower(substr($langCode, 0, 2));
        $region = strtoupper(substr($langCode, 0, 2));
        return $primary . '-' . $region;
    }
}

if (!function_exists('ee_get_lang_html_attr')) {
    function ee_get_lang_html_attr(?string $langCode = null): string {
        $locale = ee_get_lang_locale($langCode);
        $parts = explode('-', $locale);
        return strtolower((string) ($parts[0] ?? 'en'));
    }
}

if (!function_exists('ee_get_lang_bundle_url')) {
    function ee_get_lang_bundle_url(?string $langCode = null): string {
        $langCode = ee_get_current_lang_code($langCode);
        $localPath = (defined('ENV_TMP_PATH') ? rtrim((string) ENV_TMP_PATH, DIRECTORY_SEPARATOR) : '') . DIRECTORY_SEPARATOR . $langCode . '.js';
        $url = rtrim((string) ENV_URL_SITE, '/') . '/uploads/tmp/' . rawurlencode($langCode) . '.js';

        if ($localPath !== DIRECTORY_SEPARATOR && is_file($localPath)) {
            $fileMtime = (int) @filemtime($localPath);
            if ($fileMtime > 0) {
                $url .= '?v=' . $fileMtime;
            }
        }

        return $url;
    }
}

if (!function_exists('checkRedisConnection')) {
    function checkRedisConnection(string $address, int $port, array $config): bool {
        $cacheDir = rtrim((string) ($config['ENV_CACHE_PATH'] ?? ''), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($cacheDir === DIRECTORY_SEPARATOR) {
            return false;
        }
        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
            return false;
        }

        $cacheFile = $cacheDir . 'redis_connection_check.cache';
        $probeTtl = max(0, (int) ($config['ENV_REDIS_CONNECTION_CACHE_TTL'] ?? 300));
        if (file_exists($cacheFile) && ($probeTtl === 0 || (time() - (int) @filemtime($cacheFile)) < $probeTtl)) {
            return (bool) @file_get_contents($cacheFile);
        }

        try {
            if (!class_exists('\Redis')) {
                ee_runtime_write_file($cacheFile, '0', LOCK_EX, 0664, true);
                return false;
            }
            $redis = new \Redis();
            $redis->connect($address, $port);
            $isAvailable = ($redis->ping() == '+PONG');
            ee_runtime_write_file($cacheFile, $isAvailable ? '1' : '0', LOCK_EX, 0664, true);
            return $isAvailable;
        } catch (\RedisException $e) {
            $logMessage = sprintf(
                "{START}\nВремя события: %s\nИнициатор: %s\nРезультат: Ошибка подключения\nДетали: %s\n{END}\n",
                date('Y-m-d H:i:s'),
                'checkRedisConnection',
                'Ошибка подключения к Redis: ' . $e->getMessage()
            );
            $errorLogFile = (string) ($config['ENV_LOGS_PATH'] ?? '') . 'errors' . DIRECTORY_SEPARATOR . date('Y-m-d') . '.txt';
            $errorLogDir = dirname($errorLogFile);
            if (!is_dir($errorLogDir)) {
                @mkdir($errorLogDir, 0775, true);
            }
            ee_runtime_append_managed_log($errorLogFile, $logMessage, FILE_APPEND | LOCK_EX, 0664, false, $config);
            ee_runtime_write_file($cacheFile, '0', LOCK_EX, 0664, true);
            return false;
        }
    }
}

if (!function_exists('GetClientPreferedLanguage')) {
    function GetClientPreferedLanguage(bool $getSortedList = false, string|false $acceptedLanguages = false): string|array {
        $protoLang = ee_get_proto_language_seed();
        if ($acceptedLanguages === false) {
            $acceptedLanguages = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        }
        if (trim($acceptedLanguages) === '') {
            return $getSortedList ? [] : $protoLang;
        }
        preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})*)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $acceptedLanguages, $lang_parse);
        $langs = $lang_parse[1] ?? [];
        $ranks = $lang_parse[4] ?? [];
        if (empty($langs)) {
            return $getSortedList ? [] : $protoLang;
        }
        $lang2pref = array_combine($langs, array_map(fn($rank) => (float) ($rank ?? 1), $ranks));
        uksort($lang2pref, fn($a, $b) => $lang2pref[$b] <=> $lang2pref[$a] ?: strlen($b) <=> strlen($a));
        return $getSortedList ? $lang2pref : (key($lang2pref) ?? strtolower($protoLang));
    }
}

if (!function_exists('ee_load_raw_config')) {
    function ee_load_raw_config(): array {
        $config = require __DIR__ . '/configuration.php';
        if (!is_array($config)) {
            throw new RuntimeException('inc/configuration.php must return config array.');
        }
        return $config;
    }
}

if (!function_exists('ee_finalize_config')) {
    function ee_finalize_config(array $config): array {
        $config['ENV_REQUEST_SCHEME'] = ee_get_request_scheme();
        $config['ENV_REQUEST_HOST'] = ee_get_request_host();
        $config['ENV_DOMEN_PROTOCOL'] = ee_get_effective_site_scheme($config) . '://';
        $config['ENV_DOMEN_NAME'] = ee_get_effective_site_host($config);
        $config['ENV_URL_SITE'] = $config['ENV_DOMEN_PROTOCOL'] . $config['ENV_DOMEN_NAME'];
        $config['ENV_REQUEST_URL_SITE'] = ee_build_base_url($config['ENV_REQUEST_SCHEME'], $config['ENV_REQUEST_HOST']);
        $config['ENV_CANONICAL_URL_SITE'] = ee_build_base_url(
            ee_get_effective_site_scheme($config),
            ee_get_effective_site_host($config)
        );
        $config['ENV_LOGS_PATH'] = $config['ENV_SITE_PATH'] . 'logs' . $config['ENV_DIRSEP'];
        $config['ENV_TMP_PATH'] = $config['ENV_SITE_PATH'] . 'uploads' . $config['ENV_DIRSEP'] . 'tmp' . $config['ENV_DIRSEP'];
        $config['ENV_CACHE_PATH'] = $config['ENV_SITE_PATH'] . 'cache' . $config['ENV_DIRSEP'];
        $config['ENV_EMAIL_TEMPLATE'] = $config['ENV_SITE_PATH'] . 'assets' . $config['ENV_DIRSEP'] . 'emails_templates';
        $config['ENV_CUSTOM_PATH'] = $config['ENV_SITE_PATH'] . 'custom' . $config['ENV_DIRSEP'];

        if ((int) ($config['ENV_CACHE_REDIS'] ?? 0) === 1) {
            $config['ENV_CACHE_BACKEND'] = 'redis';
        }
        if (array_key_exists('ENV_ROUTING_CACHE', $config) && (int) ($config['ENV_ROUTING_CACHE'] ?? 0) === 1) {
            $config['ENV_ROUTING_CACHE_ENABLED'] = 1;
        }

        $config['ENV_CACHE_NAMESPACE'] = ee_get_cache_namespace($config);
        $config['ENV_CACHE_VERSION'] = ee_get_cache_version($config);
        $config['ENV_DEF_LANG'] = ee_detect_interface_lang_code((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));

        $cacheBackend = strtolower((string) ($config['ENV_CACHE_BACKEND'] ?? 'file'));
        $routingCacheBackend = strtolower((string) ($config['ENV_ROUTING_CACHE_BACKEND'] ?? 'file'));
        $shouldProbeRedis = $cacheBackend === 'redis'
            || $routingCacheBackend === 'redis'
            || (int) ($config['ENV_CACHE_REDIS'] ?? 0) === 1
            || (int) ($config['ENV_GUARD_REDIS'] ?? 0) === 1;

        if ($shouldProbeRedis) {
            $isConnect = checkRedisConnection((string) $config['ENV_REDIS_ADDRESS'], (int) $config['ENV_REDIS_PORT'], $config);
            if (!$isConnect) {
                if (($config['ENV_CACHE_BACKEND'] ?? 'file') === 'redis') {
                    $config['ENV_CACHE_BACKEND'] = 'file';
                }
                if (($config['ENV_ROUTING_CACHE_BACKEND'] ?? 'file') === 'redis') {
                    $config['ENV_ROUTING_CACHE_BACKEND'] = 'file';
                }
                $config['ENV_CACHE_REDIS'] = 0;
                $config['ENV_GUARD_REDIS'] = 0;
                $config['ENV_ROUTING_CACHE_ENABLED'] = 0;
            }
        } else {
            $config['ENV_CACHE_REDIS'] = 0;
            $config['ENV_GUARD_REDIS'] = 0;
        }

        $config['ENV_CACHE_REDIS'] = (($config['ENV_CACHE_BACKEND'] ?? 'file') === 'redis') ? 1 : 0;
        $config['ENV_ROUTING_CACHE_ENABLED'] = !empty($config['ENV_ROUTING_CACHE_ENABLED']) ? 1 : 0;
        $config['ENV_ROUTING_CACHE'] = $config['ENV_ROUTING_CACHE_ENABLED']; // legacy alias for older runtime code

        return $config;
    }
}

if (!function_exists('ee_define_config_constants')) {
    function ee_define_config_constants(array $config): void {
        foreach ($config as $name => $val) {
            if (!defined($name)) {
                define($name, $val);
            }
        }
    }
}

if (!function_exists('ee_define_request_snapshot')) {
    function ee_define_request_snapshot(): void {
        if (defined('__REQUEST')) {
            return;
        }
        $inputData = file_get_contents('php://input');
        define('__REQUEST', [
            'input_data' => $inputData,
            '_REQUEST' => $_REQUEST,
            '_GET' => $_GET,
            '_POST' => $_POST,
            '_SERVER' => $_SERVER,
        ]);
    }
}

if (!function_exists('ee_register_shutdown_logger')) {
    function ee_register_shutdown_logger(): void {
        static $registered = false;
        if ($registered) {
            return;
        }

        register_shutdown_function(function (): void {
            if (!defined('ENV_FATAL_ERROR_LOGGING') || !ENV_FATAL_ERROR_LOGGING) {
                return;
            }

            $error = error_get_last();
            if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            if (class_exists(\classes\system\Logger::class)) {
                try {
                    \classes\system\Logger::logFatalShutdownError($error);
                    return;
                } catch (\Throwable) {
                    // fallback below
                }
            }

            $formattedError = sprintf(
                "Date: %s\nMessage: %s in %s on line %s\n\n",
                date('d-m-Y H:i:s'),
                $error['message'],
                $error['file'],
                $error['line']
            );
            ee_runtime_append_managed_log(ENV_LOGS_PATH . 'fatal_errors.txt', $formattedError, FILE_APPEND | LOCK_EX);
        });

        $registered = true;
    }
}

if (!function_exists('ee_apply_runtime_robots_policy')) {
    function ee_apply_runtime_robots_policy(): void {
        static $applied = false;
        if ($applied) {
            return;
        }

        if (defined('ENV_SITE_INDEX') && ENV_SITE_INDEX !== 'ALL' && defined('ENV_SITE_PATH')) {
            ee_runtime_write_file(ENV_SITE_PATH . 'robots.txt', 'User-agent:* ' . PHP_EOL . 'Disallow: /', LOCK_EX, 0664, true);
        }

        $applied = true;
    }
}

if (!function_exists('ee_bootstrap_prepare_core')) {
    function ee_bootstrap_prepare_core(): array {
        static $preparedConfig = null;
        if (is_array($preparedConfig)) {
            return $preparedConfig;
        }

        $config = ee_finalize_config(ee_load_raw_config());
        ee_apply_runtime_memory_limit($config);
        if (($config['ENV_DEF_LANG'] ?? '') === 'RU') {
            date_default_timezone_set('Europe/Moscow');
        }

        ee_define_config_constants($config);
        require_once __DIR__ . '/startup.php';
        ee_apply_canonical_redirect($config);
        ee_define_request_snapshot();
        ee_register_shutdown_logger();
        ee_apply_runtime_robots_policy();

        $preparedConfig = $config;
        return $preparedConfig;
    }
}

if (!function_exists('ee_log_custom_bootstrap_error')) {
    function ee_log_custom_bootstrap_error(string $message, string $context, array $details = []): void {
        if (class_exists(\classes\system\ErrorLogger::class)) {
            new \classes\system\ErrorLogger($message, $context, 'custom', $details);
            return;
        }
        error_log($message . ' ' . json_encode($details, JSON_UNESCAPED_UNICODE));
    }
}

if (!function_exists('ee_register_custom_autoload')) {
    function ee_register_custom_autoload(): void {
        static $registered = false;
        if ($registered) {
            return;
        }

        $customSrcPath = ENV_CUSTOM_PATH . 'src' . ENV_DIRSEP;
        if (is_dir($customSrcPath)) {
            \AutoloadManager::addNamespace('custom', $customSrcPath);
        }

        $registered = true;
    }
}

if (!function_exists('ee_add_custom_hook')) {
    function ee_add_custom_hook(string $key, $callback, int $priority = 10): bool {
        return \classes\system\Hook::add($key, $callback, $priority, 'custom', 'custom');
    }
}

if (!function_exists('ee_include_custom_file')) {
    function ee_include_custom_file(string $filePath, string $context): void {
        if (!is_file($filePath)) {
            return;
        }

        try {
            require_once $filePath;
        } catch (\Throwable $e) {
            ee_log_custom_bootstrap_error(
                'Custom bootstrap error: ' . $e->getMessage(),
                $context,
                [
                    'file_path' => $filePath,
                    'trace' => $e->getTraceAsString(),
                ]
            );
        }
    }
}

if (!function_exists('ee_bootstrap_runtime')) {
    function ee_bootstrap_runtime(): void {
        static $booted = false;
        if ($booted) {
            return;
        }

        ee_bootstrap_prepare_core();
        \AutoloadManager::init();
        if (class_exists(\classes\system\Logger::class)) {
            \classes\system\Logger::bootstrap();
        }
        ee_register_custom_autoload();
        require_once ENV_SITE_PATH . 'inc' . DIRECTORY_SEPARATOR . 'hooks.php';
        ee_include_custom_file(ENV_CUSTOM_PATH . 'hooks.php', __FUNCTION__ . ':custom_hooks');
        ee_include_custom_file(ENV_CUSTOM_PATH . 'bootstrap.php', __FUNCTION__ . ':custom_bootstrap');
        $booted = true;
    }
}

if (!function_exists('ee_bootstrap_preload')) {
    function ee_bootstrap_preload(): void {
        static $prepared = false;
        if ($prepared) {
            return;
        }

        ee_bootstrap_prepare_core();
        \AutoloadManager::init();
        if (class_exists(\classes\system\Logger::class)) {
            \classes\system\Logger::bootstrap();
        }
        ee_register_custom_autoload();
        require_once ENV_SITE_PATH . 'inc' . DIRECTORY_SEPARATOR . 'hooks.php';
        ee_include_custom_file(ENV_CUSTOM_PATH . 'hooks.php', __FUNCTION__ . ':custom_hooks');
        $prepared = true;
    }
}

ee_bootstrap_prepare_core();
