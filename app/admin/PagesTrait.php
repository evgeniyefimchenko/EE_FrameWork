<?php

namespace app\admin;

use classes\system\SysClass;
use classes\system\Constants;
use classes\system\EntityTranslationService;
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
        $currentContentLanguageCode = ee_get_default_content_lang_code((string) ($_GET['language_code'] ?? \classes\system\Session::get('admin_pages_lang')));
        \classes\system\Session::set('admin_pages_lang', $currentContentLanguageCode);
        /* view */
        $this->getStandardViews();
        $pagesTable = $this->getPagesDataTable($currentContentLanguageCode);
        $this->view->set('pagesTable', $pagesTable);
        $this->view->set('availableContentLanguageCodes', ee_get_content_lang_codes());
        $this->view->set('currentContentLanguageCode', $currentContentLanguageCode);
        $this->view->set('defaultContentLanguageCode', ee_get_default_content_lang_code());
        $this->view->set('languageSwitchBaseUrl', '/admin/pages');
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
    public function page_edit($params = []) {
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
        $requestedLanguageCode = strtoupper(trim((string) ($_GET['language_code'] ?? '')));
        $translationSourceId = (int) ($_GET['translation_of'] ?? ($postData['translation_source_id'] ?? 0));
        $entityLanguageCode = 0;
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $entityLanguageCode = strtoupper((string) (\classes\plugins\SafeMySQL::gi()->getOne(
                    'SELECT language_code FROM ?n WHERE page_id = ?i LIMIT 1',
                    Constants::PAGES_TABLE,
                    (int) $params[$keyId + 1]
                ) ?: ''));
            }
        }
        $defaultContentLanguageCode = ee_get_default_content_lang_code((string) (\classes\system\Session::get('admin_pages_lang') ?: ''));
        $languageCode = strtoupper(trim((string)($postData['language_code'] ?? ($requestedLanguageCode ?: ($entityLanguageCode ?: $defaultContentLanguageCode)))));
        if ($languageCode === '') {
            $languageCode = $entityLanguageCode !== '' ? $entityLanguageCode : $defaultContentLanguageCode;
        }
        $languageCode = ee_get_default_content_lang_code($languageCode);
        \classes\system\Session::set('admin_pages_lang', $languageCode);
        if (!empty($postData) && $languageCode !== '') {
            $postData['language_code'] = $languageCode;
        }
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $pageId = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $pageId = 0;
            }
            $newEntity = empty($pageId);
            if ($newEntity && $translationSourceId > 0 && empty($postData)) {
                $draftPayload = $this->preparePageTranslationDraft($translationSourceId, $languageCode ?: ee_get_default_content_lang_code());
                if (!empty($draftPayload['redirect'])) {
                    SysClass::handleRedirect(200, $draftPayload['redirect']);
                    return;
                }
                if (!empty($draftPayload['warning']) && !empty($this->logged_in)) {
                    ClassNotifications::addNotificationUser($this->logged_in, [
                        'text' => $draftPayload['warning'],
                        'status' => 'warning',
                    ]);
                }
                if (!empty($draftPayload['pageData']) && is_array($draftPayload['pageData'])) {
                    $default_data = array_merge($default_data, $draftPayload['pageData']);
                }
            }
            $saveSucceeded = false;
            if (isset($postData['title']) && $postData['title']) {
                $postData['description'] = \classes\system\FileSystem::extractBase64Images($postData['description']);
                $saveResult = $this->notifyOperationResult(
                    $this->models['m_pages']->updatePageData($postData, $languageCode ?: ee_get_default_content_lang_code()),
                    [
                        'success_message' => $this->lang['sys.saved'] ?? 'Страница сохранена',
                        'default_error_message' => $this->lang['sys.db_registration_error'] ?? 'Ошибка сохранения',
                    ]
                );
                if ($saveResult->isSuccess()) {
                    $pageId = $saveResult->getId();
                    $saveSucceeded = true;
                    if ($translationSourceId > 0 && $newEntity && $pageId > 0 && $pageId !== $translationSourceId) {
                        EntityTranslationService::linkEntityToSource('page', (int) $pageId, $translationSourceId);
                        EntityTranslationService::duplicatePropertyValuesFromSource(
                            'page',
                            $translationSourceId,
                            (int) $pageId,
                            EntityTranslationService::getEntityLanguageCode('page', $translationSourceId),
                            EntityTranslationService::getEntityLanguageCode('page', (int) $pageId)
                        );
                    }
                    $this->saveFileProperty($postData);
                    if (isset($postData['property_data']) && is_array($postData['property_data']) && !empty($postData['property_data_changed'])) {
                        $this->processPropertyData($postData['property_data'], $languageCode);
                    }
                }
            }
            if ($saveSucceeded) {
                $this->processPostParams($postData, $newEntity, $pageId);
            }
            $getPageData = (int) $pageId ? $this->models['m_pages']->getPageData($pageId, $languageCode ?: ee_get_default_content_lang_code()) : $default_data;
            $getPageData = $getPageData ? $getPageData : $default_data;
        } else { // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        $getAllTypes = $this->models['m_categories_types']->getAllTypes(null, true, null, $languageCode ?: ee_get_default_content_lang_code());
        $result = array_reduce($getAllTypes, function ($carry, $item) {
            $carry[$item['type_id']] = $item;
            return $carry;
        }, []);
        $getAllTypes = $result;
        unset($result);
        $getAllCategories = $this->models['m_categories']->getCategoriesTree(null, null, true, $languageCode ?: ee_get_default_content_lang_code());
        $getAllPages = $this->models['m_pages']->getAllPages($pageId, $languageCode ?: ee_get_default_content_lang_code());
        $getAllProperties = $this->getPropertiesByCategoryId(
            (int) ($getPageData['category_id'] ?? 0),
            (int) $pageId,
            (string) ($getPageData['title'] ?? ''),
            $languageCode ?: ee_get_default_content_lang_code()
        );
        $contentLanguageCodes = ee_get_content_lang_codes();
        $translationUi = (int) $pageId > 0
            ? $this->buildPageTranslationUi((int) $pageId, $languageCode ?: ee_get_default_content_lang_code(), $contentLanguageCodes)
            : $this->buildPageTranslationDraftUi($translationSourceId, $languageCode ?: ee_get_default_content_lang_code(), $contentLanguageCodes);
        foreach (Constants::ALL_STATUS as $key => $value) {
            $allStatus[$key] = $this->lang['sys.' . $value];
        }
        /* view */
        $this->view->set('pageData', $getPageData);
        $this->view->set('allType', $getAllTypes);
        $this->view->set('allCategories', $getAllCategories);
        $this->view->set('allPages', $getAllPages);
        $this->view->set('allProperties', $getAllProperties);
        $this->view->set('allStatus', $allStatus);
        $this->view->set('contentLanguageCodes', $contentLanguageCodes);
        $this->view->set('currentLanguageCode', $languageCode ?: ee_get_default_content_lang_code());
        $this->view->set('languageSwitchBaseUrl', '/admin/page_edit/id/' . (int) $pageId);
        $this->view->set('translationUi', $translationUi);
        $this->view->set('translationSourceId', $translationSourceId);
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_edit_page'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->addEditorToLayout();
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/func_properties.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_pages.js" type="text/javascript" /></script>';
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . ((string) ($this->lang['sys.entity_edit'] ?? 'Edit entity'));
        $this->showLayout($this->parameters_layout);
    }

    private function getPropertiesByCategoryId(int $categoryId, int $pageId, string $pageTitle = '', string $languageCode = ''): array {
        $languageCode = ee_get_default_content_lang_code($languageCode);
        $categoryTypeId = $this->models['m_categories']->getCategoryTypeId($categoryId, $languageCode);
        $getCategoriesTypeSets = $this->models['m_categories_types']->getCategoriesTypeSetsData($categoryTypeId);
        $entityTitle = trim($pageTitle) !== '' ? trim($pageTitle) : ('page_' . $pageId);
        $getCategoriesTypeSetsData = $this->formattingEntityProperties($getCategoriesTypeSets, $pageId, 'page', $entityTitle, $languageCode);
        return $getCategoriesTypeSetsData;
    }

    /**
     * Удаление страницы
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
            $this->notifyOperationResult(
                $this->models['m_pages']->deletePage($id),
                [
                    'success_message' => $this->lang['sys.removed'] ?? 'Удалено!',
                    'default_error_message' => 'Ошибка удаления страницы',
                ]
            );
        }
        SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/pages');
    }

    /**
     * Быстро включает участие страницы в поиске.
     */
    public function page_search_enable(array $params = []): void {
        $this->togglePageSearchFromList($params, true);
    }

    /**
     * Быстро выключает участие страницы в поиске.
     */
    public function page_search_disable(array $params = []): void {
        $this->togglePageSearchFromList($params, false);
    }

    /**
     * Вернёт таблицу страниц
     */
    public function getPagesDataTable(array|string|null $params = null, ?string $contentLanguageCode = null) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        if (is_string($params) && $contentLanguageCode === null) {
            $contentLanguageCode = $params;
            $params = [];
        }
        $this->loadModel('m_pages');
        $this->loadModel('m_categories');
        $this->loadModel('m_categories_types', []);
        $languageCode = ee_get_default_content_lang_code((string) ($contentLanguageCode ?: \classes\system\Session::get('admin_pages_lang')));
        \classes\system\Session::set('admin_pages_lang', $languageCode);
        $all_types = $this->models['m_categories_types']->getAllTypes(null, true, null, $languageCode);
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
                    'field' => 'parent_page_id',
                    'title' => $this->lang['sys.parent'],
                    'sorted' => false,
                    'filterable' => false
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
                    'field' => 'search_state',
                    'title' => $this->lang['sys.search.title'] ?? 'Поиск',
                    'sorted' => false,
                    'filterable' => true,
                    'filter_field' => 'search_enabled',
                    'raw' => true
                ], [
                    'field' => 'language_code',
                    'title' => $this->lang['sys.language'],
                    'sorted' => 'ASC',
                    'filterable' => false,
                    'width' => 6,
                    'align' => 'center'
                ], [
                    'field' => 'translations',
                    'title' => $this->lang['sys.translations'] ?? 'Переводы',
                    'sorted' => false,
                    'filterable' => false,
                    'raw' => true
                ], [
                    'field' => 'actions',
                    'title' => $this->lang['sys.action'],
                    'sorted' => false,
                    'filterable' => false,
                    'width' => 16,
                    'align' => 'center'
                ],
            ]
        ];
        foreach ($all_types as $item) {
            $filter_types[] = ['value' => $item['type_id'], 'label' => $item['name']];
        }
        $filter_categories = [
            ['value' => '', 'label' => $this->lang['sys.any'] ?? 'Any'],
            ['value' => 0, 'label' => $this->lang['sys.without_category'] ?? 'Без категории'],
        ];
        foreach ((array) $this->models['m_categories']->getAllCategories(false, ['category_id', 'title'], $languageCode) as $categoryItem) {
            $filter_categories[] = [
                'value' => (int) ($categoryItem['category_id'] ?? 0),
                'label' => (string) ($categoryItem['title'] ?? ''),
            ];
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
                'type' => 'select',
                'id' => "category_id",
                'value' => [],
                'label' => 'Категория страницы',
                'options' => $filter_categories,
                'multiple' => false,
                'help_text' => 'Показывает страницы, привязанные к выбранной категории.'
            ],
            'type_id' => [
                'type' => 'select',
                'id' => "type_id",
                'value' => [],
                'label' => $this->lang['sys.type'],
                'options' => $filter_types,
                'multiple' => true,
                'ignore_values' => ['0', 0, ''],
                'help_text' => 'Если ничего не выбрано, показываются страницы всех типов.'
            ],
            'status' => [
                'type' => 'select',
                'id' => "status",
                'value' => [],
                'label' => $this->lang['sys.status'],
                'options' => $statuses,
                'multiple' => true,
                'help_text' => 'Можно выбрать несколько статусов сразу.'
            ],
            'search_enabled' => [
                'type' => 'select',
                'id' => 'search_enabled',
                'value' => '',
                'label' => $this->lang['sys.search.title'] ?? 'Поиск',
                'options' => [
                    ['value' => '', 'label' => $this->lang['sys.any'] ?? 'Any'],
                    ['value' => 1, 'label' => $this->lang['sys.yes'] ?? 'Да'],
                    ['value' => 0, 'label' => $this->lang['sys.no'] ?? 'Нет'],
                ],
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
        $selected_sorting = [];
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $arrPages = $this->models['m_pages']->getPagesData($params['order'], $params['where'], $params['start'], $params['limit'], $languageCode);
        } else {
            $arrPages = $this->models['m_pages']->getPagesData(false, false, false, 25, $languageCode);
        }
        $pageIds = array_values(array_filter(array_map(static fn(array $item): int => (int) ($item['page_id'] ?? 0), $arrPages['data'])));
        $translationsByPageId = EntityTranslationService::getTranslationsByEntityIds('page', $pageIds);
        $availableLanguageCodes = ee_get_content_lang_codes();
        foreach ($arrPages['data'] as $item) {
            $data_table['rows'][] = [
                'page_id' => $item['page_id'],
                'title' => $item['title'],
                'category_id' => $item['category_title'] ? $item['category_title'] : 'Без категории',
                'type_id' => $item['type_name'],
                'parent_page_id' => $this->models['m_pages']->getPageTitleById($item['parent_page_id'], $languageCode) ?? $this->lang['sys.no'],
                'status' => $statuses_text[$item['status']],
                'search_state' => $this->renderPageSearchStateCell($item),
                'language_code' => strtoupper((string) ($item['language_code'] ?? $languageCode)),
                'translations' => $this->renderEntityTranslationBadges(
                    'page',
                    (int) $item['page_id'],
                    $translationsByPageId[(int) $item['page_id']] ?? [],
                    $availableLanguageCodes
                ),
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'updated_at' => $item['updated_at'] ? date('d.m.Y', strtotime($item['updated_at'])) : '',
                'actions' => '<a href="/admin/page_edit/id/' . $item['page_id'] . '?language_code=' . rawurlencode((string) ($item['language_code'] ?? $languageCode)) . '"'
                . 'class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . (
                    !empty($item['search_enabled'])
                    ? '<a href="/admin/page_search_disable/id/' . $item['page_id'] . '?language_code=' . rawurlencode((string) ($item['language_code'] ?? $languageCode)) . '" onclick="return confirm(\'' . htmlspecialchars((string) ($this->lang['sys.search_page_disable_confirm'] ?? 'Отключить участие страницы в поиске?'), ENT_QUOTES) . '\');" '
                    . 'class="btn btn-warning me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars((string) ($this->lang['sys.search_page_disable'] ?? 'Отключить поиск страницы'), ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-magnifying-glass-minus"></i></a>'
                    : '<a href="/admin/page_search_enable/id/' . $item['page_id'] . '?language_code=' . rawurlencode((string) ($item['language_code'] ?? $languageCode)) . '" onclick="return confirm(\'' . htmlspecialchars((string) ($this->lang['sys.search_page_enable_confirm'] ?? 'Включить участие страницы в поиске?'), ENT_QUOTES) . '\');" '
                    . 'class="btn btn-success me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars((string) ($this->lang['sys.search_page_enable'] ?? 'Включить поиск страницы'), ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-magnifying-glass"></i></a>'
                )
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

    private function preparePageTranslationDraft(int $sourcePageId, string $targetLanguageCode): array {
        $targetLanguageCode = ee_get_default_content_lang_code($targetLanguageCode);
        $sourcePageRow = EntityTranslationService::getEntityRow('page', $sourcePageId);
        if (!is_array($sourcePageRow) || empty($sourcePageRow['page_id'])) {
            return [];
        }

        $sourceLanguageCode = ee_get_default_content_lang_code((string) ($sourcePageRow['language_code'] ?? ''));
        if ($targetLanguageCode === '') {
            $targetLanguageCode = $sourceLanguageCode;
        }

        $existingTargetId = EntityTranslationService::getTranslatedEntityId('page', $sourcePageId, $targetLanguageCode);
        if ($existingTargetId !== null) {
            return [
                'redirect' => '/admin/page_edit/id/' . $existingTargetId . '?language_code=' . rawurlencode($targetLanguageCode),
            ];
        }

        $categoryId = (int) ($sourcePageRow['category_id'] ?? 0);
        if ($categoryId > 0 && $sourceLanguageCode !== $targetLanguageCode) {
            $translatedCategoryId = EntityTranslationService::getTranslatedEntityId('category', $categoryId, $targetLanguageCode);
            if ($translatedCategoryId === null) {
                return [
                    'redirect' => '/admin/category_edit/id/' . $categoryId . '?language_code=' . rawurlencode($targetLanguageCode),
                ];
            }
            $categoryId = $translatedCategoryId;
        }

        $parentPageId = (int) ($sourcePageRow['parent_page_id'] ?? 0);
        $warning = '';
        if ($parentPageId > 0 && $sourceLanguageCode !== $targetLanguageCode) {
            $translatedParentId = EntityTranslationService::getTranslatedEntityId('page', $parentPageId, $targetLanguageCode);
            if ($translatedParentId !== null) {
                $parentPageId = $translatedParentId;
            } else {
                $parentPageId = 0;
                $warning = (string) ($this->lang['sys.translation_parent_page_missing'] ?? 'Родительская страница на целевом языке не найдена. Перевод будет создан без родителя.');
            }
        }

        return [
            'warning' => $warning,
            'pageData' => [
                'page_id' => 0,
                'parent_page_id' => $parentPageId ?: null,
                'category_id' => $categoryId,
                'status' => (($sourcePageRow['status'] ?? 'hidden') === 'active') ? 'hidden' : (string) ($sourcePageRow['status'] ?? 'hidden'),
                'title' => (string) ($sourcePageRow['title'] ?? ''),
                'short_description' => (string) ($sourcePageRow['short_description'] ?? ''),
                'description' => (string) ($sourcePageRow['description'] ?? ''),
                'created_at' => false,
                'updated_at' => false,
                'language_code' => $targetLanguageCode,
            ],
        ];
    }

    private function buildPageTranslationUi(int $pageId, string $currentLanguageCode, array $availableLanguageCodes): array {
        $availableLanguageCodes = ee_collect_lang_codes($availableLanguageCodes);
        $state = EntityTranslationService::getTranslationState('page', $pageId, $availableLanguageCodes);
        if ($state === []) {
            return [];
        }

        $ui = [
            'existing' => [],
            'missing' => [],
            'current_language_code' => strtoupper($currentLanguageCode),
            'group_key' => (string) ($state['group_key'] ?? ''),
            'is_draft' => false,
        ];

        foreach ($availableLanguageCodes as $availableLanguageCode) {
            $translation = $state['translations'][$availableLanguageCode] ?? null;
            if (is_array($translation)) {
                $ui['existing'][] = [
                    'language_code' => $availableLanguageCode,
                    'entity_id' => (int) ($translation['entity_id'] ?? 0),
                    'title' => (string) ($translation['title'] ?? ''),
                    'status' => (string) ($translation['status'] ?? ''),
                    'is_current' => (int) ($translation['entity_id'] ?? 0) === $pageId,
                    'edit_url' => '/admin/page_edit/id/' . (int) ($translation['entity_id'] ?? 0) . '?language_code=' . rawurlencode($availableLanguageCode),
                ];
                continue;
            }

            $ui['missing'][] = [
                'language_code' => $availableLanguageCode,
                'create_url' => '/admin/page_edit/id?translation_of=' . $pageId . '&language_code=' . rawurlencode($availableLanguageCode),
            ];
        }

        return $ui;
    }

    private function buildPageTranslationDraftUi(int $translationSourceId, string $currentLanguageCode, array $availableLanguageCodes): array {
        $availableLanguageCodes = ee_collect_lang_codes($availableLanguageCodes);
        if ($translationSourceId <= 0) {
            return [];
        }

        return [
            'existing' => [],
            'missing' => [],
            'current_language_code' => strtoupper($currentLanguageCode),
            'group_key' => (string) ((EntityTranslationService::getTranslations('page', $translationSourceId)['group_key'] ?? '')),
            'is_draft' => true,
            'source_entity_id' => $translationSourceId,
            'source_edit_url' => '/admin/page_edit/id/' . $translationSourceId,
            'available_languages' => $availableLanguageCodes,
        ];
    }

    private function renderEntityTranslationBadges(string $entityType, int $entityId, array $translationState, array $availableLanguageCodes): string {
        $availableLanguageCodes = ee_collect_lang_codes($availableLanguageCodes);
        $badges = [];
        $translations = (array) ($translationState['translations'] ?? []);

        foreach ($availableLanguageCodes as $availableLanguageCode) {
            if (isset($translations[$availableLanguageCode])) {
                $translation = $translations[$availableLanguageCode];
                $editUrl = '/admin/' . ($entityType === 'page' ? 'page_edit' : 'category_edit') . '/id/' . (int) ($translation['entity_id'] ?? 0) . '?language_code=' . rawurlencode($availableLanguageCode);
                $badges[] = '<a href="' . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . '" class="badge text-bg-primary text-decoration-none me-1">' . htmlspecialchars((string) $availableLanguageCode, ENT_QUOTES, 'UTF-8') . '</a>';
                continue;
            }

            $createUrl = '/admin/' . ($entityType === 'page' ? 'page_edit' : 'category_edit') . '/id?translation_of=' . $entityId . '&language_code=' . rawurlencode($availableLanguageCode);
            $badges[] = '<a href="' . htmlspecialchars($createUrl, ENT_QUOTES, 'UTF-8') . '" class="badge text-bg-light text-decoration-none border me-1">+' . htmlspecialchars((string) $availableLanguageCode, ENT_QUOTES, 'UTF-8') . '</a>';
        }

        return implode('', $badges);
    }

    private function togglePageSearchFromList(array $params, bool $enabled): void {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }

        $pageId = 0;
        if (in_array('id', $params, true)) {
            $keyId = array_search('id', $params, true);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $pageId = (int) filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            }
        }

        $this->loadModel('m_pages');
        $languageCode = ee_get_default_content_lang_code((string) ($_GET['language_code'] ?? \classes\system\Session::get('admin_pages_lang')));
        \classes\system\Session::set('admin_pages_lang', $languageCode);

        if ($pageId > 0) {
            $this->notifyOperationResult(
                $this->models['m_pages']->updatePageSearchState($pageId, $enabled),
                [
                    'success_message' => $enabled
                        ? ($this->lang['sys.search_page_enabled'] ?? 'Поиск для страницы включён')
                        : ($this->lang['sys.search_page_disabled'] ?? 'Поиск для страницы отключён'),
                    'default_error_message' => $this->lang['sys.error'] ?? 'Ошибка',
                ]
            );
        } else {
            ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => $this->lang['sys.required_id_parameter_missing'] ?? 'Required parameter id is missing.',
                'status' => 'warning',
            ]);
        }

        SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/pages?language_code=' . rawurlencode($languageCode));
    }

    private function renderPageSearchStateCell(array $item): string {
        $isEnabled = !empty($item['search_enabled']);
        $scopeMask = (int) ($item['search_scope_mask'] ?? Constants::SEARCH_SCOPE_ALL);
        $badges = [];

        $badges[] = $isEnabled
            ? '<span class="badge text-bg-success me-1">' . htmlspecialchars((string) ($this->lang['sys.yes'] ?? 'Да'), ENT_QUOTES, 'UTF-8') . '</span>'
            : '<span class="badge text-bg-secondary me-1">' . htmlspecialchars((string) ($this->lang['sys.no'] ?? 'Нет'), ENT_QUOTES, 'UTF-8') . '</span>';

        if ($isEnabled) {
            foreach ($this->getSearchScopeLabelsForPages() as $bit => $label) {
                if (($scopeMask & $bit) === $bit) {
                    $badges[] = '<span class="badge text-bg-light border text-dark me-1">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
                }
            }
            if ($scopeMask === 0) {
                $badges[] = '<span class="badge text-bg-warning text-dark me-1">' . htmlspecialchars((string) ($this->lang['sys.not_assigned'] ?? 'Не назначено'), ENT_QUOTES, 'UTF-8') . '</span>';
            }
        }

        return implode('', $badges);
    }

    private function getSearchScopeLabelsForPages(): array {
        return [
            Constants::SEARCH_SCOPE_PUBLIC => (string) ($this->lang['sys.search_scope_public_label'] ?? 'Публичный сайт'),
            Constants::SEARCH_SCOPE_MANAGER => (string) ($this->lang['sys.search_scope_manager_label'] ?? 'Рабочий интерфейс'),
            Constants::SEARCH_SCOPE_ADMIN => (string) ($this->lang['sys.search_scope_admin_label'] ?? 'Системный интерфейс'),
        ];
    }
}
