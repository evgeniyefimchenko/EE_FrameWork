<?php

namespace app\admin;

use classes\system\SysClass;
use classes\system\Plugins;
use classes\helpers\ClassNotifications;

/**
 * Функции работы с категориями
 */
trait CategoriesTrait {

    /**
     * Список категорий
     */
    public function categories() {
        $this->access = [1, 2];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin/categories');
        }
        /* view */
        $this->getStandardViews();
        $categories_table = $this->get_categories_data_table();
        $this->view->set('categories_table', $categories_table);
        $this->view->set('body_view', $this->view->read('v_categories'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_categories.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - categories';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - categories';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Добавить или редактировать категорию
     */
    public function category_edit($params) {
        $this->access = [1, 2];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin/categories');
            exit();
        }
        $new_element = false;
        $default_data = [
            'category_id' => 0,
            'type_id' => 0,
            'title' => '',
            'description' => '',
            'short_description' => '',
            'parent_id' => 0,
            'status' => '',
            'created_at' => '',
            'updated_at' => '',
            'parent_title' => '',
            'type_name' => '',
            'entity_count' => '',
            'category_path' => '',
            'category_path_text' => '',
        ];
        /* model */
        $this->loadModel('m_categories_types');
        $this->loadModel('m_categories', ['m_categories_types' => $this->models['m_categories_types']]);        
        $post_data = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $key_id = array_search('id', $params);
            if ($key_id !== false && isset($params[$key_id + 1])) {
                $id = filter_var($params[$key_id + 1], FILTER_VALIDATE_INT);
            } else {
                $id = 0;
            }
            // Сохранение основных данных
            if (isset($post_data['title']) && $post_data['title']) {
                if (!$new_id = $this->models['m_categories']->updateCategoryData($post_data)) {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                } else {
                    $id = $new_id;
                }
            }
            // Сохранение свойств для категории
            if (isset($post_data['property_data']) && is_array($post_data['property_data']) && isset($post_data['property_data_changed']) && $post_data['property_data_changed'] != 0) {
                $this->processPropertyData($post_data['property_data']);
            }
            $get_category_data = ((int) $id ? $this->models['m_categories']->getCategoryData($id) : null) ?: $default_data;
        } else { // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/category_edit/id/');
        }
        $categories_tree = $this->models['m_categories']->getCategoriesTree($id);
        $full_categories_tree = $this->models['m_categories']->getCategoriesTree();
        $get_category_entities = $this->models['m_categories']->getCategoryEntities($id);
        $get_categories_type_sets = $this->models['m_categories_types']->getCategoriesTypeSetsData($get_category_data['type_id']);
        $getAllTypes = [];
        if (isset($get_category_data['parent_id']) && $get_category_data['parent_id']) {
            // Есть родитель, можно выбрать только его тип или подчинённый
            $parent_type_id = $this->models['m_categories']->getCategoryTypeId($get_category_data['parent_id']);
            $getAllTypes = $this->models['m_categories_types']->getAllTypes(false, false, $parent_type_id);
        } elseif (isset($get_category_data['type_id']) && $get_category_data['type_id']) {
            // Если нет родителя то можно выбрать любой тип категории
            $getAllTypes = $this->models['m_categories_types']->getAllTypes(false, false);            
        } else {
            $getAllTypes = $this->models['m_categories_types']->getAllTypes(false, false);
        }
        $get_categories_type_sets_data = [];
        if (count($get_categories_type_sets) && $get_category_data) {
            $get_categories_type_sets_data = $this->processCategoryProperties($get_categories_type_sets, $id, $get_category_data['title']);
        }
        /* view */
        $this->view->set('category_data', $get_category_data);
        $this->view->set('categories_tree', $categories_tree);
        $this->view->set('full_categories_tree', $full_categories_tree);
        $this->view->set('category_entities', $get_category_entities);
        $this->view->set('categories_type_sets_data', $get_categories_type_sets_data);
        $this->view->set('all_type', $getAllTypes);
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_edit_category'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->add_editor_to_layout();
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/func_properties.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_categories.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = $this->lang['sys.categories_edit'];
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Обрабатывает данные свойств для категорий
     * @param array $get_categories_type_sets Массив идентификаторов наборов свойств
     * @param int $category_id Идентификатор категории
     * @param array $title_category Название категории
     * @return array Возвращает массив обработанных данных свойств
     */
    public function processCategoryProperties($get_categories_type_sets, $category_id, $title_category) {
        $this->loadModel('m_properties');
        $get_categories_type_sets_data = [];
        foreach ($get_categories_type_sets as $set_id) {
            $properties_data = $this->models['m_properties']->get_property_set_data($set_id);
            foreach ($properties_data['properties'] as $k_prop => &$prop) {
                $prop['default_values'] = json_decode($prop['default_values'], true);
                $prop['property_values'] = $this->models['m_properties']->getPropertyValuesForEntity($category_id, 'category', $prop['p_id'], $set_id);
                if (!count($prop['property_values'])) {
                    $count = 0;
                    $prop['property_values']['property_id'] = $prop['p_id'];
                    $prop['property_values']['entity_id'] = $category_id;
                    $prop['property_values']['entity_type'] = 'category';
                    $prop['property_values']['value_id'] = SysClass::ee_generate_uuid();
                    $prop['property_values']['set_id'] = $properties_data['set_id'];
                    if (!isset($prop['default_values']) || !count($prop['default_values'])) {
                        SysClass::pre('Критическая ошибка: default_values пусто или не установлено! ' . var_export($prop, true));
                    }
                    foreach ($prop['default_values'] as $prop_default) {
                        $prop['property_values']['property_values'][$count] = ['type' => $prop_default['type'],
                            'value' => isset($prop_default['default']) ? $prop_default['default'] : '',
                            'label' => $prop_default['label'],
                            'multiple' => $prop_default['multiple'],
                            'required' => $prop_default['required'],
                            'title' => isset($prop_default['title']) ? $prop_default['title'] : ''];
                        $count++;
                    }
                }
                unset($prop['default_values']);
            }
            usort($properties_data['properties'], function ($a, $b) {
                return $a['sort'] <=> $b['sort'];
            });
            $get_categories_type_sets_data[$title_category][$set_id] = $properties_data;
        }
        return $get_categories_type_sets_data;
    }
    
    
    /**
     * Обрабатывает массив данных свойств и обновляет их в базе данных
     * @param array $property_data Массив данных свойств
     * @return void
     */
    public function processPropertyData(array $property_data): void {
        $arrValueProp = [];
        $this->loadModel('m_properties');
        foreach ($property_data as $itemPropKey => $itemPropValue) {
            $arrPropName = explode('_', $itemPropKey);
            $valueId = $arrPropName[0];
            $keyProp = $arrPropName[1];
            $typeProp = $arrPropName[2];
            $entityIdProp = $arrPropName[3];
            $entityTypeProp = $arrPropName[4];
            $propertyIdProp = $arrPropName[5];
            $setId = $arrPropName[6];
            $addFieldProp = isset($arrPropName[7]) ? $arrPropName[7] : null;
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
                $itemPropValue = is_array($itemPropValue) && $addFieldProp == 'value' ? implode(',', $itemPropValue) : $itemPropValue;
                $arrValueProp[$keyArr]['property_values'][$keyProp][$addFieldProp] = $itemPropValue;
            } else {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Error, not type value!', 'status' => 'danger']);
                SysClass::pre([$itemPropKey, $arrPropName]);
            }
        }
        foreach ($arrValueProp as $arrValue) {
            $res = $this->models['m_properties']->updatePropertiesTypeData($arrValue);
            if ($res === false) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Error, not write properties!', 'status' => 'danger']);
            }
        }
    }

    /**
     * AJAX
     * Получение возможного набора типов категорий
     * для отображения в карточке категории при смене родителя     
     */
    public function getTypeCategory($params = []) {
        $is_ajax = SysClass::isAjaxRequestFromSameSite();
        if ($is_ajax) {
            $this->access = [1, 2];
            if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
                SysClass::handleRedirect();
                exit();
            }
            $this->loadModel('m_categories');
            $this->loadModel('m_categories_types');
            $post_data = SysClass::ee_cleanArray($_POST);
            if (isset($post_data['parent_id']) && $post_data['parent_id'] > 0) {
                $type_id = $this->models['m_categories']->getCategoryTypeId($post_data['parent_id']);
                $get_all_types = $this->models['m_categories_types']->getAllTypes(false, false, $type_id);
            } else {
                $get_all_types = $this->models['m_categories_types']->getAllTypes(false, false);
                $post_data['parent_id'] = 0;
                $type_id = 0;
            }
            if (isset($get_all_types[0])) {
                $selected_id = $get_all_types[0]['type_id'];
            } else {
                $selected_id = null;
            }
            echo json_encode(['html' => Plugins::showTypeCategogyForSelect($get_all_types, $selected_id),
                'parent_type_id' => $type_id,
                'parent_id' => $post_data['parent_id'],
                'all_types' => $get_all_types]);
        }
        die;
    }

    /**
     * AJAX
     * Получение набора свойств категории
     * для отображения в карточке категории при смене родителя     
     */
    public function getCategoriesType($params = []) {
        $is_ajax = SysClass::isAjaxRequestFromSameSite();
        if ($is_ajax) {
            $this->access = [1, 2];
            if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
                SysClass::handleRedirect();
                exit();
            }
            $post_data = SysClass::ee_cleanArray($_POST);
            $this->loadModel('m_categories_types');
            $get_categories_type_sets = $this->models['m_categories_types']->getCategoriesTypeSetsData($post_data['type_id']);
            $category_id = $post_data['category_id'];
            $get_categories_type_sets_data = $this->processCategoryProperties($get_categories_type_sets, $category_id, $post_data['title']);
            echo json_encode(['html' => Plugins::renderCategorySetsAccordion($get_categories_type_sets_data, $category_id),
                'get_categories_type_sets' => $get_categories_type_sets, 'category_id' => $category_id]);
        }
        die;
    }

    /**
     * Удаление категории
     */
    public function category_dell($params = []) {
        $this->access = [1, 2];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* model */
        $this->loadModel('m_categories');
        if (in_array('id', $params)) {
            $key_id = array_search('id', $params);
            if ($key_id !== false && isset($params[$key_id + 1])) {
                $id = filter_var($params[$key_id + 1], FILTER_VALIDATE_INT);
            } else {
                $id = 0;
            }
            $res = $this->models['m_categories']->deleteСategory($id);
            if (isset($res['error'])) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => $res['error'], 'status' => 'danger']);
            } else {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Удалено', 'status' => 'success']);
            }
        }
        SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/categories');
    }

    /**
     * Вернёт таблицу категоий
     */
    public function get_categories_data_table() {
        $this->access = [1, 2];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_categories');
        $post_data = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'category_id',
                    'title' => 'ID',
                    'sorted' => true,
                    'filterable' => false,
                    'width' => 5,
                    'align' => 'center'
                ], [
                    'field' => 'title',
                    'title' => $this->lang['sys.name'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'parent_id',
                    'title' => $this->lang['sys.parent'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'type_id',
                    'title' => $this->lang['sys.type'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'children',
                    'title' => $this->lang['sys.entities'],
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
                    'filterable' => false,
                    'width' => 10,
                    'align' => 'center'
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
                'label' => $this->lang['sys.parent']
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
        $this->loadModel('m_categories_types');
        foreach ($this->models['m_categories_types']->getAllTypes() as $item) {
            $filters['type_id']['options'][] = ['value' => $item['type_id'], 'label' => $item['name']];
        }
        if ($post_data && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_show_table_prepare_params($post_data, $data_table['columns']);
            $category_array = $this->models['m_categories']->get_categories_data($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $category_array = $this->models['m_categories']->get_categories_data(false, false, false, 25);
        }
        foreach ($category_array['data'] as $item) {
            $data_table['rows'][] = [
                'category_id' => $item['category_id'],
                'title' => $item['title'],
                'parent_id' => $item['parent_title'] ? $item['parent_title'] : 'Без категории',
                'type_id' => $item['type_name'],
                'children' => $item['entity_count'],
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'updated_at' => $item['updated_at'] ? date('d.m.Y', strtotime($item['updated_at'])) : '',
                'actions' => '<a href="/admin/category_edit/id/' . $item['category_id'] . '"'
                . 'class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . '<a href="/admin/category_dell/id/' . $item['category_id'] . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>'
            ];
        }
        $data_table['total_rows'] = $category_array['total_count'];
        if ($post_data) {
            echo Plugins::ee_show_table('category_table', $data_table, 'get_categories_data_table', $filters, $post_data["page"], $post_data["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('category_table', $data_table, 'get_categories_data_table', $filters);
        }
    }
}
