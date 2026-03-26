<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

/**
 * Сервис резервного копирования.
 * Управляет профилями удалённого хранилища, очередью backup-запусков и фоновым worker-процессом.
 */
class BackupService {

    private const DEFAULT_RETENTION_DAYS = 14;
    private const DEFAULT_MAX_LOCAL_SNAPSHOTS = 20;
    private const DEFAULT_JOB_LOCK_TTL_SEC = 7200;
    private const DEFAULT_TARGET_TIMEOUT_SEC = 30;
    private const DEFAULT_PLAN_CODE = 'backup-local-recommended';
    private const DEFAULT_PLAN_FILE_ITEMS = ['custom', 'uploads', 'htaccess', 'configuration'];
    private const FULL_SNAPSHOT_EXCLUDED_ITEMS = ['logs', 'cache'];

    private static bool $infrastructureReady = false;
    private static bool $seedingDefaultPlans = false;

    public static function ensureInfrastructure(bool $force = false): void {
        if (self::$infrastructureReady && !$force) {
            return;
        }

        if (!SysClass::checkDatabaseConnection()) {
            self::$infrastructureReady = false;
            return;
        }

        self::createBackupTargetsTable();
        self::createBackupPlansTable();
        self::createBackupJobsTable();
        self::$infrastructureReady = true;
        self::ensureDefaultPlans();
    }

    public static function resetInfrastructureState(): void {
        self::$infrastructureReady = false;
    }

