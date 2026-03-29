<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$supportedFieldTypes = is_array($supported_field_types ?? null) ? array_keys($supported_field_types) : [];
$previewEditorState = is_array($preview_editor_state ?? null) ? $preview_editor_state : [];
$previewEditorStateJson = trim((string)($preview_editor_state_json ?? ''));
$previewWarnings = is_array($preview_warnings ?? null) ? array_values($preview_warnings) : [];
$previewErrorMessage = trim((string)($preview_error_message ?? ''));
$previewSourceFilename = trim((string)($preview_source_filename ?? ($previewEditorState['source_filename'] ?? '')));
$previewProperties = is_array($previewEditorState['properties'] ?? null) ? $previewEditorState['properties'] : [];
$uiText = [
    'heading' => (string) ($lang['sys.imports_property_definitions'] ?? 'JSON: property types / properties / sets'),
    'jsonUpload' => (string) ($lang['sys.json_upload'] ?? 'JSON upload'),
    'importFile' => (string) ($lang['sys.import_file'] ?? 'Import file'),
    'openPreview' => (string) ($lang['sys.open_preview'] ?? 'Open preview'),
    'currentPreview' => (string) ($lang['sys.current_preview'] ?? 'Current preview'),
    'draftPreviewHelp' => (string) ($lang['sys.draft_preview_help'] ?? 'After upload, a draft preview will open where you can disable, rename, and merge properties before import.'),
    'rules' => (string) ($lang['sys.import_rules'] ?? 'Import rules'),
    'previewWarnings' => (string) ($lang['sys.preview_warnings'] ?? 'Draft preview warnings'),
    'properties' => (string) ($lang['sys.properties'] ?? 'Properties'),
    'selectMergeable' => (string) ($lang['sys.select_mergeable'] ?? 'Select mergeable items'),
    'clearSelection' => (string) ($lang['sys.clear_selection'] ?? 'Clear selection'),
    'mergeSelected' => (string) ($lang['sys.merge_selected'] ?? 'Merge selected'),
    'importSelectedStructure' => (string) ($lang['sys.import_selected_structure'] ?? 'Import selected structure'),
    'propertyStructure' => (string) ($lang['sys.property_structure'] ?? 'Property structure'),
    'propertyName' => (string) ($lang['sys.property_name'] ?? 'Property name'),
    'propertyTypeName' => (string) ($lang['sys.property_type_name'] ?? 'Property type name'),
    'description' => (string) ($lang['sys.description'] ?? 'Description'),
    'propertyFields' => (string) ($lang['sys.property_fields'] ?? 'Property fields'),
    'sets' => (string) ($lang['sys.sets'] ?? 'Sets'),
    'close' => (string) ($lang['sys.close'] ?? 'Close'),
    'done' => (string) ($lang['sys.done'] ?? 'Done'),
    'mergeProperties' => (string) ($lang['sys.merge_properties'] ?? 'Merge properties'),
    'mergeTargetProperty' => (string) ($lang['sys.merge_target_property'] ?? 'Target property'),
    'futurePropertyFields' => (string) ($lang['sys.future_property_fields'] ?? 'Future property fields'),
    'setsAfterMerge' => (string) ($lang['sys.sets_after_merge'] ?? 'Sets after merge'),
    'cancel' => (string) ($lang['sys.cancel'] ?? 'Cancel'),
    'applyMerge' => (string) ($lang['sys.apply_merge'] ?? 'Apply merge'),
    'total' => (string) ($lang['sys.total'] ?? 'Total'),
    'toImport' => (string) ($lang['sys.to_import'] ?? 'To import'),
    'mergeGroups' => (string) ($lang['sys.merge_groups'] ?? 'Merge groups'),
    'choose' => (string) ($lang['sys.choose'] ?? 'Choose'),
    'import' => (string) ($lang['sys.import'] ?? 'Import'),
    'type' => (string) ($lang['sys.type'] ?? 'Type'),
    'status' => (string) ($lang['sys.status'] ?? 'Status'),
    'documentationValidationNotes' => (string) ($lang['sys.property_definition_doc_notes'] ?? 'Critical errors block the preview only for invalid JSON, duplicate `code`, unknown `type_code`, `fields/default_values` mismatches, and unsupported field types.'),
    'jsonStructureHint' => (string) ($lang['sys.property_definition_json_structure_hint'] ?? 'JSON root object must contain `schema`, `version`, and arrays for `property_types`, `properties`, `property_sets`.'),
    'jsonChoiceHint' => (string) ($lang['sys.property_definition_json_choice_hint'] ?? 'For `select`, `checkbox`, and `radio`, define explicit options and default values in the same field order as the property type.'),
    'fieldRequired' => (string) ($lang['sys.field_required'] ?? 'Required'),
    'fieldOptional' => (string) ($lang['sys.field_optional'] ?? 'Optional'),
    'fieldMultiple' => (string) ($lang['sys.field_multiple'] ?? 'Multiple'),
    'fieldSingle' => (string) ($lang['sys.field_single'] ?? 'Single'),
    'setName' => (string) ($lang['sys.set_name'] ?? 'Set name'),
    'source' => (string) ($lang['sys.source'] ?? 'Source'),
    'delete' => (string) ($lang['sys.delete'] ?? 'Delete'),
    'noFieldsYet' => (string) ($lang['sys.no_fields_yet'] ?? 'No fields yet.'),
    'noSetsYet' => (string) ($lang['sys.no_sets_yet'] ?? 'No sets yet.'),
    'propertyStructureWithName' => (string) ($lang['sys.property_structure_with_name'] ?? 'Property structure: %s'),
    'mergeNeedTwo' => (string) ($lang['sys.merge_need_two'] ?? 'Select at least two available properties to merge.'),
    'mergePrepareFailed' => (string) ($lang['sys.merge_prepare_failed'] ?? 'Failed to prepare merge.'),
    'mergeTargetNotFound' => (string) ($lang['sys.merge_target_not_found'] ?? 'Target property was not found.'),
    'mergeNestedTarget' => (string) ($lang['sys.merge_nested_target'] ?? 'Property "%s" is already the target of another merge. Unmerge it first.'),
    'summaryTotal' => (string) ($lang['sys.total'] ?? 'Total'),
    'summaryToImport' => (string) ($lang['sys.to_import'] ?? 'To import'),
    'summaryMergeGroups' => (string) ($lang['sys.merge_groups'] ?? 'Merge groups'),
    'optionVariant' => (string) ($lang['sys.option_variant'] ?? 'Option'),
    'newField' => (string) ($lang['sys.new_field'] ?? 'New field'),
    'newSet' => (string) ($lang['sys.new_set'] ?? 'New set'),
    'field' => (string) ($lang['sys.field'] ?? 'Field'),
    'untitled' => (string) ($lang['sys.untitled'] ?? 'Untitled'),
    'valuesShort' => (string) ($lang['sys.values_short'] ?? 'values'),
    'fieldsShort' => (string) ($lang['sys.fields_short'] ?? 'fields'),
    'setsNotDefined' => (string) ($lang['sys.sets_not_defined'] ?? 'Sets are not defined'),
    'mergedInto' => (string) ($lang['sys.merged_into'] ?? 'Merged into'),
    'mergeTargetLabel' => (string) ($lang['sys.merge_target_label'] ?? 'Merge target property'),
    'unmerge' => (string) ($lang['sys.unmerge'] ?? 'Unmerge'),
    'mergeAllowed' => (string) ($lang['sys.merge_allowed'] ?? 'Can be merged'),
    'noMerge' => (string) ($lang['sys.no_merge'] ?? 'No merge'),
    'mergedBadge' => (string) ($lang['sys.merged'] ?? 'merged'),
    'code' => (string) ($lang['sys.code'] ?? 'Code'),
    'entity' => (string) ($lang['sys.entity'] ?? 'Entity'),
    'mergeSourcesCount' => (string) ($lang['sys.merge_sources_count'] ?? 'Merge sources'),
    'sourceCurrentProperty' => (string) ($lang['sys.source_current_property'] ?? 'Source: current property'),
    'options' => (string) ($lang['sys.options'] ?? 'Options'),
    'addOption' => (string) ($lang['sys.add_option'] ?? 'Add option'),
    'selected' => (string) ($lang['sys.selected'] ?? 'Selected'),
    'checked' => (string) ($lang['sys.checked'] ?? 'Checked'),
    'label' => (string) ($lang['sys.label'] ?? 'Label'),
    'value' => (string) ($lang['sys.value'] ?? 'Value'),
    'fieldType' => (string) ($lang['sys.field_type'] ?? 'Field type'),
    'caption' => (string) ($lang['sys.caption'] ?? 'Caption'),
    'heading' => (string) ($lang['sys.heading'] ?? 'Heading'),
    'defaultValue' => (string) ($lang['sys.default_value'] ?? 'Default value'),
];

