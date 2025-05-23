const MODAL_TITLE_SIGN_UP = AppCore.getLangVar('sys.sign_up_text');
const MODAL_TITLE_LOG_IN = AppCore.getLangVar('sys.log_in');
const MODAL_TITLE_RESTORE_PASSWORD = AppCore.getLangVar('sys.restore_password');
const urlParams = AppCore.getUrlVars();
const urlReturn = urlParams['return'] || '/';

/**
 * Анимирует модальное окно в зависимости от типа сообщения: "встряхивание" для ошибки и плавное исчезновение для успеха.
 * @param {string} message Текст сообщения.
 * @param {boolean} isSuccess Флаг, указывающий на успех операции.
 * @returns {Promise<void>} Promise, который выполнится после завершения анимации или исчезновения.
 */
function shakeOrFadeModal(message, isSuccess = false) {
    return new Promise((resolve) => {
        const alertClass = isSuccess ? 'alert alert-success' : 'alert alert-danger';
        $('.error').removeClass('alert alert-danger alert-success').addClass(alertClass).html(message);
        if (isSuccess) {
           $('#loginModal').fadeTo(500, 0.5, resolve);
        } else {
            $('#loginModal .modal-dialog').addClass('shake');
            setTimeout(() => {
                $('#loginModal .modal-dialog').removeClass('shake');
                resolve();
            }, 500);
        }
    });
}

/**
 * Отображает модальное окно с указанным содержимым.
 * @param {string} title Заголовок модального окна.
 * @param {string} contentClassToShow Класс контента для отображения.
 */
function showModalWithContent(title, contentClassToShow) {
    $('.modal-title').html(title);
    $('.loginBox, .login-footer, .registerBox, .register-footer, .PasswordRecoveryBox, .recovery-footer').hide();
    $(contentClassToShow).show();
    $('.error').removeClass('alert alert-danger').html('');
    setTimeout(function () {
        $('#loginModal').modal('show');
    }, 230);
}

/**
 * Открывает модальное окно для входа.
 */
function openLoginModal() {
    showModalWithContent(MODAL_TITLE_LOG_IN, '.loginBox, .login-footer');
}

/**
 * Открывает модальное окно для регистрации.
 */
function openRegisterModal() {
    showModalWithContent(MODAL_TITLE_SIGN_UP, '.registerBox, .register-footer');
}

/**
 * Открывает модальное окно для восстановления пароля.
 */
function openRecoveryModal() {
    showModalWithContent(MODAL_TITLE_RESTORE_PASSWORD, '.PasswordRecoveryBox, .recovery-footer');
}

/**
 * Выполняет AJAX-запрос.
 * @param {string} url URL для запроса.
 * @param {Object} data Данные для отправки.
 * @param {Function} successCallback Функция обратного вызова при успешном запросе.
 */
function performAjaxRequest(url, data, successCallback) {
    AppCore.sendAjaxRequest(
        url,
        data,
        'POST',
        'json',
        successCallback,
        function (jqXHR, textStatus, errorThrown) {
            console.error("AJAX request failed:", textStatus, errorThrown);
            console.error("Response details:", jqXHR.status, jqXHR.responseText);
        }
    );
}

$(document).ready(function () {
    let registrationFlag = false;
    // Не скрываем форму при клике вне формы
    $('#loginModal').modal({
        backdrop: 'static',
        keyboard: false
    });
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
        const urlParams = new URLSearchParams(window.location.search);
        const returnUrl = urlParams.get('return');
        await performAjaxRequest('/login', formData, async function (data) {
            if (data.error !== "") {
                await shakeOrFadeModal(data['error']);
            } else {
                await shakeOrFadeModal(AppCore.getLangVar('sys.welcome') + '!', true);
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
                shakeOrFadeModal(data['error']);
                if (data.error === AppCore.getLangVar('sys.welcome')) {
                    setTimeout($('#return_general').submit(), 5000);
                    return false;
                }
            } else {
                shakeOrFadeModal(AppCore.getLangVar('sys.verify_email'), true);
            }
        });
        return false;
    });

    // Отправка формы восстановления пароля
    $("#recovery_form").submit(function () {
        const formData = $(this).serialize();
        performAjaxRequest('/recovery_password', formData, function (data) {
            if (data.error !== "") {
                shakeOrFadeModal(data['error']);
            } else {
                shakeOrFadeModal(AppCore.getLangVar('sys.new_password_in_email'), true);
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