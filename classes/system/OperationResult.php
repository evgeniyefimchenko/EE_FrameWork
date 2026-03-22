<?php

namespace classes\system;

/**
 * Единый контракт результата мутационных операций между моделями и контроллерами.
 */
final class OperationResult {

    private bool $success;
    private mixed $data;
    private string $message;
    private string $code;
    private array $context;

    private function __construct(bool $success, mixed $data = null, string $message = '', string $code = 'ok', array $context = []) {
        $this->success = $success;
        $this->data = $data;
        $this->message = trim($message);
        $this->code = trim($code) !== '' ? trim($code) : ($success ? 'ok' : 'error');
        $this->context = $context;
    }

    public static function success(mixed $data = null, string $message = '', string $code = 'ok', array $context = []): self {
        return new self(true, $data, $message, $code, $context);
    }

    public static function failure(string $message, string $code = 'error', array $context = []): self {
        return new self(false, null, $message, $code, $context);
    }

    public static function validation(string $message, array $context = []): self {
        return new self(false, null, $message, 'validation_error', $context);
    }

    public static function fromLegacy(mixed $value, array $options = []): self {
        if ($value instanceof self) {
            return $value;
        }

        $falseMessage = trim((string) ($options['false_message'] ?? 'Операция завершилась с ошибкой'));
        $successMessage = trim((string) ($options['success_message'] ?? ''));
        $failureCode = trim((string) ($options['failure_code'] ?? 'legacy_error'));

        if ($value instanceof ErrorLogger) {
            $message = trim((string) ($value->result['error_message'] ?? $falseMessage));
            return self::failure($message, 'legacy_error_logger', ['legacy' => $value->result]);
        }

        if (is_array($value)) {
            if (isset($value['error'])) {
                return self::failure((string) $value['error'], 'legacy_array_error', ['legacy' => $value]);
            }
            if (array_key_exists('success', $value) && $value['success'] === false) {
                return self::failure((string) ($value['message'] ?? $falseMessage), 'legacy_array_error', ['legacy' => $value]);
            }
            if (isset($value['status'])) {
                $status = strtolower(trim((string) $value['status']));
                if (in_array($status, ['error', 'failed', 'failure'], true)) {
                    return self::failure((string) ($value['message'] ?? $falseMessage), $status, ['legacy' => $value]);
                }
            }
            if ($value === []) {
                return self::success([], $successMessage);
            }
            return self::success($value, $successMessage);
        }

        if (is_bool($value)) {
            return $value
                ? self::success(true, $successMessage)
                : self::failure($falseMessage, $failureCode);
        }

        if (is_int($value) || (is_string($value) && is_numeric($value))) {
            return ((int) $value) > 0
                ? self::success(is_int($value) ? $value : (int) $value, $successMessage)
                : self::failure($falseMessage, $failureCode, ['legacy' => $value]);
        }

        if ($value === null) {
            return self::failure($falseMessage, $failureCode);
        }

        return self::success($value, $successMessage);
    }

    public function isSuccess(): bool {
        return $this->success;
    }

    public function isFailure(): bool {
        return !$this->success;
    }

    public function getData(): mixed {
        return $this->data;
    }

    public function getMessage(string $fallback = ''): string {
        return $this->message !== '' ? $this->message : $fallback;
    }

    public function getCode(): string {
        return $this->code;
    }

    public function getContext(): array {
        return $this->context;
    }

    public function getId(?array $keys = null): int {
        if (is_int($this->data)) {
            return $this->data > 0 ? $this->data : 0;
        }
        if (is_string($this->data) && is_numeric($this->data)) {
            return (int) $this->data;
        }
        if (!is_array($this->data)) {
            return 0;
        }

        $keys ??= [
            'id',
            'user_id',
            'role_id',
            'type_id',
            'property_id',
            'set_id',
            'category_id',
            'page_id',
            'entity_id',
            'template_id',
            'snippet_id',
            'value_id',
            'job_id',
        ];

        foreach ($keys as $key) {
            if (isset($this->data[$key]) && is_numeric($this->data[$key])) {
                return (int) $this->data[$key];
            }
        }

        return 0;
    }

    public function toArray(): array {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'code' => $this->code,
            'data' => $this->data,
            'context' => $this->context,
        ];
    }
}
