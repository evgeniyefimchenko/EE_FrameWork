<?php

/**
 * Евгений Ефимченко, efimchenko.com
 * Реализует обработчики хуков
 * /inc/hooks.php
 */
use classes\system\SysClass;
use classes\system\Hook;
use classes\helpers\ClassSearchEngine;
use classes\helpers\FilterService;
use classes\system\ErrorLogger;
use classes\system\Constants;
use classes\system\View;
use classes\system\CacheManager;
use classes\plugins\SafeMySQL;

// === Регистрация хуков ===

if (!function_exists('ee_add_core_hook')) {
    function ee_add_core_hook(string $key, $callback, int $priority = 10): bool {
        return Hook::add($key, $callback, $priority, 'core', 'core');
    }
}

/**
 * Вызывается перед получением стандартных представлений в контроллере
 * @param View $view Объект представления
 */
ee_add_core_hook('A_beforeGetStandardViews', 'beforeGetStandardViewsHandler');

/**
 * Вызывается ПОСЛЕ обновления/создания данных категории в ModelCategories::updateCategoryData
 * @param int $categoryId ID созданной или обновлённой категории
 * @param array $categoryData Массив данных, переданных в updateCategoryData (может содержать ключ 'oldCategoryType' при смене типа)
 * @param string $method Метод ('insert' или 'update')
 */
ee_add_core_hook('afterUpdateCategoryData', 'afterUpdateCategoryDataHandler'); // Для синхронизации свойств при смене типа
ee_add_core_hook('afterUpdateCategoryData', 'createSearchIndexCategory'); // Для поисковой индексации
ee_add_core_hook('afterUpdateCategoryData', 'afterUpdateCategoryFiltersHandler'); // Для пересчета materialized-фильтров
ee_add_core_hook('afterUpdateCategoryData', 'clearPublicHtmlCacheAfterContentMutation', 50);

/**
 * Вызывается ПОСЛЕ удаления категории (предполагается из ModelCategories::deleteCategory)
 * @param int $categoryId ID удаляемой категории
 * @param mixed $categoryData Дополнительные данные (могут быть null или отсутствовать)
 * @param string $method Метод ('delete')
 */
ee_add_core_hook('afterDeleteCategory', 'deleteSearchIndexCategory');
ee_add_core_hook('afterDeleteCategory', 'deleteCategoryFiltersHandler');
ee_add_core_hook('afterDeleteCategory', 'clearPublicHtmlCacheAfterContentMutation', 50);

/**
 * Вызывается ПОСЛЕ обновления/создания данных страницы в ModelPages::updatePageData
 * @param int $pageId ID созданной или обновлённой страницы
 * @param array $pageData Массив данных, переданных в updatePageData
 * @param string $method Метод ('insert' или 'update')
 */
ee_add_core_hook('afterUpdatePageData', 'createSearchIndexPage');
ee_add_core_hook('afterUpdatePageData', 'afterUpdatePageDataHandler'); // Для синхронизации свойств при смене категории страницы
ee_add_core_hook('afterUpdatePageData', 'afterUpdatePageFiltersHandler'); // Для пересчета materialized-фильтров
ee_add_core_hook('afterUpdatePageData', 'clearPublicHtmlCacheAfterContentMutation', 50);

/**
 * Вызывается ПОСЛЕ удаления страницы (предполагается из ModelPages::deletePage)
 * @param int $pageId ID удаляемой страницы
 * @param mixed $pageData Дополнительные данные (могут быть null или отсутствовать)
 * @param string $method Метод ('delete')
 */
ee_add_core_hook('afterDeletePage', 'deleteSearchIndexPage');
ee_add_core_hook('afterDeletePage', 'deletePageFiltersHandler');
ee_add_core_hook('afterDeletePage', 'clearPublicHtmlCacheAfterContentMutation', 50);

/**
 * Вызывается ПОСЛЕ обновления связи между типами категориями и наборами свойств
 * @param int $typeId Переданный тип для обновления
 * @param array $setIds Идентификаторы наборов свойств для связывания с указанным типом категории
 * @param array $allTypeIds Все IDs включая переданный тип и его потомков
 */
ee_add_core_hook('postUpdateCategoriesTypeSetsData', 'updateCategoriesTypeSetsHandler');
ee_add_core_hook('postUpdateCategoriesTypeSetsData', 'clearPublicHtmlCacheAfterContentMutation', 50);

/**
 * Вызывается ПОСЛЕ обновления или добавления значения свойства для сущностей
 * @param int $valueId ID записи значения свойства в Constants::PROPERTY_VALUES_TABLE
 * @param array $propertyData Набор данных свойства (включая entity_type, entity_id)
 * @param string $action Выполненное действие ('update' или 'insert')
 */
ee_add_core_hook('postUpdatePropertiesValueEntities', 'postUpdatePropertiesValueHandler');
ee_add_core_hook('postUpdatePropertiesValueEntities', 'clearPublicHtmlCacheAfterContentMutation', 50);

/**
 * Вызывается ПЕРЕД обновлением или добавлением значения свойства для сущностей
 * @param int $valueId ID записи значения свойства (0 для нового)
 * @param array $propertyData Набор данных свойства
 */
