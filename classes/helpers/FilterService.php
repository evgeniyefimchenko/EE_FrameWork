<?php

namespace classes\helpers;

use classes\system\PropertyFieldContract;
use classes\system\SysClass;

/**
 * FilterService
 * Материализует доступные фильтры по дереву категорий и
 * умеет вычислять page_id по выбранным фильтрам.
 */
class FilterService {

    private const OPTION_TYPES = ['select', 'checkbox', 'radio'];
    private const RANGE_TYPES = ['number', 'date', 'time', 'datetime-local'];
    private const SUPPORTED_FILTER_TYPES = ['select', 'checkbox', 'radio', 'number', 'date', 'time', 'datetime-local'];

    private \ModelFilters $modelFilters;
    private \ModelCategories $modelCategories;

    public function __construct() {
        $this->modelFilters = SysClass::getModelObject('admin', 'm_filters');
        $this->modelCategories = SysClass::getModelObject('admin', 'm_categories');
        if (!$this->modelFilters || !$this->modelCategories) {
            throw new \Exception('Не удалось загрузить одну из моделей: ModelFilters или ModelCategories.');
        }
    }

    /**
     * Пересчитывает materialized-фильтры сущности.
     */
    public function regenerateFiltersForEntity(
        string $entityType,
        int $entityId,
        string $languageCode = ENV_DEF_LANG,
        array|string $statuses = ['active']
    ): array {
        $entityType = $this->normalizeEntityType($entityType);
        if ($entityId <= 0 || $entityType === null) {
            return ['status' => 'error', 'message' => 'Передан неверный тип сущности или entity_id'];
        }

        $pageIds = $this->resolvePageIdsForEntity($entityType, $entityId, $languageCode, $statuses);
        if (empty($pageIds)) {
            $this->modelFilters->clearFilters($entityType, $entityId, $languageCode);
            return [
                'status' => 'success',
                'message' => 'Страницы для анализа не найдены, фильтры для ' . $entityType . ' #' . $entityId . ' очищены',
            ];
        }

        $aggregatedFilters = $this->buildMaterializedFiltersForPageIds($pageIds, $languageCode, $statuses);
        if (empty($aggregatedFilters)) {
            $this->modelFilters->clearFilters($entityType, $entityId, $languageCode);
            return [
                'status' => 'success',
                'message' => 'Не найдено подходящих свойств для создания фильтров для ' . $entityType . ' #' . $entityId,
            ];
        }

        $this->modelFilters->replaceFiltersForEntity($entityType, $entityId, $aggregatedFilters, $languageCode);
        return [
            'status' => 'success',
            'message' => 'Фильтры для ' . $entityType . ' #' . $entityId . ' успешно пересчитаны',
            'filters_count' => count($aggregatedFilters),
            'page_count' => count($pageIds),
        ];
    }

