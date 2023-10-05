<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Функции работы с типами
 */
trait properties_trait {

    /**
     * Список свойств
     */
    public function properties() {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(200, '/show_login_form?return=admin');
        }
        $this->load_model('m_properties', [$this->logged_in]);
        /* get data */
        $user_data = $this->models['m_properties']->data;
        $this->get_user_data($user_data);
        /* view */
        $this->get_standart_view();
        $properties_data= $this->get_properties_data_table();
        $get_all_property_types = $this->models['m_properties']->get_all_property_types();
        $this->view->set('all_property_types', $get_all_property_types);
        $this->view->set('properties_table', $properties_data);
        $this->view->set('body_view', $this->view->read('v_properties'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - Свойства';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - Свойства';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->show_layout($this->parameters_layout);
    }
    
    /**
     * Список типов свойств
     */
    public function types_properties() {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(200, '/show_login_form?return=admin');
        }
        $this->load_model('m_properties', [$this->logged_in]);
        /* get data */
        $user_data = $this->models['m_properties']->data;
        $this->get_user_data($user_data);
        /* view */
        $this->get_standart_view();
        $types_properties_data= $this->get_types_properties_data_table();
        $this->view->set('types_properties_table', $types_properties_data);
        $this->view->set('body_view', $this->view->read('v_types_properties'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - Типы свойств';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - Типы свойств';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->show_layout($this->parameters_layout);
    }

    public function get_types_properties_data_table() {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $this->load_model('m_properties', [$this->logged_in]);
        if (!$this->lang['sys.name']) { // Подргужаем языковые переменные
            $user_data = $this->models['m_properties']->data;
            $this->get_user_data($user_data);
        }
        $post_data = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'type_id',
                    'title' => 'ID',
                    'sorted' => 'ASC',
                    'filterable' => false,
                    'width' => 5,
                    'align' => 'center'
                ], [
                    'field' => 'name',
                    'title' => $this->lang['sys.name'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'status',
                    'title' => $this->lang['sys.status'],
                    'sorted' => 'ASC',
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
            $features_array = $this->models['m_properties']->get_type_properties_data($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $features_array = $this->models['m_properties']->get_type_properties_data(false, false, false, 25);
        }

        foreach ($features_array['data'] as $item) {
            $data_table['rows'][] = [
                'type_id' => $item['type_id'],
                'name' => $item['name'],
                'status' => $this->lang['sys.' . $item['status']],
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'updated_at' => $item['updated_at'] ? date('d.m.Y', strtotime($item['updated_at'])) : '',
                'actions' => '<a href="/admin/type_properties_edit/id/' . $item['type_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip"'
                . 'data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . '<a href="/admin/type_properties_delete/id/' . $item['type_id'] . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>'
            ];
        }
        $data_table['total_rows'] = $features_array['total_count'];
        if ($post_data) {
            echo Plugins::ee_show_table('types_properties_table', $data_table, 'get_types_properties_data_table', $filters, $post_data["page"], $post_data["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('types_properties_table', $data_table, 'get_types_properties_data_table', $filters);
        }        
    }
    
    /**
     * Вернёт таблицу свойств
     */
    public function get_properties_data_table() {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $this->load_model('m_properties', [$this->logged_in]);
        if (!$this->lang['sys.name']) { // Подргужаем языковые переменные
            $user_data = $this->models['m_properties']->data;
            $this->get_user_data($user_data);
        }
        $post_data = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'property_id',
                    'title' => 'ID',
                    'sorted' => 'ASC',
                    'filterable' => false
                ], [
                    'field' => 'name',
                    'title' => $this->lang['sys.name'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'type_id',
                    'title' => $this->lang['sys.type'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'is_required',
                    'title' => 'Обязательное',
                    'sorted' => false,
                    'filterable' => false
                ], [
                    'field' => 'is_multiple',
                    'title' => 'Множественное',
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
                    'filterable' => false
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
        foreach ($this->models['m_properties']->get_all_property_types() as $item) {
           $filters['type_id']['options'][] = ['value' => $item['type_id'], 'label' => $item['name']];  
        }        
        if ($post_data && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_show_table_prepare_params($post_data, $data_table['columns']);
            $features_array = $this->models['m_properties']->get_properties_data($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $features_array = $this->models['m_properties']->get_properties_data(false, false, false, 25);
        }

        foreach ($features_array['data'] as $item) {
            $data_table['rows'][] = [
                'property_id' => $item['property_id'],
                'name' => $item['name'],
                'type_id' => $item['type_name'],
                'is_required' => $item['is_required'] ? $this->lang['sys.yes'] : $this->lang['sys.no'],
                'is_multiple' => $item['is_multiple'] ? $this->lang['sys.yes'] : $this->lang['sys.no'],
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'updated_at' => $item['updated_at'] ? date('d.m.Y', strtotime($item['updated_at'])) : '',
                'actions' => '<a href="/admin/property_edit/id/' . $item['property_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . '<a href="/admin/property_dell/id/' . $item['property_id'] . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" ' 
                . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>'
            ];
        }
        $data_table['total_rows'] = $features_array['total_count'];
        if ($post_data) {
            echo Plugins::ee_show_table('properties_table', $data_table, 'get_properties_data_table', $filters, $post_data["page"], $post_data["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('properties_table', $data_table, 'get_properties_data_table', $filters);
        }
    }
    /**
     * Добавить или редактировать тип свойств
     */
    public function type_properties_edit($params = []) {
         $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        /* model */
        $this->load_model('m_properties', [$this->logged_in]);
        /* get current user data */
        $user_data = $this->models['m_properties']->data;
        $this->get_user_data($user_data);
        $post_data = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $notifications = new Class_notifications();
            $id = filter_var($params[array_search('id', $params) + 1], FILTER_VALIDATE_INT);
            if (isset($post_data['name']) && $post_data['name']) {                                
                if (!is_array($post_data['fields']) || !count($post_data['fields'])) {
                   $notifications->add_notification_user($this->logged_in, ['text' => 'Заполните хотя бы одно поле типа!', 'status' => 'danger']);
                   goto exit_update;
                }
                $post_data['fields'] = json_encode($post_data['fields']);
                if (!$new_id = $this->models['m_properties']->update_property_type_data($post_data)) {                    
                    $notifications->add_notification_user($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                } else {
                    $id = $new_id;
                }
            }
            exit_update:
            $property_type_data = (int)$id ? $this->models['m_properties']->get_type_property_data($id) : [];
            $property_type_data['fields'] = json_decode($property_type_data['fields'], true);            
        } else { // Не передан ключевой параметр id
            SysClass::return_to_main(200, ENV_URL_SITE . '/admin/type_properties_edit/id');
        }
        foreach (Constants::ALL_STATUS as $key => $value) {
            $get_all_property_types[$key] = $this->lang['sys.' . $value];
        }
        /* view */
        $this->view->set('property_type_data', $property_type_data);
        $this->view->set('all_property_types', $get_all_property_types);
        $this->get_standart_view();
        $this->view->set('body_view', $this->view->read('v_edit_type_properties'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/edit_property_type.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Редактирование типа свойств';
        $this->show_layout($this->parameters_layout);       
    }
    
    /**
     * Добавить или редактировать свойство
     */
    public function property_edit($params) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        /* model */
        $this->load_model('m_properties', [$this->logged_in]);
        /* get current user data */
        $user_data = $this->models['m_properties']->data;
        $this->get_user_data($user_data);
        $post_data = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $id = filter_var($params[array_search('id', $params) + 1], FILTER_VALIDATE_INT);
            if (isset($post_data['name']) && $post_data['name']) {
                if (!$new_id = $this->models['m_properties']->update_property_data($post_data)) {
                    $notifications = new Class_notifications();
                    $notifications->add_notification_user($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                } else {
                    $id = $new_id;
                }
            }
            $get_property_data = (int)$id ? $this->models['m_properties']->get_property_data($id) : [];
            $get_property_data['fields'] = json_decode($get_property_data['fields'], true);
            $get_property_data['default_values'] = json_decode($get_property_data['default_values'], true);
        } else { // Не передан ключевой параметр id
            SysClass::return_to_main(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        $get_all_property_types = $this->models['m_properties']->get_all_property_types();
        /* view */
        $this->view->set('property_data', $get_property_data);
        $this->view->set('all_property_types', $get_all_property_types);
        $this->get_standart_view();
        $this->view->set('body_view', $this->view->read('v_edit_property'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/property_edit.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Редактирование свойства';
        $this->show_layout($this->parameters_layout);
    }
    
    /**
     * Удалит выбранный тип категории
     * @param array $params
     */
    public function type_properties_delete($params = []) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $notifications = new Class_notifications();
        if (in_array('id', $params)) {            
            $id = filter_var($params[array_search('id', $params) + 1], FILTER_VALIDATE_INT);
            $this->load_model('m_properties');
            $res = $this->models['m_properties']->type_properties_delete($id);
            if (count($res)) {
                $notifications->add_notification_user($this->logged_in, ['text' => 'Ошибка удаления типа id=' . $id . '<br/>' . $res['error'], 'status' => 'danger']);                    
            } else {
                $notifications->add_notification_user($this->logged_in, ['text' => 'Удалено!', 'status' => 'info']);
            }     
        } else {
            $notifications->add_notification_user($this->logged_in, ['text' => 'Нет обязательного параметра id', 'status' => 'danger']); 
        }
        SysClass::return_to_main(200, '/admin/types_properties');
    }    

    /**
     * Наборы свойств
     * @param type $params
     */
    public function properties_sets($params = []) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $this->load_model('m_properties', [$this->logged_in]);
        /* get data */
        $user_data = $this->models['m_properties']->data;
        $this->get_user_data($user_data);
        /* view */
        $this->get_standart_view();
        $this->view->set('body_view', $this->view->read('v_properties_sets'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - Наборы свойств';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - Наборы свойств';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->show_layout($this->parameters_layout);        
    }
    
}
