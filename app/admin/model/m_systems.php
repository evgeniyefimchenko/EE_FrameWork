<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Модель системных действий
 */

Class Model_systems extends Users {

    /**
     * Очищает все таблицы в базе данных.
     * Этот метод получает список всех таблиц в текущей базе данных,
     * и выполняет операцию DROP на каждой таблице для её очистки.
     * Операции выполняются в рамках одной транзакции, чтобы гарантировать,
     * что все таблицы будут успешно очищены, или ни одна из таблиц не будет удалена в случае ошибки.
     * @param int $user_id Кто вызвал
     * @throws Exception Если произошла ошибка во время очистки таблиц.
     */
    public function kill_db($user_id) {
        $notifications = new Class_notifications();
        $tables = SafeMySQL::gi()->getCol("SHOW TABLES");
        if ($tables) {
            SafeMySQL::gi()->query("START TRANSACTION");
            try {
                SafeMySQL::gi()->query("SET FOREIGN_KEY_CHECKS=0");  // отключаем проверку внешних ключей
                foreach ($tables as $table) {
                    SafeMySQL::gi()->query("DROP TABLE ?n", $table);
                }
                SafeMySQL::gi()->query("SET FOREIGN_KEY_CHECKS=1");  // включаем проверку внешних ключей обратно
                SafeMySQL::gi()->query("COMMIT");
            } catch (Exception $e) {
                SafeMySQL::gi()->query("ROLLBACK");
                $notifications->add_notification_user($user_id, ['text' => $e, 'status' => 'danger']);
                return false;
            }
        } else {
            $notifications->add_notification_user($user_id, ['text' => 'No tables found in the database.', 'status' => 'info']);
            return false;
        }
        // Пересоздание БД и регистрация первичных пользователей
        $this->get_user_data();
        $flagFilePath = ENV_LOGS_PATH . 'test_data_created.txt';
        if (file_exists($flagFilePath)) unlink($flagFilePath);
        return true;
    }

}