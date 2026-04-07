<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php $redirects = is_array($redirects_list ?? null) ? $redirects_list : []; ?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3 flex-wrap gap-2">
            <h1 class="mb-0"><?= htmlspecialchars((string)($lang['sys.redirects'] ?? 'Редиректы')) ?></h1>
            <div class="d-flex gap-2 flex-wrap">
                <a href="/admin/redirect_edit/id/0" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.redirect_new'] ?? 'Новый редирект')) ?>
                </a>
            </div>
        </div>

        <div class="alert alert-info shadow-sm mb-4">
            <?= htmlspecialchars((string)($lang['sys.redirects_help'] ?? 'Здесь хранятся ручные и автоматически созданные редиректы. Они отрабатывают раньше semantic URL сущностей и позволяют безболезненно менять публичные маршруты.')) ?>
        </div>

        <div class="card shadow-sm border">
            <div class="card-body">
                <?php if (empty($redirects)): ?>
                    <div class="text-muted"><?= htmlspecialchars((string)($lang['sys.no_data'] ?? 'Нет данных')) ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th><?= htmlspecialchars((string)($lang['sys.redirect_source'] ?? 'Источник')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.redirect_target'] ?? 'Назначение')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.status'] ?? 'Статус')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.actions'] ?? 'Действия')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($redirects as $redirect): ?>
                                    <tr>
                                        <td><?= (int)($redirect['redirect_id'] ?? 0) ?></td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars((string)($redirect['source_path'] ?? '')) ?></div>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars((string)(($redirect['source_host'] ?? '') !== '' ? $redirect['source_host'] : ($lang['sys.any_host'] ?? 'Любой host'))) ?>
                                                <?php if (($redirect['language_code'] ?? '') !== ''): ?>
                                                    / <?= htmlspecialchars((string)$redirect['language_code']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars((string)($redirect['resolved_target_url'] ?? '')) ?></div>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars((string)($redirect['target_type'] ?? 'path')) ?>
                                                <?php if (!empty($redirect['is_auto'])): ?>
                                                    / <?= htmlspecialchars((string)($lang['sys.auto'] ?? 'Авто')) ?>
                                                <?php endif; ?>
                                                / <?= (int)($redirect['http_code'] ?? 301) ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars((string)($redirect['status'] ?? 'active')) ?></td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <a href="/admin/redirect_edit/id/<?= (int)($redirect['redirect_id'] ?? 0) ?>" class="btn btn-sm btn-outline-primary" title="<?= htmlspecialchars((string)($lang['sys.edit'] ?? 'Редактировать')) ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="<?= htmlspecialchars(\classes\system\CsrfService::appendToUrl('/admin/toggle_redirect/id/' . (int)($redirect['redirect_id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-secondary" title="<?= htmlspecialchars((string)($lang['sys.status'] ?? 'Статус')) ?>">
                                                    <i class="fa-solid <?= (string)($redirect['status'] ?? 'active') === 'active' ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                                </a>
                                                <a href="<?= htmlspecialchars(\classes\system\CsrfService::appendToUrl('/admin/delete_redirect/id/' . (int)($redirect['redirect_id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('<?= htmlspecialchars((string)($lang['sys.delete'] ?? 'Удалить'), ENT_QUOTES, 'UTF-8') ?>?');" class="btn btn-sm btn-outline-danger" title="<?= htmlspecialchars((string)($lang['sys.delete'] ?? 'Удалить')) ?>">
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
    </div>
</main>
