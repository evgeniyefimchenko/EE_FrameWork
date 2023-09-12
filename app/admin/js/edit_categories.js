/*Редактирование типа категорий*/
$(document).ready(function () {
    $('#description-input').on('focus blur', function(){
        $(this).val($.trim(this.value));
    });
});