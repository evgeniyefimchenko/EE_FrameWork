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
    private static array $langCache = [];
    private static string $currentLangCode = '';

    private static function normalizeLanguageCode(string $langCode): string {
        $normalized = strtoupper(trim(pathinfo($langCode, PATHINFO_FILENAME)));
        $normalized = preg_replace('/[^A-Z0-9_-]/', '', $normalized) ?? '';
        return $normalized;
    }

    private static function resolveRequestedLanguage(string $langCode): array {
        $candidates = [
            self::normalizeLanguageCode($langCode),
            self::normalizeLanguageCode((string) (defined('ENV_DEF_LANG') ? ENV_DEF_LANG : '')),
            self::normalizeLanguageCode((string) (defined('ENV_PROTO_LANGUAGE') ? ENV_PROTO_LANGUAGE : '')),
            'EN',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $langPath = ENV_SITE_PATH . ENV_PATH_LANG . $candidate . '.php';
            if (is_file($langPath)) {
                return [
                    'code' => $candidate,
                    'path' => $langPath,
                ];
            }
        }

        return [
            'code' => '',
            'path' => null,
        ];
    }

    /**
     * Загружает языковой файл и добавляет его данные в общий массив
     * @param string $filePath Путь к языковому файлу
     * @throws \RuntimeException Если файл не найден
     */
    private static function loadLangFile(string $filePath): array {
        if (!is_file($filePath)) {
            Logger::error('lang', 'Языковой файл не найден', ['file_path' => $filePath], [
                'initiator' => __FUNCTION__,
                'details' => $filePath,
            ]);
            return [];
        }

        $loaded = require $filePath;
        if (!is_array($loaded)) {
            Logger::warning('lang', 'Языковой файл вернул не массив', ['file_path' => $filePath], [
                'initiator' => __FUNCTION__,
                'details' => $filePath,
            ]);
            return [];
        }

        return $loaded;
    }

    /**
     * Инициализирует класс и загружает языковой файл
     * @param string $langCode Код языка
     */
    public static function init(string $langCode): array {
        $resolved = self::resolveRequestedLanguage($langCode);
        $resolvedLangCode = (string) ($resolved['code'] ?? '');
        $langPath = $resolved['path'] ?? null;

        if ($resolvedLangCode === '' || !is_string($langPath) || $langPath === '') {
            self::$currentLangCode = '';
            self::$lang = [];
            return self::$lang;
        }

        if (isset(self::$langCache[$resolvedLangCode]) && is_array(self::$langCache[$resolvedLangCode])) {
            self::$currentLangCode = $resolvedLangCode;
            self::$lang = self::$langCache[$resolvedLangCode];
            return self::$lang;
        }

        if ($resolvedLangCode !== self::$currentLangCode || empty(self::$lang)) {
            self::$lang = self::loadLangFile($langPath);
            self::$langCache[$resolvedLangCode] = self::$lang;
            self::$currentLangCode = $resolvedLangCode;
        }
        return self::$lang;
    }

    /**
     * Возвращает все языковые переменные
     * @return array Массив всех языковых переменных
     */
    public static function getAll(): array {
        return self::$lang;
    }

    /**
     * Возвращает языковые переменные по указанному префиксу
     * @param string $prefix Префикс для фильтрации переменных
     * @return array Массив переменных, начинающихся с указанного префикса
     */
    public static function getByPrefix(string $prefix): array {
        if (empty(self::$lang)) {
            return [];
        }

        if ($prefix === '') {
            return self::$lang;
        }

        $filtered = [];
        foreach (self::$lang as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $filtered[$key] = $value;
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

    public static function getCurrentLangCode(): string {
        return self::$currentLangCode;
    }

    public static function resolveLangCode(string $langCode): string {
        $resolved = self::resolveRequestedLanguage($langCode);
        return (string) ($resolved['code'] ?? '');
    }

    public static function getLangFilePath(string $langCode, bool $fallback = true): ?string {
        if (!$fallback) {
            $normalized = self::normalizeLanguageCode($langCode);
            if ($normalized === '') {
                return null;
            }
            $directPath = ENV_SITE_PATH . ENV_PATH_LANG . $normalized . '.php';
            return is_file($directPath) ? $directPath : null;
        }

        $resolved = self::resolveRequestedLanguage($langCode);
        return is_string($resolved['path'] ?? null) ? $resolved['path'] : null;
    }
    
    /**
     * Возвращает массив имён PHP-файлов в указанной папке без расширения .php
     * @return array Массив имён файлов без расширения .php
     */
    public static function getLangFilesWithoutExtension(): array {
        $langPath = ENV_SITE_PATH . ENV_PATH_LANG;
        if (!is_dir($langPath)) {
            return [];
        }

        $phpFiles = [];
        foreach (glob($langPath . '*.php') ?: [] as $filePath) {
            $phpFiles[] = self::normalizeLanguageCode(pathinfo($filePath, PATHINFO_FILENAME));
        }
        $phpFiles = array_values(array_filter(array_unique($phpFiles)));
        sort($phpFiles, SORT_STRING);
        return $phpFiles;
    }    
    
}
