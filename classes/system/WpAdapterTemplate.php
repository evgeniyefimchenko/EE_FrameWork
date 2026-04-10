<?php
declare(strict_types=1);

/**
 * EE Universal Exporter (WordPress adapter).
 * Совместимость: PHP 7.0+
 *
 * Поместите этот файл в корень WordPress и откройте его в браузере
 * под администратором.
 *
 * Скрипт по шагам формирует JSONL-пакет универсального формата
 * для импорта сущностей в EE_FrameWork.
 */

$wpLoad = __DIR__ . DIRECTORY_SEPARATOR . 'wp-load.php';
if (!is_file($wpLoad)) {
    http_response_code(500);
    echo 'Файл wp-load.php не найден. Поместите этот файл в корень WordPress.';
    exit;
}

require_once $wpLoad;

if (!function_exists('wp_upload_dir')) {
    http_response_code(500);
    echo 'Ошибка инициализации WordPress.';
    exit;
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }
        return strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }
        return strpos($haystack, $needle) !== false;
    }
}

@set_time_limit(120);
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');

$ctx = ee_export_context();
$hasAccess = ee_export_has_access();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ee_action'])) {
    if (!$hasAccess) {
        ee_export_json([
            'success' => false,
            'done' => false,
            'message' => 'Доступ запрещен. Войдите как администратор.',
        ], 403);
        exit;
    }
    ee_export_handle_ajax($ctx);
    exit;
}

if (!$hasAccess) {
    echo ee_export_render_access_denied();
    exit;
}

echo ee_export_render_page($ctx);
exit;

function ee_export_context(): array {
    global $wpdb;

    $uploads = wp_upload_dir(null, false);
    $baseDir = rtrim((string)$uploads['basedir'], '/\\') . '/ee_wp_export';
    $baseUrl = rtrim((string)$uploads['baseurl'], '/\\') . '/ee_wp_export';

    $files = [
        'users' => 'users.jsonl',
        'category_types' => 'category_types.jsonl',
        'property_types' => 'property_types.jsonl',
        'property_sets' => 'property_sets.jsonl',
        'properties' => 'properties.jsonl',
        'type_set_links' => 'type_set_links.jsonl',
        'set_property_links' => 'set_property_links.jsonl',
        'categories' => 'categories.jsonl',
        'pages' => 'pages.jsonl',
        'property_values' => 'property_values.jsonl',
    ];

    return [
        'wpdb' => $wpdb,
        'prefix' => (string)$wpdb->prefix,
        'caps_meta_key' => (string)$wpdb->prefix . 'capabilities',
        'tables' => [
            'users' => (string)$wpdb->users,
            'usermeta' => (string)$wpdb->usermeta,
            'terms' => (string)$wpdb->terms,
            'term_taxonomy' => (string)$wpdb->term_taxonomy,
            'term_relationships' => (string)$wpdb->term_relationships,
            'termmeta' => property_exists($wpdb, 'termmeta') ? (string)$wpdb->termmeta : ((string)$wpdb->prefix . 'termmeta'),
            'posts' => (string)$wpdb->posts,
            'postmeta' => (string)$wpdb->postmeta,
        ],
        'base_dir' => $baseDir,
        'base_url' => $baseUrl,
        'state_file' => $baseDir . '/state.json',
        'manifest_file' => $baseDir . '/manifest.json',
        'files' => $files,
    ];
}

function ee_export_has_access(): bool {
    if (PHP_SAPI === 'cli') {
        return true;
    }
    if (!function_exists('is_user_logged_in') || !function_exists('current_user_can')) {
        return true;
    }
    return is_user_logged_in() && current_user_can('manage_options');
}

function ee_export_render_access_denied(): string {
    $loginUrl = function_exists('wp_login_url')
        ? wp_login_url((string)($_SERVER['REQUEST_URI'] ?? ''))
        : '/wp-login.php';

    return '<!doctype html><html><head><meta charset="utf-8"><title>EE Экспортер</title>'
        . '<style>body{font-family:Arial,sans-serif;margin:32px;color:#222}a{color:#0a58ca}</style>'
        . '</head><body><h1>EE WordPress Экспортер</h1>'
        . '<p>Доступ запрещен. Войдите как администратор и откройте страницу снова.</p>'
        . '<p><a href="' . esc_url($loginUrl) . '">Перейти ко входу</a></p>'
        . '</body></html>';
}

function ee_export_relative_path_from_url($url): string {
    $path = trim((string)parse_url((string)$url, PHP_URL_PATH));
    if ($path === '') {
        return '';
    }

    $path = str_replace('\\', '/', $path);
    $path = preg_replace('~/+~', '/', $path) ?? $path;
    $path = '/' . trim($path, '/');

    return $path === '/' ? '' : $path;
}

function ee_export_handle_ajax(array $ctx) {
    $action = isset($_POST['ee_action']) ? trim((string)$_POST['ee_action']) : '';
    $batch = max(20, min(2000, (int)($_POST['batch'] ?? 300)));

    try {
        switch ($action) {
            case 'start':
                $selectedTaxonomies = ee_export_selected_list_from_post('selected_taxonomies');
                $selectedPostTypes = ee_export_selected_list_from_post('selected_post_types');
                $acfMode = ee_export_normalize_acf_mode((string)($_POST['acf_mode'] ?? 'all'));
                $includePrivateMetaKeys = !empty($_POST['include_private_meta_keys']) && (string)($_POST['include_private_meta_keys']) !== '0';
                $state = ee_export_start($ctx, $selectedTaxonomies, $selectedPostTypes, $acfMode, $includePrivateMetaKeys);
                ee_export_json(array_merge([
                    'success' => true,
                    'done' => false,
                    'phase' => (string)$state['phase'],
                    'message' => 'Экспорт запущен. Режим ACF: ' . ee_export_acf_mode_label((string)($state['acf_export_mode'] ?? 'all')),
                ], ee_export_workspace_payload($ctx)));
                return;

            case 'status':
                $state = ee_export_load_state($ctx);
                if (!is_array($state)) {
                    ee_export_json(array_merge([
                        'success' => false,
                        'done' => false,
                        'message' => 'Состояние экспорта не найдено. Нажмите "Начать новый экспорт".',
                    ], ee_export_workspace_payload($ctx)));
                    return;
                }
                ee_export_json(array_merge([
                    'success' => true,
                    'done' => !empty($state['done']),
                    'phase' => (string)($state['phase'] ?? 'unknown'),
                    'stats' => (array)($state['stats'] ?? []),
                    'download_url' => (string)($state['zip_url'] ?? ''),
                ], ee_export_workspace_payload($ctx)));
                return;

            case 'step':
                $state = ee_export_load_state($ctx);
                if (!is_array($state)) {
                    ee_export_json(array_merge([
                        'success' => false,
                        'done' => false,
                        'message' => 'Состояние экспорта не найдено. Нажмите "Начать новый экспорт".',
                    ], ee_export_workspace_payload($ctx)));
                    return;
                }

                $started = microtime(true);
                $result = ee_export_run_step($ctx, $state, $batch);
                ee_export_save_state($ctx, $result['state']);
                ee_export_json(array_merge([
                    'success' => true,
                    'done' => !empty($result['state']['done']),
                    'phase' => (string)($result['state']['phase'] ?? 'unknown'),
                    'log' => (string)($result['log'] ?? ''),
                    'stats' => (array)($result['state']['stats'] ?? []),
                    'download_url' => (string)($result['state']['zip_url'] ?? ''),
                    'duration_ms' => (int)round((microtime(true) - $started) * 1000),
                ], ee_export_workspace_payload($ctx)));
                return;

            default:
                ee_export_json(array_merge([
                    'success' => false,
                    'done' => false,
                    'message' => 'Неизвестное действие.',
                ], ee_export_workspace_payload($ctx)));
                return;
        }
    } catch (Throwable $e) {
        ee_export_json(array_merge([
            'success' => false,
            'done' => false,
            'message' => $e->getMessage(),
        ], ee_export_workspace_payload($ctx)));
    }
}

function ee_export_selected_list_from_post(string $key): array {
    $raw = isset($_POST[$key]) ? $_POST[$key] : [];
    if (!is_array($raw)) {
        $raw = preg_split('/[\r\n,;]+/', (string)$raw);
    }
    if (!is_array($raw)) {
        return [];
    }

    $result = [];
    foreach ($raw as $item) {
        $item = strtolower(trim((string)$item));
        if ($item === '') {
            continue;
        }
        $result[$item] = $item;
    }
    return array_values($result);
}

function ee_export_normalize_acf_mode(string $mode): string {
    $mode = strtolower(trim($mode));
    if (!in_array($mode, ['all', 'only_acf', 'without_acf'], true)) {
        return 'all';
    }
    return $mode;
}

function ee_export_acf_mode_label(string $mode): string {
    $mode = ee_export_normalize_acf_mode($mode);
    if ($mode === 'only_acf') {
        return 'только ACF';
    }
    if ($mode === 'without_acf') {
        return 'без ACF';
    }
    return 'все поля';
}

function ee_export_start(
    array $ctx,
    array $selectedTaxonomies = [],
    array $selectedPostTypes = [],
    string $acfMode = 'all',
    bool $includePrivateMetaKeys = false
): array {
    ee_export_ensure_dir((string)$ctx['base_dir']);

    $oldState = ee_export_load_state($ctx);
    if (is_array($oldState) && !empty($oldState['zip_file'])) {
        $oldZip = (string)$oldState['zip_file'];
        if (is_file($oldZip)) {
            @unlink($oldZip);
        }
    }

    foreach ((array)$ctx['files'] as $path) {
        $full = rtrim((string)$ctx['base_dir'], '/\\') . '/' . ltrim((string)$path, '/\\');
        @file_put_contents($full, '');
    }

    $state = ee_export_default_state(
        $ctx,
        $selectedTaxonomies,
        $selectedPostTypes,
        ee_export_normalize_acf_mode($acfMode),
        $includePrivateMetaKeys
    );
    ee_export_write_manifest($ctx, $state);
    ee_export_save_state($ctx, $state);
    ee_export_assert_workspace_has_space($ctx);
    return $state;
}

function ee_export_default_state(
    array $ctx,
    array $selectedTaxonomies = [],
    array $selectedPostTypes = [],
    string $acfMode = 'all',
    bool $includePrivateMetaKeys = false
): array {
    $taxonomyCatalog = ee_export_collect_taxonomy_catalog();
    $postTypeCatalog = ee_export_collect_post_type_catalog();

    $availableTaxonomies = array_keys($taxonomyCatalog);
    $availablePostTypes = array_keys($postTypeCatalog);

    $selectedTaxonomies = array_values(array_intersect($selectedTaxonomies, $availableTaxonomies));
    $selectedPostTypes = array_values(array_intersect($selectedPostTypes, $availablePostTypes));

    if (empty($selectedTaxonomies)) {
        $selectedTaxonomies = $availableTaxonomies;
    }
    if (empty($selectedPostTypes)) {
        $selectedPostTypes = $availablePostTypes;
    }

    sort($selectedTaxonomies);
    sort($selectedPostTypes);

    $taxonomyLabels = [];
    $taxonomyDescriptions = [];
    foreach ($selectedTaxonomies as $taxonomy) {
        $taxonomyLabels[$taxonomy] = (string)($taxonomyCatalog[$taxonomy]['label'] ?? $taxonomy);
        $taxonomyDescriptions[$taxonomy] = (string)($taxonomyCatalog[$taxonomy]['description'] ?? '');
    }

    $postTypeLabels = [];
    $postTypeDescriptions = [];
    foreach ($selectedPostTypes as $postType) {
        $postTypeLabels[$postType] = (string)($postTypeCatalog[$postType]['label'] ?? $postType);
        $postTypeDescriptions[$postType] = (string)($postTypeCatalog[$postType]['description'] ?? '');
    }

    return [
        'version' => 2,
        'started_at' => gmdate('c'),
        'source_key' => sha1(home_url('/') . '|' . (string)$ctx['prefix']),
        'acf_export_mode' => ee_export_normalize_acf_mode($acfMode),
        'include_private_meta_keys' => $includePrivateMetaKeys ? 1 : 0,
        'phase' => 'users',
        'done' => false,
        'zip_file' => '',
        'zip_url' => '',
        'taxonomies' => $selectedTaxonomies,
        'taxonomy_labels' => $taxonomyLabels,
        'taxonomy_descriptions' => $taxonomyDescriptions,
        'post_types' => $selectedPostTypes,
        'post_type_labels' => $postTypeLabels,
        'post_type_descriptions' => $postTypeDescriptions,
        'phase_state' => [
            'users' => ['last_id' => 0],
            'category_types' => ['offset' => 0],
            'property_types' => ['offset' => 0],
            'property_sets' => ['offset' => 0],
            'properties' => ['source' => 'termmeta', 'offset' => 0],
            'type_set_links' => ['offset' => 0],
            'set_property_links' => ['source' => 'termmeta', 'offset' => 0],
            'categories' => ['source' => 'terms', 'last_id' => 0, 'offset' => 0],
            'pages' => ['last_id' => 0],
            'property_values' => ['source' => 'termmeta', 'last_id' => 0],
            'finalize' => ['done' => 0],
        ],
        'stats' => [],
    ];
}

function ee_export_load_state(array $ctx) {
    $stateFile = (string)$ctx['state_file'];
    if (!is_file($stateFile)) {
        return null;
    }
    $decoded = json_decode((string)file_get_contents($stateFile), true);
    return is_array($decoded) ? $decoded : null;
}

function ee_export_save_state(array $ctx, array $state) {
    ee_export_ensure_dir((string)$ctx['base_dir']);
    $json = ee_export_json_encode($state, JSON_PRETTY_PRINT);
    if (!is_string($json)) {
        throw new RuntimeException('Не удалось сериализовать состояние экспортера.');
    }
    file_put_contents((string)$ctx['state_file'], $json);
}

function ee_export_workspace_limit_bytes(): int {
    $default = 1024 * 1024 * 1024; // 1 GB

    if (defined('EE_EXPORT_WORKSPACE_LIMIT_BYTES')) {
        $defined = (int)constant('EE_EXPORT_WORKSPACE_LIMIT_BYTES');
        if ($defined >= 0) {
            return $defined;
        }
    }

    if (function_exists('apply_filters')) {
        $filtered = (int)apply_filters('ee_export_workspace_limit_bytes', $default);
        if ($filtered >= 0) {
            return $filtered;
        }
    }

    return $default;
}

function ee_export_workspace_size(array $ctx): int {
    $baseDir = rtrim((string)($ctx['base_dir'] ?? ''), '/\\');
    if ($baseDir === '' || !is_dir($baseDir)) {
        return 0;
    }

    $files = glob($baseDir . '/*');
    if (!is_array($files)) {
        return 0;
    }

    $size = 0;
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        $fileSize = filesize($file);
        if ($fileSize !== false && $fileSize > 0) {
            $size += (int)$fileSize;
        }
    }

    return $size;
}

function ee_export_format_bytes(int $bytes): string {
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = (float)$bytes;
    $i = 0;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return number_format($value, $i === 0 ? 0 : 2, '.', '') . ' ' . $units[$i];
}

function ee_export_workspace_payload(array $ctx): array {
    $size = ee_export_workspace_size($ctx);
    $limit = ee_export_workspace_limit_bytes();

    return [
        'workspace_dir' => (string)($ctx['base_dir'] ?? ''),
        'workspace_bytes' => $size,
        'workspace_human' => ee_export_format_bytes($size),
        'workspace_limit_bytes' => $limit,
        'workspace_limit_human' => $limit > 0 ? ee_export_format_bytes($limit) : 'без ограничения',
    ];
}

function ee_export_assert_workspace_has_space(array $ctx, int $extraBytes = 0) {
    $limit = ee_export_workspace_limit_bytes();
    if ($limit <= 0) {
        return;
    }

    $current = ee_export_workspace_size($ctx);
    $projected = $current + max(0, $extraBytes);
    if ($projected > $limit) {
        throw new RuntimeException(
            'Превышен лимит размера рабочей папки экспорта: ' .
            ee_export_format_bytes($projected) . ' > ' . ee_export_format_bytes($limit) .
            '. Очистите место или увеличьте EE_EXPORT_WORKSPACE_LIMIT_BYTES.'
        );
    }
}

function ee_export_run_step(array $ctx, array $state, int $batch): array {
    $phase = (string)($state['phase'] ?? 'users');
    $log = [];

    if (!empty($state['done']) || $phase === 'done') {
        $state['done'] = true;
        $state['phase'] = 'done';
        return ['state' => $state, 'log' => "Экспорт уже завершен.\n"];
    }

    ee_export_assert_workspace_has_space($ctx);

    switch ($phase) {
        case 'users':
            $phaseResult = ee_export_phase_users($ctx, $state, $batch);
            break;
        case 'category_types':
            $phaseResult = ee_export_phase_category_types($ctx, $state, $batch);
            break;
        case 'property_types':
            $phaseResult = ee_export_phase_property_types($ctx, $state, $batch);
            break;
        case 'property_sets':
            $phaseResult = ee_export_phase_property_sets($ctx, $state, $batch);
            break;
        case 'properties':
            $phaseResult = ee_export_phase_properties($ctx, $state, $batch);
            break;
        case 'type_set_links':
            $phaseResult = ee_export_phase_type_set_links($ctx, $state, $batch);
            break;
        case 'set_property_links':
            $phaseResult = ee_export_phase_set_property_links($ctx, $state, $batch);
            break;
        case 'categories':
            $phaseResult = ee_export_phase_categories($ctx, $state, $batch);
            break;
        case 'pages':
            $phaseResult = ee_export_phase_pages($ctx, $state, $batch);
            break;
        case 'property_values':
            $phaseResult = ee_export_phase_property_values($ctx, $state, $batch);
            break;
        case 'finalize':
            $phaseResult = ee_export_phase_finalize($ctx, $state, $batch);
            break;
        default:
            $phaseResult = [
                'done' => true,
                'processed' => 0,
                'written' => 0,
                'skipped' => 0,
                'log' => "Неизвестная фаза: {$phase}\n",
            ];
            break;
    }

    ee_export_update_stats(
        $state,
        $phase,
        (int)($phaseResult['processed'] ?? 0),
        (int)($phaseResult['written'] ?? 0),
        (int)($phaseResult['skipped'] ?? 0)
    );

    $log[] = (string)($phaseResult['log'] ?? '');

    if (!empty($phaseResult['done'])) {
        $next = ee_export_next_phase($phase);
        $state['phase'] = $next;
        $log[] = "Фаза {$phase} завершена. Следующая: {$next}\n";
        if ($next === 'done') {
            $state['done'] = true;
        }
    }

    ee_export_assert_workspace_has_space($ctx);

    return [
        'state' => $state,
        'log' => implode('', $log),
    ];
}

