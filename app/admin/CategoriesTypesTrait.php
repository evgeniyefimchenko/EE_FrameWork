<?php

namespace app\admin;

use classes\system\SysClass;
use classes\system\Plugins;
use classes\helpers\ClassNotifications;

/**
 * Функции работы с типами
 */
trait CategoriesTypesTrait {

    /**
     * Список категорий
     */
    public function types_categories() {
        $this->access = [1, 2];
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
        $this->access = [1, 2];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_categories_types');
        $post_data = SysClass::ee_cleanArray($_POST);
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
        if ($post_data && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_show_table_prepare_params($post_data, $data_table['columns']);
            $users_array = $this->models['m_categories_types']->getCategoriesTypesData($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $users_array = $this->models['m_categories_types']->getCategoriesTypesData(false, false, false, 25);
        }
        foreach ($users_array['data'] as $item) {
            $data_table['rows'][] = [
                'type_id' => $item['type_id'],
                'name' => $item['name'],
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'updated_at' => $item['updated_at'] ? date('d.m.Y', strtotime($item['updated_at'])) : '',
                'actions' => '<a href="/admin/categories_type_edit/id/' . $item['type_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip"'
                . 'data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . '<a href="/admin/delete_categories_type/id/' . $item['type_id'] . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>'
            ];
        }
        $data_table['total_rows'] = $users_array['total_count'];
        if ($post_data) {
            echo Plugins::ee_show_table('types_table', $data_table, 'get_categories_types_data_table', $filters, $post_data["page"], $post_data["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('types_table', $data_table, 'get_categories_types_data_table', $filters);
        }
    }

    /**
     * Добавить или редактировать тип категории
     */
    public function categories_type_edit($params) {
        $this->access = [1, 2];
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

        $post_data = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $key_id = array_search('id', $params);
            if ($key_id !== false && isset($params[$key_id + 1])) {
                $id = filter_var($params[$key_id + 1], FILTER_VALIDATE_INT);
            } else {
                $id = 0;
            }
            if (isset($post_data['name']) && $post_data['name']) {
                if (!$id = $this->models['m_categories_types']->updateCategoriesTypeData($post_data)) {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                }
                if (isset($post_data['property_set']) && is_array($post_data['property_set']) && count($post_data['property_set']) &&
                        isset($post_data['old_property_set']) && $post_data['old_property_set'] != $post_data['property_set']) {
                    // Изменения в наборе свойств
                    $parentsTypesIds = $this->models['m_categories_types']->getAllTypeParentsIds($id);        
                    $realSetsIds = array_unique(array_merge($this->models['m_categories_types']->getCategoriesTypeSetsData($parentsTypesIds), $post_data['property_set']));
                    $this->models['m_categories_types']->updateCategoriesTypeSetsData($id, $realSetsIds);
                }
                if (!$post_data['type_id'])
                    SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/categories_type_edit/id/' . $id);
            }
            $get_categories_types_data = (int) $id ? $this->models['m_categories_types']->getCategoriesTypeData($id) : $default_data;
            $get_categories_types_data = $get_categories_types_data ? $get_categories_types_data : $default_data;
        } else { // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/categories_type_edit/id/');
        }
        if (isset($get_categories_types_data['type_id'])) {
            $get_all_types = $this->models['m_categories_types']->getAllTypes($get_categories_types_data['type_id'], false);
        } else {
            $get_all_types = $this->models['m_categories_types']->getAllTypes(false, false);
        }
        $get_categories_type_sets_data = $this->models['m_categories_types']->getCategoriesTypeSetsData($id);
        /* view */
        $this->view->set('type_data', $get_categories_types_data);
        $this->view->set('all_types', $get_all_types);
        $this->view->set('property_sets_data', $this->models['m_properties']->get_property_sets_data('set_id ASC'));
        $this->view->set('categories_type_sets_data', $get_categories_type_sets_data);
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_edit_categories_type'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->add_editor_to_layout();
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
            $post_data = SysClass::ee_cleanArray($_POST);
            $this->access = [1, 2];
            if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
                SysClass::handleRedirect();
                exit();
            }
            /* model */
            $this->loadModel('m_properties');
            $this->loadModel('m_categories_types', ['m_properties' => $this->models['m_properties']]);
            $parents_types_ids = $this->models['m_categories_types']->getAllTypeParentsIds($post_data['type_id']) + [$post_data['type_id']];
            $all_sets_ids = [];
            foreach ($parents_types_ids as $t_id) {
                $all_sets_ids = $all_sets_ids + $this->models['m_categories_types']->getCategoriesTypeSetsData($t_id);
            }
            $all_sets_ids = count($all_sets_ids) ? $all_sets_ids : false;
            echo json_encode(['all_sets_ids' => $all_sets_ids]);
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
        $this->access = [1, 2];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        if (in_array('id', $params)) {
            $key_id = array_search('id', $params);
            if ($key_id !== false && isset($params[$key_id + 1])) {
                $id = filter_var($params[$key_id + 1], FILTER_VALIDATE_INT);
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
