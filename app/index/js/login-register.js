/*Расширение для получения параметров GET*/
$.extend({
  getUrlVars: function(){
    var vars = [], hash;
    var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
    for(var i = 0; i < hashes.length; i++)
    {
      hash = hashes[i].split('=');
      vars.push(hash[0]);
      vars[hash[0]] = hash[1];
    }
    return vars;
  },
  getUrlVar: function(name){
    return $.getUrlVars()[name];
  }
});

/* Сформировать форму регистрации */
function showRegisterForm() {
    $('#loginModal .loginBox, #loginModal .PasswordRecoveryBox, .login-footer, .recovery-footer').hide();
	$('.modal-title').html('Регистрация');
	$('.registerBox, .register-footer').show();
    $('.error').removeClass('alert alert-danger').html('');
}

/* Сформировать форму авторизации */
function showLoginForm() {
    $('#loginModal .registerBox, #loginModal .PasswordRecoveryBox, .register-footer, .recovery-footer').hide();
	$('.modal-title').html('Вход');
	$('.loginBox, .login-footer').show();	
    $('.error').removeClass('alert alert-danger').html('');
}

/* Сформировать форму восстановления */
function showRecoveryForm() {
	$('.modal-title').html('Восстановление пароля');
    $('#loginModal .registerBox, #loginModal .loginBox, .register-footer, .login-footer').hide();
	$('.PasswordRecoveryBox, .recovery-footer').show();		        
    $('.error').removeClass('alert alert-danger').html('');
}

/* Показать форму авторизации - используется для вызова из скриптов*/
function openLoginModal() {
    showLoginForm();
    setTimeout(function () {
        $('#loginModal').modal('show');
    }, 230);
}

/* Показать форму регистрации - используется для вызова из скриптов */
function openRegisterModal() {
    showRegisterForm();
    setTimeout(function () {
        $('#loginModal').modal('show');
    }, 230);
}

/* Показать форму восстановления - используется для вызова из скриптов */
function openRegisterModal() {
    showRecoveryForm();
    setTimeout(function () {
        $('#loginModal').modal('show');
    }, 230);
}

/* Потрясти форму */
function shakeModal($text) {
    $('#loginModal .modal-dialog').addClass('shake');
    $('.error').addClass('alert alert-danger').html($text);
    setTimeout(function () {
        $('#loginModal .modal-dialog').removeClass('shake');
    }, 3000);
}

$(document).ready(function () {
    /*Реакция кнопок*/
    $('#close_button').click(function () {
        var url_return = $.getUrlVar('return');
		if (url_return && url_return !== 'admin') {
			document.location.href = "/" + url_return;
		} else {
			document.location.href = "/";
		}
    });
	
	/*Скрытие формы*/
	$('#loginModal').on('hidden.bs.modal', function() {
	  $('#close_button').click();
	});
	
    /*Отправка формы входа на сайт*/
    $("#log_form").submit(function () {
        var form_data = $(this).serialize();
        $.ajax({
            type: 'POST',
            url: '/login',
            dataType: 'json',
            data: form_data,
            beforeSend: function () {
                $("#preloader").fadeIn("slow", function () {});
            },
            success: function (data) {
                if (data.error !== "") {
                    shakeModal(data['error']);
                } else {
                    shakeModal('Добро пожаловать!');
                    localStorage.clear();
                    setTimeout(window.location = "/admin", 3000);
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status, xhr.responseText, thrownError, ajaxOptions);
            },
            complete: function (data) {
                $("#preloader").fadeOut("slow", function () {});
            }
        });
        return false;
    });

    /*Отправка регистрационной формы*/
    $("#reg_form").submit(function () {
        var data = $(this).serialize();
        $.ajax({
            type: 'POST',
            url: '/register',
            dataType: 'json',
            data: data,
            beforeSend: function (data) {
                $("#preloader").fadeIn("slow", function () {});
            },
            success: function (data) {
                if (data.error !== "") {
                    shakeModal(data['error']);
                } else {
                    shakeModal('Мы выслали к вам на почту письмо с проверочным кодом.');
                    localStorage.clear();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status, xhr.responseText, thrownError);
            },
            complete: function (data) {
                $("#preloader").fadeOut("slow", function () {});
            }

        });
        return false;
    });

    /*Удаляем пробелы при вводе*/
    $('#reg_email, #reg_password, #reg_password_confirmation, #rec_email').keyup(function () {
        $(this).val($(this).val().replace(/\s{1,}/g, ''));
    });

    /*autocomlete off!important*/
    function clean_form() {
        if (!registration_flag) {
            $('#reg_email, #reg_password, #rec_email').val('');
            $('.fa-star').removeClass('fa-star').addClass('fa-star-o');
            $('.badge').hide();
        }
        if ($('.modal-title').html() == 'Регистрация') {
            registration_flag = true;
        }
    }
	
    var registration_flag = false;
    $('#registration_button').on('click', function () {
        clean_form();
    });
    $('#loginModal').on('shown.bs.modal', function () {
        clean_form();
    });

    /*init plugins*/
    if (jQuery().validator) {
        $('[data-validator]').validator();
    }
    if (jQuery().tooltip) {
        $('[data-toggle="tooltip"]').tooltip({delay: { show: 500, hide: 100 }});
		$('body').click(function(){
			$('[data-toggle="tooltip"]').tooltip('hide');
		});
    }
});