function ee_export_next_phase(string $phase): string {
    $phases = [
        'users',
        'category_types',
        'property_types',
        'property_sets',
        'properties',
        'type_set_links',
        'set_property_links',
        'categories',
        'pages',
        'property_values',
        'finalize',
        'done',
    ];
    $index = array_search($phase, $phases, true);
    if ($index === false) {
        return 'done';
    }
    return isset($phases[$index + 1]) ? $phases[$index + 1] : 'done';
}

function ee_export_update_stats(array &$state, string $phase, int $processed, int $written, int $skipped) {
    if (!isset($state['stats'][$phase]) || !is_array($state['stats'][$phase])) {
        $state['stats'][$phase] = ['processed' => 0, 'written' => 0, 'skipped' => 0];
    }
    $state['stats'][$phase]['processed'] += $processed;
    $state['stats'][$phase]['written'] += $written;
    $state['stats'][$phase]['skipped'] += $skipped;
}

function ee_export_phase_users(array $ctx, array &$state, int $batch): array {
    $wpdb = $ctx['wpdb'];
    $usersTable = (string)$ctx['tables']['users'];
    $usermetaTable = (string)$ctx['tables']['usermeta'];

    if (!ee_export_table_exists($ctx, $usersTable)) {
        return ['done' => true, 'processed' => 0, 'written' => 0, 'skipped' => 0, 'log' => "users: таблица отсутствует, пропуск.\n"];
    }

    $phaseState = &$state['phase_state']['users'];
    $lastId = (int)($phaseState['last_id'] ?? 0);

    $sql = $wpdb->prepare(
        "SELECT ID, user_login, user_pass, user_email, display_name, user_status FROM {$usersTable} WHERE ID > %d ORDER BY ID ASC LIMIT %d",
        $lastId,
        $batch
    );
    $rows = $wpdb->get_results($sql, ARRAY_A);
    if (!is_array($rows)) {
        $rows = [];
    }

    if (empty($rows)) {
        return ['done' => true, 'processed' => 0, 'written' => 0, 'skipped' => 0, 'log' => "users: завершено.\n"];
    }

    $processed = 0;
    $written = 0;
    $skipped = 0;
    $capsByUser = [];

    $userIds = array_values(array_filter(array_map(static function (array $row) {
        return (int)($row['ID'] ?? 0);
    }, $rows)));
    if (!empty($userIds) && ee_export_table_exists($ctx, $usermetaTable)) {
        $idsSql = implode(',', array_map('intval', $userIds));
        $capsMetaKey = esc_sql((string)$ctx['caps_meta_key']);
        $metaSql = "SELECT user_id, meta_value FROM {$usermetaTable} WHERE user_id IN ({$idsSql}) AND meta_key = '{$capsMetaKey}'";
        $metaRows = $wpdb->get_results($metaSql, ARRAY_A);
        if (is_array($metaRows)) {
            foreach ($metaRows as $meta) {
                $uid = (int)($meta['user_id'] ?? 0);
                if ($uid <= 0) {
                    continue;
                }
                $caps = maybe_unserialize((string)($meta['meta_value'] ?? ''));
                if (is_array($caps)) {
                    $capsByUser[$uid] = $caps;
                }
            }
        }
    }

    foreach ($rows as $row) {
        $processed++;
        $userId = (int)($row['ID'] ?? 0);
        if ($userId <= 0) {
            $skipped++;
            continue;
        }
        $lastId = $userId;

        $email = trim((string)($row['user_email'] ?? ''));
        if ($email === '') {
            $email = 'wp_user_' . $userId . '@example.local';
        }
        $displayName = trim((string)($row['display_name'] ?? ''));
        $login = trim((string)($row['user_login'] ?? ''));
        $name = $displayName !== '' ? $displayName : ($login !== '' ? $login : ('wp_user_' . $userId));
        $role = ee_export_role_from_caps($capsByUser[$userId] ?? []);

        $ok = ee_export_append_jsonl($ctx, 'users', [
            'source_id' => (string)$userId,
            'login' => $login,
            'name' => $name,
            'email' => $email,
            'pwd_hash' => (string)($row['user_pass'] ?? ''),
            'user_role' => $role,
            'active' => ((int)($row['user_status'] ?? 0) === 0 ? 2 : 1),
            'subscribed' => 1,
            'deleted' => 0,
            'comment' => '',
            'phone' => '',
        ]);

        if ($ok) {
            $written++;
        } else {
            $skipped++;
        }
    }

    $phaseState['last_id'] = $lastId;
    $done = count($rows) < $batch;

    return [
        'done' => $done,
        'processed' => $processed,
        'written' => $written,
        'skipped' => $skipped,
        'log' => "users: обработано={$processed}, записано={$written}, пропущено={$skipped}, последний_id={$lastId}\n",
    ];
}

function ee_export_phase_category_types(array $ctx, array &$state, int $batch): array {
    $rows = ee_export_build_category_type_rows($state);
    $phaseState = &$state['phase_state']['category_types'];
    $offset = (int)($phaseState['offset'] ?? 0);
    $slice = array_slice($rows, $offset, $batch);

    $processed = 0;
    $written = 0;
    $skipped = 0;
    foreach ($slice as $row) {
        $processed++;
        if (ee_export_append_jsonl($ctx, 'category_types', $row)) {
            $written++;
        } else {
            $skipped++;
        }
    }

    $offset += count($slice);
    $phaseState['offset'] = $offset;
    $done = $offset >= count($rows);

    return [
        'done' => $done,
        'processed' => $processed,
        'written' => $written,
        'skipped' => $skipped,
        'log' => "category_types: обработано={$processed}, записано={$written}, пропущено={$skipped}, смещение={$offset}/" . count($rows) . "\n",
    ];
}

function ee_export_build_property_type_rows(): array {
    return [
        ['source_id' => 'wp_type:string', 'name' => 'Строка', 'fields' => ['text'], 'status' => 'active', 'language_code' => 'RU'],
        ['source_id' => 'wp_type:textarea', 'name' => 'Текст (многострочный)', 'fields' => ['textarea'], 'status' => 'active', 'language_code' => 'RU'],
        ['source_id' => 'wp_type:number', 'name' => 'Число', 'fields' => ['number'], 'status' => 'active', 'language_code' => 'RU'],
        ['source_id' => 'wp_type:boolean', 'name' => 'Да/Нет', 'fields' => ['checkbox'], 'status' => 'active', 'language_code' => 'RU'],
        ['source_id' => 'wp_type:date', 'name' => 'Дата', 'fields' => ['date'], 'status' => 'active', 'language_code' => 'RU'],
        ['source_id' => 'wp_type:image', 'name' => 'Изображение', 'fields' => ['image'], 'status' => 'active', 'language_code' => 'RU'],
        ['source_id' => 'wp_type:file', 'name' => 'Файл', 'fields' => ['file'], 'status' => 'active', 'language_code' => 'RU'],
    ];
}

function ee_export_phase_property_types(array $ctx, array &$state, int $batch): array {
    $rows = ee_export_build_property_type_rows();
    $phaseState = &$state['phase_state']['property_types'];
    $offset = (int)(isset($phaseState['offset']) ? $phaseState['offset'] : 0);
    $slice = array_slice($rows, $offset, $batch);

    $processed = 0;
    $written = 0;
    $skipped = 0;
    foreach ($slice as $row) {
        $processed++;
        if (ee_export_append_jsonl($ctx, 'property_types', $row)) {
            $written++;
        } else {
            $skipped++;
        }
    }

    $offset += count($slice);
    $phaseState['offset'] = $offset;
    $done = $offset >= count($rows);

    return [
        'done' => $done,
        'processed' => $processed,
        'written' => $written,
        'skipped' => $skipped,
        'log' => "property_types: обработано={$processed}, записано={$written}, пропущено={$skipped}, смещение={$offset}/" . count($rows) . "\n",
    ];
}

function ee_export_phase_property_sets(array $ctx, array &$state, int $batch): array {
    $rows = ee_export_build_property_set_rows($state);
    $phaseState = &$state['phase_state']['property_sets'];
    $offset = (int)($phaseState['offset'] ?? 0);
    $slice = array_slice($rows, $offset, $batch);

    $processed = 0;
    $written = 0;
    $skipped = 0;
    foreach ($slice as $row) {
        $processed++;
        if (ee_export_append_jsonl($ctx, 'property_sets', $row)) {
            $written++;
        } else {
            $skipped++;
        }
    }

    $offset += count($slice);
    $phaseState['offset'] = $offset;
    $done = $offset >= count($rows);

    return [
        'done' => $done,
        'processed' => $processed,
        'written' => $written,
        'skipped' => $skipped,
        'log' => "property_sets: обработано={$processed}, записано={$written}, пропущено={$skipped}, смещение={$offset}/" . count($rows) . "\n",
    ];
}

function ee_export_phase_properties(array $ctx, array &$state, int $batch): array {
    $wpdb = $ctx['wpdb'];
    $phaseState = &$state['phase_state']['properties'];
    $source = (string)($phaseState['source'] ?? 'termmeta');
    $offset = (int)($phaseState['offset'] ?? 0);

    $sources = ['termmeta', 'postmeta', 'acf_termmeta', 'acf_postmeta'];
    if (!in_array($source, $sources, true)) {
        $source = 'termmeta';
        $offset = 0;
    }

    $processed = 0;
    $written = 0;
    $skipped = 0;
    $done = false;
    $log = '';

    while (true) {
        if ($source === 'acf_termmeta' || $source === 'acf_postmeta') {
            $metaKind = $source === 'acf_termmeta' ? 'termmeta' : 'postmeta';
            $entityType = $metaKind === 'termmeta' ? 'category' : 'page';
            $acfCatalog = ee_export_collect_acf_field_catalog_by_source($state);
            $catalogSection = is_array($acfCatalog[$metaKind] ?? null) ? $acfCatalog[$metaKind] : [];
            $existingKeys = array_fill_keys(
                ee_export_collect_existing_meta_keys_for_source($ctx, $state, $metaKind),
                true
            );

            $acfLabelsByKey = [];
            foreach ($catalogSection as $fields) {
                if (!is_array($fields)) {
                    continue;
                }
                foreach ($fields as $metaKey => $metaInfo) {
                    $metaKey = strtolower(trim((string)$metaKey));
                    if ($metaKey === '' || isset($existingKeys[$metaKey])) {
                        continue;
                    }
                    if (!ee_export_should_include_meta_key($state, $metaKey)) {
                        continue;
                    }
                    $label = trim((string)($metaInfo['label'] ?? ''));
                    if (!isset($acfLabelsByKey[$metaKey])) {
                        $acfLabelsByKey[$metaKey] = $label;
                    } elseif ($acfLabelsByKey[$metaKey] === '' && $label !== '') {
                        $acfLabelsByKey[$metaKey] = $label;
                    }
                }
            }

            $allKeys = array_keys($acfLabelsByKey);
            sort($allKeys);
            $slice = array_slice($allKeys, $offset, $batch);

            foreach ($slice as $metaKey) {
                $processed++;

                $typeGuess = ee_export_guess_property_type_for_meta_key($metaKey);
                $typeSource = isset($typeGuess[2]) ? $typeGuess[2] : 'wp_type:string';
                $typeFields = isset($typeGuess[1]) ? $typeGuess[1] : ['text'];
                $fieldType = isset($typeFields[0]) ? $typeFields[0] : 'text';
                $isMultiple = isset($typeGuess[3]) ? (int)$typeGuess[3] : 0;

                $acfLabel = trim((string)($acfLabelsByKey[$metaKey] ?? ''));
                if ($acfLabel === '') {
                    $acfLabel = ee_export_acf_label_for_meta_key($metaKey);
                }
                $displayName = $acfLabel !== '' ? $acfLabel : $metaKey;

                $canonicalDefaultValues = [
                    [
                        'type' => $fieldType,
                        'label' => $displayName,
                        'default' => '',
                        'multiple' => $isMultiple,
                        'required' => 0
                    ]
                ];

                $ok = ee_export_append_jsonl($ctx, 'properties', [
                    'source_id' => $metaKind . ':' . $metaKey,
                    'name' => $metaKey,
                    'display_name' => $displayName,
                    'acf_label' => $acfLabel,
                    'type_source_id' => $typeSource,
                    'entity_type' => $entityType,
                    'status' => 'active',
                    'sort' => 100,
                    'is_multiple' => $isMultiple,
                    'is_required' => 0,
                    'default_values' => $canonicalDefaultValues,
                    'description' => 'ACF key without values in DB (' . $metaKind . ')',
                    'language_code' => 'RU',
                ]);

                if ($ok) {
                    $written++;
                } else {
                    $skipped++;
                }
            }

            $offset += count($slice);
            if (count($slice) < $batch) {
                if ($source === 'acf_termmeta') {
                    $source = 'acf_postmeta';
                    $offset = 0;
                    continue;
                }
                $done = true;
            }
            break;
        }

        $keys = [];
        if ($source === 'termmeta') {
            $table = (string)$ctx['tables']['termmeta'];
            $ttTable = (string)$ctx['tables']['term_taxonomy'];
            if ($table === '' || !ee_export_table_exists($ctx, $table) || !ee_export_table_exists($ctx, $ttTable)) {
                $source = 'postmeta';
                $offset = 0;
                continue;
            }

            $taxonomies = ee_export_get_export_taxonomies($state);
            if (empty($taxonomies)) {
                $source = 'postmeta';
                $offset = 0;
                continue;
            }

            $in = ee_export_prepare_string_in($taxonomies);
            $keysSql = "SELECT tm.meta_key
                FROM {$table} tm
                INNER JOIN {$ttTable} tt ON tt.term_id = tm.term_id
                WHERE tm.meta_key IS NOT NULL AND tm.meta_key <> '' AND tt.taxonomy IN ({$in['placeholders']})
                GROUP BY tm.meta_key
                ORDER BY tm.meta_key ASC
                LIMIT %d OFFSET %d";
            $params = array_merge($in['params'], [$batch, $offset]);
            $prepared = $wpdb->prepare($keysSql, ...$params);
            $keys = $wpdb->get_col($prepared);
        } elseif ($source === 'postmeta') {
            $table = (string)$ctx['tables']['postmeta'];
            $postsTable = (string)$ctx['tables']['posts'];
            if ($table === '' || !ee_export_table_exists($ctx, $table) || !ee_export_table_exists($ctx, $postsTable)) {
                $source = 'acf_termmeta';
                $offset = 0;
                continue;
            }

            $postTypes = ee_export_get_export_post_types($state);
            if (empty($postTypes)) {
                $source = 'acf_termmeta';
                $offset = 0;
                continue;
            }

            $in = ee_export_prepare_string_in($postTypes);
            $keysSql = "SELECT pm.meta_key
                FROM {$table} pm
                INNER JOIN {$postsTable} p ON p.ID = pm.post_id
                WHERE pm.meta_key IS NOT NULL AND pm.meta_key <> '' AND p.post_type IN ({$in['placeholders']})
                GROUP BY pm.meta_key
                ORDER BY pm.meta_key ASC
                LIMIT %d OFFSET %d";
            $params = array_merge($in['params'], [$batch, $offset]);
            $prepared = $wpdb->prepare($keysSql, ...$params);
            $keys = $wpdb->get_col($prepared);
        } else {
            $done = true;
            $log .= "properties: unknown source, finished.\n";
            break;
        }

        if (!is_array($keys)) {
            $keys = [];
        }

        foreach ($keys as $metaKey) {
            $metaKey = trim((string)$metaKey);
            if ($metaKey === '') {
                $skipped++;
                continue;
            }
            if (!ee_export_should_include_meta_key($state, $metaKey)) {
                $skipped++;
                continue;
            }
            $processed++;

            $typeGuess = ee_export_guess_property_type_for_meta_key($metaKey);
            $typeSource = isset($typeGuess[2]) ? $typeGuess[2] : 'wp_type:string';
            $typeFields = isset($typeGuess[1]) ? $typeGuess[1] : ['text'];
            $fieldType = isset($typeFields[0]) ? $typeFields[0] : 'text';
            $isMultiple = isset($typeGuess[3]) ? (int)$typeGuess[3] : 0;
            $acfLabel = ee_export_acf_label_for_meta_key($metaKey);
            $displayName = $acfLabel !== '' ? $acfLabel : $metaKey;

            $canonicalDefaultValues = [
                [
                    'type' => $fieldType,
                    'label' => $displayName,
                    'default' => '',
                    'multiple' => $isMultiple,
                    'required' => 0
                ]
            ];

            $ok = ee_export_append_jsonl($ctx, 'properties', [
                'source_id' => $source . ':' . $metaKey,
                'name' => $metaKey,
                'display_name' => $displayName,
                'acf_label' => $acfLabel,
                'type_source_id' => $typeSource,
                'entity_type' => ($source === 'termmeta' ? 'category' : 'page'),
                'status' => 'active',
                'sort' => 100,
                'is_multiple' => $isMultiple,
                'is_required' => 0,
                'default_values' => $canonicalDefaultValues,
                'description' => 'РљР»СЋС‡ WP ' . $source,
                'language_code' => 'RU',
            ]);

            if ($ok) {
                $written++;
            } else {
                $skipped++;
            }
        }

        $offset += count($keys);
        if (count($keys) < $batch) {
            if ($source === 'termmeta') {
                $source = 'postmeta';
                $offset = 0;
                continue;
            }
            if ($source === 'postmeta') {
                $source = 'acf_termmeta';
                $offset = 0;
                continue;
            }
            $done = true;
        }
        break;
    }

    $phaseState['source'] = $source;
    $phaseState['offset'] = $offset;

    if ($done) {
        $phaseState['done'] = 1;
    }

    return [
        'done' => $done,
        'processed' => $processed,
        'written' => $written,
        'skipped' => $skipped,
        'log' => $log . "properties: source={$source}, processed={$processed}, written={$written}, skipped={$skipped}, offset={$offset}\n",
    ];
}

