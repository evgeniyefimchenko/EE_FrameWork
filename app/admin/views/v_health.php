<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$report = is_array($health_report ?? null) ? $health_report : [];
$install = is_array($report['install'] ?? null) ? $report['install'] : [];
$paths = is_array($report['paths'] ?? null) ? $report['paths'] : [];
$cache = is_array($report['cache'] ?? null) ? $report['cache'] : [];
$mail = is_array($report['mail'] ?? null) ? $report['mail'] : [];
$cron = is_array($report['cron'] ?? null) ? $report['cron'] : [];
$lifecycle = is_array($report['lifecycle'] ?? null) ? $report['lifecycle'] : [];
$media = is_array($report['media'] ?? null) ? $report['media'] : [];
$mediaQueue = is_array($report['media_queue'] ?? null) ? $report['media_queue'] : [];
$search = is_array($report['search'] ?? null) ? $report['search'] : [];
$backups = is_array($report['backups'] ?? null) ? $report['backups'] : [];
$storage = is_array($report['storage'] ?? null) ? $report['storage'] : [];
$alerts = is_array($report['alerts'] ?? null) ? $report['alerts'] : [];
$alertsSummary = is_array($report['alerts_summary'] ?? null) ? $report['alerts_summary'] : ['total' => 0, 'critical' => 0, 'warning' => 0, 'info' => 0];
$pageHeading = (string) ($system_page_heading ?? ($lang['sys.health'] ?? 'Состояние системы'));
$renderHealthAlertText = static function (array $alert, string $kind, array $lang): string {
    $key = (string) ($alert[$kind . '_key'] ?? '');
    $text = $key !== '' && isset($lang[$key]) ? (string) $lang[$key] : (string) ($alert[$kind] ?? '');
    $params = is_array($alert[$kind . '_params'] ?? null) ? $alert[$kind . '_params'] : [];
    foreach ($params as $paramKey => $paramValue) {
        $text = str_replace('{' . $paramKey . '}', (string) $paramValue, $text);
    }
    return $text;
};
?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3 flex-wrap gap-2">
            <h1 class="mb-0"><?= htmlspecialchars($pageHeading) ?></h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="/admin/recover_stale_operations" class="btn btn-outline-warning">
                    <i class="fa-solid fa-arrows-rotate"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.recover_stale_operations'] ?? 'Восстановить зависшие процессы')) ?>
                </a>
                <a href="/admin/refresh_media_metadata" class="btn btn-outline-primary">
                    <i class="fa-solid fa-photo-film"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.refresh_media_metadata'] ?? 'Обновить метаданные файлов')) ?>
                </a>
                <a href="/admin/backup" class="btn btn-primary">
                    <i class="fa-solid fa-box-archive"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.backup'] ?? 'Резервное копирование')) ?>
                </a>
            </div>
        </div>

        <div id="alerts" class="mb-4">
            <?php if (!empty($alerts)): ?>
                <div class="card shadow-sm border">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <strong><?= htmlspecialchars((string)($lang['sys.health_alerts'] ?? 'Оповещения системы')) ?></strong>
                        <div class="small text-muted">
                            <?= htmlspecialchars((string)($lang['sys.health_alerts_summary'] ?? 'Критично:')) ?> <strong><?= (int) ($alertsSummary['critical'] ?? 0) ?></strong>,
                            <?= htmlspecialchars((string)($lang['sys.health_alerts_warning'] ?? 'Предупреждения:')) ?> <strong><?= (int) ($alertsSummary['warning'] ?? 0) ?></strong>,
                            <?= htmlspecialchars((string)($lang['sys.health_alerts_info'] ?? 'Инфо:')) ?> <strong><?= (int) ($alertsSummary['info'] ?? 0) ?></strong>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($alerts as $alert): ?>
                                <?php
                                $severity = (string) ($alert['severity'] ?? 'info');
                                $alertClass = match ($severity) {
                                    'critical' => 'danger',
                                    'warning' => 'warning',
                                    default => 'info',
                                };
                                $title = $renderHealthAlertText($alert, 'title', $lang);
                                $message = $renderHealthAlertText($alert, 'message', $lang);
                                $actionLabel = '';
                                if (!empty($alert['action_label_key']) && isset($lang[$alert['action_label_key']])) {
                                    $actionLabel = (string) $lang[$alert['action_label_key']];
                                }
                                ?>
                                <div class="alert alert-<?= htmlspecialchars($alertClass) ?> mb-0">
                                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 align-items-lg-center">
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($title) ?></div>
                                            <div class="small"><?= htmlspecialchars($message) ?></div>
                                        </div>
                                        <?php if (!empty($alert['action_url']) && $actionLabel !== ''): ?>
                                            <div>
                                                <a href="<?= htmlspecialchars((string) $alert['action_url']) ?>" class="btn btn-sm btn-outline-<?= htmlspecialchars($alertClass) ?>">
                                                    <?= htmlspecialchars($actionLabel) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-success mb-0">
                    <strong><?= htmlspecialchars((string)($lang['sys.health_no_alerts'] ?? 'Критичных и предупреждающих событий не найдено.')) ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.install'] ?? 'Установка')) ?></div>
                        <div class="h5 mb-1"><?= !empty($install['database_connected']) ? ($lang['sys.active'] ?? 'Активно') : ($lang['sys.error'] ?? 'Ошибка') ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.core_tables'] ?? 'Ключевые таблицы')) ?>: <strong><?= !empty($install['core_tables_ok']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></strong></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.auth_infrastructure'] ?? 'Auth-инфраструктура')) ?>: <strong><?= !empty($install['auth_tables_ok']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></strong></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.cron_infrastructure'] ?? 'Cron-инфраструктура')) ?>: <strong><?= !empty($install['cron_tables_ok']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></strong></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.cache'] ?? 'Кэш')) ?></div>
                        <div class="h5 mb-1"><?= htmlspecialchars((string)($cache['backend'] ?? 'file')) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.route_cache'] ?? 'Route-кэш')) ?>: <strong><?= !empty($cache['route_enabled']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></strong></div>
                        <div class="small text-muted">Redis probe: <strong><?= !empty($cache['redis_probe_exists']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></strong></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.email'] ?? 'Почта')) ?></div>
                        <div class="h5 mb-1"><?= htmlspecialchars((string)($mail['mode'] ?? 'mail')) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.transport'] ?? 'Транспорт')) ?>: <strong><?= !empty($mail['transport_available']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></strong></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.confirm_email_required'] ?? 'Подтверждение почты')) ?>: <strong><?= !empty($mail['confirm_email_required']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></strong></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.cron_agents'] ?? 'Cron-агенты')) ?></div>
                        <div class="h5 mb-1"><?= (int) ($cron['due'] ?? 0) ?> / <?= (int) ($cron['locked'] ?? 0) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.cron_agent_status_due'] ?? 'Готовы к запуску')) ?> / <?= htmlspecialchars((string)($lang['sys.running'] ?? 'В работе')) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.failed'] ?? 'С ошибкой')) ?>: <strong><?= (int) ($cron['failed'] ?? 0) ?></strong></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.storage'] ?? 'Диск')) ?></div>
                        <div class="h5 mb-1"><?= htmlspecialchars((string)($storage['free_pretty'] ?? '0 B')) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.disk_free'] ?? 'Свободно')) ?> / <?= htmlspecialchars((string)($lang['sys.disk_total'] ?? 'Всего')) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.disk_total'] ?? 'Всего')) ?>: <strong><?= htmlspecialchars((string)($storage['total_pretty'] ?? '0 B')) ?></strong></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.disk_used_percent'] ?? 'Использовано')) ?>: <strong><?= htmlspecialchars((string)($storage['used_percent'] ?? '0')) ?>%</strong></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.property_lifecycle_jobs'] ?? 'Задачи жизненного цикла')) ?></div>
                        <div class="h5 mb-1"><?= (int) ($lifecycle['queued'] ?? 0) ?> / <?= (int) ($lifecycle['running'] ?? 0) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.queued'] ?? 'В очереди')) ?> / <?= htmlspecialchars((string)($lang['sys.running'] ?? 'В работе')) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.stale_jobs'] ?? 'Зависшие')) ?>: <strong><?= (int) ($lifecycle['stale_running'] ?? 0) ?></strong></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.media_diagnostics'] ?? 'Диагностика медиа')) ?></div>
                        <div class="h5 mb-1"><?= (int) ($media['total_files'] ?? 0) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.unreferenced_files'] ?? 'Неиспользуемые файлы')) ?>: <strong><?= (int) ($media['unreferenced_files'] ?? 0) ?></strong></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.missing_on_disk'] ?? 'Отсутствуют на диске')) ?>: <strong><?= (int) ($media['missing_on_disk'] ?? 0) ?></strong></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.media_queue'] ?? 'Очередь медиа')) ?></div>
                        <div class="h5 mb-1"><?= (int) ($mediaQueue['pending'] ?? 0) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.media_queue_pending'] ?? 'Ожидают')) ?> / <?= htmlspecialchars((string)($lang['sys.media_queue_done'] ?? 'Готово')) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.media_queue_done'] ?? 'Готово')) ?>: <strong><?= (int) ($mediaQueue['done'] ?? 0) ?></strong></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.media_queue_failed'] ?? 'С ошибкой')) ?>: <strong><?= (int) ($mediaQueue['failed'] ?? 0) ?></strong></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.media_queue_terminal_failed'] ?? 'Без повтора')) ?>: <strong><?= (int) ($mediaQueue['terminal_failed'] ?? 0) ?></strong></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.backup'] ?? 'Резервное копирование')) ?></div>
                        <div class="h5 mb-1"><?= (int) (($backups['jobs']['queued'] ?? 0) + ($backups['jobs']['running'] ?? 0)) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.backup_jobs_queued'] ?? 'В очереди')) ?> / <?= htmlspecialchars((string)($lang['sys.backup_jobs_running'] ?? 'В работе')) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.backup_jobs_failed'] ?? 'С ошибкой')) ?>: <strong><?= (int) (($backups['jobs']['failed'] ?? 0)) ?></strong></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.stale_jobs'] ?? 'Зависшие')) ?>: <strong><?= (int) ($backups['stale_running'] ?? 0) ?></strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-6" id="media">
                <div class="card shadow-sm border h-100">
                    <div class="card-header"><strong><?= htmlspecialchars((string)($lang['sys.install'] ?? 'Установка')) ?></strong></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <tbody>
                                    <tr>
                                        <th><?= htmlspecialchars((string)($lang['sys.database'] ?? 'База данных')) ?></th>
                                        <td><?= !empty($install['database_connected']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></td>
                                    </tr>
                                    <tr>
                                        <th><?= htmlspecialchars((string)($lang['sys.email'] ?? 'Почта')) ?></th>
                                        <td>
                                            <?= htmlspecialchars((string)($mail['mode'] ?? 'mail')) ?>
                                            /
                                            <?= !empty($mail['transport_available']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?>
                                            <?php if (!empty($mail['sendmail_path'])): ?>
                                                <div class="small text-muted"><?= htmlspecialchars((string) $mail['sendmail_path']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><?= htmlspecialchars((string)($lang['sys.core_tables'] ?? 'Ключевые таблицы')) ?></th>
                                        <td><?= !empty($install['core_tables_ok']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></td>
                                    </tr>
                                    <tr>
                                        <th><?= htmlspecialchars((string)($lang['sys.auth_infrastructure'] ?? 'Auth-инфраструктура')) ?></th>
                                        <td><?= !empty($install['auth_tables_ok']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></td>
                                    </tr>
                                    <tr>
                                        <th><?= htmlspecialchars((string)($lang['sys.cron_infrastructure'] ?? 'Cron-инфраструктура')) ?></th>
                                        <td><?= !empty($install['cron_tables_ok']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border h-100">
                    <div class="card-header"><strong><?= htmlspecialchars((string)($lang['sys.paths'] ?? 'Пути')) ?></strong></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th><?= htmlspecialchars((string)($lang['sys.type'] ?? 'Тип')) ?></th>
                                        <th><?= htmlspecialchars((string)($lang['sys.path'] ?? 'Путь')) ?></th>
                                        <th><?= htmlspecialchars((string)($lang['sys.exists'] ?? 'Существует')) ?></th>
                                        <th><?= htmlspecialchars((string)($lang['sys.writable'] ?? 'Доступно для записи')) ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paths as $pathKey => $pathItem): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) $pathKey) ?></td>
                                            <td class="small"><?= htmlspecialchars((string) ($pathItem['path'] ?? '')) ?></td>
                                            <td><?= !empty($pathItem['exists']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></td>
                                            <td><?= !empty($pathItem['writable']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong><?= htmlspecialchars((string)($lang['sys.cron_agents'] ?? 'Cron-агенты')) ?></strong>
                        <a href="/admin/cron_agents" class="btn btn-sm btn-outline-secondary"><?= htmlspecialchars((string)($lang['sys.list'] ?? 'Список')) ?></a>
                    </div>
                    <div class="card-body">
                        <div class="small text-muted mb-3"><?= htmlspecialchars((string)($lang['sys.cron_agent_scheduler_help'] ?? 'Настройте системный cron на запуск этого скрипта каждую минуту:')) ?> <code><?= htmlspecialchars((string) ($cron['scheduler_command'] ?? ('php ' . ENV_SITE_PATH . 'app/cron/run.php'))) ?></code></div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <tbody>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.total'] ?? 'Всего')) ?></th><td><?= (int) ($cron['total'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.active'] ?? 'Активно')) ?></th><td><?= (int) ($cron['active'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.cron_agent_status_due'] ?? 'Готовы к запуску')) ?></th><td><?= (int) ($cron['due'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.running'] ?? 'В работе')) ?></th><td><?= (int) ($cron['locked'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.failed'] ?? 'С ошибкой')) ?></th><td><?= (int) ($cron['failed'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.last_update'] ?? 'Последнее обновление')) ?></th><td><?= htmlspecialchars(!empty($cron['last_run_at']) ? ee_format_utc_datetime((string) $cron['last_run_at'], 'd.m.Y H:i:s') : '-') ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.cron_agent_max_per_tick'] ?? 'Лимит задач за тик')) ?></th><td><?= (int) ($cron['config']['max_agents_per_tick'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.cron_agent_max_weight'] ?? 'Лимит нагрузки')) ?></th><td><?= (int) ($cron['config']['max_weight_per_tick'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.cron_agent_max_concurrent'] ?? 'Лимит одновременных задач')) ?></th><td><?= (int) ($cron['config']['max_concurrent'] ?? 0) ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong><?= htmlspecialchars((string)($lang['sys.property_lifecycle_jobs'] ?? 'Задачи жизненного цикла')) ?></strong>
                        <a href="/admin/property_lifecycle_jobs" class="btn btn-sm btn-outline-secondary"><?= htmlspecialchars((string)($lang['sys.list'] ?? 'Список')) ?></a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <tbody>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.total'] ?? 'Всего')) ?></th><td><?= (int) ($lifecycle['total'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.queued'] ?? 'В очереди')) ?></th><td><?= (int) ($lifecycle['queued'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.running'] ?? 'В работе')) ?></th><td><?= (int) ($lifecycle['running'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.failed'] ?? 'С ошибкой')) ?></th><td><?= (int) ($lifecycle['failed'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.stale_jobs'] ?? 'Зависшие')) ?></th><td><?= (int) ($lifecycle['stale_running'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.last_update'] ?? 'Последнее обновление')) ?></th><td><?= htmlspecialchars((string) (($lifecycle['last_finished_at'] ?? '') ?: '-')) ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border h-100">
                    <div class="card-header"><strong><?= htmlspecialchars((string)($lang['sys.media_diagnostics'] ?? 'Диагностика медиа')) ?></strong></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <tbody>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.total'] ?? 'Всего')) ?></th><td><?= (int) ($media['total_files'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.referenced_files'] ?? 'Связанные файлы')) ?></th><td><?= (int) ($media['referenced_file_ids'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.unreferenced_files'] ?? 'Неиспользуемые файлы')) ?></th><td><?= (int) ($media['unreferenced_files'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.missing_on_disk'] ?? 'Отсутствуют на диске')) ?></th><td><?= (int) ($media['missing_on_disk'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.dangling_references'] ?? 'Битые ссылки')) ?></th><td><?= (int) ($media['dangling_references'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.legacy_payloads'] ?? 'Legacy payload')) ?></th><td><?= (int) ($media['legacy_payloads_without_file_ids'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.media_queue_pending'] ?? 'Ожидают')) ?></th><td><?= (int) ($mediaQueue['pending'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.media_queue_running'] ?? 'В работе')) ?></th><td><?= (int) ($mediaQueue['running'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.media_queue_failed'] ?? 'С ошибкой')) ?></th><td><?= (int) ($mediaQueue['failed'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.media_queue_terminal_failed'] ?? 'Без повтора')) ?></th><td><?= (int) ($mediaQueue['terminal_failed'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.media_queue_done'] ?? 'Готово')) ?></th><td><?= (int) ($mediaQueue['done'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.media_queue_last_completed'] ?? 'Последняя догрузка')) ?></th><td><?= htmlspecialchars((string) (($mediaQueue['last_completed_at'] ?? '') ?: '-')) ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border h-100">
                    <div class="card-header"><strong><?= htmlspecialchars((string)($lang['sys.search.title'] ?? $lang['search.title'] ?? 'Search')) ?></strong></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <tbody>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.project_logs'] ?? 'Логи проекта')) ?></th><td><?= (int) (($report['logs']['project_logs']['files'] ?? 0)) ?> <?= htmlspecialchars((string)($lang['sys.file_count'] ?? 'файлов')) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.pages'] ?? 'Страницы')) ?></th><td><?= (int) ($search['search_index_rows'] ?? 0) ?></td></tr>
                                    <tr><th>N-grams</th><td><?= (int) ($search['search_ngram_rows'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.filtres'] ?? 'Filters')) ?></th><td><?= (int) ($search['filters_rows'] ?? 0) ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong><?= htmlspecialchars((string)($lang['sys.backup'] ?? 'Резервное копирование')) ?></strong>
                        <a href="/admin/backup" class="btn btn-sm btn-outline-secondary"><?= htmlspecialchars((string)($lang['sys.list'] ?? 'Список')) ?></a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <tbody>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.path'] ?? 'Путь')) ?></th><td class="small"><?= htmlspecialchars((string) ($backups['path'] ?? '-')) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.backup_jobs_queued'] ?? 'В очереди')) ?></th><td><?= (int) (($backups['jobs']['queued'] ?? 0)) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.backup_jobs_running'] ?? 'В работе')) ?></th><td><?= (int) (($backups['jobs']['running'] ?? 0)) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.backup_jobs_failed'] ?? 'С ошибкой')) ?></th><td><?= (int) (($backups['jobs']['failed'] ?? 0)) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.stale_jobs'] ?? 'Зависшие')) ?></th><td><?= (int) ($backups['stale_running'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.backup_default_plan'] ?? 'План по умолчанию')) ?></th><td><?= htmlspecialchars((string) (($backups['default_plan']['name'] ?? '') ?: '-')) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.last_update'] ?? 'Последнее обновление')) ?></th><td><?= htmlspecialchars((string) (($backups['last_completed_at'] ?? '') ?: '-')) ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
