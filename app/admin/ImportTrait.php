<?php

namespace app\admin;

use classes\helpers\ClassNotifications;
use classes\plugins\SafeMySQL;
use classes\system\BaseImporter;
use classes\system\Constants;
use classes\system\CronAgentService;
use classes\system\FileSystem;
use classes\system\Hook;
use classes\system\ImportMediaQueueService;
use classes\system\Logger;
use classes\system\OperationResult;
use classes\system\SysClass;
use classes\system\WordpressImporter;

trait ImportTrait {

    public function imports() {
        $this->access = [Constants::ADMIN];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin/imports');
        }

        $import_jobs = SafeMySQL::gi()->getAll(
            'SELECT * FROM ?n ORDER BY `created_at` DESC',
            Constants::IMPORT_SETTINGS_TABLE
        );
        $import_jobs = $this->decorateImportJobsWithCronAgents($import_jobs);

        $this->getStandardViews();
        $this->view->set('import_jobs', $import_jobs);
        $this->view->set('body_view', $this->view->read('v_imports'));
        $this->html = $this->view->read('v_dashboard');

        $this->parameters_layout['layout_content'] = $this->html;
        $this->parameters_layout['layout'] = 'dashboard';
        $this->parameters_layout['title'] = ENV_SITE_NAME . ' - ' . ((string) ($this->lang['sys.imports_profiles_list'] ?? 'Import Profiles'));
        $this->showLayout($this->parameters_layout);
    }

    private function getCurrentUiLanguageCode(): string {
        $langCode = strtoupper(trim((string)(\classes\system\Session::get('lang') ?: ($this->users->data['options']['localize'] ?? ENV_DEF_LANG))));
        return $langCode !== '' ? $langCode : strtoupper((string)ENV_DEF_LANG);
    }

    public function import_property_definitions() {
        $this->access = [Constants::ADMIN];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin/import_property_definitions');
        }

        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            $this->processPropertyDefinitionsImportRequest();
            return;
        }

        $this->renderPropertyDefinitionsImportPage();
        return;

        $this->parameters_layout['layout_content'] = $this->html;
        $this->parameters_layout['layout'] = 'dashboard';
        $this->parameters_layout['title'] = ENV_SITE_NAME . ' - ' . ((string) ($this->lang['sys.import_property_definitions'] ?? 'Import property types, properties, and sets'));
        $this->showLayout($this->parameters_layout);
    }

    private function renderPropertyDefinitionsImportPage(array $state = []): void {
        $previewPayload = is_array($state['preview_payload'] ?? null) ? $state['preview_payload'] : [];
        $previewPayloadJson = trim((string)($state['preview_payload_json'] ?? ''));
        $previewEditorState = is_array($state['preview_editor_state'] ?? null) ? $state['preview_editor_state'] : [];
        $previewEditorStateJson = trim((string)($state['preview_editor_state_json'] ?? ''));
        if ($previewEditorState !== [] && $previewEditorStateJson === '') {
            $previewEditorStateJson = $this->encodePropertyDefinitionsEditorState($previewEditorState);
        }
        $previewWarnings = is_array($state['preview_warnings'] ?? null) ? array_values($state['preview_warnings']) : [];

        $this->getStandardViews();
        $this->view->set('schema_name', 'ee_property_definitions_import');
        $this->view->set('schema_version', 1);
        $this->view->set('doc_filename', '/docs/imports');
        $this->view->set('supported_field_types', Constants::ALL_TYPE_PROPERTY_TYPES_FIELDS);
        $this->view->set('preview_payload', $previewPayload);
        $this->view->set('preview_payload_json', $previewPayloadJson);
        $this->view->set('preview_editor_state', $previewEditorState);
        $this->view->set('preview_editor_state_json', $previewEditorStateJson);
        $this->view->set('preview_warnings', $previewWarnings);
        $this->view->set('preview_disabled_codes', is_array($state['preview_disabled_codes'] ?? null) ? $state['preview_disabled_codes'] : []);
        $this->view->set('preview_error_message', trim((string)($state['preview_error_message'] ?? '')));
        $this->view->set('preview_source_filename', trim((string)($state['preview_source_filename'] ?? '')));
        $this->view->set('body_view', $this->view->read('v_import_property_definitions'));
        $this->html = $this->view->read('v_dashboard');

        $this->parameters_layout['layout_content'] = $this->html;
        $this->parameters_layout['layout'] = 'dashboard';
        $this->parameters_layout['title'] = ENV_SITE_NAME . ' - ' . ((string) ($this->lang['sys.import_property_definitions'] ?? 'Import property types, properties, and sets'));
        $this->showLayout($this->parameters_layout);
    }

    public function edit_import_wp($params = []) {
        $this->access = [Constants::ADMIN];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin/imports');
        }

        $job_id = 0;
        if (in_array('id', $params, true)) {
            $keyId = array_search('id', $params, true);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $job_id = (int)$params[$keyId + 1];
            }
        }

        $currentUiLanguageCode = $this->getCurrentUiLanguageCode();
        $job_settings = [
            'id' => 0,
            'settings_name' => '',
            'importer_slug' => 'wordpress',
            'package_format' => 'ee_entities_json_package',
            'file_id_package' => 0,
            'package_filename' => '',
            'language_code' => $currentUiLanguageCode,
            'test_mode' => 0,
            'test_mode_limit' => 5,
            'include_private_meta_keys' => 0,
            'preserve_source_paths' => 1,
            'rewrite_donor_links' => 1,
            'donor_base_url' => '',
            'allowed_taxonomies' => '',
            'allowed_post_types' => '',
            'allowed_source_ids' => '',
            'meta_include_patterns' => '',
            'meta_exclude_patterns' => '',
            'source_type_map' => '',
            'source_set_map' => '',
            'source_property_map' => '',
            'composite_properties_map' => '[]',
            'excluded_property_source_ids' => '',
            'wizard_core_completed_at' => '',
            'wizard_content_completed_at' => '',
        ];

        if ($job_id > 0) {
            $job_data = SafeMySQL::gi()->getRow(
                'SELECT * FROM ?n WHERE id = ?i',
                Constants::IMPORT_SETTINGS_TABLE,
                $job_id
            );
            if ($job_data) {
                $settings = json_decode((string)$job_data['settings_json'], true);
                if (!is_array($settings)) {
                    $settings = [];
                }

                $job_settings = array_merge($job_settings, $settings);
                $job_settings['id'] = (int)$job_data['id'];
                $job_settings['settings_name'] = (string)$job_data['settings_name'];
            } else {
                $job_id = 0;
            }
        }
        $job_settings['language_code'] = strtoupper(trim((string)($job_settings['language_code'] ?? $currentUiLanguageCode)));
        if ($job_settings['language_code'] === '') {
            $job_settings['language_code'] = $currentUiLanguageCode;
        }

        $max_upload = $this->getIniSizeInBytes(ini_get('upload_max_filesize'));
        $post_max = $this->getIniSizeInBytes(ini_get('post_max_size'));
        $env_max = (int)ENV_MAX_FILE_SIZE;
        $file_limit = min($max_upload, $post_max, $env_max);
        if ($file_limit <= 0) {
            $file_limit = $env_max > 0 ? $env_max : 2 * 1024 * 1024;
        }

        $cron_command = 'php ' . ENV_SITE_PATH . 'app/cron/run.php';
        $cron_import_command = 'php ' . ENV_SITE_PATH . 'inc/cli.php cron:import ' . $job_id;
        $cronAgentTitle = ($this->lang['sys.cron_handler_import_profile'] ?? 'Импорт профиля') . ' #' . $job_id;
        $cronAgentDescription = ($this->lang['sys.cron_handler_import_profile_desc'] ?? 'Запускает профиль импорта по payload.job_id.') . ' #' . $job_id;
        $cron_agent_create_link = '/admin/cron_agent_edit/id/0?handler=import.profile'
            . '&code=' . urlencode('import-profile-' . $job_id)
            . '&title=' . urlencode($cronAgentTitle)
            . '&description=' . urlencode($cronAgentDescription)
            . '&schedule_mode=manual'
            . '&is_active=1'
            . '&priority=70'
            . '&weight=5'
            . '&max_runtime_sec=1800'
            . '&lock_ttl_sec=2100'
            . '&retry_delay_sec=600'
            . '&payload_json=' . urlencode(json_encode(['job_id' => $job_id], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $package_manifest = [];
        $source_catalog = ['category_types' => [], 'property_sets' => []];
        $current_type_map = [];
        $current_set_map = [];
        $current_allowed_sources = [];
        $local_category_types = [];
        $local_property_sets = [];
        $local_properties = [];
        $property_preview = ['rows' => [], 'total' => 0, 'truncated' => false, 'has_data' => false, 'meta_options' => []];
        $package_file_path = '';

        if ((int)($job_settings['file_id_package'] ?? 0) > 0) {
            $package_file_data = FileSystem::getFileData((int)($job_settings['file_id_package'] ?? 0), false);
            if (is_array($package_file_data)) {
                $package_file_path = trim((string)($package_file_data['file_path'] ?? ''));
            }
            $package_manifest = $this->readImportPackageManifest((int)$job_settings['file_id_package']);
            $source_catalog = $this->extractSourceCatalogFromManifest($package_manifest);
            if (trim((string)($job_settings['donor_base_url'] ?? '')) === '') {
                $job_settings['donor_base_url'] = trim((string)($package_manifest['site_url'] ?? ''));
            }
        }

        $current_type_map = $this->parseImportIdMap((string)($job_settings['source_type_map'] ?? ''));
        $current_set_map = $this->parseImportIdMap((string)($job_settings['source_set_map'] ?? ''));
        $current_allowed_sources = $this->normalizeListForUi((string)($job_settings['allowed_source_ids'] ?? ''));
        if (empty($current_allowed_sources)) {
            $legacyTax = $this->normalizeListForUi((string)($job_settings['allowed_taxonomies'] ?? ''));
            foreach ($legacyTax as $taxonomy) {
                $current_allowed_sources[] = 'taxonomy:' . $taxonomy;
            }
            $legacyPost = $this->normalizeListForUi((string)($job_settings['allowed_post_types'] ?? ''));
            foreach ($legacyPost as $postType) {
                $current_allowed_sources[] = 'post_type:' . $postType;
            }
            $current_allowed_sources = array_values(array_unique($current_allowed_sources));
        }

        $local_category_types = $this->getLocalCategoryTypesForImport();
        $local_property_sets = $this->getLocalPropertySetsForImport();
        $local_properties = $this->getLocalPropertiesForImport();
        $property_preview = $this->buildImportPropertyPreview(
            (int)($job_settings['file_id_package'] ?? 0),
            $package_manifest,
            $source_catalog,
            $current_set_map,
            $local_property_sets,
            $job_id
        );

        $this->getStandardViews();
        $this->view->set('job_id', $job_id);
        $this->view->set('job', $job_settings);
        $this->view->set('cron_command', $cron_command);
        $this->view->set('cron_import_command', $cron_import_command);
        $this->view->set('cron_agent_create_link', $cron_agent_create_link);
        $this->view->set('import_cron_agent', $this->getImportCronAgent($job_id));
        $this->view->set('import_media_queue', $this->getImportMediaQueueSummary($job_id));
        $this->view->set('max_file_size_bytes', $file_limit);
        $this->view->set('max_file_size_human', round($file_limit / 1024 / 1024, 1) . ' MB');
        $this->view->set('package_manifest', $package_manifest);
        $this->view->set('source_catalog', $source_catalog);
        $this->view->set('current_type_map', $current_type_map);
        $this->view->set('current_set_map', $current_set_map);
        $this->view->set('current_allowed_sources', $current_allowed_sources);
        $this->view->set('local_category_types', $local_category_types);
        $this->view->set('local_property_sets', $local_property_sets);
        $this->view->set('local_properties', $local_properties);
        $this->view->set('property_preview', $property_preview);
        $this->view->set('package_file_path', $package_file_path);
        $this->view->set('body_view', $this->view->read('v_edit_import'));
        $this->html = $this->view->read('v_dashboard');

        $this->parameters_layout['layout_content'] = $this->html;
        $this->parameters_layout['layout'] = 'dashboard';
        $this->parameters_layout['title'] = $job_id > 0
            ? 'Редактирование профиля импорта'
            : 'Новый профиль импорта';
        $this->parameters_layout['add_script'] .=
            '<script src="' . $this->getPathController() . '/js/import_wp.js" type="text/javascript" /></script>';
        $this->showLayout($this->parameters_layout);
    }

    public function sync_import_cron_agent($params = []) {
        $this->access = [Constants::ADMIN];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin/imports');
            return;
        }

        $jobId = $this->extractImportJobIdFromParams($params);
        if ($jobId <= 0) {
            $this->notifyOperationResult(
                OperationResult::failure('Не указан ID профиля импорта.', 'import_profile_missing_id'),
                ['default_error_message' => 'Не указан ID профиля импорта.']
            );
            SysClass::handleRedirect(200, '/admin/imports');
            return;
        }

        $result = $this->syncImportCronAgent($jobId);
        $this->notifyOperationResult(
            $result,
            [
                'success_message' => $this->lang['sys.imports_cron_agent_synced'] ?? 'Cron-агент профиля импорта синхронизирован.',
                'default_error_message' => $this->lang['sys.error'] ?? 'Ошибка',
            ]
        );
        SysClass::handleRedirect(200, '/admin/edit_import_wp/id/' . $jobId);
    }

    public function delete_import_cron_agent($params = []) {
        $this->access = [Constants::ADMIN];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form?return=admin/imports');
            return;
        }

        $jobId = $this->extractImportJobIdFromParams($params);
        if ($jobId <= 0) {
            $this->notifyOperationResult(
                OperationResult::failure('Не указан ID профиля импорта.', 'import_profile_missing_id'),
                ['default_error_message' => 'Не указан ID профиля импорта.']
            );
            SysClass::handleRedirect(200, '/admin/imports');
            return;
        }

        $agent = $this->getImportCronAgent($jobId);
        $result = $agent
            ? CronAgentService::deleteAgent((int) ($agent['agent_id'] ?? 0))
            : OperationResult::success(['job_id' => $jobId], $this->lang['sys.imports_cron_agent_missing'] ?? 'Связанный cron-агент не найден.', 'noop');

        $this->notifyOperationResult(
            $result,
            [
                'success_message' => $result->isSuccess()
                    ? $result->getMessage($this->lang['sys.imports_cron_agent_deleted'] ?? 'Cron-агент профиля импорта удалён.')
                    : '',
                'default_error_message' => $this->lang['sys.error'] ?? 'Ошибка',
            ]
        );
        SysClass::handleRedirect(200, '/admin/edit_import_wp/id/' . $jobId);
    }

    public function save_import_wp() {
        $this->access = [Constants::ADMIN];
        if (!SysClass::getAccessUser($this->logged_in, $this->access) || !SysClass::isAjaxRequestFromSameSite()) {
            echo json_encode(['success' => false, 'message' => 'Доступ запрещён']);
            return;
        }

        $file_error = null;
        $packageReplaced = false;
        if (isset($_FILES['import_package_file']) &&
            (int)$_FILES['import_package_file']['error'] > 0 &&
            (int)$_FILES['import_package_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file_error = FileSystem::getErrorDescriptionByUploadCode((int)$_FILES['import_package_file']['error']);
        }
        if ($file_error) {
            echo json_encode(['success' => false, 'message' => $file_error]);
            return;
        }

        $postData = SysClass::ee_cleanArray($_POST);
        $job_id = (int)($postData['job_id'] ?? 0);
        $settings_name = trim((string)($postData['settings_name'] ?? ''));
        if ($settings_name === '') {
            echo json_encode(['success' => false, 'message' => 'Укажите название профиля']);
            return;
        }

        $file_id_package = (int)($postData['file_id_package'] ?? 0);
        $package_filename = trim((string)($postData['package_filename_hidden'] ?? ''));

        if (isset($_FILES['import_package_file']) && (int)$_FILES['import_package_file']['error'] === UPLOAD_ERR_OK) {
            $originalPackageName = (string)($_FILES['import_package_file']['name'] ?? '');
            if (!$this->isSupportedImportPackageName($originalPackageName)) {
                echo json_encode(['success' => false, 'message' => 'Неподдерживаемый тип пакета. Разрешены: .zip, .jsonl, .json']);
                return;
            }

            $fileData = FileSystem::safeMoveUploadedFile($_FILES['import_package_file']);
            if (!$fileData) {
                echo json_encode(['success' => false, 'message' => 'Не удалось загрузить файл пакета']);
                return;
            }
            $uploadedPath = (string)($fileData['file_path'] ?? '');
            if (!$this->isSupportedImportPackagePath($uploadedPath)) {
                if ($uploadedPath !== '' && is_file($uploadedPath)) {
                    @unlink($uploadedPath);
                }
                echo json_encode(['success' => false, 'message' => 'Неподдерживаемый тип пакета. Разрешены: .zip, .jsonl, .json']);
                return;
            }
            $savedFileId = FileSystem::saveFileInfo($fileData);
            if (!$savedFileId) {
                echo json_encode(['success' => false, 'message' => 'Не удалось сохранить метаданные файла пакета']);
                return;
            }
            $file_id_package = (int)$savedFileId;
            $package_filename = (string)($fileData['original_name'] ?? $fileData['name'] ?? 'package.zip');
            $packageReplaced = true;
        }

        if ($file_id_package <= 0) {
            echo json_encode(['success' => false, 'message' => 'Сначала загрузите пакет JSON/JSONL']);
            return;
        }

        $detectedPackageFormat = 'ee_entities_json_package';
        $manifest = $this->readImportPackageManifest($file_id_package);
        $manifestFormat = strtolower(trim((string)($manifest['format'] ?? '')));
        if (in_array($manifestFormat, ['ee_entities_json_package', 'ee_wp_json_package', 'json_package'], true)) {
            $detectedPackageFormat = $manifestFormat;
        }

        $wizardCoreCompletedAt = trim((string)($postData['wizard_core_completed_at'] ?? ''));
        $wizardContentCompletedAt = trim((string)($postData['wizard_content_completed_at'] ?? ''));
        $languageCode = strtoupper(trim((string)($postData['language_code'] ?? $this->getCurrentUiLanguageCode())));
        if ($languageCode === '') {
            $languageCode = strtoupper((string)ENV_DEF_LANG);
        }
        if ($packageReplaced) {
            $wizardCoreCompletedAt = '';
            $wizardContentCompletedAt = '';
        }
        if ($wizardCoreCompletedAt === '') {
            $wizardContentCompletedAt = '';
        }

        $settings = [
            'package_format' => $detectedPackageFormat,
            'package_schema_version' => 1,
            'file_id_package' => $file_id_package,
            'package_filename' => $package_filename,
            'language_code' => $languageCode,
            'test_mode' => filter_var($postData['test_mode'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'test_mode_limit' => max(1, (int)($postData['test_mode_limit'] ?? 5)),
            'include_private_meta_keys' => filter_var($postData['include_private_meta_keys'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'preserve_source_paths' => filter_var($postData['preserve_source_paths'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'rewrite_donor_links' => filter_var($postData['rewrite_donor_links'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'donor_base_url' => trim((string)($postData['donor_base_url'] ?? '')),
            'allowed_taxonomies' => trim((string)($postData['allowed_taxonomies'] ?? '')),
            'allowed_post_types' => trim((string)($postData['allowed_post_types'] ?? '')),
            'allowed_source_ids' => trim((string)($postData['allowed_source_ids'] ?? '')),
            'meta_include_patterns' => trim((string)($postData['meta_include_patterns'] ?? '')),
            'meta_exclude_patterns' => trim((string)($postData['meta_exclude_patterns'] ?? '')),
            'source_type_map' => trim((string)($postData['source_type_map'] ?? '')),
            'source_set_map' => trim((string)($postData['source_set_map'] ?? '')),
            'source_property_map' => trim((string)($postData['source_property_map'] ?? '')),
            'composite_properties_map' => trim((string)($postData['composite_properties_map'] ?? '[]')),
            'excluded_property_source_ids' => trim((string)($postData['excluded_property_source_ids'] ?? '')),
            'wizard_core_completed_at' => $wizardCoreCompletedAt,
            'wizard_content_completed_at' => $wizardContentCompletedAt,
        ];

        $data_to_db = [
            'importer_slug' => 'wordpress',
            'settings_name' => $settings_name,
            'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        try {
            if ($job_id > 0) {
                SafeMySQL::gi()->query(
                    'UPDATE ?n SET ?u WHERE id = ?i',
                    Constants::IMPORT_SETTINGS_TABLE,
                    $data_to_db,
                    $job_id
                );
            } else {
                SafeMySQL::gi()->query('INSERT INTO ?n SET ?u', Constants::IMPORT_SETTINGS_TABLE, $data_to_db);
                $job_id = (int)SafeMySQL::gi()->insertId();
            }

            if ($this->getImportCronAgent($job_id)) {
                $syncResult = $this->syncImportCronAgent($job_id);
                if (!$syncResult->isSuccess()) {
                    Logger::warning('import_profiles', 'Не удалось синхронизировать связанный cron-агент профиля импорта', [
                        'job_id' => $job_id,
                        'message' => $syncResult->getMessage(),
                    ], [
                        'initiator' => __METHOD__,
                        'details' => 'Import profile cron agent sync failed after save',
                        'include_trace' => false,
                    ]);
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Сохранено',
                'job_id' => $job_id,
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function download_wp_adapter() {
        $this->access = [Constants::ADMIN];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            die('Access denied');
        }

        $adapterPath = ENV_SITE_PATH . 'classes/system/wp_adapter_template.php';
        if (!is_file($adapterPath) || !is_readable($adapterPath)) {
            die('Файл шаблона экспортёра не найден: ' . $adapterPath);
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/x-php');
        header('Content-Disposition: attachment; filename="ee_wp_exporter.php"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($adapterPath));
        readfile($adapterPath);
        exit;
    }

    public function run_wp_import($params = []) {
        $job_id = 0;
        $postData = [];
        $isStepModeRequest = false;
        $isCronRun = defined('EE_CRON_RUN') && EE_CRON_RUN === true;
        $stepBufferBaseLevel = null;
        $stepLogFile = '';
        $stepLogOffset = 0;
        $stepRequestId = '';
        $stepSeq = 0;
        $stepRestart = 0;
        $stepScope = 'all';
        $requestStartedAt = microtime(true);
        $stepFatalResponseSent = false;

        $readLogDelta = static function (string $logFile, int $offset): string {
            if ($logFile === '' || !is_file($logFile) || !is_readable($logFile)) {
                return '';
            }
            $size = (int)@filesize($logFile);
            if ($size <= 0) {
                return '';
            }
            $offset = max(0, $offset);
            if ($offset > $size) {
                $offset = 0;
            }
            $handle = @fopen($logFile, 'rb');
            if (!$handle) {
                $fallback = @file_get_contents($logFile);
                return is_string($fallback) ? $fallback : '';
            }
            try {
                if ($offset > 0) {
                    @fseek($handle, $offset);
                }
                $delta = stream_get_contents($handle);
                return is_string($delta) ? $delta : '';
            } finally {
                fclose($handle);
            }
        };

        $sendStepJson = function (array $payload) use (&$stepBufferBaseLevel, &$stepRequestId, &$requestStartedAt, &$stepFatalResponseSent): void {
            $stepFatalResponseSent = true;
            $rawOutput = '';
            if ($stepBufferBaseLevel !== null) {
                $chunks = [];
                while (ob_get_level() > $stepBufferBaseLevel) {
                    $chunk = (string)ob_get_clean();
                    if ($chunk !== '') {
                        $chunks[] = $chunk;
                    }
                }
                if (!empty($chunks)) {
                    $rawOutput = implode('', array_reverse($chunks));
                }
            } else {
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }
            }

            if ($rawOutput !== '') {
                $payload['log'] = ($payload['log'] ?? '') . $rawOutput;
            }
            if ($stepRequestId !== '' && !isset($payload['request_id'])) {
                $payload['request_id'] = $stepRequestId;
            }
            if (!isset($payload['duration_ms'])) {
                $payload['duration_ms'] = (int)round((microtime(true) - $requestStartedAt) * 1000);
            }
            if (!isset($payload['server_ts'])) {
                $payload['server_ts'] = date('Y-m-d H:i:s');
            }

            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                $json = '{"success":false,"done":false,"message":"JSON encode failed"}';
            }
            echo $json;
            exit;
        };

        if ($isCronRun) {
            $job_id = (int)($params[0] ?? 0);
        } else {
            if (!SysClass::getAccessUser($this->logged_in, [Constants::ADMIN])) {
                die('Access denied');
            }
            $postData = SysClass::ee_cleanArray($_POST);
            $job_id = (int)($postData['job_id'] ?? 0);
            $isStepModeRequest = filter_var($postData['step_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($isStepModeRequest) {
                $stepSeq = max(0, (int)($postData['step_seq'] ?? 0));
                $stepRestart = filter_var($postData['step_restart'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                $rawScope = strtolower(trim((string)($postData['step_scope'] ?? 'all')));
                $stepScope = in_array($rawScope, ['all', 'core', 'content'], true) ? $rawScope : 'all';
                try {
                    $stepRequestId = bin2hex(random_bytes(6));
                } catch (\Throwable $e) {
                    $stepRequestId = uniqid('step_', true);
                }
                header('Content-Type: application/json; charset=utf-8');
                @ini_set('display_errors', '0');
                @ini_set('html_errors', '0');
                $stepBufferBaseLevel = ob_get_level();
                ob_start();
                register_shutdown_function(function () use (
                    &$stepFatalResponseSent,
                    &$stepBufferBaseLevel,
                    &$stepRequestId,
                    &$readLogDelta,
                    &$requestStartedAt,
                    &$stepLogFile,
                    &$stepLogOffset,
                    &$job_id
                ): void {
                    if ($stepFatalResponseSent) {
                        return;
                    }
                    $error = error_get_last();
                    if (!is_array($error)) {
                        return;
                    }
                    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
                    if (!in_array((int)($error['type'] ?? 0), $fatalTypes, true)) {
                        return;
                    }
                    $stepFatalResponseSent = true;
                    BaseImporter::preLog(
                        '[WEB-STEP] FATAL: ' . (string)($error['message'] ?? '') .
                        ' in ' . (string)($error['file'] ?? '') . ':' . (string)($error['line'] ?? ''),
                        (int)$job_id
                    );
                    if ($stepBufferBaseLevel !== null) {
                        while (ob_get_level() > $stepBufferBaseLevel) {
                            @ob_end_clean();
                        }
                    }
                    $delta = $readLogDelta((string)$stepLogFile, (int)$stepLogOffset);
                    if (!headers_sent()) {
                        header('Content-Type: application/json; charset=utf-8');
                    }
                    $payload = [
                        'success' => false,
                        'done' => false,
                        'error_code' => 'IMPORT_FATAL',
                        'message' => 'Fatal import error: ' . (string)($error['message'] ?? 'unknown'),
                        'log' => $delta,
                        'request_id' => (string)$stepRequestId,
                        'duration_ms' => (int)round((microtime(true) - $requestStartedAt) * 1000),
                        'server_ts' => date('Y-m-d H:i:s'),
                    ];
                    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    echo is_string($json) ? $json : '{"success":false,"done":false,"error_code":"IMPORT_FATAL","message":"Fatal import error"}';
                });
            } else {
                header('Content-Type: text/plain; charset=utf-8');
                ob_implicit_flush(true);
                if (ob_get_level() > 0) {
                    @ob_end_flush();
                }
            }
        }

        if ($job_id <= 0) {
            $message = 'Не указан ID профиля импорта';
            if ($isStepModeRequest && !$isCronRun) {
                $sendStepJson(['success' => false, 'done' => false, 'message' => $message]);
            }
            die($message);
        }

        $job_data = SafeMySQL::gi()->getRow('SELECT * FROM ?n WHERE id = ?i', Constants::IMPORT_SETTINGS_TABLE, $job_id);
        if (!$job_data) {
            $message = 'Профиль импорта не найден: ID=' . $job_id;
            if ($isStepModeRequest && !$isCronRun) {
                $sendStepJson(['success' => false, 'done' => false, 'message' => $message]);
            }
            die($message);
        }

        $stepLogFile = rtrim(ENV_LOGS_PATH, '/\\') . ENV_DIRSEP . 'import' . ENV_DIRSEP . 'import_job_' . $job_id . '.txt';
        if ($isStepModeRequest && !$isCronRun) {
            $stepLogOffset = is_file($stepLogFile) ? (int)@filesize($stepLogFile) : 0;
            BaseImporter::preLog(
                '[WEB-STEP] START request_id=' . ($stepRequestId !== '' ? $stepRequestId : '-') .
                ' seq=' . $stepSeq .
                ' restart=' . $stepRestart .
                ' scope=' . $stepScope,
                $job_id
            );
        }

        $settings = json_decode((string)$job_data['settings_json'], true);
        if (!is_array($settings)) {
            $settings = [];
        }
        $storedSettings = $settings;
        $settings['file_id_package'] = (int)($settings['file_id_package'] ?? 0);
        $settings['package_filename'] = (string)($settings['package_filename'] ?? '');
        $settings['package_format'] = (string)($settings['package_format'] ?? 'ee_entities_json_package');
        if ($settings['file_id_package'] <= 0) {
            $message = 'Для профиля не настроен файл пакета импорта.';
            if ($isStepModeRequest && !$isCronRun) {
                $sendStepJson(['success' => false, 'done' => false, 'message' => $message]);
            }
            die($message);
        }
        if (!in_array($settings['package_format'], ['ee_entities_json_package', 'ee_wp_json_package', 'json_package'], true)) {
            $message = 'Неподдерживаемый формат пакета: ' . $settings['package_format'];
            if ($isStepModeRequest && !$isCronRun) {
                $sendStepJson(['success' => false, 'done' => false, 'message' => $message]);
            }
            die($message);
        }
        $packageFile = FileSystem::getFileData($settings['file_id_package']);
        $packagePath = is_array($packageFile) ? (string)($packageFile['file_path'] ?? '') : '';
        if ($packagePath === '' || !is_file($packagePath) || !is_readable($packagePath)) {
            $message = 'Файл пакета импорта отсутствует или недоступен для чтения.';
            if ($isStepModeRequest && !$isCronRun) {
                $sendStepJson(['success' => false, 'done' => false, 'message' => $message]);
            }
            die($message);
        }
        if (!$this->isSupportedImportPackagePath($packagePath)) {
            $message = 'Тип файла пакета импорта не поддерживается.';
            if ($isStepModeRequest && !$isCronRun) {
                $sendStepJson(['success' => false, 'done' => false, 'message' => $message]);
            }
            die($message);
        }

        if (!$isCronRun) {
            if (array_key_exists('test_mode', $postData)) {
                $parsedTestMode = filter_var($postData['test_mode'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($parsedTestMode !== null) {
                    $settings['test_mode'] = $parsedTestMode;
                }
            }
            if (array_key_exists('test_mode_limit', $postData)) {
                $parsedLimit = (int)$postData['test_mode_limit'];
                if ($parsedLimit > 0) {
                    $settings['test_mode_limit'] = $parsedLimit;
                }
            }
        }

        if ($isStepModeRequest && !$isCronRun && $stepScope === 'content') {
            $coreCompletedAt = trim((string)($settings['wizard_core_completed_at'] ?? ''));
            if ($coreCompletedAt === '') {
                $sendStepJson([
                    'success' => false,
                    'done' => false,
                    'message' => 'Сначала завершите этап 1 (типы свойств, свойства, наборы свойств).',
                ]);
            }
        }

        $settings['job_id'] = $job_id;
        $settings['importer_slug'] = (string)$job_data['importer_slug'];
        $settings['settings_name'] = (string)$job_data['settings_name'];

        if ($isStepModeRequest && !$isCronRun) {
            $settings['web_step_mode'] = true;
            $settings['web_step_restart'] = ($stepRestart === 1);
            $settings['web_step_chunk_rows'] = max(10, (int)($postData['step_chunk_rows'] ?? 120));
            $settings['import_scope'] = $stepScope;
        } else {
            $storedScope = strtolower(trim((string)($settings['import_scope'] ?? 'all')));
            if (!in_array($storedScope, ['all', 'core', 'content'], true)) {
                $storedScope = 'all';
            }
            $settings['import_scope'] = $storedScope;
        }

        $lockDir = rtrim(ENV_LOGS_PATH, '/\\') . ENV_DIRSEP . 'import' . ENV_DIRSEP;
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0755, true);
        }
        $lockFile = $lockDir . 'import_job_' . $job_id . '.lock';
        $lockHandle = @fopen($lockFile, 'c+');
        $lockAcquired = false;
        if (is_resource($lockHandle)) {
            $lockAcquired = @flock($lockHandle, LOCK_EX | LOCK_NB);
        }
        if (!$lockAcquired) {
            $message = is_resource($lockHandle)
                ? 'Импорт уже запущен для этого профиля.'
                : 'Не удалось инициализировать lock-файл импорта.';
            BaseImporter::preLog('[WEB-STEP] LOCKED profile=' . $job_id, $job_id);
            if (is_resource($lockHandle)) {
                @fclose($lockHandle);
            }
            if ($isStepModeRequest && !$isCronRun) {
                $stepLog = $readLogDelta($stepLogFile, $stepLogOffset);
                $sendStepJson([
                    'success' => false,
                    'done' => false,
                    'error_code' => 'IMPORT_LOCKED',
                    'message' => $message,
                    'log' => $stepLog,
                ]);
            }
            die($message);
        }

        try {
            $importer = new WordpressImporter($settings);
            $importer->run();
            $importMediaQueueSummary = $this->getImportMediaQueueSummary($job_id);

            if ($isStepModeRequest && !$isCronRun) {
                $stepLog = $readLogDelta($stepLogFile, $stepLogOffset);
                $durationMs = (int)round((microtime(true) - $requestStartedAt) * 1000);
                $wizardCoreCompletedAt = trim((string)($storedSettings['wizard_core_completed_at'] ?? ''));
                $wizardContentCompletedAt = trim((string)($storedSettings['wizard_content_completed_at'] ?? ''));

                if ($importer->isWebStepDone()) {
                    $now = date('Y-m-d H:i:s');
                    $needsPersist = false;
                    if ($stepScope === 'core') {
                        $wizardCoreCompletedAt = $now;
                        $wizardContentCompletedAt = '';
                        $needsPersist = true;
                    } elseif ($stepScope === 'content') {
                        $wizardContentCompletedAt = $now;
                        $needsPersist = true;
                    }

                    if ($needsPersist) {
                        $updatedSettings = is_array($storedSettings) ? $storedSettings : [];
                        $updatedSettings['wizard_core_completed_at'] = $wizardCoreCompletedAt;
                        $updatedSettings['wizard_content_completed_at'] = $wizardContentCompletedAt;
                        try {
                            SafeMySQL::gi()->query(
                                'UPDATE ?n SET settings_json = ?s WHERE id = ?i',
                                Constants::IMPORT_SETTINGS_TABLE,
                                json_encode($updatedSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                $job_id
                            );
                        } catch (\Throwable $persistError) {
                            BaseImporter::preLog(
                                '[WEB-STEP] WARNING: cannot persist wizard markers for profile=' . $job_id . ' (' . $persistError->getMessage() . ')',
                                $job_id
                            );
                        }
                    }

                    if (($importMediaQueueSummary['pending'] ?? 0) > 0) {
                        ClassNotifications::addNotificationUser($this->logged_in, [
                            'text' => sprintf(
                                $this->lang['sys.import_media_queue_notification'] ?? 'Импорт завершён. Медиа поставлены в фоновую очередь системному агенту (%d).',
                                (int) ($importMediaQueueSummary['pending'] ?? 0)
                            ),
                            'status' => 'info',
                        ]);
                    }
                }

                BaseImporter::preLog(
                    '[WEB-STEP] OK request_id=' . ($stepRequestId !== '' ? $stepRequestId : '-') .
                    ' seq=' . $stepSeq .
                    ' scope=' . $stepScope .
                    ' done=' . ($importer->isWebStepDone() ? '1' : '0') .
                    ' duration_ms=' . $durationMs,
                    $job_id
                );
                $sendStepJson([
                    'success' => true,
                    'done' => $importer->isWebStepDone(),
                    'log' => $stepLog,
                    'duration_ms' => $durationMs,
                    'scope' => $stepScope,
                    'wizard_core_completed_at' => $wizardCoreCompletedAt,
                    'wizard_content_completed_at' => $wizardContentCompletedAt,
                    'media_queue' => $importMediaQueueSummary,
                ]);
            }

            if (!$isCronRun && !$isStepModeRequest && ($importMediaQueueSummary['pending'] ?? 0) > 0) {
                ClassNotifications::addNotificationUser($this->logged_in, [
                    'text' => sprintf(
                        $this->lang['sys.import_media_queue_notification'] ?? 'Импорт завершён. Медиа поставлены в фоновую очередь системному агенту (%d).',
                        (int) ($importMediaQueueSummary['pending'] ?? 0)
                    ),
                    'status' => 'info',
                ]);
            }
        } catch (\Throwable $e) {
            $durationMs = (int)round((microtime(true) - $requestStartedAt) * 1000);
            $logMessage = 'CRITICAL ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
            BaseImporter::preLog($logMessage, $job_id);

            if ($isStepModeRequest && !$isCronRun) {
                $stepLog = $readLogDelta($stepLogFile, $stepLogOffset);
                $sendStepJson([
                    'success' => false,
                    'done' => false,
                    'message' => $e->getMessage(),
                    'log' => $stepLog,
                    'duration_ms' => $durationMs,
                ]);
            }

            echo $logMessage;
        } finally {
            if (is_resource($lockHandle)) {
                @flock($lockHandle, LOCK_UN);
                @fclose($lockHandle);
            }
        }
    }

    public function delete_import_profile($params = []) {
        $this->access = [Constants::ADMIN];
        if (!SysClass::getAccessUser($this->logged_in, $this->access)) {
            SysClass::handleRedirect(200, '/show_login_form');
            return;
        }

        $job_id = 0;
        if (in_array('id', $params, true)) {
            $keyId = array_search('id', $params, true);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                $job_id = (int)$params[$keyId + 1];
            }
        }

        if ($job_id <= 0) {
            \classes\helpers\ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => 'Ошибка: не указан ID профиля импорта.',
                'status' => 'danger',
            ]);
            SysClass::handleRedirect(200, '/admin/imports');
            return;
        }

        $job_data = SafeMySQL::gi()->getRow('SELECT * FROM ?n WHERE id = ?i', Constants::IMPORT_SETTINGS_TABLE, $job_id);
        if (!$job_data) {
            \classes\helpers\ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => 'Ошибка: профиль импорта не найден.',
                'status' => 'danger',
            ]);
            SysClass::handleRedirect(200, '/admin/imports');
            return;
        }

        $linkedAgent = $this->getImportCronAgent($job_id);
        if ($linkedAgent) {
            $deleteAgentResult = CronAgentService::deleteAgent((int) ($linkedAgent['agent_id'] ?? 0));
            if (!$deleteAgentResult->isSuccess()) {
                $this->notifyOperationResult(
                    $deleteAgentResult,
                    [
                        'default_error_message' => $this->lang['sys.error'] ?? 'Ошибка',
                    ]
                );
                SysClass::handleRedirect(200, '/admin/edit_import_wp/id/' . $job_id);
                return;
            }
        }

        $settings = json_decode((string)$job_data['settings_json'], true);
        if (!is_array($settings)) {
            $settings = [];
        }

        $fileIds = [];
        $fileIds[] = (int)($settings['file_id_package'] ?? 0);
        $fileIds = array_values(array_unique(array_filter($fileIds, static fn($id) => $id > 0)));
        foreach ($fileIds as $fileId) {
            FileSystem::deleteFileData($fileId);
        }

        $logFile = rtrim(ENV_LOGS_PATH, '/\\') . ENV_DIRSEP . 'import' . ENV_DIRSEP . "import_job_{$job_id}.txt";
        $stateFile = rtrim(ENV_LOGS_PATH, '/\\') . ENV_DIRSEP . 'import' . ENV_DIRSEP . "import_job_{$job_id}.state.json";
        if (is_file($logFile)) {
            @unlink($logFile);
        }
        if (is_file($stateFile)) {
            @unlink($stateFile);
        }

        $workDir = rtrim(ENV_SITE_PATH, '/\\') . ENV_DIRSEP . 'uploads' . ENV_DIRSEP . 'tmp' . ENV_DIRSEP . 'wp_import_job_' . $job_id;
        if (is_dir($workDir)) {
            $removeDir = function (string $path) use (&$removeDir): void {
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
                        $removeDir($full);
                    } else {
                        @unlink($full);
                    }
                }
                @rmdir($path);
            };
            $removeDir($workDir);
        }

        SafeMySQL::gi()->query('DELETE FROM ?n WHERE id = ?i', Constants::IMPORT_SETTINGS_TABLE, $job_id);
        try {
            SafeMySQL::gi()->query('DELETE FROM ?n WHERE job_id = ?i', Constants::IMPORT_MAP_TABLE, $job_id);
        } catch (\Throwable $e) {
            BaseImporter::preLog('WARNING: failed to cleanup import_map for profile=' . $job_id . ' (' . $e->getMessage() . ')', $job_id);
        }
        ImportMediaQueueService::deleteJobQueue($job_id);

        \classes\helpers\ClassNotifications::addNotificationUser($this->logged_in, [
            'text' => 'Профиль импорта удалён.',
            'status' => 'success',
        ]);
        SysClass::handleRedirect(200, '/admin/imports');
    }

    private function extractImportJobIdFromParams(array $params): int {
        if (in_array('id', $params, true)) {
            $keyId = array_search('id', $params, true);
            if ($keyId !== false && isset($params[$keyId + 1])) {
                return (int) $params[$keyId + 1];
            }
        }
        return 0;
    }

    private function decorateImportJobsWithCronAgents(array $jobs): array {
        $result = [];
        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }
            $jobId = (int) ($job['id'] ?? 0);
            $job['cron_agent'] = $jobId > 0 ? $this->getImportCronAgent($jobId) : null;
            $result[] = $job;
        }
        return $result;
    }

    private function getImportCronAgentCode(int $jobId): string {
        return 'import-profile-' . max(0, $jobId);
    }

    private function getImportCronAgent(int $jobId): ?array {
        if ($jobId <= 0) {
            return null;
        }
        $agent = CronAgentService::getAgentByCode($this->getImportCronAgentCode($jobId));
        if (!$agent) {
            return null;
        }

        $status = (string) ($agent['runtime_status'] ?? 'idle');
        $agent['runtime_status_label'] = match ($status) {
            'running' => $this->lang['sys.running'] ?? 'В работе',
            'disabled' => $this->lang['sys.disabled'] ?? 'Отключён',
            'due' => $this->lang['sys.cron_agent_status_due'] ?? 'Готов к запуску',
            'failed' => $this->lang['sys.failed'] ?? 'С ошибкой',
            'cooldown' => $this->lang['sys.cron_agent_status_cooldown'] ?? 'Пауза после ошибки',
            default => $this->lang['sys.cron_agent_status_idle'] ?? 'Ожидает',
        };
        $agent['runtime_status_class'] = match ($status) {
            'running' => 'bg-primary',
            'disabled' => 'bg-secondary',
            'due' => 'bg-warning text-dark',
            'failed' => 'bg-danger',
            'cooldown' => 'bg-info text-dark',
            default => 'bg-success',
        };
        $agent['schedule_human'] = match ((string) ($agent['schedule_mode'] ?? 'interval')) {
            'cron' => (string) ($agent['cron_expression'] ?? ''),
            'manual' => $this->lang['sys.cron_agent_schedule_manual'] ?? 'Только вручную',
            default => (int) ($agent['interval_minutes'] ?? 0) . ' ' . ($this->lang['sys.cron_agent_interval_minutes'] ?? 'мин'),
        };

        return $agent;
    }

    private function getImportMediaQueueSummary(int $jobId): array {
        if ($jobId <= 0) {
            return [
                'job_id' => 0,
                'total' => 0,
                'queued' => 0,
                'running' => 0,
                'failed' => 0,
                'terminal_failed' => 0,
                'done' => 0,
                'pending' => 0,
                'last_completed_at' => '',
                'last_updated_at' => '',
                'agent_code' => 'media-mirror-worker',
                'agent' => CronAgentService::getAgentByCode('media-mirror-worker'),
            ];
        }

        return ImportMediaQueueService::getSummary($jobId);
    }

    private function syncImportCronAgent(int $jobId): OperationResult {
        $jobData = SafeMySQL::gi()->getRow(
            'SELECT * FROM ?n WHERE id = ?i',
            Constants::IMPORT_SETTINGS_TABLE,
            $jobId
        );
        if (!$jobData) {
            return OperationResult::failure('Профиль импорта не найден.', 'import_profile_not_found', ['job_id' => $jobId]);
        }

        $existingAgent = $this->getImportCronAgent($jobId);
        $title = trim((string) ($jobData['settings_name'] ?? ''));
        if ($title === '') {
            $title = ($this->lang['sys.cron_handler_import_profile'] ?? 'Импорт профиля') . ' #' . $jobId;
        }

        $agentData = [
            'agent_id' => (int) ($existingAgent['agent_id'] ?? 0),
            'code' => $this->getImportCronAgentCode($jobId),
            'title' => $title,
            'description' => $this->lang['sys.cron_handler_import_profile_desc'] ?? 'Запускает профиль импорта по payload.job_id.',
            'handler' => 'import.profile',
            'schedule_mode' => (string) ($existingAgent['schedule_mode'] ?? 'manual'),
            'interval_minutes' => (int) ($existingAgent['interval_minutes'] ?? 60),
            'cron_expression' => (string) ($existingAgent['cron_expression'] ?? ''),
            'payload_json' => json_encode(['job_id' => $jobId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active' => (int) ($existingAgent['is_active'] ?? 1),
            'priority' => (int) ($existingAgent['priority'] ?? 70),
            'weight' => (int) ($existingAgent['weight'] ?? 5),
            'max_runtime_sec' => (int) ($existingAgent['max_runtime_sec'] ?? 1800),
            'lock_ttl_sec' => (int) ($existingAgent['lock_ttl_sec'] ?? 2100),
            'retry_delay_sec' => (int) ($existingAgent['retry_delay_sec'] ?? 600),
            'next_run_at' => (string) ($existingAgent['next_run_at'] ?? ''),
        ];

        return CronAgentService::saveAgent($agentData);
    }

    private function getIniSizeInBytes($val): int {
        $val = trim((string)$val);
        if ($val === '') {
            return 0;
        }
        $last = strtolower($val[strlen($val) - 1]);
        $num = (int)$val;
        switch ($last) {
            case 'g':
                $num *= 1024;
                // no break
            case 'm':
                $num *= 1024;
                // no break
            case 'k':
                $num *= 1024;
                // no break
        }
        return $num;
    }

    private function normalizeListForUi(string $raw): array {
        $items = preg_split('/[\r\n,;]+/', $raw);
        if (!is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            $item = strtolower(trim((string)$item));
            if ($item === '') {
                continue;
            }
            $result[$item] = $item;
        }
        return array_values($result);
    }

    private function parseImportIdMap(string $raw): array {
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
            $localId = (int)trim((string)$parts[1]);
            if ($sourceId === '' || $localId <= 0) {
                continue;
            }
            $result[$sourceId] = $localId;
        }
        return $result;
    }

    private function getLocalCategoryTypesForImport(string $languageCode = ENV_DEF_LANG): array {
        $rows = SafeMySQL::gi()->getAll(
            'SELECT type_id, name FROM ?n WHERE language_code = ?s ORDER BY name ASC',
            Constants::CATEGORIES_TYPES_TABLE,
            strtoupper($languageCode)
        );
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $typeId = (int)($row['type_id'] ?? 0);
            $name = trim((string)($row['name'] ?? ''));
            if ($typeId <= 0 || $name === '') {
                continue;
            }
            $result[] = [
                'id' => $typeId,
                'name' => $name,
            ];
        }
        return $result;
    }

    private function getLocalPropertySetsForImport(string $languageCode = ENV_DEF_LANG): array {
        $rows = SafeMySQL::gi()->getAll(
            'SELECT set_id, name FROM ?n WHERE language_code = ?s ORDER BY name ASC',
            Constants::PROPERTY_SETS_TABLE,
            strtoupper($languageCode)
        );
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $setId = (int)($row['set_id'] ?? 0);
            $name = trim((string)($row['name'] ?? ''));
            if ($setId <= 0 || $name === '') {
                continue;
            }
            $result[] = [
                'id' => $setId,
                'name' => $name,
            ];
        }
        return $result;
    }

    private function getLocalPropertiesForImport(string $languageCode = ENV_DEF_LANG): array {
        $rows = SafeMySQL::gi()->getAll(
            'SELECT property_id, name, default_values FROM ?n WHERE language_code = ?s ORDER BY name ASC, property_id ASC',
            Constants::PROPERTIES_TABLE,
            strtoupper($languageCode)
        );
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $propertyId = (int)($row['property_id'] ?? 0);
            $name = trim((string)($row['name'] ?? ''));
            if ($propertyId <= 0 || $name === '') {
                continue;
            }
            $fieldOptions = [];
            $decodedDefaults = null;
            $rawDefaults = $row['default_values'] ?? '';
            if (is_string($rawDefaults)) {
                $trimmedDefaults = trim($rawDefaults);
                if ($trimmedDefaults !== '' && SysClass::ee_isValidJson($trimmedDefaults)) {
                    $parsedDefaults = json_decode($trimmedDefaults, true);
                    if (is_array($parsedDefaults)) {
                        $decodedDefaults = $parsedDefaults;
                    }
                }
            } elseif (is_array($rawDefaults)) {
                $decodedDefaults = $rawDefaults;
            }

            if (is_array($decodedDefaults)) {
                foreach ($decodedDefaults as $fieldIndex => $fieldRow) {
                    if (!is_array($fieldRow)) {
                        continue;
                    }
                    $fieldType = strtolower(trim((string)($fieldRow['type'] ?? 'text')));
                    if ($fieldType === '') {
                        $fieldType = 'text';
                    }
                    $fieldTitle = trim((string)($fieldRow['title'] ?? ''));
                    $fieldLabelRaw = $fieldRow['label'] ?? '';
                    $fieldLabel = '';
                    if (is_array($fieldLabelRaw)) {
                        $labelParts = [];
                        foreach ($fieldLabelRaw as $labelItem) {
                            $labelText = trim((string)$labelItem);
                            if ($labelText !== '') {
                                $labelParts[] = $labelText;
                            }
                        }
                        $fieldLabel = implode(', ', $labelParts);
                    } else {
                        $fieldLabel = trim((string)$fieldLabelRaw);
                    }
                    $fieldName = trim((string)($fieldRow['name'] ?? ''));
                    if ($fieldName === '') {
                        if ($fieldTitle !== '') {
                            $fieldName = $fieldTitle;
                        } elseif ($fieldLabel !== '') {
                            $fieldName = $fieldLabel;
                        } else {
                            $fieldName = 'Field #' . ((int)$fieldIndex + 1);
                        }
                    }
                    $fieldOptions[] = [
                        'index' => (int)$fieldIndex,
                        'name' => $fieldName,
                        'title' => $fieldTitle,
                        'label' => $fieldLabel,
                        'type' => $fieldType,
                    ];
                }
            }

            $result[] = [
                'id' => $propertyId,
                'name' => $name,
                'fields' => $fieldOptions,
            ];
        }
        return $result;
    }

    private function readImportPackageManifest(int $fileId): array {
        if ($fileId <= 0) {
            return [];
        }

        $fileData = FileSystem::getFileData($fileId, false);
        if (!is_array($fileData)) {
            return [];
        }

        $path = trim((string)($fileData['file_path'] ?? ''));
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return [];
        }

        $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return [];
        }

        $zip = new \ZipArchive();
        $open = $zip->open($path);
        if ($open !== true) {
            return [];
        }

        try {
            $manifestName = '';
            $manifestIndex = $zip->locateName('manifest.json');
            if (is_int($manifestIndex)) {
                $manifestName = (string)$zip->getNameIndex($manifestIndex);
            } else {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = (string)$zip->getNameIndex($i);
                    if (strtolower((string)pathinfo($name, PATHINFO_BASENAME)) === 'manifest.json') {
                        $manifestName = $name;
                        break;
                    }
                }
            }

            if ($manifestName === '') {
                return [];
            }

            $rawManifest = $zip->getFromName($manifestName);
            if (!is_string($rawManifest) || trim($rawManifest) === '') {
                return [];
            }

            $decoded = json_decode($rawManifest, true);
            return is_array($decoded) ? $decoded : [];
        } finally {
            $zip->close();
        }
    }

    private function extractSourceCatalogFromManifest(array $manifest): array {
        $catalog = ['category_types' => [], 'property_sets' => []];
        $sourceCatalog = $manifest['source_catalog'] ?? null;
        if (!is_array($sourceCatalog) || (empty($sourceCatalog['category_types']) && empty($sourceCatalog['property_sets']))) {
            $selection = is_array($manifest['selection'] ?? null) ? $manifest['selection'] : [];
            $taxonomies = is_array($selection['taxonomies'] ?? null) ? $selection['taxonomies'] : [];
            $postTypes = is_array($selection['post_types'] ?? null) ? $selection['post_types'] : [];

            foreach ($taxonomies as $taxonomy) {
                $taxonomy = strtolower(trim((string)$taxonomy));
                if ($taxonomy === '') {
                    continue;
                }
                $sourceId = 'taxonomy:' . $taxonomy;
                $catalog['category_types'][] = [
                    'source_id' => $sourceId,
                    'name' => 'Таксономия: ' . $taxonomy,
                    'description' => '',
                    'kind' => 'taxonomy',
                ];
                $catalog['property_sets'][] = [
                    'source_id' => $sourceId,
                    'name' => 'Набор таксономии: ' . $taxonomy,
                    'description' => '',
                    'kind' => 'taxonomy',
                ];
            }

            foreach ($postTypes as $postType) {
                $postType = strtolower(trim((string)$postType));
                if ($postType === '') {
                    continue;
                }
                $sourceId = 'post_type:' . $postType;
                $catalog['category_types'][] = [
                    'source_id' => $sourceId,
                    'name' => 'Тип записи: ' . $postType,
                    'description' => '',
                    'kind' => 'post_type',
                ];
                $catalog['property_sets'][] = [
                    'source_id' => $sourceId,
                    'name' => 'Набор типа записи: ' . $postType,
                    'description' => '',
                    'kind' => 'post_type',
                ];
            }

            return $catalog;
        }

        foreach (['category_types', 'property_sets'] as $section) {
            $items = $sourceCatalog[$section] ?? [];
            if (!is_array($items)) {
                continue;
            }
            $out = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $sourceId = strtolower(trim((string)($item['source_id'] ?? '')));
                if ($sourceId === '') {
                    continue;
                }
                $name = trim((string)($item['name'] ?? $item['title'] ?? $sourceId));
                $description = trim((string)($item['description'] ?? ''));
                $kind = trim((string)($item['kind'] ?? $item['source_kind'] ?? ''));
                $out[] = [
                    'source_id' => $sourceId,
                    'name' => $name !== '' ? $name : $sourceId,
                    'description' => $description,
                    'kind' => $kind,
                ];
            }
            $catalog[$section] = $out;
        }

        return $catalog;
    }

    private function buildImportPropertyPreview(
        int $fileId,
        array $manifest,
        array $sourceCatalog,
        array $currentSetMap,
        array $localPropertySets,
        int $jobId
    ): array {
        $preview = [
            'rows' => [],
            'total' => 0,
            'truncated' => false,
            'has_data' => false,
            'meta_options' => [],
        ];
        if ($fileId <= 0 || empty($manifest)) {
            return $preview;
        }

        $propertiesRows = $this->readImportPackagePhaseRows($fileId, $manifest, 'properties', 4000);
        if (empty($propertiesRows)) {
            return $preview;
        }
        $setPropertyLinkRows = $this->readImportPackagePhaseRows($fileId, $manifest, 'set_property_links', 12000);

        $setNamesBySourceId = [];
        $propertySetCatalog = is_array($sourceCatalog['property_sets'] ?? null)
            ? $sourceCatalog['property_sets']
            : [];
        foreach ($propertySetCatalog as $setItem) {
            if (!is_array($setItem)) {
                continue;
            }
            $sourceId = strtolower(trim((string)($setItem['source_id'] ?? '')));
            if ($sourceId === '') {
                continue;
            }
            $setNamesBySourceId[$sourceId] = trim((string)($setItem['name'] ?? $sourceId));
        }

        $sourceSetsByProperty = [];
        $allSetSourceIds = [];
        foreach ($setPropertyLinkRows as $linkRow) {
            if (!is_array($linkRow)) {
                continue;
            }
            $propertySourceId = strtolower(trim((string)($linkRow['property_source_id'] ?? $linkRow['source_property_id'] ?? '')));
            if ($propertySourceId === '') {
                $propertySourceId = strtolower(trim((string)($linkRow['source_id'] ?? '')));
            }
            if ($propertySourceId === '') {
                continue;
            }

            $setSourceId = strtolower(trim((string)($linkRow['set_source_id'] ?? $linkRow['property_set_source_id'] ?? '')));
            if ($setSourceId === '') {
                continue;
            }

            if (!isset($sourceSetsByProperty[$propertySourceId])) {
                $sourceSetsByProperty[$propertySourceId] = [];
            }
            $sourceSetsByProperty[$propertySourceId][$setSourceId] = $setSourceId;
            $allSetSourceIds[$setSourceId] = $setSourceId;
        }

        $allPropertySourceIds = [];
        foreach ($propertiesRows as $propertyRow) {
            if (!is_array($propertyRow)) {
                continue;
            }
            $propertySourceId = strtolower(trim((string)($propertyRow['source_id'] ?? $propertyRow['property_source_id'] ?? '')));
            if ($propertySourceId === '') {
                continue;
            }
            $allPropertySourceIds[$propertySourceId] = $propertySourceId;
            if (isset($sourceSetsByProperty[$propertySourceId])) {
                foreach ($sourceSetsByProperty[$propertySourceId] as $setSourceId) {
                    $allSetSourceIds[$setSourceId] = $setSourceId;
                }
            }
        }

        $sampleValuesByPropertySourceId = $this->collectImportPropertyValueSamples(
            $fileId,
            $manifest,
            array_values($allPropertySourceIds),
            2,
            50000
        );

        $sourceKey = trim((string)($manifest['source_key'] ?? ''));
        if ($sourceKey === '') {
            $sourceKey = 'default';
        }

        $mappedSets = $this->getImportMapLocalIdsBySourceIds(
            $jobId,
            $sourceKey,
            'property_set',
            array_values($allSetSourceIds)
        );
        $mappedProperties = $this->getImportMapLocalIdsBySourceIds(
            $jobId,
            $sourceKey,
            'property',
            array_values($allPropertySourceIds)
        );

        $localSetNamesById = [];
        foreach ($localPropertySets as $localSet) {
            if (!is_array($localSet)) {
                continue;
            }
            $setId = (int)($localSet['id'] ?? 0);
            if ($setId <= 0) {
                continue;
            }
            $localSetNamesById[$setId] = trim((string)($localSet['name'] ?? ('set#' . $setId)));
        }
        $missingSetIds = [];
        foreach ($mappedSets as $mappedSetId) {
            $mappedSetId = (int)$mappedSetId;
            if ($mappedSetId <= 0 || isset($localSetNamesById[$mappedSetId])) {
                continue;
            }
            $missingSetIds[$mappedSetId] = $mappedSetId;
        }
        if (!empty($missingSetIds)) {
            $localSetNamesById += $this->getLocalSetNamesByIds(array_values($missingSetIds));
        }

        $propertyNameById = [];
        $mappedPropertyIds = array_values(array_unique(array_map('intval', array_values($mappedProperties))));
        if (!empty($mappedPropertyIds)) {
            $propertyNameById = $this->getLocalPropertyNamesByIds($mappedPropertyIds);
        }

        $rows = [];
        foreach ($propertiesRows as $propertyRow) {
            if (!is_array($propertyRow)) {
                continue;
            }
            $propertySourceId = strtolower(trim((string)($propertyRow['source_id'] ?? $propertyRow['property_source_id'] ?? '')));
            if ($propertySourceId === '') {
                continue;
            }

            $sourceSetIds = [];
            if (isset($sourceSetsByProperty[$propertySourceId])) {
                $sourceSetIds = array_values($sourceSetsByProperty[$propertySourceId]);
            }

            $targetSetLabels = [];
            foreach ($sourceSetIds as $sourceSetId) {
                $targetSetId = (int)($currentSetMap[$sourceSetId] ?? 0);
                $targetSetTitle = '';
                if ($targetSetId > 0) {
                    $targetSetTitle = trim((string)($localSetNamesById[$targetSetId] ?? ('set#' . $targetSetId)));
                    $targetSetLabels[] = '#' . $targetSetId . ' ' . $targetSetTitle;
                    continue;
                }

                $mappedSetId = (int)($mappedSets[$sourceSetId] ?? 0);
                if ($mappedSetId > 0) {
                    $targetSetTitle = trim((string)($localSetNamesById[$mappedSetId] ?? ('set#' . $mappedSetId)));
                    $targetSetLabels[] = '#' . $mappedSetId . ' ' . $targetSetTitle;
                    continue;
                }

                $sourceSetTitle = trim((string)($setNamesBySourceId[$sourceSetId] ?? $sourceSetId));
                $targetSetLabels[] = 'авто: ' . $sourceSetTitle;
            }
            $targetSetLabels = array_values(array_unique(array_filter($targetSetLabels, static fn($v) => trim((string)$v) !== '')));

            $mappedPropertyId = (int)($mappedProperties[$propertySourceId] ?? 0);
            $targetProperty = 'создать автоматически';
            if ($mappedPropertyId > 0) {
                $propertyName = trim((string)($propertyNameById[$mappedPropertyId] ?? ('property#' . $mappedPropertyId)));
                $targetProperty = '#' . $mappedPropertyId . ' ' . $propertyName;
            }

            $metaKey = $this->extractMetaKeyFromPropertySourceId($propertySourceId);
            $displayName = $this->resolvePropertyPreviewDisplayName($metaKey, $propertyRow);
            $sampleValues = $sampleValuesByPropertySourceId[$propertySourceId] ?? [];
            if ($this->isLikelyAcfTechnicalMetaKey($metaKey, $sampleValues) && str_starts_with($metaKey, '_')) {
                $pairSourceId = $this->buildPropertySourceIdWithMetaKey($propertySourceId, ltrim($metaKey, '_'));
                if ($pairSourceId !== '') {
                    $pairSamples = $sampleValuesByPropertySourceId[$pairSourceId] ?? [];
                    if (!empty($pairSamples)) {
                        $sampleValues = $pairSamples;
                    }
                }
            }
            $sampleValue = trim((string)($sampleValues[0] ?? ''));

            $rows[] = [
                'property_source_id' => $propertySourceId,
                'meta_key' => $metaKey,
                'display_name' => $displayName,
                'type_fields' => $this->extractTypeFieldsFromPropertyRow($propertyRow),
                'source_set_ids' => $sourceSetIds,
                'target_sets' => $targetSetLabels,
                'target_property' => $targetProperty,
                'sample_value' => $sampleValue,
                'sample_values' => $sampleValues,
                'is_acf_technical' => $this->isLikelyAcfTechnicalMetaKey($metaKey, $sampleValues) ? 1 : 0,
            ];
        }

        $rowIndexByMetaKey = [];
        foreach ($rows as $idx => $previewRow) {
            if (!is_array($previewRow)) {
                continue;
            }
            $metaLookup = strtolower(trim((string)($previewRow['meta_key'] ?? '')));
            if ($metaLookup === '' || isset($rowIndexByMetaKey[$metaLookup])) {
                continue;
            }
            $rowIndexByMetaKey[$metaLookup] = $idx;
        }
        foreach ($rows as $idx => $previewRow) {
            if (!is_array($previewRow)) {
                continue;
            }
            $metaKey = trim((string)($previewRow['meta_key'] ?? ''));
            if ($metaKey === '') {
                continue;
            }
            $sampleValues = is_array($previewRow['sample_values'] ?? null) ? $previewRow['sample_values'] : [];
            $isAcfTech = $this->isLikelyAcfTechnicalMetaKey($metaKey, $sampleValues);
            $rows[$idx]['is_acf_technical'] = $isAcfTech ? 1 : 0;
            if (!$isAcfTech) {
                continue;
            }

            $displayName = trim((string)($previewRow['display_name'] ?? ''));
            if (str_starts_with($metaKey, '_')) {
                $pairMetaKey = ltrim($metaKey, '_');
                $pairLookup = strtolower($pairMetaKey);
                if ($pairMetaKey !== '' && isset($rowIndexByMetaKey[$pairLookup])) {
                    $pairRow = $rows[$rowIndexByMetaKey[$pairLookup]] ?? [];
                    if (is_array($pairRow)) {
                        $pairDisplayName = trim((string)($pairRow['display_name'] ?? ''));
                        if ($pairDisplayName === '') {
                            $pairDisplayName = $this->humanizeMetaKeyForPreview($pairMetaKey);
                        }
                        if ($pairDisplayName !== '') {
                            $displayName = 'ACF ключ поля: ' . $pairDisplayName;
                        }
                    }
                }
            }
            if ($displayName === '' || $displayName === $this->humanizeMetaKeyForPreview($metaKey)) {
                $displayName = 'ACF технический ключ';
            }
            $rows[$idx]['display_name'] = $displayName;
        }

        usort($rows, static function (array $left, array $right): int {
            $leftKey = strtolower(trim((string)($left['meta_key'] ?? '')));
            $rightKey = strtolower(trim((string)($right['meta_key'] ?? '')));
            if ($leftKey === $rightKey) {
                $leftSource = strtolower(trim((string)($left['property_source_id'] ?? '')));
                $rightSource = strtolower(trim((string)($right['property_source_id'] ?? '')));
                return $leftSource <=> $rightSource;
            }
            return $leftKey <=> $rightKey;
        });

        $preview['total'] = count($rows);
        // Показываем весь собранный срез предпросмотра (до лимита чтения phase rows),
        // чтобы не скрывать значимую часть meta-ключей в UI.
        $previewLimit = 4000;
        if ($preview['total'] > $previewLimit) {
            $preview['truncated'] = true;
            $rows = array_slice($rows, 0, $previewLimit);
        }

        $preview['meta_options'] = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sourceId = strtolower(trim((string)($row['property_source_id'] ?? '')));
            if ($sourceId === '') {
                continue;
            }
            $preview['meta_options'][] = [
                'property_source_id' => $sourceId,
                'meta_key' => trim((string)($row['meta_key'] ?? '')),
                'display_name' => trim((string)($row['display_name'] ?? '')),
                'sample_value' => trim((string)($row['sample_value'] ?? '')),
                'type_fields' => is_array($row['type_fields'] ?? null) ? array_values($row['type_fields']) : [],
                'source_set_ids' => is_array($row['source_set_ids'] ?? null) ? array_values($row['source_set_ids']) : [],
                'is_acf_technical' => !empty($row['is_acf_technical']) ? 1 : 0,
            ];
        }

        $preview['rows'] = $rows;
        $preview['has_data'] = !empty($rows);
        return $preview;
    }

    private function readImportPackagePhaseRows(int $fileId, array $manifest, string $phaseKey, int $maxRows = 5000): array {
        if ($fileId <= 0 || $phaseKey === '' || $maxRows <= 0) {
            return [];
        }

        $manifestFiles = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
        $phaseFile = trim((string)($manifestFiles[$phaseKey] ?? ''));
        if ($phaseFile === '') {
            return [];
        }

        $fileData = FileSystem::getFileData($fileId, false);
        if (!is_array($fileData)) {
            return [];
        }
        $path = trim((string)($fileData['file_path'] ?? ''));
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return [];
        }

        $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return [];
        }

        $zip = new \ZipArchive();
        $open = $zip->open($path);
        if ($open !== true) {
            return [];
        }

        try {
            $entryName = $this->findZipEntryByName($zip, $phaseFile);
            if ($entryName === '') {
                return [];
            }

            $entryExtension = strtolower((string)pathinfo($entryName, PATHINFO_EXTENSION));
            if ($entryExtension === 'jsonl') {
                $stream = $zip->getStream($entryName);
                if (!is_resource($stream)) {
                    return [];
                }
                try {
                    return $this->decodeImportPhaseRowsFromJsonlStream($stream, $maxRows);
                } finally {
                    fclose($stream);
                }
            }

            $raw = $zip->getFromName($entryName);
            if (!is_string($raw) || trim($raw) === '') {
                return [];
            }

            return $this->decodeImportPhaseRows($raw, $maxRows);
        } finally {
            $zip->close();
        }
    }

    private function findZipEntryByName(\ZipArchive $zip, string $fileName): string {
        $fileName = trim(str_replace('\\', '/', $fileName));
        if ($fileName === '') {
            return '';
        }

        $candidates = [$fileName];
        $trimmed = ltrim($fileName, '/');
        if ($trimmed !== $fileName) {
            $candidates[] = $trimmed;
        }

        foreach ($candidates as $candidate) {
            $index = $zip->locateName($candidate, \ZipArchive::FL_NOCASE);
            if (is_int($index)) {
                $name = $zip->getNameIndex($index);
                if (is_string($name) && $name !== '') {
                    return $name;
                }
            }
        }

        $basename = strtolower((string)pathinfo($fileName, PATHINFO_BASENAME));
        if ($basename === '') {
            return '';
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if (strtolower((string)pathinfo($name, PATHINFO_BASENAME)) === $basename) {
                return $name;
            }
        }
        return '';
    }

    private function decodeImportPhaseRows(string $rawContent, int $maxRows = 5000): array {
        $rawContent = trim($rawContent);
        if ($rawContent === '') {
            return [];
        }
        if ($maxRows <= 0) {
            $maxRows = 5000;
        }

        $rows = [];
        $firstChar = $rawContent[0];
        if ($firstChar === '[' || $firstChar === '{') {
            $decoded = json_decode($rawContent, true);
            if (is_array($decoded)) {
                if (array_key_exists('data', $decoded) && is_array($decoded['data'])) {
                    $decoded = $decoded['data'];
                } elseif ($firstChar === '{') {
                    $decoded = [$decoded];
                }
                foreach ($decoded as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $rows[] = $item;
                    if (count($rows) >= $maxRows) {
                        break;
                    }
                }
                if (!empty($rows)) {
                    return $rows;
                }
                // Если JSON валиден, но структура не подходит, попробуем как JSONL.
            }
        }

        $lines = preg_split('/\r\n|\n|\r/', $rawContent);
        if (!is_array($lines)) {
            return [];
        }
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }
            $rows[] = $decoded;
            if (count($rows) >= $maxRows) {
                break;
            }
        }
        return $rows;
    }

    private function decodeImportPhaseRowsFromJsonlStream($stream, int $maxRows = 5000): array {
        if (!is_resource($stream)) {
            return [];
        }
        if ($maxRows <= 0) {
            $maxRows = 5000;
        }

        $rows = [];
        while (!feof($stream)) {
            $line = fgets($stream);
            if (!is_string($line)) {
                continue;
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }

            $rows[] = $decoded;
            if (count($rows) >= $maxRows) {
                break;
            }
        }

        return $rows;
    }

    private function collectImportPropertyValueSamples(
        int $fileId,
        array $manifest,
        array $propertySourceIds,
        int $limitPerProperty = 2,
        int $maxRows = 50000
    ): array {
        $targets = [];
        foreach ($propertySourceIds as $propertySourceId) {
            $propertySourceId = strtolower(trim((string)$propertySourceId));
            if ($propertySourceId === '') {
                continue;
            }
            $targets[$propertySourceId] = true;
        }
        if ($fileId <= 0 || empty($manifest) || empty($targets)) {
            return [];
        }
        if ($limitPerProperty <= 0) {
            $limitPerProperty = 1;
        }
        if ($maxRows <= 0) {
            $maxRows = 50000;
        }

        $manifestFiles = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
        $phaseFile = trim((string)($manifestFiles['property_values'] ?? ''));
        if ($phaseFile === '') {
            return [];
        }

        $fileData = FileSystem::getFileData($fileId, false);
        if (!is_array($fileData)) {
            return [];
        }
        $path = trim((string)($fileData['file_path'] ?? ''));
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return [];
        }

        $zip = new \ZipArchive();
        $open = $zip->open($path);
        if ($open !== true) {
            return [];
        }

        try {
            $entryName = $this->findZipEntryByName($zip, $phaseFile);
            if ($entryName === '') {
                return [];
            }

            $entryExtension = strtolower((string)pathinfo($entryName, PATHINFO_EXTENSION));
            if ($entryExtension === 'jsonl') {
                $stream = $zip->getStream($entryName);
                if (!is_resource($stream)) {
                    return [];
                }
                try {
                    return $this->collectImportPropertyValueSamplesFromJsonlStream($stream, $targets, $limitPerProperty, $maxRows);
                } finally {
                    fclose($stream);
                }
            }

            $rows = $this->readImportPackagePhaseRows($fileId, $manifest, 'property_values', min($maxRows, 5000));
            return $this->buildSampleValuesByPropertySourceId($rows, $limitPerProperty);
        } finally {
            $zip->close();
        }
    }

    private function collectImportPropertyValueSamplesFromJsonlStream(
        $stream,
        array $targets,
        int $limitPerProperty = 2,
        int $maxRows = 50000
    ): array {
        if (!is_resource($stream) || empty($targets)) {
            return [];
        }
        if ($limitPerProperty <= 0) {
            $limitPerProperty = 1;
        }
        if ($maxRows <= 0) {
            $maxRows = 50000;
        }

        $result = [];
        $scanned = 0;
        $completed = 0;
        $targetCount = count($targets);

        while (!feof($stream) && $scanned < $maxRows) {
            $line = fgets($stream);
            if (!is_string($line)) {
                continue;
            }
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $scanned++;
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }

            $propertySourceId = strtolower(trim((string)($decoded['property_source_id'] ?? $decoded['source_property_id'] ?? '')));
            if ($propertySourceId === '' || !isset($targets[$propertySourceId])) {
                continue;
            }

            $sample = $this->extractPreviewSampleValueFromPropertyValueRow($decoded);
            if ($sample === '') {
                continue;
            }

            if (!isset($result[$propertySourceId])) {
                $result[$propertySourceId] = [];
            }
            if (in_array($sample, $result[$propertySourceId], true)) {
                continue;
            }
            if (count($result[$propertySourceId]) >= $limitPerProperty) {
                continue;
            }

            $result[$propertySourceId][] = $sample;
            if (count($result[$propertySourceId]) === $limitPerProperty) {
                $completed++;
                if ($completed >= $targetCount) {
                    break;
                }
            }
        }

        return $result;
    }

    private function getImportMapLocalIdsBySourceIds(
        int $jobId,
        string $sourceKey,
        string $mapType,
        array $sourceIds
    ): array {
        if ($jobId <= 0 || $mapType === '') {
            return [];
        }
        $sourceKey = trim($sourceKey);
        if ($sourceKey === '') {
            $sourceKey = 'default';
        }

        $normalizedSourceIds = [];
        foreach ($sourceIds as $sourceId) {
            $sourceId = strtolower(trim((string)$sourceId));
            if ($sourceId === '') {
                continue;
            }
            $normalizedSourceIds[$sourceId] = $sourceId;
        }
        if (empty($normalizedSourceIds)) {
            return [];
        }

        $result = [];
        $chunks = array_chunk(array_values($normalizedSourceIds), 500);
        foreach ($chunks as $chunk) {
            try {
                $rows = SafeMySQL::gi()->getAll(
                    'SELECT source_id, local_id FROM ?n WHERE job_id = ?i AND source_key = ?s AND map_type = ?s AND source_id IN (?a)',
                    Constants::IMPORT_MAP_TABLE,
                    $jobId,
                    $sourceKey,
                    $mapType,
                    $chunk
                );
            } catch (\Throwable $e) {
                return $result;
            }
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $sourceId = strtolower(trim((string)($row['source_id'] ?? '')));
                $localId = (int)($row['local_id'] ?? 0);
                if ($sourceId === '' || $localId <= 0) {
                    continue;
                }
                $result[$sourceId] = $localId;
            }
        }

        return $result;
    }

    private function getLocalSetNamesByIds(array $setIds, string $languageCode = ENV_DEF_LANG): array {
        $normalizedIds = [];
        foreach ($setIds as $setId) {
            $setId = (int)$setId;
            if ($setId <= 0) {
                continue;
            }
            $normalizedIds[$setId] = $setId;
        }
        if (empty($normalizedIds)) {
            return [];
        }

        $rows = SafeMySQL::gi()->getAll(
            'SELECT set_id, name FROM ?n WHERE set_id IN (?a) AND language_code = ?s',
            Constants::PROPERTY_SETS_TABLE,
            array_values($normalizedIds),
            strtoupper($languageCode)
        );
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $setId = (int)($row['set_id'] ?? 0);
            $name = trim((string)($row['name'] ?? ''));
            if ($setId <= 0) {
                continue;
            }
            $result[$setId] = $name !== '' ? $name : ('set#' . $setId);
        }
        return $result;
    }

    private function getLocalPropertyNamesByIds(array $propertyIds, string $languageCode = ENV_DEF_LANG): array {
        $normalizedIds = [];
        foreach ($propertyIds as $propertyId) {
            $propertyId = (int)$propertyId;
            if ($propertyId <= 0) {
                continue;
            }
            $normalizedIds[$propertyId] = $propertyId;
        }
        if (empty($normalizedIds)) {
            return [];
        }

        $rows = SafeMySQL::gi()->getAll(
            'SELECT property_id, name FROM ?n WHERE property_id IN (?a) AND language_code = ?s',
            Constants::PROPERTIES_TABLE,
            array_values($normalizedIds),
            strtoupper($languageCode)
        );
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $propertyId = (int)($row['property_id'] ?? 0);
            $name = trim((string)($row['name'] ?? ''));
            if ($propertyId <= 0) {
                continue;
            }
            $result[$propertyId] = $name !== '' ? $name : ('property#' . $propertyId);
        }
        return $result;
    }

    private function buildSampleValuesByPropertySourceId(array $propertyValueRows, int $limitPerProperty = 2): array {
        if ($limitPerProperty <= 0) {
            $limitPerProperty = 1;
        }

        $result = [];
        foreach ($propertyValueRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $propertySourceId = strtolower(trim((string)($row['property_source_id'] ?? $row['source_property_id'] ?? '')));
            if ($propertySourceId === '') {
                continue;
            }

            $sample = $this->extractPreviewSampleValueFromPropertyValueRow($row);
            if ($sample === '') {
                continue;
            }

            if (!isset($result[$propertySourceId])) {
                $result[$propertySourceId] = [];
            }
            if (count($result[$propertySourceId]) >= $limitPerProperty) {
                continue;
            }
            if (in_array($sample, $result[$propertySourceId], true)) {
                continue;
            }
            $result[$propertySourceId][] = $sample;
        }
        return $result;
    }

    private function extractPreviewSampleValueFromPropertyValueRow(array $row): string {
        $payload = $row['property_values'] ?? ($row['value'] ?? ($row['values'] ?? null));
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
                    return $this->safeSubstr($trimmed, 160);
                }
            } else {
                return $this->safeSubstr($trimmed, 160);
            }
        }

        if (!is_array($payload)) {
            if ($payload === null || $payload === '') {
                return '';
            }
            return $this->safeSubstr((string)$payload, 160);
        }

        $parts = [];
        foreach ($payload as $field) {
            if (count($parts) >= 2) {
                break;
            }
            if (!is_array($field)) {
                $text = $this->stringifyPreviewSampleValue($field);
                if ($text !== '') {
                    $parts[] = $text;
                }
                continue;
            }
            $valueText = $this->stringifyPreviewSampleValue($field['value'] ?? ($field['default'] ?? ''));
            if ($valueText === '') {
                continue;
            }
            $parts[] = $valueText;
        }

        if (empty($parts)) {
            return '';
        }
        return $this->safeSubstr(implode(' | ', $parts), 220);
    }

    private function stringifyPreviewSampleValue(mixed $value): string {
        if (is_array($value)) {
            if (empty($value)) {
                return '';
            }
            $flat = [];
            foreach ($value as $item) {
                if (count($flat) >= 3) {
                    break;
                }
                if (is_scalar($item)) {
                    $flat[] = trim((string)$item);
                }
            }
            $text = implode(', ', array_filter($flat, static fn($v) => $v !== ''));
            if ($text !== '') {
                return $this->safeSubstr($text, 160);
            }
            return $this->safeSubstr((string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 160);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            $text = trim((string)$value);
            if ($text === '') {
                return '';
            }
            return $this->safeSubstr($text, 160);
        }
        return '';
    }

    private function resolvePropertyPreviewDisplayName(string $metaKey, array $propertyRow): string {
        $metaKey = trim($metaKey);
        $candidates = [];

        $namedFields = ['display_name', 'acf_label', 'label', 'title', 'name'];
        foreach ($namedFields as $field) {
            $value = trim((string)($propertyRow[$field] ?? ''));
            if ($value !== '') {
                $candidates[] = $value;
            }
        }

        $defaultValues = $propertyRow['default_values'] ?? null;
        if (is_string($defaultValues) && SysClass::ee_isValidJson($defaultValues)) {
            $decoded = json_decode($defaultValues, true);
            if (is_array($decoded)) {
                $defaultValues = $decoded;
            }
        }
        if (is_array($defaultValues)) {
            foreach ($defaultValues as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $label = trim((string)($field['title'] ?? $field['label'] ?? ''));
                if ($label !== '') {
                    $candidates[] = $label;
                    break;
                }
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if ($metaKey !== '' && strtolower($candidate) === strtolower($metaKey)) {
                continue;
            }
            if ($this->isLikelyAcfTechnicalMetaKey($candidate)) {
                continue;
            }
            return $candidate;
        }

        return $this->humanizeMetaKeyForPreview($metaKey);
    }

    private function humanizeMetaKeyForPreview(string $metaKey): string {
        $metaKey = trim($metaKey);
        if ($metaKey === '') {
            return '';
        }
        if ((bool)preg_match('/^_?field_[a-z0-9]+$/i', $metaKey)) {
            return 'ACF Field Key';
        }
        $clean = ltrim($metaKey, '_');
        $clean = preg_replace('/_([0-9]+)_/', ' [$1] ', $clean);
        $clean = str_replace(['__', '-'], ['_', '_'], (string)$clean);
        $clean = trim((string)preg_replace('/_+/', ' ', (string)$clean));
        if ($clean === '') {
            $clean = $metaKey;
        }
        return $this->safeTitleCase($clean);
    }

    private function isLikelyAcfTechnicalMetaKey(string $metaKey, array $sampleValues = []): bool {
        $metaKey = strtolower(trim($metaKey));
        if ($metaKey === '') {
            return false;
        }
        if ((bool)preg_match('/^_?field_[a-z0-9]+$/', $metaKey)) {
            return true;
        }
        if (str_starts_with($metaKey, '_') && !empty($sampleValues)) {
            foreach ($sampleValues as $sample) {
                $sample = strtolower(trim((string)$sample));
                if ($sample === '') {
                    continue;
                }
                if ((bool)preg_match('/\bfield_[a-z0-9]+\b/', $sample)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function safeSubstr(string $text, int $limit): string {
        if ($limit <= 0) {
            return '';
        }
        if (function_exists('mb_substr')) {
            return (string)mb_substr($text, 0, $limit);
        }
        return substr($text, 0, $limit);
    }

    private function safeTitleCase(string $text): string {
        if (function_exists('mb_convert_case')) {
            return (string)mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
        }
        return ucwords(strtolower($text));
    }

    private function extractMetaKeyFromPropertySourceId(string $propertySourceId): string {
        $propertySourceId = trim($propertySourceId);
        if ($propertySourceId === '') {
            return '';
        }
        $parts = explode(':', $propertySourceId, 2);
        if (count($parts) === 2) {
            return trim((string)$parts[1]);
        }
        return $propertySourceId;
    }

    private function buildPropertySourceIdWithMetaKey(string $propertySourceId, string $metaKey): string {
        $propertySourceId = trim($propertySourceId);
        $metaKey = trim($metaKey);
        if ($propertySourceId === '' || $metaKey === '') {
            return '';
        }
        $parts = explode(':', $propertySourceId, 2);
        if (count($parts) !== 2) {
            return '';
        }
        $prefix = strtolower(trim((string)$parts[0]));
        if ($prefix !== 'postmeta' && $prefix !== 'termmeta') {
            return '';
        }
        return $prefix . ':' . strtolower($metaKey);
    }

    private function extractTypeFieldsFromPropertyRow(array $row): array {
        $typeFields = $this->normalizeTypeFieldsValue($row['type_fields'] ?? null);
        if (!empty($typeFields)) {
            return $typeFields;
        }

        $defaultValues = $row['default_values'] ?? null;
        if (is_string($defaultValues)) {
            $decodedDefaults = json_decode($defaultValues, true);
            if (is_array($decodedDefaults)) {
                $defaultValues = $decodedDefaults;
            }
        }
        if (is_array($defaultValues)) {
            foreach ($defaultValues as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $fieldType = strtolower(trim((string)($field['type'] ?? '')));
                if ($fieldType === '') {
                    continue;
                }
                $typeFields[] = $fieldType;
            }
            $typeFields = array_values(array_unique($typeFields));
            if (!empty($typeFields)) {
                return $typeFields;
            }
        }

        $typeSourceId = strtolower(trim((string)($row['type_source_id'] ?? '')));
        if ($typeSourceId === '') {
            $typeSourceId = strtolower(trim((string)($row['type_name'] ?? '')));
        }

        $inferred = $this->inferTypeFieldsByTypeSourceId($typeSourceId);
        if (!empty($inferred)) {
            return $inferred;
        }
        return ['text'];
    }

    private function normalizeTypeFieldsValue(mixed $value): array {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [];
            }
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $this->normalizeTypeFieldsValue($decoded);
            }
            if (str_contains($value, ',')) {
                $parts = explode(',', $value);
                $result = [];
                foreach ($parts as $part) {
                    $part = strtolower(trim((string)$part));
                    if ($part === '') {
                        continue;
                    }
                    $result[] = $part;
                }
                return array_values(array_unique($result));
            }
            return [strtolower($value)];
        }

        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $field) {
            if (is_array($field)) {
                $fieldType = strtolower(trim((string)($field['type'] ?? '')));
                if ($fieldType !== '') {
                    $result[] = $fieldType;
                }
                continue;
            }
            $fieldType = strtolower(trim((string)$field));
            if ($fieldType !== '') {
                $result[] = $fieldType;
            }
        }
        return array_values(array_unique($result));
    }

    private function inferTypeFieldsByTypeSourceId(string $typeSourceId): array {
        $typeSourceId = strtolower(trim($typeSourceId));
        if ($typeSourceId === '') {
            return [];
        }
        if (str_contains($typeSourceId, 'image')) {
            return ['image'];
        }
        if (str_contains($typeSourceId, 'file')) {
            return ['file'];
        }
        if (str_contains($typeSourceId, 'bool')) {
            return ['checkbox'];
        }
        if (str_contains($typeSourceId, 'date')) {
            return ['date'];
        }
        if (str_contains($typeSourceId, 'time')) {
            return ['time'];
        }
        if (str_contains($typeSourceId, 'number') || str_contains($typeSourceId, 'int') || str_contains($typeSourceId, 'float')) {
            return ['number'];
        }
        if (str_contains($typeSourceId, 'text') || str_contains($typeSourceId, 'string')) {
            return ['text'];
        }
        return [];
    }

    private function processPropertyDefinitionsImportRequest(): void {
        $redirectUrl = '/admin/import_property_definitions';
        $action = strtolower(trim((string)($_POST['property_definitions_action'] ?? 'prepare_preview')));

        if ($action === 'confirm_import') {
            $editorState = [];

            try {
                $editorState = $this->decodePropertyDefinitionsEditorState(
                    (string)($_POST['property_definition_editor_state'] ?? '')
                );
                $importPayload = $this->buildPropertyDefinitionsPayloadFromEditorState($editorState);
                $report = $this->importPropertyDefinitionsPayload($importPayload);

                ClassNotifications::addNotificationUser($this->logged_in, [
                    'text' => $this->formatPropertyDefinitionsImportReport($report),
                    'status' => 'success',
                ]);
                SysClass::handleRedirect(200, $redirectUrl);
                return;
            } catch (\Throwable $exception) {
                $this->renderPropertyDefinitionsImportPage([
                    'preview_editor_state' => $editorState,
                    'preview_warnings' => is_array($editorState['warnings'] ?? null) ? $editorState['warnings'] : [],
                    'preview_error_message' => $exception->getMessage(),
                    'preview_source_filename' => trim((string)($editorState['source_filename'] ?? '')),
                ]);
                return;
            }
        }

        try {
            $uploadData = $this->readUploadedPropertyDefinitionsPayload();
            $editorState = $this->buildPropertyDefinitionsEditorState(
                $uploadData['payload'],
                is_array($uploadData['warnings'] ?? null) ? $uploadData['warnings'] : [],
                (string)($uploadData['original_name'] ?? '')
            );

            $this->renderPropertyDefinitionsImportPage([
                'preview_editor_state' => $editorState,
                'preview_warnings' => is_array($editorState['warnings'] ?? null) ? $editorState['warnings'] : [],
                'preview_source_filename' => (string)($uploadData['original_name'] ?? ''),
            ]);
        } catch (\Throwable $exception) {
            $this->renderPropertyDefinitionsImportPage([
                'preview_error_message' => $exception->getMessage(),
            ]);
        }
        return;

        $redirectUrl = '/admin/import_property_definitions';
        $file = $_FILES['property_definitions_file'] ?? null;
        if (!is_array($file)) {
            ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => 'Загрузите JSON-файл с описанием типов свойств, свойств и наборов.',
                'status' => 'danger',
            ]);
            SysClass::handleRedirect(200, $redirectUrl);
        }

        $fileError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($fileError === UPLOAD_ERR_NO_FILE) {
            ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => 'Выберите JSON-файл для импорта.',
                'status' => 'danger',
            ]);
            SysClass::handleRedirect(200, $redirectUrl);
        }

        if ($fileError !== UPLOAD_ERR_OK) {
            ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => FileSystem::getErrorDescriptionByUploadCode($fileError),
                'status' => 'danger',
            ]);
            SysClass::handleRedirect(200, $redirectUrl);
        }

        $originalName = trim((string)($file['name'] ?? ''));
        if (strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION)) !== 'json') {
            ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => 'Поддерживается только JSON-файл с расширением .json.',
                'status' => 'danger',
            ]);
            SysClass::handleRedirect(200, $redirectUrl);
        }

        $tmpPath = trim((string)($file['tmp_name'] ?? ''));
        $rawPayload = $tmpPath !== '' ? @file_get_contents($tmpPath) : false;
        if ($rawPayload === false) {
            ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => 'Не удалось прочитать загруженный JSON-файл.',
                'status' => 'danger',
            ]);
            SysClass::handleRedirect(200, $redirectUrl);
        }

        try {
            $payload = $this->decodePropertyDefinitionsPayload((string)$rawPayload);
            $report = $this->importPropertyDefinitionsPayload($payload);
            ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => $this->formatPropertyDefinitionsImportReport($report),
                'status' => 'success',
            ]);
        } catch (\Throwable $exception) {
            ClassNotifications::addNotificationUser($this->logged_in, [
                'text' => htmlspecialchars($exception->getMessage(), ENT_QUOTES),
                'status' => 'danger',
            ]);
        }

        SysClass::handleRedirect(200, $redirectUrl);
    }

    private function readUploadedPropertyDefinitionsPayload(): array {
        $file = $_FILES['property_definitions_file'] ?? null;
        if (!is_array($file)) {
            throw new \RuntimeException(
                'Загрузите JSON-файл с описанием типов свойств, свойств и наборов.'
            );
        }

        $fileError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($fileError === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Выберите JSON-файл для импорта.');
        }

        if ($fileError !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(FileSystem::getErrorDescriptionByUploadCode($fileError));
        }

        $originalName = trim((string)($file['name'] ?? ''));
        if (strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION)) !== 'json') {
            throw new \RuntimeException('Поддерживается только JSON-файл с расширением .json.');
        }

        $tmpPath = trim((string)($file['tmp_name'] ?? ''));
        $rawPayload = $tmpPath !== '' ? @file_get_contents($tmpPath) : false;
        if ($rawPayload === false) {
            throw new \RuntimeException('Не удалось прочитать загруженный JSON-файл.');
        }

        $decodedPayload = $this->decodePropertyDefinitionsPayload((string)$rawPayload);
        $draftPayload = $this->normalizePropertyDefinitionsPayload($decodedPayload, true);

        return [
            'original_name' => $originalName,
            'payload' => $draftPayload,
            'warnings' => $this->buildPropertyDefinitionsDraftWarnings($draftPayload),
        ];
    }

    private function buildPropertyDefinitionsPreviewPayload(array $payload): array {
        $normalizedPayload = $this->normalizePropertyDefinitionsPayload($payload);

        return [
            'schema' => 'ee_property_definitions_import',
            'version' => 1,
            'language_code' => $normalizedPayload['language_code'],
            'property_types' => $normalizedPayload['property_types'],
            'properties' => $normalizedPayload['properties'],
            'property_sets' => $normalizedPayload['property_sets'],
        ];
    }

    private function encodePropertyDefinitionsPreviewPayload(array $payload): string {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \RuntimeException('Не удалось подготовить данные формы для повторной отправки.');
        }
        return $encoded;
    }

    private function decodePropertyDefinitionsPreviewPayload(string $rawPayload): array {
        if (trim($rawPayload) === '') {
            throw new \RuntimeException('Не найдены подготовленные данные для импорта. Загрузите JSON заново.');
        }
        return $this->decodePropertyDefinitionsPayload($rawPayload);
    }

    private function encodePropertyDefinitionsEditorState(array $state): string {
        $encoded = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \RuntimeException('Не удалось подготовить состояние формы для повторной отправки.');
        }

        return base64_encode($encoded);
    }

    private function decodePropertyDefinitionsEditorState(string $rawState): array {
        if (trim($rawState) === '') {
            throw new \RuntimeException('Не найдено состояние формы. Загрузите JSON заново.');
        }

        $rawState = trim($rawState);
        if ($rawState !== '' && !in_array($rawState[0], ['{', '['], true)) {
            $decodedState = base64_decode($rawState, true);
            if ($decodedState === false || trim($decodedState) === '') {
                throw new \RuntimeException('Некорректное состояние формы.');
            }
            $rawState = $decodedState;
        }

        $rawState = (string)preg_replace('/^\xEF\xBB\xBF/', '', $rawState);
        $decoded = json_decode($rawState, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Некорректное состояние формы.');
        }

        return $decoded;
    }

    private function buildPropertyDefinitionsEditorState(
        array $draftPayload,
        array $warnings = [],
        string $sourceFilename = ''
    ): array {
        $propertyTypesByCode = [];
        foreach (($draftPayload['property_types'] ?? []) as $propertyType) {
            if (!is_array($propertyType)) {
                continue;
            }

            $typeCode = strtolower(trim((string)($propertyType['code'] ?? '')));
            if ($typeCode === '') {
                continue;
            }

            $propertyTypesByCode[$typeCode] = $propertyType;
        }

        $propertySetRowsByPropertyCode = [];
        foreach (($draftPayload['property_sets'] ?? []) as $propertySet) {
            if (!is_array($propertySet)) {
                continue;
            }

            $setCode = strtolower(trim((string)($propertySet['code'] ?? '')));
            $setName = trim((string)($propertySet['name'] ?? ''));
            foreach ((array)($propertySet['properties'] ?? []) as $propertyCode) {
                $propertyCode = strtolower(trim((string)$propertyCode));
                if ($propertyCode === '') {
                    continue;
                }

                $propertySetRowsByPropertyCode[$propertyCode][] = [
                    'code' => $setCode,
                    'name' => $setName,
                    'description' => trim((string)($propertySet['description'] ?? '')),
                ];
            }
        }

        $properties = [];
        foreach (($draftPayload['properties'] ?? []) as $property) {
            if (!is_array($property)) {
                continue;
            }

            $propertyCode = strtolower(trim((string)($property['code'] ?? '')));
            if ($propertyCode === '') {
                continue;
            }

            $typeCode = strtolower(trim((string)($property['type_code'] ?? '')));
            $typeRecord = is_array($propertyTypesByCode[$typeCode] ?? null) ? $propertyTypesByCode[$typeCode] : [];
            $fields = $this->buildPropertyDefinitionsEditorFields(
                is_array($property['default_values'] ?? null) ? $property['default_values'] : [],
                $propertyCode
            );
            $mergeMeta = $this->canMergePropertyDefinitionInPreview($property, $fields);

            $properties[] = [
                'code' => $propertyCode,
                'name' => trim((string)($property['name'] ?? '')),
                'type_name' => trim((string)($typeRecord['name'] ?? '')),
                'status' => trim((string)($property['status'] ?? 'active')),
                'sort' => (int)($property['sort'] ?? 100),
                'entity_type' => trim((string)($property['entity_type'] ?? 'all')),
                'is_multiple' => $this->isPreviewCheckboxChecked($property['is_multiple'] ?? null) ? 1 : 0,
                'is_required' => $this->isPreviewCheckboxChecked($property['is_required'] ?? null) ? 1 : 0,
                'description' => trim((string)($property['description'] ?? '')),
                'enabled' => 1,
                'fields' => $fields,
                'sets' => $this->buildPropertyDefinitionsEditorSets(
                    $propertySetRowsByPropertyCode[$propertyCode] ?? [],
                    $propertyCode
                ),
                'merged_into' => '',
                'merge_sources' => [],
                'merge_allowed' => $mergeMeta['allowed'] ? 1 : 0,
                'merge_block_reason' => $mergeMeta['reason'],
                'original_type_code' => $typeCode,
                'original_type_name' => trim((string)($typeRecord['name'] ?? '')),
                'original_field_types' => is_array($typeRecord['fields'] ?? null)
                    ? array_values(array_map(static function ($fieldType): string {
                        return strtolower(trim((string)$fieldType));
                    }, $typeRecord['fields']))
                    : [],
            ];
        }

        return [
            'schema' => 'ee_property_definitions_editor_state',
            'version' => 1,
            'language_code' => trim((string)($draftPayload['language_code'] ?? ENV_DEF_LANG)),
            'source_filename' => trim($sourceFilename),
            'warnings' => array_values(array_filter(array_map(static function ($warning): string {
                return trim((string)$warning);
            }, $warnings), static function (string $warning): bool {
                return $warning !== '';
            })),
            'properties' => $properties,
        ];
    }

    private function buildPropertyDefinitionsDraftWarnings(array $draftPayload): array {
        $warnings = [];

        $typeNameCounts = [];
        foreach (($draftPayload['property_types'] ?? []) as $propertyType) {
            if (!is_array($propertyType)) {
                continue;
            }

            $nameKey = $this->normalizeLookupKey($propertyType['name'] ?? '');
            if ($nameKey === '') {
                continue;
            }

            $typeNameCounts[$nameKey] = ($typeNameCounts[$nameKey] ?? 0) + 1;
        }
        foreach ($typeNameCounts as $count) {
            if ($count > 1) {
                $warnings[] = 'В файле есть повторяющиеся названия типов свойств. Их нужно развести перед импортом.';
                break;
            }
        }

        $propertyNameCounts = [];
        foreach (($draftPayload['properties'] ?? []) as $property) {
            if (!is_array($property)) {
                continue;
            }

            $typeCode = strtolower(trim((string)($property['type_code'] ?? '')));
            $nameKey = $this->normalizeLookupKey($property['name'] ?? '');
            if ($typeCode === '' || $nameKey === '') {
                continue;
            }

            $compoundKey = $typeCode . '|' . $nameKey;
            $propertyNameCounts[$compoundKey] = ($propertyNameCounts[$compoundKey] ?? 0) + 1;
        }
        foreach ($propertyNameCounts as $count) {
            if ($count > 1) {
                $warnings[] = 'В файле есть дубли свойств с одинаковыми названием и типом. Их нужно переименовать в черновом просмотре.';
                break;
            }
        }

        $propertySetNameCounts = [];
        foreach (($draftPayload['property_sets'] ?? []) as $propertySet) {
            if (!is_array($propertySet)) {
                continue;
            }

            $nameKey = $this->normalizeLookupKey($propertySet['name'] ?? '');
            if ($nameKey === '') {
                continue;
            }

            $propertySetNameCounts[$nameKey] = ($propertySetNameCounts[$nameKey] ?? 0) + 1;
        }
        foreach ($propertySetNameCounts as $count) {
            if ($count > 1) {
                $warnings[] = 'В файле есть дубли названий наборов. В черновом просмотре они допустимы, но должны быть исправлены до импорта.';
                break;
            }
        }

        return array_values(array_unique($warnings));
    }

    private function buildPropertyDefinitionsEditorFields(array $defaultValues, string $propertyCode): array {
        $fields = [];
        foreach ($defaultValues as $fieldIndex => $defaultValue) {
            if (!is_array($defaultValue)) {
                continue;
            }

            $fields[] = $this->buildPropertyDefinitionsEditorField($defaultValue, $propertyCode, (int)$fieldIndex);
        }

        return $fields;
    }

    private function buildPropertyDefinitionsEditorField(array $defaultValue, string $propertyCode, int $fieldIndex): array {
        $fieldType = strtolower(trim((string)($defaultValue['type'] ?? 'text')));
        $field = [
            'id' => $propertyCode . '_field_' . $fieldIndex,
            'source_property_code' => $propertyCode,
            'source_field_index' => $fieldIndex,
            'merge_source_code' => '',
            'type' => $fieldType !== '' ? $fieldType : 'text',
            'label' => is_array($defaultValue['label'] ?? null) ? '' : trim((string)($defaultValue['label'] ?? '')),
            'title' => trim((string)($defaultValue['title'] ?? '')),
            'default' => '',
            'required' => $this->isPreviewCheckboxChecked($defaultValue['required'] ?? null) ? 1 : 0,
            'multiple' => $this->isPreviewCheckboxChecked($defaultValue['multiple'] ?? null) ? 1 : 0,
            'options' => [],
        ];

        if ($field['type'] === 'select') {
            $field['options'] = array_values(array_map(static function (array $option): array {
                return [
                    'label' => trim((string)($option['label'] ?? '')),
                    'value' => trim((string)($option['value'] ?? '')),
                    'selected' => !empty($option['selected']) ? 1 : 0,
                ];
            }, $this->extractPreviewPropertyDefinitionsOptions($defaultValue)));
            return $field;
        }

        if (in_array($field['type'], ['checkbox', 'radio'], true)) {
            $field['options'] = array_values(array_map(static function (array $option): array {
                return [
                    'label' => trim((string)($option['label'] ?? '')),
                    'checked' => !empty($option['selected']) ? 1 : 0,
                ];
            }, $this->extractPreviewPropertyDefinitionsOptions($defaultValue)));
            $field['multiple'] = 0;
            return $field;
        }

        $default = $defaultValue['default'] ?? ($field['multiple'] ? [] : '');
        if ($field['type'] === 'image' || $field['type'] === 'file') {
            $default = '';
        }

        if ($field['multiple']) {
            if ($default === null || $default === '') {
                $default = [];
            } elseif (!is_array($default)) {
                $default = [trim((string)$default)];
            } else {
                $default = array_values(array_map(static function ($value): string {
                    return trim((string)$value);
                }, $default));
            }
        } else {
            $default = is_scalar($default) ? trim((string)$default) : '';
        }

        $field['default'] = $default;
        return $field;
    }

    private function buildPropertyDefinitionsEditorSets(array $setRows, string $propertyCode): array {
        $editorSets = [];
        foreach ($setRows as $index => $setRow) {
            if (!is_array($setRow)) {
                continue;
            }

            $setName = trim((string)($setRow['name'] ?? ''));
            if ($setName === '') {
                continue;
            }

            $nameKey = $this->normalizeLookupKey($setName);
            if (!isset($editorSets[$nameKey])) {
                $editorSets[$nameKey] = [
                    'id' => $propertyCode . '_set_' . $index,
                    'code' => strtolower(trim((string)($setRow['code'] ?? ''))),
                    'name' => $setName,
                    'description' => trim((string)($setRow['description'] ?? '')),
                    'source_property_codes' => [$propertyCode],
                ];
                continue;
            }

            if ($editorSets[$nameKey]['description'] === '') {
                $editorSets[$nameKey]['description'] = trim((string)($setRow['description'] ?? ''));
            }
            if (!in_array($propertyCode, $editorSets[$nameKey]['source_property_codes'], true)) {
                $editorSets[$nameKey]['source_property_codes'][] = $propertyCode;
            }
        }

        return array_values($editorSets);
    }

    private function canMergePropertyDefinitionInPreview(array $property, array $fields): array {
        if ($this->isPreviewCheckboxChecked($property['is_multiple'] ?? null)) {
            return ['allowed' => false, 'reason' => 'Множественные свойства нельзя объединять'];
        }

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $fieldType = strtolower(trim((string)($field['type'] ?? '')));
            if (in_array($fieldType, ['image', 'file'], true)) {
                return ['allowed' => false, 'reason' => 'Поля файлов и изображений нельзя объединять'];
            }
        }

        return ['allowed' => true, 'reason' => ''];
    }

    private function buildPropertyDefinitionsPayloadFromEditorState(array $state): array {
        $languageCode = trim((string)($state['language_code'] ?? ENV_DEF_LANG));
        if ($languageCode === '') {
            $languageCode = ENV_DEF_LANG;
        }

        $propertiesInput = is_array($state['properties'] ?? null) ? $state['properties'] : [];
        if ($propertiesInput === []) {
            throw new \RuntimeException('Состояние формы не содержит свойств для импорта.');
        }

        $propertyTypesByCode = [];
        $usedTypeCodes = [];
        $reservedOriginalTypeNameKeys = [];
        $properties = [];
        $propertySetsByName = [];
        $usedSetCodes = [];
        $generatedSetIndex = 1;
        foreach ($propertiesInput as $property) {
            if (!is_array($property)) {
                continue;
            }

            $enabled = $this->isPreviewCheckboxChecked($property['enabled'] ?? null);
            $mergedInto = strtolower(trim((string)($property['merged_into'] ?? '')));
            if (!$enabled || $mergedInto !== '') {
                continue;
            }

            $originalTypeName = $this->normalizePreviewEditString($property['original_type_name'] ?? '');
            if ($originalTypeName === '') {
                continue;
            }

            $reservedOriginalTypeNameKeys[$this->normalizeLookupKey($originalTypeName)] = true;
        }
        $usedSyntheticTypeNameKeys = [];

        foreach ($propertiesInput as $propertyIndex => $property) {
            if (!is_array($property)) {
                continue;
            }

            $propertyCode = strtolower(trim((string)($property['code'] ?? '')));
            if ($propertyCode === '') {
                throw new \RuntimeException('Свойство #' . ($propertyIndex + 1) . ' в состоянии формы не содержит кода.');
            }

            $enabled = $this->isPreviewCheckboxChecked($property['enabled'] ?? null);
            $mergedInto = strtolower(trim((string)($property['merged_into'] ?? '')));
            if (!$enabled || $mergedInto !== '') {
                continue;
            }

            $propertyName = $this->normalizePreviewEditString($property['name'] ?? '');
            if ($propertyName === '') {
                throw new \RuntimeException('У свойства `' . $propertyCode . '` не заполнено название.');
            }

            $typeName = $this->normalizePreviewEditString($property['type_name'] ?? '');
            if ($typeName === '') {
                $typeName = $propertyName . ' тип';
            }

            $fields = is_array($property['fields'] ?? null) ? array_values(array_filter(
                $property['fields'],
                static fn($field): bool => is_array($field)
            )) : [];
            if ($fields === []) {
                throw new \RuntimeException('У свойства `' . $propertyName . '` нет ни одного поля.');
            }

            $fieldTypes = [];
            foreach ($fields as $fieldIndex => $field) {
                $fieldType = strtolower($this->normalizePreviewEditString($field['type'] ?? 'text'));
                if ($fieldType === '') {
                    $fieldType = 'text';
                }
                if (!isset(Constants::ALL_TYPE_PROPERTY_TYPES_FIELDS[$fieldType])) {
                    throw new \RuntimeException(
                        'Поле #' . ($fieldIndex + 1) . ' у свойства `' . $propertyName . '` имеет неподдерживаемый type `' . $fieldType . '`.'
                    );
                }
                $fieldTypes[] = $fieldType;
            }

            $mergeSources = is_array($property['merge_sources'] ?? null) ? array_values(array_filter(array_map(
                static fn($value): string => strtolower(trim((string)$value)),
                $property['merge_sources']
            ), static fn(string $value): bool => $value !== '')) : [];
            $originalTypeCode = strtolower(trim((string)($property['original_type_code'] ?? '')));
            $originalTypeName = $this->normalizePreviewEditString($property['original_type_name'] ?? '');
            $originalFieldTypes = is_array($property['original_field_types'] ?? null)
                ? array_values(array_map(static fn($value): string => strtolower(trim((string)$value)), $property['original_field_types']))
                : [];

            $reuseOriginalType = $mergeSources === []
                && $originalTypeCode !== ''
                && $fieldTypes === $originalFieldTypes
                && $typeName === ($originalTypeName !== '' ? $originalTypeName : $typeName);

            if ($reuseOriginalType) {
                $typeCode = $originalTypeCode;
            } else {
                $typeCode = 'preview_type_' . substr(md5($propertyCode . '|' . implode('|', $fieldTypes) . '|' . $typeName), 0, 12);
                $suffix = 1;
                while (isset($usedTypeCodes[$typeCode])) {
                    $typeCode = 'preview_type_' . substr(md5($propertyCode . '|' . $suffix . '|' . implode('|', $fieldTypes) . '|' . $typeName), 0, 12);
                    $suffix++;
                }
            }
            $usedTypeCodes[$typeCode] = true;

            if (!isset($propertyTypesByCode[$typeCode])) {
                $resolvedTypeName = $reuseOriginalType
                    ? ($originalTypeName !== '' ? $originalTypeName : $typeName)
                    : $this->resolvePreviewSyntheticPropertyTypeName(
                        $typeName,
                        $propertyName,
                        $reservedOriginalTypeNameKeys,
                        $usedSyntheticTypeNameKeys
                    );

                $propertyTypesByCode[$typeCode] = [
                    'code' => $typeCode,
                    'name' => $resolvedTypeName,
                    'status' => trim((string)($property['status'] ?? 'active')) ?: 'active',
                    'description' => '',
                    'fields' => $fieldTypes,
                ];
            }

            $defaultValues = [];
            foreach ($fields as $fieldIndex => $field) {
                $defaultValues[] = $this->buildPropertyDefinitionsDefaultValueFromEditorField(
                    $field,
                    $propertyName,
                    (int)$fieldIndex
                );
            }

            $properties[] = [
                'code' => $propertyCode,
                'type_code' => $typeCode,
                'name' => $propertyName,
                'status' => trim((string)($property['status'] ?? 'active')) ?: 'active',
                'sort' => (int)($property['sort'] ?? 100),
                'entity_type' => trim((string)($property['entity_type'] ?? 'all')) ?: 'all',
                'is_multiple' => $this->isPreviewCheckboxChecked($property['is_multiple'] ?? null) ? 1 : 0,
                'is_required' => $this->isPreviewCheckboxChecked($property['is_required'] ?? null) ? 1 : 0,
                'description' => trim((string)($property['description'] ?? '')),
                'default_values' => $defaultValues,
            ];

            $setRows = is_array($property['sets'] ?? null) ? $property['sets'] : [];
            foreach ($setRows as $setRow) {
                if (!is_array($setRow)) {
                    continue;
                }

                $setName = $this->normalizePreviewEditString($setRow['name'] ?? '');
                if ($setName === '') {
                    continue;
                }

                $nameKey = $this->normalizeLookupKey($setName);
                if (!isset($propertySetsByName[$nameKey])) {
                    $sourceCode = strtolower(trim((string)($setRow['code'] ?? '')));
                    $resolvedCode = $this->resolvePreviewPropertySetCode(
                        $sourceCode,
                        $setName,
                        $usedSetCodes,
                        $generatedSetIndex
                    );
                    $propertySetsByName[$nameKey] = [
                        'code' => $resolvedCode,
                        'name' => $setName,
                        'description' => trim((string)($setRow['description'] ?? '')),
                        'properties' => [],
                    ];
                }

                if (!in_array($propertyCode, $propertySetsByName[$nameKey]['properties'], true)) {
                    $propertySetsByName[$nameKey]['properties'][] = $propertyCode;
                }
            }
        }

        if ($properties === []) {
            throw new \RuntimeException('После фильтрации и объединения в состоянии формы не осталось ни одного свойства для импорта.');
        }

        return [
            'schema' => 'ee_property_definitions_import',
            'version' => 1,
            'language_code' => $languageCode,
            'property_types' => array_values($propertyTypesByCode),
            'properties' => $properties,
            'property_sets' => array_values(array_filter(
                $propertySetsByName,
                static fn(array $propertySet): bool => !empty($propertySet['properties'])
            )),
        ];
    }

    private function resolvePreviewSyntheticPropertyTypeName(
        string $typeName,
        string $propertyName,
        array $reservedOriginalTypeNameKeys,
        array &$usedSyntheticTypeNameKeys
    ): string {
        $baseName = trim($typeName);
        if ($baseName === '') {
        $baseName = trim($propertyName) !== '' ? trim($propertyName) . ' тип' : 'Новый тип свойства';
        }

        $candidate = $baseName;
        $candidateKey = $this->normalizeLookupKey($candidate);
        if ($candidateKey !== ''
            && !isset($reservedOriginalTypeNameKeys[$candidateKey])
            && !isset($usedSyntheticTypeNameKeys[$candidateKey])) {
            $usedSyntheticTypeNameKeys[$candidateKey] = true;
            return $candidate;
        }

        $suffixName = trim($propertyName);
        if ($suffixName === '') {
            $suffixName = 'custom';
        }

        $candidate = $baseName . ' (' . $suffixName . ')';
        $candidateKey = $this->normalizeLookupKey($candidate);
        $suffix = 2;
        while ($candidateKey === ''
            || isset($reservedOriginalTypeNameKeys[$candidateKey])
            || isset($usedSyntheticTypeNameKeys[$candidateKey])) {
            $candidate = $baseName . ' (' . $suffixName . ' ' . $suffix . ')';
            $candidateKey = $this->normalizeLookupKey($candidate);
            $suffix++;
        }

        $usedSyntheticTypeNameKeys[$candidateKey] = true;
        return $candidate;
    }

    private function buildPropertyDefinitionsDefaultValueFromEditorField(
        array $field,
        string $propertyName,
        int $fieldIndex
    ): array {
        $fieldType = strtolower($this->normalizePreviewEditString($field['type'] ?? 'text'));
        if ($fieldType === '') {
            $fieldType = 'text';
        }

        $label = $this->normalizePreviewEditString($field['label'] ?? '');
        $title = $this->normalizePreviewEditString($field['title'] ?? '');
        $required = $this->isPreviewCheckboxChecked($field['required'] ?? null) ? 1 : 0;
        $multiple = $this->isPreviewCheckboxChecked($field['multiple'] ?? null) ? 1 : 0;
        $fallbackLabel = $label !== '' ? $label : ($title !== '' ? $title : $propertyName . ' #' . ($fieldIndex + 1));

        if ($fieldType === 'select') {
            $options = [];
            foreach ((array)($field['options'] ?? []) as $optionIndex => $option) {
                if (!is_array($option)) {
                    continue;
                }

                $optionLabel = $this->normalizePreviewEditString($option['label'] ?? '');
                if ($optionLabel === '') {
                    $optionLabel = 'Вариант ' . ($optionIndex + 1);
                }

                $optionValue = $this->normalizePreviewEditString($option['value'] ?? '');
                if ($optionValue === '') {
                    $optionValue = 'option_' . ($optionIndex + 1);
                }

                $options[] = [
                    'label' => $optionLabel,
                    'value' => $optionValue,
                    'selected' => $this->isPreviewCheckboxChecked($option['selected'] ?? null),
                ];
            }

            if ($options === []) {
                $options[] = [
                    'label' => 'Вариант 1',
                    'value' => 'option_1',
                    'selected' => false,
                ];
            }

            return [
                'type' => 'select',
                'label' => $fallbackLabel,
                'title' => $title,
                'required' => $required,
                'multiple' => $multiple,
                'options' => $options,
            ];
        }

        if (in_array($fieldType, ['checkbox', 'radio'], true)) {
            $options = [];
            foreach ((array)($field['options'] ?? []) as $optionIndex => $option) {
                if (!is_array($option)) {
                    continue;
                }

                $optionLabel = $this->normalizePreviewEditString($option['label'] ?? '');
                if ($optionLabel === '') {
                    $optionLabel = 'Вариант ' . ($optionIndex + 1);
                }

                $options[] = [
                    'label' => $optionLabel,
                    'checked' => $this->isPreviewCheckboxChecked($option['checked'] ?? null),
                ];
            }

            if ($options === []) {
                $options[] = [
                    'label' => 'Вариант 1',
                    'checked' => false,
                ];
            }

            return [
                'type' => $fieldType,
                'title' => $title !== '' ? $title : $fallbackLabel,
                'required' => $required,
                'options' => $options,
            ];
        }

        $default = $field['default'] ?? ($multiple ? [] : '');
        if (in_array($fieldType, ['image', 'file'], true)) {
            $default = '';
        } elseif ($multiple) {
            if ($default === null || $default === '') {
                $default = [];
            } elseif (!is_array($default)) {
                $default = [trim((string)$default)];
            } else {
                $default = array_values(array_map(static fn($value): string => trim((string)$value), $default));
            }
        } else {
            if (is_array($default)) {
                $default = count($default) > 0 ? trim((string)reset($default)) : '';
            } else {
                $default = is_scalar($default) ? trim((string)$default) : '';
            }
        }

        return [
            'type' => $fieldType,
            'label' => $fallbackLabel,
            'title' => $title,
            'default' => $default,
            'required' => $required,
            'multiple' => $multiple,
        ];
    }

    private function applyPropertyDefinitionsPreviewEdits(array $payload, array $edits): array {
        $typeEdits = is_array($edits['property_types'] ?? null) ? $edits['property_types'] : [];
        $propertyEdits = is_array($edits['properties'] ?? null) ? $edits['properties'] : [];

        if (is_array($payload['property_types'] ?? null)) {
            foreach ($payload['property_types'] as $typeIndex => $propertyType) {
                if (!is_array($propertyType)) {
                    continue;
                }

                $typeCode = strtolower(trim((string)($propertyType['code'] ?? '')));
                $typeEdit = $typeCode !== '' && is_array($typeEdits[$typeCode] ?? null) ? $typeEdits[$typeCode] : [];
                if ($typeEdit === []) {
                    continue;
                }

                if (array_key_exists('name', $typeEdit)) {
                    $payload['property_types'][$typeIndex]['name'] = $this->normalizePreviewEditString($typeEdit['name']);
                }

                $fieldEdits = is_array($typeEdit['fields'] ?? null) ? $typeEdit['fields'] : [];
                if (!is_array($payload['property_types'][$typeIndex]['fields'] ?? null)) {
                    continue;
                }

                foreach ($payload['property_types'][$typeIndex]['fields'] as $fieldIndex => $fieldType) {
                    $fieldEdit = is_array($fieldEdits[$fieldIndex] ?? null) ? $fieldEdits[$fieldIndex] : [];
                    if (!array_key_exists('type', $fieldEdit)) {
                        continue;
                    }

                    $payload['property_types'][$typeIndex]['fields'][$fieldIndex] = strtolower(
                        $this->normalizePreviewEditString($fieldEdit['type'])
                    );
                }
            }
        }

        $typeFieldsByCode = [];
        foreach (($payload['property_types'] ?? []) as $propertyType) {
            if (!is_array($propertyType)) {
                continue;
            }

            $typeCode = strtolower(trim((string)($propertyType['code'] ?? '')));
            if ($typeCode === '') {
                continue;
            }

            $typeFieldsByCode[$typeCode] = is_array($propertyType['fields'] ?? null) ? $propertyType['fields'] : [];
        }

        if (is_array($payload['properties'] ?? null)) {
            foreach ($payload['properties'] as $propertyIndex => $property) {
                if (!is_array($property)) {
                    continue;
                }

                $propertyCode = strtolower(trim((string)($property['code'] ?? '')));
                $propertyEdit = $propertyCode !== '' && is_array($propertyEdits[$propertyCode] ?? null)
                    ? $propertyEdits[$propertyCode]
                    : [];

                if (array_key_exists('name', $propertyEdit)) {
                    $payload['properties'][$propertyIndex]['name'] = $this->normalizePreviewEditString($propertyEdit['name']);
                }

                $fieldNameEdits = is_array($propertyEdit['field_names'] ?? null) ? $propertyEdit['field_names'] : [];
                $typeCode = strtolower(trim((string)($payload['properties'][$propertyIndex]['type_code'] ?? '')));
                $typeFields = $typeFieldsByCode[$typeCode] ?? [];

                if (!is_array($payload['properties'][$propertyIndex]['default_values'] ?? null)) {
                    continue;
                }

                foreach ($payload['properties'][$propertyIndex]['default_values'] as $fieldIndex => $defaultValue) {
                    if (!is_array($defaultValue)) {
                        continue;
                    }

                    $currentFieldType = strtolower(trim((string)($defaultValue['type'] ?? 'text')));
                    $newFieldType = isset($typeFields[$fieldIndex])
                        ? strtolower(trim((string)$typeFields[$fieldIndex]))
                        : $currentFieldType;
                    $fieldName = $this->extractPreviewPropertyDefinitionsFieldName(
                        $defaultValue,
                        (string)($payload['properties'][$propertyIndex]['name'] ?? 'Поле'),
                        (int)$fieldIndex
                    );

                    if ($newFieldType !== '' && $newFieldType !== $currentFieldType) {
                        $payload['properties'][$propertyIndex]['default_values'][$fieldIndex]
                            = $this->transformPreviewPropertyDefinitionsDefaultValue(
                                $payload['properties'][$propertyIndex]['default_values'][$fieldIndex],
                                $newFieldType,
                                $fieldName
                            );
                    }

                    if (isset($typeFields[$fieldIndex])) {
                        $payload['properties'][$propertyIndex]['default_values'][$fieldIndex]['type'] = (string)$typeFields[$fieldIndex];
                    }

                    if (array_key_exists($fieldIndex, $fieldNameEdits)) {
                        $this->applyPropertyDefinitionsFieldNameEdit(
                            $payload['properties'][$propertyIndex]['default_values'][$fieldIndex],
                            $this->normalizePreviewEditString($fieldNameEdits[$fieldIndex])
                        );
                    }
                }
            }
        }

        $payload['property_sets'] = $this->rebuildPropertyDefinitionsPreviewSets($payload, $propertyEdits);
        return $payload;
    }

    private function normalizePropertyDefinitionsPreviewEdits(array $edits): array {
        $normalizedEdits = [
            'property_types' => [],
            'properties' => [],
        ];

        foreach (['property_types', 'properties'] as $section) {
            $items = is_array($edits[$section] ?? null) ? $edits[$section] : [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $code = strtolower(trim((string)($item['code'] ?? '')));
                if ($code === '') {
                    continue;
                }

                unset($item['code']);
                $normalizedEdits[$section][$code] = $item;
            }
        }

        return $normalizedEdits;
    }

    private function rebuildPropertyDefinitionsPreviewSets(array $payload, array $propertyEdits): array {
        $originalSetsByCode = [];
        $originalPropertySetRows = [];

        foreach (($payload['property_sets'] ?? []) as $propertySet) {
            if (!is_array($propertySet)) {
                continue;
            }

            $setCode = strtolower(trim((string)($propertySet['code'] ?? '')));
            if ($setCode !== '') {
                $originalSetsByCode[$setCode] = $propertySet;
            }

            foreach ((array)($propertySet['properties'] ?? []) as $propertyCode) {
                $propertyCode = strtolower(trim((string)$propertyCode));
                if ($propertyCode === '') {
                    continue;
                }

                $originalPropertySetRows[$propertyCode][] = [
                    'code' => $setCode,
                    'name' => trim((string)($propertySet['name'] ?? '')),
                ];
            }
        }

        $rebuiltSets = [];
        $usedCodes = [];
        $generatedIndex = 1;

        foreach (($payload['properties'] ?? []) as $property) {
            if (!is_array($property)) {
                continue;
            }

            $propertyCode = strtolower(trim((string)($property['code'] ?? '')));
            if ($propertyCode === '') {
                continue;
            }

            $propertyEdit = is_array($propertyEdits[$propertyCode] ?? null) ? $propertyEdits[$propertyCode] : [];
            $setRows = array_key_exists('set_names_present', $propertyEdit)
                ? $this->extractPreviewPropertySetRows($propertyEdit['set_names'] ?? [])
                : ($originalPropertySetRows[$propertyCode] ?? []);

            foreach ($setRows as $setRow) {
                $setName = $this->normalizePreviewEditString($setRow['name'] ?? '');
                if ($setName === '') {
                    continue;
                }

                $nameKey = $this->normalizeLookupKey($setName);
                if (!isset($rebuiltSets[$nameKey])) {
                    $sourceCode = strtolower(trim((string)($setRow['code'] ?? '')));
                    $sourceSet = $sourceCode !== '' ? ($originalSetsByCode[$sourceCode] ?? null) : null;
                    $resolvedCode = $this->resolvePreviewPropertySetCode(
                        $sourceCode,
                        $setName,
                        $usedCodes,
                        $generatedIndex
                    );

                    $rebuiltSets[$nameKey] = [
                        'code' => $resolvedCode,
                        'name' => $setName,
                        'description' => trim((string)($sourceSet['description'] ?? '')),
                        'properties' => [],
                    ];
                }

                if (!in_array($propertyCode, $rebuiltSets[$nameKey]['properties'], true)) {
                    $rebuiltSets[$nameKey]['properties'][] = $propertyCode;
                }
            }
        }

        return array_values($rebuiltSets);
    }

    private function extractPreviewPropertySetRows(mixed $rows): array {
        if (!is_array($rows)) {
            return [];
        }

        $normalizedRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalizedRows[] = [
                'code' => strtolower(trim((string)($row['code'] ?? ''))),
                'name' => $this->normalizePreviewEditString($row['name'] ?? ''),
            ];
        }

        return $normalizedRows;
    }

    private function resolvePreviewPropertySetCode(
        string $sourceCode,
        string $setName,
        array &$usedCodes,
        int &$generatedIndex
    ): string {
        $sourceCode = strtolower(trim($sourceCode));
        if ($sourceCode !== '' && !isset($usedCodes[$sourceCode])) {
            $usedCodes[$sourceCode] = true;
            return $sourceCode;
        }

        $baseCode = 'preview_set_' . substr(md5($setName), 0, 10);
        $resolvedCode = $baseCode;
        while (isset($usedCodes[$resolvedCode])) {
            $resolvedCode = $baseCode . '_' . $generatedIndex;
            $generatedIndex++;
        }

        $usedCodes[$resolvedCode] = true;
        return $resolvedCode;
    }

    private function extractPreviewPropertyDefinitionsFieldName(
        array $defaultValue,
        string $fallbackName,
        int $fieldIndex
    ): string {
        $title = trim((string)($defaultValue['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $label = $defaultValue['label'] ?? '';
        if (!is_array($label)) {
            $label = trim((string)$label);
            if ($label !== '') {
                return $label;
            }
        }

        $fallbackName = trim($fallbackName);
        if ($fallbackName === '') {
            $fallbackName = 'Поле';
        }

        return $fallbackName . ' #' . ($fieldIndex + 1);
    }

    private function transformPreviewPropertyDefinitionsDefaultValue(
        array $defaultValue,
        string $newFieldType,
        string $fieldName
    ): array {
        $currentFieldType = strtolower(trim((string)($defaultValue['type'] ?? 'text')));
        $newFieldType = strtolower(trim($newFieldType));
        $fieldName = trim($fieldName);
        if ($fieldName === '') {
            $fieldName = 'Поле';
        }

        $required = $this->isPreviewCheckboxChecked($defaultValue['required'] ?? null) ? 1 : 0;
        $multiple = $this->isPreviewCheckboxChecked($defaultValue['multiple'] ?? null) ? 1 : 0;

        if ($newFieldType === 'select') {
            $options = $this->extractPreviewPropertyDefinitionsOptions($defaultValue);
            if ($options === []) {
                $options = [['label' => 'Вариант 1', 'value' => 'option_1', 'selected' => false]];
            }

            $encodedOptions = [];
            $hasSelected = false;
            foreach ($options as $index => $option) {
                $optionLabel = trim((string)($option['label'] ?? ''));
                if ($optionLabel === '') {
                    $optionLabel = 'Вариант ' . ($index + 1);
                }

                $optionValue = trim((string)($option['value'] ?? ''));
                if ($optionValue === '') {
                    $optionValue = 'option_' . ($index + 1);
                }

                $selected = !empty($option['selected']);
                if (!$multiple && $hasSelected) {
                    $selected = false;
                }
                if ($selected) {
                    $hasSelected = true;
                }

                $encodedOptions[] = $optionLabel . '=' . $optionValue . ($selected ? '{*}' : '');
            }

            return [
                'type' => 'select',
                'label' => $fieldName,
                'title' => $fieldName,
                'default' => implode('{|}', $encodedOptions),
                'required' => $required,
                'multiple' => $multiple,
            ];
        }

        if (in_array($newFieldType, ['checkbox', 'radio'], true)) {
            $options = $this->extractPreviewPropertyDefinitionsOptions($defaultValue);
            if ($options === []) {
                $options = [['label' => 'Вариант 1', 'value' => 'option_1', 'selected' => false]];
            }

            $normalizedOptions = [];
            $selectedKeys = [];
            foreach ($options as $index => $option) {
                $optionLabel = trim((string)($option['label'] ?? ''));
                if ($optionLabel === '') {
                    $optionLabel = 'Вариант ' . ($index + 1);
                }
                $optionValue = trim((string)($option['value'] ?? ''));
                if ($optionValue === '') {
                    $optionValue = 'option_' . ($index + 1);
                }
                $normalizedOptions[] = [
                    'label' => $optionLabel,
                    'value' => $optionValue,
                    'selected' => !empty($option['selected']),
                ];
                if (!empty($option['selected'])) {
                    if ($newFieldType === 'radio' && $selectedKeys !== []) {
                        continue;
                    }
                    $selectedKeys[] = $optionValue;
                }
            }

            return [
                'type' => $newFieldType,
                'label' => $fieldName,
                'title' => $fieldName,
                'options' => $normalizedOptions,
                'default' => $selectedKeys,
                'required' => $required,
                'multiple' => $newFieldType === 'checkbox' ? 1 : 0,
            ];
        }

        $default = $defaultValue['default'] ?? ($multiple ? [] : '');
        if (in_array($currentFieldType, ['select', 'checkbox', 'radio'], true)) {
            $default = $multiple ? [] : '';
        } elseif ($multiple) {
            if ($default === null || $default === '') {
                $default = [];
            } elseif (!is_array($default)) {
                $default = [trim((string)$default)];
            } else {
                $default = array_values(array_map(static function ($item): string {
                    return trim((string)$item);
                }, $default));
            }
        } else {
            $default = is_scalar($default) ? trim((string)$default) : '';
        }

        if (in_array($newFieldType, ['file', 'image'], true)) {
            $default = '';
        }

        return [
            'type' => $newFieldType,
            'label' => $fieldName,
            'title' => $fieldName,
            'default' => $default,
            'required' => $required,
            'multiple' => $multiple,
        ];
    }

    private function extractPreviewPropertyDefinitionsOptions(array $defaultValue): array {
        $fieldType = strtolower(trim((string)($defaultValue['type'] ?? '')));
        if ($fieldType === 'select') {
            return $this->decodePreviewPropertyDefinitionsSelectOptions((string)($defaultValue['default'] ?? ''));
        }

        if (!in_array($fieldType, ['checkbox', 'radio'], true)) {
            return [];
        }

        if (is_array($defaultValue['options'] ?? null)) {
            $selectedLookup = [];
            foreach ($this->normalizeChoiceSelectionInput($defaultValue['default'] ?? [], 'preview.default') as $selectedKey) {
                $selectedLookup[(string)$selectedKey] = true;
            }

            $options = [];
            foreach (array_values($defaultValue['options']) as $index => $option) {
                if (!is_array($option)) {
                    continue;
                }
                $label = trim((string)($option['label'] ?? ''));
                if ($label === '') {
                    $label = 'Вариант ' . ($index + 1);
                }
                $value = trim((string)($option['value'] ?? ($option['key'] ?? '')));
                if ($value === '') {
                    $value = 'option_' . ($index + 1);
                }
                $selected = $selectedLookup !== []
                    ? isset($selectedLookup[$value])
                    : !empty($option['selected']) || !empty($option['checked']);
                $options[] = [
                    'label' => $label,
                    'value' => $value,
                    'selected' => $selected,
                ];
            }

            return $options;
        }

        $labels = is_array($defaultValue['label'] ?? null) ? array_values($defaultValue['label']) : [];
        $selectedLookup = [];
        foreach ((array)($defaultValue['default'] ?? []) as $selectedIndex) {
            $selectedLookup[(string)$selectedIndex] = true;
        }

        $options = [];
        foreach ($labels as $index => $label) {
            $options[] = [
                'label' => trim((string)$label),
                'value' => 'option_' . ($index + 1),
                'selected' => isset($selectedLookup[(string)$index]),
            ];
        }

        return $options;
    }

    private function decodePreviewPropertyDefinitionsSelectOptions(string $encodedDefault): array {
        $encodedDefault = html_entity_decode($encodedDefault, ENT_QUOTES);
        if ($encodedDefault === '') {
            return [];
        }

        $options = [];
        foreach (explode('{|}', $encodedDefault) as $optionChunk) {
            $optionChunk = trim($optionChunk);
            if ($optionChunk === '') {
                continue;
            }

            $parts = explode('=', $optionChunk, 2);
            $label = trim((string)($parts[0] ?? ''));
            $value = trim((string)($parts[1] ?? ''));
            $selected = false;

            if ($value !== '' && str_contains($value, '{*}')) {
                $selected = true;
                $value = str_replace('{*}', '', $value);
            }

            $options[] = [
                'label' => $label,
                'value' => $value,
                'selected' => $selected,
            ];
        }

        return $options;
    }

    private function applyPropertyDefinitionsFieldNameEdit(array &$defaultValue, string $fieldName): void {
        if (is_array($defaultValue['options'] ?? null)) {
            $defaultValue['label'] = $fieldName;
            $defaultValue['title'] = $fieldName;
            return;
        }

        if (is_array($defaultValue['label'] ?? null)) {
            $defaultValue['title'] = $fieldName;
            return;
        }

        $defaultValue['label'] = $fieldName;
        $defaultValue['title'] = $fieldName;
    }

    private function normalizePreviewEditString(mixed $value): string {
        if (is_array($value) || is_object($value)) {
            return '';
        }
        return trim((string)$value);
    }

    private function extractDisabledPropertyDefinitionCodes(array $edits, array $payload): array {
        $propertyEdits = is_array($edits['properties'] ?? null) ? $edits['properties'] : [];
        $disabledCodes = [];

        foreach (($payload['properties'] ?? []) as $property) {
            if (!is_array($property)) {
                continue;
            }

            $propertyCode = strtolower(trim((string)($property['code'] ?? '')));
            if ($propertyCode === '') {
                continue;
            }

            $propertyEdit = is_array($propertyEdits[$propertyCode] ?? null) ? $propertyEdits[$propertyCode] : [];
            if (!array_key_exists('__present', $propertyEdit)) {
                continue;
            }

            if (!$this->isPreviewCheckboxChecked($propertyEdit['enabled'] ?? null)) {
                $disabledCodes[] = $propertyCode;
            }
        }

        return array_values(array_unique($disabledCodes));
    }

    private function isPreviewCheckboxChecked(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }
        return false;
    }

    private function filterPropertyDefinitionsPayloadByEnabledProperties(array $payload, array $disabledCodes): array {
        if ($disabledCodes === []) {
            return $payload;
        }

        $disabledMap = array_fill_keys(array_map('strtolower', $disabledCodes), true);
        $enabledPropertyCodes = [];
        $enabledTypeCodes = [];
        $filteredProperties = [];

        foreach (($payload['properties'] ?? []) as $property) {
            if (!is_array($property)) {
                continue;
            }

            $propertyCode = strtolower(trim((string)($property['code'] ?? '')));
            if ($propertyCode !== '' && isset($disabledMap[$propertyCode])) {
                continue;
            }

            $filteredProperties[] = $property;
            if ($propertyCode !== '') {
                $enabledPropertyCodes[$propertyCode] = true;
            }

            $typeCode = strtolower(trim((string)($property['type_code'] ?? '')));
            if ($typeCode !== '') {
                $enabledTypeCodes[$typeCode] = true;
            }
        }

        $payload['properties'] = $filteredProperties;

        if (is_array($payload['property_sets'] ?? null)) {
            foreach ($payload['property_sets'] as $setIndex => $propertySet) {
                if (!is_array($propertySet)) {
                    continue;
                }

                $propertyCodes = is_array($propertySet['properties'] ?? null) ? $propertySet['properties'] : [];
                $payload['property_sets'][$setIndex]['properties'] = array_values(array_filter(
                    $propertyCodes,
                    static function ($propertyCode) use ($enabledPropertyCodes): bool {
                        return isset($enabledPropertyCodes[strtolower(trim((string)$propertyCode))]);
                    }
                ));
            }

            $payload['property_sets'] = array_values(array_filter(
                $payload['property_sets'],
                static function ($propertySet): bool {
                    if (!is_array($propertySet)) {
                        return false;
                    }

                    return !empty($propertySet['properties']) && is_array($propertySet['properties']);
                }
            ));
        }

        if (is_array($payload['property_types'] ?? null)) {
            $payload['property_types'] = array_values(array_filter(
                $payload['property_types'],
                static function ($propertyType) use ($enabledTypeCodes): bool {
                    if (!is_array($propertyType)) {
                        return false;
                    }

                    $typeCode = strtolower(trim((string)($propertyType['code'] ?? '')));
                    return $typeCode !== '' && isset($enabledTypeCodes[$typeCode]);
                }
            ));
        }

        return $payload;
    }

    private function formatPropertyDefinitionsImportReport(array $report): string {
        return 'Импорт завершён.<br>'
            . 'Типы свойств: создано ' . (int)($report['property_types_created'] ?? 0)
            . ', обновлено ' . (int)($report['property_types_updated'] ?? 0) . '.<br>'
            . 'Свойства: создано ' . (int)($report['properties_created'] ?? 0)
            . ', обновлено ' . (int)($report['properties_updated'] ?? 0) . '.<br>'
            . 'Наборы свойств: создано ' . (int)($report['property_sets_created'] ?? 0)
            . ', обновлено ' . (int)($report['property_sets_updated'] ?? 0) . '.<br>'
            . 'Связи свойств с наборами: добавлено ' . (int)($report['set_links_added'] ?? 0)
            . ', удалено ' . (int)($report['set_links_removed'] ?? 0) . '.';
    }

    private function decodePropertyDefinitionsPayload(string $rawPayload): array {
        $rawPayload = (string)preg_replace('/^\xEF\xBB\xBF/', '', $rawPayload);
        $decoded = json_decode($rawPayload, true);
        if (!is_array($decoded)) {
            $message = json_last_error() === JSON_ERROR_NONE
                ? 'Корневой элемент JSON должен быть объектом.'
                : 'Некорректный JSON: ' . json_last_error_msg();
            throw new \RuntimeException($message);
        }
        return $decoded;
    }

    private function importPropertyDefinitionsPayload(array $payload): array {
        $normalizedPayload = $this->normalizePropertyDefinitionsPayload($payload);
        $languageCode = $normalizedPayload['language_code'];

        $this->loadModel('m_properties');
        SafeMySQL::gi()->query('START TRANSACTION');

        try {
            $report = [
                'property_types_created' => 0,
                'property_types_updated' => 0,
                'properties_created' => 0,
                'properties_updated' => 0,
                'property_sets_created' => 0,
                'property_sets_updated' => 0,
                'set_links_added' => 0,
                'set_links_removed' => 0,
            ];

            $typeIdsByCode = $this->importPropertyTypeDefinitions(
                $normalizedPayload['property_types'],
                $languageCode,
                $report
            );
            $propertyIdsByCode = $this->importPropertyDefinitions(
                $normalizedPayload['properties'],
                $typeIdsByCode,
                $languageCode,
                $report
            );
            $this->importPropertySetDefinitions(
                $normalizedPayload['property_sets'],
                $propertyIdsByCode,
                $languageCode,
                $report
            );

            SafeMySQL::gi()->query('COMMIT');
            return $report;
        } catch (\Throwable $exception) {
            SafeMySQL::gi()->query('ROLLBACK');
            throw $exception;
        }
    }

    private function importPropertyTypeDefinitions(array $propertyTypes, string $languageCode, array &$report): array {
        $existingTypes = $this->buildExistingPropertyTypeIndex($languageCode);
        $typeIdsByCode = [];

        foreach ($propertyTypes as $propertyType) {
            $lookupKey = $this->normalizeLookupKey($propertyType['name']);
            $existingType = $existingTypes[$lookupKey] ?? null;
            $typeId = isset($existingType['type_id']) ? (int)$existingType['type_id'] : 0;
            $fieldsJson = json_encode($propertyType['fields'], JSON_UNESCAPED_UNICODE);
            if ($fieldsJson === false) {
                throw new \RuntimeException('Не удалось подготовить fields для типа `' . $propertyType['code'] . '`.');
            }

            if ($typeId > 0 && $this->models['m_properties']->isExistPropertiesWithType($typeId)) {
                $existingFields = $this->normalizeTypeFieldsValue($existingType['fields'] ?? null);
                if ($existingFields !== $propertyType['fields']) {
                    throw new \RuntimeException(
                        'Нельзя изменить fields у типа `' . $propertyType['name']
                        . '`, потому что этот тип уже используется свойствами.'
                    );
                }
                $fieldsJson = '';
            }

            $typePayload = [
                'name' => $propertyType['name'],
                'status' => $propertyType['status'],
                'description' => $propertyType['description'],
            ];
            if ($fieldsJson !== '') {
                $typePayload['fields'] = $fieldsJson;
            }
            if ($typeId > 0) {
                $typePayload['type_id'] = $typeId;
            }

            $savedTypeResult = $this->normalizeOperationResult(
                $this->models['m_properties']->updatePropertyTypeData($typePayload, $languageCode),
                [
                    'default_error_message' => 'Не удалось сохранить тип свойства `' . $propertyType['name'] . '`.',
                    'failure_code' => 'import_property_type_save_failed',
                ]
            );
            if ($savedTypeResult->isFailure()) {
                throw new \RuntimeException($savedTypeResult->getMessage('Не удалось сохранить тип свойства `' . $propertyType['name'] . '`.'));
            }
            $savedTypeId = $savedTypeResult->getId(['type_id', 'id']);

            $typeIdsByCode[$propertyType['code']] = (int)$savedTypeId;
            if ($typeId > 0) {
                $report['property_types_updated']++;
            } else {
                $report['property_types_created']++;
            }

            $existingTypes[$lookupKey] = [
                'type_id' => (int)$savedTypeId,
                'name' => $propertyType['name'],
                'fields' => json_encode($propertyType['fields'], JSON_UNESCAPED_UNICODE),
            ];
        }

        return $typeIdsByCode;
    }

    private function importPropertyDefinitions(
        array $properties,
        array $typeIdsByCode,
        string $languageCode,
        array &$report
    ): array {
        $existingProperties = $this->buildExistingPropertyIndex($languageCode);
        $propertyIdsByCode = [];

        foreach ($properties as $property) {
            $typeId = (int)($typeIdsByCode[$property['type_code']] ?? 0);
            if ($typeId <= 0) {
                throw new \RuntimeException(
                    'Для свойства `' . $property['code'] . '` не найден type_code `' . $property['type_code'] . '`.'
                );
            }

            $lookupKey = $typeId . '|' . $this->normalizeLookupKey($property['name']);
            $existingProperty = $existingProperties[$lookupKey] ?? null;
            $propertyId = isset($existingProperty['property_id']) ? (int)$existingProperty['property_id'] : 0;

            $propertyPayload = [
                'type_id' => $typeId,
                'name' => $property['name'],
                'status' => $property['status'],
                'sort' => (string)$property['sort'],
                'default_values' => $property['default_values'],
                'is_multiple' => $property['is_multiple'],
                'is_required' => $property['is_required'],
                'description' => $property['description'],
                'entity_type' => $property['entity_type'],
            ];
            if ($propertyId > 0) {
                $propertyPayload['property_id'] = $propertyId;
            }

            $savedPropertyResult = $this->normalizeOperationResult(
                $this->models['m_properties']->updatePropertyData($propertyPayload, $languageCode),
                [
                    'default_error_message' => 'Не удалось сохранить свойство `' . $property['name'] . '`.',
                    'failure_code' => 'import_property_save_failed',
                ]
            );
            if ($savedPropertyResult->isFailure()) {
                throw new \RuntimeException($savedPropertyResult->getMessage('Не удалось сохранить свойство `' . $property['name'] . '`.'));
            }
            $savedPropertyId = $savedPropertyResult->getId(['property_id', 'id']);

            $propertyIdsByCode[$property['code']] = (int)$savedPropertyId;
            if ($propertyId > 0) {
                $report['properties_updated']++;
            } else {
                $report['properties_created']++;
            }

            $existingProperties[$lookupKey] = [
                'property_id' => (int)$savedPropertyId,
                'type_id' => $typeId,
                'name' => $property['name'],
            ];
        }

        return $propertyIdsByCode;
    }

    private function importPropertySetDefinitions(
        array $propertySets,
        array $propertyIdsByCode,
        string $languageCode,
        array &$report
    ): void {
        $existingSets = $this->buildExistingPropertySetIndex($languageCode);

        foreach ($propertySets as $propertySet) {
            $lookupKey = $this->normalizeLookupKey($propertySet['name']);
            $existingSet = $existingSets[$lookupKey] ?? null;
            $setId = isset($existingSet['set_id']) ? (int)$existingSet['set_id'] : 0;

            $setPayload = [
                'name' => $propertySet['name'],
                'description' => $propertySet['description'],
            ];
            if ($setId > 0) {
                $setPayload['set_id'] = $setId;
            }

            $savedSetResult = $this->normalizeOperationResult(
                $this->models['m_properties']->updatePropertySetData($setPayload, $languageCode),
                [
                    'default_error_message' => 'Не удалось сохранить набор свойств `' . $propertySet['name'] . '`.',
                    'failure_code' => 'import_property_set_save_failed',
                ]
            );
            if ($savedSetResult->isFailure()) {
                throw new \RuntimeException($savedSetResult->getMessage('Не удалось сохранить набор свойств `' . $propertySet['name'] . '`.'));
            }

            $savedSetId = $savedSetResult->getId(['set_id', 'id']);
            if ($setId > 0) {
                $report['property_sets_updated']++;
            } else {
                $report['property_sets_created']++;
            }

            $resolvedPropertyIds = [];
            foreach ($propertySet['properties'] as $propertyCode) {
                $resolvedPropertyId = (int)($propertyIdsByCode[$propertyCode] ?? 0);
                if ($resolvedPropertyId <= 0) {
                    throw new \RuntimeException(
                        'Для набора `' . $propertySet['name'] . '` не найдено свойство `' . $propertyCode . '`.'
                    );
                }
                $resolvedPropertyIds[] = $resolvedPropertyId;
            }

            [$linksAdded, $linksRemoved] = $this->syncImportedSetComposition($savedSetId, $resolvedPropertyIds, $languageCode);
            $report['set_links_added'] += $linksAdded;
            $report['set_links_removed'] += $linksRemoved;

            $existingSets[$lookupKey] = [
                'set_id' => $savedSetId,
                'name' => $propertySet['name'],
            ];
        }
    }

    private function syncImportedSetComposition(int $setId, array $newPropertyIds, string $languageCode): array {
        $setData = $this->models['m_properties']->getPropertySetData($setId, $languageCode);
        $oldPropertyIds = [];
        if (!empty($setData['properties']) && is_array($setData['properties'])) {
            $oldPropertyIds = array_map('intval', array_keys($setData['properties']));
        }

        $newPropertyIds = array_values(array_unique(array_map('intval', $newPropertyIds)));
        sort($oldPropertyIds);
        sort($newPropertyIds);

        $propertiesToAdd = array_values(array_diff($newPropertyIds, $oldPropertyIds));
        $propertiesToDelete = array_values(array_diff($oldPropertyIds, $newPropertyIds));

        if (!empty($propertiesToDelete)) {
            $deleteResult = $this->normalizeOperationResult(
                $this->models['m_properties']->deletePropertiesFromSet($setId, $propertiesToDelete),
                [
                    'default_error_message' => 'Не удалось удалить свойства из импортируемого набора',
                    'failure_code' => 'import_property_set_unlink_failed',
                ]
            );
            if ($deleteResult->isFailure()) {
                throw new \RuntimeException($deleteResult->getMessage('Не удалось удалить свойства из импортируемого набора.'));
            }
        }
        if (!empty($propertiesToAdd)) {
            $addResult = $this->normalizeOperationResult(
                $this->models['m_properties']->addPropertiesToSet($setId, $propertiesToAdd),
                [
                    'default_error_message' => 'Не удалось добавить свойства в импортируемый набор',
                    'failure_code' => 'import_property_set_link_failed',
                ]
            );
            if ($addResult->isFailure()) {
                throw new \RuntimeException($addResult->getMessage('Не удалось добавить свойства в импортируемый набор.'));
            }
        }
        if (!empty($propertiesToAdd) || !empty($propertiesToDelete)) {
            Hook::run('afterUpdatePropertySetComposition', $setId, $propertiesToAdd, $propertiesToDelete);
        }

        return [count($propertiesToAdd), count($propertiesToDelete)];
    }

    private function normalizePropertyDefinitionsPayload(array $payload, bool $allowDraftConflicts = false): array {
        $this->ensureAllowedKeys(
            $payload,
            ['schema', 'version', 'language_code', 'property_types', 'properties', 'property_sets'],
            'root'
        );

        $schema = trim((string)($payload['schema'] ?? ''));
        if ($schema !== 'ee_property_definitions_import') {
            throw new \RuntimeException('Поле root.schema должно быть равно `ee_property_definitions_import`.');
        }

        $version = (int)($payload['version'] ?? 0);
        if ($version !== 1) {
            throw new \RuntimeException('Поддерживается только root.version = 1.');
        }

        $languageCode = $this->normalizeRequiredString($payload['language_code'] ?? ENV_DEF_LANG, 'root.language_code');
        $rawPropertyTypes = $payload['property_types'] ?? [];
        $rawProperties = $payload['properties'] ?? [];
        $rawPropertySets = $payload['property_sets'] ?? [];

        if (!is_array($rawPropertyTypes)) {
            throw new \RuntimeException('Поле root.property_types должно быть массивом.');
        }
        if (!is_array($rawProperties)) {
            throw new \RuntimeException('Поле root.properties должно быть массивом.');
        }
        if (!is_array($rawPropertySets)) {
            throw new \RuntimeException('Поле root.property_sets должно быть массивом.');
        }
        if (!$this->isSequentialArray($rawPropertyTypes)) {
            throw new \RuntimeException('Поле root.property_types должно быть списком объектов.');
        }
        if (!$this->isSequentialArray($rawProperties)) {
            throw new \RuntimeException('Поле root.properties должно быть списком объектов.');
        }
        if (!$this->isSequentialArray($rawPropertySets)) {
            throw new \RuntimeException('Поле root.property_sets должно быть списком объектов.');
        }
        if (empty($rawPropertyTypes) && empty($rawProperties) && empty($rawPropertySets)) {
            throw new \RuntimeException('Файл не содержит ни одного раздела для импорта.');
        }

        $propertyTypes = [];
        $propertyTypeCodes = [];
        $propertyTypeNames = [];

        foreach ($rawPropertyTypes as $index => $item) {
            $path = 'property_types[' . $index . ']';
            if (!is_array($item)) {
                throw new \RuntimeException('Элемент ' . $path . ' должен быть объектом.');
            }
            $this->ensureAllowedKeys($item, ['code', 'name', 'status', 'description', 'fields'], $path);

            $code = $this->normalizeImportCode($item['code'] ?? '', $path . '.code');
            if (isset($propertyTypeCodes[$code])) {
                throw new \RuntimeException('Код `' . $code . '` в разделе property_types повторяется.');
            }

            $name = $this->normalizeRequiredString($item['name'] ?? '', $path . '.name');
            $nameKey = $this->normalizeLookupKey($name);
            if (!$allowDraftConflicts && isset($propertyTypeNames[$nameKey])) {
                throw new \RuntimeException('Название типа свойства `' . $name . '` повторяется в файле.');
            }

            $fields = $item['fields'] ?? null;
            if (!is_array($fields) || !$this->isSequentialArray($fields) || empty($fields)) {
                throw new \RuntimeException('Поле ' . $path . '.fields должно быть непустым списком типов полей.');
            }

            $normalizedFields = [];
            foreach ($fields as $fieldIndex => $fieldType) {
                $fieldType = strtolower($this->normalizeRequiredString(
                    $fieldType,
                    $path . '.fields[' . $fieldIndex . ']'
                ));
                if (!isset(Constants::ALL_TYPE_PROPERTY_TYPES_FIELDS[$fieldType])) {
                    throw new \RuntimeException(
                        'Тип поля `' . $fieldType . '` в ' . $path . '.fields[' . $fieldIndex . '] не поддерживается.'
                    );
                }
                $normalizedFields[] = $fieldType;
            }

            $propertyTypes[] = [
                'code' => $code,
                'name' => $name,
                'status' => $this->normalizeStatus($item['status'] ?? 'active', $path . '.status'),
                'description' => $this->normalizeOptionalString($item['description'] ?? ''),
                'fields' => $normalizedFields,
            ];
            $propertyTypeCodes[$code] = $normalizedFields;
            $propertyTypeNames[$nameKey] = true;
        }

        $properties = [];
        $propertyCodes = [];
        $propertyNames = [];

        foreach ($rawProperties as $index => $item) {
            $path = 'properties[' . $index . ']';
            if (!is_array($item)) {
                throw new \RuntimeException('Элемент ' . $path . ' должен быть объектом.');
            }
            $this->ensureAllowedKeys(
                $item,
                ['code', 'type_code', 'name', 'status', 'sort', 'entity_type', 'is_multiple', 'is_required', 'description', 'default_values'],
                $path
            );

            $code = $this->normalizeImportCode($item['code'] ?? '', $path . '.code');
            if (isset($propertyCodes[$code])) {
                throw new \RuntimeException('Код `' . $code . '` в разделе properties повторяется.');
            }

            $typeCode = $this->normalizeImportCode($item['type_code'] ?? '', $path . '.type_code');
            if (!isset($propertyTypeCodes[$typeCode])) {
                throw new \RuntimeException(
                    'Свойство ' . $path . ' ссылается на type_code `' . $typeCode . '`, которого нет в property_types.'
                );
            }

            $name = $this->normalizeRequiredString($item['name'] ?? '', $path . '.name');
            $propertyNameKey = $typeCode . '|' . $this->normalizeLookupKey($name);
            if (!$allowDraftConflicts && isset($propertyNames[$propertyNameKey])) {
                throw new \RuntimeException(
                    'Свойство `' . $name . '` уже объявлено для type_code `' . $typeCode . '` в этом файле.'
                );
            }

            $isMultiple = $this->normalizeBinaryFlag($item['is_multiple'] ?? 0, $path . '.is_multiple');
            $isRequired = $this->normalizeBinaryFlag($item['is_required'] ?? 0, $path . '.is_required');
            $fieldTypes = $propertyTypeCodes[$typeCode];
            $defaultValues = $item['default_values'] ?? null;
            if ($defaultValues === null) {
                $defaultValues = $this->buildAutoPropertyDefaultValues($fieldTypes, $name, $isMultiple, $isRequired, $path);
            } else {
                if (!is_array($defaultValues) || !$this->isSequentialArray($defaultValues)) {
                    throw new \RuntimeException('Поле ' . $path . '.default_values должно быть списком объектов.');
                }
                $defaultValues = $this->normalizePropertyDefaultValues(
                    $defaultValues,
                    $fieldTypes,
                    $name,
                    $isMultiple,
                    $isRequired,
                    $path . '.default_values'
                );
            }

            $properties[] = [
                'code' => $code,
                'type_code' => $typeCode,
                'name' => $name,
                'status' => $this->normalizeStatus($item['status'] ?? 'active', $path . '.status'),
                'sort' => $this->normalizeInteger($item['sort'] ?? 100, $path . '.sort', 0),
                'entity_type' => $this->normalizeEntityType($item['entity_type'] ?? 'all', $path . '.entity_type'),
                'is_multiple' => $isMultiple,
                'is_required' => $isRequired,
                'description' => $this->normalizeOptionalString($item['description'] ?? ''),
                'default_values' => $defaultValues,
            ];
            $propertyCodes[$code] = true;
            $propertyNames[$propertyNameKey] = true;
        }

        $propertySets = [];
        $propertySetCodes = [];
        $propertySetNames = [];

        foreach ($rawPropertySets as $index => $item) {
            $path = 'property_sets[' . $index . ']';
            if (!is_array($item)) {
                throw new \RuntimeException('Элемент ' . $path . ' должен быть объектом.');
            }
            $this->ensureAllowedKeys($item, ['code', 'name', 'status', 'description', 'properties'], $path);

            $code = $this->normalizeImportCode($item['code'] ?? '', $path . '.code');
            if (isset($propertySetCodes[$code])) {
                throw new \RuntimeException('Код `' . $code . '` в разделе property_sets повторяется.');
            }

            $name = $this->normalizeRequiredString($item['name'] ?? '', $path . '.name');
            $nameKey = $this->normalizeLookupKey($name);
            if (!$allowDraftConflicts && isset($propertySetNames[$nameKey])) {
                throw new \RuntimeException('Название набора `' . $name . '` повторяется в файле.');
            }

            $propertiesList = $item['properties'] ?? [];
            if (!is_array($propertiesList) || !$this->isSequentialArray($propertiesList)) {
                throw new \RuntimeException('Поле ' . $path . '.properties должно быть списком кодов свойств.');
            }

            $normalizedPropertyCodes = [];
            foreach ($propertiesList as $propertyIndex => $propertyCode) {
                $normalizedPropertyCode = $this->normalizeImportCode(
                    $propertyCode,
                    $path . '.properties[' . $propertyIndex . ']'
                );
                if (!isset($propertyCodes[$normalizedPropertyCode])) {
                    throw new \RuntimeException(
                        'Набор `' . $name . '` ссылается на свойство `' . $normalizedPropertyCode
                        . '`, которого нет в разделе properties.'
                    );
                }
                if (!in_array($normalizedPropertyCode, $normalizedPropertyCodes, true)) {
                    $normalizedPropertyCodes[] = $normalizedPropertyCode;
                }
            }

            $propertySets[] = [
                'code' => $code,
                'name' => $name,
                'description' => $this->normalizeOptionalString($item['description'] ?? ''),
                'properties' => $normalizedPropertyCodes,
            ];
            $propertySetCodes[$code] = true;
            $propertySetNames[$nameKey] = true;
        }

        return [
            'language_code' => $languageCode,
            'property_types' => $propertyTypes,
            'properties' => $properties,
            'property_sets' => $propertySets,
        ];
    }

    private function normalizePropertyDefaultValues(
        array $defaultValues,
        array $fieldTypes,
        string $propertyName,
        int $propertyIsMultiple,
        int $propertyIsRequired,
        string $path
    ): array {
        if (count($defaultValues) !== count($fieldTypes)) {
            throw new \RuntimeException(
                'Количество элементов в ' . $path . ' должно совпадать с количеством fields у типа свойства.'
            );
        }

        $normalized = [];
        foreach ($fieldTypes as $index => $fieldType) {
            $fieldPath = $path . '[' . $index . ']';
            $item = $defaultValues[$index];
            if (!is_array($item)) {
                throw new \RuntimeException('Элемент ' . $fieldPath . ' должен быть объектом.');
            }

            $declaredType = isset($item['type']) ? strtolower($this->normalizeRequiredString($item['type'], $fieldPath . '.type')) : $fieldType;
            if ($declaredType !== $fieldType) {
                throw new \RuntimeException(
                    'Поле ' . $fieldPath . '.type должно совпадать с типом `' . $fieldType . '` из property_types.fields.'
                );
            }

            if (in_array($fieldType, ['checkbox', 'radio'], true)) {
                $this->ensureAllowedKeys($item, ['type', 'label', 'title', 'required', 'multiple', 'default', 'options'], $fieldPath);
                $normalized[] = $this->normalizeChoiceDefaultValue(
                    $item,
                    $fieldType,
                    $fieldPath,
                    $propertyName,
                    $propertyIsMultiple,
                    $propertyIsRequired
                );
                continue;
            }

            if ($fieldType === 'select') {
                $this->ensureAllowedKeys($item, ['type', 'label', 'title', 'required', 'multiple', 'options'], $fieldPath);
                $normalized[] = $this->normalizeSelectDefaultValue(
                    $item,
                    $fieldPath,
                    $propertyName,
                    $propertyIsMultiple,
                    $propertyIsRequired
                );
                continue;
            }

            $this->ensureAllowedKeys($item, ['type', 'label', 'title', 'default', 'required', 'multiple'], $fieldPath);
            $normalized[] = $this->normalizeSimpleDefaultValue(
                $item,
                $fieldType,
                $fieldPath,
                $propertyName,
                $propertyIsMultiple,
                $propertyIsRequired
            );
        }

        return $normalized;
    }

    private function buildAutoPropertyDefaultValues(
        array $fieldTypes,
        string $propertyName,
        int $propertyIsMultiple,
        int $propertyIsRequired,
        string $path
    ): array {
        $normalized = [];
        $hasComplexTypes = array_intersect($fieldTypes, ['select', 'checkbox', 'radio']);
        if (!empty($hasComplexTypes)) {
            throw new \RuntimeException(
                'Поле ' . $path . '.default_values обязательно, если type.fields содержит select, checkbox или radio.'
            );
        }

        $fieldsCount = count($fieldTypes);
        foreach ($fieldTypes as $index => $fieldType) {
            $label = $fieldsCount === 1
                ? $propertyName
                : $propertyName . ' #' . ($index + 1);

            $default = $propertyIsMultiple ? [] : '';
            if (in_array($fieldType, ['file', 'image'], true)) {
                $default = '';
            }

            $normalized[] = [
                'type' => $fieldType,
                'label' => $label,
                'title' => '',
                'default' => $default,
                'required' => $propertyIsRequired,
                'multiple' => $propertyIsMultiple,
            ];
        }

        return $normalized;
    }

    private function normalizeSimpleDefaultValue(
        array $item,
        string $fieldType,
        string $path,
        string $propertyName,
        int $propertyIsMultiple,
        int $propertyIsRequired
    ): array {
        $multiple = $this->normalizeBinaryFlag($item['multiple'] ?? $propertyIsMultiple, $path . '.multiple');
        $required = $this->normalizeBinaryFlag($item['required'] ?? $propertyIsRequired, $path . '.required');
        $label = $this->normalizeOptionalString($item['label'] ?? '');
        if ($label === '') {
            $label = $propertyName;
        }

        $defaultValue = $item['default'] ?? ($multiple ? [] : '');
        if (in_array($fieldType, ['file', 'image'], true)) {
            if (!(is_string($defaultValue) && trim($defaultValue) === '') && !(is_array($defaultValue) && empty($defaultValue))) {
                throw new \RuntimeException(
                    'Поле ' . $path . '.default для типов file/image не поддерживается. Используйте пустое значение.'
                );
            }
            $defaultValue = '';
        } else {
            $defaultValue = $this->normalizeScalarOrList($defaultValue, $path . '.default', (bool)$multiple);
        }

        return [
            'type' => $fieldType,
            'label' => $label,
            'title' => $this->normalizeOptionalString($item['title'] ?? ''),
            'default' => $defaultValue,
            'required' => $required,
            'multiple' => $multiple,
        ];
    }

    private function normalizeSelectDefaultValue(
        array $item,
        string $path,
        string $propertyName,
        int $propertyIsMultiple,
        int $propertyIsRequired
    ): array {
        $multiple = $this->normalizeBinaryFlag($item['multiple'] ?? $propertyIsMultiple, $path . '.multiple');
        $required = $this->normalizeBinaryFlag($item['required'] ?? $propertyIsRequired, $path . '.required');
        $label = $this->normalizeOptionalString($item['label'] ?? '');
        if ($label === '') {
            $label = $propertyName;
        }

        $options = $item['options'] ?? null;
        if (!is_array($options) || !$this->isSequentialArray($options) || empty($options)) {
            throw new \RuntimeException('Поле ' . $path . '.options должно быть непустым списком.');
        }

        $encodedOptions = [];
        $selectedCount = 0;
        foreach ($options as $index => $option) {
            $optionPath = $path . '.options[' . $index . ']';
            if (!is_array($option)) {
                throw new \RuntimeException('Элемент ' . $optionPath . ' должен быть объектом.');
            }
            $this->ensureAllowedKeys($option, ['label', 'value', 'selected'], $optionPath);

            $optionLabel = $this->normalizeRequiredString($option['label'] ?? '', $optionPath . '.label');
            $optionValue = $this->normalizeRequiredString($option['value'] ?? '', $optionPath . '.value');
            $this->assertNoReservedSelectTokens($optionLabel, $optionPath . '.label');
            $this->assertNoReservedSelectTokens($optionValue, $optionPath . '.value');

            $selected = $this->normalizeBinaryFlag($option['selected'] ?? 0, $optionPath . '.selected');
            if ($selected) {
                $selectedCount++;
            }

            $encodedOptions[] = $optionLabel . '=' . $optionValue . ($selected ? '{*}' : '');
        }

        if (!$multiple && $selectedCount > 1) {
            throw new \RuntimeException('В ' . $path . '.options выбранно больше одного значения при multiple = 0.');
        }

        return [
            'type' => 'select',
            'label' => $label,
            'title' => $this->normalizeOptionalString($item['title'] ?? ''),
            'default' => implode('{|}', $encodedOptions),
            'required' => $required,
            'multiple' => $multiple,
        ];
    }

    private function normalizeChoiceDefaultValue(
        array $item,
        string $fieldType,
        string $path,
        string $propertyName,
        int $propertyIsMultiple,
        int $propertyIsRequired
    ): array {
        $required = $this->normalizeBinaryFlag($item['required'] ?? $propertyIsRequired, $path . '.required');
        $label = $this->normalizeOptionalString($item['label'] ?? '');
        if ($label === '') {
            $label = $propertyName;
        }
        $title = $this->normalizeOptionalString($item['title'] ?? '');
        if ($title === '') {
            $title = $label;
        }
        $options = $item['options'] ?? null;
        if (!is_array($options) || !$this->isSequentialArray($options) || empty($options)) {
            throw new \RuntimeException('Поле ' . $path . '.options должно быть непустым списком.');
        }

        $multiple = $fieldType === 'checkbox'
            ? 1
            : $this->normalizeBinaryFlag($item['multiple'] ?? $propertyIsMultiple, $path . '.multiple');
        if ($fieldType === 'radio') {
            $multiple = 0;
        }

        $defaultLookup = [];
        $hasExplicitDefault = array_key_exists('default', $item);
        if ($hasExplicitDefault) {
            foreach ($this->normalizeChoiceSelectionInput($item['default'], $path . '.default') as $selectedKey) {
                $defaultLookup[(string)$selectedKey] = true;
            }
        }

        $normalizedOptions = [];
        $selectedKeys = [];
        foreach ($options as $index => $option) {
            $optionPath = $path . '.options[' . $index . ']';
            if (!is_array($option)) {
                throw new \RuntimeException('Элемент ' . $optionPath . ' должен быть объектом.');
            }
            $this->ensureAllowedKeys($option, ['label', 'value', 'key', 'checked', 'selected', 'sort', 'disabled'], $optionPath);

            $optionLabel = $this->normalizeRequiredString($option['label'] ?? '', $optionPath . '.label');
            $optionKey = $this->normalizeOptionalString($option['key'] ?? ($option['value'] ?? ''));
            if ($optionKey === '') {
                $optionKey = 'option_' . ($index + 1);
            }
            $normalizedOption = [
                'label' => $optionLabel,
                'value' => $optionKey,
                'disabled' => $this->normalizeBinaryFlag($option['disabled'] ?? 0, $optionPath . '.disabled'),
                'sort' => isset($option['sort']) ? (int)$option['sort'] : (($index + 1) * 10),
            ];
            $normalizedOptions[] = $normalizedOption;

            $isSelected = $hasExplicitDefault
                ? isset($defaultLookup[$optionKey])
                : $this->normalizeBinaryFlag(($option['selected'] ?? ($option['checked'] ?? 0)), $optionPath . '.selected');
            if ($isSelected) {
                $selectedKeys[] = $optionKey;
            }
        }

        $selectedKeys = array_values(array_unique(array_map('strval', $selectedKeys)));
        if ($fieldType === 'radio' && count($selectedKeys) > 1) {
            throw new \RuntimeException('В ' . $path . '.options для radio можно отметить только один пункт.');
        }
        if ($fieldType === 'radio' && $selectedKeys !== []) {
            $selectedKeys = [reset($selectedKeys)];
        }

        return [
            'type' => $fieldType,
            'label' => $label,
            'title' => $title,
            'options' => $normalizedOptions,
            'default' => $selectedKeys,
            'required' => $required,
            'multiple' => $multiple,
        ];
    }

    private function normalizeChoiceSelectionInput(mixed $value, string $path): array {
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
            if ($value === null) {
                return [];
            }
            $value = [trim((string)$value)];
        }

        $result = [];
        foreach ($value as $index => $item) {
            if (!is_scalar($item) && $item !== null) {
                throw new \RuntimeException('Поле ' . $path . '[' . $index . '] должно быть строкой.');
            }
            $candidate = trim((string)$item);
            if ($candidate !== '') {
                $result[] = $candidate;
            }
        }

        return array_values(array_unique($result));
    }

    private function normalizeScalarOrList(mixed $value, string $path, bool $multiple): string|array {
        if ($multiple) {
            if ($value === null || $value === '') {
                return [];
            }
            if (!is_array($value)) {
                return [$this->normalizeScalarString($value, $path)];
            }

            $normalized = [];
            foreach ($value as $index => $item) {
                $normalized[] = $this->normalizeScalarString($item, $path . '[' . $index . ']');
            }
            return $normalized;
        }

        if (is_array($value)) {
            if (count($value) > 1) {
                throw new \RuntimeException('Поле ' . $path . ' не может быть массивом, если multiple = 0.');
            }
            $value = array_shift($value);
        }
        return $this->normalizeScalarString($value, $path);
    }

    private function normalizeScalarString(mixed $value, string $path): string {
        if (is_array($value) || is_object($value)) {
            throw new \RuntimeException('Поле ' . $path . ' должно быть строкой, числом или boolean.');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return trim((string)$value);
    }

    private function normalizeImportCode(mixed $value, string $path): string {
        $code = strtolower($this->normalizeRequiredString($value, $path));
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $code)) {
            throw new \RuntimeException(
                'Поле ' . $path . ' должно содержать только латиницу в нижнем регистре, цифры, точки, дефисы и подчёркивания.'
            );
        }
        return $code;
    }

    private function normalizeStatus(mixed $value, string $path): string {
        $status = strtolower($this->normalizeRequiredString($value, $path));
        if (!isset(Constants::ALL_STATUS[$status])) {
            throw new \RuntimeException(
                'Поле ' . $path . ' должно быть одним из: ' . implode(', ', array_keys(Constants::ALL_STATUS)) . '.'
            );
        }
        return $status;
    }

    private function normalizeEntityType(mixed $value, string $path): string {
        $entityType = strtolower($this->normalizeRequiredString($value, $path));
        if (!isset(Constants::ALL_ENTITY_TYPE[$entityType])) {
            throw new \RuntimeException(
                'Поле ' . $path . ' должно быть одним из: ' . implode(', ', array_keys(Constants::ALL_ENTITY_TYPE)) . '.'
            );
        }
        return $entityType;
    }

    private function normalizeInteger(mixed $value, string $path, int $min = PHP_INT_MIN): int {
        if (is_bool($value) || is_array($value) || is_object($value) || trim((string)$value) === '') {
            throw new \RuntimeException('Поле ' . $path . ' должно быть целым числом.');
        }

        $normalized = filter_var($value, FILTER_VALIDATE_INT);
        if ($normalized === false || $normalized < $min) {
            throw new \RuntimeException('Поле ' . $path . ' должно быть целым числом не меньше ' . $min . '.');
        }

        return (int)$normalized;
    }

    private function normalizeBinaryFlag(mixed $value, string $path): int {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return 1;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return 0;
            }
        }

        throw new \RuntimeException('Поле ' . $path . ' должно быть boolean, 0/1 или true/false.');
    }

    private function normalizeRequiredString(mixed $value, string $path): string {
        $normalized = $this->normalizeOptionalString($value);
        if ($normalized === '') {
            throw new \RuntimeException('Поле ' . $path . ' обязательно для заполнения.');
        }
        return $normalized;
    }

    private function normalizeOptionalString(mixed $value): string {
        if (is_array($value) || is_object($value)) {
            throw new \RuntimeException('Ожидалось строковое значение.');
        }
        return trim((string)$value);
    }

    private function ensureAllowedKeys(array $data, array $allowedKeys, string $path): void {
        foreach (array_keys($data) as $key) {
            if (!in_array((string)$key, $allowedKeys, true)) {
                throw new \RuntimeException('Поле ' . $path . '.' . $key . ' не поддерживается текущей схемой импорта.');
            }
        }
    }

    private function assertNoReservedSelectTokens(string $value, string $path): void {
        foreach (['{|}', '{*}', '='] as $token) {
            if (str_contains($value, $token)) {
                throw new \RuntimeException(
                    'Поле ' . $path . ' не может содержать служебную последовательность `' . $token . '`.'
                );
            }
        }
    }

    private function isSequentialArray(array $value): bool {
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function normalizeLookupKey(string $value): string {
        $value = trim($value);
        if (function_exists('mb_strtolower')) {
            return (string)mb_strtolower($value, 'UTF-8');
        }
        return strtolower($value);
    }

    private function buildExistingPropertyTypeIndex(string $languageCode): array {
        $types = $this->models['m_properties']->getAllPropertyTypes(Constants::ALL_STATUS, $languageCode);
        if (!is_array($types)) {
            return [];
        }
        $index = [];
        foreach ($types as $type) {
            $typeName = trim((string)($type['name'] ?? ''));
            if ($typeName === '') {
                continue;
            }
            $index[$this->normalizeLookupKey($typeName)] = $type;
        }
        return $index;
    }

    private function buildExistingPropertyIndex(string $languageCode): array {
        $properties = $this->models['m_properties']->getAllProperties(Constants::ALL_STATUS, $languageCode);
        if (!is_array($properties)) {
            return [];
        }
        $index = [];
        foreach ($properties as $property) {
            $typeId = (int)($property['type_id'] ?? 0);
            $name = trim((string)($property['name'] ?? ''));
            if ($typeId <= 0 || $name === '') {
                continue;
            }
            $index[$typeId . '|' . $this->normalizeLookupKey($name)] = $property;
        }
        return $index;
    }

    private function buildExistingPropertySetIndex(string $languageCode): array {
        $propertySets = $this->models['m_properties']->getAllPropertySetsData(false, ['set_id', 'name', 'description'], $languageCode);
        if (!is_array($propertySets)) {
            return [];
        }
        $index = [];
        foreach ($propertySets as $propertySet) {
            $name = trim((string)($propertySet['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $index[$this->normalizeLookupKey($name)] = $propertySet;
        }
        return $index;
    }

    private function isSupportedImportPackageName(string $filename): bool {
        $filename = trim($filename);
        if ($filename === '') {
            return false;
        }
        $ext = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, ['zip', 'jsonl', 'json'], true);
    }

    private function isSupportedImportPackagePath(string $path): bool {
        $path = trim($path);
        if ($path === '') {
            return false;
        }
        $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, ['zip', 'jsonl', 'json'], true);
    }
}
