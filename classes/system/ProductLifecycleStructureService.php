<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

/**
 * Поддерживает внутренний lifecycle-набор для карточек объектов размещения.
 * Набор скрыт от публичного поиска/фильтров через status=hidden,
 * но доступен в административной форме карточки.
 */
final class ProductLifecycleStructureService {

    private const OBJECT_SET_NAME = 'Тип записи: Objects';
    private const LIFECYCLE_SET_NAME = 'Внутренний жизненный цикл объекта';
    private const LIFECYCLE_SET_DESCRIPTION = 'Служебные поля публикации, размещения и верификации карточки объекта.';
    private const IMPORT_MAP_TABLE = ENV_DB_PREF . 'import_map';

    /**
     * Создаёт и синхронизирует lifecycle-набор для карточек объектов.
     * Если передан job_id, источник карточки проставляется только для страниц этого импорта.
     */
    public static function ensureObjectLifecycleSet(string $languageCode = ENV_DEF_LANG, ?int $jobId = null): array {
        $languageCode = strtoupper(trim($languageCode));
        if ($languageCode === '') {
            $languageCode = strtoupper((string) ENV_DEF_LANG);
        }

        $modelProperties = SysClass::getModelObject('admin', 'm_properties');
        if (!is_object($modelProperties)) {
            throw new \RuntimeException('ModelProperties is not available for lifecycle scaffold.');
        }

        $typeIds = [
            'text' => self::ensurePropertyType($modelProperties, 'Одно текстовое поле', ['text'], 'Стандартный одиночный текстовый input', $languageCode),
            'textarea' => self::ensurePropertyType($modelProperties, 'Одно многострочное поле', ['textarea'], 'Стандартное многострочное текстовое поле', $languageCode),
            'select' => self::ensurePropertyType($modelProperties, 'Одно поле выбора', ['select'], 'Стандартное одиночное поле выбора', $languageCode),
            'radio' => self::ensurePropertyType($modelProperties, 'Одно поле переключателя', ['radio'], 'Стандартное одиночное поле radio', $languageCode),
            'date' => self::ensurePropertyType($modelProperties, 'Одно поле даты', ['date'], 'Стандартное поле даты', $languageCode),
        ];

        $setId = self::ensurePropertySet($modelProperties, $languageCode);
        $propertyIds = [];
        $propertyIdsByCode = [];
        foreach (self::getLifecyclePropertyDefinitions($typeIds) as $definition) {
            $propertyId = self::ensureProperty($modelProperties, $definition, $languageCode);
            $propertyIds[] = $propertyId;
            $propertyIdsByCode[$definition['code']] = $propertyId;
        }

        $modelProperties->deletePreviousProperties($setId);
        $linkResult = $modelProperties->addPropertiesToSet($setId, $propertyIds);
        if ($linkResult->isFailure()) {
            throw new \RuntimeException($linkResult->getMessage('Не удалось связать lifecycle-набор со свойствами.'));
        }

        $linkedTypeIds = self::resolveTargetCategoryTypeIds($languageCode);
        self::syncSetTypeLinks($setId, $linkedTypeIds);

        $seedSummary = self::seedExistingPages($modelProperties, $setId, $propertyIdsByCode, $languageCode, $jobId);

        $summary = [
            'language_code' => $languageCode,
            'set_id' => $setId,
            'property_ids' => $propertyIdsByCode,
            'linked_type_ids' => $linkedTypeIds,
            'seed' => $seedSummary,
        ];

        Logger::info('product_lifecycle_structure', 'Внутренний lifecycle-набор объектов синхронизирован', $summary, [
            'initiator' => __METHOD__,
            'details' => 'Object lifecycle property scaffold synchronized',
            'include_trace' => false,
        ]);

        return $summary;
    }

    private static function ensurePropertyType(object $modelProperties, string $name, array $fields, string $description, string $languageCode): int {
        $typeId = (int) SafeMySQL::gi()->getOne(
            'SELECT type_id FROM ?n WHERE name = ?s AND language_code = ?s LIMIT 1',
            Constants::PROPERTY_TYPES_TABLE,
            $name,
            $languageCode
        );

        $result = $modelProperties->updatePropertyTypeData([
            'type_id' => $typeId > 0 ? $typeId : 0,
            'name' => $name,
            'status' => 'active',
            'fields' => json_encode(array_values($fields), JSON_UNESCAPED_UNICODE),
            'schema_version' => 1,
            'description' => $description,
        ], $languageCode);

        if ($result->isFailure()) {
            throw new \RuntimeException($result->getMessage('Не удалось сохранить тип свойства `' . $name . '`.'));
        }

        return (int) $result->getId(['type_id', 'id']);
    }

