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

