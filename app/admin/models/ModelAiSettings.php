<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\SysClass;

class ModelAiSettings {

    public const PROVIDER_OPENROUTER = 'openrouter';
    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_YANDEX_CLOUD = 'yandex_cloud';
    public const PROVIDER_SBERCLOUD = 'sbercloud';
    public const PROVIDER_VK_CLOUD = 'vk_cloud';
    public const DEFAULT_OPENROUTER_BASE_URL = 'https://openrouter.ai/api/v1';
    public const DEFAULT_OPENAI_BASE_URL = 'https://api.openai.com/v1';
    public const DEFAULT_YANDEX_CLOUD_BASE_URL = 'https://ai.api.cloud.yandex.net/v1';
    public const DEFAULT_SBERCLOUD_BASE_URL = 'https://gigachat.devices.sberbank.ru/api/v1';
    public const DEFAULT_SBERCLOUD_OAUTH_URL = 'https://ngw.devices.sberbank.ru:9443/api/v2/oauth';
    public const DEFAULT_VK_CLOUD_BASE_URL = '';

    private const SBERCLOUD_SCOPES = [
        'GIGACHAT_API_PERS',
        'GIGACHAT_API_B2B',
        'GIGACHAT_API_CORP',
    ];

    public function ensureInfrastructure(): void {
        SysClass::installAiProfilesSchema();
    }

    public function getProviderOptions(): array {
        return [
            [
                'value' => self::PROVIDER_OPENROUTER,
                'label' => 'OpenRouter',
                'supports_model_catalog' => true,
            ],
            [
                'value' => self::PROVIDER_OPENAI,
                'label' => 'OpenAI',
                'supports_model_catalog' => true,
            ],
            [
                'value' => self::PROVIDER_YANDEX_CLOUD,
                'label' => 'Yandex Cloud AI Studio',
                'supports_model_catalog' => true,
            ],
            [
                'value' => self::PROVIDER_SBERCLOUD,
                'label' => 'SberCloud / GigaChat',
                'supports_model_catalog' => true,
            ],
            [
                'value' => self::PROVIDER_VK_CLOUD,
                'label' => 'VK Cloud',
                'supports_model_catalog' => true,
            ],
        ];
    }

    public function getDefaultProfile(): array {
        return [
            'ai_profile_id' => 0,
            'name' => '',
            'profile_code' => '',
            'provider' => self::PROVIDER_OPENROUTER,
            'api_base_url' => self::DEFAULT_OPENROUTER_BASE_URL,
            'model' => '',
            'enabled' => 0,
            'api_key_mask' => '',
            'has_api_key' => false,
            'settings_json' => '',
            'provider_settings' => [],
            'last_test_at' => '',
            'last_test_ok' => null,
            'last_test_message' => '',
            'created_at' => '',
            'updated_at' => '',
        ];
    }

    public function getProfiles(): array {
        $this->ensureInfrastructure();
        return SafeMySQL::gi()->getAll(
            'SELECT ai_profile_id, name, profile_code, provider, enabled
             FROM ?n
             ORDER BY enabled DESC, name ASC, profile_code ASC',
            Constants::AI_PROFILES_TABLE
        );
    }

    public function getProfileById(int $profileId): ?array {
        $this->ensureInfrastructure();
        if ($profileId <= 0) {
            return null;
        }
        $row = SafeMySQL::gi()->getRow(
            'SELECT ai_profile_id, name, profile_code, provider, api_base_url, model, enabled, api_key_mask, settings_json,
                    last_test_at, last_test_ok, last_test_message, created_at, updated_at
             FROM ?n
             WHERE ai_profile_id = ?i
             LIMIT 1',
            Constants::AI_PROFILES_TABLE,
            $profileId
        );
        if (!is_array($row)) {
            return null;
        }
        $row['has_api_key'] = trim((string) ($row['api_key_mask'] ?? '')) !== '';
        $row['provider_settings'] = $this->normalizeProviderSettings(
            (string) ($row['provider'] ?? ''),
            (string) ($row['settings_json'] ?? '')
        );
        return $row;
    }