    private static function ensurePropertySet(object $modelProperties, string $languageCode): int {
        $setId = (int) SafeMySQL::gi()->getOne(
            'SELECT set_id FROM ?n WHERE name = ?s AND language_code = ?s LIMIT 1',
            Constants::PROPERTY_SETS_TABLE,
            self::LIFECYCLE_SET_NAME,
            $languageCode
        );

        $result = $modelProperties->updatePropertySetData([
            'set_id' => $setId > 0 ? $setId : 0,
            'name' => self::LIFECYCLE_SET_NAME,
            'description' => self::LIFECYCLE_SET_DESCRIPTION,
        ], $languageCode);

        if ($result->isFailure()) {
            throw new \RuntimeException($result->getMessage('Не удалось сохранить lifecycle-набор.'));
        }

        return (int) $result->getId(['set_id', 'id']);
    }

    private static function ensureProperty(object $modelProperties, array $definition, string $languageCode): int {
        $propertyId = (int) SafeMySQL::gi()->getOne(
            'SELECT property_id FROM ?n WHERE name = ?s AND language_code = ?s LIMIT 1',
            Constants::PROPERTIES_TABLE,
            (string) $definition['name'],
            $languageCode
        );

        $result = $modelProperties->updatePropertyData([
            'property_id' => $propertyId > 0 ? $propertyId : 0,
            'type_id' => (int) $definition['type_id'],
            'name' => (string) $definition['name'],
            'status' => 'hidden',
            'sort' => (int) $definition['sort'],
            'default_values' => $definition['default_values'],
            'schema_version' => 1,
            'is_multiple' => !empty($definition['is_multiple']) ? 1 : 0,
            'is_required' => !empty($definition['is_required']) ? 1 : 0,
            'description' => (string) $definition['description'],
            'entity_type' => 'page',
        ], $languageCode);

        if ($result->isFailure()) {
            throw new \RuntimeException($result->getMessage('Не удалось сохранить свойство `' . $definition['name'] . '`.'));
        }

        return (int) $result->getId(['property_id', 'id']);
    }

    private static function resolveTargetCategoryTypeIds(string $languageCode): array {
        $objectSetId = (int) SafeMySQL::gi()->getOne(
            'SELECT set_id FROM ?n WHERE name = ?s AND language_code = ?s LIMIT 1',
            Constants::PROPERTY_SETS_TABLE,
            self::OBJECT_SET_NAME,
            $languageCode
        );

        $typeIds = [];
        if ($objectSetId > 0) {
            $typeIds = array_map('intval', SafeMySQL::gi()->getCol(
                'SELECT type_id FROM ?n WHERE set_id = ?i ORDER BY type_id ASC',
                Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE,
                $objectSetId
            ));
        }

        if ($typeIds === []) {
            $typeIds = array_map('intval', SafeMySQL::gi()->getCol(
                'SELECT type_id FROM ?n WHERE name IN (?a) AND language_code = ?s ORDER BY type_id ASC',
                Constants::CATEGORIES_TYPES_TABLE,
                ['Resorts', 'Тип записи: Objects'],
                $languageCode
            ));
        }

        return array_values(array_unique(array_filter($typeIds, static fn(int $typeId): bool => $typeId > 0)));
    }

    private static function syncSetTypeLinks(int $setId, array $typeIds): void {
        SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE set_id = ?i',
            Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE,
            $setId
        );

