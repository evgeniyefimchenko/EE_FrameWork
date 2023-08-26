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
	$('.modal-title').html(lang_var('sys.sign_up_text'));
	$('.registerBox, .register-footer').show();
    $('.error').removeClass('alert alert-danger').html('');
}

/* Сформировать форму авторизации */
function showLoginForm() {
    $('#loginModal .registerBox, #loginModal .PasswordRecoveryBox, .register-footer, .recovery-footer').hide();
	$('.modal-title').html(lang_var('sys.log_in'));
	$('.loginBox, .login-footer').show();	
    $('.error').removeClass('alert alert-danger').html('');
}

/* Сформировать форму восстановления */
function showRecoveryForm() {
	$('.modal-title').html(lang_var('sys.restore_password'));
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
function shakeModal($text, $not_error = false) {
    if (!$not_error) {
		$('#loginModal .modal-dialog').addClass('shake');
		$('.error').addClass('alert alert-danger').html($text);
	} else {
		$('.error').addClass('alert alert-success').html($text);
	}
    setTimeout(function () {
        $('#loginModal .modal-dialog').removeClass('shake');
    }, 3000);
}

$(document).ready(function () {
	
	/*Не скрываем форму при клике вне формы*/
    $('#loginModal').modal({
      backdrop: 'static',
      keyboard: false
    });
	
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
                    shakeModal(lang_var('sys.welcome') + '!', true);
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
					if (data.error == lang_var('sys.welcome')) {
						$('#return_general').submit();
					}
                } else {
                    shakeModal(lang_var('sys.verify_email'), true);
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
	
    /*Отправка формы восстановления пароля*/
    $("#recovery_form").submit(function () {
        var data = $(this).serialize();
        $.ajax({
            type: 'POST',
            url: '/recovery_password',
            dataType: 'json',
            data: data,
            beforeSend: function (data) {
                $("#preloader").fadeIn("slow", function () {});
            },
            success: function (data) {
                if (data.error !== "") {
                    shakeModal(data['error']);
                } else {
                    shakeModal(lang_var('sys.new_password_in_email'), true);
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
        if ($('.modal-title').html() == lang_var('sys.sign_up_text')) {
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
	// Решает баг с сзависанием подсказки
    if (jQuery().tooltip) {
		if ($("[data-toggle='tooltip']").length != 0) {
			$('[data-toggle="tooltip"]').tooltip({
					delay: { show: 500, hide: 100 },
					trigger : 'hover'
			 });
		}		
		$('body').click(function(){
			$('[data-toggle="tooltip"]').tooltip('hide');
		});
    }
});