function ee_export_phase_type_set_links(array $ctx, array &$state, int $batch): array {
    $rows = ee_export_build_type_set_link_rows($state);
    $phaseState = &$state['phase_state']['type_set_links'];
    $offset = (int)($phaseState['offset'] ?? 0);
    $slice = array_slice($rows, $offset, $batch);

    $processed = 0;
    $written = 0;
    $skipped = 0;
    foreach ($slice as $row) {
        $processed++;
        if (ee_export_append_jsonl($ctx, 'type_set_links', $row)) {
            $written++;
        } else {
            $skipped++;
        }
    }

    $offset += count($slice);
    $phaseState['offset'] = $offset;
    $done = $offset >= count($rows);

    return [
        'done' => $done,
        'processed' => $processed,
        'written' => $written,
        'skipped' => $skipped,
        'log' => "type_set_links: обработано={$processed}, записано={$written}, пропущено={$skipped}, смещение={$offset}/" . count($rows) . "\n",
    ];
}

function ee_export_phase_set_property_links(array $ctx, array &$state, int $batch): array {
    $wpdb = $ctx['wpdb'];
    $phaseState = &$state['phase_state']['set_property_links'];
    $source = (string)($phaseState['source'] ?? 'termmeta');
    $offset = (int)($phaseState['offset'] ?? 0);

    $sources = ['termmeta', 'postmeta', 'acf_termmeta', 'acf_postmeta'];
    if (!in_array($source, $sources, true)) {
        $source = 'termmeta';
        $offset = 0;
    }

    $processed = 0;
    $written = 0;
    $skipped = 0;
    $done = false;
    $log = '';

    while (true) {
        if ($source === 'acf_termmeta' || $source === 'acf_postmeta') {
            $metaKind = $source === 'acf_termmeta' ? 'termmeta' : 'postmeta';
            $expectedSetPrefix = $metaKind === 'termmeta' ? 'taxonomy:' : 'post_type:';
            $acfCatalog = ee_export_collect_acf_field_catalog_by_source($state);
            $catalogSection = is_array($acfCatalog[$metaKind] ?? null) ? $acfCatalog[$metaKind] : [];

            $extraRowsMap = [];
            foreach ($catalogSection as $setSourceId => $fields) {
                $setSourceId = strtolower(trim((string)$setSourceId));
                if ($setSourceId === '' || !str_starts_with($setSourceId, $expectedSetPrefix)) {
                    continue;
                }
                if (!is_array($fields)) {
                    continue;
                }

                $existingKeys = array_fill_keys(ee_export_collect_existing_meta_keys_for_set_source($ctx, $setSourceId), true);
                foreach ($fields as $metaKey => $metaInfo) {
                    unset($metaInfo);
                    $metaKey = strtolower(trim((string)$metaKey));
                    if ($metaKey === '' || isset($existingKeys[$metaKey])) {
                        continue;
                    }
                    if (!ee_export_should_include_meta_key($state, $metaKey)) {
                        continue;
                    }

                    $propertySourceId = $metaKind . ':' . $metaKey;
                    $rowKey = $setSourceId . '|' . $propertySourceId;
                    $extraRowsMap[$rowKey] = [
                        'set_source_id' => $setSourceId,
                        'property_source_id' => $propertySourceId,
                    ];
                }
            }

            $extraRows = array_values($extraRowsMap);
            usort($extraRows, static function (array $left, array $right): int {
                $leftSet = strtolower(trim((string)($left['set_source_id'] ?? '')));
                $rightSet = strtolower(trim((string)($right['set_source_id'] ?? '')));
                if ($leftSet === $rightSet) {
                    $leftProperty = strtolower(trim((string)($left['property_source_id'] ?? '')));
                    $rightProperty = strtolower(trim((string)($right['property_source_id'] ?? '')));
                    return $leftProperty <=> $rightProperty;
                }
                return $leftSet <=> $rightSet;
            });

            $slice = array_slice($extraRows, $offset, $batch);
            foreach ($slice as $row) {
                $setSourceId = trim((string)($row['set_source_id'] ?? ''));
                $propertySourceId = trim((string)($row['property_source_id'] ?? ''));
                if ($setSourceId === '' || $propertySourceId === '') {
                    $skipped++;
                    continue;
                }
                $processed++;
                $ok = ee_export_append_jsonl($ctx, 'set_property_links', [
                    'set_source_id' => $setSourceId,
                    'property_source_id' => $propertySourceId,
                ]);
                if ($ok) {
                    $written++;
                } else {
                    $skipped++;
                }
            }

            $offset += count($slice);
            if (count($slice) < $batch) {
                if ($source === 'acf_termmeta') {
                    $source = 'acf_postmeta';
                    $offset = 0;
                    continue;
                }
                $done = true;
            }
            break;
        }

        if ($source === 'termmeta') {
            $table = (string)$ctx['tables']['termmeta'];
            $ttTable = (string)$ctx['tables']['term_taxonomy'];
            if (!ee_export_table_exists($ctx, $table) || !ee_export_table_exists($ctx, $ttTable)) {
                $source = 'postmeta';
                $offset = 0;
                continue;
            }

            $taxonomies = ee_export_get_export_taxonomies($state);
            if (empty($taxonomies)) {
                $source = 'postmeta';
                $offset = 0;
                continue;
            }

            $in = ee_export_prepare_string_in($taxonomies);
            $sql = "SELECT tt.taxonomy, tm.meta_key
                FROM {$table} tm
                INNER JOIN {$ttTable} tt ON tt.term_id = tm.term_id
                WHERE tm.meta_key IS NOT NULL AND tm.meta_key <> '' AND tt.taxonomy IN ({$in['placeholders']})
                GROUP BY tt.taxonomy, tm.meta_key
                ORDER BY tt.taxonomy ASC, tm.meta_key ASC
                LIMIT %d OFFSET %d";
            $params = array_merge($in['params'], [$batch, $offset]);
            $prepared = $wpdb->prepare($sql, ...$params);
            $rows = $wpdb->get_results($prepared, ARRAY_A);
            if (!is_array($rows)) {
                $rows = [];
            }

            foreach ($rows as $row) {
                $metaKey = trim((string)($row['meta_key'] ?? ''));
                $taxonomy = trim((string)($row['taxonomy'] ?? ''));
                if ($metaKey === '' || $taxonomy === '') {
                    $skipped++;
                    continue;
                }
                if (!ee_export_should_include_meta_key($state, $metaKey)) {
                    $skipped++;
                    continue;
                }
                $processed++;
                $ok = ee_export_append_jsonl($ctx, 'set_property_links', [
                    'set_source_id' => 'taxonomy:' . $taxonomy,
                    'property_source_id' => 'termmeta:' . $metaKey,
                ]);
                if ($ok) {
                    $written++;
                } else {
                    $skipped++;
                }
            }

            $offset += count($rows);
            if (count($rows) < $batch) {
                $source = 'postmeta';
                $offset = 0;
                continue;
            }
            break;
        }

        if ($source === 'postmeta') {
            $table = (string)$ctx['tables']['postmeta'];
            $postsTable = (string)$ctx['tables']['posts'];
            if (!ee_export_table_exists($ctx, $table) || !ee_export_table_exists($ctx, $postsTable)) {
                $source = 'acf_termmeta';
                $offset = 0;
                continue;
            }

            $postTypes = ee_export_get_export_post_types($state);
            if (empty($postTypes)) {
                $source = 'acf_termmeta';
                $offset = 0;
                continue;
            }

            $in = ee_export_prepare_string_in($postTypes);
            $sql = "SELECT p.post_type, pm.meta_key
                FROM {$table} pm
                INNER JOIN {$postsTable} p ON p.ID = pm.post_id
                WHERE pm.meta_key IS NOT NULL AND pm.meta_key <> '' AND p.post_type IN ({$in['placeholders']})
                GROUP BY p.post_type, pm.meta_key
                ORDER BY p.post_type ASC, pm.meta_key ASC
                LIMIT %d OFFSET %d";
            $params = array_merge($in['params'], [$batch, $offset]);
            $prepared = $wpdb->prepare($sql, ...$params);
            $rows = $wpdb->get_results($prepared, ARRAY_A);
            if (!is_array($rows)) {
                $rows = [];
            }

            foreach ($rows as $row) {
                $metaKey = trim((string)($row['meta_key'] ?? ''));
                $postType = trim((string)($row['post_type'] ?? ''));
                if ($metaKey === '' || $postType === '') {
                    $skipped++;
                    continue;
                }
                if (!ee_export_should_include_meta_key($state, $metaKey)) {
                    $skipped++;
                    continue;
                }
                $processed++;
                $ok = ee_export_append_jsonl($ctx, 'set_property_links', [
                    'set_source_id' => 'post_type:' . $postType,
                    'property_source_id' => 'postmeta:' . $metaKey,
                ]);
                if ($ok) {
                    $written++;
                } else {
                    $skipped++;
                }
            }

            $offset += count($rows);
            if (count($rows) < $batch) {
                $source = 'acf_termmeta';
                $offset = 0;
                continue;
            }
            break;
        }

        $done = true;
        $log .= "set_property_links: unknown source, finished.\n";
        break;
    }

    $phaseState['source'] = $source;
    $phaseState['offset'] = $offset;
    if ($done) {
        $phaseState['done'] = 1;
    }

    return [
        'done' => $done,
        'processed' => $processed,
        'written' => $written,
        'skipped' => $skipped,
        'log' => $log . "set_property_links: source={$source}, processed={$processed}, written={$written}, skipped={$skipped}, offset={$offset}\n",
    ];
}

function ee_export_phase_categories(array $ctx, array &$state, int $batch): array {
    $wpdb = $ctx['wpdb'];
    $phaseState = &$state['phase_state']['categories'];
    $source = (string)($phaseState['source'] ?? 'terms');
    $lastId = (int)($phaseState['last_id'] ?? 0);
    $offset = (int)($phaseState['offset'] ?? 0);

    $processed = 0;
    $written = 0;
    $skipped = 0;
    $done = false;
    $log = '';

    while (true) {
        if ($source === 'terms') {
            $termsTable = (string)$ctx['tables']['terms'];
            $ttTable = (string)$ctx['tables']['term_taxonomy'];
            if (!ee_export_table_exists($ctx, $termsTable) || !ee_export_table_exists($ctx, $ttTable)) {
                $source = 'pseudo';
                $offset = 0;
                continue;
            }

            $taxonomies = ee_export_get_export_taxonomies($state);
            if (empty($taxonomies)) {
                $source = 'pseudo';
                $offset = 0;
                continue;
            }

            $in = ee_export_prepare_string_in($taxonomies);
            $sql = "SELECT tt.term_taxonomy_id, tt.term_id, tt.taxonomy, tt.parent, tt.description, t.name, t.slug
                FROM {$ttTable} tt
                INNER JOIN {$termsTable} t ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ({$in['placeholders']}) AND tt.term_taxonomy_id > %d
                ORDER BY tt.term_taxonomy_id ASC
                LIMIT %d";
            $params = array_merge($in['params'], [$lastId, $batch]);
            $prepared = $wpdb->prepare($sql, ...$params);
            $rows = $wpdb->get_results($prepared, ARRAY_A);
            if (!is_array($rows)) {
                $rows = [];
            }

            $parentMap = [];
            $parentTermIds = [];
            foreach ($rows as $row) {
                $parentTermId = (int)($row['parent'] ?? 0);
                if ($parentTermId > 0) {
                    $parentTermIds[] = $parentTermId;
                }
            }
            $parentTermIds = array_values(array_unique($parentTermIds));
            if (!empty($parentTermIds)) {
                $idsSql = implode(',', array_map('intval', $parentTermIds));
                $parentSql = "SELECT term_taxonomy_id, term_id, taxonomy FROM {$ttTable} WHERE term_id IN ({$idsSql})";
                $parentRows = $wpdb->get_results($parentSql, ARRAY_A);
                if (is_array($parentRows)) {
                    foreach ($parentRows as $parentRow) {
                        $taxonomy = (string)($parentRow['taxonomy'] ?? '');
                        $termId = (int)($parentRow['term_id'] ?? 0);
                        $ttId = (int)($parentRow['term_taxonomy_id'] ?? 0);
                        if ($taxonomy !== '' && $termId > 0 && $ttId > 0) {
                            $parentMap[$taxonomy . ':' . $termId] = (string)$ttId;
                        }
                    }
                }
            }

            foreach ($rows as $row) {
                $processed++;
                $ttId = (int)($row['term_taxonomy_id'] ?? 0);
                if ($ttId <= 0) {
                    $skipped++;
                    continue;
                }
                $lastId = $ttId;
                $taxonomy = trim((string)($row['taxonomy'] ?? ''));
                $name = trim((string)($row['name'] ?? ''));
                if ($taxonomy === '' || $name === '') {
                    $skipped++;
                    continue;
                }

                $parentSourceId = '';
                $parentTermId = (int)($row['parent'] ?? 0);
                if ($parentTermId > 0) {
                    $parentSourceId = (string)($parentMap[$taxonomy . ':' . $parentTermId] ?? '');
                }

                $payload = [
                    'source_id' => (string)$ttId,
                    'type_source_id' => 'taxonomy:' . $taxonomy,
                    'title' => $name,
                    'slug' => trim((string)($row['slug'] ?? '')),
                    'source_path' => ee_export_relative_path_from_url(get_term_link((int)($row['term_id'] ?? 0), $taxonomy)),
                    'short_description' => '',
                    'description' => trim((string)($row['description'] ?? '')) !== ''
                        ? ee_export_normalize_rich_text((string)$row['description'])
                        : $name,
                    'status' => 'active',
                    'language_code' => 'RU',
                ];
                if ($parentSourceId !== '') {
                    $payload['parent_source_id'] = $parentSourceId;
                }

                if (ee_export_append_jsonl($ctx, 'categories', $payload)) {
                    $written++;
                } else {
                    $skipped++;
                }
            }

            if (count($rows) < $batch) {
                $source = 'pseudo';
                $offset = 0;
                continue;
            }
            break;
        }

        if ($source === 'pseudo') {
            $rows = ee_export_build_pseudo_category_rows($state);
            $slice = array_slice($rows, $offset, $batch);
            foreach ($slice as $row) {
                $processed++;
                if (ee_export_append_jsonl($ctx, 'categories', $row)) {
                    $written++;
                } else {
                    $skipped++;
                }
            }
            $offset += count($slice);
            $done = $offset >= count($rows);
            break;
        }

        $done = true;
        $log .= "categories: неизвестный источник, завершено.\n";
        break;
    }

    $phaseState['source'] = $source;
    $phaseState['last_id'] = $lastId;
    $phaseState['offset'] = $offset;
    if ($done) {
        $phaseState['done'] = 1;
    }

    return [
        'done' => $done,
        'processed' => $processed,
        'written' => $written,
        'skipped' => $skipped,
        'log' => $log . "categories: источник={$source}, обработано={$processed}, записано={$written}, пропущено={$skipped}, последний_id={$lastId}, смещение={$offset}\n",
    ];
}