        foreach ($typeIds as $typeId) {
            SafeMySQL::gi()->query(
                'INSERT INTO ?n SET ?u',
                Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE,
                ['type_id' => (int) $typeId, 'set_id' => $setId]
            );
        }
    }

    private static function seedExistingPages(object $modelProperties, int $setId, array $propertyIdsByCode, string $languageCode, ?int $jobId): array {
        $pageRows = self::getTargetPages($languageCode, $jobId);
        $syncImportedSource = $jobId !== null && (int) $jobId > 0;
        $seedSummary = [
            'pages_total' => count($pageRows),
            'inserted_values' => 0,
            'updated_status' => 0,
            'updated_source' => 0,
            'job_id' => $jobId,
        ];

        foreach ($pageRows as $pageRow) {
            $pageId = (int) ($pageRow['page_id'] ?? 0);
            if ($pageId <= 0) {
                continue;
            }

            foreach ($propertyIdsByCode as $code => $propertyId) {
                $propertyId = (int) $propertyId;
                if ($propertyId <= 0) {
                    continue;
                }

                $existingValueId = (int) SafeMySQL::gi()->getOne(
                    'SELECT value_id FROM ?n WHERE entity_id = ?i AND entity_type = ?s AND property_id = ?i AND set_id = ?i AND language_code = ?s LIMIT 1',
                    Constants::PROPERTY_VALUES_TABLE,
                    $pageId,
                    'page',
                    $propertyId,
                    $setId,
                    $languageCode
                );

                if ($existingValueId > 0 && !in_array($code, ['card_status', 'source'], true)) {
                    continue;
                }

                $valuePayload = self::buildPropertyValuePayload($code, $pageRow, $syncImportedSource);
                if ($valuePayload === null) {
                    if ($code === 'source' && $existingValueId > 0) {
                        continue;
                    }
                    $propertyRow = SafeMySQL::gi()->getRow(
                        'SELECT default_values FROM ?n WHERE property_id = ?i LIMIT 1',
                        Constants::PROPERTIES_TABLE,
                        $propertyId
                    ) ?: [];
                    $decodedDefaults = json_decode((string) ($propertyRow['default_values'] ?? ''), true);
                    $valuePayload = is_array($decodedDefaults) ? $decodedDefaults : [];
                }

                $result = $modelProperties->updatePropertiesValueEntities([
                    'value_id' => $existingValueId > 0 ? $existingValueId : 0,
                    'entity_id' => $pageId,
                    'property_id' => $propertyId,
                    'entity_type' => 'page',
                    'set_id' => $setId,
                    'property_values' => $valuePayload,
                ], $languageCode);

                if ($result->isFailure()) {
                    Logger::warning('product_lifecycle_structure', $result->getMessage('Не удалось записать lifecycle-значение'), [
                        'page_id' => $pageId,
                        'property_code' => $code,
                        'set_id' => $setId,
                    ], [
                        'initiator' => __METHOD__,
                        'details' => 'Lifecycle seed warning',
                        'include_trace' => false,
                    ]);
                    continue;
                }

                if ($existingValueId > 0) {
                    if ($code === 'card_status') {
                        $seedSummary['updated_status']++;
                    } elseif ($code === 'source') {
                        $seedSummary['updated_source']++;
                    }
                } else {
                    $seedSummary['inserted_values']++;
                }
            }
        }

        return $seedSummary;
    }

    private static function getTargetPages(string $languageCode, ?int $jobId): array {
        $jobId = $jobId !== null ? max(0, (int) $jobId) : 0;
        if ($jobId > 0) {
            return SafeMySQL::gi()->getAll(
                'SELECT p.page_id, p.status, p.category_id, p.language_code
                 FROM ?n AS p
                 INNER JOIN ?n AS im
                    ON im.local_id = p.page_id
                   AND im.map_type = ?s
                   AND im.job_id = ?i
                 WHERE p.language_code = ?s
                 GROUP BY p.page_id, p.status, p.category_id, p.language_code
                 ORDER BY p.page_id ASC',
                Constants::PAGES_TABLE,
                self::IMPORT_MAP_TABLE,
                'page',
                $jobId,
                $languageCode
            );
        }

        return SafeMySQL::gi()->getAll(
            'SELECT page_id, status, category_id, language_code
             FROM ?n
             WHERE language_code = ?s
             ORDER BY page_id ASC',
            Constants::PAGES_TABLE,
            $languageCode
        );
    }

    private static function buildPropertyValuePayload(string $propertyCode, array $pageRow, bool $syncImportedSource): ?array {
        return match ($propertyCode) {
            'card_status' => [[
                'uid' => 'legacy_0',
                'type' => 'select',
                'value' => [self::mapPageStatusToCardStatus((string) ($pageRow['status'] ?? 'active'))],
            ]],
            'source' => $syncImportedSource ? [[
                'uid' => 'legacy_0',
                'type' => 'select',
                'value' => ['wp-import'],
            ]] : null,
            default => null,
        };
    }

    private static function mapPageStatusToCardStatus(string $pageStatus): string {
        return match (strtolower(trim($pageStatus))) {
            'active' => 'published',
            'hidden' => 'hidden',
            'disabled' => 'archived',
            default => 'draft',
        };
    }

    private static function getLifecyclePropertyDefinitions(array $typeIds): array {
        return [
            [
                'code' => 'card_status',
                'type_id' => $typeIds['select'],
                'name' => 'Статус карточки',
                'sort' => 10,
                'is_multiple' => 0,
                'is_required' => 0,
                'description' => 'Внутренний статус публикации и модерации карточки объекта.',
                'default_values' => [[
                    'type' => 'select',
                    'label' => 'Статус карточки',
                    'title' => '',
                    'default' => 'Черновик=draft{*}{|}На модерации=pending_review{|}Опубликована=published{|}Скрыта=hidden{|}Отклонена=rejected{|}В архиве=archived',
                    'required' => 0,
                    'multiple' => 0,
                ]],
            ],
            [
                'code' => 'placement_mode',
                'type_id' => $typeIds['select'],
                'name' => 'Режим размещения',
                'sort' => 20,
                'is_multiple' => 0,
                'is_required' => 0,
                'description' => 'Коммерческий режим размещения карточки: бесплатный, платный или срочный.',
                'default_values' => [[
                    'type' => 'select',
                    'label' => 'Режим размещения',
                    'title' => '',
                    'default' => 'Бесплатное=free{*}{|}Платное=paid{|}Срочное=urgent',
                    'required' => 0,
                    'multiple' => 0,
                ]],
            ],
            [
                'code' => 'placement_active_until',
                'type_id' => $typeIds['date'],
                'name' => 'Размещение активно до',
                'sort' => 30,
                'is_multiple' => 0,
                'is_required' => 0,
                'description' => 'Дата окончания оплаченного или специального режима размещения.',
                'default_values' => [[
                    'type' => 'date',
                    'label' => 'Размещение активно до',
                    'title' => '',
                    'default' => '',
                    'required' => 0,
                    'multiple' => 0,
                ]],
            ],
            [
                'code' => 'contacts_verified',
                'type_id' => $typeIds['radio'],
                'name' => 'Контакты проверены',
                'sort' => 40,
                'is_multiple' => 0,
                'is_required' => 0,
                'description' => 'Подтверждены ли контактные данные объекта вручную менеджером.',
                'default_values' => [[
                    'type' => 'radio',
                    'label' => 'Контакты проверены',
                    'title' => 'Контакты проверены',
                    'options' => [
                        ['label' => 'Нет', 'value' => 'no', 'disabled' => 0, 'sort' => 10],
                        ['label' => 'Да', 'value' => 'yes', 'disabled' => 0, 'sort' => 20],
                    ],
                    'default' => ['no'],
                    'required' => 0,
                    'multiple' => 0,
                ]],
            ],
            [
                'code' => 'last_verification_date',
                'type_id' => $typeIds['date'],
                'name' => 'Дата последней проверки',
                'sort' => 50,
                'is_multiple' => 0,
                'is_required' => 0,
                'description' => 'Дата последней ручной проверки карточки или контактов.',
                'default_values' => [[
                    'type' => 'date',
                    'label' => 'Дата последней проверки',
                    'title' => '',
                    'default' => '',
                    'required' => 0,
                    'multiple' => 0,
                ]],
            ],
            [
                'code' => 'next_verification_date',
                'type_id' => $typeIds['date'],
                'name' => 'Следующая перепроверка',
                'sort' => 60,
                'is_multiple' => 0,
                'is_required' => 0,
                'description' => 'Плановая дата следующей перепроверки карточки объекта.',
                'default_values' => [[
                    'type' => 'date',
                    'label' => 'Следующая перепроверка',
                    'title' => '',
                    'default' => '',
                    'required' => 0,
                    'multiple' => 0,
                ]],
            ],
            [
                'code' => 'source',
                'type_id' => $typeIds['select'],
                'name' => 'Источник карточки',
                'sort' => 70,
                'is_multiple' => 0,
                'is_required' => 0,
                'description' => 'Канал создания карточки: импорт, админка, менеджер или API.',
                'default_values' => [[
                    'type' => 'select',
                    'label' => 'Источник карточки',
                    'title' => '',
                    'default' => 'Импорт WordPress=wp-import{|}Администратор=admin{*}{|}Менеджер=manager{|}API=api',
                    'required' => 0,
                    'multiple' => 0,
                ]],
            ],
            [
                'code' => 'manager_comment',
                'type_id' => $typeIds['textarea'],
                'name' => 'Внутренний комментарий менеджера',
                'sort' => 80,
                'is_multiple' => 0,
                'is_required' => 0,
                'description' => 'Внутренняя заметка менеджера по карточке объекта.',
                'default_values' => [[
                    'type' => 'textarea',
                    'label' => 'Внутренний комментарий менеджера',
                    'title' => '',
                    'default' => '',
                    'required' => 0,
                    'multiple' => 0,
                ]],
            ],
        ];
    }
}
