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
         * Получает HTML-код иконки для указанного расширения файла
         * @param {string} extension - Расширение файла
         * @returns {string} - HTML-код иконки
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

        /**
         * Преобразует строку в объект, добавляет параметры из переданного объекта и возвращает результат в виде строки JSON
         * Если строка пустая, возвращает объект с дополнительными данными
         * Если additionalData пустой или не объект, возвращает исходную строку или пустую строку
         * @param {string} jsonString - Входная строка, которая может содержать данные в формате JSON
         * @param {Object} additionalData - Объект с дополнительными параметрами, которые нужно добавить в JSON-объект
         * @returns {string} Возвращает строку в формате JSON или пустую строку, если входные данные некорректны
         */
        function mergeJsonStringWithObject(jsonString, additionalData) {
            if (typeof additionalData !== 'object' || additionalData === null || Object.keys(additionalData).length === 0) {
                return jsonString || '';
            }
            let data = {};
            if (jsonString) {
                try {
                    data = JSON.parse(jsonString);
                } catch (e) {
                    data = {};
                }
            }
            Object.assign(data, additionalData);
            return JSON.stringify(data);
        }

        function parseJsonString(jsonString) {
            if (!jsonString) {
                return {};
            }
            try {
                const parsed = JSON.parse(jsonString);
                return typeof parsed === 'object' && parsed !== null ? parsed : {};
            } catch (e) {
                return {};
            }
        }

        // Настройки по умолчанию Не используются!
        var settings = $.extend({
            // Добавьте здесь настройки по умолчанию, если необходимо
        }, options);

        this.each(function () {
            var $input = $(this);
            var inputName = $input.attr('name') || '';
            var propertyMatch = inputName.match(/\[([^[\]]*)\]/);
            var property_name = propertyMatch ? propertyMatch[1] : inputName;
            var fileObjectList = [];
            var $uploaderCard = $input.closest('.ee-uploader-card');
            var $hostForm = $input.closest('form');
            var layout = $uploaderCard.data('layout') || 'vertical';
            var isMultiple = $input.prop('multiple');
            var uploadLabel = $input.data('upload-label') || 'Upload';
            var pendingDeleteLabel = $input.data('pending-delete-label') || 'Marked for deletion';
            var undoDeleteLabel = $input.data('undo-delete-label') || 'Undo';
            var allowedExtensions = String($input.data('allowed-extensions') || '').split(',').map(function (item) {
                return item.trim().toLowerCase();
            }).filter(function (item) {
                return item !== '';
            });
            var $preloadedFilesData = $('#preloaded_' + $input.attr('id'));

            function markUploaderDirty() {
                $uploaderCard.find('input[name="ee_check_file[]"]').val('1');
                $hostForm.find('input[name="property_data_changed"]').val('1');
            }

            function writeDataFilePayload($fileItem, payload) {
                $fileItem.find('input[type="hidden"][name="ee_dataFiles[]"]').val(JSON.stringify(payload));
            }

            function readDataFilePayload($fileItem) {
                return parseJsonString($fileItem.find('input[type="hidden"][name="ee_dataFiles[]"]').val());
            }

            function setPendingDeleteState($fileItem, shouldDelete) {
                var payload = readDataFilePayload($fileItem);
                payload.update = true;
                payload.property_name = property_name;
                if (shouldDelete) {
                    payload.delete = true;
                } else {
                    delete payload.delete;
                }
                writeDataFilePayload($fileItem, payload);
                $fileItem.toggleClass('pending-delete', shouldDelete);

                var $toggleIcon = $fileItem.find('.deleteIcon, .restoreIcon').first();
                if (shouldDelete) {
                    if (!$fileItem.find('.pendingDeleteNotice').length) {
                        $('<div class="pendingDeleteNotice"></div>').text(pendingDeleteLabel).appendTo($fileItem);
                    }
                    $toggleIcon.removeClass('deleteIcon fa-trash').addClass('restoreIcon fa-rotate-left').attr('title', undoDeleteLabel);
                } else {
                    $fileItem.find('.pendingDeleteNotice').remove();
                    $toggleIcon.removeClass('restoreIcon fa-rotate-left').addClass('deleteIcon fa-trash').attr('title', '');
                }
            }
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
                var uniqueID = fileObject && fileObject.uniqueID ? fileObject.uniqueID : generateUniqueID();
                var fileContainer = $('<div class="fileItem"></div>').attr('data-unique-id', uniqueID);
                var fileNameDiv = $('<div class="fileName mb-2"></div>').text(fileName);
                fileContainer.append(fileNameDiv);
                var data = {
                    unique_id: uniqueID,
                    file_name: fileName,
                    property_name: property_name,
                    original_name: fileName
                };
                // Создаем скрытое поле с данными файла
                var dataFileInput = $('<input>', {
                    type: 'hidden',
                    name: 'ee_dataFiles[]'
                }).val(JSON.stringify(data));
                fileContainer.append(dataFileInput);
                if (fileObject) {
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
                if ($preloadedFilesData.length && !isMultiple) { // Есть предзагруженные файлы и поле не множественное
                    // Удалить все предзагруженный файлы
                        $preloadedFilesData.find('.deleteIcon').each(function () {
                            $(this).click();
                        });
                }
                return fileContainer;
            }

            var $uploadButton = $('<span role="button" class="badge bg-secondary m-2 p-3"></span>').text(uploadLabel);
            var $uploadButton_url = '';// $('<span role="button" class="badge bg-secondary m-2 p-3">URL</span>');
            var $preloadedFilesContainer = $('<div style="display: none;" class="preloadedFiles text-center p-1 border bg-light rounded no-select fileContainer ' + layout + '"></div>');
            $input.after($preloadedFilesContainer, $uploadButton, $uploadButton_url);
            $input.hide();

            /**
             * Обновляет порядок файлов (если требуется)
             */
            function updateFileOrder() {
                // Если необходимо, обновление порядка файлов
            }

            /**
             * Обновляет главный файл (первый в списке)
             */
            function updateMainFile() {
                var $visibleItems = $preloadedFilesContainer.find('.fileItem:visible');
                if (!$visibleItems.length) {
                    $preloadedFilesContainer.hide();
                    return;
                }
                $preloadedFilesContainer.show();
                $preloadedFilesContainer.find('.fileItem').removeClass('main-file');
                $visibleItems.first().addClass('main-file');
            }

            // Инициализация SortableJS для сортировки загруженных файлов
            new Sortable($preloadedFilesContainer[0], {
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function (evt) {
                    markUploaderDirty();
                    updateFileOrder();
                    updateMainFile();
                }
            });
            $uploadButton.on('click', function () {
                $input.trigger('click');
            });
            /*
            $uploadButton_url.on('click', function () {
                $('#uploadModal-' + $input.attr('id')).modal('show');
            });
             */
            // Для загрузки по URL требуется использовать текущий набор параметров ee_dataFiles с добавлением флага URL загрузки
            // Для загрузки на стороне сервера и преобразования файла если это необходимо TODO
            /*$('#add-file-by-url-' + $input.attr('id')).on('click', function () {
                var url = $('#file-url-input-' + $input.attr('id')).val();
                if (url) {

                } else {
                    alert('Пожалуйста, введите URL.');
                }
            });
               */
            // Обработчик изменения файлового input
            $input.on('change', function () {
                markUploaderDirty();
                if (!isMultiple) {
                    $preloadedFilesContainer.empty();
                    fileObjectList = [];
                }
                var files = this.files;
                for (var i = 0; i < files.length; i++) {
                    var uniqueID = generateUniqueID();
                    var file = files[i];
                    file.uniqueID = uniqueID;
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

            $preloadedFilesContainer.on('click', '.deleteIcon', function (event) {
                event.stopPropagation();
                markUploaderDirty();
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
                if ($fileItem.hasClass('pending-delete')) {
                    return;
                }
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
                markUploaderDirty();
                var modalId = '#addFileParamsModal_' + $input.attr('id');
                var data = {};
                // Собираем все элементы с атрибутом name в модальном окне и их значения
                var firstElement = null;
                $(modalId).find('[name]').each(function (index, value) {
                    var id = $(this).attr('name');
                    var value = $(this).val();
                    data[id] = value;
                    // Сохраняем первый элемент для записи original_name
                    if (index === 0) {
                        firstElement = { id: id, value: value };
                    }
                });
                // Извлекаем сохранённый элемент .fileItem из data модального окна
                var $fileItem = $(modalId).data('fileItem');
                if ($fileItem) {
                    if (firstElement)
                        $fileItem.find('div.fileName').text(firstElement.value);
                    var dataFileInput = $fileItem.find('input[type="hidden"][name="ee_dataFiles[]"]');
                    var dataFileValue = dataFileInput.val();
                    data.update = true;
                    data.property_name = property_name;
                    var newData = mergeJsonStringWithObject(dataFileValue, data);
                    dataFileInput.val(newData);
                }
                // Удаляем ссылку на fileItem из data-атрибутов модального окна
                $(modalId).removeData('fileItem');
                // Закрываем модальное окно после сохранения
                $(modalId).modal('hide');
            });

            /**
             * Обновляет трансформации изображения (поворот, отражение)
             * @param {jQuery} $image - jQuery-элемент изображения
             */
            function updateImageTransform($image) {
                var rotation = $image.data('rotation') || 0;
                var isFlippedH = $image.data('flippedH') || false;
                var isFlippedV = $image.data('flippedV') || false;
                var scaleX = isFlippedH ? -1 : 1;
                var scaleY = isFlippedV ? -1 : 1;
                var $fileItem = $image.closest('.fileItem');
                if ($fileItem.hasClass('pending-delete')) {
                    return;
                }
                $image.css('transform', 'rotate(' + rotation + 'deg) scale(' + scaleX + ', ' + scaleY + ')');
                var dataFileInput = $fileItem.find('input[type="hidden"][name="ee_dataFiles[]"]');
                var dataFileValue = dataFileInput.val();
                var transformations = {
                    update: true,
                    property_name: property_name,
                    transformations: {
                        rotation: rotation,
                        flipH: isFlippedH,
                        flipV: isFlippedV
                    }
                };
                var newData = mergeJsonStringWithObject(dataFileValue, transformations);
                dataFileInput.val(newData);
                markUploaderDirty();
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
             * Генерирует уникальный идентификатор
             * @returns {string} - Уникальный идентификатор
             */
            function generateUniqueID() {
                return 'id_' + Math.random().toString(36).substr(2, 9);
            }
            // События по предзагруженным файлам
            if ($preloadedFilesData.length) {
                // Инициализация SortableJS для сортировки предзагруженных файлов
                new Sortable($preloadedFilesData[0], {
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    onEnd: function (evt) {
                        markUploaderDirty();
                    }
                });         
                $preloadedFilesData.on('click', '.deleteIcon', function (event) {
                    event.stopPropagation();
                    markUploaderDirty();
                    var $fileItem = $(this).closest('.fileItem');
                    setPendingDeleteState($fileItem, true);
                });
                $preloadedFilesData.on('click', '.restoreIcon', function (event) {
                    event.stopPropagation();
                    markUploaderDirty();
                    var $fileItem = $(this).closest('.fileItem');
                    setPendingDeleteState($fileItem, false);
                });
                $preloadedFilesData.on('click', '.editIcon', function (event) {
                    event.stopPropagation();
                    var $fileItem = $(this).closest('.fileItem');
                    if ($fileItem.hasClass('pending-delete')) {
                        return;
                    }
                    var fileName = $fileItem.find('.fileName').text();
                    var modalId = '#addFileParamsModal_' + $input.attr('id');
                    $(modalId).find('#file_name_' + $input.attr('id')).val(fileName);
                    $(modalId).data('fileItem', $fileItem);
                    $(modalId).modal('show');
                });
            }
        }); // Конец each
    }; // Конец $.fn.eeUploader
    // Автоматическая инициализация плагина
    $(document).ready(function () {
        $('input[data-ee_uploader="true"]').eeUploader();
    });
})(jQuery);
