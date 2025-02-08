$(document).ready(function () {

    var nameSuffix = '';
    if (window.location.href.includes('/edit_property/')) {
        nameSuffix = 'default';
    } else {
        nameSuffix = 'value';
    }

    // Открытие модального окна и добавление существующих options в форму
    $(document).on('click', '.openModal', function () {
        var selectId = $(this).data('select-id');
        createModal(selectId);
    });

    // Сохранение новых options и удаление модального окна
    $(document).on('click', '#saveOptions', function () {
        $('input[name=property_data_changed]').val(1);
        var selectId = $(this).data('select-id');
        var defaultId = selectId + '_' + nameSuffix;
        var new_values = '';
        $('#' + selectId).empty(); // Очистка select
        $('#options-container .option-field').each(function () {
            var key = $(this).find('.key').val();
            var value = $(this).find('.value').val();
            if (key && value) {
                $('#' + selectId).append(new Option(value, key)); // Добавление option в select
                new_values = new_values + '{|}' + key + '=' + value;
            }
        });
        $('#' + defaultId).val(new_values);
        $('#select_modal_' + selectId).modal('hide');
        $('#select_modal_' + selectId).remove();
    });

    // Удаление поля ввода
    $(document).on('click', '.removeOptionField', function () {
        $(this).closest('.option-field').remove();
    });

    // Удаление модального окна после его закрытия
    $(document).on('hidden.bs.modal', '[id^="select_modal_"]', function () {
        $(this).remove();
    });

    // Обработчик клика по кнопке добавления/удаления checkbox
    $(document).on('click', '[id$="_add_checkbox_values"]', function () {
        $('input[name=property_data_changed]').val(1);
        var $button = $(this);
        var general_name = $button.attr('data-general-name');
        var $container = $button.closest('.checkbox_container');
        var $newContainer = $container.clone();
        var $parentContainer = $button.closest('.parent_checkbox_container');
        if ($button.find('i').hasClass('fa-plus')) {
            $newContainer.find('input[type="checkbox"]').prop('checked', false);
            $newContainer.find('input[type="text"]').val('');
            let $multi = $newContainer.find('.form-check-input[name$="_multiple]"]');
            $multi.parent('div').prev('span').remove();
            $multi.parent('div').remove();
            $newContainer.find('.fa-plus').removeClass('fa-plus').addClass('fa-minus');
            $newbutton = $newContainer.find('button');
            $newbutton.attr('id', Date.now() + $button.attr('id'));
            $container.parent('div').append($newContainer);
        } else if ($button.find('i').hasClass('fa-minus')) {
            $container.remove();
        }
        updateCheckboxValues('.checkbox_container', $parentContainer, general_name, 'checkbox');
    });

    // Обработчик клика по кнопке добавления/удаления radiobox
    $(document).on('click', '[id$="_add_radio_values"]', function () {
        $('input[name=property_data_changed]').val(1);
        var $button = $(this);
        var general_name = $button.attr('data-general-name');
        var $container = $button.closest('.radio_container');
        var $parentContainer = $button.closest('.parent_radio_container');
        var $newContainer = $container.clone();
        if ($button.find('i').hasClass('fa-plus')) {
            $newContainer.find('input[type="radio"]').prop('checked', false);
            $newContainer.find('input[type="text"]').val('');
            $newContainer.find('.fa-plus').removeClass('fa-plus').addClass('fa-minus');
            $newbutton = $newContainer.find('button');
            $newbutton.attr('id', Date.now() + $button.attr('id'));
            $container.parent('div').append($newContainer);
        } else if ($button.find('i').hasClass('fa-minus')) {
            $container.remove();
        }
        updateCheckboxValues('.radio_container', $parentContainer, general_name, 'radio');
    });

    // Обработка мультиполей
    $('div.multicheck input:checked').each(function () {
        var name = $(this).attr('name');
        if (name && name.indexOf('_multiple') !== -1) {
            var newName = name.replace('_multiple', '_' + nameSuffix) + '[]';
            if (newName.includes('image_') || newName.includes('file_')) {
                return; // Пропускаем итерацию, если newName содержит "image_" или "file_"
            }
            var $defaultElement = $('[name="' + newName + '"]');
            var $parentContent = $(this).closest('.property_content'); // Ищем родительский контейнер
            if ($defaultElement.length && !$parentContent.find('a[data-' + nameSuffix + '-name="' + newName + '"]').length) {
                $parentContent.append('<a href="#" class="add-element" data-' + nameSuffix + '-name="' + newName + '">' + AppCore.getLangVar('sys.add') + '</a>');
            }
            // Добавляем кнопку удаления для всех элементов после первого
            $parentContent.find('.field_container').each(function (index) {
                if (index > 0 && !$(this).find('.remove-element').length) {
                    var $deleteButton = $('<button type="button" class="btn btn-primary remove-element ms-2"><i class="fa fa-minus"></i></button>');
                    $(this).append($deleteButton); // Добавляем кнопку в конец .field_container
                }
            });
        }
    });
    // Обработка клика по ссылке "Добавить"
    $(document).on('click', '.add-element', function (e) {
        e.preventDefault();
        var defaultName = $(this).data(nameSuffix + '-name');
        var $defaultElement = $('input[name="' + defaultName + '"], textarea[name="' + defaultName + '"]').first();
        $('input[name=property_data_changed]').val(1);
        if (defaultName.includes('select')) {
            var $hiddenElement = $defaultElement;
            var $selectWrapper = $hiddenElement.next('.input-group').clone();
            var $lastSelect = $defaultElement.closest('.col').find('select').last();
            var selectIdParts = $lastSelect.attr('id').split('_');
            var baseId = selectIdParts[0] + '_' + selectIdParts[1];
            var newIndex = parseInt(selectIdParts[2]) + 1;
            $hiddenElement = $hiddenElement.clone();
            $hiddenElement.attr('id', baseId + '_' + newIndex + '_' + nameSuffix);
            $hiddenElement.val('');
            var $selectElement = $selectWrapper.find('select');
            $selectElement.attr('id', baseId + '_' + newIndex).empty();
            var $spanElement = $selectWrapper.find('span');
            $spanElement.attr('data-select-id', baseId + '_' + newIndex);
            $spanElement.attr('id', baseId + '_' + nameSuffix + '_add_select_values_' + newIndex);
            var $deleteButton = $('<button type="button" class="btn btn-primary remove-element ms-2"><i class="fa fa-minus"></i></button>');
            var $wrapper = $('<div class="cloned-element d-flex align-items-center mb-2"></div>');
            $wrapper.append($hiddenElement).append($selectWrapper).append($deleteButton);
            $defaultElement.closest('.col').append($wrapper);
        } else if ($defaultElement.is('textarea')) {
            var $clone = $defaultElement.clone();
            $clone.val('');
            var $deleteButton = $('<button type="button" class="btn btn-primary remove-element ms-2"><i class="fa fa-minus"></i></button>');
            var $wrapper = $('<div class="cloned-element d-flex align-items-center mb-2"></div>');
            $wrapper.append($clone).append($deleteButton);
            $defaultElement.closest('.col').append($wrapper);
        } else {
            if ($defaultElement.length) {
                var $clone = $defaultElement.clone();
                $clone.val('');
                var $deleteButton = $('<button type="button" class="btn btn-primary remove-element ms-2"><i class="fa fa-minus"></i></button>');
                var $wrapper = $('<div class="cloned-element d-flex align-items-center mb-2"></div>');
                $wrapper.append($clone).append($deleteButton);
                $defaultElement.closest('.col').append($wrapper);
            }
        }
    });
    // Обработка клика по кнопке удаления
    $(document).on('click', '.remove-element', function () {
        $(this).closest('.cloned-element').remove();     
        $(this).closest('.field_container').remove();        
    });
    // Флаг изменения настроек для сохранения
    $('input[name^=property_data]').change(function () {
        $('input[name=property_data_changed]').val(1);
    });
    // Проверка полей required только у категорий и страниц
    if (nameSuffix == 'value') {
        // Находим родительскую форму элемента #renderPropertiesSetsAccordion
        var parentForm = $('#renderPropertiesSetsAccordion').closest('form');
        parentForm.on('submit', function (e) {
            var hasError = false;
            $('#renderPropertiesSetsAccordion').find('input, textarea').each(function () {
                var $field = $(this);
                if ($field.is('[required]') && (!$field.val() || !$field.val().trim().length)) {
                    // Найти текст ближайшего label
                    var labelText = $field.closest('.form-group').find('label').text() // Если структура предполагает блок form-group
                            || $('label[for="' + $field.attr('id') + '"]').text() // Если используется связка for и id
                            || $field.attr('name'); // Фолбэк, если label отсутствует
                    actions.showNotification(
                            'Поле "' + labelText.trim() + '" обязательно для заполнения!',
                            'danger'
                            );
                    hasError = true;
                    return false;
                }
            });
            if (hasError) {
                e.preventDefault();
            }
        });
    }
    // Установка атрибута required
    $(document).on('click', 'input[name$="_required]"]', function () {
        const checkboxName = $(this).attr('name'); // Например, "property_data[text_0_required]"
        const baseName = checkboxName.replace('required]', nameSuffix); // Получаем "property_data[text_0_"
        const $propertyContent = $(this).closest('.property_content');
        const $targetInput = $propertyContent.find('input[name^="' + baseName + '"], textarea[name^="' + baseName + '"]');
        const isChecked = $(this).is(':checked');

        if ($targetInput.length) {
            if (isChecked) {
                $targetInput.attr('required', 'required');
            } else {
                $targetInput.removeAttr('required');
            }
        }
    });
});

