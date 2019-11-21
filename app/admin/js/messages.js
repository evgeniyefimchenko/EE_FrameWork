/*Мессенджер*/
$(document).ready(function () {
    /*Отметить сообщения*/
    $("#read_all_message, #kill_all_message, #set_readed, #dell_message").click(function () {                
        if ($(this).attr('id') === 'kill_all_message') {
            if (confirm("Удалить все сообщения?")) {
            } else {
                return false;
            }
        }
        $.ajax({
            url: '/admin/' + $(this).attr('id') + '/' + $(this).data('id'),
            success: function () {
                if ($(this).attr('id') === 'set_readed_all') {
                    window.location = '/admin/' + $(this).data('return');
                } else {
                    window.location = '/admin/messages';
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status, xhr.responseText, thrownError, ajaxOptions);
            }
        });
    });
});