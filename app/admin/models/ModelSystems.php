<?php

use classes\plugins\SafeMySQL;
use classes\system\AuthService;
use classes\system\BackupService;
use classes\system\CacheManager;
use classes\system\Constants;
use classes\system\CronAgentService;
use classes\system\FileSystem;
use classes\system\ImportMediaQueueService;
use classes\system\Logger;
use classes\system\OperationResult;
use classes\system\Router;
use classes\system\Users;
use classes\system\SysClass;

/**
 * Модель системных действий
 */
class ModelSystems {

    private const DEFAULT_PHP_LOG_ORDER = 'date_time DESC, error_type ASC';
    private const DEFAULT_PROJECT_LOG_ORDER = 'date_time DESC, type_log ASC, initiator ASC';
    private const HEALTH_CRON_WARNING_MINUTES = 5;
    private const HEALTH_CRON_CRITICAL_MINUTES = 10;
    private const HEALTH_DISK_WARNING_BYTES = 5368709120; // 5 GiB
    private const HEALTH_DISK_CRITICAL_BYTES = 2147483648; // 2 GiB

    /**
     * Очищает все таблицы в базе данных
     * Этот метод получает список всех таблиц в текущей базе данных,
     * и выполняет операцию DROP на каждой таблице для её очистки
     * Операции выполняются в рамках одной транзакции, чтобы гарантировать,
     * что все таблицы будут успешно очищены, или ни одна из таблиц не будет удалена в случае ошибки
     * После очистки базы данных метод перезаписывает файл Constants.php содержимым файла Constants_clean.php
     * @param int $user_id Кто вызвал
     * @throws Exception Если произошла ошибка во время очистки таблиц
     * @return bool Возвращает true, если операция выполнена успешно, и false в случае ошибки
     */
    public function killDB(int $user_id): OperationResult {
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
                Logger::error('system_tools', 'Ошибка очистки базы данных', [
                    'user_id' => $user_id,
                    'message' => $e->getMessage(),
                ], [
                    'initiator' => __METHOD__,
                    'details' => $e->getMessage(),
                ]);
                return OperationResult::failure('Ошибка очистки базы данных: ' . $e->getMessage(), 'kill_db_failed');
            }
        } else {
            return OperationResult::failure('Таблицы в базе данных не найдены.', 'kill_db_empty');
        }
        // Пересоздание БД и регистрация первичных пользователей
        AuthService::resetInfrastructureState();
        new Users(true);
        Logger::audit('system_tools', 'База данных очищена и пересоздана', [
            'user_id' => $user_id,
            'tables_dropped' => count((array) $tables),
        ], [
            'initiator' => __METHOD__,
            'details' => 'DB recreated',
            'include_trace' => false,
        ]);
        return OperationResult::success(['tables_dropped' => count((array) $tables)], 'База данных пересоздана.', 'db_recreated');
    }

    public function getHealthReport(): array {
        $databaseConnected = SysClass::checkDatabaseConnection();
        $coreTables = [
            Constants::USERS_TABLE,
            Constants::USERS_ROLES_TABLE,
            Constants::CATEGORIES_TYPES_TABLE,
            Constants::CATEGORIES_TABLE,
            Constants::PAGES_TABLE,
            Constants::PROPERTY_TYPES_TABLE,
            Constants::PROPERTIES_TABLE,
            Constants::PROPERTY_SETS_TABLE,
            Constants::PROPERTY_VALUES_TABLE,
            Constants::FILES_TABLE,
            Constants::SEARCH_INDEX_TABLE,
            Constants::FILTERS_TABLE,
            Constants::IMPORT_MAP_TABLE,
        ];
        $authTables = [
            Constants::USERS_AUTH_SESSIONS_TABLE,
            Constants::USERS_AUTH_CREDENTIALS_TABLE,
            Constants::USERS_AUTH_IDENTITIES_TABLE,
            Constants::USERS_AUTH_CHALLENGES_TABLE,
        ];
        $cronTables = [
            Constants::CRON_AGENTS_TABLE,
            Constants::CRON_AGENT_RUNS_TABLE,
            Constants::IMPORT_MEDIA_QUEUE_TABLE,
        ];

        $tablesState = [];
        foreach (array_merge($coreTables, $authTables, $cronTables) as $tableName) {
            $tablesState[$tableName] = $this->tableExists($tableName);
        }

        $runtimeDirectories = SysClass::getRequiredRuntimeDirectories();
        $paths = [
            'cache' => $this->getPathHealth((string) ($runtimeDirectories['cache'] ?? '')),
            'logs' => $this->getPathHealth((string) ($runtimeDirectories['logs'] ?? '')),
            'tmp' => $this->getPathHealth((string) ($runtimeDirectories['uploads_tmp'] ?? '')),
            'uploads' => $this->getPathHealth((string) ($runtimeDirectories['uploads'] ?? '')),
            'config' => $this->getPathHealth((string) (ENV_SITE_PATH . 'inc' . ENV_DIRSEP . 'configuration.php')),
        ];

        $mediaDiagnostics = ($this->tableExists(Constants::FILES_TABLE)
            && $this->tableExists(Constants::PROPERTIES_TABLE)
            && $this->tableExists(Constants::PROPERTY_VALUES_TABLE))
            ? FileSystem::collectFileDiagnostics()
            : ['summary' => ['total_files' => 0, 'referenced_file_ids' => 0, 'unreferenced_files' => 0, 'missing_on_disk' => 0, 'dangling_references' => 0, 'legacy_payloads_without_file_ids' => 0]];
        $cronSummary = $this->getCronHealthSummary();
        $lifecycleSummary = $this->getLifecycleHealthSummary();
        $mediaQueueSummary = ($databaseConnected && $this->tableExists(Constants::IMPORT_MEDIA_QUEUE_TABLE))
            ? ImportMediaQueueService::getSummary()
            : $this->getEmptyMediaQueueSummary();
        $backupSummary = $this->getBackupSummary();
        $storageSummary = $this->getStorageHealthSummary();
        $mailSummary = $this->getMailHealthSummary();

        $report = [
            'generated_at' => date('c'),
            'install' => [
                'database_connected' => $databaseConnected,
                'core_tables_ok' => count(array_filter(array_intersect_key($tablesState, array_flip($coreTables)))) === count($coreTables),
                'auth_tables_ok' => count(array_filter(array_intersect_key($tablesState, array_flip($authTables)))) === count($authTables),
                'cron_tables_ok' => count(array_filter(array_intersect_key($tablesState, array_flip($cronTables)))) === count($cronTables),
                'tables' => $tablesState,
            ],
            'paths' => $paths,
            'storage' => $storageSummary,
            'cache' => [
                'backend' => CacheManager::resolveBackend(),
                'namespace' => defined('ENV_CACHE_NAMESPACE') ? (string) ENV_CACHE_NAMESPACE : 'ee-site',
                'version' => defined('ENV_CACHE_VERSION') ? (string) ENV_CACHE_VERSION : 'v1',
                'route_enabled' => defined('ENV_ROUTING_CACHE_ENABLED') ? (int) ENV_ROUTING_CACHE_ENABLED : 0,
                'route_backend' => defined('ENV_ROUTING_CACHE_BACKEND') ? (string) ENV_ROUTING_CACHE_BACKEND : 'file',
                'redis_probe_exists' => is_file(ENV_CACHE_PATH . 'redis_connection_check.cache'),
            ],
            'mail' => $mailSummary,
            'cron' => $cronSummary,
            'lifecycle' => $lifecycleSummary,
            'media' => $mediaDiagnostics['summary'] ?? [],
            'media_queue' => $mediaQueueSummary,
            'search' => [
                'search_index_rows' => $this->tableExists(Constants::SEARCH_INDEX_TABLE) ? (int) SafeMySQL::gi()->getOne('SELECT COUNT(*) FROM ?n', Constants::SEARCH_INDEX_TABLE) : 0,
                'search_ngram_rows' => $this->tableExists(Constants::SEARCH_NGRAMS_TABLE) ? (int) SafeMySQL::gi()->getOne('SELECT COUNT(*) FROM ?n', Constants::SEARCH_NGRAMS_TABLE) : 0,
                'filters_rows' => $this->tableExists(Constants::FILTERS_TABLE) ? (int) SafeMySQL::gi()->getOne('SELECT COUNT(*) FROM ?n', Constants::FILTERS_TABLE) : 0,
            ],
            'backups' => $backupSummary,
            'logs' => $this->get_logs_summary(),
        ];

        $report['alerts'] = $this->buildHealthAlerts($report);
        $report['alerts_summary'] = $this->summarizeHealthAlerts($report['alerts']);

        return $report;
    }

    public function refreshMediaMetadata(int $limit = 500): OperationResult {
        if (!$this->tableExists(Constants::FILES_TABLE)) {
            return OperationResult::failure('Таблица файлов не найдена.', 'files_table_missing');
        }

        $limit = max(1, min($limit, 5000));
        $fileIds = SafeMySQL::gi()->getCol(
            'SELECT file_id FROM ?n ORDER BY file_id ASC LIMIT ?i',
            Constants::FILES_TABLE,
            $limit
        );

        $updated = 0;
        $missing = 0;
        foreach ((array) $fileIds as $fileId) {
            $refreshed = FileSystem::refreshFileDataFromDisk((int) $fileId);
            if ($refreshed) {
                $updated++;
            } else {
                $missing++;
            }
        }

        Logger::info('system_tools', 'Обновлены метаданные файлов', [
            'limit' => $limit,
            'updated' => $updated,
            'missing' => $missing,
        ], [
            'initiator' => __METHOD__,
            'details' => 'Media metadata refresh completed',
            'include_trace' => false,
        ]);

        return OperationResult::success([
            'updated' => $updated,
            'missing' => $missing,
            'limit' => $limit,
        ], 'Метаданные файлов обновлены.', 'media_metadata_refreshed');
    }

    public function recoverStaleLifecycleJobs(int $staleMinutes = 30): OperationResult {
        if (!$this->tableExists(Constants::PROPERTY_LIFECYCLE_JOBS_TABLE)) {
            return OperationResult::failure('Таблица lifecycle jobs не найдена.', 'lifecycle_jobs_table_missing');
        }

        if (!class_exists('ModelPropertyLifecycle', false)) {
            require_once __DIR__ . ENV_DIRSEP . 'ModelPropertyLifecycle.php';
        }

        $result = (new ModelPropertyLifecycle())->recoverStaleRunningJobs($staleMinutes);
        return OperationResult::fromLegacy($result, [
            'false_message' => 'Не удалось восстановить зависшие lifecycle jobs.',
            'success_message' => !empty($result['recovered_count'])
                ? 'Зависшие lifecycle jobs возвращены в очередь.'
                : 'Зависшие lifecycle jobs не найдены.',
            'failure_code' => 'lifecycle_jobs_recovery_failed',
        ]);
    }

    public function recoverStaleOperationalQueues(int $lifecycleStaleMinutes = 30, int $mediaRetryDelaySec = 300, int $backupStaleAfterSec = 7200): OperationResult {
        $cronResult = CronAgentService::recoverStaleAgents();
        $lifecycleResult = $this->recoverStaleLifecycleJobs($lifecycleStaleMinutes);
        $mediaRecovered = $this->tableExists(Constants::IMPORT_MEDIA_QUEUE_TABLE)
            ? ImportMediaQueueService::recoverStaleQueueItems($mediaRetryDelaySec)
            : 0;
        $backupRecovered = $this->tableExists(Constants::BACKUP_JOBS_TABLE)
            ? BackupService::recoverStaleJobsNow($backupStaleAfterSec)
            : 0;

        if (!$cronResult->isSuccess()) {
            return $cronResult;
        }
        if (!$lifecycleResult->isSuccess()) {
            return $lifecycleResult;
        }

        $cronRecovered = (int) ($cronResult->getData()['recovered'] ?? 0);
        $lifecycleRecovered = (int) ($lifecycleResult->getData()['recovered_count'] ?? 0);
        $totalRecovered = $cronRecovered + $lifecycleRecovered + $mediaRecovered + $backupRecovered;

        return OperationResult::success([
            'cron_recovered' => $cronRecovered,
            'lifecycle_recovered' => $lifecycleRecovered,
            'media_recovered' => $mediaRecovered,
            'backup_recovered' => $backupRecovered,
            'total_recovered' => $totalRecovered,
        ], $totalRecovered > 0
            ? 'Зависшие процессы восстановлены.'
            : 'Зависших процессов не найдено.', $totalRecovered > 0 ? 'recovered' : 'noop');
    }

    public function createBackupSnapshot(): OperationResult {
        return BackupService::queueBackup([
            'plan_id' => (int) ((BackupService::getDefaultPlan()['backup_plan_id'] ?? 0)),
            'requested_via' => 'legacy_model_systems',
        ]);
    }

    public function getBackupSummary(): array {
        if (!$this->tableExists(Constants::BACKUP_JOBS_TABLE)
            || !$this->tableExists(Constants::BACKUP_PLANS_TABLE)
            || !$this->tableExists(Constants::BACKUP_TARGETS_TABLE)) {
            return $this->getEmptyBackupSummary();
        }

        return BackupService::getSummary();
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
        $start = ($start !== false) ? (int) $start : 0;
        $limit = ($limit !== false) ? (int) $limit : 100;
        $order = ($order !== false && trim((string) $order) !== '')
            ? (string) $order
            : self::DEFAULT_PHP_LOG_ORDER;

        $selectedLogs = [];
        $totalCount = 0;
        $windowSize = max(1, $start + $limit);
        $files = $this->getPhpLogFiles($type);

        foreach ($files as $filePath) {
            $this->streamPhpLogFile(
                $filePath,
                $type,
                function (array $log) use ($where, &$selectedLogs, &$totalCount, $windowSize, $order): void {
                    if (!$this->filterLog($log, $where)) {
                        return;
                    }
                    $totalCount++;
                    $this->pushRankedLogEntry($selectedLogs, $log, $windowSize, $order);
                }
            );
        }

        return [
            'data' => $this->sortAndPaginateLogs($selectedLogs, $order, $start, $limit),
            'total_count' => $totalCount
        ];
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
            'level' => '',
            'channel' => '',
            'initiator' => '',
            'request_id' => '',
            'user_id' => '',
            'method' => '',
            'uri' => '',
            'host' => '',
            'ip' => '',
            'result' => '',
            'details' => '',
            'context' => '',
            'meta' => '',
            'stack_trace' => ''
        ];
        foreach (explode("\n", $logBlock) as $line) {
            if (strpos($line, 'Время события: ') === 0) {
                $log['date_time'] = trim(substr($line, strlen('Время события: ')));
            } elseif (strpos($line, 'Уровень: ') === 0) {
                $log['level'] = trim(substr($line, strlen('Уровень: ')));
            } elseif (strpos($line, 'Канал: ') === 0) {
                $log['channel'] = trim(substr($line, strlen('Канал: ')));
            } elseif (strpos($line, 'Инициатор: ') === 0) {
                $log['initiator'] = trim(substr($line, strlen('Инициатор: ')));
            } elseif (strpos($line, 'Request ID: ') === 0) {
                $log['request_id'] = trim(substr($line, strlen('Request ID: ')));
            } elseif (strpos($line, 'User ID: ') === 0) {
                $log['user_id'] = trim(substr($line, strlen('User ID: ')));
            } elseif (strpos($line, 'Метод: ') === 0) {
                $log['method'] = trim(substr($line, strlen('Метод: ')));
            } elseif (strpos($line, 'URI: ') === 0) {
                $log['uri'] = trim(substr($line, strlen('URI: ')));
            } elseif (strpos($line, 'Host: ') === 0) {
                $log['host'] = trim(substr($line, strlen('Host: ')));
            } elseif (strpos($line, 'IP: ') === 0) {
                $log['ip'] = trim(substr($line, strlen('IP: ')));
            } elseif (strpos($line, 'Результат: ') === 0) {
                $log['result'] = trim(substr($line, strlen('Результат: ')), " '");
            } elseif (strpos($line, 'Детали: ') === 0) {
                $log['details'] = trim(substr($line, strlen('Детали: ')));
            } elseif (strpos($line, 'Контекст: ') === 0) {
                $json_data = trim(substr($line, strlen('Контекст: ')));
                $log['context'] = $this->formatStructuredLogValue($json_data);
            } elseif (strpos($line, 'Мета: ') === 0) {
                $json_data = trim(substr($line, strlen('Мета: ')));
                $log['meta'] = $this->formatStructuredLogValue($json_data);
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
     * Форматирует структурированное JSON-значение для admin viewer.
     */
    private function formatStructuredLogValue(string $value): string {
        if ($value === '') {
            return '';
        }
        if (!SysClass::ee_isValidJson($value)) {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        $html = '';
        foreach ($decoded as $key => $item) {
            $rendered = is_scalar($item) || $item === null
                ? (string) $item
                : json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $html .= '<b>' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . '</b>: '
                . htmlspecialchars((string) $rendered, ENT_QUOTES, 'UTF-8') . '<br/>';
        }
        return $html;
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
        $start = ($start !== false) ? (int) $start : 0;
        $limit = ($limit !== false) ? (int) $limit : 25;
        $order = ($order !== false && trim((string) $order) !== '')
            ? (string) $order
            : self::DEFAULT_PROJECT_LOG_ORDER;

        $selectedLogs = [];
        $totalCount = 0;
        $windowSize = max(1, $start + $limit);

        foreach ($this->getProjectLogFiles() as $logFile) {
            $this->streamProjectLogFile(
                $logFile['path'],
                (string) $logFile['type_log'],
                (string) $logFile['date_log'],
                function (array $log) use ($where, &$selectedLogs, &$totalCount, $windowSize, $order): void {
                    if (!$this->filterLog($log, $where)) {
                        return;
                    }
                    $totalCount++;
                    $this->pushRankedLogEntry($selectedLogs, $log, $windowSize, $order);
                }
            );
        }

        return [
            'data' => $this->sortAndPaginateLogs($selectedLogs, $order, $start, $limit),
            'total_count' => $totalCount
        ];
    }

    /**
     * Вернёт краткую сводку по текущему состоянию логов.
     * @return array<string, array<string, mixed>>
     */
    public function get_logs_summary(): array {
        $projectFiles = $this->getProjectLogFiles();
        $projectChannels = [];
        $projectFilesCount = 0;
        $projectArchives = 0;
        $projectSize = 0;
        $projectUpdatedAt = 0;

        foreach ($projectFiles as $projectFile) {
            $projectChannels[$projectFile['type_log']] = true;
            $projectFilesCount++;
            $projectArchives += !empty($projectFile['is_archive']) ? 1 : 0;
            $size = $this->safeFileSize((string) $projectFile['path']);
            $projectSize += $size;
            $projectUpdatedAt = max($projectUpdatedAt, (int) @filemtime((string) $projectFile['path']));
        }

        $phpStats = $this->getFlatLogSummary($this->getPhpLogFiles('php_logs'));
        $fatalStats = $this->getFlatLogSummary($this->getPhpLogFiles('fatal_errors'));

        return [
            'php_logs' => [
                'label' => 'php_logs',
                'files' => $phpStats['files'],
                'archives' => $phpStats['archives'],
                'size_human' => $this->formatBytes($phpStats['size_bytes']),
                'updated_at' => $phpStats['updated_at'] ? date('Y-m-d H:i:s', $phpStats['updated_at']) : '',
            ],
            'fatal_logs' => [
                'label' => 'fatal_logs',
                'files' => $fatalStats['files'],
                'archives' => $fatalStats['archives'],
                'size_human' => $this->formatBytes($fatalStats['size_bytes']),
                'updated_at' => $fatalStats['updated_at'] ? date('Y-m-d H:i:s', $fatalStats['updated_at']) : '',
            ],
            'project_logs' => [
                'label' => 'project_logs',
                'files' => $projectFilesCount,
                'archives' => $projectArchives,
                'channels' => count($projectChannels),
                'size_human' => $this->formatBytes($projectSize),
                'updated_at' => $projectUpdatedAt ? date('Y-m-d H:i:s', $projectUpdatedAt) : '',
            ],
        ];
    }

    /**
     * Очистит текущий и архивные PHP/Fatal log-файлы.
     */
    public function clearFlatLogFiles(string $type = 'php_logs'): bool {
        $cleared = false;
        foreach ($this->getPhpLogFiles($type) as $filePath) {
            if (!is_file($filePath)) {
                continue;
            }
            if (@unlink($filePath) || @file_put_contents($filePath, '') !== false) {
                $cleared = true;
            }
        }
        return $cleared;
    }

    /**
     * Очистит project logs внутри директорий логов.
     */
    public function clearProjectLogs(): bool {
        $logsDir = rtrim((string) ENV_LOGS_PATH, '/\\');
        if (!is_dir($logsDir)) {
            return false;
        }

        $removed = false;
        $items = scandir($logsDir);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $logsDir . ENV_DIRSEP . $item;
            if (!is_dir($path)) {
                continue;
            }
            $removed = $this->removeDirectoryContents($path) || $removed;
        }

        return $removed;
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
                $field = trim((string) $field, " `");
                $fieldValue = (string) ($log[$field] ?? '');
                if (strpos($fieldValue, $value) === false) {
                    return false;
                }
            } elseif (strpos($condition, '>=') !== false || strpos($condition, '<=') !== false) {
                // Условия сравнения даты и времени
                list($field, $value) = preg_split('/(>=|<=)/', $condition);
                $value = str_replace('\'', '', trim($value));
                $operator = (strpos($condition, '>=') !== false) ? '>=' : '<=';
                $field = trim((string) $field, " `");
                $fieldValue = (string) ($log[$field] ?? '');
                if (!$this->compareDateTime($fieldValue, $value, $operator)) {
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
            usort($logs, fn($a, $b) => $this->compareLogs($a, $b, $order));
        }
        return array_slice($logs, $start, $limit);
    }

    /**
     * Сравнит два лога по правилам сортировки.
     */
    private function compareLogs(array $a, array $b, string $order): int {
        foreach ($this->normalizeOrderParts($order) as $part) {
            $field = $part['field'];
            $direction = $part['direction'];
            $left = $a[$field] ?? null;
            $right = $b[$field] ?? null;
            $comparison = $this->compareLogValues($field, $left, $right);
            if ($comparison === 0) {
                continue;
            }
            return $direction === 'ASC' ? $comparison : -$comparison;
        }
        return 0;
    }

    /**
     * Поддерживает в памяти только лучшие записи для текущей страницы.
     */
    private function pushRankedLogEntry(array &$selectedLogs, array $log, int $windowSize, string $order): void {
        if ($windowSize <= 0) {
            return;
        }

        if (count($selectedLogs) < $windowSize) {
            $selectedLogs[] = $log;
            return;
        }

        $worstIndex = $this->getWorstLogIndex($selectedLogs, $order);
        if ($this->compareLogs($log, $selectedLogs[$worstIndex], $order) < 0) {
            $selectedLogs[$worstIndex] = $log;
        }
    }

    /**
     * Найдёт худшую запись в уже отобранном наборе.
     */
    private function getWorstLogIndex(array $selectedLogs, string $order): int {
        $worstIndex = 0;
        foreach ($selectedLogs as $index => $entry) {
            if ($this->compareLogs($selectedLogs[$worstIndex], $entry, $order) < 0) {
                $worstIndex = $index;
            }
        }
        return $worstIndex;
    }

    /**
     * Нормализует строку сортировки в список инструкций.
     * @return array<int, array{field:string, direction:string}>
     */
    private function normalizeOrderParts(string $order): array {
        $parts = [];
        foreach (explode(',', $order) as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk === '') {
                continue;
            }
            $segments = preg_split('/\s+/', $chunk);
            if (!$segments || empty($segments[0])) {
                continue;
            }
            $parts[] = [
                'field' => trim((string) $segments[0], " `"),
                'direction' => strtoupper((string) ($segments[1] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC',
            ];
        }
        return $parts;
    }

    /**
     * Сравнит два отдельных значения внутри записи лога.
     */
    private function compareLogValues(string $field, mixed $left, mixed $right): int {
        if ($field === 'date_time') {
            $leftTime = strtotime((string) $left) ?: 0;
            $rightTime = strtotime((string) $right) ?: 0;
            return $leftTime <=> $rightTime;
        }
        if (is_numeric($left) && is_numeric($right)) {
            return ((float) $left) <=> ((float) $right);
        }
        return strcasecmp((string) $left, (string) $right);
    }

    /**
     * Потоково читает project log-файл и отдаёт каждую запись в callback.
     */
    private function streamProjectLogFile(string $filePath, string $typeLog, string $dateLog, callable $consumer): void {
        if (!is_file($filePath)) {
            return;
        }
        $file = new SplFileObject($filePath);
        $buffer = [];
        $isCollecting = false;
        while (!$file->eof()) {
            $line = $file->fgets();
            if (strpos($line, '{START}') !== false) {
                $buffer = [];
                $isCollecting = true;
                $line = str_replace('{START}', '', $line);
            }
            if ($isCollecting) {
                if (strpos($line, '{END}') !== false) {
                    $line = str_replace('{END}', '', $line);
                    if (trim($line) !== '') {
                        $buffer[] = $line;
                    }
                    $log = $this->parseLogBlock(implode('', $buffer));
                    $log['type_log'] = $typeLog;
                    $log['date_log'] = $dateLog;
                    $consumer($log);
                    $buffer = [];
                    $isCollecting = false;
                    continue;
                }
                $buffer[] = $line;
            }
        }
    }

    /**
     * Потоково читает PHP/Fatal log-файл и отдаёт записи в callback.
     */
    private function streamPhpLogFile(string $filePath, string $type, callable $consumer): void {
        if (!is_file($filePath)) {
            return;
        }
        $file = new SplFileObject($filePath);
        $currentLog = null;
        $isCollectingStackTrace = false;

        while (!$file->eof()) {
            $line = $file->fgets();
            if ($type === 'fatal_errors') {
                if (strpos($line, 'Date: ') === 0) {
                    if (is_array($currentLog)) {
                        $consumer($currentLog);
                    }
                    $currentLog = [
                        'date_time' => trim(substr($line, 6)),
                        'error_type' => 'PHP Fatal error',
                        'message' => '',
                        'stack_trace' => [],
                    ];
                    $isCollectingStackTrace = false;
                } elseif (is_array($currentLog) && strpos($line, 'Message: ') === 0) {
                    $currentLog['message'] = trim(substr($line, 9));
                } elseif (is_array($currentLog) && strpos($line, 'Stack trace:') === 0) {
                    $isCollectingStackTrace = true;
                } elseif (is_array($currentLog) && $isCollectingStackTrace && trim($line) !== '') {
                    $currentLog['stack_trace'][] = trim($line);
                }
                continue;
            }

            if (preg_match('/\[(.*?)\] (.*?): (.*)/', $line, $matches)) {
                if (is_array($currentLog)) {
                    $consumer($currentLog);
                }
                $currentLog = [
                    'date_time' => $matches[1],
                    'error_type' => $matches[2],
                    'message' => $matches[3],
                    'stack_trace' => [],
                ];
            } elseif (is_array($currentLog) && strpos($line, '#') === 0) {
                $currentLog['stack_trace'][] = trim($line);
            }
        }

        if (is_array($currentLog)) {
            $consumer($currentLog);
        }
    }

    /**
     * Вернёт список project log-файлов, включая архивы.
     * @return array<int, array{path:string,type_log:string,date_log:string,is_archive:bool}>
     */
    private function getProjectLogFiles(): array {
        $logsDir = rtrim((string) ENV_LOGS_PATH, '/\\');
        if (!is_dir($logsDir)) {
            return [];
        }

        $result = [];
        $directories = array_filter(glob($logsDir . ENV_DIRSEP . '*') ?: [], 'is_dir');
        foreach ($directories as $dir) {
            $dirName = basename((string) $dir);
            $files = glob($dir . ENV_DIRSEP . '*.txt*') ?: [];
            foreach ($files as $filePath) {
                if (!is_file($filePath)) {
                    continue;
                }
                $result[] = [
                    'path' => (string) $filePath,
                    'type_log' => $dirName,
                    'date_log' => $this->extractProjectDateLog((string) basename((string) $filePath)),
                    'is_archive' => preg_match('/\.txt\.\d+$/', (string) $filePath) === 1,
                ];
            }
        }
        return $result;
    }

    /**
     * Вернёт список PHP/Fatal log-файлов, включая архивы.
     * @return string[]
     */
    private function getPhpLogFiles(string $type): array {
        $baseName = $type === 'fatal_errors' ? 'fatal_errors.txt' : 'php_errors.log';
        $files = glob(ENV_LOGS_PATH . $baseName . '*') ?: [];
        $files = array_values(array_filter($files, 'is_file'));
        usort($files, static function (string $left, string $right): int {
            return ((int) @filemtime($right)) <=> ((int) @filemtime($left));
        });
        return $files;
    }

    /**
     * Соберёт плоскую сводку по набору файлов логов.
     * @param string[] $files
     * @return array{files:int,archives:int,size_bytes:int,updated_at:int}
     */
    private function getFlatLogSummary(array $files): array {
        $summary = [
            'files' => 0,
            'archives' => 0,
            'size_bytes' => 0,
            'updated_at' => 0,
        ];

        foreach ($files as $filePath) {
            if (!is_file($filePath)) {
                continue;
            }
            $summary['files']++;
            $summary['archives'] += preg_match('/\.(log|txt)\.\d+$/', $filePath) === 1 ? 1 : 0;
            $summary['size_bytes'] += $this->safeFileSize($filePath);
            $summary['updated_at'] = max($summary['updated_at'], (int) @filemtime($filePath));
        }

        return $summary;
    }

    /**
     * Удалит содержимое директории без удаления самой директории.
     */
    private function removeDirectoryContents(string $directory): bool {
        if (!is_dir($directory)) {
            return false;
        }
        $removed = false;
        $items = scandir($directory);
        if ($items === false) {
            return false;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . ENV_DIRSEP . $item;
            if (is_dir($path)) {
                $removed = $this->removeDirectoryContents($path) || $removed;
                $removed = @rmdir($path) || $removed;
                continue;
            }
            $removed = @unlink($path) || $removed;
        }
        return $removed;
    }

    /**
     * Нормализует имя файла project log до даты.
     */
    private function extractProjectDateLog(string $fileName): string {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})\.txt(?:\.\d+)?$/', $fileName, $matches)) {
            return $matches[1];
        }
        return preg_replace('/\.txt(?:\.\d+)?$/', '', $fileName) ?: $fileName;
    }

    /**
     * Вернёт размер файла без warning.
     */
    private function safeFileSize(string $filePath): int {
        return is_file($filePath) ? (int) (@filesize($filePath) ?: 0) : 0;
    }

    /**
     * Форматирует байты в человекочитаемый вид.
     */
    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = max(0, $bytes);
        $unitIndex = 0;
        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }
        return round($value, $unitIndex === 0 ? 0 : 1) . ' ' . $units[$unitIndex];
    }

    private function getLifecycleHealthSummary(): array {
        if (!$this->tableExists(Constants::PROPERTY_LIFECYCLE_JOBS_TABLE)) {
            return [
                'total' => 0,
                'queued' => 0,
                'running' => 0,
                'completed' => 0,
                'failed' => 0,
                'cancelled' => 0,
                'stale_running' => 0,
                'oldest_queued_at' => '',
                'last_finished_at' => '',
            ];
        }

        if (!class_exists('ModelPropertyLifecycle', false)) {
            require_once __DIR__ . ENV_DIRSEP . 'ModelPropertyLifecycle.php';
        }

        $lifecycle = new ModelPropertyLifecycle();
        return $lifecycle->getLifecycleJobsSummary();
    }

    private function getCronHealthSummary(): array {
        if (!$this->tableExists(Constants::CRON_AGENTS_TABLE) || !$this->tableExists(Constants::CRON_AGENT_RUNS_TABLE)) {
            return [
                'total' => 0,
                'active' => 0,
                'due' => 0,
                'locked' => 0,
                'stale_locked' => 0,
                'failed' => 0,
                'last_run_at' => '',
                'scheduler_command' => 'php ' . ENV_SITE_PATH . 'app/cron/run.php',
                'config' => [
                    'max_agents_per_tick' => 0,
                    'max_weight_per_tick' => 0,
                    'max_concurrent' => 0,
                ],
            ];
        }

        $summary = CronAgentService::getSummary();
        $summary['stale_locked'] = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM ?n WHERE locked_until IS NOT NULL AND locked_until < NOW()',
            Constants::CRON_AGENTS_TABLE
        );
        $summary['last_run_at'] = (string) (SafeMySQL::gi()->getOne(
            'SELECT MAX(started_at) FROM ?n',
            Constants::CRON_AGENT_RUNS_TABLE
        ) ?? '');
        $summary['minutes_since_last_run'] = $this->minutesSince($summary['last_run_at']);

        return $summary;
    }

    private function getEmptyMediaQueueSummary(): array {
        return [
            'job_id' => null,
            'total' => 0,
            'queued' => 0,
            'running' => 0,
            'stale_running' => 0,
            'failed' => 0,
            'terminal_failed' => 0,
            'done' => 0,
            'last_completed_at' => '',
            'last_updated_at' => '',
            'agent_code' => 'media-mirror-worker',
            'pending' => 0,
            'agent' => null,
        ];
    }

    private function getEmptyBackupSummary(): array {
        return [
            'path' => $this->getBackupDirectory(),
            'retention_days' => 0,
            'max_local_snapshots' => 0,
            'snapshots_count' => 0,
            'latest_snapshot' => '',
            'latest_updated_at' => '',
            'items' => [],
            'targets_total' => 0,
            'targets_active' => 0,
            'plans_total' => 0,
            'plans_active' => 0,
            'default_plan' => null,
            'default_target' => null,
            'jobs' => [
                'queued' => 0,
                'running' => 0,
                'done' => 0,
                'partial' => 0,
                'failed' => 0,
            ],
            'stale_running' => 0,
            'last_completed_at' => '',
        ];
    }

    private function getStorageHealthSummary(): array {
        $path = rtrim((string) ENV_SITE_PATH, '/\\');
        $freeBytes = @disk_free_space($path);
        $totalBytes = @disk_total_space($path);
        $freeBytes = $freeBytes !== false ? (int) $freeBytes : 0;
        $totalBytes = $totalBytes !== false ? (int) $totalBytes : 0;
        $usedPercent = $totalBytes > 0 ? round((1 - ($freeBytes / $totalBytes)) * 100, 1) : 0.0;

        return [
            'path' => $path,
            'free_bytes' => $freeBytes,
            'total_bytes' => $totalBytes,
            'used_percent' => $usedPercent,
            'free_pretty' => $this->formatBytes($freeBytes),
            'total_pretty' => $this->formatBytes($totalBytes),
        ];
    }

    private function getMailHealthSummary(): array {
        $mode = !empty(ENV_SMTP) ? 'smtp' : 'mail';
        $sendmailPath = trim((string) ini_get('sendmail_path'));
        $sendmailBinary = '';
        if ($sendmailPath !== '') {
            $parts = preg_split('/\s+/', $sendmailPath);
            $sendmailBinary = trim((string) ($parts[0] ?? ''));
        }

        $smtpConfigured = !empty(ENV_SMTP)
            && trim((string) ENV_SMTP_SERVER) !== ''
            && (int) ENV_SMTP_PORT > 0
            && trim((string) ENV_SMTP_LOGIN) !== ''
            && trim((string) ENV_SMTP_PASSWORD) !== '';

        $transportAvailable = !empty(ENV_SMTP)
            ? $smtpConfigured
            : ($sendmailBinary !== '' && is_file($sendmailBinary) && is_executable($sendmailBinary));

        return [
            'mode' => $mode,
            'smtp_enabled' => !empty(ENV_SMTP),
            'smtp_configured' => $smtpConfigured,
            'transport_available' => $transportAvailable,
            'sendmail_path' => $sendmailPath,
            'sendmail_binary' => $sendmailBinary,
            'confirm_email_required' => (int) (defined('ENV_CONFIRM_EMAIL') ? ENV_CONFIRM_EMAIL : 0),
        ];
    }

    private function buildHealthAlerts(array $report): array {
        $alerts = [];
        $install = is_array($report['install'] ?? null) ? $report['install'] : [];
        $paths = is_array($report['paths'] ?? null) ? $report['paths'] : [];
        $storage = is_array($report['storage'] ?? null) ? $report['storage'] : [];
        $mail = is_array($report['mail'] ?? null) ? $report['mail'] : [];
        $cron = is_array($report['cron'] ?? null) ? $report['cron'] : [];
        $lifecycle = is_array($report['lifecycle'] ?? null) ? $report['lifecycle'] : [];
        $mediaQueue = is_array($report['media_queue'] ?? null) ? $report['media_queue'] : [];
        $backups = is_array($report['backups'] ?? null) ? $report['backups'] : [];

        if (empty($install['database_connected'])) {
            $alerts[] = $this->makeHealthAlert('critical', 'sys.health_alert_db_down_title', 'sys.health_alert_db_down_message');
        }
        if (empty($install['core_tables_ok'])) {
            $alerts[] = $this->makeHealthAlert('critical', 'sys.health_alert_core_tables_title', 'sys.health_alert_core_tables_message');
        }
        if (empty($install['auth_tables_ok'])) {
            $alerts[] = $this->makeHealthAlert('critical', 'sys.health_alert_auth_tables_title', 'sys.health_alert_auth_tables_message');
        }
        if (empty($install['cron_tables_ok'])) {
            $alerts[] = $this->makeHealthAlert('warning', 'sys.health_alert_cron_tables_title', 'sys.health_alert_cron_tables_message', [], '/admin/cron_agents', 'sys.cron_agents');
        }
        if (array_key_exists('transport_available', $mail) && empty($mail['transport_available'])) {
            $severity = !empty($mail['confirm_email_required']) ? 'critical' : 'warning';
            $alerts[] = $this->makeHealthAlert(
                $severity,
                'sys.health_alert_mail_transport_title',
                'sys.health_alert_mail_transport_message',
                [
                    'mode' => (string) ($mail['mode'] ?? 'mail'),
                    'transport' => (string) (($mail['sendmail_path'] ?? '') !== '' ? $mail['sendmail_path'] : ($mail['mode'] ?? 'mail')),
                ]
            );
        }

        foreach ($paths as $pathKey => $pathItem) {
            $pathLabel = (string) $pathKey;
            $pathValue = (string) ($pathItem['path'] ?? '');
            if (empty($pathItem['exists'])) {
                $alerts[] = $this->makeHealthAlert('critical', 'sys.health_alert_path_missing_title', 'sys.health_alert_path_missing_message', [
                    'path_key' => $pathLabel,
                    'path_value' => $pathValue,
                ]);
                continue;
            }
            if ($pathKey !== 'config' && empty($pathItem['writable'])) {
                $alerts[] = $this->makeHealthAlert('critical', 'sys.health_alert_path_unwritable_title', 'sys.health_alert_path_unwritable_message', [
                    'path_key' => $pathLabel,
                    'path_value' => $pathValue,
                ]);
            }
        }

        $freeBytes = (int) ($storage['free_bytes'] ?? 0);
        if ($freeBytes > 0 && $freeBytes <= self::HEALTH_DISK_CRITICAL_BYTES) {
            $alerts[] = $this->makeHealthAlert('critical', 'sys.health_alert_disk_critical_title', 'sys.health_alert_disk_critical_message', [
                'free' => (string) ($storage['free_pretty'] ?? '0 B'),
                'total' => (string) ($storage['total_pretty'] ?? '0 B'),
                'used_percent' => (string) ($storage['used_percent'] ?? '0'),
            ], '/admin/backup', 'sys.backup');
        } elseif ($freeBytes > 0 && $freeBytes <= self::HEALTH_DISK_WARNING_BYTES) {
            $alerts[] = $this->makeHealthAlert('warning', 'sys.health_alert_disk_warning_title', 'sys.health_alert_disk_warning_message', [
                'free' => (string) ($storage['free_pretty'] ?? '0 B'),
                'total' => (string) ($storage['total_pretty'] ?? '0 B'),
                'used_percent' => (string) ($storage['used_percent'] ?? '0'),
            ], '/admin/backup', 'sys.backup');
        }

        $minutesSinceLastRun = (int) ($cron['minutes_since_last_run'] ?? -1);
        if ($minutesSinceLastRun >= self::HEALTH_CRON_CRITICAL_MINUTES || (trim((string) ($cron['last_run_at'] ?? '')) === '')) {
            $alerts[] = $this->makeHealthAlert('critical', 'sys.health_alert_cron_stalled_title', 'sys.health_alert_cron_stalled_message', [
                'minutes' => $minutesSinceLastRun >= 0 ? (string) $minutesSinceLastRun : 'n/a',
            ], '/admin/cron_agents', 'sys.cron_agents');
        } elseif ($minutesSinceLastRun >= self::HEALTH_CRON_WARNING_MINUTES) {
            $alerts[] = $this->makeHealthAlert('warning', 'sys.health_alert_cron_delayed_title', 'sys.health_alert_cron_delayed_message', [
                'minutes' => (string) $minutesSinceLastRun,
            ], '/admin/cron_agents', 'sys.cron_agents');
        }

        if ((int) ($cron['failed'] ?? 0) > 0) {
            $alerts[] = $this->makeHealthAlert('warning', 'sys.health_alert_cron_failed_title', 'sys.health_alert_cron_failed_message', [
                'count' => (string) ((int) ($cron['failed'] ?? 0)),
            ], '/admin/cron_agents', 'sys.cron_agents');
        }
        if ((int) ($cron['stale_locked'] ?? 0) > 0) {
            $alerts[] = $this->makeHealthAlert('warning', 'sys.health_alert_cron_stale_title', 'sys.health_alert_cron_stale_message', [
                'count' => (string) ((int) ($cron['stale_locked'] ?? 0)),
            ], '/admin/recover_stale_operations', 'sys.recover_stale_operations');
        }
        if ((int) ($lifecycle['stale_running'] ?? 0) > 0) {
            $alerts[] = $this->makeHealthAlert('warning', 'sys.health_alert_lifecycle_stale_title', 'sys.health_alert_lifecycle_stale_message', [
                'count' => (string) ((int) ($lifecycle['stale_running'] ?? 0)),
            ], '/admin/recover_stale_operations', 'sys.recover_stale_operations');
        }
        if ((int) ($mediaQueue['stale_running'] ?? 0) > 0) {
            $alerts[] = $this->makeHealthAlert('warning', 'sys.health_alert_media_stale_title', 'sys.health_alert_media_stale_message', [
                'count' => (string) ((int) ($mediaQueue['stale_running'] ?? 0)),
            ], '/admin/recover_stale_operations', 'sys.recover_stale_operations');
        }
        if ((int) ($mediaQueue['failed'] ?? 0) > 0) {
            $alerts[] = $this->makeHealthAlert('warning', 'sys.health_alert_media_failed_title', 'sys.health_alert_media_failed_message', [
                'count' => (string) ((int) ($mediaQueue['failed'] ?? 0)),
            ], '/admin/cron_agents', 'sys.cron_agents');
        }
        if ((int) ($mediaQueue['terminal_failed'] ?? 0) > 0) {
            $alerts[] = $this->makeHealthAlert('warning', 'sys.health_alert_media_terminal_failed_title', 'sys.health_alert_media_terminal_failed_message', [
                'count' => (string) ((int) ($mediaQueue['terminal_failed'] ?? 0)),
            ], '/admin/cron_agents', 'sys.cron_agents');
        }
        if ((int) ($mediaQueue['pending'] ?? 0) > 0) {
            $alerts[] = $this->makeHealthAlert('info', 'sys.health_alert_media_pending_title', 'sys.health_alert_media_pending_message', [
                'count' => (string) ((int) ($mediaQueue['pending'] ?? 0)),
            ], '/admin/cron_agents', 'sys.cron_agents');
        }
        if ((int) ($backups['stale_running'] ?? 0) > 0) {
            $alerts[] = $this->makeHealthAlert('warning', 'sys.health_alert_backup_stale_title', 'sys.health_alert_backup_stale_message', [
                'count' => (string) ((int) ($backups['stale_running'] ?? 0)),
            ], '/admin/recover_stale_operations', 'sys.recover_stale_operations');
        }
        if ((int) (($backups['jobs']['failed'] ?? 0)) > 0) {
            $alerts[] = $this->makeHealthAlert('warning', 'sys.health_alert_backup_failed_title', 'sys.health_alert_backup_failed_message', [
                'count' => (string) ((int) (($backups['jobs']['failed'] ?? 0))),
            ], '/admin/backup', 'sys.backup');
        }

        return $alerts;
    }

    private function summarizeHealthAlerts(array $alerts): array {
        $summary = [
            'total' => 0,
            'critical' => 0,
            'warning' => 0,
            'info' => 0,
        ];

        foreach ($alerts as $alert) {
            $severity = (string) ($alert['severity'] ?? 'info');
            if (!array_key_exists($severity, $summary)) {
                continue;
            }
            $summary[$severity]++;
            $summary['total']++;
        }

        return $summary;
    }

    private function makeHealthAlert(string $severity, string $titleKey, string $messageKey, array $messageParams = [], ?string $actionUrl = null, ?string $actionLabelKey = null): array {
        return [
            'severity' => $severity,
            'title_key' => $titleKey,
            'message_key' => $messageKey,
            'message_params' => $messageParams,
            'action_url' => $actionUrl,
            'action_label_key' => $actionLabelKey,
        ];
    }

    private function minutesSince(string $dateTime): int {
        return ee_minutes_since_utc_datetime($dateTime);
    }

    private function tableExists(string $tableName): bool {
        if ($tableName === '' || !SysClass::checkDatabaseConnection()) {
            return false;
        }

        return SafeMySQL::gi()->query('SHOW TABLES LIKE ?s', $tableName)->num_rows > 0;
    }

    private function getPathHealth(string $path): array {
        $exists = file_exists($path);
        return [
            'path' => $path,
            'exists' => $exists,
            'writable' => $exists ? is_writable($path) : false,
            'is_dir' => $exists ? is_dir($path) : false,
        ];
    }

    private function getBackupDirectory(): string {
        return rtrim((string) ENV_TMP_PATH, '/\\') . ENV_DIRSEP . 'backups';
    }

    /**
     * @param array<int, array{path:string, alias:string}> $items
     */
    private function createZipFromPaths(string $archivePath, array $items): void {
        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Не удалось создать ZIP-архив резервной копии.');
        }

        foreach ($items as $item) {
            $path = (string) ($item['path'] ?? '');
            $alias = trim((string) ($item['alias'] ?? basename($path)), '/\\');
            if ($path === '' || $alias === '' || !file_exists($path)) {
                continue;
            }

            if (is_file($path)) {
                $zip->addFile($path, $alias);
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $fileInfo) {
                /** @var SplFileInfo $fileInfo */
                $fullPath = $fileInfo->getPathname();
                $localName = $alias . ENV_DIRSEP . ltrim(str_replace($path, '', $fullPath), '/\\');
                if ($fileInfo->isDir()) {
                    $zip->addEmptyDir(rtrim($localName, '/\\'));
                } else {
                    $zip->addFile($fullPath, $localName);
                }
            }
        }

        $zip->close();
    }

    private function removeDirectoryRecursively(string $path): void {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
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
}
