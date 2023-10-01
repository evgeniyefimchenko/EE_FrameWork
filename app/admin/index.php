<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/*
 * Админ-панель
 */

Class Controller_index Extends Controller_Base {

    private $lang = []; // Языковые переменные в рамках этого класса
    private $free_active_status = [1 => 'Не подтверждён', 2 => 'Активен', 3 => 'Заблокирован'];

    /* Подключение traits */
    use messages_trait,
        notifications_trait,
        systems_trait,
        emails_trait,
        categories_trait,
        types_trait,
        entities_trait,
        properties_trait;

    /**
     * Главная страница админ-панели
     */
    public function index($params = []) {
        /* $this->access Массив с перечнем ролей пользователей которым разрешён доступ к странице
         * 1-админ 2-модератор 3-продавец 4-пользователь
         * 100 - все зарегистрированные пользователи
         */
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(200, '/show_login_form?return=admin');
        }
        /* model */
        $this->load_model('m_index', [$this->logged_in]);
        /* get user data - все переменные пользователя доступны в представлениях */
        $user_data = $this->models['m_index']->data;
        $this->get_user_data($user_data);
        /* view */
        $this->get_standart_view();
        /* Отобразить контент согласно уровня доступа */
        if ($user_data['user_role'] == 1) { // Доступ для администратора
            $data_table = $this->get_admin_dashboard_data_table();
            $this->view->set('data_table', $data_table);
            $this->view->set('body_view', $this->view->read('v_dashboard_admin'));
            $this->parameters_layout["add_script"] .= '<script src="/assets/js/plugins/Chart.js" ></script>';
            $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/dashboard_admin.js" type="text/javascript" /></script>';
        }
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - DASHBOARD';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - DASHBOARD';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Формируем данные для таблицы дашборда администратора
     */
    public function get_admin_dashboard_data_table($params = []) {
        $this->access = array(1);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(200, '/show_login_form?return=admin');
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
                    'registration_date' => '2021-01-15'
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
            'total_rows' => 1020  // Общее количество записей (используется для пагинации)
        ];
        $filters = [];
        $filters = [
            'column1' => [
                'type' => 'text', // тип фильтра: текстовое поле
                'id' => "name", // идентификатор фильтра должен совпадать с ['columns']['field']
                'value' => '', // значение по умолчанию
                'label' => 'ФИО' // метка или заголовок фильтра
            ],
            'column2' => [
                'type' => 'select', // тип фильтра: выпадающий список
                'id' => "age",
                'value' => ['option2', 'option1'],
                'label' => 'Возраст',
                'options' => [// опции для выпадающего списка
                    ['value' => 'option1', 'label' => '30+'],
                    ['value' => 'option2', 'label' => '100-']
                ],
                'multiple' => true
            ],
            'column3' => [
                'type' => 'checkbox', // тип фильтра: флажок
                'id' => "address",
                'value' => ['option1', 'option2'],
                'label' => 'Адрес проживания',
                'options' => [// опции для флажка
                    ['value' => 'option1', 'label' => 'Москва', 'id' => 'option1_id'],
                    ['value' => 'option3', 'label' => 'Не москва', 'id' => 'option3_id'],
                    ['value' => 'option4', 'label' => 'Край света', 'id' => 'option4_id'],
                    ['value' => 'option2', 'label' => 'Начало света', 'id' => 'option2_id']
                ]
            ],
            'columnDate' => [
                'type' => 'text',
                'id' => "gender",
                'value' => '',
                'label' => 'Пол'
            ],
            'columnDate1' => [
                'type' => 'date',
                'id' => "registration_date",
                'value' => '2023-08-10', // Пример даты по умолчанию
                'label' => 'Дата регистрации'
            ],
        ];
        $post_data = SysClass::ee_cleanArray($_POST);
        if ($post_data && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') { // AJAX						
            list($params, $filters, $selected_sorting) = Plugins::ee_show_table_prepare_params($post_data, $data_table['columns']);
            echo Plugins::ee_show_table('example_table', $data_table, 'get_admin_dashboard_data_table', $filters, $post_data["page"], $post_data["rows_per_page"], $selected_sorting);
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
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main(200, '/show_login_form?return=admin');
        }
        /* model */
        $this->load_model('m_index', [$this->logged_in]);
        /* get user data - все переменные пользователя доступны в представлениях */
        $user_data = $this->models['m_index']->data;
        $this->get_user_data($user_data);
        /* view */
        $this->get_standart_view();

        $this->view->set('body_view', $this->view->read('v_upgrade'));
        $this->html = $this->view->read('v_dashboard');

        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - UPGRADE';
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - UPGRADE';
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin/upgrade';
        $this->parameters_layout["keywords"] = Sysclass::keywords($this->html);
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Загрузит в представление данные пользователя
     * И языковой массив
     * @param $user_data - Данные пользователя для загрузки
     */
    private function get_user_data($user_data) {
        foreach ($user_data as $name => $val) {
            $this->view->set($name, $val);
        }
        if ($user_data['options']['localize']) { // Проверка на наличие локали в настройках пользователя
            $lang_code = $user_data['options']['localize'];
        } else { // Записываем локаль в опции пользователя
            $lang_code = Session::get('lang');
            $user_data['options']['localize'] = $lang_code;
            $this->load_model('m_index', [$this->logged_in]);
            $this->models['m_index']->set_user_options($this->logged_in, $user_data['options']);
        }
        $lang = include(ENV_SITE_PATH . ENV_PATH_LANG . '/' . $lang_code . '.php');
        if (!is_array($lang))
            SysClass::pre('Языковой файл не подключен: ' . ENV_SITE_PATH . ENV_PATH_LANG . '/' . $lang_code . '.php');
        Session::set('lang', $lang_code);
        $this->view->set('lang', $lang);
        $this->lang = $lang;
    }

    /**
     * Загрузка стандартных представлений для каждой страницы
     */
    private function get_standart_view() {
        $this->view->set('top_bar', $this->view->read('v_top_bar'));
        $this->view->set('main_menu', $this->view->read('v_main_menu'));
        $this->view->set('page_footer', $this->view->read('v_footer'));
        $this->parameters_layout["add_script"] .= '<script>var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'));var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {return new bootstrap.Tooltip(tooltipTriggerEl)});</script>';
    }

    /**
     * Обработка AJAX запросов админ-панели
     * @param array $params - дополнительные параметры запрещены
     * @param POST $post_data - POST параметры update или get
     */
    public function ajax_admin($params = array()) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access) || count($params) > 0) {
            echo '{"error": "access denieded"}';
            exit();
        }
        /* model */
        $this->load_model('m_index', [$this->logged_in]);
        /* get data */
        $user_data = $this->models['m_index']->data;
        $this->get_user_data($user_data);
        /* Read POST data */
        $post_data = SysClass::ee_cleanArray($_POST);
        switch (true) {
            case isset($post_data['update']):
                foreach ($post_data as $key => $value) {
                    if (array_key_exists($key, $user_data['options'])) {
                        $user_data['options'][$key] = $value;
                    }
                }
                echo $this->models['m_index']->set_user_options($this->logged_in, $user_data['options']);
                exit();
            case isset($post_data['get']):
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
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        /* model */
        $this->load_model('m_index', [$this->logged_in]);
        /* get current user data */
        $user_data = $this->models['m_index']->data;
        $this->get_user_data($user_data);
        if (in_array('id', $params)) {                                                       // Пользователь передан получаем данные из базы
            $id = filter_var($params[array_search('id', $params) + 1], FILTER_VALIDATE_INT);
            $get_user_context = is_integer($id) ? $this->models['m_index']->get_user_data($id) : [];
            /* Нельзя посмотреть чужую карточку равной себе роли или выше */
            if (!$id || $this->models['m_index']->data['user_role'] >= $get_user_context['user_role'] && $this->logged_in != $id) {
                SysClass::return_to_main(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
            }
        } else {                                                                            // Не передан ключевой параметр id
            SysClass::return_to_main(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }

        $this->load_model('m_user_edit');
        /* Если не админ и модер и карточка не своя возвращаем */
        if ($this->models['m_index']->data['user_role'] > 2 && $this->logged_in != $id) {
            SysClass::return_to_main(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        /* get data */
        // 1 - на подтверждении, 2 - активен, 3 - блокирован жесткая привязка в БД		
        unset($this->free_active_status[$get_user_context['active']]);
        $get_free_roles = $this->models['m_user_edit']->get_free_roles($get_user_context['user_role']); // Получим свободные роли
        $this->view->set('free_active_status', $this->free_active_status);
        $this->view->set('get_free_roles', $get_free_roles);
        $this->view->set('user_id', $this->logged_in);

        /* view */
        $this->view->set('get_user_context', $get_user_context);
        $this->get_standart_view();
        $this->view->set('body_view', $this->view->read('v_edit_user'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/js/plugins/JQ_mask.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/edit_user.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = $this->lang['sys.user_edit'];
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Аякс изменение рег. данных пользователя
     * Редактирование возможно модераторами
     * или самим пользователем
     * @param $params - ID пользователя для изменения
     * @return json сообщение об ошибке или no
     */
    public function ajax_user_edit($params) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            echo json_encode(array('error' => 'error no access'));
            exit();
        }
        $post_data = SysClass::ee_cleanArray($_POST);
        $id = filter_var($params[array_search('id', $params) + 1], FILTER_VALIDATE_INT);
        /* model */
        $this->load_model('m_index', [$this->logged_in]);
        if ($this->models['m_index']->data['user_role'] > 2 && $this->logged_in != $id) { // Роль меньше модератора или id не текущего пользователя выходим
            echo json_encode(array('error' => 'error no access'));
            exit();
        }
        /* set data user */
        $post_data['phone'] = isset($post_data['phone']) ? preg_replace('/[^0-9+]/', '', $post_data['phone']) : null;
        if ($this->models['m_index']->data['phone'] && $this->models['m_index']->data['user_role'] > 2) {
            unset($post_data['phone']);
        }

        if ($post_data['new'] == '1') {
            if ($this->models['m_index']->registration_new_user($post_data)) {
                $new_id = $this->models['m_index']->get_user_id(trim($post_data['email']));
                echo json_encode(array('error' => 'no', 'id' => $new_id));
                exit();
            } else {
                echo json_encode(array('error' => 'error ajax_user_edit isert user'));
                exit();
            }
        }
        if ($post_data['subscribed']) {
            $post_data['subscribed'] = 1;
        } else {
            $post_data['subscribed'] = 0;
        }
        if ($this->models['m_index']->set_user_data($id, $post_data)) {
            $user_role = $this->models['m_index']->get_user_role($id);
            if (isset($post_data['user_role']) && $post_data['user_role'] != $user_role) { // Сменилась роль пользователя, оповещаем админа и пишем лог
                $mail = new Class_mail();
                $mail->send_mail($this->models['m_index']->get_user_email(1), 'changed status(' . $user_role . ' to ' . $post_data['user_role'] . ') to user', 'User ' . $this->logged_in . ' changed status to user ' . $id);
                SysClass::SetLog('Изменили роль пользователю ID=' . $id . ' с ' . $this->models['m_index']->data['user_role'] . ' на ' . $post_data['user_role'], 'info', $this->logged_in);
            }
            echo json_encode(array('error' => 'no', 'id' => $id));
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
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $post_data = SysClass::ee_remove_empty_values($_POST);
        /* model */
        $this->load_model('m_index', [$this->logged_in]);
        /* get data */
        $user_data = $this->models['m_index']->data;
        $this->get_user_data($user_data);
        /* view */
        $this->get_standart_view();
        $this->view->set('users_table', $this->get_users_table());
        $this->view->set('body_view', $this->view->read('v_users'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->get_path_controller() . '/js/users.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = 'Пользователи';
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу пользователей
     */
    public function get_users_table() {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $this->load_model('m_index', [$this->logged_in]);
        if (!$this->lang['sys.name']) { // Подргужаем языковые переменные
            $user_data = $this->models['m_index']->data;
            $this->get_user_data($user_data);
        }
        $post_data = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'id',
                    'title' => 'ID',
                    'sorted' => 'ASC',
                    'filterable' => false
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
                    'field' => 'reg_date',
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
                    'filterable' => false
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
            'reg_date' => [
                'type' => 'date',
                'id' => "reg_date",
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
        $this->load_model('m_user_edit');
        $get_free_roles = $this->models['m_user_edit']->get_free_roles(0); // Получим все роли
        $filters['user_role']['options'][] = ['value' => '', 'label' => ''];
        foreach ($get_free_roles as $item) {
            $filters['user_role']['options'][] = ['value' => $item['id'], 'label' => $item['name']];
        }
        $filters['active']['options'][] = ['value' => '', 'label' => ''];
        foreach ($this->free_active_status as $k => $v) {
            $filters['active']['options'][] = ['value' => $k, 'label' => $v];
        }

        if ($post_data && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_show_table_prepare_params($post_data, $data_table['columns']);
            $users_array = $this->models['m_index']->get_users_data($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $users_array = $this->models['m_index']->get_users_data(false, false, false, 25);
        }

        foreach ($users_array['data'] as $item) {
            if (!in_array($item['id'], [1, 2])) {
                $html_actions = '<a href="/admin/user_edit/id/' . $item['id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
		. '<a href="/admin/delete_user/id/' . $item['id'] . '"  onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                . 'class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash-alt"></i></a>';
            } else {
                $html_actions = '<a href="/admin/user_edit/id/' . $item['id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>';
            }
            $data_table['rows'][] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'email' => $item['email'],
                'user_role' => $item['user_role_text'],
                'active' => $item['active_text'],
                'reg_date' => date('d.m.Y', strtotime($item['reg_date'])),
                'last_activ' => $item['last_activ'] ? date('d.m.Y', strtotime($item['last_activ'])) : '',
                'actions' => $html_actions
            ];
        }
        $data_table['total_rows'] = $users_array['total_count'];
        if ($post_data) {
            echo Plugins::ee_show_table('users_table', $data_table, 'get_users_table', $filters, $post_data["page"], $post_data["rows_per_page"], $selected_sorting);
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
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        /* model */
        $this->load_model('m_index', [$this->logged_in]);
        /* get data */
        $user_data = $this->models['m_index']->data;
        $this->get_user_data($user_data);
        /* view */
        $this->get_standart_view();
        $this->view->set('users_roles_table', $this->get_users_roles_table());
        $this->view->set('body_view', $this->view->read('v_users_roles'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = 'Роли пользователей';
        $this->show_layout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу ролей пользователей
     */
    public function get_users_roles_table() {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $this->load_model('m_index', [$this->logged_in]);
        if (!$this->lang['sys.name']) { // Подргужаем языковые переменные
            $user_data = $this->models['m_index']->data;
            $this->get_user_data($user_data);
        }
        $post_data = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'id',
                    'title' => 'ID',
                    'sorted' => 'ASC',
                    'filterable' => false
                ], [
                    'field' => 'name',
                    'title' => $this->lang['sys.name'],
                    'sorted' => true,
                    'filterable' => true
                ], [
                    'field' => 'actions',
                    'title' => $this->lang['sys.action'],
                    'sorted' => false,
                    'filterable' => false
                ],
            ]
        ];        
        $this->load_model('m_user_edit');
        if ($post_data && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_show_table_prepare_params($post_data, $data_table['columns']);
            $users_array = $this->models['m_user_edit']->get_users_roles_data($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $users_array = $this->models['m_user_edit']->get_users_roles_data(false, false, false, 25);
        }

        foreach ($users_array['data'] as $item) {
            $data_table['rows'][] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'actions' => '<a href="/admin/users_role_edit/id/' . $item['id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>
				<a href="/admin/users_role_dell/id/' . $item['id'] . '" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash-alt"></i></a>'
            ];
        }
        $data_table['total_rows'] = $users_array['total_count'];
        if ($post_data) {
            echo Plugins::ee_show_table('users_roles_table', $data_table, 'get_users_roles_table', $filters, $post_data["page"], $post_data["rows_per_page"], $selected_sorting);
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
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        if (in_array('id', $params)) {
            $id = filter_var($params[array_search('id', $params) + 1], FILTER_VALIDATE_INT);
            if (in_array($id, [1, 2, 3, 4, 8])) {
                    $notifications = new Class_notifications();
                    $notifications->add_notification_user($this->logged_in, ['text' => 'Невозможно удалить системные роли!', 'status' => 'danger']);                    
            } else {
                $this->load_model('m_user_edit');
                $this->models['m_user_edit']->users_role_dell($id);
            }
        }
        SysClass::return_to_main(200, '/admin/users_roles');
    }
    
    /**
     * Редактирование или добавление роли пользователя
     * @param array $params
     */
    public function users_role_edit($params = []) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        if (in_array('id', $params)) {
            $id = filter_var($params[array_search('id', $params) + 1], FILTER_VALIDATE_INT);
        }
        // TODO
    }
    
    /**
     * Удалённые пользователи
     * @param array $params
     */    
    public function deleted_users($params = []) {
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        // TODO
    }
    
    /**
     * Установит флаг удалённого пользователя
     * @param type $params
     */
    public function delete_user($params = []) {        
        $this->access = array(1, 2);
        if (!SysClass::get_access_user($this->logged_in, $this->access)) {
            SysClass::return_to_main();
            exit();
        }
        $notifications = new Class_notifications();
        if (in_array('id', $params)) {            
            $id = filter_var($params[array_search('id', $params) + 1], FILTER_VALIDATE_INT);
            if (in_array($id, [1, 2])) {
                $notifications->add_notification_user($this->logged_in, ['text' => 'Невозможно удалить системные роли!', 'status' => 'danger']);                    
            } else {
                $this->load_model('m_user_edit');
                if (!$this->models['m_user_edit']->delete_user($id)) {                    
                    $notifications->add_notification_user($this->logged_in, ['text' => 'Ошибка удаления пользователя id=' . $id, 'status' => 'danger']);                    
                } else {
                    $notifications->add_notification_user($this->logged_in, ['text' => 'Помечен удалённым id=' . $id, 'status' => 'info']);
                }
            }            
        } else {
            $notifications->add_notification_user($this->logged_in, ['text' => 'Нет обязательного параметра id', 'status' => 'danger']); 
        }
        SysClass::return_to_main(200, '/admin/users');
    }
    
    /**
     * Отправит сообщение администратору AJAX
     * @param array $params
     */
    public function send_message_admin($params = []) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access) || count($params) > 0) {
            echo json_encode(array('error' => 'access denided'));
            exit();
        }
        $class_messages = new Class_messages();
        $this->load_model('m_index', [$this->logged_in]);
        $class_messages->set_message_user(1, $this->logged_in, SysClass::ee_cleanString($_REQUEST['message']));
        echo json_encode(array('error' => 'no'));
        exit();
    }

}
