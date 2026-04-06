<?php

namespace classes\system;

final class PropertyFieldContract {

    private const CHOICE_TYPES = ['select', 'checkbox', 'radio'];
    private const SUPPORTED_TYPES = [
        'text',
        'number',
        'date',
        'time',
        'datetime-local',
        'hidden',
        'password',
        'file',
        'email',
        'phone',
        'select',
        'textarea',
        'image',
        'checkbox',
        'radio',
    ];

    public static function isChoiceType(string $type): bool {
        return in_array(strtolower(trim($type)), self::CHOICE_TYPES, true);
    }

    public static function isSupportedFieldType(string $type): bool {
        return in_array(strtolower(trim($type)), self::SUPPORTED_TYPES, true);
    }

    public static function decodeFieldList(mixed $payload): array {
        if (is_string($payload)) {
            $payload = trim($payload);
            if ($payload === '') {
                return [];
            }
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            } else {
                return [];
            }
        }

        if (!is_array($payload)) {
            return [];
        }

        if ($payload !== [] && !isset($payload['type']) && !isset($payload['default']) && !isset($payload['value']) && !isset($payload['uid'])) {
            $containsScalarItems = false;
            foreach ($payload as $item) {
                if (is_scalar($item) || $item === null) {
                    $containsScalarItems = true;
                    break;
                }
            }
            if ($containsScalarItems) {
                $normalized = [];
                foreach (array_values($payload) as $item) {
                    $type = strtolower(trim((string) $item));
                    if ($type === '') {
                        continue;
                    }
                    $normalized[] = ['type' => $type];
                }
                return $normalized;
            }
        }

        if (isset($payload['type']) || isset($payload['default']) || isset($payload['value']) || isset($payload['uid'])) {
            return [$payload];
        }

