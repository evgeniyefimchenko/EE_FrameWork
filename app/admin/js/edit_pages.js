/*Редактирование сущностей*/

$(document).ready(function () {
    setActiveNavLink('/admin/pages');
    $('.accordion-button').dblclick(function () {        
        var categoryID = $(this).data('category_id');
        console.log(categoryID);
        $('#category_id-input').val(categoryID);
        $('#category_id-input').change();
        $('#categories_modal').modal('hide');

    });    
    initializeTinyMCE('#short_description-input', settingsShortDescription);
    initializeTinyMCE('#description-input', settingsLongDescription);
});
