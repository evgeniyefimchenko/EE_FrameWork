/*Страница пользователей*/
$(document).ready(function () {
    $('#users-item-menu').addClass('nav-item active');
    $('#add_user').click(function () {
        window.location = "/admin/user_edit/id";
    });
    $('.delete_user').click(function (e) {
        if (confirm("Удалить пользователя?")) {
            $.ajax({
                type: 'POST',
                url: '/admin/delete_user/id/' + $(this).data('user_id'),
                dataType: 'json',
                beforeSend: function () {
                    notify = actions.showNotification('Wait please', 'primary');
                },
                success: function (data) {
                    if (data.error !== 'no') {
                        console.log('error', data);
                        actions.showNotification('Error', 'danger');
                    } else {
                        actions.showNotification('Success', 'primary');
                        window.location = "/admin/users";
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    console.log(xhr.status, xhr.responseText, thrownError, ajaxOptions);
                },
                complete: function (data) {
                    notify.close();
                }
            });
        } else {
            return false;
        }
    });
});