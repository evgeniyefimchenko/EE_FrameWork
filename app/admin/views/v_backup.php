<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$summary = is_array($backup_summary ?? null) ? $backup_summary : [];
$plans = is_array($backup_plans ?? null) ? $backup_plans : [];
$targets = is_array($backup_targets ?? null) ? $backup_targets : [];
$jobs = is_array($backup_jobs ?? null) ? $backup_jobs : [];
$workerAgent = is_array($backup_worker_agent ?? null) ? $backup_worker_agent : [];
$cronSummary = is_array($cron_agents_summary ?? null) ? $cron_agents_summary : [];
$schedulerCommand = (string) ($cronSummary['scheduler_command'] ?? ('php ' . ENV_SITE_PATH . 'app/cron/run.php'));
$defaultPlanId = (int) ($summary['default_plan']['backup_plan_id'] ?? 0);
$defaultTargetId = (int) ($summary['default_target']['target_id'] ?? 0);
$localItems = is_array($summary['items'] ?? null) ? $summary['items'] : [];
$statusLabels = [
    'queued' => (string) ($lang['sys.backup_jobs_queued'] ?? 'В очереди'),
    'running' => (string) ($lang['sys.backup_jobs_running'] ?? 'В работе'),
    'done' => (string) ($lang['sys.backup_jobs_done'] ?? 'Готово'),
    'partial' => (string) ($lang['sys.backup_jobs_partial'] ?? 'Частично'),
    'failed' => (string) ($lang['sys.backup_jobs_failed'] ?? 'С ошибкой'),
];
?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3 flex-wrap gap-2">
            <h1 class="mb-0"><?= htmlspecialchars((string) ($lang['sys.backup'] ?? 'Резервное копирование')) ?></h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="/admin/backup_plan_edit/id/0" class="btn btn-primary">
                    <i class="fa-solid fa-layer-group"></i>&nbsp;<?= htmlspecialchars((string) ($lang['sys.backup_plan_new'] ?? 'Добавить backup-план')) ?>
                </a>
                <a href="/admin/backup_target_edit/id/0" class="btn btn-outline-primary">
                    <i class="fa-solid fa-server"></i>&nbsp;<?= htmlspecialchars((string) ($lang['sys.backup_target_new'] ?? 'Добавить удалённый хост')) ?>
                </a>
                <?php if (!empty($workerAgent['agent_id'])): ?>
                    <a href="<?= htmlspecialchars(\classes\system\CsrfService::appendToUrl('/admin/run_cron_agent/id/' . (int) ($workerAgent['agent_id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-play"></i>&nbsp;<?= htmlspecialchars((string) ($lang['sys.backup_run_worker_now'] ?? 'Запустить backup-worker')) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="alert alert-info shadow-sm mb-4">
            <div class="fw-bold mb-2"><?= htmlspecialchars((string) ($lang['sys.backup_scheduler_notice'] ?? 'Резервное копирование работает через фонового системного агента.')) ?></div>
            <div class="mb-2"><?= htmlspecialchars((string) ($lang['sys.backup_scheduler_command_help'] ?? 'На сервере должен быть настроен один минутный scheduler:')) ?></div>
            <code><?= htmlspecialchars($schedulerCommand) ?></code>
        </div>

        <div class="row g-3 mb-4">
            <?php foreach ([
                ['label' => $lang['sys.backup_plans'] ?? 'Планы', 'value' => (int) ($summary['plans_total'] ?? 0), 'muted' => ($lang['sys.active'] ?? 'Активно') . ': ' . (int) ($summary['plans_active'] ?? 0)],
                ['label' => $lang['sys.backup_remote_targets'] ?? 'Удалённые хранилища', 'value' => (int) ($summary['targets_total'] ?? 0), 'muted' => ($lang['sys.active'] ?? 'Активно') . ': ' . (int) ($summary['targets_active'] ?? 0)],
                ['label' => $lang['sys.backup_jobs_queued'] ?? 'В очереди', 'value' => (int) ($summary['jobs']['queued'] ?? 0), 'muted' => ($lang['sys.backup_jobs_running'] ?? 'В работе') . ': ' . (int) ($summary['jobs']['running'] ?? 0)],
                ['label' => $lang['sys.backup_jobs_done'] ?? 'Готово', 'value' => (int) ($summary['jobs']['done'] ?? 0), 'muted' => ($lang['sys.backup_jobs_partial'] ?? 'Частично') . ': ' . (int) ($summary['jobs']['partial'] ?? 0)],
                ['label' => $lang['sys.backup_jobs_failed'] ?? 'С ошибкой', 'value' => (int) ($summary['jobs']['failed'] ?? 0), 'muted' => ($lang['sys.last_update'] ?? 'Последнее обновление') . ': ' . (($summary['last_completed_at'] ?? '') ?: '-')],
                ['label' => $lang['sys.snapshots'] ?? 'Снапшоты', 'value' => (int) ($summary['snapshots_count'] ?? 0), 'muted' => ($summary['latest_snapshot'] ?? '') ?: '-'],
            ] as $card): ?>
                <div class="col-6 col-xl-2">
                    <div class="card border shadow-sm h-100">
                        <div class="card-body">
                            <div class="small text-muted mb-1"><?= htmlspecialchars((string) $card['label']) ?></div>
                            <div class="h4 mb-1"><?= (int) $card['value'] ?></div>
                            <div class="small text-muted"><?= htmlspecialchars((string) ($card['muted'] ?? '')) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-5">
                <form method="post" action="<?= htmlspecialchars(\classes\system\CsrfService::appendToUrl('/admin/run_backup'), ENT_QUOTES, 'UTF-8') ?>" class="card shadow-sm border h-100">
                    <div class="card-header"><strong><?= htmlspecialchars((string) ($lang['sys.run_backup'] ?? 'Создать резервную копию')) ?></strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label"><?= htmlspecialchars((string) ($lang['sys.backup_plan'] ?? 'План резервного копирования')) ?></label>
                            <select name="plan_id" class="form-select" required>
                                <option value=""><?= htmlspecialchars((string) ($lang['sys.choose'] ?? 'Выберите')) ?></option>
                                <?php foreach ($plans as $plan): ?>
                                    <?php if (empty($plan['is_active'])) continue; ?>
                                    <option value="<?= (int) ($plan['backup_plan_id'] ?? 0) ?>" <?= (int) ($plan['backup_plan_id'] ?? 0) === $defaultPlanId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) ($plan['name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text"><?= htmlspecialchars((string) ($lang['sys.backup_plan_run_help'] ?? 'Запуск ставит backup-задачу в очередь. Состав БД, файлов и удалённая отправка берутся из выбранного плана.')) ?></div>
                        </div>
                        <?php if (!empty($summary['default_plan']['name'])): ?>
                            <div class="small text-muted">
                                <?= htmlspecialchars((string) ($lang['sys.backup_default_plan'] ?? 'План по умолчанию')) ?>:
                                <strong><?= htmlspecialchars((string) ($summary['default_plan']['name'] ?? '')) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-box-archive"></i>&nbsp;<?= htmlspecialchars((string) ($lang['sys.backup_queue_run'] ?? 'Поставить резервную копию в очередь')) ?>
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-12 col-xl-7">
                <div class="card shadow-sm border h-100">
                    <div class="card-header"><strong><?= htmlspecialchars((string) ($lang['sys.backup_storage_summary'] ?? 'Локальное хранилище и worker')) ?></strong></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="small text-muted mb-1"><?= htmlspecialchars((string) ($lang['sys.path'] ?? 'Путь')) ?></div>
                                <div class="small"><code><?= htmlspecialchars((string) ($summary['path'] ?? '')) ?></code></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="small text-muted mb-1"><?= htmlspecialchars((string) ($lang['sys.backup_default_remote_target'] ?? 'Профиль по умолчанию')) ?></div>
                                <div class="small">
                                    <?= htmlspecialchars((string) (($summary['default_target']['name'] ?? '') ?: ($lang['sys.no_data'] ?? 'Нет данных'))) ?>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="small text-muted mb-1"><?= htmlspecialchars((string) ($lang['sys.backup_local_storage_notice'] ?? 'Локальные снапшоты автоматически очищаются по retention-политике.')) ?></div>
                                <div class="small">
                                    <?= (int) ($summary['retention_days'] ?? 0) ?> <?= htmlspecialchars((string) ($lang['sys.cron_agent_runs_retention_days'] ?? 'дн.')) ?>,
                                    <?= (int) ($summary['max_local_snapshots'] ?? 0) ?> <?= htmlspecialchars((string) ($lang['sys.backup_local_snapshots_limit'] ?? 'последних снапшотов')) ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($localItems !== []): ?>
                            <hr>
                            <div class="small text-muted mb-2"><?= htmlspecialchars((string) ($lang['sys.backup_recent_local_snapshots'] ?? 'Последние локальные снапшоты')) ?></div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th><?= htmlspecialchars((string) ($lang['sys.name'] ?? 'Название')) ?></th>
                                            <th><?= htmlspecialchars((string) ($lang['sys.last_update'] ?? 'Последнее обновление')) ?></th>
                                            <th>DB</th>
                                            <th>Files</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($localItems as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string) ($item['name'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars((string) ($item['updated_at'] ?? '')) ?></td>
                                                <td class="small"><?= htmlspecialchars((string) (($item['db_archive'] ?? '') ?: '-')) ?></td>
                                                <td class="small"><?= htmlspecialchars((string) (($item['files_archive'] ?? '') ?: '-')) ?></td>
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

        <div class="card shadow-sm border mb-4">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <strong><?= htmlspecialchars((string) ($lang['sys.backup_plans'] ?? 'Планы резервного копирования')) ?></strong>
                <a href="/admin/backup_plan_edit/id/0" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-plus"></i>&nbsp;<?= htmlspecialchars((string) ($lang['sys.backup_plan_new'] ?? 'Добавить backup-план')) ?>
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($plans)): ?>
                    <div class="text-muted"><?= htmlspecialchars((string) ($lang['sys.no_data'] ?? 'Нет данных')) ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.name'] ?? 'Название')) ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.backup_database_contents'] ?? 'База данных')) ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.backup_files_contents'] ?? 'Файлы')) ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.backup_delivery_mode'] ?? 'Куда отправлять')) ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.status'] ?? 'Статус')) ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.actions'] ?? 'Действия')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plans as $plan): ?>
                                    <tr>
                                        <td><?= (int) ($plan['backup_plan_id'] ?? 0) ?></td>
                                        <td>
                                            <div><strong><?= htmlspecialchars((string) ($plan['name'] ?? '')) ?></strong></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string) ($plan['code'] ?? '')) ?></div>
                                            <?php if (!empty($plan['description'])): ?>
                                                <div class="small text-muted mt-1"><?= htmlspecialchars((string) ($plan['description'] ?? '')) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small"><?= htmlspecialchars((string) ($plan['db_summary'] ?? '')) ?></td>
                                        <td class="small"><?= htmlspecialchars((string) ($plan['file_summary'] ?? '')) ?></td>
                                        <td class="small"><?= htmlspecialchars((string) ($plan['delivery_summary'] ?? '')) ?></td>
                                        <td>
                                            <?php if (!empty($plan['is_default'])): ?>
                                                <div><span class="badge bg-primary"><?= htmlspecialchars((string) ($lang['sys.backup_target_default'] ?? 'По умолчанию')) ?></span></div>
                                            <?php endif; ?>
                                            <div class="mt-1">
                                                <span class="badge <?= !empty($plan['is_active']) ? 'bg-success' : 'bg-secondary' ?>">
                                                    <?= htmlspecialchars((string) (!empty($plan['is_active']) ? ($lang['sys.active'] ?? 'Активен') : ($lang['sys.disabled'] ?? 'Отключён'))) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <form method="post" action="<?= htmlspecialchars(\classes\system\CsrfService::appendToUrl('/admin/run_backup'), ENT_QUOTES, 'UTF-8') ?>" class="d-inline">
                                                    <input type="hidden" name="plan_id" value="<?= (int) ($plan['backup_plan_id'] ?? 0) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="<?= htmlspecialchars((string) ($lang['sys.run_backup'] ?? 'Создать резервную копию')) ?>">
                                                        <i class="fa-solid fa-play"></i>
                                                    </button>
                                                </form>
                                                <a href="/admin/backup_plan_edit/id/<?= (int) ($plan['backup_plan_id'] ?? 0) ?>" class="btn btn-sm btn-outline-primary" title="<?= htmlspecialchars((string) ($lang['sys.edit'] ?? 'Редактировать')) ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="<?= htmlspecialchars(\classes\system\CsrfService::appendToUrl('/admin/delete_backup_plan/id/' . (int) ($plan['backup_plan_id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('<?= htmlspecialchars((string) ($lang['sys.delete'] ?? 'Удалить'), ENT_QUOTES, 'UTF-8') ?>?');" class="btn btn-sm btn-outline-danger" title="<?= htmlspecialchars((string) ($lang['sys.delete'] ?? 'Удалить')) ?>">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm border mb-4">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <strong><?= htmlspecialchars((string) ($lang['sys.backup_remote_targets'] ?? 'Удалённые хранилища')) ?></strong>
                <a href="/admin/backup_target_edit/id/0" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-plus"></i>&nbsp;<?= htmlspecialchars((string) ($lang['sys.backup_target_new'] ?? 'Добавить удалённый хост')) ?>
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($targets)): ?>
                    <div class="text-muted"><?= htmlspecialchars((string) ($lang['sys.backup_remote_targets_empty'] ?? 'Удалённые профили ещё не настроены. Локальные backup-задачи уже работают без них.')) ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.name'] ?? 'Название')) ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.type'] ?? 'Тип')) ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.path'] ?? 'Путь')) ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.status'] ?? 'Статус')) ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.last_update'] ?? 'Последнее обновление')) ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.actions'] ?? 'Действия')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($targets as $target): ?>
                                    <tr>
                                        <td><?= (int) ($target['target_id'] ?? 0) ?></td>
                                        <td>
                                            <div><strong><?= htmlspecialchars((string) ($target['name'] ?? '')) ?></strong></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string) ($target['code'] ?? '')) ?></div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars((string) strtoupper((string) ($target['protocol'] ?? ''))) ?></div>
                                            <?php if (!empty($target['is_default'])): ?>
                                                <div class="small text-success"><?= htmlspecialchars((string) ($lang['sys.backup_target_default'] ?? 'По умолчанию')) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small"><?= htmlspecialchars((string) ($target['remote_label'] ?? '')) ?></td>
                                        <td>
                                            <span class="badge <?= !empty($target['is_active']) ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= htmlspecialchars((string) (!empty($target['is_active']) ? ($lang['sys.active'] ?? 'Активен') : ($lang['sys.disabled'] ?? 'Отключён'))) ?>
                                            </span>
                                            <?php if (!empty($target['last_test_status'])): ?>
                                                <div class="small text-muted mt-1"><?= htmlspecialchars((string) ($target['last_test_status'] ?? '')) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars((string) (($target['last_tested_at'] ?? '') ?: '-')) ?></div>
                                            <?php if (!empty($target['last_error_message'])): ?>
                                                <div class="small text-danger"><?= htmlspecialchars((string) ($target['last_error_message'] ?? '')) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <a href="/admin/backup_target_edit/id/<?= (int) ($target['target_id'] ?? 0) ?>" class="btn btn-sm btn-outline-primary" title="<?= htmlspecialchars((string) ($lang['sys.edit'] ?? 'Редактировать')) ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="<?= htmlspecialchars(\classes\system\CsrfService::appendToUrl('/admin/test_backup_target/id/' . (int) ($target['target_id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-success" title="<?= htmlspecialchars((string) ($lang['sys.backup_target_test'] ?? 'Проверить подключение')) ?>">
                                                    <i class="fa-solid fa-plug-circle-check"></i>
                                                </a>
                                                <a href="<?= htmlspecialchars(\classes\system\CsrfService::appendToUrl('/admin/delete_backup_target/id/' . (int) ($target['target_id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('<?= htmlspecialchars((string) ($lang['sys.delete'] ?? 'Удалить'), ENT_QUOTES, 'UTF-8') ?>?');" class="btn btn-sm btn-outline-danger" title="<?= htmlspecialchars((string) ($lang['sys.delete'] ?? 'Удалить')) ?>">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm border">
            <div class="card-header"><strong><?= htmlspecialchars((string) ($lang['sys.backup_recent_jobs'] ?? 'Последние backup-задачи')) ?></strong></div>
            <div class="card-body">
                <?php if (empty($jobs)): ?>
                    <div class="text-muted"><?= htmlspecialchars((string) ($lang['sys.no_data'] ?? 'Нет данных')) ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.name'] ?? 'Название')) ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.backup_plan'] ?? 'План')) ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.status'] ?? 'Статус')) ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.backup_delivery_mode'] ?? 'Куда отправлять')) ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.created'] ?? 'Создано')) ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.last_update'] ?? 'Последнее обновление')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $job): ?>
                                    <?php
                                    $status = (string) ($job['status'] ?? 'queued');
                                    $statusClass = match ($status) {
                                        'done' => 'bg-success',
                                        'partial' => 'bg-warning text-dark',
                                        'failed' => 'bg-danger',
                                        'running' => 'bg-primary',
                                        default => 'bg-secondary',
                                    };
                                    ?>
                                    <tr>
                                        <td><?= (int) ($job['backup_job_id'] ?? 0) ?></td>
                                        <td>
                                            <div><strong><?= htmlspecialchars((string) (($job['title'] ?? '') ?: ('#' . (int) ($job['backup_job_id'] ?? 0)))) ?></strong></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string) (($job['snapshot_name'] ?? '') ?: '-')) ?></div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars((string) (($job['plan_name'] ?? '') ?: '-')) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string) (($job['plan_code'] ?? '') ?: ($job['scope'] ?? ''))) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $statusClass ?>"><?= htmlspecialchars((string) ($statusLabels[$status] ?? $status)) ?></span>
                                            <?php if (!empty($job['last_error_message'])): ?>
                                                <div class="small text-danger mt-1"><?= htmlspecialchars((string) ($job['last_error_message'] ?? '')) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars((string) ($job['delivery_mode'] ?? 'local_only')) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string) (($job['target_name'] ?? '') ?: '-')) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars((string) ($job['created_at'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars((string) (($job['finished_at'] ?? '') ?: ($job['updated_at'] ?? ''))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
