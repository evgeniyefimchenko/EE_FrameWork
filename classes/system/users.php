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

    public $lang = []; // Языковые переменные для текущего класса
    public $data;

    public function __construct($params = []) {
        $userId = 0;
        $create_table = false;
        if (is_array($params) && count($params)) {
            $userId = array_shift($params);
        } elseif (is_bool($params) && $params === true) {
            $userId = 0;
            $create_table = true;
        } else {
            $userId = (int) $params;
        }
        $this->data = $this->getUserData($userId, $create_table);
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
    public function get_user_name($userId) {
        $sql = 'SELECT name FROM ?n WHERE user_id = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $userId);
    }

    /**
     * Записывает новые данные пользователя
     * @param user_id - user_id пользователя
     * @param fields - именованный массив полей для изменения, имена полей являются ключами
     * Уровень доступа из таблицы user_roles определяет набор полей для изменения
     * @return - boolean
     */
    public function setUserData(int $userId = 0, array $fields = []): int {
        if (!filter_var($userId, FILTER_VALIDATE_INT)) {
            if (ENV_TEST) {
                die('Неверный формат ID пользователя.');
            }
            return 0;
        }
        if ($this->data['user_role'] > 2 && $this->data['user_id'] != $userId) { // Нет полномочий изменения данных пользователю
            if (ENV_TEST) {
                die('Нет полномочий изменения данных пользователю.');
            }
            return 0;
        }
        if (isset($this->data['user_id']) && $this->data['user_id'] == $userId) { // Сам пользователь
            $fields = SafeMySQL::gi()->filterArray($fields, array('name', 'email', 'phone', 'subscribed', 'comment', 'pwd'));
        } else { // Модераторы 
            $fields = SafeMySQL::gi()->filterArray($fields, array('name', 'email', 'phone', 'active', 'user_role', 'subscribed', 'comment', 'pwd', 'element_id', 'element_name'));
        }
        $fields = SysClass::ee_removeEmptyValuesToArray(array_map('trim', $fields));
        if (!$fields)
            return 0;
        if (isset($fields['pwd']) && strlen($fields['pwd']) >= 5) {
            $this->setUserPassword($userId, $fields['email'], $fields['pwd']);
        }
        unset($fields['pwd']);
        $sql_user = "UPDATE ?n SET ?u, updated_at = now() WHERE user_id = ?i";
        SafeMySQL::gi()->query($sql_user, Constants::USERS_TABLE, $fields, $userId);
        SysClass::preFile('users_info', 'set_user_data', 'Обновление данных пользователю user_id=' . $userId, ['old' => $this->data['user_id'], 'new' => $fields]);
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
    public function getUserOptions($userId) {
        $sql_users = 'SELECT options FROM ?n WHERE user_id = ?i';
        $options = SafeMySQL::gi()->getOne($sql_users, Constants::USERS_DATA_TABLE, $userId);
        if (!$options) { // Для подстраховки
            $this->setUserOptions($userId);
            $options = self::BASE_OPTIONS_USER;
        }
        return json_decode($options, true);
    }

    /**
     * Записывает персональные настройки пользователя
     * настройки интерфейса, аватар, фото
     * @param int $userId - идентификатор пользователя в БД
     * @param array $options - массив с настройками
     * @return true
     */
    public function setUserOptions($userId, $options = '') {
        if (is_array($options)) {
            $options = json_encode($options);
        } else {
            $options = self::BASE_OPTIONS_USER;
        }
        if ($this->issetOptionsUser($userId) > 0) {
            $sql = 'UPDATE ?n SET options = ?s WHERE user_id = ?i';
        } else {
            $sql = 'INSERT INTO ?n SET options = ?s, user_id = ?i';
        }
        return SafeMySQL::gi()->query($sql, Constants::USERS_DATA_TABLE, $options, $userId);
    }

    /**
     * Имеются ли настройки у пользователя
     * @param int $userId - ID пользователя
     * @return boolean
     */
    public function issetOptionsUser($userId) {
        $sql = 'SELECT 1 FROM ?n WHERE user_id = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_DATA_TABLE, $userId);
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
        $ip = SysClass::getClientIp();
        if ($user_row && ($user_row['active'] == 2 || $force_login)) {
            if (password_verify($psw, $user_row['pwd']) || $force_login) {
                $add_query = '';
                if ($force_login) {
                    $add_query = 'active = 2, ';
                    $res = $this->lang['sys.welcome'];
                }
                $sql = 'UPDATE ?n SET ' . $add_query . 'last_ip = ?s, last_activ = ?s, session = ?s WHERE user_id = ?i';
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
            $badCount = (int) \classes\system\Session::get('botguard_account_bad_active');
            $badCount++;
            if ($badCount >= 5 && $badCount <= 10) {
                \classes\system\BotGuard::addIpToBlacklist($ip, 3600, $this->lang['sys.no_such_data_was_found']);
            }
            \classes\system\Session::set('botguard_account_bad_active', $badCount);
            $res = $this->lang['sys.no_such_data_was_found'] . \classes\system\Session::get('botguard_account_bad_active') . ' ' . session_id();
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
        SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, $email, $email, $newpassword, SysClass::getClientIp());
        $sql = 'SELECT MAX(user_id) FROM ?n';
        $userId = SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE);
        SysClass::preFile('users_info', 'registrationUsers', 'Зарегистрирован новый пользователь', ['user_id' => $userId, 'email' => $email]);
        $this->setUserOptions($userId); // Заполнить первичные данные из базы по шаблону
        if ($system_id = $this->getUserIdByEmail('dont-answer@' . ENV_SITE_NAME)) {
            ClassMessages::set_message_user($userId, $system_id, 'Fill in your personal information <a href="' . ENV_URL_SITE . '/admin/user_edit/id/' . $userId . '">link</a>', 'info');
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
        if ($this->getEmailExist($fields['email'])) {
            return 0;
        }
        $sql = 'INSERT INTO ?n SET ?u';
        SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, $fields);
        $userId = SafeMySQL::gi()->insertId();
        SysClass::preFile('users_info', 'registration_new_user', 'Зарегистрирован новый пользователь', ['user_id' => $userId, 'data' => $fields]);
        $this->setUserPassword($userId, $fields['email'], $fields['pwd']);
        if ($system_id = $this->getUserIdByEmail('dont-answer@' . ENV_SITE_NAME)) {
            ClassMessages::set_message_user($userId, $system_id, 'Заполните свой профиль <a href="' . ENV_URL_SITE . '/admin/user_edit/id/' . $userId . '">тут</a>', 'info');
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
     * Устанавливает пароль для пользователя
     * Обновляет пароль в базе данных для указанного пользователя по его ID или email
     * Если пароль не передан, генерирует случайный
     * @param int $userId Идентификатор пользователя (если 0, ищет по email)
     * @param string $email Электронная почта пользователя (обязательное поле)
     * @param string $password Нешифрованный пароль (если пустой, генерируется случайный)
     * @return string Сгенерированный или переданный пароль в случае успеха
     * @throws \InvalidArgumentException Если email пустой или пользователь не найден
     * @throws \RuntimeException Если обновление пароля в БД не удалось
     */
    public function setUserPassword(int $userId, string $email, string $password = ''): string {
        if (empty($email)) {
            throw new \InvalidArgumentException('Email не может быть пустым');
        }

        try {
            $db = SafeMySQL::gi();
            if ($userId === 0) {
                $userId = $this->getUserIdByEmail($email);
                if ($userId === 0) {
                    throw new \InvalidArgumentException("Пользователь с email '$email' не найден");
                }
            }
            if (empty($password)) {
                $password = $this->generateRandomPassword(10);
            }
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            if ($hashedPassword === false) {
                throw new \RuntimeException('Не удалось сгенерировать хеш пароля');
            }
            $sql = 'UPDATE ?n SET pwd = ?s WHERE user_id = ?i';
            $affectedRows = $db->query($sql, Constants::USERS_TABLE, $hashedPassword, $userId);

            if ($affectedRows === false || $db->affectedRows() === 0) {
                throw new \RuntimeException("Не удалось обновить пароль для пользователя с ID $userId");
            }
            new \classes\system\ErrorLogger(
                    'Пароль успешно обновлён',
                    __FUNCTION__,
                    'users_info',
                    ['user_id' => $userId, 'email' => $email]
            );
            return $password;
        } catch (\Exception $e) {
            new \classes\system\ErrorLogger(
                    'Ошибка при установке пароля: ' . $e->getMessage(),
                    __FUNCTION__,
                    'users_error',
                    ['user_id' => $userId, 'email' => $email, 'trace' => $e->getTraceAsString()]
            );
            throw $e; // Перебрасываем исключение для внешней обработки
        }
    }

    /**
     * Генерирует случайный пароль заданной длины
     * @param int $length Длина пароля
     * @return string Сгенерированный пароль
     */
    private function generateRandomPassword(int $length = 10): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $charsLength = strlen($chars) - 1;
        // Используем cryptographically secure генератор
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $charsLength)];
        }
        return $password;
    }

    /**
     * Вернёт ID статуса пользователя по его ID
     */
    public function getUserStatus($userId) { // Статус пользователя 1 - на подтверждении, 2 - активен,  3 - блокирован
        $sql = 'SELECT active FROM ?n WHERE user_id = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $userId);
    }

    /**
     * Вернёт ID роли пользователя по его ID
     */
    public function getUserRole($userId) {
        $sql = 'SELECT user_role FROM ?n WHERE user_id = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $userId);
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
    public function getUserIdByEmail($email) {
        $sql = 'SELECT user_id FROM ?n WHERE email LIKE ?s';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $email);
    }

    /**
     * Вернёт email по user id
     * @param int $userId
     * @return str
     */
    public function getUserEmail($userId) {
        $sql = 'SELECT email FROM ?n WHERE user_id = ?i';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $userId);
    }

    /**
     * Проверка существования почты в БД
     */
    public function getEmailExist($email) {
        $sql = 'SELECT 1 FROM ?n WHERE email = ?s';
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $email);
    }

    /**
     * Меняет и высылает на почту пользователя новый пароль к сайту     
     */
    public function sendRecoveryPassword($email) {
        $password = $this->setUserPassword(0, $email, 0);
        $mailIsSuccess = ClassMail::sendMail($email, '', 'password_recovery', ['PASSWORD' => $password]);
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
     * Генерирует короткий уникальный код активации, отправляет письмо и сохраняет данные в таблицу активации
     * @param string $email Электронная почта пользователя
     * @return bool Возвращает true в случае успеха, false при ошибке (с логированием)
     * @throws \InvalidArgumentException Если email пустой или некорректный
     * @throws \RuntimeException Если пользователь не найден или возникла ошибка при отправке/сохранении
     */
    public function sendRegistrationCode(string $email): bool {
        if (empty($email)) {
            throw new \InvalidArgumentException('Email не может быть пустым');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Некорректный формат email');
        }
        try {
            $db = SafeMySQL::gi();
            $userId = $this->getUserIdByEmail($email);
            if ($userId === 0) {
                throw new \RuntimeException("Пользователь с email '$email' не найден");
            }
            $activationCode = bin2hex(random_bytes(8));
            $activationLink = ENV_URL_SITE . '/activation/' . $activationCode;
            $resMail = ClassMail::sendMail(
                    $email,
                    '',
                    'activation_code',
                    ['activation_link' => '<a href="' . htmlspecialchars($activationLink) . '">Нажми меня</a>']
            );
            if ($resMail !== true) {
                new \classes\system\ErrorLogger(
                        'Ошибка отправки письма с кодом активации',
                        __FUNCTION__,
                        'users_error',
                        ['email' => $email, 'activation_code' => $activationCode]
                );
                return false;
            }
            $stopTime = date('Y-m-d H:i:s', time() + (defined('ENV_TIME_ACTIVATION') ? ENV_TIME_ACTIVATION : 86400));
            $sql = 'INSERT INTO ?n (user_id, email, code, stop_time) VALUES (?i, ?s, ?s, ?s)';
            $db->query($sql, Constants::USERS_ACTIVATION_TABLE, $userId, $email, $activationCode, $stopTime);
            // Логирование успеха
            new \classes\system\ErrorLogger(
                    'Письмо с кодом активации успешно отправлено',
                    __FUNCTION__,
                    'users_info',
                    ['user_id' => $userId, 'email' => $email, 'activation_code' => $activationCode, 'stop_time' => $stopTime]
            );
            return true;
        } catch (\Exception $e) {
            new \classes\system\ErrorLogger(
                    'Ошибка при отправке кода активации: ' . $e->getMessage(),
                    __FUNCTION__,
                    'users_error',
                    ['email' => $email, 'trace' => $e->getTraceAsString()]
            );
            return false;
        }
    }

    /**
     * Удаляет регистрационный код после активации и обновляет статус пользователя
     * Проверяет актуальность кода по email и stop_time, активирует пользователя или удаляет его данные при истёкшем коде
     * @param string $email Электронная почта пользователя
     * @param string $code Код активации
     * @return bool Возвращает true при успешной активации, false если код недействителен или истёк
     * @throws \InvalidArgumentException Если email или code пустые
     * @throws \RuntimeException Если возникла ошибка при работе с базой данных
     */
    public function dellActivationCode(string $email, string $code): bool {
        if (empty($email)) {
            throw new \InvalidArgumentException('Email не может быть пустым');
        }
        if (empty($code)) {
            throw new \InvalidArgumentException('Код активации не может быть пустым');
        }
        try {
            $db = SafeMySQL::gi();
            $sql = 'SELECT user_id, stop_time FROM ?n WHERE email = ?s AND code = ?s';
            $activationData = $db->getRow($sql, Constants::USERS_ACTIVATION_TABLE, $email, $code);
            if (!$activationData) {
                new \classes\system\ErrorLogger(
                        'Код активации не найден',
                        __FUNCTION__,
                        'users_error',
                        ['email' => $email, 'code' => $code]
                );
                return false;
            }
            $userId = (int) $activationData['user_id'];
            $stopTime = $activationData['stop_time'];
            if (strtotime($stopTime) > time()) {
                $db->query('START TRANSACTION');
                try {
                    $sqlDelete = 'DELETE FROM ?n WHERE user_id = ?i AND code = ?s';
                    $db->query($sqlDelete, Constants::USERS_ACTIVATION_TABLE, $userId, $code);
                    $sqlUpdate = 'UPDATE ?n SET active = 2 WHERE user_id = ?i AND active = 1';
                    $db->query($sqlUpdate, Constants::USERS_TABLE, $userId);
                    if ($db->affectedRows() === 0) {
                        throw new \RuntimeException('Пользователь уже активирован или не существует');
                    }
                    $db->query('COMMIT');
                    new \classes\system\ErrorLogger(
                            'Код активации удалён, пользователь активирован',
                            __FUNCTION__,
                            'users_info',
                            ['user_id' => $userId, 'email' => $email, 'code' => $code]
                    );
                    return true;
                } catch (\Exception $e) {
                    $db->query('ROLLBACK');
                    throw $e;
                }
            } else {
                $this->dell_user_data($userId);
                new \classes\system\ErrorLogger(
                        'Код активации истёк, данные пользователя удалены',
                        __FUNCTION__,
                        'users_info',
                        ['user_id' => $userId, 'email' => $email, 'code' => $code]
                );
                return false;
            }
        } catch (\Exception $e) {
            new \classes\system\ErrorLogger(
                    'Ошибка при удалении кода активации: ' . $e->getMessage(),
                    __FUNCTION__,
                    'users_error',
                    ['email' => $email, 'code' => $code, 'trace' => $e->getTraceAsString()]
            );
            return false;
        }
    }

    /**
     * Создаёт необходимые таблицы в БД
     * Если нет подключения то вернёт false
     */
    public function createTables() {
        SafeMySQL::gi()->query("START TRANSACTION");
        SafeMySQL::gi()->query("SET FOREIGN_KEY_CHECKS=1"); // Включаем проверку внешних ключей
        try {
            $this->createUsersTable();
            $this->createUsersRolesTable();
            $this->insertDefaultRoles();
            $this->createUsersDataTable();
            $this->createUsersMessageTable();
            $this->createUsersActivationTable();
            $this->createCategoriesTypesTable();
            $this->createCategoriesTable();
            $this->createPagesTable();
            $this->createPropertyTypesTable();
            $this->createPropertiesTable();
            $this->createPropertySetsTable();
            $this->createPropertyValuesTable();
            $this->createCategoryTypeToPropertySetTable();
            $this->createPropertySetToPropertiesTable();
            $this->createSearchContentsTable();
            $this->createFilesTable();
            $this->createGlobalOptionsTable();
            $this->createEmailTemplatesTable();
            $this->createEmailSnippetsTable();
            $this->createIpBlacklistTable();
            // Вставка начальных данных
            $this->insertDefaultPropertyTypes();
            $this->insertDefaultEmailSnippets();
            $this->insertDefaultEmailTemplates();
            // Тестовые данные
            if (defined('ENV_INSERT_TEST_DATA') && ENV_INSERT_TEST_DATA) {
                $this->insertTestData();
            }
            SafeMySQL::gi()->query("COMMIT");
        } catch (Exception $e) {
            SafeMySQL::gi()->query("ROLLBACK");
            SysClass::pre($e);
            return false;
        }
        SysClass::preFile('sql_info', 'create_tables', 'База данных успешно развёрнута', 'OK');
    }

    // Метод для создания таблицы пользователей
    private function createUsersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
            user_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            name char(255) NOT NULL DEFAULT 'no name',
            email char(255) NOT NULL,
            pwd varchar(255) NOT NULL,
            active tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 - на подтверждении, 2 - активен, 3 - блокирован',
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
        SafeMySQL::gi()->query($sql, Constants::USERS_TABLE);
    }

    /**
     * Создаёт таблицу users_roles для хранения ролей пользователей
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createUsersRolesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
        role_id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
        role_key VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        PRIMARY KEY (role_id),
        UNIQUE KEY (role_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица ролей пользователей';";
        SafeMySQL::gi()->query($sql, Constants::USERS_ROLES_TABLE);
        SysClass::preFile('sql_info', 'create_users_roles_table', 'Таблица ролей пользователей создана', 'OK');
    }

    // Метод для вставки ролей
    private function insertDefaultRoles() {
        $sql = "INSERT INTO ?n (role_id, role_key, name) VALUES
            (1, 'admin', 'Администратор'),
            (2, 'moderator', 'Модератор'),
            (3, 'manager', 'Менеджер'),
            (4, 'user', 'Пользователь'),
            (8, 'system', 'Система')
            ON DUPLICATE KEY UPDATE name = VALUES(name);";
        SafeMySQL::gi()->query($sql, Constants::USERS_ROLES_TABLE);
    }

    /**
     * Создаёт таблицу users_data для хранения системных данных пользователей
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createUsersDataTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
            data_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id int(11) UNSIGNED NOT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время любого изменения данных',
            options text NOT NULL COMMENT 'Настройки интерфейса пользователя',
            PRIMARY KEY (data_id),
            FOREIGN KEY (user_id) REFERENCES ?n(user_id) ON DELETE CASCADE
        ) ENGINE=innodb DEFAULT CHARSET=utf8 COMMENT='Системные данные пользователей';";
        SafeMySQL::gi()->query($sql, Constants::USERS_DATA_TABLE, Constants::USERS_TABLE);
        SysClass::preFile('sql_info', 'create_users_data_table', 'Таблица системных данных пользователей создана', 'OK');
    }

    /**
     * Создаёт таблицу users_message для хранения сообщений пользователей
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createUsersMessageTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
           message_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
           user_id int(11) UNSIGNED NOT NULL,
           author_id int(11) UNSIGNED NOT NULL,
           chat_id int(11) UNSIGNED NULL COMMENT 'Зарезервирован для групповых чатов',
           message_text varchar(1000) NOT NULL,
           created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
           read_at datetime DEFAULT NULL,
           status varchar(10) NOT NULL DEFAULT 'info',
           PRIMARY KEY (message_id),
           FOREIGN KEY (user_id) REFERENCES ?n(user_id) ON DELETE CASCADE,
           FOREIGN KEY (author_id) REFERENCES ?n(user_id) ON DELETE CASCADE
       ) ENGINE=innodb DEFAULT CHARSET=utf8;";
        SafeMySQL::gi()->query($sql, Constants::USERS_MESSAGE_TABLE, Constants::USERS_TABLE, Constants::USERS_TABLE);
        SysClass::preFile('sql_info', 'create_users_message_table', 'Таблица сообщений пользователей создана', 'OK');
    }

    /**
     * Создаёт таблицу users_activation для хранения кодов активации пользователей
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createUsersActivationTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
            id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            email varchar(255) NOT NULL,
            code varchar(255) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            stop_time datetime NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=innodb DEFAULT CHARSET=utf8 COMMENT='Коды активации для зарегистрировавшихся пользователей';";
        SafeMySQL::gi()->query($sql, Constants::USERS_ACTIVATION_TABLE);
        SysClass::preFile('sql_info', 'create_users_activation_table', 'Таблица кодов активации создана', 'OK');
    }

    /**
     * Создаёт таблицу categories_types для хранения типов категорий
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createCategoriesTypesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
        type_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        parent_type_id INT UNSIGNED NULL,
        name VARCHAR(255) NOT NULL UNIQUE,
        description VARCHAR(1000),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
        FOREIGN KEY (parent_type_id) REFERENCES ?n(type_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения типов сущностей и категорий';";
        SafeMySQL::gi()->query($sql, Constants::CATEGORIES_TYPES_TABLE, Constants::CATEGORIES_TYPES_TABLE);
        SysClass::preFile('sql_info', 'create_categories_types_table', 'Таблица типов категорий создана', 'OK');
    }

    /**
     * Создаёт таблицу categories для хранения категорий сущностей
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createCategoriesTable() {
        // Шаг 1: Создание таблицы без внешних ключей
        $sqlStep1 = "CREATE TABLE IF NOT EXISTS ?n (
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
        SafeMySQL::gi()->query($sqlStep1, Constants::CATEGORIES_TABLE);
        // Шаг 2: Добавление внешних ключей
        $sqlStep2 = "ALTER TABLE ?n 
        ADD FOREIGN KEY (type_id) REFERENCES ?n(type_id),
        ADD FOREIGN KEY (parent_id) REFERENCES ?n(category_id);";
        SafeMySQL::gi()->query($sqlStep2, Constants::CATEGORIES_TABLE, Constants::CATEGORIES_TYPES_TABLE, Constants::CATEGORIES_TABLE);

        SysClass::preFile('sql_info', 'create_categories_table', 'Таблица категорий создана', 'OK');
    }

    /**
     * Создаёт таблицу pages для хранения страниц
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createPagesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
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
        SafeMySQL::gi()->query($sql, Constants::PAGES_TABLE, Constants::CATEGORIES_TABLE);
        SysClass::preFile('sql_info', 'create_pages_table', 'Таблица страниц создана', 'OK');
    }

    /**
     * Создаёт таблицу property_types для хранения типов свойств
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createPropertyTypesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
        type_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        status ENUM('active', 'hidden', 'disabled') NOT NULL DEFAULT 'active',
        fields JSON NOT NULL,
        description VARCHAR(1000),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения типов свойств';";
        SafeMySQL::gi()->query($sql, Constants::PROPERTY_TYPES_TABLE);
        SysClass::preFile('sql_info', 'create_property_types_table', 'Таблица типов свойств создана', 'OK');
    }

    /**
     * Создаёт таблицу properties для хранения свойств
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createPropertiesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
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
        SafeMySQL::gi()->query($sql, Constants::PROPERTIES_TABLE, Constants::PROPERTY_TYPES_TABLE);
        SysClass::preFile('sql_info', 'create_properties_table', 'Таблица свойств создана', 'OK');
    }

    /**
     * Создаёт таблицу property_sets для хранения наборов свойств
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createPropertySetsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
        set_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description MEDIUMTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения наборов свойств';";

        SafeMySQL::gi()->query($sql, Constants::PROPERTY_SETS_TABLE);
        SysClass::preFile('sql_info', 'create_property_sets_table', 'Таблица наборов свойств создана', 'OK');
    }

    /**
     * Создаёт таблицу property_values для хранения значений свойств в формате JSON
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createPropertyValuesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
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
        SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, Constants::PROPERTIES_TABLE, Constants::PROPERTY_SETS_TABLE);
        SysClass::preFile('sql_info', 'create_property_values_table', 'Таблица значений свойств создана', 'OK');
    }

    /**
     * Создаёт таблицу category_type_to_property_set для связи типов категорий и наборов свойств
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createCategoryTypeToPropertySetTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
        type_id INT UNSIGNED NOT NULL,
        set_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (type_id, set_id),
        FOREIGN KEY (type_id) REFERENCES ?n(type_id),
        FOREIGN KEY (set_id) REFERENCES ?n(set_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для связи типов категорий и наборов свойств';";
        SafeMySQL::gi()->query($sql, Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE, Constants::CATEGORIES_TYPES_TABLE, Constants::PROPERTY_SETS_TABLE);
        SysClass::preFile('sql_info', 'create_category_type_to_property_set_table', 'Таблица связи типов категорий и наборов свойств создана', 'OK');
    }

    /**
     * Создаёт таблицу property_set_to_properties для связи наборов свойств и свойств
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createPropertySetToPropertiesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
        set_id INT UNSIGNED NOT NULL,
        property_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (set_id, property_id),
        FOREIGN KEY (set_id) REFERENCES ?n(set_id),
        FOREIGN KEY (property_id) REFERENCES ?n(property_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для связи наборов свойств и свойств';";
        SafeMySQL::gi()->query($sql, Constants::PROPERTY_SET_TO_PROPERTIES_TABLE, Constants::PROPERTY_SETS_TABLE, Constants::PROPERTIES_TABLE);
        SysClass::preFile('sql_info', 'create_property_set_to_properties_table', 'Таблица связи наборов свойств и свойств создана', 'OK');
    }

    /**
     * Создаёт таблицу search_contents для глобального поиска по сайту
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createSearchContentsTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
        search_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        entity_id INT UNSIGNED NOT NULL,
        entity_type VARCHAR(50) NOT NULL,
        area CHAR(1) NOT NULL DEFAULT 'A' COMMENT 'Локация поиска: A - админпанель, C - клиентская часть',
        full_search_content TEXT NOT NULL,
        short_search_content VARCHAR(255) NOT NULL,
        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
        relevance_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FULLTEXT idx_full_search (full_search_content, short_search_content),
        INDEX idx_short_search (short_search_content(50)),
        INDEX idx_area_entity (area, entity_type, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
    COMMENT='Таблица для глобального поиска по сайту';";

        SafeMySQL::gi()->query($sql, Constants::SEARCH_CONTENTS_TABLE);
        SysClass::preFile('sql_info', 'create_search_contents_table', 'Таблица для поиска по сайту создана', 'OK');
    }

    /**
     * Создаёт таблицу files для хранения информации о загруженных файлах
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createFilesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для сохранения информации о файлах';";
        SafeMySQL::gi()->query($sql, Constants::FILES_TABLE, Constants::USERS_TABLE);
        SysClass::preFile('sql_info', 'create_files_table', 'Таблица файлов создана', 'OK');
    }

    /**
     * Создаёт таблицу global_options для хранения глобальных опций
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createGlobalOptionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
        option_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Уникальный идентификатор опции',
        option_key VARCHAR(255) NOT NULL COMMENT 'Уникальный ключ опции',
        option_value TEXT NOT NULL COMMENT 'Значение опции (в виде текста, без преобразования)',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания записи',
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Дата обновления записи',
        UNIQUE KEY (option_key) COMMENT 'Уникальность ключа опции'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения глобальных опций';";
        SafeMySQL::gi()->query($sql, Constants::GLOBAL_OPTIONS);
        SysClass::preFile('sql_info', 'create_global_options_table', 'Таблица глобальных опций создана', 'OK');
    }

    /**
     * Создаёт таблицу email_templates для хранения почтовых шаблонов
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createEmailTemplatesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
        template_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Название шаблона',
        subject VARCHAR(255) NOT NULL COMMENT 'Тема письма',
        body LONGTEXT NOT NULL COMMENT 'HTML тело письма',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
        description VARCHAR(1000) NULL COMMENT 'Описание шаблона',
        UNIQUE KEY (name, language_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения почтовых шаблонов';";
        SafeMySQL::gi()->query($sql, Constants::EMAIL_TEMPLATES_TABLE);
        SysClass::preFile('sql_info', 'create_email_templates_table', 'Таблица почтовых шаблонов создана', 'OK');
    }

    /**
     * Создаёт таблицу email_snippets для хранения HTML-сниппетов
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createEmailSnippetsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
        snippet_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Уникальное имя сниппета',
        content TEXT NOT NULL COMMENT 'HTML содержимое сниппета',
        description VARCHAR(1000) NULL COMMENT 'Описание сниппета',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
        UNIQUE KEY (name, language_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения HTML сниппетов';";
        SafeMySQL::gi()->query($sql, Constants::EMAIL_SNIPPETS_TABLE);
        SysClass::preFile('sql_info', 'create_email_snippets_table', 'Таблица HTML-сниппетов создана', 'OK');
    }

    /**
     * Создаёт таблицу ip_blacklist для хранения заблокированных IP-адресов и диапазонов
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createIpBlacklistTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip_range VARCHAR(255) NOT NULL COMMENT 'IP-адрес или диапазон (например, 192.168.1.1 или 10.0.0.0/24)',
        block_until DATETIME NOT NULL COMMENT 'Время, до которого IP заблокирован',
        reason VARCHAR(255) NULL COMMENT 'Причина блокировки',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX ip_range_idx (ip_range)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Таблица для хранения заблокированных IP-адресов и диапазонов';";
        SafeMySQL::gi()->query($sql, Constants::IP_BLACKLIST_TABLE);
        SysClass::preFile('sql_info', 'create_ip_blacklist_table', 'Таблица заблокированных IP-адресов создана', 'OK');
    }

    /**
     * Вставляет начальные данные о типах свойств и связанных свойствах
     * @throws Exception Если запрос не удалось выполнить
     */
    private function insertDefaultPropertyTypes() {
        // Определяем начальные типы свойств
        $propertyTypes = [
            [
                'name' => 'Строка',
                'description' => 'Тип свойства для хранения строковых данных',
                'status' => 'active',
                'fields' => '["text"]'
            ],
            [
                'name' => 'Число',
                'description' => 'Тип свойства для хранения числовых данных',
                'status' => 'active',
                'fields' => '["number"]'
            ],
            [
                'name' => 'Дата',
                'description' => 'Тип свойства для хранения дат',
                'status' => 'active',
                'fields' => '["date"]'
            ],
            [
                'name' => 'Интервал дат',
                'description' => 'Тип свойства для хранения интервалов дат',
                'status' => 'active',
                'fields' => '["date", "date"]'
            ],
            [
                'name' => 'Картинка',
                'description' => 'Тип свойства для хранения изображений',
                'status' => 'active',
                'fields' => '["image"]'
            ],
            [
                'name' => 'SEO-параметры',
                'description' => "meta_title (string): заголовок, отображающийся в title\n" .
                "slug (text): ЧПУ/короткий URL\n" .
                "meta_description (text): мета-описание\n" .
                "meta_keywords (text): ключевые слова (менее актуально, но может пригодиться)\n" .
                "canonical_url (text): канонический URL\n" .
                "robots_meta (select): директивы для роботов (index, noindex, follow, nofollow и т.д.)\n" .
                "open_graph_title (text): заголовок для соцсетей (если не заполнено, используется meta_title)\n" .
                "open_graph_description (text): описание для соцсетей (если не заполнено, используется meta_description)\n" .
                "open_graph_image (image): изображение для соцсетей (Open Graph)\n" .
                "Комментарий: Поля open_graph_* дают гибкость в оформлении публикации в соцсетях. Обычно нужны «для всех» — чтобы и категориям, и страницам при желании настраивать SEO.",
                'status' => 'active',
                'fields' => '["text", "text", "text", "text", "text", "select", "text", "text", "image"]'
            ],
            [
                'name' => 'Основные свойства страницы',
                'description' => "author (text): автор материала\n" .
                "visibility (select): публичная, приватная, черновик и т.д.\n" .
                "page_status (select): опубликована, черновик, на проверке и т.д.\n" .
                "Комментарий: Обычно эти поля нужны только для страниц, но в некоторых случаях (например, content или last_updated) могут использоваться и в категориях.",
                'status' => 'active',
                'fields' => '["text", "select", "select"]'
            ],
            [
                'name' => 'Социальные элементы',
                'description' => "allow_comments (Разрешить комментарии: да/нет)\n" .
                "allow_sharing (Разрешить поделиться в соцсетях: да/нет)\n" .
                "comment_count (Количество комментариев)\n" .
                "like_count (Количество лайков)",
                'status' => 'active',
                'fields' => '["checkbox", "checkbox", "number", "number"]'
            ],
            [
                'name' => 'Параметры для карточек товаров',
                'description' => "price (Цена товара)\n" .
                "list_price (Рекомендованная цена)\n" .
                "discount (Скидка)\n" .
                "stock_status (Наличие на складе)\n" .
                "sku (Артикул)\n" .
                "brand (Бренд товара)\n" .
                "rating (Рейтинг товара)\n" .
                "review_count (Количество отзывов)",
                'status' => 'active',
                'fields' => '["text", "text", "number", "text", "text", "number", "number"]'
            ],
            [
                'name' => 'Мультимедийные элементы',
                'description' => "featured_image (Изображение страницы)\n" .
                "gallery (Галерея изображений)\n" .
                "video_url (URL видео)\n" .
                "file_attachments (Вложения файлов)",
                'status' => 'active',
                'fields' => '["image", "image", "text", "file"]'
            ],
            [
                'name' => 'Структурные свойства',
                'description' => "parent_category (Родительская категория)\n" .
                "related_pages (Связанные страницы)\n" .
                "breadcrumb_navigation (Навигация по хлебным крошкам)",
                'status' => 'active',
                'fields' => '["text", "text", "text"]'
            ],
            [
                'name' => 'Дополнительные параметры',
                'description' => "custom_css (Пользовательский CSS)\n" .
                "custom_js (Пользовательский JavaScript)\n" .
                "redirect_url (URL для редиректа)",
                'status' => 'active',
                'fields' => '["text", "text", "text"]'
            ],
        ];
        // Очистка description от лишних пробелов и TAB
        foreach ($propertyTypes as &$type) {
            if (isset($type['description'])) {
                $type['description'] = preg_replace('/[ \t]+/', ' ', $type['description']); // Заменяем группы пробелов и TAB на один пробел
                $type['description'] = preg_replace('/\s*\n\s*/', "\n", $type['description']); // Убираем пробелы и TAB вокруг переноса строк
            }
        }

        // Получаем модель для работы с типами свойств
        $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
        if (!$objectModelProperties) {
            SysClass::preFile('sql_error', 'insert_default_property_types', 'Не удалось загрузить модель m_properties', 'ERROR');
            throw new Exception('Не удалось загрузить модель m_properties');
        }
        // Вставка типов свойств и создание связанных свойств
        foreach ($propertyTypes as &$type) {
            // Сохраняем тип свойства
            $type['type_id'] = $objectModelProperties->updatePropertyTypeData($type);
            // Подготавливаем данные для создания свойства
            $propertyData = [
                'type_id' => $type['type_id'],
                'name' => $type['name'],
                'entity_type' => 'all',
                'default_values' => [],
                'description' => $type['description']
            ];

            // Определяем параметры свойства в зависимости от типа
            $propParams = [];
            switch ($type['name']) {
                case 'SEO-параметры':
                    $propParams = [
                        'Заголовок, отображающийся в title' => 'text',
                        'ЧПУ/короткий URL' => 'text',
                        'Мета-описание' => 'text',
                        'Ключевые слова' => 'text',
                        'Канонический URL' => 'text',
                        'Директивы для роботов' => ['select', '{|}index=index{|}noindex=noindex{|}follow=follow{|}nofollow=nofollow'],
                        'Заголовок для соцсетей' => 'text',
                        'Описание для соцсетей' => 'text',
                        'Изображение для соцсетей' => 'image'
                    ];
                    break;
                case 'Основные свойства страницы':
                    $propertyData['entity_type'] = 'page';
                    $propParams = [
                        'Автор материала' => 'text',
                        'Видимость' => 'select',
                        'Публичный статус' => 'select'
                    ];
                    break;
                case 'Социальные элементы':
                    $propParams = [
                        'Разрешить комментарии' => 'checkbox',
                        'Разрешить поделиться в соцсетях' => 'checkbox',
                        'Количество комментариев' => 'number',
                        'Количество лайков' => 'number'
                    ];
                    break;
                case 'Параметры для карточек товаров':
                    $propertyData['entity_type'] = 'page';
                    $propParams = [
                        'Цена товара' => 'text',
                        'Скидка' => 'text',
                        'Наличие на складе' => 'number',
                        'Артикул' => 'text',
                        'Бренд товара' => 'text',
                        'Рейтинг товара' => 'number',
                        'Количество отзывов' => 'number'
                    ];
                    break;
                case 'Мультимедийные элементы':
                    $propParams = [
                        'Изображение страницы' => 'image',
                        'Галерея изображений' => 'image',
                        'URL видео' => 'text',
                        'Вложения файлов' => 'file'
                    ];
                    break;
                case 'Структурные свойства':
                    $propertyData['entity_type'] = 'category';
                    $propParams = [
                        'Родительская категория' => 'text',
                        'Связанные страницы' => 'text',
                        'Навигация по хлебным крошкам' => 'text'
                    ];
                    break;
                case 'Дополнительные параметры':
                    $propParams = [
                        'Пользовательский CSS' => 'text',
                        'Пользовательский JavaScript' => 'text',
                        'URL для редиректа' => 'text'
                    ];
                    break;
                default:
                    break;
            }
            // Формируем default_values для свойства
            foreach ($propParams as $itemLabel => $fieldType) {
                if (is_array($fieldType)) { // Для select
                    $propertyData['default_values'][] = [
                        'type' => $fieldType[0],
                        'label' => $itemLabel,
                        'default' => $fieldType[1],
                        'multiple' => 0,
                        'required' => 0
                    ];
                } else {
                    if ($fieldType == 'checkbox' || $fieldType == 'radio') {
                        $propertyData['default_values'][] = [
                            'title' => $itemLabel,
                            'count' => 1,
                            'type' => $fieldType,
                            'label' => [$itemLabel],
                            'default' => '',
                            'multiple' => 0,
                            'required' => 0
                        ];
                    } else {
                        $propertyData['default_values'][] = [
                            'type' => $fieldType,
                            'label' => $itemLabel,
                            'default' => '',
                            'multiple' => 0,
                            'required' => 0
                        ];
                    }
                }
            }
            // Создаём свойство, если есть default_values
            if (count($propertyData['default_values'])) {
                $objectModelProperties->updatePropertyData($propertyData);
            }
        }
        SysClass::preFile('sql_info', 'insert_default_property_types', 'Начальные типы свойств и свойства добавлены', 'OK');
    }

    /**
     * Вставляет начальные HTML-сниппеты в таблицу email_snippets
     * @throws Exception Если запрос не удалось выполнить
     */
    private function insertDefaultEmailSnippets() {
        $emailSnippets = [
            [
                'name' => 'SNIP_HEADER',
                'content' => "<!DOCTYPE html>
<html>
<head>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 24px;
            color: #333;
            text-align: center;
        }
        p {
            font-size: 16px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>",
                'description' => 'Заголовок с HTML кодом который обрезается обычным редактором.',
                'language_code' => 'RU'
            ],
            [
                'name' => 'SNIP_FOOTER',
                'content' => "</body></html>",
                'description' => 'Подвал с HTML кодом который обрезается обычным редактором.',
                'language_code' => 'RU'
            ]
        ];
        // Вставка сниппетов
        foreach ($emailSnippets as $snippet) {
            $sql = "INSERT INTO ?n (name, content, description, language_code) VALUES (?s, ?s, ?s, ?s)";
            SafeMySQL::gi()->query(
                    $sql,
                    Constants::EMAIL_SNIPPETS_TABLE,
                    $snippet['name'],
                    $snippet['content'],
                    $snippet['description'],
                    $snippet['language_code']
            );
        }
        SysClass::preFile('sql_info', 'insert_default_email_snippets', 'Начальные HTML-сниппеты добавлены', 'OK');
    }

    /**
     * Вставляет начальные почтовые шаблоны в таблицу email_templates
     * @throws Exception Если запрос не удалось выполнить
     */
    private function insertDefaultEmailTemplates() {
        $emailTemplates = [
            [
                'name' => 'user_registration',
                'subject' => 'Регистрация на сайте {{ENV_DOMEN_NAME}}',
                'body' => "{{SNIP_HEADER}}
<div class='container'>
    <h1>Добро пожаловать!</h1>
    <p>С уважением,<br>Команда проекта {{ENV_DOMEN_NAME}}</p>
    <p>Если Вы не совершали регистрацию то просто проигнорируйте это письмо.</p>
    <p>Для связи с нами обратитесь на почту {{ENV_SUPPORT_EMAIL}}</p>
</div>{{SNIP_FOOTER}}",
                'description' => 'Шаблон для уведомления о регистрации пользователя',
                'language_code' => 'RU'
            ],
            [
                'name' => 'activation_code',
                'subject' => 'Ваша ссылка для активации',
                'body' => "{{SNIP_HEADER}}
<div class='container'>
    <h1>Ссылка для активации аккаунта</h1>
    <p>[activation_link]</p>
    <p>Если Вы не совершали регистрацию то просто проигнорируйте это письмо.</p>
    <p>С уважением,<br>Команда проекта {{ENV_DOMEN_NAME}}</p>
    <p>Для связи с нами обратитесь на почту {{ENV_SUPPORT_EMAIL}}</p>
</div>{{SNIP_FOOTER}}",
                'description' => 'Шаблон для уведомления о коде активации',
                'language_code' => 'RU'
            ],
            [
                'name' => 'data_change',
                'subject' => 'Ваши данные обновлены',
                'body' => "{{SNIP_HEADER}}
<div class='container'>
    <h1>Уведомление об изменении данных</h1>
    <p>Ваши данные на сайте {{ENV_DOMEN_NAME}} были изменены!</p>
    <p>С уважением,<br>Команда проекта {{ENV_DOMEN_NAME}}</p>
    <p>Для связи с нами обратитесь на почту {{ENV_SUPPORT_EMAIL}}</p>
</div>{{SNIP_FOOTER}}",
                'description' => 'Шаблон для уведомления об изменении данных пользователя',
                'language_code' => 'RU'
            ],
            [
                'name' => 'account_activated',
                'subject' => 'Ваш аккаунт активирован на {{ENV_DOMEN_NAME}}',
                'body' => "{{SNIP_HEADER}}
<div class='container'>
    <h1>Аккаунт успешно активирован!</h1>
    <p>Поздравляем! Ваш аккаунт на сайте {{ENV_DOMEN_NAME}} успешно активирован.</p>
    <p>Теперь вы можете войти, используя свои учетные данные.</p>
    <p>С уважением,<br>Команда проекта {{ENV_DOMEN_NAME}}</p>
    <p>Для связи с нами обратитесь на почту {{ENV_SUPPORT_EMAIL}}</p>
</div>{{SNIP_FOOTER}}",
                'description' => 'Шаблон для уведомления об успешной активации аккаунта',
                'language_code' => 'RU'
            ],
            [
                'name' => 'password_recovery',
                'subject' => 'Восстановление пароля на {{ENV_DOMEN_NAME}}',
                'body' => "{{SNIP_HEADER}}
<div class='container'>
    <h1>Восстановление пароля</h1>
    <p>Ваш новый пароль для входа на сайт {{ENV_DOMEN_NAME}}: [PASSWORD]</p>
    <p>Рекомендуем сменить пароль после входа в систему для вашей безопасности.</p>
    <p>Если вы не запрашивали восстановление пароля, пожалуйста, свяжитесь с нами.</p>
    <p>С уважением,<br>Команда проекта {{ENV_DOMEN_NAME}}</p>
    <p>Для связи с нами обратитесь на почту {{ENV_SUPPORT_EMAIL}}</p>
</div>{{SNIP_FOOTER}}",
                'description' => 'Шаблон для уведомления о восстановлении пароля с новым паролем',
                'language_code' => 'RU'
            ]
        ];

        // Вставка шаблонов
        foreach ($emailTemplates as $template) {
            $sql = "INSERT INTO ?n (name, subject, body, description, language_code) 
                VALUES (?s, ?s, ?s, ?s, ?s) 
                ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body), description = VALUES(description), language_code = VALUES(language_code)";
            SafeMySQL::gi()->query(
                    $sql,
                    Constants::EMAIL_TEMPLATES_TABLE,
                    $template['name'],
                    $template['subject'],
                    $template['body'],
                    $template['description'],
                    $template['language_code']
            );
        }

        SysClass::preFile('sql_info', 'insert_default_email_templates', 'Начальные почтовые шаблоны добавлены', 'OK');
    }

    /**
     * Вставляет тестовые данные для категорий, страниц и наборов свойств.
     * Используется только в тестовой среде.
     * @throws Exception Если запрос не удалось выполнить.
     */
    private function insertTestData() {
        // Получаем необходимые модели
        $objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
        $objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
        $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
        $objectModelPages = SysClass::getModelObject('admin', 'm_pages');

        if (!$objectModelCategoriesTypes || !$objectModelCategories || !$objectModelProperties || !$objectModelPages) {
            SysClass::preFile('sql_error', 'insert_test_data', 'Не удалось загрузить одну из моделей', 'ERROR');
            throw new Exception('Не удалось загрузить одну из моделей');
        }

        // 1. Создаём наборы свойств
        $setsData = [
            [
                'set_id' => '0',
                'name' => 'Курорты',
                'description' => 'Для категории курорты'
            ],
            [
                'set_id' => '0',
                'name' => 'Объекты',
                'description' => 'Для страниц объектов'
            ],
            [
                'set_id' => '0',
                'name' => 'Социалочка',
                'description' => 'Для дополнения'
            ]
        ];
        foreach ($setsData as $setData) {
            $objectModelProperties->updatePropertySetData($setData);
        }

        // 2. Связываем наборы свойств с определёнными свойствами
        $objectModelProperties->addPropertiesToSet(1, [1, 5, 6]); // Для курортов: Строка, Картинка, SEO-параметры
        $objectModelProperties->addPropertiesToSet(2, [1, 2, 4, 7]); // Для объектов: Строка, Число, Интервал дат, Основные свойства страницы
        $objectModelProperties->addPropertiesToSet(3, [3]); // Для дополнения: Социальные элементы
        // 3. Создаём типы категорий и связываем их с наборами свойств
        $categoriesTypeData = [
            [
                'typeData' => [
                    'type_id' => '0',
                    'name' => 'Для курортов',
                    'parent_type_id' => NULL,
                    'description' => ''
                ],
                'catSetData' => [1, 3] // Курорты + Социалочка
            ],
            [
                'typeData' => [
                    'type_id' => '0',
                    'name' => 'Для объектов',
                    'parent_type_id' => 1,
                    'description' => ''
                ],
                'catSetData' => [2] // Объекты
            ]
        ];
        foreach ($categoriesTypeData as $catTypeData) {
            $type_id = $objectModelCategoriesTypes->updateCategoriesTypeData($catTypeData['typeData']);
            $objectModelCategoriesTypes->updateCategoriesTypeSetsData($type_id, $catTypeData['catSetData']);
        }

        // 4. Создаём категории
        $categoriesData = [
            [
                'category_id' => 0,
                'title' => 'Курорты',
                'type_id' => 1,
                'parent_id' => 0,
                'status' => 'active',
                'short_description' => 'Главная',
                'description' => 'Главная'
            ],
            [
                'category_id' => 0,
                'title' => 'Курорты Азовского моря в России',
                'type_id' => 1,
                'parent_id' => 1,
                'status' => 'active',
                'short_description' => '-Дочерняя',
                'description' => '-Дочерняя'
            ],
            [
                'category_id' => 0,
                'title' => 'Курорты Черного моря в Абхазии',
                'type_id' => 1,
                'parent_id' => 1,
                'status' => 'active',
                'short_description' => '-Дочерняя',
                'description' => '-Дочерняя'
            ],
            [
                'category_id' => 0,
                'title' => 'Голубицкая',
                'type_id' => 2,
                'parent_id' => 2,
                'status' => 'active',
                'short_description' => '--Дочерняя',
                'description' => '--Дочерняя'
            ],
            [
                'category_id' => 0,
                'title' => 'Должанская',
                'type_id' => 2,
                'parent_id' => 2,
                'status' => 'active',
                'short_description' => '--Дочерняя',
                'description' => '--Дочерняя'
            ],
            [
                'category_id' => 0,
                'title' => 'Сухум',
                'type_id' => 2,
                'parent_id' => 3,
                'status' => 'active',
                'short_description' => '--Дочерняя',
                'description' => '--Дочерняя'
            ]
        ];
        foreach ($categoriesData as $catData) {
            $objectModelCategories->updateCategoryData($catData);
        }

        // 5. Создаём страницы
        $pages = [
            [
                'page_id' => 0,
                'title' => 'Гостиница «Морской компотик»',
                'category_id' => 4,
                'parent_page_id' => 0,
                'status' => 'active',
                'short_description' => 'Голубицкая, ул. Курортная, 69',
                'description' => ''
            ],
            [
                'page_id' => 0,
                'title' => 'Гостевой дом у моря',
                'category_id' => 4,
                'parent_page_id' => 0,
                'status' => 'active',
                'short_description' => 'Десантников освободителей 20',
                'description' => ''
            ],
            [
                'page_id' => 0,
                'title' => 'Гостиничный комплекс «МЫС»',
                'category_id' => 5,
                'parent_page_id' => 0,
                'status' => 'active',
                'short_description' => 'Должанская, Знаменский переулок, 16а',
                'description' => ''
            ],
            [
                'page_id' => 0,
                'title' => 'Студия в центре Сухум на Чачба',
                'category_id' => 6,
                'parent_page_id' => 0,
                'status' => 'active',
                'short_description' => 'Абхазия, Сухум, Чачба',
                'description' => ''
            ],
            [
                'page_id' => 0,
                'title' => 'Hotel in Sukhum',
                'category_id' => 6,
                'parent_page_id' => 0,
                'status' => 'active',
                'short_description' => 'Сухум, ул. Званба, 21',
                'description' => ''
            ]
        ];
        foreach ($pages as $page) {
            $objectModelPages->updatePageData($page);
        }
        SysClass::preFile('sql_info', 'insert_test_data', 'Тестовые данные добавлены', 'OK');
    }
}
