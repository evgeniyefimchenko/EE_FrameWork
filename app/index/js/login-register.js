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

function showRegisterForm() {
    $('.loginBox').fadeOut('fast', function () {
        $('.registerBox').fadeIn('fast');
        $('.login-footer').fadeOut('fast', function () {
            $('.register-footer').fadeIn('fast');
        });
        $('.modal-title').html('Регистрация');
    });
    $('.error').removeClass('alert alert-danger').html('');

}

function showLoginForm() {
    $('#loginModal .registerBox').fadeOut('fast', function () {
        $('.loginBox').fadeIn('fast');
        $('.register-footer').fadeOut('fast', function () {
            $('.login-footer').fadeIn('fast');
        });

        $('.modal-title').html('Вход');
    });
    $('.error').removeClass('alert alert-danger').html('');
}

function openLoginModal() {
    showLoginForm();
    setTimeout(function () {
        $('#loginModal').modal('show');
    }, 230);

}

function openRegisterModal() {
    showRegisterForm();
    setTimeout(function () {
        $('#loginModal').modal('show');
    }, 230);

}

function shakeModal($text) {
    $('#loginModal .modal-dialog').addClass('shake');
    $('.error').addClass('alert alert-danger').html($text);
    setTimeout(function () {
        $('#loginModal .modal-dialog').removeClass('shake');
    }, 3000);
}

$(document).ready(function () {
    /*Реакция кнопок*/
    $('#registration_button').click(function () {
        openRegisterModal();
    });
	
    $('#login_button').click(function () {
        openLoginModal();
    });

    $('#close_button').click(function () {
        var url_return = $.getUrlVar('return');
		if (url_return) {
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
    $('#reg_email, #reg_password, #reg_password_confirmation').keyup(function () {
        $(this).val($(this).val().replace(/\s{1,}/g, ''));
    });

    /*autocomlete off*/
    function clean_form() {
        if (!registration_flag) {
            $('#reg_email').val('');
            $('#reg_password').val('');
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
        $('[data-toggle="tooltip"]').tooltip();
    }
});