    public function getProviderSettingsContext(string $provider, array $profile = []): array {
        $provider = $this->normalizeProvider($provider);
        $settings = $this->normalizeProviderSettings($provider, $profile['provider_settings'] ?? ($profile['settings_json'] ?? []));
        $apiKeyLabel = match ($provider) {
            self::PROVIDER_SBERCLOUD => 'Authorization key',
            default => 'API key',
        };
        $apiKeyHelp = match ($provider) {
            self::PROVIDER_YANDEX_CLOUD => 'sys.ai_yandex_api_key_help',
            self::PROVIDER_SBERCLOUD => 'sys.ai_sber_api_key_help',
            self::PROVIDER_VK_CLOUD => 'sys.ai_vk_api_key_help',
            default => '',
        };
        return [
            'provider' => $provider,
            'provider_label' => $this->getProviderLabel($provider),
            'api_base_url' => $this->resolveProviderBaseUrl($provider, (string) ($profile['api_base_url'] ?? '')),
            'model' => trim((string) ($profile['model'] ?? '')),
            'api_key_mask' => trim((string) ($profile['api_key_mask'] ?? '')),
            'has_api_key' => !empty($profile['has_api_key']) || trim((string) ($profile['api_key_mask'] ?? '')) !== '',
            'supports_model_catalog' => $this->providerSupportsModelCatalog($provider),
            'settings' => $settings,
            'api_key_label' => $apiKeyLabel,
            'api_key_help_key' => $apiKeyHelp,
        ];
    }

    public function saveProfile(array $input, int $profileId = 0): int {
        $this->ensureInfrastructure();

        $name = trim((string) ($input['name'] ?? ''));
        $profileCode = $this->normalizeProfileCode((string) ($input['profile_code'] ?? ''));
        $provider = $this->normalizeProvider((string) ($input['provider'] ?? self::PROVIDER_OPENROUTER));
        $apiBaseUrl = rtrim(trim((string) ($input['api_base_url'] ?? $this->getDefaultProviderBaseUrl($provider))), '/');
        $model = trim((string) ($input['model'] ?? ''));
        $providerSettings = $this->normalizeProviderSettings($provider, $input['provider_settings'] ?? []);

        if ($name === '') {
            throw new InvalidArgumentException('sys.ai_profile_name_required');
        }
        if ($profileCode === '') {
            throw new InvalidArgumentException('sys.ai_profile_code_required');
        }
        if ($apiBaseUrl === '' || !preg_match('~^https?://~i', $apiBaseUrl)) {
            throw new InvalidArgumentException('sys.ai_api_base_url_invalid');
        }

        $existingByCode = (int) SafeMySQL::gi()->getOne(
            'SELECT ai_profile_id FROM ?n WHERE profile_code = ?s LIMIT 1',
            Constants::AI_PROFILES_TABLE,
            $profileCode
        );
        if ($existingByCode > 0 && $existingByCode !== $profileId) {
            throw new InvalidArgumentException('sys.ai_profile_code_exists');
        }

        $row = [
            'name' => $name,
            'profile_code' => $profileCode,
            'provider' => $provider,
            'api_base_url' => $apiBaseUrl,
            'model' => $model,
            'enabled' => !empty($input['enabled']) ? 1 : 0,
            'settings_json' => json_encode($providerSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ];

        $apiKey = trim((string) ($input['api_key'] ?? ''));
        if ($apiKey !== '') {
            $row['encrypted_api_key'] = $this->encryptApiKey($apiKey);
            $row['api_key_mask'] = $this->maskApiKey($apiKey);
        }

        if ($profileId > 0) {
            $existingId = (int) SafeMySQL::gi()->getOne(
                'SELECT ai_profile_id FROM ?n WHERE ai_profile_id = ?i LIMIT 1',
                Constants::AI_PROFILES_TABLE,
                $profileId
            );
            if ($existingId <= 0) {
                throw new RuntimeException('sys.ai_profile_not_found');
            }

            SafeMySQL::gi()->query(
                'UPDATE ?n SET ?u WHERE ai_profile_id = ?i',
                Constants::AI_PROFILES_TABLE,
                $row,
                $profileId
            );
            return $profileId;
        }

        SafeMySQL::gi()->query('INSERT INTO ?n SET ?u', Constants::AI_PROFILES_TABLE, $row);
        return (int) SafeMySQL::gi()->insertId();
    }

    public function getProfileStats(): array {
        $this->ensureInfrastructure();
        $row = SafeMySQL::gi()->getRow(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN enabled = 1 THEN 1 ELSE 0 END) AS enabled,
                SUM(CASE WHEN enabled = 0 THEN 1 ELSE 0 END) AS disabled,
                SUM(CASE WHEN api_key_mask <> "" THEN 1 ELSE 0 END) AS with_api_key
             FROM ?n',
            Constants::AI_PROFILES_TABLE
        );

        return [
            'total' => (int) ($row['total'] ?? 0),
            'enabled' => (int) ($row['enabled'] ?? 0),
            'disabled' => (int) ($row['disabled'] ?? 0),
            'with_api_key' => (int) ($row['with_api_key'] ?? 0),
        ];
    }

