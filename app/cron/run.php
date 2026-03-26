<?php

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

$projectRoot = dirname(__DIR__, 2);
$rawConfig = require $projectRoot . '/inc/configuration.php';
$tickTimeBudgetSec = max(15, min(300, (int) (($rawConfig['ENV_CRON_TICK_TIME_BUDGET_SEC'] ?? 45))));

$lockDirectory = $projectRoot . '/cache';
if (!is_dir($lockDirectory) && !@mkdir($lockDirectory, 0775, true) && !is_dir($lockDirectory)) {
    $lockDirectory = sys_get_temp_dir();
}

$lockFilePath = rtrim($lockDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ee_cron_run.lock';
$lockHandle = @fopen($lockFilePath, 'c+');
if ($lockHandle !== false) {
    if (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
        exit(0);
    }

    @ftruncate($lockHandle, 0);
    @fwrite($lockHandle, json_encode([
        'pid' => getmypid(),
        'started_at' => date('Y-m-d H:i:s'),
        'time_budget_sec' => $tickTimeBudgetSec,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    register_shutdown_function(static function () use ($lockHandle): void {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    });
}

$args = $_SERVER['argv'] ?? $argv ?? [];
$GLOBALS['argv'] = array_merge(
    [dirname(__DIR__, 2) . '/inc/cli.php', 'cron:run-agents'],
    array_slice($args, 1)
);
$_SERVER['argv'] = $GLOBALS['argv'];

require dirname(__DIR__, 2) . '/inc/cli.php';
