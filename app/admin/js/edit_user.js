/*Редактирование профиля пользователя*/

$(document).ready(function () {
    setActiveNavLink('/admin/users');
    $("#edit_users").submit(function (event) {
        event.preventDefault();
        var form = $(this);
        var data = form.serialize();
        var notify;
        var add = '';
        if (parseInt($("#id_user").data('id')) > 0) {
            add = '/id/' + $("#id_user").data('id');
        }
        notify = actions.showNotification('Please wait, data is being saved.', 'primary');
        AppCore.sendAjaxRequest(
            '/admin/ajax_user_edit' + add,
            data,
            'POST',
            'json',
            function (data) {
                notify.close();
                if (data.error !== 'no') {
                    console.log('error', data);
                    actions.showNotification('ERROR', 'danger');
                } else {
                    actions.showNotification('UPDATE SUCCESS', 'primary');
                    if (data.new == 1) {
                        window.location = "/admin/users";
                    } else {
                        window.location = "/admin/user_edit/id/" + data.id;
                    }
                }
            },
            function (jqXHR, textStatus, errorThrown) {
                notify.close();
                actions.showNotification('ERROR', 'danger');
                console.error("AJAX request failed:", textStatus, errorThrown);
                console.error("Response details:", jqXHR.status, jqXHR.responseText);
            }
        );
    });

    $('#new_pass_conf, #new_pass').on('input', function () {
        var pass = $("#new_pass").val();
        var pass_rep = $("#new_pass_conf").val();
        var hasError = false;
        // Проверка на соответствие паролей
        if (pass !== pass_rep) {
            hasError = true;
            $("#new_pass, #new_pass_conf").next('small').text('Passwords do not match');
        }
        // Проверка на длину пароля
        if (pass.length < 5 || pass_rep.length < 5) {
            hasError = true;
            $("#new_pass, #new_pass_conf").next('small').text('Passwords are less than 5 characters long');
        }
        if (hasError) {
            $("#new_pass, #new_pass_conf").removeClass('is-valid').addClass('is-invalid');
            $('#submit').prop('disabled', true);
        } else {
            $("#new_pass, #new_pass_conf").removeClass('is-invalid').addClass('is-valid');
            $("#new_pass, #new_pass_conf").next('small').text('');
            $('#submit').prop('disabled', false);
        }
    });
    $('#phone-input').mask('+7 (000) 000-00-00');  // Маска для российского формата телефона
});