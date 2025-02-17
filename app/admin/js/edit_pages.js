/*Редактирование Страниц*/
$(document).ready(function () {
    setActiveNavLink('/admin/pages');
    $('.accordion-button').dblclick(function () {        
        var categoryID = $(this).data('category_id');
        $('#category_id-input').val(categoryID);
        $('#category_id-input').change();
        $('#categories_modal').modal('hide');

    });
    $('input[name^=property_data]').change(function() {
        $('input[name=property_data_changed]').val(1);
    });
    $('#description-input').summernote(AppCore.summernoteParams);
    AppCore.setupTabEventListeners();
    AppCore.handleTabsFromUrl();    
});
