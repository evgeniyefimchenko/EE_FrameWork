<?php if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301));
$uri = parse_url(__REQUEST['_SERVER']['REQUEST_URI'])['path'];
// Подготовка данных для верхней панели. Включает в себя бренд и меню пользователя.
$topbarData = [
    'brand' => [
        'url' => ENV_URL_SITE,
        'name' => ENV_SITE_NAME
    ],
    'userMenu' => [// Меню пользователя с опциями
        [
            'title' => 'Настройки',
            'link' => '/admin/user_edit'
        ],
        [
            'title' => 'Сообщения',
            'link' => '/admin/messages'
        ],
        'divider',
        [
            'title' => 'Начать тур',
            'link' => 'javascript:void(0)',
            'meta' => 'onclick="$.cleanTour(\'' . $uri . '\'); location.reload();"'
        ],
        'divider',
        [
            'title' => 'Выход',
            'link' => '/exit_login' 
        ]
    ]
];

// Обработка сообщений для отображения в уведомлениях
if (isset($messages) && is_array($messages)) {
    foreach ($messages as $message) {
        if (!isset($message['date_read']) || !$message['date_read']) {
            $color = '#bcbebf';
            switch ($message['status']) {
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
                default : $icon = 'fa-regular fa-circle-question';
            }
            $topbarData['notifications'][] = [
                'text' => classes\system\SysClass::truncateString($message['message_text'], 33),
                'url' => '/admin/messages',
                'icon' => $icon,
                'color' => $color
            ];
        }
    }
}

echo classes\system\Plugins::generate_topbar($topbarData);
