<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

class WordpressImporter extends BaseImporter {

    private const STATE_VERSION = 2;
    private const IMPORT_SCOPE_ALL = 'all';
    private const IMPORT_SCOPE_CORE = 'core';
    private const IMPORT_SCOPE_CONTENT = 'content';
    private const PHASES = [
        'init',
        'pass_users',
        'pass_category_types',
        'pass_category_type_parents',
        'pass_property_types',
        'pass_property_sets',
        'pass_properties',
        'pass_type_set_links',
        'pass_set_property_links',
        'pass_categories',
        'pass_category_parents',
        'pass_pages',
        'pass_page_parents',
        'pass_property_values',
        'finalize',
        'done',
    ];
    private const SCOPE_START_PHASE = [
        self::IMPORT_SCOPE_ALL => 'pass_users',
        self::IMPORT_SCOPE_CORE => 'pass_property_types',
        self::IMPORT_SCOPE_CONTENT => 'pass_users',
    ];
    private const SCOPE_ALLOWED_PHASES = [
        self::IMPORT_SCOPE_ALL => [
            'pass_users',
            'pass_category_types',
            'pass_category_type_parents',
            'pass_property_types',
            'pass_property_sets',
            'pass_properties',
            'pass_type_set_links',
            'pass_set_property_links',
            'pass_categories',
            'pass_category_parents',
            'pass_pages',
            'pass_page_parents',
            'pass_property_values',
        ],
        self::IMPORT_SCOPE_CORE => [
            'pass_property_types',
            'pass_property_sets',
            'pass_properties',
            'pass_set_property_links',
        ],
        self::IMPORT_SCOPE_CONTENT => [
            'pass_users',
            'pass_category_types',
            'pass_category_type_parents',
            'pass_type_set_links',
            'pass_categories',
            'pass_category_parents',
            'pass_pages',
            'pass_page_parents',
            'pass_property_values',
        ],
    ];
    private const PHASE_FILE_MAP = [
        'pass_users' => 'users',
        'pass_category_types' => 'category_types',
        'pass_category_type_parents' => 'category_types',
        'pass_property_types' => 'property_types',
        'pass_property_sets' => 'property_sets',
        'pass_properties' => 'properties',
        'pass_type_set_links' => 'type_set_links',
        'pass_set_property_links' => 'set_property_links',
        'pass_categories' => 'categories',
        'pass_category_parents' => 'categories',
        'pass_pages' => 'pages',
        'pass_page_parents' => 'pages',
        'pass_property_values' => 'property_values',
    ];

    private string $stateFile = '';
    private string $workDir = '';
    private bool $webStepMode = false;
    private bool $webStepDone = false;
    private int $chunkRows = 200;
    private bool $testMode = false;
    private int $testModeLimit = 5;
    private string $languageCode = ENV_DEF_LANG;
    private bool $includePrivateMetaKeys = false;
    private array $allowedTaxonomies = [];
    private array $allowedPostTypes = [];
    private array $allowedSourceIds = [];
    private array $metaIncludePatterns = [];
    private array $metaExcludePatterns = [];
    private array $excludedPropertySourceIds = [];
    private array $sourceTypeMap = [];
    private array $sourceSetMap = [];
    private array $sourcePropertyMap = [];
    private array $additionalTypeSetLinks = [];
    private array $compositePropertyDefinitions = [];
    private array $compositeByMemberSourceId = [];
    private bool $strictCompositePropertyMapping = false;
    private bool $preserveSourcePaths = false;
    private bool $rewriteDonorLinks = true;
    private string $donorBaseUrl = '';
    private array $state = [];
    private string $mapTable = '';
    private string $importScope = self::IMPORT_SCOPE_ALL;
    private array $mappedIdCache = [];
    private array $configuredMapLocalIdCache = [];
    private array $configuredPropertyLocalIdCache = [];
    private array $propertyDefaultFieldsTemplateCache = [];
    private array $propertyValueRowCache = [];

    private $objectModelCategoriesTypes = null;
    private $objectModelCategories = null;
    private $objectModelPages = null;
    private $objectModelProperties = null;

    public function __construct(array $settings) {
        parent::__construct($settings);
        $settings = $this->settings;
        $this->webStepMode = !empty($settings['web_step_mode']);
        $this->chunkRows = $this->webStepMode
            ? max(10, (int)($settings['web_step_chunk_rows'] ?? 120))
            : max(250, (int)($settings['chunk_rows'] ?? 1500));
        $this->testMode = !empty($settings['test_mode']);
        $this->testModeLimit = max(1, (int)($settings['test_mode_limit'] ?? 5));
        $this->languageCode = strtoupper((string)($settings['language_code'] ?? ENV_DEF_LANG));
        $this->includePrivateMetaKeys = !empty($settings['include_private_meta_keys']);
        $this->allowedTaxonomies = $this->normalizeListSetting($settings['allowed_taxonomies'] ?? []);
        $this->allowedPostTypes = $this->normalizeListSetting($settings['allowed_post_types'] ?? []);
        $this->allowedSourceIds = $this->normalizeListSetting($settings['allowed_source_ids'] ?? []);
        $this->metaIncludePatterns = $this->normalizeListSetting($settings['meta_include_patterns'] ?? []);
        $this->metaExcludePatterns = $this->normalizeListSetting($settings['meta_exclude_patterns'] ?? []);
        $this->excludedPropertySourceIds = $this->normalizeListSetting($settings['excluded_property_source_ids'] ?? []);
        $this->sourceTypeMap = $this->normalizeIdMapSetting($settings['source_type_map'] ?? []);
        $this->sourceSetMap = $this->normalizeIdMapSetting($settings['source_set_map'] ?? []);
        $this->sourcePropertyMap = $this->mergeRecommendedPropertyMappings(
            $this->normalizePropertyMapSetting($settings['source_property_map'] ?? [])
        );
        $this->additionalTypeSetLinks = $this->normalizeTypeSetLinksSetting($settings['additional_type_set_links'] ?? []);
        [$this->compositePropertyDefinitions, $this->compositeByMemberSourceId] = $this->normalizeCompositePropertiesSetting(
            $settings['composite_properties_map'] ?? []
        );
        $this->strictCompositePropertyMapping = !empty($this->compositePropertyDefinitions);
        $this->preserveSourcePaths = !empty($settings['preserve_source_paths']);
        $this->rewriteDonorLinks = !array_key_exists('rewrite_donor_links', $settings)
            ? true
            : $this->toBool($settings['rewrite_donor_links'], true);
        $this->donorBaseUrl = $this->normalizeBaseUrl((string)($settings['donor_base_url'] ?? ''));
        $this->importScope = $this->normalizeImportScope((string)($settings['import_scope'] ?? self::IMPORT_SCOPE_ALL));
        $this->stateFile = self::$logDir . 'import_job_' . $this->job_id . '.state.json';
        $this->workDir = rtrim(ENV_SITE_PATH, '/\\') . ENV_DIRSEP . 'uploads' . ENV_DIRSEP . 'tmp' . ENV_DIRSEP . 'wp_import_job_' . $this->job_id;
        $this->mapTable = Constants::IMPORT_MAP_TABLE;
    }

    public function isWebStepDone(): bool {
        return $this->webStepDone;
    }

    protected function _execute() {
        $this->ensureImporterModels();
        $this->ensureImportMapInfrastructure();
        $this->ensurePageUserLinksInfrastructure();

        if ($this->webStepMode && !empty($this->settings['web_step_restart'])) {
            $this->resetStateFiles(true);
            $this->log('WEB STEP: restart requested, state reset.');
        }

        $this->loadOrInitializeState();
        if (!$this->webStepMode && !empty($this->state['done'])) {
            $this->resetStateFiles(true);
            $this->loadOrInitializeState();
        }

        if ($this->webStepMode) {
            $this->runOneStep();
            $this->saveState();
            $this->webStepDone = !empty($this->state['done']);
            return;
        }

        $guard = 0;
        while (empty($this->state['done'])) {
            $guard++;
            if ($guard > 200000) {
                throw new \RuntimeException('Importer guard limit reached.');
            }
            $this->runOneStep();
            $this->saveState();
        }
        $this->webStepDone = true;
    }

    private function runOneStep(): void {
        $phase = $this->resolvePhaseForScope((string)($this->state['phase'] ?? 'init'));
        switch ($phase) {
            case 'init': $this->phaseInit(); break;
            case 'pass_users': $this->processPhase('pass_users', '--- Pass 0: Users ---', fn(array $row) => $this->importUserRow($row), 'pass_category_types'); break;
            case 'pass_category_types': $this->processPhase('pass_category_types', '--- Pass 1: Category types ---', fn(array $row) => $this->importCategoryTypeRow($row), 'pass_category_type_parents'); break;
            case 'pass_category_type_parents': $this->processPhase('pass_category_type_parents', '--- Pass 2: Category type parents ---', fn(array $row) => $this->applyCategoryTypeParentRow($row), 'pass_property_types'); break;
            case 'pass_property_types': $this->processPhase('pass_property_types', '--- Pass 3: Property types ---', fn(array $row) => $this->importPropertyTypeRow($row), 'pass_property_sets'); break;
            case 'pass_property_sets': $this->processPhase('pass_property_sets', '--- Pass 4: Property sets ---', fn(array $row) => $this->importPropertySetRow($row), 'pass_properties'); break;
            case 'pass_properties': $this->processPhase('pass_properties', '--- Pass 5: Properties ---', fn(array $row) => $this->importPropertyRow($row), 'pass_type_set_links'); break;
            case 'pass_type_set_links': $this->processPhase('pass_type_set_links', '--- Pass 6: Type-to-set links ---', fn(array $row) => $this->importTypeSetLinkRow($row), 'pass_set_property_links'); break;
            case 'pass_set_property_links': $this->processPhase('pass_set_property_links', '--- Pass 7: Set-to-property links ---', fn(array $row) => $this->importSetPropertyLinkRow($row), 'pass_categories'); break;
            case 'pass_categories': $this->processPhase('pass_categories', '--- Pass 8: Categories ---', fn(array $row) => $this->importCategoryRow($row), 'pass_category_parents'); break;
            case 'pass_category_parents': $this->processPhase('pass_category_parents', '--- Pass 9: Category parents ---', fn(array $row) => $this->applyCategoryParentRow($row), 'pass_pages'); break;
            case 'pass_pages': $this->processPhase('pass_pages', '--- Pass 10: Pages ---', fn(array $row) => $this->importPageRow($row), 'pass_page_parents'); break;
            case 'pass_page_parents': $this->processPhase('pass_page_parents', '--- Pass 11: Page parents ---', fn(array $row) => $this->applyPageParentRow($row), 'pass_property_values'); break;
            case 'pass_property_values': $this->processPhase('pass_property_values', '--- Pass 12: Property values ---', fn(array $row) => $this->importPropertyValueRow($row), 'finalize'); break;
            case 'finalize': $this->phaseFinalize(); break;
            case 'done': $this->state['done'] = true; $this->webStepDone = true; break;
            default: throw new \RuntimeException('Unknown importer phase: ' . $phase);
        }
    }

    private function processPhase(string $phase, string $title, callable $handler, string $nextPhase): void {
        $this->log($title);
        $done = $this->processJsonlPhase($phase, $handler);
        if ($done) {
            $this->runPhasePostActions($phase);
            $this->logPhaseSummary($phase, $phase . ' finished');
            $this->advancePhase($nextPhase);
            return;
        }
        $this->log('WEB STEP: in_progress (next=' . $phase . ')');
    }

    private function runPhasePostActions(string $phase): void {
        if ($phase === 'pass_type_set_links') {
            $this->applyAdditionalTypeSetLinks();
        }
    }

    private function applyAdditionalTypeSetLinks(): void {
        if (empty($this->additionalTypeSetLinks)) {
            return;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->additionalTypeSetLinks as $row) {
            $result = $this->importTypeSetLinkRow($row);
            $status = strtolower(trim((string)($result['status'] ?? '')));
            switch ($status) {
                case 'created':
                    $created++;
                    break;
                case 'updated':
                    $updated++;
                    break;
                case 'failed':
                    $failed++;
                    break;
                default:
                    $skipped++;
                    break;
            }
        }

        $this->log(sprintf(
            'Additional type-set links applied: created=%d updated=%d skipped=%d failed=%d',
            $created,
            $updated,
            $skipped,
            $failed
        ));
    }

    private function phaseInit(): void {
        $this->log('Collecting source data...');
        $this->preparePackage();
        $this->log('Import scope: ' . $this->importScope);
        $this->log('Import mode: ' . ($this->testMode ? ('TEST (limit=' . $this->testModeLimit . ')') : 'FULL'));
        if ($this->strictCompositePropertyMapping) {
            $this->log('Composite whitelist mode: enabled (import only mapped postmeta/termmeta keys).');
        }
        $this->log('Source key: ' . (string)($this->state['source_key'] ?? ''));
        $manifestFormat = trim((string)($this->state['manifest_format'] ?? ''));
        if ($manifestFormat !== '') {
            $this->log('Package format: ' . $manifestFormat);
        }
        $sourceSystem = trim((string)($this->state['source_system'] ?? ''));
        if ($sourceSystem !== '') {
            $this->log('Source system: ' . $sourceSystem);
        }
        $this->advancePhase($this->getStartPhaseForScope());
    }

    private function phaseFinalize(): void {
        $this->log('Finalization: clear caches if needed.');
        foreach ($this->state['stats'] as $phase => $stats) {
            if (!is_array($stats)) {
                continue;
            }
            $this->log(sprintf('Summary %s: processed=%d created=%d updated=%d skipped=%d failed=%d', $phase, (int)$stats['processed'], (int)$stats['created'], (int)$stats['updated'], (int)$stats['skipped'], (int)$stats['failed']));
        }
        $lifecycleSummary = ProductLifecycleStructureService::ensureObjectLifecycleSet(
            $this->languageCode,
            $this->job_id > 0 ? (int) $this->job_id : null
        );
        $this->state['product_lifecycle_summary'] = $lifecycleSummary;
        $this->log(sprintf(
            'Product lifecycle summary: set_id=%d linked_types=%d pages_seeded=%d inserted=%d status_updates=%d source_updates=%d',
            (int) ($lifecycleSummary['set_id'] ?? 0),
            count((array) ($lifecycleSummary['linked_type_ids'] ?? [])),
            (int) (($lifecycleSummary['seed']['pages_total'] ?? 0)),
            (int) (($lifecycleSummary['seed']['inserted_values'] ?? 0)),
            (int) (($lifecycleSummary['seed']['updated_status'] ?? 0)),
            (int) (($lifecycleSummary['seed']['updated_source'] ?? 0))
        ));
        $mediaQueueSummary = ImportMediaQueueService::queueImportJobMedia($this->job_id, $this->languageCode);
        $this->state['media_queue_summary'] = $mediaQueueSummary;
        $this->log(sprintf(
            'Media queue summary: discovered=%d queued=%d requeued=%d existing_done=%d existing_pending=%d pending=%d done=%d failed=%d',
            (int) ($mediaQueueSummary['discovered'] ?? 0),
            (int) ($mediaQueueSummary['queued'] ?? 0),
            (int) ($mediaQueueSummary['requeued'] ?? 0),
            (int) ($mediaQueueSummary['existing_done'] ?? 0),
            (int) ($mediaQueueSummary['existing_pending'] ?? 0),
            (int) (($mediaQueueSummary['summary']['pending'] ?? 0)),
            (int) (($mediaQueueSummary['summary']['done'] ?? 0)),
            (int) (($mediaQueueSummary['summary']['failed'] ?? 0))
        ));
        $linkRewriteSummary = $this->rewriteImportedDonorLinksBackfill();
        $this->state['donor_link_rewrite_summary'] = $linkRewriteSummary;
        $this->log(sprintf(
            'Donor link rewrite summary: pages=%d categories=%d property_values=%d',
            (int) ($linkRewriteSummary['pages_updated'] ?? 0),
            (int) ($linkRewriteSummary['categories_updated'] ?? 0),
            (int) ($linkRewriteSummary['property_values_updated'] ?? 0)
        ));
        $sourceDir = (string)($this->state['source_dir'] ?? '');
        if ($sourceDir !== '' && is_dir($sourceDir)) {
            $this->removeDirectory($sourceDir);
        }
        $this->state['done'] = true;
        $this->advancePhase('done');
    }

    private function processJsonlPhase(string $phase, callable $handler): bool {
        $stats = $this->state['stats'][$phase] ?? $this->emptyStats();
        $this->state['stats'][$phase] = $stats;

        if ($this->testMode && (int)$stats['processed'] >= $this->testModeLimit) {
            $this->state['cursors'][$phase] = ['line' => (int)($this->state['cursors'][$phase]['line'] ?? 0), 'done' => true];
            return true;
        }

        $filePath = $this->getPhaseFilePath($phase);
        if ($filePath === '' || !is_file($filePath) || !is_readable($filePath)) {
            $this->log('No readable file for phase ' . $phase . '. Skipping.');
            $this->state['cursors'][$phase] = ['line' => 0, 'done' => true];
            return true;
        }

        $ext = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'json') {
            return $this->processJsonArrayPhase($phase, $handler, $filePath);
        }

        $cursor = $this->state['cursors'][$phase] ?? ['line' => 0, 'done' => false];
        if (!empty($cursor['done'])) {
            return true;
        }

        $lineNo = max(0, (int)($cursor['line'] ?? 0));
        $processedThisStep = 0;
        $limit = $this->testMode ? $this->testModeLimit : PHP_INT_MAX;

        $file = new \SplFileObject($filePath, 'rb');
        if ($lineNo > 0) {
            $file->seek($lineNo);
        }