function ee_export_phase_pages(array $ctx, array &$state, int $batch): array {
    $wpdb = $ctx['wpdb'];
    $postsTable = (string)$ctx['tables']['posts'];
    $usersTable = (string)$ctx['tables']['users'];

    if (!ee_export_table_exists($ctx, $postsTable)) {
        return ['done' => true, 'processed' => 0, 'written' => 0, 'skipped' => 0, 'log' => "pages: таблица posts отсутствует, пропуск.\n"];
    }

    $postTypes = ee_export_get_export_post_types($state);
    if (empty($postTypes)) {
        return ['done' => true, 'processed' => 0, 'written' => 0, 'skipped' => 0, 'log' => "pages: отсутствуют типы записей для экспорта.\n"];
    }

    $phaseState = &$state['phase_state']['pages'];
    $lastId = (int)($phaseState['last_id'] ?? 0);

    $inPostTypes = ee_export_prepare_string_in($postTypes);
    $sql = "SELECT ID, post_type, post_status, post_title, post_name, post_excerpt, post_content, post_parent, post_author
        FROM {$postsTable}
        WHERE post_type IN ({$inPostTypes['placeholders']}) AND ID > %d AND post_status <> 'auto-draft'
        ORDER BY ID ASC
        LIMIT %d";
    $params = array_merge($inPostTypes['params'], [$lastId, $batch]);
    $prepared = $wpdb->prepare($sql, ...$params);
    $rows = $wpdb->get_results($prepared, ARRAY_A);
    if (!is_array($rows)) {
        $rows = [];
    }

    if (empty($rows)) {
        return ['done' => true, 'processed' => 0, 'written' => 0, 'skipped' => 0, 'log' => "pages: завершено.\n"];
    }

    $postIds = array_values(array_filter(array_map(static function (array $row) {
        return (int)($row['ID'] ?? 0);
    }, $rows)));
    $postIdSql = empty($postIds) ? '' : implode(',', array_map('intval', $postIds));

    $authorMap = [];
    $authorIds = array_values(array_unique(array_filter(array_map(static function (array $row) {
        return (int)($row['post_author'] ?? 0);
    }, $rows))));
    if (!empty($authorIds) && ee_export_table_exists($ctx, $usersTable)) {
        $authorIdsSql = implode(',', array_map('intval', $authorIds));
        $authorRows = $wpdb->get_results(
            "SELECT ID, user_login, user_email, display_name FROM {$usersTable} WHERE ID IN ({$authorIdsSql})",
            ARRAY_A
        );
        if (is_array($authorRows)) {
            foreach ($authorRows as $authorRow) {
                $authorId = (int)($authorRow['ID'] ?? 0);
                if ($authorId <= 0) {
                    continue;
                }
                $authorMap[$authorId] = [
                    'login' => trim((string)($authorRow['user_login'] ?? '')),
                    'email' => trim((string)($authorRow['user_email'] ?? '')),
                    'name' => trim((string)($authorRow['display_name'] ?? '')),
                ];
            }
        }
    }

    $firstCategoryByPost = [];
    $taxonomies = ee_export_get_export_taxonomies($state);
    if ($postIdSql !== '' && !empty($taxonomies) && ee_export_table_exists($ctx, (string)$ctx['tables']['term_relationships']) && ee_export_table_exists($ctx, (string)$ctx['tables']['term_taxonomy'])) {
        $trTable = (string)$ctx['tables']['term_relationships'];
        $ttTable = (string)$ctx['tables']['term_taxonomy'];
        $inTax = ee_export_prepare_string_in($taxonomies);
        $relSql = "SELECT tr.object_id, tt.term_taxonomy_id
            FROM {$trTable} tr
            INNER JOIN {$ttTable} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tr.object_id IN ({$postIdSql}) AND tt.taxonomy IN ({$inTax['placeholders']})
            ORDER BY tr.object_id ASC, tt.term_taxonomy_id ASC";
        $preparedRel = $wpdb->prepare($relSql, ...$inTax['params']);
        $relRows = $wpdb->get_results($preparedRel, ARRAY_A);
        if (is_array($relRows)) {
            foreach ($relRows as $rel) {
                $objectId = (int)($rel['object_id'] ?? 0);
                $ttId = (int)($rel['term_taxonomy_id'] ?? 0);
                if ($objectId > 0 && $ttId > 0 && !isset($firstCategoryByPost[$objectId])) {
                    $firstCategoryByPost[$objectId] = (string)$ttId;
                }
            }
        }
    }

    $parentIds = [];
    foreach ($rows as $row) {
        $parentId = (int)($row['post_parent'] ?? 0);
        if ($parentId > 0) {
            $parentIds[] = $parentId;
        }
    }
    $parentIds = array_values(array_unique($parentIds));
    $parentTypeMap = [];
    if (!empty($parentIds)) {
        $parentSqlIds = implode(',', array_map('intval', $parentIds));
        $parentRows = $wpdb->get_results("SELECT ID, post_type FROM {$postsTable} WHERE ID IN ({$parentSqlIds})", ARRAY_A);
        if (is_array($parentRows)) {
            foreach ($parentRows as $parentRow) {
                $parentTypeMap[(int)($parentRow['ID'] ?? 0)] = (string)($parentRow['post_type'] ?? '');
            }
        }
    }

    $allowedPostTypes = array_flip($postTypes);
    $processed = 0;
    $written = 0;
    $skipped = 0;

    foreach ($rows as $row) {
        $processed++;
        $postId = (int)($row['ID'] ?? 0);
        if ($postId <= 0) {
            $skipped++;
            continue;
        }
        $lastId = $postId;

        $postType = (string)($row['post_type'] ?? '');
        if ($postType === '' || !isset($allowedPostTypes[$postType])) {
            $skipped++;
            continue;
        }

        $categorySourceId = (string)($firstCategoryByPost[$postId] ?? '');
        if ($categorySourceId === '') {
            $categorySourceId = 'ptcat:' . $postType;
        }

        $title = trim((string)($row['post_title'] ?? ''));
        if ($title === '') {
            $title = ee_export_humanize_fallback_title((string)($row['post_name'] ?? ''), $postId);
        }

        $payload = [
            'source_id' => (string)$postId,
            'category_source_id' => $categorySourceId,
            'title' => $title,
            'slug' => trim((string)($row['post_name'] ?? '')),
            'source_path' => ee_export_relative_path_from_url(get_permalink($postId)),
            'short_description' => (string)($row['post_excerpt'] ?? ''),
            'description' => ee_export_normalize_rich_text((string)($row['post_content'] ?? '')),
            'status' => ee_export_map_status((string)($row['post_status'] ?? 'publish')),
            'language_code' => 'RU',
        ];

        $parentId = (int)($row['post_parent'] ?? 0);
        if ($parentId > 0) {
            $parentType = (string)($parentTypeMap[$parentId] ?? '');
            if ($parentType !== '' && isset($allowedPostTypes[$parentType])) {
                $payload['parent_source_id'] = (string)$parentId;
            }
        }

        $ownerUserSourceId = (int)($row['post_author'] ?? 0);
        if ($ownerUserSourceId > 0) {
            $payload['owner_user_source_id'] = (string)$ownerUserSourceId;
            if (isset($authorMap[$ownerUserSourceId]) && is_array($authorMap[$ownerUserSourceId])) {
                $ownerData = $authorMap[$ownerUserSourceId];
                if ($ownerData['login'] !== '') {
                    $payload['owner_user_login'] = $ownerData['login'];
                }
                if ($ownerData['email'] !== '') {
                    $payload['owner_user_email'] = $ownerData['email'];
                }
                if ($ownerData['name'] !== '') {
                    $payload['owner_user_name'] = $ownerData['name'];
                }
            }
        }

        if (ee_export_append_jsonl($ctx, 'pages', $payload)) {
            $written++;
        } else {
            $skipped++;
        }
    }

    $phaseState['last_id'] = $lastId;
    $done = count($rows) < $batch;

    return [
        'done' => $done,
        'processed' => $processed,
        'written' => $written,
        'skipped' => $skipped,
        'log' => "pages: обработано={$processed}, записано={$written}, пропущено={$skipped}, последний_id={$lastId}\n",
    ];
}

function ee_export_normalize_rich_text(string $value): string {
    $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
    if ($value === '') {
        return '';
    }

    if (function_exists('wpautop')) {
        $formatted = trim((string)wpautop($value));
        if ($formatted !== '') {
            return $formatted;
        }
    }

    $blocks = preg_split("/\n{2,}/u", $value) ?: [];
    $paragraphs = [];
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }

        $decoded = html_entity_decode($block, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $escaped = htmlspecialchars($decoded, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace("/\n/u", "<br>\n", $escaped) ?? $escaped;
        $paragraphs[] = '<p>' . $escaped . '</p>';
    }

    return $paragraphs === []
        ? '<p>' . htmlspecialchars(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        : implode("\n", $paragraphs);
}

function ee_export_phase_property_values(array $ctx, array &$state, int $batch): array {
    $wpdb = $ctx['wpdb'];
    $phaseState = &$state['phase_state']['property_values'];
    $source = (string)($phaseState['source'] ?? 'termmeta');
    $lastId = (int)($phaseState['last_id'] ?? 0);

    $processed = 0;
    $written = 0;
    $skipped = 0;
    $done = false;
    $log = '';

    while (true) {
        if ($source === 'termmeta') {
            $table = (string)$ctx['tables']['termmeta'];
            $ttTable = (string)$ctx['tables']['term_taxonomy'];
            if (!ee_export_table_exists($ctx, $table) || !ee_export_table_exists($ctx, $ttTable)) {
                $source = 'postmeta';
                $lastId = 0;
                continue;
            }

            $rowsSql = $wpdb->prepare(
                "SELECT meta_id, term_id, meta_key, meta_value FROM {$table} WHERE meta_id > %d ORDER BY meta_id ASC LIMIT %d",
                $lastId,
                $batch
            );
            $rows = $wpdb->get_results($rowsSql, ARRAY_A);
            if (!is_array($rows)) {
                $rows = [];
            }

            if (empty($rows)) {
                $source = 'postmeta';
                $lastId = 0;
                continue;
            }

            $taxonomies = ee_export_get_export_taxonomies($state);
            $termIds = array_values(array_unique(array_filter(array_map(static function (array $row) {
                return (int)($row['term_id'] ?? 0);
            }, $rows))));
            $termMap = [];

            if (!empty($termIds) && !empty($taxonomies)) {
                $inTax = ee_export_prepare_string_in($taxonomies);
                $idsSql = implode(',', array_map('intval', $termIds));
                $mapSql = "SELECT term_taxonomy_id, term_id, taxonomy
                    FROM {$ttTable}
                    WHERE term_id IN ({$idsSql}) AND taxonomy IN ({$inTax['placeholders']})";
                $preparedMap = $wpdb->prepare($mapSql, ...$inTax['params']);
                $mapRows = $wpdb->get_results($preparedMap, ARRAY_A);
                if (is_array($mapRows)) {
                    foreach ($mapRows as $mapRow) {
                        $termId = (int)($mapRow['term_id'] ?? 0);
                        $ttId = (int)($mapRow['term_taxonomy_id'] ?? 0);
                        $taxonomy = (string)($mapRow['taxonomy'] ?? '');
                        if ($termId <= 0 || $ttId <= 0 || $taxonomy === '') {
                            continue;
                        }
                        $termMap[$termId][] = [
                            'term_taxonomy_id' => $ttId,
                            'taxonomy' => $taxonomy,
                        ];
                    }
                }
            }

            foreach ($rows as $row) {
                $processed++;
                $metaId = (int)($row['meta_id'] ?? 0);
                if ($metaId > 0) {
                    $lastId = $metaId;
                }

                $metaKey = trim((string)($row['meta_key'] ?? ''));
                $termId = (int)($row['term_id'] ?? 0);
                if ($metaKey === '' || $termId <= 0) {
                    $skipped++;
                    continue;
                }
                if (!ee_export_should_include_meta_key($state, $metaKey)) {
                    $skipped++;
                    continue;
                }

                $targets = $termMap[$termId] ?? [];
                if (empty($targets)) {
                    $skipped++;
                    continue;
                }

                $value = ee_export_prepare_meta_value_with_context($state, $metaKey, $row['meta_value'] ?? '');
                
                $typeGuess = ee_export_guess_property_type_for_meta_key($metaKey);
                $typeFields = isset($typeGuess[1]) ? $typeGuess[1] : ['text'];
                $fieldType = isset($typeFields[0]) ? $typeFields[0] : 'text';
                $isMultiple = isset($typeGuess[3]) ? (int)$typeGuess[3] : 0;
                $acfLabel = ee_export_acf_label_for_meta_key($metaKey);
                $displayName = $acfLabel !== '' ? $acfLabel : $metaKey;
                
                $canonicalValue = [
                    [
                        'type' => $fieldType,
                        'label' => $displayName,
                        'value' => $value,
                        'multiple' => $isMultiple,
                        'required' => 0
                    ]
                ];

                foreach ($targets as $target) {
                    $ok = ee_export_append_jsonl($ctx, 'property_values', [
                        'entity_type' => 'category',
                        'entity_source_id' => (string)$target['term_taxonomy_id'],
                        'set_source_id' => 'taxonomy:' . (string)$target['taxonomy'],
                        'property_source_id' => 'termmeta:' . $metaKey,
                        'property_values' => $canonicalValue,
                        'language_code' => 'RU',
                    ]);
                    if ($ok) {
                        $written++;
                    } else {
                        $skipped++;
                    }
                }
            }

            if (count($rows) < $batch) {
                $source = 'postmeta';
                $lastId = 0;
                continue;
            }
            break;
        }

        if ($source === 'postmeta') {
            $table = (string)$ctx['tables']['postmeta'];
            $postsTable = (string)$ctx['tables']['posts'];
            if (!ee_export_table_exists($ctx, $table) || !ee_export_table_exists($ctx, $postsTable)) {
                $done = true;
                break;
            }

            $rowsSql = $wpdb->prepare(
                "SELECT meta_id, post_id, meta_key, meta_value FROM {$table} WHERE meta_id > %d ORDER BY meta_id ASC LIMIT %d",
                $lastId,
                $batch
            );
            $rows = $wpdb->get_results($rowsSql, ARRAY_A);
            if (!is_array($rows)) {
                $rows = [];
            }
            if (empty($rows)) {
                $done = true;
                break;
            }

            $postIds = array_values(array_unique(array_filter(array_map(static function (array $row) {
                return (int)($row['post_id'] ?? 0);
            }, $rows))));
            $postTypeById = [];
            if (!empty($postIds)) {
                $idsSql = implode(',', array_map('intval', $postIds));
                $postRows = $wpdb->get_results("SELECT ID, post_type FROM {$postsTable} WHERE ID IN ({$idsSql})", ARRAY_A);
                if (is_array($postRows)) {
                    foreach ($postRows as $postRow) {
                        $postTypeById[(int)($postRow['ID'] ?? 0)] = (string)($postRow['post_type'] ?? '');
                    }
                }
            }
            $allowedPostTypes = array_flip(ee_export_get_export_post_types($state));

            foreach ($rows as $row) {
                $processed++;
                $metaId = (int)($row['meta_id'] ?? 0);
                if ($metaId > 0) {
                    $lastId = $metaId;
                }

                $metaKey = trim((string)($row['meta_key'] ?? ''));
                $postId = (int)($row['post_id'] ?? 0);
                $postType = (string)($postTypeById[$postId] ?? '');
                if ($metaKey === '' || $postId <= 0 || $postType === '' || !isset($allowedPostTypes[$postType])) {
                    $skipped++;
                    continue;
                }
                if (!ee_export_should_include_meta_key($state, $metaKey)) {
                    $skipped++;
                    continue;
                }

                $value = ee_export_prepare_meta_value_with_context($state, $metaKey, $row['meta_value'] ?? '');
                
                $typeGuess = ee_export_guess_property_type_for_meta_key($metaKey);
                $typeFields = isset($typeGuess[1]) ? $typeGuess[1] : ['text'];
                $fieldType = isset($typeFields[0]) ? $typeFields[0] : 'text';
                $isMultiple = isset($typeGuess[3]) ? (int)$typeGuess[3] : 0;
                $acfLabel = ee_export_acf_label_for_meta_key($metaKey);
                $displayName = $acfLabel !== '' ? $acfLabel : $metaKey;
                
                $canonicalValue = [
                    [
                        'type' => $fieldType,
                        'label' => $displayName,
                        'value' => $value,
                        'multiple' => $isMultiple,
                        'required' => 0
                    ]
                ];

                $ok = ee_export_append_jsonl($ctx, 'property_values', [
                    'entity_type' => 'page',
                    'entity_source_id' => (string)$postId,
                    'set_source_id' => 'post_type:' . $postType,
                    'property_source_id' => 'postmeta:' . $metaKey,
                    'property_values' => $canonicalValue,
                    'language_code' => 'RU',
                ]);
                if ($ok) {
                    $written++;
                } else {
                    $skipped++;
                }
            }

            if (count($rows) < $batch) {
                $done = true;
            }
            break;
        }

        $done = true;
        $log .= "property_values: неизвестный источник, завершено.\n";
        break;
    }

    $phaseState['source'] = $source;
    $phaseState['last_id'] = $lastId;
    if ($done) {
        $phaseState['done'] = 1;
    }

    return [
        'done' => $done,
        'processed' => $processed,
        'written' => $written,
        'skipped' => $skipped,
        'log' => $log . "property_values: источник={$source}, обработано={$processed}, записано={$written}, пропущено={$skipped}, последний_id={$lastId}\n",
    ];
}

function ee_export_phase_finalize(array $ctx, array &$state, int $batch): array {
    unset($batch);

    ee_export_write_manifest($ctx, $state);

    $processed = 1;
    $written = 0;
    $skipped = 0;
    $log = '';

    $zipName = 'ee_entities_export_' . gmdate('Ymd_His') . '.zip';
    $zipPath = rtrim((string)$ctx['base_dir'], '/\\') . '/' . $zipName;

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            throw new RuntimeException('Не удалось создать ZIP-пакет. Код: ' . $openResult);
        }
        try {
            $manifestPath = (string)$ctx['manifest_file'];
            if (is_file($manifestPath)) {
                $zip->addFile($manifestPath, 'manifest.json');
                $written++;
            }
            foreach ((array)$ctx['files'] as $relative) {
                $relative = ltrim((string)$relative, '/\\');
                $filePath = rtrim((string)$ctx['base_dir'], '/\\') . '/' . $relative;
                if (!is_file($filePath)) {
                    continue;
                }
                $zip->addFile($filePath, $relative);
                $written++;
            }
        } finally {
            $zip->close();
        }

        $state['zip_file'] = $zipPath;
        $state['zip_url'] = rtrim((string)$ctx['base_url'], '/\\') . '/' . $zipName;
        $log .= "finalize: пакет создан: {$zipName}\n";
    } else {
        $state['zip_file'] = '';
        $state['zip_url'] = '';
        $skipped++;
        $log .= "finalize: расширение ZipArchive недоступно. Файлы JSONL находятся в uploads/ee_wp_export.\n";
    }

    $acfCatalog = ee_export_collect_acf_meta_catalog();
    $acfDetected = !empty($acfCatalog['available']) ? 'yes' : 'no';
    $log .= "finalize: Media URL conversion: enabled (ACF detected: {$acfDetected})\n";

    $state['phase_state']['finalize']['done'] = 1;

    return [
        'done' => true,
        'processed' => $processed,
        'written' => $written,
        'skipped' => $skipped,
        'log' => $log,
    ];
}