        return array_values(array_filter($payload, static fn($item): bool => is_array($item)));
    }

    public static function normalizeTypeFields(mixed $fields): array {
        $decodedFields = self::decodeFieldList($fields);
        $normalized = [];
        $usedUids = [];
        foreach (array_values($decodedFields) as $index => $field) {
            $type = strtolower(trim((string) ($field['type'] ?? $field ?? '')));
            if ($type === '' || !self::isSupportedFieldType($type)) {
                continue;
            }
            $uid = trim((string) ($field['uid'] ?? ''));
            if ($uid === '') {
                $uid = 'legacy_' . $index;
            }
            if (isset($usedUids[$uid])) {
                continue;
            }
            $usedUids[$uid] = true;
            $normalized[] = [
                'uid' => $uid,
                'type' => $type,
            ];
        }
        return $normalized;
    }

    public static function normalizeDefaultFieldsForStorage(
        mixed $payload,
        mixed $typeFields = [],
        array $propertyMeta = [],
        mixed $existingDefaults = []
    ): array {
        $typeDefinitions = self::normalizeTypeFields($typeFields);
        $submittedItems = self::decodeFieldList($payload);
        $existingItems = self::decodeFieldList($existingDefaults);

        if (empty($typeDefinitions)) {
            foreach ($submittedItems as $index => $submittedItem) {
                $type = strtolower(trim((string) ($submittedItem['type'] ?? '')));
                if ($type === '') {
                    continue;
                }
                $typeDefinitions[] = [
                    'uid' => trim((string) ($submittedItem['uid'] ?? ('legacy_' . $index))),
                    'type' => $type,
                ];
            }
        }

        $submittedByUid = self::indexByUid($submittedItems);
        $existingByUid = self::indexByUid($existingItems);
        $normalized = [];

        foreach (array_values($typeDefinitions) as $index => $fieldDefinition) {
            $uid = trim((string) ($fieldDefinition['uid'] ?? ''));
            if ($uid === '') {
                $uid = 'legacy_' . $index;
            }
            $submittedItem = $submittedByUid[$uid] ?? ($submittedItems[$index] ?? []);
            $existingItem = $existingByUid[$uid] ?? ($existingItems[$index] ?? []);
            $normalized[] = self::normalizeDefaultFieldItem($submittedItem, [
                'uid' => $uid,
                'type' => strtolower(trim((string) ($fieldDefinition['type'] ?? 'text'))) ?: 'text',
            ], $propertyMeta, $existingItem, $index);
        }

        return array_values($normalized);
    }

    public static function normalizeValueFieldsForStorage(
        mixed $payload,
        mixed $defaultFields = [],
        mixed $typeFields = [],
        array $propertyMeta = []
    ): array {
        $normalizedDefaults = self::normalizeDefaultFieldsForStorage($defaultFields, $typeFields, $propertyMeta, $defaultFields);
        $submittedItems = self::decodeFieldList($payload);
        $submittedByUid = self::indexByUid($submittedItems);
        $normalized = [];

        foreach (array_values($normalizedDefaults) as $index => $defaultField) {
            $uid = (string) ($defaultField['uid'] ?? ('legacy_' . $index));
            $submittedItem = $submittedByUid[$uid] ?? ($submittedItems[$index] ?? []);
            $normalized[] = self::compactValueField($submittedItem, $defaultField);
        }

        return array_values($normalized);
    }

    public static function buildRuntimeFields(
        mixed $defaultFields = [],
        mixed $storedFields = [],
        mixed $typeFields = [],
        array $propertyMeta = []
    ): array {
        $normalizedDefaults = self::normalizeDefaultFieldsForStorage($defaultFields, $typeFields, $propertyMeta, $defaultFields);
        $storedItems = self::decodeFieldList($storedFields);
        $storedByUid = self::indexByUid($storedItems);
        $runtime = [];

        foreach (array_values($normalizedDefaults) as $index => $defaultField) {
            $uid = (string) ($defaultField['uid'] ?? ('legacy_' . $index));
            $storedItem = $storedByUid[$uid] ?? ($storedItems[$index] ?? []);
            $runtime[] = self::hydrateRuntimeField($defaultField, $storedItem);
        }

        return array_values($runtime);
    }

    private static function normalizeDefaultFieldItem(
        array $sourceItem,
        array $fieldDefinition,
        array $propertyMeta,
        array $existingItem,
        int $index
    ): array {
        $type = strtolower(trim((string) ($fieldDefinition['type'] ?? 'text'))) ?: 'text';
        $uid = trim((string) ($sourceItem['uid'] ?? $fieldDefinition['uid'] ?? $existingItem['uid'] ?? ''));
        if ($uid === '') {
            $uid = 'legacy_' . $index;
        }

        $propertyName = self::normalizeDisplayText($propertyMeta['name'] ?? '');
        $required = self::toFlag($sourceItem['required'] ?? $existingItem['required'] ?? ($propertyMeta['is_required'] ?? 0));
        $repeatableProperty = self::toFlag($propertyMeta['is_multiple'] ?? 0);
        $multiple = $repeatableProperty
            ? 1
            : self::resolveMultipleFlag($type, $sourceItem['multiple'] ?? $existingItem['multiple'] ?? 0);
        $labelFallback = self::normalizeDisplayText($sourceItem['label'] ?? $existingItem['label'] ?? $propertyName);
        $titleFallback = self::normalizeDisplayText($sourceItem['title'] ?? $existingItem['title'] ?? '');

        $normalized = [
            'uid' => $uid,
            'type' => $type,
            'label' => $labelFallback,
            'title' => $titleFallback,
            'required' => $required,
            'multiple' => $multiple,
            'repeatable_property' => $repeatableProperty,
        ];

        if (self::isChoiceType($type)) {
            [$options, $selectedKeys] = self::normalizeChoiceDefinition($type, $sourceItem, $existingItem, $multiple);
            $normalized['options'] = $options;
            $normalized['default'] = $selectedKeys;
            return $normalized;
        }

        $sourceDefault = array_key_exists('default', $sourceItem)
            ? $sourceItem['default']
            : ($sourceItem['value'] ?? ($existingItem['default'] ?? ''));
        $normalized['default'] = self::normalizeScalarOrList($sourceDefault, (bool) $multiple);
        return $normalized;
    }

    private static function hydrateRuntimeField(array $defaultField, array $storedItem): array {
        $runtime = $defaultField;
        $type = (string) ($defaultField['type'] ?? '');
        $repeatableProperty = !empty($defaultField['repeatable_property']);
        if (self::isChoiceType((string) ($defaultField['type'] ?? ''))) {
            $sourceValue = array_key_exists('value', $storedItem)
                ? $storedItem['value']
                : ($storedItem['default'] ?? ($defaultField['default'] ?? []));
            if ($repeatableProperty && $type !== 'checkbox') {
                $runtime['value'] = self::normalizeScalarOrList($sourceValue, !empty($defaultField['multiple']));
                return $runtime;
            }
            $runtime['value'] = self::normalizeChoiceValue(
                $sourceValue,
                $defaultField,
                !empty($defaultField['multiple'])
            );
            return $runtime;
        }

        $sourceValue = array_key_exists('value', $storedItem)
            ? $storedItem['value']
            : ($storedItem['default'] ?? ($defaultField['default'] ?? ''));
        if (in_array($type, ['file', 'image'], true)) {
            $runtime['value'] = self::normalizeFileReferenceValue($sourceValue, !empty($defaultField['multiple']));
            return $runtime;
        }

        $runtime['value'] = self::normalizeScalarOrList($sourceValue, !empty($defaultField['multiple']));
        return $runtime;
    }

    private static function compactValueField(array $submittedItem, array $defaultField): array {
        $type = strtolower(trim((string) ($defaultField['type'] ?? 'text'))) ?: 'text';
        $uid = trim((string) ($defaultField['uid'] ?? $submittedItem['uid'] ?? ''));
        $repeatableProperty = !empty($defaultField['repeatable_property']);

        $normalized = [
            'uid' => $uid,
            'type' => $type,
        ];

        if (self::isChoiceType($type)) {
            $sourceValue = array_key_exists('value', $submittedItem)
                ? $submittedItem['value']
                : ($submittedItem['default'] ?? ($defaultField['default'] ?? []));
            if ($repeatableProperty && $type !== 'checkbox') {
                $normalized['value'] = self::normalizeScalarOrList($sourceValue, !empty($defaultField['multiple']));
                return $normalized;
            }
            $normalized['value'] = self::normalizeChoiceValue(
                $sourceValue,
                $defaultField,
                !empty($defaultField['multiple'])
            );
            return $normalized;
        }

        $sourceValue = array_key_exists('value', $submittedItem)
            ? $submittedItem['value']
            : ($submittedItem['default'] ?? ($defaultField['default'] ?? ''));
        if (in_array($type, ['file', 'image'], true)) {
            $normalized['value'] = self::normalizeFileReferenceValue($sourceValue, !empty($defaultField['multiple']));
            return $normalized;
        }

        $normalized['value'] = self::normalizeScalarOrList($sourceValue, !empty($defaultField['multiple']));
        return $normalized;
    }

    private static function normalizeChoiceDefinition(string $type, array $sourceItem, array $existingItem, int $multiple): array {
        $existingOptions = is_array($existingItem['options'] ?? null) ? array_values($existingItem['options']) : [];

        if (is_array($sourceItem['options'] ?? null)) {
            return self::normalizeChoiceOptionsArray(
                $sourceItem['options'],
                $sourceItem['default'] ?? ($sourceItem['value'] ?? null),
                $existingOptions,
                $type,
                $multiple
            );
        }

        if ($type === 'select') {
            $legacySelect = self::parseLegacySelectPayload($sourceItem['default'] ?? ($sourceItem['value'] ?? null), $existingOptions, $multiple);
            if ($legacySelect !== null) {
                return [$legacySelect['options'], $legacySelect['selected']];
            }
        }

        return self::normalizeIndexedChoiceDefinition($type, $sourceItem, $existingOptions, $multiple);
    }

    private static function normalizeChoiceOptionsArray(
        array $options,
        mixed $selectedSource,
        array $existingOptions,
        string $type,
        int $multiple
    ): array {
        $normalizedOptions = [];
        $selectedKeys = [];
        $usedKeys = [];
        $explicitSelection = $selectedSource !== null ? self::normalizeChoiceTokens($selectedSource) : null;

        foreach (array_values($options) as $index => $option) {
            if (!is_array($option)) {
                continue;
            }
            $label = trim((string) ($option['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $existingKey = self::findExistingOptionKey($existingOptions, $label, $index);
            $candidateKey = trim((string) ($option['key'] ?? ($option['value'] ?? $existingKey)));
            $key = self::normalizeOptionKey($candidateKey !== '' ? $candidateKey : $label, $index, $usedKeys);
            $usedKeys[$key] = true;

            $normalizedOptions[] = [
                'key' => $key,
                'label' => $label,
                'sort' => isset($option['sort']) ? (int) $option['sort'] : (($index + 1) * 10),
                'disabled' => self::toFlag($option['disabled'] ?? 0),
            ];

            $isSelected = $explicitSelection !== null
                ? in_array($key, $explicitSelection, true)
                : self::toFlag($option['selected'] ?? ($option['checked'] ?? 0)) === 1;
            if ($isSelected) {
                $selectedKeys[] = $key;
            }
        }

        return [$normalizedOptions, self::finalizeChoiceSelection($selectedKeys, $normalizedOptions, $type, $multiple)];
    }

    private static function normalizeIndexedChoiceDefinition(
        string $type,
        array $sourceItem,
        array $existingOptions,
        int $multiple
    ): array {
        $labelSource = $sourceItem['option_label'] ?? ($sourceItem['label'] ?? []);
        $keySource = $sourceItem['option_key'] ?? [];

        $labels = [];
        if (is_array($labelSource)) {
            foreach ($labelSource as $label) {
                $label = trim((string) $label);
                if ($label !== '') {
                    $labels[] = $label;
                }
            }
        } elseif (is_array($existingOptions)) {
            foreach ($existingOptions as $option) {
                $label = trim((string) ($option['label'] ?? ''));
                if ($label !== '') {
                    $labels[] = $label;
                }
            }
        }

        $selectedIndexes = self::normalizeIndexedSelectionTokens(
            $sourceItem['option_selected'] ?? ($sourceItem['default'] ?? ($sourceItem['value'] ?? []))
        );

        $normalizedOptions = [];
        $selectedKeys = [];
        $usedKeys = [];

        foreach ($labels as $index => $label) {
            $existingKey = self::findExistingOptionKey($existingOptions, $label, $index);
            $candidateKey = is_array($keySource) ? trim((string) ($keySource[$index] ?? $existingKey)) : $existingKey;
            $key = self::normalizeOptionKey($candidateKey !== '' ? $candidateKey : $label, $index, $usedKeys);
            $usedKeys[$key] = true;

            $normalizedOptions[] = [
                'key' => $key,
                'label' => $label,
                'sort' => ($index + 1) * 10,
                'disabled' => 0,
            ];

            if (isset($selectedIndexes[$index])) {
                $selectedKeys[] = $key;
            }
        }

        return [$normalizedOptions, self::finalizeChoiceSelection($selectedKeys, $normalizedOptions, $type, $multiple)];
    }

    private static function parseLegacySelectPayload(mixed $payload, array $existingOptions, int $multiple): ?array {
        if (!is_string($payload)) {
            return null;
        }
        $payload = html_entity_decode(trim($payload));
        if ($payload === '' || (!str_contains($payload, '{|}') && !str_contains($payload, '='))) {
            return null;
        }

        $options = [];
        $selected = [];
        $usedKeys = [];
        foreach (explode('{|}', $payload) as $index => $rawOption) {
            $rawOption = trim((string) $rawOption);
            if ($rawOption === '') {
                continue;
            }
            [$rawLabel, $rawValue] = array_pad(explode('=', $rawOption, 2), 2, '');
            $isSelected = str_contains($rawValue, '{*}');
            $rawValue = str_replace('{*}', '', $rawValue);
            $label = trim((string) $rawLabel);
            if ($label === '') {
                continue;
            }
            $existingKey = self::findExistingOptionKey($existingOptions, $label, $index);
            $candidateKey = trim($rawValue) !== '' ? trim($rawValue) : $existingKey;
            $key = self::normalizeOptionKey($candidateKey !== '' ? $candidateKey : $label, $index, $usedKeys);
            $usedKeys[$key] = true;
            $options[] = [
                'key' => $key,
                'label' => $label,
                'sort' => ($index + 1) * 10,
                'disabled' => 0,
            ];
            if ($isSelected) {
                $selected[] = $key;
            }
        }

        return [
            'options' => $options,
            'selected' => self::finalizeChoiceSelection($selected, $options, 'select', $multiple),
        ];
    }

    private static function normalizeChoiceValue(mixed $sourceValue, array $defaultField, bool $multiple): array {
        $type = strtolower(trim((string) ($defaultField['type'] ?? 'select'))) ?: 'select';
        $allowedKeys = [];
        foreach ((array) ($defaultField['options'] ?? []) as $option) {
            if (!is_array($option)) {
                continue;
            }
            $key = trim((string) ($option['key'] ?? ''));
            if ($key !== '') {
                $allowedKeys[$key] = true;
            }
        }

        $selectedKeys = self::normalizeChoiceTokens($sourceValue);
        if ($selectedKeys === [] && array_key_exists('default', $defaultField)) {
            $selectedKeys = self::normalizeChoiceTokens($defaultField['default']);
        }

        $selectedKeys = array_values(array_filter($selectedKeys, static fn(string $key): bool => isset($allowedKeys[$key])));
        return self::finalizeChoiceSelection($selectedKeys, (array) ($defaultField['options'] ?? []), $type, (int) $multiple);
    }

    private static function normalizeScalarOrList(mixed $value, bool $multiple): string|array {
        if ($multiple) {
            if ($value === null || $value === '') {
                return [];
            }
            if (!is_array($value)) {
                return [self::normalizeScalar($value)];
            }
            if (self::containsNestedArray($value)) {
                $normalizedTree = self::normalizeNestedScalarTree($value);
                return is_array($normalizedTree) ? $normalizedTree : [];
            }
            $normalized = [];
            foreach ($value as $item) {
                $item = self::normalizeScalar($item);
                if ($item !== '') {
                    $normalized[] = $item;
                }
            }
            return array_values($normalized);
        }

        if (is_array($value)) {
            $value = reset($value);
        }
        return self::normalizeScalar($value);
    }

    public static function normalizeFileReferenceList(mixed $value): array {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }

            if ($value[0] === '[' || $value[0] === '{') {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    return self::normalizeFileReferenceList($decoded);
                }
            }

            if (str_contains($value, ',')) {
                $tokens = [];
                foreach (explode(',', $value) as $item) {
                    foreach (self::normalizeFileReferenceList($item) as $token) {
                        $tokens[$token] = $token;
                    }
                }
                return array_values($tokens);
            }

            return [$value];
        }

        if (!is_array($value)) {
            $scalar = trim((string) $value);
            return $scalar === '' ? [] : [$scalar];
        }

        $tokens = [];
        foreach ($value as $item) {
            foreach (self::normalizeFileReferenceList($item) as $token) {
                $tokens[$token] = $token;
            }
        }

        return array_values($tokens);
    }

    private static function normalizeFileReferenceValue(mixed $value, bool $multiple): string|array {
        if ($multiple && is_array($value) && self::containsNestedArray($value)) {
            $normalizedTree = self::normalizeNestedFileReferenceTree($value);
            return is_array($normalizedTree) ? $normalizedTree : [];
        }
        $references = self::normalizeFileReferenceList($value);
        if ($multiple) {
            return $references;
        }

        return $references === [] ? '' : (string) reset($references);
    }

    private static function containsNestedArray(array $value): bool {
        foreach ($value as $item) {
            if (is_array($item)) {
                return true;
            }
        }
        return false;
    }

    private static function normalizeNestedScalarTree(mixed $value): string|array {
        if (!is_array($value)) {
            return self::normalizeScalar($value);
        }

        $normalized = [];
        foreach ($value as $item) {
            $normalizedItem = self::normalizeNestedScalarTree($item);
            if ($normalizedItem === '' || $normalizedItem === []) {
                continue;
            }
            $normalized[] = $normalizedItem;
        }
        return array_values($normalized);
    }

    private static function normalizeNestedFileReferenceTree(mixed $value): string|array {
        if (!is_array($value)) {
            $references = self::normalizeFileReferenceList($value);
            if ($references === []) {
                return '';
            }
            return count($references) === 1 ? (string) reset($references) : array_values($references);
        }

        $normalized = [];
        foreach ($value as $item) {
            $normalizedItem = self::normalizeNestedFileReferenceTree($item);
            if ($normalizedItem === '' || $normalizedItem === []) {
                continue;
            }
            $normalized[] = $normalizedItem;
        }
        return array_values($normalized);
    }

    private static function normalizeScalar(mixed $value): string {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value) || is_object($value) || $value === null) {
            return '';
        }
        return trim((string) $value);
    }

    private static function normalizeDisplayText(mixed $value): string {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!is_array($item) && !is_object($item)) {
                    $item = trim((string) $item);
                    if ($item !== '') {
                        return $item;
                    }
                }
            }
            return '';
        }
        if (is_object($value) || $value === null) {
            return '';
        }
        return trim((string) $value);
    }

    private static function normalizeChoiceTokens(mixed $value): array {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }
            if ($value[0] === '[' || $value[0] === '{') {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }
        }

        if (is_array($value)) {
            $tokens = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    if (isset($item['key']) && !is_array($item['key']) && !is_object($item['key'])) {
                        $item = $item['key'];
                    } elseif (array_key_exists('value', $item) && !is_array($item['value']) && !is_object($item['value'])) {
                        $item = $item['value'];
                    } elseif (array_key_exists('default', $item) && !is_array($item['default']) && !is_object($item['default'])) {
                        $item = $item['default'];
                    } elseif (array_key_exists('label', $item) && !is_array($item['label']) && !is_object($item['label'])) {
                        $item = $item['label'];
                    } else {
                        continue;
                    }
                }
                if (is_object($item)) {
                    continue;
                }
                $item = trim((string) $item);
                if ($item !== '') {
                    $tokens[$item] = $item;
                }
            }
            return array_values($tokens);
        }

        $scalar = trim((string) $value);
        if ($scalar === '') {
            return [];
        }

        if (str_contains($scalar, '{|}') || str_contains($scalar, '{*}') || str_contains($scalar, '=')) {
            $legacy = self::parseLegacySelectPayload($scalar, [], 1);
            return $legacy['selected'] ?? [];
        }

        if (str_contains($scalar, ',')) {
            $tokens = [];
            foreach (explode(',', $scalar) as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $tokens[$item] = $item;
                }
            }
            return array_values($tokens);
        }

        return [$scalar];
    }

    private static function finalizeChoiceSelection(array $selectedKeys, array $options, string $type, int $multiple): array {
        $allowedKeys = [];
        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }
            $key = trim((string) ($option['key'] ?? ''));
            if ($key !== '') {
                $allowedKeys[$key] = true;
            }
        }

        $selectedKeys = array_values(array_filter(
            array_unique(array_map('strval', $selectedKeys)),
            static fn(string $key): bool => isset($allowedKeys[$key])
        ));

        if ($type === 'radio') {
            return $selectedKeys === [] ? [] : [reset($selectedKeys)];
        }
        if ($type === 'select' && $multiple === 0) {
            return $selectedKeys === [] ? [] : [reset($selectedKeys)];
        }
        return $selectedKeys;
    }

    private static function normalizeIndexedSelectionTokens(mixed $value): array {
        $tokens = [];
        if (!is_array($value)) {
            if ($value === null || $value === '') {
                return [];
            }
            $value = [$value];
        }

        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '' && ctype_digit($item)) {
                $tokens[(int) $item] = true;
            }
        }

        return $tokens;
    }

    private static function indexByUid(array $items): array {
        $indexed = [];
        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $uid = trim((string) ($item['uid'] ?? ''));
            if ($uid === '') {
                $uid = 'legacy_' . $index;
            }
            if (!isset($indexed[$uid])) {
                $indexed[$uid] = $item;
            }
        }
        return $indexed;
    }

    private static function resolveMultipleFlag(string $type, mixed $value): int {
        if ($type === 'radio') {
            return 0;
        }
        if ($type === 'checkbox') {
            return 1;
        }
        return self::toFlag($value);
    }

    private static function toFlag(mixed $value): int {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_numeric($value)) {
            return ((int) $value) > 0 ? 1 : 0;
        }
        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'on', 'yes', 'true'], true) ? 1 : 0;
    }

    private static function normalizeOptionKey(string $candidate, int $index, array $usedKeys): string {
        $candidate = self::slugifyKey($candidate);
        if ($candidate === '') {
            $candidate = 'option-' . ($index + 1);
        }
        $base = $candidate;
        $suffix = 2;
        while (isset($usedKeys[$candidate])) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }
        return $candidate;
    }

    private static function slugifyKey(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (class_exists('\Transliterator')) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove');
            if ($transliterator) {
                $value = (string) $transliterator->transliterate($value);
            }
        }
        $value = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
        $value = trim($value, '-');
        return $value;
    }

    private static function findExistingOptionKey(array $existingOptions, string $label, int $index): string {
        foreach ($existingOptions as $existingOption) {
            if (!is_array($existingOption)) {
                continue;
            }
            if (trim((string) ($existingOption['label'] ?? '')) === $label) {
                $key = trim((string) ($existingOption['key'] ?? ''));
                if ($key !== '') {
                    return $key;
                }
            }
        }

        if (isset($existingOptions[$index]) && is_array($existingOptions[$index])) {
            $key = trim((string) ($existingOptions[$index]['key'] ?? ''));
            if ($key !== '') {
                return $key;
            }
        }

        return '';
    }
}
