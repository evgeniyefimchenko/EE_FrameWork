<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\Hook;
use classes\system\Logger;
use classes\system\PropertyFieldContract;
use classes\system\SysClass;

class ModelPropertyLifecycle {

    private bool $infrastructureReady = false;

    public function __construct(array $params = []) {
        $this->ensureInfrastructure();
    }

    public function dispatchPropertyTypeRebuild(int $typeId, array $beforeTypeData = [], array $afterTypeData = [], array $options = []): array {
        $impact = $this->getPropertyTypeImpact($typeId);
        return $this->dispatchLifecycleOperation(
            'property_type',
            $typeId,
            [
                'before' => $beforeTypeData,
                'after' => $afterTypeData,
                'bump_schema_version' => $this->propertyTypeSchemaChanged($beforeTypeData, $afterTypeData) ? 1 : 0,
            ],
            $impact,
            $options,
            function () use ($typeId, $beforeTypeData, $afterTypeData): array {
                $result = $this->rebuildPropertyType($typeId, $beforeTypeData, $afterTypeData);
                if ($this->propertyTypeSchemaChanged($beforeTypeData, $afterTypeData)) {
                    $result['schema_version'] = $this->bumpSchemaVersion(Constants::PROPERTY_TYPES_TABLE, 'type_id', $typeId);
                }
                return $result;
            }
        );
    }

    public function dispatchPropertyRebuild(int $propertyId, array $beforePropertyData = [], array $afterPropertyData = [], array $options = []): array {
        $impact = $this->getPropertyImpact($propertyId);
        return $this->dispatchLifecycleOperation(
            'property',
            $propertyId,
            [
                'before' => $beforePropertyData,
                'after' => $afterPropertyData,
                'bump_schema_version' => $this->propertySchemaChanged($beforePropertyData, $afterPropertyData) ? 1 : 0,
            ],
            $impact,
            $options,
            function () use ($propertyId, $beforePropertyData, $afterPropertyData): array {
                $result = $this->rebuildProperty($propertyId, $beforePropertyData, $afterPropertyData);
                if ($this->propertySchemaChanged($beforePropertyData, $afterPropertyData)) {
                    $result['schema_version'] = $this->bumpSchemaVersion(Constants::PROPERTIES_TABLE, 'property_id', $propertyId);
                }
                return $result;
            }
        );
    }

    public function dispatchPropertySetSync(int $setId, array $addedPropertyIds, array $deletedPropertyIds, array $options = []): array {
        $impact = $this->getPropertySetImpact($setId);
        return $this->dispatchLifecycleOperation(
            'property_set',
            $setId,
            [
                'added_property_ids' => array_values(array_map('intval', $addedPropertyIds)),
                'deleted_property_ids' => array_values(array_map('intval', $deletedPropertyIds)),
            ],
            $impact,
            $options,
            fn(): array => $this->syncPropertySetChange($setId, $addedPropertyIds, $deletedPropertyIds)
        );
    }

    public function dispatchCategoryTypeSync(int $typeId, array $setIds, array $allTypeIds, array $options = []): array {
        $impact = $this->getCategoryTypeImpact($typeId);
        return $this->dispatchLifecycleOperation(
            'category_type',
            $typeId,
            [
                'set_ids' => array_values(array_map('intval', $setIds)),
                'all_type_ids' => array_values(array_map('intval', $allTypeIds)),
            ],
            $impact,
            $options,
            fn(): array => $this->syncCategoryTypeChange($typeId, $setIds, $allTypeIds)
        );
    }

