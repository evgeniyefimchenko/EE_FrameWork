<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

class LegalConsentService {

    private const PROVIDER_SESSION_PREFIX = 'legal_provider_consent_';
    private static bool $infrastructureReady = false;

    public static function ensureInfrastructure(bool $force = false): void {
        if (self::$infrastructureReady && !$force) {
            return;
        }
        if (!self::tableExists(Constants::USERS_TABLE)) {
            self::$infrastructureReady = false;
            return;
        }

        if (!$force) {
            self::$infrastructureReady = self::usersTableHasConsentColumns();
            if (!self::$infrastructureReady) {
                throw new \RuntimeException('Legal consent infrastructure is not installed. Run install/upgrade first.');
            }
            return;
        }

        $table = Constants::USERS_TABLE;
        SafeMySQL::gi()->query('ALTER TABLE ?n ADD COLUMN IF NOT EXISTS privacy_policy_accepted TINYINT(1) NOT NULL DEFAULT 0 AFTER subscribed', $table);
        SafeMySQL::gi()->query('ALTER TABLE ?n ADD COLUMN IF NOT EXISTS privacy_policy_accepted_at DATETIME NULL AFTER privacy_policy_accepted', $table);
        SafeMySQL::gi()->query('ALTER TABLE ?n ADD COLUMN IF NOT EXISTS privacy_policy_accept_ip VARCHAR(45) DEFAULT NULL AFTER privacy_policy_accepted_at', $table);
        SafeMySQL::gi()->query('ALTER TABLE ?n ADD COLUMN IF NOT EXISTS privacy_policy_accept_user_agent VARCHAR(255) DEFAULT NULL AFTER privacy_policy_accept_ip', $table);
        SafeMySQL::gi()->query('ALTER TABLE ?n ADD COLUMN IF NOT EXISTS privacy_policy_version VARCHAR(50) DEFAULT NULL AFTER privacy_policy_accept_user_agent', $table);
        SafeMySQL::gi()->query('ALTER TABLE ?n ADD COLUMN IF NOT EXISTS personal_data_consent_accepted TINYINT(1) NOT NULL DEFAULT 0 AFTER privacy_policy_version', $table);
        SafeMySQL::gi()->query('ALTER TABLE ?n ADD COLUMN IF NOT EXISTS personal_data_consent_accepted_at DATETIME NULL AFTER personal_data_consent_accepted', $table);
        SafeMySQL::gi()->query('ALTER TABLE ?n ADD COLUMN IF NOT EXISTS personal_data_consent_accept_ip VARCHAR(45) DEFAULT NULL AFTER personal_data_consent_accepted_at', $table);
        SafeMySQL::gi()->query('ALTER TABLE ?n ADD COLUMN IF NOT EXISTS personal_data_consent_accept_user_agent VARCHAR(255) DEFAULT NULL AFTER personal_data_consent_accept_ip', $table);
        SafeMySQL::gi()->query('ALTER TABLE ?n ADD COLUMN IF NOT EXISTS personal_data_consent_version VARCHAR(50) DEFAULT NULL AFTER personal_data_consent_accept_user_agent', $table);

        self::$infrastructureReady = true;
    }

    public static function getSubmittedFlags(array $input): array {
        return [
            'privacy_policy_accepted' => !empty($input['privacy_policy_accepted']) ? 1 : 0,
            'personal_data_consent_accepted' => !empty($input['personal_data_consent_accepted']) ? 1 : 0,
        ];
    }

    public static function getDefaultState(): array {
        return [
            'privacy_policy_accepted' => 0,
            'privacy_policy_accepted_at' => null,
            'privacy_policy_accept_ip' => null,
            'privacy_policy_accept_user_agent' => null,
            'privacy_policy_version' => null,
            'personal_data_consent_accepted' => 0,
            'personal_data_consent_accepted_at' => null,
            'personal_data_consent_accept_ip' => null,
            'personal_data_consent_accept_user_agent' => null,
            'personal_data_consent_version' => null,
        ];
    }

