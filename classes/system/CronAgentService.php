<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

/**
 * Сервис агентного крона.
 * Управляет инфраструктурой БД, расписанием, блокировками и последовательным выполнением задач.
 */
class CronAgentService {
    private const SCHEDULER_LAST_TICK_OPTION = 'cron_scheduler_last_tick_at';

    private static bool $infrastructureReady = false;

    private const DEFAULT_MAX_AGENTS_PER_TICK = 3;
    private const DEFAULT_MAX_WEIGHT_PER_TICK = 5;
    private const DEFAULT_MAX_CONCURRENT = 1;
    private const DEFAULT_GLOBAL_LOCK_TIMEOUT = 0;
    private const DEFAULT_DISPATCH_LOCK_TIMEOUT = 0;
    private const DEFAULT_DUE_FETCH_LIMIT = 25;
    private const DEFAULT_STALE_GRACE_SEC = 15;
    private const DEFAULT_RUN_HISTORY_RETENTION_DAYS = 30;
    private const DEFAULT_RUN_HISTORY_MAX_ROWS = 50000;
    private const DEFAULT_TICK_TIME_BUDGET_SEC = 45;
    private const DEFAULT_MEMORY_SOFT_LIMIT_MB = 256;
    private const DEFAULT_AGENT_MEMORY_SOFT_LIMIT_MB = 224;

    public static function ensureInfrastructure(bool $force = false): void {
        if (self::$infrastructureReady && !$force) {
            return;
        }

        if (!SysClass::checkDatabaseConnection()) {
            self::$infrastructureReady = false;
            return;
        }

        if (!$force) {
            self::$infrastructureReady = self::tableExists(Constants::CRON_AGENTS_TABLE)
                && self::tableExists(Constants::CRON_AGENT_RUNS_TABLE);
            if (!self::$infrastructureReady) {
                throw new \RuntimeException('Cron infrastructure is not installed. Run install/upgrade first.');
            }
            ImportMediaQueueService::ensureInfrastructure(false);
            BackupService::ensureInfrastructure(false);
            return;
        }

        self::createCronAgentsTable();
        self::createCronAgentRunsTable();
        ImportMediaQueueService::ensureInfrastructure($force);
        BackupService::ensureInfrastructure($force);
        self::$infrastructureReady = true;
        self::seedDefaultAgents();
    }

    public static function resetInfrastructureState(): void {
        self::$infrastructureReady = false;
    }

    public static function getSchedulerConfig(): array {
        return [
            'max_agents_per_tick' => max(1, (int) (defined('ENV_CRON_AGENTS_MAX_PER_TICK') ? ENV_CRON_AGENTS_MAX_PER_TICK : self::DEFAULT_MAX_AGENTS_PER_TICK)),
            'max_weight_per_tick' => max(1, (int) (defined('ENV_CRON_AGENTS_MAX_WEIGHT_PER_TICK') ? ENV_CRON_AGENTS_MAX_WEIGHT_PER_TICK : self::DEFAULT_MAX_WEIGHT_PER_TICK)),
            'max_concurrent' => max(1, (int) (defined('ENV_CRON_AGENTS_MAX_CONCURRENT') ? ENV_CRON_AGENTS_MAX_CONCURRENT : self::DEFAULT_MAX_CONCURRENT)),
            'global_lock_timeout' => max(0, (int) (defined('ENV_CRON_AGENTS_GLOBAL_LOCK_TIMEOUT') ? ENV_CRON_AGENTS_GLOBAL_LOCK_TIMEOUT : self::DEFAULT_GLOBAL_LOCK_TIMEOUT)),
            'dispatch_lock_timeout' => max(0, (int) (defined('ENV_CRON_AGENTS_DISPATCH_LOCK_TIMEOUT') ? ENV_CRON_AGENTS_DISPATCH_LOCK_TIMEOUT : self::DEFAULT_DISPATCH_LOCK_TIMEOUT)),
            'due_fetch_limit' => max(5, (int) (defined('ENV_CRON_AGENTS_DUE_FETCH_LIMIT') ? ENV_CRON_AGENTS_DUE_FETCH_LIMIT : self::DEFAULT_DUE_FETCH_LIMIT)),
            'run_history_retention_days' => max(1, (int) (defined('ENV_CRON_AGENTS_RUN_HISTORY_RETENTION_DAYS') ? ENV_CRON_AGENTS_RUN_HISTORY_RETENTION_DAYS : self::DEFAULT_RUN_HISTORY_RETENTION_DAYS)),
            'run_history_max_rows' => max(100, (int) (defined('ENV_CRON_AGENTS_RUN_HISTORY_MAX_ROWS') ? ENV_CRON_AGENTS_RUN_HISTORY_MAX_ROWS : self::DEFAULT_RUN_HISTORY_MAX_ROWS)),
            'tick_time_budget_sec' => max(5, (int) (defined('ENV_CRON_TICK_TIME_BUDGET_SEC') ? ENV_CRON_TICK_TIME_BUDGET_SEC : self::DEFAULT_TICK_TIME_BUDGET_SEC)),
            'memory_soft_limit_mb' => max(0, (int) (defined('ENV_CRON_MEMORY_SOFT_LIMIT_MB') ? ENV_CRON_MEMORY_SOFT_LIMIT_MB : self::DEFAULT_MEMORY_SOFT_LIMIT_MB)),
            'agent_memory_soft_limit_mb' => max(0, (int) (defined('ENV_CRON_AGENT_MEMORY_SOFT_LIMIT_MB') ? ENV_CRON_AGENT_MEMORY_SOFT_LIMIT_MB : self::DEFAULT_AGENT_MEMORY_SOFT_LIMIT_MB)),
        ];
    }

    public static function getHandlers(): array {
        return CronAgentRegistry::getHandlers();
    }

    public static function getSummary(): array {
        self::ensureInfrastructure();
        $total = (int) SafeMySQL::gi()->getOne('SELECT COUNT(*) FROM ?n', Constants::CRON_AGENTS_TABLE);
        $active = (int) SafeMySQL::gi()->getOne('SELECT COUNT(*) FROM ?n WHERE is_active = 1', Constants::CRON_AGENTS_TABLE);
        $locked = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM ?n WHERE locked_until IS NOT NULL AND locked_until > NOW()',
            Constants::CRON_AGENTS_TABLE
        );
        $due = (int) SafeMySQL::gi()->getOne(
            "SELECT COUNT(*) FROM ?n
             WHERE is_active = 1
               AND schedule_mode <> 'manual'
               AND next_run_at IS NOT NULL
               AND next_run_at <= NOW()
               AND (cooldown_until IS NULL OR cooldown_until <= NOW())
               AND (locked_until IS NULL OR locked_until <= NOW())",
            Constants::CRON_AGENTS_TABLE
        );
        $failed = (int) SafeMySQL::gi()->getOne(
            "SELECT COUNT(*) FROM ?n
             WHERE last_error_at IS NOT NULL
               AND (last_success_at IS NULL OR last_error_at >= last_success_at)",
            Constants::CRON_AGENTS_TABLE
        );
        $runsTotal = (int) SafeMySQL::gi()->getOne('SELECT COUNT(*) FROM ?n', Constants::CRON_AGENT_RUNS_TABLE);

