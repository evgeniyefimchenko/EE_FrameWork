<?php

use classes\plugins\SafeMySQL;
use classes\system\Users;
use classes\system\SysClass;
/**
 * Модель системных действий
 */
class ModelSystems {

    /**
     * Очищает все таблицы в базе данных.
     * Этот метод получает список всех таблиц в текущей базе данных,
     * и выполняет операцию DROP на каждой таблице для её очистки.
     * Операции выполняются в рамках одной транзакции, чтобы гарантировать,
     * что все таблицы будут успешно очищены, или ни одна из таблиц не будет удалена в случае ошибки
     * @param int $user_id Кто вызвал
     * @throws Exception Если произошла ошибка во время очистки таблиц
     */
    public function killDB($user_id) {
        $tables = SafeMySQL::gi()->getCol("SHOW TABLES");
        if ($tables) {
            SafeMySQL::gi()->query("START TRANSACTION");
            try {
                SafeMySQL::gi()->query("SET FOREIGN_KEY_CHECKS=0");  // отключаем проверку внешних ключей
                foreach ($tables as $table) {
                    SafeMySQL::gi()->query("DROP TABLE ?n", $table);
                }
                SafeMySQL::gi()->query("SET FOREIGN_KEY_CHECKS=1");  // включаем проверку внешних ключей обратно
                SafeMySQL::gi()->query("COMMIT");
            } catch (Exception $e) {
                SafeMySQL::gi()->query("ROLLBACK");
                ClassNotifications::add_notification_user($user_id, ['text' => $e, 'status' => 'danger']);
                return false;
            }
        } else {
            ClassNotifications::add_notification_user($user_id, ['text' => 'No tables found in the database.', 'status' => 'info']);
            return false;
        }
        // Пересоздание БД и регистрация первичных пользователей
        new Users(true);
        $flagFilePath = ENV_LOGS_PATH . 'test_data_created.txt';
        if (file_exists($flagFilePath)) {
            unlink($flagFilePath);
        }
        return true;
    }

    /**
     * Получает логи PHP из указанного файла логов и возвращает их в отфильтрованном и отсортированном виде
     * @param mixed $order Параметр сортировки, может быть строкой сортировки или false для сортировки по умолчанию
     * @param mixed $where Условие фильтрации, может быть строкой условия фильтрации или false для отсутствия фильтрации
     * @param int|false $start Начальная позиция для выборки логов, false означает начало с 0
     * @param int|false $limit Максимальное количество логов для возврата, false устанавливает лимит в 100
     * @param string $type Тип логов для обработки, 'fatal_errors' для логов фатальных ошибок и любое другое значение для стандартных логов PHP
     * @return array Возвращает массив с данными логов и общим количеством найденных логов
     */
    public function get_php_logs($order, $where, $start, $limit = 100, $type = 'fatal_errors') {
        $start = ($start !== false) ? $start : 0;
        $limit = ($limit !== false) ? $limit : 100;
        $order = ($order !== false) ? $order : 'error_type ASC, date_time DESC';
        $logFilePath = ENV_LOGS_PATH . 'php_errors.log';
        if ($type == 'fatal_errors') {
            $logFilePath = ENV_LOGS_PATH . 'fatal_errors.txt';
        }
        $logs = ['data' => [], 'total_count' => 0];
        if (file_exists($logFilePath)) {
            $file = new SplFileObject($logFilePath);
            $currentLog = null;
            $isCollectingStackTrace = false;
            while (!$file->eof()) {
                $line = $file->fgets();
                if ($type == 'fatal_errors') {
                    if (strpos($line, 'Date: ') === 0) {
                        if ($currentLog && $this->filterLog($currentLog, $where)) {
                            $logs['data'][] = $currentLog;
                        }
                        $currentLog = [
                            'date_time' => trim(substr($line, 6)),
                            'error_type' => 'PHP Fatal error',
                            'message' => '',
                            'stack_trace' => []
                        ];
                        $isCollectingStackTrace = false;
                    } elseif ($currentLog && strpos($line, 'Message: ') === 0) {
                        $currentLog['message'] = trim(substr($line, 9));
                    } elseif ($currentLog && strpos($line, 'Stack trace:') === 0) {
                        $isCollectingStackTrace = true;
                    } elseif ($currentLog && $isCollectingStackTrace) {
                        if (trim($line) !== '') {
                            $currentLog['stack_trace'][] = trim($line);
                        }
                    }                    
                } else if (preg_match('/\[(.*?)\] (.*?): (.*)/', $line, $matches)) {
                    // Обработка стандартного формата (php_errors.log)   
                    if ($currentLog) {
                        if ($this->filterLog($currentLog, $where)) {
                            $logs['data'][] = $currentLog;
                        }
                    }
                    $currentLog = [
                        'date_time' => $matches[1],
                        'error_type' => $matches[2],
                        'message' => $matches[3],
                        'stack_trace' => []
                    ];
                } elseif ($currentLog && strpos($line, '#') === 0) {
                    $currentLog['stack_trace'][] = trim($line);
                }
            }
            if ($currentLog && $this->filterLog($currentLog, $where)) {
                $logs['data'][] = $currentLog;
            }
            $logs['total_count'] = count($logs['data']);
            $logs['data'] = $this->sortAndPaginateLogs($logs['data'], $order, $start, $limit);
        } else {
            $logs['data'] = [[
            'date_time' => '',
            'error_type' => '',
            'message' => '',
            'stack_trace' => []
            ]];
            $logs['total_count'] = 0;
        }        
        return $logs;
    }
    
