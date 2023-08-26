<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/*
* Класс для обработки сообщений пользователю
*/

Class Class_messages {

    const USERS_MESSAGE_TABLE = ENV_DB_PREF . 'users_message';

    /**
    * Записать сообщение пользователю
    * @param int $user_id - ID пользователя
    * @param int $author_id - ID автора
    * @param str $message - текст сообщения
    * @param str $status Статус сообщения 'primary', 'info', 'success', 'warning', 'danger'
    */
    public function set_message_user($user_id, $author_id, $message, $status = 'info') {
        // notify
        $note = new Class_notifications();
        $note->add_notification_user($user_id, array('text' => $message, 'status' => $status));        
        $sql = 'INSERT INTO ?n SET `user_id` = ?i, `author_id` = ?i, `message_text` = ?s, `status` = ?s';
        SafeMySQL::gi()->query($sql, self::USERS_MESSAGE_TABLE, $user_id, $author_id, $message, $status);
    }

    /**
    * Вернёт все сообщения пользователю
    * @param int $user_id - ID пользователя
    * @return array - многомерный массив сообщений
    */
    public function get_messages_user($user_id) {
        $sql = 'SELECT `id`, `author_id`, `message_text`, `date_create`, `date_read`, `status` FROM ?n WHERE `user_id` = ?i';
        return SafeMySQL::gi()->getAll($sql, self::USERS_MESSAGE_TABLE, $user_id);
    }
	
    /**
    * Вернёт все непрочитанные сообщения пользователю
    * @param int $user_id - ID пользователя
    * @return array - многомерный массив сообщений
    */
    public function get_unread_messages_user($user_id) {
        $sql = 'SELECT `id`, `author_id`, `message_text`, `date_create`, `date_read`, `status` FROM ?n WHERE `user_id` = ?i AND date_read is NULL';
        return SafeMySQL::gi()->getAll($sql, self::USERS_MESSAGE_TABLE, $user_id);
    }

    /**
    * Количество непрочитанных сообщений
    * @param int $user_id - ID пользователя
    * @return int
    */
    public function get_count_unread_messages($user_id) {
        $sql = 'SELECT COUNT(`id`) FROM ?n WHERE `user_id` = ?i AND date_read is NULL';
        return SafeMySQL::gi()->getOne($sql, self::USERS_MESSAGE_TABLE, $user_id);
    }
	
    /**
    * Количество сообщений
    * @param int $user_id - ID пользователя
    * @return int
    */
    public function get_count_messages($user_id) {
        $sql = 'SELECT COUNT(`id`) FROM ?n WHERE `user_id` = ?i';
        return SafeMySQL::gi()->getOne($sql, self::USERS_MESSAGE_TABLE, $user_id);
    }

}