function ee_export_write_manifest(array $ctx, array $state) {
    $manifest = [
        'format' => 'ee_entities_json_package',
        'schema' => 'ee.entities.v2', // v2 под новую спецификацию
        'version' => 2,
        'source_system' => 'wordpress',
        'source_key' => (string)($state['source_key'] ?? ''),
        'generated_at' => gmdate('c'),
        'site_url' => home_url('/'),
        'wp_version' => (string)($GLOBALS['wp_version'] ?? ''),
        'php_version' => PHP_VERSION,
        'selection' => [
            'taxonomies' => array_values(array_filter(array_map('strval', (array)($state['taxonomies'] ?? [])))),
            'post_types' => array_values(array_filter(array_map('strval', (array)($state['post_types'] ?? [])))),
        ],
        'export_options' => [
            'acf_export_mode' => ee_export_normalize_acf_mode((string)($state['acf_export_mode'] ?? 'all')),
            'include_private_meta_keys' => !empty($state['include_private_meta_keys']) ? 1 : 0,
        ],
        'source_catalog' => [
            'category_types' => ee_export_manifest_category_type_catalog($state),
            'property_sets' => ee_export_manifest_property_set_catalog($state),
        ],
        'files' => (array)$ctx['files'],
    ];

    $json = ee_export_json_encode($manifest, JSON_PRETTY_PRINT);
    if (!is_string($json)) {
        throw new RuntimeException('Не удалось сформировать manifest.json');
    }
    file_put_contents((string)$ctx['manifest_file'], $json);
}

function ee_export_manifest_category_type_catalog(array $state): array {
    $rows = [];
    $taxonomies = array_values(array_filter(array_map('strval', (array)($state['taxonomies'] ?? []))));
    $postTypes = array_values(array_filter(array_map('strval', (array)($state['post_types'] ?? []))));

    foreach ($taxonomies as $taxonomy) {
        $label = (string)($state['taxonomy_labels'][$taxonomy] ?? $taxonomy);
        $description = trim((string)($state['taxonomy_descriptions'][$taxonomy] ?? ''));
        $rows[] = [
            'source_id' => 'taxonomy:' . $taxonomy,
            'kind' => 'taxonomy',
            'code' => $taxonomy,
            'name' => $label,
            'description' => $description !== '' ? $description : ('Таксономия WordPress: ' . $taxonomy),
        ];
    }

    foreach ($postTypes as $postType) {
        $label = (string)($state['post_type_labels'][$postType] ?? $postType);
        $description = trim((string)($state['post_type_descriptions'][$postType] ?? ''));
        $rows[] = [
            'source_id' => 'post_type:' . $postType,
            'kind' => 'post_type',
            'code' => $postType,
            'name' => 'Тип записи: ' . $label,
            'description' => $description !== '' ? $description : ('Тип записи WordPress: ' . $postType),
        ];
    }

    return $rows;
}

function ee_export_manifest_property_set_catalog(array $state): array {
    $rows = [];
    $taxonomies = array_values(array_filter(array_map('strval', (array)($state['taxonomies'] ?? []))));
    $postTypes = array_values(array_filter(array_map('strval', (array)($state['post_types'] ?? []))));

    foreach ($taxonomies as $taxonomy) {
        $label = (string)($state['taxonomy_labels'][$taxonomy] ?? $taxonomy);
        $rows[] = [
            'source_id' => 'taxonomy:' . $taxonomy,
            'kind' => 'taxonomy',
            'code' => $taxonomy,
            'name' => 'Набор таксономии: ' . $label,
            'description' => 'Свойства termmeta для таксономии "' . $taxonomy . '"',
        ];
    }

    foreach ($postTypes as $postType) {
        $label = (string)($state['post_type_labels'][$postType] ?? $postType);
        $rows[] = [
            'source_id' => 'post_type:' . $postType,
            'kind' => 'post_type',
            'code' => $postType,
            'name' => 'Набор типа записи: ' . $label,
            'description' => 'Свойства postmeta для типа записи "' . $postType . '"',
        ];
    }

    return $rows;
}

function ee_export_build_category_type_rows(array &$state): array {
    $taxonomies = ee_export_get_export_taxonomies($state);
    $postTypes = ee_export_get_export_post_types($state);

    $rows = [];
    foreach ($taxonomies as $taxonomy) {
        $label = (string)($state['taxonomy_labels'][$taxonomy] ?? $taxonomy);
        $description = (string)($state['taxonomy_descriptions'][$taxonomy] ?? ('WP таксономия: ' . $taxonomy));
        $rows[] = [
            'source_id' => 'taxonomy:' . $taxonomy,
            'taxonomy' => $taxonomy,
            'name' => $label,
            'description' => $description !== '' ? $description : $label,
            'status' => 'active',
            'language_code' => 'RU',
        ];
    }
    foreach ($postTypes as $postType) {
        $label = (string)($state['post_type_labels'][$postType] ?? $postType);
        $description = (string)($state['post_type_descriptions'][$postType] ?? ('WP тип записи: ' . $postType));
        $rows[] = [
            'source_id' => 'post_type:' . $postType,
            'post_type' => $postType,
            'name' => 'Тип записи: ' . $label,
            'description' => $description !== '' ? $description : $label,
            'status' => 'active',
            'language_code' => 'RU',
        ];
    }

    return $rows;
}

function ee_export_build_property_set_rows(array &$state): array {
    $taxonomies = ee_export_get_export_taxonomies($state);
    $postTypes = ee_export_get_export_post_types($state);

    $rows = [];
    foreach ($taxonomies as $taxonomy) {
        $label = (string)($state['taxonomy_labels'][$taxonomy] ?? $taxonomy);
        $rows[] = [
            'source_id' => 'taxonomy:' . $taxonomy,
            'name' => 'Таксономия: ' . $label,
            'description' => 'Свойства из WP termmeta для таксономии "' . $taxonomy . '"',
            'language_code' => 'RU',
        ];
    }
    foreach ($postTypes as $postType) {
        $label = (string)($state['post_type_labels'][$postType] ?? $postType);
        $rows[] = [
            'source_id' => 'post_type:' . $postType,
            'name' => 'Тип записи: ' . $label,
            'description' => 'Свойства из WP postmeta для типа записи "' . $postType . '"',
            'language_code' => 'RU',
        ];
    }

    return $rows;
}

function ee_export_build_type_set_link_rows(array &$state): array {
    $taxonomies = ee_export_get_export_taxonomies($state);
    $postTypes = ee_export_get_export_post_types($state);

    $rows = [];
    foreach ($taxonomies as $taxonomy) {
        $rows[] = [
            'type_source_id' => 'taxonomy:' . $taxonomy,
            'set_source_id' => 'taxonomy:' . $taxonomy,
        ];
    }
    foreach ($postTypes as $postType) {
        $rows[] = [
            'type_source_id' => 'post_type:' . $postType,
            'set_source_id' => 'post_type:' . $postType,
        ];
    }

    return $rows;
}

function ee_export_build_pseudo_category_rows(array &$state): array {
    $postTypes = ee_export_get_export_post_types($state);
    $rows = [];
    foreach ($postTypes as $postType) {
        $label = (string)($state['post_type_labels'][$postType] ?? $postType);
        $rows[] = [
            'source_id' => 'ptcat:' . $postType,
            'type_source_id' => 'post_type:' . $postType,
            'title' => 'Без категории: ' . $label,
            'short_description' => 'Системная резервная категория',
            'description' => 'Синтетическая категория для записей без терминов таксономии (тип записи: ' . $postType . ')',
            'status' => 'active',
            'language_code' => 'RU',
        ];
    }
    return $rows;
}

function ee_export_get_export_taxonomies(array &$state): array {
    if (!empty($state['taxonomies']) && is_array($state['taxonomies'])) {
        return array_values(array_filter(array_map('strval', $state['taxonomies'])));
    }

    $catalog = ee_export_collect_taxonomy_catalog();
    $taxonomies = array_keys($catalog);
    $labels = [];
    $descriptions = [];
    foreach ($catalog as $taxonomy => $item) {
        $labels[$taxonomy] = (string)($item['label'] ?? $taxonomy);
        $descriptions[$taxonomy] = (string)($item['description'] ?? '');
    }

    $state['taxonomies'] = $taxonomies;
    $state['taxonomy_labels'] = $labels;
    $state['taxonomy_descriptions'] = $descriptions;

    return $taxonomies;
}

function ee_export_get_export_post_types(array &$state): array {
    if (!empty($state['post_types']) && is_array($state['post_types'])) {
        return array_values(array_filter(array_map('strval', $state['post_types'])));
    }

    $catalog = ee_export_collect_post_type_catalog();
    $postTypes = array_keys($catalog);
    $labels = [];
    $descriptions = [];
    foreach ($catalog as $postType => $item) {
        $labels[$postType] = (string)($item['label'] ?? $postType);
        $descriptions[$postType] = (string)($item['description'] ?? '');
    }

    $state['post_types'] = $postTypes;
    $state['post_type_labels'] = $labels;
    $state['post_type_descriptions'] = $descriptions;

    return $postTypes;
}

function ee_export_collect_taxonomy_catalog(): array {
    $objects = function_exists('get_taxonomies') ? get_taxonomies(['public' => true], 'objects') : [];
    $exclude = ['post_format'];

    $catalog = [];
    if (is_array($objects)) {
        foreach ($objects as $taxonomy => $object) {
            $taxonomy = strtolower(trim((string)$taxonomy));
            if ($taxonomy === '' || in_array($taxonomy, $exclude, true)) {
                continue;
            }
            $catalog[$taxonomy] = [
                'code' => $taxonomy,
                'label' => trim((string)($object->label ?? $taxonomy)),
                'description' => trim((string)($object->description ?? '')),
            ];
        }
    }

    ksort($catalog);
    return $catalog;
}

function ee_export_collect_post_type_catalog(): array {
    $objects = function_exists('get_post_types') ? get_post_types(['public' => true], 'objects') : [];
    $exclude = [
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block',
        'wp_navigation',
        'wp_template',
        'wp_template_part',
    ];

    $catalog = [];
    if (is_array($objects)) {
        foreach ($objects as $postType => $object) {
            $postType = strtolower(trim((string)$postType));
            if ($postType === '' || in_array($postType, $exclude, true)) {
                continue;
            }
            $catalog[$postType] = [
                'code' => $postType,
                'label' => trim((string)($object->label ?? $postType)),
                'description' => trim((string)($object->description ?? '')),
            ];
        }
    }

    ksort($catalog);
    return $catalog;
}

function ee_export_role_from_caps(array $caps): string {
    if (empty($caps)) {
        return 'user';
    }
    $activeCaps = [];
    foreach ($caps as $key => $flag) {
        if ($flag) {
            $activeCaps[] = strtolower((string)$key);
        }
    }
    if (empty($activeCaps)) {
        return 'user';
    }
    if (in_array('administrator', $activeCaps, true) || in_array('admin', $activeCaps, true)) {
        return 'admin';
    }
    if (in_array('moderator', $activeCaps, true)) {
        return 'moderator';
    }
    if (in_array('editor', $activeCaps, true) || in_array('shop_manager', $activeCaps, true) || in_array('manager', $activeCaps, true)) {
        return 'manager';
    }
    return 'user';
}

function ee_export_guess_property_type_by_key(string $metaKey): array {
    $key = strtolower($metaKey);
    if (preg_match('/(image|img|photo|avatar|logo|thumb|thumbnail|icon|gallery)/', $key)) {
        return ['Image', ['image'], 'wp_type:image'];
    }
    if (preg_match('/(file|attachment|upload|document|pdf|doc|xls|csv|archive|zip)/', $key)) {
        return ['File', ['file'], 'wp_type:file'];
    }
    if (preg_match('/(^is_|^has_|_flag$|_bool$|enabled|disabled|active|visible|published|checked)/', $key)) {
        return ['Boolean', ['boolean'], 'wp_type:boolean'];
    }
    if (preg_match('/(_date|date_|_at$|_time$|date|time)/', $key)) {
        return ['Date', ['date'], 'wp_type:date'];
    }
    if (preg_match('/(_count|count_|_qty|qty_|_price|price_|amount|total|rating|number|_num$)/', $key)) {
        return ['Number', ['number'], 'wp_type:number'];
    }
    return ['String', ['text'], 'wp_type:string'];
}

function ee_export_guess_property_is_multiple_by_key(string $metaKey): int {
    $key = strtolower(trim($metaKey));
    if ($key === '') {
        return 0;
    }
    $key = ltrim($key, '_');
    if ($key === '') {
        return 0;
    }

    if (preg_match('/(^|_)(photos|images|gallery|galleries|files|attachments|documents|ids|items|list|values|rows)($|_)/', $key)) {
        return 1;
    }

    return 0;
}

function ee_export_guess_property_type_for_meta_key(string $metaKey): array {
    $likelyMultiple = ee_export_guess_property_is_multiple_by_key($metaKey);

    $acfType = ee_export_acf_field_type_for_meta_key($metaKey);
    if ($acfType === 'image') {
        return ['Image', ['image'], 'wp_type:image', $likelyMultiple, 'РћРїСЂРµРґРµР»РµРЅРѕ РїРѕ С‚РёРїСѓ ACF (image)'];
    }
    if ($acfType === 'file') {
        return ['File', ['file'], 'wp_type:file', $likelyMultiple, 'РћРїСЂРµРґРµР»РµРЅРѕ РїРѕ С‚РёРїСѓ ACF (file)'];
    }
    if ($acfType === 'gallery') {
        return ['Image', ['image'], 'wp_type:image', 1, 'РћРїСЂРµРґРµР»РµРЅРѕ РїРѕ С‚РёРїСѓ ACF (gallery)'];
    }

    $typeGuess = ee_export_guess_property_type_by_key($metaKey);
    $typeName = isset($typeGuess[0]) ? $typeGuess[0] : 'String';
    $typeFields = isset($typeGuess[1]) ? $typeGuess[1] : ['text'];
    $typeSource = isset($typeGuess[2]) ? $typeGuess[2] : 'wp_type:string';
    return [$typeName, $typeFields, $typeSource, $likelyMultiple, 'РћРїСЂРµРґРµР»РµРЅРѕ РїРѕ РєР»СЋС‡Сѓ WP meta'];
}

function ee_export_collect_acf_meta_catalog(): array {
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $cache = [
        'available' => false,
        'names' => [],
        'keys' => [],
        'types_by_name' => [],
        'types_by_key' => [],
        'labels_by_name' => [],
        'labels_by_key' => [],
    ];

    if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
        return $cache;
    }

    $groups = acf_get_field_groups();
    if (!is_array($groups) || empty($groups)) {
        return $cache;
    }

    foreach ($groups as $group) {
        if (!is_array($group) || empty($group['key'])) {
            continue;
        }
        $fields = acf_get_fields($group['key']);
        if (!is_array($fields) || empty($fields)) {
            continue;
        }
        ee_export_collect_acf_fields_recursive(
            $fields,
            $cache['names'],
            $cache['keys'],
            $cache['types_by_name'],
            $cache['types_by_key'],
            $cache['labels_by_name'],
            $cache['labels_by_key']
        );
    }

    $cache['available'] = !empty($cache['names']) ||
        !empty($cache['keys']) ||
        !empty($cache['types_by_name']) ||
        !empty($cache['types_by_key']) ||
        !empty($cache['labels_by_name']) ||
        !empty($cache['labels_by_key']);
    return $cache;
}

function ee_export_collect_acf_fields_recursive(
    array $fields,
    array &$names,
    array &$keys,
    array &$typesByName,
    array &$typesByKey,
    array &$labelsByName,
    array &$labelsByKey
) {
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $fieldType = strtolower(trim((string)($field['type'] ?? '')));
        $fieldLabel = trim((string)($field['label'] ?? ''));
        $name = strtolower(trim((string)($field['name'] ?? '')));
        if ($name !== '') {
            $names[$name] = true;
            if ($fieldType !== '') {
                $typesByName[$name] = $fieldType;
            }
            if ($fieldLabel !== '') {
                $labelsByName[$name] = $fieldLabel;
            }
        }

        $key = strtolower(trim((string)($field['key'] ?? '')));
        if ($key !== '') {
            $keys[$key] = true;
            if ($fieldType !== '') {
                $typesByKey[$key] = $fieldType;
            }
            if ($fieldLabel !== '') {
                $labelsByKey[$key] = $fieldLabel;
            }
        }

        $subFields = isset($field['sub_fields']) ? $field['sub_fields'] : [];
        if (is_array($subFields) && !empty($subFields)) {
            ee_export_collect_acf_fields_recursive(
                $subFields,
                $names,
                $keys,
                $typesByName,
                $typesByKey,
                $labelsByName,
                $labelsByKey
            );
        }

        $layouts = isset($field['layouts']) ? $field['layouts'] : [];
        if (is_array($layouts) && !empty($layouts)) {
            foreach ($layouts as $layout) {
                if (!is_array($layout)) {
                    continue;
                }
                $layoutSubFields = isset($layout['sub_fields']) ? $layout['sub_fields'] : [];
                if (!is_array($layoutSubFields) || empty($layoutSubFields)) {
                    continue;
                }
                ee_export_collect_acf_fields_recursive(
                    $layoutSubFields,
                    $names,
                    $keys,
                    $typesByName,
                    $typesByKey,
                    $labelsByName,
                    $labelsByKey
                );
            }
        }
    }
}

