<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$policy = is_array($url_policy ?? null) ? $url_policy : [];
$settings = is_array($policy['settings'] ?? null) ? $policy['settings'] : [];
?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3 flex-wrap gap-2">
            <h1 class="mb-0">
                <?= htmlspecialchars((string)((int)($policy['policy_id'] ?? 0) > 0 ? ($lang['sys.url_policy_edit'] ?? 'Редактирование URL-политики') : ($lang['sys.url_policy_new'] ?? 'Новая URL-политика'))) ?>
            </h1>
            <a href="/admin/url_policies" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.back'] ?? 'Назад')) ?>
            </a>
        </div>

        <form method="post" class="card shadow-sm border">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label for="name" class="form-label"><?= htmlspecialchars((string)($lang['sys.name'] ?? 'Название')) ?></label>
                        <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars((string)($policy['name'] ?? '')) ?>">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label for="code" class="form-label"><?= htmlspecialchars((string)($lang['sys.code'] ?? 'Код')) ?></label>
                        <input type="text" class="form-control" id="code" name="code" value="<?= htmlspecialchars((string)($policy['code'] ?? '')) ?>">
                        <div class="form-text"><?= htmlspecialchars((string)($lang['sys.url_policy_code_help'] ?? 'Можно оставить пустым: код будет сформирован автоматически.')) ?></div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label for="entity_type" class="form-label"><?= htmlspecialchars((string)($lang['sys.entity'] ?? 'Сущность')) ?></label>
                        <select class="form-select" id="entity_type" name="entity_type">
                            <?php foreach (['category' => ($lang['sys.categories'] ?? 'Категории'), 'page' => ($lang['sys.pages'] ?? 'Страницы')] as $entityType => $entityLabel): ?>
                                <option value="<?= htmlspecialchars($entityType) ?>" <?= (string)($policy['entity_type'] ?? 'page') === $entityType ? 'selected' : '' ?>><?= htmlspecialchars((string)$entityLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label for="language_code" class="form-label"><?= htmlspecialchars((string)($lang['sys.language'] ?? 'Язык')) ?></label>
                        <input type="text" class="form-control" id="language_code" name="language_code" value="<?= htmlspecialchars((string)($policy['language_code'] ?? '')) ?>" maxlength="16" placeholder="<?= htmlspecialchars((string)($lang['sys.all_languages'] ?? 'Все языки')) ?>">
                        <div class="form-text"><?= htmlspecialchars((string)($lang['sys.url_policy_language_help'] ?? 'Оставьте пустым, чтобы политика применялась ко всем языкам контента.')) ?></div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label for="status" class="form-label"><?= htmlspecialchars((string)($lang['sys.status'] ?? 'Статус')) ?></label>
                        <select class="form-select" id="status" name="status">
                            <?php foreach (['active', 'hidden', 'disabled'] as $status): ?>
                                <option value="<?= htmlspecialchars($status) ?>" <?= (string)($policy['status'] ?? 'active') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label"><?= htmlspecialchars((string)($lang['sys.description'] ?? 'Описание')) ?></label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?= htmlspecialchars((string)($policy['description'] ?? '')) ?></textarea>
                    </div>
                </div>

                <hr>

                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <label for="source_mode" class="form-label"><?= htmlspecialchars((string)($lang['sys.url_policy_source_mode'] ?? 'Источник slug')) ?></label>
                        <select class="form-select" id="source_mode" name="source_mode">
                            <option value="title" <?= (string)($settings['source_mode'] ?? 'title') === 'title' ? 'selected' : '' ?>><?= htmlspecialchars((string)($lang['sys.url_policy_source_mode_title'] ?? 'По заголовку')) ?></option>
                            <option value="source_slug" <?= (string)($settings['source_mode'] ?? '') === 'source_slug' ? 'selected' : '' ?>><?= htmlspecialchars((string)($lang['sys.url_policy_source_mode_source_slug'] ?? 'По source slug')) ?></option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-4">
                        <label for="separator" class="form-label"><?= htmlspecialchars((string)($lang['sys.url_policy_separator'] ?? 'Разделитель')) ?></label>
                        <input type="text" class="form-control" id="separator" name="separator" maxlength="1" value="<?= htmlspecialchars((string)($settings['separator'] ?? '-')) ?>">
                    </div>
                    <div class="col-12 col-lg-4">
                        <label for="max_length" class="form-label"><?= htmlspecialchars((string)($lang['sys.url_policy_max_length'] ?? 'Максимальная длина')) ?></label>
                        <input type="number" min="10" max="190" class="form-control" id="max_length" name="max_length" value="<?= (int)($settings['max_length'] ?? 190) ?>">
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" id="transliterate" name="transliterate" <?= !empty($settings['transliterate']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="transliterate"><?= htmlspecialchars((string)($lang['sys.url_policy_transliterate'] ?? 'Транслитерировать в ASCII')) ?></label>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" id="lowercase" name="lowercase" <?= !empty($settings['lowercase']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="lowercase"><?= htmlspecialchars((string)($lang['sys.url_policy_lowercase'] ?? 'Приводить к нижнему регистру')) ?></label>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" id="is_default" name="is_default" <?= !empty($policy['is_default']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_default"><?= htmlspecialchars((string)($lang['sys.default'] ?? 'По умолчанию')) ?></label>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label for="fallback_slug" class="form-label"><?= htmlspecialchars((string)($lang['sys.url_policy_fallback_slug'] ?? 'Fallback slug')) ?></label>
                        <input type="text" class="form-control" id="fallback_slug" name="fallback_slug" value="<?= htmlspecialchars((string)($settings['fallback_slug'] ?? 'item')) ?>">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label for="reserved_words_extra" class="form-label"><?= htmlspecialchars((string)($lang['sys.url_policy_reserved_words_extra'] ?? 'Дополнительные зарезервированные слова')) ?></label>
                        <textarea class="form-control" id="reserved_words_extra" name="reserved_words_extra" rows="2" placeholder="catalog&#10;api"><?= htmlspecialchars(implode(PHP_EOL, (array)($settings['reserved_words_extra'] ?? []))) ?></textarea>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label for="stop_words" class="form-label"><?= htmlspecialchars((string)($lang['sys.url_policy_stop_words'] ?? 'Стоп-слова')) ?></label>
                        <textarea class="form-control" id="stop_words" name="stop_words" rows="4" placeholder="hotel&#10;guest-house"><?= htmlspecialchars(implode(PHP_EOL, (array)($settings['stop_words'] ?? []))) ?></textarea>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label for="replace_map" class="form-label"><?= htmlspecialchars((string)($lang['sys.url_policy_replace_map'] ?? 'Карта замен')) ?></label>
                        <textarea class="form-control" id="replace_map" name="replace_map" rows="4" placeholder="&amp;=and&#10;+ = plus"><?php
                            $replaceMapLines = [];
                            foreach ((array)($settings['replace_map'] ?? []) as $replaceKey => $replaceValue) {
                                $replaceMapLines[] = (string)$replaceKey . '=' . (string)$replaceValue;
                            }
                            echo htmlspecialchars(implode(PHP_EOL, $replaceMapLines));
                        ?></textarea>
                        <div class="form-text"><?= htmlspecialchars((string)($lang['sys.url_policy_replace_map_help'] ?? 'По одной строке: что_заменить=на_что_заменить')) ?></div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>&nbsp;<?= htmlspecialchars((string)($lang['sys.save'] ?? 'Сохранить')) ?>
                </button>
                <a href="/admin/url_policies" class="btn btn-outline-secondary"><?= htmlspecialchars((string)($lang['sys.cancel'] ?? 'Отмена')) ?></a>
            </div>
        </form>
    </div>
</main>
