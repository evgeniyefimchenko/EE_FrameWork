<?php

use classes\system\ControllerBase;
use classes\system\ApiKeyService;
use classes\system\CsrfService;
use classes\system\SysClass;
use classes\system\Constants;
use classes\system\AuthService;
use classes\system\AuthSessionService;
use classes\system\Plugins;
use classes\plugins\SafeMySQL;
use classes\helpers\ClassNotifications;
use classes\system\Hook;
use classes\system\OperationResult;
use classes\system\CronAgentService;
use classes\system\BackupService;
use classes\system\ImportMediaQueueService;
use app\admin\MessagesTrait;
use app\admin\NotificationsTrait;
use app\admin\SystemsTrait;
use app\admin\EmailsTrait;
use app\admin\CategoriesTrait;
use app\admin\CategoriesTypesTrait;
use app\admin\PagesTrait;
use app\admin\PropertiesTrait;
use app\admin\ImportTrait;
use app\admin\CronAgentsTrait;
use app\admin\UrlManagementTrait;
use classes\helpers\ClassMessages;

/*
 * Админ-панель
 */

class ControllerAdmin Extends ControllerBase {
    /* Подключение traits */

use MessagesTrait,
    NotificationsTrait,
    SystemsTrait,
    EmailsTrait,
    CategoriesTrait,
    CategoriesTypesTrait,
    PagesTrait,
    PropertiesTrait,
    ImportTrait,
    CronAgentsTrait,
    UrlManagementTrait;

    /**
     * Главная страница админ-панели
     */
    public function index($params = []): void {
        if (!$this->requireAccess([Constants::ALL_AUTH], [
            'return' => 'admin',
            'initiator' => __METHOD__,
        ])) {
            return;
        }
        /* models */
        /* get user data - все переменные пользователя доступны в представлениях */
        $user_data = $this->users->data;
        /* views */
        $this->getStandardViews();
        $this->view->set('dashboard_overview', $this->getAdminDashboardOverview($user_data));
        $this->view->set('body_view', $this->view->read('v_dashboard_admin'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $dashboardTitle = (string) ($this->lang['sys.dashboard_overview_heading'] ?? $this->lang['sys.dashboard'] ?? 'Dashboard');
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . $dashboardTitle;
        $this->parameters_layout["description"] = ENV_SITE_DESCRIPTION . ' - ' . $dashboardTitle;
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    public function dashboard_online_users($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'ajax' => true,
            'return' => 'admin',
            'initiator' => __METHOD__,
        ])) {
            return;
        }
        if ($params || !SysClass::isAjaxRequestFromSameSite()) {
            die(json_encode(['error' => 'it`s a lie'], JSON_UNESCAPED_UNICODE));
        }

        $minutes = 15;
        $snapshot = AuthSessionService::getOnlineUsersSnapshot($minutes);
        $this->getStandardViews();
        $this->view->set('online_users_snapshot', $snapshot);
        $this->view->set('online_users_minutes', $minutes);
        $html = $this->view->read('v_dashboard_online_users');

        die(json_encode([
            'error' => '',
            'html' => $html,
            'generated_at' => (string) ($snapshot['generated_at'] ?? ''),
        ], JSON_UNESCAPED_UNICODE));
    }

    private function getAdminDashboardOverview(array $userData): array {
        $isAdmin = (int) ($userData['user_role'] ?? 0) === Constants::ADMIN;
        $overview = [
            'is_admin' => $isAdmin,
            'generated_at' => date('c'),
            'generated_at_pretty' => date('d.m.Y H:i'),
            'quick_actions' => $this->getAdminDashboardQuickActions($isAdmin),
            'user_summary' => [
                'name' => (string) ($userData['name'] ?? ''),
                'email' => (string) ($userData['email'] ?? ''),
                'notifications_count' => count((array) ($userData['notifications'] ?? [])),
                'messages_count' => (int) ($userData['count_messages'] ?? 0),
            ],
        ];

        if (!$isAdmin || !SysClass::checkDatabaseConnection()) {
            return $overview;
        }

        $operations = $this->getAdminDashboardOperations();
        $recentBackups = BackupService::getRecentJobs(10);
        $overview['catalog'] = $this->getAdminDashboardCatalogStats();
        $overview['platform'] = $this->getAdminDashboardPlatformStats();
        $overview['operations'] = $operations;
        $overview['recent_imports'] = $this->getAdminDashboardRecentImports();
        $overview['recent_runs'] = CronAgentService::getRecentRuns(10);
        $overview['recent_backups'] = $recentBackups;
        $overview['health_alerts'] = array_slice((array) ($operations['alerts'] ?? []), 0, 6);

        return $overview;
    }

    private function getAdminDashboardQuickActions(bool $isAdmin): array {
        if (!$isAdmin) {
            return [
                [
                    'label' => $this->lang['sys.edit_profile'] ?? 'Редактировать профиль',
                    'href' => '/admin/user_edit',
                    'icon' => 'fa-user-pen',
                    'class' => 'btn-outline-primary',
                ],
            ];
        }

        return [
            [
                'label' => $this->lang['sys.health'] ?? 'Состояние системы',
                'href' => '/admin/health',
                'icon' => 'fa-heart-pulse',
                'class' => 'btn-outline-primary',
            ],
            [
                'label' => $this->lang['sys.imports_profiles_list'] ?? 'Импорт',
                'href' => '/admin/imports',
                'icon' => 'fa-upload',
                'class' => 'btn-outline-secondary',
            ],
            [
                'label' => $this->lang['sys.cron_agents'] ?? 'Cron-агенты',
                'href' => '/admin/cron_agents',
                'icon' => 'fa-clock-rotate-left',
                'class' => 'btn-outline-secondary',
            ],
            [
                'label' => $this->lang['sys.pages'] ?? 'Страницы',
                'href' => '/admin/pages',
                'icon' => 'fa-file',
                'class' => 'btn-outline-secondary',
            ],
            [
                'label' => $this->lang['sys.backup'] ?? 'Резервное копирование',
                'href' => '/admin/backup',
                'icon' => 'fa-box-archive',
                'class' => 'btn-primary',
            ],
        ];
    }

    private function getAdminDashboardCatalogStats(): array {
        $db = SafeMySQL::gi();
        $pageStatus = [
            'total' => 0,
            'active' => 0,
            'hidden' => 0,
            'disabled' => 0,
        ];
        foreach ((array) $db->getAll('SELECT status, COUNT(*) AS total FROM ?n GROUP BY status', Constants::PAGES_TABLE) as $row) {
            $status = (string) ($row['status'] ?? '');
            $count = (int) ($row['total'] ?? 0);
            if (array_key_exists($status, $pageStatus)) {
                $pageStatus[$status] = $count;
                $pageStatus['total'] += $count;
            }
        }

        $categoryStatus = [
            'total' => 0,
            'active' => 0,
            'hidden' => 0,
            'disabled' => 0,
        ];
        foreach ((array) $db->getAll('SELECT status, COUNT(*) AS total FROM ?n GROUP BY status', Constants::CATEGORIES_TABLE) as $row) {
            $status = (string) ($row['status'] ?? '');
            $count = (int) ($row['total'] ?? 0);
            if (array_key_exists($status, $categoryStatus)) {
                $categoryStatus[$status] = $count;
                $categoryStatus['total'] += $count;
            }
        }

        return [
            'pages' => $pageStatus,
            'categories' => [
                'total' => $categoryStatus['total'],
                'active' => $categoryStatus['active'],
                'hidden' => $categoryStatus['hidden'],
            ],
            'users' => [
                'total' => (int) $db->getOne('SELECT COUNT(*) FROM ?n WHERE deleted = 0', Constants::USERS_TABLE),
                'active' => (int) $db->getOne('SELECT COUNT(*) FROM ?n WHERE deleted = 0 AND active = 2', Constants::USERS_TABLE),
                'blocked' => (int) $db->getOne('SELECT COUNT(*) FROM ?n WHERE deleted = 0 AND active = 3', Constants::USERS_TABLE),
            ],
            'files' => [
                'total' => (int) $db->getOne('SELECT COUNT(*) FROM ?n', Constants::FILES_TABLE),
            ],
        ];
    }

