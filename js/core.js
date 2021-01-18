/**
* Подключается на всех страницах проекта
*/

/**
* Функция вызова языковой переменной из файла
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