/**
* Подключается на всех страницах админ-панели
*/
var mobile_menu_visible = 0,
	mobile_menu_initialized = false,
	toggle_initialized = false,
	$sidebar,
	text_message,
	color;

actions = {
    showNotification: function (text_message, color, from, align) {
        // color = 'info'; // 'primary', 'info', 'success', 'warning', 'danger'
        return $.notify({
            icon: "nc-icon nc-app",
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
            data: {'get': 1},
            success: function (data) {
                if (typeof data.error !== 'undefined') {
                    console.log('error', data);
                    actions.showNotification('Ошибка чтения данных.', 'danger');
                } else {
                    $('.badge-' + data.color_filter).addClass('active');
                    $('.check-image-background').each(function (index, value) {
                        src = $(this).find("img").attr('src');
                        name_img = src.substring(src.lastIndexOf('/') + 1, src.length);
                        if (name_img == data.sidebar_img) {
                            $(this).addClass('active');
                        }
                    });                    
                    if (typeof data.notifications[0] !== 'undefined') {
                        var d = new Date().getTime();
                        data.notifications.forEach(function (notification) {                            
                            if (notification.status === 'info' || notification.status === 'success') {
                                actions.showNotification(notification.text, notification.status);
                                // Информационные сообщения прибиваем сразу
                                $.post('/admin/kill_notification_by_id', {'id' : notification.id});
                            } else if(notification.status === 'primary') {							
                                if ((parseInt(notification.showtime) - parseInt(d)) <= 0) {
                                    actions.showNotification(notification.text, notification.status);
                                    // Отложить показ уведомлений на 5-ть минут 
                                    $.post('/admin/set_notification_time', {'showtime' : d + 300000, 'id' : notification.id});
                                }
                            } else // Показывать все остальные сообщения постоянно до удаления в контроллере
                            {
                                actions.showNotification(notification.text, notification.status);
                                $.post('/admin/set_notification_time', {'showtime' : d, 'id' : notification.id});
                            }
                        });
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
                notify = actions.showNotification('Подождите данные сохраняются.', 'primary');
            },
            success: function (data) {
                if (typeof data.error !== 'undefined') {
                    console.log('error', data);
                    actions.showNotification('Ошибка обновления данных.', 'danger');
                } else {
                    actions.showNotification('Личные настройки обновлены.', 'primary');
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status, xhr.responseText, thrownError);
            },
            complete: function (data) {
                notify.close();
            }
        });
    },
    checkSidebarImage: function () {
        if (mobile_menu_initialized == true) {
            // reset all the additions that we made for the sidebar wrapper only if the screen is bigger than 991px
            $sidebar_wrapper.find('.navbar-form').remove();
            $sidebar_wrapper.find('.nav-mobile-menu').remove();
            mobile_menu_initialized = false;
        }
    },
    initRightMenu: function () {
        $sidebar_wrapper = $('.sidebar-wrapper');

        if (!mobile_menu_initialized) {

            $navbar = $('nav').find('.navbar-collapse').first().clone(true);

            nav_content = '';
            mobile_menu_content = '';

            //add the content from the regular header to the mobile menu
            $navbar.children('ul').each(function () {

                content_buff = $(this).html();
                nav_content = nav_content + content_buff;
            });

            nav_content = '<ul class="nav nav-mobile-menu">' + nav_content + '</ul>';

            $navbar_form = $('nav').find('.navbar-form').clone(true);

            $sidebar_nav = $sidebar_wrapper.find(' > .nav');

            // insert the navbar form before the sidebar list
            $nav_content = $(nav_content);
            $nav_content.insertBefore($sidebar_nav);
            $navbar_form.insertBefore($nav_content);

            $(".sidebar-wrapper .dropdown .dropdown-menu > li > a").click(function (event) {
                event.stopPropagation();

            });

            mobile_menu_initialized = true;
        } else {
            console.log('window with:' + $(window).width());
            if ($(window).width() > 991) {
                // reset all the additions that we made for the sidebar wrapper only if the screen is bigger than 991px
                $sidebar_wrapper.find('.navbar-form').remove();
                $sidebar_wrapper.find('.nav-mobile-menu').remove();

                mobile_menu_initialized = false;
            }
        }

        if (!toggle_initialized) {
            $toggle = $('.navbar-toggler');

            $toggle.click(function () {

                if (mobile_menu_visible == 1) {
                    $('html').removeClass('nav-open');

                    $('.close-layer').remove();
                    setTimeout(function () {
                        $toggle.removeClass('toggled');
                    }, 400);

                    mobile_menu_visible = 0;
                } else {
                    setTimeout(function () {
                        $toggle.addClass('toggled');
                    }, 430);


                    main_panel_height = $('.main-panel')[0].scrollHeight;
                    $layer = $('<div class="close-layer"></div>');
                    $layer.css('height', main_panel_height + 'px');
                    $layer.appendTo(".main-panel");

                    setTimeout(function () {
                        $layer.addClass('visible');
                    }, 100);

                    $layer.click(function () {
                        $('html').removeClass('nav-open');
                        mobile_menu_visible = 0;

                        $layer.removeClass('visible');

                        setTimeout(function () {
                            $layer.remove();
                            $toggle.removeClass('toggled');

                        }, 400);
                    });

                    $('html').addClass('nav-open');
                    mobile_menu_visible = 1;

                }
            });

            toggle_initialized = true;
        }
    }
}

$(document).ready(function () {
	/* Подключение плагинов */
	if ($("[data-toggle='tooltip']").length != 0) {
		$('[data-toggle="tooltip"]').tooltip({
				trigger : 'hover'
		 });
    } 
		
    if ($("[data-toggle='switch']").length != 0) {
        $("[data-toggle='switch']").bootstrapSwitch();
    }
	
    window_width = $(window).width();
    // Загрузка и активация пользовательских настроек
    actions.loadOptionsUser();
	
    // check if there is an image set for the sidebar's background
    actions.checkSidebarImage();

    // Init navigation toggle for small screens
    if (window_width <= 991) {
        actions.initRightMenu();
    }
	// activate collapse right menu when the windows is resized
	$(window).resize(function () {
		if ($(window).width() <= 991) {
			actions.initRightMenu();
		}
	});	

    $('.form-control').on("focus", function () {
        $(this).parent('.input-group').addClass("input-group-focus");
    }).on("blur", function () {
        $(this).parent(".input-group").removeClass("input-group-focus");
    });

    // Фикс бага с меню на IOS
    $('body').on('touchstart.dropdown', '.dropdown-menu', function (e) {
        e.stopPropagation();
    });

    $sidebar = $('.sidebar');
    $sidebar_img_container = $sidebar.find('.sidebar-background');

    $full_page = $('.full-page');

    $sidebar_responsive = $('body > .navbar-collapse');

    window_width = $(window).width();

    fixed_plugin_open = $('.sidebar .sidebar-wrapper .nav li.active a p').html();

    if (window_width > 767 && fixed_plugin_open == 'Dashboard') {
        if ($('.fixed-plugin .dropdown').hasClass('show-dropdown')) {
            $('.fixed-plugin .dropdown').addClass('show');
        }

    }

    $('.fixed-plugin a').click(function (event) {
        // Alex if we click on switch, stop propagation of the event, so the dropdown will not be hide, otherwise we set the  section active
        if ($(this).hasClass('switch-trigger')) {
            if (event.stopPropagation) {
                event.stopPropagation();
            } else if (window.event) {
                window.event.cancelBubble = true;
            }
        }
    });
	
	/*Выбор цвета*/
    $('.fixed-plugin .background-color span').click(function () { 
        $(this).siblings().removeClass('active');
        $(this).addClass('active');

        var new_color = $(this).data('color');

        if ($sidebar.length != 0) {
            $sidebar.attr('data-color', new_color);
        }

        if ($full_page.length != 0) {
            $full_page.attr('filter-color', new_color);
        }

        if ($sidebar_responsive.length != 0) {
            $sidebar_responsive.attr('data-color', new_color);
        }
        color = {"color_filter": new_color}
        actions.saveOptionsUser(color);
    });

    /*Выбор картинки*/
    $('.fixed-plugin .img-holder').click(function () {

        $(this).parent('li').siblings().removeClass('active');
        $(this).parent('li').addClass('active');

        var new_image = $(this).find("img").attr('src');

        if ($sidebar_img_container.length != 0 && $('.switch-sidebar-image input:checked').length != 0) {
            $sidebar_img_container.fadeOut('fast', function () {
                $sidebar_img_container.css('background-image', 'url("' + new_image + '")');
                $sidebar_img_container.fadeIn('fast');
            });
        }

        if ($('.switch-sidebar-image input:checked').length == 0) {
            var new_image = $('.fixed-plugin li.active .img-holder').find("img").attr('src');
            var new_image_full_page = $('.fixed-plugin li.active .img-holder').find('img').data('src');

            $sidebar_img_container.css('background-image', 'url("' + new_image + '")');
        }

        if ($sidebar_responsive.length != 0) {
            $sidebar_responsive.css('background-image', 'url("' + new_image + '")');
        }

        background_image = {"sidebar_img": new_image.substring(new_image.lastIndexOf('/') + 1, new_image.length)}
        actions.saveOptionsUser(background_image);
    });

    /*Отображать или нет картинку в сайдбаре*/
    $('.switch input').on("switchChange.bootstrapSwitch", function () {
        $input = $(this);
        if ($input.is(':checked')) {
            if ($sidebar_img_container.length != 0) {
                $sidebar_img_container.fadeIn('fast');
                $sidebar.attr('data-image', '#');
            }
            background_image = {"show_image_in_sidebar": "yes"}
            actions.saveOptionsUser(background_image);
        } else {
            if ($sidebar_img_container.length != 0) {
                $sidebar.removeAttr('data-image');
                $sidebar_img_container.fadeOut('fast');
            }
            background_image = {"show_image_in_sidebar": "no"}
            actions.saveOptionsUser(background_image);
        }
    });
    
    /*Пометить все сообщения прочитанными*/
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