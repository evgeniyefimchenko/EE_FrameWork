<?php

namespace classes\system;

/**
 * Класс для регистрации ошибок
 */
class ErrorLogger {

    private string $errorMessage;
    private string $functionName;
    public array $result;
    // Сколько дней хранить файлы логов
    private static int $maxLogAgeDays = 30;
    // Максимальный размер ТЕКУЩЕГО файла лога в байтах (здесь 10MB)
    private static int $maxLogFileSize = 10 * 1024 * 1024;
    private static string $logBaseName = '';

    /**
     * Конструктор класса
     * @param string $errorMessage Сообщение об ошибке
     * @param string $functionName Имя функции, в которой произошла ошибка
     * @param string $prefix Префикс для логирования
     * @param string $details Дополнительные детали
     */
    public function __construct(string $errorMessage, string $functionName, string $prefix = '', mixed $details = '') {
        $this->errorMessage = $errorMessage;
        $this->functionName = $functionName;
        $prefix = empty($prefix) ? 'ee_' : $prefix . '_';
        self::$logBaseName = $prefix . 'errors';
        $this->logError($details);
        $this->result = $this->getErrorInfo();
        $this->rotateAndCleanup();
    }

    /**
     * Метод для записи ошибки в файл
     * @param string $details Дополнительные детали
     */
    private function logError(mixed $details = ''): void {
        SysClass::preFile(self::$logBaseName, $this->functionName, $this->errorMessage, $details);
    }

    /**
     * Метод для получения информации об ошибке
     * @return array Массив с информацией об ошибке
     */
    private function getErrorInfo(): array {
        return [
            'error' => true,
            'error_message' => $this->errorMessage,
            'function_name' => $this->functionName,
        ];
    }

    /**
     * Выполняет ротацию логов по размеру и очистку старых файлов
     */
    private function rotateAndCleanup(): void {
        if (!is_dir(ENV_LOGS_PATH)) {
            return;
        }
        $normalizedRoot = rtrim(ENV_LOGS_PATH, '/\\');
        $this->rotateLogs($normalizedRoot);
        $this->cleanupOldFiles($normalizedRoot, $normalizedRoot);
    }

    /**
     * Рекурсивно удаляет файлы старше $maxLogAgeDays дней.
     *
     * @param string $directory Путь к директории для обработки
     */
    private function cleanupOldFiles(string $directory): void {
        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        $cutoffTime = time() - (self::$maxLogAgeDays * 24 * 60 * 60); // Время, до которого файлы считаются "старыми"

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                // Рекурсивно обрабатываем поддиректории
                $this->cleanupOldFiles($path);
            } elseif (is_file($path)) {
                // Удаляем файл, если он старше допустимого срока
                if (filemtime($path) && filemtime($path) < $cutoffTime) {
                    @unlink($path); // Подавляем ошибки (например, если нет прав)
                }
            }
        }
    }

    /**
     * Рекурсивно проверяет файлы логов и выполняет ротацию, если они слишком большие
     * @param string $directory Путь к директории
     */
    private function rotateLogs(string $directory): void {
        if (!is_dir($directory)) {
            return;
        }
        $items = scandir($directory);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rotateLogs($path); // рекурсия
            } elseif (is_file($path)) {
                $this->rotateIfTooBig($path);
            }
        }
    }

    /**
     * Если файл превышает максимальный размер — ротируем его
     * @param string $filePath Полный путь к файлу
     */
    private function rotateIfTooBig(string $filePath): void {
        if (!is_file($filePath)) {
            return;
        }
        $size = filesize($filePath);
        if ($size === false || $size < self::$maxLogFileSize) {
            return;
        }
        $this->performRotation($filePath);
    }

    /**
     * Выполняет ротацию файла: сдвигает старые архивы и создаёт новый .1
     * @param string $filePath Текущий файл (например, /logs/app.log)
     */
    private function performRotation(string $filePath): void {
        $this->cleanupOldestBackup($filePath);
        $this->shiftBackups($filePath);
        $backupPath = $filePath . '.1';
        if (@rename($filePath, $backupPath)) {
            // Создаём новый пустой файл на месте старого
            $newHandle = @fopen($filePath, 'w');
            if ($newHandle) {
                fwrite($newHandle, ""); // можно добавить заголовок, если нужно
                fclose($newHandle);
            }
        }
        // Если rename не удался — ничего не делаем (оставляем старый файл)
    }

    /**
     * Сдвигает архивы: .1 → .2, .2 → .3, ..., .4 → .5
     * Удаляет .5, если MAX_BACKUP_COUNT = 5
     */
    private function shiftBackups(string $filePath): void {
        for ($i = self::MAX_BACKUP_COUNT - 1; $i >= 1; $i--) {
            $current = $filePath . ".$i";
            $next = $filePath . "." . ($i + 1);
            if (is_file($current)) {
                if (is_file($next)) {
                    @unlink($next); // удаляем следующий, чтобы освободить место
                }
                @rename($current, $next);
            }
        }
    }

    /**
     * Удаляет самый старый бэкап (.MAX_BACKUP_COUNT), если он существует
     */
    private function cleanupOldestBackup(string $filePath): void {
        $oldest = $filePath . '.' . self::MAX_BACKUP_COUNT;
        if (is_file($oldest)) {
            @unlink($oldest);
        }
    }
}