    /**
     * Разбирает блок логов, извлекая из него информацию о времени события, инициаторе, результате, деталях и трассировке стека
     * Форматирует извлеченные данные в структурированный массив
     * @param string $logBlock Строка, содержащая блок логов
     * @return array Ассоциативный массив с данными лога, включая время события, инициатора, результат, детали и трассировку стека
     */
    private function parseLogBlock($logBlock) {
        $log = [
            'date_time' => '',
            'initiator' => '',
            'result' => '',
            'details' => '',
            'stack_trace' => ''
        ];
        foreach (explode("\n", $logBlock) as $line) {
            if (strpos($line, 'Время события: ') === 0) {
                $log['date_time'] = trim(substr($line, strlen('Время события: ')));
            } elseif (strpos($line, 'Инициатор: ') === 0) {
                $log['initiator'] = trim(substr($line, strlen('Инициатор: ')));
            } elseif (strpos($line, 'Результат: ') === 0) {
                $log['result'] = trim(substr($line, strlen('Результат: ')), " '");                
            } elseif (strpos($line, 'Детали: ') === 0) {
                $log['details'] = trim(substr($line, strlen('Детали: ')));
            } elseif (strpos($line, 'Полный стек вызовов: ') === 0) {
                $json_data = trim(substr($line, strlen('Полный стек вызовов: ')));
                if (SysClass::ee_isValidJson($json_data)) {
                    $log['stack_trace'] = json_decode($json_data, true);                    
                    $stack = '';
                    $count = 0;
                    foreach ($log['stack_trace'] as $item) {
                        $string = '';
                        foreach ($item as $key => $value) {
                            $string .= '<b>' . $key . '</b>: ' . $value . '<br/>';                            
                        }
                        $count++;
                        $stack .= '#' . $count . '<br/>' . trim($string) . '<hr/>';
                    }                    
                    $log['stack_trace'] = $stack;                    
                } else {
                    $log['stack_trace'] = '';
                }
            }
        }        
        return $log;
    }
    
    /**
     * Получает все логи из заданных директорий и файлов
     * Применяет фильтрацию, сортировку и пагинацию к полученным логам
     * @param mixed $order Параметры сортировки, могут быть строкой сортировки или false для пропуска сортировки
     * @param mixed $where Условия фильтрации, могут быть строкой с условиями или false для пропуска фильтрации
     * @param int $start Начальная позиция для пагинации
     * @param int $limit Количество логов для возврата на одной странице
     * @return array Возвращает массив логов после применения фильтрации, сортировки и пагинации
     */
    public function get_all_logs($order, $where, $start, $limit) {
        $logsDir = ENV_LOGS_PATH;
        $filteredLogs = [];
        $directories = array_filter(glob($logsDir . '*'), 'is_dir');

        foreach ($directories as $dir) {
            $dirName = basename($dir);
            $files = glob($dir . '/*.txt');
            foreach ($files as $file) {
                $fileName = basename($file, '.txt');
                $fileContent = file_get_contents($file);
                $logBlocks = explode("{END}", $fileContent);
                foreach ($logBlocks as $logBlock) {
                    if (strpos($logBlock, '{START}') !== false) {
                        $logBlock = str_replace('{START}', '', $logBlock);
                        $currentLog = $this->parseLogBlock($logBlock);
                        $currentLog = array_merge($currentLog, [
                            'type_log' => $dirName,
                            'date_log' => $fileName,
                        ]);
                        if ($this->filterLog($currentLog, $where)) {
                            $filteredLogs[] = $currentLog;
                        }
                    }
                }
            }
        }

        $sortedAndPaginatedLogs = $this->sortAndPaginateLogs($filteredLogs, $order, $start, $limit);
        return [
            'data' => $sortedAndPaginatedLogs,
            'total_count' => count($filteredLogs)
        ];
    }
    