    public static function normalizeState(array $row): array {
        $state = array_merge(self::getDefaultState(), SafeMySQL::gi()->filterArray($row, array_keys(self::getDefaultState())));
        $state['privacy_policy_accepted'] = !empty($state['privacy_policy_accepted']) ? 1 : 0;
        $state['personal_data_consent_accepted'] = !empty($state['personal_data_consent_accepted']) ? 1 : 0;
        return $state;
    }

    public static function hasRequiredConsents(array $userData): bool {
        $state = self::normalizeState($userData);
        return $state['privacy_policy_accepted'] === 1 && $state['personal_data_consent_accepted'] === 1;
    }

    public static function buildStoragePayload(array $input, array $existingState = [], string $source = 'web'): array {
        $flags = self::getSubmittedFlags($input);
        $existingState = self::normalizeState($existingState);
        $ip = SysClass::getClientIp();
        $userAgent = self::getCurrentUserAgent();
        $now = date('Y-m-d H:i:s');
        $payload = [];

        foreach (self::getConsentMap() as $acceptedField => $meta) {
            $accepted = !empty($flags[$acceptedField]) ? 1 : 0;
            $payload[$acceptedField] = $accepted;
            if ($accepted === 1) {
                $payload[$meta['accepted_at']] = $existingState[$acceptedField] ? ($existingState[$meta['accepted_at']] ?: $now) : $now;
                $payload[$meta['accept_ip']] = $existingState[$acceptedField] ? ($existingState[$meta['accept_ip']] ?: $ip) : $ip;
                $payload[$meta['user_agent']] = $existingState[$acceptedField] ? ($existingState[$meta['user_agent']] ?: $userAgent) : $userAgent;
                $payload[$meta['version']] = $existingState[$acceptedField] ? ($existingState[$meta['version']] ?: self::getDocumentVersion($acceptedField)) : self::getDocumentVersion($acceptedField);
                continue;
            }

            $payload[$meta['accepted_at']] = null;
            $payload[$meta['accept_ip']] = null;
            $payload[$meta['user_agent']] = null;
            $payload[$meta['version']] = null;
        }

        return $payload;
    }

    public static function getMissingRequiredKeys(array $input): array {
        $flags = self::getSubmittedFlags($input);
        $missing = [];
        if (empty($flags['privacy_policy_accepted'])) {
            $missing[] = 'privacy_policy_accepted';
        }
        if (empty($flags['personal_data_consent_accepted'])) {
            $missing[] = 'personal_data_consent_accepted';
        }
        return $missing;
    }

    public static function storeProviderConsent(string $provider, array $input): void {
        $provider = self::normalizeProvider($provider);
        if ($provider === '') {
            return;
        }
        Session::set(self::getProviderSessionKey($provider), self::buildStoragePayload($input, [], 'oauth_' . $provider));
    }

    public static function getProviderConsent(string $provider): array {
        $provider = self::normalizeProvider($provider);
        if ($provider === '') {
            return [];
        }
        $stored = Session::get(self::getProviderSessionKey($provider));
        return is_array($stored) ? $stored : [];
    }

    public static function hasProviderConsent(string $provider): bool {
        $stored = self::getProviderConsent($provider);
        return self::hasRequiredConsents($stored);
    }

    public static function clearProviderConsent(string $provider): void {
        $provider = self::normalizeProvider($provider);
        if ($provider === '') {
            return;
        }
        Session::un_set(self::getProviderSessionKey($provider));
    }

    public static function getConsentStateByUserId(int $userId): array {
        self::ensureInfrastructure();
        if ($userId <= 0) {
            return self::getDefaultState();
        }
        $row = SafeMySQL::gi()->getRow(
            'SELECT * FROM ?n WHERE user_id = ?i LIMIT 1',
            Constants::USERS_TABLE,
            $userId
        );
        return is_array($row) ? self::normalizeState($row) : self::getDefaultState();
    }

