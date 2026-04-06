<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

final class ApiKeyService {

    private const RAW_KEY_PREFIX = 'eeak_';

    public static function ensureInfrastructure(bool $force = false): void {
        if (!$force && self::tableExists(Constants::USERS_API_KEYS_TABLE)) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS ?n (
            api_key_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            key_prefix VARCHAR(24) NOT NULL,
            key_hash CHAR(64) NOT NULL,
            label VARCHAR(255) NOT NULL DEFAULT 'Default API key',
            status ENUM('active', 'revoked') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_used_at DATETIME DEFAULT NULL,
            last_used_ip VARCHAR(45) DEFAULT NULL,
            revoked_at DATETIME DEFAULT NULL,
            PRIMARY KEY (api_key_id),
            UNIQUE KEY uq_user_api_keys_hash (key_hash),
            KEY idx_user_api_keys_user_status (user_id, status),
            KEY idx_user_api_keys_last_used (last_used_at),
            CONSTRAINT fk_user_api_keys_user FOREIGN KEY (user_id) REFERENCES ?n(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API-ключи пользователей';";

        SafeMySQL::gi()->query($sql, Constants::USERS_API_KEYS_TABLE, Constants::USERS_TABLE);
    }

    public static function generateForUser(int $userId, string $label = 'Default API key'): array {
        self::ensureInfrastructure();

        $userId = max(0, $userId);
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Invalid user ID for API key generation.');
        }

        self::revokeActiveKeysForUser($userId);

        $rawKey = self::RAW_KEY_PREFIX . bin2hex(random_bytes(24));
        $keyHash = self::hashRawKey($rawKey);
        $keyPrefix = substr($rawKey, 0, 16);
        $label = trim($label) !== '' ? trim($label) : 'Default API key';

        SafeMySQL::gi()->query(
            'INSERT INTO ?n SET ?u',
            Constants::USERS_API_KEYS_TABLE,
            [
                'user_id' => $userId,
                'key_prefix' => $keyPrefix,
                'key_hash' => $keyHash,
                'label' => $label,
                'status' => 'active',
                'revoked_at' => null,
            ]
        );

        $apiKeyId = (int) SafeMySQL::gi()->insertId();

        return [
            'api_key_id' => $apiKeyId,
            'api_key' => $rawKey,
            'key_prefix' => $keyPrefix,
            'label' => $label,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public static function revokeActiveKeysForUser(int $userId): int {
        self::ensureInfrastructure();

        SafeMySQL::gi()->query(
            'UPDATE ?n
             SET status = ?s, revoked_at = NOW()
             WHERE user_id = ?i AND status = ?s',
            Constants::USERS_API_KEYS_TABLE,
            'revoked',
            max(0, $userId),
            'active'
        );

        return (int) SafeMySQL::gi()->affectedRows();
    }

    public static function getActiveKeyMetaForUser(int $userId): ?array {
        self::ensureInfrastructure();

        $row = SafeMySQL::gi()->getRow(
            'SELECT api_key_id, user_id, key_prefix, label, status, created_at, updated_at, last_used_at, last_used_ip
             FROM ?n
             WHERE user_id = ?i AND status = ?s
             ORDER BY api_key_id DESC
             LIMIT 1',
            Constants::USERS_API_KEYS_TABLE,
            max(0, $userId),
            'active'
        );

        return is_array($row) ? $row : null;
    }

    public static function resolveActiveKey(string $rawKey): ?array {
        self::ensureInfrastructure();

        $rawKey = trim($rawKey);
        if ($rawKey === '') {
            return null;
        }

        $row = SafeMySQL::gi()->getRow(
            'SELECT k.api_key_id, k.user_id, k.key_prefix, k.label, k.status, k.created_at, k.updated_at, k.last_used_at, k.last_used_ip,
                    u.user_role, u.active, u.email, u.name
             FROM ?n k
             INNER JOIN ?n u ON u.user_id = k.user_id
             WHERE k.key_hash = ?s AND k.status = ?s
             LIMIT 1',
            Constants::USERS_API_KEYS_TABLE,
            Constants::USERS_TABLE,
            self::hashRawKey($rawKey),
            'active'
        );

        return is_array($row) ? $row : null;
    }

    public static function touchKeyUsage(int $apiKeyId, ?string $ip = null): void {
        self::ensureInfrastructure();

        SafeMySQL::gi()->query(
            'UPDATE ?n SET last_used_at = NOW(), last_used_ip = ?s WHERE api_key_id = ?i',
            Constants::USERS_API_KEYS_TABLE,
            trim((string) $ip),
            max(0, $apiKeyId)
        );
    }

    public static function extractRequestApiKey(): string {
        $headers = [
            $_SERVER['HTTP_X_API_KEY'] ?? '',
            $_SERVER['HTTP_AUTHORIZATION'] ?? '',
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
        ];

        foreach ($headers as $header) {
            $header = trim((string) $header);
            if ($header === '') {
                continue;
            }
            if (stripos($header, 'Bearer ') === 0) {
                return trim(substr($header, 7));
            }
            if (stripos($header, 'ApiKey ') === 0) {
                return trim(substr($header, 7));
            }
            if (stripos($header, self::RAW_KEY_PREFIX) === 0) {
                return $header;
            }
        }

        return '';
    }

    private static function hashRawKey(string $rawKey): string {
        return hash('sha256', trim($rawKey));
    }

    private static function tableExists(string $tableName): bool {
        $db = SafeMySQL::gi();
        $row = $db->getRow('SHOW TABLES LIKE ?s', $tableName);
        return !empty($row);
    }
}
