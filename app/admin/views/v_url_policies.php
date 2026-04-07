<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php $policies = is_array($url_policies ?? null) ? $url_policies : []; ?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3 flex-wrap gap-2">
            <h1 class="mb-0"><?= htmlspecialchars((string)($lang['sys.url_policies'] ?? 'URL-политики')) ?></h1>
            <div class="d-flex gap-2 flex-wrap">
                <a href="/admin/url_policy_edit/id/0?entity_type=category" class="btn btn-outline-primary">
                    <i class="fa-solid fa-folder-tree"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.url_policy_new_category'] ?? 'Новая политика категорий')) ?>
                </a>
                <a href="/admin/url_policy_edit/id/0?entity_type=page" class="btn btn-primary">
                    <i class="fa-solid fa-file-lines"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.url_policy_new_page'] ?? 'Новая политика страниц')) ?>
                </a>
            </div>
        </div>

        <div class="alert alert-info shadow-sm mb-4">
            <?= htmlspecialchars((string)($lang['sys.url_policies_help'] ?? 'URL-политики определяют, как ядро строит новые slug: по заголовку или source slug, с какими заменами, стоп-словами и ограничением длины.')) ?>
        </div>

        <div class="card shadow-sm border">
            <div class="card-body">
                <?php if (empty($policies)): ?>
                    <div class="text-muted"><?= htmlspecialchars((string)($lang['sys.no_data'] ?? 'Нет данных')) ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th><?= htmlspecialchars((string)($lang['sys.name'] ?? 'Название')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.entity'] ?? 'Сущность')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.language'] ?? 'Язык')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.status'] ?? 'Статус')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.url_policy_rules'] ?? 'Правила')) ?></th>
                                    <th><?= htmlspecialchars((string)($lang['sys.actions'] ?? 'Действия')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($policies as $policy): ?>
                                    <?php $settings = is_array($policy['settings'] ?? null) ? $policy['settings'] : []; ?>
                                    <tr>
                                        <td><?= (int)($policy['policy_id'] ?? 0) ?></td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars((string)($policy['name'] ?? '')) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string)($policy['code'] ?? '')) ?></div>
                                            <?php if (!empty($policy['is_default'])): ?>
                                                <span class="badge bg-success mt-1"><?= htmlspecialchars((string)($lang['sys.default'] ?? 'По умолчанию')) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars((string)($policy['entity_type'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars((string)(($policy['language_code'] ?? '') !== '' ? $policy['language_code'] : ($lang['sys.all_languages'] ?? 'Все языки'))) ?></td>
                                        <td><?= htmlspecialchars((string)($policy['status'] ?? 'active')) ?></td>
                                        <td class="small">
                                            <div><?= htmlspecialchars((string)($lang['sys.url_policy_source_mode'] ?? 'Источник')) ?>: <strong><?= htmlspecialchars((string)($settings['source_mode'] ?? 'title')) ?></strong></div>
                                            <div><?= htmlspecialchars((string)($lang['sys.url_policy_max_length'] ?? 'Макс. длина')) ?>: <strong><?= (int)($settings['max_length'] ?? 190) ?></strong></div>
                                            <div><?= htmlspecialchars((string)($lang['sys.url_policy_stop_words'] ?? 'Стоп-слова')) ?>: <strong><?= count((array)($settings['stop_words'] ?? [])) ?></strong></div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <a href="/admin/url_policy_edit/id/<?= (int)($policy['policy_id'] ?? 0) ?>" class="btn btn-sm btn-outline-primary" title="<?= htmlspecialchars((string)($lang['sys.edit'] ?? 'Редактировать')) ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if (empty($policy['is_default'])): ?>
                                                    <a href="<?= htmlspecialchars(\classes\system\CsrfService::appendToUrl('/admin/delete_url_policy/id/' . (int)($policy['policy_id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('<?= htmlspecialchars((string)($lang['sys.delete'] ?? 'Удалить'), ENT_QUOTES, 'UTF-8') ?>?');" class="btn btn-sm btn-outline-danger" title="<?= htmlspecialchars((string)($lang['sys.delete'] ?? 'Удалить')) ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small"><?= htmlspecialchars((string)($lang['sys.url_policy_default_locked'] ?? 'Default policy cannot be deleted')) ?></span>
                                                <?php endif; ?>
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
