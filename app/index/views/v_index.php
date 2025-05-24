<?php
if (!ENV_SITE) {
	http_response_code(404); die;
}
?>
<!-- JSON-LD Microdata for SEO -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Person",
  "name": "Евгений Ефимченко",
  "jobTitle": "PHP-программист",
  "worksFor": {
    "@type": "Organization",
    "name": "Freelance"
  },
  "url": "<?= ENV_URL_SITE ?>",
  "image": "<?= ENV_URL_SITE ?>/uploads/images/logo.png",
  "sameAs": [
    "https://join.skype.com/invite/ozdT8wZ1jWnp",
    "https://t.me/clean_code",
	"https://vk.com/id113807047",
	"https://github.com/evgeniyefimchenko"
  ],
  "description": "Опытный, честный PHP-программист, специализируюсь на разработке и кастомизации интернет-магазинов на CS-Cart.",
  "knowsAbout": ["CS-Cart", "SEO", "Модули", "Интеграция с CRM", "Оптимизация производительности"]
}
</script>
<div class="radial-gradients"> <!-- Контейнер для радиальных градиентов -->    
	<div class="blue-radial"></div> <!-- Первый синий радиальный градиент -->
    <div class="blue-radial"></div> <!-- Второй синий радиальный градиент -->
    <div class="pink-radial"></div> <!-- Розовый радиальный градиент -->
</div>

<nav id="nav" class="navbar navbar-expand-lg fixed-top"> <!-- Навигационная панель с фиксированным положением -->
    <div class="container"> <!-- Контейнер для выравнивания содержимого -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTogglerDemo01"
                aria-controls="navbarTogglerDemo01" aria-expanded="false" aria-label="Toggle navigation">
            <!-- Кнопка для переключения навигационного меню на мобильных устройствах -->
            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e8eaed">
                <path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z"/> <!-- Иконка для меню -->
            </svg>
        </button>
        <div class="collapse navbar-collapse" id="navbarTogglerDemo01"> <!-- Содержимое навигационной панели -->
            <a href="#hero"> <!-- Ссылка на секцию героя -->
                <img width="55" src="<?=ENV_URL_SITE?>/uploads/images/logo.png" alt="Логотип Евгения Ефимченко - PHP-программист CS-Cart">
            </a>

            <ul class="navbar-nav me-auto mb-2 mb-lg-0 col-12 col-md-auto mb-2 justify-content-center mb-md-0"> <!-- Список навигационных ссылок -->
                <li><a href="#experience" class="nav-link text-white" aria-label="Опыт">Опыт</a></li> <!-- Ссылка на секцию опыта -->
                <li><a href="#skills" class="nav-link text-white" aria-label="Специализация">Специализация</a></li> <!-- Ссылка на секцию специализации -->
                <li><a href="#services" class="nav-link text-white" aria-label="Услуги">Услуги</a></li> <!-- Ссылка на секцию услуг -->
                <li><a href="#individual-development" class="nav-link text-white" aria-label="Разработка">Разработка</a></li> <!-- Ссылка на секцию разработки -->
                <li><a href="#optimizations" class="nav-link text-white" aria-label="Оптимизация">Оптимизация</a></li> <!-- Ссылка на секцию оптимизации -->
                <li><a href="#integrations" class="nav-link text-white" aria-label="Интеграция">Интеграция</a></li> <!-- Ссылка на секцию интеграций -->
                <li><a href="#security" class="nav-link text-white" aria-label="Безопасность">Безопасность</a></li> <!-- Ссылка на секцию безопасности -->
                <li><a href="#support" class="nav-link text-white" aria-label="Поддержка">Поддержка</a></li> <!-- Ссылка на секцию поддержки -->
                <li><a href="#portfolio" class="nav-link text-white" aria-label="Портфолио">Портфолио</a></li> <!-- Ссылка на секцию портфолио -->
                <li><a href="#links" class="nav-link text-white" title="Пока не заполнена" aria-label="Ссылки">Ссылки</a></li> <!-- Ссылка на секцию ссылки -->
                <li><a href="#contacts" class="nav-link text-white price-link" aria-label="Цены">Цены</a></li>
                <li><a href="#contacts" class="nav-link text-white" title="Пока не заполнена" aria-label="Контакты">Контакты</a></li> <!-- Ссылка на секцию контакты -->
            </ul>
            <div class="text-end"> <!-- Контейнер для кнопок в правом углу навигационной панели -->
                <?=$top_panel?> <!-- Первая кнопка -->
            </div>
        </div>
    </div>
</nav>

<section id="hero">
    <div class="container px-3 px-md-5 pt-5"> <!-- Контейнер для содержимого секции -->
        <div class="row p-3 p-md-4"> <!-- Строка для размещения колонок -->
            <div class="col-md-7 d-flex justify-content-center flex-column"> <!-- Левая колонка с текстом -->
                <h1 class="fs-1 display-4 display-md-1 text-center">Профессиональный программист CS-Cart</h1> <!-- Заголовок секции -->
                <p class="pt-3 pb-3">Разработка и доработка платформы CS-Cart, создание индивидуальных модулей, оптимизация производительности.</p> <!-- Описание секции -->
                <button type="button" class="btn btn-primary w-50 w-md-25 mb-5 mb-md-0" id="callback_button" data-bs-toggle="modal" data-bs-target="#feedbackModal">Связаться</button> <!-- Кнопка для связи -->
            </div>
            <div class="col-md-5"> <!-- Правая колонка с изображением -->
                <img class="img-fluid rounded-3 object-fit-cover"
                     src="<?=ENV_URL_SITE?>/uploads/images/1OQ0JjsRujYMDQcGnM14I.webp"
                     alt="Разработка и улучшение платформы CS-Cart, создание уникальных модулей, оптимизация производительности." width="401" height="515"> <!-- Изображение -->
            </div>
        </div>
    </div>
</section>

<section id="experience"> <!-- Секция опыта -->
    <div class="container p-5 d-lg-none"> <!-- Контейнер с отступами, который будет скрыт на больших экранах -->
        <div class="row"> <!-- Строка для размещения колонок -->
            <div class="col-2"> <!-- Первая колонка для иконки или графического элемента -->
                <svg height="100%" viewBox="0 0 63 353" fill="none"
                     xmlns="http://www.w3.org/2000/svg">
                    <line x1="14" y1="4.37114e-08" x2="14" y2="353" stroke="url(#paint0_linear_340_28)"
                          stroke-width="2"/>
                    <path d="M63 152L13 152" stroke="url(#paint1_linear_340_28)" stroke-width="2"/>
                    <path d="M63 279L13 279" stroke="url(#paint2_linear_340_28)" stroke-width="2"/>
                    <path d="M63 25L13 25" stroke="url(#paint3_linear_340_28)" stroke-width="2"/>
                    <rect x="0.5" y="267.5" width="24" height="24" rx="3.5" fill="url(#paint4_linear_340_28)"
                          stroke="#4A2C85"/>
                    <path d="M12.176 285.096C11.3653 285.096 10.5973 284.968 9.872 284.712C9.15733 284.445 8.58133 284.093 8.144 283.656L8.704 282.744C9.06667 283.117 9.552 283.427 10.16 283.672C10.7787 283.917 11.4507 284.04 12.176 284.04C13.104 284.04 13.8133 283.837 14.304 283.432C14.8053 283.027 15.056 282.483 15.056 281.8C15.056 281.117 14.8107 280.573 14.32 280.168C13.84 279.763 13.0773 279.56 12.032 279.56H11.232V278.712L14.688 274.376L14.848 274.824H8.608V273.8H15.808V274.616L12.352 278.952L11.792 278.584H12.224C13.568 278.584 14.5707 278.883 15.232 279.48C15.904 280.077 16.24 280.845 16.24 281.784C16.24 282.413 16.0907 282.979 15.792 283.48C15.4933 283.981 15.04 284.376 14.432 284.664C13.8347 284.952 13.0827 285.096 12.176 285.096Z"
                          fill="white"/>
                    <rect x="0.5" y="12.5" width="24" height="24" rx="3.5" fill="url(#paint5_linear_340_28)"
                          stroke="#4A2C85"/>
                    <path d="M12.784 30V19.28L13.296 19.824H10.144V18.8H13.936V30H12.784Z" fill="white"/>
                    <rect x="0.5" y="139.5" width="24" height="24" rx="3.5" fill="url(#paint6_linear_340_28)"
                          stroke="#4A2C85"/>
                    <path d="M8.624 157V156.184L13.28 151.624C13.7067 151.208 14.0267 150.845 14.24 150.536C14.4533 150.216 14.5973 149.917 14.672 149.64C14.7467 149.363 14.784 149.101 14.784 148.856C14.784 148.205 14.56 147.693 14.112 147.32C13.6747 146.947 13.024 146.76 12.16 146.76C11.4987 146.76 10.912 146.861 10.4 147.064C9.89867 147.267 9.46667 147.581 9.104 148.008L8.288 147.304C8.72533 146.792 9.28533 146.397 9.968 146.12C10.6507 145.843 11.4133 145.704 12.256 145.704C13.0133 145.704 13.6693 145.827 14.224 146.072C14.7787 146.307 15.2053 146.653 15.504 147.112C15.8133 147.571 15.968 148.109 15.968 148.728C15.968 149.091 15.9147 149.448 15.808 149.8C15.712 150.152 15.5307 150.525 15.264 150.92C15.008 151.304 14.6293 151.741 14.128 152.232L9.856 156.424L9.536 155.976H16.48V157H8.624Z"
                          fill="white"/>
                    <defs>
                        <linearGradient id="paint0_linear_340_28" x1="12.5" y1="-2.40534e-06" x2="12.5" y2="353"
                                        gradientUnits="userSpaceOnUse">
                            <stop offset="0.1" stop-color="#3C136D"/>
                            <stop offset="0.9" stop-color="#26136C"/>
                        </linearGradient>
                        <linearGradient id="paint1_linear_340_28" x1="63" y1="151" x2="13" y2="151"
                                        gradientUnits="userSpaceOnUse">
                            <stop offset="0.1" stop-color="#3C136D"/>
                            <stop offset="0.9" stop-color="#26136C"/>
                        </linearGradient>
                        <linearGradient id="paint2_linear_340_28" x1="63" y1="278" x2="13" y2="278"
                                        gradientUnits="userSpaceOnUse">
                            <stop offset="0.1" stop-color="#3C136D"/>
                            <stop offset="0.9" stop-color="#26136C"/>
                        </linearGradient>
                        <linearGradient id="paint3_linear_340_28" x1="63" y1="24" x2="13" y2="24"
                                        gradientUnits="userSpaceOnUse">
                            <stop offset="0.1" stop-color="#3C136D"/>
                            <stop offset="0.9" stop-color="#26136C"/>
                        </linearGradient>
                        <linearGradient id="paint4_linear_340_28" x1="0" y1="279.5" x2="25" y2="279.5"
                                        gradientUnits="userSpaceOnUse">
                            <stop offset="0.1" stop-color="#3C136D"/>
                            <stop offset="0.9" stop-color="#26136C"/>
                        </linearGradient>
                        <linearGradient id="paint5_linear_340_28" x1="0" y1="24.5" x2="25" y2="24.5"
                                        gradientUnits="userSpaceOnUse">
                            <stop offset="0.1" stop-color="#3C136D"/>
                            <stop offset="0.9" stop-color="#26136C"/>
                        </linearGradient>
                        <linearGradient id="paint6_linear_340_28" x1="0" y1="151.5" x2="25" y2="151.5"
                                        gradientUnits="userSpaceOnUse">
                            <stop offset="0.1" stop-color="#3C136D"/>
                            <stop offset="0.9" stop-color="#26136C"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            <div class="col-9 ms"> <!-- Основная колонка с опытом, занимает 9 из 12 возможных колонок -->
                <div class="col-12 text-center"> <!-- Внутренняя колонка для заголовка и текста с центрированием -->
                    <h3 class="fs-3 pb-3">Опыт разработки и доработки CS-Cart</h3> <!-- Заголовок третьего уровня с отступами снизу -->
                    <p>Опыт работы с платформой CS-Cart более 7 лет, реализация множества проектов различной сложности.</p> <!-- Описание опыта -->
                </div>
                <div class="col-12 text-center"> <!-- Вторая внутренняя колонка для заголовка и текста -->
                    <h3 class="fs-3 pb-3">Реализация сложных задач</h3> <!-- Заголовок третьего уровня -->
                    <p>Успешный опыт решения сложных задач, интеграции с внешними сервисами, оптимизации производительности.</p> <!-- Описание реализации задач -->
                </div>
                <div class="col-12 text-center"> <!-- Третья внутренняя колонка для заголовка и текста -->
                    <h3 class="fs-3 pb-3">Знание всех ключевых функций и возможностей</h3> <!-- Заголовок третьего уровня -->
                    <p>Глубокое понимание архитектуры CS-Cart, функций, модулей, API и возможностей платформы.</p> <!-- Описание знаний о CS-Cart -->
                </div>
            </div>
        </div>
    </div>

    <div class="container p-5 d-none d-lg-block"> <!-- Контейнер с отступами, который будет виден только на больших экранах -->
        <h2 class="fs-2 py-5 text-center">Опыт работы с CS-Cart</h2> <!-- Заголовок второго уровня с отступами -->
        <div class="row"> <!-- Строка для размещения колонок -->
            <div class="col-md-6 text-center"> <!-- Первая колонка для описания опыта на больших экранах -->
                <h3 class="fs-3 pb-3">Опыт разработки и доработки CS-Cart</h3> <!-- Заголовок третьего уровня -->
                <p>Опыт работы с платформой CS-Cart более 5 лет, реализация множества проектов различной сложности.</p> <!-- Описание опыта -->
            </div>
            <div class="col-md-6 text-center"> <!-- Вторая колонка для описания опыта на больших экранах -->
                <h3 class="fs-3 pb-3">Реализация сложных задач</h3> <!-- Заголовок третьего уровня -->
                <p>Успешный опыт решения сложных задач, интеграции с внешними сервисами, оптимизации производительности.</p> <!-- Описание реализации задач -->
            </div>
        </div>
        <div class="py-5 row"> <!-- Новая строка с отступами для дальнейшего контента -->
            <svg width="1036" height="126" viewBox="0 0 1036 126" fill="none"
                 xmlns="http://www.w3.org/2000/svg">
                <line y1="64" x2="1036" y2="64" stroke="url(#paint0_linear_335_21)" stroke-width="2"/>
                <line x1="252" y1="4.37114e-08" x2="252" y2="65" stroke="url(#paint1_linear_335_21)" stroke-width="2"/>
                <line x1="514" y1="126" x2="514" y2="65" stroke="url(#paint2_linear_335_21)" stroke-width="2"/>
                <line x1="779" y1="4.37114e-08" x2="779" y2="65" stroke="url(#paint3_linear_335_21)" stroke-width="2"/>
                <rect x="232.5" y="43.5" width="39" height="39" rx="5.5" fill="url(#paint4_linear_335_21)"
                      stroke="#4A2C85"/>
                <rect x="758.5" y="43.5" width="39" height="39" rx="5.5" fill="url(#paint5_linear_335_21)"
                      stroke="#4A2C85"/>
                <rect x="495.5" y="43.5" width="39" height="39" rx="5.5" fill="url(#paint6_linear_335_21)"
                      stroke="#4A2C85"/>
                <path d="M253.524 72V54.58L254.356 55.464H249.234V53.8H255.396V72H253.524Z" fill="white"/>
                <path d="M778.786 73.156C777.469 73.156 776.221 72.948 775.042 72.532C773.881 72.0987 772.945 71.5267 772.234 70.816L773.144 69.334C773.733 69.9407 774.522 70.4433 775.51 70.842C776.515 71.2407 777.607 71.44 778.786 71.44C780.294 71.44 781.447 71.1107 782.244 70.452C783.059 69.7933 783.466 68.9093 783.466 67.8C783.466 66.6907 783.067 65.8067 782.27 65.148C781.49 64.4893 780.251 64.16 778.552 64.16H777.252V62.782L782.868 55.736L783.128 56.464H772.988V54.8H784.688V56.126L779.072 63.172L778.162 62.574H778.864C781.048 62.574 782.677 63.0593 783.752 64.03C784.844 65.0007 785.39 66.2487 785.39 67.774C785.39 68.7967 785.147 69.7153 784.662 70.53C784.177 71.3447 783.44 71.986 782.452 72.454C781.481 72.922 780.259 73.156 778.786 73.156Z"
                      fill="white"/>
                <path d="M509.014 73V71.674L516.58 64.264C517.273 63.588 517.793 62.9987 518.14 62.496C518.487 61.976 518.721 61.4907 518.842 61.04C518.963 60.5893 519.024 60.1647 519.024 59.766C519.024 58.7087 518.66 57.8767 517.932 57.27C517.221 56.6633 516.164 56.36 514.76 56.36C513.685 56.36 512.732 56.5247 511.9 56.854C511.085 57.1833 510.383 57.6947 509.794 58.388L508.468 57.244C509.179 56.412 510.089 55.7707 511.198 55.32C512.307 54.8693 513.547 54.644 514.916 54.644C516.147 54.644 517.213 54.8433 518.114 55.242C519.015 55.6233 519.709 56.1867 520.194 56.932C520.697 57.6773 520.948 58.5527 520.948 59.558C520.948 60.1473 520.861 60.728 520.688 61.3C520.532 61.872 520.237 62.4787 519.804 63.12C519.388 63.744 518.773 64.4547 517.958 65.252L511.016 72.064L510.496 71.336H521.78V73H509.014Z"
                      fill="white"/>
                <defs>
                    <linearGradient id="paint0_linear_335_21" x1="-9.76911e-06" y1="65.5" x2="1036" y2="65.5"
                                    gradientUnits="userSpaceOnUse">
                        <stop offset="0.1" stop-color="#3C136D"/>
                        <stop offset="0.9" stop-color="#26136C"/>
                    </linearGradient>
                    <linearGradient id="paint1_linear_335_21" x1="250.5" y1="-2.18557e-08" x2="250.5" y2="65"
                                    gradientUnits="userSpaceOnUse">
                        <stop offset="0.1" stop-color="#3C136D"/>
                        <stop offset="0.9" stop-color="#26136C"/>
                    </linearGradient>
                    <linearGradient id="paint2_linear_335_21" x1="515.5" y1="65" x2="515.5" y2="126"
                                    gradientUnits="userSpaceOnUse">
                        <stop offset="0.1" stop-color="#3C136D"/>
                        <stop offset="0.9" stop-color="#26136C"/>
                    </linearGradient>
                    <linearGradient id="paint3_linear_335_21" x1="777.5" y1="-2.18557e-08" x2="777.5" y2="65"
                                    gradientUnits="userSpaceOnUse">
                        <stop offset="0.1" stop-color="#3C136D"/>
                        <stop offset="0.9" stop-color="#26136C"/>
                    </linearGradient>
                    <linearGradient id="paint4_linear_335_21" x1="232" y1="63" x2="272" y2="63"
                                    gradientUnits="userSpaceOnUse">
                        <stop offset="0.1" stop-color="#3C136D"/>
                        <stop offset="0.9" stop-color="#26136C"/>
                    </linearGradient>
                    <linearGradient id="paint5_linear_335_21" x1="758" y1="63" x2="798" y2="63"
                                    gradientUnits="userSpaceOnUse">
                        <stop offset="0.1" stop-color="#3C136D"/>
                        <stop offset="0.9" stop-color="#26136C"/>
                    </linearGradient>
                    <linearGradient id="paint6_linear_335_21" x1="495" y1="63" x2="535" y2="63"
                                    gradientUnits="userSpaceOnUse">
                        <stop offset="0.1" stop-color="#3C136D"/>
                        <stop offset="0.9" stop-color="#26136C"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>
        <div class="row"> <!-- Строка для размещения колонок с информацией о ключевых функциях -->
            <div class="col-md-4"></div> <!-- Пустая колонка для центрирования содержимого -->
            <div class="col-md-4 text-center"> <!-- Центрированная колонка для заголовка и текста -->
                <h3 class="fs-3 pb-3">Знание всех ключевых функций и возможностей</h3> <!-- Заголовок третьего уровня -->
                <p>Глубокое понимание архитектуры CS-Cart, функций, модулей, API и возможностей платформы.</p> <!-- Описание знаний о CS-Cart -->
            </div>
            <div class="col-md-4"></div> <!-- Пустая колонка для центрирования содержимого -->
        </div>
    </div>
