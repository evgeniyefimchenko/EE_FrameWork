<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/*
 * Класс для работы с уведомлениями
 * подключается в необходимых моделях
 */

class Class_notifications {

    /**
    * Удалит все найденные уведомления по переданному тексту
    * @param type $user_id
    * @param type $text_notification
    */
    public static function kill_notification_by_text($user_id, $text_notification) {
        $notifications = self::get_notifications_user($user_id);
        foreach ($notifications as $key => $notification) {
            if (mb_strpos($notification['text'], $text_notification) !== FALSE) {
                unset($notifications[$key]);
            }
        }
        self::set_notifications_user($user_id, $notifications);
    }
    
    /**
     * Удалит оповещение по его id
     * @param int $user_id - id пользователя
     * @param int $id - id оповещения
     */
    public static function kill_notification_by_id($user_id, $id) {
        $notifications = self::get_notifications_user($user_id);
        foreach ($notifications as $key => $notification) {
            if ($notification['id'] == $id) {
                unset($notifications[$key]);
            }
        }
        self::set_notifications_user($user_id, $notifications);        
    }
    
    /**
     * Удалит оповещения по статусу
     * @param int $user_id - id пользователя
     * @param str $status
     */
    public static function kill_notification_by_status($user_id, $status) {
        $notifications = self::get_notifications_user($user_id);
        foreach ($notifications as $key => $notification) {
            if ($notification['status'] == $status) {
                unset($notifications[$key]);
            }
        }
        self::set_notifications_user($user_id, $notifications);        
    }

    /**
    * Вернёт все уведомления пользователя
    * @param int $user_id - ID пользователя
    * @return array
    */
    public static function get_notifications_user($user_id) {
        $class_users = new Users(array());
        $user_options = $class_users->get_user_options($user_id);
        return $user_options['notifications'];
    }

    /**
    * Добавит напоминание пользователю
    * @param int $user_id ID - пользователя
    * @param array $notification - Массив с текстом и классом уведомления 'primary', 'info', 'success', 'warning', 'danger'
    */
    public static function add_notification_user($user_id, $notification = []) {
        $notification['showtime'] = '0';
        self::set_notifications_user($user_id, $notification, true);
    }

    /**
    * Установит или обновит все уведомления пользователю, так же
    * произведёт пересчёт и замену ID уведомлений
    * @param int $user_id
    * @param array $new_notifications новые оповещения
    */
    private function set_notifications_user($user_id, $new_notifications = [], $add = false) {
        $class_users = new Users([]);
        $user_options = $class_users->get_user_options($user_id);
        if ($add) {                    
            $user_options['notifications'][] = $new_notifications;
        } else {
            $user_options['notifications'] = $new_notifications;
        }
        $notifi_id = 0;
        foreach ($user_options['notifications'] as $key => $notification) {
            if ($notification['text'] && mb_strlen($notification['text']) >= 3) {
                $notification['id'] = $notifi_id;
                $filtered_notifications[] = $notification;
                $notifi_id++;
            }
        }
        // Обновление notifications с отфильтрованными и переиндексированными данными
        $user_options['notifications'] = $filtered_notifications;
        $class_users->set_user_options($user_id, $user_options);
    }

    /**
    * Установит время следующего показа уведомления
    * @param int $user_id - ID пользователя 
    * @param int $reading_time - время в формате UNIXTIME
    * @param int $id - ID уведомления
    */
    public static function set_reading_time($user_id, $reading_time, $id) {
        $notifications = self::get_notifications_user($user_id);
        foreach ($notifications as $key => $notification) {
            if ($notification['id'] == $id) {
                $notifications[$key]['showtime'] = $reading_time;
            }
        }
        self::set_notifications_user($user_id, $notifications);
    }

    /**
    * Удалит все уведомления пользователя
    * @param type $user_id
    */
    public static function kill_em_all($user_id) {
        self::set_notifications_user($user_id, []);
    }
}
