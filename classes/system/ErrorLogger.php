<?php

namespace classes\system;

/**
 * Legacy-совместимая обёртка поверх нового Logger.
 */
class ErrorLogger {

    private string $errorMessage;
    private string $functionName;
    public array $result;

    /**
     * @param string $errorMessage Сообщение об ошибке
     * @param string $functionName Имя функции, в которой произошла ошибка
     * @param string $prefix Префикс/канал для логирования
     * @param mixed $details Дополнительные детали
     */
    public function __construct(string $errorMessage, string $functionName, string $prefix = '', mixed $details = '') {
        $this->errorMessage = $errorMessage;
        $this->functionName = $functionName;

        $channel = trim($prefix) !== '' ? $prefix . '_errors' : 'ee_errors';
        $context = [];
        if (is_array($details)) {
            $context = $details;
        } elseif ($details !== '' && $details !== null) {
            $context = ['details' => is_scalar($details) ? (string) $details : var_export($details, true)];
        }

        if ($details instanceof \Throwable) {
            $context = [
                'throwable' => [
                    'class' => get_class($details),
                    'message' => $details->getMessage(),
                    'file' => $details->getFile(),
                    'line' => $details->getLine(),
                    'trace' => $details->getTrace(),
                ]
            ];
        }

        Logger::error($channel, $this->errorMessage, $context, [
            'initiator' => $this->functionName,
            'details' => $details,
            'include_trace' => true,
        ]);

        $this->result = [
            'error' => true,
            'error_message' => $this->errorMessage,
            'function_name' => $this->functionName,
        ];
    }
}
