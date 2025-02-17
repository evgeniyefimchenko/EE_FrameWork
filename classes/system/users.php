<?php

namespace classes\system;

use classes\plugins\SafeMySQL;
use classes\helpers\ClassMessages;
use classes\helpers\ClassMail;

/**
 * Класс работы с пользователями
 * Является родителем для моделей главной страницы и административной панели
 */
class Users {

    const BASE_OPTIONS_USER = '{"localize": "", "user_logo_img": "uploads/images/avatars/face-0-lite.jpg","user_img": "/uploads/images/avatars/face-0.jpg",'
                              . '"skin": "skin-default", "notifications": false}';

    private $lang = []; // Языковые переменные для текущего класса
    public $data;

    public function __construct($params = []) {
        $user_id = 0;
        $create_table = false;
        if (is_array($params) && count($params)) {
            $user_id = array_shift($params);
        } elseif (is_bool($params) && $params === true){
            $user_id = 0;
            $create_table = true;
        } else {
            $user_id = (int)$params;
        }
        $this->data = $this->getUserData($user_id, $create_table);
        $this->lang = !empty(Session::get('lang')) ? Lang::init(Session::get('lang')) : [];
    }

    /**
     * Возвращает подготовленные данные пользователя
     * личные + настройки интерфейса
     * @param id - идентификатор пользователя в базе
     * @param bool $create_table Развернёт БД
     * @return именованный массив
     */
    public function getUserData($id = 0, $create_table = false) {
        $resArray = [];
        if (!$create_table) {
            $sql_user = 'SELECT * FROM ?n WHERE user_id = ?i';
            $resArray = SafeMySQL::gi()->getRow($sql_user, Constants::USERS_TABLE, (int) $id);
            if ($resArray) {
                $resArray['count_unread_messages'] = ClassMessages::get_count_unread_messages($id);
                $resArray['count_messages'] = ClassMessages::get_count_messages($id);
                $resArray['messages'] = $resArray['count_unread_messages'] ? ClassMessages::get_messages_user($id) : [];
                $resArray['user_role_text'] = $this->getTextRole($resArray['user_role']);
                $resArray['user_role_name'] = $this->getNameRole($resArray['user_role']);
                $resArray['subscribed_text'] = $resArray['subscribed'] > 0 ? 'Подписан' : 'Не подписан';
                $resArray['options'] = $this->getUserOptions($id);
                $resArray['new_user'] = 0;
            } else { // Первичное заполнение полей для незарегистрированного пользователя
                $resArray['new_user'] = 1;
                $resArray['user_role'] = 4;
                $resArray['active'] = 1;
                $resArray['subscribed'] = 1;
                $resArray['options'] = json_decode(self::BASE_OPTIONS_USER, true);
                $resArray['user_role_text'] = $this->getTextRole($resArray['user_role']);
                $resArray['user_role_name'] = $this->getNameRole($resArray['user_role']);
                $resArray['subscribed_text'] = 'Подписан';
            }
        } else {
            $this->createTables(); //создаём необходимый набор таблиц в БД и первого пользователя с ролью администратора
            $this->registrationNewUser(array('name' => 'admin', 'email' => 'test@test.com', 'active' => '2', 'user_role' => '1', 'subscribed' => '0', 'comment' => 'Смените пароль администратора', 'pwd' => 'admin'), true);            
            $this->registrationNewUser(array('name' => 'moderator', 'email' => 'test_moderator@test.com', 'active' => '2', 'user_role' => '2', 'subscribed' => '0', 'comment' => 'Смените пароль модератора', 'pwd' => 'moderator'), true);
            $this->registrationNewUser(array('name' => 'system', 'email' => 'dont-answer@' . ENV_SITE_NAME, 'active' => '2', 'user_role' => '8', 'subscribed' => '0', 'comment' => '', 'pwd' => ''), true);
        }
        return $resArray;
    }

    /**
     * Вернёт имя пользователя
     */
    public function get_user_name($user_id) {
        $sql = 'SELECT name FROM ?n WHERE user_id = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $user_id);
    }

    /**
     * Записывает новые данные пользователя
     * @param user_id - user_id пользователя
     * @param fields - именованный массив полей для изменения, имена полей являются ключами
     * Уровень доступа из таблицы user_roles определяет набор полей для изменения
     * @return - boolean
     */
    public function setUserData(int $user_id = 0, array $fields = []): int {
        if (!filter_var($user_id, FILTER_VALIDATE_INT)) {
            if (ENV_TEST) {
                die('Неверный формат ID пользователя.');
            }
            return 0;
        }
        if ($this->data['user_role'] > 2 && $this->data['user_id'] != $user_id) { // Нет полномочий изменения данных пользователю
            if (ENV_TEST) {
                die('Нет полномочий изменения данных пользователю.');
            }
            return 0;
        }
        if (isset($this->data['user_id']) && $this->data['user_id'] == $user_id) { // Сам пользователь
            $fields = SafeMySQL::gi()->filterArray($fields, array('name', 'email', 'phone', 'subscribed', 'comment', 'pwd'));
        } else { // Модераторы 
            $fields = SafeMySQL::gi()->filterArray($fields, array('name', 'email', 'phone', 'active', 'user_role', 'subscribed', 'comment', 'pwd', 'element_id', 'element_name'));
        }
        $fields = SysClass::ee_removeEmptyValuesToArray(array_map('trim', $fields));
        if (!$fields)
            return 0;        
        if (isset($fields['pwd']) && strlen($fields['pwd']) >= 5) {
            $this->setUserPassword($user_id, $fields['email'], $fields['pwd']);
        }
        unset($fields['pwd']);
        $sql_user = "UPDATE ?n SET ?u, updated_at = now() WHERE user_id = ?i";
        SafeMySQL::gi()->query($sql_user, Constants::USERS_TABLE, $fields, $user_id);
        SysClass::preFile('users_info', 'set_user_data', 'Обновление данных пользователю user_id=' . $user_id, ['old' => $this->data['user_id'], 'new' => $fields]);
        return 1;
    }

