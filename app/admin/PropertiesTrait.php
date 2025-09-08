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
        $properties_data = $this->getPropertiesDataTable();
        $getAllPropertyTypes = $this->models['m_properties']->getAllPropertyTypes();
        $this->view->set('all_property_types', $getAllPropertyTypes);
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
        $dataTable = [
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
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $dataTable['columns']);
            $features_array = $this->models['m_properties']->getTypePropertiesData($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $features_array = $this->models['m_properties']->getTypePropertiesData(false, false, 0, 25);
        }

        foreach ($features_array['data'] as $item) {
            $dataTable['rows'][] = [
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
        $dataTable['total_rows'] = $features_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('types_properties_table', $dataTable, 'getTypesPropertiesDataTable', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('types_properties_table', $dataTable, 'getTypesPropertiesDataTable', $filters);
        }
    }

    /**
     * Вернёт таблицу свойств
     */
    public function getPropertiesDataTable() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_properties');
        $postData = SysClass::ee_cleanArray($_POST);
        $dataTable = [
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
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $dataTable['columns']);
            $features_array = $this->models['m_properties']->getPropertiesData($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $features_array = $this->models['m_properties']->getPropertiesData(false, false, false, 25);
        }

        foreach ($features_array['data'] as $item) {
            $dataTable['rows'][] = [
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
        $dataTable['total_rows'] = $features_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('properties_table', $dataTable, 'getPropertiesDataTable', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('properties_table', $dataTable, 'getPropertiesDataTable', $filters);
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
                $typeId = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
               $typeId = 0; 
            }
            $newEntity = empty($typeId);
            $usedByProperties = $this->models['m_properties']->isExistPropertiesWithType($typeId);
            if (isset($postData['name']) && $postData['name']) {
                if (!is_array($postData['fields']) || !count($postData['fields'])) {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Заполните хотя бы одно поле типа!', 'status' => 'danger']);
                } else {                    
                    $postData['fields'] = json_encode($postData['fields']);
                    if ($usedByProperties) unset($postData['fields']);
                    if (!$new_id = $this->models['m_properties']->updatePropertyTypeData($postData)) {
                        ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                    } else {
                        $typeId = $new_id;
                    }
                }
            }
            
            if (isset($postData['name'])) $this->processPostParams($postData, $newEntity, $typeId);
            $propertyTypeData = (int) $typeId ? $this->models['m_properties']->getTypePropertyData($typeId) : $default_data;            
            $propertyTypeData = !$propertyTypeData ? $default_data : $propertyTypeData;
            $propertyTypeData['fields'] = isset($propertyTypeData['fields']) ? json_decode($propertyTypeData['fields'], true) : [];
        } else { // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/type_properties_edit/id');
        }
        foreach (Constants::ALL_STATUS as $key => $value) {
            $allStatus[$key] = $this->lang['sys.' . $value];
        }        
        /* view */
        $this->view->set('property_type_data', $propertyTypeData);        
        $this->view->set('count_fields', count($propertyTypeData['fields']));        
        $this->view->set('all_status', $allStatus);
        $this->view->set('usedByProperties', $usedByProperties);
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
        $defaultData = [
            'property_id' => 0,
            'type_id' => 0,
            'name' => '',
            'status' => 'active',
            'sort' => '100',
            'default_values' => '[]',
            'is_multiple' => '0',
            'is_required' => '0',
            'description' => '',
            'entity_type' => 'all',
            'fields' => '[]',
            'created_at' => false,
            'updated_at' => false 
        ];        
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
            $newEntity = empty($propertyId);
            $isExistSetsWithProperty = $this->models['m_properties']->isExistSetsWithProperty($propertyId);
            if (isset($postData['name']) && $postData['name']) {
                $this->saveFileProperty($postData);                
                $postData['default_values'] = isset($postData['property_data']) ? $this->prepareDefaultValuesProperty($postData['property_data'], $propertyId) : [];                
                if ($isExistSetsWithProperty) {
                    $postData['type_id'] = $this->models['m_properties']->getTypeIdByPropertyId($propertyId);
                }
                if (!$new_id = $this->models['m_properties']->updatePropertyData($postData)) {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                } else {                    
                    $propertyId = $new_id;
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.success'], 'status' => 'info']);
                }
            }            
            if (isset($postData['name'])) $this->processPostParams($postData, $newEntity, $propertyId);
            $getPropertyData = !empty($propertyId) ? $this->getPropertyData($propertyId) : $defaultData;
        } else { // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        $getAllPropertyTypes = $this->models['m_properties']->getAllPropertyTypes();
        foreach (Constants::ALL_STATUS as $key => $value) {
            $allStatus[$key] = $this->lang['sys.' . $value];
        }
        foreach (Constants::ALL_ENTITY_TYPE as $key => $value) {
            $allEntityType[$key] = $this->lang[$value];
        }
        /* view */
        $this->view->set('propertyData', $getPropertyData);
        $this->view->set('allPropertyTypes', $getAllPropertyTypes);
        $this->view->set('allStatus', $allStatus);
        $this->view->set('allEntityType', $allEntityType);
        $this->view->set('isExistSetsWithProperty', $isExistSetsWithProperty);
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
        $dataFiles = !empty($postData['ee_dataFiles']) ? $postData['ee_dataFiles'] : false;
        $changed = !empty($postData['property_data_changed']) ? $postData['property_data_changed'] : false;
        if (is_array($dataFiles)) {
            $fileIds = [];
            foreach ($dataFiles as $dataFileJson) {
                $dataFileJson = htmlspecialchars_decode($dataFileJson);
                $dataFile = is_string($dataFileJson) ? json_decode($dataFileJson, true) : $dataFileJson;
                if (!empty($dataFile['unique_id'])) {                    
                    if (!empty($postData['property_data'][$dataFile['property_name']]) && is_string($postData['property_data'][$dataFile['property_name']])) {                        
                        $fileIds = explode(',', $postData['property_data'][$dataFile['property_name']]);
                    }
                    $uniqueID = $dataFile['unique_id'];
                    if (is_numeric($uniqueID)) { // Передан ранее загруженный файл  
                        if (isset($dataFile['update']) && $dataFile['update'] === true) { // Есть обновления
                            $changed = true;
                            if (isset($dataFile['delete']) && $dataFile['delete'] === true) { // Удалить файл
                                FileSystem::deleteFileData($uniqueID);
                                continue;
                            } else { // Обновить данные
                                $data['original_name'] = $dataFile['original_name'];
                                FileSystem::updateFileData($uniqueID, $data);
                                unset($data);
                            }
                        }
                        $fileIds[] = $uniqueID;
                        $postData['property_data'][$dataFile['property_name']] = implode(',', $fileIds);
                        $fileIds = [];
                        continue;
                    }                    
                    if (isset($_FILES['property_data']['name'][$dataFile['property_name']]) && count($_FILES['property_data']['name'][$dataFile['property_name']])) {
                        $fileCount = count($_FILES['property_data']['name'][$dataFile['property_name']]);
                        for ($i = 0; $i < $fileCount; $i++) {
                            $file = [
                                'name' => $_FILES['property_data']['name'][$dataFile['property_name']][$i],
                                'type' => $_FILES['property_data']['type'][$dataFile['property_name']][$i],
                                'tmp_name' => $_FILES['property_data']['tmp_name'][$dataFile['property_name']][$i],
                                'error' => $_FILES['property_data']['error'][$dataFile['property_name']][$i],
                                'size' => $_FILES['property_data']['size'][$dataFile['property_name']][$i],
                            ];
                            if ($file['name'] !== $dataFile['file_name']) continue; // Если имя файла не совпало то сейчас его не грузим так как в $dataFile другие данные
                            if ($file['error'] !== UPLOAD_ERR_OK) {
                                $message = FileSystem::getErrorDescriptionByUploadCode($file['error']);
                                new \classes\system\ErrorLogger($message . ': `' . var_export($file, true) . '`', __FUNCTION__);
                                ClassNotifications::addNotificationUser($this->logged_in, ['text' => $message, 'status' => 'danger']);
                                continue;
                            }
                            // Применяем трансформации (если указаны)
                            if (isset($dataFile['transformations'])) {
                                FileSystem::applyImageTransformations($file['tmp_name'], $dataFile['transformations']);
                            }
                            if (!$fileData = FileSystem::safeMoveUploadedFile($file)) {
                                $message = 'Файл не сохранён ' . $file['name'];
                                new \classes\system\ErrorLogger($message . ': `' . var_export([$file, $fileData], true) . '`', __FUNCTION__);
                                ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);                        
                                continue;                        
                            } else { // Сохраняем данные файла в таблицу
                                $originalName = isset($dataFile['original_name']) && !empty($dataFile['original_name']) ? $dataFile['original_name'] : $file['name'];
                                $fileData['original_name'] = $originalName;
                                $fileData['user_id'] = SysClass::getCurrentUserId();
                                if ($fileId = FileSystem::saveFileInfo($fileData)) {
                                    $changed = true;
                                    $fileIds[] = $fileId;                                
                                }                                
                            }
                        }
                    }                    
                }
                if (count($fileIds)) $postData['property_data'][$dataFile['property_name']] = implode(',', $fileIds);
                $fileIds = [];
            }
        }
        $postData['property_data_changed'] = $changed;
    }

    /**
     * Обрабатывает массив данных свойств и обновляет их в базе данных
     * @param array $propertyData Массив данных свойств
     * @return void
     */
    public function processPropertyData(array $propertyData): void {        
        $arrValueProp = [];
        $this->loadModel('m_properties');
        foreach ($propertyData as $itemPropKey => $itemPropValue) {
            $arrPropName = explode('_', $itemPropKey);
            $valueId = $arrPropName[0]; // property_values.value_id
            $keyProp = $arrPropName[1]; // index field
            $typeProp = $arrPropName[2]; // type field
            $entityIdProp = $arrPropName[3]; // entity ID
            $entityTypeProp = $arrPropName[4]; // entity type
            $propertyIdProp = $arrPropName[5]; // global property ID
            $setId = $arrPropName[6]; // set ID
            $addFieldProp = isset($arrPropName[7]) ? $arrPropName[7] : null; // Классификатор сущности поля multiple, type, label, value, title, count
            $keyArr = $propertyIdProp . '_' . $setId;
            $arrValueProp[$keyArr]['entity_id'] = $entityIdProp;
            $arrValueProp[$keyArr]['property_id'] = $propertyIdProp;
            $arrValueProp[$keyArr]['entity_type'] = $entityTypeProp;
            $arrValueProp[$keyArr]['value_id'] = $valueId;
            $arrValueProp[$keyArr]['set_id'] = $setId;
            if ($addFieldProp) {
                if (($addFieldProp == 'multiple' || $addFieldProp == 'required') && isset($itemPropValue)) {
                    $itemPropValue = 1;
                }
                if ($addFieldProp == 'value' && ($typeProp == 'image' || $typeProp == 'file')) {
                    $itemPropValue = is_array($itemPropValue) ? implode(',', $itemPropValue) : $itemPropValue;
                }
                $arrValueProp[$keyArr]['property_values'][$keyProp][$addFieldProp] = $itemPropValue;
            } else {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Error, not type value!', 'status' => 'danger']);
                SysClass::pre([$itemPropKey, $arrPropName]);
            }
        }
        foreach (SysClass::ee_removeEmptyValuesToArray($arrValueProp) as $arrValue) {
            array_walk($arrValue['property_values'], function (&$property) {
                if (!in_array($property['type'], ['file', 'image', 'checkbox', 'radio'], true) && empty($property['multiple'])) {
                    if (is_array($property['value'] ?? null)) {
                        $property['value'] = (string) array_shift($property['value']);
                    }
                }
            });            
            $res = $this->models['m_properties']->updatePropertiesValueEntities($arrValue);
            if ($res === false) {
                $message = 'Error, not write properties!';
                new \classes\system\ErrorLogger($message . ': `' . var_export($arrValue, true) . '`', __FUNCTION__);
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => $message, 'status' => 'danger']);
            }
        }
    }    

    /**
     * Подготавливает на вывод данные свойств для сущностей и сохраняет значения свойств при создании сущности
     * @param array $setIds Массив идентификаторов наборов свойств
     * @param int $entityId Идентификатор сущности
     * @param string $typeEntity тип сущности category, page
     * @param array $title Название сущности, её имя или заголовок
     * @return array Возвращает массив обработанных данных свойств
     */
    public function formattingEntityProperties(array $setIds, int $entityId, string $typeEntity, string $title = ''): array {
        $this->loadModel('m_properties');
        $result = [];
        foreach ($setIds as $setId) {
            $defaultValues = false;
            $propertiesData = $this->models['m_properties']->getPropertySetData($setId);
            foreach ($propertiesData['properties'] as $k_prop => &$prop) {
                if ($prop['property_entity_type'] != $typeEntity && $prop['property_entity_type'] != 'all') continue;
                if (!empty($prop['default_values'])) {
                    $defaultValues = json_decode($prop['default_values'], true);
                    unset($prop['default_values']);
                }
                $prop = array_merge($prop, $this->models['m_properties']->getPropertyValuesForEntity($entityId, $typeEntity, $prop['property_id'], $setId));                
                if (empty($prop['fields'])) { // Возникает при создании новой сущности
                    $count = 0;
                    $prop['entity_id'] = $entityId;
                    $prop['entity_type'] = $typeEntity;
                    $prop['set_id'] = $propertiesData['set_id'];
                    $prop['value_id'] = SysClass::ee_generate_uuid();
                    if (empty($defaultValues)) {
                        SysClass::pre('Критическая ошибка: default_values пусто или не установлено! ' . var_export($prop, true));
                    }
                    foreach ($defaultValues as $prop_default) {
                        $prop['fields'][$count] = [
                            'type' => $prop_default['type'],
                            'value' => isset($prop_default['default']) ? $prop_default['default'] : '',
                            'label' => $prop_default['label'],
                            'multiple' => $prop_default['multiple'],
                            'required' => $prop_default['required'],
                            'title' => isset($prop_default['title']) ? $prop_default['title'] : ''
                        ];
                        $count++;
                    }                    
                } else {
                   $defaultValues = false; 
                }
            }
            usort($propertiesData['properties'], function ($a, $b) {
                return $a['sort'] <=> $b['sort'];
            });
            if ($defaultValues) { // Записываем значения свойств при создании
                foreach ($propertiesData['properties'] as &$arrValue) {
                    unset($arrValue['value_id']);
                    $arrValue['value_id'] = $this->models['m_properties']->updatePropertiesValueEntities($arrValue);                    
                }
            }
            $result[$title][$setId] = $propertiesData;
        }
        return $result;
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
                $flattenedArr = [];
                if ($additional_key) {
                    if ($additional_key === 'default' && is_array($value)) {
                        array_walk($value, function ($val, $key) use (&$flattenedArr) {
                            $flattenedArr[] = html_entity_decode($val);
                        });
                        $prepared_data[$index]['default'] = SysClass::ee_cleanArray($flattenedArr);
                    } elseif ($additional_key === 'multiple' && $value === 'on') {
                        $prepared_data[$index]['multiple'] = 1;
                    } elseif ($additional_key === 'required' && $value === 'on') {
                        $prepared_data[$index]['required'] = 1;
                    } else {
                        if (is_array($value)) {
                            array_walk($value, function ($val, $key) use (&$flattenedArr) {
                                $flattenedArr[] = html_entity_decode($val);
                            });
                            $value = $flattenedArr;
                        } else {
                            $value = html_entity_decode($value);
                        }
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
        $dataTable = [
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
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $dataTable['columns']);
            $features_array = $this->models['m_properties']->getPropertySetsData($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $features_array = $this->models['m_properties']->getPropertySetsData(false, false, false, 25);
        }

        foreach ($features_array['data'] as $item) {
            $dataTable['rows'][] = [
                'set_id' => $item['set_id'],
                'name' => $item['name'],
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'updated_at' => $item['updated_at'] ? date('d.m.Y', strtotime($item['updated_at'])) : '',
                'actions' => '<a href="/admin/edit_property_set/id/' . $item['set_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . '<a href="/admin/property_set_delete/id/' . $item['set_id'] . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>'
            ];
        }
        $dataTable['total_rows'] = $features_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('properties_table', $dataTable, 'get_properties_property_sets_table', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('properties_table', $dataTable, 'get_properties_property_sets_table', $filters);
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
                $setId = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $setId = 0; 
            }
            $newEntity = empty($setId);
            $isExistCategoryTypeWithSet = $this->models['m_properties']->isExistCategoryTypeWithSet($setId);
            // Обработка основных полей
            if (isset($postData['name']) && $postData['name']) {
                if ($isExistCategoryTypeWithSet) {
                    // unset($postData['selected_properties']); Можно не обновлять если пытаемся изменить уже используемые наборы в категориях
                }                
                $new_id = $this->models['m_properties']->updatePropertySetData($postData);
                if (is_object($new_id) && $new_id instanceof ErrorLogger) {
                    $errorMessage = $result->result['error_message'] ?? 'Ошибка сохранения набора';
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $errorMessage, 'status' => 'danger']);
                    $new_id = 0;
                } else {
                    if (!$new_id) {
                        ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                    } else {
                        $setId = $new_id;
                        ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.success'], 'status' => 'info']);
                    }
                }                
            }
            $postData['selected_properties'] ??= [];
            if (!empty($postData['selected_properties'])) {
                // Обработка добавления свойств
                $propertySetData = $this->models['m_properties']->getPropertySetData($setId);
                $oldPropertyIds = [];
                if (isset($propertySetData['properties']) && is_array($propertySetData['properties'])) {
                    $oldPropertyIds = array_keys($propertySetData['properties']);
                }
                $newPropertyIds = $postData['selected_properties'];
                $propertiesToAdd    = array_diff($newPropertyIds, $oldPropertyIds);
                $propertiesToDelete = array_diff($oldPropertyIds, $newPropertyIds);            
                if (!empty($propertiesToDelete)) {
                    SysClass::pre([$setId, $propertiesToDelete]);
                    $this->models['m_properties']->deletePropertiesFromSet($setId, $propertiesToDelete);
                }
                if (!empty($propertiesToAdd)) {
                    $this->models['m_properties']->addPropertiesToSet($setId, $propertiesToAdd);
                }
                if (!empty($propertiesToAdd) || !empty($propertiesToDelete)) {
                    \classes\system\Hook::run('afterUpdatePropertySetComposition', $setId, $propertiesToAdd, $propertiesToDelete);
                }
            }
            if (isset($postData['name'])) {
                $this->processPostParams($postData, $newEntity, $setId);
            }            
        } else { // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        $propertySetData = (int) $setId ? $this->models['m_properties']->getPropertySetData($setId) : $default_data;
        $propertySetData ??= $default_data;        
        /* view */
        $this->getStandardViews();
        $this->view->set('property_set_data', $propertySetData);
        $this->view->set('isExistCategoryTypeWithSet', $isExistCategoryTypeWithSet);
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
            $res = $this->models['m_properties']->propertySetDelete($set_id);
            if (count($res)) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Ошибка удаления набора id=' . $set_id . '<br/>' . $res['error'], 'status' => 'danger']);
            } else {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Удалено!', 'status' => 'info']);
            }
        } else {
            ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Нет обязательного параметра id', 'status' => 'danger']);
        }
        SysClass::handleRedirect(200, '/admin/properties_sets');
    }

}
