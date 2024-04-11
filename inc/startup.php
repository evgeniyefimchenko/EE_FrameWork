<?php

/**
 * Класс AutoloadManager управляет автозагрузкой классов и трейтов
 * Он позволяет добавлять пространства имен и пути к файлам вручную,
 * а также читать их из composer.json плагинов
 */
class AutoloadManager {

    /**
     * @var array Карта классов для автозагрузки.
     */
    private static $classesMap = [];

    /**
     * @var array Массив путей к автозагрузчикам Composer.
     */
    private static $composerAutoloaders = [];

    /**
     * Устанавливает значения по умолчанию для пространств имен.
     */
    private static function setDefaultNamespaces() {
        self::addNamespace('classes\system', ENV_SITE_PATH . 'classes' . ENV_DIRSEP . 'system' . ENV_DIRSEP);
        self::addClassMap('classes\plugins\HTTPRequester', ENV_SITE_PATH . 'classes' . ENV_DIRSEP . 'plugins' . ENV_DIRSEP . 'HTTPRequester.php');
        self::addClassMap('classes\plugins\SafeMySQL', ENV_SITE_PATH . 'classes' . ENV_DIRSEP . 'plugins' . ENV_DIRSEP . 'SafeMySQL.php');
        self::addNamespace('classes\helpers', ENV_SITE_PATH . 'classes' . ENV_DIRSEP . 'helpers' . ENV_DIRSEP);
        self::addNamespace('app\admin', ENV_SITE_PATH . ENV_APP_DIRECTORY . ENV_DIRSEP . 'admin' . ENV_DIRSEP, true, 
                [ENV_SITE_PATH . ENV_APP_DIRECTORY . ENV_DIRSEP . 'admin' . ENV_DIRSEP . 'views']);
    }

    /**
     * Инициализирует автозагрузчик
     */
    public static function init() {
        // Все дополнительные классы плагинов и расширений должны быть подключены тут
        AutoloadManager::addPluginFromComposerJson(ENV_SITE_PATH . 'classes' . ENV_DIRSEP . 'plugins' . ENV_DIRSEP . 'PHPMailer');
        // Далее подключаются системные классы
        self::setDefaultNamespaces();
        spl_autoload_register([self::class, 'loadClass']);
    }

