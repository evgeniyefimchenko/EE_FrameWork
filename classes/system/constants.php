<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Класс констант таблиц БД
 */
 
 Class Constants {
	const USERS_TABLE = ENV_DB_PREF . 'users',
		DELL_USERS_TABLE = ENV_DB_PREF . 'users_dell',
		USERS_ROLES_TABLE = ENV_DB_PREF . 'user_roles',
		USERS_DATA_TABLE = ENV_DB_PREF . 'users_data',
		USERS_MESSAGE_TABLE = ENV_DB_PREF . 'users_message',
		USERS_ACTIVATION_TABLE = ENV_DB_PREF . 'users_activation',
		CATEGORIES_TABLE = ENV_DB_PREF . 'categories',
		TYPES_TABLE = ENV_DB_PREF . 'categories_types',
		ENTITIES_TABLE = ENV_DB_PREF . 'entities',
		PROPERTY_TYPES_TABLE = ENV_DB_PREF . 'property_types',
		PROPERTIES_TABLE = ENV_DB_PREF . 'properties',
		PROPERTY_VALUES_TABLE = ENV_DB_PREF . 'property_values',
                ALL_STATUS = ['active' => 'active', 'hidden' => 'hidden', 'disabled' => 'disabled'];
 
 }