$enabledCount = 0;
$mergeGroupCount = 0;
foreach ($previewProperties as $property) {
    if (!is_array($property)) {
        continue;
    }

    if (!empty($property['enabled']) && trim((string)($property['merged_into'] ?? '')) === '') {
        $enabledCount++;
    }
    if (!empty($property['merge_sources']) && is_array($property['merge_sources'])) {
        $mergeGroupCount++;
    }
}
?>
<style>
#propertyStructureModal .modal-dialog {
    width: min(96vw, 1900px);
    max-width: min(96vw, 1900px);
    margin: 5rem auto 1rem;
}

#propertyStructureModal .modal-content {
    min-height: calc(100vh - 6rem);
    max-height: calc(100vh - 6rem);
}

#propertyStructureModal .modal-body {
    overflow-y: auto;
}

@media (max-width: 991.98px) {
    #propertyStructureModal .modal-dialog {
        width: calc(100vw - 1rem);
        max-width: calc(100vw - 1rem);
        margin: 4.5rem auto 0.5rem;
    }

    #propertyStructureModal .modal-content {
        min-height: calc(100vh - 5rem);
        max-height: calc(100vh - 5rem);
    }
}
</style>
<main>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <h1 class="mt-4">
                    <i class="fas fa-sitemap me-2"></i><?= htmlspecialchars($uiText['heading'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </h1>
            </div>
        </div>

        <?php if ($previewErrorMessage !== ''): ?>
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($previewErrorMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-12 col-xxl-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h3 class="card-title mb-0"><i class="fas fa-upload me-2"></i><?= htmlspecialchars($uiText['jsonUpload'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" class="row g-3">
                            <input type="hidden" name="property_definitions_action" value="prepare_preview">
                            <div class="col-12 col-lg-8">
                                <label for="property_definitions_file" class="form-label"><?= htmlspecialchars($uiText['importFile'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></label>
                                <input
                                    type="file"
                                    class="form-control"
                                    id="property_definitions_file"
                                    name="property_definitions_file"
                                    accept=".json,application/json"
                                    required
                                >
                            </div>
                            <div class="col-12 col-lg-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i><?= htmlspecialchars($uiText['openPreview'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                </button>
                            </div>
                        </form>
                        <div class="small text-muted mt-3">
                            <?php if ($previewSourceFilename !== ''): ?>
                                <?= htmlspecialchars($uiText['currentPreview'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>: <strong><?= htmlspecialchars($previewSourceFilename, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                            <?php else: ?>
                                <?= htmlspecialchars($uiText['draftPreviewHelp'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xxl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h3 class="card-title mb-0"><i class="fas fa-file-alt me-2"></i><?= htmlspecialchars($uiText['rules'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            <?= htmlspecialchars($uiText['jsonStructureHint'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </p>
                        <p class="mb-2">
                            <?= htmlspecialchars($uiText['jsonChoiceHint'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </p>
                        <div class="small text-muted">
                            <?= htmlspecialchars($uiText['documentationValidationNotes'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($previewProperties !== []): ?>
            <?php if ($previewWarnings !== []): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-warning" role="alert">
                            <div class="fw-semibold mb-2"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($uiText['previewWarnings'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                            <ul class="mb-0">
                                <?php foreach ($previewWarnings as $warning): ?>
                                    <li><?= htmlspecialchars((string)$warning, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" id="property-definitions-confirm-form">
                <input type="hidden" name="property_definitions_action" value="confirm_import">
                <textarea
                    name="property_definition_editor_state"
                    id="property-definition-editor-state"
                    class="d-none"
                ><?= htmlspecialchars($previewEditorStateJson, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>

                <div class="card border mb-4 shadow-sm">
                    <div class="card-header bg-white d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
                        <div>
                            <div class="h5 mb-1"><?= htmlspecialchars($uiText['properties'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                            <div class="small text-muted" id="property-editor-summary">
                                <?= htmlspecialchars($uiText['total'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>: <?= (int)count($previewProperties) ?>, <?= htmlspecialchars($uiText['toImport'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>: <?= (int)$enabledCount ?>, <?= htmlspecialchars($uiText['mergeGroups'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>: <?= (int)$mergeGroupCount ?>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="select-all-mergeable">
                                <i class="fas fa-check-square me-1"></i><?= htmlspecialchars($uiText['selectMergeable'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="clear-merge-selection">
                                <i class="fas fa-square me-1"></i><?= htmlspecialchars($uiText['clearSelection'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" id="open-merge-modal">
                                <i class="fas fa-object-group me-1"></i><?= htmlspecialchars($uiText['mergeSelected'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" style="width: 64px;"><?= htmlspecialchars($uiText['choose'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
                                        <th scope="col" style="width: 88px;"><?= htmlspecialchars($uiText['import'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
                                        <th scope="col"><?= htmlspecialchars($uiText['propertyName'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
                                        <th scope="col" style="width: 240px;"><?= htmlspecialchars($uiText['type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
                                        <th scope="col" style="width: 260px;"><?= htmlspecialchars($uiText['sets'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
                                        <th scope="col" style="width: 220px;"><?= htmlspecialchars($uiText['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="property-editor-table-body"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                        <div class="small text-muted">
                            <?= htmlspecialchars((string) ($lang['sys.property_definition_help'] ?? 'Click the property name, type, or sets to edit the property structure, type name, fields, select options, and set bindings.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </div>
                        <button type="submit" class="btn btn-success" id="confirm-property-import">
                            <i class="fas fa-file-import me-2"></i><?= htmlspecialchars($uiText['importSelectedStructure'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<div class="modal fade" id="propertyStructureModal" tabindex="-1" aria-labelledby="propertyStructureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="propertyStructureModalLabel"><?= htmlspecialchars($uiText['propertyStructure'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($uiText['close'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-lg-6">
                        <label for="structure-property-name" class="form-label"><?= htmlspecialchars($uiText['propertyName'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></label>
                        <input type="text" class="form-control" id="structure-property-name">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label for="structure-type-name" class="form-label"><?= htmlspecialchars($uiText['propertyTypeName'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></label>
                        <input type="text" class="form-control" id="structure-type-name">
                    </div>
                    <div class="col-12">
                        <label for="structure-property-description" class="form-label"><?= htmlspecialchars($uiText['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></label>
                        <textarea class="form-control" id="structure-property-description" rows="2"></textarea>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3" id="structure-property-meta"></div>

                <div class="card border-0 bg-light mb-4">
                    <div class="card-header bg-light d-flex align-items-center justify-content-between">
                        <div class="fw-semibold"><?= htmlspecialchars($uiText['propertyFields'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="structure-add-field">
                            <i class="fas fa-plus me-1"></i><?= htmlspecialchars((string) ($lang['sys.add_field'] ?? 'Add field'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="structure-fields-list"></div>
                    </div>
                </div>

                <div class="card border-0 bg-light">
                    <div class="card-header bg-light d-flex align-items-center justify-content-between">
                        <div class="fw-semibold"><?= htmlspecialchars($uiText['sets'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="structure-add-set">
                            <i class="fas fa-plus me-1"></i><?= htmlspecialchars((string) ($lang['sys.add_set'] ?? 'Add set'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="structure-sets-list"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($uiText['close'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
                <button type="button" class="btn btn-primary" id="structure-modal-save" data-bs-dismiss="modal"><?= htmlspecialchars($uiText['done'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="propertyMergeModal" tabindex="-1" aria-labelledby="propertyMergeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="propertyMergeModalLabel"><?= htmlspecialchars($uiText['mergeProperties'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($uiText['close'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border" id="merge-selected-summary"></div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-lg-6">
                        <label for="merge-target-select" class="form-label"><?= htmlspecialchars($uiText['mergeTargetProperty'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></label>
                        <select class="form-select" id="merge-target-select"></select>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label for="merge-type-name" class="form-label"><?= htmlspecialchars($uiText['propertyTypeName'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></label>
                        <input type="text" class="form-control" id="merge-type-name">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label for="merge-property-name" class="form-label"><?= htmlspecialchars($uiText['propertyName'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></label>
                        <input type="text" class="form-control" id="merge-property-name">
                    </div>
                    <div class="col-12 col-lg-6">
                        <label for="merge-property-description" class="form-label"><?= htmlspecialchars($uiText['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></label>
                        <textarea class="form-control" id="merge-property-description" rows="2"></textarea>
                    </div>
                </div>

                <div class="card border-0 bg-light mb-4">
                    <div class="card-header bg-light d-flex align-items-center justify-content-between">
                        <div class="fw-semibold"><?= htmlspecialchars($uiText['futurePropertyFields'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="merge-add-field">
                            <i class="fas fa-plus me-1"></i><?= htmlspecialchars((string) ($lang['sys.add_field'] ?? 'Add field'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="merge-fields-list"></div>
                    </div>
                </div>

                <div class="card border-0 bg-light">
                    <div class="card-header bg-light d-flex align-items-center justify-content-between">
                        <div class="fw-semibold"><?= htmlspecialchars($uiText['setsAfterMerge'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="merge-add-set">
                            <i class="fas fa-plus me-1"></i><?= htmlspecialchars((string) ($lang['sys.add_set'] ?? 'Add set'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="merge-sets-list"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= htmlspecialchars($uiText['cancel'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
                <button type="button" class="btn btn-primary" id="merge-apply-button"><?= htmlspecialchars($uiText['applyMerge'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const stateTextarea = document.getElementById('property-definition-editor-state');
    const tableBody = document.getElementById('property-editor-table-body');
    const summaryNode = document.getElementById('property-editor-summary');
    const supportedFieldTypes = <?= json_encode(array_values($supportedFieldTypes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || [];
    const fallbackFieldTypes = ['text', 'textarea', 'number', 'email', 'phone', 'date', 'time', 'select', 'checkbox', 'radio', 'image', 'file'];
    const fieldTypes = Array.isArray(supportedFieldTypes) && supportedFieldTypes.length > 0 ? supportedFieldTypes : fallbackFieldTypes;
    const i18n = <?= json_encode($uiText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || {};
    const fieldTypeLabels = {
        text: <?= json_encode((string) ($lang['sys.field_type_text'] ?? 'Text'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        textarea: <?= json_encode((string) ($lang['sys.field_type_textarea'] ?? 'Textarea'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        number: <?= json_encode((string) ($lang['sys.field_type_number'] ?? 'Number'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        email: <?= json_encode((string) ($lang['sys.field_type_email'] ?? 'Email'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        phone: <?= json_encode((string) ($lang['sys.field_type_phone'] ?? 'Phone'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        date: <?= json_encode((string) ($lang['sys.field_type_date'] ?? 'Date'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        time: <?= json_encode((string) ($lang['sys.field_type_time'] ?? 'Time'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        select: <?= json_encode((string) ($lang['sys.field_type_select'] ?? 'Select'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        checkbox: <?= json_encode((string) ($lang['sys.field_type_checkbox'] ?? 'Checkboxes'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        radio: <?= json_encode((string) ($lang['sys.field_type_radio'] ?? 'Radio'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        image: <?= json_encode((string) ($lang['sys.field_type_image'] ?? 'Image'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        file: <?= json_encode((string) ($lang['sys.field_type_file'] ?? 'File'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };
    const entityTypeLabels = {
        all: <?= json_encode((string) ($lang['sys.entity_type_all'] ?? 'All entities'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        category: <?= json_encode((string) ($lang['sys.categories'] ?? 'Categories'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        page: <?= json_encode((string) ($lang['sys.pages'] ?? 'Pages'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };

    if (!stateTextarea || !tableBody) {
        return;
    }

    function deepClone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function utf8ToBase64(value) {
        const stringValue = String(value ?? '');
        if (typeof TextEncoder !== 'undefined') {
            const bytes = new TextEncoder().encode(stringValue);
            let binary = '';
            const chunkSize = 0x8000;
            for (let offset = 0; offset < bytes.length; offset += chunkSize) {
                binary += String.fromCharCode(...bytes.subarray(offset, offset + chunkSize));
            }
            return window.btoa(binary);
        }

        return window.btoa(unescape(encodeURIComponent(stringValue)));
    }

    function base64ToUtf8(value) {
        const binary = window.atob(String(value ?? '').trim());
        if (typeof TextDecoder !== 'undefined') {
            const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
            return new TextDecoder('utf-8').decode(bytes);
        }

        return decodeURIComponent(escape(binary));
    }

    function decodeStateFromTransport(value) {
        const rawValue = String(value ?? '').trim();
        if (rawValue === '') {
            return {};
        }

        const firstChar = rawValue.charAt(0);
        const jsonString = firstChar === '{' || firstChar === '['
            ? rawValue
            : base64ToUtf8(rawValue);

        return JSON.parse(jsonString);
    }

    function encodeStateForTransport(state) {
        return utf8ToBase64(JSON.stringify(state));
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizeKey(value) {
        return String(value ?? '').trim().toLowerCase();
    }

    let editorState = {};
    try {
        editorState = decodeStateFromTransport(stateTextarea.value || '');
    } catch (error) {
        console.error('Некорректное состояние формы просмотра', error);
        return;
    }

    if (!Array.isArray(editorState.properties)) {
        editorState.properties = [];
    }

    const mergeSelection = new Set();
    let currentStructurePropertyCode = '';
    let mergeDraft = null;
    let mergeSelectedCodes = [];

    const structureModalEl = document.getElementById('propertyStructureModal');
    const mergeModalEl = document.getElementById('propertyMergeModal');
    const structureModal = window.bootstrap ? new bootstrap.Modal(structureModalEl) : null;
    const mergeModal = window.bootstrap ? new bootstrap.Modal(mergeModalEl) : null;

    const structureNameInput = document.getElementById('structure-property-name');
    const structureTypeInput = document.getElementById('structure-type-name');
    const structureDescriptionInput = document.getElementById('structure-property-description');
    const structureMeta = document.getElementById('structure-property-meta');
    const structureFieldsList = document.getElementById('structure-fields-list');
    const structureSetsList = document.getElementById('structure-sets-list');

    const mergeSummary = document.getElementById('merge-selected-summary');
    const mergeTargetSelect = document.getElementById('merge-target-select');
    const mergeNameInput = document.getElementById('merge-property-name');
    const mergeTypeInput = document.getElementById('merge-type-name');
    const mergeDescriptionInput = document.getElementById('merge-property-description');
    const mergeFieldsList = document.getElementById('merge-fields-list');
    const mergeSetsList = document.getElementById('merge-sets-list');

    function getFieldTypeLabel(fieldType) {
        const normalizedType = normalizeKey(fieldType);
        return fieldTypeLabels[normalizedType] || String(fieldType || '').trim() || 'Текст';
    }

    function getEntityTypeLabel(entityType) {
        const normalizedType = normalizeKey(entityType);
        return entityTypeLabels[normalizedType] || String(entityType || '').trim() || 'Все сущности';
    }

    function getProperties() {
        return Array.isArray(editorState.properties) ? editorState.properties : [];
    }

    function getProperty(code) {
        const normalizedCode = normalizeKey(code);
        return getProperties().find((property) => normalizeKey(property.code) === normalizedCode) || null;
    }

    function isMergedSource(property) {
        return normalizeKey(property.merged_into) !== '';
    }

    function isEnabledForImport(property) {
        return !!Number(property.enabled || 0) && !isMergedSource(property);
    }

    function canSelectForMerge(property) {
        return !!property
            && !!Number(property.merge_allowed || 0)
            && !isMergedSource(property)
            && !!Number(property.enabled || 0);
    }

    function sanitizeMergeSelection() {
        Array.from(mergeSelection).forEach((code) => {
            const property = getProperty(code);
            if (!canSelectForMerge(property)) {
                mergeSelection.delete(code);
            }
        });
    }

    function syncState() {
        sanitizeMergeSelection();
        stateTextarea.value = encodeStateForTransport(editorState);
        updateSummary();
    }

    function updateSummary() {
        if (!summaryNode) {
            return;
        }

        let enabledCount = 0;
        let mergeCount = 0;
        getProperties().forEach((property) => {
            if (isEnabledForImport(property)) {
                enabledCount++;
            }
            if (Array.isArray(property.merge_sources) && property.merge_sources.length > 0) {
                mergeCount++;
            }
        });

        summaryNode.textContent = `${i18n.summaryTotal}: ${getProperties().length}, ${i18n.summaryToImport}: ${enabledCount}, ${i18n.summaryMergeGroups}: ${mergeCount}`;
    }

    function createFieldId(propertyCode) {
        return `${propertyCode}_field_${Date.now()}_${Math.floor(Math.random() * 100000)}`;
    }

    function createSetId(propertyCode) {
        return `${propertyCode}_set_${Date.now()}_${Math.floor(Math.random() * 100000)}`;
    }

    function createDefaultOption(fieldType, optionIndex = 0) {
        if (fieldType === 'select') {
            return {
                label: `${i18n.optionVariant} ${optionIndex + 1}`,
                value: `option_${optionIndex + 1}`,
                selected: optionIndex === 0 ? 1 : 0
            };
        }

        return {
            label: `${i18n.optionVariant} ${optionIndex + 1}`,
            checked: optionIndex === 0 && fieldType === 'radio' ? 1 : 0
        };
    }

    function ensureChoiceOptions(field) {
        if (!Array.isArray(field.options)) {
            field.options = [];
        }
        if (field.options.length === 0) {
            field.options.push(createDefaultOption(field.type, 0));
        }

        if (field.type === 'radio') {
            let foundChecked = false;
            field.options.forEach((option) => {
                if (Number(option.checked || 0) && !foundChecked) {
                    option.checked = 1;
                    foundChecked = true;
                } else {
                    option.checked = 0;
                }
            });
        }

        if (field.type === 'select' && !Number(field.multiple || 0)) {
            let foundSelected = false;
            field.options.forEach((option) => {
                if (Number(option.selected || 0) && !foundSelected) {
                    option.selected = 1;
                    foundSelected = true;
                } else {
                    option.selected = 0;
                }
            });
        }
    }

    function normalizeFieldForType(field) {
        field.type = normalizeKey(field.type || 'text') || 'text';
        if (!field.id) {
            field.id = createFieldId(field.source_property_code || 'property');
        }
        if (!('required' in field)) {
            field.required = 0;
        }
        if (!('multiple' in field)) {
            field.multiple = 0;
        }
        if (!('label' in field)) {
            field.label = '';
        }
        if (!('title' in field)) {
            field.title = '';
        }
        if (!('default' in field)) {
            field.default = Number(field.multiple || 0) ? [] : '';
        }
        if (!('merge_source_code' in field)) {
            field.merge_source_code = '';
        }

        if (field.type === 'select' || field.type === 'checkbox' || field.type === 'radio') {
            field.multiple = field.type === 'select' ? Number(field.multiple || 0) : 0;
            ensureChoiceOptions(field);
            if ((field.type === 'radio' || field.type === 'checkbox') && !field.title && field.label) {
                field.title = field.label;
            }
            return field;
        }

        field.options = [];
        if (field.type === 'image' || field.type === 'file') {
            field.default = '';
        } else if (Number(field.multiple || 0)) {
            if (!Array.isArray(field.default)) {
                field.default = field.default === '' || field.default == null ? [] : [String(field.default)];
            }
        } else if (Array.isArray(field.default)) {
            field.default = field.default[0] ?? '';
        }

        return field;
    }

    function createEmptyField(propertyCode, propertyName = '') {
        return normalizeFieldForType({
            id: createFieldId(propertyCode),
            source_property_code: propertyCode,
            source_field_index: -1,
            merge_source_code: '',
            type: 'text',
            label: propertyName ? `${propertyName} ${String(i18n.field || 'Field').toLowerCase()}` : i18n.newField,
            title: '',
            default: '',
            required: 0,
            multiple: 0,
            options: []
        });
    }

    function createEmptySet(propertyCode) {
        return {
            id: createSetId(propertyCode),
            code: '',
            name: i18n.newSet,
            description: '',
            source_property_codes: [propertyCode]
        };
    }

    function uniqueStrings(values) {
        return Array.from(new Set((values || []).map((value) => normalizeKey(value)).filter((value) => value !== '')));
    }

    function getFieldDisplayName(field, index) {
        const label = String(field.label || '').trim();
        const title = String(field.title || '').trim();
        if (label !== '') {
            return label;
        }
        if (title !== '') {
            return title;
        }
        return `${i18n.field} ${index + 1}`;
    }

    function getSetDisplayName(setItem) {
        const name = String(setItem.name || '').trim();
        return name !== '' ? name : i18n.untitled;
    }

    function summarizeChoiceField(field, index) {
        const type = normalizeKey(field.type);
        if (!['select', 'checkbox', 'radio'].includes(type)) {
            return '';
        }

        const optionLabels = (Array.isArray(field.options) ? field.options : [])
            .map((option) => String(option.label || '').trim())
            .filter((label) => label !== '');
        const preview = optionLabels.slice(0, 2).map((label) => escapeHtml(label)).join(', ');
        const suffix = optionLabels.length > 2 ? '…' : '';
        const label = escapeHtml(getFieldDisplayName(field, index));
        return `${label}: ${escapeHtml(getFieldTypeLabel(type))}, ${optionLabels.length} ${escapeHtml(i18n.valuesShort)} ${preview ? '(' + preview + suffix + ')' : ''}`.trim();
    }

    function summarizeFields(property) {
        const fields = Array.isArray(property.fields) ? property.fields : [];
        const choiceSummary = fields
            .map((field, index) => summarizeChoiceField(field, index))
            .filter((item) => item !== '')
            .slice(0, 2);
        if (choiceSummary.length > 0) {
            return choiceSummary.join('<br>');
        }
        return `${fields.length} ${i18n.fieldsShort}`;
    }

    function summarizeType(property) {
        const fields = Array.isArray(property.fields) ? property.fields : [];
        return `${String(property.type_name || '').trim() || i18n.untitled} · ${fields.length} ${i18n.fieldsShort}`;
    }

    function summarizeSets(property) {
        const setNames = (Array.isArray(property.sets) ? property.sets : [])
            .map((setItem) => getSetDisplayName(setItem))
            .filter((name) => name !== '');
        if (setNames.length === 0) {
            return i18n.setsNotDefined;
        }
        const preview = setNames.slice(0, 2).join(', ');
        return setNames.length > 2 ? `${preview}…` : preview;
    }

    function getPropertyName(code) {
        const property = getProperty(code);
        return property ? String(property.name || property.code || code).trim() : code;
    }

    function renderStatusCell(property) {
        if (isMergedSource(property)) {
            return `
                <div class="small text-muted">${escapeHtml(i18n.mergedInto)}</div>
                <div class="fw-semibold">${escapeHtml(getPropertyName(property.merged_into))}</div>
            `;
        }

        if (Array.isArray(property.merge_sources) && property.merge_sources.length > 0) {
            return `
                <div class="small text-muted mb-2">${escapeHtml(i18n.mergeTargetLabel)}</div>
                <div class="d-flex flex-wrap gap-1 mb-2">
                    ${property.merge_sources.map((code) => `<span class="badge bg-light text-primary border">${escapeHtml(getPropertyName(code))}</span>`).join('')}
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm js-unmerge-property" data-code="${escapeHtml(property.code)}">
                    <i class="fas fa-unlink me-1"></i>${escapeHtml(i18n.unmerge)}
                </button>
            `;
        }

        if (canSelectForMerge(property)) {
            return `<span class="badge bg-light text-success border">${escapeHtml(i18n.mergeAllowed)}</span>`;
        }

        if (String(property.merge_block_reason || '').trim() !== '') {
            return `<span class="badge bg-light text-secondary border">${escapeHtml(property.merge_block_reason)}</span>`;
        }

        return `<span class="badge bg-light text-muted border">${escapeHtml(i18n.noMerge)}</span>`;
    }

    function renderPropertyRow(property) {
        const mergeSelectable = canSelectForMerge(property);
        const rowClass = !isEnabledForImport(property) ? 'table-light' : '';
        const checked = mergeSelection.has(property.code) ? 'checked' : '';
        const includeDisabled = isMergedSource(property) ? 'disabled' : '';
        const includeChecked = Number(property.enabled || 0) ? 'checked' : '';
        const mergedBadge = isMergedSource(property)
            ? `<span class="badge bg-light text-info border ms-2">${escapeHtml(i18n.mergedBadge)}</span>`
            : '';

        return `
            <tr class="${rowClass}">
                <td>
                    ${mergeSelectable ? `
                        <div class="form-check">
                            <input class="form-check-input js-merge-select" type="checkbox" data-code="${escapeHtml(property.code)}" ${checked}>
                        </div>
                    ` : `<span class="text-muted small">—</span>`}
                </td>
                <td>
                    <div class="form-check form-switch">
                        <input
                            class="form-check-input js-property-enabled"
                            type="checkbox"
                            role="switch"
                            data-code="${escapeHtml(property.code)}"
                            ${includeChecked}
                            ${includeDisabled}
                        >
                    </div>
                </td>
                <td>
                    <button type="button" class="btn btn-link p-0 text-start text-decoration-none js-open-structure" data-code="${escapeHtml(property.code)}">
                        <span class="fw-semibold">${escapeHtml(property.name || property.code)}</span>
                    </button>
                    ${mergedBadge}
                    <div class="small text-muted mt-1">${escapeHtml(property.code || '')}</div>
                    <div class="small mt-2">${summarizeFields(property)}</div>
                </td>
                <td>
                    <button type="button" class="btn btn-outline-secondary btn-sm w-100 text-start js-open-structure" data-code="${escapeHtml(property.code)}">
                        ${escapeHtml(summarizeType(property))}
                    </button>
                </td>
                <td>
                    <button type="button" class="btn btn-outline-secondary btn-sm w-100 text-start js-open-structure" data-code="${escapeHtml(property.code)}">
                        ${escapeHtml(summarizeSets(property))}
                    </button>
                </td>
                <td>${renderStatusCell(property)}</td>
            </tr>
        `;
    }

    function renderPropertiesTable() {
        sanitizeMergeSelection();
        tableBody.innerHTML = getProperties().map(renderPropertyRow).join('');
        updateSummary();
    }

    function renderMetaBadges(property) {
        const badges = [
            {label: `${i18n.code}: ${property.code || ''}`, className: 'bg-light text-dark border'},
            {label: `${i18n.entity}: ${getEntityTypeLabel(property.entity_type || 'all')}`, className: 'bg-light text-dark border'},
            {label: Number(property.is_multiple || 0) ? i18n.fieldMultiple : i18n.fieldSingle, className: 'bg-light text-dark border'},
            {label: Number(property.is_required || 0) ? i18n.fieldRequired : i18n.fieldOptional, className: 'bg-light text-dark border'}
        ];
        if (Array.isArray(property.merge_sources) && property.merge_sources.length > 0) {
            badges.push({label: `${i18n.mergeSourcesCount}: ${property.merge_sources.length}`, className: 'bg-light text-primary border'});
        }
        structureMeta.innerHTML = badges
            .map((badge) => `<span class="badge ${badge.className}">${escapeHtml(badge.label)}</span>`)
            .join('');
    }

    function renderFieldEditorMarkup(field, index) {
        const fieldName = getFieldDisplayName(field, index);
        const sourceCode = normalizeKey(field.merge_source_code || '') || normalizeKey(field.source_property_code || '');
        const sourceLabel = sourceCode ? `${i18n.source}: ${getPropertyName(sourceCode)}` : i18n.sourceCurrentProperty;
        const scalarDefault = Array.isArray(field.default) ? field.default.join("\n") : String(field.default ?? '');
        const isChoice = ['select', 'checkbox', 'radio'].includes(normalizeKey(field.type));
        const isMedia = ['image', 'file'].includes(normalizeKey(field.type));
        const optionMarkup = isChoice
            ? `
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="fw-semibold small text-uppercase text-muted">${escapeHtml(i18n.options)}</div>
                        <button type="button" class="btn btn-outline-primary btn-sm js-option-add" data-field-index="${index}">
                            <i class="fas fa-plus me-1"></i>${escapeHtml(i18n.addOption)}
                        </button>
                    </div>
                    <div class="v-field-options">
                        ${(Array.isArray(field.options) ? field.options : []).map((option, optionIndex) => {
                            const checkedLabel = normalizeKey(field.type) === 'select' ? i18n.selected : i18n.checked;
                            return `
                                <div class="border rounded p-2 mb-2" data-option-index="${optionIndex}">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-12 col-xl-4">
                                            <label class="form-label small mb-1">${escapeHtml(i18n.label)}</label>
                                            <input type="text" class="form-control form-control-sm js-option-label" data-field-index="${index}" data-option-index="${optionIndex}" value="${escapeHtml(option.label || '')}">
                                        </div>
                                        ${normalizeKey(field.type) === 'select' ? `
                                            <div class="col-12 col-xl-3">
                                                <label class="form-label small mb-1">${escapeHtml(i18n.value)}</label>
                                                <input type="text" class="form-control form-control-sm js-option-value" data-field-index="${index}" data-option-index="${optionIndex}" value="${escapeHtml(option.value || '')}">
                                            </div>
                                        ` : ''}
                                        <div class="col-6 col-xl-2">
                                            <div class="form-check form-switch mt-xl-4">
                                                <input
                                                    class="form-check-input js-option-state"
                                                    type="checkbox"
                                                    data-field-index="${index}"
                                                    data-option-index="${optionIndex}"
                                                    ${normalizeKey(field.type) === 'select'
                                                        ? (Number(option.selected || 0) ? 'checked' : '')
                                                        : (Number(option.checked || 0) ? 'checked' : '')}
                                                >
                                                <label class="form-check-label small">${checkedLabel}</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-xl-3">
                                            <div class="btn-group btn-group-sm w-100">
                                                <button type="button" class="btn btn-outline-secondary js-option-move-up" data-field-index="${index}" data-option-index="${optionIndex}" ${optionIndex === 0 ? 'disabled' : ''}>
                                                    <i class="fas fa-arrow-up"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary js-option-move-down" data-field-index="${index}" data-option-index="${optionIndex}" ${optionIndex === ((field.options || []).length - 1) ? 'disabled' : ''}>
                                                    <i class="fas fa-arrow-down"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger js-option-remove" data-field-index="${index}" data-option-index="${optionIndex}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `
            : '';

        return `
            <div class="card border mb-3" data-field-index="${index}">
                <div class="card-header bg-white d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="fw-semibold">${escapeHtml(fieldName)}</div>
                        <div class="small text-muted">${escapeHtml(sourceLabel)}</div>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary js-field-move-up" data-field-index="${index}" ${index === 0 ? 'disabled' : ''}>
                            <i class="fas fa-arrow-up"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary js-field-move-down" data-field-index="${index}">
                            <i class="fas fa-arrow-down"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger js-field-remove" data-field-index="${index}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-xl-3">
                            <label class="form-label small">${escapeHtml(i18n.fieldType)}</label>
                            <select class="form-select form-select-sm js-field-type" data-field-index="${index}">
                                ${fieldTypes.map((fieldType) => `
                                    <option value="${escapeHtml(fieldType)}" ${normalizeKey(field.type) === normalizeKey(fieldType) ? 'selected' : ''}>
                                        ${escapeHtml(getFieldTypeLabel(fieldType))}
                                    </option>
                                `).join('')}
                            </select>
                        </div>
                        <div class="col-12 col-xl-3">
                            <label class="form-label small">${escapeHtml(i18n.caption)}</label>
                            <input type="text" class="form-control form-control-sm js-field-label" data-field-index="${index}" value="${escapeHtml(field.label || '')}">
                        </div>
                        <div class="col-12 col-xl-3">
                            <label class="form-label small">${escapeHtml(i18n.heading)}</label>
                            <input type="text" class="form-control form-control-sm js-field-title" data-field-index="${index}" value="${escapeHtml(field.title || '')}">
                        </div>
                        <div class="col-12 col-xl-3">
                            <label class="form-label small">${escapeHtml(i18n.defaultValue)}</label>
                            ${isChoice || isMedia ? `
                                <input type="text" class="form-control form-control-sm" value="${isMedia ? '' : escapeHtml(scalarDefault)}" disabled>
                            ` : Number(field.multiple || 0) ? `
                                <textarea class="form-control form-control-sm js-field-default" data-field-index="${index}" rows="2">${escapeHtml(scalarDefault)}</textarea>
                            ` : `
                                <input type="text" class="form-control form-control-sm js-field-default" data-field-index="${index}" value="${escapeHtml(scalarDefault)}">
                            `}
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-3 mt-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input js-field-required" type="checkbox" data-field-index="${index}" ${Number(field.required || 0) ? 'checked' : ''}>
                            <label class="form-check-label">${escapeHtml(i18n.fieldRequired)}</label>
                        </div>
                        ${normalizeKey(field.type) !== 'checkbox' && normalizeKey(field.type) !== 'radio' ? `
                            <div class="form-check form-switch">
                                <input class="form-check-input js-field-multiple" type="checkbox" data-field-index="${index}" ${Number(field.multiple || 0) ? 'checked' : ''} ${isMedia ? 'disabled' : ''}>
                                <label class="form-check-label">${escapeHtml(i18n.fieldMultiple)}</label>
                            </div>
                        ` : ''}
                    </div>
                    ${optionMarkup}
                </div>
            </div>
        `;
    }

    function renderSetEditorMarkup(setItem, index, propertyCode) {
        const sourceNames = uniqueStrings(setItem.source_property_codes).map((code) => getPropertyName(code));
        const sourceLabel = sourceNames.length > 0 ? sourceNames.join(', ') : getPropertyName(propertyCode);
        return `
            <div class="border rounded p-3 mb-3" data-set-index="${index}">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-xl-4">
                        <label class="form-label small">${escapeHtml(i18n.setName)}</label>
                        <input type="text" class="form-control form-control-sm js-set-name" data-set-index="${index}" value="${escapeHtml(setItem.name || '')}">
                    </div>
                    <div class="col-12 col-xl-6">
                        <label class="form-label small">${escapeHtml(i18n.description)}</label>
                        <input type="text" class="form-control form-control-sm js-set-description" data-set-index="${index}" value="${escapeHtml(setItem.description || '')}">
                    </div>
                    <div class="col-12 col-xl-2">
                        <button type="button" class="btn btn-outline-danger btn-sm w-100 js-set-remove" data-set-index="${index}">
                            <i class="fas fa-trash me-1"></i>${escapeHtml(i18n.delete)}
                        </button>
                    </div>
                </div>
                <div class="small text-muted mt-2">${escapeHtml(i18n.source)}: ${escapeHtml(sourceLabel)}</div>
            </div>
        `;
    }

    function renderStructureModal() {
        const property = getProperty(currentStructurePropertyCode);
        if (!property) {
            return;
        }

        document.getElementById('propertyStructureModalLabel').textContent = (i18n.propertyStructureWithName || 'Property structure: %s').replace('%s', property.name || property.code);
        structureNameInput.value = property.name || '';
        structureTypeInput.value = property.type_name || '';
        structureDescriptionInput.value = property.description || '';
        renderMetaBadges(property);

        const fields = Array.isArray(property.fields) ? property.fields : [];
        structureFieldsList.innerHTML = fields.map((field, index) => renderFieldEditorMarkup(normalizeFieldForType(field), index)).join('')
            || `<div class="text-muted small">${escapeHtml(i18n.noFieldsYet)}</div>`;

        const sets = Array.isArray(property.sets) ? property.sets : [];
        structureSetsList.innerHTML = sets.map((setItem, index) => renderSetEditorMarkup(setItem, index, property.code)).join('')
            || `<div class="text-muted small">${escapeHtml(i18n.noSetsYet)}</div>`;
    }

    function openStructureModal(propertyCode) {
        currentStructurePropertyCode = propertyCode;
        renderStructureModal();
        if (structureModal) {
            structureModal.show();
        }
    }

    function moveItem(array, fromIndex, toIndex) {
        if (!Array.isArray(array)) {
            return;
        }
        if (toIndex < 0 || toIndex >= array.length || fromIndex < 0 || fromIndex >= array.length) {
            return;
        }
        const [item] = array.splice(fromIndex, 1);
        array.splice(toIndex, 0, item);
    }

    function getStructureProperty() {
        return getProperty(currentStructurePropertyCode);
    }

    function getFieldCollection(scope) {
        if (scope === 'merge') {
            return Array.isArray(mergeDraft?.fields) ? mergeDraft.fields : [];
        }
        const property = getStructureProperty();
        return Array.isArray(property?.fields) ? property.fields : [];
    }

    function getSetCollection(scope) {
        if (scope === 'merge') {
            return Array.isArray(mergeDraft?.sets) ? mergeDraft.sets : [];
        }
        const property = getStructureProperty();
        return Array.isArray(property?.sets) ? property.sets : [];
    }

    function rerenderScope(scope) {
        if (scope === 'merge') {
            renderMergeDraft();
            return;
        }
        renderStructureModal();
    }

    function handleFieldInput(scope, event) {
        const fields = getFieldCollection(scope);
        const fieldIndex = Number(event.target.dataset.fieldIndex);
        if (!Array.isArray(fields) || Number.isNaN(fieldIndex) || !fields[fieldIndex]) {
            return false;
        }

        const field = fields[fieldIndex];
        if (event.target.classList.contains('js-field-type')) {
            field.type = event.target.value;
            normalizeFieldForType(field);
            rerenderScope(scope);
            syncState();
            return true;
        }

        if (event.target.classList.contains('js-field-label')) {
            field.label = event.target.value;
            syncState();
            return true;
        }

        if (event.target.classList.contains('js-field-title')) {
            field.title = event.target.value;
            syncState();
            return true;
        }

        if (event.target.classList.contains('js-field-default')) {
            field.default = Number(field.multiple || 0)
                ? event.target.value.split(/\r?\n/).map((value) => value.trim()).filter((value) => value !== '')
                : event.target.value;
            syncState();
            return true;
        }

        if (event.target.classList.contains('js-field-required')) {
            field.required = event.target.checked ? 1 : 0;
            syncState();
            return true;
        }

        if (event.target.classList.contains('js-field-multiple')) {
            field.multiple = event.target.checked ? 1 : 0;
            normalizeFieldForType(field);
            rerenderScope(scope);
            syncState();
            return true;
        }

        if (event.target.classList.contains('js-option-label')) {
            const optionIndex = Number(event.target.dataset.optionIndex);
            if (Number.isNaN(optionIndex) || !field.options[optionIndex]) {
                return false;
            }
            field.options[optionIndex].label = event.target.value;
            syncState();
            return true;
        }

        if (event.target.classList.contains('js-option-value')) {
            const optionIndex = Number(event.target.dataset.optionIndex);
            if (Number.isNaN(optionIndex) || !field.options[optionIndex]) {
                return false;
            }
            field.options[optionIndex].value = event.target.value;
            syncState();
            return true;
        }

        if (event.target.classList.contains('js-option-state')) {
            const optionIndex = Number(event.target.dataset.optionIndex);
            if (Number.isNaN(optionIndex) || !field.options[optionIndex]) {
                return false;
            }
            if (normalizeKey(field.type) === 'select') {
                if (!Number(field.multiple || 0) && event.target.checked) {
                    field.options.forEach((option, index) => {
                        option.selected = index === optionIndex ? 1 : 0;
                    });
                } else {
                    field.options[optionIndex].selected = event.target.checked ? 1 : 0;
                }
            } else if (normalizeKey(field.type) === 'radio') {
                field.options.forEach((option, index) => {
                    option.checked = index === optionIndex && event.target.checked ? 1 : 0;
                });
            } else {
                field.options[optionIndex].checked = event.target.checked ? 1 : 0;
            }
            rerenderScope(scope);
            syncState();
            return true;
        }

        return false;
    }

    function handleFieldClicks(scope, event) {
        const fields = getFieldCollection(scope);
        if (!Array.isArray(fields)) {
            return false;
        }

        const fieldButton = event.target.closest('[data-field-index]');
        const optionButton = event.target.closest('[data-option-index]');
        const fieldIndex = fieldButton ? Number(fieldButton.dataset.fieldIndex) : Number.NaN;
        const optionIndex = optionButton ? Number(optionButton.dataset.optionIndex) : Number.NaN;

        if (event.target.closest('.js-field-move-up')) {
            moveItem(fields, fieldIndex, fieldIndex - 1);
            rerenderScope(scope);
            syncState();
            return true;
        }

        if (event.target.closest('.js-field-move-down')) {
            moveItem(fields, fieldIndex, fieldIndex + 1);
            rerenderScope(scope);
            syncState();
            return true;
        }

        if (event.target.closest('.js-field-remove')) {
            fields.splice(fieldIndex, 1);
            rerenderScope(scope);
            syncState();
            return true;
        }

        if (event.target.closest('.js-option-add')) {
            const field = fields[fieldIndex];
            if (!field) {
                return false;
            }
            if (!Array.isArray(field.options)) {
                field.options = [];
            }
            field.options.push(createDefaultOption(normalizeKey(field.type), field.options.length));
            ensureChoiceOptions(field);
            rerenderScope(scope);
            syncState();
            return true;
        }

        if (event.target.closest('.js-option-remove')) {
            const field = fields[fieldIndex];
            if (!field || !Array.isArray(field.options)) {
                return false;
            }
            field.options.splice(optionIndex, 1);
            ensureChoiceOptions(field);
            rerenderScope(scope);
            syncState();
            return true;
        }

        if (event.target.closest('.js-option-move-up')) {
            const field = fields[fieldIndex];
            if (!field || !Array.isArray(field.options)) {
                return false;
            }
            moveItem(field.options, optionIndex, optionIndex - 1);
            rerenderScope(scope);
            syncState();
            return true;
        }

        if (event.target.closest('.js-option-move-down')) {
            const field = fields[fieldIndex];
            if (!field || !Array.isArray(field.options)) {
                return false;
            }
            moveItem(field.options, optionIndex, optionIndex + 1);
            rerenderScope(scope);
            syncState();
            return true;
        }

        return false;
    }

    function handleSetInput(scope, event) {
        const sets = getSetCollection(scope);
        const setIndex = Number(event.target.dataset.setIndex);
        if (!Array.isArray(sets) || Number.isNaN(setIndex) || !sets[setIndex]) {
            return false;
        }

        if (event.target.classList.contains('js-set-name')) {
            sets[setIndex].name = event.target.value;
            syncState();
            return true;
        }

        if (event.target.classList.contains('js-set-description')) {
            sets[setIndex].description = event.target.value;
            syncState();
            return true;
        }

        return false;
    }

    function handleSetClick(scope, event) {
        const sets = getSetCollection(scope);
        if (!Array.isArray(sets)) {
            return false;
        }
        if (event.target.closest('.js-set-remove')) {
            const setIndex = Number(event.target.closest('.js-set-remove').dataset.setIndex);
            if (Number.isNaN(setIndex) || !sets[setIndex]) {
                return false;
            }
            sets.splice(setIndex, 1);
            rerenderScope(scope);
            syncState();
            return true;
        }
        return false;
    }

    function mergeSetsForDraft(target, sourceProperties) {
        const map = new Map();
        const allSets = [...(Array.isArray(target.sets) ? deepClone(target.sets) : [])];
        sourceProperties.forEach((property) => {
            (Array.isArray(property.sets) ? property.sets : []).forEach((setItem) => {
                allSets.push(deepClone(setItem));
            });
        });

        allSets.forEach((setItem, index) => {
            const key = normalizeKey(setItem.name || setItem.code || `set_${index}`);
            if (!map.has(key)) {
                map.set(key, {
                    id: setItem.id || createSetId(target.code),
                    code: setItem.code || '',
                    name: setItem.name || '',
                    description: setItem.description || '',
                    source_property_codes: uniqueStrings(setItem.source_property_codes || [target.code])
                });
                return;
            }

            const current = map.get(key);
            if (!current.description && setItem.description) {
                current.description = setItem.description;
            }
            current.source_property_codes = uniqueStrings([...(current.source_property_codes || []), ...(setItem.source_property_codes || [])]);
        });

        return Array.from(map.values());
    }

    function buildMergeDraft(selectedCodes, targetCode) {
        const target = deepClone(getProperty(targetCode));
        if (!target) {
            return null;
        }

        const sourceProperties = selectedCodes
            .filter((code) => normalizeKey(code) !== normalizeKey(targetCode))
            .map((code) => getProperty(code))
            .filter((property) => !!property);

        const fields = Array.isArray(target.fields) ? deepClone(target.fields) : [];
        sourceProperties.forEach((property) => {
            const sourceFields = Array.isArray(property.fields) ? property.fields : [];
            sourceFields.forEach((field) => {
                const clone = deepClone(field);
                clone.id = createFieldId(target.code);
                clone.source_property_code = property.code;
                clone.merge_source_code = property.code;
                fields.push(normalizeFieldForType(clone));
            });
        });

        return {
            selectedCodes: [...selectedCodes],
            targetCode: target.code,
            name: target.name || '',
            type_name: target.type_name || (target.name ? `${target.name} тип` : 'Новый тип свойства'),
            description: target.description || '',
            fields: fields.map((field) => normalizeFieldForType(field)),
            sets: mergeSetsForDraft(target, sourceProperties)
        };
    }

    function renderMergeDraft() {
        if (!mergeDraft) {
            return;
        }

        mergeSummary.innerHTML = `
            <div class="fw-semibold mb-2">Выбрано свойств: ${mergeSelectedCodes.length}</div>
            <div class="d-flex flex-wrap gap-1">
                ${mergeSelectedCodes.map((code) => `<span class="badge bg-light text-dark border">${escapeHtml(getPropertyName(code))}</span>`).join('')}
            </div>
        `;

        mergeTargetSelect.innerHTML = mergeSelectedCodes.map((code) => `
            <option value="${escapeHtml(code)}" ${normalizeKey(code) === normalizeKey(mergeDraft.targetCode) ? 'selected' : ''}>
                ${escapeHtml(getPropertyName(code))}
            </option>
        `).join('');

        mergeNameInput.value = mergeDraft.name || '';
        mergeTypeInput.value = mergeDraft.type_name || '';
        mergeDescriptionInput.value = mergeDraft.description || '';
        mergeFieldsList.innerHTML = mergeDraft.fields.map((field, index) => renderFieldEditorMarkup(normalizeFieldForType(field), index)).join('');
        mergeSetsList.innerHTML = mergeDraft.sets.map((setItem, index) => renderSetEditorMarkup(setItem, index, mergeDraft.targetCode)).join('');
    }

    function openMergeDialog() {
        const selectedCodes = Array.from(mergeSelection).filter((code) => canSelectForMerge(getProperty(code)));
        if (selectedCodes.length < 2) {
            window.alert(i18n.mergeNeedTwo || 'Select at least two available properties to merge.');
            return;
        }

        mergeSelectedCodes = selectedCodes;
        mergeDraft = buildMergeDraft(selectedCodes, selectedCodes[0]);
        if (!mergeDraft) {
            window.alert(i18n.mergePrepareFailed || 'Failed to prepare merge.');
            return;
        }

        renderMergeDraft();
        if (mergeModal) {
            mergeModal.show();
        }
    }

    function applyMergeDraft() {
        if (!mergeDraft || mergeSelectedCodes.length < 2) {
            return;
        }

        const target = getProperty(mergeDraft.targetCode);
        if (!target) {
            window.alert(i18n.mergeTargetNotFound || 'Target property was not found.');
            return;
        }

        const sourceCodes = mergeSelectedCodes.filter((code) => normalizeKey(code) !== normalizeKey(target.code));
        const invalidNestedSource = sourceCodes.find((code) => {
            const property = getProperty(code);
            return property && Array.isArray(property.merge_sources) && property.merge_sources.length > 0;
        });
        if (invalidNestedSource) {
            window.alert((i18n.mergeNestedTarget || 'Property "%s" is already the target of another merge. Unmerge it first.').replace('%s', getPropertyName(invalidNestedSource)));
            return;
        }

        target.name = mergeDraft.name;
        target.type_name = mergeDraft.type_name;
        target.description = mergeDraft.description;
        target.fields = mergeDraft.fields.map((field) => normalizeFieldForType(deepClone(field)));
        target.sets = deepClone(mergeDraft.sets);
        target.merge_sources = uniqueStrings([...(target.merge_sources || []), ...sourceCodes]);
        target.enabled = 1;

        sourceCodes.forEach((code) => {
            const source = getProperty(code);
            if (!source) {
                return;
            }
            source.merged_into = target.code;
            source.enabled = 0;
        });

        mergeSelection.clear();
        mergeDraft = null;
        mergeSelectedCodes = [];
        syncState();
        renderPropertiesTable();
        if (mergeModal) {
            mergeModal.hide();
        }
    }

    function unmergeProperty(propertyCode) {
        const property = getProperty(propertyCode);
        if (!property || !Array.isArray(property.merge_sources) || property.merge_sources.length === 0) {
            return;
        }

        const sourceCodes = [...property.merge_sources];
        property.fields = (Array.isArray(property.fields) ? property.fields : []).filter(
            (field) => !sourceCodes.includes(normalizeKey(field.merge_source_code || ''))
        );
        property.sets = (Array.isArray(property.sets) ? property.sets : [])
            .map((setItem) => {
                const sourceCodesLeft = uniqueStrings(
                    (setItem.source_property_codes || []).filter((code) => !sourceCodes.includes(normalizeKey(code)))
                );
                return {
                    ...setItem,
                    source_property_codes: sourceCodesLeft
                };
            })
            .filter((setItem) => Array.isArray(setItem.source_property_codes) && setItem.source_property_codes.length > 0);

        sourceCodes.forEach((sourceCode) => {
            const source = getProperty(sourceCode);
            if (!source) {
                return;
            }
            source.merged_into = '';
            source.enabled = 1;
        });

        property.merge_sources = [];
        syncState();
        renderPropertiesTable();
    }

    tableBody.addEventListener('click', (event) => {
        const structureButton = event.target.closest('.js-open-structure');
        if (structureButton) {
            openStructureModal(structureButton.dataset.code);
            return;
        }

        const unmergeButton = event.target.closest('.js-unmerge-property');
        if (unmergeButton) {
            unmergeProperty(unmergeButton.dataset.code);
        }
    });

    tableBody.addEventListener('change', (event) => {
        if (event.target.classList.contains('js-merge-select')) {
            const code = event.target.dataset.code;
            if (event.target.checked) {
                mergeSelection.add(code);
            } else {
                mergeSelection.delete(code);
            }
            return;
        }

        if (event.target.classList.contains('js-property-enabled')) {
            const property = getProperty(event.target.dataset.code);
            if (!property) {
                return;
            }
            property.enabled = event.target.checked ? 1 : 0;
            if (!event.target.checked) {
                mergeSelection.delete(property.code);
            }
            syncState();
            renderPropertiesTable();
        }
    });

    document.getElementById('select-all-mergeable')?.addEventListener('click', () => {
        mergeSelection.clear();
        getProperties().forEach((property) => {
            if (canSelectForMerge(property)) {
                mergeSelection.add(property.code);
            }
        });
        renderPropertiesTable();
    });

    document.getElementById('clear-merge-selection')?.addEventListener('click', () => {
        mergeSelection.clear();
        renderPropertiesTable();
    });

    document.getElementById('open-merge-modal')?.addEventListener('click', openMergeDialog);

    structureNameInput?.addEventListener('input', () => {
        const property = getStructureProperty();
        if (!property) {
            return;
        }
        property.name = structureNameInput.value;
        syncState();
    });

    structureTypeInput?.addEventListener('input', () => {
        const property = getStructureProperty();
        if (!property) {
            return;
        }
        property.type_name = structureTypeInput.value;
        syncState();
    });

    structureDescriptionInput?.addEventListener('input', () => {
        const property = getStructureProperty();
        if (!property) {
            return;
        }
        property.description = structureDescriptionInput.value;
        syncState();
    });

    document.getElementById('structure-add-field')?.addEventListener('click', () => {
        const property = getStructureProperty();
        if (!property) {
            return;
        }
        if (!Array.isArray(property.fields)) {
            property.fields = [];
        }
        property.fields.push(createEmptyField(property.code, property.name || property.code));
        renderStructureModal();
        syncState();
    });

    document.getElementById('structure-add-set')?.addEventListener('click', () => {
        const property = getStructureProperty();
        if (!property) {
            return;
        }
        if (!Array.isArray(property.sets)) {
            property.sets = [];
        }
        property.sets.push(createEmptySet(property.code));
        renderStructureModal();
        syncState();
    });

    structureModalEl?.addEventListener('input', (event) => {
        handleFieldInput('structure', event) || handleSetInput('structure', event);
    });

    structureModalEl?.addEventListener('change', (event) => {
        handleFieldInput('structure', event) || handleSetInput('structure', event);
    });

    structureModalEl?.addEventListener('click', (event) => {
        handleFieldClicks('structure', event) || handleSetClick('structure', event);
    });

    structureModalEl?.addEventListener('hidden.bs.modal', () => {
        renderPropertiesTable();
    });

    mergeTargetSelect?.addEventListener('change', () => {
        mergeDraft = buildMergeDraft(mergeSelectedCodes, mergeTargetSelect.value);
        renderMergeDraft();
    });

    mergeNameInput?.addEventListener('input', () => {
        if (!mergeDraft) {
            return;
        }
        mergeDraft.name = mergeNameInput.value;
    });

    mergeTypeInput?.addEventListener('input', () => {
        if (!mergeDraft) {
            return;
        }
        mergeDraft.type_name = mergeTypeInput.value;
    });

    mergeDescriptionInput?.addEventListener('input', () => {
        if (!mergeDraft) {
            return;
        }
        mergeDraft.description = mergeDescriptionInput.value;
    });

    document.getElementById('merge-add-field')?.addEventListener('click', () => {
        if (!mergeDraft) {
            return;
        }
        mergeDraft.fields.push(createEmptyField(mergeDraft.targetCode, mergeDraft.name || mergeDraft.targetCode));
        renderMergeDraft();
    });

    document.getElementById('merge-add-set')?.addEventListener('click', () => {
        if (!mergeDraft) {
            return;
        }
        mergeDraft.sets.push(createEmptySet(mergeDraft.targetCode));
        renderMergeDraft();
    });

    mergeModalEl?.addEventListener('input', (event) => {
        handleFieldInput('merge', event) || handleSetInput('merge', event);
    });

    mergeModalEl?.addEventListener('change', (event) => {
        handleFieldInput('merge', event) || handleSetInput('merge', event);
    });

    mergeModalEl?.addEventListener('click', (event) => {
        handleFieldClicks('merge', event) || handleSetClick('merge', event);
    });

    document.getElementById('merge-apply-button')?.addEventListener('click', applyMergeDraft);

    document.getElementById('property-definitions-confirm-form')?.addEventListener('submit', (event) => {
        syncState();
        const readyProperties = getProperties().filter((property) => isEnabledForImport(property));
        if (readyProperties.length === 0) {
            event.preventDefault();
            window.alert('После фильтрации не осталось ни одного свойства для импорта.');
        }
    });

    renderPropertiesTable();
    syncState();
})();
</script>