    private function getAdminDashboardPlatformStats(): array {
        $db = SafeMySQL::gi();

        return [
            'data_model' => [
                'category_types' => (int) $db->getOne('SELECT COUNT(*) FROM ?n', Constants::CATEGORIES_TYPES_TABLE),
                'property_types' => (int) $db->getOne('SELECT COUNT(*) FROM ?n', Constants::PROPERTY_TYPES_TABLE),
                'property_sets' => (int) $db->getOne('SELECT COUNT(*) FROM ?n', Constants::PROPERTY_SETS_TABLE),
                'properties' => (int) $db->getOne('SELECT COUNT(*) FROM ?n', Constants::PROPERTIES_TABLE),
            ],
            'search_routing' => [
                'search_index' => (int) $db->getOne('SELECT COUNT(*) FROM ?n', Constants::SEARCH_INDEX_TABLE),
                'search_ngrams' => (int) $db->getOne('SELECT COUNT(*) FROM ?n', Constants::SEARCH_NGRAMS_TABLE),
                'url_policies' => (int) $db->getOne('SELECT COUNT(*) FROM ?n', Constants::URL_POLICIES_TABLE),
                'redirects' => (int) $db->getOne('SELECT COUNT(*) FROM ?n', Constants::REDIRECTS_TABLE),
            ],
            'auth_access' => [
                'session_tokens' => (int) $db->getOne(
                    'SELECT COUNT(*) FROM ?n WHERE revoked_at IS NULL AND expires_at > NOW()',
                    Constants::USERS_AUTH_SESSIONS_TABLE
                ),
                'users_with_valid_sessions' => (int) $db->getOne(
                    'SELECT COUNT(DISTINCT user_id) FROM ?n WHERE revoked_at IS NULL AND expires_at > NOW()',
                    Constants::USERS_AUTH_SESSIONS_TABLE
                ),
                'users_online_15m' => (int) $db->getOne(
                    'SELECT COUNT(DISTINCT user_id) FROM ?n WHERE revoked_at IS NULL AND expires_at > NOW() AND last_seen_at >= (NOW() - INTERVAL 15 MINUTE)',
                    Constants::USERS_AUTH_SESSIONS_TABLE
                ),
                'admins_online_15m' => (int) $db->getOne(
                    'SELECT COUNT(DISTINCT s.user_id)
                     FROM ?n AS s
                     INNER JOIN ?n AS u ON u.user_id = s.user_id
                     WHERE s.revoked_at IS NULL
                       AND s.expires_at > NOW()
                       AND s.last_seen_at >= (NOW() - INTERVAL 15 MINUTE)
                       AND u.deleted = 0
                       AND u.user_role = ?i',
                    Constants::USERS_AUTH_SESSIONS_TABLE,
                    Constants::USERS_TABLE,
                    Constants::ADMIN
                ),
                'managers_online_15m' => (int) $db->getOne(
                    'SELECT COUNT(DISTINCT s.user_id)
                     FROM ?n AS s
                     INNER JOIN ?n AS u ON u.user_id = s.user_id
                     WHERE s.revoked_at IS NULL
                       AND s.expires_at > NOW()
                       AND s.last_seen_at >= (NOW() - INTERVAL 15 MINUTE)
                       AND u.deleted = 0
                       AND u.user_role = ?i',
                    Constants::USERS_AUTH_SESSIONS_TABLE,
                    Constants::USERS_TABLE,
                    Constants::MANAGER
                ),
                'regular_users_online_15m' => (int) $db->getOne(
                    'SELECT COUNT(DISTINCT s.user_id)
                     FROM ?n AS s
                     INNER JOIN ?n AS u ON u.user_id = s.user_id
                     WHERE s.revoked_at IS NULL
                       AND s.expires_at > NOW()
                       AND s.last_seen_at >= (NOW() - INTERVAL 15 MINUTE)
                       AND u.deleted = 0
                       AND u.user_role = ?i',
                    Constants::USERS_AUTH_SESSIONS_TABLE,
                    Constants::USERS_TABLE,
                    Constants::USER
                ),
                'credentials' => (int) $db->getOne('SELECT COUNT(*) FROM ?n', Constants::USERS_AUTH_CREDENTIALS_TABLE),
                'identities' => (int) $db->getOne('SELECT COUNT(*) FROM ?n', Constants::USERS_AUTH_IDENTITIES_TABLE),
                'active_challenges' => (int) $db->getOne(
                    'SELECT COUNT(*) FROM ?n WHERE consumed_at IS NULL AND expires_at > NOW()',
                    Constants::USERS_AUTH_CHALLENGES_TABLE
                ),
            ],
        ];
    }

    private function getAdminDashboardOperations(): array {
        $cron = CronAgentService::getSummary();
        $backup = BackupService::getSummary();
        $mediaQueue = ImportMediaQueueService::getSummary();
        $storage = $this->getDashboardStorageSummary();
        $mail = $this->getDashboardMailSummary();
        $lastRunAt = (string) (SafeMySQL::gi()->getOne(
            'SELECT MAX(started_at) FROM ?n',
            Constants::CRON_AGENT_RUNS_TABLE
        ) ?? '');
        $minutesSinceLastRun = $this->minutesSince($lastRunAt);

        $alerts = [];
        if (!SysClass::checkDatabaseConnection()) {
            $alerts[] = $this->makeDashboardAlert('critical', $this->lang['sys.health_alert_db_down_title'] ?? 'База данных недоступна', $this->lang['sys.health_alert_db_down_message'] ?? 'Приложение не может подключиться к базе данных.', '/admin/health', $this->lang['sys.health'] ?? 'Состояние системы');
        }
        if (!empty($mail['transport_available']) === false) {
            $severity = !empty($mail['confirm_email_required']) ? 'critical' : 'warning';
            $alerts[] = $this->makeDashboardAlert($severity, $this->lang['sys.health_alert_mail_transport_title'] ?? 'Почтовый транспорт недоступен', strtr((string) ($this->lang['sys.health_alert_mail_transport_message'] ?? 'Режим {mode}, транспорт {transport}.'), [
                '{mode}' => (string) ($mail['mode'] ?? 'mail'),
                '{transport}' => (string) (($mail['sendmail_path'] ?? '') !== '' ? $mail['sendmail_path'] : ($mail['mode'] ?? 'mail')),
            ]), '/admin/health', $this->lang['sys.health'] ?? 'Состояние системы');
        }
        if ($storage['free_bytes'] > 0 && $storage['free_bytes'] <= 2147483648) {
            $alerts[] = $this->makeDashboardAlert('critical', $this->lang['sys.health_alert_disk_critical_title'] ?? 'Критически мало места на диске', strtr((string) ($this->lang['sys.health_alert_disk_critical_message'] ?? 'Свободно {free} из {total}.'), [
                '{free}' => (string) ($storage['free_pretty'] ?? '0 B'),
                '{total}' => (string) ($storage['total_pretty'] ?? '0 B'),
                '{used_percent}' => (string) ($storage['used_percent'] ?? '0'),
            ]), '/admin/backup', $this->lang['sys.backup'] ?? 'Резервное копирование');
        } elseif ($storage['free_bytes'] > 0 && $storage['free_bytes'] <= 5368709120) {
            $alerts[] = $this->makeDashboardAlert('warning', $this->lang['sys.health_alert_disk_warning_title'] ?? 'На диске мало места', strtr((string) ($this->lang['sys.health_alert_disk_warning_message'] ?? 'Свободно {free} из {total}.'), [
                '{free}' => (string) ($storage['free_pretty'] ?? '0 B'),
                '{total}' => (string) ($storage['total_pretty'] ?? '0 B'),
                '{used_percent}' => (string) ($storage['used_percent'] ?? '0'),
            ]), '/admin/backup', $this->lang['sys.backup'] ?? 'Резервное копирование');
        }
        if ($minutesSinceLastRun >= 10 || $lastRunAt === '') {
            $alerts[] = $this->makeDashboardAlert('critical', $this->lang['sys.health_alert_cron_stalled_title'] ?? 'Cron-агенты давно не запускались', strtr((string) ($this->lang['sys.health_alert_cron_stalled_message'] ?? 'Последний запуск был {minutes} минут назад.'), [
                '{minutes}' => $minutesSinceLastRun >= 0 ? (string) $minutesSinceLastRun : 'n/a',
            ]), '/admin/cron_agents', $this->lang['sys.cron_agents'] ?? 'Cron-агенты');
        } elseif ($minutesSinceLastRun >= 5) {
            $alerts[] = $this->makeDashboardAlert('warning', $this->lang['sys.health_alert_cron_delayed_title'] ?? 'Cron-агенты выполняются с задержкой', strtr((string) ($this->lang['sys.health_alert_cron_delayed_message'] ?? 'Последний запуск был {minutes} минут назад.'), [
                '{minutes}' => (string) $minutesSinceLastRun,
            ]), '/admin/cron_agents', $this->lang['sys.cron_agents'] ?? 'Cron-агенты');
        }
        if ((int) ($cron['failed'] ?? 0) > 0) {
            $alerts[] = $this->makeDashboardAlert('warning', $this->lang['sys.health_alert_cron_failed_title'] ?? 'Есть cron-агенты с ошибками', strtr((string) ($this->lang['sys.health_alert_cron_failed_message'] ?? 'Количество: {count}.'), [
                '{count}' => (string) ((int) ($cron['failed'] ?? 0)),
            ]), '/admin/cron_agents', $this->lang['sys.cron_agents'] ?? 'Cron-агенты');
        }
        if ((int) ($mediaQueue['failed'] ?? 0) > 0 || (int) ($mediaQueue['terminal_failed'] ?? 0) > 0) {
            $alerts[] = $this->makeDashboardAlert('warning', $this->lang['sys.health_alert_media_failed_title'] ?? 'Очередь медиа требует внимания', (($this->lang['sys.media_queue_failed'] ?? 'С ошибкой') . ': ' . (int) ($mediaQueue['failed'] ?? 0) . ', ' . ($this->lang['sys.media_queue_terminal_failed'] ?? 'Без повтора') . ': ' . (int) ($mediaQueue['terminal_failed'] ?? 0)), '/admin/cron_agents', $this->lang['sys.cron_agents'] ?? 'Cron-агенты');
        }
        if ((int) (($backup['jobs']['failed'] ?? 0)) > 0) {
            $alerts[] = $this->makeDashboardAlert('warning', $this->lang['sys.health_alert_backup_failed_title'] ?? 'Есть backup-задачи с ошибками', strtr((string) ($this->lang['sys.health_alert_backup_failed_message'] ?? 'Количество: {count}.'), [
                '{count}' => (string) ((int) (($backup['jobs']['failed'] ?? 0))),
            ]), '/admin/backup', $this->lang['sys.backup'] ?? 'Резервное копирование');
        }

        return [
            'database_connected' => SysClass::checkDatabaseConnection(),
            'storage' => $storage,
            'mail' => $mail,
            'cron' => array_merge($cron, [
                'last_run_at' => $lastRunAt,
                'minutes_since_last_run' => $minutesSinceLastRun,
            ]),
            'media_queue' => $mediaQueue,
            'backup' => $backup,
            'alerts' => $alerts,
            'alerts_summary' => $this->summarizeDashboardAlerts($alerts),
        ];
    }

