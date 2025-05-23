<?php

namespace app\admin;

use classes\system\SysClass;
use classes\system\Plugins;
use classes\system\Constants;
use classes\helpers\ClassNotifications;

/**
 * Функции работы с типами
 */
trait CategoriesTypesTrait {

    /**
     * Список категорий
     */
    public function types_categories() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin');
        }
        $this->loadModel('m_categories_types');
        /* view */
        $this->getStandardViews();
        $types_table = $this->getCategoriesTypesDataTable();
        $this->view->set('types_table', $types_table);
        $this->view->set('body_view', $this->view->read('v_categories_types'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . $this->lang['sys.type_categories'];
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - ' . $this->lang['sys.type_categories'];
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу категорий
     */
    public function getCategoriesTypesDataTable() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_categories_types');
        $postData = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'type_id',
                    'title' => 'ID',
                    'sorted' => 'ASC',
                    'filterable' => false,
                    'width' => 10,
                    'align' => 'center'
                ], [
                    'field' => 'name',
                    'title' => $this->lang['sys.name'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'parent_type_id',
                    'title' => $this->lang['sys.parent'],
                    'sorted' => 'ASC',
                    'filterable' => true
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
            'name' => [
                'type' => 'text',
                'id' => "name",
                'value' => '',
                'label' => $this->lang['sys.name']
            ],
            'name' => [
                'type' => 'text',
                'id' => "parent_type_id",
                'value' => '',
                'label' => $this->lang['sys.parent']
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
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $users_array = $this->models['m_categories_types']->getCategoriesTypesData($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $users_array = $this->models['m_categories_types']->getCategoriesTypesData(false, false, false, 25);
        }
        foreach ($users_array['data'] as $item) {
            $data_table['rows'][] = [
                'type_id' => $item['type_id'],
                'name' => $item['name'],
                'parent_type_id' => !empty($item['parent_type_id']) ? 
                    '(' . $item['parent_type_id'] . ')' . $this->models['m_categories_types']->getNameCategoriesType($item['parent_type_id']) : '',
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'updated_at' => $item['updated_at'] ? date('d.m.Y', strtotime($item['updated_at'])) : '',
                'actions' => '<a href="/admin/categories_type_edit/id/' . $item['type_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip"'
                . 'data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . '<a href="/admin/delete_categories_type/id/' . $item['type_id'] . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>'
            ];
        }
        $data_table['total_rows'] = $users_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('typesTable', $data_table, 'getCategoriesTypesDataTable', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('typesTable', $data_table, 'getCategoriesTypesDataTable', $filters);
        }
    }

    /**
     * Добавить или редактировать тип категории
     */
    public function categories_type_edit($params) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $default_data = [
            'type_id' => 0,
            'parent_type_id' => NULL,
            'name' => '',
            'description' => '',
            'created_at' => false,
            'updated_at' => false
        ];
        /* model */
        $this->loadModel('m_properties');
        $this->loadModel('m_categories_types', ['m_properties' => $this->models['m_properties']]);

        $postData = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $typeId = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $typeId = 0;
            }            
            if (isset($postData['name']) && $postData['name'] && !empty($postData['property_set'])) {
                if (!$typeId = $this->models['m_categories_types']->updateCategoriesTypeData($postData)) {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                }
                if (isset($postData['property_set']) && is_array($postData['property_set']) && count($postData['property_set']) &&
                        isset($postData['old_property_set']) && $postData['old_property_set'] != $postData['property_set']) {
                    // Изменения в наборе свойств
                    $parentsTypesIds = $this->models['m_categories_types']->getAllTypeParentsIds($typeId);        
                    $realSetsIds = array_unique(array_merge($this->models['m_categories_types']->getCategoriesTypeSetsData($parentsTypesIds), $postData['property_set']));
                    $this->models['m_categories_types']->updateCategoriesTypeSetsData($typeId, $realSetsIds);
                }
                if (!$postData['type_id'])
                    SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/categories_type_edit/id/' . $typeId);
            }
            $newEntity = empty($typeId) ? true : false;
            $this->processPostParams($postData, $newEntity, $typeId);
            $get_categories_types_data = (int) $typeId ? $this->models['m_categories_types']->getCategoriesTypeData($typeId) : $default_data;
            $get_categories_types_data = $get_categories_types_data ? $get_categories_types_data : $default_data;
        } else { // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/categories_type_edit/id/');
        }
        if (isset($get_categories_types_data['type_id'])) {
            $get_all_types = $this->models['m_categories_types']->getAllTypes($get_categories_types_data['type_id'], false);
        } else {
            $get_all_types = $this->models['m_categories_types']->getAllTypes(false, false);
        }
        $getCategoriesTypeSetsData = $this->models['m_categories_types']->getCategoriesTypeSetsData($typeId);
        /* view */
        $this->view->set('type_data', $get_categories_types_data);
        $this->view->set('all_types', $get_all_types);
        $this->view->set('propertySetsData', $this->models['m_properties']->getPropertySetsData('set_id ASC', false, 0, (10 * 10)));
        $this->view->set('categoriesTypeSetsData', $getCategoriesTypeSetsData);
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_edit_categories_type'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->addEditorToLayout();
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_categories_type.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Редактирование типов категорий';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Получает родительские типы категорий по AJAX запросу
     * Обрабатывает AJAX запрос для получения родительских типов категорий и связанных наборов свойств
     * @param array $params Параметры для метода (необязательно)
     * @return void
     */
    public function getParentCategoriesType(array $params = []): void {
        $is_ajax = SysClass::isAjaxRequestFromSameSite();
        if ($is_ajax) {
            $postData = SysClass::ee_cleanArray($_POST);
            $this->access = [Constants::ADMIN, Constants::MODERATOR];
            if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
                SysClass::handleRedirect();
                exit();
            }
            /* model */
            $this->loadModel('m_properties');
            $this->loadModel('m_categories_types', ['m_properties' => $this->models['m_properties']]);
            $parents_types_ids = $this->models['m_categories_types']->getAllTypeParentsIds($postData['type_id']) + [$postData['type_id']];
            $allSetsIds = [];
            foreach ($parents_types_ids as $t_id) {
                $allSetsIds = $allSetsIds + $this->models['m_categories_types']->getCategoriesTypeSetsData($t_id);
            }
            $allSetsIds = count($allSetsIds) ? $allSetsIds : false;
            echo json_encode(['all_sets_ids' => $allSetsIds]);
            die;
        } else {
            SysClass::handleRedirect();
            exit();
        }
    }

    /**
     * Удалит выбранный тип категории
     * @param array $params
     */
    public function delete_categories_type($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $id = 0;
            }
            $this->loadModel('m_categories_types');
            $res = $this->models['m_categories_types']->deleteCategoriesType($id);
            if (count($res)) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Ошибка удаления типа id=' . $id . '<br/>' . $res['error'], 'status' => 'danger']);
            } else {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Удалено!', 'status' => 'info']);
            }
        } else {
            ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Нет обязательного параметра id', 'status' => 'danger']);
        }
        SysClass::handleRedirect(200, '/admin/types_categories');
    }   
    
}
