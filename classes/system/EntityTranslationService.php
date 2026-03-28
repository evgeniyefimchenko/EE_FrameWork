<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

/**
 * Управляет связями переводов для страниц и категорий.
 */
class EntityTranslationService {

    private static bool $infrastructureReady = false;

    public static function ensureInfrastructure(bool $force = false): void {
        if (self::$infrastructureReady && !$force) {
            return;
        }

        if (!$force) {
            self::$infrastructureReady = self::tableExists(Constants::ENTITY_TRANSLATIONS_TABLE);
            if (!self::$infrastructureReady) {
                throw new \RuntimeException('Entity translations infrastructure is not installed. Run install/upgrade first.');
            }
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS ?n (
            translation_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type ENUM('page', 'category') NOT NULL,
            entity_id INT UNSIGNED NOT NULL,
            translation_group_key CHAR(32) NOT NULL,
            language_code CHAR(2) NOT NULL DEFAULT 'RU',
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_entity (entity_type, entity_id),
            UNIQUE KEY uq_group_lang (entity_type, translation_group_key, language_code),
            KEY idx_group_lookup (entity_type, translation_group_key),
            KEY idx_lang_lookup (language_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Связи переводов страниц и категорий';";

        SafeMySQL::gi()->query($sql, Constants::ENTITY_TRANSLATIONS_TABLE);
        self::$infrastructureReady = true;
    }

    public static function ensureEntity(string $entityType, int $entityId): array {
        $entityType = self::normalizeEntityType($entityType);
        if ($entityType === '' || $entityId <= 0) {
            return [];
        }

        self::ensureInfrastructure();
        $entityRow = self::getEntityRow($entityType, $entityId);
        if ($entityRow === null) {
            return [];
        }

        return self::ensureEntityTranslation(
            $entityType,
            $entityId,
            ee_get_default_content_lang_code((string) ($entityRow['language_code'] ?? ''))
        );
    }

    public static function ensureEntityTranslation(
        string $entityType,
        int $entityId,
        string $languageCode,
        ?string $translationGroupKey = null,
        bool $isPrimary = false
    ): array {
        $entityType = self::normalizeEntityType($entityType);
        $languageCode = self::normalizeLanguageCode($languageCode);
        if ($entityType === '' || $entityId <= 0) {
            return [];
        }

        self::ensureInfrastructure();

        $existing = self::getTranslationRecord($entityType, $entityId);
        if ($existing !== null) {
            $update = [];
            $targetGroupKey = $translationGroupKey !== null && trim($translationGroupKey) !== ''
                ? trim($translationGroupKey)
                : (string) ($existing['translation_group_key'] ?? '');

            if ($targetGroupKey === '') {
                $targetGroupKey = self::generateGroupKey();
            }

            $conflictEntityId = self::findEntityIdByGroupAndLanguage($entityType, $targetGroupKey, $languageCode);
            if ($conflictEntityId !== null && $conflictEntityId !== $entityId) {
                throw new \RuntimeException('Перевод для языка ' . $languageCode . ' уже существует в этой группе.');
            }

            if ((string) ($existing['language_code'] ?? '') !== $languageCode) {
                $update['language_code'] = $languageCode;
            }
            if ((string) ($existing['translation_group_key'] ?? '') !== $targetGroupKey) {
                $update['translation_group_key'] = $targetGroupKey;
            }
            if ($isPrimary && (int) ($existing['is_primary'] ?? 0) !== 1) {
                $update['is_primary'] = 1;
            }

            if ($update !== []) {
                SafeMySQL::gi()->query(
                    'UPDATE ?n SET ?u WHERE translation_id = ?i',
                    Constants::ENTITY_TRANSLATIONS_TABLE,
                    $update,
                    (int) $existing['translation_id']
                );
                $existing = self::getTranslationRecord($entityType, $entityId);
            }

            self::rebalancePrimary($entityType, (string) ($existing['translation_group_key'] ?? ''));
            return $existing ?? [];
        }

        $translationGroupKey = trim((string) $translationGroupKey);
        if ($translationGroupKey === '') {
            $translationGroupKey = self::generateGroupKey();
        }

        $conflictEntityId = self::findEntityIdByGroupAndLanguage($entityType, $translationGroupKey, $languageCode);
        if ($conflictEntityId !== null && $conflictEntityId !== $entityId) {
            throw new \RuntimeException('Перевод для языка ' . $languageCode . ' уже существует в этой группе.');
        }

        SafeMySQL::gi()->query(
            'INSERT INTO ?n SET ?u',
            Constants::ENTITY_TRANSLATIONS_TABLE,
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'translation_group_key' => $translationGroupKey,
                'language_code' => $languageCode,
                'is_primary' => $isPrimary ? 1 : 0,
            ]
        );

        self::rebalancePrimary($entityType, $translationGroupKey);
        return self::getTranslationRecord($entityType, $entityId) ?? [];
    }

    public static function linkEntityToSource(string $entityType, int $entityId, int $sourceEntityId): array {
        $entityType = self::normalizeEntityType($entityType);
        if ($entityType === '' || $entityId <= 0 || $sourceEntityId <= 0) {
            return [];
        }

        $sourceRecord = self::ensureEntity($entityType, $sourceEntityId);
        if ($sourceRecord === []) {
            return [];
        }

        $languageCode = self::getEntityLanguageCode($entityType, $entityId);
        if ($languageCode === '') {
            return [];
        }

        return self::ensureEntityTranslation(
            $entityType,
            $entityId,
            $languageCode,
            (string) $sourceRecord['translation_group_key'],
            false
        );
    }

    public static function ensureMappingsForEntityIds(string $entityType, array $entityIds): void {
        $entityType = self::normalizeEntityType($entityType);
        $entityIds = self::normalizeEntityIds($entityIds);
        if ($entityType === '' || $entityIds === []) {
            return;
        }

        self::ensureInfrastructure();

        $entityRows = self::getEntityRows($entityType, $entityIds);
        if ($entityRows === []) {
            return;
        }

        $existingRows = SafeMySQL::gi()->getAll(
            'SELECT entity_id, language_code FROM ?n WHERE entity_type = ?s AND entity_id IN (?a)',
            Constants::ENTITY_TRANSLATIONS_TABLE,
            $entityType,
            $entityIds
        );
        $existingByEntityId = [];
        foreach ($existingRows as $row) {
            $existingByEntityId[(int) ($row['entity_id'] ?? 0)] = $row;
        }

        foreach ($entityRows as $entityRow) {
            $entityId = (int) ($entityRow['entity_id'] ?? 0);
            if ($entityId <= 0) {
                continue;
            }
            if (isset($existingByEntityId[$entityId])) {
                continue;
            }
            self::ensureEntityTranslation(
                $entityType,
                $entityId,
                ee_get_default_content_lang_code((string) ($entityRow['language_code'] ?? ''))
            );
        }
    }

    public static function getTranslations(string $entityType, int $entityId): array {
        return self::getTranslationsByEntityIds($entityType, [$entityId])[$entityId] ?? [];
    }

    public static function getTranslationsByEntityIds(string $entityType, array $entityIds): array {
        $entityType = self::normalizeEntityType($entityType);
        $entityIds = self::normalizeEntityIds($entityIds);
        if ($entityType === '' || $entityIds === []) {
            return [];
        }

        self::ensureMappingsForEntityIds($entityType, $entityIds);

        $meta = self::getEntityMeta($entityType);
        $rows = SafeMySQL::gi()->getAll(
            "SELECT base.entity_id AS source_entity_id,
                    base.translation_group_key,
                    tr.entity_id,
                    tr.language_code,
                    tr.is_primary,
                    e.{$meta['id_field']} AS linked_entity_id,
                    e.title,
                    e.status,
                    e.updated_at
             FROM ?n AS base
             JOIN ?n AS tr
               ON tr.entity_type = base.entity_type
              AND tr.translation_group_key = base.translation_group_key
             LEFT JOIN ?n AS e
               ON e.{$meta['id_field']} = tr.entity_id
             WHERE base.entity_type = ?s
               AND base.entity_id IN (?a)
             ORDER BY tr.language_code ASC, tr.entity_id ASC",
            Constants::ENTITY_TRANSLATIONS_TABLE,
            Constants::ENTITY_TRANSLATIONS_TABLE,
            $meta['table'],
            $entityType,
            $entityIds
        );

        $result = [];
        foreach ($rows as $row) {
            $sourceEntityId = (int) ($row['source_entity_id'] ?? 0);
            $linkedEntityId = (int) ($row['linked_entity_id'] ?? 0);
            $languageCode = self::normalizeLanguageCode((string) ($row['language_code'] ?? ''));
            if ($sourceEntityId <= 0 || $linkedEntityId <= 0 || $languageCode === '') {
                continue;
            }
            $result[$sourceEntityId]['group_key'] = (string) ($row['translation_group_key'] ?? '');
            $result[$sourceEntityId]['translations'][$languageCode] = [
                'entity_id' => $linkedEntityId,
                'language_code' => $languageCode,
                'title' => (string) ($row['title'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'is_primary' => (int) ($row['is_primary'] ?? 0) === 1,
            ];
        }

        return $result;
    }

    public static function getTranslationState(string $entityType, int $entityId, array $availableLanguageCodes = []): array {
        $entityType = self::normalizeEntityType($entityType);
        if ($entityType === '' || $entityId <= 0) {
            return [];
        }

        $currentRecord = self::ensureEntity($entityType, $entityId);
        if ($currentRecord === []) {
            return [];
        }

        $translationsState = self::getTranslations($entityType, $entityId);
        $translations = (array) ($translationsState['translations'] ?? []);
        $availableLanguageCodes = array_values(array_unique(array_filter(array_map(
            static fn($code): string => self::normalizeLanguageCode((string) $code),
            $availableLanguageCodes
        ))));
        if ($availableLanguageCodes === []) {
            $availableLanguageCodes = ee_get_content_lang_codes();
        }

        return [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'group_key' => (string) ($translationsState['group_key'] ?? ($currentRecord['translation_group_key'] ?? '')),
            'current_language_code' => self::normalizeLanguageCode((string) ($currentRecord['language_code'] ?? '')),
            'translations' => $translations,
            'available_languages' => $availableLanguageCodes,
            'missing_languages' => array_values(array_diff($availableLanguageCodes, array_keys($translations))),
        ];
    }

    public static function getTranslatedEntityId(string $entityType, int $sourceEntityId, string $targetLanguageCode): ?int {
        $entityType = self::normalizeEntityType($entityType);
        $targetLanguageCode = self::normalizeLanguageCode($targetLanguageCode);
        if ($entityType === '' || $sourceEntityId <= 0 || $targetLanguageCode === '') {
            return null;
        }

        $sourceRecord = self::ensureEntity($entityType, $sourceEntityId);
        if ($sourceRecord === []) {
            return null;
        }

        $entityId = SafeMySQL::gi()->getOne(
            'SELECT entity_id FROM ?n WHERE entity_type = ?s AND translation_group_key = ?s AND language_code = ?s LIMIT 1',
            Constants::ENTITY_TRANSLATIONS_TABLE,
            $entityType,
            (string) $sourceRecord['translation_group_key'],
            $targetLanguageCode
        );

        return $entityId ? (int) $entityId : null;
    }

    public static function duplicatePropertyValuesFromSource(
        string $entityType,
        int $sourceEntityId,
        int $targetEntityId,
        string $sourceLanguageCode,
        string $targetLanguageCode
    ): int {
        $entityType = self::normalizeEntityType($entityType);
        $sourceLanguageCode = self::normalizeLanguageCode($sourceLanguageCode);
        $targetLanguageCode = self::normalizeLanguageCode($targetLanguageCode);
        if ($entityType === '' || $sourceEntityId <= 0 || $targetEntityId <= 0 || $sourceLanguageCode === '' || $targetLanguageCode === '') {
            return 0;
        }

        $rows = SafeMySQL::gi()->getAll(
            'SELECT set_id, property_id, property_values
             FROM ?n
             WHERE entity_id = ?i AND entity_type = ?s AND language_code = ?s',
            Constants::PROPERTY_VALUES_TABLE,
            $sourceEntityId,
            $entityType,
            $sourceLanguageCode
        );

        $copied = 0;
        foreach ($rows as $row) {
            $setId = (int) ($row['set_id'] ?? 0);
            $propertyId = (int) ($row['property_id'] ?? 0);
            if ($setId <= 0 || $propertyId <= 0) {
                continue;
            }

            $payload = [
                'entity_id' => $targetEntityId,
                'set_id' => $setId,
                'property_id' => $propertyId,
                'entity_type' => $entityType,
                'property_values' => (string) ($row['property_values'] ?? '[]'),
                'language_code' => $targetLanguageCode,
            ];

            $existingValueId = SafeMySQL::gi()->getOne(
                'SELECT value_id FROM ?n WHERE entity_id = ?i AND property_id = ?i AND entity_type = ?s AND set_id = ?i AND language_code = ?s LIMIT 1',
                Constants::PROPERTY_VALUES_TABLE,
                $targetEntityId,
                $propertyId,
                $entityType,
                $setId,
                $targetLanguageCode
            );

            if ($existingValueId) {
                SafeMySQL::gi()->query(
                    'UPDATE ?n SET ?u WHERE value_id = ?i',
                    Constants::PROPERTY_VALUES_TABLE,
                    $payload,
                    (int) $existingValueId
                );
            } else {
                SafeMySQL::gi()->query(
                    'INSERT INTO ?n SET ?u',
                    Constants::PROPERTY_VALUES_TABLE,
                    $payload
                );
            }
            $copied++;
        }

        return $copied;
    }

    public static function removeEntityTranslation(string $entityType, int $entityId): void {
        $entityType = self::normalizeEntityType($entityType);
        if ($entityType === '' || $entityId <= 0) {
            return;
        }

        self::ensureInfrastructure();
        $record = self::getTranslationRecord($entityType, $entityId);
        if ($record === null) {
            return;
        }

        SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE entity_type = ?s AND entity_id = ?i',
            Constants::ENTITY_TRANSLATIONS_TABLE,
            $entityType,
            $entityId
        );

        self::rebalancePrimary($entityType, (string) ($record['translation_group_key'] ?? ''));
    }

    public static function getEntityRow(string $entityType, int $entityId): ?array {
        $entityType = self::normalizeEntityType($entityType);
        if ($entityType === '' || $entityId <= 0) {
            return null;
        }

        $meta = self::getEntityMeta($entityType);
        $row = SafeMySQL::gi()->getRow(
            "SELECT * FROM ?n WHERE {$meta['id_field']} = ?i LIMIT 1",
            $meta['table'],
            $entityId
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    public static function getEntityLanguageCode(string $entityType, int $entityId): string {
        $entityRow = self::getEntityRow($entityType, $entityId);
        return self::normalizeLanguageCode((string) ($entityRow['language_code'] ?? ''));
    }

    private static function getTranslationRecord(string $entityType, int $entityId): ?array {
        self::ensureInfrastructure();
        $row = SafeMySQL::gi()->getRow(
            'SELECT * FROM ?n WHERE entity_type = ?s AND entity_id = ?i LIMIT 1',
            Constants::ENTITY_TRANSLATIONS_TABLE,
            $entityType,
            $entityId
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private static function getEntityRows(string $entityType, array $entityIds): array {
        $meta = self::getEntityMeta($entityType);
        return SafeMySQL::gi()->getAll(
            "SELECT {$meta['id_field']} AS entity_id, language_code FROM ?n WHERE {$meta['id_field']} IN (?a)",
            $meta['table'],
            $entityIds
        );
    }

    private static function rebalancePrimary(string $entityType, string $translationGroupKey): void {
        $translationGroupKey = trim($translationGroupKey);
        if ($translationGroupKey === '') {
            return;
        }

        $rows = SafeMySQL::gi()->getAll(
            'SELECT translation_id, is_primary FROM ?n WHERE entity_type = ?s AND translation_group_key = ?s ORDER BY translation_id ASC',
            Constants::ENTITY_TRANSLATIONS_TABLE,
            $entityType,
            $translationGroupKey
        );
        if ($rows === []) {
            return;
        }

        $hasPrimary = false;
        foreach ($rows as $row) {
            if ((int) ($row['is_primary'] ?? 0) === 1) {
                $hasPrimary = true;
                break;
            }
        }

        if (!$hasPrimary) {
            SafeMySQL::gi()->query(
                'UPDATE ?n SET is_primary = 1 WHERE translation_id = ?i',
                Constants::ENTITY_TRANSLATIONS_TABLE,
                (int) $rows[0]['translation_id']
            );
        }
    }

    private static function findEntityIdByGroupAndLanguage(string $entityType, string $translationGroupKey, string $languageCode): ?int {
        $entityId = SafeMySQL::gi()->getOne(
            'SELECT entity_id FROM ?n WHERE entity_type = ?s AND translation_group_key = ?s AND language_code = ?s LIMIT 1',
            Constants::ENTITY_TRANSLATIONS_TABLE,
            $entityType,
            $translationGroupKey,
            $languageCode
        );

        return $entityId ? (int) $entityId : null;
    }

    private static function getEntityMeta(string $entityType): array {
        return match ($entityType) {
            'page' => [
                'table' => Constants::PAGES_TABLE,
                'id_field' => 'page_id',
            ],
            'category' => [
                'table' => Constants::CATEGORIES_TABLE,
                'id_field' => 'category_id',
            ],
            default => throw new \InvalidArgumentException('Unsupported entity type: ' . $entityType),
        };
    }

    private static function normalizeEntityType(string $entityType): string {
        $entityType = strtolower(trim($entityType));
        return in_array($entityType, ['page', 'category'], true) ? $entityType : '';
    }

    private static function normalizeEntityIds(array $entityIds): array {
        $entityIds = array_values(array_unique(array_filter(array_map(
            static fn($value): int => (int) $value,
            $entityIds
        ), static fn(int $value): bool => $value > 0)));

        return $entityIds;
    }

    private static function normalizeLanguageCode(string $languageCode): string {
        return ee_get_default_content_lang_code($languageCode);
    }

    private static function generateGroupKey(): string {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return md5(uniqid('translation_group_', true));
        }
    }

    private static function tableExists(string $table): bool {
        return (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s',
            $table
        ) > 0;
    }
}
