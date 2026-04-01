<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

class AuthSessionService {

    private const STORAGE_KEY = 'user_session';
    private const IMPERSONATION_ORIGIN_TOKEN_KEY = 'impersonation_origin_token';
    private const IMPERSONATION_ORIGIN_USER_ID_KEY = 'impersonation_origin_user_id';
    private const IMPERSONATION_ORIGIN_TRANSPORT_KEY = 'impersonation_origin_transport';

    public static function getOnlineUsersSnapshot(int $minutes = 15): array {
        AuthService::ensureInfrastructure();

        $minutes = max(1, $minutes);
        $rows = SafeMySQL::gi()->getAll(
            'SELECT
                u.user_id,
                u.name,
                u.email,
                u.user_role,
                COALESCE(r.name, "") AS role_name,
                MAX(s.last_seen_at) AS last_seen_at,
                COUNT(DISTINCT s.auth_session_id) AS session_count
             FROM ?n AS s
             INNER JOIN ?n AS u ON u.user_id = s.user_id
             LEFT JOIN ?n AS r ON r.role_id = u.user_role
             WHERE s.revoked_at IS NULL
               AND s.expires_at > NOW()
               AND s.last_seen_at >= (NOW() - INTERVAL ?i MINUTE)
               AND u.deleted = 0
             GROUP BY u.user_id, u.name, u.email, u.user_role, r.name
             ORDER BY FIELD(u.user_role, ?i, ?i, ?i), MAX(s.last_seen_at) DESC, u.user_id ASC',
            Constants::USERS_AUTH_SESSIONS_TABLE,
            Constants::USERS_TABLE,
            Constants::USERS_ROLES_TABLE,
            $minutes,
            Constants::ADMIN,
            Constants::MANAGER,
            Constants::USER
        );

        $groups = [
            'admins' => [],
            'managers' => [],
            'users' => [],
            'others' => [],
        ];

        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'user_role' => (int) ($row['user_role'] ?? 0),
                'role_name' => (string) ($row['role_name'] ?? ''),
                'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
                'session_count' => (int) ($row['session_count'] ?? 0),
            ];

