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
                key = decodeURIComponent(key);
                value = decodeURIComponent(value);
                if (vars[key]) {
                    if (Array.isArray(vars[key])) {
                        vars[key].push(value);
                    } else {
                        vars[key] = [vars[key], value];
                    }
                } else {
                    vars[key] = value;
                }
            });
            return vars;
        },
        handleTabsFromUrl: function () {
            let getParams = AppCore.getUrlVars();
            let tabs = [];
            if (getParams['tabs[]']) {
                if (Array.isArray(getParams['tabs[]'])) {
                    tabs = tabs.concat(getParams['tabs[]'].map(tab => decodeURIComponent(tab)));
                } else {
                    tabs.push(decodeURIComponent(getParams['tabs[]']));
                }
            }
            // Обрабатываем collapse[]
            let collapses = [];
            if (getParams['collapse[]']) {
                if (Array.isArray(getParams['collapse[]'])) {
                    collapses = collapses.concat(getParams['collapse[]'].map(collapse => decodeURIComponent(collapse)));
                } else {
                    collapses.push(decodeURIComponent(getParams['collapse[]']));
                }
            }
            const clickTabsSequentially = (tabs, index = 0) => {
                if (index >= tabs.length) return;
                const tabId = tabs[index];
                if (tabId) {
                    let tabElement = $('#' + tabId);
                    if (tabElement.length) {
                        tabElement.click();
                        setTimeout(() => {
                            clickTabsSequentially(tabs, index + 1);
                        }, 100);
                    } else {
                        console.warn('Элемент с ID ' + tabId + ' не найден.');
                        clickTabsSequentially(tabs, index + 1);
                    }
                }
            };
            const handleCollapses = (collapses, index = 0) => {
                if (index >= collapses.length) return;
                const collapseId = collapses[index];
                if (collapseId) {
                    let collapseElement = $('#' + collapseId);
                    if (collapseElement.length) {
                        collapseElement.click();
                        setTimeout(() => {
                            handleCollapses(collapses, index + 1);
                        }, 100);
                    } else {
                        console.warn('Элемент с ID ' + collapseId + ' не найден.');
                        handleCollapses(collapses, index + 1);
                    }
                }
            };
            clickTabsSequentially(tabs);
            handleCollapses(collapses);
        },
        setupTabEventListeners: function () {
            // Обрабатываем элементы с data-bs-toggle="tab"
            const tabElements = document.querySelectorAll('[data-bs-toggle="tab"]');
            tabElements.forEach(tabElement => {
                tabElement.addEventListener('click', function (event) {
                    event.preventDefault();
                    const tabId = this.id;
                    if (tabId) {
                        const parentUl = this.closest('ul.nav.nav-tabs');
                        const url = new URL(window.location.href);
                        const searchParams = url.searchParams;
                        let tabArray = searchParams.getAll('tabs[]');
                        if (parentUl) {
                            parentUl.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
                                const siblingTabId = tab.id;
                                if (siblingTabId && tabArray.includes(siblingTabId)) {
                                    const index = tabArray.indexOf(siblingTabId);
                                    if (index > -1) {
                                        tabArray.splice(index, 1);
                                    }
                                }
                            });
                        }
                        if (!tabArray.includes(tabId)) {
                            tabArray.push(tabId);
                        }
                        searchParams.delete('tabs[]');
                        tabArray.forEach(tabValue => {
                            searchParams.append('tabs[]', tabValue);
                        });
                        const urlString = `${url.origin}${url.pathname}?${searchParams.toString().replace(/%5B%5D/g, '[]')}`;
                        window.history.pushState({}, '', urlString);
                    } else {
                        console.warn('Элемент вкладки не имеет ID.');
                    }
                });
            });
            // Обрабатываем элементы с data-bs-toggle="collapse"
            const collapseElements = document.querySelectorAll('[data-bs-toggle="collapse"]');
            collapseElements.forEach(collapseElement => {
                collapseElement.addEventListener('click', function (event) {
                    event.preventDefault();
                    const collapseId = this.id;
                    if (collapseId) {
                        const url = new URL(window.location.href);
                        const searchParams = url.searchParams;
                        let collapseArray = searchParams.getAll('collapse[]');
                        if (!collapseArray.includes(collapseId)) {
                            collapseArray.push(collapseId);
                        }
                        searchParams.delete('collapse[]');
                        collapseArray.forEach(collapseValue => {
                            searchParams.append('collapse[]', collapseValue);
                        });
                        const urlString = `${url.origin}${url.pathname}?${searchParams.toString().replace(/%5B%5D/g, '[]')}`;
                        window.history.pushState({}, '', urlString);
                    } else {
                        console.warn('Элемент аккордиона не имеет ID.');
                    }
                });
            });
        },
        preloader: preloader,
        summernoteParams: {},
        codeMirrorParams: {}
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
        ['view', ['fullscreen', 'codeview']]
    ]
};
// Параметры для CodeMirror (отдельная инициализация)
AppCore.codeMirrorParams = {
    theme: 'monokai',
    mode: 'htmlmixed',
    lineNumbers: true,
    lineWrapping: true,
    indentUnit: 4,
    tabSize: 4,
    autoCloseTags: false,
    firstLineNumber: 0
};