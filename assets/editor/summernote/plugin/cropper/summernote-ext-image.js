(function (factory) {
    /* global define */
    if (typeof define === 'function' && define.amd) {
        // AMD. Register as an anonymous module.
        define(['jquery'], factory);
    } else if (typeof module === 'object' && module.exports) {
        // Node/CommonJS
        module.exports = factory(require('jquery'));
    } else {
        // Browser globals
        factory(window.jQuery);
    }
}(function ($) {
    // Добавление локализации для нескольких языков
    $.extend(true, $.summernote.lang, {
        'en-US': {
            imagePlugin: {
                tooltip: 'Insert Image' // Всплывающая подсказка для кнопки (английский)
            }
        },
        'ru-RU': {
            imagePlugin: {
                tooltip: 'Вставить изображение' // Всплывающая подсказка для кнопки (русский)
            }
        }
    });

    /**
     * @class plugin.imagePlugin
     */
    $.extend($.summernote.options, {
        imagePlugin: {
            icon: '<i class="fas fa-image"></i>', // Используем иконку из Font Awesome
            tooltip: 'Insert Image', // Всплывающая подсказка для кнопки (по умолчанию - английский)
            insertToBodySelector: '',
            id: ''
        }
    });

    $.extend($.summernote.plugins, {
        'imagePlugin': function (context) {
            var self = this,
                    ui = $.summernote.ui,
                    $note = context.layoutInfo.note,
                    $editor = context.layoutInfo.editor,
                    $editable = context.layoutInfo.editable,
                    options = context.options,
                    lang = context.options.lang; // Get language code

            // Use the localized strings or default to English
            var localized = $.summernote.lang[lang] && $.summernote.lang[lang].imagePlugin ? $.summernote.lang[lang].imagePlugin : $.summernote.lang['en-US'].imagePlugin;

            context.memo('button.imagePlugin', function () {
                // Создаем кнопку
                var button = ui.button({
                    contents: options.imagePlugin.icon, // Используем иконку из настроек плагина
                    tooltip: localized.tooltip, // Используем всплывающую подсказку из локализации
                    codeviewKeepButton: true, // Кнопка остается активной в режиме Codeview
                    click: function (e) {
                        context.invoke('cropper.show'); // Вызываем метод show плагина summernote-cropper
                        $('body').on('click', '.del-image', function () {
                            var image_id = $(this).attr('id');
                            $(this).closest('.image_wrap').remove();
                            $('.' + image_id).remove();
                        });
                        if (options.imagePlugin.id) { // Проверяем, что ID не пустой
                            $('#' + options.imagePlugin.id).on('summernote.media.delete', function () {
                                var image_id = $editable.data('target').getAttribute('class');
                                $('.image_wrap.' + image_id).remove();
                            });
                        }
                    }
                });
                return button.render(); // Возвращаем отрендеренную кнопку
            });
        }
    });
}));