    private function getAdminDashboardRecentImports(): array {
        return SafeMySQL::gi()->getAll(
            "SELECT id, importer_slug, settings_name, created_at, last_run_at
             FROM ?n
             ORDER BY COALESCE(last_run_at, created_at) DESC, id DESC
             LIMIT 10",
            Constants::IMPORT_SETTINGS_TABLE
        );
    }

    private function getDashboardStorageSummary(): array {
        $path = rtrim((string) ENV_SITE_PATH, '/\\');
        $freeBytes = @disk_free_space($path);
        $totalBytes = @disk_total_space($path);
        $freeBytes = $freeBytes !== false ? (int) $freeBytes : 0;
        $totalBytes = $totalBytes !== false ? (int) $totalBytes : 0;
        $usedPercent = $totalBytes > 0 ? round((1 - ($freeBytes / $totalBytes)) * 100, 1) : 0.0;

        return [
            'path' => $path,
            'free_bytes' => $freeBytes,
            'total_bytes' => $totalBytes,
            'used_percent' => $usedPercent,
            'free_pretty' => $this->formatBytes($freeBytes),
            'total_pretty' => $this->formatBytes($totalBytes),
        ];
    }

    private function getDashboardMailSummary(): array {
        $mode = !empty(ENV_SMTP) ? 'smtp' : 'mail';
        $sendmailPath = trim((string) ini_get('sendmail_path'));
        $sendmailBinary = '';
        if ($sendmailPath !== '') {
            $parts = preg_split('/\s+/', $sendmailPath);
            $sendmailBinary = trim((string) ($parts[0] ?? ''));
        }

        $smtpConfigured = !empty(ENV_SMTP)
            && trim((string) ENV_SMTP_SERVER) !== ''
            && (int) ENV_SMTP_PORT > 0
            && trim((string) ENV_SMTP_LOGIN) !== ''
            && trim((string) ENV_SMTP_PASSWORD) !== '';

        $transportAvailable = !empty(ENV_SMTP)
            ? $smtpConfigured
            : ($sendmailBinary !== '' && is_file($sendmailBinary) && is_executable($sendmailBinary));

        return [
            'mode' => $mode,
            'transport_available' => $transportAvailable,
            'sendmail_path' => $sendmailPath,
            'confirm_email_required' => (int) (defined('ENV_CONFIRM_EMAIL') ? ENV_CONFIRM_EMAIL : 0),
        ];
    }

    private function makeDashboardAlert(string $severity, string $title, string $message, string $url, string $label): array {
        return [
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'action_url' => $url,
            'action_label' => $label,
        ];
    }

    private function summarizeDashboardAlerts(array $alerts): array {
        $summary = [
            'total' => 0,
            'critical' => 0,
            'warning' => 0,
            'info' => 0,
        ];

        foreach ($alerts as $alert) {
            $severity = (string) ($alert['severity'] ?? 'info');
            if (!isset($summary[$severity])) {
                continue;
            }
            $summary[$severity]++;
            $summary['total']++;
        }

        return $summary;
    }

    private function minutesSince(string $dateTime): int {
        return ee_minutes_since_utc_datetime($dateTime);
    }

    private function formatBytes(int $bytes): string {
        $bytes = max(0, $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);
        $value = $bytes > 0 ? $bytes / (1024 ** $power) : 0;

        return number_format($value, $power === 0 ? 0 : 1, '.', ' ') . ' ' . $units[$power];
    }

