<?php

namespace app\admin;

use classes\system\SysClass;
use classes\system\Constants;
use classes\helpers\ClassNotifications;
use classes\system\Plugins;
use classes\system\CacheManager;
use classes\system\Router;

/**
 * Функции работы с логами
 */
trait SystemsTrait {

    /**
     * Отображает панель управления фильтрами
     * @param array $params
     * @return void
     */
    public function filters_panel($params = []) {
        if (!$this->requireAccess([Constants::ADMIN, Constants::MODERATOR], [
            'return' => 'admin/filters_panel',
            'initiator' => __METHOD__,
        ])) {
            return;
        }
        $this->loadModel('m_categories');
        $this->loadModel('m_filters');
        $categoriesData = $this->models['m_categories']->getCategoriesData('title ASC', 'parent_id IS NULL OR parent_id = 0', 0, 1000, ENV_DEF_LANG);
        $rootCategories = array_values(array_filter(
            $categoriesData['data'] ?? [],
            static fn($category): bool => is_array($category) && !empty($category['category_id'])
        ));
        $existingFilters = $this->models['m_filters']->getExistingFiltersSummary(ENV_DEF_LANG);
        $this->view->set('categories', $rootCategories);
        $this->view->set('existingFilters', $existingFilters); // Новые данные
        $this->view->set('page_title', $this->lang['sys.filters_management'] ?? 'Управление фильтрами');
        $this->getStandardViews();
        $this->view->set('is_full_page_load', true);
        $this->view->set('body_view', $this->view->read('v_filters'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = $this->lang['sys.filters_management'] ?? 'Управление фильтрами';
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/filters_panel.js" type="text/javascript"></script>';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * AJAX
     * Запускает пересчет фильтров для указанной сущности и всех её потомков (ОТЛАДОЧНАЯ ВЕРСИЯ)
     * @param array $params
     * @return void
     */
    public function regenerate_filters($params = []) {
        $is_ajax = SysClass::isAjaxRequestFromSameSite();
        if ($is_ajax && !empty($_POST)) {
            if (!$this->requireAccess([Constants::ADMIN, Constants::MODERATOR], [
                'ajax' => true,
                'initiator' => __METHOD__,
                'ajax_message' => 'Access Denied',
            ])) {
                die;
            }             

            $postData = SysClass::ee_cleanArray($_POST);
            $startEntityId = (int) ($postData['entity_id'] ?? 0);

            if (!$startEntityId) {
                echo json_encode(['status' => 'error', 'message' => 'Не указан ID стартовой категории']);
                die;
            }

            if (empty($this->models['m_categories'])) {
                $this->loadModel('m_categories');
            }
            $descendants = $this->models['m_categories']->getCategoryDescendantsShort($startEntityId, ENV_DEF_LANG);

            $categoryIdsToProcess = [];
            if (!empty($descendants)) {
                foreach ($descendants as $category) {
                    $categoryIdsToProcess[] = (int) $category['category_id'];
                }
            }

            $filterService = new \classes\helpers\FilterService();
            $processedCount = 0;

            foreach ($categoryIdsToProcess as $categoryId) {
                $filterService->regenerateFiltersForEntity('category', $categoryId, ENV_DEF_LANG);
                $processedCount++;
            }

            $message = 'Фильтры для ' . $processedCount . ' категорий (ID ' . $startEntityId . ' и дочерние) успешно пересчитаны.';
            echo json_encode(['status' => 'success', 'message' => $message]);
        }
        die;
    }

    /**
     * AJAX
     * Отдает HTML-код ТОЛЬКО таблицы с существующими фильтрами
     * @param array $params
     * @return void
     */
    public function get_filters_table($params = []) {
        if (SysClass::isAjaxRequestFromSameSite()) {
            if (!$this->requireAccess([Constants::ADMIN, Constants::MODERATOR], [
                'ajax' => true,
                'initiator' => __METHOD__,
                'ajax_message' => 'Access Denied',
            ])) {
                die;
            }
            if (empty($this->models['m_filters'])) {
                $this->loadModel('m_filters');
            }            
            $this->view->set('existingFilters', $this->models['m_filters']->getExistingFiltersSummary(ENV_DEF_LANG));
            $this->view->set('is_full_page_load', false);
            echo $this->view->read('v_filters');
        }
        die;
    }

    /**
     * AJAX
     * Отдает JSON с деталями фильтров для указанной категории
     * @param array $params
     * @return void
     */
    public function get_filter_details($params = []) {
        if (SysClass::isAjaxRequestFromSameSite()) {
            if (!$this->requireAccess([Constants::ADMIN, Constants::MODERATOR], [
                'ajax' => true,
                'initiator' => __METHOD__,
                'ajax_message' => 'Access Denied',
            ])) {
                die;
            }
            $postData = SysClass::ee_cleanArray($_POST);
            $entityId = (int) ($postData['entity_id'] ?? 0);

            if ($entityId > 0) {
                if (empty($this->models['m_filters'])) {
                    $this->loadModel('m_filters');
                }
                $filterDetails = $this->models['m_filters']->getFiltersForEntity($entityId, ENV_DEF_LANG);
                echo json_encode(['status' => 'success', 'data' => $filterDetails]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Неверный ID категории']);
            }
        }
        die;
    }

    /**
     * Вывод страницы с логами
     */
    public function system_logs($params = array()) {
        $this->logs($params);
    }

    public function logs($params = array()) {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/system_logs',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }
        /* get data */
        $this->loadModel('m_systems');
        $logs_summary = $this->models['m_systems']->get_logs_summary();
        $fatal_errors_table = $this->get_php_logs_table('fatal_errors');
        $php_logs_table = $this->get_php_logs_table('php_logs');
        $project_logs = $this->get_project_logs_table();
        /* view */
        $this->getStandardViews();
        $this->view->set('logs_summary', $logs_summary);
        $this->view->set('php_logs_table', $php_logs_table);
        $this->view->set('fatal_errors_table', $fatal_errors_table);
        $this->view->set('project_logs_table', $project_logs);
        $this->view->set('cache_probe_exists', is_file(ENV_CACHE_PATH . 'redis_connection_check.cache'));
        $this->view->set('body_view', $this->view->read('v_logs'));
        $this->html = $this->view->read('v_dashboard');
        /* layouts */
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = $this->lang['sys.logs'];
        $this->showLayout($this->parameters_layout);
    }

    public function health($params = []) {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/health',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }

        $this->renderSystemHealthDashboard('health');
    }

    public function backup($params = []) {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/backup',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }

        $this->loadModel('m_backups');
        $this->getStandardViews();
        $this->view->set('backup_summary', $this->models['m_backups']->getSummary());
        $this->view->set('backup_plans', $this->models['m_backups']->getPlans());
        $this->view->set('backup_targets', $this->models['m_backups']->getTargets());
        $this->view->set('backup_jobs', $this->models['m_backups']->getRecentJobs(30));
        $this->view->set('backup_worker_agent', $this->models['m_backups']->getWorkerAgent());
        $this->view->set('cron_agents_summary', $this->models['m_backups']->getSchedulerSummary());
        $this->view->set('body_view', $this->view->read('v_backup'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = $this->lang['sys.backup'] ?? 'Резервное копирование';
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Вернёт таблицу логирования проекта
     */
    public function get_project_logs_table() {
        if (!$this->requireAccess([Constants::ADMIN], [
            'ajax' => !empty($_POST),
            'return' => 'admin/system_logs',
            'initiator' => __METHOD__,
        ])) {
            return '';
        }
        /* model */
        $this->loadModel('m_systems');
        $data_table['columns'] = [
            [
                'field' => 'date_time',
                'title' => $this->lang['sys.date_create'],
                'sorted' => 'DESC',
                'filterable' => true,
                'width' => 12
            ],
            [
                'field' => 'level',
                'title' => $this->lang['sys.level'] ?? 'Уровень',
                'sorted' => true,
                'filterable' => true,
                'width' => 8
            ],
            [
                'field' => 'type_log',
                'title' => $this->lang['sys.log_channel'] ?? 'Канал',
                'sorted' => true,
                'filterable' => true,
                'width' => 12
            ],
            [
                'field' => 'initiator',
                'title' => $this->lang['sys.initiator'] ?? 'Инициатор',
                'sorted' => false,
                'filterable' => true,
                'width' => 14
            ],
            [
                'field' => 'result',
                'title' => $this->lang['sys.result'] ?? 'Результат',
                'sorted' => false,
                'filterable' => false,
                'width' => 14
            ],
            [
                'field' => 'details',
                'title' => $this->lang['sys.details'] ?? 'Детали',
                'sorted' => false,
                'filterable' => false,
                'width' => 40
            ],
        ];
        $filters = [
            'date_time' => [
                'type' => 'date',
                'id' => "date_time",
                'value' => '',
                'label' => $this->lang['sys.date_create']
            ],
            'level' => [
                'type' => 'text',
                'id' => "level",
                'value' => '',
                'label' => $this->lang['sys.level'] ?? 'Уровень'
            ],
            'initiator' => [
                'type' => 'text',
                'id' => "initiator",
                'value' => '',
                'label' => $this->lang['sys.initiator'] ?? 'Инициатор'
            ],
            'type_log' => [
                'type' => 'text',
                'id' => "type_log",
                'value' => '',
                'label' => $this->lang['sys.log_channel'] ?? 'Канал'
            ]
        ];
        $postData = SysClass::ee_cleanArray($_POST);
        $selected_sorting = [];
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $php_logs_array = $this->models['m_systems']->get_all_logs($params['order'], $params['where'], $params['start'], $params['limit']);
        } else {
            $php_logs_array = $this->models['m_systems']->get_all_logs(false, false, false, 25);
        }
        foreach ($php_logs_array['data'] as $key => $item) {
            $level = $item['level'] ?: ($item['type_log'] && str_contains(strtolower((string) $item['type_log']), 'error') ? 'ERROR' : '');
            $hasNestedDetails = !empty($item['stack_trace']) || !empty($item['request_id']) || !empty($item['context']) || !empty($item['meta']) || !empty($item['uri']);
            $data_table['rows'][$key] = [
                'date_time' => $item['date_time'],
                'level' => $level,
                'type_log' => $item['channel'] ?: $item['type_log'],
                'initiator' => $item['initiator'],
                'result' => $item['result'],
                'details' => $item['details'],
                'nested_table' => [
                    'columns' => [
                        ['field' => 'request_id', 'title' => $this->lang['sys.request_id'] ?? 'Request ID', 'width' => 12, 'align' => 'left'],
                        ['field' => 'user_id', 'title' => $this->lang['sys.user'] ?? 'Пользователь', 'width' => 8, 'align' => 'left'],
                        ['field' => 'request', 'title' => $this->lang['sys.request'] ?? 'Запрос', 'width' => 20, 'align' => 'left'],
                        ['field' => 'context', 'title' => $this->lang['sys.context'] ?? 'Контекст', 'width' => 20, 'align' => 'left', 'raw' => true],
                        ['field' => 'meta', 'title' => $this->lang['sys.meta'] ?? 'Мета', 'width' => 20, 'align' => 'left', 'raw' => true],
                        ['field' => 'stack_trace', 'title' => $this->lang['sys.stack_trace'], 'width' => 20, 'align' => 'left', 'raw' => true],
                    ],
                    'rows' => [
                        [
                            'request_id' => $item['request_id'],
                            'user_id' => $item['user_id'],
                            'request' => trim(($item['method'] ? $item['method'] . ' ' : '') . ($item['uri'] ?? '') . ($item['host'] ? ' @ ' . $item['host'] : '') . ($item['ip'] ? ' [' . $item['ip'] . ']' : '')),
                            'context' => $item['context'],
                            'meta' => $item['meta'],
                            'stack_trace' => $item['stack_trace'],
                        ],
                    ],
                ]
            ];
            if (!$hasNestedDetails) {
                unset($data_table['rows'][$key]['nested_table']);
            }
        }
        $data_table['total_rows'] = $php_logs_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('project_logs_table_', $data_table, 'get_project_logs_table', $filters, (int) $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('project_logs_table_', $data_table, 'get_project_logs_table', $filters);
        }
    }

    /**
     * Вернёт таблицу ошибок PHP
     */
    public function get_php_logs_table($type = '') {
        if (!$this->requireAccess([Constants::ADMIN], [
            'ajax' => !empty($_POST),
            'return' => 'admin/system_logs',
            'initiator' => __METHOD__,
        ])) {
            return '';
        }
        /* model */
        $this->loadModel('m_systems');
        $data_table['columns'] = [
            [
                'field' => 'error_type',
                'title' => $this->lang['sys.error_type'],
                'sorted' => true,
                'filterable' => true,
                'width' => 15,
                'raw' => true
            ],
            [
                'field' => 'date_time',
                'title' => $this->lang['sys.date_create'],
                'sorted' => 'DESC',
                'filterable' => true,
                'width' => 15,
                'align' => 'center'
            ],
            [
                'field' => 'message',
                'title' => $this->lang['sys.message'],
                'sorted' => false,
                'filterable' => true,
                'width' => 70
            ],
        ];
        $filters = [
            'error_type' => [
                'type' => 'text',
                'id' => "error_type",
                'value' => '',
                'label' => $this->lang['sys.error_type']
            ],
            'date_time' => [
                'type' => 'date',
                'id' => "date_time",
                'value' => '',
                'label' => $this->lang['sys.date_create']
            ],
            'message' => [
                'type' => 'text',
                'id' => "message",
                'value' => '',
                'label' => $this->lang['sys.message']
            ],
        ];
        if ($type == 'fatal_errors') {
            unset($filters['error_type']);
            $data_table['columns'][0]['sorted'] = false;
            $data_table['columns'][0]['filterable'] = false;
        }
        $postData = SysClass::ee_cleanArray($_POST);
        $selected_sorting = [];
        if ($postData && SysClass::isAjaxRequestFromSameSite()) { // AJAX
            list($params, $filters, $selected_sorting) = Plugins::ee_showTablePrepareParams($postData, $data_table['columns']);
            $resolvedType = $this->get_table_name_from_post($postData);
            $type = ($resolvedType === 'fatal_errors') ? 'fatal_errors' : 'php_logs';
            $php_logs_array = $this->models['m_systems']->get_php_logs($params['order'], $params['where'], $params['start'], $params['limit'], $type);
        } else {
            $type = ($type === 'fatal_errors') ? 'fatal_errors' : 'php_logs';
            $php_logs_array = $this->models['m_systems']->get_php_logs(false, false, false, 25, $type);
        }
        foreach ($php_logs_array['data'] as $key => $item) {
            $stack_trace = false;
            if (is_array($item['stack_trace']) && count(($item['stack_trace']))) {
                foreach ($item['stack_trace'] as $stack) {
                    $stack_trace .= $stack . '<br/>';
                }
            }
            if ($type == 'fatal_errors') {
                $item['error_type'] = 'PHP Fatal error';
            }
            $data_table['rows'][$key] = [
                'date_time' => $item['date_time'],
                'error_type' => $item['error_type'] == 'PHP Fatal error' ? '<span class="text-danger">' . $item['error_type'] . '</span>' : $item['error_type'],
                'message' => $item['message'],
                'nested_table' => [
                    'columns' => [
                        ['field' => 'stack_trace', 'title' => $this->lang['sys.stack_trace'], 'width' => 20, 'align' => 'left', 'raw' => true],
                    ],
                    'rows' => [
                        ['stack_trace' => $stack_trace],
                    ],
                ]
            ];
            if (!$stack_trace) {
                unset($data_table['rows'][$key]['nested_table']);
            }
        }
        $data_table['total_rows'] = $php_logs_array['total_count'];
        if ($postData) {
            echo Plugins::ee_show_table('php_logs_table_' . $type, $data_table, 'get_php_logs_table', $filters, (int) $postData["page"], $postData["rows_per_page"], $selected_sorting);
            die;
        } else {
            return Plugins::ee_show_table('php_logs_table_' . $type, $data_table, 'get_php_logs_table', $filters);
        }
    }

    /**
     * Извлекает имя таблицы из массива POST
     * Проходит по всем ключам массива POST и ищет ключи, соответствующие шаблону 'php_logs_table_{table_name}_table_name'
     * Если такой ключ найден, извлекает из него часть, соответствующую {table_name} и возвращает ее
     * Возвращает null, если соответствующий ключ не найден
     * @param array $postData Массив POST, из которого необходимо извлечь имя таблицы
     * @return string|null Возвращает имя таблицы или null, если оно не найдено
     */
    private function get_table_name_from_post($postData) {
        foreach ($postData as $key => $value) {
            if (preg_match('/^php_logs_table_([a-zA-Z0-9_]+)_table_name$/', $key, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    /**
     * Очистит файл php_errors.log
     */
    public function clear_php_logs($params = []) {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/system_logs',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }
        $this->loadModel('m_systems');
        $this->models['m_systems']->clearFlatLogFiles('php_logs');
        SysClass::handleRedirect(200, '/admin/system_logs');
    }

    /**
     * Очистить fatal log и его архивы.
     */
    public function clear_fatal_logs($params = []) {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/system_logs',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }
        $this->loadModel('m_systems');
        $this->models['m_systems']->clearFlatLogFiles('fatal_errors');
        SysClass::handleRedirect(200, '/admin/system_logs');
    }

    /**
     * Очистить project logs и архивы.
     */
    public function clear_project_logs($params = []) {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/system_logs',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }
        $this->loadModel('m_systems');
        $this->models['m_systems']->clearProjectLogs();
        SysClass::handleRedirect(200, '/admin/system_logs');
    }

    /**
     * Очистить HTML/block cache проекта.
     */
    public function clear_html_cache($params = []) {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/system_logs',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }

        CacheManager::clearHtmlCache();
        ClassNotifications::addNotificationUser($this->logged_in, [
            'text' => $this->lang['sys.cache_html_cleared'] ?? 'HTML-кэш очищен.',
            'status' => 'success'
        ]);
        SysClass::handleRedirect(200, '/admin/system_logs');
    }

    /**
     * Очистить route cache проекта.
     */
    public function clear_route_cache($params = []) {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/system_logs',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }

        Router::clearRouteCache();
        ClassNotifications::addNotificationUser($this->logged_in, [
            'text' => $this->lang['sys.cache_route_cleared'] ?? 'Route-кэш очищен.',
            'status' => 'success'
        ]);
        SysClass::handleRedirect(200, '/admin/system_logs');
    }

    /**
     * Сбросить файл-пробу доступности Redis.
     */
    public function reset_redis_cache_probe($params = []) {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/system_logs',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }

        $reset = CacheManager::resetRedisAvailabilityProbe();
        ClassNotifications::addNotificationUser($this->logged_in, [
            'text' => $reset
                ? ($this->lang['sys.redis_probe_reset'] ?? 'Проверка Redis будет выполнена заново.')
                : ($this->lang['sys.data_update_error'] ?? 'Ошибка обновления данных.'),
            'status' => $reset ? 'info' : 'danger'
        ]);
        SysClass::handleRedirect(200, '/admin/system_logs');
    }

    /**
     * Очистить все таблицы без удаления проекта
     * Таблицы нужно дополнять на своё усмотрение
     * Оставит единственного пользователя admin с паролем admin
     */
    public function killEmAll($params = []) {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }
        $this->loadModel('m_systems');
        // Удаление временных и загруженных файлов
        SysClass::ee_removeDir(ENV_TMP_PATH);
        SysClass::ee_removeDir(ENV_SITE_PATH . 'cache');
        SysClass::ee_removeDir(ENV_SITE_PATH . 'logs');
        SysClass::ee_removeDir(ENV_SITE_PATH . 'uploads' . ENV_DIRSEP . 'files');
        @mkdir(ENV_TMP_PATH, 0775, true);
        @mkdir(ENV_SITE_PATH . 'cache', 0775, true);
        @mkdir(ENV_SITE_PATH . 'logs', 0775, true);
        @mkdir(ENV_SITE_PATH . 'logs' . ENV_DIRSEP . 'errors', 0775, true);
        @mkdir(ENV_SITE_PATH . 'uploads' . ENV_DIRSEP . 'files', 0775, true);
        // Перезапись файла Constants.php содержимым ConstantsClean.php
        $constantsCleanPath = SysClass::getConstantsCleanFilePath();
        $constantsPath = SysClass::getConstantsRuntimeFilePath();
        if (file_exists($constantsCleanPath)) {
            $constantsCleanContent = file_get_contents($constantsCleanPath);
            if (file_put_contents($constantsPath, $constantsCleanContent) === false) {
                $this->notifyOperationResult(
                    \classes\system\OperationResult::failure('Не удалось перезаписать Constants.php.', 'constants_rewrite_failed'),
                    ['skip_success_notification' => true]
                );
                SysClass::handleRedirect(200, '/admin');
                return;
            }
        } else {
            $this->notifyOperationResult(
                \classes\system\OperationResult::failure('ConstantsClean.php не найден.', 'constants_clean_missing'),
                ['skip_success_notification' => true]
            );
            SysClass::handleRedirect(200, '/admin');
            return;
        }
        $this->notifyOperationResult(
            $this->models['m_systems']->killDB($this->logged_in),
            [
                'success_message' => 'База данных и системные таблицы пересозданы.',
                'default_error_message' => 'Ошибка очистки базы данных.',
            ]
        );
        SysClass::handleRedirect(200, '/admin');
    }

    /**
     * Создает тестовые данные, если они еще не были созданы
     * @param array $params Параметры для создания тестовых данных
     * @return bool Возвращает false, если тестовые данные уже были созданы
     */
    public function createTest($params = []) {
        if (!$this->requireAccess([Constants::ADMIN, Constants::MODERATOR], [
            'return' => 'admin',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }
        // Путь к файлу-флагу
        $flagFilePath = ENV_TMP_PATH . 'test_data_created.txt';
        // Проверяем, существует ли файл-флаг
        if (file_exists($flagFilePath)) {
            $textMessage = 'Уже есть тестовые данные, для создания дополнительных удалите файл ' . $flagFilePath;
            $status = 'danger';
        } else {
            $textMessage = 'Тестовые данны записаны!';
            $status = 'info';
            $this->loadModel('m_categories_types');
            $this->loadModel('m_categories');
            $this->loadModel('m_pages');
            $this->loadModel('m_properties');
            $users = $this->generateTestUsers();
            $prop = $this->generateTestProperties();
            $sets_prop = $this->generateTestSetsProp();
            $catsType = $this->generateTestCategoriesType();
            $cats = $this->generateTestCategories();
            $ent = $this->generateTestPages();
            $setLinksTypeToCats = $this->setLinksTypeToCats();
            if (!$users || !$cats || !$ent || !$prop || !$sets_prop || !$catsType) {
                $textMessage = 'Ошибка создания тестовых данных!<br/>Users: ' . var_export($users, true) . '<br/>Categories: <br/>'
                        . var_export($cats, true) . '<br/>Entities<br/>' . var_export($ent, true) . '<br/>Properties<br/>' . var_export($prop, true)
                        . '<br/>Sets Properties<br/>' . var_export($sets_prop, true)
                        . '<br/>generateTestCategoriesType<br/>' . var_export($catsType, true)
                        . '<br/>generateTestProperties<br/>' . var_export($prop, true);
                $status = 'danger';
            } else {
                if (!SysClass::createDirectoriesForFile($flagFilePath) || !file_put_contents($flagFilePath, 'Test data created on ' . date('Y-m-d H:i:s'))) {
                    
                }
            }
        }
        ClassNotifications::addNotificationUser($this->logged_in, ['text' => $textMessage, 'status' => $status]);
        SysClass::handleRedirect(200, '/admin');
    }

    public function recover_stale_lifecycle_jobs($params = []) {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/property_lifecycle_jobs',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }

        if (!$this->requireCsrfRequest([
            'redirect' => '/admin/property_lifecycle_jobs',
        ])) {
            return;
        }

        $this->loadModel('m_systems');
        $this->notifyOperationResult(
            $this->models['m_systems']->recoverStaleLifecycleJobs(),
            [
                'success_message' => 'Проверка lifecycle jobs завершена.',
                'default_error_message' => 'Не удалось восстановить lifecycle jobs.',
            ]
        );
        SysClass::handleRedirect(200, '/admin/property_lifecycle_jobs');
    }

    public function recover_stale_operations($params = []) {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/health',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }

        if (!$this->requireCsrfRequest([
            'redirect' => '/admin/health#alerts',
        ])) {
            return;
        }

        $this->loadModel('m_systems');
        $this->notifyOperationResult(
            $this->models['m_systems']->recoverStaleOperationalQueues(),
            [
                'success_message' => $this->lang['sys.recover_stale_operations_done'] ?? 'Проверка зависших процессов завершена.',
                'default_error_message' => $this->lang['sys.recover_stale_operations_failed'] ?? 'Не удалось восстановить зависшие процессы.',
            ]
        );
        SysClass::handleRedirect(200, '/admin/health#alerts');
    }

    public function refresh_media_metadata($params = []) {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/health',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }

        if (!$this->requireCsrfRequest([
            'redirect' => '/admin/health#media',
        ])) {
            return;
        }

        $this->loadModel('m_systems');
        $this->notifyOperationResult(
            $this->models['m_systems']->refreshMediaMetadata(),
            [
                'success_message' => 'Метаданные файлов обновлены.',
                'default_error_message' => 'Не удалось обновить метаданные файлов.',
            ]
        );
        SysClass::handleRedirect(200, '/admin/health#media');
    }

    public function run_backup($params = []) {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/backup',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }

        if (!$this->requireCsrfRequest([
            'redirect' => '/admin/backup',
        ])) {
            return;
        }

        $this->loadModel('m_backups');
        $planId = $this->extractBackupPlanIdFromParams($params);
        $this->notifyOperationResult(
            $this->models['m_backups']->queueBackup([
                'plan_id' => $planId > 0 ? $planId : ($_POST['plan_id'] ?? 0),
                'scope' => $_POST['scope'] ?? 'project_data',
                'delivery_mode' => $_POST['delivery_mode'] ?? 'local_only',
                'target_id' => $_POST['target_id'] ?? 0,
                'requested_by' => (int) $this->logged_in,
                'requested_via' => 'admin_backup',
            ]),
            [
                'success_message' => $this->lang['sys.backup_job_queued'] ?? 'Резервная копия поставлена в очередь.',
                'default_error_message' => $this->lang['sys.backup_job_queue_failed'] ?? 'Не удалось поставить резервную копию в очередь.',
            ]
        );
        SysClass::handleRedirect(200, '/admin/backup');
    }

    public function backup_plan_edit($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/backup_plan_edit',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_backups');
        $planId = $this->extractBackupPlanIdFromParams($params);
        $plan = $planId > 0
            ? $this->models['m_backups']->getPlan($planId)
            : $this->models['m_backups']->getPlanDefaults();

        if ($planId > 0 && !$plan) {
            $this->notifyOperationResult(false, [
                'default_error_message' => $this->lang['sys.backup_plan_not_found'] ?? 'План резервного копирования не найден.',
            ]);
            SysClass::handleRedirect(200, '/admin/backup');
            return;
        }

        if (!empty($_POST)) {
            $saveResult = $this->notifyOperationResult(
                $this->models['m_backups']->savePlan([
                    'backup_plan_id' => $planId,
                    'code' => $_POST['code'] ?? '',
                    'name' => $_POST['name'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'db_mode' => $_POST['db_mode'] ?? 'all',
                    'db_tables' => $_POST['db_tables'] ?? [],
                    'file_mode' => $_POST['file_mode'] ?? 'exclude_selected',
                    'file_items' => $_POST['file_items'] ?? [],
                    'delivery_mode' => $_POST['delivery_mode'] ?? 'local_only',
                    'target_id' => $_POST['target_id'] ?? 0,
                    'is_active' => !empty($_POST['is_active']) ? 1 : 0,
                    'is_default' => !empty($_POST['is_default']) ? 1 : 0,
                ]),
                [
                    'success_message' => $this->lang['sys.backup_plan_saved'] ?? 'План резервного копирования сохранён.',
                    'default_error_message' => $this->lang['sys.data_update_error'] ?? 'Ошибка сохранения данных.',
                ]
            );

            if ($saveResult->isSuccess()) {
                SysClass::handleRedirect(200, '/admin/backup');
                return;
            }

            $plan = array_merge(
                is_array($plan) ? $plan : $this->models['m_backups']->getPlanDefaults(),
                [
                    'backup_plan_id' => $planId,
                    'code' => trim((string) ($_POST['code'] ?? '')),
                    'name' => trim((string) ($_POST['name'] ?? '')),
                    'description' => trim((string) ($_POST['description'] ?? '')),
                    'db_mode' => trim((string) ($_POST['db_mode'] ?? 'all')),
                    'db_tables' => array_values((array) ($_POST['db_tables'] ?? [])),
                    'file_mode' => trim((string) ($_POST['file_mode'] ?? 'exclude_selected')),
                    'file_items' => array_values((array) ($_POST['file_items'] ?? [])),
                    'delivery_mode' => trim((string) ($_POST['delivery_mode'] ?? 'local_only')),
                    'target_id' => (int) ($_POST['target_id'] ?? 0),
                    'is_active' => !empty($_POST['is_active']) ? 1 : 0,
                    'is_default' => !empty($_POST['is_default']) ? 1 : 0,
                ]
            );
        }

        $this->getStandardViews();
        $this->view->set('backup_plan', $plan);
        $this->view->set('backup_targets', $this->models['m_backups']->getTargets());
        $this->view->set('backup_db_tables', $this->models['m_backups']->getAvailableDatabaseTables());
        $this->view->set('backup_file_items', $this->models['m_backups']->getAvailableFileItems());
        $this->view->set('body_view', $this->view->read('v_edit_backup_plan'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = $planId > 0
            ? ($this->lang['sys.backup_plan_edit'] ?? 'Редактирование backup-плана')
            : ($this->lang['sys.backup_plan_new'] ?? 'Новый backup-план');
        $this->showLayout($this->parameters_layout);
    }

    public function backup_target_edit($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/backup_target_edit',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_backups');
        $targetId = $this->extractBackupTargetIdFromParams($params);
        $target = $targetId > 0
            ? $this->models['m_backups']->getTarget($targetId)
            : $this->models['m_backups']->getTargetDefaults();

        if ($targetId > 0 && !$target) {
            $this->notifyOperationResult(false, [
                'default_error_message' => $this->lang['sys.backup_target_not_found'] ?? 'Профиль удалённого хранилища не найден.',
            ]);
            SysClass::handleRedirect(200, '/admin/backup');
            return;
        }

        if (!empty($_POST)) {
            $saveResult = $this->notifyOperationResult(
                $this->models['m_backups']->saveTarget([
                    'target_id' => $targetId,
                    'code' => $_POST['code'] ?? '',
                    'name' => $_POST['name'] ?? '',
                    'protocol' => $_POST['protocol'] ?? 'sftp',
                    'host' => $_POST['host'] ?? '',
                    'port' => $_POST['port'] ?? '',
                    'username' => $_POST['username'] ?? '',
                    'password' => $_POST['password'] ?? '',
                    'remote_path' => $_POST['remote_path'] ?? '/',
                    'timeout_sec' => $_POST['timeout_sec'] ?? 30,
                    'ftp_passive' => !empty($_POST['ftp_passive']) ? 1 : 0,
                    'is_active' => !empty($_POST['is_active']) ? 1 : 0,
                    'is_default' => !empty($_POST['is_default']) ? 1 : 0,
                ]),
                [
                    'success_message' => $this->lang['sys.backup_target_saved'] ?? 'Профиль удалённого хранилища сохранён.',
                    'default_error_message' => $this->lang['sys.data_update_error'] ?? 'Ошибка сохранения данных.',
                ]
            );

            if ($saveResult->isSuccess()) {
                SysClass::handleRedirect(200, '/admin/backup');
                return;
            }

            $target = array_merge(
                is_array($target) ? $target : $this->models['m_backups']->getTargetDefaults(),
                [
                    'target_id' => $targetId,
                    'code' => trim((string) ($_POST['code'] ?? '')),
                    'name' => trim((string) ($_POST['name'] ?? '')),
                    'protocol' => trim((string) ($_POST['protocol'] ?? 'sftp')),
                    'host' => trim((string) ($_POST['host'] ?? '')),
                    'port' => (int) ($_POST['port'] ?? 0),
                    'username' => trim((string) ($_POST['username'] ?? '')),
                    'remote_path' => trim((string) ($_POST['remote_path'] ?? '/')),
                    'timeout_sec' => (int) ($_POST['timeout_sec'] ?? 30),
                    'ftp_passive' => !empty($_POST['ftp_passive']) ? 1 : 0,
                    'is_active' => !empty($_POST['is_active']) ? 1 : 0,
                    'is_default' => !empty($_POST['is_default']) ? 1 : 0,
                ]
            );
        }

        $this->getStandardViews();
        $this->view->set('backup_target', $target);
        $this->view->set('body_view', $this->view->read('v_edit_backup_target'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = $targetId > 0
            ? ($this->lang['sys.backup_target_edit'] ?? 'Редактирование удалённого хранилища')
            : ($this->lang['sys.backup_target_new'] ?? 'Новый профиль удалённого хранилища');
        $this->showLayout($this->parameters_layout);
    }

    public function delete_backup_target($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/backup',
            'initiator' => __METHOD__,
        ])) {
            return;
        }
        if (!$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin/backup',
        ])) {
            return;
        }

        $this->loadModel('m_backups');
        $targetId = $this->extractBackupTargetIdFromParams($params);
        $this->notifyOperationResult(
            $this->models['m_backups']->deleteTarget($targetId),
            [
                'success_message' => $this->lang['sys.backup_target_deleted'] ?? 'Профиль удалённого хранилища удалён.',
                'default_error_message' => $this->lang['sys.error'] ?? 'Ошибка',
            ]
        );
        SysClass::handleRedirect(200, '/admin/backup');
    }

    public function delete_backup_plan($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/backup',
            'initiator' => __METHOD__,
        ])) {
            return;
        }
        if (!$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin/backup',
        ])) {
            return;
        }

        $this->loadModel('m_backups');
        $planId = $this->extractBackupPlanIdFromParams($params);
        $this->notifyOperationResult(
            $this->models['m_backups']->deletePlan($planId),
            [
                'success_message' => $this->lang['sys.backup_plan_deleted'] ?? 'План резервного копирования удалён.',
                'default_error_message' => $this->lang['sys.error'] ?? 'Ошибка',
            ]
        );
        SysClass::handleRedirect(200, '/admin/backup');
    }

    public function test_backup_target($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/backup',
            'initiator' => __METHOD__,
        ])) {
            return;
        }
        if (!$this->requireCsrfRequest([
            'initiator' => __METHOD__,
            'redirect' => '/admin/backup',
        ])) {
            return;
        }

        $this->loadModel('m_backups');
        $targetId = $this->extractBackupTargetIdFromParams($params);
        $this->notifyOperationResult(
            $this->models['m_backups']->testTarget($targetId),
            [
                'success_message' => $this->lang['sys.backup_target_test_success'] ?? 'Подключение к удалённому хранилищу подтверждено.',
                'default_error_message' => $this->lang['sys.backup_target_test_failed'] ?? 'Не удалось проверить удалённое хранилище.',
            ]
        );
        SysClass::handleRedirect(200, '/admin/backup');
    }

    private function renderSystemHealthDashboard(string $activeSection = 'health'): void {
        $this->loadModel('m_systems');
        $healthReport = $this->models['m_systems']->getHealthReport();
        $title = $this->lang['sys.health'] ?? 'Состояние системы';

        $this->getStandardViews();
        $this->view->set('health_report', $healthReport);
        $this->view->set('active_system_section', 'health');
        $this->view->set('system_page_heading', $title);
        $this->view->set('body_view', $this->view->read('v_health'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = $title;
        $this->showLayout($this->parameters_layout);
    }

    private function extractBackupTargetIdFromParams(array $params): int {
        if (in_array('id', $params, true)) {
            $keyId = array_search('id', $params, true);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                return (int) $params[$keyId + 1];
            }
        }
        return 0;
    }

    private function extractBackupPlanIdFromParams(array $params): int {
        if (in_array('id', $params, true)) {
            $keyId = array_search('id', $params, true);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                return (int) $params[$keyId + 1];
            }
        }
        return 0;
    }

    /**
     * Генерирует тестовые наборы свойств
     * Для каждого набора свойств выбирает случайное подмножество свойств из всех доступных и добавляет его в базу данных
     * @param int $count Количество тестовых наборов свойств, которые нужно сгенерировать
     * Примечание: предполагается, что функция get_all_properties возвращает массив всех доступных свойств, и что 
     * в вашей системе имеется необходимая логика для связывания этих свойств с наборами, если это требуется
     */
    private function generateTestSetsProp($count = 10): bool {
        return true;
    }

    private function generateTestProperties($count = 20) {
        $props_name = [
            "Цвет", // свойство продукта
            "Вес", // свойство продукта
            "Размер", // свойство одежды или обуви
            "Материал", // свойство мебели или одежды
            "Производитель", // свойство электроники или автомобиля
            "Дата производства", // свойство продукта питания
            "Срок годности", // свойство продукта питания
            "Мощность", // свойство электроники
            "Объем", // свойство продукта или автомобиля
            "Тип топлива", // свойство автомобиля
            "Страна производства", // свойство любого товара
            "Гарантия", // свойство электроники
            "Возрастные ограничения", // свойство игрушек или фильмов
            "Жанр", // свойство книги или фильма
            "Автор", // свойство книги
            "Количество страниц", // свойство книги
            "Разрешение экрана", // свойство телевизора или монитора
            "Тип диска", // свойство компьютера или плеера
            "Вместимость", // свойство сумки или рюкзака
            "Тип крепления", // свойство спортивного инвентаря
            "Способ приготовления", // свойство продукта питания
            "Количество участников", // свойство настольной игры
            "Продолжительность", // свойство фильма или спектакля
            "Тип батареи", // свойство электронного устройства
            "Тип соединения", // свойство гаджета (например, Bluetooth, Wi-Fi)
            "Уровень сложности", // свойство образовательного курса
            "Длительность курса", // свойство образовательного курса
            "Стиль", // свойство одежды или декора
            "Сезон", // свойство одежды
            "Вкус", // свойство продукта питания или напитка
            "Формат", // свойство книги или диска
            "Температурный режим", // свойство холодильника или кондиционера
            "Световой поток", // свойство лампы
            "Тип лампы", // свойство светильника
            "Класс энергоэффективности", // свойство бытовой техники
            "Ширина", // свойство мебели или двери
            "Высота", // свойство мебели или двери
            "Глубина", // свойство мебели
            "Состав", // свойство косметики или продукта питания
            "Срок службы", // свойство электроники или мебели
            "Тип установки", // свойство мебели или бытовой техники
            "Тип привода", // свойство часов или автомобиля
            "Класс безопасности", // свойство сейфа или автомобиля
            "Форма", // свойство украшения или мебели
            "Стиль дизайна", // свойство интерьера или веб-сайта
            "Метод печати", // свойство принтера
            "Частота обновления", // свойство монитора или телевизора
        ];
        return true;
    }

    /**
     * Генерирует массив значений по умолчанию для свойств, основанных на типах полей
     * Каждому типу поля присваивается случайное имя из предоставленного массива и случайные значения для множественности и обязательности
     * @param string $params JSON-строка, представляющая типы полей и их параметры
     * @param array $props_name Массив названий свойств, из которых будет выбрано случайное название для каждого свойства
     * @return string JSON-строка с массивом сгенерированных значений по умолчанию для каждого типа поля
     */
    private function create_default_values(string $params, array $props_name) {
        $max_prop_name_count = count($props_name) - 1;
        foreach (json_decode($params, true) as $field_type) {
            $value = [
                "type" => $field_type,
                "label" => $props_name[random_int(0, $max_prop_name_count)] . '_' . random_int(333, 777),
                "multiple" => random_int(0, 1),
                "required" => random_int(0, 1),
                "default" => ''
            ];
            $default_values[] = $value;
        }
        return json_encode($default_values, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Генерирует тестовых пользователей и добавляет их в базу данных
     * @param int $count Количество генерируемых пользователей. По умолчанию 50
     * @param int $role Роль пользователей. По умолчанию 4
     * @param int $active Статус активности пользователей. По умолчанию 2
     */
    private function generateTestUsers($count = 50, $role = 4, $active = 2) {
        $namePrefixes = [
            'Алексей', 'Наталья', 'Сергей', 'Ольга', 'Дмитрий', 'Елена', 'Иван', 'Мария', 'Павел', 'Анастасия',
            'Андрей', 'Татьяна', 'Роман', 'Светлана', 'Евгений', 'Виктория', 'Михаил', 'Ирина', 'Владимир', 'Екатерина',
            'Борис', 'Ксения', 'Григорий', 'Лариса', 'Максим', 'Юлия', 'Арсений', 'Полина', 'Антон', 'Валерия',
            'Фёдор', 'Маргарита', 'Леонид', 'Вероника', 'Олег'
        ];
        $comments = [
            'Тестовый комментарий',
            'Сгенерировано автоматически',
            'Проверка функции',
            'Данные для теста',
            'Неизвестный пользователь',
            'Отличный продукт!',
            'Рекомендую всем друзьям.',
            'Быстрая доставка, отличное качество.',
            'Очень доволен покупкой.',
            'Буду заказывать ещё.',
            'Превосходный сервис!',
            'Очень полезный товар.',
            'Лучшее предложение на рынке.',
            'Спасибо за быстрый ответ!',
            'Отличный выбор для подарка.',
            'Удобное расположение магазина.',
            'Профессиональный персонал.',
            'Большой ассортимент товаров.',
            'Всегда нахожу то, что нужно.',
            'Самые лучшие цены!'
        ];
        $this->loadModel('m_systems');
        for ($i = 0; $i < $count; $i++) {
            $name = $namePrefixes[array_rand($namePrefixes)] . '_' . mt_rand(1000, 9999);
            $email = $name . '@' . ENV_DOMEN_NAME;
            $commentPrefix = $comments[array_rand($comments)];
            $comment = $commentPrefix . " (" . date("Y-m-d H:i:s") . ")";
            $password = bin2hex(openssl_random_pseudo_bytes(4)); // 8-character random password
            $res = $this->users->registrationNewUser([
                'name' => $name,
                'email' => $email,
                'active' => $active,
                'user_role' => $role,
                'subscribed' => '1',
                'privacy_policy_accepted' => 1,
                'personal_data_consent_accepted' => 1,
                'comment' => $comment,
                'pwd' => $password], true);
        }
        return $res;
    }

    private function generateTestCategoriesType(int $count = 10): bool {
        $testName = [
            'Товары',
            'Страницы',
            'Электроника',
            'Хоз. товары',
            'Рулетки',
            'Отвёртка',
            'Тетради',
            'Ручки',
            'Блог',
            'Эзотерика',
            'Книги',
            'Игрушки',
            'Спорт',
            'Одежда'
        ];
        return true;
    }

    /**
     * Генерирует тестовые категории с случайной вложенностью
     * @param int $count Количество генерируемых категорий
     */
    private function generateTestCategories($count = 50) {
        $types = $this->models['m_categories_types']->getAllTypes();
        if (empty($types)) {
            SysClass::pre("No types found in the types table.");
        }

        // Предопределенный массив с названиями категорий
        $predefinedNames = [
            "Книги", "Электроника", "Одежда", "Спорт", "Игрушки",
            "Красота и здоровье", "Дом и сад", "Питание", "Автомобили", "Техника",
            "Музыка", "Игры", "Путешествия", "Образование", "Искусство",
            "Кино", "Мебель", "Инструменты", "Украшения", "Фотография",
            "Животные", "Канцелярия", "Программирование", "Безопасность", "Медицина",
            "Кулинария", "Садоводство", "Спортивное питание", "Мода", "Архитектура",
            "История", "Литература", "Философия", "Психология", "География",
            "Биология", "Физика", "Химия", "Математика", "Экономика",
            "Политика", "Экология", "Астрономия", "Туризм", "Рукоделие",
            "Йога", "Фитнес", "Танцы", "Медитация", "Компьютерная графика"
        ];
        return true;
    }

    /**
     * Генерирует тестовые страницы
     * @param int $count Количество генерируемых страниц
     */
    private function generateTestPages($count = 200) {
        return true;
    }

    /**
     * Присваиваем типам категорий наборы свойств
     * @return bool
     */
    private function setLinksTypeToCats() {
        return true;
    }
}
