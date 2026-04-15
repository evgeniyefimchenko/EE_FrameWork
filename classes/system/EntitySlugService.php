<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

/**
 * Управляет slug-полями страниц и категорий.
 */
final class EntitySlugService {

    private static bool $infrastructureReady = false;
    private const RESERVED_ROOT_SEGMENTS = [
        'admin',
        'user',
        'about',
        'contact',
        'docs',
        'show-login-form',
        'show_login_form',
        'login',
        'logout',
        'exit-login',
        'exit_login',
        'register',
        'privacy-policy',
        'privacy_policy',
        'consent-personal-data',
        'consent_personal_data',
        'required-consents',
        'required_consents',
        'auth-consent',
        'auth_consent',
    ];

    public static function ensureInfrastructure(bool $force = false): void {
        if (self::$infrastructureReady && !$force) {
            return;
        }

        if (!$force) {
            self::$infrastructureReady = self::hasCategorySchema() && self::hasPageSchema();
            if (!self::$infrastructureReady) {
                throw new \RuntimeException('Slug infrastructure is not installed. Run install/upgrade first.');
            }
            return;
        }

        self::ensureCategoriesSchema();
        self::ensurePagesSchema();
        self::$infrastructureReady = true;
    }

    public static function generateCategorySlug(array $categoryData, ?int $excludeCategoryId = null): string {
        $typeId = (int) ($categoryData['type_id'] ?? 0);
        $parentId = (int) ($categoryData['parent_id'] ?? 0);
        $languageCode = self::normalizeLanguageCode((string) ($categoryData['language_code'] ?? ENV_DEF_LANG));
        $seed = trim((string) ($categoryData['slug'] ?? ''));
        $title = trim((string) ($categoryData['title'] ?? ''));
        $policy = UrlPolicyService::resolveEffectivePolicy(
            'category',
            $languageCode,
            (int) ($categoryData['url_policy_id'] ?? 0)
        );

        $baseSlug = self::normalizeSlugSeed(
            $seed,
            $title,
            'category',
            $policy,
            !empty($categoryData['preserve_existing_slug'])
        );
        $candidate = $baseSlug;
        $suffix = 2;

        while (
            self::categorySlugExists($candidate, $typeId, $languageCode, $excludeCategoryId)
            || self::categoryPathConflictsWithPage($candidate, $parentId, $languageCode)
            || self::isReservedRootSegment($candidate, $parentId, $policy)
        ) {
            $candidate = self::appendNumericSuffix($baseSlug, $suffix);
            $suffix++;
        }

        return $candidate;
    }

    public static function generateCategoryRoutePath(array $categoryData, ?int $excludeCategoryId = null): string {
        $routePath = self::normalizeRoutePath((string) ($categoryData['route_path'] ?? ''));
        if ($routePath === '') {
            return '';
        }

        return self::ensureUniqueRoutePath($routePath, 'category', self::normalizeLanguageCode((string) ($categoryData['language_code'] ?? ENV_DEF_LANG)), $excludeCategoryId);
    }

    public static function generatePageSlug(array $pageData, ?int $excludePageId = null): string {
        $categoryId = (int) ($pageData['category_id'] ?? 0);
        $languageCode = self::normalizeLanguageCode((string) ($pageData['language_code'] ?? ENV_DEF_LANG));
        $seed = trim((string) ($pageData['slug'] ?? ''));
        $title = trim((string) ($pageData['title'] ?? ''));
        $policy = UrlPolicyService::resolveEffectivePolicy(
            'page',
            $languageCode,
            (int) ($pageData['url_policy_id'] ?? 0)
        );

        $baseSlug = self::normalizeSlugSeed(
            $seed,
            $title,
            'page',
            $policy,
            !empty($pageData['preserve_existing_slug'])
        );
        $candidate = $baseSlug;
        $suffix = 2;

        while (
            self::pageSlugExists($candidate, $categoryId, $languageCode, $excludePageId)
            || self::pagePathConflictsWithCategory($candidate, $categoryId, $languageCode)
        ) {
            $candidate = self::appendNumericSuffix($baseSlug, $suffix);
            $suffix++;
        }

        return $candidate;
    }