ee_add_core_hook('preUpdatePropertiesValueEntities', 'preUpdatePropertiesHandler');

/**
 * Регистрируем хук, который срабатывает после изменения
 * состава свойств в наборе
 * @param int   $setId              ID набора свойств, который был изменен
 * @param array $addedPropertyIds   Массив ID свойств, которые были ДОБАВЛЕНЫ в набор
 * @param array $deletedPropertyIds Массив ID свойств, которые были УДАЛЕНЫ из набора
 */
ee_add_core_hook('afterUpdatePropertySetComposition', 'syncEntitiesOnSetUpdate');
ee_add_core_hook('afterUpdatePropertySetComposition', 'clearPublicHtmlCacheAfterContentMutation', 50);
ee_add_core_hook('afterPropertyLifecycleRebuild', 'afterPropertyLifecycleRebuildFiltersHandler');
ee_add_core_hook('afterPropertyTypeLifecycleRebuild', 'afterPropertyTypeLifecycleRebuildFiltersHandler');
ee_add_core_hook('afterPropertyLifecycleRebuild', 'clearPublicHtmlCacheAfterContentMutation', 50);
ee_add_core_hook('afterPropertyTypeLifecycleRebuild', 'clearPublicHtmlCacheAfterContentMutation', 50);

// === Функции-обработчики ===

/**
 * Планирует переиндексацию сущности в конце request и дедуплицирует повторные записи.
 */
function scheduleSearchEntityReindex(string $entityType, int $entityId, ?string $languageCode = null): void {
    static $queue = [];
    static $shutdownRegistered = false;

    $entityType = strtolower(trim($entityType));
    if ($entityId <= 0 || !in_array($entityType, ['page', 'category'], true)) {
        return;
    }

    $queueKey = $entityType . ':' . $entityId . ':' . ($languageCode ?? '');
    $queue[$queueKey] = [
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'language_code' => $languageCode,
    ];

    if ($shutdownRegistered) {
        return;
    }

    register_shutdown_function(static function () use (&$queue): void {
        if (empty($queue)) {
            return;
        }
        try {
            $searchEngine = new ClassSearchEngine();
            foreach ($queue as $task) {
                $searchEngine->reindexEntity(
                    (string) $task['entity_type'],
                    (int) $task['entity_id'],
                    isset($task['language_code']) ? (string) $task['language_code'] : null
                );
            }
        } catch (\Throwable $e) {
            new ErrorLogger('Hook error (scheduleSearchEntityReindex shutdown): ' . $e->getMessage(), __FUNCTION__, 'hook_error', [
                'queue' => $queue,
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $queue = [];
        }
    });

    $shutdownRegistered = true;
}

/**
 * Планирует пересчёт category-фильтров в конце request и расширяет список ancestors.
 *
 * @param array<int>|int $categoryIds
 */
function scheduleCategoryFiltersRefresh(array|int $categoryIds, ?string $languageCode = null): void {
    static $queue = [];
    static $shutdownRegistered = false;

    if (!is_array($categoryIds)) {
        $categoryIds = [$categoryIds];
    }
    $languageCode = is_string($languageCode) && trim($languageCode) !== '' ? trim($languageCode) : ENV_DEF_LANG;
    $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds), static fn(int $id): bool => $id > 0)));
    if ($categoryIds === []) {
        return;
    }

    try {
        $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
        if ($objectModelCategories && method_exists($objectModelCategories, 'getCategoryAncestorIds')) {
            $expanded = [];
            foreach ($categoryIds as $categoryId) {
                $expanded = array_merge($expanded, $objectModelCategories->getCategoryAncestorIds($categoryId, $languageCode, true));
            }
            $categoryIds = array_values(array_unique(array_filter(array_map('intval', $expanded), static fn(int $id): bool => $id > 0)));
        }
    } catch (\Throwable $e) {
        new ErrorLogger('Hook error (scheduleCategoryFiltersRefresh expand): ' . $e->getMessage(), __FUNCTION__, 'hook_error', [
            'category_ids' => $categoryIds,
            'language_code' => $languageCode,
            'trace' => $e->getTraceAsString(),
        ]);
    }

    foreach ($categoryIds as $categoryId) {
        $queue[$languageCode . ':' . $categoryId] = [
            'category_id' => $categoryId,
            'language_code' => $languageCode,
        ];
    }

    if ($shutdownRegistered) {
        return;
    }

    register_shutdown_function(static function () use (&$queue): void {
        if ($queue === []) {
            return;
        }

        try {
            $service = new FilterService();
            $grouped = [];
            foreach ($queue as $task) {
                $lang = (string) ($task['language_code'] ?? ENV_DEF_LANG);
                $grouped[$lang][] = (int) ($task['category_id'] ?? 0);
            }
            foreach ($grouped as $lang => $categoryIds) {
                $service->regenerateFiltersForCategories($categoryIds, $lang);
            }
        } catch (\Throwable $e) {
            new ErrorLogger('Hook error (scheduleCategoryFiltersRefresh shutdown): ' . $e->getMessage(), __FUNCTION__, 'hook_error', [
                'queue' => $queue,
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $queue = [];
        }
    });

    $shutdownRegistered = true;
}

/**
 * Планирует единственную очистку публичного HTML cache в конце request.
 */
function schedulePublicHtmlCacheClear(string $reason = 'content_mutation', array $context = []): void {
    static $scheduled = false;
    static $reasons = [];

    $reasons[] = [
        'reason' => $reason,
        'context' => $context,
    ];

    if ($scheduled) {
        return;
    }

    register_shutdown_function(static function () use (&$reasons): void {
        try {
            CacheManager::clearHtmlCache();
        } catch (\Throwable $e) {
            new ErrorLogger('Hook error (schedulePublicHtmlCacheClear shutdown): ' . $e->getMessage(), __FUNCTION__, 'hook_error', [
                'reasons' => $reasons,
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $reasons = [];
        }
    });

    $scheduled = true;
}

/**
 * Универсальный hook handler для инвалидации публичного HTML cache.
 */
function clearPublicHtmlCacheAfterContentMutation(...$args): void {
    schedulePublicHtmlCacheClear(__FUNCTION__, ['args' => $args]);
}

/**
 * Резолвит язык категорий по БД и планирует refresh по языковым группам.
 *
 * @param array<int>|int $categoryIds
 */
function scheduleCategoryFiltersRefreshResolved(array|int $categoryIds): void {
    if (!is_array($categoryIds)) {
        $categoryIds = [$categoryIds];
    }
    $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds), static fn(int $id): bool => $id > 0)));
    if ($categoryIds === []) {
        return;
    }

    $rows = SafeMySQL::gi()->getAll(
        'SELECT category_id, language_code FROM ?n WHERE category_id IN (?a)',
        Constants::CATEGORIES_TABLE,
        $categoryIds
    );
    if ($rows === []) {
        return;
    }

    $grouped = [];
    foreach ($rows as $row) {
        $categoryId = (int) ($row['category_id'] ?? 0);
        $languageCode = trim((string) ($row['language_code'] ?? ENV_DEF_LANG));
        if ($categoryId <= 0 || $languageCode === '') {
            continue;
        }
        $grouped[$languageCode][] = $categoryId;
    }

    foreach ($grouped as $languageCode => $resolvedCategoryIds) {
        scheduleCategoryFiltersRefresh($resolvedCategoryIds, $languageCode);
    }
}