    public static function updateUserConsents(int $userId, array $input, string $source = 'web'): bool {
        self::ensureInfrastructure();
        if ($userId <= 0) {
            return false;
        }
        $existingState = self::getConsentStateByUserId($userId);
        $payload = self::buildStoragePayload($input, $existingState, $source);
        SafeMySQL::gi()->query(
            'UPDATE ?n SET ?u, updated_at = NOW() WHERE user_id = ?i',
            Constants::USERS_TABLE,
            $payload,
            $userId
        );
        Logger::audit('users_info', 'Обновлены обязательные согласия пользователя', [
            'user_id' => $userId,
            'source' => $source,
            'privacy_policy_accepted' => $payload['privacy_policy_accepted'],
            'personal_data_consent_accepted' => $payload['personal_data_consent_accepted'],
        ], [
            'initiator' => 'legal_consents',
            'details' => 'Legal consents updated',
            'include_trace' => false,
        ]);
        return true;
    }

    public static function sanitizeReturnPath(?string $path, string $default = '/admin'): string {
        $path = trim((string) $path);
        if ($path === '') {
            return $default;
        }

        if (preg_match('/^[a-z]+:\/\//i', $path)) {
            return $default;
        }

        $path = '/' . ltrim((string) parse_url($path, PHP_URL_PATH), '/');
        if ($path === '/' || $path === '') {
            return $default;
        }

        return $path;
    }

    public static function getDocumentVersion(string $acceptedField): string {
        return match ($acceptedField) {
            'privacy_policy_accepted' => defined('ENV_LEGAL_PRIVACY_POLICY_VERSION') ? (string) ENV_LEGAL_PRIVACY_POLICY_VERSION : date('Y-m-d'),
            'personal_data_consent_accepted' => defined('ENV_LEGAL_PERSONAL_DATA_CONSENT_VERSION') ? (string) ENV_LEGAL_PERSONAL_DATA_CONSENT_VERSION : date('Y-m-d'),
            default => date('Y-m-d'),
        };
    }

    private static function tableExists(string $table): bool {
        return (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s',
            $table
        ) > 0;
    }

    private static function usersTableHasConsentColumns(): bool {
        $requiredColumns = [
            'privacy_policy_accepted',
            'privacy_policy_accepted_at',
            'privacy_policy_accept_ip',
            'privacy_policy_accept_user_agent',
            'privacy_policy_version',
            'personal_data_consent_accepted',
            'personal_data_consent_accepted_at',
            'personal_data_consent_accept_ip',
            'personal_data_consent_accept_user_agent',
            'personal_data_consent_version',
        ];

        $count = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s AND COLUMN_NAME IN (?a)',
            Constants::USERS_TABLE,
            $requiredColumns
        );

        return $count === count($requiredColumns);
    }

    private static function getCurrentUserAgent(): ?string {
        $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($userAgent === '') {
            return null;
        }
        return mb_substr($userAgent, 0, 255);
    }

    private static function getConsentMap(): array {
        return [
            'privacy_policy_accepted' => [
                'accepted_at' => 'privacy_policy_accepted_at',
                'accept_ip' => 'privacy_policy_accept_ip',
                'user_agent' => 'privacy_policy_accept_user_agent',
                'version' => 'privacy_policy_version',
            ],
            'personal_data_consent_accepted' => [
                'accepted_at' => 'personal_data_consent_accepted_at',
                'accept_ip' => 'personal_data_consent_accept_ip',
                'user_agent' => 'personal_data_consent_accept_user_agent',
                'version' => 'personal_data_consent_version',
            ],
        ];
    }

    private static function normalizeProvider(string $provider): string {
        return strtolower(trim($provider));
    }

    private static function getProviderSessionKey(string $provider): string {
        return self::PROVIDER_SESSION_PREFIX . $provider;
    }
}