    public function searchProviderModels(string $provider, int $profileId = 0, string $query = '', int $limit = 50): array {
        $provider = $this->normalizeProvider($provider);
        $limit = max(1, min(100, $limit));
        if (!$this->providerSupportsModelCatalog($provider)) {
            return [];
        }

        $runtimeProfile = $profileId > 0 ? $this->getRuntimeProfileById($profileId) : null;
        $catalog = $this->loadProviderModelCatalog($provider, is_array($runtimeProfile) ? $runtimeProfile : null);
        $models = is_array($catalog['models'] ?? null) ? $catalog['models'] : [];
        $query = mb_strtolower(trim($query), 'UTF-8');

        if ($query !== '') {
            $models = array_values(array_filter($models, static function (array $model) use ($query): bool {
                $haystack = mb_strtolower((string) ($model['id'] ?? '') . ' ' . (string) ($model['name'] ?? ''), 'UTF-8');
                return str_contains($haystack, $query);
            }));
        }

        return array_slice($models, 0, $limit);
    }

    public function testConnection(int $profileId): array {
        $profile = $this->getRuntimeProfileById($profileId);
        if (!is_array($profile)) {
            return $this->storeTestResult($profileId, false, 'profile_not_found', 'AI profile was not found.');
        }
        $provider = $this->normalizeProvider((string) ($profile['provider'] ?? ''));
        if (!$this->providerSupportsModelCatalog($provider)) {
            return $this->storeTestResult($profileId, false, 'provider_not_supported', 'Provider is not supported yet.');
        }
        if (trim((string) ($profile['api_key'] ?? '')) === '') {
            return $this->storeTestResult($profileId, false, 'api_key_missing', 'API key is not configured.');
        }

        $result = $this->requestProviderModels($provider, $profile, true);
        $ok = !empty($result['ok']);
        $message = $ok
            ? 'Connection OK. Models found: ' . (int) ($result['models_count'] ?? 0)
            : (string) ($result['message'] ?? $result['error_code'] ?? 'Connection test failed.');

        return $this->storeTestResult(
            $profileId,
            $ok,
            (string) ($result['error_code'] ?? ($ok ? 'ok' : 'provider_error')),
            $message,
            [
                'http_code' => (int) ($result['http_code'] ?? 0),
                'models_count' => (int) ($result['models_count'] ?? 0),
            ]
        );
    }

    private function getRuntimeProfileById(int $profileId): ?array {
        $this->ensureInfrastructure();
        if ($profileId <= 0) {
            return null;
        }

        $row = SafeMySQL::gi()->getRow(
            'SELECT *
             FROM ?n
             WHERE ai_profile_id = ?i
             LIMIT 1',
            Constants::AI_PROFILES_TABLE,
            $profileId
        );
        if (!is_array($row)) {
            return null;
        }

        $row['has_api_key'] = trim((string) ($row['api_key_mask'] ?? '')) !== '';
        $row['api_key'] = $this->decryptApiKey((string) ($row['encrypted_api_key'] ?? ''));
        $row['provider_settings'] = $this->normalizeProviderSettings(
            (string) ($row['provider'] ?? ''),
            (string) ($row['settings_json'] ?? '')
        );
        return $row;
    }

