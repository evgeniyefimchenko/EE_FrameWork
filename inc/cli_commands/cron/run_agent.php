<?php
/**
 * CLI command: cron:run-agent <id|code>
 *
 * Usage:
 * php inc/cli.php cron:run-agent <id|code> [--json]
 */

use classes\system\CronAgentService;

$agentRef = $eeCliArgs[0] ?? null;
if ($agentRef === null || trim((string) $agentRef) === '') {
    fwrite(STDERR, "Usage: php inc/cli.php cron:run-agent <id|code> [--json]\n");
    return 1;
}

$result = CronAgentService::runAgentNow((string) $agentRef, 'cli');
$payload = $result->toArray();

if (!empty($eeCliOptions['json'])) {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    return $result->isSuccess() ? 0 : 1;
}

echo ($result->isSuccess() ? 'SUCCESS: ' : 'FAILED: ') . $result->getMessage('Cron agent execution finished.') . PHP_EOL;
return $result->isSuccess() ? 0 : 1;
