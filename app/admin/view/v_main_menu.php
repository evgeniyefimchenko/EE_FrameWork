<?php
if (ENV_SITE !== 1) {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: http://" . $_SERVER['HTTP_HOST']);
    exit();
}
/**
* Главное меню админ-панели
* Настраивается опционально по данным массива
*/

$menuItems = [
    'menuItems' => [
		'headings' => [
			'Основное' => [
				[
					'title' => 'Обзор',
					'link' => '/admin',
					'icon' => 'fa-tachometer-alt',
				],
			],
			'Сущности' => [
				[
					'title' => 'Пользователи',
					'link' => '#',
					'icon' => 'fa-solid fa-users-gear',
					'subItems' => [
						['title' => 'Список', 'link' => '/admin/users'],
						['title' => 'Роли', 'link' => '/admin/roles', 'icon' => 'fa-solid fa-users-between-lines'],
						['title' => 'Удалённые(Архив)', 'link' => '/admin/deleted'],
					],
				],
				// ... другие пункты и подпункты
			],
			'Контент' => [
				[
					'title' => 'Категории',
					'link' => '#',
					'icon' => 'fa-regular fa-folder',
					'subItems' => [
						['title' => 'Список', 'link' => '/admin/categories'],
						['title' => 'Типы', 'link' => '/admin/type_categories'],
					],
				],
				[
					'title' => 'Страницы',
					'link' => '#',
					'icon' => 'fa-regular fa-file',
					'subItems' => [
						['title' => 'Список', 'link' => '/admin/pages'],
					],
				],
				[
					'title' => 'Свойства',
					'link' => '/admin/features',
					'icon' => 'fa-solid fa-gears',
				],
				[
					'title' => 'Комментарии',
					'link' => '/admin/comments',
					'icon' => 'fa-regular fa-comment',
				],
			],
			'Аналитика' => [
				[
					'title' => '.....',
					'link' => '/admin/#',
					'icon' => 'fa-solid fa-question',
				],			
			],
			'Системные' => [
				[
					'title' => 'Разделы',
					'link' => '#',
					'icon' => 'fa-regular fa-file',
					'subItems' => [
						[
							'title' => 'Логи',
							'link' => '/admin/logs',
							'icon' => 'fa-solid fa-table-list',
						],
						[
							'title' => 'Резервное копировани',
							'link' => '/admin/backup',
							'icon' => 'fa-regular fa-copy',
						],
						[
							'title' => 'Заполнить тестовыми данными',
							'link' => '/admin/create_test',
							'icon' => 'fa-solid fa-flask-vial',
						],
						[
							'title' => 'Удалить тестовые данные',
							'link' => '/admin/kill_test',
							'icon' => 'fa-solid fa-book-skull',
						],
					],
				],
			],			
			// ... другие разделы
		],
		'footer' => [
			[
				'title' => 'Доработка',
				'link' => '/admin/upgrade',
			],
			[
				'title' => '<div class="small">Добро пожаловать:</div><a href="/admin/user_edit" class="nav-link">' . $name . '</a>',
				'link' => false,
			],
		]
	],
	'footerTitle' => 'Развитие проекта',
];

echo Plugins::generate_vertical_menu($menuItems);
