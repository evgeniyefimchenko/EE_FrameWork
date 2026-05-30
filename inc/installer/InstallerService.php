<?php

class EEInstallException extends RuntimeException {
}

class EEInstallerService {

    private string $rootPath;
    private array $lastState = [];

    public function __construct(?string $rootPath = null) {
        $this->rootPath = rtrim($rootPath ?: dirname(__DIR__), '/\\');
    }

    public function getStatus(): array {
        $state = $this->readState();
        $installed = $this->detectInstalled();

        return [
            'locked' => $this->isLocked($installed),
            'lock_file_exists' => is_file($this->getLockPath()),
            'installed' => $installed['installed'],
            'config_exists' => $installed['config_exists'],
            'database_connected' => $installed['database_connected'],
            'core_tables_ok' => $installed['core_tables_ok'],
            'auth_tables_ok' => $installed['auth_tables_ok'],
            'cron_tables_ok' => $installed['cron_tables_ok'],
            'message' => $installed['message'],
            'state' => $state,
            'paths' => [
                'configuration' => $this->getConfigPath(),
                'sample' => $this->path('inc/configuration.sample.php'),
                'state' => $this->getStatePath(),
                'lock' => $this->getLockPath(),
            ],
        ];
    }

    public function run(array $input, ?callable $onProgress = null): array {
        $options = $this->normalizeInput($input);
        $installed = $this->detectInstalled();

        if ($this->isLocked($installed) && !$options['force']) {
            throw new EEInstallException('Установщик закрыт: проект уже установлен. Для повторного запуска используйте CLI с --force и --overwrite-config.');
        }
        if ($installed['config_exists'] && !$options['overwrite_config']) {
            throw new EEInstallException('inc/configuration.php уже существует. Укажите overwrite_config только если вы осознанно заменяете конфиг.');
        }

        $this->resetState();
        $this->progress(2, 'start', 'Старт установки', $onProgress);

        $this->progress(8, 'environment', 'Проверка PHP, расширений и runtime-директорий', $onProgress);
        $this->assertEnvironment();
        $this->prepareRuntimeDirectories();

        $this->progress(18, 'database-admin', 'Проверка прав на создание БД и пользователя', $onProgress);
        $this->prepareDatabaseAndUser($options);

        $this->progress(32, 'database-connect', 'Проверка подключения к целевой БД', $onProgress);
        $this->testTargetDatabase($options);

        $this->progress(45, 'configuration', 'Генерация inc/configuration.php из шаблона', $onProgress);
        $config = $this->buildConfiguration($options);
        $backupPath = $this->writeConfiguration($config, $options['overwrite_config']);

        $this->progress(62, 'schema', 'Разворачивание таблиц и стартовых данных', $onProgress);
        $this->bootstrapRuntimeAndInstallSchema();

        $this->progress(82, 'health-check', 'Проверка результата установки', $onProgress);
        $health = $this->collectHealthReport();
        $this->assertHealthIsUsable($health);

        $this->progress(92, 'lock', 'Закрытие установщика lock-файлом', $onProgress);
        $this->writeLock($options);

        $cronCommand = 'cd ' . $this->rootPath . ' && /usr/bin/php app/cron/run.php >/dev/null 2>&1';
        $result = [
            'success' => true,
            'message' => 'Установка завершена.',
            'backup_path' => $backupPath,
            'configuration' => $this->getConfigPath(),
            'lock' => $this->getLockPath(),
            'site_url' => $options['canonical_scheme'] . '://' . $options['site_host'],
            'admin_email' => $options['admin_email'],
            'moderator_email' => $options['support_email'],
            'generated_credentials' => [
                'admin_password_generated' => $options['admin_password_generated'],
                'moderator_password_generated' => $options['moderator_password_generated'],
                'admin_password' => $options['admin_password_generated'] ? $options['admin_password'] : '',
                'moderator_password' => $options['moderator_password_generated'] ? $options['moderator_password'] : '',
            ],
            'cron' => [
                'recommended_line' => '* * * * * ' . $cronCommand,
                'hint' => 'Добавьте строку в crontab пользователя, от которого должен работать scheduler.',
            ],
            'health' => [
                'database_connected' => !empty($health['install']['database_connected']),
                'core_tables_ok' => !empty($health['install']['core_tables_ok']),
                'auth_tables_ok' => !empty($health['install']['auth_tables_ok']),
                'cron_tables_ok' => !empty($health['install']['cron_tables_ok']),
                'alerts_summary' => $health['alerts_summary'] ?? [],
            ],
        ];

        $this->progress(100, 'done', 'Установка завершена', $onProgress, false);
        $this->writeState(array_merge($this->lastState, [
            'running' => false,
            'success' => true,
            'finished_at' => gmdate('c'),
            'result' => $this->redactResultForState($result),
        ]));

        return $result;
    }

