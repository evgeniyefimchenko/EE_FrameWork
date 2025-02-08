function renderDoc(response) {
    $('#docs-content').html(response);
    initializeStickyMenu();
}

function errorDoc(jqXHR, textStatus, errorThrown) {
    $('#docs-content').html(jqXHR, textStatus, errorThrown);
}

function initializeStickyMenu() {
    var windowWidth = $(window).width();
    if (windowWidth > 1024) {
        var menuWrapper = $('#doc-menu-wrapper');
        var stickyWrapper = $('#sticky-wrapper');
        if (menuWrapper.length && stickyWrapper.length) {
            var stickyStart = menuWrapper.offset().top;
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
        // Если у элемента есть подменю, добавляем иконку
        let submenu = $(this).next('.list-group-submenu');
        if (submenu.length) {
            // Устанавливаем обработчик клика для элементов с подменю
            $(this).on('click', function (e) {
                e.preventDefault();  // Предотвращаем стандартное поведение ссылки
                submenu.slideToggle();  // Переключаем видимость подменю

                // Меняем иконку на стрелку вверх или вниз
                $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
            });
        } else {
            // Удаляем иконку, если подменю отсутствует
            $(this).find('i').remove();
        }

        // AJAX запрос для элементов, не имеющих подменю
        if (!submenu.length) {
            $(this).on('click', function (e) {
                e.preventDefault();
                let docName = $(this).data('doc');
                AppCore.sendAjaxRequest('/get_doc', { docName: docName }, 'POST', 'html', renderDoc, errorDoc);
            });
        }
    });
}

$(document).ready(function () {
    initializeMenuClickHandlers();
});
