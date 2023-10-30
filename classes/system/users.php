<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Класс работы с пользователями
 * Является родителем для моделей главной страницы и административной панели
 */
Class Users {

    const BASE_OPTIONS_USER = '{"localize": "", "user_logo_img":"uploads/images/avatars/face-0-lite.jpg","user_img":"/uploads/images/avatars/face-0.jpg","skin":"skin-default","notifications":[{}]}';

    private $lang = []; // Языковые переменные в рамках этого класса
    public $data;

    public function __construct($param = []) {
        if (count($param)) {
            $user_id = array_shift($param);
        } else {
            $user_id = 0;
        }
        $this->data = $this->get_user_data($user_id);
    }

    /**
     * Запишет языковые переменные
     * Массив должен быть передан из экземпляра класса использующего функции Class Users
     */
    public function set_lang($lang) {
        $this->lang = $lang;
    }

    /**
     * Возвращает подготовленные данные пользователя
     * личные + настройки интерфейса
     * @param id - идентификатор пользователя в базе
     * @return именованный массив
     */
    public function get_user_data($id = 0) {
        $res_array = [];
        if (SysClass::connect_db_exists()) {
            if (SafeMySQL::gi()->query('show tables like ?s', Constants::USERS_TABLE)->{"num_rows"} > 0) {
                $sql_user = 'SELECT * FROM ?n WHERE `id` = ?i AND `deleted` = 0';
                $res_array = SafeMySQL::gi()->getRow($sql_user, Constants::USERS_TABLE, (int) $id);
                if ($res_array) {
                    $class_messages = new Class_messages();
                    $res_array['count_unread_messages'] = $class_messages->get_count_unread_messages($id);
                    $res_array['count_messages'] = $class_messages->get_count_messages($id);
                    $res_array['messages'] = $res_array['count_unread_messages'] ? $class_messages->get_messages_user($id) : [];
                    unset($class_messages);
                    $res_array['user_role_text'] = $this->get_text_role($res_array['user_role']);
                    $res_array['active_text'] = $this->get_text_active($res_array['active']);
                    $res_array['subscribed_text'] = $res_array['subscribed'] > 0 ? 'Подписан' : 'Не подписан';
                    $res_array['options'] = $this->get_user_options($id);
                } else { // Первичное заполнение полей для незарегистрированного пользователя
                    $res_array['new_user'] = 1;
                    $res_array['user_role'] = 4;
                    $res_array['active'] = 1;
                    $res_array['subscribed'] = 1;
                    $res_array['options'] = json_decode(self::BASE_OPTIONS_USER, true);
                    $res_array['user_role_text'] = $this->get_text_role($res_array['user_role']);
                    $res_array['active_text'] = $this->get_text_active($res_array['active']);
                    $res_array['subscribed_text'] = 'Подписан';
                }
            } else { // Нет таблицы users
                $this->create_tables(); //создаём необходимый набор таблиц в БД и первого пользователя с ролью администратора
                $this->registration_new_user(array('name' => 'admin', 'email' => 'test@test.com', 'active' => '2', 'user_role' => '1', 'subscribed' => '0', 'comment' => 'Смените пароль администратора', 'pwd' => 'admin'), true);
                $this->registration_new_user(array('name' => 'moderator', 'email' => 'test_moderator@test.com', 'active' => '2', 'user_role' => '2', 'subscribed' => '0', 'comment' => 'Смените пароль модератора', 'pwd' => 'moderator'), true);
                if (ENV_TEST) {
                    echo 'База данных успешно развёрнута!';
                }
            }
        }
        return $res_array;
    }

    /**
     * Вернёт имя поьзователя
     */
    public function get_user_name($id) {
        $sql = 'SELECT name FROM ?n WHERE id = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $id);
    }

    /**
     * Записывает новые данные пользователя
     * @param id - id пользователя
     * @param fields - именованный массив полей для изменения, имена полей являются ключами
     * Уровень доступа из таблицы user_roles определяет набор полей для изменения
     * @return - boolean
     */
    public function set_user_data($id = 0, $fields) {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            if (ENV_TEST) {
                die('Неверный формат ID пользователя.');
            }
            return 0;
        }
        if ($this->data['user_role'] > 2 && $this->data['id'] != $id) { // Нет полномочий изменения данных пользователю
            if (ENV_TEST) {
                die('Нет полномочий изменения данных пользователю.');
            }
            return 0;
        }
        if ($this->data['id'] == $id) { // Сам пользователь
            $fields = SafeMySQL::gi()->filterArray($fields, array('name', 'email', 'phone', 'subscribed', 'comment', 'pwd'));
        } else { // Модераторы 
            $fields = SafeMySQL::gi()->filterArray($fields, array('name', 'email', 'phone', 'active', 'user_role', 'subscribed', 'comment', 'pwd', 'element_id', 'element_name'));
        }
        $fields = SysClass::ee_remove_empty_values(array_map('trim', $fields));
        if (!$fields)
            return 0;
        if (strlen($fields['pwd']) >= 5) {
            $this->set_user_password($id, $fields['email'], $fields['pwd']);
        }
        unset($fields['pwd']);
        $sql_user = "UPDATE ?n SET ?u, `up_date` = now() WHERE id = ?i";
        SafeMySQL::gi()->query($sql_user, Constants::USERS_TABLE, $fields, $id);
        if (ENV_LOG) {
            SysClass::SetLog('Обновление данных пользователю ID=' . $id, 'info', $this->data['id']);
        }
        return 1;
    }

    /**
     * Получить данные пользователей.
     * @param array  $order Ассоциативный массив для сортировки в формате 'поле' => 'направление' (по умолчанию 'id' => 'ASC').
     * @param array|null $where Условия фильтрации в формате 'поле' => ['оператор', 'значение'].
     * @param int    $start Начальный индекс для лимитации (по умолчанию 0).
     * @param int    $limit Количество строк для извлечения (по умолчанию 100).
     * @return array Массив с данными пользователей.
     */
    public function get_users_data($order = 'id ASC', $where = NULL, $start = 0, $limit = 100) {
        $orderString = $order ? $order : 'id ASC';
        $whereString = $where ? $where . ' AND deleted = 0' : 'WHERE deleted = 0';
        $start = $start ? $start : 0;
        if ($orderString) {
            $sql_users = "SELECT `id` FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_users = "SELECT `id` FROM ?n $whereString LIMIT ?i, ?i";
        }
        $res_array = SafeMySQL::gi()->getAll($sql_users, Constants::USERS_TABLE, $start, $limit);
        $sqlExecutionTime = microtime(true) - $startTime;
        $res = [];
        foreach ($res_array as $user) {
            $res[] = $this->get_user_data($user['id']);
        }
        $loopExecutionTime = microtime(true) - $startTime;
        // Получаем общее количество записей
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::USERS_TABLE);
        $countExecutionTime = microtime(true) - $startTime;
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Возвращает персональные настройки пользователя
     * настройки интерфейса, аватар, фото
     * @param id - идентификатор пользователя в БД
     * @return именованный массив
     */
    public function get_user_options($id) {
        $sql_users = 'SELECT `options` FROM ?n WHERE `user_id` = ?i';
        $options = SafeMySQL::gi()->getOne($sql_users, Constants::USERS_DATA_TABLE, $id);
        if (!$options) { // Для подстраховки
            $this->set_user_options($id);
            $options = self::BASE_OPTIONS_USER;
        }
        return json_decode($options, true);
    }

    /**
     * Записывает персональные настройки пользователя
     * настройки интерфейса, аватар, фото
     * @param int $user_id - идентификатор пользователя в БД
     * @param array $options - массив с настройками
     * @return true
     */
    public function set_user_options($user_id, $options = '') {
        if (is_array($options)) {
            $options = json_encode($options);
        } else {
            $options = self::BASE_OPTIONS_USER;
        }
        if ($this->isset_options_user($user_id) > 0) {
            $sql = 'UPDATE ?n SET `options` = ?s WHERE `user_id` = ?i';
        } else {
            $sql = 'INSERT INTO ?n SET `options` = ?s, `user_id` = ?i';
        }
        return SafeMySQL::gi()->query($sql, Constants::USERS_DATA_TABLE, $options, $user_id);
    }

    /**
     * Имеются ли настройки у пользователя
     * @param int $user_id - ID пользователя
     * @return boolean
     */
    public function isset_options_user($user_id) {
        $sql = 'SELECT 1 FROM ?n WHERE `user_id` = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_DATA_TABLE, $user_id);
    }

    /**
     * Авторизует пользователя после проверки аргументов
     * @email - почта
     * @psw - нешифрованный пароль
     * @force_login - флаг авторизации без проверки аргументов, используется для автологина
     * @return string 
     */
    public function confirm_user($email, $psw, $force_login = false) {
        $res = '';
        $user_row = SafeMySQL::gi()->getRow('SELECT `id`, `active`, `pwd` FROM ?n WHERE `email` = ?s', Constants::USERS_TABLE, $email);
        if ($user_row['active'] == 2 || $force_login) {
            if (password_verify($psw, $user_row['pwd']) || $force_login) {
                $add_query = '';
                if ($force_login) {
                    $add_query = ' active = 2, ';
                    $res = $this->lang['sys.welcome'];
                }
                $sql = 'UPDATE ?n SET' . $add_query . '`last_ip` = ?s, `last_activ` = ?s, `session` = ?s WHERE `id` = ?i';
                $ip = SysClass::client_ip();
                $last_date = date("Y-m-d H:i:s", time());
                $hash_login = $user_row['id'];
                $session = password_hash($hash_login, PASSWORD_DEFAULT);
                if (ENV_AUTH_USER == 0) {
                    Session::set('user_id', $user_row['id']);
                }
                if (ENV_AUTH_USER === 2) {
                    Cookies::set('user_id', $user_row['id']);
                    Cookies::set('user_session', $session);
                }
                Session::set('user_session', $session);
                SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, $ip, $last_date, $session, $user_row['id']);
            } else {
                $res = $this->lang['sys.the_password_was_not_verified'];
            }
        } elseif ($user_row['active'] == 1) {
            $res = $this->lang['sys.you_have_not_verified_your_email'];
        } elseif ($user_row['active'] == 3) {
            $res = $this->lang['sys.account_is_blocked'];
        } else {
            $res = $this->lang['sys.no_such_data_was_found'];
        }
        return $res;
    }

    /**
     * Регистрирует пользователя и отправляет сообщение со ссылкой для заполнения персональных данных 
     * @email - почта
     * @password - нешифрованный пароль
     * @return boolean
     * ВАЖНО! Роль пользователя устанавливается по умолчанию в таблице БД
     */
    public function registration_users($email, $password) {
        $newpassword = password_hash($password, PASSWORD_DEFAULT);
        $sql = 'INSERT INTO ?n SET `name` = ?s, `email` = ?s, `pwd` = ?s, `last_ip` = ?s';
        if (SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, $email, $email, $newpassword, $_SERVER['REMOTE_ADDR']) && ENV_LOG) {
            SysClass::SetLog('Регистрация ' . $email . ' c ' . $password . ' успех', 'info', 8);
        }
        $sql = 'SELECT MAX(`id`) FROM ?n';
        $id_user = SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE);
        if (ENV_LOG && $id_user) {
            SysClass::SetLog('Зарегистрирован новый пользователь id=' . $id_user, 'info', $this->data['id']);
        }
        $this->set_user_options($id_user); // Заполнить первичные данные из базы по шаблону
        $class_messages = new Class_messages();
        $class_messages->set_message_user($id_user, 8, 'Fill in your personal information <a href="' . ENV_URL_SITE . '/admin/user_edit/id/' . $id_user . '">link</a>', 'info');
        return true;
    }

    /**
     * Регистрация нового пользователя модератором
     * по сути краткая регистрация пользователя без отсылки письма
     * @param $param - нефильтрованный POST массив
     * @param $flag - проверка прав пользователя
     * @return boolean результат операции
     */
    public function registration_new_user($fields, $flag = false) {
        if ($this->data['user_role'] > 2 && !$flag) {
            return 0;
        }
        $fields = SafeMySQL::gi()->filterArray($fields, array('name', 'email', 'phone', 'active', 'user_role', 'subscribed', 'comment', 'pwd'));
        $fields = array_map('trim', $fields);
        if ($this->get_email_exist($fields['email'])) {
            return 0;
        }
        $sql = 'INSERT INTO ?n SET ?u';
        SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, $fields);
        $id_user = SafeMySQL::gi()->insertId();
        if (ENV_LOG && $id_user) {
            SysClass::SetLog('Зарегистрирован новый пользователь id=' . $id_user, 'info', $this->data['id']);
        }
        $this->set_user_password($id_user, $fields['email'], $fields['pwd']);
        $this->set_user_options($id_user); // Заполнить первичные данные из базы по шаблону
        $class_messages = new Class_messages();
        $class_messages->set_message_user($id_user, 8, 'Заполните свой профиль <a href="' . ENV_URL_SITE . '/admin/user_edit/id/' . $id_user . '">тут</a>', 'info');
        return 1;
    }

    /**
     * Проверка существования профиля админа
     * Если не существует то будет создан
     */
    public function get_admin_profile() {
        $sql = 'SELECT 1 FROM ?n WHERE user_role = 1 LIMIT 1';
        if (!SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE)) {
            $this->registration_new_user(array('name' => 'admin', 'email' => 'test@test.com', 'active' => '2', 'user_role' => '1', 'subscribed' => '1', 'comment' => 'Смените пароль администратора', 'pwd' => 'admin'), true);
        }
    }

    /**
     * Устанавливает пароль пользователю 
     * @id - идентификатор пользователя
     * @email - почта обязательное поле!!!
     * @password - нешифрованный пароль
     * Если password пустой то генерирует случайный
     * @return пустое значение или сгенерированный пароль
     */
    public function set_user_password($id = 0, $email, $password = '') {
        if (!$email) {
            return 0;
        }
        if (!$id) {
            $id = $this->get_user_id_by_email($email);
        }
        if (!$password) {
            $chars = "qazxswedcvfrtgbnhyujmkiolp1234567890QAZXSWEDCVFRTGBNHYUJMKIOLP";
            $max = 10;
            $size = StrLen($chars) - 1;
            while ($max--)
                $password .= $chars[rand(0, $size)];
        }

        $newsword = password_hash($password, PASSWORD_DEFAULT);
        $sql = 'UPDATE ?n SET `pwd` = ?s WHERE `id` = ?i';
        $res_q = SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, $newsword, $id);
        if (ENV_LOG) {
            SysClass::SetLog('Пароль для ' . $email . ' обновлён на ' . $password, 'info');
        }

        return $password;
    }

    /* Получение данных пользователей */

    /**
     * Вернёт ID статуса пользователя по его ID
     */
    public function get_user_stat($id) { // Статус пользователя 1 - на подтверждении, 2 - активен,  3 - блокирован
        $sql = 'SELECT `active` FROM ?n WHERE `id` = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $id);
    }

    /**
     * Вернёт ID роли пользователя по его ID
     */
    public function get_user_role($id) { // Роль пользователя 1-админ 2-модератор 3-менеджер 4-пользователь 8-система 
        $sql = 'SELECT `user_role` FROM ?n WHERE `id` = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $id);
    }

    /**
     * Вернёт название роли по её ID
     */
    public function get_text_role($id) {
        $sql = 'SELECT `name` FROM ?n WHERE `id` = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_ROLES_TABLE, $id);
    }

    /**
     * Вернёт название статуса по его ID
     */
    public function get_text_active($id) {
        if (count($this->lang) == 0) {
            $lang_code = Session::get('lang') ? Session::get('lang') : 'ru';
            $path_file = ENV_SITE_PATH . ENV_PATH_LANG . '/' . $lang_code . '.php';
            if (file_exists($path_file)) {
                include($path_file);
            }
            $this->lang = $lang;
        }
        $res = $this->lang['sys.unknown'];
        switch ($id) {
            case 1:
                $res = $this->lang['sys.not_confirmed'];
                break;
            case 2:
                $res = $this->lang['sys.active'];
                break;
            case 3:
                $res = $this->lang['sys.blocked'];
                break;
        }
        return $res;
    }

    /**
     * Поиск ID пользователя по почте
     */
    public function get_user_id_by_email($email) {
        $sql = 'SELECT `id` FROM ?n WHERE `email` LIKE ?s';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $email);
    }

    /**
     * Вернёт email по user id
     * @param int $user_id
     * @return str
     */
    public function get_user_email($user_id) {
        $sql = 'SELECT `email` FROM ?n WHERE `id` = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $user_id);
    }

    /**
     * Проверка существования почты в БД
     */
    public function get_email_exist($email) {
        $sql = 'SELECT 1 FROM ?n WHERE `email` = ?s AND deleted = 0';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $email);
    }

    /**
     * Меняет и высылает на почту пользователя новый пароль к сайту     
     */
    public function send_recovery_password($email) {
        $password = $this->set_user_password(0, $email, 0);
        $message = 'Вы запросили восстановление пароля для сайта ' . ENV_SITE_NAME . ' </br>Если это были не Вы, то просто проигнорируйте данное сообщение.';
        $message .= '</br>Ваш новый пароль ' . $password . ' измените его в личном кабинете, при первой возможности.';

        $m = new Mail('', '', true);
        $m->From(ENV_SITE_EMAIL);
        $m->To($email);
        $m->ReplyTo($this->lang['sys.admin'] . ';' . ENV_ADMIN_EMAIL);
        $m->Subject($this->lang['sys.restore_password_process'] . ' ' . ENV_SITE_NAME);
        $m->Body($message, "html");
        if (ENV_SMTP) {
            $m->smtp_on(ENV_SMTP_SERVER, ENV_SMTP_LOGIN, ENV_SMTP_PASSWORD, ENV_SMTP_PORT, 15);
        }
        if ($m->Send()) {
            if (ENV_LOG) {
                SysClass::SetLog('Отправлен новый пароль на ' . $email, 'info');
            }
            return true;
        } else {
            if (ENV_LOG) {
                SysClass::SetLog('Отправка пароля на ' . $email . ' завершилась неудачей!', 'error');
            }
            return false;
        }
    }

    /**
     * Отправляет ссылку для активации пользователя
     */
    public function send_register_code($email) {
        $acivation_code = base64_encode(password_hash($email, PASSWORD_DEFAULT));
        $activation_link = ENV_URL_SITE . '/activation/' . $acivation_code . '/' . $email;
        $mail = new Class_mail();
        $res_mail = $mail->send_mail($email, 'Регистрация на сайте ' . ENV_SITE_NAME, 'Вы зарегистрировались на сайте ' . ENV_SITE_NAME . ', перейдите по <a href="' . $activation_link . '" target = "_blank">ссылке</a> для активации Вашего аккаунта.</br>
                           Если Вы не делали этого то просто проигнорируйте это сообщение.</br>Но всё же, вдруг мы сможем помочь Вам или быть интересными!
                                   </br></br>С уважением, сайт <a href="' . ENV_URL_SITE . '">' . ENV_SITE_NAME . '</a>');
        if ($res_mail !== TRUE) {
            return false;
        } else {
            if (ENV_LOG) {
                SysClass::SetLog('Письмо на ' . $email . ' с кодом ' . $acivation_code . ' отправлено.', 'success');
            }
            $sql = 'INSERT INTO ?n SET `user_id` = ?i,`email` = ?s, `code` = ?s, `stop_time` = ?s';
            SafeMySQL::gi()->query($sql, Constants::USERS_ACTIVATION_TABLE, $this->get_user_id_by_email($email), $email, $acivation_code, date("Y-m-d H:i:s", time() + ENV_TIME_ACTIVATION));
            return true;
        }
    }

    /**
     * Удаление регистрационного кода после активации
     */
    public function dell_activation_code($email, $code) {
        $sql = 'SELECT `stop_time` FROM ?n WHERE `email` LIKE ?s AND code LIKE ?s';
        $query = SafeMySQL::gi()->getOne($sql, Constants::USERS_ACTIVATION_TABLE, $email, $code);
        if ($query > date("Y-m-d H:i:s")) {
            $sql = 'DELETE FROM ?n WHERE `email` = ?s';
            SafeMySQL::gi()->query($sql, Constants::USERS_ACTIVATION_TABLE, $email);
            $sql = 'UPDATE ?n SET `active` = 2 WHERE `id` = ?i AND `active` = 1';
            SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, $this->get_user_id_by_email($email));
            return true;
        } else {  // Кода нет в таблице или он просрочен очищаем все данные
            $this->dell_user_data($this->get_user_id_by_email($email));
            return false;
        }
    }

    /**
     * Создаёт необходимые таблицы в БД
     * Если нет подключения то вернёт false
     */
    public function create_tables() {
        SafeMySQL::gi()->query("START TRANSACTION");
        SafeMySQL::gi()->query("SET FOREIGN_KEY_CHECKS=1");  // включаем проверку внешних ключей
        try {
            /* Пользователи */
            $create_table = "CREATE TABLE IF NOT EXISTS ?n (
            `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` char(255) NOT NULL DEFAULT 'Merchant_ID',
            `email` char(255) NOT NULL,
            `pwd` varchar(255) NOT NULL,
            `active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 - на подтверждении, 2 - активен,  3 - блокирован',
            `user_role` tinyint(2) NOT NULL DEFAULT '4' COMMENT 'таблица user_roles',
            `last_ip` char(20) DEFAULT NULL,
            `subscribed` tinyint(1) DEFAULT '1' COMMENT 'подписка на рассылку',
            `reg_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'дата регистрации',
            `last_activ` datetime DEFAULT NULL COMMENT 'дата крайней активности',
            `up_date` datetime NOT NULL COMMENT 'дата обновления инф.',
            `phone` varchar(255) NOT NULL,
            `session` varchar(255) NOT NULL,
            `comment` varchar(255) NOT NULL COMMENT 'Комментарий или дивиз пользователя',
            `deleted` BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Флаг удаленного пользователя',
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=innodb DEFAULT CHARSET=utf8 COMMENT='Пользователи сайта';";
            SafeMySQL::gi()->query($create_table, Constants::USERS_TABLE);
            /* Роли пользователей */
            $create_table = "CREATE TABLE IF NOT EXISTS ?n (
                                                   `id` tinyint(2) UNSIGNED NOT NULL AUTO_INCREMENT,
                                                   `name` varchar(255) NOT NULL,
                                                  PRIMARY KEY (`id`)
						) ENGINE=innodb DEFAULT CHARSET=utf8 COMMENT='1-админ 2-модератор 3-пользователь 8-система';";
            SafeMySQL::gi()->query($create_table, Constants::USERS_ROLES_TABLE);
            /* Добавим стандартные роли */
            $insert_data = "INSERT INTO ?n (`id`, `name`) VALUES
						(1, 'Администратор'),
						(2, 'Модератор'),
						(3, 'Менеджер'),
						(4, 'Пользователь'),						
						(8, 'Система')
						ON DUPLICATE KEY UPDATE `id` = VALUES(`id`), `name` = VALUES(`name`);";
            SafeMySQL::gi()->query($insert_data, Constants::USERS_ROLES_TABLE);
            /* Данные пользователя */
            $create_table = "CREATE TABLE IF NOT EXISTS ?n (
						  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
						  `user_id` int(11) UNSIGNED NOT NULL,
						  `up_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время любого изменения данных',
						  `options` text NOT NULL COMMENT 'Настройки интерфейса пользователя',
						  PRIMARY KEY (`id`)
						) ENGINE=innodb DEFAULT CHARSET=utf8;";
            SafeMySQL::gi()->query($create_table, Constants::USERS_DATA_TABLE);
            /* Сообщения пользователя */
            $create_table = "CREATE TABLE IF NOT EXISTS ?n (
						  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
						  `user_id` int(11) UNSIGNED NOT NULL,
						  `author_id` int(11) UNSIGNED NOT NULL,
						  `message_text` varchar(1000) NOT NULL,
						  `date_create` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
						  `date_read` datetime DEFAULT NULL,
						  `status` varchar(10) NOT NULL DEFAULT 'info',
						  PRIMARY KEY (`id`)
						) ENGINE=innodb DEFAULT CHARSET=utf8;";
            SafeMySQL::gi()->query($create_table, Constants::USERS_MESSAGE_TABLE);
            /* Таблица ссылок для активации новых пользователей */
            $create_table = "CREATE TABLE IF NOT EXISTS ?n (
                                                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                                                `user_id` int(11) NOT NULL,
                                                `email` varchar(255) NOT NULL,
                                                `code` varchar(255) NOT NULL,
                                                `reg_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                                `stop_time` datetime NOT NULL,
                                                PRIMARY KEY (`id`)
                                              ) ENGINE=innodb DEFAULT CHARSET=utf8 COMMENT='Коды активации для зарегистрировавшихся пользователей';";
            SafeMySQL::gi()->query($create_table, Constants::USERS_ACTIVATION_TABLE);
            // Создание таблицы типов категорий
            $create_types_table = "CREATE TABLE IF NOT EXISTS ?n (
                        type_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(255) NOT NULL UNIQUE,
                        description VARCHAR(255),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2'
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения типов сущностей и категорий';";
            SafeMySQL::gi()->query($create_types_table, Constants::CATEGORIES_TYPES_TABLE);
            // Создание таблицы категорий
            // Шаг 1: Создание таблицы без внешних ключей
            $create_categories_table = "CREATE TABLE IF NOT EXISTS ?n (
            category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            type_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            short_description VARCHAR(255),
            parent_id INT UNSIGNED,
            status ENUM('active', 'hidden', 'disabled') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
            INDEX (type_id),
            INDEX (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения категорий сущностей';";
            SafeMySQL::gi()->query($create_categories_table, Constants::CATEGORIES_TABLE);
            // Шаг 2: Добавление внешних ключей
            $add_foreign_keys = "ALTER TABLE ?n 
            ADD FOREIGN KEY (type_id) REFERENCES ?n(type_id),
            ADD FOREIGN KEY (parent_id) REFERENCES ?n(category_id);";
            SafeMySQL::gi()->query($add_foreign_keys, Constants::CATEGORIES_TABLE, Constants::CATEGORIES_TYPES_TABLE, Constants::CATEGORIES_TABLE);
            // Создание таблицы сущностей
            $create_entities_table = "CREATE TABLE IF NOT EXISTS ?n (
			entity_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			parent_entity_id INT UNSIGNED NULL,
			category_id INT UNSIGNED,
                        status ENUM('active', 'hidden', 'disabled') NOT NULL DEFAULT 'active',
			title VARCHAR(255) NOT NULL,
			short_description VARCHAR(255),
			description TEXT,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
			FOREIGN KEY (category_id) REFERENCES ?n(category_id),
			INDEX (category_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения сущностей (продукты или страницы)';";
            SafeMySQL::gi()->query($create_entities_table, Constants::ENTITIES_TABLE, Constants::CATEGORIES_TABLE);
            // Таблица для хранения типов свойств
            $create_property_types_table = "CREATE TABLE IF NOT EXISTS ?n (
			type_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			name VARCHAR(255) NOT NULL,
			status ENUM('active', 'hidden', 'disabled') NOT NULL DEFAULT 'active',
                        fields JSON NOT NULL,
			description VARCHAR(255),
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2'
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения типов свойств';";
            SafeMySQL::gi()->query($create_property_types_table, Constants::PROPERTY_TYPES_TABLE);
            // Таблица для хранения свойств
            $create_properties_table = "CREATE TABLE IF NOT EXISTS ?n (
			property_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			type_id INT UNSIGNED NOT NULL,
			name VARCHAR(255) NOT NULL,
                        status ENUM('active', 'hidden', 'disabled') NOT NULL DEFAULT 'active',
                        default_values JSON NOT NULL,
			is_multiple BOOLEAN NOT NULL,
			is_required BOOLEAN NOT NULL,
			description VARCHAR(255),
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
			FOREIGN KEY (type_id) REFERENCES ?n(type_id),
			INDEX (type_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения свойств';";
            SafeMySQL::gi()->query($create_properties_table, Constants::PROPERTIES_TABLE, Constants::PROPERTY_TYPES_TABLE);
            // Таблица для хранения значений свойств в формате JSON
            $create_property_values_table = "CREATE TABLE IF NOT EXISTS ?n (
			value_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			entity_id INT UNSIGNED NOT NULL,
			property_id INT UNSIGNED NOT NULL,
			entity_type ENUM('category', 'entity') NOT NULL,
			value JSON NOT NULL,			
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
			FOREIGN KEY (property_id) REFERENCES ?n(property_id),
			INDEX (property_id),
			INDEX (entity_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения значений свойств в формате JSON';";
            SafeMySQL::gi()->query($create_property_values_table, Constants::PROPERTY_VALUES_TABLE, Constants::PROPERTIES_TABLE);                        
            // Таблица для хранения общей информации о наборе свойств.
            $create_property_sets_table = "
            CREATE TABLE IF NOT EXISTS ?n (
                set_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения наборов свойств';";
            SafeMySQL::gi()->query($create_property_sets_table, Constants::PROPERTY_SETS_TABLE);
            // Таблица для представления отношения многие ко многим между типами категорий и наборами свойств.
            $create_category_type_to_set_table = "
            CREATE TABLE IF NOT EXISTS ?n (
                type_id INT UNSIGNED NOT NULL,
                set_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (type_id, set_id),
                FOREIGN KEY (type_id) REFERENCES ?n(type_id),
                FOREIGN KEY (set_id) REFERENCES ?n(set_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для связи типов категорий и наборов свойств';";
            SafeMySQL::gi()->query($create_category_type_to_set_table, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, Constants::CATEGORIES_TYPES_TABLE, Constants::PROPERTY_SETS_TABLE);
            // Таблица для представления отношения многие ко многим между наборами свойств и свойствами.
            $create_set_to_properties_table = "
            CREATE TABLE IF NOT EXISTS ?n (
                set_id INT UNSIGNED NOT NULL,
                property_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (set_id, property_id),
                FOREIGN KEY (set_id) REFERENCES ?n(set_id),
                FOREIGN KEY (property_id) REFERENCES ?n(property_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для связи наборов свойств и свойств';";
            SafeMySQL::gi()->query($create_set_to_properties_table, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, Constants::PROPERTY_SETS_TABLE, Constants::PROPERTIES_TABLE);
            // Таблица поиска по сайту
            $create_search_contents_table = "CREATE TABLE IF NOT EXISTS ?n (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                entity_id INT UNSIGNED NOT NULL,
                entity_type_table VARCHAR(255) NOT NULL,
                area CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'Локация поиска A-админпанель, C-клиетская часть',
                full_search_content TEXT NOT NULL,
                short_search_content TEXT NOT NULL,
                language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
                relevance_score TINYINT UNSIGNED DEFAULT 0,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FULLTEXT(full_search_content, short_search_content),
                INDEX (entity_id, entity_type_table, area, language_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для глобального поиска по сайту';";

            SafeMySQL::gi()->query($create_search_contents_table, Constants::SEARCH_CONTENTS_TABLE);            
            
            // Запись предварительных данных в БД
            // Добавление основных типов категорий
            $types = [
                ['name' => 'Товары', 'description' => 'Для хранения информации о товарах'],
                ['name' => 'Страницы', 'description' => 'Для хранения информации о страницах сайта'],
                ['name' => 'Блог', 'description' => 'Для хранения блогов и статей'],
                ['name' => 'Комментарии', 'description' => 'Для хранения комментариев пользователей'],
            ];
            foreach ($types as $type) {
                SafeMySQL::gi()->query(
                        "INSERT INTO ?n (name, description) VALUES (?s, ?s) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)",
                        Constants::CATEGORIES_TYPES_TABLE,
                        $type['name'],
                        $type['description']
                );
            }
            // Добавление основных типов свойств
            $property_types = [
                ['name' => 'Строка', 'description' => 'Тип свойства для хранения строковых данных', 'status' => 'active'],
                ['name' => 'Число', 'description' => 'Тип свойства для хранения числовых данных', 'status' => 'active'],
                ['name' => 'Дата', 'description' => 'Тип свойства для хранения дат', 'status' => 'active'],
                ['name' => 'Интервал дат', 'description' => 'Тип свойства для хранения интервалов дат', 'status' => 'active'],
                ['name' => 'Картинка', 'description' => 'Тип свойства для хранения изображений', 'status' => 'active'],
            ];

            foreach ($property_types as $type) {
                SafeMySQL::gi()->query(
                        "INSERT INTO ?n (name, description, status) VALUES (?s, ?s, ?s) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), status = VALUES(status)",
                        Constants::PROPERTY_TYPES_TABLE,
                        $type['name'],
                        $type['description'],
                        $type['status']
                );
            }
            SafeMySQL::gi()->query("COMMIT");
        } catch (Exception $e) {
            SafeMySQL::gi()->query("ROLLBACK");
            SysClass::pre($e);
            return false;
        }
        // Получение статистики запросов
        $stats = array_reverse(SafeMySQL::gi()->getStats());
        // Вывод статистики
        foreach ($stats as $item) {
            $echo .= "QUERY: " . $item['query'] . PHP_EOL;
            $echo .= "Timer: " . $item['timer'] . PHP_EOL;
            $echo .= "Total time: " . $item['total_time'] . PHP_EOL;
            $echo .= "Backtrace: " . var_export($item['backtrace'], true) . PHP_EOL;
        }
        SysClass::pre_file('sql', $echo);
        if (ENV_LOG) {
            SysClass::SetLog('База данных успешно развёрнута.');
        }
    }

}
