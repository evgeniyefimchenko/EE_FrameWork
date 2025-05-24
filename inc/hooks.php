<?php

use classes\system\SysClass;
use classes\system\Hook;
use classes\helpers\ClassSearchEngine;
use classes\system\ErrorLogger;

Hook::add('A_beforeGetStandardViews', 'beforeGetStandardViewsHandler');

/**
 * Вызывается после обновления категории
 */
Hook::add('afterUpdateCategoryData', 'afterUpdateCategoryDataHandler');

/**
 * Вызывается после обновления связи между типами категориями и наборами свойств
 * @param int $typeId - Переданный тип для обновления 
 * @param array $setIds - Идентификаторы наборов свойств для связывания с указанным типом категории
 * @param array $allTypeIds - Все IDs включая переданный тип и его потомков
 */
Hook::add('postUpdateCategoriesTypeSetsData', 'updateCategoriesTypeSetsHandler');

/**
 * Вызывается после обновления или добавления значения свойства для сущностей
 * @param int $value_id - ID свойства в Constants::PROPERTY_VALUES_TABLE
 * @param array $propertyData - Набор данных
 * @param string $action - выполненное действие update или insert
 */
Hook::add('postUpdatePropertiesValueEntities', 'postUpdatePropertiesValueHandler');

/**
 * Вызывается перед обновлением или добавлением значения свойства для сущностей
 * @param int $value_id - ID свойства в Constants::PROPERTY_VALUES_TABLE
 * @param array $propertyData - Набор данных
 */
Hook::add('preUpdatePropertiesValueEntities', 'preUpdatePropertiesHandler');

function beforeGetStandardViewsHandler(classes\system\View $view): void {
    return;
}

/**
 * Обновление типа категории
 * @param int $typeId Идентификатор типа категории для обновления связей
 * @param int|array $setIds Идентификаторы наборов свойств для связывания с указанным типом категории
 * @param array $allTypeIds - все подчинённые типы $type_id включая $type_id
 */
function updateCategoriesTypeSetsHandler($typeId, $setIds, $allTypeIds) {
    $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
    $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
    $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
    $allCategoriesIds = $objectModelCategoriesTypes->getAllCategoriesByType($allTypeIds);
    $allCategoriesSetIds = $objectModelCategoriesTypes->getCategoriesTypeSetsData($allTypeIds);
    $allPages = $objectModelCategories->getCategoryPages($allCategoriesIds);
    $objects['objectModelProperties'] = $objectModelProperties;
    $objects['objectModelCategoriesTypes'] = $objectModelCategoriesTypes;
    $objects['objectModelCategories'] = $objectModelCategories;
    updatePropertiesForAnEntity($allCategoriesIds, $allPages, $allCategoriesSetIds, $objects);
}

function postUpdatePropertiesValueHandler($value_id, $propertyData, $action) {
    return;
}

function preUpdatePropertiesHandler($value_id, $propertyData) {
    return;
}

/**
 * Обновление данных категории
 * @param type $categoryId - ID созданной или обновлённой категории
 * @param type $categoryData - данные категории
 * @param type $method - метод update/insert
 */
function afterUpdateCategoryDataHandler($categoryId, $categoryData, $method) {
    if ($method == 'update') {
        if (!empty($categoryData['oldCategoryType'])) { // Смена типа одной категории, $categoryData['oldCategoryType'] содержит старый тип            
            $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
            $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
            $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
            // Все типы с потомками для проверки дочерних категорий
            $allTypes = $objectModelCategoriesTypes->getAllTypes(false, true, $categoryData['type_id']);
            $arrAllTypes = array_unique(array_map(function ($item) {
                        return $item['type_id'];
                    }, $allTypes));
            // Получим все дочерние категории вместе с $categoryId
            $oldAllCategory = $objectModelCategories->getCategoryDescendantsShort($categoryId);
            foreach ($oldAllCategory as $cat) {
                // У дочерней категории установлен не новый тип и не его потомки, присваиваем родительский
                // Или это обновлённая категория
                if (!in_array($cat['type_id'], $arrAllTypes) || $cat['category_id'] == $categoryId) {
                    // Прямой запрос что бы не вызвать зацикливания хука                    
                    \classes\plugins\SafeMySQL::gi()->query('UPDATE ?n SET type_id = ?i WHERE category_id = ?i',
                            \classes\system\Constants::CATEGORIES_TABLE, $categoryData['type_id'], $cat['category_id']);
                    // Получили все значения свойств объектов                    
                    $allPages = $objectModelCategories->getCategoryPages($categoryData['category_id']);
                    $allCategoriesSetIds = $objectModelCategoriesTypes->getCategoriesTypeSetsData($categoryData['type_id']);
                    $objects['objectModelProperties'] = $objectModelProperties;
                    $objects['objectModelCategoriesTypes'] = $objectModelCategoriesTypes;
                    $objects['objectModelCategories'] = $objectModelCategories;
                    // Перезаписываем значения свойств у категории и её страниц
                    // file_put_contents(__DIR__ . '/updateCategory_logs.txt', var_export($cat, true) . PHP_EOL, FILE_APPEND);                    
                    updatePropertiesForAnEntity([$cat['category_id']], $allPages, $allCategoriesSetIds, $objects);
                }
            }
        }
    }
}

