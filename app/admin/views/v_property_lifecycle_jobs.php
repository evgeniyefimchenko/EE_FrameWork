<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3">
            <h1 class="mb-0"><?= htmlspecialchars((string)($lang['sys.property_lifecycle_jobs'] ?? 'Задачи жизненного цикла')) ?></h1>
            <div class="d-flex gap-2">
                <a href="/admin/recover_stale_lifecycle_jobs" class="btn btn-outline-warning"><?= htmlspecialchars((string)($lang['sys.recover_stale_jobs'] ?? 'Восстановить зависшие задачи')) ?></a>
                <a href="/admin/run_property_lifecycle_job" class="btn btn-primary"><?= htmlspecialchars((string)($lang['sys.property_lifecycle_run_next'] ?? 'Запустить следующую задачу')) ?></a>
            </div>
        </div>

        <?php $summary = is_array($lifecycle_jobs_summary ?? null) ? $lifecycle_jobs_summary : []; ?>
        <div class="row g-3 mb-3">
            <?php foreach ([
                'queued' => $lang['sys.queued'] ?? 'В очереди',
                'running' => $lang['sys.running'] ?? 'В работе',
                'failed' => $lang['sys.failed'] ?? 'С ошибкой',
                'stale_running' => $lang['sys.stale_jobs'] ?? 'Зависшие',
            ] as $summaryKey => $summaryLabel): ?>
                <div class="col-12 col-md-3">
                    <div class="card border shadow-sm h-100">
                        <div class="card-body">
                            <div class="small text-muted mb-1"><?= htmlspecialchars((string) $summaryLabel) ?></div>
                            <div class="h4 mb-0"><?= (int) ($summary[$summaryKey] ?? 0) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (empty($lifecycle_jobs)): ?>
                    <div class="text-muted">Записей пока нет.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th><?= htmlspecialchars((string)($lang['sys.status'] ?? 'Статус')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.scope'] ?? 'Область')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.target'] ?? 'Цель')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.progress'] ?? 'Прогресс')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.created'] ?? 'Создано')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.updated'] ?? 'Обновлено')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.actions'] ?? 'Действия')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lifecycle_jobs as $job): ?>
                                    <tr>
                                        <td><?= (int) ($job['job_id'] ?? 0) ?></td>
                                        <td><?= htmlspecialchars((string) ($job['status'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars((string) ($job['scope'] ?? '')) ?></td>
                                        <td><?= (int) ($job['target_id'] ?? 0) ?></td>
                                        <td>
                                            <?= (int) ($job['processed_steps'] ?? 0) ?>/<?= (int) ($job['total_steps'] ?? 0) ?>
                                            (<?= htmlspecialchars((string) ($job['progress_percent'] ?? '0')) ?>%)
                                        </td>
                                        <td><?= !empty($job['created_at']) ? date('d.m.Y H:i', strtotime((string) $job['created_at'])) : '' ?></td>
                                        <td><?= !empty($job['updated_at']) ? date('d.m.Y H:i', strtotime((string) $job['updated_at'])) : '' ?></td>
                                        <td>
                                            <?php if (in_array((string) ($job['status'] ?? ''), ['queued', 'failed'], true)): ?>
                                                <a class="btn btn-sm btn-outline-primary" href="/admin/run_property_lifecycle_job/id/<?= (int) ($job['job_id'] ?? 0) ?>"><?= htmlspecialchars((string)($lang['sys.run'] ?? 'Запустить')) ?></a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if (!empty($job['error_message'])): ?>
                                        <tr>
                                            <td></td>
                                            <td colspan="7" class="text-danger small"><?= htmlspecialchars((string) $job['error_message']) ?></td>
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
