<?php

namespace app\admin;

use classes\system\SysClass;
use classes\system\Plugins;
use classes\system\Session;
use classes\system\Constants;

/**
 * Функции работы с сообщениями
 */
trait MessagesTrait {

    /**
     * Страница сообщений пользователя
     * Все сообщения текущего пользователя подгружаются в $user_data
     */
    public function messages($params = []) {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* model */
        $this->loadModel('m_messages');
        /* get data */
        $user_data = $this->users->data;         
        $keyId = array_search('id', $params);
        if ($keyId !== false && isset($params[$keyId + 1])) {
            $user_id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
        } else {
            $user_id = $this->logged_in; 
        }
        if ($this->logged_in != $user_id && $user_data['user_role'] > 2) { // просмотр чужих сообщений доступен только амину и модератору
            $user_id = $this->logged_in;
        }
        $messages_table = $this->get_messages_data_table($user_id);
        /* view */
        $this->getStandardViews();
        $this->view->set('messages_table', $messages_table);
        $this->view->set('body_view', $this->view->read('v_messages'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/messages.js" type="text/javascript" /></script>';
        $messagesTitle = (string) ($this->lang['sys.messages'] ?? 'Messages');
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . $messagesTitle;
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - ' . $messagesTitle;
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу сообщений переданного пользователя
     */
    public function get_messages_data_table($user_id) {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $this->loadModel('m_messages');
        $postData = SysClass::ee_cleanArray($_POST);
        $sourceOptions = [
            ['value' => 'system', 'label' => 'Система'],
            ['value' => 'payment', 'label' => 'Оплата'],
            ['value' => 'invoice', 'label' => 'Счета'],
            ['value' => 'billing', 'label' => 'Биллинг'],
            ['value' => 'object', 'label' => 'Объект'],
            ['value' => 'owner_object', 'label' => 'Заявка владельца'],
            ['value' => 'placement', 'label' => 'Размещение'],
            ['value' => 'review', 'label' => 'Отзывы'],
        ];
        $data_table = [
            'columns' => [
                [
                    'field' => 'message_id',
                    'title' => 'ID',
                    'sorted' => false,
                    'filterable' => false,
                    'width' => 5,
                    'align' => 'center'
                ], [
                    'field' => 'author_id',
                    'title' => $this->lang['sys.author'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'title',
                    'title' => (string) ($this->lang['sys.title'] ?? 'Заголовок'),
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'source_type',
                    'title' => (string) ($this->lang['sys.type'] ?? 'Тип'),
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'message_text',
                    'title' => $this->lang['sys.message'],
                    'sorted' => false,
                    'filterable' => true
                ], [
                    'field' => 'created_at',
                    'title' => $this->lang['sys.date_create'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'read_at',
                    'title' => $this->lang['sys.read_at'],
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
        $users = $this->users->getUsersData(false, false, false, 100000000);        
        if (isset($users['data']) && count($users['data'])) {
            foreach ($users['data'] as $item) {
                $filter_authors[] = ['value' => $item['user_id'], 'label' => $item['name']];
            }
        } else {
            $filter_authors = [];
        }
        $filters = [
            'author_id' => [
                'type' => 'select',
                'id' => "author_id",
                'value' => [],
                'label' => $this->lang['sys.author'],
                'options' => $filter_authors,
                'multiple' => true                
            ],
            'message_text' => [
                'type' => 'text',
                'id' => "message_text",
                'value' => '',
                'label' => $this->lang['sys.message']
            ],
            'title' => [
                'type' => 'text',
                'id' => "title",
                'value' => '',
                'label' => (string) ($this->lang['sys.title'] ?? 'Заголовок')
            ],
            'source_type' => [
                'type' => 'select',
                'id' => 'source_type',
                'value' => [],
                'label' => (string) ($this->lang['sys.type'] ?? 'Тип'),
                'options' => $sourceOptions,
                'multiple' => true
            ],
            'created_at' => [
                'type' => 'date',
                'id' => "created_at",
                'value' => '',
                'label' => $this->lang['sys.date_create']
            ],
            'read_at' => [
                'type' => 'date',
                'id' => "read_at",
                'value' => '',
                'label' => $this->lang['sys.read_at']
            ],
        ];
        $selected_sorting = [];
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);            
            $user_id = Session::get('get_messages_data_table_userID');
            $messages_array = $this->models['m_messages']->get_user_messages($user_id, $params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            Session::set('get_messages_data_table_userID', $user_id);
            $messages_array = $this->models['m_messages']->get_user_messages($user_id, false, false, false, 25);
        }
        foreach ($messages_array['data'] as $item) {
            $actions = '';
            $title = trim((string) ($item['title'] ?? ''));
            $sourceType = trim((string) ($item['source_type'] ?? 'system'));
            $sourceLabel = match ($sourceType) {
                'payment', 'billing', 'invoice' => 'Оплата',
                'object', 'owner_object', 'placement' => 'Объект',
                'review' => 'Отзывы',
                default => 'Система',
            };
            $openUrl = trim((string) ($item['url'] ?? ''));
            if ($this->logged_in == $user_id) {
                $read_at = $item['read_at'] ? '<span class="p-3"><i class="fa-solid fa-check text-success" data-bs-toggle="tooltip" data-bs-placement="top"'
                        . 'title="' . $this->lang['sys.read'] . '"></i></span>' :
                        '<a href="' . htmlspecialchars(\classes\system\CsrfService::appendToUrl('/admin/set_readed/id/' . $item['message_id']), ENT_QUOTES, 'UTF-8') . '"'
                        . 'class="btn btn-info me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.mark_as_read'] . '">'
                        . '<i class="fa-regular fa-eye"></i></a>';
                $actions = $read_at;
                if ($openUrl !== '') {
                    $actions .= '<a href="' . htmlspecialchars($openUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-secondary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars((string) ($this->lang['sys.open'] ?? 'Открыть'), ENT_QUOTES, 'UTF-8') . '"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>';
                }
                $actions .= '<a href="' . htmlspecialchars(\classes\system\CsrfService::appendToUrl('/admin/dell_message/id/' . $item['message_id']), ENT_QUOTES, 'UTF-8') . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                        . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>';
            } else { // Нельзя удалить или пометить прочитанными чужие сообщения
                if ($openUrl !== '') {
                    $actions = '<a href="' . htmlspecialchars($openUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-secondary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars((string) ($this->lang['sys.open'] ?? 'Открыть'), ENT_QUOTES, 'UTF-8') . '"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>';
                }
            }
            $data_table['rows'][] = [
                'message_id' => $item['message_id'],
                'author_id' => trim((string) ($item['author_name'] ?? '')) !== '' ? $item['author_name'] : $item['author_id'],
                'title' => $title !== '' ? SysClass::truncateString($title, 40) : '—',
                'source_type' => $sourceLabel,
                'message_text' => SysClass::truncateString($item['message_text'], 20),
                'status' => $item['status'],
                'created_at' => date('d.m.Y h:i:s', strtotime($item['created_at'])),
                'read_at' => $item['read_at'] ? date('d.m.Y h:i:s', strtotime($item['read_at'])) : '',
                'actions' => $actions,
                'nested_table' => [
                    'columns' => [
                        ['field' => 'message_title', 'title' => (string) ($this->lang['sys.title'] ?? 'Заголовок'), 'width' => 20, 'align' => 'left'],
                        ['field' => 'message_source', 'title' => (string) ($this->lang['sys.type'] ?? 'Тип'), 'width' => 15, 'align' => 'left'],
                        ['field' => 'message_link', 'title' => (string) ($this->lang['sys.link'] ?? 'Ссылка'), 'width' => 20, 'align' => 'left'],
                        ['field' => 'message_full_text', 'title' => $this->lang['sys.content'], 'width' => 20, 'align' => 'left'],
                    ],
                    'rows' => [
                        [
                            'message_title' => $title !== '' ? $title : '—',
                            'message_source' => $sourceLabel,
                            'message_link' => $openUrl !== '' ? '<a href="' . htmlspecialchars($openUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($openUrl, ENT_QUOTES, 'UTF-8') . '</a>' : '—',
                            'message_full_text' => $item['message_text'],
                        ],
                    ],
                ]                
            ];
        }
        $data_table['total_rows'] = $messages_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('messages_table', $data_table, 'get_messages_data_table', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('messages_table', $data_table, 'get_messages_data_table', $filters);
        }
    }
    
    /**
     * Отметить все сообщения пользователя прочитанными
     */
    public function read_all_message($params = []) {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access) || array_filter((array) $params)) {
            SysClass::handleRedirect(401);
            exit;
        }
        if (!$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin/messages',
        ])) {
            return;
        }
        $this->loadModel('m_messages');
        $this->models['m_messages']->read_all($this->logged_in);
        SysClass::handleRedirect(200, $_SERVER['HTTP_REFERER']);
    }

    /**
     * Удалить все сообщения пользователя
     */
    public function kill_all_message($params = []) {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access) || array_filter((array) $params)) {
            SysClass::handleRedirect(401);
            exit();
        }
        if (!$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin/messages',
        ])) {
            return;
        }
        $this->loadModel('m_messages');
        $this->models['m_messages']->kill_all_message($this->logged_in);
        SysClass::handleRedirect(200, '/admin/messages');
    }

    /**
     * Пометить сообщение прочитанным
     * @param array $params - ID сообщения
     */
    public function set_readed($params = []) {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(401);
            exit();
        }
        if (!$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin/messages',
        ])) {
            return;
        }
        $keyId = array_search('id', (array) $params, true);
        if ($keyId !== false && isset($params[$keyId + 1])) {
            $message_id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
        } else {
            $message_id = isset($params[0]) ? filter_var($params[0], FILTER_VALIDATE_INT) : 0;
        }
        $this->loadModel('m_messages');
        $this->models['m_messages']->set_message_as_readed($message_id, $this->logged_in);
        SysClass::handleRedirect(200, '/admin/messages');
    }

    /**
     * Удалит все оповещения пользователя
     * связанные с непрочитанными сообщениями
     */
    public function set_readed_all($params = []) {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access) || array_filter((array) $params)) {
            SysClass::handleRedirect(401);
            exit();
        }
        if (!$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin/messages',
        ])) {
            return;
        }
        $this->loadModel('m_messages');
        $this->models['m_messages']->read_all($this->logged_in);
        SysClass::handleRedirect(200, '/admin/messages');
    }

    /**
     * Удалить сообщение
     * @param array $params - ID сообщения
     */
    public function dell_message($params) {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(401);
            exit();
        }
        if (!$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin/messages',
        ])) {
            return;
        }
        $keyId = array_search('id', (array) $params, true);
        if ($keyId !== false && isset($params[$keyId + 1])) {
            $message_id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
        } else {
            $message_id = isset($params[0]) ? filter_var($params[0], FILTER_VALIDATE_INT) : 0;
        }
        $this->loadModel('m_messages');
        $this->models['m_messages']->kill_message($message_id, $this->logged_in);
        SysClass::handleRedirect(200, '/admin/messages');
    }

}
