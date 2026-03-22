<?php

use classes\helpers\FilterService;
use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\SysClass;

$bootstrapOutput = $eeCliBootstrapOutput ?? '';

$options = is_array($eeCliOptions ?? null) ? $eeCliOptions : [];
$lang = isset($options['lang']) ? strtoupper(trim((string) $options['lang'])) : ENV_DEF_LANG;
$categoryId = isset($options['category']) ? (int) $options['category'] : 0;
$jsonOutput = array_key_exists('json', $options);

if ($categoryId <= 0) {
    $categoryId = (int) SafeMySQL::gi()->getOne(
        "SELECT c.category_id
         FROM ?n AS c
         INNER JOIN ?n AS p
            ON p.category_id = c.category_id
           AND p.language_code = c.language_code
         INNER JOIN ?n AS pv
            ON pv.entity_id = p.page_id
           AND pv.entity_type = 'page'
           AND pv.language_code = p.language_code
         WHERE c.language_code = ?s
         GROUP BY c.category_id
         ORDER BY COUNT(DISTINCT p.page_id) DESC, c.category_id ASC
         LIMIT 1",
        Constants::CATEGORIES_TABLE,
        Constants::PAGES_TABLE,
        Constants::PROPERTY_VALUES_TABLE,
        $lang
    );
}

if ($categoryId <= 0) {
    fwrite(STDERR, "No suitable category found for diagnostics.\n");
    exit(1);
}

$service = new FilterService();
$modelFilters = SysClass::getModelObject('admin', 'm_filters');
$modelCategories = SysClass::getModelObject('admin', 'm_categories');

$resolvePageIds = function (int $categoryId, string $lang) use ($modelCategories, $modelFilters): array {
    $descendants = $modelCategories->getCategoryDescendantsShort($categoryId, $lang);
    $categoryIds = array_values(array_unique(array_filter(array_map(
        static fn(array $row): int => (int) ($row['category_id'] ?? 0),
        $descendants
    ), static fn(int $id): bool => $id > 0)));
    if ($categoryIds === []) {
        return [];
    }
    return $modelFilters->getPageIdsForCategories($categoryIds, $lang, ['active']);
};

$report = [
    'timestamp' => date('c'),
    'language_code' => $lang,
    'category_id' => $categoryId,
    'category' => SafeMySQL::gi()->getRow(
        'SELECT category_id, title, language_code FROM ?n WHERE category_id = ?i LIMIT 1',
        Constants::CATEGORIES_TABLE,
        $categoryId
    ),
    'bootstrap_output' => $bootstrapOutput,
];

$beforeCount = (int) SafeMySQL::gi()->getOne(
    'SELECT COUNT(*) FROM ?n WHERE entity_type = ?s AND entity_id = ?i AND language_code = ?s',
    Constants::FILTERS_TABLE,
    'category',
    $categoryId,
    $lang
);
$pageIds = $resolvePageIds($categoryId, $lang);

$start = microtime(true);
$report['regenerate'] = $service->regenerateFiltersForEntity('category', $categoryId, $lang);
$report['timings']['regenerate_ms'] = round((microtime(true) - $start) * 1000, 2);
$report['counts']['filters_before'] = $beforeCount;
$report['counts']['filters_after'] = (int) SafeMySQL::gi()->getOne(
    'SELECT COUNT(*) FROM ?n WHERE entity_type = ?s AND entity_id = ?i AND language_code = ?s',
    Constants::FILTERS_TABLE,
    'category',
    $categoryId,
    $lang
);
$report['counts']['page_count'] = count($pageIds);

$start = microtime(true);
$available = $service->getAvailableFiltersForCategory($categoryId, $lang);
$report['timings']['available_filters_ms'] = round((microtime(true) - $start) * 1000, 2);

$start = microtime(true);
$flat = $service->getFlatAvailableFiltersForCategory($categoryId, $lang);
$report['timings']['flat_filters_ms'] = round((microtime(true) - $start) * 1000, 2);

$report['counts']['available_filters'] = count($available);
$report['counts']['flat_filters'] = count($flat);

$optionField = null;
$rangeField = null;
foreach ($flat as $field) {
    if ($optionField === null && ($field['filter_type'] ?? '') === 'options' && !empty($field['options'][0]['id'])) {
        $optionField = $field;
    }
    if ($rangeField === null && ($field['filter_type'] ?? '') === 'range') {
        $rangeField = $field;
    }
}

