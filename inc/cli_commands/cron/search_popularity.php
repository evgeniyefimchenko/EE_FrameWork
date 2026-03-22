<?php
/**
 * CLI command: cron:search-popularity
 *
 * Usage:
 * php inc/cli.php cron:search-popularity
 */

use classes\system\CronAgentRegistry;

$result = CronAgentRegistry::runHandler('search.popularity.update', [], ['trigger_source' => 'cli_direct']);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
return !empty($result['success']) ? 0 : 1;
