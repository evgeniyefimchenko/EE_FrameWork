/**
* Подключается на всех страницах проекта
*/

/**
* Функция вызова языковой переменной из файла
* @param str text - код переменной
*/
function lang_var(text) {
	var res;
        $.ajax({
            type: 'POST',
            url: '/language',
			async: false,
            data: 'text=' + text,
            success: function (data) {
				res = data;				
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status, xhr.responseText, thrownError);
            }
        });
	return res;
}

// Переключение языка
$('#lang_select').click(function() {
	$.ajax({
		type: 'POST',
		url: '/set_options/' + $(this).attr('data-langcode'),
		dataType: 'json',
		success: function (data) {
			if (data.error !== 'no') {
				console.log('error', data);
			} else {
				window.location.reload();
			}					
		},
		error: function (xhr, ajaxOptions, thrownError) {
			console.log(xhr.status, xhr.responseText, thrownError, ajaxOptions);
		}
	});			
});