    /**
     * Добавляет все PHP-классы из указанной директории в карту классов, используя заданное пространство имен
     * Позволяет исключать указанные директории из рекурсивного поиска
     * @param string $namespace Пространство имен для всех классов в директории
     * @param string $path Путь к директории с классами (абсолютный путь)
     * @param bool $recursive Определяет, следует ли включать вложенные директории в поиск (по умолчанию true)
     * @param array $excludeDirs Директории, которые следует исключить из поиска. Пути должны быть абсолютными
     * @throws Exception Если директория не найдена
     */
    public static function addNamespace(string $namespace, string $path, bool $recursive = true, array $excludeDirs = []): void {
        if (!is_dir($path)) {
            throw new Exception("Directory not found: $path");
        }
        $directoryIterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        $filterIterator = new RecursiveCallbackFilterIterator($directoryIterator, function ($current, $key, $iterator) use ($excludeDirs) {
                    if ($current->isDir()) {
                        $path = $current->getRealPath();
                        foreach ($excludeDirs as $excludeDir) {
                            if (strpos($path, $excludeDir) === 0) {
                                return false;
                            }
                        }
                    }
                    return true;
                });
        if ($recursive) {
            $iterator = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::LEAVES_ONLY);
        } else {
            $iterator = $filterIterator;
        }
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace([$path, '.php', DIRECTORY_SEPARATOR], ['', '', '\\'], $file->getRealPath());
                $className = $namespace . '\\' . ltrim($relativePath, '\\');
                self::addClassMap($className, $file->getRealPath());
            }
        }
    }

    /**
     * Добавляет класс и его путь в карту автозагрузки, если такой путь ещё не был зарегистрирован
     * Этот метод проверяет уникальность пути к файлу перед добавлением в карту классов
     * Если указанный путь уже зарегистрирован для любого пространства имен, 
     * новое пространство имен с тем же путем не будет добавлено, чтобы избежать дублирования
     * Это помогает предотвратить конфликты при попытке зарегистрировать разные пространства имен,
     * ссылающиеся на один и тот же файл класса
     * @param string $namespace Пространство имен класса, которое нужно зарегистрировать
     * @param string $path Путь к файлу класса. Проверяется на уникальность перед добавлением
     */
    public static function addClassMap($namespace, $path) {
        if (in_array($path, self::$classesMap)) {
            return;
        }
        self::$classesMap[$namespace] = $path;
    }

    /**
     * Добавляет автозагрузчик Composer для плагина
     * @param string $autoloaderPath Путь к файлу автозагрузчика Composer
     */
    public static function addComposerAutoloader($autoloaderPath) {
        self::$composerAutoloaders[] = $autoloaderPath;
    }

    /**
     * Читает файл composer.json плагина и добавляет пространства имен и пути в карту классов
     * @param string $pluginPath Путь к папке плагина
     */
    public static function addPluginFromComposerJson($pluginPath) {
        $composerJsonPath = $pluginPath . '/composer.json';
        if (!file_exists($composerJsonPath)) {
            throw new Exception("composer.json not found in $pluginPath");
        }
        $composerConfig = json_decode(file_get_contents($composerJsonPath), true);
        if (isset($composerConfig['autoload']['psr-4'])) {
            foreach ($composerConfig['autoload']['psr-4'] as $namespace => $path) {
                $fullPath = rtrim($pluginPath . DIRECTORY_SEPARATOR . trim($path, '/\\'), DIRECTORY_SEPARATOR);
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullPath), RecursiveIteratorIterator::LEAVES_ONLY);
                foreach ($files as $file) {
                    if ($file->isFile() && $file->getExtension() == 'php') {
                        $relativePath = str_replace([$fullPath, '.php'], '', $file->getRealPath());
                        $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
                        $className = trim($namespace, '\\') . '\\' . ltrim($relativePath, '\\');
                        self::addClassMap($className, $file->getRealPath());
                    }
                }
            }
        }
    }

    /**
     * Загружает класс или трейт, используя заданную карту классов или автозагрузчики Composer
     * @param string $className Имя класса или трейта для загрузки
     */
    private static function loadClass($className) {
        if (isset(self::$classesMap[$className])) {
            require_once self::$classesMap[$className];
            return;
        }
        foreach (self::$composerAutoloaders as $autoloaderPath) {
            if (file_exists($autoloaderPath)) {
                require_once $autoloaderPath;
                if (class_exists($className, false) || trait_exists($className, false)) {
                    return;
                }
            }
        }
        if (!headers_sent()) {
            header("HTTP/1.0 404 Not Found");
        }
        // Добавляем отладочную информацию
        $backtrace = debug_backtrace();
        echo "Класс или трейт не найден: $className<br>";
        echo "Стек вызовов:<br>";
        foreach ($backtrace as $trace) {
            echo "Файл: " . (isset($trace['file']) ? $trace['file'] : 'Неизвестный') . "<br>";
            echo "Строка: " . (isset($trace['line']) ? $trace['line'] : 'Неизвестная') . "<br>";
            echo "Функция: " . (isset($trace['function']) ? $trace['function'] : 'Неизвестная') . "<br>";
            echo "Класс: " . (isset($trace['class']) ? $trace['class'] : 'Неизвестный') . "<br><br>";
        }
        exit;
    }

    /**
     * Выведет на экран всю карту загрузки
     */
    public static function showClassMap() {
        echo '<pre>';
        var_export(self::$classesMap);
        echo '</pre>';
        die;
    }
    
}
