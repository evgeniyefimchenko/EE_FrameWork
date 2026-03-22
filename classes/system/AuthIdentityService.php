<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

class AuthIdentityService {

    public static function findIdentity(string $provider, string $providerUserId): ?array {
        AuthService::ensureInfrastructure();

        $provider = strtolower(trim($provider));
        $providerUserId = trim($providerUserId);
        if ($provider === '' || $providerUserId === '') {
            return null;
        }

        $sql = 'SELECT * FROM ?n WHERE provider = ?s AND provider_user_id = ?s LIMIT 1';
        $row = SafeMySQL::gi()->getRow($sql, Constants::USERS_AUTH_IDENTITIES_TABLE, $provider, $providerUserId);

        return is_array($row) ? $row : null;
    }

    public static function getUserByIdentity(string $provider, string $providerUserId): ?array {
        AuthService::ensureInfrastructure();

        $sql = 'SELECT u.* 
            FROM ?n AS i
            INNER JOIN ?n AS u ON u.user_id = i.user_id
            WHERE i.provider = ?s AND i.provider_user_id = ?s
            LIMIT 1';
        $row = SafeMySQL::gi()->getRow(
            $sql,
            Constants::USERS_AUTH_IDENTITIES_TABLE,
            Constants::USERS_TABLE,
            strtolower(trim($provider)),
            trim($providerUserId)
        );

        return is_array($row) ? $row : null;
    }

    public static function getIdentitiesForUser(int $userId): array {
        AuthService::ensureInfrastructure();

        if ($userId <= 0) {
            return [];
        }

        $sql = 'SELECT identity_id, provider, provider_user_id, provider_email, provider_email_verified, linked_at, last_login_at
            FROM ?n
            WHERE user_id = ?i
            ORDER BY provider ASC, identity_id ASC';

        return SafeMySQL::gi()->getAll($sql, Constants::USERS_AUTH_IDENTITIES_TABLE, $userId) ?: [];
    }

    public static function upsertIdentity(int $userId, string $provider, array $identity): int {
        AuthService::ensureInfrastructure();

        if ($userId <= 0) {
            throw new \InvalidArgumentException('User ID is required for identity linking');
        }

        $provider = strtolower(trim($provider));
        $providerUserId = trim((string) ($identity['provider_user_id'] ?? ''));
        if ($provider === '' || $providerUserId === '') {
            throw new \InvalidArgumentException('Provider identity is incomplete');
        }

        $payload = [
            'user_id' => $userId,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'provider_email' => trim((string) ($identity['provider_email'] ?? '')),
            'provider_email_verified' => !empty($identity['provider_email_verified']) ? 1 : 0,
            'payload_json' => json_encode($identity, JSON_UNESCAPED_UNICODE),
            'last_login_at' => date('Y-m-d H:i:s'),
        ];

        $existing = self::findIdentity($provider, $providerUserId);
        if ($existing) {
            $identityId = (int) $existing['identity_id'];
            $sql = 'UPDATE ?n SET ?u WHERE identity_id = ?i';
            SafeMySQL::gi()->query($sql, Constants::USERS_AUTH_IDENTITIES_TABLE, $payload, $identityId);
            return $identityId;
        }

        $payload['linked_at'] = date('Y-m-d H:i:s');
        $sql = 'INSERT INTO ?n SET ?u';
        SafeMySQL::gi()->query($sql, Constants::USERS_AUTH_IDENTITIES_TABLE, $payload);

        return (int) SafeMySQL::gi()->insertId();
    }
}