            $groupKey = match ($item['user_role']) {
                Constants::ADMIN => 'admins',
                Constants::MANAGER => 'managers',
                Constants::USER => 'users',
                default => 'others',
            };
            $groups[$groupKey][] = $item;
        }

        return [
            'minutes' => $minutes,
            'generated_at' => gmdate('Y-m-d H:i:s'),
            'groups' => $groups,
        ];
    }

    public static function establishSession(int $userId, ?string $transport = null, ?string $rawToken = null): array {
        AuthService::ensureInfrastructure();

        if ($userId <= 0) {
            throw new \InvalidArgumentException('User ID is required for auth session');
        }

        $transport = self::normalizeTransport($transport ?: self::getConfiguredTransport());
        $rawToken = $rawToken ?: bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $ip = SysClass::getClientIp();
        $userAgent = self::getCurrentUserAgent();
        $expiresAt = date('Y-m-d H:i:s', time() + (int) ENV_TIME_AUTH_SESSION);

        self::revokeDuplicateSessionsForFingerprint($userId, $transport, $ip, $userAgent);

        $sql = 'INSERT INTO ?n SET user_id = ?i, token_hash = ?s, transport = ?s, ip = ?s, user_agent = ?s, expires_at = ?s';
        SafeMySQL::gi()->query(
            $sql,
            Constants::USERS_AUTH_SESSIONS_TABLE,
            $userId,
            $tokenHash,
            $transport,
            $ip,
            $userAgent,
            $expiresAt
        );

        self::persistTokenToTransport($transport, $rawToken);
        SafeMySQL::gi()->query(
            'UPDATE ?n SET session = ?s, last_ip = ?s, last_activ = NOW() WHERE user_id = ?i',
            Constants::USERS_TABLE,
            $rawToken,
            $ip,
            $userId
        );
        self::enforceActiveSessionLimit($userId);

        return [
            'transport' => $transport,
            'token' => $rawToken,
            'expires_at' => $expiresAt,
        ];
    }

    public static function resolveCurrentUserId(): int|bool {
        AuthService::ensureInfrastructure();

        $rawToken = self::readCurrentToken();
        if ($rawToken === null || $rawToken === '') {
            return false;
        }

        $session = self::findSessionByRawToken($rawToken);
        if (!$session) {
            $legacyUserId = self::migrateLegacySessionToken($rawToken);
            if ($legacyUserId) {
                return $legacyUserId;
            }
            self::clearTransportState();
            return false;
        }

        if ((int) ($session['deleted'] ?? 0) === 1) {
            self::revokeSessionByToken($rawToken);
            self::clearTransportState();
            return false;
        }

        if ((int) ($session['active'] ?? 0) !== 2 && !self::isImpersonationSessionAllowed((int) ($session['user_id'] ?? 0), $rawToken)) {
            self::revokeSessionByToken($rawToken);
            self::clearTransportState();
            return false;
        }

        $options = AuthService::decodeJsonPayload($session['options_json'] ?? '{}');
        if (self::shouldEnforceIpPolicy((int) ($session['user_role'] ?? 0), $options) && !self::isCurrentIpAllowed((string) ($session['ip'] ?? ''))) {
            self::revokeSessionByToken($rawToken);
            self::clearTransportState();
            return false;
        }

        SafeMySQL::gi()->query(
            'UPDATE ?n SET last_seen_at = NOW() WHERE auth_session_id = ?i',
            Constants::USERS_AUTH_SESSIONS_TABLE,
            (int) $session['auth_session_id']
        );
        SafeMySQL::gi()->query(
            'UPDATE ?n SET last_activ = NOW(), last_ip = ?s WHERE user_id = ?i',
            Constants::USERS_TABLE,
            SysClass::getClientIp(),
            (int) $session['user_id']
        );

        return (int) $session['user_id'];
    }

    public static function startImpersonation(int $targetUserId): array {
        AuthService::ensureInfrastructure();

        $targetUserId = (int) $targetUserId;
        if ($targetUserId <= 0) {
            throw new \InvalidArgumentException('Target user ID is required for impersonation');
        }

        $originToken = (string) (self::readCurrentToken() ?? '');
        $originSession = $originToken !== '' ? self::findSessionByRawToken($originToken) : null;
        if (!is_array($originSession) || (int) ($originSession['user_role'] ?? 0) !== Constants::ADMIN) {
            throw new \RuntimeException('Only an administrator can start impersonation');
        }

        $originUserId = (int) ($originSession['user_id'] ?? 0);
        if ($originUserId <= 0) {
            throw new \RuntimeException('Origin administrator session is invalid');
        }

        if ($originUserId === $targetUserId) {
            return [
                'origin_user_id' => $originUserId,
                'target_user_id' => $targetUserId,
                'status' => 'noop',
            ];
        }

        $targetUser = SafeMySQL::gi()->getRow(
            'SELECT user_id, deleted, active, user_role FROM ?n WHERE user_id = ?i LIMIT 1',
            Constants::USERS_TABLE,
            $targetUserId
        );
        if (!is_array($targetUser) || (int) ($targetUser['deleted'] ?? 0) === 1 || (int) ($targetUser['active'] ?? 0) !== 2) {
            throw new \RuntimeException('Target user is not available for impersonation');
        }

        self::clearImpersonationState();
        self::revokeSessionByToken($originToken);
        SafeMySQL::gi()->query(
            'UPDATE ?n SET session = NULL WHERE session = ?s',
            Constants::USERS_TABLE,
            $originToken
        );
        self::clearTransportState();
        self::establishSession($targetUserId, (string) ($originSession['transport'] ?? null));

        return [
            'origin_user_id' => $originUserId,
            'target_user_id' => $targetUserId,
            'target_user_role' => (int) ($targetUser['user_role'] ?? 0),
            'status' => 'started',
        ];
    }

    public static function stopImpersonation(): bool {
        AuthService::ensureInfrastructure();

        $originToken = trim((string) Session::get(self::IMPERSONATION_ORIGIN_TOKEN_KEY));
        $originUserId = (int) (Session::get(self::IMPERSONATION_ORIGIN_USER_ID_KEY) ?? 0);
        $originTransport = trim((string) Session::get(self::IMPERSONATION_ORIGIN_TRANSPORT_KEY));
        if ($originToken === '' || $originUserId <= 0) {
            self::clearImpersonationState();
            return false;
        }

        $originSession = self::findSessionByRawToken($originToken);
        if (!is_array($originSession)
            || (int) ($originSession['user_id'] ?? 0) !== $originUserId
            || (int) ($originSession['user_role'] ?? 0) !== Constants::ADMIN
            || (int) ($originSession['deleted'] ?? 0) === 1
            || (int) ($originSession['active'] ?? 0) !== 2) {
            self::clearImpersonationState();
            self::clearTransportState();
            return false;
        }

        $currentToken = (string) (self::readCurrentToken() ?? '');
        if ($currentToken !== '' && $currentToken !== $originToken) {
            self::revokeSessionByToken($currentToken);
            SafeMySQL::gi()->query(
                'UPDATE ?n SET session = NULL WHERE session = ?s',
                Constants::USERS_TABLE,
                $currentToken
            );
        }

        self::clearTransportState();
        self::persistTokenToTransport(self::normalizeTransport($originTransport !== '' ? $originTransport : self::getConfiguredTransport()), $originToken);
        self::clearImpersonationState();

        return true;
    }

    public static function getImpersonationState(): array {
        $originToken = trim((string) Session::get(self::IMPERSONATION_ORIGIN_TOKEN_KEY));
        $originUserId = (int) (Session::get(self::IMPERSONATION_ORIGIN_USER_ID_KEY) ?? 0);
        $currentToken = (string) (self::readCurrentToken() ?? '');
        if ($originToken === '' || $originUserId <= 0 || $currentToken === '' || $originToken === $currentToken) {
            return ['active' => false];
        }

        $originSession = self::findSessionByRawToken($originToken);
        $currentSession = self::findSessionByRawToken($currentToken);
        if (!is_array($originSession) || !is_array($currentSession)) {
            return ['active' => false];
        }

        $originName = (string) (SafeMySQL::gi()->getOne(
            'SELECT name FROM ?n WHERE user_id = ?i LIMIT 1',
            Constants::USERS_TABLE,
            (int) ($originSession['user_id'] ?? 0)
        ) ?? '');
        $targetName = (string) (SafeMySQL::gi()->getOne(
            'SELECT name FROM ?n WHERE user_id = ?i LIMIT 1',
            Constants::USERS_TABLE,
            (int) ($currentSession['user_id'] ?? 0)
        ) ?? '');

        return [
            'active' => true,
            'origin_user_id' => (int) ($originSession['user_id'] ?? 0),
            'origin_user_name' => $originName,
            'target_user_id' => (int) ($currentSession['user_id'] ?? 0),
            'target_user_name' => $targetName,
        ];
    }

    public static function revokeCurrentSession(): void {
        AuthService::ensureInfrastructure();

        $rawToken = self::readCurrentToken();
        if ($rawToken) {
            self::revokeSessionByToken($rawToken);
            SafeMySQL::gi()->query(
                'UPDATE ?n SET session = NULL WHERE session = ?s',
                Constants::USERS_TABLE,
                $rawToken
            );
        }

        self::clearImpersonationState();
        self::clearTransportState();
    }

    public static function revokeAllForUser(int $userId): void {
        AuthService::ensureInfrastructure();

        if ($userId <= 0) {
            return;
        }

        SafeMySQL::gi()->query(
            'UPDATE ?n SET revoked_at = NOW() WHERE user_id = ?i AND revoked_at IS NULL',
            Constants::USERS_AUTH_SESSIONS_TABLE,
            $userId
        );
        SafeMySQL::gi()->query(
            'UPDATE ?n SET session = NULL WHERE user_id = ?i',
            Constants::USERS_TABLE,
            $userId
        );
    }

    public static function clearTransportState(): void {
        Session::un_set(self::STORAGE_KEY);
        Cookies::clear(self::STORAGE_KEY);
    }

    public static function getConfiguredTransport(): string {
        $transport = defined('ENV_AUTH_TRANSPORT') ? strtolower(trim((string) ENV_AUTH_TRANSPORT)) : '';
        if (in_array($transport, ['cookie', 'php_session'], true)) {
            return $transport;
        }

        if (defined('ENV_AUTH_USER') && (int) ENV_AUTH_USER === 0) {
            return 'php_session';
        }

        return 'cookie';
    }

    public static function shouldEnforceIpPolicy(int $userRole, array $options = []): bool {
        if (defined('ENV_ONE_IP_ONE_USER') && (int) ENV_ONE_IP_ONE_USER === 1) {
            return true;
        }

        $flag = (int) (($options['auth']['ip_restricted'] ?? 0));
        if ($flag === 1) {
            return true;
        }

        $roles = defined('ENV_AUTH_IP_RESTRICTED_ROLES') && is_array(ENV_AUTH_IP_RESTRICTED_ROLES)
            ? array_map('intval', ENV_AUTH_IP_RESTRICTED_ROLES)
            : [Constants::ADMIN, Constants::MODERATOR];

        return in_array((int) $userRole, $roles, true);
    }

    private static function readCurrentToken(): ?string {
        return self::getConfiguredTransport() === 'cookie'
            ? Cookies::get(self::STORAGE_KEY)
            : Session::get(self::STORAGE_KEY);
    }

    private static function persistTokenToTransport(string $transport, string $rawToken): void {
        self::clearTransportState();

        if ($transport === 'php_session') {
            Session::regenerateId();
            Session::set(self::STORAGE_KEY, $rawToken);
            return;
        }

        Cookies::set(self::STORAGE_KEY, $rawToken, (int) ENV_TIME_AUTH_SESSION);
    }

    private static function normalizeTransport(string $transport): string {
        return $transport === 'php_session' ? 'php_session' : 'cookie';
    }

    private static function findSessionByRawToken(string $rawToken): ?array {
        $sql = 'SELECT s.auth_session_id, s.user_id, s.ip, u.deleted, u.active, u.user_role, ud.options AS options_json
            FROM ?n AS s
            INNER JOIN ?n AS u ON u.user_id = s.user_id
            LEFT JOIN ?n AS ud ON ud.user_id = u.user_id
            WHERE s.token_hash = ?s
              AND s.revoked_at IS NULL
              AND s.expires_at > NOW()
            LIMIT 1';
        $row = SafeMySQL::gi()->getRow(
            $sql,
            Constants::USERS_AUTH_SESSIONS_TABLE,
            Constants::USERS_TABLE,
            Constants::USERS_DATA_TABLE,
            hash('sha256', $rawToken)
        );

        return is_array($row) ? $row : null;
    }

    private static function revokeSessionByToken(string $rawToken): void {
        SafeMySQL::gi()->query(
            'UPDATE ?n SET revoked_at = NOW() WHERE token_hash = ?s AND revoked_at IS NULL',
            Constants::USERS_AUTH_SESSIONS_TABLE,
            hash('sha256', $rawToken)
        );
    }

    private static function revokeDuplicateSessionsForFingerprint(int $userId, string $transport, string $ip, string $userAgent): void {
        if ($userId <= 0) {
            return;
        }

        SafeMySQL::gi()->query(
            'UPDATE ?n
             SET revoked_at = NOW()
             WHERE user_id = ?i
               AND transport = ?s
               AND ip = ?s
               AND user_agent = ?s
               AND revoked_at IS NULL
               AND expires_at > NOW()',
            Constants::USERS_AUTH_SESSIONS_TABLE,
            $userId,
            $transport,
            $ip,
            $userAgent
        );
    }

    private static function enforceActiveSessionLimit(int $userId): void {
        if ($userId <= 0) {
            return;
        }

        $maxSessions = self::getMaxActiveSessionsPerUser();
        if ($maxSessions <= 0) {
            return;
        }

        $sessionIds = SafeMySQL::gi()->getCol(
            'SELECT auth_session_id
             FROM ?n
             WHERE user_id = ?i
               AND revoked_at IS NULL
               AND expires_at > NOW()
             ORDER BY last_seen_at DESC, created_at DESC, auth_session_id DESC',
            Constants::USERS_AUTH_SESSIONS_TABLE,
            $userId
        );
        $sessionIds = array_map('intval', (array) $sessionIds);
        if (count($sessionIds) <= $maxSessions) {
            return;
        }

        $revokeIds = array_slice($sessionIds, $maxSessions);
        if ($revokeIds === []) {
            return;
        }

        SafeMySQL::gi()->query(
            'UPDATE ?n SET revoked_at = NOW() WHERE auth_session_id IN (?a) AND revoked_at IS NULL',
            Constants::USERS_AUTH_SESSIONS_TABLE,
            $revokeIds
        );
    }

    private static function getMaxActiveSessionsPerUser(): int {
        $configured = defined('ENV_AUTH_MAX_ACTIVE_SESSIONS_PER_USER')
            ? (int) ENV_AUTH_MAX_ACTIVE_SESSIONS_PER_USER
            : 5;

        return max(1, $configured);
    }

    private static function isImpersonationSessionAllowed(int $userId, string $rawToken): bool {
        $originToken = trim((string) Session::get(self::IMPERSONATION_ORIGIN_TOKEN_KEY));
        $originUserId = (int) (Session::get(self::IMPERSONATION_ORIGIN_USER_ID_KEY) ?? 0);
        if ($userId <= 0 || $rawToken === '' || $originToken === '' || $originUserId <= 0 || $originToken === $rawToken) {
            return false;
        }

        $originSession = self::findSessionByRawToken($originToken);
        if (!is_array($originSession)) {
            return false;
        }

        return (int) ($originSession['user_id'] ?? 0) === $originUserId
            && (int) ($originSession['user_role'] ?? 0) === Constants::ADMIN
            && (int) ($originSession['deleted'] ?? 0) === 0
            && (int) ($originSession['active'] ?? 0) === 2;
    }

    private static function clearImpersonationState(): void {
        Session::un_set(self::IMPERSONATION_ORIGIN_TOKEN_KEY);
        Session::un_set(self::IMPERSONATION_ORIGIN_USER_ID_KEY);
        Session::un_set(self::IMPERSONATION_ORIGIN_TRANSPORT_KEY);
    }

    private static function migrateLegacySessionToken(string $rawToken): int|bool {
        $sql = 'SELECT user_id, deleted, active
            FROM ?n
            WHERE session = ?s
            LIMIT 1';
        $legacyUser = SafeMySQL::gi()->getRow($sql, Constants::USERS_TABLE, $rawToken);
        if (!is_array($legacyUser) || (int) ($legacyUser['deleted'] ?? 0) === 1 || (int) ($legacyUser['active'] ?? 0) !== 2) {
            return false;
        }

        self::establishSession((int) $legacyUser['user_id'], self::getConfiguredTransport(), $rawToken);
        return (int) $legacyUser['user_id'];
    }

    private static function isCurrentIpAllowed(string $storedIp): bool {
        $storedIp = trim($storedIp);
        $currentIp = SysClass::getClientIp();
        if ($storedIp === '' || $storedIp === 'unknown' || $currentIp === 'unknown') {
            return true;
        }

        return $storedIp === $currentIp;
    }

    private static function getCurrentUserAgent(): string {
        return mb_substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 1000);
    }
}
