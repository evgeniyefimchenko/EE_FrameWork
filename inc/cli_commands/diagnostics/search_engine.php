<?php

use classes\helpers\ClassSearchEngine;

$bootstrapOutput = $eeCliBootstrapOutput ?? '';
$options = is_array($eeCliOptions ?? null) ? $eeCliOptions : [];
$query = isset($options['query']) ? trim((string) $options['query']) : 'search';
$lang = isset($options['lang']) ? trim((string) $options['lang']) : ENV_DEF_LANG;
$isAdmin = array_key_exists('admin', $options);
$jsonOutput = array_key_exists('json', $options);
$shouldRebuild = array_key_exists('rebuild', $options);

$searchEngine = new ClassSearchEngine();
$report = [
    'timestamp' => date('c'),
    'query' => $query,
    'language_code' => $lang,
    'admin_scope' => $isAdmin,
    'bootstrap_output' => $bootstrapOutput,
    'schema' => $searchEngine->getSchemaDiagnostics(),
];

if ($shouldRebuild) {
    $report['rebuild'] = $searchEngine->rebuildAllIndex();
}

$report['plans'] = $searchEngine->explainSearchPlans($query, $lang, $isAdmin, 10, 0);
$report['smoke'] = $searchEngine->runSmokeTest($lang);
$report['summary'] = [
    'schema_ok' => !empty($report['schema']['ok']),
    'smoke_ok' => !in_array(false, $report['smoke']['assertions'] ?? [], true),
    'fulltext_explain_available' => !empty($report['plans']['fulltext_results']),
    'ngram_explain_available' => !empty($report['plans']['ngram_results']),
];
$report['summary']['ok'] = !in_array(false, $report['summary'], true);

if ($jsonOutput) {
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($report['summary']['ok'] ? 0 : 1);
}

echo "===== Search Engine Diagnostics =====" . PHP_EOL;
echo "Timestamp: {$report['timestamp']}" . PHP_EOL;
echo "Query: {$query}" . PHP_EOL;
echo "Lang: {$lang}" . PHP_EOL;
echo "Admin scope: " . ($isAdmin ? 'yes' : 'no') . PHP_EOL;
echo PHP_EOL;

echo "[Schema]" . PHP_EOL;
echo "OK: " . (!empty($report['schema']['ok']) ? 'yes' : 'no') . PHP_EOL;
echo "search_index rows: " . ($report['schema']['counts']['ee_search_index'] ?? $report['schema']['counts'][\classes\system\Constants::SEARCH_INDEX_TABLE] ?? 'n/a') . PHP_EOL;
echo "search_ngrams rows: " . ($report['schema']['counts']['ee_search_ngrams'] ?? $report['schema']['counts'][\classes\system\Constants::SEARCH_NGRAMS_TABLE] ?? 'n/a') . PHP_EOL;
echo "search_log rows: " . ($report['schema']['counts']['ee_search_log'] ?? $report['schema']['counts'][\classes\system\Constants::SEARCH_LOG_TABLE] ?? 'n/a') . PHP_EOL;
if (!empty($report['schema']['issues'])) {
    foreach ($report['schema']['issues'] as $issue) {
        echo " - {$issue}" . PHP_EOL;
    }
}
echo PHP_EOL;

echo "[Smoke]" . PHP_EOL;
foreach (($report['smoke']['assertions'] ?? []) as $name => $passed) {
    echo ' - ' . $name . ': ' . ($passed ? 'pass' : 'fail') . PHP_EOL;
}
echo PHP_EOL;

echo "[Explain]" . PHP_EOL;
echo ' - fulltext_count steps: ' . count($report['plans']['fulltext_count'] ?? []) . PHP_EOL;
echo ' - fulltext_results steps: ' . count($report['plans']['fulltext_results'] ?? []) . PHP_EOL;
echo ' - ngram_results steps: ' . count($report['plans']['ngram_results'] ?? []) . PHP_EOL;
if (!empty($report['plans']['issues'])) {
    foreach ($report['plans']['issues'] as $issue) {
        echo " - {$issue}" . PHP_EOL;
    }
}
echo PHP_EOL;

echo "Overall OK: " . ($report['summary']['ok'] ? 'yes' : 'no') . PHP_EOL;

exit($report['summary']['ok'] ? 0 : 1);