function ee_export_acf_label_for_meta_key(string $metaKey): string {
    $key = strtolower(trim($metaKey));
    if ($key === '') {
        return '';
    }

    $catalog = ee_export_collect_acf_meta_catalog();
    if (empty($catalog['available'])) {
        return '';
    }

    $candidates = [$key];
    if (str_starts_with($key, '_')) {
        $trimmed = trim((string)substr($key, 1));
        if ($trimmed !== '') {
            $candidates[] = $trimmed;
        }
    }

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $labelByName = trim((string)($catalog['labels_by_name'][$candidate] ?? ''));
        if ($labelByName !== '') {
            return $labelByName;
        }
        $labelByKey = trim((string)($catalog['labels_by_key'][$candidate] ?? ''));
        if ($labelByKey !== '') {
            return $labelByKey;
        }
    }

    return '';
}

function ee_export_collect_acf_field_catalog_by_source(array &$state): array {
    static $cache = [];

    $selectedPostTypes = array_values(array_filter(array_map('strval', (array)ee_export_get_export_post_types($state))));
    $selectedTaxonomies = array_values(array_filter(array_map('strval', (array)ee_export_get_export_taxonomies($state))));
    sort($selectedPostTypes);
    sort($selectedTaxonomies);

    $cacheKey = md5(ee_export_json_encode([
        'post_types' => $selectedPostTypes,
        'taxonomies' => $selectedTaxonomies,
    ]));
    if (isset($cache[$cacheKey]) && is_array($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $result = [
        'postmeta' => [],
        'termmeta' => [],
    ];

    if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
        $cache[$cacheKey] = $result;
        return $result;
    }

    $groups = acf_get_field_groups();
    if (!is_array($groups) || empty($groups)) {
        $cache[$cacheKey] = $result;
        return $result;
    }

    foreach ($groups as $group) {
        if (!is_array($group) || empty($group['key'])) {
            continue;
        }
        $contexts = ee_export_resolve_acf_group_contexts($group, $selectedPostTypes, $selectedTaxonomies);
        if (empty($contexts)) {
            continue;
        }

        $fields = acf_get_fields($group['key']);
        if (!is_array($fields) || empty($fields)) {
            continue;
        }

        ee_export_collect_acf_fields_for_contexts_recursive($fields, $contexts, $result);
    }

    foreach (['postmeta', 'termmeta'] as $kind) {
        if (!isset($result[$kind]) || !is_array($result[$kind])) {
            $result[$kind] = [];
            continue;
        }
        ksort($result[$kind]);
        foreach ($result[$kind] as $sourceId => $fields) {
            if (!is_array($fields)) {
                unset($result[$kind][$sourceId]);
                continue;
            }
            ksort($fields);
        }
    }

    $cache[$cacheKey] = $result;
    return $result;
}

function ee_export_resolve_acf_group_contexts(array $group, array $selectedPostTypes, array $selectedTaxonomies): array {
    $selectedPostTypes = array_values(array_filter(array_map(static function ($value) {
        return strtolower(trim((string)$value));
    }, $selectedPostTypes), static function ($value) {
        return $value !== '';
    }));
    $selectedTaxonomies = array_values(array_filter(array_map(static function ($value) {
        return strtolower(trim((string)$value));
    }, $selectedTaxonomies), static function ($value) {
        return $value !== '';
    }));

    if (empty($selectedPostTypes) && empty($selectedTaxonomies)) {
        return [];
    }

    $defaultContexts = ee_export_build_default_acf_context_map($selectedPostTypes, $selectedTaxonomies);
    $contextMap = [];
    $locationGroups = $group['location'] ?? null;
    if (!is_array($locationGroups) || empty($locationGroups)) {
        return array_values($defaultContexts);
    }

    foreach ($locationGroups as $ruleGroup) {
        if (!is_array($ruleGroup) || empty($ruleGroup)) {
            continue;
        }

        $postCandidates = $selectedPostTypes;
        $taxonomyCandidates = $selectedTaxonomies;
        $hasPostTypeRule = false;
        $hasTaxonomyRule = false;
        $hasAnyRule = false;

        foreach ($ruleGroup as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $hasAnyRule = true;

            $param = strtolower(trim((string)($rule['param'] ?? '')));
            $operator = trim((string)($rule['operator'] ?? '=='));
            $value = strtolower(trim((string)($rule['value'] ?? '')));

            if ($param === 'post_type') {
                $hasPostTypeRule = true;
                if ($value === '' || $value === 'all') {
                    continue;
                }
                if ($operator === '!=' || $operator === '!==') {
                    $postCandidates = array_values(array_filter($postCandidates, static function ($candidate) use ($value) {
                        return strtolower(trim((string)$candidate)) !== $value;
                    }));
                } else {
                    $postCandidates = array_values(array_filter($postCandidates, static function ($candidate) use ($value) {
                        return strtolower(trim((string)$candidate)) === $value;
                    }));
                }
                continue;
            }

            if ($param === 'taxonomy') {
                $hasTaxonomyRule = true;
                $taxonomyValue = ee_export_extract_acf_taxonomy_code($value);
                if ($taxonomyValue === '' || $taxonomyValue === 'all') {
                    continue;
                }
                if ($operator === '!=' || $operator === '!==') {
                    $taxonomyCandidates = array_values(array_filter($taxonomyCandidates, static function ($candidate) use ($taxonomyValue) {
                        return strtolower(trim((string)$candidate)) !== $taxonomyValue;
                    }));
                } else {
                    $taxonomyCandidates = array_values(array_filter($taxonomyCandidates, static function ($candidate) use ($taxonomyValue) {
                        return strtolower(trim((string)$candidate)) === $taxonomyValue;
                    }));
                }
                continue;
            }
        }

        if ($hasPostTypeRule) {
            foreach ($postCandidates as $postType) {
                $sourceId = 'post_type:' . $postType;
                $contextMap['postmeta|' . $sourceId] = [
                    'kind' => 'postmeta',
                    'source_id' => $sourceId,
                ];
            }
        }
        if ($hasTaxonomyRule) {
            foreach ($taxonomyCandidates as $taxonomy) {
                $sourceId = 'taxonomy:' . $taxonomy;
                $contextMap['termmeta|' . $sourceId] = [
                    'kind' => 'termmeta',
                    'source_id' => $sourceId,
                ];
            }
        }

        if (!$hasPostTypeRule && !$hasTaxonomyRule && $hasAnyRule && !empty($defaultContexts)) {
            foreach ($defaultContexts as $contextKey => $contextValue) {
                $contextMap[$contextKey] = $contextValue;
            }
        }
    }

    if (empty($contextMap) && !empty($defaultContexts)) {
        foreach ($defaultContexts as $contextKey => $contextValue) {
            $contextMap[$contextKey] = $contextValue;
        }
    }

    return array_values($contextMap);
}

function ee_export_build_default_acf_context_map(array $selectedPostTypes, array $selectedTaxonomies): array {
    $contextMap = [];

    foreach ($selectedPostTypes as $postType) {
        $postType = strtolower(trim((string)$postType));
        if ($postType === '') {
            continue;
        }
        $sourceId = 'post_type:' . $postType;
        $contextMap['postmeta|' . $sourceId] = [
            'kind' => 'postmeta',
            'source_id' => $sourceId,
        ];
    }

    foreach ($selectedTaxonomies as $taxonomy) {
        $taxonomy = strtolower(trim((string)$taxonomy));
        if ($taxonomy === '') {
            continue;
        }
        $sourceId = 'taxonomy:' . $taxonomy;
        $contextMap['termmeta|' . $sourceId] = [
            'kind' => 'termmeta',
            'source_id' => $sourceId,
        ];
    }

    return $contextMap;
}

function ee_export_extract_acf_taxonomy_code(string $value): string {
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }
    if (str_contains($value, ':')) {
        $parts = explode(':', $value, 2);
        $value = strtolower(trim((string)($parts[0] ?? '')));
    }
    return $value;
}

function ee_export_collect_acf_fields_for_contexts_recursive(array $fields, array $contexts, array &$catalog) {
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $metaKey = strtolower(trim((string)($field['name'] ?? '')));
        $label = trim((string)($field['label'] ?? ''));
        $type = strtolower(trim((string)($field['type'] ?? '')));
        $fieldKey = strtolower(trim((string)($field['key'] ?? '')));

        if ($metaKey !== '') {
            foreach ($contexts as $context) {
                if (!is_array($context)) {
                    continue;
                }
                $kind = strtolower(trim((string)($context['kind'] ?? '')));
                $sourceId = strtolower(trim((string)($context['source_id'] ?? '')));
                if (($kind !== 'postmeta' && $kind !== 'termmeta') || $sourceId === '') {
                    continue;
                }

                if (!isset($catalog[$kind]) || !is_array($catalog[$kind])) {
                    $catalog[$kind] = [];
                }
                if (!isset($catalog[$kind][$sourceId]) || !is_array($catalog[$kind][$sourceId])) {
                    $catalog[$kind][$sourceId] = [];
                }
                if (!isset($catalog[$kind][$sourceId][$metaKey]) || !is_array($catalog[$kind][$sourceId][$metaKey])) {
                    $catalog[$kind][$sourceId][$metaKey] = [
                        'meta_key' => $metaKey,
                        'label' => $label,
                        'type' => $type,
                        'field_key' => $fieldKey,
                    ];
                } else {
                    if ($label !== '' && trim((string)($catalog[$kind][$sourceId][$metaKey]['label'] ?? '')) === '') {
                        $catalog[$kind][$sourceId][$metaKey]['label'] = $label;
                    }
                    if ($type !== '' && trim((string)($catalog[$kind][$sourceId][$metaKey]['type'] ?? '')) === '') {
                        $catalog[$kind][$sourceId][$metaKey]['type'] = $type;
                    }
                    if ($fieldKey !== '' && trim((string)($catalog[$kind][$sourceId][$metaKey]['field_key'] ?? '')) === '') {
                        $catalog[$kind][$sourceId][$metaKey]['field_key'] = $fieldKey;
                    }
                }
            }
        }

        $subFields = $field['sub_fields'] ?? null;
        if (is_array($subFields) && !empty($subFields)) {
            ee_export_collect_acf_fields_for_contexts_recursive($subFields, $contexts, $catalog);
        }

        $layouts = $field['layouts'] ?? null;
        if (is_array($layouts) && !empty($layouts)) {
            foreach ($layouts as $layout) {
                if (!is_array($layout)) {
                    continue;
                }
                $layoutSubFields = $layout['sub_fields'] ?? null;
                if (!is_array($layoutSubFields) || empty($layoutSubFields)) {
                    continue;
                }
                ee_export_collect_acf_fields_for_contexts_recursive($layoutSubFields, $contexts, $catalog);
            }
        }
    }
}

function ee_export_collect_existing_meta_keys_for_source(array $ctx, array &$state, string $source): array {
    static $cache = [];

    $source = strtolower(trim($source));
    if ($source !== 'termmeta' && $source !== 'postmeta') {
        return [];
    }

    $selectedPostTypes = array_values(array_filter(array_map('strval', (array)ee_export_get_export_post_types($state))));
    $selectedTaxonomies = array_values(array_filter(array_map('strval', (array)ee_export_get_export_taxonomies($state))));
    sort($selectedPostTypes);
    sort($selectedTaxonomies);
    $cacheKey = $source . '|' . md5(ee_export_json_encode([
        'post_types' => $selectedPostTypes,
        'taxonomies' => $selectedTaxonomies,
    ]));
    if (isset($cache[$cacheKey]) && is_array($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $wpdb = $ctx['wpdb'];
    $keys = [];

    if ($source === 'termmeta') {
        $table = (string)$ctx['tables']['termmeta'];
        $ttTable = (string)$ctx['tables']['term_taxonomy'];
        if (ee_export_table_exists($ctx, $table) && ee_export_table_exists($ctx, $ttTable) && !empty($selectedTaxonomies)) {
            $in = ee_export_prepare_string_in($selectedTaxonomies);
            $sql = "SELECT tm.meta_key
                FROM {$table} tm
                INNER JOIN {$ttTable} tt ON tt.term_id = tm.term_id
                WHERE tm.meta_key IS NOT NULL AND tm.meta_key <> '' AND tt.taxonomy IN ({$in['placeholders']})
                GROUP BY tm.meta_key
                ORDER BY tm.meta_key ASC";
            $prepared = $wpdb->prepare($sql, ...$in['params']);
            $rows = $wpdb->get_col($prepared);
            if (is_array($rows)) {
                $keys = $rows;
            }
        }
    } elseif ($source === 'postmeta') {
        $table = (string)$ctx['tables']['postmeta'];
        $postsTable = (string)$ctx['tables']['posts'];
        if (ee_export_table_exists($ctx, $table) && ee_export_table_exists($ctx, $postsTable) && !empty($selectedPostTypes)) {
            $in = ee_export_prepare_string_in($selectedPostTypes);
            $sql = "SELECT pm.meta_key
                FROM {$table} pm
                INNER JOIN {$postsTable} p ON p.ID = pm.post_id
                WHERE pm.meta_key IS NOT NULL AND pm.meta_key <> '' AND p.post_type IN ({$in['placeholders']})
                GROUP BY pm.meta_key
                ORDER BY pm.meta_key ASC";
            $prepared = $wpdb->prepare($sql, ...$in['params']);
            $rows = $wpdb->get_col($prepared);
            if (is_array($rows)) {
                $keys = $rows;
            }
        }
    }

    $normalized = [];
    foreach ($keys as $metaKey) {
        $metaKey = strtolower(trim((string)$metaKey));
        if ($metaKey === '') {
            continue;
        }
        $normalized[$metaKey] = $metaKey;
    }

    $cache[$cacheKey] = array_values($normalized);
    return $cache[$cacheKey];
}

function ee_export_collect_existing_meta_keys_for_set_source(array $ctx, string $setSourceId): array {
    static $cache = [];

    $setSourceId = strtolower(trim($setSourceId));
    if ($setSourceId === '') {
        return [];
    }
    if (isset($cache[$setSourceId]) && is_array($cache[$setSourceId])) {
        return $cache[$setSourceId];
    }

    $wpdb = $ctx['wpdb'];
    $keys = [];

    if (str_starts_with($setSourceId, 'taxonomy:')) {
        $taxonomy = trim((string)substr($setSourceId, strlen('taxonomy:')));
        $table = (string)$ctx['tables']['termmeta'];
        $ttTable = (string)$ctx['tables']['term_taxonomy'];
        if ($taxonomy !== '' && ee_export_table_exists($ctx, $table) && ee_export_table_exists($ctx, $ttTable)) {
            $sql = "SELECT tm.meta_key
                FROM {$table} tm
                INNER JOIN {$ttTable} tt ON tt.term_id = tm.term_id
                WHERE tm.meta_key IS NOT NULL AND tm.meta_key <> '' AND tt.taxonomy = %s
                GROUP BY tm.meta_key
                ORDER BY tm.meta_key ASC";
            $prepared = $wpdb->prepare($sql, $taxonomy);
            $rows = $wpdb->get_col($prepared);
            if (is_array($rows)) {
                $keys = $rows;
            }
        }
    } elseif (str_starts_with($setSourceId, 'post_type:')) {
        $postType = trim((string)substr($setSourceId, strlen('post_type:')));
        $table = (string)$ctx['tables']['postmeta'];
        $postsTable = (string)$ctx['tables']['posts'];
        if ($postType !== '' && ee_export_table_exists($ctx, $table) && ee_export_table_exists($ctx, $postsTable)) {
            $sql = "SELECT pm.meta_key
                FROM {$table} pm
                INNER JOIN {$postsTable} p ON p.ID = pm.post_id
                WHERE pm.meta_key IS NOT NULL AND pm.meta_key <> '' AND p.post_type = %s
                GROUP BY pm.meta_key
                ORDER BY pm.meta_key ASC";
            $prepared = $wpdb->prepare($sql, $postType);
            $rows = $wpdb->get_col($prepared);
            if (is_array($rows)) {
                $keys = $rows;
            }
        }
    }

    $normalized = [];
    foreach ($keys as $metaKey) {
        $metaKey = strtolower(trim((string)$metaKey));
        if ($metaKey === '') {
            continue;
        }
        $normalized[$metaKey] = $metaKey;
    }

    $cache[$setSourceId] = array_values($normalized);
    return $cache[$setSourceId];
}

function ee_export_acf_field_type_for_meta_key(string $metaKey) {
    $key = strtolower(trim($metaKey));
    if ($key === '') {
        return null;
    }

    $catalog = ee_export_collect_acf_meta_catalog();
    if (empty($catalog['available'])) {
        return null;
    }

    $candidates = [$key];
    if (str_starts_with($key, '_')) {
        $trimmed = substr($key, 1);
        if ($trimmed !== '') {
            $candidates[] = $trimmed;
        }
    }

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        if (!empty($catalog['types_by_key'][$candidate])) {
            return (string)$catalog['types_by_key'][$candidate];
        }
        if (!empty($catalog['types_by_name'][$candidate])) {
            return (string)$catalog['types_by_name'][$candidate];
        }
    }

    return null;
}

function ee_export_is_acf_meta_key(string $metaKey): bool {
    $key = strtolower(trim($metaKey));
    if ($key === '') {
        return false;
    }

    if (ee_export_acf_field_type_for_meta_key($key) !== null) {
        return true;
    }

    $catalog = ee_export_collect_acf_meta_catalog();
    if (!empty($catalog['available'])) {
        if (!empty($catalog['names'][$key]) || !empty($catalog['keys'][$key])) {
            return true;
        }
        if (str_starts_with($key, '_')) {
            $trimmed = substr($key, 1);
            if (!empty($catalog['names'][$trimmed]) || !empty($catalog['keys'][$trimmed])) {
                return true;
            }
        }
    }

    if (str_starts_with($key, 'field_')) {
        return true;
    }
    if (str_starts_with($key, '_') && str_starts_with(substr($key, 1), 'field_')) {
        return true;
    }

    return false;
}

function ee_export_attachment_url($id): string {
    static $cache = [];

    $attachmentId = 0;
    if (is_int($id)) {
        $attachmentId = $id;
    } elseif (is_float($id)) {
        $attachmentId = (int)$id;
    } elseif (is_string($id)) {
        $trimmed = trim($id);
        if ($trimmed !== '' && ctype_digit($trimmed)) {
            $attachmentId = (int)$trimmed;
        }
    }

    if ($attachmentId <= 0) {
        return '';
    }

    if (array_key_exists($attachmentId, $cache)) {
        return $cache[$attachmentId];
    }

    $url = '';
    if (function_exists('wp_get_original_image_url')) {
        $original = wp_get_original_image_url($attachmentId);
        if (is_string($original) && $original !== '') {
            $url = $original;
        }
    }
    if ($url === '' && function_exists('wp_get_attachment_url')) {
        $attachmentUrl = wp_get_attachment_url($attachmentId);
        if (is_string($attachmentUrl) && $attachmentUrl !== '') {
            $url = $attachmentUrl;
        }
    }

    $url = trim($url);
    $cache[$attachmentId] = $url;
    return $url;
}

function ee_export_meta_key_looks_media(string $metaKey): bool {
    $key = strtolower(trim($metaKey));
    if ($key === '') {
        return false;
    }

    $trimmed = ltrim($key, '_');
    if ($trimmed === 'thumbnail_id' || $trimmed === 'thumbnail') {
        return true;
    }

    $parts = explode('_', $trimmed);
    $tokens = [];
    foreach ($parts as $part) {
        $part = strtolower(trim((string)$part));
        if ($part === '' || ctype_digit($part)) {
            continue;
        }
        $tokens[] = $part;
    }
    if (empty($tokens)) {
        return false;
    }

    $mediaTokens = [
        'image',
        'images',
        'img',
        'photo',
        'photos',
        'avatar',
        'logo',
        'icon',
        'thumb',
        'thumbnail',
        'gallery',
        'galleries',
        'file',
        'files',
        'attachment',
        'attachments',
        'document',
        'documents',
    ];

    $last = (string)end($tokens);
    if (in_array($last, $mediaTokens, true)) {
        return true;
    }

    if (in_array($last, ['id', 'ids', 'url', 'src'], true) && count($tokens) >= 2) {
        $prev = (string)$tokens[count($tokens) - 2];
        if (in_array($prev, $mediaTokens, true)) {
            return true;
        }
    }

    return false;
}

function ee_export_convert_media_value_to_url($value) {
    if (is_int($value) || is_float($value)) {
        $url = ee_export_attachment_url($value);
        return $url !== '' ? $url : $value;
    }

    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return ee_export_convert_media_value_to_url($decoded);
            }
        }
        if ($trimmed !== '' && ctype_digit($trimmed)) {
            $url = ee_export_attachment_url($trimmed);
            return $url !== '' ? $url : $value;
        }

        if ($trimmed !== '' && preg_match('/^[0-9,\s;|]+$/', $trimmed)) {
            $chunks = preg_split('/[\s,;|]+/', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($chunks) && !empty($chunks)) {
                $urls = [];
                $isList = true;
                foreach ($chunks as $chunk) {
                    $chunk = trim((string)$chunk);
                    if ($chunk === '' || !ctype_digit($chunk)) {
                        $isList = false;
                        break;
                    }
                    $url = ee_export_attachment_url($chunk);
                    $urls[] = $url !== '' ? $url : $chunk;
                }
                if ($isList) {
                    return $urls;
                }
            }
        }
        return $value;
    }

    if (is_object($value)) {
        $value = get_object_vars($value);
    }

    if (is_array($value)) {
        if (array_key_exists('ID', $value) || array_key_exists('id', $value)) {
            $attachmentId = array_key_exists('ID', $value) ? $value['ID'] : $value['id'];
            $url = ee_export_attachment_url($attachmentId);
            if ($url !== '') {
                return $url;
            }
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = ee_export_convert_media_value_to_url($item);
        }
        return $result;
    }

    return $value;
}

