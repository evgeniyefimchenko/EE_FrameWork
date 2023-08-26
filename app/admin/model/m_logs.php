<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} 

/**
 * Модель логов
 */
Class Model_logs Extends Users {
	
	const LOGS = ENV_DB_PREF . 'logs',
			LOGS_SITE_API = ENV_DB_PREF . 'logs_site_api',
			USERS_TABLE = ENV_DB_PREF . 'users',
            DELL_USERS_TABLE = ENV_DB_PREF . 'users_dell',
            USERS_ROLES_TABLE = ENV_DB_PREF . 'user_roles',
            USERS_DATA_TABLE = ENV_DB_PREF . 'users_data',
            USERS_MESSAGE_TABLE = ENV_DB_PREF . 'users_message',
            USERS_ACTIVATION_TABLE = ENV_DB_PREF . 'users_activation';			
	
    /**
     * Возвращает основные логи
     */
    public function get_general_logs() {
        $sql = 'SELECT * FROM ?n ORDER BY `id` DESC';
        return SafeMySQL::gi()->getAll($sql, self::LOGS);
    }
	
    /**
     * Возвращает API логи
     */
    public function get_API_logs() {
        $sql = 'SELECT * FROM ?n ORDER BY `id` DESC';
        return SafeMySQL::gi()->getAll($sql, self::LOGS_SITE_API);
    }
	
	public function dell_user_data($id, $flag) {
		return true;
	}
	
	/**
	* Очистит все таблицы БД
	*/
	public function kill_db() {
        $all_tables = [self::USERS_TABLE, self::DELL_USERS_TABLE, self::USERS_ROLES_TABLE, self::USERS_DATA_TABLE, self::USERS_MESSAGE_TABLE, self::USERS_ACTIVATION_TABLE];
        $sql = 'TRUNCATE TABLE ?n';
        foreach ($all_tables as $item) {
            SafeMySQL::gi()->query($sql, ENV_DB_PREF . $item);
        }		
	}
}
