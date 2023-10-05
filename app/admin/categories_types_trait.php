<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Функции работы с типами
 */
trait categories_types_trait {

    /**
     * Список категорий
     */
    public function types_categories() {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(200, '/show_login_form?return=admin');
        }
        $this->load_model('m_categories_types', [$this->logged_in]);
        /* get data */
        $user_data = $this->models['m_categories_types']->data;
        $this->get_user_data($user_data);
        /* view */
        $this->get_standart_view();
        $types_table = $this->get_categories_types_data_table();
        $this->view->set('types_table', $types_table);
        $this->view->set('body_view', $this->view->read('v_types'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - Типы категорий';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - Типы категорий';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу категоий
     */
    public function get_categories_types_data_table() {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $this->load_model('m_categories_types', [$this->logged_in]);
        if (!$this->lang['sys.name']) { // Подргужаем языковые переменные
            $user_data = $this->models['m_categories_types']->data;
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
            $users_array = $this->models['m_categories_types']->get_categories_types_data($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $users_array = $this->models['m_categories_types']->get_categories_types_data(false, false, false, 25);
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
     * Добавить или редактировать категорию
     */
    public function categories_type_edit($params) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        /* model */
        $this->load_model('m_categories_types', [$this->logged_in]);
        /* get current user data */
        $user_data = $this->models['m_categories_types']->data;
        $this->get_user_data($user_data);
        $post_data = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $id = filter_var($params[array_search('id', $params) + 1], FILTER_VALIDATE_INT);
            if (isset($post_data['name']) && $post_data['name']) {
                if (!$id = $this->models['m_categories_types']->update_categories_type_data($post_data)) {
                    $notifications = new Class_notifications();
                    $notifications->add_notification_user($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                } else {
                    if (!$post_data['type_id']) SysClass::return_to_main(200, ENV_URL_SITE . '/admin/categories_type_edit/id/' . $id);
                }
            }
            $get_categories_types_data = (int)$id ? $this->models['m_categories_types']->get_categories_type_data($id) : [];
        } else { // Не передан ключевой параметр id
            SysClass::return_to_main(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        $get_all_types = $this->view->set('all_type', $get_all_types);
        /* view */
        $this->view->set('type_data', $get_categories_types_data);
        $this->view->set('all_type', $get_all_types);
        $this->get_standart_view();
        $this->view->set('body_view', $this->view->read('v_edit_type'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/edit_categories_type.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Редактирование типов категорий';
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Удалит выбранный тип категории
     * @param array $params
     */
    public function delete_categories_type($params = []) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $notifications = new Class_notifications();
        if (in_array('id', $params)) {            
            $id = filter_var($params[array_search('id', $params) + 1], FILTER_VALIDATE_INT);
            $this->load_model('m_categories_types');
            $res = $this->models['m_categories_types']->delete_categories_type($id);
            if (count($res)) {
                $notifications->add_notification_user($this->logged_in, ['text' => 'Ошибка удаления типа id=' . $id . '<br/>' . $res['error'], 'status' => 'danger']);                    
            } else {
                $notifications->add_notification_user($this->logged_in, ['text' => 'Удалено!', 'status' => 'info']);
            }     
        } else {
            $notifications->add_notification_user($this->logged_in, ['text' => 'Нет обязательного параметра id', 'status' => 'danger']); 
        }
        SysClass::return_to_main(200, '/admin/type_categories');
    }
}
