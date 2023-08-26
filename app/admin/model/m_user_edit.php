<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Модель карты пользователя
 */
Class Model_user_edit Extends Users {

    /**
     * Возвращает все свободные роли пользователей
     * кроме переданной и роли система
     */
    function get_free_roles($id = 1) {
        $sql = 'SELECT `id`, `name` FROM ?n WHERE `id` NOT IN (?i, 8)';
        return SafeMySQL::gi()->getAll($sql, USERS::USERS_ROLES, $id);
    }

}
