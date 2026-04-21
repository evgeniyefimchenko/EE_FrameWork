<?php

namespace classes\system;

use classes\helpers\ClassMail;
use classes\helpers\ClassMessages;
use classes\plugins\SafeMySQL;

class AuthService {

    private const MAX_FAILED_PASSWORD_ATTEMPTS = 5;
    private const FAILED_PASSWORD_LOCK_MINUTES = 15;

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
            self::$infrastructureReady = self::hasRequiredInfrastructure();
            if (!self::$infrastructureReady) {
                throw new \RuntimeException('Authentication infrastructure is not installed. Run install/upgrade first.');
            }
            self::ensureAuthEmailTemplates();
            LegalConsentService::ensureInfrastructure(false);
            return;
        }

        self::createAuthSessionsTable();
        self::createAuthCredentialsTable();
        self::createAuthIdentitiesTable();
        self::createAuthChallengesTable();
        self::ensureAuthEmailTemplates();
        LegalConsentService::ensureInfrastructure($force);

        self::$infrastructureReady = true;
    }

    public static function resetInfrastructureState(): void {
        self::$infrastructureReady = false;
    }

    public static function decodeJsonPayload(mixed $payload): array {
        if (is_array($payload)) {
            return $payload;
        }
        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function loginWithPassword(string $email, string $password, bool $forceLogin = false): array {
        self::ensureInfrastructure();

        $email = trim($email);
        $user = $this->getUserByEmail($email, true);
        if (!$user) {
            return ['status' => 'user_not_found'];
        }
        if ((int) ($user['deleted'] ?? 0) === 1) {
            return ['status' => 'deleted'];
        }
        if ((int) ($user['active'] ?? 0) === 3) {
            return ['status' => 'blocked'];
        }
        if (!$forceLogin && (int) ($user['active'] ?? 0) === 1) {
            return ['status' => 'pending_activation'];
        }

        if ($forceLogin) {
            AuthSessionService::establishSession((int) $user['user_id']);
            return ['status' => 'success', 'user_id' => (int) $user['user_id']];
        }

        $credential = $this->getPasswordCredential((int) $user['user_id']);
        $mustSetPassword = !empty($credential['must_set_password']) || trim((string) ($credential['password_hash'] ?? '')) === '';
        if ($mustSetPassword) {
            $registrationState = $this->getPublicRegistrationStateByUserRow($user, $credential);
            if (($registrationState['status'] ?? '') === 'imported_pending_claim') {
                return [
                    'status' => 'imported_user_registration_required',
                    'user_id' => (int) $user['user_id'],
                    'email' => $email,
                ];
            }
            $challenge = AuthChallengeService::createChallenge(
                (int) $user['user_id'],
                'password_setup',
                ['reason' => 'migration', 'email' => $email]
            );
            $mailSent = $this->sendChallengeEmail((int) $user['user_id'], 'password_setup', $challenge['token']);
            $this->touchPasswordSetupPrompt((int) $user['user_id'], 'migration');
            return [
                'status' => $mailSent ? 'password_setup_required' : 'password_setup_mail_failed',
                'user_id' => (int) $user['user_id'],
                'challenge_token' => $challenge['token'],
            ];
        }

        if ($this->isPasswordCredentialLocked($credential)) {
            return ['status' => 'temporarily_locked'];
        }

        if (!password_verify($password, (string) $credential['password_hash'])) {
            return ['status' => $this->registerFailedPasswordAttempt($credential) ? 'temporarily_locked' : 'invalid_credentials'];
        }

        $this->clearPasswordCredentialFailures($credential);
        AuthSessionService::establishSession((int) $user['user_id']);
        return ['status' => 'success', 'user_id' => (int) $user['user_id']];
    }

    public function getPublicRegistrationStateByEmail(string $email): array {
        self::ensureInfrastructure();

        $email = trim($email);
        if ($email === '') {
            return ['status' => 'invalid_email'];
        }

        $user = $this->getUserByEmail($email, true);
        if (!$user) {
            return ['status' => 'available'];
        }

        $credential = $this->getPasswordCredential((int) ($user['user_id'] ?? 0));
        return $this->getPublicRegistrationStateByUserRow($user, $credential);
    }

    public function completeImportedUserRegistration(string $email, string $password, array $profile = []): array {
        self::ensureInfrastructure();

        $email = trim($email);
        if ($email === '') {
            return ['status' => 'invalid_email'];
        }
        if (LegalConsentService::getMissingRequiredKeys($profile) !== []) {
            return ['status' => 'consent_required'];
        }

        $user = $this->getUserByEmail($email, true);
        if (!$user) {
            return ['status' => 'user_not_found'];
        }

        $credential = $this->getPasswordCredential((int) ($user['user_id'] ?? 0));
        $state = $this->getPublicRegistrationStateByUserRow($user, $credential);
        if (($state['status'] ?? '') !== 'imported_pending_claim') {
            return ['status' => (string) ($state['status'] ?? 'email_taken'), 'user_id' => (int) ($user['user_id'] ?? 0)];
        }

        $userId = (int) ($user['user_id'] ?? 0);
        if ($userId <= 0) {
            return ['status' => 'user_not_found'];
        }

        $storagePayload = LegalConsentService::buildStoragePayload($profile, [], 'imported_user_claim');
        SafeMySQL::gi()->query(
            'UPDATE ?n SET active = 2, deleted = 0, subscribed = ?i, ?u, updated_at = NOW() WHERE user_id = ?i',
            Constants::USERS_TABLE,
            isset($profile['subscribed']) ? (int) (bool) $profile['subscribed'] : (int) ($user['subscribed'] ?? 0),
            $storagePayload,
            $userId
        );

        $this->setPasswordForUser($userId, $password);
        $this->markUserRequiresPasswordSetup($userId, false, '');
        AuthChallengeService::invalidateUserChallenges($userId, ['password_setup', 'activation', 'recovery']);
        AuthSessionService::establishSession($userId);

        return ['status' => 'imported_user_claimed', 'user_id' => $userId];
    }

    public function registerLocalUser(string $email, string $password, array $profile = []): array {
        self::ensureInfrastructure();

        $email = trim($email);
        if ($email === '') {
            return ['status' => 'invalid_email'];
        }
        if (LegalConsentService::getMissingRequiredKeys($profile) !== []) {
            return ['status' => 'consent_required'];
        }
        if ($this->getUserByEmail($email, true)) {
            return ['status' => 'email_taken'];
        }

        $userId = $this->createUserRecord([
            'name' => trim((string) ($profile['name'] ?? $email)),
            'email' => $email,
            'comment' => trim((string) ($profile['comment'] ?? '')),
            'phone' => trim((string) ($profile['phone'] ?? '')),
            'subscribed' => isset($profile['subscribed']) ? (int) (bool) $profile['subscribed'] : 0,
            'user_role' => isset($profile['user_role']) ? (int) $profile['user_role'] : Constants::USER,
            'active' => defined('ENV_CONFIRM_EMAIL') && (int) ENV_CONFIRM_EMAIL === 1 ? 1 : 2,
            'privacy_policy_accepted' => !empty($profile['privacy_policy_accepted']) ? 1 : 0,
            'personal_data_consent_accepted' => !empty($profile['personal_data_consent_accepted']) ? 1 : 0,
        ], $password);

        $this->createOrUpdatePasswordCredential($userId, password_hash($password, PASSWORD_DEFAULT), false);
        $this->sendProfileMessage($userId);

        if (defined('ENV_CONFIRM_EMAIL') && (int) ENV_CONFIRM_EMAIL === 1) {
            $challenge = AuthChallengeService::createChallenge($userId, 'activation', ['email' => $email], (int) ENV_TIME_ACTIVATION);
            $mailSent = $this->sendChallengeEmail($userId, 'activation', $challenge['token']);
            return [
                'status' => $mailSent ? 'registered_pending_activation' : 'registered_activation_mail_failed',
                'user_id' => $userId,
            ];
        }

        AuthSessionService::establishSession($userId);
        return ['status' => 'registered_active', 'user_id' => $userId];
    }

    public function requestPasswordRecovery(string $email): array {
        self::ensureInfrastructure();

        $user = $this->getUserByEmail(trim($email), false);
        if (!$user || (int) ($user['active'] ?? 0) !== 2) {
            return ['status' => 'recovery_requested'];
        }

        $challenge = AuthChallengeService::createChallenge(
            (int) $user['user_id'],
            'recovery',
            ['email' => $user['email']]
        );
        $mailSent = $this->sendChallengeEmail((int) $user['user_id'], 'recovery', $challenge['token']);

        return [
            'status' => $mailSent ? 'recovery_requested' : 'recovery_mail_failed',
            'challenge_token' => $challenge['token'],
            'user_id' => (int) $user['user_id'],
        ];
    }

    public function confirmPasswordRecovery(string $token, string $newPassword, bool $autoLogin = true): array {
        self::ensureInfrastructure();

        $challenge = AuthChallengeService::consumeChallenge('recovery', $token);
        if (!$challenge) {
            return ['status' => 'challenge_invalid'];
        }

        $userId = (int) ($challenge['user_id'] ?? 0);
        if ($userId <= 0) {
            return ['status' => 'challenge_invalid'];
        }

        $this->setPasswordForUser($userId, $newPassword);
        $this->markUserRequiresPasswordSetup($userId, false, '');
        if ($autoLogin) {
            AuthSessionService::establishSession($userId);
        }

        return ['status' => 'password_recovery_completed', 'user_id' => $userId];
    }

    public function requestPasswordSetup(string $email, string $reason = 'password_setup'): array {
        $user = $this->getUserByEmail(trim($email), false);
        if (!$user) {
            return ['status' => 'password_setup_requested'];
        }

        return $this->requestPasswordSetupForUserId((int) $user['user_id'], $reason);
    }

    public function requestPasswordSetupForUserId(int $userId, string $reason = 'password_setup'): array {
        self::ensureInfrastructure();

        if ($userId <= 0) {
            return ['status' => 'password_setup_requested'];
        }

        $user = $this->getUserById($userId);
        if (!$user || (int) ($user['deleted'] ?? 0) === 1) {
            return ['status' => 'password_setup_requested'];
        }

        $challenge = AuthChallengeService::createChallenge(
            $userId,
            'password_setup',
            ['reason' => $reason, 'email' => $user['email']]
        );
        $mailSent = $this->sendChallengeEmail($userId, 'password_setup', $challenge['token']);
        $this->touchPasswordSetupPrompt($userId, $reason);

        return [
            'status' => $mailSent ? 'password_setup_requested' : 'password_setup_mail_failed',
            'challenge_token' => $challenge['token'],
            'user_id' => $userId,
        ];
    }

    public function confirmPasswordSetup(string $token, string $newPassword, bool $autoLogin = true): array {
        self::ensureInfrastructure();

        $challenge = AuthChallengeService::consumeChallenge('password_setup', $token);
        if (!$challenge) {
            return ['status' => 'challenge_invalid'];
        }

        $userId = (int) ($challenge['user_id'] ?? 0);
        if ($userId <= 0) {
            return ['status' => 'challenge_invalid'];
        }

        $this->setPasswordForUser($userId, $newPassword);
        $this->markUserRequiresPasswordSetup($userId, false, '');
        if ($autoLogin) {
            AuthSessionService::establishSession($userId);
        }

        return ['status' => 'password_setup_completed', 'user_id' => $userId];
    }

    public function activateByToken(string $token): array {
        self::ensureInfrastructure();

        $challenge = AuthChallengeService::consumeChallenge('activation', $token);
        if (!$challenge) {
            return ['status' => 'challenge_invalid'];
        }

        $userId = (int) ($challenge['user_id'] ?? 0);
        if ($userId <= 0) {
            return ['status' => 'challenge_invalid'];
        }

        SafeMySQL::gi()->query(
            'UPDATE ?n SET active = 2 WHERE user_id = ?i AND deleted = 0',
            Constants::USERS_TABLE,
            $userId
        );

        return ['status' => SafeMySQL::gi()->affectedRows() > 0 ? 'activation_completed' : 'activation_not_modified', 'user_id' => $userId];
    }

    public function markUserRequiresPasswordSetup(int $userId, bool $required, string $reason = 'migration'): bool {
        self::ensureInfrastructure();

        $credential = $this->getPasswordCredential($userId);
        $payload = [
            'must_set_password' => $required ? 1 : 0,
            'password_set_at' => $required ? null : date('Y-m-d H:i:s'),
        ];
        if ($credential) {
            SafeMySQL::gi()->query(
                'UPDATE ?n SET ?u WHERE credential_id = ?i',
                Constants::USERS_AUTH_CREDENTIALS_TABLE,
                $payload,
                (int) $credential['credential_id']
            );
        } else {
            $this->createOrUpdatePasswordCredential($userId, null, $required);
        }

        $this->updateUserAuthOptions($userId, [
            'require_password_setup' => $required ? 1 : 0,
            'password_setup_reason' => $required ? $reason : '',
            'last_password_prompt_at' => $required ? ($this->getUserOptions($userId)['auth']['last_password_prompt_at'] ?? null) : null,
        ]);

        return true;
    }

    public function handleEmailChange(int $userId, string $oldEmail, string $newEmail): void {
        self::ensureInfrastructure();

        if ($userId <= 0 || $oldEmail === $newEmail) {
            return;
        }

        AuthChallengeService::invalidateUserChallenges($userId, ['activation', 'recovery', 'password_setup', 'account_linking']);
        SafeMySQL::gi()->query(
            'UPDATE ?n SET provider_email = ?s WHERE user_id = ?i AND provider_email = ?s',
            Constants::USERS_AUTH_IDENTITIES_TABLE,
            $newEmail,
            $userId,
            $oldEmail
        );
    }

    public function handleSoftDelete(int $userId): bool {
        self::ensureInfrastructure();

        if ($userId <= 0) {
            return false;
        }

        SafeMySQL::gi()->query(
            'UPDATE ?n SET deleted = 1 WHERE user_id = ?i',
            Constants::USERS_TABLE,
            $userId
        );
        $deletedUpdated = SafeMySQL::gi()->affectedRows() > 0;
        AuthSessionService::revokeAllForUser($userId);
        AuthChallengeService::invalidateUserChallenges($userId);
        Logger::audit('users_info', 'Пользователь помечен удаленным', ['user_id' => $userId], [
            'initiator' => 'soft_delete_user',
            'details' => 'Пользователь помечен удаленным',
            'include_trace' => false,
        ]);

        return $deletedUpdated;
    }

    public function restoreUser(int $userId, bool $forcePasswordSetup = true, string $reason = 'restored_account'): bool {
        self::ensureInfrastructure();

        if ($userId <= 0) {
            return false;
        }

        SafeMySQL::gi()->query(
            'UPDATE ?n SET deleted = 0, session = NULL WHERE user_id = ?i',
            Constants::USERS_TABLE,
            $userId
        );
        $restoredUpdated = SafeMySQL::gi()->affectedRows() > 0;
        AuthSessionService::revokeAllForUser($userId);
        AuthChallengeService::invalidateUserChallenges($userId);
        if ($forcePasswordSetup) {
            $this->markUserRequiresPasswordSetup($userId, true, $reason);
        }
        Logger::audit('users_info', 'Пользователь восстановлен', [
            'user_id' => $userId,
            'force_password_setup' => $forcePasswordSetup,
        ], [
            'initiator' => 'restore_user',
            'details' => 'Пользователь восстановлен',
            'include_trace' => false,
        ]);

        return $restoredUpdated || $forcePasswordSetup;
    }

    public function getUserSecurityState(int $userId): array {
        self::ensureInfrastructure();

        $credential = $this->getPasswordCredential($userId);
        return [
            'must_set_password' => (int) ($credential['must_set_password'] ?? 0),
            'failed_attempts' => (int) ($credential['failed_attempts'] ?? 0),
            'locked_until' => $credential['locked_until'] ?? null,
            'has_local_password' => trim((string) ($credential['password_hash'] ?? '')) !== '',
            'linked_identities' => $this->getLinkedIdentities($userId),
        ];
    }

    public function getLinkedIdentities(int $userId): array {
        return AuthIdentityService::getIdentitiesForUser($userId);
    }

    public function startProviderAuth(string $provider): array {
        self::ensureInfrastructure();

        $providerService = $this->getProvider($provider);
        if (!$providerService || !$providerService->isConfigured()) {
            return ['status' => 'provider_not_configured'];
        }

        $challenge = AuthChallengeService::createChallenge(
            null,
            'oauth_state',
            ['provider' => strtolower(trim($provider)), 'transport' => AuthSessionService::getConfiguredTransport()],
            AuthChallengeService::getDefaultTtl('oauth_state')
        );

        return [
            'status' => 'redirect',
            'redirect_url' => $providerService->startAuth(['state' => $challenge['token']]),
        ];
    }

    public function handleProviderCallback(string $provider, array $queryParams, array $registrationContext = []): array {
        self::ensureInfrastructure();

        $providerService = $this->getProvider($provider);
        if (!$providerService || !$providerService->isConfigured()) {
            return ['status' => 'provider_not_configured'];
        }

        $stateToken = trim((string) ($queryParams['state'] ?? ''));
        $oauthState = AuthChallengeService::consumeChallenge('oauth_state', $stateToken);
        if (!$oauthState) {
            return ['status' => 'provider_state_invalid'];
        }

        try {
            $identity = $providerService->handleCallback($queryParams);
        } catch (\Throwable $e) {
            Logger::error('users_error', 'Ошибка провайдера авторизации', [
                'provider' => $provider,
                'message' => $e->getMessage(),
            ], [
                'initiator' => 'provider_callback',
                'details' => $e->getMessage(),
            ]);
            return ['status' => 'provider_error'];
        }

        $providerUserId = trim((string) ($identity['provider_user_id'] ?? ''));
        $email = trim((string) ($identity['provider_email'] ?? ''));
        if ($providerUserId === '' || $email === '') {
            return ['status' => 'provider_profile_incomplete'];
        }

        $linkedUser = AuthIdentityService::getUserByIdentity($provider, $providerUserId);
        if ($linkedUser) {
            if ((int) ($linkedUser['deleted'] ?? 0) === 1) {
                return ['status' => 'deleted'];
            }
            if ((int) ($linkedUser['active'] ?? 0) === 3) {
                return ['status' => 'blocked'];
            }
            AuthIdentityService::upsertIdentity((int) $linkedUser['user_id'], $provider, $identity);
            AuthSessionService::establishSession((int) $linkedUser['user_id']);
            return ['status' => 'success', 'user_id' => (int) $linkedUser['user_id']];
        }

        $existingUser = $this->getUserByEmail($email, true);
        if ($existingUser) {
            if ((int) ($existingUser['deleted'] ?? 0) === 1) {
                return ['status' => 'deleted'];
            }
            $challenge = AuthChallengeService::createChallenge(
                (int) $existingUser['user_id'],
                'account_linking',
                [
                    'provider' => strtolower(trim($provider)),
                    'identity' => $identity,
                ]
            );
            $mailSent = $this->sendChallengeEmail((int) $existingUser['user_id'], 'account_linking', $challenge['token'], strtolower(trim($provider)));
            return ['status' => $mailSent ? 'account_link_email_sent' : 'account_link_mail_failed'];
        }

        if (LegalConsentService::getMissingRequiredKeys($registrationContext) !== []) {
            return ['status' => 'consent_required'];
        }

        $userId = $this->createUserRecord([
            'name' => trim((string) ($identity['name'] ?? $email)),
            'email' => $email,
            'comment' => '',
            'user_role' => Constants::USER,
            'active' => !empty($identity['provider_email_verified']) ? 2 : 1,
            'subscribed' => 0,
            'privacy_policy_accepted' => !empty($registrationContext['privacy_policy_accepted']) ? 1 : 0,
            'personal_data_consent_accepted' => !empty($registrationContext['personal_data_consent_accepted']) ? 1 : 0,
        ], null);
        $this->createOrUpdatePasswordCredential($userId, null, false);
        AuthIdentityService::upsertIdentity($userId, $provider, $identity);
        $this->sendProfileMessage($userId);

        if (!empty($identity['provider_email_verified'])) {
            AuthSessionService::establishSession($userId);
            return ['status' => 'success', 'user_id' => $userId];
        }

        $challenge = AuthChallengeService::createChallenge($userId, 'activation', ['email' => $email], (int) ENV_TIME_ACTIVATION);
        $this->sendChallengeEmail($userId, 'activation', $challenge['token']);
        return ['status' => 'registered_pending_activation', 'user_id' => $userId];
    }

    public function confirmAccountLink(string $token, bool $autoLogin = true): array {
        self::ensureInfrastructure();

        $challenge = AuthChallengeService::consumeChallenge('account_linking', $token);
        if (!$challenge) {
            return ['status' => 'challenge_invalid'];
        }

        $userId = (int) ($challenge['user_id'] ?? 0);
        $payload = is_array($challenge['payload_json'] ?? null) ? $challenge['payload_json'] : self::decodeJsonPayload($challenge['payload_json'] ?? '{}');
        $provider = (string) ($payload['provider'] ?? '');
        $identity = is_array($payload['identity'] ?? null) ? $payload['identity'] : [];
        if ($userId <= 0 || $provider === '' || !$identity) {
            return ['status' => 'challenge_invalid'];
        }

        AuthIdentityService::upsertIdentity($userId, $provider, $identity);
        if ($autoLogin) {
            AuthSessionService::establishSession($userId);
        }

        return ['status' => 'account_link_completed', 'user_id' => $userId];
    }

    public function setPasswordForUser(int $userId, string $password): string {
        self::ensureInfrastructure();

        $password = trim($password);
        if ($userId <= 0 || $password === '') {
            throw new \InvalidArgumentException('User ID and password are required');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($passwordHash) || $passwordHash === '') {
            throw new \RuntimeException('Failed to hash password');
        }

        $this->createOrUpdatePasswordCredential($userId, $passwordHash, false);
        SafeMySQL::gi()->query(
            'UPDATE ?n SET pwd = ?s, updated_at = NOW() WHERE user_id = ?i',
            Constants::USERS_TABLE,
            $passwordHash,
            $userId
        );

        return $password;
    }

    private function getUserByEmail(string $email, bool $includeDeleted = false): ?array {
        $email = trim($email);
        if ($email === '') {
            return null;
        }

        $sql = 'SELECT * FROM ?n WHERE email = ?s';
        if (!$includeDeleted) {
            $sql .= ' AND deleted = 0';
        }
        $sql .= ' LIMIT 1';

        $row = SafeMySQL::gi()->getRow($sql, Constants::USERS_TABLE, $email);
        return is_array($row) ? $row : null;
    }

    private function getUserById(int $userId): ?array {
        if ($userId <= 0) {
            return null;
        }

        $row = SafeMySQL::gi()->getRow('SELECT * FROM ?n WHERE user_id = ?i LIMIT 1', Constants::USERS_TABLE, $userId);
        return is_array($row) ? $row : null;
    }

    private function getPasswordCredential(int $userId): array {
        self::ensureInfrastructure();

        $row = SafeMySQL::gi()->getRow(
            'SELECT * FROM ?n WHERE user_id = ?i AND credential_type = ?s LIMIT 1',
            Constants::USERS_AUTH_CREDENTIALS_TABLE,
            $userId,
            'password'
        );
        if (is_array($row)) {
            return $row;
        }

        $user = $this->getUserById($userId);
        if (!$user) {
            return [];
        }

        $options = $this->getUserOptions($userId);
        $mustSetPassword = !empty($options['auth']['require_password_setup']) ? 1 : 0;
        $passwordHash = trim((string) ($user['pwd'] ?? ''));
        $this->createOrUpdatePasswordCredential($userId, $passwordHash !== '' ? $passwordHash : null, (bool) $mustSetPassword);

        $row = SafeMySQL::gi()->getRow(
            'SELECT * FROM ?n WHERE user_id = ?i AND credential_type = ?s LIMIT 1',
            Constants::USERS_AUTH_CREDENTIALS_TABLE,
            $userId,
            'password'
        );

        return is_array($row) ? $row : [];
    }

    private function isPasswordCredentialLocked(array $credential): bool {
        $lockedUntil = trim((string) ($credential['locked_until'] ?? ''));
        if ($lockedUntil === '') {
            return false;
        }

        $lockedTimestamp = strtotime($lockedUntil);
        return $lockedTimestamp !== false && $lockedTimestamp > time();
    }

    private function registerFailedPasswordAttempt(array $credential): bool {
        $credentialId = (int) ($credential['credential_id'] ?? 0);
        if ($credentialId <= 0) {
            return false;
        }

        $attempts = max(0, (int) ($credential['failed_attempts'] ?? 0)) + 1;
        $payload = ['failed_attempts' => $attempts];
        $isLocked = $attempts >= self::MAX_FAILED_PASSWORD_ATTEMPTS;
        if ($isLocked) {
            $payload['locked_until'] = date('Y-m-d H:i:s', time() + (self::FAILED_PASSWORD_LOCK_MINUTES * 60));
        }

        SafeMySQL::gi()->query(
            'UPDATE ?n SET ?u WHERE credential_id = ?i',
            Constants::USERS_AUTH_CREDENTIALS_TABLE,
            $payload,
            $credentialId
        );

        return $isLocked;
    }

    private function clearPasswordCredentialFailures(array $credential): void {
        $credentialId = (int) ($credential['credential_id'] ?? 0);
        if ($credentialId <= 0) {
            return;
        }

        if ((int) ($credential['failed_attempts'] ?? 0) === 0 && trim((string) ($credential['locked_until'] ?? '')) === '') {
            return;
        }

        SafeMySQL::gi()->query(
            'UPDATE ?n SET failed_attempts = 0, locked_until = NULL WHERE credential_id = ?i',
            Constants::USERS_AUTH_CREDENTIALS_TABLE,
            $credentialId
        );
    }

    private function createOrUpdatePasswordCredential(int $userId, ?string $passwordHash, bool $mustSetPassword): void {
        if ($userId <= 0) {
            return;
        }

        $existing = SafeMySQL::gi()->getRow(
            'SELECT credential_id FROM ?n WHERE user_id = ?i AND credential_type = ?s LIMIT 1',
            Constants::USERS_AUTH_CREDENTIALS_TABLE,
            $userId,
            'password'
        );

        $payload = [
            'credential_type' => 'password',
            'password_hash' => $passwordHash,
            'must_set_password' => $mustSetPassword ? 1 : 0,
            'password_set_at' => ($passwordHash !== null && !$mustSetPassword) ? date('Y-m-d H:i:s') : null,
            'failed_attempts' => 0,
            'locked_until' => null,
        ];

        if ($existing) {
            SafeMySQL::gi()->query(
                'UPDATE ?n SET ?u WHERE credential_id = ?i',
                Constants::USERS_AUTH_CREDENTIALS_TABLE,
                $payload,
                (int) $existing['credential_id']
            );
            return;
        }

        $payload['user_id'] = $userId;
        SafeMySQL::gi()->query('INSERT INTO ?n SET ?u', Constants::USERS_AUTH_CREDENTIALS_TABLE, $payload);
    }

    private function createUserRecord(array $userData, ?string $password = null): int {
        $passwordHash = $password !== null && $password !== ''
            ? password_hash($password, PASSWORD_DEFAULT)
            : password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);
        if (!is_string($passwordHash) || $passwordHash === '') {
            throw new \RuntimeException('Failed to prepare placeholder password hash');
        }

        $payload = [
            'name' => trim((string) ($userData['name'] ?? 'no name')) ?: 'no name',
            'email' => trim((string) ($userData['email'] ?? '')),
            'pwd' => $passwordHash,
            'active' => isset($userData['active']) ? (int) $userData['active'] : 1,
            'user_role' => isset($userData['user_role']) ? (int) $userData['user_role'] : Constants::USER,
            'last_ip' => SysClass::getClientIp(),
            'subscribed' => isset($userData['subscribed']) ? (int) (bool) $userData['subscribed'] : 0,
            'phone' => trim((string) ($userData['phone'] ?? '')) ?: null,
            'comment' => trim((string) ($userData['comment'] ?? '')),
        ];
        $payload = array_merge($payload, LegalConsentService::buildStoragePayload($userData, [], 'auth_service_create_user'));

        SafeMySQL::gi()->query('INSERT INTO ?n SET ?u', Constants::USERS_TABLE, $payload);
        $userId = (int) SafeMySQL::gi()->insertId();

        $defaultOptions = self::buildDefaultUserOptions();
        SafeMySQL::gi()->query(
            'INSERT INTO ?n SET user_id = ?i, options = ?s',
            Constants::USERS_DATA_TABLE,
            $userId,
            json_encode($defaultOptions, JSON_UNESCAPED_UNICODE)
        );

        return $userId;
    }

    private function getProvider(string $provider): ?AuthProviderInterface {
        return match (strtolower(trim($provider))) {
            'google' => new GoogleAuthProvider(),
            default => null,
        };
    }

    private function sendChallengeEmail(int $userId, string $purpose, string $token, ?string $provider = null): bool {
        $user = $this->getUserById($userId);
        if (!$user) {
            return false;
        }

        $baseUrl = rtrim((string) ENV_URL_SITE, '/');
        return match ($purpose) {
            'activation' => ClassMail::sendMail(
                $user['email'],
                '',
                'activation_code',
                ['activation_link' => '<a href="' . htmlspecialchars($baseUrl . '/activation/' . $token, ENT_QUOTES) . '">Нажми меня</a>']
            ),
            'recovery' => ClassMail::sendMail(
                $user['email'],
                '',
                'password_recovery_link',
                ['reset_link' => '<a href="' . htmlspecialchars($baseUrl . '/recovery_password/confirm/' . $token, ENT_QUOTES) . '">Нажми меня</a>']
            ),
            'password_setup' => ClassMail::sendMail(
                $user['email'],
                '',
                'password_setup',
                ['setup_link' => '<a href="' . htmlspecialchars($baseUrl . '/password_setup/confirm/' . $token, ENT_QUOTES) . '">Нажми меня</a>']
            ),
            'account_linking' => ClassMail::sendMail(
                $user['email'],
                '',
                'account_linking',
                [
                    'provider_name' => ucfirst((string) $provider),
                    'linking_link' => '<a href="' . htmlspecialchars($baseUrl . '/auth/link/' . $token, ENT_QUOTES) . '">Нажми меня</a>',
                ]
            ),
            default => false,
        };
    }

    private function sendProfileMessage(int $userId): void {
        $systemId = SafeMySQL::gi()->getOne(
            'SELECT user_id FROM ?n WHERE email = ?s LIMIT 1',
            Constants::USERS_TABLE,
            'dont-answer@' . ENV_SITE_NAME
        );
        if ($systemId) {
            ClassMessages::set_message_user(
                $userId,
                (int) $systemId,
                'Заполните свой профиль <a href="' . ENV_URL_SITE . '/admin/user_edit/id/' . $userId . '">тут</a>',
                'info'
            );
        }
    }

    private function getUserOptions(int $userId): array {
        $rawOptions = SafeMySQL::gi()->getOne('SELECT options FROM ?n WHERE user_id = ?i', Constants::USERS_DATA_TABLE, $userId);
        $options = self::decodeJsonPayload($rawOptions);
        return self::mergeOptionsRecursive(self::buildDefaultUserOptions(), $options);
    }

    private function getPublicRegistrationStateByUserRow(array $user, array $credential = []): array {
        $userId = (int) ($user['user_id'] ?? 0);
        if ($userId <= 0) {
            return ['status' => 'user_not_found'];
        }

        if ((int) ($user['deleted'] ?? 0) === 1) {
            return ['status' => 'deleted', 'user_id' => $userId];
        }

        if ((int) ($user['active'] ?? 0) === 3) {
            return ['status' => 'blocked', 'user_id' => $userId];
        }

        $options = $this->getUserOptions($userId);
        $reason = trim((string) ($options['auth']['password_setup_reason'] ?? ''));
        $mustSetPassword = !empty($credential['must_set_password']) || !empty($options['auth']['require_password_setup']);
        $isImportedWordpressUser = !empty($options['migration']['wordpress']['source_id']) || $reason === 'wp_migration';

        if ($isImportedWordpressUser && $mustSetPassword) {
            return [
                'status' => 'imported_pending_claim',
                'user_id' => $userId,
                'email' => (string) ($user['email'] ?? ''),
            ];
        }

        return ['status' => 'email_taken', 'user_id' => $userId];
    }

    private function touchPasswordSetupPrompt(int $userId, string $reason): void {
        $this->updateUserAuthOptions($userId, [
            'require_password_setup' => 1,
            'password_setup_reason' => $reason,
            'last_password_prompt_at' => date('c'),
        ]);
    }

    private function updateUserAuthOptions(int $userId, array $patch): void {
        if ($userId <= 0) {
            return;
        }

        $options = $this->getUserOptions($userId);
        $options['auth'] = self::mergeOptionsRecursive($options['auth'] ?? [], $patch);
        $encoded = json_encode($options, JSON_UNESCAPED_UNICODE);
        SafeMySQL::gi()->query(
            'UPDATE ?n SET options = ?s, updated_at = NOW() WHERE user_id = ?i',
            Constants::USERS_DATA_TABLE,
            $encoded === false ? json_encode(self::buildDefaultUserOptions(), JSON_UNESCAPED_UNICODE) : $encoded,
            $userId
        );
    }

    private static function buildDefaultUserOptions(): array {
        $base = self::decodeJsonPayload(Users::BASE_OPTIONS_USER);
        $base['auth'] = self::mergeOptionsRecursive(
            [
                'require_password_setup' => 0,
                'password_setup_reason' => '',
                'last_password_prompt_at' => null,
                'ip_restricted' => 0,
            ],
            is_array($base['auth'] ?? null) ? $base['auth'] : []
        );

        return $base;
    }

    private static function mergeOptionsRecursive(array $base, array $patch): array {
        foreach ($patch as $key => $value) {
            if (is_array($value) && is_array($base[$key] ?? null)) {
                $base[$key] = self::mergeOptionsRecursive($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private static function createAuthSessionsTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
            auth_session_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            transport VARCHAR(32) NOT NULL DEFAULT 'cookie',
            ip VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(1000) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            revoked_at DATETIME DEFAULT NULL,
            PRIMARY KEY (auth_session_id),
            UNIQUE KEY uq_user_auth_sessions_token_hash (token_hash),
            KEY idx_user_auth_sessions_user (user_id),
            KEY idx_user_auth_sessions_expiration (expires_at),
            KEY idx_user_auth_sessions_revoked (revoked_at),
            CONSTRAINT fk_user_auth_sessions_user FOREIGN KEY (user_id) REFERENCES ?n(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Авторизационные сессии пользователей';";
        SafeMySQL::gi()->query($sql, Constants::USERS_AUTH_SESSIONS_TABLE, Constants::USERS_TABLE);
    }

    private static function createAuthCredentialsTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
            credential_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            credential_type VARCHAR(32) NOT NULL DEFAULT 'password',
            password_hash VARCHAR(255) DEFAULT NULL,
            must_set_password TINYINT(1) NOT NULL DEFAULT 0,
            password_set_at DATETIME DEFAULT NULL,
            failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
            locked_until DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (credential_id),
            UNIQUE KEY uq_user_auth_credentials_user_type (user_id, credential_type),
            KEY idx_user_auth_credentials_lock (locked_until),
            CONSTRAINT fk_user_auth_credentials_user FOREIGN KEY (user_id) REFERENCES ?n(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Локальные учетные данные пользователей';";
        SafeMySQL::gi()->query($sql, Constants::USERS_AUTH_CREDENTIALS_TABLE, Constants::USERS_TABLE);
    }

    private static function createAuthIdentitiesTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
            identity_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            provider VARCHAR(64) NOT NULL,
            provider_user_id VARCHAR(255) NOT NULL,
            provider_email VARCHAR(255) DEFAULT NULL,
            provider_email_verified TINYINT(1) NOT NULL DEFAULT 0,
            linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_login_at DATETIME DEFAULT NULL,
            payload_json JSON DEFAULT NULL,
            PRIMARY KEY (identity_id),
            UNIQUE KEY uq_user_auth_identity_provider_user (provider, provider_user_id),
            KEY idx_user_auth_identity_user (user_id),
            KEY idx_user_auth_identity_email (provider_email),
            CONSTRAINT fk_user_auth_identity_user FOREIGN KEY (user_id) REFERENCES ?n(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Внешние identity-провайдеры пользователей';";
        SafeMySQL::gi()->query($sql, Constants::USERS_AUTH_IDENTITIES_TABLE, Constants::USERS_TABLE);
    }

    private static function createAuthChallengesTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS ?n (
            challenge_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED DEFAULT NULL,
            purpose VARCHAR(64) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            payload_json JSON DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            consumed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (challenge_id),
            UNIQUE KEY uq_user_auth_challenge_token_hash (token_hash),
            KEY idx_user_auth_challenge_user (user_id),
            KEY idx_user_auth_challenge_purpose (purpose),
            KEY idx_user_auth_challenge_expiration (expires_at),
            KEY idx_user_auth_challenge_consumed (consumed_at),
            CONSTRAINT fk_user_auth_challenge_user FOREIGN KEY (user_id) REFERENCES ?n(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Одноразовые challenge-токены авторизации';";
        SafeMySQL::gi()->query($sql, Constants::USERS_AUTH_CHALLENGES_TABLE, Constants::USERS_TABLE);
    }

    private static function ensureAuthEmailTemplates(): void {
        if (!self::tableExists(Constants::EMAIL_TEMPLATES_TABLE)) {
            return;
        }

        $templates = [
            'activation_code' => [
                'subject' => 'Ваша ссылка для активации на {{ENV_DOMEN_NAME}}',
                'body' => "{{SNIP_HEADER}}\n<div class='container'>\n    <h1>Ссылка для активации аккаунта</h1>\n    <p>Для завершения регистрации перейдите по ссылке:</p>\n    <p>[activation_link]</p>\n    <p>Если вы не совершали регистрацию, просто проигнорируйте это письмо.</p>\n</div>{{SNIP_FOOTER}}",
                'description' => 'Шаблон письма для активации аккаунта',
            ],
            'password_recovery_link' => [
                'subject' => 'Восстановление доступа на {{ENV_DOMEN_NAME}}',
                'body' => "{{SNIP_HEADER}}\n<div class='container'>\n    <h1>Восстановление доступа</h1>\n    <p>Чтобы задать новый пароль, перейдите по ссылке:</p>\n    <p>[reset_link]</p>\n    <p>Если вы не запрашивали восстановление, просто проигнорируйте письмо.</p>\n</div>{{SNIP_FOOTER}}",
                'description' => 'Шаблон ссылки для восстановления пароля',
            ],
            'password_setup' => [
                'subject' => 'Назначение пароля на {{ENV_DOMEN_NAME}}',
                'body' => "{{SNIP_HEADER}}\n<div class='container'>\n    <h1>Назначение пароля</h1>\n    <p>Чтобы назначить новый пароль для входа, перейдите по ссылке:</p>\n    <p>[setup_link]</p>\n</div>{{SNIP_FOOTER}}",
                'description' => 'Шаблон ссылки для назначения пароля',
            ],
            'account_linking' => [
                'subject' => 'Подтвердите привязку аккаунта {{ENV_DOMEN_NAME}}',
                'body' => "{{SNIP_HEADER}}\n<div class='container'>\n    <h1>Подтверждение привязки аккаунта</h1>\n    <p>Для завершения привязки провайдера [provider_name] перейдите по ссылке:</p>\n    <p>[linking_link]</p>\n</div>{{SNIP_FOOTER}}",
                'description' => 'Шаблон подтверждения привязки внешнего провайдера',
            ],
            'account_activated' => [
                'subject' => 'Ваш аккаунт активирован на {{ENV_DOMEN_NAME}}',
                'body' => "{{SNIP_HEADER}}\n<div class='container'>\n    <h1>Аккаунт успешно активирован</h1>\n    <p>Ваш аккаунт на сайте {{ENV_DOMEN_NAME}} уже активен и готов к работе.</p>\n</div>{{SNIP_FOOTER}}",
                'description' => 'Шаблон уведомления об успешной активации аккаунта',
            ],
        ];

        foreach ($templates as $name => $template) {
            $exists = SafeMySQL::gi()->getOne(
                'SELECT 1 FROM ?n WHERE name = ?s AND language_code = ?s LIMIT 1',
                Constants::EMAIL_TEMPLATES_TABLE,
                $name,
                'RU'
            );
            if ($exists) {
                continue;
            }

            SafeMySQL::gi()->query(
                'INSERT INTO ?n SET name = ?s, subject = ?s, body = ?s, description = ?s, language_code = ?s',
                Constants::EMAIL_TEMPLATES_TABLE,
                $name,
                $template['subject'],
                $template['body'],
                $template['description'],
                'RU'
            );
        }
    }

    private static function tableExists(string $table): bool {
        if ($table === '') {
            return false;
        }

        $result = SafeMySQL::gi()->query('SHOW TABLES LIKE ?s', $table);
        return $result instanceof \mysqli_result && $result->num_rows > 0;
    }

    private static function hasRequiredInfrastructure(): bool {
        foreach ([
            Constants::USERS_AUTH_SESSIONS_TABLE,
            Constants::USERS_AUTH_CREDENTIALS_TABLE,
            Constants::USERS_AUTH_IDENTITIES_TABLE,
            Constants::USERS_AUTH_CHALLENGES_TABLE,
        ] as $table) {
            if (!self::tableExists($table)) {
                return false;
            }
        }

        return true;
    }
}