    public function detectInstalled(): array {
        $configPath = $this->getConfigPath();
        $result = [
            'installed' => false,
            'config_exists' => is_file($configPath),
            'database_connected' => false,
            'core_tables_ok' => false,
            'auth_tables_ok' => false,
            'cron_tables_ok' => false,
            'message' => '',
        ];

        if (!$result['config_exists']) {
            $result['message'] = 'configuration.php не найден.';
            return $result;
        }

        try {
            $config = require $configPath;
            if (!is_array($config)) {
                $result['message'] = 'configuration.php не вернул массив.';
                return $result;
            }
        } catch (Throwable $e) {
            $result['message'] = 'configuration.php не читается: ' . $e->getMessage();
            return $result;
        }

        try {
            $mysqli = $this->connect(
                (string) ($config['ENV_DB_HOST'] ?? 'localhost'),
                (string) ($config['ENV_DB_USER'] ?? ''),
                (string) ($config['ENV_DB_PASS'] ?? ''),
                (string) ($config['ENV_DB_NAME'] ?? '')
            );
            $result['database_connected'] = true;
            $prefix = (string) ($config['ENV_DB_PREF'] ?? 'ee_');
            $result['core_tables_ok'] = $this->tablesExist($mysqli, [
                $prefix . 'users',
                $prefix . 'user_roles',
                $prefix . 'categories',
                $prefix . 'pages',
            ]);
            $result['auth_tables_ok'] = $this->tablesExist($mysqli, [
                $prefix . 'user_auth_sessions',
                $prefix . 'user_auth_credentials',
                $prefix . 'user_auth_identities',
                $prefix . 'user_auth_challenges',
            ]);
            $result['cron_tables_ok'] = $this->tablesExist($mysqli, [
                $prefix . 'cron_agents',
                $prefix . 'cron_agent_runs',
            ]);
            $mysqli->close();
            $result['installed'] = $result['core_tables_ok'] && $result['auth_tables_ok'] && $result['cron_tables_ok'];
            $result['message'] = $result['installed'] ? 'Проект уже установлен.' : 'Конфиг есть, но схема БД неполная.';
        } catch (Throwable $e) {
            $result['message'] = 'Не удалось проверить БД: ' . $e->getMessage();
        }

        return $result;
    }

