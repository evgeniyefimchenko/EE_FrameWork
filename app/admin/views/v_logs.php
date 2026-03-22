<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<!-- Страница просмотра логов -->
<div class="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><?= $lang['sys.logs'] ?></h4>
                    </div>                    
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
                            <a href="/admin/health" class="btn btn-outline-success">
                                <i class="fa-solid fa-heart-pulse"></i>&nbsp;<?= $lang['sys.health'] ?? 'Состояние системы' ?>
                            </a>
                            <a href="/admin/clear_html_cache" onclick="return confirm('<?= $lang['sys.clear_html_cache'] ?? 'Очистить HTML-кэш?' ?>');" class="btn btn-outline-primary">
                                <i class="fa-solid fa-broom"></i>&nbsp;<?= $lang['sys.clear_html_cache'] ?? 'Очистить HTML-кэш' ?>
                            </a>
                            <a href="/admin/clear_route_cache" onclick="return confirm('<?= $lang['sys.clear_route_cache'] ?? 'Очистить route-кэш?' ?>');" class="btn btn-outline-secondary">
                                <i class="fa-solid fa-route"></i>&nbsp;<?= $lang['sys.clear_route_cache'] ?? 'Очистить route-кэш' ?>
                            </a>
                            <a href="/admin/reset_redis_cache_probe" class="btn btn-outline-warning">
                                <i class="fa-solid fa-arrows-rotate"></i>&nbsp;<?= $lang['sys.reset_redis_probe'] ?? 'Сбросить проверку Redis' ?>
                            </a>
                        </div>
                        <?php $logsSummary = is_array($logs_summary ?? null) ? $logs_summary : []; ?>
                        <div class="row g-3 mb-4">
                            <?php foreach ($logsSummary as $summaryKey => $summaryItem): ?>
                                <div class="col-12 col-lg-4">
                                    <div class="card border h-100 shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title mb-0">
                                                    <?=
                                                    $summaryKey === 'php_logs'
                                                        ? ($lang['sys.php_logs'] ?? 'PHP-логи')
                                                        : ($summaryKey === 'fatal_logs'
                                                            ? ($lang['sys.fatal_logs'] ?? 'Фатальные логи')
                                                            : ($lang['sys.project_logs'] ?? 'Логи проекта'))
                                                    ?>
                                                </h5>
                                                <span class="badge <?= !empty($summaryItem['updated_at']) ? 'bg-success' : 'bg-secondary' ?>">
                                                    <?= !empty($summaryItem['updated_at']) ? ($lang['sys.active'] ?? 'Активно') : ($lang['sys.empty'] ?? 'Пусто') ?>
                                                </span>
                                            </div>
                                            <div class="small text-muted mb-1"><?= $lang['sys.file_count'] ?? 'Файлов' ?>: <strong><?= (int) ($summaryItem['files'] ?? 0) ?></strong></div>
                                            <div class="small text-muted mb-1"><?= $lang['sys.archive_count'] ?? 'Архивов' ?>: <strong><?= (int) ($summaryItem['archives'] ?? 0) ?></strong></div>
                                            <?php if (isset($summaryItem['channels'])): ?>
                                                <div class="small text-muted mb-1"><?= $lang['sys.log_channels'] ?? 'Каналов' ?>: <strong><?= (int) ($summaryItem['channels'] ?? 0) ?></strong></div>
                                            <?php endif; ?>
                                            <div class="small text-muted mb-1"><?= $lang['sys.log_size'] ?? 'Размер' ?>: <strong><?= htmlspecialchars((string) ($summaryItem['size_human'] ?? '0 B'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                                            <div class="small text-muted"><?= $lang['sys.last_update'] ?? 'Последнее обновление' ?>:
                                                <strong><?= htmlspecialchars((string) (($summaryItem['updated_at'] ?? '') ?: ($lang['sys.no_data'] ?? 'Нет данных')), ENT_QUOTES, 'UTF-8') ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <!-- Tab links -->
                        <ul class="nav nav-tabs" id="eeTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="php_log_logs-tab" data-bs-toggle="tab" data-bs-target="#php_log_logs" role="tab" aria-controls="php_log_logs" aria-selected="true"><?= $lang['sys.php_logs'] ?? 'PHP-логи' ?></button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="fatal_errors-tab" data-bs-toggle="tab" data-bs-target="#fatal_errors" role="tab" aria-controls="fatal_errors" aria-selected="false"><?= $lang['sys.fatal_logs'] ?? 'Фатальные логи' ?></button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="project_logs-tab" data-bs-toggle="tab" data-bs-target="#project_logs" role="tab" aria-controls="project_logs" aria-selected="false"><?= $lang['sys.project_logs'] ?? 'Логи проекта' ?></button>
                            </li>
                        </ul>
                        <!-- Tab content -->
                        <div class="tab-content" id="eeTabContent">
                            <!-- php_log_logs -->
                            <div id="php_log_logs" class="tab-pane show active mt-3" role="tabpanel" aria-labelledby="php_log_logs-tab">
                                <div class="d-flex justify-content-end mb-3">
                                    <a href="/admin/clear_php_logs" onclick="return confirm('<?= $lang['sys.clear_file'] ?>');" class="btn btn-outline-danger btn-sm">
                                        <i class="fa-solid fa-trash"></i>&nbsp;<?= $lang['sys.clear_php_logs'] ?? 'Очистить PHP-логи' ?>
                                    </a>
                                </div>
                                <?=$php_logs_table ?>
                            </div>
                            <!-- fatal_errors -->
                            <div id="fatal_errors" class="tab-pane fade mt-3" role="tabpanel" aria-labelledby="fatal_errors-tab">
                                <div class="d-flex justify-content-end mb-3">
                                    <a href="/admin/clear_fatal_logs" onclick="return confirm('<?= $lang['sys.clear_file'] ?>');" class="btn btn-outline-danger btn-sm">
                                        <i class="fa-solid fa-trash"></i>&nbsp;<?= $lang['sys.clear_fatal_logs'] ?? 'Очистить фатальные логи' ?>
                                    </a>
                                </div>
                                <?= $fatal_errors_table ?>
                            </div>
                            <!-- project_logs -->
                            <div id="project_logs" class="tab-pane fade mt-3" role="tabpanel" aria-labelledby="project_logs-tab">
                                <div class="d-flex justify-content-end mb-3">
                                    <a href="/admin/clear_project_logs" onclick="return confirm('<?= $lang['sys.clear_file'] ?>');" class="btn btn-outline-danger btn-sm">
                                        <i class="fa-solid fa-trash"></i>&nbsp;<?= $lang['sys.clear_project_logs'] ?? 'Очистить логи проекта' ?>
                                    </a>
                                </div>
                                <?= $project_logs_table ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
