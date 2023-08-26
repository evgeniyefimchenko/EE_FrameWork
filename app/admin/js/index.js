/**
 * Подключается на всех страницах админ-панели
 */

var text_message, color;

actions = {
    showNotification: function (text_message, color, from, align) {
        // color = 'info'; // 'primary', 'info', 'success', 'warning', 'danger'
        return $.notify({
            icon: "fa fa-bell",
            message: text_message
        }, {
            z_index: 10000,
            type: color,
            timer: 5000,
            placement: {
                from: from,
                align: align
            }
        });
    },
    loadOptionsUser: function () {
        $.ajax({
            type: 'POST',
            url: '/admin/ajax_admin',
            dataType: 'json',
            async: false,
            data: {'get': 1},
            success: function (data) {
                if (typeof data.error !== 'undefined') {
                    console.log('error', data);
                    actions.showNotification(lang_var('sys.data_read_error'), 'danger');
                } else {
                    tmp_skin = data.skin;
                    if (typeof data.notifications[0] !== 'undefined') {                        
                        var d = new Date().getTime();
                        for (key in data.notifications) {
                            if (data.notifications[key].status === 'info' || data.notifications[key].status === 'success') {
                                actions.showNotification(data.notifications[key].text, data.notifications[key].status);
                                // Информационные сообщения прибиваем сразу
                                $.post('/admin/kill_notification_by_id', {'id': data.notifications[key].id});
                            } else if (data.notifications[key].status === 'primary') {
                                if ((parseInt(data.notifications[key].showtime) - parseInt(d)) <= 0) {
                                    actions.showNotification(data.notifications[key].text, data.notifications[key].status);
                                    // Отложить показ уведомлений на 5-ть минут 
                                    $.post('/admin/set_notification_time', {'showtime': d + 300000, 'id': data.notifications[key].id});
                                }
                            } else { // Показывать все остальные сообщения постоянно до удаления в контроллере                           
                                actions.showNotification(data.notifications[key].text, data.notifications[key].status);
                                $.post('/admin/set_notification_time', {'showtime': d, 'id': data.notifications[key].id});
                            }
                        };
                    }
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status, xhr.responseText, thrownError);
            }
        });
    },
    saveOptionsUser: function (data) {
        data.update = 1;
        $.ajax({
            type: 'POST',
            url: '/admin/ajax_admin',
            dataType: 'json',
            data: data,
            beforeSend: function (data) {
                notify = actions.showNotification(lang_var('sys.data_being_saved'), 'primary');
            },
            success: function (data) {
                if (typeof data.error !== 'undefined') {
                    console.log('error', data);
                    actions.showNotification(lang_var('sys.data_update_error'), 'danger');
                } else {
                    actions.showNotification(lang_var('sys.personal_data_updated'), 'primary');
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status, xhr.responseText, thrownError);
            },
            complete: function (data) {
                notify.close();
            }
        });
    }
}

// Загрузка и активация пользовательских настроек
actions.loadOptionsUser();

$(document).ready(function () {
    $(".preloader").fadeOut();
    // Пометить все сообщения прочитанными
    $('#set_readed_all, #read_all_message').click(function () {
        let return_url = $(this).data('return');
        $.ajax({
            url: '/admin/set_readed_all',
            success: function () {
                window.location = return_url;
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status, xhr.responseText, thrownError, ajaxOptions);
            }
        });
    });	
});

window.addEventListener('DOMContentLoaded', event => {
    const sidebarToggle = document.body.querySelector('#sidebarToggle');
    if (sidebarToggle) {
        // Раскомментируйте чтобы сохранить боковую панель для переключения между обновлениями
        // if (localStorage.getItem('sb|sidebar-toggle') === 'true') {
        //     document.body.classList.toggle('sb-sidenav-toggled');
        // }
        sidebarToggle.addEventListener('click', event => {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
            localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
        });
    }
});