/*Редактирование типа категорий*/
$(document).ready(function () {
    setActiveNavLink('/admin/type_categories');
    $('#description-input').on('focus blur', function(){
        $(this).val($.trim(this.value));
    });
});