<?php
/**
 * CLI command: ops:health-check
 *
 * Usage:
 * php inc/cli.php ops:health-check
 */
require_once 'app/admin/models/ModelSystems.php';

try {
    $model = new ModelSystems();
    echo json_encode($model->getHealthReport(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}
