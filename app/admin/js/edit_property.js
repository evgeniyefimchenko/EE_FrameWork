/*Редактирование типа категорий*/
$(document).ready(function () {
    setActiveNavLink('/admin/properties');
    $('#description-input').on('focus blur', function () {
        $(this).val($.trim(this.value));
    });
    // Открытие модального окна и добавление существующих options в форму
    $(document).on('click', '.openModal', function () {
        var selectId = $(this).data('select-id');
        createModal(selectId);
    });
    // Сохранение новых options и удаление модального окна
    $(document).on('click', '#saveOptions', function () {
        var selectId = $(this).data('select-id');
        var defaultId = selectId + '_default';
        var new_values = '';
        $('#' + selectId).empty(); // Очистка select
        $('#options-container .option-field').each(function () {
            var key = $(this).find('.key').val();
            var value = $(this).find('.value').val();
            if (key && value) {
                $('#' + selectId).append(new Option(value, key)); // Добавление option в select
                new_values = new_values + '&' + key + '=' + value;
            }
        });
        $('#' + defaultId).val(new_values);
        $('#select_modal').modal('hide');
        $('#select_modal').remove();
    });
    // Удаление поля ввода
    $(document).on('click', '.removeOptionField', function () {
        $(this).closest('.option-field').remove();
    });
    
    // Удаление модального окна после его закрытия
    $(document).on('hidden.bs.modal', '#select_modal', function () {
        $(this).remove();
    });
    
    // Обработчик клика по кнопке добавления/удаления checkbox
    $(document).on('click', '[id$="_default_add_checkbox_values"]', function () {
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
            $container.parent('div').append($newContainer);
        } else if ($button.find('i').hasClass('fa-minus')) {
            $container.remove();
        }
        updateCheckboxValues('.checkbox_container', $parentContainer, general_name, 'checkbox');
    });    
    
    // Обработчик клика по кнопке добавления/удаления radiobox
    $(document).on('click', '[id$="_default_add_radio_values"]', function () {
        var $button = $(this);
        var general_name = $button.attr('data-general-name');
        var $container = $button.closest('.radio_container');
        var $parentContainer = $button.closest('.parent_radio_container');
        var $newContainer = $container.clone();
        if ($button.find('i').hasClass('fa-plus')) {            
            $newContainer.find('input[type="radio"]').prop('checked', false);
            $newContainer.find('input[type="text"]').val('');
            $newContainer.find('.fa-plus').removeClass('fa-plus').addClass('fa-minus');
            $container.parent('div').append($newContainer);
        } else if ($button.find('i').hasClass('fa-minus')) {      
            $container.remove();
        }
        updateCheckboxValues('.radio_container', $parentContainer, general_name, 'radio');
    });    

    function updateCheckboxValues(container, $parentContainer, general_name, type) {
        var count = 0;
        $parentContainer.find(container).each(function(index) {
            $(this).find('input[type="' + type + '"]').val(index);
            count = count + 1;
        });
        $('#' + general_name + '_count').val(count);
    }
    // Смена типа свойства
    $('#type_id-input').change(function() {
        var currentSelectedValue = $(this).val(); // текущее выбранное значение
        var isConfirmed = confirm("Вы уверены, что хотите сменить тип свойства? Все поля будут очищены!");
        if (!isConfirmed) {
            $(this).val($(this).data('previous')); 
        } else {
            $(this).data('previous', currentSelectedValue);
            $('#fields_contents').remove();
            $('button[type=submit]').click();
        }
    }).data('previous', $('#type_id-input').val());

    
    // http://plugins.krajee.com/file-input
    // $('input[type="file"]').fileinput();

});

function createModal(selectId) {
    var modalHtml = `
        <div class="modal fade" id="select_modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">` + lang_var('sys.add') + `</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="options-container">
                            <!-- Здесь будут поля для ввода опций -->
                        </div>
                        <button class="btn btn-success mt-2" id="addOptionField" type="button">` + lang_var('sys.add') + `</button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="saveOptions" data-select-id="${selectId}">Вставить</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>                        
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
    $('#select_modal').modal('show');
    // Добавление нового поля по клику
    $('#addOptionField').click(function () {
        addOptionField();
    });
}

function addOptionField(key = '', value = '') {
    var fieldHtml = `
        <div class="option-field mb-2 d-flex align-items-center">
            <label for="keyInput" class="me-2">Значение:</label>
            <input type="text" id="keyInput" class="form-control key me-2" placeholder="Значение" value="${key}">
            <label for="valueInput" class="me-2">Имя:</label>
            <input type="text" id="valueInput" class="form-control value me-2" placeholder="Имя" value="${value}">
            <button class="btn btn-danger ms-2 removeOptionField" type="button">Удалить</button>
        </div>`;
    $('#options-container').append(fieldHtml);
}
