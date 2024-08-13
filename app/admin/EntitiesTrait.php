<?php

namespace app\admin;

use classes\system\SysClass;
use classes\system\Constants;
use classes\helpers\ClassNotifications;
use classes\system\Plugins;

/**
 * Функции работы с сущностями
 */
trait EntitiesTrait {

    /**
     * Список сущностей
     */
    public function entities() {
        $this->access = [1, 2];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin');
        }
        /* view */
        $this->getStandardViews();
        $entities_table = $this->get_entities_data_table();
        $this->view->set('entities_table', $entities_table);
        $this->view->set('body_view', $this->view->read('v_entities'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';        
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . $this->lang['sys.entities'];
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - ' . $this->lang['sys.entities'];
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Добавить или редактировать сущность
     */
    public function entity_edit($params = []) {
        $this->access = [1, 2];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $default_data = [
            'entity_id' => 0,
            'parent_entity_id' => NULL,
            'category_id' => 0,
            'status' => 'active',
            'title' => '',
            'short_description' => '',
            'description' => '',
            'created_at' => false,
            'updated_at' => false,
            'category_title' => '',
            'type_name' => '',            
        ];
        /* model */
        $this->loadModel('m_entities');
        $this->loadModel('m_categories_types');
        $this->loadModel('m_categories');
        $this->loadModel('m_properties');

        $post_data = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $key_id = array_search('id', $params);
            if ($key_id !== false && isset($params[$key_id + 1])) {
                $id = filter_var($params[$key_id + 1], FILTER_VALIDATE_INT);
            } else {
                $id = 0; 
            }
            if (isset($post_data['title']) && $post_data['title']) {             
                if (!$new_id = $this->models['m_entities']->update_entity_data($post_data)) {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                } else {
                    $id = $new_id;
                }
            }
            $get_entity_data = (int)$id ? $this->models['m_entities']->get_entity_data($id) : $default_data;
            $get_entity_data = $get_entity_data ? $get_entity_data : $default_data;
        } else { // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        $get_all_types = $this->models['m_categories_types']->getAllTypes();
        $result = array_reduce($get_all_types, function($carry, $item) {
          $carry[$item['type_id']] = $item;
          return $carry;
        }, []);
        $get_all_types = $result;
        unset($result);
        $get_all_categories = $this->models['m_categories']->getCategoriesTree(null, null, true);
        $get_all_entities = $this->models['m_entities']->get_all_entities($id);
        $get_all_properties = $this->models['m_properties']->getAllProperties('active', ENV_DEF_LANG, false);
        foreach (Constants::ALL_STATUS as $key => $value) {
            $all_status[$key] = $this->lang['sys.' . $value];
        }        
        /* view */
        $this->view->set('entity_data', $get_entity_data);
        $this->view->set('all_type', $get_all_types);
        $this->view->set('all_categories', $get_all_categories);
        $this->view->set('all_entities', $get_all_entities);
        $this->view->set('all_properties', $get_all_properties);
        $this->view->set('all_status', $all_status);
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_edit_entity'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->add_editor_to_layout();
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_entities.js" type="text/javascript" /></script>';
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = 'Редактирование Сущности';
        $this->showLayout($this->parameters_layout);
    }
    
    /**
     * Удаление сущности
     */
    public function entity_dell($params = []) {        
        $this->access = [1, 2];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* model */
        $this->loadModel('m_entities');        

        if (in_array('id', $params)) {
            $key_id = array_search('id', $params);
            if ($key_id !== false && isset($params[$key_id + 1])) {
                $id = filter_var($params[$key_id + 1], FILTER_VALIDATE_INT);
            } else {
                $id = 0; 
            }
            $res = $this->models['m_entities']->delete_entity($id);
            if (isset($res['error'])) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => $res['error'], 'status' => 'danger']);                
            }
        }
        SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/entities');        
    }
    
    /**
     * Вернёт таблицу категоий
     */
    public function get_entities_data_table() {
        $this->access = [1, 2];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_entities');
        $this->loadModel('m_categories_types', []);
        $all_types = $this->models['m_categories_types']->getAllTypes();
        $post_data = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'entity_id',
                    'title' => 'ID',
                    'sorted' => false,
                    'filterable' => false,
                    'width' => 5,
                    'align' => 'center'
                ], [
                    'field' => 'title',
                    'title' => $this->lang['sys.name'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'category_id',
                    'title' => $this->lang['sys.category'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'type_id',
                    'title' => $this->lang['sys.type'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'status',
                    'title' => $this->lang['sys.status'],
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
        $filter_types[] = ['value' => 0, 'label' => 'Любой'];
        foreach ($all_types as $item) {
            $filter_types[] = ['value' => $item['type_id'], 'label' => $item['name']];
        }
        foreach (Constants::ALL_STATUS as $key => $value) {
            $statuses[] = ['value' => $key, 'label' => $this->lang['sys.' . $value]];
            $statuses_text[$key] = $this->lang['sys.' . $value];
        }        
        $filters = [
            'title' => [
                'type' => 'text',
                'id' => "title",
                'value' => '',
                'label' => $this->lang['sys.name']
            ],
            'category_id' => [
                'type' => 'text',
                'id' => "category_id",
                'value' => '',
                'label' => $this->lang['sys.category']
            ],
            'type_id' => [
                'type' => 'select',
                'id' => "type_id",
                'value' => [],
                'label' => $this->lang['sys.type'],
                'options' => $filter_types,
                'multiple' => true
            ],
            'status' => [
                'type' => 'select',
                'id' => "status",
                'value' => [],
                'label' => $this->lang['sys.status'],
                'options' => $statuses,
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
        if ($post_data && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_show_table_prepare_params($post_data, $data_table['columns']);
            $entities_array = $this->models['m_entities']->get_entities_data($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $entities_array = $this->models['m_entities']->get_entities_data(false, false, false, 25);
        }
        foreach ($entities_array['data'] as $item) {
            $data_table['rows'][] = [
                'entity_id' => $item['entity_id'],
                'title' => $item['title'],
                'category_id' => $item['category_title'] ? $item['category_title'] : 'Без категории',
                'type_id' => $item['type_name'],
                'status' => $statuses_text[$item['status']],
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'updated_at' => $item['updated_at'] ? date('d.m.Y', strtotime($item['updated_at'])) : '',
                'actions' => '<a href="/admin/entity_edit/id/' . $item['entity_id'] . '"'
                . 'class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . '<a href="/admin/entity_dell/id/' . $item['entity_id'] . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>'
            ];
        }
        $data_table['total_rows'] = $entities_array['total_count'];        
        if ($post_data) {            
            echo Plugins::ee_show_table('entity_table', $data_table, 'get_entities_data_table', $filters, $post_data["page"], $post_data["rows_per_page"], $selected_sorting);
            die;
        } else {            
            return Plugins::ee_show_table('entity_table', $data_table, 'get_entities_data_table', $filters);
        }
    }
}
