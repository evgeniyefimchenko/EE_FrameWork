<?php

namespace classes\system;

/**
 * Класс для работы с языковыми переменными
 * Загружает языковые файлы и предоставляет методы для получения переменных
 */
class Lang {
    /**
     * Массив для хранения всех языковых переменных
     * @var array
     */
    private static array $lang = [];

    /**
     * Загружает языковой файл и добавляет его данные в общий массив
     * @param string $filePath Путь к языковому файлу
     * @throws \RuntimeException Если файл не найден
     */
    private static function loadLangFile(string $filePath): void {
        if (file_exists($filePath)) {
            require $filePath;
            if (isset($lang) && is_array($lang)) { // $lang массив из языкового файла
                self::$lang = array_merge(self::$lang, $lang);
            }
        } else {
            $errorLogger = new ErrorLogger('Языковой файл не найден: ' . $filePath, __FUNCTION__, 'lang');
            self::$lang = $errorLogger->result;
        }
    }

    /**
     * Инициализирует класс и загружает языковой файл
     * @param string $langCode Код языка
     */
    public static function init(string $langCode): array {
        $langCode = strtoupper($langCode);
        if (empty(self::$lang)) {
            $langPath = ENV_SITE_PATH . ENV_PATH_LANG . $langCode . '.php';
            self::loadLangFile($langPath);
        }
        return self::$lang;
    }

    /**
     * Возвращает все языковые переменные
     * @return array Массив всех языковых переменных
     */
    public static function getAll(): array {
        if (empty(self::$lang)) {
            $errorLogger = new ErrorLogger('Языковой массив пуст', __FUNCTION__, 'lang'); 
            self::$lang = $errorLogger->result;
        }        
        return self::$lang;
    }

    /**
     * Возвращает языковые переменные по указанному префиксу
     * @param string $prefix Префикс для фильтрации переменных
     * @return array Массив переменных, начинающихся с указанного префикса
     */
    public static function getByPrefix(string $prefix): array {
        $filtered = [];
        if (empty(self::$lang)) {
            $errorLogger = new ErrorLogger('Языковой массив пуст', __FUNCTION__, 'lang');
            self::$lang = $errorLogger->result;
            $filtered = var_export($errorLogger->result, true);
        } else {
            foreach (self::$lang as $key => $value) {
                if (strpos($key, $prefix) === 0) {
                    $filtered[$key] = $value;
                }
            }
        }
        return $filtered;
    }

    /**
     * Возвращает значение конкретной языковой переменной
     * @param string $key Ключ языковой переменной
     * @param string|null $default Значение по умолчанию, если переменная не найдена
     * @return string|null Значение переменной или значение по умолчанию
     */
    public static function get(string $key, ?string $default = null): ?string {
        return self::$lang[$key] ?? $default;
    }
    
    /**
     * Возвращает массив имён PHP-файлов в указанной папке без расширения .php
     * @return array Массив имён файлов без расширения .php
     */
    public static function getLangFilesWithoutExtension(): array {
        $langPath = ENV_SITE_PATH . ENV_PATH_LANG;
        $files = scandir($langPath);
        $phpFiles = [];
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $phpFiles[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }
        return $phpFiles;
    }    
    
}