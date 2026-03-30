<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

/**
 * Управляет legacy URI и картой редиректов.
 */
final class RedirectService {

    public static function getRedirects(int $limit = 500): array {
        self::assertInfrastructure();
        $limit = max(1, min(5000, $limit));
        $rows = SafeMySQL::gi()->getAll(
            'SELECT * FROM ?n ORDER BY updated_at DESC, redirect_id DESC LIMIT ?i',
            Constants::REDIRECTS_TABLE,
            $limit
        );
        return array_values(array_filter(array_map([self::class, 'decorateRedirectRow'], is_array($rows) ? $rows : [])));
    }

    public static function getRedirect(int $redirectId): ?array {
        self::assertInfrastructure();
        if ($redirectId <= 0) {
            return null;
        }

        $row = SafeMySQL::gi()->getRow(
            'SELECT * FROM ?n WHERE redirect_id = ?i LIMIT 1',
            Constants::REDIRECTS_TABLE,
            $redirectId
        );
        return is_array($row) ? self::decorateRedirectRow($row) : null;
    }

    public static function saveRedirect(array $data, string $conflictPolicy = 'skip_existing'): OperationResult {
        self::assertInfrastructure();

        $redirectId = (int) ($data['redirect_id'] ?? 0);
        $sourceHost = self::normalizeHost((string) ($data['source_host'] ?? ''));
        $sourcePath = self::normalizePath((string) ($data['source_path'] ?? ''));
        $languageCode = self::normalizeLanguageCode((string) ($data['language_code'] ?? ''), true);
        $targetType = self::normalizeTargetType((string) ($data['target_type'] ?? 'path'));
        $targetPath = self::normalizePath((string) ($data['target_path'] ?? ''));
        $targetEntityType = self::normalizeEntityType((string) ($data['target_entity_type'] ?? ''));
        $targetEntityId = (int) ($data['target_entity_id'] ?? 0);
        $status = self::normalizeStatus((string) ($data['status'] ?? 'active'));
        $httpCode = (int) ($data['http_code'] ?? 301);
        $httpCode = in_array($httpCode, [301, 302, 307, 308], true) ? $httpCode : 301;
        $isAuto = !empty($data['is_auto']) ? 1 : 0;
        $importJobId = isset($data['import_job_id']) && (int) $data['import_job_id'] > 0 ? (int) $data['import_job_id'] : null;
        $note = trim((string) ($data['note'] ?? ''));

        if ($sourcePath === '') {
            return OperationResult::validation('Укажите исходный путь для редиректа.');
        }

        if ($targetType === 'entity') {
            if ($targetEntityType === '' || $targetEntityId <= 0) {
                return OperationResult::validation('Для entity-редиректа укажите тип и ID целевой сущности.');
            }
            $resolvedTargetUrl = self::buildEntityTargetUrl($targetEntityType, $targetEntityId, $languageCode);
            if ($resolvedTargetUrl === '') {
                return OperationResult::validation('Не удалось определить URL целевой сущности.');
            }
            $resolvedTargetPath = self::normalizePath((string) parse_url($resolvedTargetUrl, PHP_URL_PATH));
            if ($resolvedTargetPath !== '' && $resolvedTargetPath === $sourcePath) {
                return OperationResult::validation('Исходный и целевой путь совпадают. Такой редирект приведёт к циклу.');
            }
            $targetPath = null;
        } else {
            if ($targetPath === '') {
                return OperationResult::validation('Укажите целевой путь для редиректа.');
            }
            if ($targetPath === $sourcePath) {
                return OperationResult::validation('Исходный и целевой путь совпадают. Такой редирект приведёт к циклу.');
            }
            $targetEntityType = null;
            $targetEntityId = null;
        }

        $existing = SafeMySQL::gi()->getRow(
            'SELECT * FROM ?n WHERE source_host = ?s AND source_path = ?s AND language_code = ?s LIMIT 1',
            Constants::REDIRECTS_TABLE,
            $sourceHost,
            $sourcePath,
            $languageCode
        );
        $existingId = (int) ($existing['redirect_id'] ?? 0);
        if ($existingId > 0 && $redirectId > 0 && $existingId !== $redirectId) {
            return OperationResult::validation('Для такого host/path/language уже существует другой редирект.');
        }
        if ($existingId > 0 && $redirectId <= 0) {
            if ($conflictPolicy === 'replace_existing') {
                $redirectId = $existingId;
            } else {
                return OperationResult::success(['redirect_id' => $existingId], 'Редирект уже существует.', 'noop');
            }
        }

        $payload = [
            'source_host' => $sourceHost,
            'source_path' => $sourcePath,
            'language_code' => $languageCode,
            'target_type' => $targetType,
            'target_path' => $targetType === 'path' ? $targetPath : null,
            'target_entity_type' => $targetType === 'entity' ? $targetEntityType : null,
            'target_entity_id' => $targetType === 'entity' ? $targetEntityId : null,
            'http_code' => $httpCode,
            'status' => $status,
            'is_auto' => $isAuto,
            'import_job_id' => $importJobId,
            'note' => $note !== '' ? $note : null,
        ];

        if ($redirectId > 0) {
            SafeMySQL::gi()->query(
                'UPDATE ?n SET ?u WHERE redirect_id = ?i',
                Constants::REDIRECTS_TABLE,
                $payload,
                $redirectId
            );
        } else {
            SafeMySQL::gi()->query(
                'INSERT INTO ?n SET ?u',
                Constants::REDIRECTS_TABLE,
                $payload
            );
            $redirectId = (int) SafeMySQL::gi()->insertId();
        }

        if ($redirectId <= 0) {
            return OperationResult::failure('Не удалось сохранить редирект.', 'redirect_save_failed');
        }

        Router::clearRouteCache();
        return OperationResult::success(
            ['redirect_id' => $redirectId],
            'Редирект сохранён.',
            $existingId > 0 || !empty($data['redirect_id']) ? 'updated' : 'created'
        );
    }

