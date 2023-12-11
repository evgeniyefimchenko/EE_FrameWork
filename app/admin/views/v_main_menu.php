<?php
use classes\system\Plugins;

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
                        ['title' => $lang['sys.list'], 'link' => '/admin/users', 'icon' => 'fa-sharp fa-solid fa-list'],
                        ['title' => $lang['sys.roles'], 'link' => '/admin/users_roles', 'icon' => 'fa-solid fa-users-between-lines'],
                        ['title' => 'Удалённые(Архив)', 'link' => '/admin/deleted_users', 'icon' => 'fa-solid fa-trash'],
                    ],
                ],
            // ... другие пункты и подпункты
            ],
            'Контент' => [
                [
                    'title' => $lang['sys.categories'],
                    'link' => '#',
                    'icon' => 'fa-regular fa-folder',
                    'subItems' => [
                        ['title' => $lang['sys.list'], 'link' => '/admin/categories', 'icon' => 'fa-sharp fa-solid fa-list'],
                        ['title' => $lang['sys.types'], 'link' => '/admin/types_categories', 'icon' => 'fa-sharp fa-solid fa-marker'],
                    ],
                ],
                [
                    'title' => $lang['sys.entities'],
                    'link' => '#',
                    'icon' => 'fa-regular fa-file',
                    'subItems' => [
                        ['title' => $lang['sys.list'], 'link' => '/admin/entities', 'icon' => 'fa-sharp fa-solid fa-list'],
                    ],
                ],
                [
                    'title' => $lang['properties'] = 'Свойства',
                    'link' => '#',
                    'icon' => 'fa-solid fa-gears',
                    'subItems' => [
                        ['title' => $lang['sys.list'], 'link' => '/admin/properties', 'icon' => 'fa-sharp fa-solid fa-list'],
                        ['title' => $lang['sys.types'], 'link' => '/admin/types_properties', 'icon' => 'fa-sharp fa-solid fa-marker'],
                        ['title' => $lang['sys.property_sets'], 'link' => '/admin/properties_sets', 'icon' => 'fa-sharp fa-solid fa-sliders'],
                    ],                    
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
            'Система' => [
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
                            'title' => 'Удалить данные БД',
                            'link' => '/admin/kill_em_all',
                            'icon' => 'fa-solid fa-book-skull',
                            'attributes' => 'onclick="return confirm(\'Все таблицы БД будут удалены!\');"',
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
