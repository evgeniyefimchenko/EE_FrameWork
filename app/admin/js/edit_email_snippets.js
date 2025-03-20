/* Редактирование почтового сниппета */
$(document).ready(function () {
    setActiveNavLink('/admin/email_snippets');
    $('.insert-snippet').click(function () {
        let snippet = $(this).data('snippet');
        $('#snippet_content').summernote('insertText', snippet);
    });
    $('#snippet_content').summernote(AppCore.summernoteParams);
    AppCore.setupTabEventListeners();
    AppCore.handleTabsFromUrl();
});
