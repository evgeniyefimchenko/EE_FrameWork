<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php $redirect = is_array($redirect_item ?? null) ? $redirect_item : []; ?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3 flex-wrap gap-2">
            <h1 class="mb-0">
                <?= htmlspecialchars((string)((int)($redirect['redirect_id'] ?? 0) > 0 ? ($lang['sys.redirect_edit'] ?? 'Редактирование редиректа') : ($lang['sys.redirect_new'] ?? 'Новый редирект'))) ?>
            </h1>
            <a href="/admin/redirects" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.back'] ?? 'Назад')) ?>
            </a>
        </div>

        <form method="post" class="card shadow-sm border">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <label for="source_host" class="form-label"><?= htmlspecialchars((string)($lang['sys.redirect_source_host'] ?? 'Источник: host')) ?></label>
                        <input type="text" class="form-control" id="source_host" name="source_host" value="<?= htmlspecialchars((string)($redirect['source_host'] ?? '')) ?>" placeholder="<?= htmlspecialchars((string)($lang['sys.any_host'] ?? 'Любой host')) ?>">
                    </div>
                    <div class="col-12 col-lg-5">
                        <label for="source_path" class="form-label"><?= htmlspecialchars((string)($lang['sys.redirect_source_path'] ?? 'Источник: path')) ?></label>
                        <input type="text" class="form-control" id="source_path" name="source_path" required value="<?= htmlspecialchars((string)($redirect['source_path'] ?? '')) ?>" placeholder="/old-path">
                    </div>
                    <div class="col-12 col-lg-3">
                        <label for="language_code" class="form-label"><?= htmlspecialchars((string)($lang['sys.language'] ?? 'Язык')) ?></label>
                        <input type="text" class="form-control" id="language_code" name="language_code" value="<?= htmlspecialchars((string)($redirect['language_code'] ?? '')) ?>" placeholder="<?= htmlspecialchars((string)($lang['sys.all_languages'] ?? 'Все языки')) ?>">
                    </div>
                    <div class="col-12 col-lg-4">
                        <label for="target_type" class="form-label"><?= htmlspecialchars((string)($lang['sys.redirect_target_type'] ?? 'Тип назначения')) ?></label>
                        <select class="form-select" id="target_type" name="target_type">
                            <option value="path" <?= (string)($redirect['target_type'] ?? 'path') === 'path' ? 'selected' : '' ?>><?= htmlspecialchars((string)($lang['sys.redirect_target_type_path'] ?? 'Путь')) ?></option>
                            <option value="entity" <?= (string)($redirect['target_type'] ?? '') === 'entity' ? 'selected' : '' ?>><?= htmlspecialchars((string)($lang['sys.redirect_target_type_entity'] ?? 'Сущность')) ?></option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-5">
                        <label for="target_path" class="form-label"><?= htmlspecialchars((string)($lang['sys.redirect_target_path'] ?? 'Целевой path')) ?></label>
                        <input type="text" class="form-control" id="target_path" name="target_path" value="<?= htmlspecialchars((string)($redirect['target_path'] ?? '')) ?>" placeholder="/new-path">
                    </div>
                    <div class="col-12 col-lg-3">
                        <label for="http_code" class="form-label"><?= htmlspecialchars((string)($lang['sys.redirect_http_code'] ?? 'HTTP-код')) ?></label>
                        <select class="form-select" id="http_code" name="http_code">
                            <?php foreach ([301, 302, 307, 308] as $httpCode): ?>
                                <option value="<?= $httpCode ?>" <?= (int)($redirect['http_code'] ?? 301) === $httpCode ? 'selected' : '' ?>><?= $httpCode ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label for="target_entity_type" class="form-label"><?= htmlspecialchars((string)($lang['sys.redirect_target_entity_type'] ?? 'Тип сущности')) ?></label>
                        <select class="form-select" id="target_entity_type" name="target_entity_type">
                            <option value="page" <?= (string)($redirect['target_entity_type'] ?? 'page') === 'page' ? 'selected' : '' ?>><?= htmlspecialchars((string)($lang['sys.pages'] ?? 'Страницы')) ?></option>
                            <option value="category" <?= (string)($redirect['target_entity_type'] ?? '') === 'category' ? 'selected' : '' ?>><?= htmlspecialchars((string)($lang['sys.categories'] ?? 'Категории')) ?></option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label for="target_entity_id" class="form-label"><?= htmlspecialchars((string)($lang['sys.redirect_target_entity_id'] ?? 'ID сущности')) ?></label>
                        <input type="number" min="0" class="form-control" id="target_entity_id" name="target_entity_id" value="<?= (int)($redirect['target_entity_id'] ?? 0) ?>">
                    </div>
                    <div class="col-12 col-lg-3">
                        <label for="status" class="form-label"><?= htmlspecialchars((string)($lang['sys.status'] ?? 'Статус')) ?></label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?= (string)($redirect['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>active</option>
                            <option value="disabled" <?= (string)($redirect['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>disabled</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-3">
                        <label for="conflict_policy" class="form-label"><?= htmlspecialchars((string)($lang['sys.import_redirect_conflict_policy'] ?? 'Политика конфликта')) ?></label>
                        <select class="form-select" id="conflict_policy" name="conflict_policy">
                            <option value="skip_existing"><?= htmlspecialchars((string)($lang['sys.import_redirect_conflict_skip'] ?? 'Оставлять существующий редирект')) ?></option>
                            <option value="replace_existing"><?= htmlspecialchars((string)($lang['sys.import_redirect_conflict_replace'] ?? 'Перезаписывать существующий редирект')) ?></option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_auto" name="is_auto" <?= !empty($redirect['is_auto']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_auto"><?= htmlspecialchars((string)($lang['sys.auto'] ?? 'Авто')) ?></label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="note" class="form-label"><?= htmlspecialchars((string)($lang['sys.note'] ?? 'Примечание')) ?></label>
                        <textarea class="form-control" id="note" name="note" rows="3"><?= htmlspecialchars((string)($redirect['note'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.save'] ?? 'Сохранить')) ?>
                </button>
                <a href="/admin/redirects" class="btn btn-outline-secondary"><?= htmlspecialchars((string)($lang['sys.cancel'] ?? 'Отмена')) ?></a>
            </div>
        </form>
    </div>
</main>
