<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

final class ContentApiService {

    private object $modelPages;
    private object $modelCategories;
    private object $modelCategoriesTypes;
    private object $modelProperties;

    public function __construct() {
        $this->modelPages = SysClass::getModelObject('admin', 'm_pages');
        $this->modelCategories = SysClass::getModelObject('admin', 'm_categories');
        $this->modelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
        $this->modelProperties = SysClass::getModelObject('admin', 'm_properties');

        if (!$this->modelPages || !$this->modelCategories || !$this->modelCategoriesTypes || !$this->modelProperties) {
            throw new \RuntimeException('Failed to initialize content API models.');
        }
    }

    public function getEntity(string $entityType, int $entityId, string $languageCode = ''): OperationResult {
        $entityType = $this->normalizeEntityType($entityType);
        if ($entityType === '') {
            return OperationResult::validation('Unsupported entity type.');
        }

        $languageCode = $this->normalizeLanguageCode($languageCode);
        $entityData = $this->loadEntityRow($entityType, $entityId, $languageCode);
        if (!is_array($entityData)) {
            return OperationResult::failure('Entity not found.', 'entity_not_found', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'language_code' => $languageCode,
            ]);
        }

        return OperationResult::success($this->buildEntityPayload($entityType, $entityData, $languageCode));
    }

    public function createEntity(string $entityType, array $payload): OperationResult {
        $entityType = $this->normalizeEntityType($entityType);
        if ($entityType === '') {
            return OperationResult::validation('Unsupported entity type.');
        }

        $languageCode = $this->normalizeLanguageCode((string) ($payload['language_code'] ?? ''));
        $propertiesPayload = $this->extractPropertiesPayload($payload);
        $entityPayload = $this->sanitizeEntityPayload($entityType, $payload, $languageCode);

        $result = $entityType === 'page'
            ? $this->modelPages->updatePageData($entityPayload, $languageCode)
            : $this->modelCategories->updateCategoryData($entityPayload, $languageCode);

        if (!($result instanceof OperationResult) || $result->isFailure()) {
            return OperationResult::fromLegacy($result, [
                'false_message' => 'Failed to create entity.',
                'failure_code' => 'entity_create_failed',
            ]);
        }

        $entityId = $result->getId();
        if ($entityId <= 0) {
            return OperationResult::failure('Created entity ID is missing.', 'entity_create_missing_id');
        }

        $savedEntity = $this->loadEntityRow($entityType, $entityId, $languageCode);
        if (!is_array($savedEntity)) {
            return OperationResult::failure('Created entity cannot be reloaded.', 'entity_create_reload_failed');
        }

        $propertyResult = $this->applyPropertiesPayload($entityType, $entityId, $savedEntity, $propertiesPayload, $languageCode);
        if ($propertyResult->isFailure()) {
            return $propertyResult;
        }

        return $this->getEntity($entityType, $entityId, $languageCode);
    }

    public function updateEntity(string $entityType, int $entityId, array $payload): OperationResult {
        $entityType = $this->normalizeEntityType($entityType);
        if ($entityType === '') {
            return OperationResult::validation('Unsupported entity type.');
        }
        if ($entityId <= 0) {
            return OperationResult::validation('Invalid entity ID.');
        }

        $languageCode = $this->normalizeLanguageCode((string) ($payload['language_code'] ?? ''));
        $currentEntity = $this->loadEntityRow($entityType, $entityId, $languageCode);
        if (!is_array($currentEntity)) {
            return OperationResult::failure('Entity not found.', 'entity_not_found', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'language_code' => $languageCode,
            ]);
        }

        $languageCode = $this->normalizeLanguageCode((string) ($currentEntity['language_code'] ?? $languageCode));
        $propertiesPayload = $this->extractPropertiesPayload($payload);
        $entityPayload = $this->sanitizeEntityPayload($entityType, $payload, $languageCode);
        $entityPayload[$entityType === 'page' ? 'page_id' : 'category_id'] = $entityId;
        $entityPayload = $this->mergeMissingRequiredFieldsForUpdate($entityType, $entityPayload, $currentEntity);

        $result = $entityType === 'page'
            ? $this->modelPages->updatePageData($entityPayload, $languageCode)
            : $this->modelCategories->updateCategoryData($entityPayload, $languageCode);

        if (!($result instanceof OperationResult) || $result->isFailure()) {
            return OperationResult::fromLegacy($result, [
                'false_message' => 'Failed to update entity.',
                'failure_code' => 'entity_update_failed',
            ]);
        }

        $savedEntity = $this->loadEntityRow($entityType, $entityId, $languageCode);
        if (!is_array($savedEntity)) {
            return OperationResult::failure('Updated entity cannot be reloaded.', 'entity_update_reload_failed');
        }

        $propertyResult = $this->applyPropertiesPayload($entityType, $entityId, $savedEntity, $propertiesPayload, $languageCode);
        if ($propertyResult->isFailure()) {
            return $propertyResult;
        }

        return $this->getEntity($entityType, $entityId, $languageCode);
    }

    private function buildEntityPayload(string $entityType, array $entityData, string $languageCode): array {
        $payload = [
            'entity_type' => $entityType,
            'language_code' => $languageCode,
            'entity' => $entityData,
            'properties' => $this->buildPropertiesPayload($entityType, $entityData, $languageCode),
        ];

        return $payload;
    }

    private function buildPropertiesPayload(string $entityType, array $entityData, string $languageCode): array {
        $setIds = $this->resolveEntitySetIds($entityType, $entityData, $languageCode);
        $entityId = (int) ($entityData[$entityType === 'page' ? 'page_id' : 'category_id'] ?? 0);
        if ($entityId <= 0 || $setIds === []) {
            return [];
        }

        $properties = [];
        foreach ($setIds as $setId) {
            $setData = $this->modelProperties->getPropertySetData((int) $setId, $languageCode);
            if (!is_array($setData) || empty($setData['properties']) || !is_array($setData['properties'])) {
                continue;
            }
            foreach ($setData['properties'] as $propertyId => $propertyRow) {
                if (($propertyRow['property_entity_type'] ?? '') !== $entityType && ($propertyRow['property_entity_type'] ?? '') !== 'all') {
                    continue;
                }

                $valueRow = $this->modelProperties->getPropertyValuesForEntity($entityId, $entityType, (int) $propertyId, (int) $setId, $languageCode);
                $fields = is_array($valueRow['fields'] ?? null)
                    ? $valueRow['fields']
                    : $this->buildDefaultFields($propertyRow);

                $properties[] = [
                    'property_id' => (int) $propertyId,
                    'set_id' => (int) $setId,
                    'set_name' => (string) ($setData['name'] ?? ''),
                    'name' => (string) ($propertyRow['name'] ?? ''),
                    'type_id' => (int) ($propertyRow['type_id'] ?? 0),
                    'type_name' => (string) ($propertyRow['type_name'] ?? ''),
                    'description' => (string) ($propertyRow['description'] ?? ''),
                    'is_multiple' => (int) ($propertyRow['is_multiple'] ?? 0),
                    'is_required' => (int) ($propertyRow['is_required'] ?? 0),
                    'fields' => $fields,
                ];
            }
        }

        usort($properties, static function (array $left, array $right): int {
            $leftSet = (int) ($left['set_id'] ?? 0);
            $rightSet = (int) ($right['set_id'] ?? 0);
            if ($leftSet !== $rightSet) {
                return $leftSet <=> $rightSet;
            }

            return ((int) ($left['property_id'] ?? 0)) <=> ((int) ($right['property_id'] ?? 0));
        });

        return $properties;
    }

    private function applyPropertiesPayload(string $entityType, int $entityId, array $entityData, array $propertiesPayload, string $languageCode): OperationResult {
        if ($propertiesPayload === []) {
            return OperationResult::success([], '', 'noop');
        }

        $definitions = $this->buildPropertyDefinitionMap($entityType, $entityData, $languageCode);
        foreach ($propertiesPayload as $index => $propertyPayload) {
            if (!is_array($propertyPayload)) {
                return OperationResult::validation('Invalid property payload item.', ['index' => $index]);
            }

            $definition = $this->resolvePropertyDefinition($definitions, $propertyPayload);
            if (!is_array($definition)) {
                return OperationResult::validation('Property definition is not assigned to the entity.', [
                    'index' => $index,
                    'property_id' => (int) ($propertyPayload['property_id'] ?? 0),
                    'name' => (string) ($propertyPayload['name'] ?? ''),
                ]);
            }

            $fields = $propertyPayload['fields'] ?? ($propertyPayload['property_values'] ?? null);
            if (is_string($fields)) {
                $decoded = json_decode($fields, true);
                $fields = is_array($decoded) ? $decoded : null;
            }
            if (!is_array($fields)) {
                return OperationResult::validation('Property fields must be an array.', [
                    'index' => $index,
                    'property_id' => (int) ($definition['property_id'] ?? 0),
                ]);
            }

            $saveResult = $this->modelProperties->updatePropertiesValueEntities([
                'entity_id' => $entityId,
                'property_id' => (int) ($definition['property_id'] ?? 0),
                'entity_type' => $entityType,
                'set_id' => (int) ($definition['set_id'] ?? 0),
                'fields' => $fields,
            ], $languageCode);

            if (!($saveResult instanceof OperationResult) || $saveResult->isFailure()) {
                return OperationResult::fromLegacy($saveResult, [
                    'false_message' => 'Failed to save property value.',
                    'failure_code' => 'property_value_save_failed',
                ]);
            }
        }

        return OperationResult::success([], '', 'updated');
    }

    private function buildPropertyDefinitionMap(string $entityType, array $entityData, string $languageCode): array {
        $definitions = [];
        foreach ($this->buildPropertiesPayload($entityType, $entityData, $languageCode) as $propertyRow) {
            $propertyId = (int) ($propertyRow['property_id'] ?? 0);
            $propertyName = trim((string) ($propertyRow['name'] ?? ''));
            if ($propertyId > 0) {
                $definitions['id:' . $propertyId] = $propertyRow;
            }
            if ($propertyName !== '') {
                $definitions['name:' . mb_strtolower($propertyName)] = $propertyRow;
            }
        }

        return $definitions;
    }

    private function resolvePropertyDefinition(array $definitions, array $propertyPayload): ?array {
        $propertyId = (int) ($propertyPayload['property_id'] ?? 0);
        if ($propertyId > 0 && isset($definitions['id:' . $propertyId])) {
            return $definitions['id:' . $propertyId];
        }

        $propertyName = trim((string) ($propertyPayload['name'] ?? ''));
        if ($propertyName !== '' && isset($definitions['name:' . mb_strtolower($propertyName)])) {
            return $definitions['name:' . mb_strtolower($propertyName)];
        }

        return null;
    }

    private function resolveEntitySetIds(string $entityType, array $entityData, string $languageCode): array {
        if ($entityType === 'page') {
            $categoryId = (int) ($entityData['category_id'] ?? 0);
            if ($categoryId <= 0) {
                return [];
            }
            $typeId = (int) $this->modelCategories->getCategoryTypeId($categoryId, $languageCode);
            if ($typeId <= 0) {
                return [];
            }

            return array_values(array_map('intval', $this->modelCategoriesTypes->getCategoriesTypeSetsData($typeId)));
        }

        $typeId = (int) ($entityData['type_id'] ?? 0);
        if ($typeId <= 0) {
            return [];
        }

        return array_values(array_map('intval', $this->modelCategoriesTypes->getCategoriesTypeSetsData($typeId)));
    }

    private function loadEntityRow(string $entityType, int $entityId, string $languageCode): ?array {
        if ($entityId <= 0) {
            return null;
        }

        $row = $entityType === 'page'
            ? $this->modelPages->getPageData($entityId, $languageCode)
            : $this->modelCategories->getCategoryData($entityId, $languageCode);

        return is_array($row) ? $row : null;
    }

    private function sanitizeEntityPayload(string $entityType, array $payload, string $languageCode): array {
        $payload = $this->unwrapEntityPayload($payload, $entityType);
        $allowedKeys = $entityType === 'page'
            ? ['page_id', 'parent_page_id', 'category_id', 'status', 'title', 'slug', 'route_path', 'short_description', 'description', 'search_enabled', 'search_scope_mask', 'search_scope_public', 'search_scope_manager', 'search_scope_admin']
            : ['category_id', 'type_id', 'title', 'slug', 'route_path', 'description', 'short_description', 'parent_id', 'status', 'search_enabled', 'search_scope_mask', 'search_scope_public', 'search_scope_manager', 'search_scope_admin'];

        $entityPayload = SafeMySQL::gi()->filterArray($payload, $allowedKeys);
        $entityPayload['language_code'] = $languageCode;

        if (isset($entityPayload['description']) && is_string($entityPayload['description'])) {
            $entityPayload['description'] = FileSystem::extractBase64Images($entityPayload['description']);
        }

        return $entityPayload;
    }

    private function unwrapEntityPayload(array $payload, string $entityType): array {
        $entityKey = $entityType === 'page' ? 'page' : 'category';
        if (isset($payload[$entityKey]) && is_array($payload[$entityKey])) {
            return array_merge($payload, $payload[$entityKey]);
        }
        if (isset($payload['entity']) && is_array($payload['entity'])) {
            return array_merge($payload, $payload['entity']);
        }

        return $payload;
    }

    private function extractPropertiesPayload(array $payload): array {
        $properties = $payload['properties'] ?? null;
        if (is_array($properties)) {
            return array_values($properties);
        }

        foreach (['page', 'category', 'entity'] as $containerKey) {
            if (!empty($payload[$containerKey]) && is_array($payload[$containerKey]) && !empty($payload[$containerKey]['properties']) && is_array($payload[$containerKey]['properties'])) {
                return array_values($payload[$containerKey]['properties']);
            }
        }

        return [];
    }

    private function buildDefaultFields(array $propertyRow): array {
        $defaultValues = [];
        if (!empty($propertyRow['default_values'])) {
            $decoded = json_decode((string) $propertyRow['default_values'], true);
            if (is_array($decoded)) {
                $defaultValues = $decoded;
            }
        }

        if ($defaultValues === []) {
            $defaultValues = [[
                'type' => 'text',
                'label' => (string) ($propertyRow['name'] ?? ''),
                'title' => '',
                'default' => '',
                'multiple' => !empty($propertyRow['is_multiple']) ? 1 : 0,
                'required' => !empty($propertyRow['is_required']) ? 1 : 0,
            ]];
        }

        $fields = [];
        foreach (array_values($defaultValues) as $index => $item) {
            $fields[] = [
                'uid' => isset($item['uid']) && is_scalar($item['uid']) && trim((string) $item['uid']) !== ''
                    ? (string) $item['uid']
                    : 'default_' . $index,
                'type' => (string) ($item['type'] ?? 'text'),
                'value' => $item['default'] ?? '',
                'label' => (string) ($item['label'] ?? ($propertyRow['name'] ?? '')),
                'multiple' => isset($item['multiple']) ? (int) $item['multiple'] : (!empty($propertyRow['is_multiple']) ? 1 : 0),
                'required' => isset($item['required']) ? (int) $item['required'] : (!empty($propertyRow['is_required']) ? 1 : 0),
                'title' => (string) ($item['title'] ?? ''),
            ];
        }

        return $fields;
    }

    private function mergeMissingRequiredFieldsForUpdate(string $entityType, array $entityPayload, array $currentEntity): array {
        $fallbackKeys = $entityType === 'page'
            ? ['category_id', 'title', 'status']
            : ['type_id', 'title', 'description', 'parent_id', 'status'];

        foreach ($fallbackKeys as $key) {
            if (!array_key_exists($key, $entityPayload) && array_key_exists($key, $currentEntity)) {
                $entityPayload[$key] = $currentEntity[$key];
            }
        }

        return $entityPayload;
    }

    private function normalizeLanguageCode(string $languageCode): string {
        return ee_get_default_content_lang_code(strtoupper(trim($languageCode)));
    }

    private function normalizeEntityType(string $entityType): string {
        $entityType = strtolower(trim($entityType));
        return in_array($entityType, ['page', 'category'], true) ? $entityType : '';
    }
}
