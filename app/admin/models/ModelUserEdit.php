<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\SysClass;

/**
 * Модель карты пользователя
 */
class ModelUserEdit {

    /**
     * Возвращает все свободные роли пользователей
     * кроме переданной и роли система
     */
    public function get_free_roles($role_id = 1) {
        $sql = 'SELECT role_id, name FROM ?n WHERE role_id NOT IN (?i, 8)';
        return SafeMySQL::gi()->getAll($sql, Constants::USERS_ROLES_TABLE, $role_id);
    }

    /**
     * Получает данные ролей пользователей из таблицы ролей
     * @param string $order Строка для указания порядка сортировки (например, 'role_id ASC'). По умолчанию 'role_id ASC'
     * @param string|null $where Опциональная строка для условия WHERE в SQL запросе
     * @param int $start Начальный индекс для LIMIT в SQL запросе. По умолчанию 0
     * @param int $limit Количество записей для получения. По умолчанию 100
     * @return array Возвращает массив с двумя ключами:
     *               - 'data': массив объектов данных ролей,
     *               - 'total_count': общее количество ролей в таблице
     */
    public function get_users_roles_data($order = 'role_id ASC', $where = null, $start = 0, $limit = 100) {
        $orderString = $order ? $order : 'role_id ASC';
        $whereString = $where ? $where : ''; // Убрано условие с language_code
        $start = $start ? $start : 0;

        // Формируем SQL-запрос для получения role_id
        if ($orderString) {
            $sql_roles = "SELECT role_id FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_roles = "SELECT role_id FROM ?n $whereString LIMIT ?i, ?i";
        }

        // Выполняем запрос и получаем данные
        $res_array = SafeMySQL::gi()->getAll($sql_roles, Constants::USERS_ROLES_TABLE, $start, $limit);
        $res = [];
        foreach ($res_array as $role) {
            $res[] = $this->get_users_role_data($role['role_id']);
        }

        // Получаем общее количество записей
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::USERS_ROLES_TABLE);

        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * Получает данные роли по её идентификатору из таблицы ролей пользователей.
     * @param int $role_id Идентификатор роли, данные которой необходимо получить.
     * @return array|null Возвращает ассоциативный массив с данными роли, если роль найдена.
     *                    Возвращает null, если роль не найдена.
     */
    public function get_users_role_data($role_id) {
        // SQL запрос для получения данных роли по идентификатору
        $sql_role = "SELECT r.* FROM ?n AS r WHERE r.role_id = ?i";
        $role_data = SafeMySQL::gi()->getRow($sql_role, Constants::USERS_ROLES_TABLE, $role_id);
        if (!$role_data) {
            return null;
        }
        return $role_data;
    }

    /**
     * Обновляет или создает запись о роли пользователя в базе данных
     * @param array $usersRoleData Ассоциативный массив данных роли пользователя
     *     - role_id (int, необязательно): Идентификатор роли, если требуется обновление
     *     - name (string): Название роли пользователя (обязательно для создания или обновления)
     *     - другие поля соответствуют структуре таблицы Constants::USERS_ROLES_TABLE
     * @param string $language_code Код языка, используемый при сохранении роли (по умолчанию — ENV_DEF_LANG)
     * @return int|false Идентификатор роли при успешном создании или обновлении, либо false при ошибке
     * Процесс работы:
     * - Фильтрует входные данные через SafeMySQL::filterArray().
     * - Обрезает лишние пробелы в строковых значениях.
     * - Конвертирует числовые значения в нужные типы.
     * - Если передан 'role_id', пытается обновить существующую запись.
     * - Если 'role_id' не передан или равен 0, создает новую запись.
     * - При ошибке SQL логирует её через ErrorLogger.
     */
    public function update_users_role_data(array $usersRoleData = [], $language_code = ENV_DEF_LANG) {
        $usersRoleData = SafeMySQL::gi()->filterArray($usersRoleData, SysClass::ee_getFieldsTable(Constants::USERS_ROLES_TABLE));
        $usersRoleData = array_map('trim', $usersRoleData);
        $usersRoleData = SysClass::ee_convertArrayValuesToNumbers($usersRoleData);
        $usersRoleData['language_code'] = $language_code;
        if (!isset($usersRoleData['name'])) {
            return false;
        }
        if (!empty($usersRoleData['role_id']) && $usersRoleData['role_id'] != 0) {
            $role_id = $usersRoleData['role_id'];
            unset($usersRoleData['role_id']);
            $sql = "UPDATE ?n SET ?u WHERE role_id = ?i AND language_code = ?s";
            $result = SafeMySQL::gi()->query($sql, Constants::USERS_ROLES_TABLE, $usersRoleData, $role_id, $language_code);
            if (!$result) {
                $message = 'error SQL';
                new \classes\system\ErrorLogger($message, __FUNCTION__, 'user_role_edit', SafeMySQL::gi()->parse($sql, Constants::USERS_ROLES_TABLE, $usersRoleData, $role_id, $language_code));
            }
            return $result ? $role_id : false;
        } else {
            unset($usersRoleData['role_id']);
        }
        $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::USERS_ROLES_TABLE, $usersRoleData);
        if (!$result) {
            $message = 'error SQL';
            new \classes\system\ErrorLogger($message, __FUNCTION__, 'user_role_edit', SafeMySQL::gi()->parse($sql, Constants::USERS_ROLES_TABLE, $usersRoleData));
        }
        return $result ? SafeMySQL::gi()->insertId() : false;
    }

    /**
     * Удаляет роль пользователя из базы данных
     * @param int $role_id Идентификатор роли пользователя, которую необходимо удалить
     * @return void
     */
    public function users_role_dell(int $role_id): void {
        $sql_role = 'DELETE FROM ?n WHERE role_id = ?i';
        SafeMySQL::gi()->query($sql_role, Constants::USERS_ROLES_TABLE, $role_id);
    }

    /**
     * 	Удаление пользователя, присвоит флаг удалённый 
     * 	@param user_id - user_id пользователя
     */
    public function delete_user(int $user_id) {
        $sql = "UPDATE ?n SET deleted = 1 WHERE user_id = ?i";
        return SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, $user_id);
    }
}
