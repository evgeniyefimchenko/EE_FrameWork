<?php

namespace classes\system;

use classes\system\SysClass;

/**
 * Роутинг проекта
 */
class Router {

    /**
     * Путь к контроллерам, задаётся в головном файле INDEX
     */
    private string $path;

    /**
     * Экземпляр Redis для кеширования
     */
    private ?\Redis $redis = null;

    /**
     * Использовать ли route cache для текущего запроса.
     */
    private bool $routeCacheEnabled = false;

    /**
     * Backend для route cache: file|redis
     */
    private string $routeCacheBackend = 'file';

    /**
     * Устанавливает путь к контроллерам, задаётся в головном файле INDEX
     * @param string $path Путь к контроллерам
     */
    public function setPath(string $path): void {
        $resolvedPath = realpath($path);
        $path = ($resolvedPath !== false ? $resolvedPath : $path);
        $path = rtrim($path, '/\\') . ENV_DIRSEP;

        if (!is_dir($path)) {
            Logger::critical('router_error', 'Указана неверная папка для контроллеров: `' . $path . '`', ['path' => $path], [
                'initiator' => __FUNCTION__,
                'details' => $path,
            ]);
            SysClass::handleRedirect(500);
            exit;
        }

        $this->path = $path;
    }

    /**
     * Инициализирует кеширование, если оно включено
     */
    private function initCache(): void {
        $this->routeCacheEnabled = self::isRouteCacheEnabled();
        $this->routeCacheBackend = self::getRouteCacheBackend();

        if (!$this->routeCacheEnabled) {
            return;
        }

        if ($this->routeCacheBackend === 'redis') {
            $this->redis = new \Redis();
            try {
                if (!$this->redis->connect(ENV_REDIS_ADDRESS, ENV_REDIS_PORT)) {
                    throw new \RuntimeException('Не удалось подключиться к Redis');
                }
            } catch (\Throwable $e) {
                Logger::critical('router_error', 'Не удалось подключиться к Redis', [
                    'message' => $e->getMessage(),
                ], [
                    'initiator' => __FUNCTION__,
                    'details' => $e->getMessage(),
                ]);
                SysClass::handleRedirect(500);
                exit;
            }
        } else {
            $routeCacheDir = self::getRouteCacheDirectory();
            if (!is_dir($routeCacheDir)) {
                if (!@mkdir($routeCacheDir, 0755, true) && !is_dir($routeCacheDir)) {
                    Logger::critical('router_error', 'Не удалось создать директорию для route cache: ' . $routeCacheDir, ['path' => $routeCacheDir], [
                        'initiator' => __FUNCTION__,
                        'details' => $routeCacheDir,
                    ]);
                    SysClass::handleRedirect(500);
                    exit;
                }
            }
        }
    }

    /**
     * Получает данные из кеша
     * @param string $key Ключ кеша
     * @return mixed Данные из кеша или null, если данные отсутствуют
     */
    private function getCache(string $key) {
        if (!$this->routeCacheEnabled) {
            return null;
        }

        if ($this->routeCacheBackend === 'redis') {
            return $this->redis?->get(self::buildRedisKey($key));
        }

        $cacheFile = self::getRouteCacheDirectory() . $key . '.cache';
        if (is_file($cacheFile)) {
            return file_get_contents($cacheFile);
        }

        return null;
    }

    /**
     * Сохраняет данные в кеш
     * @param string $key Ключ кеша
     * @param mixed $data Данные для кеширования
     */
    private function setCache(string $key, $data): void {
        if (!$this->routeCacheEnabled) {
            return;
        }

        if ($this->routeCacheBackend === 'redis') {
            $this->redis?->set(self::buildRedisKey($key), $data);
            return;
        }

        $cacheFile = self::getRouteCacheDirectory() . $key . '.cache';
        SysClass::createDirectoriesForFile($cacheFile);
        file_put_contents($cacheFile, $data, LOCK_EX);
    }

