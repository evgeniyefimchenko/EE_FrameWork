<?php
$subject = rawurlencode("Сообщение автору EE_FrameWork v." . ENV_VERSION_CORE);
$body = rawurlencode("Опишите какую именно помощь я могу Вам оказать?");
$to = rawurlencode("evgeniy@efimchenko.ru");
$сс = rawurlencode("hedgehogelez@yandex.ru");
/*href="mailto:evgeniy@efimchenko.ru"*/
?>
<!-- Страница связи с автором -->

<div id="layoutSidenav_content">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h2 class="text-center">Это полностью бесплатное программное обеспечение.</h2>
                Но если Вам понадобится интеграция то вы можете связаться со мной по следующим контактам:
                <div class="card mt-3 pl-3 w-25">
                    <a href="tel:+79208245362" target="_BLANK">Позвонить - <span class="float-end badge bg-secondary">+7(920)824-53-62</span></a>
                    <a href="//efimchenko.ru" target="_BLANK">Мой сайт - <span class="float-end badge bg-secondary">efimchenko.com</span></a>
                    <a href='mailto:?subject=<?= $subject ?>&body=<?= $body ?>&cc=<?= $сс ?>&to=<?= $to ?>' target="_BLANK">Моя почта - <span class="float-end badge bg-secondary">evgeniy@efimchenko.ru</span></a>
                    <a href="tg://resolve?domain=@clean_code" target="_BLANK">Telegram - <span class="float-end badge bg-secondary">@clean_code</span></a>
                    <a href="https://wa.me/79208245362" target="_BLANK">WhatsApp - <span class="float-end badge bg-secondary">+79208245362</span></a>
                    <a href="viber://chat?number=79208245362" target="_BLANK">Viber - <span class="float-end badge bg-secondary">+79208245362</span></a>
                    <a href="skype:evgeniyefimchenko?chat" target="_BLANK">Skype - <span class="float-end badge bg-secondary">evgeniyefimchenko</span></a>
                    <a href="https://vk.com/efimchenko_ru" target="_BLANK">Вконтакте - <span class="float-end badge bg-secondary">https://vk.com/efimchenko_ru</span></a>
                </div>
            </div>
        </div>
    </div>
</div>
