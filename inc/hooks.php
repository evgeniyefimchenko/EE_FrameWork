<?php

use classes\system\SysClass;
use classes\system\Hook;

Hook::add('A_beforeGetStandardViews', 'A_beforeGetStandardViewsHandler');

/**
 * Вызывается после обновления связи между типами категориями и наборами свойств
 * @param type $type_id - Переданный тип для обновления 
 * @param type $set_ids - Идентификаторы наборов свойств для связывания с указанным типом категории
 * @param type $allTypeIds - Все IDs включая переданный тип и его потомков
 * @return type
 */
Hook::add('A_updateCategoriesTypeSetsData', 'checkRemoteSets', 10);
Hook::add('A_updateCategoriesTypeSetsData', 'checkAddSets', 20);

function A_beforeGetStandardViewsHandler($view) {
    return;
}

function checkRemoteSets($type_id, $set_ids, $allTypeIds) {
    $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
    $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
    $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
    $allCategoriesIds = $objectModelCategoriesTypes->getAllCategoriesByType($allTypeIds);
    $allCategoriesSetIds = $objectModelCategoriesTypes->getCategoriesTypeSetsData($allTypeIds);
    $allEntities = $objectModelCategories->getCategoryEntities($allCategoriesIds);    
    $allEntitiesIds = [];
    foreach ($allEntities as $item) {
        $allEntitiesIds[] = $item['entity_id'];
    }
    // Получили все значения свойств объектов
    $allPropertiesCategories = $objectModelProperties->getPropertiesValuesForEntity($allCategoriesIds, 'category');
    $allPropertiesEntities = $objectModelProperties->getPropertiesValuesForEntity($allEntitiesIds, 'entity');        
    // Удаляем все значения свойств объектов которых нет в новых наборах
    $killValueIds = [];
    foreach ($allPropertiesCategories as $item) {
        if (!in_array($item['set_id'], $allCategoriesSetIds)) {
            $killValueIds[] = $item['value_id'];
        }
    }
    foreach ($allPropertiesEntities as $item) {
        if (!in_array($item['set_id'], $allCategoriesSetIds)) {
            $killValueIds[] = $item['value_id'];
        }
    }
    if (count($killValueIds)) {
        $objectModelProperties->deletePropertyValues($killValueIds);
    }
}

function checkAddSets($type_id, $set_ids, $allTypeIds) {
    
}