/**
 * @file eeUploader.js
 * @description Плагин для загрузки файлов с поддержкой предпросмотра, редактирования и трансформаций изображений.
 */

(function ($) {
    /**
     * eeUploader плагин для загрузки файлов с поддержкой предпросмотра и трансформаций.
     * @function
     * @param {Object} options - Опции плагина (необязательный параметр).
     */
    $.fn.eeUploader = function (options) {
        /**
         * Получает HTML-код иконки для указанного расширения файла.
         * @param {string} extension - Расширение файла.
         * @returns {string} - HTML-код иконки.
         */
        function getFileIcon(extension) {
            switch (extension) {
                case 'pdf':
                    return '<i class="fas fa-file-pdf fa-10x"></i>';
                case 'doc':
                case 'docx':
                    return '<i class="fas fa-file-word fa-10x"></i>';
                case 'xls':
                case 'xlsx':
                    return '<i class="fas fa-file-excel fa-10x"></i>';
                case 'ppt':
                case 'pptx':
                    return '<i class="fas fa-file-powerpoint fa-10x"></i>';
                case 'zip':
                case 'rar':
                case 'tar':
                case 'gz':
                    return '<i class="fas fa-file-archive fa-10x"></i>';
                case 'txt':
                    return '<i class="fas fa-file-alt fa-10x"></i>';
                default:
                    return '<i class="fas fa-file fa-10x"></i>';
            }
        }

        // Настройки по умолчанию Не используются!
        var settings = $.extend({
            // Добавьте здесь настройки по умолчанию, если необходимо
        }, options);

        this.each(function () {
            var $input = $(this);
            var $form = $input.closest('form');
            var fileObjectList = [];
            var layout = $input.closest('.card').data('layout') || 'vertical';
            var isMultiple = $input.prop('multiple');
            var allowedExtensions = $input.data('allowed-extensions').split(',').map(function (item) {
                return item.trim().toLowerCase();
            });

            /**
             * Проверяет, разрешено ли указанное расширение файла
             * @param {string} fileExtension - Расширение файла
             * @returns {boolean} - true, если разрешено, иначе false
             */
            function isExtensionAllowed(fileExtension) {
                return allowedExtensions.length === 0 || allowedExtensions.includes(fileExtension);
            }

            /**
             * Создает контейнер для файла с предпросмотром и элементами управления
             * @param {string} fileName - Имя файла
             * @param {string} fileExtension - Расширение файла
             * @param {string} fileSrc - Источник файла (URL или DataURL)
             * @param {File} [fileObject] - Объект файла (если доступен)
             * @returns {jQuery} - jQuery-элемент контейнера файла
             */
            function createFileContainer(fileName, fileExtension, fileSrc, fileObject) {
                var uniqueID = generateUniqueID();
                var fileContainer = $('<div class="fileItem p-2"></div>').attr('data-unique-id', uniqueID);
                var fileNameDiv = $('<div class="fileName mb-2"></div>').text(fileName);
                var property_name = $input.attr('name').match(/\[([^[\]]*)\]/)[1];
                fileContainer.append(fileNameDiv);
                var data = {
                    unique_id: uniqueID,
                    file_name: fileName,
                    property_name: property_name
                };
                // Создаем скрытое поле с данными файла
                var dataFileInput = $('<input type="hidden" name="dataFiles[]" value=\'' + JSON.stringify(data) + '\'>');
                fileContainer.append(dataFileInput);
                if (fileObject) {
                    // Сохраняем объект файла и уникальный ID для последующей отправки
                    fileObject.uniqueID = uniqueID;
                    fileObjectList.push(fileObject);
                }
                if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp', 'ico'].includes(fileExtension)) {
                    var image = $('<img class="m-auto" />').attr('src', fileSrc);
                    fileContainer.append(image);
                    var actionIcons = $('<div class="actionIcons" role="button"></div>');
                    var deleteIcon = $('<i class="fas fa-trash actionIcon deleteIcon"></i>');
                    var editIcon = $('<i class="fas fa-edit actionIcon editIcon"></i>');
                    var rotateIcon = $('<i class="fas fa-rotate-right actionIcon rotateIcon"></i>');
                    var flipHIcon = $('<i class="fas fa-arrows-left-right actionIcon flipHIcon"></i>');
                    var flipVIcon = $('<i class="fas fa-arrows-up-down actionIcon flipVIcon"></i>');
                    actionIcons.append(deleteIcon, editIcon, rotateIcon, flipHIcon, flipVIcon);
                    fileContainer.append(actionIcons);
                } else {
                    var actionIcons = $('<div class="actionIcons" role="button"></div>');
                    var deleteIcon = $('<i class="fas fa-trash actionIcon deleteIcon"></i>');
                    var editIcon = $('<i class="fas fa-edit actionIcon editIcon"></i>');
                    actionIcons.append(deleteIcon, editIcon);
                    fileContainer.append(actionIcons);
                    var iconMarkup = getFileIcon(fileExtension);
                    var iconContainer = $('<div class="mt-2 w-100 text-center"></div>').html(iconMarkup);
                    fileContainer.append(iconContainer);
                }
                return fileContainer;
            }

            var $uploadButton = $('<span role="button" class="badge bg-secondary m-2 p-3">Загрузить</span>');
            var $uploadButton_url = $('<span role="button" class="badge bg-secondary m-2 p-3">URL</span>');
            var $preloadedFilesContainer = $('<div style="display: none;" class="preloadedFiles text-center p-1 border bg-light rounded no-select fileContainer ' + layout + '"></div>');
            $input.after($preloadedFilesContainer, $uploadButton, $uploadButton_url);
            $input.hide();

            /**
             * Обновляет порядок файлов (если требуется)
             */
            function updateFileOrder() {
                // Если необходимо, реализуйте обновление порядка файлов
            }

            /**
             * Обновляет главный файл (первый в списке)
             */
            function updateMainFile() {
                $preloadedFilesContainer.show();
                $preloadedFilesContainer.find('.fileItem').removeClass('main-file');
                $preloadedFilesContainer.find('.fileItem').first().addClass('main-file');
            }

            // Инициализация SortableJS для сортировки
            new Sortable($preloadedFilesContainer[0], {
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function (evt) {
                    updateFileOrder();
                    updateMainFile();
                }
            });

            $uploadButton.on('click', function () {
                $input.trigger('click');
            });

            $uploadButton_url.on('click', function () {
                $('#uploadModal-' + $input.attr('id')).modal('show');
            });

            $('#add-file-by-url-' + $input.attr('id')).on('click', function () {
                var url = $('#file-url-input-' + $input.attr('id')).val();
                if (url) {
                    var fileExtension = url.split('.').pop().toLowerCase();
                    if (!isExtensionAllowed(fileExtension)) {
                        alert('Файл с расширением .' + fileExtension + ' не поддерживается!');
                        return;
                    }

                    var fileName = url.substring(url.lastIndexOf('/') + 1);
                    if (!isMultiple) {
                        $preloadedFilesContainer.empty();
                    }

                    var fileContainer = createFileContainer(fileName, fileExtension, url);
                    $preloadedFilesContainer.append(fileContainer);
                    updateMainFile();
                    updateFileOrder();
                    $('#uploadModal-' + $input.attr('id')).modal('hide');
                    $('#file-url-input-' + $input.attr('id')).val('');
                } else {
                    alert('Пожалуйста, введите URL.');
                }
            });

            // Обработчик изменения файлового input
            $input.on('change', function () {
                if (!isMultiple) {
                    $preloadedFilesContainer.empty();
                    fileObjectList = [];
                }
                var files = this.files;
                for (var i = 0; i < files.length; i++) {
                    var file = files[i];
                    var fileExtension = file.name.split('.').pop().toLowerCase();

                    if (!isExtensionAllowed(fileExtension)) {
                        alert(file.name + ' не поддерживается!');
                        continue;
                    }

                    (function (file) {
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            // Создаем контейнер файла
                            var fileContainer = createFileContainer(file.name, fileExtension, e.target.result, file);
                            $preloadedFilesContainer.append(fileContainer);
                            updateMainFile();
                            updateFileOrder();
                        };
                        reader.readAsDataURL(file);
                    })(file);
                }
            });

            // Удаление элемента!!! Не удаляет предзагруженный файл из массива upload_file[] TODO
            $preloadedFilesContainer.on('click', '.deleteIcon', function (event) {
                event.stopPropagation();
                var $fileItem = $(this).closest('.fileItem');
                var uniqueID = $fileItem.attr('data-unique-id');
                // Удаляем файл из списка файлов
                fileObjectList = fileObjectList.filter(function (file) {
                    return file.uniqueID !== uniqueID;
                });
                $fileItem.fadeOut(300, function () {
                    $(this).remove();
                    updateMainFile();
                    updateFileOrder();
                });
            });

            // Редактирование параметров файла
            $preloadedFilesContainer.on('click', '.editIcon', function (event) {
                event.stopPropagation();
                var $fileItem = $(this).closest('.fileItem');
                var fileName = $fileItem.find('.fileName').text();
                var modalId = '#addFileParamsModal_' + $input.attr('id');
                // Заполняем поля в модальном окне значениями из выбранного файла
                $(modalId).find('#file_name_' + $input.attr('id')).val(fileName); // Название файла
                // Запишем данные о fileItem
                $(modalId).data('fileItem', $fileItem);
                // Открываем модальное окно
                $(modalId).modal('show');
            });

            // Обработка формы сохранения параметров файла
            $('#addFileParamsModal_' + $input.attr('id')).on('click', '#submit_' + $input.attr('id'), function (event) {
                event.preventDefault();
                var modalId = '#addFileParamsModal_' + $input.attr('id');
                var data = {};
                // Собираем все элементы с атрибутом name в модальном окне и их значения
                var firstElement = null;
                $(modalId).find('[name]').each(function (index, value) {
                    var id = $(this).attr('name');
                    var value = $(this).val();
                    data[id] = value;
                    // Сохраняем первый элемент
                    if (index === 0) {
                        firstElement = { id: id, value: value };
                    }
                });
                // Извлекаем сохранённый элемент .fileItem из data модального окна
                var $fileItem = $(modalId).data('fileItem');
                if ($fileItem) {
                    if (firstElement)
                        $fileItem.find('div.fileName').text(firstElement.value);
                    var dataFileInput = $fileItem.find('input[type="hidden"][name="dataFiles[]"]');
                    var existingData = {};
                    var dataFileValue = dataFileInput.val();
                    if (dataFileValue) {
                        try {
                            existingData = JSON.parse(dataFileValue);
                        } catch (e) {
                            existingData = {};
                        }
                    }
                    // Объединяем существующие данные с новыми данными, не перезаписывая ключ transformations
                    var newData = $.extend({}, existingData, data);
                    dataFileInput.val(JSON.stringify(newData));
                }
                // Удаляем ссылку на fileItem из data-атрибутов модального окна
                $(modalId).removeData('fileItem');
                // Закрываем модальное окно после сохранения
                $(modalId).modal('hide');
            });

            /**
             * Обновляет трансформации изображения (поворот, отражение).
             *
             * @param {jQuery} $image - jQuery-элемент изображения.
             */
            function updateImageTransform($image) {
                var rotation = $image.data('rotation') || 0;
                var isFlippedH = $image.data('flippedH') || false;
                var isFlippedV = $image.data('flippedV') || false;
                var scaleX = isFlippedH ? -1 : 1;
                var scaleY = isFlippedV ? -1 : 1;
                $image.css('transform', 'rotate(' + rotation + 'deg) scale(' + scaleX + ', ' + scaleY + ')');

                // Обновление скрытого поля с данными трансформации
                var $fileItem = $image.closest('.fileItem');
                var dataFileInput = $fileItem.find('input[type="hidden"][name="dataFiles[]"]');
                var dataFileValue = dataFileInput.val();
                var data = {};
                if (dataFileValue) {
                    try {
                        data = JSON.parse(dataFileValue);
                    } catch (e) {
                        data = {};
                    }
                }
                data.transformations = {
                    rotation: rotation,
                    flipH: isFlippedH,
                    flipV: isFlippedV
                };
                dataFileInput.val(JSON.stringify(data));
            }

            // Обработчик вращения изображения
            $preloadedFilesContainer.on('click', '.rotateIcon', function (event) {
                event.stopPropagation();
                var $fileItem = $(this).closest('.fileItem');
                var $image = $fileItem.find('img');
                var currentRotation = $image.data('rotation') || 0;
                currentRotation = (currentRotation + 90) % 360;
                $image.data('rotation', currentRotation);
                updateImageTransform($image);
            });

            // Обработчик горизонтального отражения изображения
            $preloadedFilesContainer.on('click', '.flipHIcon', function (event) {
                event.stopPropagation();
                var $fileItem = $(this).closest('.fileItem');
                var $image = $fileItem.find('img');
                var isFlippedH = $image.data('flippedH') || false;
                isFlippedH = !isFlippedH;
                $image.data('flippedH', isFlippedH);
                updateImageTransform($image);
            });

            // Обработчик вертикального отражения изображения
            $preloadedFilesContainer.on('click', '.flipVIcon', function (event) {
                event.stopPropagation();
                var $fileItem = $(this).closest('.fileItem');
                var $image = $fileItem.find('img');
                var isFlippedV = $image.data('flippedV') || false;
                isFlippedV = !isFlippedV;
                $image.data('flippedV', isFlippedV);
                updateImageTransform($image);
            });

            /**
             * Генерирует уникальный идентификатор.
             *
             * @returns {string} - Уникальный идентификатор.
             */
            function generateUniqueID() {
                return 'id_' + Math.random().toString(36).substr(2, 9);
            }

            // Привязываем обработчик отправки формы только к этой форме
            $form.on('submit', function (event) {
                event.preventDefault();
                var formData = new FormData(this);
                fileObjectList.forEach(function (file) {
                    formData.append('upload_file[' + file.uniqueID + ']', file);
                });
                sendAjaxRequest(
                    $form.attr('action'),
                    formData,
                    $form.attr('method') || 'POST',
                    'json',
                    function (response) {
                        // Обработка успешного ответа
                    },
                    function (jqXHR, textStatus, errorThrown) {
                        console.error(jqXHR, textStatus, errorThrown);
                        alert('В модуле ee_uploader возникла ошибка!');
                    }
                );
            });
        }); // Конец each

    }; // Конец $.fn.eeUploader

    // Автоматическая инициализация плагина
    $(document).ready(function () {
        $('input[data-ee_uploader="true"]').eeUploader();
    });
})(jQuery);
