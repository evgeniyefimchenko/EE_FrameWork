/*Редактирование сущностей*/

$(document).ready(function () {
    setActiveNavLink('/admin/entities');
    initializeTinyMCE('#short_description-input', settingsShortDescription);
    initializeTinyMCE('#description-input', settingsLongDescription);
});
