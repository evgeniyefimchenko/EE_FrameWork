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
            . '"skin": "skin-default", "auth": {"require_password_setup": 0, "password_setup_reason": "", "last_password_prompt_at": null, "ip_restricted": 0}}';

    public $lang = []; // Языковые переменные для текущего класса
    public $data;
    public array $lastAuthResult = [];
    public int $lastCreatedUserId = 0;

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
        if (empty($userId) && !$create_table) {
            $user_lang = ENV_DEF_LANG;
        } else {
            $user_lang = Session::get('lang') ?: ENV_DEF_LANG;
        }
        $this->data = $this->getUserData($userId, $create_table);
        $this->lang = !empty($user_lang) ? Lang::init($user_lang) : [];
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
                $resArray['messages'] = $resArray['count_unread_messages'] ? ClassMessages::get_unread_messages_user($id) : [];
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
            $this->registrationNewUser(array('name' => 'admin', 'email' => 'test@test.com', 'active' => '2', 'user_role' => '1', 'subscribed' => '0', 'comment' => 'Смените пароль администратора', 'pwd' => 'admin', 'skip_auth_password_setup' => 1), true);
            $this->registrationNewUser(array('name' => 'moderator', 'email' => 'test_moderator@test.com', 'active' => '2', 'user_role' => '2', 'subscribed' => '0', 'comment' => 'Смените пароль модератора', 'pwd' => 'moderator', 'skip_auth_password_setup' => 1), true);
            $this->registrationNewUser(array('name' => 'system', 'email' => 'dont-answer@' . ENV_SITE_NAME, 'active' => '2', 'user_role' => '8', 'subscribed' => '0', 'comment' => '', 'pwd' => '', 'skip_auth_password_setup' => 1), true);
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
        $oldEmail = $this->getUserEmail($userId);
        if (isset($fields['email']) && !SysClass::validEmail($fields['email'])) {
            return 0;
        }
        if (isset($fields['email']) && $fields['email'] !== $oldEmail && $this->getEmailExist($fields['email'], $userId)) {
            return 0;
        }
        if (isset($fields['pwd']) && strlen($fields['pwd']) >= 5) {
            $this->setUserPassword($userId, $fields['email'] ?? $oldEmail, $fields['pwd']);
        }
        unset($fields['pwd']);
        $sql_user = "UPDATE ?n SET ?u, updated_at = now() WHERE user_id = ?i";
        SafeMySQL::gi()->query($sql_user, Constants::USERS_TABLE, $fields, $userId);
        if (isset($fields['email']) && $fields['email'] !== $oldEmail) {
            (new AuthService())->handleEmailChange($userId, (string) $oldEmail, (string) $fields['email']);
        }
        $this->logUserAudit('set_user_data', 'Обновление данных пользователю user_id=' . $userId, [
            'old' => $this->data['user_id'],
            'new' => $fields,
        ]);
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
        return $this->normalizeUserOptions($options);
    }

    /**
     * Записывает персональные настройки пользователя
     * настройки интерфейса, аватар, фото
     * @param int $userId - идентификатор пользователя в БД
     * @param array $options - массив с настройками
     * @return true
     */
    public function setUserOptions($userId, $options = '') {
        $normalizedOptions = $this->normalizeUserOptions($options);
        $options = json_encode($normalizedOptions, JSON_UNESCAPED_UNICODE);
        if ($options === false) {
            $options = self::BASE_OPTIONS_USER;
        }
        if ($this->issetOptionsUser($userId) > 0) {
            $sql = 'UPDATE ?n SET options = ?s WHERE user_id = ?i';
        } else {
            $sql = 'INSERT INTO ?n SET options = ?s, user_id = ?i';
        }
        return SafeMySQL::gi()->query($sql, Constants::USERS_DATA_TABLE, $options, $userId);
    }

    private function normalizeUserOptions($options): array {
        $baseOptions = json_decode(self::BASE_OPTIONS_USER, true);
        if (!is_array($baseOptions)) {
            $baseOptions = [];
        }

        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($options)) {
            $options = [];
        }

        unset($options['notifications']);
        return $this->mergeOptionsRecursive($baseOptions, $options);
    }

    private function mergeOptionsRecursive(array $base, array $patch): array {
        foreach ($patch as $key => $value) {
            if (is_array($value) && is_array($base[$key] ?? null)) {
                $base[$key] = $this->mergeOptionsRecursive($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
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
        $authService = new AuthService();
        $this->lastAuthResult = $authService->loginWithPassword((string) $email, (string) $psw, (bool) $force_login);
        $status = (string) ($this->lastAuthResult['status'] ?? 'unknown');
        $ip = SysClass::getClientIp();
        if (in_array($status, ['user_not_found', 'invalid_credentials'], true)) {
            $badCount = (int) \classes\system\Session::get('botguard_account_bad_active');
            $badCount++;
            if ($badCount >= 5 && $badCount <= 10) {
                \classes\system\BotGuard::addIpToBlacklist($ip, 3600, $this->lang['sys.no_such_data_was_found']);
            }
            \classes\system\Session::set('botguard_account_bad_active', $badCount);
        } elseif ($status === 'success') {
            \classes\system\Session::un_set('botguard_account_bad_active');
        }

        return $this->mapAuthStatusToMessage($status);
    }

    /**
     * Регистрирует пользователя и отправляет сообщение со ссылкой для заполнения персональных данных 
     * @email - почта
     * @password - нешифрованный пароль
     * @return boolean
     * ВАЖНО! Роль пользователя устанавливается по умолчанию в таблице БД
     */
    public function registrationUsers($email, $password) {
        $this->lastAuthResult = (new AuthService())->registerLocalUser((string) $email, (string) $password);
        $this->lastCreatedUserId = (int) ($this->lastAuthResult['user_id'] ?? 0);
        return in_array((string) ($this->lastAuthResult['status'] ?? ''), [
            'registered_pending_activation',
            'registered_activation_mail_failed',
            'registered_active',
        ], true);
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
        $requirePasswordSetup = !empty($fields['auth_require_password_setup']);
        $passwordSetupReason = trim((string) ($fields['auth_password_setup_reason'] ?? 'admin_created'));
        $ipRestricted = !empty($fields['auth_ip_restricted']);
        $skipForcedPasswordSetup = !empty($fields['skip_auth_password_setup']);

        $fields = SafeMySQL::gi()->filterArray($fields, array('name', 'email', 'phone', 'active', 'user_role', 'subscribed', 'comment', 'pwd'));
        $fields = array_map(static function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $fields);
        if (empty($fields['email']) || !SysClass::validEmail($fields['email']) || $this->getEmailExist($fields['email'])) {
            return 0;
        }
        $placeholderPassword = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);
        $payload = [
            'name' => ($fields['name'] ?? '') !== '' ? $fields['name'] : $fields['email'],
            'email' => $fields['email'],
            'phone' => ($fields['phone'] ?? '') !== '' ? $fields['phone'] : null,
            'active' => isset($fields['active']) ? (int) $fields['active'] : 2,
            'user_role' => isset($fields['user_role']) ? (int) $fields['user_role'] : Constants::USER,
            'subscribed' => isset($fields['subscribed']) ? (int) (bool) $fields['subscribed'] : 1,
            'comment' => $fields['comment'] ?? '',
            'pwd' => $placeholderPassword,
            'last_ip' => SysClass::getClientIp(),
        ];
        $sql = 'INSERT INTO ?n SET ?u';
        SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, $payload);
        $userId = SafeMySQL::gi()->insertId();
        $this->lastCreatedUserId = (int) $userId;
        $this->setUserOptions($userId);
        $this->logUserAudit('registration_new_user', 'Зарегистрирован новый пользователь', [
            'user_id' => $userId,
            'data' => $payload,
        ]);
        if (!empty($fields['pwd']) && mb_strlen((string) $fields['pwd']) >= 5) {
            $this->setUserPassword($userId, $fields['email'], (string) $fields['pwd']);
        }
        $authService = new AuthService();
        if ($ipRestricted) {
            $options = $this->getUserOptions($userId);
            $options['auth']['ip_restricted'] = 1;
            $this->setUserOptions($userId, $options);
        }
        if (!$skipForcedPasswordSetup && ($requirePasswordSetup || empty($fields['pwd']) || ($flag && in_array((int) $payload['user_role'], [Constants::ADMIN, Constants::MODERATOR], true)))) {
            $authService->markUserRequiresPasswordSetup($userId, true, $passwordSetupReason !== '' ? $passwordSetupReason : 'admin_created');
        }
        if ($system_id = $this->getUserIdByEmail('dont-answer@' . ENV_SITE_NAME)) {
            ClassMessages::set_message_user($userId, $system_id, 'Заполните свой профиль <a href="' . ENV_URL_SITE . '/admin/user_edit/id/' . $userId . '">тут</a>', 'info');
        }
        return (int) $userId;
    }

    /**
     * Проверка существования профиля админа
     * Если не существует то будет создан
     */
    public function getAdminProfile() {
        $sql = 'SELECT 1 FROM ?n WHERE user_role = 1 LIMIT 1';
        if (!SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE)) {
            $this->registrationNewUser(array('name' => 'admin', 'email' => 'test@test.com', 'active' => '2', 'user_role' => '1', 'subscribed' => '1', 'comment' => 'Смените пароль администратора', 'pwd' => 'admin', 'skip_auth_password_setup' => 1), true);
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
            if ($userId === 0) {
                $userId = $this->getUserIdByEmail($email);
                if ($userId === 0) {
                    throw new \InvalidArgumentException("Пользователь с email '$email' не найден");
                }
            }
            if (empty($password)) {
                $password = $this->generateRandomPassword(10);
            }
            (new AuthService())->setPasswordForUser($userId, $password);
            Logger::audit('users_info', 'Пароль успешно обновлён', [
                'user_id' => $userId,
                'email' => $email,
            ], [
                'initiator' => __FUNCTION__,
                'details' => 'Пароль успешно обновлён',
                'include_trace' => false,
            ]);
            return $password;
        } catch (\Exception $e) {
            Logger::error('users_error', 'Ошибка при установке пароля: ' . $e->getMessage(), [
                'user_id' => $userId,
                'email' => $email,
                'trace' => $e->getTraceAsString(),
            ], [
                'initiator' => __FUNCTION__,
                'details' => $e->getMessage(),
            ]);
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
        $sql = 'SELECT user_id FROM ?n WHERE email = ?s LIMIT 1';
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
    public function getEmailExist($email, int $excludeUserId = 0) {
        $sql = 'SELECT 1 FROM ?n WHERE email = ?s';
        if ($excludeUserId > 0) {
            $sql .= ' AND user_id != ' . (int) $excludeUserId;
        }
        return SafeMySQL::gi()->getOne($sql, Constants::USERS_TABLE, $email);
    }

    /**
     * Меняет и высылает на почту пользователя новый пароль к сайту     
     */
    public function sendRecoveryPassword($email) {
        $this->lastAuthResult = (new AuthService())->requestPasswordRecovery((string) $email);
        $status = (string) ($this->lastAuthResult['status'] ?? '');
        $isSuccess = $status !== 'recovery_mail_failed';
        $this->logUserEvent(
            $isSuccess ? Logger::LEVEL_INFO : Logger::LEVEL_ERROR,
            $isSuccess ? 'users_info' : 'users_info_errors',
            'send_recovery_password',
            $isSuccess ? 'Отправлена ссылка на восстановление пароля' : 'Отправка ссылки восстановления завершилась неудачей',
            ['email' => $email, 'status' => $status],
            !$isSuccess
        );
        return $isSuccess;
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
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        AuthService::ensureInfrastructure();
        $userId = $this->getUserIdByEmail($email);
        if ($userId === 0) {
            return false;
        }

        $challenge = AuthChallengeService::createChallenge(
            (int) $userId,
            'activation',
            ['email' => $email],
            (int) (defined('ENV_TIME_ACTIVATION') ? ENV_TIME_ACTIVATION : 86400)
        );
        $activationLink = ENV_URL_SITE . '/activation/' . $challenge['token'];
        $isSuccess = ClassMail::sendMail(
            $email,
            '',
            'activation_code',
            ['activation_link' => '<a href="' . htmlspecialchars($activationLink, ENT_QUOTES) . '">Нажми меня</a>']
        );
        $this->lastAuthResult = ['status' => $isSuccess ? 'activation_requested' : 'activation_mail_failed', 'user_id' => (int) $userId];
        return $isSuccess;
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
        if (empty($code)) {
            return false;
        }
        $this->lastAuthResult = (new AuthService())->activateByToken($code);
        return in_array((string) ($this->lastAuthResult['status'] ?? ''), ['activation_completed', 'activation_not_modified'], true);
    }

    /**
     * Создаёт необходимые таблицы в БД
     * Если нет подключения то вернёт false
     */
    public function createTables() {
        SafeMySQL::gi()->query("START TRANSACTION");
        SafeMySQL::gi()->query("SET FOREIGN_KEY_CHECKS=1"); // Включаем проверку внешних ключей
        try {
            $this->createUsersRolesTable();
            $this->insertDefaultRoles();
            $this->createUsersTable();
            $this->createUsersDataTable();
            $this->createUsersNotificationsTable();
            $this->createUsersMessageTable();
            $this->createUsersActivationTable();
            $this->createCategoriesTypesTable();
            $this->createCategoriesTable();
            $this->createPagesTable();
            $this->createPropertyTypesTable();
            $this->createPropertiesTable();
            $this->createPropertySetsTable();
            $this->createPropertyValuesTable();
            $this->createPropertyLifecycleJobsTable();
            $this->createCategoryTypeToPropertySetTable();
            $this->createPropertySetToPropertiesTable();
            $this->createFiltersTable();
            $this->createFilesTable();
            $this->createGlobalOptionsTable();
            $this->createEmailTemplatesTable();
            $this->createEmailSnippetsTable();
            AuthService::ensureInfrastructure(true);
            $this->createIpBlacklistTable();
            $this->createRequestLogsTable();
            $this->createOffensesTable();
            $this->createSearchIndexTable();
            $this->createSearchNgramsTable();
            $this->createSearchLogTable();
            $this->createImportTable();
            $this->createCronAgentsInfrastructure();
            // Вставка начальных данных            
            $this->insertDefaultEmailSnippets();
            $this->insertDefaultEmailTemplates();
            SafeMySQL::gi()->query("COMMIT");
        } catch (Exception $e) {
            SafeMySQL::gi()->query("ROLLBACK");
            SysClass::pre($e);
            return false;
        }
        $this->logSqlInfo('create_tables', 'База данных успешно развёрнута');
    }

    private function logSqlInfo(string $initiator, string $message, array $context = []): void {
        Logger::info('sql_info', $message, $context, [
            'initiator' => $initiator,
            'details' => 'OK',
            'include_trace' => false,
        ]);
    }

    private function logUserAudit(string $initiator, string $message, array $context = []): void {
        $this->logUserEvent(Logger::LEVEL_AUDIT, 'users_info', $initiator, $message, $context, false);
    }

    private function logUserEvent(string $level, string $channel, string $initiator, string $message, array $context = [], bool $includeTrace = false): void {
        Logger::log($level, $channel, $message, $context, [
            'initiator' => $initiator,
            'details' => $message,
            'include_trace' => $includeTrace,
        ]);
    }

    private function mapAuthStatusToMessage(string $status): string {
        return match ($status) {
            'success' => '',
            'pending_activation' => $this->langValue('sys.you_have_not_verified_your_email', 'Please verify your email.'),
            'blocked' => $this->langValue('sys.account_is_blocked', 'Account is blocked.'),
            'deleted' => $this->langValue('sys.account_deleted', $this->langValue('sys.no_access', 'Access denied.')),
            'password_setup_required' => $this->langValue('sys.password_setup_link_sent', $this->langValue('sys.verify_email', 'Check your email.')),
            'password_setup_mail_failed' => $this->langValue('sys.password_setup_mail_failed', $this->langValue('sys.email_sending_error', 'Email sending error.')),
            'invalid_credentials' => $this->langValue('sys.the_password_was_not_verified', 'The password was not verified.'),
            'user_not_found' => $this->langValue('sys.no_such_data_was_found', 'No such data was found.'),
            default => $this->langValue('sys.error', 'Error'),
        };
    }

    private function langValue(string $key, string $fallback = ''): string {
        if (is_array($this->lang) && array_key_exists($key, $this->lang) && is_string($this->lang[$key]) && $this->lang[$key] !== '') {
            return $this->lang[$key];
        }

        return $fallback;
    }

    /**
     * Метод для создания таблицы настроек импорта
     */
    private function createImportTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `importer_slug` VARCHAR(50) NOT NULL COMMENT 'Идентификатор импортера (e.g., \"wordpress\")',
            `settings_name` VARCHAR(255) NOT NULL COMMENT 'Название этого профиля настроек (e.g., \"Мой основной сайт\")',
            `settings_json` LONGTEXT NULL COMMENT 'Все настройки в формате JSON',
            `last_run_at` TIMESTAMP NULL COMMENT 'Время последнего запуска',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COMMENT='Профили настроек импорта';";
        SafeMySQL::gi()->query($sql, Constants::IMPORT_SETTINGS_TABLE);
    }

    /**
     * Создаёт инфраструктуру минутного планировщика cron-агентов.
     */
    private function createCronAgentsInfrastructure(): void {
        CronAgentService::ensureInfrastructure(true);
        $this->logSqlInfo('create_cron_agents_infrastructure', 'Инфраструктура cron-агентов создана');
    }

    // Метод для создания таблицы пользователей
    private function createUsersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
            user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL DEFAULT 'no name',
            email VARCHAR(255) NOT NULL,
            pwd VARCHAR(255) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT '1' COMMENT '1 - на подтверждении, 2 - активен, 3 - блокирован',
            user_role TINYINT UNSIGNED NOT NULL DEFAULT '4' COMMENT 'таблица user_roles',
            last_ip VARCHAR(45) DEFAULT NULL,
            subscribed TINYINT(1) DEFAULT '1' COMMENT 'подписка на рассылку',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'дата регистрации',
            last_activ DATETIME DEFAULT NULL COMMENT 'дата крайней активности',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'дата обновления инф.',
            phone VARCHAR(255) NULL,
            session VARCHAR(512) NULL,
            comment VARCHAR(255) NOT NULL COMMENT 'Комментарий или дивиз пользователя',
            deleted BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Флаг удаленного пользователя',
            PRIMARY KEY (user_id),
            UNIQUE KEY uq_users_email (email),
            KEY idx_users_role (user_role),
            CONSTRAINT fk_users_role FOREIGN KEY (user_role) REFERENCES ?n(role_id) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Пользователи сайта';";
        SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, Constants::USERS_ROLES_TABLE);
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
        $this->logSqlInfo('create_users_roles_table', 'Таблица ролей пользователей создана');
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
            options MEDIUMTEXT NOT NULL COMMENT 'Настройки интерфейса пользователя',
            PRIMARY KEY (data_id),
            FOREIGN KEY (user_id) REFERENCES ?n(user_id) ON DELETE CASCADE
        ) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COMMENT='Системные данные пользователей';";
        SafeMySQL::gi()->query($sql, Constants::USERS_DATA_TABLE, Constants::USERS_TABLE);
        $this->logSqlInfo('create_users_data_table', 'Таблица системных данных пользователей создана');
    }

    /**
     * Создаёт таблицу users_notifications для хранения уведомлений пользователей
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createUsersNotificationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
           notification_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
           user_id int(11) UNSIGNED NOT NULL,
           source_type varchar(32) NOT NULL DEFAULT 'system',
           source_id int(11) UNSIGNED DEFAULT NULL,
           text text NOT NULL,
           status varchar(16) NOT NULL DEFAULT 'info',
           showtime bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Unix time in ms for deferred display',
           url varchar(1024) DEFAULT NULL,
           icon varchar(255) DEFAULT NULL,
           color varchar(32) DEFAULT NULL,
           payload_json JSON DEFAULT NULL,
           created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
           updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
           PRIMARY KEY (notification_id),
           KEY idx_notifications_user (user_id),
           KEY idx_notifications_user_showtime (user_id, showtime),
           KEY idx_notifications_source_lookup (source_type, source_id),
           UNIQUE KEY uq_notifications_user_source (user_id, source_type, source_id),
           CONSTRAINT fk_users_notifications_user FOREIGN KEY (user_id) REFERENCES ?n(user_id) ON DELETE CASCADE
       ) ENGINE=innodb DEFAULT CHARSET=utf8mb4 COMMENT='Уведомления пользователей';";
        SafeMySQL::gi()->query($sql, Constants::USERS_NOTIFICATIONS_TABLE, Constants::USERS_TABLE);
        $this->logSqlInfo('create_users_notifications_table', 'Таблица уведомлений пользователей создана');
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
           KEY idx_users_message_user_read (user_id, read_at),
           KEY idx_users_message_user_created (user_id, created_at),
           FOREIGN KEY (user_id) REFERENCES ?n(user_id) ON DELETE CASCADE,
           FOREIGN KEY (author_id) REFERENCES ?n(user_id) ON DELETE CASCADE
       ) ENGINE=innodb DEFAULT CHARSET=utf8;";
        SafeMySQL::gi()->query($sql, Constants::USERS_MESSAGE_TABLE, Constants::USERS_TABLE, Constants::USERS_TABLE);
        $this->logSqlInfo('create_users_message_table', 'Таблица сообщений пользователей создана');
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
        $this->logSqlInfo('create_users_activation_table', 'Таблица кодов активации создана');
    }

    /**
     * Создаёт таблицу categories_types для хранения типов категорий
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createCategoriesTypesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
        type_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        parent_type_id INT UNSIGNED NULL,
        name VARCHAR(255) NOT NULL,
        description VARCHAR(1000),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
        UNIQUE KEY uq_category_types_name_lang (name, language_code),
        KEY idx_category_types_parent (parent_type_id),
        CONSTRAINT fk_category_types_parent FOREIGN KEY (parent_type_id) REFERENCES ?n(type_id) ON DELETE RESTRICT ON UPDATE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Таблица для хранения типов сущностей и категорий';";
        SafeMySQL::gi()->query($sql, Constants::CATEGORIES_TYPES_TABLE, Constants::CATEGORIES_TYPES_TABLE);
        $this->logSqlInfo('create_categories_types_table', 'Таблица типов категорий создана');
    }

    /**
     * Создаёт таблицу categories для хранения категорий сущностей
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createCategoriesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
        category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        type_id INT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        description MEDIUMTEXT,
        short_description VARCHAR(1000),
        parent_id INT UNSIGNED NULL,
        status ENUM('active', 'hidden', 'disabled') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
        UNIQUE KEY uq_categories_title_type_lang (title, type_id, language_code),
        KEY idx_categories_type (type_id),
        KEY idx_categories_parent (parent_id),
        KEY idx_categories_lang (language_code),
        CONSTRAINT fk_categories_type FOREIGN KEY (type_id) REFERENCES ?n(type_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
        CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES ?n(category_id) ON DELETE RESTRICT ON UPDATE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Таблица для хранения категорий сущностей';";
        SafeMySQL::gi()->query($sql, Constants::CATEGORIES_TABLE, Constants::CATEGORIES_TYPES_TABLE, Constants::CATEGORIES_TABLE);
        $this->logSqlInfo('create_categories_table', 'Таблица категорий создана');
    }

    /**
     * Создаёт таблицу pages для хранения страниц
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createPagesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
        page_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        parent_page_id INT UNSIGNED NULL,
        category_id INT UNSIGNED NOT NULL,
        status ENUM('active', 'hidden', 'disabled') NOT NULL DEFAULT 'active',
        title VARCHAR(255) NOT NULL,
        short_description VARCHAR(255),
        description LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
        KEY idx_pages_category (category_id),
        KEY idx_pages_parent (parent_page_id),
        KEY idx_pages_category_lang (category_id, language_code),
        CONSTRAINT fk_pages_category FOREIGN KEY (category_id) REFERENCES ?n(category_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
        CONSTRAINT fk_pages_parent FOREIGN KEY (parent_page_id) REFERENCES ?n(page_id) ON DELETE RESTRICT ON UPDATE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Таблица для хранения страниц';";
        SafeMySQL::gi()->query($sql, Constants::PAGES_TABLE, Constants::CATEGORIES_TABLE, Constants::PAGES_TABLE);
        $this->logSqlInfo('create_pages_table', 'Таблица страниц создана');
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
        schema_version INT UNSIGNED NOT NULL DEFAULT 1,
        description VARCHAR(1000),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
        UNIQUE KEY uq_property_types_name_lang (name, language_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Таблица для хранения типов свойств';";
        SafeMySQL::gi()->query($sql, Constants::PROPERTY_TYPES_TABLE);
        $this->logSqlInfo('create_property_types_table', 'Таблица типов свойств создана');
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
        schema_version INT UNSIGNED NOT NULL DEFAULT 1,
        is_multiple BOOLEAN NOT NULL,
        is_required BOOLEAN NOT NULL,
        description VARCHAR(1000),
        entity_type ENUM('category', 'page', 'all') NOT NULL DEFAULT 'all',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
        UNIQUE KEY uq_properties_name_type_lang (name, type_id, language_code),
        KEY idx_properties_type (type_id),
        KEY idx_properties_entity_type (entity_type),
        CONSTRAINT fk_properties_type FOREIGN KEY (type_id) REFERENCES ?n(type_id) ON DELETE RESTRICT ON UPDATE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Таблица для хранения свойств';";
        SafeMySQL::gi()->query($sql, Constants::PROPERTIES_TABLE, Constants::PROPERTY_TYPES_TABLE);
        $this->logSqlInfo('create_properties_table', 'Таблица свойств создана');
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
        language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка по ISO 3166-2',
        UNIQUE KEY uq_property_sets_name_lang (name, language_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Таблица для хранения наборов свойств';";

        SafeMySQL::gi()->query($sql, Constants::PROPERTY_SETS_TABLE);
        $this->logSqlInfo('create_property_sets_table', 'Таблица наборов свойств создана');
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
        UNIQUE KEY uq_property_values_entity_property_set_lang (entity_id, property_id, entity_type, set_id, language_code),
        KEY idx_property_values_property (property_id),
        KEY idx_property_values_set (set_id),
        KEY idx_property_values_entity_lookup (entity_type, entity_id, language_code),
        KEY idx_property_values_exact_lookup (entity_id, entity_type, property_id, set_id, language_code),
        CONSTRAINT fk_property_values_property FOREIGN KEY (property_id) REFERENCES ?n(property_id) ON DELETE CASCADE ON UPDATE RESTRICT,
        CONSTRAINT fk_property_values_set FOREIGN KEY (set_id) REFERENCES ?n(set_id) ON DELETE CASCADE ON UPDATE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Таблица для хранения значений свойств в формате JSON';";
        SafeMySQL::gi()->query($sql, Constants::PROPERTY_VALUES_TABLE, Constants::PROPERTIES_TABLE, Constants::PROPERTY_SETS_TABLE);
        $this->logSqlInfo('create_property_values_table', 'Таблица значений свойств создана');
    }

    /**
     * Создаёт таблицу фоновых lifecycle jobs для тяжёлых пересчётов свойств
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createPropertyLifecycleJobsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
        job_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        scope VARCHAR(50) NOT NULL,
        target_id INT UNSIGNED NOT NULL DEFAULT 0,
        status ENUM('queued', 'running', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'queued',
        requested_by INT UNSIGNED NULL,
        dry_run TINYINT(1) NOT NULL DEFAULT 0,
        is_async TINYINT(1) NOT NULL DEFAULT 1,
        priority TINYINT UNSIGNED NOT NULL DEFAULT 5,
        total_steps INT UNSIGNED NOT NULL DEFAULT 0,
        processed_steps INT UNSIGNED NOT NULL DEFAULT 0,
        progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        cursor_json LONGTEXT NULL,
        payload_json LONGTEXT NULL,
        result_json LONGTEXT NULL,
        error_message MEDIUMTEXT NULL,
        lock_key VARCHAR(191) NULL,
        started_at DATETIME NULL,
        finished_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status_priority (status, priority, job_id),
        INDEX idx_scope_target (scope, target_id),
        INDEX idx_requested_by (requested_by),
        CONSTRAINT fk_property_lifecycle_job_user FOREIGN KEY (requested_by) REFERENCES ?n(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Фоновые и пакетные пересчёты свойств';";
        SafeMySQL::gi()->query($sql, Constants::PROPERTY_LIFECYCLE_JOBS_TABLE, Constants::USERS_TABLE);
        $this->logSqlInfo('create_property_lifecycle_jobs_table', 'Таблица lifecycle jobs создана');
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
        $this->logSqlInfo('create_category_type_to_property_set_table', 'Таблица связи типов категорий и наборов свойств создана');
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
        $this->logSqlInfo('create_property_set_to_properties_table', 'Таблица связи наборов свойств и свойств создана');
    }

    /**
     * Создаёт таблицу ee_filters для хранения предрасчитанных фильтров
     * @throws Exception Если запрос не удалось выполнить
     */
    private function createFiltersTable() {
        $createSql = "CREATE TABLE IF NOT EXISTS ?n (
            filter_id INT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(50) NOT NULL COMMENT 'Тип сущности: category или page',
            entity_id INT NOT NULL COMMENT 'ID сущности (из ee_categories или ee_pages)',
            property_id INT NOT NULL COMMENT 'ID свойства из ee_properties',
            filter_options JSON NOT NULL COMMENT 'JSON-объект с данными и типом фильтра',
            version INT NOT NULL DEFAULT 1 COMMENT 'Версия для контроля кеша',
            language_code VARCHAR(5) NOT NULL COMMENT 'Код языка',
            recalculated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Дата последнего пересчета',
            UNIQUE KEY uq_filter (entity_type, entity_id, property_id, language_code),
            KEY idx_filters_lookup (language_code, entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Предварительно рассчитанные фильтры для сущностей';";
        SafeMySQL::gi()->query($createSql, Constants::FILTERS_TABLE);
        $this->logSqlInfo('create_filters_table', 'Таблица ee_filters создана');
    }

    /**
     * Создаёт таблицу search_index для поискового индекса сайта
     * @return void
     * @throws \Exception Если запрос не удалось выполнить
     */
    private function createSearchIndexTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
            search_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_id INT UNSIGNED NOT NULL COMMENT 'ID оригинальной сущности',
            entity_type VARCHAR(50) NOT NULL COMMENT 'Тип сущности (page, category, user...)',
            language_code CHAR(2) NOT NULL DEFAULT 'RU' COMMENT 'Код языка',
            title VARCHAR(255) NOT NULL COMMENT 'Заголовок/имя для отображения и приоритетного поиска',
            content_full MEDIUMTEXT NOT NULL COMMENT 'Полный очищенный текст для FULLTEXT',
            url VARCHAR(1024) NULL COMMENT 'URL сущности',
            static_rank TINYINT UNSIGNED NOT NULL DEFAULT 50 COMMENT 'Базовый ранг типа сущности',
            popularity_score INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Динамический ранг популярности',
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE INDEX uq_entity (entity_id, entity_type, language_code),
            FULLTEXT idx_content (title, content_full) COMMENT 'Основной индекс для MATCH AGAINST',
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_lookup (language_code),
            INDEX idx_rank (language_code, popularity_score DESC, static_rank DESC),
            INDEX idx_title_prefix (title(50))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Централизованный поисковый индекс сайта';";
        SafeMySQL::gi()->query($sql, Constants::SEARCH_INDEX_TABLE);
        $this->logSqlInfo(__FUNCTION__, 'Таблица search_index создана или уже существует');
    }

    /**
     * Создаёт таблицу search_ngrams для индекса N-грамм
     * @return void
     * @throws \Exception Если запрос не удалось выполнить
     */
    private function createSearchNgramsTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
            ngram_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ngram CHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Триграмма',
            search_id INT UNSIGNED NOT NULL COMMENT 'Ссылка на search_indexsearch_id',
            INDEX idx_ngram_search (ngram, search_id),
            INDEX idx_search_id (search_id),
            FOREIGN KEY fk_search_id(search_id) REFERENCES ?n(search_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin
        COMMENT='Индекс N-грамм (триграмм) для нечеткого поиска'";
        SafeMySQL::gi()->query($sql, Constants::SEARCH_NGRAMS_TABLE, Constants::SEARCH_INDEX_TABLE);
        $this->logSqlInfo(__FUNCTION__, 'Таблица search_ngrams создана или уже существует');
    }

    /**
     * Создаёт таблицу search_log для логирования поисковых запросов
     * @return void
     * @throws \Exception Если запрос не удалось выполнить
     */
    private function createSearchLogTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
            log_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            query_text VARCHAR(255) NOT NULL COMMENT 'Оригинальный текст запроса',
            normalized_query VARCHAR(255) NOT NULL COMMENT 'Нормализованный запрос (lowercase, trim)',
            area CHAR(1) NOT NULL,
            language_code CHAR(2) NOT NULL,
            hit_count INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Количество таких запросов',
            last_searched TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE INDEX uq_query (normalized_query, area, language_code),
            INDEX idx_hits (hit_count DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Лог поисковых запросов и их частота'";
        SafeMySQL::gi()->query($sql, Constants::SEARCH_LOG_TABLE);
        $this->logSqlInfo(__FUNCTION__, 'Таблица search_log создана или уже существует');
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
        $this->logSqlInfo('create_files_table', 'Таблица файлов создана');
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
        $this->logSqlInfo('create_global_options_table', 'Таблица глобальных опций создана');
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
        $this->logSqlInfo('create_email_templates_table', 'Таблица почтовых шаблонов создана');
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
        $this->logSqlInfo('create_email_snippets_table', 'Таблица HTML-сниппетов создана');
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
        $this->logSqlInfo('create_ip_blacklist_table', 'Таблица заблокированных IP-адресов создана');
    }

    /**
     * Создает таблицу для логов запросов (Rate Limiting)
     */
    private function createRequestLogsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
          `ip` VARCHAR(45) NOT NULL,
          `request_count` INT UNSIGNED NOT NULL DEFAULT 1,
          `first_request_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`ip`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Счетчики запросов для Rate Limit';";
        SafeMySQL::gi()->query($sql, Constants::IP_REQUEST_LOGS_TABLE);
        $this->logSqlInfo('create_request_logs_table', 'Таблица для Rate Limit создана');
    }

    /**
     * Создает таблицу для счётчика нарушений (страйков).
     */
    private function createOffensesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
          `ip` VARCHAR(45) NOT NULL,
          `strike_count` INT UNSIGNED NOT NULL DEFAULT 1,
          `last_offense_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`ip`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Счетчики нарушений IP-адресов';";

        SafeMySQL::gi()->query($sql, Constants::IP_OFFENSES_TABLE);
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
        $this->logSqlInfo('insert_default_email_snippets', 'Начальные HTML-сниппеты добавлены');
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

        $this->logSqlInfo('insert_default_email_templates', 'Начальные почтовые шаблоны добавлены');
    }

}
