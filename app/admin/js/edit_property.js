/*Редактирование типа категорий*/
$(document).ready(function () {
    setActiveNavLink('/admin/properties');
    const t = (key, fallback) => AppCore.getLangVar(key) || fallback;
    $('#description-input').on('focus blur', function () {
        $(this).val($.trim(this.value));
    });
    // Смена типа свойства
    $('#type_id-input').change(function () {
        var currentSelectedValue = $(this).val();
        var previousValue = $(this).data('previous');
        if ($('#name-input').val().trim() === '') {
            alert(t('sys.enter_name', 'Enter a name!'));
            $(this).val(previousValue);
            return false;
        }
        var isConfirmed = confirm(t('sys.property_type_change_confirm', 'All fields will be cleared, continue?'));
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
