<?php

/**
 * Евгений Ефимченко, efimchenko.com
 * Обеспечивает функциональность админ-панели
 * /app/admin/CategoriesTrait.php
 */

namespace app\admin;

use classes\system\SysClass;
use classes\system\Plugins;
use classes\helpers\ClassNotifications;
use classes\system\Constants;
use classes\system\EntityTranslationService;

/**
 * Функции работы с категориями
 */
trait CategoriesTrait {

    /**
     * Список категорий
     */
    public function categories() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin/categories');
        }
        $currentContentLanguageCode = ee_get_default_content_lang_code((string) ($_GET['language_code'] ?? \classes\system\Session::get('admin_categories_lang')));
        \classes\system\Session::set('admin_categories_lang', $currentContentLanguageCode);
        /* view */
        $this->getStandardViews();
        $categories_table = $this->getCategoriesDataTable([], $currentContentLanguageCode);
        $this->view->set('categories_table', $categories_table);
        $this->view->set('availableContentLanguageCodes', ee_get_content_lang_codes());
        $this->view->set('currentContentLanguageCode', $currentContentLanguageCode);
        $this->view->set('defaultContentLanguageCode', ee_get_default_content_lang_code());
        $this->view->set('languageSwitchBaseUrl', '/admin/categories');
        $this->view->set('body_view', $this->view->read('v_categories'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_categories.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - categories';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - categories';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Добавить или редактировать категорию
     * @param array $params
     * @return void
     */
    public function category_edit($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin/categories');
            exit();
        }
        $defaultData = [
            'category_id' => 0,
            'type_id' => 0,
            'title' => '',
            'description' => '',
            'short_description' => '',
            'parent_id' => 0,
            'status' => 'active', // Устанавливаем активный по умолчанию для формы
            'created_at' => '',
            'updated_at' => '',
            'parent_title' => '',
            'type_name' => '',
            'pages_count' => '',
            'category_path' => '',
            'category_path_text' => '',
        ];
        $categoryId = 0;
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $categoryId = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            }
        }
        if (!$categoryId)
            $categoryId = 0;
        $newEntity = empty($categoryId);
        $this->loadModel('m_categories_types');
        $this->loadModel('m_categories');
        $postData = SysClass::ee_cleanArray($_POST);
        $requestedLanguageCode = strtoupper(trim((string) ($_GET['language_code'] ?? '')));
        $translationSourceId = (int) ($_GET['translation_of'] ?? ($postData['translation_source_id'] ?? 0));
        $entityLanguageCode = $categoryId > 0
            ? strtoupper((string) (\classes\plugins\SafeMySQL::gi()->getOne(
                'SELECT language_code FROM ?n WHERE category_id = ?i LIMIT 1',
                Constants::CATEGORIES_TABLE,
                $categoryId
            ) ?: ''))
            : '';
        $defaultContentLanguageCode = ee_get_default_content_lang_code((string) (\classes\system\Session::get('admin_categories_lang') ?: ''));
        $languageCode = strtoupper(trim((string)($postData['language_code'] ?? ($requestedLanguageCode ?: ($entityLanguageCode ?: $defaultContentLanguageCode)))));
        if ($languageCode === '') {
            $languageCode = $entityLanguageCode !== '' ? $entityLanguageCode : $defaultContentLanguageCode;
        }
        $languageCode = ee_get_default_content_lang_code($languageCode);
        \classes\system\Session::set('admin_categories_lang', $languageCode);
        if (!empty($postData) && $languageCode !== '') {
            $postData['language_code'] = $languageCode;
        }
        if ($newEntity && $translationSourceId > 0 && empty($postData)) {
            $draftPayload = $this->prepareCategoryTranslationDraft($translationSourceId, $languageCode ?: ee_get_default_content_lang_code());
            if (!empty($draftPayload['redirect'])) {
                SysClass::handleRedirect(200, $draftPayload['redirect']);
                return;
            }
            if (!empty($draftPayload['warning']) && !empty($this->logged_in)) {
                ClassNotifications::addNotificationUser($this->logged_in, [
                    'text' => $draftPayload['warning'],
                    'status' => 'warning',
                ]);
            }
            if (!empty($draftPayload['categoryData']) && is_array($draftPayload['categoryData'])) {
                $defaultData = array_merge($defaultData, $draftPayload['categoryData']);
            }
        }
        if (!empty($postData) && isset($postData['title'])) {
            if (!$newEntity) {
                $postData['category_id'] = $categoryId;
            }
            if (isset($postData['description'])) {
                $postData['description'] = \classes\system\FileSystem::extractBase64Images($postData['description']);
            }
            $saveResult = $this->notifyOperationResult(
                $this->models['m_categories']->updateCategoryData($postData, $languageCode ?: ee_get_default_content_lang_code()),
                [
                    'success_message' => $this->lang['sys.saved'] ?? 'Категория сохранена',
                    'default_error_message' => 'Ошибка сохранения категории',
                ]
            );
            if ($saveResult->isSuccess()) {
                $categoryId = $saveResult->getId();
                if ($translationSourceId > 0 && $newEntity && $categoryId > 0 && $categoryId !== $translationSourceId) {
                    EntityTranslationService::linkEntityToSource('category', (int) $categoryId, $translationSourceId);
                    EntityTranslationService::duplicatePropertyValuesFromSource(
                        'category',
                        $translationSourceId,
                        (int) $categoryId,
                        EntityTranslationService::getEntityLanguageCode('category', $translationSourceId),
                        EntityTranslationService::getEntityLanguageCode('category', (int) $categoryId)
                    );
                }
                $newEntity = false;
                $this->saveFileProperty($postData);
                if (isset($postData['property_data']) && is_array($postData['property_data']) && !empty($postData['property_data_changed'])) {
                    $this->processPropertyData($postData['property_data'], $languageCode);
                }
                $this->processPostParams($postData, $newEntity, $categoryId);
            }
        }
        $getCategoryData = ($categoryId > 0 ? $this->models['m_categories']->getCategoryData($categoryId, $languageCode ?: ee_get_default_content_lang_code()) : null) ?: $defaultData;
        if (empty($this->models['m_categories'])) {
            $this->loadModel('m_categories');
        }
        if (empty($this->models['m_categories_types'])) {
            $this->loadModel('m_categories_types');
        }
        $categories_tree = $this->models['m_categories']->getCategoriesTree($categoryId, null, null, $languageCode ?: ee_get_default_content_lang_code());
        $fullCategoriesTree = $this->models['m_categories']->getCategoriesTree(null, null, null, $languageCode ?: ee_get_default_content_lang_code());
        $categoryPages = ($categoryId > 0) ? $this->models['m_categories']->getCategoryPages($categoryId, $languageCode ?: ee_get_default_content_lang_code()) : [];
        $currentTypeId = $getCategoryData['type_id'] ?? 0;
        $parentCategoryId = $getCategoryData['parent_id'] ?? 0;
        $getCategoriesTypeSets = ($currentTypeId > 0) ? $this->models['m_categories_types']->getCategoriesTypeSetsData($currentTypeId) : [];
        $getAllTypes = [];
        if ($parentCategoryId > 0) {
            $parentTypeId = $this->models['m_categories']->getCategoryTypeId($parentCategoryId, $languageCode ?: ee_get_default_content_lang_code());
            if (method_exists($this->models['m_categories_types'], 'getAllTypes')) {
                $getAllTypes = $this->models['m_categories_types']->getAllTypes(false, false, $parentTypeId, $languageCode ?: ee_get_default_content_lang_code());
            }
        } else {
            if (method_exists($this->models['m_categories_types'], 'getAllTypes')) {
                $getAllTypes = $this->models['m_categories_types']->getAllTypes(false, false, null, $languageCode ?: ee_get_default_content_lang_code());
            }
        }
        $getCategoriesTypeSetsData = [];
        if (!empty($getCategoriesTypeSets) && $categoryId > 0) {
            $getCategoriesTypeSetsData = $this->formattingEntityProperties($getCategoriesTypeSets, $categoryId, 'category', $getCategoryData['title'] ?? '', $languageCode ?: ee_get_default_content_lang_code());
        }
        $contentLanguageCodes = ee_get_content_lang_codes();
        $translationUi = (int) $categoryId > 0
            ? $this->buildCategoryTranslationUi((int) $categoryId, $languageCode ?: ee_get_default_content_lang_code(), $contentLanguageCodes)
            : $this->buildCategoryTranslationDraftUi($translationSourceId, $languageCode ?: ee_get_default_content_lang_code(), $contentLanguageCodes);
        $this->view->set('categoryData', $getCategoryData);
        $this->view->set('categories_tree', $categories_tree);
        $this->view->set('fullCategoriesTree', $fullCategoriesTree);
        $this->view->set('categoryPages', $categoryPages);
        $this->view->set('categoriesTypeSetsData', $getCategoriesTypeSetsData);
        $this->view->set('allType', $getAllTypes);
        $this->view->set('contentLanguageCodes', $contentLanguageCodes);
        $this->view->set('currentLanguageCode', $languageCode ?: ee_get_default_content_lang_code());
        $this->view->set('languageSwitchBaseUrl', '/admin/category_edit/id/' . (int) $categoryId);
        $this->view->set('translationUi', $translationUi);
        $this->view->set('translationSourceId', $translationSourceId);
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_edit_category'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->addEditorToLayout();
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/func_properties.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_categories.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = $getCategoryData['title'] ? ($this->lang['sys.category_edit'] ?? 'Редактирование категории' . ': ' . $getCategoryData['title']) : ($this->lang['sys.category_add'] ?? 'Добавление категории');
        $this->showLayout($this->parameters_layout);
    }

    /**
     * AJAX
     * Получение возможного набора типов категорий
     * для отображения в карточке категории при смене родителя     
     */
    public function getTypeCategory(array $params = []) {
        $is_ajax = SysClass::isAjaxRequestFromSameSite();
        if ($is_ajax && empty($params)) {
            $this->access = [Constants::ADMIN, Constants::MODERATOR];
            if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
                SysClass::handleRedirect();
                exit();
            }
            $this->loadModel('m_categories');
            $this->loadModel('m_categories_types');
            $postData = SysClass::ee_cleanArray($_POST);
            $entityLanguageCode = !empty($postData['category_id'])
                ? strtoupper((string) (\classes\plugins\SafeMySQL::gi()->getOne(
                    'SELECT language_code FROM ?n WHERE category_id = ?i LIMIT 1',
                    Constants::CATEGORIES_TABLE,
                    (int) $postData['category_id']
                ) ?: ''))
                : '';
            $languageCode = strtoupper(trim((string)($postData['language_code'] ?? ($entityLanguageCode ?: $this->getAdminUiLanguageCode()))));
            if (!empty($postData['parent_id'])) {
                $typeId = $this->models['m_categories']->getCategoryTypeId((int) $postData['parent_id'], $languageCode);
                $oldTypeId = $this->models['m_categories']->getCategoryTypeId((int) $postData['category_id'], $languageCode);
                $getAllTypes = $this->models['m_categories_types']->getAllTypes(false, false, $typeId, $languageCode);
            } else {
                $getAllTypes = $this->models['m_categories_types']->getAllTypes(false, false, null, $languageCode);
                $postData['parent_id'] = 0;
                $typeId = 0;
            }
            if (isset($getAllTypes[0])) {
                $selectedId = $getAllTypes[0]['type_id'];
            } else {
                $selectedId = null;
            }
            echo json_encode(['html' => Plugins::showTypeCategogyForSelect($getAllTypes, $selectedId),
                'parent_type_id' => $typeId,
                'parent_id' => $postData['parent_id'],
                'all_types' => $getAllTypes]);
        }
        die;
    }

    /**
     * AJAX
     * Получение набора свойств категории
     * для отображения в карточке категории при смене родителя     
     */
    public function getCategoriesType(array $params = []): void {
        $is_ajax = SysClass::isAjaxRequestFromSameSite();
        if ($is_ajax && empty($params)) {
            $this->access = [Constants::ADMIN, Constants::MODERATOR];
            if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
                SysClass::handleRedirect();
                exit();
            }
            $postData = SysClass::ee_cleanArray($_POST);
            $entityLanguageCode = !empty($postData['category_id'])
                ? strtoupper((string) (\classes\plugins\SafeMySQL::gi()->getOne(
                    'SELECT language_code FROM ?n WHERE category_id = ?i LIMIT 1',
                    Constants::CATEGORIES_TABLE,
                    (int) $postData['category_id']
                ) ?: ''))
                : '';
            $languageCode = strtoupper(trim((string)($postData['language_code'] ?? ($entityLanguageCode ?: $this->getAdminUiLanguageCode()))));
            $this->loadModel('m_categories_types');
            $getCategoriesTypeSets = $this->models['m_categories_types']->getCategoriesTypeSetsData($postData['type_id']);
            $categoryId = $postData['category_id'];
            $getCategoriesTypeSetsData = $this->formattingEntityProperties($getCategoriesTypeSets, $categoryId, 'category', $postData['title'], $languageCode);
            echo json_encode(['html' => Plugins::renderPropertiesSetsAccordion($getCategoriesTypeSetsData, $categoryId),
                'get_categories_type_sets' => $getCategoriesTypeSets, 'category_id' => $categoryId]);
        }
        die;
    }

    /**
     * Удаление категории
     * @param array $params
     * @return void
     */
    public function category_delete($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $categoryId = 0;
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $categoryId = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            }
        }
        if ($categoryId > 0) {
            $this->loadModel('m_categories');
            if (empty($this->models['m_categories'])) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Ошибка загрузки модели категорий', 'status' => 'danger']);
            } else {
                $this->notifyOperationResult(
                    $this->models['m_categories']->deleteCategory($categoryId),
                    [
                        'success_message' => $this->lang['sys.removed'] ?? 'Категория успешно удалена',
                        'default_error_message' => 'Произошла ошибка при удалении категории',
                    ]
                );
            }
        } else {
            ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Некорректный или отсутствующий ID категории', 'status' => 'warning']);
        }
        SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/categories');
    }

    /**
     * Вернёт таблицу категоий
     */
    public function getCategoriesDataTable(array $params = [], ?string $contentLanguageCode = null): string {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_categories');
        $postData = SysClass::ee_cleanArray($_POST);
        $languageCode = ee_get_default_content_lang_code((string) ($contentLanguageCode ?: \classes\system\Session::get('admin_categories_lang')));
        \classes\system\Session::set('admin_categories_lang', $languageCode);
        // SysClass::pre($postData);
        $data_table = [
            'columns' => [
                [
                    'field' => 'category_id',
                    'title' => 'ID',
                    'sorted' => true,
                    'filterable' => false,
                    'width' => 5,
                    'align' => 'center'
                ], [
                    'field' => 'title',
                    'title' => $this->lang['sys.name'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'parent_id',
                    'title' => $this->lang['sys.parent'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'type_id',
                    'title' => $this->lang['sys.type'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'children',
                    'title' => $this->lang['sys.pages'],
                    'sorted' => false,
                    'filterable' => false
                ], [
                    'field' => 'created_at',
                    'title' => $this->lang['sys.date_create'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'updated_at',
                    'title' => $this->lang['sys.date_update'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'language_code',
                    'title' => $this->lang['sys.language'],
                    'sorted' => 'ASC',
                    'filterable' => false,
                    'width' => 6,
                    'align' => 'center'
                ], [
                    'field' => 'translations',
                    'title' => $this->lang['sys.translations'] ?? 'Переводы',
                    'sorted' => false,
                    'filterable' => false,
                    'raw' => true
                ], [
                    'field' => 'actions',
                    'title' => $this->lang['sys.action'],
                    'sorted' => false,
                    'filterable' => false,
                    'width' => 10,
                    'align' => 'center'
                ],
            ]
        ];
        $filters = [
            'title' => [
                'type' => 'text',
                'id' => "title",
                'value' => '',
                'label' => $this->lang['sys.name']
            ],
            'parent_id' => [
                'type' => 'text',
                'id' => "parent_id",
                'value' => '',
                'label' => $this->lang['sys.parent']
            ],
            'type_id' => [
                'type' => 'select',
                'id' => "type_id",
                'value' => [],
                'label' => $this->lang['sys.type'],
                'options' => [['value' => 0, 'label' => 'Любой']],
                'multiple' => true
            ],
            'created_at' => [
                'type' => 'date',
                'id' => "created_at",
                'value' => '',
                'label' => $this->lang['sys.date_create']
            ],
            'updated_at' => [
                'type' => 'date',
                'id' => "updated_at",
                'value' => '',
                'label' => $this->lang['sys.date_update']
            ],
        ];
        $this->loadModel('m_categories_types');
        foreach ($this->models['m_categories_types']->getAllTypes(null, true, null, $languageCode) as $item) {
            $filters['type_id']['options'][] = ['value' => $item['type_id'], 'label' => $item['name']];
        }
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $category_array = $this->models['m_categories']->getCategoriesData($params['order'], $params['where'], $params['start'], $params['limit'], $languageCode);
        } else {
            $category_array = $this->models['m_categories']->getCategoriesData(false, false, false, 25, $languageCode);
        }
        $categoryIds = array_values(array_filter(array_map(static fn(array $item): int => (int) ($item['category_id'] ?? 0), $category_array['data'])));
        $translationsByCategoryId = EntityTranslationService::getTranslationsByEntityIds('category', $categoryIds);
        $availableLanguageCodes = ee_get_content_lang_codes();
        foreach ($category_array['data'] as $item) {
            $data_table['rows'][] = [
                'category_id' => $item['category_id'],
                'title' => $item['title'],
                'parent_id' => $item['parent_title'] ? $item['parent_title'] : 'Без категории',
                'type_id' => $item['type_name'],
                'children' => $item['pages_count'],
                'language_code' => strtoupper((string) ($item['language_code'] ?? $languageCode)),
                'translations' => $this->renderCategoryTranslationBadges(
                    (int) $item['category_id'],
                    $translationsByCategoryId[(int) $item['category_id']] ?? [],
                    $availableLanguageCodes
                ),
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'updated_at' => $item['updated_at'] ? date('d.m.Y', strtotime($item['updated_at'])) : '',
                'actions' => '<a href="/admin/category_edit/id/' . $item['category_id'] . '?language_code=' . rawurlencode((string) ($item['language_code'] ?? $languageCode)) . '"'
                . 'class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . '<a href="/admin/category_delete/id/' . $item['category_id'] . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>'
            ];
        }
        $data_table['total_rows'] = $category_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('category_table', $data_table, 'getCategoriesDataTable', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('category_table', $data_table, 'getCategoriesDataTable', $filters);
        }
    }

    private function prepareCategoryTranslationDraft(int $sourceCategoryId, string $targetLanguageCode): array {
        $targetLanguageCode = ee_get_default_content_lang_code($targetLanguageCode);
        $sourceCategoryRow = EntityTranslationService::getEntityRow('category', $sourceCategoryId);
        if (!is_array($sourceCategoryRow) || empty($sourceCategoryRow['category_id'])) {
            return [];
        }

        $sourceLanguageCode = ee_get_default_content_lang_code((string) ($sourceCategoryRow['language_code'] ?? ''));
        if ($targetLanguageCode === '') {
            $targetLanguageCode = $sourceLanguageCode;
        }

        $existingTargetId = EntityTranslationService::getTranslatedEntityId('category', $sourceCategoryId, $targetLanguageCode);
        if ($existingTargetId !== null) {
            return [
                'redirect' => '/admin/category_edit/id/' . $existingTargetId . '?language_code=' . rawurlencode($targetLanguageCode),
            ];
        }

        $parentId = (int) ($sourceCategoryRow['parent_id'] ?? 0);
        $warning = '';
        if ($parentId > 0 && $sourceLanguageCode !== $targetLanguageCode) {
            $translatedParentId = EntityTranslationService::getTranslatedEntityId('category', $parentId, $targetLanguageCode);
            if ($translatedParentId !== null) {
                $parentId = $translatedParentId;
            } else {
                return [
                    'redirect' => '/admin/category_edit/id/' . $parentId . '?language_code=' . rawurlencode($targetLanguageCode),
                ];
            }
        }

        return [
            'warning' => $warning,
            'categoryData' => [
                'category_id' => 0,
                'type_id' => (int) ($sourceCategoryRow['type_id'] ?? 0),
                'title' => (string) ($sourceCategoryRow['title'] ?? ''),
                'description' => (string) ($sourceCategoryRow['description'] ?? ''),
                'short_description' => (string) ($sourceCategoryRow['short_description'] ?? ''),
                'parent_id' => $parentId ?: 0,
                'status' => (($sourceCategoryRow['status'] ?? 'hidden') === 'active') ? 'hidden' : (string) ($sourceCategoryRow['status'] ?? 'hidden'),
                'created_at' => '',
                'updated_at' => '',
                'language_code' => $targetLanguageCode,
            ],
        ];
    }

    private function buildCategoryTranslationUi(int $categoryId, string $currentLanguageCode, array $availableLanguageCodes): array {
        $availableLanguageCodes = ee_collect_lang_codes($availableLanguageCodes);
        $state = EntityTranslationService::getTranslationState('category', $categoryId, $availableLanguageCodes);
        if ($state === []) {
            return [];
        }

        $ui = [
            'existing' => [],
            'missing' => [],
            'current_language_code' => strtoupper($currentLanguageCode),
            'group_key' => (string) ($state['group_key'] ?? ''),
            'is_draft' => false,
        ];

        foreach ($availableLanguageCodes as $availableLanguageCode) {
            $translation = $state['translations'][$availableLanguageCode] ?? null;
            if (is_array($translation)) {
                $ui['existing'][] = [
                    'language_code' => $availableLanguageCode,
                    'entity_id' => (int) ($translation['entity_id'] ?? 0),
                    'title' => (string) ($translation['title'] ?? ''),
                    'status' => (string) ($translation['status'] ?? ''),
                    'is_current' => (int) ($translation['entity_id'] ?? 0) === $categoryId,
                    'edit_url' => '/admin/category_edit/id/' . (int) ($translation['entity_id'] ?? 0) . '?language_code=' . rawurlencode($availableLanguageCode),
                ];
                continue;
            }

            $ui['missing'][] = [
                'language_code' => $availableLanguageCode,
                'create_url' => '/admin/category_edit/id?translation_of=' . $categoryId . '&language_code=' . rawurlencode($availableLanguageCode),
            ];
        }

        return $ui;
    }

    private function buildCategoryTranslationDraftUi(int $translationSourceId, string $currentLanguageCode, array $availableLanguageCodes): array {
        $availableLanguageCodes = ee_collect_lang_codes($availableLanguageCodes);
        if ($translationSourceId <= 0) {
            return [];
        }

        return [
            'existing' => [],
            'missing' => [],
            'current_language_code' => strtoupper($currentLanguageCode),
            'group_key' => (string) ((EntityTranslationService::getTranslations('category', $translationSourceId)['group_key'] ?? '')),
            'is_draft' => true,
            'source_entity_id' => $translationSourceId,
            'source_edit_url' => '/admin/category_edit/id/' . $translationSourceId,
            'available_languages' => $availableLanguageCodes,
        ];
    }

    private function renderCategoryTranslationBadges(int $categoryId, array $translationState, array $availableLanguageCodes): string {
        $availableLanguageCodes = ee_collect_lang_codes($availableLanguageCodes);
        $badges = [];
        $translations = (array) ($translationState['translations'] ?? []);

        foreach ($availableLanguageCodes as $availableLanguageCode) {
            if (isset($translations[$availableLanguageCode])) {
                $translation = $translations[$availableLanguageCode];
                $editUrl = '/admin/category_edit/id/' . (int) ($translation['entity_id'] ?? 0) . '?language_code=' . rawurlencode($availableLanguageCode);
                $badges[] = '<a href="' . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . '" class="badge text-bg-primary text-decoration-none me-1">' . htmlspecialchars((string) $availableLanguageCode, ENT_QUOTES, 'UTF-8') . '</a>';
                continue;
            }

            $createUrl = '/admin/category_edit/id?translation_of=' . $categoryId . '&language_code=' . rawurlencode($availableLanguageCode);
            $badges[] = '<a href="' . htmlspecialchars($createUrl, ENT_QUOTES, 'UTF-8') . '" class="badge text-bg-light text-decoration-none border me-1">+' . htmlspecialchars((string) $availableLanguageCode, ENT_QUOTES, 'UTF-8') . '</a>';
        }

        return implode('', $badges);
    }
}
