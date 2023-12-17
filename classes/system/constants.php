<?php

if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}

/**
 * Класс констант для оперативной работы
 * Может быть дописан из статического класса SysClass::ee_get_fields_table($tableName)
 */
Class Constants {

    public const USERS_TABLE = ENV_DB_PREF . 'users',
            USERS_TABLE_FIELDS = [],
            USERS_ROLES_TABLE = ENV_DB_PREF . 'user_roles',
            USERS_ROLES_TABLE_FIELDS = [],
            USERS_DATA_TABLE = ENV_DB_PREF . 'users_data',
            USERS_DATA_TABLE_FIELDS = [],
            USERS_MESSAGE_TABLE = ENV_DB_PREF . 'users_message',
            USERS_MESSAGE_TABLE_FIELDS = array (
  0 => 'message_id',
  1 => 'user_id',
  2 => 'author_id',
  3 => 'chat_id',
  4 => 'message_text',
  5 => 'created_at',
  6 => 'read_at',
  7 => 'status',
),
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
  9 => 'language_code',
),
            CATEGORIES_TYPES_TABLE = ENV_DB_PREF . 'categories_types',
            CATEGORIES_TYPES_TABLE_FIELDS = array (
  0 => 'type_id',
  1 => 'parent_type_id',
  2 => 'name',
  3 => 'description',
  4 => 'created_at',
  5 => 'updated_at',
  6 => 'language_code',
),
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
  9 => 'language_code',
),
            PROPERTY_TYPES_TABLE = ENV_DB_PREF . 'property_types',
            PROPERTY_TYPES_TABLE_FIELDS = array (
  0 => 'type_id',
  1 => 'name',
  2 => 'status',
  3 => 'fields',
  4 => 'description',
  5 => 'created_at',
  6 => 'updated_at',
  7 => 'language_code',
),
            PROPERTIES_TABLE = ENV_DB_PREF . 'properties',
            PROPERTIES_TABLE_FIELDS = array (
  0 => 'property_id',
  1 => 'type_id',
  2 => 'name',
  3 => 'status',
  4 => 'default_values',
  5 => 'is_multiple',
  6 => 'is_required',
  7 => 'description',
  8 => 'created_at',
  9 => 'updated_at',
  10 => 'language_code',
),
            PROPERTY_VALUES_TABLE = ENV_DB_PREF . 'property_values',
            PROPERTY_VALUES_TABLE_FIELDS = [],
            PROPERTY_SETS_TABLE = ENV_DB_PREF . 'property_sets',
            PROPERTY_SETS_TABLE_FIELDS = array (
  0 => 'set_id',
  1 => 'name',
  2 => 'description',
  3 => 'created_at',
  4 => 'updated_at',
),
            CATEGORY_TYPE_TO_PROPERTY_SET_TABLE = ENV_DB_PREF . 'category_type_to_property_set',
            CATEGORY_TYPE_TO_PROPERTY_SET_TABLE_FIELDS = [],
            PROPERTY_SET_TO_PROPERTIES_TABLE = ENV_DB_PREF . 'property_set_to_properties',
            PROPERTY_SET_TO_PROPERTIES_TABLE_FIELDS = array (
  0 => 'property_id',
  1 => 'type_id',
  2 => 'name',
  3 => 'status',
  4 => 'default_values',
  5 => 'is_multiple',
  6 => 'is_required',
  7 => 'description',
  8 => 'created_at',
  9 => 'updated_at',
  10 => 'language_code',
),
            SEARCH_CONTENTS_TABLE = ENV_DB_PREF . 'search_contents',
            SEARCH_CONTENTS_TABLE_FIELDS = [],
            ALL_STATUS = ['active' => 'active', 'hidden' => 'hidden', 'disabled' => 'disabled'], // Ручное заполнение
            ALL_TYPE_PROPERTY_TYPES_FIELDS = [ // Ручное заполнение
                "text" => "Text",
                "date" => "Date",
                "time" => "Time",
                "datetime-local" => "DateTime",
                "hidden" => "Hidden",
                "password" => "Password",
                "file" => "File",
                "email" => "Email",
                "phone" => "Phone",
                "select" => "Select",
                "textarea" => "Textarea",
                "image" => "Image",
                "checkbox" => "Checkbox",
                "radio" => "Radio Button"
            ];

}
