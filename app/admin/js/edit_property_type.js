/* Редактирование типа свойства */
$(document).ready(function () {
    setActiveNavLink('/admin/types_properties');
    $(document).on('click', '.add-field-btn', function(e){
        e.preventDefault();
        var newField = $('#fields-container').clone();
        newField.find('[id]').removeAttr('id');
        newField.find('.form-select').after('<button class="btn btn-danger remove-field-btn" type="button"><i class="fa fa-minus-circle"></i></button>');
        newField.find('.add-field-btn').remove();
        $('#fields-tab-pane').append(newField);
    });
    $(document).on('click', '.remove-field-btn', function(e){
        e.preventDefault();
        $(this).closest('.row').remove();
    });    
});

