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
            USERS_ROLES_TABLE_FIELDS = ['role_id', 'role_key', 'name'],
            USERS_DATA_TABLE = ENV_DB_PREF . 'users_data',
            USERS_DATA_TABLE_FIELDS = [],
            USERS_NOTIFICATIONS_TABLE = ENV_DB_PREF . 'users_notifications',
            USERS_NOTIFICATIONS_TABLE_FIELDS = [],
            USERS_MESSAGE_TABLE = ENV_DB_PREF . 'users_message',
            USERS_MESSAGE_TABLE_FIELDS = ['message_id','user_id','author_id','chat_id','message_text','created_at','read_at','status'],
            USERS_ACTIVATION_TABLE = ENV_DB_PREF . 'users_activation',
            USERS_ACTIVATION_TABLE_FIELDS = [],
            USERS_AUTH_SESSIONS_TABLE = ENV_DB_PREF . 'user_auth_sessions',
            USERS_AUTH_SESSIONS_TABLE_FIELDS = [],
            USERS_AUTH_CREDENTIALS_TABLE = ENV_DB_PREF . 'user_auth_credentials',
            USERS_AUTH_CREDENTIALS_TABLE_FIELDS = [],
            USERS_AUTH_IDENTITIES_TABLE = ENV_DB_PREF . 'user_auth_identities',
            USERS_AUTH_IDENTITIES_TABLE_FIELDS = [],
            USERS_AUTH_CHALLENGES_TABLE = ENV_DB_PREF . 'user_auth_challenges',
            USERS_AUTH_CHALLENGES_TABLE_FIELDS = [],
            CATEGORIES_TABLE = ENV_DB_PREF . 'categories',
            CATEGORIES_TABLE_FIELDS = ['category_id','type_id','title','slug','route_path','description','short_description','parent_id','status','created_at','updated_at','language_code'],
            CATEGORIES_TYPES_TABLE = ENV_DB_PREF . 'categories_types',
            CATEGORIES_TYPES_TABLE_FIELDS = ['type_id','parent_type_id','name','description','created_at','updated_at','language_code'],
            PAGES_TABLE = ENV_DB_PREF . 'pages',
            PAGES_TABLE_FIELDS = ['page_id','parent_page_id','category_id','status','title','slug','route_path','short_description','description','created_at','updated_at','language_code'],
            ENTITY_TRANSLATIONS_TABLE = ENV_DB_PREF . 'entity_translations',
            ENTITY_TRANSLATIONS_TABLE_FIELDS = ['translation_id','entity_type','entity_id','translation_group_key','language_code','is_primary','created_at','updated_at'],
            PAGE_USER_LINKS_TABLE = ENV_DB_PREF . 'page_user_links',
            PAGE_USER_LINKS_TABLE_FIELDS = ['link_id','page_id','user_id','relation_type','created_at','updated_at'],
            PROPERTY_TYPES_TABLE = ENV_DB_PREF . 'property_types',
            PROPERTY_TYPES_TABLE_FIELDS = ['type_id','name','status','fields','schema_version','description','created_at','updated_at','language_code'],
            PROPERTIES_TABLE = ENV_DB_PREF . 'properties',
            PROPERTIES_TABLE_FIELDS = ['property_id','type_id','name','status','sort','default_values','schema_version','is_multiple','is_required','description','entity_type','created_at','updated_at','language_code'],
            PROPERTY_VALUES_TABLE = ENV_DB_PREF . 'property_values',
            PROPERTY_VALUES_TABLE_FIELDS = ['value_id','entity_id','set_id','property_id','entity_type','property_values','created_at','updated_at','language_code'],
            PROPERTY_SETS_TABLE = ENV_DB_PREF . 'property_sets',
            PROPERTY_SETS_TABLE_FIELDS = ['set_id','name','description','created_at','updated_at','language_code'],
            PROPERTY_LIFECYCLE_JOBS_TABLE = ENV_DB_PREF . 'property_lifecycle_jobs',
            PROPERTY_LIFECYCLE_JOBS_TABLE_FIELDS = [],
            CATEGORY_TYPE_TO_PROPERTY_SET_TABLE = ENV_DB_PREF . 'category_type_to_property_set',
            CATEGORY_TYPE_TO_PROPERTY_SET_TABLE_FIELDS = [],
            PROPERTY_SET_TO_PROPERTIES_TABLE = ENV_DB_PREF . 'property_set_to_properties',
            PROPERTY_SET_TO_PROPERTIES_TABLE_FIELDS = ['property_id','type_id','name','status','sort','default_values','schema_version','is_multiple','is_required','description','entity_type','created_at','updated_at','language_code'],
            FILTERS_TABLE = ENV_DB_PREF . 'filters',
            FILTERS_TABLE_FIELDS = [],
            SEARCH_INDEX_TABLE = ENV_DB_PREF . 'search_index',
            SEARCH_INDEX_TABLE_FIELDS = [],
            SEARCH_NGRAMS_TABLE = ENV_DB_PREF . 'search_ngrams',
            SEARCH_NGRAMS_TABLE_FIELDS = [],
            SEARCH_LOG_TABLE = ENV_DB_PREF . 'search_log',
            SEARCH_LOG_TABLE_FIELDS = [],
            FILES_TABLE = ENV_DB_PREF . 'files',
            FILES_TABLE_FIELDS = ['file_id','name','original_name','file_path','file_url','mime_type','size','image_size','user_id','file_hash','uploaded_at','updated_at'],
            GLOBAL_OPTIONS = ENV_DB_PREF . 'global_options',
            GLOBAL_OPTIONS_FIELDS = [],
            EMAIL_TEMPLATES_TABLE = ENV_DB_PREF . 'email_templates',
            EMAIL_TEMPLATES_TABLE_FIELDS = [],
            EMAIL_SNIPPETS_TABLE = ENV_DB_PREF . 'email_snippets',
            EMAIL_SNIPPETS_TABLE_FIELDS = [],
            IP_BLACKLIST_TABLE = ENV_DB_PREF . 'ip_blacklist',
            IP_BLACKLIST_TABLE_FIELDS = [],
            IP_REQUEST_LOGS_TABLE = ENV_DB_PREF . 'ip_request_logs',
            IP_REQUEST_LOGS_TABLE_FIELDS = [],
            IP_OFFENSES_TABLE = ENV_DB_PREF . 'ip_offenses',
            IP_OFFENSES_TABLE_FIELDS = [],
            IMPORT_SETTINGS_TABLE = ENV_DB_PREF . 'import_settings',
            IMPORT_SETTINGS_TABLE_FIELDS = [],
            IMPORT_MAP_TABLE = ENV_DB_PREF . 'import_map',
            IMPORT_MAP_TABLE_FIELDS = ['map_id','job_id','source_key','map_type','source_id','local_id','created_at','updated_at'],
            IMPORT_MEDIA_QUEUE_TABLE = ENV_DB_PREF . 'import_media_queue',
            IMPORT_MEDIA_QUEUE_TABLE_FIELDS = [],
            BACKUP_TARGETS_TABLE = ENV_DB_PREF . 'backup_targets',
            BACKUP_TARGETS_TABLE_FIELDS = [],
            BACKUP_PLANS_TABLE = ENV_DB_PREF . 'backup_plans',
            BACKUP_PLANS_TABLE_FIELDS = [],
            BACKUP_JOBS_TABLE = ENV_DB_PREF . 'backup_jobs',
            BACKUP_JOBS_TABLE_FIELDS = [],
            CRON_AGENTS_TABLE = ENV_DB_PREF . 'cron_agents',
            CRON_AGENTS_TABLE_FIELDS = [],
            CRON_AGENT_RUNS_TABLE = ENV_DB_PREF . 'cron_agent_runs',
            CRON_AGENT_RUNS_TABLE_FIELDS = [],
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

    