</section>

<section id="skills"> <!-- Секция для представления навыков и специализации -->
    <div class="container p-3 p-md-5"> <!-- Контейнер с отступами для лучшей организации контента -->
        <div class="row"> <!-- Строка для размещения заголовка и блоков с информацией -->
            <div class="col-12 mb-4"> <!-- Колонка для заголовка секции -->
                <h2 class="fs-2 text-center">Специализация и экспертиза</h2> <!-- Заголовок второго уровня для названия секции -->
            </div>
            <div class="col-md-4 mb-4"> <!-- Первая колонка с описанием навыков -->
                <h3 class="fs-3 py-2">Разработка модулей</h3> <!-- Заголовок третьего уровня для темы -->
                <p>Разработка и интеграция новых модулей, расширение функционала CS-Cart для решения конкретных задач бизнеса.</p> <!-- Описание навыка -->
            </div>
            <div class="col-md-4 mb-4"> <!-- Вторая колонка с описанием навыков -->
                <h3 class="fs-3 py-2">Доработка платформы</h3> <!-- Заголовок третьего уровня для темы -->
                <p>Изменение существующих функций, доработка шаблонов, настройка и оптимизация CS-Cart для максимальной эффективности.</p> <!-- Описание навыка -->
            </div>
            <div class="col-md-4 mb-4"> <!-- Третья колонка с описанием навыков -->
                <h3 class="fs-3 py-2">Интеграция с внешними сервисами</h3> <!-- Заголовок третьего уровня для темы -->
                <p>Интеграция CS-Cart с различными платежными системами, системами доставки, CRM и другими сервисами.</p> <!-- Описание навыка -->
            </div>
        </div>
    </div>
</section>


<section id="services"> <!-- Секция для представления услуг по доработке платформы -->
    <div class="container p-3 p-md-5"> <!-- Контейнер с отступами для лучшей организации контента -->
        <div class="row row-gap-5"> <!-- Строка с промежутками между элементами -->
            <div class="col-12 mb-4"> <!-- Колонка для заголовка секции -->
                <h2 class="fs-2 text-center">Услуги по доработке платформы</h2> <!-- Заголовок второго уровня для названия секции -->
            </div>
            <div class="col-md-6 mb-4"> <!-- Первая колонка для услуги -->
                <div class="row align-items-start"> <!-- Строка для выравнивания элементов -->
                    <div class="col-auto fs-2 text-center rounded-3 purple-block">1</div> <!-- Номер услуги с фоном -->
                    <div class="col"> <!-- Колонка для заголовка и описания услуги -->
                        <h3 class="fs-3">Изменение шаблонов</h3> <!-- Заголовок третьего уровня для услуги -->
                        <p>Настройка дизайна и стиля сайта, адаптация под требования бренда.</p> <!-- Описание услуги -->
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4"> <!-- Вторая колонка для услуги -->
                <div class="row align-items-start"> <!-- Строка для выравнивания элементов -->
                    <div class="col-auto fs-2 text-center rounded-3 purple-block">2</div> <!-- Номер услуги с фоном -->
                    <div class="col"> <!-- Колонка для заголовка и описания услуги -->
                        <h3 class="fs-3">Доработка функций</h3> <!-- Заголовок третьего уровня для услуги -->
                        <p>Добавление новых функций и возможностей в соответствии с задачами бизнеса.</p> <!-- Описание услуги -->
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4"> <!-- Третья колонка для услуги -->
                <div class="row align-items-start"> <!-- Строка для выравнивания элементов -->
                    <div class="col-auto fs-2 text-center rounded-3 purple-block">3</div> <!-- Номер услуги с фоном -->
                    <div class="col"> <!-- Колонка для заголовка и описания услуги -->
                        <h3 class="fs-3">Исправление ошибок</h3> <!-- Заголовок третьего уровня для услуги -->
                        <p>Проведение диагностики и исправления ошибок в функционале платформы.</p> <!-- Описание услуги -->
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4"> <!-- Четвертая колонка для услуги -->
                <div class="row align-items-start"> <!-- Строка для выравнивания элементов -->
                    <div class="col-auto fs-2 text-center rounded-3 purple-block">4</div> <!-- Номер услуги с фоном -->
                    <div class="col"> <!-- Колонка для заголовка и описания услуги -->
                        <h3 class="fs-3">Оптимизация производительности</h3> <!-- Заголовок третьего уровня для услуги -->
                        <p>Улучшение скорости загрузки сайта, устранение узких мест в коде.</p> <!-- Описание услуги -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="individual-development"> <!-- Секция для разработки индивидуальных модулей -->
    <div class="container p-3 p-md-5"> <!-- Контейнер с отступами для лучшей организации контента -->
        <div class="row"> <!-- Строка для размещения изображения и описания -->
            <div class="col-md-5 mb-4 mb-md-0"> <!-- Колонка для изображения -->
                <img class="img-fluid rounded-3 object-fit-cover ratio ratio-4x3"
                     src="<?=ENV_URL_SITE?>/uploads/images/CKHPx7qvHtlQjfcbp56G1.webp"
                     alt="Разработка индивидуальных модулей для интеграции, управления, маркетинга и безопасности." width="421" height="541">
            </div>
            <div class="col-md-7 d-flex justify-content-center flex-column"> <!-- Колонка для текста, центрированного вертикально -->
                <div class="row g-3"> <!-- Строка для размещения заголовка и модулей -->
                    <div class="col-12"> <!-- Колонка для заголовка секции -->
                        <h2 class="fs-2 pb-4">Разработка<br>индивидуальных<br>модулей</h2> <!-- Заголовок с переносами -->
                    </div>
                    <div class="col-md-6"> <!-- Первая колонка для описания модуля -->
                        <div class="p-3 rounded-3 purple-block"> <!-- Блок с фоном для модуля -->
                            <h3 class="fs-3 pb-2">Модули интеграции</h3> <!-- Заголовок третьего уровня для модуля -->
                            <p>Создание модулей для интеграции с внешними системами и сервисами.</p> <!-- Описание модуля -->
                        </div>
                    </div>
                    <div class="col-md-6"> <!-- Вторая колонка для описания модуля -->
                        <div class="p-3 rounded-3 purple-block"> <!-- Блок с фоном для модуля -->
                            <h3 class="fs-3 pb-2">Модули управления</h3> <!-- Заголовок третьего уровня для модуля -->
                            <p>Разработка модулей для управления контентом, заказами, клиентами.</p> <!-- Описание модуля -->
                        </div>
                    </div>
                    <div class="col-md-6"> <!-- Третья колонка для описания модуля -->
                        <div class="p-3 rounded-3 purple-block"> <!-- Блок с фоном для модуля -->
                            <h3 class="fs-3 pb-2">Модули маркетинга</h3> <!-- Заголовок третьего уровня для модуля -->
                            <p>Создание модулей для реализации маркетинговых кампаний и аналитики.</p> <!-- Описание модуля -->
                        </div>
                    </div>
                    <div class="col-md-6"> <!-- Четвертая колонка для описания модуля -->
                        <div class="p-3 rounded-3 purple-block"> <!-- Блок с фоном для модуля -->
                            <h3 class="fs-3 pb-2">Модули безопасности</h3> <!-- Заголовок третьего уровня для модуля -->
                            <p>Разработка модулей для усиления безопасности сайта и защиты данных.</p> <!-- Описание модуля -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="optimizations"> <!-- Начало секции для оптимизации производительности -->
    <div class="container p-3 p-md-5"> <!-- Контейнер для содержимого с отступами -->
        <div class="row"> <!-- Начало ряда для сетки -->
            <div class="col-md-5 mb-4 mb-md-0"> <!-- Колонка с изображением, на мобильных устройствах занимает 5/12 ширины -->
                <img class="img-fluid rounded-3 object-fit-cover"
                     style="aspect-ratio: 2/3"
                     src="<?=ENV_URL_SITE?>/uploads/images/4V-Ki91JxBYOPQXWHyBGT.webp"
                     alt="Оптимизация производительности: анализ кода, оптимизация базы данных, кэширование и компрессия файлов." width="421" height="632">
            </div>
            <div class="col-md-7 d-flex flex-column"> <!-- Колонка для текста и описаний, занимает 7/12 ширины -->
                <h2 class="fs-2 pb-4">Оптимизация<br>производительности</h2> <!-- Заголовок секции с отступом снизу -->
                <div class="row g-3"> <!-- Начало ряда для списка оптимизаций с отступами между элементами -->
                    <div class="col-12"> <!-- Колонка для первого пункта оптимизации -->
                        <div class="row d-flex align-items-center"> <!-- Ряд с выравниванием элементов по центру -->
                            <div class="col-auto"> <!-- Колонка для SVG-графики -->
                                <svg width="100" height="150">
                                    <defs>
                                        <linearGradient id="gradient1" gradientTransform="rotate(121)">
                                            <stop offset="10%" stop-color="#3c136dff"/>
                                            <stop offset="90%" stop-color="#26136cff"/>
                                        </linearGradient>
                                    </defs>
                                    <path d="M 0 126 L 45 144 L 90 126 L 90 0 L 45 18 L 0 0 Z"
                                          fill="url(#gradient1)"
                                          stroke="#4a2c85ff"
                                          stroke-width="1">
                                    </path>
                                    <text x="46" y="75" fill="#FFFFFF" font-size="32" text-anchor="middle"
                                          alignment-baseline="middle">1
                                    </text>
                                </svg>
                            </div>
                            <div class="col"> <!-- Колонка для текста описания -->
                                <h3 class="fs-3">Анализ кода</h3> <!-- Заголовок пункта оптимизации -->
                                <p>Проведение анализа кода для выявления узких мест и оптимизации работы сайта.</p> <!-- Описание -->
                            </div>
                        </div>
                    </div>
                    <!-- Повторение для следующих пунктов оптимизации с аналогичной структурой -->
                    <div class="col-12"> <!-- Колонка для второго пункта оптимизации -->
                        <div class="row d-flex align-items-center">
                            <div class="col-auto">
                                <svg width="100" height="150">
                                    <defs>
                                        <linearGradient id="gradient1" gradientTransform="rotate(121)">
                                            <stop offset="10%" stop-color="#3c136dff"/>
                                            <stop offset="90%" stop-color="#26136cff"/>
                                        </linearGradient>
                                    </defs>
                                    <path d="M 0 126 L 45 144 L 90 126 L 90 0 L 45 18 L 0 0 Z"
                                          fill="url(#gradient1)"
                                          stroke="#4a2c85ff"
                                          stroke-width="1">
                                    </path>
                                    <text x="46" y="75" fill="#FFFFFF" font-size="32" text-anchor="middle"
                                          alignment-baseline="middle">2
                                    </text>
                                </svg>
                            </div>
                            <div class="col">
                                <h3 class="fs-3">Оптимизация базы данных</h3>
                                <p>Настройка индексов, оптимизация запросов к базе данных для ускорения обработки
                                    информации.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12"> <!-- Колонка для третьего пункта оптимизации -->
                        <div class="row d-flex align-items-center">
                            <div class="col-auto">
                                <svg width="100" height="150">
                                    <defs>
                                        <linearGradient id="gradient1" gradientTransform="rotate(121)">
                                            <stop offset="10%" stop-color="#3c136dff"/>
                                            <stop offset="90%" stop-color="#26136cff"/>
                                        </linearGradient>
                                    </defs>
                                    <path d="M 0 126 L 45 144 L 90 126 L 90 0 L 45 18 L 0 0 Z"
                                          fill="url(#gradient1)"
                                          stroke="#4a2c85ff"
                                          stroke-width="1">
                                    </path>
                                    <text x="46" y="75" fill="#FFFFFF" font-size="32" text-anchor="middle"
                                          alignment-baseline="middle">3
                                    </text>
                                </svg>
                            </div>
                            <div class="col">
                                <h3 class="fs-3">Кэширование данных</h3>
                                <p>Использование кэширования для уменьшения нагрузки на сервер и ускорения загрузки
                                    страниц.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12"> <!-- Колонка для четвертого пункта оптимизации -->
                        <div class="row d-flex align-items-center">
                            <div class="col-auto">
                                <svg width="100" height="150">
                                    <defs>
                                        <linearGradient id="gradient1" gradientTransform="rotate(121)">
                                            <stop offset="10%" stop-color="#3c136dff"/>
                                            <stop offset="90%" stop-color="#26136cff"/>
                                        </linearGradient>
                                    </defs>
                                    <path d="M 0 126 L 45 144 L 90 126 L 90 0 L 45 18 L 0 0 Z"
                                          fill="url(#gradient1)"
                                          stroke="#4a2c85ff"
                                          stroke-width="1">
                                    </path>
                                    <text x="46" y="75" fill="#FFFFFF" font-size="32" text-anchor="middle"
                                          alignment-baseline="middle">4
                                    </text>
                                </svg>
                            </div>
                            <div class="col">
                                <h3 class="fs-3">Компрессия файлов</h3>
                                <p>Сжатие файлов HTML, CSS и JS для уменьшения объема передаваемых данных.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section> <!-- Конец секции для оптимизации производительности -->

