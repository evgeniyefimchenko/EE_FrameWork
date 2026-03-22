<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

class AuthChallengeService {

    public static function createChallenge(?int $userId, string $purpose, array $payload = [], ?int $ttlSeconds = null): array {
        AuthService::ensureInfrastructure();

        $purpose = self::normalizePurpose($purpose);
        if ($purpose === '') {
            throw new \InvalidArgumentException('Challenge purpose is required');
        }

        if ($userId !== null && $userId > 0) {
            self::invalidateUserChallenges($userId, [$purpose]);
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $ttlSeconds = $ttlSeconds ?? self::getDefaultTtl($purpose);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $sql = 'INSERT INTO ?n SET user_id = ?i, purpose = ?s, token_hash = ?s, payload_json = ?s, expires_at = ?s';
        SafeMySQL::gi()->query(
            $sql,
            Constants::USERS_AUTH_CHALLENGES_TABLE,
            $userId ?: null,
            $purpose,
            $tokenHash,
            $payloadJson === false ? '{}' : $payloadJson,
            $expiresAt
        );

        return [
            'challenge_id' => (int) SafeMySQL::gi()->insertId(),
            'token' => $token,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
        ];
    }

    public static function getPendingChallengeByToken(string $purpose, string $token): ?array {
        AuthService::ensureInfrastructure();

        $purpose = self::normalizePurpose($purpose);
        $token = trim($token);
        if ($purpose === '' || $token === '') {
            return null;
        }

        $sql = 'SELECT * FROM ?n
            WHERE purpose = ?s
              AND token_hash = ?s
              AND consumed_at IS NULL
              AND expires_at > NOW()
            LIMIT 1';
        $row = SafeMySQL::gi()->getRow(
            $sql,
            Constants::USERS_AUTH_CHALLENGES_TABLE,
            $purpose,
            hash('sha256', $token)
        );

        if (!is_array($row)) {
            return null;
        }

        $row['payload_json'] = AuthService::decodeJsonPayload($row['payload_json'] ?? '{}');
        return $row;
    }

    public static function consumeChallenge(string $purpose, string $token): ?array {
        $challenge = self::getPendingChallengeByToken($purpose, $token);
        if (!$challenge) {
            return null;
        }

        $sql = 'UPDATE ?n SET consumed_at = NOW() WHERE challenge_id = ?i AND consumed_at IS NULL';
        SafeMySQL::gi()->query($sql, Constants::USERS_AUTH_CHALLENGES_TABLE, (int) $challenge['challenge_id']);
        if (SafeMySQL::gi()->affectedRows() <= 0) {
            return null;
        }

        return $challenge;
    }

    public static function invalidateUserChallenges(int $userId, array $purposes = []): void {
        AuthService::ensureInfrastructure();

        if ($userId <= 0) {
            return;
        }

        if ($purposes) {
            $purposes = array_values(array_filter(array_map([self::class, 'normalizePurpose'], $purposes)));
            if (!$purposes) {
                return;
            }
            $sql = 'UPDATE ?n SET consumed_at = NOW()
                WHERE user_id = ?i
                  AND consumed_at IS NULL
                  AND purpose IN (?a)';
            SafeMySQL::gi()->query($sql, Constants::USERS_AUTH_CHALLENGES_TABLE, $userId, $purposes);
            return;
        }

        $sql = 'UPDATE ?n SET consumed_at = NOW()
            WHERE user_id = ?i
              AND consumed_at IS NULL';
        SafeMySQL::gi()->query($sql, Constants::USERS_AUTH_CHALLENGES_TABLE, $userId);
    }

    public static function getDefaultTtl(string $purpose): int {
        return match (self::normalizePurpose($purpose)) {
            'activation' => (int) ENV_TIME_ACTIVATION,
            'recovery' => 3600,
            'password_setup' => 86400,
            'account_linking' => 3600,
            'oauth_state' => 900,
            default => 3600,
        };
    }

    private static function normalizePurpose(string $purpose): string {
        return strtolower(trim($purpose));
    }
}
