<?php

use classes\system\SysClass;
use classes\system\Hook;

Hook::add('A_beforeGetStandardViews', 'A_beforeGetStandardViewsHandler');

/**
 * Вызывается после обновления связи между типами категориями и наборами свойств
 * @param int $typeId - Переданный тип для обновления 
 * @param array $setIds - Идентификаторы наборов свойств для связывания с указанным типом категории
 * @param array $allTypeIds - Все IDs включая переданный тип и его потомков
 */
Hook::add('A_updateCategoriesTypeSetsData', 'checkModifiedSets');

function A_beforeGetStandardViewsHandler(classes\system\View $view): void {
    return;
}

/**
 * Проверяет и синхронизирует наборы свойств для указанных категорий и их сущностей
 * 1. Получает все категории и сущности для указанных типов
 * 2. Получает значения свойств для всех категорий и сущностей
 * 3. Удаляет значения свойств, которые не соответствуют переданным наборам
 * 4. Обновляет или добавляет новые значения свойств для категорий и сущностей на основе новых наборов
 */
function checkModifiedSets($typeId, $setIds, $allTypeIds) {
    $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
    $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
    $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
    $allCategoriesIds = $objectModelCategoriesTypes->getAllCategoriesByType($allTypeIds);
    $allCategoriesSetIds = $allNewSetIdsCategories = $objectModelCategoriesTypes->getCategoriesTypeSetsData($allTypeIds);
    $allPages = $objectModelCategories->getCategoryPages($allCategoriesIds);
    $allPagesIds = [];
    foreach ($allPages as $page) {
        $allPagesIds[] = $page['page_id'];
    }
    // Получили все значения свойств объектов
    $allPropertiesCategories = $objectModelProperties->getPropertiesValuesForEntity($allCategoriesIds, 'category');
    $allPropertiesEntities = $objectModelProperties->getPropertiesValuesForEntity($allPagesIds, 'page');
    // Удаляем все значения свойств объектов которых нет в новых наборах
    $killValueIds = $existPropCategogies = $existPropEntities = [];
    foreach ($allPropertiesCategories as $prop) {
        if (!in_array($prop['set_id'], $allCategoriesSetIds)) {
            $killValueIds[] = $prop['value_id'];
        } else {
            $existPropCategogies[] = $prop;
            unset($allNewSetIdsCategories[array_search($prop['set_id'], $allNewSetIdsCategories)]);
        }
    }
    foreach ($allPropertiesEntities as $prop) {
        if (!in_array($prop['set_id'], $allCategoriesSetIds)) {
            $killValueIds[] = $prop['value_id'];
        } else {
            $existPropEntities[] = $prop;
        }
    }
    if (count($killValueIds)) {
        $objectModelProperties->deletePropertyValues($killValueIds);
    }
    // Получить существующие свойства новых сетов
    if (count($allNewSetIdsCategories)) {
        // Получение данных по всем наборам
        $allPropertiesByNewSet = $objectModelProperties->getPropertySetsData(false, 'set_id IN (' . implode(',', $allNewSetIdsCategories) . ')')['data'];
        // Получение данных по категориям и сущностям
        $allCategorySetAndPageData = $objectModelCategoriesTypes->getCategorySetPageData($allNewSetIdsCategories);
        if (empty($allCategorySetAndPageData)) {
            return;
        }
        $cacheCategoryId = 0;
        foreach ($allCategorySetAndPageData as $c_item) {
            $updateProperties = function ($entityId, $entityType) use ($c_item, $allPropertiesByNewSet, $objectModelProperties) {
                foreach ($allPropertiesByNewSet[$c_item['set_id']]['properties'] as $p_item) {
                    $fields = [
                        'entity_id' => $entityId,
                        'property_id' => $p_item['p_id'],
                        'entity_type' => $entityType,
                        'property_values' => $p_item['default_values'],
                        'set_id' => $c_item['set_id'],
                    ];
                    $objectModelProperties->updatePropertiesValueEntities($fields);
                }
            };
            if ($cacheCategoryId != $c_item['category_id']) {
                $updateProperties($c_item['category_id'], 'category');
                $cacheCategoryId = $c_item['category_id'];
            }
            if ($c_item['page_id']) {
                $updateProperties($c_item['page_id'], 'page');
            }
        }
    }
}
