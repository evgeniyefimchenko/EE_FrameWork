<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

/**
 * Единый контракт публичных URL для страниц и категорий.
 *
 * Контракт:
 * - категория: /<category-slug>/<child-category-slug>
 * - страница:  /<category-slug>/<child-category-slug>/<page-slug>
 *
 * Язык по умолчанию не выносится в path. При необходимости допускается suffix-параметр ?sl=XX.
 */
final class EntityPublicUrlService {

    private static array $categoryRowCache = [];
    private static array $pageRowCache = [];
    private static array $categoryPathSegmentsCache = [];
    private static array $resolvedCategorySlugCache = [];
    private static array $resolvedPageSlugCache = [];
    private static array $resolvedCategoryRoutePathCache = [];
    private static array $resolvedPageRoutePathCache = [];
    private static array $resolvedRouteCache = [];

    public static function buildEntityPath(string $entityType, int $entityId, ?string $languageCode = null): string {
        $entityType = self::normalizeEntityType($entityType);
        if ($entityType === '' || $entityId <= 0) {
            return '';
        }

        $entityId = self::resolveEntityIdForLanguage($entityType, $entityId, $languageCode);
        if ($entityId <= 0) {
            return '';
        }

        return $entityType === 'category'
            ? self::buildCategoryPath($entityId)
            : self::buildPagePath($entityId);
    }

    public static function buildEntityUrl(
        string $entityType,
        int $entityId,
        ?string $languageCode = null,
        bool $absolute = true,
        ?bool $includeLanguageQuery = null
    ): string {
        $entityType = self::normalizeEntityType($entityType);
        if ($entityType === '' || $entityId <= 0) {
            return $absolute ? rtrim((string) ENV_URL_SITE, '/') . '/' : '/';
        }

        $resolvedEntityId = self::resolveEntityIdForLanguage($entityType, $entityId, $languageCode);
        if ($resolvedEntityId <= 0) {
            $resolvedEntityId = $entityId;
        }

        $path = self::buildEntityPath($entityType, $resolvedEntityId, $languageCode);
        if ($path === '') {
            return $absolute ? rtrim((string) ENV_URL_SITE, '/') . '/' : '/';
        }

        $effectiveLanguageCode = self::getEntityLanguageCode($entityType, $resolvedEntityId);
        if ($includeLanguageQuery === null) {
            $includeLanguageQuery = self::shouldAppendLanguageQuery($entityType, $resolvedEntityId, $effectiveLanguageCode);
        }

        $query = $includeLanguageQuery && $effectiveLanguageCode !== ''
            ? '?sl=' . rawurlencode($effectiveLanguageCode)
            : '';

        if ($absolute) {
            return rtrim((string) ENV_URL_SITE, '/') . $path . $query;
        }

        return $path . $query;
    }

    public static function buildCanonicalEntityUrl(string $entityType, int $entityId, ?string $languageCode = null, bool $absolute = true): string {
        return self::buildEntityUrl($entityType, $entityId, $languageCode, $absolute, null);
    }

    public static function buildCategoryPath(int $categoryId): string {
        $categoryRow = self::getCategoryRow($categoryId, true);
        if ($categoryRow !== null) {
            $routePath = EntitySlugService::normalizeRoutePath((string) ($categoryRow['route_path'] ?? ''));
            if ($routePath !== '') {
                return $routePath;
            }
        }

        $segments = self::getCategoryPathSegments($categoryId);
        return $segments === [] ? '' : '/' . implode('/', $segments);
    }

    public static function buildPagePath(int $pageId): string {
        $pageRow = self::getPageRow($pageId, true);
        if ($pageRow === null) {
            return '';
        }

        $routePath = EntitySlugService::normalizeRoutePath((string) ($pageRow['route_path'] ?? ''));
        if ($routePath !== '') {
            return $routePath;
        }

        $categoryPath = self::buildCategoryPath((int) ($pageRow['category_id'] ?? 0));
        $pageSlug = trim((string) ($pageRow['slug'] ?? ''));
        if ($categoryPath === '' || $pageSlug === '') {
            return '';
        }

        return rtrim($categoryPath, '/') . '/' . $pageSlug;
    }

