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
     * Получает данные ролей пользователей из таблицы ролей.
     * @param string $order Строка для указания порядка сортировки (например, 'id ASC'). По умолчанию 'id ASC'.
     * @param string|null $where Опциональная строка для условия WHERE в SQL запросе.
     * @param int $start Начальный индекс для LIMIT в SQL запросе. По умолчанию 0.
     * @param int $limit Количество записей для получения. По умолчанию 100.
     * @return array Возвращает массив с двумя ключами:
     *               - 'data': массив объектов данных ролей,
     *               - 'total_count': общее количество ролей в таблице.
     */
    public function get_users_roles_data($order = 'role_id ASC', $where = NULL, $start = 0, $limit = 100, $language_code = ENV_DEF_LANG) {
        $orderString = $order ? $order : 'role_id ASC';
        $whereString = $where ? $where . 'AND language_code = ?s' : 'WHERE language_code = ?s';
        $start = $start ? $start : 0;
        if ($orderString) {
            $sql_roles = "SELECT role_id FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_roles = "SELECT role_id FROM ?n $whereString LIMIT ?i, ?i";
        }
        $res_array = SafeMySQL::gi()->getAll($sql_roles, Constants::USERS_ROLES_TABLE, $language_code, $start, $limit);
        $res = [];
        foreach ($res_array as $role) {
            $res[] = $this->get_users_role_data($role['role_id']);
        }
        // Получаем общее количество записей
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::USERS_ROLES_TABLE, $language_code);
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

    public function update_users_role_data(array $users_role_data = [], $language_code = ENV_DEF_LANG) {
        $users_role_data = SafeMySQL::gi()->filterArray($users_role_data, SysClass::ee_getFieldsTable(Constants::USERS_ROLES_TABLE));
        $users_role_data = array_map('trim', $users_role_data);
        $users_role_data = SysClass::ee_convertArrayValuesToNumbers($users_role_data);
        $users_role_data['language_code'] = $language_code;
        if (!isset($users_role_data['name'])) {
            return false;
        }
        if (!empty($users_role_data['role_id']) && $users_role_data['role_id'] != 0) {
            $role_id = $users_role_data['role_id'];
            unset($users_role_data['role_id']);
            $sql = "UPDATE ?n SET ?u WHERE role_id = ?i AND language_code = ?s";
            $result = SafeMySQL::gi()->query($sql, Constants::USERS_ROLES_TABLE, $users_role_data, $role_id, $language_code);
            if (!$result) {
                SysClass::preFile('error', 'update_users_role_data', 'error SQL ' . SafeMySQL::gi()->parse($sql, Constants::USERS_ROLES_TABLE, $users_role_data, $role_id, $language_code));
            }
            return $result ? $role_id : false;
        } else {
            unset($users_role_data['role_id']);
        }
                $sql = "INSERT INTO ?n SET ?u";
        $result = SafeMySQL::gi()->query($sql, Constants::USERS_ROLES_TABLE, $category_data);
        if (!$result) {
            SysClass::preFile('error', 'update_users_role_data', 'error SQL ' . SafeMySQL::gi()->parse($sql, Constants::USERS_ROLES_TABLE, $category_data));
        }
        return $result ? SafeMySQL::gi()->insertId() : false;
    }
    
    public function users_role_dell($role_id) {
        $sql_role = 'DELETE FROM ?n WHERE role_id = ?i';
        SafeMySQL::gi()->query($sql_role, Constants::USERS_ROLES_TABLE, $role_id);
    }
 
    /**
     * 	Удаление пользователя, присвоит флаг удалённый 
     * 	@param user_id - user_id пользователя
     */
    public function delete_user($user_id) {
        $sql = "UPDATE ?n SET deleted = 1 WHERE user_id = ?i";
        return SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, $user_id);
    }    
    
}
