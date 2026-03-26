<?php

use classes\system\CronAgentService;
use classes\system\ImportMediaQueueService;
use classes\system\OperationResult;

/**
 * Модель управления cron-агентами.
 */
class ModelCronAgents {

    public function getSummary(): array {
        return CronAgentService::getSummary();
    }

    public function getAgents(int $limit = 200): array {
        return $this->decorateAgentsForUi(CronAgentService::getAgents($limit));
    }

    public function getAgent(int $agentId): ?array {
        $agent = CronAgentService::getAgent($agentId);
        if (!$agent) {
            return null;
        }
        $items = $this->decorateAgentsForUi([$agent]);
        return $items[0] ?? null;
    }

    public function getAgentByCode(string $code): ?array {
        $agent = CronAgentService::getAgentByCode($code);
        if (!$agent) {
            return null;
        }
        $items = $this->decorateAgentsForUi([$agent]);
        return $items[0] ?? null;
    }

    public function getRecentRuns(int $limit = 50, ?int $agentId = null): array {
        return CronAgentService::getRecentRuns($limit, $agentId);
    }

    public function getMediaQueueSummary(?int $jobId = null): array {
        return ImportMediaQueueService::getSummary($jobId);
    }

    public function getHandlers(): array {
        $result = [];
        foreach (CronAgentService::getHandlers() as $handlerCode => $meta) {
            $payloadExample = $meta['payload_example'] ?? [];
            $payloadFields = [];
            foreach ((array) ($meta['payload_fields'] ?? []) as $fieldMeta) {
                if (!is_array($fieldMeta) || empty($fieldMeta['key'])) {
                    continue;
                }
                $payloadFields[] = [
                    'key' => (string) $fieldMeta['key'],
                    'type' => (string) ($fieldMeta['type'] ?? 'string'),
                    'min' => $fieldMeta['min'] ?? null,
                    'max' => $fieldMeta['max'] ?? null,
                    'step' => $fieldMeta['step'] ?? null,
                    'default' => $fieldMeta['default'] ?? null,
                    'label' => $this->langValue((string) ($fieldMeta['label_key'] ?? ''), (string) $fieldMeta['key']),
                    'help' => $this->langValue((string) ($fieldMeta['help_key'] ?? ''), ''),
                ];
            }
            $result[$handlerCode] = [
                'code' => $handlerCode,
                'title_key' => (string) ($meta['title_key'] ?? ''),
                'description_key' => (string) ($meta['description_key'] ?? ''),
                'title' => $this->langValue((string) ($meta['title_key'] ?? ''), $handlerCode),
                'description' => $this->langValue((string) ($meta['description_key'] ?? ''), ''),
                'payload_example' => $payloadExample,
                'payload_example_pretty' => json_encode($payloadExample, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                'payload_fields' => $payloadFields,
            ];
        }

        return $result;
    }

    public function saveAgent(array $agentData): OperationResult {
        return CronAgentService::saveAgent($agentData);
    }

    public function deleteAgent(int $agentId): OperationResult {
        return CronAgentService::deleteAgent($agentId);
    }

    public function toggleAgent(int $agentId): OperationResult {
        return CronAgentService::toggleAgent($agentId);
    }

    public function runAgentNow(int|string $idOrCode, string $triggerSource = 'manual_dashboard'): OperationResult {
        return CronAgentService::runAgentNow($idOrCode, $triggerSource);
    }

    public function runSchedulerTick(string $triggerSource = 'manual_dashboard'): OperationResult {
        return CronAgentService::runDueAgents($triggerSource);
    }

    public function recoverStaleAgents(): OperationResult {
        return CronAgentService::recoverStaleAgents();
    }

    public function getAgentDefaults(): array {
        return [
            'agent_id' => 0,
            'code' => '',
            'title' => '',
            'description' => '',
            'handler' => 'property_lifecycle.next',
            'schedule_mode' => 'interval',
            'interval_minutes' => 1,
            'cron_expression' => '',
            'payload_json' => '{}',
            'is_active' => 1,
            'priority' => 100,
            'weight' => 1,
            'max_runtime_sec' => 300,
            'lock_ttl_sec' => 360,
            'retry_delay_sec' => 300,
            'next_run_at' => '',
            'next_run_at_form' => '',
        ];
    }

    private function decorateAgentsForUi(array $agents): array {
        $handlers = $this->getHandlers();
        $result = [];

        foreach ($agents as $agent) {
            if (!is_array($agent)) {
                continue;
            }

            $handlerCode = (string) ($agent['handler'] ?? '');
            $handlerMeta = $handlers[$handlerCode] ?? null;
            $runtimeStatus = (string) ($agent['runtime_status'] ?? 'idle');
            $payload = is_array($agent['payload'] ?? null) ? $agent['payload'] : [];
            $agent['handler_title'] = (string) ($handlerMeta['title'] ?? $handlerCode);
            $agent['handler_description'] = (string) ($handlerMeta['description'] ?? '');
            $agent['schedule_label'] = $this->resolveScheduleLabel((string) ($agent['schedule_mode'] ?? 'interval'));
            $agent['runtime_status_label'] = $this->resolveRuntimeStatusLabel($runtimeStatus);
            $agent['runtime_status_class'] = $this->resolveRuntimeStatusClass($runtimeStatus);
            $agent['payload_preview'] = $payload === []
                ? '{}'
                : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $agent['next_run_at_form'] = !empty($agent['next_run_at'])
                ? date('Y-m-d\TH:i', strtotime((string) $agent['next_run_at']))
                : '';
            $result[] = $agent;
        }

        return $result;
    }

    private function resolveScheduleLabel(string $scheduleMode): string {
        return match ($scheduleMode) {
            'cron' => $this->langValue('sys.cron_agent_schedule_cron', 'Cron'),
            'manual' => $this->langValue('sys.cron_agent_schedule_manual', 'Manual'),
            default => $this->langValue('sys.cron_agent_schedule_interval', 'Interval'),
        };
    }

    private function resolveRuntimeStatusLabel(string $status): string {
        return match ($status) {
            'running' => $this->langValue('sys.running', 'Running'),
            'disabled' => $this->langValue('sys.disabled', 'Disabled'),
            'due' => $this->langValue('sys.cron_agent_status_due', 'Due'),
            'failed' => $this->langValue('sys.failed', 'Failed'),
            'cooldown' => $this->langValue('sys.cron_agent_status_cooldown', 'Cooldown'),
            default => $this->langValue('sys.cron_agent_status_idle', 'Idle'),
        };
    }

    private function resolveRuntimeStatusClass(string $status): string {
        return match ($status) {
            'running' => 'bg-primary',
            'disabled' => 'bg-secondary',
            'due' => 'bg-warning text-dark',
            'failed' => 'bg-danger',
            'cooldown' => 'bg-info text-dark',
            default => 'bg-success',
        };
    }

    private function langValue(string $key, string $fallback = ''): string {
        global $lang;
        if (is_array($lang) && !empty($lang[$key]) && is_string($lang[$key])) {
            return $lang[$key];
        }
        return $fallback;
    }
}
