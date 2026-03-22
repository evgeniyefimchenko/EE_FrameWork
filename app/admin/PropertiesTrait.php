<?php

namespace app\admin;

use classes\system\SysClass;
use classes\system\FileSystem;
use classes\system\Constants;
use classes\system\Logger;
use classes\helpers\ClassNotifications;
use classes\system\Plugins;
use classes\system\PropertyFieldContract;

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
        $this->loadModel('m_property_lifecycle');
        $postData = SysClass::ee_cleanArray($_POST);
        $isLifecyclePreview = !empty($postData['lifecycle_preview']);
        $previewSelectedProperties = [];
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $typeId = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
               $typeId = 0; 
            }
            $newEntity = empty($typeId);
            $usedByProperties = $this->models['m_properties']->isExistPropertiesWithType($typeId);
            $originalTypeData = (int) $typeId ? $this->models['m_properties']->getTypePropertyData($typeId) : $default_data;
            $isLifecyclePreview = !empty($postData['lifecycle_preview']);
            $previewTypeData = null;
            if (isset($postData['name']) && $postData['name']) {
                if (!is_array($postData['fields']) || !count($postData['fields'])) {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Заполните хотя бы одно поле типа!', 'status' => 'danger']);
                } else {
                    $encodedFields = $this->encodePropertyTypeFields(
                        $postData['fields'],
                        is_array($postData['field_uid'] ?? null) ? $postData['field_uid'] : []
                    );
                    $previewTypeData = array_merge($originalTypeData ?: $default_data, $postData, [
                        'fields' => $this->normalizePropertyTypeFields($encodedFields),
                    ]);

                    $saveSucceeded = false;
                    if ($isLifecyclePreview) {
                        if ($newEntity) {
                            ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Предварительный расчёт доступен только для уже сохранённого типа свойства.', 'status' => 'warning']);
                        } else {
                            $lifecycleResult = $this->models['m_property_lifecycle']->dispatchPropertyTypeRebuild(
                                $typeId,
                                $originalTypeData ?: $default_data,
                                array_merge($previewTypeData, ['fields' => $encodedFields]),
                                ['dry_run' => true, 'requested_by' => $this->logged_in]
                            );
                            $this->pushLifecycleNotification('Тип свойства: предварительный расчёт', $lifecycleResult);
                        }
                    } else {
                        $postData['fields'] = $encodedFields;
                        $saveResult = $this->notifyOperationResult(
                            $this->models['m_properties']->updatePropertyTypeData($postData),
                            [
                                'success_message' => $newEntity ? ($this->lang['sys.success'] ?? 'Сохранено') : ($this->lang['sys.saved'] ?? 'Сохранено'),
                                'default_error_message' => $this->lang['sys.db_registration_error'] ?? 'Ошибка сохранения',
                                'success_status' => 'info',
                            ]
                        );
                        if ($saveResult->isSuccess()) {
                            $typeId = $saveResult->getId();
                            $saveSucceeded = true;
                            if (!$newEntity) {
                                $savedTypeData = $this->models['m_properties']->getTypePropertyData($typeId);
                                $lifecycleResult = $this->models['m_property_lifecycle']->dispatchPropertyTypeRebuild(
                                    $typeId,
                                    $originalTypeData ?: $default_data,
                                    $savedTypeData ?: [],
                                    ['requested_by' => $this->logged_in]
                                );
                                $this->pushLifecycleNotification('Тип свойства пересчитан', $lifecycleResult);
                            }
                        }
                    }
                }
            }
            
            if (isset($postData['name']) && !$isLifecyclePreview && !empty($saveSucceeded)) {
                $this->processPostParams($postData, $newEntity, $typeId);
            }
            $propertyTypeData = $previewTypeData ?: ((int) $typeId ? $this->models['m_properties']->getTypePropertyData($typeId) : $default_data);
            $propertyTypeData = !$propertyTypeData ? $default_data : $propertyTypeData;
            $propertyTypeData['fields'] = $this->normalizePropertyTypeFields($propertyTypeData['fields'] ?? []);
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
        $this->view->set('propertyTypeImpact', $this->models['m_property_lifecycle']->getPropertyTypeImpact((int) ($propertyTypeData['type_id'] ?? 0)));
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
        $this->loadModel('m_property_lifecycle');
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
            $originalPropertyData = !empty($propertyId) ? $this->models['m_properties']->getPropertyData($propertyId) : $defaultData;
            $isLifecyclePreview = !empty($postData['lifecycle_preview']);
            $previewPropertyData = null;
            if (isset($postData['name']) && $postData['name']) {
                if (!$isLifecyclePreview) {
                    $this->saveFileProperty($postData);
                }
                $postData['default_values'] = isset($postData['property_data'])
                    ? $this->prepareDefaultValuesProperty(
                        $postData['property_data'],
                        $propertyId,
                        (int) ($postData['type_id'] ?? 0),
                        [
                            'name' => (string) ($postData['name'] ?? ''),
                            'is_multiple' => !empty($postData['is_multiple']) ? 1 : 0,
                            'is_required' => !empty($postData['is_required']) ? 1 : 0,
                        ]
                    )
                    : [];
                $previewPropertyData = array_merge($originalPropertyData ?: $defaultData, $postData);
                if (!empty($previewPropertyData['type_id'])) {
                    $selectedTypeData = $this->models['m_properties']->getTypePropertyData((int) $previewPropertyData['type_id']);
                    if (!empty($selectedTypeData['fields'])) {
                        $previewPropertyData['fields'] = $selectedTypeData['fields'];
                    }
                }
                $saveSucceeded = false;
                if ($isLifecyclePreview) {
                    if ($newEntity) {
                        ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Предварительный расчёт доступен только для уже сохранённого свойства.', 'status' => 'warning']);
                    } else {
                        $lifecycleResult = $this->models['m_property_lifecycle']->dispatchPropertyRebuild(
                            $propertyId,
                            $originalPropertyData ?: $defaultData,
                            $previewPropertyData,
                            ['dry_run' => true, 'requested_by' => $this->logged_in]
                        );
                        $this->pushLifecycleNotification('Свойство: предварительный расчёт', $lifecycleResult);
                    }
                } else {
                    $saveResult = $this->notifyOperationResult(
                        $this->models['m_properties']->updatePropertyData($postData),
                        [
                            'success_message' => $newEntity ? ($this->lang['sys.success'] ?? 'Сохранено') : ($this->lang['sys.saved'] ?? 'Сохранено'),
                            'default_error_message' => $this->lang['sys.db_registration_error'] ?? 'Ошибка сохранения',
                            'success_status' => 'info',
                        ]
                    );
                    if ($saveResult->isSuccess()) {
                        $propertyId = $saveResult->getId();
                        $saveSucceeded = true;
                        if (!$newEntity) {
                            $savedPropertyData = $this->models['m_properties']->getPropertyData($propertyId);
                            $lifecycleResult = $this->models['m_property_lifecycle']->dispatchPropertyRebuild(
                                $propertyId,
                                $originalPropertyData ?: $defaultData,
                                $savedPropertyData ?: [],
                                ['requested_by' => $this->logged_in]
                            );
                            $this->pushLifecycleNotification('Свойство пересчитано', $lifecycleResult);
                        }
                    }
                }
            }            
            if (isset($postData['name']) && !$isLifecyclePreview && !empty($saveSucceeded)) {
                $this->processPostParams($postData, $newEntity, $propertyId);
            }
            $getPropertyData = $previewPropertyData ?: (!empty($propertyId) ? $this->getPropertyData($propertyId) : $defaultData);
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
        $this->view->set('propertyImpact', $this->models['m_property_lifecycle']->getPropertyImpact((int) ($getPropertyData['property_id'] ?? 0)));
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
        $dataFiles = !empty($postData['ee_dataFiles']) ? $postData['ee_dataFiles'] : false;
        $changed = !empty($postData['property_data_changed']) ? $postData['property_data_changed'] : false;
        if (!isset($postData['property_data']) || !is_array($postData['property_data'])) {
            $postData['property_data'] = [];
        }
        $groupedDataFiles = [];
        $touchedProperties = [];

        if (is_array($dataFiles)) {
            foreach ($dataFiles as $dataFileJson) {
                $dataFileJson = htmlspecialchars_decode((string) $dataFileJson);
                $dataFile = is_string($dataFileJson) ? json_decode($dataFileJson, true) : $dataFileJson;
                if (!is_array($dataFile)) {
                    continue;
                }

                $propertyName = trim((string) ($dataFile['property_name'] ?? ''));
                if ($propertyName === '') {
                    continue;
                }

                $groupedDataFiles[$propertyName][] = $dataFile;
                $touchedProperties[$propertyName] = $this->resolveFileFieldConfigByPropertyName($propertyName);
            }
        }

        $uploadedFiles = $this->collectUploadedPropertyFiles();

        foreach ($groupedDataFiles as $propertyName => $propertyItems) {
            $fieldConfig = $touchedProperties[$propertyName] ?? ['field_type' => null, 'multiple' => false];
            $fieldType = $fieldConfig['field_type'] ?? null;
            $isMultiple = !empty($fieldConfig['multiple']);
            $retainedReferences = [];

            foreach ($propertyItems as $dataFile) {
                $uniqueID = trim((string) ($dataFile['unique_id'] ?? ''));
                $isDeleteRequested = !empty($dataFile['update']) && !empty($dataFile['delete']);

                if ($uniqueID !== '' && ctype_digit($uniqueID) && (int) $uniqueID > 0) {
                    $fileId = (int) $uniqueID;
                    if ($isDeleteRequested) {
                        FileSystem::deleteFileData($fileId);
                        $changed = true;
                        continue;
                    }

                    if (!empty($dataFile['update'])) {
                        $updateData = [];
                        if (!empty($dataFile['original_name'])) {
                            $updateData['original_name'] = trim((string) $dataFile['original_name']);
                        }
                        if (!empty($dataFile['transformations']) && $fieldType === 'image') {
                            $existingFileData = FileSystem::getFileData($fileId, false);
                            if (!empty($existingFileData['file_path'])) {
                                FileSystem::applyImageTransformations((string) $existingFileData['file_path'], $dataFile['transformations']);
                                FileSystem::refreshFileDataFromDisk($fileId);
                                $changed = true;
                            }
                        } elseif (!empty($dataFile['transformations']) && $fieldType !== 'image') {
                            $message = 'Трансформации разрешены только для image-полей: ' . $propertyName;
                            Logger::warning('file', $message, ['property_name' => $propertyName, 'field_type' => $fieldType], [
                                'initiator' => __FUNCTION__,
                                'details' => $message,
                            ]);
                            ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'warning']);
                        }
                        if (!empty($updateData)) {
                            FileSystem::updateFileData($fileId, $updateData);
                            $changed = true;
                        }
                    }

                    $retainedReferences[(string) $fileId] = (string) $fileId;
                    continue;
                }

                $legacyValue = trim((string) ($dataFile['legacy_value'] ?? ''));
                if ($legacyValue !== '') {
                    if ($isDeleteRequested) {
                        $changed = true;
                        continue;
                    }
                    $retainedReferences[$legacyValue] = $legacyValue;
                    continue;
                }

                if (!isset($uploadedFiles[$propertyName]) || !is_array($uploadedFiles[$propertyName])) {
                    $uploadedFiles[$propertyName] = [];
                }
                $matchedFile = $this->consumeUploadedPropertyFile($uploadedFiles[$propertyName], (string) ($dataFile['file_name'] ?? ''));
                if (!$matchedFile) {
                    continue;
                }

                if (($matchedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $message = FileSystem::getErrorDescriptionByUploadCode((int) $matchedFile['error']);
                    Logger::error('file', $message, ['matched_file' => $matchedFile], [
                        'initiator' => __FUNCTION__,
                        'details' => $message,
                    ]);
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $message, 'status' => 'danger']);
                    continue;
                }

                if (!empty($dataFile['transformations']) && $fieldType === 'image') {
                    FileSystem::applyImageTransformations((string) $matchedFile['tmp_name'], $dataFile['transformations']);
                } elseif (!empty($dataFile['transformations']) && $fieldType !== 'image') {
                    $message = 'Трансформации разрешены только для image-полей: ' . $propertyName;
                    Logger::warning('file', $message, ['property_name' => $propertyName, 'field_type' => $fieldType], [
                        'initiator' => __FUNCTION__,
                        'details' => $message,
                    ]);
                    ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'warning']);
                }

                $fileData = FileSystem::safeMoveUploadedFile($matchedFile, FileSystem::getUploadPolicyForFieldType($fieldType));
                if (!$fileData) {
                    $message = 'Файл не сохранён ' . ($matchedFile['name'] ?? '');
                    Logger::error('file', $message, ['matched_file' => $matchedFile], [
                        'initiator' => __FUNCTION__,
                        'details' => $message,
                    ]);
                    ClassNotifications::addNotificationUser(SysClass::getCurrentUserId(), ['text' => $message, 'status' => 'danger']);
                    continue;
                }

                $fileData['original_name'] = !empty($dataFile['original_name'])
                    ? trim((string) $dataFile['original_name'])
                    : trim((string) ($matchedFile['name'] ?? ''));
                $fileData['user_id'] = SysClass::getCurrentUserId();

                $newFileId = FileSystem::saveFileInfo($fileData);
                if ($newFileId) {
                    $changed = true;
                    $retainedReferences[(string) $newFileId] = (string) $newFileId;
                }
            }

            $references = array_values($retainedReferences);
            $postData['property_data'][$propertyName] = $isMultiple
                ? $references
                : (($references === []) ? '' : (string) reset($references));
        }

        foreach ($touchedProperties as $propertyName => $fieldConfig) {
            if (!array_key_exists($propertyName, $postData['property_data'])) {
                $postData['property_data'][$propertyName] = !empty($fieldConfig['multiple']) ? [] : '';
            }
        }

        $postData['property_data_changed'] = $changed;
    }

    private function resolveFileFieldTypeByPropertyName(string $propertyName): ?string {
        return $this->resolveFileFieldConfigByPropertyName($propertyName)['field_type'] ?? null;
    }

    private function resolveFileFieldConfigByPropertyName(string $propertyName): array {
        $parts = array_values(array_filter(explode('_', strtolower(trim($propertyName))), static function ($part) {
            return $part !== '';
        }));
        $fieldIndex = isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : 0;
        $propertyId = isset($parts[5]) && is_numeric($parts[5]) ? (int) $parts[5] : 0;
        $fieldType = null;
        foreach ($parts as $part) {
            if (in_array($part, ['file', 'image'], true)) {
                $fieldType = $part;
                break;
            }
        }

        if ($propertyId <= 0) {
            return ['field_type' => $fieldType, 'multiple' => false];
        }

        $defaultValues = \classes\plugins\SafeMySQL::gi()->getOne(
            'SELECT default_values FROM ?n WHERE property_id = ?i LIMIT 1',
            Constants::PROPERTIES_TABLE,
            $propertyId
        );
        $fields = PropertyFieldContract::decodeFieldList($defaultValues);
        $field = $fields[$fieldIndex] ?? ($fields[0] ?? []);
        $resolvedType = strtolower(trim((string) ($field['type'] ?? $fieldType ?? '')));

        return [
            'field_type' => $resolvedType !== '' ? $resolvedType : $fieldType,
            'multiple' => !empty($field['multiple']),
        ];
    }

    private function collectUploadedPropertyFiles(): array {
        $grouped = [];
        if (!isset($_FILES['property_data']['name']) || !is_array($_FILES['property_data']['name'])) {
            return $grouped;
        }

        foreach ($_FILES['property_data']['name'] as $propertyName => $names) {
            if (!is_array($names)) {
                continue;
            }
            $count = count($names);
            for ($i = 0; $i < $count; $i++) {
                $grouped[$propertyName][] = [
                    'name' => $_FILES['property_data']['name'][$propertyName][$i] ?? '',
                    'type' => $_FILES['property_data']['type'][$propertyName][$i] ?? '',
                    'tmp_name' => $_FILES['property_data']['tmp_name'][$propertyName][$i] ?? '',
                    'error' => $_FILES['property_data']['error'][$propertyName][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['property_data']['size'][$propertyName][$i] ?? 0,
                ];
            }
        }

        return $grouped;
    }

    private function consumeUploadedPropertyFile(array &$files, string $expectedName): ?array {
        if ($files === []) {
            return null;
        }

        foreach ($files as $index => $file) {
            if ($expectedName !== '' && (string) ($file['name'] ?? '') !== $expectedName) {
                continue;
            }
            unset($files[$index]);
            return $file;
        }

        $firstIndex = array_key_first($files);
        if ($firstIndex === null) {
            return null;
        }

        $file = $files[$firstIndex];
        unset($files[$firstIndex]);
        return $file;
    }

    /**
     * Обрабатывает массив данных свойств и обновляет их в базе данных
     * @param array $propertyData Массив данных свойств
     * @return void
     */
    public function processPropertyData(array $propertyData, ?string $languageCode = null): void {        
        $arrValueProp = [];
        $this->loadModel('m_properties');
        $languageCode = strtoupper(trim((string)($languageCode ?: $this->getAdminUiLanguageCode())));
        if ($languageCode === '') {
            $languageCode = strtoupper((string) ENV_DEF_LANG);
        }
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
                $arrValueProp[$keyArr]['property_values'][$keyProp][$addFieldProp] = $itemPropValue;
            } else {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Error, not type value!', 'status' => 'danger']);
                SysClass::pre([$itemPropKey, $arrPropName]);
            }
        }
        foreach ($arrValueProp as $arrValue) {
            $res = $this->normalizeOperationResult(
                $this->models['m_properties']->updatePropertiesValueEntities($arrValue, $languageCode),
                [
                    'default_error_message' => 'Error, not write properties!',
                    'failure_code' => 'property_values_save_failed',
                ]
            );
            if ($res->isFailure()) {
                $message = $res->getMessage('Error, not write properties!');
                Logger::error('property_values', $message, ['property_payload' => $arrValue], [
                    'initiator' => __FUNCTION__,
                    'details' => $message,
                ]);
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
    public function formattingEntityProperties(array $setIds, int $entityId, string $typeEntity, string $title = '', ?string $languageCode = null): array {
        $this->loadModel('m_properties');
        $languageCode = strtoupper(trim((string)($languageCode ?: $this->getAdminUiLanguageCode())));
        if ($languageCode === '') {
            $languageCode = strtoupper((string) ENV_DEF_LANG);
        }
        $result = [];
        foreach ($setIds as $setId) {
            $hasNewPropertyValues = false;
            $propertiesData = $this->models['m_properties']->getPropertySetData($setId, $languageCode);
            foreach ($propertiesData['properties'] as $k_prop => &$prop) {
                if ($prop['property_entity_type'] != $typeEntity && $prop['property_entity_type'] != 'all') continue;
                $defaultValues = [];
                if (!empty($prop['default_values'])) {
                    $decodedDefaultValues = json_decode((string)$prop['default_values'], true);
                    if (is_array($decodedDefaultValues)) {
                        $defaultValues = $decodedDefaultValues;
                    }
                }
                $prop = array_merge($prop, $this->models['m_properties']->getPropertyValuesForEntity($entityId, $typeEntity, $prop['property_id'], $setId, $languageCode));                
                if (empty($prop['fields'])) { // Возникает при создании новой сущности
                    $count = 0;
                    $prop['entity_id'] = $entityId;
                    $prop['entity_type'] = $typeEntity;
                    $prop['set_id'] = $propertiesData['set_id'];
                    $prop['value_id'] = SysClass::ee_generate_uuid();
                    if (empty($defaultValues)) {
                        // Без дефолтной структуры свойство все равно должно отображаться в UI.
                        // Создаем безопасный минимальный шаблон поля.
                        $defaultValues = [[
                            'type' => 'text',
                            'label' => (string)($prop['name'] ?? ''),
                            'title' => '',
                            'default' => '',
                            'multiple' => !empty($prop['is_multiple']) ? 1 : 0,
                            'required' => !empty($prop['is_required']) ? 1 : 0,
                        ]];
                    }
                    foreach ($defaultValues as $prop_default) {
                        $prop['fields'][$count] = [
                            'uid' => isset($prop_default['uid']) && is_scalar($prop_default['uid']) && trim((string) $prop_default['uid']) !== ''
                                ? (string) $prop_default['uid']
                                : 'legacy_' . $count,
                            'type' => $prop_default['type'] ?? 'text',
                            'value' => isset($prop_default['default']) ? $prop_default['default'] : '',
                            'label' => isset($prop_default['label']) ? $prop_default['label'] : (string)($prop['name'] ?? ''),
                            'multiple' => isset($prop_default['multiple']) ? (int)$prop_default['multiple'] : (!empty($prop['is_multiple']) ? 1 : 0),
                            'required' => isset($prop_default['required']) ? (int)$prop_default['required'] : (!empty($prop['is_required']) ? 1 : 0),
                            'title' => isset($prop_default['title']) ? $prop_default['title'] : ''
                        ];
                        $count++;
                    }                    
                    $hasNewPropertyValues = true;
                } else {
                   $defaultValues = []; 
                }
            }
            usort($propertiesData['properties'], function ($a, $b) {
                return $a['sort'] <=> $b['sort'];
            });
            if ($hasNewPropertyValues) { // Записываем значения свойств при создании
                foreach ($propertiesData['properties'] as &$arrValue) {
                    if (empty($arrValue['fields'])) {
                        continue;
                    }
                    unset($arrValue['value_id']);
                    $saveResult = $this->normalizeOperationResult(
                        $this->models['m_properties']->updatePropertiesValueEntities($arrValue, $languageCode),
                        [
                            'default_error_message' => 'Не удалось создать значение свойства по умолчанию',
                            'failure_code' => 'property_values_seed_failed',
                        ]
                    );
                    if ($saveResult->isSuccess()) {
                        $arrValue['value_id'] = $saveResult->getId(['value_id', 'id']);
                    }
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
    private function prepareDefaultValuesProperty(array $propertyData, int $propertyId, int $typeId = 0, array $propertyMeta = []): array {
        $prepared_data = [];
        $existingDefaults = [];
        $typeFields = [];

        if (!empty($propertyId) && isset($this->models['m_properties'])) {
            $existingPropertyData = $this->models['m_properties']->getPropertyData($propertyId);
            if (!empty($existingPropertyData['default_values'])) {
                $existingDefaults = $existingPropertyData['default_values'];
            }
            if ($typeId <= 0 && !empty($existingPropertyData['type_id'])) {
                $typeId = (int) $existingPropertyData['type_id'];
            }
        }

        if ($typeId > 0 && isset($this->models['m_properties'])) {
            $typeData = $this->models['m_properties']->getTypePropertyData($typeId);
            $typeFields = $typeData['fields'] ?? [];
        }

        foreach ($propertyData as $key => $value) {
            if (preg_match('/^([a-z\-]+)_([0-9]+)(?:_(.+))?$/', $key, $matches)) {
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
                $flattenedArr = [];
                if ($additional_key) {
                    if (($additional_key === 'multiple' || $additional_key === 'required') && $value === 'on') {
                        $prepared_data[$index][$additional_key] = 1;
                    } elseif ($additional_key === 'default' && is_array($value)) {
                        array_walk($value, function ($val, $key) use (&$flattenedArr) {
                            $flattenedArr[] = html_entity_decode($val);
                        });
                        $prepared_data[$index]['default'] = SysClass::ee_cleanArray($flattenedArr);
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
        return PropertyFieldContract::normalizeDefaultFieldsForStorage(
            SysClass::ee_removeEmptyValuesToArray($prepared_data),
            $typeFields,
            $propertyMeta,
            $existingDefaults
        );
    }

    private function normalizePropertyTypeFields(mixed $fields): array {
        return PropertyFieldContract::normalizeTypeFields($fields);
    }

    private function encodePropertyTypeFields(array $fieldTypes, array $fieldUids = []): string {
        $normalizedFields = [];
        foreach (array_values($fieldTypes) as $index => $fieldType) {
            $fieldType = strtolower(trim((string) $fieldType));
            if ($fieldType === '' || !PropertyFieldContract::isSupportedFieldType($fieldType)) {
                continue;
            }
            $fieldUid = trim((string) ($fieldUids[$index] ?? ''));
            if ($fieldUid === '') {
                $fieldUid = $this->generatePropertyTypeFieldUid();
            }
            $normalizedFields[] = [
                'uid' => $fieldUid,
                'type' => $fieldType,
            ];
        }

        return json_encode(PropertyFieldContract::normalizeTypeFields($normalizedFields), JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    private function generatePropertyTypeFieldUid(): string {
        return 'pf_' . bin2hex(random_bytes(8));
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
            $this->notifyOperationResult(
                $this->models['m_properties']->typePropertiesDelete($id),
                [
                    'success_message' => $this->lang['sys.removed'] ?? 'Удалено!',
                    'default_error_message' => 'Ошибка удаления типа свойства',
                    'success_status' => 'info',
                ]
            );
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
            $this->notifyOperationResult(
                $this->models['m_properties']->propertyDelete($propertyId),
                [
                    'success_message' => $this->lang['sys.removed'] ?? 'Удалено!',
                    'default_error_message' => 'Ошибка удаления свойства',
                    'success_status' => 'info',
                ]
            );
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
        $this->loadModel('m_property_lifecycle');
        $default_data = [
            'set_id' => 0,
            'name' => '',
            'status' => 'active',
            'description' => '',
            'created_at' => false,
            'updated_at' => false
        ];
        $postData = SysClass::ee_cleanArray($_POST);
        $isLifecyclePreview = !empty($postData['lifecycle_preview']);
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
            $saveSucceeded = false;
            if (isset($postData['name']) && $postData['name']) {
                if (!$isLifecyclePreview) {
                    $saveResult = $this->notifyOperationResult(
                        $this->models['m_properties']->updatePropertySetData($postData),
                        [
                            'success_message' => $this->lang['sys.success'] ?? 'Сохранено',
                            'default_error_message' => 'Ошибка сохранения набора',
                            'success_status' => 'info',
                        ]
                    );
                    if ($saveResult->isSuccess()) {
                        $setId = $saveResult->getId();
                        $saveSucceeded = true;
                    }
                } else {
                    $saveSucceeded = true;
                }
            }
            $postData['selected_properties'] ??= [];
            if (isset($postData['name']) && ($isLifecyclePreview || $saveSucceeded)) {
                // Обработка добавления свойств
                $propertySetData = $this->models['m_properties']->getPropertySetData($setId);
                $oldPropertyIds = [];
                if (isset($propertySetData['properties']) && is_array($propertySetData['properties'])) {
                    $oldPropertyIds = array_keys($propertySetData['properties']);
                }
                $newPropertyIds = array_map('intval', $postData['selected_properties']);
                $previewSelectedProperties = $newPropertyIds;
                $propertiesToAdd = array_values(array_diff($newPropertyIds, $oldPropertyIds));
                $propertiesToDelete = array_values(array_diff($oldPropertyIds, $newPropertyIds));
                if ($isLifecyclePreview) {
                    if ($newEntity) {
                        ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Предварительный расчёт доступен только для уже сохранённого набора свойств.', 'status' => 'warning']);
                    } else {
                        $lifecycleResult = $this->models['m_property_lifecycle']->dispatchPropertySetSync(
                            $setId,
                            $propertiesToAdd,
                            $propertiesToDelete,
                            ['dry_run' => true, 'requested_by' => $this->logged_in]
                        );
                        $this->pushLifecycleNotification('Набор свойств: предварительный расчёт', $lifecycleResult);
                    }
                } else {
                    if (!empty($propertiesToDelete)) {
                        $deleteLinkResult = $this->notifyOperationResult(
                            $this->models['m_properties']->deletePropertiesFromSet($setId, $propertiesToDelete),
                            [
                                'default_error_message' => 'Не удалось удалить свойства из набора',
                                'skip_success_notification' => true,
                                'failure_code' => 'property_set_unlink_failed',
                            ]
                        );
                        if ($deleteLinkResult->isFailure()) {
                            $propertiesToDelete = [];
                        }
                    }
                    if (!empty($propertiesToAdd)) {
                        $addLinkResult = $this->notifyOperationResult(
                            $this->models['m_properties']->addPropertiesToSet($setId, $propertiesToAdd),
                            [
                                'default_error_message' => 'Не удалось добавить свойства в набор',
                                'skip_success_notification' => true,
                                'failure_code' => 'property_set_link_failed',
                            ]
                        );
                        if ($addLinkResult->isFailure()) {
                            $propertiesToAdd = [];
                        }
                    }
                    if (!empty($propertiesToAdd) || !empty($propertiesToDelete)) {
                        $lifecycleResult = $this->models['m_property_lifecycle']->dispatchPropertySetSync(
                            $setId,
                            $propertiesToAdd,
                            $propertiesToDelete,
                            ['requested_by' => $this->logged_in]
                        );
                        $this->pushLifecycleNotification('Набор свойств пересчитан', $lifecycleResult);
                    }
                }
            }
            if (isset($postData['name']) && !$isLifecyclePreview && $saveSucceeded) {
                $this->processPostParams($postData, $newEntity, $setId);
            }
        } else { // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        $propertySetData = (int) $setId ? $this->models['m_properties']->getPropertySetData($setId) : $default_data;
        $propertySetData ??= $default_data;        
        if ($isLifecyclePreview && !empty($postData['name'])) {
            $propertySetData = array_merge($propertySetData, [
                'name' => $postData['name'] ?? ($propertySetData['name'] ?? ''),
                'description' => $postData['description'] ?? ($propertySetData['description'] ?? ''),
            ]);
            $propertySetData['properties'] = [];
            foreach ($previewSelectedProperties as $previewPropertyId) {
                $propertySetData['properties'][(int) $previewPropertyId] = ['property_id' => (int) $previewPropertyId];
            }
        }
        /* view */
        $this->getStandardViews();
        $this->view->set('property_set_data', $propertySetData);
        $this->view->set('isExistCategoryTypeWithSet', $isExistCategoryTypeWithSet);
        $this->view->set('propertySetImpact', $this->models['m_property_lifecycle']->getPropertySetImpact((int) ($propertySetData['set_id'] ?? 0)));
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
            $this->notifyOperationResult(
                $this->models['m_properties']->propertySetDelete($set_id),
                [
                    'success_message' => $this->lang['sys.removed'] ?? 'Удалено!',
                    'default_error_message' => 'Ошибка удаления набора свойств',
                    'success_status' => 'info',
                ]
            );
        } else {
            ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Нет обязательного параметра id', 'status' => 'danger']);
        }
        SysClass::handleRedirect(200, '/admin/properties_sets');
    }

    public function property_lifecycle_jobs($params = []) {
        if (!$this->requireAccess([Constants::ADMIN, Constants::MODERATOR], [
            'return' => 'admin/property_lifecycle_jobs',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_property_lifecycle');
        $jobsData = $this->models['m_property_lifecycle']->getLifecycleJobsData('job_id DESC', null, 0, 100);
        $jobsSummary = $this->models['m_property_lifecycle']->getLifecycleJobsSummary();

        $this->getStandardViews();
        $this->view->set('lifecycle_jobs', $jobsData['data'] ?? []);
        $this->view->set('lifecycle_jobs_summary', $jobsSummary);
        $this->view->set('body_view', $this->view->read('v_property_lifecycle_jobs'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $lifecycleTitle = $this->lang['sys.property_lifecycle_jobs'] ?? 'Задачи жизненного цикла';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . $lifecycleTitle;
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - ' . $lifecycleTitle;
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin/property_lifecycle_jobs';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    public function run_property_lifecycle_job($params = []) {
        if (!$this->requireAccess([Constants::ADMIN, Constants::MODERATOR], [
            'return' => 'admin/property_lifecycle_jobs',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_property_lifecycle');
        $jobId = 0;
        if (in_array('id', $params, true)) {
            $keyId = array_search('id', $params, true);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $jobId = (int) $params[$keyId + 1];
            }
        }

        $result = $jobId > 0
            ? $this->models['m_property_lifecycle']->runLifecycleJob($jobId)
            : $this->models['m_property_lifecycle']->runNextQueuedLifecycleJob();

        if (!empty($result['success'])) {
            ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => 'Lifecycle job выполнен' . (!empty($result['job_id']) ? ' #' . (int) $result['job_id'] : ''),
                'status' => 'info'
            ]);
        } else {
            $message = !empty($result['message'])
                ? (string) $result['message']
                : (!empty($result['errors']) ? implode(', ', (array) $result['errors']) : 'Не удалось выполнить lifecycle job');
            ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => $message,
                'status' => 'danger'
            ]);
        }

        SysClass::handleRedirect(200, '/admin/property_lifecycle_jobs');
    }

    private function pushLifecycleNotification(string $title, array $result): void {
        $normalizedTitles = [
            'Р СћР С‘Р С— РЎРѓР Р†Р С•Р в„–РЎРѓРЎвЂљР Р† Р С—Р ВµРЎР‚Р ВµРЎРѓРЎвЂЎР С‘РЎвЂљР В°Р Р…' => 'Тип свойства пересчитан',
            'Р РЋР Р†Р С•Р в„–РЎРѓРЎвЂљР Р†Р С• Р С—Р ВµРЎР‚Р ВµРЎРѓРЎвЂЎР С‘РЎвЂљР В°Р Р…Р С•' => 'Свойство пересчитано',
        ];
        $title = $normalizedTitles[$title] ?? $title;
        $title = match ((string) ($result['scope'] ?? '')) {
            'property_type' => 'Тип свойства пересчитан',
            'property' => 'Свойство пересчитано',
            'property_set' => 'Набор свойств пересчитан',
            'category_type' => 'Тип категории пересчитан',
            default => $title,
        };

        $cleanCountersMap = [
            'properties_updated' => 'свойств обновлено',
            'property_updated' => 'свойств обновлено',
            'property_values_updated' => 'значений обновлено',
            'property_values_deleted' => 'значений удалено',
            'property_values_inserted' => 'значений создано',
            'processed_categories' => 'категорий обработано',
            'processed_pages' => 'страниц обработано',
        ];
        $parts = [];
        foreach ($cleanCountersMap as $key => $label) {
            $value = (int) ($result[$key] ?? 0);
            if ($value > 0) {
                $parts[] = $label . ': ' . $value;
            }
        }

        if (($result['status'] ?? '') === 'preview') {
            $impact = $result['impact'] ?? [];
            $impactParts = [];
            foreach ([
                'properties_count' => 'свойств',
                'values_count' => 'значений',
                'categories_count' => 'категорий',
                'pages_count' => 'страниц',
                'descendants_count' => 'дочерних типов',
            ] as $impactKey => $impactLabel) {
                $value = (int) ($impact[$impactKey] ?? 0);
                if ($value > 0) {
                    $impactParts[] = $impactLabel . ': ' . $value;
                }
            }
            $strategy = $result['strategy']['mode'] ?? 'sync';
            ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => $title . '. Dry-run: стратегия ' . $strategy . (count($impactParts) ? ' (' . implode(', ', $impactParts) . ')' : ''),
                'status' => 'warning'
            ]);
            return;
        }

        if (($result['status'] ?? '') === 'queued') {
            $jobId = (int) ($result['job_id'] ?? 0);
            $queueText = $title . '. Пересчёт поставлен в очередь';
            if ($jobId > 0) {
                $queueText .= ' (job #' . $jobId . ', <a href="/admin/property_lifecycle_jobs">журнал</a>)';
            }
            ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => $queueText,
                'status' => 'info'
            ]);
            return;
        }

        if (!empty($result['errors'])) {
            ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => $title . '. Ошибки: ' . implode(', ', $result['errors']),
                'status' => 'danger'
            ]);
            return;
        }

        ClassNotifications::addNotificationUser($this->logged_in, [
            'text' => $title . (count($parts) ? ' (' . implode(', ', $parts) . ')' : ''),
            'status' => 'info'
        ]);
        return;

        $countersMap = [
            'properties_updated' => 'свойств',
            'property_updated' => 'свойств',
            'property_values_updated' => 'значений обновлено',
            'property_values_deleted' => 'значений удалено',
            'property_values_inserted' => 'значений создано',
        ];
        $parts = [];
        foreach ($countersMap as $key => $label) {
            $value = (int) ($result[$key] ?? 0);
            if ($value > 0) {
                $parts[] = $label . ': ' . $value;
            }
        }

        if (!empty($result['errors'])) {
            ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => $title . '. Ошибки: ' . implode(', ', $result['errors']),
                'status' => 'danger'
            ]);
            return;
        }

        ClassNotifications::addNotificationUser($this->logged_in, [
            'text' => $title . (count($parts) ? ' (' . implode(', ', $parts) . ')' : ''),
            'status' => 'info'
        ]);
    }

}