    public static function generatePageRoutePath(array $pageData, ?int $excludePageId = null): string {
        $routePath = self::normalizeRoutePath((string) ($pageData['route_path'] ?? ''));
        if ($routePath === '') {
            return '';
        }

        return self::ensureUniqueRoutePath($routePath, 'page', self::normalizeLanguageCode((string) ($pageData['language_code'] ?? ENV_DEF_LANG)), $excludePageId);
    }

    public static function normalizeRoutePath(string $value): string {
        $value = trim((string) parse_url($value, PHP_URL_PATH));
        if ($value === '') {
            return '';
        }

        $value = str_replace('\\', '/', $value);
        $value = preg_replace('~/+~', '/', $value) ?? $value;
        $value = '/' . trim($value, '/');
        if ($value === '/') {
            return '';
        }

        return $value;
    }

    private static function ensureCategoriesSchema(): void {
        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD COLUMN IF NOT EXISTS slug VARCHAR(255) DEFAULT NULL AFTER title',
            Constants::CATEGORIES_TABLE
        );
        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD COLUMN IF NOT EXISTS route_path VARCHAR(512) DEFAULT NULL AFTER slug',
            Constants::CATEGORIES_TABLE
        );

        self::backfillCategorySlugs();

        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD INDEX IF NOT EXISTS idx_categories_lang_slug (language_code, slug)',
            Constants::CATEGORIES_TABLE
        );
        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD INDEX IF NOT EXISTS idx_categories_parent_lang_slug (parent_id, language_code, slug)',
            Constants::CATEGORIES_TABLE
        );
        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD INDEX IF NOT EXISTS idx_categories_lang_route_path (language_code, route_path)',
            Constants::CATEGORIES_TABLE
        );
        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD UNIQUE INDEX IF NOT EXISTS uq_categories_slug_type_lang (slug, type_id, language_code)',
            Constants::CATEGORIES_TABLE
        );
        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD UNIQUE INDEX IF NOT EXISTS uq_categories_route_path_lang (language_code, route_path)',
            Constants::CATEGORIES_TABLE
        );
    }

    private static function ensurePagesSchema(): void {
        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD COLUMN IF NOT EXISTS slug VARCHAR(255) DEFAULT NULL AFTER title',
            Constants::PAGES_TABLE
        );
        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD COLUMN IF NOT EXISTS route_path VARCHAR(512) DEFAULT NULL AFTER slug',
            Constants::PAGES_TABLE
        );

        self::backfillPageSlugs();

        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD INDEX IF NOT EXISTS idx_pages_lang_slug (language_code, slug)',
            Constants::PAGES_TABLE
        );
        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD INDEX IF NOT EXISTS idx_pages_category_lang_slug (category_id, language_code, slug)',
            Constants::PAGES_TABLE
        );
        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD INDEX IF NOT EXISTS idx_pages_lang_route_path (language_code, route_path)',
            Constants::PAGES_TABLE
        );
        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD UNIQUE INDEX IF NOT EXISTS uq_pages_slug_category_lang (slug, category_id, language_code)',
            Constants::PAGES_TABLE
        );
        SafeMySQL::gi()->query(
            'ALTER TABLE ?n ADD UNIQUE INDEX IF NOT EXISTS uq_pages_route_path_lang (language_code, route_path)',
            Constants::PAGES_TABLE
        );
    }

    public static function getReservedRootSegments(): array {
        return self::RESERVED_ROOT_SEGMENTS;
    }

    private static function backfillCategorySlugs(): void {
        $rows = SafeMySQL::gi()->getAll(
            'SELECT category_id, type_id, title, language_code, slug FROM ?n ORDER BY category_id ASC',
            Constants::CATEGORIES_TABLE
        );

        foreach ($rows as $row) {
            $categoryId = (int) ($row['category_id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }

            $currentSlug = trim((string) ($row['slug'] ?? ''));
            $generatedSlug = self::generateCategorySlug($row, $categoryId);
            if ($generatedSlug === '' || $generatedSlug === $currentSlug) {
                continue;
            }

            SafeMySQL::gi()->query(
                'UPDATE ?n SET slug = ?s WHERE category_id = ?i',
                Constants::CATEGORIES_TABLE,
                $generatedSlug,
                $categoryId
            );
        }
    }

    private static function backfillPageSlugs(): void {
        $rows = SafeMySQL::gi()->getAll(
            'SELECT page_id, category_id, title, language_code, slug FROM ?n ORDER BY page_id ASC',
            Constants::PAGES_TABLE
        );

        foreach ($rows as $row) {
            $pageId = (int) ($row['page_id'] ?? 0);
            if ($pageId <= 0) {
                continue;
            }

            $currentSlug = trim((string) ($row['slug'] ?? ''));
            $generatedSlug = self::generatePageSlug($row, $pageId);
            if ($generatedSlug === '' || $generatedSlug === $currentSlug) {
                continue;
            }

            SafeMySQL::gi()->query(
                'UPDATE ?n SET slug = ?s WHERE page_id = ?i',
                Constants::PAGES_TABLE,
                $generatedSlug,
                $pageId
            );
        }
    }

    private static function categorySlugExists(string $slug, int $typeId, string $languageCode, ?int $excludeCategoryId = null): bool {
        if ($slug === '') {
            return false;
        }

        $sql = 'SELECT category_id FROM ?n WHERE slug = ?s AND type_id = ?i AND language_code = ?s';
        $params = [Constants::CATEGORIES_TABLE, $slug, $typeId, $languageCode];
        if ($excludeCategoryId !== null && $excludeCategoryId > 0) {
            $sql .= ' AND category_id != ?i';
            $params[] = $excludeCategoryId;
        }
        $sql .= ' LIMIT 1';

        return (bool) SafeMySQL::gi()->getOne($sql, ...$params);
    }

    private static function pageSlugExists(string $slug, int $categoryId, string $languageCode, ?int $excludePageId = null): bool {
        if ($slug === '') {
            return false;
        }

        $sql = 'SELECT page_id FROM ?n WHERE slug = ?s AND category_id = ?i AND language_code = ?s';
        $params = [Constants::PAGES_TABLE, $slug, $categoryId, $languageCode];
        if ($excludePageId !== null && $excludePageId > 0) {
            $sql .= ' AND page_id != ?i';
            $params[] = $excludePageId;
        }
        $sql .= ' LIMIT 1';

        return (bool) SafeMySQL::gi()->getOne($sql, ...$params);
    }

    private static function categoryPathConflictsWithPage(string $slug, int $parentId, string $languageCode): bool {
        if ($slug === '' || $parentId <= 0) {
            return false;
        }

        return (bool) SafeMySQL::gi()->getOne(
            'SELECT page_id FROM ?n WHERE category_id = ?i AND language_code = ?s AND slug = ?s LIMIT 1',
            Constants::PAGES_TABLE,
            $parentId,
            $languageCode,
            $slug
        );
    }

    private static function pagePathConflictsWithCategory(string $slug, int $categoryId, string $languageCode): bool {
        if ($slug === '' || $categoryId <= 0) {
            return false;
        }

        return (bool) SafeMySQL::gi()->getOne(
            'SELECT category_id FROM ?n WHERE parent_id = ?i AND language_code = ?s AND slug = ?s LIMIT 1',
            Constants::CATEGORIES_TABLE,
            $categoryId,
            $languageCode,
            $slug
        );
    }

    private static function isReservedRootSegment(string $slug, int $parentId, array $policy = []): bool {
        if ($parentId > 0 || $slug === '') {
            return false;
        }

        $reservedSegments = array_merge(
            self::RESERVED_ROOT_SEGMENTS,
            UrlPolicyService::getReservedWordsExtra($policy)
        );

        return in_array($slug, array_values(array_unique($reservedSegments)), true);
    }

    private static function ensureUniqueRoutePath(string $routePath, string $entityType, string $languageCode, ?int $excludeId = null): string {
        $candidate = self::normalizeRoutePath($routePath);
        if ($candidate === '') {
            return '';
        }

        $basePath = $candidate;
        $suffix = 2;
        while (self::routePathExists($candidate, $entityType, $languageCode, $excludeId)) {
            $candidate = self::appendNumericSuffixToRoutePath($basePath, $suffix);
            $suffix++;
        }

        return $candidate;
    }

    private static function routePathExists(string $routePath, string $entityType, string $languageCode, ?int $excludeId = null): bool {
        $routePath = self::normalizeRoutePath($routePath);
        if ($routePath === '') {
            return false;
        }

        $entityType = strtolower(trim($entityType));
        $languageCode = self::normalizeLanguageCode($languageCode);

        $sql = 'SELECT category_id FROM ?n WHERE route_path = ?s AND language_code = ?s';
        $params = [Constants::CATEGORIES_TABLE, $routePath, $languageCode];
        if ($entityType === 'category' && $excludeId !== null && $excludeId > 0) {
            $sql .= ' AND category_id != ?i';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        if ((int) SafeMySQL::gi()->getOne($sql, ...$params) > 0) {
            return true;
        }

        $sql = 'SELECT page_id FROM ?n WHERE route_path = ?s AND language_code = ?s';
        $params = [Constants::PAGES_TABLE, $routePath, $languageCode];
        if ($entityType === 'page' && $excludeId !== null && $excludeId > 0) {
            $sql .= ' AND page_id != ?i';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        return (int) SafeMySQL::gi()->getOne($sql, ...$params) > 0;
    }

    private static function appendNumericSuffixToRoutePath(string $routePath, int $suffix): string {
        $routePath = self::normalizeRoutePath($routePath);
        if ($routePath === '') {
            return '';
        }

        $segments = array_values(array_filter(explode('/', trim($routePath, '/')), static fn(string $segment): bool => $segment !== ''));
        if ($segments === []) {
            return '';
        }

        $lastIndex = count($segments) - 1;
        $segments[$lastIndex] = self::appendNumericSuffix($segments[$lastIndex], $suffix);

        return '/' . implode('/', $segments);
    }

    private static function normalizeSlugSeed(
        string $seed,
        string $fallbackText,
        string $entityType,
        array $policy = [],
        bool $preserveExistingSlug = false
    ): string {
        $policySettings = UrlPolicyService::normalizeSettings($policy['settings'] ?? $policy);
        $sourceMode = (string) ($policySettings['source_mode'] ?? 'title');

        $candidates = [];
        if ($preserveExistingSlug && trim($seed) !== '') {
            $candidates[] = $seed;
        } elseif ($sourceMode === 'source_slug') {
            $candidates = [$seed, $fallbackText];
        } else {
            $candidates = [$fallbackText, $seed];
        }

        foreach ($candidates as $candidate) {
            $slug = self::slugify((string) $candidate, $policySettings, $entityType);
            if ($slug !== '') {
                return $slug;
            }
        }

        return self::slugify($entityType, $policySettings, $entityType);
    }

    private static function slugify(string $value, array $policySettings = [], string $fallbackSlug = 'item'): string {
        return UrlPolicyService::applyPolicy($value, $policySettings, $fallbackSlug);
    }

    private static function appendNumericSuffix(string $baseSlug, int $suffix): string {
        $suffixPart = '-' . $suffix;
        $maxBaseLength = max(1, 190 - strlen($suffixPart));
        $baseSlug = mb_substr($baseSlug, 0, $maxBaseLength);
        $baseSlug = rtrim($baseSlug, '-');

        return $baseSlug . $suffixPart;
    }

    private static function normalizeLanguageCode(string $languageCode): string {
        $languageCode = strtoupper(trim($languageCode));
        return $languageCode !== '' ? $languageCode : strtoupper((string) ENV_DEF_LANG);
    }

    private static function hasCategorySchema(): bool {
        return self::tableHasColumns(Constants::CATEGORIES_TABLE, ['slug', 'route_path']);
    }

    private static function hasPageSchema(): bool {
        return self::tableHasColumns(Constants::PAGES_TABLE, ['slug', 'route_path']);
    }

    private static function tableHasColumns(string $table, array $columns): bool {
        $count = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s AND COLUMN_NAME IN (?a)',
            $table,
            $columns
        );

        return $count === count($columns);
    }
}
