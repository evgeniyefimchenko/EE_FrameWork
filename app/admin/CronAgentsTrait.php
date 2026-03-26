<?php

namespace app\admin;

use classes\system\Constants;
use classes\system\CronAgentService;
use classes\system\SysClass;

/**
 * Управление cron-агентами в админ-панели.
 */
trait CronAgentsTrait {

    public function cron_agents($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/cron_agents',
            'initiator' => __METHOD__,
        ]) || array_filter($params)) {
            if (array_filter($params)) {
                SysClass::handleRedirect();
            }
            return;
        }

        $this->loadModel('m_cron_agents');
        $this->getStandardViews();
        $this->view->set('cron_agents_summary', $this->models['m_cron_agents']->getSummary());
        $this->view->set('cron_agents', $this->models['m_cron_agents']->getAgents(200));
        $this->view->set('cron_agent_runs', $this->models['m_cron_agents']->getRecentRuns(20));
        $this->view->set('cron_handlers', $this->models['m_cron_agents']->getHandlers());
        $this->view->set('media_mirror_agent', $this->models['m_cron_agents']->getAgentByCode('media-mirror-worker'));
        $this->view->set('media_queue_summary', $this->models['m_cron_agents']->getMediaQueueSummary());
        $this->view->set('body_view', $this->view->read('v_cron_agents'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = $this->lang['sys.cron_agents'] ?? 'Cron-агенты';
        $this->showLayout($this->parameters_layout);
    }

    public function cron_agent_edit($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/cron_agent_edit',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_cron_agents');
        $agentId = 0;
        if (in_array('id', $params, true)) {
            $keyId = array_search('id', $params, true);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $agentId = (int) $params[$keyId + 1];
            }
        }

        $agent = $agentId > 0
            ? $this->models['m_cron_agents']->getAgent($agentId)
            : $this->models['m_cron_agents']->getAgentDefaults();

        if ($agentId <= 0 && empty($_POST)) {
            $prefill = $this->buildCronAgentPrefillFromQuery();
            if (!empty($prefill)) {
                $agent = array_merge(is_array($agent) ? $agent : [], $prefill);
            }
        }

        if ($agentId > 0 && !$agent) {
            $this->notifyOperationResult(false, [
                'default_error_message' => $this->lang['sys.cron_agent_not_found'] ?? 'Cron-агент не найден.',
            ]);
            SysClass::handleRedirect(200, '/admin/cron_agents');
            return;
        }

        if (!empty($_POST)) {
            $postData = $_POST;
            $payloadJson = $this->buildCronAgentPayloadJsonFromPost($postData);
            $saveResult = $this->notifyOperationResult(
                $this->models['m_cron_agents']->saveAgent([
                    'agent_id' => $agentId,
                    'code' => $postData['code'] ?? '',
                    'title' => $postData['title'] ?? '',
                    'description' => $postData['description'] ?? '',
                    'handler' => $postData['handler'] ?? '',
                    'schedule_mode' => $postData['schedule_mode'] ?? 'interval',
                    'interval_minutes' => $postData['interval_minutes'] ?? 1,
                    'cron_expression' => $postData['cron_expression'] ?? '',
                    'payload_json' => $payloadJson,
                    'is_active' => !empty($postData['is_active']) ? 1 : 0,
                    'priority' => $postData['priority'] ?? 100,
                    'weight' => $postData['weight'] ?? 1,
                    'max_runtime_sec' => $postData['max_runtime_sec'] ?? 300,
                    'lock_ttl_sec' => $postData['lock_ttl_sec'] ?? 360,
                    'retry_delay_sec' => $postData['retry_delay_sec'] ?? 300,
                    'next_run_at' => $postData['next_run_at'] ?? '',
                ]),
                [
                    'success_message' => $this->lang['sys.cron_agent_saved'] ?? 'Cron-агент сохранён.',
                    'default_error_message' => $this->lang['sys.data_update_error'] ?? 'Ошибка сохранения данных.',
                ]
            );

            if ($saveResult->isSuccess()) {
                $agentId = $saveResult->getId(['agent_id']);
                SysClass::handleRedirect(200, '/admin/cron_agent_edit/id/' . $agentId);
                return;
            }

            $agent = array_merge(
                is_array($agent) ? $agent : $this->models['m_cron_agents']->getAgentDefaults(),
                [
                    'agent_id' => $agentId,
                    'code' => trim((string) ($postData['code'] ?? '')),
                    'title' => trim((string) ($postData['title'] ?? '')),
                    'description' => trim((string) ($postData['description'] ?? '')),
                    'handler' => trim((string) ($postData['handler'] ?? '')),
                    'schedule_mode' => trim((string) ($postData['schedule_mode'] ?? 'interval')),
                    'interval_minutes' => (int) ($postData['interval_minutes'] ?? 1),
                    'cron_expression' => trim((string) ($postData['cron_expression'] ?? '')),
                    'payload_json' => $payloadJson,
                    'is_active' => !empty($postData['is_active']) ? 1 : 0,
                    'priority' => (int) ($postData['priority'] ?? 100),
                    'weight' => (int) ($postData['weight'] ?? 1),
                    'max_runtime_sec' => (int) ($postData['max_runtime_sec'] ?? 300),
                    'lock_ttl_sec' => (int) ($postData['lock_ttl_sec'] ?? 360),
                    'retry_delay_sec' => (int) ($postData['retry_delay_sec'] ?? 300),
                    'next_run_at_form' => trim((string) ($postData['next_run_at'] ?? '')),
                ]
            );
        }

        $this->getStandardViews();
        $this->view->set('cron_agent', $agent);
        $this->view->set('cron_handlers', $this->models['m_cron_agents']->getHandlers());
        $this->view->set('cron_agent_summary', $this->models['m_cron_agents']->getSummary());
        $this->view->set('body_view', $this->view->read('v_edit_cron_agent'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = $agentId > 0
            ? ($this->lang['sys.cron_agent_edit'] ?? 'Редактирование cron-агента')
            : ($this->lang['sys.cron_agent_new'] ?? 'Новый cron-агент');
        $this->showLayout($this->parameters_layout);
    }

    public function delete_cron_agent($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/cron_agents',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_cron_agents');
        $agentId = $this->extractAgentIdFromParams($params);
        $this->notifyOperationResult(
            $this->models['m_cron_agents']->deleteAgent($agentId),
            [
                'success_message' => $this->lang['sys.cron_agent_deleted'] ?? 'Cron-агент удалён.',
                'default_error_message' => $this->lang['sys.error'] ?? 'Ошибка',
            ]
        );
        SysClass::handleRedirect(200, '/admin/cron_agents');
    }

    public function toggle_cron_agent($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/cron_agents',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_cron_agents');
        $agentId = $this->extractAgentIdFromParams($params);
        $result = $this->models['m_cron_agents']->toggleAgent($agentId);
        $successMessage = $result->isSuccess()
            ? $result->getMessage($this->lang['sys.cron_agent_saved'] ?? 'Состояние обновлено.')
            : '';
        $this->notifyOperationResult(
            $result,
            [
                'success_message' => $successMessage,
                'default_error_message' => $this->lang['sys.error'] ?? 'Ошибка',
            ]
        );
        SysClass::handleRedirect(200, '/admin/cron_agents');
    }

    public function run_cron_agent($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/cron_agents',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_cron_agents');
        $agentId = $this->extractAgentIdFromParams($params);
        $result = $this->models['m_cron_agents']->runAgentNow($agentId, 'manual_dashboard');
        $successMessage = $result->isSuccess()
            ? $result->getMessage($this->lang['sys.cron_agent_run_now'] ?? 'Cron-агент запущен.')
            : '';
        $this->notifyOperationResult(
            $result,
            [
                'success_message' => $successMessage,
                'default_error_message' => $this->lang['sys.error'] ?? 'Ошибка',
            ]
        );
        SysClass::handleRedirect(200, '/admin/cron_agents');
    }

    public function update_media_mirror_worker($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/cron_agents',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_cron_agents');
        $agent = $this->models['m_cron_agents']->getAgentByCode('media-mirror-worker');
        if (!$agent) {
            $this->notifyOperationResult(false, [
                'default_error_message' => $this->lang['sys.cron_agent_not_found'] ?? 'Cron-агент не найден.',
            ]);
            SysClass::handleRedirect(200, '/admin/cron_agents');
            return;
        }

        $postData = [
            'handler' => (string) ($agent['handler'] ?? 'media.mirror.worker'),
            'payload_json' => (string) ($agent['payload_json'] ?? '{}'),
            'payload_field' => [
                'batch_limit' => $_POST['batch_limit'] ?? (($agent['payload']['batch_limit'] ?? 10)),
                'retry_delay_sec' => $_POST['media_retry_delay_sec'] ?? (($agent['payload']['retry_delay_sec'] ?? 900)),
                'time_budget_sec' => $_POST['media_time_budget_sec'] ?? (($agent['payload']['time_budget_sec'] ?? 40)),
            ],
        ];
        $payloadJson = $this->buildCronAgentPayloadJsonFromPost($postData);

        $result = $this->models['m_cron_agents']->saveAgent([
            'agent_id' => (int) ($agent['agent_id'] ?? 0),
            'code' => (string) ($agent['code'] ?? ''),
            'title' => (string) ($agent['title'] ?? ''),
            'description' => (string) ($agent['description'] ?? ''),
            'handler' => (string) ($agent['handler'] ?? ''),
            'schedule_mode' => (string) ($agent['schedule_mode'] ?? 'interval'),
            'interval_minutes' => (int) ($agent['interval_minutes'] ?? 1),
            'cron_expression' => (string) ($agent['cron_expression'] ?? ''),
            'payload_json' => $payloadJson,
            'is_active' => !empty($agent['is_active']) ? 1 : 0,
            'priority' => (int) ($agent['priority'] ?? 100),
            'weight' => (int) ($agent['weight'] ?? 1),
            'max_runtime_sec' => (int) ($agent['max_runtime_sec'] ?? 300),
            'lock_ttl_sec' => (int) ($agent['lock_ttl_sec'] ?? 360),
            'retry_delay_sec' => (int) ($agent['retry_delay_sec'] ?? 300),
            'next_run_at' => (string) ($agent['next_run_at'] ?? ''),
        ]);

        $this->notifyOperationResult(
            $result,
            [
                'success_message' => $this->lang['sys.media_queue_worker_settings_saved'] ?? 'Параметры media-worker обновлены.',
                'default_error_message' => $this->lang['sys.error'] ?? 'Ошибка',
            ]
        );

        SysClass::handleRedirect(200, '/admin/cron_agents');
    }

    public function run_cron_scheduler($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/cron_agents',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_cron_agents');
        $result = $this->models['m_cron_agents']->runSchedulerTick('manual_dashboard');
        $successMessage = $result->isSuccess()
            ? $result->getMessage($this->lang['sys.cron_agent_run_scheduler'] ?? 'Проход scheduler-а выполнен.')
            : '';
        $this->notifyOperationResult(
            $result,
            [
                'success_message' => $successMessage,
                'default_error_message' => $this->lang['sys.error'] ?? 'Ошибка',
            ]
        );
        SysClass::handleRedirect(200, '/admin/cron_agents');
    }

    public function recover_stale_cron_agents($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/cron_agents',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_cron_agents');
        $result = $this->models['m_cron_agents']->recoverStaleAgents();
        $successMessage = $result->isSuccess()
            ? $result->getMessage($this->lang['sys.cron_agent_recover_stale'] ?? 'Зависшие cron-агенты восстановлены.')
            : '';
        $this->notifyOperationResult(
            $result,
            [
                'success_message' => $successMessage,
                'default_error_message' => $this->lang['sys.error'] ?? 'Ошибка',
            ]
        );
        SysClass::handleRedirect(200, '/admin/cron_agents');
    }

    public function cron_agent_runs($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/cron_agent_runs',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_cron_agents');
        $agentId = $this->extractAgentIdFromParams($params);
        $agent = $agentId > 0 ? $this->models['m_cron_agents']->getAgent($agentId) : null;

        $this->getStandardViews();
        $this->view->set('cron_agent', $agent);
        $this->view->set('cron_agents_summary', $this->models['m_cron_agents']->getSummary());
        $this->view->set('cron_agent_runs', $this->models['m_cron_agents']->getRecentRuns(200, $agentId > 0 ? $agentId : null));
        $this->view->set('body_view', $this->view->read('v_cron_agent_runs'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout["layout_content"] = $this->html;
        $this->parameters_layout["layout"] = 'dashboard';
        $this->parameters_layout["title"] = $this->lang['sys.cron_agent_runs'] ?? 'История запусков cron-агентов';
        $this->showLayout($this->parameters_layout);
    }

    private function extractAgentIdFromParams(array $params): int {
        if (in_array('id', $params, true)) {
            $keyId = array_search('id', $params, true);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                return (int) $params[$keyId + 1];
            }
        }
        return 0;
    }

    private function buildCronAgentPrefillFromQuery(): array {
        $prefill = [];

        $stringFields = [
            'code',
            'title',
            'description',
            'handler',
            'schedule_mode',
            'cron_expression',
            'payload_json',
            'next_run_at',
        ];
        foreach ($stringFields as $field) {
            if (!isset($_GET[$field]) || !is_scalar($_GET[$field])) {
                continue;
            }
            $prefill[$field] = trim((string) $_GET[$field]);
        }

        $intFields = [
            'interval_minutes',
            'priority',
            'weight',
            'max_runtime_sec',
            'lock_ttl_sec',
            'retry_delay_sec',
        ];
        foreach ($intFields as $field) {
            if (!isset($_GET[$field]) || !is_scalar($_GET[$field])) {
                continue;
            }
            $prefill[$field] = (int) $_GET[$field];
        }

        if (isset($_GET['is_active'])) {
            $prefill['is_active'] = !in_array((string) $_GET['is_active'], ['0', 'false', 'off', ''], true) ? 1 : 0;
        }

        if (!empty($prefill['next_run_at'])) {
            $prefill['next_run_at_form'] = trim((string) $prefill['next_run_at']);
        }

        return $prefill;
    }

    private function buildCronAgentPayloadJsonFromPost(array $postData): string {
        $rawPayload = trim((string) ($postData['payload_json'] ?? '{}'));
        $payload = json_decode($rawPayload, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $handler = trim((string) ($postData['handler'] ?? ''));
        $handlerMeta = CronAgentService::getHandlers()[$handler] ?? null;
        $payloadFieldMeta = is_array($handlerMeta['payload_fields'] ?? null) ? $handlerMeta['payload_fields'] : [];
        $postedPayloadFields = is_array($postData['payload_field'] ?? null) ? $postData['payload_field'] : [];

        foreach ($payloadFieldMeta as $fieldMeta) {
            if (!is_array($fieldMeta) || empty($fieldMeta['key'])) {
                continue;
            }

            $key = (string) $fieldMeta['key'];
            if (!array_key_exists($key, $postedPayloadFields)) {
                continue;
            }

            $type = (string) ($fieldMeta['type'] ?? 'string');
            $rawValue = $postedPayloadFields[$key];

            if ($type === 'int') {
                $value = (int) $rawValue;
                if (isset($fieldMeta['min'])) {
                    $value = max((int) $fieldMeta['min'], $value);
                }
                if (isset($fieldMeta['max'])) {
                    $value = min((int) $fieldMeta['max'], $value);
                }
                $payload[$key] = $value;
                continue;
            }

            if ($type === 'float') {
                $value = (float) $rawValue;
                if (isset($fieldMeta['min'])) {
                    $value = max((float) $fieldMeta['min'], $value);
                }
                if (isset($fieldMeta['max'])) {
                    $value = min((float) $fieldMeta['max'], $value);
                }
                $payload[$key] = $value;
                continue;
            }

            $payload[$key] = is_scalar($rawValue) ? trim((string) $rawValue) : '';
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
