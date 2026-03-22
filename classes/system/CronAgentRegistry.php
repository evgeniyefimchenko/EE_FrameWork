<?php

namespace classes\system;

use classes\helpers\ClassSearchEngine;
use classes\plugins\SafeMySQL;

/**
 * Реестр встроенных обработчиков агентного крона.
 * В БД хранится только расписание и payload, код обработчиков остаётся в проекте.
 */
class CronAgentRegistry {

    /**
     * Вернёт список встроенных обработчиков и их метаданные.
     */
    public static function getHandlers(): array {
        return [
            'property_lifecycle.next' => [
                'title_key' => 'sys.cron_handler_property_lifecycle',
                'description_key' => 'sys.cron_handler_property_lifecycle_desc',
                'payload_example' => [],
                'required_payload_keys' => [],
                'default_agent' => [
                    'code' => 'property-lifecycle-next',
                    'auto_create' => 1,
                    'required_system' => 1,
                    'schedule_mode' => 'interval',
                    'interval_minutes' => 1,
                    'priority' => 10,
                    'weight' => 2,
                    'max_runtime_sec' => 120,
                    'lock_ttl_sec' => 180,
                    'retry_delay_sec' => 60,
                    'is_active' => 1,
                    'title_ru' => 'Обработка очереди lifecycle',
                    'title_en' => 'Process lifecycle queue',
                    'description_ru' => 'Запускает следующую задачу жизненного цикла свойств из очереди.',
                    'description_en' => 'Runs the next queued property lifecycle task.',
                ],
            ],
            'search.popularity.update' => [
                'title_key' => 'sys.cron_handler_search_popularity',
                'description_key' => 'sys.cron_handler_search_popularity_desc',
                'payload_example' => [
                    'min_hits' => 3,
                    'query_limit' => 100,
                    'result_limit' => 5,
                    'decay_factor' => 0.95,
                ],
                'required_payload_keys' => [],
                'default_agent' => [
                    'code' => 'search-popularity-update',
                    'auto_create' => 0,
                    'required_system' => 0,
                    'schedule_mode' => 'interval',
                    'interval_minutes' => 60,
                    'priority' => 80,
                    'weight' => 4,
                    'max_runtime_sec' => 300,
                    'lock_ttl_sec' => 420,
                    'retry_delay_sec' => 300,
                    'is_active' => 0,
                    'title_ru' => 'Пересчёт популярности поиска',
                    'title_en' => 'Update search popularity',
                    'description_ru' => 'Повышает рейтинг популярных поисковых запросов и применяет затухание.',
                    'description_en' => 'Recalculates search popularity boosts and applies decay.',
                ],
            ],
            'import.profile' => [
                'title_key' => 'sys.cron_handler_import_profile',
                'description_key' => 'sys.cron_handler_import_profile_desc',
                'payload_example' => [
                    'job_id' => 15,
                ],
                'required_payload_keys' => ['job_id'],
                'default_agent' => null,
            ],
            'ops.health_check' => [
                'title_key' => 'sys.cron_handler_health_check',
                'description_key' => 'sys.cron_handler_health_check_desc',
                'payload_example' => [],
                'required_payload_keys' => [],
                'default_agent' => [
                    'code' => 'system-health-check',
                    'auto_create' => 0,
                    'required_system' => 0,
                    'schedule_mode' => 'interval',
                    'interval_minutes' => 30,
                    'priority' => 90,
                    'weight' => 1,
                    'max_runtime_sec' => 60,
                    'lock_ttl_sec' => 120,
                    'retry_delay_sec' => 300,
                    'is_active' => 0,
                    'title_ru' => 'Проверка состояния системы',
                    'title_en' => 'System health check',
                    'description_ru' => 'Собирает сводный отчёт по состоянию системы.',
                    'description_en' => 'Collects a consolidated system health report.',
                ],
            ],
        ];
    }

    public static function getHandlerMeta(string $handler): ?array {
        $handlers = self::getHandlers();
        return $handlers[$handler] ?? null;
    }

    /**
     * Встроенные агенты, которые можно создать по умолчанию.
     */
    public static function getDefaultAgents(): array {
        $defaults = [];
        foreach (self::getHandlers() as $handlerCode => $meta) {
            if (!is_array($meta['default_agent'] ?? null)) {
                continue;
            }
            $defaults[$handlerCode] = $meta['default_agent'];
        }
        return $defaults;
    }

    public static function getAutoCreatedDefaultAgents(): array {
        $defaults = [];
        foreach (self::getDefaultAgents() as $handlerCode => $definition) {
            if (empty($definition['auto_create'])) {
                continue;
            }
            $defaults[$handlerCode] = $definition;
        }
        return $defaults;
    }

    /**
     * Выполняет встроенный handler и возвращает стандартизированный результат.
     */
    public static function runHandler(string $handler, array $payload = [], array $context = []): array {
        return match ($handler) {
            'property_lifecycle.next' => self::runPropertyLifecycleNext($payload, $context),
            'search.popularity.update' => self::runSearchPopularityUpdate($payload, $context),
            'import.profile' => self::runImportProfile($payload, $context),
            'ops.health_check' => self::runHealthCheck($payload, $context),
            default => [
                'success' => false,
                'status' => 'unknown_handler',
                'message' => 'Unknown cron agent handler: ' . $handler,
                'data' => [],
            ],
        };
    }

