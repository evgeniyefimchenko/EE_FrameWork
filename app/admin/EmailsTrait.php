<?php

namespace app\admin;

use classes\system\SysClass;
use classes\system\Constants;
use classes\system\Plugins;

/**
 * Функции работы с письмами
 */
trait EmailsTrait {
    /* Список шаблонов писем */

    public function email_templates(array $params = []): void {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* model */
        $this->loadModel('m_email_templates');
        /* data */
        $email_templates_table = $this->getEmailTemplatesDataTable();
        /* view */
        $this->getStandardViews();
        $this->view->set('email_templates_table', $email_templates_table);
        $this->view->set('body_view', $this->view->read('v_email_templates'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/email_templates.js" type="text/javascript" /></script>';
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = 'Emails Templates';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Список почтовых сниппетов
     */
    public function email_snippets(array $params = []): void {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
        }

        /* model */
        $this->loadModel('m_email_templates');

        /* view */
        $this->getStandardViews();
        $snippets_table = $this->getEmailSnippetsDataTable();
        $this->view->set('snippets_table', $snippets_table);
        $this->view->set('body_view', $this->view->read('v_email_snippets'));
        $this->html = $this->view->read('v_dashboard');

        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_email_snippets.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - Email Snippets';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - Email Snippets';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу почтовых сниппетов
     */
    public function getEmailSnippetsDataTable(array $params = []): string {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_email_templates');
        $postData = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'snippet_id',
                    'title' => 'ID',
                    'sorted' => true,
                    'filterable' => false,
                    'width' => 5,
                    'align' => 'center'
                ], [
                    'field' => 'name',
                    'title' => $this->lang['sys.name'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'description',
                    'title' => $this->lang['sys.description'],
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
            'description' => [
                'type' => 'text',
                'id' => "description",
                'value' => '',
                'label' => $this->lang['sys.description']
            ]
        ];
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $snippets_array = $this->models['m_email_templates']->getEmailSnippets($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $snippets_array = $this->models['m_email_templates']->getEmailSnippets(false, false, false, 25);
        }
        foreach ($snippets_array['data'] as $item) {
            $data_table['rows'][] = [
                'snippet_id' => $item['snippet_id'],
                'name' => $item['name'],
                'description' => $item['description'],
                'actions' => '<a href="/admin/email_snippet_edit/id/' . $item['snippet_id'] . '"'
                . 'class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . '<a href="/admin/email_snippet_delete/id/' . $item['snippet_id'] . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>'
            ];
        }
        $data_table['total_rows'] = $snippets_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('email_snippets_table', $data_table, 'getEmailSnippetsDataTable', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('email_snippets_table', $data_table, 'getEmailSnippetsDataTable', $filters);
        }
    }

    /**
     * Возвращает таблицу почтовых шаблонов
     * @return string
     */
    public function getEmailTemplatesDataTable(): string {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_email_templates');
        $postData = SysClass::ee_cleanArray($_POST);
        $dataTable = [
            'columns' => [
                [
                    'field' => 'template_id',
                    'title' => 'ID',
                    'sorted' => true,
                    'filterable' => false,
                    'width' => 5,
                    'align' => 'center'
                ], [
                    'field' => 'name',
                    'title' => $this->lang['sys.name'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'subject',
                    'title' => $this->lang['sys.theme'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'description',
                    'title' => $this->lang['sys.description'],
                    'sorted' => false,
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
            'subject' => [
                'type' => 'text',
                'id' => "subject",
                'value' => '',
                'label' => $this->lang['sys.theme']
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
            $email_templates_array = $this->models['m_email_templates']->getEmailTemplates($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $email_templates_array = $this->models['m_email_templates']->getEmailTemplates(false, false, false, 25);
        }
        foreach ($email_templates_array['data'] as $item) {
            $dataTable['rows'][] = [
                'template_id' => $item['template_id'],
                'name' => $item['name'],
                'subject' => $item['subject'],
                'description' => $item['description'],
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'updated_at' => $item['updated_at'] ? date('d.m.Y', strtotime($item['updated_at'])) : '',
                'actions' => '<a href="/admin/edit_email_template/id/' . $item['template_id'] . '"'
                . 'class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                . '<a href="/admin/delete_email_template/id/' . $item['template_id'] . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>'
            ];
        }
        $dataTable['total_rows'] = $email_templates_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('email_templates_table', $dataTable, 'getEmailTemplatesDataTable', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('email_templates_table', $dataTable, 'getEmailTemplatesDataTable', $filters);
        }
    }

    /**
     * Редактирование шаблона писем
     * @param array $params Параметры запроса (например, ['id' => 1])
     */
    public function edit_email_template(array $params = []): void {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $defaultData = [
            'template_id' => 0,
            'name' => '',
            'subject' => '',
            'body' => '',
            'description' => '',
            'created_at' => '',
            'updated_at' => '',
            'language_code' => ENV_DEF_LANG,
        ];
        /* model */
        $this->loadModel('m_email_templates');
        $postData = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $templateId = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $templateId = 0;
            }
            $newEntity = empty($templateId) ? true : false;
            if (isset($postData['name']) && $postData['name']) {
                // Сохранение основных данных
                if (!$new_id = $this->models['m_email_templates']->updateEmailTemplateData($postData)) {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                } else {
                    $templateId = $new_id;
                }
            }
            $this->processPostParams($postData, $newEntity, $templateId);
            $getEmailTemplateData = ((int) $templateId ? $this->models['m_email_templates']->getEmailTemplateData($templateId) : null) ?: $defaultData;
        } else { // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/email_templates');
            exit();
        }
        $arrCodeSnippet = $this->models['m_email_templates']->getEmailSnippets();
        $codeSnippet = [];
        if ($arrCodeSnippet['total_count']) {
            foreach ($arrCodeSnippet['data'] as $snippet) {
                $codeSnippet[$snippet['name']] = htmlspecialchars($snippet['content']);
            }
        }
        $emailObject = new \classes\helpers\ClassMail();
        $emailBodyWithSnippets = $emailObject->replaceCodeAndSnippets($getEmailTemplateData['body']);
        /* view */
        $this->getStandardViews();
        // Замена сниппетов        
        $this->view->set('codeSnippet', $codeSnippet);
        $this->view->set('codeVars', Constants::PUBLIC_CONSTANTS);
        $this->view->set('templateData', $getEmailTemplateData);
        $this->view->set('emailBodyWithSnippets', $emailBodyWithSnippets); //Для предпросмотра
        $this->view->set('body_view', $this->view->read('v_edit_email_template'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->addEditorToLayout();
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_email_templates.js" type="text/javascript" /></script>';
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->addEditorToLayout(); // Добавляем редактор, если необходимо
        $this->parameters_layout["title"] = 'Emails Templates Edit';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Редактирование или добавление email-сниппета
     * @param array $params Параметры запроса
     */
    public function email_snippet_edit(array $params = []): void {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $defaultData = [
            'snippet_id' => 0,
            'name' => '',
            'content' => '',
            'description' => '',
            'created_at' => '',
            'updated_at' => '',
            'language_code' => ENV_DEF_LANG
        ];
        /* model */
        $this->loadModel('m_email_templates');
        $postData = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $snippetId = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $snippetId = 0;
            }
            if (isset($postData['name']) && $postData['name']) {
                if (!$new_id = $this->models['m_email_templates']->updateEmailSnippetData($postData)) {
                    ClassNotifications::addNotificationUser($this->logged_in, [
                        'text' => $this->lang['sys.db_registration_error'],
                        'status' => 'danger'
                    ]);
                } else {
                    $snippetId = $new_id;
                }
            }
            $newEntity = empty($snippetId) ? true : false;
            $this->processPostParams($postData, $newEntity, $snippetId);
            $getSnippetData = ((int) $snippetId ? $this->models['m_email_templates']->getEmailSnippetData($snippetId) : null) ?: $defaultData;
        } else {
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/email_snippets');
            exit();
        }
        $arrCodeSnippet = $this->models['m_email_templates']->getEmailSnippets();
        $codeSnippet = [];
        if ($arrCodeSnippet['total_count']) {
            foreach ($arrCodeSnippet['data'] as $snippet) {
                $codeSnippet[$snippet['name']] = htmlspecialchars($snippet['content']);
            }
        }
        /* view */
        $this->getStandardViews();
        $this->view->set('snippetData', $getSnippetData);
        $this->view->set('codeSnippet', $codeSnippet); // Все сниппеты для контекста
        $this->view->set('codeVars', Constants::PUBLIC_CONSTANTS); // Переменные для использования в сниппетах
        $this->view->set('body_view', $this->view->read('v_edit_email_snippet')); // Предполагается, что есть такой шаблон
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->addEditorToLayout(); // Добавляем текстовый редактор
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_email_snippets.js" type="text/javascript" /></script>';
        $this->addEditorToLayout();
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = $this->lang['sys.edit_email_template']; // Можно заменить на 'Edit Email Snippet'
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Отправим тестовое сообщение AJAX
     * @param array $params
     */
    public function sendTestEmail($params = []) {
        $is_ajax = SysClass::isAjaxRequestFromSameSite();
        if ($is_ajax && empty($params)) {
            $postData = SysClass::ee_cleanArray($_POST);
            if (\classes\helpers\ClassMail::send_mail($postData['email_test'], '', $postData['template_id'])) {
                die(json_encode(['status' => $this->lang['sys.success']]));
            } else {
                die(json_encode(['status' => $this->lang['sys.error']]));
            }
        }
        die;
    }

    /**
     * Удаление шаблона письма
     * @param type $params
     */
    public function delete_email_template($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* model */
        $this->loadModel('m_email_templates');
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $templateId = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $templateId = 0;
            }
            $res = $this->models['m_email_templates']->deleteEmailTemplate($templateId);
            if (isset($res['error'])) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => $res['error'], 'status' => 'danger']);
            } else {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.removed'], 'status' => 'success']);
            }
        }
        SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/categories');
    }
}
