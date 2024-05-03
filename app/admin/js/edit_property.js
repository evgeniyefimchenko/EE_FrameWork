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
        var isConfirmed = confirm("Вы уверены, что хотите сменить тип свойства? Все поля будут очищены!");
        if (!isConfirmed) {
            $(this).val(previousValue);
        } else {
            $(this).data('previous', currentSelectedValue);
            $('#fields_contents').remove();
            $('button[type=submit]').click();
        }
    }).data('previous', $('#type_id-input').val());    
});