    /**
     * Батчевый пересчёт категорий.
     *
     * @return array<int, array<string, mixed>>
     */
    public function regenerateFiltersForCategories(
        array $categoryIds,
        string $languageCode = ENV_DEF_LANG,
        array|string $statuses = ['active']
    ): array {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds), static fn(int $id): bool => $id > 0)));
        $result = [];
        foreach ($categoryIds as $categoryId) {
            $result[$categoryId] = $this->regenerateFiltersForEntity('category', $categoryId, $languageCode, $statuses);
        }
        return $result;
    }

    /**
     * Возвращает materialized-фильтры категории в frontend-ready виде.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableFiltersForCategory(
        int $categoryId,
        string $languageCode = ENV_DEF_LANG,
        bool $ensureFresh = false,
        array|string $statuses = ['active']
    ): array {
        return $this->getAvailableFiltersForEntity('category', $categoryId, $languageCode, $ensureFresh, $statuses);
    }

    /**
     * Возвращает materialized-фильтры сущности в декодированном и совместимом формате.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableFiltersForEntity(
        string $entityType,
        int $entityId,
        string $languageCode = ENV_DEF_LANG,
        bool $ensureFresh = false,
        array|string $statuses = ['active']
    ): array {
        $entityType = $this->normalizeEntityType($entityType);
        if ($entityType === null || $entityId <= 0) {
            return [];
        }

        $filters = $this->modelFilters->getDecodedFiltersForEntity($entityId, $languageCode, $entityType);
        if ($filters === [] && $ensureFresh) {
            $this->regenerateFiltersForEntity($entityType, $entityId, $languageCode, $statuses);
            $filters = $this->modelFilters->getDecodedFiltersForEntity($entityId, $languageCode, $entityType);
        }

        return array_values(array_filter(array_map(
            fn(array $payload): array => $this->normalizeMaterializedPayload($payload),
            $filters
        )));
    }

    /**
     * Возвращает плоский список фильтруемых полей категории.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFlatAvailableFiltersForCategory(
        int $categoryId,
        string $languageCode = ENV_DEF_LANG,
        bool $ensureFresh = false,
        array|string $statuses = ['active']
    ): array {
        $payloads = $this->getAvailableFiltersForCategory($categoryId, $languageCode, $ensureFresh, $statuses);
        $flat = [];
        foreach ($payloads as $payload) {
            $propertyId = (int) ($payload['property_id'] ?? 0);
            $propertyName = (string) ($payload['property_name'] ?? '');
            foreach ((array) ($payload['fields'] ?? []) as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $flat[] = [
                    'property_id' => $propertyId,
                    'property_name' => $propertyName,
                    'uid' => (string) ($field['uid'] ?? ''),
                    'label' => (string) ($field['label'] ?? $propertyName),
                    'type' => (string) ($field['type'] ?? ''),
                    'filter_type' => (string) ($field['filter_type'] ?? ''),
                    'multiple' => !empty($field['multiple']),
                    'options' => $field['options'] ?? [],
                    'min_value' => $field['min_value'] ?? null,
                    'max_value' => $field['max_value'] ?? null,
                    'count' => (int) ($field['count'] ?? 0),
                ];
            }
        }
        return $flat;
    }

    /**
     * Возвращает page_id, удовлетворяющие выбранным фильтрам категории.
     *
     * Поддерживаемые ключи для выбора:
     * - ['property_id' => 10, 'uid' => 'field_uid', 'values' => ['a', 'b']]
     * - ['property_id' => 10, 'uid' => 'field_uid', 'min' => 1, 'max' => 10]
     * - associative key "10:field_uid" => ['values' => [...]] / ['min' => ..., 'max' => ...]
     *
     * @return array<int>
     */
    public function getFilteredPageIdsForCategory(
        int $categoryId,
        array $selectedFilters,
        string $languageCode = ENV_DEF_LANG,
        array|string $statuses = ['active']
    ): array {
        if ($categoryId <= 0) {
            return [];
        }

        $pageIds = $this->resolvePageIdsForEntity('category', $categoryId, $languageCode, $statuses);
        if ($pageIds === []) {
            return [];
        }

        $criteria = $this->normalizeSelectedFilters($selectedFilters);
        if ($criteria === []) {
            return $pageIds;
        }

        $propertyIds = array_values(array_unique(array_filter(array_map(
            static fn(array $criterion): int => (int) ($criterion['property_id'] ?? 0),
            $criteria
        ), static fn(int $propertyId): bool => $propertyId > 0)));
        $sourceRows = $this->modelFilters->getFilterSourceForPages($pageIds, $languageCode, $statuses, $propertyIds);
        $pageRuntimeMap = $this->buildPageRuntimeFilterMap($sourceRows);

        $matched = [];
        foreach ($pageIds as $pageId) {
            if ($this->pageMatchesCriteria($pageRuntimeMap[$pageId] ?? [], $criteria)) {
                $matched[] = (int) $pageId;
            }
        }

        return $matched;
    }

    public function countFilteredPagesForCategory(
        int $categoryId,
        array $selectedFilters,
        string $languageCode = ENV_DEF_LANG,
        array|string $statuses = ['active']
    ): int {
        return count($this->getFilteredPageIdsForCategory($categoryId, $selectedFilters, $languageCode, $statuses));
    }

    /**
     * Строит materialized-фильтры по набору страниц.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildMaterializedFiltersForPageIds(
        array $pageIds,
        string $languageCode = ENV_DEF_LANG,
        array|string $statuses = ['active']
    ): array {
        $sourceRows = $this->modelFilters->getFilterSourceForPages($pageIds, $languageCode, $statuses);
        if ($sourceRows === []) {
            return [];
        }

        $properties = [];
        foreach ($sourceRows as $row) {
            $propertyId = (int) ($row['property_id'] ?? 0);
            if ($propertyId <= 0) {
                continue;
            }

            if (!isset($properties[$propertyId])) {
                $properties[$propertyId] = [
                    'property_id' => $propertyId,
                    'property_name' => (string) ($row['property_name'] ?? ''),
                    'entity_type' => (string) ($row['property_entity_type'] ?? 'page'),
                    'language_code' => $languageCode,
                    'fields' => [],
                ];
            }

            $runtimeFields = PropertyFieldContract::buildRuntimeFields(
                $row['default_values'] ?? [],
                $row['property_values'] ?? [],
                $row['type_fields'] ?? [],
                $row
            );

            foreach ($runtimeFields as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $fieldType = strtolower(trim((string) ($field['type'] ?? '')));
                if (!in_array($fieldType, self::SUPPORTED_FILTER_TYPES, true)) {
                    continue;
                }
                $uid = trim((string) ($field['uid'] ?? ''));
                if ($uid === '') {
                    continue;
                }

                if (!isset($properties[$propertyId]['fields'][$uid])) {
                    $properties[$propertyId]['fields'][$uid] = [
                        'uid' => $uid,
                        'label' => trim((string) ($field['label'] ?? $properties[$propertyId]['property_name'])),
                        'type' => $fieldType,
                        'filter_type' => in_array($fieldType, self::OPTION_TYPES, true) ? 'options' : 'range',
                        'multiple' => !empty($field['multiple']),
                        'options_map' => [],
                        'selected_counts' => [],
                        'range_entries' => [],
                    ];
                }

                if (in_array($fieldType, self::OPTION_TYPES, true)) {
                    foreach ((array) ($field['options'] ?? []) as $option) {
                        if (!is_array($option)) {
                            continue;
                        }
                        $optionId = trim((string) ($option['key'] ?? ''));
                        if ($optionId === '') {
                            continue;
                        }
                        $properties[$propertyId]['fields'][$uid]['options_map'][$optionId] = trim((string) ($option['label'] ?? $optionId));
                    }
                    foreach (array_map('strval', (array) ($field['value'] ?? [])) as $selectedId) {
                        if ($selectedId === '') {
                            continue;
                        }
                        $properties[$propertyId]['fields'][$uid]['selected_counts'][$selectedId] =
                            (int) ($properties[$propertyId]['fields'][$uid]['selected_counts'][$selectedId] ?? 0) + 1;
                    }
                    continue;
                }

                foreach ($this->extractComparableEntries($field) as $entry) {
                    $properties[$propertyId]['fields'][$uid]['range_entries'][] = $entry;
                }
            }
        }

        $materialized = [];
        foreach ($properties as $propertyId => $propertyData) {
            $fields = [];
            foreach ($propertyData['fields'] as $uid => $fieldData) {
                if ($fieldData['filter_type'] === 'options') {
                    $options = [];
                    foreach ($fieldData['options_map'] as $optionId => $label) {
                        $count = (int) ($fieldData['selected_counts'][$optionId] ?? 0);
                        if ($count <= 0) {
                            continue;
                        }
                        $options[] = [
                            'id' => $optionId,
                            'label' => $label,
                            'count' => $count,
                        ];
                    }
                    if ($options === []) {
                        continue;
                    }
                    $fields[] = [
                        'uid' => $uid,
                        'label' => $fieldData['label'],
                        'type' => $fieldData['type'],
                        'filter_type' => 'options',
                        'multiple' => !empty($fieldData['multiple']),
                        'options' => array_values($options),
                    ];
                    continue;
                }

                if ($fieldData['range_entries'] === []) {
                    continue;
                }
                usort($fieldData['range_entries'], static fn(array $a, array $b): int => $a['cmp'] <=> $b['cmp']);
                $first = $fieldData['range_entries'][0];
                $last = $fieldData['range_entries'][count($fieldData['range_entries']) - 1];
                $fields[] = [
                    'uid' => $uid,
                    'label' => $fieldData['label'],
                    'type' => $fieldData['type'],
                    'filter_type' => 'range',
                    'multiple' => !empty($fieldData['multiple']),
                    'min_value' => $first['raw'],
                    'max_value' => $last['raw'],
                    'count' => count($fieldData['range_entries']),
                ];
            }

            if ($fields === []) {
                continue;
            }

            $propertyData['fields'] = array_values($fields);
            $materialized[$propertyId] = $propertyData;
        }

        return $materialized;
    }

    /**
     * Строит карту runtime-фильтров по страницам для page matching.
     *
     * @return array<int, array<string, array<string, mixed>>>
     */
    private function buildPageRuntimeFilterMap(array $sourceRows): array {
        $pageMap = [];
        foreach ($sourceRows as $row) {
            $pageId = (int) ($row['page_id'] ?? 0);
            $propertyId = (int) ($row['property_id'] ?? 0);
            if ($pageId <= 0 || $propertyId <= 0) {
                continue;
            }

            $runtimeFields = PropertyFieldContract::buildRuntimeFields(
                $row['default_values'] ?? [],
                $row['property_values'] ?? [],
                $row['type_fields'] ?? [],
                $row
            );

            foreach ($runtimeFields as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $fieldType = strtolower(trim((string) ($field['type'] ?? '')));
                $uid = trim((string) ($field['uid'] ?? ''));
                if ($uid === '' || !in_array($fieldType, self::SUPPORTED_FILTER_TYPES, true)) {
                    continue;
                }

                $pageMap[$pageId][$this->buildCriterionKey($propertyId, $uid)] = [
                    'property_id' => $propertyId,
                    'uid' => $uid,
                    'type' => $fieldType,
                    'value' => $field['value'] ?? null,
                    'options' => $field['options'] ?? [],
                    'multiple' => !empty($field['multiple']),
                ];
            }
        }
        return $pageMap;
    }

    /**
     * Возвращает page_id для category/page сущности.
     *
     * @return array<int>
     */
    private function resolvePageIdsForEntity(
        string $entityType,
        int $entityId,
        string $languageCode = ENV_DEF_LANG,
        array|string $statuses = ['active']
    ): array {
        if ($entityType === 'page') {
            return [$entityId];
        }

        $descendants = $this->modelCategories->getCategoryDescendantsShort($entityId, $languageCode);
        $categoryIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int) ($row['category_id'] ?? 0),
            $descendants
        ), static fn(int $id): bool => $id > 0)));

        if ($categoryIds === []) {
            return [];
        }

        return $this->modelFilters->getPageIdsForCategories($categoryIds, $languageCode, $statuses);
    }

    /**
     * Нормализует payload materialized-фильтра и обеспечивает обратную совместимость со старым форматом.
     */
    private function normalizeMaterializedPayload(array $payload): array {
        $payload['property_id'] = (int) ($payload['property_id'] ?? 0);
        $payload['property_name'] = (string) ($payload['property_name'] ?? '');
        $payload['entity_type'] = (string) ($payload['entity_type'] ?? 'page');
        $payload['language_code'] = (string) ($payload['language_code'] ?? ENV_DEF_LANG);

        if (isset($payload['fields']) && is_array($payload['fields'])) {
            $payload['fields'] = array_values(array_filter($payload['fields'], static fn($field): bool => is_array($field)));
            return $payload;
        }

        if (!isset($payload['filter_type'])) {
            $payload['fields'] = [];
            return $payload;
        }

        $legacyField = [
            'uid' => 'legacy_' . $payload['property_id'],
            'label' => $payload['property_name'],
            'type' => $payload['filter_type'] === 'range' ? 'number' : 'select',
            'filter_type' => (string) $payload['filter_type'],
            'multiple' => !empty($payload['multiple']),
        ];
        if ($payload['filter_type'] === 'range') {
            $legacyField['min_value'] = $payload['min_value'] ?? null;
            $legacyField['max_value'] = $payload['max_value'] ?? null;
            $legacyField['count'] = (int) ($payload['count'] ?? 0);
        } else {
            $legacyField['options'] = array_values(array_filter((array) ($payload['options'] ?? []), static fn($item): bool => is_array($item)));
        }

        $payload['fields'] = [$legacyField];
        return $payload;
    }

    /**
     * Нормализует вход выбранных фильтров в единый список критериев.
     *
     * @return array<string, array<string, mixed>>
     */
    private function normalizeSelectedFilters(array $selectedFilters): array {
        $normalized = [];
        foreach ($selectedFilters as $key => $value) {
            $criterion = null;
            if (is_array($value) && isset($value['property_id'], $value['uid'])) {
                $rawValues = array_key_exists('values', $value) || array_key_exists('value', $value)
                    ? ($value['values'] ?? $value['value'] ?? [])
                    : [];
                if (!is_array($rawValues)) {
                    $rawValues = [$rawValues];
                }
                $criterion = [
                    'property_id' => (int) $value['property_id'],
                    'uid' => trim((string) $value['uid']),
                    'values' => array_map('strval', $rawValues),
                    'min' => $value['min'] ?? ($value['from'] ?? null),
                    'max' => $value['max'] ?? ($value['to'] ?? null),
                ];
            } elseif (is_string($key) && is_array($value)) {
                [$propertyId, $uid] = $this->parseCriterionKey($key);
                if ($propertyId > 0 && $uid !== '') {
                    if (array_key_exists('values', $value) || array_key_exists('value', $value)) {
                        $rawValues = $value['values'] ?? $value['value'] ?? [];
                    } else {
                        $rawValues = $value;
                    }
                    if (!is_array($rawValues)) {
                        $rawValues = [$rawValues];
                    }
                    $criterion = [
                        'property_id' => $propertyId,
                        'uid' => $uid,
                        'values' => array_map('strval', $rawValues),
                        'min' => $value['min'] ?? ($value['from'] ?? null),
                        'max' => $value['max'] ?? ($value['to'] ?? null),
                    ];
                }
            } elseif (is_string($key) && !is_array($value)) {
                [$propertyId, $uid] = $this->parseCriterionKey($key);
                if ($propertyId > 0 && $uid !== '') {
                    $criterion = [
                        'property_id' => $propertyId,
                        'uid' => $uid,
                        'values' => [trim((string) $value)],
                        'min' => null,
                        'max' => null,
                    ];
                }
            }

            if (!$criterion || $criterion['property_id'] <= 0 || $criterion['uid'] === '') {
                continue;
            }

            $criterion['values'] = array_values(array_unique(array_filter(
                array_map(static fn($item): string => trim((string) $item), (array) ($criterion['values'] ?? [])),
                static fn(string $item): bool => $item !== ''
            )));
            $normalized[$this->buildCriterionKey($criterion['property_id'], $criterion['uid'])] = $criterion;
        }

        return $normalized;
    }

    private function pageMatchesCriteria(array $pageFields, array $criteria): bool {
        foreach ($criteria as $criterionKey => $criterion) {
            $field = $pageFields[$criterionKey] ?? null;
            if (!is_array($field)) {
                return false;
            }
            if (!$this->fieldMatchesCriterion($field, $criterion)) {
                return false;
            }
        }
        return true;
    }

    private function fieldMatchesCriterion(array $field, array $criterion): bool {
        $fieldType = strtolower(trim((string) ($field['type'] ?? '')));
        if (in_array($fieldType, self::OPTION_TYPES, true)) {
            $expectedValues = array_values(array_unique(array_map('strval', (array) ($criterion['values'] ?? []))));
            if ($expectedValues === []) {
                return true;
            }
            $actualValues = array_values(array_unique(array_map('strval', (array) ($field['value'] ?? []))));
            return count(array_intersect($expectedValues, $actualValues)) > 0;
        }

        $min = $criterion['min'] ?? null;
        $max = $criterion['max'] ?? null;
        if ($min === null && $max === null) {
            return true;
        }

        $entries = $this->extractComparableEntries([
            'type' => $fieldType,
            'value' => $field['value'] ?? null,
        ]);
        if ($entries === []) {
            return false;
        }

        $minComparable = $min !== null ? $this->normalizeComparableValue($fieldType, $min) : null;
        $maxComparable = $max !== null ? $this->normalizeComparableValue($fieldType, $max) : null;
        foreach ($entries as $entry) {
            $cmp = $entry['cmp'];
            if ($minComparable !== null && $cmp < $minComparable) {
                continue;
            }
            if ($maxComparable !== null && $cmp > $maxComparable) {
                continue;
            }
            return true;
        }
        return false;
    }

    /**
     * @return array<int, array{cmp:int|float|string, raw:string|float|int}>
     */
    private function extractComparableEntries(array $field): array {
        $fieldType = strtolower(trim((string) ($field['type'] ?? '')));
        $values = $field['value'] ?? null;
        if (!is_array($values)) {
            $values = [$values];
        }

        $entries = [];
        foreach ($values as $value) {
            $raw = $this->normalizeSerializableValue($fieldType, $value);
            $cmp = $this->normalizeComparableValue($fieldType, $value);
            if ($raw === null || $cmp === null) {
                continue;
            }
            $entries[] = ['cmp' => $cmp, 'raw' => $raw];
        }
        return $entries;
    }

    private function normalizeComparableValue(string $fieldType, mixed $value): int|float|string|null {
        if (is_array($value) || is_object($value) || $value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return match ($fieldType) {
            'number' => is_numeric($value) ? (float) $value : null,
            'date' => ($timestamp = strtotime($value . ' 00:00:00')) !== false ? $timestamp : null,
            'time' => ($timestamp = strtotime('1970-01-01 ' . $value)) !== false ? $timestamp : null,
            'datetime-local' => ($timestamp = strtotime(str_replace('T', ' ', $value))) !== false ? $timestamp : null,
            default => $value,
        };
    }

    private function normalizeSerializableValue(string $fieldType, mixed $value): string|float|int|null {
        if (is_array($value) || is_object($value) || $value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return match ($fieldType) {
            'number' => is_numeric($value) ? (float) $value : null,
            'date', 'time', 'datetime-local' => $value,
            default => $value,
        };
    }

    private function parseCriterionKey(string $key): array {
        foreach([':', '|', '.'] as $delimiter) {
            if (str_contains($key, $delimiter)) {
                [$propertyId, $uid] = array_pad(explode($delimiter, $key, 2), 2, '');
                return [(int) $propertyId, trim((string) $uid)];
            }
        }
        return [0, ''];
    }

    private function buildCriterionKey(int $propertyId, string $uid): string {
        return $propertyId . ':' . trim($uid);
    }

    private function normalizeEntityType(string $entityType): ?string {
        $entityType = strtolower(trim($entityType));
        return in_array($entityType, ['category', 'page'], true) ? $entityType : null;
    }
}
