<?php

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
     */
    public function category_edit($params) {
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
            'status' => '',
            'created_at' => '',
            'updated_at' => '',
            'parent_title' => '',
            'type_name' => '',
            'pages_count' => '',
            'category_path' => '',
            'category_path_text' => '',
        ];
        /* model */
        $this->loadModel('m_categories_types');
        $this->loadModel('m_categories');        
        $postData = SysClass::ee_cleanArray($_POST);        
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {                
                $categoryId = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);                
            } else {
                $categoryId = 0;
            }            
            if (isset($postData['title']) && $postData['title']) {
                // Сохранение основных данных
                if (!$new_id = $this->models['m_categories']->updateCategoryData($postData)) {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                } else {
                    $categoryId = $new_id;
                }            
                $this->saveFileProperty($postData);         
                // Сохранение свойств для категории
                if (isset($postData['property_data']) && is_array($postData['property_data']) && !empty($postData['property_data_changed'])) {
                    $this->processPropertyData($postData['property_data']);
                }
            }
            $getCategoryData = ((int) $categoryId ? $this->models['m_categories']->getCategoryData($categoryId) : null) ?: $defaultData;            
        } else { // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/category_edit/id/');
        }        
        $categories_tree = $this->models['m_categories']->getCategoriesTree($categoryId);
        $fullCategoriesTree = $this->models['m_categories']->getCategoriesTree();
        $categoryPages = $this->models['m_categories']->getCategoryPages($categoryId);
        $getCategoriesTypeSets = $this->models['m_categories_types']->getCategoriesTypeSetsData($getCategoryData['type_id']);        
        $getAllTypes = [];
        if (isset($getCategoryData['parent_id']) && $getCategoryData['parent_id']) {
            // Есть родитель, можно выбрать только его тип или подчинённый
            $parentTypeId = $this->models['m_categories']->getCategoryTypeId($getCategoryData['parent_id']);
            $getAllTypes = $this->models['m_categories_types']->getAllTypes(false, false, $parentTypeId);
        } elseif (isset($getCategoryData['type_id']) && $getCategoryData['type_id']) {
            // Если нет родителя то можно выбрать любой тип категории
            $getAllTypes = $this->models['m_categories_types']->getAllTypes(false, false);            
        } else {
            $getAllTypes = $this->models['m_categories_types']->getAllTypes(false, false);
        }
        $getCategoriesTypeSetsData = [];
        if (count($getCategoriesTypeSets) && $getCategoryData) {
            $getCategoriesTypeSetsData = $this->formattingEntityProperties($getCategoriesTypeSets, $categoryId, 'category', $getCategoryData['title']);
        }
        
        /* view */
        $this->view->set('categoryData', $getCategoryData);        
        $this->view->set('categories_tree', $categories_tree);
        $this->view->set('fullCategoriesTree', $fullCategoriesTree);
        $this->view->set('categoryPages', $categoryPages);
        $this->view->set('categoriesTypeSetsData', $getCategoriesTypeSetsData);
        $this->view->set('allType', $getAllTypes);        
        $this->getStandardViews();        
        $this->view->set('body_view', $this->view->read('v_edit_category'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->add_editor_to_layout();
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/func_properties.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_categories.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = $this->lang['sys.categories_edit'];
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
                if ($typeId !== $oldTypeId && $postData['count_pages'] > 0) { // Нельзя сменить тип категории если есть страницы
                    echo json_encode(['parent_type_id' => false, 'html' => 'Нельзя сменить тип категории если есть страницы!']);
                    die;
                }
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
     */
    public function category_delete($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* model */
        $this->loadModel('m_categories');
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $categoryId = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $categoryId = 0;
            }
            $res = $this->models['m_categories']->deleteСategory($categoryId);
            if (isset($res['error'])) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => $res['error'], 'status' => 'danger']);
            } else {
                $this->loadModel('m_properties');
                $this->models['m_properties']->deleteAllpropertiesValuesEntity($categoryId, 'category');
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Удалено', 'status' => 'success']);
            }
        }
        SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/categories');
    }

    /**
     * Вернёт таблицу категоий
     */
    public function getCategoriesDataTable() {
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
