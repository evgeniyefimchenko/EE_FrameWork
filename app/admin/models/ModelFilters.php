<?php

use classes\system\Constants;
use classes\plugins\SafeMySQL;

/**
 * ModelFilters
 * Работа с materialized-слоем фильтров ee_filters.
 */
class ModelFilters {

    /**
     * Возвращает ID активных страниц для указанных категорий в рамках языка.
     *
     * @param array $categoryIds
     * @param string $langCode
     * @param array|string $statuses
     * @return array<int>
     */
    public function getPageIdsForCategories(
        array $categoryIds,
        string $langCode = ENV_DEF_LANG,
        array|string $statuses = ['active']
    ): array {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds), static fn(int $id): bool => $id > 0)));
        if (empty($categoryIds)) {
            return [];
        }

        $statuses = $this->normalizeStatuses($statuses);
        $sql = 'SELECT page_id
                FROM ?n
                WHERE category_id IN (?a)
                  AND language_code = ?s
                  AND status IN (?a)';

        return array_map('intval', SafeMySQL::gi()->getCol(
            $sql,
            Constants::PAGES_TABLE,
            $categoryIds,
            $langCode,
            $statuses
        ));
    }

    /**
     * Возвращает карту страниц к категориям.
     *
     * @param array $pageIds
     * @param string $langCode
     * @param array|string $statuses
     * @return array<int, int>
     */
    public function getPageCategoryMap(
        array $pageIds,
        string $langCode = ENV_DEF_LANG,
        array|string $statuses = ['active']
    ): array {
        $pageIds = array_values(array_unique(array_filter(array_map('intval', $pageIds), static fn(int $id): bool => $id > 0)));
        if (empty($pageIds)) {
            return [];
        }

        $statuses = $this->normalizeStatuses($statuses);
        $rows = SafeMySQL::gi()->getAll(
            'SELECT page_id, category_id
             FROM ?n
             WHERE page_id IN (?a)
               AND language_code = ?s
               AND status IN (?a)',
            Constants::PAGES_TABLE,
            $pageIds,
            $langCode,
            $statuses
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['page_id']] = (int) $row['category_id'];
        }
        return $map;
    }

    /**
     * Возвращает все данные, нужные FilterService для расчёта фильтров и runtime-оценки.
     *
     * @param array $pageIds
     * @param string $langCode
     * @param array|string $statuses
     * @return array
     */
    public function getFilterSourceForPages(
        array $pageIds,
        string $langCode = ENV_DEF_LANG,
        array|string $statuses = ['active'],
        array $propertyIds = []
    ): array {
        $pageIds = array_values(array_unique(array_filter(array_map('intval', $pageIds), static fn(int $id): bool => $id > 0)));
        if (empty($pageIds)) {
            return [];
        }
        $propertyIds = array_values(array_unique(array_filter(array_map('intval', $propertyIds), static fn(int $id): bool => $id > 0)));

        $statuses = $this->normalizeStatuses($statuses);
        $sql = 'SELECT
                pv.value_id,
                pv.entity_id AS page_id,
                pg.category_id,
                pv.property_id,
                pv.language_code,
                pv.property_values,
                p.name AS property_name,
                p.entity_type AS property_entity_type,
                p.is_multiple AS property_multiple,
                p.is_required AS property_required,
                p.default_values,
                pt.fields AS type_fields
             FROM ?n AS pv
             INNER JOIN ?n AS pg
                ON pg.page_id = pv.entity_id
               AND pg.language_code = pv.language_code
             INNER JOIN ?n AS p
                ON p.property_id = pv.property_id
               AND p.language_code = pv.language_code
             LEFT JOIN ?n AS pt
                ON pt.type_id = p.type_id
               AND pt.language_code = p.language_code
             WHERE pv.entity_type = ?s
               AND pv.entity_id IN (?a)
               AND pv.language_code = ?s
               AND pg.status IN (?a)
               AND p.status = ?s';
        $params = [
            Constants::PROPERTY_VALUES_TABLE,
            Constants::PAGES_TABLE,
            Constants::PROPERTIES_TABLE,
            Constants::PROPERTY_TYPES_TABLE,
            'page',
            $pageIds,
            $langCode,
            $statuses,
            'active',
        ];
        if ($propertyIds !== []) {
            $sql .= ' AND pv.property_id IN (?a)';
            $params[] = $propertyIds;
        }
        $sql .= ' ORDER BY pv.entity_id ASC, pv.property_id ASC';

        return SafeMySQL::gi()->getAll($sql, ...$params);
    }

    /**
     * Полностью заменяет materialized-фильтры сущности.
     */
    public function replaceFiltersForEntity(
        string $entityType,
        int $entityId,
        array $filtersData,
        string $langCode = ENV_DEF_LANG
    ): void {
        SafeMySQL::gi()->query('START TRANSACTION');
        try {
            $this->clearFilters($entityType, $entityId, $langCode);
            if (!empty($filtersData)) {
                $values = [];
                foreach ($filtersData as $propertyId => $payload) {
                    $jsonOptions = json_encode($payload, JSON_UNESCAPED_UNICODE);
                    if ($jsonOptions === false) {
                        continue;
                    }
                    $values[] = SafeMySQL::gi()->parse(
                        '(?s, ?i, ?i, ?s, ?s)',
                        $entityType,
                        $entityId,
                        (int) $propertyId,
                        $jsonOptions,
                        $langCode
                    );
                }

                if (!empty($values)) {
                    $sql = 'INSERT INTO ?n (entity_type, entity_id, property_id, filter_options, language_code) VALUES ' . implode(', ', $values);
                    SafeMySQL::gi()->query($sql, Constants::FILTERS_TABLE);
                }
            }
            SafeMySQL::gi()->query('COMMIT');
        } catch (\Throwable $e) {
            SafeMySQL::gi()->query('ROLLBACK');
            throw $e;
        }
    }

    public function clearFilters(string $entityType, int $entityId, string $langCode = ENV_DEF_LANG): void {
        SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE entity_type = ?s AND entity_id = ?i AND language_code = ?s',
            Constants::FILTERS_TABLE,
            $entityType,
            $entityId,
            $langCode
        );
    }

    /**
     * Сводка materialized-фильтров для admin-панели.
     */
    public function getExistingFiltersSummary(string $langCode = ENV_DEF_LANG, string $entityType = 'category'): array {
        $sql = 'SELECT
                    f.entity_id,
                    c.title AS entity_name,
                    COUNT(f.property_id) AS filters_count,
                    MAX(f.recalculated_at) AS last_recalculation
                FROM ?n f
                JOIN ?n c
                  ON f.entity_id = c.category_id
                 AND f.entity_type = ?s
                 AND c.language_code = ?s
                WHERE f.language_code = ?s
                GROUP BY f.entity_id, c.title
                ORDER BY c.title ASC';

        return SafeMySQL::gi()->getAll(
            $sql,
            Constants::FILTERS_TABLE,
            Constants::CATEGORIES_TABLE,
            $entityType,
            $langCode,
            $langCode
        );
    }

    /**
     * Возвращает материализованные фильтры сущности как есть, без декодирования.
     */
    public function getFiltersForEntity(
        int $entityId,
        string $langCode = ENV_DEF_LANG,
        string $entityType = 'category'
    ): array {
        $sql = 'SELECT
                    p.name AS property_name,
                    f.property_id,
                    f.filter_options
                FROM ?n f
                JOIN ?n p
                  ON f.property_id = p.property_id
                 AND p.language_code = f.language_code
                WHERE f.entity_type = ?s
                  AND f.entity_id = ?i
                  AND f.language_code = ?s
                ORDER BY p.name ASC, f.property_id ASC';

        return SafeMySQL::gi()->getAll(
            $sql,
            Constants::FILTERS_TABLE,
            Constants::PROPERTIES_TABLE,
            $entityType,
            $entityId,
            $langCode
        );
    }

    /**
     * Возвращает materialized-фильтры сущности в декодированном виде.
     *
     * @return array<int, array>
     */
    public function getDecodedFiltersForEntity(
        int $entityId,
        string $langCode = ENV_DEF_LANG,
        string $entityType = 'category'
    ): array {
        $rows = $this->getFiltersForEntity($entityId, $langCode, $entityType);
        $decoded = [];
        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['filter_options'] ?? ''), true);
            if (!is_array($payload)) {
                continue;
            }
            $decoded[] = $payload;
        }
        return $decoded;
    }

    private function normalizeStatuses(array|string $statuses): array {
        if (is_string($statuses) && trim($statuses) !== '') {
            $statuses = [trim($statuses)];
        }
        if (!is_array($statuses) || empty($statuses)) {
            return ['active'];
        }
        $statuses = array_values(array_unique(array_filter(array_map(
            static fn($status): string => trim((string) $status),
            $statuses
        ), static fn(string $status): bool => $status !== '')));
        return $statuses === [] ? ['active'] : $statuses;
    }
}
