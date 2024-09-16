<?php

namespace app\admin;

use classes\system\SysClass;
use classes\system\Constants;
use classes\helpers\ClassNotifications;
use classes\system\Plugins;

/**
 * Функции работы с сущностями
 */
trait PagesTrait {

    /**
     * Список сущностей
     */
    public function pages() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin');
        }
        /* view */
        $this->getStandardViews();
        $pagesTable = $this->getPagesDataTable();
        $this->view->set('pagesTable', $pagesTable);
        $this->view->set('body_view', $this->view->read('v_pages'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';        
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . $this->lang['sys.pages'];
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - ' . $this->lang['sys.pages'];
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Добавить или редактировать сущность
     */
    public function pageEdit($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $default_data = [
            'page_id' => 0,
            'parent_page_id' => NULL,
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
        $this->loadModel('m_pages');
        $this->loadModel('m_categories_types');
        $this->loadModel('m_categories');
        $this->loadModel('m_properties');

        $postData = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $id = 0; 
            }
            if (isset($postData['title']) && $postData['title']) {             
                if (!$new_id = $this->models['m_pages']->updatePageData($postData)) {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                } else {
                    $id = $new_id;
                }
            }
            $getPageData = (int)$id ? $this->models['m_pages']->getPageData($id) : $default_data;
            $getPageData = $getPageData ? $getPageData : $default_data;
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
        $getAllPages = $this->models['m_pages']->getAllPages($id);
        $get_all_properties = $this->models['m_properties']->getAllProperties('active', ENV_DEF_LANG, false);
        foreach (Constants::ALL_STATUS as $key => $value) {
            $all_status[$key] = $this->lang['sys.' . $value];
        }        
        /* view */
        $this->view->set('pageData', $getPageData);
        $this->view->set('all_type', $get_all_types);
        $this->view->set('all_categories', $get_all_categories);
        $this->view->set('allPages', $getAllPages);
        $this->view->set('all_properties', $get_all_properties);
        $this->view->set('all_status', $all_status);
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_edit_page'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->add_editor_to_layout();
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_pages.js" type="text/javascript" /></script>';
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = 'Редактирование Сущности';
        $this->showLayout($this->parameters_layout);
    }
    
    /**
     * Удаление сущности
     */
    public function pageDell($params = []) {        
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* model */
        $this->loadModel('m_pages');        

        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $id = 0; 
            }
            $res = $this->models['m_pages']->deletePage($id);
            if (isset($res['error'])) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => $res['error'], 'status' => 'danger']);                
            }
        }
        SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/pages');        
    }
    
    /**
     * Вернёт таблицу страниц
     */
    public function getPagesDataTable() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_pages');
        $this->loadModel('m_categories_types', []);
        $all_types = $this->models['m_categories_types']->getAllTypes();
        $postData = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'page_id',
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
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $arrPages = $this->models['m_pages']->getPagesData($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $arrPages = $this->models['m_pages']->getPagesData(false, false, false, 25);
        }
        foreach ($arrPages['data'] as $item) {
            $data_table['rows'][] = [
                'page_id' => $item['page_id'],
                'title' => $item['title'],
                'category_id' => $item['category_title'] ? $item['category_title'] : 'Без категории',
                'type_id' => $item['type_name'],
                'status' => $statuses_text[$item['status']],
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'updated_at' => $item['updated_at'] ? date('d.m.Y', strtotime($item['updated_at'])) : '',
                'actions' => '<a href="/admin/pageEdit/id/' . $item['page_id'] . '"'
                . 'class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . '<a href="/admin/pageDell/id/' . $item['page_id'] . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>'
            ];
        }
        $data_table['total_rows'] = $arrPages['total_count'];        
        if ($postData) {            
            echo Plugins::ee_show_table('pages_table', $data_table, 'getPagesDataTable', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {            
            return Plugins::ee_show_table('pages_table', $data_table, 'getPagesDataTable', $filters);
        }
    }
}
