/*Мессенджер*/
$(document).ready(function () {
    
    $('#messages-item-menu').addClass('nav-item active');
    
    /*Отметить сообщения*/    
    $("#kill_all_message, #set_readed, #dell_message").click(function () {
        let return_url = $(this).data('return');

        if ($(this).attr('id') === 'kill_all_message') {
            if (confirm("Удалить все сообщения?")) {
            } else {
                return false;
            }
        }

        if ($(this).attr('id') === 'dell_message') {
            if (confirm("Удалить сообщение?")) {
            } else {
                return false;
            }
        }

        $.ajax({
            url: '/admin/' + $(this).attr('id') + '/' + $(this).data('id'),
            success: function () {
                window.location = return_url;
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status, xhr.responseText, thrownError, ajaxOptions);
            }
        });

    })
})