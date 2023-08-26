/*Страница логов*/
$(document).ready(function () {
	
	$('#general_log_table, #logs_site_api').footable();
	
	$('[data-page-size]').on('click', function(e) {
		e.preventDefault();
		var newSize = $(this).data('pageSize');
		FooTable.get('#general_log_table, #logs_site_api').pageSize(newSize);
	});
	
	$('.btn.btn-default.dropdown-toggle').click(function() {
		if ($('.input-group-append').hasClass('open')) {
			$('ul.dropdown-menu.dropdown-menu-right').show();
		} else {
			$('ul.dropdown-menu.dropdown-menu-right').hide();
		}
	});
	
	$(document).mouseup(function (e) {
		var container = $('ul.dropdown-menu.dropdown-menu-right');
		if (container.has(e.target).length === 0){
			container.hide();
		}		
	});
		
});