        while (!$file->eof()) {
            if ($processedThisStep >= $this->chunkRows) {
                break;
            }
            $stats = $this->state['stats'][$phase] ?? $this->emptyStats();
            if ((int)$stats['processed'] >= $limit) {
                break;
            }

            $raw = $file->fgets();
            if ($raw === false) {
                break;
            }

            $lineNo++;
            $line = trim((string)$raw);
            if ($lineNo === 1) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
            }
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                $this->registerPhaseResult($phase, ['status' => 'failed', 'message' => 'Invalid JSONL row at line ' . $lineNo]);
                $processedThisStep++;
                continue;
            }

            try {
                $result = $handler($decoded);
            } catch (\Throwable $e) {
                $result = ['status' => 'failed', 'message' => $e->getMessage()];
            }

            $this->registerPhaseResult($phase, is_array($result) ? $result : ['status' => 'failed', 'message' => 'Invalid handler result']);
            $processedThisStep++;
        }

        $this->state['cursors'][$phase] = ['line' => $lineNo, 'done' => false];
        $stats = $this->state['stats'][$phase] ?? $this->emptyStats();
        $done = $file->eof() || (int)$stats['processed'] >= $limit;
        if ($done) {
            $this->state['cursors'][$phase]['done'] = true;
            return true;
        }

        return false;
    }

    private function processJsonArrayPhase(string $phase, callable $handler, string $filePath): bool {
        $cursor = $this->state['cursors'][$phase] ?? ['index' => 0, 'done' => false];
        if (!empty($cursor['done'])) {
            return true;
        }

        $raw = @file_get_contents($filePath);
        if (!is_string($raw) || trim($raw) === '') {
            $this->state['cursors'][$phase] = ['index' => 0, 'done' => true];
            return true;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->registerPhaseResult($phase, ['status' => 'failed', 'message' => 'Invalid JSON file: ' . json_last_error_msg()]);
            $this->state['cursors'][$phase] = ['index' => 0, 'done' => true];
            return true;
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $rows = $decoded['data'];
        } elseif (is_array($decoded) && isset($decoded[0]) && is_array($decoded[0])) {
            $rows = $decoded;
        } elseif (is_array($decoded)) {
            $rows = [$decoded];
        } else {
            $rows = [];
        }

        $index = max(0, (int)($cursor['index'] ?? 0));
        $processedThisStep = 0;
        $limit = $this->testMode ? $this->testModeLimit : PHP_INT_MAX;
        $total = count($rows);

        while ($index < $total) {
            if ($processedThisStep >= $this->chunkRows) {
                break;
            }
            $stats = $this->state['stats'][$phase] ?? $this->emptyStats();
            if ((int)$stats['processed'] >= $limit) {
                break;
            }

            $row = $rows[$index];
            $index++;
            if (!is_array($row)) {
                $this->registerPhaseResult($phase, ['status' => 'failed', 'message' => 'Invalid JSON row at index ' . $index]);
                $processedThisStep++;
                continue;
            }

            try {
                $result = $handler($row);
            } catch (\Throwable $e) {
                $result = ['status' => 'failed', 'message' => $e->getMessage()];
            }
            $this->registerPhaseResult($phase, is_array($result) ? $result : ['status' => 'failed', 'message' => 'Invalid handler result']);
            $processedThisStep++;
        }

        $done = $index >= $total;
        $stats = $this->state['stats'][$phase] ?? $this->emptyStats();
        if ((int)$stats['processed'] >= $limit) {
            $done = true;
        }
        $this->state['cursors'][$phase] = ['index' => $index, 'done' => $done];
        return $done;
    }
    private function importUserRow(array $row): array {
        $sourceId = $this->normalizeSourceId($this->rowValue($row, ['source_id', 'user_source_id', 'wp_user_id', 'id', 'user_id'], ''));
        if ($sourceId === '') {
            return $this->result('failed', 'User row has no source_id');
        }

        $email = strtolower($this->rowString($row, ['email', 'user_email'], ''));
        if ($email === '') {
            $email = 'imported_user_' . $sourceId . '@example.local';
        }

        $login = $this->rowString($row, ['login', 'user_login'], '');
        $name = $this->rowString($row, ['name', 'display_name', 'login', 'user_login'], $email);
        $existingUserId = $this->getMappedId('user', $sourceId);
        if ($existingUserId <= 0) {
            $existingUserId = (int)SafeMySQL::gi()->getOne(
                'SELECT user_id FROM ?n WHERE email = ?s LIMIT 1',
                Constants::USERS_TABLE,
                $email
            );
        }

        $legacyPasswordHash = $this->rowString($row, ['pwd', 'pwd_hash', 'password_hash'], '');
        $placeholderPasswordHash = $this->buildImportedUserPlaceholderPasswordHash($sourceId, $email);

        $userData = [
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            'active' => $this->normalizeUserActive($this->rowValue($row, ['active', 'status', 'user_status'], 2)),
            'user_role' => $this->normalizeUserRole($this->rowValue($row, ['user_role', 'role_id', 'role'], 4)),
            'subscribed' => $this->toBool($this->rowValue($row, ['subscribed', 'newsletter'], 1), true) ? 1 : 0,
            'deleted' => $this->toBool($this->rowValue($row, ['deleted', 'is_deleted'], 0), false) ? 1 : 0,
            'phone' => $this->rowString($row, ['phone', 'user_phone'], ''),
            'comment' => $this->rowString($row, ['comment', 'bio', 'description'], ''),
            'last_ip' => $this->rowString($row, ['last_ip', 'ip'], ''),
        ];

        if ($existingUserId > 0) {
            SafeMySQL::gi()->query('UPDATE ?n SET ?u WHERE user_id = ?i', Constants::USERS_TABLE, $userData, $existingUserId);
            $status = 'updated';
            $userId = $existingUserId;
        } else {
            $userData['pwd'] = $placeholderPasswordHash;
            SafeMySQL::gi()->query('INSERT INTO ?n SET ?u', Constants::USERS_TABLE, $userData);
            $userId = (int)SafeMySQL::gi()->insertId();
            if ($userId <= 0) {
                return $this->result('failed', 'Failed to create user for source_id=' . $sourceId);
            }
            $status = 'created';
        }

        $this->saveMappedId('user', $sourceId, $userId);

        $this->ensureImportedUserDataRow($userId);
        $this->applyImportedUserOptions($userId, $row, $sourceId, $login, $legacyPasswordHash);
        (new AuthService())->markUserRequiresPasswordSetup($userId, true, 'wp_migration');

        return $this->result($status);
    }

    private function buildImportedUserPlaceholderPasswordHash(string $sourceId, string $email): string {
        $seed = 'wp-import-user-' . $sourceId . '-' . $email;
        $hash = password_hash($seed, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            $hash = password_hash('wp-import-user-' . $sourceId, PASSWORD_DEFAULT);
        }
        return is_string($hash) && $hash !== '' ? $hash : sha1($seed);
    }

    private function ensureImportedUserDataRow(int $userId): void {
        $dataId = (int)SafeMySQL::gi()->getOne(
            'SELECT data_id FROM ?n WHERE user_id = ?i LIMIT 1',
            Constants::USERS_DATA_TABLE,
            $userId
        );
        if ($dataId > 0) {
            return;
        }

        $baseOptions = AuthService::decodeJsonPayload(Users::BASE_OPTIONS_USER);
        $encoded = json_encode($baseOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        SafeMySQL::gi()->query(
            'INSERT INTO ?n SET ?u',
            Constants::USERS_DATA_TABLE,
            ['user_id' => $userId, 'options' => is_string($encoded) ? $encoded : '{}']
        );
    }

    private function applyImportedUserOptions(int $userId, array $row, string $sourceId, string $login, string $legacyPasswordHash): void {
        $rawOptions = SafeMySQL::gi()->getOne(
            'SELECT options FROM ?n WHERE user_id = ?i LIMIT 1',
            Constants::USERS_DATA_TABLE,
            $userId
        );
        $currentOptions = AuthService::decodeJsonPayload($rawOptions);
        $baseOptions = AuthService::decodeJsonPayload(Users::BASE_OPTIONS_USER);
        $mergedOptions = $this->mergeImportOptions($baseOptions, $currentOptions);

        $mergedOptions['auth'] = $this->mergeImportOptions(
            is_array($mergedOptions['auth'] ?? null) ? $mergedOptions['auth'] : [],
            [
                'require_password_setup' => 1,
                'password_setup_reason' => 'wp_migration',
                'last_password_prompt_at' => date('c'),
            ]
        );

        $mergedOptions['migration'] = $this->mergeImportOptions(
            is_array($mergedOptions['migration'] ?? null) ? $mergedOptions['migration'] : [],
            [
                'wordpress' => [
                    'source_id' => $sourceId,
                    'login' => $login,
                    'legacy_password_hash_imported' => $legacyPasswordHash !== '' ? 1 : 0,
                    'imported_at' => date('c'),
                ],
            ]
        );

        $encoded = json_encode($mergedOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        SafeMySQL::gi()->query(
            'UPDATE ?n SET options = ?s, updated_at = NOW() WHERE user_id = ?i',
            Constants::USERS_DATA_TABLE,
            is_string($encoded) ? $encoded : '{}',
            $userId
        );
    }

    private function mergeImportOptions(array $base, array $patch): array {
        foreach ($patch as $key => $value) {
            if (is_array($value) && is_array($base[$key] ?? null)) {
                $base[$key] = $this->mergeImportOptions($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    private function importCategoryTypeRow(array $row): array {
        $sourceId = $this->normalizeSourceId($this->rowValue($row, ['source_id', 'type_source_id', 'taxonomy', 'id'], ''));
        if (!$this->isAllowedTypeSource($sourceId)) {
            return $this->result('skipped');
        }
        $name = $this->rowString($row, ['name', 'label', 'taxonomy'], '');
        if ($name === '') {
            return $this->result('failed', 'Category type row has empty name');
        }

        $configuredTypeId = $sourceId !== '' ? $this->getConfiguredMapLocalId('category_type', $sourceId) : 0;
        $existingTypeId = 0;
        if ($configuredTypeId > 0) {
            $existingTypeId = $configuredTypeId;
        } elseif ($sourceId !== '') {
            $existingTypeId = $this->getMappedId('category_type', $sourceId);
        }
        if ($existingTypeId <= 0) {
            $existingTypeId = (int)$this->objectModelCategoriesTypes->getIdCategoriesTypeByName($name, $this->languageCode);
        }

        $finalName = $name;
        $finalDescription = $this->rowString($row, ['description'], $name);
        if ($configuredTypeId > 0) {
            $currentType = $this->objectModelCategoriesTypes->getCategoriesTypeData($configuredTypeId, $this->languageCode);
            if (is_array($currentType)) {
                if (!empty($currentType['name'])) {
                    $finalName = (string)$currentType['name'];
                }
                if (isset($currentType['description']) && trim((string)$currentType['description']) !== '') {
                    $finalDescription = (string)$currentType['description'];
                }
            }
        }

        $typeData = [
            'type_id' => $existingTypeId > 0 ? $existingTypeId : 0,
            'name' => $finalName,
            'description' => $finalDescription,
            'parent_type_id' => 0,
        ];
        $typeSaveResult = OperationResult::fromLegacy(
            $this->objectModelCategoriesTypes->updateCategoriesTypeData($typeData, $this->languageCode),
            ['false_message' => 'Failed to import category type: ' . $name]
        );
        $typeId = $typeSaveResult->isSuccess() ? $typeSaveResult->getId(['type_id', 'id']) : 0;
        if ($typeId <= 0) {
            if ($existingTypeId > 0) {
                $typeId = $existingTypeId;
            } else {
                return $this->result('failed', $typeSaveResult->getMessage('Failed to import category type: ' . $name));
            }
        }

        if ($sourceId !== '') {
            $this->saveMappedId('category_type', $sourceId, (int)$typeId);
        }

        return $this->result($existingTypeId > 0 ? 'updated' : 'created');
    }
    private function applyCategoryTypeParentRow(array $row): array {
        $sourceId = $this->normalizeSourceId($this->rowValue($row, ['source_id', 'type_source_id', 'taxonomy', 'id'], ''));
        if ($sourceId === '') {
            return $this->result('skipped');
        }
        if (!$this->isAllowedTypeSource($sourceId)) {
            return $this->result('skipped');
        }

        $parentSourceId = $this->normalizeSourceId($this->rowValue($row, ['parent_source_id', 'parent_type_source_id'], ''));
        if ($parentSourceId === '') {
            return $this->result('skipped');
        }
        if (!$this->isAllowedTypeSource($parentSourceId)) {
            return $this->result('skipped');
        }

        $typeId = $this->getMappedId('category_type', $sourceId);
        $parentTypeId = $this->getMappedId('category_type', $parentSourceId);
        if ($typeId <= 0 || $parentTypeId <= 0) {
            return $this->result('skipped');
        }
        if ($typeId === $parentTypeId) {
            return $this->result('skipped');
        }

        $current = $this->objectModelCategoriesTypes->getCategoriesTypeData($typeId, $this->languageCode);
        if (!is_array($current) || empty($current['name'])) {
            return $this->result('failed', 'Category type not found by local ID=' . $typeId);
        }
        if ((int)($current['parent_type_id'] ?? 0) === $parentTypeId) {
            return $this->result('skipped');
        }

        $result = OperationResult::fromLegacy(
            $this->objectModelCategoriesTypes->updateCategoriesTypeData(
                [
                    'type_id' => $typeId,
                    'name' => (string)$current['name'],
                    'description' => (string)($current['description'] ?? $current['name']),
                    'parent_type_id' => $parentTypeId,
                ],
                $this->languageCode
            ),
            ['false_message' => 'Failed to update parent for category type ID=' . $typeId]
        );
        if ($result->isFailure()) {
            return $this->result('failed', $result->getMessage('Failed to update parent for category type ID=' . $typeId));
        }
        return $this->result('updated');
    }
    private function importPropertyTypeRow(array $row): array {
        $sourceId = $this->normalizeSourceId($this->rowValue($row, ['source_id', 'type_source_id', 'id'], ''));
        $name = $this->rowString($row, ['name', 'type_name', 'title'], '');

        [$resolvedTypeName, $typeNameCandidates, $fallbackTypeFields] = $this->resolveTypeDescriptor($sourceId, $name !== '' ? $name : 'String');
        if ($name === '') {
            $name = $resolvedTypeName;
        }
        if ($name === '') {
            return $this->result('failed', 'Property type row has empty name');
        }

        $existingTypeId = 0;
        if ($sourceId !== '') {
            $existingTypeId = $this->getExistingPropertyTypeId($sourceId, $typeNameCandidates, $this->toInt($this->rowValue($row, ['type_id', 'local_type_id'], 0), 0));
            if ($existingTypeId > 0) {
                $matchedName = (string)SafeMySQL::gi()->getOne(
                    'SELECT name FROM ?n WHERE type_id = ?i LIMIT 1',
                    Constants::PROPERTY_TYPES_TABLE,
                    $existingTypeId
                );
                if ($matchedName !== '') {
                    $name = $matchedName;
                }
            }
        }

        $rawTypeFields = $this->rowValue($row, ['fields', 'type_fields'], null);
        if (($rawTypeFields === null || $rawTypeFields === '' || $rawTypeFields === []) && !empty($fallbackTypeFields)) {
            $rawTypeFields = $fallbackTypeFields;
        }

        $typeData = [
            'type_id' => $existingTypeId > 0 ? $existingTypeId : 0,
            'name' => $name,
            'status' => $this->normalizeStatus($this->rowValue($row, ['status', 'type_status'], 'active')),
            'description' => $this->rowString($row, ['description', 'type_description'], $name),
            'fields' => $this->prepareJsonField($rawTypeFields ?? ['text'], ['text']),
        ];

        try {
            $typeSaveResult = OperationResult::fromLegacy(
                $this->objectModelProperties->updatePropertyTypeData($typeData, $this->languageCode),
                ['false_message' => 'Failed to import property type: ' . $name]
            );
        } catch (\Throwable $e) {
            $typeSaveResult = OperationResult::failure('Failed to import property type: ' . $name, 'import_property_type_exception', ['exception' => $e->getMessage()]);
        }
        $typeId = $typeSaveResult->isSuccess() ? $typeSaveResult->getId(['type_id', 'id']) : 0;
        if ($typeId <= 0) {
            $typeId = $this->getExistingPropertyTypeId($sourceId, $typeNameCandidates, $this->toInt($this->rowValue($row, ['type_id', 'local_type_id'], 0), 0));
            if ($typeId <= 0) {
                return $this->result('failed', $typeSaveResult->getMessage('Failed to import property type: ' . $name));
            }
        }

        if ($sourceId !== '') {
            $this->saveMappedId('property_type', $sourceId, (int)$typeId);
        }

        return $this->result($existingTypeId > 0 ? 'updated' : 'created');
    }
    private function importPropertySetRow(array $row): array {
        $sourceId = $this->normalizeSourceId($this->rowValue($row, ['source_id', 'set_source_id', 'id', 'set_id'], ''));
        if ($sourceId !== '' && !$this->isAllowedTypeSource($sourceId)) {
            return $this->result('skipped');
        }
        $name = $this->rowString($row, ['name', 'title'], '');
        if ($name === '') {
            return $this->result('failed', 'Property set row has empty name');
        }

        $configuredSetId = $sourceId !== '' ? $this->getConfiguredMapLocalId('property_set', $sourceId) : 0;
        $existingSetId = 0;
        if ($configuredSetId > 0) {
            $existingSetId = $configuredSetId;
        } elseif ($sourceId !== '') {
            $existingSetId = $this->getMappedId('property_set', $sourceId);
        }
        if ($existingSetId <= 0) {
            $existingSetId = (int)SafeMySQL::gi()->getOne(
                'SELECT set_id FROM ?n WHERE name = ?s AND language_code = ?s LIMIT 1',
                Constants::PROPERTY_SETS_TABLE,
                $name,
                $this->languageCode
            );
        }

        $finalName = $name;
        $finalDescription = $this->rowString($row, ['description'], '');
        if ($configuredSetId > 0) {
            $currentSet = SafeMySQL::gi()->getRow(
                'SELECT `name`, `description` FROM ?n WHERE set_id = ?i AND language_code = ?s LIMIT 1',
                Constants::PROPERTY_SETS_TABLE,
                $configuredSetId,
                $this->languageCode
            );
            if (is_array($currentSet)) {
                if (!empty($currentSet['name'])) {
                    $finalName = (string)$currentSet['name'];
                }
                if (isset($currentSet['description'])) {
                    $finalDescription = (string)$currentSet['description'];
                }
            }
        }

        $setData = [
            'set_id' => $existingSetId > 0 ? $existingSetId : 0,
            'name' => $finalName,
            'description' => $finalDescription,
        ];
        $setSaveResult = OperationResult::fromLegacy(
            $this->objectModelProperties->updatePropertySetData($setData, $this->languageCode),
            ['false_message' => 'Property set import failed']
        );
        $setId = $setSaveResult->isSuccess() ? $setSaveResult->getId(['set_id', 'id']) : 0;
        if ($setId <= 0) {
            if ($existingSetId > 0) {
                $setId = $existingSetId;
            } else {
                return $this->result('failed', $setSaveResult->getMessage('Failed to import property set: ' . $name));
            }
        }

        if ($sourceId !== '') {
            $this->saveMappedId('property_set', $sourceId, (int)$setId);
        }

        return $this->result($existingSetId > 0 ? 'updated' : 'created');
    }
    private function importPropertyRow(array $row): array {
        $sourceId = $this->normalizeSourceId($this->rowValue($row, ['source_id', 'property_source_id', 'id', 'property_id'], ''));
        if ($sourceId !== '' && $this->shouldSkipByCompositeWhitelist($sourceId)) {
            return $this->result('skipped');
        }
        if ($sourceId !== '' && $this->isExcludedPropertySourceId($sourceId)) {
            return $this->result('skipped');
        }
        if ($sourceId !== '' && $this->isCompositeMemberSourceId($sourceId)) {
            $compositeResult = $this->ensureCompositePropertiesForMemberSourceId($sourceId);
            $compositeStatus = strtolower(trim((string)($compositeResult['status'] ?? '')));
            if ($compositeStatus === 'failed') {
                return $compositeResult;
            }
            if ($compositeStatus === 'created' || $compositeStatus === 'updated') {
                return $this->result('skipped');
            }
        }

        $name = $this->rowString($row, ['name', 'key'], '');
        if ($name === '') {
            return $this->result('failed', 'Property row has empty name');
        }
        if (!$this->isAllowedMetaKey($name)) {
            return $this->result('skipped');
        }

        $typeId = $this->ensurePropertyTypeId($row);
        if ($typeId <= 0) {
            return $this->result('failed', 'Cannot resolve property type for property=' . $name);
        }

        $existingPropertyId = 0;
        if ($sourceId !== '') {
            $existingPropertyId = $this->getMappedId('property', $sourceId);
        }
        if ($existingPropertyId <= 0) {
            $existingPropertyId = (int)SafeMySQL::gi()->getOne(
                'SELECT property_id FROM ?n WHERE name = ?s AND type_id = ?i AND language_code = ?s LIMIT 1',
                Constants::PROPERTIES_TABLE,
                $name,
                $typeId,
                $this->languageCode
            );
        }

        $targetEntityType = $this->normalizeEntityType($this->rowValue($row, ['entity_type', 'target_entity'], 'all'));
        if ($existingPropertyId > 0) {
            $currentEntityType = strtolower(trim((string)SafeMySQL::gi()->getOne(
                'SELECT entity_type FROM ?n WHERE property_id = ?i LIMIT 1',
                Constants::PROPERTIES_TABLE,
                $existingPropertyId
            )));
            if ($currentEntityType === '') {
                $currentEntityType = 'all';
            }
            if ($targetEntityType === '') {
                $targetEntityType = $currentEntityType;
            }
            if ($currentEntityType !== $targetEntityType) {
                // Same property key can appear in both termmeta and postmeta.
                // In this case property must be universal, otherwise one branch of values is rejected.
                $targetEntityType = 'all';
            }
        }

        $propertyData = [
            'property_id' => $existingPropertyId > 0 ? $existingPropertyId : 0,
            'type_id' => $typeId,
            'name' => $name,
            'status' => $this->normalizeStatus($this->rowValue($row, ['status'], 'active')),
            'sort' => max(0, $this->toInt($this->rowValue($row, ['sort', 'order'], 100), 100)),
            'default_values' => $this->prepareJsonField($this->buildPropertyDefaults($row), []),
            'is_multiple' => $this->toBool($this->rowValue($row, ['is_multiple', 'multiple'], 0), false) ? 1 : 0,
            'is_required' => $this->toBool($this->rowValue($row, ['is_required', 'required'], 0), false) ? 1 : 0,
            'description' => $this->rowString($row, ['description'], ''),
            'entity_type' => $targetEntityType,
        ];

        $propertySaveResult = OperationResult::fromLegacy(
            $this->objectModelProperties->updatePropertyData($propertyData, $this->languageCode),
            ['false_message' => 'Failed to import property: ' . $name]
        );
        $propertyId = $propertySaveResult->isSuccess() ? $propertySaveResult->getId(['property_id', 'id']) : 0;
        if ($propertyId <= 0) {
            if ($existingPropertyId > 0) {
                $propertyId = $existingPropertyId;
            } else {
                return $this->result('failed', $propertySaveResult->getMessage('Failed to import property: ' . $name));
            }
        }

        if ($sourceId !== '') {
            $this->saveMappedId('property', $sourceId, (int)$propertyId);
        }

        return $this->result($existingPropertyId > 0 ? 'updated' : 'created');
    }
    private function importTypeSetLinkRow(array $row): array {
        $typeSourceId = $this->normalizeSourceId($this->rowValue($row, ['type_source_id', 'category_type_source_id', 'source_type_id'], ''));
        $setSourceId = $this->normalizeSourceId($this->rowValue($row, ['set_source_id', 'property_set_source_id', 'source_set_id'], ''));
        if ($typeSourceId !== '' && !$this->isAllowedTypeSource($typeSourceId)) {
            return $this->result('skipped');
        }
        if ($setSourceId !== '' && !$this->isAllowedTypeSource($setSourceId)) {
            return $this->result('skipped');
        }

        $typeId = $this->getMappedOrLocal('category_type', $typeSourceId, $this->rowValue($row, ['type_id', 'category_type_id'], 0));
        $setId = $this->getMappedOrLocal('property_set', $setSourceId, $this->rowValue($row, ['set_id', 'property_set_id'], 0));
        if ($typeId <= 0 || $setId <= 0) {
            return $this->result('skipped');
        }

        $exists = (int)SafeMySQL::gi()->getOne(
            'SELECT 1 FROM ?n WHERE type_id = ?i AND set_id = ?i LIMIT 1',
            Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE,
            $typeId,
            $setId
        );
        if ($exists > 0) {
            return $this->result('skipped');
        }

        SafeMySQL::gi()->query(
            'INSERT INTO ?n SET ?u',
            Constants::CATEGORY_TYPE_TO_PROPERTY_SET_TABLE,
            ['type_id' => $typeId, 'set_id' => $setId]
        );
        return $this->result('created');
    }
    private function importSetPropertyLinkRow(array $row): array {
        $setSourceId = $this->normalizeSourceId($this->rowValue($row, ['set_source_id', 'property_set_source_id', 'source_set_id'], ''));
        $propertySourceId = $this->normalizeSourceId($this->rowValue($row, ['property_source_id', 'source_property_id'], ''));
        if ($setSourceId !== '' && !$this->isAllowedTypeSource($setSourceId)) {
            return $this->result('skipped');
        }
        if ($propertySourceId !== '' && $this->shouldSkipByCompositeWhitelist($propertySourceId)) {
            return $this->result('skipped');
        }
        if ($propertySourceId !== '' && $this->isExcludedPropertySourceId($propertySourceId)) {
            return $this->result('skipped');
        }
        if ($propertySourceId !== '' && $this->hasCompositeDefinitionForSet($propertySourceId, $setSourceId)) {
            return $this->result('skipped');
        }
        $propertyName = $this->extractMetaKeyFromSourceId($propertySourceId);
        $propertyName = $this->normalizeSourceId($propertyName);
        if ($propertyName !== '' && !$this->isAllowedMetaKey($propertyName)) {
            return $this->result('skipped');
        }

        $setId = $this->getMappedOrLocal('property_set', $setSourceId, $this->rowValue($row, ['set_id', 'property_set_id'], 0));
        $propertyId = $this->getMappedOrLocal('property', $propertySourceId, $this->rowValue($row, ['property_id'], 0));
        if ($setId <= 0 || $propertyId <= 0) {
            return $this->result('skipped');
        }

        $exists = (int)SafeMySQL::gi()->getOne(
            'SELECT 1 FROM ?n WHERE set_id = ?i AND property_id = ?i LIMIT 1',
            Constants::PROPERTY_SET_TO_PROPERTIES_TABLE,
            $setId,
            $propertyId
        );
        if ($exists > 0) {
            return $this->result('skipped');
        }

        SafeMySQL::gi()->query(
            'INSERT INTO ?n SET ?u',
            Constants::PROPERTY_SET_TO_PROPERTIES_TABLE,
            ['set_id' => $setId, 'property_id' => $propertyId]
        );
        return $this->result('created');
    }
    private function importCategoryRow(array $row): array {
        $sourceId = $this->normalizeSourceId($this->rowValue($row, ['source_id', 'category_source_id', 'term_taxonomy_id', 'id'], ''));
        if ($sourceId === '') {
            return $this->result('failed', 'Category row has no source_id');
        }

        $title = $this->rowString($row, ['title', 'name'], '');
        if ($title === '') {
            return $this->result('failed', 'Category row has empty title');
        }

        $typeSourceId = $this->normalizeSourceId($this->rowValue($row, ['type_source_id', 'category_type_source_id', 'taxonomy'], ''));
        if ($typeSourceId !== '' && !$this->isAllowedTypeSource($typeSourceId)) {
            return $this->result('skipped');
        }
        $typeId = $this->getMappedOrLocal('category_type', $typeSourceId, $this->rowValue($row, ['type_id', 'category_type_id'], 0));
        if ($typeId <= 0) {
            return $this->result('skipped');
        }

        $existingCategoryId = $this->getMappedId('category', $sourceId);
        if ($existingCategoryId <= 0) {
            $existingCategoryId = (int)SafeMySQL::gi()->getOne(
                'SELECT category_id FROM ?n WHERE title = ?s AND type_id = ?i AND language_code = ?s LIMIT 1',
                Constants::CATEGORIES_TABLE,
                $title,
                $typeId,
                $this->languageCode
            );
        }

        $categoryData = [
            'category_id' => $existingCategoryId > 0 ? $existingCategoryId : 0,
            'type_id' => $typeId,
            'title' => $title,
            'slug' => $this->rowString($row, ['slug'], ''),
            'route_path' => $this->preserveSourcePaths ? $this->resolveImportedRoutePath($row) : '',
            'short_description' => $this->rowString($row, ['short_description', 'excerpt'], ''),
            'description' => $this->prepareImportedRichText(
                $this->rowString($row, ['description'], $title)
            ),
            'status' => $this->normalizeStatus($this->rowValue($row, ['status'], 'active')),
            'parent_id' => 0,
        ];

        $categorySaveResult = OperationResult::fromLegacy(
            $this->objectModelCategories->updateCategoryData($categoryData, $this->languageCode),
            ['false_message' => 'Category import failed']
        );
        $categoryId = $categorySaveResult->isSuccess() ? $categorySaveResult->getId(['category_id', 'id']) : 0;
        if ($categoryId <= 0) {
            return $this->result('failed', $categorySaveResult->getMessage('Failed to import category source=' . $sourceId));
        }

        $this->saveMappedId('category', $sourceId, (int)$categoryId);
        $this->saveImportedSourcePath('category', $row, (int)$categoryId);
        return $this->result($existingCategoryId > 0 ? 'updated' : 'created');
    }
    private function applyCategoryParentRow(array $row): array {
        $sourceId = $this->normalizeSourceId($this->rowValue($row, ['source_id', 'category_source_id', 'term_taxonomy_id', 'id'], ''));
        if ($sourceId === '') {
            return $this->result('skipped');
        }

        $parentSourceId = $this->normalizeSourceId($this->rowValue($row, ['parent_source_id', 'parent_category_source_id'], ''));
        if ($parentSourceId === '') {
            return $this->result('skipped');
        }

        $categoryId = $this->getMappedOrLocal('category', $sourceId, $this->rowValue($row, ['category_id'], 0));
        $parentId = $this->getMappedOrLocal('category', $parentSourceId, $this->rowValue($row, ['parent_id'], 0));
        if ($categoryId <= 0 || $parentId <= 0) {
            return $this->result('skipped');
        }
        if ($categoryId === $parentId) {
            return $this->result('skipped');
        }

        $current = $this->objectModelCategories->getCategoryData($categoryId, $this->languageCode);
        if (!is_array($current) || empty($current['title'])) {
            return $this->result('failed', 'Category not found by local ID=' . $categoryId);
        }
        if ((int)($current['parent_id'] ?? 0) === $parentId) {
            return $this->result('skipped');
        }

        $categoryData = [
            'category_id' => $categoryId,
            'type_id' => (int)$current['type_id'],
            'title' => (string)$current['title'],
            'short_description' => (string)($current['short_description'] ?? ''),
            'description' => (string)($current['description'] ?? $current['title']),
            'status' => $this->normalizeStatus($current['status'] ?? 'active'),
            'parent_id' => $parentId,
        ];

        $result = OperationResult::fromLegacy(
            $this->objectModelCategories->updateCategoryData($categoryData, $this->languageCode),
            ['false_message' => 'Category parent update failed']
        );
        if ($result->isFailure()) {
            return $this->result('failed', $result->getMessage('Category parent update failed'));
        }
        if ($result->getId(['category_id', 'id']) <= 0) {
            return $this->result('failed', 'Failed to update category parent for ID=' . $categoryId);
        }
        return $this->result('updated');
    }
    private function importPageRow(array $row): array {
        $sourceId = $this->normalizeSourceId($this->rowValue($row, ['source_id', 'page_source_id', 'post_id', 'id'], ''));
        if ($sourceId === '') {
            return $this->result('failed', 'Page row has no source_id');
        }

        $postType = strtolower(trim($this->rowString($row, ['post_type', 'source_post_type'], '')));
        if ($postType === '') {
            $categorySourceIdForType = $this->normalizeSourceId($this->rowValue($row, ['category_source_id', 'term_source_id'], ''));
            if (str_starts_with($categorySourceIdForType, 'ptcat:')) {
                $postType = strtolower(trim((string)substr($categorySourceIdForType, strlen('ptcat:'))));
            }
        }
        if (!$this->isAllowedPostType($postType)) {
            return $this->result('skipped');
        }

        $title = $this->rowString($row, ['title', 'name', 'post_title'], '');
        if ($title === '') {
            return $this->result('failed', 'Page row has empty title');
        }

        $categorySourceId = $this->normalizeSourceId($this->rowValue($row, ['category_source_id', 'term_source_id'], ''));
        $categoryId = $this->getMappedOrLocal('category', $categorySourceId, $this->rowValue($row, ['category_id'], 0));
        if ($categoryId <= 0) {
            return $this->result('skipped');
        }

        $existingPageId = $this->getMappedId('page', $sourceId);
        if ($existingPageId <= 0) {
            $existingPageId = (int)SafeMySQL::gi()->getOne(
                'SELECT page_id FROM ?n WHERE title = ?s AND category_id = ?i AND language_code = ?s LIMIT 1',
                Constants::PAGES_TABLE,
                $title,
                $categoryId,
                $this->languageCode
            );
        }

        $pageData = [
            'page_id' => $existingPageId > 0 ? $existingPageId : 0,
            'category_id' => $categoryId,
            'parent_page_id' => 0,
            'status' => $this->normalizeStatus($this->rowValue($row, ['status'], 'active')),
            'title' => $title,
            'slug' => $this->rowString($row, ['slug'], ''),
            'route_path' => $this->preserveSourcePaths ? $this->resolveImportedRoutePath($row) : '',
            'short_description' => $this->rowString($row, ['short_description', 'excerpt'], ''),
            'description' => $this->prepareImportedRichText(
                $this->rowString($row, ['description', 'content'], '')
            ),
        ];

        $pageSaveResult = OperationResult::fromLegacy(
            $this->objectModelPages->updatePageData($pageData, $this->languageCode),
            ['false_message' => 'Page import failed']
        );
        $pageId = $pageSaveResult->isSuccess() ? $pageSaveResult->getId(['page_id', 'id']) : 0;
        if ($pageId <= 0) {
            return $this->result('failed', $pageSaveResult->getMessage('Failed to import page source=' . $sourceId));
        }

        $ownerUserId = $this->resolveImportedPageOwnerUserId($row);
        if ($ownerUserId > 0) {
            $this->syncImportedPageUserLink($pageId, $ownerUserId, 'owner');
        }

        $this->saveMappedId('page', $sourceId, (int)$pageId);
        $this->saveImportedSourcePath('page', $row, (int)$pageId);
        return $this->result($existingPageId > 0 ? 'updated' : 'created');
    }
    private function applyPageParentRow(array $row): array {
        $sourceId = $this->normalizeSourceId($this->rowValue($row, ['source_id', 'page_source_id', 'post_id', 'id'], ''));
        if ($sourceId === '') {
            return $this->result('skipped');
        }

        $parentSourceId = $this->normalizeSourceId($this->rowValue($row, ['parent_source_id', 'parent_page_source_id', 'post_parent_source_id'], ''));
        if ($parentSourceId === '') {
            return $this->result('skipped');
        }

        $pageId = $this->getMappedOrLocal('page', $sourceId, $this->rowValue($row, ['page_id'], 0));
        $parentPageId = $this->getMappedOrLocal('page', $parentSourceId, $this->rowValue($row, ['parent_page_id'], 0));
        if ($pageId <= 0 || $parentPageId <= 0) {
            return $this->result('skipped');
        }
        if ($pageId === $parentPageId) {
            return $this->result('skipped');
        }

        $current = $this->objectModelPages->getPageData($pageId, $this->languageCode);
        if (!is_array($current) || empty($current['title'])) {
            return $this->result('failed', 'Page not found by local ID=' . $pageId);
        }
        if ((int)($current['parent_page_id'] ?? 0) === $parentPageId) {
            return $this->result('skipped');
        }

        $pageData = [
            'page_id' => $pageId,
            'category_id' => (int)$current['category_id'],
            'parent_page_id' => $parentPageId,
            'status' => $this->normalizeStatus($current['status'] ?? 'active'),
            'title' => (string)$current['title'],
            'short_description' => (string)($current['short_description'] ?? ''),
            'description' => (string)($current['description'] ?? ''),
        ];

        $result = OperationResult::fromLegacy(
            $this->objectModelPages->updatePageData($pageData, $this->languageCode),
            ['false_message' => 'Page parent update failed']
        );
        if ($result->isFailure()) {
            return $this->result('failed', $result->getMessage('Page parent update failed'));
        }
        if ($result->getId(['page_id', 'id']) <= 0) {
            return $this->result('failed', 'Failed to update parent for page ID=' . $pageId);
        }
        return $this->result('updated');
    }

    private function resolveImportedPageOwnerUserId(array $row): int {
        $ownerSourceId = $this->normalizeSourceId($this->rowValue(
            $row,
            ['owner_user_source_id', 'author_user_source_id', 'user_source_id'],
            ''
        ));
        if ($ownerSourceId !== '') {
            $mappedUserId = $this->getMappedOrLocal('user', $ownerSourceId, $this->rowValue($row, ['owner_user_id', 'author_user_id'], 0));
            if ($mappedUserId > 0) {
                return $mappedUserId;
            }
        }

        $ownerEmail = strtolower($this->rowString($row, ['owner_user_email', 'author_user_email'], ''));
        if ($ownerEmail !== '') {
            $userId = (int)SafeMySQL::gi()->getOne(
                'SELECT user_id FROM ?n WHERE email = ?s LIMIT 1',
                Constants::USERS_TABLE,
                $ownerEmail
            );
            if ($userId > 0) {
                return $userId;
            }
        }

        $ownerName = trim($this->rowString($row, ['owner_user_name', 'author_user_name'], ''));
        if ($ownerName !== '') {
            $rows = SafeMySQL::gi()->getAll(
                'SELECT user_id FROM ?n WHERE name = ?s AND deleted = 0 LIMIT 2',
                Constants::USERS_TABLE,
                $ownerName
            );
            if (is_array($rows) && count($rows) === 1) {
                return (int)($rows[0]['user_id'] ?? 0);
            }
        }

        return 0;
    }

    private function syncImportedPageUserLink(int $pageId, int $userId, string $relationType = 'owner'): void {
        if ($pageId <= 0 || $userId <= 0) {
            return;
        }

        $relationType = trim($relationType);
        if ($relationType === '') {
            $relationType = 'owner';
        }

        SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE page_id = ?i AND relation_type = ?s AND user_id != ?i',
            Constants::PAGE_USER_LINKS_TABLE,
            $pageId,
            $relationType,
            $userId
        );

        SafeMySQL::gi()->query(
            'INSERT INTO ?n (`page_id`, `user_id`, `relation_type`) VALUES (?i, ?i, ?s)
             ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP',
            Constants::PAGE_USER_LINKS_TABLE,
            $pageId,
            $userId,
            $relationType
        );
    }

    private function importPropertyValueRow(array $row): array {
        $entityType = $this->normalizeEntityType($this->rowValue($row, ['entity_type', 'target_entity'], ''));
        if ($entityType !== 'category' && $entityType !== 'page') {
            return $this->result('failed', 'Invalid entity_type for property value');
        }

        $setSourceId = $this->normalizeSourceId($this->rowValue($row, ['set_source_id', 'source_set_id'], ''));
        if ($setSourceId !== '' && !$this->isAllowedTypeSource($setSourceId)) {
            return $this->result('skipped');
        }

        $propertySourceId = $this->normalizeSourceId($this->rowValue($row, ['property_source_id', 'source_property_id'], ''));
        if ($propertySourceId !== '' && $this->shouldSkipByCompositeWhitelist($propertySourceId)) {
            return $this->result('skipped');
        }
        if ($propertySourceId !== '' && $this->isExcludedPropertySourceId($propertySourceId)) {
            return $this->result('skipped');
        }
        $compositeResolution = $this->resolveCompositeDefinitionForValueRow($propertySourceId, $setSourceId, $entityType);
        if (is_array($compositeResolution) && is_array($compositeResolution['definition'] ?? null)) {
            return $this->importCompositePropertyValueRow(
                $row,
                $propertySourceId,
                $setSourceId,
                $entityType,
                $compositeResolution['definition'],
                is_array($compositeResolution['match'] ?? null) ? $compositeResolution['match'] : []
            );
        }

        $propertyName = $this->extractMetaKeyFromSourceId($propertySourceId);
        if ($propertyName !== '' && !$this->isAllowedMetaKey($propertyName)) {
            return $this->result('skipped');
        }

        $entitySourceId = $this->normalizeSourceId($this->rowValue($row, ['entity_source_id', 'source_entity_id'], ''));
        $entityId = $this->getMappedOrLocal($entityType, $entitySourceId, $this->rowValue($row, ['entity_id'], 0));
        if ($entityId <= 0) {
            return $this->result('skipped');
        }

        $payloadValues = $this->rowValue($row, ['property_values', 'value', 'values', 'fields'], null);
        $ownerLinkStatus = $this->syncImportedPageOwnerFromPropertyRow(
            $entityType,
            $entityId,
            $propertySourceId,
            $payloadValues
        );
        if ($ownerLinkStatus === 'linked_only') {
            return $this->result('updated');
        }

        $propertyId = $this->getMappedOrLocal('property', $propertySourceId, $this->rowValue($row, ['property_id'], 0));
        if ($propertyId <= 0) {
            return $this->result('skipped');
        }

        $setId = $this->getMappedOrLocal('property_set', $setSourceId, $this->rowValue($row, ['set_id'], 0));
        if ($setId <= 0) {
            return $this->result('skipped');
        }

        $payloadValues = $this->normalizePropertyValuesPayload($propertyId, $payloadValues);
        if (empty($payloadValues)) {
            return $this->result('failed', 'Invalid property_values payload for entity=' . $entitySourceId . ', property=' . $propertySourceId);
        }

        $existingValueRow = $this->getCachedPropertyValueRow(
            $entityType,
            $entityId,
            $propertyId,
            $setId
        );
        $existingValueId = is_array($existingValueRow) ? (int)($existingValueRow['value_id'] ?? 0) : 0;
        $existingPayloadJson = is_array($existingValueRow) ? trim((string)($existingValueRow['property_values'] ?? '')) : '';
        $incomingPayloadJson = $this->prepareJsonField($payloadValues, []);
        if ($existingValueId > 0 && $existingPayloadJson !== '' && $existingPayloadJson === $incomingPayloadJson) {
            return $this->result('skipped');
        }

        $propertyData = [
            'entity_id' => $entityId,
            'property_id' => $propertyId,
            'entity_type' => $entityType,
            'set_id' => $setId,
            'property_values' => $payloadValues,
        ];
        if ($existingValueId > 0) {
            $propertyData['value_id'] = $existingValueId;
        }

        $valueSaveResult = OperationResult::fromLegacy(
            $this->objectModelProperties->updatePropertiesValueEntities($propertyData, $this->languageCode),
            ['false_message' => 'Failed to import property value for entity=' . $entityId]
        );
        if ($valueSaveResult->isFailure()) {
            return $this->result('failed', $valueSaveResult->getMessage('Failed to import property value for entity=' . $entityId));
        }
        $resolvedValueId = $existingValueId > 0 ? $existingValueId : $valueSaveResult->getId(['value_id', 'id']);
        $this->setCachedPropertyValueRow(
            $entityType,
            $entityId,
            $propertyId,
            $setId,
            [
                'value_id' => max(0, (int)$resolvedValueId),
                'property_values' => $incomingPayloadJson,
            ]
        );
        return $this->result($existingValueId > 0 ? 'updated' : 'created');
    }

    private function syncImportedPageOwnerFromPropertyRow(
        string $entityType,
        int $entityId,
        string $propertySourceId,
        mixed $payloadValues
    ): string {
        if ($entityType !== 'page' || $entityId <= 0) {
            return 'noop';
        }

        $propertySourceId = strtolower(trim($propertySourceId));
        if ($propertySourceId === '') {
            return 'noop';
        }

        $existingOwnerId = (int)SafeMySQL::gi()->getOne(
            'SELECT user_id FROM ?n WHERE page_id = ?i AND relation_type = ?s LIMIT 1',
            Constants::PAGE_USER_LINKS_TABLE,
            $entityId,
            'owner'
        );
        if ($existingOwnerId > 0) {
            return 'noop';
        }

        $normalizedPayload = [];
        if (is_array($payloadValues)) {
            $normalizedPayload = $payloadValues;
        }

        if ($propertySourceId === 'postmeta:email') {
            $email = $this->extractScalarValueFromImportPayload($normalizedPayload);
            $email = strtolower(trim((string)$email));
            if ($email === '') {
                return 'noop';
            }

            $userId = (int)SafeMySQL::gi()->getOne(
                'SELECT user_id FROM ?n WHERE email = ?s AND deleted = 0 LIMIT 1',
                Constants::USERS_TABLE,
                $email
            );
            if ($userId > 0) {
                $this->syncImportedPageUserLink($entityId, $userId, 'owner');
                return 'linked_only';
            }

            return 'noop';
        }

        if ($propertySourceId === 'postmeta:user_name_master_object') {
            $ownerName = trim((string)$this->extractScalarValueFromImportPayload($normalizedPayload));
            if ($ownerName === '') {
                return 'noop';
            }

            $rows = SafeMySQL::gi()->getAll(
                'SELECT user_id FROM ?n WHERE name = ?s AND deleted = 0 LIMIT 2',
                Constants::USERS_TABLE,
                $ownerName
            );
            if (is_array($rows) && count($rows) === 1) {
                $userId = (int)($rows[0]['user_id'] ?? 0);
                if ($userId > 0) {
                    $this->syncImportedPageUserLink($entityId, $userId, 'owner');
                    return 'linked_only';
                }
            }
        }

        return 'noop';
    }

    private function extractScalarValueFromImportPayload(array $payloadValues): mixed {
        if ($payloadValues === []) {
            return null;
        }

        $first = $payloadValues[0] ?? null;
        if (!is_array($first)) {
            return null;
        }

        return $first['value'] ?? null;
    }

    private function ensurePropertyTypeId(array $row): int {
        $typeSourceId = $this->normalizeSourceId($this->rowValue($row, ['type_source_id', 'property_type_source_id'], ''));
        $typeName = $this->rowString($row, ['type_name', 'property_type_name'], 'String');
        if ($typeName === '') {
            $typeName = 'String';
        }

        [$resolvedTypeName, $typeNameCandidates, $fallbackTypeFields] = $this->resolveTypeDescriptor($typeSourceId, $typeName);
        $typeName = $resolvedTypeName;

        $existingTypeId = $this->getExistingPropertyTypeId($typeSourceId, $typeNameCandidates, 0);
        if ($existingTypeId > 0) {
            $matchedName = (string)SafeMySQL::gi()->getOne(
                'SELECT name FROM ?n WHERE type_id = ?i LIMIT 1',
                Constants::PROPERTY_TYPES_TABLE,
                $existingTypeId
            );
            if ($matchedName !== '') {
                $typeName = $matchedName;
            }
        }

        $typeFieldsRaw = $this->rowValue($row, ['type_fields', 'fields'], null);
        if (($typeFieldsRaw === null || $typeFieldsRaw === '' || $typeFieldsRaw === []) && !empty($fallbackTypeFields)) {
            $typeFieldsRaw = $fallbackTypeFields;
        }

        $typeData = [
            'type_id' => $existingTypeId > 0 ? $existingTypeId : 0,
            'name' => $typeName,
            'status' => $this->normalizeStatus($this->rowValue($row, ['type_status'], 'active')),
            'description' => $this->rowString($row, ['type_description'], $typeName),
            'fields' => $this->prepareJsonField($typeFieldsRaw ?? ['text'], ['text']),
        ];
        try {
            $typeSaveResult = OperationResult::fromLegacy(
                $this->objectModelProperties->updatePropertyTypeData($typeData, $this->languageCode),
                ['false_message' => 'Failed to resolve property type']
            );
        } catch (\Throwable $e) {
            $typeSaveResult = OperationResult::failure('Failed to resolve property type', 'import_property_type_exception', ['exception' => $e->getMessage()]);
        }
        $typeId = $typeSaveResult->isSuccess() ? $typeSaveResult->getId(['type_id', 'id']) : 0;
        if ($typeId <= 0) {
            $typeId = $this->getExistingPropertyTypeId($typeSourceId, $typeNameCandidates, 0);
            if ($typeId <= 0) {
                return 0;
            }
        }

        if ($typeSourceId !== '') {
            $this->saveMappedId('property_type', $typeSourceId, (int)$typeId);
        }
        return (int)$typeId;
    }

    private function getExistingPropertyTypeId(string $sourceId, array $typeNameCandidates, int $rowTypeId = 0): int {
        if ($sourceId !== '') {
            $mappedId = $this->getMappedId('property_type', $sourceId);
            if ($mappedId > 0) {
                $exists = (int)SafeMySQL::gi()->getOne(
                    'SELECT type_id FROM ?n WHERE type_id = ?i LIMIT 1',
                    Constants::PROPERTY_TYPES_TABLE,
                    $mappedId
                );
                if ($exists > 0) {
                    return $exists;
                }
            }
        }

        if ($rowTypeId > 0) {
            $existsById = (int)SafeMySQL::gi()->getOne(
                'SELECT type_id FROM ?n WHERE type_id = ?i LIMIT 1',
                Constants::PROPERTY_TYPES_TABLE,
                $rowTypeId
            );
            if ($existsById > 0) {
                return $existsById;
            }
        }

        foreach ($typeNameCandidates as $candidateName) {
            $candidateName = trim((string)$candidateName);
            if ($candidateName === '') {
                continue;
            }
            $existingTypeId = (int)SafeMySQL::gi()->getOne(
                'SELECT type_id FROM ?n WHERE name = ?s AND language_code = ?s LIMIT 1',
                Constants::PROPERTY_TYPES_TABLE,
                $candidateName,
                $this->languageCode
            );
            if ($existingTypeId > 0) {
                if ($sourceId !== '') {
                    $this->saveMappedId('property_type', $sourceId, $existingTypeId);
                }
                return $existingTypeId;
            }
        }

        return 0;
    }

    /**
     * Returns normalized property type name candidates and fallback fields.
     * This helps reuse existing local property types for standard WP meta types.
     *
     * @return array{string, array<int, string>, array<int, string>}
     */
    private function resolveTypeDescriptor(string $typeSourceId, string $typeName): array {
        $source = strtolower(trim($typeSourceId));
        $candidates = [];
        $fields = ['text'];

        if ($source === 'wp_type:number') {
            $fields = ['number'];
            $candidates = ['Р§РёСЃР»Рѕ', 'Number', 'Integer', 'Float'];
        } elseif ($source === 'wp_type:date') {
            $fields = ['date'];
            $candidates = ['Р”Р°С‚Р°', 'Date', 'РРЅС‚РµСЂРІР°Р» РґР°С‚'];
        } elseif ($source === 'wp_type:boolean') {
            $fields = ['checkbox'];
            $candidates = ['Р¤Р»Р°Рі', 'Boolean', 'Checkbox'];
        } elseif ($source === 'wp_type:image') {
            $fields = ['image'];
            $candidates = ['РљР°СЂС‚РёРЅРєР°', 'Image'];
        } elseif ($source === 'wp_type:file') {
            $fields = ['file'];
            $candidates = ['Р¤Р°Р№Р»', 'File'];
        } else {
            $fields = ['text'];
            $candidates = ['РЎС‚СЂРѕРєР°', 'String', 'Text', 'РўРµРєСЃС‚'];
        }

        array_unshift($candidates, $typeName);
        $candidates = array_values(array_unique(array_filter(array_map(static function ($value) {
            return trim((string)$value);
        }, $candidates), static function ($value) {
            return $value !== '';
        })));

        $resolvedName = $typeName !== '' ? $typeName : ($candidates[0] ?? 'String');
        if ($resolvedName === '' && !empty($candidates)) {
            $resolvedName = $candidates[0];
        }
        return [$resolvedName, $candidates, $fields];
    }

    /**
     * @return array{array<string, array<string, mixed>>, array<string, array<int, string>>}
     */
    private function normalizeCompositePropertiesSetting(mixed $value): array {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && SysClass::ee_isValidJson($trimmed)) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }
        }
        if (!is_array($value)) {
            return [[], []];
        }

        $validFieldTypes = Constants::ALL_TYPE_PROPERTY_TYPES_FIELDS;
        if (!is_array($validFieldTypes)) {
            $validFieldTypes = [];
        }

        $definitions = [];
        $memberIndex = [];

        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $setSourceId = strtolower($this->normalizeSourceId($item['set_source_id'] ?? ''));
            $sourceKind = $this->normalizeCompositeSourceKind($item['source_kind'] ?? '', $setSourceId);
            $entityType = $this->normalizeEntityType($item['entity_type'] ?? 'all');
            $targetPropertyId = max(0, $this->toInt($item['target_property_id'] ?? 0, 0));
            $propertyIsMultiple = $this->toBool(
                $item['is_multiple'] ?? ($item['property_is_multiple'] ?? ($item['multiple'] ?? false)),
                false
            ) ? 1 : 0;
            $propertyIsRequired = $this->toBool(
                $item['is_required'] ?? ($item['property_is_required'] ?? ($item['required'] ?? false)),
                false
            ) ? 1 : 0;

            $fields = [];
            $fieldIndexBySourceId = [];
            $targetFieldIndexBySourceId = [];
            $fieldPatternRules = [];
            $typeFields = [];

            $fieldRows = is_array($item['fields'] ?? null) ? $item['fields'] : [];
            foreach ($fieldRows as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $memberSourcePattern = trim((string)($field['property_source_pattern'] ?? ($field['source_pattern'] ?? '')));
                if ($memberSourcePattern === '') {
                    $memberSourcePattern = trim((string)($field['meta_key_pattern'] ?? ''));
                }
                if ($memberSourcePattern !== '') {
                    $memberSourcePattern = $this->normalizeCompositeSourcePattern($memberSourcePattern, $sourceKind);
                }

                $memberSourceId = strtolower(trim($this->resolveCompositeFieldSourceId($field, $sourceKind)));
                if ($memberSourceId === '' && $memberSourcePattern !== '') {
                    $memberSourceId = $memberSourcePattern;
                }
                if ($memberSourcePattern === '') {
                    $memberSourcePattern = $this->buildCompositeWildcardPattern($memberSourceId);
                }
                if ($memberSourceId === '' || isset($fieldIndexBySourceId[$memberSourceId])) {
                    continue;
                }
                if ($this->isExcludedPropertySourceId($memberSourceId)) {
                    continue;
                }

                $type = strtolower(trim((string)($field['type'] ?? 'text')));
                if ($type === '' || (!empty($validFieldTypes) && !array_key_exists($type, $validFieldTypes))) {
                    $type = 'text';
                }
                $label = trim((string)($field['label'] ?? ''));
                $title = trim((string)($field['title'] ?? ''));
                $metaKey = $this->extractMetaKeyFromSourceId($memberSourceId);
                $targetFieldIndex = null;
                if (array_key_exists('target_field_index', $field) || array_key_exists('field_index', $field)) {
                    $rawTargetFieldIndex = $this->toInt($field['target_field_index'] ?? $field['field_index'], -1);
                    $targetFieldIndex = $rawTargetFieldIndex >= 0 ? $rawTargetFieldIndex : -1;
                    $targetFieldIndexBySourceId[$memberSourceId] = $targetFieldIndex;
                }

                $fieldIndex = count($fields);
                $fields[] = [
                    'type' => $type,
                    'label' => $label,
                    'title' => $title,
                    'meta_key' => $metaKey,
                    'property_source_id' => $memberSourceId,
                    'property_source_pattern' => $memberSourcePattern,
                    'target_field_index' => $targetFieldIndex,
                    'multiple' => $this->toBool($field['multiple'] ?? false, false) ? 1 : 0,
                    'required' => $this->toBool($field['required'] ?? false, false) ? 1 : 0,
                ];

                $fieldIndexBySourceId[$memberSourceId] = $fieldIndex;
                if ($memberSourcePattern !== '' && $memberSourcePattern !== $memberSourceId) {
                    $fieldPatternRules[] = [
                        'source_pattern' => $memberSourcePattern,
                        'member_source_id' => $memberSourceId,
                        'field_index' => $fieldIndex,
                    ];
                }
                $typeFields[] = $type;
            }

            if (empty($fields)) {
                continue;
            }

            $name = trim((string)($item['name'] ?? ''));
            if ($name === '') {
                $firstField = $fields[0];
                $name = trim((string)($firstField['title'] ?? $firstField['label'] ?? $firstField['meta_key'] ?? ''));
            }
            if ($name === '') {
                $name = 'Composite Property';
            }

            $providedSourceId = strtolower($this->normalizeSourceId($item['source_id'] ?? ''));
            if ($providedSourceId === '') {
                $providedSourceId = $this->buildCompositeSyntheticSourceId(
                    $setSourceId,
                    $sourceKind,
                    $entityType,
                    $name,
                    array_keys($fieldIndexBySourceId)
                );
            }

            $typeFields = array_values(array_unique(array_filter($typeFields, static fn($code) => trim((string)$code) !== '')));
            if (empty($typeFields)) {
                $typeFields = ['text'];
            }

            $typeSourceId = 'composite_type:' . substr(sha1($providedSourceId), 0, 24);
            $typeName = 'Комплексный: ' . $name;
            if (function_exists('mb_substr')) {
                $typeName = (string)mb_substr($typeName, 0, 255);
            } else {
                $typeName = substr($typeName, 0, 255);
            }

            $definitions[$providedSourceId] = [
                'source_id' => $providedSourceId,
                'name' => $name,
                'entity_type' => $entityType,
                'set_source_id' => $setSourceId,
                'source_kind' => $sourceKind,
                'target_property_id' => $targetPropertyId,
                'target_property_name' => trim((string)($item['target_property_name'] ?? '')),
                'is_multiple' => $propertyIsMultiple,
                'is_required' => $propertyIsRequired,
                'type_source_id' => $typeSourceId,
                'type_name' => $typeName,
                'type_fields' => $typeFields,
                'fields' => $fields,
                'field_index_by_source_id' => $fieldIndexBySourceId,
                'field_pattern_rules' => $fieldPatternRules,
                'target_field_index_by_source_id' => $targetFieldIndexBySourceId,
            ];

            foreach (array_keys($fieldIndexBySourceId) as $memberSourceId) {
                if (!isset($memberIndex[$memberSourceId])) {
                    $memberIndex[$memberSourceId] = [];
                }
                $memberIndex[$memberSourceId][] = $providedSourceId;
            }
        }

        return [$definitions, $memberIndex];
    }
    private function normalizeCompositeSourceKind(mixed $value, string $setSourceId = ''): string {
        $kind = strtolower(trim((string)$value));
        if ($kind === 'postmeta' || $kind === 'termmeta') {
            return $kind;
        }
        $setSourceId = strtolower(trim($setSourceId));
        if (str_starts_with($setSourceId, 'taxonomy:')) {
            return 'termmeta';
        }
        return 'postmeta';
    }

    private function normalizeCompositeSourcePattern(mixed $value, string $sourceKind): string {
        $pattern = strtolower(trim((string)$value));
        if ($pattern === '') {
            return '';
        }
        if (str_starts_with($pattern, 'postmeta:') || str_starts_with($pattern, 'termmeta:')) {
            return $pattern;
        }
        if (str_contains($pattern, ':')) {
            return $pattern;
        }
        return $this->buildCompositePropertySourceIdFromMeta($sourceKind, $pattern);
    }

    private function buildCompositeWildcardPattern(string $sourceId): string {
        $normalized = strtolower(trim($sourceId));
        if ($normalized === '') {
            return '';
        }
        if (str_contains($normalized, '*') || str_contains($normalized, '?')) {
            return $normalized;
        }

        $sourceKind = 'postmeta';
        $metaKey = $normalized;
        if (str_contains($normalized, ':')) {
            [$rawSourceKind, $rawMetaKey] = explode(':', $normalized, 2);
            $sourceKind = $rawSourceKind === 'termmeta' ? 'termmeta' : 'postmeta';
            $metaKey = $rawMetaKey;
        }
        if ($metaKey === '') {
            return '';
        }

        $parts = explode('_', $metaKey);
        $hasNumericPart = false;
        foreach ($parts as &$part) {
            $part = strtolower(trim((string)$part));
            if ($part !== '' && ctype_digit($part)) {
                $part = '*';
                $hasNumericPart = true;
            }
        }
        unset($part);
        if (!$hasNumericPart) {
            return '';
        }

        return $sourceKind . ':' . implode('_', $parts);
    }

    private function extractWildcardCaptures(string $pattern, string $value): array {
        $pattern = trim($pattern);
        $value = trim($value);
        if ($pattern === '' || $value === '') {
            return [];
        }
        if (!str_contains($pattern, '*') && !str_contains($pattern, '?')) {
            return [];
        }

        $regex = '/^' . str_replace(['\*', '\?'], ['(.*?)', '(.)'], preg_quote($pattern, '/')) . '$/iu';
        $matches = [];
        if (!preg_match($regex, $value, $matches)) {
            return [];
        }
        if (count($matches) <= 1) {
            return [];
        }

        return array_values(array_map(
            static fn($capture) => trim((string)$capture),
            array_slice($matches, 1)
        ));
    }

    private function matchCompositeFieldForSourceId(array $definition, string $propertySourceId): ?array {
        $propertySourceId = strtolower(trim($propertySourceId));
        if ($propertySourceId === '') {
            return null;
        }

        $fieldIndexMap = is_array($definition['field_index_by_source_id'] ?? null)
            ? $definition['field_index_by_source_id']
            : [];
        if (array_key_exists($propertySourceId, $fieldIndexMap)) {
            return [
                'member_source_id' => $propertySourceId,
                'field_index' => $this->toInt($fieldIndexMap[$propertySourceId], -1),
                'source_pattern' => $propertySourceId,
                'captures' => [],
                'is_exact' => true,
            ];
        }

        $patternRules = is_array($definition['field_pattern_rules'] ?? null)
            ? $definition['field_pattern_rules']
            : [];
        foreach ($patternRules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $sourcePattern = strtolower(trim((string)($rule['source_pattern'] ?? '')));
            if ($sourcePattern === '') {
                continue;
            }
            $patternMatch = $this->resolveCompositeSourcePatternMatch($sourcePattern, $propertySourceId);
            if (!is_array($patternMatch)) {
                continue;
            }

            $memberSourceId = strtolower(trim((string)($rule['member_source_id'] ?? '')));
            if ($memberSourceId === '') {
                $memberSourceId = $sourcePattern;
            }
            $fieldIndex = $this->toInt(
                $rule['field_index'] ?? ($fieldIndexMap[$memberSourceId] ?? -1),
                -1
            );
            if ($fieldIndex < 0) {
                continue;
            }

            return [
                'member_source_id' => $memberSourceId,
                'field_index' => $fieldIndex,
                'source_pattern' => (string)($patternMatch['matched_pattern'] ?? $sourcePattern),
                'captures' => is_array($patternMatch['captures'] ?? null) ? $patternMatch['captures'] : [],
                'is_exact' => false,
            ];
        }

        foreach ($fieldIndexMap as $memberSourceId => $fieldIndex) {
            $memberSourceId = strtolower(trim((string)$memberSourceId));
            if ($memberSourceId === '' || (!str_contains($memberSourceId, '*') && !str_contains($memberSourceId, '?'))) {
                continue;
            }
            $patternMatch = $this->resolveCompositeSourcePatternMatch($memberSourceId, $propertySourceId);
            if (!is_array($patternMatch)) {
                continue;
            }
            return [
                'member_source_id' => $memberSourceId,
                'field_index' => $this->toInt($fieldIndex, -1),
                'source_pattern' => (string)($patternMatch['matched_pattern'] ?? $memberSourceId),
                'captures' => is_array($patternMatch['captures'] ?? null) ? $patternMatch['captures'] : [],
                'is_exact' => false,
            ];
        }

        return null;
    }

    private function matchesCompositeSourcePattern(string $pattern, string $propertySourceId): bool {
        return is_array($this->resolveCompositeSourcePatternMatch($pattern, $propertySourceId));
    }

    private function resolveCompositeSourcePatternMatch(string $pattern, string $propertySourceId): ?array {
        $pattern = strtolower(trim($pattern));
        $propertySourceId = strtolower(trim($propertySourceId));
        if ($pattern === '' || $propertySourceId === '') {
            return null;
        }

        foreach ($this->expandCompositeLegacyPatternVariants($pattern) as $alternatePattern) {
            if ($alternatePattern !== '' && $this->wildcardMatch($alternatePattern, $propertySourceId)) {
                return [
                    'matched_pattern' => $alternatePattern,
                    'captures' => $this->extractWildcardCaptures($alternatePattern, $propertySourceId),
                ];
            }
        }

        if ($this->wildcardMatch($pattern, $propertySourceId)) {
            return [
                'matched_pattern' => $pattern,
                'captures' => $this->extractWildcardCaptures($pattern, $propertySourceId),
            ];
        }

        return null;
    }

    private function expandCompositeLegacyPatternVariants(string $pattern): array {
        $pattern = strtolower(trim($pattern));
        if ($pattern === '') {
            return [];
        }

        $variants = [];
        if (preg_match('/^(postmeta:numbers_\\*_)(\\d+)(_from|_end|_pricec)$/', $pattern, $matches) === 1) {
            $variants[] = $matches[1] . 'newprices_' . $matches[2] . $matches[3];
        }

        return array_values(array_unique(array_filter($variants, static fn(string $item): bool => $item !== '')));
    }

    private function getCompositeCandidateIdsForSourceId(string $sourceId): array {
        $sourceId = strtolower(trim($sourceId));
        if ($sourceId === '') {
            return [];
        }

        $candidateMap = [];
        $directCandidates = $this->compositeByMemberSourceId[$sourceId] ?? [];
        foreach ($directCandidates as $candidateId) {
            $candidateId = strtolower(trim((string)$candidateId));
            if ($candidateId === '') {
                continue;
            }
            $candidateMap[$candidateId] = $candidateId;
        }

        foreach ($this->compositePropertyDefinitions as $candidateId => $definition) {
            if (!is_array($definition)) {
                continue;
            }
            if ($this->matchCompositeFieldForSourceId($definition, $sourceId) === null) {
                continue;
            }
            $candidateId = strtolower(trim((string)$candidateId));
            if ($candidateId === '') {
                continue;
            }
            $candidateMap[$candidateId] = $candidateId;
        }

        return array_values($candidateMap);
    }

    private function resolveCompositeFieldSourceId(array $field, string $sourceKind): string {
        $sourceId = strtolower($this->normalizeSourceId($field['property_source_id'] ?? ($field['source_id'] ?? '')));
        if ($sourceId !== '') {
            if (str_starts_with($sourceId, 'postmeta:') || str_starts_with($sourceId, 'termmeta:')) {
                return $sourceId;
            }
            if (str_contains($sourceId, ':')) {
                return $sourceId;
            }
        }

        $metaKey = trim((string)($field['meta_key'] ?? ''));
        if ($metaKey === '' && $sourceId !== '' && str_contains($sourceId, ':')) {
            $metaKey = $this->extractMetaKeyFromSourceId($sourceId);
        }
        if ($metaKey === '') {
            return '';
        }
        return $this->buildCompositePropertySourceIdFromMeta($sourceKind, $metaKey);
    }

    private function buildCompositePropertySourceIdFromMeta(string $sourceKind, string $metaKey): string {
        $sourceKind = $sourceKind === 'termmeta' ? 'termmeta' : 'postmeta';
        $metaKey = strtolower(trim($metaKey));
        if ($metaKey === '') {
            return '';
        }
        if (str_starts_with($metaKey, 'postmeta:') || str_starts_with($metaKey, 'termmeta:')) {
            return $metaKey;
        }
        return $sourceKind . ':' . $metaKey;
    }

    private function buildCompositeSyntheticSourceId(
        string $setSourceId,
        string $sourceKind,
        string $entityType,
        string $name,
        array $memberSourceIds
    ): string {
        $seed = strtolower(trim($setSourceId)) . '|' .
            strtolower(trim($sourceKind)) . '|' .
            strtolower(trim($entityType)) . '|' .
            strtolower(trim($name)) . '|' .
            strtolower(trim(implode('|', $memberSourceIds)));
        return 'composite:' . substr(sha1($seed), 0, 24);
    }

    private function isCompositeMemberSourceId(string $sourceId): bool {
        $sourceId = strtolower(trim($sourceId));
        if ($sourceId === '') {
            return false;
        }
        return !empty($this->getCompositeCandidateIdsForSourceId($sourceId));
    }

    private function hasCompositeDefinitionForSet(string $propertySourceId, string $setSourceId): bool {
        $propertySourceId = strtolower(trim($propertySourceId));
        if ($propertySourceId === '') {
            return false;
        }
        $setSourceId = strtolower(trim($setSourceId));
        $candidateIds = $this->getCompositeCandidateIdsForSourceId($propertySourceId);
        if (empty($candidateIds)) {
            return false;
        }
        foreach ($candidateIds as $candidateId) {
            $definition = $this->compositePropertyDefinitions[$candidateId] ?? null;
            if (!is_array($definition)) {
                continue;
            }
            $definitionSetSourceId = strtolower(trim((string)($definition['set_source_id'] ?? '')));
            if ($definitionSetSourceId === '' || $setSourceId === '' || $definitionSetSourceId === $setSourceId) {
                return true;
            }
        }
        return false;
    }

    private function ensureCompositePropertiesForMemberSourceId(string $sourceId): array {
        $sourceId = strtolower(trim($sourceId));
        $compositeIds = $this->getCompositeCandidateIdsForSourceId($sourceId);
        if (empty($compositeIds)) {
            return $this->result('skipped');
        }

        $created = 0;
        $updated = 0;
        foreach ($compositeIds as $compositeId) {
            $definition = $this->compositePropertyDefinitions[$compositeId] ?? null;
            if (!is_array($definition)) {
                continue;
            }
            $result = $this->ensureCompositePropertyImported($definition);
            $status = strtolower(trim((string)($result['status'] ?? '')));
            if ($status === 'failed') {
                return $result;
            }
            if ($status === 'created') {
                $created++;
            } elseif ($status === 'updated') {
                $updated++;
            }
        }

        if ($created > 0) {
            return $this->result('created');
        }
        if ($updated > 0) {
            return $this->result('updated');
        }
        return $this->result('skipped');
    }

    private function isCompositeDefinitionLikelyMultiple(array $definition): bool {
        if ($this->toBool($definition['is_multiple'] ?? false, false)) {
            return true;
        }

        $patternRules = is_array($definition['field_pattern_rules'] ?? null)
            ? $definition['field_pattern_rules']
            : [];
        foreach ($patternRules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $pattern = strtolower(trim((string)($rule['source_pattern'] ?? '')));
            if ($pattern !== '' && (str_contains($pattern, '*') || str_contains($pattern, '?'))) {
                return true;
            }
        }

        $fieldIndexMap = is_array($definition['field_index_by_source_id'] ?? null)
            ? $definition['field_index_by_source_id']
            : [];
        foreach (array_keys($fieldIndexMap) as $sourceId) {
            $sourceId = strtolower(trim((string)$sourceId));
            if ($sourceId === '') {
                continue;
            }
            if (str_contains($sourceId, '*') || str_contains($sourceId, '?')) {
                return true;
            }
            $metaKey = strtolower(trim($this->extractMetaKeyFromSourceId($sourceId)));
            if ($metaKey !== '' && preg_match('/(^|_)\\d+($|_)/', $metaKey)) {
                return true;
            }
        }

        return false;
    }

    private function ensureCompositePropertyImported(array $definition): array {
        $compositeSourceId = strtolower(trim((string)($definition['source_id'] ?? '')));
        if ($compositeSourceId === '') {
            return $this->result('failed', 'Composite property source_id is empty');
        }

        $setSourceId = strtolower(trim((string)($definition['set_source_id'] ?? '')));
        if ($setSourceId !== '' && !$this->isAllowedTypeSource($setSourceId)) {
            return $this->result('skipped');
        }

        $fields = is_array($definition['fields'] ?? null) ? $definition['fields'] : [];
        if (empty($fields)) {
            return $this->result('failed', 'Composite property has no fields: ' . $compositeSourceId);
        }
        $fieldIndexBySourceId = is_array($definition['field_index_by_source_id'] ?? null)
            ? $definition['field_index_by_source_id']
            : [];
        $targetFieldIndexBySourceId = is_array($definition['target_field_index_by_source_id'] ?? null)
            ? $definition['target_field_index_by_source_id']
            : [];
        $propertyIsMultiple = $this->toBool($definition['is_multiple'] ?? false, false);
        if (!$propertyIsMultiple && $this->isCompositeDefinitionLikelyMultiple($definition)) {
            $propertyIsMultiple = true;
        }
        $propertyIsRequired = $this->toBool($definition['is_required'] ?? false, false);
        $propertyIsMultipleInt = $propertyIsMultiple ? 1 : 0;
        $propertyIsRequiredInt = $propertyIsRequired ? 1 : 0;

        $inputName = trim((string)($definition['name'] ?? ''));
        $name = $inputName;
        if ($name === '') {
            $name = 'Composite Property';
        }

        $configuredPropertyId = max(0, $this->toInt($definition['target_property_id'] ?? 0, 0));
        $existingPropertyId = 0;
        if ($configuredPropertyId > 0) {
            $exists = (int)SafeMySQL::gi()->getOne(
                'SELECT property_id FROM ?n WHERE property_id = ?i LIMIT 1',
                Constants::PROPERTIES_TABLE,
                $configuredPropertyId
            );
            if ($exists > 0) {
                $existingPropertyId = $configuredPropertyId;
            }
        }
        $isConfiguredExistingProperty = $configuredPropertyId > 0 && $existingPropertyId === $configuredPropertyId;
        $configuredPropertyName = trim((string)($definition['target_property_name'] ?? ''));
        if ($existingPropertyId <= 0 && $configuredPropertyName !== '') {
            $existingPropertyId = (int)SafeMySQL::gi()->getOne(
                'SELECT property_id FROM ?n WHERE name = ?s AND language_code = ?s LIMIT 1',
                Constants::PROPERTIES_TABLE,
                $configuredPropertyName,
                $this->languageCode
            );
            if ($existingPropertyId > 0) {
                $isConfiguredExistingProperty = true;
            }
        }
        if ($existingPropertyId <= 0) {
            $existingPropertyId = $this->getMappedId('property', $compositeSourceId);
        }
        $typeId = 0;
        if (!$isConfiguredExistingProperty) {
            $typeId = $this->ensureCompositePropertyTypeId($definition);
            if ($typeId <= 0) {
                return $this->result('failed', 'Cannot resolve property type for composite=' . $compositeSourceId);
            }
        }
        if ($existingPropertyId <= 0 && $typeId > 0) {
            $existingPropertyId = (int)SafeMySQL::gi()->getOne(
                'SELECT property_id FROM ?n WHERE name = ?s AND type_id = ?i AND language_code = ?s LIMIT 1',
                Constants::PROPERTIES_TABLE,
                $name,
                $typeId,
                $this->languageCode
            );
        }

        if ($inputName === '' && $existingPropertyId > 0) {
            $currentName = trim((string)SafeMySQL::gi()->getOne(
                'SELECT name FROM ?n WHERE property_id = ?i AND language_code = ?s LIMIT 1',
                Constants::PROPERTIES_TABLE,
                $existingPropertyId,
                $this->languageCode
            ));
            if ($currentName !== '') {
                $name = $currentName;
            }
        }

        $targetEntityType = $this->normalizeEntityType($definition['entity_type'] ?? 'all');
        if ($existingPropertyId > 0) {
            $currentEntityType = strtolower(trim((string)SafeMySQL::gi()->getOne(
                'SELECT entity_type FROM ?n WHERE property_id = ?i LIMIT 1',
                Constants::PROPERTIES_TABLE,
                $existingPropertyId
            )));
            if ($currentEntityType === '') {
                $currentEntityType = 'all';
            }
            if ($targetEntityType === '') {
                $targetEntityType = $currentEntityType;
            }
            if ($currentEntityType !== $targetEntityType) {
                $targetEntityType = 'all';
            }
        }

        $defaultValues = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $defaultValues[] = [
                'type' => strtolower(trim((string)($field['type'] ?? 'text'))) ?: 'text',
                'label' => (string)($field['label'] ?? ''),
                'title' => (string)($field['title'] ?? ''),
                'default' => '',
                'multiple' => (!empty($field['multiple']) || $propertyIsMultipleInt === 1) ? 1 : 0,
                'required' => !empty($field['required']) ? 1 : 0,
            ];
        }
        if (empty($defaultValues)) {
            return $this->result('failed', 'Composite property has no fields: ' . $compositeSourceId);
        }

        $resolvedFieldIndexBySourceId = [];
        foreach ($fieldIndexBySourceId as $memberSourceId => $fallbackIndex) {
            $memberSourceId = strtolower(trim((string)$memberSourceId));
            if ($memberSourceId === '') {
                continue;
            }
            $resolvedIndex = $this->toInt($targetFieldIndexBySourceId[$memberSourceId] ?? $fallbackIndex, -1);
            if ($resolvedIndex >= 0) {
                $resolvedFieldIndexBySourceId[$memberSourceId] = $resolvedIndex;
            }
        }

        if ($isConfiguredExistingProperty && $existingPropertyId > 0) {
            $currentDefaultValues = $this->getPropertyDefaultFieldsTemplate($existingPropertyId);
            if (!is_array($currentDefaultValues)) {
                $currentDefaultValues = [];
            }
            $mergedDefaultValues = $currentDefaultValues;
            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $memberSourceId = strtolower(trim((string)($field['property_source_id'] ?? '')));
                if ($memberSourceId === '') {
                    continue;
                }
                $preferredIndex = $this->toInt(
                    $targetFieldIndexBySourceId[$memberSourceId] ?? ($fieldIndexBySourceId[$memberSourceId] ?? -1),
                    -1
                );
                if ($preferredIndex >= 0 && array_key_exists($preferredIndex, $mergedDefaultValues)) {
                    $resolvedFieldIndexBySourceId[$memberSourceId] = $preferredIndex;
                    continue;
                }
                $fieldType = strtolower(trim((string)($field['type'] ?? 'text'))) ?: 'text';
                $fieldTitle = trim((string)($field['title'] ?? ''));
                $fieldLabel = trim((string)($field['label'] ?? ''));
                $fieldTitleNeedle = strtolower($fieldTitle);
                $fieldLabelNeedle = strtolower($fieldLabel);
                if ($fieldTitleNeedle !== '' || $fieldLabelNeedle !== '') {
                    foreach ($mergedDefaultValues as $existingFieldIndex => $existingField) {
                        if (!is_array($existingField)) {
                            continue;
                        }
                        $existingType = strtolower(trim((string)($existingField['type'] ?? 'text'))) ?: 'text';
                        if ($existingType !== $fieldType) {
                            continue;
                        }
                        $existingTitle = strtolower(trim((string)($existingField['title'] ?? '')));
                        $existingLabelRaw = $existingField['label'] ?? '';
                        if (is_array($existingLabelRaw)) {
                            $labelParts = [];
                            foreach ($existingLabelRaw as $labelPart) {
                                $labelPart = trim((string)$labelPart);
                                if ($labelPart !== '') {
                                    $labelParts[] = $labelPart;
                                }
                            }
                            $existingLabelRaw = implode(', ', $labelParts);
                        }
                        $existingLabel = strtolower(trim((string)$existingLabelRaw));

                        $titleMatched = $fieldTitleNeedle !== '' && $existingTitle === $fieldTitleNeedle;
                        $labelMatched = $fieldLabelNeedle !== '' && $existingLabel === $fieldLabelNeedle;
                        if ($titleMatched || $labelMatched) {
                            $resolvedFieldIndexBySourceId[$memberSourceId] = (int)$existingFieldIndex;
                            continue 2;
                        }
                    }
                }
                $mergedDefaultValues[] = [
                    'type' => $fieldType,
                    'label' => (string)($field['label'] ?? ''),
                    'title' => (string)($field['title'] ?? ''),
                    'default' => '',
                    'multiple' => (!empty($field['multiple']) || $propertyIsMultipleInt === 1) ? 1 : 0,
                    'required' => !empty($field['required']) ? 1 : 0,
                ];
                $resolvedFieldIndexBySourceId[$memberSourceId] = count($mergedDefaultValues) - 1;
            }
            if (empty($mergedDefaultValues)) {
                $mergedDefaultValues = $defaultValues;
            }

            $currentDefaultJson = $this->prepareJsonField($currentDefaultValues, []);
            $mergedDefaultJson = $this->prepareJsonField($mergedDefaultValues, []);
            if ($mergedDefaultJson !== $currentDefaultJson) {
                SafeMySQL::gi()->query(
                    'UPDATE ?n SET default_values = ?s, updated_at = NOW() WHERE property_id = ?i',
                    Constants::PROPERTIES_TABLE,
                    $mergedDefaultJson,
                    $existingPropertyId
                );
            }

            $currentFlags = SafeMySQL::gi()->getRow(
                'SELECT is_multiple, is_required FROM ?n WHERE property_id = ?i LIMIT 1',
                Constants::PROPERTIES_TABLE,
                $existingPropertyId
            );
            $flagUpdateData = [];
            if ($propertyIsMultipleInt === 1 && (int)($currentFlags['is_multiple'] ?? 0) !== 1) {
                $flagUpdateData['is_multiple'] = 1;
            }
            if ($propertyIsRequiredInt === 1 && (int)($currentFlags['is_required'] ?? 0) !== 1) {
                $flagUpdateData['is_required'] = 1;
            }
            if (!empty($flagUpdateData)) {
                SafeMySQL::gi()->query(
                    'UPDATE ?n SET ?u, updated_at = NOW() WHERE property_id = ?i',
                    Constants::PROPERTIES_TABLE,
                    $flagUpdateData,
                    $existingPropertyId
                );
            }

            if (!isset($this->compositePropertyDefinitions[$compositeSourceId]) || !is_array($this->compositePropertyDefinitions[$compositeSourceId])) {
                $this->compositePropertyDefinitions[$compositeSourceId] = $definition;
            }
            $this->compositePropertyDefinitions[$compositeSourceId]['resolved_field_index_by_source_id'] = $resolvedFieldIndexBySourceId;
            $this->saveMappedId('property', $compositeSourceId, (int)$existingPropertyId);
            $this->ensureCompositeSetPropertyLink($definition, (int)$existingPropertyId);
            return $this->result('updated');
        }

        $propertyData = [
            'property_id' => $existingPropertyId > 0 ? $existingPropertyId : 0,
            'type_id' => $typeId,
            'name' => $name,
            'status' => 'active',
            'sort' => 100,
            'default_values' => $this->prepareJsonField($defaultValues, []),
            'is_multiple' => $propertyIsMultipleInt,
            'is_required' => $propertyIsRequiredInt,
            'description' => 'РљРѕРјРїР»РµРєСЃРЅРѕРµ СЃРІРѕР№СЃС‚РІРѕ WordPress-РёРјРїРѕСЂС‚Р°',
            'entity_type' => $targetEntityType,
        ];

        $propertySaveResult = OperationResult::fromLegacy(
            $this->objectModelProperties->updatePropertyData($propertyData, $this->languageCode),
            ['false_message' => 'Failed to import composite property: ' . $name]
        );
        $propertyId = $propertySaveResult->isSuccess() ? $propertySaveResult->getId(['property_id', 'id']) : 0;
        if ($propertyId <= 0) {
            if ($existingPropertyId > 0) {
                $propertyId = $existingPropertyId;
            } else {
                return $this->result('failed', $propertySaveResult->getMessage('Failed to import composite property: ' . $name));
            }
        }

        if (!isset($this->compositePropertyDefinitions[$compositeSourceId]) || !is_array($this->compositePropertyDefinitions[$compositeSourceId])) {
            $this->compositePropertyDefinitions[$compositeSourceId] = $definition;
        }
        $this->compositePropertyDefinitions[$compositeSourceId]['resolved_field_index_by_source_id'] = $fieldIndexBySourceId;
        $this->saveMappedId('property', $compositeSourceId, (int)$propertyId);
        $this->ensureCompositeSetPropertyLink($definition, (int)$propertyId);

        return $this->result($existingPropertyId > 0 ? 'updated' : 'created');
    }

    private function ensureCompositePropertyTypeId(array $definition): int {
        $typeSourceId = strtolower(trim((string)($definition['type_source_id'] ?? '')));
        if ($typeSourceId === '') {
            $typeSourceId = 'composite_type:' . substr(sha1((string)($definition['source_id'] ?? '')), 0, 24);
        }
        $typeName = trim((string)($definition['type_name'] ?? 'РљРѕРјРїР»РµРєСЃРЅС‹Р№ С‚РёРї'));
        if ($typeName === '') {
            $typeName = 'РљРѕРјРїР»РµРєСЃРЅС‹Р№ С‚РёРї';
        }

        $typeFields = is_array($definition['type_fields'] ?? null) ? $definition['type_fields'] : [];
        $typeFields = array_values(array_filter(array_map(static fn($v) => strtolower(trim((string)$v)), $typeFields), static fn($v) => $v !== ''));
        if (empty($typeFields)) {
            $typeFields = ['text'];
        }

        return $this->ensurePropertyTypeId([
            'type_source_id' => $typeSourceId,
            'type_name' => $typeName,
            'type_fields' => $typeFields,
            'type_status' => 'active',
            'type_description' => 'РђРІС‚РѕСЃРѕР·РґР°РЅРЅС‹Р№ С‚РёРї РґР»СЏ РєРѕРјРїР»РµРєСЃРЅРѕРіРѕ СЃРІРѕР№СЃС‚РІР° РёРјРїРѕСЂС‚Р°',
        ]);
    }

    private function ensureCompositeSetPropertyLink(array $definition, int $propertyId): void {
        if ($propertyId <= 0) {
            return;
        }
        $setSourceId = strtolower(trim((string)($definition['set_source_id'] ?? '')));
        if ($setSourceId === '' || !$this->isAllowedTypeSource($setSourceId)) {
            return;
        }

        $setId = $this->getMappedOrLocal('property_set', $setSourceId, 0);
        if ($setId <= 0) {
            return;
        }

        $exists = (int)SafeMySQL::gi()->getOne(
            'SELECT 1 FROM ?n WHERE set_id = ?i AND property_id = ?i LIMIT 1',
            Constants::PROPERTY_SET_TO_PROPERTIES_TABLE,
            $setId,
            $propertyId
        );
        if ($exists > 0) {
            return;
        }

        SafeMySQL::gi()->query(
            'INSERT INTO ?n SET ?u',
            Constants::PROPERTY_SET_TO_PROPERTIES_TABLE,
            ['set_id' => $setId, 'property_id' => $propertyId]
        );
    }

    private function importCompositePropertyValueRow(
        array $row,
        string $propertySourceId,
        string $setSourceId,
        string $entityType,
        array $definition,
        array $match = []
    ): array {
        $propertySourceId = strtolower(trim($propertySourceId));
        $compositeSourceId = strtolower(trim((string)($definition['source_id'] ?? '')));
        $matchData = is_array($match) ? $match : [];

        $propertyId = $this->getMappedId('property', $compositeSourceId);
        if ($propertyId <= 0) {
            $createResult = $this->ensureCompositePropertyImported($definition);
            if (($createResult['status'] ?? '') === 'failed') {
                return $createResult;
            }
            $propertyId = $this->getMappedId('property', $compositeSourceId);
            if ($propertyId <= 0) {
                return $this->result('skipped');
            }
        }
        if ($compositeSourceId !== '' && isset($this->compositePropertyDefinitions[$compositeSourceId]) && is_array($this->compositePropertyDefinitions[$compositeSourceId])) {
            $definition = $this->compositePropertyDefinitions[$compositeSourceId];
        }
        $resolvedFieldIndexMap = is_array($definition['resolved_field_index_by_source_id'] ?? null)
            ? $definition['resolved_field_index_by_source_id']
            : [];
        $targetFieldIndexMap = is_array($definition['target_field_index_by_source_id'] ?? null)
            ? $definition['target_field_index_by_source_id']
            : [];
        $fieldIndexMap = is_array($definition['field_index_by_source_id'] ?? null)
            ? $definition['field_index_by_source_id']
            : [];

        if (empty($resolvedFieldIndexMap) && max(0, $this->toInt($definition['target_property_id'] ?? 0, 0)) > 0) {
            $ensureResult = $this->ensureCompositePropertyImported($definition);
            if (($ensureResult['status'] ?? '') === 'failed') {
                return $ensureResult;
            }
            if ($compositeSourceId !== '' && isset($this->compositePropertyDefinitions[$compositeSourceId]) && is_array($this->compositePropertyDefinitions[$compositeSourceId])) {
                $definition = $this->compositePropertyDefinitions[$compositeSourceId];
                $resolvedFieldIndexMap = is_array($definition['resolved_field_index_by_source_id'] ?? null)
                    ? $definition['resolved_field_index_by_source_id']
                    : [];
                $targetFieldIndexMap = is_array($definition['target_field_index_by_source_id'] ?? null)
                    ? $definition['target_field_index_by_source_id']
                    : [];
                $fieldIndexMap = is_array($definition['field_index_by_source_id'] ?? null)
                    ? $definition['field_index_by_source_id']
                    : [];
            }
        }

        $matchedMemberSourceId = strtolower(trim((string)($matchData['member_source_id'] ?? '')));
        if ($matchedMemberSourceId === '') {
            $matchedMemberSourceId = $propertySourceId;
        }

        $fieldIndex = -1;
        if (array_key_exists($matchedMemberSourceId, $resolvedFieldIndexMap)) {
            $fieldIndex = $this->toInt($resolvedFieldIndexMap[$matchedMemberSourceId], -1);
        } elseif (array_key_exists($propertySourceId, $resolvedFieldIndexMap)) {
            $fieldIndex = $this->toInt($resolvedFieldIndexMap[$propertySourceId], -1);
        } elseif (array_key_exists($matchedMemberSourceId, $targetFieldIndexMap)) {
            $fieldIndex = $this->toInt($targetFieldIndexMap[$matchedMemberSourceId], -1);
        } elseif (array_key_exists($propertySourceId, $targetFieldIndexMap)) {
            $fieldIndex = $this->toInt($targetFieldIndexMap[$propertySourceId], -1);
        } elseif (array_key_exists($matchedMemberSourceId, $fieldIndexMap)) {
            $fieldIndex = $this->toInt($fieldIndexMap[$matchedMemberSourceId], -1);
        } elseif (array_key_exists($propertySourceId, $fieldIndexMap)) {
            $fieldIndex = $this->toInt($fieldIndexMap[$propertySourceId], -1);
        }
        if ($fieldIndex < 0) {
            $runtimeMatch = $this->matchCompositeFieldForSourceId($definition, $propertySourceId);
            if (is_array($runtimeMatch)) {
                $runtimeMemberSourceId = strtolower(trim((string)($runtimeMatch['member_source_id'] ?? '')));
                if ($runtimeMemberSourceId !== '') {
                    $matchedMemberSourceId = $runtimeMemberSourceId;
                }
                $runtimeFieldIndex = $this->toInt($runtimeMatch['field_index'] ?? -1, -1);
                if ($runtimeFieldIndex >= 0) {
                    $fieldIndex = $runtimeFieldIndex;
                }
                if (!isset($matchData['captures']) && isset($runtimeMatch['captures'])) {
                    $matchData['captures'] = $runtimeMatch['captures'];
                }
            }
        }
        if ($fieldIndex < 0) {
            return $this->result('skipped');
        }

        $effectiveSetSourceId = strtolower(trim((string)($definition['set_source_id'] ?? '')));
        if ($effectiveSetSourceId === '') {
            $effectiveSetSourceId = strtolower(trim($setSourceId));
        }
        if ($effectiveSetSourceId !== '' && !$this->isAllowedTypeSource($effectiveSetSourceId)) {
            return $this->result('skipped');
        }

        $entitySourceId = $this->normalizeSourceId($this->rowValue($row, ['entity_source_id', 'source_entity_id'], ''));
        $entityId = $this->getMappedOrLocal($entityType, $entitySourceId, $this->rowValue($row, ['entity_id'], 0));
        if ($entityId <= 0) {
            return $this->result('skipped');
        }

        $setId = $this->getMappedOrLocal('property_set', $effectiveSetSourceId, $this->rowValue($row, ['set_id'], 0));
        if ($setId <= 0) {
            return $this->result('skipped');
        }

        $existingValueRow = $this->getCachedPropertyValueRow(
            $entityType,
            $entityId,
            $propertyId,
            $setId
        );
        $existingValueId = is_array($existingValueRow) ? (int)($existingValueRow['value_id'] ?? 0) : 0;

        $existingPayload = [];
        if (is_array($existingValueRow)) {
            $raw = $existingValueRow['property_values'] ?? '[]';
            if (is_string($raw) && trim($raw) !== '' && SysClass::ee_isValidJson($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $existingPayload = $decoded;
                }
            } elseif (is_array($raw)) {
                $existingPayload = $raw;
            }
        }

        $normalizedPayload = $this->normalizePropertyValuesPayload($propertyId, $existingPayload);
        if (!isset($normalizedPayload[$fieldIndex])) {
            $fallbackIndex = $this->toInt(
                $fieldIndexMap[$matchedMemberSourceId] ?? ($fieldIndexMap[$propertySourceId] ?? -1),
                -1
            );
            if ($fallbackIndex >= 0 && isset($normalizedPayload[$fallbackIndex])) {
                $fieldIndex = $fallbackIndex;
            }
        }
        if (!isset($normalizedPayload[$fieldIndex])) {
            $normalizedPayload = $this->normalizePropertyValuesPayload($propertyId, []);
            if (!isset($normalizedPayload[$fieldIndex])) {
                $fallbackIndex = $this->toInt(
                    $fieldIndexMap[$matchedMemberSourceId] ?? ($fieldIndexMap[$propertySourceId] ?? -1),
                    -1
                );
                if ($fallbackIndex >= 0 && isset($normalizedPayload[$fallbackIndex])) {
                    $fieldIndex = $fallbackIndex;
                }
            }
        }
        if (!isset($normalizedPayload[$fieldIndex])) {
            return $this->result(
                'failed',
                'Composite field index out of range for property=' . $compositeSourceId . ', field=' . $fieldIndex
            );
        }

        $incomingPayload = $this->rowValue($row, ['property_values', 'value', 'values', 'fields'], null);
        $incomingValue = $this->resolveCompositeValueForField($incomingPayload);
        $propertyIsMultiple = $this->toBool($definition['is_multiple'] ?? false, false);
        if (!$propertyIsMultiple && $this->isCompositeDefinitionLikelyMultiple($definition)) {
            $propertyIsMultiple = true;
        }
        $groupKey = $this->resolveCompositeGroupKey($matchData);
        $normalizedPayload[$fieldIndex]['value'] = $this->mergeCompositeFieldValue(
            $normalizedPayload[$fieldIndex]['value'] ?? null,
            $incomingValue,
            $propertyIsMultiple,
            $groupKey
        );
        if ($propertyIsMultiple) {
            foreach ($normalizedPayload as $payloadIndex => $payloadField) {
                if (!is_array($payloadField)) {
                    continue;
                }
                $normalizedPayload[$payloadIndex]['multiple'] = 1;
            }
        }
        $normalizedPayload = $this->normalizePropertyValuesPayload($propertyId, $normalizedPayload);
        $existingPayloadJson = $this->prepareJsonField($this->normalizePropertyValuesPayload($propertyId, $existingPayload), []);
        $normalizedPayloadJson = $this->prepareJsonField($normalizedPayload, []);
        if ($existingValueId > 0 && $existingPayloadJson === $normalizedPayloadJson) {
            return $this->result('skipped');
        }

        $propertyData = [
            'entity_id' => $entityId,
            'property_id' => $propertyId,
            'entity_type' => $entityType,
            'set_id' => $setId,
            'property_values' => $normalizedPayload,
        ];
        if ($existingValueId > 0) {
            $propertyData['value_id'] = $existingValueId;
        }

        $valueSaveResult = OperationResult::fromLegacy(
            $this->objectModelProperties->updatePropertiesValueEntities($propertyData, $this->languageCode),
            ['false_message' => 'Failed to import composite property value for entity=' . $entityId]
        );
        if ($valueSaveResult->isFailure()) {
            return $this->result('failed', $valueSaveResult->getMessage('Failed to import composite property value for entity=' . $entityId));
        }
        $resolvedValueId = $existingValueId > 0 ? $existingValueId : $valueSaveResult->getId(['value_id', 'id']);
        $this->setCachedPropertyValueRow(
            $entityType,
            $entityId,
            $propertyId,
            $setId,
            [
                'value_id' => max(0, (int)$resolvedValueId),
                'property_values' => $normalizedPayloadJson,
            ]
        );
        return $this->result($existingValueId > 0 ? 'updated' : 'created');
    }

    private function resolveCompositeDefinitionForValueRow(
        string $propertySourceId,
        string $setSourceId,
        string $entityType
    ): ?array {
        $propertySourceId = strtolower(trim($propertySourceId));
        if ($propertySourceId === '') {
            return null;
        }

        $candidateIds = $this->getCompositeCandidateIdsForSourceId($propertySourceId);
        if (empty($candidateIds)) {
            return null;
        }

        $setSourceId = strtolower(trim($setSourceId));
        $entityType = $this->normalizeEntityType($entityType);
        $sourceKind = str_starts_with($propertySourceId, 'termmeta:') ? 'termmeta' : 'postmeta';

        $bestDefinition = null;
        $bestMatch = null;
        $bestScore = -1;
        foreach ($candidateIds as $candidateId) {
            $definition = $this->compositePropertyDefinitions[$candidateId] ?? null;
            if (!is_array($definition)) {
                continue;
            }

            $match = $this->matchCompositeFieldForSourceId($definition, $propertySourceId);
            if (!is_array($match)) {
                continue;
            }

            $definitionEntityType = $this->normalizeEntityType($definition['entity_type'] ?? 'all');
            if ($definitionEntityType !== 'all' && $definitionEntityType !== $entityType) {
                continue;
            }

            $definitionSourceKind = $this->normalizeCompositeSourceKind($definition['source_kind'] ?? '', '');
            if ($definitionSourceKind !== $sourceKind) {
                continue;
            }

            $definitionSetSourceId = strtolower(trim((string)($definition['set_source_id'] ?? '')));
            if ($definitionSetSourceId !== '' && $setSourceId !== '' && $definitionSetSourceId !== $setSourceId) {
                continue;
            }

            $score = 0;
            if ($definitionSetSourceId !== '' && $definitionSetSourceId === $setSourceId) {
                $score += 3;
            } elseif ($definitionSetSourceId === '') {
                $score += 1;
            } else {
                $score += 2;
            }
            if ($definitionEntityType === $entityType) {
                $score += 2;
            } elseif ($definitionEntityType === 'all') {
                $score += 1;
            }
            if (!empty($match['is_exact'])) {
                $score += 4;
            } else {
                $score += 2;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestDefinition = $definition;
                $bestMatch = $match;
            }
        }

        if (!is_array($bestDefinition)) {
            return null;
        }

        return [
            'definition' => $bestDefinition,
            'match' => is_array($bestMatch) ? $bestMatch : [],
        ];
    }

    private function resolveCompositeGroupKey(array $match): string {
        $captures = is_array($match['captures'] ?? null) ? $match['captures'] : [];
        if (empty($captures)) {
            return '';
        }
        $normalized = [];
        foreach ($captures as $capture) {
            $capture = trim((string)$capture);
            if ($capture === '') {
                continue;
            }
            $normalized[] = $capture;
        }
        if (empty($normalized)) {
            return '';
        }
        return implode('|', $normalized);
    }

    private function mergeCompositeFieldValue(mixed $currentValue, mixed $incomingValue, bool $propertyIsMultiple, string $groupKey = ''): mixed {
        if (!$propertyIsMultiple) {
            return $incomingValue;
        }

        if (!is_array($currentValue)) {
            $currentValue = [];
        }

        if ($groupKey !== '') {
            $currentValue[$groupKey] = $incomingValue;
            return $currentValue;
        }

        $currentValue[] = $incomingValue;
        return $currentValue;
    }

    private function resolveCompositeValueForField(mixed $payload): mixed {
        if (is_string($payload)) {
            $trimmed = trim($payload);
            if ($trimmed === '') {
                return '';
            }
            if (SysClass::ee_isValidJson($trimmed)) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                } else {
                    return $trimmed;
                }
            } else {
                return $trimmed;
            }
        }

        if ($payload === null) {
            return '';
        }

        if (!is_array($payload)) {
            return $payload;
        }

        if ($this->isSequentialArray($payload)) {
            if (empty($payload)) {
                return '';
            }
            $first = $payload[0];
            if (is_array($first)) {
                if (array_key_exists('value', $first)) {
                    return $first['value'];
                }
                if (array_key_exists('default', $first)) {
                    return $first['default'];
                }
                if (array_key_exists('values', $first)) {
                    return $first['values'];
                }
            }
            return $first;
        }

        if (array_key_exists('value', $payload)) {
            return $payload['value'];
        }
        if (array_key_exists('default', $payload)) {
            return $payload['default'];
        }
        if (array_key_exists('values', $payload)) {
            return $payload['values'];
        }

        foreach ($payload as $value) {
            if (is_scalar($value) || is_array($value)) {
                return $value;
            }
        }

        return '';
    }

    private function normalizeListSetting(mixed $value): array {
        if (is_array($value)) {
            $items = $value;
        } else {
            $raw = (string)$value;
            $items = preg_split('/[\r\n,;]+/', $raw);
        }
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            $item = strtolower(trim((string)$item));
            if ($item === '') {
                continue;
            }
            $normalized[$item] = $item;
        }
        return array_values($normalized);
    }

    private function normalizeIdMapSetting(mixed $value): array {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && SysClass::ee_isValidJson($trimmed)) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $sourceKey => $item) {
                $sourceId = '';
                $targetId = 0;
                $targetName = '';

                if (is_string($sourceKey) && !is_array($item)) {
                    $sourceId = strtolower(trim($sourceKey));
                    if (is_numeric($item)) {
                        $targetId = (int)$item;
                    } else {
                        $targetName = trim((string)$item);
                    }
                } elseif (is_array($item)) {
                    $sourceId = strtolower(trim((string)($item['source_id'] ?? $sourceKey)));
                    $targetId = max(0, $this->toInt($item['target_id'] ?? $item['id'] ?? 0, 0));
                    $targetName = trim((string)($item['target_name'] ?? $item['name'] ?? ''));
                } elseif (is_string($item)) {
                    $line = trim($item);
                    if ($line !== '' && str_contains($line, '=')) {
                        [$left, $right] = array_map('trim', explode('=', $line, 2));
                        $sourceId = strtolower($left);
                        if ($right !== '' && preg_match('/^#?\d+$/', $right)) {
                            $targetId = (int)ltrim($right, '#');
                        } else {
                            $targetName = $right;
                        }
                    }
                }

                if ($sourceId === '' || ($targetId <= 0 && $targetName === '')) {
                    continue;
                }
                $result[$sourceId] = ['id' => $targetId, 'name' => $targetName];
            }

            return $result;
        }

        $raw = (string)$value;
        $lines = preg_split('/[\r\n;]+/', $raw);
        if (!is_array($lines)) {
            return [];
        }

        $result = [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s*=\s*/', $line, 2);
            if (!is_array($parts) || count($parts) !== 2) {
                continue;
            }
            $sourceId = strtolower(trim((string)$parts[0]));
            $right = trim((string)$parts[1]);
            if ($sourceId === '' || $right === '') {
                continue;
            }
            if (preg_match('/^#?\d+$/', $right)) {
                $result[$sourceId] = ['id' => (int)ltrim($right, '#'), 'name' => ''];
            } else {
                $result[$sourceId] = ['id' => 0, 'name' => $right];
            }
        }
        return $result;
    }

    private function normalizePropertyMapSetting(mixed $value): array {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && SysClass::ee_isValidJson($trimmed)) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }
        }

        $result = [];
        if (is_array($value)) {
            foreach ($value as $sourceKey => $item) {
                $sourceId = '';
                $targetId = 0;
                $targetName = '';

                if (is_string($sourceKey) && !is_array($item)) {
                    $sourceId = strtolower(trim($sourceKey));
                    if (is_numeric($item)) {
                        $targetId = (int)$item;
                    } else {
                        $targetName = trim((string)$item);
                    }
                } elseif (is_array($item)) {
                    $sourceId = strtolower(trim((string)($item['source_id'] ?? $sourceKey)));
                    $targetId = max(0, $this->toInt(
                        $item['target_property_id']
                            ?? $item['property_id']
                            ?? $item['target_id']
                            ?? $item['id']
                            ?? 0,
                        0
                    ));
                    $targetName = trim((string)($item['target_property_name'] ?? $item['property_name'] ?? $item['target_name'] ?? $item['name'] ?? ''));
                } elseif (is_string($item)) {
                    $line = trim($item);
                    if ($line !== '' && str_contains($line, '=')) {
                        [$left, $right] = array_map('trim', explode('=', $line, 2));
                        $sourceId = strtolower($left);
                        if ($right !== '' && preg_match('/^#?\d+$/', $right)) {
                            $targetId = (int)ltrim($right, '#');
                        } else {
                            $targetName = $right;
                        }
                    }
                }

                if ($sourceId === '' || ($targetId <= 0 && $targetName === '')) {
                    continue;
                }
                $result[$sourceId] = ['id' => $targetId, 'name' => $targetName];
            }

            return $result;
        }

        $lines = preg_split('/[\r\n;]+/', (string)$value);
        if (!is_array($lines)) {
            return [];
        }
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }
            [$left, $right] = array_map('trim', explode('=', $line, 2));
            $sourceId = strtolower($left);
            if ($sourceId === '' || $right === '') {
                continue;
            }
            if (preg_match('/^#?\d+$/', $right)) {
                $result[$sourceId] = ['id' => (int)ltrim($right, '#'), 'name' => ''];
            } else {
                $result[$sourceId] = ['id' => 0, 'name' => $right];
            }
        }

        return $result;
    }

    private function normalizeTypeSetLinksSetting(mixed $value): array {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && SysClass::ee_isValidJson($trimmed)) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $typeSourceId = $this->normalizeSourceId((string)($item['type_source_id'] ?? $item['category_type_source_id'] ?? ''));
            $setSourceId = $this->normalizeSourceId((string)($item['set_source_id'] ?? $item['property_set_source_id'] ?? ''));
            if ($typeSourceId === '' || $setSourceId === '') {
                continue;
            }
            $result[] = [
                'type_source_id' => $typeSourceId,
                'set_source_id' => $setSourceId,
            ];
        }

        return $result;
    }

    private function wildcardMatch(string $pattern, string $value): bool {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return false;
        }
        $regex = '/^' . str_replace(['\*', '\?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/iu';
        return (bool)preg_match($regex, $value);
    }

    private function matchesAnyPattern(string $value, array $patterns): bool {
        foreach ($patterns as $pattern) {
            if ($this->wildcardMatch((string)$pattern, $value)) {
                return true;
            }
        }
        return false;
    }

    private function isAllowedTypeSource(string $sourceId): bool {
        $sourceId = strtolower(trim($sourceId));
        if ($sourceId === '') {
            return true;
        }
        if (!empty($this->allowedSourceIds)) {
            return in_array($sourceId, $this->allowedSourceIds, true);
        }
        if (str_starts_with($sourceId, 'taxonomy:')) {
            if (empty($this->allowedTaxonomies)) {
                return true;
            }
            $taxonomy = trim(substr($sourceId, strlen('taxonomy:')));
            return in_array($taxonomy, $this->allowedTaxonomies, true);
        }
        if (str_starts_with($sourceId, 'post_type:')) {
            if (empty($this->allowedPostTypes)) {
                return true;
            }
            $postType = trim(substr($sourceId, strlen('post_type:')));
            return in_array($postType, $this->allowedPostTypes, true);
        }
        return true;
    }

    private function isAllowedPostType(string $postType): bool {
        $postType = strtolower(trim($postType));
        if ($postType === '') {
            return true;
        }
        if (!empty($this->allowedSourceIds)) {
            return in_array('post_type:' . $postType, $this->allowedSourceIds, true);
        }
        if (empty($this->allowedPostTypes)) {
            return true;
        }
        return in_array($postType, $this->allowedPostTypes, true);
    }

    private function extractMetaKeyFromSourceId(string $sourceId): string {
        $sourceId = trim($sourceId);
        if ($sourceId === '') {
            return '';
        }
        if (!str_contains($sourceId, ':')) {
            return $sourceId;
        }
        $metaKey = (string)substr($sourceId, strpos($sourceId, ':') + 1);
        return trim($metaKey);
    }

    private function getConfiguredMapLocalId(string $mapType, string $sourceId): int {
        $sourceId = strtolower(trim($sourceId));
        if ($sourceId === '') {
            return 0;
        }
        $cacheKey = $mapType . '|' . $sourceId;
        if (array_key_exists($cacheKey, $this->configuredMapLocalIdCache)) {
            return (int)$this->configuredMapLocalIdCache[$cacheKey];
        }

        $mapping = null;
        if ($mapType === 'category_type') {
            $mapping = $this->sourceTypeMap[$sourceId] ?? null;
        } elseif ($mapType === 'property_set') {
            $mapping = $this->sourceSetMap[$sourceId] ?? null;
        }
        if ($mapping === null) {
            $this->configuredMapLocalIdCache[$cacheKey] = 0;
            return 0;
        }

        if (is_numeric($mapping)) {
            $resolved = (int)$mapping;
            $this->configuredMapLocalIdCache[$cacheKey] = $resolved;
            return $resolved;
        }
        if (!is_array($mapping)) {
            $this->configuredMapLocalIdCache[$cacheKey] = 0;
            return 0;
        }

        $targetId = max(0, (int)($mapping['id'] ?? 0));
        if ($targetId > 0) {
            $this->configuredMapLocalIdCache[$cacheKey] = $targetId;
            return $targetId;
        }

        $targetName = trim((string)($mapping['name'] ?? ''));
        if ($targetName === '') {
            $this->configuredMapLocalIdCache[$cacheKey] = 0;
            return 0;
        }

        if ($mapType === 'category_type') {
            $resolved = (int)SafeMySQL::gi()->getOne(
                'SELECT type_id FROM ?n WHERE name = ?s AND language_code = ?s LIMIT 1',
                Constants::CATEGORIES_TYPES_TABLE,
                $targetName,
                $this->languageCode
            );
            $this->configuredMapLocalIdCache[$cacheKey] = $resolved;
            return $resolved;
        }

        if ($mapType === 'property_set') {
            $resolved = (int)SafeMySQL::gi()->getOne(
                'SELECT set_id FROM ?n WHERE name = ?s AND language_code = ?s LIMIT 1',
                Constants::PROPERTY_SETS_TABLE,
                $targetName,
                $this->languageCode
            );
            $this->configuredMapLocalIdCache[$cacheKey] = $resolved;
            return $resolved;
        }

        $this->configuredMapLocalIdCache[$cacheKey] = 0;
        return 0;
    }

    private function getConfiguredPropertyLocalId(string $sourceId): int {
        $sourceId = strtolower(trim($sourceId));
        if ($sourceId === '') {
            return 0;
        }
        if (array_key_exists($sourceId, $this->configuredPropertyLocalIdCache)) {
            return (int)$this->configuredPropertyLocalIdCache[$sourceId];
        }

        $mapping = $this->sourcePropertyMap[$sourceId] ?? null;
        if (!is_array($mapping)) {
            $this->configuredPropertyLocalIdCache[$sourceId] = 0;
            return 0;
        }

        $propertyId = max(0, (int)($mapping['id'] ?? 0));
        if ($propertyId > 0) {
            $this->configuredPropertyLocalIdCache[$sourceId] = $propertyId;
            return $propertyId;
        }

        $propertyName = trim((string)($mapping['name'] ?? ''));
        if ($propertyName === '') {
            $this->configuredPropertyLocalIdCache[$sourceId] = 0;
            return 0;
        }

        $resolved = (int)SafeMySQL::gi()->getOne(
            'SELECT property_id FROM ?n WHERE name = ?s AND language_code = ?s LIMIT 1',
            Constants::PROPERTIES_TABLE,
            $propertyName,
            $this->languageCode
        );
        $this->configuredPropertyLocalIdCache[$sourceId] = $resolved;
        return $resolved;
    }

    private function isMetaPropertySourceId(string $sourceId): bool {
        $sourceId = strtolower(trim($sourceId));
        if ($sourceId === '') {
            return false;
        }
        return str_starts_with($sourceId, 'postmeta:') || str_starts_with($sourceId, 'termmeta:');
    }

    private function shouldSkipByCompositeWhitelist(string $sourceId): bool {
        if (!$this->strictCompositePropertyMapping) {
            return false;
        }
        $sourceId = strtolower($this->normalizeSourceId($sourceId));
        if ($sourceId === '' || !$this->isMetaPropertySourceId($sourceId)) {
            return false;
        }
        if (isset($this->sourcePropertyMap[$sourceId])) {
            return false;
        }
        return !$this->isCompositeMemberSourceId($sourceId);
    }

    private function isExcludedPropertySourceId(string $sourceId): bool {
        $sourceId = strtolower($this->normalizeSourceId($sourceId));
        if ($sourceId === '' || empty($this->excludedPropertySourceIds)) {
            return false;
        }
        return in_array($sourceId, $this->excludedPropertySourceIds, true);
    }

    private function isAllowedMetaKey(string $metaKey): bool {
        $metaKey = strtolower(trim($metaKey));
        if ($metaKey === '') {
            return false;
        }
        // Featured image must be importable even when private meta keys are filtered out.
        if ($metaKey === '_thumbnail_id') {
            return true;
        }
        if ($this->isHardExcludedMetaKey($metaKey)) {
            return false;
        }
        if (!$this->includePrivateMetaKeys && str_starts_with($metaKey, '_')) {
            return false;
        }

        if (!empty($this->metaIncludePatterns) && !$this->matchesAnyPattern($metaKey, $this->metaIncludePatterns)) {
            return false;
        }
        if (!empty($this->metaExcludePatterns) && $this->matchesAnyPattern($metaKey, $this->metaExcludePatterns)) {
            return false;
        }
        return true;
    }

    private function isHardExcludedMetaKey(string $metaKey): bool {
        $metaKey = strtolower(trim($metaKey));
        if ($metaKey === '' || $metaKey === '_thumbnail_id') {
            return false;
        }

        if ((bool)preg_match('/^_?field_[a-z0-9]+$/i', $metaKey)) {
            return true;
        }

        if ($this->isKnownTechnicalMetaKey($metaKey)) {
            return true;
        }

        if (str_starts_with($metaKey, '_') && $this->isPrivateAcfReferenceMetaKey($metaKey)) {
            return true;
        }

        return false;
    }

    private function isPrivateAcfReferenceMetaKey(string $metaKey): bool {
        $metaKey = strtolower(trim($metaKey));
        if ($metaKey === '' || !str_starts_with($metaKey, '_')) {
            return false;
        }

        $publicMetaKey = ltrim($metaKey, '_');
        if ($publicMetaKey === '' || $publicMetaKey === $metaKey) {
            return false;
        }

        foreach (['postmeta:', 'termmeta:'] as $prefix) {
            $publicSourceId = $prefix . $publicMetaKey;
            if (isset($this->sourcePropertyMap[$publicSourceId])) {
                return true;
            }
            if ($this->isCompositeMemberSourceId($publicSourceId)) {
                return true;
            }
        }

        return false;
    }

    private function isKnownTechnicalMetaKey(string $metaKey): bool {
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

    private function buildPropertyDefaults(array $row): array {
        $rawDefaults = $this->rowValue($row, ['default_values', 'defaults', 'default'], []);
        if (is_string($rawDefaults) && SysClass::ee_isValidJson($rawDefaults)) {
            $decodedDefaults = json_decode($rawDefaults, true);
            if (is_array($decodedDefaults)) {
                $rawDefaults = $decodedDefaults;
            }
        }

        $defaultFields = [];
        if (is_array($rawDefaults)) {
            foreach ($rawDefaults as $item) {
                if (is_array($item)) {
                    $defaultFields[] = [
                        'type' => (string)($item['type'] ?? 'text'),
                        'label' => (string)($item['label'] ?? ''),
                        'title' => (string)($item['title'] ?? ''),
                        'default' => $item['default'] ?? ($item['value'] ?? ''),
                        'multiple' => $this->toBool($item['multiple'] ?? false, false) ? 1 : 0,
                        'required' => $this->toBool($item['required'] ?? false, false) ? 1 : 0,
                    ];
                } elseif (is_string($item) && trim($item) !== '') {
                    $defaultFields[] = [
                        'type' => trim($item),
                        'label' => '',
                        'title' => '',
                        'default' => '',
                        'multiple' => 0,
                        'required' => 0,
                    ];
                }
            }
        }

        if (!empty($defaultFields)) {
            return $defaultFields;
        }

        $rawTypeFields = $this->rowValue($row, ['type_fields', 'fields'], ['text']);
        if (is_string($rawTypeFields) && SysClass::ee_isValidJson($rawTypeFields)) {
            $decodedTypeFields = json_decode($rawTypeFields, true);
            if (is_array($decodedTypeFields)) {
                $rawTypeFields = $decodedTypeFields;
            }
        }
        if (!is_array($rawTypeFields)) {
            $rawTypeFields = [is_scalar($rawTypeFields) ? (string)$rawTypeFields : 'text'];
        }

        foreach ($rawTypeFields as $fieldType) {
            $fieldType = trim((string)$fieldType);
            if ($fieldType === '') {
                continue;
            }
            $defaultFields[] = [
                'type' => $fieldType,
                'label' => '',
                'title' => '',
                'default' => '',
                'multiple' => $this->toBool($this->rowValue($row, ['is_multiple', 'multiple'], false), false) ? 1 : 0,
                'required' => $this->toBool($this->rowValue($row, ['is_required', 'required'], false), false) ? 1 : 0,
            ];
        }

        if (empty($defaultFields)) {
            $defaultFields[] = [
                'type' => 'text',
                'label' => '',
                'title' => '',
                'default' => '',
                'multiple' => $this->toBool($this->rowValue($row, ['is_multiple', 'multiple'], false), false) ? 1 : 0,
                'required' => $this->toBool($this->rowValue($row, ['is_required', 'required'], false), false) ? 1 : 0,
            ];
        }

        return $defaultFields;
    }

    private function normalizePropertyValuesPayload(int $propertyId, mixed $payloadValues): array {
        $templateFields = $this->getPropertyDefaultFieldsTemplate($propertyId);
        if (empty($templateFields)) {
            $templateFields = [[
                'type' => 'text',
                'label' => '',
                'title' => '',
                'default' => '',
                'multiple' => 0,
                'required' => 0,
            ]];
        }

        if (is_string($payloadValues)) {
            $trimmed = trim($payloadValues);
            if ($trimmed === '') {
                $payloadValues = null;
            } elseif (SysClass::ee_isValidJson($trimmed)) {
                $decodedPayload = json_decode($trimmed, true);
                $payloadValues = is_array($decodedPayload) ? $decodedPayload : $trimmed;
            } else {
                $payloadValues = $trimmed;
            }
        }

        if ($payloadValues === null || $payloadValues === '') {
            $normalized = [];
            foreach ($templateFields as $templateField) {
                $normalized[] = $this->normalizePropertyFieldValue([], $templateField);
            }
            return $normalized;
        }

        $structuredCompositePayload = $this->normalizeStructuredCompositePayload($payloadValues, $templateFields);
        if (is_array($structuredCompositePayload)) {
            $payloadValues = $structuredCompositePayload;
        }

        if (!is_array($payloadValues)) {
            $payloadValues = [['value' => $payloadValues]];
        }

        if (!$this->isSequentialArray($payloadValues)) {
            if (isset($payloadValues['type']) || isset($payloadValues['label']) || isset($payloadValues['value']) || isset($payloadValues['title'])) {
                $payloadValues = [$payloadValues];
            } else {
                $payloadValues = $this->mapAssociativePayloadToTemplateFields($payloadValues, $templateFields);
            }
        }

        $normalizedFields = [];
        foreach ($payloadValues as $index => $item) {
            if (is_string($item) && SysClass::ee_isValidJson($item)) {
                $decodedItem = json_decode($item, true);
                if (is_array($decodedItem)) {
                    $item = $decodedItem;
                }
            }
            if (!is_array($item)) {
                $item = ['value' => $item];
            }

            $templateField = $templateFields[$index] ?? $templateFields[0];
            $normalizedFields[] = $this->normalizePropertyFieldValue($item, $templateField);
        }

        if (empty($normalizedFields)) {
            foreach ($templateFields as $templateField) {
                $normalizedFields[] = $this->normalizePropertyFieldValue([], $templateField);
            }
        }

        return $normalizedFields;
    }

    private function normalizeStructuredCompositePayload(mixed $payloadValues, array $templateFields): ?array {
        if (count($templateFields) <= 1) {
            return null;
        }

        $candidate = $payloadValues;
        if (is_string($candidate)) {
            $trimmed = trim($candidate);
            if ($trimmed === '' || !SysClass::ee_isValidJson($trimmed)) {
                return null;
            }
            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded)) {
                return null;
            }
            $candidate = $decoded;
        }

        if (!is_array($candidate)) {
            return null;
        }

        if ($this->isNormalizedPropertyFieldList($candidate, $templateFields)) {
            return $candidate;
        }

        if ($this->isSequentialArray($candidate) && count($candidate) === 1 && is_array($candidate[0])) {
            $singleRow = $candidate[0];
            if (array_key_exists('value', $singleRow) && is_array($singleRow['value'])) {
                $candidate = $singleRow['value'];
            } elseif (array_key_exists('default', $singleRow) && is_array($singleRow['default'])) {
                $candidate = $singleRow['default'];
            }
        }

        if (is_array($candidate) && !$this->isSequentialArray($candidate)) {
            return $this->mapAssociativePayloadToTemplateFields($candidate, $templateFields);
        }

        if (!is_array($candidate) || !$this->isSequentialArray($candidate) || $candidate === []) {
            return null;
        }

        foreach ($candidate as $row) {
            if (!is_array($row) || $this->isSequentialArray($row)) {
                return null;
            }
        }

        return $this->mapAssociativeRowListToTemplateFields($candidate, $templateFields);
    }

    private function isNormalizedPropertyFieldList(array $candidate, array $templateFields): bool {
        if (!$this->isSequentialArray($candidate) || empty($candidate)) {
            return false;
        }
        if (count($candidate) !== count($templateFields)) {
            return false;
        }

        foreach ($candidate as $index => $fieldRow) {
            if (!is_array($fieldRow) || $this->isSequentialArray($fieldRow)) {
                return false;
            }

            $fieldType = strtolower(trim((string)($fieldRow['type'] ?? '')));
            $templateType = strtolower(trim((string)($templateFields[$index]['type'] ?? '')));
            if ($fieldType === '' || $templateType === '' || $fieldType !== $templateType) {
                return false;
            }

            if (
                !array_key_exists('value', $fieldRow)
                && !array_key_exists('default', $fieldRow)
                && !array_key_exists('label', $fieldRow)
                && !array_key_exists('title', $fieldRow)
            ) {
                return false;
            }
        }

        return true;
    }

    private function getPropertyDefaultFieldsTemplate(int $propertyId): array {
        if ($propertyId <= 0) {
            return [];
        }
        if (array_key_exists($propertyId, $this->propertyDefaultFieldsTemplateCache)) {
            $cached = $this->propertyDefaultFieldsTemplateCache[$propertyId];
            return is_array($cached) ? $cached : [];
        }

        $propertyRow = SafeMySQL::gi()->getRow(
            'SELECT default_values, is_multiple FROM ?n WHERE property_id = ?i LIMIT 1',
            Constants::PROPERTIES_TABLE,
            $propertyId
        );
        $rawDefaults = is_array($propertyRow) ? (string) ($propertyRow['default_values'] ?? '') : '';
        $propertyIsMultiple = is_array($propertyRow) && !empty($propertyRow['is_multiple']);
        if ($rawDefaults === '' || !SysClass::ee_isValidJson($rawDefaults)) {
            $this->propertyDefaultFieldsTemplateCache[$propertyId] = [];
            return [];
        }

        $decodedDefaults = json_decode($rawDefaults, true);
        if (!is_array($decodedDefaults)) {
            return [];
        }

        $result = [];
        foreach ($decodedDefaults as $defaultField) {
            if (!is_array($defaultField)) {
                continue;
            }
            $result[] = [
                'type' => strtolower(trim((string)($defaultField['type'] ?? 'text'))),
                'label' => $defaultField['label'] ?? '',
                'title' => $defaultField['title'] ?? '',
                'default' => $defaultField['default'] ?? '',
                'multiple' => ($propertyIsMultiple || !empty($defaultField['multiple'])) ? 1 : 0,
                'required' => !empty($defaultField['required']) ? 1 : 0,
            ];
        }

        $this->propertyDefaultFieldsTemplateCache[$propertyId] = $result;
        return $result;
    }

    private function getCachedPropertyValueRow(string $entityType, int $entityId, int $propertyId, int $setId): array {
        if ($entityId <= 0 || $propertyId <= 0 || $setId <= 0) {
            return [];
        }
        $cacheKey = $this->buildPropertyValueCacheKey($entityType, $entityId, $propertyId, $setId);
        if (array_key_exists($cacheKey, $this->propertyValueRowCache)) {
            $cached = $this->propertyValueRowCache[$cacheKey];
            return is_array($cached) ? $cached : [];
        }

        $row = SafeMySQL::gi()->getRow(
            'SELECT value_id, property_values FROM ?n WHERE entity_id = ?i AND property_id = ?i AND entity_type = ?s AND set_id = ?i AND language_code = ?s LIMIT 1',
            Constants::PROPERTY_VALUES_TABLE,
            $entityId,
            $propertyId,
            $entityType,
            $setId,
            $this->languageCode
        );
        $row = is_array($row) ? $row : [];
        $this->propertyValueRowCache[$cacheKey] = $row;
        return $row;
    }

    private function setCachedPropertyValueRow(string $entityType, int $entityId, int $propertyId, int $setId, array $row): void {
        if ($entityId <= 0 || $propertyId <= 0 || $setId <= 0) {
            return;
        }
        $cacheKey = $this->buildPropertyValueCacheKey($entityType, $entityId, $propertyId, $setId);
        $this->propertyValueRowCache[$cacheKey] = $row;
    }

    private function buildPropertyValueCacheKey(string $entityType, int $entityId, int $propertyId, int $setId): string {
        return $entityType . '|' . $entityId . '|' . $propertyId . '|' . $setId . '|' . $this->languageCode;
    }

    private function mapAssociativePayloadToTemplateFields(array $payloadValues, array $templateFields): array {
        $result = [];
        $payloadLower = [];
        foreach ($payloadValues as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $payloadLower[strtolower(trim($key))] = $value;
        }

        foreach ($templateFields as $templateField) {
            $fieldType = strtolower(trim((string)($templateField['type'] ?? 'text')));
            $candidateKeys = match ($fieldType) {
                'number' => ['number', 'num', 'count', 'value', 'zoom', 'price', 'pricec'],
                'image' => ['image', 'img', 'url', 'src', 'value', 'photos', 'gallery'],
                'file' => ['file', 'path', 'url', 'value'],
                'phone' => ['phone', 'tel', 'telephone', 'number', 'value'],
                'checkbox', 'radio' => ['value', 'checked', 'selected'],
                'textarea' => ['textarea', 'text', 'content', 'value', 'comment', 'description'],
                'select' => ['selected', 'option', 'value'],
                default => ['text', 'value', 'content', 'description', 'title', 'comment', 'coords', 'address', 'name'],
            };

            $resolved = null;
            foreach ($candidateKeys as $candidateKey) {
                if (array_key_exists($candidateKey, $payloadLower)) {
                    $resolved = $payloadLower[$candidateKey];
                    break;
                }
            }

            if ($resolved === null) {
                $label = $templateField['label'] ?? '';
                if (is_string($label)) {
                    $labelKey = strtolower(trim($label));
                    if ($labelKey !== '' && array_key_exists($labelKey, $payloadLower)) {
                        $resolved = $payloadLower[$labelKey];
                    }
                }
            }

            if ($resolved === null) {
                $resolved = $templateField['default'] ?? '';
            }

            $result[] = ['value' => $resolved];
        }

        if (empty($result) && !empty($payloadValues)) {
            $result[] = ['value' => reset($payloadValues)];
        }

        return $result;
    }

    private function mapAssociativeRowListToTemplateFields(array $payloadRows, array $templateFields): array {
        $result = [];
        foreach ($templateFields as $templateField) {
            $fieldValues = [];
            foreach ($payloadRows as $payloadRow) {
                $mappedRow = $this->mapAssociativePayloadToTemplateFields($payloadRow, [$templateField]);
                $fieldValues[] = $mappedRow[0]['value'] ?? ($templateField['default'] ?? '');
            }
            $result[] = [
                'value' => $fieldValues,
                'multiple' => 1,
            ];
        }

        return $result;
    }

    private function normalizePropertyFieldValue(array $payloadField, array $templateField): array {
        $fieldType = strtolower(trim((string)($payloadField['type'] ?? $templateField['type'] ?? 'text')));
        if ($fieldType === '') {
            $fieldType = 'text';
        }

        $fieldValue = $payloadField['value'] ?? ($payloadField['default'] ?? ($templateField['default'] ?? ''));
        $fieldLabel = $payloadField['label'] ?? ($templateField['label'] ?? '');
        $fieldTitle = $payloadField['title'] ?? ($templateField['title'] ?? '');
        $fieldMultiple = !empty($payloadField['multiple']) || !empty($templateField['multiple']) ? 1 : 0;
        $fieldRequired = !empty($payloadField['required']) || !empty($templateField['required']) ? 1 : 0;

        if ($fieldType === 'checkbox' || $fieldType === 'radio') {
            if (!is_array($fieldLabel)) {
                $labelText = is_scalar($fieldLabel) ? trim((string)$fieldLabel) : '';
                $fieldLabel = $labelText !== '' ? [$labelText] : ['Р—РЅР°С‡РµРЅРёРµ'];
            }
            if (!is_array($fieldValue) && $fieldMultiple) {
                $fieldValue = [$fieldValue];
            }
        } else {
            if (!is_scalar($fieldLabel)) {
                $fieldLabel = '';
            }
            if (!$fieldMultiple && is_array($fieldValue)) {
                $fieldValue = reset($fieldValue);
            }
        }

        if (in_array($fieldType, ['text', 'textarea'], true)) {
            $fieldValue = $this->rewriteImportedTextValue($fieldValue);
        }

        $fieldValue = $this->normalizeTypedPropertyFieldValue($fieldType, $fieldValue);

        if (!is_scalar($fieldTitle)) {
            $fieldTitle = '';
        }

        return [
            'type' => $fieldType,
            'value' => $fieldValue,
            'label' => $fieldLabel,
            'multiple' => $fieldMultiple,
            'required' => $fieldRequired,
            'title' => (string)$fieldTitle,
        ];
    }

    private function isSequentialArray(array $value): bool {
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function normalizeTypedPropertyFieldValue(string $fieldType, mixed $fieldValue): mixed {
        if (is_array($fieldValue)) {
            foreach ($fieldValue as $index => $item) {
                $fieldValue[$index] = $this->normalizeTypedPropertyFieldValue($fieldType, $item);
            }
            return $fieldValue;
        }

        if (!is_scalar($fieldValue)) {
            return $fieldValue;
        }

        $value = trim((string)$fieldValue);
        if ($value === '') {
            return '';
        }

        if ($fieldType === 'date') {
            if ((bool)preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $matches)) {
                return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
            }
            if ((bool)preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $matches)) {
                return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            }
            return $value;
        }

        if ($fieldType === 'time' && (bool)preg_match('/^(\d{2})(\d{2})$/', $value, $matches)) {
            return $matches[1] . ':' . $matches[2];
        }

        return $fieldValue;
    }

    private function prepareImportedRichText(string $value): string {
        $normalized = self::normalizeImportedRichText($value);
        return $this->rewriteImportedRichTextLinks($normalized);
    }

    private function rewriteImportedTextValue(mixed $value): mixed {
        if (is_array($value)) {
            foreach ($value as $index => $item) {
                $value[$index] = $this->rewriteImportedTextValue($item);
            }
            return $value;
        }

        if (!is_scalar($value)) {
            return $value;
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return $value;
        }

        $normalizedBareDonorValue = $this->normalizeBareDonorReferenceValue($stringValue);
        if ($normalizedBareDonorValue !== null) {
            return $normalizedBareDonorValue;
        }

        if (preg_match('~<[^>]+>~u', $stringValue) === 1) {
            return $this->rewriteImportedRichTextLinks($stringValue);
        }

        if (str_contains($stringValue, '://') || str_starts_with($stringValue, '/')) {
            return $this->rewriteAbsoluteDonorUrlsInText($stringValue);
        }

        return $value;
    }

    private function normalizeBareDonorReferenceValue(string $value): ?string {
        if (!$this->rewriteDonorLinks) {
            return null;
        }

        $donorBaseUrl = $this->getDonorBaseUrl();
        if ($donorBaseUrl === '') {
            return null;
        }

        $donorHost = strtolower(trim((string) parse_url($donorBaseUrl, PHP_URL_HOST)));
        if ($donorHost === '') {
            return null;
        }

        $normalizedValue = strtolower(trim($value));
        $normalizedValue = preg_replace('~^https?://~iu', '', $normalizedValue) ?? $normalizedValue;
        $normalizedValue = preg_replace('~^//~u', '', $normalizedValue) ?? $normalizedValue;
        $normalizedValue = rtrim($normalizedValue, '/');

        $allowedMatches = [
            $donorHost,
            'www.' . $donorHost,
        ];

        return in_array($normalizedValue, $allowedMatches, true) ? '' : null;
    }

    private function rewriteImportedRichTextLinks(string $html): string {
        if (!$this->rewriteDonorLinks) {
            return $html;
        }

        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $rewritten = $html;
        if (class_exists(\DOMDocument::class) && preg_match('~<[^>]+>~u', $html) === 1) {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $previousUseInternalErrors = libxml_use_internal_errors(true);
            try {
                $loaded = $dom->loadHTML(
                    '<?xml encoding="utf-8" ?><div data-ee-import-root="1">' . $html . '</div>',
                    LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
                );
                if ($loaded) {
                    $xpath = new \DOMXPath($dom);
                    $nodes = $xpath->query('//*[@data-ee-import-root="1"]//*');
                    if ($nodes instanceof \DOMNodeList) {
                        foreach ($nodes as $node) {
                            if (!$node instanceof \DOMElement) {
                                continue;
                            }
                            foreach (['href', 'src', 'poster', 'action', 'data-href', 'data-src'] as $attributeName) {
                                if (!$node->hasAttribute($attributeName)) {
                                    continue;
                                }
                                $attributeValue = trim((string) $node->getAttribute($attributeName));
                                if ($attributeValue === '') {
                                    continue;
                                }
                                $rewrittenValue = $this->rewriteDonorUrl($attributeValue);
                                if ($rewrittenValue !== '' && $rewrittenValue !== $attributeValue) {
                                    $node->setAttribute($attributeName, $rewrittenValue);
                                }
                            }
                        }
                    }

                    $rootNodes = $xpath->query('//*[@data-ee-import-root="1"]');
                    $root = ($rootNodes instanceof \DOMNodeList && $rootNodes->length > 0) ? $rootNodes->item(0) : null;
                    if ($root instanceof \DOMElement) {
                        $chunks = [];
                        foreach ($root->childNodes as $childNode) {
                            $chunks[] = $dom->saveHTML($childNode);
                        }
                        $rewritten = trim(implode('', $chunks));
                    }
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previousUseInternalErrors);
            }
        }

        return $this->rewriteAbsoluteDonorUrlsInText($rewritten);
    }

    private function rewriteAbsoluteDonorUrlsInText(string $text): string {
        if (!$this->rewriteDonorLinks || $text === '') {
            return $text;
        }

        $donorBaseUrl = $this->getDonorBaseUrl();
        if ($donorBaseUrl === '') {
            return $text;
        }

        $donorHost = strtolower(trim((string) parse_url($donorBaseUrl, PHP_URL_HOST)));
        if ($donorHost === '') {
            return $text;
        }

        $escapedHost = preg_quote($donorHost, '~');
        $text = preg_replace_callback(
            '~https?://(?:www\.)?' . $escapedHost . '(?::\d+)?(?:/[^\s"\'<>()]*)?~iu',
            fn(array $matches): string => $this->rewriteDonorUrl((string) ($matches[0] ?? '')),
            $text
        ) ?? $text;

        $text = preg_replace_callback(
            '~//(?:www\.)?' . $escapedHost . '(?::\d+)?(?:/[^\s"\'<>()]*)?~iu',
            fn(array $matches): string => $this->rewriteDonorUrl((string) ($matches[0] ?? '')),
            $text
        ) ?? $text;

        return $text;
    }

    private function rewriteDonorUrl(string $url): string {
        $url = trim($url);
        if ($url === '' || !$this->rewriteDonorLinks) {
            return $url;
        }

        if (str_starts_with($url, '#') || str_starts_with(strtolower($url), 'mailto:') || str_starts_with(strtolower($url), 'tel:') || str_starts_with(strtolower($url), 'javascript:')) {
            return $url;
        }

        $sourcePath = '';
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        if (str_starts_with($url, '/')) {
            $sourcePath = $this->normalizeImportedSourcePath($url);
        } else {
            $donorBaseUrl = $this->getDonorBaseUrl();
            if ($donorBaseUrl === '') {
                return $url;
            }

            $urlHost = strtolower(trim((string) parse_url($url, PHP_URL_HOST)));
            $donorHost = strtolower(trim((string) parse_url($donorBaseUrl, PHP_URL_HOST)));
            if ($urlHost === '' || $donorHost === '' || !in_array($urlHost, [$donorHost, 'www.' . $donorHost], true)) {
                return $url;
            }
            $sourcePath = $this->normalizeImportedSourcePath($url);
        }

        if ($sourcePath === '') {
            return $url;
        }

        $localEntityUrl = $this->resolveLocalUrlForSourcePath($sourcePath);
        if ($localEntityUrl !== '') {
            return $localEntityUrl;
        }

        return $this->buildLocalUrlFromPath($sourcePath);
    }

    private function resolveLocalUrlForSourcePath(string $sourcePath): string {
        $sourcePath = $this->normalizeImportedSourcePath($sourcePath);
        if ($sourcePath === '') {
            return '';
        }

        $pageId = $this->getMappedId('page_source_path', $sourcePath);
        if ($pageId > 0) {
            return EntityPublicUrlService::buildEntityUrl('page', $pageId, $this->languageCode);
        }

        $categoryId = $this->getMappedId('category_source_path', $sourcePath);
        if ($categoryId > 0) {
            return EntityPublicUrlService::buildEntityUrl('category', $categoryId, $this->languageCode);
        }

        return '';
    }

    private function buildLocalUrlFromPath(string $path): string {
        $path = $this->normalizeImportedSourcePath($path);
        if ($path === '') {
            return rtrim((string) ENV_URL_SITE, '/') . '/';
        }

        return rtrim((string) ENV_URL_SITE, '/') . $path;
    }

    private function resolveImportedRoutePath(array $row): string {
        return $this->normalizeImportedSourcePath($this->rowString($row, ['source_path', 'path', 'route_path'], ''));
    }

    private function saveImportedSourcePath(string $entityType, array $row, int $entityId): void {
        if ($entityId <= 0) {
            return;
        }

        $sourcePath = $this->resolveImportedRoutePath($row);
        if ($sourcePath === '') {
            return;
        }

        $mapType = $entityType === 'category' ? 'category_source_path' : 'page_source_path';
        $this->saveMappedId($mapType, $sourcePath, $entityId);
    }

    private function normalizeImportedSourcePath(string $value): string {
        return EntitySlugService::normalizeRoutePath($value);
    }

    private function getDonorBaseUrl(): string {
        if ($this->donorBaseUrl !== '') {
            return $this->donorBaseUrl;
        }

        $this->donorBaseUrl = $this->normalizeBaseUrl((string) ($this->state['site_url'] ?? ''));
        return $this->donorBaseUrl;
    }

    private function normalizeBaseUrl(string $url): string {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower(trim((string) ($parts['scheme'] ?? 'https')));
        $host = strtolower(trim((string) ($parts['host'] ?? '')));
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        return $host !== '' ? ($scheme . '://' . $host . $port) : '';
    }

    private function rewriteImportedDonorLinksBackfill(): array {
        if (!$this->rewriteDonorLinks || $this->getDonorBaseUrl() === '') {
            return ['pages_updated' => 0, 'categories_updated' => 0, 'property_values_updated' => 0];
        }

        $donorHost = trim((string) parse_url($this->getDonorBaseUrl(), PHP_URL_HOST));
        if ($donorHost === '') {
            return ['pages_updated' => 0, 'categories_updated' => 0, 'property_values_updated' => 0];
        }

        return [
            'pages_updated' => $this->rewriteRichTextColumnsInTable(Constants::PAGES_TABLE, 'page_id', ['description', 'short_description'], $donorHost),
            'categories_updated' => $this->rewriteRichTextColumnsInTable(Constants::CATEGORIES_TABLE, 'category_id', ['description', 'short_description'], $donorHost),
            'property_values_updated' => $this->rewritePropertyValuePayloadsByDonorHost($donorHost),
        ];
    }

    private function rewriteRichTextColumnsInTable(string $tableName, string $primaryKey, array $columns, string $donorHost): int {
        $updated = 0;
        $rows = SafeMySQL::gi()->getAll(
            'SELECT * FROM ?n WHERE ' . implode(' OR ', array_map(static fn(string $column): string => "`{$column}` LIKE ?s", $columns)),
            $tableName,
            ...array_fill(0, count($columns), '%' . $donorHost . '%')
        );
        if (!is_array($rows)) {
            return 0;
        }

        foreach ($rows as $row) {
            $primaryId = (int) ($row[$primaryKey] ?? 0);
            if ($primaryId <= 0) {
                continue;
            }

            $updateData = [];
            foreach ($columns as $column) {
                $currentValue = (string) ($row[$column] ?? '');
                if ($currentValue === '' || !str_contains($currentValue, $donorHost)) {
                    continue;
                }

                $rewrittenValue = $this->rewriteImportedTextValue($currentValue);
                if (is_string($rewrittenValue) && $rewrittenValue !== $currentValue) {
                    $updateData[$column] = $rewrittenValue;
                }
            }

            if ($updateData === []) {
                continue;
            }

            SafeMySQL::gi()->query(
                'UPDATE ?n SET ?u WHERE `' . $primaryKey . '` = ?i',
                $tableName,
                $updateData,
                $primaryId
            );
            $updated++;
        }

        return $updated;
    }

    private function rewritePropertyValuePayloadsByDonorHost(string $donorHost): int {
        $updated = 0;
        $rows = SafeMySQL::gi()->getAll(
            'SELECT value_id, property_values FROM ?n WHERE property_values LIKE ?s',
            Constants::PROPERTY_VALUES_TABLE,
            '%' . $donorHost . '%'
        );
        if (!is_array($rows)) {
            return 0;
        }

        foreach ($rows as $row) {
            $valueId = (int) ($row['value_id'] ?? 0);
            $rawPayload = (string) ($row['property_values'] ?? '');
            if ($valueId <= 0 || $rawPayload === '' || !SysClass::ee_isValidJson($rawPayload)) {
                continue;
            }

            $decodedPayload = json_decode($rawPayload, true);
            if (!is_array($decodedPayload)) {
                continue;
            }

            $rewrittenPayload = $this->rewritePayloadStrings($decodedPayload);
            $rewrittenJson = json_encode($rewrittenPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($rewrittenJson) || $rewrittenJson === '' || $rewrittenJson === $rawPayload) {
                continue;
            }

            SafeMySQL::gi()->query(
                'UPDATE ?n SET property_values = ?s, updated_at = NOW() WHERE value_id = ?i',
                Constants::PROPERTY_VALUES_TABLE,
                $rewrittenJson,
                $valueId
            );
            $updated++;
        }

        return $updated;
    }

    private function rewritePayloadStrings(mixed $value): mixed {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->rewritePayloadStrings($item);
            }
            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        return $this->rewriteImportedTextValue($value);
    }

    private function rowValue(array $row, array $keys, mixed $default = null): mixed {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }
        return $default;
    }

    private function rowString(array $row, array $keys, string $default = ''): string {
        $value = $this->rowValue($row, $keys, $default);
        if ($value === null) {
            return $default;
        }
        if (is_scalar($value)) {
            return trim((string)$value);
        }
        return $default;
    }

    public static function normalizeImportedRichText(string $value): string {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        if ($value === '') {
            return '';
        }

        if (preg_match('~<[^>]+>~u', $value) === 1) {
            if (preg_match('~<(p|br)\b~iu', $value) !== 1) {
                $value = self::normalizeImportedMixedHtml($value);
            }
            return self::encodeUnsupportedUtf8mb4Chars($value);
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

        if ($paragraphs === []) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $escaped = htmlspecialchars($decoded, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return self::encodeUnsupportedUtf8mb4Chars('<p>' . $escaped . '</p>');
        }

        return self::encodeUnsupportedUtf8mb4Chars(implode("\n", $paragraphs));
    }

    private static function normalizeImportedMixedHtml(string $value): string {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        if ($value === '' || !class_exists(\DOMDocument::class)) {
            $value = preg_replace("/\n{2,}/u", "<br>\n<br>\n", $value) ?? $value;
            return preg_replace("/(?<!>)\n(?!<)/u", "<br>\n", $value) ?? $value;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadHTML(
                '<?xml encoding="utf-8" ?><div data-ee-import-root="1">' . $value . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );
            if (!$loaded) {
                $value = preg_replace("/\n{2,}/u", "<br>\n<br>\n", $value) ?? $value;
                return preg_replace("/(?<!>)\n(?!<)/u", "<br>\n", $value) ?? $value;
            }

            $root = null;
            $xpath = new \DOMXPath($dom);
            $rootNodes = $xpath->query('//*[@data-ee-import-root="1"]');
            if ($rootNodes instanceof \DOMNodeList && $rootNodes->length > 0) {
                $candidate = $rootNodes->item(0);
                if ($candidate instanceof \DOMElement) {
                    $root = $candidate;
                }
            }

            if (!$root instanceof \DOMElement) {
                $value = preg_replace("/\n{2,}/u", "<br>\n<br>\n", $value) ?? $value;
                return preg_replace("/(?<!>)\n(?!<)/u", "<br>\n", $value) ?? $value;
            }

            $output = self::normalizeImportedDomNodeSequence($dom, iterator_to_array($root->childNodes));
            $output = array_values(array_filter(array_map(static function (string $item): string {
                return trim($item);
            }, $output), static function (string $item): bool {
                return $item !== '';
            }));

            if ($output === []) {
                $value = preg_replace("/\n{2,}/u", "<br>\n<br>\n", $value) ?? $value;
                return preg_replace("/(?<!>)\n(?!<)/u", "<br>\n", $value) ?? $value;
            }

            return implode("\n", $output);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }
    }

    private static function flushImportedHtmlInlineBuffer(array &$inlineBuffer): array {
        $html = trim(implode('', $inlineBuffer));
        $inlineBuffer = [];
        if ($html === '') {
            return [];
        }

        $blocks = preg_split("/\n{2,}/u", $html) ?: [$html];
        $paragraphs = [];
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            $block = preg_replace("/\n/u", "<br>\n", $block) ?? $block;
            $paragraphs[] = '<p>' . $block . '</p>';
        }

        return $paragraphs;
    }

    private static function normalizeImportedDomNodeSequence(\DOMDocument $dom, iterable $nodes): array {
        $output = [];
        $inlineBuffer = [];

        foreach ($nodes as $childNode) {
            if ($childNode instanceof \DOMText) {
                $inlineBuffer[] = htmlspecialchars($childNode->wholeText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }

            if (!$childNode instanceof \DOMElement) {
                continue;
            }

            $tagName = strtolower($childNode->tagName);
            if ($tagName === 'br') {
                $inlineBuffer[] = '<br>';
                continue;
            }

            $serializedNode = trim((string)$dom->saveHTML($childNode));
            if ($serializedNode === '') {
                continue;
            }

            if (self::isImportedHtmlContainerElement($tagName)) {
                $output = array_merge($output, self::flushImportedHtmlInlineBuffer($inlineBuffer));
                $output[] = self::normalizeImportedBlockContainer($dom, $childNode);
                continue;
            }

            if (self::isImportedHtmlBlockElement($tagName)) {
                $output = array_merge($output, self::flushImportedHtmlInlineBuffer($inlineBuffer));
                $output[] = $serializedNode;
                continue;
            }

            $inlineBuffer[] = $serializedNode;
        }

        return array_merge($output, self::flushImportedHtmlInlineBuffer($inlineBuffer));
    }

    private static function normalizeImportedBlockContainer(\DOMDocument $dom, \DOMElement $element): string {
        $tagName = strtolower($element->tagName);
        $serialized = trim((string)$dom->saveHTML($element));
        if (!self::isImportedHtmlContainerElement($tagName) || $serialized === '' || preg_match('~<(p|br)\b~iu', $serialized) === 1) {
            return $serialized;
        }

        $innerOutput = self::normalizeImportedDomNodeSequence($dom, iterator_to_array($element->childNodes));
        $innerOutput = array_values(array_filter(array_map(static function (string $item): string {
            return trim($item);
        }, $innerOutput), static function (string $item): bool {
            return $item !== '';
        }));
        if ($innerOutput === []) {
            return $serialized;
        }

        $openTag = '<' . $tagName . self::serializeImportedHtmlAttributes($element) . '>';
        $closeTag = '</' . $tagName . '>';
        return $openTag . "\n" . implode("\n", $innerOutput) . "\n" . $closeTag;
    }

    private static function serializeImportedHtmlAttributes(\DOMElement $element): string {
        if (!$element->hasAttributes()) {
            return '';
        }

        $attributes = [];
        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }
            $attributes[] = ' ' . $attribute->name . '="' .
                htmlspecialchars($attribute->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return implode('', $attributes);
    }

    private static function isImportedHtmlBlockElement(string $tagName): bool {
        static $blockTags = [
            'address',
            'article',
            'aside',
            'blockquote',
            'details',
            'div',
            'dl',
            'dt',
            'dd',
            'figure',
            'figcaption',
            'footer',
            'form',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'header',
            'hr',
            'li',
            'main',
            'nav',
            'ol',
            'p',
            'pre',
            'section',
            'table',
            'tbody',
            'td',
            'tfoot',
            'th',
            'thead',
            'tr',
            'ul',
        ];

        return in_array(strtolower(trim($tagName)), $blockTags, true);
    }

    private static function isImportedHtmlContainerElement(string $tagName): bool {
        static $containerTags = [
            'article',
            'blockquote',
            'div',
            'section',
        ];

        return in_array(strtolower(trim($tagName)), $containerTags, true);
    }

    private static function encodeUnsupportedUtf8mb4Chars(string $value): string {
        $encoded = preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', static function (array $matches): string {
            $char = (string)($matches[0] ?? '');
            if ($char === '') {
                return '';
            }

            if (function_exists('mb_ord')) {
                $codePoint = (int)mb_ord($char, 'UTF-8');
            } else {
                $packed = @unpack('N', (string)mb_convert_encoding($char, 'UCS-4BE', 'UTF-8'));
                $codePoint = (int)($packed[1] ?? 0);
            }

            return $codePoint > 0 ? '&#' . $codePoint . ';' : '';
        }, $value);

        return is_string($encoded) ? $encoded : $value;
    }

    private function normalizeSourceId(mixed $value): string {
        if ($value === null) {
            return '';
        }
        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return trim((string)$value);
        }
        return '';
    }

    private function mergeRecommendedPropertyMappings(array $sourcePropertyMap): array {
        $recommendedMap = [
            'postmeta:zametki' => ['id' => 0, 'name' => 'Внутренний комментарий менеджера'],
            'postmeta:published_to' => ['id' => 0, 'name' => 'Размещение активно до'],
        ];

        foreach ($recommendedMap as $sourceId => $mapping) {
            if (isset($sourcePropertyMap[$sourceId])) {
                continue;
            }
            $sourcePropertyMap[$sourceId] = $mapping;
        }

        return $sourcePropertyMap;
    }

    private function sourceKey(): string {
        $sourceKey = trim((string)($this->state['source_key'] ?? ''));
        if ($sourceKey === '') {
            $sourceKey = 'default';
        }
        return substr($sourceKey, 0, 128);
    }

    private function getMappedId(string $mapType, mixed $sourceId): int {
        $source = $this->normalizeSourceId($sourceId);
        if ($source === '') {
            return 0;
        }
        $configuredMapId = $this->getConfiguredMapLocalId($mapType, $source);
        if ($configuredMapId > 0) {
            return $configuredMapId;
        }
        $source = substr($source, 0, 191);
        $cacheKey = $mapType . '|' . $source;
        if (array_key_exists($cacheKey, $this->mappedIdCache)) {
            return (int) $this->mappedIdCache[$cacheKey];
        }

        $localId = (int)SafeMySQL::gi()->getOne(
            'SELECT local_id FROM ?n WHERE job_id = ?i AND source_key = ?s AND map_type = ?s AND source_id = ?s LIMIT 1',
            $this->mapTable,
            $this->job_id,
            $this->sourceKey(),
            $mapType,
            $source
        );
        $this->mappedIdCache[$cacheKey] = $localId;
        return $localId;
    }

    private function saveMappedId(string $mapType, mixed $sourceId, int $localId): void {
        if ($localId <= 0) {
            return;
        }
        $source = $this->normalizeSourceId($sourceId);
        if ($source === '') {
            return;
        }
        $source = substr($source, 0, 191);
        $cacheKey = $mapType . '|' . $source;
        SafeMySQL::gi()->query(
            'INSERT INTO ?n (`job_id`, `source_key`, `map_type`, `source_id`, `local_id`) VALUES (?i, ?s, ?s, ?s, ?i)
             ON DUPLICATE KEY UPDATE `local_id` = VALUES(`local_id`), `updated_at` = CURRENT_TIMESTAMP',
            $this->mapTable,
            $this->job_id,
            $this->sourceKey(),
            $mapType,
            $source,
            $localId
        );
        $this->mappedIdCache[$cacheKey] = $localId;
    }

    private function getMappedOrLocal(string $mapType, mixed $sourceId, mixed $fallbackLocalId = null): int {
        if ($mapType === 'property') {
            $configuredPropertyId = $this->getConfiguredPropertyLocalId((string)$sourceId);
            if ($configuredPropertyId > 0) {
                return $configuredPropertyId;
            }
        }
        $mapped = $this->getMappedId($mapType, $sourceId);
        if ($mapped > 0) {
            return $mapped;
        }
        return $this->toInt($fallbackLocalId, 0);
    }

    private function normalizeStatus(mixed $status): string {
        $status = strtolower(trim((string)$status));
        if (!in_array($status, ['active', 'hidden', 'disabled'], true)) {
            $status = 'active';
        }
        return $status;
    }

    private function normalizeEntityType(mixed $entityType): string {
        $entityType = strtolower(trim((string)$entityType));
        if (!in_array($entityType, ['category', 'page', 'all'], true)) {
            $entityType = 'all';
        }
        return $entityType;
    }

    private function normalizeUserRole(mixed $value): int {
        $requestedRoleId = 4;

        if (is_numeric($value)) {
            $roleId = (int)$value;
            if ($roleId >= 1 && $roleId <= 8) {
                $requestedRoleId = $roleId;
            }
        } else {
            $role = strtolower(trim((string)$value));
            $requestedRoleId = match ($role) {
                'admin', 'administrator' => 1,
                'moderator' => 2,
                'manager' => 3,
                'system' => 8,
                default => 4,
            };
        }

        if ($requestedRoleId === 1 && !$this->canAssignImportedAdminRole()) {
            return 2;
        }

        return $requestedRoleId;
    }

    private function canAssignImportedAdminRole(): bool {
        $activeAdminId = (int)SafeMySQL::gi()->getOne(
            'SELECT user_id FROM ?n WHERE user_role = ?i AND deleted = 0 LIMIT 1',
            Constants::USERS_TABLE,
            1
        );

        return $activeAdminId <= 0;
    }

    private function normalizeUserActive(mixed $value): int {
        if (is_numeric($value)) {
            $active = (int)$value;
            if (in_array($active, [1, 2, 3], true)) {
                return $active;
            }
        }
        $status = strtolower(trim((string)$value));
        return match ($status) {
            'pending' => 1,
            'active', 'publish', 'published' => 2,
            'blocked', 'disabled' => 3,
            default => 2,
        };
    }

    private function toBool(mixed $value, bool $default = false): bool {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return ((int)$value) !== 0;
        }
        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return $default;
        }
        if (in_array($normalized, ['1', 'true', 'yes', 'on', 'y'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off', 'n'], true)) {
            return false;
        }
        return $default;
    }

    private function toInt(mixed $value, int $default = 0): int {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        return $default;
    }

    private function prepareJsonField(mixed $value, mixed $fallback = []): string {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                $value = $fallback;
            } elseif (SysClass::ee_isValidJson($trimmed)) {
                return $trimmed;
            } else {
                $value = [$trimmed];
            }
        } elseif ($value === null) {
            $value = $fallback;
        }
        if (!is_array($value)) {
            $value = [$value];
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) && $json !== '' ? $json : '[]';
    }

    private function ensureImporterModels(): void {
        $this->objectModelCategoriesTypes = SysClass::getModelObject('admin', 'm_categories_types');
        $this->objectModelCategories = SysClass::getModelObject('admin', 'm_categories');
        $this->objectModelPages = SysClass::getModelObject('admin', 'm_pages');
        $this->objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
        if (!$this->objectModelCategoriesTypes || !$this->objectModelCategories || !$this->objectModelPages || !$this->objectModelProperties) {
            throw new \RuntimeException('Failed to initialize importer models.');
        }
    }

    private function ensureImportMapInfrastructure(): void {
        $exists = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?s',
            $this->mapTable
        );
        if ($exists === 0) {
            throw new \RuntimeException('Import map infrastructure is not installed. Run install/upgrade first.');
        }
    }

    private function ensurePageUserLinksInfrastructure(): void {
        $exists = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?s',
            Constants::PAGE_USER_LINKS_TABLE
        );
        if ($exists === 0) {
            throw new \RuntimeException('Page-user links infrastructure is not installed. Run install/upgrade first.');
        }
    }

    private function preparePackage(): void {
        $fileId = (int)($this->settings['file_id_package'] ?? 0);
        if ($fileId <= 0) {
            throw new \RuntimeException('Import package file is not configured.');
        }

        $fileData = FileSystem::getFileData($fileId);
        if (!is_array($fileData) || empty($fileData['file_path'])) {
            throw new \RuntimeException('Import package metadata is unavailable (file_id=' . $fileId . ').');
        }

        $packagePath = (string)$fileData['file_path'];
        if (!is_file($packagePath) || !is_readable($packagePath)) {
            throw new \RuntimeException('Import package file is missing or unreadable: ' . $packagePath);
        }

        $hash = sha1($packagePath . '|' . (string)@filesize($packagePath) . '|' . (string)@filemtime($packagePath));
        $sourceDir = $this->workDir . ENV_DIRSEP . 'src_' . $hash;

        $alreadyPrepared = !empty($this->state['prepared'])
            && (string)($this->state['package_file_path'] ?? '') === $packagePath
            && !empty($this->state['files']);
        if ($alreadyPrepared) {
            return;
        }

        $this->removeDirectory($sourceDir);
        $this->ensureDirectory($sourceDir);

        $ext = strtolower((string)pathinfo($packagePath, PATHINFO_EXTENSION));
        if ($ext === 'zip') {
            $zip = new \ZipArchive();
            $res = $zip->open($packagePath);
            if ($res !== true) {
                throw new \RuntimeException('Cannot open ZIP package: code=' . $res);
            }
            try {
                if (!$zip->extractTo($sourceDir)) {
                    throw new \RuntimeException('Cannot extract ZIP package: ' . $packagePath);
                }
            } finally {
                $zip->close();
            }
        } elseif ($ext === 'jsonl' || $ext === 'json') {
            $target = $sourceDir . ENV_DIRSEP . 'package.' . $ext;
            if (!@copy($packagePath, $target)) {
                throw new \RuntimeException('Cannot copy package file: ' . $packagePath);
            }
        } else {
            throw new \RuntimeException('Unsupported package extension: ' . $ext);
        }

        $manifest = [];
        $manifestPath = $this->findManifestPath($sourceDir);
        if ($manifestPath !== '') {
            $decoded = json_decode((string)@file_get_contents($manifestPath), true);
            if (is_array($decoded)) {
                $manifest = $decoded;
            }
        }

        if (!is_array($manifest) || empty($manifest)) {
            $manifest = $this->buildSyntheticManifest($sourceDir);
            if (empty($manifest['files'])) {
                throw new \RuntimeException('Manifest is missing and cannot be generated.');
            }
            $manifestPath = $sourceDir . ENV_DIRSEP . 'manifest.generated.json';
            @file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $manifestFiles = (array)($manifest['files'] ?? []);
        if (empty($manifestFiles) && !empty($manifest['phases']) && is_array($manifest['phases'])) {
            $manifestFiles = (array)$manifest['phases'];
        }

        $files = [];
        foreach ($manifestFiles as $key => $relPath) {
            $key = trim((string)$key);
            $relPath = trim((string)$relPath);
            if ($key === '' || $relPath === '') {
                continue;
            }
            $path = $this->isAbsolutePath($relPath)
                ? $relPath
                : $sourceDir . ENV_DIRSEP . ltrim(str_replace(['/', '\\'], ENV_DIRSEP, $relPath), '/\\');
            if (is_file($path)) {
                $files[$key] = $path;
            } else {
                $this->log('Warning: phase file not found: ' . $key . ' -> ' . $path);
            }
        }

        if (empty($files)) {
            throw new \RuntimeException('No usable phase files found in package manifest.');
        }

        $manifestFormat = strtolower(trim((string)($manifest['format'] ?? '')));
        if ($manifestFormat !== '' && !in_array($manifestFormat, ['ee_entities_json_package', 'ee_wp_json_package', 'json_package'], true)) {
            throw new \RuntimeException('Unsupported package manifest format: ' . $manifestFormat);
        }

        $sourceKey = trim((string)($manifest['source_key'] ?? ''));
        if ($sourceKey === '') {
            $sourceKey = $hash;
        }

        $this->state['prepared'] = true;
        $this->state['source_key'] = $sourceKey;
        $this->state['manifest_format'] = $manifestFormat;
        $this->state['source_system'] = trim((string)($manifest['source_system'] ?? ''));
        $this->state['site_url'] = trim((string)($manifest['site_url'] ?? ''));
        $this->state['source_file_id'] = $fileId;
        $this->state['package_file_path'] = $packagePath;
        $this->state['source_dir'] = $sourceDir;
        $this->state['manifest_path'] = $manifestPath;
        $this->state['manifest'] = $manifest;
        $this->state['files'] = $files;
        $this->state['cursors'] = [];
        $this->state['stats'] = [];
        if ($this->donorBaseUrl === '') {
            $this->donorBaseUrl = $this->normalizeBaseUrl((string)($manifest['site_url'] ?? ''));
        }
    }

    private function loadOrInitializeState(): void {
        $state = null;
        if (is_file($this->stateFile)) {
            $decoded = json_decode((string)@file_get_contents($this->stateFile), true);
            if (is_array($decoded)) {
                $state = $decoded;
            }
        }
        if (!is_array($state) || (int)($state['version'] ?? 0) !== self::STATE_VERSION) {
            $state = [
                'version' => self::STATE_VERSION,
                'phase' => 'init',
                'done' => false,
                'prepared' => false,
                'source_key' => '',
                'files' => [],
                'cursors' => [],
                'stats' => [],
            ];
        }
        $state['phase'] = in_array((string)($state['phase'] ?? ''), self::PHASES, true) ? (string)$state['phase'] : 'init';
        $state['done'] = !empty($state['done']);
        $state['prepared'] = !empty($state['prepared']);
        $state['source_key'] = trim((string)($state['source_key'] ?? ''));
        $state['files'] = is_array($state['files'] ?? null) ? $state['files'] : [];
        $state['cursors'] = is_array($state['cursors'] ?? null) ? $state['cursors'] : [];
        $state['stats'] = is_array($state['stats'] ?? null) ? $state['stats'] : [];
        $this->state = $state;
    }

    private function saveState(): void {
        $this->ensureDirectory(dirname($this->stateFile));
        @file_put_contents($this->stateFile, json_encode($this->state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function resetStateFiles(bool $clearWorkDir = true): void {
        if (is_file($this->stateFile)) {
            @unlink($this->stateFile);
        }
        if ($clearWorkDir && is_dir($this->workDir)) {
            $this->removeDirectory($this->workDir);
        }
        $this->state = [];
    }

    private function advancePhase(string $nextPhase): void {
        if (!in_array($nextPhase, self::PHASES, true)) {
            throw new \RuntimeException('Invalid phase transition target: ' . $nextPhase);
        }
        $this->state['phase'] = $nextPhase;
        if ($nextPhase === 'done') {
            $this->state['done'] = true;
            $this->webStepDone = true;
            $this->log('WEB STEP: done');
        } else {
            $this->state['done'] = false;
            if ($this->webStepMode) {
                $this->log('WEB STEP: in_progress (next=' . $nextPhase . ')');
            }
        }
    }

    private function normalizeImportScope(string $scope): string {
        $scope = strtolower(trim($scope));
        if (!in_array($scope, [self::IMPORT_SCOPE_ALL, self::IMPORT_SCOPE_CORE, self::IMPORT_SCOPE_CONTENT], true)) {
            return self::IMPORT_SCOPE_ALL;
        }
        return $scope;
    }

    private function getStartPhaseForScope(): string {
        $start = (string)(self::SCOPE_START_PHASE[$this->importScope] ?? 'pass_users');
        if (!in_array($start, self::PHASES, true)) {
            return 'pass_users';
        }
        return $start;
    }

    private function isPhaseAllowedForScope(string $phase): bool {
        if ($phase === 'init' || $phase === 'finalize' || $phase === 'done') {
            return true;
        }
        $allowed = self::SCOPE_ALLOWED_PHASES[$this->importScope] ?? self::SCOPE_ALLOWED_PHASES[self::IMPORT_SCOPE_ALL];
        return in_array($phase, $allowed, true);
    }

    private function resolvePhaseForScope(string $phase): string {
        $phase = trim($phase);
        if (!in_array($phase, self::PHASES, true)) {
            $this->state['phase'] = 'init';
            return 'init';
        }
        if ($this->isPhaseAllowedForScope($phase)) {
            return $phase;
        }

        $nextAllowed = $this->findNextAllowedPhaseForScope($phase);
        if ($nextAllowed === '') {
            $nextAllowed = 'finalize';
        }

        $this->log('Scope "' . $this->importScope . '": skip phase ' . $phase . ' -> ' . $nextAllowed);
        $this->state['phase'] = $nextAllowed;
        return $nextAllowed;
    }

    private function findNextAllowedPhaseForScope(string $phase): string {
        $index = array_search($phase, self::PHASES, true);
        if ($index === false) {
            return '';
        }
        $total = count(self::PHASES);
        for ($i = $index + 1; $i < $total; $i++) {
            $candidate = (string)self::PHASES[$i];
            if ($candidate === 'done') {
                return 'finalize';
            }
            if ($this->isPhaseAllowedForScope($candidate)) {
                return $candidate;
            }
        }
        return 'finalize';
    }

    private function getPhaseFilePath(string $phase): string {
        $fileKey = self::PHASE_FILE_MAP[$phase] ?? '';
        if ($fileKey === '') {
            return '';
        }
        $path = $this->state['files'][$fileKey] ?? '';
        return is_string($path) ? $path : '';
    }

    private function logPhaseSummary(string $phase, string $title): void {
        $stats = $this->state['stats'][$phase] ?? $this->emptyStats();
        $this->log(sprintf(
            '%s. Processed: %d, created: %d, updated: %d, skipped: %d, failed: %d',
            $title,
            (int)($stats['processed'] ?? 0),
            (int)($stats['created'] ?? 0),
            (int)($stats['updated'] ?? 0),
            (int)($stats['skipped'] ?? 0),
            (int)($stats['failed'] ?? 0)
        ));
    }

    private function result(string $status, string $message = ''): array { return ['status' => $status, 'message' => $message]; }
    private function emptyStats(): array { return ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0]; }
    private function registerPhaseResult(string $phase, array $result): void {
        $status = strtolower(trim((string)($result['status'] ?? 'updated')));
        if (!in_array($status, ['created', 'updated', 'skipped', 'failed'], true)) {
            $status = 'updated';
        }
        $stats = $this->state['stats'][$phase] ?? $this->emptyStats();
        $stats['processed'] = (int)$stats['processed'] + 1;
        $stats[$status] = (int)$stats[$status] + 1;
        $this->state['stats'][$phase] = $stats;
        if ($status === 'failed') {
            $msg = trim((string)($result['message'] ?? ''));
            if ($msg !== '') {
                $this->log('Phase ' . $phase . ' failed row: ' . $msg);
            }
        }
    }

    private function findManifestPath(string $sourceDir): string {
        $candidate = $sourceDir . ENV_DIRSEP . 'manifest.json';
        if (is_file($candidate)) {
            return $candidate;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && strtolower($fileInfo->getFilename()) === 'manifest.json') {
                return $fileInfo->getPathname();
            }
        }
        return '';
    }

    private function buildSyntheticManifest(string $sourceDir): array {
        $files = [];
        $expected = array_values(array_unique(array_values(self::PHASE_FILE_MAP)));
        foreach ($expected as $key) {
            $jsonl = $sourceDir . ENV_DIRSEP . $key . '.jsonl';
            $json = $sourceDir . ENV_DIRSEP . $key . '.json';
            if (is_file($jsonl)) {
                $files[$key] = $jsonl;
                continue;
            }
            if (is_file($json)) {
                $files[$key] = $json;
            }
        }
        return [
            'format' => 'ee_entities_json_package',
            'schema' => 'ee.entities.v1',
            'source_system' => 'unknown',
            'version' => 1,
            'source_key' => sha1($sourceDir),
            'files' => $files,
        ];
    }

    private function isAbsolutePath(string $path): bool {
        return (bool)preg_match('/^([a-zA-Z]:\\\\|\\\\\\\\|\/)/', $path);
    }

    private function ensureDirectory(string $path): void {
        if ($path === '') {
            return;
        }
        if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException('Cannot create directory: ' . $path);
        }
    }

    private function removeDirectory(string $path): void {
        if ($path === '' || !is_dir($path)) {
            return;
        }
        $items = @scandir($path);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . ENV_DIRSEP . $item;
            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }
}
