<?php

namespace app\admin;

use classes\system\SysClass;
use classes\system\Plugins;
use classes\helpers\ClassNotifications;

/**
 * Функции работы с сообщениями
 */
trait MessagesTrait {

    /**
     * Страница сообщений пользователя
     * Все сообщения текущего пользователя подгружаются в $user_data
     */
    public function messages($params = []) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        /* model */
        $this->load_model('m_messages');
        /* get data */
        $user_data = $this->users->data;         
        $key_id = array_search('id', $params);
        if ($key_id !== false && isset($params[$key_id + 1])) {
            $user_id = filter_var($params[$key_id + 1], FILTER_VALIDATE_INT);
        } else {
            $user_id = $this->logged_in; 
        }
        if ($this->logged_in != $user_id && $user_data['user_role'] > 2) { // просмотр чужих сообщений доступен только амину и модератору
            $user_id = $this->logged_in;
        }
        $messages_table = $this->get_messages_data_table($user_id);
        /* view */
        $this->get_standart_view();
        $this->view->set('messages_table', $messages_table);
        $this->view->set('body_view', $this->view->read('v_messages'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/messages.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'MESSAGES';
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу сообщений переданного пользователя
     */
    public function get_messages_data_table($user_id) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $this->load_model('m_messages');
        $post_data = SysClass::ee_cleanArray($_POST);
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
        $users = $this->users->get_users_data(false, 'WHERE user_id NOT IN (1,2,3)', false, 1000000);        
        if (isset($users['data']) && count($users['data'])) {
            foreach ($users as $item) {
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
        if ($post_data && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_show_table_prepare_params($post_data, $data_table['columns']);
            $messages_array = $this->models['m_messages']->get_user_messages($user_id, $params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $messages_array = $this->models['m_messages']->get_user_messages($user_id, false, false, false, 25);
        }
        foreach ($messages_array['data'] as $item) {
            if ($this->logged_in == $user_id) {
                $read_at = $item['read_at'] ? '<span class="p-3"><i class="fa-solid fa-check text-success" data-bs-toggle="tooltip" data-bs-placement="top"'
                        . 'title="' . $this->lang['sys.read'] . '"></i></span>' :
                    '<a href="/admin/set_readed/id/' . $item['message_id'] . '"'
                    . 'class="me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.mark_as_read'] . '">'
                    . '<i class="fa-regular fa-eye"></i></a>';
                $actions = $read_at . '<a href="/admin/dell_message/id/' . $item['message_id'] . '" onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" ' 
                    . 'class="btn btn-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash"></i></a>';
            } else { // Нельзя удалить или пометить прочитанными чужие сообщения
                $actions = '';
            }           
            $data_table['rows'][] = [
                'message_id' => $item['message_id'],
                'author_id' => $item['author_id'],
                'message_text' => SysClass::truncate_string($item['message_text'], 20),
                'status' => $item['status'],
                'created_at' => date('d.m.Y h:i:s', strtotime($item['created_at'])),
                'read_at' => $item['read_at'] ? date('d.m.Y h:i:s', strtotime($item['read_at'])) : '',
                'actions' => $actions,
                    'nested_table' => [
                        'columns' => [
                            ['field' => 'message_full_text', 'title' => $this->lang['sys.content'], 'width' => 20, 'align' => 'left'],
                        ],
                        'rows' => [
                            ['message_full_text' => $item['message_text']],
                        ],
                    ],                
            ];
        }
        $data_table['total_rows'] = $messages_array['total_count'];
        if ($post_data) {
            echo Plugins::ee_show_table('messages_table', $data_table, 'get_messages_data_table', $filters, $post_data["page"], $post_data["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('messages_table', $data_table, 'get_messages_data_table', $filters);
        }
    }
    
    /**
     * Отметить все сообщения пользователя прочитанными
     */
    public function read_all_message() {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(401);
            exit;
        }
        $this->load_model('m_messages');
        $this->models['m_messages']->read_all($this->logged_in);
        SysClass::return_to_main(200, $_SERVER['HTTP_REFERER']);
    }

    /**
     * Удалить все сообщения пользователя
     */
    public function kill_all_message() {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(401);
            exit();
        }
        $this->load_model('m_messages');
        $this->models['m_messages']->kill_all_message($this->logged_in);
        SysClass::return_to_main(200, '/admin/messages');
    }

    /**
     * Пометить сообщение прочитанным
     * @param array $params - ID сообщения
     */
    public function set_readed($id_message = 0) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(401);
            exit();
        }
        $message_id = filter_var($id_message, FILTER_VALIDATE_INT);
        $this->load_model('m_messages');
        $this->models['m_messages']->set_message_as_readed($message_id, $this->logged_in);
    }

    /**
     * Удалит все оповещения пользователя
     * связанные с непрочитанными сообщениями
     */
    public function set_readed_all() {
        ClassNotifications::kill_notification_by_text($this->logged_in, 'непрочитанное сообщение');
        $this->set_readed();
    }

    /**
     * Удалить сообщение
     * @param array $params - ID сообщения
     */
    public function dell_message($params) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(401);
            exit();
        }
        $message_id = filter_var($params[0], FILTER_VALIDATE_INT);
        $this->load_model('m_messages');
        $this->models['m_messages']->kill_message($message_id, $this->logged_in);
    }

}
