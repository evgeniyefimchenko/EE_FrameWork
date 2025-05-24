<?php

use classes\system\ControllerBase;
use classes\system\SysClass;
use classes\system\Constants;
use classes\system\Plugins;
use classes\helpers\ClassNotifications;
use classes\system\Hook;
use app\admin\MessagesTrait;
use app\admin\NotificationsTrait;
use app\admin\SystemsTrait;
use app\admin\EmailsTrait;
use app\admin\CategoriesTrait;
use app\admin\CategoriesTypesTrait;
use app\admin\PagesTrait;
use app\admin\PropertiesTrait;
use classes\helpers\ClassMessages;

/*
 * Админ-панель
 */
class ControllerAdmin Extends ControllerBase {
    /* Подключение traits */

use MessagesTrait,
    NotificationsTrait,
    SystemsTrait,
    EmailsTrait,
    CategoriesTrait,
    CategoriesTypesTrait,
    PagesTrait,
    PropertiesTrait;

    /**
     * Главная страница админ-панели
     */
    public function index($params = []): void {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin');
        }
        /* models */
        /* get user data - все переменные пользователя доступны в представлениях */
        $user_data = $this->users->data;
        /* views */
        $this->getStandardViews();
        /* Отобразить контент согласно уровня доступа */
        if ($user_data['user_role'] == 1) { // Доступ для администратора
            $data_table = $this->get_admin_dashboard_data_table();
            $this->view->set('data_table', $data_table);
            $this->view->set('body_view', $this->view->read('v_dashboard_admin'));
            $this->parameters_layout["add_script"] .= '<script src="/assets/js/plugins/Chart.js" ></script>';
            $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/dashboard_admin.js" type="text/javascript" /></script>';
        }
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - DASHBOARD';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - DASHBOARD';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Формируем данные для таблицы дашборда администратора
     */
    public function get_admin_dashboard_data_table($params = []) {
        $this->access = [Constants::ADMIN];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin');
        }
        /* Пример данных таблицы */
        $data_table = [
            'columns' => [
                [
                    'field' => 'name', // Имя поля в данных
                    'title' => 'Имя', // Заголовок столбца
                    'sorted' => 'ASC', // Направление сортировки (ASC, DESC или false, если сортировка не применена)
                    'filterable' => true    // Возможность фильтрации по этому столбцу (true или false)
                ],
                [
                    'field' => 'age',
                    'title' => 'Возраст',
                    'sorted' => true,
                    'filterable' => true
                ],
                [
                    'field' => 'address',
                    'title' => 'Адрес',
                    'sorted' => false,
                    'filterable' => true
                ],
                [
                    'field' => 'gender',
                    'title' => 'Пол',
                    'sorted' => true,
                    'filterable' => true
                ],
                [
                    'field' => 'registration_date',
                    'title' => 'Дата регистрации',
                    'sorted' => false,
                    'filterable' => true
                ],
            // ... другие столбцы ...
            ],
            'rows' => [
                [
                    'name' => 'Джон',
                    'age' => 25,
                    'address' => 'ул. Ленина, 5',
                    'gender' => 'Мужской',
                    'registration_date' => '2021-01-15',
                    'nested_table' => [
                        'columns' => [
                            ['field' => 'detail_id', 'title' => 'Detail ID', 'width' => 20, 'align' => 'left'],
                            ['field' => 'description', 'title' => 'Description', 'width' => 80, 'align' => 'left'],
                        // ...
                        ],
                        'rows' => [
                            ['detail_id' => 1, 'description' => 'Detail 1'],
                            ['detail_id' => 2, 'description' => 'Detail 2'],
                        // ...
                        ],
                    ],
                ],
                [
                    'name' => 'Джейн',
                    'age' => 30,
                    'address' => 'пр. Мира, 15',
                    'gender' => 'Женский',
                    'registration_date' => '2020-10-07'
                ],
                [
                    'name' => 'Иван',
                    'age' => 35,
                    'address' => 'ул. Комсомольская, 4',
                    'gender' => 'Мужской',
                    'registration_date' => '2019-02-14'
                ],
                [
                    'name' => 'Мария',
                    'age' => 28,
                    'address' => 'ул. Ленина, 22',
                    'gender' => 'Женский',
                    'registration_date' => '2018-05-21'
                ],
                [
                    'name' => 'Александр',
                    'age' => 42,
                    'address' => 'пр. Революции, 7',
                    'gender' => 'Мужской',
                    'registration_date' => '2016-12-12'
                ],
                [
                    'name' => 'Анна',
                    'age' => 23,
                    'address' => 'ул. Московская, 19',
                    'gender' => 'Женский',
                    'registration_date' => '2020-01-15'
                ],
                [
                    'name' => 'Дмитрий',
                    'age' => 37,
                    'address' => 'пр. Строителей, 8',
                    'gender' => 'Мужской',
                    'registration_date' => '2017-03-03'
                ],
                [
                    'name' => 'Ольга',
                    'age' => 31,
                    'address' => 'ул. Зеленая, 33',
                    'gender' => 'Женский',
                    'registration_date' => '2018-08-10'
                ],
                [
                    'name' => 'Сергей',
                    'age' => 40,
                    'address' => 'ул. Парковая, 5',
                    'gender' => 'Мужской',
                    'registration_date' => '2015-06-30'
                ],
                [
                    'name' => 'Екатерина',
                    'age' => 29,
                    'address' => 'пр. Королева, 50',
                    'gender' => 'Женский',
                    'registration_date' => '2019-09-09'
                ],
                [
                    'name' => 'Андрей',
                    'age' => 33,
                    'address' => 'ул. Приморская, 70',
                    'gender' => 'Мужской',
                    'registration_date' => '2020-04-20'
                ],
                [
                    'name' => 'Татьяна',
                    'age' => 26,
                    'address' => 'пр. Ветеранов, 2',
                    'gender' => 'Женский',
                    'registration_date' => '2021-02-28'
                ],
            // ... другие строки ...
            ],
            'total_rows' => 1110  // Общее количество записей (используется для пагинации)
        ];
        $filters = [];
        $postData = SysClass::ee_cleanArray($_POST);
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX						
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            echo Plugins::ee_show_table('example_table', $data_table, 'get_admin_dashboard_data_table', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            $html = Plugins::ee_show_table('example_table', $data_table, 'get_admin_dashboard_data_table', $filters);
        }
        return $html;
    }

    /**
     * Коммерческое предложение
     */
    public function upgrade($params = []) {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin');
        }
        /* view */
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_upgrade'));
        $this->html = $this->view->read('v_dashboard');

        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - UPGRADE';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - UPGRADE';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin/upgrade';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Загрузка стандартных представлений для каждой страницы
     */
    private function getStandardViews() {
        Hook::run('A_beforeGetStandardViews', $this->view);
        $this->view->set('top_bar', $this->view->read('v_top_bar'));
        $this->view->set('main_menu', $this->view->read('v_main_menu'));
        $this->view->set('page_footer', /* $this->view->read('v_footer') */ ''); // TODO        
        Hook::run('A_afterGetStandardViews', $this->view);
    }

    /**
     * Обработка AJAX запросов админ-панели
     * @param array $params - дополнительные параметры запрещены
     * @param POST $postData - POST параметры update или get
     */
    public function ajax_admin(array $params = []) {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access) || count($params) > 0) {
            echo '{"error": "access denieded ' . var_export([$this->logged_in, $this->access], true) . '"}';
            exit();
        }
        /* get data */
        $user_data = $this->users->data;
        /* Read POST data */
        $postData = SysClass::ee_cleanArray($_POST);
        switch (true) {
            case isset($postData['update']):
                foreach ($postData as $key => $value) {
                    if (array_key_exists($key, $user_data['options'])) {
                        $user_data['options'][$key] = $value;
                    }
                }
                echo $this->users->setUserOptions($this->logged_in, $user_data['options']);
                exit();
            case isset($postData['get']):
                echo json_encode($user_data['options']);
                exit();
            default:
                echo '{"error": "no data"}';
        }
    }

    /**
     * Карточка пользователя сайта
     * для изменения данных и внесения новых пользователей вручную
     * @param type $params
     */
    public function user_edit($params) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* get current user data */
        $user_data = $this->users->data;
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $user_id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $user_id = 0;
            }
            $get_user_context = is_integer($user_id) ? $this->users->getUserData($user_id) : [];
            /* Нельзя посмотреть чужую карточку равной себе роли или выше */
            if (!$user_id || $this->users->data['user_role'] >= $get_user_context['user_role'] && $this->logged_in != $user_id) {
                SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
            }
        } else {                                                                            // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        if (isset($get_user_context['new_user']) && $get_user_context['new_user']) {
            $get_user_context['user_id'] = 0;
            $get_user_context['name'] = '';
            $get_user_context['email'] = '';
            $get_user_context['phone'] = '';
            $get_user_context['user_role_text'] = '';
            $get_user_context['created_at'] = '';
            $get_user_context['updated_at'] = '';
            $get_user_context['last_activ'] = '';
        }
        $this->loadModel('m_user_edit');
        /* Если не админ и не модератор и карточка не своя возвращаем */
        if ($this->users->data['user_role'] > 2 && $this->logged_in != $user_id) {
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        /* get data */
        $get_user_context['active_text'] = $this->lang[Constants::USERS_STATUS[$get_user_context['active']]];
        $free_active_status = Constants::USERS_STATUS;
        unset($free_active_status[$get_user_context['active']]);
        $get_free_roles = $this->models['m_user_edit']->get_free_roles($get_user_context['user_role']); // Получим свободные роли
        $this->view->set('free_active_status', $free_active_status);
        $this->view->set('get_free_roles', $get_free_roles);

        /* view */
        $this->view->set('user_context', $get_user_context);
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_edit_user'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/js/plugins/JQ_mask.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_user.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = $this->lang['sys.user_edit'];
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Аякс изменение рег. данных пользователя
     * Редактирование возможно модераторами
     * или самим пользователем
     * @param $params - ID пользователя для изменения
     * @return json сообщение об ошибке или no
     */
    public function ajax_user_edit($params) {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            echo json_encode(array('error' => 'error no access'));
            exit();
        }
        $postData = SysClass::ee_cleanArray($_POST);
        $keyId = array_search('id', $params);
        if ($keyId !== false && isset($params[$keyId + 1])) {
            $user_id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
        } else {
            $user_id = 0;
        }
        if ($this->users->data['user_role'] > 2 && $this->logged_in != $user_id) { // Роль меньше модератора или id не текущего пользователя выходим
            echo json_encode(array('error' => 'error no access'));
            exit();
        }
        /* set data user */
        $postData['phone'] = isset($postData['phone']) ? preg_replace('/[^0-9+]/', '', $postData['phone']) : null;
        if ($this->users->data['phone'] && $this->users->data['user_role'] > 2) {
            unset($postData['phone']);
        }

        if (isset($postData['new']) && $postData['new'] == 1) {
            if ($this->users->registrationNewUser($postData)) {
                $new_id = $this->users->get_user_id(trim($postData['email']));
                echo json_encode(array('error' => 'no', 'id' => $new_id));
                exit();
            } else {
                echo json_encode(array('error' => 'error ajax_user_edit isert user'));
                exit();
            }
        }
        if (isset($postData['subscribed']) && $postData['subscribed']) {
            $postData['subscribed'] = 1;
        } else {
            $postData['subscribed'] = 0;
        }
        if ($this->users->setUserData($user_id, $postData)) {
            $user_role = $this->users->getUserRole($user_id);
            if (isset($postData['user_role']) && $postData['user_role'] != $user_role) { // Сменилась роль пользователя, оповещаем админа и пишем лог                                
                SysClass::preFile('users_edit', 'ajax_user_edit', 'Изменили роль пользователю', ['id_user' => $user_id, 'old' => $this->users->data['user_role'], 'new' => $postData['user_role']]);
            }
            echo json_encode(array('error' => 'no', 'id' => $user_id));
            exit();
        } else {
            echo json_encode(array('error' => 'error ajax_user_edit'));
            exit();
        }
    }

    /**
     * Выводит список пользователей
     * Доступ у администраторов, модераторов
     * @param arg - массив аргументов для поиска
     */
    public function users($params = array()) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* view */
        $this->getStandardViews();
        $this->view->set('users_table', $this->get_users_table());
        $this->view->set('body_view', $this->view->read('v_users'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/users.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Пользователи';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу пользователей
     */
    public function get_users_table() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $postData = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'user_id',
                    'title' => 'ID',
                    'sorted' => 'ASC',
                    'filterable' => false,
                    'width' => 10,
                    'align' => 'center'
                ], [
                    'field' => 'name',
                    'title' => $this->lang['sys.name'],
                    'sorted' => false,
                    'filterable' => true
                ], [
                    'field' => 'email',
                    'title' => $this->lang['sys.email'],
                    'sorted' => false,
                    'filterable' => true
                ], [
                    'field' => 'user_role',
                    'title' => $this->lang['sys.role'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'active',
                    'title' => $this->lang['sys.status'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'created_at',
                    'title' => $this->lang['sys.sign_up_text'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'last_activ',
                    'title' => $this->lang['sys.activity'],
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
                'label' => 'Имя'
            ],
            'email' => [
                'type' => 'text',
                'id' => "email",
                'value' => '',
                'label' => 'email'
            ],
            'user_role' => [
                'type' => 'select',
                'id' => "user_role",
                'value' => [],
                'label' => 'Роль',
                'options' => [],
                'multiple' => false
            ],
            'active' => [
                'type' => 'select',
                'id' => "active",
                'value' => [],
                'label' => 'Статус',
                'options' => [],
                'multiple' => false
            ],
            'created_at' => [
                'type' => 'date',
                'id' => "created_at",
                'value' => '',
                'label' => 'Дата регистрации'
            ],
            'last_activ' => [
                'type' => 'date',
                'id' => "last_activ",
                'value' => '',
                'label' => 'Был активен'
            ],
        ];
        $this->loadModel('m_user_edit');
        $get_free_roles = $this->models['m_user_edit']->get_free_roles(0); // Получим все роли
        $filters['user_role']['options'][] = ['value' => '', 'label' => ''];
        foreach ($get_free_roles as $item) {
            $filters['user_role']['options'][] = ['value' => $item['role_id'], 'label' => $item['name']];
        }
        $filters['active']['options'][] = ['value' => '', 'label' => ''];
        foreach (Constants::USERS_STATUS as $k => $v) {
            $filters['active']['options'][] = ['value' => $k, 'label' => $this->lang[$v]];
        }

        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $users_array = $this->users->getUsersData($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $users_array = $this->users->getUsersData(false, false, false, 25);
        }

        foreach ($users_array['data'] as $item) {
            if (!in_array($item['user_id'], [1, 2, 3])) {
                $html_actions = '<a href="/admin/user_edit/id/' . $item['user_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                        . '<a href="/admin/delete_user/id/' . $item['user_id'] . '"  onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                        . 'class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash-alt"></i></a>';
            } else {
                $html_actions = '<a href="/admin/user_edit/id/' . $item['user_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>';
            }
            $data_table['rows'][] = [
                'user_id' => $item['user_id'],
                'name' => $item['name'],
                'email' => $item['email'],
                'user_role' => $item['user_role_text'],
                'active' => $this->lang[Constants::USERS_STATUS[$item['active']]],
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'last_activ' => $item['last_activ'] ? date('d.m.Y', strtotime($item['last_activ'])) : '',
                'actions' => $html_actions
            ];
        }
        $data_table['total_rows'] = $users_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('users_table', $data_table, 'get_users_table', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('users_table', $data_table, 'get_users_table', $filters);
        }
    }

    /**
     * Выведет роли пользователей
     * @param type $params
     */
    public function users_roles($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* view */
        $this->getStandardViews();
        $this->view->set('users_roles_table', $this->get_users_roles_table());
        $this->view->set('body_view', $this->view->read('v_users_roles'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = 'Роли пользователей';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу ролей пользователей
     */
    public function get_users_roles_table() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $postData = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'role_id',
                    'title' => 'ID',
                    'sorted' => 'ASC',
                    'filterable' => false,
                    'width' => 10,
                    'align' => 'center'
                ], [
                    'field' => 'name',
                    'title' => $this->lang['sys.name'],
                    'sorted' => true,
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
        $filters = [];
        $this->loadModel('m_user_edit');
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $users_array = $this->models['m_user_edit']->get_users_roles_data($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $users_array = $this->models['m_user_edit']->get_users_roles_data(false, false, false, 25);
        }

        foreach ($users_array['data'] as $item) {
            if (!in_array($item['role_id'], [1, 2, 3, 4, 8])) {
                $html_actions = '<a href="/admin/users_role_edit/id/' . $item['role_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>
				<a href="/admin/users_role_dell/id/' . $item['role_id'] . '" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash-alt"></i></a>';
            } else {
                $html_actions = $item['role_id'] == 1 ? '' : '<a href="/admin/users_role_edit/id/' . $item['role_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>';
            }
            $data_table['rows'][] = [
                'role_id' => $item['role_id'],
                'name' => $item['name'],
                'actions' => $html_actions
            ];
        }
        $data_table['total_rows'] = $users_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('users_roles_table', $data_table, 'get_users_roles_table', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('users_roles_table', $data_table, 'get_users_roles_table', $filters);
        }
    }

    /**
     * Удалит роль пользователя кроме стандартных
     * @param array $params
     */
    public function users_role_dell($params = []) {
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
            if (in_array($id, [1, 2, 3, 4, 8])) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Невозможно удалить системные роли!', 'status' => 'danger']);
            } else {
                $this->loadModel('m_user_edit');
                $this->models['m_user_edit']->users_role_dell($id);
            }
        }
        SysClass::handleRedirect(200, '/admin/users_roles');
    }

    /**
     * Установит флаг удалённого пользователя
     * @param type $params
     */
    public function delete_user($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $user_id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $user_id = 0;
            }
            if (in_array($user_id, [1, 2, 8])) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Невозможно удалить системные роли!', 'status' => 'danger']);
            } else {
                $this->loadModel('m_user_edit');
                if (!$this->models['m_user_edit']->delete_user($user_id)) {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Ошибка удаления пользователя id=' . $user_id, 'status' => 'danger']);
                } else {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Помечен удалённым id=' . $user_id, 'status' => 'info']);
                }
            }
        } else {
            ClassNotifications::addNotificationUser($this->logged_in, ['text' => 'Нет обязательного параметра id', 'status' => 'danger']);
        }
        SysClass::handleRedirect(200, '/admin/users');
    }

    /**
     * Отправит сообщение администратору AJAX
     * @param array $params
     */
    public function send_message_admin($params = []) {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access) || count($params) > 0) {
            echo json_encode(array('error' => 'access denided'));
            exit();
        }
        ClassMessages::set_message_user(1, $this->logged_in, SysClass::ee_cleanString($_REQUEST['message']));
        echo json_encode(array('error' => 'no'));
        exit();
    }

    /**
     * Редактирование или добавление роли пользователя
     * @param array $params
     */
    public function users_role_edit($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $default_data = [
            'role_id' => 0,
            'name' => '',
        ];
        $this->loadModel('m_user_edit');
        $postData = SysClass::ee_cleanArray($_POST);
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $id = 0;
            }
            if (isset($postData['name']) && $postData['name']) {
                if (!$new_id = $this->models['m_user_edit']->update_users_role_data($postData)) {
                    ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.db_registration_error'], 'status' => 'danger']);
                } else {
                    $id = $new_id;
                }
            }
            $get_users_role_data = (int) $id ? $this->models['m_user_edit']->get_users_role_data($id) : $default_data;
            $get_users_role_data = $get_users_role_data ? $get_users_role_data : $default_data;
        } else {
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/users_role_edit/id/');
        }
        /* view */
        $this->view->set('users_role_data', $get_users_role_data);
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_edit_users_role'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = 'Роль пользователей';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_users_role.js" type="text/javascript" /></script>';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Удалённые пользователи
     * @param array $params
     */
    public function deleted_users($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* view */
        $this->getStandardViews();
        $this->view->set('deleted_users_table', $this->get_deleted_users_table());
        $this->view->set('body_view', $this->view->read('v_deleted_users'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = 'Удалённые пользователи';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу удалённых пользователей
     */
    public function get_deleted_users_table() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $postData = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'user_id',
                    'title' => 'ID',
                    'sorted' => 'ASC',
                    'filterable' => false,
                    'width' => 10,
                    'align' => 'center'
                ], [
                    'field' => 'name',
                    'title' => $this->lang['sys.name'],
                    'sorted' => true,
                    'filterable' => true,
                    'width' => 30,
                ], [
                    'field' => 'email',
                    'title' => $this->lang['sys.email'],
                    'sorted' => false,
                    'filterable' => true,
                    'width' => 20,
                    'align' => 'center'
                ], [
                    'field' => 'user_role_text',
                    'title' => $this->lang['sys.role'],
                    'sorted' => true,
                    'filterable' => false,
                    'width' => 20,
                    'align' => 'center'
                ], [
                    'field' => 'last_ip',
                    'title' => $this->lang['sys.last_ip'],
                    'sorted' => false,
                    'filterable' => false,
                    'width' => 10,
                    'align' => 'center'
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
        $filters = [];
        $this->loadModel('m_user_edit');
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $users_array = $this->users->getUsersData($params['order'], $params['where'], $params['start'], $params['limit'], true);
        } else {
            $users_array = $this->users->getUsersData(false, false, false, 25, true);
        }
        foreach ($users_array['data'] as $item) {
            $data_table['rows'][] = [
                'user_id' => $item['user_id'],
                'name' => $item['name'],
                'email' => $item['email'],
                'user_role_text' => $item['user_role_text'],
                'last_ip' => $item['last_ip'],
                'actions' => '<a href="/admin/deleted_user_edit/id/' . $item['user_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>',
            ];
        }
        $data_table['total_rows'] = $users_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('deleted_users_table', $data_table, 'get_deleted_users_table', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('deleted_users_table', $data_table, 'get_deleted_users_table', $filters);
        }
    }

    /**
     * Обрабатывает страницу редактирования удаленного пользователя
     * Эта функция обрабатывает параметры запроса для получения данных удаленного пользователя и отображает страницу для его редактирования
     * Доступ к этой функции имеют только пользователи с определенными правами (1 и 2)
     * Если доступ запрещен или пользователь не найден, происходит перенаправление на главную страницу
     * @param array $params Массив параметров из URL, например, ID пользователя
     * Если ID пользователя не указан или не валиден, используется значение по умолчанию (false)
     * @return void
     */
    public function deleted_user_edit($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $default_data = false;
        $this->loadModel('m_user_edit');
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $user_id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $user_id = 0;
            }
            $get_deleted_user_data = (int) $user_id ? $this->users->getUserData($user_id) : $default_data;
            if (!$get_deleted_user_data) {
                SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/deleted_users');
            }
        } else {
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/deleted_users');
        }
        /* view */
        $this->getStandardViews();
        $this->view->set('deleted_user_data', $get_deleted_user_data);
        $this->view->set('body_view', $this->view->read('v_edit_deleted_users'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = 'Удалённый пользователь';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_deleted_user.js" type="text/javascript" /></script>';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Добавит необходимые стили и скрипты для подключения редактора
     */
    private function addEditorToLayout() {
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . ENV_URL_SITE . '/assets/editor/summernote/summernote-bs5.min.css">';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . ENV_URL_SITE . '/assets/js/plugins/cropper.min.css">';
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/editor/summernote/summernote-bs5.min.js" type="text/javascript"></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/js/plugins/cropper.min.js" type="text/javascript"></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/editor/summernote/plugin/text/text_manipulation.js" type="text/javascript"></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/editor/summernote/plugin/cropper/summernote-cropper.js" type="text/javascript" type="text/javascript"></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/editor/summernote/plugin/cropper/summernote-ext-image.js" type="text/javascript" type="text/javascript"></script>';
        if (ENV_DEF_LANG == 'RU') {
            $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/editor/summernote/lang/summernote-ru-RU.min.js" type="text/javascript"></script>';
        } else {
            $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/editor/summernote/lang/summernote-en-US.min.js" type="text/javascript"></script>';
        }       
    }
    
    /**
     * Подключает стили и скрипты CodeMirror (v5.65.10)
     */
    private function addCodeMirror() {
        // Подключение стилей CodeMirror
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/codemirror.css">';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/theme/monokai.css">';

        // Подключение скриптов CodeMirror
        $this->parameters_layout["add_script"] .= '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/codemirror.js"></script>';
        $this->parameters_layout["add_script"] .= '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/mode/xml/xml.js"></script>';
        $this->parameters_layout["add_script"] .= '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/mode/javascript/javascript.js"></script>';
        $this->parameters_layout["add_script"] .= '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/mode/css/css.js"></script>';
        $this->parameters_layout["add_script"] .= '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/mode/htmlmixed/htmlmixed.js"></script>';
    }   
    
}