    private function loadProviderModelCatalog(string $provider, ?array $profile = null): array {
        $provider = $this->normalizeProvider($provider);
        $requestProfile = is_array($profile) ? $profile : [
            'api_base_url' => $this->getDefaultProviderBaseUrl($provider),
            'api_key' => '',
            'provider_settings' => [],
        ];
        $cacheFile = ENV_SITE_PATH . 'cache/ai/' . $this->buildModelCacheKey($provider, $requestProfile) . '_models.json';
        $ttlSeconds = 86400;
        if (is_file($cacheFile) && filemtime($cacheFile) !== false && (time() - filemtime($cacheFile) < $ttlSeconds)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached) && is_array($cached['models'] ?? null)) {
                return ['ok' => true, 'cached' => true, 'models' => $cached['models']];
            }
        }

        $result = $this->requestProviderModels($provider, $requestProfile, false);
        if (!empty($result['ok']) && is_array($result['models'] ?? null)) {
            $models = $this->normalizeProviderModels($provider, (array) $result['models']);
            $cacheDir = dirname($cacheFile);
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0775, true);
            }
            if (is_dir($cacheDir) && is_writable($cacheDir)) {
                @file_put_contents($cacheFile, json_encode([
                    'cached_at' => date('c'),
                    'models' => $models,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            }
            return ['ok' => true, 'cached' => false, 'models' => $models];
        }

        return ['ok' => false, 'models' => $this->fallbackProviderModels($provider, $requestProfile)];
    }

    private function requestProviderModels(string $provider, array $profile, bool $requireApiKey): array {
        $provider = $this->normalizeProvider($provider);
        if ($provider === self::PROVIDER_SBERCLOUD) {
            return $this->requestSberCloudModels($profile, $requireApiKey);
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error_code' => 'curl_missing', 'message' => 'curl extension is not available.'];
        }

        $apiKey = trim((string) ($profile['api_key'] ?? ''));
        if ($requireApiKey && $apiKey === '') {
            return ['ok' => false, 'error_code' => 'api_key_missing', 'message' => 'API key is not configured.'];
        }
        if (!$requireApiKey && $apiKey === '' && $this->providerRequiresApiKeyForCatalog($provider)) {
            return ['ok' => false, 'error_code' => 'api_key_missing', 'message' => 'API key is not configured.'];
        }

        $baseUrl = rtrim(trim((string) ($profile['api_base_url'] ?? $this->getDefaultProviderBaseUrl($provider))), '/');
        if ($baseUrl === '' || !preg_match('~^https?://~i', $baseUrl)) {
            return ['ok' => false, 'error_code' => 'invalid_base_url', 'message' => 'Invalid API base URL.'];
        }

        $headers = ['Accept: application/json'];
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $ch = curl_init($baseUrl . '/models');
        if ($ch === false) {
            return ['ok' => false, 'error_code' => 'curl_init_failed', 'message' => 'Failed to initialize curl.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($body === false || $error !== '') {
            return [
                'ok' => false,
                'http_code' => $httpCode,
                'error_code' => $errno === 28 ? 'timeout' : 'curl_error',
                'message' => $error !== '' ? $error : 'curl_exec_failed',
            ];
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'http_code' => $httpCode, 'error_code' => 'invalid_json_response', 'message' => 'Provider returned invalid JSON.'];
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'ok' => false,
                'http_code' => $httpCode,
                'error_code' => 'http_' . $httpCode,
                'message' => $this->extractProviderErrorMessage($decoded, $this->getProviderLabel($provider) . ' models request failed.'),
            ];
        }

        $models = $this->extractModelsFromResponse($decoded);
        return [
            'ok' => true,
            'http_code' => $httpCode,
            'models' => $models,
            'models_count' => count($models),
        ];
    }

    private function requestSberCloudModels(array $profile, bool $requireApiKey): array {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error_code' => 'curl_missing', 'message' => 'curl extension is not available.'];
        }

        $apiKey = trim((string) ($profile['api_key'] ?? ''));
        if ($requireApiKey && $apiKey === '') {
            return ['ok' => false, 'error_code' => 'api_key_missing', 'message' => 'API key is not configured.'];
        }
        if ($apiKey === '') {
            return ['ok' => false, 'error_code' => 'api_key_missing', 'message' => 'API key is not configured.'];
        }

        $settings = $this->normalizeProviderSettings(self::PROVIDER_SBERCLOUD, $profile['provider_settings'] ?? ($profile['settings_json'] ?? []));
        $tokenResult = $this->requestSberCloudAccessToken($apiKey, $settings);
        if (empty($tokenResult['ok'])) {
            return $tokenResult;
        }

        $baseUrl = rtrim(trim((string) ($profile['api_base_url'] ?? self::DEFAULT_SBERCLOUD_BASE_URL)), '/');
        if ($baseUrl === '' || !preg_match('~^https?://~i', $baseUrl)) {
            return ['ok' => false, 'error_code' => 'invalid_base_url', 'message' => 'Invalid API base URL.'];
        }

        $ch = curl_init($baseUrl . '/models');
        if ($ch === false) {
            return ['ok' => false, 'error_code' => 'curl_init_failed', 'message' => 'Failed to initialize curl.'];
        }

        $verifySsl = !empty($settings['ssl_verify']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . (string) ($tokenResult['access_token'] ?? ''),
            ],
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($body === false || $error !== '') {
            return [
                'ok' => false,
                'http_code' => $httpCode,
                'error_code' => $errno === 28 ? 'timeout' : 'curl_error',
                'message' => $error !== '' ? $error : 'curl_exec_failed',
            ];
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'http_code' => $httpCode, 'error_code' => 'invalid_json_response', 'message' => 'Provider returned invalid JSON.'];
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'ok' => false,
                'http_code' => $httpCode,
                'error_code' => 'http_' . $httpCode,
                'message' => $this->extractProviderErrorMessage($decoded, 'GigaChat models request failed.'),
            ];
        }

        $models = $this->extractModelsFromResponse($decoded);
        return [
            'ok' => true,
            'http_code' => $httpCode,
            'models' => $models,
            'models_count' => count($models),
        ];
    }

    private function requestSberCloudAccessToken(string $apiKey, array $settings): array {
        $oauthUrl = rtrim(trim((string) ($settings['oauth_url'] ?? self::DEFAULT_SBERCLOUD_OAUTH_URL)), '/');
        if ($oauthUrl === '' || !preg_match('~^https?://~i', $oauthUrl)) {
            return ['ok' => false, 'error_code' => 'invalid_oauth_url', 'message' => 'Invalid OAuth URL.'];
        }

        $scope = (string) ($settings['scope'] ?? 'GIGACHAT_API_PERS');
        $ch = curl_init($oauthUrl);
        if ($ch === false) {
            return ['ok' => false, 'error_code' => 'curl_init_failed', 'message' => 'Failed to initialize curl.'];
        }

        $verifySsl = !empty($settings['ssl_verify']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['scope' => $scope], '', '&'),
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'RqUID: ' . $this->generateUuidV4(),
                $this->buildSberCloudAuthorizationHeader($apiKey),
            ],
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($body === false || $error !== '') {
            return [
                'ok' => false,
                'http_code' => $httpCode,
                'error_code' => $errno === 28 ? 'timeout' : 'curl_error',
                'message' => $error !== '' ? $error : 'curl_exec_failed',
            ];
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'http_code' => $httpCode, 'error_code' => 'invalid_json_response', 'message' => 'OAuth endpoint returned invalid JSON.'];
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'ok' => false,
                'http_code' => $httpCode,
                'error_code' => 'http_' . $httpCode,
                'message' => $this->extractProviderErrorMessage($decoded, 'GigaChat OAuth request failed.'),
            ];
        }

        $accessToken = trim((string) ($decoded['access_token'] ?? ''));
        if ($accessToken === '') {
            return ['ok' => false, 'http_code' => $httpCode, 'error_code' => 'access_token_missing', 'message' => 'OAuth response does not contain access_token.'];
        }

        return [
            'ok' => true,
            'http_code' => $httpCode,
            'access_token' => $accessToken,
            'expires_at' => (int) ($decoded['expires_at'] ?? 0),
        ];
    }

    private function extractModelsFromResponse(array $decoded): array {
        if (is_array($decoded['data'] ?? null)) {
            return (array) $decoded['data'];
        }
        if (is_array($decoded['models'] ?? null)) {
            return (array) $decoded['models'];
        }
        return array_is_list($decoded) ? $decoded : [];
    }

    private function extractProviderErrorMessage(array $decoded, string $fallback): string {
        $message = $decoded['error']['message'] ?? $decoded['error_description'] ?? $decoded['message'] ?? $decoded['detail'] ?? '';
        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $message = trim((string) $message);
        return $message !== '' ? mb_substr($message, 0, 300, 'UTF-8') : $fallback;
    }

    private function buildSberCloudAuthorizationHeader(string $apiKey): string {
        $apiKey = trim($apiKey);
        if (preg_match('~^(Basic|Bearer)\s+~i', $apiKey)) {
            return 'Authorization: ' . $apiKey;
        }
        return 'Authorization: Basic ' . $apiKey;
    }

    private function generateUuidV4(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function normalizeProviderModels(string $provider, array $rows): array {
        $models = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $models[] = [
                'id' => $id,
                'name' => trim((string) ($row['name'] ?? $id)) ?: $id,
                'owned_by' => trim((string) ($row['owned_by'] ?? '')),
                'context_length' => (int) ($row['context_length'] ?? 0),
            ];
        }

        usort($models, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });
        return $models;
    }

    private function fallbackProviderModels(string $provider, array $profile = []): array {
        $provider = $this->normalizeProvider($provider);
        if ($provider === self::PROVIDER_OPENAI) {
            return [
                ['id' => 'gpt-5.5', 'name' => 'GPT-5.5', 'owned_by' => 'openai', 'context_length' => 0],
                ['id' => 'gpt-5.4', 'name' => 'GPT-5.4', 'owned_by' => 'openai', 'context_length' => 0],
                ['id' => 'gpt-5.4-mini', 'name' => 'GPT-5.4 mini', 'owned_by' => 'openai', 'context_length' => 0],
                ['id' => 'gpt-5.4-nano', 'name' => 'GPT-5.4 nano', 'owned_by' => 'openai', 'context_length' => 0],
            ];
        }

        if ($provider === self::PROVIDER_YANDEX_CLOUD) {
            $settings = $this->normalizeProviderSettings($provider, $profile['provider_settings'] ?? ($profile['settings_json'] ?? []));
            $folderId = trim((string) ($settings['folder_id'] ?? ''));
            $folderId = $folderId !== '' ? $folderId : '<folder_ID>';
            return [
                ['id' => 'gpt://' . $folderId . '/aliceai-llm', 'name' => 'Alice AI LLM', 'owned_by' => 'yandex', 'context_length' => 65536],
                ['id' => 'gpt://' . $folderId . '/aliceai-llm-flash', 'name' => 'Alice AI LLM Flash', 'owned_by' => 'yandex', 'context_length' => 65536],
                ['id' => 'gpt://' . $folderId . '/yandexgpt-5.1', 'name' => 'YandexGPT Pro 5.1', 'owned_by' => 'yandex', 'context_length' => 32768],
                ['id' => 'gpt://' . $folderId . '/yandexgpt-5-pro', 'name' => 'YandexGPT Pro 5', 'owned_by' => 'yandex', 'context_length' => 32768],
                ['id' => 'gpt://' . $folderId . '/yandexgpt-5-lite', 'name' => 'YandexGPT Lite 5', 'owned_by' => 'yandex', 'context_length' => 32768],
                ['id' => 'gpt://' . $folderId . '/deepseek-v4-flash', 'name' => 'DeepSeek V4 Flash', 'owned_by' => 'yandex', 'context_length' => 1048576],
                ['id' => 'gpt://' . $folderId . '/qwen3-235b-a22b-fp8', 'name' => 'Qwen3 235B', 'owned_by' => 'yandex', 'context_length' => 262144],
                ['id' => 'gpt://' . $folderId . '/gpt-oss-120b', 'name' => 'gpt-oss-120b', 'owned_by' => 'yandex', 'context_length' => 131072],
                ['id' => 'gpt://' . $folderId . '/gpt-oss-20b', 'name' => 'gpt-oss-20b', 'owned_by' => 'yandex', 'context_length' => 131072],
                ['id' => 'gpt://' . $folderId . '/qwen3.6-35b-a3b', 'name' => 'Qwen3.6 35B', 'owned_by' => 'yandex', 'context_length' => 262144],
            ];
        }

        if ($provider === self::PROVIDER_SBERCLOUD) {
            return [
                ['id' => 'GigaChat', 'name' => 'GigaChat', 'owned_by' => 'sber', 'context_length' => 0],
                ['id' => 'GigaChat-2-Max', 'name' => 'GigaChat 2 Max', 'owned_by' => 'sber', 'context_length' => 0],
                ['id' => 'GigaChat-2-Pro', 'name' => 'GigaChat 2 Pro', 'owned_by' => 'sber', 'context_length' => 0],
                ['id' => 'GigaChat-2-Lite', 'name' => 'GigaChat 2 Lite', 'owned_by' => 'sber', 'context_length' => 0],
                ['id' => 'EmbeddingsGigaR', 'name' => 'EmbeddingsGigaR', 'owned_by' => 'sber', 'context_length' => 0],
            ];
        }

        if ($provider === self::PROVIDER_VK_CLOUD) {
            return [];
        }

        return [
            ['id' => 'openrouter/free', 'name' => 'OpenRouter free router', 'owned_by' => '', 'context_length' => 0],
        ];
    }

    private function storeTestResult(int $profileId, bool $ok, string $code, string $message, array $extra = []): array {
        if ($profileId > 0) {
            SafeMySQL::gi()->query(
                'UPDATE ?n SET last_test_at = NOW(), last_test_ok = ?i, last_test_message = ?s WHERE ai_profile_id = ?i',
                Constants::AI_PROFILES_TABLE,
                $ok ? 1 : 0,
                mb_substr($message, 0, 255, 'UTF-8'),
                $profileId
            );
        }

        return array_merge([
            'ok' => $ok,
            'error_code' => $code,
            'message' => $message,
        ], $extra);
    }

    private function normalizeProfileCode(string $code): string {
        $code = strtolower(trim($code));
        $code = preg_replace('~[^a-z0-9_-]+~', '_', $code) ?? '';
        return trim($code, '_-');
    }

    private function normalizeProvider(string $provider): string {
        $provider = strtolower(trim($provider));
        return in_array($provider, $this->getSupportedProviderCodes(), true)
            ? $provider
            : self::PROVIDER_OPENROUTER;
    }

    private function getSupportedProviderCodes(): array {
        return [
            self::PROVIDER_OPENROUTER,
            self::PROVIDER_OPENAI,
            self::PROVIDER_YANDEX_CLOUD,
            self::PROVIDER_SBERCLOUD,
            self::PROVIDER_VK_CLOUD,
        ];
    }

    private function getDefaultProviderBaseUrl(string $provider): string {
        return match ($this->normalizeProvider($provider)) {
            self::PROVIDER_OPENAI => self::DEFAULT_OPENAI_BASE_URL,
            self::PROVIDER_YANDEX_CLOUD => self::DEFAULT_YANDEX_CLOUD_BASE_URL,
            self::PROVIDER_SBERCLOUD => self::DEFAULT_SBERCLOUD_BASE_URL,
            self::PROVIDER_VK_CLOUD => self::DEFAULT_VK_CLOUD_BASE_URL,
            default => self::DEFAULT_OPENROUTER_BASE_URL,
        };
    }

    private function resolveProviderBaseUrl(string $provider, string $currentValue): string {
        $provider = $this->normalizeProvider($provider);
        $value = rtrim(trim($currentValue), '/');
        if ($value === '') {
            return $this->getDefaultProviderBaseUrl($provider);
        }
        foreach ($this->getSupportedProviderCodes() as $knownProvider) {
            if ($knownProvider !== $provider && $value === $this->getDefaultProviderBaseUrl($knownProvider)) {
                return $this->getDefaultProviderBaseUrl($provider);
            }
        }
        return $value;
    }

    private function getProviderLabel(string $provider): string {
        return match ($this->normalizeProvider($provider)) {
            self::PROVIDER_OPENAI => 'OpenAI',
            self::PROVIDER_YANDEX_CLOUD => 'Yandex Cloud AI Studio',
            self::PROVIDER_SBERCLOUD => 'SberCloud / GigaChat',
            self::PROVIDER_VK_CLOUD => 'VK Cloud',
            default => 'OpenRouter',
        };
    }

    private function providerSupportsModelCatalog(string $provider): bool {
        return in_array($this->normalizeProvider($provider), $this->getSupportedProviderCodes(), true);
    }

    private function providerRequiresApiKeyForCatalog(string $provider): bool {
        return in_array($this->normalizeProvider($provider), [
            self::PROVIDER_OPENAI,
            self::PROVIDER_YANDEX_CLOUD,
            self::PROVIDER_SBERCLOUD,
            self::PROVIDER_VK_CLOUD,
        ], true);
    }

    private function decodeProviderSettings(mixed $settings): array {
        if (is_array($settings)) {
            return $settings;
        }

        $settings = trim((string) $settings);
        if ($settings === '') {
            return [];
        }

        $decoded = json_decode($settings, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeProviderSettings(string $provider, mixed $settings): array {
        $provider = $this->normalizeProvider($provider);
        $settings = $this->decodeProviderSettings($settings);

        if ($provider === self::PROVIDER_YANDEX_CLOUD) {
            $folderId = preg_replace('~[^a-zA-Z0-9_-]+~', '', (string) ($settings['folder_id'] ?? '')) ?? '';
            return [
                'folder_id' => $folderId,
            ];
        }

        if ($provider === self::PROVIDER_SBERCLOUD) {
            $oauthUrl = rtrim(trim((string) ($settings['oauth_url'] ?? self::DEFAULT_SBERCLOUD_OAUTH_URL)), '/');
            if ($oauthUrl === '' || !preg_match('~^https?://~i', $oauthUrl)) {
                $oauthUrl = self::DEFAULT_SBERCLOUD_OAUTH_URL;
            }
            $scope = strtoupper(trim((string) ($settings['scope'] ?? 'GIGACHAT_API_PERS')));
            if (!in_array($scope, self::SBERCLOUD_SCOPES, true)) {
                $scope = 'GIGACHAT_API_PERS';
            }
            $sslVerify = array_key_exists('ssl_verify', $settings) ? !empty($settings['ssl_verify']) : true;
            return [
                'oauth_url' => $oauthUrl,
                'scope' => $scope,
                'ssl_verify' => $sslVerify ? 1 : 0,
            ];
        }

        return [];
    }

    private function buildModelCacheKey(string $provider, array $profile): string {
        $provider = $this->normalizeProvider($provider);
        $settings = $this->normalizeProviderSettings($provider, $profile['provider_settings'] ?? ($profile['settings_json'] ?? []));
        $fingerprint = '';
        if ($provider === self::PROVIDER_YANDEX_CLOUD) {
            $fingerprint = (string) ($settings['folder_id'] ?? '');
        } elseif ($provider === self::PROVIDER_SBERCLOUD) {
            $fingerprint = (string) ($profile['api_base_url'] ?? '') . '|' . (string) ($settings['scope'] ?? '');
        } elseif ($provider === self::PROVIDER_VK_CLOUD) {
            $fingerprint = (string) ($profile['api_base_url'] ?? '');
        }

        $suffix = $fingerprint !== '' ? '_' . substr(sha1($fingerprint), 0, 12) : '';
        return preg_replace('~[^a-z0-9_-]+~', '_', $provider . $suffix) ?: $provider;
    }

    private function maskApiKey(string $apiKey): string {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            return '';
        }
        $prefix = substr($apiKey, 0, min(8, strlen($apiKey)));
        $suffix = strlen($apiKey) > 12 ? substr($apiKey, -4) : '';
        return $prefix . '...' . $suffix;
    }

    private function encryptApiKey(string $apiKey): string {
        if (!defined('ENV_SECRET_KEY') || trim((string) ENV_SECRET_KEY) === '') {
            throw new RuntimeException('ENV_SECRET_KEY is required to encrypt AI profile API keys.');
        }
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('OpenSSL extension is required to encrypt AI profile API keys.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($apiKey, 'aes-256-gcm', hash('sha256', (string) ENV_SECRET_KEY, true), OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($cipher) || $tag === '') {
            throw new RuntimeException('Failed to encrypt AI profile API key.');
        }

        return json_encode([
            'v' => 1,
            'alg' => 'aes-256-gcm',
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'cipher' => base64_encode($cipher),
        ], JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function decryptApiKey(string $payload): string {
        $payload = trim($payload);
        if ($payload === '') {
            return '';
        }
        if (!defined('ENV_SECRET_KEY') || trim((string) ENV_SECRET_KEY) === '' || !function_exists('openssl_decrypt')) {
            return '';
        }

        $data = json_decode($payload, true);
        if (!is_array($data) || ($data['alg'] ?? '') !== 'aes-256-gcm') {
            return '';
        }

        $iv = base64_decode((string) ($data['iv'] ?? ''), true);
        $tag = base64_decode((string) ($data['tag'] ?? ''), true);
        $cipher = base64_decode((string) ($data['cipher'] ?? ''), true);
        if (!is_string($iv) || !is_string($tag) || !is_string($cipher)) {
            return '';
        }

        $plain = openssl_decrypt($cipher, 'aes-256-gcm', hash('sha256', (string) ENV_SECRET_KEY, true), OPENSSL_RAW_DATA, $iv, $tag);
        return is_string($plain) ? $plain : '';
    }
}
