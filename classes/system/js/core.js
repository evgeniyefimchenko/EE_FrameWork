(function() {
    function loadLanguageVars(callback) {
        if (!localStorage.getItem('langVars') || localStorage.getItem('langVars') === "var not found!") {
            $.ajax({
                type: 'POST',
                url: '/language',
                data: 'loadAll=true',
                success: function(response) {
                    localStorage.setItem('langVars', response);
                    callback();
                },
                error: function(error) {
                    console.error("Error loading language variables:", error);
                    callback(); // даже в случае ошибки, мы продолжаем выполнение, чтобы не блокировать остальные скрипты
                }
            });
        } else {
            callback();
        }
    }

    window.lang_var = function(key) {
        const langVars = JSON.parse(localStorage.getItem('langVars'));
        return langVars[key] || 'Undefined';
    };

    $(document).ready(function() {
        loadLanguageVars(function() {
            $('#preloader').fadeOut(500);
            $('#lang_select').click(function() {
                $.ajax({
                    type: 'POST',
                    url: '/set_options/' + $(this).attr('data-langcode'),
                    dataType: 'json',
                    success: function(data) {
                        if (data.error !== 'no') {
                            console.error("Error setting language:", data);
                        } else {
                            window.location.reload();
                        }
                    },
                    error: function(error) {
                        console.error("Error during language selection:", error);
                    }
                });
            });
        });
    });
})();

/**
 * Отправляет AJAX-запрос к указанному URL
 * @param {string} url URL-адрес, по которому будет отправлен запрос.
 * @param {Object} data Объект с данными, которые будут отправлены в запросе.
 * @param {string} method HTTP-метод запроса, например 'GET' или 'POST'. 
 * @param {string} [dataType] Тип данных, ожидаемых в ответе (например, 'json', 'xml', 'html').
 * @param {Function} [successCallback] Функция обратного вызова, которая будет вызвана при успешном ответе.
 *                                      Принимает один аргумент - данные ответа.
 * @param {Function} [errorCallback] Функция обратного вызова, которая будет вызвана при возникновении ошибки.
 *                                    Принимает три аргумента: jqXHR, textStatus и errorThrown.
 * @param {Object} [headers] Объект с заголовками запроса.
 */
function sendAjaxRequest(url, data, method, dataType = 'json', successCallback, errorCallback, headers = {}) {
    $.ajax({
        url: url,
        type: method,
        data: data,
        headers: headers,
        dataType: dataType,
        success: function(response) {
            if (successCallback && typeof successCallback === 'function') {
                successCallback(response);
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            if (errorCallback && typeof errorCallback === 'function') {
                errorCallback(jqXHR, textStatus, errorThrown);
            } else {
                console.error('sendAjaxRequest error:', jqXHR, textStatus, errorThrown);
            }
        }
    });
}



