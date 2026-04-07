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
        const feedback = $('#auth-feedback');
        feedback
            .removeClass('is-success is-error is-visible')
            .empty();

        if (message) {
            feedback
                .addClass(isSuccess ? 'is-success' : 'is-error')
                .addClass('is-visible')
                .html(message);
        }

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
    $('#auth-feedback').removeClass('is-success is-error is-visible').empty();
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

$(document).on('click', '[data-auth-modal]', function (event) {
    event.preventDefault();
    const target = $(this).data('auth-modal');
    if (target === 'register') {
        openRegisterModal();
        return;
    }
    if (target === 'recovery') {
        openRecoveryModal();
        return;
    }
    openLoginModal();
});

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
            const fallbackMessage = AppCore.getLangVar('sys.server_request_error') || 'Server request error.';
            shakeOrFadeModal(fallbackMessage);
        }
    );
}

function isValidEmail(value) {
    const email = String(value || '').trim();
    return /^([a-zа-яё0-9_\.-])+@[a-zа-яё0-9-]+\.([a-zа-яё]{2,4}\.)?[a-zа-яё]{2,4}$/i.test(email);
}

function getTrimmedValue(selector) {
    return String($(selector).val() || '').trim();
}

function validateLoginForm() {
    const email = getTrimmedValue('#log_email');
    const password = getTrimmedValue('#log_password');

    if (email === '' || password === '') {
        return AppCore.getLangVar('sys.empty_field') || 'Пустое поле';
    }
    if (!isValidEmail(email)) {
        return AppCore.getLangVar('sys.invalid_mail_format') || 'Неверный формат почты';
    }

    return '';
}

function validateRegisterForm() {
    const email = getTrimmedValue('#reg_email');
    const password = getTrimmedValue('#reg_password');
    const confirmation = getTrimmedValue('#reg_password_confirmation');
    const privacyAccepted = $('#reg_privacy_policy_accepted').is(':checked');
    const consentAccepted = $('#reg_personal_data_consent_accepted').is(':checked');

    if (email === '' || password === '' || confirmation === '') {
        return AppCore.getLangVar('sys.empty_field') || 'Пустое поле';
    }
    if (!isValidEmail(email)) {
        return AppCore.getLangVar('sys.invalid_mail_format') || 'Неверный формат почты';
    }
    if (password.length < 5) {
        return AppCore.getLangVar('sys.password_too_short') || 'Пароль слишком короткий';
    }
    if (password !== confirmation) {
        return AppCore.getLangVar('sys.password_mismatch') || 'Пароли не совпадают.';
    }
    if (!privacyAccepted) {
        return AppCore.getLangVar('sys.privacy_policy_acceptance_required') || 'Необходимо принять Политику в отношении обработки персональных данных.';
    }
    if (!consentAccepted) {
        return AppCore.getLangVar('sys.personal_data_consent_required') || 'Необходимо дать согласие на обработку персональных данных.';
    }

    return '';
}

function validateRecoveryForm() {
    const email = getTrimmedValue('#rec_email');
    if (email === '') {
        return AppCore.getLangVar('sys.empty_field') || 'Пустое поле';
    }
    if (!isValidEmail(email)) {
        return AppCore.getLangVar('sys.invalid_mail_format') || 'Неверный формат почты';
    }

    return '';
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
        const validationError = validateLoginForm();
        if (validationError !== '') {
            await shakeOrFadeModal(validationError);
            return false;
        }
        const formData = $(this).serialize();
        const urlParams = new URLSearchParams(window.location.search);
        const returnUrl = urlParams.get('return');
        await performAjaxRequest('/login', formData, async function (data) {
            if (data.error !== "") {
                await shakeOrFadeModal(data['error']);
            } else {
                const successMessage = data.message !== "" ? data.message : AppCore.getLangVar('sys.welcome') + '!';
                await shakeOrFadeModal(successMessage, true);
                if (data.redirect) {
                    window.location = returnUrl ? returnUrl : data.redirect;
                }
            }
        });
    });

    // Отправка регистрационной формы
    $("#reg_form").submit(function () {
        const validationError = validateRegisterForm();
        if (validationError !== '') {
            shakeOrFadeModal(validationError);
            return false;
        }
        const formData = $(this).serialize();
        performAjaxRequest('/register', formData, function (data) {
            if (data.error !== "") {
                shakeOrFadeModal(data['error']);
            } else {
                const successMessage = data.message !== "" ? data.message : AppCore.getLangVar('sys.verify_email');
                shakeOrFadeModal(successMessage, true).then(function () {
                    if (data.redirect) {
                        window.location = data.redirect;
                    }
                });
            }
        });
        return false;
    });

    // Отправка формы восстановления пароля
    $("#recovery_form").submit(function () {
        const validationError = validateRecoveryForm();
        if (validationError !== '') {
            shakeOrFadeModal(validationError);
            return false;
        }
        const formData = $(this).serialize();
        performAjaxRequest('/recovery_password', formData, function (data) {
            if (data.error !== "") {
                shakeOrFadeModal(data['error']);
            } else {
                const successMessage = data.message !== "" ? data.message : AppCore.getLangVar('sys.new_password_in_email');
                shakeOrFadeModal(successMessage, true);
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