    private function normalizeInput(array $input): array {
        $requestHost = $this->normalizeHost((string) ($input['site_host'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost')));
        $scheme = strtolower(trim((string) ($input['canonical_scheme'] ?? $input['site_scheme'] ?? $this->detectRequestScheme())));
        $scheme = $scheme === 'http' ? 'http' : 'https';
        $siteName = trim((string) ($input['site_name'] ?? $requestHost));
        $siteDescription = trim((string) ($input['site_description'] ?? $siteName));
        $siteAuthor = trim((string) ($input['site_author'] ?? $input['owner_name'] ?? ''));
        $siteEmail = trim((string) ($input['site_email'] ?? $input['admin_email'] ?? ''));
        $adminEmail = trim((string) ($input['admin_email'] ?? $siteEmail));
        $supportEmail = trim((string) ($input['support_email'] ?? $siteEmail));
        $protoLanguage = $this->normalizeLanguageCode((string) ($input['proto_language'] ?? 'EN'), 'EN');
        $contentLangs = $this->normalizeLanguageList($input['content_langs'] ?? ($input['content_lang'] ?? 'RU'), ['RU']);
        $dbPrefix = trim((string) ($input['db_prefix'] ?? $input['db_pref'] ?? 'ee_'));
        $dbUserHost = trim((string) ($input['db_user_host'] ?? 'localhost'));
        $bootstrap533Cdn = array_key_exists('bootstrap533_cdn', $input) ? $this->truthy($input['bootstrap533_cdn']) : true;
        $fontAwesomeCdn = array_key_exists('font_awesome_cdn', $input) ? $this->truthy($input['font_awesome_cdn']) : true;
        $adminPassword = (string) ($input['admin_password'] ?? '');
        $moderatorPassword = (string) ($input['moderator_password'] ?? '');
        $adminPasswordGenerated = false;
        $moderatorPasswordGenerated = false;

        if ($adminPassword === '') {
            $adminPassword = $this->generatePassword();
            $adminPasswordGenerated = true;
        }
        if ($moderatorPassword === '') {
            $moderatorPassword = $this->generatePassword();
            $moderatorPasswordGenerated = true;
        }

        $options = [
            'site_host' => $requestHost,
            'canonical_scheme' => $scheme,
            'site_name' => $siteName !== '' ? $siteName : $requestHost,
            'site_description' => $siteDescription !== '' ? $siteDescription : $siteName,
            'site_author' => $siteAuthor,
            'site_email' => $siteEmail,
            'admin_email' => $adminEmail,
            'support_email' => $supportEmail !== '' ? $supportEmail : $adminEmail,
            'legal_operator_status' => trim((string) ($input['legal_operator_status'] ?? 'Физическое лицо')),
            'legal_operator_name' => trim((string) ($input['legal_operator_name'] ?? $siteAuthor)),
            'legal_operator_address' => trim((string) ($input['legal_operator_address'] ?? 'Не указан')),
            'legal_operator_inn' => trim((string) ($input['legal_operator_inn'] ?? 'Не указан')),
            'legal_operator_ogrn' => trim((string) ($input['legal_operator_ogrn'] ?? 'Не указан')),
            'proto_language' => $protoLanguage,
            'content_langs' => $contentLangs,
            'bootstrap533_cdn' => $bootstrap533Cdn,
            'font_awesome_cdn' => $fontAwesomeCdn,
            'db_host' => trim((string) ($input['db_host'] ?? 'localhost')),
            'db_name' => trim((string) ($input['db_name'] ?? '')),
            'db_user' => trim((string) ($input['db_user'] ?? '')),
            'db_pass' => (string) ($input['db_pass'] ?? ''),
            'db_prefix' => $dbPrefix,
            'db_admin_user' => trim((string) ($input['db_admin_user'] ?? '')),
            'db_admin_pass' => (string) ($input['db_admin_pass'] ?? ''),
            'db_user_host' => $dbUserHost !== '' ? $dbUserHost : 'localhost',
            'create_database' => $this->truthy($input['create_database'] ?? false),
            'create_user' => $this->truthy($input['create_user'] ?? false),
            'overwrite_config' => $this->truthy($input['overwrite_config'] ?? false),
            'force' => $this->truthy($input['force'] ?? false),
            'admin_password' => $adminPassword,
            'moderator_password' => $moderatorPassword,
            'admin_password_generated' => $adminPasswordGenerated,
            'moderator_password_generated' => $moderatorPasswordGenerated,
        ];

        $this->validateOptions($options);
        return $options;
    }

    private function validateOptions(array $options): void {
        $required = [
            'site_host' => 'домен сайта',
            'site_author' => 'владелец/автор сайта',
            'site_email' => 'почта сайта',
            'admin_email' => 'почта администратора',
            'db_host' => 'хост БД',
            'db_name' => 'имя БД',
            'db_user' => 'пользователь БД',
        ];
        foreach ($required as $key => $label) {
            if (trim((string) ($options[$key] ?? '')) === '') {
                throw new EEInstallException('Не заполнено поле: ' . $label . '.');
            }
        }
        foreach (['site_email', 'admin_email', 'support_email'] as $emailKey) {
            $email = trim((string) ($options[$emailKey] ?? ''));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new EEInstallException('Некорректный email в поле ' . $emailKey . '.');
            }
        }
        if (!preg_match('~^[a-zA-Z0-9_]+$~', (string) $options['db_name'])) {
            throw new EEInstallException('Имя БД может содержать только латиницу, цифры и подчёркивание.');
        }
        if (!preg_match('~^[a-zA-Z0-9_\\-.]+$~', (string) $options['db_user'])) {
            throw new EEInstallException('Имя пользователя БД содержит недопустимые символы.');
        }
        if (!preg_match('~^[a-zA-Z0-9_]*$~', (string) $options['db_prefix'])) {
            throw new EEInstallException('Префикс таблиц может содержать только латиницу, цифры и подчёркивание.');
        }
        if (($options['create_database'] || $options['create_user']) && $options['db_admin_user'] === '') {
            throw new EEInstallException('Для создания БД или пользователя укажите db_admin_user.');
        }
        if (!preg_match('~^[A-Z]{2}$~', (string) $options['proto_language'])) {
            throw new EEInstallException('ENV_PROTO_LANGUAGE должен быть двухбуквенным ISO-кодом.');
        }
        if (($options['content_langs'] ?? []) === []) {
            throw new EEInstallException('Укажите минимум один язык контента.');
        }
        if (strlen((string) $options['admin_password']) < 8 || strlen((string) $options['moderator_password']) < 8) {
            throw new EEInstallException('Пароли администратора и модератора должны быть не короче 8 символов.');
        }
    }

    private function assertEnvironment(): void {
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            throw new EEInstallException('Нужен PHP 8.0 или выше. Сейчас: ' . PHP_VERSION);
        }
        foreach (['mysqli', 'json', 'mbstring'] as $extension) {
            if (!extension_loaded($extension)) {
                throw new EEInstallException('Не установлено PHP-расширение: ' . $extension);
            }
        }
        if (!is_readable($this->path('inc/configuration.sample.php'))) {
            throw new EEInstallException('Не найден шаблон inc/configuration.sample.php.');
        }
        if (!is_writable($this->path('inc'))) {
            throw new EEInstallException('Каталог inc недоступен для записи. Установщик не сможет создать configuration.php.');
        }
    }

    private function prepareRuntimeDirectories(): void {
        foreach (['cache/install', 'logs', 'logs/errors', 'uploads', 'uploads/tmp', 'uploads/tmp/backups', 'uploads/files', 'uploads/images', 'uploads/images/avatars', 'backups'] as $relativePath) {
            $path = $this->path($relativePath);
            if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
                throw new EEInstallException('Не удалось создать каталог: ' . $relativePath);
            }
            if (!is_writable($path)) {
                throw new EEInstallException('Каталог недоступен для записи: ' . $relativePath);
            }
        }
    }

