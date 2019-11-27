/*Редактирование пользователя*/
$(document).ready(function () {

    $('#users-item-menu').addClass('nav-item active');

    $("#edit_users").submit(function () {
        var form = $(this);
        var data = form.serialize();
        var notify;
        if (!validatePhone) return false;
        var add= '';
        if (parseInt($("#id_user").data('id'))>0){
            add = '/id/' + $("#id_user").data('id');
        }
        $.ajax({
            type: 'POST',
            url: '/admin/ajax_user_edit' + add,
            dataType: 'json',
            data: data,
            beforeSend: function () {
                notify = actions.showNotification('Подождите данные сохраняются.', 'primary');
            },
            success: function (data) {
                if (data.error !== 'no') {
                    console.log('error', data);
                    actions.showNotification('Ошибка обновления данных.', 'danger');
                } else {
                    actions.showNotification('Данные пользователя обновлены.', 'primary');
                    if ($("#id_user").data('id') !== '') {
                        window.location = "/admin/user_edit/" + $("#id_user").data('id');
                    } else {
                        window.location = "/admin/user_edit/id/" + data.id;
                    }
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status, xhr.responseText, thrownError, ajaxOptions);
            },
            complete: function (data) {
                notify.close();
            }

        });
        return false;
    });

    /*Валидация формы*/
    function validatePhone() {
        var filter = /^[\d+][\d\(\)\ -]{4,14}\d$/;
        if (filter.test($('#phone').val())) {
            return true;
        } else {
            return false;
        }
    }

    $('#phone').blur(function () {
        if (validatePhone($('#phone').val())) {
            $(this).parents('.form-group').removeClass('has-error');
            $(this).parents('.form-group').addClass('has-success');
            $(this).next('small').text('');
            $('#submit').prop('disabled', false);
        } else if ($('#phone').val().trim() !== '') {
            $(this).parents('.form-group').removeClass('has-success');
            $(this).parents('.form-group').addClass('has-error');
            $(this).next('small').text('Недопустимый формат номера');
            $('#submit').prop('disabled', true);
        }
    });

    $('#new_pass_conf, #new_pass').blur(function () {
        var pass = $("#new_pass").val();
        var pass_rep = $("#new_pass_conf").val();
        if ((pass !== pass_rep || pass.length < 5) && (pass.trim() !== '' && pass_rep.trim() !== '')) {
            $("#new_pass").parents('.form-group').removeClass('has-success');
            $('#new_pass_conf').parents('.form-group').removeClass('has-success');
            $("#new_pass").parents('.form-group').addClass('has-error');
            $('#new_pass_conf').parents('.form-group').addClass('has-error');
            $("#new_pass").next('small').text('Пароли не совпадают или длинна менее 5 символов');
            $('#new_pass_conf').next('small').text('Пароли не совпадают или длинна менее 5 символов');
            $('#submit').prop('disabled', true);
        } else {
            $("#new_pass").parents('.form-group').removeClass('has-error');
            $('#new_pass_conf').parents('.form-group').removeClass('has-error');
            $("#new_pass").parents('.form-group').addClass('has-success');
            $('#new_pass_conf').parents('.form-group').addClass('has-success');
            $("#new_pass").next('small').text('');
            $('#new_pass_conf').next('small').text('');
            $('#submit').prop('disabled', false);
        }
    });

    /*Инициализация плагинов для страницы*/
    $('[data-validator]').validator();
});