/* Управление панелью пересчета фильтров (ФИНАЛЬНАЯ ВЕРСИЯ) */
$(document).ready(function () {
    setActiveNavLink('/admin/filters_panel');
    const t = (key, fallback) => AppCore.getLangVar(key) || fallback;

    const regenerateBtn = $('#regenerate-btn');
    const regenerateAllBtn = $('#regenerate-all-btn');
    const categorySelect = $('#category-select');
    const logOutput = $('#log-output');
    const tableContainer = $('#filters-table-container');
    
    const modalTitle = $('#filterDetailModalLabel span');
    const modalBody = $('#filterDetailModal .modal-body');

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizeLabel(value) {
        return String(value === null || value === undefined ? '' : value).replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function shouldRenderFieldLabel(fieldLabel, propertyName) {
        const normalizedFieldLabel = normalizeLabel(fieldLabel);
        return normalizedFieldLabel !== '' && normalizedFieldLabel !== normalizeLabel(propertyName);
    }

    $('#filterDetailModal')
        .on('show.bs.modal', function () {
            $('body').addClass('filter-detail-modal-open');
        })
        .on('hidden.bs.modal', function () {
            $('body').removeClass('filter-detail-modal-open');
        });

    function log(message, status = 'info') {
        let color = '#ffffff';
        if (status === 'error') color = '#ff8a80';
        if (status === 'success') color = '#b9f6ca';
        const timestamp = new Date().toLocaleTimeString();
        logOutput.append(`[${timestamp}] <span style="color:${color};">${message}</span>\n`);
        logOutput.scrollTop(logOutput[0].scrollHeight);
    }

    function updateFiltersTable() {
        log(t('sys.filters_table_updating', 'Updating table...'));
        AppCore.sendAjaxRequest('/admin/get_filters_table', {}, 'GET', 'html', 
            function(response) {
                tableContainer.html(response);
                log(t('sys.filters_table_updated', 'Table updated successfully.'), 'success');
            },
            function() {
                log(t('sys.filters_table_update_failed', 'Failed to update table.'), 'error');
            }
        );
    }

    function runRegeneration(entityId) {
        return new Promise((resolve) => {
            if (entityId <= 0) {
                log(t('sys.filters_category_id_invalid', 'Category ID cannot be 0.'), 'error');
                resolve();
                return;
            }
            log(`${t('sys.filters_regenerate_category_prefix', 'Starting regeneration for category ID')}: ${entityId}...`);
            regenerateBtn.prop('disabled', true);
            regenerateAllBtn.prop('disabled', true);
            AppCore.sendAjaxRequest('/admin/regenerate_filters', { entity_type: 'category', entity_id: entityId }, 'POST', 'json',
                function (response) {
                    if (response && response.status === 'success') {
                        log(`${t('sys.success', 'Success')}: ${response.message}`, 'success');
                    } else {
                        log(`${t('sys.error', 'Error')}: ${response.message || t('sys.unknown_server_response', 'Unknown server response.')}`, 'error');
                    }
                    regenerateBtn.prop('disabled', false);
                    regenerateAllBtn.prop('disabled', false);
                    resolve();
                },
                function (xhr, status, error) {
                    log(`${t('sys.ajax_critical_error', 'Critical AJAX request error')}: ${error}`, 'error');
                    regenerateBtn.prop('disabled', false);
                    regenerateAllBtn.prop('disabled', false);
                    resolve();
                }
            );
        });
    }

    regenerateBtn.on('click', async function () {
        const selectedCategoryId = parseInt(categorySelect.val());
        if (!selectedCategoryId) {
            log(t('sys.filters_select_category', 'Please choose a category from the list.'), 'error');
            return;
        }
        await runRegeneration(selectedCategoryId);
        updateFiltersTable();
    });

    regenerateAllBtn.on('click', async function () {
        log(t('sys.filters_regenerate_all_start', 'Starting full regeneration for all root categories...'));
        const categories = categorySelect.find('option');
        for (const option of categories) {
            const categoryId = parseInt($(option).val());
            if (categoryId > 0) {
                await runRegeneration(categoryId);
            }
        }
        log(t('sys.filters_regenerate_all_done', 'Full regeneration completed.'), 'success');
        updateFiltersTable();
    });

    $(document).on('click', '.view-filter-details', function() {
        const entityId = $(this).data('entity-id');
        const entityName = $(this).data('entity-name');

        modalTitle.text(entityName + ' (ID: ' + entityId + ')');
        modalBody.html('<p class="text-center">' + t('sys.loading', 'Loading...') + '</p>');
        
        AppCore.sendAjaxRequest('/admin/get_filter_details', {entity_id: entityId}, 'POST', 'json',
            function(response) {
                if (response.status === 'success' && response.data.length > 0) {
                    let html = '<dl class="dl-horizontal">';
                    response.data.forEach(function(filter) {
                        try {
                            const payload = JSON.parse(filter.filter_options);
                            const fields = Array.isArray(payload.fields)
                                ? payload.fields
                                : [payload];
                            const propertyName = filter.property_name || '';
                            html += `<dt>${escapeHtml(propertyName)}</dt>`;
                            let fieldsHtml = '';
                            fields.forEach(field => {
                                const fieldLabel = field.label || propertyName;
                                const renderFieldLabel = shouldRenderFieldLabel(fieldLabel, propertyName);
                                const fieldTitle = renderFieldLabel ? `<strong>${escapeHtml(fieldLabel)}</strong>: ` : '';
                                if (field.filter_type === 'range') {
                                    fieldsHtml += `<div>${fieldTitle}${t('sys.range_from', 'range from')} ${escapeHtml(field.min_value)} ${t('sys.range_to', 'to')} ${escapeHtml(field.max_value)}, ${t('sys.found_count', 'found')} ${parseInt(field.count || 0, 10)} ${t('sys.items_short', 'items')}</div>`;
                                    return;
                                }
                                if (field.filter_type === 'options' && Array.isArray(field.options) && field.options.length > 0) {
                                    let optionsHtml = '<ul>';
                                    field.options.forEach(opt => {
                                        optionsHtml += `<li>${escapeHtml(opt.label)} (ID: ${escapeHtml(opt.id)}) - ${parseInt(opt.count || 0, 10)} ${t('sys.items_short', 'items')}</li>`;
                                    });
                                    optionsHtml += '</ul>';
                                    fieldsHtml += `<div>${renderFieldLabel ? `<strong>${escapeHtml(fieldLabel)}</strong>` : ''}${optionsHtml}</div>`;
                                }
                            });
                            if (fieldsHtml) {
                                html += `<dd>${fieldsHtml}</dd>`;
                            } else {
                                html += `<dd>${t('sys.no_data_to_display', 'No data to display.')}</dd>`;
                            }
                        } catch (e) {
                            console.error(t('sys.filters_json_parse_error', 'Filter JSON parsing error:'), filter);
                            html += `<dd>${t('sys.display_error', 'Display error.')}</dd>`;
                        }
                    });
                    html += '</dl>';
                    modalBody.html(html);
                } else {
                    modalBody.html('<p class="text-center">' + t('sys.filters_no_generated', 'No generated filters for this category.') + '</p>');
                }
            },
            function() {
                modalBody.html('<p class="text-center text-danger">' + t('sys.server_request_error', 'Server request error.') + '</p>');
            }
        );
    });
});