<section id="integrations">
    <div class="container p-3 p-md-5">
        <div class="row">
            <div class="col-md-12">
                <!-- Заголовок раздела, описывающий интеграцию с внешними сервисами -->
                <h2 class="fs-2 mb-4 text-center">Интеграция с внешними сервисами</h2>
                <!-- Таблица для отображения интеграций -->
                <table class="table table-borderless">
                    <thead>
                    <tr>
                        <!-- Заголовок для столбца с названием сервиса -->
                        <th class="p-3 text-white" style="background: rgba(255, 255, 255, .05);">Сервис</th>
                        <!-- Заголовок для столбца с описанием сервиса -->
                        <th class="p-3 text-white border-start border-secondary"
                            style="background: rgba(255, 255, 255, .05);">Описание
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <!-- Ячейка с названием сервиса: Платежные системы -->
                        <td class="p-3 bg-transparent text-white">Платежные системы</td>
                        <!-- Ячейка с описанием сервиса -->
                        <td class="p-3 bg-transparent text-white border-start border-secondary">Интеграция с различными
                            платежными системами для
                            удобства оплаты заказов.
                        </td>
                    </tr>
                    <tr>
                        <!-- Ячейка с названием сервиса: Системы доставки -->
                        <td class="p-3 text-white" style="background: rgba(255, 255, 255, .05);">Системы доставки</td>
                        <!-- Ячейка с описанием сервиса -->
                        <td class="p-3 text-white border-start border-secondary"
                            style="background: rgba(255, 255, 255, .05);">Интеграция с системами
                            доставки для отслеживания посылок и управления доставкой.
                        </td>
                    </tr>
                    <tr>
                        <!-- Ячейка с названием сервиса: CRM системы -->
                        <td class="p-3 bg-transparent text-white">CRM системы</td>
                        <!-- Ячейка с описанием сервиса -->
                        <td class="p-3 bg-transparent text-white border-start border-secondary">Интеграция с CRM
                            системами для управления клиентами и
                            заказами.
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>


<section id="security">
    <div class="container p-3 p-md-4">
        <div class="row align-items-center">
            <!-- Колонка для изображения -->
            <div class="col-md-5 d-flex justify-content-end">
                <img class="rounded-3 object-fit-cover" style="aspect-ratio: 2/1; max-width: 90%;"
                     src="<?=ENV_URL_SITE?>/uploads/images/kx-Z-1261oicq27hQMuhk.webp"
                     alt="Интеграция с внешними сервисами: платежные системы, системы доставки, CRM системы." width="397" height="198"> <!-- Изображение для блока безопасности -->
            </div>
            <!-- Колонка для текста и информации о безопасности -->
            <div class="col-md-7 p-4">
                <h2 class="fs-2 py-4">Обеспечение<br>безопасности</h2> <!-- Заголовок секции -->

                <!-- Блок с основными мерами безопасности -->
                <details class="py-3">
                    <summary>
                        <b class="ps-2">Основные меры безопасности</b> <!-- Заголовок для раскрывающегося списка -->
                    </summary>
                    <ul class="border-start border-secondary lh-lg">
                        <li>Защита от SQL-инъекций</li> <!-- Элемент списка -->
                        <li>Защита от XSS-атак</li> <!-- Элемент списка -->
                        <li>Защита от CSRF-атак</li> <!-- Элемент списка -->
                        <li>Регулярные обновления</li> <!-- Элемент списка -->
                        <li>Шифрование данных</li> <!-- Элемент списка -->
                    </ul>
                </details>

                <!-- Блок с дополнительными мерами безопасности -->
                <details class="py-3">
                    <summary>
                        <b class="ps-2">Дополнительные меры</b> <!-- Заголовок для раскрывающегося списка -->
                    </summary>
                    <ul class="border-start border-secondary lh-lg">
                        <li>Двухфакторная аутентификация</li> <!-- Элемент списка -->
                        <li>Брандмауэр</li> <!-- Элемент списка -->
                        <li>Антивирусное программное обеспечение</li> <!-- Элемент списка -->
                    </ul>
                </details>

                <!-- Блок с информацией о регулярном мониторинге безопасности -->
                <details class="py-3">
                    <summary>
                        <b class="ps-2">Регулярный мониторинг</b> <!-- Заголовок для раскрывающегося списка -->
                    </summary>
                    <ul class="border-start border-secondary lh-lg list-unstyled ps-3 mt-3">
                        <li>Регулярный мониторинг безопасности сайта для своевременного выявления и устранения
                            угроз. <!-- Элемент списка -->
                        </li>
                    </ul>
                </details>
            </div>
        </div>
    </div>
</section>


