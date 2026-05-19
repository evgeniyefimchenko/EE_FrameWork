<?php

namespace classes\system;

/**
 * Класс констант для оперативной работы
 */
class Constants {

    public const USERS_TABLE = ENV_DB_PREF . 'users',
            USERS_ROLES_TABLE = ENV_DB_PREF . 'user_roles',
            USERS_DATA_TABLE = ENV_DB_PREF . 'users_data',
            USERS_NOTIFICATIONS_TABLE = ENV_DB_PREF . 'users_notifications',
            USERS_MESSAGE_TABLE = ENV_DB_PREF . 'users_message',
            USERS_ACTIVATION_TABLE = ENV_DB_PREF . 'users_activation',
            USERS_AUTH_SESSIONS_TABLE = ENV_DB_PREF . 'user_auth_sessions',
            USERS_AUTH_CREDENTIALS_TABLE = ENV_DB_PREF . 'user_auth_credentials',
            USERS_API_KEYS_TABLE = ENV_DB_PREF . 'user_api_keys',
            USERS_AUTH_IDENTITIES_TABLE = ENV_DB_PREF . 'user_auth_identities',
            USERS_AUTH_CHALLENGES_TABLE = ENV_DB_PREF . 'user_auth_challenges',
            CATEGORIES_TABLE = ENV_DB_PREF . 'categories',
            CATEGORIES_TYPES_TABLE = ENV_DB_PREF . 'categories_types',
            PAGES_TABLE = ENV_DB_PREF . 'pages',
            ENTITY_TRANSLATIONS_TABLE = ENV_DB_PREF . 'entity_translations',
            URL_POLICIES_TABLE = ENV_DB_PREF . 'url_policies',
            ENTITY_LEGACY_PATHS_TABLE = ENV_DB_PREF . 'entity_legacy_paths',
            REDIRECTS_TABLE = ENV_DB_PREF . 'redirects',
            PAGE_USER_LINKS_TABLE = ENV_DB_PREF . 'page_user_links',
            PROPERTY_TYPES_TABLE = ENV_DB_PREF . 'property_types',
            PROPERTIES_TABLE = ENV_DB_PREF . 'properties',
            PROPERTY_VALUES_TABLE = ENV_DB_PREF . 'property_values',
            PROPERTY_SETS_TABLE = ENV_DB_PREF . 'property_sets',
            PROPERTY_LIFECYCLE_JOBS_TABLE = ENV_DB_PREF . 'property_lifecycle_jobs',
            CATEGORY_TYPE_TO_PROPERTY_SET_TABLE = ENV_DB_PREF . 'category_type_to_property_set',
            PROPERTY_SET_TO_PROPERTIES_TABLE = ENV_DB_PREF . 'property_set_to_properties',
            FILTERS_TABLE = ENV_DB_PREF . 'filters',
            SEARCH_INDEX_TABLE = ENV_DB_PREF . 'search_index',
            SEARCH_NGRAMS_TABLE = ENV_DB_PREF . 'search_ngrams',
            SEARCH_LOG_TABLE = ENV_DB_PREF . 'search_log',
            SEARCH_SCOPE_PUBLIC = 1,
            SEARCH_SCOPE_MANAGER = 2,
            SEARCH_SCOPE_ADMIN = 4,
            SEARCH_SCOPE_ALL = 7,
            FILES_TABLE = ENV_DB_PREF . 'files',
            GLOBAL_OPTIONS = ENV_DB_PREF . 'global_options',
            EMAIL_TEMPLATES_TABLE = ENV_DB_PREF . 'email_templates',
            EMAIL_SNIPPETS_TABLE = ENV_DB_PREF . 'email_snippets',
            IP_BLACKLIST_TABLE = ENV_DB_PREF . 'ip_blacklist',
            IP_REQUEST_LOGS_TABLE = ENV_DB_PREF . 'ip_request_logs',
            IP_OFFENSES_TABLE = ENV_DB_PREF . 'ip_offenses',
            IMPORT_SETTINGS_TABLE = ENV_DB_PREF . 'import_settings',
            IMPORT_MAP_TABLE = ENV_DB_PREF . 'import_map',
            IMPORT_MEDIA_QUEUE_TABLE = ENV_DB_PREF . 'import_media_queue',
            BACKUP_TARGETS_TABLE = ENV_DB_PREF . 'backup_targets',
            BACKUP_PLANS_TABLE = ENV_DB_PREF . 'backup_plans',
            BACKUP_JOBS_TABLE = ENV_DB_PREF . 'backup_jobs',
            CRON_AGENTS_TABLE = ENV_DB_PREF . 'cron_agents',
            CRON_AGENT_RUNS_TABLE = ENV_DB_PREF . 'cron_agent_runs',
            ALL_STATUS = ['active' => 'active', 'hidden' => 'hidden', 'disabled' => 'disabled'], // Ручное заполнение
            ALL_ENTITY_TYPE = ['category' => 'sys.categories', 'page' => 'sys.pages', 'all' => 'sys.all'], // Ручное заполнение
            USERS_STATUS = [1 => 'sys.not_confirmed', 2 => 'sys.active', 3 => 'sys.blocked'], // Ручное заполнение
            ALL_TYPE_PROPERTY_TYPES_FIELDS = [ // Типы полей свойств, ручное заполнение
            "text" => "Text",
            "number" => "Number",
            "date" => "Date",
            "date-range" => "Date range",
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
            "radio" => "Radio Button",
            "repeatable-group" => "Repeatable group"
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

    
