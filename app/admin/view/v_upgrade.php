<?php if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
$subject = rawurlencode("Сообщение автору EE_FrameWork v." . ENV_VERSION_CORE);
$body = rawurlencode("Опишите какую именно помощь я могу Вам оказать?");
$to = rawurlencode("evgeniy@efimchenko.ru");
$сс = rawurlencode("hedgehogelez@yandex.ru");
/*href="mailto:evgeniy@efimchenko.ru"*/
 ?>
<!-- Страница связи с автором -->
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
				<h2 class="text-center">Это полностью бесплатное программное обеспечение.</h2>
				Но если Вам понадобится интеграция то вы можете связаться со мной по следующим контактам:
				<div class="card mt-3 pl-3">
					<a href="tel:+79208245362" target="_BLANK">Позвонить - <span class="badge badge-secondary">+7(920)824-53-62</span></a>
					<a href="//efimchenko.ru" target="_BLANK">Мой сайт - <span class="badge badge-secondary">efimchenko.ru</span></a>
					<a href='mailto:?subject=<?=$subject?>&body=<?=$body?>&cc=<?=$сс?>&to=<?=$to?>' target="_BLANK">Моя почта - <span class="badge badge-secondary">evgeniy@efimchenko.ru</span></a>
					<a href="tg://resolve?domain=@clean_code" target="_BLANK">Telegram - <span class="badge badge-secondary">@clean_code</span></a>
					<a href="https://wa.me/79208245362" target="_BLANK">WhatsApp - <span class="badge badge-secondary">+79208245362</span></a>
					<a href="viber://chat?number=79208245362" target="_BLANK">Viber - <span class="badge badge-secondary">+79208245362</span></a>
					<a href="skype:evgeniyefimchenko?chat" target="_BLANK">Skype - <span class="badge badge-secondary">evgeniyefimchenko</span></a>
					<a href="https://vk.com/id113807047" target="_BLANK">Вконтакте - <span class="badge badge-secondary">https://vk.com/id113807047</span></a>
					<a href="https://ok.ru/eugene.efimchenko" target="_BLANK">Одноклассники - <span class="badge badge-secondary">https://ok.ru/eugene.efimchenko</span></a>
					<a href="https://www.facebook.com/EvgeniyEfimchenko" target="_BLANK">FaceBook - <span class="badge badge-secondary">https://www.facebook.com/EvgeniyEfimchenko</span></a>
				</div>
            </div>
        </div>
    </div>
</div>