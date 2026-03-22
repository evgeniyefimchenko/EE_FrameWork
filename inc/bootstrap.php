<?php

require_once __DIR__ . '/configuration.php';
require_once __DIR__ . '/startup.php';

if (!function_exists('ee_log_custom_bootstrap_error')) {
    /**
     * Логирует ошибки пользовательского слоя, не роняя ядро.
     */
    function ee_log_custom_bootstrap_error(string $message, string $context, array $details = []): void {
        if (class_exists(\classes\system\ErrorLogger::class)) {
            new \classes\system\ErrorLogger($message, $context, 'custom', $details);
            return;
        }
        error_log($message . ' ' . json_encode($details, JSON_UNESCAPED_UNICODE));
    }
}

if (!function_exists('ee_register_custom_autoload')) {
    /**
     * Подключает namespace `custom\...`, если папка custom/src существует.
     */
    function ee_register_custom_autoload(): void {
        static $registered = false;
        if ($registered) {
            return;
        }

        $customSrcPath = ENV_CUSTOM_PATH . 'src' . ENV_DIRSEP;
        if (is_dir($customSrcPath)) {
            \AutoloadManager::addNamespace('custom', $customSrcPath);
        }

        $registered = true;
    }
}

if (!function_exists('ee_add_custom_hook')) {
    /**
     * Регистрирует hook пользовательского слоя.
     */
    function ee_add_custom_hook(string $key, $callback, int $priority = 10): bool {
        return \classes\system\Hook::add($key, $callback, $priority, 'custom', 'custom');
    }
}

if (!function_exists('ee_include_custom_file')) {
    /**
     * Безопасно подключает файл пользовательского слоя.
     */
    function ee_include_custom_file(string $filePath, string $context): void {
        if (!is_file($filePath)) {
            return;
        }

        try {
            require_once $filePath;
        } catch (\Throwable $e) {
            ee_log_custom_bootstrap_error(
                'Custom bootstrap error: ' . $e->getMessage(),
                $context,
                [
                    'file_path' => $filePath,
                    'trace' => $e->getTraceAsString(),
                ]
            );
        }
    }
}

if (!function_exists('ee_bootstrap_runtime')) {
    /**
     * Единая runtime инициализация ядра и project-level слоя `custom/` для web/cron.
     */
    function ee_bootstrap_runtime(): void {
        static $booted = false;
        if ($booted) {
            return;
        }

        \AutoloadManager::init();
        if (class_exists(\classes\system\Logger::class)) {
            \classes\system\Logger::bootstrap();
        }
        ee_register_custom_autoload();
        require_once ENV_SITE_PATH . 'inc' . DIRECTORY_SEPARATOR . 'hooks.php';
        ee_include_custom_file(ENV_CUSTOM_PATH . 'hooks.php', __FUNCTION__ . ':custom_hooks');
        ee_include_custom_file(ENV_CUSTOM_PATH . 'bootstrap.php', __FUNCTION__ . ':custom_bootstrap');
        $booted = true;
    }
}

if (!function_exists('ee_bootstrap_preload')) {
    /**
     * Инициализация для OPcache preload с видимостью custom-классов и custom-hooks.
     */
    function ee_bootstrap_preload(): void {
        static $prepared = false;
        if ($prepared) {
            return;
        }

        \AutoloadManager::init();
        if (class_exists(\classes\system\Logger::class)) {
            \classes\system\Logger::bootstrap();
        }
        ee_register_custom_autoload();
        require_once ENV_SITE_PATH . 'inc' . DIRECTORY_SEPARATOR . 'hooks.php';
        ee_include_custom_file(ENV_CUSTOM_PATH . 'hooks.php', __FUNCTION__ . ':custom_hooks');
        $prepared = true;
    }
}