/**
 * Находит все категории, на которые влияют указанные свойства.
 *
 * @param array<int> $propertyIds
 * @return array<int>
 */
function getAffectedCategoryIdsByPropertyIds(array $propertyIds): array {
    $propertyIds = array_values(array_unique(array_filter(array_map('intval', $propertyIds), static fn(int $id): bool => $id > 0)));
    if ($propertyIds === []) {
        return [];
    }

    $categoryIds = SafeMySQL::gi()->getCol(
        "SELECT DISTINCT entity_id
         FROM ?n
         WHERE property_id IN (?a)
           AND entity_type = 'category'",
        Constants::PROPERTY_VALUES_TABLE,
        $propertyIds
    );
    $pageCategoryIds = SafeMySQL::gi()->getCol(
        "SELECT DISTINCT pg.category_id
         FROM ?n AS pv
         INNER JOIN ?n AS pg
            ON pg.page_id = pv.entity_id
           AND pg.language_code = pv.language_code
         WHERE pv.property_id IN (?a)
           AND pv.entity_type = 'page'",
        Constants::PROPERTY_VALUES_TABLE,
        Constants::PAGES_TABLE,
        $propertyIds
    );

    return array_values(array_unique(array_filter(array_map(
        'intval',
        array_merge($categoryIds, $pageCategoryIds)
    ), static fn(int $id): bool => $id > 0)));
}

/**
 * Синхронизирует сущности после изменения состава набора свойств
 * @param int   $setId                ID измененного набора
 * @param array $addedPropertyIds     ID добавленных свойств
 * @param array $deletedPropertyIds   ID удаленных свойств
 */
function syncEntitiesOnSetUpdate(int $setId, array $addedPropertyIds, array $deletedPropertyIds): void {
    try {
        $propertyLifecycle = SysClass::getModelObject('admin', 'm_property_lifecycle');
        if ($propertyLifecycle) {
            $propertyLifecycle->dispatchPropertySetSync($setId, $addedPropertyIds, $deletedPropertyIds);
            return;
        }
        $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
        $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
        $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
        if (!$objectModelCategoriesTypes || !$objectModelCategories || !$objectModelProperties) {
            throw new \Exception('Не удалось получить одну из моделей в syncEntitiesOnSetUpdate');
        }
        $typeIds = $objectModelCategoriesTypes->getCategoryTypeIdsBySet($setId);
        if (empty($typeIds)) {
            return;
        }
        $allRequiredSetIds = $objectModelCategoriesTypes->getCategoriesTypeSetsData($typeIds);
        $allCategoriesIds = [];
        foreach (SafeMySQL::gi()->getAll(
            'SELECT category_id FROM ?n WHERE type_id IN (?a)',
            Constants::CATEGORIES_TABLE,
            $typeIds
        ) as $categoryRow) {
            $allCategoriesIds[] = (int) ($categoryRow['category_id'] ?? 0);
        }
        $allCategoriesIds = array_values(array_unique(array_filter($allCategoriesIds, static fn(int $id): bool => $id > 0)));
        if ($allCategoriesIds === []) {
            return;
        }
        $allPages = SafeMySQL::gi()->getAll(
            'SELECT page_id FROM ?n WHERE category_id IN (?a)',
            Constants::PAGES_TABLE,
            $allCategoriesIds
        );
        $objects = [
            'objectModelProperties' => $objectModelProperties,
            'objectModelCategoriesTypes' => $objectModelCategoriesTypes,
            'objectModelCategories' => $objectModelCategories
        ];
        updatePropertiesForAnEntity(
                $allCategoriesIds,
                $allPages,
                $allRequiredSetIds,
                $objects,
                $setId,
                $addedPropertyIds,
                $deletedPropertyIds
        );
    } catch (\Throwable $e) {
        new ErrorLogger('Hook error (syncEntitiesOnSetUpdate): ' . $e->getMessage(), __FUNCTION__, 'hook_error', [
            'setId' => $setId,
            'addedPropertyIds' => $addedPropertyIds,
            'deletedPropertyIds' => $deletedPropertyIds,
            'trace' => $e->getTraceAsString(),
        ]);
    }
}

