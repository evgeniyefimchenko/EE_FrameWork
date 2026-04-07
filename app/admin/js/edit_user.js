/*Редактирование профиля пользователя*/

$(document).ready(function () {
    setActiveNavLink('/admin/users');

    function updateApiKeyUi(meta, rawKey) {
        var generateBtn = $('#generate-api-key-btn');
        var revokeBtn = $('#revoke-api-key-btn');
        var output = $('#generated-api-key-output');
        var status = $('#api-key-status');
        var metaBox = $('#api-key-meta');
        var neverLabel = metaBox.data('never-label') || 'never';
        var prefixLabel = metaBox.data('prefix-label') || 'Prefix';
        var createdLabel = metaBox.data('created-label') || 'Created';
        var lastUsedLabel = metaBox.data('last-used-label') || 'Last used';
        var lastIpLabel = metaBox.data('last-ip-label') || 'Last IP';
        var missingLabel = metaBox.data('missing-label') || 'No active API key';
        var accessOnlyAdminLabel = metaBox.data('access-only-admin') || 'API is currently available only to the CMS administrator.';
        var activeLabel = metaBox.data('active-label') || 'active';
        var generateLabel = generateBtn.data('generate-label') || 'Generate API key';
        var regenerateLabel = generateBtn.data('regenerate-label') || 'Regenerate API key';

        if (typeof rawKey === 'string') {
            output.val(rawKey);
        }

        if (meta && typeof meta === 'object') {
            var prefix = meta.key_prefix ? String(meta.key_prefix) + '...' : '';
            status.html('<span class="badge text-bg-success">' + activeLabel + '</span>' + (prefix ? '<span class="ms-2 text-muted">' + prefix + '</span>' : ''));
            generateBtn.text(regenerateLabel);
            revokeBtn.prop('disabled', false);

            var metaHtml = '';
            if (prefix) {
                metaHtml += '<div><strong>' + prefixLabel + ':</strong> ' + prefix + '</div>';
            }
            if (meta.created_at) {
                metaHtml += '<div><strong>' + createdLabel + ':</strong> ' + meta.created_at + '</div>';
            }
            metaHtml += '<div><strong>' + lastUsedLabel + ':</strong> ' + (meta.last_used_at || neverLabel) + '</div>';
            if (meta.last_used_ip) {
                metaHtml += '<div><strong>' + lastIpLabel + ':</strong> ' + meta.last_used_ip + '</div>';
            }
            metaBox.html(metaHtml);
            return;
        }

        status.html('<span class="text-muted">' + missingLabel + '</span>');
        generateBtn.text(generateLabel);
        revokeBtn.prop('disabled', true);
        metaBox.text(accessOnlyAdminLabel);
        if (typeof rawKey !== 'string') {
            output.val('');
        }
    }

    $("#edit_users").submit(function (event) {
        event.preventDefault();
        var form = $(this);
        var data = form.serialize();
        var notify;
        var add = '';
        if (parseInt($("#id_user").data('id')) > 0) {
            add = '/id/' + $("#id_user").data('id');
        }
        notify = actions.showNotification('Please wait, data is being saved.', 'primary');
        AppCore.sendAjaxRequest(
            '/admin/ajax_user_edit' + add,
            data,
            'POST',
            'json',
            function (data) {
                notify.close();
                if (data.error !== 'no') {
                    console.log('error', data);
                    actions.showNotification(data.error || 'ERROR', 'danger');
                } else {
                    actions.showNotification(data.message || 'UPDATE SUCCESS', 'primary');
                    if (data.new == 1) {
                        window.location = "/admin/users";
                    } else {
                        window.location = "/admin/user_edit/id/" + data.id;
                    }
                }
            },
            function (jqXHR, textStatus, errorThrown) {
                notify.close();
                actions.showNotification('ERROR', 'danger');
                console.error("AJAX request failed:", textStatus, errorThrown);
                console.error("Response details:", jqXHR.status, jqXHR.responseText);
            }
        );
    });

    $('#generate-api-key-btn').on('click', function () {
        var button = $(this);
        var userId = parseInt(button.data('user-id'), 10) || 0;
        if (userId <= 0) {
            actions.showNotification('ERROR', 'danger');
            return;
        }

        var originalText = button.text();
        button.prop('disabled', true).text(button.data('pending-label') || originalText);
        $('#revoke-api-key-btn').prop('disabled', true);

        AppCore.sendAjaxRequest(
            '/admin/user_api_key_generate/id/' + userId,
            {},
            'POST',
            'json',
            function (data) {
                button.prop('disabled', false).text(originalText);
                if (!data || data.error !== 'no') {
                    actions.showNotification((data && (data.message || data.error)) || 'ERROR', 'danger');
                    return;
                }

                updateApiKeyUi(data.meta || null, data.api_key || '');
                actions.showNotification(button.data('success-text') || 'API key generated', 'primary');
            },
            function (jqXHR, textStatus, errorThrown) {
                button.prop('disabled', false).text(originalText);
                actions.showNotification('ERROR', 'danger');
                console.error('API key generation failed:', textStatus, errorThrown, jqXHR.responseText);
            }
        );
    });

    $('#revoke-api-key-btn').on('click', function () {
        var button = $(this);
        var userId = parseInt(button.data('user-id'), 10) || 0;
        if (userId <= 0) {
            actions.showNotification('ERROR', 'danger');
            return;
        }
        if (!window.confirm(button.data('confirm-text') || 'Revoke active API key?')) {
            return;
        }

        var originalText = button.text();
        button.prop('disabled', true).text(button.data('pending-label') || originalText);
        $('#generate-api-key-btn').prop('disabled', true);

        AppCore.sendAjaxRequest(
            '/admin/user_api_key_revoke/id/' + userId,
            {},
            'POST',
            'json',
            function (data) {
                button.prop('disabled', false).text(originalText);
                $('#generate-api-key-btn').prop('disabled', false);
                if (!data || data.error !== 'no') {
                    actions.showNotification((data && (data.message || data.error)) || 'ERROR', 'danger');
                    return;
                }

                updateApiKeyUi(null);
                actions.showNotification(button.data('success-text') || 'API key revoked', 'primary');
            },
            function (jqXHR, textStatus, errorThrown) {
                button.prop('disabled', false).text(originalText);
                $('#generate-api-key-btn').prop('disabled', false);
                actions.showNotification('ERROR', 'danger');
                console.error('API key revoke failed:', textStatus, errorThrown, jqXHR.responseText);
            }
        );
    });

    $('#new_pass_conf, #new_pass').on('input', function () {
        var pass = $("#new_pass").val();
        var pass_rep = $("#new_pass_conf").val();
        var hasError = false;
        // Проверка на соответствие паролей
        if (pass !== pass_rep) {
            hasError = true;
            $("#new_pass, #new_pass_conf").next('small').text('Passwords do not match');
        }
        // Проверка на длину пароля
        if (pass.length < 5 || pass_rep.length < 5) {
            hasError = true;
            $("#new_pass, #new_pass_conf").next('small').text('Passwords are less than 5 characters long');
        }
        if (hasError) {
            $("#new_pass, #new_pass_conf").removeClass('is-valid').addClass('is-invalid');
            $('#submit').prop('disabled', true);
        } else {
            $("#new_pass, #new_pass_conf").removeClass('is-invalid').addClass('is-valid');
            $("#new_pass, #new_pass_conf").next('small').text('');
            $('#submit').prop('disabled', false);
        }
    });
    $('#phone-input').mask('+7 (000) 000-00-00');  // Маска для российского формата телефона
});
