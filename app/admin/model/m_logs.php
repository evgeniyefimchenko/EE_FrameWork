<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} 

/**
 * Модель логов
 */
Class Model_logs Extends Users {
	
	const LOGS = ENV_DB_PREF . 'logs';
    /**
     * Возвращает все логи
     */
    function get_logs() {
        $sql = 'SELECT * FROM ?n ORDER BY `id` DESC';
        return SafeMySQL::gi()->getAll($sql, self::LOGS);
    }
}