function createModal(selectId) {
    var modalHtml = `
        <div class="modal fade" id="select_modal_` + selectId + `" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">` + AppCore.getLangVar('sys.add') + `</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="options-container">
                            <!-- Здесь будут поля для ввода опций -->
                        </div>
                        <button class="btn btn-success mt-2" id="addOptionField" type="button">` + AppCore.getLangVar('sys.add') + `</button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="saveOptions" data-select-id="${selectId}">` + AppCore.getLangVar('sys.insert') + `</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">` + AppCore.getLangVar('sys.cancel') + `</button>                        
                    </div>
                </div>
            </div>
        </div>`;
    $('body').append(modalHtml);
    // Добавление существующих options в форму
    $('#' + selectId + ' option').each(function () {
        addOptionField($(this).val(), $(this).text());
    });
    // Отображение модального окна
    $('#select_modal_' + selectId).modal('show');
    // Добавление нового поля по клику
    $('#addOptionField').click(function () {
        addOptionField();
    });
}

function addOptionField(key = '', value = '') {
    var rand = Date.now();
    var fieldHtml = `
        <div class="option-field mb-2 d-flex align-items-center">
            <label for="valueInput` + rand + `" class="me-2">` + AppCore.getLangVar('sys.name') + `:</label>
            <input type="text" id="valueInput` + rand + `" class="form-control value me-2" placeholder="` + AppCore.getLangVar('sys.name') + `" value="${value}">    
            <label for="keyInput` + rand + `" class="me-2">` + AppCore.getLangVar('sys.value') + `:</label>
            <input type="text" id="keyInput` + rand + `" class="form-control key me-2" placeholder="` + AppCore.getLangVar('sys.value') + `" value="${key}">
            <button class="btn btn-danger ms-2 removeOptionField" type="button">` + AppCore.getLangVar('sys.delete') + `</button>
        </div>`;
    $('#options-container').append(fieldHtml);
}

function updateCheckboxValues(container, $parentContainer, general_name, type) {
    var count = 0;
    $parentContainer.find(container).each(function (index) {
        $(this).find('input[type="' + type + '"]').val(index);
        count = count + 1;
    });
    $('#' + general_name + '_count').val(count);
}