    public function getLifecycleJobsData(string $order = 'job_id DESC', ?string $where = null, int $start = 0, int $limit = 50): array {
        $this->ensureInfrastructure();
        $orderString = trim($order) !== '' ? $order : 'job_id DESC';
        $whereString = $where ? 'WHERE ' . $where : '';
        $rows = SafeMySQL::gi()->getAll(
            'SELECT * FROM ?n ' . $whereString . ' ORDER BY ' . $orderString . ' LIMIT ?i, ?i',
            Constants::PROPERTY_LIFECYCLE_JOBS_TABLE,
            $start,
            $limit
        );
        $total = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM ?n ' . $whereString,
            Constants::PROPERTY_LIFECYCLE_JOBS_TABLE
        );
        return [
            'data' => $rows,
            'total_count' => $total,
        ];
    }

    public function getLifecycleJob(int $jobId): ?array {
        $this->ensureInfrastructure();
        if ($jobId <= 0) {
            return null;
        }
        $job = SafeMySQL::gi()->getRow(
            'SELECT * FROM ?n WHERE job_id = ?i',
            Constants::PROPERTY_LIFECYCLE_JOBS_TABLE,
            $jobId
        );
        return $job ?: null;
    }

    public function getLifecycleJobsSummary(int $staleMinutes = 30): array {
        $this->ensureInfrastructure();
        $summary = [
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

        $rows = SafeMySQL::gi()->getAll(
            'SELECT status, COUNT(*) AS total_count FROM ?n GROUP BY status',
            Constants::PROPERTY_LIFECYCLE_JOBS_TABLE
        );
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $count = (int) ($row['total_count'] ?? 0);
            if ($status !== '' && array_key_exists($status, $summary)) {
                $summary[$status] = $count;
                $summary['total'] += $count;
            }
        }

        $staleThreshold = date('Y-m-d H:i:s', time() - max(1, $staleMinutes) * 60);
        $summary['stale_running'] = (int) SafeMySQL::gi()->getOne(
            "SELECT COUNT(*) FROM ?n WHERE status = 'running' AND started_at IS NOT NULL AND started_at < ?s AND finished_at IS NULL",
            Constants::PROPERTY_LIFECYCLE_JOBS_TABLE,
            $staleThreshold
        );
        $summary['oldest_queued_at'] = (string) (SafeMySQL::gi()->getOne(
            "SELECT created_at FROM ?n WHERE status = 'queued' ORDER BY priority ASC, job_id ASC LIMIT 1",
            Constants::PROPERTY_LIFECYCLE_JOBS_TABLE
        ) ?: '');
        $summary['last_finished_at'] = (string) (SafeMySQL::gi()->getOne(
            "SELECT finished_at FROM ?n WHERE status IN ('completed', 'failed', 'cancelled') AND finished_at IS NOT NULL ORDER BY finished_at DESC LIMIT 1",
            Constants::PROPERTY_LIFECYCLE_JOBS_TABLE
        ) ?: '');

        return $summary;
    }

    public function recoverStaleRunningJobs(int $staleMinutes = 30): array {
        $this->ensureInfrastructure();
        $staleMinutes = max(1, $staleMinutes);
        $staleThreshold = date('Y-m-d H:i:s', time() - $staleMinutes * 60);
        $jobIds = SafeMySQL::gi()->getCol(
            "SELECT job_id FROM ?n WHERE status = 'running' AND started_at IS NOT NULL AND started_at < ?s AND finished_at IS NULL ORDER BY started_at ASC",
            Constants::PROPERTY_LIFECYCLE_JOBS_TABLE,
            $staleThreshold
        );
        $jobIds = array_values(array_filter(array_map('intval', (array) $jobIds), static fn(int $jobId): bool => $jobId > 0));

        if ($jobIds === []) {
            return [
                'success' => true,
                'status' => 'noop',
                'recovered_count' => 0,
                'job_ids' => [],
            ];
        }

        $message = 'Recovered stale running job on ' . date('Y-m-d H:i:s');
        SafeMySQL::gi()->query(
            "UPDATE ?n
             SET status = 'queued',
                 started_at = NULL,
                 finished_at = NULL,
                 processed_steps = 0,
                 progress_percent = 0,
                 error_message = CASE
                    WHEN error_message IS NULL OR error_message = '' THEN ?s
                    ELSE CONCAT(error_message, '\n', ?s)
                 END
             WHERE job_id IN (?a)",
            Constants::PROPERTY_LIFECYCLE_JOBS_TABLE,
            $message,
            $message,
            $jobIds
        );

        Logger::warning('property_lifecycle', 'Выполнено восстановление зависших lifecycle jobs', [
            'job_ids' => $jobIds,
            'stale_minutes' => $staleMinutes,
        ], [
            'initiator' => __METHOD__,
            'details' => 'Recovered stale lifecycle jobs',
            'include_trace' => false,
        ]);

        return [
            'success' => true,
            'status' => 'requeued',
            'recovered_count' => count($jobIds),
            'job_ids' => $jobIds,
        ];
    }

    public function runNextQueuedLifecycleJob(): array {
        $this->ensureInfrastructure();
        $jobId = (int) SafeMySQL::gi()->getOne(
            "SELECT job_id FROM ?n WHERE status = 'queued' ORDER BY priority ASC, job_id ASC LIMIT 1",
            Constants::PROPERTY_LIFECYCLE_JOBS_TABLE
        );
        if ($jobId <= 0) {
            return [
                'success' => false,
                'status' => 'empty',
                'message' => 'No queued lifecycle jobs found.',
            ];
        }
        return $this->runLifecycleJob($jobId);
    }

    public function runLifecycleJob(int $jobId): array {
        $this->ensureInfrastructure();
        $job = $this->getLifecycleJob($jobId);
        if (!$job) {
            return [
                'success' => false,
                'status' => 'not_found',
                'errors' => ['job_not_found'],
            ];
        }

        $scope = (string) ($job['scope'] ?? '');
        $targetId = (int) ($job['target_id'] ?? 0);
        $payload = json_decode((string) ($job['payload_json'] ?? '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        return $this->withLifecycleLock($scope, $targetId, function () use ($jobId, $job, $scope, $targetId, $payload): array {
            SafeMySQL::gi()->query(
                "UPDATE ?n SET status = 'running', started_at = NOW(), error_message = NULL WHERE job_id = ?i",
                Constants::PROPERTY_LIFECYCLE_JOBS_TABLE,
                $jobId
            );

            try {
                $result = $this->runInTransaction(function () use ($scope, $targetId, $payload): array {
                    $result = match ($scope) {
                        'property_type' => $this->rebuildPropertyType(
                            $targetId,
                            is_array($payload['before'] ?? null) ? $payload['before'] : [],
                            is_array($payload['after'] ?? null) ? $payload['after'] : []
                        ),
                        'property' => $this->rebuildProperty(
                            $targetId,
                            is_array($payload['before'] ?? null) ? $payload['before'] : [],
                            is_array($payload['after'] ?? null) ? $payload['after'] : []
                        ),
                        'property_set' => $this->syncPropertySetChange(
                            $targetId,
                            is_array($payload['added_property_ids'] ?? null) ? array_map('intval', $payload['added_property_ids']) : [],
                            is_array($payload['deleted_property_ids'] ?? null) ? array_map('intval', $payload['deleted_property_ids']) : []
                        ),
                        'category_type' => $this->syncCategoryTypeChange(
                            $targetId,
                            is_array($payload['set_ids'] ?? null) ? array_map('intval', $payload['set_ids']) : [],
                            is_array($payload['all_type_ids'] ?? null) ? array_map('intval', $payload['all_type_ids']) : []
                        ),
                        default => ['scope' => $scope, 'errors' => ['unknown_scope']],
                    };

                    if (!empty($payload['bump_schema_version'])) {
                        if ($scope === 'property_type') {
                            $result['schema_version'] = $this->bumpSchemaVersion(Constants::PROPERTY_TYPES_TABLE, 'type_id', $targetId);
                        } elseif ($scope === 'property') {
                            $result['schema_version'] = $this->bumpSchemaVersion(Constants::PROPERTIES_TABLE, 'property_id', $targetId);
                        }
                    }

                    return $result;
                });

                SafeMySQL::gi()->query(
                    "UPDATE ?n SET status = 'completed', processed_steps = total_steps, progress_percent = 100.00, finished_at = NOW(), result_json = ?s WHERE job_id = ?i",
                    Constants::PROPERTY_LIFECYCLE_JOBS_TABLE,
                    json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $jobId
                );

                return array_merge($result, [
                    'job_id' => $jobId,
                    'status' => 'completed',
                    'success' => empty($result['errors']),
                ]);
            } catch (\Throwable $e) {
                SafeMySQL::gi()->query(
                    "UPDATE ?n SET status = 'failed', finished_at = NOW(), error_message = ?s WHERE job_id = ?i",
                    Constants::PROPERTY_LIFECYCLE_JOBS_TABLE,
                    $e->getMessage(),
                    $jobId
                );
                return [
                    'scope' => $scope,
                    'job_id' => $jobId,
                    'status' => 'failed',
                    'success' => false,
                    'errors' => [$e->getMessage()],
                ];
            }
        });
    }

    public function getPropertyTypeImpact(int $typeId): array {
        $impact = [
            'type_id' => $typeId,
            'properties_count' => 0,
            'sets_count' => 0,
            'values_count' => 0,
            'categories_count' => 0,
            'pages_count' => 0,
        ];

        if ($typeId <= 0) {
            return Hook::filter('collectPropertyTypeLifecycleImpact', $impact, $typeId);
        }

        $propertyIds = $this->getPropertyIdsByType($typeId);
        if (!empty($propertyIds)) {
            $impact['properties_count'] = count($propertyIds);
            $impact['sets_count'] = (int) SafeMySQL::gi()->getOne(
                'SELECT COUNT(DISTINCT set_id) FROM ?n WHERE property_id IN (?a)',
                Constants::PROPERTY_SET_TO_PROPERTIES_TABLE,
                $propertyIds
            );
            $impact['values_count'] = (int) SafeMySQL::gi()->getOne(
                'SELECT COUNT(*) FROM ?n WHERE property_id IN (?a)',
                Constants::PROPERTY_VALUES_TABLE,
                $propertyIds
            );
            $impact['categories_count'] = (int) SafeMySQL::gi()->getOne(
                "SELECT COUNT(DISTINCT entity_id) FROM ?n WHERE property_id IN (?a) AND entity_type = 'category'",
                Constants::PROPERTY_VALUES_TABLE,
                $propertyIds
            );
            $impact['pages_count'] = (int) SafeMySQL::gi()->getOne(
                "SELECT COUNT(DISTINCT entity_id) FROM ?n WHERE property_id IN (?a) AND entity_type = 'page'",
                Constants::PROPERTY_VALUES_TABLE,
                $propertyIds
            );
        }

        return Hook::filter('collectPropertyTypeLifecycleImpact', $impact, $typeId);
    }

    public function getPropertyImpact(int $propertyId): array {
        $impact = [
            'property_id' => $propertyId,
            'sets_count' => 0,
            'values_count' => 0,
            'categories_count' => 0,
            'pages_count' => 0,
        ];

        if ($propertyId <= 0) {
            return Hook::filter('collectPropertyLifecycleImpact', $impact, $propertyId);
        }

        $impact['sets_count'] = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(DISTINCT set_id) FROM ?n WHERE property_id = ?i',
            Constants::PROPERTY_SET_TO_PROPERTIES_TABLE,
            $propertyId
        );
        $impact['values_count'] = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM ?n WHERE property_id = ?i',
            Constants::PROPERTY_VALUES_TABLE,
            $propertyId
        );
        $impact['categories_count'] = (int) SafeMySQL::gi()->getOne(
            "SELECT COUNT(DISTINCT entity_id) FROM ?n WHERE property_id = ?i AND entity_type = 'category'",
            Constants::PROPERTY_VALUES_TABLE,
            $propertyId
        );
        $impact['pages_count'] = (int) SafeMySQL::gi()->getOne(
            "SELECT COUNT(DISTINCT entity_id) FROM ?n WHERE property_id = ?i AND entity_type = 'page'",
            Constants::PROPERTY_VALUES_TABLE,
            $propertyId
        );

        return Hook::filter('collectPropertyLifecycleImpact', $impact, $propertyId);
    }

    public function getPropertySetImpact(int $setId): array {
        $impact = [
            'set_id' => $setId,
            'properties_count' => 0,
            'category_types_count' => 0,
            'categories_count' => 0,
            'pages_count' => 0,
        ];

        if ($setId <= 0) {
            return Hook::filter('collectPropertySetLifecycleImpact', $impact, $setId);
        }

        $impact['properties_count'] = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM ?n WHERE set_id = ?i',
            Constants::PROPERTY_SET_TO_PROPERTIES_TABLE,
            $setId
        );

        $categoryTypeIds = SafeMySQL::gi()->getCol(
            'SELECT DISTINCT type_id FROM ?n WHERE set_id = ?i',
            Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE,
            $setId
        );
        $impact['category_types_count'] = count($categoryTypeIds);

        if (!empty($categoryTypeIds)) {
            $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
            $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
            if ($objectModelCategoriesTypes && $objectModelCategories) {
                $categoryIds = $objectModelCategoriesTypes->getAllCategoriesByType($categoryTypeIds);
                $impact['categories_count'] = count($categoryIds);
                if (!empty($categoryIds)) {
                    $impact['pages_count'] = count($objectModelCategories->getCategoryPages($categoryIds));
                }
            }
        }

        return Hook::filter('collectPropertySetLifecycleImpact', $impact, $setId);
    }

    public function getCategoryTypeImpact(int $typeId): array {
        $impact = [
            'type_id' => $typeId,
            'descendants_count' => 0,
            'sets_count' => 0,
            'categories_count' => 0,
            'pages_count' => 0,
        ];

        if ($typeId <= 0) {
            return Hook::filter('collectCategoryTypeLifecycleImpact', $impact, $typeId);
        }

        $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
        $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
        if (!$objectModelCategoriesTypes || !$objectModelCategories) {
            return Hook::filter('collectCategoryTypeLifecycleImpact', $impact, $typeId);
        }

        $allTypeIds = $objectModelCategoriesTypes->getAllTypeChildrensIds($typeId);
        $allTypeIds[] = $typeId;
        $allTypeIds = array_values(array_unique(array_map('intval', $allTypeIds)));
        $impact['descendants_count'] = max(count($allTypeIds) - 1, 0);
        $impact['sets_count'] = count($objectModelCategoriesTypes->getCategoriesTypeSetsData($typeId));

        $categoryIds = $objectModelCategoriesTypes->getAllCategoriesByType($allTypeIds);
        $impact['categories_count'] = count($categoryIds);
        if (!empty($categoryIds)) {
            $impact['pages_count'] = count($objectModelCategories->getCategoryPages($categoryIds));
        }

        return Hook::filter('collectCategoryTypeLifecycleImpact', $impact, $typeId);
    }

    private function dispatchLifecycleOperation(
        string $scope,
        int $targetId,
        array $payload,
        array $impact,
        array $options,
        callable $runner
    ): array {
        $this->ensureInfrastructure();

        $intercepted = Hook::until(
            'beforePropertyLifecycleDispatch',
            null,
            $scope,
            $targetId,
            $impact,
            $options,
            $payload
        );
        if (is_array($intercepted)) {
            return $intercepted;
        }

        $strategy = $this->buildDispatchStrategy($scope, $targetId, $impact, $options, $payload);
        $estimatedSteps = $this->estimateLifecycleSteps($scope, $impact);

        if (!empty($options['dry_run'])) {
            return [
                'scope' => $scope,
                'target_id' => $targetId,
                'status' => 'preview',
                'dry_run' => true,
                'impact' => $impact,
                'strategy' => $strategy,
                'estimated_steps' => $estimatedSteps,
            ];
        }

        if (($strategy['mode'] ?? 'sync') === 'queue') {
            $jobId = $this->createLifecycleJob($scope, $targetId, $payload, $impact, $strategy, $options, $estimatedSteps);
            return [
                'scope' => $scope,
                'target_id' => $targetId,
                'status' => 'queued',
                'queued' => true,
                'success' => true,
                'job_id' => $jobId,
                'impact' => $impact,
                'strategy' => $strategy,
                'estimated_steps' => $estimatedSteps,
            ];
        }

        $result = $this->withLifecycleLock($scope, $targetId, function () use ($runner, $strategy): array {
            if (!empty($strategy['wrap_transaction'])) {
                return $this->runInTransaction($runner);
            }
            return $runner();
        }, (int) ($strategy['lock_timeout'] ?? 0));

        $result['impact'] ??= $impact;
        $result['strategy'] = $strategy;
        $result['estimated_steps'] = $estimatedSteps;
        $result['status'] = $result['status'] ?? 'completed';
        $result['success'] = !empty($result['errors']) ? false : (($result['success'] ?? true) !== false);

        return $result;
    }

    private function buildDispatchStrategy(
        string $scope,
        int $targetId,
        array $impact,
        array $options = [],
        array $payload = []
    ): array {
        $entitiesCount = (int) ($impact['categories_count'] ?? 0) + (int) ($impact['pages_count'] ?? 0);
        $thresholds = [
            'values_count' => 2000,
            'entities_count' => 500,
            'properties_count' => 150,
            'descendants_count' => 150,
        ];
        $mode = 'sync';
        $reason = 'below_threshold';

        if (!empty($options['force_async'])) {
            $mode = 'queue';
            $reason = 'force_async';
        } elseif (!empty($options['force_sync'])) {
            $mode = 'sync';
            $reason = 'force_sync';
        } elseif (
            (int) ($impact['values_count'] ?? 0) >= $thresholds['values_count']
            || $entitiesCount >= $thresholds['entities_count']
            || (int) ($impact['properties_count'] ?? 0) >= $thresholds['properties_count']
            || (int) ($impact['descendants_count'] ?? 0) >= $thresholds['descendants_count']
        ) {
            $mode = 'queue';
            $reason = 'impact_threshold';
        }

        $strategy = [
            'mode' => $mode,
            'reason' => $reason,
            'wrap_transaction' => $mode === 'sync',
            'lock_timeout' => $mode === 'sync' ? 0 : 1,
            'priority' => $mode === 'queue' ? 3 : 5,
        ];

        return Hook::filter(
            'propertyLifecycleDispatchStrategy',
            $strategy,
            $scope,
            $targetId,
            $impact,
            $options,
            $payload
        );
    }

    private function estimateLifecycleSteps(string $scope, array $impact): int {
        $steps = match ($scope) {
            'property_type' => (int) ($impact['properties_count'] ?? 0) + (int) ($impact['values_count'] ?? 0),
            'property' => 1 + (int) ($impact['values_count'] ?? 0),
            'property_set' => (int) ($impact['categories_count'] ?? 0) + (int) ($impact['pages_count'] ?? 0),
            'category_type' => (int) ($impact['categories_count'] ?? 0) + (int) ($impact['pages_count'] ?? 0) + (int) ($impact['descendants_count'] ?? 0),
            default => 0,
        };

        return max(1, $steps);
    }

    private function createLifecycleJob(
        string $scope,
        int $targetId,
        array $payload,
        array $impact,
        array $strategy,
        array $options,
        int $estimatedSteps
    ): int {
        $lockKey = 'property_lifecycle:' . $scope . ':' . $targetId;
        SafeMySQL::gi()->query(
            'INSERT INTO ?n SET ?u',
            Constants::PROPERTY_LIFECYCLE_JOBS_TABLE,
            [
                'scope' => $scope,
                'target_id' => $targetId,
                'status' => 'queued',
                'requested_by' => (int) ($options['requested_by'] ?? 0) ?: null,
                'dry_run' => !empty($options['dry_run']) ? 1 : 0,
                'is_async' => 1,
                'priority' => (int) ($strategy['priority'] ?? 5),
                'total_steps' => $estimatedSteps,
                'processed_steps' => 0,
                'progress_percent' => 0,
                'cursor_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'payload_json' => json_encode(array_merge($payload, ['impact' => $impact]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'result_json' => null,
                'lock_key' => $lockKey,
            ]
        );

        return (int) SafeMySQL::gi()->insertId();
    }

    private function withLifecycleLock(string $scope, int $targetId, callable $callback, int $timeout = 0): array {
        $lockName = substr('property_lifecycle:' . $scope . ':' . $targetId, 0, 191);
        $acquired = (int) SafeMySQL::gi()->getOne('SELECT GET_LOCK(?s, ?i)', $lockName, max(0, $timeout));
        if ($acquired !== 1) {
            return [
                'scope' => $scope,
                'status' => 'locked',
                'success' => false,
                'errors' => ['lifecycle_locked'],
            ];
        }

        try {
            $result = $callback();
            if (!is_array($result)) {
                $result = [
                    'scope' => $scope,
                    'status' => 'completed',
                    'success' => true,
                ];
            }
            return $result;
        } finally {
            SafeMySQL::gi()->getOne('SELECT RELEASE_LOCK(?s)', $lockName);
        }
    }

    private function runInTransaction(callable $callback): array {
        SafeMySQL::gi()->query('START TRANSACTION');
        try {
            $result = $callback();
            SafeMySQL::gi()->query('COMMIT');
            return is_array($result) ? $result : [];
        } catch (\Throwable $e) {
            SafeMySQL::gi()->query('ROLLBACK');
            throw $e;
        }
    }

    private function propertyTypeSchemaChanged(array $beforeTypeData = [], array $afterTypeData = []): bool {
        return $this->normalizeTypeFields($beforeTypeData['fields'] ?? []) !== $this->normalizeTypeFields($afterTypeData['fields'] ?? []);
    }

    private function propertySchemaChanged(array $beforePropertyData = [], array $afterPropertyData = []): bool {
        return (int) ($beforePropertyData['type_id'] ?? 0) !== (int) ($afterPropertyData['type_id'] ?? 0)
            || (string) ($beforePropertyData['entity_type'] ?? '') !== (string) ($afterPropertyData['entity_type'] ?? '')
            || json_encode($beforePropertyData['default_values'] ?? [], JSON_UNESCAPED_UNICODE) !== json_encode($afterPropertyData['default_values'] ?? [], JSON_UNESCAPED_UNICODE)
            || (int) ($beforePropertyData['is_multiple'] ?? 0) !== (int) ($afterPropertyData['is_multiple'] ?? 0)
            || (int) ($beforePropertyData['is_required'] ?? 0) !== (int) ($afterPropertyData['is_required'] ?? 0);
    }

    private function bumpSchemaVersion(string $table, string $idField, int $id): int {
        if ($id <= 0) {
            return 0;
        }
        SafeMySQL::gi()->query(
            'UPDATE ?n SET schema_version = schema_version + 1 WHERE ?n = ?i',
            $table,
            $idField,
            $id
        );
        return (int) SafeMySQL::gi()->getOne(
            'SELECT schema_version FROM ?n WHERE ?n = ?i',
            $table,
            $idField,
            $id
        );
    }

    private function ensureInfrastructure(): void {
        if ($this->infrastructureReady) {
            return;
        }
        $requiredTables = [
            Constants::PROPERTY_TYPES_TABLE,
            Constants::PROPERTIES_TABLE,
            Constants::PROPERTY_VALUES_TABLE,
            Constants::PROPERTY_LIFECYCLE_JOBS_TABLE,
        ];
        foreach ($requiredTables as $table) {
            if (!$this->tableExists($table)) {
                throw new \RuntimeException('Lifecycle infrastructure is not installed. Missing table: ' . $table);
            }
        }
        if (!$this->tableHasColumn(Constants::PROPERTY_TYPES_TABLE, 'schema_version')) {
            throw new \RuntimeException('Lifecycle infrastructure is not installed. Missing column: ' . Constants::PROPERTY_TYPES_TABLE . '.schema_version');
        }
        if (!$this->tableHasColumn(Constants::PROPERTIES_TABLE, 'schema_version')) {
            throw new \RuntimeException('Lifecycle infrastructure is not installed. Missing column: ' . Constants::PROPERTIES_TABLE . '.schema_version');
        }
        $this->infrastructureReady = true;
    }

    private function tableHasColumn(string $table, string $column): bool {
        $columnName = SafeMySQL::gi()->getOne(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s AND COLUMN_NAME = ?s LIMIT 1',
            $table,
            $column
        );
        return is_string($columnName) && $columnName !== '';
    }

    private function tableExists(string $table): bool {
        $tableName = SafeMySQL::gi()->getOne(
            'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s LIMIT 1',
            $table
        );
        return is_string($tableName) && $tableName !== '';
    }

    public function rebuildPropertyType(int $typeId, array $beforeTypeData = [], array $afterTypeData = []): array {
        $result = [
            'scope' => 'property_type',
            'type_id' => $typeId,
            'properties_updated' => 0,
            'property_values_updated' => 0,
            'errors' => [],
            'impact' => $this->getPropertyTypeImpact($typeId),
        ];

        $beforeFields = $this->normalizeTypeFields($beforeTypeData['fields'] ?? []);
        $afterFields = $this->normalizeTypeFields($afterTypeData['fields'] ?? []);
        if ($typeId <= 0 || empty($afterFields) || $beforeFields === $afterFields) {
            return $result;
        }

        $config = $this->applyRuntimeConfig('property_type', [
            'type_id' => $typeId,
            'impact' => $result['impact'],
        ]);
        $propertyRows = SafeMySQL::gi()->getAll(
            'SELECT property_id, name, default_values, is_multiple, is_required FROM ?n WHERE type_id = ?i',
            Constants::PROPERTIES_TABLE,
            $typeId
        );

        Hook::run('beforePropertyTypeLifecycleRebuild', $typeId, $beforeTypeData, $afterTypeData, $result);

        $propertyMeta = [];
        foreach ($propertyRows as $propertyRow) {
            $propertyId = (int) ($propertyRow['property_id'] ?? 0);
            if ($propertyId <= 0) {
                continue;
            }
            $propertyMeta[$propertyId] = $propertyRow;
            $normalizedDefaultValues = $this->normalizeStoredFields(
                $propertyRow['default_values'] ?? '[]',
                $afterFields,
                true,
                $propertyRow
            );
            if (SafeMySQL::gi()->query(
                'UPDATE ?n SET default_values = ?s WHERE property_id = ?i',
                Constants::PROPERTIES_TABLE,
                json_encode($normalizedDefaultValues, JSON_UNESCAPED_UNICODE),
                $propertyId
            )) {
                $result['properties_updated']++;
            }
        }

        foreach (array_chunk(array_keys($propertyMeta), $config['chunk_size']) as $propertyIdsChunk) {
            $valueRows = SafeMySQL::gi()->getAll(
                'SELECT value_id, property_id, property_values FROM ?n WHERE property_id IN (?a)',
                Constants::PROPERTY_VALUES_TABLE,
                $propertyIdsChunk
            );
            foreach ($valueRows as $valueRow) {
                $propertyId = (int) ($valueRow['property_id'] ?? 0);
                $valueId = (int) ($valueRow['value_id'] ?? 0);
                if ($valueId <= 0 || empty($propertyMeta[$propertyId])) {
                    continue;
                }
                $normalizedPropertyValues = $this->normalizeStoredFields(
                    $valueRow['property_values'] ?? '[]',
                    $afterFields,
                    false,
                    $propertyMeta[$propertyId]
                );
                if (SafeMySQL::gi()->query(
                    'UPDATE ?n SET property_values = ?s WHERE value_id = ?i',
                    Constants::PROPERTY_VALUES_TABLE,
                    json_encode($normalizedPropertyValues, JSON_UNESCAPED_UNICODE),
                    $valueId
                )) {
                    $result['property_values_updated']++;
                }
            }
        }

        Hook::run('afterPropertyTypeLifecycleRebuild', $typeId, $beforeTypeData, $afterTypeData, $result);
        return $result;
    }

    public function rebuildProperty(int $propertyId, array $beforePropertyData = [], array $afterPropertyData = []): array {
        $result = [
            'scope' => 'property',
            'property_id' => $propertyId,
            'property_updated' => 0,
            'property_values_updated' => 0,
            'property_values_deleted' => 0,
            'property_values_inserted' => 0,
            'errors' => [],
            'impact' => $this->getPropertyImpact($propertyId),
        ];

        if ($propertyId <= 0) {
            return $result;
        }

        $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
        $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
        $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
        if (!$objectModelProperties || !$objectModelCategoriesTypes || !$objectModelCategories) {
            $result['errors'][] = 'required_models_not_loaded';
            return $result;
        }

        $currentProperty = $objectModelProperties->getPropertyData($propertyId);
        if (!$currentProperty) {
            $result['errors'][] = 'property_not_found';
            return $result;
        }

        $config = $this->applyRuntimeConfig('property', [
            'property_id' => $propertyId,
            'impact' => $result['impact'],
        ]);
        $beforeTypeFields = $this->getTypeFields((int) ($beforePropertyData['type_id'] ?? $currentProperty['type_id'] ?? 0));
        $afterTypeFields = $this->getTypeFields((int) ($afterPropertyData['type_id'] ?? $currentProperty['type_id'] ?? 0));
        $beforeTypeFields = !empty($beforeTypeFields) ? $beforeTypeFields : $afterTypeFields;
        $afterTypeFields = !empty($afterTypeFields) ? $afterTypeFields : $beforeTypeFields;
        $beforePropertyContext = array_merge($currentProperty, $beforePropertyData);
        $afterPropertyContext = array_merge($currentProperty, $afterPropertyData);
        $beforeDefaultValues = $this->normalizeStoredFields(
            $beforePropertyData['default_values'] ?? '[]',
            $beforeTypeFields,
            true,
            $beforePropertyContext
        );
        $afterDefaultValues = $this->normalizeStoredFields(
            $afterPropertyData['default_values'] ?? ($currentProperty['default_values'] ?? '[]'),
            $afterTypeFields,
            true,
            $afterPropertyContext
        );
        $schemaChanged = $beforeTypeFields !== $afterTypeFields;
        $defaultsChanged = !$this->normalizedPayloadsEqual($beforeDefaultValues, $afterDefaultValues);
        $afterDefaultRuntimeValues = $this->convertDefaultItemsToRuntime($afterDefaultValues);
        $beforeDefaultRuntimeValues = $this->convertDefaultItemsToRuntime($beforeDefaultValues);

        Hook::run('beforePropertyLifecycleRebuild', $propertyId, $beforePropertyData, $afterPropertyData, $result);

        if ($schemaChanged || $defaultsChanged) {
            if (SafeMySQL::gi()->query(
                'UPDATE ?n SET default_values = ?s WHERE property_id = ?i',
                Constants::PROPERTIES_TABLE,
                json_encode($afterDefaultValues, JSON_UNESCAPED_UNICODE),
                $propertyId
            )) {
                $result['property_updated']++;
            }

            $valueRows = SafeMySQL::gi()->getAll(
                'SELECT value_id, property_values FROM ?n WHERE property_id = ?i',
                Constants::PROPERTY_VALUES_TABLE,
                $propertyId
            );
            foreach (array_chunk($valueRows, $config['chunk_size']) as $valueRowsChunk) {
                foreach ($valueRowsChunk as $valueRow) {
                    $valueId = (int) ($valueRow['value_id'] ?? 0);
                    if ($valueId <= 0) {
                        continue;
                    }
                    $currentNormalizedAfter = $this->normalizeStoredFields(
                        $valueRow['property_values'] ?? '[]',
                        $afterTypeFields,
                        false,
                        $afterPropertyContext
                    );
                    $shouldRewriteValue = $schemaChanged;
                    $normalizedPropertyValues = $currentNormalizedAfter;

                    if ($defaultsChanged || $schemaChanged) {
                        $currentNormalizedBefore = $this->normalizeStoredFields(
                            $valueRow['property_values'] ?? '[]',
                            $beforeTypeFields,
                            false,
                            $beforePropertyContext
                        );
                        if ($this->normalizedPayloadsEqual($currentNormalizedBefore, $beforeDefaultRuntimeValues)) {
                            $normalizedPropertyValues = $afterDefaultRuntimeValues;
                            $shouldRewriteValue = true;
                        }
                    }

                    if (!$shouldRewriteValue) {
                        continue;
                    }

                    if (SafeMySQL::gi()->query(
                        'UPDATE ?n SET property_values = ?s WHERE value_id = ?i',
                        Constants::PROPERTY_VALUES_TABLE,
                        json_encode($normalizedPropertyValues, JSON_UNESCAPED_UNICODE),
                        $valueId
                    )) {
                        $result['property_values_updated']++;
                    }
                }
            }
        }

        $syncResult = $this->syncPropertyEntityAssignments(
            $propertyId,
            (string) ($afterPropertyData['entity_type'] ?? $currentProperty['entity_type'] ?? 'all'),
            $afterDefaultRuntimeValues,
            $objectModelProperties,
            $objectModelCategoriesTypes,
            $objectModelCategories,
            $config['chunk_size']
        );
        $result['property_values_deleted'] += $syncResult['deleted_values'];
        $result['property_values_inserted'] += $syncResult['inserted_values'];

        Hook::run('afterPropertyLifecycleRebuild', $propertyId, $beforePropertyData, $afterPropertyData, $result);
        return $result;
    }

    public function syncPropertySetChange(int $setId, array $addedPropertyIds, array $deletedPropertyIds): array {
        $result = [
            'scope' => 'property_set',
            'set_id' => $setId,
            'added_property_ids' => array_values($addedPropertyIds),
            'deleted_property_ids' => array_values($deletedPropertyIds),
            'impact' => $this->getPropertySetImpact($setId),
        ];

        Hook::run('beforePropertySetLifecycleRebuild', $setId, $result);

        $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
        $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
        $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
        if (!$objectModelProperties || !$objectModelCategories || !$objectModelCategoriesTypes) {
            $result['errors'][] = 'required_models_not_loaded';
            Hook::run('afterPropertySetLifecycleRebuild', $setId, $result);
            return $result;
        }

        $typeIds = $objectModelCategoriesTypes->getCategoryTypeIdsBySet($setId);
        if (empty($typeIds)) {
            Hook::run('afterPropertySetLifecycleRebuild', $setId, $result);
            return $result;
        }

        $allRequiredSetIds = $objectModelCategoriesTypes->getCategoriesTypeSetsData($typeIds);
        $allCategoriesIds = $objectModelCategoriesTypes->getAllCategoriesByType($typeIds);
        $allPages = !empty($allCategoriesIds) ? $objectModelCategories->getCategoryPages($allCategoriesIds) : [];
        $objects = [
            'objectModelProperties' => $objectModelProperties,
            'objectModelCategoriesTypes' => $objectModelCategoriesTypes,
            'objectModelCategories' => $objectModelCategories,
        ];

        $syncStats = updatePropertiesForAnEntity(
            $allCategoriesIds,
            $allPages,
            $allRequiredSetIds,
            $objects,
            $setId,
            $addedPropertyIds,
            $deletedPropertyIds
        );
        if (is_array($syncStats)) {
            $result = array_merge($result, $syncStats);
        }

        Hook::run('afterPropertySetLifecycleRebuild', $setId, $result);
        return $result;
    }

    public function syncCategoryTypeChange(int $typeId, array $setIds, array $allTypeIds): array {
        $result = [
            'scope' => 'category_type',
            'type_id' => $typeId,
            'set_ids' => array_values($setIds),
            'all_type_ids' => array_values($allTypeIds),
            'impact' => $this->getCategoryTypeImpact($typeId),
        ];

        Hook::run('beforeCategoryTypeLifecycleRebuild', $typeId, $result);

        $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
        $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
        $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
        if (!$objectModelCategoriesTypes || !$objectModelCategories || !$objectModelProperties) {
            $result['errors'][] = 'required_models_not_loaded';
            Hook::run('afterCategoryTypeLifecycleRebuild', $typeId, $result);
            return $result;
        }

        $allCategoriesIds = $objectModelCategoriesTypes->getAllCategoriesByType($allTypeIds);
        $allPages = !empty($allCategoriesIds) ? $objectModelCategories->getCategoryPages($allCategoriesIds) : [];
        $objects = [
            'objectModelProperties' => $objectModelProperties,
            'objectModelCategoriesTypes' => $objectModelCategoriesTypes,
            'objectModelCategories' => $objectModelCategories,
        ];

        $syncStats = updatePropertiesForAnEntity(
            $allCategoriesIds,
            $allPages,
            $setIds,
            $objects
        );
        if (is_array($syncStats)) {
            $result = array_merge($result, $syncStats);
        }

        Hook::run('afterCategoryTypeLifecycleRebuild', $typeId, $result);
        return $result;
    }

    private function syncPropertyEntityAssignments(
        int $propertyId,
        string $propertyEntityType,
        array $defaultValuesPrepared,
        $objectModelProperties,
        $objectModelCategoriesTypes,
        $objectModelCategories,
        int $chunkSize
    ): array {
        $result = [
            'deleted_values' => 0,
            'inserted_values' => 0,
        ];

        $setIds = SafeMySQL::gi()->getCol(
            'SELECT set_id FROM ?n WHERE property_id = ?i',
            Constants::PROPERTY_SET_TO_PROPERTIES_TABLE,
            $propertyId
        );
        $setIds = array_values(array_unique(array_filter(array_map('intval', $setIds), static fn(int $id): bool => $id > 0)));

        $allowedEntityTypes = $propertyEntityType === 'all'
            ? ['category', 'page']
            : [$propertyEntityType];

        $requiredSetsByCategory = [];
        $requiredSetsByPage = [];
        if (!empty($setIds)) {
            $entitySetRows = $objectModelCategoriesTypes->getCategorySetPageData($setIds);
            foreach ($entitySetRows as $entitySetRow) {
                $setId = (int) ($entitySetRow['set_id'] ?? 0);
                if ($setId <= 0 || !in_array($setId, $setIds, true)) {
                    continue;
                }

                $categoryId = (int) ($entitySetRow['category_id'] ?? 0);
                if ($categoryId > 0 && in_array('category', $allowedEntityTypes, true)) {
                    $requiredSetsByCategory[$categoryId][$setId] = true;
                }

                $pageId = (int) ($entitySetRow['page_id'] ?? 0);
                if ($pageId > 0 && in_array('page', $allowedEntityTypes, true)) {
                    $requiredSetsByPage[$pageId][$setId] = true;
                }
            }
        }
        foreach ($requiredSetsByCategory as $categoryId => $requiredSetMap) {
            $requiredSetsByCategory[$categoryId] = array_keys($requiredSetMap);
        }
        foreach ($requiredSetsByPage as $pageId => $requiredSetMap) {
            $requiredSetsByPage[$pageId] = array_keys($requiredSetMap);
        }

        $valueRows = SafeMySQL::gi()->getAll(
            'SELECT value_id, entity_id, entity_type, set_id FROM ?n WHERE property_id = ?i',
            Constants::PROPERTY_VALUES_TABLE,
            $propertyId
        );
        $existingMap = [];
        $valueIdsToDelete = [];

        foreach ($valueRows as $valueRow) {
            $entityType = (string) ($valueRow['entity_type'] ?? '');
            $entityId = (int) ($valueRow['entity_id'] ?? 0);
            $setId = (int) ($valueRow['set_id'] ?? 0);
            $valueId = (int) ($valueRow['value_id'] ?? 0);
            if ($valueId <= 0 || $entityId <= 0 || $setId <= 0) {
                continue;
            }

            $requiredSets = $entityType === 'category'
                ? ($requiredSetsByCategory[$entityId] ?? [])
                : ($entityType === 'page' ? ($requiredSetsByPage[$entityId] ?? []) : []);

            if (!in_array($entityType, $allowedEntityTypes, true) || !in_array($setId, $requiredSets, true)) {
                $valueIdsToDelete[] = $valueId;
                continue;
            }

            $existingMap[$entityType][$entityId][$setId] = true;
        }

        if (!empty($valueIdsToDelete)) {
            $objectModelProperties->deletePropertyValues($valueIdsToDelete);
            $result['deleted_values'] += count($valueIdsToDelete);
        }

        foreach (array_chunk(array_keys($requiredSetsByCategory), $chunkSize) as $categoryIdsChunk) {
            foreach ($categoryIdsChunk as $categoryId) {
                foreach ($requiredSetsByCategory[$categoryId] as $setId) {
                    if (!empty($existingMap['category'][$categoryId][$setId])) {
                        continue;
                    }
                    $fields = [
                        'entity_id' => (int) $categoryId,
                        'property_id' => $propertyId,
                        'entity_type' => 'category',
                        'set_id' => (int) $setId,
                        'fields' => $defaultValuesPrepared,
                    ];
                    $saveResult = $objectModelProperties->updatePropertiesValueEntities($fields);
                    if ($saveResult->isSuccess()) {
                        $existingMap['category'][$categoryId][$setId] = true;
                        $result['inserted_values']++;
                    }
                }
            }
        }

        foreach (array_chunk(array_keys($requiredSetsByPage), $chunkSize) as $pageIdsChunk) {
            foreach ($pageIdsChunk as $pageId) {
                foreach ($requiredSetsByPage[$pageId] as $setId) {
                    if (!empty($existingMap['page'][$pageId][$setId])) {
                        continue;
                    }
                    $fields = [
                        'entity_id' => (int) $pageId,
                        'property_id' => $propertyId,
                        'entity_type' => 'page',
                        'set_id' => (int) $setId,
                        'fields' => $defaultValuesPrepared,
                    ];
                    $saveResult = $objectModelProperties->updatePropertiesValueEntities($fields);
                    if ($saveResult->isSuccess()) {
                        $existingMap['page'][$pageId][$setId] = true;
                        $result['inserted_values']++;
                    }
                }
            }
        }

        return $result;
    }

    private function convertDefaultItemsToRuntime(array $defaultValues): array {
        foreach ($defaultValues as &$item) {
            if (!is_array($item)) {
                continue;
            }
            $item['value'] = $item['default'] ?? ($item['value'] ?? '');
            unset($item['default']);
        }
        unset($item);
        return array_values($defaultValues);
    }

    private function getPropertyIdsByType(int $typeId): array {
        if ($typeId <= 0) {
            return [];
        }
        return SafeMySQL::gi()->getCol(
            'SELECT property_id FROM ?n WHERE type_id = ?i',
            Constants::PROPERTIES_TABLE,
            $typeId
        );
    }

    private function getTypeFields(int $typeId): array {
        if ($typeId <= 0) {
            return [];
        }
        $fields = SafeMySQL::gi()->getOne(
            'SELECT fields FROM ?n WHERE type_id = ?i',
            Constants::PROPERTY_TYPES_TABLE,
            $typeId
        );
        return $this->normalizeTypeFields($fields);
    }

    private function applyRuntimeConfig(string $scope, array $context = []): array {
        $config = [
            'chunk_size' => 200,
            'memory_limit' => '512M',
            'set_time_limit' => 0,
        ];

        $config = Hook::filter('propertyLifecycleRuntimeConfig', $config, $scope, $context);
        $config['chunk_size'] = max(1, (int) ($config['chunk_size'] ?? 200));

        if (!empty($config['memory_limit'])) {
            @ini_set('memory_limit', (string) $config['memory_limit']);
        }
        if (isset($config['set_time_limit'])) {
            @set_time_limit((int) $config['set_time_limit']);
        }

        return $config;
    }

    private function normalizeTypeFields(mixed $fields): array {
        return PropertyFieldContract::normalizeTypeFields($fields);
    }

    private function decodeFieldPayload(mixed $payload): array {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            } elseif ($payload === '') {
                $payload = [];
            } else {
                $payload = [$payload];
            }
        }

        if (!is_array($payload)) {
            return [];
        }

        if (isset($payload['type']) || isset($payload['default']) || isset($payload['value']) || isset($payload['label'])) {
            return [$payload];
        }

        return array_values($payload);
    }

    private function normalizeStoredFields(mixed $payload, array $typeFields, bool $defaultMode, array $propertyData): array {
        return $defaultMode
            ? PropertyFieldContract::normalizeDefaultFieldsForStorage($payload, $typeFields, $propertyData, $payload)
            : PropertyFieldContract::normalizeValueFieldsForStorage(
                $payload,
                $propertyData['default_values'] ?? [],
                $typeFields,
                $propertyData
            );
    }

    private function normalizeFieldItem(array $sourceItem, array $fieldDefinition, bool $defaultMode, array $propertyData): array {
        $fieldType = strtolower(trim((string) ($fieldDefinition['type'] ?? 'text')));
        $fieldUid = trim((string) ($fieldDefinition['uid'] ?? ''));
        if ($fieldUid === '') {
            $fieldUid = 'legacy_0';
        }
        $valueKey = $defaultMode ? 'default' : 'value';
        $sourceValue = $sourceItem[$valueKey] ?? ($defaultMode ? ($sourceItem['value'] ?? '') : ($sourceItem['default'] ?? ''));
        $fallbackLabel = $propertyData['name'] ?? '';
        $label = $sourceItem['label'] ?? $fallbackLabel;
        $required = isset($sourceItem['required']) ? (int) !empty($sourceItem['required']) : (int) !empty($propertyData['is_required']);
        $multiple = isset($sourceItem['multiple']) ? (int) !empty($sourceItem['multiple']) : (int) !empty($propertyData['is_multiple']);
        $title = isset($sourceItem['title']) && is_scalar($sourceItem['title']) ? (string) $sourceItem['title'] : '';

        $normalized = [
            'uid' => $fieldUid,
            'type' => $fieldType,
            'label' => is_scalar($label) ? (string) $label : (string) $fallbackLabel,
            'title' => $title,
            $valueKey => '',
            'required' => $required,
            'multiple' => $multiple,
        ];

        if ($fieldType === 'select') {
            $choiceLabels = $this->extractChoiceLabels($label, $sourceValue, $fallbackLabel);
            $normalized[$valueKey] = $this->buildSelectValue($sourceValue, $choiceLabels);
            return $normalized;
        }

        if ($fieldType === 'checkbox' || $fieldType === 'radio') {
            $choiceLabels = $this->extractChoiceLabels($label, $sourceValue, $fallbackLabel);
            $selectedIndexes = $this->extractSelectedIndexes($sourceValue);
            if ($fieldType === 'radio' && count($selectedIndexes) > 1) {
                $selectedIndexes = [reset($selectedIndexes)];
            }
            $normalized['label'] = $choiceLabels;
            $normalized['title'] = $title !== '' ? $title : (string) $fallbackLabel;
            $normalized[$valueKey] = array_values($selectedIndexes);
            return $normalized;
        }

        if ($fieldType === 'file' || $fieldType === 'image') {
            $normalized[$valueKey] = $this->normalizeFileValue($sourceValue);
            return $normalized;
        }

        $normalized[$valueKey] = $this->normalizeScalarValue($sourceValue, $multiple);
        return $normalized;
    }

    private function extractChoiceLabels(mixed $label, mixed $sourceValue, string $fallbackLabel): array {
        if (is_array($label)) {
            $normalized = array_values(array_filter(array_map(static function ($item): string {
                return trim((string) $item);
            }, $label), static fn(string $item): bool => $item !== ''));
            if (!empty($normalized)) {
                return $normalized;
            }
        }

        if (is_string($sourceValue) && (str_contains($sourceValue, '{|}') || str_contains($sourceValue, '='))) {
            $labels = [];
            foreach (explode('{|}', html_entity_decode($sourceValue)) as $option) {
                if ($option === '') {
                    continue;
                }
                [$optionLabel] = array_pad(explode('=', $option, 2), 2, '');
                $optionLabel = trim((string) $optionLabel);
                if ($optionLabel !== '') {
                    $labels[] = $optionLabel;
                }
            }
            if (!empty($labels)) {
                return $labels;
            }
        }

        if (is_scalar($label) && trim((string) $label) !== '') {
            return [trim((string) $label)];
        }

        if (is_array($sourceValue)) {
            $labels = array_values(array_filter(array_map(static function ($item): string {
                return trim((string) $item);
            }, $sourceValue), static fn(string $item): bool => $item !== ''));
            if (!empty($labels)) {
                return $labels;
            }
        }

        return [trim((string) $fallbackLabel) !== '' ? trim((string) $fallbackLabel) : 'Value'];
    }

    private function buildSelectValue(mixed $sourceValue, array $choiceLabels): string {
        if (is_string($sourceValue) && (str_contains($sourceValue, '{|}') || str_contains($sourceValue, '='))) {
            return $sourceValue;
        }

        $selectedIndexes = $this->extractSelectedIndexes($sourceValue);
        $options = [];

        if (!empty($choiceLabels)) {
            foreach ($choiceLabels as $index => $choiceLabel) {
                $optionValue = $choiceLabel;
                $options[] = $choiceLabel . '=' . $optionValue . (isset($selectedIndexes[(string) $index]) ? '{*}' : '');
            }
            return implode('{|}', $options);
        }

        if (is_array($sourceValue)) {
            foreach ($sourceValue as $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }
                $options[] = $value . '=' . $value . '{*}';
            }
            return implode('{|}', $options);
        }

        $sourceValue = trim((string) $sourceValue);
        if ($sourceValue === '') {
            return '';
        }

        return $sourceValue . '=' . $sourceValue . '{*}';
    }

    private function extractSelectedIndexes(mixed $sourceValue): array {
        if (is_string($sourceValue) && (str_contains($sourceValue, '{|}') || str_contains($sourceValue, '='))) {
            $selected = [];
            foreach (explode('{|}', html_entity_decode($sourceValue)) as $index => $option) {
                if ($option !== '' && str_contains($option, '{*}')) {
                    $selected[(string) $index] = (string) $index;
                }
            }
            return $selected;
        }

        if (is_array($sourceValue)) {
            $selected = [];
            foreach ($sourceValue as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $selected[$item] = $item;
                }
            }
            return $selected;
        }

        $sourceValue = trim((string) $sourceValue);
        if ($sourceValue === '') {
            return [];
        }

        if (str_contains($sourceValue, ',')) {
            $selected = [];
            foreach (explode(',', $sourceValue) as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $selected[$item] = $item;
                }
            }
            return $selected;
        }

        return ['0' => '0'];
    }

    private function normalizeFileValue(mixed $sourceValue): string {
        if (is_array($sourceValue)) {
            $sourceValue = implode(',', array_filter(array_map(static function ($item): string {
                return trim((string) $item);
            }, $sourceValue), static fn(string $item): bool => $item !== ''));
        }

        return trim((string) $sourceValue);
    }

    private function normalizeScalarValue(mixed $sourceValue, int $multiple): mixed {
        if ($multiple) {
            if (!is_array($sourceValue)) {
                $sourceValue = $sourceValue === '' ? [] : [$sourceValue];
            }
            return array_values(array_map(static function ($item): string {
                return (string) $item;
            }, $sourceValue));
        }

        if (is_array($sourceValue)) {
            $sourceValue = reset($sourceValue);
        }

        return is_scalar($sourceValue) ? (string) $sourceValue : '';
    }

    private function normalizedPayloadsEqual(array $left, array $right): bool {
        return json_encode(array_values($left), JSON_UNESCAPED_UNICODE)
            === json_encode(array_values($right), JSON_UNESCAPED_UNICODE);
    }
}
