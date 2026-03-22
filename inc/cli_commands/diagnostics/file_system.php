<?php

use classes\system\FileSystem;

$bootstrapOutput = $eeCliBootstrapOutput ?? '';

$options = is_array($eeCliOptions ?? null) ? $eeCliOptions : [];
$jsonOutput = array_key_exists('json', $options);
$report = FileSystem::collectFileDiagnostics();
$report['bootstrap_output'] = $bootstrapOutput;

if ($jsonOutput) {
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

echo "===== File System Diagnostics =====" . PHP_EOL;
echo "Generated: " . ($report['generated_at'] ?? date('c')) . PHP_EOL;
echo "Files total: " . ($report['summary']['total_files'] ?? 0) . PHP_EOL;
echo "Referenced: " . ($report['summary']['referenced_file_ids'] ?? 0) . PHP_EOL;
echo "Unreferenced: " . ($report['summary']['unreferenced_files'] ?? 0) . PHP_EOL;
echo "Missing on disk: " . ($report['summary']['missing_on_disk'] ?? 0) . PHP_EOL;
echo "Dangling references: " . ($report['summary']['dangling_references'] ?? 0) . PHP_EOL;
echo "Legacy payloads without file IDs: " . ($report['summary']['legacy_payloads_without_file_ids'] ?? 0) . PHP_EOL;

exit(0);
