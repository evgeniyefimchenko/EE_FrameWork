$(document).ready(function () {
    // Установка активной ссылки навигации
    setActiveNavLink('/admin/email_snippets');
    var codeMirrorEditor = CodeMirror.fromTextArea(document.getElementById("snippet_content"), AppCore.codeMirrorParams);
    var initialContent = $('#snippet_content').val();
    codeMirrorEditor.setValue(initialContent);
    codeMirrorEditor.on('change', function () {
        $('#snippet_content').val(codeMirrorEditor.getValue());
    });
    $('.insert-snippet').click(function () {
        let snippet = $(this).data('snippet');
        let cursor = codeMirrorEditor.getCursor();
        codeMirrorEditor.replaceRange(snippet, cursor);
    });
});

