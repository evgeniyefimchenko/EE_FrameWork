<?php
/**
 * Класс AutoloadManager управляет автозагрузкой классов и трейтов.
 * Он позволяет добавлять пространства имен и пути к файлам вручную,
 * а также читать их из composer.json плагинов.
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
    * Добавляет все PHP-классы из указанной директории в карту классов, используя заданное пространство имен.
    * Проходит рекурсивно по всем поддиректориям указанного пути и регистрирует файлы с расширением .php.
    * @param string $namespace Пространство имен, которое будет использоваться для всех классов в директории.
    * @param string $path Путь к директории, содержащей классы. Должен быть абсолютным путем.
    * @throws Exception Если указанная директория не найдена.
    * @example AutoloadManager::addNamespace('classes\system', '/path/to/system/classes');
    */
    public static function addNamespace($namespace, $path) {
        if (!is_dir($path)) {
            throw new Exception("Directory not found: $path");
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() == 'php') {
                $className = $namespace . '\\' . $file->getBasename('.php');
                self::addClassMap($className, $file->getRealPath());
            }
        }
    }
    
    /**
     * Добавляет отдельное пространство имён и путь к файлу в карту классов.
     * @param string $namespace Пространство имён класса.
     * @param string $path Путь к файлу класса.
     */
    public static function addClassMap($namespace, $path) {
        self::$classesMap[$namespace] = $path;
    }

    /**
     * Добавляет автозагрузчик Composer для плагина.
     * @param string $autoloaderPath Путь к файлу автозагрузчика Composer.
     */
    public static function addComposerAutoloader($autoloaderPath) {
        self::$composerAutoloaders[] = $autoloaderPath;
    }

    /**
     * Читает файл composer.json плагина и добавляет пространства имен и пути в карту классов.
     * @param string $pluginPath Путь к папке плагина.
     */
    public static function addPluginFromComposerJson($pluginPath) {
        $composerJsonPath = $pluginPath . '/composer.json';
        if (!file_exists($composerJsonPath)) {
            throw new Exception("composer.json not found in $pluginPath");
        }

        $composerConfig = json_decode(file_get_contents($composerJsonPath), true);
        if (isset($composerConfig['autoload']['psr-4'])) {
            foreach ($composerConfig['autoload']['psr-4'] as $namespace => $path) {
                self::addClassMap($namespace, $pluginPath . '/' . $path);
            }
        }
    }

    /**
     * Загружает класс или трейт, используя заданную карту классов или автозагрузчики Composer.
     * @param string $className Имя класса или трейта для загрузки.
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
     * Инициализирует автозагрузчик.
     */
    public static function init() {
        spl_autoload_register([self::class, 'loadClass']);
    }
}

/**
AutoloadManager - это класс для управления автозагрузкой классов и трейтов в проекте.
Он позволяет добавлять пространства имен и пути к файлам вручную, а также автоматически через чтение composer.json плагинов.

*Инициализация*
Перед использованием класса его нужно инициализировать.
AutoloadManager::init();

Добавление пространства имён и пути
Вы можете добавить путь к классу вручную, используя пространство имён и соответствующий путь к файлу.
AutoloadManager::addClassMap('Namespace\ClassName', '/path/to/ClassName.php');

Добавление автозагрузчика Composer
Если вы используете библиотеки, управляемые Composer, можно добавить их автозагрузчик.
AutoloadManager::addComposerAutoloader('/path/to/vendor/autoload.php');

Добавление плагинов через composer.json
Можно добавить пространства имён и пути из файла composer.json плагина.
AutoloadManager::addPluginFromComposerJson('/path/to/plugin');

Пример использования
Предположим, у вас есть класс MyApp\Router, который находится в файле /classes/MyApp/Router.php. Чтобы добавить его в автозагрузчик:
AutoloadManager::addClassMap('MyApp\Router', '/classes/MyApp/Router.php');
Теперь, когда вы используете класс MyApp\Router в своем коде, PHP автоматически загрузит его из указанного файла.

Добавление сторонних библиотек
Если вы установили библиотеку через Composer и хотите, чтобы её классы автоматически подгружались, просто добавьте путь к автозагрузчику Composer.
AutoloadManager::addComposerAutoloader('/path/to/vendor/autoload.php');

Заключение
AutoloadManager обеспечивает гибкий и централизованный способ управления автозагрузкой классов,
что особенно полезно в больших проектах или при использовании множества сторонних библиотек.
 */