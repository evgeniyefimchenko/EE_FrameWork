<?php

namespace classes\helpers;

abstract class Singleton {

    /**
     * Массив для хранения единственных экземпляров классов.
     * @var array
     */
    private static $_aInstances = array();

    /**
     * Возвращает единственный экземпляр класса.
     * Если экземпляр не существует, создается новый.
     * 
     * @return mixed|object Возвращает экземпляр класса или 0, если класс не существует.
     */
    public static function getInstance() {
        $sClassName = get_called_class();
        if (class_exists($sClassName)) {
            if (!isset(self::$_aInstances[$sClassName]))
                self::$_aInstances[$sClassName] = new $sClassName();
            return self::$_aInstances[$sClassName];
        }
        return 0;
    }

    /**
     * Упрощенный метод для получения экземпляра класса.
     * 
     * @return mixed|object
     */
    public static function gI() {
        return self::getInstance();
    }

    /**
     * Закрытие клонирования экземпляра.
     */
    private function __clone() {}

    /**
     * Закрытие вызова конструктора извне.
     */
    private function __construct() {}
}

/**
 * Класс для безопасного выполнения запросов к базе данных PostgreSQL.
 * Реализует паттерн Singleton.
 */
class SafePostgres extends Singleton {

    /**
     * Подключение к базе данных.
     * @var \PDO
     */
    private $conn;

    /**
     * Статистика выполненных запросов.
     * @var array
     */
    private $stats;

    /**
     * Режим обработки ошибок.
     * @var string
     */
    private $emode;

    /**
     * Имя класса исключений.
     * @var string
     */
    private $exname;

    /**
     * Настройки подключения по умолчанию.
     * @var array
     */
    private $defaults = array(
        'host' => ENV_DB_HOST,
        'user' => ENV_DB_USER,
        'pass' => ENV_DB_PASS,
        'db' => ENV_DB_NAME,
        'port' => '5432',
        'charset' => 'utf8',
        'errmode' => 'exception', // или exception
        'exception' => 'Exception', // Имя класса исключения
    );

