// Файл: app\index\js\docs.js

$(document).ready(function() {
    // Обработчик кликов для элементов меню
    $('.list-group-item').on('click', function() {
        $('.list-group-item').removeClass('active');
        $(this).addClass('active');

        const docName = $(this).data('doc');
        AppCore.sendAjaxRequest('/get_doc', { docName }, 'POST', 'html', renderDoc, errorDoc);
    });

    // Функция для обработки ошибок
    function errorDoc() {
        console.error('Ошибка при загрузке документации');
    }

    // Функция для рендеринга документации
    function renderDoc(data) {
        $('#docs-content').html(data);
    }
});
