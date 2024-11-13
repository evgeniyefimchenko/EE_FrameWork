$(document).ready(function () {

    // Дополнительный код и обработчики событий
    var currentPage = window.location.pathname;
    var parts = currentPage.split('/').filter(part => part !== '');

    // Открытие модального окна и добавление существующих options в форму
    $(document).on('click', '.openModal', function () {
        var selectId = $(this).data('select-id');
        createModal(selectId);
    });

    // Сохранение новых options и удаление модального окна
    $(document).on('click', '#saveOptions', function () {
        var selectId = $(this).data('select-id');
        var defaultId = (parts[1] === 'edit_property') ? selectId + '_default' : selectId + '_value';
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
            $newbutton.attr('id', getRandomInteger() + $button.attr('id'));
            $container.parent('div').append($newContainer);
        } else if ($button.find('i').hasClass('fa-minus')) {
            $container.remove();
        }
        updateCheckboxValues('.checkbox_container', $parentContainer, general_name, 'checkbox');
    });

    // Обработчик клика по кнопке добавления/удаления radiobox
    $(document).on('click', '[id$="_add_radio_values"]', function () {
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
            $newbutton.attr('id', getRandomInteger() + $button.attr('id'));
            $container.parent('div').append($newContainer);
        } else if ($button.find('i').hasClass('fa-minus')) {
            $container.remove();
        }
        updateCheckboxValues('.radio_container', $parentContainer, general_name, 'radio');
    });

});

function createModal(selectId) {
    var modalHtml = `
        <div class="modal fade" id="select_modal_` + selectId + `" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">` + lang_var('sys.add') + `</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="options-container">
                            <!-- Здесь будут поля для ввода опций -->
                        </div>
                        <button class="btn btn-success mt-2" id="addOptionField" type="button">` + lang_var('sys.add') + `</button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="saveOptions" data-select-id="${selectId}">` + lang_var('sys.insert') + `</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">` + lang_var('sys.cancel') + `</button>                        
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
    var rand = getRandomInteger();
    var fieldHtml = `
        <div class="option-field mb-2 d-flex align-items-center">
            <label for="valueInput` + rand + `" class="me-2">` + lang_var('sys.name') + `:</label>
            <input type="text" id="valueInput` + rand + `" class="form-control value me-2" placeholder="` + lang_var('sys.name') + `" value="${value}">    
            <label for="keyInput` + rand + `" class="me-2">` + lang_var('sys.value') + `:</label>
            <input type="text" id="keyInput` + rand + `" class="form-control key me-2" placeholder="` + lang_var('sys.value') + `" value="${key}">
            <button class="btn btn-danger ms-2 removeOptionField" type="button">` + lang_var('sys.delete') + `</button>
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