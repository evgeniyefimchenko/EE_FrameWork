<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$plan = is_array($backup_plan ?? null) ? $backup_plan : [];
$targets = is_array($backup_targets ?? null) ? $backup_targets : [];
$dbTables = is_array($backup_db_tables ?? null) ? $backup_db_tables : [];
$fileItems = is_array($backup_file_items ?? null) ? $backup_file_items : [];
$selectedDbTables = array_fill_keys(array_values((array) ($plan['db_tables'] ?? [])), true);
$selectedFileItems = array_fill_keys(array_values((array) ($plan['file_items'] ?? [])), true);
?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3">
            <h1 class="mb-0"><?= htmlspecialchars((string) (($plan['backup_plan_id'] ?? 0) > 0 ? ($lang['sys.backup_plan_edit'] ?? 'Редактирование backup-плана') : ($lang['sys.backup_plan_new'] ?? 'Новый backup-план'))) ?></h1>
            <a href="/admin/backup" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left"></i>&nbsp;<?= htmlspecialchars((string) ($lang['sys.back'] ?? 'Назад')) ?>
            </a>
        </div>

        <form method="post" action="" class="card shadow-sm border">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-xl-6">
                        <label class="form-label"><?= htmlspecialchars((string) ($lang['sys.backup_plan_code'] ?? 'Код плана')) ?></label>
                        <input type="text" name="code" class="form-control" value="<?= htmlspecialchars((string) ($plan['code'] ?? '')) ?>" required>
                    </div>
                    <div class="col-12 col-xl-6">
                        <label class="form-label"><?= htmlspecialchars((string) ($lang['sys.name'] ?? 'Название')) ?></label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars((string) ($plan['name'] ?? '')) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= htmlspecialchars((string) ($lang['sys.description'] ?? 'Описание')) ?></label>
                        <textarea name="description" rows="3" class="form-control"><?= htmlspecialchars((string) ($plan['description'] ?? '')) ?></textarea>
                    </div>

                    <div class="col-12 col-xl-6">
                        <label class="form-label"><?= htmlspecialchars((string) ($lang['sys.backup_database_contents'] ?? 'Состав БД')) ?></label>
                        <select name="db_mode" class="form-select">
                            <?php foreach ([
                                'all' => $lang['sys.backup_mode_all_tables'] ?? 'Все таблицы',
                                'only_selected' => $lang['sys.backup_mode_only_selected_tables'] ?? 'Только выбранные таблицы',
                                'exclude_selected' => $lang['sys.backup_mode_exclude_selected_tables'] ?? 'Все таблицы кроме выбранных',
                                'none' => $lang['sys.backup_mode_no_database'] ?? 'Без базы данных',
                            ] as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= (string) ($plan['db_mode'] ?? 'all') === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?= htmlspecialchars((string) ($lang['sys.backup_tables_hint'] ?? 'Список таблиц используется для режимов "только выбранные" и "кроме выбранных".')) ?></div>
                    </div>

                    <div class="col-12 col-xl-6">
                        <label class="form-label"><?= htmlspecialchars((string) ($lang['sys.backup_files_contents'] ?? 'Состав файлов')) ?></label>
                        <select name="file_mode" class="form-select">
                            <?php foreach ([
                                'all' => $lang['sys.backup_mode_all_files'] ?? 'Все доступные файлы и папки',
                                'only_selected' => $lang['sys.backup_mode_only_selected_files'] ?? 'Только выбранные файлы и папки',
                                'exclude_selected' => $lang['sys.backup_mode_exclude_selected_files'] ?? 'Все файлы и папки кроме выбранных',
                                'none' => $lang['sys.backup_mode_no_files'] ?? 'Без файловой части',
                            ] as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= (string) ($plan['file_mode'] ?? 'exclude_selected') === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?= htmlspecialchars((string) ($lang['sys.backup_files_hint'] ?? 'Логи и кэш можно исключать из полного snapshot-плана, а uploads включать или отключать осознанно.')) ?></div>
                    </div>

                    <div class="col-12 col-xl-6">
                        <label class="form-label"><?= htmlspecialchars((string) ($lang['sys.backup_tables'] ?? 'Таблицы БД')) ?></label>
                        <select name="db_tables[]" class="form-select" size="14" multiple>
                            <?php foreach ($dbTables as $tableName): ?>
                                <option value="<?= htmlspecialchars((string) $tableName) ?>" <?= isset($selectedDbTables[(string) $tableName]) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $tableName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-xl-6">
                        <label class="form-label"><?= htmlspecialchars((string) ($lang['sys.backup_files_and_folders'] ?? 'Файлы и папки')) ?></label>
                        <select name="file_items[]" class="form-select" size="14" multiple>
                            <?php foreach ($fileItems as $item): ?>
                                <?php $code = (string) ($item['code'] ?? ''); ?>
                                <option value="<?= htmlspecialchars($code) ?>" <?= isset($selectedFileItems[$code]) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) (($item['label'] ?? $code) . ' [' . ($item['path_label'] ?? '') . ']')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-xl-6">
                        <label class="form-label"><?= htmlspecialchars((string) ($lang['sys.backup_delivery_mode'] ?? 'Куда отправлять')) ?></label>
                        <select name="delivery_mode" class="form-select">
                            <option value="local_only" <?= (string) ($plan['delivery_mode'] ?? 'local_only') === 'local_only' ? 'selected' : '' ?>><?= htmlspecialchars((string) ($lang['sys.backup_delivery_local_only'] ?? 'Только локально')) ?></option>
                            <option value="local_and_remote" <?= (string) ($plan['delivery_mode'] ?? '') === 'local_and_remote' ? 'selected' : '' ?>><?= htmlspecialchars((string) ($lang['sys.backup_delivery_local_and_remote'] ?? 'Локально и на удалённый хост')) ?></option>
                            <option value="remote_required" <?= (string) ($plan['delivery_mode'] ?? '') === 'remote_required' ? 'selected' : '' ?>><?= htmlspecialchars((string) ($lang['sys.backup_delivery_remote_required'] ?? 'Удалённый хост обязателен')) ?></option>
                        </select>
                    </div>

                    <div class="col-12 col-xl-6">
                        <label class="form-label"><?= htmlspecialchars((string) ($lang['sys.backup_remote_target'] ?? 'Удалённый хост')) ?></label>
                        <select name="target_id" class="form-select">
                            <option value="0"><?= htmlspecialchars((string) ($lang['sys.backup_remote_target_default'] ?? 'Использовать профиль по умолчанию')) ?></option>
                            <?php foreach ($targets as $target): ?>
                                <option value="<?= (int) ($target['target_id'] ?? 0) ?>" <?= (int) ($plan['target_id'] ?? 0) === (int) ($target['target_id'] ?? 0) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) (($target['name'] ?? '') . ' [' . strtoupper((string) ($target['protocol'] ?? '')) . ']')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="backup-plan-active" name="is_active" value="1" <?= !empty($plan['is_active']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="backup-plan-active"><?= htmlspecialchars((string) ($lang['sys.active'] ?? 'Активен')) ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="backup-plan-default" name="is_default" value="1" <?= !empty($plan['is_default']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="backup-plan-default"><?= htmlspecialchars((string) ($lang['sys.backup_plan_default'] ?? 'План по умолчанию')) ?></label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="/admin/backup" class="btn btn-outline-secondary"><?= htmlspecialchars((string) ($lang['sys.cancel'] ?? 'Отмена')) ?></a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>&nbsp;<?= htmlspecialchars((string) ($lang['sys.save'] ?? 'Сохранить')) ?>
                </button>
            </div>
        </form>
    </div>
</main>
