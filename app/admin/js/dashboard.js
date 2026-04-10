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
        AppCore.sendAjaxRequest(
                '/admin/ajax_admin',
                {'get': 1},
                'POST',
                'json',
                function (data) {
                    if (typeof data.error !== 'undefined') {
                        console.log('error', data);
                        actions.showNotification(AppCore.getLangVar('sys.data_read_error') + ' ' + data.error, 'danger');
                    } else {
                        if (data.notifications && typeof data.notifications[0] !== 'undefined') {
                            var d = new Date().getTime();
                            for (let key in data.notifications) {
                                const notification = data.notifications[key];
                                const shouldAutoRemove = ['info', 'success', 'danger'].includes(notification.status)
                                    || (notification.status === 'warning' && notification.source_type === 'security');

                                if (shouldAutoRemove) {
                                    actions.showNotification(notification.text, notification.status);
                                    AppCore.sendAjaxRequest(
                                            '/admin/killNotificationById',
                                            {'id': notification.id},
                                            'POST'
                                            );
                                } else if (notification.status === 'primary') {
                                    if ((parseInt(notification.showtime) - parseInt(d)) <= 0) {
                                        actions.showNotification(notification.text, notification.status);
                                        AppCore.sendAjaxRequest(
                                                '/admin/setNotificationTime',
                                                {'showtime': d + 300000, 'id': notification.id},
                                                'POST'
                                                );
                                    }
                                } else {
                                    // Показывать все остальные сообщения постоянно до удаления в контроллере
                                    actions.showNotification(notification.text, notification.status);
                                    AppCore.sendAjaxRequest(
                                            '/admin/setNotificationTime',
                                            {'showtime': d, 'id': notification.id},
                                            'POST'
                                            );
                                }
                            }
                        }
                    }
                },
                function (jqXHR, textStatus, errorThrown) {
                    console.error("AJAX request failed:", textStatus, errorThrown);
                    console.error("Response details:", jqXHR.status, jqXHR.responseText);
                }
        );
    },
    saveOptionsUser: function (data) {
        data.update = 1;
        let notify = actions.showNotification(AppCore.getLangVar('sys.data_being_saved'), 'primary');
        AppCore.sendAjaxRequest(
                '/admin/ajax_admin',
                data,
                'POST',
                'json',
                function (data) {
                    if (typeof data.error !== 'undefined') {
                        console.log('error', data);
                        actions.showNotification(AppCore.getLangVar('sys.data_update_error'), 'danger');
                    } else {
                        actions.showNotification(AppCore.getLangVar('sys.personal_data_updated'), 'primary');
                    }
                },
                function (jqXHR, textStatus, errorThrown) {
                    console.error("AJAX request failed:", textStatus, errorThrown);
                    console.error("Response details:", jqXHR.status, jqXHR.responseText);
                },
                {}
        ).finally(function () {
            // Закрытие уведомления после завершения запроса
            notify.close();
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
        AppCore.sendAjaxRequest(
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

    $(document).on('click', '[data-dashboard-online-users-toggle]', function (event) {
        event.preventDefault();

        const $button = $(this);
        const targetSelector = ($button.attr('data-target') || '').toString().trim();
        const requestUrl = ($button.attr('data-url') || '').toString().trim();
        const $target = targetSelector ? $(targetSelector) : $();

        if (!$target.length || !requestUrl) {
            return;
        }

        if ($target.attr('data-loaded') === '1') {
            $target.toggleClass('d-none');
            return;
        }

        const loadingText = ($target.attr('data-loading-text') || AppCore.getLangVar('sys.loading') || 'Loading...').toString();
        const errorText = ($target.attr('data-load-error-text') || AppCore.getLangVar('sys.dashboard_auth_online_load_error') || 'Load error').toString();

        $button.prop('disabled', true);
        $target
            .removeClass('d-none')
            .html('<div class="small text-muted">' + loadingText + '</div>');

        AppCore.sendAjaxRequest(
            requestUrl,
            {},
            'GET',
            'json',
            function (data) {
                if (data && !data.error && data.html) {
                    $target.html(data.html);
                    $target.attr('data-loaded', '1');
                    $button.prop('disabled', false);
                    return;
                }

                $target.html('<div class="alert alert-danger mb-0">' + errorText + '</div>');
                $button.prop('disabled', false);
            },
            function () {
                $target.html('<div class="alert alert-danger mb-0">' + errorText + '</div>');
                $button.prop('disabled', false);
            }
        );
    });

    $(".preloader").fadeOut();
});
