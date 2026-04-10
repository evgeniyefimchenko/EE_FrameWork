<?php

/**
 * Евгений Ефимченко, efimchenko.com
 * Управляет типами категорий в админ-панели, поддерживает CRUD-операции и AJAX-запросы.
 * /app/admin/CategoriesTypesTrait.php
 */

namespace app\admin;

use classes\system\SysClass;
use classes\system\Plugins;
use classes\system\Constants;
use classes\helpers\ClassNotifications;

/**
 * Функции работы с типами
 */
trait CategoriesTypesTrait {

    /**
     * Список категорий
     */
    public function types_categories() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin');
        }
        $this->loadModel('m_categories_types');
        /* view */
        $this->getStandardViews();
        $types_table = $this->getCategoriesTypesDataTable();
        $this->view->set('types_table', $types_table);
        $this->view->set('body_view', $this->view->read('v_categories_types'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . $this->lang['sys.type_categories'];
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - ' . $this->lang['sys.type_categories'];
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу категорий
     */
    public function getCategoriesTypesDataTable() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_categories_types');
        $postData = SysClass::ee_cleanArray($_POST);
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
                    'field' => 'parent_type_id',
                    'title' => $this->lang['sys.parent'],
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
            'parent_type_id' => [
                'type' => 'select',
                'id' => "parent_type_id",
                'value' => [],
                'label' => $this->lang['sys.parent'],
                'options' => [
                    ['value' => '', 'label' => $this->lang['sys.any'] ?? 'Any'],
                    ['value' => 0, 'label' => $this->lang['sys.without_category'] ?? 'Без родителя'],
                ],
                'multiple' => false,
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
        foreach ((array) $this->models['m_categories_types']->getAllTypes(null, true) as $typeItem) {
            $filters['parent_type_id']['options'][] = [
                'value' => (int) ($typeItem['type_id'] ?? 0),
                'label' => (string) ($typeItem['name'] ?? ''),
            ];
        }
        $selected_sorting = [];
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $users_array = $this->models['m_categories_types']->getCategoriesTypesData($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $users_array = $this->models['m_categories_types']->getCategoriesTypesData(false, false, false, 25);
        }
        foreach ($users_array['data'] as $item) {
            $data_table['rows'][] = [
                'type_id' => $item['type_id'],
                'name' => $item['name'],
                'parent_type_id' => !empty($item['parent_type_id']) ?
                '(' . $item['parent_type_id'] . ')' . $this->models['m_categories_types']->getNameCategoriesType($item['parent_type_id']) : '',
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'updated_at' => $item['updated_at'] ? date('d.m.Y', strtotime($item['updated_at'])) : '',
                'actions' => '<a href="/admin/categories_type_edit/id/' . $item['type_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip"'
                . 'data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . '<a href="' . htmlspecialchars($this->withCsrfUrl('/admin/delete_categories_type/id/' . $item['type_id']), ENT_QUOTES, 'UTF-8') . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>'
            ];
        }
        $data_table['total_rows'] = $users_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('typesTable', $data_table, 'getCategoriesTypesDataTable', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('typesTable', $data_table, 'getCategoriesTypesDataTable', $filters);
        }
    }

    /**
     * Добавить или редактировать тип категории
     */
    public function categories_type_edit($params) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $default_data = [
            'type_id' => 0,
            'parent_type_id' => NULL,
            'name' => '',
            'description' => '',
            'created_at' => false,
            'updated_at' => false
        ];
        /* model */
        $this->loadModel('m_properties');
        $this->loadModel('m_categories_types', ['m_properties' => $this->models['m_properties']]);
        $this->loadModel('m_property_lifecycle');

        $postData = SysClass::ee_cleanArray($_POST);
        $isLifecyclePreview = !empty($postData['lifecycle_preview']);
        $previewEffectiveSetIds = [];
        $previewDirectSetIds = [];
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $typeId = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $typeId = 0;
            }
            $postData['property_set'] ??= [];
            $saveSucceeded = false;
            if (isset($postData['name']) && $postData['name']) {
                if (!$isLifecyclePreview) {
                    $saveResult = $this->notifyOperationResult(
                        $this->models['m_categories_types']->updateCategoriesTypeData($postData),
                        [
                            'success_message' => $this->lang['sys.saved'] ?? 'Тип категории сохранён',
                            'default_error_message' => $this->lang['sys.db_registration_error'] ?? 'Ошибка сохранения',
                        ]
                    );
                    if ($saveResult->isSuccess()) {
                        $typeId = $saveResult->getId();
                        $saveSucceeded = true;
                    }
                } else {
                    $saveSucceeded = true;
                }
                $selectedSetIds = array_values(array_unique(array_filter(array_map('intval', $postData['property_set']), static fn(int $id): bool => $id > 0)));
                $oldSetIds = array_values(array_unique(array_filter(array_map('intval', $postData['old_property_set'] ?? []), static fn(int $id): bool => $id > 0)));
                $parentEffectiveSetIds = $this->models['m_categories_types']->getCategoriesTypeSetsData((int) ($postData['parent_type_id'] ?? 0));
                $directSelectedSetIds = array_values(array_diff($selectedSetIds, $parentEffectiveSetIds));
                $previewEffectiveSetIds = $selectedSetIds;
                $previewDirectSetIds = $directSelectedSetIds;
                sort($directSelectedSetIds);
                sort($oldSetIds);
                $parentChanged = (int) ($postData['old_parent_type_id'] ?? 0) !== (int) ($postData['parent_type_id'] ?? 0);
                $setsChanged = $oldSetIds !== $directSelectedSetIds;
                if (($isLifecyclePreview || $saveSucceeded) && ($parentChanged || $setsChanged)) {
                    if ($isLifecyclePreview) {
                        if (empty($typeId)) {
                            ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Предварительный расчёт доступен только для уже сохранённого типа категории.', 'status' => 'warning']);
                        } else {
                            $allTypeIdsPreview = $this->models['m_categories_types']->getAllTypeChildrensIds($typeId);
                            $allTypeIdsPreview[] = $typeId;
                            $currentDescendantsEffectiveSetIds = $this->models['m_categories_types']->getCategoriesTypeSetsData(array_diff($allTypeIdsPreview, [$typeId]));
                            $effectiveSetIdsPreview = array_values(array_unique(array_merge($selectedSetIds, $currentDescendantsEffectiveSetIds)));
                            $lifecycleResult = $this->models['m_property_lifecycle']->dispatchCategoryTypeSync(
                                $typeId,
                                $effectiveSetIdsPreview,
                                $allTypeIdsPreview,
                                ['dry_run' => true, 'requested_by' => $this->logged_in]
                            );
                            $impact = $lifecycleResult['impact'] ?? [];
                            $strategy = (string) ($lifecycleResult['strategy']['mode'] ?? 'sync');
                            $summary = [];
                            foreach ([
                                'categories_count' => 'категорий',
                                'pages_count' => 'страниц',
                                'descendants_count' => 'дочерних типов',
                            ] as $impactKey => $label) {
                                $value = (int) ($impact[$impactKey] ?? 0);
                                if ($value > 0) {
                                    $summary[] = $label . ': ' . $value;
                                }
                            }
                            ClassNotifications::addNotificationUser($this->logged_in, [
                                'text' => 'Предварительный расчёт выполнен. Dry-run: стратегия ' . $strategy . (count($summary) ? ' (' . implode(', ', $summary) . ')' : ''),
                                'status' => 'info'
                            ]);
                        }
                    } else {
                        $setSyncResult = $this->notifyOperationResult(
                            $this->models['m_categories_types']->updateCategoriesTypeSetsData($typeId, $directSelectedSetIds),
                            [
                                'default_error_message' => 'Не удалось обновить связи типа категории с наборами свойств',
                                'skip_success_notification' => true,
                                'failure_code' => 'category_type_set_sync_failed',
                            ]
                        );
                        if ($setSyncResult->isSuccess()) {
                            $latestLifecycleJob = $this->models['m_property_lifecycle']->getLifecycleJobsData(
                                'job_id DESC',
                                "scope = 'category_type' AND target_id = " . (int) $typeId,
                                0,
                                1
                            )['data'][0] ?? null;
                            if (!empty($latestLifecycleJob) && ($latestLifecycleJob['status'] ?? '') === 'queued') {
                                $message = 'Связи типа категории поставлены в очередь на пересчёт (job #' . (int) $latestLifecycleJob['job_id'] . ').';
                            } else {
                                $message = 'Связи типа категории и зависимые свойства пересчитаны';
                            }
                            ClassNotifications::addNotificationUser($this->logged_in, [
                                'text' => $message,
                                'status' => 'info'
                            ]);
                        }
                    }
                }
                if (!$isLifecyclePreview && $saveSucceeded && !$postData['type_id'])
                    SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/categories_type_edit/id/' . $typeId);
            }
            $newEntity = empty($typeId) ? true : false;
            if (!$isLifecyclePreview && $saveSucceeded) {
                $this->processPostParams($postData, $newEntity, $typeId);
            }
            $get_categories_types_data = (int) $typeId ? $this->models['m_categories_types']->getCategoriesTypeData($typeId) : $default_data;
            $get_categories_types_data = $get_categories_types_data ? $get_categories_types_data : $default_data;
            if ($isLifecyclePreview && isset($postData['name'])) {
                $get_categories_types_data = array_merge($get_categories_types_data, [
                    'name' => $postData['name'] ?? ($get_categories_types_data['name'] ?? ''),
                    'description' => $postData['description'] ?? ($get_categories_types_data['description'] ?? ''),
                    'parent_type_id' => (int) ($postData['parent_type_id'] ?? ($get_categories_types_data['parent_type_id'] ?? 0)),
                ]);
            }
        } else { // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/categories_type_edit/id/');
        }
        if (isset($get_categories_types_data['type_id'])) {
            $get_all_types = $this->models['m_categories_types']->getAllTypes($get_categories_types_data['type_id'], false);
        } else {
            $get_all_types = $this->models['m_categories_types']->getAllTypes(false, false);
        }
        $getCategoriesTypeSetsData = $isLifecyclePreview && !empty($previewEffectiveSetIds)
            ? $previewEffectiveSetIds
            : $this->models['m_categories_types']->getCategoriesTypeSetsData($typeId);
        $getDirectCategoriesTypeSetsData = $isLifecyclePreview
            ? $previewDirectSetIds
            : $this->models['m_categories_types']->getDirectCategoriesTypeSetsData($typeId);
        /* view */
        $this->view->set('type_data', $get_categories_types_data);
        $this->view->set('all_types', $get_all_types);
        $this->view->set('propertySetsData', $this->models['m_properties']->getPropertySetsData('set_id ASC', false, 0, (10 * 10)));
        $this->view->set('categoriesTypeSetsData', $getCategoriesTypeSetsData);
        $this->view->set('directCategoriesTypeSetsData', $getDirectCategoriesTypeSetsData);
        $this->view->set('categoryTypeImpact', $this->models['m_property_lifecycle']->getCategoryTypeImpact((int) ($get_categories_types_data['type_id'] ?? 0)));
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_edit_categories_type'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->addEditorToLayout();
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_categories_type.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . ((string) ($this->lang['sys.category_types_edit'] ?? 'Edit category types'));
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Получает родительские типы категорий по AJAX запросу
     * Обрабатывает AJAX запрос для получения родительских типов категорий и связанных наборов свойств
     * @param array $params Параметры для метода (необязательно)
     * @return void
     */
    public function getParentCategoriesType(array $params = []): void {
        $is_ajax = SysClass::isAjaxRequestFromSameSite();
        if ($is_ajax) {
            $postData = SysClass::ee_cleanArray($_POST);
            $this->access = [Constants::ADMIN, Constants::MODERATOR];
            if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
                SysClass::handleRedirect();
                exit();
            }
            /* model */
            $this->loadModel('m_properties');
            $this->loadModel('m_categories_types', ['m_properties' => $this->models['m_properties']]);
            $allSetsIds = $this->models['m_categories_types']->getCategoriesTypeSetsData((int) ($postData['type_id'] ?? 0));
            $allSetsIds = count($allSetsIds) ? array_values(array_unique(array_map('intval', $allSetsIds))) : false;
            echo json_encode(['all_sets_ids' => $allSetsIds]);
            die;
        } else {
            SysClass::handleRedirect();
            exit();
        }
    }

    /**
     * Удалит выбранный тип категории
     * @param array $params
     */
    public function delete_categories_type($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        if (!$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin/types_categories',
        ])) {
            return;
        }
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $id = 0;
            }
            $this->loadModel('m_categories_types');
            $this->notifyOperationResult(
                $this->models['m_categories_types']->deleteCategoriesType($id),
                [
                    'success_message' => $this->lang['sys.removed'] ?? 'Удалено!',
                    'default_error_message' => 'Ошибка удаления типа категории',
                    'success_status' => 'info',
                ]
            );
        } else {
            ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Нет обязательного параметра id', 'status' => 'danger']);
        }
        SysClass::handleRedirect(200, '/admin/types_categories');
    }
}