    public static function buildHreflangLinks(string $entityType, int $entityId, array $availableLanguageCodes = []): array {
        $entityType = self::normalizeEntityType($entityType);
        if ($entityType === '' || $entityId <= 0) {
            return [];
        }

        $state = EntityTranslationService::getTranslationState($entityType, $entityId, $availableLanguageCodes);
        $translations = (array) ($state['translations'] ?? []);
        if ($translations === []) {
            $currentLanguageCode = self::getEntityLanguageCode($entityType, $entityId);
            $href = self::buildCanonicalEntityUrl($entityType, $entityId, $currentLanguageCode);
            return $href !== '' ? [[
                'language_code' => $currentLanguageCode,
                'hreflang' => strtolower(str_replace('_', '-', ee_get_lang_locale($currentLanguageCode))),
                'href' => $href,
                'entity_id' => $entityId,
                'is_current' => true,
            ]] : [];
        }

        $links = [];
        $defaultHref = '';
        foreach ($translations as $languageCode => $translation) {
            $translatedEntityId = (int) ($translation['entity_id'] ?? 0);
            if ($translatedEntityId <= 0) {
                continue;
            }

            $href = self::buildCanonicalEntityUrl($entityType, $translatedEntityId, (string) $languageCode);
            if ($href === '') {
                continue;
            }

            $normalizedLanguageCode = self::normalizeLanguageCode((string) $languageCode);
            $links[] = [
                'language_code' => $normalizedLanguageCode,
                'hreflang' => strtolower(str_replace('_', '-', ee_get_lang_locale($normalizedLanguageCode))),
                'href' => $href,
                'entity_id' => $translatedEntityId,
                'is_current' => $translatedEntityId === $entityId,
            ];

            if ($normalizedLanguageCode === self::getPrimaryLanguageCode($entityType, $entityId)) {
                $defaultHref = $href;
            }
        }

        if ($defaultHref === '' && !empty($links[0]['href'])) {
            $defaultHref = (string) $links[0]['href'];
        }
        if ($defaultHref !== '') {
            $links[] = [
                'language_code' => 'X-DEFAULT',
                'hreflang' => 'x-default',
                'href' => $defaultHref,
                'entity_id' => 0,
                'is_current' => false,
            ];
        }

        return $links;
    }

    public static function getEntityViewPayload(string $entityType, int $entityId, ?string $languageCode = null): ?array {
        $entityType = self::normalizeEntityType($entityType);
        if ($entityType === '' || $entityId <= 0) {
            return null;
        }

        $resolvedEntityId = self::resolveEntityIdForLanguage($entityType, $entityId, $languageCode);
        if ($resolvedEntityId <= 0) {
            $resolvedEntityId = $entityId;
        }

        $row = $entityType === 'category'
            ? self::getCategoryRow($resolvedEntityId, true)
            : self::getPageRow($resolvedEntityId, true);
        if ($row === null) {
            return null;
        }

        $effectiveLanguageCode = self::normalizeLanguageCode((string) ($row['language_code'] ?? $languageCode ?? ''));
        $canonicalUrl = self::buildCanonicalEntityUrl($entityType, $resolvedEntityId, $effectiveLanguageCode);
        $breadcrumbs = $entityType === 'category'
            ? self::buildCategoryBreadcrumbs((int) ($row['category_id'] ?? 0))
            : self::buildPageBreadcrumbs((int) ($row['page_id'] ?? 0));

        $descriptionHtml = trim((string) ($row['description'] ?? ''));
        $shortDescription = trim((string) ($row['short_description'] ?? ''));
        $plainText = trim(strip_tags($shortDescription . ' ' . $descriptionHtml));

        return [
            'entity_type' => $entityType,
            'entity_id' => $resolvedEntityId,
            'language_code' => $effectiveLanguageCode,
            'title' => trim((string) ($row['title'] ?? '')),
            'slug' => trim((string) ($row['slug'] ?? '')),
            'route_path' => EntitySlugService::normalizeRoutePath((string) ($row['route_path'] ?? '')),
            'short_description' => $shortDescription,
            'description_html' => $descriptionHtml,
            'plain_text' => $plainText,
            'status' => (string) ($row['status'] ?? ''),
            'public_path' => self::buildEntityPath($entityType, $resolvedEntityId, $effectiveLanguageCode),
            'public_url' => self::buildEntityUrl($entityType, $resolvedEntityId, $effectiveLanguageCode),
            'canonical_url' => $canonicalUrl,
            'alternate_links' => self::buildHreflangLinks($entityType, $resolvedEntityId),
            'breadcrumbs' => $breadcrumbs,
            'meta_title' => trim((string) ($row['title'] ?? '')),
            'meta_description' => $shortDescription !== '' ? $shortDescription : SysClass::truncateString($plainText, 180),
        ];
    }

