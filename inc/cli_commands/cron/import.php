<?php
/**
 * CLI command: cron:import <job_id>
 *
 * Usage:
 * php inc/cli.php cron:import <job_id>
 */

use classes\system\CronAgentRegistry;

$job_id = $eeCliArgs[0] ?? null;
if (!$job_id || !ctype_digit((string)$job_id)) {
    fwrite(STDERR, "Usage: php inc/cli.php cron:import <job_id>\n");
    return 1;
}

$result = CronAgentRegistry::runHandler('import.profile', ['job_id' => (int) $job_id], ['trigger_source' => 'cli_direct']);
$payload = [
    'success' => !empty($result['success']),
    'status' => $result['status'] ?? '',
    'message' => $result['message'] ?? '',
    'data' => $result['data'] ?? [],
];
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
if (!empty($result['output'])) {
    echo trim((string) $result['output']) . PHP_EOL;
}
return !empty($result['success']) ? 0 : 1;
