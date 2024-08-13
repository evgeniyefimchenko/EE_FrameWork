/*Редактирование типа категорий*/
$(document).ready(function () {
    setActiveNavLink('/admin/categories');
    $('#description-input').on('focus blur', function () {
        $(this).val($.trim(this.value));
    });
    $('#parent_id-input').change(function () {
        var parent_id = $(this).val();
        if (parent_id) {
            sendAjaxRequest('/admin/getTypeCategory',
                    {
                        parent_id: parent_id
                    },
                    'POST',
                    'json',
                    function (response) {
                        $('#type_id-input').html(response.html);
                        var type_id = response.parent_type_id;
                        if (type_id) {
                            sendAjaxRequest('/admin/getCategoriesType',
                                    {
                                        type_id: type_id,
                                        category_id: $('#category_id').attr('data-id'),
                                        title: $('#title-input').val()
                                    },
                                    'POST',
                                    'json',
                                    function (response) {
                                        $('#renderCategorySetsAccordion').html(response.html);
                                    }
                            );
                        }                        
                    });
        } else {
            $('#type_id-input').html('<option value="" selected="">---</option>');
        }
    });
    $('.accordion-button').dblclick(function () {
        var categoryID = $(this).data('category_id');
        $('#parent_id-input').val(categoryID);
        $('#parent_id-input').change();
        $('#parents_modal').modal('hide');

    });
    $('input[name^=property_data]').change(function() {
        $('input[name=property_data_changed]').val(1);
    });
    var currentPage = window.location.pathname;
    var parts = currentPage.split('/').filter(part => part !== '');

    if (parts[1] === 'category_edit') {
        initializeTinyMCE('#short_description-input', settingsShortDescription);
        initializeTinyMCE('#description-input', settingsLongDescription);
    }
});