    public static function resolvePath(string $routePath, ?string $preferredLanguageCode = null): ?array {
        $normalizedPath = self::normalizePath($routePath);
        if ($normalizedPath === '') {
            return null;
        }

        $cacheKey = md5($normalizedPath . '|' . implode(',', self::getLanguageCandidates($preferredLanguageCode)));
        if (array_key_exists($cacheKey, self::$resolvedRouteCache)) {
            return self::$resolvedRouteCache[$cacheKey];
        }

        foreach (self::getLanguageCandidates($preferredLanguageCode) as $languageCode) {
            $pageMatch = self::resolvePagePath($normalizedPath, $languageCode);
            if ($pageMatch !== null) {
                return self::$resolvedRouteCache[$cacheKey] = $pageMatch;
            }

            $categoryMatch = self::resolveCategoryPath($normalizedPath, $languageCode);
            if ($categoryMatch !== null) {
                return self::$resolvedRouteCache[$cacheKey] = $categoryMatch;
            }
        }

        return self::$resolvedRouteCache[$cacheKey] = null;
    }

    public static function getRouteCacheContextKey(): string {
        $explicitLanguageCode = self::normalizeLanguageCode((string) ($_GET['sl'] ?? ''));
        if ($explicitLanguageCode !== '') {
            return 'sl:' . $explicitLanguageCode;
        }

        return 'lang:' . self::resolveRequestLanguageCode();
    }

    private static function buildCategoryBreadcrumbs(int $categoryId): array {
        $segments = [];
        $visited = [];
        $currentId = $categoryId;

        while ($currentId > 0 && !isset($visited[$currentId])) {
            $visited[$currentId] = true;
            $row = self::getCategoryRow($currentId, true);
            if ($row === null) {
                break;
            }

            $segments[] = [
                'entity_type' => 'category',
                'entity_id' => (int) ($row['category_id'] ?? 0),
                'title' => trim((string) ($row['title'] ?? '')),
                'url' => self::buildCanonicalEntityUrl('category', (int) ($row['category_id'] ?? 0), (string) ($row['language_code'] ?? '')),
            ];
            $currentId = (int) ($row['parent_id'] ?? 0);
        }

        return array_reverse($segments);
    }

    private static function buildPageBreadcrumbs(int $pageId): array {
        $pageRow = self::getPageRow($pageId, true);
        if ($pageRow === null) {
            return [];
        }

        $breadcrumbs = self::buildCategoryBreadcrumbs((int) ($pageRow['category_id'] ?? 0));
        $breadcrumbs[] = [
            'entity_type' => 'page',
            'entity_id' => (int) ($pageRow['page_id'] ?? 0),
            'title' => trim((string) ($pageRow['title'] ?? '')),
            'url' => self::buildCanonicalEntityUrl('page', (int) ($pageRow['page_id'] ?? 0), (string) ($pageRow['language_code'] ?? '')),
        ];

        return $breadcrumbs;
    }

