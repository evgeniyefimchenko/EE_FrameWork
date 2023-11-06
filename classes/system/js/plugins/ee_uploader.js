(function($) {
    $.fn.eeUploader = function() {
        // Внутренняя функция getFileIcon
        function getFileIcon(extension) {
            switch (extension) {
                case 'pdf': return '<i class="fas fa-file-pdf fa-10x"></i>';
                case 'doc': return '<i class="fa-solid fa-file-word fa-10x"></i>';
                case 'docx': return '<i class="fas fa-file-word fa-10x"></i>';
                case 'xls': return '<i class="fas fa-file-excel fa-10x"></i>';
                case 'xlsx': return '<i class="fas fa-file-excel fa-10x"></i>';
                case 'ppt': return '<i class="fas fa-file-powerpoint fa-10x"></i>';
                case 'pptx': return '<i class="fas fa-file-powerpoint fa-10x"></i>';
                case 'zip': return '<i class="fa-solid fa-file-zipper fa-10x"></i>';
                case 'rar': return '<i class="fa-solid fa-box-archive fa-10x"></i>';
                case 'tar': return '<i class="fa-solid fa-box-archive fa-10x"></i>';
                case 'gz': return '<i class="fas fa-file-archive fa-10x"></i>';
                case 'txt': return '<i class="fas fa-file-alt fa-10x"></i>';
                default: return '<i class="fas fa-file fa-10x"></i>';
            }
        }

        this.each(function() { // Ищем все поля для инициализации
            var $input = $(this);
            var allowedExtensions = $input.data('allowed-extensions').split(',');
            var $uploadButton = $('<span role="button" class="badge bg-secondary m-2">Нажми для загрузки</span>');
            var or_tag = '<small class="m-auto text-center text-muted w-100">ИЛИ</small>';
            var $uploadButton_url = $('<span role="button" class="badge bg-secondary m-2">Вставь URL</span>');
            var $preloadedFilesContainer = $('<div class="preloadedFiles p-3 border bg-light rounded"></div>');
            $input.after($preloadedFilesContainer, $uploadButton, or_tag, $uploadButton_url);
            $input.hide();
            $uploadButton.on('click', function() {
                $input.trigger('click');
            });           
            $uploadButton_url.on('click', function() {
                $('#uploadModal-' + $input.attr('id')).modal('show');
            });
            $('#add-file-by-url-' + $input.attr('id')).on('click', function() {
                var url = $('#file-url-input-' + $input.attr('id')).val();
                if (url) {
                    alert(url);
                }
            });
            $input.on('change', function() {
                var files = this.files;
                for (var i = 0; i < files.length; i++) {
                    var file = files[i];
                    var fileExtension = file.name.split('.').pop().toLowerCase();
                    if (allowedExtensions.length > 0 && allowedExtensions[0] !== "" && allowedExtensions.indexOf(fileExtension) === -1) {
                        alert(file.name + ' not supported!');
                        continue;
                    }
                    var fileContainer = $('<div class="fileItem d-flex flex-column align-items-start p-2"></div>');
                    var fileName = $('<div class="fileName mb-2"></div>').text(file.name);
                    fileContainer.append(fileName);
                    var actionIcons = $('<div class="actionIcons" role="button"></div>');
                    var deleteIcon = $('<i class="fas fa-trash actionIcon deleteIcon"></i>');
                    var editIcon = $('<i class="fas fa-edit actionIcon editIcon"></i>');
                    actionIcons.append(deleteIcon, editIcon);
                    fileContainer.append(actionIcons);
                    if (fileExtension === 'jpg' || fileExtension === 'jpeg' || fileExtension === 'png') {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            var image = $('<img class="m-auto" style="max-width: 190px; max-height: 190px;">').attr('src', e.target.result);
                            fileContainer.append(image);
                        };
                        reader.readAsDataURL(file);
                    } else {
                        var iconMarkup = getFileIcon(fileExtension);
                        var iconContainer = $('<div class="mt-2 w-100 text-center"></div>').html(iconMarkup);
                        fileContainer.append(iconContainer);
                    }
                    $preloadedFilesContainer.append(fileContainer);
                }
                $preloadedFilesContainer.sortable({
                    placeholder: "ui-state-highlight", // Класс для плейсхолдера
                    cursor: 'move', // Изменение курсора при перетаскивании
                    update: function(event, ui) {
                        // Здесь можно обработать событие после изменения порядка элементов,
                        // например, обновить скрытое поле формы с новым порядком
                    }
                }).disableSelection();                
            });
            $('#upload-content-' + $input.attr('id')).on('click', '.deleteIcon', function(event) {
                event.stopPropagation();
                $(this).closest('.fileItem').remove();
            });            
            $('#upload-content-' + $input.attr('id')).on('click', '.editIcon', function(event) {
                event.stopPropagation();
                alert('Редактирование файла');
            });            
        });
        return this;
    };

    $(document).ready(function() {
        $('input[data-ee_uploader="true"]').eeUploader();
    });
    
})(jQuery);