    public static function deleteRedirect(int $redirectId): OperationResult {
        self::assertInfrastructure();
        if ($redirectId <= 0) {
            return OperationResult::validation('Не указан ID редиректа.');
        }

        $redirect = self::getRedirect($redirectId);
        if ($redirect === null) {
            return OperationResult::failure('Редирект не найден.', 'redirect_not_found');
        }

        SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE redirect_id = ?i',
            Constants::REDIRECTS_TABLE,
            $redirectId
        );
        Router::clearRouteCache();
        return OperationResult::success(['redirect_id' => $redirectId], 'Редирект удалён.', 'deleted');
    }

    public static function toggleRedirect(int $redirectId): OperationResult {
        self::assertInfrastructure();
        $redirect = self::getRedirect($redirectId);
        if ($redirect === null) {
            return OperationResult::failure('Редирект не найден.', 'redirect_not_found');
        }

        $newStatus = (string) ($redirect['status'] ?? 'active') === 'active' ? 'disabled' : 'active';
        SafeMySQL::gi()->query(
            'UPDATE ?n SET status = ?s WHERE redirect_id = ?i',
            Constants::REDIRECTS_TABLE,
            $newStatus,
            $redirectId
        );
        Router::clearRouteCache();
        return OperationResult::success(['redirect_id' => $redirectId, 'status' => $newStatus], 'Состояние редиректа обновлено.', 'updated');
    }

    public static function registerLegacyPath(
        string $entityType,
        int $entityId,
        string $sourceSystem,
        string $sourceHost,
        string $sourcePath,
        string $languageCode = '',
        ?int $importJobId = null,
        bool $isPrimary = true
    ): void {
        self::assertInfrastructure();

        $entityType = self::normalizeEntityType($entityType);
        $entityId = (int) $entityId;
        $sourceSystem = trim($sourceSystem) !== '' ? trim($sourceSystem) : 'wordpress';
        $sourceHost = self::normalizeHost($sourceHost);
        $sourcePath = self::normalizePath($sourcePath);
        $languageCode = self::normalizeLanguageCode($languageCode, true);
        if ($entityType === '' || $entityId <= 0 || $sourcePath === '') {
            return;
        }

        $payload = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'source_system' => $sourceSystem,
            'source_host' => $sourceHost,
            'source_path' => $sourcePath,
            'language_code' => $languageCode,
            'import_job_id' => $importJobId,
            'is_primary' => $isPrimary ? 1 : 0,
        ];

        SafeMySQL::gi()->query(
            'INSERT INTO ?n SET ?u
             ON DUPLICATE KEY UPDATE entity_type = VALUES(entity_type), entity_id = VALUES(entity_id), import_job_id = VALUES(import_job_id), is_primary = VALUES(is_primary), updated_at = CURRENT_TIMESTAMP',
            Constants::ENTITY_LEGACY_PATHS_TABLE,
            $payload
        );
    }

    public static function resolveRequestRedirect(string $sourcePath, string $host = '', string $languageCode = ''): ?array {
        self::assertInfrastructure();

        $sourcePath = self::normalizePath($sourcePath);
        $host = self::normalizeHost($host);
        $languageCode = self::normalizeLanguageCode($languageCode, true);
        if ($sourcePath === '') {
            return null;
        }

        $rows = self::loadRedirectCandidates($sourcePath, $host, $languageCode, false);
        if ($rows === [] && self::shouldAllowConfiguredHostFallback($host)) {
            $rows = self::loadRedirectCandidates($sourcePath, $host, $languageCode, true);
        }

        foreach ((array) $rows as $row) {
            $row = self::decorateRedirectRow($row);
            $targetUrl = trim((string) ($row['resolved_target_url'] ?? ''));
            $targetPath = self::normalizePath((string) parse_url($targetUrl, PHP_URL_PATH));
            if ($targetUrl === '' || $targetPath === '' || $targetPath === $sourcePath) {
                continue;
            }
            return [
                'redirect_id' => (int) ($row['redirect_id'] ?? 0),
                'http_code' => (int) ($row['http_code'] ?? 301),
                'target_url' => $targetUrl,
                'target_path' => $targetPath,
                'row' => $row,
            ];
        }

        return null;
    }

    /**
     * Загружает кандидаты редиректов для path/language с учётом host.
     * При $ignoreHost=true ищет любые host-specific правила, что помогает тестировать donor URI на временном домене.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function loadRedirectCandidates(string $sourcePath, string $host, string $languageCode, bool $ignoreHost): array {
        if ($ignoreHost) {
            return SafeMySQL::gi()->getAll(
                "SELECT *
                 FROM ?n
                 WHERE status = 'active'
                   AND source_path = ?s
                   AND source_host != ''
                   AND (language_code = ?s OR language_code = '')
                 ORDER BY
                   CASE WHEN language_code = ?s THEN 2 ELSE 1 END DESC,
                   is_auto DESC,
                   redirect_id DESC
                 LIMIT 5",
                Constants::REDIRECTS_TABLE,
                $sourcePath,
                $languageCode,
                $languageCode
            ) ?: [];
        }

        return SafeMySQL::gi()->getAll(
            "SELECT *
             FROM ?n
             WHERE status = 'active'
               AND source_path = ?s
               AND (source_host = ?s OR source_host = '')
               AND (language_code = ?s OR language_code = '')
             ORDER BY
               CASE WHEN source_host = ?s THEN 2 ELSE 1 END DESC,
               CASE WHEN language_code = ?s THEN 2 ELSE 1 END DESC,
               is_auto DESC,
               redirect_id DESC
             LIMIT 5",
            Constants::REDIRECTS_TABLE,
            $sourcePath,
            $host,
            $languageCode,
            $host,
            $languageCode
        ) ?: [];
    }

    /**
     * На временном проектном host разрешаем fallback на host-specific donor redirects,
     * чтобы можно было тестировать старые URI до переключения канонического домена.
     */
    private static function shouldAllowConfiguredHostFallback(string $host): bool {
        if ($host === '') {
            return false;
        }

        $configuredHost = self::normalizeHost((string) parse_url((string) ENV_URL_SITE, PHP_URL_HOST));
        if ($configuredHost === '' || $configuredHost !== $host) {
            return false;
        }

        return true;
    }

    public static function syncImportRedirectsFromLegacy(int $importJobId, array $options = []): array {
        self::assertInfrastructure();
        if ($importJobId <= 0) {
            return ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $hostScope = self::normalizeHostScope((string) ($options['host_scope'] ?? 'donor_host_only'));
        $conflictPolicy = self::normalizeConflictPolicy((string) ($options['conflict_policy'] ?? 'skip_existing'));
        $rows = SafeMySQL::gi()->getAll(
            'SELECT * FROM ?n WHERE import_job_id = ?i AND is_primary = 1 ORDER BY legacy_id ASC',
            Constants::ENTITY_LEGACY_PATHS_TABLE,
            $importJobId
        );

        $summary = ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ((array) $rows as $row) {
            $summary['processed']++;
            $entityType = self::normalizeEntityType((string) ($row['entity_type'] ?? ''));
            $entityId = (int) ($row['entity_id'] ?? 0);
            $languageCode = self::normalizeLanguageCode((string) ($row['language_code'] ?? ''), true);
            $sourcePath = self::normalizePath((string) ($row['source_path'] ?? ''));
            if ($entityType === '' || $entityId <= 0 || $sourcePath === '') {
                $summary['failed']++;
                continue;
            }

            $targetUrl = self::buildEntityTargetUrl($entityType, $entityId, $languageCode);
            $targetPath = self::normalizePath((string) parse_url($targetUrl, PHP_URL_PATH));
            if ($targetPath === '' || $targetPath === $sourcePath) {
                $summary['skipped']++;
                continue;
            }

            $sourceHost = match ($hostScope) {
                'current_host' => self::normalizeHost((string) parse_url((string) ENV_URL_SITE, PHP_URL_HOST)),
                'any_host' => '',
                default => self::normalizeHost((string) ($row['source_host'] ?? '')),
            };
            if ($hostScope === 'donor_host_only' && $sourceHost === '') {
                $summary['skipped']++;
                continue;
            }

            $result = self::saveRedirect([
                'source_host' => $sourceHost,
                'source_path' => $sourcePath,
                'language_code' => $languageCode,
                'target_type' => 'entity',
                'target_entity_type' => $entityType,
                'target_entity_id' => $entityId,
                'http_code' => 301,
                'status' => 'active',
                'is_auto' => 1,
                'import_job_id' => $importJobId,
                'note' => 'Auto-generated from legacy source path',
            ], $conflictPolicy);

            if ($result->isFailure()) {
                $summary['failed']++;
                continue;
            }

            $code = $result->getCode();
            if ($code === 'updated') {
                $summary['updated']++;
            } elseif ($code === 'created') {
                $summary['created']++;
            } else {
                $summary['skipped']++;
            }
        }

        return $summary;
    }

    private static function decorateRedirectRow(array $row): array {
        $row['redirect_id'] = (int) ($row['redirect_id'] ?? 0);
        $row['source_host'] = self::normalizeHost((string) ($row['source_host'] ?? ''));
        $row['source_path'] = self::normalizePath((string) ($row['source_path'] ?? ''));
        $row['language_code'] = self::normalizeLanguageCode((string) ($row['language_code'] ?? ''), true);
        $row['target_type'] = self::normalizeTargetType((string) ($row['target_type'] ?? 'path'));
        $row['target_path'] = self::normalizePath((string) ($row['target_path'] ?? ''));
        $row['target_entity_type'] = self::normalizeEntityType((string) ($row['target_entity_type'] ?? ''));
        $row['target_entity_id'] = (int) ($row['target_entity_id'] ?? 0);
        $row['http_code'] = (int) ($row['http_code'] ?? 301);
        $row['status'] = self::normalizeStatus((string) ($row['status'] ?? 'active'));
        $row['is_auto'] = !empty($row['is_auto']) ? 1 : 0;
        $row['resolved_target_url'] = self::resolveTargetUrlFromRow($row);
        return $row;
    }

    private static function resolveTargetUrlFromRow(array $row): string {
        if ((string) ($row['target_type'] ?? 'path') === 'entity') {
            return self::buildEntityTargetUrl(
                (string) ($row['target_entity_type'] ?? ''),
                (int) ($row['target_entity_id'] ?? 0),
                (string) ($row['language_code'] ?? '')
            );
        }

        $targetPath = self::normalizePath((string) ($row['target_path'] ?? ''));
        if ($targetPath === '') {
            return '';
        }
        return rtrim((string) ENV_URL_SITE, '/') . $targetPath;
    }

    private static function buildEntityTargetUrl(string $entityType, int $entityId, string $languageCode = ''): string {
        $entityType = self::normalizeEntityType($entityType);
        if ($entityType === '' || $entityId <= 0) {
            return '';
        }
        return EntityPublicUrlService::buildEntityUrl($entityType, $entityId, $languageCode !== '' ? $languageCode : null, true, null);
    }

    private static function normalizeEntityType(string $entityType): string {
        $entityType = strtolower(trim($entityType));
        return in_array($entityType, ['category', 'page'], true) ? $entityType : '';
    }

    private static function normalizeTargetType(string $targetType): string {
        $targetType = strtolower(trim($targetType));
        return in_array($targetType, ['path', 'entity'], true) ? $targetType : 'path';
    }

    private static function normalizeStatus(string $status): string {
        $status = strtolower(trim($status));
        return in_array($status, ['active', 'disabled'], true) ? $status : 'active';
    }

    private static function normalizeLanguageCode(string $languageCode, bool $allowEmpty = false): string {
        $languageCode = strtoupper(trim($languageCode));
        if ($languageCode === '' && !$allowEmpty) {
            return ee_get_default_content_lang_code();
        }
        return $languageCode;
    }

    private static function normalizePath(string $path): string {
        return EntitySlugService::normalizeRoutePath($path);
    }

    private static function normalizeHost(string $host): string {
        $host = trim($host);
        if ($host === '') {
            return '';
        }

        if (str_contains($host, '://')) {
            $host = (string) parse_url($host, PHP_URL_HOST);
        }

        $host = strtolower(trim($host));
        $host = preg_replace('~:\d+$~', '', $host) ?? $host;
        $host = preg_replace('~^www\.~', '', $host) ?? $host;
        return trim($host, '.');
    }

    private static function normalizeHostScope(string $scope): string {
        $scope = strtolower(trim($scope));
        return in_array($scope, ['donor_host_only', 'current_host', 'any_host'], true) ? $scope : 'donor_host_only';
    }

    private static function normalizeConflictPolicy(string $policy): string {
        $policy = strtolower(trim($policy));
        return in_array($policy, ['skip_existing', 'replace_existing'], true) ? $policy : 'skip_existing';
    }

    private static function assertInfrastructure(): void {
        $redirectsExists = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s',
            Constants::REDIRECTS_TABLE
        );
        $legacyExists = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s',
            Constants::ENTITY_LEGACY_PATHS_TABLE
        );
        if ($redirectsExists <= 0 || $legacyExists <= 0) {
            throw new \RuntimeException('Redirect infrastructure is not installed. Run install/upgrade first.');
        }
    }
}
