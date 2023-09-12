<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
} 

/**
 * Модель логов
 */
 
use Constants;
 
Class Model_logs {	
	
	public function dell_user_data($id, $flag) {
		return true;
	}
	
	/**
	* Очистит все таблицы БД
	*/
	public function kill_db() {
        $all_tables = [Constants::USERS_TABLE, Constants::DELL_USERS_TABLE, Constants::USERS_ROLES_TABLE, Constants::USERS_DATA_TABLE, Constants::USERS_MESSAGE_TABLE, Constants::USERS_ACTIVATION_TABLE];
        $sql = 'TRUNCATE TABLE ?n';
        foreach ($all_tables as $item) {
            SafeMySQL::gi()->query($sql, ENV_DB_PREF . $item);
        }		
	}
}
