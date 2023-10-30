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
            USERS_MESSAGE_TABLE_FIELDS = [],
            USERS_ACTIVATION_TABLE = ENV_DB_PREF . 'users_activation',
            USERS_ACTIVATION_TABLE_FIELDS = [],
            CATEGORIES_TABLE = ENV_DB_PREF . 'categories',
            CATEGORIES_TABLE_FIELDS = [],
            CATEGORIES_TYPES_TABLE = ENV_DB_PREF . 'categories_types',
            CATEGORIES_TYPES_TABLE_FIELDS = [],
            ENTITIES_TABLE = ENV_DB_PREF . 'entities',
            ENTITIES_TABLE_FIELDS = [],
            PROPERTY_TYPES_TABLE = ENV_DB_PREF . 'property_types',
            PROPERTY_TYPES_TABLE_FIELDS = [],
            PROPERTIES_TABLE = ENV_DB_PREF . 'properties',
            PROPERTIES_TABLE_FIELDS = [],
            PROPERTY_VALUES_TABLE = ENV_DB_PREF . 'property_values',
            PROPERTY_VALUES_TABLE_FIELDS = [],
            PROPERTY_SETS_TABLE = ENV_DB_PREF . 'property_sets',
            PROPERTY_SETS_TABLE_FIELDS = [],
            CATEGORY_TYPE_TO_PROPERTY_SET_TABLE = ENV_DB_PREF . 'category_type_to_property_set',
            CATEGORY_TYPE_TO_PROPERTY_SET_TABLE_FIELDS = [],
            PROPERTY_SET_TO_PROPERTIES_TABLE = ENV_DB_PREF . 'property_set_to_properties',
            PROPERTY_SET_TO_PROPERTIES_TABLE_FIELDS = [],
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
