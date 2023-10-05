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
            CATEGORIES_TABLE_FIELDS = [],
            TYPES_TABLE = ENV_DB_PREF . 'categories_types',
            TYPES_TABLE_FIELDS = [],
            ENTITIES_TABLE = ENV_DB_PREF . 'entities',
            ENTITIES_TABLE_FIELDS = [],
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
  3 => 'default_values',
  4 => 'is_multiple',
  5 => 'is_required',
  6 => 'description',
  7 => 'created_at',
  8 => 'updated_at',
  9 => 'language_code',
),
            PROPERTY_VALUES_TABLE = ENV_DB_PREF . 'property_values',
            PROPERTY_VALUES_TABLE_FIELDS = [],
            SEARCH_CONTENTS_TABLE = ENV_DB_PREF . 'search_contents',
            ALL_STATUS = ['active' => 'active', 'hidden' => 'hidden', 'disabled' => 'disabled'],
            ALL_TYPE_PROPERTY_TYPES_FIELDS = [
                "text" => "Text",
                "date" => "Date",
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
