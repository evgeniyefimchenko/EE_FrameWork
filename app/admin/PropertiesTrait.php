<?php

namespace app\admin;

use classes\system\SysClass;
use classes\system\FileSystem;
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
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin');
        }
        $this->loadModel('m_properties');
        /* view */
        $this->getStandardViews();
        $properties_data = $this->get_properties_data_table();
        $get_all_property_types = $this->models['m_properties']->getAllPropertyTypes();
        $this->view->set('all_property_types', $get_all_property_types);
        $this->view->set('properties_table', $properties_data);
        $this->view->set('body_view', $this->view->read('v_properties'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - Свойства';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - Свойства';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Список типов свойств
     */
    public function types_properties() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin');
        }
        /* view */
        $this->getStandardViews();
        $types_properties_data = $this->getTypesPropertiesDataTable();
        $this->view->set('types_properties_table', $types_properties_data);
        $this->view->set('body_view', $this->view->read('v_properties_types'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - Типы свойств';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - Типы свойств';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    public function getTypesPropertiesDataTable() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_properties');
        $postData = SysClass::ee_cleanArray($_POST);
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
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $features_array = $this->models['m_properties']->getTypePropertiesData($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $features_array = $this->models['m_properties']->getTypePropertiesData(false, false, false, 25);
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
        if ($postData) {
            echo Plugins::ee_show_table('types_properties_table', $data_table, 'getTypesPropertiesDataTable', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('types_properties_table', $data_table, 'getTypesPropertiesDataTable', $filters);
        }
    }

    /**
     * Вернёт таблицу свойств
     */
    public function get_properties_data_table() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_properties');
        $postData = SysClass::ee_cleanArray($_POST);
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
        foreach ($this->models['m_properties']->getAllPropertyTypes() as $item) {
            $filters['type_id']['options'][] = ['value' => $item['type_id'], 'label' => $item['name']];
        }
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $features_array = $this->models['m_properties']->getPropertiesData($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $features_array = $this->models['m_properties']->getPropertiesData(false, false, false, 25);
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
        if ($postData) {
            echo Plugins::ee_show_table('properties_table', $data_table, 'get_properties_data_table', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('properties_table', $data_table, 'get_properties_data_table', $filters);
        }
    }

    /**
     * Добавить или редактировать тип свойств
     */
    public function type_properties_edit($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
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
        $this->loadModel('m_properties');
        $postData = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
               $id = 0; 
            }
            if (isset($postData['name']) && $postData['name']) {
                if (!is_array($postData['fields']) || !count($postData['fields'])) {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Заполните хотя бы одно поле типа!', 'status' => 'danger']);
                } else {
                    $postData['fields'] = json_encode($postData['fields']);
                    if (!$new_id = $this->models['m_properties']->updatePropertyTypeData($postData)) {
                        ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                    } else {
                        $id = $new_id;
                    }
                }
            }            
            $property_type_data = (int) $id ? $this->models['m_properties']->getTypePropertyData($id) : $default_data;            
            $property_type_data = !$property_type_data ? $default_data : $property_type_data;
            $property_type_data['fields'] = isset($property_type_data['fields']) ? json_decode($property_type_data['fields'], true) : [];
        } else { // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/type_properties_edit/id');
        }
        foreach (Constants::ALL_STATUS as $key => $value) {
            $all_status[$key] = $this->lang['sys.' . $value];
        }
        /* view */
        $this->view->set('property_type_data', $property_type_data);        
        $this->view->set('count_fields', count($property_type_data['fields']));        
        $this->view->set('all_status', $all_status);
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_edit_type_properties'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_property_type.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Редактирование типа свойств';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Добавить или редактировать свойство
     */
    public function edit_property(array $params = []): void {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* model */
        $this->loadModel('m_properties');
        
        $postData = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $propertyId = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
               $propertyId = 0; 
            }
            if (isset($postData['name']) && $postData['name']) {
                if (isset($postData['dataFiles'])) { // Переданы файлы для сохранения
                    $this->saveFileProperty($postData); // TODO не пишет свойство с файлом
                }                
                $postData['default_values'] = isset($postData['property_data']) ? $this->prepareDefaultValuesProperty($postData['property_data'], $propertyId) : [];
                if (!$new_id = $this->models['m_properties']->updatePropertyData($postData)) {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                } else {
                    $propertyId = $new_id;
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.success'], 'status' => 'info']);
                }
            }
            $getPropertyData = $this->getPropertyData($propertyId);
        } else { // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        $get_all_property_types = $this->models['m_properties']->getAllPropertyTypes();
        foreach (Constants::ALL_STATUS as $key => $value) {
            $all_status[$key] = $this->lang['sys.' . $value];
        }
        /* view */
        $this->view->set('property_data', $getPropertyData);
        $this->view->set('all_property_types', $get_all_property_types);
        $this->view->set('all_status', $all_status);
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_edit_property'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_property.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/func_properties.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Редактирование свойства';
        $this->showLayout($this->parameters_layout);
    }
    
    /**
     * Сохраняет свойства файлов и применяет к ним трансформации, если это необходимо
     * @param array $postData Массив данных
     * @throws Exception Если возникает ошибка при загрузке или перемещении файла
     */
    private function saveFileProperty(array &$postData): void {
        $fileId = false;
        $dataFiles = $postData['dataFiles'];        
        foreach ($dataFiles as $dataFileJson) {
            $dataFileJson = htmlspecialchars_decode($dataFileJson);
            $dataFile = is_string($dataFileJson) ? json_decode($dataFileJson, true) : $dataFileJson;
            if (isset($dataFile['unique_id'])) {
                $uniqueID = $dataFile['unique_id'];
                $originalName = isset($dataFile['original_name']) ? $dataFile['original_name'] : '';
                if (isset($_FILES['upload_file']['name'][$uniqueID])) {
                    // Получаем данные загруженного файла
                    $file = [
                        'name' => $_FILES['upload_file']['name'][$uniqueID],
                        'type' => $_FILES['upload_file']['type'][$uniqueID],
                        'tmp_name' => $_FILES['upload_file']['tmp_name'][$uniqueID],
                        'error' => $_FILES['upload_file']['error'][$uniqueID],
                        'size' => $_FILES['upload_file']['size'][$uniqueID],
                    ];
                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        $message = "Ошибка загрузки файла: " . $file['error'];
                        SysClass::preFile('errors', 'saveFileProperty', $message, $file);
                        ClassNotifications::addNotificationUser($this->logged_in, ['text' => $message, 'status' => 'danger']);
                        continue;
                    }
                    // Применяем трансформации (если указаны)
                    if (isset($dataFile['transformations'])) {
                        FileSystem::applyImageTransformations($file['tmp_name'], $dataFile['transformations']);
                    }                   
                    if (!$fileData = FileSystem::safeMoveUploadedFile($file)) {
                        $message = 'Файл не сохранён ' . $file['name'];
                        SysClass::preFile('errors', 'saveFileProperty', $message, $file);
                        ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);                        
                        continue;                        
                    } else { // Сохраняем данные файла в таблицу
                        $fileData['original_name'] = $originalName;
                        $fileData['image_size'] = ''; // задел на будущее
                        $fileData['user_id'] = SysClass::getCurrentUserId();
                        $fileId = FileSystem::saveFileInfo($fileData);
                        if ($fileId) {
                            $fileIds = [];
                            if (isset($postData[$dataFile['property_name']])) {
                                $fileIds = explode(',', $postData[$dataFile['property_name']]);
                            }
                            $fileIds[] = $fileId;                                
                            $postData[$dataFile['property_name']] = implode(',', $fileIds);
                        }
                    }
                }
            }
        }
    }

    /**
     * Подготовка списка полей для свойства
     * Используется как в AJAX запросе так и напрямую в коде
     * При AJAX вызове, роутер передаёт в $propertyId массив
     * @param type $propertyId
     */
    public function getPropertyData(mixed $propertyId = 0):mixed {
        $is_ajax = SysClass::isAjaxRequestFromSameSite();
        if ($is_ajax) {
            $postData = SysClass::ee_cleanArray($_POST);
            $this->access = [Constants::ADMIN, Constants::MODERATOR];
            if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
                SysClass::handleRedirect();
                exit();
            }
        }
        $this->loadModel('m_properties');
        $default_data = [
            'title' => '', 'property_id' => 0, 'name' => '', 'is_multiple' => 0, 'is_required' => 0, 'description' => '',
            'status' => 0, 'sort' => 100, 'type_id' => 0, 'created_at' => false, 'updated_at' => false, 'default_values' => []
        ];
        if ($is_ajax) {
            $data = $this->models['m_properties']->getTypePropertyData($postData['type_id']);
            $fields = $data['fields'];
            $default_values = [];
            echo json_encode(['html' => Plugins::renderPropertyHtmlFields($fields, $default_values)]);
            die;
        } else {
            $getPropertyData = (int) $propertyId ? $this->models['m_properties']->getPropertyData($propertyId) : $default_data;
            $getPropertyData = !$getPropertyData ? $default_data : $getPropertyData;
            $getPropertyData['fields'] = isset($getPropertyData['fields']) ? $getPropertyData['fields'] : [];
            $getPropertyData['default_values'] = isset($getPropertyData['default_values']) ? $getPropertyData['default_values'] : [];            
            return $getPropertyData;
        }
    }
    
    /**
     * Подготавливает данные свойств для сохранения в формате JSON в базе данных
     * Функция принимает ассоциативный массив данных свойств, где ключи представляют собой
     * строку, включающую тип свойства, порядковый номер и дополнительный ключ (если есть),
     * а значения могут быть строками или массивами. Функция возвращает массив, структурированный
     * для последующей конвертации в JSON, который может быть сохранен в базе данных
     * @param array $propertyData Ассоциативный массив данных свойств
     * @return array
     */
    private function prepareDefaultValuesProperty(array $propertyData, int $propertyId): array {
        $prepared_data = [];        
        foreach ($propertyData as $key => $value) {
            // Извлекаем порядковый номер и тип из ключа
            if (preg_match('/([a-z\-]+)_([0-9]+)_?([a-z]*)/', $key, $matches)) {
                $type = $matches[1];
                $index = $matches[2];
                $additional_key = isset($matches[3]) ? $matches[3] : null;
                // Генерация уникального кода
                $unique_code = hash('crc32', $type . $index . $propertyId);
                // Проверка уникальности кода
                while ($this->models['m_properties']->findUniqueCodeInPropertiesTable($unique_code)) {
                    // Генерация нового уникального кода, если текущий уже существует
                    $unique_code = hash('crc32', $type . $index . $propertyId . uniqid());
                }
                if (!isset($prepared_data[$index])) {
                    $prepared_data[$index] = [
                        'type' => $type,
                        'label' => '',
                        'title' => '',
                        'default' => '',
                        'required' => 0,
                        'multiple' => 0,
                        'unique_code' => $unique_code
                    ];
                }
                // Заполнение данных в зависимости от дополнительного ключа
                if ($additional_key) {
                    if ($additional_key === 'default' && is_array($value)) {
                        $prepared_data[$index]['default'] = SysClass::ee_cleanArray($value);
                    } elseif ($additional_key === 'multiple' && $value === 'on') {
                        $prepared_data[$index]['multiple'] = 1;
                    } elseif ($additional_key === 'required' && $value === 'on') {
                        $prepared_data[$index]['required'] = 1;
                    } else {
                        $prepared_data[$index][$additional_key] = SysClass::ee_cleanArray($value);
                    }
                }
            }
        }
        ksort($prepared_data);
        return SysClass::ee_removeEmptyValuesToArray($prepared_data);
    }

    /**
     * Удалит выбранный тип свойства
     * @param array $params
     */
    public function type_properties_delete($params = []) {
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
            $this->loadModel('m_properties');
            $res = $this->models['m_properties']->typePropertiesDelete($id);
            if (count($res)) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Ошибка удаления типа id=' . $id . '<br/>' . $res['error'], 'status' => 'danger']);
            } else {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Удалено!', 'status' => 'info']);
            }
        } else {
            ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Нет обязательного параметра id', 'status' => 'danger']);
        }
        SysClass::handleRedirect(200, '/admin/types_properties');
    }

    /**
     * Удалит свойство
     * @param array $params
     */
    public function property_delete($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $propertyId = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $propertyId = 0; 
            }            
            $this->loadModel('m_properties');
            $res = $this->models['m_properties']->propertyDelete($propertyId);
            if (count($res)) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Ошибка удаления свойства id=' . $propertyId . '<br/>' . $res['error'], 'status' => 'danger']);
            } else {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Удалено!', 'status' => 'info']);
            }
        } else {
            ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Нет обязательного параметра id', 'status' => 'danger']);
        }
        SysClass::handleRedirect(200, '/admin/properties');
    }

    /**
     * Наборы свойств
     * @param type $params
     */
    public function properties_sets($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }        
        $properties_property_sets_table = $this->get_properties_property_sets_table();
        /* view */
        $this->getStandardViews();
        $this->view->set('properties_property_sets_table', $properties_property_sets_table);
        $this->view->set('body_view', $this->view->read('v_properties_sets'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . $this->lang['sys.sets'];
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - ' . $this->lang['sys.sets'];
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу наборов свойств
     */
    public function get_properties_property_sets_table() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_properties');
        $postData = SysClass::ee_cleanArray($_POST);
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
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $features_array = $this->models['m_properties']->getPropertySetsData($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $features_array = $this->models['m_properties']->getPropertySetsData(false, false, false, 25);
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
        if ($postData) {
            echo Plugins::ee_show_table('properties_table', $data_table, 'get_properties_property_sets_table', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
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
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_properties');
        $default_data = [
            'set_id' => 0,
            'name' => '',
            'status' => 'active',
            'description' => '',
            'created_at' => false,
            'updated_at' => false
        ];
        $postData = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $id = 0; 
            }
            // Обработка основных полей
            if (isset($postData['name']) && $postData['name']) {                
                if (!$new_id = $this->models['m_properties']->updatePropertySetData($postData)) {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                } else {
                    $id = $new_id;
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.success'], 'status' => 'info']);
                }
            }
            // Обработка добавления свойств
            if (isset($postData['selected_properties']) && is_array($postData['selected_properties'])) {
                // Удалить все предыдущие свойства для этого набора
                $this->models['m_properties']->deletePreviousProperties($id);
                // Добавить выбранные свойства в таблицу
                $this->models['m_properties']->addPropertiesToSet($id, $postData['selected_properties']);
            }
            $property_set_data = (int) $id ? $this->models['m_properties']->getPropertySetData($id) : $default_data;
            $property_set_data = $property_set_data ? $property_set_data : $default_data;
        } else { // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        /* view */
        $this->getStandardViews();
        $this->view->set('property_set_data', $property_set_data);
        $this->view->set('all_properties_data', $this->models['m_properties']->getAllProperties('active'));
        $this->view->set('body_view', $this->view->read('v_edit_property_set'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_property_set.js" type="text/javascript" /></script>';
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . $this->lang['sys.sets'];
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - ' . $this->lang['sys.sets'];
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Удалит набор свойств
     * @param array $params
     */
    public function property_set_delete($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $set_id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $set_id = 0;
            }
            $this->loadModel('m_properties');
            $res = $this->models['m_properties']->property_set_delete($set_id);
            if (count($res)) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Ошибка удаления свойства id=' . $set_id . '<br/>' . $res['error'], 'status' => 'danger']);
            } else {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Удалено!', 'status' => 'info']);
            }
        } else {
            ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Нет обязательного параметра id', 'status' => 'danger']);
        }
        SysClass::handleRedirect(200, '/admin/properties');
    }

}