/**
 * Обработчик хука перед получением стандартных представлений
 */
function beforeGetStandardViewsHandler(View $view): void {
    return;
}

/**
 * Обработчик хука для обновления связей свойств при смене типа категории или набора свойств
 */
function updateCategoriesTypeSetsHandler(int $typeId, array $setIds, array $allTypeIds): void {
    try {
        $propertyLifecycle = SysClass::getModelObject('admin', 'm_property_lifecycle');
        if ($propertyLifecycle) {
            $propertyLifecycle->dispatchCategoryTypeSync($typeId, $setIds, $allTypeIds);
            return;
        }
        $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
        $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
        $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
        if (!$objectModelCategoriesTypes || !$objectModelCategories || !$objectModelProperties) {
            throw new \Exception('Не удалось получить одну из моделей в updateCategoriesTypeSetsHandler');
        }
        $allCategoriesIds = [];
        foreach (SafeMySQL::gi()->getAll(
            'SELECT category_id FROM ?n WHERE type_id IN (?a)',
            Constants::CATEGORIES_TABLE,
            $allTypeIds
        ) as $categoryRow) {
            $allCategoriesIds[] = (int) ($categoryRow['category_id'] ?? 0);
        }
        $allCategoriesIds = array_values(array_unique(array_filter($allCategoriesIds, static fn(int $id): bool => $id > 0)));
        if ($allCategoriesIds === []) {
            return;
        }
        // Берем актуальные наборы напрямую из БД после обновления связей типов.
        $allCategoriesSetIds = $objectModelCategoriesTypes->getCategoriesTypeSetsData($allTypeIds);
        $allPages = SafeMySQL::gi()->getAll(
            'SELECT page_id FROM ?n WHERE category_id IN (?a)',
            Constants::PAGES_TABLE,
            $allCategoriesIds
        );
        $objects['objectModelProperties'] = $objectModelProperties;
        $objects['objectModelCategoriesTypes'] = $objectModelCategoriesTypes;
        $objects['objectModelCategories'] = $objectModelCategories;
        updatePropertiesForAnEntity($allCategoriesIds, $allPages, $allCategoriesSetIds, $objects);
    } catch (\Throwable $e) {
        new ErrorLogger('Hook error (updateCategoriesTypeSetsHandler): ' . $e->getMessage(), __FUNCTION__, 'hook_error', [
            'typeId' => $typeId, 'setIds' => $setIds, 'allTypeIds' => $allTypeIds, 'trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Обработчик хука ПОСЛЕ обновления значения свойства
 */
function postUpdatePropertiesValueHandler(int $valueId, array $propertyData, string $action): void {
    $entityType = strtolower(trim((string) ($propertyData['entity_type'] ?? '')));
    $entityId = (int) ($propertyData['entity_id'] ?? 0);
    if ($entityId <= 0 || !in_array($entityType, ['page', 'category'], true)) {
        return;
    }
    $languageCode = $propertyData['language_code'] ?? ENV_DEF_LANG;
    scheduleSearchEntityReindex($entityType, $entityId, $languageCode);

    if ($entityType !== 'page') {
        return;
    }

    $pageRow = SafeMySQL::gi()->getRow(
        'SELECT category_id, language_code FROM ?n WHERE page_id = ?i LIMIT 1',
        Constants::PAGES_TABLE,
        $entityId
    );
    $categoryId = (int) ($pageRow['category_id'] ?? 0);
    $languageCode = (string) ($pageRow['language_code'] ?? $languageCode);
    if ($categoryId > 0) {
        scheduleCategoryFiltersRefresh([$categoryId], $languageCode);
    }
}

/**
 * Обработчик хука ПЕРЕД обновлением значения свойства
 */
function preUpdatePropertiesHandler(?int $valueId, array $propertyData): void {
    return;
}

/**
 * Обработчик хука для синхронизации свойств при смене типа категории
 */
function afterUpdateCategoryDataHandler(int $categoryId, array $categoryData, string $method): void {
    if ($method == 'update' && !empty($categoryData['oldCategoryType'])) {
        try {
            $languageCode = (string) ($categoryData['language_code'] ?? ENV_DEF_LANG);
            $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
            $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
            $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
            if (!$objectModelCategoriesTypes || !$objectModelCategories || !$objectModelProperties) {
                throw new \Exception('Не удалось получить одну из моделей для синхронизации типов');
            }
            $allTypes = $objectModelCategoriesTypes->getAllTypes(false, true, $categoryData['type_id'], $languageCode);
            $arrAllTypes = array_unique(array_map(function ($item) {
                        return $item['type_id'];
                    }, $allTypes));
            $oldAllCategory = $objectModelCategories->getCategoryDescendantsShort($categoryId, $languageCode);
            foreach ($oldAllCategory as $cat) {
                if (!in_array($cat['type_id'], $arrAllTypes) || $cat['category_id'] == $categoryId) {
                    // Прямой запрос, чтобы не вызвать хук повторно
                    SafeMySQL::gi()->query('UPDATE ?n SET type_id = ?i WHERE category_id = ?i',
                            Constants::CATEGORIES_TABLE, $categoryData['type_id'], $cat['category_id']);
                    $allPages = $objectModelCategories->getCategoryPages($cat['category_id'], $languageCode);
                    // ID наборов свойств для НОВОГО типа текущей категории $cat['category_id']
                    // Здесь нужно получить ID наборов для $categoryData['type_id'], а не для $allTypeIds
                    $setIdsForNewType = $objectModelCategoriesTypes->getCategoriesTypeSetsData([$categoryData['type_id']]);
                    $objects['objectModelProperties'] = $objectModelProperties;
                    $objects['objectModelCategoriesTypes'] = $objectModelCategoriesTypes;
                    $objects['objectModelCategories'] = $objectModelCategories;
                    // Вызываем синхронизацию свойств для текущей категории/страниц
                    updatePropertiesForAnEntity([$cat['category_id']], $allPages, $setIdsForNewType, $objects);
                }
            }
        } catch (\Throwable $e) {
            new ErrorLogger('Hook error (afterUpdateCategoryDataHandler - type change): ' . $e->getMessage(), __FUNCTION__, 'hook_error', [
                'category_id' => $categoryId, 'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

/**
 * Обработчик хука для синхронизации свойств при смене категории страницы
 */
function afterUpdatePageDataHandler(int $pageId, array $pageData, string $method): void {
    if ($method !== 'update') {
        return;
    }
    $newCategoryId = (int) ($pageData['category_id'] ?? 0);
    $oldCategoryId = (int) ($pageData['old_category_id'] ?? 0);
    if ($pageId <= 0 || $newCategoryId <= 0 || $oldCategoryId <= 0 || $newCategoryId === $oldCategoryId) {
        return;
    }
    try {
        $languageCode = (string) ($pageData['language_code'] ?? ENV_DEF_LANG);
        $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
        $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
        $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
        if (!$objectModelCategoriesTypes || !$objectModelCategories || !$objectModelProperties) {
            throw new \Exception('Не удалось получить одну из моделей для синхронизации страницы');
        }
        $newTypeId = (int) $objectModelCategories->getCategoryTypeId($newCategoryId, $languageCode);
        if ($newTypeId <= 0) {
            return;
        }
        $setIdsForNewType = $objectModelCategoriesTypes->getCategoriesTypeSetsData([$newTypeId]);
        $objects['objectModelProperties'] = $objectModelProperties;
        $objects['objectModelCategoriesTypes'] = $objectModelCategoriesTypes;
        $objects['objectModelCategories'] = $objectModelCategories;
        updatePropertiesForAnEntity([$newCategoryId], [['page_id' => $pageId]], $setIdsForNewType, $objects);
    } catch (\Throwable $e) {
        new ErrorLogger('Hook error (afterUpdatePageDataHandler): ' . $e->getMessage(), __FUNCTION__, 'hook_error', [
            'page_id' => $pageId,
            'new_category_id' => $newCategoryId,
            'old_category_id' => $oldCategoryId,
            'trace' => $e->getTraceAsString(),
        ]);
    }
}

/**
 * Обработчик хука для пересчёта materialized-фильтров после сохранения страницы.
 */
function afterUpdatePageFiltersHandler(int $pageId, array $pageData, string $method): void {
    if ($pageId <= 0 || !in_array($method, ['insert', 'update'], true)) {
        return;
    }

    $languageCode = (string) ($pageData['language_code'] ?? ENV_DEF_LANG);
    $categoryIds = [];
    $newCategoryId = (int) ($pageData['category_id'] ?? 0);
    $oldCategoryId = (int) ($pageData['old_category_id'] ?? 0);
    if ($newCategoryId > 0) {
        $categoryIds[] = $newCategoryId;
    }
    if ($oldCategoryId > 0) {
        $categoryIds[] = $oldCategoryId;
    }
    if ($categoryIds !== []) {
        scheduleCategoryFiltersRefresh($categoryIds, $languageCode);
    }
}

/**
 * Пересчитывает фильтры при структурном изменении категории.
 */
function afterUpdateCategoryFiltersHandler(int $categoryId, array $categoryData, string $method): void {
    if ($categoryId <= 0) {
        return;
    }

    $languageCode = (string) ($categoryData['language_code'] ?? ENV_DEF_LANG);
    $categoryIds = [$categoryId];
    $oldParentId = (int) ($categoryData['old_parent_id'] ?? 0);
    $newParentId = (int) ($categoryData['parent_id'] ?? 0);
    if ($oldParentId > 0) {
        $categoryIds[] = $oldParentId;
    }
    if ($newParentId > 0) {
        $categoryIds[] = $newParentId;
    }
    scheduleCategoryFiltersRefresh($categoryIds, $languageCode);
}

/**
 * Обработчик хука для индексации категории после сохранения
 */
function createSearchIndexCategory(int $categoryId, array $categoryData, string $method): void {
    if ($categoryId <= 0) {
        return;
    }
    try {
        scheduleSearchEntityReindex('category', $categoryId, $categoryData['language_code'] ?? ENV_DEF_LANG);
    } catch (\Throwable $e) {
        new ErrorLogger('Hook error (createSearchIndexCategory): ' . $e->getMessage(), __FUNCTION__, 'hook_error', [
            'category_id' => $categoryId, 'method' => $method, 'trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Обработчик хука для удаления категории из индекса
 */
function deleteSearchIndexCategory(int $categoryId, $categoryData = null, string $method = 'delete'): void {
    if ($categoryId <= 0) {
        return;
    }
    try {
        $searchEngine = new ClassSearchEngine();
        $searchEngine->removeIndexEntry('category', $categoryId);
    } catch (\Throwable $e) {
        new ErrorLogger('Hook error (deleteSearchIndexCategory): ' . $e->getMessage(), __FUNCTION__, 'hook_error', [
            'category_id' => $categoryId, 'method' => $method, 'trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Удаляет materialized-фильтры категории после её удаления.
 */
function deleteCategoryFiltersHandler(int $categoryId, $categoryData = null, string $method = 'delete'): void {
    if ($categoryId <= 0) {
        return;
    }
    try {
        $languageCode = is_array($categoryData) && !empty($categoryData['language_code'])
            ? (string) $categoryData['language_code']
            : ENV_DEF_LANG;
        $serviceModel = SysClass::getModelObject('admin', 'm_filters');
        if ($serviceModel) {
            $serviceModel->clearFilters('category', $categoryId, $languageCode);
        }
        $oldParentId = is_array($categoryData) ? (int) ($categoryData['parent_id'] ?? 0) : 0;
        if ($oldParentId > 0) {
            scheduleCategoryFiltersRefresh([$oldParentId], $languageCode);
        }
    } catch (\Throwable $e) {
        new ErrorLogger('Hook error (deleteCategoryFiltersHandler): ' . $e->getMessage(), __FUNCTION__, 'hook_error', [
            'category_id' => $categoryId,
            'method' => $method,
            'trace' => $e->getTraceAsString(),
        ]);
    }
}

/**
 * Обработчик хука для индексации страницы после сохранения
 */
function createSearchIndexPage(int $pageId, array $pageData, string $method): void {
    if ($pageId <= 0) {
        return;
    }
    try {
        scheduleSearchEntityReindex('page', $pageId, $pageData['language_code'] ?? ENV_DEF_LANG);
    } catch (\Throwable $e) {
        new ErrorLogger('Hook error (createSearchIndexPage): ' . $e->getMessage(), __FUNCTION__, 'hook_error', [
            'page_id' => $pageId, 'method' => $method, 'trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Обработчик хука для удаления страницы из индекса
 */
function deleteSearchIndexPage(int $pageId, $pageData = null, string $method = 'delete'): void {
    if ($pageId <= 0) {
        return;
    }
    try {
        $searchEngine = new ClassSearchEngine();
        $searchEngine->removeIndexEntry('page', $pageId);
    } catch (\Throwable $e) {
        new ErrorLogger('Hook error (deleteSearchIndexPage): ' . $e->getMessage(), __FUNCTION__, 'hook_error', [
            'page_id' => $pageId, 'method' => $method, 'trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Пересчитывает category-фильтры после удаления страницы.
 */
function deletePageFiltersHandler(int $pageId, $pageData = null, string $method = 'delete'): void {
    if ($pageId <= 0 || !is_array($pageData)) {
        return;
    }
    $categoryId = (int) ($pageData['category_id'] ?? 0);
    $languageCode = (string) ($pageData['language_code'] ?? ENV_DEF_LANG);
    if ($categoryId > 0) {
        scheduleCategoryFiltersRefresh([$categoryId], $languageCode);
    }
}

/**
 * Пересчитывает category-фильтры после rebuild конкретного свойства.
 */
function afterPropertyLifecycleRebuildFiltersHandler(int $propertyId, array $beforePropertyData, array $afterPropertyData, array $result): void {
    if ($propertyId <= 0) {
        return;
    }
    $categoryIds = getAffectedCategoryIdsByPropertyIds([$propertyId]);
    if ($categoryIds !== []) {
        scheduleCategoryFiltersRefreshResolved($categoryIds);
    }
}

/**
 * Пересчитывает category-фильтры после rebuild типа свойств.
 */
function afterPropertyTypeLifecycleRebuildFiltersHandler(int $typeId, array $beforeTypeData, array $afterTypeData, array $result): void {
    if ($typeId <= 0) {
        return;
    }
    $propertyIds = SafeMySQL::gi()->getCol(
        'SELECT property_id FROM ?n WHERE type_id = ?i',
        Constants::PROPERTIES_TABLE,
        $typeId
    );
    $propertyIds = array_values(array_unique(array_filter(array_map('intval', $propertyIds), static fn(int $id): bool => $id > 0)));
    if ($propertyIds === []) {
        return;
    }
    $categoryIds = getAffectedCategoryIdsByPropertyIds($propertyIds);
    if ($categoryIds !== []) {
        scheduleCategoryFiltersRefreshResolved($categoryIds);
    }
}

/**
 * Синхронизирует значения свойств для категорий и страниц.
 * Пересчитывает состав значений по актуальным наборам и удаляет устаревшие
 * или недостающие записи пакетными чанками.
 */
function updatePropertiesForAnEntity(
    array $allCategoriesIds,
    array $allPages,
    array $allRequiredSetIds,
    array $models,
    ?int $changedSetId = null,
    array $addedPropertyIds = [],
    array $deletedPropertyIds = []
): array {
    $categoryIds = array_values(array_unique(array_filter(
        array_map('intval', $allCategoriesIds),
        static fn(int $id): bool => $id > 0
    )));

    $pageIds = [];
    foreach ($allPages as $page) {
        if (is_array($page) && isset($page['page_id'])) {
            $pageIds[] = (int) $page['page_id'];
            continue;
        }
        if (is_scalar($page)) {
            $pageIds[] = (int) $page;
        }
    }
    $pageIds = array_values(array_unique(array_filter($pageIds, static fn(int $id): bool => $id > 0)));

    $result = [
        'processed_categories' => count($categoryIds),
        'processed_pages' => count($pageIds),
        'deleted_values' => 0,
        'inserted_values' => 0,
    ];

    if (empty($categoryIds) && empty($pageIds)) {
        return $result;
    }

    $requiredSetIds = array_values(array_unique(array_filter(
        array_map('intval', $allRequiredSetIds),
        static fn(int $id): bool => $id > 0
    )));
    $addedPropertyIds = array_values(array_unique(array_filter(
        array_map('intval', $addedPropertyIds),
        static fn(int $id): bool => $id > 0
    )));
    $deletedPropertyIds = array_values(array_unique(array_filter(
        array_map('intval', $deletedPropertyIds),
        static fn(int $id): bool => $id > 0
    )));
    $changedSetId = ($changedSetId !== null && $changedSetId > 0) ? $changedSetId : null;
    $runtimeConfig = Hook::filter('propertyLifecycleRuntimeConfig', [
        'chunk_size' => 200,
        'memory_limit' => '512M',
        'set_time_limit' => 0,
    ], 'entity_sync', [
        'category_ids' => $categoryIds,
        'page_ids' => $pageIds,
        'required_set_ids' => $requiredSetIds,
        'changed_set_id' => $changedSetId,
    ]);
    $chunkSize = max(1, (int) ($runtimeConfig['chunk_size'] ?? 200));
    if (!empty($runtimeConfig['memory_limit'])) {
        @ini_set('memory_limit', (string) $runtimeConfig['memory_limit']);
    }
    if (isset($runtimeConfig['set_time_limit'])) {
        @set_time_limit((int) $runtimeConfig['set_time_limit']);
    }

    $requiredSetsByCategory = [];
    $requiredSetsByPage = [];

    if (!empty($requiredSetIds)) {
        $entitiesData = $models['objectModelCategoriesTypes']->getCategorySetPageData($requiredSetIds);
        foreach ($entitiesData as $entity) {
            $categoryId = (int) ($entity['category_id'] ?? 0);
            $setId = (int) ($entity['set_id'] ?? 0);
            if ($categoryId <= 0 || $setId <= 0 || !in_array($categoryId, $categoryIds, true)) {
                continue;
            }
            $requiredSetsByCategory[$categoryId][$setId] = true;

            $pageId = (int) ($entity['page_id'] ?? 0);
            if ($pageId > 0 && in_array($pageId, $pageIds, true)) {
                $requiredSetsByPage[$pageId][$setId] = true;
            }
        }
    }

    foreach ($categoryIds as $categoryId) {
        $requiredSetsByCategory[$categoryId] = array_keys($requiredSetsByCategory[$categoryId] ?? []);
    }
    foreach ($pageIds as $pageId) {
        $requiredSetsByPage[$pageId] = array_keys($requiredSetsByPage[$pageId] ?? []);
    }

    $valueIdsToDelete = [];
    $existingValuesMap = [];
    $scanExistingProps = function (array $entityIds, string $entityType) use (
        $chunkSize,
        $models,
        $requiredSetsByCategory,
        $requiredSetsByPage,
        $changedSetId,
        $deletedPropertyIds,
        &$valueIdsToDelete,
        &$existingValuesMap
    ): void {
        foreach (array_chunk($entityIds, $chunkSize) as $entityIdsChunk) {
            if (empty($entityIdsChunk)) {
                continue;
            }
            $currentProps = $models['objectModelProperties']->getPropertiesValuesForEntity($entityIdsChunk, $entityType);
            foreach ($currentProps as $prop) {
                $valueId = (int) ($prop['value_id'] ?? 0);
                $entityId = (int) ($prop['entity_id'] ?? 0);
                $setId = (int) ($prop['set_id'] ?? 0);
                $propertyId = (int) ($prop['property_id'] ?? 0);
                if ($valueId <= 0 || $entityId <= 0 || $setId <= 0 || $propertyId <= 0) {
                    continue;
                }

                $requiredSets = $entityType === 'category'
                    ? ($requiredSetsByCategory[$entityId] ?? [])
                    : ($entityType === 'page' ? ($requiredSetsByPage[$entityId] ?? []) : []);

                $mustDelete = !in_array($setId, $requiredSets, true);
                if (!$mustDelete && $changedSetId !== null && $setId === $changedSetId && !empty($deletedPropertyIds)) {
                    $mustDelete = in_array($propertyId, $deletedPropertyIds, true);
                }

                if ($mustDelete) {
                    $valueIdsToDelete[$valueId] = $valueId;
                    continue;
                }

                $existingValuesMap[$entityType][$entityId][$setId][$propertyId] = true;
            }
        }
    };

    $scanExistingProps($categoryIds, 'category');
    $scanExistingProps($pageIds, 'page');

    if (!empty($valueIdsToDelete)) {
        foreach (array_chunk(array_values($valueIdsToDelete), $chunkSize) as $valueIdsChunk) {
            $models['objectModelProperties']->deletePropertyValues($valueIdsChunk);
            $result['deleted_values'] += count($valueIdsChunk);
        }
    }

    if (empty($requiredSetIds)) {
        if (!empty($categoryIds)) {
            scheduleCategoryFiltersRefreshResolved($categoryIds);
        }
        return $result;
    }

    $setsData = $models['objectModelProperties']->getPropertySetsData(
        false,
        'set_id IN (' . implode(',', $requiredSetIds) . ')'
    )['data'] ?? [];
    if (empty($setsData)) {
        if (!empty($categoryIds)) {
            scheduleCategoryFiltersRefreshResolved($categoryIds);
        }
        return $result;
    }

    $prepareDefaultValues = static function (string $defaultValues): string {
        if (trim($defaultValues) === '') {
            return '[]';
        }
        $decoded = json_decode($defaultValues, true);
        if (!is_array($decoded)) {
            return '[]';
        }
        foreach ($decoded as &$item) {
            if (!is_array($item)) {
                continue;
            }
            if (!array_key_exists('value', $item)) {
                $item['value'] = $item['default'] ?? null;
            }
            unset($item['default']);
        }
        return json_encode($decoded, JSON_UNESCAPED_UNICODE) ?: '[]';
    };

    $insertMissingValues = function (int $entityId, string $entityType, array $entitySetIds) use (
        $setsData,
        $models,
        $prepareDefaultValues,
        $changedSetId,
        $addedPropertyIds,
        &$existingValuesMap,
        &$result
    ): void {
        foreach ($entitySetIds as $setId) {
            if ($changedSetId !== null && $setId !== $changedSetId) {
                continue;
            }
            if (empty($setsData[$setId]['properties']) || !is_array($setsData[$setId]['properties'])) {
                continue;
            }
            foreach ($setsData[$setId]['properties'] as $property) {
                $propertyId = (int) ($property['property_id'] ?? 0);
                if ($propertyId <= 0) {
                    continue;
                }

                $propertyEntityType = (string) ($property['property_entity_type'] ?? 'all');
                if ($propertyEntityType !== 'all' && $propertyEntityType !== $entityType) {
                    continue;
                }

                if ($changedSetId !== null) {
                    if (empty($addedPropertyIds) || !in_array($propertyId, $addedPropertyIds, true)) {
                        continue;
                    }
                }

                if (!empty($existingValuesMap[$entityType][$entityId][$setId][$propertyId])) {
                    continue;
                }

                $fields = [
                    'entity_id' => $entityId,
                    'property_id' => $propertyId,
                    'entity_type' => $entityType,
                    'fields' => $prepareDefaultValues((string) ($property['default_values'] ?? '[]')),
                    'set_id' => $setId,
                ];
                $writeResult = $models['objectModelProperties']->updatePropertiesValueEntities($fields);
                if ($writeResult !== false) {
                    $existingValuesMap[$entityType][$entityId][$setId][$propertyId] = true;
                    $result['inserted_values']++;
                }
            }
        }
    };

    foreach ($categoryIds as $categoryId) {
        $insertMissingValues($categoryId, 'category', $requiredSetsByCategory[$categoryId] ?? []);
    }
    foreach ($pageIds as $pageId) {
        $insertMissingValues($pageId, 'page', $requiredSetsByPage[$pageId] ?? []);
    }
    if (!empty($categoryIds)) {
        scheduleCategoryFiltersRefreshResolved($categoryIds);
    }
    return $result;
}
