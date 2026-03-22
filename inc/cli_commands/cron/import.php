<?php
/**
 * CLI command: cron:import <job_id>
 *
 * Usage:
 * php inc/cli.php cron:import <job_id>
 */

$job_id = $eeCliArgs[0] ?? null;
if (!$job_id || !ctype_digit((string)$job_id)) {
    fwrite(STDERR, "Usage: php inc/cli.php cron:import <job_id>\n");
    return 1;
}

try {
    require_once 'app/admin/index.php';
} catch (\Throwable $e) {
    die("Error loading admin controller definition: " . $e->getMessage() . "\n");
}

try {
    $view = new \classes\system\View();
    $adminController = new \ControllerAdmin($view);
} catch (\Throwable $e) {
    die("Error initializing admin controller: " . $e->getMessage() . "\n");
}

if (!method_exists($adminController, 'run_wp_import')) {
    die("Error: Method 'run_wp_import' not found in admin controller.\n");
}

echo "Running import job ID: {$job_id}\n";
$adminController->run_wp_import([(int)$job_id]);
echo "Import job {$job_id} finished.\n";
return 0;
