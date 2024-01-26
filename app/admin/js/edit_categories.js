/*Редактирование типа категорий*/
$(document).ready(function () {
    setActiveNavLink('/admin/categories');
    $('#description-input').on('focus blur', function(){
        $(this).val($.trim(this.value));
    });
    $('#parent_id-input').change(function() {
        var parent_id = $(this).val();
        if (parent_id) {
            sendAjaxRequest('/admin/get_type_category', 
            {
                parent_id: parent_id
            },
            'POST',
            'json',
            function(response) {
                $('#type_id-input').html(response.html);
            });
        } else {
            $('#type_id-input').html('<option value="" selected="">---</option>');
        }
    });
    $('.accordion-button').dblclick(function() {
        var categoryID = $(this).data('category_id');
        $('#parent_id-input').val(categoryID);
        $('#parent_id-input').change();
        $('#parents_modal').modal('hide');

    });
});