    private static function resolvePagePath(string $normalizedPath, string $languageCode): ?array {
        $directPageRow = self::getPageRowByRoutePath('/' . $normalizedPath, $languageCode);
        if ($directPageRow !== null) {
            return [
                'entity_type' => 'page',
                'entity_id' => (int) ($directPageRow['page_id'] ?? 0),
                'language_code' => self::normalizeLanguageCode((string) ($directPageRow['language_code'] ?? $languageCode)),
                'path' => '/' . $normalizedPath,
                'url' => self::buildCanonicalEntityUrl('page', (int) ($directPageRow['page_id'] ?? 0), (string) ($directPageRow['language_code'] ?? $languageCode)),
                'row' => $directPageRow,
            ];
        }

        $segments = array_values(array_filter(explode('/', $normalizedPath), static fn(string $segment): bool => $segment !== ''));
        if (count($segments) < 2) {
            return null;
        }

        $pageSlug = (string) array_pop($segments);
        $categoryRow = self::resolveCategoryRowBySegments($segments, $languageCode);
        if ($categoryRow === null) {
            return null;
        }

        $categoryId = (int) ($categoryRow['category_id'] ?? 0);
        if ($categoryId <= 0) {
            return null;
        }

        $pageRow = self::getPageRowByCategoryAndSlug($categoryId, $pageSlug, $languageCode);
        if ($pageRow === null) {
            return null;
        }

        return [
            'entity_type' => 'page',
            'entity_id' => (int) ($pageRow['page_id'] ?? 0),
            'language_code' => self::normalizeLanguageCode((string) ($pageRow['language_code'] ?? $languageCode)),
            'path' => '/' . $normalizedPath,
            'url' => self::buildCanonicalEntityUrl('page', (int) ($pageRow['page_id'] ?? 0), (string) ($pageRow['language_code'] ?? $languageCode)),
            'row' => $pageRow,
        ];
    }

    private static function resolveCategoryPath(string $normalizedPath, string $languageCode): ?array {
        $directCategoryRow = self::getCategoryRowByRoutePath('/' . $normalizedPath, $languageCode);
        if ($directCategoryRow !== null) {
            return [
                'entity_type' => 'category',
                'entity_id' => (int) ($directCategoryRow['category_id'] ?? 0),
                'language_code' => self::normalizeLanguageCode((string) ($directCategoryRow['language_code'] ?? $languageCode)),
                'path' => '/' . $normalizedPath,
                'url' => self::buildCanonicalEntityUrl('category', (int) ($directCategoryRow['category_id'] ?? 0), (string) ($directCategoryRow['language_code'] ?? $languageCode)),
                'row' => $directCategoryRow,
            ];
        }

        $segments = array_values(array_filter(explode('/', $normalizedPath), static fn(string $segment): bool => $segment !== ''));
        if ($segments === []) {
            return null;
        }

        $categoryRow = self::resolveCategoryRowBySegments($segments, $languageCode);
        if ($categoryRow === null) {
            return null;
        }

        return [
            'entity_type' => 'category',
            'entity_id' => (int) ($categoryRow['category_id'] ?? 0),
            'language_code' => self::normalizeLanguageCode((string) ($categoryRow['language_code'] ?? $languageCode)),
            'path' => '/' . $normalizedPath,
            'url' => self::buildCanonicalEntityUrl('category', (int) ($categoryRow['category_id'] ?? 0), (string) ($categoryRow['language_code'] ?? $languageCode)),
            'row' => $categoryRow,
        ];
    }

