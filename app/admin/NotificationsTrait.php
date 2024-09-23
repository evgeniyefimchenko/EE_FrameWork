<?php

namespace app\admin;

use classes\system\SysClass;
use classes\helpers\ClassNotifications;
use classes\system\Constants;

/**
 * Функции работы с оповещениями
 */
trait NotificationsTrait {

    /**
     * Сохраняет время показа уведомления пользователю
     * Функция предназначена только для AJAX-запросов
     * Ограничивает доступ к методу пользователям с определенными правами
     * В случае отсутствия доступа или невалидных параметров выполняет перенаправление
     * @param array $params Параметры запроса (не используются в данной функции)
     */
    public function setNotificationTime(array $params = []) {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access) || array_filter($params) || !SysClass::isAjaxRequestFromSameSite()) {
            SysClass::handleRedirect(401);
            exit();
        }
        $postData = SysClass::ee_cleanArray($_POST);
        ClassNotifications::set_reading_time($this->logged_in, $postData['showtime'], $postData['id']);
    }

    /**
     * Удаляет уведомление по его ID
     * Функция предназначена только для AJAX-запросов
     * Ограничивает доступ к методу пользователям с определенными правами
     * В случае отсутствия доступа или невалидных параметров ничего не делает
     * @param array $params Параметры запроса (не используются в данной функции)
     */
    public function killNotificationById(array $params = []) {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access) || array_filter($params) || !SysClass::isAjaxRequestFromSameSite()) {
            SysClass::handleRedirect(401);
            exit();
        }        
        $postData = SysClass::ee_cleanArray($_POST);
        if (!is_array($postData) || !isset($postData['id']) || !is_numeric($postData['id']) || $postData['id'] < 0) {
            return;
        }
        ClassNotifications::killNotificationById($this->logged_in, $postData['id']);
    }
}