    /**
     * Получить данные пользователей
     * @param string|array|false $order Ассоциативный массив или строка для сортировки в формате 'поле' => 'направление' или false для сортировки по умолчанию
     * @param string|null|false $where Условия фильтрации
     * @param int|false $start Начальный индекс для лимитации или false для значения по умолчанию
     * @param int|false $limit Количество строк для извлечения или false для значения по умолчанию
     * @param bool $deleted Учитывать удаленных пользователей
     * @return array Массив с данными пользователей и общим количеством записей
     */
    public function getUsersData($order = 'user_id ASC', $where = NULL, $start = 0, $limit = 100, bool $deleted = false) {
        $orderString = $order === false ? 'user_id ASC' : (is_array($order) ? implode(', ', array_map(fn($key, $value) => "$key $value", array_keys($order), $order)) : $order);
        $whereString = $where === false ? '' : ($where ? "WHERE $where" : '');
        if (!$deleted) {
            $whereString .= ($whereString ? ' AND' : 'WHERE') . ' deleted = 0';
        } else {
           $whereString .= ($whereString ? ' AND' : 'WHERE') . ' deleted = 1'; 
        }
        $sql_users = "SELECT user_id FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        $start = $start === false ? 0 : $start;
        $limit = $limit === false ? 100 : $limit;
        $resArray = SafeMySQL::gi()->getAll($sql_users, Constants::USERS_TABLE, $start, $limit);
        $res = array_map(fn($user) => $this->getUserData($user['user_id']), $resArray);
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::USERS_TABLE);
        return ['data' => $res, 'total_count' => $total_count];
    }

    /**
     * Возвращает персональные настройки пользователя
     * настройки интерфейса, аватар, фото
     * @param user_id - идентификатор пользователя в БД
     * @return именованный массив
     */
    public function getUserOptions($user_id) {
        $sql_users = 'SELECT options FROM ?n WHERE user_id = ?i';
        $options = SafeMySQL::gi()->getOne($sql_users, Constants::USERS_DATA_TABLE, $user_id);
        if (!$options) { // Для подстраховки
            $this->setUserOptions($user_id);
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
    public function setUserOptions($user_id, $options = '') {
        if (is_array($options)) {
            $options = json_encode($options);
        } else {
            $options = self::BASE_OPTIONS_USER;
        }
        if ($this->issetOptionsUser($user_id) > 0) {
            $sql = 'UPDATE ?n SET options = ?s WHERE user_id = ?i';
        } else {
            $sql = 'INSERT INTO ?n SET options = ?s, user_id = ?i';
        }
        return SafeMySQL::gi()->query($sql, Constants::USERS_DATA_TABLE, $options, $user_id);
    }

    /**
     * Имеются ли настройки у пользователя
     * @param int $user_id - ID пользователя
     * @return boolean
     */
    public function issetOptionsUser($user_id) {
        $sql = 'SELECT 1 FROM ?n WHERE user_id = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_DATA_TABLE, $user_id);
    }

    /**
     * Авторизует пользователя после проверки аргументов
     * @email - почта
     * @psw - нешифрованный пароль
     * @force_login - флаг авторизации без проверки аргументов, используется для автологина
     * @return string 
     */
    public function confirmUser($email, $psw, $force_login = false) {
        $res = '';
        $user_row = SafeMySQL::gi()->getRow('SELECT user_id, active, pwd FROM ?n WHERE email = ?s', Constants::USERS_TABLE, $email);
        if ($user_row && ($user_row['active'] == 2 || $force_login)) {
            if (password_verify($psw, $user_row['pwd']) || $force_login) {
                $add_query = '';
                if ($force_login) {
                    $add_query = 'active = 2, ';
                    $res = $this->lang['sys.welcome'];
                }
                $sql = 'UPDATE ?n SET ' . $add_query . 'last_ip = ?s, last_activ = ?s, session = ?s WHERE user_id = ?i';
                $ip = SysClass::client_ip();
                $last_date = date("Y-m-d H:i:s", time());
                $hash_login = $user_row['user_id'];
                if (ENV_ONE_IP_ONE_USER) {
                    $session = password_hash($hash_login, PASSWORD_DEFAULT);
                } else {                    
                    $session = md5($hash_login);
                }
                if (ENV_AUTH_USER == 0) {
                    Session::set('user_session', $session);
                }
                if (ENV_AUTH_USER === 2) {
                    Cookies::set('user_session', $session, ENV_TIME_AUTH_SESSION);
                }                
                SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, $ip, $last_date, $session, $user_row['user_id']);
            } else {
                $res = $this->lang['sys.the_password_was_not_verified'];
            }
        } elseif ($user_row && $user_row['active'] == 1) {
            $res = $this->lang['sys.you_have_not_verified_your_email'];
        } elseif ($user_row && $user_row['active'] == 3) {
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
    public function registrationUsers($email, $password) {
        $newpassword = password_hash($password, PASSWORD_DEFAULT);
        $sql = 'INSERT INTO ?n SET name = ?s, email = ?s, pwd = ?s, last_ip = ?s';
        SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, $email, $email, $newpassword, SysClass::client_ip());
        $sql = 'SELECT MAX(user_id) FROM ?n';
        $user_id = SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE);
        SysClass::preFile('users_info', 'registrationUsers', 'Зарегистрирован новый пользователь', ['user_id' => $user_id, 'email' => $email]);
        $this->setUserOptions($user_id); // Заполнить первичные данные из базы по шаблону
        if ($system_id = $this->get_user_id_by_email('dont-answer@' . ENV_SITE_NAME)) {
            ClassMessages::set_message_user($user_id, $system_id, 'Fill in your personal information <a href="' . ENV_URL_SITE . '/admin/user_edit/id/' . $user_id . '">link</a>', 'info');   
        }        
        return true;
    }

    /**
     * Регистрация нового пользователя модератором
     * по сути краткая регистрация пользователя без отсылки письма
     * @param $param - нефильтрованный POST массив
     * @param $flag - проверка прав пользователя
     * @return boolean результат операции
     */
    public function registrationNewUser($fields, $flag = false) {
        if (isset($this->data['user_role']) && $this->data['user_role'] > 2 && !$flag) {
            return 0;
        }
        $fields = SafeMySQL::gi()->filterArray($fields, array('name', 'email', 'phone', 'active', 'user_role', 'subscribed', 'comment', 'pwd'));
        $fields = array_map('trim', $fields);
        if ($this->get_email_exist($fields['email'])) {
            return 0;
        }
        $sql = 'INSERT INTO ?n SET ?u';
        SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, $fields);
        $user_id = SafeMySQL::gi()->insertId();
        SysClass::preFile('users_info', 'registration_new_user', 'Зарегистрирован новый пользователь', ['user_id' => $user_id, 'data' => $fields]);
        $this->setUserPassword($user_id, $fields['email'], $fields['pwd']);
        if ($system_id = $this->get_user_id_by_email('dont-answer@' . ENV_SITE_NAME)) {
            ClassMessages::set_message_user($user_id, $system_id, 'Заполните свой профиль <a href="' . ENV_URL_SITE . '/admin/user_edit/id/' . $user_id . '">тут</a>', 'info');
        }
        return 1;
    }

    /**
     * Проверка существования профиля админа
     * Если не существует то будет создан
     */
    public function getAdminProfile() {
        $sql = 'SELECT 1 FROM ?n WHERE user_role = 1 LIMIT 1';
        if (!SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE)) {
            $this->registrationNewUser(array('name' => 'admin', 'email' => 'test@test.com', 'active' => '2', 'user_role' => '1', 'subscribed' => '1', 'comment' => 'Смените пароль администратора', 'pwd' => 'admin'), true);
        }
    }

    /**
     * Устанавливает пароль пользователю 
     * @user_id - идентификатор пользователя
     * @email - почта обязательное поле!!!
     * @password - нешифрованный пароль
     * Если password пустой то генерирует случайный
     * @return пустое значение или сгенерированный пароль
     */
    public function setUserPassword($user_id = 0, $email = '', $password = '') {
        if (!$email) {
            return 0;
        }
        if (!$user_id) {
            $user_id = $this->get_user_id_by_email($email);
        }
        if (!$password) {
            $chars = "qazxswedcvfrtgbnhyujmkiolp1234567890QAZXSWEDCVFRTGBNHYUJMKIOLP";
            $max = 10;
            $size = StrLen($chars) - 1;
            while ($max--)
                $password .= $chars[rand(0, $size)];
        }

        $newsword = password_hash($password, PASSWORD_DEFAULT);
        $sql = 'UPDATE ?n SET pwd = ?s WHERE user_id = ?i';
        SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, $newsword, $user_id);
        SysClass::preFile('users_info', 'set_user_password', 'Пароль обновлён', ['email' => $email]);
        return $password;
    }

    /* Получение данных пользователей */

    /**
     * Вернёт ID статуса пользователя по его ID
     */
    public function get_user_stat($user_id) { // Статус пользователя 1 - на подтверждении, 2 - активен,  3 - блокирован
        $sql = 'SELECT active FROM ?n WHERE user_id = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $user_id);
    }

    /**
     * Вернёт ID роли пользователя по его ID
     */
    public function getUserRole($user_id) {
        $sql = 'SELECT user_role FROM ?n WHERE user_id = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $user_id);
    }

    /**
     * Вернёт название роли по её ID
     */
    public function getTextRole($roleId) {
        $sql = 'SELECT name FROM ?n WHERE role_id = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_ROLES_TABLE, $roleId);
    }

    public function getNameRole($roleId) {
        $sql = 'SELECT role_key FROM ?n WHERE role_id = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_ROLES_TABLE, $roleId);        
    }
    
    /**
     * Поиск ID пользователя по почте
     */
    public function get_user_id_by_email($email) {
        $sql = 'SELECT user_id FROM ?n WHERE email LIKE ?s';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $email);
    }

    /**
     * Вернёт email по user id
     * @param int $user_id
     * @return str
     */
    public function get_user_email($user_id) {
        $sql = 'SELECT email FROM ?n WHERE user_id = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $user_id);
    }

    /**
     * Проверка существования почты в БД
     */
    public function get_email_exist($email) {
        $sql = 'SELECT 1 FROM ?n WHERE email = ?s AND deleted = 0';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $email);
    }

    /**
     * Меняет и высылает на почту пользователя новый пароль к сайту     
     */
    public function send_recovery_password($email) {
        $password = $this->setUserPassword(0, $email, 0);
        $mailIsSuccess = ClassMail::send_mail($email, $this->lang['sys.restore_password_process'] . ' ' . ENV_SITE_NAME,
                ['PASSWORD' => $password]);
        if ($mailIsSuccess) {
            SysClass::preFile('users_info', 'send_recovery_password', 'Отправлен новый пароль', ['email' => $email]);
            return true;
        } else {
            SysClass::preFile('users_info_errors', 'send_recovery_password', 'Отправка пароля завершилась неудачей', ['email' => $email]);
            return false;
        }
    }

    /**
     * Отправляет ссылку для активации пользователя
     */
    public function send_register_code($email) {
        $acivation_code = base64_encode(password_hash($email, PASSWORD_DEFAULT));
        $activation_link = ENV_URL_SITE . '/activation/' . $acivation_code . '/' . $email;
        $res_mail = ClassMail::send_mail($email, 'Регистрация на сайте', ['activation_link' => $activation_link], 'activation_link');
        if ($res_mail !== TRUE) {
            SysClass::preFile('users_info', 'send_register_code', 'Ошибка отправки письма с кодом', ['email' => $email, 'acivation_code' => $acivation_code]);
            return false;
        } else {
            SysClass::preFile('users_info', 'send_register_code', 'Письмо на с кодом отправлено', ['email' => $email, 'acivation_code' => $acivation_code]);
            $sql = 'INSERT INTO ?n SET user_id = ?i,email = ?s, code = ?s, stop_time = ?s';
            SafeMySQL::gi()->query($sql, Constants::USERS_ACTIVATION_TABLE, $this->get_user_id_by_email($email), $email, $acivation_code, date("Y-m-d H:i:s", time() + ENV_TIME_ACTIVATION));
            return true;
        }
    }

    /**
     * Удаление регистрационного кода после активации
     */
    public function dell_activation_code($email, $code) {
        $sql = 'SELECT stop_time FROM ?n WHERE email LIKE ?s AND code LIKE ?s';
        $query = SafeMySQL::gi()->getOne($sql, Constants::USERS_ACTIVATION_TABLE, $email, $code);
        if ($query > date("Y-m-d H:i:s")) {
            $sql = 'DELETE FROM ?n WHERE email = ?s';
            SafeMySQL::gi()->query($sql, Constants::USERS_ACTIVATION_TABLE, $email);
            $sql = 'UPDATE ?n SET active = 2 WHERE user_id = ?i AND active = 1';
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
    public function createTables() {
        SafeMySQL::gi()->query("START TRANSACTION");
        SafeMySQL::gi()->query("SET FOREIGN_KEY_CHECKS=1");  // включаем проверку внешних ключей
        try {
            /* Пользователи */
            $createUsersTable = "CREATE TABLE IF NOT EXISTS ?n (
                        user_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                        name char(255) NOT NULL DEFAULT 'no name',
                        email char(255) NOT NULL,
                        pwd varchar(255) NOT NULL,
                        active tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 - на подтверждении, 2 - активен,  3 - блокирован',
                        user_role tinyint(2) NOT NULL DEFAULT '4' COMMENT 'таблица user_roles',
                        last_ip char(20) DEFAULT NULL,
                        subscribed tinyint(1) DEFAULT '1' COMMENT 'подписка на рассылку',
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'дата регистрации',
                        last_activ datetime DEFAULT NULL COMMENT 'дата крайней активности',
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'дата обновления инф.',
                        phone varchar(255) NULL,
                        session varchar(512) NULL,
                        comment varchar(255) NOT NULL COMMENT 'Комментарий или дивиз пользователя',
                        deleted BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Флаг удаленного пользователя',
                        PRIMARY KEY (user_id),
                        UNIQUE KEY email (email)
                    ) ENGINE=innodb DEFAULT CHARSET=utf8 COMMENT='Пользователи сайта';";
            SafeMySQL::gi()->query($createUsersTable, Constants::USERS_TABLE);
            /* Роли пользователей */
            $createUsersRolesTable = "CREATE TABLE IF NOT EXISTS ?n (
                    role_id tinyint(2) UNSIGNED NOT NULL AUTO_INCREMENT,
                    role_key varchar(50) NOT NULL COMMENT 'Уникальный ключ роли',
                    name varchar(255) NOT NULL,
                    PRIMARY KEY (role_id),
                    UNIQUE KEY (role_key)
                    ) ENGINE=innodb DEFAULT CHARSET=utf8 COMMENT='Роли пользователей';";
            SafeMySQL::gi()->query($createUsersRolesTable, Constants::USERS_ROLES_TABLE);
            /* Добавим стандартные роли */
            $insertData = "INSERT INTO ?n (role_id, role_key, name) VALUES
                    (1, 'admin', 'Администратор'),
                    (2, 'moderator', 'Модератор'),
                    (3, 'manager', 'Менеджер'),
                    (4, 'user', 'Пользователь'),
                    (8, 'system', 'Система')
                    ON DUPLICATE KEY UPDATE name = VALUES(name);";
            SafeMySQL::gi()->query($insertData, Constants::USERS_ROLES_TABLE);
            /* Данные пользователя */
            $createUsersDataTable = "CREATE TABLE IF NOT EXISTS ?n (
                data_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id int(11) UNSIGNED NOT NULL,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время любого изменения данных',
                options text NOT NULL COMMENT 'Настройки интерфейса пользователя',
                PRIMARY KEY (data_id),
                FOREIGN KEY (user_id) REFERENCES ?n(user_id) ON DELETE CASCADE
            ) ENGINE=innodb DEFAULT CHARSET=utf8 COMMENT='Системные данные пользователей';";
            SafeMySQL::gi()->query($createUsersDataTable, Constants::USERS_DATA_TABLE, Constants::USERS_TABLE);
            /* Сообщения пользователя */
            $createUsersMessageTable = "CREATE TABLE IF NOT EXISTS ?n (
                message_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id int(11) UNSIGNED NOT NULL,
                author_id int(11) UNSIGNED NOT NULL,
                chat_id int(11) UNSIGNED NULL COMMENT 'Зарезервирован для груповых чатов',
                message_text varchar(1000) NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                read_at datetime DEFAULT NULL,
                status varchar(10) NOT NULL DEFAULT 'info',
                PRIMARY KEY (message_id),
                FOREIGN KEY (user_id) REFERENCES ?n(user_id) ON DELETE CASCADE,
                FOREIGN KEY (author_id) REFERENCES ?n(user_id) ON DELETE CASCADE
            ) ENGINE=innodb DEFAULT CHARSET=utf8;";
            SafeMySQL::gi()->query($createUsersMessageTable, Constants::USERS_MESSAGE_TABLE, Constants::USERS_TABLE, Constants::USERS_TABLE);
            /* Таблица ссылок для активации новых пользователей */
            $create_users_activation_table = "CREATE TABLE IF NOT EXISTS ?n (
                id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id int(11) NOT NULL,
                email varchar(255) NOT NULL,
                code varchar(255) NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                stop_time datetime NOT NULL,
                PRIMARY KEY (id)
            ) ENGINE=innodb DEFAULT CHARSET=utf8 COMMENT='Коды активации для зарегистрировавшихся пользователей';";
            SafeMySQL::gi()->query($create_users_activation_table, Constants::USERS_ACTIVATION_TABLE);
            // Создание таблицы типов категорий
            $createTypesTable = "CREATE TABLE IF NOT EXISTS ?n (
                        type_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        parent_type_id INT UNSIGNED NULL,
                        name VARCHAR(255) NOT NULL UNIQUE,
                        description VARCHAR(1000),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
                        FOREIGN KEY (parent_type_id) REFERENCES ?n(type_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения типов сущностей и категорий';";
            SafeMySQL::gi()->query($createTypesTable, Constants::CATEGORIES_TYPES_TABLE, Constants::CATEGORIES_TYPES_TABLE);
            // Создание таблицы категорий
            // Шаг 1: Создание таблицы без внешних ключей
            $createCategoriesTable = "CREATE TABLE IF NOT EXISTS ?n (
                        category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        type_id INT UNSIGNED NOT NULL,
                        title VARCHAR(255) NOT NULL,
                        description mediumtext,
                        short_description VARCHAR(1000),
                        parent_id INT UNSIGNED,
                        status ENUM('active', 'hidden', 'disabled') NOT NULL DEFAULT 'active',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
                        INDEX (type_id),
                        INDEX (parent_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения категорий сущностей';";
            SafeMySQL::gi()->query($createCategoriesTable, Constants::CATEGORIES_TABLE);
            // Шаг 2: Добавление внешних ключей
            $add_foreign_keys = "ALTER TABLE ?n 
                        ADD FOREIGN KEY (type_id) REFERENCES ?n(type_id),
                        ADD FOREIGN KEY (parent_id) REFERENCES ?n(category_id);";
            SafeMySQL::gi()->query($add_foreign_keys, Constants::CATEGORIES_TABLE, Constants::CATEGORIES_TYPES_TABLE, Constants::CATEGORIES_TABLE);
            // Создание таблицы сущностей
            $createPagesTable = "CREATE TABLE IF NOT EXISTS ?n (
			page_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			parent_page_id INT UNSIGNED NULL,
			category_id INT UNSIGNED,
                        status ENUM('active', 'hidden', 'disabled') NOT NULL DEFAULT 'active',
			title VARCHAR(255) NOT NULL,
			short_description VARCHAR(255),
			description LONGTEXT,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
			FOREIGN KEY (category_id) REFERENCES ?n(category_id),
			INDEX (category_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения страниц';";
            SafeMySQL::gi()->query($createPagesTable, Constants::PAGES_TABLE, Constants::CATEGORIES_TABLE);
            // Таблица для хранения типов свойств
            $createPropertyTypesTable = "CREATE TABLE IF NOT EXISTS ?n (
			type_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			name VARCHAR(255) NOT NULL,
			status ENUM('active', 'hidden', 'disabled') NOT NULL DEFAULT 'active',
                        fields JSON NOT NULL,
			description VARCHAR(1000),
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2'
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения типов свойств';";
            SafeMySQL::gi()->query($createPropertyTypesTable, Constants::PROPERTY_TYPES_TABLE);
            // Таблица для хранения свойств
            $createPropertiesTable = "CREATE TABLE IF NOT EXISTS ?n (
			property_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			type_id INT UNSIGNED NOT NULL,
			name VARCHAR(255) NOT NULL,
                        status ENUM('active', 'hidden', 'disabled') NOT NULL DEFAULT 'active',
                        sort INT UNSIGNED NOT NULL DEFAULT 100,
                        default_values JSON NOT NULL,
			is_multiple BOOLEAN NOT NULL,
			is_required BOOLEAN NOT NULL,
			description VARCHAR(1000),
                        entity_type ENUM('category', 'page', 'all') NOT NULL DEFAULT 'all',
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
			FOREIGN KEY (type_id) REFERENCES ?n(type_id),
			INDEX (type_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения свойств';";
            SafeMySQL::gi()->query($createPropertiesTable, Constants::PROPERTIES_TABLE, Constants::PROPERTY_TYPES_TABLE);
            // Таблица для хранения общей информации о наборе свойств.
            $createPropertySetsTable = "CREATE TABLE IF NOT EXISTS ?n (
                        set_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(255) NOT NULL,
                        description MEDIUMTEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2'
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения наборов свойств';";
            SafeMySQL::gi()->query($createPropertySetsTable, Constants::PROPERTY_SETS_TABLE);            
            // Таблица для хранения значений свойств в формате JSON
            $createPropertyValuesTable = "CREATE TABLE IF NOT EXISTS ?n (
			value_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			entity_id INT UNSIGNED NOT NULL,
			set_id INT UNSIGNED NOT NULL,
			property_id INT UNSIGNED NOT NULL,
			entity_type ENUM('category', 'page') NOT NULL,
			property_values JSON NOT NULL,			
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
			FOREIGN KEY (property_id) REFERENCES ?n(property_id),
			FOREIGN KEY (set_id) REFERENCES ?n(set_id),
			INDEX (property_id),
			INDEX (entity_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения значений свойств в формате JSON';";
            SafeMySQL::gi()->query($createPropertyValuesTable, Constants::PROPERTY_VALUES_TABLE, Constants::PROPERTIES_TABLE, Constants::PROPERTY_SETS_TABLE);
            // Таблица для представления отношения многие ко многим между типами категорий и наборами свойств.
            $createCategoryTypeToSetTable = "CREATE TABLE IF NOT EXISTS ?n (
                        type_id INT UNSIGNED NOT NULL,
                        set_id INT UNSIGNED NOT NULL,
                        PRIMARY KEY (type_id, set_id),
                        FOREIGN KEY (type_id) REFERENCES ?n(type_id),
                        FOREIGN KEY (set_id) REFERENCES ?n(set_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для связи типов категорий и наборов свойств';";
            SafeMySQL::gi()->query($createCategoryTypeToSetTable, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, Constants::CATEGORIES_TYPES_TABLE, Constants::PROPERTY_SETS_TABLE);
            // Таблица для представления отношения многие ко многим между наборами свойств и свойствами.
            $createSetToPropertiesTable = "CREATE TABLE IF NOT EXISTS ?n (
                        set_id INT UNSIGNED NOT NULL,
                        property_id INT UNSIGNED NOT NULL,
                        PRIMARY KEY (set_id, property_id),
                        FOREIGN KEY (set_id) REFERENCES ?n(set_id),
                        FOREIGN KEY (property_id) REFERENCES ?n(property_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для связи наборов свойств и свойств';";
            SafeMySQL::gi()->query($createSetToPropertiesTable, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, Constants::PROPERTY_SETS_TABLE, Constants::PROPERTIES_TABLE);
            // Таблица поиска по сайту
            $createSearchContentsTable = "CREATE TABLE IF NOT EXISTS ?n (
                        search_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        entity_id INT UNSIGNED NOT NULL,
                        entity_type VARCHAR(255) NOT NULL,
                        area CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'Локация поиска A-админпанель, C-клиетская часть',
                        full_search_content TEXT NOT NULL,
                        short_search_content TEXT NOT NULL,
                        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
                        relevance_score TINYINT UNSIGNED DEFAULT 0,
                        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FULLTEXT(full_search_content, short_search_content),
                        INDEX (entity_id, entity_type, area, language_code)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для глобального поиска по сайту';";
            SafeMySQL::gi()->query($createSearchContentsTable, Constants::SEARCH_CONTENTS_TABLE);
            // Для файлов загруженных на страницах проекта
            $createFilesTable = "CREATE TABLE IF NOT EXISTS ?n (
                                    file_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                    name VARCHAR(255) NOT NULL,
                                    original_name VARCHAR(255) NOT NULL,
                                    file_path VARCHAR(255) NOT NULL,
                                    file_url VARCHAR(255) NULL,
                                    mime_type VARCHAR(50) NOT NULL,
                                    size BIGINT UNSIGNED NOT NULL,
                                    image_size ENUM('small', 'medium', 'large') DEFAULT NULL,
                                    user_id INT UNSIGNED NULL,
                                    file_hash CHAR(32) NOT NULL COMMENT 'MD5 хеш файла для проверки уникальности',
                                    uploaded_at DATETIME NOT NULL,
                                    updated_at DATETIME DEFAULT NULL,
                                    UNIQUE KEY unique_file_hash (file_hash),                                 
                                    FOREIGN KEY (user_id) REFERENCES ?n(user_id)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
                                 COMMENT='Таблица для сохранения информации о файлах';";
            SafeMySQL::gi()->query($createFilesTable, Constants::FILES_TABLE, Constants::USERS_TABLE);
            // Создание таблицы для глобальных опций
            $createGlobalOptionsTable = "CREATE TABLE IF NOT EXISTS ?n (
                                            option_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Уникальный идентификатор опции',
                                            option_key VARCHAR(255) NOT NULL COMMENT 'Уникальный ключ опции',
                                            option_value TEXT NOT NULL COMMENT 'Значение опции (в виде текста, без преобразования)',
                                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания записи',
                                            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Дата обновления записи',
                                            UNIQUE KEY (option_key) COMMENT 'Уникальность ключа опции'
                                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения глобальных опций';";
            SafeMySQL::gi()->query($createGlobalOptionsTable, Constants::GLOBAL_OPTIONS);            
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
            $propertyTypes = [
                ['name' => 'Строка', 'description' => 'Тип свойства для хранения строковых данных', 'status' => 'active', 'fields' => '["text"]'],
                ['name' => 'Число', 'description' => 'Тип свойства для хранения числовых данных', 'status' => 'active', 'fields' => '["number"]'],
                ['name' => 'Дата', 'description' => 'Тип свойства для хранения дат', 'status' => 'active', 'fields' => '["date"]'],
                ['name' => 'Интервал дат', 'description' => 'Тип свойства для хранения интервалов дат', 'status' => 'active', 'fields' => '["date", "date"]'],
                ['name' => 'Картинка', 'description' => 'Тип свойства для хранения изображений', 'status' => 'active', 'fields' => '["image"]'],
                ['name' => 'SEO-параметры', 'description' => '
                    meta_title (string): заголовок, отображающийся в title
                    slug (text): ЧПУ/короткий URL
                    meta_description (text): мета-описание
                    meta_keywords (text): ключевые слова (менее актуально, но может пригодиться)
                    canonical_url (text): канонический URL
                    robots_meta (select): директивы для роботов (index, noindex, follow, nofollow и т.д.)
                    open_graph_title (text): заголовок для соцсетей (если не заполнено, используется meta_title)
                    open_graph_description (text): описание для соцсетей (если не заполнено, используется meta_description)
                    open_graph_image (image): изображение для соцсетей (Open Graph)
                    Комментарий: Поля open_graph_* дают гибкость в оформлении публикации в соцсетях. Обычно нужны «для всех» — чтобы и категориям, и страницам при желании настраивать SEO.',
                    'status' => 'active', 'fields' => '["text", "text", "text", "text", "text", "select", "text", "text", "image"]'],
                ['name' => 'Основные свойства страницы', 'description' => '
                    author (text): автор материала
                    visibility (select): публичная, приватная, черновик и т.д.
                    page_status (select): опубликована, черновик, на проверке и т.д.
                    Комментарий: Обычно эти поля нужны только для страниц, но в некоторых случаях (например, content или last_updated) могут использоваться и в категориях.',
                    'status' => 'active', 'fields' => '["text", "select", "select"]'],
                ['name' => 'Социальные элементы', 'description' => '
                    allow_comments (Разрешить комментарии: да/нет)
                    allow_sharing (Разрешить поделиться в соцсетях: да/нет)
                    comment_count (Количество комментариев)
                    like_count (Количество лайков)',
                    'status' => 'active', 'fields' => '["checkbox", "checkbox", "number", "number"]'],
                ['name' => 'Параметры для карточек товаров', 'description' => '
                    price (Цена товара)
                    list_price(Рекомендованная цена)
                    discount (Скидка)
                    stock_status (Наличие на складе)
                    sku (Артикул)
                    brand (Бренд товара)
                    rating (Рейтинг товара)
                    review_count (Количество отзывов)',
                    'status' => 'active', 'fields' => '["text", "text", "number", "text", "text", "number", "number"]'],
                ['name' => 'Мультимедийные элементы', 'description' => '
                    featured_image (Изображение страницы)
                    gallery (Галерея изображений)
                    video_url (URL видео)
                    file_attachments (Вложения файлов)',
                    'status' => 'active', 'fields' => '["image", "image", "text", "file"]'],
                ['name' => 'Структурные свойства', 'description' => '
                    parent_category (Родительская категория)
                    related_pages (Связанные страницы)
                    breadcrumb_navigation (Навигация по хлебным крошкам)',
                    'status' => 'active', 'fields' => '["text", "text", "text"]'],
                ['name' => 'Дополнительные параметры', 'description' => '
                    custom_css (Пользовательский CSS)
                    custom_js (Пользовательский JavaScript)
                    redirect_url (URL для редиректа)',
                    'status' => 'active', 'fields' => '["text", "text", "text"]'],
            ];
            // Очистка description
            foreach ($propertyTypes as &$type) {
                if (isset($type['description'])) {
                    // Удаляем лишние пробелы и TAB, оставляя переносы строк
                    $type['description'] = preg_replace('/[ \t]+/', ' ', $type['description']); // Заменяем группы пробелов и TAB на один пробел
                    $type['description'] = preg_replace('/\s*\n\s*/', "\n", $type['description']); // Убираем пробелы и TAB вокруг переноса строк
                }
            }            
            // Создание дефолтных свойств
            if ($objectModelProperties = SysClass::getModelObject('admin', 'm_properties')) {
                foreach ($propertyTypes as &$type) {
                    $propertyData = [];
                    $type['type_id'] = $objectModelProperties->updatePropertyTypeData($type);
                    $propertyData['type_id'] = $type['type_id'];
                    $propertyData['name'] = $type['name'];                
                    $propertyData['entity_type'] = 'all';
                    $propertyData['default_values'] = [];
                    $propertyData['description'] = $type['description'];
                    $propParams = [];
                    switch ($type['name']) {
                        case 'SEO-параметры' :
                            $propParams = ['Заголовок, отображающийся в title' => 'text', 'ЧПУ/короткий URL' => 'text', 'Мета-описание' => 'text', 'Ключевые слова' => 'text',
                                'Канонический URL' => 'text', 'Директивы для роботов' => ['select', '{|}index=index{|}noindex=noindex{|}follow=follow{|}nofollow=nofollow'], 
                                'Заголовок для соцсетей' => 'text', 'Описание для соцсетей' => 'text',
                                'Изображение для соцсетей' => 'image'];
                        break;
                        case 'Основные свойства страницы':
                            $propertyData['entity_type'] = 'page';
                            $propParams = ['Автор материала' => 'text', 'Видимость' => 'select', 'Публичный статус' => 'select'];
                        break;
                        case 'Социальные элементы': 
                            $propParams = ['Разрешить комментарии' => 'checkbox', 'Разрешить поделиться в соцсетях' => 'checkbox', 'Количество комментариев' => 'number',
                                'Количество лайков' => 'number'];
                        break;
                        case 'Параметры для карточек товаров':
                            $propertyData['entity_type'] = 'page';
                            $propParams = ['Цена товара' => 'text', 'Скидка' => 'text', 'Наличие на складе' => 'number',
                                'Артикул' => 'text', 'Бренд товара' => 'text', 'Рейтинг товара' => 'number', 'Количество отзывов' => 'number'];
                        break;
                        case 'Мультимедийные элементы': 
                            $propParams = ['Изображение страницы' => 'image', 'Галерея изображений' => 'image', 'URL видео' => 'text',
                                'Вложения файлов' => 'file'];
                        break;
                        case 'Структурные свойства':
                            $propertyData['entity_type'] = 'category';
                            $propParams = ['Родительская категория' => 'text', 'Связанные страницы' => 'text', 'Навигация по хлебным крошкам' => 'text'];
                        break;
                        case 'Дополнительные параметры': 
                            $propParams = ['Пользовательский CSS' => 'text', 'Пользовательский JavaScript' => 'text', 'URL для редиректа' => 'text'];
                        break;
                        default: break;
                    }
                    $count = 0;
                    foreach ($propParams as $itemLabel => $fieldType) {
                        if (is_array($fieldType)) { // Пока только для select
                            $propertyData['default_values'][] = ['type' => $fieldType[0], 'label' => $itemLabel, 'default' => $fieldType[1], 'multiple' => 0,
                                'required' => 0];
                        } else {
                            if ($fieldType == 'checkbox' || $fieldType == 'radio') {
                                $propertyData['default_values'][] = ['title' => $itemLabel, 'count' => 1, 'type' => $fieldType, 'label' => [$itemLabel], 'default' => '', 'multiple' => 0,
                                    'required' => 0];
                            } else {
                                $propertyData['default_values'][] = ['type' => $fieldType, 'label' => $itemLabel, 'default' => '', 'multiple' => 0,
                                    'required' => 0];
                            }
                        }
                    }
                    if (count($propertyData['default_values'])) { // Создаём свойство только с default_values
                        $objectModelProperties->updatePropertyData($propertyData);
                    }
                }
                
                // TODO только для теста!
                if (1 == 1) {
                    $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
                    $objectModelCategories = SysClass::getModelObject('admin', 'm_categories', ['m_categories_types' => $objectModelCategoriesTypes]);
                    $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
                    $objectModelPages= SysClass::getModelObject('admin', 'm_pages');
                    // Наборы свойств
                    $setsData[] = [
                        'set_id' => '0',
                        'name' => 'Курорты',
                        'description' => 'Для категории курорты'
                    ];
                    $setsData[] = [
                        'set_id' => '0',
                        'name' => 'Объекты',
                        'description' => 'Для страниц объектов'
                    ];
                    $setsData[] = [
                        'set_id' => '0',
                        'name' => 'Социалочка',
                        'description' => 'Для дополнения'
                    ];
                    foreach ($setsData as $setData) {
                        $objectModelProperties->updatePropertySetData($setData);
                    }
                    // Добавим наборам свойства
                    $objectModelProperties->addPropertiesToSet(1, [1, 5, 6]); // Для курортов
                    $objectModelProperties->addPropertiesToSet(2, [1, 2, 4, 7]); // Для объектов
                    $objectModelProperties->addPropertiesToSet(3, [3]); // До кучи
                    // Типы категорий
                    $categoriesTypeData[] = ['typeData' => [
                        'type_id' => '0',
                        'name' => 'Для курортов',
                        'parent_type_id' => NULL,
                        'description' => ''
                    ], 'catSetData' => [1, 3]];
                    $categoriesTypeData[] = ['typeData' => [
                        'type_id' => '0',
                        'name' => 'Для объектов',
                        'parent_type_id' => NULL,
                        'description' => ''
                    ], 'catSetData' => [2]];
                    foreach ($categoriesTypeData as $catTypeData) {
                        $type_id = $objectModelCategoriesTypes->updateCategoriesTypeData($catTypeData['typeData']);
                        $objectModelCategoriesTypes->updateCategoriesTypeSetsData($type_id, $catTypeData['catSetData']);
                    }                
                    // Категории
                    $categoriesData[] = [
                        'category_id' => 0,
                        'title' => 'Курорты',
                        'type_id' => 5,
                        'parent_id' => 0,
                        'status' => 'active',
                        'short_description' => 'Главная',
                        'description' => 'Главная',
                    ];
                    $categoriesData[] = [
                        'category_id' => 0,
                        'title' => 'Курорты Азовского моря в России',
                        'type_id' => 5,
                        'parent_id' => 1,
                        'status' => 'active',
                        'short_description' => '-Дочерняя',
                        'description' => '-Дочерняя',
                    ];
                    $categoriesData[] = [
                        'category_id' => 0,
                        'title' => 'Голубицкая',
                        'type_id' => 5,
                        'parent_id' => 2,
                        'status' => 'active',
                        'short_description' => '--Дочерняя',
                        'description' => '--Дочерняя',
                    ];
                    $categoriesData[] = [
                        'category_id' => 0,
                        'title' => 'Должанская',
                        'type_id' => 5,
                        'parent_id' => 2,
                        'status' => 'active',
                        'short_description' => '--Дочерняя',
                        'description' => '--Дочерняя',
                    ];
                    $categoriesData[] = [
                        'category_id' => 0,
                        'title' => 'Курорты Черного моря в Абхазии',
                        'type_id' => 5,
                        'parent_id' => 1,
                        'status' => 'active',
                        'short_description' => '-Дочерняя',
                        'description' => '-Дочерняя',
                    ];
                    $categoriesData[] = [
                        'category_id' => 0,
                        'title' => 'Сухум',
                        'type_id' => 5,
                        'parent_id' => 5,
                        'status' => 'active',
                        'short_description' => '--Дочерняя',
                        'description' => '--Дочерняя',
                    ];
                    foreach ($categoriesData as $catData) {
                        $objectModelCategories->updateCategoryData($catData);
                    }
                    // Нужно создать страницы !!!
                    $pages[] = [
                        'page_id' => 0,
                        'title' => 'Гостиница «Морской компотик»',
                        'category_id' => 3,
                        'parent_page_id' => 0,
                        'status' => 'active',
                        'short_description' => 'Голубицкая, ул. Курортная, 69',
                        'description' => '',                    
                    ];
                    $pages[] = [
                        'page_id' => 0,
                        'title' => 'Гостевой дом у моря',
                        'category_id' => 3,
                        'parent_page_id' => 0,
                        'status' => 'active',
                        'short_description' => 'Десантников освободителей 20',
                        'description' => '',                    
                    ];
                    $pages[] = [
                        'page_id' => 0,
                        'title' => 'Гостиничный комплекс «МЫС»',
                        'category_id' => 4,
                        'parent_page_id' => 0,
                        'status' => 'active',
                        'short_description' => 'Должанская, Знаменский переулок, 16а',
                        'description' => '',                    
                    ];
                    $pages[] = [
                        'page_id' => 0,
                        'title' => 'Студия в центре Сухум на Чачба',
                        'category_id' => 6,
                        'parent_page_id' => 0,
                        'status' => 'active',
                        'short_description' => 'Абхазия, Сухум, Чачба',
                        'description' => '',                    
                    ];
                    $pages[] = [
                        'page_id' => 0,
                        'title' => 'Hotel in Sukhum',
                        'category_id' => 6,
                        'parent_page_id' => 0,
                        'status' => 'active',
                        'short_description' => 'Сухум, ул. Званба, 21',
                        'description' => '',                    
                    ];
                    foreach ($pages as $page) {
                        $objectModelPages->updatePageData($page);
                    }
                }
                // Конец теста TODO не записывает значения свойств
            }
            SafeMySQL::gi()->query("COMMIT");
        } catch (Exception $e) {
            SafeMySQL::gi()->query("ROLLBACK");
            SysClass::pre($e);
            return false;
        }
        SysClass::preFile('sql_info', 'create_tables', 'База данных успешно развёрнута', 'OK');
    }

}