<section id="support">
    <!-- Основной контейнер для раздела поддержки -->
    <div class="container p-4 p-md-5">
        <!-- Ряд для размещения элементов поддержки -->
        <div class="row row-gap-4">
            <div class="col-12">
                <!-- Заголовок раздела "Техническая поддержка и консультации" -->
                <h2 class="fs-2 text-center">Техническая поддержка и консультации</h2>
            </div>
            <div class="col-md-3">
			<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="45" height="45">
				<path d="M0 0 C5.28 0 10.56 0 16 0 C16 3.63 16 7.26 16 11 C14.1128125 11.12375 14.1128125 11.12375 12.1875 11.25 C8.69859456 11.6225603 8.69859456 11.6225603 6.1875 14.0625 C5.795625 14.701875 5.40375 15.34125 5 16 C4.01 16 3.02 16 2 16 C2 14.35 2 12.7 2 11 C1.01 11.495 1.01 11.495 0 12 C0.103125 13.258125 0.20625 14.51625 0.3125 15.8125 C0.328125 18.03125 0.328125 18.03125 0 20 C-2.4375 21.8125 -2.4375 21.8125 -5 23 C-5.33 23.99 -5.66 24.98 -6 26 C-4.081875 25.938125 -4.081875 25.938125 -2.125 25.875 C0.0546875 25.8046875 0.0546875 25.8046875 2 26 C6.55800339 30.55800339 6.8485424 37.81353946 7 44 C6 45 6 45 2.67358398 45.11352539 C1.19774444 45.11336629 -0.27810069 45.10767314 -1.75390625 45.09765625 C-2.52935287 45.0962413 -3.3047995 45.09482635 -4.10374451 45.09336853 C-6.59003494 45.08775345 -9.0762366 45.07519846 -11.5625 45.0625 C-13.24413933 45.05748694 -14.92578006 45.0529237 -16.60742188 45.04882812 C-20.73831566 45.03777913 -24.86914276 45.02050445 -29 45 C-29.03917647 43.42858838 -29.06685812 41.85688808 -29.08984375 40.28515625 C-29.106521 39.40996338 -29.12319824 38.53477051 -29.14038086 37.63305664 C-28.93062399 33.69874727 -27.87390825 28.87390825 -25 26 C-23.48071962 25.92820036 -21.95832518 25.91607993 -20.4375 25.9375 C-19.61121094 25.94652344 -18.78492188 25.95554687 -17.93359375 25.96484375 C-16.97646484 25.98224609 -16.97646484 25.98224609 -16 26 C-16.77537109 25.18208984 -16.77537109 25.18208984 -17.56640625 24.34765625 C-22.80674493 18.3484427 -22.80674493 18.3484427 -23.34765625 14.57421875 C-22.88471522 9.74176417 -21.51617995 5.51617995 -18 2 C-16.06791163 1.82169835 -14.12728628 1.73206583 -12.1875 1.6875 C-11.12917969 1.65011719 -10.07085938 1.61273438 -8.98046875 1.57421875 C-5.37015662 2.08997763 -4.26215289 3.22297454 -2 6 C-1.34 4.02 -0.68 2.04 0 0 Z " fill="#100809" transform="translate(29,0)"/>
				<path d="M0 0 C5.28 0 10.56 0 16 0 C16 3.63 16 7.26 16 11 C14.1128125 11.12375 14.1128125 11.12375 12.1875 11.25 C8.69859456 11.6225603 8.69859456 11.6225603 6.1875 14.0625 C5.795625 14.701875 5.40375 15.34125 5 16 C4.01 16 3.02 16 2 16 C2 14.35 2 12.7 2 11 C1.01 10.67 0.02 10.34 -1 10 C-1.66 10.66 -2.32 11.32 -3 12 C-3 10.35 -3 8.7 -3 7 C-3.99 6.67 -4.98 6.34 -6 6 C-6 5.34 -6 4.68 -6 4 C-7.97986082 4.11347619 -9.95891598 4.2410935 -11.9375 4.375 C-13.03964844 4.44460937 -14.14179688 4.51421875 -15.27734375 4.5859375 C-16.17582031 4.72257813 -17.07429688 4.85921875 -18 5 C-19.4783226 7.95664519 -19.06032783 10.74229737 -19 14 C-19.66 14 -20.32 14 -21 14 C-19.97407374 17.07777878 -19.24530756 17.84450474 -17 20 C-15 23 -15 23 -15 27 C-17.97 27.33 -20.94 27.66 -24 28 C-24.33 30.64 -24.66 33.28 -25 36 C-25.33 36 -25.66 36 -26 36 C-26.495 38.475 -26.495 38.475 -27 41 C-22.71 41 -18.42 41 -14 41 C-14.66 39.68 -15.32 38.36 -16 37 C-15.625 34.25 -15.625 34.25 -15 32 C-14.01 32 -13.02 32 -12 32 C-12 33.32 -12 34.64 -12 36 C-11.34 36 -10.68 36 -10 36 C-10 34.68 -10 33.36 -10 32 C-8.515 32.495 -8.515 32.495 -7 33 C-6.79913557 36.71599191 -6.8480563 37.77208445 -9 41 C-4.38 41 0.24 41 5 41 C5 39.02 5 37.04 5 35 C5.33 35 5.66 35 6 35 C6.19412181 36.47831221 6.38012131 37.95769316 6.5625 39.4375 C6.71912109 40.67306641 6.71912109 40.67306641 6.87890625 41.93359375 C6.93884766 42.95646484 6.93884766 42.95646484 7 44 C6 45 6 45 2.67358398 45.11352539 C1.19774444 45.11336629 -0.27810069 45.10767314 -1.75390625 45.09765625 C-2.52935287 45.0962413 -3.3047995 45.09482635 -4.10374451 45.09336853 C-6.59003494 45.08775345 -9.0762366 45.07519846 -11.5625 45.0625 C-13.24413933 45.05748694 -14.92578006 45.0529237 -16.60742188 45.04882812 C-20.73831566 45.03777913 -24.86914276 45.02050445 -29 45 C-29.03917647 43.42858838 -29.06685812 41.85688808 -29.08984375 40.28515625 C-29.106521 39.40996338 -29.12319824 38.53477051 -29.14038086 37.63305664 C-28.93062399 33.69874727 -27.87390825 28.87390825 -25 26 C-23.48071962 25.92820036 -21.95832518 25.91607993 -20.4375 25.9375 C-19.61121094 25.94652344 -18.78492188 25.95554687 -17.93359375 25.96484375 C-16.97646484 25.98224609 -16.97646484 25.98224609 -16 26 C-16.77537109 25.18208984 -16.77537109 25.18208984 -17.56640625 24.34765625 C-22.80674493 18.3484427 -22.80674493 18.3484427 -23.34765625 14.57421875 C-22.88471522 9.74176417 -21.51617995 5.51617995 -18 2 C-16.06791163 1.82169835 -14.12728628 1.73206583 -12.1875 1.6875 C-11.12917969 1.65011719 -10.07085938 1.61273438 -8.98046875 1.57421875 C-5.37015662 2.08997763 -4.26215289 3.22297454 -2 6 C-1.34 4.02 -0.68 2.04 0 0 Z " fill="#0B0E0B" transform="translate(29,0)"/>
				<path d="M0 0 C8.91 0 17.82 0 27 0 C27 4.29 27 8.58 27 13 C23.37 13 19.74 13 16 13 C16.33 10.36 16.66 7.72 17 5 C16.34 4.67 15.68 4.34 15 4 C15 5.32 15 6.64 15 8 C14.34 8 13.68 8 13 8 C13 6.68 13 5.36 13 4 C12.01 4 11.02 4 10 4 C10.33 6.97 10.66 9.94 11 13 C6.71 13 2.42 13 -2 13 C-2 11.02 -2 9.04 -2 7 C-1.67 7 -1.34 7 -1 7 C-0.67 4.69 -0.34 2.38 0 0 Z " fill="#DDE3EA" transform="translate(4,28)"/>
				<path d="M0 0 C0.99 0 1.98 0 3 0 C3 1.32 3 2.64 3 4 C3.66 4 4.32 4 5 4 C5 2.68 5 1.36 5 0 C5.99 0.33 6.98 0.66 8 1 C8.20086443 4.71599191 8.1519437 5.77208445 6 9 C10.62 9 15.24 9 20 9 C20 7.02 20 5.04 20 3 C20.33 3 20.66 3 21 3 C21.19412181 4.47831221 21.38012131 5.95769316 21.5625 7.4375 C21.71912109 8.67306641 21.71912109 8.67306641 21.87890625 9.93359375 C21.91886719 10.61550781 21.95882812 11.29742187 22 12 C21 13 21 13 17.67358398 13.11352539 C16.19774444 13.11336629 14.72189931 13.10767314 13.24609375 13.09765625 C12.47064713 13.0962413 11.6952005 13.09482635 10.89625549 13.09336853 C8.40996506 13.08775345 5.9237634 13.07519846 3.4375 13.0625 C1.75586067 13.05748694 0.07421994 13.0529237 -1.60742188 13.04882812 C-5.73831566 13.03777913 -9.86914276 13.02050445 -14 13 C-14 9.7 -14 6.4 -14 3 C-12.68 2.67 -11.36 2.34 -10 2 C-10.66 4.31 -11.32 6.62 -12 9 C-7.71 9 -3.42 9 1 9 C0.34 7.68 -0.32 6.36 -1 5 C-0.625 2.25 -0.625 2.25 0 0 Z " fill="#46576D" transform="translate(14,32)"/>
				<path d="M0 0 C1.6260287 0.06000106 3.25101549 0.1487885 4.875 0.25 C6.23238281 0.31960938 6.23238281 0.31960938 7.6171875 0.390625 C10 1 10 1 11.3203125 3.046875 C11.54460938 3.69140625 11.76890625 4.3359375 12 5 C12.66 5 13.32 5 14 5 C14 5.99 14 6.98 14 8 C13.34 8 12.68 8 12 8 C12 8.66 12 9.32 12 10 C10.35 10.33 8.7 10.66 7 11 C7.33 11.99 7.66 12.98 8 14 C5.34002109 14.73888303 3.46535456 15.10341212 0.75 14.5 C-1 13 -1 13 -1.875 9.75 C-2.00204591 5.93862261 -1.44890816 3.49442555 0 0 Z " fill="#F7D5C7" transform="translate(13,9)"/>
				<path d="M0 0 C4.62 0 9.24 0 14 0 C15.125 5.625 15.125 5.625 13 9 C10.36 9 7.72 9 5 9 C4.67 9.99 4.34 10.98 4 12 C3.67 11.01 3.34 10.02 3 9 C2.01 9 1.02 9 0 9 C0 6.03 0 3.06 0 0 Z " fill="#87B683" transform="translate(30,1)"/>
				<path d="M0 0 C1.51400391 0.01740234 1.51400391 0.01740234 3.05859375 0.03515625 C4.07050781 0.04417969 5.08242188 0.05320313 6.125 0.0625 C7.29869141 0.07990234 7.29869141 0.07990234 8.49609375 0.09765625 C8.49609375 0.42765625 8.49609375 0.75765625 8.49609375 1.09765625 C7.88121094 1.13503906 7.26632813 1.17242187 6.6328125 1.2109375 C5.42044922 1.30955078 5.42044922 1.30955078 4.18359375 1.41015625 C3.38308594 1.46816406 2.58257812 1.52617188 1.7578125 1.5859375 C-0.93294974 2.19472826 -1.89687238 2.87253244 -3.50390625 5.09765625 C-3.75426395 8.72301601 -3.75426395 8.72301601 -3.50390625 12.09765625 C-4.16390625 12.09765625 -4.82390625 12.09765625 -5.50390625 12.09765625 C-4.47797999 15.17543503 -3.74921381 15.94216099 -1.50390625 18.09765625 C0.49609375 21.09765625 0.49609375 21.09765625 0.49609375 25.09765625 C-2.47390625 25.42765625 -5.44390625 25.75765625 -8.50390625 26.09765625 C-8.83390625 28.07765625 -9.16390625 30.05765625 -9.50390625 32.09765625 C-10.49390625 31.76765625 -11.48390625 31.43765625 -12.50390625 31.09765625 C-10.62890625 25.22265625 -10.62890625 25.22265625 -9.50390625 24.09765625 C-7.98462587 24.02585661 -6.46223143 24.01373618 -4.94140625 24.03515625 C-4.11511719 24.04417969 -3.28882813 24.05320312 -2.4375 24.0625 C-1.48037109 24.07990234 -1.48037109 24.07990234 -0.50390625 24.09765625 C-1.27927734 23.27974609 -1.27927734 23.27974609 -2.0703125 22.4453125 C-7.31065118 16.44609895 -7.31065118 16.44609895 -7.8515625 12.671875 C-7.37374971 7.68418006 -5.8981182 0.23003581 0 0 Z " fill="#030304" transform="translate(13.50390625,1.90234375)"/>
				<path d="M0 0 C2.1640625 -0.29296875 2.1640625 -0.29296875 4.625 -0.1875 C5.44226562 -0.16042969 6.25953125 -0.13335937 7.1015625 -0.10546875 C8.04128906 -0.05326172 8.04128906 -0.05326172 9 0 C9.33 1.32 9.66 2.64 10 4 C7.36 4 4.72 4 2 4 C2.04125 4.9075 2.0825 5.815 2.125 6.75 C2.00468444 9.8782046 1.6085292 11.38614006 0 14 C-0.99 13.67 -1.98 13.34 -3 13 C-2.88557539 11.39504433 -2.75805306 9.79101881 -2.625 8.1875 C-2.55539062 7.29417969 -2.48578125 6.40085937 -2.4140625 5.48046875 C-2 3 -2 3 0 0 Z " fill="#66636D" transform="translate(11,4)"/>
				<path d="M0 0 C0.99 0 1.98 0 3 0 C3 1.32 3 2.64 3 4 C3.66 4 4.32 4 5 4 C5 2.68 5 1.36 5 0 C5.99 0.33 6.98 0.66 8 1 C8 2.65 8 4.3 8 6 C7.01 6.33 6.02 6.66 5 7 C5 7.66 5 8.32 5 9 C4.01 9 3.02 9 2 9 C2 8.34 2 7.68 2 7 C1.34 7 0.68 7 0 7 C-1.125 2.25 -1.125 2.25 0 0 Z " fill="#577AAA" transform="translate(14,32)"/>
				<path d="M0 0 C3 0 3 0 4.6875 1.125 C6.53521368 3.76459098 6.20946395 5.85804073 6 9 C6.66 9 7.32 9 8 9 C7.67 10.32 7.34 11.64 7 13 C7 12.01 7 11.02 7 10 C6.01 10.33 5.02 10.66 4 11 C3.67 9.35 3.34 7.7 3 6 C1.3603125 6.061875 1.3603125 6.061875 -0.3125 6.125 C-4 6 -4 6 -7 4 C-4.36 4 -1.72 4 1 4 C0.67 2.68 0.34 1.36 0 0 Z " fill="#574E52" transform="translate(20,4)"/>
				<path d="M0 0 C1.65 0 3.3 0 5 0 C4.67 3.96 4.34 7.92 4 12 C2.68 12 1.36 12 0 12 C0 8.04 0 4.08 0 0 Z " fill="#D9E6F7" transform="translate(26,28)"/>
				<path d="M0 0 C2.31 0 4.62 0 7 0 C7 1.32 7 2.64 7 4 C9.97 4 12.94 4 16 4 C16 4.33 16 4.66 16 5 C7.75 5 -0.5 5 -9 5 C-9 4.67 -9 4.34 -9 4 C-6.03 4 -3.06 4 0 4 C0 2.68 0 1.36 0 0 Z " fill="#736B69" transform="translate(14,23)"/>
				<path d="M0 0 C0.33 0 0.66 0 1 0 C1.33 2.97 1.66 5.94 2 9 C1.34 9 0.68 9 0 9 C0 8.34 0 7.68 0 7 C-6.6 7 -13.2 7 -20 7 C-19.34 6.01 -18.68 5.02 -18 4 C-18 4.66 -18 5.32 -18 6 C-17.01 6 -16.02 6 -15 6 C-14.67 5.34 -14.34 4.68 -14 4 C-14 4.66 -14 5.32 -14 6 C-9.38 6 -4.76 6 0 6 C0 4.02 0 2.04 0 0 Z " fill="#04070C" transform="translate(34,35)"/>
				<path d="M0 0 C0.45375 0.66 0.9075 1.32 1.375 2 C1.91125 2.66 2.4475 3.32 3 4 C3.66 4 4.32 4 5 4 C5 4.99 5 5.98 5 7 C4.34 7 3.68 7 3 7 C3 7.66 3 8.32 3 9 C1.35 9 -0.3 9 -2 9 C-1.34 6.03 -0.68 3.06 0 0 Z " fill="#C2A6A6" transform="translate(22,10)"/>
				<path d="M0 0 C0.33 1.32 0.66 2.64 1 4 C1.99 4.33 2.98 4.66 4 5 C3.67 7.31 3.34 9.62 3 12 C2.01 12 1.02 12 0 12 C0 8.04 0 4.08 0 0 Z " fill="#6E5A29" transform="translate(31,29)"/>
				<path d="M0 0 C0.99 0 1.98 0 3 0 C3 1.32 3 2.64 3 4 C3.66 4.33 4.32 4.66 5 5 C4.01 5.33 3.02 5.66 2 6 C1.01 6.495 1.01 6.495 0 7 C-1.125 2.25 -1.125 2.25 0 0 Z " fill="#42546E" transform="translate(14,32)"/>
				<path d="M0 0 C0.33 0 0.66 0 1 0 C1 1.98 1 3.96 1 6 C1.66 6 2.32 6 3 6 C2.67 7.32 2.34 8.64 2 10 C2 9.01 2 8.02 2 7 C1.01 7.33 0.02 7.66 -1 8 C-1.625 5.6875 -1.625 5.6875 -2 3 C-1.34 2.01 -0.68 1.02 0 0 Z " fill="#35353D" transform="translate(25,7)"/>
			</svg>
                <!-- Подраздел для технической поддержки -->
                <h3 class="fs-3 py-3">Техническая поддержка</h3>
                <!-- Описание услуг технической поддержки -->
                <p>Предоставление технической поддержки по всем вопросам, связанным с CS-Cart.</p>
            </div>
            <div class="col-md-3">
                <!-- Подраздел для консультаций -->
				<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="45" height="45">
					<path d="M0 0 C0.88945312 0.09667969 1.77890625 0.19335938 2.6953125 0.29296875 C3.41460938 0.46441406 4.13390625 0.63585937 4.875 0.8125 C6.04483345 3.1521669 6.0411241 4.64641527 6.0625 7.25 C6.07667969 8.03246094 6.09085938 8.81492187 6.10546875 9.62109375 C5.875 11.8125 5.875 11.8125 3.875 14.8125 C5.525 15.4725 7.175 16.1325 8.875 16.8125 C8.9575 15.53375 9.04 14.255 9.125 12.9375 C9.63504673 9.0432243 9.63504673 9.0432243 11.5 7.25 C14.91974736 6.62004654 17.51740161 6.94201153 20.875 7.8125 C22.375 9.4375 22.375 9.4375 22.875 11.8125 C22.5625 15.1875 22.5625 15.1875 21.875 18.8125 C21.5965625 20.5140625 21.5965625 20.5140625 21.3125 22.25 C21.168125 23.095625 21.02375 23.94125 20.875 24.8125 C21.65875 24.915625 22.4425 25.01875 23.25 25.125 C25.875 25.8125 25.875 25.8125 27.875 28.8125 C28.18754378 31.53533905 28.00833428 34.05692486 27.875 36.8125 C26.875 37.8125 26.875 37.8125 24.74194336 37.92602539 C23.36727905 37.91817017 23.36727905 37.91817017 21.96484375 37.91015625 C20.97548828 37.90693359 19.98613281 37.90371094 18.96679688 37.90039062 C17.92587891 37.89201172 16.88496094 37.88363281 15.8125 37.875 C14.76771484 37.87048828 13.72292969 37.86597656 12.64648438 37.86132812 C10.0559334 37.84949913 7.46549441 37.83301689 4.875 37.8125 C4.545 36.8225 4.215 35.8325 3.875 34.8125 C-2.065 34.4825 -8.005 34.1525 -14.125 33.8125 C-14.125 30.80039329 -14.03718989 27.82038527 -13.875 24.8125 C-13.82859375 23.760625 -13.7821875 22.70875 -13.734375 21.625 C-13.125 18.8125 -13.125 18.8125 -11.265625 17 C-9.125 15.8125 -9.125 15.8125 -6.125 14.8125 C-6.125 13.8225 -6.125 12.8325 -6.125 11.8125 C-6.785 11.8125 -7.445 11.8125 -8.125 11.8125 C-8.20631463 10.37594146 -8.26433559 8.9380517 -8.3125 7.5 C-8.34730469 6.69949219 -8.38210937 5.89898437 -8.41796875 5.07421875 C-8.125 2.8125 -8.125 2.8125 -6.93359375 1.0078125 C-4.44242202 -0.63862065 -2.93055768 -0.36311575 0 0 Z M9.875 17.8125 C10.875 19.8125 10.875 19.8125 10.875 19.8125 Z M10.875 19.8125 C11.875 22.8125 11.875 22.8125 11.875 22.8125 Z " fill="#6A5C87" transform="translate(16.125,6.1875)"/>
					<path d="M0 0 C1.5 1.625 1.5 1.625 2 4 C1.6875 7.375 1.6875 7.375 1 11 C0.814375 12.134375 0.62875 13.26875 0.4375 14.4375 C0.2209375 15.7059375 0.2209375 15.7059375 0 17 C0.78375 17.103125 1.5675 17.20625 2.375 17.3125 C5 18 5 18 7 21 C7.31254378 23.72283905 7.13333428 26.24442486 7 29 C6 30 6 30 3.86694336 30.11352539 C2.95050049 30.10828857 2.03405762 30.10305176 1.08984375 30.09765625 C0.10048828 30.09443359 -0.88886719 30.09121094 -1.90820312 30.08789062 C-2.94912109 30.07951172 -3.99003906 30.07113281 -5.0625 30.0625 C-6.10728516 30.05798828 -7.15207031 30.05347656 -8.22851562 30.04882812 C-10.8190666 30.03699913 -13.40950559 30.02051689 -16 30 C-17.25686099 26.22941703 -16.82745636 23.86146303 -16 20 C-14.625 18.0625 -14.625 18.0625 -13 17 C-12.01 17 -11.02 17 -10 17 C-10.18464366 12.65815798 -10.18464366 12.65815798 -12 9 C-11.6487524 1.62380038 -11.6487524 1.62380038 -9.375 -0.5625 C-5.95525264 -1.19245346 -3.35759839 -0.87048847 0 0 Z " fill="#65B8FE" transform="translate(37,14)"/>
					<path d="M0 0 C0.88945312 0.09667969 1.77890625 0.19335938 2.6953125 0.29296875 C3.41460937 0.46441406 4.13390625 0.63585937 4.875 0.8125 C6.95811141 4.97872283 6.48549338 9.96944652 5.05078125 14.30078125 C3.55455319 17.69927696 1.93833715 20.71749427 -0.125 23.8125 C-0.125 22.8225 -0.125 21.8325 -0.125 20.8125 C-0.785 20.8125 -1.445 20.8125 -2.125 20.8125 C-2.455 22.1325 -2.785 23.4525 -3.125 24.8125 C-3.63012244 23.20993892 -4.12891627 21.60538208 -4.625 20 C-4.9034375 19.10667969 -5.181875 18.21335937 -5.46875 17.29296875 C-6.125 14.8125 -6.125 14.8125 -6.125 11.8125 C-6.785 11.8125 -7.445 11.8125 -8.125 11.8125 C-8.20631463 10.37594146 -8.26433559 8.9380517 -8.3125 7.5 C-8.34730469 6.69949219 -8.38210937 5.89898437 -8.41796875 5.07421875 C-8.125 2.8125 -8.125 2.8125 -6.93359375 1.0078125 C-4.44242202 -0.63862065 -2.93055768 -0.36311575 0 0 Z " fill="#F8A78E" transform="translate(16.125,6.1875)"/>
					<path d="M0 0 C1.5 1.625 1.5 1.625 2 4 C1.70663502 8.1804509 1.34075925 10.48886112 -1 14 C-4.99455234 14.71014264 -6.56443994 14.29655153 -9.9375 12 C-12.06148605 8.91056575 -12.33140925 8.32752801 -12.0625 4.8125 C-11 1 -11 1 -9.375 -0.5625 C-5.95525264 -1.19245346 -3.35759839 -0.87048847 0 0 Z " fill="#5D5B81" transform="translate(37,14)"/>
					<path d="M0 0 C1.19109375 -0.00386719 1.19109375 -0.00386719 2.40625 -0.0078125 C4.5 0.125 4.5 0.125 6.5 1.125 C6.59765625 7.27734375 6.59765625 7.27734375 6.5 9.125 C4.85337258 10.77162742 2.82325947 10.52295099 0.5625 10.75 C-0.33339844 10.84539063 -1.22929687 10.94078125 -2.15234375 11.0390625 C-4.5 11.125 -4.5 11.125 -6.5 10.125 C-6.5 7.155 -6.5 4.185 -6.5 1.125 C-4.13386544 -0.05806728 -2.63219264 -0.00854608 0 0 Z " fill="#E1385E" transform="translate(29.5,0.875)"/>
					<path d="M0 0 C2.875 0.3125 2.875 0.3125 6 1 C8.14201526 4.21302289 8.65325876 5.26709281 8 9 C5.5625 10.625 5.5625 10.625 3 12 C2.67 12.99 2.34 13.98 2 15 C0.35 15 -1.3 15 -3 15 C-2.22095672 4.44191344 -2.22095672 4.44191344 0 0 Z " fill="#575A87" transform="translate(19,22)"/>
					<path d="M0 0 C0.91007812 0.10183594 1.82015625 0.20367187 2.7578125 0.30859375 C3.45648438 0.39238281 4.15515625 0.47617188 4.875 0.5625 C5.205 2.8725 5.535 5.1825 5.875 7.5625 C-0.065 6.5725 -0.065 6.5725 -6.125 5.5625 C-6.125 6.2225 -6.125 6.8825 -6.125 7.5625 C-6.785 7.5625 -7.445 7.5625 -8.125 7.5625 C-7.86938677 5.2193787 -7.53229228 2.88406597 -7.125 0.5625 C-4.50184968 -0.74907516 -2.89466564 -0.33399988 0 0 Z " fill="#A15A4C" transform="translate(16.125,6.4375)"/>
					<path d="M0 0 C6.27 0 12.54 0 19 0 C19 0.99 19 1.98 19 3 C12.73 3 6.46 3 0 3 C0 2.01 0 1.02 0 0 Z " fill="#B26953" transform="translate(2,37)"/>
					<path d="M0 0 C0.99 0.33 1.98 0.66 3 1 C2.34 1.66 1.68 2.32 1 3 C0.53837573 5.43252661 0.53837573 5.43252661 0.375 8.125 C0.30023437 9.03507812 0.22546875 9.94515625 0.1484375 10.8828125 C0.09945313 11.58148438 0.05046875 12.28015625 0 13 C-1.32 12.67 -2.64 12.34 -4 12 C-3.91253716 10.56138706 -3.80434652 9.12402492 -3.6875 7.6875 C-3.62949219 6.88699219 -3.57148438 6.08648437 -3.51171875 5.26171875 C-2.91793306 2.63727666 -2.04219706 1.69179328 0 0 Z " fill="#4080F9" transform="translate(24,31)"/>
					<path d="M0 0 C-0.64453125 0.37769531 -1.2890625 0.75539063 -1.953125 1.14453125 C-4.24029762 2.86059908 -4.24029762 2.86059908 -4.609375 5.69921875 C-4.65578125 6.68535156 -4.7021875 7.67148437 -4.75 8.6875 C-4.80671875 9.68136719 -4.8634375 10.67523438 -4.921875 11.69921875 C-4.94765625 12.45847656 -4.9734375 13.21773437 -5 14 C-5.66 14 -6.32 14 -7 14 C-7.10807131 12.06372231 -7.1856225 10.12572244 -7.25 8.1875 C-7.29640625 7.10855469 -7.3428125 6.02960937 -7.390625 4.91796875 C-7 2 -7 2 -5.171875 0.05078125 C-3 -1 -3 -1 0 0 Z " fill="#302E4C" transform="translate(10,23)"/>
					<path d="M0 0 C2.64 0.33 5.28 0.66 8 1 C8.33 1.99 8.66 2.98 9 4 C5.7 4 2.4 4 -1 4 C-0.67 2.68 -0.34 1.36 0 0 Z " fill="#E0CBE0" transform="translate(28,27)"/>
					<path d="M0 0 C0.66 0.66 1.32 1.32 2 2 C4.06874034 2.6425235 4.06874034 2.6425235 6 3 C6.33 2.01 6.66 1.02 7 0 C8 2 8 2 7.0625 5.125 C6.711875 6.07375 6.36125 7.0225 6 8 C5.67 7.01 5.34 6.02 5 5 C4.34 5 3.68 5 3 5 C2.67 6.32 2.34 7.64 2 9 C1.34 6.03 0.68 3.06 0 0 Z " fill="#D0C5E5" transform="translate(11,22)"/>
					<path d="M0 0 C3 1 3 1 4 3 C3.01 3 2.02 3 1 3 C1.33 4.98 1.66 6.96 2 9 C1.34 9 0.68 9 0 9 C0 8.01 0 7.02 0 6 C-0.66 6 -1.32 6 -2 6 C-2 4.68 -2 3.36 -2 2 C-1.34 2 -0.68 2 0 2 C0 1.34 0 0.68 0 0 Z " fill="#E38576" transform="translate(10,12)"/>
					<path d="M0 0 C1.25 1.5625 1.25 1.5625 2 4 C0.93237266 6.57001015 -0.46544943 8.6530403 -2 11 C-3.13643966 7.59068103 -2.86747764 6.99664544 -1.5625 3.8125 C-1.13130859 2.73935547 -1.13130859 2.73935547 -0.69140625 1.64453125 C-0.46324219 1.10183594 -0.23507813 0.55914063 0 0 Z " fill="#3B3A5C" transform="translate(19,23)"/>
					<path d="M0 0 C0.66 0.33 1.32 0.66 2 1 C2.40729228 3.32156597 2.74438677 5.6568787 3 8 C0 6 0 6 -0.75 3.4375 C-0.8325 2.633125 -0.915 1.82875 -1 1 C-0.67 0.67 -0.34 0.34 0 0 Z " fill="#343254" transform="translate(10,24)"/>
					<path d="M0 0 C2.475 0.99 2.475 0.99 5 2 C5 2.99 5 3.98 5 5 C3.35 4.67 1.7 4.34 0 4 C-0.33 3.01 -0.66 2.02 -1 1 C-0.67 0.67 -0.34 0.34 0 0 Z " fill="#F49D8B" transform="translate(12,20)"/>
				</svg>
                <h3 class="fs-3 py-3">Консультации</h3>
                <!-- Описание услуг консультаций по CS-Cart -->
                <p>Предоставление консультаций по вопросам разработки, доработки и использования CS-Cart.</p>
            </div>
            <div class="col-md-3">
                <!-- Подраздел для настройки и оптимизации -->
				<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="45" height="45">
					<path d="M0 0 C0 1.65 0 3.3 0 5 C-3.3 5 -6.6 5 -10 5 C-10 4.34 -10 3.68 -10 3 C-12.64 3 -15.28 3 -18 3 C-18 4.32 -18 5.64 -18 7 C-17.01 7 -16.02 7 -15 7 C-14.67 7.99 -14.34 8.98 -14 10 C-12.7934375 9.7215625 -12.7934375 9.7215625 -11.5625 9.4375 C-10.2940625 9.2209375 -10.2940625 9.2209375 -9 9 C-8.67 9.33 -8.34 9.66 -8 10 C-6.65252916 10.23075082 -5.29622435 10.41153063 -3.9375 10.5625 C-2.638125 10.706875 -1.33875 10.85125 0 11 C0 10.34 0 9.68 0 9 C2.375 8.375 2.375 8.375 5 8 C5.66 8.66 6.32 9.32 7 10 C6.625 12.625 6.625 12.625 6 15 C4.33388095 15.042721 2.66617115 15.04063832 1 15 C0.505 14.505 0.505 14.505 0 14 C-1.66617115 13.95936168 -3.33388095 13.957279 -5 14 C-5.66 15.65 -6.32 17.3 -7 19 C-5.68 19 -4.36 19 -3 19 C-3 19.99 -3 20.98 -3 22 C-1.35 22 0.3 22 2 22 C2 21.01 2 20.02 2 19 C4.97 19 7.94 19 11 19 C11 20.98 11 22.96 11 25 C8.03 25 5.06 25 2 25 C2 24.34 2 23.68 2 23 C0.35 23 -1.3 23 -3 23 C-3 23.99 -3 24.98 -3 26 C-4.32 26 -5.64 26 -7 26 C-6.67 27.98 -6.34 29.96 -6 32 C-4.948125 31.67 -3.89625 31.34 -2.8125 31 C0.38873357 30.16033218 2.7411008 29.80249096 6 30 C6.625 31.8125 6.625 31.8125 7 34 C6.34 34.99 5.68 35.98 5 37 C3.35 36.67 1.7 36.34 0 36 C0 35.34 0 34.68 0 34 C-1.85625 34.2475 -1.85625 34.2475 -3.75 34.5 C-7.73761859 35.03168248 -11.11049216 35.29650261 -15 34 C-15 35.32 -15 36.64 -15 38 C-15.99 38 -16.98 38 -18 38 C-18 39.32 -18 40.64 -18 42 C-15.36 42 -12.72 42 -10 42 C-10 41.34 -10 40.68 -10 40 C-6.7 40 -3.4 40 0 40 C0 41.65 0 43.3 0 45 C-6.42650535 45.12074853 -12.63111261 44.90984106 -19 44 C-20 43 -20 43 -20.0625 40.4375 C-20.041875 39.633125 -20.02125 38.82875 -20 38 C-20.66 38 -21.32 38 -22 38 C-22.495 36.515 -22.495 36.515 -23 35 C-23.7425 35.2475 -24.485 35.495 -25.25 35.75 C-28 36 -28 36 -30.375 34.375 C-32 32 -32 32 -31.75 29.25 C-31.5025 28.5075 -31.255 27.765 -31 27 C-31.99 26.67 -32.98 26.34 -34 26 C-34 23.69 -34 21.38 -34 19 C-33.01 18.67 -32.02 18.34 -31 18 C-31.33 16.35 -31.66 14.7 -32 13 C-29.75 10.9375 -29.75 10.9375 -27 9 C-24.6875 9.25 -24.6875 9.25 -23 10 C-22.67 9.01 -22.34 8.02 -22 7 C-21.34 7 -20.68 7 -20 7 C-20.020625 6.195625 -20.04125 5.39125 -20.0625 4.5625 C-20 2 -20 2 -19 1 C-12.63111261 0.09015894 -6.42650535 -0.12074853 0 0 Z " fill="#FCBF3F" transform="translate(34,0)"/>
					<path d="M0 0 C3.50035742 0.7971111 4.97632236 2.57948879 6.97265625 5.44921875 C7.58322457 9.11262868 7.20011253 10.95098118 5.66015625 14.32421875 C5.10328125 15.02546875 4.54640625 15.72671875 3.97265625 16.44921875 C2.98265625 16.44921875 1.99265625 16.44921875 0.97265625 16.44921875 C0.97265625 15.12921875 0.97265625 13.80921875 0.97265625 12.44921875 C0.31265625 12.44921875 -0.34734375 12.44921875 -1.02734375 12.44921875 C-1.02734375 14.09921875 -1.02734375 15.74921875 -1.02734375 17.44921875 C-3.21484375 17.82421875 -3.21484375 17.82421875 -6.02734375 17.44921875 C-8.38524298 15.34761292 -9.67404591 14.17369942 -11.02734375 11.44921875 C-11.09551948 7.4950267 -10.59386605 5.33299353 -8.46484375 2.01171875 C-5.62583458 0.19184107 -3.3659453 -0.18245311 0 0 Z " fill="#F1F1F3" transform="translate(17.02734375,13.55078125)"/>
					<path d="M0 0 C1.125 3.75 1.125 3.75 0 6 C-1.66611905 6.042721 -3.33382885 6.04063832 -5 6 C-5.33 5.67 -5.66 5.34 -6 5 C-7.155 4.855625 -8.31 4.71125 -9.5 4.5625 C-10.655 4.376875 -11.81 4.19125 -13 4 C-13.33 3.34 -13.66 2.68 -14 2 C-11.36 2 -8.72 2 -6 2 C-6 1.34 -6 0.68 -6 0 C-3.50907189 -1.24546405 -2.58919267 -0.7767578 0 0 Z " fill="#F483A0" transform="translate(40,9)"/>
					<path d="M0 0 C1.2065625 0.0309375 1.2065625 0.0309375 2.4375 0.0625 C3.0625 1.875 3.0625 1.875 3.4375 4.0625 C2.4475 5.5475 2.4475 5.5475 1.4375 7.0625 C-0.2125 6.7325 -1.8625 6.4025 -3.5625 6.0625 C-3.5625 5.4025 -3.5625 4.7425 -3.5625 4.0625 C-6.2025 4.0625 -8.8425 4.0625 -11.5625 4.0625 C-11.2325 3.4025 -10.9025 2.7425 -10.5625 2.0625 C-8.23534 1.68849214 -5.90132138 1.35485267 -3.5625 1.0625 C-2.5625 0.0625 -2.5625 0.0625 0 0 Z " fill="#F486A2" transform="translate(37.5625,29.9375)"/>
					<path d="M0 0 C2.97 0 5.94 0 9 0 C9 1.98 9 3.96 9 6 C6.03 6 3.06 6 0 6 C0 4.02 0 2.04 0 0 Z " fill="#706AE1" transform="translate(36,19)"/>
					<path d="M0 0 C3.3 0 6.6 0 10 0 C10 1.65 10 3.3 10 5 C7.03 5 4.06 5 1 5 C0.67 3.35 0.34 1.7 0 0 Z " fill="#6966E9" transform="translate(24,40)"/>
					<path d="M0 0 C2.97 0 5.94 0 9 0 C9 1.65 9 3.3 9 5 C5.7 5 2.4 5 -1 5 C-0.67 3.35 -0.34 1.7 0 0 Z " fill="#6966E9" transform="translate(25,0)"/>
					<path d="M0 0 C0.66 0.66 1.32 1.32 2 2 C4.10820797 1.6814823 4.10820797 1.6814823 6 1 C5.67 1.99 5.34 2.98 5 4 C4.96079185 5.99961564 4.95556653 8.00049364 5 10 C0.07692308 5.69230769 0.07692308 5.69230769 -0.3125 2.1875 C-0.209375 1.465625 -0.10625 0.74375 0 0 Z " fill="#CFE2D2" transform="translate(6,20)"/>
					<path d="M0 0 C0.66 0 1.32 0 2 0 C2 1.32 2 2.64 2 4 C4.97 4 7.94 4 11 4 C10.67 4.99 10.34 5.98 10 7 C8.52031676 6.85950482 7.04125484 6.71245174 5.5625 6.5625 C4.73878906 6.48128906 3.91507813 6.40007813 3.06640625 6.31640625 C1 6 1 6 0 5 C-0.04063832 3.33382885 -0.042721 1.66611905 0 0 Z " fill="#E3D7BE" transform="translate(14,38)"/>
					<path d="M0 0 C0.33 0.99 0.66 1.98 1 3 C-1.97 3 -4.94 3 -8 3 C-8 4.32 -8 5.64 -8 7 C-8.66 7 -9.32 7 -10 7 C-10.042721 5.33388095 -10.04063832 3.66617115 -10 2 C-8.41727399 0.41727399 -6.61965997 0.65264253 -4.4375 0.4375 C-3.19806641 0.31181641 -3.19806641 0.31181641 -1.93359375 0.18359375 C-0.97646484 0.09271484 -0.97646484 0.09271484 0 0 Z " fill="#E3D7BD" transform="translate(24,0)"/>
					<path d="M0 0 C0.66 0.33 1.32 0.66 2 1 C1.67 1.99 1.34 2.98 1 4 C0.01 3.34 -0.98 2.68 -2 2 C-1.34 1.34 -0.68 0.68 0 0 Z " fill="#E76AB1" transform="translate(7,38)"/>
					<path d="M0 0 C0.99 0.33 1.98 0.66 3 1 C2.01 2.485 2.01 2.485 1 4 C0.34 3.01 -0.32 2.02 -1 1 C-0.67 0.67 -0.34 0.34 0 0 Z " fill="#9AA9FF" transform="translate(39,3)"/>
				</svg>
                <h3 class="fs-3 py-3">Настройка и оптимизация</h3>
                <!-- Описание услуг по настройке и оптимизации CS-Cart -->
                <p>Настройка и оптимизация CS-Cart под требования клиента.</p>
            </div>
            <div class="col-md-3 text-center">
                <!-- Подраздел для обновлений и модернизации -->
				<svg
				   version="1.1"
				   width="44.999996"
				   height="44.999996"
				   id="svg60"
				   sodipodi:docname="upgrade.svg"
				   inkscape:export-filename="upgrade2.svg"
				   inkscape:export-xdpi="8.4375"
				   inkscape:export-ydpi="8.4375"
				   xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape"
				   xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd"
				   xmlns="http://www.w3.org/2000/svg"
				   xmlns:svg="http://www.w3.org/2000/svg">
				  <defs
					 id="defs64" />
				  <sodipodi:namedview
					 id="namedview62"
					 pagecolor="#ffffff"
					 bordercolor="#000000"
					 borderopacity="0.25"
					 inkscape:showpageshadow="2"
					 inkscape:pageopacity="0.0"
					 inkscape:pagecheckerboard="0"
					 inkscape:deskcolor="#d1d1d1"
					 showgrid="false"
					 inkscape:zoom="1.6191406"
					 inkscape:cx="256.61761"
					 inkscape:cy="256.30881"
					 inkscape:window-width="1920"
					 inkscape:window-height="1009"
					 inkscape:window-x="1358"
					 inkscape:window-y="-8"
					 inkscape:window-maximized="1"
					 inkscape:current-layer="svg60" />
				  <path
					 d="m 38.447325,0.24858968 c 0.704574,0.55291361 1.271705,1.31633262 1.828213,2.01249452 0.238803,0.2948341 0.484616,0.5829336 0.731692,0.8707612 0.311736,0.3635424 0.619817,0.7291307 0.920788,1.1017935 0.297822,0.3685143 0.601916,0.7307927 0.909823,1.0907755 0.06641,0.07775 0.06641,0.07775 0.134155,0.1570701 0.178205,0.2085386 0.356489,0.4170053 0.535539,0.6248158 1.481096,1.7197117 1.481096,1.7197117 1.470241,2.8192572 -0.0433,0.5130312 -0.253819,0.8116345 -0.632697,1.1530955 -0.801626,0.575526 -1.223837,0.369101 -2.373215,0.369101 0.02894,7.475448 0.05788,14.950896 0.08769,22.652873 0.31833,0.174524 0.636657,0.349048 0.964631,0.528861 1.003848,0.796574 1.697783,1.806371 1.929264,3.085022 0.09133,0.996023 0.08318,1.993327 -0.350777,2.908734 -0.03075,0.06885 -0.06149,0.137711 -0.09317,0.208652 -0.591019,1.192221 -1.592783,1.902065 -2.767837,2.430144 -0.739067,0.335386 -1.277345,0.696729 -1.644258,1.448858 -0.17786,0.478391 -0.17786,0.478391 -0.317891,1.289098 -1.909969,0 -3.819942,0 -5.787794,0 -0.08681,-0.669008 -0.17363,-1.338017 -0.263077,-2.027299 -0.575335,-2.998522 -2.214051,-5.684644 -4.725875,-7.433319 -2.531847,-1.689753 -5.602869,-2.400838 -8.603584,-1.821746 -1.081237,0.229493 -2.076895,0.570959 -3.069284,1.057721 -0.08727,0.04261 -0.174536,0.08522 -0.26445,0.129116 -0.985606,0.510898 -1.825434,1.226393 -2.629447,1.986328 -0.07766,0.06954 -0.07766,0.06954 -0.156889,0.140478 -1.359034,1.268894 -2.198903,3.041557 -2.737007,4.795556 -0.04019,0.126121 -0.04019,0.126121 -0.08119,0.25479 -0.269768,0.951129 -0.323087,1.938296 -0.444972,2.918375 -1.9099758,0 -3.8199429,0 -5.787792,0 C 5.1413381,44.621861 5.0545213,44.243725 4.9650734,43.854131 4.5043554,43.022692 3.9552372,42.553988 3.090618,42.184914 2.5115417,41.922768 2.0087243,41.654771 1.5450148,41.209827 1.4777531,41.145744 1.4104933,41.08166 1.341195,41.015635 0.35869069,40.003609 0.01675145,38.803387 0.02234018,37.424865 0.04380938,36.321369 0.3726621,35.486958 1.0188516,34.599066 c 0.06223,-0.08692 0.06223,-0.08692 0.1257171,-0.175598 0.474504,-0.607994 1.1252316,-0.981945 1.8035471,-1.322841 0.028938,-4.188578 0.057878,-8.377156 0.087694,-12.69266 -0.4919626,0 -0.9839251,0 -1.4907948,0 C 0.94703046,20.249795 0.58247235,20.017563 0.20220306,19.532041 -0.06614618,19.021215 -0.032805,18.379824 0.10286176,17.831491 0.31100129,17.348324 0.69635862,16.98255 1.0507091,16.604025 1.2657603,16.370899 1.4669624,16.132393 1.6655938,15.885105 1.9697772,15.506437 2.2811349,15.135277 2.5973402,14.766784 2.9074423,14.405199 3.2137075,14.041537 3.5126444,13.6705 3.8282425,13.279592 4.1536707,12.898524 4.4827572,12.519126 4.8245863,12.125024 5.1622772,11.72872 5.4912361,11.32368 5.5535221,11.2471 5.615809,11.170516 5.6799833,11.091615 c 0.096431,-0.120901 0.1913654,-0.243074 0.2829493,-0.367724 0.2782192,-0.371015 0.5779067,-0.587969 1.0190986,-0.716854 0.5702071,-0.06703 1.0947995,-0.07945 1.5784889,0.26443 0.679657,0.662813 1.2985182,1.379928 1.8929529,2.119919 0.241389,0.296418 0.488966,0.586905 0.737861,0.876959 0.377077,0.439766 0.745821,0.885156 1.109871,1.335925 0.240002,0.292691 0.48539,0.580339 0.731701,0.867662 1.964017,2.29344 1.964017,2.29344 1.953929,3.342222 -0.04517,0.497511 -0.230297,0.826868 -0.572752,1.18064 -0.853531,0.526443 -1.310955,0.413173 -2.433502,0.413173 0.02894,1.018057 0.05788,2.036114 0.08769,3.085021 0.231515,-0.174524 0.463022,-0.349048 0.70155,-0.52886 1.331886,-0.713521 2.604202,-0.730928 4.033919,-0.352574 0.144696,0.05817 0.28939,0.116349 0.438464,0.176287 0.02894,-2.821473 0.05788,-5.642946 0.0877,-8.549918 -0.578777,0 -1.157561,0 -1.753874,0 -0.595119,-0.299084 -0.595119,-0.299084 -0.860498,-0.617004 -0.05121,-0.05999 -0.102414,-0.119985 -0.15518,-0.181796 -0.315367,-0.433125 -0.255615,-0.981738 -0.212038,-1.49293 0.145438,-0.586849 0.595848,-0.996095 0.996488,-1.424068 0.21505,-0.233126 0.416257,-0.471632 0.614881,-0.71892 0.30419,-0.378669 0.615554,-0.7498245 0.931748,-1.1183206 C 17.20316,8.3213351 17.51125,7.955754 17.812222,7.583091 18.19648,7.1076176 18.592793,6.6432876 18.990263,6.1789929 19.25495,5.8696902 19.51775,5.5588113 19.779848,5.2472888 c 0.03825,-0.045435 0.0765,-0.090869 0.115913,-0.137681 0.224221,-0.266425 0.224221,-0.266425 0.444634,-0.5360269 0.354575,-0.4386454 0.67193,-0.6335226 1.226212,-0.7255697 0.570215,-0.045273 1.060068,0.1164775 1.50724,0.4737711 0.325318,0.3052893 0.598193,0.6534965 0.875225,1.0026321 0.241603,0.30397 0.494056,0.5980995 0.74711,0.8924527 0.311765,0.3635167 0.619813,0.7291319 0.920782,1.1017934 0.384729,0.476042 0.781552,0.9409033 1.179414,1.4058195 0.375602,0.4389442 0.748519,0.880128 1.118999,1.323443 0.110954,0.132748 0.222189,0.26526 0.333432,0.397765 1.120404,1.351379 1.120404,1.351379 1.095145,2.117509 -0.05547,0.509618 -0.242651,0.963558 -0.613855,1.322152 -0.613857,0.352574 -0.613857,0.352574 -2.45543,0.352574 0.02894,3.141434 0.05788,6.282867 0.0877,9.519496 0.434079,-0.349048 0.434079,-0.349048 0.876937,-0.705148 1.366516,-0.735817 2.730764,-0.888738 4.209299,-0.440717 0.54302,0.21862 1.074592,0.463094 1.490796,0.881434 0.02894,-4.304927 0.05788,-8.609854 0.0877,-13.045234 -0.549841,0 -1.099683,0 -1.666184,0 -0.640372,-0.321827 -1.046349,-0.567945 -1.31541,-1.2340087 -0.07541,-0.6032047 -0.0898,-1.1624918 0.280555,-1.6626753 0.0521,-0.062151 0.104222,-0.1243029 0.157914,-0.1883377 0.06929,-0.084876 0.138588,-0.169752 0.209982,-0.2572 0.205487,-0.2488014 0.415029,-0.4938663 0.62559,-0.7383306 0.205627,-0.2395296 0.408402,-0.4814622 0.611372,-0.7232672 0.08014,-0.095274 0.160301,-0.1905332 0.240476,-0.2857777 0.03976,-0.047238 0.07952,-0.094477 0.120492,-0.1431471 0.122757,-0.1456746 0.245691,-0.2911907 0.368673,-0.4366717 0.37264,-0.4411508 0.743338,-0.883332 1.106448,-1.3324814 0.343021,-0.4234425 0.694443,-0.8389089 1.049245,-1.2523648 0.225223,-0.2625802 0.448937,-0.5261578 0.669348,-0.7928393 0.04281,-0.051599 0.0856,-0.1031976 0.129702,-0.1563599 0.0806,-0.097348 0.160864,-0.1949707 0.24073,-0.29292221 0.664016,-0.80003237 1.604773,-1.26767862 2.591291,-0.70278071 z"
					 fill="#796d78"
					 id="path34"
					 style="display:inline;fill:#796d78;fill-opacity:1;stroke-width:0.0879182;image-rendering:optimizeSpeed" />
				  <path
					 d="m 38.447325,0.24858968 c 0.704575,0.55291361 1.271705,1.31633272 1.828213,2.01249462 0.238804,0.2948341 0.484616,0.5829334 0.731692,0.8707611 0.311736,0.3635424 0.619818,0.7291307 0.920789,1.1017935 0.297822,0.3685143 0.601916,0.7307926 0.909822,1.0907755 0.06641,0.07775 0.06641,0.07775 0.134155,0.1570701 0.178205,0.2085386 0.356489,0.4170053 0.535539,0.6248158 1.481096,1.7197118 1.481096,1.7197118 1.470241,2.8192572 -0.0433,0.5130312 -0.253819,0.8116345 -0.632697,1.1530955 -0.801625,0.575527 -1.223837,0.369101 -2.373214,0.369101 0,7.504535 0,15.009071 0,22.741017 -0.607718,-0.130893 -0.607718,-0.130893 -1.227713,-0.264431 -1.002653,-0.14383 -1.803613,0.08991 -2.738165,0.454253 -0.762141,0.287991 -1.484794,0.380454 -2.271345,0.124189 -0.848283,-0.388559 -1.507647,-1.105572 -1.838487,-1.975653 -0.294718,-1.024724 0.0058,-1.859119 0.391198,-2.803032 0.431535,-1.090133 0.311317,-2.313802 -0.120241,-3.380096 -0.263196,-0.601561 -0.263196,-0.601561 -0.673882,-1.101849 -0.383164,-0.3587 -0.509775,-0.632527 -0.539955,-1.159614 -0.0018,-0.236237 -2.63e-4,-0.470951 0.0041,-0.707009 1.76e-4,-0.130364 7.5e-5,-0.260728 -2.64e-4,-0.391092 -1.76e-4,-0.352209 0.0034,-0.704289 0.0077,-1.05647 0.0039,-0.368669 0.0042,-0.737341 0.0049,-1.106027 0.0019,-0.697391 0.007,-1.39472 0.01311,-2.092084 0.0069,-0.794243 0.01024,-1.58849 0.01332,-2.382755 0.0064,-1.63315 0.0172,-3.266244 0.03089,-4.899347 -0.112138,0.0041 -0.224279,0.0082 -0.339818,0.0124 -0.149807,0.0033 -0.299615,0.0065 -0.449429,0.0096 -0.07359,0.003 -0.147179,0.0059 -0.223,0.009 -0.578789,0.009 -1.058887,-0.10079 -1.489383,-0.511215 -0.45529,-0.5267418 -0.526657,-0.9278774 -0.511479,-1.6145579 0.04778,-0.4321945 0.195214,-0.6625331 0.469988,-0.9902368 0.06929,-0.084876 0.138588,-0.1697519 0.209983,-0.2572 0.205486,-0.2488014 0.415028,-0.4938663 0.625588,-0.7383307 0.205628,-0.2395295 0.408402,-0.4814621 0.611372,-0.7232671 0.08014,-0.095274 0.160303,-0.1905332 0.240478,-0.2857777 0.05964,-0.070858 0.05964,-0.070858 0.120491,-0.1431471 0.122756,-0.1456746 0.24569,-0.2911907 0.368673,-0.4366717 0.37264,-0.4411508 0.743338,-0.8833319 1.106448,-1.3324814 0.34302,-0.4234426 0.694443,-0.8389089 1.049244,-1.2523648 0.225225,-0.2625802 0.448938,-0.5261578 0.669349,-0.7928393 0.04281,-0.051599 0.0856,-0.1031976 0.129702,-0.15636 0.0806,-0.097348 0.160865,-0.1949706 0.24073,-0.29292211 0.664016,-0.80003237 1.604773,-1.26767862 2.591291,-0.70278071 z"
					 fill="#fee076"
					 id="path36"
					 style="stroke-width:0.0879182" />
				  <path
					 d="m 23.117696,4.3658541 c 0.201152,0.1928914 0.377551,0.4009701 0.553567,0.6170043 0.0727,0.086793 0.0727,0.086793 0.146867,0.1753401 0.09932,0.1187117 0.198254,0.2377563 0.296779,0.3571361 0.133121,0.1609604 0.267559,0.3206428 0.402805,0.4797965 0.369648,0.4352301 0.734568,0.8740083 1.093774,1.3180205 0.386873,0.4775795 0.78536,0.9444757 1.18489,1.4113285 0.375602,0.4389441 0.748519,0.8801279 1.118999,1.3234439 0.110954,0.132748 0.222189,0.265259 0.333433,0.397764 1.120403,1.351379 1.120403,1.351379 1.095144,2.117509 -0.05547,0.509619 -0.242651,0.963558 -0.613855,1.322152 -0.527514,0.378545 -0.935752,0.397377 -1.57301,0.37461 -0.08506,-0.0016 -0.170127,-0.0032 -0.257774,-0.0048 -0.208302,-0.0041 -0.416426,-0.0099 -0.624646,-0.01721 5.28e-4,0.04961 0.0011,0.09922 0.0017,0.150337 0.01359,1.210314 0.02369,2.420615 0.03009,3.63099 0.0032,0.585358 0.0075,1.170634 0.01451,1.755959 0.0067,0.565233 0.01034,1.130411 0.01193,1.695681 0.0011,0.215282 0.0033,0.430562 0.0067,0.645821 0.0045,0.302376 0.0051,0.604534 0.0048,0.906938 0.0022,0.08832 0.0044,0.176636 0.0067,0.26763 -0.0061,0.605396 -0.167306,0.946767 -0.541238,1.420054 -0.210957,0.271776 -0.326758,0.547243 -0.461402,0.864044 -0.418013,0.937426 -0.888391,1.584999 -1.860071,1.983917 -0.759974,0.205302 -1.630574,0.193772 -2.349234,-0.138413 -0.893161,-0.533264 -1.253043,-1.235354 -1.667852,-2.156416 -0.38243,-0.834023 -0.781517,-1.45487 -1.634455,-1.850669 -0.272162,-0.180492 -0.464676,-0.315594 -0.565608,-0.636159 -0.09364,-0.592872 -0.06174,-1.193464 -0.04405,-1.79123 0.002,-0.194631 0.0035,-0.389269 0.0045,-0.583907 0.004,-0.510792 0.01433,-1.021263 0.02599,-1.531929 0.01079,-0.521684 0.01558,-1.043421 0.02087,-1.565187 0.01127,-1.022628 0.02922,-2.045017 0.05139,-3.067461 -0.05038,0.0014 -0.100753,0.0028 -0.152651,0.0042 -0.23046,0.0055 -0.460906,0.0089 -0.691404,0.01233 -0.07924,0.0022 -0.158483,0.0044 -0.240128,0.0067 -0.561972,0.0062 -0.951718,-0.05931 -1.378441,-0.458449 -0.05008,-0.05999 -0.100157,-0.119985 -0.151748,-0.181796 -0.05121,-0.05999 -0.102415,-0.119985 -0.155182,-0.181796 -0.315367,-0.433126 -0.255613,-0.981738 -0.212037,-1.49293 0.145439,-0.586849 0.595849,-0.996095 0.996489,-1.424068 0.215049,-0.233126 0.416256,-0.471632 0.614881,-0.71892 0.304189,-0.3786683 0.615553,-0.7498246 0.931747,-1.1183207 0.31173,-0.3635493 0.619819,-0.7291303 0.920791,-1.1017935 0.384259,-0.4754733 0.780571,-0.9398032 1.178041,-1.404098 0.264689,-0.3093027 0.52749,-0.6201815 0.789587,-0.9317041 0.05738,-0.068152 0.05738,-0.068152 0.115914,-0.1376811 0.224125,-0.2661882 0.224125,-0.2661882 0.443861,-0.5360269 0.30984,-0.3853061 0.583906,-0.5620909 1.051595,-0.7145517 0.697238,-0.065192 1.168595,0.083763 1.726478,0.506825 z"
					 fill="#81ade2"
					 id="path38"
					 style="stroke-width:0.0879182" />
				  <path
					 d="m 8.3358045,10.111707 c 0.8265862,0.587656 1.4859524,1.493281 2.1176685,2.279679 0.24139,0.296418 0.488967,0.586905 0.737861,0.876959 0.377077,0.439766 0.745821,0.885156 1.109871,1.335925 0.240002,0.292691 0.485391,0.580339 0.731701,0.867662 1.964017,2.29344 1.964017,2.29344 1.953929,3.342222 -0.04517,0.497511 -0.230297,0.826869 -0.572752,1.18064 -0.59454,0.366702 -1.032808,0.429362 -1.714139,0.420058 -0.06923,-5.28e-4 -0.138448,-9.96e-4 -0.209778,-0.0015 -0.169865,-0.0013 -0.339727,-0.0033 -0.509585,-0.0054 0.0055,0.141961 0.0055,0.141961 0.01113,0.286789 0.01279,0.35429 0.02079,0.70856 0.0274,1.063016 0.0035,0.152794 0.0083,0.305565 0.01439,0.458277 0.05582,1.432821 0.05582,1.432821 -0.385203,1.91598 -0.122721,0.110594 -0.245779,0.220817 -0.36927,0.330538 -0.138946,0.201686 -0.233386,0.399597 -0.339817,0.622514 -0.04861,0.09919 -0.09721,0.198384 -0.147296,0.300583 -0.237709,0.583118 -0.334501,1.119881 -0.329538,1.748752 4.39e-4,0.0656 8.78e-4,0.131191 0.0013,0.198775 0.01391,0.661585 0.167431,1.220058 0.404276,1.835756 0.370164,0.9756 0.462755,1.708594 0.101048,2.703526 -0.385471,0.775934 -1.0432488,1.36591 -1.8549219,1.646837 -1.0108435,0.267928 -1.7119277,0.03626 -2.646233,-0.363248 -0.5623507,-0.23232 -1.0999045,-0.247768 -1.7000954,-0.248248 -0.069747,-0.0011 -0.1394948,-0.0022 -0.2113557,-0.0033 -0.5467272,-0.0013 -0.9640412,0.0842 -1.520597,0.196085 0,-4.188578 0,-8.377156 0,-12.692661 -0.4919626,0 -0.9839251,0 -1.4907949,0 C 0.94702899,20.249753 0.58247122,20.01752 0.20220123,19.531998 -0.06614772,19.021172 -0.03280678,18.379781 0.10286053,17.831448 0.31099985,17.348281 0.69635703,16.982507 1.0507073,16.603981 1.2657587,16.370901 1.4669614,16.132396 1.6655921,15.885107 1.9697758,15.506439 2.281134,15.13528 2.5973385,14.766787 2.9074409,14.405202 3.2137064,14.041541 3.5126428,13.670502 3.8282409,13.279596 4.1536697,12.898527 4.4827556,12.519128 4.8245851,12.125027 5.1622759,11.728723 5.4912345,11.323682 5.5535215,11.247101 5.6158079,11.170519 5.679982,11.091617 5.776413,10.970716 5.8713477,10.848544 5.9629315,10.723893 6.5403571,9.953876 7.4504394,9.804288 8.3358028,10.11171 Z"
					 fill="#baeb6b"
					 id="path40"
					 style="stroke-width:0.0879182" />
				  <path
					 d="m 38.485009,0.29162849 c 0.22947,0.1600716 0.407507,0.3387839 0.592959,0.5484865 -0.133687,0.44068341 -0.396006,0.72247401 -0.696067,1.05772181 -0.103623,0.117933 -0.207188,0.2359176 -0.310698,0.3539511 -0.05193,0.058913 -0.103857,0.1178265 -0.15736,0.178525 C 37.692072,2.6834412 37.474729,2.9401934 37.25832,3.197953 37.21875,3.245 37.17919,3.292047 37.138428,3.3405191 c -0.24614,0.293521 -0.488693,0.5897754 -0.729641,0.8876108 -0.299845,0.3701502 -0.605747,0.7343528 -0.915302,1.0962845 -0.04427,0.051762 -0.08853,0.1035242 -0.134134,0.1568549 -0.08968,0.1048399 -0.179365,0.2096752 -0.269055,0.3145061 -1.311525,1.3453069 -1.311525,1.3453069 -1.827527,3.0581655 0.06875,0.4604034 0.290475,0.8362511 0.635783,1.1420781 0.765225,0.533694 1.223035,0.392406 2.28552,0.451735 0,7.620885 0,15.24177 0,23.093591 -0.893713,-0.149716 -1.492652,-0.678693 -2.01696,-1.410296 -0.384847,-0.643982 -0.488746,-1.378116 -0.350775,-2.115443 0.127691,-0.442009 0.297206,-0.866193 0.471016,-1.291509 0.431535,-1.090133 0.311317,-2.313802 -0.120241,-3.380096 -0.263196,-0.601561 -0.263196,-0.601561 -0.673882,-1.101849 -0.383164,-0.3587 -0.509775,-0.632527 -0.539955,-1.159614 -0.0018,-0.236237 -2.63e-4,-0.470951 0.0041,-0.707009 1.76e-4,-0.130364 7.5e-5,-0.260728 -2.64e-4,-0.391092 -1.76e-4,-0.352209 0.0034,-0.704289 0.0077,-1.05647 0.0039,-0.368669 0.0042,-0.737341 0.0049,-1.106027 0.0019,-0.697391 0.007,-1.39472 0.01311,-2.092084 0.0069,-0.794243 0.01024,-1.58849 0.01332,-2.382755 0.0064,-1.63315 0.0172,-3.266244 0.03089,-4.899347 -0.112138,0.0041 -0.224279,0.0082 -0.339818,0.0124 -0.149807,0.0033 -0.299615,0.0065 -0.449429,0.0096 -0.07359,0.003 -0.147179,0.0059 -0.223,0.009 -0.578789,0.009 -1.058887,-0.10079 -1.489383,-0.511215 -0.45529,-0.5267418 -0.526657,-0.9278774 -0.511479,-1.6145579 0.04778,-0.4321945 0.195214,-0.6625331 0.469988,-0.9902368 0.06929,-0.084876 0.138588,-0.1697519 0.209983,-0.2572 0.205486,-0.2488014 0.415028,-0.4938663 0.625588,-0.7383307 0.205628,-0.2395295 0.408402,-0.4814621 0.611372,-0.7232671 0.08014,-0.095274 0.160303,-0.1905332 0.240478,-0.2857777 0.05964,-0.070858 0.05964,-0.070858 0.120491,-0.1431471 0.122756,-0.1456746 0.24569,-0.2911907 0.368673,-0.4366717 0.37264,-0.4411508 0.743338,-0.8833319 1.106448,-1.3324814 0.34302,-0.4234426 0.694443,-0.8389089 1.049244,-1.2523648 0.225225,-0.2625802 0.448938,-0.5261578 0.669349,-0.7928393 0.04281,-0.051599 0.0856,-0.1031976 0.129702,-0.15636 0.0806,-0.097348 0.160865,-0.1949706 0.24073,-0.29292211 0.705303,-0.84977743 1.609995,-1.23936111 2.628975,-0.6597419 z"
					 fill="#fdc165"
					 id="path42"
					 style="stroke-width:0.0879182" />
				  <path
					 d="m 8.3358045,10.111707 c 0.255386,0.181565 0.4631525,0.358026 0.6631825,0.600477 0,0.451243 -0.1908801,0.614648 -0.476833,0.931016 -0.097719,0.111131 -0.1953462,0.222344 -0.2928837,0.333637 -0.072552,0.08217 -0.072552,0.08217 -0.1465703,0.166001 -0.1824596,0.20914 -0.3568959,0.42425 -0.5306588,0.640718 -0.3450255,0.42721 -0.7002831,0.84466 -1.057657,1.261403 -0.2641038,0.308639 -0.5235144,0.620273 -0.7784324,0.936675 -0.2671113,0.331337 -0.5414928,0.654659 -0.8212089,0.975195 -1.1625768,1.20729 -1.1625768,1.20729 -1.6205159,2.734061 0.055056,0.488051 0.2465316,0.8856 0.5946736,1.226779 0.8367315,0.525638 0.9897349,0.335104 2.3238861,0.402154 0,4.188578 0,8.377156 0,12.692661 -0.2025726,-0.02909 -0.4051451,-0.05817 -0.6138567,-0.08814 -0.2722545,-0.0167 -0.5439243,-0.01631 -0.8166487,-0.01653 -0.069352,-0.0011 -0.1387036,-0.0022 -0.2101569,-0.0033 -0.5451888,-0.0013 -0.9610659,0.08447 -1.5163148,0.196085 0,-4.188578 0,-8.377156 0,-12.69266 -0.4919626,0 -0.9839251,0 -1.4907948,0 C 0.94703061,20.249797 0.58247284,20.017564 0.20220286,19.532042 -0.06614609,19.021216 -0.03280516,18.379825 0.10286216,17.831492 0.31100148,17.348325 0.69635867,16.982551 1.0507089,16.604026 1.2657603,16.370899 1.466963,16.132394 1.6655938,15.885105 1.9697774,15.506438 2.2811356,15.135278 2.5973402,14.766785 2.9074425,14.4052 3.213708,14.041539 3.5126444,13.670501 c 0.3155981,-0.390907 0.6410269,-0.771976 0.9701128,-1.151375 0.3418295,-0.3941 0.6795203,-0.790405 1.0084789,-1.195445 0.062287,-0.07658 0.1245735,-0.153164 0.1887475,-0.232065 0.096431,-0.120902 0.1913657,-0.243074 0.2829495,-0.367724 C 6.5403587,9.953875 7.450441,9.804286 8.3358045,10.111708 Z"
					 fill="#a2e62e"
					 id="path44"
					 style="stroke-width:0.0879182" />
				  <path
					 d="m 23.274584,4.4904944 c 0.05256,0.069196 0.05256,0.069196 0.106188,0.1397901 -0.05705,0.399313 -0.311578,0.6405766 -0.570007,0.9310155 -0.09703,0.1112474 -0.193976,0.2225753 -0.290826,0.3339811 -0.04784,0.054794 -0.09566,0.1095888 -0.144944,0.1660438 -0.179298,0.2077542 -0.35164,0.4207334 -0.523382,0.6348223 -0.363266,0.4499529 -0.740456,0.8866112 -1.11925,1.3233143 -0.343994,0.3975007 -0.678084,0.8018152 -1.007664,1.2114991 -0.193868,0.2367348 -0.392052,0.4688284 -0.591981,0.7003704 -1.125327,1.187679 -1.125327,1.187679 -1.565771,2.683168 0.03585,0.293509 0.146305,0.511842 0.291513,0.766092 0.0853,0.152185 0.0853,0.152185 0.172992,0.416615 0.20547,0.0988 0.20547,0.0988 0.438472,0.176287 0.115754,0.05818 0.231508,0.116349 0.350775,0.176287 0.549835,0.02909 1.099676,0.05818 1.666177,0.08814 0,4.159491 0,8.318982 0,12.604517 -0.492695,-0.495225 -0.492695,-0.495225 -0.616254,-0.764369 -0.02755,-0.05819 -0.05509,-0.116378 -0.08347,-0.176329 -0.02773,-0.06043 -0.05547,-0.120866 -0.08403,-0.183131 -0.25719,-0.537761 -0.528022,-1.039636 -0.882419,-1.520475 -0.09225,-0.13362 -0.09225,-0.13362 -0.186352,-0.269939 -0.157062,-0.197401 -0.267759,-0.265771 -0.503324,-0.368929 -0.322401,-0.153258 -0.593549,-0.300394 -0.801121,-0.59514 -0.198824,-0.626481 -0.121943,-1.329724 -0.102765,-1.97772 0.002,-0.194631 0.0035,-0.389269 0.0045,-0.583907 0.004,-0.510792 0.01433,-1.021263 0.026,-1.531929 0.01079,-0.521684 0.01558,-1.043422 0.02087,-1.565187 0.01127,-1.022628 0.02922,-2.045018 0.05139,-3.067462 -0.05038,0.0014 -0.100753,0.0028 -0.152651,0.0042 -0.23046,0.0055 -0.460906,0.0089 -0.691404,0.01233 -0.07924,0.0022 -0.158483,0.0044 -0.240128,0.0067 -0.561972,0.0062 -0.951718,-0.05931 -1.378441,-0.458449 -0.05008,-0.05999 -0.100157,-0.119986 -0.151748,-0.181796 -0.05121,-0.05999 -0.102415,-0.119985 -0.155182,-0.181796 -0.315366,-0.433126 -0.255613,-0.981739 -0.212037,-1.49293 0.145439,-0.586849 0.595849,-0.996096 0.996489,-1.424068 0.215049,-0.233127 0.416256,-0.471632 0.614881,-0.71892 0.304189,-0.3786684 0.615553,-0.7498247 0.931747,-1.1183208 0.31173,-0.3635493 0.619819,-0.7291304 0.920791,-1.1017935 0.384259,-0.4754733 0.780571,-0.9398033 1.178041,-1.4040981 0.264689,-0.3093026 0.52749,-0.6201814 0.789587,-0.931704 0.05738,-0.068152 0.05738,-0.068152 0.115914,-0.1376812 0.224125,-0.2661881 0.224125,-0.2661881 0.443861,-0.5360268 0.30984,-0.3853062 0.583906,-0.5620909 1.051595,-0.7145517 0.780174,-0.072946 1.279961,0.1520929 1.883366,0.6314653 z"
					 fill="#528fd8"
					 id="path46"
					 style="stroke-width:0.0879182" />
				  <path
					 d="m 4.7403581,32.814161 c 0.070821,-7.05e-4 0.1416425,-0.0014 0.21461,-0.0021 0.7062335,0.0023 1.2990923,0.169855 1.9393693,0.46482 -0.2095835,0.228213 -0.4284916,0.411074 -0.679627,0.589459 -0.6984836,0.521647 -1.18845,1.155905 -1.5127186,1.966702 -0.034156,0.08534 -0.034156,0.08534 -0.069003,0.172413 -0.189942,0.514323 -0.2387503,0.977997 -0.2324444,1.524348 6.679e-4,0.08105 0.00133,0.162097 0.00202,0.245602 0.030265,1.264227 0.4981459,2.340911 1.3503906,3.270153 0.5426274,0.504186 1.2066273,0.817612 1.8648637,1.139323 0.875717,0.432395 1.4774392,0.879408 1.8196473,1.845504 0.043405,0.479941 0.043405,0.479941 0.087689,0.969578 -1.4180048,0 -2.8360138,0 -4.2969926,0 C 5.1413454,44.621865 5.0545287,44.243729 4.9650809,43.854135 4.504356,43.022693 3.9552378,42.553989 3.090618,42.184914 2.5115422,41.922768 2.0087244,41.654771 1.5450148,41.209827 1.4777538,41.145744 1.4104937,41.081661 1.3411951,41.015635 0.358691,40.00361 0.01675189,38.803387 0.02234059,37.424865 0.04380939,36.321369 0.3726624,35.486958 1.0188516,34.599066 c 0.06223,-0.08692 0.06223,-0.08692 0.1257175,-0.175598 0.5494743,-0.704055 1.3192708,-1.136412 2.1543216,-1.410984 0.092922,-0.0317 0.1858424,-0.0634 0.2815794,-0.09606 0.3951729,-0.09541 0.7554104,-0.106135 1.159888,-0.10226 z"
					 fill="#685e68"
					 id="path48"
					 style="stroke-width:0.0879182" />
				  <path
					 d="m 34.49888,36.367165 c 0.531236,0.481196 0.990377,1.008679 1.422113,1.581353 0.07478,0.09493 0.07478,0.09493 0.151064,0.191781 0.956014,1.278947 1.585552,2.75607 1.953587,4.303537 0.02317,0.09658 0.04635,0.193158 0.07022,0.292663 0.152683,0.76629 0.105167,1.459567 0.105167,2.263497 -1.389069,0 -2.778143,0 -4.209307,0 -0.130224,-1.003513 -0.130224,-1.003513 -0.263076,-2.027299 -0.575337,-2.998522 -2.214054,-5.684644 -4.725877,-7.433319 -1.354083,-0.903714 -2.807349,-1.488684 -4.394285,-1.821746 0.02894,-0.05818 0.05788,-0.11635 0.0877,-0.176287 3.437904,-0.76605 7.220228,0.556194 9.802698,2.82582 z"
					 fill="#685e68"
					 id="path50"
					 style="stroke-width:0.0879182" />
				  <path
					 d="m 17.242204,22.787841 c -0.108113,0.326002 -0.211043,0.367489 -0.493274,0.550896 -1.013539,0.69657 -1.616239,1.609686 -1.923101,2.813016 -0.173636,1.111477 -0.08045,2.222814 0.393598,3.247537 0.320372,0.711818 0.305665,1.686994 0.04967,2.417748 -0.427012,0.886721 -1.076491,1.363819 -1.973115,1.724307 -0.810449,0.245435 -1.509215,0.0015 -2.280036,-0.264431 -0.155278,-0.05145 -0.31057,-0.102873 -0.465872,-0.154251 -0.106717,-0.03636 -0.213426,-0.07272 -0.323376,-0.110179 0.0524,-0.291588 0.149569,-0.448526 0.339818,-0.672094 0.441546,-0.577165 0.598008,-1.237759 0.580629,-1.961193 -0.07538,-0.477291 -0.263322,-0.912021 -0.440866,-1.358993 -0.476534,-1.208913 -0.464479,-2.567346 0.04658,-3.764346 0.586605,-1.225818 1.544534,-2.147458 2.798671,-2.641205 1.232109,-0.415422 2.493428,-0.347072 3.690671,0.173188 z"
					 fill="#685e68"
					 id="path52"
					 style="stroke-width:0.0879182" />
				  <path
					 d="m 31.887075,22.787841 c -0.10419,0.314171 -0.170153,0.336574 -0.443947,0.501316 -0.906833,0.590291 -1.355581,1.307433 -1.804917,2.278302 -0.402634,0.861501 -0.825573,1.506029 -1.701123,1.919186 -0.805657,0.286093 -1.754109,0.26227 -2.527708,-0.09572 -0.270362,-0.155783 -0.487079,-0.333172 -0.713198,-0.548487 0.143047,-0.370565 0.310768,-0.720732 0.490194,-1.074593 0.0841,-0.168357 0.165543,-0.338074 0.244587,-0.50889 0.612843,-1.30415 1.428383,-2.121209 2.771604,-2.647403 1.231674,-0.410527 2.488503,-0.343436 3.684508,0.176287 z"
					 fill="#685e68"
					 id="path54"
					 style="stroke-width:0.0879182" />
				  <path
					 d="m 20.837651,4.1014237 c 0.05788,0.029087 0.115762,0.058175 0.175389,0.088143 -0.347263,0.3490482 -0.694532,0.6980964 -1.052326,1.0577217 0,-0.3458969 0.111019,-0.4208439 0.339817,-0.672094 0.09462,-0.105328 0.09462,-0.105328 0.191141,-0.2127839 0.170591,-0.1728438 0.170591,-0.1728438 0.345979,-0.2609868 z"
					 fill="#629dde"
					 id="path56"
					 style="stroke-width:0.0879182" />
				  <path
					 d="m 6.5435622,10.183323 c 0.057878,0.02909 0.1157558,0.05818 0.1753876,0.08814 -0.3183286,0.319961 -0.6366571,0.639922 -0.9646319,0.969578 0,-0.310617 0.048332,-0.353899 0.2466387,-0.578441 0.068674,-0.07925 0.068674,-0.07925 0.1387342,-0.160105 0.1407898,-0.142889 0.1407898,-0.142889 0.4038714,-0.319176 z"
					 fill="#a7e83d"
					 id="path58"
					 style="stroke-width:0.0879182" />
				</svg>
                <h3 class="fs-3 py-3">Обновления и модернизация</h3>
                <!-- Описание услуг по обновлениям и модернизации CS-Cart -->
                <p>Проведение обновлений и модернизации CS-Cart для обеспечения совместимости и безопасности.</p>
            </div>
        </div>
    </div>
