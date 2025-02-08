const AppCore = (function () {
    // Глобальные переменные
    const preloader = $('#preloader');
    const lang_select = $('#lang_select');

    // Приватные функции
    function safeParseJSON(json) {
        try {
            if (typeof json !== 'string') {
                json = JSON.stringify(json);
            }
            return JSON.parse(json);
        } catch (e) {
            console.error("JSON Parsing Error:", e, "Received JSON:", json);
            return {};
        }
    }

    // Публичные методы
    return {
        init: function () {
            $(document).ready(function () {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (tooltipTriggerEl) {
                    new bootstrap.Tooltip(tooltipTriggerEl);
                });
                preloader.fadeOut(500);
                lang_select.click(function () {
                    AppCore.sendAjaxRequest(
                            '/set_options/' + $(this).attr('data-langcode'),
                            {},
                            'POST',
                            'json',
                            function (data) {
                                if (data.error !== 'no') {
                                    console.error("Error setting language:", data);
                                } else {
                                    // Перезагружаем страницу для применения нового языка
                                    location.reload();
                                }
                            },
                            function (jqXHR, textStatus, errorThrown) {
                                console.error("Error during language selection:", textStatus, errorThrown);
                            }
                    );
                });
            });
        },

        // Возвращает языковую переменную
        getLangVar: function (key) {
            return window.LANG_VARS?.[key] || `Key ${key} not found in LANG_VARS`;
        },

        // Возвращает все языковые переменные
        getAllLangVars: function () {
            return window.LANG_VARS || {};
        },

        // Отправка AJAX-запроса
        sendAjaxRequest: function (url, data, method, dataType = 'json', successCallback, errorCallback, headers = {}) {
            var isFormData = data instanceof FormData;

            if (!isFormData) {
                data.is_ajax = 1;
            } else {
                data.append('is_ajax', 1);
            }

            $.ajax({
                url: url,
                type: method,
                data: data,
                headers: headers,
                dataType: dataType,
                processData: !isFormData,
                contentType: isFormData ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
                success: function (response) {
                    if (successCallback && typeof successCallback === 'function') {
                        successCallback(response);
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    if (errorCallback && typeof errorCallback === 'function') {
                        errorCallback(jqXHR, textStatus, errorThrown);
                    }
                }
            });
        },

        // Загрузка CSS
        loadCSS: function (url) {
            var link = document.createElement("link");
            link.rel = "stylesheet";
            link.href = url;
            link.crossOrigin = "anonymous";
            document.head.appendChild(link);
        },

        // Загрузка JS
        loadJS: function (url) {
            var script = document.createElement("script");
            script.src = url;
            script.type = "text/javascript";
            script.crossOrigin = "anonymous";
            document.body.appendChild(script);
        },

        // Загрузка JS с callback
        loadJSWithCallback: function (url, callback) {
            var script = document.createElement("script");
            script.src = url;
            script.type = "text/javascript";
            script.crossOrigin = "anonymous";
            script.onload = callback;
            document.body.appendChild(script);
        },

        // Генерация случайного числа
        getRandomInteger: function (min = 1, max = 1000000) {
            if (isNaN(min) || isNaN(max)) {
                throw new Error("Аргументы должны быть числами.");
            }
            min = Math.ceil(min);
            max = Math.floor(max);
            return Math.floor(Math.random() * (max - min + 1)) + min;
        },
        getUrlVars: function () {
            var vars = {};
            window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function (m, key, value) {
                vars[key] = value;
            });
            return vars;
        },
        preloader: preloader,
        summernoteParams: {}
    };
})();

// Инициализация приложения
AppCore.init();
AppCore.summernoteParams = {
    height: 300,
    lang: AppCore.getLangVar('ENV_DEF_LANG') === 'RU' ? 'ru-RU' : 'en-US',
    toolbar: [
        ['custom', ['textManipulator']],
        ['style', ['style']],
        ['font', ['bold', 'italic', 'underline', 'clear']],
        ['fontname', ['fontname']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['height', ['height']],
        ['table', ['table']],
        ['insert', ['imagePlugin', 'link', 'hr']],
        ['view', ['fullscreen', 'codeview']],
        ['help', ['help']]
    ]
};