/* Страница админ-панели подключается в layouts Подключается на всех страницах админ-панели */
var text_message, color;

actions = {
    showNotification: function (text_message, color, from, align) {
        // color = 'info'; // 'primary', 'info', 'success', 'warning', 'danger'
        return $.notify({
            icon: "fa fa-bell",
            message: text_message
        }, {
            z_index: 100000,
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
            data: {'get': 1},
            success: function (data) {
                if (typeof data.error !== 'undefined') {
                    console.log('error', data);
                    actions.showNotification(lang_var('sys.data_read_error') + ' ' + data.error, 'danger');
                } else {
                    if (data.notifications && typeof data.notifications[0] !== 'undefined') {
                        var d = new Date().getTime();
                        for (key in data.notifications) {
                            if (data.notifications[key].status === 'info' || data.notifications[key].status === 'success' || data.notifications[key].status === 'danger') {
                                actions.showNotification(data.notifications[key].text, data.notifications[key].status);
                                // Информационные сообщения удаляем сразу
                                $.post('/admin/killNotificationById', {'id': data.notifications[key].id});
                            } else if (data.notifications[key].status === 'primary') {
                                if ((parseInt(data.notifications[key].showtime) - parseInt(d)) <= 0) {
                                    actions.showNotification(data.notifications[key].text, data.notifications[key].status);
                                    // Отложить показ уведомлений на 5-ть минут 
                                    $.post('/admin/setNotificationTime', {'showtime': d + 300000, 'id': data.notifications[key].id});
                                }
                            } else { // Показывать все остальные сообщения постоянно до удаления в контроллере                           
                                actions.showNotification(data.notifications[key].text, data.notifications[key].status);
                                $.post('/admin/setNotificationTime', {'showtime': d, 'id': data.notifications[key].id});
                            }
                        }
                        ;
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
};

// Загрузка и активация пользовательских настроек
actions.loadOptionsUser();

function setActiveNavLink(path) {
    $('a.nav-link').removeClass('active');
    let $elem = $('a.nav-link[href="' + path + '"]');
    $elem.addClass('active');
    $($elem.attr('data-parent-bs-target')).addClass('show');
    $('a[data-bs-target="' + $elem.attr('data-parent-bs-target') + '"]').attr('aria-expanded', true).removeClass('collapsed');
}

$(document).ready(function () {
    // Отметить выбранный элемент меню
    setActiveNavLink(window.location.pathname);
    // Пометить все сообщения прочитанными
    $('#set_readed_all, #read_all_message').click(function () {
        let return_url = $(this).data('return');
        sendAjaxRequest(
                '/admin/set_readed_all', // URL
                {}, // Data
                'GET', // Method
                'json', // DataType
                function () {
                    window.location = return_url; // SuccessCallback
                },
                function (xhr, textStatus, errorThrown) { // ErrorCallback
                    console.log(xhr.status, xhr.responseText, errorThrown, textStatus);
                }
        );
    });
    // Нужно закомментировать что бы удалить эффект
    let $sidebarToggle = $('#sidebarToggle');
    // Сохранение состояния сайдбара в localStorage
    if (localStorage.getItem('sb|sidebar-toggle') === 'true') {
        $('body').addClass('sb-sidenav-toggled');
    }
    $sidebarToggle.click(function (e) {
        e.preventDefault();
        $('body').toggleClass('sb-sidenav-toggled');
        localStorage.setItem('sb|sidebar-toggle', $('body').hasClass('sb-sidenav-toggled'));
    });
    $(".preloader").fadeOut();
});