if ($optionField !== null) {
    $criterion = [
        $optionField['property_id'] . ':' . $optionField['uid'] => [
            (string) ($optionField['options'][0]['id'] ?? ''),
        ],
    ];
    $start = microtime(true);
    $matchedPageIds = $service->getFilteredPageIdsForCategory($categoryId, $criterion, $lang);
    $report['sample_option_filter'] = [
        'criterion' => $criterion,
        'matched_page_count' => count($matchedPageIds),
        'matched_page_ids' => array_slice($matchedPageIds, 0, 20),
    ];
    $report['timings']['sample_option_filter_ms'] = round((microtime(true) - $start) * 1000, 2);

    $allRows = $modelFilters->getFilterSourceForPages($pageIds, $lang, ['active']);
    $targetedRows = $modelFilters->getFilterSourceForPages($pageIds, $lang, ['active'], [(int) $optionField['property_id']]);
    $report['source_row_reduction'] = [
        'all_rows' => count($allRows),
        'targeted_rows' => count($targetedRows),
    ];
}

if ($rangeField !== null) {
    $criterion = [[
        'property_id' => (int) $rangeField['property_id'],
        'uid' => (string) $rangeField['uid'],
        'min' => $rangeField['min_value'] ?? null,
        'max' => $rangeField['max_value'] ?? null,
    ]];
    $start = microtime(true);
    $matchedPageIds = $service->getFilteredPageIdsForCategory($categoryId, $criterion, $lang);
    $report['sample_range_filter'] = [
        'criterion' => $criterion,
        'matched_page_count' => count($matchedPageIds),
        'matched_page_ids' => array_slice($matchedPageIds, 0, 20),
    ];
    $report['timings']['sample_range_filter_ms'] = round((microtime(true) - $start) * 1000, 2);
}

$languageRows = SafeMySQL::gi()->getAll(
    'SELECT property_id, language_code FROM ?n WHERE entity_type = ?s AND entity_id = ?i ORDER BY property_id ASC',
    Constants::FILTERS_TABLE,
    'category',
    $categoryId
);
$wrongLanguageRows = array_values(array_filter($languageRows, static fn(array $row): bool => (string) ($row['language_code'] ?? '') !== $lang));
$report['language_validation'] = [
    'rows' => $languageRows,
    'wrong_language_rows' => $wrongLanguageRows,
    'ok' => $wrongLanguageRows === [],
];

$report['summary'] = [
    'regenerate_ok' => ($report['regenerate']['status'] ?? '') === 'success',
    'filters_materialized' => ($report['counts']['filters_after'] ?? 0) > 0,
    'language_ok' => !empty($report['language_validation']['ok']),
    'option_filter_ok' => !empty($report['sample_option_filter']['matched_page_count']) || $optionField === null,
    'range_filter_ok' => !empty($report['sample_range_filter']['matched_page_count']) || $rangeField === null,
];
$report['summary']['ok'] = !in_array(false, $report['summary'], true);

if ($jsonOutput) {
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($report['summary']['ok'] ? 0 : 1);
}

echo "===== Filter Service Diagnostics =====" . PHP_EOL;
echo "Timestamp: {$report['timestamp']}" . PHP_EOL;
echo "Category: #" . $categoryId . ' ' . ($report['category']['title'] ?? '') . PHP_EOL;
echo "Lang: {$lang}" . PHP_EOL;
echo "Pages in scope: " . ($report['counts']['page_count'] ?? 0) . PHP_EOL;
echo PHP_EOL;

echo "[Regenerate]" . PHP_EOL;
echo "Status: " . ($report['regenerate']['status'] ?? 'n/a') . PHP_EOL;
echo "Filters after: " . ($report['counts']['filters_after'] ?? 0) . PHP_EOL;
echo "Time: " . ($report['timings']['regenerate_ms'] ?? 'n/a') . " ms" . PHP_EOL;
echo PHP_EOL;

echo "[Frontend Path]" . PHP_EOL;
echo "Available filters: " . ($report['counts']['available_filters'] ?? 0) . PHP_EOL;
echo "Flat fields: " . ($report['counts']['flat_filters'] ?? 0) . PHP_EOL;
if (!empty($report['sample_option_filter'])) {
    echo "Option matched pages: " . ($report['sample_option_filter']['matched_page_count'] ?? 0) . PHP_EOL;
    echo "Option time: " . ($report['timings']['sample_option_filter_ms'] ?? 'n/a') . " ms" . PHP_EOL;
}
if (!empty($report['sample_range_filter'])) {
    echo "Range matched pages: " . ($report['sample_range_filter']['matched_page_count'] ?? 0) . PHP_EOL;
    echo "Range time: " . ($report['timings']['sample_range_filter_ms'] ?? 'n/a') . " ms" . PHP_EOL;
}
if (!empty($report['source_row_reduction'])) {
    echo "Source rows all: " . ($report['source_row_reduction']['all_rows'] ?? 0) . PHP_EOL;
    echo "Source rows targeted: " . ($report['source_row_reduction']['targeted_rows'] ?? 0) . PHP_EOL;
}
echo PHP_EOL;

echo "Language OK: " . (!empty($report['language_validation']['ok']) ? 'yes' : 'no') . PHP_EOL;
echo "Overall OK: " . (!empty($report['summary']['ok']) ? 'yes' : 'no') . PHP_EOL;

exit(!empty($report['summary']['ok']) ? 0 : 1);