        return [
            'total' => $total,
            'active' => $active,
            'due' => $due,
            'locked' => $locked,
            'failed' => $failed,
            'runs_total' => $runsTotal,
            'config' => self::getSchedulerConfig(),
            'scheduler_command' => 'php ' . ENV_SITE_PATH . 'app/cron/run.php',
            'one_off_import_command_template' => 'php ' . ENV_SITE_PATH . 'inc/cli.php cron:import <job_id>',
            'auto_created_agents' => self::getAutoCreatedAgentsSummary(),
            'last_tick_at' => self::getLastSchedulerTickAt(),
        ];
    }

    public static function getAgents(int $limit = 200): array {
        self::ensureInfrastructure();
        $limit = max(1, min($limit, 1000));
        $rows = SafeMySQL::gi()->getAll(
            'SELECT * FROM ?n ORDER BY priority ASC, agent_id ASC LIMIT ?i',
            Constants::CRON_AGENTS_TABLE,
            $limit
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = self::decorateAgentRow($row);
        }

        return $result;
    }

    public static function getAgent(int $agentId): ?array {
        self::ensureInfrastructure();
        if ($agentId <= 0) {
            return null;
        }

        $row = SafeMySQL::gi()->getRow(
            'SELECT * FROM ?n WHERE agent_id = ?i',
            Constants::CRON_AGENTS_TABLE,
            $agentId
        );

        return is_array($row) ? self::decorateAgentRow($row) : null;
    }

    public static function getAgentByCode(string $code): ?array {
        self::ensureInfrastructure();
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $row = SafeMySQL::gi()->getRow(
            'SELECT * FROM ?n WHERE code = ?s',
            Constants::CRON_AGENTS_TABLE,
            $code
        );

        return is_array($row) ? self::decorateAgentRow($row) : null;
    }

    public static function getAgentByIdOrCode(int|string $idOrCode): ?array {
        if (is_int($idOrCode) || (is_string($idOrCode) && ctype_digit($idOrCode))) {
            return self::getAgent((int) $idOrCode);
        }
        return self::getAgentByCode((string) $idOrCode);
    }

    public static function getRecentRuns(int $limit = 50, ?int $agentId = null): array {
        self::ensureInfrastructure();
        $limit = max(1, min($limit, 500));
        if ($agentId !== null && $agentId > 0) {
            return SafeMySQL::gi()->getAll(
                'SELECT * FROM ?n FORCE INDEX (idx_cron_agent_runs_agent_run) WHERE agent_id = ?i ORDER BY run_id DESC LIMIT ?i',
                Constants::CRON_AGENT_RUNS_TABLE,
                $agentId,
                $limit
            );
        }

        return SafeMySQL::gi()->getAll(
            'SELECT * FROM ?n ORDER BY run_id DESC LIMIT ?i',
            Constants::CRON_AGENT_RUNS_TABLE,
            $limit
        );
    }

    public static function saveAgent(array $agentData): OperationResult {
        self::ensureInfrastructure();
        $normalized = self::normalizeAgentData($agentData);
        if ($normalized instanceof OperationResult) {
            return $normalized;
        }

        $agentId = (int) ($normalized['agent_id'] ?? 0);
        unset($normalized['agent_id']);

        if ($agentId > 0) {
            $updated = SafeMySQL::gi()->query(
                'UPDATE ?n SET ?u WHERE agent_id = ?i',
                Constants::CRON_AGENTS_TABLE,
                $normalized,
                $agentId
            );
            if (!$updated) {
                return OperationResult::failure('Не удалось обновить cron-агент.', 'cron_agent_update_failed', ['agent_id' => $agentId]);
            }

            Logger::audit('cron_agents', 'Cron-агент обновлён', [
                'agent_id' => $agentId,
                'code' => $normalized['code'] ?? '',
                'handler' => $normalized['handler'] ?? '',
            ], [
                'initiator' => __METHOD__,
                'details' => 'Cron agent updated',
                'include_trace' => false,
            ]);

            return OperationResult::success(['agent_id' => $agentId], 'Cron-агент сохранён.', 'updated');
        }

        $inserted = SafeMySQL::gi()->query(
            'INSERT INTO ?n SET ?u',
            Constants::CRON_AGENTS_TABLE,
            $normalized
        );
        if (!$inserted) {
            return OperationResult::failure('Не удалось создать cron-агент.', 'cron_agent_insert_failed');
        }

        $newId = (int) SafeMySQL::gi()->insertId();
        Logger::audit('cron_agents', 'Cron-агент создан', [
            'agent_id' => $newId,
            'code' => $normalized['code'] ?? '',
            'handler' => $normalized['handler'] ?? '',
        ], [
            'initiator' => __METHOD__,
            'details' => 'Cron agent created',
            'include_trace' => false,
        ]);

        return OperationResult::success(['agent_id' => $newId], 'Cron-агент создан.', 'created');
    }

    public static function deleteAgent(int $agentId): OperationResult {
        self::ensureInfrastructure();
        $agent = self::getAgent($agentId);
        if (!$agent) {
            return OperationResult::failure('Cron-агент не найден.', 'cron_agent_not_found');
        }
        if (!empty($agent['is_locked'])) {
            return OperationResult::failure('Нельзя удалить выполняющийся cron-агент.', 'cron_agent_locked');
        }

        $deleted = SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE agent_id = ?i',
            Constants::CRON_AGENTS_TABLE,
            $agentId
        );
        if (!$deleted) {
            return OperationResult::failure('Не удалось удалить cron-агент.', 'cron_agent_delete_failed', ['agent_id' => $agentId]);
        }

        Logger::audit('cron_agents', 'Cron-агент удалён', [
            'agent_id' => $agentId,
            'code' => $agent['code'] ?? '',
        ], [
            'initiator' => __METHOD__,
            'details' => 'Cron agent deleted',
            'include_trace' => false,
        ]);

        return OperationResult::success(['agent_id' => $agentId], 'Cron-агент удалён.', 'deleted');
    }

    public static function toggleAgent(int $agentId): OperationResult {
        self::ensureInfrastructure();
        $agent = self::getAgent($agentId);
        if (!$agent) {
            return OperationResult::failure('Cron-агент не найден.', 'cron_agent_not_found');
        }

        $isActive = !empty($agent['is_active']) ? 0 : 1;
        $update = [
            'is_active' => $isActive,
        ];

        if ($isActive === 1 && (($agent['schedule_mode'] ?? 'interval') !== 'manual')) {
            $nextRunAt = trim((string) ($agent['next_run_at'] ?? ''));
            if ($nextRunAt === '' || strtotime($nextRunAt) <= time()) {
                $computed = self::calculateNextRunAt(array_merge($agent, ['is_active' => 1]), new \DateTimeImmutable('now'), true);
                $update['next_run_at'] = $computed;
            }
        }

        $saved = SafeMySQL::gi()->query(
            'UPDATE ?n SET ?u WHERE agent_id = ?i',
            Constants::CRON_AGENTS_TABLE,
            $update,
            $agentId
        );
        if (!$saved) {
            return OperationResult::failure('Не удалось изменить состояние cron-агента.', 'cron_agent_toggle_failed', ['agent_id' => $agentId]);
        }

        return OperationResult::success(
            ['agent_id' => $agentId, 'is_active' => $isActive],
            $isActive ? 'Cron-агент включён.' : 'Cron-агент отключён.',
            $isActive ? 'enabled' : 'disabled'
        );
    }

    public static function recoverStaleAgents(): OperationResult {
        self::ensureInfrastructure();

        $staleAgents = SafeMySQL::gi()->getAll(
            'SELECT agent_id, code, current_run_token FROM ?n WHERE locked_until IS NOT NULL AND locked_until < NOW()',
            Constants::CRON_AGENTS_TABLE
        );

        if (empty($staleAgents)) {
            return OperationResult::success(['recovered' => 0], 'Зависших cron-агентов не найдено.', 'noop');
        }

        $recoveredIds = [];
        foreach ($staleAgents as $agent) {
            $agentId = (int) ($agent['agent_id'] ?? 0);
            if ($agentId <= 0) {
                continue;
            }

            SafeMySQL::gi()->query(
                'UPDATE ?n SET locked_at = NULL, locked_until = NULL, locked_by = NULL, current_run_token = NULL, last_error_at = NOW(), last_error_message = ?s WHERE agent_id = ?i',
                Constants::CRON_AGENTS_TABLE,
                'Recovered from stale lock.',
                $agentId
            );

            SafeMySQL::gi()->query(
                "UPDATE ?n
                 SET status = 'failed', finished_at = NOW(), error_message = ?s
                 WHERE agent_id = ?i AND status = 'running'",
                Constants::CRON_AGENT_RUNS_TABLE,
                'Recovered from stale lock.',
                $agentId
            );

            $recoveredIds[] = $agentId;
        }

        Logger::warning('cron_agents', 'Восстановлены зависшие cron-агенты', [
            'agent_ids' => $recoveredIds,
        ], [
            'initiator' => __METHOD__,
            'details' => 'Stale cron agents recovered',
            'include_trace' => false,
        ]);

        return OperationResult::success(
            ['recovered' => count($recoveredIds), 'agent_ids' => $recoveredIds],
            'Зависшие cron-агенты восстановлены.',
            'recovered'
        );
    }

    public static function runAgentNow(int|string $idOrCode, string $triggerSource = 'manual'): OperationResult {
        self::ensureInfrastructure();
        $agent = self::getAgentByIdOrCode($idOrCode);
        if (!$agent) {
            return OperationResult::failure('Cron-агент не найден.', 'cron_agent_not_found');
        }

        $reservation = self::reserveAgentForRun($agent, $triggerSource, true);
        if (!$reservation['success']) {
            return OperationResult::failure(
                (string) ($reservation['message'] ?? 'Не удалось зарезервировать запуск cron-агента.'),
                (string) ($reservation['code'] ?? 'cron_agent_reserve_failed'),
                $reservation
            );
        }

        $execution = self::executeReservedAgent(
            $reservation['agent'],
            (int) $reservation['run_id'],
            (string) $reservation['run_token'],
            $triggerSource
        );

        if (!empty($execution['success'])) {
            return OperationResult::success($execution, (string) ($execution['message'] ?? 'Cron-агент выполнен.'), 'executed');
        }

        return OperationResult::failure(
            (string) ($execution['message'] ?? 'Cron-агент завершился с ошибкой.'),
            (string) ($execution['code'] ?? 'cron_agent_execution_failed'),
            $execution
        );
    }

    public static function runDueAgents(string $triggerSource = 'scheduler'): OperationResult {
        self::ensureInfrastructure();
        $config = self::getSchedulerConfig();
        $schedulerLock = self::acquireLock(self::getSchedulerLockName(), (int) $config['global_lock_timeout']);
        if (!$schedulerLock) {
            return OperationResult::failure('Scheduler уже выполняется в другом процессе.', 'cron_scheduler_locked');
        }

        $summary = [
            'selected' => 0,
            'executed' => 0,
            'success' => 0,
            'failed' => 0,
            'skipped_busy' => 0,
            'skipped_locked' => 0,
            'skipped_invalid' => 0,
            'recovered_stale' => 0,
            'max_agents_per_tick' => (int) $config['max_agents_per_tick'],
            'max_weight_per_tick' => (int) $config['max_weight_per_tick'],
            'consumed_weight' => 0,
            'tick_time_budget_sec' => (int) $config['tick_time_budget_sec'],
            'memory_soft_limit_mb' => (int) $config['memory_soft_limit_mb'],
            'agent_memory_soft_limit_mb' => (int) $config['agent_memory_soft_limit_mb'],
            'stopped_by_guard' => false,
            'stop_reason' => '',
            'runs' => [],
        ];
        $tickStartedAt = microtime(true);

        try {
            $recovered = self::recoverStaleAgents();
            if ($recovered->isSuccess()) {
                $summary['recovered_stale'] = (int) (($recovered->getData()['recovered'] ?? 0));
            }

            $candidates = self::getDueAgents((int) $config['due_fetch_limit']);
            $summary['selected'] = count($candidates);

            foreach ($candidates as $agent) {
                if (self::shouldStopSchedulerTick($config, $tickStartedAt, $stopReason)) {
                    $summary['stopped_by_guard'] = true;
                    $summary['stop_reason'] = $stopReason;
                    break;
                }

                if ($summary['executed'] >= (int) $config['max_agents_per_tick']) {
                    break;
                }

                $agentWeight = max(1, (int) ($agent['weight'] ?? 1));
                if (($summary['consumed_weight'] + $agentWeight) > (int) $config['max_weight_per_tick']) {
                    $summary['skipped_busy']++;
                    continue;
                }

                $reservation = self::reserveAgentForRun($agent, $triggerSource, false);
                if (!$reservation['success']) {
                    $code = (string) ($reservation['code'] ?? '');
                    if (in_array($code, ['cron_agent_busy', 'cron_agent_capacity_reached'], true)) {
                        $summary['skipped_busy']++;
                    } elseif ($code === 'cron_agent_locked') {
                        $summary['skipped_locked']++;
                    } else {
                        $summary['skipped_invalid']++;
                    }
                    continue;
                }

                $execution = self::executeReservedAgent(
                    $reservation['agent'],
                    (int) $reservation['run_id'],
                    (string) $reservation['run_token'],
                    $triggerSource
                );

                $summary['executed']++;
                $summary['consumed_weight'] += $agentWeight;
                if (!empty($execution['success'])) {
                    $summary['success']++;
                } else {
                    $summary['failed']++;
                }
                $summary['runs'][] = $execution;

                if (self::shouldStopSchedulerTick($config, $tickStartedAt, $stopReason)) {
                    $summary['stopped_by_guard'] = true;
                    $summary['stop_reason'] = $stopReason;
                    break;
                }
            }

            $summary['pruned_run_history'] = self::pruneRunHistory();
        } finally {
            self::releaseLock(self::getSchedulerLockName());
        }

        $summary['memory_usage_mb'] = round(memory_get_usage(true) / 1048576, 2);
        $summary['peak_memory_mb'] = round(memory_get_peak_usage(true) / 1048576, 2);
        $summary['tick_duration_ms'] = (int) round((microtime(true) - $tickStartedAt) * 1000);
        self::storeSchedulerHeartbeat();

        Logger::info('cron_agents', 'Выполнен минутный проход scheduler-а cron-агентов', $summary, [
            'initiator' => __METHOD__,
            'details' => 'Cron scheduler tick completed',
            'include_trace' => false,
        ]);

        return OperationResult::success($summary, 'Проход scheduler-а cron-агентов выполнен.', 'tick_completed');
    }

    public static function getLastSchedulerTickAt(): string {
        return (string) (SysClass::getOption(self::SCHEDULER_LAST_TICK_OPTION) ?? '');
    }

    private static function storeSchedulerHeartbeat(): void {
        SysClass::setOption(self::SCHEDULER_LAST_TICK_OPTION, date('Y-m-d H:i:s'));
    }

    private static function getDueAgents(int $limit): array {
        $limit = max(1, min($limit, 500));
        $rows = SafeMySQL::gi()->getAll(
            "SELECT * FROM ?n
             WHERE is_active = 1
               AND schedule_mode <> 'manual'
               AND next_run_at IS NOT NULL
               AND next_run_at <= NOW()
               AND (cooldown_until IS NULL OR cooldown_until <= NOW())
             ORDER BY priority ASC, next_run_at ASC, agent_id ASC
             LIMIT ?i",
            Constants::CRON_AGENTS_TABLE,
            $limit
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = self::decorateAgentRow($row);
        }
        return $result;
    }

    private static function reserveAgentForRun(array $agent, string $triggerSource, bool $ignoreSchedule): array {
        $agentId = (int) ($agent['agent_id'] ?? 0);
        if ($agentId <= 0) {
            return ['success' => false, 'code' => 'cron_agent_not_found', 'message' => 'Cron-агент не найден.'];
        }

        $config = self::getSchedulerConfig();
        $dispatchLock = self::acquireLock(self::getDispatchLockName(), (int) $config['dispatch_lock_timeout']);
        if (!$dispatchLock) {
            return ['success' => false, 'code' => 'cron_dispatch_locked', 'message' => 'Не удалось получить dispatch-lock scheduler-а.'];
        }

        try {
            $freshAgent = self::getAgent($agentId);
            if (!$freshAgent) {
                return ['success' => false, 'code' => 'cron_agent_not_found', 'message' => 'Cron-агент не найден.'];
            }

            if (!$ignoreSchedule) {
                if (empty($freshAgent['is_active'])) {
                    return ['success' => false, 'code' => 'cron_agent_disabled', 'message' => 'Cron-агент отключён.'];
                }
                if (($freshAgent['schedule_mode'] ?? 'interval') === 'manual') {
                    return ['success' => false, 'code' => 'cron_agent_manual_only', 'message' => 'Cron-агент настроен только на ручной запуск.'];
                }
                if (!self::isAgentDue($freshAgent)) {
                    return ['success' => false, 'code' => 'cron_agent_not_due', 'message' => 'Cron-агент ещё не готов к запуску.'];
                }
            }

            if (!empty($freshAgent['is_locked'])) {
                return ['success' => false, 'code' => 'cron_agent_locked', 'message' => 'Cron-агент уже выполняется.'];
            }

            $capacity = self::checkCapacity($freshAgent);
            if (!$capacity['allowed']) {
                return ['success' => false, 'code' => $capacity['code'], 'message' => $capacity['message']];
            }

            $runToken = bin2hex(random_bytes(16));
            $workerId = self::buildWorkerId();
            $lockTtl = max((int) ($freshAgent['lock_ttl_sec'] ?? 360), (int) ($freshAgent['max_runtime_sec'] ?? 300) + self::DEFAULT_STALE_GRACE_SEC);

            SafeMySQL::gi()->query(
                "UPDATE ?n
                 SET locked_at = NOW(),
                     locked_until = DATE_ADD(NOW(), INTERVAL ?i SECOND),
                     locked_by = ?s,
                     current_run_token = ?s
                 WHERE agent_id = ?i
                   AND (locked_until IS NULL OR locked_until <= NOW())",
                Constants::CRON_AGENTS_TABLE,
                $lockTtl,
                $workerId,
                $runToken,
                $agentId
            );

            if ((int) SafeMySQL::gi()->affectedRows() <= 0) {
                return ['success' => false, 'code' => 'cron_agent_locked', 'message' => 'Cron-агент уже зарезервирован другим процессом.'];
            }

            SafeMySQL::gi()->query(
                'INSERT INTO ?n SET ?u',
                Constants::CRON_AGENT_RUNS_TABLE,
                [
                    'agent_id' => $agentId,
                    'agent_code' => (string) ($freshAgent['code'] ?? ''),
                    'handler' => (string) ($freshAgent['handler'] ?? ''),
                    'run_token' => $runToken,
                    'trigger_source' => $triggerSource,
                    'status' => 'running',
                    'worker_id' => $workerId,
                    'started_at' => date('Y-m-d H:i:s'),
                    'context_json' => json_encode([
                        'request_id' => Logger::getRequestId(),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]
            );

            $runId = (int) SafeMySQL::gi()->insertId();
            $freshAgent['locked_at'] = date('Y-m-d H:i:s');
            $freshAgent['locked_until'] = date('Y-m-d H:i:s', time() + $lockTtl);
            $freshAgent['locked_by'] = $workerId;
            $freshAgent['current_run_token'] = $runToken;
            $freshAgent['is_locked'] = true;

            return [
                'success' => true,
                'agent' => $freshAgent,
                'run_id' => $runId,
                'run_token' => $runToken,
                'worker_id' => $workerId,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'code' => 'cron_agent_reserve_exception', 'message' => $e->getMessage()];
        } finally {
            self::releaseLock(self::getDispatchLockName());
        }
    }

    private static function executeReservedAgent(array $agent, int $runId, string $runToken, string $triggerSource): array {
        $agentId = (int) ($agent['agent_id'] ?? 0);
        $handler = (string) ($agent['handler'] ?? '');
        $payload = self::decodeJsonPayload($agent['payload_json'] ?? '');
        $startedAt = microtime(true);
        $capturedOutput = '';

        try {
            ob_start();
            $handlerResult = CronAgentRegistry::runHandler($handler, $payload, [
                'agent' => $agent,
                'run_id' => $runId,
                'run_token' => $runToken,
                'trigger_source' => $triggerSource,
            ]);
            $capturedOutput = trim((string) ob_get_clean());
        } catch (\Throwable $e) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            $handlerResult = [
                'success' => false,
                'status' => 'failed',
                'message' => $e->getMessage(),
                'data' => [
                    'exception' => $e->getMessage(),
                ],
            ];
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $success = !empty($handlerResult['success']);
        $message = trim((string) ($handlerResult['message'] ?? ''));
        $status = (string) ($handlerResult['status'] ?? ($success ? 'success' : 'failed'));
        $data = is_array($handlerResult['data'] ?? null) ? $handlerResult['data'] : [];
        $outputParts = [];
        $explicitOutput = trim((string) ($handlerResult['output'] ?? ''));
        if ($explicitOutput !== '') {
            $outputParts[] = $explicitOutput;
        }
        if ($capturedOutput !== '') {
            $outputParts[] = $capturedOutput;
        }
        $output = trim(implode("\n", $outputParts));

        if ($success) {
            $nextRunAt = self::calculateNextRunAt($agent, new \DateTimeImmutable('now'), true);
            SafeMySQL::gi()->query(
                'UPDATE ?n SET ?u WHERE agent_id = ?i',
                Constants::CRON_AGENTS_TABLE,
                [
                    'last_run_at' => date('Y-m-d H:i:s'),
                    'last_success_at' => date('Y-m-d H:i:s'),
                    'last_error_at' => null,
                    'last_error_message' => null,
                    'last_duration_ms' => $durationMs,
                    'run_count' => (int) ($agent['run_count'] ?? 0) + 1,
                    'next_run_at' => $nextRunAt,
                    'cooldown_until' => null,
                    'locked_at' => null,
                    'locked_until' => null,
                    'locked_by' => null,
                    'current_run_token' => null,
                ],
                $agentId
            );
            SafeMySQL::gi()->query(
                "UPDATE ?n SET status = 'success', finished_at = NOW(), duration_ms = ?i, output_text = ?s, context_json = ?s WHERE run_id = ?i",
                Constants::CRON_AGENT_RUNS_TABLE,
                $durationMs,
                $output,
                json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $runId
            );

            Logger::info('cron_agents', 'Cron-агент выполнен успешно', [
                'agent_id' => $agentId,
                'code' => $agent['code'] ?? '',
                'handler' => $handler,
                'run_id' => $runId,
                'duration_ms' => $durationMs,
                'status' => $status,
            ], [
                'initiator' => __METHOD__,
                'details' => $message !== '' ? $message : 'Cron agent succeeded',
                'include_trace' => false,
            ]);

            return [
                'success' => true,
                'code' => 'success',
                'message' => $message !== '' ? $message : 'Cron-агент выполнен успешно.',
                'agent_id' => $agentId,
                'run_id' => $runId,
                'duration_ms' => $durationMs,
                'status' => $status,
                'output' => $output,
                'data' => $data,
            ];
        }

        $retryAt = self::calculateRetryRunAt($agent);
        SafeMySQL::gi()->query(
            'UPDATE ?n SET ?u WHERE agent_id = ?i',
            Constants::CRON_AGENTS_TABLE,
            [
                'last_run_at' => date('Y-m-d H:i:s'),
                'last_error_at' => date('Y-m-d H:i:s'),
                'last_error_message' => $message !== '' ? $message : 'Cron agent execution failed.',
                'last_duration_ms' => $durationMs,
                'run_count' => (int) ($agent['run_count'] ?? 0) + 1,
                'fail_count' => (int) ($agent['fail_count'] ?? 0) + 1,
                'next_run_at' => $retryAt,
                'cooldown_until' => $retryAt,
                'locked_at' => null,
                'locked_until' => null,
                'locked_by' => null,
                'current_run_token' => null,
            ],
            $agentId
        );
        SafeMySQL::gi()->query(
            "UPDATE ?n
             SET status = 'failed',
                 finished_at = NOW(),
                 duration_ms = ?i,
                 output_text = ?s,
                 error_message = ?s,
                 context_json = ?s
             WHERE run_id = ?i",
            Constants::CRON_AGENT_RUNS_TABLE,
            $durationMs,
            $output,
            $message !== '' ? $message : 'Cron agent execution failed.',
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $runId
        );

        Logger::error('cron_agents', 'Cron-агент завершился с ошибкой', [
            'agent_id' => $agentId,
            'code' => $agent['code'] ?? '',
            'handler' => $handler,
            'run_id' => $runId,
            'duration_ms' => $durationMs,
            'status' => $status,
            'message' => $message,
        ], [
            'initiator' => __METHOD__,
            'details' => $message !== '' ? $message : 'Cron agent failed',
            'include_trace' => false,
        ]);

        return [
            'success' => false,
            'code' => 'failed',
            'message' => $message !== '' ? $message : 'Cron-агент завершился с ошибкой.',
            'agent_id' => $agentId,
            'run_id' => $runId,
            'duration_ms' => $durationMs,
            'status' => $status,
            'output' => $output,
            'data' => $data,
        ];
    }

    private static function checkCapacity(array $agent): array {
        $config = self::getSchedulerConfig();
        $running = SafeMySQL::gi()->getRow(
            "SELECT COUNT(*) AS total_running, COALESCE(SUM(weight), 0) AS total_weight
             FROM ?n
             WHERE locked_until IS NOT NULL AND locked_until > NOW()",
            Constants::CRON_AGENTS_TABLE
        ) ?: ['total_running' => 0, 'total_weight' => 0];

        $currentRunning = (int) ($running['total_running'] ?? 0);
        $currentWeight = (int) ($running['total_weight'] ?? 0);
        $agentWeight = max(1, (int) ($agent['weight'] ?? 1));

        if ($currentRunning >= (int) $config['max_concurrent']) {
            return [
                'allowed' => false,
                'code' => 'cron_agent_capacity_reached',
                'message' => 'Лимит одновременно выполняемых cron-задач достигнут.',
            ];
        }

        if (($currentWeight + $agentWeight) > (int) $config['max_weight_per_tick']) {
            return [
                'allowed' => false,
                'code' => 'cron_agent_busy',
                'message' => 'Текущая суммарная нагрузка cron-задач превышает лимит.',
            ];
        }

        return [
            'allowed' => true,
            'code' => 'ok',
            'message' => '',
        ];
    }

    private static function shouldStopSchedulerTick(array $config, float $tickStartedAt, ?string &$reason = null): bool {
        $timeBudgetSec = max(5, (int) ($config['tick_time_budget_sec'] ?? self::DEFAULT_TICK_TIME_BUDGET_SEC));
        if ((microtime(true) - $tickStartedAt) >= $timeBudgetSec) {
            $reason = 'tick_time_budget_exceeded';
            return true;
        }

        if (function_exists('ee_runtime_memory_guard_exceeded') && ee_runtime_memory_guard_exceeded('cron_scheduler', 8 * 1024 * 1024)) {
            $reason = 'scheduler_memory_guard_exceeded';
            return true;
        }

        return false;
    }

    private static function normalizeAgentData(array $agentData): array|OperationResult {
        $current = null;
        $agentId = (int) ($agentData['agent_id'] ?? 0);
        if ($agentId > 0) {
            $current = self::getAgent($agentId);
            if (!$current) {
                return OperationResult::failure('Cron-агент не найден.', 'cron_agent_not_found');
            }
        }

        $code = strtolower(trim((string) ($agentData['code'] ?? ($current['code'] ?? ''))));
        if ($code === '' || !preg_match('~^[a-z0-9][a-z0-9._:-]{1,99}$~', $code)) {
            return OperationResult::validation('Укажите корректный код cron-агента латиницей.', ['field' => 'code']);
        }

        $existing = self::getAgentByCode($code);
        if ($existing && (int) ($existing['agent_id'] ?? 0) !== $agentId) {
            return OperationResult::failure('Cron-агент с таким кодом уже существует.', 'cron_agent_duplicate_code', ['field' => 'code']);
        }

        $handler = trim((string) ($agentData['handler'] ?? ($current['handler'] ?? '')));
        $handlerMeta = CronAgentRegistry::getHandlerMeta($handler);
        if (!$handlerMeta) {
            return OperationResult::validation('Выбран неизвестный handler cron-агента.', ['field' => 'handler']);
        }

        $scheduleMode = strtolower(trim((string) ($agentData['schedule_mode'] ?? ($current['schedule_mode'] ?? 'interval'))));
        if (!in_array($scheduleMode, ['interval', 'cron', 'manual'], true)) {
            return OperationResult::validation('Укажите корректный режим расписания.', ['field' => 'schedule_mode']);
        }

        $intervalMinutes = max(1, (int) ($agentData['interval_minutes'] ?? ($current['interval_minutes'] ?? 1)));
        if ($scheduleMode === 'interval' && $intervalMinutes <= 0) {
            return OperationResult::validation('Интервал cron-агента должен быть не меньше одной минуты.', ['field' => 'interval_minutes']);
        }

        $cronExpression = trim((string) ($agentData['cron_expression'] ?? ($current['cron_expression'] ?? '')));
        if ($scheduleMode === 'cron') {
            if ($cronExpression === '' || !self::isValidCronExpression($cronExpression)) {
                return OperationResult::validation('Укажите корректное cron-выражение из 5 полей.', ['field' => 'cron_expression']);
            }
        } else {
            $cronExpression = '';
        }

        $payloadJsonRaw = trim((string) ($agentData['payload_json'] ?? ($current['payload_json'] ?? '')));
        $payload = self::decodeJsonPayload($payloadJsonRaw);
        if ($payloadJsonRaw !== '' && $payload === [] && trim($payloadJsonRaw) !== '[]' && trim($payloadJsonRaw) !== '{}') {
            return OperationResult::validation('Payload JSON содержит ошибки.', ['field' => 'payload_json']);
        }

        foreach ((array) ($handlerMeta['required_payload_keys'] ?? []) as $requiredKey) {
            if (!array_key_exists($requiredKey, $payload) || $payload[$requiredKey] === '' || $payload[$requiredKey] === null) {
                return OperationResult::validation('Для выбранного handler требуется payload.' . $requiredKey, ['field' => 'payload_json']);
            }
        }

        $isActive = !empty($agentData['is_active']) ? 1 : 0;
        $priority = max(1, min(999, (int) ($agentData['priority'] ?? ($current['priority'] ?? 100))));
        $weight = max(1, min(100, (int) ($agentData['weight'] ?? ($current['weight'] ?? 1))));
        $maxRuntimeSec = max(10, min(86400, (int) ($agentData['max_runtime_sec'] ?? ($current['max_runtime_sec'] ?? 300))));
        $lockTtlSec = max($maxRuntimeSec + self::DEFAULT_STALE_GRACE_SEC, min(172800, max(30, (int) ($agentData['lock_ttl_sec'] ?? ($current['lock_ttl_sec'] ?? 360)))));
        $retryDelaySec = max(30, min(86400, (int) ($agentData['retry_delay_sec'] ?? ($current['retry_delay_sec'] ?? 300))));
        $title = trim((string) ($agentData['title'] ?? ($current['title'] ?? '')));
        $description = trim((string) ($agentData['description'] ?? ($current['description'] ?? '')));
        $nextRunAt = self::normalizeDateTimeString((string) ($agentData['next_run_at'] ?? ($current['next_run_at'] ?? '')));

        if ($scheduleMode === 'manual') {
            $nextRunAt = null;
        } elseif ($isActive && !$nextRunAt) {
            $nextRunAt = self::calculateNextRunAt([
                'schedule_mode' => $scheduleMode,
                'interval_minutes' => $intervalMinutes,
                'cron_expression' => $cronExpression,
            ], new \DateTimeImmutable('now'), true);
        }

        return [
            'agent_id' => $agentId,
            'code' => $code,
            'title' => $title,
            'description' => $description,
            'handler' => $handler,
            'schedule_mode' => $scheduleMode,
            'interval_minutes' => $scheduleMode === 'interval' ? $intervalMinutes : null,
            'cron_expression' => $scheduleMode === 'cron' ? $cronExpression : null,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active' => $isActive,
            'priority' => $priority,
            'weight' => $weight,
            'max_runtime_sec' => $maxRuntimeSec,
            'lock_ttl_sec' => $lockTtlSec,
            'retry_delay_sec' => $retryDelaySec,
            'next_run_at' => $nextRunAt,
        ];
    }

    private static function calculateRetryRunAt(array $agent): ?string {
        if (($agent['schedule_mode'] ?? 'interval') === 'manual') {
            return null;
        }
        $retryDelay = max(30, (int) ($agent['retry_delay_sec'] ?? 300));
        return date('Y-m-d H:i:s', time() + $retryDelay);
    }

    public static function calculateNextRunAt(array $agent, ?\DateTimeImmutable $from = null, bool $afterCurrentMoment = false): ?string {
        $from ??= new \DateTimeImmutable('now');
        $scheduleMode = strtolower(trim((string) ($agent['schedule_mode'] ?? 'interval')));

        return match ($scheduleMode) {
            'manual' => null,
            'cron' => self::findNextCronRun((string) ($agent['cron_expression'] ?? ''), $from),
            default => self::calculateIntervalRun((int) ($agent['interval_minutes'] ?? 1), $from, $afterCurrentMoment),
        };
    }

    private static function calculateIntervalRun(int $intervalMinutes, \DateTimeImmutable $from, bool $afterCurrentMoment): string {
        $intervalMinutes = max(1, $intervalMinutes);
        $base = $afterCurrentMoment ? $from : $from->modify('-' . $intervalMinutes . ' minutes');
        return $base->modify('+' . $intervalMinutes . ' minutes')->format('Y-m-d H:i:s');
    }

    private static function isAgentDue(array $agent): bool {
        if (empty($agent['is_active'])) {
            return false;
        }
        if (($agent['schedule_mode'] ?? 'interval') === 'manual') {
            return false;
        }
        if (!empty($agent['is_locked'])) {
            return false;
        }
        $cooldownUntil = trim((string) ($agent['cooldown_until'] ?? ''));
        if ($cooldownUntil !== '' && strtotime($cooldownUntil) > time()) {
            return false;
        }
        $nextRunAt = trim((string) ($agent['next_run_at'] ?? ''));
        return $nextRunAt !== '' && strtotime($nextRunAt) <= time();
    }

    private static function decorateAgentRow(array $row): array {
        $row['payload'] = self::decodeJsonPayload($row['payload_json'] ?? '');
        $row['is_locked'] = !empty($row['locked_until']) && strtotime((string) $row['locked_until']) > time();
        $row['runtime_status'] = self::resolveRuntimeStatus($row);
        return $row;
    }

    private static function resolveRuntimeStatus(array $agent): string {
        if (!empty($agent['is_locked']) || (!empty($agent['locked_until']) && strtotime((string) $agent['locked_until']) > time())) {
            return 'running';
        }
        if (empty($agent['is_active'])) {
            return 'disabled';
        }
        $cooldownUntil = trim((string) ($agent['cooldown_until'] ?? ''));
        if ($cooldownUntil !== '' && strtotime($cooldownUntil) > time()) {
            return 'cooldown';
        }
        $nextRunAt = trim((string) ($agent['next_run_at'] ?? ''));
        if ($nextRunAt !== '' && strtotime($nextRunAt) <= time() && ($agent['schedule_mode'] ?? 'interval') !== 'manual') {
            return 'due';
        }
        if (!empty($agent['last_error_at']) && (empty($agent['last_success_at']) || strtotime((string) $agent['last_error_at']) >= strtotime((string) $agent['last_success_at']))) {
            return 'failed';
        }
        return 'idle';
    }

    private static function decodeJsonPayload(mixed $payload): array {
        if (is_array($payload)) {
            return $payload;
        }
        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function normalizeDateTimeString(string $value): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = str_replace('T', ' ', $value);
        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
        return $date->format('Y-m-d H:i:s');
    }

    private static function isValidCronExpression(string $expression): bool {
        return self::findNextCronRun($expression, new \DateTimeImmutable('now')) !== null;
    }

    private static function findNextCronRun(string $expression, \DateTimeImmutable $from): ?string {
        $fields = preg_split('/\s+/', trim($expression));
        if (!is_array($fields) || count($fields) !== 5) {
            return null;
        }

        [$minutes, $hours, $days, $months, $weekdays] = $fields;
        $cursor = $from->setTime((int) $from->format('H'), (int) $from->format('i'), 0)->modify('+1 minute');

        for ($i = 0; $i < 525600; $i++) {
            if (
                self::cronFieldMatches($minutes, (int) $cursor->format('i'), 0, 59)
                && self::cronFieldMatches($hours, (int) $cursor->format('G'), 0, 23)
                && self::cronFieldMatches($days, (int) $cursor->format('j'), 1, 31)
                && self::cronFieldMatches($months, (int) $cursor->format('n'), 1, 12)
                && self::cronFieldMatches($weekdays, (int) $cursor->format('w'), 0, 6)
            ) {
                return $cursor->format('Y-m-d H:i:s');
            }
            $cursor = $cursor->modify('+1 minute');
        }

        return null;
    }

    private static function cronFieldMatches(string $field, int $value, int $min, int $max): bool {
        $field = trim($field);
        if ($field === '*') {
            return true;
        }

        $parts = explode(',', $field);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (str_contains($part, '/')) {
                [$base, $stepRaw] = explode('/', $part, 2);
                $step = (int) $stepRaw;
                if ($step <= 0) {
                    return false;
                }

                if ($base === '*' || $base === '') {
                    if (($value - $min) % $step === 0) {
                        return true;
                    }
                    continue;
                }

                if (str_contains($base, '-')) {
                    [$rangeStart, $rangeEnd] = explode('-', $base, 2);
                    $rangeStart = (int) $rangeStart;
                    $rangeEnd = (int) $rangeEnd;
                    if ($value >= $rangeStart && $value <= $rangeEnd && (($value - $rangeStart) % $step === 0)) {
                        return true;
                    }
                    continue;
                }

                $baseValue = (int) $base;
                if ($baseValue === $value) {
                    return true;
                }
                continue;
            }

            if (str_contains($part, '-')) {
                [$rangeStart, $rangeEnd] = explode('-', $part, 2);
                $rangeStart = (int) $rangeStart;
                $rangeEnd = (int) $rangeEnd;
                if ($rangeStart <= $value && $value <= $rangeEnd) {
                    return true;
                }
                continue;
            }

            if ((int) $part === $value && (int) $part >= $min && (int) $part <= $max) {
                return true;
            }
        }

        return false;
    }

    private static function tableExists(string $tableName): bool {
        return (bool) SafeMySQL::gi()->getOne('SHOW TABLES LIKE ?s', $tableName);
    }

    private static function createCronAgentsTable(): void {
        SafeMySQL::gi()->query(
            "CREATE TABLE IF NOT EXISTS ?n (
                agent_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(100) NOT NULL,
                title VARCHAR(255) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                handler VARCHAR(100) NOT NULL,
                schedule_mode VARCHAR(16) NOT NULL DEFAULT 'interval',
                interval_minutes INT UNSIGNED DEFAULT NULL,
                cron_expression VARCHAR(64) DEFAULT NULL,
                payload_json LONGTEXT DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                priority SMALLINT NOT NULL DEFAULT 100,
                weight SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                max_runtime_sec INT UNSIGNED NOT NULL DEFAULT 300,
                lock_ttl_sec INT UNSIGNED NOT NULL DEFAULT 360,
                retry_delay_sec INT UNSIGNED NOT NULL DEFAULT 300,
                cooldown_until DATETIME DEFAULT NULL,
                next_run_at DATETIME DEFAULT NULL,
                last_run_at DATETIME DEFAULT NULL,
                last_success_at DATETIME DEFAULT NULL,
                last_error_at DATETIME DEFAULT NULL,
                last_error_message TEXT DEFAULT NULL,
                last_duration_ms INT UNSIGNED DEFAULT NULL,
                run_count INT UNSIGNED NOT NULL DEFAULT 0,
                fail_count INT UNSIGNED NOT NULL DEFAULT 0,
                locked_at DATETIME DEFAULT NULL,
                locked_until DATETIME DEFAULT NULL,
                locked_by VARCHAR(128) DEFAULT NULL,
                current_run_token VARCHAR(64) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (agent_id),
                UNIQUE KEY uq_cron_agents_code (code),
                KEY idx_cron_agents_due (is_active, next_run_at, priority),
                KEY idx_cron_agents_handler (handler),
                KEY idx_cron_agents_locked (locked_until),
                KEY idx_cron_agents_cooldown (cooldown_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Агенты минутного планировщика'",
            Constants::CRON_AGENTS_TABLE
        );
    }

    private static function createCronAgentRunsTable(): void {
        SafeMySQL::gi()->query(
            "CREATE TABLE IF NOT EXISTS ?n (
                run_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                agent_id INT UNSIGNED NOT NULL,
                agent_code VARCHAR(100) NOT NULL,
                handler VARCHAR(100) NOT NULL,
                run_token VARCHAR(64) NOT NULL,
                trigger_source VARCHAR(16) NOT NULL DEFAULT 'scheduler',
                status VARCHAR(16) NOT NULL DEFAULT 'running',
                worker_id VARCHAR(128) DEFAULT NULL,
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                finished_at DATETIME DEFAULT NULL,
                duration_ms INT UNSIGNED DEFAULT NULL,
                output_text MEDIUMTEXT DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                context_json LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (run_id),
                KEY idx_cron_agent_runs_agent_started (agent_id, started_at),
                KEY idx_cron_agent_runs_agent_run (agent_id, run_id),
                KEY idx_cron_agent_runs_status_started (status, started_at),
                KEY idx_cron_agent_runs_token (run_token),
                CONSTRAINT fk_cron_agent_runs_agent FOREIGN KEY (agent_id) REFERENCES ?n(agent_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='История запусков cron-агентов'",
            Constants::CRON_AGENT_RUNS_TABLE,
            Constants::CRON_AGENTS_TABLE
        );
        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD INDEX IF NOT EXISTS idx_cron_agent_runs_agent_run (agent_id, run_id)',
            Constants::CRON_AGENT_RUNS_TABLE
        );
    }

    private static function pruneRunHistory(): array {
        $config = self::getSchedulerConfig();
        $deleted = 0;
        $deletedByRetention = 0;
        $deletedByMaxRows = 0;

        $retentionDays = max(1, (int) ($config['run_history_retention_days'] ?? self::DEFAULT_RUN_HISTORY_RETENTION_DAYS));
        $maxRows = max(100, (int) ($config['run_history_max_rows'] ?? self::DEFAULT_RUN_HISTORY_MAX_ROWS));

        SafeMySQL::gi()->query(
            "DELETE FROM ?n
             WHERE status <> 'running'
               AND started_at < DATE_SUB(NOW(), INTERVAL ?i DAY)",
            Constants::CRON_AGENT_RUNS_TABLE,
            $retentionDays
        );
        $deletedByRetention = max(0, (int) SafeMySQL::gi()->affectedRows());
        $deleted += $deletedByRetention;

        $totalRows = (int) SafeMySQL::gi()->getOne('SELECT COUNT(*) FROM ?n', Constants::CRON_AGENT_RUNS_TABLE);
        if ($totalRows > $maxRows) {
            $thresholdRunId = (int) SafeMySQL::gi()->getOne(
                "SELECT run_id FROM ?n
                 WHERE status <> 'running'
                 ORDER BY run_id DESC
                 LIMIT ?i, 1",
                Constants::CRON_AGENT_RUNS_TABLE,
                $maxRows - 1
            );

            if ($thresholdRunId > 0) {
                SafeMySQL::gi()->query(
                    "DELETE FROM ?n
                     WHERE status <> 'running'
                       AND run_id < ?i",
                    Constants::CRON_AGENT_RUNS_TABLE,
                    $thresholdRunId
                );
                $deletedByMaxRows = max(0, (int) SafeMySQL::gi()->affectedRows());
                $deleted += $deletedByMaxRows;
            }
        }

        return [
            'deleted' => $deleted,
            'deleted_by_retention' => $deletedByRetention,
            'deleted_by_max_rows' => $deletedByMaxRows,
            'retention_days' => $retentionDays,
            'max_rows' => $maxRows,
        ];
    }

    private static function seedDefaultAgents(): void {
        $handlersMeta = CronAgentRegistry::getHandlers();
        $defaults = CronAgentRegistry::getAutoCreatedDefaultAgents();
        foreach ($defaults as $handler => $definition) {
            $code = trim((string) ($definition['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $title = strtoupper((string) ENV_DEF_LANG) === 'RU'
                ? (string) ($definition['title_ru'] ?? $code)
                : (string) ($definition['title_en'] ?? $code);
            $description = strtoupper((string) ENV_DEF_LANG) === 'RU'
                ? (string) ($definition['description_ru'] ?? '')
                : (string) ($definition['description_en'] ?? '');
            $scheduleMode = (string) ($definition['schedule_mode'] ?? 'interval');
            $intervalMinutes = (int) ($definition['interval_minutes'] ?? 1);
            $cronExpression = (string) ($definition['cron_expression'] ?? '');
            $isActive = (int) ($definition['is_active'] ?? 0);
            $payloadExample = $handlersMeta[$handler]['payload_example'] ?? [];
            $nextRunAt = $isActive
                ? self::calculateNextRunAt([
                    'schedule_mode' => $scheduleMode,
                    'interval_minutes' => $intervalMinutes,
                    'cron_expression' => $cronExpression,
                ], new \DateTimeImmutable('now'), true)
                : null;

            SafeMySQL::gi()->query(
                "INSERT INTO ?n SET ?u
                 ON DUPLICATE KEY UPDATE code = code",
                Constants::CRON_AGENTS_TABLE,
                [
                    'code' => $code,
                    'title' => $title,
                    'description' => $description,
                    'handler' => $handler,
                    'schedule_mode' => $scheduleMode,
                    'interval_minutes' => $scheduleMode === 'interval' ? $intervalMinutes : null,
                    'cron_expression' => $scheduleMode === 'cron' ? $cronExpression : null,
                    'payload_json' => json_encode($payloadExample, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'is_active' => $isActive,
                    'priority' => (int) ($definition['priority'] ?? 100),
                    'weight' => (int) ($definition['weight'] ?? 1),
                    'max_runtime_sec' => (int) ($definition['max_runtime_sec'] ?? 300),
                    'lock_ttl_sec' => (int) ($definition['lock_ttl_sec'] ?? 360),
                    'retry_delay_sec' => (int) ($definition['retry_delay_sec'] ?? 300),
                    'next_run_at' => $nextRunAt,
                ]
            );
        }
    }

    private static function getAutoCreatedAgentsSummary(): array {
        $items = [];
        foreach (CronAgentRegistry::getAutoCreatedDefaultAgents() as $handler => $definition) {
            $code = trim((string) ($definition['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $existing = SafeMySQL::gi()->getRow(
                'SELECT agent_id, is_active FROM ?n WHERE code = ?s',
                Constants::CRON_AGENTS_TABLE,
                $code
            );

            $items[] = [
                'code' => $code,
                'handler' => $handler,
                'title' => strtoupper((string) ENV_DEF_LANG) === 'RU'
                    ? (string) ($definition['title_ru'] ?? $code)
                    : (string) ($definition['title_en'] ?? $code),
                'description' => strtoupper((string) ENV_DEF_LANG) === 'RU'
                    ? (string) ($definition['description_ru'] ?? '')
                    : (string) ($definition['description_en'] ?? ''),
                'exists' => is_array($existing),
                'is_active' => !empty($existing['is_active']),
            ];
        }

        return $items;
    }

    private static function acquireLock(string $lockName, int $timeout = 0): bool {
        $acquired = (int) SafeMySQL::gi()->getOne('SELECT GET_LOCK(?s, ?i)', substr($lockName, 0, 191), max(0, $timeout));
        return $acquired === 1;
    }

    private static function releaseLock(string $lockName): void {
        SafeMySQL::gi()->getOne('SELECT RELEASE_LOCK(?s)', substr($lockName, 0, 191));
    }

    private static function getSchedulerLockName(): string {
        return substr((string) ENV_DB_NAME . ':cron_agents:scheduler', 0, 191);
    }

    private static function getDispatchLockName(): string {
        return substr((string) ENV_DB_NAME . ':cron_agents:dispatch', 0, 191);
    }

    private static function buildWorkerId(): string {
        $host = gethostname() ?: 'localhost';
        $pid = function_exists('getmypid') ? (int) getmypid() : 0;
        return $host . ':' . $pid . ':' . Logger::getRequestId();
    }
}
