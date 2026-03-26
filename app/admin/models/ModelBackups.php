<?php

use classes\system\BackupService;
use classes\system\CronAgentService;
use classes\system\OperationResult;

/**
 * Модель управления резервным копированием.
 */
class ModelBackups {

    public function getSummary(): array {
        return BackupService::getSummary();
    }

    public function getPlans(): array {
        return BackupService::getPlans();
    }

    public function getPlan(int $planId): ?array {
        return BackupService::getPlan($planId);
    }

    public function getPlanDefaults(): array {
        return BackupService::getPlanDefaults();
    }

    public function getAvailableDatabaseTables(): array {
        return BackupService::getAvailableDatabaseTables();
    }

    public function getAvailableFileItems(): array {
        return BackupService::getAvailableFileItems();
    }

    public function getTargets(): array {
        return BackupService::getTargets();
    }

    public function getTarget(int $targetId): ?array {
        return BackupService::getTarget($targetId, true);
    }

    public function getRecentJobs(int $limit = 20): array {
        return BackupService::getRecentJobs($limit);
    }

    public function saveTarget(array $targetData): OperationResult {
        return BackupService::saveTarget($targetData);
    }

    public function savePlan(array $planData): OperationResult {
        return BackupService::savePlan($planData);
    }

    public function deletePlan(int $planId): OperationResult {
        return BackupService::deletePlan($planId);
    }

    public function deleteTarget(int $targetId): OperationResult {
        return BackupService::deleteTarget($targetId);
    }

    public function testTarget(int $targetId): OperationResult {
        return BackupService::testTarget($targetId);
    }

    public function queueBackup(array $request): OperationResult {
        return BackupService::queueBackup($request);
    }

    public function getWorkerAgent(): ?array {
        return CronAgentService::getAgentByCode('backup-queue-worker');
    }

    public function getSchedulerSummary(): array {
        return CronAgentService::getSummary();
    }

    public function getTargetDefaults(): array {
        return [
            'target_id' => 0,
            'code' => '',
            'name' => '',
            'protocol' => 'sftp',
            'host' => '',
            'port' => 22,
            'username' => '',
            'password' => '',
            'remote_path' => '/backups',
            'timeout_sec' => 30,
            'ftp_passive' => 1,
            'is_active' => 1,
            'is_default' => 0,
            'password_mask' => '',
        ];
    }
}