function ee_export_humanize_fallback_title(string $slug, int $postId): string {
    $slug = trim($slug);
    if ($slug !== '') {
        $slug = preg_replace('/[-_]+/', ' ', $slug) ?? $slug;
        $slug = preg_replace('/\s+/u', ' ', $slug) ?? $slug;
        $slug = trim($slug);
        if ($slug !== '' && !preg_match('/^\d+$/', $slug)) {
            return mb_convert_case($slug, MB_CASE_TITLE, 'UTF-8');
        }
    }

    return 'Запись #' . $postId;
}

function ee_export_should_include_meta_key(array $state, string $metaKey): bool {
    $metaKey = strtolower(trim($metaKey));
    if ($metaKey === '') {
        return false;
    }

    if ($metaKey === '_thumbnail_id') {
        return true;
    }

    if (ee_export_is_hard_excluded_meta_key($metaKey)) {
        return false;
    }

    $includePrivate = !empty($state['include_private_meta_keys']);
    if (!$includePrivate && str_starts_with($metaKey, '_')) {
        return false;
    }

    $acfMode = ee_export_normalize_acf_mode((string)($state['acf_export_mode'] ?? 'all'));
    $isAcf = ee_export_is_acf_meta_key($metaKey);
    if ($acfMode === 'only_acf' && !$isAcf) {
        return false;
    }
    if ($acfMode === 'without_acf' && $isAcf) {
        return false;
    }

    return true;
}

function ee_export_is_hard_excluded_meta_key(string $metaKey): bool {
    $metaKey = strtolower(trim($metaKey));
    if ($metaKey === '' || $metaKey === '_thumbnail_id') {
        return false;
    }

    if ((bool)preg_match('/^_?field_[a-z0-9]+$/i', $metaKey)) {
        return true;
    }

    if (ee_export_is_known_technical_meta_key($metaKey)) {
        return true;
    }

    if (str_starts_with($metaKey, '_')) {
        $publicMetaKey = ltrim($metaKey, '_');
        if ($publicMetaKey !== '' && ee_export_is_acf_meta_key($publicMetaKey)) {
            return true;
        }
    }

    return false;
}

function ee_export_is_known_technical_meta_key(string $metaKey): bool {
    $metaKey = strtolower(trim($metaKey));
    if ($metaKey === '') {
        return false;
    }

    static $exactMatch = [
        '_edit_last',
        '_edit_lock',
        '_pingme',
        '_encloseme',
        '_trackbackme',
        '_wp_old_slug',
        '_wp_page_template',
        '_wp_desired_post_slug',
    ];

    if (in_array($metaKey, $exactMatch, true)) {
        return true;
    }

    static $patternMatch = [
        '/^_oembed_/i',
        '/^_wp_trash_/i',
        '/^_wp_attachment_/i',
        '/^_menu_item_/i',
    ];

    foreach ($patternMatch as $pattern) {
        if ((bool)preg_match($pattern, $metaKey)) {
            return true;
        }
    }

    return false;
}

function ee_export_prepare_meta_value($raw) {
    $value = maybe_unserialize($raw);
    return ee_export_normalize_value($value);
}

function ee_export_prepare_meta_value_with_context(array $state, string $metaKey, $rawValue) {
    unset($state);

    $value = ee_export_prepare_meta_value($rawValue);
    $normalizedMetaKey = strtolower(trim($metaKey));
    $acfType = ee_export_acf_field_type_for_meta_key($normalizedMetaKey);
    $looksLikeMedia = ee_export_meta_key_looks_media($normalizedMetaKey);

    if (in_array($acfType, ['image', 'file', 'gallery'], true)) {
        return ee_export_convert_media_value_to_url($value);
    }
    if ($normalizedMetaKey === '_thumbnail_id') {
        return ee_export_convert_media_value_to_url($value);
    }
    if ($looksLikeMedia) {
        return ee_export_convert_media_value_to_url($value);
    }

    return $value;
}

function ee_export_normalize_value($value) {
    if ($value === null) {
        return '';
    }
    if (is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
        return $value;
    }
    if (is_object($value)) {
        $value = get_object_vars($value);
    }
    if (is_array($value)) {
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[(string)$key] = ee_export_normalize_value($item);
        }
        return $normalized;
    }
    return (string)$value;
}

function ee_export_map_status(string $wpStatus): string {
    $status = strtolower(trim($wpStatus));
    if (in_array($status, ['publish', 'future', 'inherit'], true)) {
        return 'active';
    }
    if (in_array($status, ['trash'], true)) {
        return 'disabled';
    }
    if (in_array($status, ['draft', 'pending', 'private', 'auto-draft'], true)) {
        return 'hidden';
    }
    return 'active';
}

function ee_export_phase_label(string $phase): string {
    $map = [
        'not_started' => 'не запущено',
        'users' => 'пользователи',
        'category_types' => 'типы категорий',
        'property_types' => 'типы свойств',
        'property_sets' => 'наборы свойств',
        'properties' => 'свойства',
        'type_set_links' => 'связи тип-набор',
        'set_property_links' => 'связи набор-свойство',
        'categories' => 'категории',
        'pages' => 'страницы',
        'property_values' => 'значения свойств',
        'finalize' => 'завершение',
        'done' => 'завершено',
        'unknown' => 'неизвестно',
    ];

    return isset($map[$phase]) ? $map[$phase] : $phase;
}

function ee_export_prepare_string_in(array $items): array {
    $items = array_values(array_filter(
        array_map(static function ($item) {
            return trim((string)$item);
        }, $items),
        static function (string $item): bool {
            return $item !== '';
        }
    ));
    if (empty($items)) {
        return ['placeholders' => "''", 'params' => []];
    }
    return [
        'placeholders' => implode(',', array_fill(0, count($items), '%s')),
        'params' => $items,
    ];
}

function ee_export_ensure_dir(string $path) {
    if ($path === '') {
        return;
    }
    if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
        throw new RuntimeException('Не удалось создать директорию: ' . $path);
    }
}

function ee_export_json(array $payload, int $httpCode = 200) {
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    $json = ee_export_json_encode($payload);
    echo is_string($json) ? $json : '{"success":false,"message":"Ошибка JSON-кодирования"}';
}

function ee_export_json_encode($data, int $extraFlags = 0) {
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | $extraFlags;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    return json_encode($data, $flags);
}

function ee_export_append_jsonl(array $ctx, string $fileKey, array $row): bool {
    static $writesSinceCheck = 0;

    $relative = (string)($ctx['files'][$fileKey] ?? '');
    if ($relative === '') {
        return false;
    }
    $path = rtrim((string)$ctx['base_dir'], '/\\') . '/' . ltrim($relative, '/\\');
    $line = ee_export_json_encode($row);
    if (!is_string($line)) {
        return false;
    }
    $result = file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX) !== false;
    if ($result) {
        $writesSinceCheck++;
        if ($writesSinceCheck >= 120) {
            $writesSinceCheck = 0;
            ee_export_assert_workspace_has_space($ctx);
        }
    }
    return $result;
}

function ee_export_table_exists(array $ctx, string $table): bool {
    static $cache = [];
    $key = strtolower($table);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $wpdb = $ctx['wpdb'];
    $like = $wpdb->esc_like($table);
    $sql = $wpdb->prepare('SHOW TABLES LIKE %s', $like);
    $exists = (bool)$wpdb->get_var($sql);
    $cache[$key] = $exists;
    return $exists;
}