    private function prepareDatabaseAndUser(array $options): void {
        if (!$options['create_database'] && !$options['create_user']) {
            return;
        }

        $mysqli = $this->connect($options['db_host'], $options['db_admin_user'], $options['db_admin_pass']);
        if ($options['create_database']) {
            $dbName = $this->escapeIdentifier($options['db_name']);
            $this->queryOrThrow($mysqli, "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        if ($options['create_user']) {
            $user = $mysqli->real_escape_string($options['db_user']);
            $host = $mysqli->real_escape_string($options['db_user_host']);
            $pass = $mysqli->real_escape_string($options['db_pass']);
            $dbName = $this->escapeIdentifier($options['db_name']);
            $this->queryOrThrow($mysqli, "CREATE USER IF NOT EXISTS '{$user}'@'{$host}' IDENTIFIED BY '{$pass}'");
            $this->queryOrThrow($mysqli, "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$user}'@'{$host}'");
            $this->queryOrThrow($mysqli, 'FLUSH PRIVILEGES');
        }
        $mysqli->close();
    }

    private function testTargetDatabase(array $options): void {
        $mysqli = $this->connect($options['db_host'], $options['db_user'], $options['db_pass'], $options['db_name']);
        $mysqli->close();
    }

    private function buildConfiguration(array $options): array {
        $template = require $this->path('inc/configuration.sample.php');
        if (!is_array($template)) {
            throw new EEInstallException('configuration.sample.php должен возвращать массив.');
        }

        $todayHuman = date('d.m.Y');
        $todayIso = date('Y-m-d');

        return array_merge($template, [
            'ENV_DB_HOST' => $options['db_host'],
            'ENV_DB_USER' => $options['db_user'],
            'ENV_DB_PASS' => $options['db_pass'],
            'ENV_DB_NAME' => $options['db_name'],
            'ENV_DB_PREF' => $options['db_prefix'],
            'ENV_SITE_NAME' => $options['site_name'],
            'ENV_SITE_DESCRIPTION' => $options['site_description'],
            'ENV_SITE_AUTHOR' => $options['site_author'],
            'ENV_DATE_SITE_CREATE' => $todayHuman,
            'ENV_CANONICAL_HOST' => $options['site_host'],
            'ENV_CANONICAL_SCHEME' => $options['canonical_scheme'],
            'ENV_AUTH_COOKIE_SECURE' => $options['canonical_scheme'] === 'https',
            'ENV_SECRET_KEY' => $this->randomHex(32),
            'ENV_CACHE_NAMESPACE' => $this->slugify($options['site_host']),
            'ENV_FONT_AWESOME_CDN' => $options['font_awesome_cdn'],
            'ENV_BOOTSTRAP533_CDN' => $options['bootstrap533_cdn'],
            'ENV_PROTO_LANGUAGE' => $options['proto_language'],
            'ENV_CONTENT_LANGS' => $options['content_langs'],
            'ENV_SITE_EMAIL' => $options['site_email'],
            'ENV_ADMIN_EMAIL' => $options['admin_email'],
            'ENV_SUPPORT_EMAIL' => $options['support_email'],
            'ENV_LEGAL_OPERATOR_STATUS' => $options['legal_operator_status'],
            'ENV_LEGAL_OPERATOR_NAME' => $options['legal_operator_name'] !== '' ? $options['legal_operator_name'] : $options['site_author'],
            'ENV_LEGAL_OPERATOR_ADDRESS' => $options['legal_operator_address'],
            'ENV_LEGAL_OPERATOR_INN' => $options['legal_operator_inn'],
            'ENV_LEGAL_OPERATOR_OGRN' => $options['legal_operator_ogrn'],
            'ENV_LEGAL_PRIVACY_POLICY_VERSION' => $todayIso,
            'ENV_LEGAL_PERSONAL_DATA_CONSENT_VERSION' => $todayIso,
            'ENV_LEGAL_PERSONAL_DATA_DISTRIBUTION_CONSENT_VERSION' => $todayIso,
            'ENV_INSTALL_ADMIN_PASSWORD' => $options['admin_password'],
            'ENV_INSTALL_MODERATOR_PASSWORD' => $options['moderator_password'],
        ]);
    }

    private function writeConfiguration(array $config, bool $overwrite): string {
        $configPath = $this->getConfigPath();
        $backupPath = '';
        if (is_file($configPath)) {
            if (!$overwrite) {
                throw new EEInstallException('configuration.php уже существует.');
            }
            $backupPath = $this->path('backups/configuration.' . gmdate('YmdHis') . '.php');
            if (!@copy($configPath, $backupPath)) {
                throw new EEInstallException('Не удалось создать backup текущего configuration.php.');
            }
            @chmod($backupPath, 0660);
        }

        $contents = $this->renderConfigurationFile($config);
        $tmpPath = $configPath . '.tmp.' . getmypid();
        if (@file_put_contents($tmpPath, $contents, LOCK_EX) === false) {
            throw new EEInstallException('Не удалось записать временный configuration.php.');
        }
        @chmod($tmpPath, 0660);
        if (!@rename($tmpPath, $configPath)) {
            @unlink($tmpPath);
            throw new EEInstallException('Не удалось заменить configuration.php.');
        }
        clearstatcache(true, $configPath);
        return $backupPath;
    }

    private function bootstrapRuntimeAndInstallSchema(): void {
        if (!defined('PROJECT_ROOT_DIR')) {
            define('PROJECT_ROOT_DIR', $this->rootPath);
        }
        if (!defined('EE_INSTALL_RUN')) {
            define('EE_INSTALL_RUN', true);
        }

        require_once $this->path('inc/bootstrap.php');
        ee_bootstrap_runtime();
        \classes\system\SysClass::installProjectSchema();
    }

    private function collectHealthReport(): array {
        $modelFile = $this->path('app/admin/models/ModelSystems.php');
        if (!class_exists('ModelSystems', false) && is_file($modelFile)) {
            require_once $modelFile;
        }
        if (!class_exists('ModelSystems', false)) {
            return [
                'install' => [
                    'database_connected' => true,
                    'core_tables_ok' => true,
                    'auth_tables_ok' => true,
                    'cron_tables_ok' => true,
                ],
                'alerts_summary' => ['critical' => 0],
            ];
        }

        $model = new \ModelSystems();
        $report = $model->getHealthReport();
        return is_array($report) ? $report : [];
    }

    private function assertHealthIsUsable(array $health): void {
        $install = is_array($health['install'] ?? null) ? $health['install'] : [];
        foreach (['database_connected', 'core_tables_ok', 'auth_tables_ok', 'cron_tables_ok'] as $key) {
            if (empty($install[$key])) {
                throw new EEInstallException('Health-check не пройден: ' . $key);
            }
        }
    }

    private function writeLock(array $options): void {
        $payload = [
            'installed_at' => gmdate('c'),
            'site_host' => $options['site_host'],
            'site_name' => $options['site_name'],
            'configuration' => $this->getConfigPath(),
        ];
        $this->ensureInstallStateDirectory();
        if (@file_put_contents($this->getLockPath(), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) === false) {
            throw new EEInstallException('Не удалось записать lock-файл установщика.');
        }
        @chmod($this->getLockPath(), 0660);
    }

    private function isLocked(?array $installed = null): bool {
        $installed ??= $this->detectInstalled();
        return is_file($this->getLockPath()) || !empty($installed['installed']);
    }

    private function tablesExist(mysqli $mysqli, array $tableNames): bool {
        foreach ($tableNames as $tableName) {
            $escaped = $mysqli->real_escape_string($tableName);
            $result = $mysqli->query("SHOW TABLES LIKE '{$escaped}'");
            if (!$result || $result->num_rows === 0) {
                return false;
            }
            $result->free();
        }
        return true;
    }

    private function connect(string $host, string $user, string $password, string $database = ''): mysqli {
        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }

        [$hostName, $port] = $this->splitHostPort($host);
        $mysqli = mysqli_init();
        if (!$mysqli) {
            throw new EEInstallException('Не удалось инициализировать mysqli.');
        }

        $ok = @$mysqli->real_connect($hostName, $user, $password, $database !== '' ? $database : null, $port ?: null);
        if (!$ok) {
            $message = mysqli_connect_error() ?: $mysqli->connect_error ?: 'unknown error';
            throw new EEInstallException('Ошибка подключения к БД: ' . $message);
        }
        if (!$mysqli->set_charset('utf8mb4')) {
            throw new EEInstallException('Не удалось включить utf8mb4 для соединения с БД.');
        }
        return $mysqli;
    }

