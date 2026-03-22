<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$agent = is_array($cron_agent ?? null) ? $cron_agent : [];
$handlers = is_array($cron_handlers ?? null) ? $cron_handlers : [];
$summary = is_array($cron_agent_summary ?? null) ? $cron_agent_summary : [];
$schedulerCommand = (string) ($summary['scheduler_command'] ?? ('php ' . ENV_SITE_PATH . 'app/cron/run.php'));
$autoCreatedAgents = is_array($summary['auto_created_agents'] ?? null) ? $summary['auto_created_agents'] : [];
$isNew = (int) ($agent['agent_id'] ?? 0) <= 0;
?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3 flex-wrap gap-2">
            <h1 class="mb-0"><?= htmlspecialchars((string)($isNew ? ($lang['sys.cron_agent_new'] ?? 'Новый cron-агент') : ($lang['sys.cron_agent_edit'] ?? 'Редактирование cron-агента'))) ?></h1>
            <div class="d-flex flex-wrap gap-2">
                <a href="/admin/cron_agents" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.list'] ?? 'Список')) ?>
                </a>
                <?php if (!$isNew): ?>
                    <a href="/admin/cron_agent_runs/id/<?= (int) ($agent['agent_id'] ?? 0) ?>" class="btn btn-outline-dark">
                        <i class="fa-solid fa-clock-rotate-left"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.cron_agent_runs'] ?? 'История запусков')) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="alert alert-warning shadow-sm mb-4">
            <div class="fw-bold mb-2"><?= htmlspecialchars((string)($lang['sys.cron_agent_bootstrap_notice'] ?? 'Для работы платформы нужен один системный cron и обязательные системные агенты.')) ?></div>
            <div class="mb-2"><?= htmlspecialchars((string)($lang['sys.cron_agent_scheduler_single_command'] ?? 'На сервере нужно настроить только одну команду минутного запуска:')) ?></div>
            <code><?= htmlspecialchars($schedulerCommand) ?></code>
            <?php if (!empty($autoCreatedAgents)): ?>
                <div class="small mt-3"><?= htmlspecialchars((string)($lang['sys.cron_agent_auto_created_hint'] ?? 'Обязательные системные агенты создаются автоматически при развёртывании и первом bootstrap приложения.')) ?></div>
            <?php endif; ?>
        </div>

        <form method="post" class="card shadow-sm border">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.cron_agent_code'] ?? 'Код')) ?></label>
                        <input type="text" class="form-control" name="code" value="<?= htmlspecialchars((string)($agent['code'] ?? '')) ?>" placeholder="property-lifecycle-next" required>
                    </div>
                    <div class="col-12 col-lg-5">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.name'] ?? 'Название')) ?></label>
                        <input type="text" class="form-control" name="title" value="<?= htmlspecialchars((string)($agent['title'] ?? '')) ?>">
                    </div>
                    <div class="col-12 col-lg-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="cron-agent-active" name="is_active" value="1" <?= !empty($agent['is_active']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="cron-agent-active"><?= htmlspecialchars((string)($lang['sys.active'] ?? 'Активен')) ?></label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.description'] ?? 'Описание')) ?></label>
                        <textarea class="form-control" name="description" rows="2"><?= htmlspecialchars((string)($agent['description'] ?? '')) ?></textarea>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.cron_agent_handler'] ?? 'Handler')) ?></label>
                        <select class="form-select" name="handler" id="cron-agent-handler" required>
                            <?php foreach ($handlers as $handlerCode => $handlerMeta): ?>
                                <option value="<?= htmlspecialchars((string) $handlerCode) ?>" <?= (string)($agent['handler'] ?? '') === (string)$handlerCode ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) ($handlerMeta['title'] ?? $handlerCode)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text" id="cron-agent-handler-description"></div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.cron_agent_schedule_mode'] ?? 'Режим расписания')) ?></label>
                        <select class="form-select" name="schedule_mode" id="cron-agent-schedule-mode">
                            <option value="interval" <?= (string)($agent['schedule_mode'] ?? 'interval') === 'interval' ? 'selected' : '' ?>><?= htmlspecialchars((string)($lang['sys.cron_agent_schedule_interval'] ?? 'Интервал')) ?></option>
                            <option value="cron" <?= (string)($agent['schedule_mode'] ?? '') === 'cron' ? 'selected' : '' ?>><?= htmlspecialchars((string)($lang['sys.cron_agent_schedule_cron'] ?? 'Cron-выражение')) ?></option>
                            <option value="manual" <?= (string)($agent['schedule_mode'] ?? '') === 'manual' ? 'selected' : '' ?>><?= htmlspecialchars((string)($lang['sys.cron_agent_schedule_manual'] ?? 'Только вручную')) ?></option>
                        </select>
                    </div>

                    <div class="col-12 col-lg-4" data-schedule-mode="interval">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.cron_agent_interval_minutes'] ?? 'Интервал, минут')) ?></label>
                        <input type="number" min="1" class="form-control" name="interval_minutes" value="<?= (int)($agent['interval_minutes'] ?? 1) ?>">
                    </div>
                    <div class="col-12 col-lg-8" data-schedule-mode="cron">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.cron_agent_cron_expression'] ?? 'Cron-выражение')) ?></label>
                        <input type="text" class="form-control" name="cron_expression" value="<?= htmlspecialchars((string)($agent['cron_expression'] ?? '')) ?>" placeholder="*/5 * * * *">
                    </div>

                    <div class="col-12">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.cron_agent_payload_json'] ?? 'Payload JSON')) ?></label>
                        <textarea class="form-control font-monospace" name="payload_json" id="cron-agent-payload" rows="8"><?= htmlspecialchars((string)($agent['payload_json'] ?? '{}')) ?></textarea>
                        <div class="form-text"><?= htmlspecialchars((string)($lang['sys.cron_agent_payload_help'] ?? 'В payload храните только параметры запуска handler-а. Код обработчика живёт в проекте.')) ?></div>
                        <pre class="small mt-2 p-2 bg-light border rounded" id="cron-agent-payload-example"></pre>
                    </div>

                    <div class="col-12 col-lg-3">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.priority'] ?? 'Приоритет')) ?></label>
                        <input type="number" min="1" max="999" class="form-control" name="priority" value="<?= (int)($agent['priority'] ?? 100) ?>">
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.cron_agent_weight'] ?? 'Вес нагрузки')) ?></label>
                        <input type="number" min="1" max="100" class="form-control" name="weight" value="<?= (int)($agent['weight'] ?? 1) ?>">
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.cron_agent_max_runtime'] ?? 'Макс. время, сек')) ?></label>
                        <input type="number" min="10" class="form-control" name="max_runtime_sec" value="<?= (int)($agent['max_runtime_sec'] ?? 300) ?>">
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.cron_agent_lock_ttl'] ?? 'TTL блокировки, сек')) ?></label>
                        <input type="number" min="30" class="form-control" name="lock_ttl_sec" value="<?= (int)($agent['lock_ttl_sec'] ?? 360) ?>">
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.cron_agent_retry_delay'] ?? 'Задержка перед повтором, сек')) ?></label>
                        <input type="number" min="30" class="form-control" name="retry_delay_sec" value="<?= (int)($agent['retry_delay_sec'] ?? 300) ?>">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label"><?= htmlspecialchars((string)($lang['sys.cron_agent_next_run'] ?? 'Следующий запуск')) ?></label>
                        <input type="datetime-local" class="form-control" name="next_run_at" value="<?= htmlspecialchars((string)($agent['next_run_at_form'] ?? '')) ?>">
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="/admin/cron_agents" class="btn btn-outline-secondary"><?= htmlspecialchars((string)($lang['sys.cancel'] ?? 'Отмена')) ?></a>
                <button type="submit" class="btn btn-primary"><?= htmlspecialchars((string)($lang['sys.save'] ?? 'Сохранить')) ?></button>
            </div>
        </form>
    </div>