    private static function resolveCategoryRowBySegments(array $segments, string $languageCode): ?array {
        $segments = array_values(array_filter(array_map(static fn($segment): string => trim((string) $segment), $segments)));
        if ($segments === []) {
            return null;
        }

        $currentRow = null;
        foreach ($segments as $index => $segment) {
            $parentId = $currentRow ? (int) ($currentRow['category_id'] ?? 0) : 0;
            $currentRow = self::getCategoryRowByParentAndSlug($parentId, $segment, $languageCode);
            if ($currentRow === null) {
                return null;
            }
        }

        return $currentRow;
    }

    private static function getCategoryPathSegments(int $categoryId): array {
        if ($categoryId <= 0) {
            return [];
        }

        $cacheKey = $categoryId;
        if (isset(self::$categoryPathSegmentsCache[$cacheKey])) {
            return self::$categoryPathSegmentsCache[$cacheKey];
        }

        $segments = [];
        $visited = [];
        $currentId = $categoryId;
        while ($currentId > 0 && !isset($visited[$currentId])) {
            $visited[$currentId] = true;
            $row = self::getCategoryRow($currentId, true);
            if ($row === null) {
                break;
            }

            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug !== '') {
                array_unshift($segments, $slug);
            }

            $currentId = (int) ($row['parent_id'] ?? 0);
        }

