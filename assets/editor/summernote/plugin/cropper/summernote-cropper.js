(function (factory) {
    /* global define */
    if (typeof define === 'function' && define.amd) {
        // AMD. Register as an anonymous module.
        define(['jquery', 'cropper'], factory);
    } else if (typeof module === 'object' && module.exports) {
        // Node/CommonJS
        module.exports = factory(require('jquery'), require('cropper'));
    } else {
        // Browser globals
        factory(window.jQuery, window.Cropper);
    }
}(function ($, Cropper) {

    // Добавление локализации для нескольких языков
    $.extend(true, $.summernote.lang, {
        'en-US': {
            cropper: {
                modalTitle: 'Crop Image',
                chooseImage: 'Choose an image',
                insertCroppedImage: 'Insert',
                cancel: 'Cancel',
                invalidFileType: 'Invalid file type. Must be an image.',
                fileReadError: 'Error reading the file.',
                cropperError: 'Error initializing Cropper.js: ',
                insertOriginalImage: 'Insert Original'
            }
        },
        'ru-RU': {
            cropper: {
                modalTitle: 'Обрезка изображения',
                chooseImage: 'Выберите изображение',
                insertCroppedImage: 'Вставить',
                cancel: 'Отмена',
                invalidFileType: 'Неверный тип файла. Должен быть изображением.',
                fileReadError: 'Ошибка чтения файла.',
                cropperError: 'Ошибка инициализации Cropper.js: ',
                insertOriginalImage: 'Вставить оригинал'
            }
        }
    });

    // Расширяем настройки Summernote, добавляя настройки для Cropper
    $.extend($.summernote.options, {
        cropper: {
            enabled: true, // Включен ли плагин Cropper (по умолчанию - включен)
            options: {// Настройки Cropper.js
                aspectRatio: 1 / 1, // Соотношение сторон (1:1 по умолчанию)
                viewMode: 1, // Режим отображения (см. документацию Cropper.js)
                autoCropArea: 1, // Автоматически обрезать всю область (по умолчанию - 1)
                responsive: true, // Адаптивность (включена по умолчанию)
                restore: false, // Не восстанавливать состояние после обрезки (по умолчанию - false)
                checkCrossOrigin: false, // Не проверять cross-origin (по умолчанию - false)
                checkOrientation: false, // Не проверять ориентацию (по умолчанию - false)
                zoomable: true, // Разрешить масштабирование (включено по умолчанию)
                movable: true, // Разрешить перемещение (включено по умолчанию)
                scalable: true, // Разрешить изменение масштаба (включено по умолчанию)
                rotatable: true, // Разрешить поворот (включено по умолчанию)
                cropBoxMovable: true, // Разрешить перемещение области обрезки (включено по умолчанию)
                cropBoxResizable: true, // Разрешить изменение размера области обрезки (включено по умолчанию)
                toggleDragModeOnDblclick: true // Переключать режим перетаскивания по двойному клику (включено по умолчанию)
            },
            insertButtonText: 'ADD', // Текст кнопки добавления (английский, будет переопределен локализацией)
            modalTitle: 'Crop Image' // Заголовок модального окна (английский, будет переопределен локализацией)
        }
    });

    // Расширяем плагины Summernote, добавляя плагин Cropper
    $.extend($.summernote.plugins, {
        'cropper': function (context) {
            var self = this,
                    ui = $.summernote.ui,
                    $editor = context.layoutInfo.editor,
                    options = context.options.cropper,
                    lang = context.options.lang; // Get language code from Summernote options

            // Use the localized strings or default to English
            var localized = $.summernote.lang[lang] && $.summernote.lang[lang].cropper ? $.summernote.lang[lang].cropper : $.summernote.lang['en-US'].cropper;

            // Инициализация плагина
            this.initialize = function () {
                // Создаем модальное окно
                this.$modal = ui.dialog({
                    title: localized.modalTitle, // Устанавливаем заголовок модального окна из локализации
                    body: this.getBodyTemplate(), // Устанавливаем тело модального окна
                    footer: this.getFooterTemplate() // Устанавливаем футер модального окна
                }).render().appendTo($('body')); // Добавляем модальное окно в body

                // Находим элементы внутри модального окна
                this.$image = this.$modal.find('.note-image-cropper'); // Находим элемент для изображения
                this.$input = this.$modal.find('.note-image-input'); // Находим элемент для загрузки файла
                this.$insertButton = this.$modal.find('.note-image-insert'); // Находим кнопку добавления
                this.$insertOriginalButton = this.$modal.find('.note-image-insert-original'); // Add original-image button
                this.$cancelButton = this.$modal.find('.note-image-cancel'); // Add cancel button
                this.$controls = this.$modal.find('.note-image-controls'); // Находим контейнер для кнопок управления
                this.$error = this.$modal.find('.note-image-error'); // Находим элемент для отображения ошибок

                // Скрываем кнопки управления до загрузки изображения
                this.$controls.hide();

                // Добавляем обработчики событий для кнопок
                this.$input.on('change', this.uploadImage.bind(this)); // Загрузка изображения
                this.$insertButton.on('click', this.insertCroppedImage.bind(this)); // Добавление обрезанного изображения
                this.$insertOriginalButton.on('click', this.insertOriginalImage.bind(this)); // Insert Original Image Button
                this.$cancelButton.on('click', this.cancel.bind(this)); // Add cancel button
            };

            // Уничтожение плагина (удаление модального окна)
            this.destroy = function () {
                this.$modal.remove();
            };

            // Шаблон тела модального окна
            this.getBodyTemplate = function () {
                return `
          <div class="form-group">
            <label>${localized.chooseImage}</label>
            <input class="note-image-input form-control-file" type="file" name="image" accept="image/*">
          </div>
          <div class="note-image-preview">
            <img src="" class="note-image-cropper" style="max-width: 100%; display: none;">
          </div>
          <div class="note-image-controls-wrapper">
            <div class="note-image-controls">
              <button type="button" class="btn btn-sm btn-secondary rotate-left" title="Rotate Left"><i class="fas fa-undo"></i></button>
              <button type="button" class="btn btn-sm btn-secondary rotate-right" title="Rotate Right"><i class="fas fa-redo"></i></button>
              <button type="button" class="btn btn-sm btn-secondary zoom-in" title="Zoom In"><i class="fas fa-search-plus"></i></button>
              <button type="button" class="btn btn-sm btn-secondary zoom-out" title="Zoom Out"><i class="fas fa-search-minus"></i></button>
              <button type="button" class="btn btn-sm btn-secondary reset" title="Reset"><i class="fas fa-sync-alt"></i></button>
            </div>
          </div>
          <div class="note-image-error text-danger"></div>
        `;
            };

            // Шаблон футера модального окна
            this.getFooterTemplate = function () {
                return `
          <button href="#" class="btn btn-primary note-image-insert" disabled>${localized.insertCroppedImage}</button>
          <button href="#" class="btn btn-primary note-image-insert-original" disabled>${localized.insertOriginalImage}</button>
          <button href="#" class="btn btn-light note-image-cancel" data-dismiss="modal">${localized.cancel}</button>
        `;
            };

            // Показать модальное окно
            this.show = function () {
                context.invoke('beforeCommand', 'cropper'); // Вызываем событие beforeCommand
                this.$input.val(''); // Очищаем поле загрузки файла
                this.$image.attr('src', '').hide(); // Скрываем изображение
                this.$controls.hide(); // Скрываем кнопки управления
                this.$error.text(''); // Очищаем сообщения об ошибках
                this.$insertButton.prop('disabled', true); // Отключаем кнопку добавления
                this.$insertOriginalButton.prop('disabled', true); // Enable "Insert Original" button
                this.$cancelButton.on('click', this.cancel.bind(this)); // Fix cancel button

                this.$modal.modal('show'); // Показываем модальное окно
                this.$modal.one('shown.bs.modal', () => {
                    this.$input.focus(); // Устанавливаем фокус на поле загрузки файла
                }).one('hidden.bs.modal', () => {
                    context.invoke('afterCommand', 'cropper'); // Вызываем событие afterCommand
                    this.destroyCropper(); // Уничтожаем Cropper
                });
            };

            // Скрыть модальное окно
            this.hide = function () {
                this.$modal.modal('hide');
            };

            // Загрузка изображения
            this.uploadImage = function (event) {
                const file = event.target.files[0]; // Получаем файл из события

                if (!file) {
                    return; // Если файл не выбран - выходим
                }

                if (!file.type.startsWith('image/')) {
                    this.showError(`${localized.invalidFileType}`); // Если тип файла не изображение - показываем ошибку
                    return;
                }

                const reader = new FileReader(); // Создаем FileReader

                reader.onload = (e) => {
                    this.$image.attr('src', e.target.result).show(); // Устанавливаем источник изображения и показываем его
                    this.$image.on('load', () => { // Добавляем обработчик события onload для изображения
                        this.initCropper(); // Инициализируем Cropper
                        this.$controls.show(); // Показываем кнопки управления
                        this.$insertButton.prop('disabled', false);
                        this.$insertOriginalButton.prop('disabled', false);
                    });
                };

                reader.onerror = (error) => {
                    this.showError(localized.fileReadError); // Если произошла ошибка при чтении файла - показываем ошибку
                    console.error('FileReader error:', error);
                };

                reader.readAsDataURL(file); // Читаем файл как Data URL
            };

            // Инициализация Cropper
            this.initCropper = function () {
                if (this.cropper) {
                    this.destroyCropper(); // Если Cropper уже инициализирован - уничтожаем его
                }

                try {
                    this.cropper = new window.Cropper(this.$image[0], options.options); // Создаем Cropper

                    // Add event handlers for the buttons (внутри initCropper, чтобы Cropper был инициализирован)
                    this.$modal.find('.rotate-left').on('click', this.rotateLeft.bind(this)); // Поворот влево
                    this.$modal.find('.rotate-right').on('click', this.rotateRight.bind(this)); // Поворот вправо
                    this.$modal.find('.zoom-in').on('click', this.zoomIn.bind(this)); // Увеличение
                    this.$modal.find('.zoom-out').on('click', this.zoomOut.bind(this)); // Уменьшение
                    this.$modal.find('.reset').on('click', this.reset.bind(this)); // Сброс
                } catch (error) {
                    console.error('Cropper.js error:', error);
                    this.showError(localized.cropperError + error); // Если произошла ошибка при инициализации Cropper.js - показываем ошибку
                }
            };

            // Уничтожение Cropper
            this.destroyCropper = function () {
                if (this.cropper) {
                    this.cropper.destroy(); // Уничтожаем Cropper
                    this.cropper = null; // Обнуляем переменную
                }
            };

            // Вставка обрезанного изображения
            this.insertCroppedImage = function () {
                if (!this.cropper) {
                    return; // Если Cropper не инициализирован - выходим
                }

                const croppedCanvas = this.cropper.getCroppedCanvas(); // Получаем холст с обрезанным изображением
                const croppedDataURL = croppedCanvas.toDataURL('image/jpeg'); // Получаем Data URL обрезанного изображения (в формате JPEG)

                context.invoke('editor.insertImage', croppedDataURL); // Вставляем изображение в редактор Summernote
                this.hide(); // Скрываем модальное окно
            };

            // Вставка оригинального изображения
            this.insertOriginalImage = function () {
                const originalImageSrc = this.$image.attr('src'); // Get original image
                context.invoke('editor.insertImage', originalImageSrc); // Inserts the original image.
                this.hide(); // Hide modal
            };

            // Function to handle cancel button
            this.cancel = function () {
                this.hide(); // Hide modal
            };

            // Показать сообщение об ошибке
            this.showError = function (message) {
                this.$error.text(message); // Устанавливаем текст ошибки
                this.$image.hide(); // Скрываем изображение
                this.$insertButton.prop('disabled', true); // Отключаем кнопку добавления
            };

            // Поворот изображения влево
            this.rotateLeft = function () {
                this.cropper.rotate(-90); // Поворачиваем изображение на -45 градусов
            };

            // Поворот изображения вправо
            this.rotateRight = function () {
                this.cropper.rotate(90); // Поворачиваем изображение на 45 градусов
            };

            // Увеличение масштаба
            this.zoomIn = function () {
                this.cropper.zoom(0.1); // Увеличиваем масштаб на 0.1
            };

            // Уменьшение масштаба
            this.zoomOut = function () {
                this.cropper.zoom(-0.1); // Уменьшаем масштаб на -0.1
            };

            // Сброс
            this.reset = function () {
                this.cropper.reset(); // Сбрасываем настройки Cropper
            };

            this.initialize(); // Инициализируем плагин
        }
    });
}));
