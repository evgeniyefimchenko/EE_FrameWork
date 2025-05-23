<?php
if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301));
$subject = rawurlencode("Сообщение автору EE_FrameWork v." . ENV_VERSION_CORE);
$body = rawurlencode("Опишите какую именно помощь я могу Вам оказать?");
$to = rawurlencode("evgeniy@efimchenko.com");
$сс = rawurlencode("hedgehogelez@yandex.ru");
?>
<!-- Страница связи с автором -->

<main>
    <div class="container mt-3">
        <div class="row">
            <div class="col-md-12">
                <h2 class="text-center">Это полностью бесплатное программное обеспечение.</h2>
                Но если Вам понадобится интеграция то вы можете связаться со мной по следующим контактам:
                <div class="card mt-3 p-3 w-75">
                    <a href="tel:+79208245362" target="_BLANK">Позвонить - <span class="float-end badge bg-secondary">+7(920)824-53-62</span></a>
                    <a href="//efimchenko.com" target="_BLANK">Мой сайт - <span class="float-end badge bg-secondary">efimchenko.com</span></a>
                    <a href='mailto:?subject=<?= $subject ?>&body=<?= $body ?>&cc=<?= $сс ?>&to=<?= $to ?>' target="_BLANK">Моя почта - <span class="float-end badge bg-secondary">evgeniy@efimchenko.com</span></a>
                    <a href="tg://resolve?domain=@clean_code" target="_BLANK">Telegram - <span class="float-end badge bg-secondary">@clean_code</span></a>
                    <a href="https://wa.me/79208245362" target="_BLANK">WhatsApp - <span class="float-end badge bg-secondary">+79208245362</span></a>
                    <a href="https://vk.com/efimchenko_ru" target="_BLANK">Вконтакте - <span class="float-end badge bg-secondary">https://vk.com/efimchenko_ru</span></a>
                </div>
            </div>
        </div>
    </div>
</main>