</section>
<section id="portfolio">
    <div class="container p-5">
        <div class="row mb-4 text-center">
            <h2 class="fs-2 col-12 text-white">Портфолио выполненных проектов</h2>
            <p class="col-12 text-white">Примеры реализованных проектов с использованием CS-Cart. Разработка индивидуальных модулей, доработка платформы, интеграция с внешними сервисами.</p>
        </div>
        <div class="row">
			<?=$portfolio_cards?>
        </div>
    </div>
</section>
<section id="links" class="text-center">
    <div class="container p-5">
        <div class="row mb-4">
            <h2 class="fs-2 col-12 text-white">Ссылки где Вы можете ознакомиться с моим кодом</h2>
        </div>
        <div class="row">
			<div class="col-md-6 mb-4">
				<a href="https://github.com/evgeniyefimchenko?tab=repositories" target="_BLANK"><svg width="98" height="96" xmlns="http://www.w3.org/2000/svg" aria-label="GITHUB">
				<path fill-rule="evenodd" clip-rule="evenodd" d="M48.854 0C21.839 0 0 22 0 49.217c0 21.756 13.993 40.172 33.405 46.69 2.427.49 3.316-1.059 3.316-2.362 0-1.141-.08-5.052-.08-9.127-13.59 2.934-16.42-5.867-16.42-5.867-2.184-5.704-5.42-7.17-5.42-7.17-4.448-3.015.324-3.015.324-3.015 4.934.326 7.523 5.052 7.523 5.052 4.367 7.496 11.404 5.378 14.235 4.074.404-3.178 1.699-5.378 3.074-6.6-10.839-1.141-22.243-5.378-22.243-24.283 0-5.378 1.94-9.778 5.014-13.2-.485-1.222-2.184-6.275.486-13.038 0 0 4.125-1.304 13.426 5.052a46.97 46.97 0 0 1 12.214-1.63c4.125 0 8.33.571 12.213 1.63 9.302-6.356 13.427-5.052 13.427-5.052 2.67 6.763.97 11.816.485 13.038 3.155 3.422 5.015 7.822 5.015 13.2 0 18.905-11.404 23.06-22.324 24.283 1.78 1.548 3.316 4.481 3.316 9.126 0 6.6-.08 11.897-.08 13.526 0 1.304.89 2.853 3.316 2.364 19.412-6.52 33.405-24.935 33.405-46.691C97.707 22 75.788 0 48.854 0z" fill="#fff"/></svg></a>
				&nbsp;GITHUB
			</div>
			<div class="col-md-6 mb-4">
				<a href="https://marketplace.cs-cart.com/?q=efimchenko.com&dispatch=products.search" target="_BLANK" aria-label="Маркетплейс модулей CS-Cart">
					<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="98" height="98">
					<path d="M0 0 C17.82 0 35.64 0 54 0 C54 0.66 54 1.32 54 2 C56.64 2.33 59.28 2.66 62 3 C62 3.66 62 4.32 62 5 C62.99 5.33 63.98 5.66 65 6 C66.39166753 8.78333506 66.15813426 11.09207532 66.19287109 14.20629883 C66.20893402 15.51137894 66.22499695 16.81645905 66.24154663 18.16108704 C66.25303344 19.59306183 66.26364209 21.02504389 66.2734375 22.45703125 C66.27876118 23.17710546 66.28408485 23.89717968 66.28956985 24.63907433 C66.31629677 28.45085969 66.33567256 32.26261236 66.35009766 36.07446289 C66.36673983 40.00901814 66.41151369 43.94273481 66.4624691 47.87697315 C66.49614872 50.90448452 66.50767574 53.93179704 66.51332474 56.95947838 C66.52003597 58.40956718 66.53525509 59.85964296 66.55921555 61.30954933 C66.5908741 63.34130823 66.58790166 65.37351682 66.58349609 67.40551758 C66.59158295 68.56086502 66.5996698 69.71621246 66.60800171 70.90657043 C65.79275675 75.05442534 64.37342746 76.50425212 61 79 C57.92675781 79.61473083 57.92675781 79.61473083 54.453125 79.59936523 C53.14827148 79.60544296 51.84341797 79.61152069 50.49902344 79.61778259 C49.08267106 79.59887669 47.66632973 79.57912658 46.25 79.55859375 C44.78939232 79.55434002 43.32877786 79.55201467 41.86816406 79.55152893 C38.81153578 79.54537253 35.75564292 79.52271905 32.69921875 79.48706055 C28.78853348 79.44178857 24.87851347 79.42703389 20.96759033 79.42332363 C17.95289598 79.4188867 14.93834012 79.40356823 11.92370605 79.3844471 C10.4820672 79.37559093 9.04041372 79.36884836 7.59875488 79.36428642 C5.58138974 79.35520512 3.56410918 79.33111215 1.546875 79.30639648 C0.40057617 79.29575668 -0.74572266 79.28511688 -1.92675781 79.27415466 C-5.05117088 78.9954352 -7.25199223 78.50919963 -10 77 C-10.8125 73.875 -10.8125 73.875 -11 71 C-11.66 71 -12.32 71 -13 71 C-13 51.2 -13 31.4 -13 11 C-12.34 11 -11.68 11 -11 11 C-10.67 9.35 -10.34 7.7 -10 6 C-9.01 6 -8.02 6 -7 6 C-7 5.01 -7 4.02 -7 3 C-3.535 2.505 -3.535 2.505 0 2 C0 1.34 0 0.68 0 0 Z " fill="#74A3FE" transform="translate(32,19)"/>
					<path d="M0 0 C0.67224609 -0.00322266 1.34449219 -0.00644531 2.03710938 -0.00976562 C5.70708778 0.03032351 7.30992617 0.22745078 10.4375 2.3125 C10.4375 3.3025 10.4375 4.2925 10.4375 5.3125 C11.0975 5.3125 11.7575 5.3125 12.4375 5.3125 C12.4375 9.9325 12.4375 14.5525 12.4375 19.3125 C11.7775 19.3125 11.1175 19.3125 10.4375 19.3125 C10.4375 20.3025 10.4375 21.2925 10.4375 22.3125 C9.4475 22.3125 8.4575 22.3125 7.4375 22.3125 C7.4375 22.9725 7.4375 23.6325 7.4375 24.3125 C2.4875 24.3125 -2.4625 24.3125 -7.5625 24.3125 C-7.5625 23.6525 -7.5625 22.9925 -7.5625 22.3125 C-8.8825 21.9825 -10.2025 21.6525 -11.5625 21.3125 C-11.670334 18.37402347 -11.74971916 15.43948849 -11.8125 12.5 C-11.84601562 11.66662109 -11.87953125 10.83324219 -11.9140625 9.97460938 C-12.01147981 3.91038197 -12.01147981 3.91038197 -10.01953125 1.2578125 C-6.59357493 -0.06028198 -3.66299289 -0.0465483 0 0 Z " fill="#73A3FE" transform="translate(11.5625,-0.3125)"/>
					<path d="M0 0 C4.62 0 9.24 0 14 0 C14 0.66 14 1.32 14 2 C8.51053913 3.12029814 5.16569441 2.79217969 0 1 C0 0.67 0 0.34 0 0 Z " fill="#7CD1FF" transform="translate(72,19)"/>
					<path d="" fill="#74A4FF" transform="translate(0,0)"/>
					<path d="" fill="#727FFC" transform="translate(0,0)"/>
					</svg>					
				</a>
				&nbsp;ADDONS MARKETPLACE
			</div>
        </div>
    </div>
