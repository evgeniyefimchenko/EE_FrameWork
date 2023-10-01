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
                        ['title' => 'Список', 'link' => '/admin/users', 'icon' => 'fa-sharp fa-solid fa-list'],
                        ['title' => 'Роли', 'link' => '/admin/users_roles', 'icon' => 'fa-solid fa-users-between-lines'],
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
                        ['title' => 'Список', 'link' => '/admin/categories', 'icon' => 'fa-sharp fa-solid fa-list'],
                        ['title' => 'Типы', 'link' => '/admin/type_categories', 'icon' => 'fa-sharp fa-solid fa-marker'],
                    ],
                ],
                [
                    'title' => $lang['sys.entities'],
                    'link' => '#',
                    'icon' => 'fa-regular fa-file',
                    'subItems' => [
                        ['title' => 'Список', 'link' => '/admin/entities', 'icon' => 'fa-sharp fa-solid fa-list'],
                    ],
                ],
                [
                    'title' => $lang['properties'] = 'Свойства',
                    'link' => '#',
                    'icon' => 'fa-solid fa-gears',
                    'subItems' => [
                        ['title' => 'Список', 'link' => '/admin/properties', 'icon' => 'fa-sharp fa-solid fa-list'],
                        ['title' => 'Типы', 'link' => '/admin/types_properties', 'icon' => 'fa-sharp fa-solid fa-marker'],
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
