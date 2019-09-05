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
    public function kill_notification_by_text($user_id, $text_notification) {
        $notifications = $this->get_notifications_user($user_id);
        foreach ($notifications as $key => $notification) {
            if (mb_strpos($notification['text'], $text_notification)) {
                unset($notifications[$key]);
            }
        }
        $this->set_notifications_user($user_id, $notifications);
    }

    /**
    * Вернёт все уведомления пользователя
    * @param int $user_id - ID пользователя
    * @return array
    */
    private function get_notifications_user($user_id) {
        $class_users = new Users(array());
        $user_options = $class_users->get_user_options($user_id);
        return $user_options['notifications'];
    }

    /**
    * Добавит напоминание пользователю
    * @param int $user_id ID - пользователя
    * @param array $notification - Массив с текстом и классом уведомления
    */
    public function add_notification_user($user_id, $notification) {
        $notifications = $this->get_notifications_user($user_id);
        $notification['showtime'] = '0';
        $notifications[] = $notification;
        $this->set_notifications_user($user_id, $notifications);
    }

    /**
    * Установит все уведомления пользователю, так же
    * произведёт пересчёт и замену ID уведомлений
    * @param int $user_id
    * @param array $notifications
    */
    private function set_notifications_user($user_id, $notifications) {
        $class_users = new Users(array());
        $user_options = $class_users->get_user_options($user_id);
        $new_id = 0;
        if (is_array($notifications[0])) {
            foreach ($user_options['notifications'] as $key => $notification) {
                $new_id++;
                $notifications[$key]['id'] = $new_id;
            }
        }
        $user_options['notifications'] = $notifications;
        $class_users->set_user_options($user_id, $user_options);
    }

    /**
    * Установит время следующего показа уведомления
    * @param int $user_id - ID пользователя 
    * @param int $reading_time - время в формате UNIXTIME
    * @param int $id - ID уведомления
    */
    public function set_reading_time($user_id, $reading_time, $id) {
        $notifications = $this->get_notifications_user($user_id);
        foreach ($notifications as $key => $notification) {
            if ($notification['id'] == $id) {
                $notifications[$key]['showtime'] = $reading_time;
            }
        }
        $this->set_notifications_user($user_id, $notifications);
    }

    /**
    * Удалит все уведомления пользователя
    * @param type $user_id
    */
    public function kill_em_all($user_id) {
        $this->set_notifications_user($user_id, array());
    }
}