</section>
<section id="contacts">
    <div class="container p-5">
        <div class="row mb-4">
            <h2 class="fs-2 col-12 text-white text-center">Мои контакты</h2>
        </div>
        <div class="row">
            <div class="col-12 text-center">
                <ul class="list-inline">
                    <li class="list-inline-item mx-3">
                        <a href="https://t.me/clean_code" target="_blank" aria-label="Связаться со мной в Telegram">
                            <i class="fa-brands fa-telegram fa-2x"></i>
                        </a>
                    </li>
                    <li class="list-inline-item mx-3">
                        <a href="https://vk.com/id113807047" target="_blank" aria-label="Связаться со мной в VK">
                            <i class="fa-brands fa-vk fa-2x"></i>
                        </a>
                    </li>
                    <li class="list-inline-item mx-3">
                        <a href="https://github.com/evgeniyefimchenko" target="_blank" aria-label="Связаться со мной в GitHub">
                            <i class="fa-brands fa-github fa-2x"></i>
                        </a>
                    </li>
                    <li class="list-inline-item mx-3">
                        <a href="mailto:?subject=Евгению с сайта&body=Евгений хотел Вас спросить: &cc=<?=ENV_ADMIN_EMAIL?>&to=" target="_blank" aria-label="Связаться со мной по электронной почте">
                            <i class="fa-solid fa-envelope fa-2x"></i>
                        </a>
                    </li>
                </ul>
                <p class="text-white mt-3">
                    ИНН 482108536226 Работаю как 
                    <a href="#" class="text-white text-decoration-underline" data-bs-toggle="collapse" data-bs-target="#priceInfo" aria-expanded="false" aria-controls="priceInfo">самозанятый</a><br/>
                    <div class="collapse my-3" id="priceInfo">
                        <div class="card card-body bg-dark text-white mx-auto" style="max-width: 500px;">
                            <h5 class="card-title">Стоимость услуг</h5>
                            <ul class="list-unstyled">
                                <li>Частные лица: 1200 ₽/час</li>
                                <li>Юридические лица и ИП: 1300 ₽/час</li>
                                <li>Консультация (до 1 часа): бесплатно</li>
                            </ul>
                            <a href="/uploads/files/contract.pdf" target="_blank" class="btn btn-outline-light btn-sm">Типовой договор</a>
                        </div>
                    </div>
                    © 2014 - <?= date("Y") ?>
                </p>
            </div>
        </div>
    </div>
</section>

<?=$callback_modal?>
<?=$busy?>