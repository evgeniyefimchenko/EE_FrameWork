/*Редактирование типа категорий*/
$(document).ready(function () {
    setActiveNavLink('/admin/categories');
    $('#description-input').on('focus blur', function(){
        $(this).val($.trim(this.value));
    });
});