<?php

use classes\system\OperationResult;
use classes\system\RedirectService;
use classes\system\UrlPolicyService;

/**
 * Модель управления URL-политиками и редиректами.
 */
class ModelUrlManagement {

    public function getPolicies(?string $entityType = null): array {
        return UrlPolicyService::getPolicies($entityType, true);
    }

    public function getPolicy(int $policyId): ?array {
        return UrlPolicyService::getPolicy($policyId);
    }

    public function getPolicyDefaults(string $entityType = 'page'): array {
        return [
            'policy_id' => 0,
            'code' => '',
            'name' => '',
            'entity_type' => in_array($entityType, ['page', 'category'], true) ? $entityType : 'page',
            'language_code' => '',
            'status' => 'active',
            'is_default' => 0,
            'description' => '',
            'settings' => UrlPolicyService::getDefaultSettings(),
        ];
    }

    public function savePolicy(array $data): OperationResult {
        return UrlPolicyService::savePolicy($data);
    }

    public function deletePolicy(int $policyId): OperationResult {
        return UrlPolicyService::deletePolicy($policyId);
    }

    public function getRedirects(int $limit = 500): array {
        return RedirectService::getRedirects($limit);
    }

    public function getRedirect(int $redirectId): ?array {
        return RedirectService::getRedirect($redirectId);
    }

    public function getRedirectDefaults(): array {
        return [
            'redirect_id' => 0,
            'source_host' => '',
            'source_path' => '',
            'language_code' => '',
            'target_type' => 'path',
            'target_path' => '',
            'target_entity_type' => 'page',
            'target_entity_id' => 0,
            'http_code' => 301,
            'status' => 'active',
            'is_auto' => 0,
            'import_job_id' => null,
            'note' => '',
            'resolved_target_url' => '',
        ];
    }

    public function saveRedirect(array $data, string $conflictPolicy = 'skip_existing'): OperationResult {
        return RedirectService::saveRedirect($data, $conflictPolicy);
    }

    public function deleteRedirect(int $redirectId): OperationResult {
        return RedirectService::deleteRedirect($redirectId);
    }

    public function toggleRedirect(int $redirectId): OperationResult {
        return RedirectService::toggleRedirect($redirectId);
    }
}