    private static function runPropertyLifecycleNext(array $payload = [], array $context = []): array {
        require_once ENV_SITE_PATH . 'app/admin/models/ModelPropertyLifecycle.php';
        $lifecycle = new \ModelPropertyLifecycle();
        $result = $lifecycle->runNextQueuedLifecycleJob();
        $status = (string) ($result['status'] ?? 'completed');

        if ($status === 'empty') {
            return [
                'success' => true,
                'status' => 'noop',
                'message' => 'Lifecycle queue is empty.',
                'data' => $result,
            ];
        }

        return [
            'success' => !empty($result['success']),
            'status' => $status,
            'message' => (string) ($result['message'] ?? 'Lifecycle agent finished.'),
            'data' => $result,
        ];
    }

    private static function runSearchPopularityUpdate(array $payload = [], array $context = []): array {
        $db = SafeMySQL::gi();
        $minHits = max(1, (int) ($payload['min_hits'] ?? 3));
        $queryLimit = max(1, min(1000, (int) ($payload['query_limit'] ?? 100)));
        $resultLimit = max(1, min(100, (int) ($payload['result_limit'] ?? 5)));
        $decayFactor = (float) ($payload['decay_factor'] ?? 0.95);
        if ($decayFactor <= 0 || $decayFactor > 1) {
            $decayFactor = 0.95;
        }

        $popularQueries = $db->getAll(
            "SELECT normalized_query, hit_count FROM ?n WHERE hit_count >= ?i ORDER BY hit_count DESC LIMIT ?i",
            Constants::SEARCH_LOG_TABLE,
            $minHits,
            $queryLimit
        );

        $totalUpdated = 0;
        $processedQueries = 0;

        foreach ($popularQueries as $popQuery) {
            $normalizedQuery = trim((string) ($popQuery['normalized_query'] ?? ''));
            if ($normalizedQuery === '') {
                continue;
            }

            $hits = (int) ($popQuery['hit_count'] ?? 0);
            $scoreFactor = (int) ceil(log($hits + 1));
            if ($scoreFactor < 1) {
                $scoreFactor = 1;
            }

            $booleanQuery = ClassSearchEngine::prepareSearchQueryBoolean($normalizedQuery);
            if ($booleanQuery === '') {
                continue;
            }

            $topResults = $db->getCol(
                "SELECT search_id FROM ?n
                 WHERE MATCH(title, content_full) AGAINST (?s IN BOOLEAN MODE)
                 ORDER BY MATCH(title, content_full) AGAINST (?s IN BOOLEAN MODE) DESC
                 LIMIT ?i",
                Constants::SEARCH_INDEX_TABLE,
                $booleanQuery,
                $booleanQuery,
                $resultLimit
            );

            if (empty($topResults)) {
                continue;
            }

            $db->query(
                "UPDATE ?n SET popularity_score = popularity_score + ?i WHERE search_id IN (?a)",
                Constants::SEARCH_INDEX_TABLE,
                $scoreFactor,
                $topResults
            );
            $totalUpdated += (int) $db->affectedRows();
            $processedQueries++;
        }

        $decaySql = $db->parse(
            "UPDATE ?n SET popularity_score = FLOOR(popularity_score * " . (float) $decayFactor . ")
             WHERE popularity_score > 0",
            Constants::SEARCH_INDEX_TABLE
        );
        $db->query($decaySql);
        $decayedRows = (int) $db->affectedRows();

        return [
            'success' => true,
            'status' => 'completed',
            'message' => 'Search popularity updated.',
            'data' => [
                'processed_queries' => $processedQueries,
                'updated_results' => $totalUpdated,
                'decayed_rows' => $decayedRows,
                'min_hits' => $minHits,
                'query_limit' => $queryLimit,
                'result_limit' => $resultLimit,
                'decay_factor' => $decayFactor,
            ],
        ];
    }

    private static function runImportProfile(array $payload = [], array $context = []): array {
        $jobId = (int) ($payload['job_id'] ?? 0);
        if ($jobId <= 0) {
            return [
                'success' => false,
                'status' => 'validation_error',
                'message' => 'The import.profile handler requires payload.job_id.',
                'data' => [],
            ];
        }

        if (!defined('EE_CRON_RUN')) {
            define('EE_CRON_RUN', true);
        }

        require_once ENV_SITE_PATH . 'app/admin/index.php';
        $view = new View();
        $adminController = new \ControllerAdmin($view);

        $output = '';
        try {
            ob_start();
            $adminController->run_wp_import([$jobId]);
            $output = trim((string) ob_get_clean());
        } catch (\Throwable $e) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            return [
                'success' => false,
                'status' => 'failed',
                'message' => $e->getMessage(),
                'data' => [
                    'job_id' => $jobId,
                    'exception' => $e->getMessage(),
                ],
            ];
        }

        $isFailure = stripos($output, 'CRITICAL ERROR') !== false
            || stripos($output, 'Fatal import error') !== false
            || stripos($output, 'Access denied') !== false;

        return [
            'success' => !$isFailure,
            'status' => $isFailure ? 'failed' : 'completed',
            'message' => $isFailure ? 'Import profile execution failed.' : 'Import profile executed.',
            'data' => [
                'job_id' => $jobId,
            ],
            'output' => $output,
        ];
    }

    private static function runHealthCheck(array $payload = [], array $context = []): array {
        require_once ENV_SITE_PATH . 'app/admin/models/ModelSystems.php';
        $systemsModel = new \ModelSystems();
        $report = $systemsModel->getHealthReport();

        return [
            'success' => true,
            'status' => 'completed',
            'message' => 'System health report generated.',
            'data' => $report,
        ];
    }
}
