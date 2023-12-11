<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;

/**
 * Модель карты пользователя
 */
class ModelUserEdit {

    /**
     * Возвращает все свободные роли пользователей
     * кроме переданной и роли система
     */
    public function get_free_roles($id = 1) {
        $sql = 'SELECT `id`, `name` FROM ?n WHERE `id` NOT IN (?i, 8)';
        return SafeMySQL::gi()->getAll($sql, Constants::USERS_ROLES_TABLE, $id);
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
    public function get_users_roles_data($order = 'id ASC', $where = NULL, $start = 0, $limit = 100) {
        $orderString = $order ? $order : 'id ASC';
        $whereString = $where ? $where : '';
        $start = $start ? $start : 0;
        if ($orderString) {
            $sql_roles = "SELECT `id` FROM ?n $whereString ORDER BY $orderString LIMIT ?i, ?i";
        } else {
            $sql_roles = "SELECT `id` FROM ?n $whereString LIMIT ?i, ?i";
        }
        $res_array = SafeMySQL::gi()->getAll($sql_roles, Constants::USERS_ROLES_TABLE, $start, $limit);
        $res = [];
        foreach ($res_array as $role) {
            $res[] = $this->get_users_role_data($role['id']);
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
        $sql_role = "SELECT r.* FROM ?n AS r WHERE r.id = ?i";
        $role_data = SafeMySQL::gi()->getRow($sql_role, Constants::USERS_ROLES_TABLE, $role_id);
        if (!$role_data) {
            return null;
        }
        return $role_data;
    }

    public function users_role_dell($role_id) {
        $sql_role = 'DELETE FROM ?n WHERE id = ?i';
        SafeMySQL::gi()->query($sql_role, Constants::USERS_ROLES_TABLE, $role_id);
    }
 
    /**
     * 	Удаление пользователя, присвоит флаг удалённый 
     * 	@param id - id пользователя
     */
    public function delete_user($id) {
        $sql = "UPDATE ?n SET `deleted` = 1 WHERE `id` = ?i";
        if (!SafeMySQL::gi()->query($sql, Constants::USERS_TABLE, $id)) {
            if (ENV_LOG) {
                SysClass::SetLog('Ошибка удаления пользователя id=' . $id);
            }
            return false;
        }
        if (ENV_LOG) {
            SysClass::SetLog('Удалены данные пользователя id=' . $id, 'info', $this->data['id']);
        }
        return true;
    }    
    
}
