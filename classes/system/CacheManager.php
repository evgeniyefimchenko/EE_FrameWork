<?php

namespace classes\system;

/**
 * Управление HTML/block cache проекта с безопасной изоляцией по namespace/version.
 */
class CacheManager {

    private const SECTION_HTML = 'html';
    private const SECTION_BLOCK = 'block';
    private const SECTION_ROUTE = 'route';
    private const REDIS_NAMESPACE_PREFIX = 'ee_cache';

    /**
     * @var string $cachePath Базовый путь к директории кэша
     */
    private string $cachePath;

    /**
     * @var int $cacheDuration Время жизни кэша в секундах
     */
    private int $cacheDuration;

    /**
     * @var \Redis|null $redisClient Клиент Redis, если backend = redis
     */
    private ?\Redis $redisClient = null;

    /**
     * @var bool $useRedis Использовать ли Redis для кэширования
     */
    private bool $useRedis = false;

    public function __construct(int $cacheDuration = 3600) {
        $this->cachePath = rtrim((string) ENV_CACHE_PATH, '/\\') . ENV_DIRSEP;
        $this->cacheDuration = $cacheDuration;
        $this->bootBackend();
    }

    private function bootBackend(): void {
        $this->useRedis = self::resolveBackend() === 'redis' && class_exists('\Redis');
        if (!$this->useRedis) {
            return;
        }

        try {
            $this->redisClient = new \Redis();
            $this->redisClient->connect(ENV_REDIS_ADDRESS, ENV_REDIS_PORT);
        } catch (\Throwable $e) {
            $this->useRedis = false;
            $this->redisClient = null;
            Logger::warning('cache_error', 'Redis connection failed', [
                'message' => $e->getMessage(),
                'address' => ENV_REDIS_ADDRESS,
                'port' => ENV_REDIS_PORT,
            ], [
                'initiator' => __METHOD__,
                'details' => $e->getMessage(),
                'include_trace' => false,
            ]);
        }
    }

    public static function resolveBackend(): string {
        $backend = defined('ENV_CACHE_BACKEND') ? strtolower((string) ENV_CACHE_BACKEND) : '';
        if ($backend === 'redis') {
            return 'redis';
        }
        if ($backend === 'file') {
            return 'file';
        }

        return (defined('ENV_CACHE_REDIS') && (int) ENV_CACHE_REDIS === 1) ? 'redis' : 'file';
    }

    private static function getNamespace(): string {
        $namespace = defined('ENV_CACHE_NAMESPACE') ? (string) ENV_CACHE_NAMESPACE : 'ee-site';
        $namespace = preg_replace('~[^a-z0-9._-]+~i', '-', strtolower($namespace)) ?? 'ee-site';
        $namespace = trim($namespace, '-');
        return $namespace !== '' ? $namespace : 'ee-site';
    }

    private static function getVersion(): string {
        $version = defined('ENV_CACHE_VERSION') ? (string) ENV_CACHE_VERSION : 'v1';
        $version = preg_replace('~[^a-z0-9._-]+~i', '-', strtolower($version)) ?? 'v1';
        $version = trim($version, '-');
        return $version !== '' ? $version : 'v1';
    }

    private static function buildRedisPrefix(string $section, bool $allVersions = false): string {
        $parts = [
            self::REDIS_NAMESPACE_PREFIX,
            self::getNamespace(),
        ];
        if (!$allVersions) {
            $parts[] = self::getVersion();
        }
        $parts[] = $section;
        return implode(':', $parts) . ':';
    }

    private static function buildRedisMatch(string $section, bool $allVersions = false): string {
        return $allVersions
            ? self::REDIS_NAMESPACE_PREFIX . ':' . self::getNamespace() . ':*:' . $section . ':*'
            : self::buildRedisPrefix($section, false) . '*';
    }

    private function buildRedisKey(string $section, string $cacheKey): string {
        return self::buildRedisPrefix($section, false) . $cacheKey;
    }

    private static function getNamespacedDirectory(string $section): string {
        return rtrim((string) ENV_CACHE_PATH, '/\\') . ENV_DIRSEP
            . trim($section, '/\\') . ENV_DIRSEP
            . self::getNamespace() . '-' . self::getVersion() . ENV_DIRSEP;
    }

