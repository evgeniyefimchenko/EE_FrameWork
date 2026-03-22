<?php
/**
 * CLI command: cron:lifecycle <job_id|next>
 *
 * Usage:
 * php inc/cli.php cron:lifecycle <job_id|next>
 */
$jobArg = $eeCliArgs[0] ?? null;
if ($jobArg === null || ($jobArg !== 'next' && !ctype_digit((string) $jobArg))) {
    fwrite(STDERR, "Usage: php inc/cli.php cron:lifecycle <job_id|next>\n");
    return 1;
}

try {
    require_once 'app/admin/models/ModelPropertyLifecycle.php';
    $lifecycle = new \ModelPropertyLifecycle();
} catch (\Throwable $e) {
    die("Error initializing lifecycle model: " . $e->getMessage() . "\n");
}

if ($jobArg === 'next') {
    echo "Running next queued lifecycle job\n";
    $result = $lifecycle->runNextQueuedLifecycleJob();
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    return 0;
}

echo "Running lifecycle job ID: {$jobArg}\n";
$result = $lifecycle->runLifecycleJob((int) $jobArg);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
return 0;
