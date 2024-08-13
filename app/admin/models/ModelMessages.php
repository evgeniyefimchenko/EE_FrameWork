<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\SysClass;

/**
 * Модель сообщений пользователя
 */
class ModelMessages {
    
    public function get_user_messages($user_id, $order = 'created_at DESC', $where = NULL, $start = 0, $limit = 100) {
        $orderString = $order ?: 'created_at DESC';
        $start = $start ?: 0;
        $needsJoin = strpos($where, 'author_id') !== false || strpos($order, 'author_id') !== false;
        $order = SysClass::ee_addPrefixToFields($order, SysClass::ee_getFieldsTable(Constants::USERS_MESSAGE_TABLE), 'm.');
        $where = SysClass::ee_addPrefixToFields($where, SysClass::ee_getFieldsTable(Constants::USERS_MESSAGE_TABLE), 'm.');
        $whereString = $where ? "$where AND " : "";
        if (is_array($user_id)) {
           $whereString .= "m.user_id IN ?a"; 
        } else {        
            $whereString .= "m.user_id = ?i";
        }
        $params = [Constants::USERS_MESSAGE_TABLE, Constants::USERS_TABLE, $user_id, $start, $limit];
        // Обновленный запрос с JOIN для получения имени автора
        $sql_messages = "SELECT m.*, u.name as author_name FROM ?n m 
                         LEFT JOIN ?n u ON m.author_id = u.user_id
                         WHERE $whereString 
                         ORDER BY $orderString LIMIT ?i, ?i";
        $res_array = SafeMySQL::gi()->getAll($sql_messages, ...$params);
        // Для подсчета общего количества сообщений, убираем LIMIT
        $sql_count = "SELECT COUNT(*) as total_count FROM ?n m 
                      LEFT JOIN ?n u ON m.author_id = u.user_id
                      WHERE $whereString";
        $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::USERS_MESSAGE_TABLE, Constants::USERS_TABLE, $user_id);
        return [
            'data' => $res_array,
            'total_count' => $total_count
        ];
    }
    
    /**
     * Помечает все сообщения пользователю как прочитанные
     * @param int $user_id - ID пользователя
     */
    public function read_all($user_id) {
        $sql = 'UPDATE ?n SET `read_at` = NOW() WHERE `user_id` = ?i';
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
            $sql = 'UPDATE ?n SET `read_at` = NOW() WHERE `id` = ?i';
            SafeMySQL::gI()->query($sql, Constants::USERS_MESSAGE_TABLE, $message_id);
        } elseif($user_id) {
            $sql = 'UPDATE ?n SET `read_at` = NOW() WHERE `user_id` = ?i AND ISNULL(`read_at`)';
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
