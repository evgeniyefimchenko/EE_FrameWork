<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Класс констант
 */
Class Constants {

    public const USERS_TABLE = ENV_DB_PREF . 'users',
            USERS_TABLE_FIELDS = [],
            USERS_ROLES_TABLE = ENV_DB_PREF . 'user_roles',
            USERS_ROLES_TABLE_FIELDS = [],
            USERS_DATA_TABLE = ENV_DB_PREF . 'users_data',
            USERS_DATA_TABLE_FIELDS = [],
            USERS_MESSAGE_TABLE = ENV_DB_PREF . 'users_message',
            USERS_MESSAGE_TABLE_FIELDS = [],
            USERS_ACTIVATION_TABLE = ENV_DB_PREF . 'users_activation',
            USERS_ACTIVATION_TABLE_FIELDS = [],
            CATEGORIES_TABLE = ENV_DB_PREF . 'categories',
            CATEGORIES_TABLE_FIELDS = array (
  0 => 'category_id',
  1 => 'type_id',
  2 => 'title',
  3 => 'description',
  4 => 'short_description',
  5 => 'parent_id',
  6 => 'status',
  7 => 'created_at',
  8 => 'updated_at',
),
            TYPES_TABLE = ENV_DB_PREF . 'categories_types',
            TYPES_TABLE_FIELDS = [],
            ENTITIES_TABLE = ENV_DB_PREF . 'entities',
            ENTITIES_TABLE_FIELDS = array (
  0 => 'entity_id',
  1 => 'parent_entity_id',
  2 => 'category_id',
  3 => 'status',
  4 => 'title',
  5 => 'short_description',
  6 => 'description',
  7 => 'created_at',
  8 => 'updated_at',
),
            PROPERTY_TYPES_TABLE = ENV_DB_PREF . 'property_types',
            PROPERTY_TYPES_TABLE_FIELDS = [],
            PROPERTIES_TABLE = ENV_DB_PREF . 'properties',
            PROPERTIES_TABLE_FIELDS = [],
            PROPERTY_VALUES_TABLE = ENV_DB_PREF . 'property_values',
            PROPERTY_VALUES_TABLE_FIELDS = [],
            ALL_STATUS = ['active' => 'active', 'hidden' => 'hidden', 'disabled' => 'disabled'];

}