// // file_put_contents(__DIR__ . '/logs.txt', var_export($cat, true) . PHP_EOL, FILE_APPEND);
/**
 * Проверяет и синхронизирует наборы свойств для указанных категорий и их сущностей
 * 1. Получает все категории и сущности для указанных типов
 * 2. Получает значения свойств для всех категорий и сущностей
 * 3. Удаляет значения свойств, которые не соответствуют переданным наборам
 * 4. Обновляет или добавляет новые значения свойств для категорий и сущностей на основе новых наборов
 */
function updatePropertiesForAnEntity($allCategoriesIds, $allPages, $allCategoriesSetIds, $objects) {
    $allNewSetIdsCategories = $allCategoriesSetIds;
    $allPagesIds = [];
    foreach ($allPages as $page) {
        $allPagesIds[] = $page['page_id'];
    }
    // Получили все значения свойств объектов
    $allPropertiesCategories = $objects['objectModelProperties']->getPropertiesValuesForEntity($allCategoriesIds, 'category');
    $allPropertiesPages = $objects['objectModelProperties']->getPropertiesValuesForEntity($allPagesIds, 'page');
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
    foreach ($allPropertiesPages as $prop) {
        if (!in_array($prop['set_id'], $allCategoriesSetIds)) {
            $killValueIds[] = $prop['value_id'];
        } else {
            $existPropEntities[] = $prop;
        }
    }
    if (count($killValueIds)) {
        // file_put_contents(__DIR__ . '/killValueIds_logs.txt', var_export($killValueIds, true) . PHP_EOL, FILE_APPEND);
        $objects['objectModelProperties']->deletePropertyValues($killValueIds);
    }
    // Получить существующие свойства новых сетов с дефолтными настройками
    if (count($allNewSetIdsCategories)) {
        // Получение данных по всем наборам
        $allPropertiesByNewSet = $objects['objectModelProperties']->getPropertySetsData(false, 'set_id IN (' . implode(',', $allNewSetIdsCategories) . ')')['data'];
        // Получение данных по категориям и сущностям
        $allCategorySetAndPageData = $objects['objectModelCategoriesTypes']->getCategorySetPageData($allNewSetIdsCategories);
        if (empty($allCategorySetAndPageData)) {
            return;
        }
        $cacheCategoryId = 0;
        foreach ($allCategorySetAndPageData as $c_item) {
            $updateProperties = function ($entityId, $entityType) use ($c_item, $allPropertiesByNewSet, $objects) {
                foreach ($allPropertiesByNewSet[$c_item['set_id']]['properties'] as $p_item) {
                    // Устанавливаем значение value для каждого поля по его default
                    $p_item['default_values'] = json_decode($p_item['default_values'], true);
                    if (!empty($p_item['default_values'])) {
                        foreach ($p_item['default_values'] as &$propDefault) {
                            $propDefault['value'] = isset($propDefault['default']) ? $propDefault['default'] : null;
                            unset($propDefault['default']);
                        }
                        $p_item['default_values'] = json_encode($p_item['default_values']);
                    }
                    if ($p_item['property_entity_type'] == $entityType || $p_item['property_entity_type'] == 'all') {
                        $fields = [
                            'entity_id' => $entityId,
                            'property_id' => $p_item['property_id'],
                            'entity_type' => $entityType,
                            'fields' => $p_item['default_values'],
                            'set_id' => $c_item['set_id'],
                        ];
                        // file_put_contents(__DIR__ . '/updateProperties_logs.txt', var_export($fields, true) . PHP_EOL, FILE_APPEND);
                        $objects['objectModelProperties']->updatePropertiesValueEntities($fields);
                    }
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

Hook::add('afterUpdateCategoryData', 'createSearchIndexCategory');

/**
 * Обработчик хука для индексации категории после сохранения
 * @param int $categoryId ID сохраненной категории
 * @param array $categoryData Данные, переданные в метод сохранения
 * @param string $method Метод ('insert' или 'update')
 * @return void
 */
function createSearchIndexCategory(int $categoryId, array $categoryData, string $method): void {
    if ($categoryId <= 0)
        return;
    try {
        $searchEngine = new ClassSearchEngine();
        if (!isset($categoryData['status']) || $categoryData['status'] !== 'active') {
            $searchEngine->removeIndexEntry('category', $categoryId);
            return;
        }
        $entityType = 'category';
        $languageCode = $categoryData['language_code'] ?? ENV_DEF_LANG;
        $title = ClassSearchEngine::prepareTitle($categoryData['title'] ?? '');
        $contentParts = [];
        $contentParts[] = $categoryData['title'] ?? '';
        $contentParts[] = $categoryData['short_description'] ?? ($categoryData['description'] ?? '');
        $contentParts[] = $categoryData['description'] ?? '';
        $contentFull = ClassSearchEngine::prepareContent(implode(' ', $contentParts));
        if (empty($title) && empty($contentFull)) {
            $searchEngine->removeIndexEntry($entityType, $categoryId);
            return;
        }
        $url = '';
        $indexData = [
            'entity_id' => $categoryId,
            'entity_type' => $entityType,
            'language_code' => $languageCode,
            'title' => $title,
            'content_full' => $contentFull,
            'url' => $url
        ];
        $searchEngine->updateIndexEntry($indexData);
    } catch (\Throwable $e) { // Ловим Throwable
        new ErrorLogger('Hook error (createSearchIndexCategory): ' . $e->getMessage(), __FUNCTION__, 'hook_error', [
            'category_id' => $categoryId, 'method' => $method, 'trace' => $e->getTraceAsString()
        ]);
    }
}
