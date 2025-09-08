<?php

/**
 * Евгений Ефимченко, efimchenko.com
 * Реализует обработчики хуков.
 * /inc/hooks.php
 */
use classes\system\SysClass;
use classes\system\Hook;
use classes\helpers\ClassSearchEngine;
use classes\system\ErrorLogger;
use classes\system\Constants;
use classes\system\View;
use classes\plugins\SafeMySQL;

// === Регистрация хуков ===

/**
 * Вызывается перед получением стандартных представлений в контроллере
 * @param View $view Объект представления
 */
Hook::add('A_beforeGetStandardViews', 'beforeGetStandardViewsHandler');

/**
 * Вызывается ПОСЛЕ обновления/создания данных категории в ModelCategories::updateCategoryData
 * @param int $categoryId ID созданной или обновлённой категории
 * @param array $categoryData Массив данных, переданных в updateCategoryData (может содержать ключ 'oldCategoryType' при смене типа)
 * @param string $method Метод ('insert' или 'update')
 */
Hook::add('afterUpdateCategoryData', 'afterUpdateCategoryDataHandler'); // Для синхронизации свойств при смене типа
Hook::add('afterUpdateCategoryData', 'createSearchIndexCategory'); // Для поисковой индексации

/**
 * Вызывается ПОСЛЕ удаления категории (предполагается из ModelCategories::deleteCategory)
 * @param int $categoryId ID удаляемой категории
 * @param mixed $categoryData Дополнительные данные (могут быть null или отсутствовать)
 * @param string $method Метод ('delete')
 */
Hook::add('afterDeleteCategory', 'deleteSearchIndexCategory');

/**
 * Вызывается ПОСЛЕ обновления/создания данных страницы в ModelPages::updatePageData
 * @param int $pageId ID созданной или обновлённой страницы
 * @param array $pageData Массив данных, переданных в updatePageData
 * @param string $method Метод ('insert' или 'update')
 */
Hook::add('afterUpdatePageData', 'createSearchIndexPage');

/**
 * Вызывается ПОСЛЕ удаления страницы (предполагается из ModelPages::deletePage)
 * @param int $pageId ID удаляемой страницы
 * @param mixed $pageData Дополнительные данные (могут быть null или отсутствовать)
 * @param string $method Метод ('delete')
 */
Hook::add('afterDeletePage', 'deleteSearchIndexPage');

/**
 * Вызывается ПОСЛЕ обновления связи между типами категориями и наборами свойств
 * @param int $typeId Переданный тип для обновления
 * @param array $setIds Идентификаторы наборов свойств для связывания с указанным типом категории
 * @param array $allTypeIds Все IDs включая переданный тип и его потомков
 */
Hook::add('postUpdateCategoriesTypeSetsData', 'updateCategoriesTypeSetsHandler');

/**
 * Вызывается ПОСЛЕ обновления или добавления значения свойства для сущностей
 * @param int $valueId ID записи значения свойства в Constants::PROPERTY_VALUES_TABLE
 * @param array $propertyData Набор данных свойства (включая entity_type, entity_id)
 * @param string $action Выполненное действие ('update' или 'insert')
 */
Hook::add('postUpdatePropertiesValueEntities', 'postUpdatePropertiesValueHandler');

/**
 * Вызывается ПЕРЕД обновлением или добавлением значения свойства для сущностей
 * @param int $valueId ID записи значения свойства (0 для нового)
 * @param array $propertyData Набор данных свойства
 */
Hook::add('preUpdatePropertiesValueEntities', 'preUpdatePropertiesHandler');

/**
 * Регистрируем хук, который срабатывает после изменения
 * состава свойств в наборе
 * @param int   $setId              ID набора свойств, который был изменен
 * @param array $addedPropertyIds   Массив ID свойств, которые были ДОБАВЛЕНЫ в набор
 * @param array $deletedPropertyIds Массив ID свойств, которые были УДАЛЕНЫ из набора
 */
Hook::add('afterUpdatePropertySetComposition', 'syncEntitiesOnSetUpdate');

// === Функции-обработчики ===

/**
 * Синхронизирует сущности после изменения состава набора свойств
 * @param int   $setId                ID измененного набора
 * @param array $addedPropertyIds     ID добавленных свойств
 * @param array $deletedPropertyIds   ID удаленных свойств
 */
