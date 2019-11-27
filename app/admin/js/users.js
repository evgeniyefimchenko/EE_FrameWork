/*Страница пользователей*/
$(document).ready(function () {
    $('#users-item-menu').addClass('nav-item active');    
    $('#add_user').click(function () {
        window.location = "/admin/user_edit/id";
    });
	$('.delete_user').click(function(e){
		if (confirm("Удалить пользователя?")) {        
			$.ajax({
				type: 'POST',
				url: '/admin/delete_user/id/' + $(this).data('user_id'),
				dataType: 'json',
				beforeSend: function () {
					notify = actions.showNotification('Подождите данные обрабатываются.', 'primary');
				},
				success: function (data) {
					if (data.error !== 'no') {
						console.log('error', data);
						actions.showNotification('Ошибка обновления данных.', 'danger');
					} else {
						actions.showNotification('Данные пользователя обновлены.', 'primary');
						window.location = "/admin/users";
					}
				},
				error: function (xhr, ajaxOptions, thrownError) {
					console.log(xhr.status, xhr.responseText, thrownError, ajaxOptions);
				},
				complete: function (data) {
					notify.close();
				}
			});
		} else {
		  return false;
		}			
	});
    $("#users_table").dataTable({
        "aoColumnDefs": [{"aTargets": [8]
                , "bSortable": false}],
        language: {
            "processing": "Подождите...",
            "search": "Поиск:",
            "lengthMenu": "Показать _MENU_ записей",
            "info": "Записи с _START_ до _END_ из _TOTAL_ записей",
            "infoEmpty": "Записи с 0 до 0 из 0 записей",
            "infoFiltered": "(отфильтровано из _MAX_ записей)",
            "infoPostFix": "",
            "loadingRecords": "Загрузка записей...",
            "zeroRecords": "Записи отсутствуют.",
            "emptyTable": "В таблице отсутствуют данные",
            "paginate": {
                "first": "Первая",
                "previous": "Предыдущая",
                "next": "Следующая",
                "last": "Последняя"
            },
            "aria": {
                "sortAscending": ": активировать для сортировки столбца по возрастанию",
                "sortDescending": ": активировать для сортировки столбца по убыванию"
            }
        }
    });	
});