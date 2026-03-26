<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$runs = is_array($cron_agent_runs ?? null) ? $cron_agent_runs : [];
$agent = is_array($cron_agent ?? null) ? $cron_agent : null;
?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3 flex-wrap gap-2">
            <h1 class="mb-0">
                <?= htmlspecialchars((string)($lang['sys.cron_agent_runs'] ?? 'История запусков cron-агентов')) ?>
                <?php if ($agent): ?>
                    <span class="text-muted fs-6">/ <?= htmlspecialchars((string) (($agent['title'] ?? '') !== '' ? $agent['title'] : ($agent['code'] ?? ''))) ?></span>
                <?php endif; ?>
            </h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="/admin/cron_agents" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.cron_agents'] ?? 'Cron-агенты')) ?>
                </a>
                <?php if ($agent): ?>
                    <a href="/admin/cron_agent_edit/id/<?= (int) ($agent['agent_id'] ?? 0) ?>" class="btn btn-outline-primary">
                        <i class="fa-solid fa-gear"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.edit'] ?? 'Редактировать')) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm border">
            <div class="card-body">
                <?php if (empty($runs)): ?>
                    <div class="text-muted"><?= htmlspecialchars((string)($lang['sys.no_data'] ?? 'Нет данных')) ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th><?= htmlspecialchars((string)($lang['sys.cron_agent'] ?? 'Агент')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.cron_agent_trigger_source'] ?? 'Источник')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.status'] ?? 'Статус')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.cron_agent_worker_id'] ?? 'Worker')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.created'] ?? 'Создано')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.updated'] ?? 'Обновлено')) ?></th>
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
                                        <td class="small"><?= htmlspecialchars((string) ($run['worker_id'] ?? '')) ?></td>
                                        <td><?= !empty($run['started_at']) ? htmlspecialchars(ee_format_utc_datetime((string) $run['started_at'], 'd.m.Y H:i:s')) : '' ?></td>
                                        <td><?= !empty($run['finished_at']) ? htmlspecialchars(ee_format_utc_datetime((string) $run['finished_at'], 'd.m.Y H:i:s')) : '<span class="text-muted">-</span>' ?></td>
                                        <td><?= (int) ($run['duration_ms'] ?? 0) ?></td>
                                    </tr>
                                    <?php if (!empty($run['error_message']) || !empty($run['output_text'])): ?>
                                        <tr>
                                            <td></td>
                                            <td colspan="7">
                                                <?php if (!empty($run['error_message'])): ?>
                                                    <div class="small text-danger mb-1"><?= htmlspecialchars((string) $run['error_message']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($run['output_text'])): ?>
                                                    <pre class="small bg-light border rounded p-2 mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars((string) $run['output_text']) ?></pre>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
