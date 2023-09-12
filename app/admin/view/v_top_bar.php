<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
$topbarData = [
    'brand' => [
        'url' => ENV_DOMEN_NAME,
        'name' => ENV_SITE_NAME
    ],
    'userMenu' => [
        [
            'title' => 'Настройки',
            'link' => '/admin/user_edit'
        ],
        [
            'title' => 'Сообщения',
            'link' => '/admin/messages'
        ],
        'divider', // Разделитель
        [
            'title' => 'Выход',
            'link' => '/exit_login'
        ]
    ]
];

foreach ($messages as $message) {
	if (!$message['date_read']) {
		$color = '#bcbebf';
		switch ($message['status']) { // 'primary', 'info', 'success', 'warning', 'danger'			
			case 'info' : $icon = 'fa-solid fa-circle-info'; $color='#61bdd1'; break;
			case 'primary' : $icon = 'fa-solid fa-envelope'; $color='#0d6efd'; break;
			case 'success' : $icon = 'fa-solid fa-check'; $color='#198754'; break;
			case 'warning' : $icon = 'fa-solid fa-triangle-exclamation'; $color='#ffc107'; break;
			case 'danger' : $icon = 'fa-solid fa-bolt'; $color='#dc3545'; break;
			default : $icon = 'fa-regular fa-circle-question';
		}
		$topbarData['notifications'][] = [
			'text' => SysClass::truncate_string($message['message_text'], 33),
			'url' => '/admin/messages',
			'icon' => $icon,
			'color' => $color
		];
	}
}

echo Plugins::generate_topbar($topbarData);
