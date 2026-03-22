<?php

namespace classes\system;

use classes\system\SysClass;
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
    private static array $langCache = [];
    private static string $currentLangCode = '';

    /**
     * Загружает языковой файл и добавляет его данные в общий массив
     * @param string $filePath Путь к языковому файлу
     * @throws \RuntimeException Если файл не найден
     */
    private static function loadLangFile(string $filePath): array {
        $loaded = [];
        if (file_exists($filePath)) {
            require $filePath;
            if (isset($lang) && is_array($lang)) { // $lang массив из языкового файла
                $loaded = array_merge($loaded, $lang);
            }
        } else {
            $message = 'Языковой файл не найден: ' . $filePath . ' Искал: ' . SysClass::detectClientBrowser() . ' IP: ' . SysClass::getClientIp();
            Logger::error('lang', $message, ['file_path' => $filePath], [
                'initiator' => __FUNCTION__,
                'details' => $message,
            ]);
            $loaded = [
                'error' => true,
                'error_message' => $message,
                'function_name' => __FUNCTION__,
            ];
        }
        return $loaded;
    }

    /**
     * Инициализирует класс и загружает языковой файл
     * @param string $langCode Код языка
     */
    public static function init(string &$langCode): array {
        $langCode = strtoupper($langCode);
        if (isset(self::$langCache[$langCode]) && is_array(self::$langCache[$langCode])) {
            self::$currentLangCode = $langCode;
            self::$lang = self::$langCache[$langCode];
            return self::$lang;
        }

        if ($langCode !== self::$currentLangCode || empty(self::$lang)) {
            $langPath = ENV_SITE_PATH . ENV_PATH_LANG . $langCode . '.php';
            if (!file_exists($langPath)) {
                $langCode = strtoupper((string)ENV_PROTO_LANGUAGE);
                $langPath = ENV_SITE_PATH . ENV_PATH_LANG . $langCode . '.php';
            }
            self::$lang = self::loadLangFile($langPath);
            self::$langCache[$langCode] = self::$lang;
            self::$currentLangCode = $langCode;
        }
        return self::$lang;
    }

    /**
     * Возвращает все языковые переменные
     * @return array Массив всех языковых переменных
     */
    public static function getAll(): array {
        if (empty(self::$lang)) {
            Logger::warning('lang', 'Языковой массив пуст', [], [
                'initiator' => __FUNCTION__,
                'details' => 'Языковой массив пуст',
            ]);
            self::$lang = [
                'error' => true,
                'error_message' => 'Языковой массив пуст',
                'function_name' => __FUNCTION__,
            ];
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
            Logger::warning('lang', 'Языковой массив пуст', ['prefix' => $prefix], [
                'initiator' => __FUNCTION__,
                'details' => 'Языковой массив пуст',
            ]);
            self::$lang = [
                'error' => true,
                'error_message' => 'Языковой массив пуст',
                'function_name' => __FUNCTION__,
            ];
            $filtered = self::$lang;
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
