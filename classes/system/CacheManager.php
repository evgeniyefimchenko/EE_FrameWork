<?php

namespace classes\system;

/**
 * Класс CacheManager
 * Этот класс предоставляет методы для управления кешем сайта, поддерживая кеширование на основе файловой системы и Redis
 */
class CacheManager {

    /**
     * @var string $cachePath Путь к директории для кеша
     */
    private $cachePath;

    /**
     * @var int $cacheDuration Время жизни кеша в секундах
     */
    private $cacheDuration;

    /**
     * @var \Redis|null $redisClient Клиент Redis (если используется Redis)
     */
    private $redisClient = null;

    /**
     * @var bool $useRedis Использовать ли Redis для кеширования
     */
    private $useRedis = false;

    /**
     * @var array $parametersLayout Параметры из контроллера
     */
    private $parametersLayout = [];
    
    /**
     * Конструктор класса CacheManager
     * @param int $cacheDuration Время жизни кеша в секундах (по умолчанию 3600 секунд)
     */
    public function __construct(int $cacheDuration = 3600) {
        $this->cachePath = ENV_CACHE_PATH;
        $this->cacheDuration = $cacheDuration;        
        
        // Проверка на использование Redis
        if (ENV_CACHE_REDIS == 1 && class_exists('\Redis')) {
            $this->useRedis = true;
            try {
                $this->redisClient = new \Redis();
                $this->redisClient->connect(ENV_REDIS_ADDRESS, ENV_REDIS_PORT);
            } catch (\Exception $e) {
                // Если подключение к Redis не удалось, отключаем его использование
                $this->useRedis = false;
                $this->redisClient = null;
                error_log("Redis connection failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Генерация уникального ключа для кеша на основе URL и параметров запроса
     * @param string $param Уникальный ключ для блока кеширования
     * @return string Возвращает хешированный ключ для идентификации кеша
     */
    private function generateCacheKey(string $param): string {
        $key = $param;
        $key .= isset($_GET) ? json_encode($_GET) : '';
        return md5($key);
    }

    /**
     * Проверка наличия актуального кеша
     * @param string $param Уникальный ключ для блока кеширования
     * @return string|false Путь к кеш-файлу или содержимое из Redis, если существует актуальный кеш
     */
    public function isCached(string $param): string|false {
        $cacheKey = $this->generateCacheKey($param);
        if ($this->useRedis && $this->redisClient) {
            if ($this->redisClient->exists($cacheKey)) {
                return $this->redisClient->get($cacheKey);
            }
        } else {
            $cacheFile = $this->cachePath . $cacheKey . '.cache';
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->cacheDuration) {
                return $cacheFile;
            }
        }        
        return false;
    }

    /**
     * Получение данных из кеша
     * @param string $cacheKey Путь к файлу кеша или ключ для Redis
     * @return string Содержимое кеша
     */
    public function getCache(string $cacheKey): string {
        if ($this->useRedis && $this->redisClient) {
            return $cacheKey;  // В случае с Redis возвращаем кеш сразу
        }        
        return file_get_contents($cacheKey);  // Для файловой системы читаем содержимое файла
    }

    /**
     * Запись данных в кеш
     * @param string $content Данные для кеширования
     * @param string $param Уникальный ключ для блока кеширования
     * @return void
     */
    public function setCache(string $content, string $param): void {
        $cacheKey = $this->generateCacheKey($param);        
        if ($this->useRedis && $this->redisClient) {
            $this->redisClient->set($cacheKey, $content, $this->cacheDuration);  // Кешируем в Redis
        } else {
            $cacheFile = $this->cachePath . $cacheKey . '.cache';
            if (SysClass::createDirectoriesForFile($cacheFile)) {
                file_put_contents($cacheFile, $content);  // Кешируем в файловую систему
            }
        }
    }

    /**
     * Очистка кеша для текущего запроса
     * @param string $param Уникальный ключ для блока кеширования
     * @return void
     */
    public function clearCache(string $param): void {
        $cacheKey = $this->generateCacheKey($param);        
        if ($this->useRedis && $this->redisClient) {
            $this->redisClient->del($cacheKey);
        } else {
            $cacheFile = $this->cachePath . DIRECTORY_SEPARATOR . $cacheKey . '.cache';
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        }
    }

    /**
     * Полная очистка кеша
     * @return void
     */
    public function clearAllCache(): void {
        if ($this->useRedis && $this->redisClient) {
            $this->redisClient->flushAll();
        } else {
            array_map('unlink', glob($this->cachePath . DIRECTORY_SEPARATOR . '*.cache'));
        }
    }

    /**
     * Логирование файлов кеша
     * @return array Лог-файл с информацией о кешированных данных
     */
    public function logCacheFiles(): array {
        if ($this->useRedis && $this->redisClient) {
            // Логирование для Redis
            $keys = $this->redisClient->keys('*');
            $log = [];
            foreach ($keys as $key) {
                $log[] = [
                    'key' => $key,
                    'size' => strlen($this->redisClient->get($key)),
                    'created_at' => 'N/A',  // Redis не предоставляет время создания
                ];
            }
            return $log;
        } else {
            // Логирование для файловой системы
            $files = glob($this->cachePath . DIRECTORY_SEPARATOR . '*.cache');
            $log = [];
            foreach ($files as $file) {
                $log[] = [
                    'file' => $file,
                    'size' => filesize($file),
                    'created_at' => date('Y-m-d H:i:s', filemtime($file)),
                ];
            }
            return $log;
        }
    }

    /**
     * Кеширование блоков страницы
     * @param string $blockId Уникальный идентификатор блока
     * @param string $content Контент блока
     * @return void
     */
    public function cacheBlock(string $blockId, string $content): void {
        $cacheKey = md5($blockId);        
        if ($this->useRedis && $this->redisClient) {
            $this->redisClient->set($cacheKey, $content, $this->cacheDuration);
        } else {
            $cacheFile = $this->cachePath . DIRECTORY_SEPARATOR . $cacheKey . '.cache';
            file_put_contents($cacheFile, $content);
        }
    }

    /**
     * Получение кешированного блока
     * @param string $blockId Уникальный идентификатор блока
     * @return string|false Кешированный блок или false
     */
    public function getCachedBlock(string $blockId): string|false {
        $cacheKey = md5($blockId);        
        if ($this->useRedis && $this->redisClient) {
            return $this->redisClient->get($cacheKey);
        } else {
            $cacheFile = $this->cachePath . DIRECTORY_SEPARATOR . $cacheKey . '.cache';
            if (file_exists($cacheFile)) {
                return file_get_contents($cacheFile);
            }
        }        
        return false;
    }
}