/* Редактирование почтового шаблона */

$(document).ready(function () {
    setActiveNavLink('/admin/email_templates');
    $('.insert-snippet').click(function() {
        let snippet = $(this).data('snippet');
        $('#body-input').summernote('insertText', snippet);
    });    
    $('#body-input').summernote(AppCore.summernoteParams);
    AppCore.setupTabEventListeners();
    AppCore.handleTabsFromUrl();
    $('#send_test_email').click(function() {
        var template_id = $('#template_id').attr('data-id');
        if (parseInt(template_id)) {
        AppCore.sendAjaxRequest('/admin/sendTestEmail',
            {
                email_test: $('#testEmail').val(),
                template_id: template_id
            },
            'POST',
            'json',
            function(response) {
                alert(response.status);
            },
            function(jqXHR, textStatus, errorThrown) {
                console.log(jqXHR, textStatus, errorThrown);
            });
        } else {
            alert('Требуется template_id ' + parseInt(template_id));
        }
    });
});
