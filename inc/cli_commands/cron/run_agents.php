<?php
/**
 * CLI command: cron:run-agents
 *
 * Usage:
 * php inc/cli.php cron:run-agents [--json]
 */

use classes\system\CronAgentService;

$result = CronAgentService::runDueAgents('scheduler');
$payload = $result->toArray();

if (!empty($eeCliOptions['json'])) {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    return $result->isSuccess() ? 0 : 1;
}

$data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
echo 'Cron scheduler tick: '
    . 'selected=' . (int) ($data['selected'] ?? 0)
    . ', executed=' . (int) ($data['executed'] ?? 0)
    . ', success=' . (int) ($data['success'] ?? 0)
    . ', failed=' . (int) ($data['failed'] ?? 0)
    . ', skipped_busy=' . (int) ($data['skipped_busy'] ?? 0)
    . ', skipped_locked=' . (int) ($data['skipped_locked'] ?? 0)
    . PHP_EOL;

return $result->isSuccess() ? 0 : 1;