    /**
     * Коммерческое предложение
     */
    public function upgrade($params = []) {
        if (!$this->requireAccess([Constants::ALL_AUTH], [
            'return' => 'admin/upgrade',
            'initiator' => __METHOD__,
        ])) {
            return;
        }
        /* view */
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_upgrade'));
        $this->html = $this->view->read('v_dashboard');

        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $pageTitle = (string) ($this->lang['sys.upgrade'] ?? 'Upgrade');
        $pageDescription = (string) ($this->lang['sys.upgrade_text'] ?? ENV_SITE_DESCRIPTION);
        $this->parameters_layout["title"] = ENV_SITE_NAME . ' - ' . $pageTitle;
        $this->parameters_layout["description"] = $pageDescription;
        $this->parameters_layout["canonical_href"] = ENV_URL_SITE . '/admin/upgrade';
        $this->parameters_layout["keywords"] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Загрузка стандартных представлений для каждой страницы
     */
    private function getStandardViews() {
        Hook::run('A_beforeGetStandardViews', $this->view);
        $this->view->set('top_bar', $this->view->read('v_top_bar'));
        $this->view->set('main_menu', $this->view->read('v_main_menu'));
        $this->view->set('page_footer', /* $this->view->read('v_footer') */ ''); // TODO        
        Hook::run('A_afterGetStandardViews', $this->view);
    }

    /**
     * Обработка AJAX запросов админ-панели
     * @param array $params - дополнительные параметры запрещены
     * @param POST $postData - POST параметры update или get
     */
    public function ajax_admin(array $params = []) {
        if (!$this->requireAccess([Constants::ALL_AUTH], [
            'ajax' => true,
            'initiator' => __METHOD__,
        ]) || count($params) > 0) {
            if (count($params) > 0) {
                echo json_encode(['error' => 'access denied'], JSON_UNESCAPED_UNICODE);
            }
            exit();
        }
        /* get data */
        $user_data = $this->users->data;
        /* Read POST data */
        $postData = SysClass::ee_cleanArray($_POST);
        switch (true) {
            case isset($postData['update']):
                foreach ($postData as $key => $value) {
                    if (array_key_exists($key, $user_data['options'])) {
                        $user_data['options'][$key] = $value;
                    }
                }
                $saved = $this->users->setUserOptions($this->logged_in, $user_data['options']);
                if (!$saved) {
                    echo json_encode(['error' => 'update failed'], JSON_UNESCAPED_UNICODE);
                    exit();
                }
                echo json_encode($this->buildAjaxAdminPayload($this->users->getUserOptions($this->logged_in)), JSON_UNESCAPED_UNICODE);
                exit();
            case isset($postData['get']):
                echo json_encode($this->buildAjaxAdminPayload($user_data['options']), JSON_UNESCAPED_UNICODE);
                exit();
            default:
                echo json_encode(['error' => 'no data'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function buildAjaxAdminPayload(array $options): array {
        $notifications = ClassNotifications::getNotificationsUser((int) $this->logged_in, 10);
        $messages = ClassMessages::get_unread_messages_user((int) $this->logged_in);
        $payload = array_merge($options, [
            'options' => $options,
            'notifications' => $notifications,
            'messages' => $messages,
            'count_unread_messages' => ClassMessages::get_count_unread_messages((int) $this->logged_in),
            'count_messages' => ClassMessages::get_count_messages((int) $this->logged_in),
            'success' => true,
        ]);

        return $payload;
    }

    /**
     * Карточка пользователя сайта
     * для изменения данных и внесения новых пользователей вручную
     * @param type $params
     */
    public function user_edit($params) {
        if (!$this->requireAccess([Constants::ADMIN, Constants::MODERATOR], [
            'return' => 'admin/user_edit',
            'initiator' => __METHOD__,
        ])) {
            return;
        }
        /* get current user data */
        $user_data = $this->users->data;
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $user_id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $user_id = 0;
            }
            $get_user_context = is_integer($user_id) ? $this->users->getUserData($user_id) : [];
            /* Нельзя посмотреть чужую карточку равной себе роли или выше */
            if (!$user_id || $this->users->data['user_role'] >= $get_user_context['user_role'] && $this->logged_in != $user_id) {
                SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
            }
        } else {                                                                            // Не передан ключевой параметр id
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        if (isset($get_user_context['new_user']) && $get_user_context['new_user']) {
            $get_user_context['user_id'] = 0;
            $get_user_context['name'] = '';
            $get_user_context['email'] = '';
            $get_user_context['phone'] = '';
            $get_user_context['user_role_text'] = '';
            $get_user_context['created_at'] = '';
            $get_user_context['updated_at'] = '';
            $get_user_context['last_activ'] = '';
            $get_user_context['auth_security'] = [
                'must_set_password' => 0,
                'failed_attempts' => 0,
                'locked_until' => null,
                'has_local_password' => false,
                'linked_identities' => [],
            ];
        }
        $this->loadModel('m_user_edit');
        /* Если не админ и не модератор и карточка не своя возвращаем */
        if ($this->users->data['user_role'] > 2 && $this->logged_in != $user_id) {
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/user_edit/id/' . $this->logged_in);
        }
        if (!empty($get_user_context['user_id'])) {
            $get_user_context['auth_security'] = (new AuthService())->getUserSecurityState((int) $get_user_context['user_id']);
        } elseif (empty($get_user_context['auth_security'])) {
            $get_user_context['auth_security'] = [
                'must_set_password' => 0,
                'failed_attempts' => 0,
                'locked_until' => null,
                'has_local_password' => false,
                'linked_identities' => [],
            ];
        }
        /* get data */
        $get_user_context['active_text'] = $this->lang[Constants::USERS_STATUS[$get_user_context['active']]];
        $free_active_status = Constants::USERS_STATUS;
        unset($free_active_status[$get_user_context['active']]);
        $get_free_roles = $this->models['m_user_edit']->get_free_roles($get_user_context['user_role'], (int) ($get_user_context['user_id'] ?? 0)); // Получим свободные роли
        $this->view->set('free_active_status', $free_active_status);
        $this->view->set('get_free_roles', $get_free_roles);
        $canManageOwnApiKey = (int) ($this->users->data['user_role'] ?? 0) === Constants::ADMIN
            && (int) ($get_user_context['user_id'] ?? 0) === (int) $this->logged_in
            && (int) ($get_user_context['user_role'] ?? 0) === Constants::ADMIN;
        $apiKeyMeta = null;
        if ($canManageOwnApiKey && !empty($get_user_context['user_id'])) {
            ApiKeyService::ensureInfrastructure();
            $apiKeyMeta = ApiKeyService::getActiveKeyMetaForUser((int) $get_user_context['user_id']);
        }

        /* view */
        $this->view->set('user_context', $get_user_context);
        $this->view->set('can_manage_own_api_key', $canManageOwnApiKey);
        $this->view->set('api_key_meta', $apiKeyMeta);
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_edit_user'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/js/plugins/JQ_mask.js" type="text/javascript" /></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_user.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = $this->lang['sys.user_edit'];
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Аякс изменение рег. данных пользователя
     * Редактирование возможно модераторами
     * или самим пользователем
     * @param $params - ID пользователя для изменения
     * @return json сообщение об ошибке или no
     */
    public function ajax_user_edit($params) {
        if (!$this->requireAccess([Constants::ALL_AUTH], [
            'ajax' => true,
            'initiator' => __METHOD__,
        ])) {
            exit();
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !CsrfService::isValidForCurrentRequest()) {
            echo json_encode(['error' => $this->lang['sys.security_action_expired'] ?? 'Проверка безопасности не пройдена. Обновите страницу и повторите попытку.']);
            exit();
        }
        $postData = SysClass::ee_cleanArray($_POST);
        $keyId = array_search('id', $params);
        if ($keyId !== false && isset($params[$keyId + 1])) {
            $user_id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
        } else {
            $user_id = 0;
        }
        if ($this->users->data['user_role'] > 2 && $this->logged_in != $user_id) { // Роль меньше модератора или id не текущего пользователя выходим
            echo json_encode(array('error' => 'error no access'));
            exit();
        }
        /* set data user */
        $postData['phone'] = isset($postData['phone']) ? preg_replace('/[^0-9+]/', '', $postData['phone']) : null;
        if ($this->users->data['phone'] && $this->users->data['user_role'] > 2) {
            unset($postData['phone']);
        }
        if (!empty($postData['email']) && !SysClass::validEmail($postData['email'])) {
            echo json_encode(array('error' => $this->lang['sys.invalid_mail_format']));
            exit();
        }
        $postData['subscribed'] = isset($postData['subscribed']) && $postData['subscribed'] ? 1 : 0;
        $postData['privacy_policy_accepted'] = isset($postData['privacy_policy_accepted']) && $postData['privacy_policy_accepted'] ? 1 : 0;
        $postData['personal_data_consent_accepted'] = isset($postData['personal_data_consent_accepted']) && $postData['personal_data_consent_accepted'] ? 1 : 0;

        if (isset($postData['new']) && $postData['new'] == 1) {
            if (!empty($postData['email']) && $this->users->getEmailExist(trim($postData['email']))) {
                echo json_encode(array('error' => $this->lang['sys.the_mail_is_already_busy']));
                exit();
            }
            if (isset($postData['user_role']) && (int) $postData['user_role'] === Constants::ADMIN && $this->users->hasAnotherActiveUserWithRole(Constants::ADMIN)) {
                echo json_encode(array('error' => $this->lang['sys.single_admin_only'] ?? 'В системе может быть только один администратор'));
                exit();
            }
            if ($new_id = $this->users->registrationNewUser($postData)) {
                echo json_encode(array('error' => 'no', 'id' => (int) $new_id, 'new' => 1));
                exit();
            } else {
                echo json_encode(array('error' => $this->lang['sys.db_registration_error']));
                exit();
            }
        }
        if (!empty($postData['email']) && $this->users->getEmailExist(trim($postData['email']), (int) $user_id)) {
            echo json_encode(array('error' => $this->lang['sys.the_mail_is_already_busy']));
            exit();
        }
        $currentUserRole = (int) $this->users->getUserRole($user_id);
        $requestedUserRole = isset($postData['user_role']) ? (int) $postData['user_role'] : $currentUserRole;
        if ($requestedUserRole === Constants::ADMIN && $this->users->hasAnotherActiveUserWithRole(Constants::ADMIN, (int) $user_id)) {
            echo json_encode(array('error' => $this->lang['sys.single_admin_only'] ?? 'В системе может быть только один администратор'));
            exit();
        }
        if ($currentUserRole === Constants::ADMIN && $requestedUserRole !== Constants::ADMIN && !$this->users->hasAnotherActiveUserWithRole(Constants::ADMIN, (int) $user_id)) {
            echo json_encode(array('error' => $this->lang['sys.single_admin_demotion_forbidden'] ?? 'Нельзя снять роль у единственного администратора'));
            exit();
        }
        if ($this->users->setUserData($user_id, $postData)) {
            $authService = new AuthService();
            $authReason = trim((string) ($postData['auth_password_setup_reason'] ?? 'admin_update'));
            $authService->markUserRequiresPasswordSetup(
                (int) $user_id,
                !empty($postData['auth_require_password_setup']),
                $authReason !== '' ? $authReason : 'admin_update'
            );
            $options = $this->users->getUserOptions((int) $user_id);
            $options['auth']['ip_restricted'] = !empty($postData['auth_ip_restricted']) ? 1 : 0;
            $this->users->setUserOptions((int) $user_id, $options);
            if (isset($postData['user_role']) && (int) $postData['user_role'] !== $currentUserRole) { // Сменилась роль пользователя, оповещаем админа и пишем лог
                \classes\system\Logger::audit('users_edit', 'Изменили роль пользователю', [
                    'id_user' => $user_id,
                    'old' => $currentUserRole,
                    'new' => $postData['user_role'],
                ], [
                    'initiator' => 'ajax_user_edit',
                    'details' => 'Изменили роль пользователю',
                    'include_trace' => false,
                ]);
            }
            echo json_encode(array('error' => 'no', 'id' => $user_id));
            exit();
        } else {
            echo json_encode(array('error' => 'error ajax_user_edit'));
            exit();
        }
    }

    public function user_api_key_generate($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'ajax' => true,
            'initiator' => __METHOD__,
        ])) {
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !CsrfService::isValidForCurrentRequest()) {
            echo json_encode(['error' => 'security_action_expired', 'message' => $this->lang['sys.security_action_expired'] ?? 'Security check failed. Refresh the page and try again.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }

        $targetUserId = 0;
        if (in_array('id', $params, true)) {
            $keyId = array_search('id', $params, true);
            if ($keyId !== false && isset($params[$keyId + 1]) && is_numeric($params[$keyId + 1])) {
                $targetUserId = (int) $params[$keyId + 1];
            }
        }
        if ($targetUserId <= 0) {
            $targetUserId = (int) $this->logged_in;
        }

        if ($targetUserId !== (int) $this->logged_in) {
            echo json_encode(['error' => 'access denied']);
            exit();
        }

        $currentUser = $this->users->getUserData($targetUserId);
        if ((int) ($currentUser['user_role'] ?? 0) !== Constants::ADMIN) {
            echo json_encode(['error' => 'API key is available only for administrator']);
            exit();
        }

        try {
            ApiKeyService::ensureInfrastructure();
            $generated = ApiKeyService::generateForUser($targetUserId, 'Administrator API key');
            $meta = ApiKeyService::getActiveKeyMetaForUser($targetUserId);

            echo json_encode([
                'error' => 'no',
                'api_key' => (string) ($generated['api_key'] ?? ''),
                'meta' => $meta,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        } catch (\Throwable $e) {
            echo json_encode([
                'error' => 'api_key_generate_failed',
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
    }

    public function user_api_key_revoke($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'ajax' => true,
            'initiator' => __METHOD__,
        ])) {
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !CsrfService::isValidForCurrentRequest()) {
            echo json_encode(['error' => 'security_action_expired', 'message' => $this->lang['sys.security_action_expired'] ?? 'Security check failed. Refresh the page and try again.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }

        $targetUserId = 0;
        if (in_array('id', $params, true)) {
            $keyId = array_search('id', $params, true);
            if ($keyId !== false && isset($params[$keyId + 1]) && is_numeric($params[$keyId + 1])) {
                $targetUserId = (int) $params[$keyId + 1];
            }
        }
        if ($targetUserId <= 0) {
            $targetUserId = (int) $this->logged_in;
        }

        if ($targetUserId !== (int) $this->logged_in) {
            echo json_encode(['error' => 'access denied']);
            exit();
        }

        ApiKeyService::ensureInfrastructure();
        ApiKeyService::revokeActiveKeysForUser($targetUserId);
        echo json_encode([
            'error' => 'no',
            'meta' => null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    /**
     * Выводит список пользователей
     * Доступ у администраторов, модераторов
     * @param arg - массив аргументов для поиска
     */
    public function users($params = array()) {
        if (!$this->requireAccess([Constants::ADMIN, Constants::MODERATOR], [
            'return' => 'admin/users',
            'initiator' => __METHOD__,
        ])) {
            return;
        }
        /* view */
        $this->getStandardViews();
        $this->view->set('users_table', $this->get_users_table());
        $this->view->set('body_view', $this->view->read('v_users'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/users.js" type="text/javascript" /></script>';
        $this->parameters_layout["title"] = $this->lang['sys.users'] ?? 'Users';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу пользователей
     */
    public function get_users_table() {
        if (!$this->requireAccess([Constants::ADMIN, Constants::MODERATOR], [
            'ajax' => !empty($_POST),
            'return' => 'admin/users',
            'initiator' => __METHOD__,
        ])) {
            return '';
        }
        $postData = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'user_id',
                    'title' => 'ID',
                    'sorted' => 'ASC',
                    'filterable' => false,
                    'width' => 10,
                    'align' => 'center'
                ], [
                    'field' => 'name',
                    'title' => $this->lang['sys.name'],
                    'sorted' => false,
                    'filterable' => true
                ], [
                    'field' => 'email',
                    'title' => $this->lang['sys.email'],
                    'sorted' => false,
                    'filterable' => true
                ], [
                    'field' => 'user_role',
                    'title' => $this->lang['sys.role'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'active',
                    'title' => $this->lang['sys.status'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'created_at',
                    'title' => $this->lang['sys.sign_up_text'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'last_activ',
                    'title' => $this->lang['sys.activity'],
                    'sorted' => 'ASC',
                    'filterable' => true
                ], [
                    'field' => 'actions',
                    'title' => $this->lang['sys.action'],
                    'sorted' => false,
                    'filterable' => false,
                    'width' => 10,
                    'align' => 'center'
                ],
            ]
        ];
        $filters = [
            'name' => [
                'type' => 'text',
                'id' => "name",
                'value' => '',
                'label' => $this->lang['sys.name'] ?? 'Name'
            ],
            'email' => [
                'type' => 'text',
                'id' => "email",
                'value' => '',
                'label' => $this->lang['sys.email'] ?? 'Email'
            ],
            'user_role' => [
                'type' => 'select',
                'id' => "user_role",
                'value' => [],
                'label' => $this->lang['sys.role'] ?? 'Role',
                'options' => [],
                'multiple' => false
            ],
            'active' => [
                'type' => 'select',
                'id' => "active",
                'value' => [],
                'label' => $this->lang['sys.status'] ?? 'Status',
                'options' => [],
                'multiple' => false
            ],
            'created_at' => [
                'type' => 'date',
                'id' => "created_at",
                'value' => '',
                'label' => $this->lang['sys.registration_date'] ?? 'Registration date'
            ],
            'last_activ' => [
                'type' => 'date',
                'id' => "last_activ",
                'value' => '',
                'label' => $this->lang['sys.last_active'] ?? 'Last active'
            ],
        ];
        $this->loadModel('m_user_edit');
        $get_free_roles = $this->models['m_user_edit']->get_free_roles(0, 0, false); // Получим все роли
        $filters['user_role']['options'][] = ['value' => '', 'label' => ''];
        foreach ($get_free_roles as $item) {
            $filters['user_role']['options'][] = ['value' => $item['role_id'], 'label' => $item['name']];
        }
        $filters['active']['options'][] = ['value' => '', 'label' => ''];
        foreach (Constants::USERS_STATUS as $k => $v) {
            $filters['active']['options'][] = ['value' => $k, 'label' => $this->lang[$v]];
        }

        $selected_sorting = [];
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $users_array = $this->users->getUsersData($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $users_array = $this->users->getUsersData(false, false, false, 25);
        }

        foreach ($users_array['data'] as $item) {
            $impersonateButton = '';
            if ((int) ($this->users->data['user_role'] ?? 0) === Constants::ADMIN && (int) ($item['user_id'] ?? 0) !== (int) $this->logged_in) {
                $impersonateButton = '<a href="' . htmlspecialchars($this->withCsrfUrl('/admin/login_as_user/id/' . $item['user_id']), ENT_QUOTES, 'UTF-8') . '" class="btn btn-outline-secondary me-2" '
                    . 'onclick="return confirm(\'' . ($this->lang['sys.login_as_user_confirm'] ?? 'Войти под этим пользователем?') . '\');" '
                    . 'data-bs-toggle="tooltip" data-bs-placement="top" title="' . ($this->lang['sys.login_as_user'] ?? 'Войти как пользователь') . '"><i class="fas fa-user-secret"></i></a>';
            }
            $isProtectedUser = in_array((int) ($item['user_role'] ?? 0), [Constants::ADMIN, Constants::SYSTEM], true);
            if (!$isProtectedUser) {
                $html_actions = $impersonateButton
                        . '<a href="/admin/user_edit/id/' . $item['user_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                        . '<a href="' . htmlspecialchars($this->withCsrfUrl('/admin/delete_user/id/' . $item['user_id']), ENT_QUOTES, 'UTF-8') . '"  onclick="return confirm(\'' . $this->lang['sys.delete'] . '?\');" '
                        . 'class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash-alt"></i></a>';
            } else {
                $html_actions = $impersonateButton
                        . '<a href="/admin/user_edit/id/' . $item['user_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>';
            }
            $data_table['rows'][] = [
                'user_id' => $item['user_id'],
                'name' => $item['name'],
                'email' => $item['email'],
                'user_role' => $item['user_role_text'],
                'active' => $this->lang[Constants::USERS_STATUS[$item['active']]],
                'created_at' => date('d.m.Y', strtotime($item['created_at'])),
                'last_activ' => $item['last_activ'] ? date('d.m.Y', strtotime($item['last_activ'])) : '',
                'actions' => $html_actions
            ];
        }
        $data_table['total_rows'] = $users_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('users_table', $data_table, 'get_users_table', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('users_table', $data_table, 'get_users_table', $filters);
        }
    }

    public function login_as_user($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/users',
            'initiator' => __METHOD__,
        ])) {
            return;
        }
        if (!$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin/users',
        ])) {
            return;
        }

        $userId = 0;
        if (in_array('id', $params, true)) {
            $keyId = array_search('id', $params, true);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $userId = (int) filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            }
        }

        if ($userId <= 0 || $userId === (int) $this->logged_in) {
            SysClass::handleRedirect(200, '/admin/users');
            return;
        }

        try {
            $state = AuthSessionService::startImpersonation($userId);
            if (($state['status'] ?? '') === 'started') {
                SysClass::handleRedirect(200, $this->getDefaultAuthorizedLandingUrl((int) ($state['target_user_role'] ?? 0)));
                return;
            }
        } catch (\Throwable $e) {
            ClassNotifications::addNotificationUser((int) $this->logged_in, [
                'text' => $this->lang['sys.login_as_user_failed'] ?? ('Не удалось войти под пользователем: ' . $e->getMessage()),
                'status' => 'danger',
                'url' => '/admin/users',
                'icon' => 'fa-solid fa-user-secret',
            ]);
        }

        SysClass::handleRedirect(200, '/admin/users');
    }

    public function stop_impersonation($params = []): void {
        if (!$this->requireAccess([Constants::ALL_AUTH], [
            'return' => 'admin',
            'initiator' => __METHOD__,
        ])) {
            return;
        }
        if (!$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin',
        ])) {
            return;
        }

        $state = AuthSessionService::getImpersonationState();
        $originUserId = (int) ($state['origin_user_id'] ?? 0);
        $restored = AuthSessionService::stopImpersonation();
        if ($restored && $originUserId > 0) {
            ClassNotifications::addNotificationUser($originUserId, [
                'text' => $this->lang['sys.impersonation_stopped'] ?? 'Вы вернулись в учётную запись администратора.',
                'status' => 'success',
                'url' => '/admin/users',
                'icon' => 'fa-solid fa-user-shield',
            ]);
        }

        SysClass::handleRedirect(200, '/admin');
    }

    /**
     * Выведет роли пользователей
     * @param type $params
     */
    public function users_roles($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* view */
        $this->getStandardViews();
        $this->view->set('users_roles_table', $this->get_users_roles_table());
        $this->view->set('body_view', $this->view->read('v_users_roles'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = $this->lang['sys.user_roles'] ?? 'User roles';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу ролей пользователей
     */
    public function get_users_roles_table() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $postData = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'role_id',
                    'title' => 'ID',
                    'sorted' => 'ASC',
                    'filterable' => false,
                    'width' => 10,
                    'align' => 'center'
                ], [
                    'field' => 'name',
                    'title' => $this->lang['sys.name'],
                    'sorted' => true,
                    'filterable' => true
                ], [
                    'field' => 'actions',
                    'title' => $this->lang['sys.action'],
                    'sorted' => false,
                    'filterable' => false,
                    'width' => 10,
                    'align' => 'center'
                ],
            ]
        ];
        $filters = [];
        $this->loadModel('m_user_edit');
        $selected_sorting = [];
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $users_array = $this->models['m_user_edit']->get_users_roles_data($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $users_array = $this->models['m_user_edit']->get_users_roles_data(false, false, false, 25);
        }

        foreach ($users_array['data'] as $item) {
            if (!in_array($item['role_id'], [1, 2, 3, 4, 8])) {
                $html_actions = '<a href="/admin/users_role_edit/id/' . $item['role_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>
				<a href="/admin/users_role_dell/id/' . $item['role_id'] . '" class="btn btn-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.delete'] . '"><i class="fas fa-trash-alt"></i></a>';
            } else {
                $html_actions = $item['role_id'] == 1 ? '' : '<a href="/admin/users_role_edit/id/' . $item['role_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>';
            }
            $data_table['rows'][] = [
                'role_id' => $item['role_id'],
                'name' => $item['name'],
                'actions' => $html_actions
            ];
        }
        $data_table['total_rows'] = $users_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('users_roles_table', $data_table, 'get_users_roles_table', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('users_roles_table', $data_table, 'get_users_roles_table', $filters);
        }
    }

    /**
     * Удалит роль пользователя кроме стандартных
     * @param array $params
     */
    public function users_role_dell($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        if (!$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin/users',
        ])) {
            return;
        }
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $id = 0;
            }
            if (in_array($id, [1, 2, 3, 4, 8])) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.system_roles_cannot_be_deleted'] ?? 'System roles cannot be deleted.', 'status' => 'danger']);
            } else {
                $this->loadModel('m_user_edit');
                $this->notifyOperationResult(
                    $this->models['m_user_edit']->users_role_dell($id),
                    [
                        'default_error_message' => $this->lang['sys.data_update_error'],
                        'success_message' => $this->lang['sys.removed'],
                        'failure_code' => 'user_role_delete_failed',
                    ]
                );
            }
        }
        SysClass::handleRedirect(200, '/admin/users_roles');
    }

    /**
     * Установит флаг удалённого пользователя
     * @param type $params
     */
    public function delete_user($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        if (!$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin/users',
        ])) {
            return;
        }
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $user_id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $user_id = 0;
            }
            $targetUserRole = (int) $this->users->getUserRole((int) $user_id);
            if (in_array($targetUserRole, [Constants::ADMIN, Constants::SYSTEM], true)) {
                ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.system_roles_cannot_be_deleted'] ?? 'System roles cannot be deleted.', 'status' => 'danger']);
            } else {
                $this->loadModel('m_user_edit');
                $this->notifyOperationResult(
                    $this->models['m_user_edit']->delete_user($user_id),
                    [
                        'default_error_message' => ($this->lang['sys.user_delete_error'] ?? 'User delete failed') . ' id=' . $user_id,
                        'success_message' => ($this->lang['sys.user_marked_deleted'] ?? 'User marked as deleted') . ' id=' . $user_id,
                        'success_status' => 'info',
                        'failure_code' => 'user_soft_delete_failed',
                    ]
                );
            }
        } else {
            ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.required_id_parameter_missing'] ?? 'Required parameter id is missing.', 'status' => 'danger']);
        }
        SysClass::handleRedirect(200, '/admin/users');
    }

    /**
     * Отправит сообщение администратору AJAX
     * @param array $params
     */
    public function send_message_admin($params = []) {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access) || count($params) > 0) {
            echo json_encode(array('error' => 'access denided'));
            exit();
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !CsrfService::isValidForCurrentRequest()) {
            echo json_encode([
                'error' => $this->lang['sys.security_action_expired']
                    ?? 'Проверка безопасности не пройдена. Обновите страницу и повторите попытку.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        ClassMessages::set_message_user(1, $this->logged_in, SysClass::ee_cleanString((string)($_POST['message'] ?? '')));
        echo json_encode(array('error' => 'no'));
        exit();
    }

    /**
     * Редактирование или добавление роли пользователя
     * @param array $params
     */
    public function users_role_edit($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $default_data = [
            'role_id' => 0,
            'name' => '',
        ];
        $this->loadModel('m_user_edit');
        $postData = SysClass::ee_cleanArray($_POST);
        if (!empty($postData) && !$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin/users_roles',
        ])) {
            return;
        }
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $id = 0;
            }
            if (isset($postData['name']) && $postData['name']) {
                $saveResult = $this->notifyOperationResult(
                    $this->models['m_user_edit']->update_users_role_data($postData),
                    [
                        'default_error_message' => $this->lang['sys.db_registration_error'],
                        'success_message' => empty($id)
                            ? ($this->lang['sys.role_created'] ?? 'Role created')
                            : ($this->lang['sys.role_updated'] ?? 'Role updated'),
                        'failure_code' => 'user_role_save_failed',
                    ]
                );
                if ($saveResult->isSuccess()) {
                    $id = $saveResult->getId(['role_id', 'id']);
                }
            }
            $get_users_role_data = (int) $id ? $this->models['m_user_edit']->get_users_role_data($id) : $default_data;
            $get_users_role_data = $get_users_role_data ? $get_users_role_data : $default_data;
        } else {
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/users_role_edit/id/');
        }
        /* view */
        $this->view->set('users_role_data', $get_users_role_data);
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read('v_edit_users_role'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = $this->lang['sys.user_role_single'] ?? 'User role';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_users_role.js" type="text/javascript" /></script>';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Удалённые пользователи
     * @param array $params
     */
    public function deleted_users($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        /* view */
        $this->getStandardViews();
        $this->view->set('deleted_users_table', $this->get_deleted_users_table());
        $this->view->set('body_view', $this->view->read('v_deleted_users'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = $this->lang['sys.deleted_users'] ?? 'Deleted users';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу удалённых пользователей
     */
    public function get_deleted_users_table() {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $postData = SysClass::ee_cleanArray($_POST);
        $data_table = [
            'columns' => [
                [
                    'field' => 'user_id',
                    'title' => 'ID',
                    'sorted' => 'ASC',
                    'filterable' => false,
                    'width' => 10,
                    'align' => 'center'
                ], [
                    'field' => 'name',
                    'title' => $this->lang['sys.name'],
                    'sorted' => true,
                    'filterable' => true,
                    'width' => 30,
                ], [
                    'field' => 'email',
                    'title' => $this->lang['sys.email'],
                    'sorted' => false,
                    'filterable' => true,
                    'width' => 20,
                    'align' => 'center'
                ], [
                    'field' => 'user_role_text',
                    'title' => $this->lang['sys.role'],
                    'sorted' => true,
                    'filterable' => false,
                    'width' => 20,
                    'align' => 'center'
                ], [
                    'field' => 'last_ip',
                    'title' => $this->lang['sys.last_ip'],
                    'sorted' => false,
                    'filterable' => false,
                    'width' => 10,
                    'align' => 'center'
                ], [
                    'field' => 'actions',
                    'title' => $this->lang['sys.action'],
                    'sorted' => false,
                    'filterable' => false,
                    'width' => 10,
                    'align' => 'center'
                ],
            ]
        ];
        $filters = [];
        $this->loadModel('m_user_edit');
        $selected_sorting = [];
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $users_array = $this->users->getUsersData($params['order'], $params['where'], $params['start'], $params['limit'], true);
        } else {
            $users_array = $this->users->getUsersData(false, false, false, 25, true);
        }
        foreach ($users_array['data'] as $item) {
                $data_table['rows'][] = [
                    'user_id' => $item['user_id'],
                    'name' => $item['name'],
                    'email' => $item['email'],
                    'user_role_text' => $item['user_role_text'],
                    'last_ip' => $item['last_ip'],
                    'actions' => '<a href="/admin/deleted_user_edit/id/' . $item['user_id'] . '" class="btn btn-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.edit'] . '"><i class="fas fa-edit"></i></a>'
                    . '<a href="' . htmlspecialchars($this->withCsrfUrl('/admin/restore_user/id/' . $item['user_id']), ENT_QUOTES, 'UTF-8') . '" onclick="return confirm(\'' . $this->lang['sys.restore_user_confirm'] . '\');" class="btn btn-success" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $this->lang['sys.restore_user'] . '"><i class="fas fa-rotate-left"></i></a>',
                ];
        }
        $data_table['total_rows'] = $users_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('deleted_users_table', $data_table, 'get_deleted_users_table', $filters, $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('deleted_users_table', $data_table, 'get_deleted_users_table', $filters);
        }
    }

    /**
     * Обрабатывает страницу редактирования удаленного пользователя
     * Эта функция обрабатывает параметры запроса для получения данных удаленного пользователя и отображает страницу для его редактирования
     * Доступ к этой функции имеют только пользователи с определенными правами (1 и 2)
     * Если доступ запрещен или пользователь не найден, происходит перенаправление на главную страницу
     * @param array $params Массив параметров из URL, например, ID пользователя
     * Если ID пользователя не указан или не валиден, используется значение по умолчанию (false)
     * @return void
     */
    public function deleted_user_edit($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        $default_data = false;
        $this->loadModel('m_user_edit');
        if (in_array('id', $params)) {
            $keyId = array_search('id', $params);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $user_id = filter_var($params[$keyId + 1], FILTER_VALIDATE_INT);
            } else {
                $user_id = 0;
            }
            $get_deleted_user_data = (int) $user_id ? $this->users->getUserData($user_id) : $default_data;
            if (!$get_deleted_user_data) {
                SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/deleted_users');
            }
        } else {
            SysClass::handleRedirect(200, ENV_URL_SITE . '/admin/deleted_users');
        }
        $deletedUserContext = Hook::filter('admin.deleted_user_context', [
            'can_purge' => ((int) ($this->users->data['user_role'] ?? 0) === Constants::ADMIN)
                && ((int) ($get_deleted_user_data['deleted'] ?? 0) === 1),
            'purge_warning' => '',
            'extra_info_rows' => [],
            'extra_sidebar_html' => '',
        ], $get_deleted_user_data, $this);
        if (!is_array($deletedUserContext)) {
            $deletedUserContext = [
                'can_purge' => ((int) ($this->users->data['user_role'] ?? 0) === Constants::ADMIN)
                    && ((int) ($get_deleted_user_data['deleted'] ?? 0) === 1),
                'purge_warning' => '',
                'extra_info_rows' => [],
                'extra_sidebar_html' => '',
            ];
        }
        /* view */
        $this->getStandardViews();
        $this->view->set('deleted_user_data', $get_deleted_user_data);
        $this->view->set('deleted_user_can_purge', !empty($deletedUserContext['can_purge']));
        $this->view->set('deleted_user_purge_warning', (string) ($deletedUserContext['purge_warning'] ?? ''));
        $this->view->set('deleted_user_extra_info_rows', (array) ($deletedUserContext['extra_info_rows'] ?? []));
        $this->view->set('deleted_user_extra_sidebar_html', (string) ($deletedUserContext['extra_sidebar_html'] ?? ''));
        $this->view->set('deleted_user_purge_url', \classes\system\CsrfService::appendToUrl('/admin/purge_deleted_user/id/' . (int) ($get_deleted_user_data['user_id'] ?? 0)));
        $this->view->set('body_view', $this->view->read('v_edit_deleted_users'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = $this->lang['sys.deleted_user'] ?? 'Deleted user';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/edit_deleted_user.js" type="text/javascript" /></script>';
        $this->showLayout($this->parameters_layout);
    }

    public function purge_deleted_user($params = []) {
        $this->access = [Constants::ADMIN];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        if (!$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin/deleted_users',
        ])) {
            return;
        }

        if (!in_array('id', $params, true)) {
            ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.error'], 'status' => 'danger']);
            SysClass::handleRedirect(200, '/admin/deleted_users');
            return;
        }

        $keyId = array_search('id', $params, true);
        $user_id = ($keyId !== false && isset($params[$keyId + 1])) ? (int) filter_var($params[$keyId + 1], FILTER_VALIDATE_INT) : 0;
        if ($user_id <= 0) {
            ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.error'], 'status' => 'danger']);
            SysClass::handleRedirect(200, '/admin/deleted_users');
            return;
        }

        $this->loadModel('m_user_edit');
        $userData = $this->users->getUserData($user_id);
        $beforePurgeResult = Hook::until('admin.before_purge_deleted_user', null, is_array($userData) ? $userData : ['user_id' => $user_id], $this);
        if ($beforePurgeResult instanceof OperationResult && $beforePurgeResult->isFailure()) {
            $this->notifyOperationResult(
                $beforePurgeResult,
                [
                    'default_error_message' => 'Не удалось удалить пользователя из архива.',
                    'failure_code' => 'user_purge_blocked',
                ]
            );
            SysClass::handleRedirect(200, '/admin/deleted_user_edit/id/' . $user_id);
            return;
        }
        $result = $this->models['m_user_edit']->purge_deleted_user($user_id);
        if ($result->isSuccess()) {
            Hook::run('admin.after_purge_deleted_user', $user_id, is_array($userData) ? $userData : ['user_id' => $user_id], $this);
        }
        $this->notifyOperationResult(
            $result,
            [
                'default_error_message' => 'Не удалось удалить пользователя из архива.',
                'success_message' => 'Пользователь полностью удалён из архива.',
                'failure_code' => 'user_purge_failed',
            ]
        );

        SysClass::handleRedirect(200, $result->isSuccess() ? '/admin/deleted_users' : '/admin/deleted_user_edit/id/' . $user_id);
    }

    public function restore_user($params = []) {
        $this->access = [Constants::ADMIN, Constants::MODERATOR];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect();
            exit();
        }
        if (!$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin/deleted_users',
        ])) {
            return;
        }

        if (!in_array('id', $params, true)) {
            ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.error'], 'status' => 'danger']);
            SysClass::handleRedirect(200, '/admin/deleted_users');
            return;
        }

        $keyId = array_search('id', $params, true);
        $user_id = ($keyId !== false && isset($params[$keyId + 1])) ? (int) filter_var($params[$keyId + 1], FILTER_VALIDATE_INT) : 0;
        if ($user_id <= 0) {
            ClassNotifications::addNotificationUser($this->logged_in, ['text' => $this->lang['sys.error'], 'status' => 'danger']);
            SysClass::handleRedirect(200, '/admin/deleted_users');
            return;
        }

        $this->loadModel('m_user_edit');
        $this->notifyOperationResult(
            $this->models['m_user_edit']->restore_user($user_id, true),
            [
                'default_error_message' => $this->lang['sys.data_update_error'],
                'success_message' => $this->lang['sys.user_restored'],
                'failure_code' => 'user_restore_failed',
            ]
        );
        SysClass::handleRedirect(200, '/admin/deleted_users');
    }

    /**
     * Добавит необходимые стили и скрипты для подключения редактора
     */
    private function addEditorToLayout() {
        $this->parameters_layout["add_style"] .= '<link rel="preload" href="' . ENV_URL_SITE . '/assets/editor/summernote/font/summernote.woff2" as="font" type="font/woff2" crossorigin>';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . ENV_URL_SITE . '/assets/editor/summernote/summernote-bs5.min.css">';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . ENV_URL_SITE . '/assets/editor/summernote/summernote-font-display.css">';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . ENV_URL_SITE . '/assets/js/plugins/cropper.min.css">';
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/editor/summernote/summernote-bs5.min.js" type="text/javascript"></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/js/plugins/cropper.min.js" type="text/javascript"></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/editor/summernote/plugin/text/text_manipulation.js" type="text/javascript"></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/editor/summernote/plugin/cropper/summernote-cropper.js" type="text/javascript" type="text/javascript"></script>';
        $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/editor/summernote/plugin/cropper/summernote-ext-image.js" type="text/javascript" type="text/javascript"></script>';
        $editorLangCode = strtoupper((string)(\classes\system\Session::get('lang') ?: ($this->users->data['options']['localize'] ?? ENV_DEF_LANG)));
        if ($editorLangCode == 'RU') {
            $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/editor/summernote/lang/summernote-ru-RU.min.js" type="text/javascript"></script>';
        } else {
            $this->parameters_layout["add_script"] .= '<script src="' . ENV_URL_SITE . '/assets/editor/summernote/lang/summernote-en-US.min.js" type="text/javascript"></script>';
        }
    }

    /**
     * Подключает стили и скрипты CodeMirror (v5.65.10)
     */
    private function addCodeMirror() {
        // Подключение стилей CodeMirror
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/codemirror.css">';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/theme/monokai.css">';
        // Подключение скриптов CodeMirror
        $this->parameters_layout["add_script"] .= '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/codemirror.js"></script>';
        $this->parameters_layout["add_script"] .= '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/mode/xml/xml.js"></script>';
        $this->parameters_layout["add_script"] .= '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/mode/javascript/javascript.js"></script>';
        $this->parameters_layout["add_script"] .= '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/mode/css/css.js"></script>';
        $this->parameters_layout["add_script"] .= '<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.10/mode/htmlmixed/htmlmixed.js"></script>';
    }

    /**
     * Поиск по админ-панели
     */
    public function search() {
        $this->access = [Constants::ALL_AUTH];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            // Вместо редиректа на 200 лучше использовать стандартный 302
            SysClass::return_to('/admin/login_form');
        }
        // Получаем и очищаем поисковый запрос
        $query = isset($_GET['q']) ? trim($_GET['q']) : null;
        $searchResults = [];
        $totalResults = 0;
        if (!empty($query)) {
            // 1. Правильное создание экземпляра класса
            $searchEngine = new \classes\helpers\ClassSearchEngine();
            // 2. Вызов метода search и получение структурированного результата
            $searchData = $searchEngine->search($query);
            // 3. Извлечение результатов и общего количества
            $searchResults = $searchData['results'];
            $totalResults = $searchData['total'];
        }
        /* Подготовка данных для view */
        $this->getStandardViews();
        $this->view->set('query', $query);
        $this->view->set('searchResults', $searchResults);
        $this->view->set('totalResults', $totalResults); // Передаем и общее количество
        $this->view->set('body_view', $this->view->read('v_search_results'));
        $this->html = $this->view->read('v_dashboard');
        /* Формирование layout */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        // Условие для заголовка
        $this->parameters_layout["title"] = $query
            ? ($this->lang['sys.search_results'] ?? 'Search results') . ': ' . htmlspecialchars($query)
            : ($this->lang['sys.search_placeholder'] ?? 'Search');
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Обрабатывает POST-данные и выполняет редирект на чистую страницу, чтобы избежать повторной отправки данных при обновлении страницы (F5)
     * Если POST-данные не пусты, функция выполняет редирект:
     * - Если $newEntity равно false, редирект происходит на текущий URI
     * - Если $newEntity равно true, редирект происходит на путь контроллера с добавлением действия и идентификатора сущности
     * @param array $postData Массив POST-данных, которые необходимо обработать
     * @param bool $newEntity Флаг, указывающий, создается ли новая сущность (true) или редактируется существующая (false)
     * @param int $entityId Идентификатор сущности, который будет добавлен в URL при редиректе (если $newEntity равно true)
     * @return void
     */
    public function processPostParams(array $postData, bool $newEntity, int $entityId): void {
        if (!empty($postData)) {
            !$newEntity ? SysClass::handleRedirect(200, __REQUEST['_SERVER']['REQUEST_URI']) :
                            SysClass::handleRedirect(200, $this->getPathController(true) . '/' . ENV_CONTROLLER_ACTION . '/id/' . $entityId);
        }
    }
}