    private static function getSectionRoot(string $section): string {
        return rtrim((string) ENV_CACHE_PATH, '/\\') . ENV_DIRSEP . trim($section, '/\\') . ENV_DIRSEP;
    }

    private function getCacheFilePath(string $section, string $cacheKey): string {
        return self::getNamespacedDirectory($section) . $cacheKey . '.cache';
    }

    private function generateCacheKey(string $param, array $extraContext = []): string {
        $payload = [
            'scope' => $this->getRequestCacheScope(),
            'lang' => $this->getLanguageContext(),
            'param' => $param,
            'get' => $_GET ?? [],
            'context' => $extraContext,
        ];

        return md5((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function getRequestCacheScope(): string {
        $scheme = defined('ENV_REQUEST_SCHEME')
            ? (string) ENV_REQUEST_SCHEME
            : (function_exists('ee_get_request_scheme') ? ee_get_request_scheme() : 'http');
        $host = defined('ENV_REQUEST_HOST')
            ? (string) ENV_REQUEST_HOST
            : (function_exists('ee_get_request_host') ? ee_get_request_host() : (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));

        return strtolower($scheme) . '://' . strtolower($host);
    }

    private function getLanguageContext(): string {
        $lang = null;
        if (class_exists(Session::class)) {
            $lang = Session::get('lang');
        }

        $lang = strtoupper(trim((string) ($lang ?: (defined('ENV_DEF_LANG') ? ENV_DEF_LANG : 'RU'))));
        return $lang !== '' ? $lang : 'RU';
    }

    public function isCached(string $param): string|false {
        $cacheKey = $this->generateCacheKey($param);

        if ($this->useRedis && $this->redisClient) {
            $redisKey = $this->buildRedisKey(self::SECTION_HTML, $cacheKey);
            if ($this->redisClient->exists($redisKey)) {
                return (string) $this->redisClient->get($redisKey);
            }
            return false;
        }

        $cacheFile = $this->getCacheFilePath(self::SECTION_HTML, $cacheKey);
        if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < $this->cacheDuration) {
            return $cacheFile;
        }

        return false;
    }

    public function getCache(string $cacheKey): string {
        if ($this->useRedis && $this->redisClient) {
            return $cacheKey;
        }

        return (string) @file_get_contents($cacheKey);
    }

    public function setCache(string $content, string $param): void {
        $cacheKey = $this->generateCacheKey($param);

        if ($this->useRedis && $this->redisClient) {
            $this->redisClient->set($this->buildRedisKey(self::SECTION_HTML, $cacheKey), $content, $this->cacheDuration);
            return;
        }

        $cacheFile = $this->getCacheFilePath(self::SECTION_HTML, $cacheKey);
        if (SysClass::createDirectoriesForFile($cacheFile)) {
            file_put_contents($cacheFile, $content, LOCK_EX);
        }
    }

    public function clearCache(string $param): void {
        $cacheKey = $this->generateCacheKey($param);

        if ($this->useRedis && $this->redisClient) {
            $this->redisClient->del($this->buildRedisKey(self::SECTION_HTML, $cacheKey));
            return;
        }

        $cacheFile = $this->getCacheFilePath(self::SECTION_HTML, $cacheKey);
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    public function clearAllCache(): void {
        self::clearSection(self::SECTION_HTML);
        self::clearSection(self::SECTION_BLOCK);
        self::clearSection(self::SECTION_ROUTE);
    }

    public static function clearHtmlCache(): void {
        self::clearSection(self::SECTION_HTML);
        self::clearSection(self::SECTION_BLOCK);
    }

    public static function clearBlockCache(): void {
        self::clearSection(self::SECTION_BLOCK);
    }

    public static function resetRedisAvailabilityProbe(): bool {
        $probeFile = rtrim((string) ENV_CACHE_PATH, '/\\') . ENV_DIRSEP . 'redis_connection_check.cache';
        if (!is_file($probeFile)) {
            return true;
        }

        return @unlink($probeFile);
    }

    public function logCacheFiles(): array {
        if ($this->useRedis && $this->redisClient) {
            return $this->logRedisCacheKeys();
        }

        return $this->logFileCacheEntries();
    }

    private function logRedisCacheKeys(): array {
        $patterns = [
            self::buildRedisMatch(self::SECTION_HTML, true),
            self::buildRedisMatch(self::SECTION_BLOCK, true),
            self::REDIS_NAMESPACE_PREFIX . ':' . self::getNamespace() . ':*:' . self::SECTION_ROUTE . ':*',
        ];

        $log = [];
        foreach ($patterns as $pattern) {
            foreach ($this->scanRedisKeys($pattern) as $key) {
                $value = (string) $this->redisClient->get($key);
                $log[] = [
                    'key' => $key,
                    'size' => strlen($value),
                    'created_at' => 'N/A',
                ];
            }
        }

        return $log;
    }

    private function logFileCacheEntries(): array {
        $directories = [
            self::getSectionRoot(self::SECTION_HTML),
            self::getSectionRoot(self::SECTION_BLOCK),
            self::getSectionRoot(self::SECTION_ROUTE),
        ];

        $log = [];
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $item) {
                if (!$item->isFile() || $item->getExtension() !== 'cache') {
                    continue;
                }

                $log[] = [
                    'file' => $item->getPathname(),
                    'size' => $item->getSize(),
                    'created_at' => date('Y-m-d H:i:s', $item->getMTime()),
                ];
            }
        }

        return $log;
    }

    public function cacheBlock(string $blockId, string $content): void {
        $cacheKey = $this->generateCacheKey('block:' . $blockId, ['block_id' => $blockId]);

        if ($this->useRedis && $this->redisClient) {
            $this->redisClient->set($this->buildRedisKey(self::SECTION_BLOCK, $cacheKey), $content, $this->cacheDuration);
            return;
        }

        $cacheFile = $this->getCacheFilePath(self::SECTION_BLOCK, $cacheKey);
        if (SysClass::createDirectoriesForFile($cacheFile)) {
            file_put_contents($cacheFile, $content, LOCK_EX);
        }
    }

    public function getCachedBlock(string $blockId): string|false {
        $cacheKey = $this->generateCacheKey('block:' . $blockId, ['block_id' => $blockId]);

        if ($this->useRedis && $this->redisClient) {
            $value = $this->redisClient->get($this->buildRedisKey(self::SECTION_BLOCK, $cacheKey));
            return $value !== false ? (string) $value : false;
        }

        $cacheFile = $this->getCacheFilePath(self::SECTION_BLOCK, $cacheKey);
        if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < $this->cacheDuration) {
            return (string) @file_get_contents($cacheFile);
        }

        return false;
    }

