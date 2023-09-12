<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Модель карты пользователя
 */
 
use Constants;
 
Class Model_user_edit {

    /**
     * Возвращает все свободные роли пользователей
     * кроме переданной и роли система
     */
    function get_free_roles($id = 1) {
        $sql = 'SELECT `id`, `name` FROM ?n WHERE `id` NOT IN (?i, 8)';
        return SafeMySQL::gi()->getAll($sql, Constants::USERS_ROLES_TABLE, $id);
    }

}