    /**
    * Фильтрует лог на основе заданного условия
    * @param array $log Массив, представляющий одну запись лога
    * @param mixed $where Строка с условиями фильтрации, может быть false для отсутствия фильтрации
    * @return bool Возвращает true, если лог соответствует всем условиям фильтрации, иначе false
    */   
    private function filterLog($log, $where) {
        if ($where === false) {
            return true;
        }
        $conditions = explode(' AND ', $where);
        foreach ($conditions as $condition) {
            if (strpos($condition, 'LIKE') !== false) {
                // Условие LIKE
                list($field, $value) = explode(' LIKE ', $condition);
                $value = str_replace(['\'', '%'], '', $value);
                if (strpos($log[trim($field)], $value) === false) {
                    return false;
                }
            } elseif (strpos($condition, '>=') !== false || strpos($condition, '<=') !== false) {
                // Условия сравнения даты и времени
                list($field, $value) = preg_split('/(>=|<=)/', $condition);
                $value = str_replace('\'', '', trim($value));
                $operator = (strpos($condition, '>=') !== false) ? '>=' : '<=';
                if (!$this->compareDateTime($log[trim($field)], $value, $operator)) {
                    return false;
                }
            }
            // Добавьте здесь другие условия фильтрации, если необходимо
        }
        return true;
    }
    
    /**
     * Сравнивает дату и время из лога с заданным условием
     * @param string $logTime Дата и время из лога
     * @param string $conditionTime Дата и время, указанные в условии
     * @param string $operator Оператор сравнения, '>=', '<='
     * @return bool Возвращает true, если сравнение соответствует заданному оператору, иначе false
     */
    private function compareDateTime($logTime, $conditionTime, $operator) {
        $logTimestamp = strtotime($logTime);
        $conditionTimestamp = strtotime($conditionTime);

        if ($operator == '>=') {
            return $logTimestamp >= $conditionTimestamp;
        } elseif ($operator == '<=') {
            return $logTimestamp <= $conditionTimestamp;
        }

        return false;
    }

    /**
     * Сортирует и применяет пагинацию к массиву логов
     * @param array $logs Массив логов для сортировки и пагинации
     * @param mixed $order Параметры сортировки, могут быть строкой сортировки или false для пропуска сортировки
     * @param int $start Начальная позиция для пагинации
     * @param int $limit Количество логов для возврата 
     * @return array Возвращает массив логов после применения сортировки и пагинации
     */    
    private function sortAndPaginateLogs($logs, $order, $start, $limit) {
        if ($order !== false) {
            usort($logs, function ($a, $b) use ($order) {
                $orders = explode(',', $order);
                foreach ($orders as $o) {
                    list($field, $direction) = explode(' ', trim($o));
                    if ($a[$field] == $b[$field]) {
                        continue;
                    }
                    return ($direction == 'ASC' ? ($a[$field] < $b[$field]) : ($a[$field] > $b[$field])) ? -1 : 1;
                }
                return 0;
            });
        }
        return array_slice($logs, $start, $limit);
    }

    /**
     * Читает и анализирует лог-файл фатальных ошибок PHP.
     * Функция считывает содержимое файла логов фатальных ошибок PHP, разбирает его 
     * и формирует массив с детальной информацией о каждой фатальной ошибке, 
     * включая время, сообщение об ошибке и стек вызовов.
     * @return array Массив с информацией о фатальных ошибках. Каждый элемент массива содержит:
     *               - 'date_time'    => время возникновения ошибки,
     *               - 'message'     => сообщение об ошибке,
     *               - 'stack_trace' => массив со стеком вызовов.
     */
    public function get_fatal_errors() {
        $logFilePath = ENV_LOGS_PATH . 'fatal_errors.txt';
        $fatalErrors = [];
        if (file_exists($logFilePath)) {
            $file = new SplFileObject($logFilePath);
            $currentError = null;
            while (!$file->eof()) {
                $line = $file->fgets();
                if (preg_match('/(\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
                    if ($currentError) {
                        // Добавляем предыдущую ошибку в массив
                        $fatalErrors[] = $currentError;
                    }
                    $currentError = [
                        'date_time' => $matches[1],
                        'message' => '',
                        'stack_trace' => []
                    ];
                } elseif ($currentError) {
                    if (trim($line) === 'array (') {
                        $currentError['message'] .= $line;
                    } elseif (strpos($line, ')') !== false && !next($file)) {
                        $currentError['message'] .= $line;
                    } elseif (strpos($line, '#') === 0) {
                        $currentError['stack_trace'][] = trim($line);
                    } else {
                        $currentError['message'] .= $line;
                    }
                }
            }
            if ($currentError) {
                $fatalErrors[] = $currentError;
            }
        } else {
            $fatalErrors[] = ['error' => 'Fatal error log file not found.'];
        }
        return $fatalErrors;
    }

    public function get_progect_logs() {
        $logFilePath = ENV_LOGS_PATH; // Содержит /home3/whrgijws/public_html/skku.shop/logs
    }

}