</main>

<script id="cron-agent-handlers-json" type="application/json"><?= json_encode($handlers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?></script>
<script>
(() => {
    const handlersNode = document.getElementById('cron-agent-handlers-json');
    const handlerSelect = document.getElementById('cron-agent-handler');
    const scheduleSelect = document.getElementById('cron-agent-schedule-mode');
    const descriptionNode = document.getElementById('cron-agent-handler-description');
    const payloadExampleNode = document.getElementById('cron-agent-payload-example');
    const handlers = handlersNode ? JSON.parse(handlersNode.textContent || '{}') : {};

    function refreshScheduleMode() {
        const mode = scheduleSelect ? scheduleSelect.value : 'interval';
        document.querySelectorAll('[data-schedule-mode]').forEach((node) => {
            const expected = node.getAttribute('data-schedule-mode');
            node.style.display = expected === mode ? '' : 'none';
        });
    }

    function refreshHandlerMeta() {
        const code = handlerSelect ? handlerSelect.value : '';
        const meta = handlers[code] || null;
        if (!meta) {
            if (descriptionNode) descriptionNode.textContent = '';
            if (payloadExampleNode) payloadExampleNode.textContent = '';
            return;
        }
        if (descriptionNode) {
            descriptionNode.textContent = meta.description || '';
        }
        if (payloadExampleNode) {
            payloadExampleNode.textContent = meta.payload_example_pretty || '{}';
        }
    }

    if (scheduleSelect) {
        scheduleSelect.addEventListener('change', refreshScheduleMode);
    }
    if (handlerSelect) {
        handlerSelect.addEventListener('change', refreshHandlerMeta);
    }
    refreshScheduleMode();
    refreshHandlerMeta();
})();
</script>
