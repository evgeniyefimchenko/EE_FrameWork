<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Функции работы с категориями
 */
trait categories_trait {

    /**
     * Список категорий
     */
    public function categories() {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(200, '/show_login_form?return=admin/categories');
        }
        $this->load_model('m_categories', array($this->logged_in));
        /* get data */
        $user_data = $this->models['m_categories']->data;
        $this->get_user_data($user_data);
        /* view */
        $this->get_standart_view();
        $categories_table = $this->get_categories_data_table();
        $this->view->set('categories_table', $categories_table);
        $this->view->set('body_view', $this->view->read('v_categories'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/edit_categories.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - categories';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - categories';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Добавить или редактировать категорию
     */
    public function category_edit($arg) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(200, '/show_login_form?return=admin/categories');
            exit();
        }
        /* model */
        $this->load_model('m_categories', array($this->logged_in));
        $this->load_model('m_types', []);
        /* get current user data */
        $user_data = $this->models['m_categories']->data;
        $this->get_user_data($user_data);
        if (in_array('id', $arg)) {
            $id = filter_var($arg[array_search('id', $arg) + 1], FILTER_VALIDATE_INT);
            if (isset($_POST['title']) && $_POST['title']) {                
                $id = $this->models['m_categories']->update_category_data($_POST);                
                if (!$id) {
                    $notifications = new Class_notifications();
                    $notifications->add_notification_user($this->logged_in, ['text' => 'Ошибка записи в БД', 'status' => 'danger']);
                } else {
                    if (!$_POST['type_id']) SysClass::return_to_main(200, ENV_URL_SITE . '/admin/category_edit/id/' . $id);
                }
            }
            $get_category_data = (int)$id ? $this->models['m_categories']->get_category_data($id) : [];
        } else { // Не передан ключевой параметр id
            SysClass::return_to_main(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        $parents = $this->models['m_categories']->getCategoriesTree($id);
        /* view */
        $get_all_types = $this->models['m_types']->get_all_types();
        $this->view->set('category_data', $get_category_data);
        $this->view->set('parents', $parents);
        $this->view->set('all_type', $get_all_types);
        $this->get_standart_view();
        $this->view->set('body_view', $this->view->read('v_edit_category'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = 'Редактирование категорий';
        $this->show_layout($this->parameters_layout);
    }
    
    /**
     * Вернёт таблицу категоий
     */
    public function get_categories_data_table() {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $this->load_model('m_categories', array($this->logged_in));
        if (!$this->lang['sys.name']) { // Подргужаем языковые переменные
            $user_data = $this->models['m_categories']->data;
            $this->get_user_data($user_data);
        }
        $post_data = $_POST;
        $data_table = [
            'columns' => [
                [
                    'field' => 'category_id',
                    'title' => 'ID',
                    'sorted' => false,
                    'filterable' => false
                ], [
                    'field' => 'title',
                    'title' => $this->lang['sys.name'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'parent_id',
                    'title' => $this->lang['parent'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'type_id',
                    'title' => $this->lang['type'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'created_at',
                    'title' => $this->lang['date_create'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'updated_at',
                    'title' => $this->lang['date_update'],
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
                'label' => $this->lang['parent']
            ],
            'type_id' => [
                'type' => 'select',
                'id' => "type_id",
                'value' => [],
                'label' => $this->lang['type'],
                'options' => [['value' => 0, 'label' => 'Любой']],
                'multiple' => true
            ],
            'created_at' => [
                'type' => 'date',
                'id' => "created_at",
                'value' => '',
                'label' => $this->lang['date_create']
            ],
            'updated_at' => [
                'type' => 'date',
                'id' => "updated_at",
                'value' => '',
                'label' => $this->lang['date_update']
            ],
        ];
        $this->load_model('m_types', []);
        foreach ($this->models['m_types']->get_all_types() as $item) {
           $filters['type_id']['options'][] = ['value' => $item['type_id'], 'label' => $item['name']];  
        }
        if ($post_data && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_show_table_prepare_params($post_data, $data_table['columns']);
            $users_array = $this->models['m_categories']->get_categories_data($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $users_array = $this->models['m_categories']->get_categories_data(false, false, false, 25);
        }
        foreach ($users_array['data'] as $item) {
            $data_table['rows'][] = [
                'category_id' => $item['category_id'],
                'title' => $item['title'],
                'parent_id' => $item['parent_title'] ? $item['parent_title'] : 'Без категории',
                'type_id' => $item['type_name'],
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'updated_at' => $item['updated_at'] ? date('d.m.Y', strtotime($item['updated_at'])) : '',
                'actions' => '<a href="/admin/category_edit/id/' . $item['category_id'] . '"'
                . 'class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . '<a href="/admin/category_dell/id/' . $item['category_id'] . '"'
                . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>'
            ];
        }
        $data_table['total_rows'] = $users_array['total_count'];
        if ($post_data) {
            echo Plugins::ee_show_table('category_table', $data_table, 'get_categories_data_table', $filters, $post_data["page"], $post_data["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('category_table', $data_table, 'get_categories_data_table', $filters);
        }
    }
}
