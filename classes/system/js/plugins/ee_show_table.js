$(document).ready(function () {
    function initEventHandlers(tableId) {
        let form = $(`#${tableId}_filters`);
        // 1. Инициализация элемента для сортировки
        $("[id^='" + tableId + "_column_']").each(function () {
            let field = $(this).attr('id').replace(tableId + "_column_", "").replace(/_(ASC|DESC)$/, "");
            let currentSort = $(this).data('current-sort');
            let sortInput = $("#" + tableId + "_filters input[name='sort_" + field + "']");
            if (!sortInput.length) {
                $("#" + tableId + "_filters").append('<input type="hidden" name="sort_' + field + '" value="' + currentSort + '">');
            }
        });
        // 2. Инициализация элемента для пагинации
        let currentPage = $("#" + tableId + "_content_tables .pagination .page-item.active .page-link").data('page');
        let pageInput = $("#" + tableId + "_filters input[name='page']");
        if (!pageInput.length) {
            if (currentPage == 'undefined')
                currentPage = 1;
            $("#" + tableId + "_filters").append('<input type="hidden" name="page" value="' + currentPage + '">');
        }
        // 3. Инициализация элемента для количества строк на странице
        let currentRowsPerPage = $("#" + tableId + "-rows-per-page").val();
        let rowsInput = $("#" + tableId + "_filters input[name='rows_per_page']");
        if (!rowsInput.length) {
            $("#" + tableId + "_filters").append('<input type="hidden" name="rows_per_page" value="' + currentRowsPerPage + '">');
        }
        // Обработчик события клика по ссылке сортировки
        $(document).off("click", "[id^='" + tableId + "_column_']").on("click", "[id^='" + tableId + "_column_']", function (e) {
            e.preventDefault();
            // Получение текущего значения сортировки из ID ссылки
            let field = $(this).attr('id').replace(tableId + "_column_", "").replace(/_(ASC|DESC)$/, "");
            let currentSort = $(this).data('current-sort');
            // Определение нового значения сортировки
            let newSort = (currentSort.toUpperCase() === "ASC") ? "DESC" : "ASC";
            // Добавляем (или изменяем) input в форме с новым значением сортировки
            let sortInput = $("#" + tableId + "_filters input[name='sort_" + field + "']");
            $('input[name^="sort_"]').not(sortInput).each(function() {
                var currentName = $(this).attr('name');
                $(this).attr('name', currentName + '_disabled');
            });
            if (sortInput.length) {
                sortInput.val(newSort).prop('disabled', false);
            } else {
                $("#" + tableId + "_filters").append('<input type="hidden" name="sort_' + field + '" value="' + newSort + '">');
            }
            $("#" + tableId + "_filters").trigger("submit");
        });
        // Обработчик события click на кнопке сброса
        $(document).off("click", "[id^='" + tableId + "_filters_reset']").on("click", '#' + tableId + '_filters_reset', function (e) {
            e.preventDefault();
            $(this).attr('disabled', 'disabled');
            let form = $('#' + $(this).attr('form'));
            form.find('input[type="text"], textarea, input[type="date"]').val('');
            form.find('input[type="checkbox"], input[type="radio"]').prop('checked', false);
            form.find('select').val(0);
            form.submit();
            $(this).removeAttr('disabled');
        });
        // Обработчик события клика по динамическим элементам пагинации
        $(document).off("click", "#" + tableId + "_content_tables .pagination .page-link").on("click", "#" + tableId + "_content_tables .pagination .page-link", function (e) {
            e.preventDefault();
            let pageNumber = $(this).data('page');
            let pageInput = $("#" + tableId + "_filters input[name='page']");
            if (pageInput.length) {
                pageInput.val(pageNumber);
            } else {
                $("#" + tableId + "_filters").append('<input type="hidden" name="page" value="' + pageNumber + '">');
            }
            $("#" + tableId + "_filters").trigger("submit");
        });
        // Обработчик события изменения количества строк на странице
        $(document).off("change", "#" + tableId + "-rows-per-page").on("change", "#" + tableId + "-rows-per-page", function () {
            let rowsPerPage = $(this).val();
            let rowsInput = $("#" + tableId + "_filters input[name='rows_per_page']");
            if (rowsInput.length) {
                rowsInput.val(rowsPerPage);
            } else {
                $("#" + tableId + "_filters").append('<input type="hidden" name="rows_per_page" value="' + rowsPerPage + '">');
            }
            $("#" + tableId + "_filters").trigger("submit");
        });
    }
    // Обработчик события отправки формы
    $(document).on('submit', "[id$='_filters']", function (e) {
        e.preventDefault();
        let form = $(this);
        let tableId = form.attr('id').replace('_filters', '');
        let callbackInput = $("#" + tableId + "_callback_function");
        if (!callbackInput.val()) {
            console.error(`Callback input with ID ${tableId}_callback_function not found or has no value.`);
            return;
        }
        let filter_data = form.serialize();
        $('#' + tableId + '_content_tables').css('opacity', '0.2');
        console.log('preloader OUT');
        $('#preloader').fadeIn(500);
        $.ajax({
            url: '/admin/' + callbackInput.val(),
            type: 'POST',
            data: filter_data,
            dataType: 'html',
            success: function (response) {
                $("#" + tableId + "_content_tables").replaceWith(response);
                // Добавление задержки перед повторной инициализацией обработчиков событий
                setTimeout(function () {
                    initEventHandlers(tableId); // переинициализация обработчиков
                }, 500); // Задержка в 500 миллисекунд (или 0.5 секунды)
                $('#preloader').fadeOut(500);
            },
            error: function (a, b, c) {
                console.error('Error:', a, b, c);
                $('#preloader').fadeIn(500);
            }
        });
    });
    $("[id$='_content_tables']").each(function () {
        let tableId = $(this).attr('data-tableID');
        initEventHandlers(tableId);
    });
});
