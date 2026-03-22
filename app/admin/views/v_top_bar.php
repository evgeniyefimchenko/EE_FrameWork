<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301));
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

// Обработка уведомлений для отображения в верхней панели
if (isset($notifications) && is_array($notifications)) {
    $nowMs = (int) round(microtime(true) * 1000);
    foreach ($notifications as $notification) {
        $status = (string) ($notification['status'] ?? 'info');
        $showtime = max(0, (int) ($notification['showtime'] ?? 0));
        if ($status === 'primary' && $showtime > $nowMs) {
            continue;
        }
        $topbarData['notifications'][] = [
            'text' => classes\system\SysClass::truncateString(strip_tags((string) ($notification['text'] ?? '')), 33),
            'url' => (string) ($notification['url'] ?? '/admin/messages'),
            'icon' => (string) ($notification['icon'] ?? 'fa-regular fa-circle-question'),
            'color' => (string) ($notification['color'] ?? '#bcbebf')
        ];
    }
}

echo classes\system\Plugins::generate_topbar($topbarData);
