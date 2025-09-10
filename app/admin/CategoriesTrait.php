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
        /* view */
        $this->getStandardViews();
        $categories_table = $this->getCategoriesDataTable();
        $this->view->set('categories_table', $categories_table);
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
        if (!empty($postData) && isset($postData['title'])) {
            if (!$newEntity) {
                $postData['category_id'] = $categoryId;
            }
            if (isset($postData['description'])) {
                $postData['description'] = \classes\system\FileSystem::extractBase64Images($postData['description']);
            }
            $result = $this->models['m_categories']->updateCategoryData($postData, $postData['language_code'] ?? ENV_DEF_LANG);
            if (is_object($result) && $result instanceof ErrorLogger) {
                $errorMessage = $result->result['error_message'] ?? 'Ошибка сохранения категории';
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => $errorMessage, 'status' => 'danger']);
            } else if (is_int($result) && $result > 0) {
                $categoryId = $result;
                $newEntity = false;
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.saved'] ?? 'Категория сохранена', 'status' => 'success']);

                $this->saveFileProperty($postData);
                if (isset($postData['property_data']) && is_array($postData['property_data']) && !empty($postData['property_data_changed'])) {
                    $this->processPropertyData($postData['property_data']);
                }
                $this->processPostParams($postData, $newEntity, $categoryId);
            } else {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Произошла неизвестная ошибка при сохранении', 'status' => 'danger']);
                new ErrorLogger('Неожиданный результат от ModelCategories::updateCategoryData', __FUNCTION__, 'category_edit', ['result' => $result]);
            }
        }
        $getCategoryData = ($categoryId > 0 ? $this->models['m_categories']->getCategoryData($categoryId) : null) ?: $defaultData;
        if (empty($this->models['m_categories'])) {
            $this->loadModel('m_categories');
        }
        if (empty($this->models['m_categories_types'])) {
            $this->loadModel('m_categories_types');
        }
        $categories_tree = $this->models['m_categories']->getCategoriesTree($categoryId);
        $fullCategoriesTree = $this->models['m_categories']->getCategoriesTree();
        $categoryPages = ($categoryId > 0) ? $this->models['m_categories']->getCategoryPages($categoryId) : [];
        $currentTypeId = $getCategoryData['type_id'] ?? 0;
        $parentCategoryId = $getCategoryData['parent_id'] ?? 0;
        $getCategoriesTypeSets = ($currentTypeId > 0) ? $this->models['m_categories_types']->getCategoriesTypeSetsData($currentTypeId) : [];
        $getAllTypes = [];
        if ($parentCategoryId > 0) {
            $parentTypeId = $this->models['m_categories']->getCategoryTypeId($parentCategoryId);
            if (method_exists($this->models['m_categories_types'], 'getAllTypes')) {
                $getAllTypes = $this->models['m_categories_types']->getAllTypes(false, false, $parentTypeId);
            }
        } else {
            if (method_exists($this->models['m_categories_types'], 'getAllTypes')) {
                $getAllTypes = $this->models['m_categories_types']->getAllTypes(false, false);
            }
        }
        $getCategoriesTypeSetsData = [];
        if (!empty($getCategoriesTypeSets) && $categoryId > 0) {
            $getCategoriesTypeSetsData = $this->formattingEntityProperties($getCategoriesTypeSets, $categoryId, 'category', $getCategoryData['title'] ?? '');
        }
        $this->view->set('categoryData', $getCategoryData);
        $this->view->set('categories_tree', $categories_tree);
        $this->view->set('fullCategoriesTree', $fullCategoriesTree);
        $this->view->set('categoryPages', $categoryPages);
        $this->view->set('categoriesTypeSetsData', $getCategoriesTypeSetsData);
        $this->view->set('allType', $getAllTypes);
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
            if (!empty($postData['parent_id'])) {
                $typeId = $this->models['m_categories']->getCategoryTypeId($postData['parent_id']);
                $oldTypeId = $this->models['m_categories']->getCategoryTypeId($postData['category_id']);
                $getAllTypes = $this->models['m_categories_types']->getAllTypes(false, false, $typeId);
            } else {
                $getAllTypes = $this->models['m_categories_types']->getAllTypes(false, false);
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
            $this->loadModel('m_categories_types');
            $getCategoriesTypeSets = $this->models['m_categories_types']->getCategoriesTypeSetsData($postData['type_id']);
            $categoryId = $postData['category_id'];
            $getCategoriesTypeSetsData = $this->formattingEntityProperties($getCategoriesTypeSets, $categoryId, 'category', $postData['title']);
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
                $res = $this->models['m_categories']->deleteCategory($categoryId);
                if (is_object($res) && $res instanceof ErrorLogger) {
                    $errorMessage = $res->result['error_message'] ?? 'Произошла ошибка при удалении категории';
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $errorMessage, 'status' => 'danger']);
                } else {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.removed'] ?? 'Категория успешно удалена', 'status' => 'success']);
                }
            }
        } else {
            ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Некорректный или отсутствующий ID категории', 'status' => 'warning']);
        }
        SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/categories');
    }

    /**
     * Вернёт таблицу категоий
     */
    public function getCategoriesDataTable(array $params = []): string {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_categories');
        $postData = SysClass::ee_cleanArray($_POST);
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
        foreach ($this->models['m_categories_types']->getAllTypes() as $item) {
            $filters['type_id']['options'][] = ['value' => $item['type_id'], 'label' => $item['name']];
        }
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $category_array = $this->models['m_categories']->getCategoriesData($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $category_array = $this->models['m_categories']->getCategoriesData(false, false, false, 25);
        }
        foreach ($category_array['data'] as $item) {
            $data_table['rows'][] = [
                'category_id' => $item['category_id'],
                'title' => $item['title'],
                'parent_id' => $item['parent_title'] ? $item['parent_title'] : 'Без категории',
                'type_id' => $item['type_name'],
                'children' => $item['pages_count'],
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'updated_at' => $item['updated_at'] ? date('d.m.Y', strtotime($item['updated_at'])) : '',
                'actions' => '<a href="/admin/category_edit/id/' . $item['category_id'] . '"'
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
}
