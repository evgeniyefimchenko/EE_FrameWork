<?php if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301));
// Разбор текущего URI для получения пути без параметров запроса.
$uri = parse_url(__REQUEST['_SERVER']['REQUEST_URI'])['path'];
// Подготовка данных для верхней панели. Включает в себя бренд и меню пользователя.
$topbarData = [
    'brand' => [
        'url' => ENV_URL_SITE, // Ссылка на главную страницу
        'name' => ENV_SITE_NAME // Название сайта
    ],
    'userMenu' => [// Меню пользователя с опциями
        [
            'title' => 'Настройки', // Название пункта меню
            'link' => '/admin/user_edit' // Ссылка на страницу
        ],
        [
            'title' => 'Сообщения', // Название второго пункта меню
            'link' => '/admin/messages' // Ссылка на страницу сообщений
        ],
        'divider', // Разделитель для визуального отделения частей меню
        [
            'title' => 'Начать тур', // Пункт для начала интерактивного тура по сайту
            'link' => 'javascript:void(0)', // Псевдо-ссылка для выполнения JavaScript действия
            'meta' => 'onclick="$.cleanTour(\'' . $uri . '\'); location.reload();"' // Действие по клику: очистка данных тура и перезагрузка страницы
        ],
        'divider',
        [
            'title' => 'Выход', // Пункт для выхода из системы
            'link' => '/exit_login' // Ссылка для выхода
        ]
    ]
];

// Обработка сообщений для отображения в уведомлениях
if (isset($messages) && is_array($messages)) {
    foreach ($messages as $message) {
        if (!isset($message['date_read']) || !$message['date_read']) { // Если сообщение не прочитано
            $color = '#bcbebf'; // Цвет иконки по умолчанию
            switch ($message['status']) { // Определение цвета иконки в зависимости от статуса сообщения
                case 'info' : $icon = 'fa-solid fa-circle-info';
                    $color = '#61bdd1';
                    break;
                case 'primary' : $icon = 'fa-solid fa-envelope';
                    $color = '#0d6efd';
                    break;
                case 'success' : $icon = 'fa-solid fa-check';
                    $color = '#198754';
                    break;
                case 'warning' : $icon = 'fa-solid fa-triangle-exclamation';
                    $color = '#ffc107';
                    break;
                case 'danger' : $icon = 'fa-solid fa-bolt';
                    $color = '#dc3545';
                    break;
                default : $icon = 'fa-regular fa-circle-question'; // Иконка по умолчанию, если статус не определен
            }
            // Добавление уведомления в данные верхней панели
            $topbarData['notifications'][] = [
                'text' => classes\system\SysClass::truncateString($message['message_text'], 33), // Текст уведомления с обрезкой до 33 символов
                'url' => '/admin/messages', // Ссылка на страницу уведомлений
                'icon' => $icon, // Иконка уведомления
                'color' => $color // Цвет иконки
            ];
        }
    }
}
// Генерация HTML верхней панели с использованием подготовленных данных
echo classes\system\Plugins::generate_topbar($topbarData);
