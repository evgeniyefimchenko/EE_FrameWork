<?php

use classes\system\Constants;
use classes\plugins\SafeMySQL;

/**
 * ModelFilters
 * Модель для прямого взаимодействия с таблицей фильтров ee_filters
 * Предоставляет данные для сервиса расчетов и сохраняет результат
 */
class ModelFilters {

    public function getPageIdsForCategories(array $categoryIds): array {
        if (empty($categoryIds)) {
            return [];
        }
        $sql = "SELECT page_id FROM ?n WHERE category_id IN (?a)";
        return SafeMySQL::gi()->getCol($sql, Constants::PAGES_TABLE, $categoryIds);
    }

    /**
     * Получает сырые данные о значениях свойств для указанных страниц
     * @param array $pageIds Массив ID страниц
     * @return array
     */
    public function getRawPropertyDataForPages(array $pageIds): array {
        if (empty($pageIds)) {
            return [];
        }

        $sql = "SELECT 
                    pv.property_id, 
                    pv.property_values AS value_json, 
                    pt.name AS property_type, 
                    p.name AS property_name, 
                    p.is_multiple AS property_multiple 
                FROM ?n pv
                JOIN ?n p ON pv.property_id = p.property_id
                LEFT JOIN ?n pt ON p.type_id = pt.type_id
                WHERE pv.entity_type = 'page' AND pv.entity_id IN (?a)";

        return SafeMySQL::gi()->getAll(
                        $sql,
                        Constants::PROPERTY_VALUES_TABLE,
                        Constants::PROPERTIES_TABLE,
                        Constants::PROPERTY_TYPES_TABLE,
                        $pageIds
                );
    }

    public function replaceFiltersForEntity(string $entityType, int $entityId, array $filtersData, string $langCode = 'RU'): void {
        $this->clearFilters($entityType, $entityId, $langCode);
        if (empty($filtersData)) {
            return;
        }
        $sql = "INSERT INTO ?n (entity_type, entity_id, property_id, filter_options, language_code) VALUES";
        $values = [];
        foreach ($filtersData as $propertyId => $options) {
            $jsonOptions = json_encode($options, JSON_UNESCAPED_UNICODE);
            $values[] = SafeMySQL::gi()->parse("(?s, ?i, ?i, ?s, ?s)", $entityType, $entityId, $propertyId, $jsonOptions, $langCode);
        }
        $sql .= implode(', ', $values);
        SafeMySQL::gi()->query($sql, Constants::FILTERS_TABLE);
    }

    public function clearFilters(string $entityType, int $entityId, string $langCode = 'RU'): void {
        $sql = "DELETE FROM ?n WHERE entity_type = ?s AND entity_id = ?i AND language_code = ?s";
        SafeMySQL::gi()->query($sql, Constants::FILTERS_TABLE, $entityType, $entityId, $langCode);
    }

    /**
     * Получает сводную информацию о существующих фильтрах
     * @param string $langCode Код языка
     * @return array
     */
    public function getExistingFiltersSummary(string $langCode = 'RU'): array {
        $sql = "SELECT 
                    f.entity_id, 
                    c.title AS entity_name,
                    COUNT(f.property_id) AS filters_count,
                    MAX(f.recalculated_at) AS last_recalculation
                FROM ?n f
                JOIN ?n c ON f.entity_id = c.category_id AND f.entity_type = 'category'
                WHERE f.language_code = ?s AND c.language_code = ?s
                GROUP BY f.entity_id, c.title
                ORDER BY c.title ASC";
        return SafeMySQL::gi()->getAll(
                        $sql,
                        Constants::FILTERS_TABLE,
                        Constants::CATEGORIES_TABLE,
                        $langCode,
                        $langCode
                );
    }

    /**
     * Получает детальную информацию о фильтрах для одной сущности (категории)
     * @param int $entityId ID категории
     * @param string $langCode Код языка
     * @return array
     */
    public function getFiltersForEntity(int $entityId, string $langCode = 'RU'): array {
        $sql = "SELECT 
                    p.name AS property_name,
                    f.filter_options
                FROM ?n f
                JOIN ?n p ON f.property_id = p.property_id
                WHERE f.entity_type = 'category' AND f.entity_id = ?i AND f.language_code = ?s";

        return SafeMySQL::gi()->getAll(
                        $sql,
                        Constants::FILTERS_TABLE,
                        Constants::PROPERTIES_TABLE,
                        $entityId,
                        $langCode
                );
    }
}