function ee_export_render_page(array $ctx): string {
    $state = ee_export_load_state($ctx);
    $phase = is_array($state) ? (string)($state['phase'] ?? 'not_started') : 'not_started';
    $phaseLabel = ee_export_phase_label($phase);
    $done = is_array($state) ? !empty($state['done']) : false;
    $downloadUrl = is_array($state) ? (string)($state['zip_url'] ?? '') : '';
    $siteUrl = esc_html((string)home_url('/'));
    $workspaceDir = esc_html((string)($ctx['base_dir'] ?? ''));
    $workspaceBytes = ee_export_workspace_size($ctx);
    $workspaceLimitBytes = ee_export_workspace_limit_bytes();
    $workspaceHuman = ee_export_format_bytes($workspaceBytes);
    $workspaceLimitHuman = $workspaceLimitBytes > 0 ? ee_export_format_bytes($workspaceLimitBytes) : 'без ограничения';
    $availableTaxonomyCatalog = ee_export_collect_taxonomy_catalog();
    $availablePostTypeCatalog = ee_export_collect_post_type_catalog();

    $selectedTaxonomies = is_array($state) && !empty($state['taxonomies']) && is_array($state['taxonomies'])
        ? array_values(array_filter(array_map('strval', $state['taxonomies'])))
        : array_keys($availableTaxonomyCatalog);
    $selectedPostTypes = is_array($state) && !empty($state['post_types']) && is_array($state['post_types'])
        ? array_values(array_filter(array_map('strval', $state['post_types'])))
        : array_keys($availablePostTypeCatalog);

    $selectedTaxonomiesSet = array_fill_keys(array_map('strval', $selectedTaxonomies), true);
    $selectedPostTypesSet = array_fill_keys(array_map('strval', $selectedPostTypes), true);
    $acfMode = ee_export_normalize_acf_mode((string)(is_array($state) ? ($state['acf_export_mode'] ?? 'all') : 'all'));
    $includePrivateMetaKeys = is_array($state) ? !empty($state['include_private_meta_keys']) : false;

    ob_start();
    ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EE WordPress Экспортер</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #1f1f1f; background: #f6f8fb; }
        .card { background: #fff; border: 1px solid #dce3ef; border-radius: 10px; padding: 18px; max-width: 1060px; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 12px; }
        .btn { border: 1px solid #1255b5; background: #1769e0; color: #fff; border-radius: 6px; padding: 8px 14px; cursor: pointer; font-weight: 600; }
        .btn:disabled { opacity: 0.6; cursor: default; }
        .btn.secondary { background: #fff; color: #1769e0; }
        .hint { font-size: 13px; color: #505a6b; }
        .status { font-weight: 700; }
        pre { background: #0f172a; color: #d1e6ff; border-radius: 8px; padding: 12px; min-height: 280px; max-height: 560px; overflow: auto; }
        input[type=number], select { padding: 6px; border: 1px solid #c2ccde; border-radius: 6px; }
        input[type=number] { width: 110px; }
        a.link { color: #1769e0; font-weight: 700; }
        code { background: #eef3fb; padding: 2px 6px; border-radius: 4px; }
        .step-title { margin: 14px 0 8px; font-size: 18px; font-weight: 700; }
        .sources-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        .source-box { border: 1px solid #dce3ef; border-radius: 8px; padding: 10px; background: #fafcff; min-height: 150px; }
        .source-box h3 { margin: 0 0 8px 0; font-size: 15px; }
        .source-list { max-height: 190px; overflow: auto; font-size: 13px; }
        .source-item { margin-bottom: 6px; }
        .source-item .meta { color: #6a7487; font-size: 12px; margin-left: 20px; }
        .panel { border: 1px solid #dce3ef; border-radius: 8px; padding: 10px; background: #fafcff; margin-bottom: 12px; }
        .inline-label { min-width: 150px; font-weight: 600; }
        .muted { color: #6a7487; font-size: 12px; }
        @media (max-width: 860px) { .sources-grid { grid-template-columns: 1fr; } .inline-label { min-width: 100%; } }
    </style>
</head>
<body>
    <div class="card">
        <h1 style="margin-top:0;">EE WordPress Экспортер</h1>
        <div class="hint">Сайт: <code><?php echo $siteUrl; ?></code></div>
        <div class="hint">После завершения экспорта удалите этот файл из корня WordPress.</div>
        <div class="hint">Рабочая папка: <code id="workspaceDir"><?php echo $workspaceDir; ?></code></div>
        <div class="hint">Использовано места: <code id="workspaceUsage"><?php echo esc_html($workspaceHuman); ?></code> / <code id="workspaceLimit"><?php echo esc_html($workspaceLimitHuman); ?></code></div>
        <hr>

        <div class="panel">
            <div class="step-title" style="margin-top:0;">Шаг 1. Что выгружать из WordPress</div>
            <div class="hint" style="margin-bottom:8px;">
                Выберите таксономии и типы записей. Эти данные формируют будущую структуру импорта в вашей CMS.
            </div>

            <div class="sources-grid">
                <div class="source-box">
                    <h3>Таксономии (типы категорий)</h3>
                    <div class="source-list">
                        <?php if (empty($availableTaxonomyCatalog)): ?>
                            <div class="hint">Публичные таксономии не найдены.</div>
                        <?php else: ?>
                            <?php foreach ($availableTaxonomyCatalog as $taxonomy => $item): ?>
                                <?php
                                $label = trim((string)($item['label'] ?? $taxonomy));
                                $description = trim((string)($item['description'] ?? ''));
                                $checked = isset($selectedTaxonomiesSet[$taxonomy]);
                                ?>
                                <div class="source-item">
                                    <label>
                                        <input type="checkbox" class="js-taxonomy" value="<?php echo esc_attr($taxonomy); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                        <?php echo esc_html($label); ?> (<?php echo esc_html($taxonomy); ?>)
                                    </label>
                                    <?php if ($description !== ''): ?>
                                        <div class="meta"><?php echo esc_html($description); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="source-box">
                    <h3>Типы записей (страницы/контент)</h3>
                    <div class="source-list">
                        <?php if (empty($availablePostTypeCatalog)): ?>
                            <div class="hint">Публичные типы записей не найдены.</div>
                        <?php else: ?>
                            <?php foreach ($availablePostTypeCatalog as $postType => $item): ?>
                                <?php
                                $label = trim((string)($item['label'] ?? $postType));
                                $description = trim((string)($item['description'] ?? ''));
                                $checked = isset($selectedPostTypesSet[$postType]);
                                ?>
                                <div class="source-item">
                                    <label>
                                        <input type="checkbox" class="js-post-type" value="<?php echo esc_attr($postType); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                        <?php echo esc_html($label); ?> (<?php echo esc_html($postType); ?>)
                                    </label>
                                    <?php if ($description !== ''): ?>
                                        <div class="meta"><?php echo esc_html($description); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="muted">
                Выбрано: таксономий <strong id="selectedTaxonomiesCount">0</strong>, типов записей <strong id="selectedPostTypesCount">0</strong>.
            </div>
        </div>

        <div class="panel">
            <div class="step-title" style="margin-top:0;">Шаг 2. Как обрабатывать ACF и meta-ключи</div>
            <div class="row">
                <label for="acfMode" class="inline-label">Поля ACF:</label>
                <select id="acfMode">
                    <option value="all" <?php echo $acfMode === 'all' ? 'selected' : ''; ?>>Выгружать все поля (включая ACF)</option>
                    <option value="only_acf" <?php echo $acfMode === 'only_acf' ? 'selected' : ''; ?>>Выгружать только ACF</option>
                    <option value="without_acf" <?php echo $acfMode === 'without_acf' ? 'selected' : ''; ?>>Выгружать всё, кроме ACF</option>
                </select>
            </div>
            <div class="row" style="margin-bottom:4px;">
                <label style="display:flex;align-items:center;gap:6px;">
                    <input type="checkbox" id="includePrivateMeta" <?php echo $includePrivateMetaKeys ? 'checked' : ''; ?>>
                    Включать служебные ключи (начинаются с `_`)
                </label>
            </div>
            <div class="hint">
                ACF-значения выгружаются из `postmeta` и `termmeta`. Если ACF активен, ключи определяются через API плагина.
            </div>
        </div>

        <div class="panel">
            <div class="step-title" style="margin-top:0;">Шаг 3. Запуск и контроль</div>
            <div class="row">
                <label for="batch" class="inline-label">Строк за шаг:</label>
                <input id="batch" type="number" min="20" max="2000" value="300">
                <button id="startBtn" class="btn">Начать новый экспорт</button>
                <button id="resumeBtn" class="btn secondary">Продолжить</button>
                <button id="stepBtn" class="btn secondary">Один шаг</button>
                <button id="statusBtn" class="btn secondary">Статус</button>
            </div>
            <div class="hint">Для больших сайтов используйте "Продолжить": экспорт будет идти по шагам через AJAX.</div>
        </div>

        <div class="row">
            <div class="status" id="status">Фаза: <?php echo esc_html($phaseLabel); ?><?php echo $done ? ' (завершено)' : ''; ?></div>
        </div>
        <div class="row" id="downloadRow" style="<?php echo $downloadUrl !== '' ? '' : 'display:none;'; ?>">
            <a id="downloadLink" class="link" href="<?php echo esc_url($downloadUrl); ?>" target="_blank" rel="noopener">Скачать ZIP-пакет</a>
        </div>
        <pre id="log"></pre>
    </div>

    <script>
    (function () {
        const statusEl = document.getElementById('status');
        const logEl = document.getElementById('log');
        const batchEl = document.getElementById('batch');
        const startBtn = document.getElementById('startBtn');
        const resumeBtn = document.getElementById('resumeBtn');
        const stepBtn = document.getElementById('stepBtn');
        const statusBtn = document.getElementById('statusBtn');
        const downloadRow = document.getElementById('downloadRow');
        const downloadLink = document.getElementById('downloadLink');
        const workspaceDirEl = document.getElementById('workspaceDir');
        const workspaceUsageEl = document.getElementById('workspaceUsage');
        const workspaceLimitEl = document.getElementById('workspaceLimit');
        const acfModeEl = document.getElementById('acfMode');
        const includePrivateMetaEl = document.getElementById('includePrivateMeta');
        const taxCountEl = document.getElementById('selectedTaxonomiesCount');
        const postTypeCountEl = document.getElementById('selectedPostTypesCount');

        function applyStep2UxTexts() {
            const step2Panel = acfModeEl ? acfModeEl.closest('.panel') : null;
            if (!step2Panel) {
                return;
            }

            const titleEl = step2Panel.querySelector('.step-title');
            if (titleEl) {
                titleEl.textContent = '\u0428\u0430\u0433 2. \u041d\u0430\u0441\u0442\u0440\u043e\u0439\u043a\u0438 ACF \u0438 meta-\u043f\u043e\u043b\u0435\u0439';
            }

            const acfLabelEl = step2Panel.querySelector('label[for=\"acfMode\"]');
            if (acfLabelEl) {
                acfLabelEl.textContent = '\u0420\u0435\u0436\u0438\u043c ACF:';
            }

            if (acfModeEl) {
                const optionAll = acfModeEl.querySelector('option[value=\"all\"]');
                const optionOnlyAcf = acfModeEl.querySelector('option[value=\"only_acf\"]');
                const optionWithoutAcf = acfModeEl.querySelector('option[value=\"without_acf\"]');
                if (optionAll) {
                    optionAll.textContent = '\u0412\u044b\u0433\u0440\u0443\u0436\u0430\u0442\u044c \u0432\u0441\u0435 \u043f\u043e\u043b\u044f (\u0432\u043a\u043b\u044e\u0447\u0430\u044f ACF)';
                }
                if (optionOnlyAcf) {
                    optionOnlyAcf.textContent = '\u0412\u044b\u0433\u0440\u0443\u0436\u0430\u0442\u044c \u0442\u043e\u043b\u044c\u043a\u043e ACF';
                }
                if (optionWithoutAcf) {
                    optionWithoutAcf.textContent = '\u0412\u044b\u0433\u0440\u0443\u0436\u0430\u0442\u044c \u0432\u0441\u0435, \u043a\u0440\u043e\u043c\u0435 ACF';
                }
            }

            if (includePrivateMetaEl) {
                const privateLabelEl = includePrivateMetaEl.closest('label');
                if (privateLabelEl) {
                    while (privateLabelEl.firstChild) {
                        privateLabelEl.removeChild(privateLabelEl.firstChild);
                    }
                    privateLabelEl.appendChild(includePrivateMetaEl);
                    privateLabelEl.appendChild(document.createTextNode(' \u0412\u043a\u043b\u044e\u0447\u0430\u0442\u044c \u0441\u043b\u0443\u0436\u0435\u0431\u043d\u044b\u0435 \"_\" \u043a\u043b\u044e\u0447\u0438 ACF (\u043e\u0431\u044b\u0447\u043d\u043e \u043d\u0435 \u043d\u0443\u0436\u043d\u044b). Featured image \u0432\u0441\u0435\u0433\u0434\u0430 \u0443\u0439\u0434\u0435\u0442 \u0432 \u044d\u043a\u0441\u043f\u043e\u0440\u0442.'));
                }
            }

            const hints = step2Panel.querySelectorAll('.hint');
            if (hints.length > 0) {
                hints[0].textContent = '\u041f\u043e\u043b\u044f ACF \u0442\u0438\u043f\u0430 Image/File/Gallery \u0432\u044b\u0433\u0440\u0443\u0436\u0430\u044e\u0442\u0441\u044f \u043a\u0430\u043a URL-\u0441\u0441\u044b\u043b\u043a\u0438.';
            }
            if (hints.length > 1) {
                hints[1].textContent = '\u041c\u0438\u043d\u0438\u0430\u0442\u044e\u0440\u0430 \u0437\u0430\u043f\u0438\u0441\u0438 (featured image) \u0442\u043e\u0436\u0435 \u0432\u044b\u0433\u0440\u0443\u0436\u0430\u0435\u0442\u0441\u044f \u043a\u0430\u043a URL, \u0434\u0430\u0436\u0435 \u0435\u0441\u043b\u0438 \u0441\u043b\u0443\u0436\u0435\u0431\u043d\u044b\u0435 \u043a\u043b\u044e\u0447\u0438 \u043e\u0442\u043a\u043b\u044e\u0447\u0435\u043d\u044b.';
            } else {
                const extraHint = document.createElement('div');
                extraHint.className = 'hint';
                extraHint.textContent = '\u041c\u0438\u043d\u0438\u0430\u0442\u044e\u0440\u0430 \u0437\u0430\u043f\u0438\u0441\u0438 (featured image) \u0442\u043e\u0436\u0435 \u0432\u044b\u0433\u0440\u0443\u0436\u0430\u0435\u0442\u0441\u044f \u043a\u0430\u043a URL, \u0434\u0430\u0436\u0435 \u0435\u0441\u043b\u0438 \u0441\u043b\u0443\u0436\u0435\u0431\u043d\u044b\u0435 \u043a\u043b\u044e\u0447\u0438 \u043e\u0442\u043a\u043b\u044e\u0447\u0435\u043d\u044b.';
                step2Panel.appendChild(extraHint);
            }
        }

        let running = false;
        let autoLoop = false;
        let retry = 0;
        const maxRetry = 5;
        const logMaxChars = 350000;
        const logTrimChars = 250000;

        applyStep2UxTexts();

        function updateSelectedCounters() {
            const selectedTaxonomyCount = document.querySelectorAll('.js-taxonomy:checked').length;
            const selectedPostTypeCount = document.querySelectorAll('.js-post-type:checked').length;
            if (taxCountEl) {
                taxCountEl.textContent = String(selectedTaxonomyCount);
            }
            if (postTypeCountEl) {
                postTypeCountEl.textContent = String(selectedPostTypeCount);
            }
        }

        function appendLog(text) {
            if (!text) {
                return;
            }
            let next = logEl.textContent + text;
            if (next.length > logMaxChars) {
                next = "[... лог сокращен, старые строки удалены ...]\n" + next.slice(-logTrimChars);
            }
            logEl.textContent = next;
            logEl.scrollTop = logEl.scrollHeight;
        }

        function getBatch() {
            const parsed = parseInt(batchEl.value || '300', 10);
            if (Number.isNaN(parsed)) {
                return 300;
            }
            return Math.min(2000, Math.max(20, parsed));
        }

        function extractJsonObject(text, startIndex) {
            const start = Number(startIndex);
            if (!Number.isInteger(start) || start < 0 || start >= text.length || text.charAt(start) !== '{') {
                return '';
            }

            let depth = 0;
            let inString = false;
            let escaped = false;

            for (let i = start; i < text.length; i++) {
                const ch = text.charAt(i);
                if (inString) {
                    if (escaped) {
                        escaped = false;
                    } else if (ch === '\\') {
                        escaped = true;
                    } else if (ch === '"') {
                        inString = false;
                    }
                    continue;
                }

                if (ch === '"') {
                    inString = true;
                    continue;
                }
                if (ch === '{') {
                    depth++;
                    continue;
                }
                if (ch === '}') {
                    depth--;
                    if (depth === 0) {
                        return text.slice(start, i + 1);
                    }
                }
            }
            return '';
        }

        function parseJsonSafe(raw) {
            const text = String(raw || '').trim();
            if (text === '') {
                throw new Error('Пустой ответ');
            }
            try {
                return JSON.parse(text);
            } catch (e) {
                const starts = [];
                let idx = text.indexOf('{"success"');
                while (idx >= 0) {
                    starts.push(idx);
                    idx = text.indexOf('{"success"', idx + 1);
                }
                if (starts.length === 0) {
                    for (let i = 0; i < text.length; i++) {
                        if (text.charAt(i) === '{') {
                            starts.push(i);
                            if (starts.length >= 40) {
                                break;
                            }
                        }
                    }
                }

                for (let i = 0; i < starts.length; i++) {
                    const start = starts[i];
                    const candidate = extractJsonObject(text, start);
                    if (!candidate) {
                        continue;
                    }
                    try {
                        const parsed = JSON.parse(candidate);
                        const extra = (text.slice(0, start) + text.slice(start + candidate.length)).trim();
                        if (extra) {
                            appendLog(extra + "\n");
                        }
                        return parsed;
                    } catch (ignored) {
                        // Try next candidate.
                    }
                }
                throw e;
            }
        }

        async function callApi(action) {
            const body = new URLSearchParams();
            body.set('ee_action', action);
            body.set('batch', String(getBatch()));
            if (action === 'start') {
                document.querySelectorAll('.js-taxonomy:checked').forEach((el) => {
                    body.append('selected_taxonomies[]', String(el.value || ''));
                });
                document.querySelectorAll('.js-post-type:checked').forEach((el) => {
                    body.append('selected_post_types[]', String(el.value || ''));
                });
                body.set('acf_mode', acfModeEl ? String(acfModeEl.value || 'all') : 'all');
                body.set('include_private_meta_keys', includePrivateMetaEl && includePrivateMetaEl.checked ? '1' : '0');
            }

            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString(),
                credentials: 'same-origin'
            });
            const raw = await response.text();
            const json = parseJsonSafe(raw);
            return { status: response.status, payload: json };
        }

        function refreshControls() {
            const busy = running || autoLoop;
            startBtn.disabled = busy;
            resumeBtn.disabled = busy;
            stepBtn.disabled = busy;
            statusBtn.disabled = busy;
            document.querySelectorAll('.js-taxonomy, .js-post-type').forEach((el) => {
                el.disabled = busy;
            });
            if (acfModeEl) {
                acfModeEl.disabled = busy;
            }
            if (includePrivateMetaEl) {
                includePrivateMetaEl.disabled = busy;
            }
        }

        function phaseLabel(phase) {
            const labels = {
                not_started: 'не запущено',
                users: 'пользователи',
                category_types: 'типы категорий',
                property_types: 'типы свойств',
                property_sets: 'наборы свойств',
                properties: 'свойства',
                type_set_links: 'связи тип-набор',
                set_property_links: 'связи набор-свойство',
                categories: 'категории',
                pages: 'страницы',
                property_values: 'значения свойств',
                finalize: 'завершение',
                done: 'завершено',
                unknown: 'неизвестно'
            };
            const key = String(phase || 'unknown');
            return labels[key] || key;
        }

        function updateStatus(payload) {
            const phase = payload && payload.phase ? String(payload.phase) : 'unknown';
            const done = !!(payload && payload.done);
            statusEl.textContent = 'Фаза: ' + phaseLabel(phase) + (done ? ' (завершено)' : '');

            if (payload && payload.download_url) {
                downloadLink.href = payload.download_url;
                downloadRow.style.display = '';
            }
            if (payload && payload.workspace_human && workspaceUsageEl) {
                workspaceUsageEl.textContent = String(payload.workspace_human);
            }
            if (payload && payload.workspace_limit_human && workspaceLimitEl) {
                workspaceLimitEl.textContent = String(payload.workspace_limit_human);
            }
            if (payload && payload.workspace_dir && workspaceDirEl) {
                workspaceDirEl.textContent = String(payload.workspace_dir);
            }
        }

        async function runStep() {
            if (running) {
                return;
            }
            running = true;
            refreshControls();
            try {
                const result = await callApi('step');
                const payload = result.payload || {};
                if (payload.log) {
                    appendLog(payload.log);
                }
                updateStatus(payload);

                if (payload.success !== true) {
                    appendLog('ОШИБКА: ' + (payload.message || 'Сбой шага') + "\n");
                    autoLoop = false;
                    retry = 0;
                    return;
                }

                if (payload.done) {
                    autoLoop = false;
                    retry = 0;
                    appendLog("Экспорт завершен.\n");
                    return;
                }

                retry = 0;
                if (autoLoop) {
                    setTimeout(runStep, 250);
                }
            } catch (err) {
                retry++;
                appendLog('ПРЕДУПРЕЖДЕНИЕ: сбой шага (' + err.message + '), повтор ' + retry + '/' + maxRetry + "\n");
                if (autoLoop && retry <= maxRetry) {
                    setTimeout(runStep, 1200 + retry * 800);
                } else {
                    autoLoop = false;
                    retry = 0;
                }
            } finally {
                running = false;
                refreshControls();
            }
        }

        startBtn.addEventListener('click', async function () {
            if (running || autoLoop) {
                return;
            }
            const selectedTaxonomyCount = document.querySelectorAll('.js-taxonomy:checked').length;
            const selectedPostTypeCount = document.querySelectorAll('.js-post-type:checked').length;
            if (selectedTaxonomyCount === 0 && selectedPostTypeCount === 0) {
                appendLog('ОШИБКА: выберите минимум один источник (таксономию или тип записи).\n');
                return;
            }
            running = true;
            refreshControls();
            try {
                const result = await callApi('start');
                const payload = result.payload || {};
                if (payload.message) {
                    appendLog(payload.message + "\n");
                }
                updateStatus(payload);
                downloadRow.style.display = 'none';
                autoLoop = true;
                retry = 0;
                setTimeout(runStep, 150);
            } catch (err) {
                appendLog('ОШИБКА: запуск не удался: ' + err.message + "\n");
            } finally {
                running = false;
                refreshControls();
            }
        });

        resumeBtn.addEventListener('click', function () {
            if (running || autoLoop) {
                return;
            }
            autoLoop = true;
            retry = 0;
            refreshControls();
            runStep();
        });

        stepBtn.addEventListener('click', function () {
            if (running || autoLoop) {
                return;
            }
            autoLoop = false;
            retry = 0;
            runStep();
        });

        statusBtn.addEventListener('click', async function () {
            if (running || autoLoop) {
                return;
            }
            running = true;
            refreshControls();
            try {
                const result = await callApi('status');
                const payload = result.payload || {};
                updateStatus(payload);
                if (payload.stats) {
                    appendLog('Статус: ' + JSON.stringify(payload.stats) + "\n");
                }
                if (payload.message) {
                    appendLog(payload.message + "\n");
                }
            } catch (err) {
                appendLog('ОШИБКА: запрос статуса не удался: ' + err.message + "\n");
            } finally {
                running = false;
                refreshControls();
            }
        });

        document.querySelectorAll('.js-taxonomy, .js-post-type').forEach((el) => {
            el.addEventListener('change', updateSelectedCounters);
        });
        updateSelectedCounters();
        refreshControls();
    })();
    </script>
</body>
</html>
    <?php
    return (string)ob_get_clean();
}
