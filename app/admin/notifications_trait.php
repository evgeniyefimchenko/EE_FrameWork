<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Функции работы с оповещениями
 */
trait notifications_trait {

    /**
     * AJAX Функция сохранения время показа уведомления пользователю
     */
    public function set_notification_time($param = array()) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access) || array_filter($param)) {
            SysClass::return_to_main(401);
            exit();
        }
        $class_notifications = new Class_notifications();
        $post_data = $_POST;
        $class_notifications->set_reading_time($this->logged_in, $post_data['showtime'], $post_data['id']);
    }

    /**
     * AJAX прибиваем сообщение по ID
     */
    public function kill_notification_by_id($param = array()) {
        $this->access = array(100);
        if (!SysClass::get_access_user($this->logged_in, $this->access) || array_filter($param)) {
            SysClass::return_to_main(401);
            exit();
        }        
        $post_data = $_POST;
        $class_notifications = new Class_notifications();
        $class_notifications->kill_notification_by_id($this->logged_in, $post_data['id']);
    }

}
