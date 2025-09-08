<?php

namespace classes\system;

use classes\system\SysClass;
use classes\system\ErrorLogger;

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
     * Устанавливает путь к контроллерам, задаётся в головном файле INDEX
     * @param string $path Путь к контроллерам
     */
    public function setPath(string $path): void {
        $path = trim($path, '/\\') . ENV_DIRSEP;
        $path = ENV_DIRSEP . $path;
        if (!is_dir($path)) {
            $errorLogger = new ErrorLogger('Указана неверная папка для контроллеров: `' . $path . '`', __FUNCTION__);
            SysClass::handleRedirect(500);
            exit;
        }

        $this->path = $path;
    }

    /**
     * Инициализирует кеширование, если оно включено
     */
    private function initCache(): void {
        if (ENV_ROUTING_CACHE) {
            $this->redis = new \Redis();
            if (!$this->redis->connect(ENV_REDIS_ADDRESS, ENV_REDIS_PORT)) {
                $errorLogger = new ErrorLogger('Не удалось подключиться к Redis', __FUNCTION__);
                SysClass::handleRedirect(500);
                exit;
            }
        } else {
            if (!is_dir(ENV_CACHE_PATH)) {
                if (!mkdir(ENV_CACHE_PATH, 0755, true)) {
                    $errorLogger = new ErrorLogger('Не удалось создать директорию для кеша: ' . ENV_CACHE_PATH, __FUNCTION__);
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
        if (ENV_ROUTING_CACHE) {
            return $this->redis->get($key);
        } else {
            $cacheFile = ENV_CACHE_PATH . ENV_DIRSEP . 'route' . ENV_DIRSEP . $key . '.cache';
            if (file_exists($cacheFile)) {
                return file_get_contents($cacheFile);
            }
        }
        return null;
    }

    /**
     * Сохраняет данные в кеш
     * @param string $key Ключ кеша
     * @param mixed $data Данные для кеширования
     */
    private function setCache(string $key, $data): void {
        if (ENV_ROUTING_CACHE) {
            $this->redis->set($key, $data);
        } else {
            $cacheFile = ENV_CACHE_PATH . ENV_DIRSEP . 'route' . ENV_DIRSEP . $key . '.cache';
            SysClass::createDirectoriesForFile($cacheFile);
            file_put_contents($cacheFile, $data);
        }
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
             new ErrorLogger('Ошибка безопасности при фильтрации маршрута: ' . $routeRaw, __FUNCTION__, 'router_error');
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
             new ErrorLogger('Файл контроллера не найден или недоступен для чтения: [' . $file . '] (Raw: ' . $routeRaw . ', Norm: ' . $routeNormalized . ')', __FUNCTION__, 'router_error');
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
            new ErrorLogger('Файл контроллера не найден или недоступен для чтения: ' . $file, __FUNCTION__, 'router_error');
            SysClass::handleRedirect(404);
            exit;
        }
        \AutoloadManager::addClassMap($class, $file);
        $view = new View();
        if (!class_exists($class)) {
            new ErrorLogger('Класс контроллера не найден автозагрузчиком после регистрации: ' . $class . '. Проверьте файл: ' . $file, __FUNCTION__, 'router_error', ['expected_class' => $class, 'file_path' => $file]);
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
                new ErrorLogger('Метод контроллера не найден или не может быть вызван: ' . $class . '->' . $actualAction, __FUNCTION__, 'router_error', ['class' => $class, 'action' => $actualAction]);
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
