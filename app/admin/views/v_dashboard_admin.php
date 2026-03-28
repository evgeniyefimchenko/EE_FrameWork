<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$overview = is_array($dashboard_overview ?? null) ? $dashboard_overview : [];
$isAdmin = !empty($overview['is_admin']);
$catalog = is_array($overview['catalog'] ?? null) ? $overview['catalog'] : [];
$quality = is_array($overview['quality'] ?? null) ? $overview['quality'] : [];
$operations = is_array($overview['operations'] ?? null) ? $overview['operations'] : [];
$alerts = is_array($overview['health_alerts'] ?? null) ? $overview['health_alerts'] : [];
$alertsSummary = is_array($operations['alerts_summary'] ?? null) ? $operations['alerts_summary'] : ['total' => 0, 'critical' => 0, 'warning' => 0, 'info' => 0];
$cron = is_array($operations['cron'] ?? null) ? $operations['cron'] : [];
$mediaQueue = is_array($operations['media_queue'] ?? null) ? $operations['media_queue'] : [];
$backup = is_array($operations['backup'] ?? null) ? $operations['backup'] : [];
$storage = is_array($operations['storage'] ?? null) ? $operations['storage'] : [];
$mail = is_array($operations['mail'] ?? null) ? $operations['mail'] : [];
$recentImports = is_array($overview['recent_imports'] ?? null) ? $overview['recent_imports'] : [];
$recentRuns = is_array($overview['recent_runs'] ?? null) ? $overview['recent_runs'] : [];
$recentBackups = is_array($overview['recent_backups'] ?? null) ? $overview['recent_backups'] : [];
$quickActions = is_array($overview['quick_actions'] ?? null) ? $overview['quick_actions'] : [];
$generatedAt = (string) ($overview['generated_at_pretty'] ?? '');
$formatDate = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '—';
    }
    return ee_format_utc_datetime($value, 'd.m.Y H:i');
};
$alertClass = static function (string $severity): string {
    return match ($severity) {
        'critical' => 'danger',
        'warning' => 'warning',
        default => 'info',
    };
};
$interfaceLanguageCodes = ee_get_interface_lang_codes();
$currentInterfaceLanguageCode = ee_get_current_lang_code();
$currentPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/admin'), PHP_URL_PATH);
$currentQuery = $_GET;
unset($currentQuery['ui_lang']);
?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mt-4 mb-4">
            <div>
                <h1 class="mb-1"><?= htmlspecialchars((string) ($lang['sys.dashboard_overview_heading'] ?? 'Обзор проекта')) ?></h1>
                <div class="text-muted small">
                    <?= htmlspecialchars((string) ($lang['sys.dashboard_overview_subtitle'] ?? 'Живой срез каталога, импорта, агентов и операционного состояния.')) ?>
                    <?php if ($generatedAt !== ''): ?>
                        <span class="ms-2"><?= htmlspecialchars((string) ($lang['sys.updated_at'] ?? 'Обновлено')) ?>: <strong><?= htmlspecialchars($generatedAt) ?></strong></span>
                    <?php endif; ?>
                </div>
                <?php if ($interfaceLanguageCodes !== []): ?>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                        <span class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.interface_language'] ?? 'Interface language')) ?>:</span>
                        <div class="btn-group btn-group-sm" role="group" aria-label="<?= htmlspecialchars((string) ($lang['sys.interface_language'] ?? 'Interface language')) ?>">
                            <?php foreach ($interfaceLanguageCodes as $interfaceLanguageCode): ?>
                                <?php
                                $langQuery = array_merge($currentQuery, ['ui_lang' => $interfaceLanguageCode]);
                                $langUrl = $currentPath . (!empty($langQuery) ? '?' . http_build_query($langQuery) : '');
                                ?>
                                <a
                                    href="<?= htmlspecialchars($langUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    class="btn <?= $interfaceLanguageCode === $currentInterfaceLanguageCode ? 'btn-primary active' : 'btn-outline-secondary' ?>"
                                    data-lang-switch
                                    data-langcode="<?= htmlspecialchars((string) $interfaceLanguageCode, ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <?= htmlspecialchars((string) $interfaceLanguageCode, ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($quickActions !== []): ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($quickActions as $action): ?>
                        <a href="<?= htmlspecialchars((string) ($action['href'] ?? '#')) ?>" class="btn <?= htmlspecialchars((string) ($action['class'] ?? 'btn-outline-secondary')) ?>">
                            <i class="fa-solid <?= htmlspecialchars((string) ($action['icon'] ?? 'fa-arrow-right')) ?>"></i>
                            &nbsp;<?= htmlspecialchars((string) ($action['label'] ?? '')) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$isAdmin): ?>
            <?php $userSummary = is_array($overview['user_summary'] ?? null) ? $overview['user_summary'] : []; ?>
            <div class="alert alert-info">
                <strong><?= htmlspecialchars((string) ($lang['sys.dashboard_admin_only'] ?? 'Подробный обзор системы доступен администратору.')) ?></strong>
            </div>
            <div class="row g-3">
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body">
                            <div class="small text-muted mb-1"><?= htmlspecialchars((string) ($lang['sys.user'] ?? 'Пользователь')) ?></div>
                            <div class="h5 mb-1"><?= htmlspecialchars((string) ($userSummary['name'] ?? '')) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($userSummary['email'] ?? '')) ?></div>
                            <div class="small text-muted mt-2"><?= htmlspecialchars((string) ($lang['sys.notifications'] ?? 'Уведомления')) ?>: <strong><?= (int) ($userSummary['notifications_count'] ?? 0) ?></strong></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.messages'] ?? 'Сообщения')) ?>: <strong><?= (int) ($userSummary['messages_count'] ?? 0) ?></strong></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php if ((int) ($alertsSummary['total'] ?? 0) > 0): ?>
                <div class="card shadow-sm border mb-4">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <strong><?= htmlspecialchars((string) ($lang['sys.dashboard_operational_alerts'] ?? 'Операционные сигналы')) ?></strong>
                        <div class="small text-muted">
                            <?= htmlspecialchars((string) ($lang['sys.health_alerts_summary'] ?? 'Критично:')) ?> <strong><?= (int) ($alertsSummary['critical'] ?? 0) ?></strong>,
                            <?= htmlspecialchars((string) ($lang['sys.health_alerts_warning'] ?? 'Предупреждения:')) ?> <strong><?= (int) ($alertsSummary['warning'] ?? 0) ?></strong>,
                            <?= htmlspecialchars((string) ($lang['sys.health_alerts_info'] ?? 'Инфо:')) ?> <strong><?= (int) ($alertsSummary['info'] ?? 0) ?></strong>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($alerts as $alert): ?>
                                <div class="alert alert-<?= htmlspecialchars($alertClass((string) ($alert['severity'] ?? 'info'))) ?> mb-0 d-flex flex-column flex-lg-row justify-content-between gap-2 align-items-lg-center">
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars((string) ($alert['title'] ?? '')) ?></div>
                                        <div class="small"><?= htmlspecialchars((string) ($alert['message'] ?? '')) ?></div>
                                    </div>
                                    <?php if (!empty($alert['action_url']) && !empty($alert['action_label'])): ?>
                                        <div>
                                            <a href="<?= htmlspecialchars((string) $alert['action_url']) ?>" class="btn btn-sm btn-outline-<?= htmlspecialchars($alertClass((string) ($alert['severity'] ?? 'info'))) ?>">
                                                <?= htmlspecialchars((string) $alert['action_label']) ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-success mb-4">
                    <strong><?= htmlspecialchars((string) ($lang['sys.health_no_alerts'] ?? 'Критичных и предупреждающих событий не найдено.')) ?></strong>
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body">
                            <div class="small text-muted mb-1"><?= htmlspecialchars((string) ($lang['sys.pages'] ?? 'Страницы')) ?></div>
                            <div class="h4 mb-1"><?= (int) ($catalog['pages']['total'] ?? 0) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.dashboard_pages_active'] ?? 'Активных')) ?>: <strong><?= (int) ($catalog['pages']['active'] ?? 0) ?></strong></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.dashboard_pages_hidden'] ?? 'Скрытых')) ?>: <strong><?= (int) ($catalog['pages']['hidden'] ?? 0) ?></strong></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body">
                            <div class="small text-muted mb-1"><?= htmlspecialchars((string) ($lang['sys.categories'] ?? 'Категории')) ?></div>
                            <div class="h4 mb-1"><?= (int) ($catalog['categories']['total'] ?? 0) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.dashboard_categories_with_photos'] ?? 'С галереей')) ?>: <strong><?= (int) ($catalog['categories']['with_photos'] ?? 0) ?></strong></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.dashboard_categories_with_map'] ?? 'С картой')) ?>: <strong><?= (int) ($catalog['categories']['with_map'] ?? 0) ?></strong></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body">
                            <div class="small text-muted mb-1"><?= htmlspecialchars((string) ($lang['sys.users'] ?? 'Пользователи')) ?></div>
                            <div class="h4 mb-1"><?= (int) ($catalog['users']['total'] ?? 0) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.dashboard_owner_links'] ?? 'Связано владельцев')) ?>: <strong><?= (int) ($catalog['users']['owners_linked'] ?? 0) ?></strong></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.dashboard_pages_without_owner'] ?? 'Без владельца')) ?>: <strong><?= (int) ($quality['without_owner'] ?? 0) ?></strong></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body">
                            <div class="small text-muted mb-1"><?= htmlspecialchars((string) ($lang['sys.media_queue'] ?? 'Медиа')) ?></div>
                            <div class="h4 mb-1"><?= (int) ($catalog['files']['total'] ?? 0) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.media_queue_pending'] ?? 'Ожидают')) ?>: <strong><?= (int) ($mediaQueue['pending'] ?? 0) ?></strong></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.media_queue_done'] ?? 'Готово')) ?>: <strong><?= (int) ($mediaQueue['done'] ?? 0) ?></strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body">
                            <div class="small text-muted mb-1"><?= htmlspecialchars((string) ($lang['sys.dashboard_content_quality'] ?? 'Контентная готовность')) ?></div>
                            <div class="h4 mb-2"><?= (int) ($quality['ready_pages'] ?? 0) ?> / <?= (int) ($quality['active_pages'] ?? 0) ?></div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: <?= htmlspecialchars((string) ($quality['readiness_percent'] ?? 0)) ?>%;" aria-valuenow="<?= htmlspecialchars((string) ($quality['readiness_percent'] ?? 0)) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="small text-muted mt-2"><?= htmlspecialchars((string) ($lang['sys.dashboard_readiness_percent'] ?? 'Готово к публикации')) ?>: <strong><?= htmlspecialchars((string) ($quality['readiness_percent'] ?? 0)) ?>%</strong></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body">
                            <div class="small text-muted mb-1"><?= htmlspecialchars((string) ($lang['sys.dashboard_contacts_block'] ?? 'Контакты и фото')) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.dashboard_pages_without_contacts'] ?? 'Без контактов')) ?>: <strong><?= (int) ($quality['without_contacts'] ?? 0) ?></strong></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.dashboard_pages_without_photos'] ?? 'Без фото')) ?>: <strong><?= (int) ($quality['without_photos'] ?? 0) ?></strong></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.dashboard_contacts_verified'] ?? 'Контакты проверены')) ?>: <strong><?= (int) ($quality['contacts_verified'] ?? 0) ?></strong></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body">
                            <div class="small text-muted mb-1"><?= htmlspecialchars((string) ($lang['sys.dashboard_room_block'] ?? 'Номера и размещение')) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.dashboard_pages_without_rooms'] ?? 'Без блока номеров')) ?>: <strong><?= (int) ($quality['without_rooms'] ?? 0) ?></strong></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.dashboard_paid_placements'] ?? 'Платное размещение')) ?>: <strong><?= (int) ($quality['paid_placements'] ?? 0) ?></strong></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.dashboard_placements_expiring'] ?? 'Истекает в 30 дней')) ?>: <strong><?= (int) ($quality['expiring_soon'] ?? 0) ?></strong></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body">
                            <div class="small text-muted mb-1"><?= htmlspecialchars((string) ($lang['sys.dashboard_operations'] ?? 'Операционное состояние')) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.cron_agents'] ?? 'Cron-агенты')) ?>: <strong><?= (int) ($cron['due'] ?? 0) ?></strong> / <strong><?= (int) ($cron['locked'] ?? 0) ?></strong></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.disk_free'] ?? 'Свободно на диске')) ?>: <strong><?= htmlspecialchars((string) ($storage['free_pretty'] ?? '0 B')) ?></strong></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.email'] ?? 'Почта')) ?>: <strong><?= !empty($mail['transport_available']) ? ($lang['sys.active'] ?? 'Активно') : ($lang['sys.error'] ?? 'Ошибка') ?></strong></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.dashboard_last_cron'] ?? 'Last cron')) ?>: <strong><?= htmlspecialchars($formatDate((string) ($cron['last_run_at'] ?? ''))) ?></strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-xl-4">
                    <div class="card shadow-sm border h-100">
                        <div class="card-header"><strong><?= htmlspecialchars((string) ($lang['sys.dashboard_recent_imports'] ?? 'Последние импорты')) ?></strong></div>
                        <div class="card-body">
                            <?php if ($recentImports === []): ?>
                                <div class="text-muted small"><?= htmlspecialchars((string) ($lang['sys.dashboard_no_imports'] ?? 'Профили импорта ещё не запускались.')) ?></div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th><?= htmlspecialchars((string) ($lang['sys.name'] ?? 'Название')) ?></th>
                                                <th><?= htmlspecialchars((string) ($lang['sys.updated_at'] ?? 'Обновлено')) ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentImports as $importRow): ?>
                                                <tr>
                                                    <td>#<?= (int) ($importRow['id'] ?? 0) ?></td>
                                                    <td>
                                                        <div class="fw-semibold"><?= htmlspecialchars((string) ($importRow['settings_name'] ?? '')) ?></div>
                                                        <div class="small text-muted"><?= htmlspecialchars((string) ($importRow['importer_slug'] ?? '')) ?></div>
                                                    </td>
                                                    <td><?= htmlspecialchars($formatDate((string) (($importRow['last_run_at'] ?? '') !== '' ? $importRow['last_run_at'] : ($importRow['created_at'] ?? '')))) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-4">
                    <div class="card shadow-sm border h-100">
                        <div class="card-header"><strong><?= htmlspecialchars((string) ($lang['sys.dashboard_recent_agent_runs'] ?? 'Последние запуски агентов')) ?></strong></div>
                        <div class="card-body">
                            <?php if ($recentRuns === []): ?>
                                <div class="text-muted small"><?= htmlspecialchars((string) ($lang['sys.dashboard_no_runs'] ?? 'Запусков агентов пока нет.')) ?></div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th><?= htmlspecialchars((string) ($lang['sys.agent'] ?? 'Agent')) ?></th>
                                                <th><?= htmlspecialchars((string) ($lang['sys.status'] ?? 'Статус')) ?></th>
                                                <th><?= htmlspecialchars((string) ($lang['sys.when'] ?? 'When')) ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentRuns as $runRow): ?>
                                                <?php $status = (string) ($runRow['status'] ?? ''); ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?= htmlspecialchars((string) ($runRow['agent_code'] ?? '')) ?></div>
                                                        <div class="small text-muted"><?= htmlspecialchars((string) ($runRow['handler'] ?? '')) ?></div>
                                                    </td>
                                                    <td>
                                                        <span class="badge text-bg-<?= htmlspecialchars($status === 'success' ? 'success' : ($status === 'failed' ? 'danger' : 'secondary')) ?>">
                                                            <?= htmlspecialchars($status) ?>
                                                        </span>
                                                        <div class="small text-muted"><?= (int) ($runRow['duration_ms'] ?? 0) ?> ms</div>
                                                    </td>
                                                    <td><?= htmlspecialchars($formatDate((string) ($runRow['started_at'] ?? ''))) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-4">
                    <div class="card shadow-sm border h-100">
                        <div class="card-header"><strong><?= htmlspecialchars((string) ($lang['sys.dashboard_recent_backups'] ?? 'Последние backup-задачи')) ?></strong></div>
                        <div class="card-body">
                            <?php if ($recentBackups === []): ?>
                                <div class="text-muted small"><?= htmlspecialchars((string) ($lang['sys.dashboard_no_backup_jobs'] ?? 'Backup-задачи ещё не запускались.')) ?></div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th><?= htmlspecialchars((string) ($lang['sys.backup'] ?? 'Backup')) ?></th>
                                                <th><?= htmlspecialchars((string) ($lang['sys.status'] ?? 'Статус')) ?></th>
                                                <th><?= htmlspecialchars((string) ($lang['sys.when'] ?? 'When')) ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentBackups as $backupRow): ?>
                                                <?php $backupStatus = (string) ($backupRow['status'] ?? ''); ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?= htmlspecialchars((string) ($backupRow['title'] ?? ('#' . (int) ($backupRow['backup_job_id'] ?? 0)))) ?></div>
                                                        <div class="small text-muted"><?= htmlspecialchars((string) ($backupRow['scope'] ?? '')) ?></div>
                                                    </td>
                                                    <td>
                                                        <span class="badge text-bg-<?= htmlspecialchars($backupStatus === 'done' ? 'success' : ($backupStatus === 'failed' ? 'danger' : ($backupStatus === 'running' ? 'primary' : 'secondary'))) ?>">
                                                            <?= htmlspecialchars($backupStatus) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($formatDate((string) (($backupRow['finished_at'] ?? '') !== '' ? $backupRow['finished_at'] : ($backupRow['created_at'] ?? '')))) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>
