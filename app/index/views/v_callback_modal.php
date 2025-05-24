<?php
if (!ENV_SITE) {
	http_response_code(404); die;
}
?>
<!-- Модальное окно -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="feedbackModalLabel">Форма обратной связи</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="feedbackForm">
                    <div class="mb-3">
                        <label for="name" class="form-label">Как к вам обращаться</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Почта</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">Сообщение</label>
                        <textarea class="form-control" id="message" name="message"></textarea>
                    </div>
                    <button type="submit" id="modal_close" class="btn btn-primary">Отправить</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Всплывающее окно -->
<div class="toast-container position-fixed top-50 start-50 translate-middle p-3">
    <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto">Уведомление</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            <h4>Я скоро свяжусь с Вами!</h4>
        </div>
    </div>
</div>
