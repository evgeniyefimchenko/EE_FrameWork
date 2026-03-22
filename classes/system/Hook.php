<?php

namespace classes\system;

class Hook {

    protected static $callbacks = [];
    protected static int $sequence = 0;

    /**
     * Добавляет новый коллбек для указанного ключа с приоритетом
     * @param string $key Ключ для коллбека
     * @param callable|string $callback Функция коллбек или имя функции
     * @param int $priority Приоритет выполнения коллбека
     * @param string|null $source Источник регистрации (например, core или extension:vendor/package)
     * @param string|null $extensionId Идентификатор расширения
     * @return bool Успешность добавления коллбека
     */
    public static function add(string $key, $callback, int $priority = 10, ?string $source = null, ?string $extensionId = null): bool {
        if (empty($key) || (!is_callable($callback) && !is_string($callback))) {
            return false;
        }
        self::$callbacks[$key][] = [
            'callback' => $callback,
            'priority' => $priority,
            'source' => $source,
            'extension_id' => $extensionId,
            'callback_id' => self::buildCallbackId($callback),
            'sequence' => ++self::$sequence,
        ];
        usort(self::$callbacks[$key], function ($a, $b) {
            return ($a['priority'] <=> $b['priority']) ?: (($a['sequence'] ?? 0) <=> ($b['sequence'] ?? 0));
        });
        return true;
    }

    /**
     * Удаляет коллбек для указанного ключа
     * @param string $key Ключ коллбека
     * @return bool Успешность удаления коллбека
     */
    public static function remove(string $key): bool {
        if (self::exists($key)) {
            unset(self::$callbacks[$key]);
            return true;
        }
        return false;
    }

    /**
     * Проверяет существование коллбека для указанного ключа
     * @param string $key Ключ коллбека
     * @return bool Существует ли коллбек для указанного ключа
     */
    public static function exists(string $key): bool {
        return array_key_exists($key, self::$callbacks) && self::$callbacks[$key] !== [];
    }

    /**
     * Проверяет, зарегистрирован ли конкретный callback у указанного ключа.
     */
    public static function hasCallback(string $key, $callback): bool {
        if (!self::exists($key)) {
            return false;
        }

        $callbackId = self::buildCallbackId($callback);
        foreach (self::$callbacks[$key] as $registeredCallback) {
            if (($registeredCallback['callback_id'] ?? null) === $callbackId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Удаляет только указанный callback из конкретного hook key.
     */
    public static function removeCallback(string $key, $callback): bool {
        if (!self::exists($key)) {
            return false;
        }

        $callbackId = self::buildCallbackId($callback);
        $beforeCount = count(self::$callbacks[$key]);
        self::$callbacks[$key] = array_values(array_filter(
            self::$callbacks[$key],
            static fn(array $registeredCallback): bool => ($registeredCallback['callback_id'] ?? null) !== $callbackId
        ));

        if (self::$callbacks[$key] === []) {
            unset(self::$callbacks[$key]);
        }

        return $beforeCount !== count(self::$callbacks[$key] ?? []);
    }

    /**
     * Удаляет все callback-ы, зарегистрированные указанным source.
     */
    public static function removeBySource(string $source): int {
        $source = trim($source);
        if ($source === '') {
            return 0;
        }

        $removed = 0;
        foreach (array_keys(self::$callbacks) as $key) {
            $beforeCount = count(self::$callbacks[$key]);
            self::$callbacks[$key] = array_values(array_filter(
                self::$callbacks[$key],
                static fn(array $registeredCallback): bool => (string) ($registeredCallback['source'] ?? '') !== $source
            ));
            $removed += $beforeCount - count(self::$callbacks[$key]);
            if (self::$callbacks[$key] === []) {
                unset(self::$callbacks[$key]);
            }
        }

        return $removed;
    }

    /**
     * Выполняет коллбек для указанного ключа с переданными аргументами
     * @param string $key Ключ коллбека
     * @param mixed ...$args Аргументы для коллбека
     * @return void
     */
    public static function run(string $key, ...$args): void {
        if (self::exists($key)) {
            foreach (self::$callbacks[$key] as $callback) {
                if (is_callable($callback['callback'])) {
                    call_user_func_array($callback['callback'], $args);
                }
            }
        }
    }

    /**
     * Пропускает значение через цепочку зарегистрированных коллбеков и возвращает итоговый результат
     * @param string $key Ключ коллбека
     * @param mixed $value Исходное значение для фильтрации
     * @param mixed ...$args Дополнительные аргументы для коллбеков
     * @return mixed
     */
    public static function filter(string $key, $value, ...$args) {
        if (!self::exists($key)) {
            return $value;
        }
        foreach (self::$callbacks[$key] as $callback) {
            if (is_callable($callback['callback'])) {
                $value = call_user_func_array($callback['callback'], array_merge([$value], $args));
            }
        }
        return $value;
    }

    /**
     * Возвращает первый ненулевой результат из цепочки коллбеков
     * Удобно для управляющих хуков, которые могут отменить или перенаправить операцию
     * @param string $key Ключ коллбека
     * @param mixed $default Значение по умолчанию, если никто не вернул результат
     * @param mixed ...$args Аргументы для коллбеков
     * @return mixed
     */
    public static function until(string $key, $default = null, ...$args) {
        if (!self::exists($key)) {
            return $default;
        }
        foreach (self::$callbacks[$key] as $callback) {
            if (!is_callable($callback['callback'])) {
                continue;
            }
            $result = call_user_func_array($callback['callback'], $args);
            if ($result !== null) {
                return $result;
            }
        }
        return $default;
    }

    /**
     * Возвращает все зарегистрированные функции и события
     * @return array Ассоциативный массив всех зарегистрированных событий и их колбеков
     */
    public static function getAllHooks(): array {
        return self::$callbacks;
    }

    /**
     * Сбрасывает все зарегистрированные hooks.
     */
    public static function reset(): void {
        self::$callbacks = [];
        self::$sequence = 0;
    }

    /**
     * Формирует стабильный идентификатор callback-а для поиска и удаления.
     */
    protected static function buildCallbackId($callback): string {
        if ($callback instanceof \Closure) {
            return 'closure:' . spl_object_hash($callback);
        }

        if (is_string($callback)) {
            return 'string:' . ltrim($callback, '\\');
        }

        if (is_array($callback) && count($callback) === 2) {
            $target = $callback[0];
            $method = (string) $callback[1];

            if (is_object($target)) {
                return 'object:' . spl_object_hash($target) . '::' . $method;
            }

            return 'class:' . ltrim((string) $target, '\\') . '::' . $method;
        }

        if (is_object($callback) && method_exists($callback, '__invoke')) {
            return 'invokable:' . spl_object_hash($callback);
        }

        return 'unknown:' . md5(serialize($callback));
    }
}
