<?php if (!defined('ENV_SITE')) exit(header("Location: http://" . $_SERVER['HTTP_HOST'], true, 301));
/**
 * Главное меню админ-панели
 * Настраивается опционально по данным массива
 */
$menuItems = [
    'menuItems' => [
        'headings' => [
            $lang['sys.basics'] => [
                [
                    'title' => $lang['sys.review'],
                    'link' => '/admin',
                    'icon' => 'fa-tachometer-alt',
                ],
            ],
            $lang['sys.entities'] => [
                [
                    'title' => $lang['sys.users'],
                    'link' => '#',
                    'icon' => 'fa-solid fa-users-gear',
                    'subItems' => [
                        ['title' => $lang['sys.list'], 'link' => '/admin/users', 'icon' => 'fa-sharp fa-solid fa-list'],
                        ['title' => $lang['sys.roles'], 'link' => '/admin/users_roles', 'icon' => 'fa-solid fa-users-between-lines'],
                        ['title' => $lang['sys.deleted_users'], 'link' => '/admin/deleted_users', 'icon' => 'fa-solid fa-trash',
                            'attributes' => 'data-bs-toggle="tooltip" data-bs-placement="top" title="' . $lang['sys.archive'] . '"'],
                    ],
                ],
            // ... другие пункты и подпункты
            ],
            $lang['sys.content'] => [
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
                ],
                [
                    'title' => $lang['sys.email_templates'],
                    'link' => '#',
                    'icon' => 'fa-solid fa-envelopes-bulk',
                    'subItems' => [
                        ['title' => $lang['sys.list'], 'link' => '/admin/email_templates', 'icon' => 'fa-sharp fa-solid fa-list'],
                        ['title' => $lang['sys.snippets'], 'link' => '/admin/email_snippets', 'icon' => 'fa-solid fa-file-fragment'],
                    ],                    
                ],
                [
                    'title' => $lang['sys.filters_management'] ?? 'Управление фильтрами',
                    'link' => '/admin/filters_panel',
                    'icon' => 'fa-solid fa-filter-circle-dollar',
                ],                
            ],
            $lang['sys.system'] => [
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
                'title' => '<div class="small">' . $lang['sys.welcome'] . ':</div><a href="/admin/user_edit" class="nav-link">' . $userData['name'] . '</a>',
                'link' => false,
            ],
        ]
    ],
    'footerTitle' => $lang['sys.project_development'],
];

echo classes\system\Plugins::generateVerticalMenu($menuItems);