    private function queryOrThrow(mysqli $mysqli, string $sql): void {
        if (!$mysqli->query($sql)) {
            throw new EEInstallException('Ошибка SQL: ' . $mysqli->error);
        }
    }

    private function splitHostPort(string $host): array {
        $host = trim($host);
        if ($host === '') {
            return ['localhost', null];
        }
        if (substr_count($host, ':') === 1) {
            [$name, $port] = explode(':', $host, 2);
            if (ctype_digit($port)) {
                return [$name !== '' ? $name : 'localhost', (int) $port];
            }
        }
        return [$host, null];
    }

    private function escapeIdentifier(string $identifier): string {
        return str_replace('`', '``', $identifier);
    }

    private function renderConfigurationFile(array $config): string {
        $groups = [
            'Настройка базы данных' => ['ENV_DB_HOST', 'ENV_DB_USER', 'ENV_DB_PASS', 'ENV_DB_NAME', 'ENV_DB_PREF'],
            'Технические настройки сайта' => [
                'ENV_SITE_NAME', 'ENV_SITE_DESCRIPTION', 'ENV_SITE_AUTHOR', 'ENV_GET_KEYWORDS',
                'ENV_DATE_SITE_CREATE', 'ENV_DIRSEP', 'ENV_CANONICAL_HOST', 'ENV_CANONICAL_SCHEME',
                'ENV_CANONICAL_REDIRECT', 'ENV_SITE_PATH', 'ENV_LOG', 'ENV_LOG_RETENTION_DAYS',
                'ENV_LOG_ROTATE_FILE_SIZE', 'ENV_LOG_MAX_BACKUPS', 'ENV_LOG_REQUEST_ID_HEADER',
                'ENV_BACKUP_RETENTION_DAYS', 'ENV_BACKUP_MAX_LOCAL_SNAPSHOTS', 'ENV_MEMORY_LIMIT_WEB',
                'ENV_MEMORY_LIMIT_CLI', 'ENV_MEMORY_SOFT_LIMIT_MB', 'ENV_CRON_MEMORY_SOFT_LIMIT_MB',
                'ENV_CRON_AGENT_MEMORY_SOFT_LIMIT_MB', 'ENV_MEDIA_MIRROR_MEMORY_SOFT_LIMIT_MB',
                'ENV_CRON_TICK_TIME_BUDGET_SEC', 'ENV_MEDIA_MIRROR_TIME_BUDGET_SEC',
                'ENV_IMPORT_CREATE_LEGACY_MEDIA_ALIASES', 'ENV_COMPRESS_HTML', 'ENV_SHOW_SIDENAV_FOOTER',
                'ENV_CREATE_WEBP', 'ENV_WEBP_QUALITY', 'ENV_CACHE', 'ENV_CACHE_LIFETIME',
                'ENV_SECRET_KEY', 'ENV_SITE', 'ENV_TEST', 'ENV_FATAL_ERROR_LOGGING',
                'ENV_CONFIRM_EMAIL', 'ENV_TIME_AUTH_SESSION', 'ENV_TIME_ACTIVATION', 'ENV_SITE_INDEX',
                'ENV_FONT_AWESOME_CDN', 'ENV_BOOTSTRAP533_CDN', 'ENV_JQUERY_CDN', 'ENV_CODEMIRROR_CDN',
                'ENV_VENDOR_ASSETS_AUTO_DOWNLOAD', 'ENV_VENDOR_ASSETS_DOWNLOAD_TIMEOUT', 'ENV_CACHE_REDIS',
                'ENV_CACHE_BACKEND', 'ENV_CACHE_NAMESPACE', 'ENV_CACHE_VERSION', 'ENV_GUARD_REDIS',
                'ENV_GUARD_RATE_LIMIT_COUNT', 'ENV_GUARD_RATE_LIMIT_WINDOW', 'ENV_GUARD_RATE_LIMIT_GET_COUNT',
                'ENV_GUARD_RATE_LIMIT_GET_WINDOW', 'ENV_GUARD_RATE_LIMIT_WRITE_COUNT',
                'ENV_GUARD_RATE_LIMIT_WRITE_WINDOW', 'ENV_GUARD_STRIKE_LIMIT', 'ENV_GUARD_STRIKE_TTL',
                'ENV_REDIS_ADDRESS', 'ENV_REDIS_PORT', 'ENV_REDIS_CONNECTION_CACHE_TTL',
                'ENV_MAX_FILE_SIZE', 'ENV_ROUTING_CACHE_ENABLED', 'ENV_ROUTING_CACHE_BACKEND',
            ],
            'Персональные настройки сайта' => [
                'ENV_APP_DIRECTORY', 'ENV_PATH_LANG', 'ENV_PROTO_LANGUAGE', 'ENV_CONTENT_LANGS',
                'ENV_SITE_EMAIL', 'ENV_ADMIN_EMAIL', 'ENV_SUPPORT_EMAIL', 'ENV_LEGAL_OPERATOR_STATUS',
                'ENV_LEGAL_OPERATOR_NAME', 'ENV_LEGAL_OPERATOR_ADDRESS', 'ENV_LEGAL_OPERATOR_INN',
                'ENV_LEGAL_OPERATOR_OGRN', 'ENV_LEGAL_PRIVACY_POLICY_VERSION',
                'ENV_LEGAL_PERSONAL_DATA_CONSENT_VERSION', 'ENV_LEGAL_PERSONAL_DATA_DISTRIBUTION_CONSENT_VERSION',
                'ENV_INSTALL_ADMIN_PASSWORD', 'ENV_INSTALL_MODERATOR_PASSWORD', 'ENV_SMTP',
                'ENV_ONE_IP_ONE_USER', 'ENV_AUTH_TRANSPORT', 'ENV_AUTH_COOKIE_SAMESITE',
                'ENV_AUTH_COOKIE_SECURE', 'ENV_AUTH_TRUST_PROXY_HEADERS', 'ENV_AUTH_TRUSTED_PROXIES',
                'ENV_AUTH_IP_RESTRICTED_ROLES', 'ENV_AUTH_MAX_ACTIVE_SESSIONS_PER_USER', 'ENV_AUTH_GOOGLE_CLIENT_ID',
                'ENV_AUTH_GOOGLE_CLIENT_SECRET', 'ENV_AUTH_GOOGLE_REDIRECT_URI', 'ENV_SMTP_PORT',
                'ENV_SMTP_SERVER', 'ENV_SMTP_LOGIN', 'ENV_SMTP_PASSWORD',
            ],
        ];

        $lines = [];
        $lines[] = '<?php';
        $lines[] = '';
        $lines[] = '/**';
        $lines[] = ' * Основной конфиг EE_FrameWork.';
        $lines[] = ' * Сгенерировано установщиком ' . gmdate('c') . '.';
        $lines[] = ' */';
        $lines[] = '';
        $lines[] = 'return [';
        $lines[] = "    'ENV_VERSION_CORE' => " . $this->exportValue($config['ENV_VERSION_CORE'] ?? '5.4.2') . ',';
        $lines[] = "    'ENV_DEBUG' => " . $this->exportValue((bool) ($config['ENV_DEBUG'] ?? false)) . ',';
        $lines[] = '';

        foreach ($groups as $title => $keys) {
            $lines[] = '    // ' . $title;
            foreach ($keys as $key) {
                if (!array_key_exists($key, $config) || in_array($key, ['ENV_VERSION_CORE', 'ENV_DEBUG'], true)) {
                    continue;
                }
                $lines[] = "    '{$key}' => " . $this->exportConfigValue($key, $config[$key]) . ',';
            }
            $lines[] = '';
        }

        while (end($lines) === '') {
            array_pop($lines);
        }
        $lines[] = '];';
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function exportConfigValue(string $key, mixed $value): string {
        return match ($key) {
            'ENV_DIRSEP' => 'DIRECTORY_SEPARATOR',
            'ENV_SITE_PATH' => "realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR",
            'ENV_PATH_LANG' => "'inc' . DIRECTORY_SEPARATOR . 'langs' . DIRECTORY_SEPARATOR",
            default => $this->exportValue($value),
        };
    }

    private function exportValue(mixed $value): string {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return var_export($value, true);
    }

    private function progress(int $percent, string $stage, string $message, ?callable $onProgress, bool $running = true): void {
        $history = is_array($this->lastState['history'] ?? null) ? $this->lastState['history'] : [];
        $history[] = [
            'at' => gmdate('c'),
            'percent' => $percent,
            'stage' => $stage,
            'message' => $message,
        ];
        $this->lastState = [
            'running' => $running,
            'success' => null,
            'percent' => $percent,
            'stage' => $stage,
            'message' => $message,
            'started_at' => $this->lastState['started_at'] ?? gmdate('c'),
            'updated_at' => gmdate('c'),
            'finished_at' => null,
            'history' => $history,
        ];
        $this->writeState($this->lastState);
        if ($onProgress) {
            $onProgress($this->lastState);
        }
    }

    private function resetState(): void {
        $this->lastState = [
            'running' => true,
            'success' => null,
            'percent' => 0,
            'stage' => 'pending',
            'message' => 'Ожидание запуска',
            'started_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'finished_at' => null,
            'history' => [],
        ];
        $this->writeState($this->lastState);
    }

    public function writeFailureState(Throwable $e): void {
        if ($this->lastState === []) {
            $existingState = $this->readState();
            if (empty($existingState['running'])) {
                return;
            }
        }

        $state = $this->lastState ?: $this->readState();
        $history = is_array($state['history'] ?? null) ? $state['history'] : [];
        $history[] = [
            'at' => gmdate('c'),
            'percent' => (int) ($state['percent'] ?? 0),
            'stage' => 'failed',
            'message' => $e->getMessage(),
        ];
        $state['running'] = false;
        $state['success'] = false;
        $state['stage'] = 'failed';
        $state['message'] = $e->getMessage();
        $state['updated_at'] = gmdate('c');
        $state['finished_at'] = gmdate('c');
        $state['history'] = $history;
        $this->writeState($state);
    }

    private function readState(): array {
        $path = $this->getStatePath();
        if (!is_file($path)) {
            return [
                'running' => false,
                'success' => null,
                'percent' => 0,
                'stage' => 'idle',
                'message' => 'Установка ещё не запускалась.',
                'history' => [],
            ];
        }
        $decoded = json_decode((string) @file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeState(array $state): void {
        $this->ensureInstallStateDirectory();
        @file_put_contents($this->getStatePath(), json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
        @chmod($this->getStatePath(), 0660);
    }

    private function ensureInstallStateDirectory(): void {
        $dir = dirname($this->getStatePath());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private function redactResultForState(array $result): array {
        unset($result['generated_credentials']);
        return $result;
    }

    private function normalizeHost(string $host): string {
        $host = strtolower(trim($host));
        $host = preg_replace('~^https?://~', '', $host) ?? $host;
        $host = preg_replace('~[/?#].*$~', '', $host) ?? $host;
        return trim($host, '.');
    }

    private function detectRequestScheme(): string {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return 'https';
        }
        $forwarded = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwarded !== '') {
            $forwarded = trim(explode(',', $forwarded)[0] ?? '');
            if (in_array($forwarded, ['http', 'https'], true)) {
                return $forwarded;
            }
        }
        return ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') ? 'https' : 'http';
    }

    private function normalizeLanguageCode(string $value, string $fallback): string {
        $value = strtoupper(trim($value));
        $value = preg_replace('~[^A-Z0-9_-]+~', '', $value) ?? '';
        if (preg_match('~^[A-Z]{2}$~', $value)) {
            return $value;
        }

        return $fallback;
    }

    private function normalizeLanguageList(mixed $value, array $fallback): array {
        if (is_string($value)) {
            $items = preg_split('~[\\s,;|]+~', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        } elseif (is_array($value)) {
            $items = $value;
        } else {
            $items = [];
        }

        $languages = [];
        foreach ($items as $item) {
            $language = $this->normalizeLanguageCode((string) $item, '');
            if ($language !== '' && !in_array($language, $languages, true)) {
                $languages[] = $language;
            }
        }

        return $languages !== [] ? $languages : $fallback;
    }

    private function truthy(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }
        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'yes', 'on', 'да'], true);
    }

    private function randomHex(int $bytes): string {
        return bin2hex(random_bytes($bytes));
    }

    private function generatePassword(): string {
        return substr(strtr(base64_encode(random_bytes(18)), '+/', 'Aa'), 0, 24);
    }

    private function slugify(string $value): string {
        $value = preg_replace('~[^a-z0-9]+~i', '-', strtolower($value)) ?? 'ee-site';
        $value = trim($value, '-');
        return $value !== '' ? $value : 'ee-site';
    }

    private function path(string $relativePath): string {
        return $this->rootPath . '/' . ltrim($relativePath, '/\\');
    }

    private function getConfigPath(): string {
        return $this->path('inc/configuration.php');
    }

    private function getStatePath(): string {
        return $this->path('cache/install/state.json');
    }

    private function getLockPath(): string {
        return $this->path('cache/install/install.lock');
    }
}
