<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$manifest = is_array($package_manifest ?? null) ? $package_manifest : [];
$catalog = is_array($source_catalog ?? null) ? $source_catalog : ['category_types' => [], 'property_sets' => []];
$typeCatalog = is_array($catalog['category_types'] ?? null) ? $catalog['category_types'] : [];
$setCatalog = is_array($catalog['property_sets'] ?? null) ? $catalog['property_sets'] : [];
$typeMap = is_array($current_type_map ?? null) ? $current_type_map : [];
$allowedSources = is_array($current_allowed_sources ?? null) ? $current_allowed_sources : [];
$allowedSourcesSet = array_fill_keys(array_map('strtolower', $allowedSources), true);
$isSourceEnabled = static function (string $sourceId) use ($allowedSourcesSet): bool {
    if (empty($allowedSourcesSet)) {
        return true;
    }
    return isset($allowedSourcesSet[strtolower(trim($sourceId))]);
};
$localTypeOptions = is_array($local_category_types ?? null) ? $local_category_types : [];
$localPropertyOptions = is_array($local_properties ?? null) ? $local_properties : [];
$propertyPreview = is_array($property_preview ?? null) ? $property_preview : ['rows' => [], 'total' => 0, 'truncated' => false, 'has_data' => false, 'meta_options' => []];
$propertyPreviewMetaOptions = is_array($propertyPreview['meta_options'] ?? null) ? $propertyPreview['meta_options'] : [];
$propertyFieldTypes = [];
if (class_exists('\\classes\\system\\Constants')) {
    $propertyFieldTypes = \classes\system\Constants::ALL_TYPE_PROPERTY_TYPES_FIELDS;
    if (!is_array($propertyFieldTypes)) {
        $propertyFieldTypes = [];
    }
}

$normalizeSourceDescription = static function (string $description): string {
    $description = trim($description);
    if ($description === '') {
        return '';
    }
    $normalized = preg_replace('/\s+/u', ' ', $description);
    $normalized = is_string($normalized) ? trim($normalized) : $description;
    if (function_exists('mb_strtolower')) {
        $normalized = mb_strtolower($normalized);
    } else {
        $normalized = strtolower($normalized);
    }
    if (in_array($normalized, ['description', 'description.', 'описание', 'n/a', '-', '--'], true)) {
        return '';
    }
    return $description;
};
$detectSourceKind = static function (string $sourceId, string $kindHint = ''): string {
    $kindHint = strtolower(trim($kindHint));
    if (in_array($kindHint, ['taxonomy', 'post_type'], true)) {
        return $kindHint;
    }
    $sourceId = strtolower(trim($sourceId));
    if (str_starts_with($sourceId, 'taxonomy:')) {
        return 'taxonomy';
    }
    if (str_starts_with($sourceId, 'post_type:')) {
        return 'post_type';
    }
    return '';
};
$detectLocalOptionKind = static function (string $name): string {
    $name = trim($name);
    if (function_exists('mb_strtolower')) {
        $name = mb_strtolower($name);
    } else {
        $name = strtolower($name);
    }
    if ($name === '') {
        return '';
    }
    if (str_starts_with($name, 'таксономия:') || str_starts_with($name, 'набор таксономии:') || str_starts_with($name, 'taxonomy:')) {
        return 'taxonomy';
    }
    if (str_starts_with($name, 'тип записи:') || str_starts_with($name, 'набор типа записи:') || str_starts_with($name, 'post type:') || str_starts_with($name, 'post_type:')) {
        return 'post_type';
    }
    return '';
};
$isCompatibleOptionKind = static function (string $sourceKind, string $optionKind): bool {
    return true;
};

$preparedLocalTypeOptions = [];
foreach ($localTypeOptions as $typeOption) {
    if (!is_array($typeOption)) {
        continue;
    }
    $optId = (int)($typeOption['id'] ?? 0);
    $optName = trim((string)($typeOption['name'] ?? ''));
    if ($optId <= 0 || $optName === '') {
        continue;
    }
    $preparedLocalTypeOptions[$optId] = [
        'id' => $optId,
        'name' => $optName,
        'kind' => $detectLocalOptionKind($optName),
    ];
}

