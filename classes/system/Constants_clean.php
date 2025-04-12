<?php

namespace classes\system;

/**
 * Класс констант для оперативной работы
 * Может быть дописан автоматически из статического класса функцией SysClass::ee_getFieldsTable($tableName)
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
            PAGES_TABLE = ENV_DB_PREF . 'pages',
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
            FILES_TABLE = ENV_DB_PREF . 'files',
            FILES_TABLE_FIELDS = [],
            GLOBAL_OPTIONS = ENV_DB_PREF . 'global_options',
            GLOBAL_OPTIONS_FIELDS = [],
            EMAIL_TEMPLATES_TABLE = ENV_DB_PREF . 'email_templates',
            EMAIL_TEMPLATES_TABLE_FIELDS = [],
            EMAIL_SNIPPETS_TABLE = ENV_DB_PREF . 'email_snippets',
            EMAIL_SNIPPETS_TABLE_FIELDS = [],
            IP_BLACKLIST_TABLE = ENV_DB_PREF . 'ip_blacklist',
            IP_BLACKLIST_TABLE_FIELDS = [],
            ALL_STATUS = ['active' => 'active', 'hidden' => 'hidden', 'disabled' => 'disabled'], // Ручное заполнение
            ALL_ENTITY_TYPE = ['category' => 'sys.categories', 'page' => 'sys.pages', 'all' => 'sys.all'], // Ручное заполнение
            USERS_STATUS = [1 => 'sys.not_confirmed', 2 => 'sys.active', 3 => 'sys.blocked'], // Ручное заполнение
            ALL_TYPE_PROPERTY_TYPES_FIELDS = [ // Типы полей свойств, ручное заполнение
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
            PUBLIC_CONSTANTS = [ // Константы доступные публично для шаблонов писем и т.д.
                'ENV_SITE_NAME' => ENV_SITE_NAME,
                'ENV_DOMEN_NAME' => ENV_DOMEN_NAME,
                'ENV_URL_SITE' => ENV_URL_SITE,
                'ENV_SITE_DESCRIPTION' => ENV_SITE_DESCRIPTION,
                'ENV_VERSION_CORE' => ENV_VERSION_CORE,
                'ENV_SITE_AUTHOR' => ENV_SITE_AUTHOR,
                'ENV_DOMEN_NAME' => ENV_DOMEN_NAME,
                'ENV_SITE_EMAIL' => ENV_SITE_EMAIL,
                'ENV_ADMIN_EMAIL' => ENV_ADMIN_EMAIL,
                'ENV_SUPPORT_EMAIL' => ENV_SUPPORT_EMAIL
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

    