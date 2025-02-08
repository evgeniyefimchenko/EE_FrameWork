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
            if (ENV_CACHE_REDIS) {
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
    }

    /**
     * Получает данные из кеша
     * @param string $key Ключ кеша
     * @return mixed Данные из кеша или null, если данные отсутствуют
     */
    private function getCache(string $key) {
        if (ENV_ROUTING_CACHE) {
            if (ENV_CACHE_REDIS) {
                return $this->redis->get($key);
            } else {
                $cacheFile = ENV_CACHE_PATH . md5($key) . '.cache';
                if (file_exists($cacheFile)) {
                    return file_get_contents($cacheFile);
                }
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
            if (ENV_CACHE_REDIS) {
                $this->redis->set($key, $data);
            } else {
                $cacheFile = ENV_CACHE_PATH . md5($key) . '.cache';
                file_put_contents($cacheFile, $data);
            }
        }
    }

    /**
     * Отфильтровывает и разбивает на параметры маршрут сайта
     * @param string|null $file Полный путь к файлу контроллера для подключения
     * @param string|null $controller Имя класса контроллера
     * @param string|null $action Функция в классе контроллера
     * @param array|null $args Массив параметров для функции класса контроллера
     */
    private function getController(?string &$file, ?string &$controller, ?string &$action, ?array &$args): void {
        $cacheKey = 'route_' . md5($_GET['route'] ?? 'index');
        $cachedData = $this->getCache($cacheKey);
        if ($cachedData) {
            $data = json_decode($cachedData, true);
            $file = $data['file'];
            $controller = $data['controller'];
            $action = $data['action'];
            $args = $data['args'];
            if (!is_readable($file)) {
                if (ENV_TEST) {
                    die('not readable ' . $file);
                }                
                $errorLogger = new ErrorLogger('Файл контроллера не найден: ' . $file, __FUNCTION__);
                SysClass::handleRedirect(404); 
                exit;
            }
            include_once $file; // Подключаем файл контроллера
            return;
        }
        if (ENV_TEST && isset($_GET['route'])) {
            echo 'route= ' . $_GET['route'] . '<br/>';
        }
        $getRoute = filter_var($_GET['route'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (isset($_GET['route']) && $_GET['route'] !== $getRoute) {
            if (ENV_TEST) {
                die('Ошибка проверки запроса = ' . $getRoute);
            }            
            $errorLogger = new ErrorLogger('Ошибка проверки запроса: ' . $getRoute, __FUNCTION__);
            SysClass::handleRedirect(404); 
            exit;
        }
        $route = empty($getRoute) ? 'index' : $this->normalizeRoute($getRoute);
        $parts = explode('/', $route);
        $addPath = 0;
        // Поиск папки и контроллера
        foreach ($parts as $part) {
            $fullPath = $this->path . $part;

            if (is_dir($fullPath)) {
                $this->path .= $part . ENV_DIRSEP;
                array_shift($parts);
                $addPath++;
                continue;
            }
            if (is_file($fullPath . '.php')) {
                $controller = $part;
                array_shift($parts);
                break;
            }
            if (is_file($this->path . 'index' . ENV_DIRSEP . $part . '.php')) {
                $controller = $part;
                $this->path .= 'index' . ENV_DIRSEP;
                array_shift($parts);
                break;
            }
        }
        if (empty($controller)) {
            $this->path .= $addPath === 0 ? 'index' . ENV_DIRSEP : '';
            $controller = 'index';
        }
        $file = $this->path . $controller . '.php';
        if (!is_readable($file)) {
            if (ENV_TEST) {
                die('not readable ' . $file);
            }            
            $errorLogger = new ErrorLogger('Файл контроллера не найден: ' . $file, __FUNCTION__);
            SysClass::handleRedirect(404);
            exit;
        }
        $args = $parts;
        include_once $file;
        $action = array_shift($args);
        $tempClass = 'Controller' . ucfirst($controller);
        if (ENV_TEST) {
            echo '<pre>';
            var_dump(['file' => $file, 'controller' => $tempClass, 'action' => $action, 'args' => $args]);
            echo '</pre>';
        }
        if (empty($action) || !method_exists($tempClass, $action)) {
            $action = 'index';
        }
        $this->setCache($cacheKey, json_encode([
            'file' => $file,
            'controller' => $controller,
            'action' => $action,
            'args' => $args
        ]));
    }

    /**
     * Подключение класса контроллера для страницы
     */
    public function delegate(): void {
        $this->initCache();
        $this->getController($file, $controller, $action, $args);
        define('ENV_CONTROLLER_PATH', $file);
        define('ENV_CONTROLLER_NAME', $controller);
        define('ENV_CONTROLLER_ACTION', $action);
        define('ENV_CONTROLLER_ARGS', $args);
        $parts = explode(ENV_DIRSEP, $file); 
        $appIndex = array_search(ENV_APP_DIRECTORY, $parts);
        define('ENV_CONTROLLER_FOLDER', $parts[$appIndex + 1]);
        $class = 'Controller' . ucfirst($controller);
        $view = new View();
        $controller = new $class($view);
        if (!is_callable([$controller, $action])) {
            if (ENV_TEST) {
                die('</br></br>is_callable class= ' . $class . ' action= ' . $action);
            }            
            $errorLogger = new ErrorLogger('Метод контроллера не найден: ' . $action, __FUNCTION__);
            SysClass::handleRedirect(404); 
            exit;
        }
        $controller->$action($args);
    }

    /**
     * Удаляет все index и лишние слэши
     * Вернёт текущий путь или выполнит редирект 307 на валидный
     * @param string $param Входной маршрут
     * @return string Нормализованный маршрут
     */
    private function normalizeRoute(string $param): string {
        $dellIndex = preg_replace('/index/', '', $param);
        $dellDoubleSlash = preg_replace('/(?<!:)[\/]{2,}/', '', $dellIndex);
        $dellEndSlash = preg_replace('/\/{1,}$/', '', $dellDoubleSlash);
        if ($dellEndSlash === $param) {
            return $dellEndSlash;
        }
        SysClass::handleRedirect(307, ENV_URL_SITE . ENV_DIRSEP . $dellEndSlash);
        return $dellEndSlash;
    }
}