$sourceOptions = [];
foreach ($typeCatalog as $item) {
    if (!is_array($item)) {
        continue;
    }
    $sourceId = strtolower(trim((string)($item['source_id'] ?? '')));
    if ($sourceId === '') {
        continue;
    }
    $sourceOptions[$sourceId] = [
        'source_id' => $sourceId,
        'name' => trim((string)($item['name'] ?? $sourceId)),
        'description' => $normalizeSourceDescription((string)($item['description'] ?? '')),
        'kind' => $detectSourceKind($sourceId, (string)($item['kind'] ?? '')),
    ];
}
foreach ($setCatalog as $item) {
    if (!is_array($item)) {
        continue;
    }
    $sourceId = strtolower(trim((string)($item['source_id'] ?? '')));
    if ($sourceId === '') {
        continue;
    }
    if (!isset($sourceOptions[$sourceId])) {
        $sourceOptions[$sourceId] = [
            'source_id' => $sourceId,
            'name' => trim((string)($item['name'] ?? $sourceId)),
            'description' => $normalizeSourceDescription((string)($item['description'] ?? '')),
            'kind' => $detectSourceKind($sourceId, (string)($item['kind'] ?? '')),
        ];
    } elseif ($sourceOptions[$sourceId]['description'] === '') {
        $sourceOptions[$sourceId]['description'] = $normalizeSourceDescription((string)($item['description'] ?? ''));
    }
}
ksort($sourceOptions);
$compositePropertyOptions = [];
foreach ($localPropertyOptions as $item) {
    if (!is_array($item)) {
        continue;
    }
    $propertyId = (int)($item['id'] ?? 0);
    $propertyName = trim((string)($item['name'] ?? ''));
    if ($propertyId <= 0 || $propertyName === '') {
        continue;
    }
    $propertyFields = [];
    $rawFields = is_array($item['fields'] ?? null) ? $item['fields'] : [];
    foreach ($rawFields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $fieldIndex = (int)($field['index'] ?? -1);
        if ($fieldIndex < 0) {
            continue;
        }
        $fieldTitle = trim((string)($field['title'] ?? ''));
        $fieldLabel = trim((string)($field['label'] ?? ''));
        $fieldType = strtolower(trim((string)($field['type'] ?? 'text')));
        if ($fieldType === '') {
            $fieldType = 'text';
        }
        $fieldName = trim((string)($field['name'] ?? ''));
        if ($fieldName === '') {
            if ($fieldTitle !== '') {
                $fieldName = $fieldTitle;
            } elseif ($fieldLabel !== '') {
                $fieldName = $fieldLabel;
            } else {
                $fieldName = 'Field #' . ($fieldIndex + 1);
            }
        }
        $propertyFields[] = [
            'index' => $fieldIndex,
            'name' => $fieldName,
            'title' => $fieldTitle,
            'label' => $fieldLabel,
            'type' => $fieldType,
        ];
    }
    $compositePropertyOptions[] = [
        'id' => $propertyId,
        'name' => $propertyName,
        'fields' => $propertyFields,
    ];
}
$wizardCoreCompletedAt = trim((string)($job['wizard_core_completed_at'] ?? ''));
$wizardContentCompletedAt = trim((string)($job['wizard_content_completed_at'] ?? ''));
$wizardCoreDone = $wizardCoreCompletedAt !== '';
$wizardContentDone = $wizardContentCompletedAt !== '';
$packageReady = (int)($job['file_id_package'] ?? 0) > 0;
$compositePropertiesMapRaw = trim((string)($job['composite_properties_map'] ?? '[]'));
$packageFilePath = trim((string)($package_file_path ?? ''));
$enabledSourcesCount = 0;
foreach ($sourceOptions as $sourceOption) {
    $sourceId = (string)($sourceOption['source_id'] ?? '');
    if ($sourceId !== '' && $isSourceEnabled($sourceId)) {
        $enabledSourcesCount++;
    }
}
$wizardStep1Done = $packageReady;
$wizardStep2Done = $packageReady && $enabledSourcesCount > 0;
$wizardStep3Done = $wizardCoreDone;
$wizardStep4Done = $wizardContentDone;
$cronImportCommand = trim((string)($cron_import_command ?? ''));
$cronAgentCreateLink = trim((string)($cron_agent_create_link ?? ''));
$importCronAgent = is_array($import_cron_agent ?? null) ? $import_cron_agent : null;
?>
<main>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <h1 class="mt-4">
                    <i class="fas fa-file-import"></i>
                    <?= htmlspecialchars((string) ($job_id > 0 ? ($lang['sys.import_profile_edit'] ?? 'Edit import profile') : ($lang['sys.import_profile_new'] ?? 'New import profile'))) ?>
                    <?= $job_id > 0 ? '(ID: ' . (int)$job_id . ')' : '' ?>
                </h1>
                <div class="mb-3">
                    <a class="btn btn-primary btn-sm" href="/admin/download_wp_adapter">
                        <i class="fas fa-download me-1"></i><?= htmlspecialchars((string)($lang['sys.download_wp_exporter'] ?? 'Download ee_wp_exporter.php')) ?>
                    </a>
                    <span class="small text-muted ms-2"><?= htmlspecialchars((string)($lang['sys.import_package_step0_short'] ?? 'Step 0: download the WordPress exporter')) ?></span>
                </div>

                <?php if ($job_id > 0): ?>
                    <div class="card border shadow-sm mb-4">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.cron_agents'] ?? 'Cron-агенты')) ?></div>
                                    <?php if ($importCronAgent): ?>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <strong><?= htmlspecialchars((string) (($importCronAgent['title'] ?? '') !== '' ? $importCronAgent['title'] : ($importCronAgent['code'] ?? ''))) ?></strong>
                                            <span class="badge <?= htmlspecialchars((string) ($importCronAgent['runtime_status_class'] ?? 'bg-secondary')) ?>"><?= htmlspecialchars((string) ($importCronAgent['runtime_status_label'] ?? '')) ?></span>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            <?= htmlspecialchars((string)($lang['sys.cron_agent_schedule_mode'] ?? 'Режим расписания')) ?>:
                                            <strong><?= htmlspecialchars((string) ($importCronAgent['schedule_human'] ?? '')) ?></strong>
                                        </div>
                                        <div class="small text-muted">
                                            <?= htmlspecialchars((string)($lang['sys.cron_agent_next_run'] ?? 'Следующий запуск')) ?>:
                                            <strong><?= !empty($importCronAgent['next_run_at']) ? htmlspecialchars(ee_format_utc_datetime((string) $importCronAgent['next_run_at'], 'd.m.Y H:i')) : '-' ?></strong>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted"><?= htmlspecialchars((string)($lang['sys.imports_cron_agent_missing'] ?? 'No linked cron agent has been created yet.')) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="/admin/sync_import_cron_agent/id/<?= (int)$job_id ?>" class="btn btn-sm btn-primary">
                                        <i class="fa-solid fa-rotate"></i> <?= htmlspecialchars((string)($lang['sys.imports_cron_agent_sync'] ?? 'Синхронизировать cron-агент')) ?>
                                    </a>
                                    <?php if ($importCronAgent): ?>
                                        <a href="/admin/cron_agent_edit/id/<?= (int) ($importCronAgent['agent_id'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fa-solid fa-gear"></i> <?= htmlspecialchars((string)($lang['sys.edit'] ?? 'Редактировать')) ?>
                                        </a>
                                        <a href="/admin/run_cron_agent/id/<?= (int) ($importCronAgent['agent_id'] ?? 0) ?>" class="btn btn-sm btn-outline-success">
                                            <i class="fa-solid fa-play"></i> <?= htmlspecialchars((string)($lang['sys.cron_agent_run_now'] ?? 'Запустить сейчас')) ?>
                                        </a>
                                        <a href="/admin/cron_agent_runs/id/<?= (int) ($importCronAgent['agent_id'] ?? 0) ?>" class="btn btn-sm btn-outline-dark">
                                            <i class="fa-solid fa-clock-rotate-left"></i> <?= htmlspecialchars((string)($lang['sys.cron_agent_runs'] ?? 'История запусков')) ?>
                                        </a>
                                        <a href="/admin/delete_import_cron_agent/id/<?= (int)$job_id ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= htmlspecialchars((string)($lang['sys.imports_delete_cron_agent_confirm'] ?? 'Delete the linked cron agent?'), ENT_QUOTES, 'UTF-8') ?>');">
                                            <i class="fa-solid fa-trash"></i> <?= htmlspecialchars((string)($lang['sys.delete'] ?? 'Удалить')) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php $importMediaQueue = is_array($import_media_queue ?? null) ? $import_media_queue : []; ?>
                    <div class="card border shadow-sm mb-4">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.media_queue'] ?? 'Очередь медиа')) ?></div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <strong><?= htmlspecialchars((string)($lang['sys.media_queue_worker'] ?? 'Системный агент media-mirror-worker')) ?></strong>
                                        <span class="badge bg-secondary"><?= htmlspecialchars((string)($importMediaQueue['agent_code'] ?? 'media-mirror-worker')) ?></span>
                                    </div>
                                    <div class="small text-muted mt-2">
                                        <?= htmlspecialchars((string)($lang['sys.media_queue_pending'] ?? 'Ожидают')) ?>:
                                        <strong><?= (int)($importMediaQueue['pending'] ?? 0) ?></strong>,
                                        <?= htmlspecialchars((string)($lang['sys.media_queue_running'] ?? 'В работе')) ?>:
                                        <strong><?= (int)($importMediaQueue['running'] ?? 0) ?></strong>,
                                        <?= htmlspecialchars((string)($lang['sys.media_queue_failed'] ?? 'С ошибкой')) ?>:
                                        <strong><?= (int)($importMediaQueue['failed'] ?? 0) ?></strong>,
                                        <?= htmlspecialchars((string)($lang['sys.media_queue_terminal_failed'] ?? 'Без повтора')) ?>:
                                        <strong><?= (int)($importMediaQueue['terminal_failed'] ?? 0) ?></strong>,
                                        <?= htmlspecialchars((string)($lang['sys.media_queue_done'] ?? 'Готово')) ?>:
                                        <strong><?= (int)($importMediaQueue['done'] ?? 0) ?></strong>
                                    </div>
                                    <div class="small text-muted">
                                        <?= htmlspecialchars((string)($lang['sys.media_queue_worker_notice'] ?? 'Фоновые медиа автоматически подхватывает встроенный системный агент, созданный при установке.')) ?>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="/admin/cron_agents" class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-robot"></i> <?= htmlspecialchars((string)($lang['sys.cron_agents'] ?? 'Cron-агенты')) ?>
                                    </a>
                                    <a href="/admin/health#media" class="btn btn-sm btn-outline-primary">
                                        <i class="fa-solid fa-photo-film"></i> <?= htmlspecialchars((string)($lang['sys.health'] ?? 'Состояние системы')) ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <h3><?= htmlspecialchars((string)($lang['sys.import_profile_settings'] ?? 'Profile settings')) ?></h3>

                <form id="import-settings-form" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="job_id" id="job_id" value="<?= (int)$job_id ?>">
                    <input type="hidden" name="file_id_package" id="file_id_package" value="<?= (int)($job['file_id_package'] ?? 0) ?>">
                    <input type="hidden" name="package_filename_hidden" id="package_filename_hidden" value="<?= htmlspecialchars((string)($job['package_filename'] ?? '')) ?>">
                    <input type="hidden" name="language_code" id="language_code" value="<?= htmlspecialchars((string)($job['language_code'] ?? '')) ?>">
                    <input type="hidden" id="max-file-size-bytes" value="<?= (int)($max_file_size_bytes ?? 0) ?>">
                    <input type="hidden" id="max-file-size-human" value="<?= htmlspecialchars((string)($max_file_size_human ?? 'N/A')) ?>">
                    <input type="hidden" name="wizard_core_completed_at" id="wizard_core_completed_at" value="<?= htmlspecialchars($wizardCoreCompletedAt) ?>">
                    <input type="hidden" name="wizard_content_completed_at" id="wizard_content_completed_at" value="<?= htmlspecialchars($wizardContentCompletedAt) ?>">
                    <input type="hidden" name="composite_properties_map" id="composite_properties_map" value="<?= htmlspecialchars($compositePropertiesMapRaw) ?>">
                    <input type="hidden" name="excluded_property_source_ids" id="excluded_property_source_ids" value="<?= htmlspecialchars((string)($job['excluded_property_source_ids'] ?? '')) ?>">

                    <div class="mb-3">
                        <label for="settings_name" class="form-label"><?= htmlspecialchars((string)($lang['sys.profile_name_required'] ?? 'Profile name*')) ?></label>
                        <input type="text" class="form-control" id="settings_name" name="settings_name" value="<?= htmlspecialchars((string)($job['settings_name'] ?? '')) ?>" required>
                        <div class="form-text"><?= htmlspecialchars((string)($lang['sys.entities_created_language'] ?? 'Language of created entities')) ?>: <strong><?= htmlspecialchars((string)($job['language_code'] ?? '')) ?></strong></div>
                    </div>

                    <div class="alert alert-light border mb-3">
                        <div class="fw-bold mb-2"><?= htmlspecialchars((string)($lang['sys.step_by_step_wizard'] ?? 'Step-by-step wizard')) ?></div>
                        <ol class="mb-0 small">
                            <li id="wizard-step1-item" class="<?= $wizardStep1Done ? 'text-success' : 'text-muted' ?>">
                                <i id="wizard-step1-check" class="<?= $wizardStep1Done ? 'fas fa-check-circle text-success' : 'far fa-circle text-muted' ?> me-1"></i>
                                <?= htmlspecialchars((string)($lang['sys.import_step_package_selected'] ?? 'Import package selected.')) ?>
                            </li>
                            <li id="wizard-step2-item" class="<?= $wizardStep2Done ? 'text-success' : 'text-muted' ?>">
                                <i id="wizard-step2-check" class="<?= $wizardStep2Done ? 'fas fa-check-circle text-success' : 'far fa-circle text-muted' ?> me-1"></i>
                                <?= htmlspecialchars((string)($lang['sys.import_step_sources_configured'] ?? 'Import sources configured.')) ?>
                            </li>
                            <li id="wizard-step3-item" class="<?= $wizardStep3Done ? 'text-success' : 'text-muted' ?>">
                                <i id="wizard-step3-check" class="<?= $wizardStep3Done ? 'fas fa-check-circle text-success' : 'far fa-circle text-muted' ?> me-1"></i>
                                <?= htmlspecialchars((string)($lang['sys.import_step_stage1_done'] ?? 'Stage 1 (property structure) completed.')) ?>
                            </li>
                            <li id="wizard-step4-item" class="<?= $wizardStep4Done ? 'text-success' : 'text-muted' ?>">
                                <i id="wizard-step4-check" class="<?= $wizardStep4Done ? 'fas fa-check-circle text-success' : 'far fa-circle text-muted' ?> me-1"></i>
                                <?= htmlspecialchars((string)($lang['sys.import_step_stage2_done'] ?? 'Stage 2 (categories and pages) completed.')) ?>
                            </li>
                        </ol>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-2"><?= htmlspecialchars((string)($lang['sys.import_package_title'] ?? 'Import package')) ?></h5>
                            <div class="mb-3">
                                <a class="btn btn-outline-primary btn-sm" href="/admin/download_wp_adapter">
                                    <i class="fas fa-download me-1"></i><?= htmlspecialchars((string)($lang['sys.download_wp_exporter'] ?? 'Download ee_wp_exporter.php')) ?>
                                </a>
                                <div class="form-text">
                                    <?= htmlspecialchars((string)($lang['sys.import_package_step0_help'] ?? 'Step 0. Download the exporter, run it on the WordPress site, and upload the resulting package below.')) ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="import_package_file" class="form-label fw-bold">
                                    <?= htmlspecialchars((string)(!empty($job['file_id_package']) ? ($lang['sys.import_package_replace_step'] ?? 'Step 1. Replace import package') : ($lang['sys.import_package_upload_step'] ?? 'Step 1. Upload import package'))) ?>
                                </label>
                                <input
                                    type="file"
                                    class="form-control"
                                    id="import_package_file"
                                    name="import_package_file"
                                    accept=".zip,.json,.jsonl,application/zip,application/json,text/plain"
                                    <?= empty($job['file_id_package']) ? 'required' : '' ?>
                                >
                                <div class="form-text">
                                    <?= htmlspecialchars((string)($lang['sys.import_package_upload_help'] ?? 'Supported: .zip, .json, .jsonl. Upload limit:')) ?>
                                    <strong><?= htmlspecialchars((string)($max_file_size_human ?? 'N/A')) ?></strong>.
                                    <?= htmlspecialchars((string)($lang['sys.import_package_upload_help_suffix'] ?? 'After selecting a file, click “Save profile”.')) ?>
                                </div>
                            </div>
                            <?php if (!empty($job['file_id_package']) && !empty($job['package_filename'])): ?>
                            <div class="form-text text-success">
                                <?= htmlspecialchars((string)($lang['sys.current_file'] ?? 'Current file')) ?>: <i class="fa fa-file-archive"></i> <?= htmlspecialchars((string)$job['package_filename']) ?>
                            </div>
                            <div class="form-text text-muted">
                                <?= htmlspecialchars((string)($lang['sys.full_path'] ?? 'Full path')) ?>: <code><?= htmlspecialchars($packageFilePath !== '' ? $packageFilePath : ($lang['sys.path_not_found'] ?? 'path not found')) ?></code>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0"><?= htmlspecialchars((string)($lang['sys.import_package_not_selected_for_profile'] ?? 'Import package is not selected for this profile.')) ?></div>
                        <?php endif; ?>
                    </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-2"><?= htmlspecialchars((string)($lang['sys.package_passport'] ?? 'Package passport')) ?></h5>
                            <?php if (!empty($manifest)): ?>
                                <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.import_format'] ?? 'Format')) ?>: <strong><?= htmlspecialchars((string)($manifest['format'] ?? ($lang['sys.not_specified'] ?? 'not specified'))) ?></strong></div>
                                <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.schema'] ?? 'Schema')) ?>: <strong><?= htmlspecialchars((string)($manifest['schema'] ?? ('v' . (string)($manifest['version'] ?? '1')))) ?></strong></div>
                                <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.source'] ?? 'Source')) ?>: <strong><?= htmlspecialchars((string)($manifest['source_system'] ?? ($lang['sys.not_specified'] ?? 'not specified'))) ?></strong></div>
                                <div class="small text-muted mb-1"><?= htmlspecialchars((string)($lang['sys.donor_site'] ?? 'Donor site')) ?>: <strong><?= htmlspecialchars((string)($manifest['site_url'] ?? ($lang['sys.not_specified'] ?? 'not specified'))) ?></strong></div>
                                <div class="small text-muted mb-0"><?= htmlspecialchars((string)($lang['sys.generated'] ?? 'Generated')) ?>: <strong><?= htmlspecialchars((string)($manifest['generated_at'] ?? ($lang['sys.not_specified'] ?? 'not specified'))) ?></strong></div>
                            <?php else: ?>
                                <div class="text-muted"><?= htmlspecialchars((string)($lang['sys.manifest_not_found_help'] ?? 'Manifest was not found. After uploading a ZIP with manifest.json, the source structure will appear here.')) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-2"><?= htmlspecialchars((string)($lang['sys.import_migration_contract_title'] ?? 'Миграционный контракт URL и ссылок')) ?></h5>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="preserve_source_paths" id="preserve_source_paths" <?= !empty($job['preserve_source_paths']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="preserve_source_paths">
                                    <?= htmlspecialchars((string)($lang['sys.import_preserve_source_paths'] ?? 'Сохранять исходные WP пути как public URL (route_path)')) ?>
                                </label>
                                <div class="form-text">
                                    <?= htmlspecialchars((string)($lang['sys.import_preserve_source_paths_help'] ?? 'Если включено, категории и страницы получают точный публичный путь сайта-донора. Это помогает сохранить ссылочную ценность при полном переезде.')) ?>
                                </div>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="rewrite_donor_links" id="rewrite_donor_links" <?= !empty($job['rewrite_donor_links']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="rewrite_donor_links">
                                    <?= htmlspecialchars((string)($lang['sys.import_rewrite_donor_links'] ?? 'Переписывать ссылки сайта-донора на локальный домен во время импорта')) ?>
                                </label>
                                <div class="form-text">
                                    <?= htmlspecialchars((string)($lang['sys.import_rewrite_donor_links_help'] ?? 'Абсолютные ссылки донора будут заменены на локальные URL. Для импортированных сущностей используется их новый public URL, для остальных ссылок сохраняется исходный path на текущем домене.')) ?>
                                </div>
                            </div>
                            <div class="mb-0">
                                <label for="donor_base_url" class="form-label"><?= htmlspecialchars((string)($lang['sys.import_donor_base_url'] ?? 'Базовый URL сайта-донора')) ?></label>
                                <input type="url" class="form-control" id="donor_base_url" name="donor_base_url" value="<?= htmlspecialchars((string)($job['donor_base_url'] ?? '')) ?>" placeholder="https://donor.example/">
                                <div class="form-text">
                                    <?= htmlspecialchars((string)($lang['sys.import_donor_base_url_help'] ?? 'Обычно заполняется автоматически из manifest.json. Используется для переписывания ссылок и распознавания source_path.')) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mb-3" id="wizard-step2-lock-hint" style="<?= $packageReady ? 'display:none;' : '' ?>">
                        <?= htmlspecialchars((string)($lang['sys.stage2_unlock_hint'] ?? 'Step 2 will become available after selecting an import package for this profile.')) ?>
                    </div>

                    <div id="wizard-step2-section" style="<?= $packageReady ? '' : 'display:none;' ?>">
                        <div class="d-flex align-items-center mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="test_mode" id="test_mode" <?= !empty($job['test_mode']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="test_mode"><?= htmlspecialchars((string)($lang['sys.test_mode'] ?? 'Test mode')) ?></label>
                            </div>
                            <div class="ms-3" id="test-mode-limit-group" style="<?= !empty($job['test_mode']) ? '' : 'display:none;' ?>">
                                <label for="test_mode_limit" class="form-label mb-0 me-2"><?= htmlspecialchars((string)($lang['sys.test_mode_limit_per_phase'] ?? 'Row limit per phase:')) ?></label>
                                <input type="number" min="1" max="500" class="form-control form-control-sm d-inline-block" style="width:90px;" id="test_mode_limit" name="test_mode_limit" value="<?= (int)($job['test_mode_limit'] ?? 5) ?>">
                            </div>
                        </div>

                        <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-2"><?= htmlspecialchars((string)($lang['sys.import_mapping_to_cms'] ?? 'Map import to CMS structure')) ?></h5>

                            <?php if (!empty($sourceOptions)): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold mb-2"><?= htmlspecialchars((string)($lang['sys.import_sources_step'] ?? 'Step 1. Select sources for import')) ?></label>
                                    <div class="small text-muted mb-2">
                                        <?= htmlspecialchars((string)($lang['sys.import_source_disabled_help'] ?? 'A disabled source is skipped completely: its categories/pages and meta will not be imported.')) ?>
                                    </div>
                                    <div class="border rounded p-2" style="max-height:220px; overflow:auto;">
                                        <?php foreach ($sourceOptions as $source): ?>
                                            <?php
                                            $sourceId = (string)$source['source_id'];
                                            $checked = empty($allowedSourcesSet) || isset($allowedSourcesSet[strtolower($sourceId)]);
                                            ?>
                                            <div class="form-check mb-1">
                                                <input
                                                    class="form-check-input js-source-enabled"
                                                    type="checkbox"
                                                    id="src-enable-<?= htmlspecialchars(md5($sourceId)) ?>"
                                                    data-source-id="<?= htmlspecialchars($sourceId) ?>"
                                                    <?= $checked ? 'checked' : '' ?>
                                                >
                                                <label class="form-check-label" for="src-enable-<?= htmlspecialchars(md5($sourceId)) ?>">
                                                    <?= htmlspecialchars((string)$source['name']) ?>
                                                    <span class="text-muted">(<?= htmlspecialchars($sourceId) ?>)</span>
                                                </label>
                                                <?php if (!empty($source['description'])): ?>
                                                    <div class="small text-muted ms-4"><?= htmlspecialchars((string)$source['description']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="alert alert-light border py-2 mb-3" id="mapping-health-panel">
                                    <div class="small fw-bold mb-2"><?= htmlspecialchars((string)($lang['sys.import_mapping_summary'] ?? 'Configuration summary')) ?></div>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <span class="badge bg-primary"><?= htmlspecialchars((string)($lang['sys.import_active_sources'] ?? 'Active sources')) ?>: <span id="mh-enabled-sources">0</span></span>
                                        <span class="badge bg-success"><?= htmlspecialchars((string)($lang['sys.import_types_explicit'] ?? 'Types (explicit)')) ?>: <span id="mh-type-explicit">0</span></span>
                                        <span class="badge bg-warning text-dark"><?= htmlspecialchars((string)($lang['sys.import_types_auto'] ?? 'Types (auto)')) ?>: <span id="mh-type-auto">0</span></span>
                                    </div>
                                    <div class="small text-muted" id="mh-message">
                                        <?= htmlspecialchars((string)($lang['sys.import_mapping_summary_waiting'] ?? 'Waiting for calculation...')) ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <?= htmlspecialchars((string)($lang['sys.import_sources_missing_catalog'] ?? 'The package does not contain a source catalog. Mapping will become available after uploading a new package built with the current exporter.')) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($propertyPreview['rows'])): ?>
                                <details class="mt-4">
                                    <summary class="fw-bold"><?= htmlspecialchars((string)($lang['sys.import_meta_matrix'] ?? 'Technical matrix of import meta keys')) ?></summary>
                                    <div class="mt-3">
                                        <div class="small text-muted mb-2">
                                            <?= htmlspecialchars((string)($lang['sys.import_meta_matrix_help'] ?? 'This is a technical live preview: it shows which meta keys will be imported and which are excluded by current filters.')) ?>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="property-preview-active-only" checked>
                                            <label class="form-check-label" for="property-preview-active-only">
                                                <?= htmlspecialchars((string)($lang['sys.import_hide_disabled_rows'] ?? 'Hide rows where all sources are disabled (does not affect import)')) ?>
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="property-preview-hide-acf-technical" checked>
                                            <label class="form-check-label" for="property-preview-hide-acf-technical">
                                                <?= htmlspecialchars((string)($lang['sys.import_hide_acf_technical'] ?? 'Hide technical ACF keys')) ?> (<?= htmlspecialchars('_field_...') ?>)
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="property-preview-with-sample-only">
                                            <label class="form-check-label" for="property-preview-with-sample-only">
                                                <?= htmlspecialchars((string)($lang['sys.import_with_sample_only'] ?? 'Show only rows where a sample value was found')) ?>
                                            </label>
                                        </div>
                                        <details class="mb-3">
                                            <summary class="small fw-bold"><?= htmlspecialchars((string)($lang['sys.import_meta_filter_policy'] ?? 'Import meta key filtering policy')) ?></summary>
                                            <div class="card border mt-2 mb-0">
                                                <div class="card-body py-2">
                                                    <div class="form-check form-switch mb-2">
                                                        <input class="form-check-input" type="checkbox" name="include_private_meta_keys" id="include_private_meta_keys" <?= !empty($job['include_private_meta_keys']) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="include_private_meta_keys"><?= htmlspecialchars((string)($lang['sys.import_private_meta_keys'] ?? 'Import system keys that start with `_`')) ?></label>
                                                        <div class="form-text">
                                                            <?= htmlspecialchars((string)($lang['sys.import_private_meta_help'] ?? 'By default such keys are skipped (except the required `_thumbnail_id` for images).')) ?>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label for="meta_include_patterns" class="form-label"><?= htmlspecialchars((string)($lang['sys.import_meta_whitelist'] ?? 'Key whitelist (masks `*`, one per line)')) ?></label>
                                                        <textarea class="form-control" id="meta_include_patterns" name="meta_include_patterns" rows="3" placeholder="left_*&#10;map*"><?= htmlspecialchars((string)($job['meta_include_patterns'] ?? '')) ?></textarea>
                                                        <div class="form-text">
                                                            <?= htmlspecialchars((string)($lang['sys.import_meta_whitelist_help'] ?? 'If filled, only keys matching this list will be imported.')) ?>
                                                        </div>
                                                    </div>
                                                    <div class="mb-0">
                                                        <label for="meta_exclude_patterns" class="form-label"><?= htmlspecialchars((string)($lang['sys.import_meta_blacklist'] ?? 'Key blacklist (masks `*`, one per line)')) ?></label>
                                                        <textarea class="form-control" id="meta_exclude_patterns" name="meta_exclude_patterns" rows="3" placeholder="*_temp*&#10;rank_math_*"><?= htmlspecialchars((string)($job['meta_exclude_patterns'] ?? '')) ?></textarea>
                                                        <div class="form-text">
                                                            <?= htmlspecialchars((string)($lang['sys.import_meta_blacklist_help'] ?? 'This list is applied after the whitelist and excludes matching keys.')) ?>
                                                        </div>
                                                    </div>
                                                    <div class="mb-0 mt-3">
                                                        <label for="source_property_map" class="form-label"><?= htmlspecialchars((string)($lang['sys.import_source_property_map'] ?? 'Прямое сопоставление свойств')) ?></label>
                                                        <textarea class="form-control" id="source_property_map" name="source_property_map" rows="5" placeholder="postmeta:object_type=Тип объекта&#10;postmeta:times=Характер функционирования объекта&#10;postmeta:phones=Телефоны&#10;postmeta:map=Карта"><?= htmlspecialchars((string)($job['source_property_map'] ?? '')) ?></textarea>
                                                        <div class="form-text">
                                                            <?= htmlspecialchars((string)($lang['sys.import_source_property_map_help'] ?? 'Используйте по одной строке: source_property_id = локальное имя свойства или source_property_id = #123. Это нужно для прямого импорта WP meta в уже созданные curated-свойства EE.')) ?>
                                                        </div>
                                                    </div>
                                                    <div class="form-text mt-2" id="meta-filter-live-summary">
                                                        <?= htmlspecialchars((string)($lang['sys.import_meta_filter_live_summary_prefix'] ?? 'Live filter result:'), ENT_QUOTES, 'UTF-8') ?>
                                                        <?= htmlspecialchars((string)($lang['sys.import_meta_filter_live_summary_allowed'] ?? 'imported'), ENT_QUOTES, 'UTF-8') ?>
                                                        <span id="meta-filter-allowed-count">0</span>,
                                                        <?= htmlspecialchars((string)($lang['sys.import_meta_filter_live_summary_blocked'] ?? 'excluded'), ENT_QUOTES, 'UTF-8') ?>
                                                        <span id="meta-filter-blocked-count">0</span>.
                                                    </div>
                                                    <div class="form-text text-warning" id="meta-filter-private-hint" style="display:none;">
                                                        <?= htmlspecialchars((string)($lang['sys.import_meta_filter_private_hint'] ?? 'A significant part of the keys is currently excluded as system ones (`_...`). Enable the switch to import system keys if that is expected for your package.'), ENT_QUOTES, 'UTF-8') ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </details>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th style="width:22%;"><?= htmlspecialchars((string)($lang['sys.import_meta_column_key_name'] ?? 'Meta key and name'), ENT_QUOTES, 'UTF-8') ?></th>
                                                        <th style="width:18%;"><?= htmlspecialchars((string)($lang['sys.import_meta_column_example'] ?? 'Example value from file'), ENT_QUOTES, 'UTF-8') ?></th>
                                                        <th style="width:12%;"><?= htmlspecialchars((string)($lang['sys.import_meta_column_field_types'] ?? 'Field types (`type_fields`)'), ENT_QUOTES, 'UTF-8') ?></th>
                                                        <th style="width:16%;"><?= htmlspecialchars((string)($lang['sys.import_meta_column_sources'] ?? 'WP entities (sources)'), ENT_QUOTES, 'UTF-8') ?></th>
                                                        <th style="width:10%;" title="<?= htmlspecialchars((string)($lang['sys.import_meta_column_manual_exclude_title'] ?? 'The exclusion is stored in the profile and affects the real import'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)($lang['sys.import_meta_column_manual_exclude'] ?? 'Manual exclusion'), ENT_QUOTES, 'UTF-8') ?></th>
                                                        <th style="width:10%;"><?= htmlspecialchars((string)($lang['sys.property'] ?? 'Property'), ENT_QUOTES, 'UTF-8') ?></th>
                                                        <th style="width:12%;" title="Наведите на бейдж статуса, чтобы увидеть причину">Статус</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="property-preview-body">
                                                    <?php foreach ($propertyPreview['rows'] as $previewRow): ?>
                                                        <?php
                                                        $metaKey = trim((string)($previewRow['meta_key'] ?? ''));
                                                        $propertySourceId = trim((string)($previewRow['property_source_id'] ?? ''));
                                                        $displayName = trim((string)($previewRow['display_name'] ?? ''));
                                                        $sampleValue = trim((string)($previewRow['sample_value'] ?? ''));
                                                        $isAcfTechnical = !empty($previewRow['is_acf_technical']);
                                                        $typeFieldsPreview = is_array($previewRow['type_fields'] ?? null) ? $previewRow['type_fields'] : [];
                                                        $sourceSetIdsPreview = is_array($previewRow['source_set_ids'] ?? null) ? $previewRow['source_set_ids'] : [];
                                                        $targetProperty = trim((string)($previewRow['target_property'] ?? 'создать автоматически'));
                                                        $sourceSetIdsJson = htmlspecialchars((string)json_encode(array_values($sourceSetIdsPreview), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                                                        ?>
                                                        <tr
                                                            class="js-property-preview-row"
                                                            data-source-set-ids="<?= $sourceSetIdsJson ?>"
                                                            data-meta-key="<?= htmlspecialchars($metaKey) ?>"
                                                            data-property-source-id="<?= htmlspecialchars($propertySourceId) ?>"
                                                            data-is-acf-technical="<?= $isAcfTechnical ? '1' : '0' ?>"
                                                            data-has-sample="<?= $sampleValue !== '' ? '1' : '0' ?>"
                                                        >
                                                            <td>
                                                                <?php if ($displayName !== ''): ?>
                                                                    <div class="fw-bold"><?= htmlspecialchars($displayName) ?></div>
                                                                <?php endif; ?>
                                                                <strong><?= htmlspecialchars($metaKey !== '' ? $metaKey : $propertySourceId) ?></strong><br>
                                                                <span class="text-muted small"><?= htmlspecialchars($propertySourceId) ?></span>
                                                                <?php if ($isAcfTechnical): ?>
                                                                    <div class="small text-warning">Похоже на технический ACF-ключ</div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($sampleValue !== ''): ?>
                                                                    <div class="small"><?= htmlspecialchars($sampleValue) ?></div>
                                                                <?php else: ?>
                                                                    <span class="text-muted small">Нет примера в текущем срезе файла</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($typeFieldsPreview)): ?>
                                                                    <?php foreach ($typeFieldsPreview as $fieldCode): ?>
                                                                        <span class="badge bg-light text-dark border me-1 mb-1"><?= htmlspecialchars((string)$fieldCode) ?></span>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">text</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($sourceSetIdsPreview)): ?>
                                                                    <?php foreach ($sourceSetIdsPreview as $sourceSetId): ?>
                                                                        <?php
                                                                        $sourceSetIdNorm = strtolower(trim((string)$sourceSetId));
                                                                        $sourceMeta = is_array($sourceOptions[$sourceSetIdNorm] ?? null) ? $sourceOptions[$sourceSetIdNorm] : [];
                                                                        $sourceKind = strtolower(trim((string)($sourceMeta['kind'] ?? '')));
                                                                        $sourceKindLabel = '';
                                                                        if ($sourceKind === 'taxonomy') {
                                                                            $sourceKindLabel = 'taxonomy';
                                                                        } elseif ($sourceKind === 'post_type') {
                                                                            $sourceKindLabel = 'post_type';
                                                                        }
                                                                        $sourceNameLabel = trim((string)($sourceMeta['name'] ?? ''));
                                                                        ?>
                                                                        <div class="mb-1">
                                                                            <code><?= htmlspecialchars((string)$sourceSetId) ?></code>
                                                                            <?php if ($sourceKindLabel !== ''): ?>
                                                                                <span class="badge bg-secondary ms-1"><?= htmlspecialchars($sourceKindLabel) ?></span>
                                                                            <?php endif; ?>
                                                                            <?php if ($sourceNameLabel !== ''): ?>
                                                                                <div class="small text-muted"><?= htmlspecialchars($sourceNameLabel) ?></div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted"><?= htmlspecialchars((string)($lang['sys.import_preview_status_standalone'] ?? 'without source')) ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="js-preview-manual-control">
                                                                <button type="button" class="btn btn-outline-secondary btn-sm js-preview-toggle-manual-exclude" data-property-source-id="<?= htmlspecialchars($propertySourceId) ?>">
                                                                    <?= htmlspecialchars((string)($lang['sys.exclude'] ?? 'Exclude')) ?>
                                                                </button>
                                                            </td>
                                                            <td><?= htmlspecialchars($targetProperty) ?></td>
                                                            <td class="js-preview-status">
                                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars((string)($lang['sys.pending'] ?? 'Pending')) ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="form-text mt-2" id="property-preview-summary">
                                            <?= htmlspecialchars((string)($lang['sys.import_preview_shown'] ?? 'Shown')) ?>: <span id="property-preview-visible-count"><?= (int)count($propertyPreview['rows']) ?></span> <?= htmlspecialchars((string)($lang['sys.of'] ?? 'of')) ?>
                                            <span id="property-preview-total-count"><?= (int)($propertyPreview['total'] ?? count($propertyPreview['rows'])) ?></span> <?= htmlspecialchars((string)($lang['sys.import_preview_properties'] ?? 'properties')) ?>.
                                            <?= htmlspecialchars((string)($lang['sys.status'] ?? 'Status')) ?>:
                                            <?= htmlspecialchars((string)($lang['sys.import_preview_status_auto'] ?? 'to import')) ?>=<span id="property-preview-status-auto">0</span>,
                                            <?= htmlspecialchars((string)($lang['sys.import_preview_status_filtered'] ?? 'filtered out')) ?>=<span id="property-preview-status-filtered">0</span>,
                                            <?= htmlspecialchars((string)($lang['sys.import_preview_status_manual'] ?? 'excluded manually')) ?>=<span id="property-preview-status-manual">0</span>,
                                            <?= htmlspecialchars((string)($lang['sys.import_preview_status_disabled'] ?? 'disabled by source')) ?>=<span id="property-preview-status-disabled">0</span>,
                                            <?= htmlspecialchars((string)($lang['sys.import_preview_status_standalone'] ?? 'without source')) ?>=<span id="property-preview-status-standalone">0</span>.
                                            <?php if (!empty($propertyPreview['truncated'])): ?>
                                                <span id="property-preview-truncated-note"><?= htmlspecialchars((string)($lang['sys.import_preview_truncated_note'] ?? 'The list is truncated for performance.')) ?></span>
                                            <?php endif; ?>
                                            <?= htmlspecialchars((string)($lang['sys.import_preview_auto_refresh'] ?? 'The preview updates automatically when mappings and the source list change.')) ?>
                                        </div>
                                        <div class="form-text text-warning" id="property-preview-empty-hint" style="display:none;">
                                            <?= htmlspecialchars((string)($lang['sys.import_preview_empty_hint'] ?? 'With the current source restrictions, no preview row participates in import.')) ?>
                                        </div>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card mb-3" id="composite-properties-card">
                        <div class="card-body">
                            <h5 class="card-title mb-2"><?= htmlspecialchars((string)($lang['sys.composite_properties_builder'] ?? 'Composite properties builder')) ?></h5>
                            <p class="text-muted mb-2">
                                <?= htmlspecialchars((string)($lang['sys.composite_builder_help'] ?? 'Here you can combine several source meta keys into one CMS property with a field set.')) ?>
                            </p>
                            <div class="alert alert-primary py-2 small mb-3">
                                <?= htmlspecialchars((string)($lang['sys.composite_builder_whitelist_notice'] ?? 'If at least one composite property is added here, the importer loads only the postmeta/termmeta keys explicitly mapped in this builder.')) ?>
                            </div>
                            <div class="alert alert-warning py-2 small mb-3">
                                <?= htmlspecialchars((string)($lang['sys.composite_builder_acf_notice'] ?? 'For technical ACF keys such as field_... or _field_..., provide readable names manually.')) ?>
                            </div>
                            <div class="alert alert-info py-2 small mb-3">
                                <?= htmlspecialchars((string)($lang['sys.composite_builder_mask_notice'] ?? 'For repeating keys you can use masks with *, for example postmeta:numbers_*_photos or postmeta:numbers_*_newprices_*_pricec. Enable the multiple property flag for such groups.')) ?>
                            </div>

                            <script type="application/json" id="composite-meta-options-json"><?= (string)json_encode($propertyPreviewMetaOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
                            <script type="application/json" id="composite-field-types-json"><?= (string)json_encode($propertyFieldTypes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
                            <script type="application/json" id="composite-local-properties-json"><?= (string)json_encode($compositePropertyOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

                            <div class="d-flex gap-2 mb-3">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="composite-add-btn">
                                    <i class="fas fa-plus"></i> <?= htmlspecialchars((string)($lang['sys.add_composite_property'] ?? 'Add composite property')) ?>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="composite-clear-btn">
                                    <?= htmlspecialchars((string)($lang['sys.clear_builder'] ?? 'Clear builder')) ?>
                                </button>
                            </div>

                            <div id="composite-properties-builder" class="d-flex flex-column gap-3"></div>
                            <div class="form-text mt-2">
                                <?= htmlspecialchars((string)($lang['sys.composite_builder_saved_help'] ?? 'The configuration is saved in the profile and used in Stage 1 (property creation) and Stage 2 (writing values).')) ?>
                            </div>
                        </div>
                    </div>

                    </div>

                    <textarea class="d-none" id="source_type_map" name="source_type_map"><?= htmlspecialchars((string)($job['source_type_map'] ?? '')) ?></textarea>
                    <textarea class="d-none" id="source_set_map" name="source_set_map"></textarea>
                    <textarea class="d-none" id="allowed_source_ids" name="allowed_source_ids"><?= htmlspecialchars((string)($job['allowed_source_ids'] ?? '')) ?></textarea>

                    <hr>

                    <button type="button" id="save-settings-btn" class="btn btn-success btn-lg">
                        <i class="fas fa-save"></i> <?= htmlspecialchars((string)($lang['sys.save_profile'] ?? 'Save profile')) ?>
                    </button>
                    <span id="import-status" class="ms-3"></span>

                    <?php if ($job_id > 0): ?>
                        <div class="alert alert-info mt-3 mb-0" id="wizard-step3-lock-hint" style="<?= $packageReady ? 'display:none;' : '' ?>">
                            <?= htmlspecialchars((string)($lang['sys.stage3_unlock_hint'] ?? 'Step 3 will become available after selecting a package and configuring at least one source in step 2.')) ?>
                        </div>
                        <div class="card mt-3 mb-0" id="import-wizard-panel" style="<?= $packageReady ? '' : 'display:none;' ?>">
                            <div class="card-body">
                                <h5 class="card-title mb-2"><?= htmlspecialchars((string)($lang['sys.import_launch_wizard'] ?? 'Import launch wizard')) ?></h5>
                                <p class="text-muted mb-3">
                                    <?= htmlspecialchars((string)($lang['sys.import_launch_wizard_help'] ?? 'Stages run sequentially. First the property structure is loaded, then categories and pages.')) ?>
                                </p>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="border rounded p-3 h-100">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <strong><?= htmlspecialchars((string)($lang['sys.import_stage1_title'] ?? 'Stage 1. Property structure')) ?></strong>
                                                <span class="badge <?= $wizardCoreDone ? 'bg-success' : 'bg-secondary' ?>" id="wizard-core-status-badge">
                                                    <?= htmlspecialchars((string)($wizardCoreDone ? ($lang['sys.completed'] ?? 'completed') : ($lang['sys.not_started'] ?? 'not started'))) ?>
                                                </span>
                                            </div>
                                            <div class="small text-muted mb-3" id="wizard-core-status-text">
                                                <?php if ($wizardCoreDone): ?>
                                                    <?= htmlspecialchars((string)($lang['sys.completed'] ?? 'Completed')) ?>: <?= htmlspecialchars($wizardCoreCompletedAt) ?>
                                                <?php else: ?>
                                                    <?= htmlspecialchars((string)($lang['sys.import_stage1_help'] ?? 'Imports property types, property sets, properties, and their links.')) ?>
                                                <?php endif; ?>
                                            </div>
                                            <button
                                                type="button"
                                                id="run-import-core-btn"
                                                class="btn btn-primary w-100 js-run-import-btn"
                                                data-stage-scope="core"
                                                data-stage-title="Этап 1: структура свойств"
                                                data-stage-restart="1"
                                                data-lang-running="<?= htmlspecialchars((string)($lang['sys.imports_stage_1_running'] ?? 'Этап 1 выполняется...'), ENT_QUOTES, 'UTF-8') ?>"
                                                data-lang-done="<?= htmlspecialchars((string)($lang['sys.imports_stage_1_done'] ?? 'Этап 1 завершён'), ENT_QUOTES, 'UTF-8') ?>"
                                                data-lang-error="<?= htmlspecialchars((string)($lang['sys.imports_stage_1_error'] ?? 'Ошибка этапа 1'), ENT_QUOTES, 'UTF-8') ?>"
                                            >
                                                <i class="fas fa-play"></i> <?= htmlspecialchars((string)($lang['sys.imports_stage_1_label'] ?? 'Запустить этап 1'), ENT_QUOTES, 'UTF-8') ?>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="col-md-6" id="wizard-content-stage-col" style="<?= $wizardCoreDone ? '' : 'display:none;' ?>">
                                        <div class="border rounded p-3 h-100">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <strong><?= htmlspecialchars((string)($lang['sys.import_stage2_title'] ?? 'Stage 2. Categories and pages')) ?></strong>
                                                <span class="badge <?= $wizardContentDone ? 'bg-success' : 'bg-secondary' ?>" id="wizard-content-status-badge">
                                                    <?= htmlspecialchars((string)($wizardContentDone ? ($lang['sys.completed'] ?? 'completed') : ($lang['sys.not_started'] ?? 'not started'))) ?>
                                                </span>
                                            </div>
                                            <div class="small text-muted mb-3" id="wizard-content-status-text">
                                                <?php if ($wizardContentDone): ?>
                                                    <?= htmlspecialchars((string)($lang['sys.completed'] ?? 'Completed')) ?>: <?= htmlspecialchars($wizardContentCompletedAt) ?>
                                                <?php elseif ($wizardCoreDone): ?>
                                                    <?= htmlspecialchars((string)($lang['sys.import_stage2_help'] ?? 'Ready to run: it will import category types, type-to-set links, categories, pages, and property values.')) ?>
                                                <?php else: ?>
                                                    <?= htmlspecialchars((string)($lang['sys.import_stage2_wait_stage1'] ?? 'It will become available after stage 1 is completed.')) ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($typeCatalog)): ?>
                                                <div class="mb-3 p-2 border rounded bg-light">
                                                    <label class="form-label fw-bold mb-2"><?= htmlspecialchars((string)($lang['sys.import_stage2_type_mapping_title'] ?? 'Stage 2 setup: CMS category type by source')) ?></label>
                                                    <div class="small text-muted mb-2">
                                                        <?= htmlspecialchars((string)($lang['sys.import_stage2_type_mapping_help'] ?? 'These mappings are applied only in stage 2.')) ?>
                                                    </div>
                                                    <?php if (empty($preparedLocalTypeOptions)): ?>
                                                        <div class="alert alert-warning py-2 small mb-2">
                                                            <?= htmlspecialchars((string)($lang['sys.import_stage2_no_local_types'] ?? 'No CMS category types are available for explicit selection yet. Auto-create mode is available.')) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered align-middle mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th style="width:45%;"><?= htmlspecialchars((string)($lang['sys.source'] ?? 'Source')) ?></th>
                                                                    <th style="width:55%;"><?= htmlspecialchars((string)($lang['sys.category_type_in_cms'] ?? 'Category type in CMS')) ?></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($typeCatalog as $item): ?>
                                                                    <?php
                                                                    if (!is_array($item)) {
                                                                        continue;
                                                                    }
                                                                    $sourceId = strtolower(trim((string)($item['source_id'] ?? '')));
                                                                    if ($sourceId === '') {
                                                                        continue;
                                                                    }
                                                                    $selectedTypeId = (int)($typeMap[$sourceId] ?? 0);
                                                                    $sourceEnabled = $isSourceEnabled($sourceId);
                                                                    $sourceKind = $detectSourceKind($sourceId, (string)($item['kind'] ?? ''));
                                                                    ?>
                                                                    <tr class="js-source-dependent-row js-type-map-row <?= $sourceEnabled ? '' : 'table-secondary opacity-75' ?>" data-source-id="<?= htmlspecialchars($sourceId) ?>">
                                                                        <td>
                                                                            <strong><?= htmlspecialchars((string)($item['name'] ?? $sourceId)) ?></strong><br>
                                                                            <?php
                                                                            $kindLabel = (string)($lang['sys.source'] ?? 'Source');
                                                                            if ($sourceKind === 'taxonomy') {
                                                                                $kindLabel = (string)($lang['sys.wp_taxonomy'] ?? 'WP taxonomy');
                                                                            } elseif ($sourceKind === 'post_type') {
                                                                                $kindLabel = (string)($lang['sys.wp_post_type'] ?? 'WP post type');
                                                                            }
                                                                            ?>
                                                                            <span class="badge bg-secondary"><?= htmlspecialchars($kindLabel) ?></span><br>
                                                                            <span class="text-muted small"><?= htmlspecialchars($sourceId) ?></span>
                                                                        </td>
                                                                        <td>
                                                                            <select class="form-select form-select-sm js-type-map" data-source-id="<?= htmlspecialchars($sourceId) ?>" data-source-kind="<?= htmlspecialchars($sourceKind) ?>" <?= $sourceEnabled ? '' : 'disabled' ?>>
                                                                                <option value="0"><?= htmlspecialchars((string)($lang['sys.create_automatically'] ?? 'Create automatically')) ?></option>
                                                                                <?php if ($selectedTypeId > 0 && !isset($preparedLocalTypeOptions[$selectedTypeId])): ?>
                                                                                    <option value="<?= $selectedTypeId ?>" selected>
                                                                                        #<?= $selectedTypeId ?> (<?= htmlspecialchars((string)($lang['sys.type_not_found'] ?? 'type not found')) ?>)
                                                                                    </option>
                                                                                <?php endif; ?>
                                                                                <?php foreach ($preparedLocalTypeOptions as $typeOption): ?>
                                                                                    <?php
                                                                                    $optId = (int)$typeOption['id'];
                                                                                    $optName = (string)$typeOption['name'];
                                                                                    $optKind = (string)($typeOption['kind'] ?? '');
                                                                                    if (!$isCompatibleOptionKind($sourceKind, $optKind) && $selectedTypeId !== $optId) {
                                                                                        continue;
                                                                                    }
                                                                                    ?>
                                                                                    <option value="<?= $optId ?>" <?= $selectedTypeId === $optId ? 'selected' : '' ?>>
                                                                                        #<?= $optId ?> <?= htmlspecialchars($optName) ?>
                                                                                    </option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                            <div class="form-text text-warning js-row-disabled-hint" style="<?= $sourceEnabled ? 'display:none;' : '' ?>">
                                                                                Источник отключён выше, эта строка не участвует в импорте.
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <button
                                                type="button"
                                                id="run-import-content-btn"
                                                class="btn btn-outline-primary w-100 js-run-import-btn"
                                                data-stage-scope="content"
                                                data-stage-title="Этап 2: категории и страницы"
                                                data-stage-restart="1"
                                                data-stage-requires-core="1"
                                                data-lang-running="<?= htmlspecialchars((string)($lang['sys.imports_stage_2_running'] ?? 'Этап 2 выполняется...'), ENT_QUOTES, 'UTF-8') ?>"
                                                data-lang-done="<?= htmlspecialchars((string)($lang['sys.imports_stage_2_done'] ?? 'Этап 2 завершён'), ENT_QUOTES, 'UTF-8') ?>"
                                                data-lang-error="<?= htmlspecialchars((string)($lang['sys.imports_stage_2_error'] ?? 'Ошибка этапа 2'), ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $wizardCoreDone ? '' : 'disabled' ?>
                                            >
                                                <i class="fas fa-play"></i> <?= htmlspecialchars((string)($lang['sys.imports_stage_2_label'] ?? 'Запустить этап 2'), ENT_QUOTES, 'UTF-8') ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-secondary mt-3 mb-0" id="wizard-step4-lock-hint" style="<?= ($packageReady && !$wizardCoreDone) ? '' : 'display:none;' ?>">
                                    Шаг 4 будет доступен после завершения этапа 1.
                                </div>

                                <details class="mt-3">
                                    <summary class="small fw-bold">Служебный режим: полный запуск всех фаз</summary>
                                    <div class="mt-2">
                                        <button
                                            type="button"
                                            id="run-import-full-btn"
                                            class="btn btn-sm btn-outline-secondary js-run-import-btn"
                                            data-stage-scope="all"
                                            data-stage-title="Полный импорт"
                                            data-stage-restart="1"
                                            data-lang-running="<?= htmlspecialchars((string)($lang['sys.imports_full_running'] ?? 'Полный импорт выполняется...'), ENT_QUOTES, 'UTF-8') ?>"
                                            data-lang-done="<?= htmlspecialchars((string)($lang['sys.imports_full_done'] ?? 'Полный импорт завершён'), ENT_QUOTES, 'UTF-8') ?>"
                                            data-lang-error="<?= htmlspecialchars((string)($lang['sys.imports_full_error'] ?? 'Ошибка полного импорта'), ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            <i class="fas fa-play"></i> <?= htmlspecialchars((string)($lang['sys.imports_full_label'] ?? 'Запустить все фазы'), ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                    </div>
                                </details>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>

                <?php if ($job_id > 0): ?>
                    <h3 class="mt-5">Запуск по CRON</h3>
                    <p><?= htmlspecialchars((string)($lang['sys.cron_agent_scheduler_help'] ?? 'Настройте системный cron на запуск этого скрипта каждую минуту:')) ?></p>
                    <pre class="bg-light p-3 rounded"><code><?= htmlspecialchars((string)$cron_command) ?></code></pre>
                    <div class="alert alert-info">
                        <div class="fw-bold mb-2"><?= htmlspecialchars((string)($lang['sys.imports_cron_agent_title'] ?? 'Для профиля импорта лучше использовать cron-агент')) ?></div>
                        <p class="mb-2"><?= htmlspecialchars((string)($lang['sys.imports_cron_agent_desc'] ?? 'Создайте агент с handler import.profile и payload JSON с job_id. Тогда минутный scheduler сам запустит профиль по расписанию.')) ?></p>
                        <?php if ($cronAgentCreateLink !== ''): ?>
                            <a href="<?= htmlspecialchars($cronAgentCreateLink) ?>" class="btn btn-sm btn-primary">
                                <i class="fa-solid fa-clock-rotate-left"></i> <?= htmlspecialchars((string)($lang['sys.imports_cron_agent_create'] ?? 'Создать cron-агент для этого профиля')) ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($cronImportCommand !== ''): ?>
                            <div class="small text-muted mt-3"><?= htmlspecialchars((string)($lang['sys.imports_cron_agent_manual'] ?? 'Разовый ручной запуск конкретного профиля по-прежнему доступен командой:')) ?></div>
                            <pre class="bg-light p-3 rounded mb-0"><code><?= htmlspecialchars($cronImportCommand) ?></code></pre>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="card mt-4">
                    <div class="card-body">
                        <h3 class="h5 mb-3">Лог выполнения</h3>
                        <pre id="log-output" class="mb-0" style="min-height:260px;max-height:700px;background:#222;color:#f5f5f5;padding:10px;border-radius:5px;overflow-y:auto;font-family:monospace;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
