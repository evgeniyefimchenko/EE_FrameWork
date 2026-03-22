<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

class AuthSessionService {

    private const STORAGE_KEY = 'user_session';

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

        if ((int) ($session['deleted'] ?? 0) === 1 || (int) ($session['active'] ?? 0) !== 2) {
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
