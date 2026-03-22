<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$report = is_array($health_report ?? null) ? $health_report : [];
$install = is_array($report['install'] ?? null) ? $report['install'] : [];
$paths = is_array($report['paths'] ?? null) ? $report['paths'] : [];
$cache = is_array($report['cache'] ?? null) ? $report['cache'] : [];
$lifecycle = is_array($report['lifecycle'] ?? null) ? $report['lifecycle'] : [];
$media = is_array($report['media'] ?? null) ? $report['media'] : [];
$search = is_array($report['search'] ?? null) ? $report['search'] : [];
$backups = is_array($report['backups'] ?? null) ? $report['backups'] : [];
?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3 flex-wrap gap-2">
            <h1 class="mb-0"><?= htmlspecialchars((string)($lang['sys.health'] ?? 'Состояние системы')) ?></h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="/admin/recover_stale_lifecycle_jobs" class="btn btn-outline-warning">
                    <i class="fa-solid fa-arrows-rotate"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.recover_stale_jobs'] ?? 'Восстановить зависшие задачи')) ?>
                </a>
                <a href="/admin/refresh_media_metadata" class="btn btn-outline-primary">
                    <i class="fa-solid fa-photo-film"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.refresh_media_metadata'] ?? 'Обновить метаданные файлов')) ?>
                </a>
                <a href="/admin/run_backup" class="btn btn-primary">
                    <i class="fa-solid fa-box-archive"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.run_backup'] ?? 'Создать резервную копию')) ?>
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.install'] ?? 'Установка')) ?></div>
                        <div class="h5 mb-1"><?= !empty($install['database_connected']) ? ($lang['sys.active'] ?? 'Активно') : ($lang['sys.error'] ?? 'Ошибка') ?></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.core_tables'] ?? 'Ключевые таблицы')) ?>: <strong><?= !empty($install['core_tables_ok']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></strong></div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.auth_infrastructure'] ?? 'Auth-инфраструктура')) ?>: <strong><?= !empty($install['auth_tables_ok']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></strong></div>
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
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-6">
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
                                        <th><?= htmlspecialchars((string)($lang['sys.core_tables'] ?? 'Ключевые таблицы')) ?></th>
                                        <td><?= !empty($install['core_tables_ok']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></td>
                                    </tr>
                                    <tr>
                                        <th><?= htmlspecialchars((string)($lang['sys.auth_infrastructure'] ?? 'Auth-инфраструктура')) ?></th>
                                        <td><?= !empty($install['auth_tables_ok']) ? ($lang['sys.yes'] ?? 'Да') : ($lang['sys.no'] ?? 'Нет') ?></td>
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
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6" id="backup">
                <div class="card shadow-sm border h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong><?= htmlspecialchars((string)($lang['sys.backup'] ?? 'Резервное копирование')) ?></strong>
                        <a href="/admin/run_backup" class="btn btn-sm btn-primary"><?= htmlspecialchars((string)($lang['sys.run_backup'] ?? 'Создать резервную копию')) ?></a>
                    </div>
                    <div class="card-body">
                        <div class="small text-muted mb-2"><?= htmlspecialchars((string)($lang['sys.path'] ?? 'Путь')) ?>: <?= htmlspecialchars((string) ($backups['path'] ?? '')) ?></div>
                        <div class="small text-muted mb-3"><?= htmlspecialchars((string)($lang['sys.snapshots'] ?? 'Снапшоты')) ?>: <strong><?= (int) ($backups['snapshots_count'] ?? 0) ?></strong></div>
                        <?php if (!empty($backups['items']) && is_array($backups['items'])): ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th><?= htmlspecialchars((string)($lang['sys.name'] ?? 'Название')) ?></th>
                                            <th><?= htmlspecialchars((string)($lang['sys.updated'] ?? 'Обновлено')) ?></th>
                                            <th>DB</th>
                                            <th>Files</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backups['items'] as $backupItem): ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string) ($backupItem['name'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars((string) ($backupItem['updated_at'] ?? '')) ?></td>
                                                <td class="small"><?= htmlspecialchars((string) ($backupItem['db_archive'] ?? '')) ?></td>
                                                <td class="small"><?= htmlspecialchars((string) ($backupItem['files_archive'] ?? '')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-muted"><?= htmlspecialchars((string)($lang['sys.no_data'] ?? 'Нет данных')) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border h-100">
                    <div class="card-header"><strong><?= htmlspecialchars((string)($lang['sys.search.title'] ?? 'Поиск')) ?></strong></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <tbody>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.project_logs'] ?? 'Логи проекта')) ?></th><td><?= (int) (($report['logs']['project_logs']['files'] ?? 0)) ?> <?= htmlspecialchars((string)($lang['sys.file_count'] ?? 'файлов')) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.pages'] ?? 'Страницы')) ?></th><td><?= (int) ($search['search_index_rows'] ?? 0) ?></td></tr>
                                    <tr><th>N-grams</th><td><?= (int) ($search['search_ngram_rows'] ?? 0) ?></td></tr>
                                    <tr><th><?= htmlspecialchars((string)($lang['sys.filtres'] ?? 'Фильтры')) ?></th><td><?= (int) ($search['filters_rows'] ?? 0) ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
