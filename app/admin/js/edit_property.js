/*Редактирование типа категорий*/
$(document).ready(function () {
    setActiveNavLink('/admin/properties');
    $('#description-input').on('focus blur', function () {
        $(this).val($.trim(this.value));
    });
    // Смена типа свойства
    $('#type_id-input').change(function () {
        var currentSelectedValue = $(this).val();
        var previousValue = $(this).data('previous');
        if ($('#name-input').val().trim() === '') {
            alert('Введите название!');
            $(this).val(previousValue);
            return false;
        }
        var isConfirmed = confirm("Все поля будут очищены, продолжить?");
        if (!isConfirmed) {
            $(this).val(previousValue);
        } else {
            $(this).data('previous', currentSelectedValue);
            sendAjaxRequest('/admin/getPropertyData',
                    {
                        type_id: currentSelectedValue
                    },
                    'POST',
                    'json',
                    function (response) {
                        $('#fields_contents').html(response.html);
                    });
        }
    }).data('previous', $('#type_id-input').val());

    // Обработка мультиполей
$('div.multicheck input:checked').each(function() {
    var name = $(this).attr('name');
    if (name && name.indexOf('_multiple') !== -1) {
        var newName = name.replace('_multiple', '_default') + '[]';
        var $defaultElement = $('[name="' + newName + '"]');
        var $parentContent = $(this).closest('.property_content'); // Ищем родительский контейнер

        if ($defaultElement.length && !$parentContent.find('a[data-default-name="' + newName + '"]').length) {
            $parentContent.append('<a href="#" class="add-element" data-default-name="' + newName + '">Добавить</a>');
        }

        // Добавляем кнопку удаления для всех элементов после первого
        $parentContent.find('.field_container').each(function(index) {
            if (index > 0 && !$(this).find('.remove-element').length) {
                var $deleteButton = $('<button type="button" class="btn btn-primary remove-element ms-2"><i class="fa fa-minus"></i></button>');
                $(this).append($deleteButton); // Добавляем кнопку в конец .field_container
            }
        });
    }
});

// Обработка клика по ссылке "Добавить"
$(document).on('click', '.add-element', function(e) {
    e.preventDefault();
    var defaultName = $(this).data('default-name');
    var $defaultElement = $('input[name="' + defaultName + '"], textarea[name="' + defaultName + '"]').first();

    if (defaultName.includes('select')) {
        var $hiddenElement = $defaultElement;
        var $selectWrapper = $hiddenElement.next('.input-group').clone();
        var $lastSelect = $defaultElement.closest('.col').find('select').last();
        var selectIdParts = $lastSelect.attr('id').split('_');
        var baseId = selectIdParts[0] + '_' + selectIdParts[1]; // Например, "select_10"
        var newIndex = parseInt(selectIdParts[2]) + 1;
        $hiddenElement = $hiddenElement.clone();
        $hiddenElement.attr('id', baseId + '_' + newIndex + '_default');
        $hiddenElement.val('');
        var $selectElement = $selectWrapper.find('select');
        $selectElement.attr('id', baseId + '_' + newIndex).empty();
        var $spanElement = $selectWrapper.find('span');
        $spanElement.attr('data-select-id', baseId + '_' + newIndex);
        $spanElement.attr('id', baseId + '_default_add_select_values_' + newIndex);
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
$(document).on('click', '.remove-element', function() {
    $(this).closest('.field_container').remove();
});


});
