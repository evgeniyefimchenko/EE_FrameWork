<?php

namespace classes\system;

/**
 * Класс для регистрации ошибок
 */
class ErrorLogger {
    
    private string $errorMessage;
    private string $functionName;
    public array $result;

    /**
     * Конструктор класса
     * @param string $errorMessage Сообщение об ошибке
     * @param string $functionName Имя функции, в которой произошла ошибка
     * @param string $prefix Префикс для логирования
     * @param string $details Дополнительные детали
     */
    public function __construct(string $errorMessage, string $functionName, string $prefix = '', string $details = ''): void {
        $this->errorMessage = $errorMessage;
        $this->functionName = $functionName;
        $this->logError($prefix, $details);
        $this->result = $this->getErrorInfo();
    }

    /**
     * Метод для записи ошибки в файл
     * @param string $prefix Префикс для логирования
     * @param string $details Дополнительные детали
     */
    private function logError(string $prefix = '', string $details = ''): void {
        $prefix = empty($prefix) ? 'ee_' : $prefix . '_';
        SysClass::preFile($prefix . 'errors', $this->functionName, $this->errorMessage, $details);
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
}