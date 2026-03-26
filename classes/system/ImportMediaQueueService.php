<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

/**
 * Очередь фонового зеркалирования внешних медиа после импорта.
 * Импорт складывает задания в БД, а системный media-mirror worker скачивает файлы порциями.
 */
class ImportMediaQueueService {

    private const DEFAULT_BATCH_LIMIT = 10;
    private const DEFAULT_LOCK_TTL_SEC = 480;
    private const DEFAULT_RETRY_DELAY_SEC = 900;
    private const MAX_BATCH_LIMIT = 100;
    private const DEFAULT_TIME_BUDGET_SEC = 40;
    private const WP_UPLOAD_REGEX = "~https?://[^\\s\"'<>]+/wp-content/uploads/[^\\s\"'<>]+~iu";
    private const WORKER_AGENT_CODE = 'media-mirror-worker';
    private const STATUS_TERMINAL_FAILED = 'terminal_failed';

    private static bool $infrastructureReady = false;

    public static function ensureInfrastructure(bool $force = false): void {
        if (self::$infrastructureReady && !$force) {
            return;
        }

        if (!SysClass::checkDatabaseConnection()) {
            self::$infrastructureReady = false;
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS ?n (
            queue_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_id INT UNSIGNED NULL,
            entity_type ENUM('page', 'category') NOT NULL,
            entity_id INT UNSIGNED NOT NULL,
            target_kind ENUM('property_value', 'entity_field') NOT NULL,
            target_field VARCHAR(64) NULL,
            target_key VARCHAR(255) NOT NULL,
            property_id INT UNSIGNED NULL,
            set_id INT UNSIGNED NULL,
            language_code CHAR(2) NOT NULL DEFAULT 'RU',
            field_type_hint VARCHAR(20) NOT NULL DEFAULT 'image',
            source_url VARCHAR(1000) NOT NULL,
            source_url_hash CHAR(40) NOT NULL,
            status ENUM('queued', 'running', 'done', 'failed', 'terminal_failed') NOT NULL DEFAULT 'queued',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            file_id INT UNSIGNED NULL,
            last_error MEDIUMTEXT NULL,
            next_retry_at DATETIME NULL,
            locked_at DATETIME NULL,
            locked_until DATETIME NULL,
            locked_by VARCHAR(128) NULL,
            completed_at DATETIME NULL,
            context_json LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_media_queue_target_url (target_key, source_url_hash),
            KEY idx_media_queue_status (status, next_retry_at, locked_until),
            KEY idx_media_queue_job (job_id),
            KEY idx_media_queue_source (source_url_hash),
            KEY idx_media_queue_entity (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Очередь фонового зеркалирования внешних медиа';";

        SafeMySQL::gi()->query($sql, Constants::IMPORT_MEDIA_QUEUE_TABLE);
        $statusColumnType = (string) (SafeMySQL::gi()->getOne(
            "SELECT COLUMN_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?s
               AND COLUMN_NAME = 'status'
             LIMIT 1",
            Constants::IMPORT_MEDIA_QUEUE_TABLE
        ) ?? '');

        if ($statusColumnType !== '' && stripos($statusColumnType, 'terminal_failed') === false) {
            SafeMySQL::gi()->query(
                "ALTER TABLE ?n MODIFY COLUMN status ENUM('queued', 'running', 'done', 'failed', 'terminal_failed') NOT NULL DEFAULT 'queued'",
                Constants::IMPORT_MEDIA_QUEUE_TABLE
            );
        }
        self::$infrastructureReady = true;
    }

    public static function resetInfrastructureState(): void {
        self::$infrastructureReady = false;
    }

    public static function getSummary(?int $jobId = null): array {
        self::ensureInfrastructure();

        $params = [Constants::IMPORT_MEDIA_QUEUE_TABLE];
        $where = '';
        if ($jobId !== null && $jobId > 0) {
            $where = 'WHERE job_id = ?i';
            $params[] = $jobId;
        }

        $row = SafeMySQL::gi()->getRow(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = 'queued'), 0) AS queued,
                COALESCE(SUM(status = 'running'), 0) AS running,
                COALESCE(SUM(status = 'running' AND locked_until IS NOT NULL AND locked_until < NOW()), 0) AS stale_running,
                COALESCE(SUM(status = 'failed'), 0) AS failed,
                COALESCE(SUM(status = 'terminal_failed'), 0) AS terminal_failed,
                COALESCE(SUM(status = 'done'), 0) AS done,
                MAX(completed_at) AS last_completed_at,
                MAX(updated_at) AS last_updated_at
             FROM ?n {$where}",
            ...$params
        ) ?: [];

        $summary = [
            'job_id' => $jobId,
            'total' => (int) ($row['total'] ?? 0),
            'queued' => (int) ($row['queued'] ?? 0),
            'running' => (int) ($row['running'] ?? 0),
            'stale_running' => (int) ($row['stale_running'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'terminal_failed' => (int) ($row['terminal_failed'] ?? 0),
            'done' => (int) ($row['done'] ?? 0),
            'last_completed_at' => (string) ($row['last_completed_at'] ?? ''),
            'last_updated_at' => (string) ($row['last_updated_at'] ?? ''),
            'agent_code' => self::WORKER_AGENT_CODE,
        ];
        $summary['pending'] = $summary['queued'] + $summary['running'] + $summary['failed'];
        $summary['agent'] = class_exists(CronAgentService::class)
            ? CronAgentService::getAgentByCode(self::WORKER_AGENT_CODE)
            : null;

        return $summary;
    }

    public static function recoverStaleQueueItems(int $retryDelaySec = self::DEFAULT_RETRY_DELAY_SEC, ?int $jobId = null): int {
        self::ensureInfrastructure();

        $retryDelaySec = max(60, $retryDelaySec);
        $params = [
            Constants::IMPORT_MEDIA_QUEUE_TABLE,
            'Recovered from stale media worker lock.',
            $retryDelaySec,
        ];
        $where = "
            WHERE status = 'running'
              AND locked_until IS NOT NULL
              AND locked_until < NOW()";

        if ($jobId !== null && $jobId > 0) {
            $where .= ' AND job_id = ?i';
            $params[] = $jobId;
        }

        SafeMySQL::gi()->query(
            "UPDATE ?n
             SET status = 'failed',
                 last_error = ?s,
                 next_retry_at = DATE_ADD(NOW(), INTERVAL ?i SECOND),
                 locked_at = NULL,
                 locked_until = NULL,
                 locked_by = NULL
             {$where}",
            ...$params
        );

        $recovered = max(0, (int) SafeMySQL::gi()->affectedRows());
        if ($recovered > 0) {
            Logger::warning('import_media_queue', 'Восстановлены зависшие элементы очереди медиа', [
                'recovered' => $recovered,
                'job_id' => $jobId,
                'retry_delay_sec' => $retryDelaySec,
            ], [
                'initiator' => __METHOD__,
                'details' => 'Stale media queue items recovered',
                'include_trace' => false,
            ]);
        }

        return $recovered;
    }

    public static function deleteJobQueue(int $jobId): void {
        self::ensureInfrastructure();
        if ($jobId <= 0) {
            return;
        }

        SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE job_id = ?i',
            Constants::IMPORT_MEDIA_QUEUE_TABLE,
            $jobId
        );
    }

    public static function queueImportJobMedia(int $jobId, string $languageCode = ENV_DEF_LANG): array {
        self::ensureInfrastructure();
        $jobId = max(0, $jobId);
        $languageCode = strtoupper(trim($languageCode));
        if ($jobId <= 0) {
            return [
                'job_id' => $jobId,
                'discovered' => 0,
                'queued' => 0,
                'requeued' => 0,
                'existing_done' => 0,
                'existing_pending' => 0,
                'existing_terminal_failed' => 0,
                'summary' => self::getSummary($jobId > 0 ? $jobId : null),
            ];
        }

        $stats = [
            'job_id' => $jobId,
            'discovered' => 0,
            'queued' => 0,
            'requeued' => 0,
            'existing_done' => 0,
            'existing_pending' => 0,
            'existing_terminal_failed' => 0,
        ];

        $pageIds = self::getImportedEntityIds($jobId, 'page');
        $categoryIds = self::getImportedEntityIds($jobId, 'category');

        self::queueEntityFieldMedia($jobId, 'page', $pageIds, $languageCode, $stats);
        self::queueEntityFieldMedia($jobId, 'category', $categoryIds, $languageCode, $stats);
        self::queuePropertyValueMedia($jobId, 'page', $pageIds, $stats);
        self::queuePropertyValueMedia($jobId, 'category', $categoryIds, $stats);

        $stats['summary'] = self::getSummary($jobId);

        Logger::info('import_media_queue', 'Импортное медиа поставлено в фоновую очередь', $stats, [
            'initiator' => __METHOD__,
            'details' => 'Import media queue populated',
            'include_trace' => false,
        ]);

        return $stats;
    }

    public static function processDueQueue(array $payload = []): array {
        self::ensureInfrastructure();

        $batchLimit = max(1, min((int) ($payload['batch_limit'] ?? self::DEFAULT_BATCH_LIMIT), self::MAX_BATCH_LIMIT));
        $retryDelaySec = max(60, (int) ($payload['retry_delay_sec'] ?? self::DEFAULT_RETRY_DELAY_SEC));
        $lockTtlSec = max(120, (int) ($payload['lock_ttl_sec'] ?? self::DEFAULT_LOCK_TTL_SEC));
        $onlyJobId = max(0, (int) ($payload['job_id'] ?? 0));
        $timeBudgetSec = max(10, (int) ($payload['time_budget_sec'] ?? (defined('ENV_MEDIA_MIRROR_TIME_BUDGET_SEC') ? ENV_MEDIA_MIRROR_TIME_BUDGET_SEC : self::DEFAULT_TIME_BUDGET_SEC)));
        $workerId = 'media-worker:' . getmypid();
        $workerStartedAt = microtime(true);
        $recoveredStale = self::recoverStaleQueueItems($retryDelaySec, $onlyJobId > 0 ? $onlyJobId : null);
        $cleanedObsolete = self::cleanupObsoleteQueueItems(max(200, $batchLimit * 20), $onlyJobId > 0 ? $onlyJobId : null);

        $candidates = self::getDueQueueItems($batchLimit * 3, $onlyJobId);
        $summary = [
            'recovered_stale' => $recoveredStale,
            'cleaned_obsolete' => $cleanedObsolete,
            'selected' => count($candidates),
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'terminal_failed' => 0,
            'cleaned_missing' => 0,
            'downloaded_files' => 0,
            'reused_files' => 0,
            'updated_targets' => 0,
            'skipped' => 0,
            'cleared_html_cache' => false,
            'time_budget_sec' => $timeBudgetSec,
            'memory_soft_limit_mb' => defined('ENV_MEDIA_MIRROR_MEMORY_SOFT_LIMIT_MB') ? (int) ENV_MEDIA_MIRROR_MEMORY_SOFT_LIMIT_MB : 0,
            'stopped_by_guard' => false,
            'stop_reason' => '',
            'runs' => [],
        ];

        $cacheShouldBeCleared = false;

        foreach ($candidates as $row) {
            if (self::shouldStopWorker($workerStartedAt, $timeBudgetSec, $stopReason)) {
                $summary['stopped_by_guard'] = true;
                $summary['stop_reason'] = $stopReason;
                break;
            }

            if ($summary['processed'] >= $batchLimit) {
                break;
            }

            $queueId = (int) ($row['queue_id'] ?? 0);
            if ($queueId <= 0 || !self::reserveQueueItem($queueId, $workerId, $lockTtlSec)) {
                $summary['skipped']++;
                continue;
            }

            $reservedRow = SafeMySQL::gi()->getRow(
                'SELECT * FROM ?n WHERE queue_id = ?i',
                Constants::IMPORT_MEDIA_QUEUE_TABLE,
                $queueId
            );
            if (!is_array($reservedRow)) {
                $summary['skipped']++;
                continue;
            }

            try {
                $fileId = self::resolveExistingFileId($reservedRow);
                $reused = $fileId > 0;
                if ($fileId <= 0) {
                    $importResult = FileSystem::importExternalFileDetailed(
                        (string) ($reservedRow['source_url'] ?? ''),
                        FileSystem::getUploadPolicyForFieldType((string) ($reservedRow['field_type_hint'] ?? 'image')),
                        basename((string) parse_url((string) ($reservedRow['source_url'] ?? ''), PHP_URL_PATH)),
                        null
                    );
                    $fileId = (int) ($importResult['file_id'] ?? 0);
                    if ($fileId <= 0) {
                        $isTerminalFailure = !empty($importResult['is_terminal']);
                        if ($isTerminalFailure) {
                            $cleanupResult = self::cleanupMissingSourceReference($reservedRow);
                            if (!empty($cleanupResult['resolved'])) {
                                self::markQueueItemResolvedWithoutFile(
                                    $queueId,
                                    (string) ($importResult['error_message'] ?? 'Внешний источник недоступен, ссылка удалена из данных.')
                                );
                                $summary['processed']++;
                                $summary['success']++;
                                $summary['cleaned_missing']++;
                                if (!empty($cleanupResult['updated'])) {
                                    $summary['updated_targets']++;
                                    $cacheShouldBeCleared = true;
                                }
                                $summary['runs'][] = [
                                    'queue_id' => $queueId,
                                    'status' => (string) ($cleanupResult['status'] ?? 'removed_missing_source'),
                                    'source_url' => (string) ($reservedRow['source_url'] ?? ''),
                                    'message' => (string) ($importResult['error_message'] ?? 'Внешний источник недоступен, ссылка удалена из данных.'),
                                    'http_code' => (int) ($importResult['http_code'] ?? 0),
                                ];
                                continue;
                            }
                        }

                        self::markQueueItemFailed(
                            $queueId,
                            (string) ($importResult['error_message'] ?? 'Не удалось скачать и сохранить внешний файл.'),
                            $retryDelaySec,
                            $isTerminalFailure
                        );
                        $summary['processed']++;
                        if ($isTerminalFailure) {
                            $summary['terminal_failed']++;
                        } else {
                            $summary['failed']++;
                        }
                        $summary['runs'][] = [
                            'queue_id' => $queueId,
                            'status' => $isTerminalFailure ? self::STATUS_TERMINAL_FAILED : 'failed',
                            'source_url' => (string) ($reservedRow['source_url'] ?? ''),
                            'message' => (string) ($importResult['error_message'] ?? 'Не удалось скачать и сохранить внешний файл.'),
                            'http_code' => (int) ($importResult['http_code'] ?? 0),
                        ];
                        continue;
                    }
                }

                $applyResult = self::applyQueueItem($reservedRow, $fileId);
                self::markQueueItemDone($queueId, $fileId);

                $summary['processed']++;
                $summary['success']++;
                if ($reused) {
                    $summary['reused_files']++;
                } else {
                    $summary['downloaded_files']++;
                }
                if (!empty($applyResult['updated'])) {
                    $summary['updated_targets']++;
                    $cacheShouldBeCleared = true;
                }
                $summary['runs'][] = [
                    'queue_id' => $queueId,
                    'status' => (string) ($applyResult['status'] ?? 'success'),
                    'source_url' => (string) ($reservedRow['source_url'] ?? ''),
                    'file_id' => $fileId,
                ];
            } catch (\Throwable $e) {
                self::markQueueItemFailed($queueId, $e->getMessage(), $retryDelaySec, false);
                $summary['processed']++;
                $summary['failed']++;
                $summary['runs'][] = [
                    'queue_id' => $queueId,
                    'status' => 'failed',
                    'source_url' => (string) ($reservedRow['source_url'] ?? ''),
                    'message' => $e->getMessage(),
                ];
            }

            if ($summary['processed'] > 0 && $summary['processed'] % 5 === 0 && function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        if ($cacheShouldBeCleared) {
            CacheManager::clearHtmlCache();
            $summary['cleared_html_cache'] = true;
        }

        $summary['memory_usage_mb'] = round(memory_get_usage(true) / 1048576, 2);
        $summary['peak_memory_mb'] = round(memory_get_peak_usage(true) / 1048576, 2);
        $summary['duration_ms'] = (int) round((microtime(true) - $workerStartedAt) * 1000);

        if ($summary['processed'] === 0 && $summary['cleaned_obsolete'] === 0) {
            return [
                'success' => true,
                'status' => 'noop',
                'message' => 'Очередь зеркалирования медиа пуста.',
                'data' => $summary,
            ];
        }

        Logger::info('import_media_queue', 'Media mirror worker обработал очередь', $summary, [
            'initiator' => __METHOD__,
            'details' => 'Media mirror worker batch completed',
            'include_trace' => false,
        ]);

        $hasErrors = ($summary['failed'] + $summary['terminal_failed']) > 0;

        return [
            'success' => !$hasErrors,
            'status' => $summary['stopped_by_guard']
                ? 'guard_stopped'
                : (!$hasErrors ? 'completed' : 'partial_failed'),
            'message' => $summary['stopped_by_guard']
                ? 'Очередь медиа остановлена защитным ограничителем и продолжится на следующем тике.'
                : (!$hasErrors
                    ? 'Очередь медиа обработана.'
                    : 'Часть элементов очереди медиа завершилась с ошибкой.'),
            'data' => $summary,
        ];
    }

    private static function cleanupObsoleteQueueItems(int $limit, ?int $jobId = null): int {
        $limit = max(1, min($limit, 5000));
        $params = [Constants::IMPORT_MEDIA_QUEUE_TABLE];
        $where = "status IN ('queued', 'failed', 'terminal_failed')
            AND target_kind = 'property_value'";

        if ($jobId !== null && $jobId > 0) {
            $where .= ' AND job_id = ?i';
            $params[] = $jobId;
        }

        $params[] = $limit;
        $rows = SafeMySQL::gi()->getAll(
            "SELECT queue_id, target_key, source_url
             FROM ?n
             WHERE {$where}
             ORDER BY queue_id ASC
             LIMIT ?i",
            ...$params
        );

        if ($rows === []) {
            return 0;
        }

        $valueIds = [];
        foreach ($rows as $row) {
            if (preg_match('~^property_value:(\d+)$~', (string) ($row['target_key'] ?? ''), $matches)) {
                $valueIds[(int) $matches[1]] = (int) $matches[1];
            }
        }

        $payloadMap = [];
        if ($valueIds !== []) {
            $valueRows = SafeMySQL::gi()->getAll(
                'SELECT value_id, property_values FROM ?n WHERE value_id IN (?a)',
                Constants::PROPERTY_VALUES_TABLE,
                array_values($valueIds)
            );
            foreach ($valueRows as $valueRow) {
                $payloadMap[(int) ($valueRow['value_id'] ?? 0)] = (string) ($valueRow['property_values'] ?? '');
            }
        }

        $queueIds = [];
        foreach ($rows as $row) {
            $valueId = 0;
            if (preg_match('~^property_value:(\d+)$~', (string) ($row['target_key'] ?? ''), $matches)) {
                $valueId = (int) ($matches[1] ?? 0);
            }
            $payload = $valueId > 0 ? (string) ($payloadMap[$valueId] ?? '') : '';
            $sourceUrl = (string) ($row['source_url'] ?? '');
            if ($payload === '' || $sourceUrl === '' || !self::propertyValuePayloadContainsSourceUrl($payload, $sourceUrl)) {
                $queueIds[] = (int) ($row['queue_id'] ?? 0);
            }
        }

        $queueIds = array_values(array_filter(array_map('intval', (array) $queueIds), static fn(int $id): bool => $id > 0));
        if ($queueIds === []) {
            return 0;
        }

        SafeMySQL::gi()->query(
            "UPDATE ?n
             SET status = 'done',
                 file_id = NULL,
                 last_error = ?s,
                 next_retry_at = NULL,
                 locked_at = NULL,
                 locked_until = NULL,
                 locked_by = NULL,
                 completed_at = NOW()
             WHERE queue_id IN (?a)",
            Constants::IMPORT_MEDIA_QUEUE_TABLE,
            'Resolved automatically: source URL is no longer present in target payload.',
            $queueIds
        );

        return count($queueIds);
    }

    private static function shouldStopWorker(float $workerStartedAt, int $timeBudgetSec, ?string &$reason = null): bool {
        if ((microtime(true) - $workerStartedAt) >= max(10, $timeBudgetSec)) {
            $reason = 'worker_time_budget_exceeded';
            return true;
        }

        if (function_exists('ee_runtime_memory_guard_exceeded') && ee_runtime_memory_guard_exceeded('media_worker', 12 * 1024 * 1024)) {
            $reason = 'worker_memory_guard_exceeded';
            return true;
        }

        return false;
    }

    private static function getImportedEntityIds(int $jobId, string $mapType): array {
        if ($jobId <= 0) {
            return [];
        }

        $rows = SafeMySQL::gi()->getCol(
            'SELECT DISTINCT local_id FROM ?n WHERE job_id = ?i AND map_type = ?s ORDER BY local_id ASC',
            ENV_DB_PREF . 'import_map',
            $jobId,
            $mapType
        );

        return array_values(array_filter(array_map('intval', (array) $rows), static fn(int $id): bool => $id > 0));
    }

    private static function queueEntityFieldMedia(int $jobId, string $entityType, array $entityIds, string $languageCode, array &$stats): void {
        if (empty($entityIds)) {
            return;
        }

        $table = $entityType === 'category' ? Constants::CATEGORIES_TABLE : Constants::PAGES_TABLE;
        $idField = $entityType === 'category' ? 'category_id' : 'page_id';

        foreach (array_chunk($entityIds, 200) as $chunk) {
            $rows = SafeMySQL::gi()->getAll(
                "SELECT {$idField} AS entity_id, short_description, description, language_code
                 FROM ?n
                 WHERE {$idField} IN (?a)",
                $table,
                $chunk
            );

            foreach ($rows as $row) {
                $entityId = (int) ($row['entity_id'] ?? 0);
                if ($entityId <= 0) {
                    continue;
                }
                $rowLanguageCode = strtoupper(trim((string) ($row['language_code'] ?? $languageCode)));
                foreach (['short_description', 'description'] as $fieldName) {
                    $content = (string) ($row[$fieldName] ?? '');
                    if ($content === '') {
                        continue;
                    }
                    foreach (self::extractWpUploadUrlsFromString($content) as $sourceUrl) {
                        $stats['discovered']++;
                        self::queueItem([
                            'job_id' => $jobId,
                            'entity_type' => $entityType,
                            'entity_id' => $entityId,
                            'target_kind' => 'entity_field',
                            'target_field' => $fieldName,
                            'target_key' => implode(':', ['entity_field', $entityType, $entityId, $fieldName, $rowLanguageCode]),
                            'property_id' => null,
                            'set_id' => null,
                            'language_code' => $rowLanguageCode !== '' ? $rowLanguageCode : $languageCode,
                            'field_type_hint' => 'image',
                            'source_url' => $sourceUrl,
                            'context_json' => json_encode([
                                'entity_field' => $fieldName,
                                'entity_type' => $entityType,
                                'entity_id' => $entityId,
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ], $stats);
                    }
                }
            }
        }
    }

    private static function queuePropertyValueMedia(int $jobId, string $entityType, array $entityIds, array &$stats): void {
        if (empty($entityIds)) {
            return;
        }

        foreach (array_chunk($entityIds, 200) as $chunk) {
            $rows = SafeMySQL::gi()->getAll(
                "SELECT value_id, entity_id, property_id, set_id, language_code, property_values
                 FROM ?n
                 WHERE entity_type = ?s
                   AND entity_id IN (?a)",
                Constants::PROPERTY_VALUES_TABLE,
                $entityType,
                $chunk
            );

            foreach ($rows as $row) {
                $valueId = (int) ($row['value_id'] ?? 0);
                if ($valueId <= 0) {
                    continue;
                }

                foreach (self::extractPropertyValueMediaReferences($row['property_values'] ?? '[]') as $mediaRef) {
                    $sourceUrl = (string) ($mediaRef['source_url'] ?? '');
                    if ($sourceUrl === '') {
                        continue;
                    }
                    $stats['discovered']++;
                    self::queueItem([
                        'job_id' => $jobId,
                        'entity_type' => $entityType,
                        'entity_id' => (int) ($row['entity_id'] ?? 0),
                        'target_kind' => 'property_value',
                        'target_field' => null,
                        'target_key' => 'property_value:' . $valueId,
                        'property_id' => (int) ($row['property_id'] ?? 0),
                        'set_id' => (int) ($row['set_id'] ?? 0),
                        'language_code' => strtoupper(trim((string) ($row['language_code'] ?? ENV_DEF_LANG))) ?: ENV_DEF_LANG,
                        'field_type_hint' => (string) ($mediaRef['field_type_hint'] ?? 'image'),
                        'source_url' => $sourceUrl,
                        'context_json' => json_encode([
                            'value_id' => $valueId,
                            'entity_type' => $entityType,
                            'entity_id' => (int) ($row['entity_id'] ?? 0),
                            'property_id' => (int) ($row['property_id'] ?? 0),
                            'set_id' => (int) ($row['set_id'] ?? 0),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ], $stats);
                }
            }
        }
    }

    private static function queueItem(array $data, array &$stats): void {
        $sourceUrl = trim((string) ($data['source_url'] ?? ''));
        if ($sourceUrl === '') {
            return;
        }

        $targetKey = trim((string) ($data['target_key'] ?? ''));
        if ($targetKey === '') {
            return;
        }

        $sourceHash = sha1($sourceUrl);
        $existing = SafeMySQL::gi()->getRow(
            'SELECT queue_id, status FROM ?n WHERE target_key = ?s AND source_url_hash = ?s LIMIT 1',
            Constants::IMPORT_MEDIA_QUEUE_TABLE,
            $targetKey,
            $sourceHash
        );

        if (is_array($existing) && !empty($existing['queue_id'])) {
            $status = (string) ($existing['status'] ?? '');
            if ($status === 'done') {
                $stats['existing_done']++;
                return;
            }
            if ($status === 'failed') {
                SafeMySQL::gi()->query(
                    "UPDATE ?n
                     SET status = 'queued',
                         last_error = NULL,
                         next_retry_at = NULL,
                         locked_at = NULL,
                         locked_until = NULL,
                         locked_by = NULL
                     WHERE queue_id = ?i",
                    Constants::IMPORT_MEDIA_QUEUE_TABLE,
                    (int) $existing['queue_id']
                );
                $stats['requeued']++;
                return;
            }
            if ($status === self::STATUS_TERMINAL_FAILED) {
                $stats['existing_terminal_failed']++;
                return;
            }
            $stats['existing_pending']++;
            return;
        }

        SafeMySQL::gi()->query(
            'INSERT INTO ?n SET ?u',
            Constants::IMPORT_MEDIA_QUEUE_TABLE,
            [
                'job_id' => (int) ($data['job_id'] ?? 0),
                'entity_type' => (string) ($data['entity_type'] ?? 'page'),
                'entity_id' => (int) ($data['entity_id'] ?? 0),
                'target_kind' => (string) ($data['target_kind'] ?? 'property_value'),
                'target_field' => $data['target_field'] ?? null,
                'target_key' => $targetKey,
                'property_id' => isset($data['property_id']) ? (int) $data['property_id'] : null,
                'set_id' => isset($data['set_id']) ? (int) $data['set_id'] : null,
                'language_code' => strtoupper(trim((string) ($data['language_code'] ?? ENV_DEF_LANG))) ?: ENV_DEF_LANG,
                'field_type_hint' => strtolower(trim((string) ($data['field_type_hint'] ?? 'image'))) ?: 'image',
                'source_url' => $sourceUrl,
                'source_url_hash' => $sourceHash,
                'status' => 'queued',
                'context_json' => (string) ($data['context_json'] ?? ''),
            ]
        );
        $stats['queued']++;
    }

    private static function extractWpUploadUrlsFromString(string $content): array {
        if ($content === '') {
            return [];
        }

        if (!preg_match_all(self::WP_UPLOAD_REGEX, $content, $matches)) {
            return [];
        }

        $urls = array_map('trim', (array) ($matches[0] ?? []));
        $urls = array_values(array_filter($urls, static fn(string $url): bool => $url !== ''));

        return array_values(array_unique($urls));
    }

    private static function extractPropertyValueMediaReferences(mixed $payload): array {
        $fields = PropertyFieldContract::decodeFieldList($payload);
        $result = [];
        foreach ($fields as $field) {
            $fieldType = strtolower(trim((string) ($field['type'] ?? '')));
            if (!in_array($fieldType, ['image', 'file'], true)) {
                continue;
            }
            $sourceValue = array_key_exists('value', $field)
                ? $field['value']
                : ($field['default'] ?? null);
            foreach (self::extractWpUploadUrlsFromMixed($sourceValue) as $sourceUrl) {
                $result[$fieldType . '|' . $sourceUrl] = [
                    'source_url' => $sourceUrl,
                    'field_type_hint' => $fieldType,
                ];
            }
        }

        return array_values($result);
    }

    private static function extractWpUploadUrlsFromMixed(mixed $value): array {
        $result = [];
        if (is_string($value)) {
            foreach (self::extractWpUploadUrlsFromString($value) as $url) {
                $result[$url] = $url;
            }
            return array_values($result);
        }

        if (!is_array($value)) {
            return [];
        }

        foreach ($value as $item) {
            foreach (self::extractWpUploadUrlsFromMixed($item) as $url) {
                $result[$url] = $url;
            }
        }

        return array_values($result);
    }

    private static function getDueQueueItems(int $limit, int $onlyJobId = 0): array {
        $limit = max(1, min($limit, self::MAX_BATCH_LIMIT * 4));

        if ($onlyJobId > 0) {
            return SafeMySQL::gi()->getAll(
                "SELECT * FROM ?n
                 WHERE job_id = ?i
                   AND status IN ('queued', 'failed')
                   AND (next_retry_at IS NULL OR next_retry_at <= NOW())
                   AND (locked_until IS NULL OR locked_until <= NOW())
                 ORDER BY queue_id ASC
                 LIMIT ?i",
                Constants::IMPORT_MEDIA_QUEUE_TABLE,
                $onlyJobId,
                $limit
            );
        }

        return SafeMySQL::gi()->getAll(
            "SELECT * FROM ?n
             WHERE status IN ('queued', 'failed')
               AND (next_retry_at IS NULL OR next_retry_at <= NOW())
               AND (locked_until IS NULL OR locked_until <= NOW())
             ORDER BY queue_id ASC
             LIMIT ?i",
            Constants::IMPORT_MEDIA_QUEUE_TABLE,
            $limit
        );
    }

    private static function reserveQueueItem(int $queueId, string $workerId, int $lockTtlSec): bool {
        SafeMySQL::gi()->query(
            "UPDATE ?n
             SET status = 'running',
                 attempts = attempts + 1,
                 locked_at = NOW(),
                 locked_until = DATE_ADD(NOW(), INTERVAL ?i SECOND),
                 locked_by = ?s
             WHERE queue_id = ?i
               AND status IN ('queued', 'failed')
               AND (next_retry_at IS NULL OR next_retry_at <= NOW())
               AND (locked_until IS NULL OR locked_until <= NOW())",
            Constants::IMPORT_MEDIA_QUEUE_TABLE,
            $lockTtlSec,
            $workerId,
            $queueId
        );

        return (int) SafeMySQL::gi()->affectedRows() > 0;
    }

    private static function resolveExistingFileId(array $row): int {
        $queueFileId = (int) ($row['file_id'] ?? 0);
        if ($queueFileId > 0 && FileSystem::getFileData($queueFileId, false)) {
            return $queueFileId;
        }

        $sourceHash = trim((string) ($row['source_url_hash'] ?? ''));
        if ($sourceHash === '') {
            return 0;
        }

        $fileId = (int) SafeMySQL::gi()->getOne(
            "SELECT file_id FROM ?n
             WHERE source_url_hash = ?s
               AND status = 'done'
               AND file_id IS NOT NULL
             ORDER BY queue_id ASC
             LIMIT 1",
            Constants::IMPORT_MEDIA_QUEUE_TABLE,
            $sourceHash
        );

        if ($fileId > 0 && FileSystem::getFileData($fileId, false)) {
            return $fileId;
        }

        return 0;
    }

    private static function applyQueueItem(array $row, int $fileId): array {
        $targetKind = (string) ($row['target_kind'] ?? '');
        return match ($targetKind) {
            'entity_field' => self::applyEntityFieldMedia($row, $fileId),
            default => self::applyPropertyValueMedia($row, $fileId),
        };
    }

    private static function applyPropertyValueMedia(array $row, int $fileId): array {
        $context = self::decodeJsonArray($row['context_json'] ?? '');
        $valueId = (int) ($context['value_id'] ?? 0);
        if ($valueId <= 0 && preg_match('~^property_value:(\d+)$~', (string) ($row['target_key'] ?? ''), $matches)) {
            $valueId = (int) ($matches[1] ?? 0);
        }

        if ($valueId <= 0) {
            return ['updated' => false, 'status' => 'noop'];
        }

        $valueRow = SafeMySQL::gi()->getRow(
            'SELECT property_values FROM ?n WHERE value_id = ?i LIMIT 1',
            Constants::PROPERTY_VALUES_TABLE,
            $valueId
        );
        if (!is_array($valueRow)) {
            return ['updated' => false, 'status' => 'target_missing'];
        }

        $decoded = json_decode((string) ($valueRow['property_values'] ?? '[]'), true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $changed = false;
        $replaced = self::replaceSourceUrlWithFileId($decoded, (string) ($row['source_url'] ?? ''), (string) $fileId, $changed);
        if (!$changed) {
            return ['updated' => false, 'status' => 'noop'];
        }

        SafeMySQL::gi()->query(
            'UPDATE ?n SET property_values = ?s WHERE value_id = ?i',
            Constants::PROPERTY_VALUES_TABLE,
            json_encode($replaced, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $valueId
        );

        return ['updated' => true, 'status' => 'updated'];
    }

    private static function applyEntityFieldMedia(array $row, int $fileId): array {
        $entityType = (string) ($row['entity_type'] ?? 'page');
        $fieldName = (string) ($row['target_field'] ?? '');
        if (!in_array($fieldName, ['description', 'short_description'], true)) {
            return ['updated' => false, 'status' => 'noop'];
        }

        $entityId = (int) ($row['entity_id'] ?? 0);
        if ($entityId <= 0) {
            return ['updated' => false, 'status' => 'noop'];
        }

        $table = $entityType === 'category' ? Constants::CATEGORIES_TABLE : Constants::PAGES_TABLE;
        $idField = $entityType === 'category' ? 'category_id' : 'page_id';
        $currentContent = SafeMySQL::gi()->getOne(
            "SELECT {$fieldName} FROM ?n WHERE {$idField} = ?i LIMIT 1",
            $table,
            $entityId
        );
        if (!is_string($currentContent) || $currentContent === '') {
            return ['updated' => false, 'status' => 'target_missing'];
        }

        $fileData = FileSystem::getFileData($fileId, false);
        $localUrl = trim((string) ($fileData['file_url'] ?? ''));
        if ($localUrl === '') {
            return ['updated' => false, 'status' => 'file_missing'];
        }

        $updatedContent = str_replace((string) ($row['source_url'] ?? ''), $localUrl, $currentContent, $replaceCount);
        if ($replaceCount <= 0) {
            return ['updated' => false, 'status' => 'noop'];
        }

        SafeMySQL::gi()->query(
            "UPDATE ?n SET {$fieldName} = ?s WHERE {$idField} = ?i",
            $table,
            $updatedContent,
            $entityId
        );

        return ['updated' => true, 'status' => 'updated'];
    }

    private static function replaceSourceUrlWithFileId(mixed $value, string $sourceUrl, string $replacement, bool &$changed): mixed {
        if (is_string($value)) {
            if (trim($value) === $sourceUrl) {
                $changed = true;
                return $replacement;
            }
            return $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::replaceSourceUrlWithFileId($item, $sourceUrl, $replacement, $changed);
        }

        return $value;
    }

    private static function markQueueItemDone(int $queueId, int $fileId): void {
        SafeMySQL::gi()->query(
            "UPDATE ?n
             SET status = 'done',
                 file_id = ?i,
                 last_error = NULL,
                 next_retry_at = NULL,
                 locked_at = NULL,
                 locked_until = NULL,
                 locked_by = NULL,
                 completed_at = NOW()
             WHERE queue_id = ?i",
            Constants::IMPORT_MEDIA_QUEUE_TABLE,
            $fileId,
            $queueId
        );
    }

    private static function markQueueItemResolvedWithoutFile(int $queueId, string $note = ''): void {
        SafeMySQL::gi()->query(
            "UPDATE ?n
             SET status = 'done',
                 file_id = NULL,
                 last_error = ?s,
                 next_retry_at = NULL,
                 locked_at = NULL,
                 locked_until = NULL,
                 locked_by = NULL,
                 completed_at = NOW()
             WHERE queue_id = ?i",
            Constants::IMPORT_MEDIA_QUEUE_TABLE,
            trim($note),
            $queueId
        );
    }

    private static function markQueueItemFailed(int $queueId, string $errorMessage, int $retryDelaySec, bool $isTerminal = false): void {
        if ($isTerminal) {
            SafeMySQL::gi()->query(
                "UPDATE ?n
                 SET status = ?s,
                     last_error = ?s,
                     next_retry_at = NULL,
                     locked_at = NULL,
                     locked_until = NULL,
                     locked_by = NULL,
                     completed_at = NOW()
                 WHERE queue_id = ?i",
                Constants::IMPORT_MEDIA_QUEUE_TABLE,
                self::STATUS_TERMINAL_FAILED,
                trim($errorMessage),
                $queueId
            );
            return;
        }

        SafeMySQL::gi()->query(
            "UPDATE ?n
             SET status = 'failed',
                 last_error = ?s,
                 next_retry_at = DATE_ADD(NOW(), INTERVAL ?i SECOND),
                 locked_at = NULL,
                 locked_until = NULL,
                 locked_by = NULL
             WHERE queue_id = ?i",
            Constants::IMPORT_MEDIA_QUEUE_TABLE,
            trim($errorMessage),
            $retryDelaySec,
            $queueId
        );
    }

    private static function decodeJsonArray(mixed $payload): array {
        if (is_array($payload)) {
            return $payload;
        }
        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function cleanupMissingSourceReference(array $row): array {
        $targetKind = (string) ($row['target_kind'] ?? '');
        return match ($targetKind) {
            'entity_field' => self::cleanupMissingEntityFieldMedia($row),
            default => self::cleanupMissingPropertyValueMedia($row),
        };
    }

    private static function cleanupMissingEntityFieldMedia(array $row): array {
        $entityType = (string) ($row['entity_type'] ?? 'page');
        $entityId = (int) ($row['entity_id'] ?? 0);
        $fieldName = trim((string) ($row['target_field'] ?? ''));
        $sourceUrl = trim((string) ($row['source_url'] ?? ''));
        if ($entityId <= 0 || $fieldName === '' || $sourceUrl === '') {
            return ['resolved' => false, 'updated' => false, 'status' => 'noop'];
        }

        $table = $entityType === 'category' ? Constants::CATEGORIES_TABLE : Constants::PAGES_TABLE;
        $idField = $entityType === 'category' ? 'category_id' : 'page_id';
        $content = (string) (SafeMySQL::gi()->getOne(
            "SELECT {$fieldName} FROM ?n WHERE {$idField} = ?i",
            $table,
            $entityId
        ) ?? '');

        if ($content === '' || !str_contains($content, $sourceUrl)) {
            return ['resolved' => true, 'updated' => false, 'status' => 'already_clean'];
        }

        $updatedContent = str_replace($sourceUrl, '', $content, $replaceCount);
        if ($replaceCount <= 0) {
            return ['resolved' => false, 'updated' => false, 'status' => 'noop'];
        }

        SafeMySQL::gi()->query(
            "UPDATE ?n SET {$fieldName} = ?s WHERE {$idField} = ?i",
            $table,
            $updatedContent,
            $entityId
        );

        return ['resolved' => true, 'updated' => true, 'status' => 'removed_missing_source'];
    }

    private static function cleanupMissingPropertyValueMedia(array $row): array {
        $valueId = 0;
        if (preg_match('~^property_value:(\d+)$~', (string) ($row['target_key'] ?? ''), $matches)) {
            $valueId = (int) ($matches[1] ?? 0);
        }
        $sourceUrl = trim((string) ($row['source_url'] ?? ''));
        if ($valueId <= 0 || $sourceUrl === '') {
            return ['resolved' => false, 'updated' => false, 'status' => 'noop'];
        }

        $payload = (string) (SafeMySQL::gi()->getOne(
            'SELECT property_values FROM ?n WHERE value_id = ?i',
            Constants::PROPERTY_VALUES_TABLE,
            $valueId
        ) ?? '');

        if ($payload === '' || !self::propertyValuePayloadContainsSourceUrl($payload, $sourceUrl)) {
            return ['resolved' => true, 'updated' => false, 'status' => 'already_clean'];
        }

        $fields = PropertyFieldContract::decodeFieldList($payload);
        if ($fields === []) {
            return ['resolved' => false, 'updated' => false, 'status' => 'noop'];
        }

        $changed = false;
        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }
            if (array_key_exists('value', $field)) {
                $fields[$index]['value'] = self::removeSourceUrlFromMixed($field['value'], $sourceUrl, $changed);
                continue;
            }
            if (array_key_exists('default', $field)) {
                $fields[$index]['default'] = self::removeSourceUrlFromMixed($field['default'], $sourceUrl, $changed);
            }
        }

        if (!$changed) {
            return ['resolved' => false, 'updated' => false, 'status' => 'noop'];
        }

        SafeMySQL::gi()->query(
            'UPDATE ?n SET property_values = ?s WHERE value_id = ?i',
            Constants::PROPERTY_VALUES_TABLE,
            json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $valueId
        );

        return ['resolved' => true, 'updated' => true, 'status' => 'removed_missing_source'];
    }

    private static function removeSourceUrlFromMixed(mixed $value, string $sourceUrl, bool &$changed): mixed {
        if (is_string($value)) {
            if (trim($value) === $sourceUrl) {
                $changed = true;
                return null;
            }
            return $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        $isList = array_keys($value) === range(0, count($value) - 1);
        $result = [];
        foreach ($value as $key => $item) {
            $normalized = self::removeSourceUrlFromMixed($item, $sourceUrl, $changed);
            if ($normalized === null) {
                continue;
            }
            if ($isList) {
                $result[] = $normalized;
            } else {
                $result[$key] = $normalized;
            }
        }

        return $result;
    }

    private static function propertyValuePayloadContainsSourceUrl(string $payload, string $sourceUrl): bool {
        if ($payload === '' || $sourceUrl === '') {
            return false;
        }

        $fields = PropertyFieldContract::decodeFieldList($payload);
        if ($fields === []) {
            return str_contains($payload, $sourceUrl);
        }

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            if (array_key_exists('value', $field) && self::mixedContainsSourceUrl($field['value'], $sourceUrl)) {
                return true;
            }
            if (array_key_exists('default', $field) && self::mixedContainsSourceUrl($field['default'], $sourceUrl)) {
                return true;
            }
        }

        return false;
    }

    private static function mixedContainsSourceUrl(mixed $value, string $sourceUrl): bool {
        if (is_string($value)) {
            return trim($value) === $sourceUrl;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (self::mixedContainsSourceUrl($item, $sourceUrl)) {
                return true;
            }
        }

        return false;
    }
}
