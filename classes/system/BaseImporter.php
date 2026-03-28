<?php
namespace classes\system;

use classes\plugins\SafeMySQL;
use classes\system\Constants;

/**
 * Базовый класс для всех импортеров
 */
abstract class BaseImporter {

    protected static $logDir;
    protected $logFile;
    protected $job_id;
    protected $settings;

    public function __construct(array $settings) {
        $this->settings = $this->normalizeSettings($settings);
        $this->job_id = (int)($this->settings['job_id'] ?? 0);

        if (!defined('ENV_BULK_IMPORT_MODE')) {
            define('ENV_BULK_IMPORT_MODE', true);
        }
        
        // Используем ENV_LOGS_PATH, как вы и просили
        self::$logDir = rtrim(ENV_LOGS_PATH, '/\\') . ENV_DIRSEP . 'import' . ENV_DIRSEP;
        
        $this->logFile = self::$logDir . "import_job_{$this->job_id}.txt";

        if (!is_dir(self::$logDir)) {
            if (!@mkdir(self::$logDir, 0755, true)) {
                die("Critical Error: Could not create log directory at " . self::$logDir);
            }
        }
        \classes\helpers\ClassNotifications::$logCallback = [$this, 'log'];
    }

    private function normalizeSettings(array $settings): array {
        $decodedSettings = [];
        if (!empty($settings['settings_json']) && is_string($settings['settings_json'])) {
            $decoded = json_decode($settings['settings_json'], true);
            if (is_array($decoded)) {
                $decodedSettings = $decoded;
            }
        }

        $normalized = array_merge($settings, $decodedSettings);
        if (!isset($normalized['job_id'])) {
            $normalized['job_id'] = (int)($settings['id'] ?? 0);
        }
        if (!isset($normalized['settings_name'])) {
            $normalized['settings_name'] = (string)($settings['settings_name'] ?? $settings['name'] ?? 'manual');
        }
        if (!isset($normalized['importer_slug']) && isset($settings['importer_slug'])) {
            $normalized['importer_slug'] = (string)$settings['importer_slug'];
        }

        return $normalized;
    }

    /**
     * Главный метод запуска
     */
    public function run() {
        $startTime = microtime(true);
        $settingsName = htmlspecialchars((string)($this->settings['settings_name'] ?? 'manual'));
        $this->log("==================================================");
        $this->log("Начало импорта: " . get_class($this) . " (Профиль: " . $settingsName . " [ID: {$this->job_id}])");
        
        SafeMySQL::gi()->query("UPDATE ?n SET `last_run_at` = CURRENT_TIMESTAMP WHERE id = ?i", Constants::IMPORT_SETTINGS_TABLE, $this->job_id);
        
        try {
            $this->_execute();
        } catch (\Throwable $e) {
            $this->log("!!! КРИТИЧЕСКАЯ ОШИБКА !!!");
            $this->log("Файл: " . $e->getFile() . " (Строка: " . $e->getLine() . ")");
            $this->log("Сообщение: " . $e->getMessage());
            Logger::error('import_job_' . $this->job_id, $e->getMessage(), ['trace' => $e->getTrace()], [
                'initiator' => __METHOD__,
                'details' => $e->getMessage(),
            ]);
        }

        $endTime = microtime(true);
        $this->log("Импорт завершен. Время выполнения: " . round($endTime - $startTime, 2) . " сек.");
        $this->log("==================================================\n");
    }

    /**
     * Основная логика импорта
     */
    abstract protected function _execute();

    /**
     * Универсальный метод логирования
     */
    public function log($message) {
        if (is_array($message)) {
            $status = $message['status'] ?? 'info';
            $text = $message['text'] ?? 'Пустое уведомление';
            $message = "УВЕДОМЛЕНИЕ ({$status}): {$text}";
        }
        $logEntry = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
        $isCronRun = defined('EE_CRON_RUN') && EE_CRON_RUN === true;
        $isWebStepMode = !empty($this->settings['web_step_mode']);
        if (!$isCronRun && !$isWebStepMode) {
            echo $logEntry;
            @ob_flush();
            @flush();
        }

        @file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Статический метод для логирования ошибок до инициализации
     */
    public static function preLog(string $message, int $job_id = 0) {
        $logDir = rtrim(ENV_LOGS_PATH, '/\\') . ENV_DIRSEP . 'import' . ENV_DIRSEP;
        $logFile = $logDir . "import_job_{$job_id}.txt";
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        $logEntry = "[" . date('Y-m-d H:i:s') . "] [PRE-INIT] " . $message . "\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Очистка при завершении
     */
    public function __destruct() {
        \classes\helpers\ClassNotifications::$logCallback = null;
    }    
    
}
