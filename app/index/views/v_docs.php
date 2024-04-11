<div class="container mt-3">
    <a href="<?= ENV_URL_SITE ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $lang['sys.general'] ?>" type="button"
       class="btn btn-info mx-1 float-end">
        <i class="fa-solid fa-house"></i>
    </a>
    <div class="row">
        <h1 class="mb-4">Документация проекта EE_FrameWork</h1>
        <div class="col-md-3">
            <?= $menu_docs ?> <!-- Включаем файл бокового меню -->
        </div>
        <div class="col-md-9">                
            <div id="docs-content">
                <p>EE_FrameWork - это платформа для разработчиков среднего уровня, ориентированная как на создание сложных экспертных систем, так и на разработку простых веб-сайтов. Платформа дает широкие возможности для настройки и адаптации под конкретные требования проекта. Главный принцип работы с EE_FrameWork - "установи и развивай".</p>
                <p>Обратите внимание, что обновления платформы не предполагают автоматическую интеграцию с уже запущенными проектами без дополнительной разработки.</p>
                <p>Основные особенности:</p>
                <ul>
                    <li>Код с комментариями на русском языке для удобства восприятия и разработки.</li>
                    <li>Отсутствие предустановленного пользовательского интерфейса (фронтенда), что дает полный контроль над внешним видом сайта.</li>
                    <li>Готовый к использованию бэкенд (административная панель) с модулями сообщений, оповещений, таблиц и графиков.</li>
                    <li>Система регистрации и управления пользователями с ролями и разграничением доступа.</li>
                    <li>Проверенная на практике архитектура MVC для гибкой работы с контроллерами, представлениями и моделями.</li>
                    <li>Набор вспомогательных функций и классов в системной библиотеке.</li>
                </ul>
            </div>
        </div>
    </div>
</div>