    private static function clearSection(string $section): void {
        if (self::resolveBackend() === 'redis' && class_exists('\Redis')) {
            self::clearRedisSection($section);
        }

        self::clearSectionDirectory(self::getSectionRoot($section));
    }

    private static function clearRedisSection(string $section): void {
        try {
            $redis = new \Redis();
            $redis->connect(ENV_REDIS_ADDRESS, ENV_REDIS_PORT);
        } catch (\Throwable $e) {
            Logger::warning('cache_error', 'Redis section clear failed', [
                'message' => $e->getMessage(),
                'section' => $section,
            ], [
                'initiator' => __METHOD__,
                'details' => $e->getMessage(),
                'include_trace' => false,
            ]);
            return;
        }

        $patterns = [$section === self::SECTION_ROUTE
            ? self::REDIS_NAMESPACE_PREFIX . ':' . self::getNamespace() . ':*:' . self::SECTION_ROUTE . ':*'
            : self::buildRedisMatch($section, true)];

        foreach ($patterns as $pattern) {
            $iterator = null;
            do {
                $keys = $redis->scan($iterator, $pattern, 500);
                if (is_array($keys) && $keys !== []) {
                    $redis->del($keys);
                }
            } while ($iterator !== 0 && $iterator !== null);
        }
    }

    private function scanRedisKeys(string $pattern): array {
        if (!$this->redisClient) {
            return [];
        }

        $keys = [];
        $iterator = null;
        do {
            $chunk = $this->redisClient->scan($iterator, $pattern, 500);
            if (is_array($chunk) && $chunk !== []) {
                array_push($keys, ...$chunk);
            }
        } while ($iterator !== 0 && $iterator !== null);

        return $keys;
    }

    private static function clearSectionDirectory(string $directory): void {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }
            @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