    /**
     * Конструктор класса SafePostgres.
     * Инициализирует подключение к базе данных PostgreSQL с использованием PDO.
     * 
     * @param array $opt Параметры подключения, которые могут переопределить значения по умолчанию.
     */
    function __construct($opt = array()) {
        $opt = array_merge($this->defaults, $opt);
        $this->emode = $opt['errmode'];
        $this->exname = $opt['exception'];

        $dsn = "pgsql:host={$opt['host']};port={$opt['port']};dbname={$opt['db']}";
        try {
            $this->conn = new \PDO($dsn, $opt['user'], $opt['pass'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $this->conn->exec("SET NAMES '{$opt['charset']}'");
        } catch (\PDOException $e) {
            $this->error('Ошибка подключения: ' . $e->getMessage());
        }
    }

    /**
     * Выполняет SQL-запрос с поддержкой плейсхолдеров.
     * 
     * @param string $query SQL-запрос с плейсхолдерами.
     * @param mixed ...$args Аргументы для замены плейсхолдеров в запросе.
     * @return bool Результат выполнения запроса.
     */
    public function query($query, ...$args) {
        return $this->rawQuery($this->prepareQuery(func_get_args()));
    }

    /**
     * Извлекает строку из результата запроса.
     * 
     * @param \PDOStatement $result Результат выполнения запроса.
     * @param int $mode Режим выборки данных, по умолчанию - ассоциативный массив.
     * @return array|false Возвращает массив данных или false, если строк нет.
     */
    public function fetch($result, $mode = \PDO::FETCH_ASSOC) {
        return $result->fetch($mode);
    }

    /**
     * Возвращает количество измененных строк.
     * 
     * @return int Количество строк, затронутых последним запросом.
     */
    public function affectedRows() {
        return $this->conn->rowCount();
    }

    /**
     * Возвращает ID последней вставленной строки.
     * 
     * @return string|false ID последней вставленной строки или false при ошибке.
     */
    public function insertId() {
        return $this->conn->lastInsertId();
    }

    /**
     * Возвращает количество строк в результате запроса.
     * 
     * @param \PDOStatement $result Результат запроса.
     * @return int Количество строк в результате.
     */
    public function numRows($result) {
        return $result->rowCount();
    }

    /**
     * Освобождает память, занятую результатом запроса.
     * 
     * @param \PDOStatement $result Результат запроса.
     */
    public function free($result) {
        $result = null; // В PDO освобождение результата - это простое его удаление
    }

    /**
     * Извлекает одно значение из результата запроса.
     * 
     * @param string $query SQL-запрос с плейсхолдерами.
     * @param mixed ...$args Аргументы для замены плейсхолдеров.
     * @return mixed|false Значение первой колонки первой строки результата или false, если ничего не найдено.
     */
    public function getOne() {
        $query = $this->prepareQuery(func_get_args());
        $stmt = $this->rawQuery($query);
        return $stmt ? $stmt->fetchColumn() : false;
    }

    /**
     * Извлекает одну строку из результата запроса.
     * 
     * @param string $query SQL-запрос с плейсхолдерами.
     * @param mixed ...$args Аргументы для замены плейсхолдеров.
     * @return array|false Ассоциативный массив с данными строки или false, если ничего не найдено.
     */
    public function getRow() {
        $query = $this->prepareQuery(func_get_args());
        $stmt = $this->rawQuery($query);
        return $stmt ? $stmt->fetch() : false;
    }

    /**
     * Извлекает один столбец данных из результата запроса.
     * 
     * @param string $query SQL-запрос с плейсхолдерами.
     * @param mixed ...$args Аргументы для замены плейсхолдеров.
     * @return array|false Массив данных из одного столбца или false, если ничего не найдено.
     */
    public function getCol() {
        $query = $this->prepareQuery(func_get_args());
        $stmt = $this->rawQuery($query);
        return $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
    }

    /**
     * Извлекает все строки из результата запроса.
     * 
     * @param string $query SQL-запрос с плейсхолдерами.
     * @param mixed ...$args Аргументы для замены плейсхолдеров.
     * @return array Массив строк результата.
     */
    public function getAll() {
        $query = $this->prepareQuery(func_get_args());
        $stmt = $this->rawQuery($query);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Извлекает строки с индексами, основанными на значении указанного поля.
     * 
     * @param string $index Поле для индексации.
     * @param string $query SQL-запрос с плейсхолдерами.
     * @param mixed ...$args Аргументы для замены плейсхолдеров.
     * @return array Ассоциативный массив с индексами на основе значения поля.
     */
    public function getInd($index) {
        $args = func_get_args();
        array_shift($args); // Удаляем индекс из аргументов
        $query = $this->prepareQuery($args);
        $stmt = $this->rawQuery($query);

        $ret = [];
        while ($row = $stmt->fetch()) {
            $ret[$row[$index]] = $row;
        }
        return $ret;
    }

    /**
     * Извлекает столбец данных с индексами на основе указанного поля.
     * 
     * @param string $index Поле для индексации.
     * @param string $query SQL-запрос с плейсхолдерами.
     * @param mixed ...$args Аргументы для замены плейсхолдеров.
     * @return array Ассоциативный массив, где ключи - значения индекса, а значения - остальные поля.
     */
    public function getIndCol($index) {
        $args = func_get_args();
        array_shift($args);
        $query = $this->prepareQuery($args);
        $stmt = $this->rawQuery($query);

        $ret = [];
        while ($row = $stmt->fetch()) {
            $key = $row[$index];
            unset($row[$index]);
            $ret[$key] = reset($row);
        }
        return $ret;
    }

    /**
     * Парсит SQL-запрос, заменяя плейсхолдеры.
     * 
     * @param string $query SQL-запрос с плейсхолдерами.
     * @param mixed ...$args Аргументы для замены плейсхолдеров.
     * @return string Подготовленный SQL-запрос.
     */
    public function parse() {
        return $this->prepareQuery(func_get_args());
    }

    /**
     * Проверяет, присутствует ли значение в "белом списке".
     * 
     * @param mixed $input Проверяемое значение.
     * @param array $allowed Список разрешенных значений.
     * @param mixed $default Значение по умолчанию, если входное значение не найдено.
     * @return mixed Одиночное значение или массив проверенных значений.
     */
    public function whiteList($input, $allowed, $default = FALSE) {
        $found = array_search($input, $allowed);
        return ($found === FALSE) ? $default : $allowed[$found];
    }

    /**
     * Фильтрует массив, оставляя только разрешенные ключи.
     * 
     * @param array $input Входной массив.
     * @param array $allowed Список разрешенных ключей.
     * @return array Отфильтрованный массив.
     */
    public function filterArray($input, $allowed) {
        foreach (array_keys($input) as $key) {
            if (!in_array($key, $allowed)) {
                unset($input[$key]);
            }
        }
        return $input;
    }

    /**
     * Возвращает последний выполненный запрос.
     * 
     * @return string|null Последний выполненный SQL-запрос или NULL, если запросов не было.
     */
    public function lastQuery() {
        return end($this->stats)['query'];
    }

    /**
     * Возвращает статистику по всем выполненным запросам.
     * 
     * @return array Массив со статистикой запросов.
     */
    public function getStats() {
        return $this->stats;
    }

    /**
     * Выполняет SQL-запрос, используя PDO и возвращает результат.
     * 
     * @param string $query Подготовленный SQL-запрос.
     * @return \PDOStatement|false Результат выполнения запроса или false при ошибке.
     */
    private function rawQuery($query) {
        $start = microtime(true);
        $stmt = $this->conn->prepare($query);

        if (!$stmt->execute()) {
            $error = $stmt->errorInfo();
            $this->error("Ошибка: {$error[2]}. Полный запрос: [$query]");
        }

        $timer = microtime(true) - $start;
        $this->stats[] = [
            'query' => $query,
            'timer' => $timer,
            'total_time' => isset(end($this->stats)['total_time']) ? end($this->stats)['total_time'] + $timer : $timer
        ];

        return $stmt;
    }

    /**
     * Подготавливает SQL-запрос с учетом плейсхолдеров.
     * 
     * @param array $args Аргументы для подстановки в запрос.
     * @return string Подготовленный SQL-запрос.
     */
    private function prepareQuery($args) {
        $query = '';
        $raw = array_shift($args);
        $array = preg_split('~(\?[nsiuap])~u', $raw, 0, PREG_SPLIT_DELIM_CAPTURE);
        $anum = count($args);
        $pnum = floor(count($array) / 2);

        if ($pnum != $anum) {
            $this->error("Количество аргументов ($anum) не соответствует количеству плейсхолдеров ($pnum) в [$raw]");
        }

        foreach ($array as $i => $part) {
            if (($i % 2) == 0) {
                $query .= $part;
                continue;
            }
            $value = array_shift($args);
            switch ($part) {
                case '?n':
                    $part = $this->escapeIdent($value);
                    break;
                case '?s':
                    $part = $this->escapeString($value);
                    break;
                case '?i':
                    $part = $this->escapeInt($value);
                    break;
                case '?a':
                    $part = $this->createIN($value);
                    break;
                case '?u':
                    $part = $this->createSET($value);
                    break;
                case '?p':
                    $part = $value;
                    break;
            }
            $query .= $part;
        }
        return $query;
    }

    /**
     * Экранирует целое число для безопасного использования в SQL-запросе.
     * 
     * @param mixed $value Целочисленное значение.
     * @return string Целочисленное значение или NULL.
     */
    private function escapeInt($value) {
        if ($value === NULL) {
            return 'NULL';
        }
        if (!is_numeric($value)) {
            $this->error("Целочисленный плейсхолдер (?i) ожидает числовое значение, передано: " . gettype($value));
            return FALSE;
        }
        return (int)$value;
    }

    /**
     * Экранирует строку для безопасного использования в SQL-запросе.
     * 
     * @param string $value Строковое значение.
     * @return string Экранированное строковое значение.
     */
    private function escapeString($value) {
        return $this->conn->quote($value);
    }

    /**
     * Экранирует идентификатор (имя таблицы или столбца).
     * 
     * @param string $value Имя таблицы или столбца.
     * @return string Экранированное имя таблицы или столбца.
     */
    private function escapeIdent($value) {
        if ($value) {
            return '"' . str_replace('"', '""', $value) . '"';
        } else {
            $this->error("Пустое значение для идентификатора (?n)");
        }
    }

    /**
     * Формирует список значений для оператора IN.
     * 
     * @param array $data Массив значений для оператора IN.
     * @return string Строка значений для IN, разделенных запятыми.
     */
    private function createIN($data) {
        if (!is_array($data)) {
            $this->error("Плейсхолдер IN (?a) ожидает массив");
        }
        if (!$data) {
            return 'NULL';
        }
        return implode(',', array_map([$this, 'escapeString'], $data));
    }

    /**
     * Формирует строку для оператора SET в запросах типа UPDATE.
     * 
     * @param array $data Ассоциативный массив, где ключи - имена полей, а значения - их новые значения.
     * @return string Строка для оператора SET.
     */
    private function createSET($data) {
        if (!is_array($data)) {
            $this->error("Плейсхолдер SET (?u) ожидает массив");
        }
        if (!$data) {
            $this->error("Пустой массив для плейсхолдера SET (?u)");
        }
        $query = '';
        foreach ($data as $key => $value) {
            $query .= $this->escapeIdent($key) . '=' . $this->escapeString($value) . ',';
        }
        return rtrim($query, ',');
    }

    /**
     * Обработка ошибок в зависимости от режима.
     * 
     * @param string $err Текст ошибки.
     * @throws \Exception В случае, если установлен режим "exception".
     */
    private function error($err) {
        $err = __CLASS__ . ": " . $err;
        if ($this->emode == 'error') {
            trigger_error($err, E_USER_ERROR);
        } else {
            throw new $this->exname($err);
        }
    }
}
