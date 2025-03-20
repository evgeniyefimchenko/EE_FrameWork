function renderDoc(response) {
    $('#docs-content').html(response);
    initializeStickyMenu();
}

function errorDoc(jqXHR, textStatus, errorThrown) {
    $('#docs-content').html(`<div class="alert alert-danger">Ошибка: ${textStatus} - ${errorThrown}</div>`);
}

function initializeStickyMenu() {
    const windowWidth = $(window).width();
    if (windowWidth > 1024) {
        const menuWrapper = $('#doc-menu-wrapper');
        const stickyWrapper = $('#sticky-wrapper');
        if (menuWrapper.length && stickyWrapper.length) {
            const stickyStart = menuWrapper.offset().top;
            $(window).off('scroll').on('scroll', function () {
                if ($(window).scrollTop() >= stickyStart) {
                    stickyWrapper.addClass('sticky-horizontal');
                    stickyWrapper.width(menuWrapper.parent().width());
                } else {
                    stickyWrapper.removeClass('sticky-horizontal');
                    stickyWrapper.width('auto');
                }
            });
        }
    }
}

function initializeMenuClickHandlers() {
    $('.list-group-item').each(function () {
        const submenu = $(this).next('.list-group-submenu');

        if (submenu.length) {
            // Добавляем обработчик для элементов с подменю
            $(this).on('click', function (e) {
                e.preventDefault(); // Предотвращаем стандартное поведение ссылки
                submenu.slideToggle(); // Переключаем видимость подменю

                // Меняем иконку на стрелку вверх или вниз
                $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
            });
        } else {
            // AJAX-запрос для элементов без подменю
            $(this).on('click', function (e) {
                e.preventDefault();
                const docName = $(this).data('doc');
                AppCore.sendAjaxRequest('/get_doc', { docName }, 'POST', 'html', renderDoc, errorDoc);
            });
        }
    });

    // Устанавливаем начальное состояние иконок в подменю
    $('.list-group-submenu').hide(); // Скрываем все подменю по умолчанию
}

$(document).ready(function () {
    initializeMenuClickHandlers();
});
