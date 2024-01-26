<?php

namespace app\admin;

use classes\system\SysClass;
use classes\helpers\ClassNotifications;

/**
 * Функции работы с оповещениями
 */
trait NotificationsTrait {

    /**
     * Сохраняет время показа уведомления пользователю
     * Функция предназначена для AJAX-запросов
     * Ограничивает доступ к методу пользователям с определенными правами
     * В случае отсутствия доступа или невалидных параметров выполняет перенаправление
     * @param array $params Параметры запроса (не используются в данной функции)
     */
    public function set_notification_time(array $params = []) {
        $this->access = [100];
        if (!SysClass::get_access_user($this->logged_in, $this->access) || array_filter($params)) {
            SysClass::return_to_main(401);
            exit();
        }
        $post_data = SysClass::ee_cleanArray($_POST);
        ClassNotifications::set_reading_time($this->logged_in, $post_data['showtime'], $post_data['id']);
    }

    /**
     * Удаляет уведомление по его ID
     * Функция предназначена для AJAX-запросов
     * Ограничивает доступ к методу пользователям с определенными правами
     * В случае отсутствия доступа или невалидных параметров ничего не делает
     * @param array $params Параметры запроса (не используются в данной функции)
     */
    public function kill_notification_by_id(array $params = []) {
        $this->access = [100];
        if (!SysClass::get_access_user($this->logged_in, $this->access) || array_filter($params)) {
            SysClass::return_to_main(401);
            exit();
        }        
        $post_data = SysClass::ee_cleanArray($_POST);
        if (!is_array($post_data) || !isset($post_data['id']) || !is_numeric($post_data['id']) || $post_data['id'] < 0) {
            return;
        }
        ClassNotifications::kill_notification_by_id($this->logged_in, $post_data['id']);
    }

}
