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
                    'title' => $lang['sys.review'],
                    'link' => '/admin',
                    'icon' => 'fa-tachometer-alt',
                ],
            ],
            'Сущности' => [
                [
                    'title' => $lang['sys.users'],
                    'link' => '#',
                    'icon' => 'fa-solid fa-users-gear',
                    'subItems' => [
                        ['title' => $lang['sys.list'], 'link' => '/admin/users', 'icon' => 'fa-sharp fa-solid fa-list'],
                        ['title' => $lang['sys.roles'], 'link' => '/admin/users_roles', 'icon' => 'fa-solid fa-users-between-lines'],
                        ['title' => $lang['sys.deleted_users'], 'link' => '/admin/deleted_users', 'icon' => 'fa-solid fa-trash',
                            'attributes' => 'data-bs-toggle="tooltip" data-bs-placement="top" title="Архив"'],
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
                    'title' => $lang['sys.pages'],
                    'link' => '#',
                    'icon' => 'fa-regular fa-file',
                    'subItems' => [
                        ['title' => $lang['sys.list'], 'link' => '/admin/pages', 'icon' => 'fa-sharp fa-solid fa-list'],
                    ],
                ],
                [
                    'title' => $lang['sys.properties'],
                    'link' => '#',
                    'icon' => 'fa-solid fa-gears',
                    'subItems' => [
                        ['title' => $lang['sys.list'], 'link' => '/admin/properties', 'icon' => 'fa-sharp fa-solid fa-list'],
                        ['title' => $lang['sys.types'], 'link' => '/admin/types_properties', 'icon' => 'fa-sharp fa-solid fa-marker'],
                        ['title' => $lang['sys.property_sets'], 'link' => '/admin/properties_sets', 'icon' => 'fa-sharp fa-solid fa-sliders'],
                    ],                    
                ],/*
                [
                    'title' => 'Комментарии',
                    'link' => '/admin/comments',
                    'icon' => 'fa-regular fa-comment',
                ],*/
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
                    'title' => $lang['sys.tools'],
                    'link' => '#',
                    'icon' => 'fa fa-wrench',
                    'subItems' => [
                        [
                            'title' => $lang['sys.logs'],
                            'link' => '/admin/logs',
                            'icon' => 'fa-solid fa-table-list',
                        ],
                        [
                            'title' => $lang['sys.backup'],
                            'link' => '/admin/backup',
                            'icon' => 'fa-regular fa-copy',
                        ],
                        [
                            'title' => $lang['sys.fill_with_test_data'],
                            'link' => '/admin/createTest',
                            'icon' => 'fa-solid fa-flask-vial',
                            'attributes' => 'onclick="return confirm(\'' . $lang['sys.fill_with_test_data'] . '?\');"',
                        ],
                        [
                            'title' => $lang['sys.delete_database_data'],
                            'link' => '/admin/killEmAll',
                            'icon' => 'fa-solid fa-book-skull',
                            'attributes' => 'onclick="return confirm(\'' . $lang['sys.kill_db'] . '\');"',
                        ],
                    ],
                ],
            ],
        // ... другие разделы
        ],
        'footer' => [
            [
                'title' => $lang['sys.order_a_project'],
                'link' => '/admin/upgrade',
            ],
            [
                'title' => '<div class="small">' . $lang['sys.welcome'] . ':</div><a href="/admin/user_edit" class="nav-link">' . $name . '</a>',
                'link' => false,
            ],
        ]
    ],
    'footerTitle' => $lang['sys.project_development'],
];

echo Plugins::generateVerticalMenu($menuItems);
