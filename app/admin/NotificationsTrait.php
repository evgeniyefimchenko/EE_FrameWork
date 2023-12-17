<?php

namespace app\admin;

/**
 * Функции работы с оповещениями
 */
trait NotificationsTrait {

    /**
     * AJAX Функция сохранения время показа уведомления пользователю
     */
    public function set_notification_time(array $params = []) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access) || array_filter($params)) {
            SysClass::return_to_main(401);
            exit();
        }
        $post_data = SysClass::ee_cleanArray($_POST);
        ClassNotifications::set_reading_time($this->logged_in, $post_data['showtime'], $post_data['id']);
    }

    /**
     * AJAX прибиваем сообщение по ID
     */
    public function kill_notification_by_id($params = array()) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access) || array_filter($params)) {
            SysClass::return_to_main(401);
            exit();
        }        
        $post_data = SysClass::ee_cleanArray($_POST);
        ClassNotifications::kill_notification_by_id($this->logged_in, $post_data['id']);
    }

}
