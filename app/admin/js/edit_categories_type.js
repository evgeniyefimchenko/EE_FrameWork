/*Редактирование типа категорий*/
$(document).ready(function () {
    setActiveNavLink('/admin/types_categories');
    var checkBoxs = $('#features-tab-pane').find('input[type="checkbox"]');
    $('#description-input').on('focus blur', function () {
        $(this).val($.trim(this.value));
    });
    $('#type_edit').on('submit', function() {
        checkBoxs.each(function () {
            $(this).prop('disabled', false);
        });
    });
    $('#type_id-input').on('change', function (event) {
        var type_id = $(this).val();
        if (type_id) {
            AppCore.sendAjaxRequest('/admin/getParentCategoriesType',
                    {
                        type_id: type_id
                    },
                    'POST',
                    'json',
                    function (response) {
                        if (typeof event.originalEvent !== 'undefined')
                            checkBoxs.prop('checked', false).prop('disabled', false);
                        if (response.all_sets_ids) {
                            checkBoxs.each(function () {
                                if (response.all_sets_ids.includes($(this).val())) {
                                    $(this).prop('checked', true).prop('disabled', true);
                                }
                            });
                        }
                    }
            );
        }
    });
    $('#type_id-input').change();
});