    /**
     * Определяет файл контроллера, имя класса, метод и аргументы на основе URL
     * Использует кеширование для ускорения повторных запросов
     * @param string|null &$file        Полный путь к файлу контроллера
     * @param string|null &$controllerName Имя контроллера (базовое, например 'index' или 'users')
     * @param string|null &$action      Имя метода (action)
     * @param array|null  &$args        Массив аргументов для метода
     * @return void
     */
    private function getController(?string &$file, ?string &$controllerName, ?string &$action, ?array &$args): void {
        $routeRaw = $_GET['route'] ?? 'index';
        $cacheKey = md5($routeRaw);
        $cachedData = $this->getCache($cacheKey);

        if ($cachedData) {
            $data = json_decode($cachedData, true);
            // Добавлена проверка is_string для $data['file'] на всякий случай
            if (isset($data['file'], $data['controllerName'], $data['action'], $data['args']) && is_string($data['file']) && is_readable($data['file'])) {
                $file = $data['file'];
                $controllerName = $data['controllerName'];
                $action = $data['action'];
                $args = $data['args'];
                return;
            }
        }

        $routeFiltered = filter_var($routeRaw, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ($routeRaw !== $routeFiltered) {
             Logger::warning('router_error', 'Ошибка безопасности при фильтрации маршрута: ' . $routeRaw, ['route' => $routeRaw], [
                 'initiator' => __FUNCTION__,
                 'details' => $routeRaw,
             ]);
             SysClass::handleRedirect(400);
             exit;
        }

        // Нормализуем ПОСЛЕ определения, был ли запрос пустым/index
        $isRootRequest = ($routeFiltered === '' || $routeFiltered === 'index');
        $routeNormalized = $isRootRequest ? '' : $this->normalizeRoute($routeFiltered);

        // Инициализация переменных
        $basePath = $this->path; // Базовый путь к папке app
        $currentPath = $basePath;
        $controllerFileFound = false;
        $controllerName = 'index';
        $action = 'index';
        $args = [];
        $file = '';

        if ($routeNormalized === '') {
             // --- Явная обработка корневого маршрута ('') ---
             $currentPath .= 'index' . ENV_DIRSEP; // Переходим в папку /app/index/
             $file = $currentPath . 'index.php'; // Файл /app/index/index.php
             $controllerName = 'index'; // Имя контроллера по умолчанию для папки index
             // action и args уже 'index' и [] по умолчанию
        } else {
            // --- Обработка НЕ корневых маршрутов ---
            $parts = explode('/', $routeNormalized);
            $firstSegment = $parts[0] ?? '';

            if ($firstSegment !== '') {
                $potentialPathDir = rtrim($basePath, ENV_DIRSEP) . ENV_DIRSEP . $firstSegment;
                $potentialPathFile = $potentialPathDir . '.php';

                if (is_dir($potentialPathDir)) {
                    // --- Первый сегмент - это ПАПКА (например, /admin/...) ---
                    $currentPath = $potentialPathDir . ENV_DIRSEP;
                    array_shift($parts);
                    // ... (логика поиска контроллера/action ВНУТРИ папки - остается как была) ...
                    while (!empty($parts)) {
                        $segment = $parts[0];
                        if ($segment === '') { array_shift($parts); continue; }
                        $potentialSubPathDir = rtrim($currentPath, ENV_DIRSEP) . ENV_DIRSEP . $segment;
                        $potentialSubPathFile = $potentialSubPathDir . '.php';
                        if (is_dir($potentialSubPathDir)) {
                            $currentPath = $potentialSubPathDir . ENV_DIRSEP;
                            array_shift($parts);
                        } elseif (is_file($potentialSubPathFile)) {
                            $controllerName = $segment;
                            $file = $potentialSubPathFile;
                            $controllerFileFound = true;
                            array_shift($parts);
                            break;
                        } else { break; }
                    }
                    if (!$controllerFileFound) {
                        $file = rtrim($currentPath, ENV_DIRSEP) . ENV_DIRSEP . 'index.php';
                        $controllerName = 'index'; // Имя по умолчанию для папки
                    }
                    // Определяем action/args из оставшихся $parts
                    if (!empty($parts)) {
                        $potentialAction = array_shift($parts);
                        if (!empty($potentialAction) && !is_numeric($potentialAction) && preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $potentialAction)) {
                           $action = $potentialAction; $args = $parts;
                        } else {
                           if (!empty($potentialAction)) array_unshift($parts, $potentialAction);
                           $action = 'index'; $args = $parts;
                        }
                    } else { $action = 'index'; $args = []; }

                } elseif (is_file($potentialPathFile)) {
                    // --- Первый сегмент - это ФАЙЛ контроллера в app/ ---
                    $controllerName = $firstSegment;
                    $file = $potentialPathFile;
                    array_shift($parts);
                    // Определяем action/args
                    if (!empty($parts)) {
                         $potentialAction = array_shift($parts);
                          if (!empty($potentialAction) && !is_numeric($potentialAction) && preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $potentialAction)) {
                              $action = $potentialAction; $args = $parts;
                          } else {
                              if (!empty($potentialAction)) array_unshift($parts, $potentialAction);
                              $action = 'index'; $args = $parts;
                          }
                    } else { $action = 'index'; $args = []; }

                } else {
                    // --- Первый сегмент НЕ папка и НЕ файл в app/ => Считаем ACTION для контроллера по УМОЛЧАНИЮ ---
                    $currentPath .= 'index' . ENV_DIRSEP; // Путь к контроллеру по умолчанию
                    $file = $currentPath . 'index.php';    // Файл контроллера по умолчанию
                    $controllerName = 'index';             // Имя контроллера по умолчанию
                    $action = $firstSegment;               // Первый сегмент становится action
                    array_shift($parts);                   // Убираем action из оставшихся частей
                    $args = $parts;                        // Остальное - аргументы

                     // Проверка валидности action
                     if (empty($action) || is_numeric($action) || !preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $action)) {
                          array_unshift($args, $action); // Если невалидный, кладем в начало аргументов
                          $action = 'index';             // Сам action сбрасываем на index
                     }
                }
            } else {
                 // Случай пустого $parts после нормализации (маловероятен, но для полноты)
                 $currentPath .= 'index' . ENV_DIRSEP;
                 $file = $currentPath . 'index.php';
                 $controllerName = 'index';
                 $action = 'index';
                 $args = [];
            }
        } // --- Конец обработки не корневых маршрутов ---

        // Финальная проверка читаемости файла
        if (empty($file) || !is_readable($file)) {
             Logger::warning('router_error', 'Файл контроллера не найден или недоступен для чтения', [
                 'file' => $file,
                 'route_raw' => $routeRaw,
                 'route_normalized' => $routeNormalized,
             ], [
                 'initiator' => __FUNCTION__,
                 'details' => $file,
             ]);
             SysClass::handleRedirect(404);
             exit;
        }

        // Сохраняем в кеш
        $this->setCache($cacheKey, json_encode([
            'file' => $file,
            'controllerName' => $controllerName, // Имя базового контроллера (index или users)
            'action' => $action,
            'args' => $args
        ]));
    }

    public static function clearRouteCache(): void {
        self::clearRouteCacheFiles();
        if (self::getRouteCacheBackend() === 'redis' && class_exists('\Redis')) {
            self::clearRouteCacheRedis();
        }
    }

    public static function isRouteCacheEnabled(): bool {
        if (defined('ENV_ROUTING_CACHE_ENABLED')) {
            return (bool) ENV_ROUTING_CACHE_ENABLED;
        }

        return defined('ENV_ROUTING_CACHE') ? (bool) ENV_ROUTING_CACHE : false;
    }

    public static function getRouteCacheBackend(): string {
        $backend = defined('ENV_ROUTING_CACHE_BACKEND') ? strtolower((string) ENV_ROUTING_CACHE_BACKEND) : '';
        if ($backend === 'redis') {
            return 'redis';
        }
        return 'file';
    }

    private static function getCacheNamespace(): string {
        $namespace = defined('ENV_CACHE_NAMESPACE') ? (string) ENV_CACHE_NAMESPACE : 'ee-site';
        $namespace = preg_replace('~[^a-z0-9._-]+~i', '-', strtolower($namespace)) ?? 'ee-site';
        $namespace = trim($namespace, '-');
        return $namespace !== '' ? $namespace : 'ee-site';
    }

    private static function getCacheVersion(): string {
        $version = defined('ENV_CACHE_VERSION') ? (string) ENV_CACHE_VERSION : 'v1';
        $version = preg_replace('~[^a-z0-9._-]+~i', '-', strtolower($version)) ?? 'v1';
        $version = trim($version, '-');
        return $version !== '' ? $version : 'v1';
    }

    private static function getRouteCacheDirectory(): string {
        return rtrim((string) ENV_CACHE_PATH, '/\\') . ENV_DIRSEP
            . 'route' . ENV_DIRSEP
            . self::getCacheNamespace() . '-' . self::getCacheVersion() . ENV_DIRSEP;
    }

    private static function buildRedisKey(string $cacheKey): string {
        return 'ee_cache:' . self::getCacheNamespace() . ':' . self::getCacheVersion() . ':route:' . $cacheKey;
    }

    private static function getRedisMatchPattern(): string {
        return 'ee_cache:' . self::getCacheNamespace() . ':*:route:*';
    }

    private static function clearRouteCacheFiles(): void {
        $routeRoot = rtrim((string) ENV_CACHE_PATH, '/\\') . ENV_DIRSEP . 'route' . ENV_DIRSEP;
        if (!is_dir($routeRoot)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($routeRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($routeRoot);
    }

    private static function clearRouteCacheRedis(): void {
        try {
            $redis = new \Redis();
            $redis->connect(ENV_REDIS_ADDRESS, ENV_REDIS_PORT);
        } catch (\Throwable $e) {
            Logger::warning('router_error', 'Route cache Redis clear failed', [
                'message' => $e->getMessage(),
                'address' => ENV_REDIS_ADDRESS,
                'port' => ENV_REDIS_PORT,
            ], [
                'initiator' => __METHOD__,
                'details' => $e->getMessage(),
                'include_trace' => false,
            ]);
            return;
        }

        $iterator = null;
        $pattern = self::getRedisMatchPattern();
        do {
            $keys = $redis->scan($iterator, $pattern, 500);
            if (is_array($keys) && $keys !== []) {
                $redis->del($keys);
            }
        } while ($iterator !== 0 && $iterator !== null);
    }

    /**
     * Подключает и запускает нужный контроллер и его метод (action)
     * Определяет имя класса контроллера на основе URL и вызывает соответствующий метод
     * @return void
     */
    public function delegate(): void {
        $this->initCache();
        $this->getController($file, $controllerDefaultName, $action, $args);
        define('ENV_CONTROLLER_PATH', $file);
        define('ENV_CONTROLLER_NAME', $controllerDefaultName);
        define('ENV_CONTROLLER_ACTION', $action);
        define('ENV_CONTROLLER_ARGS', $args);
        $parts = explode(ENV_DIRSEP, $file);
        $appIndex = array_search(ENV_APP_DIRECTORY, $parts);
        $folderName = ($appIndex !== false && isset($parts[$appIndex + 1])) ? $parts[$appIndex + 1] : (basename(dirname($file)) === ENV_APP_DIRECTORY ? 'index' : basename(dirname($file)));
        define('ENV_CONTROLLER_FOLDER', $folderName);
        $controllerClassNamePart = '';
        if (!empty(ENV_CONTROLLER_FOLDER) && ENV_CONTROLLER_FOLDER !== ENV_APP_DIRECTORY && ENV_CONTROLLER_NAME === 'index' && basename($file) === 'index.php') {
            $controllerClassNamePart = ucfirst(ENV_CONTROLLER_FOLDER);
        } else {
            $controllerClassNamePart = ucfirst(ENV_CONTROLLER_NAME);
        }
        $class = 'Controller' . $controllerClassNamePart;
        if (ENV_TEST) {
            SysClass::pre([
                'file_path' => $file,
                'resolved_class' => $class,
                'default_controller_name' => ENV_CONTROLLER_NAME,
                'folder_name' => ENV_CONTROLLER_FOLDER,
                'action' => $action,
                'args' => $args
                    ], false);
        }
        if (!is_readable($file)) {
            Logger::warning('router_error', 'Файл контроллера не найден или недоступен для чтения: ' . $file, ['file' => $file], [
                'initiator' => __FUNCTION__,
                'details' => $file,
            ]);
            SysClass::handleRedirect(404);
            exit;
        }
        \AutoloadManager::addClassMap($class, $file);
        $view = new View();
        if (!class_exists($class)) {
            Logger::error('router_error', 'Класс контроллера не найден автозагрузчиком после регистрации: ' . $class . '. Проверьте файл: ' . $file, ['expected_class' => $class, 'file_path' => $file], [
                'initiator' => __FUNCTION__,
                'details' => $class,
            ]);
            SysClass::handleRedirect(404);
            exit;
        }
        $controllerInstance = new $class($view);
        $actualAction = $action ?: 'index';
        if (!is_callable([$controllerInstance, $actualAction])) {
            if (is_callable([$controllerInstance, 'index'])) {
                if ($action) {
                    array_unshift($args, $action);
                }
                $actualAction = 'index';
            } else {
                Logger::warning('router_error', 'Метод контроллера не найден или не может быть вызван: ' . $class . '->' . $actualAction, ['class' => $class, 'action' => $actualAction], [
                    'initiator' => __FUNCTION__,
                    'details' => $class . '->' . $actualAction,
                ]);
                SysClass::handleRedirect(404);
                exit;
            }
        }
        $controllerInstance->$actualAction($args);
    }

/**
     * Удаляет все index и лишние слэши
     * Вернёт текущий путь или выполнит редирект 307 на валидный
     * @param string $param Входной маршрут
     * @return string Нормализованный маршрут
     */
    private function normalizeRoute(string $param): string {
        // Запоминаем оригинал для сравнения и редиректа
        $originalParam = $param;

        // Нормализация
        $normalized = $param;
        // Удаляем /index/ или index/ в начале, /index в конце, или если это весь путь
        // Заменяем на соответствующий слеш или пустую строку
        $normalized = preg_replace('/(^|\/)index($|\/)/', '$1$2', $normalized);
        // Убираем двойные слэши
        $normalized = preg_replace('/(?<!:)[\/]{2,}/', '/', $normalized);
        // Убираем слэш в конце, если он есть и это не единственный символ "/"
        if ($normalized !== '/' && substr($normalized, -1) === '/') {
            $normalized = rtrim($normalized, '/');
        }
        // Если остался только слэш или пустая строка - это корень
        if ($normalized === '/' || $normalized === '') {
            $normalized = ''; // Корень представляем пустой строкой
        }
        // Удаляем возможный слеш в начале, если путь не корневой
        if ($normalized !== '' && substr($normalized, 0, 1) === '/') {
             $normalized = ltrim($normalized, '/');
        }

        // Сравниваем нормализованный путь с ОРИГИНАЛЬНЫМ входом
        if ($normalized !== $originalParam) {
            // Определяем, являются ли оба варианта (оригинал и нормализованный) представлением корня
            $isOriginalRoot = ($originalParam === '' || $originalParam === '/' || $originalParam === 'index');
            $isNormalizedRoot = ($normalized === '');

            // Редиректим только если ИЛИ оригинал не был корнем, ИЛИ результат нормализации не корень
            // (то есть, не редиректим с '/', 'index', '' на '/')
            if (!($isOriginalRoot && $isNormalizedRoot)) {
                // Формируем URL для редиректа
                $redirectPath = '/' . $normalized; // Добавляем слеш для всех путей, кроме корневого, который уже ''
                $redirectUrl = rtrim(ENV_URL_SITE, '/') . $redirectPath; // Собираем полный URL
                SysClass::handleRedirect(307, $redirectUrl);
                // exit; // handleRedirect должен сам выходить
            }
        }

        // Возвращаем нормализованный путь ('' для корня, 'path' для остального)
        return $normalized;
    }
}
