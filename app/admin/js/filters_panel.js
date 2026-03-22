/* Управление панелью пересчета фильтров (ФИНАЛЬНАЯ ВЕРСИЯ) */
$(document).ready(function () {
    setActiveNavLink('/admin/filters_panel');

    const regenerateBtn = $('#regenerate-btn');
    const regenerateAllBtn = $('#regenerate-all-btn');
    const categorySelect = $('#category-select');
    const logOutput = $('#log-output');
    const tableContainer = $('#filters-table-container');
    
    const modalInstance = new bootstrap.Modal(document.getElementById('filterDetailModal'));
    const modalTitle = $('#filterDetailModalLabel span');
    const modalBody = $('#filterDetailModal .modal-body');

    function log(message, status = 'info') {
        let color = '#ffffff';
        if (status === 'error') color = '#ff8a80';
        if (status === 'success') color = '#b9f6ca';
        const timestamp = new Date().toLocaleTimeString();
        logOutput.append(`[${timestamp}] <span style="color:${color};">${message}</span>\n`);
        logOutput.scrollTop(logOutput[0].scrollHeight);
    }

    function updateFiltersTable() {
        log('Обновление таблицы...');
        AppCore.sendAjaxRequest('/admin/get_filters_table', {}, 'GET', 'html', 
            function(response) {
                tableContainer.html(response);
                log('Таблица успешно обновлена.', 'success');
            },
            function() {
                log('Не удалось обновить таблицу.', 'error');
            }
        );
    }

    function runRegeneration(entityId) {
        return new Promise((resolve) => {
            if (entityId <= 0) {
                log('ID категории не может быть 0.', 'error');
                resolve();
                return;
            }
            log(`Запускаем пересчет для категории ID: ${entityId}...`);
            regenerateBtn.prop('disabled', true);
            regenerateAllBtn.prop('disabled', true);
            AppCore.sendAjaxRequest('/admin/regenerate_filters', { entity_type: 'category', entity_id: entityId }, 'POST', 'json',
                function (response) {
                    if (response && response.status === 'success') {
                        log(`УСПЕХ: ${response.message}`, 'success');
                    } else {
                        log(`ОШИБКА: ${response.message || 'Неизвестный ответ от сервера.'}`, 'error');
                    }
                    regenerateBtn.prop('disabled', false);
                    regenerateAllBtn.prop('disabled', false);
                    resolve();
                },
                function (xhr, status, error) {
                    log(`Критическая ошибка AJAX запроса: ${error}`, 'error');
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
            log('Пожалуйста, выберите категорию из списка.', 'error');
            return;
        }
        await runRegeneration(selectedCategoryId);
        updateFiltersTable();
    });

    regenerateAllBtn.on('click', async function () {
        log('Запускаем полный пересчет для ВСЕХ корневых категорий...');
        const categories = categorySelect.find('option');
        for (const option of categories) {
            const categoryId = parseInt($(option).val());
            if (categoryId > 0) {
                await runRegeneration(categoryId);
            }
        }
        log('Полный пересчет завершен.', 'success');
        updateFiltersTable();
    });

    $(document).on('click', '.view-filter-details', function() {
        const entityId = $(this).data('entity-id');
        const entityName = $(this).data('entity-name');

        modalTitle.text(entityName + ' (ID: ' + entityId + ')');
        modalBody.html('<p class="text-center">Загрузка...</p>');
        
        // Вручную открываем окно
        modalInstance.show(); 
        
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
                            html += `<dt>${filter.property_name}</dt>`;
                            let fieldsHtml = '';
                            fields.forEach(field => {
                                const fieldLabel = field.label || filter.property_name;
                                if (field.filter_type === 'range') {
                                    fieldsHtml += `<div><strong>${fieldLabel}</strong>: диапазон от ${field.min_value} до ${field.max_value}, найдено ${field.count || 0} шт.</div>`;
                                    return;
                                }
                                if (field.filter_type === 'options' && Array.isArray(field.options) && field.options.length > 0) {
                                    let optionsHtml = '<ul>';
                                    field.options.forEach(opt => {
                                        optionsHtml += `<li>${opt.label} (ID: ${opt.id}) - ${opt.count} шт.</li>`;
                                    });
                                    optionsHtml += '</ul>';
                                    fieldsHtml += `<div><strong>${fieldLabel}</strong>${optionsHtml}</div>`;
                                }
                            });
                            if (fieldsHtml) {
                                html += `<dd>${fieldsHtml}</dd>`;
                            } else {
                                html += `<dd>Нет данных для отображения.</dd>`;
                            }
                        } catch (e) {
                            console.error('Ошибка парсинга JSON для фильтра:', filter);
                            html += `<dd>Ошибка отображения данных.</dd>`;
                        }
                    });
                    html += '</dl>';
                    modalBody.html(html);
                } else {
                    modalBody.html('<p class="text-center">Для этой категории нет сгенерированных фильтров.</p>');
                }
            },
            function() {
                modalBody.html('<p class="text-center text-danger">Ошибка запроса к серверу.</p>');
            }
        );
    });
});
