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
        if (!isset($postData['showtime'], $postData['id'])) {
            echo json_encode(['error' => 'invalid payload'], JSON_UNESCAPED_UNICODE);
            exit();
        }
        $updated = ClassNotifications::set_reading_time($this->logged_in, $postData['showtime'], $postData['id']);
        echo json_encode(['error' => $updated ? 'no' : 'update failed'], JSON_UNESCAPED_UNICODE);
        exit();
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
            echo json_encode(['error' => 'invalid payload'], JSON_UNESCAPED_UNICODE);
            exit();
        }
        $deleted = ClassNotifications::killNotificationById($this->logged_in, $postData['id']);
        echo json_encode(['error' => $deleted ? 'no' : 'delete failed'], JSON_UNESCAPED_UNICODE);
        exit();
    }
}
