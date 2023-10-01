<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Модель сообщений пользователя
 */

Class Model_messages {
    
    /**
     * Помечает все сообщения пользователю как прочитанные
     * @param int $user_id - ID пользователя
     */
    public function read_all($user_id) {
        $sql = 'UPDATE ?n SET `date_read` = NOW() WHERE `user_id` = ?i';
        SafeMySQL::gI()->query($sql, Constants::USERS_MESSAGE_TABLE, $user_id);
    }
    
    /**
     * Удалить все сообщения переданного пользователя
     * @param int $user_id - ID пользователя
     */
    public function kill_all_message($user_id) {
        $sql = 'DELETE FROM ?n WHERE `user_id` = ?i';
        SafeMySQL::gI()->query($sql, Constants::USERS_MESSAGE_TABLE, $user_id);
    }
    
    /**
     * Пометить сообщение прочитанным     
     * @param int $message_id - ID сообщения
     * @param int $user_id - ID пользователя нужен при пометке всех сообщений
     */
    public function set_message_as_readed($message_id = 0, $user_id = 0) {
        if ($message_id) {
            $sql = 'UPDATE ?n SET `date_read` = NOW() WHERE `id` = ?i';
            SafeMySQL::gI()->query($sql, Constants::USERS_MESSAGE_TABLE, $message_id);
        } elseif($user_id) {
            $sql = 'UPDATE ?n SET `date_read` = NOW() WHERE `user_id` = ?i AND ISNULL(`date_read`)';
            SafeMySQL::gI()->query($sql, Constants::USERS_MESSAGE_TABLE, $user_id);
        }
    }

    /**
     * Удалить сообщение     
     * @param int $message_id - ID сообщения
     * @param int $user_id - ID пользователя нужен при удалении всех сообщений
     */
    public function kill_message($message_id) {
        if ($message_id) {
            $sql = 'DELETE FROM ?n WHERE `id` = ?i';
            SafeMySQL::gI()->query($sql, Constants::USERS_MESSAGE_TABLE, $message_id);
         }
    }

}
