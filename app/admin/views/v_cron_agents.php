<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$summary = is_array($cron_agents_summary ?? null) ? $cron_agents_summary : [];
$agents = is_array($cron_agents ?? null) ? $cron_agents : [];
$runs = is_array($cron_agent_runs ?? null) ? $cron_agent_runs : [];
$schedulerCommand = (string) ($summary['scheduler_command'] ?? ('php ' . ENV_SITE_PATH . 'app/cron/run.php'));
$autoCreatedAgents = is_array($summary['auto_created_agents'] ?? null) ? $summary['auto_created_agents'] : [];
?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3 flex-wrap gap-2">
            <h1 class="mb-0"><?= htmlspecialchars((string)($lang['sys.cron_agents'] ?? 'Cron-агенты')) ?></h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="/admin/run_cron_scheduler" class="btn btn-primary">
                    <i class="fa-solid fa-play"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.cron_agent_run_scheduler'] ?? 'Запустить scheduler')) ?>
                </a>
                <a href="/admin/recover_stale_cron_agents" class="btn btn-outline-warning">
                    <i class="fa-solid fa-arrows-rotate"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.cron_agent_recover_stale'] ?? 'Восстановить зависшие')) ?>
                </a>
                <a href="/admin/cron_agent_edit/id/0" class="btn btn-success">
                    <i class="fa-solid fa-plus"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.cron_agent_new'] ?? 'Новый cron-агент')) ?>
                </a>
            </div>
        </div>

        <div class="alert alert-warning shadow-sm mb-4">
            <div class="fw-bold mb-2"><?= htmlspecialchars((string)($lang['sys.cron_agent_bootstrap_notice'] ?? 'Для работы платформы нужен один системный cron и обязательные системные агенты.')) ?></div>
            <div class="mb-2"><?= htmlspecialchars((string)($lang['sys.cron_agent_scheduler_single_command'] ?? 'На сервере нужно настроить только одну команду минутного запуска:')) ?></div>
            <code><?= htmlspecialchars($schedulerCommand) ?></code>
            <?php if (!empty($autoCreatedAgents)): ?>
                <div class="small mt-3 mb-1"><?= htmlspecialchars((string)($lang['sys.cron_agent_auto_created_list'] ?? 'Автоматически создаваемые системные агенты:')) ?></div>
                <ul class="small mb-0">
                    <?php foreach ($autoCreatedAgents as $autoAgent): ?>
                        <li>
                            <strong><?= htmlspecialchars((string) ($autoAgent['title'] ?? ($autoAgent['code'] ?? ''))) ?></strong>
                            <span class="text-muted">(<?= htmlspecialchars((string) ($autoAgent['code'] ?? '')) ?>)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card shadow-sm border mb-4">
            <div class="card-body">
                <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.cron_agent_scheduler_help'] ?? 'Настройте системный cron на запуск этого скрипта каждую минуту:')) ?></div>
                <code><?= htmlspecialchars($schedulerCommand) ?></code>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <?php foreach ([
                ['key' => 'total', 'label' => $lang['sys.total'] ?? 'Всего'],
                ['key' => 'active', 'label' => $lang['sys.active'] ?? 'Активно'],
                ['key' => 'due', 'label' => $lang['sys.cron_agent_status_due'] ?? 'Готовы к запуску'],
                ['key' => 'locked', 'label' => $lang['sys.running'] ?? 'В работе'],
                ['key' => 'failed', 'label' => $lang['sys.failed'] ?? 'С ошибкой'],
            ] as $card): ?>
                <div class="col-12 col-md-6 col-xl-2">
                    <div class="card border shadow-sm h-100">
                        <div class="card-body">
                            <div class="small text-muted mb-1"><?= htmlspecialchars((string) $card['label']) ?></div>
                            <div class="h4 mb-0"><?= (int) ($summary[$card['key']] ?? 0) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="col-12 col-md-6 col-xl-2">
                <div class="card border shadow-sm h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.cron_agent_max_per_tick'] ?? 'Лимит задач за тик')) ?></div>
                        <div class="h4 mb-0"><?= (int) ($summary['config']['max_agents_per_tick'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <div class="card border shadow-sm h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.cron_agent_max_weight'] ?? 'Лимит нагрузки')) ?></div>
                        <div class="h4 mb-0"><?= (int) ($summary['config']['max_weight_per_tick'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <div class="card border shadow-sm h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.cron_agent_max_concurrent'] ?? 'Лимит одновременных задач')) ?></div>
                        <div class="h4 mb-0"><?= (int) ($summary['config']['max_concurrent'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><?= htmlspecialchars((string)($lang['sys.cron_agents'] ?? 'Cron-агенты')) ?></strong>
                <a href="/admin/cron_agent_runs" class="btn btn-sm btn-outline-secondary">
                    <?= htmlspecialchars((string)($lang['sys.cron_agent_runs'] ?? 'История запусков')) ?>
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($agents)): ?>
                    <div class="text-muted"><?= htmlspecialchars((string)($lang['sys.no_data'] ?? 'Нет данных')) ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th><?= htmlspecialchars((string)($lang['sys.name'] ?? 'Название')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.cron_agent_handler'] ?? 'Handler')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.cron_agent_schedule_mode'] ?? 'Расписание')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.status'] ?? 'Статус')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.cron_agent_next_run'] ?? 'Следующий запуск')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.cron_agent_last_run'] ?? 'Последний запуск')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.priority'] ?? 'Приоритет')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.actions'] ?? 'Действия')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agents as $agent): ?>
                                    <tr>
                                        <td><?= (int) ($agent['agent_id'] ?? 0) ?></td>
                                        <td>
                                            <div><strong><?= htmlspecialchars((string) (($agent['title'] ?? '') !== '' ? $agent['title'] : ($agent['code'] ?? ''))) ?></strong></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string) ($agent['code'] ?? '')) ?></div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars((string) ($agent['handler_title'] ?? ($agent['handler'] ?? ''))) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string) ($agent['handler'] ?? '')) ?></div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars((string) ($agent['schedule_label'] ?? '')) ?></div>
                                            <div class="small text-muted">
                                                <?php if (($agent['schedule_mode'] ?? '') === 'interval'): ?>
                                                    <?= (int) ($agent['interval_minutes'] ?? 0) ?> <?= htmlspecialchars((string)($lang['sys.cron_agent_interval_minutes'] ?? 'мин')) ?>
                                                <?php elseif (($agent['schedule_mode'] ?? '') === 'cron'): ?>
                                                    <code><?= htmlspecialchars((string) ($agent['cron_expression'] ?? '')) ?></code>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><span class="badge <?= htmlspecialchars((string) ($agent['runtime_status_class'] ?? 'bg-secondary')) ?>"><?= htmlspecialchars((string) ($agent['runtime_status_label'] ?? '')) ?></span></td>
                                        <td><?= !empty($agent['next_run_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $agent['next_run_at']))) : '<span class="text-muted">-</span>' ?></td>
                                        <td><?= !empty($agent['last_run_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $agent['last_run_at']))) : '<span class="text-muted">-</span>' ?></td>
                                        <td>
                                            <div><?= htmlspecialchars((string)($lang['sys.priority'] ?? 'Приоритет')) ?>: <?= (int) ($agent['priority'] ?? 0) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string)($lang['sys.cron_agent_weight'] ?? 'Вес')) ?>: <?= (int) ($agent['weight'] ?? 0) ?></div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <a href="/admin/cron_agent_edit/id/<?= (int) ($agent['agent_id'] ?? 0) ?>" class="btn btn-sm btn-outline-primary" title="<?= htmlspecialchars((string)($lang['sys.edit'] ?? 'Редактировать')) ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="/admin/run_cron_agent/id/<?= (int) ($agent['agent_id'] ?? 0) ?>" class="btn btn-sm btn-outline-success" title="<?= htmlspecialchars((string)($lang['sys.cron_agent_run_now'] ?? 'Запустить сейчас')) ?>">
                                                    <i class="fa-solid fa-play"></i>
                                                </a>
                                                <a href="/admin/toggle_cron_agent/id/<?= (int) ($agent['agent_id'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary" title="<?= htmlspecialchars((string)(!empty($agent['is_active']) ? ($lang['sys.cron_agent_disable'] ?? 'Отключить') : ($lang['sys.cron_agent_enable'] ?? 'Включить'))) ?>">
                                                    <i class="fa-solid <?= !empty($agent['is_active']) ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                                </a>
                                                <a href="/admin/cron_agent_runs/id/<?= (int) ($agent['agent_id'] ?? 0) ?>" class="btn btn-sm btn-outline-dark" title="<?= htmlspecialchars((string)($lang['sys.cron_agent_runs'] ?? 'История запусков')) ?>">
                                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                                </a>
                                                <a href="/admin/delete_cron_agent/id/<?= (int) ($agent['agent_id'] ?? 0) ?>" onclick="return confirm('<?= htmlspecialchars((string)($lang['sys.delete'] ?? 'Удалить'), ENT_QUOTES, 'UTF-8') ?>?');" class="btn btn-sm btn-outline-danger" title="<?= htmlspecialchars((string)($lang['sys.delete'] ?? 'Удалить')) ?>">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php if (!empty($agent['last_error_message'])): ?>
                                        <tr>
                                            <td></td>
                                            <td colspan="8" class="small text-danger"><?= htmlspecialchars((string) $agent['last_error_message']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm border">
            <div class="card-header"><strong><?= htmlspecialchars((string)($lang['sys.cron_agent_recent_runs'] ?? 'Последние запуски')) ?></strong></div>
            <div class="card-body">
                <?php if (empty($runs)): ?>
                    <div class="text-muted"><?= htmlspecialchars((string)($lang['sys.no_data'] ?? 'Нет данных')) ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th><?= htmlspecialchars((string)($lang['sys.cron_agent'] ?? 'Агент')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.cron_agent_trigger_source'] ?? 'Источник')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.status'] ?? 'Статус')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.created'] ?? 'Создано')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.cron_agent_duration_ms'] ?? 'Длительность, мс')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($runs as $run): ?>
                                    <tr>
                                        <td><?= (int) ($run['run_id'] ?? 0) ?></td>
                                        <td>
                                            <div><?= htmlspecialchars((string) ($run['agent_code'] ?? '')) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string) ($run['handler'] ?? '')) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars((string) ($run['trigger_source'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars((string) ($run['status'] ?? '')) ?></td>
                                        <td><?= !empty($run['started_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $run['started_at']))) : '' ?></td>
                                        <td><?= (int) ($run['duration_ms'] ?? 0) ?></td>
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