    public static function getSummary(): array {
        self::ensureInfrastructure();
        $backupRoot = self::getBackupDirectory();
        $snapshotSummary = self::getLocalSnapshotsSummary($backupRoot);
        $targetsTotal = (int) SafeMySQL::gi()->getOne('SELECT COUNT(*) FROM ?n', Constants::BACKUP_TARGETS_TABLE);
        $targetsActive = (int) SafeMySQL::gi()->getOne('SELECT COUNT(*) FROM ?n WHERE is_active = 1', Constants::BACKUP_TARGETS_TABLE);
        $plansTotal = (int) SafeMySQL::gi()->getOne('SELECT COUNT(*) FROM ?n', Constants::BACKUP_PLANS_TABLE);
        $plansActive = (int) SafeMySQL::gi()->getOne('SELECT COUNT(*) FROM ?n WHERE is_active = 1', Constants::BACKUP_PLANS_TABLE);

        $statusCounts = [
            'queued' => 0,
            'running' => 0,
            'done' => 0,
            'partial' => 0,
            'failed' => 0,
        ];
        $rows = SafeMySQL::gi()->getAll(
            'SELECT status, COUNT(*) AS total FROM ?n GROUP BY status',
            Constants::BACKUP_JOBS_TABLE
        );
        foreach ((array) $rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $statusCounts)) {
                $statusCounts[$status] = (int) ($row['total'] ?? 0);
            }
        }

        $lastCompleted = (string) (SafeMySQL::gi()->getOne(
            "SELECT finished_at FROM ?n
             WHERE status IN ('done', 'partial')
             ORDER BY backup_job_id DESC
             LIMIT 1",
            Constants::BACKUP_JOBS_TABLE
        ) ?? '');
        $staleRunning = (int) SafeMySQL::gi()->getOne(
            "SELECT COUNT(*) FROM ?n
             WHERE status = 'running'
               AND locked_until IS NOT NULL
               AND locked_until < NOW()",
            Constants::BACKUP_JOBS_TABLE
        );

        $defaultTarget = self::getDefaultTarget();

        return [
            'path' => $backupRoot,
            'retention_days' => self::getRetentionDays(),
            'max_local_snapshots' => self::getMaxLocalSnapshots(),
            'snapshots_count' => $snapshotSummary['snapshots_count'],
            'latest_snapshot' => $snapshotSummary['latest_snapshot'],
            'latest_updated_at' => $snapshotSummary['latest_updated_at'],
            'items' => $snapshotSummary['items'],
            'targets_total' => $targetsTotal,
            'targets_active' => $targetsActive,
            'plans_total' => $plansTotal,
            'plans_active' => $plansActive,
            'default_plan' => self::getDefaultPlan(),
            'default_target' => $defaultTarget,
            'jobs' => $statusCounts,
            'stale_running' => $staleRunning,
            'last_completed_at' => $lastCompleted,
        ];
    }

    public static function recoverStaleJobsNow(int $staleAfterSec = self::DEFAULT_JOB_LOCK_TTL_SEC): int {
        self::ensureInfrastructure();
        $recovered = self::recoverStaleJobs($staleAfterSec);
        if ($recovered > 0) {
            Logger::warning('backup', 'Восстановлены зависшие backup-задачи', [
                'recovered' => $recovered,
                'stale_after_sec' => max(300, $staleAfterSec),
            ], [
                'initiator' => __METHOD__,
                'details' => 'Stale backup jobs recovered',
                'include_trace' => false,
            ]);
        }

        return $recovered;
    }

    public static function getTargets(): array {
        self::ensureInfrastructure();
        $rows = SafeMySQL::gi()->getAll(
            'SELECT * FROM ?n ORDER BY is_default DESC, name ASC, target_id ASC',
            Constants::BACKUP_TARGETS_TABLE
        );

        return array_values(array_filter(array_map(
            static fn($row) => is_array($row) ? self::decorateTargetRow($row, false) : null,
            (array) $rows
        )));
    }

    public static function getTarget(int $targetId, bool $withSecrets = false): ?array {
        self::ensureInfrastructure();
        if ($targetId <= 0) {
            return null;
        }

        $row = SafeMySQL::gi()->getRow(
            'SELECT * FROM ?n WHERE target_id = ?i',
            Constants::BACKUP_TARGETS_TABLE,
            $targetId
        );

        return is_array($row) ? self::decorateTargetRow($row, $withSecrets) : null;
    }

    public static function getDefaultTarget(bool $withSecrets = false): ?array {
        self::ensureInfrastructure();
        $row = SafeMySQL::gi()->getRow(
            'SELECT * FROM ?n WHERE is_default = 1 ORDER BY target_id ASC LIMIT 1',
            Constants::BACKUP_TARGETS_TABLE
        );

        return is_array($row) ? self::decorateTargetRow($row, $withSecrets) : null;
    }

    public static function saveTarget(array $targetData): OperationResult {
        self::ensureInfrastructure();

        $targetId = (int) ($targetData['target_id'] ?? 0);
        $current = $targetId > 0 ? self::getTarget($targetId, true) : null;
        if ($targetId > 0 && !$current) {
            return OperationResult::failure('Профиль удалённого хранилища не найден.', 'backup_target_not_found');
        }

        $code = strtolower(trim((string) ($targetData['code'] ?? ($current['code'] ?? ''))));
        if ($code === '' || !preg_match('~^[a-z0-9][a-z0-9._:-]{1,99}$~', $code)) {
            return OperationResult::validation('Укажите корректный код профиля удалённого хранилища.', ['field' => 'code']);
        }

        $existing = SafeMySQL::gi()->getRow(
            'SELECT target_id FROM ?n WHERE code = ?s',
            Constants::BACKUP_TARGETS_TABLE,
            $code
        );
        if (is_array($existing) && (int) ($existing['target_id'] ?? 0) !== $targetId) {
            return OperationResult::failure('Профиль удалённого хранилища с таким кодом уже существует.', 'backup_target_duplicate_code', ['field' => 'code']);
        }

        $protocol = strtolower(trim((string) ($targetData['protocol'] ?? ($current['protocol'] ?? 'sftp'))));
        if (!in_array($protocol, ['sftp', 'ftp'], true)) {
            return OperationResult::validation('Выберите поддерживаемый протокол удалённого хранилища.', ['field' => 'protocol']);
        }

        $name = trim((string) ($targetData['name'] ?? ($current['name'] ?? '')));
        if ($name === '') {
            return OperationResult::validation('Укажите название профиля удалённого хранилища.', ['field' => 'name']);
        }

        $host = trim((string) ($targetData['host'] ?? ($current['host'] ?? '')));
        if ($host === '') {
            return OperationResult::validation('Укажите адрес удалённого хоста.', ['field' => 'host']);
        }

        $username = trim((string) ($targetData['username'] ?? ($current['username'] ?? '')));
        if ($username === '') {
            return OperationResult::validation('Укажите логин удалённого хранилища.', ['field' => 'username']);
        }

        $password = (string) ($targetData['password'] ?? '');
        if ($targetId > 0 && $password === '') {
            $password = (string) ($current['password'] ?? '');
        }

        $port = (int) ($targetData['port'] ?? ($current['port'] ?? ($protocol === 'sftp' ? 22 : 21)));
        $port = max(1, min(65535, $port));

        $remotePath = self::normalizeRemotePath((string) ($targetData['remote_path'] ?? ($current['remote_path'] ?? '/')));
        $isActive = !empty($targetData['is_active']) ? 1 : 0;
        $isDefault = !empty($targetData['is_default']) ? 1 : 0;
        if ($isDefault === 1) {
            $isActive = 1;
        }
        $timeoutSec = max(5, min(300, (int) ($targetData['timeout_sec'] ?? (($current['settings']['timeout_sec'] ?? self::DEFAULT_TARGET_TIMEOUT_SEC)))));
        $ftpPassive = !array_key_exists('ftp_passive', $targetData)
            ? (int) (($current['settings']['ftp_passive'] ?? 1) ? 1 : 0)
            : (!empty($targetData['ftp_passive']) ? 1 : 0);

        $settingsJson = json_encode([
            'timeout_sec' => $timeoutSec,
            'ftp_passive' => $ftpPassive,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $payload = [
            'code' => $code,
            'name' => $name,
            'protocol' => $protocol,
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'remote_path' => $remotePath,
            'settings_json' => $settingsJson,
            'is_active' => $isActive,
            'is_default' => $isDefault,
        ];

        if ($targetId > 0) {
            $saved = SafeMySQL::gi()->query(
                'UPDATE ?n SET ?u WHERE target_id = ?i',
                Constants::BACKUP_TARGETS_TABLE,
                $payload,
                $targetId
            );
            if (!$saved) {
                return OperationResult::failure('Не удалось обновить профиль удалённого хранилища.', 'backup_target_update_failed');
            }
        } else {
            $saved = SafeMySQL::gi()->query(
                'INSERT INTO ?n SET ?u',
                Constants::BACKUP_TARGETS_TABLE,
                $payload
            );
            if (!$saved) {
                return OperationResult::failure('Не удалось создать профиль удалённого хранилища.', 'backup_target_insert_failed');
            }
            $targetId = (int) SafeMySQL::gi()->insertId();
        }

        if ($isDefault === 1) {
            SafeMySQL::gi()->query(
                'UPDATE ?n SET is_default = 0 WHERE target_id <> ?i',
                Constants::BACKUP_TARGETS_TABLE,
                $targetId
            );
        }

        Logger::audit('backup', 'Профиль удалённого backup-хранилища сохранён', [
            'target_id' => $targetId,
            'protocol' => $protocol,
            'host' => $host,
            'remote_path' => $remotePath,
            'is_default' => $isDefault,
        ], [
            'initiator' => __METHOD__,
            'details' => 'Backup target saved',
            'include_trace' => false,
        ]);

        return OperationResult::success(['target_id' => $targetId], 'Профиль удалённого хранилища сохранён.', 'backup_target_saved');
    }

    public static function deleteTarget(int $targetId): OperationResult {
        self::ensureInfrastructure();
        $target = self::getTarget($targetId, true);
        if (!$target) {
            return OperationResult::failure('Профиль удалённого хранилища не найден.', 'backup_target_not_found');
        }

        $busyJobs = (int) SafeMySQL::gi()->getOne(
            "SELECT COUNT(*) FROM ?n
             WHERE target_id = ?i AND status IN ('queued', 'running')",
            Constants::BACKUP_JOBS_TABLE,
            $targetId
        );
        if ($busyJobs > 0) {
            return OperationResult::failure('Нельзя удалить профиль, пока с ним связаны queued/running backup-задачи.', 'backup_target_in_use');
        }

        $deleted = SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE target_id = ?i',
            Constants::BACKUP_TARGETS_TABLE,
            $targetId
        );
        if (!$deleted) {
            return OperationResult::failure('Не удалось удалить профиль удалённого хранилища.', 'backup_target_delete_failed');
        }

        Logger::audit('backup', 'Профиль удалённого backup-хранилища удалён', [
            'target_id' => $targetId,
            'code' => (string) ($target['code'] ?? ''),
        ], [
            'initiator' => __METHOD__,
            'details' => 'Backup target deleted',
            'include_trace' => false,
        ]);

        return OperationResult::success(['target_id' => $targetId], 'Профиль удалённого хранилища удалён.', 'backup_target_deleted');
    }

    public static function testTarget(int $targetId): OperationResult {
        self::ensureInfrastructure();
        $target = self::getTarget($targetId, true);
        if (!$target) {
            return OperationResult::failure('Профиль удалённого хранилища не найден.', 'backup_target_not_found');
        }

        $test = self::testRemoteConnection($target);
        SafeMySQL::gi()->query(
            'UPDATE ?n SET last_tested_at = NOW(), last_test_status = ?s, last_error_message = ?s WHERE target_id = ?i',
            Constants::BACKUP_TARGETS_TABLE,
            !empty($test['success']) ? 'success' : 'failed',
            (string) ($test['message'] ?? ''),
            $targetId
        );

        if (!empty($test['success'])) {
            return OperationResult::success($test, 'Подключение к удалённому хранилищу подтверждено.', 'backup_target_test_success');
        }

        return OperationResult::failure(
            (string) ($test['message'] ?? 'Не удалось подключиться к удалённому хранилищу.'),
            'backup_target_test_failed',
            $test
        );
    }

    public static function getPlans(): array {
        self::ensureInfrastructure();
        $rows = SafeMySQL::gi()->getAll(
            "SELECT p.*, t.name AS target_name, t.protocol AS target_protocol
             FROM ?n AS p
             LEFT JOIN ?n AS t ON t.target_id = p.target_id
             ORDER BY p.is_default DESC, p.name ASC, p.backup_plan_id ASC",
            Constants::BACKUP_PLANS_TABLE,
            Constants::BACKUP_TARGETS_TABLE
        );

        return array_values(array_filter(array_map(
            static fn($row) => is_array($row) ? self::decoratePlanRow($row) : null,
            (array) $rows
        )));
    }

    public static function getPlan(int $planId): ?array {
        self::ensureInfrastructure();
        if ($planId <= 0) {
            return null;
        }

        $row = SafeMySQL::gi()->getRow(
            "SELECT p.*, t.name AS target_name, t.protocol AS target_protocol
             FROM ?n AS p
             LEFT JOIN ?n AS t ON t.target_id = p.target_id
             WHERE p.backup_plan_id = ?i
             LIMIT 1",
            Constants::BACKUP_PLANS_TABLE,
            Constants::BACKUP_TARGETS_TABLE,
            $planId
        );

        return is_array($row) ? self::decoratePlanRow($row) : null;
    }

    public static function getDefaultPlan(): ?array {
        self::ensureInfrastructure();
        $row = SafeMySQL::gi()->getRow(
            "SELECT p.*, t.name AS target_name, t.protocol AS target_protocol
             FROM ?n AS p
             LEFT JOIN ?n AS t ON t.target_id = p.target_id
             WHERE p.is_default = 1
             ORDER BY p.backup_plan_id ASC
             LIMIT 1",
            Constants::BACKUP_PLANS_TABLE,
            Constants::BACKUP_TARGETS_TABLE
        );

        return is_array($row) ? self::decoratePlanRow($row) : null;
    }

    public static function getPlanDefaults(): array {
        return [
            'backup_plan_id' => 0,
            'code' => '',
            'name' => '',
            'description' => '',
            'db_mode' => 'all',
            'db_tables' => [],
            'file_mode' => 'exclude_selected',
            'file_items' => self::FULL_SNAPSHOT_EXCLUDED_ITEMS,
            'delivery_mode' => 'local_only',
            'target_id' => 0,
            'is_active' => 1,
            'is_default' => 0,
        ];
    }

    public static function getAvailableDatabaseTables(): array {
        self::ensureInfrastructure();
        if (!SysClass::checkDatabaseConnection()) {
            return [];
        }

        $rows = SafeMySQL::gi()->getAll('SHOW TABLES');
        $tables = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $tableName = (string) array_values($row)[0];
            if ($tableName !== '') {
                $tables[] = $tableName;
            }
        }
        sort($tables, SORT_NATURAL | SORT_FLAG_CASE);
        return $tables;
    }

    public static function getAvailableFileItems(): array {
        $catalog = self::getBackupFileItemsCatalog();
        return array_values(array_map(static function (array $item): array {
            return [
                'code' => (string) ($item['code'] ?? ''),
                'label' => (string) ($item['label'] ?? ''),
                'path_label' => (string) ($item['path_label'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'type' => (string) ($item['type'] ?? 'dir'),
            ];
        }, array_values($catalog)));
    }

    public static function savePlan(array $planData): OperationResult {
        self::ensureInfrastructure();

        $planId = (int) ($planData['backup_plan_id'] ?? 0);
        $current = $planId > 0 ? self::getPlan($planId) : null;
        if ($planId > 0 && !$current) {
            return OperationResult::failure('План резервного копирования не найден.', 'backup_plan_not_found');
        }

        $code = strtolower(trim((string) ($planData['code'] ?? ($current['code'] ?? ''))));
        if ($code === '' || !preg_match('~^[a-z0-9][a-z0-9._:-]{1,99}$~', $code)) {
            return OperationResult::validation('Укажите корректный код плана резервного копирования.', ['field' => 'code']);
        }

        $existing = SafeMySQL::gi()->getRow(
            'SELECT backup_plan_id FROM ?n WHERE code = ?s',
            Constants::BACKUP_PLANS_TABLE,
            $code
        );
        if (is_array($existing) && (int) ($existing['backup_plan_id'] ?? 0) !== $planId) {
            return OperationResult::failure('План резервного копирования с таким кодом уже существует.', 'backup_plan_duplicate_code', ['field' => 'code']);
        }

        $name = trim((string) ($planData['name'] ?? ($current['name'] ?? '')));
        if ($name === '') {
            return OperationResult::validation('Укажите название плана резервного копирования.', ['field' => 'name']);
        }

        $dbMode = self::normalizePlanMode((string) ($planData['db_mode'] ?? ($current['db_mode'] ?? 'all')), true);
        $fileMode = self::normalizePlanMode((string) ($planData['file_mode'] ?? ($current['file_mode'] ?? 'exclude_selected')), false);
        $deliveryMode = strtolower(trim((string) ($planData['delivery_mode'] ?? ($current['delivery_mode'] ?? 'local_only'))));
        if (!in_array($deliveryMode, ['local_only', 'local_and_remote', 'remote_required'], true)) {
            return OperationResult::validation('Выберите корректный способ доставки резервной копии.', ['field' => 'delivery_mode']);
        }

        $availableTables = self::getAvailableDatabaseTables();
        $dbTables = self::filterSelectedValues($planData['db_tables'] ?? ($current['db_tables'] ?? []), $availableTables);
        if ($dbMode === 'only_selected' && $dbTables === []) {
            return OperationResult::validation('Для режима "Только выбранные таблицы" выберите хотя бы одну таблицу.', ['field' => 'db_tables']);
        }

        $availableFileItems = array_column(self::getAvailableFileItems(), 'code');
        $fileItems = self::filterSelectedValues($planData['file_items'] ?? ($current['file_items'] ?? []), $availableFileItems);
        if ($fileMode === 'only_selected' && $fileItems === []) {
            return OperationResult::validation('Для режима "Только выбранные файлы и папки" выберите хотя бы один элемент.', ['field' => 'file_items']);
        }

        $resolvedTables = self::resolveDatabaseTables($dbMode, $dbTables, $availableTables);
        $resolvedFileItems = self::resolveFileItems($fileMode, $fileItems, $availableFileItems);
        if ($resolvedTables === [] && $resolvedFileItems === []) {
            return OperationResult::validation('План резервного копирования не может быть пустым. Добавьте хотя бы таблицы БД или файлы.', ['field' => 'db_mode']);
        }

        $targetId = (int) ($planData['target_id'] ?? ($current['target_id'] ?? 0));
        if ($deliveryMode === 'local_only') {
            $targetId = 0;
        } elseif ($targetId > 0) {
            $target = self::getTarget($targetId, true);
            if (!$target) {
                return OperationResult::validation('Выбранный профиль удалённого хранилища не найден.', ['field' => 'target_id']);
            }
            if (empty($target['is_active'])) {
                return OperationResult::validation('Выбранный профиль удалённого хранилища отключён.', ['field' => 'target_id']);
            }
        }

        $description = trim((string) ($planData['description'] ?? ($current['description'] ?? '')));
        $isActive = !empty($planData['is_active']) ? 1 : 0;
        $isDefault = !empty($planData['is_default']) ? 1 : 0;
        if ($isDefault === 1) {
            $isActive = 1;
        }

        $payload = [
            'code' => $code,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'db_mode' => $dbMode,
            'db_tables_json' => json_encode($dbTables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'file_mode' => $fileMode,
            'file_items_json' => json_encode($fileItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'delivery_mode' => $deliveryMode,
            'target_id' => $targetId > 0 ? $targetId : null,
            'is_active' => $isActive,
            'is_default' => $isDefault,
        ];

        if ($planId > 0) {
            $saved = SafeMySQL::gi()->query(
                'UPDATE ?n SET ?u WHERE backup_plan_id = ?i',
                Constants::BACKUP_PLANS_TABLE,
                $payload,
                $planId
            );
            if (!$saved) {
                return OperationResult::failure('Не удалось обновить план резервного копирования.', 'backup_plan_update_failed');
            }
        } else {
            $saved = SafeMySQL::gi()->query(
                'INSERT INTO ?n SET ?u',
                Constants::BACKUP_PLANS_TABLE,
                $payload
            );
            if (!$saved) {
                return OperationResult::failure('Не удалось создать план резервного копирования.', 'backup_plan_insert_failed');
            }
            $planId = (int) SafeMySQL::gi()->insertId();
        }

        if ($isDefault === 1) {
            SafeMySQL::gi()->query(
                'UPDATE ?n SET is_default = 0 WHERE backup_plan_id <> ?i',
                Constants::BACKUP_PLANS_TABLE,
                $planId
            );
        }

        Logger::audit('backup', 'План резервного копирования сохранён', [
            'backup_plan_id' => $planId,
            'code' => $code,
            'db_mode' => $dbMode,
            'file_mode' => $fileMode,
            'delivery_mode' => $deliveryMode,
        ], [
            'initiator' => __METHOD__,
            'details' => 'Backup plan saved',
            'include_trace' => false,
        ]);

        return OperationResult::success(['backup_plan_id' => $planId], 'План резервного копирования сохранён.', 'backup_plan_saved');
    }

    public static function deletePlan(int $planId): OperationResult {
        self::ensureInfrastructure();
        $plan = self::getPlan($planId);
        if (!$plan) {
            return OperationResult::failure('План резервного копирования не найден.', 'backup_plan_not_found');
        }

        $busyJobs = (int) SafeMySQL::gi()->getOne(
            "SELECT COUNT(*) FROM ?n
             WHERE plan_id = ?i AND status IN ('queued', 'running')",
            Constants::BACKUP_JOBS_TABLE,
            $planId
        );
        if ($busyJobs > 0) {
            return OperationResult::failure('Нельзя удалить план, пока по нему есть queued/running backup-задачи.', 'backup_plan_in_use');
        }

        $deleted = SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE backup_plan_id = ?i',
            Constants::BACKUP_PLANS_TABLE,
            $planId
        );
        if (!$deleted) {
            return OperationResult::failure('Не удалось удалить план резервного копирования.', 'backup_plan_delete_failed');
        }

        Logger::audit('backup', 'План резервного копирования удалён', [
            'backup_plan_id' => $planId,
            'code' => (string) ($plan['code'] ?? ''),
        ], [
            'initiator' => __METHOD__,
            'details' => 'Backup plan deleted',
            'include_trace' => false,
        ]);

        return OperationResult::success(['backup_plan_id' => $planId], 'План резервного копирования удалён.', 'backup_plan_deleted');
    }

    public static function queueBackup(array $request): OperationResult {
        self::ensureInfrastructure();

        $queuePayload = self::buildQueuedRequestPayload($request);
        if (!$queuePayload['success']) {
            return OperationResult::failure(
                (string) ($queuePayload['message'] ?? 'Не удалось подготовить резервную копию к постановке в очередь.'),
                (string) ($queuePayload['code'] ?? 'backup_queue_prepare_failed'),
                is_array($queuePayload['data'] ?? null) ? $queuePayload['data'] : []
            );
        }

        $resolved = is_array($queuePayload['data'] ?? null) ? $queuePayload['data'] : [];
        $scope = (string) ($resolved['scope'] ?? 'custom_plan');
        $deliveryMode = (string) ($resolved['delivery_mode'] ?? 'local_only');
        $targetId = (int) ($resolved['target_id'] ?? 0);
        $planId = (int) ($resolved['plan_id'] ?? 0);
        $requestedBy = (int) ($request['requested_by'] ?? 0);
        $requestedVia = trim((string) ($request['requested_via'] ?? 'admin'));
        $title = trim((string) ($request['title'] ?? ($resolved['title'] ?? self::buildJobTitle($scope, $deliveryMode))));
        $requestJson = json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $saved = SafeMySQL::gi()->query(
            'INSERT INTO ?n SET ?u',
            Constants::BACKUP_JOBS_TABLE,
            [
                'title' => $title,
                'scope' => $scope,
                'delivery_mode' => $deliveryMode,
                'plan_id' => $planId > 0 ? $planId : null,
                'target_id' => $targetId ?: null,
                'status' => 'queued',
                'requested_by' => $requestedBy > 0 ? $requestedBy : null,
                'requested_via' => $requestedVia !== '' ? $requestedVia : 'admin',
                'request_json' => $requestJson,
            ]
        );

        if (!$saved) {
            return OperationResult::failure('Не удалось поставить резервную копию в очередь.', 'backup_queue_insert_failed');
        }

        $jobId = (int) SafeMySQL::gi()->insertId();
        Logger::audit('backup', 'Резервная копия поставлена в очередь', [
            'backup_job_id' => $jobId,
            'plan_id' => $planId,
            'scope' => $scope,
            'delivery_mode' => $deliveryMode,
            'target_id' => $targetId,
        ], [
            'initiator' => __METHOD__,
            'details' => 'Backup job queued',
            'include_trace' => false,
        ]);

        return OperationResult::success(['backup_job_id' => $jobId], 'Резервная копия поставлена в очередь.', 'backup_queued');
    }

    public static function getRecentJobs(int $limit = 20): array {
        self::ensureInfrastructure();
        $limit = max(1, min($limit, 200));
        return SafeMySQL::gi()->getAll(
            "SELECT j.*, t.name AS target_name, t.protocol AS target_protocol, p.name AS plan_name, p.code AS plan_code
             FROM ?n AS j
             LEFT JOIN ?n AS t ON t.target_id = j.target_id
             LEFT JOIN ?n AS p ON p.backup_plan_id = j.plan_id
             ORDER BY j.backup_job_id DESC
             LIMIT ?i",
            Constants::BACKUP_JOBS_TABLE,
            Constants::BACKUP_TARGETS_TABLE,
            Constants::BACKUP_PLANS_TABLE,
            $limit
        );
    }

    public static function runNextQueuedJob(array $payload = []): array {
        self::ensureInfrastructure();
        $summary = [
            'recovered_stale' => self::recoverStaleJobs((int) ($payload['stale_after_sec'] ?? self::DEFAULT_JOB_LOCK_TTL_SEC)),
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'partial' => 0,
            'runs' => [],
        ];

        $job = self::claimNextJob();
        if (!$job) {
            $summary['status'] = 'noop';
            $summary['message'] = 'Backup queue is empty.';
            return [
                'success' => true,
                'status' => 'noop',
                'message' => 'Backup queue is empty.',
                'data' => $summary,
            ];
        }

        $execution = self::executeJob($job);
        $summary['processed'] = 1;
        $summary['runs'][] = $execution;
        if (($execution['status'] ?? '') === 'done') {
            $summary['success'] = 1;
        } elseif (($execution['status'] ?? '') === 'partial') {
            $summary['partial'] = 1;
        } else {
            $summary['failed'] = 1;
        }

        return [
            'success' => !empty($execution['success']),
            'status' => (string) ($execution['status'] ?? 'failed'),
            'message' => (string) ($execution['message'] ?? 'Backup worker finished.'),
            'data' => $summary,
            'output' => (string) ($execution['output'] ?? ''),
        ];
    }

    private static function claimNextJob(): ?array {
        $job = SafeMySQL::gi()->getRow(
            "SELECT * FROM ?n
             WHERE status = 'queued'
             ORDER BY backup_job_id ASC
             LIMIT 1",
            Constants::BACKUP_JOBS_TABLE
        );
        if (!is_array($job) || empty($job['backup_job_id'])) {
            return null;
        }

        $jobId = (int) $job['backup_job_id'];
        $workerId = self::buildWorkerId();
        $lockTtl = self::DEFAULT_JOB_LOCK_TTL_SEC;
        SafeMySQL::gi()->query(
            "UPDATE ?n
             SET status = 'running',
                 started_at = NOW(),
                 attempts = attempts + 1,
                 locked_at = NOW(),
                 locked_until = DATE_ADD(NOW(), INTERVAL ?i SECOND),
                 locked_by = ?s
             WHERE backup_job_id = ?i
               AND status = 'queued'",
            Constants::BACKUP_JOBS_TABLE,
            $lockTtl,
            $workerId,
            $jobId
        );

        if ((int) SafeMySQL::gi()->affectedRows() <= 0) {
            return null;
        }

        return SafeMySQL::gi()->getRow(
            'SELECT * FROM ?n WHERE backup_job_id = ?i',
            Constants::BACKUP_JOBS_TABLE,
            $jobId
        ) ?: null;
    }

    private static function executeJob(array $job): array {
        $jobId = (int) ($job['backup_job_id'] ?? 0);
        $scope = (string) ($job['scope'] ?? 'project_data');
        $deliveryMode = (string) ($job['delivery_mode'] ?? 'local_only');
        $snapshotName = date('Ymd_His') . '_job' . $jobId;
        $snapshotDir = self::getBackupDirectory() . ENV_DIRSEP . $snapshotName;

        try {
            $local = self::buildLocalSnapshot($job, $snapshotName, $snapshotDir);
            $remoteResult = [
                'success' => true,
                'status' => 'not_required',
                'message' => 'Remote upload not required.',
                'files' => [],
            ];

            if ($deliveryMode !== 'local_only') {
                $target = self::getTarget((int) ($job['target_id'] ?? 0), true);
                if (!$target) {
                    throw new \RuntimeException('Не найден профиль удалённого хранилища для резервной копии.');
                }
                $remoteResult = self::uploadSnapshotToTarget($target, $snapshotName, $snapshotDir, $local);
            }

            $status = 'done';
            $message = 'Резервная копия создана.';
            $success = true;
            if ($deliveryMode === 'local_and_remote' && empty($remoteResult['success'])) {
                $status = 'partial';
                $message = 'Локальная резервная копия создана, но отправка на удалённый хост завершилась с ошибкой.';
                $success = true;
            } elseif ($deliveryMode === 'remote_required' && empty($remoteResult['success'])) {
                $status = 'failed';
                $message = 'Резервная копия создана локально, но обязательная удалённая отправка завершилась с ошибкой.';
                $success = false;
            }

            SafeMySQL::gi()->query(
                'UPDATE ?n SET ?u WHERE backup_job_id = ?i',
                Constants::BACKUP_JOBS_TABLE,
                [
                    'status' => $status,
                    'snapshot_name' => $snapshotName,
                    'snapshot_path' => $snapshotDir,
                    'db_archive' => basename((string) ($local['db_archive'] ?? '')),
                    'files_archive' => basename((string) ($local['files_archive'] ?? '')),
                    'manifest_json' => json_encode($local['manifest'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                    'remote_result_json' => json_encode($remoteResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                    'last_error_message' => $status === 'done' ? null : (string) ($remoteResult['message'] ?? ''),
                    'finished_at' => date('Y-m-d H:i:s'),
                    'locked_at' => null,
                    'locked_until' => null,
                    'locked_by' => null,
                ],
                $jobId
            );

            self::pruneLocalSnapshots();

            Logger::audit('backup', 'Фоновая backup-задача завершена', [
                'backup_job_id' => $jobId,
                'scope' => $scope,
                'delivery_mode' => $deliveryMode,
                'status' => $status,
                'snapshot' => $snapshotName,
            ], [
                'initiator' => __METHOD__,
                'details' => $message,
                'include_trace' => false,
            ]);

            return [
                'success' => $success,
                'status' => $status,
                'message' => $message,
                'data' => [
                    'backup_job_id' => $jobId,
                    'snapshot_name' => $snapshotName,
                    'snapshot_path' => $snapshotDir,
                    'scope' => $scope,
                    'delivery_mode' => $deliveryMode,
                ],
            ];
        } catch (\Throwable $e) {
            SafeMySQL::gi()->query(
                'UPDATE ?n SET ?u WHERE backup_job_id = ?i',
                Constants::BACKUP_JOBS_TABLE,
                [
                    'status' => 'failed',
                    'last_error_message' => $e->getMessage(),
                    'finished_at' => date('Y-m-d H:i:s'),
                    'locked_at' => null,
                    'locked_until' => null,
                    'locked_by' => null,
                ],
                $jobId
            );

            Logger::error('backup', 'Ошибка фоновой backup-задачи', [
                'backup_job_id' => $jobId,
                'message' => $e->getMessage(),
            ], [
                'initiator' => __METHOD__,
                'details' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'message' => $e->getMessage(),
                'data' => [
                    'backup_job_id' => $jobId,
                ],
            ];
        }
    }

    private static function buildLocalSnapshot(array $job, string $snapshotName, string $snapshotDir): array {
        if (!is_dir($snapshotDir) && !@mkdir($snapshotDir, 0775, true) && !is_dir($snapshotDir)) {
            throw new \RuntimeException('Не удалось создать директорию снапшота.');
        }

        $request = self::decodeJsonPayload((string) ($job['request_json'] ?? ''));
        $resolvedTables = array_values(array_filter(
            array_map('strval', (array) ($request['resolved_db_tables'] ?? [])),
            static fn(string $tableName): bool => $tableName !== ''
        ));
        $resolvedFileItems = array_values(array_filter(
            array_map('strval', (array) ($request['resolved_file_items'] ?? [])),
            static fn(string $itemCode): bool => $itemCode !== ''
        ));

        if ($resolvedTables === [] && !array_key_exists('resolved_db_tables', $request) && ($job['scope'] ?? 'project_data') !== 'files_only') {
            $resolvedTables = self::getAvailableDatabaseTables();
        }
        if ($resolvedFileItems === [] && !array_key_exists('resolved_file_items', $request) && ($job['scope'] ?? 'project_data') === 'project_data') {
            $resolvedFileItems = self::DEFAULT_PLAN_FILE_ITEMS;
        }

        $dbArchive = '';
        if ($resolvedTables !== []) {
            $dbArchive = SysClass::backupDatabase(
                (string) ENV_DB_HOST,
                (string) ENV_DB_USER,
                (string) ENV_DB_PASS,
                (string) ENV_DB_NAME,
                $snapshotDir,
                '',
                $resolvedTables
            );
        }

        $filesArchive = '';
        if ($resolvedFileItems !== []) {
            $filesArchive = $snapshotDir . ENV_DIRSEP . 'project_data.zip';
            self::createZipFromPaths($filesArchive, self::resolveZipPathsFromItemCodes($resolvedFileItems));
        }

        $manifest = [
            'generated_at' => date('c'),
            'snapshot' => $snapshotName,
            'scope' => (string) ($job['scope'] ?? 'project_data'),
            'delivery_mode' => (string) ($job['delivery_mode'] ?? 'local_only'),
            'db_archive' => $dbArchive !== '' ? basename($dbArchive) : '',
            'files_archive' => $filesArchive !== '' ? basename($filesArchive) : '',
            'site' => defined('ENV_SITE_NAME') ? ENV_SITE_NAME : 'EE_FrameWork',
            'version' => defined('ENV_VERSION_CORE') ? ENV_VERSION_CORE : '',
            'plan' => [
                'plan_id' => (int) ($request['plan_id'] ?? 0),
                'plan_code' => (string) ($request['plan_code'] ?? ''),
                'plan_name' => (string) ($request['plan_name'] ?? ''),
                'db_mode' => (string) ($request['db_mode'] ?? ''),
                'db_tables' => $resolvedTables,
                'file_mode' => (string) ($request['file_mode'] ?? ''),
                'file_items' => $resolvedFileItems,
            ],
            'checksum' => [
                'db_archive' => ($dbArchive !== '' && is_file($dbArchive)) ? hash_file('sha256', $dbArchive) : '',
                'files_archive' => ($filesArchive !== '' && is_file($filesArchive)) ? hash_file('sha256', $filesArchive) : '',
            ],
        ];
        file_put_contents(
            $snapshotDir . ENV_DIRSEP . 'manifest.json',
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );

        return [
            'db_archive' => $dbArchive,
            'files_archive' => $filesArchive,
            'manifest' => $manifest,
        ];
    }

    private static function uploadSnapshotToTarget(array $target, string $snapshotName, string $snapshotDir, array $local): array {
        $remoteBase = self::normalizeRemotePath((string) ($target['remote_path'] ?? '/')) . '/' . $snapshotName;
        $remoteFiles = [];
        $filesToUpload = [
            $snapshotDir . ENV_DIRSEP . 'manifest.json' => $remoteBase . '/manifest.json',
        ];
        if (!empty($local['db_archive'])) {
            $filesToUpload[(string) $local['db_archive']] = $remoteBase . '/' . basename((string) $local['db_archive']);
        }
        if (!empty($local['files_archive'])) {
            $filesToUpload[(string) $local['files_archive']] = $remoteBase . '/' . basename((string) $local['files_archive']);
        }

        foreach ($filesToUpload as $localPath => $remotePath) {
            if ($localPath === '' || !is_file($localPath)) {
                continue;
            }
            if (($target['protocol'] ?? 'sftp') === 'ftp') {
                self::uploadFileViaFtp($target, $localPath, $remotePath);
            } else {
                self::uploadFileViaSftp($target, $localPath, $remotePath);
            }
            $remoteFiles[] = [
                'local' => $localPath,
                'remote' => $remotePath,
                'size' => @filesize($localPath) ?: 0,
            ];
        }

        return [
            'success' => true,
            'status' => 'done',
            'message' => 'Резервная копия отправлена на удалённый хост.',
            'target_id' => (int) ($target['target_id'] ?? 0),
            'remote_base' => $remoteBase,
            'files' => $remoteFiles,
        ];
    }

    private static function uploadFileViaSftp(array $target, string $localPath, string $remotePath): void {
        $fh = @fopen($localPath, 'rb');
        if (!$fh) {
            throw new \RuntimeException('Не удалось открыть локальный файл для SFTP-отправки: ' . basename($localPath));
        }

        $ch = curl_init();
        $timeout = (int) ($target['settings']['timeout_sec'] ?? self::DEFAULT_TARGET_TIMEOUT_SEC);
        $password = (string) ($target['password'] ?? '');
        $result = null;
        $error = '';

        try {
            curl_setopt_array($ch, [
                CURLOPT_URL => self::buildRemoteUrl('sftp', $target, $remotePath),
                CURLOPT_USERPWD => (string) ($target['username'] ?? '') . ':' . $password,
                CURLOPT_UPLOAD => true,
                CURLOPT_INFILE => $fh,
                CURLOPT_INFILESIZE => (int) filesize($localPath),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FTP_CREATE_MISSING_DIRS => CURLFTP_CREATE_DIR_RETRY,
            ]);

            $result = curl_exec($ch);

            if ($result === false) {
                $error = curl_error($ch);
            }
        } finally {
            if (is_resource($fh)) {
                fclose($fh);
            }
            if (is_resource($ch) || $ch instanceof \CurlHandle) {
                curl_close($ch);
            }
        }

        if ($result === false) {
            throw new \RuntimeException('SFTP upload failed: ' . ($error !== '' ? $error : basename($localPath)));
        }
    }

    private static function uploadFileViaFtp(array $target, string $localPath, string $remotePath): void {
        $connection = self::openFtpConnection($target);
        try {
            self::ensureFtpDirectory($connection, dirname($remotePath));
            if (!@ftp_put($connection, $remotePath, $localPath, FTP_BINARY)) {
                throw new \RuntimeException('FTP upload failed for ' . basename($localPath));
            }
        } finally {
            @ftp_close($connection);
        }
    }

    private static function testRemoteConnection(array $target): array {
        if (($target['protocol'] ?? 'sftp') === 'ftp') {
            $connection = null;
            try {
                $connection = self::openFtpConnection($target);
                self::ensureFtpDirectory($connection, (string) ($target['remote_path'] ?? '/'));
                return [
                    'success' => true,
                    'message' => 'FTP подключение успешно.',
                ];
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            } finally {
                if ($connection) {
                    @ftp_close($connection);
                }
            }
        }

        $remotePath = (string) ($target['remote_path'] ?? '/');
        $ch = curl_init();
        $result = null;
        $error = '';
        try {
            curl_setopt_array($ch, [
                CURLOPT_URL => self::buildRemoteUrl('sftp', $target, $remotePath),
                CURLOPT_USERPWD => (string) ($target['username'] ?? '') . ':' . (string) ($target['password'] ?? ''),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => (int) ($target['settings']['timeout_sec'] ?? self::DEFAULT_TARGET_TIMEOUT_SEC),
                CURLOPT_DIRLISTONLY => true,
            ]);
            $result = curl_exec($ch);
            if ($result === false) {
                $error = curl_error($ch);
            }
        } finally {
            if (is_resource($ch) || $ch instanceof \CurlHandle) {
                curl_close($ch);
            }
        }

        if ($result === false) {
            return [
                'success' => false,
                'message' => 'SFTP подключение не подтверждено: ' . ($error !== '' ? $error : 'unknown error'),
            ];
        }

        return [
            'success' => true,
            'message' => 'SFTP подключение успешно.',
        ];
    }

    private static function openFtpConnection(array $target) {
        $timeout = (int) ($target['settings']['timeout_sec'] ?? self::DEFAULT_TARGET_TIMEOUT_SEC);
        $connection = @ftp_connect((string) ($target['host'] ?? ''), (int) ($target['port'] ?? 21), $timeout);
        if (!$connection) {
            throw new \RuntimeException('Не удалось подключиться к FTP-хосту.');
        }

        $loggedIn = @ftp_login($connection, (string) ($target['username'] ?? ''), (string) ($target['password'] ?? ''));
        if (!$loggedIn) {
            @ftp_close($connection);
            throw new \RuntimeException('Не удалось авторизоваться на FTP-хосте.');
        }

        @ftp_pasv($connection, !empty($target['settings']['ftp_passive']));
        return $connection;
    }

    private static function ensureFtpDirectory($connection, string $remotePath): void {
        $remotePath = self::normalizeRemotePath($remotePath);
        $parts = array_values(array_filter(explode('/', trim($remotePath, '/'))));
        if ($parts === []) {
            return;
        }

        $current = '';
        foreach ($parts as $part) {
            $current .= '/' . $part;
            if (@ftp_chdir($connection, $current)) {
                @ftp_chdir($connection, '/');
                continue;
            }
            if (@ftp_mkdir($connection, $current) === false && !@ftp_chdir($connection, $current)) {
                throw new \RuntimeException('Не удалось создать FTP-директорию: ' . $current);
            }
            @ftp_chdir($connection, '/');
        }
    }

    private static function buildRemoteUrl(string $protocol, array $target, string $remotePath): string {
        $segments = array_map(
            static fn(string $segment): string => rawurlencode($segment),
            array_values(array_filter(explode('/', trim($remotePath, '/')), static fn($item): bool => $item !== ''))
        );
        $path = '/' . implode('/', $segments);
        if ($path === '/') {
            $path = '/';
        }

        return sprintf(
            '%s://%s:%d%s',
            $protocol,
            (string) ($target['host'] ?? ''),
            (int) ($target['port'] ?? ($protocol === 'sftp' ? 22 : 21)),
            $path
        );
    }

    private static function recoverStaleJobs(int $staleAfterSec): int {
        $staleAfterSec = max(300, $staleAfterSec);
        SafeMySQL::gi()->query(
            "UPDATE ?n
             SET status = 'failed',
                 finished_at = NOW(),
                 last_error_message = ?s,
                 locked_at = NULL,
                 locked_until = NULL,
                 locked_by = NULL
             WHERE status = 'running'
               AND locked_until IS NOT NULL
               AND locked_until < NOW()",
            Constants::BACKUP_JOBS_TABLE,
            'Recovered from stale backup lock.'
        );

        return max(0, (int) SafeMySQL::gi()->affectedRows());
    }

    private static function getBackupDirectory(): string {
        return rtrim((string) ENV_TMP_PATH, '/\\') . ENV_DIRSEP . 'backups';
    }

    private static function getLocalSnapshotsSummary(string $backupRoot): array {
        if (!is_dir($backupRoot)) {
            return [
                'snapshots_count' => 0,
                'latest_snapshot' => '',
                'latest_updated_at' => '',
                'items' => [],
            ];
        }

        $items = array_values(array_filter(glob($backupRoot . ENV_DIRSEP . '*') ?: [], 'is_dir'));
        usort($items, static function (string $left, string $right): int {
            return ((int) @filemtime($right)) <=> ((int) @filemtime($left));
        });

        $summaryItems = [];
        foreach (array_slice($items, 0, 10) as $itemPath) {
            $dbArchive = (string) ((glob($itemPath . ENV_DIRSEP . '*.sql.zip')[0] ?? ''));
            $filesArchive = '';
            foreach ((array) (glob($itemPath . ENV_DIRSEP . '*.zip') ?: []) as $zipFile) {
                if ($zipFile === $dbArchive) {
                    continue;
                }
                $filesArchive = (string) $zipFile;
                break;
            }
            $summaryItems[] = [
                'name' => basename($itemPath),
                'updated_at' => date('Y-m-d H:i:s', (int) @filemtime($itemPath)),
                'db_archive' => basename($dbArchive),
                'files_archive' => basename($filesArchive),
            ];
        }

        return [
            'snapshots_count' => count($items),
            'latest_snapshot' => $summaryItems[0]['name'] ?? '',
            'latest_updated_at' => $summaryItems[0]['updated_at'] ?? '',
            'items' => $summaryItems,
        ];
    }

    private static function pruneLocalSnapshots(): void {
        $backupRoot = self::getBackupDirectory();
        if (!is_dir($backupRoot)) {
            return;
        }

        $retentionDays = self::getRetentionDays();
        $maxSnapshots = self::getMaxLocalSnapshots();
        $items = array_values(array_filter(glob($backupRoot . ENV_DIRSEP . '*') ?: [], 'is_dir'));
        usort($items, static function (string $left, string $right): int {
            return ((int) @filemtime($right)) <=> ((int) @filemtime($left));
        });

        $now = time();
        foreach ($items as $index => $itemPath) {
            $mtime = (int) @filemtime($itemPath);
            $tooOld = $mtime > 0 && $mtime < strtotime('-' . $retentionDays . ' days', $now);
            $overflow = $index >= $maxSnapshots;
            if ($tooOld || $overflow) {
                self::removeDirectoryRecursively($itemPath);
            }
        }
    }

    private static function createBackupTargetsTable(): void {
        SafeMySQL::gi()->query(
            "CREATE TABLE IF NOT EXISTS ?n (
                target_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                protocol VARCHAR(16) NOT NULL DEFAULT 'sftp',
                host VARCHAR(255) NOT NULL,
                port SMALLINT UNSIGNED DEFAULT NULL,
                username VARCHAR(255) NOT NULL,
                password TEXT DEFAULT NULL,
                remote_path VARCHAR(512) NOT NULL DEFAULT '/',
                settings_json LONGTEXT DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                last_tested_at DATETIME DEFAULT NULL,
                last_test_status VARCHAR(16) DEFAULT NULL,
                last_error_message TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (target_id),
                UNIQUE KEY uq_backup_targets_code (code),
                KEY idx_backup_targets_default (is_default),
                KEY idx_backup_targets_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Профили удалённого backup-хранилища'",
            Constants::BACKUP_TARGETS_TABLE
        );
    }

    private static function createBackupPlansTable(): void {
        SafeMySQL::gi()->query(
            "CREATE TABLE IF NOT EXISTS ?n (
                backup_plan_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                db_mode VARCHAR(32) NOT NULL DEFAULT 'all',
                db_tables_json LONGTEXT DEFAULT NULL,
                file_mode VARCHAR(32) NOT NULL DEFAULT 'exclude_selected',
                file_items_json LONGTEXT DEFAULT NULL,
                delivery_mode VARCHAR(32) NOT NULL DEFAULT 'local_only',
                target_id INT UNSIGNED DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (backup_plan_id),
                UNIQUE KEY uq_backup_plans_code (code),
                KEY idx_backup_plans_default (is_default),
                KEY idx_backup_plans_active (is_active),
                KEY idx_backup_plans_target (target_id),
                CONSTRAINT fk_backup_plans_target FOREIGN KEY (target_id) REFERENCES ?n(target_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Планы резервного копирования'",
            Constants::BACKUP_PLANS_TABLE,
            Constants::BACKUP_TARGETS_TABLE
        );
    }

    private static function createBackupJobsTable(): void {
        SafeMySQL::gi()->query(
            "CREATE TABLE IF NOT EXISTS ?n (
                backup_job_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) DEFAULT NULL,
                scope VARCHAR(32) NOT NULL DEFAULT 'project_data',
                delivery_mode VARCHAR(32) NOT NULL DEFAULT 'local_only',
                plan_id INT UNSIGNED DEFAULT NULL,
                target_id INT UNSIGNED DEFAULT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'queued',
                requested_by INT UNSIGNED DEFAULT NULL,
                requested_via VARCHAR(32) NOT NULL DEFAULT 'admin',
                request_json LONGTEXT DEFAULT NULL,
                snapshot_name VARCHAR(64) DEFAULT NULL,
                snapshot_path VARCHAR(512) DEFAULT NULL,
                db_archive VARCHAR(255) DEFAULT NULL,
                files_archive VARCHAR(255) DEFAULT NULL,
                manifest_json LONGTEXT DEFAULT NULL,
                remote_result_json LONGTEXT DEFAULT NULL,
                started_at DATETIME DEFAULT NULL,
                finished_at DATETIME DEFAULT NULL,
                last_error_message TEXT DEFAULT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                locked_at DATETIME DEFAULT NULL,
                locked_until DATETIME DEFAULT NULL,
                locked_by VARCHAR(128) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (backup_job_id),
                KEY idx_backup_jobs_status_created (status, created_at),
                KEY idx_backup_jobs_plan (plan_id),
                KEY idx_backup_jobs_target (target_id),
                KEY idx_backup_jobs_locked (locked_until),
                KEY idx_backup_jobs_requested_by (requested_by),
                CONSTRAINT fk_backup_jobs_plan FOREIGN KEY (plan_id) REFERENCES ?n(backup_plan_id) ON DELETE SET NULL,
                CONSTRAINT fk_backup_jobs_target FOREIGN KEY (target_id) REFERENCES ?n(target_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Очередь и история backup-задач'",
            Constants::BACKUP_JOBS_TABLE,
            Constants::BACKUP_PLANS_TABLE,
            Constants::BACKUP_TARGETS_TABLE
        );

        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD COLUMN IF NOT EXISTS plan_id INT UNSIGNED DEFAULT NULL AFTER delivery_mode',
            Constants::BACKUP_JOBS_TABLE
        );
    }

    private static function ensureDefaultPlans(): void {
        if (self::$seedingDefaultPlans) {
            return;
        }

        self::$seedingDefaultPlans = true;

        try {
        $defaults = [
            [
                'code' => self::DEFAULT_PLAN_CODE,
                'name' => 'Рекомендуемый локальный backup',
                'description' => 'База данных и критичные проектные данные: custom/, uploads/, .htaccess, inc/configuration.php.',
                'db_mode' => 'all',
                'db_tables' => [],
                'file_mode' => 'only_selected',
                'file_items' => self::DEFAULT_PLAN_FILE_ITEMS,
                'delivery_mode' => 'local_only',
                'target_id' => 0,
                'is_active' => 1,
                'is_default' => 1,
            ],
            [
                'code' => 'backup-db-only',
                'name' => 'Только база данных',
                'description' => 'Полный дамп БД без файловой части.',
                'db_mode' => 'all',
                'db_tables' => [],
                'file_mode' => 'none',
                'file_items' => [],
                'delivery_mode' => 'local_only',
                'target_id' => 0,
                'is_active' => 1,
                'is_default' => 0,
            ],
            [
                'code' => 'backup-full-snapshot',
                'name' => 'Полный snapshot без cache/logs',
                'description' => 'База данных и все основные файлы проекта с исключением cache и logs.',
                'db_mode' => 'all',
                'db_tables' => [],
                'file_mode' => 'exclude_selected',
                'file_items' => self::FULL_SNAPSHOT_EXCLUDED_ITEMS,
                'delivery_mode' => 'local_only',
                'target_id' => 0,
                'is_active' => 1,
                'is_default' => 0,
            ],
        ];

        foreach ($defaults as $plan) {
            $existing = SafeMySQL::gi()->getRow(
                'SELECT backup_plan_id FROM ?n WHERE code = ?s',
                Constants::BACKUP_PLANS_TABLE,
                (string) $plan['code']
            );
            if (is_array($existing) && !empty($existing['backup_plan_id'])) {
                continue;
            }

            SafeMySQL::gi()->query(
                'INSERT INTO ?n SET ?u',
                Constants::BACKUP_PLANS_TABLE,
                [
                    'code' => (string) $plan['code'],
                    'name' => (string) $plan['name'],
                    'description' => (string) $plan['description'],
                    'db_mode' => (string) $plan['db_mode'],
                    'db_tables_json' => json_encode($plan['db_tables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'file_mode' => (string) $plan['file_mode'],
                    'file_items_json' => json_encode($plan['file_items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'delivery_mode' => (string) $plan['delivery_mode'],
                    'target_id' => null,
                    'is_active' => (int) $plan['is_active'],
                    'is_default' => (int) $plan['is_default'],
                ]
            );
        }

        $defaultPlanId = (int) (SafeMySQL::gi()->getOne(
            'SELECT backup_plan_id FROM ?n WHERE is_default = 1 ORDER BY backup_plan_id ASC LIMIT 1',
            Constants::BACKUP_PLANS_TABLE
        ) ?? 0);
        if ($defaultPlanId <= 0) {
            SafeMySQL::gi()->query(
                'UPDATE ?n SET is_default = 1 WHERE code = ?s LIMIT 1',
                Constants::BACKUP_PLANS_TABLE,
                self::DEFAULT_PLAN_CODE
            );
        }
        } finally {
            self::$seedingDefaultPlans = false;
        }
    }

    private static function decoratePlanRow(array $row): array {
        $availableTables = self::getAvailableDatabaseTables();
        $availableFileItems = array_column(self::getAvailableFileItems(), 'code');
        $row['db_tables'] = self::filterSelectedValues(self::decodeJsonPayload((string) ($row['db_tables_json'] ?? '')), $availableTables);
        $row['file_items'] = self::filterSelectedValues(self::decodeJsonPayload((string) ($row['file_items_json'] ?? '')), $availableFileItems);
        $row['db_mode'] = self::normalizePlanMode((string) ($row['db_mode'] ?? 'all'), true);
        $row['file_mode'] = self::normalizePlanMode((string) ($row['file_mode'] ?? 'exclude_selected'), false);
        $row['delivery_mode'] = strtolower((string) ($row['delivery_mode'] ?? 'local_only'));
        $row['resolved_db_tables'] = self::resolveDatabaseTables($row['db_mode'], $row['db_tables'], $availableTables);
        $row['resolved_file_items'] = self::resolveFileItems($row['file_mode'], $row['file_items'], $availableFileItems);
        $row['db_summary'] = self::buildDatabaseSummary($row['db_mode'], $row['db_tables'], count($row['resolved_db_tables']));
        $row['file_summary'] = self::buildFileSummary($row['file_mode'], $row['file_items'], count($row['resolved_file_items']));
        $row['delivery_summary'] = self::buildDeliverySummary($row['delivery_mode'], $row);
        return $row;
    }

    private static function buildQueuedRequestPayload(array $request): array {
        $planId = (int) ($request['plan_id'] ?? 0);
        if ($planId > 0) {
            $plan = self::getPlan($planId);
            if (!$plan) {
                return [
                    'success' => false,
                    'message' => 'План резервного копирования не найден.',
                    'code' => 'backup_plan_not_found',
                ];
            }
            if (empty($plan['is_active'])) {
                return [
                    'success' => false,
                    'message' => 'Выбранный план резервного копирования отключён.',
                    'code' => 'backup_plan_disabled',
                ];
            }

            $deliveryMode = (string) ($plan['delivery_mode'] ?? 'local_only');
            $targetId = (int) ($plan['target_id'] ?? 0);
            if ($deliveryMode !== 'local_only') {
                $target = $targetId > 0 ? self::getTarget($targetId, true) : self::getDefaultTarget(true);
                if (!$target) {
                    return [
                        'success' => false,
                        'message' => 'Для плана не найден активный профиль удалённого хранилища.',
                        'code' => 'backup_target_not_found',
                    ];
                }
                if (empty($target['is_active'])) {
                    return [
                        'success' => false,
                        'message' => 'Удалённый профиль плана отключён.',
                        'code' => 'backup_target_disabled',
                    ];
                }
                $targetId = (int) ($target['target_id'] ?? 0);
            } else {
                $targetId = 0;
            }

            $resolvedTables = array_values(array_map('strval', (array) ($plan['resolved_db_tables'] ?? [])));
            $resolvedFileItems = array_values(array_map('strval', (array) ($plan['resolved_file_items'] ?? [])));
            if ($resolvedTables === [] && $resolvedFileItems === []) {
                return [
                    'success' => false,
                    'message' => 'Выбранный план не содержит ни таблиц БД, ни файлов для резервного копирования.',
                    'code' => 'backup_plan_empty',
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'plan_id' => (int) ($plan['backup_plan_id'] ?? 0),
                    'plan_code' => (string) ($plan['code'] ?? ''),
                    'plan_name' => (string) ($plan['name'] ?? ''),
                    'title' => (string) ($plan['name'] ?? ''),
                    'scope' => self::deriveScopeFromResolvedSets($resolvedTables, $resolvedFileItems),
                    'delivery_mode' => $deliveryMode,
                    'target_id' => $targetId,
                    'db_mode' => (string) ($plan['db_mode'] ?? 'all'),
                    'db_tables' => array_values((array) ($plan['db_tables'] ?? [])),
                    'resolved_db_tables' => $resolvedTables,
                    'file_mode' => (string) ($plan['file_mode'] ?? 'none'),
                    'file_items' => array_values((array) ($plan['file_items'] ?? [])),
                    'resolved_file_items' => $resolvedFileItems,
                    'request_id' => Logger::getRequestId(),
                ],
            ];
        }

        $scope = strtolower(trim((string) ($request['scope'] ?? 'project_data')));
        if (!in_array($scope, ['db_only', 'project_data'], true)) {
            return [
                'success' => false,
                'message' => 'Выберите корректный тип резервной копии.',
                'code' => 'backup_scope_invalid',
                'data' => ['field' => 'scope'],
            ];
        }

        $deliveryMode = strtolower(trim((string) ($request['delivery_mode'] ?? 'local_only')));
        if (!in_array($deliveryMode, ['local_only', 'local_and_remote', 'remote_required'], true)) {
            return [
                'success' => false,
                'message' => 'Выберите корректный способ доставки резервной копии.',
                'code' => 'backup_delivery_invalid',
                'data' => ['field' => 'delivery_mode'],
            ];
        }

        $targetId = (int) ($request['target_id'] ?? 0);
        if ($deliveryMode !== 'local_only') {
            $target = $targetId > 0 ? self::getTarget($targetId, true) : self::getDefaultTarget(true);
            if (!$target) {
                return [
                    'success' => false,
                    'message' => 'Для удалённой отправки выберите активный профиль хранилища.',
                    'code' => 'backup_target_not_found',
                    'data' => ['field' => 'target_id'],
                ];
            }
            if (empty($target['is_active'])) {
                return [
                    'success' => false,
                    'message' => 'Выбранный профиль удалённого хранилища отключён.',
                    'code' => 'backup_target_disabled',
                    'data' => ['field' => 'target_id'],
                ];
            }
            $targetId = (int) ($target['target_id'] ?? 0);
        } else {
            $targetId = 0;
        }

        $resolvedTables = $scope === 'db_only' || $scope === 'project_data'
            ? self::getAvailableDatabaseTables()
            : [];
        $resolvedFileItems = $scope === 'project_data' ? self::DEFAULT_PLAN_FILE_ITEMS : [];

        return [
            'success' => true,
            'data' => [
                'plan_id' => 0,
                'plan_code' => '',
                'plan_name' => '',
                'scope' => $scope,
                'delivery_mode' => $deliveryMode,
                'target_id' => $targetId,
                'db_mode' => 'all',
                'db_tables' => [],
                'resolved_db_tables' => $resolvedTables,
                'file_mode' => $scope === 'project_data' ? 'only_selected' : 'none',
                'file_items' => $scope === 'project_data' ? self::DEFAULT_PLAN_FILE_ITEMS : [],
                'resolved_file_items' => $resolvedFileItems,
                'request_id' => Logger::getRequestId(),
            ],
        ];
    }

    private static function normalizePlanMode(string $mode, bool $databaseMode): string {
        $mode = strtolower(trim($mode));
        $allowed = $databaseMode
            ? ['all', 'only_selected', 'exclude_selected', 'none']
            : ['all', 'only_selected', 'exclude_selected', 'none'];
        return in_array($mode, $allowed, true) ? $mode : ($databaseMode ? 'all' : 'exclude_selected');
    }

    private static function filterSelectedValues($values, array $allowedValues): array {
        $allowedMap = array_fill_keys(array_map('strval', $allowedValues), true);
        $result = [];
        foreach ((array) $values as $value) {
            $value = trim((string) $value);
            if ($value !== '' && isset($allowedMap[$value])) {
                $result[$value] = $value;
            }
        }
        return array_values($result);
    }

    private static function resolveDatabaseTables(string $mode, array $selectedTables, array $availableTables): array {
        $availableTables = array_values(array_unique(array_map('strval', $availableTables)));
        if ($mode === 'none') {
            return [];
        }
        if ($mode === 'only_selected') {
            return self::filterSelectedValues($selectedTables, $availableTables);
        }
        if ($mode === 'exclude_selected') {
            return array_values(array_diff($availableTables, self::filterSelectedValues($selectedTables, $availableTables)));
        }
        return $availableTables;
    }

    private static function resolveFileItems(string $mode, array $selectedItems, array $availableItems): array {
        $availableItems = array_values(array_unique(array_map('strval', $availableItems)));
        if ($mode === 'none') {
            return [];
        }
        if ($mode === 'only_selected') {
            return self::filterSelectedValues($selectedItems, $availableItems);
        }
        if ($mode === 'exclude_selected') {
            return array_values(array_diff($availableItems, self::filterSelectedValues($selectedItems, $availableItems)));
        }
        return $availableItems;
    }

    private static function deriveScopeFromResolvedSets(array $resolvedTables, array $resolvedFileItems): string {
        if ($resolvedTables !== [] && $resolvedFileItems === []) {
            return 'db_only';
        }
        if ($resolvedTables === [] && $resolvedFileItems !== []) {
            return 'files_only';
        }
        return 'custom_plan';
    }

    private static function buildDatabaseSummary(string $mode, array $selectedTables, int $resolvedCount): string {
        return match ($mode) {
            'none' => 'Без базы данных',
            'only_selected' => 'Только выбранные таблицы: ' . count($selectedTables) . ' шт.',
            'exclude_selected' => 'Все таблицы кроме ' . count($selectedTables) . ' исключённых',
            default => 'Все таблицы БД (' . $resolvedCount . ')',
        };
    }

    private static function buildFileSummary(string $mode, array $selectedItems, int $resolvedCount): string {
        return match ($mode) {
            'none' => 'Без файловой части',
            'only_selected' => 'Только выбранные файлы и папки: ' . count($selectedItems) . ' шт.',
            'exclude_selected' => 'Все файлы и папки кроме ' . count($selectedItems) . ' исключённых',
            default => 'Все доступные файлы и папки (' . $resolvedCount . ')',
        };
    }

    private static function buildDeliverySummary(string $deliveryMode, array $plan): string {
        $base = match ($deliveryMode) {
            'local_and_remote' => 'Локально и на удалённый хост',
            'remote_required' => 'Удалённый хост обязателен',
            default => 'Только локально',
        };
        if ($deliveryMode === 'local_only') {
            return $base;
        }
        $targetName = trim((string) ($plan['target_name'] ?? ''));
        if ($targetName !== '') {
            return $base . ': ' . $targetName;
        }
        return $base . ': профиль по умолчанию';
    }

    /**
     * @return array<string, array{code:string,label:string,path:string,path_label:string,alias:string,type:string,description:string}>
     */
    private static function getBackupFileItemsCatalog(): array {
        return [
            'app' => [
                'code' => 'app',
                'label' => 'app/',
                'path' => ENV_SITE_PATH . 'app',
                'path_label' => 'app/',
                'alias' => 'app',
                'type' => 'dir',
                'description' => 'Маршруты, контроллеры, views и модели проекта.',
            ],
            'assets' => [
                'code' => 'assets',
                'label' => 'assets/',
                'path' => ENV_SITE_PATH . 'assets',
                'path_label' => 'assets/',
                'alias' => 'assets',
                'type' => 'dir',
                'description' => 'Публичные ассеты и JS/CSS ресурсы.',
            ],
            'classes' => [
                'code' => 'classes',
                'label' => 'classes/',
                'path' => ENV_SITE_PATH . 'classes',
                'path_label' => 'classes/',
                'alias' => 'classes',
                'type' => 'dir',
                'description' => 'Ядро классов и системные сервисы.',
            ],
            'layouts' => [
                'code' => 'layouts',
                'label' => 'layouts/',
                'path' => ENV_SITE_PATH . 'layouts',
                'path_label' => 'layouts/',
                'alias' => 'layouts',
                'type' => 'dir',
                'description' => 'Layout-шаблоны проекта.',
            ],
            'custom' => [
                'code' => 'custom',
                'label' => 'custom/',
                'path' => ENV_SITE_PATH . 'custom',
                'path_label' => 'custom/',
                'alias' => 'custom',
                'type' => 'dir',
                'description' => 'Upgrade-safe кастомизации проекта.',
            ],
            'uploads' => [
                'code' => 'uploads',
                'label' => 'uploads/',
                'path' => ENV_SITE_PATH . 'uploads',
                'path_label' => 'uploads/',
                'alias' => 'uploads',
                'type' => 'dir',
                'description' => 'Загруженные файлы, изображения и временные артефакты проекта.',
            ],
            'logs' => [
                'code' => 'logs',
                'label' => 'logs/',
                'path' => ENV_SITE_PATH . 'logs',
                'path_label' => 'logs/',
                'alias' => 'logs',
                'type' => 'dir',
                'description' => 'Логи проекта. Обычно исключаются из backup-плана.',
            ],
            'cache' => [
                'code' => 'cache',
                'label' => 'cache/',
                'path' => ENV_SITE_PATH . 'cache',
                'path_label' => 'cache/',
                'alias' => 'cache',
                'type' => 'dir',
                'description' => 'Кэш проекта. Обычно исключается из backup-плана.',
            ],
            'configuration' => [
                'code' => 'configuration',
                'label' => 'inc/configuration.php',
                'path' => ENV_SITE_PATH . 'inc' . ENV_DIRSEP . 'configuration.php',
                'path_label' => 'inc/configuration.php',
                'alias' => 'inc/configuration.php',
                'type' => 'file',
                'description' => 'Основной конфигурационный файл проекта.',
            ],
            'htaccess' => [
                'code' => 'htaccess',
                'label' => '.htaccess',
                'path' => ENV_SITE_PATH . '.htaccess',
                'path_label' => '.htaccess',
                'alias' => '.htaccess',
                'type' => 'file',
                'description' => 'Правила Apache для проекта.',
            ],
            'index' => [
                'code' => 'index',
                'label' => 'index.php',
                'path' => ENV_SITE_PATH . 'index.php',
                'path_label' => 'index.php',
                'alias' => 'index.php',
                'type' => 'file',
                'description' => 'Основная точка входа приложения.',
            ],
            'error' => [
                'code' => 'error',
                'label' => 'error.php',
                'path' => ENV_SITE_PATH . 'error.php',
                'path_label' => 'error.php',
                'alias' => 'error.php',
                'type' => 'file',
                'description' => 'Обработчик ошибок и 404.',
            ],
        ];
    }

    /**
     * @param string[] $itemCodes
     * @return array<int, array{path:string, alias:string}>
     */
    private static function resolveZipPathsFromItemCodes(array $itemCodes): array {
        $catalog = self::getBackupFileItemsCatalog();
        $items = [];
        foreach ($itemCodes as $itemCode) {
            $itemCode = (string) $itemCode;
            if (!isset($catalog[$itemCode])) {
                continue;
            }
            $items[] = [
                'path' => (string) $catalog[$itemCode]['path'],
                'alias' => (string) $catalog[$itemCode]['alias'],
            ];
        }
        return $items;
    }

    private static function decorateTargetRow(array $row, bool $withSecrets): array {
        $settings = self::decodeJsonPayload((string) ($row['settings_json'] ?? ''));
        $password = (string) ($row['password'] ?? '');

        $row['settings'] = $settings;
        $row['timeout_sec'] = (int) ($settings['timeout_sec'] ?? self::DEFAULT_TARGET_TIMEOUT_SEC);
        $row['ftp_passive'] = !empty($settings['ftp_passive']) ? 1 : 0;
        $row['remote_label'] = strtoupper((string) ($row['protocol'] ?? ''))
            . '://'
            . (string) ($row['host'] ?? '')
            . ':'
            . (int) ($row['port'] ?? 0)
            . (string) ($row['remote_path'] ?? '/');
        $row['password_mask'] = $password !== '' ? '********' : '';
        if (!$withSecrets) {
            unset($row['password']);
        }
        return $row;
    }

    private static function decodeJsonPayload(string $value): array {
        if ($value === '' || !SysClass::ee_isValidJson($value)) {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function normalizeRemotePath(string $remotePath): string {
        $remotePath = trim($remotePath);
        if ($remotePath === '') {
            return '/';
        }
        return '/' . trim($remotePath, '/');
    }

    private static function buildJobTitle(string $scope, string $deliveryMode): string {
        $scopeTitle = $scope === 'db_only' ? 'БД' : 'БД + проектные данные';
        $deliveryTitle = match ($deliveryMode) {
            'local_and_remote' => 'локально + удалённо',
            'remote_required' => 'удалённо обязательно',
            default => 'локально',
        };
        return $scopeTitle . ' / ' . $deliveryTitle;
    }

    private static function getRetentionDays(): int {
        return max(1, (int) (defined('ENV_BACKUP_RETENTION_DAYS') ? ENV_BACKUP_RETENTION_DAYS : self::DEFAULT_RETENTION_DAYS));
    }

    private static function getMaxLocalSnapshots(): int {
        return max(1, (int) (defined('ENV_BACKUP_MAX_LOCAL_SNAPSHOTS') ? ENV_BACKUP_MAX_LOCAL_SNAPSHOTS : self::DEFAULT_MAX_LOCAL_SNAPSHOTS));
    }

    private static function buildWorkerId(): string {
        return php_sapi_name() . ':' . gethostname() . ':' . getmypid();
    }

    /**
     * @param array<int, array{path:string, alias:string}> $items
     */
    private static function createZipFromPaths(string $archivePath, array $items): void {
        $zip = new \ZipArchive();
        if ($zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Не удалось создать ZIP-архив резервной копии.');
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

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $fileInfo) {
                /** @var \SplFileInfo $fileInfo */
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

    private static function removeDirectoryRecursively(string $path): void {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }
}