        return self::$categoryPathSegmentsCache[$cacheKey] = $segments;
    }

    private static function getCategoryRow(int $categoryId, bool $activeOnly = false): ?array {
        if ($categoryId <= 0) {
            return null;
        }

        $cacheKey = ($activeOnly ? 'active:' : 'all:') . $categoryId;
        if (array_key_exists($cacheKey, self::$categoryRowCache)) {
            return self::$categoryRowCache[$cacheKey];
        }

        $statusSql = $activeOnly ? ' AND status = ?s' : '';
        $params = [Constants::CATEGORIES_TABLE, $categoryId];
        if ($activeOnly) {
            $params[] = 'active';
        }

        $row = SafeMySQL::gi()->getRow(
            "SELECT category_id, type_id, title, slug, route_path, short_description, description, parent_id, status, language_code
             FROM ?n
             WHERE category_id = ?i{$statusSql}
             LIMIT 1",
            ...$params
        );

        return self::$categoryRowCache[$cacheKey] = (is_array($row) && $row !== [] ? $row : null);
    }

    private static function getPageRow(int $pageId, bool $activeOnly = false): ?array {
        if ($pageId <= 0) {
            return null;
        }

        $cacheKey = ($activeOnly ? 'active:' : 'all:') . $pageId;
        if (array_key_exists($cacheKey, self::$pageRowCache)) {
            return self::$pageRowCache[$cacheKey];
        }

        $statusSql = $activeOnly ? ' AND status = ?s' : '';
        $params = [Constants::PAGES_TABLE, $pageId];
        if ($activeOnly) {
            $params[] = 'active';
        }

        $row = SafeMySQL::gi()->getRow(
            "SELECT page_id, parent_page_id, category_id, status, title, slug, route_path, short_description, description, language_code
             FROM ?n
             WHERE page_id = ?i{$statusSql}
             LIMIT 1",
            ...$params
        );

        return self::$pageRowCache[$cacheKey] = (is_array($row) && $row !== [] ? $row : null);
    }

    private static function getCategoryRowByParentAndSlug(int $parentId, string $slug, string $languageCode): ?array {
        $slug = trim($slug);
        $languageCode = self::normalizeLanguageCode($languageCode);
        if ($slug === '' || $languageCode === '') {
            return null;
        }

        $cacheKey = $parentId . '|' . $languageCode . '|' . $slug;
        if (array_key_exists($cacheKey, self::$resolvedCategorySlugCache)) {
            return self::$resolvedCategorySlugCache[$cacheKey];
        }

        if ($parentId > 0) {
            $row = SafeMySQL::gi()->getRow(
                "SELECT category_id, type_id, title, slug, route_path, short_description, description, parent_id, status, language_code
                 FROM ?n
                 WHERE parent_id = ?i AND language_code = ?s AND slug = ?s AND status = ?s
                 LIMIT 1",
                Constants::CATEGORIES_TABLE,
                $parentId,
                $languageCode,
                $slug,
                'active'
            );
        } else {
            $row = SafeMySQL::gi()->getRow(
                "SELECT category_id, type_id, title, slug, route_path, short_description, description, parent_id, status, language_code
                 FROM ?n
                 WHERE (parent_id IS NULL OR parent_id = 0) AND language_code = ?s AND slug = ?s AND status = ?s
                 LIMIT 1",
                Constants::CATEGORIES_TABLE,
                $languageCode,
                $slug,
                'active'
            );
        }

        return self::$resolvedCategorySlugCache[$cacheKey] = (is_array($row) && $row !== [] ? $row : null);
    }

    private static function getPageRowByCategoryAndSlug(int $categoryId, string $slug, string $languageCode): ?array {
        $slug = trim($slug);
        $languageCode = self::normalizeLanguageCode($languageCode);
        if ($categoryId <= 0 || $slug === '' || $languageCode === '') {
            return null;
        }

        $cacheKey = $categoryId . '|' . $languageCode . '|' . $slug;
        if (array_key_exists($cacheKey, self::$resolvedPageSlugCache)) {
            return self::$resolvedPageSlugCache[$cacheKey];
        }

        $row = SafeMySQL::gi()->getRow(
            "SELECT page_id, parent_page_id, category_id, status, title, slug, route_path, short_description, description, language_code
             FROM ?n
             WHERE category_id = ?i AND language_code = ?s AND slug = ?s AND status = ?s
             LIMIT 1",
            Constants::PAGES_TABLE,
            $categoryId,
            $languageCode,
            $slug,
            'active'
        );

        return self::$resolvedPageSlugCache[$cacheKey] = (is_array($row) && $row !== [] ? $row : null);
    }

    private static function getCategoryRowByRoutePath(string $routePath, string $languageCode): ?array {
        $routePath = EntitySlugService::normalizeRoutePath($routePath);
        $languageCode = self::normalizeLanguageCode($languageCode);
        if ($routePath === '' || $languageCode === '') {
            return null;
        }

        $cacheKey = $languageCode . '|' . $routePath;
        if (array_key_exists($cacheKey, self::$resolvedCategoryRoutePathCache)) {
            return self::$resolvedCategoryRoutePathCache[$cacheKey];
        }

        $row = SafeMySQL::gi()->getRow(
            "SELECT category_id, type_id, title, slug, route_path, short_description, description, parent_id, status, language_code
             FROM ?n
             WHERE language_code = ?s AND route_path = ?s AND status = ?s
             LIMIT 1",
            Constants::CATEGORIES_TABLE,
            $languageCode,
            $routePath,
            'active'
        );

        return self::$resolvedCategoryRoutePathCache[$cacheKey] = (is_array($row) && $row !== [] ? $row : null);
    }

    private static function getPageRowByRoutePath(string $routePath, string $languageCode): ?array {
        $routePath = EntitySlugService::normalizeRoutePath($routePath);
        $languageCode = self::normalizeLanguageCode($languageCode);
        if ($routePath === '' || $languageCode === '') {
            return null;
        }

        $cacheKey = $languageCode . '|' . $routePath;
        if (array_key_exists($cacheKey, self::$resolvedPageRoutePathCache)) {
            return self::$resolvedPageRoutePathCache[$cacheKey];
        }

        $row = SafeMySQL::gi()->getRow(
            "SELECT page_id, parent_page_id, category_id, status, title, slug, route_path, short_description, description, language_code
             FROM ?n
             WHERE language_code = ?s AND route_path = ?s AND status = ?s
             LIMIT 1",
            Constants::PAGES_TABLE,
            $languageCode,
            $routePath,
            'active'
        );

        return self::$resolvedPageRoutePathCache[$cacheKey] = (is_array($row) && $row !== [] ? $row : null);
    }

    private static function resolveEntityIdForLanguage(string $entityType, int $entityId, ?string $languageCode = null): int {
        $languageCode = self::normalizeLanguageCode((string) $languageCode);
        if ($entityId <= 0 || $languageCode === '') {
            return $entityId;
        }

        $entityLanguageCode = self::getEntityLanguageCode($entityType, $entityId);
        if ($entityLanguageCode === '' || $entityLanguageCode === $languageCode) {
            return $entityId;
        }

        $translatedEntityId = EntityTranslationService::getTranslatedEntityId($entityType, $entityId, $languageCode);
        return $translatedEntityId ?? $entityId;
    }

    private static function getEntityLanguageCode(string $entityType, int $entityId): string {
        if ($entityType === 'page') {
            return self::normalizeLanguageCode((string) (self::getPageRow($entityId, false)['language_code'] ?? ''));
        }
        return self::normalizeLanguageCode((string) (self::getCategoryRow($entityId, false)['language_code'] ?? ''));
    }

    private static function getLanguageCandidates(?string $preferredLanguageCode = null): array {
        $explicitLanguageCode = self::normalizeLanguageCode((string) ($_GET['sl'] ?? ''));
        if ($explicitLanguageCode !== '') {
            return [$explicitLanguageCode];
        }

        $candidates = [];
        if ($preferredLanguageCode !== null) {
            $candidates[] = $preferredLanguageCode;
        }
        $candidates[] = self::resolveRequestLanguageCode();
        $candidates[] = self::getFallbackLanguageCode();
        $candidates[] = ee_get_proto_language_seed();
        foreach (ee_get_content_lang_codes() as $availableLanguageCode) {
            $candidates[] = $availableLanguageCode;
        }

        $normalized = [];
        foreach ($candidates as $candidate) {
            $candidate = self::normalizeLanguageCode((string) $candidate);
            if ($candidate !== '' && !in_array($candidate, $normalized, true)) {
                $normalized[] = $candidate;
            }
        }

        return $normalized !== [] ? $normalized : [self::getFallbackLanguageCode()];
    }

    private static function resolveRequestLanguageCode(): string {
        return self::normalizeLanguageCode(ee_get_current_lang_code());
    }

    private static function shouldAppendLanguageQuery(string $entityType, int $entityId, string $languageCode): bool {
        $languageCode = self::normalizeLanguageCode($languageCode);
        if ($languageCode === '') {
            return false;
        }
        return $languageCode !== self::getPrimaryLanguageCode($entityType, $entityId);
    }

    private static function getPrimaryLanguageCode(string $entityType, int $entityId): string {
        $state = EntityTranslationService::getTranslationState($entityType, $entityId);
        $translations = (array) ($state['translations'] ?? []);
        foreach ($translations as $translation) {
            if (is_array($translation) && !empty($translation['is_primary'])) {
                return self::normalizeLanguageCode((string) ($translation['language_code'] ?? ''));
            }
        }

        $currentLanguageCode = self::getEntityLanguageCode($entityType, $entityId);
        return $currentLanguageCode !== '' ? $currentLanguageCode : self::getFallbackLanguageCode();
    }

    private static function getFallbackLanguageCode(): string {
        return ee_get_default_content_lang_code();
    }

    private static function normalizeEntityType(string $entityType): string {
        $entityType = strtolower(trim($entityType));
        return in_array($entityType, ['page', 'category'], true) ? $entityType : '';
    }

    private static function normalizePath(string $routePath): string {
        $routePath = trim((string) parse_url($routePath, PHP_URL_PATH), '/');
        $routePath = preg_replace('~/{2,}~', '/', $routePath) ?? $routePath;
        return trim($routePath, '/');
    }

    private static function normalizeLanguageCode(string $languageCode): string {
        return ee_normalize_lang_code($languageCode);
    }
}
