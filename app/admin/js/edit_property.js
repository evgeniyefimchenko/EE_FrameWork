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
            AppCore.sendAjaxRequest('/admin/getPropertyData',
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
    AppCore.setupTabEventListeners();
    AppCore.handleTabsFromUrl();    
});
