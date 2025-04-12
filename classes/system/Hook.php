<?php

namespace classes\system;

class Hook {

    protected static $callbacks = [];

    /**
     * Добавляет новый коллбек для указанного ключа с приоритетом
     * @param string $key Ключ для коллбека
     * @param callable|string $callback Функция коллбек или имя функции
     * @param int $priority Приоритет выполнения коллбека
     * @return bool Успешность добавления коллбека
     */
    public static function add(string $key, $callback, int $priority = 10): bool {
        if (empty($key) || (!is_callable($callback) && !is_string($callback))) {
            return false;
        }
        self::$callbacks[$key][] = ['callback' => $callback, 'priority' => $priority];
        usort(self::$callbacks[$key], function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
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
        return array_key_exists($key, self::$callbacks);
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
     * Возвращает все зарегистрированные функции и события
     * @return array Ассоциативный массив всех зарегистрированных событий и их колбеков
     */
    public static function getAllHooks(): array {
        return self::$callbacks;
    }
}
