/*Основной скрипт фронта*/
(function($){
	// Preloader
	jQuery(window).on('load', function() {
		$('.preloader').fadeOut();
	});
	// AJAX запрос на отправку формы
	$("#feedbackForm").on("submit", function (event) {
		event.preventDefault();
		let formData = {
			name: $("#name").val(),
			email: $("#email").val(),
			message: $("#message").val()
		};
		sendAjaxRequest('/feedback', formData, 'POST', 'json', function (response) {
			var feedbackModal = bootstrap.Modal.getInstance(document.getElementById('feedbackModal'));
			feedbackModal.hide();
			var successToast = new bootstrap.Toast(document.getElementById('successToast'));
			successToast.show();
		});
	});	
	// Закрытие мобильного меню при выборе пункта и обработка ссылки "Цены"
	$(document).ready(function () {
		const navLinks = $('.navbar-nav .nav-link'); // Получение всех ссылок навигации
		const navbarToggler = $('.navbar-toggler'); // Кнопка для мобильного меню
		const navbarCollapse = $('.navbar-collapse'); // Контейнер навигационного меню

		navLinks.on('click', function (e) {
			// Закрытие мобильного меню
			if (navbarToggler.is(':visible')) {
				navbarToggler.trigger('click'); // Закрыть меню при выборе пункта
			}

			// Обработка ссылки "Цены"
			if ($(this).hasClass('price-link')) {
				e.preventDefault(); // Предотвращаем стандартное поведение
				const targetSection = $('#contacts');
				$('html, body').animate({
					scrollTop: targetSection.offset().top
				}, 1000); // Плавная прокрутка к секции
				const collapseElement = $('#priceInfo');
				const bsCollapse = new bootstrap.Collapse(collapseElement, {
					toggle: true
				}); // Раскрытие блока с ценами
			}
		});
	});	
}(jQuery));

(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
m[i].l=1*new Date();
for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
(window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

ym(98596184, "init", {
	clickmap:true,
	trackLinks:true,
	accurateTrackBounce:true
});