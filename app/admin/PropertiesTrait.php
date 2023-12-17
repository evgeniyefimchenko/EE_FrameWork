<?php

namespace app\admin;

use classes\system\SysClass;
use classes\system\Constants;
use classes\helpers\ClassNotifications;
use classes\system\Plugins;

/**
 * Функции работы с типами
 */
trait PropertiesTrait {

    /**
     * Список свойств
     */
    public function properties() {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(200, '/show_login_form?return=admin');
        }
        $this->load_model('m_properties');
        /* view */
        $this->get_standart_view();
        $properties_data = $this->get_properties_data_table();
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
        $this->parameters_layout["keywords"] = SysClass::keywords($this->html);
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
        /* view */
        $this->get_standart_view();
        $types_properties_data = $this->get_types_properties_data_table();
        $this->view->set('types_properties_table', $types_properties_data);
        $this->view->set('body_view', $this->view->read('v_properties_types'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - Типы свойств';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - Типы свойств';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::keywords($this->html);
        $this->show_layout($this->parameters_layout);
    }

    public function get_types_properties_data_table() {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $this->load_model('m_properties');
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
        $this->load_model('m_properties');
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
                'actions' => '<a href="/admin/edit_property/id/' . $item['property_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . '<a href="/admin/property_delete/id/' . $item['property_id'] . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
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
        $default_data = [
            'type_id' => 0,
            'name' => '',
            'status' => 'active',
            'fields' => '["text"]',
            'description' => '',
            'created_at' => false,
            'updated_at' => false
        ];
        /* model */
        $this->load_model('m_properties');
        $post_data = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $key_id = array_search('id', $params);
            if ($key_id !== false && isset($params[$key_id + 1])) {
                $id = filter_var($params[$key_id + 1], FILTER_VALIDATE_INT);
            } else {
               $id = 0; 
            }
            if (isset($post_data['name']) && $post_data['name']) {
                if (!is_array($post_data['fields']) || !count($post_data['fields'])) {
                    ClassNotifications::add_notification_user($this->logged_in, ['text' => 'Заполните хотя бы одно поле типа!', 'status' => 'danger']);
                    goto exit_update;
                }
                $post_data['fields'] = json_encode($post_data['fields']);
                if (!$new_id = $this->models['m_properties']->update_property_type_data($post_data)) {
                    ClassNotifications::add_notification_user($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                } else {
                    $id = $new_id;
                }
            }
            exit_update:
            $property_type_data = (int) $id ? $this->models['m_properties']->get_type_property_data($id) : $default_data;
            $property_type_data = !$property_type_data ? $default_data : $property_type_data;
            $property_type_data['fields'] = isset($property_type_data['fields']) ? json_decode($property_type_data['fields'], true) : [];
        } else { // Не передан ключевой параметр id
            SysClass::return_to_main(200, ENV_URL_SITE . '/admin/type_properties_edit/id');
        }
        foreach (Constants::ALL_STATUS as $key => $value) {
            $all_status[$key] = $this->lang['sys.' . $value];
        }
        /* view */
        // SysClass::pre($property_type_data);
        $this->view->set('property_type_data', $property_type_data);
        $this->view->set('all_status', $all_status);
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
    public function edit_property($params) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $default_data = [
            'property_id' => 0, 'name' => '', 'is_multiple' => 0, 'is_required' => 0, 'description' => '',
            'status' => 0, 'type_id' => 0, 'created_at' => false, 'updated_at' => false
        ];
        /* model */
        $this->load_model('m_properties');
        
        $post_data = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $key_id = array_search('id', $params);
            if ($key_id !== false && isset($params[$key_id + 1])) {
                $id = filter_var($params[$key_id + 1], FILTER_VALIDATE_INT);
            } else {
               $id = 0; 
            }
            if (isset($post_data['name']) && $post_data['name']) {
                $post_data['default_values'] = $this->prepare_default_values_property($post_data['property_data']);
                if (!$new_id = $this->models['m_properties']->update_property_data($post_data)) {
                    ClassNotifications::add_notification_user($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                } else {
                    $id = $new_id;
                    ClassNotifications::add_notification_user($this->logged_in, ['text' => $this->lang['sys.success'], 'status' => 'info']);
                }
            }
            $get_property_data = (int) $id ? $this->models['m_properties']->get_property_data($id) : $default_data;
            $get_property_data = !$get_property_data ? $default_data : $get_property_data;
            $get_property_data['fields'] = isset($get_property_data['fields']) ? $get_property_data['fields'] : ['text'];
            $get_property_data['default_values'] = isset($get_property_data['default_values']) ? $get_property_data['default_values'] : [];
        } else { // Не передан ключевой параметр id
            SysClass::return_to_main(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        $get_all_property_types = $this->models['m_properties']->get_all_property_types();
        foreach (Constants::ALL_STATUS as $key => $value) {
            $all_status[$key] = $this->lang['sys.' . $value];
        }
        /* view */
        $this->view->set('property_data', $get_property_data);
        $this->view->set('all_property_types', $get_all_property_types);
        $this->view->set('all_status', $all_status);
        $this->get_standart_view();
        $this->view->set('body_view', $this->view->read('v_edit_property'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/edit_property.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Редактирование свойства';
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Подготавливает данные свойств для сохранения в формате JSON в базе данных.
     * Функция принимает ассоциативный массив данных свойств, где ключи представляют собой
     * строку, включающую тип свойства, порядковый номер и дополнительный ключ (если есть),
     * а значения могут быть строками или массивами. Функция возвращает массив, структурированный
     * для последующей конвертации в JSON, который может быть сохранен в базе данных.
     * @param array $property_data Ассоциативный массив данных свойств.
     * @return array
     */
    private function prepare_default_values_property($property_data = []) {
        $prepared_data = [];
        foreach ($property_data as $key => $value) {
            // Извлекаем порядковый номер и тип из ключа
            if (preg_match('/([a-z\-]+)_([0-9]+)_?([a-z]*)/', $key, $matches)) {
                $type = $matches[1];
                $index = $matches[2];
                $additional_key = isset($matches[3]) ? $matches[3] : null;
                if (!isset($prepared_data[$index])) {
                    $prepared_data[$index] = [
                        'type' => $type,
                        'label' => '',
                        'title' => '',
                        'default' => '',
                        'required' => 0,
                        'multiple' => 0
                    ];
                }
                // Заполнение данных в зависимости от дополнительного ключа
                if ($additional_key) {
                    if ($additional_key === 'default' && is_array($value)) {
                        $prepared_data[$index]['default'] = implode(',', $value);
                    } elseif ($additional_key === 'multiple' && $value === 'on') {
                        $prepared_data[$index]['multiple'] = 1;
                    } elseif ($additional_key === 'required' && $value === 'on') {
                        $prepared_data[$index]['required'] = 1;
                    } else {
                        $prepared_data[$index][$additional_key] = $value;
                    }
                }
            }
        }
        ksort($prepared_data);
        return SysClass::ee_remove_empty_values($prepared_data);
    }

    /**
     * Удалит выбранный тип свойства
     * @param array $params
     */
    public function type_properties_delete($params = []) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        if (in_array('id', $params)) {
            $key_id = array_search('id', $params);
            if ($key_id !== false && isset($params[$key_id + 1])) {
                $id = filter_var($params[$key_id + 1], FILTER_VALIDATE_INT);
            } else {
               $id = 0; 
            }
            $this->load_model('m_properties');
            $res = $this->models['m_properties']->type_properties_delete($id);
            if (count($res)) {
                ClassNotifications::add_notification_user($this->logged_in, ['text' => 'Ошибка удаления типа id=' . $id . '<br/>' . $res['error'], 'status' => 'danger']);
            } else {
                ClassNotifications::add_notification_user($this->logged_in, ['text' => 'Удалено!', 'status' => 'info']);
            }
        } else {
            ClassNotifications::add_notification_user($this->logged_in, ['text' => 'Нет обязательного параметра id', 'status' => 'danger']);
        }
        SysClass::return_to_main(200, '/admin/types_properties');
    }

    /**
     * Удалит свойство
     * @param array $params
     */
    public function property_delete($params = []) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        if (in_array('id', $params)) {
            $key_id = array_search('id', $params);
            if ($key_id !== false && isset($params[$key_id + 1])) {
                $property_id = filter_var($params[$key_id + 1], FILTER_VALIDATE_INT);
            } else {
                $property_id = 0; 
            }            
            $this->load_model('m_properties');
            $res = $this->models['m_properties']->property_delete($property_id);
            if (count($res)) {
                ClassNotifications::add_notification_user($this->logged_in, ['text' => 'Ошибка удаления свойства id=' . $property_id . '<br/>' . $res['error'], 'status' => 'danger']);
            } else {
                ClassNotifications::add_notification_user($this->logged_in, ['text' => 'Удалено!', 'status' => 'info']);
            }
        } else {
            ClassNotifications::add_notification_user($this->logged_in, ['text' => 'Нет обязательного параметра id', 'status' => 'danger']);
        }
        SysClass::return_to_main(200, '/admin/properties');
    }

    /**
     * Наборы свойств TODO
     * @param type $params
     */
    public function properties_sets($params = []) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }        
        $properties_property_sets_table = $this->get_properties_property_sets_table();
        /* view */
        $this->get_standart_view();
        $this->view->set('properties_property_sets_table', $properties_property_sets_table);
        $this->view->set('body_view', $this->view->read('v_properties_sets'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . $this->lang['sys.sets'];
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - ' . $this->lang['sys.sets'];
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::keywords($this->html);
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу наборов свойств
     */
    public function get_properties_property_sets_table() {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $this->load_model('m_properties');
        $post_data = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'set_id',
                    'title' => 'ID',
                    'sorted' => 'ASC',
                    'filterable' => false
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
            $features_array = $this->models['m_properties']->get_property_sets_data($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $features_array = $this->models['m_properties']->get_property_sets_data(false, false, false, 25);
        }

        foreach ($features_array['data'] as $item) {
            $data_table['rows'][] = [
                'set_id' => $item['set_id'],
                'name' => $item['name'],
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'updated_at' => $item['updated_at'] ? date('d.m.Y', strtotime($item['updated_at'])) : '',
                'actions' => '<a href="/admin/edit_property_set/id/' . $item['set_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . '<a href="/admin/property_set_delete/id/' . $item['set_id'] . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>'
            ];
        }
        $data_table['total_rows'] = $features_array['total_count'];
        if ($post_data) {
            echo Plugins::ee_show_table('properties_table', $data_table, 'get_properties_property_sets_table', $filters, $post_data["page"], $post_data["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('properties_table', $data_table, 'get_properties_property_sets_table', $filters);
        }
    }

    /**
     * Редактирует набор свойств на основе предоставленных параметров.
     * @param array $params Ассоциативный массив параметров. Если массив содержит ключ 'id', то функция будет
     *                      редактировать существующий набор свойств с этим ID. В противном случае будет
     *                      произведено создание нового набора свойств.
     * @throws Exception Если возникают проблемы с доступом, функция перенаправляет пользователя на главную страницу.
     */
    public function edit_property_set($params = []) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $this->load_model('m_properties');
        $default_data = [
            'set_id' => 0,
            'name' => '',
            'status' => 'active',
            'description' => '',
            'created_at' => false,
            'updated_at' => false
        ];
        $post_data = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $key_id = array_search('id', $params);
            if ($key_id !== false && isset($params[$key_id + 1])) {
                $id = filter_var($params[$key_id + 1], FILTER_VALIDATE_INT);
            } else {
                $id = 0; 
            }
            // Обработка основных полей
            if (isset($post_data['name']) && $post_data['name']) {
                if (!$new_id = $this->models['m_properties']->update_property_set_data($post_data)) {
                    ClassNotifications::add_notification_user($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                } else {
                    $id = $new_id;
                    ClassNotifications::add_notification_user($this->logged_in, ['text' => $this->lang['sys.success'], 'status' => 'info']);
                }
            }
            // Обработка добавления свойств
            if (isset($post_data['selected_properties']) && is_array($post_data['selected_properties'])) {
                // Удалить все предыдущие свойства для этого набора
                $this->models['m_properties']->deletePreviousProperties($id);
                // Добавить выбранные свойства в таблицу
                $this->models['m_properties']->addPropertiesToSet($id, $post_data['selected_properties']);
            }
            $property_set_data = (int) $id ? $this->models['m_properties']->get_property_set_data($id) : $default_data;
            $property_set_data = $property_set_data ? $property_set_data : $default_data;
        } else { // Не передан ключевой параметр id
            SysClass::return_to_main(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        /* view */
        $this->get_standart_view();
        $this->view->set('property_set_data', $property_set_data);
        $this->view->set('all_properties_data', $this->models['m_properties']->get_all_properties('active'));
        $this->view->set('body_view', $this->view->read('v_edit_property_set'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/edit_property_set.js" type="text/javascript" /></script>';
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . $this->lang['sys.sets'];
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - ' . $this->lang['sys.sets'];
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::keywords($this->html);
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Удалит набор свойств
     * @param array $params
     */
    public function property_set_delete($params = []) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        if (in_array('id', $params)) {
            $key_id = array_search('id', $params);
            if ($key_id !== false && isset($params[$key_id + 1])) {
                $set_id = filter_var($params[$key_id + 1], FILTER_VALIDATE_INT);
            } else {
                $set_id = 0;
            }
            $this->load_model('m_properties');
            $res = $this->models['m_properties']->property_set_delete($set_id);
            if (count($res)) {
                ClassNotifications::add_notification_user($this->logged_in, ['text' => 'Ошибка удаления свойства id=' . $set_id . '<br/>' . $res['error'], 'status' => 'danger']);
            } else {
                ClassNotifications::add_notification_user($this->logged_in, ['text' => 'Удалено!', 'status' => 'info']);
            }
        } else {
            ClassNotifications::add_notification_user($this->logged_in, ['text' => 'Нет обязательного параметра id', 'status' => 'danger']);
        }
        SysClass::return_to_main(200, '/admin/properties');
    }

}
