const MODAL_TITLE_SIGN_UP = lang_var('sys.sign_up_text');
const MODAL_TITLE_LOG_IN = lang_var('sys.log_in');
const MODAL_TITLE_RESTORE_PASSWORD = lang_var('sys.restore_password');
const urlParams = getUrlVars();
const urlReturn = urlParams['return'] || '/';

function getUrlVars() {
    const vars = {};
    window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m, key, value) {
        vars[key] = value;
    });
    return vars;
}

function shakeModal($text, $not_error = false) {
    return new Promise((resolve) => {
        $('#loginModal .modal-dialog').addClass('shake');
        if (!$not_error) {            
            $('.error').addClass('alert alert-danger').html($text);
        } else {
            $('.error').addClass('alert alert-success').html($text);
        }
        setTimeout(function () {
            $('#loginModal .modal-dialog').removeClass('shake');
            resolve();  // Завершаем промис после 3 секунд
        }, 500);
    });
}

function showModalWithContent(title, contentClassToShow) {
    $('.modal-title').html(title);
    $('.loginBox, .login-footer, .registerBox, .register-footer, .PasswordRecoveryBox, .recovery-footer').hide();
    $(contentClassToShow).show();
    $('.error').removeClass('alert alert-danger').html('');
    setTimeout(function () {
        $('#loginModal').modal('show');
    }, 230);
}

function openLoginModal() {
    showModalWithContent(MODAL_TITLE_LOG_IN, '.loginBox, .login-footer');
}

function openRegisterModal() {
    showModalWithContent(MODAL_TITLE_SIGN_UP, '.registerBox, .register-footer');
}

function openRecoveryModal() {
    showModalWithContent(MODAL_TITLE_RESTORE_PASSWORD, '.PasswordRecoveryBox, .recovery-footer');
}

function performAjaxRequest(url, data, successCallback) {
    $.ajax({
        type: 'POST',
        url: url,
        dataType: 'json',
        data: data,
        beforeSend: function () {
            $("#preloader").fadeIn("slow");
        },
        success: successCallback,
        error: function (xhr, ajaxOptions, thrownError) {
            console.log(xhr.status, xhr.responseText, thrownError);
        },
        complete: function () {
            $("#preloader").fadeOut("slow");
        }
    });
}

$(document).ready(function () {
    let registrationFlag = false;
    // Не скрываем форму при клике вне формы
    $('#loginModal').modal({
        backdrop: 'static',
        keyboard: false
    });

    // Реакция кнопок
    $('#close_button').click(() => {
        if (urlReturn === '/') {
            document.location.href = '/';
        } else {
            document.location.href = `/${urlReturn}`;
        }
    });

    // Скрытие формы
    $('#loginModal').on('hidden.bs.modal', () => {
        $('#close_button').click();
    });

    $('#loginModal').modal({
        backdrop: 'static',
        keyboard: false
    });

    $("#log_form").submit(async function (e) {
        e.preventDefault();
        const formData = $(this).serialize();

        // Получение параметра 'return' из URL
        const urlParams = new URLSearchParams(window.location.search);
        const returnUrl = urlParams.get('return');

        await performAjaxRequest('/login', formData, async function (data) {
            if (data.error !== "") {
                await shakeModal(data['error']);
            } else {
                await shakeModal(lang_var('sys.welcome') + '!', true);

                // Переход на адрес из параметра 'return', если он существует, иначе переход на '/admin'
                window.location = returnUrl ? returnUrl : "/admin";
            }
        });
    });

    // Отправка регистрационной формы
    $("#reg_form").submit(function () {
        const formData = $(this).serialize();
        performAjaxRequest('/register', formData, function (data) {
            if (data.error !== "") {
                shakeModal(data['error']);
                if (data.error === lang_var('sys.welcome')) {
                    $('#return_general').submit();
                }
            } else {
                shakeModal(lang_var('sys.verify_email'), true);
            }
        });
        return false;
    });

    // Отправка формы восстановления пароля
    $("#recovery_form").submit(function () {
        const formData = $(this).serialize();
        performAjaxRequest('/recovery_password', formData, function (data) {
            if (data.error !== "") {
                shakeModal(data['error']);
            } else {
                shakeModal(lang_var('sys.new_password_in_email'), true);
            }
        });
        return false;
    });

    // Удаляем пробелы при вводе
    $('#reg_email, #reg_password, #reg_password_confirmation, #rec_email').keyup(function () {
        $(this).val($(this).val().replace(/\s+/g, ''));
    });

    // autocomlete off!important
    function cleanForm() {
        if (!registrationFlag) {
            $('#reg_email, #reg_password, #rec_email').val('');
            $('.fa-star').removeClass('fa-star').addClass('fa-star-o');
            $('.badge').hide();
        }
        if ($('.modal-title').html() === MODAL_TITLE_SIGN_UP) {
            registrationFlag = true;
        }
    }

    $('#registration_button').on('click', cleanForm);
    $('#loginModal').on('shown.bs.modal', cleanForm);

    // init plugins
    if (jQuery().validator) {
        $('[data-validator]').validator();
    }
    
    function validatePhone(phone) {
        const filter = /^\+?(\d[\d-. ]+)?(\([\d-. ]+\))?[\d-. ]+\d$/;
        return filter.test(phone);
    }
    
    if (jQuery().tooltip && $("[data-toggle='tooltip']").length) {
        $('[data-toggle="tooltip"]').tooltip({
            delay: {show: 500, hide: 100},
            trigger: 'hover'
        });
        $('body').click(function () {
            $('[data-toggle="tooltip"]').tooltip('hide');
        });
    }
});