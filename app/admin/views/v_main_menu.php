<?php

if (!defined('ENV_SITE'))
    exit(header('Location: /', true, 301));
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
                        ['title' => $lang['sys.property_lifecycle_jobs'] ?? 'Задачи жизненного цикла', 'link' => '/admin/property_lifecycle_jobs', 'icon' => 'fa-solid fa-list-check'],
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
                [
                    'title' => $lang['sys.imports_management'] ?? 'Import Management',
                    'link' => '#',
                    'icon' => 'fa-solid fa-upload',
                    'subItems' => [
                        [
                            'title' => $lang['sys.imports_profiles_list'] ?? 'Import Profiles',
                            'link' => '/admin/imports',
                            'icon' => 'fa-sharp fa-solid fa-list-check'
                        ],
                        [
                            'title' => $lang['sys.imports_new_profile_wp'] ?? 'Create WordPress Profile',
                            'link' => '/admin/edit_import_wp/id/0',
                            'icon' => 'fa-brands fa-wordpress'
                        ],
                        [
                            'title' => $lang['sys.imports_property_definitions'] ?? 'Import property types, properties, and sets',
                            'link' => '/admin/import_property_definitions',
                            'icon' => 'fa-solid fa-diagram-project'
                        ],
                    ]
                ],
            ],
            $lang['sys.system'] => [
                [
                    'title' => $lang['sys.tools'],
                    'link' => '#',
                    'icon' => 'fa fa-wrench',
                    'subItems' => [
                        [
                            'title' => $lang['sys.health'] ?? 'Состояние системы',
                            'link' => '/admin/health',
                            'icon' => 'fa-solid fa-heart-pulse',
                        ],
                        [
                            'title' => $lang['sys.logs'],
                            'link' => '/admin/system_logs',
                            'icon' => 'fa-solid fa-table-list',
                        ],
                        [
                            'title' => $lang['sys.cron_agents'] ?? 'Cron-агенты',
                            'link' => '/admin/cron_agents',
                            'icon' => 'fa-solid fa-clock-rotate-left',
                        ],
                        [
                            'title' => $lang['sys.clear_html_cache'] ?? 'Очистить HTML-кэш',
                            'link' => '/admin/clear_html_cache',
                            'icon' => 'fa-solid fa-broom',
                            'attributes' => 'onclick="return confirm(\'' . ($lang['sys.clear_html_cache'] ?? 'Очистить HTML-кэш?') . '\');"',
                        ],
                        [
                            'title' => $lang['sys.clear_route_cache'] ?? 'Очистить route-кэш',
                            'link' => '/admin/clear_route_cache',
                            'icon' => 'fa-solid fa-route',
                            'attributes' => 'onclick="return confirm(\'' . ($lang['sys.clear_route_cache'] ?? 'Очистить route-кэш?') . '\');"',
                        ],
                        [
                            'title' => $lang['sys.reset_redis_probe'] ?? 'Сбросить проверку Redis',
                            'link' => '/admin/reset_redis_cache_probe',
                            'icon' => 'fa-solid fa-arrows-rotate',
                        ],
                        [
                            'title' => $lang['sys.backup'],
                            'link' => '/admin/backup',
                            'icon' => 'fa-regular fa-copy',
                        ],
                        [
                            'title' => $lang['sys.url_management'] ?? 'URL и редиректы',
                            'link' => '#',
                            'icon' => 'fa-solid fa-route',
                            'subItems' => [
                                [
                                    'title' => $lang['sys.url_policies'] ?? 'URL-политики',
                                    'link' => '/admin/url_policies',
                                    'icon' => 'fa-solid fa-wand-magic-sparkles',
                                ],
                                [
                                    'title' => $lang['sys.redirects'] ?? 'Редиректы',
                                    'link' => '/admin/redirects',
                                    'icon' => 'fa-solid fa-signs-post',
                                ],
                            ],
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
    'showFooter' => defined('ENV_SHOW_SIDENAV_FOOTER') ? (bool) ENV_SHOW_SIDENAV_FOOTER : true,
];

echo classes\system\Plugins::generateVerticalMenu($menuItems);
