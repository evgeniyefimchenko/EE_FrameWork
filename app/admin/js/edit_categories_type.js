/*Редактирование типа категорий*/
$(document).ready(function () {
    setActiveNavLink('/admin/types_categories');
    $('#description-input').on('focus blur', function(){
        $(this).val($.trim(this.value));
    });
    initializeTinyMCE('#description-input', settingsLongDescription);
});