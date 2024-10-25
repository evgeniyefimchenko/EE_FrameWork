<?php

namespace classes\system;

/**
 * Класс констант для оперативной работы
 * Может быть дописан из статического класса SysClass::ee_getFieldsTable($tableName)
 */
class Constants {

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
            PAGES_TABLE = ENV_DB_PREF . 'entities',
            PAGES_TABLE_FIELDS = [],
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
            FILES_TABLE = ENV_DB_PREF . 'files_table',
            FILES_TABLE_FIELDS = [],
            ALL_STATUS = ['active' => 'active', 'hidden' => 'hidden', 'disabled' => 'disabled'], // Ручное заполнение
            USERS_STATUS = [1 => 'sys.not_confirmed', 2 => 'sys.active', 3 => 'sys.blocked'],  // Ручное заполнение
            ALL_TYPE_PROPERTY_TYPES_FIELDS = [ // Ручное заполнение
                "text" => "Text",
                "number" => "Number",
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
            ],
            // Роли пользователей
            ALL = 777, // Все
            ALL_AUTH = 100, // Все зарегистрированные пользователи
            ADMIN = 1, // Администратор
            MODERATOR = 2, // Модератор
            MANAGER = 3, // Менеджер
            USER = 4, // Пользователь
            SYSTEM = 8 // Системный пользователь
            ;
}