function syncEntitiesOnSetUpdate(int $setId, array $addedPropertyIds, array $deletedPropertyIds): void {
    $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
    $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
    $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
    $typeIds = $objectModelCategoriesTypes->getCategoryTypeIdsBySet($setId);
    if (empty($typeIds)) {
        return;
    }
    $allRequiredSetIds = $objectModelCategoriesTypes->getCategoriesTypeSetsData($typeIds);
    if (empty($allRequiredSetIds)) {
        return;
    }
    $categories = $objectModelCategoriesTypes->getAllCategoriesByType($typeIds);
    if (empty($categories)) {
        return;
    }
    $allCategoriesIds = array_column($categories, 'category_id');
    $allPages = $objectModelCategories->getCategoryPages($allCategoriesIds);
    $objects = [
        'objectModelProperties' => $objectModelProperties,
        'objectModelCategoriesTypes' => $objectModelCategoriesTypes,
        'objectModelCategories' => $objectModelCategories
    ];
    updatePropertiesForAnEntity($allCategoriesIds, $allPages, $allRequiredSetIds, $objects);
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
        $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
        $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
        $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
        if (!$objectModelCategoriesTypes || !$objectModelCategories || !$objectModelProperties) {
            throw new \Exception('Не удалось получить одну из моделей в updateCategoriesTypeSetsHandler');
        }
        $allCategoriesIds = $objectModelCategoriesTypes->getAllCategoriesByType($allTypeIds);
        // Используем $setIds, переданные в хук, для получения актуальных наборов
        // $allCategoriesSetIds = $objectModelProperties->getSetProperties($setIds); // Предполагаем, что getSetProperties вернет ID свойств в этих наборах, или нужна другая логика
        // Нужно убедиться, что $allCategoriesSetIds содержит ID именно НАБОРОВ для сравнения,
        // а не ID свойств. Возможно, логика $allCategoriesSetIds = $setIds; будет правильнее?
        // Или функция updatePropertiesForAnEntity ожидает ID свойств?
        // Оставляю $setIds как есть, но это место требует проверки соответствия данных!
        $allCategoriesSetIds = $setIds; // Используем ID наборов напрямую
        $allPages = $objectModelCategories->getCategoryPages($allCategoriesIds);
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
    return;
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
            $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
            $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
            $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
            if (!$objectModelCategoriesTypes || !$objectModelCategories || !$objectModelProperties) {
                throw new \Exception('Не удалось получить одну из моделей для синхронизации типов');
            }
            $allTypes = $objectModelCategoriesTypes->getAllTypes(false, true, $categoryData['type_id']);
            $arrAllTypes = array_unique(array_map(function ($item) {
                        return $item['type_id'];
                    }, $allTypes));
            $oldAllCategory = $objectModelCategories->getCategoryDescendantsShort($categoryId);
            foreach ($oldAllCategory as $cat) {
                if (!in_array($cat['type_id'], $arrAllTypes) || $cat['category_id'] == $categoryId) {
                    // Прямой запрос, чтобы не вызвать хук повторно
                    SafeMySQL::gi()->query('UPDATE ?n SET type_id = ?i WHERE category_id = ?i',
                            Constants::CATEGORIES_TABLE, $categoryData['type_id'], $cat['category_id']);
                    $allPages = $objectModelCategories->getCategoryPages($cat['category_id']);
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
 * Обработчик хука для индексации категории после сохранения
 */
function createSearchIndexCategory(int $categoryId, array $categoryData, string $method): void {
    if ($categoryId <= 0) {
        return;
    }
    try {
        $searchEngine = new ClassSearchEngine();
        $isActive = (isset($categoryData['status']) && $categoryData['status'] === 'active');
        if (!$isActive) {
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
        // TODO: Добавить получение и добавление текстовых свойств категории, если нужно
        $contentFull = ClassSearchEngine::prepareContent(implode(' ', $contentParts));
        if (empty($title) && empty($contentFull)) {
            $searchEngine->removeIndexEntry($entityType, $categoryId);
            return;
        }
        $indexData = [
            'entity_id' => $categoryId,
            'entity_type' => $entityType,
            'language_code' => $languageCode,
            'title' => $title,
            'content_full' => $contentFull,
        ];
        $searchEngine->updateIndexEntry($indexData);
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
 * Обработчик хука для индексации страницы после сохранения
 */
function createSearchIndexPage(int $pageId, array $pageData, string $method): void {
    if ($pageId <= 0) {
        return;
    }
    try {
        $searchEngine = new ClassSearchEngine();
        $isActive = (isset($pageData['status']) && $pageData['status'] === 'active');
        if (!$isActive) {
            $searchEngine->removeIndexEntry('page', $pageId);
            return;
        }
        $entityType = 'page';
        $languageCode = $pageData['language_code'] ?? ENV_DEF_LANG;
        $title = ClassSearchEngine::prepareTitle($pageData['title'] ?? '');
        $contentParts = [];
        $contentParts[] = $pageData['title'] ?? '';
        $contentParts[] = $pageData['short_description'] ?? '';
        $contentParts[] = $pageData['description'] ?? '';
        // TODO: Добавить получение и добавление текстовых свойств страницы, если нужно
        $contentFull = ClassSearchEngine::prepareContent(implode(' ', $contentParts));
        if (empty($title) && empty($contentFull)) {
            $searchEngine->removeIndexEntry($entityType, $pageId);
            return;
        }
        $indexData = [
            'entity_id' => $pageId,
            'entity_type' => $entityType,
            'language_code' => $languageCode,
            'title' => $title,
            'content_full' => $contentFull,
        ];
        $searchEngine->updateIndexEntry($indexData);
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
 * Синхронизирует наборы свойств для указанных категорий и страниц
 * Функция выполняет две основные задачи:
 * 1. Удаляет значения свойств, принадлежащих к наборам, которые больше не привязаны к сущностям
 * 2. Добавляет свойства из вновь привязанных наборов, используя их значения по умолчанию
 * @param int[] $allCategoriesIds Массив ID категорий, для которых нужно провести синхронизацию
 * @param array<int, array{page_id: int}> $allPages Массив страниц, содержащий 'page_id'
 * @param int[] $allRequiredSetIds "Эталонный" массив всех ID наборов, которые ДОЛЖНЫ быть у категорий
 * @param array{objectModelProperties: ModelProperties, objectModelCategoriesTypes: ModelCategoriesTypes} $models Ассоциативный массив с экземплярами необходимых моделей
 * @throws JsonException Если значения по умолчанию свойства хранятся в некорректном JSON-формате
 * @return void
 */
function updatePropertiesForAnEntity(array $allCategoriesIds, array $allPages, array $allRequiredSetIds, array $models): void {
    // 1. Получаем ID всех страниц и текущие свойства для категорий и страниц.
    $allPageIds = array_column($allPages, 'page_id');
    $allCurrentCategoryProps = $models['objectModelProperties']->getPropertiesValuesForEntity($allCategoriesIds, 'category');
    $allCurrentPageProps = $models['objectModelProperties']->getPropertiesValuesForEntity($allPageIds, 'page');
    $allCurrentProps = array_merge($allCurrentCategoryProps, $allCurrentPageProps);
    // 2. Определяем, какие значения свойств нужно удалить.
    // Это те значения, чей set_id отсутствует в "эталонном" списке.
    $valuesToDelete = array_filter(
            $allCurrentProps,
            static fn(array $prop): bool => !in_array($prop['set_id'], $allRequiredSetIds, true)
    );
    if (!empty($valuesToDelete)) {
        $valueIdsToDelete = array_column($valuesToDelete, 'value_id');
        $models['objectModelProperties']->deletePropertyValues($valueIdsToDelete);
    }
    // 3. Определяем, какие наборы свойств нужно добавить.
    // Для этого находим разницу между тем, что должно быть, и тем, что уже есть.
    $existingSetIds = array_unique(array_column($allCurrentCategoryProps, 'set_id'));
    $setsToAddIds = array_diff($allRequiredSetIds, $existingSetIds);
    if (empty($setsToAddIds)) {
        return; // Если добавлять нечего, выходим.
    }
    // 4. Получаем данные для новых наборов и сущностей.
    // ВНИМАНИЕ: Эти два запроса могут быть ресурсоемкими при большом количестве данных!
    $newSetsData = $models['objectModelProperties']->getPropertySetsData(false, 'set_id IN (' . implode(',', $setsToAddIds) . ')')['data'] ?? [];
    $entitiesData = $models['objectModelCategoriesTypes']->getCategorySetPageData($setsToAddIds);
    if (empty($newSetsData) || empty($entitiesData)) {
        return;
    }
    // 5. Локальная функция для добавления свойств сущности.
    $updatePropertiesFn = function (int $entityId, string $entityType) use ($entitiesData, $newSetsData, $models): void {
        foreach ($entitiesData as $entity) {
            if (!isset($newSetsData[$entity['set_id']]['properties'])) {
                continue;
            }
            foreach ($newSetsData[$entity['set_id']]['properties'] as $property) {
                if ($property['property_entity_type'] !== 'all' && $property['property_entity_type'] !== $entityType) {
                    continue;
                }
                $defaultValues = json_decode($property['default_values'], true, 512, JSON_THROW_ON_ERROR);
                $preparedValues = '[]';
                if (is_array($defaultValues) && !empty($defaultValues)) {
                    $preparedValues = json_encode(
                            array_map(static function (array $propDefault): array {
                                // Переименовываем 'default' в 'value' для соответствия формату
                                $propDefault['value'] = $propDefault['default'] ?? null;
                                unset($propDefault['default']);
                                return $propDefault;
                            }, $defaultValues),
                            JSON_THROW_ON_ERROR
                    );
                }
                $fields = [
                    'entity_id' => $entityId,
                    'property_id' => $property['property_id'],
                    'entity_type' => $entityType,
                    'fields' => $preparedValues,
                    'set_id' => $entity['set_id'],
                ];
                $models['objectModelProperties']->updatePropertiesValueEntities($fields);
            }
        }
    };
    // 6. Применяем логику добавления ко всем сущностям, избегая дублирования.
    $processedCategoryIds = [];
    foreach ($entitiesData as $item) {
        if (!in_array($item['category_id'], $processedCategoryIds, true)) {
            $updatePropertiesFn($item['category_id'], 'category');
            $processedCategoryIds[] = $item['category_id'];
        }
        if ($item['page_id']) {
            $updatePropertiesFn($item['page_id'], 'page');
        }
    }
}
