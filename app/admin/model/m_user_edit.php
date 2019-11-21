<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} ?>
<?php

/**
 * Модель карты пользователя
 */
Class Model_user_edit {

    /**
     * Возвращает все свободные роли пользователей
     * кроме текущей и роли система
     */
    function get_free_roles($id) {
        $sql = 'SELECT `id`, `name` FROM ?n WHERE `id` NOT IN (?i, 8)';
        return SafeMySQL::gi()->getAll($sql, USERS::USERS_ROLES, $id);
    }

}
?>