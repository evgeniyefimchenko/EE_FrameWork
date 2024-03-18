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

$(document).ready(function () {
    $('.list-group-item').click(function () {
        let docName = $(this).data('doc');
        sendAjaxRequest('/get_doc', {docName: docName}, 'POST', 'html', renderDoc, errorDoc);
    });
});
