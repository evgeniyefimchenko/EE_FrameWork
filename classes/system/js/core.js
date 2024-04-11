window.preloader = $('#preloader');
window.lang_select = $('#lang_select');

(function () {
    function loadLanguageVars(callback) {
        if (!localStorage.getItem('langVars') || localStorage.getItem('langVars') === "var not found!") {
            sendAjaxRequest(
                    '/language',
                    'loadAll=true',
                    'POST',
                    'json',
                    function (response) {
                        localStorage.setItem('langVars', response.langVars);
                        localStorage.setItem('envGlobal', response.envGlobal);
                        callback();
                    },
                    function (jqXHR, textStatus, errorThrown) {
                        console.error("Error loading language variables:", textStatus, errorThrown);
                        callback();
                    }
            );
        } else {
            callback();
        }
    }

    window.lang_var = function (key) {
        const langVars = safeParseJSON(localStorage.getItem('langVars'));
        return langVars ? langVars[key] : '';
    };
    window.env_Global = function (key) {
        const envGlobal = safeParseJSON(localStorage.getItem('envGlobal'));
        return envGlobal ? envGlobal[key] : '';
    };

    $(document).ready(function () {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
        loadLanguageVars(function () {
            window.preloader.fadeOut(500);
            window.lang_select.click(function () {
                sendAjaxRequest(
                    '/set_options/' + $(this).attr('data-langcode'),
                    {},
                    'POST',
                    'json',
                    function (data) {
                        if (data.error !== 'no') {
                            console.error("Error setting language:", data);
                        } else {
                            window.location.reload();
                        }
                    },
                    function (jqXHR, textStatus, errorThrown) {
                        console.error("Error during language selection:", textStatus, errorThrown);
                    }
                );
            });
        });
    });
})();

/**
 * Безопасно анализирует строку JSON, предотвращая выброс исключения.
 * В случае ошибки анализа выводит сообщение об ошибке в консоль и возвращает `null`.
 * Эта функция полезна, когда необходимо обработать неизвестные или потенциально некорректные JSON-строки,
 * обеспечивая отказоустойчивость приложения.
 * @param {string} json Строка в формате JSON, которую нужно анализировать.
 * @returns {Object|null} Результат анализа строки JSON как объект или `null`, если анализ не удался.
 */
function safeParseJSON(json) {
    try {
        return JSON.parse(json);
    } catch (e) {
        console.error("Error parsing JSON:", e);
        return null;
    }
}

/**
 * Стандартная функция обратного вызова после успешного выполнения AJAX-запроса.
 * Выводит в консоль сообщение об успехе и данные ответа.
 * @param {Object|string} response Ответ от сервера. Может быть объектом или строкой,
 *                                 в зависимости от типа данных ответа (dataType), указанного в AJAX-запросе.
 */
function defaultSuccessCallback(response) {
    console.log('Request Successful:', response);
}

/**
 * Стандартная функция обратного вызова для обработки ошибок при выполнении AJAX-запроса.
 * Выводит в консоль сообщение об ошибке, статус и текст ошибки.
 * @param {jqXHR} jqXHR Объект jqXHR, представляющий ответ сервера, который содержит детали ошибки.
 *                      jqXHR - это расширение объекта XMLHttpRequest и предоставляет все его свойства и методы.
 * @param {string} textStatus Описательный статус ошибки, например "timeout", "error", "abort", и "parsererror".
 * @param {string} errorThrown Текстовое сообщение об ошибке, выброшенное при попытке отправить запрос.
 */
function defaultErrorCallback(jqXHR, textStatus, errorThrown) {
    console.error('Request Failed:', textStatus, errorThrown);
}

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
function sendAjaxRequest(
        url,
        data,
        method,
        dataType = 'json',
        successCallback = defaultSuccessCallback,
        errorCallback = defaultErrorCallback,
        headers = {}
) {
    $.ajax({
        url: url,
        type: method,
        data: data,
        headers: headers,
        dataType: dataType,
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
}

// Константы с настройками TYNIMCE
const settingsShortDescription = {
    language: 'ru',
    skin: 'oxide-dark',
    language_load: false,
    deprecation_warnings: false,
    menubar: 'file edit view',
    toolbar: "undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | align numlist bullist | link image"
};

const settingsLongDescription = {
    language: 'ru',
    skin: 'oxide-dark',
    language_load: false,
    deprecation_warnings: false,
    plugins: 'imageEditor preview importcss searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media template codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help charmap quickbars emoticons accordion',
    menubar: 'file edit view insert format tools table help',
    toolbar: "undo redo | accordion accordionremove | blocks fontfamily fontsize | bold italic underline strikethrough | align numlist bullist | link image imageEditor | table media | lineheight outdent indent| forecolor backcolor removeformat | charmap emoticons | code fullscreen preview | save print | pagebreak anchor codesample",
    autosave_ask_before_unload: true,
    autosave_interval: '30s',
    autosave_prefix: '{path}{query}-{id}-',
    autosave_restore_when_empty: false,
    autosave_retention: '2m',
    image_advtab: true,
    link_list: [{title: 'General page', value: '/'}],
    importcss_append: true,
    height: 600,
    image_caption: true,
    quickbars_selection_toolbar: 'bold italic | quicklink h2 h3 blockquote quickimage quicktable',
    noneditable_class: 'mceNonEditable',
    toolbar_mode: 'sliding',
    contextmenu: 'link image table',
    setup: function(editor) {
        // Добавление кнопки, которая открывает elFinder
        editor.ui.registry.addButton('elfinder', {
            text: 'Выбрать файл',
            onAction: function() {
                // Открывает elFinder в модальном окне или в новом окне
                let win = window.open('/assets/editor/elFinder/elfinder.html', 'elfinder', 'width=900,height=600');
                window.processFile = function(fileUrl) {
                    // Функция обратного вызова для вставки URL в контент TinyMCE
                    editor.insertContent('<a href="' + fileUrl + '">' + fileUrl + '</a>');
                    win.close(); // Закрываем окно elFinder после выбора файла
                };
            }
        });
    }    
};

/**
 * Инициализирует редактор TinyMCE с заданной конфигурацией и поддерживает множественные экземпляры на странице.
 * @param {string} selector - CSS селектор для элемента, к которому будет применён редактор.
 * @param {Object} settings - Объект настроек для инициализации TinyMCE.
 */
function initializeTinyMCE(selector, settings) {
    settings.selector = selector;
    tinymce.init(settings);
}


