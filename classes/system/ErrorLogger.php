<?php

namespace classes\system;

/**
 * Класс для регистрации ошибок
 */
class ErrorLogger {
    
    private $errorMessage;
    private $functionName;
    public $result;
    /**
     * Конструктор класса
     * @param string $errorMessage Сообщение об ошибке
     * @param string $functionName Имя функции, в которой произошла ошибка
     */
    public function __construct($errorMessage, $functionName) {
        $this->errorMessage = $errorMessage;
        $this->functionName = $functionName;
        $this->logError();
        $this->result = $this->getErrorInfo();
    }

    /**
     * Метод для записи ошибки в файл
     */
    private function logError() {
        SysClass::preFile('errors', $this->functionName, $this->errorMessage, [
            'function_name' => $this->functionName,
            'error_message' => $this->errorMessage,
        ]);
    }
    
    /**
     * Метод для получения информации об ошибке
     * @return array Массив с информацией об ошибке
     */
    private function getErrorInfo() {
        return [
            'error_message' => $this->errorMessage,
            'function_name' => $this->functionName,
        ];
    }
}